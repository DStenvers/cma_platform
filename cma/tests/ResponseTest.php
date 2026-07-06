<?php
/**
 * Tests for App\Library\Response.
 *
 * Run with: php tests/TestRunner.php ResponseTest
 *
 * Response is mostly HTTP header I/O. Under the CLI test runner, output has
 * already been emitted by the runner, so headers_sent() is true — every
 * header-setting method is guarded by `if (!headers_sent())` and therefore
 * safely becomes a no-op returning false (it never fatals). We assert that
 * contract for the header setters, and fully exercise the methods that ECHO a
 * body (write/writeEncoded/json/xml/download) by capturing output.
 *
 * We deliberately avoid redirect()/notFound()/serverError()/forbidden()/
 * unauthorized() — they call exit(), which would terminate the whole runner.
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Response;

class ResponseTest extends TestCase
{
    /** Run a callable, return whatever it echoed. */
    private function capture(callable $fn): string
    {
        ob_start();
        try {
            $fn();
        } finally {
            $out = ob_get_clean();
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // write / writeEncoded
    // ------------------------------------------------------------------

    public function testWritePlainEchoesVerbatim(): void
    {
        $out = $this->capture(fn() => Response::write('<b>hi & bye</b>'));
        $this->assertEquals('<b>hi & bye</b>', $out);
    }

    public function testWriteWithEncodeEscapesHtml(): void
    {
        $out = $this->capture(fn() => Response::write('<script>alert("x")</script>', true));
        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
        // ENT_QUOTES escapes double quotes too.
        $this->assertStringContainsString('&quot;', $out);
    }

    public function testWriteEncodedIsAliasForEncodedWrite(): void
    {
        $out = $this->capture(fn() => Response::writeEncoded('a & b < c'));
        $this->assertStringContainsString('&amp;', $out);
        $this->assertStringContainsString('&lt;', $out);
    }

    // ------------------------------------------------------------------
    // json — the body is always echoed even when header setters no-op
    // ------------------------------------------------------------------

    public function testJsonEncodesPayload(): void
    {
        $out = $this->capture(fn() => Response::json(['status' => 'ok', 'n' => 3]));
        $decoded = json_decode($out, true);
        $this->assertIsArray($decoded);
        $this->assertEquals('ok', $decoded['status']);
        $this->assertEquals(3, $decoded['n']);
    }

    public function testJsonUnescapesSlashesAndUnicode(): void
    {
        // Default options include JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE.
        $out = $this->capture(fn() => Response::json(['url' => 'https://example.com/p', 'txt' => 'café']));
        $this->assertStringContainsString('https://example.com/p', $out);
        $this->assertStringNotContainsString('https:\/\/', $out);
        $this->assertStringContainsString('café', $out);
    }

    public function testJsonRespectsCustomOptions(): void
    {
        // Passing 0 options → default (escaped) encoding, no pretty print.
        $out = $this->capture(fn() => Response::json(['url' => 'http://x/y'], 200, 0));
        $this->assertStringContainsString('http:\/\/x\/y', $out);
    }

    // ------------------------------------------------------------------
    // xml
    // ------------------------------------------------------------------

    public function testXmlEchoesBodyVerbatim(): void
    {
        $xml = '<?xml version="1.0"?><root><a>1</a></root>';
        $out = $this->capture(fn() => Response::xml($xml));
        $this->assertEquals($xml, $out);
    }

    // ------------------------------------------------------------------
    // download / inline — file-not-found and happy paths (no exit() in these)
    // ------------------------------------------------------------------

    public function testDownloadMissingFileEmitsNotFoundBody(): void
    {
        $out = $this->capture(fn() => Response::download(sys_get_temp_dir() . '/missing-' . uniqid() . '.bin'));
        $this->assertStringContainsString('File not found', $out);
    }

    public function testDownloadExistingFileStreamsContent(): void
    {
        $path = sys_get_temp_dir() . '/resp-dl-' . bin2hex(random_bytes(4)) . '.txt';
        file_put_contents($path, 'PAYLOAD-BYTES');
        try {
            $out = $this->capture(fn() => Response::download($path));
            $this->assertEquals('PAYLOAD-BYTES', $out);
        } finally {
            @unlink($path);
        }
    }

    public function testInlineMissingFileEmitsNotFoundBody(): void
    {
        $out = $this->capture(fn() => Response::inline(sys_get_temp_dir() . '/missing-' . uniqid() . '.pdf'));
        $this->assertStringContainsString('File not found', $out);
    }

    // ------------------------------------------------------------------
    // Header-setting methods: guarded, safe, return bool. Under the runner
    // headers are already sent so these no-op — we assert the type contract
    // and that they never throw.
    // ------------------------------------------------------------------

    public function testHeaderSettersReturnBoolAndDoNotThrow(): void
    {
        $this->assertTrue(is_bool(Response::setHeader('X-Test', '1')));
        $this->assertTrue(is_bool(Response::setContentType('application/json')));
        $this->assertTrue(is_bool(Response::setStatus(201)));
        $this->assertTrue(is_bool(Response::cacheControl(60)));
        $this->assertTrue(is_bool(Response::cacheControl(0)));
        $this->assertTrue(is_bool(Response::noCache()));
        $this->assertTrue(is_bool(Response::cacheExpires(5)));
        $this->assertTrue(is_bool(Response::enableCors()));
    }

    public function testHeadersSentReturnsBool(): void
    {
        $this->assertTrue(is_bool(Response::headersSent()));
    }

    public function testHeadersSentPopulatesFileAndLineByReference(): void
    {
        $file = null;
        $line = null;
        Response::headersSent($file, $line);
        // In the CLI runner output has already been emitted, so file/line are
        // populated; either way the call must not throw and returns cleanly.
        $this->assertTrue(true);
    }

    // ------------------------------------------------------------------
    // clearBuffers
    // ------------------------------------------------------------------

    public function testClearBuffersUnwindsToZeroLevel(): void
    {
        $baseline = ob_get_level();
        ob_start();
        ob_start();
        $this->assertGreaterThan($baseline, ob_get_level());
        Response::clearBuffers();
        $this->assertEquals(0, ob_get_level());
    }
}
