<?php
/**
 * Tests for App\Library\File::relativePath()
 *
 * This exists because of the invariant it protects, not the string arithmetic:
 * a JSON `$schema` reference resolves against the referencing file's OWN
 * directory. Form definitions are written to two directories at different
 * depths (cma/assets/forms/definitions/ and the site's assets/forms/), so a
 * hardcoded '../schema/…' literal is wrong in one of them — and a wrong
 * reference fails silently: the editor just drops validation, nothing warns.
 *
 * Every case below asserts round-trip resolution: walking the returned path
 * from the source directory must land exactly on the target.
 *
 * Run with: php tests/TestRunner.php FileRelativePathTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\File;

class FileRelativePathTest extends TestCase
{
    /** Resolve '.' / '..' the way a schema-aware editor would, without touching disk. */
    private function resolve(string $path): string
    {
        $out = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($out);
                continue;
            }
            $out[] = $part;
        }
        return '/' . implode('/', $out);
    }

    private function assertResolvesTo(string $fromDir, string $target): void
    {
        $rel = File::relativePath($fromDir, $target);
        $this->assertEquals($target, $this->resolve($fromDir . '/' . $rel), "from={$fromDir} rel={$rel}");
    }

    // ========================================================================
    // The two form-definition locations — the case that broke
    // ========================================================================

    public function testInternalFormDefinitionReachesSchema(): void
    {
        $rel = File::relativePath(
            '/site/cma/assets/forms/definitions',
            '/site/cma/config/schema/form-definition.schema.json'
        );
        $this->assertEquals('../../../config/schema/form-definition.schema.json', $rel);
    }

    public function testAppFormDefinitionReachesSchema(): void
    {
        $rel = File::relativePath(
            '/site/assets/forms',
            '/site/cma/config/schema/form-definition.schema.json'
        );
        $this->assertEquals('../../cma/config/schema/form-definition.schema.json', $rel);
    }

    public function testTheTwoFormLocationsDoNotShareAReference(): void
    {
        // If these ever collapse to the same string the depths have changed and
        // one of the two callers is silently writing a dead reference again.
        $internal = File::relativePath('/site/cma/assets/forms/definitions', '/site/cma/config/schema/x.json');
        $app      = File::relativePath('/site/assets/forms', '/site/cma/config/schema/x.json');
        $this->assertNotEquals($internal, $app);
    }

    // ========================================================================
    // Every directory the repair migration walks
    // ========================================================================

    public function testAllConfigLocationsRoundTrip(): void
    {
        foreach ([
            '/site/data',
            '/site/assets/forms',
            '/site/assets/datastores',
            '/site/cma/config',
            '/site/cma/assets/forms/definitions',
        ] as $dir) {
            $this->assertResolvesTo($dir, '/site/cma/config/schema/databases.schema.json');
        }
    }

    // ========================================================================
    // Path shapes
    // ========================================================================

    public function testTargetInsideSourceNeedsNoParentSteps(): void
    {
        $this->assertEquals(
            'schema/menu.schema.json',
            File::relativePath('/site/cma/config', '/site/cma/config/schema/menu.schema.json')
        );
    }

    public function testSiblingDirectory(): void
    {
        $this->assertEquals('../b/x.json', File::relativePath('/site/a', '/site/b/x.json'));
    }

    public function testTrailingSlashAndDotSegmentsAreNormalised(): void
    {
        $this->assertEquals(
            '../b/x.json',
            File::relativePath('/site/a/', '/site/./a/../b/x.json')
        );
    }

    public function testBackslashesAreAccepted(): void
    {
        $this->assertEquals(
            '../../cma/config/schema/x.json',
            File::relativePath('C:\\site\\assets\\forms', 'C:\\site\\cma\\config\\schema\\x.json')
        );
    }

    public function testIdenticalPathsGiveEmptyString(): void
    {
        $this->assertEquals('', File::relativePath('/site/a', '/site/a'));
    }

    public function testDifferentRootsFallBackToTheTargetPath(): void
    {
        // No common ancestor to walk up to — returning $to unchanged beats
        // emitting a '..' chain that resolves somewhere arbitrary.
        $this->assertEquals('D:/other/x.json', File::relativePath('C:/site/a', 'D:\\other\\x.json'));
        $this->assertEquals('/abs/x.json', File::relativePath('relative/dir', '/abs/x.json'));
    }

    // ========================================================================
    // The values the shipped files actually carry
    // ========================================================================

    public function testShippedFormDefinitionsCarryTheComputedReference(): void
    {
        $dir = dirname(__DIR__, 2) . '/cma/assets/forms/definitions';
        $expected = File::relativePath($dir, dirname(__DIR__, 2) . '/cma/config/schema/form-definition.schema.json');

        $files = glob($dir . '/*.json') ?: [];
        $this->assertTrue(count($files) > 0, 'no bundled form definitions found');

        foreach ($files as $path) {
            $data = json_decode((string)file_get_contents($path), true);
            $ref = is_array($data) ? ($data['$schema'] ?? null) : null;
            if ($ref === null) {
                continue;
            }
            $this->assertEquals($expected, $ref, basename($path));
            $this->assertTrue(is_file($dir . '/' . $ref), basename($path) . ' -> ' . $ref);
        }
    }

    /**
     * Every bundled JSON must reference a schema that lives in the one schema
     * directory. "The file exists" is too weak a bar: a reference that landed
     * on an ordinary JSON document (a form definition of the same name) or on
     * a second, drifted copy of the schemas resolved perfectly well and
     * validated nothing — which is precisely why nobody noticed.
     */
    public function testEveryBundledJsonReferencesTheOneSchemaDirectory(): void
    {
        $root = dirname(__DIR__, 2);
        $schemaDir = realpath($root . '/cma/config/schema');
        $this->assertTrue($schemaDir !== false, 'cma/config/schema/ is missing');

        $checked = 0;
        foreach (['cma', 'cma/config', 'cma/assets/forms/definitions', 'cma/assets/contentblocks'] as $relDir) {
            foreach (glob($root . '/' . $relDir . '/*.json') ?: [] as $path) {
                $data = json_decode((string)file_get_contents($path), true);
                $ref = is_array($data) ? ($data['$schema'] ?? null) : null;
                if ($ref === null || preg_match('#^[a-z][a-z0-9+.-]*://#i', $ref)) {
                    continue;
                }
                $checked++;
                $target = realpath(dirname($path) . '/' . $ref);
                $this->assertTrue($target !== false, $relDir . '/' . basename($path) . ' -> ' . $ref . ' (bestaat niet)');
                $this->assertEquals($schemaDir, dirname((string)$target), $relDir . '/' . basename($path) . ' -> ' . $ref);
            }
        }
        $this->assertTrue($checked > 0, 'no bundled JSON with a local $schema found');
    }

    public function testShippedConfigFilesReferenceAnExistingSchema(): void
    {
        $dir = dirname(__DIR__, 2) . '/cma/config';
        $files = glob($dir . '/*.json') ?: [];
        $this->assertTrue(count($files) > 0, 'no bundled config files found');

        foreach ($files as $path) {
            $data = json_decode((string)file_get_contents($path), true);
            $ref = is_array($data) ? ($data['$schema'] ?? null) : null;
            if ($ref === null || preg_match('#^[a-z][a-z0-9+.-]*://#i', $ref)) {
                continue;
            }
            $this->assertTrue(is_file($dir . '/' . $ref), basename($path) . ' -> ' . $ref);
        }
    }
}
