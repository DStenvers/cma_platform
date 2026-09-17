<?php
/**
 * Tests for Cma\Services\SystemSettings::applyEnvContent — the pure .env
 * transform behind saving on cma/tools/tools_settings.php.
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

    // The writes save() makes must build a clean file from nothing.
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

    // ---- normalize(): the registry decides how form input becomes .env text ----

    public function testNormalizeBoolAndFlagUseTheReadersSpelling(): void
    {
        $n = SystemSettings::normalize([
            'perf_log_enabled' => 'J',
            'sql_log_enabled'  => 'N',
            'force_debug'      => 'J',
            'cma_debug'        => 'N',
        ]);
        $this->assertSame([], $n['errors']);
        // bool readers use FILTER_VALIDATE_BOOLEAN; flag readers compare with '1'
        $this->assertSame('true',  $n['values']['perf_log_enabled']);
        $this->assertSame('false', $n['values']['sql_log_enabled']);
        $this->assertSame('1',     $n['values']['force_debug']);
        $this->assertSame('0',     $n['values']['cma_debug']);
    }

    public function testNormalizeAcceptsCommaSeparatedAddressesAndTrims(): void
    {
        $n = SystemSettings::normalize(['error_mail_to' => ' a@b.nl , c@d.org ']);
        $this->assertSame([], $n['errors']);
        $this->assertSame('a@b.nl,c@d.org', $n['values']['error_mail_to']);
    }

    public function testNormalizeRejectsInvalidAddressAndWritesNothingForIt(): void
    {
        $n = SystemSettings::normalize(['error_mail_to' => 'a@b.nl, niet-een-adres']);
        $this->assertArrayHasKey('error_mail_to', $n['errors']);
        $this->assertStringContainsString('niet-een-adres', $n['errors']['error_mail_to']);
        $this->assertFalse(array_key_exists('error_mail_to', $n['values']));
    }

    public function testNormalizeEmptyAddressClearsTheSetting(): void
    {
        $n = SystemSettings::normalize(['notfound_mail_to' => '']);
        $this->assertSame([], $n['errors']);
        $this->assertSame('', $n['values']['notfound_mail_to']);
    }

    public function testNormalizeBoundsTheRetentionDays(): void
    {
        $ok = SystemSettings::normalize(['error_log_retention_days' => '30']);
        $this->assertSame('30', $ok['values']['error_log_retention_days']);
        $low = SystemSettings::normalize(['error_log_retention_days' => '0']);
        $this->assertArrayHasKey('error_log_retention_days', $low['errors']);
        $text = SystemSettings::normalize(['error_log_retention_days' => 'zeven']);
        $this->assertArrayHasKey('error_log_retention_days', $text['errors']);
    }

    public function testNormalizeIgnoresUnknownKeys(): void
    {
        $n = SystemSettings::normalize(['iets_anders' => 'J']);
        $this->assertSame([], $n['values']);
        $this->assertSame([], $n['errors']);
    }

    // ---- get(): defaults when unset, typed when set ----

    public function testGetFallsBackToTheRegistryDefault(): void
    {
        unset($_ENV['ERROR_MAIL_ENABLED'], $_ENV['ERROR_LOG_RETENTION_DAYS']);
        putenv('ERROR_MAIL_ENABLED');
        putenv('ERROR_LOG_RETENTION_DAYS');
        $this->assertFalse(SystemSettings::get('error_mail_enabled'));
        $this->assertSame(7, SystemSettings::get('error_log_retention_days'));
    }

    public function testGetReadsTypedValuesFromEnv(): void
    {
        $_ENV['ERROR_MAIL_ENABLED'] = 'true';
        $_ENV['ERROR_MAIL_TO'] = ' x@y.nl ';
        $_ENV['ERROR_LOG_RETENTION_DAYS'] = '900';
        $_ENV['FORCE_DEBUG'] = '1';
        try {
            $this->assertTrue(SystemSettings::get('error_mail_enabled'));
            $this->assertSame('x@y.nl', SystemSettings::get('error_mail_to'));
            $this->assertSame(365, SystemSettings::get('error_log_retention_days'));
            $this->assertTrue(SystemSettings::get('force_debug'));
        } finally {
            unset($_ENV['ERROR_MAIL_ENABLED'], $_ENV['ERROR_MAIL_TO'], $_ENV['ERROR_LOG_RETENTION_DAYS'], $_ENV['FORCE_DEBUG']);
        }
    }

    // ---- text / secret: the mail server settings ----

    public function testTextIsTrimmedAndQuotedOnlyWhenNeeded(): void
    {
        $n = SystemSettings::normalize(['mail_host' => ' smtp.example.nl ', 'mail_username' => 'user name']);
        $this->assertSame([], $n['errors']);
        $this->assertSame('smtp.example.nl', $n['values']['mail_host']);
        $this->assertSame('"user name"', $n['values']['mail_username']);
    }

    public function testSecretEmptyKeepsTheStoredValue(): void
    {
        $n = SystemSettings::normalize(['mail_password' => '']);
        $this->assertSame([], $n['errors']);
        $this->assertFalse(array_key_exists('mail_password', $n['values']), 'an empty password submission writes nothing');
    }

    public function testSecretWithHashAndQuotesSurvivesTheEnvRoundTrip(): void
    {
        $raw = 'p#ss "wo\\rd" x';
        $n = SystemSettings::normalize(['mail_password' => $raw]);
        $line = SystemSettings::applyEnvContent('', 'MAIL_PASSWORD', $n['values']['mail_password']);
        $parsed = \App\Library\EnvFile::parse($line);
        $this->assertSame($raw, $parsed['MAIL_PASSWORD'], 'what the page saves is what Email reads back');
    }

    public function testOptionalPortAcceptsEmptyAndBounds(): void
    {
        $this->assertSame('', SystemSettings::normalize(['mail_port' => ''])['values']['mail_port']);
        $this->assertSame('587', SystemSettings::normalize(['mail_port' => '587'])['values']['mail_port']);
        $this->assertArrayHasKey('mail_port', SystemSettings::normalize(['mail_port' => '70000'])['errors']);
    }

    public function testTextRejectsLineBreaks(): void
    {
        $n = SystemSettings::normalize(['mail_host' => "smtp\nevil"]);
        $this->assertArrayHasKey('mail_host', $n['errors']);
    }

    // ---- float / list / select / requires ----

    public function testFloatListAndSelectNormalize(): void
    {
        \App\Library\Settings::reset();
        \App\Library\Settings::registerExtra([
            'x_ratio'  => ['env' => 'X_RATIO',  'type' => 'float',  'default' => 1.5, 'min' => 0, 'max' => 10, 'group' => 'site', 'label' => 'Ratio'],
            'x_sizes'  => ['env' => 'X_SIZES',  'type' => 'list',   'default' => [1, 2], 'item' => 'int', 'group' => 'site', 'label' => 'Maten'],
            'x_mode'   => ['env' => 'X_MODE',   'type' => 'select', 'default' => 'a', 'options' => ['a' => 'A', 'b' => 'B'], 'group' => 'site', 'label' => 'Modus'],
        ]);
        try {
            $n = SystemSettings::normalize(['x_ratio' => '2,5', 'x_sizes' => ' 300, 800 ,1200', 'x_mode' => 'b']);
            $this->assertSame([], $n['errors']);
            $this->assertSame('2.5', $n['values']['x_ratio']);
            $this->assertSame('300,800,1200', $n['values']['x_sizes']);
            $this->assertSame('b', $n['values']['x_mode']);
            $bad = SystemSettings::normalize(['x_ratio' => '11', 'x_sizes' => '3,x', 'x_mode' => 'z']);
            $this->assertArrayHasKey('x_ratio', $bad['errors']);
            $this->assertArrayHasKey('x_sizes', $bad['errors']);
            $this->assertArrayHasKey('x_mode', $bad['errors']);
        } finally {
            \App\Library\Settings::reset();
        }
    }

    public function testRequiresRejectsASwitchWithoutItsCompanion(): void
    {
        unset($_ENV['ERROR_MAIL_TO']);
        putenv('ERROR_MAIL_TO');
        $n = SystemSettings::normalize(['error_mail_enabled' => 'J', 'error_mail_to' => '']);
        $this->assertArrayHasKey('error_mail_to', $n['errors']);
        $this->assertStringContainsString('Fouten mailen staat aan', $n['errors']['error_mail_to']);
        $ok = SystemSettings::normalize(['error_mail_enabled' => 'J', 'error_mail_to' => 'a@b.nl']);
        $this->assertSame([], $ok['errors']);
        $off = SystemSettings::normalize(['error_mail_enabled' => 'N', 'error_mail_to' => '']);
        $this->assertSame([], $off['errors']);
    }

    public function testRequiresLooksAtTheStoredValueWhenTheCompanionIsNotSubmitted(): void
    {
        $_ENV['ERROR_MAIL_TO'] = 'stored@b.nl';
        try {
            $n = SystemSettings::normalize(['error_mail_enabled' => 'J']);
            $this->assertSame([], $n['errors']);
        } finally {
            unset($_ENV['ERROR_MAIL_TO']);
        }
    }

    public function testGroupedDefinitionsFollowGroupOrderAndSkipHidden(): void
    {
        $groups = SystemSettings::groupedDefinitions();
        $this->assertSame('notifications', array_key_first($groups));
        foreach ($groups as $g) {
            foreach ($g['rows'] as $def) {
                $this->assertFalse(!empty($def['hidden']));
            }
        }
    }
}
