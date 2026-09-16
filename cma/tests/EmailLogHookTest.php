<?php
/**
 * EmailLogHookTest.php — where the mail-log hook is registered.
 *
 * Front-end pages never load cma/bootstrap.inc, so a hook registered there
 * logs CMA mail only. It belongs in the platform bootstrap, which every
 * request goes through. Guarded on the source so it holds without a site.
 *
 *   php tests/TestRunner.php EmailLogHookTest
 */
require_once __DIR__ . '/TestRunner.php';

class EmailLogHookTest extends TestCase
{
    public function testPlatformBootstrapRegistersTheHook(): void
    {
        $s = (string) file_get_contents(__DIR__ . '/../../src/helpers/Bootstrap.php');
        $this->assertTrue(str_contains($s, 'Email::$afterSend = '), 'the platform bootstrap assigns Email::$afterSend');
        $this->assertTrue(str_contains($s, "EnvFile::flag('EMAIL_LOG_ENABLED', true)"), 'and honours EMAIL_LOG_ENABLED with default on');
        $this->assertTrue(str_contains($s, 'EmailLogService::log($data)'), 'and hands the mail to EmailLogService');
    }

    public function testCmaBootstrapDoesNotRegisterItAgain(): void
    {
        $s = (string) file_get_contents(__DIR__ . '/../bootstrap.inc');
        $this->assertFalse(str_contains($s, '$afterSend'), 'cma/bootstrap.inc must not register a second hook');
    }
}
