<?php
/**
 * Tests for the deterministically-testable helpers of App\Library\ErrorHandler.
 *
 * Run with: php tests/TestRunner.php ErrorHandlerHelpersTest
 *
 * We do NOT exercise the full HTML/CLI rendering paths (they echo huge pages
 * and call exit()). Instead we reach the pure helper methods — most of which
 * are `protected static` — through reflection.
 *
 * The most important coverage here is SECRET REDACTION
 * (getRedactedEnvContent + scrubEmbeddedSecrets): a miss there leaks a
 * credential into an error page. Those tests are intentionally thorough,
 * including keys that don't literally contain the word "password"
 * (SMTP_PASS) and credentials embedded inside otherwise-innocuous values
 * (DATABASE_URL, ODBC DSN).
 */

require_once __DIR__ . '/TestRunner.php';

class ErrorHandlerHelpersTest extends TestCase
{
    private const CLASS_NAME = 'App\\Library\\ErrorHandler';

    /** @var array Saved $_SERVER keys we mutate, so we can restore them. */
    private array $savedServer = [];

    /** @var string[] Temp files to clean up in tearDown. */
    private array $tmpFiles = [];

    public function setUp(): void
    {
        // Save every $_SERVER key the tests below touch.
        foreach (['SERVER_NAME', 'REMOTE_ADDR', 'REQUEST_URI', 'HTTP_X_REQUESTED_WITH', 'HTTP_ACCEPT'] as $key) {
            $this->savedServer[$key] = array_key_exists($key, $_SERVER) ? $_SERVER[$key] : null;
        }
        $this->tmpFiles = [];
    }

    public function tearDown(): void
    {
        foreach ($this->savedServer as $key => $value) {
            if ($value === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $value;
            }
        }
        foreach ($this->tmpFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->tmpFiles = [];
    }

    /** Invoke a protected/private static method by name. */
    private function invoke(string $method, ...$args)
    {
        $m = new \ReflectionMethod(self::CLASS_NAME, $method);
        $m->setAccessible(true);
        return $m->invoke(null, ...$args);
    }

    /** Write a temp file, track it for cleanup, return its path. */
    private function tmpFile(string $content, string $suffix = '.env'): string
    {
        $path = sys_get_temp_dir() . '/eh-' . bin2hex(random_bytes(4)) . $suffix;
        file_put_contents($path, $content);
        $this->tmpFiles[] = $path;
        return $path;
    }

    // ------------------------------------------------------------------
    // getRedactedEnvContent — the security-critical redaction
    // ------------------------------------------------------------------

    private function sampleEnv(): string
    {
        return implode("\n", [
            'DB_HOST=localhost',
            'SMTP_PASS=supersecret',
            'ANTHROPIC_API_KEY=sk-ant-123',
            'DEPLOY_SECRET=whmac_x',
            'DATABASE_URL=mysql://user:pass@host/db',
            'ODBC=Driver={x};Uid=sa;Pwd=hunter2',
            'PLAIN_URL=https://example.com/p',
        ]);
    }

    public function testRedactMissingFileReturnsMessage(): void
    {
        $out = $this->invoke('getRedactedEnvContent', sys_get_temp_dir() . '/does-not-exist-' . uniqid() . '.env');
        $this->assertStringContainsString('Unable to read', $out);
    }

    public function testRedactKeepsPlainNonSecretValuesVisible(): void
    {
        $out = $this->invoke('getRedactedEnvContent', $this->tmpFile($this->sampleEnv()));

        // Plain host, plain URL and the non-secret PLAIN_URL must survive intact.
        $this->assertStringContainsString('DB_HOST=localhost', $out);
        $this->assertStringContainsString('PLAIN_URL=https://example.com/p', $out);
    }

    public function testRedactMasksKeyNamedSecrets(): void
    {
        $out = $this->invoke('getRedactedEnvContent', $this->tmpFile($this->sampleEnv()));

        // Each secret VALUE must be gone and replaced with the mask.
        $this->assertStringNotContainsString('supersecret', $out, 'SMTP_PASS value leaked');
        $this->assertStringNotContainsString('sk-ant-123', $out, 'ANTHROPIC_API_KEY value leaked');
        $this->assertStringNotContainsString('whmac_x', $out, 'DEPLOY_SECRET value leaked');

        // Key-name masking keeps only the first char + mask.
        $this->assertStringContainsString('SMTP_PASS=s*****', $out);
        $this->assertStringContainsString('ANTHROPIC_API_KEY=s*****', $out);
        $this->assertStringContainsString('DEPLOY_SECRET=w*****', $out);
    }

    public function testRedactCatchesSmtpPassEvenWithoutLiteralPassword(): void
    {
        // SMTP_PASS does not contain the literal word "password" — the /pass/i
        // pattern is what must catch it. This is the tricky-key case.
        $out = $this->invoke('getRedactedEnvContent', $this->tmpFile("SMTP_PASS=supersecret"));
        $this->assertStringNotContainsString('supersecret', $out);
        $this->assertStringContainsString('*****', $out);
    }

    public function testRedactMasksEmbeddedCredentialInDatabaseUrl(): void
    {
        // DATABASE_URL is NOT a key-name match, so the embedded userinfo password
        // must be caught by scrubEmbeddedSecrets on the value side.
        $out = $this->invoke('getRedactedEnvContent', $this->tmpFile($this->sampleEnv()));

        $this->assertStringContainsString('user:*****@', $out, 'Embedded URL password not masked');
        $this->assertStringContainsString('DATABASE_URL=mysql://user:*****@host/db', $out);
        // The word "user" (the username) stays visible; only the password is masked.
        $this->assertStringContainsString('mysql://user:', $out);
    }

    public function testRedactMasksOdbcPwdInPlainKey(): void
    {
        // ODBC is not a key-name match; the Pwd=... inside the DSN value must be
        // scrubbed so hunter2 never appears in cleartext.
        $out = $this->invoke('getRedactedEnvContent', $this->tmpFile($this->sampleEnv()));

        $this->assertStringNotContainsString('hunter2', $out, 'ODBC Pwd leaked in cleartext');
        $this->assertStringContainsString('Pwd=*****', $out);
        // The non-secret bits of the DSN survive.
        $this->assertStringContainsString('Uid=sa', $out);
    }

    public function testRedactPreservesCommentsAndBlankLines(): void
    {
        $env = "# a comment\n\nDB_HOST=localhost\n# SECRET_TOKEN=should-stay-commented";
        $out = $this->invoke('getRedactedEnvContent', $this->tmpFile($env));
        $this->assertStringContainsString('# a comment', $out);
        // A commented line is emitted verbatim (not parsed as a secret).
        $this->assertStringContainsString('# SECRET_TOKEN=should-stay-commented', $out);
    }

    // ------------------------------------------------------------------
    // scrubEmbeddedSecrets — value-level credential masking
    // ------------------------------------------------------------------

    public function testScrubUrlUserinfo(): void
    {
        $out = $this->invoke('scrubEmbeddedSecrets', 'mysql://user:pass@host/db');
        $this->assertEquals('mysql://user:*****@host/db', $out);
    }

    public function testScrubOdbcPwd(): void
    {
        $out = $this->invoke('scrubEmbeddedSecrets', 'Driver={x};Uid=sa;Pwd=hunter2');
        $this->assertStringNotContainsString('hunter2', $out);
        $this->assertStringContainsString('Pwd=*****', $out);
    }

    public function testScrubInlinePasswordAssignment(): void
    {
        $out = $this->invoke('scrubEmbeddedSecrets', 'password=topsecret');
        $this->assertEquals('password=*****', $out);
    }

    public function testScrubInlineTokenAndApiKey(): void
    {
        $this->assertStringContainsString('token=*****', $this->invoke('scrubEmbeddedSecrets', 'token=abc123'));
        $this->assertStringContainsString('api_key=*****', $this->invoke('scrubEmbeddedSecrets', 'api_key=abc123'));
    }

    public function testScrubLeavesPlainValuesUnchanged(): void
    {
        // A plain URL with no userinfo and no inline secret assignment is untouched.
        $this->assertEquals('https://example.com/p', $this->invoke('scrubEmbeddedSecrets', 'https://example.com/p'));
        $this->assertEquals('localhost', $this->invoke('scrubEmbeddedSecrets', 'localhost'));
        $this->assertEquals('', $this->invoke('scrubEmbeddedSecrets', ''));
    }

    // ------------------------------------------------------------------
    // isLocalEnvironment
    // ------------------------------------------------------------------

    private function setEnvServer(?string $serverName, ?string $remoteAddr): void
    {
        if ($serverName === null) { unset($_SERVER['SERVER_NAME']); } else { $_SERVER['SERVER_NAME'] = $serverName; }
        if ($remoteAddr === null) { unset($_SERVER['REMOTE_ADDR']); } else { $_SERVER['REMOTE_ADDR'] = $remoteAddr; }
    }

    public function testIsLocalForLocalhostServerName(): void
    {
        $this->setEnvServer('localhost', '203.0.113.5');
        $this->assertTrue($this->invoke('isLocalEnvironment'));
    }

    public function testIsLocalForLoopbackRemoteAddr(): void
    {
        $this->setEnvServer('example.com', '127.0.0.1');
        $this->assertTrue($this->invoke('isLocalEnvironment'));
    }

    public function testIsLocalForDotTestAndDotLocalDomains(): void
    {
        $this->setEnvServer('myapp.test', '203.0.113.5');
        $this->assertTrue($this->invoke('isLocalEnvironment'));

        $this->setEnvServer('myapp.local', '203.0.113.5');
        $this->assertTrue($this->invoke('isLocalEnvironment'));
    }

    public function testIsLocalForIpv6Loopback(): void
    {
        $this->setEnvServer('example.com', '::1');
        $this->assertTrue($this->invoke('isLocalEnvironment'));
    }

    public function testIsNotLocalForRealHostname(): void
    {
        $this->setEnvServer('www.example.com', '203.0.113.5');
        $this->assertFalse($this->invoke('isLocalEnvironment'));
    }

    public function testIsNotLocalWhenServerVarsAbsent(): void
    {
        $this->setEnvServer(null, null);
        // Empty server name/addr are not in the local host list and contain no
        // .local/.test — so this reads as non-local.
        $this->assertFalse($this->invoke('isLocalEnvironment'));
    }

    // ------------------------------------------------------------------
    // wantsJson — NOTE: short-circuits to false under CLI (PHP_SAPI === 'cli').
    // The test runner is CLI, so the true-branches are unreachable here; we can
    // only prove the CLI guard holds. Documented as a coverage gap in the report.
    // ------------------------------------------------------------------

    public function testWantsJsonIsFalseUnderCli(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/x';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        // Even with every JSON signal set, the CLI guard returns false.
        $result = $this->invoke('wantsJson');
        $this->assertTrue(is_bool($result));
        $this->assertFalse($result, 'wantsJson must short-circuit to false under CLI SAPI');
    }

    // ------------------------------------------------------------------
    // viewerIsElevated — SecurityHelper (Cma\) is not loaded in the runner, so
    // this fails closed to false. We only assert it is a bool and never throws.
    // ------------------------------------------------------------------

    public function testViewerIsElevatedReturnsBoolWithoutThrowing(): void
    {
        $result = $this->invoke('viewerIsElevated');
        $this->assertTrue(is_bool($result));
        // Cma\SecurityHelper isn't loaded here → fail-closed to false.
        $this->assertFalse($result);
    }

    // ------------------------------------------------------------------
    // generateErrorComment
    // ------------------------------------------------------------------

    public function testGenerateErrorCommentFormat(): void
    {
        $ex = new \RuntimeException('boom');
        $comment = $this->invoke('generateErrorComment', $ex);
        $this->assertStringContainsString('[PHP_ERROR]', $comment);
        $this->assertStringContainsString('[/PHP_ERROR]', $comment);
        $this->assertStringContainsString('Type: RuntimeException', $comment);
        $this->assertStringContainsString('Message: boom', $comment);
    }

    public function testGenerateErrorCommentNeutralisesCommentTerminators(): void
    {
        // A "--" or newline in the message must not break out of the HTML comment.
        $ex = new \RuntimeException("bad -- thing\nsecond line");
        $comment = $this->invoke('generateErrorComment', $ex);
        $this->assertStringNotContainsString('bad -- thing', $comment);
        $this->assertStringContainsString('bad - - thing', $comment);
        // Newlines collapsed to spaces → no raw newline inside the message body.
        $this->assertStringContainsString('bad - - thing second line', $comment);
    }

    // ------------------------------------------------------------------
    // formatExceptionForLog
    // ------------------------------------------------------------------

    public function testFormatExceptionForLogContainsCoreFields(): void
    {
        $ex = new \RuntimeException('kaboom');
        $log = $this->invoke('formatExceptionForLog', $ex);
        $this->assertStringContainsString('RuntimeException', $log);
        $this->assertStringContainsString('kaboom', $log);
        $this->assertStringContainsString('Stack trace:', $log);
        // Line reference "file:line" present.
        $this->assertStringContainsString(__FILE__, $log);
    }

    public function testFormatExceptionForLogIncludesPreviousException(): void
    {
        $inner = new \InvalidArgumentException('root cause');
        $outer = new \RuntimeException('wrapper', 0, $inner);
        $log = $this->invoke('formatExceptionForLog', $outer);
        $this->assertStringContainsString('Caused by:', $log);
        $this->assertStringContainsString('root cause', $log);
    }

    // ------------------------------------------------------------------
    // getDatabaseDiagnostics
    // ------------------------------------------------------------------

    public function testDiagnosticsNullForUnrelatedError(): void
    {
        $out = $this->invoke('getDatabaseDiagnostics', new \RuntimeException('Something totally unrelated'));
        $this->assertNull($out);
    }

    public function testDiagnosticsConnectionRefused(): void
    {
        $out = $this->invoke('getDatabaseDiagnostics', new \RuntimeException('SQLSTATE[HY000]: could not connect: Connection refused'));
        $this->assertIsArray($out);
        $this->assertEquals('Database Connection Refused', $out['title']);
        $this->assertArrayHasKey('likely_causes', $out);
    }

    public function testDiagnosticsAccessDenied(): void
    {
        $out = $this->invoke('getDatabaseDiagnostics', new \RuntimeException("Access denied for user 'sa'@'localhost'"));
        $this->assertIsArray($out);
        $this->assertEquals('Database Authentication Failed', $out['title']);
    }

    public function testDiagnosticsDriverNotFound(): void
    {
        $out = $this->invoke('getDatabaseDiagnostics', new \PDOException('could not find driver'));
        $this->assertIsArray($out);
        $this->assertStringContainsString('Not Found', $out['title']);
        $this->assertArrayHasKey('likely_causes', $out);
        $this->assertArrayHasKey('additional_info', $out);
    }

    // ------------------------------------------------------------------
    // getFileLines
    // ------------------------------------------------------------------

    public function testGetFileLinesReturnsContextWindow(): void
    {
        $lines = [];
        for ($i = 1; $i <= 20; $i++) { $lines[] = "line{$i}"; }
        $path = $this->tmpFile(implode("\n", $lines), '.txt');

        $out = $this->invoke('getFileLines', $path, 10, 2);
        $this->assertIsArray($out);
        // context=2 around line 10 → lines 8..12 (5 lines), keyed by line number.
        $this->assertCount(5, $out);
        $this->assertArrayHasKey(10, $out);
        $this->assertArrayHasKey(8, $out);
        $this->assertArrayHasKey(12, $out);
        $this->assertStringContainsString('line10', $out[10]);
    }

    public function testGetFileLinesMissingFileReturnsEmpty(): void
    {
        $out = $this->invoke('getFileLines', sys_get_temp_dir() . '/nope-' . uniqid() . '.txt', 5, 3);
        $this->assertIsArray($out);
        $this->assertCount(0, $out);
    }

    // ------------------------------------------------------------------
    // getTraceFrames
    // ------------------------------------------------------------------

    public function testGetTraceFramesFirstFrameIsThrowSite(): void
    {
        $ex = new \RuntimeException('x');
        $frames = $this->invoke('getTraceFrames', $ex);
        $this->assertIsArray($frames);
        $this->assertGreaterThan(0, count($frames));
        $this->assertEquals($ex->getFile(), $frames[0]['file']);
        $this->assertEquals($ex->getLine(), $frames[0]['line']);
    }

    // ------------------------------------------------------------------
    // formatFileSize
    // ------------------------------------------------------------------

    public function testFormatFileSize(): void
    {
        $this->assertEquals('0 B', $this->invoke('formatFileSize', 0));
        $this->assertEquals('1 KB', $this->invoke('formatFileSize', 1024));
        $this->assertEquals('1.5 KB', $this->invoke('formatFileSize', 1536));
        $this->assertEquals('1 MB', $this->invoke('formatFileSize', 1048576));
    }

    // ------------------------------------------------------------------
    // renderPermissionStatus
    // ------------------------------------------------------------------

    public function testRenderPermissionStatus(): void
    {
        $this->assertStringContainsString('✓', $this->invoke('renderPermissionStatus', true));
        $this->assertStringContainsString('✗', $this->invoke('renderPermissionStatus', false));
    }

    // ------------------------------------------------------------------
    // getRecentLogEntries
    // ------------------------------------------------------------------

    public function testGetRecentLogEntriesReturnsMostRecentFirst(): void
    {
        $path = $this->tmpFile("first\nsecond\nthird\n", '.log');
        $out = $this->invoke('getRecentLogEntries', $path, 100);
        $this->assertIsArray($out);
        $this->assertCount(3, $out);
        // Most recent (last written) line comes first.
        $this->assertEquals('third', $out[0]);
        $this->assertEquals('first', $out[2]);
    }

    public function testGetRecentLogEntriesMissingFileReturnsEmpty(): void
    {
        $out = $this->invoke('getRecentLogEntries', sys_get_temp_dir() . '/no-log-' . uniqid() . '.log', 10);
        $this->assertIsArray($out);
        $this->assertCount(0, $out);
    }
}
