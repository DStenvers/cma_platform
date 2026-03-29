<?php
/**
 * QueryBuilder Unit Tests
 *
 * Tests for the SQL generation in QueryBuilder.php
 * Run with: php tests/QueryBuilderTest.php
 */

// Setup autoloading
require_once __DIR__ . '/../vendor/autoload.php';

// Include the QueryBuilder class
require_once __DIR__ . '/../classes/QueryBuilder.php';
require_once __DIR__ . '/../classes/SchemaHelper.php';

use Cma\QueryBuilder;

// Track test results
$testsPassed = 0;
$testsFailed = 0;
$failures = [];

/**
 * Simple assertion function
 */
function assertEqual($expected, $actual, string $message = ''): void
{
    global $testsPassed, $testsFailed, $failures;

    if ($expected === $actual) {
        $testsPassed++;
        echo ".";
    } else {
        $testsFailed++;
        $failures[] = [
            'message' => $message,
            'expected' => $expected,
            'actual' => $actual
        ];
        echo "F";
    }
}

function assertTrue(bool $condition, string $message = ''): void
{
    assertEqual(true, $condition, $message);
}

function assertFalse(bool $condition, string $message = ''): void
{
    assertEqual(false, $condition, $message);
}

function assertContains(string $needle, string $haystack, string $message = ''): void
{
    global $testsPassed, $testsFailed, $failures;

    if (strpos($haystack, $needle) !== false) {
        $testsPassed++;
        echo ".";
    } else {
        $testsFailed++;
        $failures[] = [
            'message' => $message,
            'expected' => "String to contain: $needle",
            'actual' => $haystack
        ];
        echo "F";
    }
}

function assertNotContains(string $needle, string $haystack, string $message = ''): void
{
    global $testsPassed, $testsFailed, $failures;

    if (strpos($haystack, $needle) === false) {
        $testsPassed++;
        echo ".";
    } else {
        $testsFailed++;
        $failures[] = [
            'message' => $message,
            'expected' => "String to NOT contain: $needle",
            'actual' => $haystack
        ];
        echo "F";
    }
}

// ============================================================================
// TEST CASES
// ============================================================================

echo "\n=== QueryBuilder Tests ===\n\n";

// Test 1: Simple SELECT from one table
echo "Test 1: Simple SELECT from one table\n";
$builder = new QueryBuilder([
    'tables' => [
        ['name' => 'tblUsers', 'joins' => []]
    ],
    'fields' => [
        ['table' => 'tblUsers', 'field' => 'ID', 'visible' => true],
        ['table' => 'tblUsers', 'field' => 'Name', 'visible' => true]
    ]
]);
$sql = $builder->toSql();
assertContains('SELECT', $sql, "Should contain SELECT");
assertContains('[tblUsers].[ID]', $sql, "Should contain ID field");
assertContains('[tblUsers].[Name]', $sql, "Should contain Name field");
assertContains('FROM [tblUsers]', $sql, "Should have FROM clause");
echo "\n";

// Test 2: Simple JOIN
echo "Test 2: Simple INNER JOIN\n";
$builder = new QueryBuilder([
    'tables' => [
        [
            'name' => 'tblUsers',
            'joins' => [
                [
                    'table' => 'tblRoles',
                    'type' => 'INNER',
                    'on' => '[tblUsers].[RoleID] = [tblRoles].[ID]'
                ]
            ]
        ],
        ['name' => 'tblRoles', 'joins' => []]
    ],
    'fields' => [
        ['table' => 'tblUsers', 'field' => 'Name', 'visible' => true],
        ['table' => 'tblRoles', 'field' => 'RoleName', 'visible' => true]
    ]
]);
$sql = $builder->toSql();
assertContains('INNER JOIN [tblRoles]', $sql, "Should have INNER JOIN");
assertContains('[tblUsers].[RoleID] = [tblRoles].[ID]', $sql, "Should have ON clause");
echo "\n";

// Test 3: LEFT JOIN
echo "Test 3: LEFT JOIN\n";
$builder = new QueryBuilder([
    'tables' => [
        [
            'name' => 'tblUsers',
            'joins' => [
                [
                    'table' => 'tblProfiles',
                    'type' => 'LEFT',
                    'on' => '[tblUsers].[ID] = [tblProfiles].[UserID]'
                ]
            ]
        ]
    ],
    'fields' => [
        ['table' => 'tblUsers', 'field' => 'Name', 'visible' => true]
    ]
]);
$sql = $builder->toSql();
assertContains('LEFT JOIN [tblProfiles]', $sql, "Should have LEFT JOIN");
echo "\n";

// Test 4: Multiple JOINs (MS Access nested parentheses)
echo "Test 4: Multiple JOINs with MS Access nested parentheses\n";
$builder = new QueryBuilder([
    'tables' => [
        [
            'name' => 'tblA',
            'joins' => [
                [
                    'table' => 'tblB',
                    'type' => 'INNER',
                    'on' => '[tblA].[B_ID] = [tblB].[ID]'
                ],
                [
                    'table' => 'tblC',
                    'type' => 'INNER',
                    'on' => '[tblB].[C_ID] = [tblC].[ID]'
                ]
            ]
        ]
    ],
    'fields' => [
        ['table' => 'tblA', 'field' => 'Name', 'visible' => true]
    ]
]);
$sql = $builder->toSql();
assertContains('((', $sql, "Should have nested parentheses for MS Access");
assertContains('INNER JOIN [tblB]', $sql, "Should have first JOIN");
assertContains('INNER JOIN [tblC]', $sql, "Should have second JOIN");
echo "\n";

// Test 5: ORDER BY
echo "Test 5: ORDER BY clause\n";
$builder = new QueryBuilder([
    'tables' => [
        ['name' => 'tblUsers', 'joins' => []]
    ],
    'fields' => [
        ['table' => 'tblUsers', 'field' => 'Name', 'visible' => true]
    ],
    'sorting' => [
        ['table' => 'tblUsers', 'field' => 'Name', 'direction' => 'ASC'],
        ['table' => 'tblUsers', 'field' => 'ID', 'direction' => 'DESC']
    ]
]);
$sql = $builder->toSql();
assertContains('ORDER BY', $sql, "Should have ORDER BY");
assertContains('[tblUsers].[Name] ASC', $sql, "Should have first sort");
assertContains('[tblUsers].[ID] DESC', $sql, "Should have second sort");
echo "\n";

// Test 6: WHERE clause with conditions
echo "Test 6: WHERE clause with conditions\n";
$builder = new QueryBuilder([
    'tables' => [
        ['name' => 'tblUsers', 'joins' => []]
    ],
    'fields' => [
        ['table' => 'tblUsers', 'field' => 'Name', 'visible' => true]
    ],
    'conditions' => [
        [
            'table' => 'tblUsers',
            'field' => 'Status',
            'operator' => '=',
            'value' => 'Active',
            'typeCategory' => 'text',
            'logic' => ''
        ]
    ]
]);
$sql = $builder->toSql();
assertContains('WHERE', $sql, "Should have WHERE");
assertContains("[tblUsers].[Status] = 'Active'", $sql, "Should have condition");
echo "\n";

// Test 7: SELECT DISTINCT
echo "Test 7: SELECT DISTINCT\n";
$builder = new QueryBuilder([
    'tables' => [
        ['name' => 'tblUsers', 'joins' => []]
    ],
    'fields' => [
        ['table' => 'tblUsers', 'field' => 'Name', 'visible' => true]
    ],
    'distinct' => true
]);
$sql = $builder->toSql();
assertContains('SELECT DISTINCT', $sql, "Should have SELECT DISTINCT");
echo "\n";

// Test 8: TOP N limit
echo "Test 8: TOP N limit\n";
$builder = new QueryBuilder([
    'tables' => [
        ['name' => 'tblUsers', 'joins' => []]
    ],
    'fields' => [
        ['table' => 'tblUsers', 'field' => 'Name', 'visible' => true]
    ],
    'topN' => 100
]);
$sql = $builder->toSql();
assertContains('SELECT TOP 100', $sql, "Should have TOP 100");
echo "\n";

// Test 9: Field alias
echo "Test 9: Field alias\n";
$builder = new QueryBuilder([
    'tables' => [
        ['name' => 'tblUsers', 'joins' => []]
    ],
    'fields' => [
        ['table' => 'tblUsers', 'field' => 'Name', 'alias' => 'UserName', 'visible' => true]
    ]
]);
$sql = $builder->toSql();
assertContains('[Name] AS [UserName]', $sql, "Should have field alias");
echo "\n";

// Test 10: GROUP BY
echo "Test 10: GROUP BY clause\n";
$builder = new QueryBuilder([
    'tables' => [
        ['name' => 'tblUsers', 'joins' => []]
    ],
    'fields' => [
        ['table' => 'tblUsers', 'field' => 'Department', 'visible' => true]
    ],
    'grouping' => [
        ['table' => 'tblUsers', 'field' => 'Department']
    ]
]);
$sql = $builder->toSql();
assertContains('GROUP BY', $sql, "Should have GROUP BY");
assertContains('[tblUsers].[Department]', $sql, "Should group by Department");
echo "\n";

// ============================================================================
// BUG REPRODUCTION TESTS
// ============================================================================

// Test 11: CRITICAL - Table referenced in ON but not in FROM should report error
echo "Test 11: CRITICAL - Detect missing table in ON clause\n";
// This reproduces the bug where tblDeelname is referenced in ON but not joined
$builder = new QueryBuilder([
    'tables' => [
        [
            'name' => 'tblDocenten',
            'joins' => [
                // These JOINs reference tblDeelname in ON, but tblDeelname is never joined!
                [
                    'table' => 'tblDeelnemers',
                    'type' => 'INNER',
                    'on' => '[tblDeelname].[fkDeelnemer] = [tblDeelnemers].[ID]'
                ],
                [
                    'table' => 'tblOpleidingen',
                    'type' => 'INNER',
                    'on' => '[tblDeelname].[fkOpleiding] = [tblOpleidingen].[ID]'
                ]
            ]
        ],
        ['name' => 'tblDeelname', 'joins' => []],
        ['name' => 'tblDeelnemers', 'joins' => []],
        ['name' => 'tblOpleidingen', 'joins' => []]
    ],
    'fields' => [
        ['table' => 'tblDocenten', 'field' => 'VolledigeNaam', 'visible' => true],
        ['table' => 'tblDeelname', 'field' => 'fkHoofdDocent', 'visible' => true],
        ['table' => 'tblDeelnemers', 'field' => 'VolledigeNaam', 'visible' => true],
        ['table' => 'tblOpleidingen', 'field' => 'Titel', 'visible' => true]
    ]
]);
$sql = $builder->toSql();
echo "\nGenerated SQL:\n$sql\n";

// The QueryBuilder should now detect and report the error
$errors = $builder->getErrors();
echo "Builder errors: " . (empty($errors) ? "(none)" : implode(', ', $errors)) . "\n\n";

// Verify that the error is detected
assertTrue(!empty($errors), "Should detect missing table error");
$errorText = implode(' ', $errors);
assertContains('tblDeelname', $errorText, "Error should mention tblDeelname");
echo "\n";

// Test 12: Proper chain of JOINs
echo "Test 12: Proper chain of JOINs (tblDeelname properly joined)\n";
$builder = new QueryBuilder([
    'tables' => [
        [
            'name' => 'tblDocenten',
            'joins' => [
                // First join tblDeelname to tblDocenten
                [
                    'table' => 'tblDeelname',
                    'type' => 'INNER',
                    'on' => '[tblDeelname].[fkHoofdDocent] = [tblDocenten].[ID]'
                ],
                // Then join other tables through tblDeelname
                [
                    'table' => 'tblDeelnemers',
                    'type' => 'INNER',
                    'on' => '[tblDeelname].[fkDeelnemer] = [tblDeelnemers].[ID]'
                ],
                [
                    'table' => 'tblOpleidingen',
                    'type' => 'INNER',
                    'on' => '[tblDeelname].[fkOpleiding] = [tblOpleidingen].[ID]'
                ]
            ]
        ]
    ],
    'fields' => [
        ['table' => 'tblDocenten', 'field' => 'VolledigeNaam', 'visible' => true],
        ['table' => 'tblDeelname', 'field' => 'fkHoofdDocent', 'visible' => true],
        ['table' => 'tblDeelnemers', 'field' => 'VolledigeNaam', 'visible' => true],
        ['table' => 'tblOpleidingen', 'field' => 'Titel', 'visible' => true]
    ]
]);
$sql = $builder->toSql();
echo "\nGenerated SQL:\n$sql\n\n";
assertContains('INNER JOIN [tblDeelname]', $sql, "tblDeelname should be joined");
assertContains('INNER JOIN [tblDeelnemers]', $sql, "tblDeelnemers should be joined");
assertContains('INNER JOIN [tblOpleidingen]', $sql, "tblOpleidingen should be joined");
echo "\n";

// Test 13: Verify ON clause tables are validated
echo "Test 13: Validate ON clause references only joined tables\n";
// Build the SQL and check if the builder detects invalid references
$builder = new QueryBuilder([
    'tables' => [
        [
            'name' => 'tblA',
            'joins' => [
                [
                    'table' => 'tblB',
                    'type' => 'INNER',
                    // This ON references tblC which is not joined!
                    'on' => '[tblC].[id] = [tblB].[c_id]'
                ]
            ]
        ]
    ],
    'fields' => [
        ['table' => 'tblA', 'field' => 'id', 'visible' => true]
    ]
]);
$sql = $builder->toSql();
$errors = $builder->getErrors();
echo "SQL: $sql\n";
echo "Errors: " . (empty($errors) ? "(none)" : implode(', ', $errors)) . "\n";

// The builder should detect that tblC is referenced but not in FROM
assertTrue(!empty($errors), "Should detect missing tblC");
$errorText = implode(' ', $errors);
assertContains('tblC', $errorText, "Error should mention tblC");
echo "\n";

// ============================================================================
// RESULTS
// ============================================================================

echo "\n=== Results ===\n";
echo "Passed: $testsPassed\n";
echo "Failed: $testsFailed\n";

if (!empty($failures)) {
    echo "\nFailures:\n";
    foreach ($failures as $i => $failure) {
        echo "\n" . ($i + 1) . ". {$failure['message']}\n";
        echo "   Expected: " . var_export($failure['expected'], true) . "\n";
        echo "   Actual:   " . var_export($failure['actual'], true) . "\n";
    }
}

echo "\n";

// Exit with error code if any tests failed (only when run directly, not included)
if (php_sapi_name() === 'cli' || !defined('CMA_PHPUNIT_RUNNER')) {
    exit($testsFailed > 0 ? 1 : 0);
}
