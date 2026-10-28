<?php

namespace App\Library;

/**
 * The two beheerlijsten of the 404 overview: hack attempts and ignored 404s.
 *
 * The 404 log mixes three kinds of misses: real broken links (worth fixing),
 * scanners probing for /wp-login.php or /.env (hack attempts), and known misses
 * that are not worth chasing (an old link in an external newsletter). Only the
 * first kind belongs in "Meest gemiste URLs" and in the daily digest. A path
 * that matches a pattern on one of these lists is counted separately instead.
 *
 * Stored per site in data/notfound_lists.json (the Installer never touches
 * data/). A pattern is matched against the PATH of the requested URL, case-
 * insensitively and against the whole path; `*` matches any run of characters.
 * Without `*` a pattern is one exact path.
 */
final class NotFoundLists
{
    public const HACK = 'hack';
    public const IGNORE = 'genegeerd';

    /** Labels for the UI, in display order. */
    public const LABELS = [
        self::HACK   => 'Hackpogingen',
        self::IGNORE => "Genegeerde 404's",
    ];

    private string $file;

    /** @var array<string, list<array{patroon:string, toegevoegd:string, door:string}>>|null */
    private ?array $lists = null;

    /** @var array<string, string> path => list, per instance (dashboard classifies thousands of lines) */
    private array $classified = [];

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    /** The lists of the site in $siteRoot (data/notfound_lists.json). */
    public static function forSite(string $siteRoot): self
    {
        return new self(rtrim($siteRoot, '/\\') . '/data/notfound_lists.json');
    }

    /** @return array<string, list<array{patroon:string, toegevoegd:string, door:string}>> */
    public function all(): array
    {
        if ($this->lists !== null) {
            return $this->lists;
        }
        $lists = [self::HACK => [], self::IGNORE => []];
        if (is_file($this->file)) {
            $data = json_decode((string) file_get_contents($this->file), true);
            if (is_array($data)) {
                foreach (array_keys($lists) as $list) {
                    foreach ((array) ($data[$list] ?? []) as $entry) {
                        if (is_array($entry) && trim((string) ($entry['patroon'] ?? '')) !== '') {
                            $lists[$list][] = [
                                'patroon'    => (string) $entry['patroon'],
                                'toegevoegd' => (string) ($entry['toegevoegd'] ?? ''),
                                'door'       => (string) ($entry['door'] ?? ''),
                            ];
                        }
                    }
                }
            }
        }
        return $this->lists = $lists;
    }

    public static function isList(string $list): bool
    {
        return isset(self::LABELS[$list]);
    }

    /**
     * A pattern as it is stored: trimmed, and a full URL reduced to its path
     * (the log holds full URLs, the lists match paths).
     */
    public static function normalize(string $pattern): string
    {
        $pattern = trim($pattern);
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $pattern)) {
            $path = parse_url($pattern, PHP_URL_PATH);
            $pattern = is_string($path) && $path !== '' ? $path : '/';
        }
        return $pattern;
    }

    /**
     * The path a log line is grouped and matched on: the path of the requested
     * URL, the raw URL when it has no parsable path, '(onbekend)' when empty.
     */
    public static function pathOf(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        return is_string($path) && $path !== '' ? $path : ($url !== '' ? $url : '(onbekend)');
    }

    /** Does $pattern match $path? Whole path, case-insensitive, `*` = anything. */
    public static function matches(string $pattern, string $path): bool
    {
        $pattern = self::normalize($pattern);
        if ($pattern === '') {
            return false;
        }
        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#is';
        return (bool) preg_match($regex, $path);
    }

    /** Which list a path is on (hack wins over genegeerd), or null for a real miss. */
    public function classify(string $path): ?string
    {
        if (array_key_exists($path, $this->classified)) {
            return $this->classified[$path] === '' ? null : $this->classified[$path];
        }
        $found = '';
        foreach ($this->all() as $list => $entries) {
            foreach ($entries as $entry) {
                if (self::matches($entry['patroon'], $path)) {
                    $found = $list;
                    break 2;
                }
            }
        }
        $this->classified[$path] = $found;
        return $found === '' ? null : $found;
    }

    /**
     * Add a pattern. Returns false when the list is unknown, the pattern empty,
     * or the pattern is already on that list (case-insensitive).
     */
    public function add(string $list, string $pattern, string $by): bool
    {
        $pattern = self::normalize($pattern);
        if (!self::isList($list) || $pattern === '') {
            return false;
        }
        $lists = $this->all();
        foreach ($lists[$list] as $entry) {
            if (strcasecmp($entry['patroon'], $pattern) === 0) {
                return false;
            }
        }
        $lists[$list][] = ['patroon' => $pattern, 'toegevoegd' => date('Y-m-d H:i'), 'door' => $by];
        return $this->save($lists);
    }

    /** Remove a pattern (exact, case-insensitive). False when it was not there. */
    public function remove(string $list, string $pattern): bool
    {
        if (!self::isList($list)) {
            return false;
        }
        $lists = $this->all();
        $before = count($lists[$list]);
        $lists[$list] = array_values(array_filter(
            $lists[$list],
            static fn (array $entry): bool => strcasecmp($entry['patroon'], trim($pattern)) !== 0
        ));
        return count($lists[$list]) < $before && $this->save($lists);
    }

    /** Write via a temp file + rename, so a reader never sees half a file. */
    private function save(array $lists): bool
    {
        $dir = dirname($this->file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return false;
        }
        $tmp = $this->file . '.' . getmypid() . '.tmp';
        $json = json_encode($lists, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || @file_put_contents($tmp, $json . "\n") === false) {
            return false;
        }
        if (!@rename($tmp, $this->file)) {
            // Windows: rename onto an existing file can fail while it is open; fall back.
            $ok = @copy($tmp, $this->file);
            @unlink($tmp);
            if (!$ok) {
                return false;
            }
        }
        $this->lists = $lists;
        $this->classified = [];
        return true;
    }
}
