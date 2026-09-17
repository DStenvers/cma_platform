<?php
/**
 * App\Library\Settings — the one registry for site-wide settings.
 *
 *   php tests/TestRunner.php SettingsTest
 */
require_once __DIR__ . '/TestRunner.php';

use App\Library\Settings;

class SettingsTest extends TestCase
{
    public function tearDown(): void
    {
        foreach (['LIST_PAGE_SIZE', 'DB_CONNECT_TIMEOUT', 'LIST_CACHE_TTL'] as $k) {
            unset($_ENV[$k]);
            putenv($k);
        }
    }

    public function testRegistryIsSharedWithTheAdminScreen(): void
    {
        require_once __DIR__ . '/../classes/Services/SystemSettings.php';
        $this->assertSame(Settings::DEFINITIONS, \Cma\Services\SystemSettings::DEFINITIONS, 'one registry, two readers');
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
    }

    public function testEnvWinsAndIsBounded(): void
    {
        $_ENV['LIST_PAGE_SIZE'] = '200';
        $this->assertSame(200, Settings::get('list_page_size'));
        $_ENV['DB_CONNECT_TIMEOUT'] = '9999';
        $this->assertSame(300, Settings::get('db_connect_timeout'), 'clamped to the registry maximum');
    }

    public function testFallbackFillsInOnlyWhenEnvIsUnset(): void
    {
        $this->assertSame(45, Settings::get('list_cache_ttl', 45), 'an older app.php key stands in for the default');
        $_ENV['LIST_CACHE_TTL'] = '120';
        $this->assertSame(120, Settings::get('list_cache_ttl', 45), 'but the setting wins once it is set');
    }

    public function testClientShapeCarriesTheBrowserSettings(): void
    {
        $c = Settings::forClient();
        $this->assertSame(['listPageSize', 'listScrollBatch', 'exportMaxRows'], array_keys($c));
    }

    public function testEveryEntryHasEnvTypeAndDefault(): void
    {
        foreach (Settings::DEFINITIONS as $key => $def) {
            $this->assertTrue(isset($def['env'], $def['type']) && array_key_exists('default', $def), "$key is complete");
            if ($def['type'] === 'int') {
                $this->assertTrue(isset($def['min'], $def['max']), "$key has bounds");
            }
        }
    }
}
