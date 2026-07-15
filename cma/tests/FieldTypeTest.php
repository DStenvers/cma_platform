<?php
/**
 * Tests for Cma\Services\FieldType::categoryForSchemaType — the shared schema
 * data-type -> list-column category mapping used by both the legacy
 * (TableService) and JSON (JsonFormService) list renderers.
 *
 * Run with: php tests/TestRunner.php FieldTypeTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/classes/Services/FieldType.php';

use Cma\Services\FieldType;

class FieldTypeTest extends TestCase
{
    public function testTimeCode134(): void
    {
        // The bug this consolidation fixes: JSON path used to miss code 134.
        $this->assertEquals('time', FieldType::categoryForSchemaType(134));
        $this->assertEquals('time', FieldType::categoryForSchemaType('134'));
        $this->assertEquals('time', FieldType::categoryForSchemaType('time'));
    }

    public function testDateCodesAndNames(): void
    {
        foreach ([7, 133, 135] as $code) {
            $this->assertEquals('date', FieldType::categoryForSchemaType($code));
        }
        foreach (['date', 'datetime', 'datetime2', 'smalldatetime', 'datetimeoffset'] as $name) {
            $this->assertEquals('date', FieldType::categoryForSchemaType($name));
        }
    }

    public function testNumberCodesIncludingPreviouslyMissedOnes(): void
    {
        // 14,17-21 were recognised by the legacy path but not the JSON path.
        foreach ([2, 3, 4, 5, 6, 14, 17, 18, 19, 20, 21, 131] as $code) {
            $this->assertEquals('number', FieldType::categoryForSchemaType($code), "code $code");
        }
        foreach (['int', 'bigint', 'decimal', 'double', 'money'] as $name) {
            $this->assertEquals('number', FieldType::categoryForSchemaType($name), $name);
        }
    }

    public function testUnknownAndEmptyReturnNull(): void
    {
        $this->assertNull(FieldType::categoryForSchemaType(''));
        $this->assertNull(FieldType::categoryForSchemaType('varchar'));
        $this->assertNull(FieldType::categoryForSchemaType('text'));
        $this->assertNull(FieldType::categoryForSchemaType(202)); // adWChar
    }
}
