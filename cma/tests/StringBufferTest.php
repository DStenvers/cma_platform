<?php
/**
 * Tests for App\Library\StringBuffer
 *
 * Run with: php tests/TestRunner.php StringBufferTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

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
}
