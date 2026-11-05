<?php

namespace App\Library;

/**
 * Suggests hack patterns from the 404 log with the LLM (App\Library\Llm).
 *
 * The model sees only paths and their counts (no IPs, user agents, query
 * strings) and answers with patterns. Nothing it says is trusted: parse()
 * keeps a suggestion only when it is a well-formed pattern, is not on a list
 * yet, is not nearly "everything", and matches at least one path of the log
 * (so a pattern cannot be invented). Each suggestion carries the number of
 * misses and example paths it would catch, so the administrator judges it
 * before adding it; nothing is added automatically.
 */
final class NotFoundAnalyzer
{
    public const MAX_PATHS = 200;
    public const MAX_SUGGESTIONS = 20;

    /** Months after which the dashboard reminds the administrator to run the analysis again. */
    public const REMINDER_MONTHS = 3;

    /** Days of 404 log the analysis reads (the newest day files). */
    public const LOG_DAYS = 7;

    /** The lines of the newest LOG_DAYS day files of the 404 log in $logDir. @return list<string> */
    public static function recentLines(string $logDir): array
    {
        $files = glob(rtrim($logDir, '/\\') . '/404_*.log') ?: [];
        rsort($files);
        $lines = [];
        foreach (array_slice($files, 0, self::LOG_DAYS) as $file) {
            foreach ((array) file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $lines[] = $line;
            }
        }
        return $lines;
    }

    private static function runFile(string $siteRoot): string
    {
        return rtrim($siteRoot, '/\\') . '/data/notfound_analysis.json';
    }

    /** Unix time of the last successful analysis, or null when it never ran. */
    public static function lastRun(string $siteRoot): ?int
    {
        $data = json_decode(is_file(self::runFile($siteRoot)) ? (string) file_get_contents(self::runFile($siteRoot)) : '', true);
        return is_array($data) && (int) ($data['laatste'] ?? 0) > 0 ? (int) $data['laatste'] : null;
    }

    public static function markRun(string $siteRoot, ?int $now = null): bool
    {
        $file = self::runFile($siteRoot);
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return false;
        }
        return @file_put_contents($file, json_encode(['laatste' => $now ?? time()])) !== false;
    }

    /** Has the analysis never run, or not within REMINDER_MONTHS months? */
    public static function reminderDue(string $siteRoot, ?int $now = null): bool
    {
        $last = self::lastRun($siteRoot);
        $now ??= time();
        return $last === null || $last < strtotime('-' . self::REMINDER_MONTHS . ' months', $now);
    }

    /**
     * Paths in the log that are on no list, most frequent first.
     *
     * @param  iterable<string> $lines JSON lines of the 404 log
     * @return array<string,int> path => number of misses
     */
    public static function candidates(iterable $lines, NotFoundLists $lists): array
    {
        $counts = [];
        foreach ($lines as $line) {
            $entry = json_decode(trim((string) $line), true);
            if (!is_array($entry) || ($entry['type'] ?? '') === 'icon_redirect') {
                continue;
            }
            $path = NotFoundLists::pathOf((string) ($entry['url'] ?? ''));
            if ($path === '(onbekend)' || $lists->classify($path) !== null) {
                continue;
            }
            $path = substr($path, 0, 200);
            $counts[$path] = ($counts[$path] ?? 0) + 1;
        }
        arsort($counts);
        return array_slice($counts, 0, self::MAX_PATHS, true);
    }

    /**
     * Ask the model which of $candidates are hack attempts.
     *
     * @param  array<string,int> $candidates
     * @return array{ok:bool, error:string, suggestions:list<array{patroon:string, reden:string, aantal:int, voorbeelden:list<string>}>}
     */
    public static function suggest(array $candidates, NotFoundLists $lists): array
    {
        if ($candidates === []) {
            return ['ok' => true, 'error' => '', 'suggestions' => []];
        }
        $system = 'Je analyseert het 404-log van een website. Je krijgt paden die niet bestaan, met het aantal keer dat ze werden opgevraagd. '
            . 'Zoek de paden waarmee scanners en hackers naar kwetsbaarheden zoeken (WordPress, .env, .git, phpMyAdmin, shells, backups, enz.) en geef daar patronen voor. '
            . 'Een patroon is een pad met * voor "elke tekst"; zonder * is het één exact pad. Houd patronen zo smal mogelijk: een gewone bezoeker met een kapotte link mag nooit onder een patroon vallen. '
            . 'Antwoord uitsluitend met een JSON-array van objecten {"patroon": "...", "reden": "korte uitleg in het Nederlands"}, zonder tekst eromheen. Geen hackpogingen gevonden: antwoord [].';
        $user = '';
        foreach ($candidates as $path => $count) {
            $user .= $count . "\t" . $path . "\n";
        }
        $result = Llm::generate($system, $user, null, 1500);
        if (!$result['ok']) {
            return ['ok' => false, 'error' => (string) $result['error'], 'suggestions' => []];
        }
        return ['ok' => true, 'error' => '', 'suggestions' => self::parse($result['text'], $candidates, $lists)];
    }

    /**
     * Validate the model's answer. Pure, so it is unit-tested.
     *
     * @param  array<string,int> $candidates
     * @return list<array{patroon:string, reden:string, aantal:int, voorbeelden:list<string>}>
     */
    public static function parse(string $answer, array $candidates, NotFoundLists $lists): array
    {
        $items = json_decode(Llm::stripWrapping($answer), true);
        if (!is_array($items)) {
            return [];
        }
        $known = array_map('strtolower', array_column($lists->all()[NotFoundLists::HACK], 'patroon'));
        $suggestions = [];
        foreach ($items as $item) {
            if (!is_array($item) || !is_string($item['patroon'] ?? null)) {
                continue;
            }
            $pattern = NotFoundLists::normalize($item['patroon']);
            // Without the wildcards at least four characters must remain: "*", "/*" or "*a*" would catch ordinary visitors.
            if ($pattern === '' || $pattern[0] !== '/' && $pattern[0] !== '*' || strlen(str_replace('*', '', $pattern)) < 4) {
                continue;
            }
            if (in_array(strtolower($pattern), $known, true) || isset($suggestions[strtolower($pattern)])) {
                continue;
            }
            $count = 0;
            $examples = [];
            foreach ($candidates as $path => $misses) {
                if (NotFoundLists::matches($pattern, (string) $path)) {
                    $count += $misses;
                    if (count($examples) < 3) {
                        $examples[] = (string) $path;
                    }
                }
            }
            if ($count === 0) {
                continue;
            }
            $suggestions[strtolower($pattern)] = [
                'patroon'     => $pattern,
                'reden'       => substr(trim((string) ($item['reden'] ?? '')), 0, 200),
                'aantal'      => $count,
                'voorbeelden' => $examples,
            ];
            if (count($suggestions) >= self::MAX_SUGGESTIONS) {
                break;
            }
        }
        return array_values($suggestions);
    }
}
