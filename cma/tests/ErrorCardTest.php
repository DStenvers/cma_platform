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

    public function testRepeatOfTheSameMessageIsSuppressed(): void
    {
        $msg = 'dubbel ' . __FUNCTION__;
        $first  = $this->render($msg);
        $second = $this->render($msg);
        $this->assertTrue($first !== '', 'first render produces the card');
        $this->assertEquals('', $second, 'the same error must not stack up on one page');
    }
}
