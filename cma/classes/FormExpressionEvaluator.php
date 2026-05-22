<?php

namespace Cma;

use App\Library\Application;

/**
 * Safe expression evaluator for JSON form definitions.
 *
 * Purpose
 * -------
 * Lets JSON form definitions declare *conditional* behaviour (currently
 * `hiddenWhen`) without ever invoking PHP `eval()`. Form definitions live in
 * `cma/assets/forms/definitions/` and travel with the platform package, so any
 * expression mechanism must be safe-by-construction: even if a future feature
 * lets non-developers edit those JSON files, evaluating them must never
 * execute arbitrary code.
 *
 * The evaluator is a small recursive-descent parser + interpreter. It supports
 * exactly the operators and identifiers listed below — nothing else. There is
 * no function call syntax, no property access beyond the whitelisted
 * namespaces, no variable assignment, no string interpolation.
 *
 * Grammar
 * -------
 * ```
 *   expression  = orExpr
 *   orExpr      = andExpr ( "||" andExpr )*
 *   andExpr     = notExpr ( "&&" notExpr )*
 *   notExpr     = "!" notExpr
 *               | comparison
 *   comparison  = primary ( ( "==" | "!=" ) primary )?
 *   primary     = "(" expression ")"
 *               | literal
 *               | identifier
 *   literal     = string | number | "true" | "false" | "null"
 *   string      = "'" [^']* "'"
 *   number      = [0-9]+ ( "." [0-9]+ )?
 *   identifier  = namespace "." key
 *   namespace   = "app"
 *   key         = [A-Za-z_][A-Za-z0-9_]*
 * ```
 *
 * Operator precedence (lowest to highest): `||`, `&&`, `!`, `==`/`!=`.
 * Parentheses override precedence as usual.
 *
 * Whitelisted namespaces
 * ----------------------
 * - `app.<key>` — resolves to `Application::get('<key>', null)`. Use this to
 *   read any value from `$GLOBALS['Application']` (config, environment flags,
 *   etc.). Unknown keys return `null`, which is unequal to any literal except
 *   `null` itself.
 *
 * To add a new namespace (e.g. `user.<role>`), extend
 * {@see resolveIdentifier()} with a new prefix. Do not add `eval`, `exec`,
 * file I/O, or anything that touches the database — those belong in PHP, not
 * in JSON-declarable expressions.
 *
 * Comparison semantics
 * --------------------
 * `==` and `!=` are **loose** comparisons (PHP `==` / `!=`), so the common
 * pattern `app.cma_sso_enabled == 'true'` matches both the string `"true"` and
 * any truthy non-empty equivalent. If you need strict comparison later, add
 * `===` / `!==` to the grammar; do not change `==` semantics.
 *
 * Examples
 * --------
 * ```
 *   true                                            => true
 *   app.cma_sso_enabled == 'true'                   => true if SSO enabled
 *   app.cma_sso_enabled == 'true' && app.omgeving != 'O'
 *                                                   => SSO on AND not dev
 *   !(app.feature_x == 'true')                      => feature_x is NOT on
 *   app.missing_key == null                         => true (unknown keys -> null)
 * ```
 *
 * Errors
 * ------
 * Any syntactic problem (unknown token, unbalanced parens, unknown namespace)
 * throws {@see \InvalidArgumentException}. Callers should treat a thrown
 * exception as a configuration bug — fail loudly in development, log + treat
 * as `false` in production if you want to be defensive.
 *
 * Performance
 * -----------
 * The evaluator is stateless and allocates a few small arrays per call. Each
 * form-definition load triggers one evaluation per field that declares
 * `hiddenWhen`, so cost is negligible. Results are NOT cached internally; the
 * caller is expected to cache the *resulting* form-definition shape.
 */
class FormExpressionEvaluator
{
    /** @var array<int, array{type: string, value: mixed}> */
    private array $tokens = [];

    /** Current position in the token stream. */
    private int $pos = 0;

    /**
     * Evaluate an expression and return its boolean result.
     *
     * An empty or whitespace-only expression returns `false` (so an absent
     * `hiddenWhen` in JSON behaves as "never hide").
     *
     * @param string $expression Expression in the grammar documented above.
     * @return bool Truthy result of the expression.
     * @throws \InvalidArgumentException On any syntax error.
     */
    public static function isTrue(string $expression): bool
    {
        $expression = trim($expression);
        if ($expression === '') {
            return false;
        }
        return (bool) (new self())->run($expression);
    }

    /**
     * Evaluate an expression and return the raw result (bool, string, number, null).
     *
     * Exposed mainly for tests; production callers should use {@see isTrue()}.
     *
     * @throws \InvalidArgumentException On any syntax error.
     */
    public static function evaluate(string $expression): mixed
    {
        $expression = trim($expression);
        if ($expression === '') {
            return null;
        }
        return (new self())->run($expression);
    }

    private function run(string $expression): mixed
    {
        $this->tokens = $this->tokenize($expression);
        $this->pos = 0;
        $result = $this->parseOr();
        if ($this->pos < count($this->tokens)) {
            throw new \InvalidArgumentException(
                "Unexpected token '" . $this->tokens[$this->pos]['value'] . "' at end of expression"
            );
        }
        return $result;
    }

    // ------------------------------------------------------------------
    // Tokenizer
    // ------------------------------------------------------------------

    /**
     * @return array<int, array{type: string, value: mixed}>
     */
    private function tokenize(string $expr): array
    {
        $tokens = [];
        $len = strlen($expr);
        $i = 0;

        while ($i < $len) {
            $c = $expr[$i];

            if (ctype_space($c)) {
                $i++;
                continue;
            }

            // Two-char operators: ==, !=, &&, ||
            if ($i + 1 < $len) {
                $two = substr($expr, $i, 2);
                if (in_array($two, ['==', '!=', '&&', '||'], true)) {
                    $tokens[] = ['type' => 'op', 'value' => $two];
                    $i += 2;
                    continue;
                }
            }

            // Single-char structural tokens
            if ($c === '(' || $c === ')') {
                $tokens[] = ['type' => $c, 'value' => $c];
                $i++;
                continue;
            }
            if ($c === '!') {
                $tokens[] = ['type' => 'op', 'value' => '!'];
                $i++;
                continue;
            }

            // String literal: single-quoted, no escapes (keep grammar minimal)
            if ($c === "'") {
                $end = strpos($expr, "'", $i + 1);
                if ($end === false) {
                    throw new \InvalidArgumentException("Unterminated string literal starting at position $i");
                }
                $tokens[] = ['type' => 'string', 'value' => substr($expr, $i + 1, $end - $i - 1)];
                $i = $end + 1;
                continue;
            }

            // Number literal
            if (ctype_digit($c)) {
                $j = $i;
                while ($j < $len && (ctype_digit($expr[$j]) || $expr[$j] === '.')) {
                    $j++;
                }
                $raw = substr($expr, $i, $j - $i);
                $tokens[] = [
                    'type' => 'number',
                    'value' => str_contains($raw, '.') ? (float) $raw : (int) $raw,
                ];
                $i = $j;
                continue;
            }

            // Identifier / keyword
            if (ctype_alpha($c) || $c === '_') {
                $j = $i;
                while ($j < $len && (ctype_alnum($expr[$j]) || $expr[$j] === '_' || $expr[$j] === '.')) {
                    $j++;
                }
                $word = substr($expr, $i, $j - $i);
                if ($word === 'true' || $word === 'false') {
                    $tokens[] = ['type' => 'bool', 'value' => $word === 'true'];
                } elseif ($word === 'null') {
                    $tokens[] = ['type' => 'null', 'value' => null];
                } else {
                    $tokens[] = ['type' => 'ident', 'value' => $word];
                }
                $i = $j;
                continue;
            }

            throw new \InvalidArgumentException("Unexpected character '$c' at position $i");
        }

        return $tokens;
    }

    // ------------------------------------------------------------------
    // Parser / interpreter (recursive descent, no AST — evaluates inline)
    // ------------------------------------------------------------------

    private function parseOr(): mixed
    {
        $left = $this->parseAnd();
        while ($this->matchOp('||')) {
            $right = $this->parseAnd();
            $left = (bool) $left || (bool) $right;
        }
        return $left;
    }

    private function parseAnd(): mixed
    {
        $left = $this->parseNot();
        while ($this->matchOp('&&')) {
            $right = $this->parseNot();
            $left = (bool) $left && (bool) $right;
        }
        return $left;
    }

    private function parseNot(): mixed
    {
        if ($this->matchOp('!')) {
            return ! (bool) $this->parseNot();
        }
        return $this->parseComparison();
    }

    private function parseComparison(): mixed
    {
        $left = $this->parsePrimary();
        if ($this->matchOp('==')) {
            $right = $this->parsePrimary();
            return $left == $right;
        }
        if ($this->matchOp('!=')) {
            $right = $this->parsePrimary();
            return $left != $right;
        }
        return $left;
    }

    private function parsePrimary(): mixed
    {
        if ($this->pos >= count($this->tokens)) {
            throw new \InvalidArgumentException('Unexpected end of expression');
        }
        $tok = $this->tokens[$this->pos];

        if ($tok['type'] === '(') {
            $this->pos++;
            $value = $this->parseOr();
            if ($this->pos >= count($this->tokens) || $this->tokens[$this->pos]['type'] !== ')') {
                throw new \InvalidArgumentException('Missing closing parenthesis');
            }
            $this->pos++;
            return $value;
        }

        if (in_array($tok['type'], ['string', 'number', 'bool', 'null'], true)) {
            $this->pos++;
            return $tok['value'];
        }

        if ($tok['type'] === 'ident') {
            $this->pos++;
            return $this->resolveIdentifier($tok['value']);
        }

        throw new \InvalidArgumentException("Unexpected token '" . $tok['value'] . "'");
    }

    private function matchOp(string $op): bool
    {
        if ($this->pos < count($this->tokens)
            && $this->tokens[$this->pos]['type'] === 'op'
            && $this->tokens[$this->pos]['value'] === $op
        ) {
            $this->pos++;
            return true;
        }
        return false;
    }

    /**
     * Resolve a whitelisted identifier to its runtime value.
     *
     * Adding a new namespace? Add another `if (str_starts_with(...))` branch
     * here and document it in the class-level docblock. Keep the namespaces
     * read-only and side-effect-free.
     */
    private function resolveIdentifier(string $name): mixed
    {
        if (str_starts_with($name, 'app.')) {
            $key = substr($name, 4);
            if ($key === '') {
                throw new \InvalidArgumentException("Identifier 'app.' missing key");
            }
            return Application::get($key, null);
        }

        throw new \InvalidArgumentException("Unknown identifier '$name' (only app.<key> is supported)");
    }
}
