<?php
/**
 * Database::encodeForOdbc(): UTF-8 SQL text is converted to Windows-1252 for the ANSI
 * Access ODBC driver, the mirror of the Windows-1252 -> UTF-8 conversion on read.
 */
require_once __DIR__ . '/TestRunner.php';

use App\Library\Database;

class DatabaseEncodeForOdbcTest extends TestCase
{
    public function testUtf8IsConvertedToWindows1252(): void
    {
        $out = Database::encodeForOdbc("UPDATE t SET x='één €'");
        $this->assertSame("UPDATE t SET x='\xE9\xE9n \x80'", $out);
    }

    public function testAsciiOnlyIsUntouched(): void
    {
        $sql = "SELECT * FROM t WHERE id=1";
        $this->assertSame($sql, Database::encodeForOdbc($sql));
    }

    public function testAlreadyWindows1252IsNotConvertedTwice(): void
    {
        $sql = "x='\xE9\xE9n'";   // not valid UTF-8, so already single-byte
        $this->assertSame($sql, Database::encodeForOdbc($sql));
    }

    public function testCharactersOutsideWindows1252BecomeQuestionMark(): void
    {
        $this->assertSame("x='?'", Database::encodeForOdbc("x='ĳ'"));
    }

    public function testRoundTripMatchesReadConversion(): void
    {
        $utf8 = "Proef één ü – • €";
        $stored = Database::encodeForOdbc($utf8);
        $this->assertSame($utf8, mb_convert_encoding($stored, 'UTF-8', 'Windows-1252'));
    }
}
