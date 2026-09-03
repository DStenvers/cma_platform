<?php
/**
 * ClearCacheOpcacheBodemTest — het cachescherm moet meten wat het beweert te meten.
 *
 * WAAROM DEZE TEST ER IS. Na "Alle beschikbare caches zijn geleegd" stond OPcache op
 * 132 items, en dat leest als een restant dat blijft hangen. Dat is het niet: OPcache
 * flusht uitgesteld (pas bij het volgende verzoek), en de tellingen op het scherm zijn
 * standen van vlak vóór het legen. Bovendien is er een bodem — deze pagina compileert
 * zelf tientallen PHP-bestanden, en die staan na de herstart meteen weer in de cache,
 * want er is nu eenmaal een verzoek nodig om het je te laten zien.
 *
 * Het scherm zei dat ook, maar de regel die het had moeten bewijzen deugde niet: onder
 * het label "Bootstrap scripts" stond opnieuw opcache_get_status() — de globale teller
 * van de hele app-pool, niet wat dit verzoek laadde. Door de uitgestelde flush was dat
 * hetzelfde getal als hierboven. Een label dat iets anders beloofde dan het toonde,
 * precies bij het getal waar iedereen naar kijkt.
 *
 * Vandaar deze test: het per-verzoek getal komt uit get_included_files(), en nergens
 * anders vandaan.
 *
 *   php cma/tests/TestRunner.php ClearCacheOpcacheBodemTest
 */

require_once __DIR__ . '/TestRunner.php';

class ClearCacheOpcacheBodemTest extends TestCase
{
    private function bron(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/tools/tools_clearcache.php');
    }

    public function testDeBodemWordtGemetenMetGetIncludedFiles(): void
    {
        $bron = $this->bron();
        $this->assertStringContainsString(
            '$_ditVerzoekBestanden = count(get_included_files());',
            $bron,
            'alleen get_included_files() telt DIT verzoek'
        );
    }

    public function testHetOudeMisleidendeLabelIsWeg(): void
    {
        $bron = $this->bron();
        // Op het scherm, niet in de toelichting: de uitleg mag het oude label juist
        // wél noemen, anders is niet meer na te lezen wat er mis was.
        $this->assertStringNotContainsString("'Bootstrap scripts' =>", $bron,
            'het label beloofde de bootstrap en toonde de hele app-pool');
        $this->assertStringNotContainsString('$_bootstrapOpcacheCount', $bron,
            'de variabele die het verkeerde getal droeg is weg');
    }

    public function testHetPerVerzoekGetalKomtNietUitOpcacheStatus(): void
    {
        // De valkuil: opcache_get_status() geeft door de uitgestelde flush hetzelfde
        // getal als vóór het legen, dus daar is een bodem nooit uit af te leiden.
        $bron = $this->bron();
        $pos = strpos($bron, '$_ditVerzoekBestanden =');
        $this->assertTrue($pos !== false, 'de meting moet bestaan');

        $regel = substr($bron, $pos, (int) strpos($bron, ';', $pos) - $pos);
        $this->assertStringNotContainsString('opcache_get_status', $regel);
        $this->assertStringNotContainsString('num_cached_scripts', $regel);
    }

    public function testDeUitlegNoemtDeGemetenBodem(): void
    {
        $bron = $this->bron();
        $this->assertStringContainsString('bodem', $bron, 'de uitleg moet het woord noemen');
        // En met het gemeten getal erin, niet als los verhaal.
        $uitleg = substr($bron, (int) strpos($bron, 'Waarom deze aantallen nooit 0 worden'));
        $this->assertStringContainsString('$_ditVerzoekBestanden', $uitleg,
            'de uitleg toont het gemeten aantal, niet alleen een bewering');
    }

    public function testDeUitgesteldeFlushBlijftUitgelegd(): void
    {
        // De andere helft van het antwoord: de telling staat er nog omdat OPcache
        // pas bij het volgende verzoek leegt. Die uitleg mag niet sneuvelen.
        $bron = $this->bron();
        $this->assertStringContainsString('restart_pending', $bron);
        $this->assertStringContainsString('Herstart gepland', $bron);
    }
}
