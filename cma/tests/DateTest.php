<?php
/**
 * Tests for App\Library\Date
 *
 * Run with: php tests/TestRunner.php DateTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Date class may not be in the helpers package - load from app/library if available
if (!class_exists('App\Library\Date')) {
    $datePath = dirname(__DIR__, 2) . '/app/library/Date.php';
    if (file_exists($datePath)) {
        require_once $datePath;
    }
}

use App\Library\Date;

class DateTest extends TestCase
{
    // ========================================================================
    // age
    // ========================================================================

    public function testAgeBasic(): void
    {
        $age = Date::age('2000-01-01', '2026-03-02');
        $this->assertEquals(26, $age);
    }

    public function testAgeBirthdayNotYet(): void
    {
        // Birthday December, reference March => not had birthday yet this year context
        $age = Date::age('2000-12-15', '2026-03-02');
        $this->assertEquals(25, $age);
    }

    public function testAgeNull(): void
    {
        $this->assertNull(Date::age(null));
        $this->assertNull(Date::age(''));
    }

    public function testAgeInvalidDate(): void
    {
        $this->assertNull(Date::age('not-a-date'));
    }

    // ========================================================================
    // shortDate
    // ========================================================================

    public function testShortDate(): void
    {
        $this->assertEquals('15-03-2024', Date::shortDate('2024-03-15'));
    }

    public function testShortDateWithTime(): void
    {
        $this->assertEquals('15-03-2024', Date::shortDate('2024-03-15 14:30:00'));
    }

    public function testShortDateEmpty(): void
    {
        $this->assertEquals('', Date::shortDate(''));
        $this->assertEquals('', Date::shortDate(null));
    }

    // ========================================================================
    // sortable / sortableDateTime
    // ========================================================================

    public function testSortable(): void
    {
        $this->assertEquals('2024-03-15', Date::sortable('2024-03-15'));
    }

    public function testSortableEmpty(): void
    {
        $this->assertEquals('', Date::sortable(''));
    }

    public function testSortableDateTime(): void
    {
        $result = Date::sortableDateTime('2024-03-15 14:30:00');
        $this->assertEquals('2024-03-15_14_30', $result);
    }

    // ========================================================================
    // time
    // ========================================================================

    public function testTime(): void
    {
        $this->assertEquals('14:30', Date::time('2024-03-15 14:30:00'));
    }

    public function testTimeEmpty(): void
    {
        $this->assertEquals('', Date::time(''));
    }

    // ========================================================================
    // Dutch month/weekday names
    // ========================================================================

    public function testShortMonthNames(): void
    {
        $this->assertEquals('Jan', Date::shortMonth(1));
        $this->assertEquals('Mrt', Date::shortMonth(3));
        $this->assertEquals('Mei', Date::shortMonth(5));
        $this->assertEquals('Okt', Date::shortMonth(10));
        $this->assertEquals('Dec', Date::shortMonth(12));
    }

    public function testShortMonthInvalid(): void
    {
        $this->assertEquals('', Date::shortMonth(0));
        $this->assertEquals('', Date::shortMonth(13));
    }

    public function testLongMonthNames(): void
    {
        $this->assertEquals('Januari', Date::longMonth(1));
        $this->assertEquals('Maart', Date::longMonth(3));
        $this->assertEquals('Augustus', Date::longMonth(8));
        $this->assertEquals('December', Date::longMonth(12));
    }

    public function testShortWeekday(): void
    {
        // 2024-03-15 is a Friday
        $this->assertEquals('Vr', Date::shortWeekday('2024-03-15'));
    }

    public function testLongWeekday(): void
    {
        // 2024-03-15 is a Friday
        $this->assertEquals('Vrijdag', Date::longWeekday('2024-03-15'));
    }

    public function testWeekdayEmpty(): void
    {
        $this->assertEquals('', Date::shortWeekday(''));
        $this->assertEquals('', Date::longWeekday(''));
    }

    // ========================================================================
    // relative
    // ========================================================================

    public function testRelativeToday(): void
    {
        $today = date('Y-m-d');
        $this->assertEquals('Vandaag', Date::relative($today));
    }

    public function testRelativeTomorrow(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $this->assertEquals('Morgen', Date::relative($tomorrow));
    }

    public function testRelativeYesterday(): void
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $this->assertEquals('Gisteren', Date::relative($yesterday));
    }

    public function testRelativeDayBeforeYesterday(): void
    {
        $dayBefore = date('Y-m-d', strtotime('-2 days'));
        $this->assertEquals('Eergisteren', Date::relative($dayBefore));
    }

    public function testRelativeDayAfterTomorrow(): void
    {
        $dayAfter = date('Y-m-d', strtotime('+2 days'));
        $this->assertEquals('Overmorgen', Date::relative($dayAfter));
    }

    public function testRelativeOutOfRange(): void
    {
        $farDate = date('Y-m-d', strtotime('+10 days'));
        $this->assertNull(Date::relative($farDate));
    }

    public function testRelativeDisabled(): void
    {
        $today = date('Y-m-d');
        $this->assertNull(Date::relative($today, false));
    }

    // ========================================================================
    // mediumDate (without relative, to avoid Application dependency)
    // ========================================================================

    public function testMediumDateNoRelative(): void
    {
        $this->assertEquals('15 Mrt 2024', Date::mediumDate('2024-03-15', false));
    }

    public function testMediumDateJanuary(): void
    {
        $this->assertEquals('1 Jan 2024', Date::mediumDate('2024-01-01', false));
    }

    public function testMediumDateEmpty(): void
    {
        $this->assertEquals('', Date::mediumDate('', false));
    }

    // ========================================================================
    // longDate (without relative)
    // ========================================================================

    public function testLongDateNoRelative(): void
    {
        $this->assertEquals('15 maart 2024', Date::longDate('2024-03-15', false));
    }

    public function testLongDateEmpty(): void
    {
        $this->assertEquals('', Date::longDate('', false));
    }

    // ========================================================================
    // fullDate (without relative)
    // ========================================================================

    public function testFullDateNoRelative(): void
    {
        $this->assertEquals('Vrijdag 15 Maart 2024', Date::fullDate('2024-03-15', false));
    }

    public function testFullDateEmpty(): void
    {
        $this->assertEquals('', Date::fullDate('', false));
    }

    // ========================================================================
    // day / month / year
    // ========================================================================

    public function testDay(): void
    {
        $this->assertEquals('15', Date::day('2024-03-15'));
    }

    public function testMonth(): void
    {
        $this->assertEquals('3', Date::month('2024-03-15'));
    }

    public function testYear(): void
    {
        $this->assertEquals('2024', Date::year('2024-03-15'));
    }

    public function testDayMonthYearEmpty(): void
    {
        $this->assertEquals('', Date::day(''));
        $this->assertEquals('', Date::month(''));
        $this->assertEquals('', Date::year(''));
    }

    // ========================================================================
    // isValid
    // ========================================================================

    public function testIsValidAllEmpty(): void
    {
        $this->assertTrue(Date::isValid('', '', ''));
        $this->assertTrue(Date::isValid(null, null, null));
    }

    public function testIsValidGoodDate(): void
    {
        $this->assertTrue(Date::isValid(15, 3, 2024));
    }

    public function testIsValidBadDate(): void
    {
        $this->assertFalse(Date::isValid(31, 2, 2024)); // Feb 31
    }

    public function testIsValidPartialEmpty(): void
    {
        $this->assertFalse(Date::isValid(15, '', 2024)); // month missing
    }

    public function testIsValidLeapYear(): void
    {
        $this->assertTrue(Date::isValid(29, 2, 2024)); // 2024 is leap year
        $this->assertFalse(Date::isValid(29, 2, 2023)); // 2023 is not
    }

    // ========================================================================
    // isParseable
    // ========================================================================

    public function testIsParseableValid(): void
    {
        $this->assertTrue(Date::isParseable('2024-03-15'));
    }

    public function testIsParseableInvalid(): void
    {
        $this->assertFalse(Date::isParseable('not-a-date'));
    }

    public function testIsParseableEmpty(): void
    {
        $this->assertFalse(Date::isParseable(''));
        $this->assertFalse(Date::isParseable(null));
    }

    // ========================================================================
    // toGMT
    // ========================================================================

    public function testToGMTFormat(): void
    {
        $result = Date::toGMT('2024-03-15 12:00:00');
        $this->assertMatchesRegularExpression('/^\d{8}T\d{6}Z$/', $result);
    }

    public function testToGMTEmpty(): void
    {
        $this->assertEquals('', Date::toGMT(''));
    }

    // ========================================================================
    // isDST / dstOffset
    // ========================================================================

    public function testIsDSTSummer(): void
    {
        // July is DST in Europe
        $result = Date::isDST('2024-07-15');
        // Result depends on server timezone, just check it returns bool
        $this->assertTrue(is_bool($result));
    }

    public function testDstOffset(): void
    {
        $result = Date::dstOffset('2024-07-15');
        $this->assertTrue($result === 0 || $result === 1);
    }

    // ========================================================================
    // fixMonthNames
    // ========================================================================

    public function testFixMonthNamesEnglishToDutch(): void
    {
        $this->assertEquals('15 mei 2024', Date::fixMonthNames('15 may 2024'));
        $this->assertEquals('15 Mei 2024', Date::fixMonthNames('15 May 2024'));
        $this->assertEquals('15 okt 2024', Date::fixMonthNames('15 oct 2024'));
        $this->assertEquals('15 Okt 2024', Date::fixMonthNames('15 Oct 2024'));
    }

    public function testFixMonthNamesNoChange(): void
    {
        $this->assertEquals('15 jan 2024', Date::fixMonthNames('15 jan 2024'));
    }

    // ========================================================================
    // add
    // ========================================================================

    public function testAddDays(): void
    {
        $result = Date::add('2024-03-15', 5, 'day');
        $this->assertStringContainsString('2024-03-20', $result);
    }

    public function testAddMonths(): void
    {
        $result = Date::add('2024-03-15', 2, 'month');
        $this->assertStringContainsString('2024-05-15', $result);
    }

    public function testAddYears(): void
    {
        $result = Date::add('2024-03-15', 1, 'year');
        $this->assertStringContainsString('2025-03-15', $result);
    }

    public function testAddNegativeDays(): void
    {
        $result = Date::add('2024-03-15', -5, 'day');
        $this->assertStringContainsString('2024-03-10', $result);
    }

    public function testAddEmpty(): void
    {
        $this->assertEquals('', Date::add('', 5));
    }

    // ========================================================================
    // fixValue
    // ========================================================================

    public function testFixValueDate(): void
    {
        $this->assertEquals('15-03-2024', Date::fixValue('2024-03-15'));
    }

    public function testFixValueDatetime(): void
    {
        $this->assertEquals('15-03-2024 14:30', Date::fixValue('2024-03-15 14:30:00'));
    }

    public function testFixValueTimeOnly(): void
    {
        // MS Access time-only (year 1899)
        $this->assertEquals('14:30', Date::fixValue('1899-12-30 14:30:00'));
    }

    public function testFixValueNonDate(): void
    {
        $this->assertEquals('hello', Date::fixValue('hello'));
    }

    public function testFixValueEmpty(): void
    {
        $this->assertEquals('', Date::fixValue(''));
        $this->assertEquals('', Date::fixValue(null));
    }

    // ========================================================================
    // diff
    // ========================================================================

    public function testDiffDays(): void
    {
        $this->assertEquals(10, Date::diff('2024-03-05', '2024-03-15', 'day'));
    }

    public function testDiffNegative(): void
    {
        $this->assertEquals(-10, Date::diff('2024-03-15', '2024-03-05', 'day'));
    }

    public function testDiffMonths(): void
    {
        $this->assertEquals(3, Date::diff('2024-01-15', '2024-04-15', 'month'));
    }

    public function testDiffYears(): void
    {
        $this->assertEquals(2, Date::diff('2022-03-15', '2024-03-15', 'year'));
    }

    public function testDiffHours(): void
    {
        $this->assertEquals(24, Date::diff('2024-03-15 00:00:00', '2024-03-16 00:00:00', 'hour'));
    }

    public function testDiffInvalid(): void
    {
        $this->assertNull(Date::diff('', '2024-03-15'));
        $this->assertNull(Date::diff('2024-03-15', ''));
    }
}
