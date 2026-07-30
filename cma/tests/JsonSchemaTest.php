<?php
/**
 * Tests for App\Library\JsonSchema — the validator behind save-time config
 * checking. Also validates every schema that ships against the config files
 * that ship, so a schema and its data can never drift apart unnoticed.
 *
 * Run with: php cma/tests/TestRunner.php JsonSchemaTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\JsonSchema;

class JsonSchemaTest extends TestCase
{
    private function schema(array $extra = []): array
    {
        return array_merge([
            'type' => 'object',
            'required' => ['databases'],
            'properties' => [
                'databases' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['id', 'name'],
                        'properties' => [
                            'id'      => ['type' => 'integer'],
                            'name'    => ['type' => 'string'],
                            'type'    => ['type' => 'string', 'enum' => ['access', 'mysql']],
                            'default' => ['type' => 'boolean'],
                        ],
                    ],
                ],
            ],
        ], $extra);
    }

    public function testValidDocumentHasNoErrors(): void
    {
        $data = ['databases' => [['id' => 6, 'name' => 'data', 'type' => 'access', 'default' => true]]];
        $this->assertSame([], JsonSchema::validate($data, $this->schema()));
    }

    public function testMissingRequiredKeyIsReportedWithPath(): void
    {
        $errors = JsonSchema::validate(['databases' => [['name' => 'data']]], $this->schema());
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('databases[0]', $errors[0]);
        $this->assertStringContainsString('id', $errors[0]);
    }

    public function testMissingTopLevelKeyIsReported(): void
    {
        $errors = JsonSchema::validate(['version' => '1.0'], $this->schema());
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('databases', $errors[0]);
    }

    public function testWrongTypeIsReported(): void
    {
        $errors = JsonSchema::validate(['databases' => [['id' => '6', 'name' => 'data']]], $this->schema());
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('moet integer zijn, is string', $errors[0]);
    }

    public function testEnumViolationIsReported(): void
    {
        $errors = JsonSchema::validate(['databases' => [['id' => 1, 'name' => 'x', 'type' => 'oracle']]], $this->schema());
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('oracle', $errors[0]);
    }

    public function testEmptyArrayCountsAsBothObjectAndArray(): void
    {
        $this->assertSame([], JsonSchema::validate(['databases' => []], $this->schema()));
        $this->assertSame([], JsonSchema::validate([], ['type' => 'object']));
        $this->assertSame([], JsonSchema::validate([], ['type' => 'array']));
    }

    public function testNullableTypeUnion(): void
    {
        $schema = ['type' => 'object', 'properties' => ['formId' => ['type' => ['integer', 'string', 'null']]]];
        $this->assertSame([], JsonSchema::validate(['formId' => null], $schema));
        $this->assertSame([], JsonSchema::validate(['formId' => 'users'], $schema));
        $this->assertSame([], JsonSchema::validate(['formId' => 12], $schema));
        $this->assertCount(1, JsonSchema::validate(['formId' => true], $schema));
    }

    public function testLocalRefIsResolved(): void
    {
        $schema = [
            'type' => 'object',
            'definitions' => ['level' => ['type' => 'string', 'enum' => ['user', 'admin']]],
            'properties' => ['accessLevel' => ['$ref' => '#/definitions/level']],
        ];
        $this->assertSame([], JsonSchema::validate(['accessLevel' => 'admin'], $schema));
        $this->assertCount(1, JsonSchema::validate(['accessLevel' => 'root'], $schema));
    }

    public function testUnresolvableRefBlamesNothing(): void
    {
        $schema = ['type' => 'object', 'properties' => ['x' => ['$ref' => '#/definitions/gone']]];
        $this->assertSame([], JsonSchema::validate(['x' => 'anything'], $schema));
    }

    public function testOneOfAcceptsEitherShape(): void
    {
        $schema = ['type' => 'object', 'properties' => ['variables' => ['oneOf' => [
            ['type' => 'object', 'additionalProperties' => ['type' => 'string']],
            ['type' => 'array', 'items' => []],
        ]]]];
        $this->assertSame([], JsonSchema::validate(['variables' => ['a' => 'x']], $schema));
        $this->assertSame([], JsonSchema::validate(['variables' => []], $schema));
        $this->assertSame([], JsonSchema::validate(['variables' => ['x', 'y']], $schema));
        $this->assertCount(1, JsonSchema::validate(['variables' => ['a' => 5]], $schema));
    }

    public function testAdditionalPropertiesAsSchemaValidatesUnnamedKeys(): void
    {
        $schema = ['type' => 'object', 'additionalProperties' => ['type' => 'integer']];
        $this->assertSame([], JsonSchema::validate(['a' => 1, 'b' => 2], $schema));
        $errors = JsonSchema::validate(['a' => 1, 'b' => 'two'], $schema);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('b', $errors[0]);
    }

    public function testMinimumMaximumAndPattern(): void
    {
        $schema = ['type' => 'object', 'properties' => [
            'n' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
            's' => ['type' => 'string', 'pattern' => '^\\d+\\.\\d+$'],
        ]];
        $this->assertSame([], JsonSchema::validate(['n' => 5, 's' => '1.0'], $schema));
        $this->assertCount(1, JsonSchema::validate(['n' => 0, 's' => '1.0'], $schema));
        $this->assertCount(1, JsonSchema::validate(['n' => 99, 's' => '1.0'], $schema));
        $this->assertCount(1, JsonSchema::validate(['n' => 5, 's' => 'x'], $schema));
    }

    /**
     * The cross-item rule draft-07 cannot express: at most one entry in the
     * array may carry default:true. This is what guards databases.json.
     */
    public function testMaxContainsCapsMatchingItems(): void
    {
        $schema = [
            'type' => 'array',
            'contains' => [
                'description' => 'één entry met default:true',
                'type' => 'object',
                'required' => ['default'],
                'properties' => ['default' => ['enum' => [true]]],
            ],
            'minContains' => 0,
            'maxContains' => 1,
        ];
        $this->assertSame([], JsonSchema::validate([['a' => 1], ['b' => 2]], $schema), 'none is allowed');
        $this->assertSame([], JsonSchema::validate([['default' => true], ['b' => 2]], $schema), 'exactly one');
        $this->assertSame([], JsonSchema::validate([['default' => false], ['default' => true]], $schema), 'false does not count');

        $errors = JsonSchema::validate([['default' => true], ['default' => true]], $schema);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('maximaal 1', $errors[0]);
        $this->assertStringContainsString('default:true', $errors[0]);
        $this->assertStringContainsString('heeft 2', $errors[0]);
    }

    public function testMinContainsDefaultsToOne(): void
    {
        $schema = ['type' => 'array', 'contains' => ['type' => 'integer']];
        $this->assertSame([], JsonSchema::validate([1, 'a'], $schema));
        $this->assertCount(1, JsonSchema::validate(['a', 'b'], $schema));
    }

    public function testUnknownKeywordsAreIgnored(): void
    {
        $schema = ['type' => 'string', 'title' => 'x', 'description' => 'y', 'default' => 'z', 'contentMediaType' => 'text/plain'];
        $this->assertSame([], JsonSchema::validate('anything', $schema));
    }

    /**
     * Every config the package ships must satisfy its own schema. This is the
     * test that would have caught the two drifts found when validation was
     * introduced: a contentblocks variable type absent from the enum, and menu
     * items whose formId is a name or null.
     */
    public function testShippedConfigsSatisfyTheirSchemas(): void
    {
        $cma = dirname(__DIR__);
        $pairs = [
            $cma . '/config/cma_branding.json'            => 'cma_branding',
            $cma . '/config/cma_reports.json'             => 'cma_reports',
            $cma . '/config/menu.json'                    => 'menu',
            $cma . '/config/control-types.json'           => 'control-types',
            $cma . '/control-types.json'                  => 'control-types',
            $cma . '/config/migrations.json'              => 'migrations',
            $cma . '/assets/contentblocks/contentblocks.json' => 'contentblocks',
        ];
        foreach ($pairs as $file => $schemaName) {
            if (!is_file($file)) {
                continue; // a config the package no longer ships
            }
            $schemaFile = $cma . '/config/schema/' . $schemaName . '.schema.json';
            $this->assertTrue(is_file($schemaFile), "Schema ontbreekt: $schemaName");
            $errors = JsonSchema::validate(
                json_decode((string)file_get_contents($file), true),
                json_decode((string)file_get_contents($schemaFile), true)
            );
            $this->assertSame([], $errors, basename($file) . ' schendt ' . basename($schemaFile) . ': ' . implode(' | ', $errors));
        }
    }

    /** Every form definition the package ships must satisfy form-definition.schema.json. */
    public function testShippedFormDefinitionsSatisfyTheirSchema(): void
    {
        $cma = dirname(__DIR__);
        $schema = json_decode((string)file_get_contents($cma . '/config/schema/form-definition.schema.json'), true);
        foreach (glob($cma . '/assets/forms/definitions/*.json') ?: [] as $file) {
            $errors = JsonSchema::validate(json_decode((string)file_get_contents($file), true), $schema);
            $this->assertSame([], $errors, basename($file) . ': ' . implode(' | ', $errors));
        }
    }
}
