<?php
/**
 * Migration 9.9.1: standaard hackpatronen op de hacklijst
 *
 * Voegt de patronen uit NotFoundLists::DEFAULT_HACK toe aan de hacklijst van
 * de site (data/notfound_lists.json) die er nog niet op staan. Bestaande
 * patronen, hun datum en hun auteur blijven ongemoeid; opnieuw draaien voegt
 * niets toe.
 */

use App\Library\NotFoundLists;

$basePath = defined('MIGRATION_RUNNING') ? dirname(__DIR__) : __DIR__;
if (strpos($basePath, 'migrations') !== false) {
    $basePath = dirname($basePath);
}
require_once $basePath . '/bootstrap.inc';

$lists = NotFoundLists::forSite(dirname($basePath));
$added = $lists->mergeDefaults('migratie 9.9.1');

if ($added === false) {
    echo "✗ Kan data/notfound_lists.json niet schrijven\n";
    if (defined('MIGRATION_RUNNING')) return false;
    exit(1);
}

echo $added === 0
    ? "✓ Alle standaard hackpatronen staan al op de hacklijst\n"
    : "✓ $added standaard hackpatronen toegevoegd aan de hacklijst\n";
if (defined('MIGRATION_RUNNING')) return true;
exit(0);
