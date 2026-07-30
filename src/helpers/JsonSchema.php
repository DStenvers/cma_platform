<?php
/**
 * Minimal JSON Schema validator for the platform's own config schemas.
 *
 * Scope is deliberately the keyword set those schemas actually use:
 * $ref (local), oneOf, type, enum, properties, required, additionalProperties
 * (as a schema), items, minimum, maximum, pattern, maxItems, minItems, and
 * contains with min/maxContains — the only way to say "at most one entry in
 * this array may look like X", which draft-07 cannot express.
 * Unknown keywords are ignored, which is what the spec prescribes for
 * annotations — so a schema may carry title/description/default freely.
 *
 * Data is expected in json_decode($s, true) shape: JSON objects arrive as
 * PHP associative arrays. An empty array is therefore ambiguous and counts
 * as both "object" and "array", which is the only sane reading.
 *
 * @package App\Library
 */

namespace App\Library;

final class JsonSchema
{
    /**
     * Validate a decoded JSON document against a decoded JSON schema.
     *
     * @return string[] Human-readable errors, each prefixed with the path of
     *                  the offending value. Empty array means valid.
     */
    public static function validate($data, array $schema): array
    {
        $errors = [];
        self::check($data, $schema, '', $schema, $errors);
        return $errors;
    }

    /**
     * True when the value satisfies the schema — used for oneOf branches,
     * where a failing branch is not an error by itself.
     */
    public static function matches($data, array $schema, array $root): bool
    {
        $errors = [];
        self::check($data, $schema, '', $root, $errors);
        return $errors === [];
    }

    private static function check($value, array $schema, string $path, array $root, array &$errors): void
    {
        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            $resolved = self::resolveRef($schema['$ref'], $root);
            if ($resolved === null) {
                // A reference we cannot resolve says nothing about the data.
                // Reporting it as a data error would blame the wrong file.
                return;
            }
            $schema = $resolved;
        }

        if (isset($schema['oneOf']) && is_array($schema['oneOf'])) {
            foreach ($schema['oneOf'] as $branch) {
                if (is_array($branch) && self::matches($value, $branch, $root)) {
                    return;
                }
            }
            $errors[] = self::at($path) . 'past op geen van de toegestane vormen';
            return;
        }

        if (isset($schema['type']) && !self::typeMatches($value, $schema['type'])) {
            $expected = is_array($schema['type']) ? implode('/', $schema['type']) : (string)$schema['type'];
            $errors[] = self::at($path) . 'moet ' . $expected . ' zijn, is ' . self::typeName($value);
            return; // every further keyword assumes the type held
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            $errors[] = self::at($path) . 'moet één van [' . implode(', ', array_map([self::class, 'scalar'], $schema['enum'])) . '] zijn, is ' . self::scalar($value);
            return;
        }

        if (is_string($value)) {
            if (isset($schema['pattern']) && is_string($schema['pattern'])
                && @preg_match('#' . str_replace('#', '\\#', $schema['pattern']) . '#', $value) !== 1) {
                $errors[] = self::at($path) . 'past niet op patroon ' . $schema['pattern'];
            }
            return;
        }

        if (is_int($value) || is_float($value)) {
            if (isset($schema['minimum']) && $value < $schema['minimum']) {
                $errors[] = self::at($path) . 'moet minimaal ' . $schema['minimum'] . ' zijn, is ' . $value;
            }
            if (isset($schema['maximum']) && $value > $schema['maximum']) {
                $errors[] = self::at($path) . 'moet maximaal ' . $schema['maximum'] . ' zijn, is ' . $value;
            }
            return;
        }

        if (!is_array($value)) {
            return;
        }

        // Arrays and objects share the PHP array type, so let the schema decide
        // which shape is being described; a list-shaped value with "properties"
        // is simply an empty object as far as those checks go.
        if (self::isList($value) && (isset($schema['items']) || isset($schema['maxItems'])
            || isset($schema['minItems']) || isset($schema['contains']))) {
            self::checkList($value, $schema, $path, $root, $errors);
            return;
        }

        self::checkObject($value, $schema, $path, $root, $errors);
    }

    private static function checkList(array $value, array $schema, string $path, array $root, array &$errors): void
    {
        if (isset($schema['minItems']) && count($value) < $schema['minItems']) {
            $errors[] = self::at($path) . 'moet minimaal ' . $schema['minItems'] . ' item(s) hebben, heeft ' . count($value);
        }
        if (isset($schema['maxItems']) && count($value) > $schema['maxItems']) {
            $errors[] = self::at($path) . 'mag maximaal ' . $schema['maxItems'] . ' item(s) hebben, heeft ' . count($value);
        }
        if (isset($schema['contains']) && is_array($schema['contains'])) {
            self::checkContains($value, $schema, $path, $root, $errors);
        }
        if (!isset($schema['items']) || !is_array($schema['items']) || $schema['items'] === []) {
            return; // "items": {} accepts anything
        }
        foreach ($value as $i => $item) {
            self::check($item, $schema['items'], $path . '[' . $i . ']', $root, $errors);
        }
    }

    /**
     * How many items match "contains", against minContains/maxContains. This is
     * what expresses a cross-item rule such as "exactly one database may be the
     * default"; the description on the contains schema, when present, names the
     * rule in the error so the operator does not have to read the schema.
     */
    private static function checkContains(array $value, array $schema, string $path, array $root, array &$errors): void
    {
        $matched = 0;
        foreach ($value as $item) {
            if (self::matches($item, $schema['contains'], $root)) {
                $matched++;
            }
        }

        $rule = isset($schema['contains']['description'])
            ? ' (' . $schema['contains']['description'] . ')'
            : '';
        $min = $schema['minContains'] ?? 1;
        $max = $schema['maxContains'] ?? null;

        if ($matched < $min) {
            $errors[] = self::at($path) . 'moet minimaal ' . $min . ' item(s) hebben' . $rule . ', heeft ' . $matched;
        }
        if ($max !== null && $matched > $max) {
            $errors[] = self::at($path) . 'mag maximaal ' . $max . ' item(s) hebben' . $rule . ', heeft ' . $matched;
        }
    }

    private static function checkObject(array $value, array $schema, string $path, array $root, array &$errors): void
    {
        foreach ((array)($schema['required'] ?? []) as $key) {
            if (!array_key_exists($key, $value)) {
                $errors[] = self::at($path) . 'mist de verplichte key ' . $key;
            }
        }

        $properties = (array)($schema['properties'] ?? []);
        foreach ($properties as $key => $sub) {
            if (array_key_exists($key, $value) && is_array($sub)) {
                self::check($value[$key], $sub, $path === '' ? $key : $path . '.' . $key, $root, $errors);
            }
        }

        // additionalProperties as a schema: every key the properties list does
        // not name must satisfy it. As a boolean it is a strictness switch.
        $extra = $schema['additionalProperties'] ?? null;
        if ($extra === null) {
            return;
        }
        foreach ($value as $key => $sub) {
            if (isset($properties[$key])) {
                continue;
            }
            if ($extra === false) {
                $errors[] = self::at($path) . 'heeft de onbekende key ' . $key;
            } elseif (is_array($extra) && $extra !== []) {
                self::check($sub, $extra, $path === '' ? (string)$key : $path . '.' . $key, $root, $errors);
            }
        }
    }

    /** Local references only: #/definitions/x and #/$defs/x. */
    private static function resolveRef(string $ref, array $root): ?array
    {
        if (strpos($ref, '#/') !== 0) {
            return null;
        }
        $node = $root;
        foreach (explode('/', substr($ref, 2)) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return null;
            }
            $node = $node[$segment];
        }
        return is_array($node) ? $node : null;
    }

    private static function typeMatches($value, $type): bool
    {
        foreach ((array)$type as $one) {
            switch ($one) {
                case 'object':  if (is_array($value) && (!self::isList($value) || $value === [])) return true; break;
                case 'array':   if (is_array($value) && self::isList($value)) return true; break;
                case 'string':  if (is_string($value)) return true; break;
                case 'integer': if (is_int($value)) return true; break;
                case 'number':  if (is_int($value) || is_float($value)) return true; break;
                case 'boolean': if (is_bool($value)) return true; break;
                case 'null':    if ($value === null) return true; break;
                default:        return true; // unknown type keyword: no opinion
            }
        }
        return false;
    }

    /** array_is_list() is PHP 8.1; the platform supports 8.0. */
    private static function isList(array $value): bool
    {
        $i = 0;
        foreach ($value as $key => $_) {
            if ($key !== $i++) {
                return false;
            }
        }
        return true;
    }

    private static function typeName($value): string
    {
        if (is_array($value)) {
            return self::isList($value) ? 'array' : 'object';
        }
        if (is_bool($value))   return 'boolean';
        if (is_int($value))    return 'integer';
        if (is_float($value))  return 'number';
        if (is_string($value)) return 'string';
        if ($value === null)   return 'null';
        return gettype($value);
    }

    private static function scalar($value): string
    {
        if (is_bool($value))   return $value ? 'true' : 'false';
        if ($value === null)   return 'null';
        if (is_array($value))  return self::typeName($value);
        return (string)$value;
    }

    private static function at(string $path): string
    {
        return $path === '' ? 'De config ' : $path . ': ';
    }
}
