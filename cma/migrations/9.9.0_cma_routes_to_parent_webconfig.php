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
 * Backup wordt gemaakt naar web.config.cma-routes-backup-{timestamp}
 * voor de wijziging zodat handmatige rollback mogelijk blijft.
 */

use Cma\Services\Logger;
use Cma\SecurityHelper;

// Path-shimming idem aan andere migrations
$basePath = defined('MIGRATION_RUNNING') ? dirname(__DIR__) : __DIR__;
if (strpos($basePath, 'migrations') !== false) {
    $basePath = dirname($basePath);
}
require_once $basePath . '/bootstrap.inc';

if (!SecurityHelper::isDeveloper()) {
    throw new \RuntimeException('Developer-rechten vereist voor 9.9.0_cma_routes_to_parent_webconfig');
}

$siteRoot  = dirname($basePath);
$webConfig = $siteRoot . '/web.config';

if (!is_file($webConfig)) {
    Logger::warning('9.9.0: parent web.config niet gevonden, skip', ['path' => $webConfig]);
    echo "Parent web.config niet aangetroffen op " . $webConfig . " — skip.\n";
    return;
}

$content = file_get_contents($webConfig);
if ($content === false) {
    throw new \RuntimeException('9.9.0: kan parent web.config niet lezen: ' . $webConfig);
}

// Idempotency-marker. De versie staat erin zodat we later kunnen detecteren
// of er een nieuwe versie van deze patch nodig is.
$marker = 'cma_platform: CMA rewrite rules applied (v1.20.12+)';
if (strpos($content, $marker) !== false) {
    echo "Marker al aanwezig — migration 9.9.0 reeds uitgevoerd. Skip.\n";
    return;
}

// Detecteer of de rules al handmatig zijn toegevoegd (zonder marker) door
// op rule name "CMA Dashboard" te zoeken. Voorkomt dubbele toevoeging
// na een eerdere handmatige patch.
if (strpos($content, 'name="CMA Dashboard"') !== false) {
    echo "Rule 'CMA Dashboard' al aanwezig in parent web.config (handmatige patch?). Marker wordt toegevoegd voor idempotency.\n";
    // Voeg alleen marker toe bij de eerste CMA-rule
    $patched = str_replace(
        '<rule name="CMA Dashboard"',
        '<!-- ' . $marker . ' --><rule name="CMA Dashboard"',
        $content
    );
    $backup = $webConfig . '.cma-routes-backup-' . date('YmdHis');
    copy($webConfig, $backup);
    file_put_contents($webConfig, $patched);
    echo "Backup: " . $backup . "\n";
    return;
}

// Vind de <rules> opening-tag — flexibel voor whitespace-varianten.
if (!preg_match('|<rules>\s*\R|', $content, $m, PREG_OFFSET_CAPTURE)) {
    echo "Kan <rules> opening-tag niet vinden in parent web.config — handmatige patch nodig.\n";
    echo "Voeg de CMA-routes (zie documentation.php?topic=iis_config) handmatig toe bovenaan <rules>.\n";
    return;
}

$insertPos = $m[0][1] + strlen($m[0][0]);

// Backup vóór wijziging.
$backup = $webConfig . '.cma-routes-backup-' . date('YmdHis');
if (!copy($webConfig, $backup)) {
    throw new \RuntimeException('9.9.0: kan geen backup maken naar ' . $backup);
}
echo "Backup: " . $backup . "\n";

// Rules om in te voegen — gebruik nowdoc ('EOT') zodat $-tekens NIET
// als PHP-variabelen worden geïnterpreteerd. $ in pattern is regex-end.
$cmaRules = <<<'EOT'
                <!-- ============================================================
                     CMA rewrite rules — applied by migration 9.9.0
                     Marker: cma_platform: CMA rewrite rules applied (v1.20.12+)
                     ============================================================
                     Deze rules leefden historisch in cma/web.config maar werken
                     daar niet betrouwbaar wegens URL Rewrite Module distributed-
                     rule semantics. Hier in de parent worden ze direct geapplied
                     zonder inheritance-issues. Plaats vóór elke catch-all rule
                     die anders /cma/* zou opslokken. -->
                <rule name="CMA Root" stopProcessing="true">
                    <match url="^cma/?$" />
                    <action type="Redirect" url="/cma/dashboard" redirectType="Found" />
                </rule>
                <rule name="CMA Dashboard" stopProcessing="true">
                    <match url="^cma/dashboard/?$" />
                    <action type="Rewrite" url="/cma/main.php?page=dashboard.php" appendQueryString="true" />
                </rule>
                <rule name="CMA Preferences" stopProcessing="true">
                    <match url="^cma/preferences/?$" />
                    <action type="Rewrite" url="/cma/main.php?page=preferences.php" appendQueryString="true" />
                </rule>
                <rule name="CMA Tools" stopProcessing="true">
                    <match url="^cma/tools/?$" />
                    <action type="Rewrite" url="/cma/main.php?page=tools.php" appendQueryString="true" />
                </rule>
                <rule name="CMA Form list" stopProcessing="true">
                    <match url="^cma/form/([^/]+)/?$" />
                    <action type="Rewrite" url="/cma/main.php?page=form.php%3Fform%3D{R:1}" appendQueryString="true" />
                </rule>
                <rule name="CMA Form with record" stopProcessing="true">
                    <match url="^cma/form/([^/]+)/([^/]+)/?$" />
                    <action type="Rewrite" url="/cma/main.php?page=form.php%3Fform%3D{R:1}&amp;formID={R:2}" appendQueryString="true" />
                </rule>
                <rule name="CMA Form with subform list" stopProcessing="true">
                    <match url="^cma/form/([^/]+)/([^/]+)/([^/]+)/?$" />
                    <action type="Rewrite" url="/cma/main.php?page=form.php%3Fform%3D{R:1}&amp;formID={R:2}&amp;popup={R:3}" appendQueryString="true" />
                </rule>
                <rule name="CMA Form with subform record" stopProcessing="true">
                    <match url="^cma/form/([^/]+)/([^/]+)/([^/]+)/([^/]+)/?$" />
                    <action type="Rewrite" url="/cma/main.php?page=form.php%3Fform%3D{R:1}&amp;formID={R:2}&amp;popup={R:3}&amp;popupID={R:4}" appendQueryString="true" />
                </rule>
                <rule name="CMA Form with subsubform list" stopProcessing="true">
                    <match url="^cma/form/([^/]+)/([^/]+)/([^/]+)/([^/]+)/([^/]+)/?$" />
                    <action type="Rewrite" url="/cma/main.php?page=form.php%3Fform%3D{R:1}&amp;formID={R:2}&amp;popup={R:3}&amp;popupID={R:4}&amp;subpopup={R:5}" appendQueryString="true" />
                </rule>
                <rule name="CMA Form with subsubform record" stopProcessing="true">
                    <match url="^cma/form/([^/]+)/([^/]+)/([^/]+)/([^/]+)/([^/]+)/([^/]+)/?$" />
                    <action type="Rewrite" url="/cma/main.php?page=form.php%3Fform%3D{R:1}&amp;formID={R:2}&amp;popup={R:3}&amp;popupID={R:4}&amp;subpopup={R:5}&amp;subpopupID={R:6}" appendQueryString="true" />
                </rule>

EOT;

$patched = substr_replace($content, $cmaRules, $insertPos, 0);
if (file_put_contents($webConfig, $patched) === false) {
    throw new \RuntimeException('9.9.0: kan parent web.config niet schrijven');
}

// Mtime-change triggert IIS app-pool recycle automatisch.
Logger::info('9.9.0: CMA-routes toegevoegd aan parent web.config', [
    'path'   => $webConfig,
    'backup' => $backup,
]);

echo "Parent web.config gepatched met CMA-routes.\n";
echo "10 nieuwe rules toegevoegd bovenaan <rules>.\n";
echo "App-pool wordt automatisch gerecycled (mtime-change).\n";
echo "Test /cma/dashboard, /cma/preferences, /cma/tools — moet allemaal werken.\n";
