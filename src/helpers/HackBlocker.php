<?php

namespace App\Library;

/**
 * Temporary IP blocks for clients that keep requesting hack URLs.
 *
 * A request that ends in a 404 on a path from the hack list (NotFoundLists) is
 * a hit for the client's IP. More than HACK_BLOCK_THRESHOLD hits within
 * HACK_BLOCK_WINDOW_MINUTES block that IP for HACK_BLOCK_DURATION_MINUTES:
 * every PHP request from it gets a bare 403 until the block expires or an
 * administrator lifts it (logreader → 404 → Blokkades).
 *
 * Two places, because IIS sends a missing file or an unknown URL straight to
 * the 404 handler, without the bootstrap:
 *   - Bootstrap::init calls guard(): a blocked IP gets its 403 before any page.
 *   - the 404 handlers (site 404.php, cma/404.php) require cma/hackguard.inc,
 *     which calls guardNotFound(): the 403 too, and the hit is counted.
 *
 * The IP is REMOTE_ADDR, as in the 404 log. Never X-Forwarded-For: a client
 * sets that header itself, so it could dodge the block or get someone else
 * blocked.
 *
 * Files:
 *   data/ip_blocks.json      active blocks  {ip: {sinds, tot, pogingen, laatste}}
 *   .logs/404/hackhits.json  hits inside the window  {ip: [unix time, ...]}
 */
final class HackBlocker
{
    private string $blockFile;
    private string $hitsFile;

    public function __construct(string $siteRoot)
    {
        $siteRoot = rtrim($siteRoot, '/\\');
        $this->blockFile = $siteRoot . '/data/ip_blocks.json';
        $this->hitsFile  = $siteRoot . '/.logs/404/hackhits.json';
    }

    /** The bootstrap's gate: a blocked IP stops here with a 403. One file check for everyone else. */
    public static function guard(string $siteRoot): void
    {
        $ip = self::clientIp();
        if ($ip === null) {
            return;
        }
        $now = time();
        $until = (new self($siteRoot))->blockedUntil($ip, $now);
        if ($until !== null) {
            self::deny($until, $now);
        }
    }

    /**
     * The 404 handlers' gate: the 403 for a blocked IP, and a hit when the
     * missing path is on the hack list. Runs without the bootstrap, so the
     * HACK_BLOCK_* values are read from .env here.
     */
    public static function guardNotFound(string $siteRoot): void
    {
        self::loadOwnSettings($siteRoot);
        $ip = self::clientIp();
        if ($ip === null) {
            return;
        }
        $blocker = new self($siteRoot);
        $now = time();
        $until = $blocker->blockedUntil($ip, $now);
        if ($until === null) {
            $path = NotFoundLists::pathOf((string) ($_SERVER['REQUEST_URI'] ?? ''));
            if (NotFoundLists::forSite($siteRoot)->classify($path) === NotFoundLists::HACK) {
                $until = $blocker->recordHit(
                    $ip,
                    $path,
                    $now,
                    (int) Settings::get('hack_block_threshold'),
                    (int) Settings::get('hack_block_window_minutes') * 60,
                    (int) Settings::get('hack_block_duration_minutes') * 60
                );
            }
        }
        if ($until !== null) {
            self::deny($until, $now);
        }
    }

    /** REMOTE_ADDR when blocking is on and this is a web request; null otherwise. */
    private static function clientIp(): ?string
    {
        if (PHP_SAPI === 'cli' || !Settings::get('hack_block_enabled')) {
            return null;
        }
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return $ip === '' ? null : $ip;
    }

    /**
     * The HACK_BLOCK_* values from the site's .env, for a request the
     * bootstrap did not load it for. A value the environment already has wins.
     */
    private static function loadOwnSettings(string $siteRoot): void
    {
        foreach (EnvFile::load(rtrim($siteRoot, '/\\') . '/.env') as $key => $value) {
            if (strncmp($key, 'HACK_BLOCK_', 11) === 0 && EnvFile::value($key) === null) {
                $_ENV[$key] = $value;
            }
        }
    }

    private static function deny(int $until, int $now): void
    {
        http_response_code(403);
        header('Retry-After: ' . max(1, $until - $now));
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Toegang tijdelijk geblokkeerd.';
        exit;
    }

    /** Unix time the block on $ip ends, or null when it is not blocked. */
    public function blockedUntil(string $ip, int $now): ?int
    {
        $until = (int) ($this->blocks()[$ip]['tot'] ?? 0);
        return $until > $now ? $until : null;
    }

    /**
     * Count one hack hit. Returns the end of the block when this hit pushes
     * the IP over the threshold, null otherwise.
     */
    public function recordHit(string $ip, string $path, int $now, int $threshold, int $window, int $duration): ?int
    {
        $dir = dirname($this->hitsFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $fh = @fopen($this->hitsFile, 'c+');
        if ($fh === false) {
            ErrorHandler::report(new \RuntimeException('Kan ' . $this->hitsFile . ' niet openen'), 'HackBlocker');
            return null;
        }
        try {
            flock($fh, LOCK_EX);
            $hits = json_decode((string) stream_get_contents($fh), true);
            $hits = is_array($hits) ? $hits : [];
            // Only hits inside the window count; the rest is dropped on every write,
            // so the file never holds more than one window's worth.
            foreach ($hits as $hitIp => $times) {
                $hits[$hitIp] = array_values(array_filter((array) $times, static fn ($t): bool => (int) $t > $now - $window));
                if ($hits[$hitIp] === []) {
                    unset($hits[$hitIp]);
                }
            }
            $hits[$ip][] = $now;
            $until = null;
            if (count($hits[$ip]) > $threshold) {
                $until = $now + $duration;
                $this->block($ip, $now, $until, count($hits[$ip]), $path);
                unset($hits[$ip]);
            }
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, (string) json_encode($hits));
            return $until;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /**
     * Active blocks, expired ones left out.
     *
     * @return array<string, array{sinds:int, tot:int, pogingen:int, laatste:string}>
     */
    public function blocks(?int $now = null): array
    {
        if (!is_file($this->blockFile)) {
            return [];
        }
        $data = json_decode((string) @file_get_contents($this->blockFile), true);
        if (!is_array($data)) {
            return [];
        }
        $now ??= time();
        return array_filter($data, static fn ($b): bool => is_array($b) && (int) ($b['tot'] ?? 0) > $now);
    }

    /** Lift the block on $ip. False when there was none. */
    public function unblock(string $ip): bool
    {
        $blocks = $this->blocks();
        if (!isset($blocks[$ip])) {
            return false;
        }
        unset($blocks[$ip]);
        return $this->saveBlocks($blocks);
    }

    private function block(string $ip, int $now, int $until, int $hits, string $path): void
    {
        $blocks = $this->blocks($now);
        $blocks[$ip] = ['sinds' => $now, 'tot' => $until, 'pogingen' => $hits, 'laatste' => $path];
        if (!$this->saveBlocks($blocks)) {
            ErrorHandler::report(new \RuntimeException('Kan ' . $this->blockFile . ' niet schrijven'), 'HackBlocker');
        }
    }

    /** Write via a temp file + rename, so the gate never reads half a file. */
    private function saveBlocks(array $blocks): bool
    {
        $dir = dirname($this->blockFile);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return false;
        }
        $tmp = $this->blockFile . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, json_encode($blocks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n") === false) {
            return false;
        }
        if (!@rename($tmp, $this->blockFile)) {
            // Windows: rename onto an existing file can fail while it is open; fall back.
            $ok = @copy($tmp, $this->blockFile);
            @unlink($tmp);
            return $ok;
        }
        return true;
    }
}
