<?php
/**
 * Tests for four small CMA form fixes (2026-10-26):
 *  - gebruikersscherm: groepen zonder naam niet als lege knopjes (JsonFormRenderer::renderSecurityGroups);
 *  - .checklist-item-inline: vinkje en tekst verticaal gecentreerd (was line-height:7px, tekst te laag);
 *  - mobiel: .toolbar-left padding-left:8px (de toolbar schoof het scherm af);
 *  - actief item in boom/lijst in de kleur van het actieve sidebar-item (--sidebar-active).
 *
 * Run with: php cma/tests/TestRunner.php CmaFormDetailsTest
 */

require_once __DIR__ . '/TestRunner.php';

class CmaFormDetailsTest extends TestCase
{
    private function css(): string
    {
        return (string) file_get_contents(__DIR__ . '/../assets/css/form.css');
    }

    private function regel(string $css, string $selector): string
    {
        // Commentaar eruit: de toelichting noemt de oude waarde (line-height: 7px).
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        return preg_match('/(?:^|\})\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/m', $css, $m) ? $m[1] : '';
    }

    public function testUnnamedGroupsAreSkippedUnlessMember(): void
    {
        $src = (string) file_get_contents(__DIR__ . '/../classes/JsonFormRenderer.php');
        $this->assertTrue(str_contains($src, "\$groupName = trim((string) \$rs->fields['grpName']);"));
        $this->assertTrue(str_contains($src, "if (\$groupName === '') {\n                if (\$checked === '') {\n                    \$rs->MoveNext();\n                    continue;"), 'lege naam en geen lid: overslaan');
        $this->assertTrue(str_contains($src, "'(naamloos, groep ' . \$groupId . ')'"), 'wel lid: blijft staan (anders wist opslaan het lidmaatschap)');
    }

    public function testChecklistItemIsVerticallyCentred(): void
    {
        $r = $this->regel($this->css(), '.checklist-item-inline');
        $this->assertTrue(str_contains($r, 'display: inline-flex;') && str_contains($r, 'align-items: center;'));
        $this->assertFalse(str_contains($r, 'line-height: 7px'), 'de oude line-height duwde de tekst omlaag');
        $this->assertTrue(str_contains($this->regel($this->css(), '.checklist-item-inline input[type="checkbox"]'), 'margin: 0;'));
    }

    public function testMobileToolbarLeftHasPadding(): void
    {
        $this->assertEquals(1, preg_match('/\.toolbar-left \{\s*flex: 1 1;[^}]*padding-left: 8px;/', $this->css()));
    }

    public function testActiveTreeItemUsesSidebarActive(): void
    {
        $this->assertTrue(str_contains($this->css(), "#listContent a.active::before {\n    background-color: var(--sidebar-active) !important;"));
    }
}
