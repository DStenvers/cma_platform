<?php
/**
 * Tests for App\Library\File
 *
 * Run with: php tests/TestRunner.php FileTest
 *
 * File resolves virtual paths through Server::mapPath(). setUp points Server's
 * cached document-root and allowed-paths at a throwaway temp dir via reflection,
 * so absolute virtual paths ("/x.txt") resolve to $tmpRoot/x.txt. The temp dir
 * sits under sys_get_temp_dir(), which mapPath allows.
 *
 * Note: createAsciiFile() appends PHP_EOL to the content (VBScript WriteLine
 * compatibility), so readAsciiFile() round-trips as content . PHP_EOL.
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\File;
use App\Library\Server;

class FileTest extends TestCase
{
    private string $tmpRoot;

    public function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/file-' . bin2hex(random_bytes(6));
        mkdir($this->tmpRoot, 0755, true);
        $this->pointServerAt($this->tmpRoot);
    }

    public function tearDown(): void
    {
        $this->resetServer();
        $this->rmrf($this->tmpRoot);
    }

    /**
     * Reset Server's cached statics to null so Server::init() re-initializes
     * naturally for subsequent test classes (avoids leaking our sandbox).
     */
    private function resetServer(): void
    {
        foreach (['documentRoot', 'allowedPaths'] as $prop) {
            $ref = new \ReflectionProperty(Server::class, $prop);
            $ref->setAccessible(true);
            $ref->setValue(null, $prop === 'allowedPaths' ? [] : null);
        }
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

    private function pointServerAt(string $dir): void
    {
        $norm = str_replace('\\', '/', rtrim($dir, '/\\'));
        $root = new \ReflectionProperty(Server::class, 'documentRoot');
        $root->setAccessible(true);
        $root->setValue(null, $norm);
        $allowed = new \ReflectionProperty(Server::class, 'allowedPaths');
        $allowed->setAccessible(true);
        $allowed->setValue(null, [$norm, str_replace('\\', '/', sys_get_temp_dir())]);
    }

    // ========================================================================
    // createAsciiFile / readAsciiFile round-trip
    // ========================================================================

    public function testCreateAndReadRoundTrip(): void
    {
        $this->assertTrue(File::createAsciiFile('/note.txt', 'hello world'));
        // createAsciiFile appends PHP_EOL for VBScript WriteLine compatibility.
        $this->assertEquals('hello world' . PHP_EOL, File::readAsciiFile('/note.txt'));
    }

    public function testCreateAsciiFileOverwrites(): void
    {
        File::createAsciiFile('/ow.txt', 'first');
        File::createAsciiFile('/ow.txt', 'second');
        $this->assertEquals('second' . PHP_EOL, File::readAsciiFile('/ow.txt'));
    }

    public function testCreateAsciiFileCreatesMissingDir(): void
    {
        $this->assertTrue(File::createAsciiFile('/sub/dir/f.txt', 'x'));
        $this->assertTrue(is_dir($this->tmpRoot . '/sub/dir'));
    }

    public function testCreateAsciiFileEmptyPathReturnsFalse(): void
    {
        $this->assertFalse(File::createAsciiFile('', 'content'));
    }

    public function testCreateAsciiFileUnicodeContent(): void
    {
        $content = 'café — 日本語 — Ø — ß';
        File::createAsciiFile('/uni.txt', $content);
        $this->assertEquals($content . PHP_EOL, File::readAsciiFile('/uni.txt'));
    }

    public function testCreateAsciiFileEmptyContent(): void
    {
        $this->assertTrue(File::createAsciiFile('/empty.txt', ''));
        // Empty content still yields the trailing EOL.
        $this->assertEquals(PHP_EOL, File::readAsciiFile('/empty.txt'));
    }

    public function testReadAsciiFileMissingReturnsEmptyString(): void
    {
        $result = File::readAsciiFile('/does-not-exist.txt');
        $this->assertEquals('', $result);
    }

    public function testReadAsciiFileEmptyPathReturnsEmptyString(): void
    {
        $this->assertEquals('', File::readAsciiFile(''));
    }

    // ========================================================================
    // exists
    // ========================================================================

    public function testExistsTrueForCreatedFile(): void
    {
        File::createAsciiFile('/present.txt', 'x');
        $this->assertTrue(File::exists('/present.txt'));
    }

    public function testExistsFalseForMissingFile(): void
    {
        $this->assertFalse(File::exists('/missing.txt'));
    }

    public function testExistsEmptyPathReturnsFalse(): void
    {
        $this->assertFalse(File::exists(''));
    }

    public function testExistsFalseForDirectory(): void
    {
        // exists() is file-only (is_file); a directory is not a "file".
        mkdir($this->tmpRoot . '/adir');
        $this->assertFalse(File::exists('/adir'));
    }

    // ========================================================================
    // folderExists
    // ========================================================================

    public function testFolderExistsTrue(): void
    {
        mkdir($this->tmpRoot . '/afolder');
        $this->assertTrue(File::folderExists('/afolder'));
    }

    public function testFolderExistsFalseForMissing(): void
    {
        $this->assertFalse(File::folderExists('/nofolder'));
    }

    public function testFolderExistsFalseForFile(): void
    {
        File::createAsciiFile('/afile.txt', 'x');
        $this->assertFalse(File::folderExists('/afile.txt'));
    }

    public function testFolderExistsEmptyPathReturnsFalse(): void
    {
        $this->assertFalse(File::folderExists(''));
    }

    // ========================================================================
    // createFolder
    // ========================================================================

    public function testCreateFolderSimple(): void
    {
        $this->assertTrue(File::createFolder('/newdir'));
        $this->assertTrue(is_dir($this->tmpRoot . '/newdir'));
    }

    public function testCreateFolderNested(): void
    {
        $this->assertTrue(File::createFolder('/a/b/c/d'));
        $this->assertTrue(is_dir($this->tmpRoot . '/a/b/c/d'));
    }

    public function testCreateFolderIdempotentOnExisting(): void
    {
        mkdir($this->tmpRoot . '/exists');
        $this->assertTrue(File::createFolder('/exists'), 'existing folder should return true');
    }

    public function testCreateFolderEmptyPathReturnsFalse(): void
    {
        $this->assertFalse(File::createFolder(''));
    }

    public function testCreateFolderRawUsesLiteralPath(): void
    {
        // $raw = true bypasses Server::mapPath() and treats the path verbatim.
        $literal = $this->tmpRoot . '/raw/one/two';
        $this->assertTrue(File::createFolder($literal, true));
        $this->assertTrue(is_dir($literal));
    }

    // ========================================================================
    // copy
    // ========================================================================

    public function testCopyDuplicatesFile(): void
    {
        File::createAsciiFile('/src.txt', 'payload');
        $this->assertTrue(File::copy('/src.txt', '/dst.txt'));
        $this->assertTrue(File::exists('/dst.txt'));
        $this->assertEquals('payload' . PHP_EOL, File::readAsciiFile('/dst.txt'));
    }

    public function testCopyCreatesDestinationDir(): void
    {
        File::createAsciiFile('/src2.txt', 'data');
        $this->assertTrue(File::copy('/src2.txt', '/newdst/dir/out.txt'));
        $this->assertTrue(is_dir($this->tmpRoot . '/newdst/dir'));
        $this->assertTrue(File::exists('/newdst/dir/out.txt'));
    }

    public function testCopyMissingSourceReturnsFalse(): void
    {
        $this->assertFalse(File::copy('/no-source.txt', '/dst3.txt'));
        $this->assertFalse(File::exists('/dst3.txt'));
    }

    public function testCopyPreservesOriginal(): void
    {
        File::createAsciiFile('/orig.txt', 'keep');
        File::copy('/orig.txt', '/clone.txt');
        // Source still present after copy.
        $this->assertTrue(File::exists('/orig.txt'));
        $this->assertEquals('keep' . PHP_EOL, File::readAsciiFile('/orig.txt'));
    }

    // ========================================================================
    // delete
    // ========================================================================

    public function testDeleteRemovesFile(): void
    {
        File::createAsciiFile('/del.txt', 'x');
        $this->assertTrue(File::exists('/del.txt'));
        $this->assertTrue(File::delete('/del.txt'));
        $this->assertFalse(File::exists('/del.txt'));
    }

    public function testDeleteMissingFileReturnsFalse(): void
    {
        // Graceful: deleting a non-existent file returns false, does not throw.
        $this->assertFalse(File::delete('/never-existed.txt'));
    }

    public function testDeleteEmptyPathReturnsFalse(): void
    {
        $this->assertFalse(File::delete(''));
    }

    public function testDeleteUnicodeFilename(): void
    {
        $name = '/café-日本語.txt';
        File::createAsciiFile($name, 'unicode filename');
        $this->assertTrue(File::exists($name));
        $this->assertTrue(File::delete($name));
        $this->assertFalse(File::exists($name));
    }

    // ========================================================================
    // Combined lifecycle
    // ========================================================================

    public function testFullLifecycle(): void
    {
        // create -> exists -> copy -> read -> delete
        $this->assertTrue(File::createAsciiFile('/life.txt', 'v1'));
        $this->assertTrue(File::exists('/life.txt'));
        $this->assertTrue(File::copy('/life.txt', '/life-copy.txt'));
        $this->assertEquals('v1' . PHP_EOL, File::readAsciiFile('/life-copy.txt'));
        $this->assertTrue(File::delete('/life.txt'));
        $this->assertFalse(File::exists('/life.txt'));
        $this->assertTrue(File::exists('/life-copy.txt'));
    }
}
