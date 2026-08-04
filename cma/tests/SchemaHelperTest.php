<?php
/**
 * SchemaHelper / SqlHelper unit tests.
 *
 * Covers the PURE, connection-free logic that previously had no tests:
 *  - SchemaHelper::categorizeType()  (int ODBC/ADO codes + string type names)
 *  - SchemaHelper::getFieldTypeName()
 *  - SqlHelper::formatFieldName()
 *
 * The DB-touching methods (getTables/getColumns/…) are intentionally left to
 * integration coverage — they need a live connection.
 *
 * Run with: php tests/TestRunner.php SchemaHelperTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/SchemaHelper.php';
require_once __DIR__ . '/../classes/SqlHelper.php';

use Cma\SchemaHelper;
use Cma\SqlHelper;

class SchemaHelperTest extends TestCase
{
    // ---- getColumns(): de Access-terugval ---------------------------------

    /**
     * De PDO-terugval in de Access-tak van getColumns() moet afhangen van het RESULTAAT,
     * niet van de vraag of er een ODBC-handle is.
     *
     * Waarom dit een BRONcontrole is en geen gewone unittest: getColumns() heeft een levende
     * verbinding nodig, en dit bestand laat die methodes bewust aan integratiedekking over
     * (zie de kop). Het gedrag dat hier misging is puur besturingslogica, en dat is op deze
     * manier wel vast te leggen.
     *
     * Wat er misging: odbc_columns() kan een geldig resultaat teruggeven en er tóch geen enkele
     * rij in stoppen. Gemeten op een mijnRINO-site (Access via PDO_ODBC, extensie geladen,
     * handle open): tblUsers heeft 16 kolommen en 17 rijen, odbc_columns() gaf 0 rijen - ook
     * met ('',''), ('%','%','%') en (null,null,'%'). Omdat de terugval in een `else` stond die
     * alleen bij een ONTBREKENDE handle liep, gaf getColumns() een lege lijst en was
     * hasColumn() voor elke kolom false.
     *
     * Gevolg in de praktijk: cma/login.php kiest met hasColumn('userLevel') tussen de moderne
     * en de legacy adminkolom, viel daardoor altijd in de legacy-tak, en probeerde te schrijven
     * naar userAdministrator - een kolom die op zo'n site niet bestaat. Twee stacktraces per
     * paginaload, ~80 logregels per dag.
     */
    public function testAccessKolommenVallenTerugOpPdoBijEenLeegOdbcResultaat(): void
    {
        $bron = file_get_contents(__DIR__ . '/../classes/SchemaHelper.php');

        $this->assertStringContainsString(
            'if (!$columns) {',
            $bron,
            'de PDO-terugval in getColumns() hangt niet meer af van het resultaat'
        );

        // En de terugval moet NA de odbc-tak staan, niet ervoor.
        $posOdbc = strpos($bron, 'odbc_columns($odbc');
        $this->assertTrue($posOdbc !== false, 'de odbc-tak van getColumns() is verdwenen');
        $posVal = strpos($bron, 'if (!$columns) {', (int)$posOdbc);
        $this->assertTrue($posVal !== false, 'er staat geen terugval na de odbc-tak van getColumns()');
    }

    // ---- categorizeType(): integer ODBC/ADO type codes -------------------

    public function testCategorizeDateTypes(): void
    {
        $this->assertEquals('date', SchemaHelper::categorizeType(SchemaHelper::SQL_TYPE_DATE));
        $this->assertEquals('date', SchemaHelper::categorizeType(SchemaHelper::SQL_TYPE_TIME));
        $this->assertEquals('date', SchemaHelper::categorizeType(SchemaHelper::SQL_TYPE_TIMESTAMP));
        $this->assertEquals('date', SchemaHelper::categorizeType(SchemaHelper::ADO_DATE));
        // ODBC 2.x deprecated codes (used by the MS Access ODBC driver).
        $this->assertEquals('date', SchemaHelper::categorizeType(SchemaHelper::SQL_DATE_DEPRECATED));
    }

    public function testAccessYesNoIsSqlBit(): void
    {
        // MS Access Yes/No fields report SQL_BIT (-7), not ADO_BOOLEAN (11).
        $this->assertEquals('boolean', SchemaHelper::categorizeType(SchemaHelper::SQL_BIT));
    }

    public function testType11ResolvesToDateNotBoolean(): void
    {
        // Documented collision: ADO_BOOLEAN and SQL_TIMESTAMP_DEPRECATED are both
        // the integer 11. categorizeType() must resolve 11 as a date (the Access
        // driver never uses 11 for Yes/No), so the deprecated-timestamp check wins.
        $this->assertEquals(11, SchemaHelper::ADO_BOOLEAN);
        $this->assertEquals(11, SchemaHelper::SQL_TIMESTAMP_DEPRECATED);
        $this->assertEquals('date', SchemaHelper::categorizeType(11));
    }

    public function testCategorizeNumberTypes(): void
    {
        $this->assertEquals('number', SchemaHelper::categorizeType(SchemaHelper::SQL_INTEGER));
        $this->assertEquals('number', SchemaHelper::categorizeType(SchemaHelper::SQL_DOUBLE));
        $this->assertEquals('number', SchemaHelper::categorizeType(SchemaHelper::ADO_INTEGER));
        $this->assertEquals('number', SchemaHelper::categorizeType(SchemaHelper::ADO_CURRENCY));
    }

    public function testCategorizeBinaryTypes(): void
    {
        $this->assertEquals('binary', SchemaHelper::categorizeType(SchemaHelper::SQL_BINARY));
        $this->assertEquals('binary', SchemaHelper::categorizeType(SchemaHelper::SQL_LONGVARBINARY));
    }

    public function testTextIsTheDefault(): void
    {
        $this->assertEquals('text', SchemaHelper::categorizeType(SchemaHelper::SQL_CHAR));
        $this->assertEquals('text', SchemaHelper::categorizeType(SchemaHelper::SQL_VARCHAR));
        $this->assertEquals('text', SchemaHelper::categorizeType(999999)); // unknown code
    }

    // ---- categorizeType(): string type names (PDO metadata fallback) -----

    public function testCategorizeByNameIsCaseInsensitive(): void
    {
        $this->assertEquals('date', SchemaHelper::categorizeType('datetime'));
        $this->assertEquals('date', SchemaHelper::categorizeType('DATE'));
        $this->assertEquals('date', SchemaHelper::categorizeType('smalldatetime'));
    }

    public function testCategorizeByNameBoolean(): void
    {
        $this->assertEquals('boolean', SchemaHelper::categorizeType('bit'));
        $this->assertEquals('boolean', SchemaHelper::categorizeType('yesno'));
    }

    public function testCategorizeByNameNumber(): void
    {
        $this->assertEquals('number', SchemaHelper::categorizeType('int'));
        $this->assertEquals('number', SchemaHelper::categorizeType('autonumber'));
        $this->assertEquals('number', SchemaHelper::categorizeType('currency'));
    }

    public function testCategorizeByNameBinaryAndText(): void
    {
        $this->assertEquals('binary', SchemaHelper::categorizeType('oleobject'));
        $this->assertEquals('binary', SchemaHelper::categorizeType('longbinary'));
        $this->assertEquals('text', SchemaHelper::categorizeType('varchar'));
        $this->assertEquals('text', SchemaHelper::categorizeType('nvarchar'));
    }

    // ---- getFieldTypeName() ----------------------------------------------

    public function testGetFieldTypeName(): void
    {
        $this->assertEquals('Boolean', SchemaHelper::getFieldTypeName(SchemaHelper::SQL_BIT));
        $this->assertEquals('Integer', SchemaHelper::getFieldTypeName(SchemaHelper::SQL_INTEGER));
        $this->assertEquals('DateTime', SchemaHelper::getFieldTypeName(SchemaHelper::SQL_TYPE_TIMESTAMP));
        $this->assertEquals('VarChar', SchemaHelper::getFieldTypeName(SchemaHelper::SQL_VARCHAR));
        $this->assertEquals('Double', SchemaHelper::getFieldTypeName(SchemaHelper::SQL_FLOAT));
        $this->assertEquals('Decimal', SchemaHelper::getFieldTypeName(SchemaHelper::SQL_DECIMAL));
    }

    // ---- SqlHelper::formatFieldName() ------------------------------------

    public function testFormatFieldNameBracketsPlainField(): void
    {
        $this->assertEquals('[field]', SqlHelper::formatFieldName('field'));
    }

    public function testFormatFieldNameStripsTableQualifier(): void
    {
        $this->assertEquals('[field]', SqlHelper::formatFieldName('table.field'));
    }

    public function testFormatFieldNameIsIdempotentOnBrackets(): void
    {
        $this->assertEquals('[field]', SqlHelper::formatFieldName('[field]'));
    }

    // ---- getSqlServerTypeName() ------------------------------------------

    public function testSqlServerTypeNameOdbcCodes(): void
    {
        $this->assertEquals('datetime', SchemaHelper::getSqlServerTypeName(SchemaHelper::SQL_TYPE_TIMESTAMP));
        $this->assertEquals('date', SchemaHelper::getSqlServerTypeName(SchemaHelper::SQL_TYPE_DATE));
        $this->assertEquals('bit', SchemaHelper::getSqlServerTypeName(SchemaHelper::SQL_BIT));
        $this->assertEquals('int', SchemaHelper::getSqlServerTypeName(SchemaHelper::SQL_INTEGER));
        $this->assertEquals('decimal', SchemaHelper::getSqlServerTypeName(SchemaHelper::SQL_DECIMAL));
    }

    public function testSqlServerTypeNameAppendsLength(): void
    {
        $this->assertEquals('varchar(50)', SchemaHelper::getSqlServerTypeName(SchemaHelper::SQL_VARCHAR, 50));
        $this->assertEquals('char(10)', SchemaHelper::getSqlServerTypeName(SchemaHelper::SQL_CHAR, 10));
        // No length -> bare type name.
        $this->assertEquals('varchar', SchemaHelper::getSqlServerTypeName(SchemaHelper::SQL_VARCHAR));
    }

    public function testSqlServerTypeNameValue6IsFloatNotMoney(): void
    {
        // Documented integer collision: SQL_FLOAT (6) and ADO_CURRENCY (6) share
        // the code 6. getSqlServerTypeName checks the ODBC switch first, so 6
        // always resolves to 'float' — ADO_CURRENCY's 'money' branch is
        // unreachable for the literal integer. Pinned so the collision is
        // visible, not silently "fixed" into ambiguity. (Same class of
        // collision as the type-11 date/boolean case above.)
        $this->assertEquals(6, SchemaHelper::SQL_FLOAT);
        $this->assertEquals(6, SchemaHelper::ADO_CURRENCY);
        $this->assertEquals('float', SchemaHelper::getSqlServerTypeName(6));
    }
}
