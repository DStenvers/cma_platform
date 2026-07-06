<?php
/**
 * Tests for App\Library\Server
 *
 * Run with: php tests/TestRunner.php ServerTest
 *
 * Notes on the test environment:
 * - Server::init() caches the document root and allowed paths in private statics
 *   the FIRST time any path method runs, process-wide, with no reset. Other
 *   suites (FileTest, LogTest) already defeat that cache by overwriting the
 *   statics via reflection in their setUp; we follow the same convention so the
 *   tests are deterministic regardless of run order. The document root is pinned
 *   to the cma/ directory (which exists, so realpath() branches behave), and the
 *   allowed-path list is exactly that directory so anything resolving outside it
 *   is a hard denial. Every expected path is derived from getDocumentRoot().
 * - Every $_SERVER touch is saved in setUp() and restored in tearDown().
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Server;

class ServerTest extends TestCase
{
    /** @var array Snapshot of $_SERVER to restore in tearDown */
    private array $serverBackup = [];

    public function setUp(): void
    {
        // Save the superglobal so per-test mutations never leak.
        $this->serverBackup = $_SERVER;

        // Pin Server's cached statics to the cma/ directory via reflection (the
        // pattern used by FileTest/LogTest), defeating the once-only init() cache.
        $root = str_replace('\\', '/', realpath(dirname(__DIR__)));
        $prop = new \ReflectionProperty(Server::class, 'documentRoot');
        $prop->setAccessible(true);
        $prop->setValue(null, $root);
        $allowed = new \ReflectionProperty(Server::class, 'allowedPaths');
        $allowed->setAccessible(true);
        $allowed->setValue(null, [$root]);

        // Relative mapPath() resolves against dirname(SCRIPT_FILENAME); keep it
        // under the document root so the resolved script dir stays allowed.
        $_SERVER['SCRIPT_FILENAME'] = $root . '/tests/TestRunner.php';
    }

    public function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
    }

    // ========================================================================
    // htmlEncode
    // ========================================================================

    public function testHtmlEncodeEmpty(): void
    {
        $this->assertSame('', Server::htmlEncode(''));
    }

    public function testHtmlEncodeNull(): void
    {
        // htmlEncode(?string) explicitly maps null to empty string.
        $this->assertSame('', Server::htmlEncode(null));
    }

    public function testHtmlEncodeAngleBrackets(): void
    {
        $this->assertSame('&lt;a&gt;', Server::htmlEncode('<a>'));
    }

    public function testHtmlEncodeAmpersand(): void
    {
        $this->assertSame('&amp;', Server::htmlEncode('&'));
    }

    public function testHtmlEncodeDoubleQuote(): void
    {
        // ENT_QUOTES encodes double quotes.
        $this->assertSame('&quot;', Server::htmlEncode('"'));
    }

    public function testHtmlEncodeSingleQuote(): void
    {
        // ENT_QUOTES | ENT_HTML5 encodes the single quote as the named &apos;.
        $this->assertSame('&apos;', Server::htmlEncode("'"));
    }

    public function testHtmlEncodeAllSpecials(): void
    {
        $out = Server::htmlEncode('<>&"\'');
        $this->assertStringNotContainsString('<', $out);
        $this->assertStringNotContainsString('>', $out);
        // The literal double/single quote characters must be gone.
        $this->assertStringNotContainsString('"', $out);
        $this->assertStringContainsString('&lt;', $out);
        $this->assertStringContainsString('&amp;', $out);
    }

    public function testHtmlEncodeUnicodePreserved(): void
    {
        // Multibyte UTF-8 text has no special chars, so it survives verbatim.
        $this->assertSame('café €—漢字', Server::htmlEncode('café €—漢字'));
    }

    public function testHtmlEncodePlainText(): void
    {
        $this->assertSame('Hello World 123', Server::htmlEncode('Hello World 123'));
    }

    public function testHtmlEncodeLongString(): void
    {
        $input = str_repeat('<x>&', 5000);
        $out = Server::htmlEncode($input);
        $this->assertStringNotContainsString('<', $out);
        $this->assertGreaterThan(strlen($input), strlen($out));
    }

    public function testHtmlEncodeIsString(): void
    {
        $this->assertIsString(Server::htmlEncode('anything'));
    }

    // ========================================================================
    // htmlDecode
    // ========================================================================

    public function testHtmlDecodeBasic(): void
    {
        $this->assertSame('<b>&"', Server::htmlDecode('&lt;b&gt;&amp;&quot;'));
    }

    public function testHtmlDecodeApos(): void
    {
        // ENT_HTML5 knows the named &apos; entity.
        $this->assertSame("'", Server::htmlDecode('&apos;'));
    }

    public function testHtmlDecodeEmpty(): void
    {
        $this->assertSame('', Server::htmlDecode(''));
    }

    public function testHtmlDecodeNoEntities(): void
    {
        $this->assertSame('plain text', Server::htmlDecode('plain text'));
    }

    public function testHtmlEncodeDecodeRoundTrip(): void
    {
        $original = '<script>alert("x&y") & \'quote\'</script>';
        $this->assertSame($original, Server::htmlDecode(Server::htmlEncode($original)));
    }

    public function testHtmlEncodeDecodeRoundTripUnicode(): void
    {
        $original = 'Ünïcödé <tag> & "quotes" \'apos\' 漢字';
        $this->assertSame($original, Server::htmlDecode(Server::htmlEncode($original)));
    }

    // ========================================================================
    // urlEncode / urlDecode
    // ========================================================================

    public function testUrlEncodeSpace(): void
    {
        // urlencode() uses '+' for spaces.
        $this->assertSame('a+b', Server::urlEncode('a b'));
    }

    public function testUrlEncodeReservedChars(): void
    {
        $this->assertSame('a+b%26c%3Dd', Server::urlEncode('a b&c=d'));
    }

    public function testUrlEncodePlusSign(): void
    {
        $this->assertSame('a%2Bb', Server::urlEncode('a+b'));
    }

    public function testUrlEncodeEmpty(): void
    {
        $this->assertSame('', Server::urlEncode(''));
    }

    public function testUrlDecodeBasic(): void
    {
        $this->assertSame('a b&c=d', Server::urlDecode('a+b%26c%3Dd'));
    }

    public function testUrlDecodePercentSpace(): void
    {
        $this->assertSame('a b', Server::urlDecode('a%20b'));
    }

    public function testUrlDecodeEmpty(): void
    {
        $this->assertSame('', Server::urlDecode(''));
    }

    public function testUrlEncodeDecodeRoundTrip(): void
    {
        $original = 'path/to file?x=1&y=2#frag';
        $this->assertSame($original, Server::urlDecode(Server::urlEncode($original)));
    }

    public function testUrlEncodeDecodeRoundTripUnicode(): void
    {
        $original = 'café €—漢字 & symbols';
        $this->assertSame($original, Server::urlDecode(Server::urlEncode($original)));
    }

    public function testUrlEncodeAlreadyEncodedIsDoubleEncoded(): void
    {
        // Feeding already-encoded text re-encodes the percent signs.
        $this->assertSame('a%2520b', Server::urlEncode('a%20b'));
    }

    public function testUrlEncodeLongString(): void
    {
        $input = str_repeat('a b&', 5000);
        $out = Server::urlEncode($input);
        $this->assertSame($input, Server::urlDecode($out));
    }

    // ========================================================================
    // mapPath
    // ========================================================================

    public function testMapPathAbsoluteExistingFile(): void
    {
        $expected = Server::getDocumentRoot() . '/tests/TestRunner.php';
        $this->assertSame($expected, Server::mapPath('/tests/TestRunner.php'));
    }

    public function testMapPathAbsoluteNonExistentUnderRoot(): void
    {
        // realpath() fails -> normalizePath() keeps it under the doc root -> allowed.
        $expected = Server::getDocumentRoot() . '/no/such/file.txt';
        $this->assertSame($expected, Server::mapPath('/no/such/file.txt'));
    }

    public function testMapPathBackslashesNormalized(): void
    {
        $expected = Server::getDocumentRoot() . '/tests/TestRunner.php';
        $this->assertSame($expected, Server::mapPath('\\tests\\TestRunner.php'));
    }

    public function testMapPathLeadingSlashesStripped(): void
    {
        // ltrim removes the leading slash; extra internal slashes collapse.
        $expected = Server::getDocumentRoot() . '/no/such/file.txt';
        $this->assertSame($expected, Server::mapPath('/no//such/file.txt'));
    }

    public function testMapPathDotDotResolvingInsideIsAllowed(): void
    {
        // '..' is permitted as long as the final resolved path stays inside root.
        $expected = Server::getDocumentRoot() . '/tests/TestRunner.php';
        $this->assertSame($expected, Server::mapPath('/tests/../tests/TestRunner.php'));
    }

    public function testMapPathSingleDotSegmentIgnored(): void
    {
        $expected = Server::getDocumentRoot() . '/no/file.txt';
        $this->assertSame($expected, Server::mapPath('/no/./file.txt'));
    }

    public function testMapPathTraversalOutsideThrows(): void
    {
        $threw = false;
        try {
            Server::mapPath('/../../../etc/passwd');
        } catch (\RuntimeException $e) {
            $threw = true;
            $this->assertStringContainsString('Path traversal detected', $e->getMessage());
        }
        $this->assertTrue($threw, 'Expected mapPath to throw on traversal outside root');
    }

    public function testMapPathTraversalNonExistentOutsideThrows(): void
    {
        $threw = false;
        try {
            Server::mapPath('/../outside-file-that-does-not-exist.txt');
        } catch (\RuntimeException $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected mapPath to throw on ".." resolving outside root');
    }

    public function testMapPathReturnsForwardSlashes(): void
    {
        $result = Server::mapPath('/tests/TestRunner.php');
        $this->assertStringNotContainsString('\\', $result);
    }

    public function testMapPathRelativeUsesScriptDir(): void
    {
        // Relative paths resolve against dirname(SCRIPT_FILENAME).
        $_SERVER['SCRIPT_FILENAME'] = Server::getDocumentRoot() . '/tests/TestRunner.php';
        $expected = Server::getDocumentRoot() . '/tests/rel-file.txt';
        $this->assertSame($expected, Server::mapPath('rel-file.txt'));
    }

    public function testMapPathEmptyStringReturnsRoot(): void
    {
        // Empty absolute-less path resolves to the script directory.
        $_SERVER['SCRIPT_FILENAME'] = Server::getDocumentRoot() . '/tests/TestRunner.php';
        $result = Server::mapPath('');
        $this->assertStringContainsString(Server::getDocumentRoot(), $result);
    }

    // ========================================================================
    // getDocumentRoot
    // ========================================================================

    public function testGetDocumentRootIsString(): void
    {
        $this->assertIsString(Server::getDocumentRoot());
    }

    public function testGetDocumentRootNoTrailingSlash(): void
    {
        $root = Server::getDocumentRoot();
        $this->assertFalse(str_ends_with($root, '/'), 'Document root should not end with a slash');
    }

    public function testGetDocumentRootForwardSlashes(): void
    {
        $this->assertStringNotContainsString('\\', Server::getDocumentRoot());
    }

    // ========================================================================
    // getVar / getAllVars
    // ========================================================================

    public function testGetVarPresent(): void
    {
        $_SERVER['MY_TEST_VAR'] = 'hello';
        $this->assertSame('hello', Server::getVar('MY_TEST_VAR'));
    }

    public function testGetVarMissingReturnsEmptyDefault(): void
    {
        unset($_SERVER['DEFINITELY_MISSING_VAR']);
        $this->assertSame('', Server::getVar('DEFINITELY_MISSING_VAR'));
    }

    public function testGetVarMissingReturnsCustomDefault(): void
    {
        unset($_SERVER['DEFINITELY_MISSING_VAR']);
        $this->assertSame('fallback', Server::getVar('DEFINITELY_MISSING_VAR', 'fallback'));
    }

    public function testGetVarPresentIgnoresDefault(): void
    {
        $_SERVER['MY_TEST_VAR'] = 'present';
        $this->assertSame('present', Server::getVar('MY_TEST_VAR', 'fallback'));
    }

    public function testGetVarEmptyStringValueIsReturned(): void
    {
        // An explicitly-set empty string is a real value, not "missing".
        $_SERVER['EMPTY_VALUE_VAR'] = '';
        $this->assertSame('', Server::getVar('EMPTY_VALUE_VAR', 'fallback'));
    }

    public function testGetVarNonStringDefault(): void
    {
        unset($_SERVER['DEFINITELY_MISSING_VAR']);
        $this->assertSame(42, Server::getVar('DEFINITELY_MISSING_VAR', 42));
    }

    public function testGetAllVarsReturnsArray(): void
    {
        $this->assertIsArray(Server::getAllVars());
    }

    public function testGetAllVarsContainsSetKey(): void
    {
        $_SERVER['CUSTOM_KEY'] = 'x';
        $all = Server::getAllVars();
        $this->assertArrayHasKey('CUSTOM_KEY', $all);
        $this->assertSame('x', $all['CUSTOM_KEY']);
    }

    // ========================================================================
    // getServerName / getServerSoftware
    // ========================================================================

    public function testGetServerNamePresent(): void
    {
        $_SERVER['SERVER_NAME'] = 'example.com';
        $this->assertSame('example.com', Server::getServerName());
    }

    public function testGetServerNameDefault(): void
    {
        unset($_SERVER['SERVER_NAME']);
        $this->assertSame('localhost', Server::getServerName());
    }

    public function testGetServerSoftwarePresent(): void
    {
        $_SERVER['SERVER_SOFTWARE'] = 'nginx/1.25';
        $this->assertSame('nginx/1.25', Server::getServerSoftware());
    }

    public function testGetServerSoftwareDefault(): void
    {
        unset($_SERVER['SERVER_SOFTWARE']);
        $this->assertSame('PHP/' . PHP_VERSION, Server::getServerSoftware());
    }

    // ========================================================================
    // getProtocol / getServerUrl
    // ========================================================================

    public function testGetProtocolHttpsOn(): void
    {
        $_SERVER['HTTPS'] = 'on';
        unset($_SERVER['SERVER_PORT']);
        $this->assertSame('https', Server::getProtocol());
    }

    public function testGetProtocolPort443(): void
    {
        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = 443;
        $this->assertSame('https', Server::getProtocol());
    }

    public function testGetProtocolPlainHttp(): void
    {
        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = 80;
        $this->assertSame('http', Server::getProtocol());
    }

    public function testGetProtocolHttpsOffFallsBackToHttp(): void
    {
        $_SERVER['HTTPS'] = 'off';
        $_SERVER['SERVER_PORT'] = 8080;
        $this->assertSame('http', Server::getProtocol());
    }

    public function testGetServerUrlHttps(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_NAME'] = 'secure.example.com';
        $this->assertSame('https://secure.example.com', Server::getServerUrl());
    }

    public function testGetServerUrlHttp(): void
    {
        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['SERVER_NAME'] = 'plain.example.com';
        $this->assertSame('http://plain.example.com', Server::getServerUrl());
    }

    // ========================================================================
    // setScriptTimeout / getScriptTimeout
    // ========================================================================

    public function testSetScriptTimeoutReturnsTrue(): void
    {
        $this->assertTrue(Server::setScriptTimeout(30));
    }

    public function testSetScriptTimeoutZeroUnlimitedReturnsTrue(): void
    {
        $this->assertTrue(Server::setScriptTimeout(0));
    }

    public function testGetScriptTimeoutIsInt(): void
    {
        $value = Server::getScriptTimeout();
        $this->assertTrue(is_int($value), 'Expected int timeout');
    }

    public function testGetScriptTimeoutNonNegative(): void
    {
        $this->assertTrue(Server::getScriptTimeout() >= 0);
    }

    // ========================================================================
    // createObject (compatibility stub — always throws)
    // ========================================================================

    public function testCreateObjectAlwaysThrows(): void
    {
        $threw = false;
        try {
            Server::createObject('Scripting.FileSystemObject');
        } catch (\RuntimeException $e) {
            $threw = true;
            $this->assertStringContainsString('not supported in PHP', $e->getMessage());
            $this->assertStringContainsString('Scripting.FileSystemObject', $e->getMessage());
        }
        $this->assertTrue($threw, 'createObject must always throw');
    }

    // ========================================================================
    // execute (error-handling path only; success path requires including a file)
    // ========================================================================

    public function testExecuteReturnsFalseOnTraversal(): void
    {
        // mapPath throws -> execute catches and returns false (never includes a file).
        $this->assertFalse(Server::execute('/../../../etc/passwd'));
    }

    public function testExecuteReturnsFalseWhenFileMissing(): void
    {
        // Path is inside the root (allowed) but the file does not exist.
        $this->assertFalse(Server::execute('/no/such/include-target.php'));
    }

    // ========================================================================
    // addAllowedPath (idempotency + no side effect on document root)
    // ========================================================================

    public function testAddAllowedPathIsIdempotentAndSafe(): void
    {
        $rootBefore = Server::getDocumentRoot();
        Server::addAllowedPath('/some/extra/allowed');
        Server::addAllowedPath('/some/extra/allowed'); // second call must not error
        // Document root is unaffected by adding an allowed path.
        $this->assertSame($rootBefore, Server::getDocumentRoot());
    }

    public function testAddAllowedPathTrailingSlashHandled(): void
    {
        // A trailing slash must be tolerated without throwing.
        Server::addAllowedPath('/another/allowed/path/');
        $this->assertIsString(Server::getDocumentRoot());
    }
}
