<?php
/**
 * ErrorCardTest.php — the error card rendered by App\Library\Error.
 *
 * This card is the last thing a user sees when a page breaks, so it has to
 * stand on its own: no stylesheet, no jQuery, nothing from the page it lands
 * in. The markup mirrors the ASP original (library/lib_error.inc ::
 * internal_errordialog) — same .lib-error-card hook, same header, same copy
 * button — because operators recognise it and support scripts key off it.
 *
 *   php tests/TestRunner.php ErrorCardTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Error;

class ErrorCardTest extends TestCase
{
    /** Render a card and hand back its HTML. */
    private function render(string $message): string
    {
        // The card suppresses a repeat of the SAME message process-wide, so
        // every test needs its own text.
        ob_start();
        Error::show($message);
        return (string) ob_get_clean();
    }

    public function testCardCarriesTheLibErrorCardHook(): void
    {
        $html = $this->render('hook ' . __FUNCTION__);
        $this->assertTrue(
            str_contains($html, 'class="lib-error-card"'),
            'the copy handler walks up to .lib-error-card — without the class it copies nothing'
        );
    }

    public function testCopyButtonIsPresentAndSelfContained(): void
    {
        $html = $this->render('copy ' . __FUNCTION__);
        $this->assertTrue(str_contains($html, 'libErrorCopy(this)'), 'copy button must be wired');
        $this->assertTrue(str_contains($html, '<svg'), 'button carries an inline icon, not an image file');
        $this->assertTrue(str_contains($html, 'createTreeWalker'), 'copies what is on the card, not a baked string');
        // A dev host on plain http has no navigator.clipboard: the fallback is
        // the difference between a working button and a dead one.
        $this->assertTrue(str_contains($html, 'execCommand'), 'needs the non-secure-context fallback');
        $this->assertFalse(str_contains($html, '.css'), 'must not depend on a stylesheet');
    }

    public function testCopyTakesTheMessageOnlyNotTheTitleOrTheButton(): void
    {
        // Gemeld: "er is een kopieer-knop, maar die kopieert alles, de titel en
        // knop mogen daar weg". De wandeling liep over de hele kaart, dus
        // "Er is een fout opgetreden" en "Terug" kwamen mee op het klembord.
        $html = $this->render('scope ' . __FUNCTION__);
        $this->assertTrue(
            str_contains($html, 'class="lib-error-copy"'),
            'de meldingscel moet de haak dragen waar de kopieerknop op loopt'
        );
        $this->assertTrue(
            str_contains($html, "querySelector('.lib-error-copy')"),
            'de kopieerknop moet naar die haak zoeken in plaats van de hele kaart te nemen'
        );
        // De haak zit op de cel met de melding — niet op de kop en niet op de
        // rij met de Terug-knop.
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<div>' . $html . '</div>');
        libxml_clear_errors();
        $xp = new DOMXPath($doc);
        $cellen = $xp->query('//*[contains(concat(" ", normalize-space(@class), " "), " lib-error-copy ")]');
        $this->assertEquals(1, $cellen->length, 'precies één kopieergebied');
        $tekst = trim($cellen->item(0)->textContent);
        $this->assertTrue(str_contains($tekst, 'scope ' . __FUNCTION__), 'de melding zit erin');
        $this->assertFalse(str_contains($tekst, 'Er is een fout opgetreden'), 'de titel hoort er niet in');
        $this->assertFalse(str_contains($tekst, 'Terug'), 'de knop hoort er niet in');
    }

    public function testCardCarriesItsOwnFont(): void
    {
        // Error::show() schrijft geen <head> en geen stylesheet: zonder eigen
        // font-family op de KAART kreeg je een Verdana-kop met een Times-melding.
        $html = $this->render('font ' . __FUNCTION__);
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<div>' . $html . '</div>');
        libxml_clear_errors();
        $xp = new DOMXPath($doc);
        $kaart = $xp->query('//*[contains(concat(" ", normalize-space(@class), " "), " lib-error-card ")]')->item(0);
        $this->assertTrue($kaart !== null, 'de kaart staat er');
        $stijl = $kaart->getAttribute('style');
        $this->assertTrue(
            str_contains($stijl, 'font-family:Verdana'),
            'de kaart zelf moet het lettertype zetten, anders erft de melding de schreefletter van de browser'
        );
        $this->assertTrue(str_contains($stijl, 'font-size:13px'), 'en de tekstgrootte ook');
    }

    public function testMessageIsShownAndCleanedUp(): void
    {
        $html = $this->render('De Microsoft Jet-database-engine kan de invoertabel of -query tblX niet vinden.');
        $this->assertTrue(str_contains($html, 'Tabel'), 'Jet wording is rewritten to something readable');
        $this->assertTrue(str_contains($html, 'niet gevonden.'), 'and the tail is rewritten too');
        $this->assertFalse(str_contains($html, 'Jet-database-engine'), 'raw engine name must not survive');
    }

    public function testBackButtonIsRendered(): void
    {
        $html = $this->render('back ' . __FUNCTION__);
        $this->assertTrue(str_contains($html, 'FormBack'), 'the Terug control keeps its class');
        $this->assertTrue(str_contains($html, 'history.go(-1)'));
    }

    public function testCardIsValidHtml(): void
    {
        $html = $this->render('parse ' . __FUNCTION__);
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        $doc = new DOMDocument();
        $doc->loadHTML('<div>' . $html . '</div>');
        $fatal = array_filter(libxml_get_errors(), static fn($e) => $e->level > 2);
        libxml_clear_errors();
        $this->assertEquals(0, count($fatal), 'the card must parse — it is injected into broken pages');
    }

    public function testDetailsAreCollapsedButCopied(): void
    {
        // Gemeld bij "toets plaatsen": de databasemelding stond in het zicht en de SQL
        // ontbrak. Details horen achter "Toon details" (dicht) én mee met de kopieerknop.
        ob_start();
        Error::show('details ' . __FUNCTION__, "Data type mismatch\n\nSQL: INSERT INTO tblToetsen (a) VALUES ('x<y')");
        $html = (string) ob_get_clean();
        $this->assertTrue(str_contains($html, '>Toon details</a>'), 'er staat een schakelaar');
        $this->assertTrue(str_contains($html, "'Verberg details'"), 'die de andere kant op ook werkt');
        $this->assertFalse(str_contains($html, '.js'), 'zonder script van buiten — de kaart staat op zichzelf');

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<div>' . $html . '</div>');
        libxml_clear_errors();
        $xp = new DOMXPath($doc);
        $blok = $xp->query('//*[contains(concat(" ", normalize-space(@class), " "), " lib-error-details ")]')->item(0);
        $this->assertTrue($blok !== null, 'het detailblok staat er');
        $this->assertTrue(str_contains($blok->getAttribute('style'), 'display:none'), 'en staat dicht');
        $this->assertTrue(str_contains($blok->textContent, "VALUES ('x<y')"), 'de SQL staat erin, letterlijk');
        $this->assertTrue(str_contains($html, '&#039;x&lt;y&#039;'), 'maar wel ontsnapt: SQL is tekst, geen opmaak');

        $cel = $xp->query('//*[contains(concat(" ", normalize-space(@class), " "), " lib-error-copy ")]')->item(0);
        $this->assertTrue(str_contains($cel->textContent, 'Data type mismatch'), 'de kopieerknop neemt de details mee');
        $this->assertFalse(str_contains($cel->textContent, 'Toon details'), 'maar niet de schakelaar zelf');
    }

    public function testWithoutDetailsThereIsNoToggle(): void
    {
        $html = $this->render('kaal ' . __FUNCTION__);
        $this->assertFalse(str_contains($html, 'Toon details'), 'geen details, geen schakelaar');
        $this->assertFalse(str_contains($html, 'lib-error-details'), 'en geen leeg blok');
    }

    public function testRepeatOfTheSameMessageIsSuppressed(): void
    {
        $msg = 'dubbel ' . __FUNCTION__;
        $first  = $this->render($msg);
        $second = $this->render($msg);
        $this->assertTrue($first !== '', 'first render produces the card');
        $this->assertEquals('', $second, 'the same error must not stack up on one page');
    }
}
