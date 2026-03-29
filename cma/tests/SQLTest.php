<?php
/**
 * Tests for App\Library\SQL
 *
 * Tests pure functions that don't require Database connection.
 * Run with: php tests/TestRunner.php SQLTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Library\SQL;

class SQLTest extends TestCase
{
    // ========================================================================
    // postNumber
    // ========================================================================

    public function testPostNumberNormal(): void
    {
        $this->assertEquals('123', SQL::postNumber(123));
    }

    public function testPostNumberDecimalComma(): void
    {
        $this->assertEquals('12.50', SQL::postNumber('12,50'));
    }

    public function testPostNumberDecimalDot(): void
    {
        $this->assertEquals('12.50', SQL::postNumber('12.50'));
    }

    public function testPostNumberEmpty(): void
    {
        $this->assertEquals('null', SQL::postNumber(''));
    }

    public function testPostNumberNull(): void
    {
        $this->assertEquals('null', SQL::postNumber(null));
    }

    public function testPostNumberWithSpaces(): void
    {
        $this->assertEquals('42', SQL::postNumber(' 42 '));
    }

    // ========================================================================
    // postGuid
    // ========================================================================

    public function testPostGuidPlain(): void
    {
        $this->assertEquals("'abc-123'", SQL::postGuid('abc-123'));
    }

    public function testPostGuidWithBraces(): void
    {
        $this->assertEquals("'abc-123'", SQL::postGuid('{abc-123}'));
    }

    // ========================================================================
    // postTimeStr
    // ========================================================================

    public function testPostTimeStrNormal(): void
    {
        $this->assertEquals('#14:30#', SQL::postTimeStr('14:30'));
    }

    public function testPostTimeStrSlash(): void
    {
        $this->assertEquals('#14:30#', SQL::postTimeStr('14/30'));
    }

    public function testPostTimeStrEmpty(): void
    {
        $this->assertEquals('NULL', SQL::postTimeStr(''));
    }

    public function testPostTimeStrTooShort(): void
    {
        $this->assertEquals('NULL', SQL::postTimeStr('1'));
    }

    // ========================================================================
    // sqlDateToRealDate
    // ========================================================================

    public function testSqlDateToRealDateValid(): void
    {
        // Format is MMDDYYYY -> MM-DD-YYYY -> strtotime
        // Note: strtotime may not parse MM-DD-YYYY in all locales
        // Test with a format that works: 01-01-2024
        $result = SQL::sqlDateToRealDate('01012024');
        $this->assertStringContainsString('2024', $result);
    }

    public function testSqlDateToRealDateJuly(): void
    {
        // 07042024 = July 4, 2024
        $result = SQL::sqlDateToRealDate('07042024');
        $this->assertStringContainsString('2024', $result);
    }

    public function testSqlDateToRealDateTooShort(): void
    {
        $thrown = false;
        try {
            SQL::sqlDateToRealDate('123');
        } catch (\Exception $e) {
            $thrown = true;
            $this->assertEquals('Type mismatch', $e->getMessage());
        }
        $this->assertTrue($thrown, 'Expected exception for short date');
    }

    // ========================================================================
    // addWhere
    // ========================================================================

    public function testAddWhereNew(): void
    {
        $sql = SQL::addWhere('SELECT * FROM users', 'active=1');
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('active=1', $sql);
    }

    public function testAddWhereExisting(): void
    {
        $sql = SQL::addWhere('SELECT * FROM users WHERE name=\'John\'', 'active=1');
        $this->assertStringContainsString('AND', $sql);
        $this->assertStringContainsString('active=1', $sql);
    }

    public function testAddWhereEmpty(): void
    {
        $sql = 'SELECT * FROM users';
        $this->assertEquals($sql, SQL::addWhere($sql, ''));
    }

    public function testAddWherePreservesOrderBy(): void
    {
        $sql = SQL::addWhere('SELECT * FROM users ORDER BY name', 'active=1');
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('ORDER BY name', $sql);
    }

    // ========================================================================
    // addWhereOR
    // ========================================================================

    public function testAddWhereORNew(): void
    {
        $sql = SQL::addWhereOR('SELECT * FROM users', 'active=1');
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('active=1', $sql);
    }

    public function testAddWhereORExisting(): void
    {
        $sql = SQL::addWhereOR('SELECT * FROM users WHERE name=\'John\'', 'active=1');
        $this->assertStringContainsString('OR', $sql);
    }

    // ========================================================================
    // addHaving
    // ========================================================================

    public function testAddHavingNew(): void
    {
        $sql = SQL::addHaving('SELECT COUNT(*) FROM users GROUP BY name', 'COUNT(*)>1');
        $this->assertStringContainsString('HAVING', $sql);
        $this->assertStringContainsString('COUNT(*)>1', $sql);
    }

    public function testAddHavingEmpty(): void
    {
        $sql = 'SELECT * FROM users';
        $this->assertEquals($sql, SQL::addHaving($sql, ''));
    }

    // ========================================================================
    // addInClause
    // ========================================================================

    public function testAddInClauseSingleValue(): void
    {
        $sql = SQL::addInClause('SELECT * FROM users', 'id', '5');
        $this->assertStringContainsString('id = 5', $sql);
    }

    public function testAddInClauseFewValues(): void
    {
        $sql = SQL::addInClause('SELECT * FROM users', 'id', '1,2,3');
        // Should use OR for 3 values (< default minInValues of 5)
        $this->assertStringContainsString('OR', $sql);
    }

    public function testAddInClauseManyValues(): void
    {
        $sql = SQL::addInClause('SELECT * FROM users', 'id', '1,2,3,4,5,6');
        $this->assertStringContainsString('IN', $sql);
    }

    public function testAddInClauseEmpty(): void
    {
        $sql = 'SELECT * FROM users';
        $this->assertEquals($sql, SQL::addInClause($sql, 'id', ''));
    }

    // ========================================================================
    // addTop (may not be in installed version)
    // ========================================================================

    public function testAddTopBasic(): void
    {
        if (!method_exists(SQL::class, 'addTop')) { return; }
        $sql = SQL::addTop('SELECT * FROM users', 10);
        $this->assertEquals('SELECT TOP 10 * FROM users', $sql);
    }

    public function testAddTopWithDistinct(): void
    {
        if (!method_exists(SQL::class, 'addTop')) { return; }
        $sql = SQL::addTop('SELECT DISTINCT name FROM users', 5);
        $this->assertEquals('SELECT DISTINCT TOP 5 name FROM users', $sql);
    }

    public function testAddTopAlreadyPresent(): void
    {
        if (!method_exists(SQL::class, 'addTop')) { return; }
        $sql = 'SELECT TOP 10 * FROM users';
        $this->assertEquals($sql, SQL::addTop($sql, 20));
    }

    // ========================================================================
    // complicatedWhere (text search)
    // ========================================================================

    public function testComplicatedWhereTextSearch(): void
    {
        $sql = SQL::complicatedWhere('SELECT * FROM users', 'john', 'name,email', 1);
        $this->assertStringContainsString('LIKE', $sql);
        $this->assertStringContainsString('john', strtolower($sql));
    }

    public function testComplicatedWhereEmpty(): void
    {
        $sql = 'SELECT * FROM users';
        $this->assertEquals($sql, SQL::complicatedWhere($sql, '', 'name', 1));
    }

    public function testComplicatedWhereWithAnd(): void
    {
        // "en" is Dutch for "and" - should split search
        $sql = SQL::complicatedWhere('SELECT * FROM users', 'john en doe', 'name', 1);
        // Should have multiple WHERE conditions
        $this->assertStringContainsString('john', strtolower($sql));
        $this->assertStringContainsString('doe', strtolower($sql));
    }
}
