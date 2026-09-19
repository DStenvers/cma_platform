<?php
/**
 * PhpAdvice — the dashboard's PHP-environment advice for admins and developers.
 *
 *   php cma/tests/TestRunner.php PhpAdviceTest
 */
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/Services/PhpAdvice.php';

use Cma\Services\PhpAdvice;

class PhpAdviceTest extends TestCase
{
    private function all(string $version, string $jit = 'tracing', int $buffer = 64 * 1024 * 1024): array
    {
        return PhpAdvice::warnings($version, true, $jit, $buffer, true, 4096);
    }

    public function testAHealthyEnvironmentGetsNoAdvice(): void
    {
        $this->assertSame([], $this->all('8.4.2'));
        $this->assertSame([], $this->all('8.3.14'));
    }

    public function testOlderThan8314AdvisesAnUpgradeAndNamesTheOdbcDefect(): void
    {
        $w = $this->all('8.3.11');
        $this->assertCount(1, $w);
        $this->assertStringContainsString('PHP 8.3.11', $w[0]);
        $this->assertStringContainsString('8.3.14', $w[0]);
        $this->assertStringContainsString('PDO_ODBC', $w[0]);
        $this->assertCount(1, $this->all('8.2.30'), '8.2 is older too');
    }

    public function testJitOffIsAdvisedOnlyWhenOpcacheIsOn(): void
    {
        $w = $this->all('8.4.2', 'tracing', 0);
        $this->assertCount(1, $w);
        $this->assertStringContainsString('opcache.jit_buffer_size=64M', $w[0]);
        $this->assertCount(1, $this->all('8.4.2', 'off', 64 * 1024 * 1024), 'jit=off with a buffer is still off');
        $this->assertCount(1, $this->all('8.4.2', '0', 64 * 1024 * 1024));
        $this->assertSame([], $this->all('8.4.2', '1255', 64 * 1024 * 1024), 'the numeric spelling of tracing');

        $noOpcache = PhpAdvice::warnings('8.4.2', false, 'tracing', 0, true, 4096);
        $this->assertCount(1, $noOpcache, 'without OPcache only the OPcache warning, not also JIT');
        $this->assertStringContainsString('OPcache', $noOpcache[0]);
    }

    public function testApcuAndRealpathCache(): void
    {
        $w = PhpAdvice::warnings('8.4.2', true, 'tracing', 64 * 1024 * 1024, false, 16);
        $this->assertCount(2, $w);
        $this->assertStringContainsString('APCu', $w[0]);
        $this->assertStringContainsString('realpath_cache_size', $w[1]);
        $this->assertSame([], PhpAdvice::warnings('8.4.2', true, 'tracing', 64 * 1024 * 1024, true, 0), 'an unknown realpath size is not a warning');
    }

    public function testNoEmphasisTags(): void
    {
        foreach (PhpAdvice::warnings('8.3.11', false, '0', 0, false, 16) as $w) {
            $this->assertFalse((bool) preg_match('/<(b|i|strong|em)\b/', $w), 'geen nadruk-tags: ' . $w);
        }
    }
}
