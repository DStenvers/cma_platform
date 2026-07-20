<?php
/**
 * DeployHealth — post-deploy sanity checks for cma_platform consumers.
 *
 * The load-bearing check is cmaSyncCheck(): /cma/ is gitignored on every
 * consumer and exists ONLY because `composer install` ran
 * Installer::postInstall, which syncs vendor/stenversonline/platform/cma
 * → /cma/. When that sync half-fails (network blip to the platform repo,
 * a locked file under an IIS worker, a partial copy) /cma/ is left broken.
 *
 * That used to be invisible AND self-perpetuating: the deploy webhook was
 * historically a shim under /cma/tools/, so a broken sync 404'd the very
 * endpoint that would deploy the fix — stranding the site until someone
 * ran composer by hand (the multi-day June-2026 outage). The robust webhook
 * is now the git-tracked site-root /deploy.php (provisioned by the
 * Installer), which can't vanish with /cma/. This check makes a broken
 * sync LOUD — a prominent deploy.log line (surfaced by the root
 * deploy_status.php even while /cma/ is down) plus a best-effort admin
 * email — so it's noticed before it rots.
 *
 * Generic across consumers; no DB, no app bootstrap required for the
 * check + log. The email is best-effort: it only lands when an app
 * context with SMTP config is loaded (i.e. /deploy.php found a working
 * vendor/ + app config), which is fine — the deploy.log line is the
 * reliable alert.
 *
 * @package App\Library
 */

namespace App\Library;

class DeployHealth
{
    /**
     * Core CMA entrypoints that MUST exist for /cma/ to serve at all.
     * Relative to the site root. If any are missing the composer sync
     * didn't complete.
     */
    public const CMA_PROBES = [
        'cma/index.php',
        'cma/form.php',
        'cma/main.php',
        'cma/bootstrap.inc',
    ];

    /**
     * Verify the /cma/ composer sync produced its core files; log the
     * result, and on failure fire a best-effort admin alert.
     *
     * @param string               $siteRoot Absolute path to the consumer site root.
     * @param array<string,mixed>  $opts     'log_file' => override deploy-log path;
     *                                        'mail_to'  => override alert recipient;
     *                                        'host'     => host label for the alert.
     * @return array{ok:bool, missing:string[]}
     */
    public static function cmaSyncCheck(string $siteRoot, array $opts = []): array
    {
        $siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
        $logFile  = (string)($opts['log_file'] ?? $siteRoot . '/.logs/deploy/deploy.log');

        $missing = [];
        foreach (self::CMA_PROBES as $rel) {
            if (!is_file($siteRoot . '/' . $rel)) {
                $missing[] = $rel;
            }
        }

        $log = static function (string $msg) use ($logFile): void {
            $dir = dirname($logFile);
            if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
            @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
        };

        if ($missing === []) {
            $log('[health] /cma/ sync OK (' . count(self::CMA_PROBES) . ' probes present)');
            return ['ok' => true, 'missing' => []];
        }

        $log('[health] /cma/ sync FAILED — missing: ' . implode(', ', $missing));
        self::alert($siteRoot, $missing, $opts, $log);
        return ['ok' => false, 'missing' => $missing];
    }

    /**
     * Best-effort admin email. Never throws — a notification failure must
     * never mask or abort the deploy. Silently no-ops when no recipient is
     * resolvable or the mailer/app-config isn't available (recovery-path
     * deploys), where the deploy.log line above is the alert of record.
     *
     * @param string[] $missing
     * @param array<string,mixed> $opts
     */
    private static function alert(string $siteRoot, array $missing, array $opts, callable $log): void
    {
        $to = (string)($opts['mail_to'] ?? self::envFirst([
            'DEPLOY_HEALTH_EMAIL', 'ADMIN_EMAIL', 'MAIL_FROM',
        ]));
        if ($to === '' || !class_exists(Email::class)) {
            return;
        }

        $host = (string)($opts['host'] ?? ($_SERVER['HTTP_HOST'] ?? (gethostname() ?: 'unknown-host')));
        $subject = '[deploy] /cma/ sync incompleet op ' . $host;
        $body = "De /cma/ map is onvolledig na de deploy van {$host}.\n\n"
              . "Ontbrekende kernbestanden:\n"
              . '  - ' . implode("\n  - ", $missing) . "\n\n"
              . "Oorzaak: `composer install` (Installer::postInstall) heeft\n"
              . "vendor/stenversonline/platform/cma niet volledig naar /cma/\n"
              . "gesynced. Het CMA-admin (/cma/form.php) is nu onbereikbaar.\n\n"
              . "Herstel op de server, in de site-root:\n"
              . "  composer install --no-dev --optimize-autoloader\n\n"
              . "Controleer daarna https://{$host}/deploy_status.php — die\n"
              . "blijft bereikbaar ook als /cma/ stuk is.\n";

        try {
            Email::create()
                ->setSubject($subject)
                ->setBody(nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')))
                ->addRecipient($to)
                ->send();
            $log('[health] alert e-mail verstuurd naar ' . $to);
        } catch (\Throwable $e) {
            $log('[health] alert e-mail kon niet worden verzonden: ' . $e->getMessage());
        }
    }

    /**
     * First non-empty value across $_ENV then getenv() for a list of keys.
     */
    private static function envFirst(array $keys): string
    {
        foreach ($keys as $key) {
            $v = (string)($_ENV[$key] ?? getenv($key) ?: '');
            if ($v !== '') { return $v; }
        }
        return '';
    }
}
