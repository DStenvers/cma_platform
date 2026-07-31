<?php
/**
 * Migration 9.22.0: remove the orphaned <site>/config/constants.php
 *
 * The constants a site needs come from library/config/constants.php, which the
 * Installer syncs with the package. A site-root config/constants.php predates that
 * and is nothing but a leftover: migration 9.9.0 was meant to move it to
 * library/constants.php, but the platform-provided library/config/constants.php
 * superseded that target, so on sites where 9.9.0 did not complete the old file
 * simply stayed behind.
 *
 * It is not loaded by anything — _bootstrap.php does not name it — so it drifts:
 * on the site this was found on it had grown to 371 lines with the same constant
 * block repeated twelve times. A dead file that looks authoritative is worse than
 * no file: the next person to change a constant may well edit this one.
 *
 * Two conditions before deleting, so this is safe on any consumer:
 *   1. library/config/constants.php must exist — that is the proof the real
 *      constants are in place.
 *   2. Nothing in the site's own PHP may require/include config/constants.php.
 *      A site that does load it keeps the file and gets a message instead; there
 *      the file is not an orphan and removing it would break the boot.
 *
 * config/ itself stays: page_roles.php, page_params.php and file_extensions.php
 * live there.
 *
 * Idempotent: no-op once the file is gone.
 */

// $siteRoot may be pre-set by a test harness; otherwise derive it from the migration
// location (site root when the runner defines MIGRATION_RUNNING, else the cma/ parent).
if (!isset($siteRoot)) {
    $siteRoot = defined('MIGRATION_RUNNING') ? dirname(__DIR__, 2) : dirname(__DIR__);
}

$wees = $siteRoot . '/config/constants.php';
if (!is_file($wees)) {
    echo "  Overgeslagen: config/constants.php bestaat niet (al opgeruimd)\n";
    return;
}

if (!is_file($siteRoot . '/library/config/constants.php')) {
    echo "  Overgeslagen: library/config/constants.php ontbreekt — zonder die bron is\n"
       . "  config/constants.php mogelijk wél de enige plek waar de constanten staan.\n";
    return;
}

/** Laadt de site dit bestand ergens zelf? Dan is het geen wees. */
$laders = [];
$overslaan = ['vendor', 'node_modules', '.git', '.deprecated', 'cma', '.tests', 'tests', 'uploads'];
$it = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($siteRoot, FilesystemIterator::SKIP_DOTS),
        static function ($huidig) use ($overslaan) {
            return !$huidig->isDir() || !in_array($huidig->getFilename(), $overslaan, true);
        }
    )
);
foreach ($it as $bestand) {
    if (!$bestand->isFile() || !preg_match('/\.(php|inc)$/', $bestand->getFilename())) {
        continue;
    }
    $src = (string) @file_get_contents($bestand->getPathname());
    // Alleen een echte require/include telt; een vermelding in commentaar niet.
    if (preg_match('#(?<![_a-zA-Z])(require|include)(_once)?\s*\(?\s*[^;]*config/constants\.php#i', $src)) {
        foreach (explode("\n", $src) as $nr => $regel) {
            $kaal = ltrim($regel);
            if ($kaal === '' || $kaal[0] === '*' || strpos($kaal, '//') === 0) {
                continue;
            }
            if (preg_match('#(?<![_a-zA-Z])(require|include)(_once)?\s*\(?\s*[^;]*config/constants\.php#i', $regel)) {
                $laders[] = str_replace($siteRoot . DIRECTORY_SEPARATOR, '', $bestand->getPathname()) . ':' . ($nr + 1);
            }
        }
    }
}

if ($laders !== []) {
    echo "  Overgeslagen: config/constants.php wordt hier wél geladen:\n";
    foreach (array_slice($laders, 0, 5) as $l) {
        echo "    - {$l}\n";
    }
    return;
}

$regels = count(file($wees) ?: []);
if (@unlink($wees)) {
    echo "  Verwijderd: config/constants.php ({$regels} regels) — niet geladen, en de\n"
       . "  constanten komen uit library/config/constants.php.\n";
} else {
    echo "  MISLUKT: config/constants.php kon niet worden verwijderd (rechten?).\n";
}
