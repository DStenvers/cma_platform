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
libxml_use_internal_errors(true);
libxml_clear_errors();
$post = simplexml_load_string($patched);
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

// VALIDATIE-STAP 1b: duplicate rule names binnen elke collection.
// IIS schema definieert <rule name=""> als uniqueId="true" voor zowel
// de inboundRules-collectie als de outboundRules-collectie. Duplicate
// names → IIS 500.50 "URL Rewrite Module Error: cannot add duplicate
// collection entry" — exact het symptoom dat ons hier bracht.
$dupErrors = [];
$collect = function (array $nodes, string $scope) use (&$dupErrors) {
    $seen = [];
    foreach ($nodes as $node) {
        $name = (string)$node['name'];
        if ($name === '') continue;
        if (isset($seen[$name])) {
            $dupErrors[] = "duplicate $scope rule name: '" . $name . "'";
        }
        $seen[$name] = true;
    }
};
$collect($post->xpath('//rewrite/rules/rule'), 'inbound');
$collect($post->xpath('//rewrite/outboundRules/rule'), 'outbound');
$collect($post->xpath('//rewrite/outboundRules/preConditions/preCondition'), 'preCondition');
if (!empty($dupErrors)) {
    echo "FOUT: duplicate-name conflicten in gepatchte config. Original NIET aangeraakt.\n";
    foreach ($dupErrors as $e) echo "  $e\n";
    echo "Een conflict ontstaat meestal omdat de site al handmatig gepatched is OF\n";
    echo "omdat parent en child web.config dezelfde rule-naam definiëren. Verwijder\n";
    echo "de bestaande conflicterende rules of patch handmatig met unieke namen.\n";
    return;
}

// VALIDATIE-STAP 1c: PCRE-syntax check op ONZE eigen patterns. We
// beperken tot rules met "CMA " prefix zodat we de bestaande user-
// rules niet false-positiven (IIS gebruikt .NET regex, niet PCRE,
// dus user-rules kunnen syntactisch verschillen). Voor onze eigen
// rules is PCRE-compatible — een fout hier wijst direct op een
// platform-bug in het rule-blok hierboven.
foreach ($post->xpath('//rewrite/rules/rule[starts-with(@name, "CMA ")]/match') as $match) {
    $url = (string)$match['url'];
    if ($url === '') continue;
    $delim = '~'; // tilde-delim om niet te conflicteren met / in patterns
    if (@preg_match($delim . $url . $delim, '') === false) {
        $err = error_get_last();
        echo "FOUT: invalid regex in CMA rule match url '" . $url . "': " . ($err['message'] ?? 'unknown') . "\n";
        echo "Platform-bug. Original web.config NIET aangeraakt.\n";
        return;
    }
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

// VALIDATIE-STAP 7 (optioneel): IIS appcmd.exe op de file gooien.
// appcmd parsed dezelfde schemas die IIS zelf intern gebruikt — als
// appcmd "list config" voor onze site uitvoert en succesvol parsed
// is dat het sterkste bewijs dat IIS de config niet zal weigeren.
//
// Skip op non-Windows of als appcmd niet bestaat. Skip ook als we
// niet weten welke site-naam we moeten testen. Faal-vriendelijk:
// op een appcmd-fout doen we ROLLBACK want het sterkste signaal is
// "IIS gaat dit niet accepteren".
$winDir  = getenv('WINDIR') ?: getenv('SystemRoot');
$appcmd  = $winDir ? ($winDir . '\\system32\\inetsrv\\appcmd.exe') : '';
$skipped = false;
if ($appcmd === '' || !is_file($appcmd)) {
    $skipped = true;
    echo "Smoke-test overgeslagen: appcmd.exe niet gevonden (non-IIS host).\n";
} else {
    // appcmd list config /commit:WEBROOT laadt EN parsed de gehele
    // effectieve config-chain voor de webroot. Exit-code 0 = parsed
    // succesvol. Non-zero = config-error (precies wat we willen vangen).
    // 2>&1 om stderr in $output te krijgen voor diagnose.
    $cmd = '"' . $appcmd . '" list config /commit:WEBROOT 2>&1';
    exec($cmd, $output, $exitCode);
    if ($exitCode !== 0) {
        // ROLLBACK
        $rolledBack = copy($backup, $webConfig);
        echo "FOUT: appcmd weigert de gepatchte config (exit $exitCode).\n";
        echo "appcmd output (eerste 20 regels):\n";
        foreach (array_slice($output, 0, 20) as $line) echo "  $line\n";
        if ($rolledBack) {
            echo "ROLLBACK uit backup voltooid.\n";
        } else {
            echo "ROLLBACK FAALDE — kopieer handmatig: $backup → $webConfig\n";
        }
        return;
    }
    echo "appcmd smoke-test: OK (config door IIS-parser geaccepteerd).\n";
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
// worden (DNS/TLS/timeout). In die gevallen vertrouwen we op de 7 eerdere
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
        echo "FOUT: live smoke-test gaf HTTP $code op $smokeUrl — de gepatchte config produceert een server-error.\n";
        if ($rolledBack) {
            echo "ROLLBACK uit backup voltooid.\n";
        } else {
            echo "ROLLBACK FAALDE — kopieer handmatig: $backup → $webConfig\n";
        }
        return;
    } else {
        echo "Live smoke-test: OK (HTTP $code op /cma/dashboard).\n";
    }
}

// Mtime-change triggert IIS app-pool recycle automatisch.
Logger::info('9.9.0: CMA-routes toegevoegd aan parent web.config', [
    'path'   => $webConfig,
    'backup' => $backup,
]);

echo "Parent web.config gepatched met CMA-routes (XML + duplicate-name + regex + appcmd + live smoke-test gevalideerd, atomic write).\n";
echo "10 nieuwe rules toegevoegd bovenaan <rules>.\n";
echo "App-pool wordt automatisch gerecycled (mtime-change).\n";
echo "Test /cma/dashboard, /cma/preferences, /cma/tools — moet allemaal werken.\n";
echo "Bij problemen: kopieer " . $backup . " terug naar " . $webConfig . ".\n";
