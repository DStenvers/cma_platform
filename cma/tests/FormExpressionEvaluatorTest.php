<?php
/**
 * Tests for Cma\FormExpressionEvaluator.
 *
 * Run with: php tests/TestRunner.php FormExpressionEvaluatorTest
 *
 * Covers:
 * - Literals (string, number, bool, null)
 * - All operators (==, !=, &&, ||, !) and precedence
 * - Parentheses
 * - Identifier resolution against Application
 * - Empty/whitespace input
 * - Rejected syntax: unknown identifiers, unbalanced parens, stray operators,
 *   no function-call syntax, no assignment.
 *
 * Tests do not boot the full CMA — they load just the autoloader plus the
 * evaluator class. Application state is stubbed via Application::set().
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/classes/FormExpressionEvaluator.php';

use App\Library\Application;
use Cma\FormExpressionEvaluator;

class FormExpressionEvaluatorTest extends TestCase
{
    public function setUp(): void
    {
        Application::set('cma_sso_enabled', 'true');
        Application::set('omgeving', 'P');
        Application::set('feature_x', 'false');
        Application::set('numeric_key', '42');
    }

    // ------------------------------------------------------------------
    // Literals
    // ------------------------------------------------------------------

    public function testBoolLiteralTrue(): void
    {
        $this->assertTrue(FormExpressionEvaluator::isTrue('true'));
    }

    public function testBoolLiteralFalse(): void
    {
        $this->assertFalse(FormExpressionEvaluator::isTrue('false'));
    }

    public function testNullLiteral(): void
    {
        // null is falsy in isTrue(), but evaluate() returns the raw value.
        $this->assertFalse(FormExpressionEvaluator::isTrue('null'));
        $this->assertNull(FormExpressionEvaluator::evaluate('null'));
    }

    public function testStringLiteralEvaluatedRaw(): void
    {
        $this->assertEquals('hello', FormExpressionEvaluator::evaluate("'hello'"));
    }

    public function testNumberLiteral(): void
    {
        $this->assertEquals(42, FormExpressionEvaluator::evaluate('42'));
        $this->assertEquals(3.14, FormExpressionEvaluator::evaluate('3.14'));
    }

    // ------------------------------------------------------------------
    // Empty input
    // ------------------------------------------------------------------

    public function testEmptyExpressionIsFalse(): void
    {
        $this->assertFalse(FormExpressionEvaluator::isTrue(''));
        $this->assertFalse(FormExpressionEvaluator::isTrue('   '));
    }

    // ------------------------------------------------------------------
    // app.<key> identifier resolution
    // ------------------------------------------------------------------

    public function testAppIdentifierResolvesValue(): void
    {
        $this->assertEquals('true', FormExpressionEvaluator::evaluate('app.cma_sso_enabled'));
    }

    public function testAppIdentifierEqualsString(): void
    {
        $this->assertTrue(FormExpressionEvaluator::isTrue("app.cma_sso_enabled == 'true'"));
        $this->assertFalse(FormExpressionEvaluator::isTrue("app.cma_sso_enabled == 'false'"));
    }

    public function testAppIdentifierNotEquals(): void
    {
        $this->assertTrue(FormExpressionEvaluator::isTrue("app.omgeving != 'O'"));
        $this->assertFalse(FormExpressionEvaluator::isTrue("app.omgeving != 'P'"));
    }

    public function testUnknownAppKeyReturnsNull(): void
    {
        $this->assertNull(FormExpressionEvaluator::evaluate('app.nonexistent_key'));
        $this->assertTrue(FormExpressionEvaluator::isTrue('app.nonexistent_key == null'));
    }

    // ------------------------------------------------------------------
    // Logical operators
    // ------------------------------------------------------------------

    public function testAnd(): void
    {
        $this->assertTrue(FormExpressionEvaluator::isTrue('true && true'));
        $this->assertFalse(FormExpressionEvaluator::isTrue('true && false'));
    }

    public function testOr(): void
    {
        $this->assertTrue(FormExpressionEvaluator::isTrue('false || true'));
        $this->assertFalse(FormExpressionEvaluator::isTrue('false || false'));
    }

    public function testNot(): void
    {
        $this->assertTrue(FormExpressionEvaluator::isTrue('!false'));
        $this->assertFalse(FormExpressionEvaluator::isTrue('!true'));
    }

    public function testDoubleNegation(): void
    {
        $this->assertTrue(FormExpressionEvaluator::isTrue('!!true'));
    }

    public function testPrecedenceAndBeforeOr(): void
    {
        // false || (true && false) == false  vs  (false || true) && false == false
        // Both happen to be false here; pick a clearer case:
        // true || false && false  ==  true || (false && false)  ==  true
        $this->assertTrue(FormExpressionEvaluator::isTrue('true || false && false'));
    }

    public function testPrecedenceNotBeforeAnd(): void
    {
        // !false && true  ==  (!false) && true  ==  true
        $this->assertTrue(FormExpressionEvaluator::isTrue('!false && true'));
    }

    public function testParenthesesOverridePrecedence(): void
    {
        // (true || false) && false  ==  false
        $this->assertFalse(FormExpressionEvaluator::isTrue('(true || false) && false'));
    }

    // ------------------------------------------------------------------
    // Compound real-world expressions
    // ------------------------------------------------------------------

    public function testSsoAndProduction(): void
    {
        $this->assertTrue(FormExpressionEvaluator::isTrue(
            "app.cma_sso_enabled == 'true' && app.omgeving == 'P'"
        ));
    }

    public function testSsoEnabledOrFeatureOff(): void
    {
        $this->assertTrue(FormExpressionEvaluator::isTrue(
            "app.cma_sso_enabled == 'true' || app.feature_x == 'true'"
        ));
    }

    public function testNegatedCompoundExpression(): void
    {
        $this->assertFalse(FormExpressionEvaluator::isTrue(
            "!(app.cma_sso_enabled == 'true')"
        ));
    }

    // ------------------------------------------------------------------
    // Error cases — these are config bugs and must throw.
    // ------------------------------------------------------------------

    private function assertThrows(string $expression, string $label): void
    {
        $thrown = false;
        try {
            FormExpressionEvaluator::isTrue($expression);
        } catch (\InvalidArgumentException $e) {
            $thrown = true;
        }
        $this->assertTrue($thrown, "Expected InvalidArgumentException for: $label");
    }

    public function testUnknownNamespaceThrows(): void
    {
        $this->assertThrows("user.role == 'admin'", 'unknown namespace');
    }

    public function testEmptyAppKeyThrows(): void
    {
        $this->assertThrows("app. == 'x'", 'empty app key');
    }

    public function testUnbalancedParenthesisThrows(): void
    {
        $this->assertThrows('(true && false', 'unbalanced parens');
    }

    public function testUnterminatedStringThrows(): void
    {
        $this->assertThrows("app.x == 'hello", 'unterminated string');
    }

    public function testTrailingTokenThrows(): void
    {
        $this->assertThrows('true true', 'trailing token');
    }

    public function testUnexpectedCharacterThrows(): void
    {
        // `+` is not in the grammar — arithmetic is intentionally not supported.
        $this->assertThrows('1 + 2', 'unsupported operator');
    }

    public function testFunctionCallSyntaxThrows(): void
    {
        // Function-call syntax must NOT be supported — that would open an
        // RCE-shaped door. `system` parses as an identifier, the `(` then
        // becomes an unexpected primary token.
        $this->assertThrows("system('ls')", 'function-call syntax');
    }

    public function testAssignmentSyntaxThrows(): void
    {
        $this->assertThrows("app.cma_sso_enabled = 'true'", 'assignment syntax');
    }
}
