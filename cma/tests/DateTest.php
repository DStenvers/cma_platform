<?php
/**
 * Tests for App\Library\Date
 *
 * Run with: php tests/TestRunner.php DateTest
 */

require_once __DIR__ . '/TestRunner.php';

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
        $this->assertEquals('15' . Date::NBSP . 'Mrt' . Date::NBSP . '2024', Date::mediumDate('2024-03-15', false));
    }

    public function testMediumDateJanuary(): void
    {
        $this->assertEquals('1' . Date::NBSP . 'Jan' . Date::NBSP . '2024', Date::mediumDate('2024-01-01', false));
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
        $this->assertEquals('15' . Date::NBSP . 'maart' . Date::NBSP . '2024', Date::longDate('2024-03-15', false));
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
        $this->assertEquals('Vrijdag 15' . Date::NBSP . 'Maart' . Date::NBSP . '2024', Date::fullDate('2024-03-15', false));
    }

    public function testFullDateEmpty(): void
    {
        $this->assertEquals('', Date::fullDate('', false));
    }

    // ========================================================================
    // De spatie binnen een geschreven datum is HARD (U+00A0)
    //
    // "6 aug 2026" brak op smalle plekken af: dag onderaan de ene regel, jaar bovenaan de
    // volgende. Sites plakten er daarom met de hand str_replace(' ', '&nbsp;') overheen, en
    // dat is precies de verkeerde plek: Twig escapet die entiteit tot &amp;nbsp; en in een
    // mailonderwerp staat hij rauw in de subject. Vandaar het echte teken, in de bron.
    // ========================================================================

    public function testGeschrevenDatumsBrekenNietAf(): void
    {
        foreach ([
            'mediumDate' => Date::mediumDate('2024-03-15', false),
            'longDate'   => Date::longDate('2024-03-15', false),
        ] as $vorm => $uitkomst) {
            $this->assertStringNotContainsString(' ', $uitkomst,
                "$vorm() heeft weer een gewone spatie: daar breekt de datum af");
            $this->assertEquals(2, substr_count($uitkomst, Date::NBSP),
                "$vorm() hoort twee harde spaties te hebben (dag-maand-jaar)");
        }
    }

    public function testHardeSpatieIsHetTekenEnNietDeEntiteit(): void
    {
        // Met &nbsp; in de tekst zou dit de assertie halen die op afbreken controleert, maar
        // op het scherm eindigen als "&amp;nbsp;" zodra Twig hem escapet.
        $this->assertStringNotContainsString('&nbsp;', Date::longDate('2024-03-15', false));
        $this->assertSame("\u{00A0}", Date::NBSP);
    }

    public function testFullDateLaatDeWeekdagWelAfbreken(): void
    {
        // Anders is "Vrijdag 15 Maart 2024" één blok van ruim twintig tekens en duwt het
        // smalle kolommen juist uit elkaar.
        $uit = Date::fullDate('2024-03-15', false);
        $this->assertStringContainsString('Vrijdag ', $uit, 'na de weekdag hoort een gewone spatie te staan');
        $this->assertEquals(2, substr_count($uit, Date::NBSP), 'de datum zelf blijft wel heel');
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

    // ========================================================================
    // age — DateTime objects, int timestamp, same-day, future birthday
    // ========================================================================

    public function testAgeFromDateTimeObject(): void
    {
        $age = Date::age(new \DateTime('2000-01-01'), new \DateTime('2026-03-02'));
        $this->assertEquals(26, $age);
    }

    public function testAgeSameDay(): void
    {
        $this->assertEquals(0, Date::age('2026-03-02', '2026-03-02'));
    }

    public function testAgeExactBirthday(): void
    {
        // Birthday is exactly the reference date's month/day → full years
        $this->assertEquals(26, Date::age('2000-03-02', '2026-03-02'));
    }

    public function testAgeInvalidReference(): void
    {
        $this->assertNull(Date::age('2000-01-01', 'not-a-date'));
    }

    // ========================================================================
    // diff — same date, seconds, minutes, month across year
    // ========================================================================

    public function testDiffSameDate(): void
    {
        $this->assertEquals(0, Date::diff('2024-03-15', '2024-03-15', 'day'));
    }

    public function testDiffSeconds(): void
    {
        $this->assertEquals(3600, Date::diff('2024-03-15 10:00:00', '2024-03-15 11:00:00', 'second'));
    }

    public function testDiffMinutes(): void
    {
        $this->assertEquals(90, Date::diff('2024-03-15 10:00:00', '2024-03-15 11:30:00', 'minute'));
    }

    public function testDiffMonthsAcrossYear(): void
    {
        $this->assertEquals(14, Date::diff('2023-01-15', '2024-03-15', 'month'));
    }

    public function testDiffHoursNegative(): void
    {
        $this->assertEquals(-24, Date::diff('2024-03-16 00:00:00', '2024-03-15 00:00:00', 'hour'));
    }

    // ========================================================================
    // add — hours, minutes, negative month/year, seconds
    // ========================================================================

    public function testAddHours(): void
    {
        $this->assertStringContainsString('2024-03-15 15:00:00', Date::add('2024-03-15 10:00:00', 5, 'hour'));
    }

    public function testAddMinutes(): void
    {
        $this->assertStringContainsString('2024-03-15 10:30:00', Date::add('2024-03-15 10:00:00', 30, 'minute'));
    }

    public function testAddNegativeMonth(): void
    {
        $this->assertStringContainsString('2024-01-15', Date::add('2024-03-15', -2, 'month'));
    }

    public function testAddNegativeYear(): void
    {
        $this->assertStringContainsString('2022-03-15', Date::add('2024-03-15', -2, 'year'));
    }

    public function testAddUnknownUnitDefaultsToDays(): void
    {
        $this->assertStringContainsString('2024-03-20', Date::add('2024-03-15', 5, 'fortnight'));
    }

    // ========================================================================
    // shortWeekday / longWeekday — Sunday boundary (index 0)
    // ========================================================================

    public function testWeekdaySunday(): void
    {
        // 2024-03-17 is a Sunday
        $this->assertEquals('Zo', Date::shortWeekday('2024-03-17'));
        $this->assertEquals('Zondag', Date::longWeekday('2024-03-17'));
    }

    // ========================================================================
    // isValid — numeric-string inputs and zero values
    // ========================================================================

    public function testIsValidNumericStrings(): void
    {
        $this->assertTrue(Date::isValid('15', '3', '2024'));
    }

    public function testIsValidZeroDay(): void
    {
        // '0' is neither '' nor null, so it is validated — day 0 is invalid
        $this->assertFalse(Date::isValid('0', '3', '2024'));
    }

    public function testIsValidMonthOutOfRange(): void
    {
        $this->assertFalse(Date::isValid(15, 13, 2024));
    }

    // ========================================================================
    // fixValue — already dd-mm-yyyy formatted, and time-only variations
    // ========================================================================

    public function testFixValueAlreadyDutchFormat(): void
    {
        // Doesn't match the YYYY-MM-DD anchor → returned unchanged
        $this->assertEquals('15-03-2024', Date::fixValue('15-03-2024'));
    }

    public function testFixValueMidnightDropsTime(): void
    {
        $this->assertEquals('15-03-2024', Date::fixValue('2024-03-15 00:00:00'));
    }

    // ========================================================================
    // isParseable — DateTime object and integer timestamp
    // ========================================================================

    public function testIsParseableDateTime(): void
    {
        $this->assertTrue(Date::isParseable(new \DateTime('2024-03-15')));
    }

    public function testIsParseableIntTimestamp(): void
    {
        $this->assertTrue(Date::isParseable(1710460800));
    }

    // ========================================================================
    // sortableDateTime / time — empty handling
    // ========================================================================

    public function testSortableDateTimeEmpty(): void
    {
        $this->assertEquals('', Date::sortableDateTime(''));
        $this->assertEquals('', Date::sortableDateTime(null));
    }

    // ========================================================================
    // dstOffset / isDST — deterministic in a fixed timezone
    // ========================================================================

    public function testDstDeterministicInEuropeTimezone(): void
    {
        $prev = date_default_timezone_get();
        date_default_timezone_set('Europe/Amsterdam');
        try {
            $this->assertTrue(Date::isDST('2024-07-15'));   // summer → DST
            $this->assertFalse(Date::isDST('2024-01-15'));  // winter → no DST
            $this->assertEquals(1, Date::dstOffset('2024-07-15'));
            $this->assertEquals(0, Date::dstOffset('2024-01-15'));
        } finally {
            date_default_timezone_set($prev);
        }
    }

    // ========================================================================
    // toGMT — deterministic conversion in a fixed timezone
    // ========================================================================

    public function testToGMTUtcTimezone(): void
    {
        $prev = date_default_timezone_get();
        date_default_timezone_set('UTC');
        try {
            $this->assertEquals('20240315T120000Z', Date::toGMT('2024-03-15 12:00:00'));
        } finally {
            date_default_timezone_set($prev);
        }
    }
}
