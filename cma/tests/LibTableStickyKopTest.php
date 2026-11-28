<?php
/**
 * De kop van een lib-table blijft staan onder de toolbar van tools- en rapportpagina's (in een echte browser geverifieerd:
 * zonder deze regel schuift de kop met top:0 achter de sticky toolbar, z-index 100, weg).
 */
class LibTableStickyKopTest extends TestCase
{
    private function css(): string
    {
        return (string) file_get_contents(__DIR__ . '/../assets/css/style.css');
    }

    public function testKopStaatOnderDeToolbarOpToolsEnRapportpaginas(): void
    {
        $css = $this->css();
        $this->assertTrue(preg_match('/body\.contentbody\.tools:has\(> \.toolbar-top\) lib-table table thead[^{]*\{\s*top:\s*var\(--toolbar-height\)/s', $css) === 1, 'tools');
        $this->assertTrue(strpos($css, 'body.contentbody.reportdetails:has(> .toolbar-top) lib-table table thead') !== false, 'rapporten');
    }

    public function testStandaardKopBlijftTopNul(): void
    {
        $css = (string) file_get_contents(__DIR__ . '/../../library/webcomponents/lib-table.css');
        $this->assertTrue(preg_match('/lib-table table thead,\s*\.listtable thead\s*\{[^}]*top:\s*0;/s', $css) === 1);
    }
}
