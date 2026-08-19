<?php
/**
 * FormComboLabelTest — de omschrijving bij een gekozen combo-waarde, niet het kale ID.
 *
 * Draaien met: php cma/tests/TestRunner.php FormComboLabelTest
 *
 * DE FOUT. Bij het openen van een record vraagt de combo het LABEL op bij de opgeslagen
 * waarde. Die opzoek-query werd zo gebouwd:
 *
 *     SQL::addWhere($sql, quoteIdentifier(...) . " = " . is_numeric($id) ? postNumber($id) : postString($id))
 *
 * In PHP bindt de punt sterker dan ?:, dus dat leest als
 * ("... = " . is_numeric($id)) ? postNumber($id) : postString($id). De voorwaarde is een
 * niet-lege string en dus altijd waar, en wat overblijft is alleen de WAARDE: de voorwaarde
 * werd "WHERE 218" in plaats van "WHERE tblOpleidingenBlokken.ID = 218". Die levert geen rij
 * op, er komt geen label terug, en het scherm toont het kale ID — gemeld op
 * /cma/form/rooster/530, waar bij Blok "218" stond in plaats van de omschrijving.
 *
 * Deze test kijkt naar de opgebouwde SQL, niet naar een database: het gaat om de vorm.
 */

require_once __DIR__ . '/TestRunner.php';

class FormComboLabelTest extends TestCase
{
    /** De regels die de opzoek-query bouwen. */
    private function bron(): string
    {
        return (string) file_get_contents(__DIR__ . '//../classes/FormDataProvider.php');
    }

    public function testDeVoorwaardeStaatTussenHaakjes(): void
    {
        $bron = $this->bron();
        // Zonder haakjes: `. " = " . is_numeric($lookupId) ?` — dat is precies de fout.
        $this->assertFalse(
            (bool) preg_match('/\.\s*"\s*=\s*"\s*\.\s*is_numeric\(\$lookupId\)\s*\?/', $bron),
            'de ?: bij de opzoek-waarde staat niet meer los achter een concatenatie'
        );
        $this->assertTrue(
            substr_count($bron, '(is_numeric($lookupId) ? SQL::postNumber($lookupId) : SQL::postString($lookupId))') === 2,
            'beide takken (eigen SQL en sourceTable) zetten de keuze tussen haakjes'
        );
    }

    public function testPhpLeestHetZonderHaakjesInderdaadAnders(): void
    {
        // Meetbaar maken waarom dit een fout is, in plaats van het te beweren.
        $id = '218';
        $zonder = 'tbl.ID' . ' = ' . is_numeric($id) ? 'WAARDE' : 'TEKST';
        $met    = 'tbl.ID' . ' = ' . (is_numeric($id) ? 'WAARDE' : 'TEKST');
        $this->assertSame('WAARDE', $zonder);
        $this->assertSame('tbl.ID = WAARDE', $met);
    }
}
