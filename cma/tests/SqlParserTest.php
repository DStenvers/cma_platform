<?php
/**
 * SQL Parser Unit Tests
 *
 * Tests for the Cma\SqlParser class
 * Run with: php tests/SqlParserTest.php
 */

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

function assertNotNull($value, string $message = ''): void
{
    global $testsPassed, $testsFailed, $failures;

    if ($value !== null) {
        $testsPassed++;
        echo ".";
    } else {
        $testsFailed++;
        $failures[] = [
            'message' => $message,
            'expected' => 'not null',
            'actual' => 'null'
        ];
        echo "F";
    }
}

function assertNull($value, string $message = ''): void
{
    global $testsPassed, $testsFailed, $failures;

    if ($value === null) {
        $testsPassed++;
        echo ".";
    } else {
        $testsFailed++;
        $failures[] = [
            'message' => $message,
            'expected' => 'null',
            'actual' => var_export($value, true)
        ];
        echo "F";
    }
}

function assertCount(int $expected, $array, string $message = ''): void
{
    if (!is_array($array)) {
        global $testsFailed, $failures;
        $testsFailed++;
        $failures[] = [
            'message' => $message . " (not an array)",
            'expected' => "array with $expected items",
            'actual' => var_export($array, true)
        ];
        echo "F";
        return;
    }
    assertEqual($expected, count($array), $message . " (count mismatch)");
}

function assertContains($needle, array $haystack, string $message = ''): void
{
    global $testsPassed, $testsFailed, $failures;

    if (in_array($needle, $haystack)) {
        $testsPassed++;
        echo ".";
    } else {
        $testsFailed++;
        $failures[] = [
            'message' => $message,
            'expected' => "array to contain: " . var_export($needle, true),
            'actual' => var_export($haystack, true)
        ];
        echo "F";
    }
}

function assertStringContains(string $needle, string $haystack, string $message = ''): void
{
    global $testsPassed, $testsFailed, $failures;

    if (strpos($haystack, $needle) !== false) {
        $testsPassed++;
        echo ".";
    } else {
        $testsFailed++;
        $failures[] = [
            'message' => $message,
            'expected' => "string to contain: $needle",
            'actual' => $haystack
        ];
        echo "F";
    }
}

// ============================================================================
// Load the shared SqlParser class
// ============================================================================

require_once __DIR__ . '/../classes/SqlParser.php';

/**
 * Wrapper for backward compatibility with existing tests
 */
function parseSqlQuery(string $sql): ?array
{
    return \Cma\SqlParser::parse($sql);
}

// ============================================================================
// TEST CASES
// ============================================================================

echo "\n=== SQL Parser Tests ===\n\n";

// Test 1: Simple SELECT
echo "Test: Simple SELECT\n";
$sql = "SELECT id, name FROM users";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse simple SELECT");
assertCount(1, $result['tables'], "Should find 1 table");
assertEqual('users', $result['tables'][0], "Table should be 'users'");
assertCount(2, $result['fields'], "Should find 2 fields");
echo "\n";

// Test 2: SELECT with brackets
echo "Test: SELECT with brackets\n";
$sql = "SELECT [id], [name] FROM [tblUsers]";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse bracketed SELECT");
assertEqual('tblUsers', $result['tables'][0], "Table should be 'tblUsers'");
assertCount(2, $result['fields'], "Should find 2 fields");
assertEqual('id', $result['fields'][0]['field'], "First field should be 'id'");
echo "\n";

// Test 3: SELECT with table prefix
echo "Test: SELECT with table prefix\n";
$sql = "SELECT [tblUsers].[id], [tblUsers].[name] FROM [tblUsers]";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse prefixed SELECT");
assertEqual('tblUsers', $result['fields'][0]['table'], "Field table should be 'tblUsers'");
assertEqual('id', $result['fields'][0]['field'], "Field name should be 'id'");
echo "\n";

// Test 4: SELECT with alias
echo "Test: SELECT with alias\n";
$sql = "SELECT [id] AS [gebruikerId], [name] AS [gebruikerNaam] FROM [tblUsers]";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse aliased SELECT");
assertEqual('gebruikerId', $result['fields'][0]['alias'], "First alias should be 'gebruikerId'");
assertEqual('gebruikerNaam', $result['fields'][1]['alias'], "Second alias should be 'gebruikerNaam'");
echo "\n";

// Test 5: SELECT with JOIN
echo "Test: SELECT with JOIN\n";
$sql = "SELECT u.id, u.name, r.rolename FROM users u INNER JOIN roles r ON u.role_id = r.id";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse JOIN query");
assertCount(2, $result['tables'], "Should find 2 tables");
assertContains('users', $result['tables'], "Should contain 'users'");
assertContains('roles', $result['tables'], "Should contain 'roles'");
echo "\n";

// Test 6: MS Access nested JOINs
echo "Test: MS Access nested JOINs\n";
$sql = "SELECT [tblA].[id], [tblB].[name], [tblC].[value] FROM (([tblA] INNER JOIN [tblB] ON [tblA].[b_id] = [tblB].[id]) INNER JOIN [tblC] ON [tblB].[c_id] = [tblC].[id])";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse MS Access nested JOINs");
assertContains('tblA', $result['tables'], "Should contain 'tblA'");
assertContains('tblB', $result['tables'], "Should contain 'tblB'");
assertContains('tblC', $result['tables'], "Should contain 'tblC'");
assertTrue(count($result['tables']) >= 3, "Should find at least 3 tables");
echo "\n";

// Test 7: SELECT with ORDER BY
echo "Test: SELECT with ORDER BY\n";
$sql = "SELECT id, name FROM users ORDER BY name ASC, id DESC";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse ORDER BY");
assertCount(2, $result['sorting'], "Should find 2 sort items");
assertEqual('name', $result['sorting'][0]['field'], "First sort field should be 'name'");
assertEqual('asc', $result['sorting'][0]['direction'], "First sort direction should be 'asc'");
assertEqual('id', $result['sorting'][1]['field'], "Second sort field should be 'id'");
assertEqual('desc', $result['sorting'][1]['direction'], "Second sort direction should be 'desc'");
echo "\n";

// Test 8: SELECT *
echo "Test: SELECT *\n";
$sql = "SELECT * FROM users";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse SELECT *");
assertCount(0, $result['fields'], "Should return empty fields for SELECT *");
echo "\n";

// Test 9: SELECT TOP N
echo "Test: SELECT TOP N\n";
$sql = "SELECT TOP 10 id, name FROM users";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse SELECT TOP");
assertCount(2, $result['fields'], "Should find 2 fields despite TOP");
assertEqual('id', $result['fields'][0]['field'], "First field should be 'id'");
echo "\n";

// Test 10: SELECT DISTINCT
echo "Test: SELECT DISTINCT\n";
$sql = "SELECT DISTINCT id, name FROM users";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse SELECT DISTINCT");
assertCount(2, $result['fields'], "Should find 2 fields despite DISTINCT");
echo "\n";

// Test 11: UNION should return null
echo "Test: UNION returns null\n";
$sql = "SELECT id FROM users UNION SELECT id FROM admins";
$result = parseSqlQuery($sql);
assertNull($result, "Should return null for UNION");
echo "\n";

// Test 12: WITH CTE should return null
echo "Test: WITH CTE returns null\n";
$sql = "WITH cte AS (SELECT id FROM users) SELECT * FROM cte";
$result = parseSqlQuery($sql);
assertNull($result, "Should return null for WITH CTE");
echo "\n";

// Test 13: Function in field
echo "Test: Function in field with alias\n";
$sql = "SELECT COUNT(*) AS total, MAX([created]) AS laatste FROM [tblUsers]";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse function fields");
assertTrue(count($result['fields']) >= 1, "Should find at least 1 field");
echo "\n";

// Test 14: LEFT JOIN
echo "Test: LEFT JOIN\n";
$sql = "SELECT u.id, r.name FROM [users] u LEFT JOIN [roles] r ON u.role_id = r.id";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse LEFT JOIN");
assertCount(2, $result['tables'], "Should find 2 tables");
assertContains('users', $result['tables'], "Should contain 'users'");
assertContains('roles', $result['tables'], "Should contain 'roles'");
echo "\n";

// Test 15: Multiple JOINs
echo "Test: Multiple JOINs\n";
$sql = "SELECT u.id, r.name, d.dept FROM users u JOIN roles r ON u.role_id = r.id JOIN departments d ON u.dept_id = d.id";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse multiple JOINs");
assertCount(3, $result['tables'], "Should find 3 tables");
echo "\n";

// Test 16: ORDER BY with brackets
echo "Test: ORDER BY with brackets\n";
$sql = "SELECT [id], [name] FROM [users] ORDER BY [name] DESC";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse ORDER BY with brackets");
assertEqual('name', $result['sorting'][0]['field'], "Sort field should be 'name'");
assertEqual('desc', $result['sorting'][0]['direction'], "Sort direction should be 'desc'");
echo "\n";

// Test 17: ORDER BY with table prefix
echo "Test: ORDER BY with table prefix\n";
$sql = "SELECT [id], [name] FROM [users] ORDER BY [users].[name] ASC";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse ORDER BY with table prefix");
assertEqual('users', $result['sorting'][0]['table'], "Sort table should be 'users'");
assertEqual('name', $result['sorting'][0]['field'], "Sort field should be 'name'");
echo "\n";

// Test 18: Extract tables from complex nested query
echo "Test: Complex nested MS Access query\n";
$sql = "SELECT [tblDeelnemers].[id], [tblOpleidingen].[naam] FROM ((([tblDeelnemers] INNER JOIN [tblOpleidingen] ON [tblDeelnemers].[opl_id] = [tblOpleidingen].[id]) LEFT JOIN [tblDocenten] ON [tblDeelnemers].[doc_id] = [tblDocenten].[id]) INNER JOIN [tblStatus] ON [tblDeelnemers].[status_id] = [tblStatus].[id])";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse complex nested query");
assertContains('tblDeelnemers', $result['tables'], "Should contain 'tblDeelnemers'");
assertContains('tblOpleidingen', $result['tables'], "Should contain 'tblOpleidingen'");
assertContains('tblDocenten', $result['tables'], "Should contain 'tblDocenten'");
assertContains('tblStatus', $result['tables'], "Should contain 'tblStatus'");
assertTrue(count($result['tables']) >= 4, "Should find at least 4 tables");
echo "\n";

// Test 19: SQL with comments
echo "Test: SQL with comments removed\n";
$sql = "SELECT id, name -- this is a comment\n FROM users /* block comment */";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse SQL with comments");
assertCount(2, $result['fields'], "Should find 2 fields");
echo "\n";

// Test 20: No ORDER BY returns empty array
echo "Test: No ORDER BY returns empty array\n";
$sql = "SELECT id, name FROM users";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse query without ORDER BY");
assertCount(0, $result['sorting'], "Should return empty sorting array");
echo "\n";

// Test 21: Simple field without table prefix
echo "Test: Field without table prefix uses first table\n";
$sql = "SELECT id, name FROM users";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse simple fields");
assertEqual('users', $result['fields'][0]['table'], "Field table should default to first table");
echo "\n";

// Test 22: WHERE clause doesn't affect parsing
echo "Test: WHERE clause doesn't affect parsing\n";
$sql = "SELECT id, name FROM users WHERE status = 1 AND active = true";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse query with WHERE");
assertCount(2, $result['fields'], "Should find 2 fields");
assertCount(1, $result['tables'], "Should find 1 table");
echo "\n";

// Test 23: GROUP BY doesn't affect table extraction
echo "Test: GROUP BY doesn't affect table extraction\n";
$sql = "SELECT department, COUNT(*) AS cnt FROM users GROUP BY department";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse query with GROUP BY");
assertEqual('users', $result['tables'][0], "Table should be 'users'");
echo "\n";

// Test 24: INSERT should fail (non-SELECT)
echo "Test: INSERT not parseable\n";
$sql = "INSERT INTO users (name) VALUES ('test')";
$result = parseSqlQuery($sql);
assertNull($result, "Should return null for INSERT");
echo "\n";

// Test 25: Empty SQL returns null
echo "Test: Empty SQL returns null\n";
$sql = "   ";
$result = parseSqlQuery($sql);
assertNull($result, "Should return null for empty SQL");
echo "\n";

// Test 26: CROSS JOIN
echo "Test: CROSS JOIN\n";
$sql = "SELECT a.id, b.name FROM tableA a CROSS JOIN tableB b";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse CROSS JOIN");
assertContains('tableA', $result['tables'], "Should contain 'tableA'");
assertContains('tableB', $result['tables'], "Should contain 'tableB'");
echo "\n";

// Test 27: RIGHT JOIN
echo "Test: RIGHT JOIN\n";
$sql = "SELECT u.id, r.name FROM users u RIGHT JOIN roles r ON u.role_id = r.id";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse RIGHT JOIN");
assertCount(2, $result['tables'], "Should find 2 tables");
echo "\n";

// Test 28: OUTER JOIN
echo "Test: OUTER JOIN\n";
$sql = "SELECT u.id, r.name FROM users u FULL OUTER JOIN roles r ON u.role_id = r.id";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse OUTER JOIN");
assertTrue(count($result['tables']) >= 1, "Should find at least 1 table");
echo "\n";

// Test 29: Multiple ORDER BY with mixed directions
echo "Test: Multiple ORDER BY with mixed directions\n";
$sql = "SELECT id, name, created FROM users ORDER BY created DESC, name ASC, id";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse multiple ORDER BY");
assertCount(3, $result['sorting'], "Should find 3 sort items");
assertEqual('desc', $result['sorting'][0]['direction'], "First sort should be DESC");
assertEqual('asc', $result['sorting'][1]['direction'], "Second sort should be ASC");
assertEqual('asc', $result['sorting'][2]['direction'], "Third sort should default to ASC");
echo "\n";

// Test 30: SELECT with WHERE containing subquery-like patterns
echo "Test: SELECT with complex WHERE\n";
$sql = "SELECT id, name FROM users WHERE status IN (1,2,3) AND created > '2024-01-01'";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse complex WHERE");
assertCount(2, $result['fields'], "Should find 2 fields");
assertEqual('users', $result['tables'][0], "Table should be 'users'");
echo "\n";

// Test 31: Field alias without AS keyword
echo "Test: Field alias without AS keyword (function)\n";
$sql = "SELECT COUNT(*) total FROM users";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse field alias without AS");
assertTrue(count($result['fields']) >= 0, "Should parse some fields");
echo "\n";

// Test 32: Multiple fields with expressions
echo "Test: Multiple fields with expressions\n";
$sql = "SELECT id, UPPER(name) AS upper_name, created FROM users";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse multiple fields with expressions");
assertTrue(count($result['fields']) >= 2, "Should find at least 2 fields");
echo "\n";

// Test 33: Verify table extraction doesn't include field names from dot notation
echo "Test: Table extraction from simple query\n";
$sql = "SELECT [users].[id], [users].[name] FROM [users]";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse simple bracketed query");
assertCount(1, $result['tables'], "Should find exactly 1 table");
assertEqual('users', $result['tables'][0], "Table should be 'users'");
echo "\n";

// Test 34: HAVING clause doesn't affect parsing
echo "Test: HAVING clause doesn't affect parsing\n";
$sql = "SELECT department, COUNT(*) AS cnt FROM users GROUP BY department HAVING COUNT(*) > 5";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse query with HAVING");
assertEqual('users', $result['tables'][0], "Table should be 'users'");
echo "\n";

// Test 35: Newlines in original SQL are handled
echo "Test: Newlines in SQL are normalized\n";
$sql = "SELECT\n    id,\n    name\nFROM\n    users\nORDER BY\n    name";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse SQL with newlines");
assertCount(2, $result['fields'], "Should find 2 fields");
assertCount(1, $result['sorting'], "Should find 1 sort");
echo "\n";

// Test 36: Tab characters in SQL
echo "Test: Tab characters in SQL\n";
$sql = "SELECT\tid,\tname\tFROM\tusers";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse SQL with tabs");
assertCount(2, $result['fields'], "Should find 2 fields");
echo "\n";

// Test 37: Lowercase keywords
echo "Test: Lowercase keywords\n";
$sql = "select id, name from users order by name desc";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse lowercase SQL");
assertCount(2, $result['fields'], "Should find 2 fields");
assertEqual('desc', $result['sorting'][0]['direction'], "Should parse lowercase DESC");
echo "\n";

// Test 38: Mixed case keywords
echo "Test: Mixed case keywords\n";
$sql = "Select Id, Name From Users Order By Name Desc";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse mixed case SQL");
assertCount(2, $result['fields'], "Should find 2 fields");
echo "\n";

// Test 39: DELETE should fail
echo "Test: DELETE not parseable\n";
$sql = "DELETE FROM users WHERE id = 1";
$result = parseSqlQuery($sql);
assertNull($result, "Should return null for DELETE");
echo "\n";

// Test 40: UPDATE should fail
echo "Test: UPDATE not parseable\n";
$sql = "UPDATE users SET name = 'test' WHERE id = 1";
$result = parseSqlQuery($sql);
assertNull($result, "Should return null for UPDATE");
echo "\n";

// ============================================================================
// BUG REPRODUCTION TESTS
// ============================================================================

// Test 41: CRITICAL - Complex MS Access nested JOINs with 4 tables
echo "Test: Complex MS Access nested JOINs (4 tables) - tblDocenten must be found\n";
$sql = "SELECT [tblDocenten].[VolledigeNaam], [tblDocenten].[MV], [tblDocenten].[emailTonen], [tblDeelname].[fkHoofdDocent], [tblDeelnemers].[VolledigeNaam], [tblDeelnemers].[Achternaam], [tblDeelnemers].[emailTonen], [tblOpleidingen].[Titel], [tblOpleidingen].[Code]
FROM ((([tblDocenten]
INNER JOIN [tblDeelname] ON [tblDeelname].[fkHoofdDocent] = [tblDocenten].[ID])
INNER JOIN [tblDeelnemers] ON [tblDeelname].[fkDeelnemer] = [tblDeelnemers].[ID])
INNER JOIN [tblOpleidingen] ON [tblDeelname].[fkOpleiding] = [tblOpleidingen].[ID])
WHERE ([tblDocenten].[VolledigeNaam] <> 'Diederik Stenvers'
AND ([tblDocenten].[MV] IS NOT NULL AND [tblDocenten].[MV] <> ''))
ORDER BY [tblOpleidingen].[Code] ASC, [tblDeelnemers].[Achternaam] ASC";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse complex nested query");
echo "\nTables found: " . implode(', ', $result['tables']) . "\n";
assertContains('tblDocenten', $result['tables'], "CRITICAL: Should contain 'tblDocenten'");
assertContains('tblDeelname', $result['tables'], "Should contain 'tblDeelname'");
assertContains('tblDeelnemers', $result['tables'], "Should contain 'tblDeelnemers'");
assertContains('tblOpleidingen', $result['tables'], "Should contain 'tblOpleidingen'");
assertTrue(count($result['tables']) >= 4, "Should find at least 4 tables");
echo "\n";

// Test 42: Same query but with lowercase DESC
echo "Test: Same query with lowercase DESC - must still find tblDocenten\n";
$sql = "SELECT [tblDocenten].[VolledigeNaam], [tblDocenten].[MV], [tblDocenten].[emailTonen], [tblDeelname].[fkHoofdDocent], [tblDeelnemers].[VolledigeNaam], [tblDeelnemers].[Achternaam], [tblDeelnemers].[emailTonen], [tblOpleidingen].[Titel], [tblOpleidingen].[Code]
FROM ((([tblDocenten]
INNER JOIN [tblDeelname] ON [tblDeelname].[fkHoofdDocent] = [tblDocenten].[ID])
INNER JOIN [tblDeelnemers] ON [tblDeelname].[fkDeelnemer] = [tblDeelnemers].[ID])
INNER JOIN [tblOpleidingen] ON [tblDeelname].[fkOpleiding] = [tblOpleidingen].[ID])
WHERE ([tblDocenten].[VolledigeNaam] <> 'Diederik Stenvers'
AND ([tblDocenten].[MV] IS NOT NULL AND [tblDocenten].[MV] <> ''))
ORDER BY [tblOpleidingen].[Code] ASC, [tblDeelnemers].[Achternaam] desc";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse complex nested query with desc");
echo "\nTables found: " . implode(', ', $result['tables']) . "\n";
assertContains('tblDocenten', $result['tables'], "CRITICAL: Should contain 'tblDocenten' after DESC edit");
assertContains('tblDeelname', $result['tables'], "Should contain 'tblDeelname' after DESC edit");
assertContains('tblDeelnemers', $result['tables'], "Should contain 'tblDeelnemers' after DESC edit");
assertContains('tblOpleidingen', $result['tables'], "Should contain 'tblOpleidingen' after DESC edit");
assertTrue(count($result['tables']) >= 4, "Should find at least 4 tables after DESC edit");
echo "\n";

// Test 43: CRITICAL - Verify table ORDER is correct for join computation
echo "Test: Table order - tblDocenten must be FIRST\n";
$sql = "SELECT [tblDocenten].[VolledigeNaam], [tblDeelname].[fkHoofdDocent]
FROM ((([tblDocenten]
INNER JOIN [tblDeelname] ON [tblDeelname].[fkHoofdDocent] = [tblDocenten].[ID])
INNER JOIN [tblDeelnemers] ON [tblDeelname].[fkDeelnemer] = [tblDeelnemers].[ID])
INNER JOIN [tblOpleidingen] ON [tblDeelname].[fkOpleiding] = [tblOpleidingen].[ID])";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse nested JOINs");
echo "\nTable order: " . implode(', ', $result['tables']) . "\n";
assertEqual('tblDocenten', $result['tables'][0], "CRITICAL: First table must be 'tblDocenten' (the FROM table)");
assertEqual(4, count($result['tables']), "Should have exactly 4 tables");
echo "\n";

// Test 44: CRITICAL - Extract JOIN conditions from SQL
echo "Test: Extract JOIN conditions from MS Access nested JOINs\n";
$sql = "SELECT [tblDocenten].[VolledigeNaam]
FROM ((([tblDocenten]
INNER JOIN [tblDeelname] ON [tblDeelname].[fkHoofdDocent] = [tblDocenten].[ID])
INNER JOIN [tblDeelnemers] ON [tblDeelname].[fkDeelnemer] = [tblDeelnemers].[ID])
INNER JOIN [tblOpleidingen] ON [tblDeelname].[fkOpleiding] = [tblOpleidingen].[ID])";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse nested JOINs");
echo "\nJoins found: " . count($result['joins']) . "\n";
foreach ($result['joins'] as $i => $join) {
    echo "  $i: {$join['type']} JOIN {$join['table']} ON {$join['on']}\n";
}
assertEqual(3, count($result['joins']), "Should find 3 JOINs");
assertEqual('tblDeelname', $result['joins'][0]['table'], "First join should be tblDeelname");
assertEqual('INNER', $result['joins'][0]['type'], "First join type should be INNER");
assertStringContains('[tblDeelname].[fkHoofdDocent]', $result['joins'][0]['on'], "First join ON should reference fkHoofdDocent");
echo "\n";

// Test 45: LEFT JOIN extraction
echo "Test: Extract LEFT JOIN conditions\n";
$sql = "SELECT u.id FROM users u LEFT JOIN roles r ON u.role_id = r.id";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse LEFT JOIN");
assertEqual(1, count($result['joins']), "Should find 1 JOIN");
assertEqual('LEFT', $result['joins'][0]['type'], "Join type should be LEFT");
assertEqual('roles', $result['joins'][0]['table'], "Join table should be roles");
assertStringContains('role_id', $result['joins'][0]['on'], "ON should reference role_id");
echo "\n";

// ============================================================================
// NEW TESTS - Parser improvements
// ============================================================================

// Test 46: Deep nesting (5 tables, depth 4) - no longer rejected
echo "Test: Deep nesting (5 tables, depth 4)\n";
$sql = "SELECT [tblA].[id]
FROM (((([tblA]
INNER JOIN [tblB] ON [tblA].[b_id] = [tblB].[id])
INNER JOIN [tblC] ON [tblB].[c_id] = [tblC].[id])
INNER JOIN [tblD] ON [tblC].[d_id] = [tblD].[id])
INNER JOIN [tblE] ON [tblD].[e_id] = [tblE].[id])";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse deeply nested query (depth limit removed)");
assertContains('tblA', $result['tables'], "Should contain tblA");
assertContains('tblB', $result['tables'], "Should contain tblB");
assertContains('tblC', $result['tables'], "Should contain tblC");
assertContains('tblD', $result['tables'], "Should contain tblD");
assertContains('tblE', $result['tables'], "Should contain tblE");
assertTrue(count($result['tables']) >= 5, "Should find at least 5 tables");
echo "\n";

// Test 47: Compound ON with AND - no longer rejected
echo "Test: Compound ON with AND\n";
$sql = "SELECT u.id FROM users u INNER JOIN roles r ON u.role_id = r.id AND u.active = 1";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse compound ON with AND");
assertContains('users', $result['tables'], "Should contain users");
assertContains('roles', $result['tables'], "Should contain roles");
assertEqual(1, count($result['joins']), "Should find 1 JOIN");
assertStringContains('u.active = 1', $result['joins'][0]['on'], "ON condition should include AND part");
echo "\n";

// Test 48: Compound ON with multiple AND
echo "Test: Compound ON with multiple AND\n";
$sql = "SELECT [a].[id] FROM [tblA] a INNER JOIN [tblB] b ON a.b_id = b.id AND a.type = b.type AND a.status = 1";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse compound ON with multiple AND");
assertEqual(1, count($result['joins']), "Should find 1 JOIN");
assertStringContains('a.type = b.type', $result['joins'][0]['on'], "ON should include second AND condition");
echo "\n";

// Test 49: LEFT OUTER JOIN type preservation
echo "Test: LEFT OUTER JOIN type preservation\n";
$sql = "SELECT u.id FROM users u LEFT OUTER JOIN roles r ON u.role_id = r.id";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse LEFT OUTER JOIN");
assertEqual(1, count($result['joins']), "Should find 1 JOIN");
assertEqual('LEFT OUTER', $result['joins'][0]['type'], "Join type should be 'LEFT OUTER'");
echo "\n";

// Test 50: RIGHT OUTER JOIN type preservation
echo "Test: RIGHT OUTER JOIN type preservation\n";
$sql = "SELECT u.id FROM users u RIGHT OUTER JOIN roles r ON u.role_id = r.id";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse RIGHT OUTER JOIN");
assertEqual(1, count($result['joins']), "Should find 1 JOIN");
assertEqual('RIGHT OUTER', $result['joins'][0]['type'], "Join type should be 'RIGHT OUTER'");
echo "\n";

// Test 51: FULL OUTER JOIN type preservation
echo "Test: FULL OUTER JOIN type preservation\n";
$sql = "SELECT u.id FROM users u FULL OUTER JOIN roles r ON u.role_id = r.id";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse FULL OUTER JOIN");
assertEqual(1, count($result['joins']), "Should find 1 JOIN");
assertEqual('FULL OUTER', $result['joins'][0]['type'], "Join type should be 'FULL OUTER'");
echo "\n";

// Test 52: FULL JOIN (without OUTER keyword)
echo "Test: FULL JOIN (without OUTER keyword)\n";
$sql = "SELECT u.id FROM users u FULL JOIN roles r ON u.role_id = r.id";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse FULL JOIN");
assertEqual(1, count($result['joins']), "Should find 1 JOIN");
assertEqual('FULL OUTER', $result['joins'][0]['type'], "FULL JOIN should normalize to 'FULL OUTER'");
echo "\n";

// Test 53: LIMIT N parsing
echo "Test: LIMIT N parsing\n";
$sql = "SELECT id, name FROM users ORDER BY id LIMIT 50";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse LIMIT");
assertEqual(50, $result['limit'], "Limit should be 50");
assertNull($result['offset'], "Offset should be null");
echo "\n";

// Test 54: LIMIT N OFFSET M parsing
echo "Test: LIMIT N OFFSET M parsing\n";
$sql = "SELECT id, name FROM users ORDER BY id LIMIT 25 OFFSET 100";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse LIMIT OFFSET");
assertEqual(25, $result['limit'], "Limit should be 25");
assertEqual(100, $result['offset'], "Offset should be 100");
echo "\n";

// Test 55: TOP N in result
echo "Test: TOP N in result\n";
$sql = "SELECT TOP 20 id, name FROM users";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse TOP N");
assertEqual(20, $result['topN'], "topN should be 20");
echo "\n";

// Test 56: IIf() in SELECT parsed as expression - no longer rejected
echo "Test: IIf() in SELECT parsed as expression\n";
$sql = "SELECT [name], IIf([active]=1,'Ja','Nee') AS status FROM [users]";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse IIf() in SELECT (no longer rejected)");
assertTrue(count($result['fields']) >= 2, "Should find at least 2 fields");
// Find the IIf field
$iifField = null;
foreach ($result['fields'] as $f) {
    if (isset($f['expression']) && $f['expression'] === true) {
        $iifField = $f;
        break;
    }
}
assertNotNull($iifField, "Should have an expression field for IIf");
assertEqual('status', $iifField['alias'], "IIf alias should be 'status'");
echo "\n";

// Test 57: Switch() in SELECT parsed as expression - no longer rejected
echo "Test: Switch() in SELECT parsed as expression\n";
$sql = "SELECT [id], Switch([type]=1,'Admin',[type]=2,'User') AS roleName FROM [users]";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse Switch() in SELECT (no longer rejected)");
assertTrue(count($result['fields']) >= 2, "Should find at least 2 fields");
$switchField = null;
foreach ($result['fields'] as $f) {
    if (isset($f['expression']) && $f['expression'] === true) {
        $switchField = $f;
        break;
    }
}
assertNotNull($switchField, "Should have an expression field for Switch");
assertEqual('roleName', $switchField['alias'], "Switch alias should be 'roleName'");
echo "\n";

// Test 58: DISTINCT flag extraction
echo "Test: DISTINCT flag extraction\n";
$sql = "SELECT DISTINCT id, name FROM users";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse DISTINCT");
assertEqual(true, $result['distinct'], "distinct should be true");
echo "\n";

// Test 59: DISTINCTROW flag extraction
echo "Test: DISTINCTROW flag extraction\n";
$sql = "SELECT DISTINCTROW id, name FROM users";
$result = parseSqlQuery($sql);
assertNotNull($result, "Should parse DISTINCTROW");
assertEqual('DISTINCTROW', $result['distinct'], "distinct should be 'DISTINCTROW'");
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
