<?php
/**
 * Database Migrations Tool
 *
 * Admin interface for viewing and applying database migrations.
 * Note: This tool requires Admin level (not Developer) because migrations
 * are needed to set up the user level system in the first place.
 */

use App\Library\Error;
use App\Library\Request;
use App\Library\Response;
use App\Library\Session;
use Cma\SecurityHelper;
use Cma\Services\MigrationService;
use Cma\ToolbarHelper;

// EARLY AJAX CHECK - before bootstrap to avoid HTML output
// Note: Uses $_GET/$_POST/$_SERVER directly because this runs before bootstrap loads.
$isAjaxRequest = (isset($_GET['ajax']) && $_GET['ajax'] === '1')
              || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
              || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($isAjaxRequest) {
    // Set JSON header immediately - MUST be before any output
    header('Content-Type: application/json; charset=utf-8');
    // Prevent any HTML error output
    ini_set('html_errors', '0');

    // Catch any fatal errors and return as JSON
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            // Clear any output buffer
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'PHP Fatal Error: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']
            ]);
        }
    });

    // Load bootstrap
    require_once __DIR__ . '/../bootstrap.inc';

    // Check admin (return JSON error if not)
    if (!SecurityHelper::isAdmin()) {
        echo json_encode(['success' => false, 'error' => 'Toegang geweigerd']);
        exit;
    }

    $autoBackup = Request::post('auto_backup', '') === '1';
    $migrationService = new MigrationService($autoBackup);
    $action = Request::post('action', '');
    $targetVersionToApply = Request::post('target_version', '');

    if (!empty($targetVersionToApply)) {
        if ($action === 'apply_single') {
            $result = $migrationService->applySingleMigration($targetVersionToApply);
        } elseif ($action === 'rerun') {
            $result = $migrationService->rerunMigration($targetVersionToApply);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
        }

        Session::remove('_migration_check_done');
        Session::remove('_migration_needed');
        Session::remove('_migration_current');
        Session::remove('_migration_target');

        echo json_encode($result);
        exit;
    }

    // Return endpoint info for GET requests (helpful for testing)
    if (Request::server('REQUEST_METHOD') === 'GET') {
        echo json_encode([
            'success' => true,
            'message' => 'Migrations API endpoint',
            'usage' => 'POST with action=apply_single|rerun, target_version=X.X.X'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Missing target_version parameter']);
    }
    exit;
}

// Regular page request - load bootstrap
require_once __DIR__ . '/../bootstrap.inc';

// Admin level required (not Developer - that would be a catch-22 for setting up user levels)
if (!SecurityHelper::isAdmin()) {
    Error::page('Toegang geweigerd', 'Deze functie is alleen beschikbaar voor administrators.', false);
    exit;
}

Response::noCache();

// Handle actions
$action = Request::post('action', '');
$targetVersionToApply = Request::post('target_version', '');
$autoBackup = Request::post('auto_backup', '') === '1';
$result = null;

if ($action === 'apply_all') {
    // Initialize migration service with auto-backup option
    $migrationService = new MigrationService($autoBackup);

    // Apply up to selected version (empty string means all)
    $result = $targetVersionToApply !== ''
        ? $migrationService->applyUpToVersion($targetVersionToApply)
        : $migrationService->applyAllPending();

    // Clear migration check session flags so bootstrap re-checks
    Session::remove('_migration_check_done');
    Session::remove('_migration_needed');
    Session::remove('_migration_current');
    Session::remove('_migration_target');
} elseif ($action === 'rerun') {
    // Rerun a specific migration version (no backup for reruns)
    $migrationService = new MigrationService(false);
    $result = $migrationService->rerunMigration($targetVersionToApply);

    // Clear migration check session flags
    Session::remove('_migration_check_done');
    Session::remove('_migration_needed');
    Session::remove('_migration_current');
    Session::remove('_migration_target');
}

// Get current state (after potential migration)
$migrationService = new MigrationService(); // Reload to get fresh state
// HIDDEN: Version display section - commented out per user request
// $currentVersions = $migrationService->getCurrentVersions();
$pendingMigrations = $migrationService->getPendingMigrations();
$allMigrations = $migrationService->getAllMigrations();

// Sort migrations descending by version (newest first)
usort($allMigrations, fn($a, $b) => version_compare($b['version'], $a['version']));
$targetVersion = $migrationService->getTargetVersion();
$history = $migrationService->getMigrationHistory();
$errors = $migrationService->getErrors();

cma_html_header('Database migraties');
echo '<body class="contentbody tools migrations">';
ToolbarHelper::report('Database migraties', false, false, false, false, 'Beheer en voer database migraties uit');
echo '<div id="c" class="tools">';

// Show result from action
if ($result !== null) {
    if ($result['success']) {
        echo '<lib-message type="success" closable>';
        echo '<strong>Migraties succesvol toegepast!</strong>';
        if (!empty($result['applied'])) {
            echo '<br>Toegepaste versies: ' . implode(' → ', $result['applied']);
        }
        echo '</lib-message>';
    } else {
        echo '<lib-message type="error" closable>';
        echo '<strong>Migratie is mislukt</strong>';
        echo '</lib-message>';
    }

    // Show log
    if (!empty($result['log'])) {
        echo '<h3>Uitvoeringslog</h3>';
        echo '<div class="log-output" style="background:#1e1e1e;color:#d4d4d4;padding:15px;font-family:monospace;font-size:var(--font-size-sm);border-radius:4px;overflow-y:auto;white-space:pre-wrap;">';
        foreach ($result['log'] as $line) {
            $line = htmlspecialchars($line);
            // Color coding
            if (strpos($line, '✓') !== false) {
                $line = '<span style="color:#4ec9b0">' . $line . '</span>';
            } elseif (strpos($line, '✗') !== false || strpos($line, 'MISLUKT') !== false) {
                $line = '<span style="color:#f14c4c">' . $line . '</span>';
            } elseif (strpos($line, '⊘') !== false || strpos($line, 'Waarschuwing') !== false || strpos($line, '⚠') !== false) {
                $line = '<span style="color:#cca700">' . $line . '</span>';
            } elseif (strpos($line, '━') !== false || strpos($line, '═') !== false) {
                $line = '<span style="color:#569cd6">' . $line . '</span>';
            } elseif (strpos($line, 'Versie ') === 0) {
                $line = '<span style="color:#9cdcfe">' . $line . '</span>';
            }
            echo $line . "\n";
        }
        echo '</div>';
    }
}

// Show errors from service
if (!empty($errors)) {
    echo '<lib-message type="error" closable><strong>Fouten:</strong><ul style="margin:5px 0 0 0;">';
    foreach ($errors as $error) {
        // Converteer newlines naar <br> voor betere weergave van multi-line errors
        $formattedError = nl2br(htmlspecialchars($error));
        echo '<li style="white-space:pre-wrap;margin-bottom:10px;">' . $formattedError . '</li>';
    }
    echo '</ul></lib-message>';
}

// HIDDEN: Current Versions Section - commented out per user request
/*
// Current Versions Section
echo '<h3>Huidige versies</h3>';
echo '<div class="status-grid">';

// Display order: Applicatie, Gebruikers (skip Repository)
$displayOrder = ['data' => 'Applicatie', 'users' => 'Gebruikers'];

foreach ($displayOrder as $db => $dbName) {
    $version = $currentVersions[$db] ?? 'onbekend';
    $class = '';
    $icon = '';
    if ($version === 'fout' || $version === 'geen verbinding' || $version === 'onbekend') {
        $class = 'error';
        $icon = '✗';
    } elseif (version_compare($version, $targetVersion, '<')) {
        $class = 'pending';
        $icon = '⚠';
    } else {
        $class = 'ok';
        $icon = '✓';
    }

    echo '<div class="status-card">';
    echo '<h4>' . htmlspecialchars($dbName) . '</h4>';
    echo '<div class="value ' . $class . '"><span class="status-icon">' . $icon . '</span> ' . htmlspecialchars($version) . '</div>';
    echo '</div>';
}

// JSON Configuration versions
// Note: __DIR__ is tools/, so paths need ../ to reach cma/ root
$cmaRoot = dirname(__DIR__);
$siteRoot = dirname($cmaRoot);

// Internal CMA system forms (users, groups, _menus, etc.)
$internalFormNames = ['users', 'groups', '_menus', '_menu_items', 'cmamonitoring', 'auditlog'];

$jsonConfigs = [
    // Internal CMA forms (system forms)
    'forms_internal' => [
        'name' => 'Formulieren (CMA)',
        'path' => $cmaRoot . '/assets/forms/definitions',
        'type' => 'dir',
        'filter' => $internalFormNames
    ],
    // External application forms - check site/config/forms first, fallback to cma/assets/forms
    'forms_external' => [
        'name' => 'Formulieren (App)',
        'path' => $siteRoot . '/config/forms',
        'fallback' => $cmaRoot . '/assets/forms/definitions',
        'type' => 'dir',
        'exclude' => $internalFormNames
    ],
    'databases' => ['name' => 'Databases', 'path' => $cmaRoot . '/config/databases.json', 'type' => 'file'],
    'menu' => ['name' => 'Menu', 'path' => $cmaRoot . '/config/menu.json', 'type' => 'file'],
    'reports' => ['name' => 'Reports', 'path' => $cmaRoot . '/config/reports.json', 'type' => 'file'],
    'data-sources' => ['name' => 'DataSources', 'path' => dirname($cmaRoot) . '/assets/datastores/data-sources.json', 'type' => 'file'],
    'contentblocks' => ['name' => 'ContentBlocks', 'path' => $cmaRoot . '/assets/contentblocks/contentblocks.json', 'type' => 'file'],
];

foreach ($jsonConfigs as $key => $config) {
    $version = '-';
    $class = 'pending';
    $icon = '⚠';

    if ($config['type'] === 'dir') {
        // For forms directory, find the lowest version number across all form files
        $formDir = $config['path'];
        $fallbackDir = $config['fallback'] ?? null;
        $filterNames = $config['filter'] ?? null;  // Only include these form names
        $excludeNames = $config['exclude'] ?? null; // Exclude these form names

        // Use fallback directory if primary is empty or doesn't exist
        if ($fallbackDir && (!is_dir($formDir) || count(glob($formDir . '/*.json')) === 0)) {
            $formDir = $fallbackDir;
        }

        if (is_dir($formDir)) {
            $lowestVersion = null;
            $formFiles = glob($formDir . '/*.json');
            foreach ($formFiles as $file) {
                $formName = basename($file, '.json');

                // Apply filter: only include if in filter list
                if ($filterNames !== null && !in_array($formName, $filterNames)) {
                    // Also check for subforms (e.g., users_notifications)
                    $isSubform = false;
                    foreach ($filterNames as $parentForm) {
                        if (strpos($formName, $parentForm . '_') === 0) {
                            $isSubform = true;
                            break;
                        }
                    }
                    if (!$isSubform) {
                        continue;
                    }
                }

                // Apply exclude: skip if in exclude list
                if ($excludeNames !== null) {
                    if (in_array($formName, $excludeNames)) {
                        continue;
                    }
                    // Also exclude subforms of excluded forms
                    $isExcludedSubform = false;
                    foreach ($excludeNames as $excludedForm) {
                        if (strpos($formName, $excludedForm . '_') === 0) {
                            $isExcludedSubform = true;
                            break;
                        }
                    }
                    if ($isExcludedSubform) {
                        continue;
                    }
                }

                $content = @file_get_contents($file);
                if ($content) {
                    $data = @json_decode($content, true);
                    $formVersion = $data['version'] ?? null;
                    if ($formVersion !== null) {
                        if ($lowestVersion === null || version_compare($formVersion, $lowestVersion, '<')) {
                            $lowestVersion = $formVersion;
                        }
                    }
                }
            }
            if ($lowestVersion !== null) {
                $version = $lowestVersion;
                $class = 'ok';
                $icon = '✓';
            } elseif (!empty($formFiles)) {
                $version = 'geen versie';
            }
        }
    } else {
        // For JSON files, read the version field
        if (file_exists($config['path'])) {
            $content = @file_get_contents($config['path']);
            if ($content) {
                $data = @json_decode($content, true);
                $version = $data['version'] ?? 'geen versie';
                if ($version !== 'geen versie') {
                    $class = 'ok';
                    $icon = '✓';
                }
            }
        }
    }

    echo '<div class="status-card">';
    echo '<h4>' . htmlspecialchars($config['name']) . '</h4>';
    echo '<div class="value ' . $class . '"><span class="status-icon">' . $icon . '</span> ' . htmlspecialchars($version) . '</div>';
    echo '</div>';
}

echo '<div class="status-arrow">→</div>';

echo '<div class="status-card">';
echo '<h4>Doelversie</h4>';
echo '<div class="value">' . htmlspecialchars($targetVersion) . '</div>';
echo '</div>';

echo '</div>';
*/

// Migrations Section - single table with checkbox to show completed
$pendingVersions = array_column($pendingMigrations, 'version');
$completedCount = count($allMigrations) - count($pendingMigrations);
$hasPending = !empty($pendingMigrations);

// Success message is shown by JavaScript after AJAX migration completes (not on initial load)

// Build pending versions array for JavaScript - output script early so onchange handlers work
$sortedPending = $pendingMigrations;
usort($sortedPending, fn($a, $b) => version_compare($a['version'], $b['version']));
$lastPendingVersion = !empty($sortedPending) ? end($sortedPending)['version'] : '';
$pendingVersionsJs = json_encode(array_column($sortedPending, 'version'));

echo '<script>
var pendingVersions = ' . $pendingVersionsJs . ';
var migrationInProgress = false;
var retryMigrations = null;  // Set when migration fails, used for retry

function updateMigrationButton() {
    var radios = document.querySelectorAll("input[name=target_version]");
    var selectedVersion = null;
    radios.forEach(function(radio) {
        if (radio.checked) {
            selectedVersion = radio.value;
        }
    });

    if (selectedVersion) {
        var button = document.getElementById("applyButton");
        var countSpan = document.getElementById("migrationCount");
        var lastVersion = pendingVersions.length > 0 ? pendingVersions[pendingVersions.length - 1] : "";
        var isLastVersion = (selectedVersion === lastVersion);

        if (button) {
            // Count how many migrations will be applied
            var count = 0;
            for (var i = 0; i < pendingVersions.length; i++) {
                if (compareVersions(pendingVersions[i], selectedVersion) <= 0) {
                    count++;
                }
            }

            if (isLastVersion) {
                // All migrations selected
                button.textContent = "Alle migraties toepassen";
                if (countSpan) {
                    countSpan.textContent = "";
                }
            } else {
                // Partial selection
                button.textContent = "Migraties toepassen tot versie " + selectedVersion;
                if (countSpan) {
                    var migratieWord = (pendingVersions.length === 1) ? "migratie" : "migraties";
                    countSpan.textContent = count + " van " + pendingVersions.length + " " + migratieWord;
                }
            }
        }
    }
}

function compareVersions(a, b) {
    var pa = a.split(".").map(Number);
    var pb = b.split(".").map(Number);
    for (var i = 0; i < 3; i++) {
        if (pa[i] > pb[i]) return 1;
        if (pa[i] < pb[i]) return -1;
    }
    return 0;
}

function markMigrationComplete(version) {
    var row = document.querySelector("tr[data-version=\"" + version + "\"]");
    if (row) {
        // Replace radio button with checkmark
        var radioCell = row.querySelector("td:first-child");
        if (radioCell) {
            radioCell.innerHTML = "<span style=\"color:#28a745;font-size:var(--font-size-xl);\">✓</span>";
        }
        // Update status badge
        var statusCell = row.querySelector("td:last-child");
        if (statusCell) {
            statusCell.innerHTML = "<span class=\"badge badge-success\">Toegepast</span>";
        }
        // Change row background
        row.style.background = "#e8f5e9";
    }
}

function markMigrationFailed(version, error) {
    var row = document.querySelector("tr[data-version=\"" + version + "\"]");
    if (row) {
        // Replace radio button with X
        var radioCell = row.querySelector("td:first-child");
        if (radioCell) {
            radioCell.innerHTML = "<span style=\"color:#dc3545;font-size:var(--font-size-xl);\">✗</span>";
        }
        // Update status badge
        var statusCell = row.querySelector("td:last-child");
        if (statusCell) {
            statusCell.innerHTML = "<span class=\"badge badge-error\">Mislukt</span>";
        }
        // Change row background
        row.style.background = "#ffebee";
    }
}

function markMigrationInProgress(version) {
    var row = document.querySelector("tr[data-version=\"" + version + "\"]");
    if (row) {
        // Replace radio button with spinner
        var radioCell = row.querySelector("td:first-child");
        if (radioCell) {
            radioCell.innerHTML = "<span class=\"migration-spinner\"></span>";
        }
        // Update status badge
        var statusCell = row.querySelector("td:last-child");
        if (statusCell) {
            statusCell.innerHTML = "<span class=\"badge badge-info\">Bezig...</span>";
        }
        // Change row background
        row.style.background = "#e3f2fd";
    }
}

async function applyMigration(version, autoBackup) {
    var formData = new FormData();
    formData.append("action", "apply_single");
    formData.append("target_version", version);
    if (autoBackup) {
        formData.append("auto_backup", "1");
    }

    // Use absolute URL to avoid clean URL issues
    var fetchUrl = "/cma/tools/tools_migrations.php?ajax=1";
    console.log("[migrations] Fetching:", fetchUrl, "version:", version);

    try {
        var response = await fetch(fetchUrl, {
            method: "POST",
            body: formData,
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        });

        console.log("[migrations] Response status:", response.status, response.statusText);

        // Check if response is OK
        if (!response.ok) {
            var errorText = await response.text();
            cmaLog.error("[migrations] Error response body:", errorText.substring(0, 500));
            return { success: false, error: "HTTP " + response.status + ": " + response.statusText };
        }

        var text = await response.text();

        // Try to parse as JSON
        try {
            var result = JSON.parse(text);
            return result;
        } catch (parseError) {
            cmaLog.error("JSON parse error:", parseError, "Response:", text.substring(0, 500));
            var preview = text.substring(0, 300);
            if (text.length > 300) preview += "...";
            return { success: false, error: "Ongeldige server response. Response preview: " + preview };
        }
    } catch (e) {
        cmaLog.error("Fetch error:", e);
        return { success: false, error: e.message };
    }
}

async function submitMigration(e) {
    e.preventDefault();

    if (migrationInProgress) {
        return false;
    }

    var migrationsToApply = [];

    // Get radio buttons (needed for disabling during migration)
    var radios = document.querySelectorAll("input[name=target_version]");

    // Check if this is a retry (use stored remaining migrations)
    if (retryMigrations && retryMigrations.length > 0) {
        migrationsToApply = retryMigrations.slice();  // Copy array
        retryMigrations = null;  // Clear for next run
    } else {
        // Get selected target version
        var selectedVersion = null;
        radios.forEach(function(radio) {
            if (radio.checked) {
                selectedVersion = radio.value;
            }
        });

        if (!selectedVersion) {
            alert("Selecteer een doelversie");
            return false;
        }

        // Get migrations to apply (up to selected version)
        for (var i = 0; i < pendingVersions.length; i++) {
            if (compareVersions(pendingVersions[i], selectedVersion) <= 0) {
                migrationsToApply.push(pendingVersions[i]);
            }
        }
    }

    if (migrationsToApply.length === 0) {
        return false;
    }

    // Get auto-backup checkbox state
    var autoBackupCheckbox = document.getElementById("autoBackupCheckbox");
    var autoBackup = autoBackupCheckbox && autoBackupCheckbox.checked;

    migrationInProgress = true;

    // Disable button and show progress
    var button = document.getElementById("applyButton");
    var countSpan = document.getElementById("migrationCount");
    button.disabled = true;
    button.textContent = "Bezig met migraties...";

    // Disable all radio buttons and checkbox
    radios.forEach(function(radio) {
        radio.disabled = true;
    });
    if (autoBackupCheckbox) {
        autoBackupCheckbox.disabled = true;
    }

    var successCount = 0;
    var failedVersion = null;
    var remainingMigrations = [];  // Track remaining migrations for retry
    var allLogs = [];

    // Hide backup checkbox during migration
    var backupDiv = autoBackupCheckbox ? autoBackupCheckbox.closest("div") : null;
    if (backupDiv) {
        backupDiv.style.display = "none";
    }

    // Apply migrations one by one
    console.log("Starting migrations:", migrationsToApply, "autoBackup:", autoBackup);
    for (var i = 0; i < migrationsToApply.length; i++) {
        var version = migrationsToApply[i];
        console.log("Processing migration " + (i + 1) + "/" + migrationsToApply.length + ": " + version);

        // Update progress
        countSpan.textContent = "Migratie " + (i + 1) + " van " + migrationsToApply.length + "...";

        // Mark as in progress
        markMigrationInProgress(version);

        // Apply migration (only backup on first migration)
        var result = await applyMigration(version, autoBackup && i === 0);
        console.log("Migration result for " + version + ":", result);

        if (result.log) {
            allLogs = allLogs.concat(result.log);
        }

        if (result.success) {
            markMigrationComplete(version);
            successCount++;
            console.log("Migration " + version + " succeeded, continuing...");
        } else {
            markMigrationFailed(version, result.error || "Onbekende fout");
            failedVersion = version;
            // Store remaining migrations (including failed one) for retry
            remainingMigrations = migrationsToApply.slice(i);
            if (result.error) {
                allLogs.push("✗ Fout bij migratie versie " + version + ": " + result.error);
            }
            if (result.sql) {
                allLogs.push("  SQL: " + result.sql);
            }
            if (result.debug) {
                allLogs.push("  Debug: " + JSON.stringify(result.debug, null, 2));
            }
            console.log("Migration " + version + " failed, stopping. Remaining:", remainingMigrations);
            console.log("Failed result:", result);
            break; // Stop on first failure
        }
    }
    console.log("Migration loop finished. Success count:", successCount, "Failed:", failedVersion);

    migrationInProgress = false;

    // Show completion message
    var resultDiv = document.getElementById("migrationResult");
    if (!resultDiv) {
        resultDiv = document.createElement("lib-message");
        resultDiv.id = "migrationResult";
        var form = document.getElementById("migrationForm");
        form.parentNode.insertBefore(resultDiv, form);
    }

    if (failedVersion) {
        resultDiv.setAttribute("type", "error");
        resultDiv.innerHTML = "<strong>Migratie mislukt bij versie " + failedVersion + "</strong><br>" + successCount + " van " + migrationsToApply.length + " migraties toegepast.";
        button.textContent = "Opnieuw proberen";
        button.disabled = false;
        // Store remaining migrations for retry
        retryMigrations = remainingMigrations;
    } else {
        // Clear caches in the background
        fetch("/cma/tools/tools_clearcache.php?silent=1").catch(function() {});

        resultDiv.setAttribute("type", "success");
        resultDiv.innerHTML = "<strong>Alle migraties succesvol toegepast!</strong><br>" + successCount + " migraties uitgevoerd.<br><em>Caches zijn geleegd.</em>";
        countSpan.textContent = "";

        // Update pending versions array
        pendingVersions = pendingVersions.filter(function(v) {
            return !migrationsToApply.includes(v);
        });

        // If no more pending, hide button and warning
        if (pendingVersions.length === 0) {
            button.style.display = "none";
            var warningDiv = document.querySelector(\'lib-message[type="warning"]\');
            if (warningDiv) {
                warningDiv.style.display = "none";
            }
        } else {
            button.textContent = "Alle migraties toepassen";
            button.disabled = false;
        }
    }

    // Re-enable remaining radio buttons and checkbox
    radios.forEach(function(radio) {
        radio.disabled = false;
    });
    if (autoBackupCheckbox) {
        autoBackupCheckbox.disabled = false;
    }

    // Show log if available
    if (allLogs.length > 0) {
        var logDiv = document.getElementById("migrationLog");
        if (!logDiv) {
            logDiv = document.createElement("div");
            logDiv.id = "migrationLog";
            resultDiv.parentNode.insertBefore(logDiv, resultDiv.nextSibling);
        }
        logDiv.innerHTML = "<h3>Uitvoeringslog</h3><div class=\"log-output\" style=\"background:#1e1e1e;color:#d4d4d4;padding:15px;font-family:monospace;font-size:var(--font-size-sm);border-radius:4px;overflow-y:auto;white-space:pre-wrap;\">" +
            allLogs.map(function(line) {
                line = line.replace(/</g, "&lt;").replace(/>/g, "&gt;");
                if (line.indexOf("✓") !== -1) {
                    return "<span style=\"color:#4ec9b0\">" + line + "</span>";
                } else if (line.indexOf("✗") !== -1 || line.indexOf("MISLUKT") !== -1) {
                    return "<span style=\"color:#f14c4c\">" + line + "</span>";
                } else if (line.indexOf("⊘") !== -1 || line.indexOf("Waarschuwing") !== -1 || line.indexOf("⚠") !== -1) {
                    return "<span style=\"color:#cca700\">" + line + "</span>";
                } else if (line.indexOf("━") !== -1 || line.indexOf("═") !== -1) {
                    return "<span style=\"color:#569cd6\">" + line + "</span>";
                } else if (line.indexOf("Versie ") === 0) {
                    return "<span style=\"color:#9cdcfe\">" + line + "</span>";
                }
                return line;
            }).join("\n") + "</div>";
    }

    return false;
}

async function rerunMigration(version) {
    var confirmed = await libConfirm("Weet je zeker dat je migratie " + version + " opnieuw wilt uitvoeren?", {
        title: "Migratie opnieuw uitvoeren",
        confirmText: "Uitvoeren",
        cancelText: "Annuleren"
    });
    if (!confirmed) {
        return false;
    }

    // Find the row and update it
    var row = document.querySelector("tr[data-version=\"" + version + "\"]");
    var statusCell = row ? row.querySelector("td:last-child") : null;
    var originalContent = statusCell ? statusCell.innerHTML : "";

    // Show spinner
    if (statusCell) {
        statusCell.innerHTML = "<span class=\"migration-spinner\"></span> <span class=\"badge badge-info\">Bezig...</span>";
    }

    // Make AJAX call
    var formData = new FormData();
    formData.append("action", "rerun");
    formData.append("target_version", version);

    try {
        var response = await fetch("/cma/tools/tools_migrations.php?ajax=1", {
            method: "POST",
            body: formData,
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        });

        var result = await response.json();

        // Show result in console area
        var resultDiv = document.getElementById("migrationResult");
        if (!resultDiv) {
            resultDiv = document.createElement("lib-message");
            resultDiv.id = "migrationResult";
            var table = document.querySelector(".lib_table");
            table.parentNode.insertBefore(resultDiv, table);
        }

        if (result.success) {
            if (statusCell) {
                statusCell.innerHTML = "<span class=\"badge badge-success\">Toegepast</span><br><a href=\"#\" onclick=\"return rerunMigration(\'" + version + "\')\" style=\"font-size:var(--font-size-xs);color:#666;\">opnieuw</a>";
            }
            resultDiv.setAttribute("type", "success");
            resultDiv.innerHTML = "<strong>Migratie " + version + " succesvol uitgevoerd!</strong>";
        } else {
            if (statusCell) {
                statusCell.innerHTML = "<span class=\"badge badge-error\">Mislukt</span><br><a href=\"#\" onclick=\"return rerunMigration(\'" + version + "\')\" style=\"font-size:var(--font-size-xs);color:#666;\">opnieuw</a>";
            }
            resultDiv.setAttribute("type", "error");
            var errorMsg = result.error || "Onbekende fout, controleer de uitvoeringslog voor details";
            resultDiv.innerHTML = "<strong>Migratie " + version + " mislukt</strong><br>" + errorMsg;
        }

        // Show log
        if (result.log && result.log.length > 0) {
            var logDiv = document.getElementById("migrationLog");
            if (!logDiv) {
                logDiv = document.createElement("div");
                logDiv.id = "migrationLog";
                resultDiv.parentNode.insertBefore(logDiv, resultDiv.nextSibling);
            }
            logDiv.innerHTML = "<h3>Uitvoeringslog</h3><div class=\"log-output\" style=\"background:#1e1e1e;color:#d4d4d4;padding:15px;font-family:monospace;font-size:var(--font-size-sm);border-radius:4px;overflow-y:auto;white-space:pre-wrap;\">" +
                result.log.map(function(line) {
                    line = line.replace(/</g, "&lt;").replace(/>/g, "&gt;");
                    if (line.indexOf("✓") !== -1) {
                        return "<span style=\"color:#4ec9b0\">" + line + "</span>";
                    } else if (line.indexOf("✗") !== -1 || line.indexOf("MISLUKT") !== -1) {
                        return "<span style=\"color:#f14c4c\">" + line + "</span>";
                    } else if (line.indexOf("⊘") !== -1 || line.indexOf("Waarschuwing") !== -1 || line.indexOf("⚠") !== -1) {
                        return "<span style=\"color:#cca700\">" + line + "</span>";
                    } else if (line.indexOf("━") !== -1 || line.indexOf("═") !== -1) {
                        return "<span style=\"color:#569cd6\">" + line + "</span>";
                    } else if (line.indexOf("Versie ") === 0) {
                        return "<span style=\"color:#9cdcfe\">" + line + "</span>";
                    }
                    return line;
                }).join("\n") + "</div>";
        }

    } catch (e) {
        cmaLog.error("Rerun migration error:", e);
        if (statusCell) {
            statusCell.innerHTML = originalContent;
        }
        alert("Fout bij uitvoeren migratie: " + e.message);
    }

    return false;
}
</script>
<style>
.migration-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid #ccc;
    border-top-color: #007bff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
.badge-info {
    background: #17a2b8;
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: var(--font-size-xs);
}
.badge-error {
    background: #dc3545;
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: var(--font-size-xs);
}
</style>';

// Hidden form for rerun functionality
echo '<form method="post" action="tools_migrations.php" id="rerunForm" style="display:none;">';
echo '<input type="hidden" name="action" value="rerun">';
echo '<input type="hidden" name="target_version" id="rerunVersion" value="">';
echo '</form>';

// Separate pending and completed migrations
$pendingMigrationsList = [];
$completedMigrationsList = [];
foreach ($allMigrations as $migration) {
    if (in_array($migration['version'], $pendingVersions)) {
        $pendingMigrationsList[] = $migration;
    } else {
        $completedMigrationsList[] = $migration;
    }
}

$pendingCount = count($pendingMigrationsList);

// Tabs for pending/completed
echo '<cma-tabs id="migrationTabs" selected="0">';
echo '<tab-item title="Openstaand"></tab-item>';
echo '<tab-item title="Voltooid"></tab-item>';
echo '</cma-tabs>';

// Set count badges via JavaScript
echo '<script>
(function() {
    var tabs = document.getElementById("migrationTabs");
    if (tabs && typeof tabs.setCount === "function") {
        tabs.setCount(0, ' . $pendingCount . ');
        tabs.setCount(1, ' . $completedCount . ');
    } else {
        // Wait for custom element to be defined
        customElements.whenDefined("cma-tabs").then(function() {
            tabs.setCount(0, ' . $pendingCount . ');
            tabs.setCount(1, ' . $completedCount . ');
        });
    }
})();
</script>';

echo '<div class="migration-tab-content">';

// Tab 1: Pending migrations
echo '<div class="migration-tab-panel" id="tabPending">';
if (empty($pendingMigrationsList)) {
    echo '<p style="color:var(--text-muted);padding:15px 0;">Geen openstaande migraties.</p>';
} else {
    echo '<table class="lib_table">';
    echo '<thead><tr>';
    echo '<th style="width:40px">Doel</th>';
    echo '<th style="width:80px">Versie</th><th>Beschrijving</th><th style="width:100px">Status</th></tr></thead>';
    echo '<tbody id="pendingMigrations">';
    foreach ($pendingMigrationsList as $migration) {
        echo '<tr data-version="' . htmlspecialchars($migration['version']) . '" style="background:#fff8e6;">';
        echo '<td style="text-align:center;">';
        $isLast = ($migration['version'] === $lastPendingVersion);
        $checked = $isLast ? ' checked' : '';
        echo '<input type="radio" name="target_version" value="' . htmlspecialchars($migration['version']) . '" form="migrationForm"' . $checked . ' onchange="updateMigrationButton()">';
        echo '</td>';
        echo '<td><strong>' . htmlspecialchars($migration['version']) . '</strong></td>';
        echo '<td>' . htmlspecialchars($migration['description']) . '</td>';
        echo '<td><span class="badge badge-warning">Openstaand</span></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}
echo '</div>';

// Tab 2: Completed migrations
echo '<div class="migration-tab-panel" id="tabCompleted" style="display:none;">';
if (empty($completedMigrationsList)) {
    echo '<p style="color:var(--text-muted);padding:15px 0;">Geen voltooide migraties.</p>';
} else {
    echo '<table class="lib_table">';
    echo '<thead><tr>';
    echo '<th style="width:80px">Versie</th><th>Beschrijving</th><th style="width:100px">Status</th></tr></thead>';
    echo '<tbody id="completedMigrations">';
    foreach ($completedMigrationsList as $migration) {
        echo '<tr data-version="' . htmlspecialchars($migration['version']) . '">';
        echo '<td><strong>' . htmlspecialchars($migration['version']) . '</strong></td>';
        echo '<td>' . htmlspecialchars($migration['description']) . '</td>';
        echo '<td>';
        echo '<span class="badge badge-success">Toegepast</span>';
        echo '<br><a href="#" onclick="return rerunMigration(\'' . htmlspecialchars($migration['version']) . '\')" style="font-size:var(--font-size-xs);color:#666;">opnieuw</a>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}
echo '</div>';

echo '</div>';

// Tab switching script
echo '<script>
document.getElementById("migrationTabs").addEventListener("tab-select", function(e) {
    var panels = document.querySelectorAll(".migration-tab-panel");
    panels.forEach(function(panel, index) {
        panel.style.display = (index === e.detail.index) ? "" : "none";
    });
});
</script>';

if ($hasPending) {
    echo '<div style="margin-top:20px;">';
    echo '<form method="post" action="tools_migrations.php" id="migrationForm" onsubmit="return submitMigration(event);">';
    echo '<input type="hidden" name="action" value="apply_all">';
    // Only show backup checkbox if no migration was just executed
    if ($result === null) {
        echo '<div style="margin-bottom:15px;">';
        echo '<label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;">';
        echo '<input type="checkbox" name="auto_backup" id="autoBackupCheckbox" value="1" checked>';
        echo '<span>Maak een backup voor de migratie(s)</span>';
        echo '</label>';
        echo '</div>';
    }
    echo '<button type="submit" class="btn btn-primary" id="applyButton">';
    echo 'Alle migraties toepassen';
    echo '</button>';
    echo '<span id="migrationCount" style="margin-left:15px;color:#666;"></span>';
    echo '</form>';
    echo '</div>';
    // Initialize the count display
    echo '<script>updateMigrationButton();</script>';
}

echo '</div></body></html>';
