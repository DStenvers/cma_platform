<?php
/**
 * Tests for App\Library\WebConfigCmaRoutes::applyToParent — the fail-safe
 * injector of the CMA friendly-URL rewrite rules into a site's parent
 * web.config.
 *
 * Run with: php tests/TestRunner.php WebConfigCmaRoutesTest
 *
 * Pure filesystem test: every case writes a temp .config, runs applyToParent
 * against it, and asserts on the return shape + the resulting file. No IIS,
 * no bootstrap, no DB (the class is deliberately dependency-free so the
 * Composer Installer can call it before any bootstrap runs).
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\WebConfigCmaRoutes;

class WebConfigCmaRoutesTest extends TestCase
{
    private string $tmpRoot;
    private string $configPath;

    public function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/webconfig-cma-' . bin2hex(random_bytes(6));
        mkdir($this->tmpRoot, 0755, true);
        $this->configPath = $this->tmpRoot . '/web.config';
    }

    public function tearDown(): void
    {
        $this->rmrf($this->tmpRoot);
    }

    private function rmrf(string $path): void
    {
        if (!file_exists($path)) return;
        if (is_file($path) || is_link($path)) { @unlink($path); return; }
        foreach ((array)scandir($path) as $e) {
            if ($e === '.' || $e === '..') continue;
            $this->rmrf($path . '/' . $e);
        }
        @rmdir($path);
    }

    private function write(string $contents): void
    {
        file_put_contents($this->configPath, $contents);
    }

    private function freshConfig(): string
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
             . "<configuration>\n"
             . "    <system.webServer>\n"
             . "        <rewrite>\n"
             . "            <rules>\n"
             . "                <rule name=\"Existing Rule\" stopProcessing=\"true\">\n"
             . "                    <match url=\"^existing/?$\" />\n"
             . "                    <action type=\"Rewrite\" url=\"/index.php\" />\n"
             . "                </rule>\n"
             . "            </rules>\n"
             . "        </rewrite>\n"
             . "    </system.webServer>\n"
             . "</configuration>\n";
    }

    /**
     * The class refuses to touch the file unless ext-simplexml is loaded (it
     * can't validate XML without it, so it returns status 'skipped'). Some CI
     * PHP CLIs are built without simplexml/dom. In that case we can't exercise
     * the real patch logic, so we assert the documented graceful-skip contract
     * instead and short-circuit the rest of the test. Where simplexml IS
     * present (the normal case — consumer sites require it) the full patch,
     * idempotency and error behaviour is verified.
     *
     * @return bool true when the environment has no simplexml and the skip
     *              contract has been asserted (caller should return early).
     */
    private function skipIfNoSimpleXml(): bool
    {
        if (function_exists('simplexml_load_string')) {
            return false;
        }
        $this->write($this->freshConfig());
        $res = WebConfigCmaRoutes::applyToParent($this->configPath);
        $this->assertEquals('skipped', $res['status'], 'without ext-simplexml the class must refuse gracefully');
        $this->assertEmpty($res['errors'], 'a graceful skip is not an error');
        $this->assertNull($res['backup'], 'a skip makes no backup');
        // The file must be left exactly as written — never touched.
        $this->assertEquals($this->freshConfig(), file_get_contents($this->configPath), 'skip must not modify the file');
        return true;
    }

    /** Assert a string parses as well-formed XML. */
    private function assertWellFormed(string $xml, string $context = ''): void
    {
        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $ok = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $this->assertNotEquals(false, $ok, "expected well-formed XML {$context}");
    }

    // -----------------------------------------------------------------------

    public function testFreshConfigGetsRoutesInjected(): void
    {
        if ($this->skipIfNoSimpleXml()) return;
        $this->write($this->freshConfig());

        $res = WebConfigCmaRoutes::applyToParent($this->configPath);

        $this->assertEquals('patched', $res['status'], 'fresh valid config should be patched');
        $this->assertEmpty($res['errors'], 'no errors expected on a clean patch');
        $this->assertNotEmpty($res['messages'], 'a success message should be reported');
        $this->assertNotNull($res['backup'], 'a backup path should be returned');
        $this->assertTrue(is_file($res['backup']), 'the backup file should exist on disk');

        $after = file_get_contents($this->configPath);
        // Marker + the actual CMA rules landed.
        $this->assertStringContainsString(WebConfigCmaRoutes::MARKER, $after);
        $this->assertStringContainsString('name="CMA Dashboard"', $after);
        $this->assertStringContainsString('name="CMA Form with record"', $after);
        // The pre-existing rule is preserved.
        $this->assertStringContainsString('name="Existing Rule"', $after);
        // Result is still well-formed XML.
        $this->assertWellFormed($after, 'after patch');
        // Backup preserves the original (un-patched) content.
        $this->assertStringNotContainsString(WebConfigCmaRoutes::MARKER, file_get_contents($res['backup']));
    }

    public function testSecondRunIsNoopAndDoesNotDuplicate(): void
    {
        if ($this->skipIfNoSimpleXml()) return;
        $this->write($this->freshConfig());

        $first = WebConfigCmaRoutes::applyToParent($this->configPath);
        $this->assertEquals('patched', $first['status']);
        $afterFirst = file_get_contents($this->configPath);

        $second = WebConfigCmaRoutes::applyToParent($this->configPath);

        $this->assertEquals('noop', $second['status'], 'marker already present → noop');
        $this->assertEmpty($second['errors']);
        $this->assertNull($second['backup'], 'a noop makes no backup');

        $afterSecond = file_get_contents($this->configPath);
        $this->assertEquals($afterFirst, $afterSecond, 'a noop must not change the file');
        // Exactly one copy of the rules — no duplication.
        $this->assertEquals(1, substr_count($afterSecond, 'name="CMA Dashboard"'), 'no duplicate CMA Dashboard rule');
        $this->assertWellFormed($afterSecond, 'after idempotent second run');
    }

    public function testMalformedXmlReportsErrorAndLeavesFileIntact(): void
    {
        if ($this->skipIfNoSimpleXml()) return;
        // Valid-ish start but structurally broken (unclosed tag). No marker,
        // no CMA Dashboard rule, so the flow reaches the XML validation gate.
        $broken = "<configuration>\n    <system.webServer>\n        <rewrite>\n            <rules>\n";
        $this->write($broken);

        $res = WebConfigCmaRoutes::applyToParent($this->configPath);

        $this->assertEquals('error', $res['status'], 'malformed XML must be an error');
        $this->assertNotEmpty($res['errors'], 'an error message must be reported');
        $this->assertNull($res['backup'], 'no backup on a refused write');

        // Crucially: the file is NOT corrupted or blanked — left exactly as-is.
        $this->assertEquals($broken, file_get_contents($this->configPath), 'original must be untouched');
        // And no backup/tmp junk was left behind either.
        $this->assertCount(1, glob($this->tmpRoot . '/*'), 'no stray backup/tmp files after a refused write');
    }

    public function testMissingRulesNodeIsHandledGracefully(): void
    {
        if ($this->skipIfNoSimpleXml()) return;
        // Well-formed XML, but there is no <rules> (nor <rewrite>) element.
        $noRules = "<?xml version=\"1.0\"?>\n<configuration>\n    <system.webServer>\n    </system.webServer>\n</configuration>\n";
        $this->write($noRules);

        $res = WebConfigCmaRoutes::applyToParent($this->configPath);

        $this->assertEquals('manual', $res['status'], 'missing <rules> → manual, not a fatal');
        $this->assertEmpty($res['errors'], 'a missing <rules> is not an error, just needs a hand-patch');
        $this->assertNotEmpty($res['messages']);
        $this->assertNull($res['backup']);
        // File untouched.
        $this->assertEquals($noRules, file_get_contents($this->configPath));
    }

    public function testNonExistentPathIsSkippedNotFatal(): void
    {
        $missing = $this->tmpRoot . '/does-not-exist.config';

        $res = WebConfigCmaRoutes::applyToParent($missing);

        $this->assertEquals('skipped', $res['status'], 'a missing file is skipped, never fatal');
        $this->assertEmpty($res['errors']);
        $this->assertNotEmpty($res['messages']);
        $this->assertNull($res['backup']);
        $this->assertFalse(is_file($missing), 'the class must not create the missing file');
    }

    public function testHandAddedRulesJustGetMarkerStamped(): void
    {
        if ($this->skipIfNoSimpleXml()) return;
        // Operator added CMA rules by hand earlier; there's no marker yet.
        // applyToParent should only stamp the idempotency marker, NOT inject a
        // second copy of the rules.
        $handPatched = "<?xml version=\"1.0\"?>\n"
             . "<configuration>\n    <system.webServer>\n        <rewrite>\n            <rules>\n"
             . "                <rule name=\"CMA Dashboard\" stopProcessing=\"true\">\n"
             . "                    <match url=\"^cma/dashboard/?$\" />\n"
             . "                    <action type=\"Rewrite\" url=\"/cma/main.php?page=dashboard.php\" />\n"
             . "                </rule>\n"
             . "            </rules>\n        </rewrite>\n    </system.webServer>\n</configuration>\n";
        $this->write($handPatched);

        $res = WebConfigCmaRoutes::applyToParent($this->configPath);

        $this->assertEquals('patched', $res['status'], 'hand-added rules get the marker stamped');
        $this->assertEmpty($res['errors']);
        $this->assertNotNull($res['backup']);

        $after = file_get_contents($this->configPath);
        $this->assertStringContainsString(WebConfigCmaRoutes::MARKER, $after, 'marker stamped');
        // Still exactly one CMA Dashboard rule — no injected duplicate.
        $this->assertEquals(1, substr_count($after, 'name="CMA Dashboard"'), 'must not inject a duplicate rule block');
        $this->assertWellFormed($after, 'after marker stamp');

        // And now it is idempotent.
        $second = WebConfigCmaRoutes::applyToParent($this->configPath);
        $this->assertEquals('noop', $second['status']);
    }
}
