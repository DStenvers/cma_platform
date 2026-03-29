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
     * Load migrations from JSON file
     */
    private function loadMigrations(): void
    {
        if (!file_exists(self::MIGRATIONS_FILE)) {
            $this->errors[] = 'Migratie bestand niet gevonden: ' . self::MIGRATIONS_FILE;
            return;
        }

        $json = file_get_contents(self::MIGRATIONS_FILE);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->errors[] = 'Ongeldige JSON in migratie bestand: ' . json_last_error_msg();
            return;
        }

        $this->migrations = $data['migrations'] ?? [];
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

        // Use 'data' database as the single source of truth for version tracking
        try {
            $conn = Database::getConnection('data');
            if ($conn === null) {
                $this->currentVersions['data'] = 'geen verbinding';
                $this->errors[] = "Kan geen verbinding maken met database 'data'";
            } elseif ($this->versionTableExists($conn)) {
                $version = $this->getLatestVersion($conn);
                $this->currentVersions['data'] = $version ?: '0.0.0';
            } else {
                $this->currentVersions['data'] = '0.0.0';
            }
        } catch (\Exception $e) {
            $this->currentVersions['data'] = 'fout';
            $this->errors[] = "Fout bij verbinden met 'data': " . $e->getMessage();
        }

        // Check other databases for connection status only (informational)
        foreach (['rep', 'users'] as $db) {
            try {
                $conn = Database::getConnection($db);
                if ($conn === null) {
                    $this->currentVersions[$db] = 'geen verbinding';
                } else {
                    // Just show connection works, version comes from 'data'
                    $this->currentVersions[$db] = 'verbonden';
                }
            } catch (\Exception $e) {
                $this->currentVersions[$db] = 'fout';
            }
        }

        return $this->currentVersions;
    }

    /**
     * Check if version table exists
     */
    private function versionTableExists(\PDO $conn): bool
    {
        try {
            $driver = $conn->getAttribute(\PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                // SQLite - check sqlite_master
                $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name='" . self::VERSION_TABLE . "'";
                $stmt = $conn->query($sql);
                return $stmt->fetch() !== false;
            } elseif ($driver === 'odbc') {
                // Access database - try to select from the table
                try {
                    $stmt = $conn->query("SELECT TOP 1 version FROM " . self::VERSION_TABLE);
                    return true;
                } catch (\Exception $e) {
                    return false;
                }
            } else {
                // SQL Server
                $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '" . self::VERSION_TABLE . "'";
                $stmt = $conn->query($sql);
                return (int)$stmt->fetchColumn() > 0;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get latest applied version from database
     */
    private function getLatestVersion(\PDO $conn): ?string
    {
        try {
            $driver = $conn->getAttribute(\PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                // SQLite uses LIMIT instead of TOP
                $sql = "SELECT version FROM " . self::VERSION_TABLE . " ORDER BY applied_at DESC LIMIT 1";
            } elseif ($driver === 'odbc') {
                // Access uses TOP
                $sql = "SELECT TOP 1 version FROM " . self::VERSION_TABLE . " ORDER BY applied_at DESC";
            } else {
                // SQL Server
                $sql = "SELECT TOP 1 version FROM " . self::VERSION_TABLE . " ORDER BY applied_at DESC";
            }

            $stmt = $conn->query($sql);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ? $row['version'] : null;
        } catch (\Exception $e) {
            $this->log[] = "Waarschuwing: Kan versie niet ophalen: " . $e->getMessage();
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

        // Use 'data' database version as the source of truth
        $currentVersion = $versions['data'] ?? '0.0.0';
        if ($currentVersion === 'fout' || $currentVersion === 'geen verbinding') {
            $currentVersion = '0.0.0';
        }

        // Get all migrations after current version (skip disabled migrations)
        foreach ($this->migrations as $migration) {
            if (!empty($migration['disabled'])) {
                continue; // Skip disabled migrations
            }
            if (version_compare($migration['version'], $currentVersion, '>')) {
                $pending[] = $migration;
            }
        }

        // Sort by version
        usort($pending, function($a, $b) {
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

        $backupService = $this->getBackupService();

        foreach ($affectedDatabases as $dbName) {
            $dbConfig = $databaseConfigs[strtolower($dbName)] ?? null;

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
                return $this->runSqlScript($change['script']);

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
     * Create version tracking table
     */
    private function createVersionTable(string $database): array
    {
        try {
            $conn = Database::getConnection($database);

            if ($conn === null) {
                return [
                    'success' => false,
                    'error' => "Kan geen verbinding maken met database '$database'"
                ];
            }

            if ($this->versionTableExists($conn)) {
                return ['success' => true, 'error' => null, 'message' => 'Tabel bestaat al'];
            }

            $driver = $conn->getAttribute(\PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                // SQLite syntax
                $sql = "CREATE TABLE " . self::VERSION_TABLE . " (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    version TEXT NOT NULL,
                    applied_at TEXT,
                    description TEXT
                )";
            } elseif ($driver === 'odbc') {
                // Access syntax
                $sql = "CREATE TABLE " . self::VERSION_TABLE . " (
                    id AUTOINCREMENT PRIMARY KEY,
                    version VARCHAR(20) NOT NULL,
                    applied_at DATETIME,
                    description VARCHAR(255)
                )";
            } else {
                // SQL Server syntax
                $sql = "CREATE TABLE " . self::VERSION_TABLE . " (
                    id INT IDENTITY(1,1) PRIMARY KEY,
                    version VARCHAR(20) NOT NULL,
                    applied_at DATETIME NOT NULL DEFAULT GETDATE(),
                    description NVARCHAR(255)
                )";
            }

            $conn->exec($sql);

            return ['success' => true, 'error' => null, 'message' => 'Tabel aangemaakt'];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Kan versietabel niet aanmaken in '$database': " . $e->getMessage()
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
            return [
                'success' => false,
                'error' => "Brontabel '$oldName' bestaat niet"
            ];
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

        return Database::dropIndexPDO($conn, $table, $indexName);
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
    private function runSqlScript(string $scriptPath): array
    {
        $fullPath = __DIR__ . '/../../' . $scriptPath;

        if (!file_exists($fullPath)) {
            return [
                'success' => false,
                'error' => "Script bestand niet gevonden: $scriptPath"
            ];
        }

        try {
            $sql = file_get_contents($fullPath);

            // Split by GO or semicolon for multiple statements
            $statements = preg_split('/;\s*\n|^GO\s*$/m', $sql, -1, PREG_SPLIT_NO_EMPTY);
            $executed = 0;

            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement) || strpos($statement, '--') === 0) {
                    continue;
                }

                // Determine database from statement or use 'rep' as default
                $conn = Database::getConnection('rep');
                $conn->exec($statement);
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
     * Record migration in version table (data database only)
     */
    private function recordMigration(array $migration): void
    {
        $now = date('Y-m-d H:i:s');

        try {
            $conn = Database::getConnection('data');

            if ($conn === null) {
                $this->log[] = "  ⚠ Kan geen verbinding maken met 'data' database";
                return;
            }

            if (!$this->versionTableExists($conn)) {
                $this->log[] = "  ⚠ Versietabel bestaat niet in 'data' database";
                return;
            }

            $driver = $conn->getAttribute(\PDO::ATTR_DRIVER_NAME);

            if ($driver === 'odbc') {
                // Access syntax - use # for dates, parameterized query for safety
                $sql = "INSERT INTO " . self::VERSION_TABLE . " (version, applied_at, description) VALUES (?, #" . $now . "#, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $migration['version'],
                    $migration['description'] ?? ''
                ]);
            } else {
                // SQLite/SQL Server - use parameterized query
                $sql = "INSERT INTO " . self::VERSION_TABLE . " (version, applied_at, description) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $migration['version'],
                    $now,
                    $migration['description'] ?? ''
                ]);
            }
        } catch (\Exception $e) {
            $this->log[] = "  ⚠ Kan versie niet registreren in 'data': " . $e->getMessage();
        }

        // Clear cached versions
        $this->currentVersions = [];
    }

    /**
     * Get migration history from the data database
     *
     * @return array List of applied migrations with timestamps
     */
    public function getMigrationHistory(): array
    {
        $history = [];

        try {
            $conn = Database::getConnection('data');

            if ($conn === null) {
                return [];
            }

            if (!$this->versionTableExists($conn)) {
                return [];
            }

            $sql = "SELECT version, applied_at, description FROM " . self::VERSION_TABLE . " ORDER BY applied_at DESC";
            $stmt = $conn->query($sql);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $history[] = $row;
            }
        } catch (\Exception $e) {
            $this->errors[] = "Kan migratiegeschiedenis niet laden: " . $e->getMessage();
        }

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
