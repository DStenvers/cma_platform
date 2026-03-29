<?php
/**
 * CMA Endpoint Tester
 *
 * Lists and tests all CMA endpoints for errors.
 * Tests endpoints via AJAX using current user session.
 *
 * Features:
 * - Dynamically discovers all forms and their fields
 * - Tests combo field retrieval for combobox fields
 * - Tests subform retrieval for forms with subforms
 * - Response time measurement
 * - Error detection (PHP errors, JSON failures)
 */

use App\Library\Request;
use App\Library\Response;
use Cma\SecurityHelper;
use Cma\ToolbarHelper;

require_once __DIR__ . '/../bootstrap.inc';

// Allow LLM mode without auth (only returns URL list)
$llmMode = Request::query('llm', '') === 'Y';

// Require developer access (except for LLM mode)
if (!$llmMode && !SecurityHelper::isDeveloper()) {
    http_response_code(403);
    echo '<lib-message type="error">Toegang geweigerd - alleen developers</lib-message>';
    exit;
}

Response::noCache();

/**
 * Get a real record ID for a form by querying its first record directly from database
 */
function getRealRecordId(string $formName): ?string {
    static $cache = [];
    if (isset($cache[$formName])) {
        return $cache[$formName];
    }

    // Load form definition to get table and primaryKey
    $formDefFile = __DIR__ . '/../assets/forms/definitions/' . $formName . '.json';
    if (!file_exists($formDefFile)) {
        // Try external forms directory
        $formDefFile = __DIR__ . '/../../assets/forms/' . $formName . '.json';
    }

    if (!file_exists($formDefFile)) {
        $cache[$formName] = null;
        return null;
    }

    $formDef = @json_decode(file_get_contents($formDefFile), true);
    if (!$formDef || empty($formDef['table'])) {
        $cache[$formName] = null;
        return null;
    }

    $table = $formDef['table'];
    $primaryKey = $formDef['primaryKey'] ?? 'ID';
    $database = $formDef['database'] ?? 1;

    try {
        // Get database connection
        $conn = \App\Library\Database::getConnection($database);
        if (!$conn) {
            $cache[$formName] = null;
            return null;
        }

        // Query for first record
        $sql = "SELECT TOP 1 [{$primaryKey}] FROM [{$table}]";
        $rs = \App\Library\Database::openRS($sql, $conn);

        if ($rs && !$rs->EOF) {
            $id = $rs->fields[$primaryKey] ?? null;
            $rs->Close();
            if ($id !== null) {
                $cache[$formName] = (string)$id;
                return $cache[$formName];
            }
        }
    } catch (\Exception $e) {
        // Query failed, return null
    }

    $cache[$formName] = null;
    return null;
}

/**
 * Get all form definitions with their fields and subforms
 */
function getFormDefinitions(): array {
    $forms = [];
    $directories = [
        __DIR__ . '/../assets/forms/definitions',
        dirname(__DIR__, 2) . '/assets/forms'
    ];

    foreach ($directories as $dir) {
        if (!is_dir($dir)) continue;
        $files = glob($dir . '/*.json');
        foreach ($files as $file) {
            $name = basename($file, '.json');
            // Skip schema files, menu files, old_* forms, and duplicates
            if (strpos($name, 'schema') !== false) continue;
            if ($name === '_menu_items') continue;
            if (strpos($name, 'old_') === 0) continue; // Skip deprecated old_* forms
            if (isset($forms[$name])) continue;

            $content = file_get_contents($file);
            $data = json_decode($content, true);
            if (!$data) continue;

            // Extract combobox fields
            $comboFields = [];
            if (!empty($data['fields'])) {
                foreach ($data['fields'] as $field) {
                    if (isset($field['type']) && $field['type'] === 'combobox' && !empty($field['name'])) {
                        $comboFields[] = $field['name'];
                    }
                }
            }

            // Extract subforms
            $subforms = [];
            if (!empty($data['subforms'])) {
                foreach ($data['subforms'] as $subform) {
                    if (!empty($subform['name'])) {
                        $subforms[] = $subform;
                    }
                }
            }

            $forms[$name] = [
                'name' => $name,
                'title' => $data['title'] ?? $name,
                'comboFields' => $comboFields,
                'subforms' => $subforms
            ];
        }
    }

    ksort($forms);
    return $forms;
}

/**
 * Get all tools files
 */
function getToolsFiles(): array {
    $tools = [];
    $files = glob(__DIR__ . '/*.php');
    // Skip files that shouldn't be tested automatically
    $skipFiles = [
        'tools_endpoint_tester',  // This file itself
        'tools_process_test',     // Requires bash/Unix (development only)
        'tools_testrunner',       // Test runner itself (avoid recursion)
        'reload_env',             // Utility script, not a page
        'set_migration_version'   // Admin script, not a page
    ];
    foreach ($files as $file) {
        $name = basename($file, '.php');
        if (in_array($name, $skipFiles)) continue;
        $tools[] = [
            'file' => $name,
            'path' => '/cma/tools/' . basename($file)
        ];
    }
    return $tools;
}

$formDefs = getFormDefinitions();
$toolsFiles = getToolsFiles();

// Build endpoints array
$endpoints = [];

// =====================================================================
// Auto-discover all CMA PHP files
// =====================================================================

// Root CMA pages (auto-discovered)
$skipRootFiles = [
    '404',           // Error page
    'index',         // Redirects
    'minify',        // Asset pipeline (requires ?f=)
    'login',         // Auth flow (redirects)
    'logout',        // Auth flow (destroys session)
    'sso_login',     // Auth flow (external redirect)
    'sso_callback',  // Auth flow (external redirect)
    'template_post', // POST-only handler
    'details_getdata', // Requires formId + recordId
    'form_api',      // Tested separately via Combo/Legacy API sections
    'html_edit_cell',  // CKEditor plugin (requires parameters)
    'html_edit_image', // CKEditor plugin (requires parameters)
    'html_edit_link',  // CKEditor plugin (requires parameters)
    'html_edit_row',   // CKEditor plugin (requires parameters)
    'html_edit_table', // CKEditor plugin (requires parameters)
    'imageupload_action', // POST-only handler
];

$rootFiles = glob(dirname(__DIR__) . '/*.php');
foreach ($rootFiles as $file) {
    $name = basename($file, '.php');
    if (in_array($name, $skipRootFiles)) continue;

    // Categorize by function
    $category = 'Hoofdpaginas';
    if (strpos($name, 'template_') === 0 || $name === 'listTemplates') {
        $category = 'Templates';
    } elseif (strpos($name, 'imageupload') === 0) {
        $category = 'Image Upload';
    } elseif (strpos($name, 'report') === 0) {
        $category = 'Rapporten';
    }

    $displayName = ucfirst(str_replace('_', ' ', $name));
    $url = '/cma/' . basename($file);

    // Some pages require query parameters to load
    switch ($name) {
        case 'form':
            // Test with first available form
            $firstForm = array_key_first($formDefs);
            if ($firstForm) {
                $endpoints[] = ['category' => $category, 'url' => $url . '?form=' . $firstForm, 'name' => $displayName . ' (' . $firstForm . ')', 'type' => 'page'];
            }
            continue 2;
        case 'subform':
            // Needs form + parentId - skip from auto-discovery, tested via Subform API
            continue 2;
        case 'menurep':
            // Frameset page - tested as-is
            break;
        case 'imageupload':
        case 'imageupload_crop':
            // Needs parameters but should still return HTML without error
            break;
    }

    $endpoints[] = ['category' => $category, 'url' => $url, 'name' => $displayName, 'type' => 'page'];
}

// API endpoints (auto-discovered)
$skipApiFiles = [
    'config_post',   // POST-only handler
    'change-password', // POST-only handler
    'report-save',   // POST-only handler
    'icon_add',      // POST-only handler
    'file_edit',     // POST-only handler
    'test_ip_match', // Dev utility
];

$apiFiles = glob(dirname(__DIR__) . '/api/*.php');
foreach ($apiFiles as $file) {
    $name = basename($file, '.php');
    if (in_array($name, $skipApiFiles)) continue;

    $displayName = ucfirst(str_replace(['_', '-'], ' ', $name));
    $url = '/cma/api/' . basename($file);

    // Add meaningful query parameters for APIs that need them
    if ($name === 'config_api') {
        $endpoints[] = ['category' => 'API', 'url' => $url . '?type=menu', 'name' => $displayName . ' - Menu', 'type' => 'api'];
        $endpoints[] = ['category' => 'API', 'url' => $url . '?type=databases', 'name' => $displayName . ' - Databases', 'type' => 'api'];
    } elseif ($name === 'report-query' || $name === 'report-export' || $name === 'report-schema') {
        // These need a report parameter - use first available report
        $endpoints[] = ['category' => 'API - Rapporten', 'url' => $url, 'name' => $displayName, 'type' => 'api'];
    } else {
        $endpoints[] = ['category' => 'API', 'url' => $url, 'name' => $displayName, 'type' => 'api'];
    }
}

// Form List API for each form
foreach ($formDefs as $formName => $formData) {
    $endpoints[] = [
        'category' => 'Form List API',
        'url' => "/cma/api/form_list.php?formName={$formName}",
        'name' => "List: {$formName}",
        'type' => 'api'
    ];
}

// Form Record API for main forms (use real record IDs)
$mainForms = ['users', 'groups', 'contentblocks', 'opleidingen', 'deelnemers', 'docenten', 'rooster'];
foreach ($mainForms as $formName) {
    if (isset($formDefs[$formName])) {
        $realId = getRealRecordId($formName);
        if ($realId !== null) {
            $endpoints[] = [
                'category' => 'Form Record API',
                'url' => "/cma/api/form_record.php?formName={$formName}&id={$realId}",
                'name' => "Record: {$formName} #{$realId}",
                'type' => 'api'
            ];
        }
    }
}

// Combo Field API - extract from forms with combobox fields
foreach ($formDefs as $formName => $formData) {
    if (!empty($formData['comboFields'])) {
        foreach ($formData['comboFields'] as $fieldName) {
            $endpoints[] = [
                'category' => 'Combo API',
                'url' => "/cma/form_api.php?action=combo&form={$formName}&field={$fieldName}",
                'name' => "Combo: {$formName}.{$fieldName}",
                'type' => 'api'
            ];
        }
    }
}

// Subform API - extract from forms with subforms (use real parent IDs)
foreach ($formDefs as $formName => $formData) {
    if (!empty($formData['subforms'])) {
        $parentId = getRealRecordId($formName);
        if ($parentId !== null) {
            foreach ($formData['subforms'] as $subform) {
                $subformName = $subform['name'];
                $endpoints[] = [
                    'category' => 'Subform API',
                    'url' => "/cma/api/form_list.php?formName={$formName}&subform={$subformName}&parentId={$parentId}",
                    'name' => "Subform: {$formName}.{$subformName}",
                    'type' => 'api'
                ];
            }
        }
    }
}

// Legacy Form API (use real record IDs)
$legacyForms = ['users', 'groups'];
foreach ($legacyForms as $formName) {
    $realId = getRealRecordId($formName);
    if ($realId !== null) {
        $endpoints[] = [
            'category' => 'Form API (legacy)',
            'url' => "/cma/form_api.php?formName={$formName}&action=get_form&id={$realId}",
            'name' => 'Form API - ' . ucfirst($formName),
            'type' => 'api'
        ];
    }
}

// Tools pages
foreach ($toolsFiles as $tool) {
    $endpoints[] = [
        'category' => 'Tools',
        'url' => $tool['path'],
        'name' => 'Tools - ' . ucfirst(str_replace(['tools_', '_'], ['', ' '], $tool['file'])),
        'type' => 'page'
    ];
}

// Wizards (auto-discovered)
$skipWizardFiles = [
    'file_controls_delete', // POST-only handler
    'file_outputfile',      // Binary file output (requires path)
];

$wizardFiles = glob(dirname(__DIR__) . '/wizards/*.php');
if ($wizardFiles) {
    foreach ($wizardFiles as $file) {
        $name = basename($file, '.php');
        if (in_array($name, $skipWizardFiles)) continue;

        $displayName = ucfirst(str_replace(['_', '-'], ' ', $name));
        $endpoints[] = [
            'category' => 'Wizards',
            'url' => '/cma/wizards/' . basename($file),
            'name' => $displayName,
            'type' => 'page'
        ];
    }
}

// Migrations - skipped from endpoint testing (they modify data)

// Output format for LLM parsing
if (Request::query('llm', '') === 'Y') {
    header('Content-Type: text/plain');
    foreach ($endpoints as $ep) {
        echo $ep['url'] . "\n";
    }
    exit;
}

// JSON format for AJAX
if (Request::query('format', '') === 'json') {
    header('Content-Type: application/json');
    echo json_encode(['endpoints' => $endpoints, 'total' => count($endpoints)]);
    exit;
}

// Group endpoints by category
$groupedEndpoints = [];
foreach ($endpoints as $ep) {
    $category = $ep['category'] ?? 'Overig';
    if (!isset($groupedEndpoints[$category])) {
        $groupedEndpoints[$category] = [];
    }
    $groupedEndpoints[$category][] = $ep;
}

// Calculate stats
$totalEndpoints = count($endpoints);
$comboCount = count(array_filter($endpoints, fn($e) => $e['category'] === 'Combo API'));
$subformCount = count(array_filter($endpoints, fn($e) => $e['category'] === 'Subform API'));
$formCount = count($formDefs);

// HTML output with proper tools template
cma_html_header('Endpoint Tester');
echo '<BODY class="contentbody tools tool-endpoint-tester">';
ToolbarHelper::start(true);
ToolbarHelper::title('Endpoint Tester');
ToolbarHelper::separator();
ToolbarHelper::button('javascript:testAllEndpoints()', 'lnr-rocket', true, 'Alle testen', 'Test alle endpoints op fouten', 'testAllBtn');
ToolbarHelper::button('javascript:testVisibleEndpoints()', 'lnr-checkmark-circle', true, 'Zichtbare', 'Test alleen zichtbare endpoints', 'testVisibleBtn');
echo '<span class="tb-btn" id="stopBtn" title="Stop testen" style="display:none"><a href="javascript:stopTesting()"><span class="lnr lnr-cross"></span><span class="tb-btn-text">Stop</span></a></span>' . PHP_EOL;
ToolbarHelper::button('javascript:resetResults()', 'lnr-undo', true, 'Reset', 'Alle resultaten wissen', 'resetBtn');
ToolbarHelper::startRight();
echo '<lib-search-input id="filterInput" placeholder="Filter endpoints..." style="width:200px"></lib-search-input> ';
echo '<select id="statusFilter" onchange="filterEndpoints()" class="toolbar-select">';
echo '<option value="">Alle statussen</option><option value="pending">Niet getest</option><option value="success">Geslaagd</option><option value="error">Fout</option>';
echo '</select> ';
echo '<select id="categoryFilter" onchange="filterEndpoints()" class="toolbar-select">';
echo '<option value="">Alle categorieen</option>';
foreach (array_keys($groupedEndpoints) as $cat) {
    echo '<option value="' . htmlspecialchars($cat) . '">' . htmlspecialchars($cat) . '</option>';
}
echo '</select> ';
echo '<label class="toolbar-checkbox"><input type="checkbox" id="errorsOnly" onchange="filterEndpoints()"> Alleen fouten</label> ';
ToolbarHelper::separator();
ToolbarHelper::button('?llm=Y', 'lnr-laptop-phone', true, 'LLM', 'LLM mode (alleen URLs)');
ToolbarHelper::button('?format=json', 'lnr-code', true, 'JSON', 'JSON format');
ToolbarHelper::end(false);
echo '<div id="c" class="tools">';
?>

<div class="endpoint-tester">
    <div class="test-gauges" id="testGauges" style="display:none;">
        <lib-gauge id="gaugeProgress" value="0" max="<?= $totalEndpoints ?>" size="lg" type="info" format="raw" label="0 / <?= $totalEndpoints ?> endpoints"></lib-gauge>
        <div class="gauge-row">
            <lib-gauge id="gaugeSuccess" value="0" max="<?= $totalEndpoints ?>" size="sm" type="success" format="raw" label="0 geslaagd"></lib-gauge>
            <lib-gauge id="gaugeError" value="0" max="<?= $totalEndpoints ?>" size="sm" type="error" format="raw" label="0 fouten"></lib-gauge>
            <lib-gauge id="gaugePending" value="<?= $totalEndpoints ?>" max="<?= $totalEndpoints ?>" size="sm" type="warning" format="raw" label="<?= $totalEndpoints ?> niet getest"></lib-gauge>
        </div>
    </div>

    <?php foreach ($groupedEndpoints as $category => $categoryEndpoints): ?>
    <div class="category" data-category="<?= htmlspecialchars($category) ?>">
        <cma-groupbox caption="<?= htmlspecialchars($category) ?>" count="<?= count($categoryEndpoints) ?>" storage-key="ep_<?= preg_replace('/[^a-z0-9]/i', '_', $category) ?>"></cma-groupbox>
        <div class="category-content">
            <table class="listtable">
                <thead>
                    <tr class="listheader">
                        <th style="width:25%;">Naam</th>
                        <th style="width:30%;">URL</th>
                        <th style="width:80px;">Status</th>
                        <th style="width:70px;text-align:right;">Tijd</th>
                        <th style="width:20%;">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categoryEndpoints as $ep): ?>
                    <tr class="endpoint-row"
                        data-url="<?= htmlspecialchars($ep['url']) ?>"
                        data-type="<?= htmlspecialchars($ep['type'] ?? 'page') ?>"
                        data-status="pending">
                        <td>
                            <?= htmlspecialchars($ep['name']) ?>
                            <span class="type-badge type-<?= htmlspecialchars($ep['type'] ?? 'page') ?>"><?= htmlspecialchars($ep['type'] ?? 'page') ?></span>
                        </td>
                        <td><a href="<?= htmlspecialchars($ep['url']) ?>" target="_blank"><?= htmlspecialchars($ep['url']) ?></a></td>
                        <td class="status"><span class="status-badge status-pending">Niet getest</span></td>
                        <td class="response-time">-</td>
                        <td class="details">-</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<style>
.endpoint-tester {
    padding: 0;
}

.toolbar-select {
    padding: 3px 8px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    height: 28px;
}

.toolbar-checkbox {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: var(--font-size-sm);
    cursor: pointer;
    white-space: nowrap;
}

.test-gauges {
    margin-top: 12px;
}

.test-gauges lib-gauge {
    margin-bottom: 6px;
}

.test-gauges > lib-gauge {
    width: 33.33%;
}

.gauge-row {
    display: flex;
    gap: 15px;
    margin-top: 8px;
}

.gauge-row lib-gauge {
    flex: 1;
    min-width: 120px;
}

.category {
    margin-bottom: 15px;
}

.category .category-content {
    border: 1px solid var(--border-color, #e0e0e0);
    border-top: 0;
    border-radius: 0 0 6px 6px;
}

.category table {
    margin: 0;
    table-layout: fixed;
}

.status-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: var(--font-size-xs);
    font-weight: 500;
}

.status-pending { background: #e9ecef; color: #666; }
.status-testing { background: #fff3cd; color: #856404; }
.status-success { background: #d4edda; color: #155724; }
.status-error { background: #f8d7da; color: #721c24; }
.status-redirect { background: #cce5ff; color: #004085; }

.response-time {
    text-align: right;
    font-size: var(--font-size-sm);
    color: var(--text-muted);
    font-family: monospace;
}

.response-time.slow {
    color: #dc3545;
    font-weight: 600;
}

.details {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.details.error {
    color: #dc3545;
}

.type-badge {
    font-size: 9px;
    padding: 2px 5px;
    border-radius: 3px;
    margin-left: 6px;
    text-transform: uppercase;
    vertical-align: middle;
}

.type-api { background: #e7f3ff; color: #0366d6; }
.type-page { background: #f0fff0; color: #22863a; }
</style>

<script>
(function() {
    'use strict';

    var testing = false;
    var stats = { success: 0, error: 0, pending: <?= $totalEndpoints ?> };

    function updateStats() {
        var total = stats.success + stats.error + stats.pending;
        var tested = stats.success + stats.error;

        var gProgress = document.getElementById('gaugeProgress');
        if (gProgress) {
            gProgress.setAttribute('value', tested);
            gProgress.setAttribute('max', total);
            gProgress.setAttribute('label', tested + ' / ' + total + ' endpoints');
        }

        var gSuccess = document.getElementById('gaugeSuccess');
        if (gSuccess) {
            gSuccess.setAttribute('value', stats.success);
            gSuccess.setAttribute('max', total);
            gSuccess.setAttribute('label', stats.success + ' geslaagd');
        }

        var gError = document.getElementById('gaugeError');
        if (gError) {
            gError.setAttribute('value', stats.error);
            gError.setAttribute('max', total);
            gError.setAttribute('label', stats.error + ' fouten');
        }

        var gPending = document.getElementById('gaugePending');
        if (gPending) {
            gPending.setAttribute('value', stats.pending);
            gPending.setAttribute('max', total);
            gPending.setAttribute('label', stats.pending + ' niet getest');
        }

        document.getElementById('testGauges').style.display = '';
    }

    function updateProgress(current, total) {
        var gProgress = document.getElementById('gaugeProgress');
        if (gProgress) {
            gProgress.setAttribute('value', current);
            gProgress.setAttribute('max', total);
            gProgress.setAttribute('label', current + ' / ' + total + ' endpoints');
        }
    }

    async function testEndpoint(row) {
        var url = row.dataset.url;
        var statusCell = row.querySelector('.status');
        var timeCell = row.querySelector('.response-time');
        var detailsCell = row.querySelector('.details');

        statusCell.innerHTML = '<span class="status-badge status-testing">Testen...</span>';
        row.dataset.status = 'testing';

        var startTime = performance.now();

        try {
            var response = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            var endTime = performance.now();
            var duration = Math.round(endTime - startTime);

            timeCell.textContent = duration + ' ms';
            if (duration > 2000) {
                timeCell.classList.add('slow');
            } else {
                timeCell.classList.remove('slow');
            }

            // Check response
            var contentType = response.headers.get('content-type') || '';
            var details = '';
            var isError = false;

            if (response.status >= 200 && response.status < 300) {
                // Check for JSON API responses
                if (contentType.includes('application/json')) {
                    try {
                        var json = await response.json();
                        if (json.success === false) {
                            isError = true;
                            details = json.error || json.message || 'API returned success:false';
                        } else if (json.error) {
                            isError = true;
                            details = json.error;
                        } else {
                            details = 'JSON OK';
                            if (json.total !== undefined) details += ' (' + json.total + ' items)';
                            if (json.count !== undefined) details += ' (' + json.count + ' items)';
                            if (json.options !== undefined) details += ' (' + json.options.length + ' opties)';
                        }
                    } catch (e) {
                        isError = true;
                        details = 'Invalid JSON response';
                    }
                } else if (contentType.includes('text/html')) {
                    var text = await response.text();
                    // Check for PHP errors
                    if (text.includes('Fatal error') || text.includes('Parse error')) {
                        isError = true;
                        var match = text.match(/(Fatal error|Parse error)[^<]*/);
                        details = match ? match[0].substring(0, 80) : 'PHP Error';
                    } else if (text.includes('Warning:') && text.includes('.php')) {
                        isError = true;
                        var warnMatch = text.match(/Warning:[^<]*/);
                        details = warnMatch ? warnMatch[0].substring(0, 80) : 'PHP Warning';
                    } else if (text.includes('forcelogin=J')) {
                        details = 'Redirect to login';
                        statusCell.innerHTML = '<span class="status-badge status-redirect">Redirect</span>';
                        row.dataset.status = 'redirect';
                        stats.pending--;
                        updateStats();
                        return;
                    } else {
                        details = 'HTML OK (' + Math.round(text.length / 1024) + ' KB)';
                    }
                } else {
                    details = 'Response: ' + response.status + ' ' + contentType;
                }
            } else if (response.status === 302 || response.status === 301) {
                details = 'Redirect';
                statusCell.innerHTML = '<span class="status-badge status-redirect">Redirect</span>';
                row.dataset.status = 'redirect';
                stats.pending--;
                updateStats();
                return;
            } else {
                isError = true;
                details = 'HTTP ' + response.status + ' ' + response.statusText;
            }

            if (isError) {
                statusCell.innerHTML = '<span class="status-badge status-error">Fout</span>';
                detailsCell.className = 'details error';
                row.dataset.status = 'error';
                stats.error++;
            } else {
                statusCell.innerHTML = '<span class="status-badge status-success">OK</span>';
                row.dataset.status = 'success';
                stats.success++;
            }
            detailsCell.textContent = details;
            detailsCell.title = details;

        } catch (e) {
            var endTime2 = performance.now();
            timeCell.textContent = Math.round(endTime2 - startTime) + ' ms';
            statusCell.innerHTML = '<span class="status-badge status-error">Fout</span>';
            detailsCell.textContent = e.message;
            detailsCell.className = 'details error';
            row.dataset.status = 'error';
            stats.error++;
        }

        stats.pending--;
        updateStats();
    }

    window.testAllEndpoints = async function() {
        if (testing) return;

        testing = true;
        document.getElementById('testAllBtn').disabled = true;
        document.getElementById('testVisibleBtn').disabled = true;
        document.getElementById('stopBtn').style.display = 'inline-block';
        document.getElementById('testGauges').style.display = '';

        var rows = document.querySelectorAll('.endpoint-row');
        var total = rows.length;
        var current = 0;

        // Reset stats
        stats = { success: 0, error: 0, pending: total };
        updateStats();

        for (var i = 0; i < rows.length; i++) {
            if (!testing) break;

            await testEndpoint(rows[i]);
            current++;
            updateProgress(current, total);

            // Small delay to prevent overwhelming the server
            await new Promise(function(r) { setTimeout(r, 30); });
        }

        testing = false;
        document.getElementById('testAllBtn').disabled = false;
        document.getElementById('testVisibleBtn').disabled = false;
        document.getElementById('stopBtn').style.display = 'none';
    };

    window.testVisibleEndpoints = async function() {
        if (testing) return;

        testing = true;
        document.getElementById('testAllBtn').disabled = true;
        document.getElementById('testVisibleBtn').disabled = true;
        document.getElementById('stopBtn').style.display = 'inline-block';
        document.getElementById('testGauges').style.display = '';

        var rows = document.querySelectorAll('.endpoint-row');
        var visibleRows = [];
        for (var i = 0; i < rows.length; i++) {
            if (rows[i].style.display !== 'none') {
                visibleRows.push(rows[i]);
            }
        }

        var total = visibleRows.length;
        var current = 0;

        for (var j = 0; j < visibleRows.length; j++) {
            if (!testing) break;

            await testEndpoint(visibleRows[j]);
            current++;
            updateProgress(current, total);

            await new Promise(function(r) { setTimeout(r, 30); });
        }

        testing = false;
        document.getElementById('testAllBtn').disabled = false;
        document.getElementById('testVisibleBtn').disabled = false;
        document.getElementById('stopBtn').style.display = 'none';
    };

    window.stopTesting = function() {
        testing = false;
    };

    window.resetResults = function() {
        var rows = document.querySelectorAll('.endpoint-row');
        for (var i = 0; i < rows.length; i++) {
            rows[i].dataset.status = 'pending';
            rows[i].querySelector('.status').innerHTML = '<span class="status-badge status-pending">Niet getest</span>';
            rows[i].querySelector('.response-time').textContent = '-';
            rows[i].querySelector('.response-time').classList.remove('slow');
            rows[i].querySelector('.details').textContent = '-';
            rows[i].querySelector('.details').className = 'details';
        }

        stats = { success: 0, error: 0, pending: rows.length };
        updateStats();
        document.getElementById('testGauges').style.display = 'none';
    };

    // Hook up lib-search-input events
    var searchInput = document.getElementById('filterInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() { filterEndpoints(); });
        searchInput.addEventListener('clear', function() { filterEndpoints(); });
    }

    window.filterEndpoints = function() {
        var filterEl = document.getElementById('filterInput');
        var filter = (filterEl ? filterEl.value : '').toLowerCase();
        var statusFilter = document.getElementById('statusFilter').value;
        var categoryFilter = document.getElementById('categoryFilter').value;
        var errorsOnly = document.getElementById('errorsOnly').checked;

        var categories = document.querySelectorAll('.category');
        categories.forEach(function(cat) {
            var catName = cat.dataset.category;
            var rows = cat.querySelectorAll('.endpoint-row');
            var visibleCount = 0;

            // Check category filter first
            if (categoryFilter && catName !== categoryFilter) {
                cat.style.display = 'none';
                return;
            }

            rows.forEach(function(row) {
                var url = row.dataset.url.toLowerCase();
                var name = row.querySelector('td').textContent.toLowerCase();
                var status = row.dataset.status;

                var show = true;

                if (filter && !url.includes(filter) && !name.includes(filter)) {
                    show = false;
                }

                if (statusFilter && status !== statusFilter) {
                    show = false;
                }

                if (errorsOnly && status !== 'error') {
                    show = false;
                }

                row.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });

            // Hide category if no visible rows
            cat.style.display = visibleCount > 0 ? '' : 'none';
        });
    };
})();
</script>

<?php
echo '</div></BODY></HTML>';
