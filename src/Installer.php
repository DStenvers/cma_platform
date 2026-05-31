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
    ];

    /**
     * Template files that are copied to the project root only if they don't exist.
     * These are one-time setup files.
     */
    private const TEMPLATE_FILES = [
        '_bootstrap.php.template'         => '_bootstrap.php',
        '_bootstrap_wrapper.php.template' => '_bootstrap_wrapper.php',
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
        foreach (self::REMOVED_PATHS as $relPath) {
            $abs = $projectRoot . '/' . $relPath;
            if (is_file($abs)) {
                @unlink($abs);
                $io->write('  - removed (retired): ' . $relPath);
            }
        }

        // 1. Sync library → /library/
        $librarySrc = $platformDir . '/library';
        $libraryDest = $projectRoot . '/library';
        if (is_dir($librarySrc)) {
            self::syncDirectory($librarySrc, $libraryDest, [], $io);
            $io->write('  - library/ synced');
        }

        // 2. Sync CMA → /cma/ (with protected configs)
        $cmaSrc = $platformDir . '/cma';
        $cmaDest = $projectRoot . '/cma';
        if (is_dir($cmaSrc)) {
            self::syncDirectory($cmaSrc, $cmaDest, self::PROTECTED_PATHS, $io);
            $io->write('  - cma/ synced');
        }

        // 3. Sync module → /module/
        $moduleSrc = $platformDir . '/module';
        $moduleDest = $projectRoot . '/module';
        if (is_dir($moduleSrc)) {
            self::syncDirectory($moduleSrc, $moduleDest, [], $io);
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

        // 8. Write manifest for tracking
        self::writeManifest($projectRoot, $platformDir);

        $io->write('<info>stenversonline/platform: sync complete</info>');
    }

    /**
     * Recursively sync a source directory to a destination.
     * Skips protected paths. Overwrites everything else.
     *
     * @param string $src Source directory
     * @param string $dest Destination directory
     * @param string[] $protectedPaths Relative paths to skip
     * @param mixed $io Composer IO interface (optional)
     */
    private static function syncDirectory(string $src, string $dest, array $protectedPaths = [], $io = null): void
    {
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
                    throw new \RuntimeException("Installer::syncDirectory could not create $destPath");
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

                // Skip node_modules, .git, vendor directories
                if (strpos($normalizedRelative, 'node_modules/') !== false ||
                    strpos($normalizedRelative, '.git/') !== false ||
                    strpos($normalizedRelative, 'vendor/') !== false) {
                    continue;
                }

                self::copyFile($item->getPathname(), $destPath);
            }
        }
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
