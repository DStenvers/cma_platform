<?php
/**
 * ProcessRunner - Run long-running processes from PHP
 *
 * Supports:
 * - Synchronous execution with real-time output
 * - Background execution with status polling
 * - Configurable timeouts
 * - Output to file for persistence
 */

namespace Cma\Services;

class ProcessRunner
{
    private $tempDir;
    private $timeout;
    private $pollInterval;

    /**
     * @param string $tempDir Directory for output files
     * @param int $timeout Maximum execution time in seconds
     * @param int $pollInterval Polling interval in microseconds
     */
    public function __construct($tempDir = null, $timeout = 300, $pollInterval = 100000)
    {
        $this->tempDir = $tempDir ?? dirname(__DIR__, 2) . '/temp';
        $this->timeout = $timeout;
        $this->pollInterval = $pollInterval;

        // Ensure temp directory exists
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Run a command synchronously with real-time output
     *
     * @param string $command The command to run
     * @param callable|null $outputCallback Called with each output chunk
     * @return array ['output' => string, 'errors' => string, 'exitCode' => int, 'duration' => int]
     */
    public function runSync($command, ?callable $outputCallback = null)
    {
        // Extend PHP limits
        $previousLimit = ini_get('max_execution_time');
        set_time_limit($this->timeout + 30);
        ignore_user_abort(true);

        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new \Exception("Failed to start process: $command");
        }

        // Close stdin
        fclose($pipes[0]);

        // Set streams to non-blocking
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $errors = '';
        $startTime = time();

        while (true) {
            $status = proc_get_status($process);

            // Read available output
            $chunk = fread($pipes[1], 8192);
            if ($chunk !== false && $chunk !== '') {
                $output .= $chunk;
                if ($outputCallback) {
                    $outputCallback($chunk, 'stdout');
                }
            }

            // Read available errors
            $errChunk = fread($pipes[2], 8192);
            if ($errChunk !== false && $errChunk !== '') {
                $errors .= $errChunk;
                if ($outputCallback) {
                    $outputCallback($errChunk, 'stderr');
                }
            }

            // Check if process has finished
            if (!$status['running']) {
                // Read any remaining output
                $output .= stream_get_contents($pipes[1]);
                $errors .= stream_get_contents($pipes[2]);
                break;
            }

            // Check timeout
            $elapsed = time() - $startTime;
            if ($elapsed > $this->timeout) {
                proc_terminate($process, 9);
                throw new \Exception("Process timed out after {$this->timeout} seconds");
            }

            usleep($this->pollInterval);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        // Restore previous limit
        set_time_limit((int)$previousLimit);

        return [
            'output' => $output,
            'errors' => $errors,
            'exitCode' => $exitCode,
            'duration' => time() - $startTime,
            'success' => $exitCode === 0
        ];
    }

    /**
     * Run a command in the background
     *
     * @param string $command The command to run
     * @param string|null $jobId Optional job ID (auto-generated if not provided)
     * @return string The job ID for status checking
     */
    public function runAsync($command, $jobId = null)
    {
        $jobId = $jobId ?? uniqid('job_', true);
        $outputFile = $this->getOutputFile($jobId);
        $pidFile = $this->getPidFile($jobId);
        $statusFile = $this->getStatusFile($jobId);

        // Write initial status
        $this->writeStatus($jobId, [
            'status' => 'starting',
            'startTime' => date('c'),
            'command' => $command
        ]);

        // Wrap command to update status on completion
        $wrappedCommand = sprintf(
            '(%s; echo $? > %s) > %s 2>&1 & echo $!',
            $command,
            escapeshellarg($this->getExitCodeFile($jobId)),
            escapeshellarg($outputFile)
        );

        $pid = trim(shell_exec($wrappedCommand));

        file_put_contents($pidFile, $pid);

        $this->writeStatus($jobId, [
            'status' => 'running',
            'pid' => $pid,
            'startTime' => date('c'),
            'command' => $command
        ]);

        return $jobId;
    }

    /**
     * Check the status of a background job
     *
     * @param string $jobId The job ID from runAsync
     * @return array Status information
     */
    public function getStatus($jobId)
    {
        $pidFile = $this->getPidFile($jobId);
        $outputFile = $this->getOutputFile($jobId);
        $statusFile = $this->getStatusFile($jobId);
        $exitCodeFile = $this->getExitCodeFile($jobId);

        if (!file_exists($pidFile)) {
            return ['status' => 'not_found', 'jobId' => $jobId];
        }

        $pid = trim(file_get_contents($pidFile));
        $isRunning = $this->isProcessRunning($pid);
        $output = file_exists($outputFile) ? file_get_contents($outputFile) : '';
        $exitCode = file_exists($exitCodeFile) ? (int)trim(file_get_contents($exitCodeFile)) : null;

        $status = $isRunning ? 'running' : 'completed';

        return [
            'status' => $status,
            'jobId' => $jobId,
            'pid' => $pid,
            'output' => $output,
            'exitCode' => $exitCode,
            'success' => $exitCode === 0
        ];
    }

    /**
     * Wait for a background job to complete
     *
     * @param string $jobId The job ID from runAsync
     * @param callable|null $progressCallback Called periodically with status
     * @return array Final status
     */
    public function waitFor($jobId, ?callable $progressCallback = null)
    {
        $startTime = time();

        while (true) {
            $status = $this->getStatus($jobId);

            if ($progressCallback) {
                $progressCallback($status);
            }

            if ($status['status'] !== 'running') {
                return $status;
            }

            if ((time() - $startTime) > $this->timeout) {
                // Kill the process
                $pid = $status['pid'];
                if ($pid && $this->isProcessRunning($pid)) {
                    posix_kill((int)$pid, 9);
                }
                throw new \Exception("Job $jobId timed out after {$this->timeout} seconds");
            }

            usleep($this->pollInterval * 10); // Poll less frequently for async
        }
    }

    /**
     * Clean up files for a completed job
     *
     * @param string $jobId The job ID
     */
    public function cleanup($jobId)
    {
        $files = [
            $this->getOutputFile($jobId),
            $this->getPidFile($jobId),
            $this->getStatusFile($jobId),
            $this->getExitCodeFile($jobId)
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    private function getOutputFile($jobId)
    {
        return $this->tempDir . '/' . $jobId . '.output';
    }

    private function getPidFile($jobId)
    {
        return $this->tempDir . '/' . $jobId . '.pid';
    }

    private function getStatusFile($jobId)
    {
        return $this->tempDir . '/' . $jobId . '.status';
    }

    private function getExitCodeFile($jobId)
    {
        return $this->tempDir . '/' . $jobId . '.exit';
    }

    private function writeStatus($jobId, array $status)
    {
        file_put_contents(
            $this->getStatusFile($jobId),
            json_encode($status, JSON_PRETTY_PRINT)
        );
    }

    private function isProcessRunning($pid)
    {
        if (empty($pid)) {
            return false;
        }

        // Check if process exists
        if (function_exists('posix_kill')) {
            return posix_kill((int)$pid, 0);
        }

        // Fallback: check /proc
        return file_exists("/proc/$pid");
    }
}
