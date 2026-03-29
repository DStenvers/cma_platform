<?php
/**
 * SQLite Database Reparatie Tool
 *
 * Controleert automatisch de SQLite gebruikersdatabase en biedt
 * herstel opties aan indien nodig.
 */

require_once __DIR__ . '/../bootstrap.inc';

use App\Library\Arr;
use App\Library\Request;
use App\Library\Response;
use App\Library\Session;
use App\Library\Database;
use Cma\ToolbarHelper;

Response::noCache();

$dbPath = realpath(dirname(__DIR__) . '/../db/cmausers.sqlite');
$action = Request::query('action', '');

// Helper function to format file size
function formatSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

/**
 * Translate SQLite error messages to readable Dutch
 */
function translateSqliteErrors($errors) {
    if (!Arr::isArray($errors)) {
        $errors = [$errors];
    }

    $translated = [];
    $indexErrors = [];

    foreach ($errors as $error) {
        $error = trim($error);
        if (empty($error) || $error === 'ok') continue;

        // Pattern: "wrong # of entries in index idx_name"
        if (preg_match('/wrong # of entries in index (\S+)/', $error, $m)) {
            $indexName = $m[1];
            // Extract readable name from index
            $readableName = str_replace(['idx_', '_'], ['', ' '], $indexName);
            $indexErrors[] = $readableName;
            continue;
        }

        // Pattern: "2nd reference to page X" - duplicate page reference
        if (preg_match('/(\d+)(st|nd|rd|th) reference to page (\d+)/', $error, $m)) {
            $translated[] = "Dubbele verwijzing naar pagina {$m[3]} gevonden (interne structuurfout)";
            continue;
        }

        // Pattern: "Tree X page Y cell Z"
        if (preg_match('/Tree \d+ page \d+ cell \d+/', $error)) {
            // Skip these technical details, they're part of the above errors
            continue;
        }

        // Pattern: "** in database main ***"
        if (strpos($error, '** in database') !== false) {
            continue; // Skip header line
        }

        // Pattern: row X missing from index
        if (preg_match('/row \d+ missing from index (\S+)/', $error, $m)) {
            $indexName = str_replace(['idx_', '_'], ['', ' '], $m[1]);
            $indexErrors[] = $indexName;
            continue;
        }

        // Default: show original if not matched
        if (!empty($error)) {
            $translated[] = $error;
        }
    }

    // Summarize index errors
    if (count($indexErrors) > 0) {
        $uniqueIndexes = array_unique($indexErrors);
        $translated[] = 'Indexen met fouten: ' . implode(', ', $uniqueIndexes);
        $translated[] = '<em>Oplossing: gebruik "Indexen herstellen" om de indexen opnieuw op te bouwen</em>';
    }

    return $translated;
}

/**
 * Close any existing CMA database connections to SQLite
 */
function closeExistingConnections() {
    // Close all database connections
    try {
        Database::closeAll();
    } catch (Exception $e) {
        // Ignore - connections might not exist
    }

    // Force garbage collection to release any lingering connections
    gc_collect_cycles();
}

// Output HTML header
cma_html_header('SQLite repareren');
echo '<body class="contentbody tools tool-sqlite-repair">';

// Build toolbar
ToolbarHelper::start(true);
ToolbarHelper::title('SQLite database repareren');
ToolbarHelper::startRight();
ToolbarHelper::status('Beheer SQLite gebruikersdatabase');
ToolbarHelper::end(true);

echo '<div id="c" class="tools">';

// Check if recovery was performed on this bootstrap
if (!empty($_sqlite_recovery_performed)) {
    echo '<lib-message type="success">Noodherstel is automatisch uitgevoerd bij het opstarten van de server.</lib-message>';
}

// Handle schedule_recovery action - set flag for next server restart
if ($action === 'schedule_recovery') {
    $flagPath = dirname(__DIR__, 2) . '/db/sqlite_emergency_recovery.flag';
    $flagContent = 'Scheduled at: ' . date('Y-m-d H:i:s') . "\n";
    $flagContent .= 'Scheduled by: ' . Session::get('Username', 'unknown') . "\n";

    if (@file_put_contents($flagPath, $flagContent)) {
        echo '<h3>Noodherstel ingepland</h3>';
        echo '<lib-message type="success">';
        echo '<strong>Noodherstel is ingepland!</strong><br><br>';
        echo 'De volgende keer dat de webserver (IIS) herstart wordt, zullen de WAL en SHM bestanden automatisch worden verwijderd.';
        echo '</lib-message>';

        echo '<lib-message type="info">';
        echo '<strong>Volgende stappen:</strong><br><br>';
        echo '1. <strong>Herstart IIS</strong> via command prompt (als administrator):<br>';
        echo '&nbsp;&nbsp;&nbsp;<code>iisreset</code><br><br>';
        echo '2. Of herstart de server volledig<br><br>';
        echo '3. Na herstart wordt het herstel automatisch uitgevoerd<br><br>';
        echo '<em>Let op: Alle niet-opgeslagen wijzigingen in de WAL gaan verloren!</em>';
        echo '</lib-message>';

        echo '<p style="margin-top:15px;">';
        echo '<a href="?action=cancel_recovery" class="btn btn-secondary"><span class="lnr lnr-cross"></span> Herstel annuleren</a> ';
        echo '<a href="?" class="btn btn-primary"><span class="lnr lnr-sync"></span> Terug naar status</a>';
        echo '</p>';
    } else {
        echo '<lib-message type="error">Kon het herstel niet inplannen. Controleer schrijfrechten op de db/ map.</lib-message>';
        echo '<p style="margin-top:15px;"><a href="?" class="btn btn-primary"><span class="lnr lnr-sync"></span> Terug</a></p>';
    }
    echo '</div></body></html>';
    exit;
}

// Handle cancel_recovery action - remove the flag
if ($action === 'cancel_recovery') {
    $flagPath = dirname(__DIR__, 2) . '/db/sqlite_emergency_recovery.flag';
    if (file_exists($flagPath)) {
        @unlink($flagPath);
        echo '<lib-message type="success">Ingepland noodherstel is geannuleerd.</lib-message>';
    } else {
        echo '<lib-message type="info">Er was geen noodherstel ingepland.</lib-message>';
    }
    echo '<p style="margin-top:15px;"><a href="?" class="btn btn-primary"><span class="lnr lnr-sync"></span> Terug naar status</a></p>';
    echo '</div></body></html>';
    exit;
}

// Handle reindex action - rebuild indexes
if ($action === 'reindex') {
    echo '<h3>Indexen herstellen</h3>';

    // Close existing connections first
    closeExistingConnections();

    $messages = [];

    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, 30);

        // Run REINDEX to rebuild all indexes
        $pdo->exec('REINDEX;');
        $messages[] = ['type' => 'success', 'text' => 'Alle indexen zijn opnieuw opgebouwd.'];

        // Run integrity check to verify
        $check = $pdo->query('PRAGMA quick_check;')->fetchAll(PDO::FETCH_COLUMN);
        if (count($check) === 1 && $check[0] === 'ok') {
            $messages[] = ['type' => 'success', 'text' => 'Controle geslaagd - database is nu in orde.'];
        } else {
            $messages[] = ['type' => 'warning', 'text' => 'Er zijn nog steeds problemen gevonden na het herbouwen van indexen.'];
        }

        // Close connection
        $pdo = null;

    } catch (PDOException $e) {
        $errorMsg = $e->getMessage();
        if (strpos($errorMsg, 'locked') !== false || strpos($errorMsg, 'busy') !== false) {
            $messages[] = ['type' => 'error', 'text' => 'Database is vergrendeld door een ander proces.'];
            $messages[] = ['type' => 'info', 'text' => 'Sluit alle CMA vensters, wacht 30 seconden, en probeer opnieuw.'];
        } elseif (strpos($errorMsg, 'malformed') !== false || strpos($errorMsg, 'corrupt') !== false) {
            $messages[] = ['type' => 'error', 'text' => 'Database is beschadigd en kan niet worden gerepareerd met REINDEX.'];
            $messages[] = ['type' => 'info', 'text' => '<strong>Aanbevolen actie:</strong> Probeer eerst "Noodherstel" om de WAL/SHM bestanden te verwijderen. Dit herstelt de database naar de laatste stabiele staat.'];
            $messages[] = ['type' => 'info', 'text' => 'Als dat niet werkt, moet de database worden hersteld vanaf een backup.'];
            echo '<p style="margin-top:15px;"><a href="?action=rebuild" class="btn btn-primary"><span class="lnr lnr-warning"></span> Noodherstel uitvoeren</a></p>';
        } else {
            $messages[] = ['type' => 'error', 'text' => 'Fout bij herstellen: ' . htmlspecialchars($errorMsg)];
        }
    }

    foreach ($messages as $msg) {
        echo '<lib-message type="' . $msg['type'] . '">' . $msg['text'] . '</lib-message>';
    }

    echo '<p style="margin-top:15px;"><a href="?" class="btn btn-primary"><span class="lnr lnr-sync"></span> Opnieuw controleren</a></p>';
    echo '</div></body></html>';
    exit;
}

// Handle rebuild action - emergency recovery
if ($action === 'rebuild') {
    // Show confirmation dialog if not confirmed
    if (Request::query('confirm', '') !== 'ja') {
        echo '<h3>Noodherstel bevestigen</h3>';
        echo '<lib-message type="warning">';
        echo '<strong>Let op!</strong> U staat op het punt om de WAL en SHM bestanden te verwijderen.<br><br>';
        echo '<strong>Dit betekent:</strong><br>';
        echo '• Alle wijzigingen die nog niet naar het hoofdbestand zijn geschreven gaan <strong>verloren</strong><br>';
        echo '• De database wordt teruggebracht naar de staat van de laatste checkpoint<br>';
        echo '• Dit kan minuten tot uren aan werk verliezen<br>';
        echo '• Er wordt <strong>geen backup</strong> gemaakt (een backup van corrupte data is zinloos)<br><br>';
        echo '<strong>Controleer eerst:</strong><br>';
        echo '• Heb je een recente backup via het backup-systeem?<br><br>';
        echo '<strong>Alleen gebruiken als:</strong><br>';
        echo '• De database corrupt of vergrendeld is<br>';
        echo '• Bovenstaande controle een fout gaf';
        echo '</lib-message>';
        echo '<p style="margin-top:15px;">';
        echo '<a href="?action=rebuild&confirm=ja" class="btn btn-primary" onclick="event.preventDefault(); var href=this.href; libConfirm(\'Weet je zeker dat je door wilt gaan?\').then(function(ok){if(ok){window.location.href=href}})"><span class="lnr lnr-warning"></span> Ja, voer noodherstel uit</a> ';
        echo '<a href="?" class="btn btn-secondary">Annuleren</a>';
        echo '</p>';
        echo '</div></body></html>';
        exit;
    }

    echo '<h3>Noodherstel uitgevoerd</h3>';

    // Close existing connections first
    closeExistingConnections();

    // Small delay to allow connections to fully close
    usleep(500000); // 0.5 seconds

    $walPath = $dbPath . '-wal';
    $shmPath = $dbPath . '-shm';

    $messages = [];

    // Note: We don't backup a potentially corrupted database - that's pointless.
    // If the main file is good (only WAL corrupted), removing WAL restores it.
    // If the main file is corrupted, a backup of corrupted data is useless.
    // Users should maintain their own backups via the backup tool.

    // Remove WAL and SHM files
    $removed = [];
    $failed = [];
    if (file_exists($walPath)) {
        if (@unlink($walPath)) {
            $removed[] = 'WAL';
        } else {
            $failed[] = 'WAL';
        }
    }
    if (file_exists($shmPath)) {
        if (@unlink($shmPath)) {
            $removed[] = 'SHM';
        } else {
            $failed[] = 'SHM';
        }
    }

    if (count($removed) > 0) {
        $messages[] = ['type' => 'success', 'text' => 'Verwijderd: ' . implode(', ', $removed) . ' bestanden'];
        $messages[] = ['type' => 'info', 'text' => 'De database is teruggebracht naar de laatste checkpoint.'];
    }
    if (count($failed) > 0) {
        $messages[] = ['type' => 'error', 'text' => 'Kon niet verwijderen: ' . implode(', ', $failed) . ' - bestand is in gebruik door een ander proces.'];
        $messages[] = ['type' => 'info', 'text' => '<strong>Tip:</strong> Sluit alle browservensters met CMA, wacht 30 seconden, en probeer opnieuw. Als dat niet werkt, herstart IIS (iisreset) of de webserver.'];
    }
    if (count($removed) === 0 && count($failed) === 0) {
        $messages[] = ['type' => 'info', 'text' => 'Geen WAL/SHM bestanden gevonden om te verwijderen.'];
    }

    foreach ($messages as $msg) {
        echo '<lib-message type="' . $msg['type'] . '">' . $msg['text'] . '</lib-message>';
    }

    echo '<p style="margin-top:15px;"><a href="?" class="btn btn-primary"><span class="lnr lnr-sync"></span> Opnieuw controleren</a></p>';
    echo '</div></body></html>';
    exit;
}

// Always run the check automatically
echo '<h3>Database status</h3>';

// Show database info table
echo '<table class="tools-table">';
echo '<tr><th>Eigenschap</th><th>Waarde</th></tr>';

$hasProblems = false;
$hasIndexProblems = false;
$walExists = false;
$walSize = 0;

if (file_exists($dbPath)) {
    echo '<tr><td>Pad</td><td><code>' . htmlspecialchars($dbPath) . '</code></td></tr>';
    echo '<tr><td>Hoofdbestand</td><td>' . formatSize(filesize($dbPath)) . ' (laatst gewijzigd: ' . date('d-m-Y H:i:s', filemtime($dbPath)) . ')</td></tr>';

    $walPath = $dbPath . '-wal';
    $shmPath = $dbPath . '-shm';

    if (file_exists($walPath)) {
        $walExists = true;
        $walSize = filesize($walPath);
        $walMod = date('d-m-Y H:i:s', filemtime($walPath));
        $walClass = $walSize > 100000 ? 'style="color: var(--warning-color, orange);"' : '';
        echo '<tr><td>WAL bestand</td><td ' . $walClass . '>' . formatSize($walSize) . ' (laatst gewijzigd: ' . $walMod . ')';
        if ($walSize > 100000) {
            echo ' <span class="lnr lnr-warning"></span>';
        }
        echo '</td></tr>';
    } else {
        echo '<tr><td>WAL bestand</td><td><em>Niet aanwezig</em></td></tr>';
    }

    if (file_exists($shmPath)) {
        echo '<tr><td>SHM bestand</td><td>' . formatSize(filesize($shmPath)) . '</td></tr>';
    } else {
        echo '<tr><td>SHM bestand</td><td><em>Niet aanwezig</em></td></tr>';
    }
} else {
    $hasProblems = true;
    echo '<tr><td colspan="2"><lib-message type="error">Database bestand niet gevonden!</lib-message></td></tr>';
}
echo '</table>';

// Brief explanation of WAL/SHM
echo '<p class="info-text"><span class="lnr lnr-question-circle"></span> ';
echo '<strong>WAL</strong> (Write-Ahead Log) bevat recente wijzigingen die nog niet naar het hoofdbestand zijn geschreven. ';
echo '<strong>SHM</strong> (Shared Memory) coördineert toegang tussen processen. ';
echo 'Beide bestanden zijn normaal en worden automatisch beheerd.';
echo '</p>';

// Run integrity checks
if (file_exists($dbPath)) {
    echo '<h3>Controle resultaten</h3>';

    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $results = [];

        // Step 1: Integrity check
        $integrityResult = $pdo->query('PRAGMA integrity_check;')->fetchAll(PDO::FETCH_COLUMN);
        $integrityOk = count($integrityResult) === 1 && $integrityResult[0] === 'ok';
        if (!$integrityOk) {
            $hasProblems = true;
            // Check if it's specifically an index problem
            $rawError = implode(' ', $integrityResult);
            if (strpos($rawError, 'index') !== false) {
                $hasIndexProblems = true;
            }
        }

        $translatedErrors = $integrityOk ? ['Database is intact'] : translateSqliteErrors($integrityResult);
        $results['Integriteitscontrole'] = [
            'status' => $integrityOk ? 'ok' : 'fail',
            'detail' => implode('<br>', $translatedErrors),
            'html' => true
        ];

        // Step 2: Check current journal mode
        $journalMode = $pdo->query('PRAGMA journal_mode;')->fetchColumn();
        $results['Journaalmodus'] = [
            'status' => 'info',
            'detail' => strtoupper($journalMode)
        ];

        // Step 3: WAL checkpoint if applicable
        if (strtolower($journalMode) === 'wal') {
            $walCheck = $pdo->query('PRAGMA wal_checkpoint(PASSIVE);')->fetch(PDO::FETCH_ASSOC);
            $walPages = $walCheck[1] ?? 0;

            if ($walPages > 0) {
                // Try truncate checkpoint
                $walTruncate = $pdo->query('PRAGMA wal_checkpoint(TRUNCATE);')->fetch(PDO::FETCH_ASSOC);
                $truncateBlocked = $walTruncate[0] ?? 0;

                if ($truncateBlocked == 0) {
                    $results['WAL checkpoint'] = [
                        'status' => 'ok',
                        'detail' => $walPages . ' pagina\'s geschreven naar hoofdbestand'
                    ];
                } else {
                    $hasProblems = true;
                    $results['WAL checkpoint'] = [
                        'status' => 'warning',
                        'detail' => 'Checkpoint geblokkeerd - database in gebruik door ander proces'
                    ];
                }
            } else {
                $results['WAL checkpoint'] = [
                    'status' => 'ok',
                    'detail' => 'Geen wachtende wijzigingen'
                ];
            }
        }

        // Step 4: Quick check
        $quickResult = $pdo->query('PRAGMA quick_check;')->fetchAll(PDO::FETCH_COLUMN);
        $quickOk = count($quickResult) === 1 && $quickResult[0] === 'ok';
        if (!$quickOk) {
            $hasProblems = true;
            $rawError = implode(' ', $quickResult);
            if (strpos($rawError, 'index') !== false) {
                $hasIndexProblems = true;
            }
        }

        $translatedQuick = $quickOk ? ['OK'] : translateSqliteErrors($quickResult);
        $results['Snelle controle'] = [
            'status' => $quickOk ? 'ok' : 'warning',
            'detail' => implode('<br>', $translatedQuick),
            'html' => true
        ];

        // Step 5: List tables with row counts
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;")->fetchAll(PDO::FETCH_COLUMN);
        $tableInfo = [];
        foreach ($tables as $table) {
            $count = $pdo->query("SELECT COUNT(*) FROM \"$table\"")->fetchColumn();
            $tableInfo[] = $table . ' (' . $count . ')';
        }
        $results['Tabellen'] = [
            'status' => 'info',
            'detail' => implode(', ', $tableInfo)
        ];

        // Close connection
        $pdo = null;

        // Output results table
        echo '<table class="tools-table">';
        echo '<tr><th style="width:200px;">Controle</th><th>Status</th><th>Details</th></tr>';
        foreach ($results as $name => $result) {
            $statusIcon = match($result['status']) {
                'ok' => '<span class="status-ok lnr lnr-checkmark-circle"></span>',
                'fail' => '<span class="status-fail lnr lnr-cross-circle"></span>',
                'warning' => '<span class="status-warning lnr lnr-warning"></span>',
                default => '<span class="status-info lnr lnr-question-circle"></span>'
            };
            $detail = ($result['html'] ?? false) ? $result['detail'] : htmlspecialchars($result['detail']);
            echo '<tr>';
            echo '<td>' . htmlspecialchars($name) . '</td>';
            echo '<td style="text-align:center;">' . $statusIcon . '</td>';
            echo '<td>' . $detail . '</td>';
            echo '</tr>';
        }
        echo '</table>';

        // Overall status message and repair options
        if ($hasProblems) {
            echo '<lib-message type="warning" style="margin-top:15px;">';
            echo '<strong>Database heeft problemen.</strong>';
            echo '</lib-message>';

            echo '<div class="repair-options">';

            if ($hasIndexProblems) {
                echo '<div class="repair-option">';
                echo '<h4><span class="lnr lnr-layers"></span> Indexen herstellen</h4>';
                echo '<p>Bouwt alle indexen opnieuw op. Dit is veilig en lost de meeste index-gerelateerde fouten op zonder dataverlies.</p>';
                echo '<a href="?action=reindex" class="btn btn-primary"><span class="lnr lnr-sync"></span> Indexen herstellen</a>';
                echo '</div>';
            }

            echo '<div class="repair-option">';
            echo '<h4><span class="lnr lnr-warning"></span> Noodherstel (direct)</h4>';
            echo '<p>Verwijdert WAL/SHM bestanden direct. Kan mislukken als database in gebruik is.</p>';
            echo '<a href="?action=rebuild" class="btn btn-secondary"><span class="lnr lnr-warning"></span> Direct uitvoeren</a>';
            echo '</div>';

            echo '<div class="repair-option">';
            echo '<h4><span class="lnr lnr-clock"></span> Noodherstel (ingepland)</h4>';
            echo '<p>Plant herstel in voor na server herstart. <strong>Aanbevolen</strong> als directe poging mislukt vanwege vergrendeling.</p>';
            $flagPath = dirname(__DIR__, 2) . '/db/sqlite_emergency_recovery.flag';
            if (file_exists($flagPath)) {
                echo '<lib-message type="info">Herstel is al ingepland. Herstart de server om uit te voeren.</lib-message>';
                echo '<a href="?action=cancel_recovery" class="btn btn-secondary"><span class="lnr lnr-cross"></span> Annuleren</a>';
            } else {
                echo '<a href="?action=schedule_recovery" class="btn btn-primary"><span class="lnr lnr-clock"></span> Inplannen</a>';
            }
            echo '</div>';

            echo '</div>';
        } else {
            echo '<lib-message type="success" style="margin-top:15px;">Database is in orde.</lib-message>';
        }

    } catch (PDOException $e) {
        $hasProblems = true;
        echo '<lib-message type="error">';
        echo '<strong>Database fout:</strong> ' . htmlspecialchars($e->getMessage());
        echo '</lib-message>';

        echo '<lib-message type="warning" style="margin-top:10px;">';
        echo 'De database kan niet worden geopend. Probeer noodherstel om de WAL/SHM bestanden te verwijderen.';
        echo '</lib-message>';

        echo '<div class="repair-options">';

        echo '<div class="repair-option">';
        echo '<h4><span class="lnr lnr-warning"></span> Noodherstel (direct)</h4>';
        echo '<p>Verwijdert WAL/SHM bestanden direct. Kan mislukken als database in gebruik is.</p>';
        echo '<a href="?action=rebuild" class="btn btn-secondary"><span class="lnr lnr-warning"></span> Direct uitvoeren</a>';
        echo '</div>';

        echo '<div class="repair-option">';
        echo '<h4><span class="lnr lnr-clock"></span> Noodherstel (ingepland)</h4>';
        echo '<p>Plant herstel in voor na server herstart. <strong>Aanbevolen</strong> als directe poging mislukt.</p>';
        $flagPath = dirname(__DIR__, 2) . '/db/sqlite_emergency_recovery.flag';
        if (file_exists($flagPath)) {
            echo '<lib-message type="info">Herstel is al ingepland. Herstart de server om uit te voeren.</lib-message>';
            echo '<a href="?action=cancel_recovery" class="btn btn-secondary"><span class="lnr lnr-cross"></span> Annuleren</a>';
        } else {
            echo '<a href="?action=schedule_recovery" class="btn btn-primary"><span class="lnr lnr-clock"></span> Inplannen</a>';
        }
        echo '</div>';

        echo '</div>';
    }
}

echo '</div>';

// Styles
echo '<style>
.status-ok { color: var(--success-color, #28a745); }
.status-fail { color: var(--danger-color, #dc3545); }
.status-warning { color: var(--warning-color, #ffc107); }
.status-info { color: var(--info-color, #17a2b8); }
.info-text {
    color: var(--text-muted);
    font-size: 0.9em;
    margin: 15px 0;
}
.info-text .lnr {
    margin-right: 5px;
}
.repair-options {
    display: flex;
    gap: 20px;
    margin-top: 15px;
}
.repair-option {
    flex: 1;
    padding: 15px;
    border: 1px solid var(--border-color, #ddd);
    border-radius: 8px;
    background: var(--bg-surface, #fff);
}
.repair-option h4 {
    margin: 0 0 10px 0;
    font-size: var(--font-size-md);
    display: flex;
    align-items: center;
    gap: 8px;
}
.repair-option p {
    margin: 0 0 15px 0;
    font-size: var(--font-size);
    color: var(--text-muted);
}
</style>';

echo '</body></html>';
