<?php
/**
 * documentation.php — CMA developer + administrator documentation hub.
 *
 * Layout: cma-tree sidebar listing topics on the left, topic content
 * on the right. Topic selected via ?topic=<slug>; default is the
 * index / overview pane.
 *
 * Adding a new topic:
 *   1. Add an entry to $topics below (slug, label, icon, render function).
 *   2. Implement render_doc_<slug>() in this file.
 *   3. Mention the topic in the overview pane (in render_doc_overview).
 *   4. CLAUDE.md section "Documentation maintenance" describes when a
 *      topic needs updating (every PR that changes the surface it covers).
 */

use App\Library\Request;
use App\Library\Response;
use Cma\SecurityHelper;
use Cma\ToolbarHelper;

require_once __DIR__ . '/../bootstrap.inc';

if (!SecurityHelper::isAdmin()) {
    echo '<lib-message type="error">Geen toegang — alleen beheerders</lib-message>';
    exit;
}

Response::noCache();

// =========================================================================
// Topic registry — the single place that lists what documentation lives
// here. Each entry needs a slug, a human label, an lnr icon, and a render
// function. The sidebar is generated from this; the router dispatches via
// the slug. Keeping it dense + flat (no nested groups) on purpose — once
// we have more than ~12 topics it'll be worth grouping, not before.
// =========================================================================
$topics = [
    'overview'   => ['label' => 'Overzicht',          'icon' => 'lnr-book',     'render' => 'render_doc_overview'],
    'deployment' => ['label' => 'Deployment',         'icon' => 'lnr-rocket',   'render' => 'render_doc_deployment'],
];

$selected = strtolower(trim((string)Request::query('topic', 'overview')));
if (!isset($topics[$selected])) { $selected = 'overview'; }

cma_html_header('CMA - Documentatie');
echo '<body class="contentbody tools tool-docs">';
ToolbarHelper::start(true);
ToolbarHelper::title('Documentatie');
ToolbarHelper::separator();
ToolbarHelper::status($topics[$selected]['label']);
ToolbarHelper::end();
echo '<div id="c" class="tools">';
?>

<style>
.tool-docs .docs-layout { display: flex; gap: 20px; align-items: flex-start; }
.tool-docs .docs-sidebar { flex: 0 0 220px; position: sticky; top: 10px; }
.tool-docs .docs-content { flex: 1; min-width: 0; max-width: 880px; }
.tool-docs .docs-content h1 { margin: 0 0 6px; }
.tool-docs .docs-content h2 { margin-top: 28px; padding-top: 10px; border-top: 1px solid var(--border-color, #e0e0e0); }
.tool-docs .docs-content h3 { margin-top: 18px; }
.tool-docs .docs-content p { line-height: 1.55; }
.tool-docs .docs-content code { padding: 1px 4px; background: var(--bg-surface-alt, #f6f8fa); border-radius: 3px; font-size: 0.9em; }
.tool-docs .docs-content pre { padding: 12px 14px; background: #1e1e1e; color: #d4d4d4; border-radius: 4px; overflow-x: auto; font-size: var(--font-size-sm); }
.tool-docs .docs-content pre code { padding: 0; background: transparent; color: inherit; font-size: inherit; }
.tool-docs .docs-content ul, .tool-docs .docs-content ol { padding-left: 22px; line-height: 1.55; }
.tool-docs .docs-content table { width: 100%; margin: 12px 0; }
.tool-docs .docs-content .docs-callout { padding: 12px 14px; margin: 12px 0; border-left: 3px solid var(--color-info, #077ab2); background: var(--bg-surface-alt, #f6f8fa); border-radius: 4px; }
.tool-docs .docs-content .docs-callout--warn { border-left-color: var(--color-warning, #d4a017); background: #fffbe6; }
.tool-docs .docs-meta { color: var(--text-muted, #6c757d); font-size: var(--font-size-sm); margin: 6px 0 18px; }
</style>

<div class="docs-layout">
    <nav class="docs-sidebar">
        <cma-tree id="docsNav" storage-key="docs_nav"></cma-tree>
    </nav>
    <div class="docs-content">
        <?php call_user_func($topics[$selected]['render']); ?>
    </div>
</div>

<script>
(function () {
    var nav = document.getElementById('docsNav');
    if (!nav) return;
    var data = <?= json_encode(array_map(function ($slug, $t) {
        return [
            'label' => $t['label'],
            'icon'  => $t['icon'],
            'href'  => 'documentation.php' . ($slug === 'overview' ? '' : '?topic=' . $slug),
        ];
    }, array_keys($topics), $topics)) ?>;
    nav.setData(data);
    nav.selectByHref(<?= json_encode('documentation.php' . ($selected === 'overview' ? '' : '?topic=' . $selected)) ?>);
})();
</script>

<?php
echo '</div></body>';

// =========================================================================
// Topic renderers — one function per topic, mirroring the $topics map
// keys. Each renderer outputs the right-pane HTML for its topic. Use
// the standard tools-page classes (cma-tool__strong, code, lib-message,
// listtable) so the look matches the rest of the CMA. NO <strong>/<em>
// per CLAUDE.md hard rule.
// =========================================================================

function render_doc_overview(): void
{
    ?>
    <h1>Documentatie</h1>
    <p class="docs-meta">Onderwerpen geselecteerd voor ontwikkelaars en beheerders. Selecteer een onderwerp in de zijbalk.</p>

    <div class="docs-callout">
        Deze documentatie is onderdeel van het <code>stenversonline/platform</code> package. Wijzigingen leven naast de code — bij elke PR die een hier beschreven gebied raakt, hoort het bijbehorende topic ook bijgewerkt te worden. Zie de "Documentation maintenance" sectie in <code>CLAUDE.md</code> voor de workflow.
    </div>

    <h2>Beschikbare onderwerpen</h2>
    <table class="listtable">
        <thead>
            <tr class="listheader">
                <th style="width:200px">Onderwerp</th>
                <th>Voor wie</th>
                <th>Inhoud</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><a href="documentation.php?topic=deployment"><span class="lnr lnr-rocket"></span> Deployment</a></td>
                <td>Beheerder / DevOps</td>
                <td>Hoe deploys werken (deploy_webhook), vereiste <code>.env</code> instellingen, link naar de deploy-log, troubleshooting.</td>
            </tr>
            <tr>
                <td><a href="storybook.php"><span class="lnr lnr-book"></span> Component Storybook</a></td>
                <td>Frontend ontwikkelaar</td>
                <td>Levende voorbeelden van alle <code>lib-*</code> en <code>cma-*</code> webcomponents en helpers (libAlert, libConfirm, LibLog).</td>
            </tr>
        </tbody>
    </table>

    <h2>Externe referenties</h2>
    <ul>
        <li>Package repository: <a href="https://github.com/DStenvers/cma_platform" target="_blank" rel="noopener">github.com/DStenvers/cma_platform</a></li>
        <li>CLAUDE.md (in repo root) — werkprincipes en hard rules</li>
        <li><code>cma/docs/</code> — bestaande markdown documenten (api-reference, forms, json, logging, iis-setup, architecture_review)</li>
    </ul>
    <?php
}

function render_doc_deployment(): void
{
    $deployLogHref = 'logreader.php?log=deploy';
    $logFile       = dirname(__DIR__, 2) . '/logs/deploy.log';
    $envFile       = dirname(__DIR__, 2) . '/.env';
    ?>
    <h1>Deployment</h1>
    <p class="docs-meta">Hoe code op een consumer-site terechtkomt en welke instellingen daarvoor nodig zijn.</p>

    <h2>Overzicht</h2>
    <p>
        Deploys verlopen via <code>cma/tools/deploy_webhook_standalone.php</code> — een
        standalone, single-file webhook-endpoint dat GitHub-push-events ontvangt en in
        de site-root een commando-pipeline draait. Belangrijkste stappen:
    </p>
    <ol>
        <li><span class="cma-tool__strong">Verificatie</span> — HMAC-SHA256 signature (<code>X-Hub-Signature-256</code>) wordt getoetst tegen <code>DEPLOY_SECRET</code>.</li>
        <li><span class="cma-tool__strong">Vroege response</span> — 202 Accepted wordt teruggegeven en de verbinding losgekoppeld (<code>fastcgi_finish_request</code>); rest draait async.</li>
        <li><span class="cma-tool__strong">Pipeline</span> — <code>;</code>-gescheiden commando's draaien in <code>$siteRoot</code> (default: <code>git pull --ff-only origin {branch}</code>).</li>
        <li><span class="cma-tool__strong">Composer update</span> — sinds v1.13.0 update de webhook automatisch het platform-package zodat <code>vendor/</code> mee-loopt met de net-gesyncde <code>cma/</code> files.</li>
        <li><span class="cma-tool__strong">Recycle</span> — touch op <code>web.config</code> om de IIS app-pool te recyclen.</li>
        <li><span class="cma-tool__strong">Post-hook</span> — optionele project-side PHP script (<code>deploy_post.php</code>) voor cache-flushes / migration-runners.</li>
        <li><span class="cma-tool__strong">Logging</span> — alle output landt in <code>logs/deploy.log</code>, ge-banner per run.</li>
    </ol>

    <h2>Deploy-log bekijken</h2>
    <p>
        <a class="btn btn-primary" href="<?= htmlspecialchars($deployLogHref) ?>" target="_top">
            <span class="lnr lnr-list"></span> Open deploy-log in logreader
        </a>
    </p>
    <p class="docs-meta">
        Pad op de schijf: <code><?= htmlspecialchars($logFile) ?></code> (override via <code>DEPLOY_LOG_FILE</code>).
        Elke run is voorzien van een banner met branch + commit zodat je in één oogopslag ziet welke deploy welke output produceerde. <code>OK: deploy &lt;sha&gt;</code> betekent succes, <code>FAILED: deploy &lt;sha&gt;</code> betekent een breek in de pipeline.
    </p>

    <h2>Vereiste <code>.env</code> instellingen</h2>
    <p>Plaats deze in het actieve env-bestand (te zien in <a href="tools_serverinfo.php" target="_top">Server informatie → Omgeving → Actief .env bestand</a>):</p>
    <table class="listtable">
        <thead>
            <tr class="listheader">
                <th style="width:230px">Variabele</th>
                <th style="width:90px">Verplicht</th>
                <th>Default</th>
                <th>Doel</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>DEPLOY_SECRET</code></td>
                <td><lib-label type="error">ja</lib-label></td>
                <td>—</td>
                <td>Shared secret tussen GitHub-webhook en de server. Wordt gebruikt voor HMAC-validatie. Genereer een 64-char hex met <code>openssl rand -hex 32</code>.</td>
            </tr>
            <tr>
                <td><code>DEPLOY_BRANCH</code></td>
                <td><lib-label type="information">nee</lib-label></td>
                <td><code>main</code></td>
                <td>Welke branch deployen. Push-events op andere branches worden genegeerd.</td>
            </tr>
            <tr>
                <td><code>DEPLOY_SITE_ROOT</code></td>
                <td><lib-label type="information">nee</lib-label></td>
                <td>auto</td>
                <td>Pad naar de git working tree. Auto-detect via <code>__DIR__</code>; override nodig wanneer het webhook-script elders draait dan in de repo.</td>
            </tr>
            <tr>
                <td><code>DEPLOY_PIPELINE</code></td>
                <td><lib-label type="information">nee</lib-label></td>
                <td><code>git pull --ff-only origin {branch}</code></td>
                <td><code>;</code>-gescheiden commando's. <code>{branch}</code> wordt vervangen door <code>DEPLOY_BRANCH</code>.</td>
            </tr>
            <tr>
                <td><code>DEPLOY_COMPOSER_UPDATE</code></td>
                <td><lib-label type="information">nee</lib-label></td>
                <td><code>stenversonline/platform</code></td>
                <td>Comma-separated pakketten om na de pipeline te updaten. Set <code>-</code> om de stap over te slaan. Voorkomt vendor-drift t.o.v. de net-gesyncde <code>cma/</code> files.</td>
            </tr>
            <tr>
                <td><code>DEPLOY_RECYCLE_TOUCH</code></td>
                <td><lib-label type="information">nee</lib-label></td>
                <td><code>web.config</code></td>
                <td>Bestand om te touch'en na een succesvolle pipeline (IIS app-pool recycle). Set <code>-</code> om over te slaan.</td>
            </tr>
            <tr>
                <td><code>DEPLOY_LOG_FILE</code></td>
                <td><lib-label type="information">nee</lib-label></td>
                <td><code>logs/deploy.log</code></td>
                <td>Locatie van de deploy-log. Het pad moet schrijfbaar zijn voor de webserver-user.</td>
            </tr>
            <tr>
                <td><code>DEPLOY_POST_HOOK</code></td>
                <td><lib-label type="information">nee</lib-label></td>
                <td><code>deploy_post.php</code></td>
                <td>Project-side PHP script dat NA recycle wordt geïncludeerd. Typisch voor cache-flushes, schema-migraties, image-profile backfills. Set <code>-</code> om over te slaan.</td>
            </tr>
        </tbody>
    </table>

    <p>Actief env-bestand op deze site: <code><?= htmlspecialchars((string)($GLOBALS['_env_file'] ?? '.env')) ?></code>.</p>

    <h2>GitHub-webhook instellen</h2>
    <ol>
        <li>Open de GitHub repo → Settings → Webhooks → Add webhook.</li>
        <li>Payload URL: <code>https://&lt;host&gt;/cma/tools/deploy_webhook_standalone.php</code></li>
        <li>Content type: <code>application/json</code></li>
        <li>Secret: zelfde waarde als <code>DEPLOY_SECRET</code> in <code>.env</code>.</li>
        <li>Events: alleen "Push" events.</li>
    </ol>
    <p class="docs-meta">
        GitHub toetst de webhook bij toevoeging met een ping; succesvolle response is HTTP 202 (asynchrone acceptatie). Een 401 betekent secret-mismatch, 403 betekent verkeerde branch.
    </p>

    <h2>Eerste deploy</h2>
    <p>Vóór de eerste webhook-driven deploy moet de server-side klaarstaan:</p>
    <ol>
        <li>Repo gecloned in <code>$siteRoot</code> (typisch <code>C:\wwwroot\&lt;site&gt;</code>).</li>
        <li>Eenmalig handmatig <code>composer install</code> om <code>vendor/</code> op te bouwen.</li>
        <li><code>.env</code> aangemaakt met <code>DEPLOY_SECRET</code> + andere project-specifieke env vars.</li>
        <li>Schrijfrechten voor de IIS-user op: <code>vendor/</code>, <code>logs/</code>, <code>cache/</code>, <code>cma/</code>, <code>library/</code>, <code>module/</code>, <code>web.config</code>.</li>
        <li>Composer in <code>PATH</code> van de IIS-user — anders faalt de auto-update-stap (logged als <code>WARN</code>, geen breek).</li>
    </ol>

    <h2>Troubleshooting</h2>
    <table class="listtable">
        <thead>
            <tr class="listheader">
                <th style="width:280px">Symptoom</th>
                <th>Oorzaak / Fix</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>GitHub-webhook delivery faalt met 401</td>
                <td><code>DEPLOY_SECRET</code> op de server stemt niet overeen met de Secret in GitHub. Roteer beide tegelijk.</td>
            </tr>
            <tr>
                <td>Webhook accepteert (202) maar er gebeurt niks</td>
                <td>Pipeline-fout. Kijk in <code>logs/deploy.log</code> — de output van het mislukte commando staat onder de <code>RUN:</code> / <code>EXIT: 1</code> regels.</td>
            </tr>
            <tr>
                <td>vdev-main in plaats van het versienummer in de profiel-balk</td>
                <td><code>vendor/</code> is niet ververst. Triggert de symptomen dat nieuwe <code>cma/</code> features falen omdat hun helpers in <code>vendor/</code> ontbreken. Fix: voer <code>composer update stenversonline/platform</code> handmatig, of laat de webhook v1.13.0+ dit automatisch doen.</td>
            </tr>
            <tr>
                <td>Class "App\Library\Email" not found</td>
                <td>Zelfde oorzaak — vendor is stale. v1.12.1+ heeft een class_exists-guard zodat CMA niet meer crash't, maar de feature blijft offline tot composer update.</td>
            </tr>
            <tr>
                <td><code>/cma/dashboard</code> geeft 404, <code>/cma/dashboard.php</code> wel 200</td>
                <td>Parent web.config in de site-root vangt <code>/cma/*</code> op voordat het kind <code>cma/web.config</code> kan rewriten. Voeg de <code>Skip /cma to child config</code> regel toe — zie <code>templates/web.config.template</code> in v1.14.2+.</td>
            </tr>
        </tbody>
    </table>
    <?php
}
