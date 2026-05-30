<?php
/**
 * documentation.php — CMA developer + administrator documentation hub.
 *
 * Topic structure supports nested groups in the sidebar but a flat slug
 * namespace for routing. Each leaf entry registers either:
 *   - 'render' => '<function name>'  (internal topic, rendered inline)
 *   - 'href'   => '<url>'            (external link, opens directly)
 *
 * Adding a new topic:
 *   1. Add a leaf entry to $topics (slug, label, icon, render).
 *   2. Implement render_doc_<slug>() in this file.
 *   3. Mention the topic in the overview render (render_doc_overview).
 *   4. Cross-link from related topics with
 *      <a href="documentation.php?topic=<slug>">…</a>.
 *
 * Adding a new group (admin / dev / cross-cutting / external):
 *   Only when none of the existing groups fits. Don't pre-create empty
 *   groups for topics that don't yet exist — flat-until-it-hurts. See
 *   CLAUDE.md "Documentation maintenance".
 *
 * Reference content (markdown files in cma/docs/) is migrated INTO this
 * file's topics, not linked to. CLAUDE.md forbids creating new .md files
 * — single source of truth lives here.
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
// Topic registry — single source of truth for sidebar AND router.
// Nested 'children' arrays render as cma-tree folders in the sidebar.
// External 'href' entries render as direct links (no internal routing).
// =========================================================================
$topics = [
    'overview' => [
        'label'  => 'Overzicht',
        'icon'   => 'lnr-book',
        'render' => 'render_doc_overview',
    ],
    'admin' => [
        'label' => 'Voor beheerders',
        'icon'  => 'lnr-user',
        'children' => [
            'installation' => ['label' => 'Installatie',           'icon' => 'lnr-download',    'render' => 'render_doc_installation'],
            'environment'  => ['label' => 'Omgeving & .env',       'icon' => 'lnr-cog',         'render' => 'render_doc_environment'],
            'deployment'   => ['label' => 'Deployment',            'icon' => 'lnr-rocket',      'render' => 'render_doc_deployment'],
            'backups'      => ['label' => 'Backups & herstel',     'icon' => 'lnr-database',    'render' => 'render_doc_backups'],
            'logs'         => ['label' => 'Logs & monitoring',     'icon' => 'lnr-list',        'render' => 'render_doc_logs'],
            'security'     => ['label' => 'Beveiliging',           'icon' => 'lnr-lock',        'render' => 'render_doc_security'],
            'iis_config'   => ['label' => 'IIS-configuratie',      'icon' => 'lnr-server',      'render' => 'render_doc_iis_config'],
        ],
    ],
    'storybook' => [
        'label' => 'Component Storybook',
        'icon'  => 'lnr-bubble',
        'href'  => 'storybook.php',
    ],
];

// Flatten registry into slug → render lookup. Recursive in case we ever
// add deeper nesting (we won't, per CLAUDE.md "flat-until-12" rule).
function flatten_topics(array $tree, array &$out = []): array
{
    foreach ($tree as $slug => $node) {
        if (isset($node['children'])) {
            flatten_topics($node['children'], $out);
        } else {
            $out[$slug] = $node;
        }
    }
    return $out;
}
$flat = flatten_topics($topics);

$selected = strtolower(trim((string)Request::query('topic', 'overview')));
if (!isset($flat[$selected]) || !isset($flat[$selected]['render'])) {
    $selected = 'overview';
}

cma_html_header('CMA - Documentatie');
echo '<body class="contentbody tools tool-docs">';
ToolbarHelper::start(true);
ToolbarHelper::title('Documentatie');
ToolbarHelper::separator();
ToolbarHelper::status($flat[$selected]['label']);
ToolbarHelper::end();
echo '<div id="c" class="tools">';
?>

<style>
.tool-docs .docs-layout { display: flex; gap: 20px; align-items: flex-start; }
.tool-docs .docs-sidebar { flex: 0 0 240px; position: sticky; top: 10px; }
.tool-docs .docs-content { flex: 1; min-width: 0; max-width: 900px; }
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
.tool-docs .docs-content .docs-callout--danger { border-left-color: var(--color-error, #c0392b); background: #fdedec; }
.tool-docs .docs-meta { color: var(--text-muted, #6c757d); font-size: var(--font-size-sm); margin: 6px 0 18px; }
.tool-docs .docs-content .seealso { margin-top: 24px; padding-top: 14px; border-top: 1px solid var(--border-color, #e0e0e0); color: var(--text-muted, #6c757d); font-size: var(--font-size-sm); }
</style>

<div class="docs-layout">
    <nav class="docs-sidebar">
        <cma-tree id="docsNav" storage-key="docs_nav"></cma-tree>
    </nav>
    <div class="docs-content">
        <?php call_user_func($flat[$selected]['render']); ?>
    </div>
</div>

<script>
(function () {
    var nav = document.getElementById('docsNav');
    if (!nav) return;
    var data = <?= json_encode(build_sidebar_data($topics)) ?>;
    nav.setData(data);
    nav.expandAll();
    nav.selectByHref(<?= json_encode(slug_to_href($selected)) ?>);
})();
</script>

<?php
echo '</div></body>';

// =========================================================================
// Sidebar tree builder — converts $topics registry into the data shape
// cma-tree expects (array of nodes with label/icon/href/children).
// =========================================================================
function build_sidebar_data(array $tree): array
{
    $out = [];
    foreach ($tree as $slug => $node) {
        $entry = [
            'label' => $node['label'],
            'icon'  => $node['icon'] ?? null,
        ];
        if (isset($node['children'])) {
            $entry['type']     = 'folder';
            $entry['children'] = build_sidebar_data($node['children']);
        } elseif (isset($node['href'])) {
            $entry['href'] = $node['href'];
        } else {
            $entry['href'] = slug_to_href($slug);
        }
        $out[] = $entry;
    }
    return $out;
}

function slug_to_href(string $slug): string
{
    return $slug === 'overview' ? 'documentation.php' : 'documentation.php?topic=' . $slug;
}

// =========================================================================
// === TOPIC RENDERERS ====================================================
// One render_doc_<slug>() function per leaf topic. Output goes into the
// right pane. Use the standard tools-page classes (cma-tool__strong,
// docs-callout, code, lib-message, listtable). NO <strong>/<em>/<b>/<i>.
// =========================================================================

function render_doc_overview(): void
{
    ?>
    <h1>Documentatie</h1>
    <p class="docs-meta">Onderwerpen voor ontwikkelaars en beheerders die werken met het <code>stenversonline/platform</code> package.</p>

    <div class="docs-callout">
        Deze documentatie leeft naast de code. Bij elke PR die een hier beschreven gebied raakt, hoort het bijbehorende topic ook bijgewerkt te worden. Zie de "Documentation maintenance" sectie in <code>CLAUDE.md</code> voor de workflow.
    </div>

    <h2>Voor beheerders</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:220px">Onderwerp</th><th>Inhoud</th></tr></thead>
        <tbody>
            <tr><td><a href="documentation.php?topic=installation"><span class="lnr lnr-download"></span> Installatie</a></td><td>Nieuwe site opzetten: composer, file-rechten, _bootstrap.php template chain.</td></tr>
            <tr><td><a href="documentation.php?topic=environment"><span class="lnr lnr-cog"></span> Omgeving &amp; .env</a></td><td>APP_ENVIRONMENT, file-pick volgorde, omgeving-codes en de env-wissel knop.</td></tr>
            <tr><td><a href="documentation.php?topic=deployment"><span class="lnr lnr-rocket"></span> Deployment</a></td><td>Hoe de deploy_webhook werkt, alle DEPLOY_* env-vars, link naar deploy-log.</td></tr>
            <tr><td><a href="documentation.php?topic=backups"><span class="lnr lnr-database"></span> Backups &amp; herstel</a></td><td>BackupService, waar backups leven, SQLite emergency-recovery.</td></tr>
            <tr><td><a href="documentation.php?topic=logs"><span class="lnr lnr-list"></span> Logs &amp; monitoring</a></td><td>Welke log waar ligt, logreader, cmamonitoring, retentie.</td></tr>
            <tr><td><a href="documentation.php?topic=security"><span class="lnr lnr-lock"></span> Beveiliging</a></td><td>Secrets, sessie-cookies, geblokkeerde extensies, hiddenSegments.</td></tr>
            <tr><td><a href="documentation.php?topic=iis_config"><span class="lnr lnr-server"></span> IIS-configuratie</a></td><td>web.config layering, URL Rewrite Module, app-pool recycle.</td></tr>
        </tbody>
    </table>

    <h2>Andere bronnen</h2>
    <ul>
        <li><a href="storybook.php"><span class="lnr lnr-bubble"></span> Component Storybook</a> — levende voorbeelden van alle <code>lib-*</code> en <code>cma-*</code> web components, plus JS-helpers (libAlert, libConfirm, libToast, LibLog).</li>
        <li>Package repository: <a href="https://github.com/DStenvers/cma_platform" target="_blank" rel="noopener">github.com/DStenvers/cma_platform</a></li>
        <li><code>CLAUDE.md</code> (in repo root) — werkprincipes, hard rules, documentation-maintenance workflow.</li>
    </ul>
    <?php
}

// -------------------------------------------------------------------------
// VOOR BEHEERDERS
// -------------------------------------------------------------------------

function render_doc_installation(): void
{
    ?>
    <h1>Installatie</h1>
    <p class="docs-meta">Van leeg directory tot werkende CMA op een Windows-IIS-host.</p>

    <h2>Vereisten</h2>
    <ul>
        <li>Windows Server (of Windows 10/11) met IIS geïnstalleerd.</li>
        <li><span class="cma-tool__strong">IIS URL Rewrite Module</span> — download van <a href="https://www.iis.net/downloads/microsoft/url-rewrite" target="_blank" rel="noopener">iis.net</a>. Zonder deze module worden alle friendly-URL rewrites in <code>web.config</code> stilzwijgend genegeerd.</li>
        <li>PHP 8.4+ via FastCGI handler (PHP 8.5 wordt ondersteund maar test consumer-code op nieuwe deprecaties).</li>
        <li>Composer 2.x in het <code>PATH</code> van de IIS app-pool user (anders faalt <code>DEPLOY_COMPOSER_UPDATE</code> stilzwijgend tijdens deploys).</li>
        <li>Git voor pull-deploys.</li>
    </ul>

    <h2>Server-level: server variables ontgrendelen</h2>
    <p>De <code>cma/web.config</code> gebruikt custom server variables (<code>HTTP_X_ORIGINAL_FILE</code>, <code>HTTP_X_TOOL_NAME</code>) voor URL rewriting. IIS lockt deze sectie standaard op server-niveau af. Eenmalig als Administrator uitvoeren:</p>
    <pre><code>%windir%\system32\inetsrv\appcmd.exe unlock config -section:system.webServer/rewrite/allowedServerVariables</code></pre>
    <p>Symptoom als dit niet gebeurd is:</p>
    <pre><code>Deze configuratiesectie kan niet worden gebruikt voor dit pad.
Dit gebeurt wanneer de sectie is vergrendeld op bovenliggend niveau.</code></pre>

    <h2>Project opzetten</h2>
    <ol>
        <li>Maak een lege site-folder, typisch <code>C:\wwwroot\&lt;site&gt;</code>.</li>
        <li>Clone de consumer-repo erin (de site-specifieke code, niet dit platform-package).</li>
        <li>Voeg het platform toe aan de consumer's <code>composer.json</code>:
            <pre><code>{
    "require": {
        "stenversonline/platform": "^1.0"
    },
    "scripts": {
        "post-install-cmd": "App\\Library\\Installer::postInstall",
        "post-update-cmd":  "App\\Library\\Installer::postUpdate"
    }
}</code></pre>
            De <code>scripts</code> sectie is cruciaal — zonder die hooks draait de Installer niet en blijven <code>cma/</code>, <code>library/</code> bestanden achter in <code>vendor/</code> in plaats van naar de project-root gesynct te worden.
        </li>
        <li><code>composer install</code> uitvoeren. De Installer runt automatisch en kopieert <code>library/</code>, <code>cma/</code>, <code>module/</code> naar de project-root, en plaatst eenmalige template-bestanden (<code>_bootstrap.php</code>, <code>web.config</code>, <code>app.php</code>, <code>global.asa.php</code>, <code>.env.example</code>) als die nog niet bestaan.</li>
        <li><code>.env</code> aanmaken op basis van <code>.env.example</code> met minimaal:
            <pre><code>APP_ENVIRONMENT=T
DEPLOY_SECRET=&lt;64-char hex via openssl rand -hex 32&gt;</code></pre>
            Project-specifiek aanvullen met SMTP, DB-paden, LLM-config, etc.
        </li>
        <li>Project-specifieke configs zet je in <code>data/</code> op de site-root: <code>data/app.json</code>, <code>data/databases.json</code>, <code>data/menu.json</code>, <code>data/reports.json</code>. Het platform leest deze EERST en valt terug op de defaults in <code>cma/config/&lt;naam&gt;.json</code> (die laatste worden door composer update overschreven — niet bewerken).</li>
    </ol>

    <h2>File-rechten checklist</h2>
    <p>De IIS app-pool user (typisch <code>IIS APPPOOL\&lt;sitename&gt;</code>) heeft schrijfrechten nodig op:</p>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:240px">Pad</th><th>Waarom</th></tr></thead>
        <tbody>
            <tr><td><code>vendor/</code></td><td><code>composer update</code> tijdens deploys.</td></tr>
            <tr><td><code>logs/</code></td><td>deploy.log, php_errors.log.</td></tr>
            <tr><td><code>cache/</code></td><td>Performance-logs, debug-logs, opcache-files.</td></tr>
            <tr><td><code>cma/</code></td><td>Installer kopieert hier nieuwe bestanden naartoe.</td></tr>
            <tr><td><code>library/</code></td><td>Installer-sync target.</td></tr>
            <tr><td><code>module/</code></td><td>Installer-sync target.</td></tr>
            <tr><td><code>web.config</code></td><td>App-pool recycle via touch tijdens deploys.</td></tr>
            <tr><td><code>db/</code> (bij SQLite)</td><td>SQLite database write, WAL/SHM files.</td></tr>
            <tr><td><code>sessions/</code></td><td>PHP-session files.</td></tr>
            <tr><td><code>backup/</code></td><td>Database backups door <code>BackupService</code>.</td></tr>
            <tr><td>Site-root <code>.env</code></td><td>Env-switch UI op de Omgeving-tab schrijft hierheen.</td></tr>
        </tbody>
    </table>

    <h2>De _bootstrap.php template chain</h2>
    <p>De Installer plaatst drie samenwerkende bestanden in de site-root als ze nog niet bestaan:</p>
    <ul>
        <li><code>_bootstrap.php</code> — auto-prepended door IIS via <code>web.config</code>. Roept het platform's <code>App\Library\Bootstrap::init()</code> aan en laadt project-specifieke globals.</li>
        <li><code>_bootstrap_wrapper.php</code> — wordt aangeroepen door URL Rewrite rules; include't <code>_bootstrap.php</code> + het target-script.</li>
        <li><code>_bootstrap_constants.inc</code> — bevat <code>STRCHARSET</code>, <code>PROJECTNAAM</code> en andere site-constanten die door legacy code gelezen worden.</li>
    </ul>
    <p>Deze bestanden zijn templates: ze worden eenmalig gekopieerd bij eerste install en daarna NOOIT overschreven door composer update. Wijzigingen in jouw site-specifieke <code>_bootstrap.php</code> overleven dus elke upgrade.</p>

    <h2>Eerste deploy + voorts</h2>
    <p>Zie <a href="documentation.php?topic=deployment">Deployment</a> voor webhook-setup en de DEPLOY_* env-vars.</p>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=iis_config">IIS-configuratie</a>, <a href="documentation.php?topic=environment">Omgeving &amp; .env</a>, <a href="documentation.php?topic=security">Beveiliging</a>.
    </div>
    <?php
}

function render_doc_environment(): void
{
    $activeEnv = (string)($GLOBALS['_env_file'] ?? '.env');
    ?>
    <h1>Omgeving &amp; .env</h1>
    <p class="docs-meta">Hoe het platform bepaalt welke <code>.env</code> wordt geladen en wat <code>APP_ENVIRONMENT</code> precies betekent.</p>

    <h2>Omgeving-codes</h2>
    <p>Alle omgeving-bewuste code leest <code>Application::get('omgeving')</code> dat een ééncijferige code teruggeeft:</p>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:80px">Code</th><th>Naam</th><th>Gebruik</th></tr></thead>
        <tbody>
            <tr><td><code>O</code></td><td>Ontwikkeling</td><td>Lokale dev-omgeving van de developer.</td></tr>
            <tr><td><code>L</code></td><td>Lokaal</td><td>Lokale acceptatie-clone met productie-achtige data.</td></tr>
            <tr><td><code>T</code></td><td>Test</td><td>Shared test-omgeving voor de hele organisatie.</td></tr>
            <tr><td><code>A</code></td><td>Acceptatie</td><td>Pre-production staging.</td></tr>
            <tr><td><code>P</code></td><td>Productie</td><td>Live.</td></tr>
        </tbody>
    </table>
    <p>Debug-mode wordt automatisch geactiveerd voor alles wat niet <code>P</code> is — error_reporting=E_ALL en display_errors=on.</p>

    <h2>Bestandskeuze: welke .env wordt geladen?</h2>
    <p><code>Bootstrap::prepareDotenv()</code> bepaalt het in deze volgorde:</p>
    <ol>
        <li>Als de aanroeper een explicit <code>env_file</code> meegaf in de Bootstrap::init() config — gebruik die.</li>
        <li>Als <code>$_ENV['APP_ENVIRONMENT']</code> of <code>$_SERVER['APP_ENVIRONMENT']</code> gezet is op O/L/T/A/P — gebruik het bijbehorende <code>.env.development</code>/<code>.env.local</code>/<code>.env.test</code>/<code>.env.acceptance</code>/<code>.env.production</code>.</li>
        <li>Anders: zoek de eerst-bestaande in volgorde <code>.env.local</code> → <code>.env.development</code> → <code>.env.test</code> → <code>.env.acceptance</code> → <code>.env.production</code>.</li>
        <li>Fallback: <code>.env</code>.</li>
    </ol>
    <p>Het uiteindelijke bestand wordt opgeslagen in <code>$GLOBALS['_env_file']</code> en is zichtbaar op de <a href="tools_serverinfo.php" target="_top">Omgeving-tab</a> als "Actief .env bestand".</p>

    <div class="docs-callout docs-callout--warn">
        <span class="cma-tool__strong">Belangrijk:</span> de OS-level <code>APP_ENVIRONMENT</code> wordt gelezen <em class="cma-tool__em">voordat</em> phpdotenv een file laadt. Het zetten van <code>APP_ENVIRONMENT</code> in een <code>.env</code> bestand zelf heeft dus geen invloed op welke file gekozen wordt — alleen op wat <code>Application::get('omgeving')</code> uiteindelijk teruggeeft.
    </div>

    <h2>Actief .env bestand op deze site</h2>
    <p>Op de huidige site is dat: <code><?= htmlspecialchars($activeEnv) ?></code></p>

    <h2>De env-wissel knop</h2>
    <p>Op de Omgeving-tab van <a href="tools_serverinfo.php" target="_top">Server informatie</a> staat een "Wissel naar T/P" knop naast het omgeving-label. Die schrijft <code>APP_ENVIRONMENT=&lt;target&gt;</code> naar het <span class="cma-tool__strong">actieve</span> env-bestand (niet hardcoded <code>.env</code>; sinds v1.14.3 wordt het juiste bestand gepakt). Na refresh leest bootstrap de nieuwe waarde.</p>
    <p>Toggle-doel: <code>P</code> → <code>T</code>; alles anders → <code>P</code>. Een bevestigingsdialoog meldt dat de wijziging in <code>.env</code> wordt weggeschreven en geldt voor alle gebruikers van de site.</p>

    <h2>Welke env-vars zijn er?</h2>
    <p>Per onderwerp:</p>
    <ul>
        <li><span class="cma-tool__strong">Deploy</span>: <code>DEPLOY_SECRET</code>, <code>DEPLOY_BRANCH</code>, <code>DEPLOY_SITE_ROOT</code>, <code>DEPLOY_PIPELINE</code>, <code>DEPLOY_COMPOSER_UPDATE</code>, <code>DEPLOY_RECYCLE_TOUCH</code>, <code>DEPLOY_LOG_FILE</code>, <code>DEPLOY_POST_HOOK</code> — zie <a href="documentation.php?topic=deployment">Deployment</a>.</li>
        <li><span class="cma-tool__strong">LLM</span>: <code>LLM_PROVIDER</code>, <code>LLM_URL</code>, <code>LLM_MODEL</code>, <code>LLM_KEY</code>, <code>LLM_FALLBACK_MODEL</code>, <code>OCR_VISION_KEY</code>, <code>OCR_VISION_PROVIDER</code>, <code>LLM_MODELS_DIR</code> — zie <a href="documentation.php?topic=deployment">Deployment</a> en de LLM-tools.</li>
        <li><span class="cma-tool__strong">Logging</span>: <code>EMAIL_LOG_ENABLED</code>, <code>PERF_LOG_ENABLED</code>, <code>CACHE_LOG_ENABLED</code> — zie <a href="documentation.php?topic=logs">Logs &amp; monitoring</a>.</li>
        <li><span class="cma-tool__strong">Mail</span>: <code>mail_server</code>, <code>mail_server_port</code>, <code>mail_username</code>, <code>mail_password</code> via <code>Application::get</code> (uit <code>app.php</code>, niet uit <code>.env</code>).</li>
    </ul>

    <h2>Project-specifieke configs</h2>
    <p>Het platform splitst JSON-config in een platform-default-laag en een per-site override-laag:</p>
    <ul>
        <li><code>cma/config/&lt;naam&gt;.json</code> — platform-defaults, gebundeld met het pakket. <span class="cma-tool__strong">Worden door composer update overschreven.</span> Niet bewerken voor site-specifieke wijzigingen.</li>
        <li><code>data/&lt;naam&gt;.json</code> op de site-root — per-site overrides. Voor <code>app.json</code> en <code>databases.json</code> lezen <code>cma_get_app_logo()</code> en consumer-bootstraps deze EERST en vallen ze terug op <code>cma/config/...</code>. Voor <code>menu.json</code> en <code>reports.json</code> wijzen <code>MenuService::CONFIG_PATH</code> en <code>ReportsService::$configPath</code> rechtstreeks naar <code>data/&lt;naam&gt;.json</code>.</li>
    </ul>
    <p>De Installer raakt <code>data/</code> nooit aan (het is geen sync-target). De <code>PROTECTED_PATHS</code> lijst in <code>src/Installer.php</code> bevat de <code>data/...</code> entries als belt-and-braces — die check is nooit triggerable omdat de Installer alleen <code>cma/</code>, <code>library/</code>, <code>module/</code> synct, maar het maakt duidelijk welke paden per ontwerp project-bezit zijn.</p>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=installation">Installatie</a>, <a href="documentation.php?topic=deployment">Deployment</a>, <a href="documentation.php?topic=security">Beveiliging</a>.
    </div>
    <?php
}

function render_doc_deployment(): void
{
    $deployLogHref = 'logreader.php?log=deploy';
    $logFile       = dirname(__DIR__, 2) . '/logs/deploy.log';
    ?>
    <h1>Deployment</h1>
    <p class="docs-meta">Hoe code op een consumer-site terechtkomt en welke instellingen daarvoor nodig zijn.</p>

    <h2>Overzicht</h2>
    <p>Deploys verlopen via <code>cma/tools/deploy_webhook_standalone.php</code> — een standalone single-file webhook-endpoint dat GitHub push-events ontvangt en in de site-root een commando-pipeline draait. Belangrijkste stappen:</p>
    <ol>
        <li><span class="cma-tool__strong">Verificatie</span> — HMAC-SHA256 signature (<code>X-Hub-Signature-256</code>) getoetst tegen <code>DEPLOY_SECRET</code>.</li>
        <li><span class="cma-tool__strong">Vroege response</span> — 202 Accepted en de verbinding losgekoppeld (<code>fastcgi_finish_request</code>); de rest draait async.</li>
        <li><span class="cma-tool__strong">Pipeline</span> — <code>;</code>-gescheiden commando's in <code>$siteRoot</code> (default: <code>git pull --ff-only origin {branch}</code>).</li>
        <li><span class="cma-tool__strong">Composer update</span> — sinds v1.13.0 update de webhook automatisch het platform-package zodat <code>vendor/</code> mee-loopt met de net-gesyncde <code>cma/</code> files.</li>
        <li><span class="cma-tool__strong">Recycle</span> — touch op <code>web.config</code> om de IIS app-pool te recyclen.</li>
        <li><span class="cma-tool__strong">Post-hook</span> — optioneel project-side PHP script (<code>deploy_post.php</code>) voor cache-flushes en migrations.</li>
        <li><span class="cma-tool__strong">Logging</span> — alle output landt in <code>logs/deploy.log</code>, banner per run.</li>
    </ol>

    <h2>Deploy-log bekijken</h2>
    <p>
        <a class="btn btn-primary" href="<?= htmlspecialchars($deployLogHref) ?>" target="_top">
            <span class="lnr lnr-list"></span> Open deploy-log in logreader
        </a>
    </p>
    <p class="docs-meta">
        Pad op de schijf: <code><?= htmlspecialchars($logFile) ?></code> (override via <code>DEPLOY_LOG_FILE</code>). Elke run heeft een banner met branch + commit; <code>OK: deploy &lt;sha&gt;</code> betekent succes, <code>FAILED: deploy &lt;sha&gt;</code> betekent een breek in de pipeline.
    </p>

    <h2>Vereiste .env instellingen</h2>
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
            <tr><td><code>DEPLOY_SECRET</code></td><td><lib-label type="error">ja</lib-label></td><td>—</td><td>Shared secret tussen GitHub-webhook en de server. HMAC-validatie. Genereer met <code>openssl rand -hex 32</code>.</td></tr>
            <tr><td><code>DEPLOY_BRANCH</code></td><td><lib-label type="information">nee</lib-label></td><td><code>main</code></td><td>Welke branch deployen. Push-events op andere branches worden genegeerd.</td></tr>
            <tr><td><code>DEPLOY_SITE_ROOT</code></td><td><lib-label type="information">nee</lib-label></td><td>auto</td><td>Pad naar de git working tree. Auto via <code>__DIR__</code>; override nodig als het webhook-script elders draait.</td></tr>
            <tr><td><code>DEPLOY_PIPELINE</code></td><td><lib-label type="information">nee</lib-label></td><td><code>git pull --ff-only origin {branch}</code></td><td><code>;</code>-gescheiden commando's. <code>{branch}</code> wordt vervangen.</td></tr>
            <tr><td><code>DEPLOY_COMPOSER_UPDATE</code></td><td><lib-label type="information">nee</lib-label></td><td><code>stenversonline/platform</code></td><td>Pakketten om na de pipeline te updaten. Comma-separated. Set <code>-</code> om over te slaan.</td></tr>
            <tr><td><code>DEPLOY_RECYCLE_TOUCH</code></td><td><lib-label type="information">nee</lib-label></td><td><code>web.config</code></td><td>Bestand om te touch'en na succes (IIS app-pool recycle). Set <code>-</code> om over te slaan.</td></tr>
            <tr><td><code>DEPLOY_LOG_FILE</code></td><td><lib-label type="information">nee</lib-label></td><td><code>logs/deploy.log</code></td><td>Locatie van de deploy-log. Schrijfbaar voor de webserver-user.</td></tr>
            <tr><td><code>DEPLOY_POST_HOOK</code></td><td><lib-label type="information">nee</lib-label></td><td><code>deploy_post.php</code></td><td>Project-side PHP-script NA recycle. Cache-flushes, schema-migraties, image-profile backfills. Set <code>-</code> om over te slaan.</td></tr>
        </tbody>
    </table>
    <p>Actief env-bestand op deze site: <code><?= htmlspecialchars((string)($GLOBALS['_env_file'] ?? '.env')) ?></code>.</p>

    <h2>GitHub-webhook instellen</h2>
    <ol>
        <li>GitHub repo → Settings → Webhooks → Add webhook.</li>
        <li>Payload URL: <code>https://&lt;host&gt;/cma/tools/deploy_webhook_standalone.php</code></li>
        <li>Content type: <code>application/json</code></li>
        <li>Secret: zelfde waarde als <code>DEPLOY_SECRET</code> in <code>.env</code>.</li>
        <li>Events: alleen "Push" events.</li>
    </ol>
    <p class="docs-meta">Succesvolle response is HTTP 202 (asynchrone acceptatie). 401 = secret-mismatch; 403 = verkeerde branch.</p>

    <h2>Eerste deploy</h2>
    <ol>
        <li>Repo gecloned in <code>$siteRoot</code> (typisch <code>C:\wwwroot\&lt;site&gt;</code>).</li>
        <li>Eenmalig handmatig <code>composer install</code> om <code>vendor/</code> op te bouwen.</li>
        <li><code>.env</code> aangemaakt met <code>DEPLOY_SECRET</code> + project-specifieke env vars.</li>
        <li>Schrijfrechten voor de IIS-user — zie <a href="documentation.php?topic=installation">Installatie</a>.</li>
        <li>Composer in <code>PATH</code> van de IIS-user — anders faalt de auto-update-stap (WARN, geen breek).</li>
    </ol>

    <h2>Troubleshooting</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:300px">Symptoom</th><th>Oorzaak / Fix</th></tr></thead>
        <tbody>
            <tr><td>Webhook delivery faalt met 401</td><td><code>DEPLOY_SECRET</code> op de server stemt niet overeen met GitHub. Roteer beide tegelijk.</td></tr>
            <tr><td>Webhook accepteert (202) maar niks gebeurt</td><td>Pipeline-fout. Kijk in <code>logs/deploy.log</code> onder de <code>RUN:</code> / <code>EXIT: 1</code> regels.</td></tr>
            <tr><td><code>vdev-main</code> in profielmenu i.p.v. versienummer</td><td><code>vendor/</code> niet ververst. Fix: <code>composer update stenversonline/platform</code>; v1.13.0+ webhook doet dit automatisch.</td></tr>
            <tr><td>Class "App\Library\Email" not found</td><td>Zelfde oorzaak — stale vendor. v1.12.1+ bootstrap heeft class_exists-guard zodat CMA niet crash't.</td></tr>
            <tr><td><code>/cma/dashboard</code> geeft 404, <code>/cma/dashboard.php</code> wel 200</td><td>Parent web.config vangt <code>/cma/*</code> op vóór het kind <code>cma/web.config</code>. Voeg "Skip /cma to child config" regel toe — zie <a href="documentation.php?topic=iis_config">IIS-configuratie</a>.</td></tr>
        </tbody>
    </table>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=installation">Installatie</a>, <a href="documentation.php?topic=environment">Omgeving &amp; .env</a>, <a href="documentation.php?topic=iis_config">IIS-configuratie</a>.
    </div>
    <?php
}

function render_doc_backups(): void
{
    ?>
    <h1>Backups &amp; herstel</h1>
    <p class="docs-meta">Wat het backup-systeem doet, waar bestanden landen, en hoe je SQLite-corruptie herstelt.</p>

    <h2>Backup-systeem in een notendop</h2>
    <p><a href="tools/tools_backup.php" target="_top">Tools → Database backup</a> draait op <code>Cma\Services\BackupService</code>. Per geconfigureerde database (uit <code>cma/config/databases.json</code>) maakt het een backup op de juiste manier:</p>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:160px">DB-type</th><th>Backup-strategie</th><th>Bestand</th></tr></thead>
        <tbody>
            <tr><td>SQLite</td><td>File copy (atomic via temp + rename)</td><td><code>backup/&lt;yyyy-mm-dd-hh-mm&gt;_&lt;name&gt;_backup.sqlite</code></td></tr>
            <tr><td>MS Access (.mdb)</td><td>File copy</td><td><code>backup/&lt;…&gt;_backup.mdb</code></td></tr>
            <tr><td>MySQL / MariaDB</td><td><code>mysqldump</code> stream</td><td><code>backup/&lt;…&gt;_backup.sql</code></td></tr>
            <tr><td>PostgreSQL</td><td><code>pg_dump</code> stream</td><td><code>backup/&lt;…&gt;_backup.sql</code></td></tr>
            <tr><td>SQL Server</td><td>SQL Server bulk-export</td><td><code>backup/&lt;…&gt;_backup.sql</code></td></tr>
        </tbody>
    </table>
    <p>Beschrijvingen per backup leven in <code>backup/backups.json</code>; <code>BackupService::syncIndex()</code> reconciliert dat bestand met wat er op schijf staat.</p>

    <h2>Pre-migration backups</h2>
    <p>Voordat <code>MigrationService</code> een database-wijzigende migration uitvoert, roept hij <code>BackupService::createMigrationBackup()</code> aan met de migration-versie als label. Die backups krijgen een vaste prefix zodat je in <a href="tools/tools_backup.php?tab=manage" target="_top">Backups beheren</a> snel de pre-migration snapshots terugvindt.</p>
    <p>Auto-backup is toggle-able op de Migraties-pagina; uit-zetten voor migration-runs die geen schema-impact hebben (b.v. data-only export-runs) bespaart schijfruimte op grote DB's.</p>

    <h2>Restore</h2>
    <p>Selecteer een backup in de "Backups beheren" tab; de tool valideert dat de file leesbaar is en biedt een restore-knop. SQLite/Access restores zijn file-replacements (DB moet niet in gebruik zijn — IIS app-pool recyclen ervoor). MySQL/PG/MSSQL restores draaien het SQL-bestand via de DB-client.</p>
    <div class="docs-callout docs-callout--warn">
        Restore overschrijft de bestaande database zonder rollback-pad. Maak eerst een fresh backup van de huidige staat als die nog niet recent is.
    </div>

    <h2>SQLite emergency-recovery</h2>
    <p>Bij SQLite kan een crash mid-write een corrupte database achterlaten met dangling <code>-wal</code> / <code>-shm</code> sidecars. Het platform heeft een emergency-recovery flag:</p>
    <ul>
        <li>Plaats een leeg bestand <code>db/sqlite_emergency_recovery.flag</code>.</li>
        <li>Bij de volgende request voert <code>Bootstrap::sqliteEmergencyRecovery()</code> uit: verwijder <code>cmausers.sqlite-wal</code> en <code>-shm</code>, log naar <code>db/sqlite_recovery.log</code>, en verwijder de flag.</li>
        <li>Zet <code>$GLOBALS['_sqlite_recovery_performed'] = true</code> zodat downstream code weet dat er net iets gebeurd is.</li>
    </ul>
    <p>Voor diepere corruptie: <code>tools/tools_sqlite_repair.php</code> draait <code>sqlite3 .dump</code> + her-import. Use sparingly — eerst backup, dan repair.</p>

    <h2>MS Access compaction</h2>
    <p>Access (<code>.mdb</code>) DB's groeien onbegrensd na veel writes. <a href="tools/tools_dbcompact.php" target="_top">Tools → DB-compact</a> draait een compact + repair (gelijkwaardig aan Access's "Compact &amp; Repair Database"). Doe dit periodiek — eens per maand bij actieve sites.</p>

    <h2>Backup-retentie</h2>
    <p>Het platform verwijdert niks automatisch. Stel een Windows Task Scheduler taak in voor <code>logs/</code>-style cleanup (b.v. delete files older than 60 days in <code>backup/</code>) als je schijfruimte een bottleneck is.</p>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=installation">Installatie</a> (file-rechten op <code>backup/</code>), <a href="documentation.php?topic=logs">Logs &amp; monitoring</a> (waar je backup-fouten terugziet).
    </div>
    <?php
}

function render_doc_logs(): void
{
    ?>
    <h1>Logs &amp; monitoring</h1>
    <p class="docs-meta">Wat elke log-bron bevat, waar hij ligt, en hoe je hem leest.</p>

    <h2>Welke log waar?</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:170px">Naam</th><th style="width:280px">Bestand</th><th>Inhoud</th></tr></thead>
        <tbody>
            <tr><td>PHP error log</td><td><code>logs/php_errors.log</code></td><td>Alle uncaught exceptions, fatal errors, warnings (in dev), en <code>error_log()</code> output.</td></tr>
            <tr><td>Deploy log</td><td><code>logs/deploy.log</code></td><td>Output van elke deploy-pipeline; banner per run.</td></tr>
            <tr><td>Application log</td><td><code>cache/logs/app_YYYY-MM-DD.log</code></td><td>Structured logs van <code>Cma\Services\Logger</code>. JSON per regel. Productie: WARNING+; dev/test: DEBUG+.</td></tr>
            <tr><td>Performance log</td><td><code>cache/perf_logs/perf_YYYY-MM-DD.log</code></td><td>Timing metrics van <code>PerformanceLogger</code> (queries, API-calls, memory). Aan via <code>PERF_LOG_ENABLED=true</code>.</td></tr>
            <tr><td>Debug log</td><td><code>cma/logs/debug_YYYY-MM-DD.log</code></td><td>Verbose debug van <code>api/log.php</code> (JS-side <code>LibLog</code>) wanneer debug-mode aan.</td></tr>
            <tr><td>404 log</td><td><code>cma/logs/404_YYYY-MM-DD.log</code></td><td>Niet-gevonden URLs gevangen door <code>cma/404.php</code>.</td></tr>
            <tr><td>Cache log</td><td><code>cache/cache.log</code></td><td>Cache-hit/-miss events. Aan via <code>CACHE_LOG_ENABLED=true</code>.</td></tr>
            <tr><td>JS errors (DB)</td><td>Tabel <code>tblCMAJavascriptErrors</code></td><td>Client-side errors gevangen door <code>CmaErrorHandler</code>. Rate-limited tot 100 per IP per uur.</td></tr>
        </tbody>
    </table>

    <h2>Logreader</h2>
    <p>Alle file-based logs hebben een UI: <a href="logreader.php" target="_top">Tools → Logbestanden lezen</a>. Per log-type:</p>
    <ul>
        <li><span class="cma-tool__strong">PHP errors / Deploy / Cache</span> — plain text view, leegmaken via "Log leegmaken" toolbar-knop (truncate, niet delete, zodat de volgende write kan appenden).</li>
        <li><span class="cma-tool__strong">Performance</span> — JSON-rendered table, klikbare detail-rows, SQL-threshold filter op de toolbar.</li>
        <li><span class="cma-tool__strong">404</span> — JSON-rendered table met URL + referrer + datum.</li>
        <li><span class="cma-tool__strong">Debug</span> — date-picker bovenaan voor historische dagen.</li>
        <li><span class="cma-tool__strong">JS errors</span> — leest van de database; "leegmaken" verwijdert ALLE entries.</li>
    </ul>

    <h2>Logger API (PHP)</h2>
    <p><code>Cma\Services\Logger</code> is PSR-3 compatible:</p>
    <pre><code>use Cma\Services\Logger;

Logger::info('User logged in', ['userId' =&gt; 123]);
Logger::warning('Slow query', ['ms' =&gt; 500, 'sql' =&gt; $sql]);
Logger::error('Save failed', ['formId' =&gt; 45, 'error' =&gt; $e-&gt;getMessage()]);
Logger::exception($e, 'Database error', ['query' =&gt; $sql]);</code></pre>
    <p>Levels: EMERGENCY, ALERT, CRITICAL, ERROR, WARNING, NOTICE, INFO, DEBUG. ERROR+ gaat ALTIJD ook naar PHP's <code>error_log()</code> zodat productie-errors nooit verloren gaan, ongeacht of de Logger-config draait.</p>

    <h2>Sensitive-data scrubbing</h2>
    <p>De Logger verwijdert automatisch keys die matchen op <code>password</code>, <code>token</code>, <code>secret</code>, <code>api_key</code>, <code>credentials</code> uit context-arrays voordat het bestand wordt geschreven.</p>

    <h2>PerformanceLogger</h2>
    <pre><code>use Cma\Services\PerformanceLogger;

PerformanceLogger::startTimer('complex_query');
$result = $db-&gt;query($sql);
$ms = PerformanceLogger::endTimer('complex_query', ['rows' =&gt; $result-&gt;rowCount()]);

PerformanceLogger::logQuery($sql, $durationMs, ['table' =&gt; 'users']);
PerformanceLogger::logApi('form_list', $durationMs, ['formName' =&gt; 'users']);
PerformanceLogger::logMemory('after_query');</code></pre>

    <h2>Migratie-controle banner</h2>
    <p><code>cma/bootstrap.inc</code> doet bij elke admin-request een check op pending migrations. Resultaat surfacet boven de toolbar als rode banner als de check fataalde (sinds v1.10.4 — voor die versie werd de exceptie stilzwijgend ingeslikt). PHP-error-log levert dan de details onder <code>[MigrationService]</code>.</p>

    <h2>Retentie</h2>
    <ul>
        <li>Application log: 30 dagen (<code>Logger::cleanup(30)</code>).</li>
        <li>Performance log: 7 dagen (<code>PerformanceLogger::cleanup(7)</code>).</li>
        <li>Andere logs (php_errors, deploy, debug, 404): platform doet niks automatisch. Stel een cleanup-job in als de schijf vol loopt.</li>
    </ul>

    <h2>Debug-mode aan/uit</h2>
    <p>Per-user via <a href="preferences.php" target="_top">Voorkeuren</a> → Console logging. Schrijft cookie <code>cma_debug_mode</code> (<code>J</code>/<code>N</code>). Beïnvloedt LibLog's console-output en server-logging-niveau.</p>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=deployment">Deployment</a> (deploy.log specifiek), <a href="documentation.php?topic=backups">Backups</a> (backup-failures landen in php_errors.log).
    </div>
    <?php
}

function render_doc_security(): void
{
    ?>
    <h1>Beveiliging</h1>
    <p class="docs-meta">Wat het platform afdwingt en waar jij als beheerder op moet letten.</p>

    <h2>Secrets management</h2>
    <ul>
        <li><span class="cma-tool__strong">DEPLOY_SECRET</span> — leeft alleen in <code>.env</code>. Webhook-validatie via HMAC-SHA256; bytestream-vergelijking via <code>hash_equals()</code> (timing-safe). Roteer eens per kwartaal of bij staff-wissel.</li>
        <li><span class="cma-tool__strong">LLM_KEY / OCR_VISION_KEY</span> — Anthropic-API keys. <code>App\Library\Llm::anthropicFallbackKey()</code> leest in volgorde: <code>LLM_KEY</code> → <code>OCR_VISION_KEY</code> (als <code>OCR_VISION_PROVIDER=anthropic</code> of unset). Op de LLM-status pagina worden ze gemaskeerd weergegeven (<code>sk-ant-…XyZ</code>).</li>
        <li><span class="cma-tool__strong">Mail credentials</span> — <code>mail_password</code> in <code>app.php</code> (NIET in git: <code>app.php</code> is een template-bestand dat per-site bestaat).</li>
        <li><span class="cma-tool__strong">global.asa.php</span> — legacy locatie voor secrets; ook template-bestand, ook NIET in git.</li>
    </ul>
    <p>Het platform's repo zelf bevat geen secrets — alleen <code>.env.example</code> en <code>app.php.template</code> als skeleton.</p>

    <h2>web.config: hidden segments</h2>
    <p>De site-root <code>web.config</code> blokkeert direct-access op gevoelige bestanden via <code>&lt;hiddenSegments&gt;</code>:</p>
    <ul>
        <li><code>.env</code> — alle environment files.</li>
        <li><code>.app_started</code> — application state file.</li>
        <li><code>composer.json</code> — voorkomt lekken van dependency-config.</li>
        <li><code>composer.lock</code> — zelfde reden.</li>
    </ul>
    <p>Voeg toe als je andere paden ook wilt afschermen (b.v. <code>.git/</code> als die per ongeluk in webroot landde).</p>

    <h2>Sessie-cookies</h2>
    <p>Productie hoort <code>session.cookie_secure = 1</code> te hebben in <code>php.ini</code> (cookies alleen over HTTPS verzonden). De <a href="tools_serverinfo.php" target="_top">Omgeving-tab</a> toont de huidige waarde — als die op productie <code>Uit</code> staat, fix dat eerst.</p>
    <p>Andere session-hardening:</p>
    <ul>
        <li><code>session.cookie_httponly = 1</code> — JS kan de cookie niet lezen.</li>
        <li><code>session.cookie_samesite = Lax</code> — CSRF mitigatie zonder same-origin requests te breken.</li>
        <li><code>session.use_strict_mode = 1</code> — voorkomt session-fixation.</li>
    </ul>

    <h2>File-upload restricties</h2>
    <p>De file-browser wizard (<code>cma/wizards/file-browser.php</code>) blokkeert uploads met deze extensies:</p>
    <p><code>php, phtml, phar, php3, php4, php5, php7, asp, aspx, jsp, sh, cgi, pl, exe, bat, cmd, com, htaccess, htpasswd</code></p>
    <p>De check draait op de FINAL filename (na sanitization + na een eventuele <code>targetName</code>-redirect), zodat een client-side "overschrijf het geselecteerde bestand met deze upload" actie niet om de check heen kan.</p>

    <h2>Gebruikersniveau's</h2>
    <p><code>Cma\SecurityHelper</code> kent drie levels:</p>
    <table class="listtable">
        <thead><tr class="listheader"><th>Level</th><th>Constant</th><th>Toegang</th></tr></thead>
        <tbody>
            <tr><td>Gebruiker</td><td><code>LEVEL_USER</code> (0)</td><td>Reguliere CMA-functionaliteit conform menu-toegang.</td></tr>
            <tr><td>Administrator</td><td><code>LEVEL_ADMIN</code></td><td>Beheerstools, migrations, backup, alle tools die data raken.</td></tr>
            <tr><td>Developer</td><td><code>LEVEL_DEVELOPER</code></td><td>Alles + storybook, formulier-editor, dev-tools, env-switch, test-email.</td></tr>
        </tbody>
    </table>
    <p>Gates: <code>SecurityHelper::isAdmin()</code> en <code>SecurityHelper::isDeveloper()</code>. Tools-pagina's beginnen typisch met:</p>
    <pre><code>if (!SecurityHelper::isAdmin()) {
    echo '&lt;lib-message type="error"&gt;Geen toegang&lt;/lib-message&gt;';
    exit;
}</code></pre>

    <h2>CSRF</h2>
    <p>POST-endpoints in <code>cma/api/*</code> en <code>cma/form_api.php</code> krijgen automatisch een CSRF-token via de form-controller (verzonden in een hidden field). Custom POST-handlers moeten het token valideren met de form-helper voordat ze een mutation uitvoeren.</p>

    <h2>SQL-injection</h2>
    <p>Twee patronen:</p>
    <ul>
        <li><span class="cma-tool__strong">Prepared statements</span> — <code>Database::executeQuery($sql, ['param' =&gt; $value])</code>. PDO bindt de waardes. Voorkeur voor alles wat user-input bevat.</li>
        <li><span class="cma-tool__strong">SQL-helpers</span> — <code>SQL::postString($v)</code>, <code>SQL::postNumber($v)</code> escapen oude ASP-stijl. Acceptabel in legacy code; nieuw werk gebruikt prepared statements.</li>
    </ul>

    <h2>Audit-trail</h2>
    <p>Mutations door beheerders (formulier-saves, deletes) loggen naar <code>tblCMAMonitoring</code> via <code>CmaMonitoringLogger</code>. Zichtbaar in <a href="form.php?form=cmamonitoring" target="_top">CMA Monitoring</a> rapport — wie deed wat wanneer.</p>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=installation">Installatie</a> (file-rechten), <a href="documentation.php?topic=environment">Omgeving &amp; .env</a> (waar secrets leven), <a href="documentation.php?topic=iis_config">IIS-configuratie</a> (hiddenSegments + securityheaders).
    </div>
    <?php
}

function render_doc_iis_config(): void
{
    ?>
    <h1>IIS-configuratie</h1>
    <p class="docs-meta">Hoe het platform IIS gebruikt — URL Rewrite, web.config layering, app-pool recycle.</p>

    <h2>Vereisten</h2>
    <ul>
        <li><span class="cma-tool__strong">IIS URL Rewrite Module</span> — installer downloadbaar van <a href="https://www.iis.net/downloads/microsoft/url-rewrite" target="_blank" rel="noopener">iis.net/downloads/microsoft/url-rewrite</a>. Zonder deze module worden ALLE <code>&lt;rewrite&gt;</code> regels in <code>web.config</code> stilzwijgend genegeerd.</li>
        <li><span class="cma-tool__strong">FastCGI handler</span> voor PHP — met <code>fastcgi_finish_request</code> support zodat de deploy-webhook async kan flushen.</li>
        <li>Eenmalig server-level: allowed server variables ontgrendelen (zie <a href="documentation.php?topic=installation">Installatie</a>).</li>
    </ul>

    <h2>web.config layering</h2>
    <p>Een typische consumer-site heeft TWEE web.configs:</p>
    <ul>
        <li><code>&lt;site-root&gt;/web.config</code> — de eigen routing van de consumer (friendly URLs, HTTPS-redirect, www-redirect). PROJECT-protected.</li>
        <li><code>&lt;site-root&gt;/cma/web.config</code> — PLATFORM-owned, gesynct door de Installer. Bevat de rewrite-rules voor extensionless URLs <code>/cma/dashboard</code>, <code>/cma/preferences</code>, <code>/cma/form/&lt;…&gt;</code>, etc.</li>
    </ul>
    <p>IIS evalueert parent-rules eerst. Als een parent-rule met <code>stopProcessing="true"</code> matcht op een URL, krijgt de child <code>web.config</code> niks meer te doen. Dat is precies waar je tegenaan loopt bij sites met catch-all "alle .php door <code>_bootstrap_wrapper.php</code>" rules.</p>

    <div class="docs-callout docs-callout--danger">
        <span class="cma-tool__strong">Skip /cma to child config</span> — sinds v1.14.2 staat deze regel standaard in de <code>templates/web.config.template</code> die nieuwe installs krijgen:
        <pre><code>&lt;rule name="Skip /cma to child config" stopProcessing="true"&gt;
    &lt;match url="^cma($|/)" /&gt;
    &lt;action type="None" /&gt;
&lt;/rule&gt;</code></pre>
        Voor BESTAANDE installs moet je 'm handmatig toevoegen bovenaan <code>&lt;rules&gt;</code> in de site-root web.config (of NA HTTPS/www redirects, zodat <code>/cma/*</code> nog steeds geforceerd naar HTTPS gaat). Symptoom als de regel ontbreekt: <code>/cma/dashboard</code> geeft 404, maar <code>/cma/dashboard.php</code> wel 200.
    </div>

    <h2>cma/web.config in detail</h2>
    <p>De platform-side web.config doet drie dingen:</p>
    <ol>
        <li><span class="cma-tool__strong">Friendly-URL rewrites</span>: <code>^dashboard/?$</code>, <code>^preferences/?$</code>, <code>^tools/?$</code>, <code>^form/&lt;...&gt;</code> → <code>_bootstrap_wrapper.php?page=&lt;target&gt;.php</code>. Bewaart <code>HTTP_X_ORIGINAL_FILE</code> als server-variable zodat <code>main.php</code> weet welke pagina geladen werd.</li>
        <li><span class="cma-tool__strong">Default document</span>: <code>default.php</code> en <code>index.php</code>.</li>
        <li><span class="cma-tool__strong">404 handler</span>: <code>/cma/404.php</code> via <code>responseMode="ExecuteURL"</code>. <code>existingResponse="Auto"</code> — alleen het custom 404-pagina laden als de app niks gerendered heeft. NOOIT <code>existingResponse="Replace"</code> gebruiken — dat triggert een infinite-loop wanneer 404.php zelf een 404-status terugstuurt.</li>
    </ol>

    <h2>Friendly URLs voor CMA-tools</h2>
    <p>De rewrite-regel <code>CMA Tools Friendly URL</code> (in de site-root <code>web.config</code>, niet in <code>cma/web.config</code>) maakt korte URLs voor veelgebruikte tools:</p>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:230px">Friendly URL</th><th>Maps naar</th></tr></thead>
        <tbody>
            <tr><td><code>/cma/tools/clearcache</code></td><td><code>tools/tools_clearcache.php</code></td></tr>
            <tr><td><code>/cma/tools/migrations</code></td><td><code>tools/tools_migrations.php</code></td></tr>
            <tr><td><code>/cma/tools/query</code></td><td><code>tools/tools_query.php</code></td></tr>
            <tr><td><code>/cma/tools/dbsummary</code></td><td><code>tools/tools_dbsummary.php</code></td></tr>
            <tr><td><code>/cma/tools/logs</code></td><td><code>tools/logreader.php</code></td></tr>
            <tr><td><code>/cma/tools/serverinfo</code></td><td><code>tools/tools_serverinfo.php</code></td></tr>
            <tr><td><code>/cma/tools/llm</code></td><td><code>tools/tools_llm.php</code></td></tr>
            <tr><td><code>/cma/tools/docs</code></td><td><code>tools/documentation.php</code></td></tr>
        </tbody>
    </table>
    <p>De full lijst staat in <code>$toolNameMap</code> in <code>cma/tools.php</code>. Voeg een entry toe om een eigen tool een korte URL te geven.</p>

    <h2>Security-headers</h2>
    <p>De standaard <code>web.config.template</code> stuurt:</p>
    <ul>
        <li><code>X-Content-Type-Options: nosniff</code> — voorkomt MIME-type-sniffing aanvallen.</li>
        <li><code>X-Frame-Options: SAMEORIGIN</code> — voorkomt clickjacking.</li>
        <li><code>removeServerHeader="true"</code> en outbound-rewrite van het Server-header — beperkt fingerprinting.</li>
    </ul>

    <h2>App-pool recycle</h2>
    <p>Touch op <code>web.config</code> triggert een app-pool recycle in IIS — alle PHP-OpCache state, in-memory session-handlers en lopende processes worden geflushed. Het deploy-webhook script doet dit standaard na een succesvolle pipeline; je kan het handmatig forceren met:</p>
    <pre><code>copy /b web.config +,, </code></pre>
    <p>Of via de tool <a href="tools/opcache_reset.php" target="_top">Tools → OPcache reset</a>.</p>

    <h2>Troubleshooting</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak / Fix</th></tr></thead>
        <tbody>
            <tr><td><code>/cma/dashboard</code> → 404</td><td>"Skip /cma" rule ontbreekt in parent web.config. Zie callout hierboven.</td></tr>
            <tr><td><code>/cma/dashboard.php</code> → 500 Server Error</td><td>Allowed server variables niet ontgrendeld. Zie <a href="documentation.php?topic=installation">Installatie</a>.</td></tr>
            <tr><td><code>/cma/tools/&lt;naam&gt;</code> → 404 maar <code>?tool=&lt;naam&gt;</code> werkt wel</td><td>URL Rewrite Module ontbreekt of de "CMA Tools Friendly URL" regel staat niet in de site-root web.config.</td></tr>
            <tr><td>Site geeft IIS default 404, niet cma/404.php</td><td><code>cma/404.php</code> bestaat niet op disk (Installer-sync incompleet). Run <code>composer update stenversonline/platform</code>.</td></tr>
            <tr><td>Mobile Safari prompts "Download logreader.php?"</td><td>Sinds v1.10.1 gefixed (@-suppress op file_put_contents in delete-handler zodat warnings niet de Location-redirect breken).</td></tr>
        </tbody>
    </table>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=installation">Installatie</a>, <a href="documentation.php?topic=deployment">Deployment</a> (web.config touch voor recycle), <a href="documentation.php?topic=security">Beveiliging</a> (security-headers).
    </div>
    <?php
}
