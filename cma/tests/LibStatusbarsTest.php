<?php
/**
 * Tests for library/webcomponents/lib-statusbars.js
 *
 * WHY THIS EXISTS
 * lib-statusbars is the chart behind site-injected dashboard cards (see
 * documentation.php topic "dashboard_cards"): a site's endpoint delivers a
 * JSON payload and this component turns it into stacked status bars. That
 * makes its data contract a cross-repo agreement — a site built against it
 * must keep rendering when the component evolves. The tests below pin the
 * contract points in the shipped source: the kind→colour mapping with its
 * muted fallback, the zero-segment behaviour, the double-declaration guard
 * and the update() path.
 *
 * Run with: php cma/tests/TestRunner.php LibStatusbarsTest
 */

require_once __DIR__ . '/TestRunner.php';

class LibStatusbarsTest extends TestCase
{
    private function source(): string
    {
        $path = dirname(__DIR__, 2) . '/library/webcomponents/lib-statusbars.js';
        $this->assertTrue(is_file($path), 'library/webcomponents/lib-statusbars.js is missing');
        return (string)file_get_contents($path);
    }

    /** The body of one top-level method, brace-matched. */
    private function methodBody(string $src, string $name): string
    {
        $gevonden = preg_match('/\n\s*' . preg_quote($name, '/') . '\s*\([^)]*\)\s*\{/', $src, $m, PREG_OFFSET_CAPTURE);
        $this->assertTrue($gevonden === 1, "method $name() not found");
        $start = $m[0][1];
        $open = strpos($src, '{', $start);
        $depth = 0;
        for ($i = $open; $i < strlen($src); $i++) {
            if ($src[$i] === '{') { $depth++; }
            elseif ($src[$i] === '}') {
                $depth--;
                if ($depth === 0) { return substr($src, $open, $i - $open + 1); }
            }
        }
        $this->assertTrue(false, "method $name() is not brace-balanced");
        return '';
    }

    public function testGuardsAgainstDoubleDeclaration(): void
    {
        $src = $this->source();
        $this->assertTrue(strpos($src, "if (!customElements.get('lib-statusbars'))") !== false,
            'the double-declaration guard is gone');
        $this->assertTrue(strpos($src, "customElements.define('lib-statusbars'") !== false,
            'the element is no longer defined');
    }

    public function testEveryKindMapsToItsColourVariable(): void
    {
        $body = $this->methodBody($this->source(), '_kindColor');
        foreach (['success', 'info', 'warning', 'error'] as $kind) {
            $this->assertTrue(strpos($body, "$kind: 'var(--color-$kind") !== false,
                "kind '$kind' no longer maps to var(--color-$kind)");
        }
        // Muted is the fallback: an unknown kind must render visibly, not vanish.
        $this->assertTrue(strpos($body, 'colors[kind] || colors.muted') !== false,
            'unknown kinds no longer fall back to muted');
    }

    public function testZeroSegmentsAreSkipped(): void
    {
        // A zero-value segment renders as nothing rather than a sliver; the
        // row filter is the contract point sites rely on when they always send
        // every segment and let the component drop the empty ones.
        $body = $this->methodBody($this->source(), '_buildRow');
        $this->assertTrue(strpos($body, 'Number(s.value) > 0') !== false,
            'zero-value segments are no longer filtered out');
    }

    public function testBrokenAttributeJsonRendersInsteadOfThrowing(): void
    {
        $body = $this->methodBody($this->source(), '_parseData');
        $this->assertTrue(strpos($body, 'catch') !== false && strpos($body, 'return null') !== false,
            'broken attribute JSON no longer degrades to null');
    }

    public function testUpdateReplacesTheDataAndRerenders(): void
    {
        $src = $this->source();
        $body = $this->methodBody($src, 'update');
        $this->assertTrue(strpos($body, 'this.data = value') !== false,
            'update() no longer routes through the data setter');
        $setter = $this->methodBody($src, 'set data');
        $this->assertTrue(strpos($setter, 'this.render()') !== false,
            'setting data no longer re-renders');
    }

    public function testLegendDeduplicatesAcrossRows(): void
    {
        $body = $this->methodBody($this->source(), '_buildLegend');
        $this->assertTrue(strpos($body, 'if (seen[key]) continue') !== false,
            'the legend no longer deduplicates label+kind pairs');
    }
}
