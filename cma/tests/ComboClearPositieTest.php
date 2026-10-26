<?php
/**
 * Tests for the position of the clear cross (.combo-clear) in <lib-combo>
 * (library/webcomponents/lib-combo.js, shadow-DOM styles).
 *
 * Gemeld: het kruisje stond te hoog in het veld; top: 48% -> 52% zet het in het midden.
 *
 * Run with: php cma/tests/TestRunner.php ComboClearPositieTest
 */

require_once __DIR__ . '/TestRunner.php';

class ComboClearPositieTest extends TestCase
{
    private function rule(): string
    {
        $js = (string) file_get_contents(__DIR__ . '/../../library/webcomponents/lib-combo.js');
        return preg_match('/\.combo-clear\s*\{([^}]*)\}/', $js, $m) ? $m[1] : '';
    }

    public function testClearCrossIsVerticallyCentred(): void
    {
        $rule = $this->rule();
        $this->assertTrue(str_contains($rule, 'top: 52%;'), 'kruisje op 52%');
        $this->assertTrue(str_contains($rule, 'right: 13px;'), 'rechts op 13px');
        $this->assertTrue(str_contains($rule, 'position: absolute;'));
    }
}
