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
// This test needs a full CMA + DB (it queries tblEmailLog directly). The
// parent _bootstrap.php lives one level up from cma/ in a consumer site,
// but is absent in the bare platform repo. PHP "hoists" top-level class
// declarations at compile time so a plain `return` here would still
// register the class with TestRunner; we wrap the whole declaration in
// an if-block so the class is only declared when the bootstrap is
// reachable.
if (file_exists(dirname(dirname(__DIR__)) . '/_bootstrap.php')) {
    require_once dirname(__DIR__) . '/bootstrap.inc';

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

} // end if (file_exists parent bootstrap)

/**
 * Deterministic, bootstrap-free coverage for EmailLogService.
 *
 * The EmailLogTest class above needs a full CMA + real tblEmailLog and only
 * declares when the parent _bootstrap.php exists (consumer site). In the bare
 * platform repo it runs zero tests. This class fills that gap without a DB:
 *  - the pure address (de)serialisation helpers, via reflection;
 *  - getById / delete / cleanup / resend against a StubConnection injected
 *    into Database's connection pool, asserting the SQL shape and the
 *    null-connection / not-found error paths.
 */
require_once __DIR__ . '/StubConnection.php';
require_once dirname(__DIR__) . '/classes/Services/EmailLogService.php';

use Cma\Services\EmailLogService;

class EmailLogServiceUnitTest extends TestCase
{
    private StubConnection $conn;
    /** @var array [\ReflectionProperty, originalValue] for namedConnections restore. */
    private array $poolRestore = [];

    public function setUp(): void
    {
        $this->conn = StubConnection::create();
        $ref = new \ReflectionClass(\App\Library\Database::class);
        $prop = $ref->getProperty('namedConnections');
        $prop->setAccessible(true);
        $this->poolRestore = [$prop, $prop->getValue()];
        $current = $prop->getValue();
        $current['data'] = $this->conn;
        $prop->setValue(null, $current);
    }

    public function tearDown(): void
    {
        if ($this->poolRestore) {
            [$prop, $original] = $this->poolRestore;
            $prop->setValue(null, $original);
            $this->poolRestore = [];
        }
    }

    private function callPrivate(string $method, array $args): mixed
    {
        $m = new \ReflectionMethod(EmailLogService::class, $method);
        $m->setAccessible(true);
        return $m->invoke(null, ...$args);
    }

    // ---- Pure address helpers -----------------------------------------

    public function testFormatAddressesRendersNameAndEmail(): void
    {
        $out = $this->callPrivate('formatAddresses', [[
            ['alice@example.test', 'Alice'],
            ['bob@example.test', 'Bob'],
        ]]);
        $this->assertEquals('Alice <alice@example.test>, Bob <bob@example.test>', $out);
    }

    public function testFormatAddressesOmitsAngleBracketsWhenNoName(): void
    {
        $out = $this->callPrivate('formatAddresses', [[
            ['alice@example.test', ''],
        ]]);
        $this->assertEquals('alice@example.test', $out);
    }

    public function testFormatAddressesAcceptsPlainStringEntries(): void
    {
        // Non-array entries pass through verbatim.
        $out = $this->callPrivate('formatAddresses', [['plain@example.test']]);
        $this->assertEquals('plain@example.test', $out);
    }

    public function testFormatAddressesEmptyGivesEmptyString(): void
    {
        $this->assertEquals('', $this->callPrivate('formatAddresses', [[]]));
    }

    public function testParseAddressesExtractsFromAngleBrackets(): void
    {
        $out = $this->callPrivate('parseAddresses', ['Alice <alice@example.test>, bob@example.test']);
        $this->assertEquals(['alice@example.test', 'bob@example.test'], $out);
    }

    public function testParseAddressesSkipsEmptyParts(): void
    {
        // Trailing comma / blank segment must not yield an empty address.
        $out = $this->callPrivate('parseAddresses', ['a@example.test, , b@example.test,']);
        $this->assertEquals(['a@example.test', 'b@example.test'], $out);
    }

    public function testFormatThenParseRoundTrips(): void
    {
        $formatted = $this->callPrivate('formatAddresses', [[
            ['alice@example.test', 'Alice'],
            ['bob@example.test', 'Bob'],
        ]]);
        $parsed = $this->callPrivate('parseAddresses', [$formatted]);
        $this->assertEquals(['alice@example.test', 'bob@example.test'], $parsed);
    }

    // ---- getById / delete / cleanup against the stub ------------------

    public function testGetByIdReturnsRowWhenFound(): void
    {
        $this->conn->enqueueResult([['ID' => 5, 'mail_subject' => 'Hi']]);
        $row = EmailLogService::getById(5);
        $this->assertNotNull($row);
        $this->assertEquals('Hi', $row['mail_subject']);

        $call = $this->conn->getLastCall();
        $this->assertStringContainsString('FROM tblEmailLog', $call['sql']);
        $this->assertStringContainsString('mail_error', $call['sql'], 'memo columns (mail_body/mail_error) must be selected last');
        $this->assertEquals([5], $call['params'], 'ID must be bound as a parameter');
    }

    public function testGetByIdReturnsNullWhenNotFound(): void
    {
        $this->conn->enqueueResult([]); // no rows
        $this->assertNull(EmailLogService::getById(123));
    }

    public function testDeleteIssuesParameterisedDeleteAndReturnsTrue(): void
    {
        $ok = EmailLogService::delete(7);
        $this->assertTrue($ok);
        $call = $this->conn->getLastCall();
        $this->assertStringContainsString('DELETE FROM tblEmailLog WHERE ID = ?', $call['sql']);
        $this->assertEquals([7], $call['params']);
    }

    public function testDeleteReturnsFalseOnDbError(): void
    {
        // A thrown driver error is caught and reported as a boolean false,
        // never bubbles up as a fatal.
        $this->conn->enqueueException(new \PDOException('locked'));
        $this->assertFalse(EmailLogService::delete(7));
    }

    public function testCleanupRunsExecWithoutThrowing(): void
    {
        EmailLogService::cleanup($this->conn);
        $call = $this->conn->getLastCall();
        $this->assertEquals('exec', $call['type']);
        $this->assertStringContainsString("DELETE FROM tblEmailLog", $call['sql']);
        $this->assertStringContainsString('-30', $call['sql'], 'cleanup deletes rows older than 30 days');
    }

    public function testResendOfMissingIdFailsCleanly(): void
    {
        // getById → empty result → resend must return a friendly failure and
        // never attempt to construct/send an email.
        $this->conn->enqueueResult([]);
        $result = EmailLogService::resend(999999);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('niet gevonden', $result['error']);
    }
}

