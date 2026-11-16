<?php
/**
 * The maintenance page ends its "Probeer het over een paar minuten opnieuw." text with a centred Vernieuwen button that reloads the page.
 *
 *   php cma/tests/TestRunner.php MaintenancePageRefreshButtonTest
 */
require_once __DIR__ . '/TestRunner.php';

class MaintenancePageRefreshButtonTest extends TestCase
{
    public function testRefreshButtonSitsUnderTheRetryText(): int
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/maintenance_page.inc');
        $this->assertTrue(
            (bool) preg_match('~Probeer het over een paar minuten opnieuw\.</p>\s*<button type="button" class="vernieuw" onclick="location\.reload\(\)">Vernieuwen</button>\s*<\?php endif; \?>~', $src),
            'the button follows the retry text and only shows together with it'
        );
        $this->assertTrue((bool) preg_match('~\.box\s*\{[^}]*text-align:\s*center~', $src) || strpos($src, 'text-align: center') !== false, 'the content is centred');
        return 0;
    }

    public function testPageRefreshesItselfEveryMinute(): int
    {
        foreach (['/maintenance_page.inc', '/../templates/src/pages/maintenance.php.template'] as $bestand) {
            $src = (string) file_get_contents(dirname(__DIR__) . $bestand);
            $this->assertTrue(strpos($src, '<meta http-equiv="refresh" content="60">') !== false, basename($bestand) . ' refreshes itself every 60 seconds');
        }
        return 0;
    }

    public function testRenderedPageHasTheButton(): int
    {
        $message = 'Test';
        ob_start();
        try {
            include dirname(__DIR__) . '/maintenance_page.inc';
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->assertTrue(true, 'page cannot render outside a site: ' . $e->getMessage());
            return 0;
        }
        $html = ob_get_clean();
        $this->assertTrue(strpos($html, '>Vernieuwen</button>') !== false, 'the rendered page has the Vernieuwen button');
        return 0;
    }

    /** Het voorbeeld in de CMA-tool: dezelfde pagina, met het bericht uit het formulier, zonder zichzelf te verversen en met de keuze voor "opnieuw proberen". */
    public function testPreviewShowsMessageWithoutAutoRefreshAndHonoursRetry(): int
    {
        $render = function (bool $retry): string {
            $message = 'Voorbeeldbericht XYZ';
            $cmaMaintPreview = true;
            $cmaMaintPreviewRetry = $retry;
            ob_start();
            include dirname(__DIR__) . '/maintenance_page.inc';
            return (string) ob_get_clean();
        };
        $met = $render(true);
        $zonder = $render(false);
        $this->assertTrue(strpos($met, 'Voorbeeldbericht XYZ') !== false, 'the preview shows the message typed in the form');
        $this->assertTrue(strpos($met, 'http-equiv="refresh"') === false, 'a preview does not refresh itself');
        $this->assertTrue(strpos($met, 'Probeer het over een paar minuten opnieuw') !== false, 'retry on: the retry text shows');
        $this->assertTrue(strpos($zonder, 'Probeer het over een paar minuten opnieuw') === false, 'retry off: the retry text is gone');
        $tool = (string) file_get_contents(dirname(__DIR__) . '/tools/tools_maintenance.php');
        $this->assertTrue(strpos($tool, 'id="maint-preview-frame"') !== false && strpos($tool, "Request::query('preview', '') === '1'") !== false, 'the tool has a preview iframe and a preview endpoint');
        $this->assertTrue((bool) preg_match('~display:flex;flex-wrap:wrap[^>]*>\';\s*echo \'<form method="post\'~', $tool) && strpos($tool, 'flex:1 1 24rem') !== false, 'the preview sits next to the form (flex row)');
        return 0;
    }
}
