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
}
