<?php
/**
 * Het onderhoudsscherm: regeleinden in het bericht komen op het scherm (nieuwe regel of letterlijke <br>), de rest wordt geëscaped,
 * en er is geen "Vernieuwen"-knop meer maar een korte regel (de pagina ververst zichzelf elke 60 seconden).
 */
require_once __DIR__ . '/TestRunner.php';

class MaintenancePageBerichtTest extends TestCase
{
    private function render(string $bericht, bool $retry = true): string
    {
        $cmaMaintPreview = true;
        $cmaMaintPreviewRetry = $retry;
        $message = $bericht;
        ob_start();
        include dirname(__DIR__) . '/maintenance_page.inc';
        return (string) ob_get_clean();
    }

    public function testNieuweRegelWordtEenRegelafbreking(): void
    {
        $html = $this->render("Wij zijn druk bezig om\nmijn•RINO nog beter te maken.");
        $this->assertStringContainsString("Wij zijn druk bezig om<br>\nmijn•RINO nog beter te maken.", $html);
    }

    public function testLetterlijkeBrBlijftEenRegelafbreking(): void
    {
        $html = $this->render('Wij zijn druk bezig om<br>mijn•RINO nog beter te maken.');
        $this->assertStringContainsString('<h1>Wij zijn druk bezig om<br>' . "\n" . 'mijn•RINO nog beter te maken.</h1>', $html);
        $this->assertStringNotContainsString('&lt;br', $html);
    }

    public function testOverigeHtmlWordtGeescaped(): void
    {
        $html = $this->render('Hallo <script>alert(1)</script> <b>vet</b>');
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testGeenVernieuwKnopMaarEenKorteRegel(): void
    {
        $html = $this->render('Bericht');
        $this->assertStringNotContainsString('Vernieuwen', $html);
        $this->assertStringNotContainsString('location.reload', $html);
        $this->assertStringContainsString('Zodra de site weer online is, ziet u dat vanzelf.', $html);
    }

    public function testPaginaVerverstZichzelfBuitenHetVoorbeeld(): void
    {
        $cmaMaintPreview = false;
        $this->assertTrue(strpos((string) file_get_contents(dirname(__DIR__) . '/maintenance_page.inc'), 'http-equiv="refresh" content="60"') !== false);
    }
}
