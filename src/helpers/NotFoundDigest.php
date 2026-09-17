<?php

namespace App\Library;

/**
 * Daily 404 digest.
 *
 * The site's 404 handler appends one JSON line per miss to .logs/404/404_<date>.log
 * and runs without the bootstrap, so it cannot mail. Instead, the first
 * bootstrapped request after midnight mails a summary of the previous day to
 * NOTFOUND_MAIL_TO when NOTFOUND_MAIL_ENABLED is on: how many misses, which
 * paths, and where the visitors came from. One mail per day, never one per
 * miss — a crawler probing /wp-login.php would otherwise flood the inbox.
 *
 * A marker file (digest_<date>.sent) next to the log claims the day before the
 * mail goes out, so concurrent requests cannot both send it.
 */
final class NotFoundDigest
{
    /** User agents counted separately and left out of the path list. */
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|seek|scan|archiver|heritrix|java\/|python-|curl\/|wget\//i';

    /**
     * Send yesterday's digest if it is due. Cheap when nothing is due: one env
     * lookup and, when enabled, two file checks. The send itself runs at
     * shutdown so a slow SMTP server never delays the page that triggered it.
     */
    public static function maybeSend(string $logDir): void
    {
        if (PHP_SAPI === 'cli' || !EnvFile::flag('NOTFOUND_MAIL_ENABLED')) {
            return;
        }
        $to = trim((string) EnvFile::value('NOTFOUND_MAIL_TO'));
        if ($to === '') {
            return;
        }
        $day    = date('Y-m-d', strtotime('yesterday'));
        $log    = $logDir . '/404_' . $day . '.log';
        $marker = $logDir . '/digest_' . $day . '.sent';
        if (!is_file($log) || is_file($marker)) {
            return;
        }
        if (@touch($marker) === false) {
            return; // cannot claim the day; the next request tries again
        }
        register_shutdown_function(static function () use ($log, $day, $to, $logDir): void {
            try {
                $lines = file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                $summary = self::summarize($lines);
                if ($summary['total'] === 0) {
                    return;
                }
                $server = $_SERVER['SERVER_NAME'] ?? php_uname('n');
                $mail = new Email();
                $mail->setSubject('404-overzicht ' . $server . ' ' . $day . ': ' . $summary['total'] . ' niet gevonden');
                $mail->setBody(self::renderBody($day, $summary));
                foreach (array_filter(array_map('trim', explode(',', $to))) as $recipient) {
                    $mail->addRecipient($recipient);
                }
                $mail->send();
                self::prune($logDir, (int) Settings::get('notfound_log_retention_days'));
            } catch (\Throwable $e) {
                error_log('404 digest failed: ' . $e->getMessage());
            }
        });
    }

    /**
     * Aggregate the JSON lines of one day's log. Pure, so it is unit-tested.
     *
     * @param  iterable<string> $lines
     * @return array{total:int, bots:int, paths:array<string,array{count:int, referer:string}>}
     */
    public static function summarize(iterable $lines): array
    {
        $total = 0;
        $bots = 0;
        $paths = [];
        foreach ($lines as $line) {
            $entry = json_decode(trim((string) $line), true);
            if (!is_array($entry) || ($entry['type'] ?? '') === 'icon_redirect') {
                continue;
            }
            $total++;
            if (preg_match(self::BOT_PATTERN, (string) ($entry['ua'] ?? ''))) {
                $bots++;
                continue;
            }
            $url  = (string) ($entry['url'] ?? '');
            $path = parse_url($url, PHP_URL_PATH);
            $path = is_string($path) && $path !== '' ? $path : ($url !== '' ? $url : '(onbekend)');
            if (!isset($paths[$path])) {
                $paths[$path] = ['count' => 0, 'referer' => ''];
            }
            $paths[$path]['count']++;
            $referer = (string) ($entry['referer'] ?? '');
            if ($paths[$path]['referer'] === '' && $referer !== '') {
                $paths[$path]['referer'] = $referer;
            }
        }
        uasort($paths, static fn ($a, $b) => $b['count'] <=> $a['count']);
        return ['total' => $total, 'bots' => $bots, 'paths' => $paths];
    }

    /**
     * @param array{total:int, bots:int, paths:array<string,array{count:int, referer:string}>} $summary
     */
    public static function renderBody(string $day, array $summary): string
    {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $visitors = $summary['total'] - $summary['bots'];
        $body = '<p>Op ' . $e($day) . ' zijn ' . $summary['total'] . ' pagina\'s of bestanden niet gevonden'
              . ($summary['bots'] > 0 ? ', waarvan ' . $summary['bots'] . ' door zoekmachines en scanners' : '')
              . '.</p>';
        if ($visitors === 0) {
            return $body . '<p>Geen misses van bezoekers.</p>';
        }
        $body .= '<table cellpadding="4" cellspacing="0" border="0">'
               . '<tr><td style="color:#666;">Aantal</td><td style="color:#666;">Pad</td><td style="color:#666;">Verwijzing vanaf</td></tr>';
        $shown = 0;
        foreach ($summary['paths'] as $path => $info) {
            if ($shown++ >= (int) Settings::get('notfound_digest_top')) {
                break;
            }
            $body .= '<tr><td align="right">' . $info['count'] . '</td>'
                   . '<td>' . $e($path) . '</td>'
                   . '<td>' . ($info['referer'] !== '' ? $e($info['referer']) : '<span style="color:#999;">-</span>') . '</td></tr>';
        }
        $body .= '</table>';
        $rest = count($summary['paths']) - $shown;
        if ($rest > 0) {
            $body .= '<p style="color:#666;">En nog ' . $rest . ' andere paden.</p>';
        }
        $body .= '<p style="color:#666;">De volledige log staat in het CMA onder Beheerstools → Logbestanden lezen → 404.</p>';
        return $body;
    }

    /**
     * Retention of the 404 logs: daily log files and their digest markers
     * older than $days are removed. The age is the DATE IN THE FILENAME, not
     * mtime, so a tool that opens a log cannot keep it alive. Runs after each
     * digest. Returns the number of files removed.
     */
    public static function prune(string $logDir, int $days, ?int $now = null): int
    {
        $cutoff = ($now ?? time()) - $days * 86400;
        $removed = 0;
        foreach (array_merge(glob($logDir . '/404_*.log') ?: [], glob($logDir . '/digest_*.sent') ?: []) as $file) {
            if (!preg_match('/(\d{4}-\d{2}-\d{2})\.(log|sent)$/', $file, $m)) {
                continue;
            }
            $stamp = strtotime($m[1]);
            if ($stamp !== false && $stamp < $cutoff && @unlink($file)) {
                $removed++;
            }
        }
        return $removed;
    }
}
