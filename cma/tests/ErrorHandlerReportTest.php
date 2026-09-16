<?php
/**
 * ErrorHandler::report() — the loud channel for a caught failure.
 *
 * A caught failure that the operation survives (audit row, mail archive)
 * must still be logged like an uncaught one and kept for the admin notice.
 *
 *   php tests/TestRunner.php ErrorHandlerReportTest
 */
require_once __DIR__ . '/TestRunner.php';

use App\Library\ErrorHandler;

class ErrorHandlerReportTest extends TestCase
{
    private string $tmpLog;
    private string $originalErrorLog;

    public function setUp(): void
    {
        $this->tmpLog = sys_get_temp_dir() . '/eh-report-' . bin2hex(random_bytes(4)) . '.log';
        touch($this->tmpLog);
        $this->originalErrorLog = (string) ini_get('error_log');
        ini_set('error_log', $this->tmpLog);
    }

    public function tearDown(): void
    {
        ini_set('error_log', $this->originalErrorLog);
        @unlink($this->tmpLog);
    }

    public function testReportKeepsTheMessageWithContext(): void
    {
        $before = count(ErrorHandler::getReported());
        ErrorHandler::report(new \RuntimeException('kolom ontbreekt'), 'Audit-regel niet weggeschreven');
        $reported = ErrorHandler::getReported();
        $this->assertEquals($before + 1, count($reported));
        $this->assertEquals('Audit-regel niet weggeschreven: kolom ontbreekt', end($reported));
    }

    public function testReportWritesTheErrorLog(): void
    {
        ErrorHandler::report(new \RuntimeException('gelogd via report'));
        clearstatcache(true, $this->tmpLog);
        $log = (string) file_get_contents($this->tmpLog);
        $this->assertStringContainsString('gelogd via report', $log, 'a reported failure lands in the PHP error log');
    }

    public function testReportNeverThrows(): void
    {
        // Mail is off in the test process; report() must stay silent about that.
        $threw = false;
        try {
            ErrorHandler::report(new \RuntimeException('x'), 'y');
        } catch (\Throwable $e) {
            $threw = true;
        }
        $this->assertFalse($threw);
    }
}
