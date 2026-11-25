<?php
/**
 * Stapelen van lib-dialog (library/webcomponents/lib-dialog.js): LibDialog.stack, LibDialog.stackOffset, het attribuut stack,
 * en Escape/Enter alleen op het bovenste dialoog. Statische controle op de bron en de gebouwde .min.js; het gedrag in de
 * browser (verschuiving, lichtere achtergrond, Escape sluit alleen het bovenste) is met Chromium nagelopen.
 *
 * Run with: php tests/TestRunner.php LibDialogStackTest
 */

require_once __DIR__ . '/TestRunner.php';

class LibDialogStackTest extends TestCase
{
    private function lees(string $bestand): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/library/webcomponents/' . $bestand);
    }

    public function testInstellingenEnStandaardUit(): void
    {
        $js = $this->lees('lib-dialog.js');
        $this->assertStringContainsString('LibDialog.stack = false;', $js);
        $this->assertStringContainsString('LibDialog.stackOffset = 24;', $js);
        $this->assertStringContainsString("this.getAttribute('stack')", $js);
    }

    public function testOpenEnCloseHoudenDeStapelBij(): void
    {
        $js = $this->lees('lib-dialog.js');
        $this->assertStringContainsString('LibDialog._register(this);', $js);
        $this->assertStringContainsString('LibDialog._unregister(this);', $js);
        $this->assertStringContainsString('LibDialog._refreshStack();', $js);
    }

    public function testToetsenwerkenAlleenOpHetBovensteDialoog(): void
    {
        $this->assertStringContainsString('if (!LibDialog._isTop(this)) return;', $this->lees('lib-dialog.js'));
    }

    public function testStijlVerschuiftMetTranslateEnDimtDeAchtergrond(): void
    {
        $js = $this->lees('lib-dialog.js');
        $this->assertStringContainsString('.dialog-backdrop.stacked .dialog-content', $js);
        $this->assertStringContainsString('translate: var(--lib-dialog-stack-offset', $js);
        $this->assertStringContainsString('background: rgba(0, 0, 0, 0.18)', $js);
    }

    public function testMinBestandIsBijgewerkt(): void
    {
        $this->assertStringContainsString('stackOffset', $this->lees('lib-dialog.min.js'));
    }
}
