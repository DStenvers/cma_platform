<?php
/**
 * DeployWebhook - GitHub auto-deploy receiver.
 *
 * Verifies an HMAC-SHA256 signature posted by GitHub against a shared
 * secret in `$_ENV[DEPLOY_SECRET]` (or whichever env key the caller
 * names), then runs `git pull` + `composer install` + recycles the
 * IIS app pool. Output is logged to `<root>/logs/deploy.log`.
 *
 * Generic across cma_platform consumers (mijntoprecepten, karaat, etc.).
 * Each consumer ships a thin shim that calls this from a routed POST
 * handler. Example shim (project's pages/_deploy.php):
 *
 *   <?php
 *   \App\Library\DeployWebhook::handle();
 *
 * The webhook in GitHub Settings → Webhooks must:
 *   - POST to https://<host>/_deploy (or wherever the shim is routed)
 *   - Use the same DEPLOY_SECRET as the server's .env
 *   - Send the `push` event (no others)
 *   - Use `application/json` content type
 *
 * @package App\Library
 */

namespace App\Library;

class DeployWebhook
{
    /**
     * Handle a GitHub webhook request. Outputs plain-text response and
     * sets HTTP status code. Returns nothing — this is a terminal handler.
     *
     * @param array<string,mixed> $config Optional overrides:
     *   - 'site_root'    string  Project root. Default: Bootstrap::getRootDir() if available, else 4 levels up from this file.
     *   - 'log_file'     string  Where to write the deploy log. Default: <site_root>/logs/deploy.log
     *   - 'branch'       string  Branch to deploy. Default: env DEPLOY_BRANCH if set, else 'main'.
     *                            Lets a staging server deploy from `staging` while production
     *                            tracks `main`, with no code change.
     *   - 'branch_env'   string  Env var name to read for the branch. Default: 'DEPLOY_BRANCH'.
     *                            Set to '' to disable env lookup and force the literal 'branch' value.
     *   - 'secret_env'   string  Env var name holding the HMAC secret. Default: 'DEPLOY_SECRET'.
     *   - 'composer'     bool    Run composer install after git pull. Default: true.
     *   - 'composer_args' string Args to composer. Default: '--no-dev --optimize-autoloader --no-interaction'.
     *   - 'recycle'      bool    Touch web.config after success to recycle IIS app pool. Default: true.
     */
    public static function handle(array $config = []): void
    {
        $defaults = [
            'site_root'     => null,
            'log_file'      => null,
            'branch'        => 'main',
            'branch_env'    => 'DEPLOY_BRANCH',
            'secret_env'    => 'DEPLOY_SECRET',
            'composer'      => true,
            'composer_args' => '--no-dev --optimize-autoloader --no-interaction',
            'recycle'       => true,
        ];
        $cfg = array_merge($defaults, $config);

        if ($cfg['site_root'] === null) {
            $cfg['site_root'] = class_exists(Bootstrap::class) && Bootstrap::getRootDir() !== ''
                ? Bootstrap::getRootDir()
                : dirname(__DIR__, 4);
        }
        if ($cfg['log_file'] === null) {
            $cfg['log_file'] = $cfg['site_root'] . '/logs/deploy.log';
        }

        // Branch precedence: explicit caller arg with non-default value wins;
        // otherwise env var if set; otherwise the default ('main').
        // Detection of "explicit non-default" is done by the caller passing
        // 'branch' in $config — if absent, $cfg['branch'] === 'main' from
        // the merge, and we look at the env var.
        if (!array_key_exists('branch', $config) && $cfg['branch_env'] !== '') {
            $envBranch = (string)($_ENV[$cfg['branch_env']] ?? getenv($cfg['branch_env']) ?: '');
            if ($envBranch !== '') {
                $cfg['branch'] = $envBranch;
            }
        }

        header('Content-Type: text/plain; charset=utf-8');

        $log = static function (string $msg) use ($cfg): void {
            $dir = dirname($cfg['log_file']);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            @file_put_contents(
                $cfg['log_file'],
                '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n",
                FILE_APPEND
            );
        };

        $secret = (string)($_ENV[$cfg['secret_env']] ?? getenv($cfg['secret_env']) ?: '');
        if ($secret === '') {
            http_response_code(503);
            $log('REJECT: ' . $cfg['secret_env'] . ' not configured');
            echo "Deploy not configured.\n";
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo "Method not allowed.\n";
            return;
        }

        $payload   = file_get_contents('php://input') ?: '';
        $signature = (string)($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
        $expected  = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expected, $signature)) {
            http_response_code(403);
            $log('REJECT: signature mismatch from ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
            echo "Bad signature.\n";
            return;
        }

        $event = (string)($_SERVER['HTTP_X_GITHUB_EVENT'] ?? '');
        if ($event !== 'push') {
            http_response_code(200);
            $log("SKIP: event=$event (only 'push' deploys)");
            echo "Skipped (event=$event).\n";
            return;
        }

        $data    = json_decode($payload, true);
        $ref     = (string)($data['ref'] ?? '');
        $wantRef = 'refs/heads/' . $cfg['branch'];
        if ($ref !== $wantRef) {
            http_response_code(200);
            $log("SKIP: ref=$ref (only $wantRef deploys)");
            echo "Skipped (ref=$ref).\n";
            return;
        }

        $commit = substr((string)($data['after'] ?? ''), 0, 7);
        $pusher = (string)($data['pusher']['name'] ?? '?');
        $log("ACCEPTED: deploy $commit by $pusher (running async)");

        // GitHub webhook timeout is ~10s historically (now 30s). Our
        // deploy (git pull + composer install + post-install sync) often
        // runs 12-15s. If we wait for the full deploy before responding,
        // GitHub records the delivery as "EOF / failed" even though the
        // server-side work completes. Solution: respond 202 Accepted
        // immediately, then continue the deploy in the background.
        // Cook (or whoever's watching) sees full output in deploy.log.

        http_response_code(202);
        header('Content-Type: text/plain; charset=utf-8');
        $earlyMsg = "Accepted ($commit by $pusher). Running async — see logs/deploy.log for output.\n";
        header('Content-Length: ' . strlen($earlyMsg));
        echo $earlyMsg;

        // Flush response to GitHub. fastcgi_finish_request() (when
        // available — IIS FastCGI build supports it) cleanly closes the
        // connection so PHP can keep running. Falls back to manual ob
        // flush + connection_aborted-aware loop on plain-CLI builds.
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            while (ob_get_level() > 0) { @ob_end_flush(); }
            @flush();
        }

        // Belt-and-braces: keep running even if the connection closes
        // (which it just did) and give the deploy enough time even on
        // a slow composer fetch.
        ignore_user_abort(true);
        @set_time_limit(180);

        $failed = false;
        $out    = '';

        $run = static function (string $cmd) use ($cfg, $log, &$failed): string {
            $log("RUN: $cmd");
            $output = [];
            $exit   = 0;
            // 2>&1 to capture stderr; cd via cmd /c so chdir() isn't needed.
            $cwd = $cfg['site_root'];
            exec("cmd /c \"cd /d \"$cwd\" && $cmd 2>&1\"", $output, $exit);
            $text = implode("\n", $output);
            $log("EXIT: $exit\n--- output ---\n$text\n--- end ---");
            if ($exit !== 0) {
                $failed = true;
            }
            return $text;
        };

        $out .= "=== git pull ===\n" . $run('git pull --ff-only origin ' . escapeshellarg($cfg['branch'])) . "\n\n";

        if (!$failed && $cfg['composer']) {
            $composerCmd = self::resolveComposerCommand($cfg['site_root']);
            if ($composerCmd === null) {
                $log("FAIL: composer not found on PATH / standard locations / composer.phar in site root. Install composer or drop composer.phar into the site root.");
                $failed = true;
            } else {
                $out .= "=== composer install ($composerCmd) ===\n" . $run($composerCmd . ' install ' . $cfg['composer_args']) . "\n\n";
            }
        }

        if (!$failed && $cfg['recycle']) {
            @touch($cfg['site_root'] . '/web.config');
            $out .= "=== recycled app pool (touched web.config) ===\n";
        }

        if ($failed) {
            $log("FAIL: deploy $commit");
        } else {
            $log("OK: deploy $commit");
        }
    }

    /**
     * Find a usable composer command. On Windows IIS the deploy webhook
     * runs as the app pool identity (e.g. ApplicationPoolIdentity) which
     * doesn't share the interactive PATH, so a bare `composer` often
     * exits 127 even when composer is installed for the operator account.
     *
     * Tries in order:
     *   1. `composer` — bare command (works when on the app pool's PATH).
     *   2. C:\ProgramData\ComposerSetup\bin\composer.bat — standard
     *      Windows installer location (machine-wide).
     *   3. <site_root>\composer.phar — drop the phar into the site root
     *      as a last resort, runs via the local `php`.
     *
     * Returns the full command prefix to use, or null if nothing works.
     */
    private static function resolveComposerCommand(string $siteRoot): ?string
    {
        // 1. Bare `composer`. Probe via `where` (Windows) so we don't
        //    accidentally run something with side effects.
        @exec('cmd /c where composer 2>nul', $out1, $ex1);
        if ($ex1 === 0 && !empty($out1)) {
            return 'composer';
        }
        // 2. Common Windows install locations. Checked in order — first
        //    hit wins. C:\composer\ is our convention; ProgramData is
        //    the official installer's default.
        $candidates = [
            'C:\\composer\\composer.bat',
            'C:\\composer\\composer.phar',
            'C:\\ProgramData\\ComposerSetup\\bin\\composer.bat',
        ];
        foreach ($candidates as $path) {
            if (!is_file($path)) { continue; }
            if (str_ends_with($path, '.phar') && defined('PHP_BINARY') && PHP_BINARY !== '') {
                return '"' . PHP_BINARY . '" "' . $path . '"';
            }
            if (str_ends_with($path, '.bat')) {
                return '"' . $path . '"';
            }
        }
        // 3. composer.phar dropped into the site root. Use the same PHP
        //    binary that's running this code so PATH for `php` is also
        //    moot — PHP_BINARY is always absolute under FastCGI.
        $phar = $siteRoot . DIRECTORY_SEPARATOR . 'composer.phar';
        if (is_file($phar) && defined('PHP_BINARY') && PHP_BINARY !== '') {
            return '"' . PHP_BINARY . '" "' . $phar . '"';
        }
        return null;
    }
}
