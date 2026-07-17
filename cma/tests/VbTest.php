<?php
/**
 * Tests for App\Library\Vb — VBScript runtime-semantics helpers (cDate).
 *
 * Vb::cDate deliberately diverges from strtotime(): Dutch day-first dates
 * (dd-mm-yyyy, dd/mm/yyyy) parse the way VBScript CDate did under nl-NL, and
 * Empty (null/'') returns the VBScript zero-date instead of throwing. These
 * tests pin exactly those divergences so a "cleanup" to strtotime() fails.
 */

require_once __DIR__ . '/../../src/helpers/Vb.php';

use App\Library\Vb;

class VbTest extends TestCase
{
    public function testEmptyReturnsVbScriptZeroDate(): void
    {
        $this->assertEquals('1899-12-30 00:00:00', Vb::cDate(null));
        $this->assertEquals('1899-12-30 00:00:00', Vb::cDate(''));
        $this->assertEquals('1899-12-30 00:00:00', Vb::cDate(false));
        $this->assertEquals('1899-12-30 00:00:00', Vb::cDate('   '));
    }

    public function testDutchDayFirstDashDate(): void
    {
        $this->assertEquals('2015-12-17 00:00:00', Vb::cDate('17-12-2015'));
    }

    public function testDutchDayFirstSlashDateWithDayAboveTwelve(): void
    {
        // strtotime() reads slash dates month-first and REJECTS a day > 12;
        // CDate under nl-NL parsed these day-first.
        $this->assertEquals('2015-12-17 00:00:00', Vb::cDate('17/12/2015'));
    }

    public function testDayFirstDateWithTime(): void
    {
        $this->assertEquals('2015-12-17 14:30:00', Vb::cDate('17-12-2015 14:30'));
        $this->assertEquals('2015-12-17 14:30:45', Vb::cDate('17-12-2015 14:30:45'));
    }

    public function testDateTimeObjectPassesThrough(): void
    {
        $dt = new \DateTime('2020-06-01 12:00:00');
        $this->assertEquals('2020-06-01 12:00:00', Vb::cDate($dt));
    }

    public function testTimestampIsFormatted(): void
    {
        $this->assertEquals(date('Y-m-d H:i:s', 1600000000), Vb::cDate(1600000000));
    }

    public function testIsoDateFallsThroughToStrtotime(): void
    {
        $this->assertEquals('2021-03-05 00:00:00', Vb::cDate('2021-03-05'));
    }

    public function testGarbageThrowsTypeMismatch(): void
    {
        try {
            Vb::cDate('niet-een-datum');
            $this->fail('Expected Type mismatch exception');
        } catch (\Exception $e) {
            $this->assertEquals('Type mismatch', $e->getMessage());
        }
    }
}
