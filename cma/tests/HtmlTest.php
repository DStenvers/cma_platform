<?php
/**
 * Tests for App\Library\Html
 *
 * Run with: php tests/TestRunner.php HtmlTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Library\Html;

class HtmlTest extends TestCase
{
    // ========================================================================
    // fixUnicode
    // ========================================================================

    public function testFixUnicodeEmpty(): void
    {
        $this->assertEquals('', Html::fixUnicode(''));
        $this->assertEquals('', Html::fixUnicode(null));
    }

    public function testFixUnicodeNoChange(): void
    {
        $this->assertEquals('hello world', Html::fixUnicode('hello world'));
    }

    public function testFixUnicodeEmDash(): void
    {
        $this->assertEquals('-', Html::fixUnicode('â€"'));
    }

    public function testFixUnicodeAccentedE(): void
    {
        $this->assertEquals('&eacute;', Html::fixUnicode('Ã©'));
        $this->assertEquals('&euml;', Html::fixUnicode('Ã«'));
    }

    public function testFixUnicodeEuro(): void
    {
        $this->assertEquals('&euro;', Html::fixUnicode('â‚¬'));
    }

    public function testFixUnicodeFraction(): void
    {
        $this->assertEquals('&frac12;', Html::fixUnicode('Â½'));
    }

    // ========================================================================
    // encode / decode
    // ========================================================================

    public function testEncode(): void
    {
        $this->assertEquals('&lt;b&gt;test&lt;/b&gt;', Html::encode('<b>test</b>'));
    }

    public function testEncodeQuotes(): void
    {
        $result = Html::encode('"hello" & \'world\'');
        $this->assertStringContainsString('&amp;', $result);
        $this->assertStringContainsString('&quot;', $result);
    }

    public function testEncodeNull(): void
    {
        $this->assertEquals('', Html::encode(null));
    }

    public function testDecode(): void
    {
        $this->assertEquals('<b>test</b>', Html::decode('&lt;b&gt;test&lt;/b&gt;'));
    }

    public function testDecodeNull(): void
    {
        $this->assertEquals('', Html::decode(null));
    }

    public function testEncodeDecodeRoundTrip(): void
    {
        $original = '<script>alert("xss")</script>';
        $this->assertEquals($original, Html::decode(Html::encode($original)));
    }

    // ========================================================================
    // stripTags
    // ========================================================================

    public function testStripTags(): void
    {
        $this->assertEquals('hello world', Html::stripTags('<b>hello</b> <i>world</i>'));
    }

    public function testStripTagsAllowed(): void
    {
        $result = Html::stripTags('<b>hello</b> <i>world</i>', '<b>');
        $this->assertEquals('<b>hello</b> world', $result);
    }

    public function testStripTagsNull(): void
    {
        $this->assertEquals('', Html::stripTags(null));
    }

    // ========================================================================
    // containsHtml
    // ========================================================================

    public function testContainsHtmlTrue(): void
    {
        $this->assertTrue(Html::containsHtml('<b>bold</b>'));
        $this->assertTrue(Html::containsHtml('text <br> more'));
    }

    public function testContainsHtmlFalse(): void
    {
        $this->assertFalse(Html::containsHtml('plain text'));
        $this->assertFalse(Html::containsHtml(''));
        $this->assertFalse(Html::containsHtml(null));
    }
}
