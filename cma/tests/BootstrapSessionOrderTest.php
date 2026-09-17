<?php
/**
 * The session starts AFTER .env is loaded, so its cookie can read the
 * SESSION_COOKIE_* settings — and still before app.php and Application_OnStart,
 * where site code may already touch the session. Source-order check: the
 * bootstrap needs a site to run.
 *
 *   php tests/TestRunner.php BootstrapSessionOrderTest
 */
require_once __DIR__ . '/TestRunner.php';

class BootstrapSessionOrderTest extends TestCase
{
    public function testInitSessionRunsAfterDotenvAndBeforeApplication(): void
    {
        $src = (string) file_get_contents(__DIR__ . '/../../src/helpers/Bootstrap.php');
        $init = strpos($src, 'public static function init(');
        $dotenv = strpos($src, 'self::loadDotenv();', $init);
        $session = strpos($src, 'self::initSession();', $init);
        $app = strpos($src, 'self::initApplication();', $init);
        $this->assertTrue($dotenv !== false && $session !== false && $app !== false, 'all three calls found in init()');
        $this->assertTrue($dotenv < $session, 'initSession() after loadDotenv()');
        $this->assertTrue($session < $app, 'initSession() before initApplication()');
        $this->assertStringContainsString("Settings::get('session_cookie_samesite')", $src, 'the cookie params come from the settings');
    }
}
