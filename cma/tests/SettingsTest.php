<?php
/**
 * App\Library\Settings — the one registry for site-wide settings.
 *
 *   php tests/TestRunner.php SettingsTest
 */
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/Services/SystemSettings.php';

use App\Library\Settings;
use Cma\Services\SystemSettings;

class SettingsTest extends TestCase
{
    private $applicationBackup;

    public function setUp(): void
    {
        $this->applicationBackup = $GLOBALS['Application'] ?? null;
        Settings::reset();
    }

    public function tearDown(): void
    {
        foreach (['LIST_PAGE_SIZE', 'DB_CONNECT_TIMEOUT', 'LIST_CACHE_TTL', 'MAIL_HOST', 'X_ON', 'X_SIZES', 'X_RATIO', 'X_MODE'] as $k) {
            unset($_ENV[$k]);
            putenv($k);
        }
        if ($this->applicationBackup === null) {
            unset($GLOBALS['Application']);
        } else {
            $GLOBALS['Application'] = $this->applicationBackup;
        }
        Settings::reset();
    }

    public function testRegistryIsSharedWithTheAdminScreen(): void
    {
        $this->assertSame(Settings::definitions(), SystemSettings::definitions(), 'one registry, two readers');
    }

    public function testDefaultsAreTheValuesTheLiteralsHad(): void
    {
        $this->assertSame(50, Settings::get('list_page_size'));
        $this->assertSame(500, Settings::get('list_scroll_batch'));
        $this->assertSame(800, Settings::get('list_limit'));
        $this->assertSame(50, Settings::get('combo_dynamic_items'));
        $this->assertSame(100, Settings::get('report_preview_rows'));
        $this->assertSame(15000, Settings::get('export_max_rows'));
        $this->assertSame(86400, Settings::get('cache_default_ttl'));
        $this->assertSame(60, Settings::get('list_cache_ttl'));
        $this->assertSame(1800, Settings::get('lookup_cache_ttl'));
        $this->assertSame(300, Settings::get('api_cache_ttl'));
        $this->assertSame(28, Settings::get('asset_cache_days'));
        $this->assertSame(10, Settings::get('db_connect_timeout'));
        $this->assertSame(1000, Settings::get('db_query_timeout'));
        $this->assertSame(30, Settings::get('http_timeout'));
        $this->assertSame(60, Settings::get('http_download_timeout'));
        $this->assertSame(90, Settings::get('llm_timeout'));
        $this->assertSame(300, Settings::get('process_timeout'));
        // Phase 3: the env-backed groups keep the literals their readers had
        $this->assertSame('', Settings::get('sql_log_file'));
        $this->assertFalse(Settings::get('profiler_enabled'));
        $this->assertSame(0, Settings::get('profiler_threshold_ms'));
        $this->assertTrue(Settings::get('cache_enabled'));
        $this->assertSame('auto', Settings::get('cache_backend'));
        $this->assertSame('127.0.0.1', Settings::get('redis_host'));
        $this->assertSame(6379, Settings::get('redis_port'));
        $this->assertSame(2.5, Settings::get('redis_timeout'));
        $this->assertSame('cma_', Settings::get('redis_prefix'));
        $this->assertSame('', Settings::get('llm_provider'));
        $this->assertSame('claude-haiku-4-5', Settings::get('llm_fallback_model'));
        $this->assertSame('anthropic', Settings::get('ocr_vision_provider'));
        $this->assertSame('main', Settings::get('deploy_branch'));
        $this->assertTrue(Settings::get('deploy_migrate'));
        $this->assertSame(['stenversonline/platform'], Settings::get('deploy_composer_update'));
        $this->assertSame('localhost', Settings::get('db_host'));
        $this->assertSame('mysql', Settings::get('db_type'));
        // Phase 4: retention, dashboard and client caps
        $this->assertSame(30, Settings::get('app_log_retention_days'));
        $this->assertSame(7, Settings::get('perf_log_retention_days'));
        $this->assertSame(7, Settings::get('debug_log_retention_days'));
        $this->assertSame(60, Settings::get('notfound_log_retention_days'));
        $this->assertSame(30, Settings::get('email_log_retention_days'));
        $this->assertSame(3600, Settings::get('error_mail_throttle_seconds'));
        $this->assertSame(30, Settings::get('notfound_digest_top'));
        $this->assertSame('', Settings::get('log_min_level'));
        $this->assertSame(100, Settings::get('js_error_rate_limit'));
        $this->assertSame(3600, Settings::get('js_error_rate_window'));
        $this->assertSame(7, Settings::get('dashboard_stats_days'));
        $this->assertSame(14, Settings::get('dashboard_notfound_days'));
        $this->assertSame(20, Settings::get('perf_client_batch_size'));
        $this->assertSame(5000, Settings::get('perf_client_batch_interval'));
        $this->assertSame(200, Settings::get('perf_client_max_queue'));
        $this->assertSame(500, Settings::get('table_filter_max_values'));
        $this->assertSame(30, Settings::get('table_filter_max_checkboxes'));
    }

    public function testCacheKeysFallBackToTheirAppPhpNames(): void
    {
        $GLOBALS['Application'] = ['cma_caching' => false, 'cache_backend' => 'file', 'cache_directory' => '/tmp/x', 'redis_timeout' => '4'];
        $this->assertFalse(Settings::get('cache_enabled'));
        $this->assertSame('file', Settings::get('cache_backend'));
        $this->assertSame('/tmp/x', Settings::get('cache_directory'));
        $this->assertSame(4.0, Settings::get('redis_timeout'));
    }

    public function testEnvWinsAndIsBounded(): void
    {
        $_ENV['LIST_PAGE_SIZE'] = '200';
        $this->assertSame(200, Settings::get('list_page_size'));
        $this->assertSame('env', Settings::source('list_page_size'));
        $_ENV['DB_CONNECT_TIMEOUT'] = '9999';
        $this->assertSame(300, Settings::get('db_connect_timeout'), 'clamped to the registry maximum');
    }

    public function testExplicitFallbackFillsInOnlyWhenEnvIsUnset(): void
    {
        $this->assertSame(45, Settings::get('list_cache_ttl', 45));
        $_ENV['LIST_CACHE_TTL'] = '120';
        $this->assertSame(120, Settings::get('list_cache_ttl', 45), 'the setting wins once it is set');
    }

    public function testAppKeyStandsInForTheDefault(): void
    {
        $GLOBALS['Application'] = ['list_cache_ttl' => '45', 'mail_server' => 'smtp.site.nl'];
        $this->assertSame(45, Settings::get('list_cache_ttl'));
        $this->assertSame('app', Settings::source('list_cache_ttl'));
        $this->assertSame('smtp.site.nl', Settings::get('mail_host'));
        $this->assertFalse(Settings::isSet('mail_host'), 'isSet means the env variable itself');
        $_ENV['MAIL_HOST'] = 'smtp.env.nl';
        $this->assertSame('smtp.env.nl', Settings::get('mail_host'), 'the variable beats app.php');
        $this->assertSame('default', Settings::source('http_timeout'));
    }

    public function testClientShapeCarriesEveryClientSettingCastPerType(): void
    {
        Settings::registerExtra([
            'x_on'    => ['env' => 'X_ON',    'type' => 'bool',  'default' => true,  'group' => 'site', 'label' => 'Aan',   'client' => 'xOn'],
            'x_sizes' => ['env' => 'X_SIZES', 'type' => 'list',  'default' => [1, 2], 'item' => 'int', 'group' => 'site', 'label' => 'Maten', 'client' => 'xSizes'],
            'x_ratio' => ['env' => 'X_RATIO', 'type' => 'float', 'default' => 1.5, 'min' => 0, 'max' => 10, 'group' => 'site', 'label' => 'Ratio', 'client' => 'xRatio'],
        ]);
        $_ENV['X_SIZES'] = '300, 800';
        $c = Settings::forClient();
        foreach (['listPageSize', 'listScrollBatch', 'exportMaxRows'] as $k) {
            $this->assertArrayHasKey($k, $c);
        }
        $this->assertTrue($c['xOn'] === true);
        $this->assertSame([300, 800], $c['xSizes']);
        $this->assertSame(1.5, $c['xRatio']);
        $this->assertFalse(array_key_exists('mail_password', $c) || in_array('mail_password', $c, true));
        $this->assertStringContainsString('window.CMA.settings=', Settings::clientScript());
    }

    public function testEveryEntryIsCompleteAndGroupsExist(): void
    {
        $groups = Settings::groups();
        $clients = [];
        foreach (Settings::definitions() as $key => $def) {
            $this->assertTrue(array_key_exists('default', $def), "$key has a default");
            foreach (['env', 'type', 'group', 'label', 'hint', 'doc'] as $field) {
                $this->assertTrue(isset($def[$field]) && $def[$field] !== '', "$key has $field");
            }
            $this->assertTrue(isset($groups[$def['group']]), "$key: group " . $def['group'] . ' exists');
            if (in_array($def['type'], ['int', 'float'], true)) {
                $this->assertTrue(isset($def['min'], $def['max']), "$key has bounds");
            }
            if (!empty($def['client'])) {
                $this->assertFalse(isset($clients[$def['client']]), "$key: client name unique");
                $clients[$def['client']] = $key;
                $this->assertFalse($def['type'] === 'secret', "$key: no secret in the browser");
            }
        }
    }

    public function testRegisterExtraAcceptsAndRejectsWithReasons(): void
    {
        $rejected = Settings::registerExtra([
            'shop_min'        => ['env' => 'SHOP_MIN', 'type' => 'float', 'default' => 25.0, 'min' => 0, 'max' => 100, 'group' => 'nope', 'label' => 'Min'],
            'list_page_size'  => ['env' => 'OTHER', 'type' => 'int', 'default' => 1, 'min' => 1, 'max' => 2, 'group' => 'site', 'label' => 'x'],
            'dup_env'         => ['env' => 'LIST_PAGE_SIZE', 'type' => 'int', 'default' => 1, 'min' => 1, 'max' => 2, 'group' => 'site', 'label' => 'x'],
            'no_type'         => ['env' => 'NO_TYPE', 'default' => 1, 'group' => 'site', 'label' => 'x'],
            'weird'           => ['env' => 'WEIRD', 'type' => 'colour', 'default' => 1, 'group' => 'site', 'label' => 'x'],
            'leaky'           => ['env' => 'LEAKY', 'type' => 'secret', 'default' => '', 'group' => 'site', 'label' => 'x', 'client' => 'leaky'],
            'Bad Key'         => ['env' => 'BAD', 'type' => 'text', 'default' => '', 'group' => 'site', 'label' => 'x'],
        ], ['shop' => ['caption' => 'Webshop', 'order' => 500]]);
        $defs = Settings::definitions();
        $this->assertTrue(isset($defs['shop_min']), 'a valid entry is accepted');
        $this->assertSame('site', $defs['shop_min']['group'], 'an unknown group lands in "Deze site"');
        $this->assertSame(25.0, Settings::get('shop_min'));
        $this->assertSame(50, Settings::get('list_page_size'), 'the platform entry is untouched');
        foreach (['list_page_size', 'dup_env', 'no_type', 'weird', 'leaky', 'Bad Key'] as $k) {
            $this->assertTrue(isset($rejected[$k]), "$k rejected: " . json_encode($rejected));
        }
        $this->assertSame($rejected, Settings::rejectedExtra());
        $this->assertTrue(isset(Settings::groups()['shop']));
    }

    public function testResetForgetsTheExtras(): void
    {
        Settings::registerExtra(['x_on' => ['env' => 'X_ON', 'type' => 'bool', 'default' => false, 'group' => 'site', 'label' => 'x']]);
        $this->assertTrue(isset(Settings::definitions()['x_on']));
        Settings::reset();
        $this->assertFalse(isset(Settings::definitions()['x_on']));
    }

    public function testSelectAndAppInvertCasts(): void
    {
        Settings::registerExtra([
            'x_mode' => ['env' => 'X_MODE', 'type' => 'select', 'default' => 'a', 'options' => ['a' => 'A', 'b' => 'B'], 'group' => 'site', 'label' => 'Modus'],
            'x_on'   => ['env' => 'X_ON', 'type' => 'bool', 'default' => true, 'group' => 'site', 'label' => 'Aan', 'app' => 'x_off', 'app_invert' => true],
        ]);
        $_ENV['X_MODE'] = 'zzz';
        $this->assertSame('a', Settings::get('x_mode'), 'an unknown option falls back to the default');
        $_ENV['X_MODE'] = 'b';
        $this->assertSame('b', Settings::get('x_mode'));
        $GLOBALS['Application'] = ['x_off' => '1'];
        $this->assertFalse(Settings::get('x_on'), 'app_invert turns the app.php meaning around');
        $_ENV['X_ON'] = 'true';
        $this->assertTrue(Settings::get('x_on'));
    }
}
