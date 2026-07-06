<?php
/**
 * Tests for App\Library\StringBuffer
 *
 * Run with: php tests/TestRunner.php StringBufferTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\StringBuffer;

class StringBufferTest extends TestCase
{
    public function testAppend(): void
    {
        $buf = new StringBuffer();
        $buf->append('Hello ');
        $buf->append('World');
        $this->assertEquals('Hello World', $buf->toString());
    }

    public function testAppendLine(): void
    {
        $buf = new StringBuffer();
        $buf->appendLine('Line 1');
        $buf->appendLine('Line 2');
        $this->assertEquals("Line 1\r\nLine 2\r\n", $buf->toString());
    }

    public function testClear(): void
    {
        $buf = new StringBuffer();
        $buf->append('test');
        $buf->clear();
        $this->assertEquals('', $buf->toString());
        $this->assertEquals(0, $buf->getSize());
    }

    public function testGetSize(): void
    {
        $buf = new StringBuffer();
        $this->assertEquals(0, $buf->getSize());
        $buf->append('hello');
        $this->assertEquals(5, $buf->getSize());
    }

    public function testToString(): void
    {
        $buf = new StringBuffer();
        $buf->append('test');
        $this->assertEquals('test', (string)$buf);
    }

    public function testAppendNumber(): void
    {
        $buf = new StringBuffer();
        $buf->append(42);
        $this->assertEquals('42', $buf->toString());
    }

    public function testEmptyBuffer(): void
    {
        $buf = new StringBuffer();
        $this->assertEquals('', $buf->toString());
    }

    // ========================================================================
    // saveToFile (previously uncovered)
    // ========================================================================

    private array $tempFiles = [];

    public function tearDown(): void
    {
        foreach ($this->tempFiles as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        $this->tempFiles = [];
    }

    private function tempPath(): string
    {
        $path = sys_get_temp_dir() . '/sb_test_' . uniqid('', true) . '.txt';
        $this->tempFiles[] = $path;
        return $path;
    }

    public function testSaveToFileWritesContents(): void
    {
        $buf = new StringBuffer();
        $buf->append('Hello ');
        $buf->append('File');

        $path = $this->tempPath();
        $this->assertTrue($buf->saveToFile($path));
        $this->assertEquals('Hello File', file_get_contents($path));
    }

    public function testSaveToFileEmptyBuffer(): void
    {
        $buf = new StringBuffer();
        $path = $this->tempPath();
        // file_put_contents('') returns 0 (bytes written), which is !== false → true
        $this->assertTrue($buf->saveToFile($path));
        $this->assertEquals('', file_get_contents($path));
    }

    // ========================================================================
    // getSize — counts BYTES, not characters (multibyte)
    // ========================================================================

    public function testGetSizeCountsBytes(): void
    {
        $buf = new StringBuffer();
        $buf->append('é'); // 2 bytes in UTF-8
        $this->assertEquals(2, $buf->getSize());
    }

    public function testGetSizeTracksAppends(): void
    {
        $buf = new StringBuffer();
        $buf->append('ab');
        $buf->append('cde');
        $this->assertEquals(5, $buf->getSize());
    }

    // ========================================================================
    // appendLine — empty value still adds the CRLF
    // ========================================================================

    public function testAppendLineEmpty(): void
    {
        $buf = new StringBuffer();
        $buf->appendLine('');
        $this->assertEquals("\r\n", $buf->toString());
        $this->assertEquals(2, $buf->getSize());
    }

    // ========================================================================
    // clear then reuse
    // ========================================================================

    public function testClearThenReuse(): void
    {
        $buf = new StringBuffer();
        $buf->append('first');
        $buf->clear();
        $buf->append('second');
        $this->assertEquals('second', $buf->toString());
        $this->assertEquals(6, $buf->getSize());
    }

    // ========================================================================
    // append — type coercion of non-string scalars
    // ========================================================================

    public function testAppendFloat(): void
    {
        $buf = new StringBuffer();
        $buf->append(3.14);
        $this->assertEquals('3.14', $buf->toString());
    }

    public function testAppendBoolTrue(): void
    {
        $buf = new StringBuffer();
        $buf->append(true);   // (string)true === '1'
        $buf->append(false);  // (string)false === ''
        $this->assertEquals('1', $buf->toString());
    }

    // ========================================================================
    // __toString reflects live buffer state
    // ========================================================================

    public function testToStringMagicAfterAppend(): void
    {
        $buf = new StringBuffer();
        $buf->append('a');
        $buf->append('b');
        $this->assertEquals('ab', (string)$buf);
    }
}
