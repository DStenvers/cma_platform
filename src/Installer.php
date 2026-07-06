<?php
/**
 * Composer Installer for stenversonline/platform
 *
 * Runs after `composer install` and `composer update` to copy
 * web-accessible files to the correct project directories.
 *
 * Usage in project composer.json:
 * {
 *     "scripts": {
 *         "post-install-cmd": "App\\Library\\Installer::postInstall",
 *         "post-update-cmd": "App\\Library\\Installer::postUpdate"
 *     }
 * }
 */

namespace App\Library;

use Composer\Script\Event;

class Installer
{
    /**
     * Files/directories that should NEVER be overwritten in the project.
     * These contain project-specific configuration.
     */
    private const PROTECTED_PATHS = [
        'data/app.json',
        'data/databases.json',
        'data/menu.json',
        'data/reports.json',
    ];

    /**
     * Paths (relative to project root) of files that were once shipped by
     * this package but have since been retired. syncDirectory() only copies
     * from source → dest; without an explicit list a consumer site keeps
     * the dead file forever after `composer update`. Add a row here when
     * you delete a file from the package and want it gone from existing
     * installs. Safe to leave entries here long-term — the cleanup just
     * no-ops once the file isn't there anymore.
     */
    private const REMOVED_PATHS = [
        'cma/tools/llm_models.php',
        // Renumbered in v1.28.35: these two migrations were never registered in
        // config/migrations.json, so the high-water-mark runner never applied
        // them (leaving tblCMAMarketingUrl / api_call_log missing on sites past
        // 9.13.0). Re-issued as 9.14.0 / 9.15.0 (above target) so they become
        // pending everywhere. Drop the old-named orphan files from sites.
        'cma/migrations/9.10.0_create_tblcmamarketingurl.php',
        'cma/migrations/9.11.0_create_api_call_log.php',
        // Retired: the legacy IE/ActiveX image-upload wizard (an <OBJECT> COM
        // control + IE-only event scripting) and its POST target. Dead in any
        // modern browser and reached by nothing; superseded by the file-browser
        // wizard (cma/wizards/file-browser.php) and imageupload_crop.php.
        'cma/imageupload.php',
        'cma/imageupload_action.php',
        // Markdown docs retired in v1.16.0 (Phase 1 of the in-CMA
        // documentation hub at cma/tools/documentation.php). Their
        // content was inlined into the topics there. See CLAUDE.md
        // "No new .md documentation files" rule.
        'cma/docs/iis-setup.md',
        'cma/docs/logging.md',
        // Phase 2 retirements (v1.17.0)
        'cma/docs/architecture_review.md',
        'cma/docs/json.md',
        'cma/docs/forms.md',
        'cma/docs/api-reference.md',
        'cma/docs/menuicons.md',
        // Retired in v1.22.0: BOTH the standalone single-file webhook and the
        // framework webhook (cma/tools/deploy_webhook.php → DeployWebhook) are
        // superseded by the one-and-only site-root recovery hatch /deploy.php
        // (ROOT_SYNCED_FILES), which folds in their full feature set. Re-point
        // any GitHub webhook still on these URLs to /deploy.php before this
        // lands, or auto-deploy 404s on that site. (The DeployWebhook class
        // under src/helpers/ lives in vendor/ — composer drops it, so it needs
        // no REMOVED_PATHS entry.)
        'cma/tools/deploy_webhook_standalone.php',
        'cma/tools/deploy_webhook.php',
        // Retired in v1.22.3: the /cma/tools/ deploy-status endpoint sat under
        // the gitignored /cma/ tree — it 404s during exactly the botched
        // deploy you'd want to inspect. The git-tracked site-root twin
        // /deploy_status.php (ROOT_SYNCED_FILES) is the single status endpoint.
        'cma/tools/deploy_status.php',
        // Removed: migration 2.2.0 (JavaScript error logging table) targeted the
        // DEPRECATED_rep database, which is not part of the platform config. The
        // migration and its SQL script were dropped; this removes the synced copy.
        'cma/migrations/sql/2.2.0_javascript_errors.sql',
        // Removed: duplicate migrations.json. The single source of truth is
        // cma/config/migrations.json (read by MigrationService, Bootstrap and
        // ConfigLoader). 9.9.0 used to move it here, splitting it from the file
        // the runner reads; that move was dropped and this stale copy deleted.
        'cma/migrations/migrations.json',
        // Removed: lib_htmleditor.inc defined a single dead function
        // (lib_HTMLEditorInit) with no call site anywhere, plus a legacy
        // CreateFKEditor JS duplicate long superseded by cma/assets/js/cma.js
        // and cma/webcomponents/cma-htmledit.js. Its require in library.inc was
        // dropped; this removes the synced copy from consumer sites.
        'library/lib_htmleditor.inc',
        // Windows junk file accidentally committed under the Linearicons font dir.
        // It was synced to consumers and its system/hidden/readonly attributes made
        // copy() fail ("Permission denied"), aborting the whole post-update sync.
        // Untracked now + skipped by syncDirectory; this cleans the synced copy.
        // (@unlink is best-effort, so a system-attribute file that won't delete is
        // a harmless no-op rather than a new failure.)
        'library/fonts/Linearicons/SVG/desktop.ini',
        // Retired in v1.28.45: legacy "image/file maintenance" tool family that
        // walked the pre-JSON tblForms/tblControls schema plus on-disk image
        // folders. All superseded by tools_db_consistency.php (the only surfaced
        // one) + its POST target tools_consistency_picture_delete.php. These were
        // unsurfaced in the tools menu, self-declared deprecated, and reachable by
        // nothing. tools_contentblocks.php is superseded by form.php?form=contentblocks.
        'cma/tools/tools_missing_files.php',
        'cma/tools/tools_missing_pictures.php',
        'cma/tools/tools_show_pictures.php',
        'cma/tools/tools_set_picture_sizes.php',
        'cma/tools/tools_picture_analyse.php',
        'cma/tools/tools_picture_analyse_delete.php',
        'cma/tools/tools_contentblocks.php',
        // Relocated in v1.28.95: the general error handler's assets moved under
        // library/assets/ (was library/error-handler.js[.min.js] and
        // library/css/errorhandler.css). New locations: library/assets/js/ and
        // library/assets/css/. Without these removals the old copies linger and,
        // for errorhandler.css, a stale file at the old path could still be
        // hit by any not-yet-updated reference.
        'library/error-handler.js',
        'library/error-handler.min.js',
        'library/css/errorhandler.css',
    ];

    /**
     * Platform-owned files copied to the project root and ALWAYS overwritten
     * (unlike TEMPLATE_FILES, which are copy-if-absent). Use this for shared
     * ops/tooling that must stay in lock-step with the platform AND must live
     * at the site root rather than inside the gitignored /cma/ tree.
     *
     * deploy.php + deploy_status.php are the deploy recovery hatch + its
     * status endpoint: git-tracked root files that survive a broken /cma/
     * sync (the failure that historically stranded deploys, because the old
     * webhook lived under /cma/tools/). Consumers commit them; this keeps
     * them current on every `composer update`. See DeployHealth + the
     * templates/deploy*.php.template headers.
     *
     * Map of: <template under templates/> => <target relative to site root>.
     */
    private const ROOT_SYNCED_FILES = [
        'deploy.php.template'        => 'deploy.php',
        'deploy_status.php.template' => 'deploy_status.php',
    ];

    /**
     * Template files that are copied to the project root only if they don't exist.
     * These are one-time setup files.
     */
    private const TEMPLATE_FILES = [
        '_bootstrap.php.template'         => '_bootstrap.php',
        '_bootstrap_wrapper.php.template' => '_bootstrap_wrapper.php',
        'maintenance.php.template'        => 'maintenance.php',
        'web.config.template'             => 'web.config',
        'app.php.template'                => 'app.php',
        'global.asa.php.template'         => 'global.asa.php',
        '.env.example'                    => '.env.example',
        'cma.css.template'                => 'assets/css/cma.css',
    ];

    /**
     * Run after composer install.
     */
    public static function postInstall(Event $event): void
    {
        self::run($event);
    }

    /**
     * Run after composer update.
     */
    public static function postUpdate(Event $event): void
    {
        self::run($event);
    }

    /**
     * Main installation logic.
     */
    private static function run(Event $event): void
    {
        $io = $event->getIO();
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $projectRoot = dirname($vendorDir); // vendor is always one level deep
        $platformDir = $vendorDir . '/stenversonline/platform';

        if (!is_dir($platformDir)) {
            $io->write('<warning>stenversonline/platform not found in vendor — skipping install</warning>');
            return;
        }

        $io->write('<info>stenversonline/platform: syncing shared files...</info>');

        // 0. Clean up files that have been retired from the package. Without
        //    this step, composer update copies the new files but leaves the
        //    old ones lingering on the consumer site. Runs before sync so
        //    a re-added file with the same path isn't accidentally wiped.
        foreach (self::cleanRemovedPaths($projectRoot) as $relPath) {
            $io->write('  - removed (retired): ' . $relPath);
        }

        // Per-file copy failures are collected across all three directory syncs
        // and reported together at the very end (step 9). A single unwritable
        // file no longer aborts the sync, so critical top-level files like
        // library/library.inc always get overwritten even if some junk/locked
        // file elsewhere in the tree fails.
        $syncErrors = [];

        // 1. Sync library → /library/
        $librarySrc = $platformDir . '/library';
        $libraryDest = $projectRoot . '/library';
        if (is_dir($librarySrc)) {
            $syncErrors = array_merge($syncErrors, self::syncDirectory($librarySrc, $libraryDest, [], $io));
            $io->write('  - library/ synced');
        }

        // 2. Sync CMA → /cma/ (with protected configs)
        $cmaSrc = $platformDir . '/cma';
        $cmaDest = $projectRoot . '/cma';
        if (is_dir($cmaSrc)) {
            $syncErrors = array_merge($syncErrors, self::syncDirectory($cmaSrc, $cmaDest, self::PROTECTED_PATHS, $io));
            $io->write('  - cma/ synced');
        }

        // 3. Sync module → /module/
        $moduleSrc = $platformDir . '/module';
        $moduleDest = $projectRoot . '/module';
        if (is_dir($moduleSrc)) {
            $syncErrors = array_merge($syncErrors, self::syncDirectory($moduleSrc, $moduleDest, [], $io));
            $io->write('  - module/ synced');
        }

        // 5. Copy template files (only if they don't exist in project)
        $templatesDir = $platformDir . '/templates';
        if (is_dir($templatesDir)) {
            foreach (self::TEMPLATE_FILES as $template => $target) {
                $src = $templatesDir . '/' . $template;
                $dest = $projectRoot . '/' . $target;
                if (file_exists($src) && !file_exists($dest)) {
                    self::copyFile($src, $dest);
                    $io->write("  - created $target (from template)");
                }
            }

            // 5a. Root-synced ops files — ALWAYS overwritten so platform
            //     fixes to the deploy recovery hatch reach every consumer.
            foreach (self::ROOT_SYNCED_FILES as $template => $target) {
                $src  = $templatesDir . '/' . $template;
                $dest = $projectRoot . '/' . $target;
                if (file_exists($src)) {
                    self::copyFile($src, $dest);
                    $io->write("  - synced $target (root ops file)");
                }
            }
        }

        // 5b. Ensure the parent web.config carries the CMA friendly-URL
        //     rewrite rules. Uses the same fail-safe patch/validate logic as
        //     migration 9.9.0 (shared helper): simplexml-check, XML
        //     well-formedness, duplicate-name + PCRE-regex validation, backup,
        //     atomic temp-write + rename, read-back, rollback. Idempotent via
        //     marker, so it's safe to run on every update. The appcmd +
        //     live-HTTP smoke-test stay migration-only (no IIS/HTTP context at
        //     composer time). Never throws — a routing-rule failure must not
        //     break the composer run.
        $parentWebConfig = $projectRoot . '/web.config';
        if (is_file($parentWebConfig)) {
            // Defensive load in case the package PSR-4 autoload isn't warm yet.
            if (!class_exists(WebConfigCmaRoutes::class)) {
                require __DIR__ . '/helpers/WebConfigCmaRoutes.php';
            }
            $res = WebConfigCmaRoutes::applyToParent($parentWebConfig);
            foreach ($res['messages'] as $line) {
                $io->write('  - web.config: ' . $line);
            }
            foreach ($res['errors'] as $line) {
                $io->write('  - <warning>web.config: ' . $line . '</warning>');
            }
        }

        // 6. Copy _bootstrap_constants.inc (from platform)
        $constantsSrc = $platformDir . '/templates/_bootstrap_constants.inc';
        $constantsDest = $projectRoot . '/_bootstrap_constants.inc';
        if (file_exists($constantsSrc)) {
            self::copyFile($constantsSrc, $constantsDest);
        }

        // 7. Ensure writable directories exist
        foreach (['sessions', 'cache', 'logs'] as $dir) {
            $path = $projectRoot . '/' . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
                $io->write("  - created $dir/");
            }
        }

        // 7a. Clear regenerable disk caches (minified JS/CSS, cached form
        //     definitions) so freshly-synced code/assets take effect on the next
        //     request instead of waiting for TTLs. These are file-based and
        //     shared with the web process, so clearing them here (composer CLI)
        //     IS effective. NOTE: this can NOT clear the web server's OPcache or
        //     APCu — those live in the FastCGI worker, not this CLI run — so an
        //     app-pool recycle / iisreset is still required for PHP code changes.
        foreach (self::clearRegenerableCaches($projectRoot) as $line) {
            $io->write('  - ' . $line);
        }

        // 7b. Touch the root web.config to trigger an IIS application-pool
        //     recycle. IIS watches web.config; bumping its mtime restarts the
        //     worker (and its PHP FastCGI children) — the only way to flush the
        //     in-process OPcache/APCu that a file sync alone can't reach. This
        //     makes `composer update` self-applying, so a manual iisreset is no
        //     longer required for changed PHP to take effect. Guarded on
        //     existence so we never create an (empty, site-breaking) web.config.
        $rootWebConfig = $projectRoot . '/web.config';
        if (is_file($rootWebConfig) && @touch($rootWebConfig)) {
            $io->write('  - touched web.config (app-pool recycle → flushes OPcache/APCu)');
        }

        // 8. Write manifest for tracking
        self::writeManifest($projectRoot, $platformDir);

        // 9. If any file could not be copied, the sync ran to completion (so
        //    the site got as much of the new version as possible) but the
        //    result is a mixed-version state. Fail loudly — an unmissable
        //    non-zero composer exit — so the operator fixes the underlying
        //    permission/disk/lock problem and re-runs, rather than discovering
        //    a half-updated site at runtime.
        if (!empty($syncErrors)) {
            $io->write('  - <warning>' . count($syncErrors) . ' file(s) could NOT be synced:</warning>');
            foreach ($syncErrors as $err) {
                $io->write('    <warning>' . $err . '</warning>');
            }
            throw new \RuntimeException(
                'stenversonline/platform: sync completed with ' . count($syncErrors)
                . ' file error(s) — the site is on MIXED versions. Fix the file '
                . 'permission/disk/lock problem listed above and re-run "composer '
                . 'update stenversonline/platform".'
            );
        }

        $io->write('<info>stenversonline/platform: sync complete</info>');
    }

    /**
     * Clear the platform's regenerable disk caches (minified JS/CSS in
     * cache/cma/minify, cached form definitions in cache/cma/forms). They are
     * file-based and shared with the web process, so clearing them from the
     * composer CLI is effective — the web process simply regenerates them.
     * Does NOT (cannot) clear the FastCGI worker's OPcache/APCu.
     *
     * @return string[] Log lines describing what was cleared.
     */
    private static function clearRegenerableCaches(string $projectRoot): array
    {
        $log = [];
        foreach (['cache/cma/minify', 'cache/cma/forms'] as $rel) {
            $dir = $projectRoot . '/' . $rel;
            if (!is_dir($dir)) {
                continue;
            }
            $count = self::deleteDirContents($dir);
            if ($count > 0) {
                $log[] = "cleared $rel ($count files)";
            }
        }
        return $log;
    }

    /**
     * Recursively delete everything inside $dir, keeping $dir itself (so its
     * permissions are preserved). Returns the number of files removed.
     */
    private static function deleteDirContents(string $dir): int
    {
        $removed = 0;
        $items = @scandir($dir);
        if ($items === false) {
            return 0;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $removed += self::deleteDirContents($path);
                @rmdir($path);
            } elseif (@unlink($path)) {
                $removed++;
            }
        }
        return $removed;
    }

    /**
     * Recursively sync a source directory to a destination.
     * Delete files listed in REMOVED_PATHS from the consumer site root.
     * Public for testability — `run()` is composer-bound, this isn't.
     *
     * Each REMOVED_PATHS entry is a relative path under $projectRoot;
     * existing files are unlinked, missing ones are no-ops (idempotent).
     * Returns the list of paths that were actually removed so the caller
     * can log them. Symlinks are left alone — only regular files match
     * is_file().
     *
     * @param string $projectRoot Absolute path to the consumer site root.
     * @return string[] Relative paths that were actually removed.
     */
    public static function cleanRemovedPaths(string $projectRoot): array
    {
        $removed = [];
        foreach (self::REMOVED_PATHS as $relPath) {
            $abs = $projectRoot . '/' . $relPath;
            if (is_file($abs) && @unlink($abs)) {
                $removed[] = $relPath;
            }
        }
        return $removed;
    }

    /**
     * Skips protected paths. Overwrites everything else.
     *
     * Resilient by design: a single file that can't be copied (locked, bad
     * permissions, disk full, an OS-junk file with system attributes) is
     * recorded and skipped, NOT allowed to abort the whole sync. Aborting on
     * the first bad file used to strand critical top-level files — e.g. a
     * `desktop.ini` under library/fonts/ failing left the freshly-retired
     * `lib_htmleditor.inc` deleted but the top-level `library.inc` (which
     * required it) never overwritten, so every request fatal-errored. Best-
     * effort completeness means library.inc always gets copied; the caller
     * still fails loudly at the end if any file was skipped.
     *
     * @param string $src Source directory
     * @param string $dest Destination directory
     * @param string[] $protectedPaths Relative paths to skip
     * @param mixed $io Composer IO interface (optional)
     * @return string[] One message per file that could not be synced (empty on full success).
     */
    private static function syncDirectory(string $src, string $dest, array $protectedPaths = [], $io = null): array
    {
        $errors = [];
        if (!is_dir($dest) && !mkdir($dest, 0755, true) && !is_dir($dest)) {
            // Failing silently here meant `composer update` could land
            // half a sync — destination dir missing, no files copied,
            // no exception, exit 0 — and the consumer site appeared
            // to update successfully. Fail loud so the operator sees
            // the permissions/disk problem in the composer output.
            throw new \RuntimeException("Installer::syncDirectory could not create $dest");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            // Calculate relative path from source root
            $relativePath = substr($item->getPathname(), strlen($src) + 1);
            $destPath = $dest . '/' . $relativePath;

            // Normalize path separators for comparison
            $normalizedRelative = str_replace('\\', '/', $relativePath);

            // Check if this path is protected
            $isProtected = false;
            foreach ($protectedPaths as $protected) {
                // Protected paths are relative to project root, but we're syncing
                // a subdirectory. Extract the relevant part.
                $protectedParts = explode('/', $protected);
                $destBasename = basename($dest);

                // If the protected path starts with our destination folder name,
                // compare the remainder
                if ($protectedParts[0] === $destBasename) {
                    $protectedRelative = implode('/', array_slice($protectedParts, 1));
                    if ($normalizedRelative === $protectedRelative) {
                        $isProtected = true;
                        break;
                    }
                }
            }

            if ($item->isDir()) {
                if (!is_dir($destPath) && !mkdir($destPath, 0755, true) && !is_dir($destPath)) {
                    // Non-fatal: record and continue. copyFile() recreates the
                    // parent dir per file anyway, so a failed dir here doesn't
                    // strand siblings elsewhere in the tree.
                    $errors[] = "could not create directory: $normalizedRelative";
                }
            } else {
                // Skip protected files that already exist
                if ($isProtected && file_exists($destPath)) {
                    if ($io) {
                        $io->write("  - SKIPPED (protected): $normalizedRelative");
                    }
                    continue;
                }

                // Skip .template files (they are handled separately)
                if (substr($item->getFilename(), -9) === '.template') {
                    continue;
                }

                // Skip OS-generated junk (Windows desktop.ini / Thumbs.db, macOS
                // .DS_Store). These are never platform content, and the Windows
                // ones carry system/hidden/readonly attributes, so copy() over an
                // existing target fails with "Permission denied" — which, because
                // copyFile() throws, aborted the ENTIRE post-update sync mid-way.
                if (preg_match('/^(desktop\.ini|Thumbs\.db|\.DS_Store)$/i', $item->getFilename())) {
                    continue;
                }

                // Skip node_modules, .git, vendor directories
                if (strpos($normalizedRelative, 'node_modules/') !== false ||
                    strpos($normalizedRelative, '.git/') !== false ||
                    strpos($normalizedRelative, 'vendor/') !== false) {
                    continue;
                }

                // Best-effort: one unwritable file must not abort the sync and
                // leave the site on mixed versions. Record and keep going; the
                // caller reports every failure and fails the run loudly at the end.
                try {
                    self::copyFile($item->getPathname(), $destPath);
                } catch (\Throwable $e) {
                    $errors[] = $normalizedRelative . ': ' . $e->getMessage();
                    if ($io) {
                        $io->write("  - <warning>skipped (copy failed): $normalizedRelative</warning>");
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Copy a single file, creating parent directories as needed.
     */
    private static function copyFile(string $src, string $dest): void
    {
        $dir = dirname($dest);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Installer::copyFile could not create parent dir $dir");
        }
        if (!copy($src, $dest)) {
            // Disk full / permission denied / source vanished. Silent
            // copy failure mid-sync left consumer sites with mixed
            // platform versions — surface the error so the operator
            // sees it in the composer-update output.
            throw new \RuntimeException("Installer::copyFile failed: $src -> $dest");
        }
    }

    /**
     * Write a manifest file to track what was installed.
     * This helps identify which files came from the platform vs. project-specific.
     */
    private static function writeManifest(string $projectRoot, string $platformDir): void
    {
        $version = 'unknown';

        // Try to get version from Composer
        if (class_exists('Composer\InstalledVersions')) {
            try {
                $version = \Composer\InstalledVersions::getPrettyVersion('stenversonline/platform') ?? 'unknown';
            } catch (\Exception $e) {
                // Ignore
            }
        }

        $manifest = [
            'package' => 'stenversonline/platform',
            'version' => $version,
            'synced_at' => date('Y-m-d H:i:s'),
            'directories' => ['library/', 'cma/', 'module/'],
            'protected_configs' => self::PROTECTED_PATHS,
        ];

        $dest = $projectRoot . '/.platform-manifest.json';
        file_put_contents($dest, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }
}
