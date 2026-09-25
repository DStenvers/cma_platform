<?php

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__, 2) . '/library/lib_html.inc';
require_once dirname(__DIR__, 2) . '/library/lib_url.inc';

if (!class_exists('Str')) {
    class_alias(\App\Library\Str::class, 'Str');
}

/**
 * lib_friendlyURL(): witte lijst (a-z, 0-9, punt), verder enkele streepjes. Dezelfde
 * gevallen als de ASP-versie in de library van de sites, zodat beide dezelfde slug maken.
 */
class FriendlyUrlTest extends TestCase
{
    public function testCurlyQuotesVerdwijnenZonderStreepje(): void
    {
        $this->assertEquals('zorg-in-de-wijk-we-hebben-het-verschil-geleerd',
            lib_friendlyURL('Zorg in de wijk: ‘We hebben het verschil geleerd’'));
    }

    public function testMerktekenEnZachtAfbreekstreepje(): void
    {
        $this->assertEquals('verdiepingscursus-verbindend-gezag-en-geweldloos-verzet',
            lib_friendlyURL('Verdiepingscursus Verbindend Gezag® en Geweldloos Verzet'));
        $this->assertEquals('modulaire-opleiding-systeemtherapie',
            lib_friendlyURL("Modulaire opleiding systeem\u{00AD}thera\u{00AD}pie"));
    }

    public function testPuntenBlijvenStaan(): void
    {
        $this->assertEquals('prof.-dr.-paul-boelen', lib_friendlyURL('prof. dr. Paul Boelen'));
        $this->assertEquals('prof.-dr.-esther-van-den-berg', lib_friendlyURL("prof. dr. Esther\u{00A0}van den Berg"));
        $this->assertEquals('afl.-5.-in-de-serie', lib_friendlyURL('Afl. 5. In de serie'));
    }

    public function testAccentenEnSpecialeLetters(): void
    {
        $this->assertEquals('drs.-jorgen-mous', lib_friendlyURL('drs. Jørgen Mous'));
        $this->assertEquals('saniye-yucel-ozturk', lib_friendlyURL('Saniye Yücel-Öztürk'));
        $this->assertEquals('veel-culturen-een-zorg', lib_friendlyURL('Veel culturen, één zorg'));
    }

    public function testStreepjesEnRanden(): void
    {
        $this->assertEquals('ggz-volwassenen-psychopathologie-en-diagnostiek',
            lib_friendlyURL('Ggz volwassenen - Psychopathologie en diagnostiek'));
        $this->assertEquals('clients-100-procent-focus-en-groei', lib_friendlyURL("Cliënt's 100% focus & ... groei!"));
        $this->assertEquals('begin-en-eind', lib_friendlyURL('...Begin - en eind...'));
        $this->assertEquals('frederique-geven-jr', lib_friendlyURL('Frédérique Geven jr.'));
        $this->assertEquals('', lib_friendlyURL(''));
    }
}
