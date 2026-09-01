<?php
/**
 * Startwaarden terughalen uit repository.mdb.
 *
 * De omzetting van formulierdefinities naar JSON liet tblControls.schema_default
 * liggen. 430 velden verloren daarmee hun startwaarde voor een nieuw record —
 * waaronder de vinkjes die aan hoorden te staan. Dit hulpmiddel leest die kolom
 * alsnog en zet de waarden in de JSON-definities.
 *
 * Het toont eerst wat het van plan is, met per veld de reden. Pas op "Doorvoeren"
 * worden bestanden geschreven, en dan nog alleen velden die zelf nog niets
 * vastleggen — een definitie die inmiddels een eigen defaultValue heeft, wint.
 *
 * Zonder repository.mdb valt er niets terug te halen; dan zegt het scherm dat, en
 * verder niets. De beoordeling van de waarden zit in Cma\Services\StartwaardeMigratie
 * en is daar los getest.
 */
require_once dirname(__DIR__) . '/bootstrap.inc';

use App\Library\Database;
use App\Library\Request;
use App\Library\Response;
use Cma\JsonFormLoader;
use Cma\SecurityHelper;
use Cma\Services\StartwaardeMigratie;

Response::noCache();

if (!SecurityHelper::isAdmin()) {
    http_response_code(403);
    die('Toegang geweigerd - alleen admins');
}

$doorvoeren = Request::query('mode') === 'doorvoeren';
$formsDir   = dirname(__DIR__, 2) . '/assets/forms';

// ---------------------------------------------------------------------------
// 1. De repository openen — of netjes melden dat hij er niet meer is.
//
// Expliciet via databases.json, niet via Database::getConnection('rep'): die valt
// terug op de hoofddatabase zodra 'rep' niet is ingesteld, en dan zou dit
// hulpmiddel in pdodomain.mdb naar tblControls gaan zoeken. Eerst het bestand,
// dan pas een verbinding — bestaat repository.mdb niet meer, dan is het antwoord
// "niets terug te halen" en gaat er geen database open.
// ---------------------------------------------------------------------------
$repConn = null;
$repPad = '';
$geenRepository = '';

$dbConfig = json_decode((string) @file_get_contents(dirname(__DIR__) . '/config/databases.json'), true);
$repConnStr = '';
foreach (($dbConfig['databases'] ?? []) as $db) {
    if (stripos((string) ($db['connectionString'] ?? ''), 'repository.mdb') !== false) {
        $repConnStr = (string) $db['connectionString'];
        break;
    }
}

if ($repConnStr === '') {
    $geenRepository = 'Geen database in databases.json wijst naar repository.mdb.';
} else {
    $volledig = \Cma\CmaRepository::resolveConnectionString($repConnStr);
    if (preg_match('/Data Source=([^;]+)/i', $volledig, $m) === 1) {
        $repPad = trim($m[1]);
    }
    if ($repPad === '' || !file_exists($repPad)) {
        $geenRepository = 'repository.mdb staat niet (meer) op ' . ($repPad !== '' ? $repPad : '(onbekend pad)') . '.';
    } else {
        try {
            $repConn = Database::getConnection($volledig);
        } catch (\Throwable $e) {
            $geenRepository = 'repository.mdb is er wel, maar gaat niet open: ' . $e->getMessage();
        }
        if (!$repConn && $geenRepository === '') {
            $geenRepository = 'repository.mdb is er wel, maar gaat niet open.';
        }
    }
}

// ---------------------------------------------------------------------------
// 2. De startwaarden ophalen en beoordelen.
// ---------------------------------------------------------------------------
$regels = [];      // alles, voor het verslag
$perForm = [];     // sourceFormId => [veldnaam => waarde] — alleen wat we overnemen

if (!$geenRepository) {
    $sql = 'SELECT f.ID AS FormID, f.FormName, c.FieldName, c.ControlTypeID, c.schema_default'
         . ' FROM tblControls c INNER JOIN tblForms f ON c.FormID = f.ID'
         . " WHERE c.schema_default IS NOT NULL AND c.schema_default <> ''"
         . ' ORDER BY f.FormName, c.ExecutionOrder';

    try {
        $rs = Database::openRS($sql, $repConn);
    } catch (\Throwable $e) {
        $rs = null;
        $geenRepository = 'tblControls is niet te lezen: ' . $e->getMessage();
    }
    while ($rs && $rij = $rs->fetch()) {
        $formId       = (int) ($rij['FormID'] ?? 0);
        $veldnaam     = trim((string) ($rij['FieldName'] ?? ''));
        $isSchakelaar = ((int) ($rij['ControlTypeID'] ?? 0)) === 5;
        if ($veldnaam === '') {
            continue;
        }

        $oordeel = StartwaardeMigratie::beoordeel($rij['schema_default'], $isSchakelaar);
        $regel = [
            'formId'    => $formId,
            'formNaam'  => (string) ($rij['FormName'] ?? ''),
            'veld'      => $veldnaam,
            'ruw'       => (string) ($rij['schema_default'] ?? ''),
            'soort'     => $oordeel['soort'],
            'besluit'   => $oordeel['besluit'],
            'reden'     => $oordeel['reden'],
            'waarde'    => $oordeel['waarde'],
        ];
        $regels[] = $regel;

        if ($oordeel['besluit'] === StartwaardeMigratie::OVERNEMEN) {
            $perForm[$formId][strtolower($veldnaam)] = $oordeel['waarde'];
        }
    }
}

// ---------------------------------------------------------------------------
// 3. De formulierdefinities erbij zoeken (op sourceFormId) en toepassen.
// ---------------------------------------------------------------------------
$definitiePerId = [];
foreach (glob($formsDir . '/*.json') ?: [] as $pad) {
    $def = json_decode((string) file_get_contents($pad), true);
    if (!is_array($def) || empty($def['sourceFormId'])) {
        continue;
    }
    $definitiePerId[(int) $def['sourceFormId']] = ['pad' => $pad, 'def' => $def];
}

$verslag = [];
$geschreven = 0;
$fouten = [];

foreach ($perForm as $formId => $perVeld) {
    if (!isset($definitiePerId[$formId])) {
        $verslag[] = [
            'form'   => 'formulier ' . $formId,
            'status' => 'geen JSON-definitie met sourceFormId ' . $formId,
            'gezet'  => [],
        ];
        continue;
    }

    $pad = $definitiePerId[$formId]['pad'];
    $uit = StartwaardeMigratie::toepassen($definitiePerId[$formId]['def'], $perVeld);

    $ontbreekt = array_diff(array_keys($perVeld), array_keys($uit['gezet']), array_keys($uit['overgeslagen']));

    $verslag[] = [
        'form'         => basename($pad),
        'status'       => $uit['gezet'] === [] ? 'niets te doen' : count($uit['gezet']) . ' veld(en)',
        'gezet'        => $uit['gezet'],
        'overgeslagen' => $uit['overgeslagen'],
        'onbekend'     => $ontbreekt,
    ];

    if ($doorvoeren && $uit['gezet'] !== []) {
        // In de schrijfwijze van het bestand zelf: de definities staan niet allemaal
        // in dezelfde stijl, en één stijl erover herschrijft duizenden regels die
        // niets veranderen.
        $origineel = (string) file_get_contents($pad);
        $json = json_encode($uit['definitie'], StartwaardeMigratie::schrijfvlaggen($origineel));
        if ($json === false || file_put_contents($pad, $json . "\n") === false) {
            $fouten[] = 'Kon ' . basename($pad) . ' niet schrijven';
        } else {
            $geschreven++;
        }
    }
}

if ($doorvoeren && $geschreven > 0) {
    // De definities zitten in drie lagen cache; zonder legen blijft het scherm
    // de oude versie tonen.
    JsonFormLoader::clearCache();
}

usort($verslag, fn($a, $b) => strcmp($a['form'], $b['form']));

$aantalOvernemen = count(array_filter($regels, fn($r) => $r['besluit'] === StartwaardeMigratie::OVERNEMEN));

cma_html_header('Startwaarden terughalen');
?>
<body class="contentbody tools">
<div id="c">
    <h2>Startwaarden terughalen uit repository.mdb</h2>

    <?php if ($geenRepository): ?>
        <lib-message type="info">
            repository.mdb is niet beschikbaar, dus er valt niets terug te halen.
            (<?= htmlspecialchars($geenRepository) ?>)
        </lib-message>
    <?php else: ?>

        <p>
            De omzetting naar JSON las <code>tblControls.schema_default</code> niet.
            Daardoor missen formulieren de startwaarde die een <span class="cma-tool__em">nieuw</span> record hoort
            te krijgen — een vinkje dat aan moest staan, staat uit.
        </p>
        <p>
            Gevonden: <span class="cma-tool__strong"><?= count($regels) ?></span> velden met een
            startwaarde in de repository, waarvan
            <span class="cma-tool__strong"><?= $aantalOvernemen ?></span> als losse waarde te
            lezen zijn. De rest is een uitdrukking die de database zelf invult
            (<code>Now()</code>, <code>GenGUID()</code>), of betekent "uit" of "leeg" —
            dat is al het gedrag zonder startwaarde. Alles staat hieronder met de reden.
        </p>

        <?php if (!$doorvoeren): ?>
            <p><span class="cma-tool__strong">Modus:</span> tonen — er wordt niets geschreven.</p>
            <p>
                <a href="?mode=doorvoeren" class="btn btn-primary"
                   onclick="event.preventDefault(); var href=this.href; libConfirm('De startwaarden in <?= count(array_filter($verslag, fn($v) => $v['gezet'] !== [])) ?> formulierdefinitie(s) wegschrijven?').then(function(ok){if(ok){window.location.href=href}})">Doorvoeren</a>
            </p>
        <?php else: ?>
            <p><span class="cma-tool__strong">Modus:</span> doorvoeren.</p>
            <lib-message type="success"><?= $geschreven ?> formulierdefinitie(s) bijgewerkt.</lib-message>
        <?php endif; ?>

        <?php if ($fouten): ?>
            <lib-message type="error"><?= htmlspecialchars(implode(' — ', $fouten)) ?></lib-message>
        <?php endif; ?>

        <h3>Per formulier</h3>
        <table class="datatable">
            <thead><tr><th>Definitie</th><th><?= $doorvoeren ? 'Bijgewerkt' : 'Wordt gezet' ?></th><th>Blijft liggen</th></tr></thead>
            <tbody>
            <?php foreach ($verslag as $v): ?>
                <tr>
                    <td><?= htmlspecialchars($v['form']) ?></td>
                    <td>
                        <?php foreach ($v['gezet'] ?? [] as $veld => $waarde): ?>
                            <div><code><?= htmlspecialchars($veld) ?></code> = <?= htmlspecialchars(json_encode($waarde)) ?></div>
                        <?php endforeach; ?>
                        <?= ($v['gezet'] ?? []) === [] ? '<span class="cma-tool__hint">—</span>' : '' ?>
                    </td>
                    <td>
                        <?php foreach ($v['overgeslagen'] ?? [] as $veld => $reden): ?>
                            <div><code><?= htmlspecialchars($veld) ?></code>: <?= htmlspecialchars($reden) ?></div>
                        <?php endforeach; ?>
                        <?php foreach ($v['onbekend'] ?? [] as $veld): ?>
                            <div><code><?= htmlspecialchars($veld) ?></code>: staat niet in de definitie</div>
                        <?php endforeach; ?>
                        <?php if (!empty($v['status']) && ($v['gezet'] ?? []) === [] && ($v['overgeslagen'] ?? []) === []): ?>
                            <?= htmlspecialchars($v['status']) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h3>Alle gevonden startwaarden (<?= count($regels) ?>)</h3>
        <table class="datatable">
            <thead><tr><th>Formulier</th><th>Veld</th><th>In de repository</th><th>Gelezen als</th><th>Besluit</th></tr></thead>
            <tbody>
            <?php foreach ($regels as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['formNaam']) ?></td>
                    <td><code><?= htmlspecialchars($r['veld']) ?></code></td>
                    <td><code><?= htmlspecialchars($r['ruw']) ?></code></td>
                    <td><?= htmlspecialchars($r['soort']) ?></td>
                    <td>
                        <?php if ($r['besluit'] === StartwaardeMigratie::OVERNEMEN): ?>
                            overnemen als <?= htmlspecialchars(json_encode($r['waarde'])) ?>
                        <?php else: ?>
                            <span class="cma-tool__hint"><?= htmlspecialchars($r['reden']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
