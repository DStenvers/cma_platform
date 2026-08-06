<?php
/**
 * Migration 9.23.0: verhuis <site>/.backup naar <site>/data/.backup
 *
 * De back-uptool schreef zijn databasekopieën naar een verborgen map in de siteroot. Dat is
 * een vreemde plek: het ZIJN gegevens (kopieën van de .mdb/.sql-bestanden uit data/db/), en
 * een losse punt-map naast index.php is precies waar niemand ze zoekt. Ze horen bij de rest
 * van de gegevens, onder data/.
 *
 * BackupService::bepaalBackupDir() kiest sinds deze versie data/.backup, met één terugval:
 * staat de oude map er nog mét inhoud en de nieuwe nog niet, dan blijft de oude in gebruik.
 * Dat venster — tussen het bijwerken van het pakket en het draaien van deze migratie — is
 * precies waar deze migratie een eind aan maakt.
 *
 * Wat er gebeurt, en wat er nadrukkelijk NIET gebeurt:
 *  - bestaat .backup/ niet, dan is er niets te doen (idempotent, ook na een tweede run);
 *  - bestaat data/.backup/ nog niet, dan wordt de hele map in één keer hernoemd — dat is een
 *    verplaatsing binnen dezelfde schijf en kopieert dus geen honderden megabytes;
 *  - bestaan ze allebei, dan gaat elk bestand apart over. Een bestand dat op de nieuwe plek al
 *    bestaat blijft daar staan en het oude wordt NIET weggegooid: bij een back-up is
 *    "misschien dubbel" altijd beter dan "misschien weg". Zo'n overgeslagen bestand wordt
 *    gemeld, met het advies het met de hand na te lopen;
 *  - de oude map wordt alleen opgeruimd als hij daarna echt leeg is.
 *
 * backups.json (de index van de tool) verhuist als gewoon bestand mee; hij bevat alleen
 * bestandsnamen en metadata, geen paden, dus er valt niets in te herschrijven.
 */

// $siteRoot mag door een testopstelling vooraf gezet zijn; anders afleiden uit de locatie
// (de siteroot als de runner MIGRATION_RUNNING definieert, anders de map boven cma/).
if (!isset($siteRoot)) {
    $siteRoot = defined('MIGRATION_RUNNING') ? dirname(__DIR__, 2) : dirname(__DIR__);
}

$oud    = $siteRoot . '/.backup';
$nieuw  = $siteRoot . '/data/.backup';
$dataMap = $siteRoot . '/data';

if (!is_dir($oud)) {
    echo "  Overgeslagen: .backup/ bestaat niet (al verhuisd of nooit gebruikt)\n";
    return;
}

if (!is_dir($dataMap)) {
    echo "  Overgeslagen: data/ bestaat niet — dit lijkt geen siteroot.\n";
    return;
}

// Geval 1: de nieuwe map bestaat nog niet. Eén rename, dus geen kopieerslag over de schijf.
if (!is_dir($nieuw)) {
    if (@rename($oud, $nieuw)) {
        echo "  Verplaatst: .backup/ -> data/.backup/\n";
        return;
    }
    echo "  Hernoemen mislukte (rechten of een andere schijf) — bestand voor bestand dan.\n";
    if (!@mkdir($nieuw, 0755, true) && !is_dir($nieuw)) {
        echo "  MISLUKT: kan data/.backup/ niet aanmaken. De back-ups blijven staan waar ze staan.\n";
        return;
    }
}

// Geval 2: allebei aanwezig (of de rename lukte niet). Bestand voor bestand, en nooit
// overschrijven — een back-up die je per ongeluk wegschrijft is niet terug te halen.
$verplaatst = 0;
$overgeslagen = [];
foreach (glob($oud . '/*') ?: [] as $pad) {
    $naam = basename($pad);
    $doel = $nieuw . '/' . $naam;
    if (file_exists($doel)) {
        $overgeslagen[] = $naam;
        continue;
    }
    if (@rename($pad, $doel)) {
        $verplaatst++;
    } else {
        $overgeslagen[] = $naam . ' (verplaatsen mislukte)';
    }
}

echo "  Verplaatst: $verplaatst bestand(en) naar data/.backup/\n";
if ($overgeslagen !== []) {
    echo "  Blijft staan in .backup/ (bestaat al op de nieuwe plek of niet te verplaatsen):\n";
    foreach (array_slice($overgeslagen, 0, 10) as $naam) {
        echo "    - $naam\n";
    }
    if (count($overgeslagen) > 10) {
        echo '    … en nog ' . (count($overgeslagen) - 10) . " andere\n";
    }
    echo "  Loop deze met de hand na; er wordt hier bewust niets overschreven of weggegooid.\n";
    return;
}

// Alleen opruimen als er echt niets meer in staat.
if ((glob($oud . '/*') ?: []) === [] && (glob($oud . '/.*') ?: []) === []) {
    @rmdir($oud);
}
if (!is_dir($oud)) {
    echo "  Opgeruimd: de lege map .backup/ is weg\n";
}
