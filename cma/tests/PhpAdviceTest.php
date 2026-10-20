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
    private function all(string $version): array
    {
        return PhpAdvice::warnings($version, true, true, 4096);
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

    public function testNoJitAdvice(): void
    {
        // JIT aanzetten gaf zwarte schermen; het dashboard mag er niet meer toe aansporen.
        foreach (PhpAdvice::warnings('8.3.11', false, false, 16) as $w) {
            $this->assertFalse(stripos($w, 'jit') !== false, 'het dashboard noemt de JIT: ' . $w);
        }
        $this->assertFalse(method_exists(PhpAdvice::class, 'jitOn'), 'jitOn() hoort weg te zijn');

        $noOpcache = PhpAdvice::warnings('8.4.2', false, true, 4096);
        $this->assertCount(1, $noOpcache, 'zonder OPcache alleen de OPcache-melding');
        $this->assertStringContainsString('OPcache', $noOpcache[0]);
    }

    public function testApcuAndRealpathCache(): void
    {
        $w = PhpAdvice::warnings('8.4.2', true, false, 16);
        $this->assertCount(2, $w);
        $this->assertStringContainsString('APCu', $w[0]);
        $this->assertStringContainsString('realpath_cache_size', $w[1]);
        $this->assertSame([], PhpAdvice::warnings('8.4.2', true, true, 0), 'an unknown realpath size is not a warning');
    }

    public function testNoEmphasisTags(): void
    {
        foreach (PhpAdvice::warnings('8.3.11', false, false, 16) as $w) {
            $this->assertFalse((bool) preg_match('/<(b|i|strong|em)\b/', $w), 'geen nadruk-tags: ' . $w);
        }
    }
}
