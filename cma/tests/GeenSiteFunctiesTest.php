<?php
/**
 * Guards that platform code never calls a function only a consumer site defines.
 *
 * Run with: php cma/tests/TestRunner.php GeenSiteFunctiesTest
 *
 * hasValue() is a global from mijnRINO (src/fn_hasvalue.inc, made by its ASP-to-PHP
 * converter). It slipped into library/classes/class_table.inc (row-href on lib-table) and
 * cma/tools/tools_serverinfo.php. On mijnRINO that works by accident; on every other site
 * those pages die with "Call to undefined function hasValue()". Platform code uses plain
 * PHP for the check: (string)($x ?? '') !== ''.
 *
 * Add a name to SITE_FUNCTIONS when another site-only global turns up in platform code.
 */

require_once __DIR__ . '/TestRunner.php';

class GeenSiteFunctiesTest extends TestCase
{
    /** Globals that consumer sites define and the platform does not. */
    private const SITE_FUNCTIONS = ['hasValue'];

    /** Platform trees that are shipped to sites, relative to the platform root. */
    private const DIRS = ['src', 'library', 'cma'];

    public function testPlatformRoeptGeenSiteFunctiesAan(): void
    {
        $root = dirname(__DIR__, 2);
        $treffers = [];
        foreach (self::DIRS as $dir) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                $pad = str_replace('\\', '/', $file->getPathname());
                if (!preg_match('/\.(php|inc)$/', $pad) || preg_match('#/(node_modules|vendor|tests)/#', $pad)) {
                    continue;
                }
                foreach (file($pad) ?: [] as $nr => $regel) {
                    foreach (self::SITE_FUNCTIONS as $fn) {
                        // a call, not a method (->hasValue) or static (::hasValue) of our own
                        if (preg_match('/(?<![\w>:$])' . $fn . '\s*\(/', $regel) && !preg_match('/function\s+' . $fn . '\b/', $regel)) {
                            $treffers[] = substr($pad, strlen($root) + 1) . ':' . ($nr + 1) . '  ' . trim($regel);
                        }
                    }
                }
            }
        }
        $this->assertEquals([], $treffers, "Platformcode roept een functie van een site aan:\n  " . implode("\n  ", $treffers));
    }
}
