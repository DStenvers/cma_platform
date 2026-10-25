<?php
/**
 * Tests for the hover style of a CMA menu group header (cma/assets/css/main.css).
 *
 * The hover only changed the background, which was too subtle. Icon and title now
 * take the blue of the group arrow on the right (--color-info).
 *
 * Run with: php cma/tests/TestRunner.php MenuGroupHoverTest
 */

require_once __DIR__ . '/TestRunner.php';

class MenuGroupHoverTest extends TestCase
{
    private function css(): string
    {
        return (string) file_get_contents(__DIR__ . '/../assets/css/main.css');
    }

    private function colorOf(string $selector): string
    {
        $css = $this->css();
        $pattern = '/(?:^|,|\})\s*' . preg_quote($selector, '/') . '\s*(?:,[^{]*)?\{([^}]*)\}/m';
        if (!preg_match_all($pattern, $css, $m)) {
            return '';
        }
        $color = '';
        foreach ($m[1] as $body) {
            if (preg_match('/(?:^|;|\s)color:\s*([^;]+);/', $body, $c)) {
                $color = trim($c[1]);
            }
        }
        return $color;
    }

    public function testArrowIsTheInfoBlue(): void
    {
        $this->assertSame('var(--color-info, #077ab2)', $this->colorOf('.cma-menu-group-arrow'));
    }

    public function testHoveredIconTakesArrowBlue(): void
    {
        $this->assertSame($this->colorOf('.cma-menu-group-arrow'), $this->colorOf('.cma-menu-group-header:hover .cma-menu-group-icon'));
    }

    public function testHoveredTitleTakesArrowBlue(): void
    {
        $this->assertSame($this->colorOf('.cma-menu-group-arrow'), $this->colorOf('.cma-menu-group-header:hover .cma-menu-group-title'));
    }
}
