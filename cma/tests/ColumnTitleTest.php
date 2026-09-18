<?php
/**
 * JsonFormService::columnTitle() — a list header is the caption up to its
 * first <br>, without markup. A caption written for the label column may
 * break a long name over two lines; a table header cannot.
 *
 *   php cma/tests/TestRunner.php ColumnTitleTest
 */
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/Services/BaseFormService.php';
require_once __DIR__ . '/../classes/Services/JsonFormService.php';

use Cma\Services\JsonFormService;

class ColumnTitleTest extends TestCase
{
    public function testStopsAtTheFirstLineBreak(): void
    {
        $this->assertSame('Aandachtspunten en afspraken', JsonFormService::columnTitle('Aandachtspunten en afspraken<br>hoofd-/jaargroepopleider', 'x'));
        $this->assertSame('Regel een', JsonFormService::columnTitle('Regel een<BR/>regel twee<br />drie', 'x'));
    }

    public function testStripsMarkupAndKeepsPlainCaptions(): void
    {
        $this->assertSame('Naam', JsonFormService::columnTitle('<span class="cma-page__strong">Naam</span>', 'x'));
        $this->assertSame('Startdatum', JsonFormService::columnTitle('Startdatum', 'x'));
    }

    public function testFallsBackWhenTheCaptionIsEmpty(): void
    {
        $this->assertSame('Fkopleiding', JsonFormService::columnTitle(null, 'Fkopleiding'));
        $this->assertSame('Fkopleiding', JsonFormService::columnTitle('  ', 'Fkopleiding'));
        $this->assertSame('tweede', JsonFormService::columnTitle('<br>tweede', 'x'), 'a caption that starts with a break keeps its text');
    }
}
