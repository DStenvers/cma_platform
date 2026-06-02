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

// XML-validatie vereist simplexml. Default extensie op IIS PHP maar
// niet altijd op WSL/Docker test-envs. Zonder is de migration te
// risicovol om te draaien — corrupt web.config = site plat.
if (!function_exists('simplexml_load_string')) {
    echo "FOUT: ext-simplexml is niet geladen. Migration weigert te draaien omdat XML-validatie niet mogelijk is.\n";
    echo "Voeg in php.ini toe: extension=simplexml\n";
    echo "Of patch parent web.config handmatig (zie documentation.php?topic=iis_config).\n";
    return;
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

// Vóór we ook maar IETS doen: parse de huidige parent web.config om
// zeker te weten dat hij valid XML is. Als de huidige config al stuk
// is heeft het geen zin om er bij in te schrijven — wijst eerder op
// een ongerelateerd probleem dat de operator eerst moet fixen.
$pre = @simplexml_load_string($content);
if ($pre === false) {
    echo "FOUT: parent web.config is GEEN valid XML. Migration weigert te draaien.\n";
    echo "Fix het XML-probleem eerst (browser developer-tools → Network → response).\n";
    return;
}

// Vind de <rules> opening-tag — flexibel voor whitespace-varianten.
if (!preg_match('|<rules>\s*\R|', $content, $m, PREG_OFFSET_CAPTURE)) {
    echo "Kan <rules> opening-tag niet vinden in parent web.config — handmatige patch nodig.\n";
    echo "Voeg de CMA-routes (zie documentation.php?topic=iis_config) handmatig toe bovenaan <rules>.\n";
    return;
}

$insertPos = $m[0][1] + strlen($m[0][0]);

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

// VALIDATIE-STAP 1: parse de gepatchte content IN MEMORY voordat we
// ook maar iets naar disk schrijven. Een corrupt parent web.config
// = de hele site plat (niet alleen /cma). Daarom: nooit een file
// schrijven die we niet eerst hebben gevalideerd.
$post = @simplexml_load_string($patched);
if ($post === false) {
    $errors = libxml_get_errors();
    libxml_clear_errors();
    echo "FOUT: gepatchte XML is niet valid. Original web.config NIET aangeraakt.\n";
    foreach ($errors as $err) {
        echo "  libxml: " . trim($err->message) . " (line " . $err->line . ")\n";
    }
    echo "Dit is een platform-bug — geef deze output aan de ontwikkelaar.\n";
    return;
}

// VALIDATIE-STAP 2: backup maken NU we zeker weten dat de patch
// syntactisch ok is. Backup gaat vóór de write zodat we kunnen
// rollback bij een schrijf-fout halverwege.
$backup = $webConfig . '.cma-routes-backup-' . date('YmdHis');
if (!copy($webConfig, $backup)) {
    throw new \RuntimeException('9.9.0: kan geen backup maken naar ' . $backup);
}
echo "Backup: " . $backup . "\n";

// VALIDATIE-STAP 3: write naar tmp-bestand naast het origineel, dan
// rename (atomic op de meeste filesystems). Voorkomt half-geschreven
// file als de write halverwege faalt door disk-full / permissie-
// wijziging / process-crash.
$tmpFile = $webConfig . '.cma-routes-tmp-' . date('YmdHis');
if (file_put_contents($tmpFile, $patched) === false) {
    @unlink($tmpFile);
    throw new \RuntimeException('9.9.0: kan tmp-bestand niet schrijven: ' . $tmpFile);
}

// VALIDATIE-STAP 4: lees het tmp-bestand terug en valideer. Dit vangt
// het zeldzame geval waar disk-encoding / line-ending de XML breekt
// na write maar vóór IIS de file inleest.
$readBack = @file_get_contents($tmpFile);
if ($readBack === false || @simplexml_load_string($readBack) === false) {
    @unlink($tmpFile);
    echo "FOUT: tmp-bestand passeert validatie niet na read-back. Original NIET vervangen.\n";
    return;
}

// VALIDATIE-STAP 5: atomic rename. PHP rename() is atomic op POSIX
// en op Windows-NTFS binnen hetzelfde volume.
if (!rename($tmpFile, $webConfig)) {
    @unlink($tmpFile);
    throw new \RuntimeException('9.9.0: rename ' . $tmpFile . ' → ' . $webConfig . ' faalde');
}

// VALIDATIE-STAP 6: lees de FINALE file terug en valideer nog een
// laatste keer. Als deze faalt rollen we terug uit de backup. Paranoïde
// maar het is web.config — de prijs van een gemiste fout is "site plat".
$final = @file_get_contents($webConfig);
if ($final === false || @simplexml_load_string($final) === false) {
    if (copy($backup, $webConfig)) {
        echo "FOUT: finale validatie faalde, ROLLBACK uit backup voltooid.\n";
    } else {
        echo "FOUT: finale validatie faalde EN rollback faalde. HANDMATIG HERSTEL VEREIST: kopieer\n";
        echo "      $backup\n";
        echo "      naar\n";
        echo "      $webConfig\n";
    }
    return;
}

// Mtime-change triggert IIS app-pool recycle automatisch.
Logger::info('9.9.0: CMA-routes toegevoegd aan parent web.config', [
    'path'   => $webConfig,
    'backup' => $backup,
]);

echo "Parent web.config gepatched met CMA-routes (XML 4× gevalideerd, atomic write).\n";
echo "10 nieuwe rules toegevoegd bovenaan <rules>.\n";
echo "App-pool wordt automatisch gerecycled (mtime-change).\n";
echo "Test /cma/dashboard, /cma/preferences, /cma/tools — moet allemaal werken.\n";
echo "Bij problemen: kopieer " . $backup . " terug naar " . $webConfig . ".\n";
