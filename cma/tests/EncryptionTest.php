<?php
/**
 * Tests for App\Library\Encryption
 *
 * Run with: php tests/TestRunner.php EncryptionTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Encryption;

class EncryptionTest extends TestCase
{
    public function testSha256(): void
    {
        $hash = Encryption::sha256('hello');
        $this->assertEquals(64, strlen($hash)); // SHA-256 = 64 hex chars
        $this->assertEquals('2cf24dba5fb0a30e26e83b2ac5b9e29e1b161e5c1fa7425e73043362938b9824', $hash);
    }

    public function testSha256Empty(): void
    {
        $hash = Encryption::sha256('');
        $this->assertEquals(64, strlen($hash));
        $this->assertEquals('e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', $hash);
    }

    public function testSha256Consistency(): void
    {
        $hash1 = Encryption::sha256('test');
        $hash2 = Encryption::sha256('test');
        $this->assertEquals($hash1, $hash2);
    }

    public function testSha256Different(): void
    {
        $hash1 = Encryption::sha256('abc');
        $hash2 = Encryption::sha256('abd');
        $this->assertFalse($hash1 === $hash2);
    }

    public function testSha256Hex(): void
    {
        // Output is always lowercase hexadecimal
        $hash = Encryption::sha256('anything');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
    }

    public function testSha256Unicode(): void
    {
        // Multibyte UTF-8 input hashes over its raw bytes, deterministically
        $this->assertEquals(hash('sha256', 'café'), Encryption::sha256('café'));
        $this->assertEquals(64, strlen(Encryption::sha256('café')));
    }

    public function testSha256Binary(): void
    {
        // Binary/NUL bytes hash without truncation
        $data = "\x00\x01\x02\xFF\x00binary";
        $hash = Encryption::sha256($data);
        $this->assertEquals(hash('sha256', $data), $hash);
        $this->assertEquals(64, strlen($hash));
    }

    public function testSha256LongInput(): void
    {
        $data = str_repeat('a', 100000);
        $this->assertEquals(hash('sha256', $data), Encryption::sha256($data));
    }

    public function testSha256AvalancheOneBit(): void
    {
        // A one-character change produces a completely different digest
        $this->assertNotEquals(Encryption::sha256('password'), Encryption::sha256('passwore'));
    }
}
