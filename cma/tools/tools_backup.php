<?php
/**
 * Database Backup & Restore Tool
 *
 * Combined tool for backup creation, restoration, and management.
 * - SQLite and MS Access: Copies the database file
 * - MySQL, PostgreSQL, SQL Server: Creates/restores SQL dumps
 *
 * Backup files are stored in /backup with timestamp: yyyy-mm-dd-hh-mm_dbname_backup.*
 * Backup metadata (descriptions) are stored in /backup/backups.json
 */

use App\Library\Application;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use Cma\SecurityHelper;
use Cma\Services\BackupService;
use Cma\ToolbarHelper;

require_once __DIR__ . '/../bootstrap.inc';

// Check access - admin or developer only
if (!SecurityHelper::isAdmin()) {
    echo '<lib-message type="error">Geen toegang - alleen beheerders</lib-message>';
    exit;
}

Response::noCache();

// Get site root and backup directory
$siteRoot = dirname(__DIR__, 2);
$backupDir = $siteRoot . '/backup';
$dbDir = $siteRoot . '/db';

// Ensure backup directory exists
if (!is_dir($backupDir)) {
    if (!@mkdir($backupDir, 0755, true)) {
        echo '<lib-message type="error">Kan backup map niet aanmaken: ' . htmlspecialchars($backupDir) . '</lib-message>';
        exit;
    }
}

// Load databases configuration
$databasesFile = __DIR__ . '/../config/databases.json';
$databases = [];
if (file_exists($databasesFile)) {
    $content = file_get_contents($databasesFile);
    $config = json_decode($content, true);
    $databases = $config['databases'] ?? [];
}

// Build database list for backup
$foundDatabases = [];
foreach ($databases as $db) {
    $type = strtolower($db['type'] ?? BackupService::detectDatabaseType($db));
    $connString = $db['connectionString'] ?? '';
    $name = $db['name'] ?? $db['title'] ?? 'unknown';

    // If connectionString is empty, try to get it from Application config
    if (empty($connString)) {
        $appConnKey = 'conn_' . $name;
        $appConnString = Application::get($appConnKey, '');
        if (!empty($appConnString)) {
            $connString = $appConnString;
        }
    }

    $filePath = '';
    if (!empty($db['path'])) {
        $filePath = $db['path'];
        if (str_starts_with($filePath, '/')) {
            $filePath = $siteRoot . $filePath;
        } elseif (!file_exists($filePath)) {
            $filePath = $siteRoot . '/' . $filePath;
        }
    } elseif (preg_match('/Data Source=\[([^\]]+)\]/', $connString, $matches)) {
        $filePath = str_replace(['[', ']'], '', $matches[1]);
        $filePath = $siteRoot . '/' . ltrim($filePath, '/');
    } elseif (preg_match('/Data Source=([^;]+)/', $connString, $matches)) {
        $filePath = trim($matches[1]);
        if (!file_exists($filePath)) {
            $filePath = $siteRoot . '/' . ltrim($filePath, '/');
        }
    } elseif (preg_match('/Dbq=([^;]+)/i', $connString, $matches)) {
        // Access ODBC driver uses Dbq= instead of Data Source=
        $filePath = trim($matches[1]);
        if (!file_exists($filePath)) {
            $filePath = $siteRoot . '/' . ltrim($filePath, '/');
        }
    }

    $foundDatabases[] = [
        'name' => $name,
        'title' => $db['title'] ?? $name,
        'type' => $type,
        'path' => $filePath,
        'connectionString' => $connString
    ];
}

// Build a map of database names to their paths for restore
$dbPathMap = [];
foreach ($databases as $db) {
    $connString = $db['connectionString'] ?? '';
    $name = strtolower($db['name'] ?? '');

    // If connectionString is empty, try to get it from Application config
    if (empty($connString)) {
        $appConnKey = 'conn_' . $name;
        $appConnString = Application::get($appConnKey, '');
        if (!empty($appConnString)) {
            $connString = $appConnString;
        }
    }

    if (!empty($db['path'])) {
        $filePath = $db['path'];
        if (str_starts_with($filePath, '/')) {
            $dbPathMap[$name] = $siteRoot . $filePath;
        } else {
            $dbPathMap[$name] = $siteRoot . '/' . $filePath;
        }
    } elseif (preg_match('/Data Source=\[([^\]]+)\]/', $connString, $matches)) {
        $filePath = str_replace(['[', ']'], '', $matches[1]);
        $dbPathMap[$name] = $siteRoot . '/' . ltrim($filePath, '/');
    } elseif (preg_match('/Dbq=([^;]+)/i', $connString, $matches)) {
        // Access ODBC driver uses Dbq= instead of Data Source=
        $filePath = trim($matches[1]);
        if (file_exists($filePath)) {
            $dbPathMap[$name] = $filePath;
        } else {
            $dbPathMap[$name] = $siteRoot . '/' . ltrim($filePath, '/');
        }
    }
}

// Also add common locations for scanned databases
if (is_dir($dbDir)) {
    foreach (glob($dbDir . '/*.{sqlite,db,mdb,accdb}', GLOB_BRACE) as $file) {
        $name = strtolower(pathinfo($file, PATHINFO_FILENAME));
        if (!isset($dbPathMap[$name])) {
            $dbPathMap[$name] = $file;
        }
    }
}

// Initialize BackupService
$backupService = new BackupService($backupDir);

// Determine current tab and action
$tab = Request::query('tab', 'create');
$action = Request::post('action', '') ?: Request::query('action', '');
$selectedFile = Request::query('file', '') ?: Request::post('file', '');
$results = [];
$restoreResult = null;

// Process backup creation
if ($action === 'backup') {
    $selectedDbs = Request::post('databases', []);
    $description = trim(Request::post('description', ''));

    if (empty($selectedDbs)) {
        $results[] = [
            'success' => false,
            'database' => '-',
            'message' => 'Geen databases geselecteerd'
        ];
    } else {
        foreach ($selectedDbs as $index) {
            if (!isset($foundDatabases[$index])) {
                $results[] = [
                    'success' => false,
                    'database' => "Index $index",
                    'message' => 'Database niet gevonden in configuratie'
                ];
                continue;
            }
            $db = $foundDatabases[$index];
            try {
                $result = $backupService->createBackup($db, $description);
                // Ensure database name is always set
                if (!isset($result['database'])) {
                    $result['database'] = $db['name'] ?? $db['title'] ?? 'onbekend';
                }
                $results[] = $result;
            } catch (\Throwable $e) {
                $results[] = [
                    'success' => false,
                    'database' => $db['name'] ?? $db['title'] ?? 'onbekend',
                    'message' => 'Fout: ' . $e->getMessage(),
                    'debug' => [
                        'exception' => get_class($e),
                        'file' => $e->getFile() . ':' . $e->getLine()
                    ]
                ];
            }
        }
    }
    $tab = 'create';
}

// Process restore request
if ($action === 'restore' && !empty($selectedFile)) {
    $backupPath = $backupDir . '/' . basename($selectedFile);
    $preRestoreDescription = trim(Request::post('preRestoreDescription', ''));

    if (!file_exists($backupPath)) {
        $restoreResult = ['success' => false, 'message' => 'Backup bestand niet gevonden'];
    } else {
        $restoreResult = restoreBackup($backupPath, $dbPathMap, $siteRoot, $dbDir, $backupService, $preRestoreDescription);
    }
    $tab = 'manage';
}

// Process delete request
$deleteFile = Request::query('file', '');
if ($action === 'delete' && !empty($deleteFile)) {
    $fileToDelete = basename($deleteFile);
    $backupService->deleteBackup($fileToDelete);
    header('Location: tools_backup.php?tab=manage');
    exit;
}

// Handle description update
$descFile = Request::post('file', '');
$descText = Request::post('description', '');
if ($action === 'update_description' && $descFile !== '') {
    $file = basename($descFile);
    $newDescription = trim($descText);
    $backupService->updateDescription($file, $newDescription);
    header('Location: tools_backup.php?tab=manage');
    exit;
}

/**
 * Restore a backup file
 */
function restoreBackup(string $backupPath, array $dbPathMap, string $siteRoot, string $dbDir, BackupService $backupService, string $preRestoreDescription = ''): array
{
    $filename = basename($backupPath);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (preg_match('/^(\d{4}-\d{2}-\d{2}-\d{2}-\d{2})_(.+)_backup\.' . preg_quote($ext, '/') . '$/', $filename, $matches)) {
        $backupTimestamp = $matches[1];
        $dbName = strtolower($matches[2]);
    } else {
        return ['success' => false, 'message' => 'Ongeldig backup bestandsformaat: ' . $filename];
    }

    switch ($ext) {
        case 'sqlite':
        case 'db':
        case 'mdb':
        case 'accdb':
            return restoreFileDatabase($backupPath, $dbName, $dbPathMap, $dbDir, '.' . $ext, $backupTimestamp, $backupService, $preRestoreDescription);
        case 'sql':
            return restoreSqlDump($backupPath, $dbName, $backupTimestamp);
        default:
            return ['success' => false, 'message' => 'Onbekend backup type: ' . $ext];
    }
}

/**
 * Restore a file-based database (SQLite, Access)
 */
function restoreFileDatabase(string $backupPath, string $dbName, array $dbPathMap, string $dbDir, string $ext, string $backupTimestamp, BackupService $backupService, string $preRestoreDescription = ''): array
{
    $targetPath = $dbPathMap[$dbName] ?? null;

    if (!$targetPath) {
        $possiblePaths = glob($dbDir . '/' . $dbName . '.*');
        if (!empty($possiblePaths)) {
            $targetPath = $possiblePaths[0];
        } else {
            $targetPath = $dbDir . '/' . $dbName . $ext;
        }
    }

    $targetDir = dirname($targetPath);
    if (!is_dir($targetDir)) {
        if (!@mkdir($targetDir, 0755, true)) {
            return ['success' => false, 'message' => 'Kan doelmap niet aanmaken: ' . $targetDir];
        }
    }

    if (file_exists($targetPath)) {
        $timestamp = date('Y-m-d-H-i');
        // Create pre-restore backup with proper naming convention
        $preRestoreFilename = $timestamp . '_' . $dbName . '_backup' . $ext;
        $backupDir = $backupService->getBackupDir();
        $preRestoreBackup = $backupDir . '/' . $preRestoreFilename;

        // For SQLite: checkpoint WAL so all data is flushed before copying
        if (in_array(strtolower($ext), ['.sqlite', '.sqlite3', '.db'])) {
            try {
                $pdo = new \PDO('sqlite:' . $targetPath);
                $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
                $pdo = null;
            } catch (\Throwable $e) { /* non-fatal */ }
        }

        if (!@copy($targetPath, $preRestoreBackup)) {
            return ['success' => false, 'message' => 'Kan huidige database niet backuppen voor herstel'];
        }

        // Register the pre-restore backup with description
        $backupService->updateDescription($preRestoreFilename, $preRestoreDescription);
    }

    if (@copy($backupPath, $targetPath)) {
        $displayTimestamp = $backupTimestamp;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})-(\d{2})-(\d{2})$/', $backupTimestamp, $m)) {
            $displayTimestamp = "{$m[3]}-{$m[2]}-{$m[1]} {$m[4]}:{$m[5]}";
        }
        return [
            'success' => true,
            'message' => 'Database hersteld naar versie ' . $displayTimestamp,
            'target' => basename($targetPath)
        ];
    } else {
        return ['success' => false, 'message' => 'Kon backup niet kopiëren naar: ' . basename($targetPath)];
    }
}

/**
 * Restore SQL dump (MySQL, PostgreSQL, SQL Server)
 */
function restoreSqlDump(string $backupPath, string $dbName, string $backupTimestamp): array
{
    $dbType = strtolower(getenv('DB_TYPE') ?: 'mysql');
    $host = getenv('DB_HOST') ?: 'localhost';
    $database = getenv('DB_NAME') ?: $dbName;
    $user = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASSWORD') ?: '';

    if (empty($user)) {
        return ['success' => false, 'message' => 'Database gebruiker niet geconfigureerd (DB_USER)'];
    }

    switch ($dbType) {
        case 'mysql':
        case 'mariadb':
            $port = getenv('DB_PORT') ?: '3306';
            $cmd = sprintf('mysql --host=%s --port=%s --user=%s --password=%s %s < %s 2>&1',
                escapeshellarg($host), escapeshellarg($port), escapeshellarg($user),
                escapeshellarg($password), escapeshellarg($database), escapeshellarg($backupPath));
            break;
        case 'pgsql':
        case 'postgresql':
            $port = getenv('DB_PORT') ?: '5432';
            $cmd = sprintf('PGPASSWORD=%s psql --host=%s --port=%s --username=%s --dbname=%s --file=%s 2>&1',
                escapeshellarg($password), escapeshellarg($host), escapeshellarg($port),
                escapeshellarg($user), escapeshellarg($database), escapeshellarg($backupPath));
            break;
        case 'sqlserver':
        case 'mssql':
            $cmd = sprintf('sqlcmd -S %s -U %s -P %s -d %s -i %s 2>&1',
                escapeshellarg($host), escapeshellarg($user), escapeshellarg($password),
                escapeshellarg($database), escapeshellarg($backupPath));
            break;
        default:
            return ['success' => false, 'message' => 'Onbekend database type: ' . $dbType];
    }

    exec($cmd, $output, $returnCode);

    if ($returnCode === 0) {
        $displayTimestamp = $backupTimestamp;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})-(\d{2})-(\d{2})$/', $backupTimestamp, $m)) {
            $displayTimestamp = "{$m[3]}-{$m[2]}-{$m[1]} {$m[4]}:{$m[5]}";
        }
        return ['success' => true, 'message' => 'Database hersteld naar versie ' . $displayTimestamp, 'target' => $database];
    } else {
        return ['success' => false, 'message' => 'Import mislukt: ' . implode(' ', $output)];
    }
}

/**
 * Format file size for display
 */
function formatSize(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

/**
 * Get backup type description
 */
function getBackupTypeLabel(string $ext): string
{
    return match(strtolower($ext)) {
        'sqlite', 'db' => 'SQLite',
        'mdb', 'accdb' => 'MS Access',
        'sql' => 'SQL Dump',
        default => strtoupper($ext)
    };
}

// Get all backups
$backupFiles = $backupService->getAllBackups();

// Output HTML
cma_html_header('Database backup');
echo '<BODY class="contentbody tools tool-backup backup">';
ToolbarHelper::report('Database backup', false, false, false, false, 'Maak of herstel database backups');
echo '<div id="c" class="tools">';

// Tab navigation using cma-tabs component
$selectedTab = $tab === 'manage' ? 1 : 0;
echo '<cma-tabs id="backupTabs" selected="' . $selectedTab . '">';
echo '<tab-item title="Backup maken" data-count="' . count($foundDatabases) . '"></tab-item>';
echo '<tab-item title="Backups beheren" data-count="' . count($backupFiles) . '"></tab-item>';
echo '</cma-tabs>';

// ========== CREATE TAB ==========
$createTabStyle = $tab === 'create' ? '' : 'display:none;';
echo '<div id="tabCreate" style="' . $createTabStyle . '">';
{
    // Show results if any
    if (!empty($results)) {
        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $failCount = count($results) - $successCount;

        if ($failCount === 0) {
            echo '<lib-message type="success">' . $successCount . ' backup(s) succesvol aangemaakt</lib-message>';
        } elseif ($successCount === 0) {
            echo '<lib-message type="error">Alle backups mislukt</lib-message>';
        } else {
            echo '<lib-message type="warning">' . $successCount . ' succesvol, ' . $failCount . ' mislukt</lib-message>';
        }

        echo '<table class="listtable">';
        echo '<thead><tr><th>Database</th><th>Status</th><th>Bestand</th><th>Grootte</th></tr></thead>';
        echo '<tbody>';
        foreach ($results as $result) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($result['database']) . '</td>';
            if ($result['success']) {
                echo '<td class="status ok">Gelukt</td>';
                echo '<td>' . htmlspecialchars($result['file'] ?? '') . '</td>';
                echo '<td>' . formatSize($result['size'] ?? 0) . '</td>';
            } else {
                echo '<td class="status fail">Mislukt</td>';
                echo '<td colspan="2">';
                echo htmlspecialchars($result['message']);
                // Show debug info if available
                if (!empty($result['debug'])) {
                    echo '<div class="debug-info">';
                    if (!empty($result['debug']['configPath'])) {
                        echo 'Config path: ' . htmlspecialchars($result['debug']['configPath']) . '<br>';
                    }
                    if (!empty($result['debug']['connectionString'])) {
                        echo 'Connection: ' . htmlspecialchars($result['debug']['connectionString']) . '<br>';
                    }
                    if (!empty($result['debug']['sourcePath'])) {
                        echo 'Source: ' . htmlspecialchars($result['debug']['sourcePath']);
                    }
                    echo '</div>';
                }
                echo '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    // Show database selection form

    if (empty($foundDatabases)) {
        echo '<lib-message type="warning">Geen databases gevonden</lib-message>';
    } else {
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="action" value="backup">';

        echo '<lib-table resizable>';
        echo'<table >';
        echo '<thead><tr>';
        echo '<th data-no-filter class="col-check"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>';
        echo '<th>Database</th>';
        echo '<th>Type</th>';
        echo '<th>Bestand/Locatie</th>';
        echo '<th data-type="number">Grootte</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($foundDatabases as $index => $db) {
            $exists = !empty($db['path']) && file_exists($db['path']);
            $typeLabel = match(strtolower($db['type'])) {
                'sqlite' => 'SQLite',
                'access' => 'MS Access',
                'mysql', 'mariadb' => 'MySQL',
                'pgsql', 'postgresql' => 'PostgreSQL',
                'sqlserver', 'mssql' => 'SQL Server',
                default => ucfirst($db['type'])
            };

            echo '<tr>';
            echo '<td><input type="checkbox" name="databases[]" value="' . $index . '"' . ($exists ? '' : ' disabled') . '></td>';
            echo '<td><strong>' . htmlspecialchars($db['title'] ?? $db['name']) . '</strong></td>';
            echo '<td><span class="lnr lnr-database"></span>' . $typeLabel . '</td>';

            if (!empty($db['path'])) {
                echo '<td class="col-path">' . htmlspecialchars(basename($db['path'])) . '</td>';
            } else {
                echo '<td><span class="text-muted">Server database</span></td>';
            }

            if ($exists) {
                $size = filesize($db['path']);
                echo '<td data-sort-value="' . $size . '">' . formatSize($size) . '</td>';
            } else {
                echo '<td data-sort-value="0">-</td>';
            }

            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</lib-table>';

        echo '<div class="backup-form">';
        echo '<label for="description">Omschrijving (optioneel):</label>';
        echo '<input type="text" name="description" id="description" placeholder="Bijv. backup voor update of backup voor migratie" class="backup-input" style="height:31px">';
        echo '<button type="submit" id="backupBtn" class="btn btn-primary" disabled><span class="lnr lnr-download"></span> Backup maken</button>';
        echo '</div>';
        echo '</form>';
    }
}
echo '</div>'; // End tabCreate

// ========== MANAGE TAB ==========
$manageTabStyle = $tab === 'manage' ? '' : 'display:none;';
echo '<div id="tabManage" style="' . $manageTabStyle . '">';
{
    // Show restore result if any
    if ($restoreResult !== null) {
        $msgType = $restoreResult['success'] ? 'success' : 'error';
        $message = htmlspecialchars($restoreResult['message']);
        if ($restoreResult['success'] && !empty($restoreResult['target'])) {
            $message .= ' (' . htmlspecialchars($restoreResult['target']) . ')';
        }
        echo '<lib-message type="' . $msgType . '">' . $message . '</lib-message>';
    }

    // If a file is selected for restore, show confirmation
    if (!empty($selectedFile) && $action !== 'restore') {
        $backupPath = $backupDir . '/' . basename($selectedFile);

        if (file_exists($backupPath)) {
            $fileInfo = [
                'name' => basename($backupPath),
                'size' => filesize($backupPath),
                'modified' => filemtime($backupPath),
                'ext' => pathinfo($backupPath, PATHINFO_EXTENSION)
            ];

            $backupMeta = $backupService->getBackupInfo($fileInfo['name']);
            $description = $backupMeta['description'] ?? '';

            $dbNameFromFile = '';
            if (preg_match('/^\d{4}-\d{2}-\d{2}-\d{2}-\d{2}_(.+)_backup\./', $fileInfo['name'], $matches)) {
                $dbNameFromFile = $matches[1];
            }

            echo '<h3>Backup herstellen</h3>';

            echo '<lib-message type="warning">';
            echo '<strong>Let op:</strong> Het herstellen van een backup overschrijft de huidige database. ';
            echo 'Er wordt automatisch een backup gemaakt van de huidige database voordat het herstel plaatsvindt.';
            echo '</lib-message>';

            echo '<div class="backup-details">';
            echo '<table>';
            echo '<tr><td>Bestand:</td><td>' . htmlspecialchars($fileInfo['name']) . '</td></tr>';
            echo '<tr><td>Database:</td><td><strong>' . htmlspecialchars($dbNameFromFile) . '</strong></td></tr>';
            if (!empty($description)) {
                echo '<tr><td>Omschrijving:</td><td>' . htmlspecialchars($description) . '</td></tr>';
            }
            echo '<tr><td>Type:</td><td>' . getBackupTypeLabel($fileInfo['ext']) . '</td></tr>';
            echo '<tr><td>Grootte:</td><td>' . formatSize($fileInfo['size']) . '</td></tr>';
            echo '<tr><td>Datum:</td><td>' . date('d-m-Y H:i:s', $fileInfo['modified']) . '</td></tr>';
            echo '</table>';
            echo '</div>';

            // Generate default description for pre-restore backup
            $preRestoreDesc = 'Automatische backup vóór herstel naar ' . date('d-m-Y H:i');

            echo '<form method="post" action="" id="restoreForm" class="restore-form">';
            echo '<input type="hidden" name="action" value="restore">';
            echo '<input type="hidden" name="file" value="' . htmlspecialchars($selectedFile) . '">';
            echo '<div class="form-field">';
            echo '<label for="preRestoreDescription">Omschrijving huidige versie (vóór herstel):</label>';
            echo '<input type="text" name="preRestoreDescription" id="preRestoreDescription" value="' . htmlspecialchars($preRestoreDesc) . '" class="backup-input">';
            echo '</div>';
            echo '<button type="submit" class="btn btn-primary"><span class="lnr lnr-undo"></span> Backup herstellen</button>';
            echo ' <a href="?tab=manage" class="btn">Annuleren</a>';
            echo '</form>';
        } else {
            echo '<lib-message type="error">Backup bestand niet gevonden: ' . htmlspecialchars($selectedFile) . '</lib-message>';
        }
    } else {
        // Show list of backups

        if (empty($backupFiles)) {
            echo '<p class="text-muted">Geen backups gevonden in /backup</p>';
            echo '<p><a href="?tab=create" class="btn btn-primary"><span class="lnr lnr-download"></span> Backup maken</a></p>';
        } else {
            echo '<lib-table resizable>';
            echo'<table >';
            echo '<thead><tr><th>Bestand</th><th>Omschrijving</th><th>Type</th><th data-type="number">Grootte</th><th data-type="date">Datum</th><th data-no-filter>Acties</th></tr></thead>';
            echo '<tbody>';
            foreach ($backupFiles as $file) {
                $hasDescription = !empty($file['description']);
                echo '<tr>';
                echo '<td>' . htmlspecialchars($file['name']) . '</td>';
                echo '<td><span class="backup-description">' . htmlspecialchars($file['description'] ?? '') . '</span></td>';
                echo '<td>' . getBackupTypeLabel($file['ext']) . '</td>';
                echo '<td data-sort-value="' . $file['size'] . '">' . formatSize($file['size']) . '</td>';
                echo '<td data-sort-value="' . $file['modified'] . '">' . date('d-m-Y H:i', $file['modified']) . '</td>';
                echo '<td class="col-actions">';
                echo '<button type="button" class="btn btn-sm btn-primary btn-edit-description" data-file="' . htmlspecialchars($file['name']) . '" data-description="' . htmlspecialchars($file['description'] ?? '') . '" title="Omschrijving bewerken"><span class="lnr lnr-pencil"></span></button> ';
                echo '<a href="?tab=manage&file=' . urlencode($file['name']) . '" class="btn btn-sm btn-primary" title="Herstellen"><span class="lnr lnr-undo"></span></a> ';
                echo '<a href="?tab=manage&action=delete&file=' . urlencode($file['name']) . '" class="btn btn-sm btn-primary btn-delete-backup" title="Verwijderen"><span class="lnr lnr-trash"></span></a>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</lib-table>';
        }
    }
}
echo '</div>'; // End tabManage

?>

<script>
function toggleAll(checkbox) {
    var checkboxes = document.querySelectorAll('input[name="databases[]"]');
    checkboxes.forEach(function(cb) {
        if (!cb.disabled) {
            cb.checked = checkbox.checked;
        }
    });
    updateButtonState();
}

function updateButtonState() {
    var checkboxes = document.querySelectorAll('input[name="databases[]"]');
    var submitBtn = document.getElementById("backupBtn");
    if (submitBtn) {
        var anyChecked = Array.from(checkboxes).some(function(cb) { return cb.checked && !cb.disabled; });
        submitBtn.disabled = !anyChecked;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Tab navigation - toggle visibility without page refresh
    var tabs = document.getElementById('backupTabs');
    var tabCreate = document.getElementById('tabCreate');
    var tabManage = document.getElementById('tabManage');
    if (tabs && tabCreate && tabManage) {
        tabs.addEventListener('tab-select', function(e) {
            var index = e.detail.index;
            if (index === 0) {
                tabCreate.style.display = '';
                tabManage.style.display = 'none';
            } else {
                tabCreate.style.display = 'none';
                tabManage.style.display = '';
            }
            // Update URL without reload (for bookmarking/refresh)
            var tabParam = index === 0 ? 'create' : 'manage';
            history.replaceState(null, '', '?tab=' + tabParam);
        });
    }

    // Attach checkbox listeners
    document.querySelectorAll('input[name="databases[]"]').forEach(function(cb) {
        cb.addEventListener("change", updateButtonState);
    });

    // Delete backup confirmation
    document.querySelectorAll('.btn-delete-backup').forEach(function(btn) {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            var confirmed = await libConfirm("Weet je zeker dat je deze backup wilt verwijderen?", {
                title: "Backup verwijderen",
                confirmText: "Verwijderen",
                cancelText: "Annuleren"
            });
            if (confirmed) {
                window.location.href = this.href;
            }
        });
    });

    // Restore confirmation
    var restoreForm = document.getElementById('restoreForm');
    if (restoreForm) {
        restoreForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            var confirmed = await libConfirm("Weet je zeker dat je deze backup wilt herstellen? Dit overschrijft de huidige database!", {
                title: "Backup herstellen",
                confirmText: "Herstellen",
                cancelText: "Annuleren"
            });
            if (confirmed) {
                this.submit();
            }
        });
    }

    // Edit description
    document.querySelectorAll('.btn-edit-description').forEach(function(btn) {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            var file = this.dataset.file;
            var currentDesc = this.dataset.description || '';
            var newDesc = await libPrompt("Omschrijving voor backup:", { title: 'Backup omschrijving', defaultValue: currentDesc });
            if (newDesc !== null) {
                var form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = '<input type="hidden" name="action" value="update_description">' +
                    '<input type="hidden" name="file" value="' + file + '">' +
                    '<input type="hidden" name="description" value="' + newDesc.replace(/"/g, '&quot;') + '">';
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
});
</script>


<?php
echo '</div></BODY></HTML>';
