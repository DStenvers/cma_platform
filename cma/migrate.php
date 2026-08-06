<?php
/**
 * Migratieloper voor de opdrachtregel — de kant die de deployer gebruikt.
 *
 * Dezelfde MigrationService als het beheerscherm (cma/tools/tools_migrations.php),
 * maar zonder scherm en zonder sessie: één proces, één exitcode. Dat laatste is
 * het hele punt. deploy.php draait dit ná `composer update` (zodat nieuwe
 * migratiescripts al op schijf staan) en vóór de app-pool-recycle; een exitcode
 * ongelijk nul laat de deploy falen, en dan blijft de oude code draaien in
 * plaats van nieuwe code op een niet-gemigreerde database.
 *
 * Alleen CLI. Via de webserver is er niets te halen: dit script kent geen
 * inlog en zou anders een ongeauthenticeerde schrijfactie op de database zijn.
 * Het beheerscherm is de weg via de browser.
 *
 *   php cma/migrate.php                 openstaande migraties toepassen
 *   php cma/migrate.php --check         alleen tonen wat openstaat (past niets toe)
 *   php cma/migrate.php --no-backup     zonder databaseback-up vooraf
 *
 * Exitcodes: 0 = niets te doen of alles toegepast, 1 = migratie mislukt,
 * 2 = verkeerd aangeroepen. Met --check: 0 = niets openstaand, 1 = er staat
 * werk open (bruikbaar als poort in een eigen pijplijn).
 */

use Cma\Services\MigrationService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// De onderhoudspagina van _bootstrap.php laat /cma/ door en stopt de rest met
// een 503 + exit. Draait dit script binnen het onderhoudsvenster van een deploy
// (en dat is precies de bedoeling), dan moet die poort weten dat we in /cma/
// zitten. Sites met een ouder _bootstrap.php kennen de CLI-uitzondering nog
// niet; deze regel maakt het daar ook goed.
$_SERVER['REQUEST_URI'] ??= '/cma/migrate.php';

// Een bootstrap die halverwege afbreekt (onderhoudspagina, ontbrekend bestand)
// eindigt met exit 0 — de deployer zou dat als "migraties gedaan" lezen. Deze
// wachter maakt er een luide mislukking van.
$gebootstrapt = false;
register_shutdown_function(static function () use (&$gebootstrapt): void {
    if (!$gebootstrapt) {
        fwrite(STDERR, "FOUT: bootstrap.inc brak af voordat de migratieloper begon — er is niets toegepast.\n");
        exit(1);
    }
});

require_once __DIR__ . '/bootstrap.inc';
$gebootstrapt = true;

$args = array_slice($argv, 1);
$check = in_array('--check', $args, true);
$backup = !in_array('--no-backup', $args, true);

$onbekend = array_values(array_diff($args, ['--check', '--no-backup']));
if ($onbekend !== []) {
    fwrite(STDERR, "Onbekende optie: " . implode(', ', $onbekend) . "\n");
    fwrite(STDERR, "Gebruik: php cma/migrate.php [--check] [--no-backup]\n");
    exit(2);
}

$service = new MigrationService($backup);
$pending = $service->getPendingMigrations();

if ($pending === []) {
    echo "Geen openstaande migraties.\n";
    exit(0);
}

echo count($pending) . " openstaande migratie(s):\n";
foreach ($pending as $migration) {
    echo '  ' . ($migration['_source'] ?? 'platform') . ' ' . $migration['version']
        . ' — ' . ($migration['description'] ?? '') . "\n";
}

if ($check) {
    exit(1);
}

echo "\nToepassen" . ($backup ? ' (met back-up vooraf)' : ' (zonder back-up)') . "...\n\n";

$result = $service->applyAllPending();

foreach ($result['log'] ?? [] as $regel) {
    echo $regel . "\n";
}

foreach ($service->getWarnings() as $waarschuwing) {
    fwrite(STDERR, 'WAARSCHUWING: ' . $waarschuwing . "\n");
}

if (empty($result['success'])) {
    foreach ($result['errors'] ?? [] as $fout) {
        fwrite(STDERR, 'FOUT bij versie ' . ($fout['version'] ?? '?') . ': ' . ($fout['error'] ?? '') . "\n");
    }
    exit(1);
}

echo "\nToegepast: " . implode(', ', $result['applied'] ?? []) . "\n";
exit(0);
