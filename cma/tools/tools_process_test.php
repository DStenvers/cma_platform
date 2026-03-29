<?php
/**
 * Process Runner Test Page
 * Tests running long processes from PHP
 */
require_once dirname(__DIR__) . '/bootstrap.inc';
require_once dirname(__DIR__) . '/classes/Services/ProcessRunner.php';

use App\Library\Request;
use Cma\Services\ProcessRunner;

// Check if this is an AJAX request for status
$action = Request::query('action', '');
$jobId = Request::query('job', '');

if ($action === 'status' && $jobId) {
    header('Content-Type: application/json');
    $runner = new ProcessRunner();
    echo json_encode($runner->getStatus($jobId));
    exit;
}

if ($action === 'cleanup' && $jobId) {
    header('Content-Type: application/json');
    $runner = new ProcessRunner();
    $runner->cleanup($jobId);
    echo json_encode(['success' => true]);
    exit;
}

// Handle form submission
$result = null;
$jobStarted = null;

if (Request::server('REQUEST_METHOD') === 'POST') {
    $duration = Request::postInt('duration', 10);
    $duration = max(5, min(120, $duration)); // Clamp between 5-120 seconds
    $mode = Request::post('mode', 'sync');

    $runner = new ProcessRunner(null, $duration + 30);

    // Create a test script that runs for the specified duration
    $tempDir = dirname(__DIR__) . '/temp';
    $testScript = $tempDir . '/test_' . uniqid() . '.sh';

    $scriptContent = <<<BASH
#!/bin/bash
DURATION=$duration
INTERVAL=5
for ((i=0; i<=\$duration; i+=\$INTERVAL)); do
    sleep \$INTERVAL
    echo "[\$(date '+%H:%M:%S')] Progress: \$((i+\$INTERVAL))/\$duration seconds"
done
echo ""
echo "Completed at \$(date)"
echo "STATUS: SUCCESS"
BASH;

    file_put_contents($testScript, $scriptContent);
    chmod($testScript, 0755);

    $command = "bash " . escapeshellarg($testScript);

    if ($mode === 'async') {
        // Start in background
        $jobStarted = $runner->runAsync($command);
        // Clean up script file after a delay (let it start first)
    } else {
        // Run synchronously
        $output = '';
        $result = $runner->runSync($command, function($chunk, $type) use (&$output) {
            $output .= $chunk;
        });
        $result['output'] = $output;

        // Clean up test script
        unlink($testScript);
    }
}

include dirname(__DIR__) . '/include/header.inc';
?>

<style>
.test-container {
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
}
.form-group {
    margin-bottom: 15px;
}
.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}
.form-group input[type="number"],
.form-group select {
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
    width: 200px;
}
.output-box {
    background: #1e1e1e;
    color: #00ff00;
    font-family: 'Consolas', 'Monaco', monospace;
    padding: 15px;
    border-radius: 4px;
    white-space: pre-wrap;
    max-height: 400px;
    overflow-y: auto;
}
.result-info {
    background: #f5f5f5;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 10px;
}
.result-info.success {
    border-left: 4px solid #28a745;
}
.result-info.error {
    border-left: 4px solid #dc3545;
}
.status-running {
    color: #007bff;
}
.status-completed {
    color: #28a745;
}
</style>

<div class="test-container">
    <h2>Process Runner Test</h2>
    <p>Test running long-duration processes from PHP.</p>

    <form method="post" id="testForm">
        <div class="form-group">
            <label for="duration">Duur (seconden):</label>
            <input type="number" id="duration" name="duration" value="10" min="5" max="120">
        </div>

        <div class="form-group">
            <label for="mode">Uitvoeringsmodus:</label>
            <select id="mode" name="mode">
                <option value="sync">Synchroon (wacht op voltooiing)</option>
                <option value="async">Asynchroon (draait op achtergrond)</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Start Test</button>
    </form>

    <?php if ($jobStarted): ?>
    <div id="asyncStatus" style="margin-top: 20px;">
        <h3>Achtergrondproces gestart</h3>
        <p>Job ID: <code><?= htmlspecialchars($jobStarted) ?></code></p>
        <p>Status: <span id="jobStatus" class="status-running">Wordt uitgevoerd...</span></p>
        <h4>Output:</h4>
        <div id="asyncOutput" class="output-box">Wachten op output...</div>
        <button id="cleanupBtn" class="btn" style="margin-top: 10px; display: none;">Opruimen</button>
    </div>

    <script>
    (function() {
        const jobId = <?= json_encode($jobStarted) ?>;
        const statusSpan = document.getElementById('jobStatus');
        const outputDiv = document.getElementById('asyncOutput');
        const cleanupBtn = document.getElementById('cleanupBtn');
        let pollInterval;

        function checkStatus() {
            fetch('?action=status&job=' + encodeURIComponent(jobId))
                .then(r => r.json())
                .then(data => {
                    outputDiv.textContent = data.output || 'Geen output';
                    outputDiv.scrollTop = outputDiv.scrollHeight;

                    if (data.status === 'completed') {
                        statusSpan.textContent = 'Voltooid (exit code: ' + data.exitCode + ')';
                        statusSpan.className = data.success ? 'status-completed' : 'status-error';
                        clearInterval(pollInterval);
                        cleanupBtn.style.display = 'inline-block';
                    }
                });
        }

        pollInterval = setInterval(checkStatus, 1000);
        checkStatus();

        cleanupBtn.onclick = function() {
            fetch('?action=cleanup&job=' + encodeURIComponent(jobId))
                .then(() => {
                    cleanupBtn.textContent = 'Opgeruimd!';
                    cleanupBtn.disabled = true;
                });
        };
    })();
    </script>
    <?php endif; ?>

    <?php if ($result): ?>
    <div style="margin-top: 20px;">
        <h3>Resultaat</h3>
        <div class="result-info <?= $result['success'] ? 'success' : 'error' ?>">
            <strong>Status:</strong> <?= $result['success'] ? 'Geslaagd' : 'Mislukt' ?><br>
            <strong>Exit code:</strong> <?= $result['exitCode'] ?><br>
            <strong>Duur:</strong> <?= $result['duration'] ?> seconden
        </div>

        <h4>Output:</h4>
        <div class="output-box"><?= htmlspecialchars($result['output']) ?></div>

        <?php if (!empty($result['errors'])): ?>
        <h4>Fouten:</h4>
        <div class="output-box" style="color: #ff6b6b;"><?= htmlspecialchars($result['errors']) ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/include/footer.inc'; ?>
