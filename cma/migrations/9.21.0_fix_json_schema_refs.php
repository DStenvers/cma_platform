<?php
/**
 * Migration 9.21.0: repair the `$schema` reference in the site's JSON config
 *
 * A JSON `$schema` value resolves against the referencing file's OWN directory.
 * The form/datastore generators used to write a fixed literal ('../schema/…'),
 * which is only correct at one nesting depth — so every definition written to
 * the site's assets/forms/ (two levels from cma/config/schema/, not one) points
 * at a path that does not exist. Editors then silently fall back to no
 * validation and no IntelliSense: the definitions look fine and nothing warns.
 *
 * Two schema files were also renamed with the configs that use them
 * (app -> cma_branding, reports -> cma_reports), leaving those references
 * dangling as well.
 *
 * This walks the directories where CMA-owned JSON lives, and for every file
 * whose `$schema` does not resolve, rewrites it to the correct relative path —
 * but only when a schema of that name genuinely exists in cma/config/schema/.
 * An unrecognised reference is reported and left alone.
 *
 * Only the quoted `$schema` value is substituted, so hand-formatting (tabs,
 * key order, trailing newline) survives untouched. Idempotent: a file whose
 * reference already resolves is skipped.
 */

use App\Library\File;

// $siteRoot may be pre-set by a test harness; otherwise derive it from the
// migration location (site root when the runner defines MIGRATION_RUNNING,
// else the cma/ parent).
if (!isset($siteRoot)) {
    $siteRoot = defined('MIGRATION_RUNNING') ? dirname(__DIR__, 2) : dirname(__DIR__);
}

$schemaDir = $siteRoot . '/cma/config/schema';
if (!is_dir($schemaDir)) {
    // A pre-sync site (or a test root without the cma/ tree): nothing to point at.
    echo "  Overgeslagen: {$schemaDir} bestaat niet\n";
    return;
}

/**
 * Targets that are no longer the right file to point at.
 *
 * The first two were renamed along with the config that references them. The
 * third is a reference that never named a schema at all: contentblocks.json
 * pointed at the form DEFINITION of the same name, which exists — so it
 * resolved, and looked healthy, while giving the editor a document that cannot
 * validate anything.
 */
$renamed = [
    'app.schema.json'     => 'cma_branding.schema.json',
    'reports.schema.json' => 'cma_reports.schema.json',
    'contentblocks.json'  => 'contentblocks.schema.json',
];

$directories = [
    'data',
    'assets/forms',
    'assets/datastores',
    'assets/contentblocks',
    'cma',
    'cma/config',
    'cma/assets/forms/definitions',
    'cma/assets/contentblocks',
];

$fixed = 0;
$skipped = 0;
$unknown = [];

foreach ($directories as $relDir) {
    $dir = $siteRoot . '/' . $relDir;
    if (!is_dir($dir)) {
        continue;
    }

    foreach (glob($dir . '/*.json') as $path) {
        $src = (string)file_get_contents($path);

        // The `$schema` value as written, before any JSON unescaping — that is
        // exactly the substring to replace.
        if (!preg_match('/"\$schema"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $src, $m)) {
            continue;
        }
        $ref = $m[1];

        // Remote schemas are somebody else's problem.
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $ref)) {
            continue;
        }

        $name = basename(str_replace('\\', '/', $ref));
        $name = $renamed[$name] ?? $name;

        if (!is_file($schemaDir . '/' . $name)) {
            $unknown[] = $relDir . '/' . basename($path) . ' -> ' . $ref;
            continue;
        }

        $correct = File::relativePath($dir, $schemaDir . '/' . $name);
        if ($correct === $ref) {
            $skipped++;
            continue;
        }

        // Replace only inside the matched `$schema` value, so a path that also
        // occurs elsewhere in the file (a listQuery, a description) is untouched.
        $updated = preg_replace(
            '/("\$schema"\s*:\s*")(?:[^"\\\\]|\\\\.)*(")/',
            '${1}' . str_replace(['\\', '$'], ['\\\\', '\\$'], $correct) . '${2}',
            $src,
            1
        );

        if ($updated === null || $updated === $src) {
            $unknown[] = $relDir . '/' . basename($path) . ' (vervanging mislukt)';
            continue;
        }

        file_put_contents($path, $updated);
        $fixed++;
    }
}

echo "  Hersteld: {$fixed} \$schema-verwijzing(en)\n";
echo "  Al correct: {$skipped}\n";

if ($unknown !== []) {
    echo "  Niet herkend (ongemoeid gelaten):\n";
    foreach ($unknown as $line) {
        echo "    - {$line}\n";
    }
}
