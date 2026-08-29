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
        $host = self::host($opts);
        self::alert(
            $siteRoot,
            '[deploy] /cma/ sync incompleet op ' . $host,
            "De /cma/ map is onvolledig na de deploy van {$host}.\n\n"
                . "Ontbrekende kernbestanden:\n"
                . '  - ' . implode("\n  - ", $missing) . "\n\n"
                . "Oorzaak: `composer install` (Installer::postInstall) heeft\n"
                . "vendor/stenversonline/platform/cma niet volledig naar /cma/\n"
                . "gesynced. Het CMA-admin (/cma/form.php) is nu onbereikbaar.\n\n"
                . "Herstel op de server, in de site-root:\n"
                . "  composer install --no-dev --optimize-autoloader\n\n"
                . "Controleer daarna https://{$host}/deploy_status.php — die\n"
                . "blijft bereikbaar ook als /cma/ stuk is.\n",
            $opts,
            $log
        );
        return ['ok' => false, 'missing' => $missing];
    }

    /**
     * Staat in vendor/ het platform dat de site zegt te willen?
     *
     * composer.lock legt per pakket een commit vast; vendor/composer/installed.json zegt
     * welke er daadwerkelijk staat. Lopen die uiteen, dan heeft de composer-stap niet
     * gedaan wat de deploy aannam — vendor/ is achtergebleven of half bijgewerkt. Dat is
     * de stille variant: de deploy eindigt groen en de site draait ondertussen op oude
     * platformcode.
     *
     * Wat dit NIET kan zien: dat er een nieuwere release bestaat. Lock en vendor kunnen
     * allebei even oud zijn — dan klopt vendor met de lock en zwijgt deze controle. Daar
     * is de regel "platform: <versie>" in het deploy-log en de footer voor; die zegt
     * altijd waar de site staat.
     *
     * @param string              $siteRoot Absoluut pad naar de site-root.
     * @param array<string,mixed> $opts     'log_file' => ander pad voor het deploy-log.
     * @return array{ok:bool, locked:string, installed:string, version:string}
     */
    public static function platformVersionCheck(string $siteRoot, array $opts = []): array
    {
        $siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
        $logFile  = (string)($opts['log_file'] ?? $siteRoot . '/.logs/deploy/deploy.log');
        $log = static function (string $msg) use ($logFile): void {
            $dir = dirname($logFile);
            if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
            @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
        };

        $locked    = self::referenceUit($siteRoot . '/composer.lock');
        $installed = self::referenceUit($siteRoot . '/vendor/composer/installed.json');
        $version   = '(onbekend)';
        $platJson  = $siteRoot . '/vendor/stenversonline/platform/composer.json';
        if (is_file($platJson)) {
            $pj = json_decode((string) @file_get_contents($platJson), true);
            if (is_array($pj) && isset($pj['version'])) {
                $version = (string) $pj['version'];
            }
        }

        $resultaat = ['ok' => true, 'locked' => $locked, 'installed' => $installed, 'version' => $version];

        // Geen van beide te lezen: niets te vergelijken, en klagen zou hier alleen ruis
        // zijn (een site kan het platform als pad-repo hebben, of vendor kan ontbreken
        // omdat composer nog moet draaien).
        if ($locked === '' || $installed === '') {
            $log('[health] platform ' . $version . ' — lock/installed niet te vergelijken');
            return $resultaat;
        }

        if ($locked === $installed) {
            $log('[health] platform ' . $version . ' (' . substr($installed, 0, 7) . ') volgt composer.lock');
            return $resultaat;
        }

        $resultaat['ok'] = false;
        $log('[health] platform LOOPT ACHTER — composer.lock wil ' . substr($locked, 0, 7)
            . ', vendor/ heeft ' . substr($installed, 0, 7) . ' (versie ' . $version . ').'
            . ' De composer-stap heeft niet gedaan wat de deploy aannam.');
        $host = self::host($opts);
        self::alert(
            $siteRoot,
            '[deploy] platform loopt achter op ' . $host,
            "Op {$host} staat in vendor/ niet het platform dat composer.lock voorschrijft.\n\n"
                . '  composer.lock wil : ' . substr($locked, 0, 7) . "\n"
                . '  vendor/ heeft     : ' . substr($installed, 0, 7) . ' (versie ' . $version . ")\n\n"
                . "De composer-stap van de deploy heeft dus niet gedaan wat er werd\n"
                . "aangenomen: de deploy eindigde groen, maar de site draait door op\n"
                . "oude platformcode.\n\n"
                . "Herstel op de server, in de site-root:\n"
                . "  composer install --no-dev --optimize-autoloader\n\n"
                . "Blijft het terugkomen, kijk dan naar DEPLOY_COMPOSER_UPDATE in .env\n"
                . "(een '-' of 'install' slaat de update over) en naar de composer-regels\n"
                . "in https://{$host}/deploy_status.php.\n",
            $opts,
            $log
        );
        return $resultaat;
    }

    /**
     * De vastgelegde commit van stenversonline/platform uit een composer.lock of
     * installed.json. Beide bestanden hebben dezelfde pakketvorm; alleen de omhullende
     * structuur verschilt (lock: 'packages', installed.json: 'packages' of een kale lijst).
     */
    private static function referenceUit(string $bestand): string
    {
        if (!is_file($bestand)) {
            return '';
        }
        $data = json_decode((string) @file_get_contents($bestand), true);
        if (!is_array($data)) {
            return '';
        }
        $pakketten = $data['packages'] ?? $data;
        if (!is_array($pakketten)) {
            return '';
        }
        foreach ($pakketten as $pak) {
            if (!is_array($pak) || ($pak['name'] ?? '') !== 'stenversonline/platform') {
                continue;
            }
            $ref = (string) ($pak['source']['reference'] ?? '');
            if ($ref === '') {
                $ref = (string) ($pak['dist']['reference'] ?? '');
            }
            return $ref;
        }
        return '';
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
    /** De host waar deze melding over gaat, voor onderwerp en tekst. */
    private static function host(array $opts): string
    {
        return (string)($opts['host'] ?? ($_SERVER['HTTP_HOST'] ?? (gethostname() ?: 'unknown-host')));
    }

    private static function alert(string $siteRoot, string $subject, string $body, array $opts, callable $log): void
    {
        $to = (string)($opts['mail_to'] ?? self::envFirst([
            'DEPLOY_HEALTH_EMAIL', 'ADMIN_EMAIL', 'MAIL_FROM',
        ]));
        if ($to === '' || !class_exists(Email::class)) {
            return;
        }

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
