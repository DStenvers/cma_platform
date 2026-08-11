<?php
/**
 * Een onbekende kolom moet bij naam genoemd worden.
 *
 * Access meldt een tikfout in een kolomnaam niet als kolomfout. Het ziet er een
 * parameter in en zegt "Er zijn te weinig parameters. Het verwachte aantal is:
 * 3" — een getal, zonder één naam erbij. Wie dat leest weet dat er drie namen
 * fout zijn, maar niet wélke, en gaat de query met de hand naast de tabel
 * leggen. Dat is werk dat de code zelf kan doen.
 *
 * Gebeurde echt: een formulierdefinitie noemde drie velden die niet in de tabel
 * staan. De lijst bleef leeg, de melding stond alleen in de console, en er stond
 * geen enkele kolomnaam in.
 *
 * Run met: php tests/TestRunner.php QueryFoutUitlegTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/Services/ListServiceHelper.php';

use Cma\Services\ListServiceHelper;

class QueryFoutUitlegTest extends TestCase
{
    /** De query zoals de bouwer hem opschrijft: namen tussen blokhaken. */
    private const SQL = 'SELECT TOP 201 [ID], [fkDeelname], [verslagnummer], [Reminder_Deelnemer],'
        . ' [Reminder_Praktijkopl], [Reminder_Hoofdopleider] FROM [tblCompetenties]'
        . ' WHERE [fkDeelname] = 17 ORDER BY [ID] ASC';

    private const KOLOMMEN = ['ID', 'fkDeelname', 'fkCompetentieTemplate', 'verslagnummer', 'startdatum'];

    public function testNoemtDeKolommenDieNietBestaan(): void
    {
        $uitleg = ListServiceHelper::benoemOntbrekendeKolommen(self::SQL, 'tblCompetenties', self::KOLOMMEN);

        $this->assertNotNull($uitleg);
        $this->assertStringContainsString('Reminder_Deelnemer', $uitleg);
        $this->assertStringContainsString('Reminder_Praktijkopl', $uitleg);
        $this->assertStringContainsString('Reminder_Hoofdopleider', $uitleg);
        $this->assertStringContainsString('tblCompetenties', $uitleg);
    }

    public function testNoemtBestaandeKolommenNiet(): void
    {
        $uitleg = ListServiceHelper::benoemOntbrekendeKolommen(self::SQL, 'tblCompetenties', self::KOLOMMEN);

        $this->assertStringNotContainsString('verslagnummer', $uitleg);
        $this->assertStringNotContainsString('[ID]', $uitleg);
    }

    /** Hoofdlettergebruik verschilt tussen definitie en tabel; dat is geen fout. */
    public function testHoofdletterOngevoelig(): void
    {
        $sql = 'SELECT [id], [FKDEELNAME] FROM [tblCompetenties]';
        $this->assertNull(ListServiceHelper::benoemOntbrekendeKolommen($sql, 'tblCompetenties', self::KOLOMMEN));
    }

    /** Klopt alles, dan valt er niets te melden — en verzint hij niets. */
    public function testZwijgtAlsAlleKolommenBestaan(): void
    {
        $sql = 'SELECT [ID], [verslagnummer] FROM [tblCompetenties] ORDER BY [ID]';
        $this->assertNull(ListServiceHelper::benoemOntbrekendeKolommen($sql, 'tblCompetenties', self::KOLOMMEN));
    }

    /**
     * Alleen de SELECT telt. Een WHERE op een kolom die niet bestaat is een
     * ander verhaal; hier gaat het om de kolommen die worden opgehaald, en de
     * uitleg mag niet gaan gokken over de rest van de query.
     */
    public function testKijktAlleenNaarDeSelect(): void
    {
        $sql = 'SELECT [ID] FROM [tblCompetenties] WHERE [BestaatNiet] = 1 ORDER BY [OokNiet]';
        $this->assertNull(ListServiceHelper::benoemOntbrekendeKolommen($sql, 'tblCompetenties', self::KOLOMMEN));
    }

    /**
     * Zonder kolomlijst is er niets om mee te vergelijken. Dan liever niets
     * zeggen dan alles als ontbrekend aanmerken — een diagnose die alles
     * verdacht maakt, wijst nergens heen.
     */
    public function testZwijgtZonderKolomlijst(): void
    {
        $this->assertNull(ListServiceHelper::benoemOntbrekendeKolommen(self::SQL, 'tblCompetenties', []));
    }

    /** Een query die niet als SELECT te lezen is, levert geen uitleg op. */
    public function testZwijgtBijOnleesbareQuery(): void
    {
        $this->assertNull(ListServiceHelper::benoemOntbrekendeKolommen('EXEC iets', 'tblCompetenties', self::KOLOMMEN));
    }
}
