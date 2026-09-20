<?php
/**
 * Cma\Services\TemplateService — editable regions in page templates: parsing
 * the tags, reading the current values, writing values back, the datestamp.
 *
 *   php cma/tests/TestRunner.php TemplateServiceTest
 */
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/Services/TemplateService.php';

use Cma\Services\TemplateService;

class TemplateServiceTest extends TestCase
{
    private function page(): string
    {
        return "<html><head>\n"
            . "<!-- #beginedit type=title,name=titel,pre=<title>,post=</title>,required=yes -->\n<title>Welkom &amp; tot ziens</title>\n<!-- #endedit -->\n"
            . "<!-- #beginedit type=keyword,name=trefwoorden --><meta name=\"KEYWORD\" content=\"rino, opleiding\"><!-- #endedit -->\n"
            . "<!-- #beginedit type=description,name=omschrijving --><META name=\"DESCRIPTION\" content=\"Over &quot;ons&quot;\"><!-- #endedit -->\n"
            . "</head><body>\n<h1>Vast</h1>\n"
            . "<!-- #beginedit type=textarea,name=intro tekst,html=yes,height=8 -->\n<p>Hallo <b>wereld</b></p>\n<!-- #endedit -->\n"
            . "<!-- #beginedit type=text,name=onderschrift,size=120 -->Prijs &lt; 10 &euro;<!-- #endedit -->\n"
            . "<p>Bijgewerkt: <!-- #beginedit type=datestamp,format=dd-mm-jjjj -->1/1/2020<!-- #endedit --></p>\n"
            . "</body></html>";
    }

    public function testParseFindsEveryRegionInOrderWithItsSettings(): void
    {
        $r = TemplateService::parse($this->page());
        $this->assertCount(6, $r);
        $this->assertSame(['title', 'keyword', 'description', 'textarea', 'text', 'datestamp'], array_column($r, 'type'));
        $this->assertSame('required-titel', $r[0]['field'], 'required prefixes the field name');
        $this->assertSame('<title>', $r[0]['pre']);
        $this->assertSame('intro_tekst', $r[3]['field'], 'spaces become underscores');
        $this->assertTrue($r[3]['html']);
        $this->assertSame(8, $r[3]['height']);
        $this->assertSame(120, $r[4]['size']);
        $this->assertSame('dd-mm-yyyy', $r[5]['format'], 'a Dutch J year reads as Y');
    }

    public function testParseReadsTheCurrentValues(): void
    {
        $r = TemplateService::parse($this->page());
        $this->assertSame('Welkom & tot ziens', $r[0]['value'], 'pre/post stripped, entities decoded');
        $this->assertSame('rino, opleiding', $r[1]['value'], 'the content attribute of the meta tag');
        $this->assertSame('Over "ons"', $r[2]['value'], 'case-insensitive META, entities decoded');
        $this->assertSame('<p>Hallo <b>wereld</b></p>', $r[3]['value'], 'HTML kept as-is');
        $this->assertSame('Prijs < 10 €', $r[4]['value']);
    }

    public function testDefaultsWhenTheTagSaysNothing(): void
    {
        $r = TemplateService::parse('<!-- #beginedit --><!-- #endedit -->');
        $this->assertCount(1, $r);
        $this->assertSame(['text', 'field', 'field', 80, 6, false, false], [$r[0]['type'], $r[0]['name'], $r[0]['field'], $r[0]['size'], $r[0]['height'], $r[0]['html'], $r[0]['required']]);
        $this->assertSame('text', TemplateService::parse('<!-- #beginedit type=bogus --><!-- #endedit -->')[0]['type'], 'an unknown type falls back to text');
    }

    public function testRenderWritesValuesBackAndLeavesTheRestUntouched(): void
    {
        $now = mktime(12, 0, 0, 9, 20, 2026);
        $out = TemplateService::render($this->page(), [
            'required-titel' => 'Nieuw & beter',
            'trefwoorden' => 'a, "b"',
            'omschrijving' => 'Kort',
            'intro_tekst' => '<p>Dag</p>',
            'onderschrift' => '1 < 2',
        ], $now);

        $this->assertStringContainsString("<!-- #beginedit type=title,name=titel,pre=<title>,post=</title>,required=yes --><title>Nieuw &amp; beter</title><!-- #endedit -->", $out);
        $this->assertStringContainsString('<meta name="KEYWORD" content="a, &quot;b&quot;">', $out);
        $this->assertStringContainsString('<meta name="DESCRIPTION" content="Kort">', $out);
        $this->assertStringContainsString('<!-- #beginedit type=textarea,name=intro tekst,html=yes,height=8 --><p>Dag</p><!-- #endedit -->', $out, 'HTML stored raw');
        $this->assertStringContainsString('-->1 &lt; 2<!-- #endedit -->', $out, 'plain text stored encoded');
        $this->assertStringContainsString('<!-- #beginedit type=datestamp,format=dd-mm-jjjj -->20-9-2026<!-- #endedit -->', $out, 'datestamp rewritten, no value needed');
        $this->assertStringContainsString("<h1>Vast</h1>", $out);
        $this->assertTrue(str_starts_with($out, "<html><head>\n"));
        $this->assertTrue(str_ends_with($out, "</body></html>"));
    }

    public function testRenderThenParseRoundTrips(): void
    {
        $out = TemplateService::render($this->page(), ['required-titel' => 'A & B', 'trefwoorden' => 'x', 'omschrijving' => 'y "z"', 'intro_tekst' => '<i>q</i>', 'onderschrift' => 'p < q'], 0);
        $again = TemplateService::parse($out);
        $this->assertSame(['A & B', 'x', 'y "z"', '<i>q</i>', 'p < q'], array_column(array_slice($again, 0, 5), 'value'));
        $this->assertSame($out, TemplateService::render($out, ['required-titel' => 'A & B', 'trefwoorden' => 'x', 'omschrijving' => 'y "z"', 'intro_tekst' => '<i>q</i>', 'onderschrift' => 'p < q'], 0), 'rendering the same values again changes nothing');
    }

    public function testAMissingValueEmptiesTheRegionInsteadOfBreakingTheFile(): void
    {
        $out = TemplateService::render($this->page(), [], 0);
        $this->assertStringContainsString('--><title></title><!-- #endedit -->', $out);
        $this->assertStringContainsString('<h1>Vast</h1>', $out);
    }

    public function testAnUnterminatedRegionStopsParsingWithoutEatingTheFile(): void
    {
        $broken = "a<!-- #beginedit type=text,name=x -->value";
        $this->assertSame([], TemplateService::parse($broken));
        $this->assertSame($broken, TemplateService::render($broken, ['x' => 'y']), 'nothing to write, file unchanged');
    }

    public function testIsTemplateAndTitle(): void
    {
        $this->assertTrue(TemplateService::isTemplate($this->page()));
        $this->assertFalse(TemplateService::isTemplate('<html><title>x</title></html>'));
        $this->assertSame('Welkom & tot ziens', TemplateService::title($this->page(), 'bestand.php'));
        $this->assertSame('bestand.php', TemplateService::title('<html></html>', 'bestand.php'));
        $this->assertSame('Open', TemplateService::title('<title>Open', 'x'), 'an unclosed title still counts');
    }

    public function testDatestampPatterns(): void
    {
        $now = mktime(0, 0, 0, 3, 7, 2026);
        $this->assertSame('7/3/2026', TemplateService::datestamp('dd/mm/yyyy', $now));
        $this->assertSame('7-3-26', TemplateService::datestamp('dd-mm-yy', $now));
        $this->assertSame('2026', TemplateService::datestamp('yyyy', $now));
    }
}
