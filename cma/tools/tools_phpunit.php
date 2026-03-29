<?php
/**
 * PHP Unit Test Runner Tool
 *
 * Developer-only tool for running PHP unit tests from the CMA interface.
 * Tests are in the /tests directory and run isolated (no Cypress/browser required).
 * Uses subprocess execution to avoid function redeclaration crashes.
 */

use App\Library\Request;
use App\Library\Response;
use Cma\SecurityHelper;
use Cma\ToolbarHelper;

require_once __DIR__ . '/../bootstrap.inc';

// Developer access required
if (!SecurityHelper::isDeveloper()) {
    http_response_code(403);
    echo '<lib-message type="error">Alleen toegankelijk voor developers</lib-message>';
    exit;
}

Response::noCache();

$cmaRoot = dirname(__DIR__);
$testsDir = $cmaRoot . '/tests';

// Find available test files
$testFiles = [];
if (is_dir($testsDir)) {
    foreach (glob($testsDir . '/*Test.php') as $file) {
        $name = basename($file, '.php');
        $testFiles[] = [
            'name' => $name,
            'file' => basename($file),
            'path' => $file,
            'mtime' => filemtime($file)
        ];
    }
}

/**
 * Run a single test file in a subprocess
 * Returns associative array with name, passed, failed, success, output
 */
function runTestSubprocess(string $testPath, string $testName): array
{
    $phpBinary = escapeshellarg(PHP_BINARY);

    // Detect if test uses TestRunner (extends TestCase) or is self-contained
    $content = file_get_contents($testPath);
    $usesTestRunner = str_contains($content, 'extends TestCase');

    if ($usesTestRunner) {
        // Run via TestRunner.php with the test name
        $testsDir = dirname($testPath);
        $runnerPath = escapeshellarg($testsDir . '/TestRunner.php');
        $testBaseName = escapeshellarg(basename($testPath, '.php'));
        $cmd = "$phpBinary $runnerPath $testBaseName 2>&1";
    } else {
        // Self-contained test - run directly
        $cmd = "$phpBinary " . escapeshellarg($testPath) . " 2>&1";
    }

    // Run in subprocess to avoid function redeclaration
    $output = '';
    $exitCode = 0;
    exec($cmd, $outputLines, $exitCode);
    $output = implode("\n", $outputLines);

    // Strip PHP startup warnings (e.g. "Module PDO_ODBC is already loaded")
    $output = preg_replace('/^(PHP )?Warning:\s+Module .* is already loaded.*\n?/m', '', $output);
    $output = preg_replace('/^X-Powered-By:.*\n?/m', '', $output);
    $output = preg_replace('/^Content-type:.*\n?/m', '', $output);
    // Strip ANSI color codes for parsing (keep original for display)
    $cleanOutput = preg_replace('/\033\[[0-9;]*m/', '', $output);
    $output = trim($output);
    $cleanOutput = trim($cleanOutput);

    // Parse results from clean output (without ANSI codes)
    $passed = 0;
    $failed = 0;
    $errors = 0;

    // TestRunner format: "Passed: X" / "Failed: X" / "Errors: X"
    if (preg_match('/Passed:\s*(\d+)/', $cleanOutput, $m)) {
        $passed = (int)$m[1];
    }
    if (preg_match('/Failed:\s*(\d+)/', $cleanOutput, $m)) {
        $failed = (int)$m[1];
    }
    if (preg_match('/Errors:\s*(\d+)/', $cleanOutput, $m)) {
        $errors = (int)$m[1];
        $failed += $errors;
    }

    // Old self-contained format: "Tests passed: X/Y" or "X tests passed, Y failed"
    if ($passed === 0 && $failed === 0) {
        if (preg_match('/(\d+)\s+tests?\s+passed/i', $cleanOutput, $m)) {
            $passed = (int)$m[1];
        }
        if (preg_match('/(\d+)\s+tests?\s+failed/i', $cleanOutput, $m)) {
            $failed = (int)$m[1];
        }
        // Format: "Tests passed: 15/15"
        if (preg_match('/passed:\s*(\d+)\s*\/\s*(\d+)/i', $cleanOutput, $m)) {
            $passed = (int)$m[1];
            $failed = (int)$m[2] - $passed;
        }
    }

    // Extract individual test method results for detail view
    $testDetails = [];
    // TestRunner format: "  ✓ testMethodName" or "  ✗ testMethodName"
    preg_match_all('/^\s*[✓✗]\s+(\S+)/m', $cleanOutput, $detailMatches, PREG_SET_ORDER);
    foreach ($detailMatches as $dm) {
        $isPass = strpos($dm[0], '✓') !== false;
        $testDetails[] = ['method' => $dm[1], 'pass' => $isPass];
    }

    return [
        'name' => $testName,
        'passed' => $passed,
        'failed' => $failed,
        'success' => $failed === 0 && $exitCode === 0,
        'output' => $cleanOutput,
        'exitCode' => $exitCode,
        'details' => $testDetails
    ];
}

// Handle AJAX run single test
if (Request::query('action', '') === 'run') {
    header('Content-Type: application/json');

    $testFile = Request::query('test', '');
    $testPath = $testsDir . '/' . basename($testFile) . '.php';

    if (!file_exists($testPath)) {
        echo json_encode(['success' => false, 'error' => 'Test bestand niet gevonden: ' . $testFile]);
        exit;
    }

    $result = runTestSubprocess($testPath, $testFile);

    echo json_encode([
        'success' => $result['success'],
        'passed' => $result['passed'],
        'failed' => $result['failed'],
        'output' => $result['output'],
        'exitCode' => $result['exitCode'],
        'details' => $result['details'] ?? []
    ]);
    exit;
}

// Handle run selected tests (multiple)
if (Request::query('action', '') === 'runSelected') {
    header('Content-Type: application/json');

    $testsParam = Request::query('tests', '');
    $selectedTests = array_filter(array_map('trim', explode(',', $testsParam)));

    if (empty($selectedTests)) {
        echo json_encode(['success' => false, 'error' => 'Geen tests geselecteerd']);
        exit;
    }

    $results = [];
    $totalPassed = 0;
    $totalFailed = 0;

    foreach ($selectedTests as $testName) {
        $testPath = $testsDir . '/' . basename($testName) . '.php';
        if (!file_exists($testPath)) {
            $results[] = [
                'name' => $testName,
                'passed' => 0,
                'failed' => 1,
                'success' => false,
                'output' => 'Test bestand niet gevonden: ' . $testName
            ];
            $totalFailed++;
            continue;
        }

        $result = runTestSubprocess($testPath, $testName);
        $totalPassed += $result['passed'];
        $totalFailed += $result['failed'];
        $results[] = $result;
    }

    echo json_encode([
        'success' => $totalFailed === 0,
        'totalPassed' => $totalPassed,
        'totalFailed' => $totalFailed,
        'results' => $results
    ]);
    exit;
}

// Handle run all tests (subprocess per file)
if (Request::query('action', '') === 'runAll') {
    header('Content-Type: application/json');

    $results = [];
    $totalPassed = 0;
    $totalFailed = 0;

    foreach ($testFiles as $test) {
        $result = runTestSubprocess($test['path'], $test['name']);
        $totalPassed += $result['passed'];
        $totalFailed += $result['failed'];
        $results[] = $result;
    }

    echo json_encode([
        'success' => $totalFailed === 0,
        'totalPassed' => $totalPassed,
        'totalFailed' => $totalFailed,
        'results' => $results
    ]);
    exit;
}

// Output HTML
cma_html_header('PHP Unit Tests');
echo '<BODY class="contentbody tools tool-phpunit">';

// Toolbar
$canRun = !empty($testFiles);
ToolbarHelper::start(true);
ToolbarHelper::title('PHP Unit Tests');
ToolbarHelper::separator();
ToolbarHelper::button('javascript:runAllTests()', 'lnr-rocket', $canRun, 'Alle tests', 'Alle tests uitvoeren', 'btnRunAll');
ToolbarHelper::button('javascript:runSelectedTests()', 'lnr-play', $canRun, 'Selectie', 'Geselecteerde tests uitvoeren', 'btnRunSelected');
ToolbarHelper::startRight();
echo '<label class="select-all-label"><input type="checkbox" id="selectAll"> Alles selecteren</label>';
ToolbarHelper::end();

echo '<div id="c" class="tools">';

if (empty($testFiles)) {
    echo '<lib-message type="warning">Geen tests gevonden. Maak test bestanden aan in <code>tests/</code> met de naam <code>*Test.php</code>.</lib-message>';
}

// Results panel
echo '<div id="resultsPanel"></div>';

// Test files list
if (!empty($testFiles)):
?>
<h3>Beschikbare tests <span style="font-weight:normal;color:var(--text-muted);">(<?= count($testFiles) ?> bestanden)</span></h3>

<table class="tests-table">
    <thead>
        <tr>
            <th style="width:5%;"></th>
            <th style="width:30%;">Test</th>
            <th style="width:25%;">Bestand</th>
            <th style="width:15%;">Laatst gewijzigd</th>
            <th style="width:25%;">Resultaat</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($testFiles as $test): ?>
        <tr data-test="<?= htmlspecialchars($test['name']) ?>">
            <td><input type="checkbox" class="test-check" value="<?= htmlspecialchars($test['name']) ?>"></td>
            <td><strong><?= htmlspecialchars($test['name']) ?></strong></td>
            <td><code><?= htmlspecialchars($test['file']) ?></code></td>
            <td><?= date('d-m-Y H:i', $test['mtime']) ?></td>
            <td class="test-result"></td>
        </tr>
        <tr class="test-output-row" data-test-output="<?= htmlspecialchars($test['name']) ?>" style="display:none;">
            <td colspan="5" style="padding:0;"><pre class="test-output"></pre></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<style>
.tests-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}
.tests-table th, .tests-table td {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}
.tests-table th {
    background: var(--bg-header);
    font-weight: 600;
}
.tests-table tr:hover td {
    background: var(--bg-hover);
}
.tests-table code {
    font-size: var(--font-size-sm);
    background: var(--bg-code, #f0f0f0);
    padding: 2px 6px;
    border-radius: 3px;
}

#resultsPanel:not(:empty) {
    margin-bottom: 20px;
}

.select-all-label {
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    color: var(--text-primary);
}

.test-output {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 16px;
    margin: 0;
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: var(--font-size);
    line-height: 1.5;
    white-space: pre-wrap;
    max-height: 400px;
    overflow-y: auto;
}
.test-output .pass { color: #4ec9b0; }
.test-output .fail { color: #f14c4c; }
.test-output .header { color: #569cd6; font-weight: bold; }

/* Row states */
tr.test-passed > td:first-child { border-left: 3px solid var(--color-success); }
tr.test-failed > td:first-child { border-left: 3px solid var(--color-error); }

/* Test detail list inside result cell */
.test-detail-list {
    margin: 4px 0 0 0;
    padding: 0;
    list-style: none;
    font-size: var(--font-size-sm);
    color: var(--text-muted);
}
.test-detail-list li { padding: 1px 0; }
.test-detail-list .lnr { margin-right: 4px; }

.test-output-row td { background: #1e1e1e; }
</style>

<script>
(function() {
    'use strict';

    // Select all checkbox
    var selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            var checks = document.querySelectorAll('.test-check');
            for (var i = 0; i < checks.length; i++) {
                checks[i].checked = selectAll.checked;
            }
        });
    }

    // Update select-all state when individual checkboxes change
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('test-check')) {
            var checks = document.querySelectorAll('.test-check');
            var allChecked = true;
            for (var i = 0; i < checks.length; i++) {
                if (!checks[i].checked) { allChecked = false; break; }
            }
            if (selectAll) selectAll.checked = allChecked;
        }
    });

    function getSelectedTests() {
        var checks = document.querySelectorAll('.test-check:checked');
        var tests = [];
        for (var i = 0; i < checks.length; i++) {
            tests.push(checks[i].value);
        }
        return tests;
    }

    function formatOutput(output) {
        return output
            .replace(/^(=== .* ===)$/gm, '<span class="header">$1</span>')
            .replace(/\./g, function(match, offset, str) {
                var before = str.charAt(offset - 1);
                var after = str.charAt(offset + 1);
                if (before === '.' || after === '.' || after === '\n' || after === 'F') {
                    return '<span class="pass">.</span>';
                }
                return match;
            })
            .replace(/F/g, '<span class="fail">F</span>')
            .replace(/(Passed: \d+)/g, '<span class="pass">$1</span>')
            .replace(/(Failed: \d+)/g, function(match) {
                return match.includes('0') ? '<span class="pass">' + match + '</span>' : '<span class="fail">' + match + '</span>';
            });
    }

    function setRowResult(testName, data) {
        var row = document.querySelector('tr[data-test="' + testName + '"]');
        if (!row) return;
        var cell = row.querySelector('.test-result');
        if (!cell) return;

        var html = '';
        if (data.success) {
            html = '<span class="badge badge-success">' + data.passed + ' geslaagd</span>';
            row.classList.add('test-passed');
            row.classList.remove('test-failed');
        } else {
            html = '<span class="badge badge-error">' + data.failed + ' gefaald</span> ';
            if (data.passed > 0) html += '<span class="badge badge-success">' + data.passed + ' geslaagd</span>';
            row.classList.add('test-failed');
            row.classList.remove('test-passed');
        }

        // Show individual test method results if available
        if (data.details && data.details.length > 0) {
            html += '<ul class="test-detail-list">';
            data.details.forEach(function(d) {
                var icon = d.pass ? '<span class="lnr lnr-checkmark-circle" style="color:var(--color-success)"></span>' : '<span class="lnr lnr-cross-circle" style="color:var(--color-error)"></span>';
                html += '<li>' + icon + d.method + '</li>';
            });
            html += '</ul>';
        }

        cell.innerHTML = html;

        // Fill output row
        var outputRow = document.querySelector('tr[data-test-output="' + testName + '"]');
        if (outputRow && data.output) {
            outputRow.querySelector('.test-output').innerHTML = formatOutput(data.output);
            // Click on result row toggles output
            row.style.cursor = 'pointer';
            row.onclick = function() {
                outputRow.style.display = outputRow.style.display === 'none' ? '' : 'none';
            };
        }
    }

    function setRowLoading(testName) {
        var row = document.querySelector('tr[data-test="' + testName + '"]');
        if (!row) return;
        var cell = row.querySelector('.test-result');
        if (cell) {
            cell.innerHTML = '<span class="lnr lnr-sync" style="animation:spin 1s linear infinite;display:inline-block;"></span>';
        }
        // Hide output row
        var outputRow = document.querySelector('tr[data-test-output="' + testName + '"]');
        if (outputRow) outputRow.style.display = 'none';
    }

    function showSummary(data) {
        var panel = document.getElementById('resultsPanel');
        if (!panel) return;
        var msgType = data.success ? 'success' : 'error';
        var msgText = data.success
            ? 'Alle tests geslaagd (' + data.totalPassed + ')'
            : data.totalFailed + ' gefaald, ' + data.totalPassed + ' geslaagd';
        panel.innerHTML = '<lib-message type="' + msgType + '">' + msgText + '</lib-message>';
    }

    window.runSelectedTests = function() {
        var selected = getSelectedTests();
        if (selected.length === 0) {
            libAlert('Selecteer eerst een of meer tests', { type: 'warning' });
            return;
        }

        var panel = document.getElementById('resultsPanel');
        panel.innerHTML = '';

        // Set loading state on selected rows
        selected.forEach(function(name) { setRowLoading(name); });

        if (selected.length === 1) {
            var testName = selected[0];
            fetch('?action=run&test=' + encodeURIComponent(testName))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    data.totalPassed = data.passed;
                    data.totalFailed = data.failed;
                    setRowResult(testName, data);
                    showSummary(data);
                })
                .catch(function(err) {
                    panel.innerHTML = '<lib-message type="error">Fout: ' + err.message + '</lib-message>';
                });
        } else {
            fetch('?action=runSelected&tests=' + encodeURIComponent(selected.join(',')))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    data.results.forEach(function(result) {
                        setRowResult(result.name, result);
                    });
                    showSummary(data);
                })
                .catch(function(err) {
                    panel.innerHTML = '<lib-message type="error">Fout: ' + err.message + '</lib-message>';
                });
        }
    };

    window.runAllTests = function() {
        var panel = document.getElementById('resultsPanel');
        panel.innerHTML = '';

        // Set loading state on all rows
        document.querySelectorAll('tr[data-test]').forEach(function(row) {
            setRowLoading(row.dataset.test);
        });

        fetch('?action=runAll')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                data.results.forEach(function(result) {
                    setRowResult(result.name, result);
                });
                showSummary(data);
            })
            .catch(function(err) {
                panel.innerHTML = '<lib-message type="error">Fout: ' + err.message + '</lib-message>';
            });
    };
})();
</script>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

<?php
echo '</div></BODY></HTML>';
