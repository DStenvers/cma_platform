<?php
/**
 * Hack URLs — the patterns that count a 404 as a hack attempt (App\Library\NotFoundLists, list "hack").
 *
 * Add and remove go through api/notfound_lists.php, the same endpoint the 404 log
 * in the logreader uses. Administrators and up.
 */

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

$patronen = NotFoundLists::forSite(dirname(__DIR__, 2))->all()[NotFoundLists::HACK] ?? [];

cma_html_header("Hack-URL's");
echo '<body class="contentbody tools tool-hackurls">';
ToolbarHelper::report("Hack-URL's", false, false, false, false, 'Patronen waarvan een 404 als hackpoging telt');
echo '<div id="c" class="tools">';

echo '<p>Een 404 op een pad dat bij een van deze patronen past, telt als hackpoging en gaat mee in de IP-blokkade. '
   . 'Een <code>*</code> staat voor elke tekst; een patroon zonder <code>*</code> moet het hele pad zijn. '
   . 'Houd patronen nauw: een te ruim patroon blokkeert ook gewone bezoekers met een kapotte link.</p>';

echo '<p><button type="button" class="btn btn-primary" id="hackurlToevoegen"><span class="lnr lnr-plus-circle"></span> Patroon toevoegen</button></p>';

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
