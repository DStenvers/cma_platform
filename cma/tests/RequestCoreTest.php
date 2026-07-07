<?php
/**
 * Comprehensive tests for App\Library\Request.
 *
 * Run with: php tests/TestRunner.php RequestCoreTest
 *
 * The class is a thin, pure wrapper over the request superglobals
 * ($_GET, $_POST, $_REQUEST, $_SERVER), so every method is exercised by
 * populating those superglobals in each test. setUp() snapshots and clears
 * all four; tearDown() restores them, so tests never leak state.
 *
 * NOTE: currentDomain() is covered separately by RequestCurrentDomainTest.php
 * and is intentionally not re-tested here.
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Request;

class RequestCoreTest extends TestCase
{
    private array $savedGet;
    private array $savedPost;
    private array $savedRequest;
    private array $savedServer;

    public function setUp(): void
    {
        $this->savedGet = $_GET;
        $this->savedPost = $_POST;
        $this->savedRequest = $_REQUEST;
        $this->savedServer = $_SERVER;

        // Start every test from a clean slate.
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        // Keep $_SERVER but tests overwrite the specific keys they need.
    }

    public function tearDown(): void
    {
        $_GET = $this->savedGet;
        $_POST = $this->savedPost;
        $_REQUEST = $this->savedRequest;
        $_SERVER = $this->savedServer;
    }

    // ------------------------------------------------------------------
    // get() — reads $_REQUEST
    // ------------------------------------------------------------------

    public function testGetReturnsPresentValue(): void
    {
        $_REQUEST['foo'] = 'bar';
        $this->assertEquals('bar', Request::get('foo'));
    }

    public function testGetMissingReturnsDefaultEmptyString(): void
    {
        $this->assertEquals('', Request::get('missing'));
    }

    public function testGetMissingReturnsCustomDefault(): void
    {
        $this->assertEquals('fallback', Request::get('missing', 'fallback'));
    }

    public function testGetMissingReturnsNullDefault(): void
    {
        $this->assertNull(Request::get('missing', null));
    }

    public function testGetHonoursExplicitEmptyString(): void
    {
        $_REQUEST['empty'] = '';
        // Present but empty -> the stored '' wins over the default.
        $this->assertEquals('', Request::get('empty', 'default'));
    }

    // ------------------------------------------------------------------
    // query() — reads $_GET, case-insensitive
    // ------------------------------------------------------------------

    public function testQueryReturnsPresentValue(): void
    {
        $_GET['id'] = '42';
        $this->assertEquals('42', Request::query('id'));
    }

    public function testQueryMissingReturnsDefault(): void
    {
        $this->assertEquals('def', Request::query('id', 'def'));
    }

    public function testQueryMissingDefaultIsEmptyString(): void
    {
        $this->assertEquals('', Request::query('id'));
    }

    public function testQueryIsCaseInsensitive(): void
    {
        $_GET['UserName'] = 'alice';
        $this->assertEquals('alice', Request::query('username'));
        $this->assertEquals('alice', Request::query('USERNAME'));
    }

    public function testQueryExactMatchWinsOverCaseInsensitive(): void
    {
        // Exact key present is returned immediately.
        $_GET['id'] = 'exact';
        $this->assertEquals('exact', Request::query('id'));
    }

    // ------------------------------------------------------------------
    // post() — reads $_POST, case-insensitive
    // ------------------------------------------------------------------

    public function testPostReturnsPresentValue(): void
    {
        $_POST['name'] = 'bob';
        $this->assertEquals('bob', Request::post('name'));
    }

    public function testPostMissingReturnsDefault(): void
    {
        $this->assertEquals('x', Request::post('name', 'x'));
    }

    public function testPostIsCaseInsensitive(): void
    {
        $_POST['FieldName'] = 'v';
        $this->assertEquals('v', Request::post('fieldname'));
    }

    // ------------------------------------------------------------------
    // server()
    // ------------------------------------------------------------------

    public function testServerReturnsPresentValue(): void
    {
        $_SERVER['CUSTOM_KEY'] = 'sval';
        $this->assertEquals('sval', Request::server('CUSTOM_KEY'));
    }

    public function testServerMissingReturnsDefault(): void
    {
        unset($_SERVER['DOES_NOT_EXIST']);
        $this->assertEquals('d', Request::server('DOES_NOT_EXIST', 'd'));
    }

    // ------------------------------------------------------------------
    // queryAll / postAll / all
    // ------------------------------------------------------------------

    public function testQueryAllReturnsWholeGet(): void
    {
        $_GET = ['a' => '1', 'b' => '2'];
        $this->assertEquals(['a' => '1', 'b' => '2'], Request::queryAll());
    }

    public function testPostAllReturnsWholePost(): void
    {
        $_POST = ['x' => 'y'];
        $this->assertEquals(['x' => 'y'], Request::postAll());
    }

    public function testAllReturnsWholeRequest(): void
    {
        $_REQUEST = ['k' => 'v'];
        $this->assertEquals(['k' => 'v'], Request::all());
    }

    public function testQueryAllEmptyIsArray(): void
    {
        $this->assertIsArray(Request::queryAll());
        $this->assertEmpty(Request::queryAll());
    }

    // ------------------------------------------------------------------
    // hasQuery / hasPost / has
    // ------------------------------------------------------------------

    public function testHasQueryTrueWhenPresent(): void
    {
        $_GET['q'] = 'anything';
        $this->assertTrue(Request::hasQuery('q'));
    }

    public function testHasQueryFalseWhenAbsent(): void
    {
        $this->assertFalse(Request::hasQuery('q'));
    }

    public function testHasQueryTrueEvenForEmptyString(): void
    {
        // isset() is true for '' -> hasQuery returns true.
        $_GET['q'] = '';
        $this->assertTrue(Request::hasQuery('q'));
    }

    public function testHasQueryFalseForNullValue(): void
    {
        // isset() is false for a null value.
        $_GET['q'] = null;
        $this->assertFalse(Request::hasQuery('q'));
    }

    public function testHasPostTrueForKey(): void
    {
        $_POST['p'] = '1';
        $this->assertTrue(Request::hasPost('p'));
    }

    public function testHasPostFalseForMissingKey(): void
    {
        $this->assertFalse(Request::hasPost('p'));
    }

    public function testHasPostNoArgTrueWhenPostNonEmpty(): void
    {
        $_POST['anything'] = '1';
        $this->assertTrue(Request::hasPost());
    }

    public function testHasPostNoArgFalseWhenPostEmpty(): void
    {
        $_POST = [];
        $this->assertFalse(Request::hasPost());
    }

    public function testHasTrueWhenInRequest(): void
    {
        $_REQUEST['k'] = 'v';
        $this->assertTrue(Request::has('k'));
    }

    public function testHasFalseWhenAbsent(): void
    {
        $this->assertFalse(Request::has('k'));
    }

    // ------------------------------------------------------------------
    // int() — reads $_REQUEST, casts with (int)
    // ------------------------------------------------------------------

    public function testIntNumericString(): void
    {
        $_REQUEST['n'] = '123';
        $this->assertSame(123, Request::int('n'));
    }

    public function testIntNonNumericReturnsZero(): void
    {
        $_REQUEST['n'] = 'abc';
        $this->assertSame(0, Request::int('n'));
    }

    public function testIntMissingReturnsDefault(): void
    {
        $this->assertSame(7, Request::int('n', 7));
    }

    public function testIntNegative(): void
    {
        $_REQUEST['n'] = '-5';
        $this->assertSame(-5, Request::int('n'));
    }

    public function testIntFloatStringTruncates(): void
    {
        $_REQUEST['n'] = '3.9';
        $this->assertSame(3, Request::int('n'));
    }

    public function testIntLeadingZeros(): void
    {
        $_REQUEST['n'] = '007';
        $this->assertSame(7, Request::int('n'));
    }

    public function testIntLeadingWhitespace(): void
    {
        $_REQUEST['n'] = '  42';
        $this->assertSame(42, Request::int('n'));
    }

    public function testIntTrailingGarbage(): void
    {
        $_REQUEST['n'] = '12abc';
        $this->assertSame(12, Request::int('n'));
    }

    public function testIntEmptyStringIsZero(): void
    {
        $_REQUEST['n'] = '';
        $this->assertSame(0, Request::int('n'));
    }

    // ------------------------------------------------------------------
    // queryInt() — reads $_GET, returns STRING of digits only
    // ------------------------------------------------------------------

    public function testQueryIntNumericStaysString(): void
    {
        $_GET['id'] = '456';
        $this->assertSame('456', Request::queryInt('id'));
    }

    public function testQueryIntStripsNonNumeric(): void
    {
        $_GET['id'] = '12ab34';
        $this->assertSame('1234', Request::queryInt('id'));
    }

    public function testQueryIntNonNumericBecomesEmpty(): void
    {
        $_GET['id'] = 'abc';
        $this->assertSame('', Request::queryInt('id'));
    }

    public function testQueryIntPreservesMinusSign(): void
    {
        // A leading minus is preserved: "-5" stays "-5" (no silent sign flip).
        $_GET['id'] = '-5';
        $this->assertSame('-5', Request::queryInt('id'));
        // But a minus not at the start is still stripped (it's not a sign).
        $_GET['id'] = '5-3';
        $this->assertSame('53', Request::queryInt('id'));
    }

    public function testQueryIntFloatLosesDot(): void
    {
        $_GET['id'] = '3.14';
        $this->assertSame('314', Request::queryInt('id'));
    }

    public function testQueryIntMissingUsesNumericDefault(): void
    {
        // Default is passed through numbersOnly too.
        $this->assertSame('10', Request::queryInt('id', '10'));
    }

    public function testQueryIntMissingNonNumericDefaultStripped(): void
    {
        $this->assertSame('', Request::queryInt('id', 'abc'));
    }

    // ------------------------------------------------------------------
    // postInt() — reads $_POST, casts with (int)
    // ------------------------------------------------------------------

    public function testPostIntNumeric(): void
    {
        $_POST['n'] = '99';
        $this->assertSame(99, Request::postInt('n'));
    }

    public function testPostIntNonNumericZero(): void
    {
        $_POST['n'] = 'xx';
        $this->assertSame(0, Request::postInt('n'));
    }

    public function testPostIntMissingDefault(): void
    {
        $this->assertSame(3, Request::postInt('n', 3));
    }

    // ------------------------------------------------------------------
    // queryIntAndComma()
    // ------------------------------------------------------------------

    public function testQueryIntAndCommaKeepsList(): void
    {
        $_GET['ids'] = '1,2,3,4';
        $this->assertSame('1,2,3,4', Request::queryIntAndComma('ids'));
    }

    public function testQueryIntAndCommaDropsEmptyElements(): void
    {
        // Chars outside [0-9,] are removed AND the resulting empty elements are
        // dropped, so a stripped "abc" between commas does not leave "1,2,,3".
        $_GET['ids'] = '1, 2, abc,3';
        $this->assertSame('1,2,3', Request::queryIntAndComma('ids'));
        $_GET['ids'] = ',1,,2,';
        $this->assertSame('1,2', Request::queryIntAndComma('ids'));
    }

    public function testQueryIntAndCommaMissingIsEmpty(): void
    {
        $this->assertSame('', Request::queryIntAndComma('ids'));
    }

    public function testQueryIntAndCommaStripsInjection(): void
    {
        $_GET['ids'] = "1);DROP TABLE users,2";
        $this->assertSame('1,2', Request::queryIntAndComma('ids'));
    }

    // ------------------------------------------------------------------
    // queryIntAndGuid()
    // ------------------------------------------------------------------

    public function testQueryIntAndGuidNumericStripsToDigits(): void
    {
        $_GET['id'] = '00042';
        $this->assertSame('00042', Request::queryIntAndGuid('id'));
    }

    public function testQueryIntAndGuidBracedGuidPassesThrough(): void
    {
        $guid = '{12345678-1234-1234-1234-1234567890ab}';
        $_GET['id'] = $guid;
        $this->assertSame($guid, Request::queryIntAndGuid('id'));
    }

    public function testQueryIntAndGuidBarePlainGuidPassesThrough(): void
    {
        $guid = '12345678-1234-1234-1234-1234567890ab';
        $_GET['id'] = $guid;
        $this->assertSame($guid, Request::queryIntAndGuid('id'));
    }

    public function testQueryIntAndGuidNonGuidStripped(): void
    {
        // Not a valid GUID -> falls through to numbersOnly.
        $_GET['id'] = 'abc-123-def';
        $this->assertSame('123', Request::queryIntAndGuid('id'));
    }

    // ------------------------------------------------------------------
    // queryId()
    // ------------------------------------------------------------------

    public function testQueryIdNumeric(): void
    {
        $_GET['id'] = '12345';
        $this->assertSame('12345', Request::queryId('id'));
    }

    public function testQueryIdAlphanumericCode(): void
    {
        $_GET['id'] = 'C47';
        $this->assertSame('C47', Request::queryId('id'));
    }

    public function testQueryIdGuidWithBraces(): void
    {
        $guid = '{12345678-1234-1234-1234-1234567890ab}';
        $_GET['id'] = $guid;
        $this->assertSame($guid, Request::queryId('id'));
    }

    public function testQueryIdKeepsHyphenAndUnderscore(): void
    {
        $_GET['id'] = 'a-b_c';
        $this->assertSame('a-b_c', Request::queryId('id'));
    }

    public function testQueryIdStripsInjectionChars(): void
    {
        // Spaces, quotes, semicolons, parens are removed; the letters/digits stay.
        $_GET['id'] = "1' OR '1'='1";
        $this->assertSame('1OR11', Request::queryId('id'));
    }

    public function testQueryIdMissingReturnsDefault(): void
    {
        $this->assertSame('none', Request::queryId('id', 'none'));
    }

    public function testQueryIdEmptyStaysEmpty(): void
    {
        $_GET['id'] = '';
        $this->assertSame('', Request::queryId('id'));
    }

    // ------------------------------------------------------------------
    // bool() — reads $_REQUEST
    // ------------------------------------------------------------------

    public function testBoolOneIsTrue(): void
    {
        $_REQUEST['b'] = '1';
        $this->assertTrue(Request::bool('b'));
    }

    public function testBoolTrueWordIsTrue(): void
    {
        $_REQUEST['b'] = 'true';
        $this->assertTrue(Request::bool('b'));
    }

    public function testBoolYesIsTrue(): void
    {
        $_REQUEST['b'] = 'yes';
        $this->assertTrue(Request::bool('b'));
    }

    public function testBoolOnIsTrue(): void
    {
        $_REQUEST['b'] = 'on';
        $this->assertTrue(Request::bool('b'));
    }

    public function testBoolIsCaseInsensitiveForTrue(): void
    {
        $_REQUEST['b'] = 'TRUE';
        $this->assertTrue(Request::bool('b'));
    }

    public function testBoolZeroIsFalse(): void
    {
        $_REQUEST['b'] = '0';
        $this->assertFalse(Request::bool('b'));
    }

    public function testBoolFalseWordIsFalse(): void
    {
        $_REQUEST['b'] = 'false';
        $this->assertFalse(Request::bool('b'));
    }

    public function testBoolEmptyStringIsFalse(): void
    {
        $_REQUEST['b'] = '';
        $this->assertFalse(Request::bool('b'));
    }

    public function testBoolNoAndOffAreFalse(): void
    {
        $_REQUEST['b'] = 'no';
        $this->assertFalse(Request::bool('b'));
        $_REQUEST['b'] = 'off';
        $this->assertFalse(Request::bool('b'));
    }

    public function testBoolMissingReturnsDefault(): void
    {
        $this->assertFalse(Request::bool('b'));
        $this->assertTrue(Request::bool('b', true));
    }

    public function testBoolDutchJIsTrue(): void
    {
        // The Dutch "J"/"ja" is recognised as true (case-insensitive).
        $_REQUEST['b'] = 'J';
        $this->assertTrue(Request::bool('b'));
        $_REQUEST['b'] = 'ja';
        $this->assertTrue(Request::bool('b'));
        $_REQUEST['b'] = 'nee';
        $this->assertFalse(Request::bool('b'));
    }

    public function testBoolYSingleLetterIsTrue(): void
    {
        // "Y" alone is now recognised as true (alongside "yes").
        $_REQUEST['b'] = 'Y';
        $this->assertTrue(Request::bool('b'));
    }

    public function testBoolUnknownFallsBackToFilterVar(): void
    {
        // Whitespace-padded "true" is trimmed and matched.
        $_REQUEST['b'] = '  true  ';
        $this->assertTrue(Request::bool('b'));
    }

    // ------------------------------------------------------------------
    // float() — reads $_REQUEST
    // ------------------------------------------------------------------

    public function testFloatDecimalString(): void
    {
        $_REQUEST['f'] = '3.14';
        $this->assertSame(3.14, Request::float('f'));
    }

    public function testFloatIntegerString(): void
    {
        $_REQUEST['f'] = '10';
        $this->assertSame(10.0, Request::float('f'));
    }

    public function testFloatHonoursCommaDecimal(): void
    {
        // Dutch-locale decimals are honoured: "1,5" -> 1.5, "1.234,56" -> 1234.56.
        $_REQUEST['f'] = '1,5';
        $this->assertSame(1.5, Request::float('f'));
        $_REQUEST['f'] = '1.234,56';
        $this->assertSame(1234.56, Request::float('f'));
        $_REQUEST['f'] = '3.14';
        $this->assertSame(3.14, Request::float('f'));
        // Missing / non-numeric fall back to the default.
        unset($_REQUEST['f']);
        $this->assertSame(9.0, Request::float('f', 9.0));
    }

    public function testFloatNonNumericIsZero(): void
    {
        $_REQUEST['f'] = 'abc';
        $this->assertSame(0.0, Request::float('f'));
    }

    public function testFloatTrailingGarbage(): void
    {
        $_REQUEST['f'] = '3.14xyz';
        $this->assertSame(3.14, Request::float('f'));
    }

    public function testFloatMissingReturnsDefault(): void
    {
        $this->assertSame(2.5, Request::float('f', 2.5));
    }

    // ------------------------------------------------------------------
    // string() / array()
    // ------------------------------------------------------------------

    public function testStringCastsToString(): void
    {
        $_REQUEST['s'] = 123;
        $this->assertSame('123', Request::string('s'));
    }

    public function testStringMissingDefault(): void
    {
        $this->assertSame('def', Request::string('s', 'def'));
    }

    public function testArrayReturnsArrayValue(): void
    {
        $_REQUEST['a'] = ['x', 'y'];
        $this->assertEquals(['x', 'y'], Request::array('a'));
    }

    public function testArrayScalarReturnsDefault(): void
    {
        $_REQUEST['a'] = 'notarray';
        $this->assertEquals([], Request::array('a'));
    }

    // ------------------------------------------------------------------
    // method / isGet / isPost
    // ------------------------------------------------------------------

    public function testMethodUppercased(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'post';
        $this->assertSame('POST', Request::method());
    }

    public function testMethodDefaultsToGet(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
        $this->assertSame('GET', Request::method());
    }

    public function testIsGetTrue(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertTrue(Request::isGet());
        $this->assertFalse(Request::isPost());
    }

    public function testIsPostTrue(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertTrue(Request::isPost());
        $this->assertFalse(Request::isGet());
    }

    // ------------------------------------------------------------------
    // isAjax
    // ------------------------------------------------------------------

    public function testIsAjaxTrue(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $this->assertTrue(Request::isAjax());
    }

    public function testIsAjaxCaseInsensitive(): void
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
        $this->assertTrue(Request::isAjax());
    }

    public function testIsAjaxFalseWhenAbsent(): void
    {
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        $this->assertFalse(Request::isAjax());
    }

    // ------------------------------------------------------------------
    // ip
    // ------------------------------------------------------------------

    public function testIpFromRemoteAddr(): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
        $this->assertSame('10.0.0.5', Request::ip());
    }

    public function testIpPrefersForwardedFor(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
        $this->assertSame('203.0.113.7', Request::ip());
    }

    public function testIpForwardedForTakesFirstOfList(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7, 70.41.3.18, 150.172.238.178';
        $this->assertSame('203.0.113.7', Request::ip());
    }

    public function testIpMissingIsEmptyString(): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);
        $this->assertSame('', Request::ip());
    }

    // ------------------------------------------------------------------
    // userAgent / uri
    // ------------------------------------------------------------------

    public function testUserAgentReturnsHeader(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
        $this->assertSame('Mozilla/5.0', Request::userAgent());
    }

    public function testUserAgentMissingIsEmpty(): void
    {
        unset($_SERVER['HTTP_USER_AGENT']);
        $this->assertSame('', Request::userAgent());
    }

    public function testUriReturnsRequestUri(): void
    {
        $_SERVER['REQUEST_URI'] = '/cma/details.php?id=1';
        $this->assertSame('/cma/details.php?id=1', Request::uri());
    }

    public function testUriMissingIsEmpty(): void
    {
        unset($_SERVER['REQUEST_URI']);
        $this->assertSame('', Request::uri());
    }

    // ------------------------------------------------------------------
    // url
    // ------------------------------------------------------------------

    public function testUrlHttp(): void
    {
        unset($_SERVER['HTTPS']);
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/page';
        $this->assertSame('http://example.com/page', Request::url());
    }

    public function testUrlHttps(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'secure.example.com';
        $_SERVER['REQUEST_URI'] = '/x';
        $this->assertSame('https://secure.example.com/x', Request::url());
    }

    public function testUrlHttpsOffTreatedAsHttp(): void
    {
        $_SERVER['HTTPS'] = 'off';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['REQUEST_URI'] = '/y';
        $this->assertSame('http://example.com/y', Request::url());
    }

    public function testUrlMissingHostDefaultsToLocalhost(): void
    {
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_HOST']);
        $_SERVER['REQUEST_URI'] = '/z';
        $this->assertSame('http://localhost/z', Request::url());
    }

    // ------------------------------------------------------------------
    // scriptName / getOriginalScript
    // ------------------------------------------------------------------

    public function testScriptNamePrefersOriginalFileHeader(): void
    {
        $_SERVER['HTTP_X_ORIGINAL_FILE'] = '/cma/details.php';
        $_SERVER['PHP_SELF'] = '/_bootstrap_wrapper.php';
        $this->assertSame('/cma/details.php', Request::scriptName());
    }

    public function testScriptNameFallsBackToPhpSelf(): void
    {
        unset($_SERVER['HTTP_X_ORIGINAL_FILE']);
        $_SERVER['PHP_SELF'] = '/cma/index.php';
        $this->assertSame('/cma/index.php', Request::scriptName());
    }

    public function testScriptNameEmptyWhenNothingSet(): void
    {
        unset($_SERVER['HTTP_X_ORIGINAL_FILE'], $_SERVER['PHP_SELF']);
        $this->assertSame('', Request::scriptName());
    }

    public function testGetOriginalScriptAliasesScriptName(): void
    {
        $_SERVER['HTTP_X_ORIGINAL_FILE'] = '/cma/edit.php';
        $this->assertSame(Request::scriptName(), Request::getOriginalScript());
    }

    // ------------------------------------------------------------------
    // addToURL
    // ------------------------------------------------------------------

    public function testAddToUrlNoExistingQueryString(): void
    {
        $this->assertSame(
            'http://x.com/p?a=1',
            Request::addToURL('http://x.com/p', 'a', '1')
        );
    }

    public function testAddToUrlAppendsToExistingQueryString(): void
    {
        $this->assertSame(
            'http://x.com/p?a=1&b=2',
            Request::addToURL('http://x.com/p?a=1', 'b', '2')
        );
    }

    public function testAddToUrlOverwritesExistingParam(): void
    {
        $this->assertSame(
            'http://x.com/p?a=2',
            Request::addToURL('http://x.com/p?a=1', 'a', '2')
        );
    }

    public function testAddToUrlEmptyValueRemovesParam(): void
    {
        $this->assertSame(
            'http://x.com/p',
            Request::addToURL('http://x.com/p?a=1', 'a', '')
        );
    }

    public function testAddToUrlEmptyValueOnBareUrlReturnsBase(): void
    {
        $this->assertSame(
            'http://x.com/p',
            Request::addToURL('http://x.com/p', 'a', '')
        );
    }

    public function testAddToUrlRemovesParamCaseInsensitively(): void
    {
        // The removal branch also strips a case-differing duplicate key.
        $this->assertSame(
            'http://x.com/p?b=2',
            Request::addToURL('http://x.com/p?A=1&b=2', 'a', '')
        );
    }

    public function testAddToUrlEncodesValueWhenQueryExists(): void
    {
        // With an existing query string, http_build_query encodes the space as '+'.
        $this->assertSame(
            'http://x.com/p?a=1&b=x+y',
            Request::addToURL('http://x.com/p?a=1', 'b', 'x y')
        );
    }

    public function testAddToUrlEncodesValueWhenNoQueryString(): void
    {
        // The no-query branch now URL-encodes name+value the same way the
        // existing-query branch does (space -> '+'), so links can't break.
        $this->assertSame(
            'http://x.com/p?b=x+y',
            Request::addToURL('http://x.com/p', 'b', 'x y')
        );
    }

    public function testAddToUrlEmptyUrlUsesCurrentUrl(): void
    {
        unset($_SERVER['HTTPS']);
        $_SERVER['HTTP_HOST'] = 'host.local';
        $_SERVER['REQUEST_URI'] = '/base';
        $this->assertSame(
            'http://host.local/base?a=1',
            Request::addToURL('', 'a', '1')
        );
    }

    // ------------------------------------------------------------------
    // deleteParam
    // ------------------------------------------------------------------

    public function testDeleteParamRemovesPresent(): void
    {
        $this->assertSame(
            'http://x.com/p?b=2',
            Request::deleteParam('http://x.com/p?a=1&b=2', 'a')
        );
    }

    public function testDeleteParamAbsentIsNoOp(): void
    {
        $this->assertSame(
            'http://x.com/p?a=1',
            Request::deleteParam('http://x.com/p?a=1', 'zzz')
        );
    }

    public function testDeleteParamLastParamDropsQuestionMark(): void
    {
        $this->assertSame(
            'http://x.com/p',
            Request::deleteParam('http://x.com/p?a=1', 'a')
        );
    }

    // ------------------------------------------------------------------
    // addAllToURL
    // ------------------------------------------------------------------

    public function testAddAllToUrlCopiesGetParams(): void
    {
        $_GET = ['a' => '1', 'b' => '2'];
        $result = Request::addAllToURL('http://x.com/p');
        // Order follows $_GET insertion order.
        $this->assertSame('http://x.com/p?a=1&b=2', $result);
    }

    public function testAddAllToUrlEmptyGetIsUnchanged(): void
    {
        $_GET = [];
        $this->assertSame('http://x.com/p', Request::addAllToURL('http://x.com/p'));
    }

    public function testAddAllToUrlMergesWithExistingQuery(): void
    {
        $_GET = ['b' => '2'];
        $this->assertSame(
            'http://x.com/p?a=1&b=2',
            Request::addAllToURL('http://x.com/p?a=1')
        );
    }
}
