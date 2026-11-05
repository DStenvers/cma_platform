<?php
/**
 * Blocked IPs — the IP addresses that currently get a 403 (App\Library\HackBlocker).
 *
 * Lifting and blocking by hand go through api/notfound_lists.php, the same
 * endpoint the 404 log in the logreader uses. Administrators and up.
 */

use App\Library\HackBlocker;
use App\Library\Server;
use App\Library\Settings;
use Cma\SecurityHelper;
use Cma\ToolbarHelper;

require_once __DIR__ . '/../bootstrap.inc';

if (!SecurityHelper::isAdmin()) {
    http_response_code(403);
    echo 'Toegang geweigerd — alleen beheerders.';
    exit;
}

$blokkades = (new HackBlocker(dirname(__DIR__, 2)))->blocks();
$standaardMinuten = max(1, (int) Settings::get('hack_block_duration_minutes'));

cma_html_header("Geblokkeerde IP's");
echo '<body class="contentbody tools tool-blockedips">';
ob_start();
ToolbarHelper::button('tools_hackurls.php', 'lnr-warning', true, "Hack-URL's", "Naar de hack-URL's");
$zusterknop = ob_get_clean();
ToolbarHelper::report("Geblokkeerde IP's", false, false, false, false, 'IP-adressen die nu een 403 krijgen', $zusterknop);
echo '<div id="c" class="tools">';

if (Settings::get('hack_block_enabled')) {
    echo '<p>Een IP dat meer dan ' . (int) Settings::get('hack_block_threshold') . ' hackpogingen doet binnen '
       . (int) Settings::get('hack_block_window_minutes') . ' minuten, krijgt '
       . (int) Settings::get('hack_block_duration_minutes') . ' minuten lang een 403 op elke pagina. '
       . 'Instellen via Systeeminstellingen → Beveiliging.</p>';
} else {
    echo '<lib-message type="warning" compact>Blokkeren staat uit (Systeeminstellingen → Beveiliging); handmatig geblokkeerde IP\'s worden dan ook niet tegengehouden.</lib-message>';
}

echo '<p><button type="button" class="btn btn-primary" id="ipBlokkeren"><span class="lnr lnr-lock"></span> IP blokkeren</button></p>';

if ($blokkades === []) {
    echo '<p class="text-muted">Geen actieve blokkades.</p>';
} else {
    echo '<lib-table resizable>';
    echo '<table><thead><tr><th>IP</th><th data-type="number">Sinds</th><th data-type="number">Tot</th><th data-type="number">Pogingen</th><th>Laatste URL</th><th data-no-filter></th></tr></thead><tbody>';
    foreach ($blokkades as $ip => $b) {
        echo '<tr>';
        echo '<td><code>' . Server::htmlEncode((string) $ip) . '</code></td>';
        echo '<td data-sort-value="' . (int) $b['sinds'] . '">' . date('d-m-Y H:i', (int) $b['sinds']) . '</td>';
        echo '<td data-sort-value="' . (int) $b['tot'] . '">' . date('d-m-Y H:i', (int) $b['tot']) . '</td>';
        echo '<td>' . (int) $b['pogingen'] . '</td>';
        echo '<td><code>' . Server::htmlEncode((string) ($b['laatste'] ?? '')) . '</code></td>';
        echo '<td><button type="button" class="btn ip-opheffen" data-ip="' . Server::htmlEncode((string) $ip) . '">Opheffen</button></td>';
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
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); });
    }
    function klaar(antwoord) {
        if (antwoord.success) { location.reload(); return; }
        libAlert(antwoord.error || 'Opslaan mislukt');
    }
    document.getElementById('ipBlokkeren').addEventListener('click', async function () {
        var ip = await libPrompt('IP-adres:', { title: 'IP blokkeren' });
        if (ip === null || ip.trim() === '') { return; }
        var minuten = await libPrompt('Aantal minuten:', { title: 'IP blokkeren', defaultValue: '<?= $standaardMinuten ?>' });
        if (minuten !== null) {
            stuur({ action: 'block', ip: ip.trim(), minuten: parseInt(minuten, 10) || 0 }).then(klaar);
        }
    });
    document.querySelectorAll('.ip-opheffen').forEach(function (knop) {
        knop.addEventListener('click', async function () {
            var ip = this.dataset.ip;
            var ok = await libConfirm('Blokkade op ' + ip + ' opheffen?', {
                title: 'Blokkade opheffen', confirmText: 'Opheffen', cancelText: 'Annuleren'
            });
            if (ok) { stuur({ action: 'unblock', ip: ip }).then(klaar); }
        });
    });
});
</script>
