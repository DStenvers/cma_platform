<?php
/**
 * Tests for Cma\ConfigLoader::save() — schema validation at write time, the
 * no-regression rule that keeps an already-broken config editable, and the
 * temp-file + .bak write.
 *
 * Run with: php cma/tests/TestRunner.php ConfigLoaderSaveValidationTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/ConfigLoader.php';

use Cma\ConfigLoader;

class ConfigLoaderSaveValidationTest extends TestCase
{
    private string $dir = '';

    private function sandbox(): string
    {
        $this->dir = sys_get_temp_dir() . '/cfgsave-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0755, true);
        ConfigLoader::setConfigPath($this->dir);
        return $this->dir;
    }

    private function stored(string $name = 'databases'): array
    {
        return json_decode((string)file_get_contents($this->dir . '/' . $name . '.json'), true);
    }

    private function entry(int $id, string $name): array
    {
        return ['id' => $id, 'name' => $name, 'type' => 'access', 'connectionString' => 'odbc:x'];
    }

    public function testSchemaPathFollowsRenamesAndAliases(): void
    {
        $this->assertStringContainsString('cma_branding.schema.json', (string)ConfigLoader::schemaPath('app'));
        $this->assertStringContainsString('cma_reports.schema.json', (string)ConfigLoader::schemaPath('reports'));
        // menu was renamed to cma_menu, but its schema kept the old name.
        $this->assertStringContainsString('menu.schema.json', (string)ConfigLoader::schemaPath('menu'));
        $this->assertStringContainsString('migrations.schema.json', (string)ConfigLoader::schemaPath('migrations'));
        $this->assertStringContainsString('contentblocks.schema.json', (string)ConfigLoader::schemaPath('../assets/contentblocks/contentblocks'));
        $this->assertNull(ConfigLoader::schemaPath('cma_tools'));
    }

    public function testValidConfigIsWritten(): void
    {
        $this->sandbox();
        $this->assertTrue(ConfigLoader::save('databases', ['databases' => [$this->entry(6, 'data')]]));
        $this->assertSame([], ConfigLoader::lastErrors());
        $this->assertSame('data', $this->stored()['databases'][0]['name']);
    }

    public function testMissingRequiredKeyIsRefused(): void
    {
        $this->sandbox();
        $this->assertFalse(ConfigLoader::save('databases', ['version' => '2.0.0']));
        $errors = ConfigLoader::lastErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('databases', $errors[0]);
        $this->assertFalse(file_exists($this->dir . '/databases.json'), 'refused save must not create the file');
    }

    public function testIntroducedViolationIsRefusedAndLeavesFileIntact(): void
    {
        $this->sandbox();
        ConfigLoader::save('databases', ['databases' => [$this->entry(6, 'data')]]);

        $this->assertFalse(ConfigLoader::save('databases', ['databases' => [['id' => 'six', 'name' => 'data']]]));
        $reported = implode(' | ', ConfigLoader::lastErrors());
        $this->assertStringContainsString('databases[0]', $reported);
        $this->assertStringContainsString('moet integer zijn', $reported);
        $this->assertSame(6, $this->stored()['databases'][0]['id'], 'stored config must be untouched');
    }

    public function testPreExistingViolationStaysEditable(): void
    {
        $this->sandbox();
        // A config that already breaks its schema — as shipped configs did
        // before the schemas were brought in line with reality.
        file_put_contents(
            $this->dir . '/databases.json',
            json_encode(['databases' => [['id' => 'six', 'name' => 'data']]])
        );

        $save = ConfigLoader::save('databases', ['databases' => [
            ['id' => 'six', 'name' => 'data'],
            $this->entry(7, 'users'),
        ]]);

        $this->assertTrue($save, 'a save that adds no new violation must go through: ' . implode(' | ', ConfigLoader::lastErrors()));
        $this->assertCount(2, $this->stored()['databases']);
    }

    public function testPreviousVersionIsKeptAsBak(): void
    {
        $this->sandbox();
        ConfigLoader::save('databases', ['databases' => [$this->entry(6, 'data')]]);
        ConfigLoader::save('databases', ['databases' => [$this->entry(6, 'data'), $this->entry(7, 'users')]]);

        $bak = $this->dir . '/databases.json.bak';
        $this->assertTrue(file_exists($bak), '.bak should hold the version before the last save');
        $previous = json_decode((string)file_get_contents($bak), true);
        $this->assertCount(1, $previous['databases']);
        $this->assertCount(2, $this->stored()['databases']);
    }

    public function testNoTempFileIsLeftBehind(): void
    {
        $this->sandbox();
        ConfigLoader::save('databases', ['databases' => [$this->entry(6, 'data')]]);
        $this->assertFalse(file_exists($this->dir . '/databases.json.tmp'));
    }

    public function testConfigWithoutSchemaIsSavedUnchecked(): void
    {
        $this->sandbox();
        // cma_tools.json has no schema; anything structurally valid must save.
        $this->assertTrue(ConfigLoader::save('cma_tools', ['whatever' => ['a', 'b']]));
        $this->assertSame(['a', 'b'], $this->stored('cma_tools')['whatever']);
    }
}
