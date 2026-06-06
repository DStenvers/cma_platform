<?php
/**
 * Tests for App\Library\Bootstrap::peekEnvVar — the single-var env reader
 * used to learn APP_ENVIRONMENT before the full dotenv load (single-.env model).
 *
 * Run with: php cma/tests/TestRunner.php BootstrapEnvTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Bootstrap;

class BootstrapEnvTest extends TestCase
{
    private string $tmp = '';

    private function writeEnv(string $contents): string
    {
        $this->tmp = sys_get_temp_dir() . '/peekenv_' . uniqid('', true) . '.env';
        file_put_contents($this->tmp, $contents);
        return $this->tmp;
    }

    private function cleanup(): void
    {
        if ($this->tmp !== '' && is_file($this->tmp)) { @unlink($this->tmp); }
        $this->tmp = '';
    }

    public function testReadsPlainValue(): void
    {
        $p = $this->writeEnv("FOO=bar\nAPP_ENVIRONMENT=P\nBAZ=qux\n");
        try {
            $this->assertSame('P', Bootstrap::peekEnvVar($p, 'APP_ENVIRONMENT'));
            $this->assertSame('bar', Bootstrap::peekEnvVar($p, 'FOO'));
        } finally { $this->cleanup(); }
    }

    public function testStripsQuotesAndWhitespace(): void
    {
        $p = $this->writeEnv("APP_ENVIRONMENT = \"L\" \nNAME='Diederik'\n");
        try {
            $this->assertSame('L', Bootstrap::peekEnvVar($p, 'APP_ENVIRONMENT'));
            $this->assertSame('Diederik', Bootstrap::peekEnvVar($p, 'NAME'));
        } finally { $this->cleanup(); }
    }

    public function testSkipsCommentsAndBlankLines(): void
    {
        $p = $this->writeEnv("# comment\n\n#APP_ENVIRONMENT=X\nAPP_ENVIRONMENT=A\n");
        try {
            $this->assertSame('A', Bootstrap::peekEnvVar($p, 'APP_ENVIRONMENT'));
        } finally { $this->cleanup(); }
    }

    public function testReturnsNullForMissingKey(): void
    {
        $p = $this->writeEnv("FOO=bar\n");
        try {
            $this->assertNull(Bootstrap::peekEnvVar($p, 'APP_ENVIRONMENT'));
        } finally { $this->cleanup(); }
    }

    public function testReturnsNullForMissingFile(): void
    {
        $this->assertNull(Bootstrap::peekEnvVar(sys_get_temp_dir() . '/does-not-exist-' . uniqid() . '.env', 'APP_ENVIRONMENT'));
    }
}
