<?php
/**
 * The environment topic must not describe a registry variable by hand: the
 * registry renders its own tables, and a second description drifts.
 *
 *   php tests/TestRunner.php SettingsDocTest
 */
require_once __DIR__ . '/TestRunner.php';

use App\Library\Settings;

class SettingsDocTest extends TestCase
{
    private function handWrittenEnvTopic(): string
    {
        $src = (string) file_get_contents(__DIR__ . '/../tools/documentation.php');
        $start = strpos($src, "\nfunction render_doc_environment(): void");
        $end = strpos($src, "\nfunction ", $start + 10);
        $this->assertTrue($start !== false && $end !== false, 'render_doc_environment found');
        return substr($src, $start, $end - $start);
    }

    public function testNoHandWrittenRowForARegistryVariable(): void
    {
        preg_match_all('/<tr><td><code>([A-Z][A-Z0-9_]+)<\/code><\/td>/', $this->handWrittenEnvTopic(), $m);
        $registry = array_map(static fn ($d) => $d['env'], Settings::definitions());
        $dupes = array_values(array_intersect(array_unique($m[1]), $registry));
        $this->assertSame([], $dupes, 'hand-written rows duplicate the generated table: ' . implode(', ', $dupes));
    }

    public function testTopicRendersTheGeneratedTables(): void
    {
        $this->assertStringContainsString('cma_doc_render_settings_tables();', $this->handWrittenEnvTopic());
    }

    public function testEveryGroupUsedExists(): void
    {
        $groups = Settings::groups();
        foreach (Settings::definitions() as $key => $def) {
            $this->assertTrue(isset($groups[$def['group']]), "$key: group " . $def['group']);
        }
    }
}
