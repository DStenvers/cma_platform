<?php
/**
 * Unit tests for App\Library\Debug (var / recordset dump helpers).
 *
 * Debug keeps global state in private static flags (enabled, toFile, toScreen,
 * jsonMode, fileName). setUp() snapshots all of them via reflection and
 * tearDown() restores them, so these tests never leak state into the rest of
 * the suite. The log filename has no public setter, so reflection is also used
 * to point file output at a temp file we create and clean up.
 *
 * Output-emitting methods are captured with ob_start()/ob_get_clean().
 *
 * Run with: php tests/TestRunner.php DebugTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Debug;

class DebugTest extends TestCase
{
    /** @var array<string,mixed> Snapshot of Debug's private static state. */
    private array $saved = [];
    private string $tempFile = '';

    private const PROPS = ['enabled', 'toFile', 'toScreen', 'jsonMode', 'fileName'];

    private function getProp(string $name)
    {
        $ref = new ReflectionProperty(Debug::class, $name);
        $ref->setAccessible(true);
        return $ref->getValue();
    }

    private function setProp(string $name, $value): void
    {
        $ref = new ReflectionProperty(Debug::class, $name);
        $ref->setAccessible(true);
        $ref->setValue(null, $value);
    }

    public function setUp(): void
    {
        foreach (self::PROPS as $p) {
            $this->saved[$p] = $this->getProp($p);
        }
        $this->tempFile = sys_get_temp_dir() . '/debug_test_' . uniqid() . '.log';
    }

    public function tearDown(): void
    {
        foreach (self::PROPS as $p) {
            $this->setProp($p, $this->saved[$p]);
        }
        if ($this->tempFile !== '' && is_file($this->tempFile)) {
            @unlink($this->tempFile);
        }
    }

    /** Run a callable and return everything it echoed. */
    private function capture(callable $fn): string
    {
        ob_start();
        $fn();
        return ob_get_clean();
    }

    // --- getActive() / setActive() --------------------------------------

    public function testSetActiveToggles(): void
    {
        Debug::setActive(true);
        $this->assertTrue(Debug::getActive());
        Debug::setActive(false);
        $this->assertFalse(Debug::getActive());
    }

    // --- write(): inactive emits nothing --------------------------------

    public function testWriteInactiveEmitsNothing(): void
    {
        Debug::setActive(false);
        $out = $this->capture(fn() => Debug::write('should-not-appear'));
        $this->assertEquals('', $out);
    }

    // --- write(): console (default) path --------------------------------

    public function testWriteActiveConsoleContainsMessage(): void
    {
        Debug::setActive(true);
        Debug::setToFile(false);
        Debug::setToScreen(false);
        Debug::setJsonMode(false);
        $out = $this->capture(fn() => Debug::write('hello-console'));
        $this->assertStringContainsString('console.log', $out);
        $this->assertStringContainsString('hello-console', $out);
    }

    public function testWriteConsolePayloadIsValidJson(): void
    {
        Debug::setActive(true);
        Debug::setToFile(false);
        Debug::setToScreen(false);
        Debug::setJsonMode(false);
        $out = $this->capture(fn() => Debug::write('json-me'));

        // Extract the argument passed to console.log(...) and validate it as JSON.
        $this->assertMatchesRegularExpression('/console\.log\((.*)\);<\/script>/', $out);
        preg_match('/console\.log\((.*)\);<\/script>/', $out, $m);
        $decoded = json_decode($m[1]);
        $this->assertTrue(json_last_error() === JSON_ERROR_NONE, 'console.log payload is not valid JSON');
        $this->assertIsString($decoded);
        $this->assertStringContainsString('json-me', $decoded);
    }

    // --- write(): screen path -------------------------------------------

    public function testWriteToScreenEmitsHtml(): void
    {
        Debug::setActive(true);
        Debug::setToFile(false);
        Debug::setToScreen(true);
        Debug::setJsonMode(false);
        $out = $this->capture(fn() => Debug::write('screen-msg'));
        $this->assertStringContainsString('screen-msg', $out);
        $this->assertStringContainsString('<br>', $out);
        // Screen path does not use the console.log wrapper.
        $this->assertStringNotContainsString('console.log', $out);
    }

    // --- write(): file path ---------------------------------------------

    public function testWriteToFileWritesToFileNotScreen(): void
    {
        Debug::setActive(true);
        Debug::setToScreen(false);
        Debug::setJsonMode(false);
        Debug::setToFile(true);
        $this->setProp('fileName', $this->tempFile);

        $out = $this->capture(fn() => Debug::write('file-marker'));

        $this->assertEquals('', $out, 'toFile mode must not echo to screen');
        $this->assertTrue(is_file($this->tempFile), 'log file was not created');
        $this->assertStringContainsString('file-marker', file_get_contents($this->tempFile));
    }

    // --- write(): json mode redirects to file, suppresses screen --------

    public function testJsonModeSuppressesScreenAndWritesFile(): void
    {
        Debug::setActive(true);
        Debug::setToFile(false);
        Debug::setToScreen(true); // even with screen on, jsonMode wins
        Debug::setJsonMode(true);
        $this->setProp('fileName', $this->tempFile);

        $out = $this->capture(fn() => Debug::write('json-mode-marker'));

        $this->assertEquals('', $out, 'jsonMode must not echo to screen');
        $this->assertTrue(is_file($this->tempFile));
        $this->assertStringContainsString('json-mode-marker', file_get_contents($this->tempFile));
    }

    // --- array() --------------------------------------------------------

    public function testArrayInactiveEmitsNothing(): void
    {
        Debug::setActive(false);
        $out = $this->capture(fn() => Debug::array(['a', 'b']));
        $this->assertEquals('', $out);
    }

    public function testArrayActiveDumpsValues(): void
    {
        Debug::setActive(true);
        $out = $this->capture(fn() => Debug::array(['alpha', 'beta']));
        $this->assertStringContainsString('alpha', $out);
        $this->assertStringContainsString('beta', $out);
        $this->assertStringContainsString('<hr>', $out);
    }

    public function testArrayEscapesValues(): void
    {
        Debug::setActive(true);
        $out = $this->capture(fn() => Debug::array(['<x>']));
        $this->assertStringContainsString('&lt;x&gt;', $out);
        $this->assertStringNotContainsString('<x>', $out);
    }

    public function testArrayWithNonArrayReportsSo(): void
    {
        Debug::setActive(true);
        $out = $this->capture(fn() => Debug::array('not-an-array'));
        $this->assertStringContainsString('This is not an array', $out);
    }

    // --- multiArray() ---------------------------------------------------

    public function testMultiArrayActive(): void
    {
        Debug::setActive(true);
        $out = $this->capture(fn() => Debug::multiArray([['a', 'b'], ['c', 'd']]));
        $this->assertStringContainsString('Debug MultiArray', $out);
        $this->assertStringContainsString('a - b', $out);
        $this->assertStringContainsString('c - d', $out);
    }

    public function testMultiArrayInactiveEmitsNothing(): void
    {
        Debug::setActive(false);
        $out = $this->capture(fn() => Debug::multiArray([['a', 'b']]));
        $this->assertEquals('', $out);
    }

    // --- collection() ---------------------------------------------------

    public function testCollectionActive(): void
    {
        Debug::setActive(true);
        $out = $this->capture(fn() => Debug::collection(['k1' => 'v1', 'k2' => 'v2']));
        $this->assertStringContainsString('Collection dump', $out);
        $this->assertStringContainsString('k1 = v1', $out);
        $this->assertStringContainsString('k2 = v2', $out);
    }

    public function testCollectionInactiveEmitsNothing(): void
    {
        Debug::setActive(false);
        $out = $this->capture(fn() => Debug::collection(['k' => 'v']));
        $this->assertEquals('', $out);
    }

    // --- postContent() / paramContent() / cookiesContent() --------------
    // These build HTML from superglobals and always return a string.

    public function testPostContentEscapesValues(): void
    {
        $savedPost = $_POST;
        $_POST = ['field' => '<script>'];
        try {
            $html = Debug::postContent();
        } finally {
            $_POST = $savedPost;
        }
        $this->assertStringContainsString('Form POST', $html);
        $this->assertStringContainsString('field', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testParamContentEscapesValues(): void
    {
        $savedGet = $_GET;
        $_GET = ['q' => '<b>'];
        try {
            $html = Debug::paramContent();
        } finally {
            $_GET = $savedGet;
        }
        $this->assertStringContainsString('Parameters', $html);
        $this->assertStringContainsString('&lt;b&gt;', $html);
    }

    public function testCookiesContentEscapesValues(): void
    {
        $savedCookie = $_COOKIE;
        $_COOKIE = ['sid' => '<evil>'];
        try {
            $html = Debug::cookiesContent();
        } finally {
            $_COOKIE = $savedCookie;
        }
        $this->assertStringContainsString('Cookies', $html);
        $this->assertStringContainsString('&lt;evil&gt;', $html);
    }

    // --- settingsWrite() ------------------------------------------------

    public function testSettingsWriteOff(): void
    {
        Debug::setActive(false);
        $out = $this->capture(fn() => Debug::settingsWrite());
        $this->assertStringContainsString('Debug: OFF', $out);
    }

    public function testSettingsWriteOnConsole(): void
    {
        Debug::setActive(true);
        Debug::setToFile(false);
        Debug::setToScreen(false);
        $out = $this->capture(fn() => Debug::settingsWrite());
        $this->assertStringContainsString('Debug: ON', $out);
        $this->assertStringContainsString('to console', $out);
    }

    public function testSettingsWriteOnScreen(): void
    {
        Debug::setActive(true);
        Debug::setToFile(false);
        Debug::setToScreen(true);
        $out = $this->capture(fn() => Debug::settingsWrite());
        $this->assertStringContainsString('to screen', $out);
    }

    // --- recordset() (object with fetchAll) -----------------------------

    private function makeRsStub(array $rows): object
    {
        return new class($rows) {
            private array $rows;
            public function __construct(array $rows) { $this->rows = $rows; }
            public function fetchAll($mode = null) { return $this->rows; }
        };
    }

    public function testRecordsetDumpsFields(): void
    {
        Debug::setActive(true);
        Debug::setToFile(false);
        Debug::setToScreen(true);
        Debug::setJsonMode(false);
        $rs = $this->makeRsStub([['id' => 1, 'name' => 'Alpha']]);
        $out = $this->capture(fn() => Debug::recordset($rs));
        $this->assertStringContainsString('id =&gt; 1', $out);
        $this->assertStringContainsString('name =&gt; Alpha', $out);
        $this->assertStringContainsString('End of record', $out);
    }

    public function testRecordsetEmptyObject(): void
    {
        Debug::setActive(true);
        Debug::setToFile(false);
        Debug::setToScreen(true);
        Debug::setJsonMode(false);
        $rs = $this->makeRsStub([]);
        $out = $this->capture(fn() => Debug::recordset($rs));
        $this->assertStringContainsString('Recordset is empty', $out);
    }

    public function testRecordsetInactiveEmitsNothing(): void
    {
        Debug::setActive(false);
        $rs = $this->makeRsStub([['id' => 1]]);
        $out = $this->capture(fn() => Debug::recordset($rs));
        $this->assertEquals('', $out);
    }

    // --- fullRecordset() (object with fetchAll) -------------------------

    public function testFullRecordsetRendersTable(): void
    {
        Debug::setActive(true);
        $rs = $this->makeRsStub([
            ['id' => 1, 'name' => 'Alpha'],
            ['id' => 2, 'name' => 'Beta'],
        ]);
        $out = $this->capture(fn() => Debug::fullRecordset($rs));
        $this->assertStringContainsString('<table', $out);
        $this->assertStringContainsString('<th>id</th>', $out);
        $this->assertStringContainsString('<th>name</th>', $out);
        $this->assertStringContainsString('Alpha', $out);
        $this->assertStringContainsString('Beta', $out);
    }

    public function testFullRecordsetEmptyObject(): void
    {
        Debug::setActive(true);
        $rs = $this->makeRsStub([]);
        $out = $this->capture(fn() => Debug::fullRecordset($rs));
        $this->assertStringContainsString('Empty recordset', $out);
    }

    public function testFullRecordsetInactiveEmitsNothing(): void
    {
        Debug::setActive(false);
        $rs = $this->makeRsStub([['id' => 1]]);
        $out = $this->capture(fn() => Debug::fullRecordset($rs));
        $this->assertEquals('', $out);
    }
}
