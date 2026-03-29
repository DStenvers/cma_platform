<?php
/**
 * Tests for EmailLogService
 *
 * Run with: php tests/TestRunner.php EmailLogTest
 *
 * Tests the email logging system end-to-end:
 * - Table existence, hook registration, env variable
 * - Log insert, getById, resend, delete, cleanup
 * - Form definition, API endpoint, JS, menu entry
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/bootstrap.inc';

use App\Library\Database;
use App\Library\Email;
use App\Library\Application;
use Cma\Services\EmailLogService;

class EmailLogTest extends TestCase
{
    private static ?int $testEmailId = null;

    // ========================================================================
    // Infrastructure tests
    // ========================================================================

    public function testTableExists(): void
    {
        $conn = Database::getConnection('data');
        $this->assertNotNull($conn, 'Geen verbinding met data database');
        // Will throw if table doesn't exist
        $conn->query("SELECT TOP 1 ID FROM tblEmailLog");
        $this->assertTrue(true);
    }

    public function testAfterSendHookRegistered(): void
    {
        $this->assertNotNull(Email::$afterSend, 'Hook is niet geregistreerd - controleer bootstrap.inc');
        $this->assertTrue(is_callable(Email::$afterSend), 'Hook is geen callable');
    }

    public function testEmailLogEnabledEnv(): void
    {
        $value = $_ENV['EMAIL_LOG_ENABLED'] ?? 'true';
        $this->assertTrue($value !== 'false', 'EMAIL_LOG_ENABLED staat op false');
    }

    // ========================================================================
    // Functional tests
    // ========================================================================

    public function testSendAndLog(): void
    {
        $conn = Database::getConnection('data');
        $before = (int)$conn->query("SELECT COUNT(*) FROM tblEmailLog")->fetchColumn();

        $email = Email::create()
            ->setSubject('[TEST] EmailLogTest - ' . date('Y-m-d H:i:s'))
            ->setBody('Automatische test.<BR>Tijdstip: ' . date('Y-m-d H:i:s'))
            ->setUseTemplate(false)
            ->addRecipient('test@example.com');
        $email->send();

        $after = (int)$conn->query("SELECT COUNT(*) FROM tblEmailLog")->fetchColumn();
        $this->assertGreaterThan($before, $after, 'E-mail werd niet gelogd');

        $stmt = $conn->query("SELECT TOP 1 * FROM tblEmailLog ORDER BY ID DESC");
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotNull($record, 'Geen record gevonden na verzending');

        self::$testEmailId = (int)$record['ID'];

        $this->assertNotEmpty($record['mail_subject'], 'mail_subject is leeg');
        $this->assertStringContainsString('test@example.com', $record['mail_to'], 'mail_to mist test@example.com');
        $this->assertNotEmpty($record['mail_status'], 'mail_status is leeg');
        $this->assertNotEmpty($record['datestamp'], 'datestamp is leeg');
    }

    public function testGetById(): void
    {
        $this->assertNotNull(self::$testEmailId, 'Geen test e-mail ID (testSendAndLog gefaald?)');
        $record = EmailLogService::getById(self::$testEmailId);
        $this->assertNotNull($record, 'Record niet gevonden via getById');
        $this->assertEquals(self::$testEmailId, (int)$record['ID']);
    }

    public function testResend(): void
    {
        $this->assertNotNull(self::$testEmailId, 'Geen test e-mail ID');
        $result = EmailLogService::resend(self::$testEmailId);
        // In simulation mode the result depends on environment, but it shouldn't crash
        $this->assertIsArray($result, 'Resend gaf geen array terug');
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('error', $result);
    }

    public function testResendInvalidId(): void
    {
        $result = EmailLogService::resend(999999);
        $this->assertFalse($result['success'], 'Resend van ongeldig ID zou moeten falen');
        $this->assertNotEmpty($result['error'], 'Foutmelding ontbreekt');
    }

    public function testDelete(): void
    {
        $this->assertNotNull(self::$testEmailId, 'Geen test e-mail ID');
        $success = EmailLogService::delete(self::$testEmailId);
        $this->assertTrue($success, 'Delete gaf false terug');
        $record = EmailLogService::getById(self::$testEmailId);
        $this->assertNull($record, 'Record bestaat nog na delete');
    }

    public function testCleanup(): void
    {
        // Just verify cleanup runs without errors
        EmailLogService::cleanup();
        $this->assertTrue(true);
    }

    // ========================================================================
    // File/config existence tests
    // ========================================================================

    public function testFormDefinitionExists(): void
    {
        $path = dirname(__DIR__) . '/assets/forms/definitions/emaillog.json';
        $this->assertTrue(file_exists($path), 'emaillog.json niet gevonden');
        $json = json_decode(file_get_contents($path), true);
        $this->assertNotNull($json, 'Ongeldig JSON');
        $this->assertEquals('emaillog', $json['name'] ?? '');
        $this->assertEquals('tblEmailLog', $json['table'] ?? '');
    }

    public function testApiEndpointExists(): void
    {
        $path = dirname(__DIR__) . '/api/email-actions.php';
        $this->assertTrue(file_exists($path), 'email-actions.php niet gevonden');
    }

    public function testJsFunction(): void
    {
        $path = dirname(__DIR__) . '/assets/js/cma.js';
        $this->assertTrue(file_exists($path), 'cma.js niet gevonden');
        $content = file_get_contents($path);
        $this->assertStringContainsString('CMA.emailLog', $content, 'CMA.emailLog niet gevonden in cma.js');
    }

    public function testMenuEntry(): void
    {
        $path = dirname(__DIR__, 2) . '/data/menu.json';
        $this->assertTrue(file_exists($path), 'menu.json niet gevonden');
        $content = file_get_contents($path);
        $this->assertStringContainsString('"emaillog"', $content, '"emaillog" niet in menu.json');
    }

    // ========================================================================
    // Cleanup
    // ========================================================================

    public function testZCleanupTestRecords(): void
    {
        $conn = Database::getConnection('data');
        if ($conn) {
            $conn->exec("DELETE FROM tblEmailLog WHERE mail_subject LIKE '%[TEST] EmailLogTest%'");
        }
        $this->assertTrue(true);
    }
}
