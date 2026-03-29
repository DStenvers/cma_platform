<?php
/**
 * Tests for App\Library\Encryption
 *
 * Run with: php tests/TestRunner.php EncryptionTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

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
}
