<?php
/**
 * Hack URLs — the patterns that count a 404 as a hack attempt (App\Library\NotFoundLists, list "hack").
 *
 * Add and remove go through api/notfound_lists.php, the same endpoint the 404 log
 * in the logreader uses. Administrators and up.
 */

use App\Library\Llm;
use App\Library\NotFoundAnalyzer;
use App\Library\NotFoundLists;
use App\Library\Server;
use Cma\SecurityHelper;
use Cma\ToolbarHelper;

require_once __DIR__ . '/../bootstrap.inc';

if (!SecurityHelper::isAdmin()) {
    http_response_code(403);
    echo 'Toegang geweigerd — alleen beheerders.';
    exit;
}

$lijsten = NotFoundLists::forSite(dirname(__DIR__, 2));
$analyseMogelijk = Llm::isConfigured();
$laatsteAnalyse = NotFoundAnalyzer::lastRun(dirname(__DIR__, 2));
$patronen = $lijsten->all()[NotFoundLists::HACK] ?? [];

cma_html_header("Hack-URL's");
echo '<body class="contentbody tools tool-hackurls">';
ob_start();
ToolbarHelper::button('tools_blockedips.php', 'lnr-lock', true, "Geblokkeerde IP's", "Naar de geblokkeerde IP's");
$zusterknop = ob_get_clean();
ToolbarHelper::report("Hack-URL's", false, false, false, false, 'Patronen waarvan een 404 als hackpoging telt', '', '', $zusterknop);
echo '<div id="c" class="tools">';

echo '<p>Een 404 op een pad dat bij een van deze patronen past, telt als hackpoging en gaat mee in de IP-blokkade. '
   . 'Een <code>*</code> staat voor elke tekst; een patroon zonder <code>*</code> moet het hele pad zijn. '
   . 'Houd patronen nauw: een te ruim patroon blokkeert ook gewone bezoekers met een kapotte link.</p>';

if ($analyseMogelijk) {
    echo '<p class="text-muted">Laatste 404-analyse: ' . ($laatsteAnalyse ? date('d-m-Y', $laatsteAnalyse) : 'nog nooit') . '. De analyse stuurt alleen paden en aantallen (geen IP\'s) naar de ingestelde LLM en doet voorstellen; er wordt niets automatisch toegevoegd.</p>';
}
echo '<p><button type="button" class="btn btn-primary" id="hackurlToevoegen"><span class="lnr lnr-plus-circle"></span> Patroon toevoegen</button>'
   . ($lijsten->canAddDefaults() ? ' <button type="button" class="btn" id="hackurlStandaard"><span class="lnr lnr-layers"></span> Standaardlijst toevoegen</button>' : '')
   . ($analyseMogelijk ? ' <button type="button" class="btn" id="hackurlAnalyse"><span class="lnr lnr-magic-wand"></span> Analyseer 404-log</button>' : '')
   . '</p>';
echo '<div id="hackurlVoorstellen"></div>';

if ($patronen === []) {
    echo '<p class="text-muted">Nog geen patronen.</p>';
} else {
    echo '<lib-table resizable>';
    echo '<table><thead><tr><th>Patroon</th><th>Toegevoegd</th><th>Door</th><th data-no-filter></th></tr></thead><tbody>';
    foreach ($patronen as $p) {
        echo '<tr>';
        echo '<td><code>' . Server::htmlEncode($p['patroon']) . '</code></td>';
        echo '<td>' . Server::htmlEncode($p['toegevoegd'] ?: '-') . '</td>';
        echo '<td>' . Server::htmlEncode($p['door'] ?: '-') . '</td>';
        echo '<td><button type="button" class="btn hackurl-verwijder" data-patroon="' . Server::htmlEncode($p['patroon']) . '" title="Van de lijst halen"><span class="lnr lnr-trash"></span></button></td>';
        echo '</tr>';
    }
    echo '</tbody></table></lib-table>';
}
?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function stuur(body) {
        return fetch('../api/notfound_lists.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ lijst: 'hack' }, body))
        }).then(function (r) { return r.json(); });
    }
    function klaar(antwoord) {
        if (antwoord.success) { location.reload(); return; }
        libAlert(antwoord.error || 'Opslaan mislukt');
    }
    document.getElementById('hackurlToevoegen').addEventListener('click', async function () {
        var patroon = await libPrompt('Patroon (bijvoorbeeld /wp-* of *.env):', { title: 'Hack-URL toevoegen' });
        if (patroon !== null && patroon.trim() !== '') {
            stuur({ action: 'add', patroon: patroon }).then(klaar);
        }
    });
    var standaard = document.getElementById('hackurlStandaard');
    if (standaard) {
        standaard.addEventListener('click', async function () {
            var ok = await libConfirm('De standaardlijst met gangbare aanvalspaden toevoegen (WordPress, .env, .git, phpMyAdmin, ...)? Dit kan maar één keer.', {
                title: 'Standaardlijst toevoegen', confirmText: 'Toevoegen', cancelText: 'Annuleren'
            });
            if (ok) { stuur({ action: 'defaults' }).then(klaar); }
        });
    }
    var analyse = document.getElementById('hackurlAnalyse');
    if (analyse) {
        analyse.addEventListener('click', function () {
            var uit = document.getElementById('hackurlVoorstellen');
            analyse.disabled = true;
            uit.textContent = 'Bezig met analyseren...';
            stuur({ action: 'analyse' }).then(function (a) {
                analyse.disabled = false;
                uit.textContent = '';
                if (!a.success) { libAlert(a.error || 'Analyse mislukt'); return; }
                if (!a.voorstellen.length) { uit.textContent = 'Geen nieuwe hackpogingen gevonden in ' + a.paden + ' paden.'; return; }
                var tabel = document.createElement('table');
                tabel.className = 'table';
                a.voorstellen.forEach(function (v) {
                    var rij = tabel.insertRow();
                    rij.insertCell().textContent = v.patroon;
                    rij.insertCell().textContent = v.reden + ' (' + v.aantal + 'x: ' + v.voorbeelden.join(', ') + ')';
                    var knop = document.createElement('button');
                    knop.type = 'button';
                    knop.className = 'btn';
                    knop.textContent = 'Toevoegen';
                    knop.addEventListener('click', function () {
                        stuur({ action: 'add', patroon: v.patroon }).then(function (r) {
                            if (r.success) { knop.disabled = true; knop.textContent = 'Toegevoegd'; } else { libAlert(r.error || 'Opslaan mislukt'); }
                        });
                    });
                    rij.insertCell().appendChild(knop);
                });
                uit.appendChild(tabel);
            }).catch(function () { analyse.disabled = false; uit.textContent = ''; libAlert('Analyse mislukt'); });
        });
    }
    document.querySelectorAll('.hackurl-verwijder').forEach(function (knop) {
        knop.addEventListener('click', async function () {
            var patroon = this.dataset.patroon;
            var ok = await libConfirm('Patroon ' + patroon + ' van de lijst halen?', {
                title: 'Patroon verwijderen', confirmText: 'Verwijderen', cancelText: 'Annuleren'
            });
            if (ok) { stuur({ action: 'remove', patroon: patroon }).then(klaar); }
        });
    });
});
</script>
