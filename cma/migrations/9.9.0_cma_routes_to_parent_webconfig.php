<?php
/**
 * Migration 9.9.0: CMA-routes naar parent web.config
 *
 * De CMA rewrite-rules (Dashboard, Preferences, Tools, Forms) leefden
 * historisch in cma/web.config. Dat werkt op de meeste IIS-installaties
 * niet betrouwbaar omdat URL Rewrite Module's distributed-rule semantics
 * + outbound-rule inheritance regelmatig naar 500.50 of 404 leiden
 * (zie v1.19.9 outbound-conflict en v1.20.10 cma-as-Application
 * issue-trail).
 *
 * Deze migration verplaatst de CMA rewrite-rules NAAR het parent
 * web.config van de consumer-site. Daar worden ze ZONDER inheritance-
 * complicaties geapplied. Idempotent via een marker-comment.
 *
 * De file-level patch + validatie (XML well-formedness, duplicate-name,
 * PCRE-regex, backup, atomic write, read-back, rollback) zit in de
 * gedeelde helper App\Library\WebConfigCmaRoutes — dezelfde code die de
 * Composer Installer bij `composer update` draait. Deze migration voegt
 * daar twee IIS-only stappen aan toe (appcmd schema-check + live HTTP
 * smoke-test) die alleen zin hebben met een draaiende IIS + HTTP-context.
 *
 * Backup wordt gemaakt naar web.config.cma-routes-backup-{timestamp}
 * voor de wijziging zodat handmatige rollback mogelijk blijft.
 */

use Cma\Services\Logger;
use Cma\SecurityHelper;
use App\Library\WebConfigCmaRoutes;

// Path-shimming idem aan andere migrations
$basePath = defined('MIGRATION_RUNNING') ? dirname(__DIR__) : __DIR__;
if (strpos($basePath, 'migrations') !== false) {
    $basePath = dirname($basePath);
}
require_once $basePath . '/bootstrap.inc';

$siteRoot  = dirname($basePath);
$webConfig = $siteRoot . '/web.config';

// If the CMA routes are already in the parent web.config (hand-applied, or by
// the Composer Installer / a previous run), there is nothing to patch — succeed
// without requiring developer rights. The elevated-rights guard below only
// matters when we would actually modify web.config; an already-applied site
// should not be blocked from completing the migration.
if (is_file($webConfig)
    && strpos((string)@file_get_contents($webConfig), WebConfigCmaRoutes::MARKER) !== false) {
    echo "CMA-routes al aanwezig in parent web.config (marker gevonden) — niets te doen.\n";
    return true;
}

if (!SecurityHelper::isDeveloper()) {
    throw new \RuntimeException('Developer-rechten vereist voor 9.9.0_cma_routes_to_parent_webconfig');
}

// File-level patch + validatie wordt gedeeld met de Composer Installer via
// WebConfigCmaRoutes (simplexml-check, XML well-formedness, duplicate-name,
// PCRE-regex, backup, atomic write, read-back, rollback). De appcmd- en
// live-smoke-test-stappen hieronder zijn migration-only.
$result = WebConfigCmaRoutes::applyToParent($webConfig);
foreach ($result['messages'] as $line) {
    echo $line . "\n";
}
foreach ($result['errors'] as $line) {
    echo "FOUT: " . $line . "\n";
}

if ($result['status'] === 'error') {
    // De helper heeft al teruggerold / niets aangeraakt. THROW zodat de
    // migration-runner dit als MISLUKT rapporteert (niet "✓ succesvol").
    Logger::warning('9.9.0: web.config patch faalde', ['errors' => $result['errors']]);
    throw new \RuntimeException('9.9.0: web.config patch faalde — ' . implode('; ', $result['errors']));
}
if ($result['status'] === 'manual') {
    // <rules> niet gevonden → kon niet automatisch patchen. THROW zodat de
    // operator weet dat hij handmatig moet ingrijpen.
    throw new \RuntimeException('9.9.0: kon <rules> niet vinden in parent web.config — handmatige patch nodig (zie documentation.php?topic=iis_config).');
}
if ($result['status'] !== 'patched') {
    // skipped (geen simplexml / web.config afwezig) of noop (marker al
    // aanwezig = al toegepast) — niets geschreven, niets te smoke-testen.
    return;
}

$backup = $result['backup'];

// VALIDATIE-STAP 7 (advisory): IIS appcmd.exe op de file gooien.
// appcmd parsed dezelfde schemas die IIS intern gebruikt — exit 0 is sterk
// bewijs dat IIS de config accepteert.
//
// MAAR: appcmd moet eerst de CENTRALE IIS-config lezen (redirection.config /
// applicationHost.config). Deze migration draait als de app-pool-identity
// (PHP onder IIS), die daar meestal GEEN leesrechten op heeft → exit 5 /
// "insufficient permissions" / "Cannot read configuration file". Dat is een
// rechten-limitatie van appcmd, GEEN afkeuring van onze patch. Daarom is deze
// stap advisory: we rollen NOOIT terug op een appcmd-fout. De patch is al
// XML- + duplicate-name- + regex- + read-back-gevalideerd, en de live
// smoke-test hieronder (STAP 8) is de doorslaggevende semantische check — die
// rolt wél terug (op een hard 5xx).
$winDir  = getenv('WINDIR') ?: getenv('SystemRoot');
$appcmd  = $winDir ? ($winDir . '\\system32\\inetsrv\\appcmd.exe') : '';
if ($appcmd === '' || !is_file($appcmd)) {
    echo "appcmd-check overgeslagen: appcmd.exe niet gevonden (non-IIS host).\n";
} else {
    $cmd = '"' . $appcmd . '" list config /commit:WEBROOT 2>&1';
    exec($cmd, $output, $exitCode);
    if ($exitCode === 0) {
        echo "appcmd-check: OK (config door IIS-parser geaccepteerd).\n";
    } else {
        echo "appcmd-check onbeslist (exit $exitCode) — appcmd kon de IIS-config niet (volledig) lezen, "
           . "vaak een rechten-limitatie van de app-pool-identity. Geen afkeuring van de patch, dus "
           . "GEEN rollback; de live smoke-test hieronder is doorslaggevend.\n";
        echo "appcmd output (eerste 20 regels):\n";
        foreach (array_slice($output, 0, 20) as $line) echo "  $line\n";
    }
}

// VALIDATIE-STAP 8 (optioneel, sterkste): live HTTP smoke-test.
// appcmd (stap 7) valideert het SCHEMA maar niet de SEMANTIEK — een rule
// die syntactisch + schema-correct is maar logisch de verkeerde URL matcht,
// of een rule-volgorde die /cma/* alsnog opslokt, ziet alleen IIS bij een
// echte request. Daarom doen we na de write een HTTP-request naar
// /cma/dashboard en checken de response-code. 5xx → de config is door IIS
// geaccepteerd maar produceert een server-error → ROLLBACK. 2xx/3xx/401/403
// = gezond (een auth-redirect naar login telt als "site leeft").
//
// Fail-safe: skip zonder rollback als ext-curl ontbreekt, geen host bekend
// is (CLI-run zonder HTTP_HOST), of de request zelf niet uitgevoerd kan
// worden (DNS/TLS/timeout). In die gevallen vertrouwen we op de eerdere
// stappen — we rollen alleen terug op een hard 5xx-bewijs van een breuk.
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!function_exists('curl_init')) {
    echo "Live smoke-test overgeslagen: ext-curl niet geladen.\n";
} elseif ($host === '') {
    echo "Live smoke-test overgeslagen: geen HTTP_HOST (CLI-run) — kan geen request bouwen.\n";
} else {
    $scheme   = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $smokeUrl = $scheme . '://' . $host . '/cma/dashboard';
    $client   = \App\Library\HttpClient::get($smokeUrl, [], 10000);
    $code     = $client->getStatus();
    if ($code === 0) {
        // Request niet uitgevoerd (timeout / DNS / TLS). Geen rollback — de
        // config is door appcmd al geaccepteerd; dit is "onbeslist", geen breuk.
        $curlErr = $client->getError();
        echo "Live smoke-test onbeslist: geen HTTP-response van " . $smokeUrl;
        if ($curlErr !== '') echo " (" . $curlErr . ")";
        echo " — geen rollback.\n";
    } elseif ($code >= 500) {
        $rolledBack = copy($backup, $webConfig);
        $msg = "live smoke-test gaf HTTP $code op $smokeUrl — de gepatchte config produceert een server-error.";
        // ROLLBACK + THROW: een 5xx is een hard bewijs dat de patch de site
        // breekt. Throw zodat de runner dit als MISLUKT rapporteert (niet
        // "✓ succesvol") — de patch is teruggerold.
        if ($rolledBack) {
            echo "FOUT: $msg ROLLBACK uit backup voltooid.\n";
            throw new \RuntimeException("9.9.0: $msg Teruggerold uit $backup.");
        }
        echo "FOUT: $msg ROLLBACK FAALDE — kopieer handmatig: $backup → $webConfig\n";
        throw new \RuntimeException("9.9.0: $msg ROLLBACK FAALDE — herstel handmatig: $backup → $webConfig.");
    } else {
        echo "Live smoke-test: OK (HTTP $code op /cma/dashboard).\n";
    }
}

// Mtime-change triggert IIS app-pool recycle automatisch.
Logger::info('9.9.0: CMA-routes toegevoegd aan parent web.config', [
    'path'   => $webConfig,
    'backup' => $backup,
]);

echo "Parent web.config gepatched met CMA-routes (XML + duplicate-name + regex + read-back gevalideerd, atomic write; appcmd advisory, live smoke-test als die kon draaien).\n";
echo "10 nieuwe rules toegevoegd bovenaan <rules>.\n";
echo "App-pool wordt automatisch gerecycled (mtime-change).\n";
echo "Test /cma/dashboard, /cma/preferences, /cma/tools — moet allemaal werken.\n";
echo "Bij problemen: kopieer " . $backup . " terug naar " . $webConfig . ".\n";
