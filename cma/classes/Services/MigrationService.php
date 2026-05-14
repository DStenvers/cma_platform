<?php
/**
 * Database Migration Service
 *
 * Handles automatic database versioning and migrations for CMA.
 * Reads migration definitions from config/migrations.json and applies
 * pending migrations to keep the database schema in sync.
 */

namespace Cma\Services;

use App\Library\Arr;
use App\Library\Database;
use App\Library\SQL;

class MigrationService
{
    private const MIGRATIONS_FILE = __DIR__ . '/../../config/migrations.json';
    private const VERSION_TABLE = '_cma_version';

    /**
     * Multi-source migration support.
     *
     * The platform is one source ('platform'); consumer projects can
     * register additional sources by setting `migration_sources_extra`
     * in Application config (typically in the project's app.php):
     *
     *   Application::set('migration_sources_extra', [
     *       [
     *           'name'          => 'project',
     *           'file'          => __DIR__ . '/data/project_migrations.json',
     *           'trackingDb'    => 'data',                  // optional, default 'data'
     *           'trackingTable' => '_cma_project_version',  // optional, derived from name otherwise
     *       ],
     *   ]);
     *
     * Each source is loaded independently, tracked in its own version
     * table, and rendered as a separate group in the migration tool UI.
     *
     * @var array<string, array{name:string,file:string,trackingDb:string,trackingTable:string}>
     */
    private array $sources = [];

    private array $migrations = [];
    private array $currentVersions = [];
    private array $errors = [];
    private array $log = [];
    private bool $autoBackup = false;
    private ?BackupService $backupService = null;

    /**
     * Constructor - loads migrations configuration
     *
     * @param bool $autoBackup Whether to automatically backup databases before migration
     */
    public function __construct(bool $autoBackup = false)
    {
        $this->autoBackup = $autoBackup;
        $this->loadMigrations();
    }

    /**
     * Enable or disable auto-backup before migrations
     */
    public function setAutoBackup(bool $enabled): self
    {
        $this->autoBackup = $enabled;
        return $this;
    }

    /**
     * Check if auto-backup is enabled
     */
    public function isAutoBackupEnabled(): bool
    {
        return $this->autoBackup;
    }

    /**
     * Get the BackupService instance (lazy loaded)
     */
    private function getBackupService(): BackupService
    {
        if ($this->backupService === null) {
            $this->backupService = new BackupService();
        }
        return $this->backupService;
    }

    /**
     * Build the sources registry: platform first, then any consumer-
     * registered extras from Application::get('migration_sources_extra').
     */
    private function loadSources(): void
    {
        // Platform is always the first source.  Its tracking table is the
        // existing `_cma_version` so we don't break any prior installs.
        $this->sources = [
            'platform' => [
                'name'          => 'platform',
                'file'          => self::MIGRATIONS_FILE,
                'trackingDb'    => 'data',
                'trackingTable' => self::VERSION_TABLE,
            ],
        ];

        $extra = \App\Library\Application::get('migration_sources_extra', []);
        if (!is_array($extra)) {
            return;
        }
        foreach ($extra as $src) {
            if (!is_array($src) || empty($src['name']) || empty($src['file'])) {
                continue;
            }
            $name = (string)$src['name'];
            if ($name === 'platform' || isset($this->sources[$name])) {
                continue; // 'platform' is reserved; ignore name collisions.
            }
            $this->sources[$name] = [
                'name'          => $name,
                'file'          => (string)$src['file'],
                'trackingDb'    => (string)($src['trackingDb'] ?? 'data'),
                'trackingTable' => (string)($src['trackingTable'] ?? ('_cma_' . $name . '_version')),
            ];
        }
    }

    /**
     * Load migrations from every registered source.
     *
     * Each migration is tagged with `_source` (the source name) so
     * applyMigration knows which tracking table to record into.
     */
    private function loadMigrations(): void
    {
        $this->loadSources();
        $this->migrations = [];

        foreach ($this->sources as $name => $source) {
            if (!file_exists($source['file'])) {
                if ($name === 'platform') {
                    // Only complain about the platform's own file — extra
                    // sources may be conditionally absent in some projects.
                    $this->errors[] = 'Migratie bestand niet gevonden: ' . $source['file'];
                }
                continue;
            }

            $json = file_get_contents($source['file']);
            $data = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->errors[] = "Ongeldige JSON in $name-migratiebestand ({$source['file']}): " . json_last_error_msg();
                continue;
            }

            foreach ($data['migrations'] ?? [] as $migration) {
                $migration['_source'] = $name;
                $this->migrations[] = $migration;
            }
        }
    }

    /**
     * @return array<string, array{name:string,file:string,trackingDb:string,trackingTable:string}>
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    /**
     * Get current database versions
     *
     * Version tracking uses the 'data' database as the single source of truth.
     * Other databases are listed for information but don't affect version tracking.
     *
     * @return array ['database' => 'version', ...]
     */
    public function getCurrentVersions(): array
    {
        if (!empty($this->currentVersions)) {
            return $this->currentVersions;
        }

        // One latest-version per source, computed from that source's own
        // tracking table.  Each source can target a different database;
        // when the connection or the version table doesn't exist yet,
        // the source reports '0.0.0' (so all its migrations look pending).
        foreach ($this->sources as $name => $source) {
            try {
                $conn = Database::getConnection($source['trackingDb']);
                if ($conn === null) {
                    $this->currentVersions[$name] = 'geen verbinding';
                    continue;
                }
                if (!$this->versionTableExists($conn, $source['trackingTable'])) {
                    $this->currentVersions[$name] = '0.0.0';
                    continue;
                }
                $version = $this->getLatestVersion($conn, $source['trackingTable']);
                $this->currentVersions[$name] = $version ?: '0.0.0';
            } catch (\Exception $e) {
                $this->currentVersions[$name] = 'fout';
                $this->errors[] = "Fout bij verbinden met {$source['trackingDb']}: " . $e->getMessage();
            }
        }

        // Backwards-compat alias: callers built before multi-source
        // expected $versions['data'] to mean the platform's version.
        if (isset($this->currentVersions['platform']) && !isset($this->currentVersions['data'])) {
            $this->currentVersions['data'] = $this->currentVersions['platform'];
        }

        // Connection status for the other named databases (informational).
        foreach (['rep', 'users'] as $db) {
            if (isset($this->currentVersions[$db])) {
                continue;
            }
            try {
                $conn = Database::getConnection($db);
                $this->currentVersions[$db] = $conn === null ? 'geen verbinding' : 'verbonden';
            } catch (\Exception $e) {
                $this->currentVersions[$db] = 'fout';
            }
        }

        return $this->currentVersions;
    }

    /**
     * Check if a version-tracking table exists.  Default table name is the
     * platform's `_cma_version`; pass a custom name for additional sources.
     */
    private function versionTableExists(\PDO $conn, string $tableName = self::VERSION_TABLE): bool
    {
        try {
            $driver = $conn->getAttribute(\PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                // SQLite - check sqlite_master.  Table name embedded as a
                // literal because $tableName is service-controlled (never
                // user input); same reasoning for the other branches.
                $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name='" . $tableName . "'";
                $stmt = $conn->query($sql);
                return $stmt->fetch() !== false;
            } elseif ($driver === 'odbc') {
                // Access — try to read the table; thrown exception means absent.
                try {
                    $conn->query("SELECT TOP 1 version FROM " . $tableName);
                    return true;
                } catch (\Exception $e) {
                    return false;
                }
            } else {
                // SQL Server / others
                $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '" . $tableName . "'";
                $stmt = $conn->query($sql);
                return (int)$stmt->fetchColumn() > 0;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the latest applied version from a tracking table.  Default
     * table is the platform's; pass a custom name for additional sources.
     */
    private function getLatestVersion(\PDO $conn, string $tableName = self::VERSION_TABLE): ?string
    {
        try {
            $driver = $conn->getAttribute(\PDO::ATTR_DRIVER_NAME);

            // Tiebreak on id DESC so that batches of migrations recorded
            // within the same second (Access DATETIME has 1-second resolution)
            // resolve to the actual latest insert.  Without the tiebreaker, a
            // single CLI run that applies dozens of fast no-op migrations
            // would persist a non-deterministic version, and re-runs would
            // advance only partially each time.
            if ($driver === 'sqlite') {
                $sql = "SELECT version FROM " . $tableName . " ORDER BY applied_at DESC, id DESC LIMIT 1";
            } else {
                $sql = "SELECT TOP 1 version FROM " . $tableName . " ORDER BY applied_at DESC, id DESC";
            }

            $stmt = $conn->query($sql);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ? $row['version'] : null;
        } catch (\Exception $e) {
            $this->log[] = "Waarschuwing: Kan versie niet ophalen uit $tableName: " . $e->getMessage();
            return null;
        }
    }

    /**
     * Get pending migrations
     *
     * Uses the 'data' database version as the source of truth.
     *
     * @return array List of migrations that need to be applied
     */
    public function getPendingMigrations(): array
    {
        $versions = $this->getCurrentVersions();
        $pending = [];

        // Each migration is compared against ITS source's current version
        // (not a single global one).  Source order is preserved so that
        // applyAllPending walks platform first, then project, then any
        // module sources — predictable, and lets a project migration
        // depend on platform tables that landed earlier in the same run.
        foreach ($this->migrations as $migration) {
            if (!empty($migration['disabled'])) {
                continue;
            }
            $sourceName = (string)($migration['_source'] ?? 'platform');
            $current = $versions[$sourceName] ?? '0.0.0';
            if ($current === 'fout' || $current === 'geen verbinding') {
                $current = '0.0.0';
            }
            if (version_compare($migration['version'], $current, '>')) {
                $pending[] = $migration;
            }
        }

        // Sort each source's pending list by version, but preserve the
        // source registration order across sources.  We do this by sorting
        // with a stable key (source-index, version) — `array_search` on
        // sources is fine here, the list is tiny.
        $sourceNames = array_keys($this->sources);
        usort($pending, function ($a, $b) use ($sourceNames) {
            $sa = (int)array_search($a['_source'] ?? 'platform', $sourceNames, true);
            $sb = (int)array_search($b['_source'] ?? 'platform', $sourceNames, true);
            if ($sa !== $sb) { return $sa <=> $sb; }
            return version_compare($a['version'], $b['version']);
        });

        return $pending;
    }

    /**
     * Apply all pending migrations in order
     *
     * @return array ['success' => bool, 'applied' => [], 'errors' => [], 'log' => []]
     */
    public function applyAllPending(): array
    {
        return $this->applyUpToVersion(null);
    }

    /**
     * Apply pending migrations up to (and including) a specific version
     *
     * @param string|null $targetVersion The version to apply up to, or null for all pending
     * @return array ['success' => bool, 'applied' => [], 'errors' => [], 'log' => []]
     */
    public function applyUpToVersion(?string $targetVersion): array
    {
        $pending = $this->getPendingMigrations();
        $applied = [];
        $errors = [];

        // Filter pending migrations if target version specified
        if ($targetVersion !== null) {
            $pending = array_filter($pending, function($migration) use ($targetVersion) {
                return version_compare($migration['version'], $targetVersion, '<=');
            });
            $pending = array_values($pending); // Re-index array
        }

        if (empty($pending)) {
            $this->log[] = "Geen openstaande migraties gevonden.";
            return [
                'success' => true,
                'applied' => [],
                'errors' => [],
                'log' => $this->log
            ];
        }

        $targetMsg = $targetVersion !== null ? " (tot versie $targetVersion)" : "";
        $this->log[] = "Start migratie: " . count($pending) . " versie(s) toe te passen$targetMsg...";
        $this->log[] = "";

        foreach ($pending as $migration) {
            $this->log[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
            $this->log[] = "Versie " . $migration['version'] . ": " . $migration['description'];
            $this->log[] = "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

            $result = $this->applyMigration($migration);

            if ($result['success']) {
                $applied[] = $migration['version'];
                $this->log[] = "✓ Versie " . $migration['version'] . " succesvol toegepast";
                $this->log[] = "";
            } else {
                $errors[] = [
                    'version' => $migration['version'],
                    'error' => $result['error'],
                    'details' => $result['details'] ?? null
                ];
                $this->log[] = "✗ Versie " . $migration['version'] . " MISLUKT";
                $this->log[] = "  Fout: " . $result['error'];
                if (!empty($result['details'])) {
                    $this->log[] = "  Details: " . $result['details'];
                }
                if (!empty($result['sql'])) {
                    $this->log[] = "  SQL: " . $result['sql'];
                }
                $this->log[] = "";
                $this->log[] = "Migratie gestopt vanwege fout. Los het probleem op en probeer opnieuw.";
                break;
            }
        }

        if (empty($errors)) {
            $this->log[] = "";
            $this->log[] = "════════════════════════════════════════════════";
            $this->log[] = "Alle migraties succesvol toegepast!";
            $this->log[] = "════════════════════════════════════════════════";
        }

        return [
            'success' => empty($errors),
            'applied' => $applied,
            'errors' => $errors,
            'log' => $this->log
        ];
    }

    /**
     * Backup databases affected by a migration
     *
     * @param array $migration Migration definition
     * @return array ['success' => bool, 'backups' => [], 'errors' => []]
     */
    private function backupAffectedDatabases(array $migration): array
    {
        $backups = [];
        $errors = [];

        // Collect all databases affected by this migration
        $affectedDatabases = [];
        foreach ($migration['changes'] ?? [] as $change) {
            $db = $change['database'] ?? null;
            if ($db && !in_array($db, $affectedDatabases)) {
                $affectedDatabases[] = $db;
            }
        }

        if (empty($affectedDatabases)) {
            return ['success' => true, 'backups' => [], 'errors' => []];
        }

        // Load database configurations
        $databasesFile = __DIR__ . '/../../../data/databases.json';
        $databaseConfigs = [];
        if (file_exists($databasesFile)) {
            $content = file_get_contents($databasesFile);
            $config = json_decode($content, true);
            foreach ($config['databases'] ?? [] as $dbConfig) {
                $name = strtolower($dbConfig['name'] ?? '');
                $databaseConfigs[$name] = $dbConfig;
            }
        }

        // Map migration aliases to database config names
        $aliasMap = ['rep' => 'repository', 'data' => 'database', 'users' => 'cmausers'];

        $backupService = $this->getBackupService();

        foreach ($affectedDatabases as $dbName) {
            $key = strtolower($dbName);
            $dbConfig = $databaseConfigs[$key] ?? $databaseConfigs[$aliasMap[$key] ?? ''] ?? null;

            if (!$dbConfig) {
                $this->log[] = "  ⚠ Database '$dbName' niet gevonden in configuratie, backup overgeslagen";
                continue;
            }

            $this->log[] = "  Backup maken van database '$dbName'...";

            $result = $backupService->createMigrationBackup(
                $dbConfig,
                $migration['version'],
                $migration['description'] ?? ''
            );

            if ($result['success']) {
                $backups[] = [
                    'database' => $dbName,
                    'file' => $result['file'] ?? ''
                ];
                $this->log[] = "  ✓ Backup aangemaakt: " . ($result['file'] ?? '');
            } else {
                $errors[] = [
                    'database' => $dbName,
                    'error' => $result['message'] ?? 'Onbekende fout'
                ];
                $this->log[] = "  ✗ Backup mislukt: " . ($result['message'] ?? 'Onbekende fout');
            }
        }

        return [
            'success' => empty($errors),
            'backups' => $backups,
            'errors' => $errors
        ];
    }

    /**
     * Apply a single migration
     *
     * @param array $migration Migration definition
     * @return array ['success' => bool, 'error' => string|null, 'details' => string|null]
     */
    public function applyMigration(array $migration): array
    {
        $changeCount = count($migration['changes'] ?? []);

        // Auto-backup if enabled
        if ($this->autoBackup) {
            $this->log[] = "  Automatisch backup staat aan.";
            $backupResult = $this->backupAffectedDatabases($migration);

            if (!$backupResult['success']) {
                // Backup failure is non-fatal - log warning and continue
                $details = implode(', ', array_column($backupResult['errors'] ?? [], 'error'));
                $this->log[] = "  ⚠ Backup overgeslagen: " . ($details ?: 'onbekende fout');
            }
            $this->log[] = "";
        }

        $this->log[] = "  $changeCount wijziging(en) uit te voeren...";

        $changeIndex = 0;
        foreach ($migration['changes'] as $change) {
            $changeIndex++;
            $changeDesc = $this->describeChange($change);

            try {
                $result = $this->applyChange($change);

                if ($result['success']) {
                    $this->log[] = "  [$changeIndex/$changeCount] ✓ $changeDesc";
                    if (!empty($result['message'])) {
                        $this->log[] = "              " . $result['message'];
                    }
                    if (!empty($result['sql'])) {
                        $this->log[] = "              SQL: " . $result['sql'];
                    }
                } else {
                    if (!empty($change['optional'])) {
                        $this->log[] = "  [$changeIndex/$changeCount] ⊘ $changeDesc (optioneel, overgeslagen)";
                        $this->log[] = "              " . ($result['error'] ?? 'onbekende fout');
                    } else {
                        // Include debug info in error
                        $debugInfo = '';
                        if (!empty($result['debug'])) {
                            $debugInfo = ' | Debug: ' . json_encode($result['debug'], JSON_UNESCAPED_UNICODE);
                        }
                        return [
                            'success' => false,
                            'error' => $changeDesc,
                            'details' => ($result['error'] ?? 'Onbekende fout') . $debugInfo,
                            'sql' => $result['sql'] ?? null,
                            'debug' => $result['debug'] ?? null
                        ];
                    }
                }
            } catch (\Exception $e) {
                if (!empty($change['optional'])) {
                    $this->log[] = "  [$changeIndex/$changeCount] ⊘ $changeDesc (optioneel, overgeslagen)";
                    $this->log[] = "              " . $e->getMessage();
                } else {
                    return [
                        'success' => false,
                        'error' => $changeDesc,
                        'details' => $e->getMessage()
                    ];
                }
            }
        }

        // Record migration in all affected databases
        $this->recordMigration($migration);

        return ['success' => true, 'error' => null];
    }

    /**
     * Check whether the named database has a configured connection string.
     * Used by applyChange() to skip migrations that target databases the
     * project doesn't use (e.g. 'rep' on installs that don't ship a
     * repository database).
     */
    private function databaseIsConfigured(string $name): bool
    {
        $value = \App\Library\Application::get('conn_' . $name, '');
        return is_string($value) && trim($value) !== '';
    }

    /**
     * Describe a change for logging
     */
    private function describeChange(array $change): string
    {
        $type = $change['type'] ?? 'onbekend';

        switch ($type) {
            case 'createVersionTable':
                return "Versietabel aanmaken in '{$change['database']}'";
            case 'addColumn':
                return "Kolom '{$change['column']}' toevoegen aan {$change['database']}.{$change['table']}";
            case 'dropColumn':
                return "Kolom '{$change['column']}' verwijderen uit {$change['database']}.{$change['table']}";
            case 'addIndex':
                return "Index '{$change['indexName']}' aanmaken op {$change['database']}.{$change['table']}";
            case 'dropIndex':
                $db = $change['database'] ?? 'rep';
                return "Index '{$change['indexName']}' verwijderen van {$db}.{$change['table']}";
            case 'runSql':
                $sql = $change['sql'] ?? '';
                $preview = strlen($sql) > 50 ? substr($sql, 0, 50) . '...' : $sql;
                return "SQL uitvoeren in '{$change['database']}': $preview";
            case 'runSqlScript':
                return "SQL script uitvoeren: {$change['script']}";
            case 'runPhp':
                return "PHP script uitvoeren: {$change['script']}";
            case 'renameTable':
                return "Tabel hernoemen: {$change['database']}.{$change['oldName']} -> {$change['newName']}";
            case 'dropTable':
                return "Tabel verwijderen: {$change['database']}.{$change['table']}";
            default:
                return "Onbekend type: $type";
        }
    }

    /**
     * Apply a single change
     */
    private function applyChange(array $change): array
    {
        $type = $change['type'] ?? '';

        // Skip when any required database has no configured connection string.
        // Required databases come from:
        //   (a) the change's 'database' field for typed schema operations
        //       (createVersionTable / addColumn / dropColumn / addIndex /
        //        dropIndex / runSql / runSqlScript / renameTable / dropTable /
        //        updateData)
        //   (b) an explicit 'requires' array on the change for runPhp and
        //       other operations that consume one or more databases at
        //       runtime but don't carry a 'database' field
        // The migration is still recorded as applied (in the 'data' database)
        // so the runner doesn't retry it on every boot.
        $required = [];
        if (!empty($change['database'])) {
            $required[] = $change['database'];
        }
        if (!empty($change['requires']) && is_array($change['requires'])) {
            foreach ($change['requires'] as $r) {
                if (is_string($r) && $r !== '') {
                    $required[] = $r;
                }
            }
        }
        foreach (array_unique($required) as $db) {
            if (!$this->databaseIsConfigured($db)) {
                return [
                    'success' => true,
                    'message' => "Overgeslagen: database '$db' is niet geconfigureerd"
                ];
            }
        }

        switch ($type) {
            case 'createVersionTable':
                return $this->createVersionTable($change['database']);

            case 'addColumn':
                return $this->addColumn(
                    $change['database'],
                    $change['table'],
                    $change['column'],
                    $change['dataType'],
                    $change['default'] ?? null
                );

            case 'dropColumn':
                return $this->dropColumn(
                    $change['database'],
                    $change['table'],
                    $change['column']
                );

            case 'addIndex':
                return $this->addIndex(
                    $change['database'],
                    $change['table'],
                    $change['columns'],
                    $change['indexName']
                );

            case 'dropIndex':
                return $this->dropIndex(
                    $change['database'] ?? 'rep',
                    $change['table'],
                    $change['indexName']
                );

            case 'runSql':
                return $this->runSql($change['database'], $change['sql']);

            case 'runSqlScript':
                return $this->runSqlScript($change['script'], $change['database'] ?? 'data');

            case 'runPhp':
                return $this->runPhpScript($change['script']);

            case 'updateData':
                return $this->runSql($change['database'], $change['sql']);

            case 'renameTable':
                return $this->renameTable(
                    $change['database'],
                    $change['oldName'],
                    $change['newName']
                );

            case 'dropTable':
                return $this->dropTable(
                    $change['database'],
                    $change['table']
                );

            default:
                return [
                    'success' => false,
                    'error' => "Onbekend wijzigingstype: $type"
                ];
        }
    }

    /**
     * Create a version-tracking table.
     *
     * Default $tableName is the platform's `_cma_version`; pass a custom
     * name (e.g. '_cma_project_version') to create the tracking table for
     * an additional source.  Idempotent: returns success if the table
     * already exists.
     *
     * Project sources whose tracking column widths differ from the
     * platform schema (e.g. project versions wider than 20 chars) can
     * pass a longer column via $versionWidth.
     */
    private function createVersionTable(string $database, string $tableName = self::VERSION_TABLE, int $versionWidth = 120): array
    {
        try {
            $conn = Database::getConnection($database);

            if ($conn === null) {
                return [
                    'success' => false,
                    'error' => "Kan geen verbinding maken met database '$database'"
                ];
            }

            if ($this->versionTableExists($conn, $tableName)) {
                return ['success' => true, 'error' => null, 'message' => 'Tabel bestaat al'];
            }

            $driver = $conn->getAttribute(\PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                $sql = "CREATE TABLE " . $tableName . " (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    version TEXT NOT NULL,
                    applied_at TEXT,
                    description TEXT
                )";
            } elseif ($driver === 'odbc') {
                $sql = "CREATE TABLE " . $tableName . " (
                    id AUTOINCREMENT PRIMARY KEY,
                    version VARCHAR($versionWidth) NOT NULL,
                    applied_at DATETIME,
                    description VARCHAR(255)
                )";
            } else {
                $sql = "CREATE TABLE " . $tableName . " (
                    id INT IDENTITY(1,1) PRIMARY KEY,
                    version VARCHAR($versionWidth) NOT NULL,
                    applied_at DATETIME NOT NULL DEFAULT GETDATE(),
                    description NVARCHAR(255)
                )";
            }

            $conn->exec($sql);

            return ['success' => true, 'error' => null, 'message' => 'Tabel aangemaakt'];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Kan versietabel '$tableName' niet aanmaken in '$database': " . $e->getMessage()
            ];
        }
    }

    /**
     * Add column to table
     */
    private function addColumn(string $database, string $table, string $column, string $dataType, ?string $default): array
    {
        $conn = Database::getConnection($database);

        if ($conn === null) {
            return [
                'success' => false,
                'error' => "Kan geen verbinding maken met database '$database'"
            ];
        }

        return Database::addColumnPDO($conn, $table, $column, $dataType, $default);
    }

    /**
     * Drop table
     */
    private function dropTable(string $database, string $table): array
    {
        $conn = Database::getConnection($database);

        if ($conn === null) {
            return [
                'success' => false,
                'error' => "Kan geen verbinding maken met database '$database'"
            ];
        }

        // Check if table exists
        if (!Database::tableExistsPDO($conn, $table)) {
            return ['success' => true, 'error' => null, 'message' => 'Tabel bestaat niet (al verwijderd)'];
        }

        // Detecteer relaties voor betere foutmelding
        $relations = $this->getTableRelations($conn, $table);

        try {
            $conn->exec("DROP TABLE [$table]");
            return ['success' => true, 'error' => null, 'message' => 'Tabel verwijderd'];
        } catch (\Exception $e) {
            $errorMsg = "Kan tabel '$table' niet verwijderen: " . $e->getMessage();

            // Voeg relatie-informatie toe als die beschikbaar is
            if (!empty($relations)) {
                $errorMsg .= "\n\nGevonden relaties voor '$table':";
                foreach ($relations as $rel) {
                    $errorMsg .= "\n  - {$rel['type']}: {$rel['description']}";
                }
                $errorMsg .= "\n\nVerwijder eerst de relaties of de gerelateerde tabellen.";
            }

            return [
                'success' => false,
                'error' => $errorMsg
            ];
        }
    }

    /**
     * Haal relaties op voor een tabel (Access/ODBC specifiek)
     */
    private function getTableRelations(\PDO $conn, string $table): array
    {
        $relations = [];

        try {
            // Methode 1: Probeer MSysRelationships te lezen (Access systeem tabel)
            // Dit werkt alleen als de gebruiker rechten heeft op systeemtabellen
            $sql = "SELECT szRelationship, szReferencedObject, szColumn, szReferencedColumn " .
                   "FROM MSysRelationships " .
                   "WHERE szObject = ? OR szReferencedObject = ?";

            $stmt = $conn->prepare($sql);
            $stmt->execute([$table, $table]);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $relName = $row['szRelationship'] ?? 'onbekend';
                $refTable = $row['szReferencedObject'] ?? '';
                $column = $row['szColumn'] ?? '';
                $refColumn = $row['szReferencedColumn'] ?? '';

                $relations[] = [
                    'type' => 'Foreign Key',
                    'description' => "Relatie '$relName': kolom '$column' verwijst naar '$refTable.$refColumn'"
                ];
            }
        } catch (\Exception $e) {
            // MSysRelationships niet toegankelijk, probeer alternatieve methode
        }

        // Methode 2: Zoek naar tabellen die mogelijk naar deze tabel verwijzen (op basis van naamgeving)
        try {
            // Zoek naar kolommen die 'fk' + tabelnaam bevatten (veelgebruikte conventie)
            $tableWithoutPrefix = preg_replace('/^tbl/i', '', $table);

            // Haal alle tabellen op
            $tables = [];
            $result = $conn->query("SELECT Name FROM MSysObjects WHERE Type=1 AND Name NOT LIKE 'MSys*'");
            if ($result) {
                while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
                    $tables[] = $row['Name'];
                }
            }

            // Zoek naar foreign key kolommen in andere tabellen
            foreach ($tables as $otherTable) {
                if (strcasecmp($otherTable, $table) === 0) continue;

                try {
                    // Probeer schema info te krijgen
                    $schemaResult = $conn->query("SELECT TOP 1 * FROM [$otherTable]");
                    if ($schemaResult) {
                        for ($i = 0; $i < $schemaResult->columnCount(); $i++) {
                            $colMeta = $schemaResult->getColumnMeta($i);
                            $colName = $colMeta['name'] ?? '';

                            // Check of kolomnaam suggereert dat het een FK is naar onze tabel
                            if (stripos($colName, 'fk' . $tableWithoutPrefix) !== false ||
                                stripos($colName, $tableWithoutPrefix . 'ID') !== false ||
                                stripos($colName, $tableWithoutPrefix . '_ID') !== false) {
                                $relations[] = [
                                    'type' => 'Mogelijke FK (op naam)',
                                    'description' => "Tabel '$otherTable' heeft kolom '$colName' die mogelijk verwijst naar '$table'"
                                ];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Negeer fouten bij individuele tabellen
                }
            }
        } catch (\Exception $e) {
            // Als MSysObjects ook niet werkt, voeg een algemene hint toe
            $relations[] = [
                'type' => 'Info',
                'description' => "Kan relaties niet automatisch detecteren. Controleer handmatig in Access of er relaties zijn gedefinieerd."
            ];
        }

        // Methode 3: Check of er indexes zijn die naar deze tabel verwijzen
        try {
            $sql = "SELECT Name FROM MSysObjects WHERE Type=1 AND Name LIKE '%$table%' AND Name <> ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$table]);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $relations[] = [
                    'type' => 'Gerelateerde tabel',
                    'description' => "Tabel '{$row['Name']}' bevat '$table' in de naam"
                ];
            }
        } catch (\Exception $e) {
            // Negeer
        }

        return $relations;
    }

    /**
     * Rename table
     */
    private function renameTable(string $database, string $oldName, string $newName): array
    {
        $conn = Database::getConnection($database);

        if ($conn === null) {
            return [
                'success' => false,
                'error' => "Kan geen verbinding maken met database '$database'"
            ];
        }

        // Check if old table exists using Database helper
        if (!Database::tableExistsPDO($conn, $oldName)) {
            // Check if new table already exists (migration might have been partially applied)
            if (Database::tableExistsPDO($conn, $newName)) {
                return ['success' => true, 'error' => null, 'message' => 'Tabel is al hernoemd'];
            }
            // Neither old nor new table exists — skip (table doesn't apply to this site)
            return ['success' => true, 'error' => null, 'message' => "Overgeslagen: tabel '$oldName' bestaat niet in deze database"];
        }

        return Database::renameTablePDO($conn, $oldName, $newName);
    }

    /**
     * Drop column from table
     */
    private function dropColumn(string $database, string $table, string $column): array
    {
        $conn = Database::getConnection($database);

        if ($conn === null) {
            return [
                'success' => false,
                'error' => "Kan geen verbinding maken met database '$database'"
            ];
        }

        return Database::dropColumnPDO($conn, $table, $column);
    }

    /**
     * Add index to table
     */
    private function addIndex(string $database, string $table, array $columns, string $indexName): array
    {
        $conn = Database::getConnection($database);

        if ($conn === null) {
            return [
                'success' => false,
                'error' => "Kan geen verbinding maken met database '$database'"
            ];
        }

        return Database::addIndexPDO($conn, $table, $columns, $indexName);
    }

    /**
     * Drop index from table
     */
    private function dropIndex(string $database, string $table, string $indexName): array
    {
        $conn = Database::getConnection($database);

        if ($conn === null) {
            return [
                'success' => false,
                'error' => "Kan geen verbinding maken met database '$database'"
            ];
        }

        try {
            return Database::dropIndexPDO($conn, $table, $indexName);
        } catch (\Throwable $e) {
            // Index doesn't exist — skip silently
            return ['success' => true, 'error' => null, 'message' => "Overgeslagen: index '$indexName' bestaat niet"];
        }
    }

    /**
     * Run raw SQL
     */
    private function runSql(string $database, string $sql): array
    {
        try {
            $conn = Database::getConnection($database);

            if ($conn === null) {
                return [
                    'success' => false,
                    'error' => "Kan geen verbinding maken met database '$database'"
                ];
            }

            // Translate SQL for database dialect
            $sql = SQL::processSQL($conn, $sql);

            $affected = $conn->exec($sql);

            return ['success' => true, 'error' => null, 'message' => "$affected rij(en) aangepast"];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "SQL uitvoering mislukt: " . $e->getMessage() . " (SQL: $sql)"
            ];
        }
    }

    /**
     * Run SQL script file
     */
    /**
     * Run a multi-statement SQL script against $database.
     *
     * Path resolution:
     *   - Relative paths are resolved from the cma/ directory (so platform
     *     scripts can use 'migrations/sql/foo.sql').  Project scripts
     *     reference up the tree, e.g. '../migrations/001_users.sql'.
     *   - Absolute paths are used as-is (handy for tests / one-offs).
     *
     * Statement splitting:
     *   - `;` followed by end-of-line  — standard SQL
     *   - `GO` on its own line          — T-SQL batch separator
     *   - `-- ;;` on its own line       — DDL-only convention for scripts
     *     where individual statements must NOT contain a trailing `;`
     *     (Access ODBC, for instance — some drivers reject the trailing `;`)
     *
     * Each statement is trimmed, has leading comment-only lines stripped
     * (so a script can have a header comment block + a statement in the
     * same chunk), and is then executed via $conn->exec.
     */
    private function runSqlScript(string $scriptPath, string $database = 'data'): array
    {
        $fullPath = (strlen($scriptPath) > 1 && ($scriptPath[0] === '/' || $scriptPath[1] === ':'))
            ? $scriptPath
            : __DIR__ . '/../../' . $scriptPath;

        if (!file_exists($fullPath)) {
            return [
                'success' => false,
                'error' => "Script bestand niet gevonden: $scriptPath"
            ];
        }

        try {
            $conn = Database::getConnection($database);
            if ($conn === null) {
                return [
                    'success' => false,
                    'error' => "Geen verbinding met database '$database' voor script '$scriptPath'"
                ];
            }

            $sql = file_get_contents($fullPath);
            $statements = preg_split(
                '/;\s*\n|^GO\s*$|^\s*--\s*;;\s*$/m',
                $sql,
                -1,
                PREG_SPLIT_NO_EMPTY
            );

            $executed = 0;
            foreach ($statements as $statement) {
                $cleaned = self::stripLeadingComments($statement);
                if ($cleaned === '') {
                    continue;
                }
                $conn->exec($cleaned);
                $executed++;
            }

            return ['success' => true, 'error' => null, 'message' => "$executed statement(s) uitgevoerd"];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Script uitvoering mislukt ($scriptPath): " . $e->getMessage()
            ];
        }
    }

    /**
     * Trim leading blank / comment-only lines from a SQL fragment.  Used
     * by runSqlScript so statements can start with a header comment block.
     * Doesn't touch comments mid-statement (those stay valid SQL).
     */
    private static function stripLeadingComments(string $sql): string
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $sql));
        $i = 0;
        while ($i < count($lines)) {
            $trimmed = trim($lines[$i]);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                $i++;
                continue;
            }
            break;
        }
        return $i >= count($lines) ? '' : trim(implode("\n", array_slice($lines, $i)));
    }

    /**
     * Run PHP script file
     *
     * Runs the script directly with output buffering to avoid issues with
     * HTTP requests going through URL rewrite rules.
     */
    private function runPhpScript(string $scriptPath): array
    {
        $fullPath = __DIR__ . '/../../' . $scriptPath;

        if (!file_exists($fullPath)) {
            return [
                'success' => false,
                'error' => "PHP script niet gevonden: $scriptPath"
            ];
        }

        // Run script directly with output buffering
        return $this->runPhpScriptDirect($fullPath, $scriptPath);
    }

    /**
     * Run PHP script directly with output buffering
     */
    private function runPhpScriptDirect(string $fullPath, string $scriptPath): array
    {
        try {
            // Define constant so script knows it's running as migration
            if (!defined('MIGRATION_RUNNING')) {
                define('MIGRATION_RUNNING', true);
            }

            // Capture output
            ob_start();

            // Include the script - it may return an array with success/message
            $result = include $fullPath;

            $output = ob_get_clean();

            // Check for PHP error tags in output
            if (preg_match('/\[PHP_ERROR\](.*?)\[\/PHP_ERROR\]/s', $output, $matches)) {
                $errorInfo = trim($matches[1]);
                return [
                    'success' => false,
                    'error' => "PHP Error: $errorInfo"
                ];
            }

            // Check if script returned a structured result array
            if (Arr::isArray($result) && isset($result['success'])) {
                return [
                    'success' => (bool)$result['success'],
                    'error' => $result['success'] ? null : ($result['message'] ?? $result['error'] ?? 'Onbekende fout'),
                    'message' => $result['message'] ?? 'Script uitgevoerd'
                ];
            }

            // Check result for explicit false
            if ($result === false) {
                return [
                    'success' => false,
                    'error' => "PHP script gaf false terug: $scriptPath\n" . strip_tags($output)
                ];
            }

            // No structured result - check output for success/error markers
            if (!empty($output)) {
                $cleanOutput = trim(strip_tags($output));
                if (strpos($output, '✗') !== false || strpos($output, 'mislukt') !== false || strpos($output, 'Fout:') !== false) {
                    return [
                        'success' => false,
                        'error' => $cleanOutput
                    ];
                }
                return [
                    'success' => true,
                    'error' => null,
                    'message' => $cleanOutput ?: "Script uitgevoerd"
                ];
            }

            return [
                'success' => true,
                'error' => null,
                'message' => "Script uitgevoerd"
            ];
        } catch (\Exception $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            return [
                'success' => false,
                'error' => "PHP script uitvoering mislukt ($scriptPath): " . $e->getMessage()
            ];
        }
    }

    /**
     * Record an applied migration in its source's tracking table.
     *
     * The tracking destination is derived from $migration['_source']
     * (set by loadMigrations).  Falls back to 'platform' for callers
     * that hand-build a migration array without going through
     * loadMigrations.
     */
    private function recordMigration(array $migration): void
    {
        $sourceName = (string)($migration['_source'] ?? 'platform');
        $source = $this->sources[$sourceName] ?? $this->sources['platform'] ?? null;
        if ($source === null) {
            $this->log[] = "  ⚠ Geen migratiebron geconfigureerd voor '$sourceName'";
            return;
        }

        $now = date('Y-m-d H:i:s');

        try {
            $conn = Database::getConnection($source['trackingDb']);

            if ($conn === null) {
                $this->log[] = "  ⚠ Kan geen verbinding maken met '{$source['trackingDb']}' database";
                return;
            }

            // Auto-create the version table on first use of this source.
            // For 'platform' the table normally already exists from the
            // 1.0.0 migration; this branch handles project sources whose
            // tracking table hasn't been created yet.
            if (!$this->versionTableExists($conn, $source['trackingTable'])) {
                $createResult = $this->createVersionTable($source['trackingDb'], $source['trackingTable']);
                if (!($createResult['success'] ?? false)) {
                    $this->log[] = "  ⚠ Kan versietabel '{$source['trackingTable']}' niet aanmaken: "
                        . ($createResult['error'] ?? 'onbekende fout');
                    return;
                }
            }

            $driver = $conn->getAttribute(\PDO::ATTR_DRIVER_NAME);

            if ($driver === 'odbc') {
                $sql = "INSERT INTO " . $source['trackingTable']
                     . " (version, applied_at, description) VALUES (?, #" . $now . "#, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $migration['version'],
                    $migration['description'] ?? ''
                ]);
            } else {
                $sql = "INSERT INTO " . $source['trackingTable']
                     . " (version, applied_at, description) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $migration['version'],
                    $now,
                    $migration['description'] ?? ''
                ]);
            }
        } catch (\Exception $e) {
            $this->log[] = "  ⚠ Kan versie niet registreren in '{$source['trackingTable']}': " . $e->getMessage();
        }

        // Clear cached versions so subsequent reads see the new state.
        $this->currentVersions = [];
    }

    /**
     * Get applied-migration history.  Walks every registered source and
     * returns rows tagged with `_source` so callers (the CMA migration
     * tool, the dashboard) can group by origin.
     *
     * @return array<int, array{version:string, applied_at:string, description:string, _source:string}>
     */
    public function getMigrationHistory(): array
    {
        $history = [];

        foreach ($this->sources as $name => $source) {
            try {
                $conn = Database::getConnection($source['trackingDb']);
                if ($conn === null) {
                    continue;
                }
                if (!$this->versionTableExists($conn, $source['trackingTable'])) {
                    continue;
                }

                $sql = "SELECT version, applied_at, description FROM " . $source['trackingTable']
                     . " ORDER BY applied_at DESC, id DESC";
                $stmt = $conn->query($sql);
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $row['_source'] = $name;
                    $history[] = $row;
                }
            } catch (\Exception $e) {
                $this->errors[] = "Kan migratiegeschiedenis voor '$name' niet laden: " . $e->getMessage();
            }
        }

        // Newest first overall (so the UI shows the most recent record at
        // the top regardless of source order).
        usort($history, function ($a, $b) {
            return strcmp((string)($b['applied_at'] ?? ''), (string)($a['applied_at'] ?? ''));
        });

        return $history;
    }

    /**
     * Get all migrations
     *
     * @return array All defined migrations
     */
    public function getAllMigrations(): array
    {
        return $this->migrations;
    }

    /**
     * Get a specific migration by version
     *
     * @param string $version The version to find
     * @return array|null The migration definition or null if not found
     */
    public function getMigrationByVersion(string $version): ?array
    {
        foreach ($this->migrations as $migration) {
            if ($migration['version'] === $version) {
                return $migration;
            }
        }
        return null;
    }

    /**
     * Rerun a specific migration (even if already applied)
     *
     * @param string $version The version to rerun
     * @return array ['success' => bool, 'applied' => [], 'errors' => [], 'log' => []]
     */
    public function rerunMigration(string $version): array
    {
        $this->log = [];
        $errors = [];

        $migration = $this->getMigrationByVersion($version);
        if ($migration === null) {
            return [
                'success' => false,
                'applied' => [],
                'errors' => ["Migratie versie '$version' niet gevonden"],
                'log' => $this->log
            ];
        }

        $this->log[] = str_repeat('━', 60);
        $this->log[] = "Migratie opnieuw uitvoeren: versie {$version}";
        $this->log[] = str_repeat('━', 60);
        $this->log[] = "Versie {$version}: {$migration['description']}";

        $result = $this->applyMigration($migration);

        if ($result['success']) {
            $this->log[] = "✓ Migratie {$version} succesvol opnieuw uitgevoerd";
            return [
                'success' => true,
                'applied' => [$version],
                'errors' => [],
                'log' => $this->log
            ];
        } else {
            $errorMsg = $result['details'] ?? $result['error'] ?? 'Onbekende fout';
            $this->log[] = "✗ Migratie {$version} MISLUKT: " . $errorMsg;
            return [
                'success' => false,
                'applied' => [],
                'errors' => [$errorMsg],
                'error' => $errorMsg,
                'log' => $this->log
            ];
        }
    }

    /**
     * Apply a single pending migration by version (for AJAX progress)
     *
     * @param string $version The version to apply
     * @return array ['success' => bool, 'log' => [], 'error' => string|null]
     */
    public function applySingleMigration(string $version): array
    {
        $this->log = [];

        $migration = $this->getMigrationByVersion($version);
        if ($migration === null) {
            return [
                'success' => false,
                'log' => [],
                'error' => "Migratie versie '$version' niet gevonden"
            ];
        }

        // Check if this migration is actually pending
        $pending = $this->getPendingMigrations();
        $pendingVersions = array_column($pending, 'version');

        if (!in_array($version, $pendingVersions)) {
            return [
                'success' => true,
                'log' => ["Migratie $version is al toegepast"],
                'error' => null
            ];
        }

        $this->log[] = "Versie {$version}: {$migration['description']}";

        $result = $this->applyMigration($migration);

        if ($result['success']) {
            $this->log[] = "✓ Versie {$version} succesvol toegepast";
            return [
                'success' => true,
                'log' => $this->log,
                'error' => null
            ];
        } else {
            $this->log[] = "✗ Versie {$version} MISLUKT: " . ($result['details'] ?? $result['error'] ?? 'Onbekende fout');
            return [
                'success' => false,
                'log' => $this->log,
                'error' => $result['details'] ?? $result['error'] ?? 'Migratie mislukt'
            ];
        }
    }

    /**
     * Get target version
     */
    public function getTargetVersion(): string
    {
        if (!file_exists(self::MIGRATIONS_FILE)) {
            return '0.0.0';
        }
        $json = file_get_contents(self::MIGRATIONS_FILE);
        $data = json_decode($json, true);
        return $data['targetVersion'] ?? '0.0.0';
    }

    /**
     * Get errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get log
     */
    public function getLog(): array
    {
        return $this->log;
    }

    /**
     * Check if there are pending migrations
     */
    public function hasPendingMigrations(): bool
    {
        return count($this->getPendingMigrations()) > 0;
    }

    // =========================================================================
    // Static helper methods for feature detection
    // =========================================================================

    /** @var array Cache for migration status checks */
    private static array $migrationStatusCache = [];

    /** @var array Cache for column existence checks */
    private static array $columnExistsCache = [];

    /**
     * Check if a specific migration version has been applied
     * Results are cached for the request lifetime.
     *
     * @param string $version Version to check (e.g., '6.3.0')
     * @param string $database Database to check (default: 'data')
     * @return bool True if migration has been applied
     */
    public static function isMigrationApplied(string $version, string $database = 'data'): bool
    {
        $cacheKey = "{$database}:{$version}";

        if (isset(self::$migrationStatusCache[$cacheKey])) {
            return self::$migrationStatusCache[$cacheKey];
        }

        try {
            $conn = Database::getConnection($database);
            if ($conn === null) {
                return false;
            }

            // Check if version table exists
            $sql = "SELECT version FROM _cma_version WHERE version = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$version]);
            $result = $stmt->fetch();

            self::$migrationStatusCache[$cacheKey] = ($result !== false);
        } catch (\Exception $e) {
            // Table doesn't exist or query failed - migration not applied
            self::$migrationStatusCache[$cacheKey] = false;
        }

        return self::$migrationStatusCache[$cacheKey];
    }

    /**
     * Check if a column exists in a table
     * Results are cached for the request lifetime.
     *
     * @param string $table Table name
     * @param string $column Column name
     * @param string $database Database to check (default: 'data')
     * @return bool True if column exists
     */
    public static function columnExists(string $table, string $column, string $database = 'data'): bool
    {
        $cacheKey = "{$database}:{$table}:{$column}";

        if (isset(self::$columnExistsCache[$cacheKey])) {
            return self::$columnExistsCache[$cacheKey];
        }

        try {
            $conn = Database::getConnection($database);
            if ($conn === null) {
                return false;
            }

            // Try to select the column - will fail if it doesn't exist
            $sql = "SELECT TOP 1 [{$column}] FROM [{$table}]";
            $conn->query($sql);

            self::$columnExistsCache[$cacheKey] = true;
        } catch (\Exception $e) {
            self::$columnExistsCache[$cacheKey] = false;
        }

        return self::$columnExistsCache[$cacheKey];
    }

    /**
     * Clear the static caches (useful after running migrations)
     */
    public static function clearCache(): void
    {
        self::$migrationStatusCache = [];
        self::$columnExistsCache = [];
    }
}
