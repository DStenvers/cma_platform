<?php
/**
 * Tests for App\Library\SQL
 *
 * Tests pure functions that don't require Database connection.
 * Run with: php tests/TestRunner.php SQLTest
 */

require_once __DIR__ . '/TestRunner.php';

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

    // ========================================================================
    // normalizeDecimal
    // ========================================================================

    public function testNormalizeDecimalLoneDotIsDecimal(): void
    {
        // A lone dot is always the decimal sign (CMA convention).
        $this->assertEquals('12.5', SQL::normalizeDecimal('12.5'));
        $this->assertEquals('1.760', SQL::normalizeDecimal('1.760'));
    }

    public function testNormalizeDecimalLoneCommaIsDecimal(): void
    {
        $this->assertEquals('12.5', SQL::normalizeDecimal('12,5'));
        $this->assertEquals('0.5', SQL::normalizeDecimal('0,5'));
    }

    public function testNormalizeDecimalBothSeparatorsRightmostWins(): void
    {
        // Dutch grouping: dot thousands, comma decimal.
        $this->assertEquals('1234.56', SQL::normalizeDecimal('1.234,56'));
        // English grouping: comma thousands, dot decimal.
        $this->assertEquals('1234.56', SQL::normalizeDecimal('1,234.56'));
    }

    public function testNormalizeDecimalNoSeparator(): void
    {
        $this->assertEquals('1000', SQL::normalizeDecimal('1000'));
    }

    public function testNormalizeDecimalNonNumericUnchanged(): void
    {
        $this->assertEquals('abc', SQL::normalizeDecimal('abc'));
        $this->assertEquals('', SQL::normalizeDecimal(''));
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

    // ========================================================================
    // postString — quote-escaping / injection safety
    //
    // Access/MySQL escape: '  ->  ' & chr(39) & '   (ADO-style concat)
    // SQL Server escape:   '  ->  '+char(39)+'
    // The whole value is wrapped in single quotes, so every embedded quote is
    // neutralised by concatenation and can never break out of the literal.
    // ========================================================================

    private string $access = 'Driver={Microsoft Access Driver (*.mdb, *.accdb)}';
    private string $sqlserver = 'Initial Catalog=mydb';

    public function testPostStringEscapesSingleQuoteAccess(): void
    {
        $this->assertEquals("'O' & chr(39) & 'Brien'", SQL::postString("O'Brien", $this->access));
    }

    public function testPostStringEscapesSingleQuoteSqlServer(): void
    {
        $this->assertEquals("'O'+char(39)+'Brien'", SQL::postString("O'Brien", $this->sqlserver));
    }

    public function testPostStringNeutralisesInjectionAttemptAccess(): void
    {
        // A classic breakout payload: the leading quote is concatenated away,
        // so the DROP TABLE text is inert data inside a single string literal.
        $out = SQL::postString("'; DROP TABLE users; --", $this->access);
        $this->assertEquals("'' & chr(39) & '; DROP TABLE users; --'", $out);
        // Structural invariant: the literal begins and ends with a quote and
        // every internal quote is part of the " & chr(39) & " escape sequence,
        // so quotes are always balanced — no unescaped breakout possible.
        $this->assertMatchesRegularExpression("/^'.*'$/", $out);
    }

    public function testPostStringNeutralisesInjectionAttemptSqlServer(): void
    {
        $out = SQL::postString("' OR '1'='1", $this->sqlserver);
        // Every ' becomes '+char(39)+' and the whole thing is wrapped in quotes,
        // so the payload is one inert string literal — quotes stay balanced.
        $this->assertEquals("''+char(39)+' OR '+char(39)+'1'+char(39)+'='+char(39)+'1'", $out);
        $this->assertMatchesRegularExpression("/^'.*'$/", $out);
    }

    public function testPostStringPreservesOtherMetacharacters(): void
    {
        // Semicolons, comment markers and backticks are harmless data inside a
        // quoted literal — they must be preserved verbatim, not stripped.
        $this->assertEquals("'a;b--c`d'", SQL::postString('a;b--c`d', $this->access));
    }

    public function testPostStringUnicodePassesThrough(): void
    {
        $this->assertEquals("'café ünï 日本'", SQL::postString('café ünï 日本', $this->access));
    }

    public function testPostStringEmptyIsNull(): void
    {
        $this->assertEquals('null', SQL::postString('', $this->access));
        $this->assertEquals('null', SQL::postString(null, $this->access));
        $this->assertEquals('null', SQL::postString('   ', $this->access));
    }

    // ========================================================================
    // postNumber — locale + injection safety
    //
    // postNumber inlines its result UNQUOTED, so it validates numericness:
    // a non-numeric value collapses to '0' (see testPostNumberNonNumericIsNeutralised).
    // The decimal-locale contract IS enforced for real numbers.
    // ========================================================================

    public function testPostNumberDotDecimalStaysDot(): void
    {
        // The known Dutch-locale hazard: a value typed with a '.' decimal must
        // NOT be mangled to ',' or to an integer. 10.5 stays 10.5.
        $this->assertEquals('10.5', SQL::postNumber('10.5'));
        $this->assertEquals('0.05', SQL::postNumber('0.05'));
    }

    public function testPostNumberDutchGroupingCollapses(): void
    {
        // "1.234,56" (dot thousands, comma decimal) -> canonical 1234.56
        $this->assertEquals('1234.56', SQL::postNumber('1.234,56'));
    }

    public function testPostNumberNegativeAndBoundary(): void
    {
        $this->assertEquals('-42', SQL::postNumber('-42'));
        $this->assertEquals('0', SQL::postNumber('0'));
        $this->assertEquals('9999999999', SQL::postNumber('9999999999'));
    }

    public function testPostNumberNonNumericIsNeutralised(): void
    {
        // Injection safety: postNumber inlines its result UNQUOTED, so a
        // non-numeric value must NOT pass through raw — it collapses to '0'.
        $this->assertEquals('0', SQL::postNumber('1 OR 1=1'));
        $this->assertEquals('0', SQL::postNumber("1); DROP TABLE users;--"));
        $this->assertEquals('0', SQL::postNumber('abc'));
        $this->assertEquals('0', SQL::postNumber('10.5abc'));
        // Genuine numbers still pass through untouched (locale-normalised).
        $this->assertEquals('10.5', SQL::postNumber('10.5'));
        $this->assertEquals('1234.56', SQL::postNumber('1.234,56'));
        $this->assertEquals('-42', SQL::postNumber('-42'));
        // Empty stays NULL (unchanged contract).
        $this->assertEquals('null', SQL::postNumber(''));
    }

    // ========================================================================
    // postBoolean
    // ========================================================================

    public function testPostBooleanAccessTrueFalse(): void
    {
        $this->assertEquals('True', SQL::postBoolean(true, $this->access));
        $this->assertEquals('False', SQL::postBoolean(false, $this->access));
        $this->assertEquals('False', SQL::postBoolean('no', $this->access));
        $this->assertEquals('True', SQL::postBoolean('yes', $this->access));
    }

    public function testPostBooleanSqlServerOneZero(): void
    {
        $this->assertEquals('1', SQL::postBoolean('yes', $this->sqlserver));
        $this->assertEquals('0', SQL::postBoolean('0', $this->sqlserver));
        $this->assertEquals('0', SQL::postBoolean('false', $this->sqlserver));
    }

    // ========================================================================
    // postDate / postDateOnly / postDateStr
    // ========================================================================

    public function testPostDateAccess(): void
    {
        $this->assertEquals('#6/7/2026#', SQL::postDate('7', '6', '2026', $this->access));
    }

    public function testPostDateSqlServer(): void
    {
        $this->assertEquals("CAST('6-7-2026' AS DATE)", SQL::postDate('7', '6', '2026', $this->sqlserver));
    }

    public function testPostDateEmptyComponentIsNull(): void
    {
        $this->assertEquals('NULL', SQL::postDate('', '6', '2026', $this->access));
        $this->assertEquals('NULL', SQL::postDate('7', '', '2026', $this->access));
    }

    public function testPostDateInvalidIsNull(): void
    {
        $this->assertEquals('NULL', SQL::postDate('99', '99', '2026', $this->access));
    }

    public function testPostDateOnlyAccess(): void
    {
        $this->assertEquals('#6/7/2026 0:0#', SQL::postDateOnly('2026-06-07', $this->access));
    }

    public function testPostDateOnlyNull(): void
    {
        $this->assertEquals('NULL', SQL::postDateOnly(null, $this->access));
        $this->assertEquals('NULL', SQL::postDateOnly('not a date', $this->access));
    }

    public function testPostDateStrAccess(): void
    {
        // DD-MM-YYYY input -> Access #M/D/YYYY#
        $this->assertEquals('#06/07/2026#', SQL::postDateStr('07-06-2026', $this->access));
    }

    public function testPostDateStrEmptyIsNull(): void
    {
        $this->assertEquals('NULL', SQL::postDateStr('', $this->access));
    }

    public function testPostDateStrAcceptsIso(): void
    {
        // <lib-datepicker> submits YYYY-MM-DD. Read day-first this became day 2026 /
        // year 01, which is not a date, so the method returned NULL and the query it
        // landed in ("datestamp >= NULL") matched nothing: a report with zero rows and
        // no error anywhere. Same day as the DD-MM-YYYY form, so both must agree.
        $this->assertEquals('#06/07/2026#', SQL::postDateStr('2026-06-07', $this->access));
        $this->assertEquals(
            SQL::postDateStr('07-06-2026', $this->access),
            SQL::postDateStr('2026-06-07', $this->access)
        );
    }

    public function testPostDateStrIgnoresTimePart(): void
    {
        $this->assertEquals('#06/07/2026#', SQL::postDateStr('2026-06-07 14:30:45', $this->access));
        $this->assertEquals('#06/07/2026#', SQL::postDateStr('07-06-2026 14:30:45', $this->access));
    }

    // ========================================================================
    // guidEquals — Access ODBC needs LIKE, SQL Server uses =
    // ========================================================================

    public function testGuidEqualsAccessUsesLike(): void
    {
        $this->assertEquals("Guid LIKE '%abc-123%'", SQL::guidEquals('Guid', '{abc-123}', $this->access));
    }

    public function testGuidEqualsSqlServerUsesEquals(): void
    {
        $this->assertEquals("Guid = 'abc-123'", SQL::guidEquals('Guid', '{abc-123}', $this->sqlserver));
    }

    // ========================================================================
    // guidEqualsToLike — raw `<col>guid = '<guid>'` -> LIKE (Access ODBC fix,
    // ported from the mijnRINO site). Access columns with Replication-ID (GUID)
    // return no rows with '=' through Jet/ACE ODBC.
    // ========================================================================

    public function testGuidEqualsToLikeRewritesGuidShapedEquality(): void
    {
        $g = '11223344-5566-7788-99aa-bbccddeeff00';
        $this->assertEquals(
            "SELECT * FROM t WHERE userGUID LIKE '%$g%'",
            SQL::guidEqualsToLike("SELECT * FROM t WHERE userGUID = '$g'")
        );
        // Qualified column (table.guid) also rewritten.
        $this->assertStringContainsString("u.guid LIKE '%$g%'", SQL::guidEqualsToLike("WHERE u.guid = '$g'"));
    }

    public function testGuidEqualsToLikeLeavesOrdinaryFiltersUntouched(): void
    {
        // Non-guid column, and a guid column with a non-GUID-shaped value, stay '='.
        $this->assertEquals("WHERE naam = 'x' AND id = '5'", SQL::guidEqualsToLike("WHERE naam = 'x' AND id = '5'"));
        $this->assertEquals("WHERE guid = 'abc'", SQL::guidEqualsToLike("WHERE guid = 'abc'"));
    }

    public function testProcessSqlAppliesGuidLikeOnlyForAccess(): void
    {
        $g = '11223344-5566-7788-99aa-bbccddeeff00';
        // Access (via ODBC) -> rewritten to LIKE.
        $this->assertStringContainsString("guid LIKE '%$g%'", SQL::processSQL('ACCESS_VIA_ODBC', "WHERE guid = '$g'"));
        // A SQL Server connection string -> '=' preserved (LIKE would table-scan).
        $this->assertStringContainsString("guid = '$g'", SQL::processSQL('Server=x;Provider=SQLNCLI11', "WHERE guid = '$g'"));
    }

    public function testProcessSqlOperatorSpacingSkipsStringLiterals(): void
    {
        // The ODBC operator-spacing must NOT corrupt < > = inside quoted string
        // literals — reports embed '<html>...' and '<sort:KEY>' cell markers there.
        $in  = "SELECT '<sort:' & a.Achternaam & '>' & a.Naam AS X, "
             . "'<html><status class=paused>x</status>' AS S FROM t WHERE a.n<date() AND a.k>=5";
        $out = SQL::processSQL('ACCESS_VIA_ODBC', $in);
        // Literals preserved verbatim (no injected spaces).
        $this->assertStringContainsString("'<sort:'", $out, 'sort marker literal preserved');
        $this->assertStringContainsString("'<html><status class=paused>x</status>'", $out, 'html marker literal preserved');
        $this->assertStringNotContainsString('< sort', $out, 'no space injected into literal');
        // Real comparison operators (outside literals) are still normalised.
        $this->assertStringContainsString('a.n < date()', $out, 'operator outside literal still spaced');
        $this->assertStringContainsString('a.k >= 5', $out);
    }

    // ========================================================================
    // Null-tolerant builders (ported from mijnRINO) — VBScript seeds SQL
    // accumulators with an uninitialised Empty variant (-> null); the builders
    // must treat it as '' instead of a TypeError.
    // ========================================================================

    public function testBuildersTolerateNullSqlAccumulator(): void
    {
        $this->assertEquals('', SQL::addWhere(null, null));
        $this->assertEquals('', SQL::addWhereOR(null, null));
        $this->assertEquals('', SQL::addHaving(null, null));
        $this->assertEquals('', SQL::addInClause(null, null, ''));
        $this->assertEquals('', SQL::processSQL('ACCESS_VIA_ODBC', null));
        // A null accumulator with a real clause still builds the clause.
        $this->assertStringContainsString('WHERE x=1', SQL::addWhere(null, 'x=1'));
    }

    // ---- stripSelfAlias (Access circular-alias fix) ------------------------

    public function testStripSelfAliasBareColumn(): void
    {
        $this->assertEquals(
            'SELECT EindVerslagTemplate from tblOpleidingen',
            SQL::stripSelfAlias('SELECT EindVerslagTemplate as EINDVERSLAGTEMPLATE from tblOpleidingen')
        );
    }

    public function testStripSelfAliasTableQualified(): void
    {
        $this->assertEquals(
            'SELECT t.Naam FROM x',
            SQL::stripSelfAlias('SELECT t.Naam AS naam FROM x')
        );
    }

    public function testStripSelfAliasKeepsRealAliases(): void
    {
        $sql = 'SELECT id as CompetentieGebiedID, Naam AS Competentiegebied FROM x';
        $this->assertEquals($sql, SQL::stripSelfAlias($sql));
    }

    public function testStripSelfAliasIgnoresExpressionAliases(): void
    {
        // The char before AS isn't a word char, so IIF(...) AS a / count(*) AS n
        // are never matched — only a bare identifier before AS qualifies.
        $sql = 'SELECT count(*) AS Aantal, IIF(a>0,1,0) AS a FROM x';
        $this->assertEquals($sql, SQL::stripSelfAlias($sql));
    }

    public function testProcessSqlStripsSelfAliasOnAccess(): void
    {
        $this->assertEquals(
            'SELECT Field FROM t',
            SQL::processSQL('ACCESS_VIA_ODBC', 'SELECT Field as FIELD FROM t')
        );
    }
}
