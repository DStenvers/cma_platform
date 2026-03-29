<?php
/**
 * Backup Service
 *
 * Centralized backup management with description support.
 * Maintains a backups.json file with metadata for all backups.
 */

namespace Cma\Services;

use App\Library\Database;

class BackupService
{
    private const BACKUP_INDEX_FILE = 'backups.json';

    private string $backupDir;
    private string $siteRoot;
    private array $backupIndex = [];

    /**
     * Constructor
     */
    public function __construct(?string $backupDir = null)
    {
        $this->siteRoot = dirname(__DIR__, 3);
        $this->backupDir = $backupDir ?? $this->siteRoot . '/backup';

        // Ensure backup directory exists
        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0755, true);
        }

        $this->loadBackupIndex();
    }

    /**
     * Load backup index from JSON file
     */
    private function loadBackupIndex(): void
    {
        $indexPath = $this->backupDir . '/' . self::BACKUP_INDEX_FILE;

        if (file_exists($indexPath)) {
            $content = file_get_contents($indexPath);
            $data = json_decode($content, true);
            $this->backupIndex = $data['backups'] ?? [];
        } else {
            $this->backupIndex = [];
        }
    }

    /**
     * Save backup index to JSON file
     */
    private function saveBackupIndex(): void
    {
        $indexPath = $this->backupDir . '/' . self::BACKUP_INDEX_FILE;

        $data = [
            '$schema' => '../cma/config/schema/backups.schema.json',
            'lastUpdated' => date('Y-m-d H:i:s'),
            'backups' => $this->backupIndex
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($indexPath, $json);
    }

    /**
     * Create a backup with optional description
     *
     * @param array $database Database config from databases.json
     * @param string $description Optional description for the backup
     * @return array ['success' => bool, 'file' => string, 'message' => string]
     */
    public function createBackup(array $database, string $description = ''): array
    {
        $timestamp = date('Y-m-d-H-i');
        $type = strtolower($database['type'] ?? $this->detectDatabaseType($database));
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $database['name'] ?? 'unknown');

        // Determine file path
        $filePath = $this->getDatabasePath($database);

        $result = match($type) {
            'sqlite' => $this->backupFileDatabase($database, $timestamp, '.sqlite'),
            'access' => $this->backupFileDatabase($database, $timestamp, '.' . (pathinfo($filePath, PATHINFO_EXTENSION) ?: 'mdb')),
            'mysql', 'mariadb' => $this->backupMySql($database, $timestamp),
            'pgsql', 'postgresql' => $this->backupPostgreSql($database, $timestamp),
            'sqlserver', 'mssql' => $this->backupSqlServer($database, $timestamp),
            default => ['success' => false, 'message' => 'Onbekend database type: ' . $type]
        };

        // Add to index if successful
        if ($result['success'] && !empty($result['file'])) {
            $this->addToIndex($result['file'], $database['name'], $description, $type);
        }

        return $result;
    }

    /**
     * Create backup for migration (with automatic description)
     *
     * @param array $database Database config
     * @param string $migrationVersion Migration version
     * @param string $migrationDescription Migration description
     * @return array
     */
    public function createMigrationBackup(array $database, string $migrationVersion, string $migrationDescription): array
    {
        $description = "Backup voor de migratie '{$migrationVersion}: {$migrationDescription}'";
        return $this->createBackup($database, $description);
    }

    /**
     * Get database file path from config
     */
    private function getDatabasePath(array $database): string
    {
        if (!empty($database['path'])) {
            $path = $database['path'];

            // Check if already an absolute path (Windows or Unix)
            if ($this->isAbsolutePath($path)) {
                return $path;
            }

            // Relative path - prepend site root
            if (str_starts_with($path, '/')) {
                return $this->siteRoot . $path;
            }
            return $this->siteRoot . '/' . $path;
        }

        $connString = $database['connectionString'] ?? '';
        if (preg_match('/Data Source=\[([^\]]+)\]/', $connString, $matches)) {
            $path = str_replace(['[', ']'], '', $matches[1]);
            if ($this->isAbsolutePath($path)) {
                return $path;
            }
            return $this->siteRoot . '/' . ltrim($path, '/');
        }
        if (preg_match('/Data Source=([^;]+)/', $connString, $matches)) {
            $path = trim($matches[1]);
            if ($this->isAbsolutePath($path) || file_exists($path)) {
                return $path;
            }
            return $this->siteRoot . '/' . ltrim($path, '/');
        }
        // ODBC connection strings use DBQ= instead of Data Source=
        if (preg_match('/DBQ=([^;]+)/i', $connString, $matches)) {
            $path = trim($matches[1]);
            if ($this->isAbsolutePath($path)) {
                return $path;
            }
            return $this->siteRoot . '/' . ltrim($path, '/');
        }

        return '';
    }

    /**
     * Check if path is absolute (Windows or Unix)
     */
    private function isAbsolutePath(string $path): bool
    {
        // Windows absolute path (e.g., C:\... or D:/...)
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            return true;
        }
        // Unix absolute path
        if (str_starts_with($path, '/') && !str_starts_with($path, '//')) {
            // But /db/file.sqlite should be relative to site root
            // Only truly absolute if it exists at root level
            return file_exists($path);
        }
        return false;
    }

    /**
     * Detect database type from connection string or file path
     * Public static so it can be reused in tools_backup.php
     */
    public static function detectDatabaseType(array $database): string
    {
        $connString = $database['connectionString'] ?? '';

        // Detect from connection string provider
        if (stripos($connString, 'Microsoft.Jet.OLEDB') !== false ||
            stripos($connString, 'Microsoft.ACE.OLEDB') !== false) {
            return 'access';
        }
        if (stripos($connString, 'mysql') !== false) {
            return 'mysql';
        }
        if (stripos($connString, 'pgsql') !== false || stripos($connString, 'postgresql') !== false) {
            return 'pgsql';
        }
        if (stripos($connString, 'sqlsrv') !== false || stripos($connString, 'mssql') !== false) {
            return 'sqlserver';
        }

        // Detect from ODBC driver
        if (stripos($connString, 'Microsoft Access Driver') !== false) {
            return 'access';
        }

        // Detect from file extension in path or connection string
        $path = $database['path'] ?? '';
        if (empty($path) && preg_match('/Data Source=\[?([^\];]+)\]?/', $connString, $matches)) {
            $path = $matches[1];
        }
        if (empty($path) && preg_match('/DBQ=([^;]+)/i', $connString, $matches)) {
            $path = trim($matches[1]);
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match($ext) {
            'mdb', 'accdb' => 'access',
            'sqlite', 'sqlite3', 'db' => 'sqlite',
            default => 'unknown'
        };
    }

    /**
     * Check if a SQLite database is corrupt
     *
     * @param string $path Path to the SQLite database file
     * @return array ['valid' => bool, 'message' => string]
     */
    public function checkSqliteIntegrity(string $path): array
    {
        if (!file_exists($path)) {
            return ['valid' => false, 'message' => 'Bestand niet gevonden'];
        }

        if (!is_readable($path)) {
            return ['valid' => false, 'message' => 'Bestand niet leesbaar'];
        }

        try {
            $db = new \PDO('sqlite:' . $path);
            $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Run integrity check
            $result = $db->query('PRAGMA integrity_check;')->fetchColumn();

            if ($result === 'ok') {
                return ['valid' => true, 'message' => 'Database is intact'];
            }

            return ['valid' => false, 'message' => 'Database is corrupt: ' . $result];
        } catch (\PDOException $e) {
            return ['valid' => false, 'message' => 'Kon database niet openen: ' . $e->getMessage()];
        }
    }

    /**
     * Backup file-based database (SQLite, Access)
     */
    private function backupFileDatabase(array $database, string $timestamp, string $ext): array
    {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $database['name']);
        $sourcePath = $this->getDatabasePath($database);
        $backupFile = $this->backupDir . '/' . $timestamp . '_' . $name . '_backup' . $ext;

        // Debug: include source path in all results
        $debug = [
            'sourcePath' => $sourcePath,
            'configPath' => $database['path'] ?? '',
            'connectionString' => $database['connectionString'] ?? ''
        ];

        if (empty($sourcePath)) {
            return [
                'success' => false,
                'database' => $database['name'],
                'message' => 'Geen database pad gevonden in configuratie',
                'debug' => $debug
            ];
        }

        if (!file_exists($sourcePath)) {
            return [
                'success' => false,
                'database' => $database['name'],
                'message' => 'Bronbestand niet gevonden: ' . $sourcePath,
                'debug' => $debug
            ];
        }

        if (!is_readable($sourcePath)) {
            return [
                'success' => false,
                'database' => $database['name'],
                'message' => 'Bronbestand niet leesbaar: ' . $sourcePath,
                'debug' => $debug
            ];
        }

        // Check SQLite database integrity before backup
        if (strtolower($ext) === '.sqlite' || strtolower($ext) === '.sqlite3' || strtolower($ext) === '.db') {
            $integrityCheck = $this->checkSqliteIntegrity($sourcePath);
            if (!$integrityCheck['valid']) {
                return [
                    'success' => false,
                    'database' => $database['name'],
                    'message' => 'Backup geannuleerd - ' . $integrityCheck['message'],
                    'corrupt' => true,
                    'debug' => $debug
                ];
            }
        }

        // For SQLite: checkpoint WAL so all data is flushed to the main file before copying
        if (in_array(strtolower($ext), ['.sqlite', '.sqlite3', '.db'])) {
            try {
                $pdo = new \PDO('sqlite:' . $sourcePath);
                $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
                $pdo = null;
            } catch (\Throwable $e) {
                // Non-fatal: proceed with copy even if checkpoint fails
            }
        }

        if (@copy($sourcePath, $backupFile)) {
            $size = filesize($backupFile);
            return [
                'success' => true,
                'database' => $database['name'],
                'message' => 'Backup aangemaakt',
                'file' => basename($backupFile),
                'size' => $size,
                'path' => $backupFile
            ];
        }

        return [
            'success' => false,
            'database' => $database['name'],
            'message' => 'Kon bestand niet kopiëren naar ' . $backupFile,
            'debug' => $debug
        ];
    }

    /**
     * Backup MySQL database
     */
    private function backupMySql(array $database, string $timestamp): array
    {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $database['name']);
        $backupFile = $this->backupDir . '/' . $timestamp . '_' . $name . '_backup.sql';

        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $dbName = getenv('DB_NAME') ?: $database['name'];
        $user = getenv('DB_USER') ?: '';
        $password = getenv('DB_PASSWORD') ?: '';

        if (empty($user)) {
            return [
                'success' => false,
                'database' => $database['name'],
                'message' => 'Database gebruiker niet geconfigureerd (DB_USER)'
            ];
        }

        $cmd = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s %s > %s 2>&1',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($password),
            escapeshellarg($dbName),
            escapeshellarg($backupFile)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode === 0 && file_exists($backupFile)) {
            return [
                'success' => true,
                'database' => $database['name'],
                'message' => 'SQL dump aangemaakt',
                'file' => basename($backupFile),
                'size' => filesize($backupFile),
                'path' => $backupFile
            ];
        }

        @unlink($backupFile);
        return [
            'success' => false,
            'database' => $database['name'],
            'message' => 'mysqldump mislukt: ' . implode(' ', $output)
        ];
    }

    /**
     * Backup PostgreSQL database
     */
    private function backupPostgreSql(array $database, string $timestamp): array
    {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $database['name']);
        $backupFile = $this->backupDir . '/' . $timestamp . '_' . $name . '_backup.sql';

        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '5432';
        $dbName = getenv('DB_NAME') ?: $database['name'];
        $user = getenv('DB_USER') ?: '';

        if (empty($user)) {
            return [
                'success' => false,
                'database' => $database['name'],
                'message' => 'Database gebruiker niet geconfigureerd (DB_USER)'
            ];
        }

        $env = 'PGPASSWORD=' . escapeshellarg(getenv('DB_PASSWORD') ?: '');

        $cmd = sprintf(
            '%s pg_dump --host=%s --port=%s --username=%s --format=plain --file=%s %s 2>&1',
            $env,
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($backupFile),
            escapeshellarg($dbName)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode === 0 && file_exists($backupFile)) {
            return [
                'success' => true,
                'database' => $database['name'],
                'message' => 'SQL dump aangemaakt',
                'file' => basename($backupFile),
                'size' => filesize($backupFile),
                'path' => $backupFile
            ];
        }

        @unlink($backupFile);
        return [
            'success' => false,
            'database' => $database['name'],
            'message' => 'pg_dump mislukt: ' . implode(' ', $output)
        ];
    }

    /**
     * Backup SQL Server database
     */
    private function backupSqlServer(array $database, string $timestamp): array
    {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $database['name']);
        $backupFile = $this->backupDir . '/' . $timestamp . '_' . $name . '_backup.sql';

        $host = getenv('DB_HOST') ?: 'localhost';
        $dbName = getenv('DB_NAME') ?: $database['name'];
        $user = getenv('DB_USER') ?: '';
        $password = getenv('DB_PASSWORD') ?: '';

        if (empty($user)) {
            return [
                'success' => false,
                'database' => $database['name'],
                'message' => 'Database gebruiker niet geconfigureerd (DB_USER)'
            ];
        }

        $cmd = sprintf(
            'sqlcmd -S %s -U %s -P %s -d %s -Q "EXEC sp_generate_inserts @table_name = \'%%\'" -o %s 2>&1',
            escapeshellarg($host),
            escapeshellarg($user),
            escapeshellarg($password),
            escapeshellarg($dbName),
            escapeshellarg($backupFile)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode === 0 && file_exists($backupFile)) {
            return [
                'success' => true,
                'database' => $database['name'],
                'message' => 'SQL dump aangemaakt',
                'file' => basename($backupFile),
                'size' => filesize($backupFile),
                'path' => $backupFile
            ];
        }

        @unlink($backupFile);
        return [
            'success' => false,
            'database' => $database['name'],
            'message' => 'sqlcmd mislukt: ' . implode(' ', $output)
        ];
    }

    /**
     * Add backup to index
     */
    private function addToIndex(string $filename, string $databaseName, string $description, string $type): void
    {
        $this->backupIndex[$filename] = [
            'database' => $databaseName,
            'type' => $type,
            'description' => $description,
            'created' => date('Y-m-d H:i:s'),
            'createdBy' => \App\Library\Cookie::get('cmaUsername', 'system')
        ];

        $this->saveBackupIndex();
    }

    /**
     * Update backup description
     */
    public function updateDescription(string $filename, string $description): bool
    {
        // If file exists but not in index, add it first
        if (!isset($this->backupIndex[$filename])) {
            $filePath = $this->backupDir . '/' . $filename;
            if (file_exists($filePath)) {
                // Extract database name and type from filename
                $dbName = '';
                $type = pathinfo($filename, PATHINFO_EXTENSION);
                if (preg_match('/^\d{4}-\d{2}-\d{2}-\d{2}-\d{2}_(.+)_backup\./', $filename, $matches)) {
                    $dbName = $matches[1];
                }
                $this->backupIndex[$filename] = [
                    'database' => $dbName,
                    'type' => $type,
                    'description' => '',
                    'created' => date('Y-m-d H:i:s', filemtime($filePath)),
                    'createdBy' => 'unknown'
                ];
            } else {
                return false;
            }
        }

        $this->backupIndex[$filename]['description'] = $description;
        $this->saveBackupIndex();
        return true;
    }

    /**
     * Get backup info from index
     */
    public function getBackupInfo(string $filename): ?array
    {
        return $this->backupIndex[$filename] ?? null;
    }

    /**
     * Get all backups with their metadata
     */
    public function getAllBackups(): array
    {
        $backups = [];

        if (!is_dir($this->backupDir)) {
            return $backups;
        }

        foreach (glob($this->backupDir . '/*_backup.*') as $file) {
            $filename = basename($file);
            $indexInfo = $this->backupIndex[$filename] ?? [];

            $backups[] = [
                'name' => $filename,
                'path' => $file,
                'size' => filesize($file),
                'modified' => filemtime($file),
                'ext' => pathinfo($file, PATHINFO_EXTENSION),
                'database' => $indexInfo['database'] ?? $this->extractDatabaseFromFilename($filename),
                'description' => $indexInfo['description'] ?? '',
                'createdBy' => $indexInfo['createdBy'] ?? '',
                'type' => $indexInfo['type'] ?? ''
            ];
        }

        // Sort by modification time, newest first
        usort($backups, fn($a, $b) => $b['modified'] - $a['modified']);

        return $backups;
    }

    /**
     * Extract database name from backup filename
     */
    private function extractDatabaseFromFilename(string $filename): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}-\d{2}-\d{2}_(.+)_backup\./', $filename, $matches)) {
            return $matches[1];
        }
        return 'unknown';
    }

    /**
     * Delete backup and remove from index
     */
    public function deleteBackup(string $filename): bool
    {
        $fullPath = $this->backupDir . '/' . basename($filename);

        if (file_exists($fullPath) && strpos($filename, '_backup.') !== false) {
            if (@unlink($fullPath)) {
                unset($this->backupIndex[$filename]);
                $this->saveBackupIndex();
                return true;
            }
        }

        return false;
    }

    /**
     * Sync index with actual files (cleanup orphaned entries)
     */
    public function syncIndex(): int
    {
        $removed = 0;

        foreach ($this->backupIndex as $filename => $info) {
            $fullPath = $this->backupDir . '/' . $filename;
            if (!file_exists($fullPath)) {
                unset($this->backupIndex[$filename]);
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->saveBackupIndex();
        }

        return $removed;
    }

    /**
     * Get backup directory path
     */
    public function getBackupDir(): string
    {
        return $this->backupDir;
    }
}
