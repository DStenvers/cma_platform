<?php
/**
 * Migration 9.9.1: .well-known-regel in de parent web.config
 *
 * Verzoeken onder /.well-known/ zonder bestand (passkey-endpoints,
 * change-password) krijgen een lege 200 in plaats van een 404 die de 404-log
 * vult. Bestaande bestanden (acme-challenge, security.txt) blijven bereikbaar.
 * De patch gebruikt dezelfde veiligheidsstappen als de CMA-routes (XML-
 * validatie, backup, atomisch schrijven, rollback) en is idempotent.
 */

use App\Library\WebConfigCmaRoutes;

$basePath = defined('MIGRATION_RUNNING') ? dirname(__DIR__) : __DIR__;
if (strpos($basePath, 'migrations') !== false) {
    $basePath = dirname($basePath);
}
require_once $basePath . '/bootstrap.inc';

$result = WebConfigCmaRoutes::applyWellKnown(dirname($basePath) . '/web.config');
foreach ($result['messages'] as $line) {
    echo $line . "\n";
}
if ($result['status'] === 'error') {
    throw new \RuntimeException('9.9.1: web.config patch faalde — ' . implode('; ', $result['errors']));
}
if ($result['status'] === 'manual') {
    throw new \RuntimeException('9.9.1: kon <rules> niet vinden in parent web.config — handmatige patch nodig (zie documentation.php?topic=iis_config).');
}
if ($result['status'] === 'patched') {
    echo "App-pool wordt automatisch gerecycled (mtime-change).\n";
}
return true;
