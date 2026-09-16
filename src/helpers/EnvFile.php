<?php

namespace App\Library;

/**
 * Generic reader for `.env` / ini-style `KEY=VALUE` files.
 *
 * Replaces the vlucas/phpdotenv dependency: a `.env` is just a flat,
 * comment-tolerant key/value file, which this parses directly.
 *
 * Deliberately NOT built on PHP's parse_ini_file(): INI treats
 * `?{}|&!()^"` (and the words true/false/null/yes/no) specially and returns
 * false / emits warnings on unquoted values containing reserved characters —
 * which would silently drop a secret like DEPLOY_SECRET=ab&c!d. This parser
 * takes values literally.
 *
 * Supported syntax:
 *   - `KEY=value`               unquoted; an inline ` #comment` is stripped
 *   - `KEY="value"` / `KEY='value'`  quoted; kept verbatim (no inline-comment
 *                               stripping); double-quotes unescape \n \r \t \" \\
 *   - `export KEY=value`        optional leading `export `
 *   - `# comment` / `; comment` whole-line comments (and blank lines) ignored
 *   - keys must match [A-Za-z_][A-Za-z0-9_.]*
 */
final class EnvFile
{
    /**
     * Parse file contents into an associative array. Never throws.
     *
     * @return array<string,string>
     */
    public static function parse(string $contents): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed[0] === '#' || $trimmed[0] === ';') {
                continue;
            }
            if (stripos($trimmed, 'export ') === 0) {
                $trimmed = ltrim(substr($trimmed, 7));
            }
            $eq = strpos($trimmed, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($trimmed, 0, $eq));
            if ($key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $key)) {
                continue;
            }
            $out[$key] = self::parseValue(trim(substr($trimmed, $eq + 1)));
        }
        return $out;
    }

    /**
     * One loaded variable, or null when it is not set. Looks in $_ENV first
     * (where loadInto() puts the .env contents) and falls back to getenv() for
     * values the web server supplies at OS level. Callers outside the CMA —
     * ErrorHandler, the 404 digest — read their settings through this so they
     * need nothing from cma/classes.
     */
    public static function value(string $key): ?string
    {
        if (isset($_ENV[$key])) {
            return (string) $_ENV[$key];
        }
        $v = getenv($key);
        return $v === false ? null : (string) $v;
    }

    /** True for 1/true/on/yes (case-insensitive); false otherwise. */
    public static function flag(string $key, bool $default = false): bool
    {
        $v = self::value($key);
        if ($v === null || trim($v) === '') {
            return $default;
        }
        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Read and parse a file. Returns [] if missing/unreadable.
     *
     * @return array<string,string>
     */
    public static function load(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }
        $contents = @file_get_contents($path);
        return $contents === false ? [] : self::parse($contents);
    }

    /**
     * Load a file and populate $_ENV, $_SERVER and putenv(). Immutable: a key
     * already present in the environment (OS-level or previously loaded) is
     * NOT overwritten — same semantics as phpdotenv's createImmutable().
     *
     * @return array<string,string> the parsed key/value pairs
     */
    public static function loadInto(string $path): array
    {
        $vars = self::load($path);
        foreach ($vars as $key => $value) {
            if (array_key_exists($key, $_ENV) || getenv($key) !== false) {
                continue; // already set — don't override
            }
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
        return $vars;
    }

    private static function parseValue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $q = $value[0];
        if (($q === '"' || $q === "'") && strlen($value) >= 2 && substr($value, -1) === $q) {
            $inner = substr($value, 1, -1);
            if ($q === '"') {
                $inner = strtr($inner, [
                    '\\n' => "\n", '\\r' => "\r", '\\t' => "\t",
                    '\\"' => '"', '\\\\' => '\\',
                ]);
            }
            return $inner;
        }
        // Unquoted: strip an inline comment that begins with whitespace + '#'.
        if (preg_match('/\s#/', $value, $m, PREG_OFFSET_CAPTURE)) {
            $value = rtrim(substr($value, 0, $m[0][1]));
        }
        return $value;
    }
}
