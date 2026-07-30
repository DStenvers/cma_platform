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

    /**
     * A connection the probe can actually open, so the databases-specific
     * connection check does not need a real Access file.
     */
    private function entry(int $id, string $name): array
    {
        return ['id' => $id, 'name' => $name, 'type' => 'sqlite', 'connectionString' => 'sqlite::memory:'];
    }

    public function testSchemaPathFollowsRenamesAndAliases(): void
    {
        $this->assertStringContainsString('cma_branding.schema.json', (string)ConfigLoader::schemaPath('app'));
        $this->assertStringContainsString('cma_reports.schema.json', (string)ConfigLoader::schemaPath('reports'));
        // menu was renamed to cma_menu, but its schema kept the old name.
        $this->assertStringContainsString('menu.schema.json', (string)ConfigLoader::schemaPath('menu'));
        $this->assertStringContainsString('migrations.schema.json', (string)ConfigLoader::schemaPath('migrations'));
        $this->assertStringContainsString('contentblocks.schema.json', (string)ConfigLoader::schemaPath('../assets/contentblocks/contentblocks'));
        // tools was renamed to cma_tools; both names must find the schema.
        $this->assertStringContainsString('cma_tools.schema.json', (string)ConfigLoader::schemaPath('tools'));
        $this->assertStringContainsString('cma_tools.schema.json', (string)ConfigLoader::schemaPath('cma_tools'));
        $this->assertNull(ConfigLoader::schemaPath('no_such_config'));
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
        // No schema for this name; anything structurally valid must save.
        $this->assertTrue(ConfigLoader::save('no_such_config', ['whatever' => ['a', 'b']]));
        $this->assertSame(['a', 'b'], $this->stored('no_such_config')['whatever']);
    }

    public function testOnlyOneDatabaseMayBeTheDefault(): void
    {
        $this->sandbox();
        $one = $this->entry(1, 'data');
        $one['default'] = true;
        $two = $this->entry(2, 'users');
        $two['default'] = true;

        $this->assertTrue(ConfigLoader::save('databases', ['databases' => [$one, $this->entry(2, 'users')]]));

        $this->assertFalse(ConfigLoader::save('databases', ['databases' => [$one, $two]]));
        $this->assertStringContainsString('default', implode(' ', ConfigLoader::lastErrors()));
    }

    public function testChangedConnectionThatCannotBeOpenedIsRefused(): void
    {
        $this->sandbox();
        ConfigLoader::save('databases', ['databases' => [$this->entry(1, 'data')]]);

        $broken = $this->entry(1, 'data');
        $broken['type'] = 'access';
        $broken['connectionString'] = 'odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=' . $this->dir . '/nope.mdb';

        $this->assertFalse(ConfigLoader::save('databases', ['databases' => [$broken]]));
        $reported = implode(' | ', ConfigLoader::lastErrors());
        $this->assertStringContainsString('data', $reported);
        $this->assertStringContainsString('nope.mdb', $reported);
        $this->assertSame('sqlite::memory:', $this->stored()['databases'][0]['connectionString']);
    }

    public function testAnAlreadyBrokenConnectionDoesNotBlockEditingAnother(): void
    {
        $this->sandbox();
        $broken = [
            'id' => 9, 'name' => 'rep', 'type' => 'access',
            'connectionString' => 'odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=' . $this->dir . '/gone.mdb',
        ];
        file_put_contents(
            $this->dir . '/databases.json',
            json_encode(['databases' => [$broken, $this->entry(1, 'data')]])
        );

        // Touch only 'data'; the untouched broken 'rep' must not block the save.
        $save = ConfigLoader::save('databases', ['databases' => [$broken, $this->entry(1, 'data') + ['title' => 'Hoofddatabase']]]);
        $this->assertTrue($save, implode(' | ', ConfigLoader::lastErrors()));
        $this->assertSame('Hoofddatabase', $this->stored()['databases'][1]['title']);
    }

    public function testDeprecatedAndEmptyConnectionsAreNotProbed(): void
    {
        $this->sandbox();
        $deprecated = [
            'id' => 9, 'name' => 'rep', 'type' => 'access', 'deprecated' => true,
            'connectionString' => 'odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=' . $this->dir . '/gone.mdb',
        ];
        $unconfigured = ['id' => 3, 'name' => 'users', 'type' => 'access', 'connectionString' => ''];

        $this->assertTrue(
            ConfigLoader::save('databases', ['databases' => [$deprecated, $unconfigured, $this->entry(1, 'data')]]),
            implode(' | ', ConfigLoader::lastErrors())
        );
    }
}
