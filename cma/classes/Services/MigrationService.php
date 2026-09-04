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
    private array $warnings = [];
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
                // Directory of THIS source's manifest, so runPhp/runSqlScript
                // changes can carry their script next to the manifest (a
                // site-owned migration lives outside the platform-synced cma/).
                $migration['_sourceDir'] = dirname($source['file']);
                // De identiteit van een migratie is bron + versie, niet het versienummer
                // alleen: het platform staat op 9.x, een site-migratie op 0.x, en die twee
                // reeksen zijn niet met elkaar te vergelijken. Alles wat een migratie
                // aanwijst (toepassen, opnieuw uitvoeren, het scherm) gebruikt dit id.
                $migration['_id'] = self::migrationId($name, (string)($migration['version'] ?? ''));
                $this->migrations[] = $migration;
            }
        }

        $this->detectVersionIssues();
    }

    /**
     * Flag version-numbering problems across sources so the tool can report
     * them instead of letting them silently misbehave:
     *
     *  - Two sources sharing the same version string. Each source has its own
     *    tracking table so apply-all still runs both, but rerun/target-by-
     *    version can only see one of them — ambiguous by design.
     *  - A site (non-platform) source using a version >= 1.0.0. The platform
     *    owns 1.0.0+ (it climbs there over releases); site migrations must
     *    stay in the reserved 0.x.x range to guarantee they never collide
     *    with a future platform version.
     */
    private function detectVersionIssues(): void
    {
        $sourcesByVersion = [];
        foreach ($this->migrations as $migration) {
            $version = (string)($migration['version'] ?? '');
            $source  = (string)($migration['_source'] ?? 'platform');
            if ($version === '') {
                continue;
            }
            $sourcesByVersion[$version][$source] = true;

            if ($source !== 'platform' && version_compare($version, '1.0.0', '>=')) {
                $this->warnings[] = "Site-migratie '$source' gebruikt versie $version. "
                    . "Versies vanaf 1.0.0 zijn gereserveerd voor het platform — geef site-eigen "
                    . "migraties een versie in het 0.x.x-bereik (bijv. 0.1.0) om botsingen te voorkomen.";
            }
        }

        foreach ($sourcesByVersion as $version => $sources) {
            if (count($sources) > 1) {
                $this->warnings[] = "Versie $version is gedefinieerd door meerdere bronnen ("
                    . implode(', ', array_keys($sources)) . "). Apply-all voert beide uit, maar "
                    . "'opnieuw uitvoeren' op alleen het versienummer is dubbelzinnig.";
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
            } catch (\Throwable $e) {
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
            } catch (\Throwable $e) {
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
        // Per-driver catalogue lookups live in Database::tableExistsPDO(); a second
        // copy here is a second thing to keep right. $tableName is service-controlled
        // (never user input), which is what makes the literal interpolation there safe.
        return Database::tableExistsPDO($conn, $tableName);
    }

    /**
     * Get the latest applied version from a tracking table.  Default
     * table is the platform's; pass a custom name for additional sources.
     *
     * "Latest" is the HIGHEST version ever recorded (compared with
     * version_compare), NOT the most recently inserted row.  Re-running an
     * older migration inserts a fresh row with today's timestamp; ordering by
     * applied_at would then pull the reported version back down to that older
     * value and make every later migration look pending again.  Taking the
     * semver-max never lowers the version and is also insertion-order- and
     * clock-independent, so a batch of no-op migrations applied within the
     * same second still resolves deterministically.
     */
    private function getLatestVersion(\PDO $conn, string $tableName = self::VERSION_TABLE): ?string
    {
        try {
            $stmt = $conn->query("SELECT version FROM " . $tableName);

            $latest = null;
            while (($version = $stmt->fetchColumn()) !== false) {
                $version = (string)$version;
                if ($version === '') {
                    continue;
                }
                if ($latest === null || version_compare($version, $latest, '>')) {
                    $latest = $version;
                }
            }
            return $latest;
        } catch (\Throwable $e) {
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
        $pending = $this->selectPendingUpTo($this->getPendingMigrations(), $targetVersion);
        $applied = [];
        $errors = [];

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

        // Read the SAME config the runtime connections come from
        // (data/databases.json, via Bootstrap). Hardcoding cma/config/ here let
        // the backup config diverge from the live connections — a host whose
        // real DSNs live in data/databases.json would back up stale entries.
        // Key each databases.json entry by its LOGICAL connection name
        // (data/rep/users) using the same canonicalisation the runtime uses
        // (Database::$connectionAliases / Bootstrap). Without this a site that
        // renamed its data DB to 'main' (or legacy 'database'/'repository')
        // wouldn't match the migration alias 'data' and the backup was skipped.
        $logicalNames = [
            'users' => 'users', 'cmausers' => 'users',
            'data' => 'data', 'database' => 'data', 'main' => 'data',
            'rep' => 'rep', 'repository' => 'rep',
        ];
        $databaseConfigs = [];
        foreach (\App\Library\Bootstrap::loadDatabasesConfig() as $dbConfig) {
            $name = strtolower(trim((string)($dbConfig['name'] ?? '')));
            $logical = $logicalNames[$name] ?? $name;
            if ($logical !== '' && !isset($databaseConfigs[$logical])) {
                $databaseConfigs[$logical] = $dbConfig; // first match wins
            }
        }

        // Diagnose an empty/mismatched backup config instead of silently
        // skipping every database. Report WHICH file was consulted and which
        // logical names it yielded, so an operator can see at a glance why a
        // backup was skipped (e.g. databases.json missing/unreadable, or a
        // 'name' that maps to no known logical connection).
        $configSource = (string)($GLOBALS['_db_config_source'] ?? '');
        if (empty($databaseConfigs)) {
            $this->log[] = "  ⚠ Backup-config leeg: geen bruikbare database-entries gevonden"
                . ($configSource !== '' ? " in $configSource" : " (geen databases.json gelezen)")
                . ". Controleer dat het bestand bestaat, geldige JSON is en per entry een 'name' bevat.";
        }

        $backupService = $this->getBackupService();

        foreach ($affectedDatabases as $dbName) {
            $key = $logicalNames[strtolower($dbName)] ?? strtolower($dbName);
            $dbConfig = $databaseConfigs[$key] ?? null;

            if (!$dbConfig) {
                $available = !empty($databaseConfigs) ? implode(', ', array_keys($databaseConfigs)) : 'geen';
                $this->log[] = "  ⚠ Database '$dbName' (logisch '$key') niet gevonden in configuratie"
                    . ($configSource !== '' ? " ($configSource)" : '')
                    . "; beschikbaar: $available. Backup overgeslagen.";
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

        $sourceDir = $migration['_sourceDir'] ?? null;
        $changeIndex = 0;
        foreach ($migration['changes'] as $change) {
            $changeIndex++;
            $changeDesc = $this->describeChange($change);

            try {
                $result = $this->applyChange($change, $sourceDir);

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
            } catch (\Throwable $e) {
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

        // Invalidate the front-end migration banner immediately (not after its 5-min
        // TTL) now that the applied set has changed.
        self::clearCache();

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
        // databases.json is the source of truth: Bootstrap::initDatabaseConnections
        // registers a DSN per configured connection, and getConnection() reads
        // ONLY that. The old `Application::get('conn_<name>')` globals are no
        // longer set, so checking them here made this method always return false
        // on modern installs — which silently skipped EVERY declarative schema
        // change (addColumn/addIndex/createVersionTable) while still recording
        // the migration as applied. Database::isConfigured() is the correct,
        // databases.json-aware check (and still honours legacy conn_* globals).
        return \App\Library\Database::isConfigured($name);
    }

    /**
     * Can this connection actually be opened on this machine?
     *
     * Opening is the only honest test — a DSN says what someone intended, not
     * what is installed. The result is remembered per request so a migration
     * run with several changes against the same database does not pay for the
     * same failing connect over and over (a missing ODBC driver is slow to
     * fail).
     */
    private function databaseIsReachable(string $name): bool
    {
        static $seen = [];
        $key = strtolower($name);
        if (array_key_exists($key, $seen)) {
            return $seen[$key];
        }
        try {
            $seen[$key] = \App\Library\Database::getConnection($name) !== null;
        } catch (\Throwable $e) {
            // A driver that is not loaded throws, a file that is not there
            // throws, a server that refuses throws. All of them mean the same
            // thing to a migration: not here.
            $seen[$key] = false;
        }
        return $seen[$key];
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
    private function applyChange(array $change, ?string $sourceDir = null): array
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
            // Configured is not the same as available. A connection can name a
            // database that is not there: an Access file that was never copied
            // to this box, a driver that is not installed, a server that is
            // down. The change would then run against nothing and fail deep
            // inside a script — which is how a missing repository.mdb turned
            // into an HTTP 500 on the migrations screen instead of a line of
            // text. Ask once, here, and skip the same way.
            if (!$this->databaseIsReachable($db)) {
                return [
                    'success' => true,
                    'message' => "Overgeslagen: database '$db' is geconfigureerd maar niet "
                        . "bereikbaar op deze machine"
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
                return $this->runSqlScript($change['script'], $change['database'] ?? 'data', $sourceDir);

            case 'runPhp':
                return $this->runPhpScript($change['script'], $sourceDir);

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

            // One statement in the platform's Access dialect; Database::executeDdl()
            // makes it whatever this backend accepts. Three hand-maintained variants
            // is how they drift — the SQLite one had already lost the version width.
            Database::executeDdl($conn, "CREATE TABLE " . $tableName . " (
                id AUTOINCREMENT PRIMARY KEY,
                version VARCHAR($versionWidth) NOT NULL,
                applied_at DATETIME,
                description VARCHAR(255)
            )");

            return ['success' => true, 'error' => null, 'message' => 'Tabel aangemaakt'];
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
                } catch (\Throwable $e) {
                    // Negeer fouten bij individuele tabellen
                }
            }
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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

        // Idempotent: re-running a migration must not fail when the index is
        // already present. addIndexPDO may either throw or return a failure
        // array, so tolerate both and treat "already exists" as success.
        try {
            $res = Database::addIndexPDO($conn, $table, $columns, $indexName);
        } catch (\Throwable $e) {
            $res = ['success' => false, 'error' => $e->getMessage()];
        }
        if (!($res['success'] ?? false)) {
            $msg = strtolower((string)($res['error'] ?? ''));
            if (strpos($msg, 'already has an index') !== false
                || strpos($msg, 'already exists') !== false
                || strpos($msg, 'duplicate') !== false
                || strpos($msg, 'bestaat al') !== false) {
                return ['success' => true, 'error' => null, 'message' => "Overgeslagen: index '$indexName' bestaat al"];
            }
        }
        return $res;
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
        } catch (\Throwable $e) {
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
    private function runSqlScript(string $scriptPath, string $database = 'data', ?string $sourceDir = null): array
    {
        $fullPath = $this->resolveScriptPath($scriptPath, $sourceDir);

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
                // Script statements are DDL by convention, so they go through the
                // same dialect translation as a declarative schema change.
                Database::executeDdl($conn, $cleaned);
                $executed++;
            }

            return ['success' => true, 'error' => null, 'message' => "$executed statement(s) uitgevoerd"];
        } catch (\Throwable $e) {
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
    private function runPhpScript(string $scriptPath, ?string $sourceDir = null): array
    {
        $fullPath = $this->resolveScriptPath($scriptPath, $sourceDir);

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
     * Resolve a migration script path (runPhp / runSqlScript).
     *
     * Order of resolution:
     *   1. An absolute path (POSIX `/…` or Windows `X:\…`) is used verbatim.
     *   2. A path that exists relative to the source manifest's own directory
     *      wins next. This is how a SITE-OWNED migration carries its script
     *      next to its manifest, outside the platform-synced `cma/` tree —
     *      a bundled platform script under cma/ would be overwritten (or
     *      simply absent) on a consumer site.
     *   3. Otherwise fall back to the platform default: relative to `cma/`
     *      (i.e. `cma/migrations/…`), where the bundled migrations live.
     *
     * The platform source's own manifest sits in `cma/config/`, so its
     * `migrations/x.php` entries miss step 2 and correctly resolve via step 3.
     */
    private function resolveScriptPath(string $scriptPath, ?string $sourceDir): string
    {
        if (strlen($scriptPath) > 1 && ($scriptPath[0] === '/' || $scriptPath[1] === ':')) {
            return $scriptPath;
        }
        if ($sourceDir !== null && $sourceDir !== '') {
            $candidate = rtrim($sourceDir, '/\\') . '/' . $scriptPath;
            if (file_exists($candidate)) {
                return $candidate;
            }
        }
        return __DIR__ . '/../../' . $scriptPath;
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
            } catch (\Throwable $e) {
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
     * Het id van een migratie: "<bron>:<versie>", bijv. "platform:9.23.0" of
     * "mijnrino:0.1.0". Versienummers zijn alleen BINNEN een bron te vergelijken.
     */
    public static function migrationId(string $source, string $version): string
    {
        return $source . ':' . $version;
    }

    /**
     * Zoek een migratie op id ("bron:versie") of op kaal versienummer.
     *
     * Een kaal nummer is er voor oude aanroepers en oude links; het wordt alleen
     * aanvaard als precies EEN bron die versie kent. Kennen twee bronnen hem (het
     * scherm waarschuwt daar al voor), dan is de aanwijzing dubbelzinnig en komt er
     * null terug - liever niets doen dan de verkeerde migratie draaien.
     */
    public function findMigration(string $ref): ?array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }
        if (strpos($ref, ':') !== false) {
            foreach ($this->migrations as $migration) {
                if (($migration['_id'] ?? '') === $ref) {
                    return $migration;
                }
            }
            return null;
        }
        $gevonden = [];
        foreach ($this->migrations as $migration) {
            if ((string)($migration['version'] ?? '') === $ref) {
                $gevonden[] = $migration;
            }
        }
        return count($gevonden) === 1 ? $gevonden[0] : null;
    }

    /**
     * Get a specific migration by version (or by id, see findMigration()).
     *
     * @param string $version The version (or "source:version") to find
     * @return array|null The migration definition or null if not found / ambiguous
     */
    public function getMigrationByVersion(string $version): ?array
    {
        return $this->findMigration($version);
    }

    /**
     * Het deel van de openstaande lijst dat "tot en met" $target loopt.
     *
     * Op POSITIE in de lijst, niet op versievergelijking: de lijst is per bron
     * gesorteerd (platform eerst, dan de site), en "toepassen tot hier" betekent alles
     * boven en inclusief die regel. Een vergelijking op versienummer over bronnen heen
     * gaf onzin - een site-migratie 0.1.0 viel dan wel of niet binnen "tot 9.24.0" al
     * naar gelang hoe je het bekeek. Zonder target: alles. Een target dat niet in de
     * lijst staat (al toegepast, verkeerd id): lege selectie, dus niets toepassen.
     *
     * @param array<int, array> $pending de openstaande migraties, in uitvoervolgorde
     */
    public function selectPendingUpTo(array $pending, ?string $target): array
    {
        if ($target === null || trim($target) === '') {
            return array_values($pending);
        }
        $doel = $this->findMigration($target);
        $doelId = $doel['_id'] ?? null;
        // Een kaal versienummer dat findMigration niet uniek kon plaatsen: kijk of het
        // in de openstaande lijst zelf wel uniek is (alleen daar telt het).
        if ($doelId === null && strpos($target, ':') === false) {
            $treffers = array_values(array_filter($pending, static fn($m) => (string)($m['version'] ?? '') === trim($target)));
            if (count($treffers) === 1) {
                $doelId = $treffers[0]['_id'] ?? null;
            }
        }
        if ($doelId === null) {
            return [];
        }
        $selectie = [];
        foreach ($pending as $migration) {
            $selectie[] = $migration;
            if (($migration['_id'] ?? '') === $doelId) {
                return $selectie;
            }
        }
        return [];
    }

    /**
     * De doelversie per bron: wat het manifest van die bron als targetVersion opgeeft,
     * anders de hoogste versie erin. Het platform en een site hebben elk hun eigen
     * reeks; een enkel getal voor allebei bestaat niet.
     *
     * @return array<string, string> bronnaam => versie
     */
    public function getTargetVersions(): array
    {
        $doelen = [];
        foreach ($this->sources as $name => $source) {
            $doel = '0.0.0';
            if (is_file($source['file'])) {
                $data = json_decode((string)file_get_contents($source['file']), true);
                $doel = (string)($data['targetVersion'] ?? '');
                if ($doel === '') {
                    foreach ($data['migrations'] ?? [] as $m) {
                        $v = (string)($m['version'] ?? '');
                        if ($v !== '' && version_compare($v, $doel === '' ? '0.0.0' : $doel, '>')) {
                            $doel = $v;
                        }
                    }
                    $doel = $doel === '' ? '0.0.0' : $doel;
                }
            }
            $doelen[$name] = $doel;
        }
        return $doelen;
    }

    /**
     * De versietabel van een bron, ook zonder een MigrationService te bouwen: het
     * platform heeft `_cma_version`, een geregistreerde site-bron wat haar registratie
     * zegt (standaard `_cma_<naam>_version`).
     */
    public static function trackingTableFor(string $source): string
    {
        if ($source === '' || $source === 'platform') {
            return self::VERSION_TABLE;
        }
        $extra = \App\Library\Application::get('migration_sources_extra', []);
        if (is_array($extra)) {
            foreach ($extra as $src) {
                if (is_array($src) && (string)($src['name'] ?? '') === $source) {
                    return (string)($src['trackingTable'] ?? ('_cma_' . $source . '_version'));
                }
            }
        }
        return '_cma_' . $source . '_version';
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

        // Check if this migration is actually pending - op id, want hetzelfde
        // versienummer kan bij een andere bron wel/niet openstaan.
        $pending = $this->getPendingMigrations();
        $pendingIds = array_column($pending, '_id');

        if (!in_array($migration['_id'] ?? '', $pendingIds, true)) {
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
     * Non-fatal advisories about the migration set — e.g. two sources that
     * define the same version string (which makes rerun/target-by-version
     * ambiguous), or a site source that strays into the platform's 1.0.0+
     * version space. Surfaced by the migrations tool so these stay visible
     * instead of silently biting later.
     *
     * @return string[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
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
    public static function isMigrationApplied(string $version, string $database = 'data', string $source = 'platform'): bool
    {
        // Een "bron:versie"-id mag ook; dan wint de bron uit het id.
        if (strpos($version, ':') !== false) {
            [$source, $version] = explode(':', $version, 2);
        }
        $table = self::trackingTableFor($source);
        $cacheKey = "{$database}:{$table}:{$version}";

        if (isset(self::$migrationStatusCache[$cacheKey])) {
            return self::$migrationStatusCache[$cacheKey];
        }

        try {
            $conn = Database::getConnection($database);
            if ($conn === null) {
                return false;
            }

            // De versietabel van DEZE bron; het platform en een site houden elk hun eigen.
            $sql = "SELECT version FROM " . $table . " WHERE version = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$version]);
            $result = $stmt->fetch();

            self::$migrationStatusCache[$cacheKey] = ($result !== false);
        } catch (\Throwable $e) {
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

            self::$columnExistsCache[$cacheKey] = Database::columnExistsPDO($conn, $table, $column);
        } catch (\Throwable $e) {
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
        // Also drop the front-end migration banner's file cache (header.inc's
        // WriteMigratieBanner, key 'migratie_banner_status') so applying/rolling back
        // a migration reflects immediately instead of after that banner's 5-min TTL.
        if (class_exists('\App\Library\Cache')) {
            \App\Library\Cache::clearFile('migratie_banner_status');
        }
    }
}
