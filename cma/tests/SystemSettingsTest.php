<?php
/**
 * Tests for Cma\Services\SystemSettings::applyEnvContent — the pure .env
 * transform behind "saveSystemSettings" on cma/preferences.php.
 *
 * Regression: on a site with no .env yet (fresh install, or env supplied by the
 * web server) saving system settings failed with
 * "Fout bij opslaan systeeminstellingen." — updateEnvSetting bailed on the
 * missing file. The transform now seeds from empty content (so the file gets
 * created), and without a phantom leading blank line.
 *
 * Run with: php cma/tests/TestRunner.php SystemSettingsTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/Services/SystemSettings.php';

use Cma\Services\SystemSettings;

class SystemSettingsTest extends TestCase
{
    // The bug: empty content (no .env) must yield a single clean assignment,
    // not "\nKEY=val" with a phantom leading blank line.
    public function testCreatesFromEmptyContentNoLeadingBlank(): void
    {
        $out = SystemSettings::applyEnvContent('', 'PERF_LOG_ENABLED', 'false');
        $this->assertSame('PERF_LOG_ENABLED=false', $out);
    }

    public function testReplacesExistingKeyInPlace(): void
    {
        $in  = "APP_ENVIRONMENT=P\nPERF_LOG_ENABLED=true\nDEBUG=1";
        $out = SystemSettings::applyEnvContent($in, 'PERF_LOG_ENABLED', 'false');
        $this->assertSame("APP_ENVIRONMENT=P\nPERF_LOG_ENABLED=false\nDEBUG=1", $out);
    }

    public function testInsertsAfterExistingLoggingKey(): void
    {
        $in  = "APP_ENVIRONMENT=P\nPERF_LOG_ENABLED=false";
        $out = SystemSettings::applyEnvContent($in, 'CACHE_LOG_ENABLED', 'true');
        $this->assertSame("APP_ENVIRONMENT=P\nPERF_LOG_ENABLED=false\nCACHE_LOG_ENABLED=true", $out);
    }

    public function testAppendsWhenNoLoggingKeyPresent(): void
    {
        $in  = "APP_ENVIRONMENT=P\nDB_HOST=localhost";
        $out = SystemSettings::applyEnvContent($in, 'DEBUG_LOG_ENABLED', 'false');
        $this->assertSame("APP_ENVIRONMENT=P\nDB_HOST=localhost\nDEBUG_LOG_ENABLED=false", $out);
    }

    // The three writes saveSettings() makes must build a clean file from nothing.
    public function testSequentialWritesFromEmpty(): void
    {
        $c = '';
        $c = SystemSettings::applyEnvContent($c, 'PERF_LOG_ENABLED', 'false');
        $c = SystemSettings::applyEnvContent($c, 'CACHE_LOG_ENABLED', 'true');
        $c = SystemSettings::applyEnvContent($c, 'DEBUG_LOG_ENABLED', 'false');
        $this->assertSame(
            "PERF_LOG_ENABLED=false\nCACHE_LOG_ENABLED=true\nDEBUG_LOG_ENABLED=false",
            $c
        );
    }
}
