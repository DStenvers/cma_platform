<?php

namespace App\Library;

/**
 * VBScript RegExp (VBScript 5.5 engine) compatibility shim, backed by PCRE.
 *
 * ASP→PHP converted code emits `new Regexp()` with the classic VBScript RegExp
 * surface — the `Pattern` / `IgnoreCase` / `Global` / `MultiLine` properties and
 * the `Test()` / `Replace()` / `Execute()` methods. That object was never ported,
 * so every reach for it fataled with "Class \"Regexp\" not found". This class is
 * that object.
 *
 * VBScript's regex dialect is very close to a subset of PCRE, so this is a thin
 * translation layer rather than a re-implementation. The differences it bridges:
 *   - Flags are PROPERTIES in VBScript (IgnoreCase, MultiLine) vs inline modifiers
 *     in PCRE  -> mapped to the `i` / `m` flags.
 *   - `Global` decides replace-all vs replace-first  -> preg limit (-1 vs 1).
 *   - VBScript uses `￿` for a Unicode code point; PCRE uses `\x{FFFF}`
 *     -> rewritten in the pattern.
 *   - VBScript has no lookbehind; that's a non-issue here (source patterns can't
 *     contain what the source engine couldn't run).
 * Byte semantics (no `/u`) are deliberate: this codebase is ISO-8859-1/Windows-
 * 1252, matching VBScript's ANSI string handling; adding `/u` would change how
 * `.`/`\w` count multibyte bytes versus the original.
 *
 * The class is registered as a GLOBAL `Regexp` alias in Bootstrap so unqualified
 * `new Regexp()` in converted (namespace-less) includes resolves to it.
 */
class Regexp
{
    /** @var string The regular-expression pattern (VBScript syntax). */
    public string $Pattern = '';

    /** @var bool Case-insensitive matching (VBScript RegExp.IgnoreCase). */
    public bool $IgnoreCase = false;

    /** @var bool Replace/scan all matches, not just the first (RegExp.Global). */
    public bool $Global = false;

    /** @var bool `^`/`$` match at line boundaries (RegExp.MultiLine). */
    public bool $MultiLine = false;

    /**
     * Translate the VBScript pattern + property flags into a full PCRE pattern.
     */
    private function toPcre(): string
    {
        $pattern = $this->Pattern;

        // VBScript ￿ (exactly four hex digits) -> PCRE \x{FFFF}.
        $pattern = preg_replace_callback(
            '/(?<!\\\\)((?:\\\\\\\\)*)\\\\u([0-9A-Fa-f]{4})/',
            static fn ($m) => $m[1] . '\\x{' . $m[2] . '}',
            $pattern
        ) ?? $pattern;

        $flags = '';
        if ($this->IgnoreCase) {
            $flags .= 'i';
        }
        if ($this->MultiLine) {
            $flags .= 'm';
        }

        // Pick a delimiter the pattern doesn't contain, so we never have to
        // rewrite the pattern body. Fall back to '/' with escaped slashes.
        foreach (['/', '~', '#', '%', '@', '!', '`'] as $d) {
            if (strpos($pattern, $d) === false) {
                return $d . $pattern . $d . $flags;
            }
        }
        return '/' . str_replace('/', '\\/', $pattern) . '/' . $flags;
    }

    /**
     * VBScript RegExp.Test — does the pattern match anywhere in the subject?
     */
    public function Test($subject): bool
    {
        return preg_match($this->toPcre(), (string) $subject) === 1;
    }

    /**
     * VBScript RegExp.Replace. Honors Global (all vs first). Backreferences use
     * the same `$1`/`$2` syntax in both engines, so replacement passes through;
     * a literal `$` that is NOT a backreference is escaped to `\$` so PCRE keeps
     * it literal (VBScript treats it as literal too).
     */
    public function Replace($subject, $replacement): string
    {
        $replacement = (string) $replacement;
        // Protect literal `$` (not followed by a digit or `{n}`) — PCRE would
        // otherwise try to interpret it.
        $replacement = preg_replace('/\$(?![0-9]|\{[0-9])/', '\\\\$0', $replacement);

        $limit = $this->Global ? -1 : 1;
        $result = preg_replace($this->toPcre(), $replacement, (string) $subject, $limit);

        // On a malformed pattern PCRE returns null; VBScript would have surfaced
        // an error too, but returning the input unchanged is the safe shim choice.
        return $result === null ? (string) $subject : $result;
    }

    /**
     * VBScript RegExp.Execute — returns the matches. Each element mirrors a
     * VBScript Match: ['Value','FirstIndex','Length','SubMatches'=>[...]]. When
     * Global is false only the first match is returned (as VBScript does).
     */
    public function Execute($subject): array
    {
        $subject = (string) $subject;
        preg_match_all($this->toPcre(), $subject, $sets, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $matches = [];
        foreach ($sets as $set) {
            $whole = $set[0];
            $sub = [];
            foreach (array_slice($set, 1) as $group) {
                $sub[] = $group[0];
            }
            $matches[] = [
                'Value' => $whole[0],
                'FirstIndex' => $whole[1],
                'Length' => strlen($whole[0]),
                'SubMatches' => $sub,
            ];
            if (!$this->Global) {
                break;
            }
        }
        return $matches;
    }
}
