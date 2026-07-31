<?php
/**
 * Tests for App\Library\Date::normalize() — the one gate a date passes before it is used.
 *
 * Run with: php cma/tests/TestRunner.php DateNormalizeTest
 *
 * What this pins down: both written forms are read correctly (a day is never four
 * digits), impossible dates are refused instead of silently rolling over, and a refusal
 * is null — never a date the caller did not mean. The failure this replaces was silent:
 * an unread date reached SQL::postDate() as day 2026 / year 01, came back as the literal
 * NULL and made a WHERE match nothing, with no error anywhere.
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Date;

class DateNormalizeTest extends TestCase
{
    public function testLeestBeideGeschrevenVormen(): void
    {
        // Dezelfde dag, twee notaties: het eerste deel bepaalt welke.
        $this->assertSame('2026-06-07', Date::normalize('07-06-2026'));
        $this->assertSame('2026-06-07', Date::normalize('2026-06-07'));
        $this->assertSame('2026-06-07', Date::normalize('07/06/2026'));
        $this->assertSame('2026-06-07', Date::normalize('7-6-2026'));
    }

    public function testTijdsdeelWordtGenegeerd(): void
    {
        $this->assertSame('2026-06-07', Date::normalize('2026-06-07 14:30:45'));
        $this->assertSame('2026-06-07', Date::normalize('07-06-2026 14:30:45'));
        $this->assertSame('2026-06-07', Date::normalize('2026-06-07T14:30:45Z'));
    }

    public function testWeigertOnmogelijkeDagEnMaand(): void
    {
        $this->assertNull(Date::normalize('32-01-2026'), 'dag 32 bestaat niet');
        $this->assertNull(Date::normalize('01-13-2026'), 'maand 13 bestaat niet');
        $this->assertNull(Date::normalize('31-02-2026'), '31 februari bestaat niet');
        $this->assertNull(Date::normalize('29-02-2026'), '2026 is geen schrikkeljaar');
        $this->assertSame('2024-02-29', Date::normalize('29-02-2024'), '2024 wel');
        $this->assertNull(Date::normalize('00-01-2026'), 'dag 0 bestaat niet');
        $this->assertNull(Date::normalize('01-00-2026'), 'maand 0 bestaat niet');
    }

    public function testWeigertJaarBuitenHetVenster(): void
    {
        $this->assertNull(Date::normalize('01-01-1899'));
        $this->assertNull(Date::normalize('01-01-2100'));
        $this->assertSame('1900-01-01', Date::normalize('01-01-1900'));
        $this->assertSame('2099-12-31', Date::normalize('31-12-2099'));
    }

    public function testVensterIsInstelbaar(): void
    {
        // Een datum die niet historisch kan zijn: strakker venster, zelfde functie.
        $this->assertNull(Date::normalize('01-01-1975', 2000, 2099));
        $this->assertSame('2026-01-01', Date::normalize('01-01-2026', 2000, 2099));
    }

    public function testGeboortedatumBlijftGeldigBinnenHetStandaardvenster(): void
    {
        // tblDeelnemers.GeboorteDatum loopt door dezelfde helpers als een rapportfilter.
        // Een ondergrens in deze eeuw zou elke geboortedatum op NULL zetten.
        $this->assertSame('1975-03-14', Date::normalize('14-03-1975'));
    }

    public function testWeigertOnzin(): void
    {
        $this->assertNull(Date::normalize(''));
        $this->assertNull(Date::normalize(null));
        $this->assertNull(Date::normalize('geen datum'));
        $this->assertNull(Date::normalize('07-06'), 'onvolledig');
        $this->assertNull(Date::normalize('07-06-26'), 'tweecijferig jaar is te dubbelzinnig om te raden');
        $this->assertNull(Date::normalize('07-6a-2026'));
        $this->assertNull(Date::normalize(true));
    }

    public function testAccepteertTijdstempelEnDateTime(): void
    {
        $this->assertSame('2026-06-07', Date::normalize(mktime(12, 0, 0, 6, 7, 2026)));
        $this->assertSame('2026-06-07', Date::normalize(new \DateTime('2026-06-07 09:15:00')));
    }
}
