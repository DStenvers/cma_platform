<?php
/**
 * Cypress Test Runner Tool
 *
 * Developer-only tool for running Cypress E2E tests from the CMA interface.
 * Shows available test specs, allows running all or selected tests,
 * and displays results in a formatted table.
 */

use App\Library\Arr;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
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
$cypressDir = $cmaRoot;
$resultsFile = $cmaRoot . '/cypress/reports/results.json';
$specDir = $cmaRoot . '/cypress/e2e';

// Check if Cypress is installed
$cypressInstalled = file_exists($cmaRoot . '/node_modules/.bin/cypress')
    || file_exists($cmaRoot . '/node_modules/.bin/cypress.cmd'); // Windows

// Recursively find all test specs in subdirectories
function findTestSpecs(string $baseDir, string $currentDir = ''): array {
    $specs = [];
    $searchDir = $currentDir ? $baseDir . '/' . $currentDir : $baseDir;

    if (!is_dir($searchDir)) {
        return $specs;
    }

    // Find .cy.js files in current directory
    foreach (glob($searchDir . '/*.cy.js') as $file) {
        $name = basename($file, '.cy.js');
        // relativePath WITHOUT extension (e.g., "navigation/breadcrumb")
        $relativePath = $currentDir ? $currentDir . '/' . $name : $name;
        $category = $currentDir ?: 'root';
        $specs[] = [
            'name' => $name,
            'file' => basename($file),
            'path' => 'cypress/e2e/' . $relativePath . '.cy.js',
            'relativePath' => $relativePath,
            'category' => $category,
            'mtime' => filemtime($file)
        ];
    }

    // Recursively scan subdirectories
    foreach (glob($searchDir . '/*', GLOB_ONLYDIR) as $subDir) {
        $subDirName = basename($subDir);
        $subPath = $currentDir ? $currentDir . '/' . $subDirName : $subDirName;
        $specs = array_merge($specs, findTestSpecs($baseDir, $subPath));
    }

    return $specs;
}

// Get available specs (recursively)
$specs = findTestSpecs($specDir);
$testsAvailable = !empty($specs);
$testCount = count($specs);

// Group specs by category
$specsByCategory = [];
foreach ($specs as $spec) {
    $category = $spec['category'];
    if (!isset($specsByCategory[$category])) {
        $specsByCategory[$category] = [];
    }
    $specsByCategory[$category][] = $spec;
}
// Sort categories
ksort($specsByCategory);
// Sort specs within each category
foreach ($specsByCategory as &$categorySpecs) {
    usort($categorySpecs, fn($a, $b) => strcasecmp($a['name'], $b['name']));
}
unset($categorySpecs);

// Get last results if available
$lastResults = null;
if (file_exists($resultsFile)) {
    $lastResults = json_decode(file_get_contents($resultsFile), true);
}

// Simple timeout test - sleeps for N seconds (default 10)
// Use: ?action=timeout_test&seconds=10
if (Request::query('action', '') === 'timeout_test') {
    set_time_limit(600);
    ignore_user_abort(true);

    header('Content-Type: application/json');

    $seconds = min(60, max(1, Request::queryInt('seconds', 10)));
    $startTime = microtime(true);

    error_log("[TestRunner] timeout_test starting for {$seconds} seconds");

    // Sleep in small increments to keep PHP active
    for ($i = 0; $i < $seconds; $i++) {
        sleep(1);
        error_log("[TestRunner] timeout_test: {$i} seconds elapsed");
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    error_log("[TestRunner] timeout_test completed after {$elapsed}s");

    echo json_encode([
        'success' => true,
        'requested' => $seconds,
        'actual' => $elapsed,
        'message' => "Slept for {$elapsed} seconds without timeout"
    ]);
    exit;
}

// Handle AJAX request to generate command (not execute)
if (Request::query('action', '') === 'command') {
    header('Content-Type: application/json');

    $specsParam = Request::query('specs', '');
    $selectedSpecs = $specsParam !== '' ? explode(',', $specsParam) : [];

    // Build the command
    $nodeExe = '"C:\\Program Files\\nodejs\\node.exe"';
    $cypressScript = '"' . $cmaRoot . '\\node_modules\\cypress\\bin\\cypress"';
    $cypressArgs = 'run --headless --browser electron';

    // Add spec filter if specific tests selected
    if (!empty($selectedSpecs)) {
        $specFiles = [];
        foreach ($selectedSpecs as $specPath) {
            $fullPath = $specDir . '/' . $specPath . '.cy.js';
            if (file_exists($fullPath)) {
                $specFiles[] = 'cypress/e2e/' . $specPath . '.cy.js';
            }
        }
        if (!empty($specFiles)) {
            $cypressArgs .= ' --spec "' . implode(',', $specFiles) . '"';
        }
    }

    $command = $nodeExe . ' ' . $cypressScript . ' ' . $cypressArgs;
    $workingDir = $cmaRoot;

    echo json_encode([
        'command' => $command,
        'workingDir' => $workingDir,
        'instructions' => "Open een terminal, ga naar:\ncd \"$workingDir\"\n\nEn voer uit:\n$command"
    ]);
    exit;
}

// Handle AJAX run request (kept for future use if IIS identity is configured)
if (Request::query('action', '') === 'run') {
    // Track timing from the very start
    $actionStartTime = microtime(true);

    // Checkpoint function for debugging
    $checkpoints = [];
    $checkpointLog = function($name) use (&$checkpoints, $actionStartTime) {
        $elapsed = round((microtime(true) - $actionStartTime) * 1000);
        $checkpoints[] = "$name: {$elapsed}ms";
        error_log("[TestRunner] CHECKPOINT [$elapsed ms] $name");
    };

    $checkpointLog('START');

    // Cypress tests can take a long time - set generous limits
    set_time_limit(600); // 10 minutes
    ini_set('max_execution_time', 600);
    ignore_user_abort(true); // Don't abort if browser disconnects

    $checkpointLog('TIMEOUTS_SET');

    // Disable output buffering for real-time output (helps avoid timeouts)
    while (ob_get_level()) {
        ob_end_flush();
    }

    $checkpointLog('BUFFERS_FLUSHED');

    header('Content-Type: application/json');
    header('X-Accel-Buffering: no'); // Disable nginx/proxy buffering
    header('Cache-Control: no-cache');

    // Flush headers immediately
    flush();

    $checkpointLog('HEADERS_SENT');

    $specsParam = Request::query('specs', '');
    $selectedSpecs = $specsParam !== '' ? explode(',', $specsParam) : [];

    $checkpointLog('PARAMS_PARSED');

    // Find Node.js path and cypress executable
    $cypressCmd = null;
    $nodejsPath = null;
    $npxSearchLog = [];

    if (PHP_OS_FAMILY === 'Windows') {
        // Get NODEJS_PATH from .env (required for IIS which doesn't have PATH set)
        $nodejsPathRaw = $_ENV['NODEJS_PATH'] ?? getenv('NODEJS_PATH') ?? null;

        if ($nodejsPathRaw) {
            // Normalize path separators and remove quotes
            $nodejsPath = trim(str_replace('/', '\\', $nodejsPathRaw), '"\' ');
            $npxSearchLog[] = "NODEJS_PATH: $nodejsPath";

            // Check if node.exe exists at this path
            $nodePath = rtrim($nodejsPath, '\\') . '\\node.exe';
            $npxSearchLog[] = "Checking: $nodePath";

            if (!file_exists($nodePath)) {
                $npxSearchLog[] = "node.exe not found";
                // Try is_readable to check permissions
                if (is_dir($nodejsPath)) {
                    $npxSearchLog[] = "Directory exists but node.exe missing";
                } else {
                    $npxSearchLog[] = "Directory not accessible: $nodejsPath";
                }
                $nodejsPath = null;
            } else {
                $npxSearchLog[] = "node.exe found";
            }
        }

        // Try to find Node.js using 'where' command if not found
        if (!$nodejsPath) {
            $whereOutput = [];
            exec('where node.exe 2>&1', $whereOutput);
            if (!empty($whereOutput) && strpos($whereOutput[0], 'node.exe') !== false) {
                $foundPath = dirname(trim($whereOutput[0]));
                if (file_exists($foundPath . '\\node.exe')) {
                    $nodejsPath = $foundPath;
                    $npxSearchLog[] = "Found via WHERE: $nodejsPath";
                }
            }
        }

        // Fallback: try common Node.js installation paths
        if (!$nodejsPath) {
            $commonPaths = [
                'C:\\Program Files\\nodejs',
                'C:\\Program Files (x86)\\nodejs',
            ];
            foreach ($commonPaths as $path) {
                if (file_exists($path . '\\node.exe')) {
                    $nodejsPath = $path;
                    $npxSearchLog[] = "Found Node.js at: $path";
                    break;
                }
            }
        }

        if (!$nodejsPath) {
            error_log('[TestRunner] Node.js not found. Search log: ' . implode('; ', $npxSearchLog));
            echo json_encode([
                'error' => 'Node.js niet gevonden. Stel NODEJS_PATH in in .env bestand.',
                'details' => implode(' | ', $npxSearchLog),
                'hint' => 'Voeg NODEJS_PATH="C:/Program Files/nodejs" toe aan .env.local'
            ]);
            exit;
        }

        // Now find cypress - call node.exe directly to bypass .cmd wrapper issues
        $cypressScript = $cmaRoot . '\\node_modules\\cypress\\bin\\cypress';
        $nodeExe = $nodejsPath . '\\node.exe';

        if (file_exists($cypressScript)) {
            // Call node.exe directly with cypress script - most reliable on IIS
            $cypressCmd = '"' . $nodeExe . '" "' . $cypressScript . '"';
            $npxSearchLog[] = "Using node directly: $nodeExe with $cypressScript";
        } else {
            // Fallback: try .cmd wrapper
            $cypressBin = $cmaRoot . '\\node_modules\\.bin\\cypress.cmd';
            if (file_exists($cypressBin)) {
                $cypressCmd = '"' . $cypressBin . '"';
                $npxSearchLog[] = "Using local cypress.cmd: " . $cypressBin;
            } else {
                // Use npx from Node.js installation
                $npxPath = $nodejsPath . '\\npx.cmd';
                if (file_exists($npxPath)) {
                    $cypressCmd = '"' . $npxPath . '" cypress';
                    $npxSearchLog[] = "Using npx: " . $npxPath;
                }
            }
        }
    } else {
        // Linux/Mac - just use npx from PATH
        $cypressCmd = 'npx cypress';
    }

    $checkpointLog('NODEJS_LOOKUP_DONE');

    // If no cypress found, return detailed error
    if (!$cypressCmd) {
        error_log('[TestRunner] Cypress not found. Search log: ' . implode('; ', $npxSearchLog));
        echo json_encode([
            'error' => 'Cypress niet gevonden. Voer npm install uit in de CMA directory.',
            'details' => implode(' | ', $npxSearchLog),
            'hint' => 'Voer "npm install" uit in ' . $cmaRoot
        ]);
        exit;
    }

    $checkpointLog('CYPRESS_FOUND');
    error_log('[TestRunner] Using cypress command: ' . $cypressCmd);
    error_log('[TestRunner] Node.js path: ' . $nodejsPath);

    // Build Cypress command arguments
    // Use Electron (Cypress's built-in browser) - more reliable than Chrome in service contexts
    // --headless is required when running under IIS (no display)
    $cypressArgs = 'run --headless --browser electron --reporter json';

    // Add spec filter if specific tests selected
    if (!empty($selectedSpecs)) {
        // Run specific specs (now using relative paths which include subdirectory)
        $specFiles = [];
        foreach ($selectedSpecs as $specPath) {
            // specPath is now relative (e.g., "auth/login" or "components/cma-tree")
            $fullPath = $specDir . '/' . $specPath . '.cy.js';
            if (file_exists($fullPath)) {
                $specFiles[] = 'cypress/e2e/' . $specPath . '.cy.js';
            }
        }
        if (empty($specFiles)) {
            echo json_encode(['error' => 'Geen geldige specs geselecteerd']);
            exit;
        }
        $cypressArgs .= ' --spec "' . implode(',', $specFiles) . '"';
    }

    // Wrap in cmd.exe /c to properly inherit environment in IIS context
    // 2>&1 redirects stderr to stdout so we capture all output
    $cmd = 'cmd.exe /c "' . $cypressCmd . ' ' . $cypressArgs . ' 2>&1"';

    // Debug: log the command being executed
    error_log('[TestRunner] Cypress command: ' . $cmd);
    error_log('[TestRunner] Working directory: ' . $cypressDir);

    // Run test using proc_open for reliable environment variable handling on IIS
    $output = [];
    $returnCode = 0;

    // Build environment with Node.js path
    $env = null;
    if (PHP_OS_FAMILY === 'Windows' && $nodejsPath) {
        // Get all environment variables - use getenv() for most reliable results on IIS
        $env = getenv();
        if (!Arr::isArray($env)) {
            $env = [];
        }

        // Also merge $_ENV and relevant $_SERVER vars
        foreach ($_ENV as $key => $value) {
            if (is_string($value) && !isset($env[$key])) {
                $env[$key] = $value;
            }
        }

        // Find existing PATH (Windows is case-insensitive, could be PATH or Path)
        $existingPath = '';
        $pathKey = 'PATH';
        foreach ($env as $key => $value) {
            if (strtoupper($key) === 'PATH') {
                $existingPath = $value;
                $pathKey = $key; // Keep original casing
                break;
            }
        }
        if (!$existingPath) {
            $existingPath = getenv('PATH') ?: getenv('Path') ?: '';
        }

        // Set PATH with Node.js at the front
        $env[$pathKey] = $nodejsPath . ';' . $existingPath;

        // Essential Windows vars that must be set for cmd.exe
        if (!isset($env['SystemRoot']) && getenv('SystemRoot')) {
            $env['SystemRoot'] = getenv('SystemRoot');
        }
        if (!isset($env['COMSPEC']) && getenv('COMSPEC')) {
            $env['COMSPEC'] = getenv('COMSPEC');
        }

        // Set CYPRESS_CACHE_FOLDER - IIS runs as SYSTEM which has different cache location
        // Check .env first, then try to find user's cache
        $cypressCacheFolder = $_ENV['CYPRESS_CACHE_FOLDER'] ?? getenv('CYPRESS_CACHE_FOLDER') ?? null;

        if (!$cypressCacheFolder) {
            // Try common user cache locations
            $possibleCaches = [
                'C:\\Users\\diede\\AppData\\Local\\Cypress\\Cache',
                getenv('USERPROFILE') . '\\AppData\\Local\\Cypress\\Cache',
            ];
            foreach ($possibleCaches as $cache) {
                if ($cache && is_dir($cache)) {
                    $cypressCacheFolder = $cache;
                    break;
                }
            }
        }

        if ($cypressCacheFolder) {
            $env['CYPRESS_CACHE_FOLDER'] = $cypressCacheFolder;
            error_log('[TestRunner] CYPRESS_CACHE_FOLDER: ' . $cypressCacheFolder);
        }

        error_log('[TestRunner] proc_open PATH: ' . $env[$pathKey]);
    }

    $checkpointLog('ENV_SETUP_DONE');

    // First, quick sanity check - can we run node at all?
    $nodeExe = $nodejsPath . '\\node.exe';
    $testCmd = '"' . $nodeExe . '" --version 2>&1';
    $checkpointLog('NODE_VERSION_TEST_START');
    $nodeVersion = shell_exec($testCmd);
    $checkpointLog('NODE_VERSION_TEST_DONE');
    error_log('[TestRunner] Node version test: ' . trim($nodeVersion));

    // SKIP THE PING TEST - it blocks for 5 seconds and may cause issues
    // The ping test was useful for debugging but now we know the system works
    $pingDuration = 0; // Skip
    $checkpointLog('PING_SKIPPED');

    // Add CI environment variables that help Cypress run in headless/server environments
    $env['CI'] = '1';
    $env['CYPRESS_CRASH_REPORTS'] = '0';
    $env['NO_COLOR'] = '1';

    // Electron-specific settings for running in service/headless mode
    $env['ELECTRON_ENABLE_LOGGING'] = '1';
    $env['ELECTRON_NO_ATTACH_CONSOLE'] = '1';
    $env['DISPLAY'] = ':0'; // Fake display for Linux compatibility layers

    // Disable GPU and sandbox for IIS service context
    $env['ELECTRON_DISABLE_GPU'] = '1';
    $env['CYPRESS_ELECTRON_ARGS'] = '--disable-gpu --no-sandbox --disable-dev-shm-usage';

    // Use proc_open with NON-BLOCKING reads to avoid hard_timeout issues
    // This polls for output instead of blocking, which keeps PHP active
    $checkpointLog('BEFORE_PROC_OPEN');
    error_log('[TestRunner] Starting command: ' . $cmd);
    error_log('[TestRunner] Working dir: ' . $cypressDir);

    $startTime = time();
    $stdout = '';
    $stderr = '';
    $outputText = '';
    $returnCode = -1;
    $processTimeout = 1200; // 20 minutes max for Cypress tests
    $pollInterval = 100000; // 100ms between polls

    $descriptors = [
        0 => ['pipe', 'r'],  // stdin
        1 => ['pipe', 'w'],  // stdout
        2 => ['pipe', 'w'],  // stderr
    ];

    $checkpointLog('PROC_OPEN_CALL');
    $process = proc_open($cmd, $descriptors, $pipes, $cypressDir, $env);
    $checkpointLog('PROC_OPEN_RETURNED');

    if (is_resource($process)) {
        // Close stdin immediately - we don't need it
        fclose($pipes[0]);

        // Set streams to non-blocking mode - CRITICAL for avoiding timeouts
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $checkpointLog('POLLING_LOOP_START');
        $pollCount = 0;

        // Poll for output until process finishes
        while (true) {
            $pollCount++;
            $status = proc_get_status($process);

            // Read available stdout (non-blocking)
            $chunk = fread($pipes[1], 8192);
            if ($chunk !== false && $chunk !== '') {
                $stdout .= $chunk;
            }

            // Read available stderr (non-blocking)
            $errChunk = fread($pipes[2], 8192);
            if ($errChunk !== false && $errChunk !== '') {
                $stderr .= $errChunk;
            }

            // Check if process has finished
            if (!$status['running']) {
                // Read any remaining output
                while (($remaining = fread($pipes[1], 8192)) !== false && $remaining !== '') {
                    $stdout .= $remaining;
                }
                while (($remaining = fread($pipes[2], 8192)) !== false && $remaining !== '') {
                    $stderr .= $remaining;
                }
                break;
            }

            // Check timeout
            $elapsed = time() - $startTime;
            if ($elapsed > $processTimeout) {
                error_log('[TestRunner] Process timeout after ' . $elapsed . 's, terminating...');
                proc_terminate($process, 9);
                $stderr .= "\n\nProcess terminated: timeout after {$processTimeout} seconds";
                break;
            }

            // Small sleep to prevent CPU spinning
            // This keeps PHP "active" and prevents hard_timeout from killing the request
            usleep($pollInterval);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $checkpointLog("POLLING_LOOP_END (pollCount=$pollCount)");

        // Get the exit code
        $returnCode = proc_close($process);

        $checkpointLog("PROC_CLOSE_DONE (code=$returnCode)");
        error_log('[TestRunner] Process closed with code: ' . $returnCode);
    } else {
        $checkpointLog('PROC_OPEN_FAILED');
        error_log('[TestRunner] Failed to open process!');
        $stderr = 'Failed to start process';
    }

    $outputText = trim($stdout);
    if (trim($stderr)) {
        $outputText .= ($outputText ? "\n\nSTDERR:\n" : "STDERR:\n") . trim($stderr);
    }

    $checkpointLog('OUTPUT_PROCESSED');

    $elapsed = time() - $startTime;
    $totalElapsed = round((microtime(true) - $actionStartTime) * 1000);
    $checkpointLog("DONE (total={$totalElapsed}ms)");

    error_log('[TestRunner] Finished after ' . $elapsed . 's, returnCode: ' . $returnCode);
    error_log('[TestRunner] Stdout length: ' . strlen($stdout) . ', Stderr length: ' . strlen($stderr));
    error_log('[TestRunner] All checkpoints: ' . implode(' -> ', $checkpoints));

    // Log first 500 chars of output for debugging
    if ($outputText) {
        error_log('[TestRunner] Output preview: ' . substr($outputText, 0, 500));
    } else {
        error_log('[TestRunner] No output captured');
    }
    $results = parseTestResults($outputText);

    // Save results
    $resultsData = [
        'timestamp' => date('c'),
        'specs' => empty($selectedSpecs) ? 'all' : implode(',', $selectedSpecs),
        'exitCode' => $returnCode,
        'success' => $returnCode === 0,
        'results' => $results,
        'rawOutput' => $outputText,
        'command' => $cmd,
        'workingDir' => $cypressDir,
        // Diagnostic info
        'diagnostics' => [
            'phpVersion' => PHP_VERSION,
            'osFamily' => PHP_OS_FAMILY,
            'executionTime' => isset($elapsed) ? $elapsed . 's' : 'unknown',
            'totalTimeMs' => $totalElapsed ?? 0,
            'processTimeout' => $processTimeout . 's',
            'pollInterval' => ($pollInterval / 1000) . 'ms',
            'pollCount' => $pollCount ?? 0,
            'stdoutLen' => isset($stdout) ? strlen($stdout) : 'N/A',
            'stderrLen' => isset($stderr) ? strlen($stderr) : 'N/A',
            'stdoutRaw' => isset($stdout) ? bin2hex($stdout) : 'N/A', // Show raw bytes
            'stderrRaw' => isset($stderr) ? substr($stderr, 0, 500) : 'N/A',
            'cypressCacheFolder' => $env['CYPRESS_CACHE_FOLDER'] ?? 'not set',
            'nodejsPath' => $nodejsPath ?? 'not set',
            'nodeVersionTest' => isset($nodeVersion) ? trim($nodeVersion) : 'not tested',
            'mode' => 'non-blocking poll',
            'checkpoints' => implode(' -> ', $checkpoints)
        ]
    ];

    if (!is_dir(dirname($resultsFile))) {
        mkdir(dirname($resultsFile), 0755, true);
    }
    file_put_contents($resultsFile, json_encode($resultsData, JSON_PRETTY_PRINT));

    echo json_encode([
        'status' => $returnCode === 0 ? 'passed' : 'failed',
        'exitCode' => $returnCode,
        'results' => $results,
        'timestamp' => date('c')
    ]);
    exit;
}

// Parse Cypress JSON output
function parseTestResults(string $output): array
{
    $results = [
        'total' => 0,
        'passed' => 0,
        'failed' => 0,
        'pending' => 0,
        'duration' => 0,
        'tests' => [],
        'failures' => []
    ];

    // Try to extract JSON from output
    if (preg_match('/\{[\s\S]*"stats"[\s\S]*\}/', $output, $matches)) {
        $json = json_decode($matches[0], true);
        if ($json) {
            $results['total'] = $json['stats']['tests'] ?? 0;
            $results['passed'] = $json['stats']['passes'] ?? 0;
            $results['failed'] = $json['stats']['failures'] ?? 0;
            $results['pending'] = $json['stats']['pending'] ?? 0;
            $results['duration'] = $json['stats']['duration'] ?? 0;

            if (isset($json['results'])) {
                foreach ($json['results'] as $suite) {
                    extractTests($suite, $results['tests'], $results['failures']);
                }
            }
        }
    }

    // Fallback: parse plain text output
    if ($results['total'] === 0) {
        if (preg_match('/(\d+) passing/', $output, $m)) {
            $results['passed'] = (int)$m[1];
            $results['total'] += $results['passed'];
        }
        if (preg_match('/(\d+) failing/', $output, $m)) {
            $results['failed'] = (int)$m[1];
            $results['total'] += $results['failed'];
        }
        if (preg_match('/(\d+) pending/', $output, $m)) {
            $results['pending'] = (int)$m[1];
            $results['total'] += $results['pending'];
        }

        if (preg_match_all('/\d+\)\s+(.+?):\n\s+AssertionError:\s+(.+)/m', $output, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $results['failures'][] = [
                    'test' => trim($match[1]),
                    'error' => trim($match[2])
                ];
            }
        }
    }

    return $results;
}

function extractTests(array $suite, array &$tests, array &$failures): void
{
    if (isset($suite['tests'])) {
        foreach ($suite['tests'] as $test) {
            $testInfo = [
                'title' => $test['title'] ?? 'Unknown',
                'state' => $test['state'] ?? 'unknown',
                'duration' => $test['duration'] ?? 0
            ];
            $tests[] = $testInfo;

            if (($test['state'] ?? '') === 'failed' && isset($test['err'])) {
                $failures[] = [
                    'test' => $test['title'],
                    'error' => $test['err']['message'] ?? 'Unknown error'
                ];
            }
        }
    }

    if (isset($suite['suites'])) {
        foreach ($suite['suites'] as $subSuite) {
            extractTests($subSuite, $tests, $failures);
        }
    }
}

// Format duration
function formatDuration($ms): string
{
    if ($ms >= 60000) {
        return number_format($ms / 60000, 1) . ' min';
    } elseif ($ms >= 1000) {
        return number_format($ms / 1000, 1) . ' s';
    }
    return $ms . ' ms';
}

// Output HTML
cma_html_header('Cypress Tests');
echo '<BODY class="contentbody tools tool-testrunner">';

// Toolbar with buttons
$canRun = $cypressInstalled && $testsAvailable;
ToolbarHelper::start(true);
ToolbarHelper::title('Cypress Tests');
ToolbarHelper::separator();
ToolbarHelper::button('javascript:runAllTests()', 'lnr-rocket', $canRun, 'Alle tests', 'Command voor alle tests genereren', 'btnRunAll');
ToolbarHelper::button('javascript:runSelectedTests()', 'lnr-chevron-right-circle', $canRun, 'Selectie', 'Command voor selectie genereren', 'btnRunSelected');
ToolbarHelper::separator();
ToolbarHelper::button('javascript:fExpandAll()', 'lnr-expandall', true, '', 'Uitklappen', 'btnExpandAll');
ToolbarHelper::button('javascript:fCollapseAll()', 'lnr-collapseall', true, '', 'Inklappen', 'btnCollapseAll');
ToolbarHelper::startRight();
echo '<label class="select-all-label"><input type="checkbox" id="selectAll"> Alles selecteren</label>';
ToolbarHelper::end();

echo '<div id="c" class="tools">';

// Check Cypress installation
if (!$cypressInstalled) {
    echo '<lib-message type="error" style="margin-bottom:15px;">';
    echo 'Cypress is niet geïnstalleerd. Voer <code>npm install</code> uit in de CMA directory.';
    echo '</lib-message>';
}

// Check if tests are available
if (!$testsAvailable) {
    echo '<lib-message type="warning" style="margin-bottom:15px;">';
    echo 'Geen Cypress tests beschikbaar. Maak test bestanden aan in <code>cypress/e2e/</code>.';
    echo '</lib-message>';
}

// Running indicator (at top for visibility)
echo '<div id="runningIndicator" style="display:none; margin:20px 0; padding:15px; background:var(--bg-surface); border-radius:4px;">';
echo '<span class="lnr lnr-sync" style="animation: spin 1s linear infinite; display:inline-block;"></span>';
echo ' <span id="runningText">Tests worden uitgevoerd...</span>';
echo '</div>';

// Results section (above tests for visibility)
function displayResults($data, $isPrevious = false): void
{
    $results = $data['results'] ?? [];
    $success = $data['success'] ?? false;
    $timestamp = $data['timestamp'] ?? '';

    if ($isPrevious) {
        echo '<h3>Vorige resultaten</h3>';
        // For previous results, show info-style message, not alarming error
        $msgType = $success ? 'success' : 'warning';
        $msgText = $success ? 'Laatste run: alle tests geslaagd' : 'Laatste run: er waren gefaalde tests';
    } else {
        echo '<h3>Resultaten</h3>';
        $msgType = $success ? 'success' : 'error';
        $msgText = $success ? 'Alle tests geslaagd' : 'Er zijn tests gefaald';
    }
    echo '<lib-message type="' . $msgType . '" style="margin-bottom:15px;">' . $msgText . '</lib-message>';

    // Stats
    echo '<div class="stats-row">';
    echo '<div class="stat passed"><span class="stat-value">' . ($results['passed'] ?? 0) . '</span><span class="stat-label">Geslaagd</span></div>';
    echo '<div class="stat failed"><span class="stat-value">' . ($results['failed'] ?? 0) . '</span><span class="stat-label">Gefaald</span></div>';
    echo '<div class="stat pending"><span class="stat-value">' . ($results['pending'] ?? 0) . '</span><span class="stat-label">Overgeslagen</span></div>';
    echo '<div class="stat duration"><span class="stat-value">' . formatDuration($results['duration'] ?? 0) . '</span><span class="stat-label">Duur</span></div>';
    echo '</div>';

    // Timestamp
    if ($timestamp) {
        echo '<p class="timestamp">Uitgevoerd: ' . date('d-m-Y H:i:s', strtotime($timestamp)) . '</p>';
    }

    // Failures detail
    if (!empty($results['failures'])) {
        echo '<h4>Gefaalde tests</h4>';
        echo '<table class="failures-table">';
        echo '<thead><tr><th>Test</th><th>Fout</th></tr></thead>';
        echo '<tbody>';
        foreach ($results['failures'] as $failure) {
            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($failure['test'] ?? '') . '</strong></td>';
            echo '<td><code>' . htmlspecialchars($failure['error'] ?? '') . '</code></td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }

    // Show command and raw output for debugging
    $rawOutput = trim($data['rawOutput'] ?? '');
    $command = $data['command'] ?? '';
    $workingDir = $data['workingDir'] ?? '';
    $diagnostics = $data['diagnostics'] ?? [];

    echo '<p style="margin-top:15px;">';
    echo '<a href="#" onclick="toggleRawOutput(); return false;" style="color:var(--link-color);text-decoration:underline;">';
    echo '<span class="lnr lnr-code"></span> Toon details</a></p>';
    echo '<div id="rawOutput" style="display:none; max-height:400px; overflow:auto; background:var(--bg-code,#f5f5f5); padding:10px; border-radius:4px; font-size:var(--font-size-xs);">';

    if ($command) {
        echo '<strong>Command:</strong> <button type="button" onclick="copyCommand()" class="btn" style="font-size:var(--font-size-2xs); padding:2px 8px;">Kopiëren</button><br>';
        echo '<code id="cypressCommand" style="display:block; padding:5px; background:#fff; margin:5px 0 10px 0; word-break:break-all; cursor:pointer;" onclick="copyCommand()" title="Klik om te kopiëren">' . htmlspecialchars($command) . '</code>';
        echo '<script>function copyCommand() { navigator.clipboard.writeText(' . json_encode($command) . '); libToast("Command gekopieerd!"); }</script>';
    }
    if ($workingDir) {
        echo '<strong>Working directory:</strong> ' . htmlspecialchars($workingDir) . '<br><br>';
    }

    // Show diagnostics if available
    if (!empty($diagnostics)) {
        echo '<strong>Diagnostics:</strong><br>';
        echo '<table style="font-size:var(--font-size-xs); margin:5px 0 10px 0; border-collapse:collapse;">';
        foreach ($diagnostics as $key => $value) {
            echo '<tr><td style="padding:2px 10px 2px 0; color:#666;">' . htmlspecialchars($key) . ':</td>';
            echo '<td style="padding:2px 0;">' . htmlspecialchars($value) . '</td></tr>';
        }
        echo '</table>';
    }

    echo '<strong>Output:</strong><pre style="margin:5px 0 0 0; white-space:pre-wrap;">';
    if ($rawOutput) {
        echo htmlspecialchars($rawOutput);
    } else {
        echo '(geen output - Cypress heeft niets teruggegeven)';

        // Check if hard_timeout might be the issue
        $hardTimeout = ini_get('hard_timeout');
        $execTime = $diagnostics['executionTime'] ?? '';
        $execSeconds = (int) filter_var($execTime, FILTER_SANITIZE_NUMBER_INT);

        if ($hardTimeout !== false && $hardTimeout > 0 && $execSeconds <= ($hardTimeout + 1)) {
            echo "\n\n";
            echo "⚠️ HINT: PHP hard_timeout is set to {$hardTimeout} seconds.\n";
            echo "Cypress tests need more time to run. Edit php.ini:\n\n";
            echo "  File: " . php_ini_loaded_file() . "\n";
            echo "  Change: hard_timeout = 0  (or higher value like 600)\n";
            echo "  Then: iisreset\n";
        }
    }
    echo '</pre></div>';
}

echo '<div id="resultsPanel">';
// Only show results if explicitly requested via URL parameter (set after test run)
if ($lastResults && Request::query('showResults', '') === '1') {
    displayResults($lastResults);
}
echo '</div>';

// Specs section
echo '<h3>Beschikbare tests <span class="test-count">(' . $testCount . ' tests in ' . count($specsByCategory) . ' categorieën)</span></h3>';

if (!empty($specs)) {

    // Group by category
    foreach ($specsByCategory as $category => $categorySpecs):
        $categoryCount = count($categorySpecs);
        $categoryId = 'cat-' . preg_replace('/[^a-z0-9]/i', '-', $category);
    ?>
    <div class="spec-category" data-category="<?= htmlspecialchars($category) ?>">
        <cma-groupbox caption="<?= htmlspecialchars(ucfirst($category)) ?>" count="<?= $categoryCount ?>" storage-key="cy_<?= htmlspecialchars($categoryId) ?>"></cma-groupbox>
        <div class="category-content">
        <table class="specs-table" id="<?= htmlspecialchars($categoryId) ?>">
            <thead><tr>
                <th style="width:5%;"><input type="checkbox" class="category-check" data-category="<?= htmlspecialchars($categoryId) ?>" title="Selecteer alle in deze categorie"></th>
                <th style="width:25%;">Test spec</th><th style="width:50%;">Bestand</th><th style="width:20%;">Laatst gewijzigd</th>
            </tr></thead>
            <tbody>
            <?php foreach ($categorySpecs as $spec): ?>
                <tr>
                    <td><input type="checkbox" class="spec-check" value="<?= htmlspecialchars($spec['relativePath']) ?>" data-category="<?= htmlspecialchars($categoryId) ?>"></td>
                    <td><strong><?= htmlspecialchars($spec['name']) ?></strong></td>
                    <td><code><?= htmlspecialchars($spec['relativePath']) ?>.cy.js</code></td>
                    <td><?= date('d-m-Y H:i', $spec['mtime']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endforeach;
}

// Styles
?>
<style>
.select-all-label {
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    color: var(--text-primary);
}
.test-count {
    font-size: var(--font-size-md);
    font-weight: normal;
    color: var(--text-muted, #888);
}

/* Category sections */
.spec-category {
    margin-bottom: 15px;
}

.spec-category .category-content {
    border: 1px solid var(--border-color, #e0e0e0);
    border-top: 0;
    border-bottom: 0;
    border-radius: 0 0 6px 6px;
}

.specs-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}
.specs-table th, .specs-table td {
    padding: 8px 10px;
    text-align: left;
    border-bottom: 1px solid var(--border-color, #ddd);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.specs-table th {
    background: var(--bg-surface, #fafafa);
    font-weight: 600;
    font-size: var(--font-size-sm);
}
.specs-table tr:hover td {
    background: var(--bg-hover);
}
.specs-table code {
    font-size: var(--font-size-xs);
    background: var(--bg-code, #f0f0f0);
    padding: 2px 5px;
    border-radius: 3px;
}

.stats-row {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
}
.stat {
    padding: 15px 25px;
    border-radius: 6px;
    text-align: center;
    background: var(--bg-surface, #f5f5f5);
}
.stat.passed { background: #d4edda; color: #155724; }
.stat.failed { background: #f8d7da; color: #721c24; }
.stat.pending { background: #fff3cd; color: #856404; }
.stat.duration { background: var(--bg-surface, #e9ecef); }
.stat-value {
    display: block;
    font-size: var(--font-size-3xl);
    font-weight: 700;
}
.stat-label {
    font-size: var(--font-size-sm);
    text-transform: uppercase;
}

.timestamp {
    color: var(--text-muted, #888);
    font-size: var(--font-size-sm);
}

.failures-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
}
.failures-table th, .failures-table td {
    padding: 8px 10px;
    text-align: left;
    border-bottom: 1px solid var(--border-color, #ddd);
}
.failures-table th {
    background: #f8d7da;
    color: #721c24;
}
.failures-table code {
    font-size: var(--font-size-xs);
    color: #721c24;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

<script>
(function() {
    'use strict';

    var selectAll = document.getElementById('selectAll');
    var runningIndicator = document.getElementById('runningIndicator');
    var runningText = document.getElementById('runningText');

    // Select all toggle
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            var checks = document.querySelectorAll('.spec-check');
            checks.forEach(function(cb) { cb.checked = selectAll.checked; });
            // Also update category checkboxes
            document.querySelectorAll('.category-check').forEach(function(cb) {
                cb.checked = selectAll.checked;
            });
        });
    }

    // Category checkbox - select all in category
    document.querySelectorAll('.category-check').forEach(function(cb) {
        cb.addEventListener('change', function() {
            var categoryId = cb.dataset.category;
            var table = document.getElementById(categoryId);
            if (table) {
                table.querySelectorAll('.spec-check').forEach(function(specCb) {
                    specCb.checked = cb.checked;
                });
            }
        });
    });

    // Expose runTests function globally - shows command to copy
    window.runTests = function(specs) {
        console.log('[TestRunner] runTests called with', specs.length, 'specs:', specs);

        // Build URL to get command
        var url = '?action=command';
        if (specs.length > 0) {
            url += '&specs=' + encodeURIComponent(specs.join(','));
        }

        fetch(url)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                showCommandDialog(data.command, data.workingDir);
            })
            .catch(function(err) {
                libAlert('Fout bij genereren command: ' + err.message, { type: 'error' });
            });
    };

    /**
     * Show dialog with copyable command
     */
    function showCommandDialog(command, workingDir) {
        var resultsPanel = document.getElementById('resultsPanel');
        if (!resultsPanel) return;

        var html = '<h3>Cypress Test Command</h3>';
        html += '<lib-message type="info" style="margin-bottom:15px;">Kopieer en voer uit in een terminal (Cypress kan niet vanuit IIS draaien)</lib-message>';

        html += '<p><strong>1. Open een terminal en ga naar:</strong></p>';
        html += '<div class="command-box" onclick="copyToClipboard(this)" title="Klik om te kopiëren">';
        html += '<code>cd "' + escapeHtml(workingDir) + '"</code>';
        html += '<span class="copy-hint">📋</span></div>';

        html += '<p style="margin-top:15px;"><strong>2. Voer uit:</strong></p>';
        html += '<div class="command-box" onclick="copyToClipboard(this)" title="Klik om te kopiëren">';
        html += '<code>' + escapeHtml(command) + '</code>';
        html += '<span class="copy-hint">📋</span></div>';

        html += '<style>';
        html += '.command-box { background:#1e1e1e; color:#4ec9b0; padding:12px 40px 12px 15px; border-radius:4px; font-family:monospace; cursor:pointer; position:relative; margin:5px 0; word-break:break-all; }';
        html += '.command-box:hover { background:#2d2d2d; }';
        html += '.command-box code { color:#4ec9b0; }';
        html += '.copy-hint { position:absolute; right:10px; top:50%; transform:translateY(-50%); opacity:0.5; }';
        html += '.command-box:hover .copy-hint { opacity:1; }';
        html += '</style>';

        resultsPanel.innerHTML = html;
        resultsPanel.scrollIntoView({ behavior: 'smooth' });
    }

    /**
     * Copy command box content to clipboard
     */
    window.copyToClipboard = function(el) {
        var code = el.querySelector('code');
        if (code) {
            navigator.clipboard.writeText(code.textContent).then(function() {
                libToast('Gekopieerd naar klembord!');
            });
        }
    };

    /**
     * Extract PHP error from HTML response
     */
    function extractPhpError(html) {
        if (!html) return null;

        // Look for Fatal error pattern
        var fatalMatch = html.match(/Fatal error:\s*(.+?)\s+in\s+([^\s<]+)\s+on line\s+(\d+)/i);
        if (fatalMatch) {
            return 'PHP Fatal Error: ' + fatalMatch[1] + '\nBestand: ' + fatalMatch[2] + ':' + fatalMatch[3];
        }

        // Look for Parse error pattern
        var parseMatch = html.match(/Parse error:\s*(.+?)\s+in\s+([^\s<]+)\s+on line\s+(\d+)/i);
        if (parseMatch) {
            return 'PHP Parse Error: ' + parseMatch[1] + '\nBestand: ' + parseMatch[2] + ':' + parseMatch[3];
        }

        // Look for Exception pattern
        var exMatch = html.match(/Exception:\s*(.+?)(?:\s+in\s+([^\s<]+)\s+on line\s+(\d+))?/i);
        if (exMatch) {
            var msg = 'Exception: ' + exMatch[1];
            if (exMatch[2]) msg += '\nBestand: ' + exMatch[2] + ':' + exMatch[3];
            return msg;
        }

        // Look for [PHP_ERROR] marker from ErrorHandler
        var markerMatch = html.match(/\[PHP_ERROR\][^T]*Type:\s*([^|]+?)\s*\|\s*Message:\s*([^|]+?)\s*\|\s*File:\s*([^|]+?)\s*\|\s*Line:\s*(\d+)\s*\[\/PHP_ERROR\]/);
        if (markerMatch) {
            return markerMatch[1].trim() + ': ' + markerMatch[2].trim() + '\nBestand: ' + markerMatch[3].trim() + ':' + markerMatch[4];
        }

        // Return first 500 chars if no pattern matched
        return html.substring(0, 500) + (html.length > 500 ? '...' : '');
    }

    /**
     * Show error in the results panel instead of alert
     */
    function showErrorInResults(errorText, statusCode) {
        var resultsPanel = document.getElementById('resultsPanel');
        if (!resultsPanel) return;

        var html = '<h3>Fout bij uitvoeren tests</h3>';
        html += '<lib-message type="error" style="margin-bottom:15px;">Er is een fout opgetreden</lib-message>';

        if (statusCode) {
            html += '<p><strong>HTTP Status:</strong> ' + statusCode + '</p>';
        }

        html += '<div style="background:#1e1e1e;color:#ff6b6b;padding:15px;border-radius:4px;font-family:monospace;white-space:pre-wrap;max-height:400px;overflow:auto;">';
        html += escapeHtml(errorText);
        html += '</div>';

        html += '<p style="margin-top:15px;color:var(--text-muted);">Controleer de PHP error logs voor meer details.</p>';

        resultsPanel.innerHTML = html;
        resultsPanel.scrollIntoView({ behavior: 'smooth' });
    }

    // escapeHtml() provided by cma-utils.js

    console.log('[TestRunner] Initialized, window.runTests available:', typeof window.runTests === 'function');
})();

// Run all tests - called from toolbar
function runAllTests() {
    console.log('[TestRunner] runAllTests called');
    if (typeof window.runTests !== 'function') {
        libAlert('Test runner niet geïnitialiseerd', { type: 'error' });
        return;
    }
    window.runTests([]);
}

// Run selected tests - called from toolbar
function runSelectedTests() {
    console.log('[TestRunner] runSelectedTests called');
    if (typeof window.runTests !== 'function') {
        libAlert('Test runner niet geïnitialiseerd', { type: 'error' });
        return;
    }
    var selected = [];
    document.querySelectorAll('.spec-check:checked').forEach(function(cb) {
        selected.push(cb.value);
    });
    console.log('[TestRunner] Selected specs:', selected);
    if (selected.length === 0) {
        libAlert('Selecteer eerst een of meer tests', { type: 'warning', title: 'Geen tests geselecteerd' });
        return;
    }
    window.runTests(selected);
}

// Expand/collapse all categories via cma-groupbox
function fExpandAll() {
    document.querySelectorAll('.spec-category cma-groupbox').forEach(function(gb) {
        gb.open();
    });
}

function fCollapseAll() {
    document.querySelectorAll('.spec-category cma-groupbox').forEach(function(gb) {
        gb.close();
    });
}

function toggleRawOutput() {
    var el = document.getElementById('rawOutput');
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}

// Test 10-second timeout to verify long-running PHP works
function runTimeoutTest() {
    var resultsPanel = document.getElementById('resultsPanel');
    var runningIndicator = document.getElementById('runningIndicator');
    var runningText = document.getElementById('runningText');

    if (runningIndicator) {
        runningIndicator.style.display = 'block';
    }
    if (runningText) {
        runningText.textContent = 'Timeout test: wachten 10 seconden...';
    }

    var startTime = Date.now();
    console.log('[TestRunner] Starting timeout test at', new Date().toISOString());

    fetch('?action=timeout_test&seconds=10')
        .then(function(response) {
            var elapsed = Math.round((Date.now() - startTime) / 1000);
            console.log('[TestRunner] Timeout test response after', elapsed, 'seconds, status:', response.status);
            return response.text().then(function(text) {
                return { text: text, status: response.status, elapsed: elapsed };
            });
        })
        .then(function(result) {
            if (runningIndicator) {
                runningIndicator.style.display = 'none';
            }

            var html = '<h3>Timeout Test Resultaat</h3>';

            try {
                var data = JSON.parse(result.text);
                if (data.success) {
                    html += '<lib-message type="success">Test geslaagd!</lib-message>';
                    html += '<p>Server sliep ' + data.actual + ' seconden zonder timeout.</p>';
                    html += '<p>Dit betekent dat lange PHP processen werken.</p>';
                } else {
                    html += '<lib-message type="error">Test gefaald</lib-message>';
                    html += '<pre>' + result.text + '</pre>';
                }
            } catch (e) {
                html += '<lib-message type="error">Onverwachte response na ' + result.elapsed + 's</lib-message>';
                html += '<p>HTTP Status: ' + result.status + '</p>';
                html += '<pre style="background:#1e1e1e;color:#ff6b6b;padding:10px;max-height:300px;overflow:auto;">';
                html += result.text.substring(0, 2000);
                html += '</pre>';
            }

            html += '<p style="color:var(--text-muted);font-size:var(--font-size-sm);">Browser wachtte: ' + result.elapsed + 's</p>';

            if (resultsPanel) {
                resultsPanel.innerHTML = html;
            }
        })
        .catch(function(err) {
            var elapsed = Math.round((Date.now() - startTime) / 1000);
            cmaLog.error('[TestRunner] Timeout test error after', elapsed, 'seconds:', err);

            if (runningIndicator) {
                runningIndicator.style.display = 'none';
            }

            if (resultsPanel) {
                resultsPanel.innerHTML = '<h3>Timeout Test Resultaat</h3>' +
                    '<lib-message type="error">Test gefaald na ' + elapsed + 's</lib-message>' +
                    '<p>Netwerk fout: ' + err.message + '</p>' +
                    '<p>Dit kan betekenen dat IIS/FastCGI de connectie verbreekt.</p>';
            }
        });
}
</script>

<?php
echo '</div></BODY></HTML>';
