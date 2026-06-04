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
    'dev' => [
        'label' => 'Voor ontwikkelaars',
        'icon'  => 'lnr-code',
        'children' => [
            'architecture'  => ['label' => 'Architectuur',                'icon' => 'lnr-layers',     'render' => 'render_doc_architecture'],
            'new_tool'      => ['label' => 'Een CMA-tool toevoegen',      'icon' => 'lnr-construction','render' => 'render_doc_new_tool'],
            'database'      => ['label' => 'Database & RecordSet',        'icon' => 'lnr-database',   'render' => 'render_doc_database'],
            'migrations'    => ['label' => 'Migraties schrijven',         'icon' => 'lnr-arrow-right','render' => 'render_doc_migrations'],
            'json_forms'    => ['label' => 'JSON-gedreven formulieren',   'icon' => 'lnr-text-format','render' => 'render_doc_json_forms'],
            'web_components'=> ['label' => 'Web components ontwikkelen',  'icon' => 'lnr-bubble',     'render' => 'render_doc_web_components'],
            'errors'        => ['label' => 'Logging & errors (dev)',      'icon' => 'lnr-bug',        'render' => 'render_doc_errors'],
            'testing'       => ['label' => 'Tests & coverage strategie',  'icon' => 'lnr-shield-check','render' => 'render_doc_testing'],
            'releasing'     => ['label' => 'Releasen & versies',          'icon' => 'lnr-tag',        'render' => 'render_doc_releasing'],
        ],
    ],
    'reference' => [
        'label' => 'Troubleshooting & referentie',
        'icon'  => 'lnr-magnifier',
        'children' => [
            'troubleshooting' => ['label' => 'Troubleshooting',     'icon' => 'lnr-warning',  'render' => 'render_doc_troubleshooting'],
            'mail'            => ['label' => 'Mail-configuratie',   'icon' => 'lnr-envelope', 'render' => 'render_doc_mail'],
            'llm'             => ['label' => 'LLM-configuratie',    'icon' => 'lnr-brain',    'render' => 'render_doc_llm'],
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
// cma-tree is not in the auto-load list (cma-fold is); load it explicitly
// for the sidebar. cma-fold is autoloaded by bootstrap but we re-state
// it here to make the dependency obvious to a future maintainer.
cma_script('webcomponents/cma-tree.js');
ToolbarHelper::start(true);
ToolbarHelper::title('Documentatie');
ToolbarHelper::separator();
ToolbarHelper::status($flat[$selected]['label']);
ToolbarHelper::end();
echo '<div id="c" class="tools">';
?>

<style>
/* Remove the default #c.tools 20px padding for this topic-router page —
   the sidebar + content layout below has its own internal spacing and
   the outer padding pushed everything off-center and added a visible
   double border. Matches the pattern used by body.tool-serverinfo. */
body.tool-docs #c.tools { padding: 0; }

.tool-docs .docs-layout { display: flex; gap: 0; align-items: stretch; min-height: calc(100vh - 80px); }
.tool-docs .docs-sidebar { flex: 0 0 260px; padding: 14px 8px 14px 14px; overflow: auto; background: var(--bg-surface-alt, #f6f8fa); border-right: 1px solid var(--border-color, #e0e0e0); }
.tool-docs .docs-content { flex: 1; min-width: 0; max-width: 900px; padding: 16px 22px; overflow: auto; }
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
    <nav class="docs-sidebar" id="docsSidebar">
        <cma-tree id="docsNav" storage-key="docs_nav"></cma-tree>
    </nav>
    <cma-fold orientation="vertical" target="#docsSidebar" min-size="180" max-size="500" storage-key="docs_fold"></cma-fold>
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

    // cma-tree calls e.preventDefault() on item clicks and only dispatches
    // an item-click event — the default anchor navigation never runs. Wire
    // the navigation explicitly. Same-page reload keeps the docs sidebar
    // state via the cma-tree storage-key cookie.
    nav.addEventListener('item-click', function (e) {
        var href = e.detail && e.detail.href;
        if (!href || href === '#') return;
        window.location.href = href;
    });
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
// === SELF-CHECK HELPERS =================================================
// Each cma_doc_check_<name>() function returns ['label','status','detail','fix']
// where status is one of 'pass'|'fail'|'warn'|'info'. Topics inline the
// results via cma_doc_render_check_table() so operators see at a glance
// whether the rule/file/setting being documented actually exists on this
// site. The checks are cheap (file-stat + simplexml parse) so they run on
// every doc page-load — no caching layer, no staleness risk.
//
// CMA layout invariant: cma/ is at the site root, so from cma/tools/
// documentation.php the site root is dirname(__DIR__, 2). Same anchor
// as deploy_status.php uses.
// =========================================================================

function cma_doc_site_root(): string {
    return dirname(__DIR__, 2);
}

function cma_doc_parent_webconfig(): ?\SimpleXMLElement {
    static $xml = null;
    static $loaded = false;
    if (!$loaded) {
        $loaded = true;
        $path = cma_doc_site_root() . '/web.config';
        if (is_file($path)) {
            $xml = @simplexml_load_file($path);
            if ($xml === false) { $xml = null; }
        }
    }
    return $xml;
}

function cma_doc_child_webconfig(): ?\SimpleXMLElement {
    static $xml = null;
    static $loaded = false;
    if (!$loaded) {
        $loaded = true;
        $path = cma_doc_site_root() . '/cma/web.config';
        if (is_file($path)) {
            $xml = @simplexml_load_file($path);
            if ($xml === false) { $xml = null; }
        }
    }
    return $xml;
}

function cma_doc_check_parent_skip_cma(): array {
    $label = 'Parent web.config: "Skip /cma to child config" rule';
    $xml = cma_doc_parent_webconfig();
    if ($xml === null) {
        return ['label' => $label, 'status' => 'info', 'detail' => 'Parent <code>web.config</code> niet gevonden in site-root — site draait niet onder IIS of staat ergens anders.', 'fix' => ''];
    }
    $hit = $xml->xpath("//rewrite/rules/rule[@name='Skip /cma to child config']");
    if (!empty($hit)) {
        return ['label' => $label, 'status' => 'pass', 'detail' => 'Aanwezig.', 'fix' => ''];
    }
    return ['label' => $label, 'status' => 'fail', 'detail' => 'Regel ontbreekt — <code>/cma/dashboard</code> en andere extension-less URLs eindigen in 404.', 'fix' => 'Kopieer uit <code>templates/web.config.template</code>, plaats bovenaan parent <code>&lt;rules&gt;</code>.'];
}

function cma_doc_check_parent_default_content_type(): array {
    $label = 'Parent web.config: outbound "Default Content-Type" rule';
    $xml = cma_doc_parent_webconfig();
    if ($xml === null) {
        return ['label' => $label, 'status' => 'info', 'detail' => 'Parent <code>web.config</code> niet gevonden.', 'fix' => ''];
    }
    $hit = $xml->xpath("//rewrite/outboundRules/rule[@name='Default Content-Type to text/html']");
    if (!empty($hit)) {
        return ['label' => $label, 'status' => 'pass', 'detail' => 'Aanwezig — mobile Safari download-prompt bij Content-Type-loze responses is afgevangen.', 'fix' => ''];
    }
    return ['label' => $label, 'status' => 'fail', 'detail' => 'Regel ontbreekt — een PHP-response zonder Content-Type wordt door iOS Safari als download aangeboden.', 'fix' => 'Sinds v1.19.9 standaard in <code>templates/web.config.template</code>. Doe <code>composer update stenversonline/platform</code> en kopieer de outbound rule.'];
}

function cma_doc_check_parent_nosniff(): array {
    $label = 'Parent web.config: X-Content-Type-Options: nosniff';
    $xml = cma_doc_parent_webconfig();
    if ($xml === null) {
        return ['label' => $label, 'status' => 'info', 'detail' => 'Parent <code>web.config</code> niet gevonden.', 'fix' => ''];
    }
    $hit = $xml->xpath("//httpProtocol/customHeaders/add[@name='X-Content-Type-Options']");
    if (!empty($hit)) {
        return ['label' => $label, 'status' => 'pass', 'detail' => 'Aanwezig.', 'fix' => ''];
    }
    return ['label' => $label, 'status' => 'warn', 'detail' => 'Header niet gezet — MIME-sniffing aanvallen mogelijk.', 'fix' => 'Voeg toe in <code>&lt;httpProtocol&gt;&lt;customHeaders&gt;</code>.'];
}

function cma_doc_check_parent_frame_options(): array {
    $label = 'Parent web.config: X-Frame-Options: SAMEORIGIN';
    $xml = cma_doc_parent_webconfig();
    if ($xml === null) {
        return ['label' => $label, 'status' => 'info', 'detail' => 'Parent <code>web.config</code> niet gevonden.', 'fix' => ''];
    }
    $hit = $xml->xpath("//httpProtocol/customHeaders/add[@name='X-Frame-Options']");
    if (!empty($hit)) {
        return ['label' => $label, 'status' => 'pass', 'detail' => 'Aanwezig.', 'fix' => ''];
    }
    return ['label' => $label, 'status' => 'warn', 'detail' => 'Header niet gezet — clickjacking mogelijk.', 'fix' => 'Voeg toe in <code>&lt;httpProtocol&gt;&lt;customHeaders&gt;</code>.'];
}

function cma_doc_check_parent_hidden_segments(): array {
    $label = 'Parent web.config: hiddenSegments (.env / composer.json / composer.lock)';
    $xml = cma_doc_parent_webconfig();
    if ($xml === null) {
        return ['label' => $label, 'status' => 'info', 'detail' => 'Parent <code>web.config</code> niet gevonden.', 'fix' => ''];
    }
    $required = ['.env', 'composer.json', 'composer.lock'];
    $present  = [];
    foreach ($xml->xpath("//security/requestFiltering/hiddenSegments/add") as $node) {
        $present[] = (string)$node['segment'];
    }
    $missing = array_diff($required, $present);
    if (empty($missing)) {
        return ['label' => $label, 'status' => 'pass', 'detail' => 'Alle drie gehide.', 'fix' => ''];
    }
    return ['label' => $label, 'status' => 'fail', 'detail' => 'Ontbreekt: <code>' . htmlspecialchars(implode('</code>, <code>', $missing)) . '</code> — publiek bereikbaar.', 'fix' => 'Voeg <code>&lt;add segment="…"/&gt;</code> toe in <code>&lt;hiddenSegments&gt;</code>.'];
}

function cma_doc_check_cma_is_iis_application(): array {
    // Detect of cma/ als IIS Application is geconfigureerd, niet als gewone
    // Virtual Directory. De child cma/web.config rewrite-rules gebruiken
    // patronen als `^dashboard/?$` (zonder cma/-prefix) — die matchen
    // alleen als IIS de URL aan child-rules aanbiedt RELATIEF aan de
    // cma-locatie, en dat doet IIS uitsluitend wanneer cma als
    // Application is ingericht. Op een gewone Virtual Directory ziet de
    // child config de FULL URL (`cma/dashboard`) en matcht niets.
    //
    // IIS zet $_SERVER['APPL_MD_PATH'] op het virtuele applicatie-pad.
    // Voor een Application op /cma is dat /LM/W3SVC/<id>/ROOT/cma. Voor
    // een Virtual Directory is het /LM/W3SVC/<id>/ROOT (de site-root).
    // We checken of het APPL_MD_PATH eindigt op '/cma' (case-insensitive).
    $label = 'cma/ is als IIS Application ingericht (niet als Virtual Directory)';

    if (PHP_SAPI === 'cli') {
        return ['label' => $label, 'status' => 'info', 'detail' => 'CLI-context: niet te testen.', 'fix' => ''];
    }
    $applMd = (string)($_SERVER['APPL_MD_PATH'] ?? '');
    if ($applMd === '') {
        return ['label' => $label, 'status' => 'info', 'detail' => 'Niet IIS, of <code>APPL_MD_PATH</code> niet beschikbaar — check niet uitvoerbaar.', 'fix' => ''];
    }
    if (strcasecmp(substr($applMd, -4), '/cma') === 0) {
        return ['label' => $label, 'status' => 'pass', 'detail' => 'Ja — <code>APPL_MD_PATH</code> eindigt op <code>/cma</code>. Child-config rewrite-rules zien de URL relatief en matchen correct.', 'fix' => ''];
    }
    return [
        'label'  => $label,
        'status' => 'fail',
        'detail' => 'Nee — <code>APPL_MD_PATH</code> = <code>' . htmlspecialchars($applMd) . '</code> (eindigt niet op <code>/cma</code>). cma/ is een Virtual Directory binnen de parent-Application. Gevolg: de child cma/web.config rewrite-rules zien de full URL (<code>cma/dashboard</code>) terwijl de patterns op de cma-relatieve URL (<code>dashboard</code>) zijn geschreven — niets matcht, IIS valt terug op static-file lookup, en extensionless URLs als <code>/cma/dashboard</code> krijgen 404.',
        'fix'    => 'Open IIS Manager → Sites → deze site → rechtermuis op <code>cma</code> (gele map-icoon) → <em>Convert to Application</em>. Gebruik dezelfde Application Pool als de parent. Het icoon wordt blauw. Test direct: <code>/cma/dashboard</code> werkt nu.',
    ];
}

function cma_doc_check_child_dashboard_rule(): array {
    $label = 'Child cma/web.config: Dashboard rewrite rule';
    $xml = cma_doc_child_webconfig();
    if ($xml === null) {
        return ['label' => $label, 'status' => 'fail', 'detail' => 'Child <code>cma/web.config</code> niet gevonden.', 'fix' => 'Doe <code>composer update stenversonline/platform</code>.'];
    }
    $hit = $xml->xpath("//rewrite/rules/rule[@name='Dashboard']");
    if (!empty($hit)) {
        return ['label' => $label, 'status' => 'pass', 'detail' => 'Aanwezig — <code>/cma/dashboard</code> kan extensionless worden bereikt.', 'fix' => ''];
    }
    return ['label' => $label, 'status' => 'fail', 'detail' => 'Dashboard-rule ontbreekt in kind-config.', 'fix' => 'Doe <code>composer update stenversonline/platform</code>.'];
}

function cma_doc_check_url_rewrite_module_active(): array {
    // Self-test: do a tiny HTTP HEAD request to /cma/dashboard. If IIS URL
    // Rewrite Module is loaded AND the child cma/web.config rules are being
    // applied, the rewrite fires and dashboard.php answers (200, or 302 to
    // login if not logged in). If the module is missing or the child config
    // is blocked, IIS looks for a literal `cma/dashboard` file, finds none,
    // and returns 404. Discriminator: 404 = module/config inactive; anything
    // else = working.
    $label = 'IIS URL Rewrite Module — extensionless /cma/* paden werken';

    if (PHP_SAPI === 'cli') {
        return ['label' => $label, 'status' => 'info', 'detail' => 'CLI-context: zelf-test niet uitvoerbaar.', 'fix' => ''];
    }
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return ['label' => $label, 'status' => 'info', 'detail' => 'Host onbekend, zelf-test niet uitvoerbaar.', 'fix' => ''];
    }
    if (!function_exists('curl_init')) {
        return ['label' => $label, 'status' => 'info', 'detail' => 'cURL niet geladen, zelf-test overgeslagen.', 'fix' => ''];
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $url    = $scheme . '://' . $host . '/cma/dashboard';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    curl_exec($ch);
    $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    curl_close($ch);

    if ($errno !== 0) {
        return ['label' => $label, 'status' => 'info', 'detail' => 'cURL-fout bij zelf-test: ' . htmlspecialchars($err) . ' (errno ' . $errno . ').', 'fix' => ''];
    }
    if ($code === 200 || ($code >= 300 && $code < 400)) {
        return ['label' => $label, 'status' => 'pass', 'detail' => '<code>/cma/dashboard</code> antwoordt met HTTP <code>' . $code . '</code> — module en child-config werken samen.', 'fix' => ''];
    }
    if ($code === 404) {
        return [
            'label'  => $label,
            'status' => 'fail',
            'detail' => '<code>/cma/dashboard</code> geeft HTTP <code>404</code> terwijl <code>/cma/dashboard.php</code> (vermoedelijk) wel werkt. URL Rewrite Module is niet actief voor het <code>/cma</code>-pad óf de child <code>cma/web.config</code> wordt niet geapplied.',
            'fix'    => 'Stappen, in volgorde: (1) verifieer dat de IIS URL Rewrite Module geïnstalleerd is — Server Manager → Web Server (IIS) → Roles, of download van <a href="https://www.iis.net/downloads/microsoft/url-rewrite" target="_blank" rel="noopener">iis.net/downloads/microsoft/url-rewrite</a>. (2) Recycle de app-pool (touch <code>web.config</code>). (3) Check <code>%windir%\\system32\\inetsrv\\config\\applicationHost.config</code> op <code>&lt;section name="rewrite" overrideMode="Allow"/&gt;</code> en geen <code>inheritInChildApplications="false"</code> op een parent <code>&lt;location&gt;</code>.',
        ];
    }
    return ['label' => $label, 'status' => 'warn', 'detail' => '<code>/cma/dashboard</code> antwoordt met onverwachte HTTP <code>' . $code . '</code>.', 'fix' => 'Check de child-config Dashboard-rule en eventuele parent-rewrites die <code>/cma</code> niet doorlaten.'];
}

function cma_doc_check_child_default_content_type(): array {
    $label = 'Child cma/web.config: outbound "Default Content-Type" rule';
    $xml = cma_doc_child_webconfig();
    if ($xml === null) {
        return ['label' => $label, 'status' => 'fail', 'detail' => 'Child <code>cma/web.config</code> niet gevonden.', 'fix' => 'Doe <code>composer update stenversonline/platform</code>.'];
    }
    $hit = $xml->xpath("//rewrite/outboundRules/rule[@name='Default Content-Type to text/html']");
    if (!empty($hit)) {
        return ['label' => $label, 'status' => 'pass', 'detail' => 'Aanwezig.', 'fix' => ''];
    }
    return ['label' => $label, 'status' => 'fail', 'detail' => 'Regel ontbreekt in kind-config (outbound rules erven niet over).', 'fix' => 'Sinds v1.19.9 standaard. <code>composer update stenversonline/platform</code>.'];
}

function cma_doc_check_child_404_handler(): array {
    $label = 'Child cma/web.config: 404 handler → /cma/404.php';
    $xml = cma_doc_child_webconfig();
    if ($xml === null) {
        return ['label' => $label, 'status' => 'fail', 'detail' => 'Child <code>cma/web.config</code> niet gevonden.', 'fix' => 'Doe <code>composer update stenversonline/platform</code>.'];
    }
    $hit = $xml->xpath("//httpErrors/error[@statusCode='404']");
    if (!empty($hit)) {
        return ['label' => $label, 'status' => 'pass', 'detail' => 'Geconfigureerd.', 'fix' => ''];
    }
    return ['label' => $label, 'status' => 'warn', 'detail' => 'Geen custom 404-handler — IIS-default error-pagina verschijnt.', 'fix' => 'Voeg <code>&lt;httpErrors&gt;</code> blok toe.'];
}

function cma_doc_check_env_file(): array {
    $label = 'Actief .env-bestand';
    $envName = (string)($GLOBALS['_env_file'] ?? '.env');
    $envPath = cma_doc_site_root() . '/' . $envName;
    if (is_file($envPath) && is_readable($envPath)) {
        return ['label' => $label, 'status' => 'pass', 'detail' => '<code>' . htmlspecialchars($envName) . '</code> aanwezig + leesbaar.', 'fix' => ''];
    }
    if (is_file($envPath)) {
        return ['label' => $label, 'status' => 'fail', 'detail' => '<code>' . htmlspecialchars($envName) . '</code> bestaat maar is niet leesbaar voor de IIS-user.', 'fix' => 'Geef leesrechten aan de application-pool identity.'];
    }
    return ['label' => $label, 'status' => 'fail', 'detail' => '<code>' . htmlspecialchars($envName) . '</code> ontbreekt op <code>' . htmlspecialchars($envPath) . '</code>.', 'fix' => 'Kopieer uit <code>.env.template</code> of unset <code>APP_ENVIRONMENT</code>.'];
}

function cma_doc_check_app_environment_match(): array {
    $label = 'APP_ENVIRONMENT vs. actief .env-bestand';
    $envName = (string)($GLOBALS['_env_file'] ?? '.env');
    $appEnv  = (string)($GLOBALS['_app_env'] ?? '');
    $expected = ['L' => '.env.local', 'O' => '.env.development', 'T' => '.env.test', 'A' => '.env.acceptance', 'P' => '.env.production'];
    if ($appEnv === '' || !isset($expected[$appEnv])) {
        return ['label' => $label, 'status' => 'info', 'detail' => 'Auto-detect actief (<code>APP_ENVIRONMENT</code> niet gezet). Gebruikt: <code>' . htmlspecialchars($envName) . '</code>.', 'fix' => ''];
    }
    if ($envName === $expected[$appEnv]) {
        return ['label' => $label, 'status' => 'pass', 'detail' => '<code>APP_ENVIRONMENT=' . htmlspecialchars($appEnv) . '</code> ↔ <code>' . htmlspecialchars($envName) . '</code>.', 'fix' => ''];
    }
    return ['label' => $label, 'status' => 'warn', 'detail' => '<code>APP_ENVIRONMENT=' . htmlspecialchars($appEnv) . '</code> verwacht <code>' . htmlspecialchars($expected[$appEnv]) . '</code> maar geladen is <code>' . htmlspecialchars($envName) . '</code>.', 'fix' => 'Sinds v1.19.7 zou de bootstrap loud falen — als je dit ziet draai je nog een oudere versie.'];
}

function cma_doc_check_deploy_secret(): array {
    // Read DEPLOY_SECRET length WITHOUT echoing the value. Privacy.
    $label = 'DEPLOY_SECRET';
    $secret = (string)(getenv('DEPLOY_SECRET') ?: ($_ENV['DEPLOY_SECRET'] ?? ''));
    if ($secret === '') {
        // Try .env scan since Dotenv::safeLoad doesn't necessarily expose to getenv depending on config
        $envFile = cma_doc_site_root() . '/' . ($GLOBALS['_env_file'] ?? '.env');
        if (is_file($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (preg_match('/^\s*DEPLOY_SECRET\s*=\s*["\']?([^"\'\r\n#]+)/', $line, $m)) {
                    $secret = trim($m[1]);
                    break;
                }
            }
        }
    }
    if ($secret === '') {
        return ['label' => $label, 'status' => 'warn', 'detail' => 'Niet gezet — deploy-webhook returnt 503. OK voor dev-machines zonder webhook.', 'fix' => 'Genereer met <code>openssl rand -hex 32</code> en zet in <code>.env</code>.'];
    }
    $len = strlen($secret);
    if ($len >= 32 && ctype_xdigit($secret)) {
        return ['label' => $label, 'status' => 'pass', 'detail' => 'Gezet, ' . $len . ' hex-chars.', 'fix' => ''];
    }
    return ['label' => $label, 'status' => 'warn', 'detail' => 'Gezet, ' . $len . ' chars — niet hex of korter dan 32. Webhook werkt maar entropie is laag.', 'fix' => 'Roteer met <code>openssl rand -hex 32</code> + zelfde waarde in GitHub-webhook.'];
}

function cma_doc_check_dir_writable(string $relPath, string $description, string $severityIfMissing = 'fail'): array {
    $label = $description;
    $path = cma_doc_site_root() . '/' . $relPath;
    if (!is_dir($path)) {
        return ['label' => $label, 'status' => $severityIfMissing, 'detail' => '<code>' . htmlspecialchars($relPath) . '/</code> bestaat niet.', 'fix' => 'Maak de directory aan en geef schrijfrechten aan de IIS-user.'];
    }
    if (!is_writable($path)) {
        return ['label' => $label, 'status' => 'fail', 'detail' => '<code>' . htmlspecialchars($relPath) . '/</code> bestaat maar is niet schrijfbaar voor de IIS-user.', 'fix' => 'Geef Modify-rechten aan de application-pool identity.'];
    }
    return ['label' => $label, 'status' => 'pass', 'detail' => '<code>' . htmlspecialchars($relPath) . '/</code> schrijfbaar.', 'fix' => ''];
}

function cma_doc_check_logs_dir(): array {
    return cma_doc_check_dir_writable('logs', 'logs/ — deploy.log + php_errors.log');
}

function cma_doc_check_data_logs_dir(): array {
    return cma_doc_check_dir_writable('data/logs', 'data/logs/ — app/performance/debug logs', 'warn');
}

function cma_doc_check_cache_dir(): array {
    return cma_doc_check_dir_writable('cache', 'cache/ — OpCache + form-cache', 'warn');
}

function cma_doc_check_php_error_log(): array {
    $label = 'php.ini error_log destination';
    $cfg = ini_get('error_log');
    if ($cfg === '' || $cfg === false) {
        return ['label' => $label, 'status' => 'info', 'detail' => 'Niet expliciet geconfigureerd — gaat naar webserver default (IIS event log of stderr).', 'fix' => 'Stel in <code>php.ini</code> in: <code>error_log = "C:\\…\\logs\\php_errors.log"</code>.'];
    }
    if (!file_exists($cfg) && !is_writable(dirname($cfg))) {
        return ['label' => $label, 'status' => 'fail', 'detail' => 'Geconfigureerd op <code>' . htmlspecialchars($cfg) . '</code> maar pad niet bestaande + dir niet schrijfbaar.', 'fix' => 'Maak het pad aan en geef rechten.'];
    }
    if (file_exists($cfg) && !is_writable($cfg)) {
        return ['label' => $label, 'status' => 'fail', 'detail' => '<code>' . htmlspecialchars($cfg) . '</code> bestaat maar is niet schrijfbaar.', 'fix' => 'Geef Modify-rechten.'];
    }
    return ['label' => $label, 'status' => 'pass', 'detail' => '<code>' . htmlspecialchars($cfg) . '</code> schrijfbaar.', 'fix' => ''];
}

function cma_doc_check_vendor_in_sync(): array {
    $label = 'vendor/stenversonline/platform versie ↔ Bootstrap::getPlatformVersion()';
    if (!class_exists('\\App\\Library\\Bootstrap')) {
        return ['label' => $label, 'status' => 'warn', 'detail' => 'Bootstrap-class niet geladen.', 'fix' => ''];
    }
    $detected = \App\Library\Bootstrap::getPlatformVersion();
    // CMA_APP_VERSION wordt in cma/bootstrap.inc op zelfde manier gezet.
    $constant = defined('CMA_APP_VERSION') ? (string)constant('CMA_APP_VERSION') : '';
    if ($constant === '' || $constant === $detected) {
        return ['label' => $label, 'status' => 'pass', 'detail' => 'Versie: <code>' . htmlspecialchars($detected) . '</code>.', 'fix' => ''];
    }
    return ['label' => $label, 'status' => 'warn', 'detail' => 'Detected <code>' . htmlspecialchars($detected) . '</code> vs constant <code>' . htmlspecialchars($constant) . '</code>.', 'fix' => 'Doe <code>composer update stenversonline/platform</code>.'];
}

function cma_doc_check_deploy_log(): array {
    $label = 'logs/deploy.log';
    $path = cma_doc_site_root() . '/logs/deploy.log';
    if (!file_exists($path)) {
        return ['label' => $label, 'status' => 'info', 'detail' => 'Nog niet aangemaakt — webhook is op deze site nog niet gevuurd.', 'fix' => ''];
    }
    $age = time() - filemtime($path);
    $ageStr = $age < 3600 ? round($age / 60) . ' min' : ($age < 86400 ? round($age / 3600) . ' uur' : round($age / 86400) . ' dagen');
    if (!is_readable($path)) {
        return ['label' => $label, 'status' => 'fail', 'detail' => 'Bestaat maar niet leesbaar voor de IIS-user.', 'fix' => 'Geef Read-rechten.'];
    }
    return ['label' => $label, 'status' => 'pass', 'detail' => 'Laatst gewijzigd: ' . $ageStr . ' geleden, ' . number_format(filesize($path)) . ' bytes.', 'fix' => ''];
}

function cma_doc_run_checks(array $checks): array {
    $results = [];
    foreach ($checks as $fn) {
        try {
            $r = $fn();
        } catch (\Throwable $e) {
            $r = ['label' => '(check ' . $fn . ')', 'status' => 'warn', 'detail' => 'Check threw: ' . htmlspecialchars($e->getMessage()), 'fix' => ''];
        }
        if (is_array($r) && isset($r['label'], $r['status'], $r['detail'])) {
            if (!isset($r['fix'])) { $r['fix'] = ''; }
            $results[] = $r;
        }
    }
    return $results;
}

function cma_doc_render_check_table(string $title, array $results): void {
    $labelType = ['pass' => 'success', 'fail' => 'error', 'warn' => 'warning', 'info' => 'information'];
    $statusText = ['pass' => 'OK', 'fail' => 'FOUT', 'warn' => 'LET OP', 'info' => 'INFO'];
    $counts = ['pass' => 0, 'fail' => 0, 'warn' => 0, 'info' => 0];
    foreach ($results as $r) { $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1; }
    ?>
    <h2><?= htmlspecialchars($title) ?></h2>
    <p class="docs-meta">
        Live check op deze site —
        <lib-label type="success"><?= $counts['pass'] ?> OK</lib-label>
        <?php if ($counts['fail'] > 0): ?> <lib-label type="error"><?= $counts['fail'] ?> fout</lib-label><?php endif; ?>
        <?php if ($counts['warn'] > 0): ?> <lib-label type="warning"><?= $counts['warn'] ?> waarschuwing</lib-label><?php endif; ?>
        <?php if ($counts['info'] > 0): ?> <lib-label type="information"><?= $counts['info'] ?> info</lib-label><?php endif; ?>
    </p>
    <table class="listtable">
        <thead><tr class="listheader"><th>Check</th><th style="width:110px">Status</th><th>Detail</th><th>Fix</th></tr></thead>
        <tbody>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td><?= $r['label'] ?></td>
                    <td><lib-label type="<?= $labelType[$r['status']] ?? 'information' ?>"><?= $statusText[$r['status']] ?? '?' ?></lib-label></td>
                    <td><?= $r['detail'] ?></td>
                    <td><?= $r['fix'] !== '' ? $r['fix'] : '—' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
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

    <h2>Voor ontwikkelaars</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:220px">Onderwerp</th><th>Inhoud</th></tr></thead>
        <tbody>
            <tr><td><a href="documentation.php?topic=architecture"><span class="lnr lnr-layers"></span> Architectuur</a></td><td>Layer-map, namespace-conventies, boot sequence, legacy ASP-erfenis.</td></tr>
            <tr><td><a href="documentation.php?topic=new_tool"><span class="lnr lnr-construction"></span> Een CMA-tool toevoegen</a></td><td>File-skeleton, registratie in tools.php tile-grid, URL-aliassen, isAdmin/isDeveloper.</td></tr>
            <tr><td><a href="documentation.php?topic=database"><span class="lnr lnr-database"></span> Database &amp; RecordSet</a></td><td>Database::executeQuery PDO, RecordSet ADO-emulatie, connectie-namen, SQL-helpers.</td></tr>
            <tr><td><a href="documentation.php?topic=migrations"><span class="lnr lnr-arrow-right"></span> Migraties schrijven</a></td><td>Bestandsnaam, MigrationService flow, change-types, idempotente-invariant.</td></tr>
            <tr><td><a href="documentation.php?topic=json_forms"><span class="lnr lnr-text-format"></span> JSON-gedreven formulieren</a></td><td>JsonFormLoader + JsonFormRenderer, schema basics, form.php entry point, extraButtons placeholders.</td></tr>
            <tr><td><a href="documentation.php?topic=web_components"><span class="lnr lnr-bubble"></span> Web components ontwikkelen</a></td><td>lib- vs cma- prefix, shadow DOM, minified counterpart, Storybook-integratie, icon-conventies.</td></tr>
            <tr><td><a href="documentation.php?topic=errors"><span class="lnr lnr-bug"></span> Logging &amp; errors (dev)</a></td><td>LibLog en CmaErrorHandler interna, error-flow, sensitive-data scrubbing in code.</td></tr>
            <tr><td><a href="documentation.php?topic=releasing"><span class="lnr lnr-tag"></span> Releasen &amp; versies</a></td><td>composer.json version bump, git tag, semver, REMOVED_PATHS voor retired bestanden.</td></tr>
        </tbody>
    </table>

    <h2>Troubleshooting &amp; referentie</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:220px">Onderwerp</th><th>Inhoud</th></tr></thead>
        <tbody>
            <tr><td><a href="documentation.php?topic=troubleshooting"><span class="lnr lnr-warning"></span> Troubleshooting</a></td><td>Catalog van bekende symptomen + root cause + fix-versie.</td></tr>
            <tr><td><a href="documentation.php?topic=mail"><span class="lnr lnr-envelope"></span> Mail-configuratie</a></td><td>Email API, SMTP config-keys, test/local-mode, EmailLogService afterSend hook.</td></tr>
            <tr><td><a href="documentation.php?topic=llm"><span class="lnr lnr-brain"></span> LLM-configuratie</a></td><td>Engines, env-vars, curated modellenlijst, Anthropic-fallback.</td></tr>
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
        <li>De Installer kopieert tegelijk eenmalig de template-bestanden uit <code>templates/</code> als ze nog niet bestaan op de site-root: <code>_bootstrap.php</code>, <code>_bootstrap_wrapper.php</code>, <code>web.config</code>, <code>app.php</code>, <code>global.asa.php</code>, <code>.env.example</code>, en <code>assets/css/cma.css</code> (uit <code>cma.css.template</code>). <code>_bootstrap_constants.inc</code> wordt apart gekopieerd. Bestaande bestanden worden NOOIT overschreven.</li>
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
            <tr><td><code>logs/</code></td><td>deploy.log, php_errors.log (mits <code>php.ini</code> daar wijst).</td></tr>
            <tr><td><code>data/logs/</code></td><td>app-log, performance-log (in <code>data/logs/perf/</code>), debug-log. Wordt door Logger/PerformanceLogger/api-log.php gecreëerd indien afwezig, maar de IIS-user heeft schrijfrechten op de parent <code>data/</code> nodig.</td></tr>
            <tr><td><code>cache/</code></td><td>cache.log, OpCache-files, form-definition cache, perf-log oude locatie waar logreader nog naar zoekt.</td></tr>
            <tr><td><code>cma/logs/</code></td><td>404 log; oude locatie waar logreader nog naar debug-logs zoekt.</td></tr>
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

    <?php
    cma_doc_render_check_table('Omgeving — live check op deze site', cma_doc_run_checks([
        'cma_doc_check_env_file',
        'cma_doc_check_app_environment_match',
        'cma_doc_check_vendor_in_sync',
    ]));
    ?>

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
    <p><code>Bootstrap::detectAndLoadEnv()</code> bepaalt het in deze volgorde:</p>
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
        <li><span class="cma-tool__strong">Deploy</span>: <code>DEPLOY_SECRET</code>, <code>DEPLOY_BRANCH</code>, <code>DEPLOY_SITE_ROOT</code>, <code>DEPLOY_PIPELINE</code>, <code>DEPLOY_COMPOSER_UPDATE</code>, <code>DEPLOY_RUN_TESTS</code>, <code>DEPLOY_RECYCLE_TOUCH</code>, <code>DEPLOY_LOG_FILE</code>, <code>DEPLOY_POST_HOOK</code> — zie <a href="documentation.php?topic=deployment">Deployment</a>.</li>
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

    <?php
    cma_doc_render_check_table('Deployment — live check op deze site', cma_doc_run_checks([
        'cma_doc_check_deploy_secret',
        'cma_doc_check_logs_dir',
        'cma_doc_check_deploy_log',
    ]));
    ?>

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

    <h2>Remote deploy-status check (sinds v1.19.1)</h2>
    <p>Publiek read-only endpoint dat de laatste run uit <code>logs/deploy.log</code> als JSON teruggeeft. Geen auth — status / commit-SHA / branch / timestamp zijn niet gevoelig (commit-SHAs staan al in de public git history, branch-namen ook), en het log bevat per conventie geen secrets in zijn pipeline-output. Volledig standalone: geen Composer autoload, geen platform-bootstrap, geen <code>.env</code>-reader — werkt dus ook als <code>vendor/</code> of <code>.env</code> stuk is.</p>
    <pre><code>curl 'https://&lt;host&gt;/cma/tools/deploy_status.php'</code></pre>
    <p>Success-respons:</p>
    <pre><code>{
    "ok":               true,
    "status":           "OK",            // of "FAILED" of "RUNNING"
    "branch":           "main",
    "commit":           "c4ed4d5",
    "ended_at":         "2026-05-30 14:23:14",
    "duration_seconds": 13,
    "age_seconds":      312,
    "running":          false,
    "log_tail":         "...laatste 40 regels van deploy.log..."
}
</code></pre>
    <p>Foutgevallen:</p>
    <ul>
        <li>HTTP <code>404</code> met <code>{"ok": false, "error": "deploy.log not found", "path": "..."}</code> — de webhook heeft nog nooit op deze site gedraaid, of de <code>logs/</code> directory bestaat niet.</li>
        <li>HTTP <code>500</code> met <code>{"ok": false, "error": "log file unreadable", "path": "..."}</code> — <span class="cma-tool__strong">sinds v1.19.6</span>: bestand bestaat maar is niet leesbaar (permissies of lock). Pre-v1.19.6 viel deze case stilletjes door en eindigde als "no completed deploy" — wat de operator de verkeerde kant op stuurde.</li>
        <li>HTTP <code>200</code> met <code>{"ok": false, "error": "no completed deploy in log"}</code> — log-bestand bestaat wel maar er staat nog geen banner-bracketed run in.</li>
    </ul>

    <h3>Triage-flow</h3>
    <ol>
        <li>Curl het endpoint. Krijg je een netwerkfout of HTML in plaats van JSON, dan is de site down of <code>web.config</code> stuk — fix dat eerst.</li>
        <li><code>status: "OK"</code> + lage <code>age_seconds</code> → deploy is geslaagd.</li>
        <li><code>status: "FAILED"</code> → lees <code>log_tail</code> om te zien welke stap omviel. Usual suspects: <code>git pull</code>, <code>composer update</code>, de post-deploy hook.</li>
        <li><code>status: "RUNNING"</code> waarbij <code>age_seconds</code> blijft groeien voorbij een paar minuten → de deploy hangt. Kijk direct in <code>logs/deploy.log</code> op de server.</li>
        <li><code>404 "deploy.log not found"</code> → de webhook heeft nog niet gevuurd op deze site. Check de GitHub webhook delivery log en <code>DEPLOY_SECRET</code>.</li>
    </ol>

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
            <tr><td><code>DEPLOY_RUN_TESTS</code></td><td><lib-label type="information">nee</lib-label></td><td><code>(leeg)</code></td><td><span class="cma-tool__strong">Sinds v1.20.5</span>. Commando dat NA <code>composer update</code> en VÓÓR recycle draait. Non-zero exit → deploy FAILED, geen recycle, geen post-hook (productie blijft op oude code). Voorbeeld: <code>php cma/tests/TestRunner.php</code>. Leeg / <code>-</code> = geen gate.</td></tr>
            <tr><td><code>DEPLOY_RECYCLE_TOUCH</code></td><td><lib-label type="information">nee</lib-label></td><td><code>web.config</code></td><td>Bestand om te touch'en na succes (IIS app-pool recycle). Set <code>-</code> om over te slaan.</td></tr>
            <tr><td><code>DEPLOY_LOG_FILE</code></td><td><lib-label type="information">nee</lib-label></td><td><code>logs/deploy.log</code></td><td>Locatie van de deploy-log voor de webhook-writer. <span class="cma-tool__strong">Let op</span>: <code>deploy_status.php</code> leest sinds v1.19.1 alleen het default pad <code>logs/deploy.log</code> — een override hier wordt door de reader genegeerd.</td></tr>
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
    <p class="docs-meta">Succesvolle response is HTTP 202 (asynchrone acceptatie). 401 = secret-mismatch; 403 = verkeerde branch. <span class="cma-tool__strong">Sinds v1.19.6</span>: 503 als <code>logs/</code> niet schrijfbaar is voor de IIS-user — de fix-instructie staat in de response body en in <code>php_errors.log</code>. Eerder verdween de deploy stilletjes na een 202.</p>

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
            <tr><td>Webhook geeft "Parse error: syntax error, unexpected '?'" of "Unsupported declare 'strict_types'"</td><td>IIS heeft een oude PHP-handler aan het <code>cma/tools/</code>-pad gekoppeld (PHP 5.x ipv 8.x). De webhook is sinds v1.20.11 bewust PHP 5.6-compatibel geschreven juist om dit soort recovery-scenario's af te dekken — als je dit nu nog ziet, run <code>composer update stenversonline/platform</code>. Permanente fix: in IIS Manager → site → Handler Mappings → zorg dat <code>*.php</code> in <code>cma/tools/</code> dezelfde PHP-versie gebruikt als de rest van de site.</td></tr>
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

    <?php
    cma_doc_render_check_table('Logs — live check op deze site', cma_doc_run_checks([
        'cma_doc_check_logs_dir',
        'cma_doc_check_data_logs_dir',
        'cma_doc_check_cache_dir',
        'cma_doc_check_php_error_log',
    ]));
    ?>

    <h2>Welke log waar?</h2>
    <p>Alle paden relatief aan de site-root (typisch <code>C:\wwwroot\&lt;site&gt;\</code>).</p>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:160px">Naam</th><th style="width:280px">Schrijflocatie</th><th>Inhoud</th></tr></thead>
        <tbody>
            <tr><td>PHP error log</td><td><code>ini_get('error_log')</code> — typisch <code>logs/php_errors.log</code></td><td>Alle uncaught exceptions, fatal errors, warnings (in dev), en <code>error_log()</code> output. Locatie hangt af van <code>php.ini</code>.</td></tr>
            <tr><td>Deploy log</td><td><code>logs/deploy.log</code></td><td>Output van elke deploy-pipeline; banner per run. Override via <code>DEPLOY_LOG_FILE</code>.</td></tr>
            <tr><td>Application log</td><td><code>data/logs/app_YYYY-MM-DD.log</code></td><td>Structured JSON-per-regel logs van <code>Cma\Services\Logger</code>. Productie: WARNING+; dev/test: DEBUG+. <code>$logDir</code> gezet door <code>Logger.php</code> line 88.</td></tr>
            <tr><td>Performance log</td><td><code>data/logs/perf/perf_YYYY-MM-DD.log</code></td><td>Timing metrics van <code>PerformanceLogger</code> (queries, API-calls, memory). Aan via <code>PERF_LOG_ENABLED=true</code>. Locatie gezet door <code>PerformanceLogger.php</code> line 75.</td></tr>
            <tr><td>Debug log</td><td><code>data/logs/debug_YYYY-MM-DD.log</code></td><td>Verbose debug van <code>cma/api/log.php</code> wanneer browser-side <code>LibLog</code> debug-mode aan zet. <code>$logsDir</code> gezet door <code>api/log.php</code> line 44.</td></tr>
            <tr><td>404 log</td><td><code>cma/logs/404_YYYY-MM-DD.log</code></td><td>Niet-gevonden URLs gevangen door <code>cma/404.php</code>.</td></tr>
            <tr><td>Cache log</td><td><code>cache/cache.log</code></td><td>Cache-hit/-miss events. Aan via <code>CACHE_LOG_ENABLED=true</code>.</td></tr>
            <tr><td>JS errors (DB)</td><td>Tabel <code>tblCMAJavascriptErrors</code></td><td>Client-side errors gevangen door <code>CmaErrorHandler</code>. Rate-limited tot 100 per IP per uur.</td></tr>
        </tbody>
    </table>

    <div class="docs-callout docs-callout--warn">
        <span class="cma-tool__strong">Bekende discrepantie (codebase-bug):</span> de logreader-UI verwacht performance-logs in <code>cache/perf_logs/perf_*.log</code> en debug-logs in <code>cma/logs/debug_*.log</code>, terwijl de writers ze naar <code>data/logs/perf/</code> en <code>data/logs/</code> sturen. Resultaat: voor Performance en Debug toont de logreader-UI vaak "geen entries gevonden" omdat hij in de verkeerde map kijkt. Tail de bestanden direct via de console totdat dit gesynchroniseerd is. Files-on-disk zijn de bron van waarheid; logreader paden moeten matchen.
    </div>

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

    <?php
    cma_doc_render_check_table('web.config — live check op deze site', cma_doc_run_checks([
        'cma_doc_check_cma_is_iis_application',
        'cma_doc_check_url_rewrite_module_active',
        'cma_doc_check_parent_skip_cma',
        'cma_doc_check_parent_default_content_type',
        'cma_doc_check_parent_nosniff',
        'cma_doc_check_parent_frame_options',
        'cma_doc_check_parent_hidden_segments',
        'cma_doc_check_child_dashboard_rule',
        'cma_doc_check_child_default_content_type',
        'cma_doc_check_child_404_handler',
    ]));
    ?>

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

    <div class="docs-callout docs-callout--danger">
        <p><span class="cma-tool__strong">Sinds v1.20.12: CMA-routes leven in het parent web.config</span> — niet meer in <code>cma/web.config</code>. Eerdere pogingen om dit via distributed rules in de child-config op te lossen liepen vast op (1) inheritance-issues bij Virtual Directory setup, (2) outbound-rule duplicate-name conflicts (500.50), (3) niet-matchende patterns wanneer <code>cma/</code> geen IIS Application is. De definitieve fix is migration <code>9.9.0_cma_routes_to_parent_webconfig.php</code> die de rewrite-rules direct in de parent zet (waar IIS er altijd bij kan zonder scope-complicaties). Idempotent via marker-comment, backup wordt automatisch gemaakt. De migratie valideert de gepatchte config vóór én na de write — XML well-formedness, duplicate rule-names (het 500.50-symptoom), PCRE-syntax van de eigen patterns, een <code>appcmd</code> schema-check, en tenslotte een live HTTP smoke-test op <code>/cma/dashboard</code>; bij een 5xx of een geweigerde <code>appcmd</code> rolt hij automatisch terug uit de backup. Run via <a href="documentation.php?topic=migrations">Migraties</a>-tool of <code>Tools → Migraties uitvoeren</code>.</p>
        <p style="margin:8px 0 0 0;">Sinds v1.20.20 past de Composer <code>Installer</code> diezelfde routes óók automatisch toe bij elke <code>composer update stenversonline/platform</code> — bestaande sites krijgen de fix dus zonder de migratie handmatig te draaien. De file-level safeguards (simplexml-check, XML well-formedness, duplicate-name, PCRE-regex, backup, atomic write, read-back, rollback) zitten in de gedeelde helper <code>App\Library\WebConfigCmaRoutes</code> die migratie én Installer delen; de <code>appcmd</code>- en live-smoke-test-stappen blijven migration-only (de composer-CLI heeft geen draaiende IIS + HTTP-context). Idempotent via dezelfde marker, dus veilig om elke update te draaien.</p>

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

    <h2>Belt-and-suspenders: default Content-Type</h2>
    <p>Sinds v1.19.9 staat in zowel <code>templates/web.config.template</code> als <code>cma/web.config</code> een outbound-rewrite die een lege <code>Content-Type</code> respons-header overschrijft naar <code>text/html; charset=UTF-8</code>:</p>
    <pre><code>&lt;outboundRules&gt;
    &lt;rule name="Default Content-Type to text/html" preCondition="ContentTypeMissing"&gt;
        &lt;match serverVariable="RESPONSE_Content-Type" pattern=".*" /&gt;
        &lt;action type="Rewrite" value="text/html; charset=UTF-8" /&gt;
    &lt;/rule&gt;
    &lt;preConditions&gt;
        &lt;preCondition name="ContentTypeMissing"&gt;
            &lt;add input="{RESPONSE_Content-Type}" pattern="^$" /&gt;
        &lt;/preCondition&gt;
    &lt;/preConditions&gt;
&lt;/outboundRules&gt;</code></pre>
    <p>Waarom: als PHP doodgaat vóór een header gezet is (fatal error, lege <code>default_mimetype</code>, output-buffer breuk zoals het v1.10.1 logreader-incident), gaat de response zonder Content-Type de deur uit. Mobile Safari — vooral als geïnstalleerde PWA — combineert dit met onze <code>X-Content-Type-Options: nosniff</code> en weigert MIME-sniffing, met als gevolg dat de pagina als <span class="cma-tool__strong">download</span> wordt aangeboden ("Download logreader.php?"). De regel vuurt alleen als de header echt leeg is — door PHP gezette Content-Types blijven onaangeroerd.</p>

    <h2>App-pool recycle</h2>
    <p>Touch op <code>web.config</code> triggert een app-pool recycle in IIS — alle PHP-OpCache state, in-memory session-handlers en lopende processes worden geflushed. Het deploy-webhook script doet dit standaard na een succesvolle pipeline; je kan het handmatig forceren met:</p>
    <pre><code>copy /b web.config +,, </code></pre>
    <p>Of via de tool <a href="tools/opcache_reset.php" target="_top">Tools → OPcache reset</a>.</p>

    <h2>Troubleshooting</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak / Fix</th></tr></thead>
        <tbody>
            <tr><td><code>/cma/dashboard</code> → 404, <code>/cma/dashboard.php</code> werkt wél</td><td>Klassiek symptoom van "URL Rewrite Module rules in het child <code>cma/web.config</code> worden niet geapplied". Drie meest voorkomende oorzaken: (1) <strong class="cma-tool__strong">cma/ is in IIS Manager als aparte Application geconfigureerd</strong> — dan moet de rewrite-config OPNIEUW worden geregistreerd in die app-context, óf de app moet worden teruggezet naar een virtuele directory binnen de parent app. (2) <strong class="cma-tool__strong">URL Rewrite Module is niet meer geïnstalleerd</strong> (Windows-update kan het verwijderen) — herinstalleer van <a href="https://www.iis.net/downloads/microsoft/url-rewrite" target="_blank" rel="noopener">iis.net/downloads/microsoft/url-rewrite</a>. (3) <strong class="cma-tool__strong"><code>applicationHost.config</code> heeft <code>&lt;section name="rewrite" overrideMode="Deny"/&gt;</code></strong> waardoor child-configs geen rewrite-rules mogen toevoegen — zet om naar <code>Allow</code>. De live-check bovenaan deze pagina doet een HTTP HEAD op <code>/cma/dashboard</code> en toont direct of het werkt.</td></tr>
            <tr><td><code>/cma/preferences</code> → 404 (en <code>/cma/dashboard</code> ook)</td><td>Zelfde diagnose als hierboven — alle extensionless URLs in de child-config falen samen.</td></tr>
            <tr><td><code>/cma/dashboard</code> → 404 op nieuwe install</td><td>"Skip /cma" rule ontbreekt in parent web.config. Zie callout hierboven en <a href="documentation.php?topic=iis_config">live-check</a> bovenaan deze pagina.</td></tr>
            <tr><td><code>/cma/dashboard.php</code> → 500 Server Error</td><td>Allowed server variables niet ontgrendeld. Zie <a href="documentation.php?topic=installation">Installatie</a>.</td></tr>
            <tr><td><code>/cma/tools/&lt;naam&gt;</code> → 404 maar <code>?tool=&lt;naam&gt;</code> werkt wel</td><td>URL Rewrite Module ontbreekt of de "CMA Tools Friendly URL" regel staat niet in de site-root web.config.</td></tr>
            <tr><td><code>/cma/tools?tool=X</code> verliest de <code>?tool=X</code></td><td>De Tools Directory rewrite-rule in <code>cma/web.config</code> mist <code>appendQueryString="true"</code>. Sinds v1.20.7 standaard aanwezig — run <code>composer update stenversonline/platform</code>.</td></tr>
            <tr><td>Site geeft IIS default 404, niet cma/404.php</td><td><code>cma/404.php</code> bestaat niet op disk (Installer-sync incompleet). Run <code>composer update stenversonline/platform</code>.</td></tr>
            <tr><td>Mobile Safari prompts "Download logreader.php?"</td><td>Sinds v1.10.1 gefixed (@-suppress op file_put_contents in delete-handler zodat warnings niet de Location-redirect breken).</td></tr>
        </tbody>
    </table>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=installation">Installatie</a>, <a href="documentation.php?topic=deployment">Deployment</a> (web.config touch voor recycle), <a href="documentation.php?topic=security">Beveiliging</a> (security-headers).
    </div>
    <?php
}

// -------------------------------------------------------------------------
// VOOR ONTWIKKELAARS
// -------------------------------------------------------------------------

function render_doc_architecture(): void
{
    ?>
    <h1>Architectuur</h1>
    <p class="docs-meta">Hoe het platform is opgebouwd: lagen, namespaces, boot sequence, en wat van legacy ASP komt.</p>

    <h2>Lagen</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:170px">Map</th><th>Namespace</th><th>Inhoud</th></tr></thead>
        <tbody>
            <tr><td><code>src/helpers/</code></td><td><code>App\Library\*</code> (PSR-4, Composer-autoloaded)</td><td>Stateless static-method helpers: Database, Application, Bootstrap, Request, Response, RecordSet, SQL, Session, Email, etc. Worden door alle consumer-sites geconsumeerd via composer.</td></tr>
            <tr><td><code>library/</code></td><td>—</td><td>Gedeelde frontend: jQuery, CSS, JS, plus legacy <code>lib_*.inc</code> include files met procedurele helpers. <code>library/webcomponents/</code> bevat alle <code>lib-*</code> web components.</td></tr>
            <tr><td><code>cma/</code></td><td><code>Cma\*</code> (require_once, NIET PSR-4)</td><td>CMA admin-applicatie. Entry: <code>bootstrap.inc</code>. <code>cma/classes/Services/</code> bevat service-classes (BackupService, MigrationService, MenuService, JsonFormService, etc.). <code>cma/webcomponents/</code> bevat <code>cma-*</code> componenten.</td></tr>
            <tr><td><code>templates/</code></td><td>—</td><td>Project-level templates (<code>.template</code> extensie). Eenmalig gekopieerd bij de eerste install van een consumer-site; daarna nooit overschreven.</td></tr>
        </tbody>
    </table>

    <h2>Namespace conventies</h2>
    <ul>
        <li><span class="cma-tool__strong"><code>App\Library\*</code></span> — PSR-4 mapping naar <code>src/helpers/</code> via composer.json. Wordt door Composer's autoloader gevonden zonder require's.</li>
        <li><span class="cma-tool__strong"><code>Cma\*</code></span> — <span class="cma-tool__em">geen</span> autoloading. Wordt expliciet via <code>require_once</code> uit <code>cma/bootstrap.inc</code> geladen. Services staan in <code>cma/classes/Services/&lt;Name&gt;.php</code>.</li>
    </ul>
    <p>De reden voor het splits: <code>App\Library\</code> moet ook werken in standalone contexten (CLI-scripts, deploy webhooks) waar <code>cma/bootstrap.inc</code> mogelijk niet draait. Composer autoload is genoeg om die te bereiken.</p>

    <h2>Boot sequence</h2>
    <ol>
        <li><span class="cma-tool__strong">IIS request komt binnen</span> — web.config rewrites routen naar <code>_bootstrap_wrapper.php</code> of een specifiek PHP-bestand.</li>
        <li><span class="cma-tool__strong">_bootstrap.php</span> (auto-prepended) draait — laadt <code>vendor/autoload.php</code>, registreert <code>App\Library\</code> autoload, en roept <code>App\Library\Bootstrap::init()</code> aan.</li>
        <li><span class="cma-tool__strong">Bootstrap::init()</span> draait in volgorde: <code>initEncoding</code>, <code>initSession</code>, <code>loadConstants</code>, <code>detectAndLoadEnv</code> (kiest welke .env), <code>configureErrorDisplay</code> (op basis van omgeving), <code>sqliteEmergencyRecovery</code> (als de flag staat), <code>loadDotenv</code> (phpdotenv), <code>initApplication</code> (zet <code>$GLOBALS['Application']</code> op), <code>registerErrorHandler</code>, en de loadLegacy* steps.</li>
        <li><span class="cma-tool__strong">cma/bootstrap.inc</span> wordt door tools/admin-pagina's met <code>require_once __DIR__ . '/../bootstrap.inc'</code> geladen — definieert <code>CMA_APP_VERSION</code>, laadt alle <code>Cma\</code> classes via require_once, registreert <code>EmailLogService</code> afterSend hook (sinds v1.12.1 met <code>class_exists</code> guard), doet migratie-controle voor admins.</li>
        <li><span class="cma-tool__strong">Het target script</span> (de tool / form.php / main.php) draait.</li>
    </ol>

    <h2>Legacy ASP-erfenis</h2>
    <p>De codebase is een conversie van een originele Classic ASP applicatie. Veel patronen weerspiegelen dat:</p>
    <ul>
        <li><span class="cma-tool__strong">RecordSet</span> — ADO-cursor emulatie boven PDOStatement. <code>$rs-&gt;EOF</code>, <code>$rs-&gt;Fields['kolom']</code>, <code>$rs-&gt;MoveNext()</code> komen letterlijk uit het ASP-tijdperk. Zie <a href="documentation.php?topic=database">Database &amp; RecordSet</a>.</li>
        <li><span class="cma-tool__strong">SQL::postString / SQL::postNumber</span> — escaping-helpers met "post" in de naam (ASP-jargon voor "form post variable"). Acceptabel in legacy code; nieuw werk gebruikt prepared statements.</li>
        <li><span class="cma-tool__strong">global.asa.php</span> — legacy locatie voor app-bootstrapping en secrets. In ASP was <code>global.asa</code> het standaard application-onload script.</li>
        <li><span class="cma-tool__strong">Application::get/set</span> — wrapper rond <code>$GLOBALS['Application']</code>, geïnspireerd op ASP's <code>Application</code> object.</li>
        <li><span class="cma-tool__strong">Veel <code>.inc</code> files</span> in <code>library/</code> — ASP gebruikte <code>.inc</code> voor include-bestanden; PHP-conversies behielden de extensie.</li>
        <li><span class="cma-tool__strong">ODBC-driver standaard</span> — historische binding aan MS Access (<code>.mdb</code>) databases. PDO ODBC is daarbovenop gebouwd; SQL Server en MySQL zijn ook ondersteund maar minder uitgewerkt.</li>
    </ul>

    <h2>Service classes</h2>
    <p>Hoger-niveau orkestratie zit in <code>cma/classes/Services/</code>. Belangrijkste:</p>
    <ul>
        <li><code>BackupService</code> — DB-backup workflow, zie <a href="documentation.php?topic=backups">Backups</a>.</li>
        <li><code>MigrationService</code> — migration runner, zie <a href="documentation.php?topic=migrations">Migraties schrijven</a>.</li>
        <li><code>JsonFormService</code> + <code>JsonFormLoader</code> + <code>JsonFormRenderer</code> — JSON form pipeline, zie <a href="documentation.php?topic=json_forms">JSON-gedreven formulieren</a>.</li>
        <li><code>ListService</code>, <code>TableService</code>, <code>TreeService</code> — list / detail rendering en navigatie.</li>
        <li><code>Logger</code> + <code>PerformanceLogger</code> — server-side logging, zie <a href="documentation.php?topic=logs">Logs &amp; monitoring</a>.</li>
        <li><code>MenuService</code> — leest <code>data/menu.json</code> (per-site) of <code>cma/config/menu.json</code> (platform-default fallback).</li>
        <li><code>SystemSettings</code> — leest env-vars zoals <code>PERF_LOG_ENABLED</code> / <code>CACHE_LOG_ENABLED</code> + persisted UI-settings.</li>
    </ul>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=new_tool">Een CMA-tool toevoegen</a>, <a href="documentation.php?topic=database">Database &amp; RecordSet</a>.
    </div>
    <?php
}

function render_doc_new_tool(): void
{
    ?>
    <h1>Een CMA-tool toevoegen</h1>
    <p class="docs-meta">Wat het skeleton is, hoe je 'm registreert, en welke gates de juiste zijn.</p>

    <h2>File-skeleton</h2>
    <p>Plaats het bestand in <code>cma/tools/&lt;name&gt;.php</code>. Minimaal:</p>
    <pre><code>&lt;?php
use App\Library\Request;
use App\Library\Response;
use Cma\SecurityHelper;
use Cma\ToolbarHelper;

require_once __DIR__ . '/../bootstrap.inc';

if (!SecurityHelper::isAdmin()) {        // of isDeveloper(), afhankelijk van scope
    echo '&lt;lib-message type="error"&gt;Geen toegang&lt;/lib-message&gt;';
    exit;
}

Response::noCache();

// POST-handlers HIER, vóór HTML output, zodat header()/Location kan vuren.
if (Request::post('action') === 'do_something') {
    // doe iets; sla resultaat op in een var voor de render
}

cma_html_header('CMA - &lt;Titel&gt;');
echo '&lt;body class="contentbody tools tool-&lt;naam&gt;"&gt;';
ToolbarHelper::start(true);
ToolbarHelper::title('&lt;Titel&gt;');
ToolbarHelper::separator();
ToolbarHelper::status('&lt;Korte beschrijving&gt;');
ToolbarHelper::end();
echo '&lt;div id="c" class="tools"&gt;';

// content rendering hier

echo '&lt;/div&gt;&lt;/body&gt;';
</code></pre>

    <h2>Klassen voor de chrome</h2>
    <ul>
        <li><span class="cma-tool__strong"><code>contentbody tools</code></span> op het body-element — gebruikt door de basis CSS voor padding, font, etc.</li>
        <li><span class="cma-tool__strong"><code>tool-&lt;name&gt;</code></span> als derde class — geeft je een prefix om tool-specifieke CSS scopen onder (zoals <code>.tool-llm .llm-grid { … }</code>).</li>
        <li><span class="cma-tool__strong"><code>&lt;div id="c" class="tools"&gt;</code></span> als content-wrapper — vereist door layout JS dat scroll-behavior, sticky elements en form-state hierin verwacht.</li>
    </ul>

    <h2>Toolbar helpers</h2>
    <p>Gebruik <code>Cma\ToolbarHelper</code> in plaats van zelf HTML te bouwen:</p>
    <pre><code>ToolbarHelper::start(true);
ToolbarHelper::title('Title');
ToolbarHelper::separator();
ToolbarHelper::status('Subtitle / status text');
ToolbarHelper::button('javascript:doSomething()', 'lnr-cog', true, 'Doe X', 'Tooltip');
ToolbarHelper::startRight();                 // rechter-uitlijning vanaf hier
ToolbarHelper::button('?reset=1', 'lnr-sync', true, 'Reset');
ToolbarHelper::end();
</code></pre>
    <p>Voor "report-style" pagina's met een title-block kun je <code>ToolbarHelper::report('Title', false, false, false, false, 'subtitle', $extraButtonHtml)</code> in één call doen.</p>

    <h2>Registreren in de tile-grid</h2>
    <p>Voeg een entry toe aan <code>buildToolsTreeData()</code> in <code>cma/tools.php</code>:</p>
    <pre><code>['type' =&gt; 'item', 'label' =&gt; 'Mijn Tool', 'href' =&gt; 'tools/my_tool.php',
 'target' =&gt; 'R', 'icon' =&gt; 'lnr-cog', 'badge' =&gt; 'A']
</code></pre>
    <p>Plaats in de juiste folder-array (<code>$standardFolder</code>, <code>$healthFolder</code>, <code>$dbFolder</code>, <code>$docsFolder</code>, <code>$devFolder</code>, etc.) zodat hij in de logische groep verschijnt.</p>

    <h2>Friendly URL alias</h2>
    <p>Optioneel: voeg een entry toe aan <code>$toolNameMap</code> bovenaan <code>cma/tools.php</code> om een korte URL te krijgen:</p>
    <pre><code>$toolNameMap = [
    // …
    'mijntool' =&gt; 'tools/my_tool.php',
];
</code></pre>
    <p>De URL <code>/cma/tools/mijntool</code> wordt dan door IIS URL Rewrite + de wrapper afgehandeld als <code>tools/my_tool.php</code>. Vereist dat de "CMA Tools Friendly URL" regel in de site-root web.config aanwezig is.</p>

    <h2>Welke access-gate?</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:200px">Wanneer</th><th>Gate</th></tr></thead>
        <tbody>
            <tr><td>Tool muteert site-data of triggert side-effects (deploy, migration, backup, cache clear).</td><td><code>SecurityHelper::isAdmin()</code></td></tr>
            <tr><td>Tool toont alleen platform-internals of code-reflectie (storybook, formulier-editor, env-switch, test-mail).</td><td><code>SecurityHelper::isDeveloper()</code></td></tr>
            <tr><td>Tool is een raw diagnose-endpoint die ook moet werken als CMA stuk is.</td><td><code>DEPLOY_SECRET</code> via <code>?key=</code> (zoals <code>diag.php</code>). Zelden de juiste keuze — eerst <code>isAdmin()</code> proberen.</td></tr>
        </tbody>
    </table>

    <h2>POST-Redirect-GET</h2>
    <p>Voor formulier-acties: handle de POST <span class="cma-tool__em">vóór</span> <code>cma_html_header</code> aangeroepen wordt. Bij succes <code>header('Location: ...')</code> + <code>exit</code>. Belangrijk: <code>file_put_contents</code>, <code>error_log</code>, <code>echo</code> in je handler-pad voorkomen het redirect (zie de logreader v1.10.1 fix waar een ontbrekende <code>@</code> de Location header brak en mobile Safari een download-prompt liet zien).</p>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=architecture">Architectuur</a>, <a href="documentation.php?topic=security">Beveiliging</a> (toegangsniveaus).
    </div>
    <?php
}

function render_doc_database(): void
{
    ?>
    <h1>Database &amp; RecordSet</h1>
    <p class="docs-meta">PDO-laag + ADO-cursor emulatie. Welke API voor welk patroon.</p>

    <h2>Connectie-namen</h2>
    <p>Databases zijn benoemd in <code>data/databases.json</code> (per-site overrides) of <code>cma/config/databases.json</code> (platform-defaults). Standaard-namen:</p>
    <ul>
        <li><code>data</code> — de primaire applicatie-database (default voor <code>Database::getConnection()</code>).</li>
        <li><code>platform</code> — alias / fallback voor <code>data</code> in migration-context.</li>
        <li><code>users</code> — CMA-gebruikers DB (typisch <code>CMAusers.mdb</code>).</li>
        <li><code>rep</code> — DEPRECATED repository DB (formulier-definities zijn naar JSON gemigreerd).</li>
    </ul>
    <p><code>Database::getConnection($name = 'data')</code> retourneert een <code>PDO</code> object. <code>$conn === null</code> betekent dat de connectie niet gemaakt kon worden — handle dat expliciet.</p>

    <h2>Prepared statements (voorkeur)</h2>
    <pre><code>use App\Library\Database;

// Single row
$row = Database::executeSingleRecord(
    'SELECT * FROM tblUsers WHERE userID = :id',
    ['id' =&gt; $userId]
);
// $row is een associative array of null.

// Multiple rows
$rows = Database::executeQuery(
    'SELECT * FROM tblOrders WHERE status = :status',
    ['status' =&gt; 'pending']
);
// $rows is een array van associative arrays.

// Mutation (geen result expected)
$rowsAffected = Database::execute(
    'UPDATE tblUsers SET lastLogin = NOW() WHERE userID = :id',
    ['id' =&gt; $userId]
);

// Single value
$count = Database::fetchOne(
    'SELECT COUNT(*) FROM tblOrders WHERE userID = :id',
    ['id' =&gt; $userId]
);
</code></pre>
    <p>Voor een specifieke connectie: <code>Database::query($sql, $params, $connectionName)</code>. <code>fetchOne</code> en <code>fetchAll</code> hebben ook een optionele <code>$connection</code> derde parameter.</p>

    <h2>RecordSet (ADO-emulatie)</h2>
    <p>Voor legacy-stijl iteratie (vooral handig in oude form-callbacks):</p>
    <pre><code>$rs = Database::openRS('SELECT * FROM tblUsers ORDER BY userName');
while (!$rs-&gt;EOF) {
    echo $rs-&gt;Fields['userName'];
    $rs-&gt;MoveNext();
}
$rs-&gt;Close();
</code></pre>
    <p><code>RecordSet</code> wraps een <code>PDOStatement</code> en implementeert <code>ArrayAccess</code> + <code>IteratorAggregate</code>, dus je kan ook gewoon <code>foreach</code> gebruiken:</p>
    <pre><code>foreach ($rs as $row) {
    echo $row['userName'];
}
</code></pre>
    <p>Andere methodes: <code>MoveNext</code>, <code>MoveFirst</code>, <code>MoveLast</code>, <code>MovePrevious</code>, <code>Close</code>, <code>isEOF</code>, <code>fetchAll</code>, <code>GetRows</code>, <code>fetchAssoc</code>, <code>fetch</code>. <code>__get</code> handelt de <code>EOF</code> en <code>Fields</code> properties af.</p>

    <h2>SQL-helpers (legacy)</h2>
    <p>Voor escaping in legacy code waar prepared statements niet handig zijn:</p>
    <pre><code>use App\Library\SQL;

$sql = "SELECT * FROM tblUsers WHERE userName = " . SQL::postString($_POST['username'])
     . " AND age &gt; " . SQL::postNumber($_POST['minAge']);
</code></pre>
    <p>Helpers per type: <code>postString</code>, <code>postNumber</code>, <code>postDate</code>, <code>postBool</code>. De "post" prefix komt uit het ASP-tijdperk ("form post variable"). <span class="cma-tool__strong">Nieuw werk gebruikt prepared statements</span>; SQL-helpers blijven voor de tienduizenden legacy-regels die er al zijn.</p>

    <h2>ODBC-modes</h2>
    <p>De Database-class ondersteunt twee ODBC-backends:</p>
    <ul>
        <li><span class="cma-tool__strong"><code>odbc</code></span> (default) — native PHP <code>odbc_*</code> functies. Stabiel voor MS Access.</li>
        <li><span class="cma-tool__strong"><code>pdo</code></span> — PDO ODBC. Modernere syntax, ondersteunt prepared statements direct.</li>
    </ul>
    <p>Schakelen met <code>Database::setOdbcMode('pdo')</code> tijdens bootstrap. Per-DB instelling via <code>databases.json</code>'s <code>odbcMode</code> property.</p>

    <h2>Connection-strings</h2>
    <p>Zoals gebruikt in <code>databases.json</code>:</p>
    <table class="listtable">
        <thead><tr class="listheader"><th>DB-type</th><th>Connection-string voorbeeld</th></tr></thead>
        <tbody>
            <tr><td>MS Access (Jet)</td><td><code>Provider=Microsoft.Jet.OLEDB.4.0;Locale Identifier=1043;Data Source=[db/mydata.mdb]</code></td></tr>
            <tr><td>MS Access ODBC</td><td><code>Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=[db/mydata.mdb]</code></td></tr>
            <tr><td>SQLite (PDO)</td><td><code>sqlite:[db/mydata.sqlite]</code></td></tr>
            <tr><td>MySQL (PDO)</td><td><code>mysql:host=localhost;dbname=mydata;charset=utf8mb4</code></td></tr>
            <tr><td>SQL Server</td><td><code>sqlsrv:Server=localhost;Database=mydata</code></td></tr>
        </tbody>
    </table>
    <p>Paden tussen <code>[...]</code> worden opgelost relatief aan de site-root. Auth-credentials komen meestal uit env-vars (<code>DB_USER</code>, <code>DB_PASS</code>) of uit <code>app.php</code>.</p>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=migrations">Migraties schrijven</a>, <a href="documentation.php?topic=architecture">Architectuur</a>.
    </div>
    <?php
}

function render_doc_migrations(): void
{
    ?>
    <h1>Migraties schrijven</h1>
    <p class="docs-meta">Hoe je een database-wijziging laat uitvoeren via <code>MigrationService</code>.</p>

    <h2>File-naming en plaatsing</h2>
    <ul>
        <li>Platform-migrations leven in <code>cma/migrations/</code>.</li>
        <li>Project-migrations leven in <code>migrations/</code> op de site-root.</li>
        <li>Bestandsnaam-conventie: <code>&lt;X.Y.Z&gt;_&lt;slug&gt;.php</code> — bijvoorbeeld <code>2.6.0_export_menu.php</code>.</li>
        <li>De versie in de bestandsnaam wordt vergeleken met de huidige tracking-versie in de DB. Alleen migrations met versie &gt; current draaien.</li>
    </ul>

    <h2>migrations.json</h2>
    <p>Naast het PHP-bestand registreer je elke migratie in <code>cma/config/migrations.json</code> (platform) of <code>config/migrations.json</code> (project). Voorbeeld-entry:</p>
    <pre><code>{
    "version": "2.6.0",
    "description": "Menu structuur naar JSON exporteren",
    "changes": [
        {
            "type": "runPhp",
            "script": "migrations/2.6.0_export_menu.php",
            "note": "Genereert config/menu.json uit tblMenu"
        }
    ]
}
</code></pre>
    <p>De <code>migrations</code> array moet op versie-volgorde gesorteerd zijn; <code>targetVersion</code> bovenaan zegt waar de current versie naartoe moet.</p>

    <h2>Change-types in <code>changes[]</code></h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:180px">Type</th><th>Doel</th></tr></thead>
        <tbody>
            <tr><td><code>createVersionTable</code></td><td>Eerste keer-setup van het tracking-table.</td></tr>
            <tr><td><code>addColumn</code> / <code>dropColumn</code></td><td>Schema-wijziging op een bestaande tabel.</td></tr>
            <tr><td><code>addIndex</code> / <code>dropIndex</code></td><td>Index management.</td></tr>
            <tr><td><code>renameTable</code> / <code>dropTable</code></td><td>Tabel-niveau structuur-wijziging.</td></tr>
            <tr><td><code>runSql</code></td><td>Voer een inline SQL-statement uit.</td></tr>
            <tr><td><code>runSqlScript</code></td><td>Voer een SQL-bestand uit.</td></tr>
            <tr><td><code>runPhp</code></td><td>Voer een PHP-script uit — meest flexibele optie voor data-migrations.</td></tr>
        </tbody>
    </table>

    <h2>Idempotent &amp; rerunnable</h2>
    <p>Een migratie kan opnieuw uitgevoerd worden via de "Rerun migration" knop op de Migraties-tool. Schrijf je migratie zó dat dat veilig is. Let op de exact-signatures: <code>Database::tableExists</code> neemt de connection-string eerst, <code>MigrationService::columnExists</code> neemt de tabel eerst:</p>
    <pre><code>// In je migration PHP:
$connString = 'data';   // of welke databases.json-entry je ook target

if (!Database::tableExists($connString, 'tblNewThing')) {
    Database::execute('CREATE TABLE tblNewThing (...)');
}

if (!MigrationService::columnExists('tblOrders', 'discountCode', $connString)) {
    Database::execute('ALTER TABLE tblOrders ADD COLUMN discountCode VARCHAR(50)');
}
</code></pre>
    <p>Voor PDO-handles in plaats van connection-strings: <code>Database::tableExistsPDO(PDO $conn, string $table)</code>.</p>
    <p>De if-check voorkomt dat een rerun een fout geeft op "tabel bestaat al" / "kolom bestaat al". Voor data-migrations: check eerst of de target-rows al de gewenste staat hebben en sla over.</p>

    <h2>MIGRATION_RUNNING constant</h2>
    <p>Sommige migration-scripts hebben volledige bootstrap niet nodig en willen 'm zelfs vermijden (b.v. omdat een migration een tabel wijzigt die bootstrap zelf leest). Definieer aan het begin:</p>
    <pre><code>define('MIGRATION_RUNNING', true);
</code></pre>
    <p>Bootstrap-paden die deze constant zien skippen niet-essentiële initialisatie.</p>

    <h2>Pre-migration backup</h2>
    <p>De Migraties-tool heeft een toggle "Auto-backup voor migratie". Aan: <code>BackupService::createMigrationBackup()</code> draait per affected database voordat de migratie start, met de migratie-versie als label. Uit: skip — zinvol voor data-only migrations die geen schema raken.</p>

    <h2>Multi-source migrations</h2>
    <p><code>MigrationService::getPendingMigrations()</code> walks BOTH sources: platform-migrations eerst (<code>cma/migrations/</code>), dan project-migrations (<code>migrations/</code>), dan eventuele module-migrations. Versie-volgorde is gegarandeerd per source maar source-volgorde wordt strikt aangehouden — dus een project-migration die hangt van een platform-tabel moet versie-genoeg hebben dat de platform-tabel zeker eerder gemaakt is.</p>

    <h2>Test &amp; deploy workflow</h2>
    <ol>
        <li>Schrijf de migratie + registreer in <code>migrations.json</code>.</li>
        <li>Run op je dev-omgeving via <a href="tools/tools_migrations.php" target="_top">Tools → Migraties</a>. Auto-backup aan voor dev-safety.</li>
        <li>Voer Rerun uit om idempotentie te valideren — moet "geen wijzigingen" rapporteren.</li>
        <li>Commit + push. Productie-deploy draait de migratie automatisch als <code>deploy_post.php</code> dat triggert; anders handmatig na de deploy via de Migraties-tool.</li>
    </ol>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=database">Database &amp; RecordSet</a>, <a href="documentation.php?topic=backups">Backups</a>, <a href="documentation.php?topic=deployment">Deployment</a> (deploy_post.php).
    </div>
    <?php
}

function render_doc_json_forms(): void
{
    ?>
    <h1>JSON-gedreven formulieren</h1>
    <p class="docs-meta">Hoe het formulier-systeem werkt: definitie → loader → renderer → form.php entry point.</p>

    <h2>Definitie-bestanden</h2>
    <p>Form-definities zijn JSON-bestanden die de oude MS Access repository hebben vervangen. Locaties:</p>
    <ul>
        <li><code>cma/assets/forms/definitions/&lt;form_name&gt;.json</code> — platform-bundled forms (CMA-internal: gebruikers, groepen, etc.). Worden door composer update overschreven.</li>
        <li><code>assets/forms/&lt;form_name&gt;.json</code> op site-root — project-eigen forms. Niet door Installer aangeraakt.</li>
    </ul>
    <p>Schema-validatie: <code>cma/config/schema/&lt;naam&gt;.schema.json</code> bevat de JSON Schema's. Editors lezen deze in voor IntelliSense.</p>

    <h2>JsonFormLoader</h2>
    <p>De loader leest het JSON-bestand, cached het in memory + op disk, en lost <code>includes</code> / inherited fields op. Belangrijkste API:</p>
    <pre><code>use Cma\Services\JsonFormLoader; // namespace, NIET autoloaded — require_once in bootstrap.inc

$def = JsonFormLoader::load('opleidingen');
//   Returns ?array (null als form niet bestaat). Hierarchical, met
//   inherited definities reeds gemerged.

$raw = JsonFormLoader::loadRaw('opleidingen');
//   Zelfde maar zonder inheritance — voor formulier-editor gebruik.

JsonFormLoader::exists('opleidingen');                    // bool
JsonFormLoader::listForms();                              // array van slugs
JsonFormLoader::getSubforms('opleidingen');               // array van subform-defs
JsonFormLoader::clearCache('opleidingen');                // invalidate cache
JsonFormLoader::setFileCacheEnabled(false);               // disable disk-cache
</code></pre>
    <p>Caching is automatisch on (in-memory per request + disk in <code>cache/forms/</code>). Editor-tools roepen <code>clearCache</code> aan na een save.</p>

    <h2>Definitie-schema basics</h2>
    <p>Een minimale form-definitie:</p>
    <pre><code>{
    "$schema": "../../../config/schema/form.schema.json",
    "name": "opleidingen",
    "title": "Opleidingen",
    "titleSingular": "Opleiding",
    "database": "data",
    "table": "tblOpleidingen",
    "primaryKey": "ID",
    "fields": [
        {"name": "ID",        "type": "autonumber", "primaryKey": true},
        {"name": "naam",      "type": "text",       "label": "Naam",      "required": true},
        {"name": "startDatum","type": "date",       "label": "Startdatum"},
        {"name": "actief",    "type": "switch",     "label": "Actief",    "default": true}
    ],
    "views": {
        "list": {"columns": ["naam", "startDatum", "actief"]},
        "detail": {"layout": "vertical"}
    }
}
</code></pre>
    <p>Field-types: <code>text</code>, <code>textarea</code>, <code>number</code>, <code>date</code>, <code>datetime</code>, <code>switch</code>, <code>combo</code> (dropdown), <code>radio-group</code>, <code>file</code>, <code>image</code>, <code>richtext</code>, <code>code</code>, <code>autonumber</code>, etc. De full lijst staat in <code>cma/config/control-types.json</code>.</p>

    <h2>Subforms</h2>
    <p>Een subform is een form-definitie waarvan records gekoppeld zijn aan een parent-record via een foreign key:</p>
    <pre><code>"subforms": [
    {
        "name": "opleiding_modules",
        "title": "Modules",
        "parentField": "opleidingID",
        "form": "modules"
    }
]
</code></pre>
    <p>De URL <code>form.php?form=opleidingen/ID/opleiding_modules</code> rendert dan de modules-list onder de opleiding-detail.</p>

    <h2>form.php entry point</h2>
    <p>Alle form-views lopen door <code>cma/form.php</code>. Belangrijke URL-parameters:</p>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:160px">Parameter</th><th>Effect</th></tr></thead>
        <tbody>
            <tr><td><code>form=&lt;name&gt;</code></td><td>Welk form laden (verplicht).</td></tr>
            <tr><td><code>ID=&lt;n&gt;</code></td><td>Detail-view voor record <code>n</code>.</td></tr>
            <tr><td><code>New=Y</code></td><td>Nieuw-record formulier.</td></tr>
            <tr><td><code>view=list</code> / <code>view=table</code> / <code>view=tree</code></td><td>Forceer een view-type voor lists.</td></tr>
            <tr><td><code>filter=&lt;json&gt;</code></td><td>Pre-applied filter op de list.</td></tr>
        </tbody>
    </table>

    <h2>extraButtons + placeholder substitution</h2>
    <p>Een form-definitie kan extra knoppen toevoegen aan het detail-view:</p>
    <pre><code>"extraButtons": [
    {
        "label": "Bekijk online",
        "icon": "lnr-eye",
        "url": "https://www.[domein]/r/[slug]"
    }
]
</code></pre>
    <p>Placeholders worden vervangen door waardes uit het huidige record. Het platform substitueert hardgecodeerd: <code>[id]</code>, <code>[guid]</code>, <code>[guid2]</code>, <code>[domein]</code>. Sinds v1.10.0 worden <span class="cma-tool__em">alle</span> overige <code>[fieldname]</code> placeholders ook geresolveerd door naar het form-veld met die naam te kijken — zo werkt <code>[slug]</code> automatisch als er een veld <code>slug</code> bestaat.</p>

    <h2>JsonFormRenderer</h2>
    <p>Server-side rendering gebeurt door <code>Cma\Services\JsonFormRenderer</code>. Die produceert de HTML; het form-controller.js framework in de browser handelt validatie, AJAX-save, subform navigation, etc. af. Custom render-overrides plaats je in <code>cma/classes/Services/</code> met eigen subclassen — zelden nodig, meestal volstaat een nieuwe field-type via <code>control-types.json</code>.</p>

    <h2>Form_api.php</h2>
    <p>AJAX-endpoint <code>cma/form_api.php</code> handelt alle save/load/list/delete operaties af. Common parameters:</p>
    <ul>
        <li><code>jsonForm=&lt;name&gt;</code> of <code>form=&lt;name&gt;</code> — het form (verplicht).</li>
        <li><code>action=&lt;actie&gt;</code> — operatie (load, save, list, delete, getOptions, etc.).</li>
    </ul>
    <p>Response is altijd JSON: <code>{"success": true|false, "error": "...", ...action-specific fields}</code>. In dev mode (omgeving ≠ P) bevat de response ook <code>_debugPath</code>, <code>_exception</code>, <code>_badFields</code> velden voor debugging.</p>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=architecture">Architectuur</a> (waar Services in <code>Cma\</code> namespace leven), <a href="documentation.php?topic=database">Database &amp; RecordSet</a>.
    </div>
    <?php
}

function render_doc_web_components(): void
{
    ?>
    <h1>Web components ontwikkelen</h1>
    <p class="docs-meta">Hoe je een <code>lib-*</code> of <code>cma-*</code> component schrijft, en wat de prefix-conventies betekenen.</p>

    <h2>Prefix-conventies</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:140px">Prefix</th><th>Locatie</th><th>Bedoeld voor</th></tr></thead>
        <tbody>
            <tr><td><code>lib-*</code></td><td><code>library/webcomponents/</code></td><td>Reusable building blocks zonder CMA-specifieke afhankelijkheden. Voorbeelden: lib-dialog, lib-combo, lib-datepicker, lib-sheet. Ook bruikbaar in front-end pages van consumer-sites.</td></tr>
            <tr><td><code>cma-*</code></td><td><code>cma/webcomponents/</code></td><td>CMA-specifieke componenten die afhankelijk zijn van CMA-context (tools, forms, layout). Voorbeelden: cma-tree, cma-toolbar, cma-tabs, cma-fold, cma-groupbox.</td></tr>
        </tbody>
    </table>

    <h2>Bestandsstructuur</h2>
    <p>Elk component is één JavaScript-bestand met de extensie <code>.js</code>, plus een meegegenereerd <code>.min.js</code> dat door de build-stap geproduceerd wordt. Het component-bestand bevat:</p>
    <pre><code>// Guard tegen dubbele declaratie wanneer het script meerdere keren wordt geladen.
if (!customElements.get('lib-mything')) {

class LibMything extends HTMLElement {
    static get observedAttributes() {
        return ['heading', 'open', 'closable'];
    }
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this.shadowRoot.innerHTML = this._template();
    }
    connectedCallback() { /* … */ }
    disconnectedCallback() { /* cleanup event listeners */ }
    attributeChangedCallback(name, oldValue, newValue) { /* react */ }
    _template() {
        return `&lt;style&gt;…&lt;/style&gt;&lt;div class="root"&gt;…&lt;/div&gt;`;
    }
}

customElements.define('lib-mything', LibMything);

}</code></pre>

    <h2>Shadow DOM</h2>
    <p>Use <code>mode: 'open'</code> zodat dev-tools de shadow root kunnen inspecteren. Style-encapsulation gebeurt automatisch — selectors in het shadow-template raken alleen het component.</p>
    <p>CSS Custom Properties (<code>--lib-sheet-bg</code>, etc.) gebruiken om consumers toe te staan kleuren / sizes te theming-en. Documenteer ze bovenaan het bestand als een `Theming` blok in JSDoc.</p>
    <p>Voor styling vanuit de host-pagina: <code>::part(...)</code> hooks. Markeer interne elementen met <code>part="…"</code> attributen:</p>
    <pre><code>&lt;div class="panel" part="panel"&gt;…&lt;/div&gt;
// Host page kan dan:
// lib-sheet::part(panel) { border-radius: 0; }</code></pre>

    <h2>Events</h2>
    <p>Dispatch custom events vanuit het component zodat de host code kan reageren:</p>
    <pre><code>this.dispatchEvent(new CustomEvent('sheet-open', { bubbles: true }));
this.dispatchEvent(new CustomEvent('change', { detail: { value: this.value }, bubbles: true }));
</code></pre>
    <p>Naam-conventie: korte, semantische names (<code>change</code>, <code>open</code>, <code>close</code>, <code>sheet-open</code>). Detail-object alleen wanneer waardes nuttig zijn.</p>

    <h2>Touch / pointer events</h2>
    <p>Gebruik Pointer Events (<code>pointerdown</code> / <code>pointermove</code> / <code>pointerup</code>) voor unified touch + mouse handling. Voor drag-gestures (zoals lib-sheet's drag-to-dismiss): <code>setPointerCapture</code> op het target zodat moves na de eerste frame nog steeds binnenkomen.</p>
    <p>Op het drag-target: <code>touch-action: none</code> in CSS zodat het browser-niveau scroll-gesture niet inschiet.</p>

    <h2>Minified counterpart</h2>
    <p>Iedere component heeft een <code>.min.js</code> sibling. Genereer met:</p>
    <pre><code>cd cma
npm run build:minify          # alleen minify
npm run build                 # icons + minify
</code></pre>
    <p>De build-stap slaat over wanneer <code>.min.js</code> nieuwer is dan de source. Wanneer terser een fout geeft, fix de source — minified output committeer je NIET als je deps niet hebt; CI of de pre-commit hook zou dat moeten regenereren.</p>

    <h2>Storybook-integratie</h2>
    <p>Elk nieuwe component hoort een entry in <code>cma/tools/storybook.php</code>. Sectie-structuur:</p>
    <ul>
        <li>Voeg een entry toe aan de <code>navData</code> array (binnen ofwel "Library componenten" of "CMA componenten").</li>
        <li>Voeg een <code>&lt;section class="component-section" id="lib-mything"&gt;</code> toe met een <code>&lt;textarea&gt;</code> playground en een <code>&lt;div class="component-options"&gt;</code> met DL-blocks voor attributen, methodes, properties, events.</li>
    </ul>
    <p>Bekijk een bestaand component-section voor het patroon (de file is groot, ~5000 regels — copy-paste een sectie en pas aan).</p>

    <h2>Icon-conventies (Linearicons)</h2>
    <p>Alle iconen komen uit de paid Linearicons font (<a href="https://linearicons.com" target="_blank" rel="noopener">linearicons.com</a>) — 1000+ glyphs. Gebruik via class:</p>
    <pre><code>&lt;span class="lnr lnr-home"&gt;&lt;/span&gt;
&lt;span class="lnr lnr-rocket"&gt;&lt;/span&gt;
&lt;span class="lnr lnr-bubble"&gt;&lt;/span&gt;
</code></pre>
    <p>Beschikbare icon-namen staan in <code>cma/docs/linearicons.css</code>. De Storybook-pagina (sectie "Linearicons") rendert de complete lijst zodat je visueel kan kiezen.</p>

    <h2>Menu-group iconen</h2>
    <p>Sidebar menu-groups krijgen automatisch een icon op basis van de slug. Het <code>$menuGroupIcons</code> array in <code>cma/main.php</code> mapt slug → lnr-class:</p>
    <pre><code>$menuGroupIcons = [
    'dashboard'    =&gt; 'lnr-home',
    'systeem'      =&gt; 'lnr-cog',
    'beheer'       =&gt; 'lnr-database',
    'content'      =&gt; 'lnr-file-add',
    'rapportages'  =&gt; 'lnr-chart-bars',
    'tools'        =&gt; 'lnr-construction',
    'formulieren'  =&gt; 'lnr-layers',
    'opleidingen'  =&gt; 'lnr-graduation-hat',
    // …
];
</code></pre>
    <p>Voeg een entry toe wanneer je een nieuwe menu-group introduceert. Voor menu-items zelf: zet <code>"icon": "lnr-..."</code> in <code>data/menu.json</code>.</p>

    <div class="seealso">
        Zie ook: <a href="storybook.php">Component Storybook</a> (levende voorbeelden), <a href="documentation.php?topic=architecture">Architectuur</a> (library/ vs cma/ scheiding).
    </div>
    <?php
}

function render_doc_errors(): void
{
    ?>
    <h1>Logging &amp; errors (dev)</h1>
    <p class="docs-meta">De ontwikkelaars-kant van logging — interna van LibLog en CmaErrorHandler.</p>

    <h2>Twee lagen</h2>
    <p>Het platform heeft een JavaScript-laag en een PHP-laag die samen werken:</p>
    <ul>
        <li><span class="cma-tool__strong">LibLog</span> (<code>library/webcomponents/lib-log.js</code>) — onderschept <code>console.*</code> calls, batched naar de server.</li>
        <li><span class="cma-tool__strong">CmaErrorHandler</span> (<code>cma/assets/js/error-handler.js</code>) — vangt <code>window.onerror</code> en <code>unhandledrejection</code>, toont visueel paneel in dev-mode, post naar <code>form_api.php?action=logJsError</code>.</li>
        <li><span class="cma-tool__strong">Logger</span> (<code>cma/classes/Services/Logger.php</code>) — server-side PSR-3 logger.</li>
        <li><span class="cma-tool__strong">PerformanceLogger</span> (<code>cma/classes/Services/PerformanceLogger.php</code>) — timing metrics.</li>
    </ul>
    <p>Voor de operator-kant (welke log-bron waar): zie <a href="documentation.php?topic=logs">Logs &amp; monitoring</a>. Hier focus op de code-API.</p>

    <h2>LibLog runtime config</h2>
    <pre><code>window.LIBLOG_CONFIG = {
    apiEndpoint:       '/cma/api/log.php',
    sendToServer:      true,
    batchSize:         10,
    flushInterval:     5000,
    interceptConsole:  true,
    minLevelForServer: 'error',       // alleen errors gaan naar server
    debugMode:         false          // override via cma_debug_mode cookie
};</code></pre>
    <p>De config wordt gelezen <span class="cma-tool__em">vóór</span> lib-log.js draait. Zet 'm in een inline script-tag boven de lib-log.js include als je defaults wilt overrulen.</p>

    <h2>Per-niveau gedrag</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th>Niveau</th><th>Console</th><th>Error panel</th><th>Naar server</th></tr></thead>
        <tbody>
            <tr><td><code>error</code></td><td>Altijd</td><td>Altijd</td><td>Altijd</td></tr>
            <tr><td><code>warning</code></td><td>Alleen debug=AAN</td><td>Alleen debug=AAN</td><td>Als minLevel ≤ warning</td></tr>
            <tr><td><code>info</code></td><td>Alleen debug=AAN</td><td>Nee</td><td>Als minLevel ≤ info</td></tr>
            <tr><td><code>debug</code></td><td>Alleen debug=AAN</td><td>Nee</td><td>Als minLevel ≤ debug</td></tr>
        </tbody>
    </table>

    <h2>CmaErrorHandler interna</h2>
    <ul>
        <li><span class="cma-tool__strong">Rate-limit client-side</span>: 5 errors per minuut in het visuele paneel. Verdere errors worden in een queue gehouden tot het minuut-venster opnieuw begint.</li>
        <li><span class="cma-tool__strong">Rate-limit server-side</span>: 100 errors per IP per uur in <code>tblCMAJavascriptErrors</code>. <code>form_api.php?action=logJsError</code> negeert verdere posts.</li>
        <li><span class="cma-tool__strong">Deduplicatie</span>: identieke errors binnen 60 seconden window worden niet opnieuw gepost. Bewust om error-storms op te vangen wanneer een onbedoelde loop oneindig dezelfde error gooit.</li>
        <li><span class="cma-tool__strong">window.CmaErrorHandler.report()</span> — interface die LibLog en custom code kunnen gebruiken om handmatig een error in het paneel te krijgen. Signature: <code>report(source, message, context)</code>.</li>
    </ul>

    <h2>Logger PHP API</h2>
    <pre><code>use Cma\Services\Logger;

Logger::debug('Probably wrong', ['ctx' =&gt; …]);
Logger::info('Happened', ['ctx' =&gt; …]);
Logger::notice('Significant', ['ctx' =&gt; …]);
Logger::warning('Probably wrong', ['ctx' =&gt; …]);
Logger::error('Wrong', ['ctx' =&gt; …]);
Logger::critical('Very wrong', ['ctx' =&gt; …]);
Logger::alert('Pager-worthy', ['ctx' =&gt; …]);
Logger::emergency('System down', ['ctx' =&gt; …]);

// Exception-shortcut:
Logger::exception($e, 'Wrapper message', ['ctx' =&gt; …]);
</code></pre>
    <p>ERROR, CRITICAL, ALERT, EMERGENCY → óók naar <code>error_log()</code>. Andere levels alleen naar het app-log-bestand.</p>

    <h2>Sensitive-data scrubbing</h2>
    <p>De Logger redacteert automatisch keys die matchen op <code>password</code>, <code>token</code>, <code>secret</code>, <code>api_key</code>, <code>credentials</code> in context-arrays, recursief. De redactie vervangt de waarde door <code>'[REDACTED]'</code> zodat je in het log nog ziet DAT er een password-key was, maar niet de waarde.</p>
    <p>Voor custom keys: pas <code>Logger::$sensitivePatterns</code> aan in <code>bootstrap.inc</code> als je <code>credit_card</code>, <code>ssn</code>, etc. ook wilt blokken.</p>

    <h2>PerformanceLogger</h2>
    <pre><code>use Cma\Services\PerformanceLogger;

// Timer
PerformanceLogger::startTimer('expensive_op');
doExpensiveOp();
$ms = PerformanceLogger::endTimer('expensive_op', ['rows' =&gt; $count]);

// Convenience-shortcuts
PerformanceLogger::logQuery($sql, $ms, ['table' =&gt; 'users']);
PerformanceLogger::logApi('endpoint_name', $ms, ['arg' =&gt; …]);
PerformanceLogger::logMemory('checkpoint');
</code></pre>
    <p>Wordt door <code>PERF_LOG_ENABLED=true</code> aangezet. Output naar <code>data/logs/perf/perf_YYYY-MM-DD.log</code> als JSON-per-regel (locatie gezet in <code>PerformanceLogger.php</code> line 75).</p>

    <h2>Best practices</h2>
    <ul>
        <li>Structured logging: <span class="cma-tool__strong">altijd</span> context-array meegeven. <code>Logger::error('Save failed')</code> alleen is onbruikbaar; <code>Logger::error('Save failed', ['formId' =&gt; …, 'error' =&gt; …])</code> wel.</li>
        <li>Errors die je catch't en niet opnieuw throw't moeten gelogd worden. Geen "silent catch" — zie <code>memory/feedback_defensive_checks.md</code>.</li>
        <li>JS-side: prefereer <code>LibLog.error(…)</code> boven raw <code>console.error</code> — context is rijker en de error landt in het visuele paneel.</li>
    </ul>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=logs">Logs &amp; monitoring</a> (operator-kant), <a href="documentation.php?topic=security">Beveiliging</a> (sensitive-data scrubbing context).
    </div>
    <?php
}

function render_doc_testing(): void
{
    // Live counts so the doc page never lies about scope.
    $unitTests   = glob(dirname(__DIR__) . '/tests/*Test.php') ?: [];
    $cypressSpecs = [];
    $cypressDir = dirname(__DIR__) . '/cypress/e2e';
    if (is_dir($cypressDir)) {
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cypressDir, \RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iter as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.cy.js')) {
                $cypressSpecs[] = $f->getPathname();
            }
        }
    }
    $platformClasses = count(glob(dirname(__DIR__, 2) . '/src/helpers/*.php') ?: []);
    $cmaClasses      = count(glob(dirname(__DIR__) . '/classes/*.php') ?: []);
    $cmaServices     = count(glob(dirname(__DIR__) . '/classes/Services/*.php') ?: []);
    $unitCount   = count($unitTests);
    $cypressCount = count($cypressSpecs);
    ?>
    <h1>Tests &amp; coverage strategie</h1>
    <p class="docs-meta">Waar het CMA-platform staat met geautomatiseerde tests, waar de gaten zitten, en welk volgende stuk werk de meeste regressie-risico afdekt per uur investering.</p>

    <h2>Huidige stand (live geteld)</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th>Test-laag</th><th>Aantal</th><th>Dekt</th></tr></thead>
        <tbody>
            <tr><td>PHP unit-tests (<code>cma/tests/*Test.php</code>)</td><td><?= $unitCount ?></td><td>Pure-logic helpers: <code>Arr</code>, <code>Date</code>, <code>Encryption</code>, <code>Html</code>, <code>Str</code>, <code>SQL</code>, <code>StringBuffer</code>, <code>FormExpressionEvaluator</code>, <code>QueryBuilder</code>, <code>SqlParser</code>, <code>ColumnMajorArray</code>, <code>EmailLog</code></td></tr>
            <tr><td>Cypress E2E-specs (<code>cma/cypress/e2e/**/*.cy.js</code>)</td><td><?= $cypressCount ?></td><td>UI-flows: forms, components, auth, navigation, tools, wizards, search, reports, integration, performance, accessibility, responsive, visual, email-log, readonly-forms</td></tr>
        </tbody>
    </table>
    <p class="docs-meta">Productie-PHP-classes ter referentie: <code>src/helpers/</code> = <?= $platformClasses ?>, <code>cma/classes/</code> = <?= $cmaClasses ?>, <code>cma/classes/Services/</code> = <?= $cmaServices ?>. De unit-tests dekken <strong class="cma-tool__strong">geen</strong> van de service-classes (RecordService, FormDataProvider, ListService, MigrationService) of de data-laag (<code>Database</code>, <code>RecordSet</code>). Daar zit de risico-zone.</p>

    <h2>Risico-zones (waar regressies wegglippen)</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th>Zone</th><th>Wat misgaat zonder dekking</th><th>Huidige observatie</th></tr></thead>
        <tbody>
            <tr><td><code>Database</code> + <code>RecordSet</code></td><td>PDOException paths retourneren <code>null</code>/<code>[]</code>; veranderde SQL-quoting; ODBC ↔ SQLite ↔ MySQL edge cases.</td><td>Sinds v1.19.8 worden errors ge-logged, maar geen test bewijst dat de catches gaat-niet-stuk-paden hetzelfde gedragen na refactor.</td></tr>
            <tr><td><code>FormDataProvider::saveJsonFormRecord</code></td><td>Add/Edit/Delete branches; custom-renderer save; veld-validatie; monitoring/changelog.</td><td>Sinds v1.20.1 ook server-side changelog fallback — geen test die de oud↔nieuw diff bewijst.</td></tr>
            <tr><td><code>Services\RecordService</code> + <code>ListService</code></td><td>Subform piggyback, group-rights save, custom renderer waarde-collectie.</td><td>Cypress dekt de happy-path UI; de service-laag heeft geen geïsoleerde test.</td></tr>
            <tr><td><code>MigrationService</code></td><td>Migratie-volgorde, rerunbaarheid, rollback-gedrag bij partial failure.</td><td>Geen test. Wel changelog in <code>migrations.json</code> maar dat is geen contract.</td></tr>
            <tr><td>Web components met state (cma-blockeditor, cma-tree, lib-fileuploader)</td><td>Attribute-change handlers, JSON.parse fallbacks, drag-and-drop volgorde, fetch-failure UX.</td><td>Storybook-aanwezigheid garandeert syntax, geen gedrag. Eén Cypress-spec voor lib-sheet sinds v1.19, rest niet.</td></tr>
            <tr><td><code>Installer</code></td><td>File-sync van platform naar consumer-site; <code>REMOVED_PATHS</code> opschoning; protected-paths bewaring.</td><td>Sinds v1.19.7 throwen op copy/mkdir failure, maar geen test bewijst dat de juiste files bewaard blijven.</td></tr>
        </tbody>
    </table>

    <h2>Drie-laags aanpak</h2>
    <p>Niet alle code-laag verdient dezelfde test-stijl. De juiste keuze per laag:</p>
    <ol>
        <li><span class="cma-tool__strong">Pure-logic units</span> — al goed gedekt (12 testklassen). Vuistregel: <em>elke nieuwe pure functie in <code>src/helpers/</code> krijgt een <code>*Test.php</code></em>. Geen DB, geen filesystem, runt in &lt; 1s totaal.</li>
        <li><span class="cma-tool__strong">Pure-data service-tests</span> — methodes die hun input als array binnen krijgen en hun output als array/string terug geven (zoals <code>FormDataProvider::buildEditChangelog</code>, <code>FormDataProvider::buildDeleteChangelog</code>, <code>QueryBuilder</code>, <code>SqlParser</code>) test je <span class="cma-tool__em">zonder</span> connectie. Geen mock-DB nodig — feed de arrays in, vergelijk de output. Dit zou je voor de v1.20.1 changelog-fix direct kunnen testen.</li>
        <li><span class="cma-tool__strong">Connection-gebonden service-tests</span> — voor methodes die wél een PDO/RecordSet aanraken (<code>RecordService::save</code>, <code>MigrationService::run</code>) is een echte ODBC-Access verbinding nodig; SQLite zou een ander dialect testen dan productie en is voor form-data niet representatief (alleen <code>cmausers.sqlite</code> draait SQLite). Twee opties: (a) een <em>fixtures-mdb</em> aanpak — een kale <code>.mdb</code> met minimale schema-tabellen die de testrunner kopieert per test, of (b) een <em>PDO-stub</em> waarbij de connectie een in-memory key-value mock is die alleen de queries en parameters opvangt. (b) is sneller op te zetten maar dekt geen ODBC-specifiek gedrag.</li>
        <li><span class="cma-tool__strong">Cypress E2E</span> — al sterk in UI-flows (109 specs). Toevoegen alleen voor regressie-incident-pairs die niet op service-laag te isoleren zijn (multi-form-flows, popup-close-then-reopen edge cases) of waar het écht eind-tot-eind moet draaien tegen een echte CMA-DB.</li>
    </ol>

    <h2>Quick-win plan (sprint-1 status)</h2>
    <p>Eén PR die het meeste regressie-risico per uur afdekt — start bij wat <span class="cma-tool__em">geen</span> DB-harness nodig heeft:</p>
    <ol>
        <li><span class="cma-tool__strong">Pure-data tests die nu direct kunnen</span> (geen connection vereist):
            <ul>
                <li><lib-label type="success">v1.20.3</lib-label> <code>FormDataProviderChangelogTest</code> — 17 tests dekken <code>buildEditChangelog</code> incl. no-change paths, field-filtering, value-rendering, regression-guards. Pure-data, reflectie voor private method.</li>
                <li><lib-label type="success">v1.20.4</lib-label> <code>FormDataProviderDeleteChangelogTest</code> — 14 tests voor <code>buildDeleteChangelog</code>; ook dekt boolean/array/null-rendering en de twee-koloms structuur.</li>
                <li><lib-label type="success">v1.20.4</lib-label> <code>InstallerRemovedPathsTest</code> — 7 tests met tmp-dir filesystem fixtures, REMOVED_PATHS via reflectie zodat nieuwe retirements automatisch meedoen.</li>
            </ul>
        </li>
        <li><lib-label type="success">v1.20.4</lib-label> <span class="cma-tool__strong">PDO-stub harness</span> in <code>cma/tests/StubConnection.php</code> + <code>StubConnectionTest.php</code> (12 tests). Extends <code>\PDO</code> met <code>newInstanceWithoutConstructor</code>-trick zodat <code>instanceof PDO</code> blijft passen ook zonder pdo_sqlite-driver in CI. Queue van vooraf-gedefinieerde results, recordt alle SQL + params zodat tests kunnen asserten "exact deze UPDATE met deze parameters". Niet representatief voor ODBC-dialect-issues, wel voor query-shape regressies. Klaar voor gebruik in RecordService / saveJsonFormRecord tests.</li>
        <li><span class="cma-tool__strong">Voor ODBC-specifiek</span> (dialect-quirks, identifier-quoting, fetch-encoding): een gedeelde <code>tests/fixtures/blank.mdb</code> die per test wordt gekopieerd naar tmp en daar weer wordt opgeruimd. Dat is een tweede sprint; eerste prioriteit zijn de pure-data tests.</li>
        <li><lib-label type="success">v1.20.5</lib-label> <span class="cma-tool__strong">CI-gate in deploy-webhook</span>: <code>DEPLOY_RUN_TESTS</code> env-var draait een commando NA <code>composer update</code> en VÓÓR recycle. Non-zero exit → deploy FAILED, recycle + post-hook overgeslagen, productie blijft op oude code via cached opcache. Operator ziet <code>status: FAILED</code> in <code>deploy_status.php</code>. Default leeg (= geen gate); opt-in per site door <code>DEPLOY_RUN_TESTS=php cma/tests/TestRunner.php</code> in <code>.env</code>.</li>
    </ol>
    <p class="docs-meta">Status na v1.20.5: <strong class="cma-tool__strong">392/392 tests groen</strong> (18 testklassen, +7 cases sinds 1.20.4 in <code>DatabaseErrorPathTest</code> die v1.19.8 always-log expliciet bewijst). PDO-stub harness compleet met throw-mode. Volgende sprint: RecordService::save en saveJsonFormRecord contract-tests die de query-shape valideren.</p>

    <h2>Coverage-doel</h2>
    <p>Geen absoluut percentage nastreven — een 80%-target dat in 80% van de niet-belangrijke loops zit is misleidend. Wel <span class="cma-tool__em">gedrags-doelen</span>:</p>
    <ul>
        <li>Iedere methode in <code>src/helpers/</code> heeft minstens 1 test op happy-path + 1 op edge case (null, leeg, max-grootte).</li>
        <li>Iedere methode in <code>cma/classes/Services/</code> heeft minstens 1 integration-test per public contract.</li>
        <li>Iedere web component in <code>library/webcomponents/</code> heeft een Cypress-spec die <em>connectedCallback → attribute change → user event → expected state</em> doorloopt (lib-sheet is de blueprint).</li>
        <li>Geen merge naar main zonder dat <code>composer test</code> groen is — gedwongen via deploy webhook.</li>
    </ul>

    <h2>Hoe tests draaien</h2>
    <pre><code>cd cma
php tests/TestRunner.php                        # alle PHP unit-tests
php tests/TestRunner.php ArrTest                # één klasse
php tests/TestRunner.php ArrTest --filter=testFlatten   # één methode

npx cypress open                                # interactief
npx cypress run                                 # headless (CI)
npx cypress run --spec 'cypress/e2e/forms/**/*.cy.js'  # selectief
</code></pre>
    <p>De PHP-runner is custom (<code>cma/tests/TestRunner.php</code>) en heeft géén PHPUnit-dependency — bewust kept zo zodat consumer-sites niets extra hoeven te installeren. Wel ondersteunt de TestCase-base PHPUnit-compatible asserties zodat eventueel naar PHPUnit migreren een grep-and-replace is.</p>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=architecture">Architectuur</a> (welke laag wat doet), <a href="documentation.php?topic=releasing">Releasen &amp; versies</a> (semver + tagging), <a href="documentation.php?topic=errors">Logging &amp; errors</a> (waar regressies opduiken na deploy).
    </div>
    <?php
}

function render_doc_releasing(): void
{
    ?>
    <h1>Releasen &amp; versies</h1>
    <p class="docs-meta">Hoe een nieuwe versie van het platform gepublic'd wordt en wat semver hier betekent.</p>

    <h2>De release-stappen</h2>
    <ol>
        <li>Bump <code>composer.json</code>'s <code>version</code> field volgens semver.</li>
        <li>Commit met een release-message: <code>Release X.Y.Z: &lt;wat veranderde&gt;</code>.</li>
        <li>Tag de commit: <code>git tag vX.Y.Z</code>.</li>
        <li>Push commits én tags: <code>git push &amp;&amp; git push --tags</code>.</li>
        <li>Consumer-sites pullen de nieuwe versie via <code>composer update stenversonline/platform</code>. Hun deploy-webhook (sinds v1.13.0) doet dit automatisch.</li>
    </ol>

    <h2>Semver-richtlijn</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:120px">Bump</th><th>Wanneer</th></tr></thead>
        <tbody>
            <tr><td><span class="cma-tool__strong">PATCH</span> (x.y.Z)</td><td>Alleen bugfixes; geen gedragsverandering voor callers.</td></tr>
            <tr><td><span class="cma-tool__strong">MINOR</span> (x.Y.0)</td><td>Nieuwe features, interne uitbreidingen, removal van internal-only surfaces.</td></tr>
            <tr><td><span class="cma-tool__strong">MAJOR</span> (X.0.0)</td><td>Breaking changes voor consumer-code (Composer-installed classes, web components, JSON form contracts).</td></tr>
        </tbody>
    </table>

    <h2>Waar de versie geleest wordt</h2>
    <p><code>App\Library\Bootstrap::getPlatformVersion()</code> lost de versie op in deze volgorde (sinds v1.9.1):</p>
    <ol>
        <li><code>vendor/stenversonline/platform/composer.json</code>'s <code>version</code> field — bron-van-waarheid; werkt ook als de consumer's composer.json een branch-constraint (<code>dev-main</code>) gebruikt.</li>
        <li><code>vendor/composer/installed.json</code> — wat Composer registreerde tijdens install.</li>
        <li><code>Composer\InstalledVersions::getPrettyVersion()</code> — runtime API.</li>
        <li>Fallback: <code>'dev'</code>.</li>
    </ol>
    <p><code>CMA_APP_VERSION</code> constant wordt in <code>cma/bootstrap.inc</code> gezet vanuit deze functie. Zichtbaar in het profielmenu (sinds v1.9.0).</p>

    <div class="docs-callout docs-callout--warn">
        <span class="cma-tool__strong">vdev-main symptoom:</span> stap 1 ontbreekt of mislukt → installed.json valt door, dat zegt <code>dev-main</code> bij branch-installs. Fix sinds v1.9.1: lees <span class="cma-tool__em">eerst</span> de package's eigen composer.json — die heeft altijd de tagged versie.
    </div>

    <h2>REMOVED_PATHS voor retired bestanden</h2>
    <p>Wanneer je een bestand verwijdert uit <code>library/</code>, <code>cma/</code> of <code>module/</code>: voeg het ook toe aan <code>REMOVED_PATHS</code> in <code>src/Installer.php</code>. De Installer's syncDirectory kopieert alleen forward — zonder REMOVED_PATHS blijft het oude bestand voor altijd op consumer-sites bestaan na een upgrade.</p>
    <pre><code>private const REMOVED_PATHS = [
    'cma/tools/llm_models.php',     // retired v1.9.0
    'cma/docs/iis-setup.md',        // retired v1.16.0 (inlined in docs)
    'cma/docs/logging.md',          // retired v1.16.0
    // …
];</code></pre>
    <p>Entries kunnen voor altijd blijven staan — <code>is_file()</code> guard zorgt dat de cleanup idempotent is.</p>

    <h2>Automatisch melden in de response</h2>
    <p>Per CLAUDE.md / memory <code>feedback_version_bump</code>: na elke push noem ik de nieuwe versie expliciet in de user-facing message. Zonder dat moeten consumer-operators in de profiel-balk kijken om te weten welke versie ze net binnen krijgen.</p>

    <h2>De afgeronde release-bash macro</h2>
    <p>Voor commit + tag + push in één:</p>
    <pre><code>VERSION=X.Y.Z
git add composer.json &lt;files&gt; &amp;&amp; \
  git commit -m "Release $VERSION: &lt;short description&gt;" &amp;&amp; \
  git tag v$VERSION &amp;&amp; \
  git push &amp;&amp; git push --tags
</code></pre>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=deployment">Deployment</a> (consumer-side composer update), <a href="documentation.php?topic=installation">Installatie</a>.
    </div>
    <?php
}

// -------------------------------------------------------------------------
// TROUBLESHOOTING & REFERENTIE
// -------------------------------------------------------------------------

function render_doc_troubleshooting(): void
{
    ?>
    <h1>Troubleshooting</h1>
    <p class="docs-meta">Catalog van bekende symptomen, hun root cause, en de versie waarin ze gefixed zijn. Vóórdat je hier zoekt: check de juiste topic-specifieke troubleshooting-sectie eerst.</p>

    <h2>Versies, vendor &amp; install</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak</th><th>Fix</th></tr></thead>
        <tbody>
            <tr><td><code>vdev-main</code> in profielmenu i.p.v. echte versie</td><td>vendor/ stale t.o.v. cma/ files; Bootstrap::getPlatformVersion() pre-v1.9.1 las eerst installed.json, die zegt <code>dev-main</code> bij branch-installs.</td><td><code>composer update stenversonline/platform</code>. v1.9.1+ leest eerst de package's eigen composer.json — daarna verschijnt vanzelf <code>v&lt;X.Y.Z&gt;</code>.</td></tr>
            <tr><td>Fatal: <code>Class "App\Library\Email" not found</code></td><td>Zelfde oorzaak — vendor refresh ontbrak, Email.php nog niet autoloadable maar bootstrap.inc raakt 'm aan.</td><td>v1.12.1+ heeft <code>class_exists</code> guard zodat CMA niet meer crash't; voor permanent: composer update.</td></tr>
            <tr><td>Composer update draait, maar cma/ files niet ververst</td><td><code>composer.json</code> mist de <code>post-install-cmd</code> / <code>post-update-cmd</code> scripts die <code>App\Library\Installer</code> aanroepen.</td><td>Scripts sectie toevoegen aan consumer's composer.json — zie <a href="documentation.php?topic=installation">Installatie</a>.</td></tr>
            <tr><td>Retired bestand blijft op consumer-site na upgrade</td><td>Installer's syncDirectory kopieert alleen forward; verwijderde bestanden propageren niet.</td><td>Voeg het pad toe aan <code>REMOVED_PATHS</code> in <code>src/Installer.php</code>. Bij volgende composer update opgeruimd. Zie <a href="documentation.php?topic=releasing">Releasen &amp; versies</a>.</td></tr>
        </tbody>
    </table>

    <h2>IIS &amp; URL Rewrite</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak</th><th>Fix</th></tr></thead>
        <tbody>
            <tr><td><code>/cma/dashboard</code> → 404, maar <code>/cma/dashboard.php</code> wel 200</td><td>Parent web.config vangt <code>/cma/*</code> op vóór het kind <code>cma/web.config</code>.</td><td>Voeg "Skip /cma to child config" regel bovenaan parent <code>&lt;rules&gt;</code> toe — sinds v1.14.2 standaard in <code>templates/web.config.template</code>. Zie <a href="documentation.php?topic=iis_config">IIS-configuratie</a>.</td></tr>
            <tr><td>500 op alle <code>/cma/*</code> requests, parent IIS-error over locked config-sectie</td><td>Allowed server variables nog niet ontgrendeld op server-niveau.</td><td><code>appcmd unlock</code> — zie <a href="documentation.php?topic=installation">Installatie</a>.</td></tr>
            <tr><td>Friendly URL <code>/cma/tools/&lt;naam&gt;</code> → 404 maar <code>?tool=&lt;naam&gt;</code> werkt</td><td>"CMA Tools Friendly URL" regel ontbreekt in site-root web.config, of URL Rewrite Module niet geïnstalleerd.</td><td>Module installeren via iis.net; regel kopiëren uit een werkende consumer-site.</td></tr>
            <tr><td>iOS Safari prompt "Download logreader.php?" bij Log leegmaken</td><td><code>file_put_contents()</code> in delete-handler emitterde PHP-warning, polluatie van response-buffer brak de Location-redirect. Browser kreeg 200 OK met warning-tekst, geen Content-Type → download.</td><td>Gefixed in v1.10.1 met <code>@</code>-suppress op de truncate-call.</td></tr>
        </tbody>
    </table>

    <h2>Omgeving / .env</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak</th><th>Fix</th></tr></thead>
        <tbody>
            <tr><td>Env-switch knop drukt, refresh, niks gewijzigd</td><td>v1.13.0 schreef altijd naar hardcoded <code>.env</code>, ook als bootstrap <code>.env.test</code> of <code>.env.production</code> had geladen. Regel landde in het verkeerde bestand.</td><td>v1.14.3+ schrijft naar het ACTIEVE env-bestand (zichtbaar als "Actief .env bestand" op de Omgeving-tab).</td></tr>
            <tr><td>"Schrijven naar .env mislukt" foutmelding</td><td>IIS-user heeft geen schrijfrechten op het env-bestand.</td><td>NTFS ACL aanpassen voor <code>IIS APPPOOL\&lt;sitename&gt;</code>.</td></tr>
            <tr><td>APP_ENVIRONMENT staat goed in .env, maar omgeving-code blijft P</td><td>OS-level <code>APP_ENVIRONMENT</code> (in IIS app-pool environment variables) overrulet de file-content tijdens <code>Bootstrap::detectAndLoadEnv</code>.</td><td>Verwijder de OS-level setting in IIS Manager → Application Pools → Advanced Settings → Environment Variables.</td></tr>
        </tbody>
    </table>

    <h2>Deployment &amp; webhook</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak</th><th>Fix</th></tr></thead>
        <tbody>
            <tr><td>GitHub-webhook delivery faalt met 401</td><td><code>DEPLOY_SECRET</code> mismatch tussen server en GitHub.</td><td>Roteer beide tegelijk; valideer met curl tegen het endpoint.</td></tr>
            <tr><td>202 Accepted maar er gebeurt niks zichtbaars</td><td>Pipeline-fout post-acceptatie.</td><td>Tail <code>logs/deploy.log</code> — failed step staat onder <code>RUN: ...</code> / <code>EXIT: 1</code>.</td></tr>
            <tr><td>"composer update failed" WARN in deploy-log</td><td>Composer niet in PATH van de IIS-user.</td><td>Path toevoegen of composer.phar absoluut pad gebruiken in <code>DEPLOY_COMPOSER_UPDATE</code> — beide niet automatisch.</td></tr>
            <tr><td>Migration-banner verschijnt niet ondanks pending migrations</td><td>Tot v1.10.4 werd de bootstrap-side migration-check fout silent ingeslikt.</td><td>v1.10.4+ surface't de exception als rode banner + <code>error_log</code>.</td></tr>
        </tbody>
    </table>

    <h2>LLM &amp; LLM-status</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak</th><th>Fix</th></tr></thead>
        <tbody>
            <tr><td>"Probe-fout: Connection timed out…" op een engine die je niet gebruikt</td><td>Tot v1.10.2 toonde tools_llm.php de raw probe-error op elke kaart, ook engines die niet geïnstalleerd zijn.</td><td>v1.10.2+ onderdrukt de regel voor niet-in-use engines en gebruikt "niet geïnstalleerd" badge in neutrale styling.</td></tr>
            <tr><td>"Modellen installeren" knop deed niks</td><td>llm_models.php's install-knop liep tegen permissions/PowerShell-paden aan.</td><td>v1.13.0 retirde de page; aanbevolen modellen leven nu inline op de Ollama-kaart met <code>ollama pull &lt;tag&gt;</code> copy-paste.</td></tr>
            <tr><td>Recipe-parser specifieke teksten op een niet-recipe site</td><td>llm_analyse.php had hardgecodeerde "receptenparser" wording uit mijntoprecepten-context.</td><td>v1.14.1 generieked naar "LLM-pipeline" / "Anthropic-fallback".</td></tr>
        </tbody>
    </table>

    <h2>Logging / log-reader</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak</th><th>Fix</th></tr></thead>
        <tbody>
            <tr><td>Logreader → Performance: "Geen log entries gevonden", maar er IS verkeer.</td><td>logreader leest <code>cache/perf_logs/perf_*.log</code>; <code>PerformanceLogger</code> schrijft naar <code>data/logs/perf/perf_*.log</code>. De UI kijkt in de verkeerde map.</td><td>Bekijk de files direct op disk in <code>data/logs/perf/</code> totdat een release de paden synchroniseert. Open issue.</td></tr>
            <tr><td>Logreader → Debug: "Geen log entries gevonden", LibLog console-logging staat aan.</td><td>Zelfde patroon: logreader leest <code>cma/logs/debug_*.log</code>; <code>cma/api/log.php</code> schrijft naar <code>data/logs/debug_*.log</code>.</td><td>Bekijk de files in <code>data/logs/</code>. Same fix.</td></tr>
            <tr><td>Logreader → Application: log-bron ontbreekt in de dropdown.</td><td>De logreader-config heeft geen 'app' source-entry — Logger's <code>data/logs/app_*.log</code> wordt nergens via de UI ontsloten.</td><td>Open de bestanden direct, of voeg een source-entry toe aan <code>logreader.php</code>.</td></tr>
        </tbody>
    </table>

    <h2>Web components</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak</th><th>Fix</th></tr></thead>
        <tbody>
            <tr><td>lib-sheet animeert niet bij open</td><td>CSS-transitions kunnen door browsers worden overgeslagen als de closed-state niet eerst gecommit is (first-open na attach, of na een onderbroken drag).</td><td>v1.11.4+ gebruikt CSS <code>@keyframes</code> i.p.v. transition — keyframes hebben dit probleem niet.</td></tr>
            <tr><td>lib-sheet grab-bar niet draggable op mobiel</td><td>Hit-area van ~12px is te klein voor touch.</td><td>v1.11.3+ bindt drag óók op het hele <code>.header</code>-element (~50px); v1.11.4+ heeft de bar zelf ook gepromoot naar ~28px hit-area.</td></tr>
            <tr><td>Knop-klik geeft geen visuele feedback</td><td>CSS <code>:active</code> styles bestaan wel, maar een korte klik laat ze nooit lang genoeg zien.</td><td>v1.11.0+ heeft <code>.btn--clicked</code> animatie die door een document-level click handler 220ms wordt aangezet.</td></tr>
        </tbody>
    </table>

    <h2>Content blocks (blockedit)</h2>
    <p class="docs-meta">Het content-block veld (<code>&lt;div class="blockedit"&gt;</code> rond een <code>data-allow-html</code> textarea, aangestuurd door <code>cma/assets/js/blockedit.js</code>) rendert per blok een CKEditor. Hetzelfde veld is tegelijk een CKEditor-instance én de serialisatie-sink — die dubbele eigenaarschap is de bron van de meeste content-verlies-symptomen.</p>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak</th><th>Fix</th></tr></thead>
        <tbody>
            <tr><td>Blok verslepen (drag-drop) maakt het rich-text veld leeg</td><td><code>blockedit_init_dragdrop</code> zocht bij dragstart <code>CKEDITOR.instances["cke_&lt;naam&gt;"]</code> (de id van de <code>.cke</code> container i.p.v. de textarea-id), dus <code>updateElement()</code> gooide een (ingeslikte) fout en de editor-inhoud werd nooit naar de textarea geflusht. De DOM-move blankt vervolgens de iframe-editor en dragend's <code>destroy()</code> (zonder arg → roept updateElement) schreef die lege inhoud terug.</td><td>v1.20.18: dragstart stript de <code>cke_</code>-prefix zodat de flush wél draait, en dragend gebruikt <code>destroy(true)</code> (noUpdate) zodat de geblankte editor de zojuist geflushte textarea niet overschrijft. De up/down-knoppen (<code>blockedit_save_ckeditor_states</code>) waren al correct.</td></tr>
            <tr><td>Nieuw blok toevoegen: de CKEditor verschijnt niet</td><td>De editor van een nieuw blok wordt uitgesteld in <code>pendingCKEditors</code> zolang het accordeon nog niet <code>.opened</code> is, en pas aangemaakt via <code>blockedit_process_pending_ckeditors</code> (150ms setTimeout). Die queue maakt zichzelf synchroon leeg en vult 150ms later weer aan, waardoor een net-geopende editor uit de handshake kon vallen.</td><td>v1.20.18: <code>blockedit_click</code> maakt de editor(s) van het zojuist geopende blok direct aan (idempotent via de <code>'exists'</code>-check in <code>blockedit_createCKEditor</code>), naast de bestaande queue.</td></tr>
            <tr><td>Content-block veld wordt leeg opgeslagen / edits verdwijnen</td><td><code>blockedit_collect_htmls</code> schrijft het veld alleen als de serialisatie niet leeg is (<code>cTotalHTML != ""</code>) en doet niets als <code>contentblocks.json</code> nog niet geladen is. Na een record-switch / <code>clearForm</code> (<code>setData('')</code> + <code>blockedit_clear</code>) zonder dat de blokken herbouwd zijn, produceert collect <code>""</code>, slaat de schrijfactie over en wordt een lege waarde opgeslagen.</td><td>v1.20.19: <code>blockedit_collect_htmls</code> houdt per veld een laatst-bekende-goede snapshot bij (<code>blockedit_lastGood</code>, getagd met het record-id). Produceert collect leeg terwijl er géén blokken gerenderd zijn (<code>.blockedit_block</code> count 0 — gewist/nog niet herbouwd) én het veld leeg is, dan herstelt het de snapshot i.p.v. leeg op te slaan. De record-id-guard zorgt dat een nieuw record (id <code>null</code>) nooit de inhoud van een vorig record erft. (Bewust niet "harvesten in <code>blockedit_clear</code>": <code>newRecord()</code> roept <code>clearForm()</code> zonder <code>populateForm</code>, dus harvesten zou oude inhoud naar het nieuwe record lekken.) De gated <code>[BlockEdit][LOSS-RISK]</code> tripwire-logging (sinds v1.20.18) blijft en meldt nu "prevented empty save".</td></tr>
        </tbody>
    </table>

    <h2>File-browser wizard</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak</th><th>Fix</th></tr></thead>
        <tbody>
            <tr><td>Upload met zelfde extensie als geselecteerd bestand vraagt niet "overschrijven?"</td><td>Feature niet geïmplementeerd tot v1.10.0.</td><td>v1.10.0+ vraagt "Ja, overschrijf bestand" / "Nee, plaats ernaast" wanneer een single-file upload dezelfde extensie heeft.</td></tr>
        </tbody>
    </table>

    <div class="seealso">
        Zie ook: alle topics hebben eigen "Troubleshooting" subsecties voor onderwerp-specifieke issues. Deze pagina is de cross-cutting catalog.
    </div>
    <?php
}

function render_doc_mail(): void
{
    ?>
    <h1>Mail-configuratie</h1>
    <p class="docs-meta">Hoe de Email-helper SMTP gebruikt, en welke environment/Application keys hij leest.</p>

    <h2>Application-keys</h2>
    <p><code>App\Library\Email</code> leest in zijn <code>initialize()</code> deze waardes via <code>Application::get()</code>:</p>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:240px">Application-key</th><th>Default</th><th>Doel</th></tr></thead>
        <tbody>
            <tr><td><code>mail_server</code></td><td><code>localhost</code></td><td>SMTP-host.</td></tr>
            <tr><td><code>mail_server_port</code></td><td><code>25</code></td><td>SMTP-poort. Voor TLS: 587. Voor SSL: 465.</td></tr>
            <tr><td><code>mail_username</code></td><td><code>''</code></td><td>SMTP-username. Lege string → geen auth.</td></tr>
            <tr><td><code>mail_password</code></td><td><code>''</code></td><td>SMTP-password.</td></tr>
            <tr><td><code>email_fromname</code></td><td><code>webmaster@stenversonline.nl</code></td><td>Default From-adres.</td></tr>
            <tr><td><code>company</code></td><td><code>RINO amsterdam</code></td><td>Default From-name.</td></tr>
            <tr><td><code>email_template</code></td><td><code>''</code></td><td>HTML-template voor de body. Leeg = geen template.</td></tr>
            <tr><td><code>app_beheerder_email</code></td><td><code>''</code></td><td>Auto-BCC op alle uitgaande mail. Gehandhaafd voor audit-trail.</td></tr>
            <tr><td><code>local</code></td><td><code>false</code></td><td>True → simulatie-modus: <code>showPreview()</code> i.p.v. echte SMTP.</td></tr>
            <tr><td><code>test</code></td><td><code>false</code></td><td>True → <code>wrapTestEnvironmentWarning()</code> plakt een "TEST" banner bovenaan de body.</td></tr>
        </tbody>
    </table>
    <p>Deze keys leef in <code>app.php</code> (template-bestand op de site-root, NIET in git). Voor production-secrets is dat de juiste plek.</p>

    <h2>Email API</h2>
    <pre><code>use App\Library\Email;

// Factory + fluent interface
$ok = Email::create()
    -&gt;setSubject('Welkom bij ...')
    -&gt;setBody('&lt;p&gt;Beste ...&lt;/p&gt;')
    -&gt;setFrom('noreply@example.nl', 'Naam')   // override default
    -&gt;setReplyTo('support@example.nl')
    -&gt;addRecipient('user@example.com', 'Naam')
    -&gt;addRecipientCC('cc@example.com')
    -&gt;addRecipientBCC('audit@example.com')
    -&gt;addAttachment('/path/to/file.pdf')
    -&gt;setTemplate('&lt;html&gt;&lt;body&gt;{{BODY}}&lt;/body&gt;&lt;/html&gt;')
    -&gt;send();
// returns bool
</code></pre>

    <h2>Test-mode (Application::get('test'))</h2>
    <p>Als de <code>test</code> flag in Application aan staat, voegt <code>send()</code> een rode warning-banner bovenaan elke body:</p>
    <pre><code>&lt;div style="background:#fee; border:2px solid red; padding:10px;"&gt;
    LET OP - TEST OMGEVING (origineel naar: original-recipient@example.com)
&lt;/div&gt;
</code></pre>
    <p>De originele To/CC/BCC-lijsten worden NIET aangepast — die staan in de banner zodat je ziet waar de mail "echt" naartoe ging. Default: BCC blijft op <code>app_beheerder_email</code> staan en die ontvangt 'm dus ook.</p>

    <h2>Local-mode (Application::get('local'))</h2>
    <p>Als de <code>local</code> flag aan staat, draait <code>showPreview()</code> in plaats van <code>$this-&gt;mailer-&gt;send()</code>:</p>
    <ul>
        <li>De rendered HTML (inclusief test-wrap als <code>test</code> ook aan staat) wordt in een pop-up venster geserveerd.</li>
        <li>SMTP wordt niet geraakt — handig voor lokale dev zonder mail-server.</li>
        <li>De Omgeving-tab's test-mail formulier surface't dit als "E-mail gesimuleerd (local-mode actief)".</li>
    </ul>

    <h2>EmailLogService afterSend hook</h2>
    <p><code>cma/bootstrap.inc</code> registreert sinds altijd een afterSend-callback op de static <code>Email::$afterSend</code> property:</p>
    <pre><code>\App\Library\Email::$afterSend = function(array $data) {
    \Cma\Services\EmailLogService::log($data);
};
</code></pre>
    <p>Elke <code>Email::send()</code> roept deze hook aan met <code>$data</code> dat bevat: <code>success</code>, <code>from</code>, <code>to</code> (originele recipients, vóór test-clearing), <code>cc</code>, <code>bcc</code>, <code>subject</code>, <code>body</code>, <code>error</code>. <code>EmailLogService</code> persist deze naar <code>tblEmailLog</code> voor admin-review.</p>
    <p>Controleerbaar via env-var <code>EMAIL_LOG_ENABLED</code> (default <code>true</code>). Sinds v1.12.1 staat er een <code>class_exists</code> guard om de afterSend-assignment heen zodat half-updated installs (waar <code>Email.php</code> nog niet autoloadable is) niet crash'en op deze regel.</p>

    <h2>Test-mail formulier</h2>
    <p>Op de Omgeving-tab van <a href="tools_serverinfo.php" target="_top">Server informatie</a> staat een test-mail form dat <code>Email::create()-&gt;send()</code> aanroept tegen de huidige config — handig om SMTP-bereikbaarheid te testen zonder een echte form-action te triggeren. Developers-only.</p>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=logs">Logs &amp; monitoring</a> (waar email-fouten landen), <a href="documentation.php?topic=security">Beveiliging</a> (secrets in app.php).
    </div>
    <?php
}

function render_doc_llm(): void
{
    ?>
    <h1>LLM-configuratie</h1>
    <p class="docs-meta">Engines, env-vars, en de curated modellenlijst die <code>tools_llm.php</code> en <code>llm_analyse.php</code> beide voeden.</p>

    <h2>Engines die het platform kent</h2>
    <p><code>tools_llm.php</code> definieert vier engine-entries:</p>
    <table class="listtable">
        <thead><tr class="listheader"><th>Engine-key</th><th>Default URL</th><th>Probe-pad</th></tr></thead>
        <tbody>
            <tr><td><code>ollama</code></td><td><code>http://localhost:11434</code></td><td><code>/api/tags</code></td></tr>
            <tr><td><code>lmstudio</code></td><td><code>http://localhost:1234</code></td><td><code>/v1/models</code></td></tr>
            <tr><td><code>llamacpp</code></td><td><code>http://localhost:8080</code></td><td><code>/v1/models</code></td></tr>
            <tr><td><code>anthropic_fallback</code></td><td><code>https://api.anthropic.com</code></td><td><code>/v1/models</code> met <code>x-api-key</code> header</td></tr>
        </tbody>
    </table>
    <p>De Ollama-kaart toont sinds v1.13.0 de curated modellenlijst inline. Wanneer <code>llamacpp</code>'s probe succesvol is, wordt de Ollama-kaart overgeslagen — de cook heeft dan al een werkende engine.</p>

    <h2>Env-vars</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:200px">Variabele</th><th>Doel</th></tr></thead>
        <tbody>
            <tr><td><code>LLM_PROVIDER</code></td><td>Forceer een specifieke provider. Default: auto-detect op basis van <code>LLM_URL</code>.</td></tr>
            <tr><td><code>LLM_URL</code></td><td>OpenAI-compatible endpoint voor de primary LLM. Bijv. <code>http://localhost:11434/api/generate</code> voor Ollama, <code>http://localhost:8080/v1</code> voor llama.cpp.</td></tr>
            <tr><td><code>LLM_MODEL</code></td><td>Modelnaam die de primary engine moet gebruiken. Op de LLM-status pagina wordt deze gematcht tegen wat de engine zelf zegt te hebben geladen.</td></tr>
            <tr><td><code>LLM_KEY</code></td><td>API-key voor de Anthropic-fallback. <code>App\Library\Llm::anthropicFallbackKey()</code> leest deze EERST.</td></tr>
            <tr><td><code>LLM_FALLBACK_MODEL</code></td><td>Anthropic-model voor de fallback (bijv. <code>claude-haiku-4-5</code>).</td></tr>
            <tr><td><code>OCR_VISION_KEY</code></td><td>API-key voor vision-OCR. Als <code>LLM_KEY</code> leeg is, valt de fallback-resolver hier op terug — mits <code>OCR_VISION_PROVIDER</code> op <code>anthropic</code> staat (of unset, default = anthropic).</td></tr>
            <tr><td><code>OCR_VISION_PROVIDER</code></td><td>Provider voor de vision-OCR. Standaard <code>anthropic</code>; alternatieven blokken de fallback-share met <code>LLM_KEY</code>.</td></tr>
            <tr><td><code>LLM_MODELS_DIR</code></td><td>Waar GGUF-bestanden lokaal leven. Default: <code>C:\llama\models</code> op Windows, <code>~/llama-models</code> elders.</td></tr>
        </tbody>
    </table>

    <h2>Curated modellenlijst</h2>
    <p>Single source of truth: <code>cma/data/llm_suggested_models.php</code>. Per entry:</p>
    <ul>
        <li><code>name</code> — GGUF-bestandsnaam op disk.</li>
        <li><code>label</code> — human-readable label.</li>
        <li><code>note</code> — one-line context (grootte, vendor, sterke punten).</li>
        <li><code>url</code> — Hugging Face direct-download URL.</li>
        <li><code>sizeApprox</code> — bytes (voor download-progress estimate).</li>
        <li><code>ollama_tag</code> — Ollama-registry tag (bv. <code>gemma3:4b</code>).</li>
    </ul>
    <p>Index 0 is de current recommendation; zowel <code>tools_llm.php</code>'s install-steps (interpoleert <code>$recOllamaTag</code> / <code>$recGGUF</code> / <code>$recLlmModel</code> in code-blocks) als de Ollama-kaart's "Aanbevolen modellen" sectie gebruiken die. Nieuw SOTA-model? Prepend in de array — beide surfaces volgen automatisch.</p>

    <h2>Resolutie van de Anthropic-fallback key</h2>
    <p>De fallback-resolver in <code>App\Library\Llm::anthropicFallbackKey()</code> volgt deze volgorde:</p>
    <ol>
        <li><code>LLM_KEY</code> als die niet leeg is.</li>
        <li><code>OCR_VISION_KEY</code> als <code>OCR_VISION_PROVIDER</code> op <code>anthropic</code> staat (of unset).</li>
        <li>Anders: geen key — fallback werkt niet.</li>
    </ol>
    <p>Op de <a href="tools/llm_analyse.php" target="_top">LLM-status pagina</a> staat een gemaskeerde versie van de gebruikte key (<code>sk-ant-…XyZ</code>) zodat je kan verifiëren dat de juiste env-var aanslaat.</p>

    <h2>llm_analyse.php vs tools_llm.php</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:200px">Page</th><th>Doel</th></tr></thead>
        <tbody>
            <tr><td><a href="tools/llm_analyse.php" target="_top"><code>llm_analyse.php</code></a></td><td>Status-dashboard: config-tabel, endpoint-probe, lokale modellen-lijst, recente <code>[Llm]</code> fouten uit php_errors.log. Sinds v1.14.0 standaard CMA-login (was DEPLOY_SECRET).</td></tr>
            <tr><td><a href="tools/tools_llm.php" target="_top"><code>tools_llm.php</code></a></td><td>Management-page: probe per engine, inline modellen-lijst per engine, install-steps per OS in collapsible details. De Ollama-kaart heeft de curated <code>ollama pull</code>-lijst inline.</td></tr>
        </tbody>
    </table>

    <h2>Probe-kind sturing</h2>
    <p>Per engine kun je het probe-gedrag overrulen via <code>probe_kind</code>:</p>
    <ul>
        <li>Default: <code>local</code> — eenvoudige GET, geen extra headers.</li>
        <li><code>anthropic</code> — voegt <code>x-api-key</code> + <code>anthropic-version</code> headers toe, leest de key via <code>llm_anthropic_key()</code> resolver.</li>
    </ul>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=environment">Omgeving &amp; .env</a> (waar LLM_* leeft), <a href="documentation.php?topic=troubleshooting">Troubleshooting</a> (LLM-sectie).
    </div>
    <?php
}
