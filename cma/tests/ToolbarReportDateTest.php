<?php
/**
 * ToolbarReportDateTest — de rapportwerkbalk toont rechts de lange Nederlandse
 * datum met weekdag en tijd, zoals toolbar.inc (FormatDateTime vbLongDate) deed.
 *
 *   php cma/tests/TestRunner.php ToolbarReportDateTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/classes/ToolbarHelper.php';

use Cma\ToolbarHelper;

class ToolbarReportDateTest extends TestCase
{
    public function testLangeDatumMetWeekdagEnTijd(): void
    {
        $ts = mktime(14, 5, 0, 9, 18, 2026); // vrijdag
        $this->assertSame('vrijdag 18 september 2026 (14:05)', ToolbarHelper::longDateTime($ts));
        $this->assertSame('zondag 1 maart 2026 (09:00)', ToolbarHelper::longDateTime(mktime(9, 0, 0, 3, 1, 2026)));
    }

    public function testRapportwerkbalkHeeftPrintknop(): void
    {
        $bron = file_get_contents(dirname(__DIR__) . '/classes/ToolbarHelper.php');
        $start = strpos($bron, 'public static function report(');
        $body = substr($bron, $start, strpos($bron, 'public static function longDateTime') - $start);
        $this->assertStringContainsString('self::printButton(true);', $body);
        $this->assertStringContainsString('self::status(self::longDateTime(time()));', $body);
    }
}
