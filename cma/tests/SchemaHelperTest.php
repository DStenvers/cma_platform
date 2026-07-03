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
}
