<?php
/**
 * Tests for the corners of <lib-radio-group> options (library/css/lib-components.css,
 * cma/assets/css/style.css).
 *
 * Gemeld: "the individual buttons should blend in, so the right button should have no
 * border-left radius, the middle border-radius:0px and the left button only
 * border-left-radius." In CMA-formulieren kreeg elke knop border-radius:4px (style.css),
 * en die regel won van de randregels van het component.
 *
 * Run with: php cma/tests/TestRunner.php RadioGroupHoekenTest
 */

require_once __DIR__ . '/TestRunner.php';

class RadioGroupHoekenTest extends TestCase
{
    private function regel(string $css, string $selector): string
    {
        return preg_match('/(?:^|\})\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/m', $css, $m) ? $m[1] : '';
    }

    public function testMiddleSquareLeftOnlyLeftRightOnlyRight(): void
    {
        $css = (string) file_get_contents(__DIR__ . '/../../library/css/lib-components.css');
        $alle = $this->regel($css, '.lib-radio-group .lib-radio-group__option');
        $eerste = $this->regel($css, '.lib-radio-group .lib-radio-group__option:first-child');
        $laatste = $this->regel($css, '.lib-radio-group .lib-radio-group__option:last-child');
        $this->assertTrue(str_contains($alle, 'border-radius: 0;'), 'elke knop standaard vierkant (de middelste)');
        $this->assertTrue(str_contains($eerste, 'border-top-left-radius') && str_contains($eerste, 'border-bottom-left-radius'), 'linkerknop links rond');
        $this->assertFalse(str_contains($eerste, 'right-radius'), 'linkerknop rechts niet rond');
        $this->assertTrue(str_contains($laatste, 'border-top-right-radius') && str_contains($laatste, 'border-bottom-right-radius'), 'rechterknop rechts rond');
        $this->assertFalse(str_contains($laatste, 'left-radius'), 'rechterknop links niet rond');
    }

    public function testCmaFormOverrideNoLongerRoundsEveryOption(): void
    {
        $style = (string) file_get_contents(__DIR__ . '/../assets/css/style.css');
        $this->assertFalse((bool) preg_match('/\.lib-radio-group__option\s*\{[^}]*border-radius/', $style), 'geen border-radius op elke knop in CMA-formulieren');
    }
}
