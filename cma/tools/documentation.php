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

// Load the auto-fix module BEFORE anything that can render or exit early: the
// fragment branch and the search-index build below both run topic renderers,
// and the docfix endpoint answers, long before the bottom of this file is
// reached. A require further down is simply never executed on those paths.
//
// It is a separate file so it can be unit-tested without loading this
// admin-gated page (see cma/tests/DocFixWebConfigTest.php). That does mean a
// `composer update` writes TWO files, so a request landing mid-sync can see
// this page without its fixers — and this is the page an operator opens when
// a deploy went sideways. Hence: degrade with a visible notice, never fatal.
if (is_file(__DIR__ . '/documentation_fixes.inc')) {
    require_once __DIR__ . '/documentation_fixes.inc';
}

/** False when documentation_fixes.inc is missing or half-written (deploy in flight). */
function cma_doc_fixers_available(): bool {
    return function_exists('cma_doc_fixers');
}

// Auto-fix endpoint (POST only — it writes web.config). Sits before the topic
// registry because it answers in JSON and never renders page chrome. Admin-only
// is already enforced by the SecurityHelper gate above.
if (Request::server('REQUEST_METHOD', '') === 'POST' && Request::post('docfix', '') !== '') {
    header('Content-Type: application/json; charset=UTF-8');
    if (!function_exists('cma_doc_apply_fix')) {
        echo json_encode(['ok' => false, 'message' => '<code>documentation_fixes.inc</code> ontbreekt op deze site — draai <code>composer update stenversonline/platform</code> opnieuw.']);
        exit;
    }
    echo json_encode(cma_doc_apply_fix((string) Request::post('docfix', '')));
    exit;
}

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
            'portability'  => ['label' => 'Portabiliteit (niet-IIS)', 'icon' => 'lnr-sync',    'render' => 'render_doc_portability'],
        ],
    ],
    'dev' => [
        'label' => 'Voor ontwikkelaars',
        'icon'  => 'lnr-code',
        'children' => [
            'architecture'  => ['label' => 'Architectuur',                'icon' => 'lnr-layers',     'render' => 'render_doc_architecture'],
            'routing'       => ['label' => 'URL-routing & deep links',    'icon' => 'lnr-link',       'render' => 'render_doc_routing'],
            'new_tool'      => ['label' => 'Een CMA-tool toevoegen',      'icon' => 'lnr-construction','render' => 'render_doc_new_tool'],
            'database'      => ['label' => 'Database & RecordSet',        'icon' => 'lnr-database',   'render' => 'render_doc_database'],
            'migrations'    => ['label' => 'Migraties schrijven',         'icon' => 'lnr-arrow-right','render' => 'render_doc_migrations'],
            'json_forms'    => ['label' => 'JSON-gedreven formulieren',   'icon' => 'lnr-text-format','render' => 'render_doc_json_forms'],
            'formval'       => ['label' => 'Formuliervalidatie (front-end)', 'icon' => 'lnr-checkmark-circle','render' => 'render_doc_formval'],
            'json_config'   => ['label' => 'JSON-configuratie',           'icon' => 'lnr-papers',     'render' => 'render_doc_json_config'],
            'images'        => ['label' => 'WebP & afbeeldingen',         'icon' => 'lnr-picture',    'render' => 'render_doc_images'],
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

/**
 * Split the tree into a CMA and a Front-end branch — but ONLY when the site
 * actually brings its own chapters. Without site documentation the tree stays
 * exactly as it was, so nothing changes for sites that don't use this.
 *
 * Pure function (no I/O) so it is unit-testable; see DocumentationSiteTopicsTest.
 */
function cma_doc_split_tree(array $topics, array $siteTopics): array
{
    if (!$siteTopics) {
        return $topics;
    }
    return [
        'cma' => [
            'label'    => 'CMA',
            'icon'     => 'lnr-cog',
            'children' => $topics,
        ],
        'frontend' => [
            'label'    => 'Front-end',
            'icon'     => 'lnr-screen',
            'children' => $siteTopics,
        ],
    ];
}

/**
 * Drop site topics whose slug is already taken by a platform topic. The slug
 * namespace is flat for routing, so a collision would silently shadow one of
 * the two — the platform topic always wins and the site gets a log line.
 */
function cma_doc_strip_reserved(array $tree, array $reserved): array
{
    foreach ($tree as $slug => $node) {
        if (isset($node['children'])) {
            $tree[$slug]['children'] = cma_doc_strip_reserved($node['children'], $reserved);
            if (!$tree[$slug]['children']) {
                unset($tree[$slug]);
            }
        } elseif (isset($reserved[$slug])) {
            error_log('[site_doc] hoofdstuk-slug "' . $slug . '" botst met een CMA-topic en is overgeslagen');
            unset($tree[$slug]);
        }
    }
    return $tree;
}

// =========================================================================
// Site-eigen documentatie (optioneel)
// -------------------------------------------------------------------------
// Een consumer-site levert zijn eigen hoofdstukken aan als DATA:
//   doc/documentation.json  — hoofdstukregistratie (per site verschillend)
//   doc/chapters/*.html     — de inhoud, als HTML-fragment
//   src/includes/site_documentation.inc — de lezer die dit platform aanroept
// Het platform kent die inhoud niet: het vraagt alleen de tree op en routeert
// ernaartoe. Ontbreekt de lezer, dan blijft deze tool exact zoals hij was.
// =========================================================================
$siteDocReader = cma_doc_site_root() . '/src/includes/site_documentation.inc';
$siteTopics    = [];
if (is_file($siteDocReader)) {
    require_once $siteDocReader;
    if (function_exists('site_doc_tree')) {
        // Hoofdstukken die de site bewust in een BESTAANDE platform-tak wil
        // hangen, daar tussen zetten. Die blijven dus onder CMA staan.
        if (function_exists('site_doc_chapters_for_parent')) {
            foreach (['admin', 'dev', 'reference'] as $__branch) {
                $__extra = site_doc_chapters_for_parent($__branch);
                if ($__extra) {
                    $topics[$__branch]['children'] = array_merge($topics[$__branch]['children'] ?? [], $__extra);
                }
            }
        }
        $siteTopics = cma_doc_strip_reserved(site_doc_tree(), flatten_topics($topics));
    }
}

$topics = cma_doc_split_tree($topics, $siteTopics);

$flat = flatten_topics($topics);

$selected = strtolower(trim((string)Request::query('topic', 'overview')));
if (!isset($flat[$selected]) || !isset($flat[$selected]['render'])) {
    $selected = 'overview';
}

// Fragment mode: emit only the rendered topic, no page chrome. The client-side
// topic router fetches this to swap the content area in place instead of a
// full page (re)load. Sits BEFORE the search-index build below, which renders
// every topic and would be pure waste on a fragment request.
if (Request::query('fragment', '') === '1') {
    call_user_func($flat[$selected]['render']);
    exit;
}

// =========================================================================
// Full-text search index — render every topic ONCE into plain text so the
// sidebar search box can match across ALL documents, not just the open one.
// The live self-checks self-skip while $_docs_indexing is set, so this stays
// cheap (no per-topic cURL probe, no status tables). Emitted as JSON below.
// =========================================================================
$GLOBALS['_docs_indexing'] = true;
$docs_search_index = [];
foreach ($flat as $slug => $node) {
    if (!isset($node['render']) || !function_exists($node['render'])) { continue; }
    ob_start();
    try { call_user_func($node['render']); } catch (\Throwable $e) { /* skip a broken topic, keep the rest searchable */ }
    $html = ob_get_clean();
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $docs_search_index[] = [
        'label' => $node['label'],
        'href'  => slug_to_href($slug),
        'text'  => $text,
    ];
}
$GLOBALS['_docs_indexing'] = false;

cma_html_header('CMA - Documentatie');
echo '<body class="contentbody tools tool-docs">';
// cma-tree is not in the auto-load list (cma-fold is); load it explicitly
// for the sidebar. cma-fold is autoloaded by bootstrap but we re-state
// it here to make the dependency obvious to a future maintainer.
// NOTE: absolute path — cma_script emits the src verbatim, so a relative
// 'webcomponents/…' would resolve against /cma/tools/ (404) on this page.
cma_script('/cma/webcomponents/cma-tree.js');
ToolbarHelper::start(true);
ToolbarHelper::title('Documentatie');
ToolbarHelper::separator();
ToolbarHelper::status($flat[$selected]['label']);
ToolbarHelper::end();
// The wrapper deliberately does NOT carry the generic `tools` class: the
// documentation hub is not a tool page and inherited none of #c.tools's
// chrome (20px padding, h2 spacing) usefully — it brings its own layout.
// The tool-docs class sits on the #c wrapper (not only on <body>): inside the
// main.php shell this page is loadPage-injected into #contentArea and the
// <body> tag (with its classes) is stripped by the HTML parser — every
// .tool-docs-scoped rule below would silently stop matching there.
echo '<div id="c" class="documentatie tool-docs">';
?>

<style>
/* Own wrapper class instead of the shared `tools` one: no outer padding
   (the sidebar + content layout below brings its own internal spacing;
   the 20px of #c.tools pushed everything off-center and added a visible
   double border), just the scroll container. */
#c.documentatie, body.contentbody #c.documentatie { padding: 0; overflow: auto; }
/* .tools lib-message (form.css) no longer applies now that the wrapper has
   its own class — keep the spacing the callouts had. */
#c.documentatie lib-message { margin-bottom: 15px; }

/* No fixed height — the layout grows with its content so the page never looks
   misformed (cut off / double scrollbar). Sidebar and content can still scroll
   internally via their own overflow:auto when they individually overflow. */
.tool-docs .docs-layout { display: flex; gap: 0; align-items: stretch; }
/* The fold divider must span the full layout height. The component's own
   height:100% resolves to auto here (this flex row has no definite height),
   leaving a stub — stretch it along the cross axis instead. */
.tool-docs .docs-layout > cma-fold { height: auto; align-self: stretch; }
.tool-docs .docs-sidebar { flex: 0 0 260px; padding: 14px 8px 14px 14px; overflow: auto; background: var(--bg-surface-alt, #f6f8fa); border-right: 1px solid var(--border-color, #e0e0e0); }
.tool-docs .docs-content { flex: 1; min-width: 0; padding: 16px 22px; overflow: auto; }
/* Sidebar search box + cross-document results list. When results are shown the
   cma-tree is hidden (see .docs-sidebar.is-searching) so the two never stack. */
.tool-docs .docs-search { margin-bottom: 10px; }
.tool-docs .docs-search__input { width: 100%; box-sizing: border-box; padding: 6px 10px; font-size: var(--font-size-sm); border: 1px solid var(--border-color, #d0d4d9); border-radius: 4px; background: #fff; }
.tool-docs .docs-search__input:focus { outline: none; border-color: var(--color-info, #077ab2); box-shadow: 0 0 0 2px rgba(7, 122, 178, 0.15); }
.tool-docs .docs-sidebar.is-searching #docsNav { display: none; }
.tool-docs .docs-search__results { list-style: none; margin: 0; padding: 0; }
.tool-docs .docs-search__results li { margin: 0 0 2px; }
.tool-docs .docs-search__results a { display: block; padding: 7px 9px; border-radius: 4px; text-decoration: none; color: inherit; }
.tool-docs .docs-search__results a:hover, .tool-docs .docs-search__results a:focus { background: var(--bg-surface-alt, #eef1f4); outline: none; }
.tool-docs .docs-search__results .docs-search__topic { display: block; font-weight: 600; font-size: var(--font-size-sm); color: var(--color-info, #077ab2); }
.tool-docs .docs-search__results .docs-search__snippet { display: block; font-size: var(--font-size-xs, 12px); color: var(--text-muted, #6c757d); line-height: 1.4; margin-top: 1px; }
.tool-docs .docs-search__results mark, .tool-docs .docs-content mark { background: #fff2a8; color: inherit; padding: 0 1px; border-radius: 2px; }
.tool-docs .docs-search__empty { padding: 7px 9px; color: var(--text-muted, #6c757d); font-size: var(--font-size-sm); }
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
/* "Los op"-knop in de check-tabel: op een eigen regel onder de fix-tekst. */
.tool-docs .docs-fix-btn { display: inline-block; margin-top: 6px; white-space: nowrap; }
.tool-docs .docs-content .seealso { margin-top: 24px; padding-top: 14px; border-top: 1px solid var(--border-color, #e0e0e0); color: var(--text-muted, #6c757d); font-size: var(--font-size-sm); }

/* Generic copy button — injected on every <pre> block by the script below.
   Top-right, fades in on hover/focus, copies the block as plain text. */
.tool-docs .docs-content pre { position: relative; }
.tool-docs .docs-copy-btn {
    position: absolute; top: 6px; right: 6px;
    padding: 3px 9px; font-size: var(--font-size-xs, 12px); line-height: 1.4;
    color: #d4d4d4; background: rgba(255, 255, 255, 0.10);
    border: 1px solid rgba(255, 255, 255, 0.20); border-radius: 4px;
    cursor: pointer; opacity: 0;
    transition: opacity 0.12s ease, background 0.12s ease;
}
.tool-docs .docs-content pre:hover .docs-copy-btn,
.tool-docs .docs-copy-btn:focus { opacity: 1; }
.tool-docs .docs-copy-btn:hover { background: rgba(255, 255, 255, 0.20); }
.tool-docs .docs-copy-btn.copied { color: #4ec9b0; border-color: #4ec9b0; }
</style>

<div class="docs-layout">
    <nav class="docs-sidebar" id="docsSidebar">
        <div class="docs-search">
            <input type="search" id="docsSearch" class="docs-search__input" placeholder="Zoek in alle documentatie…" autocomplete="off" aria-label="Zoek in alle documentatie">
        </div>
        <ul class="docs-search__results" id="docsSearchResults" hidden></ul>
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
    var topicLabels = <?= json_encode(array_map(static fn($n) => $n['label'], $flat), JSON_UNESCAPED_UNICODE) ?>;
    nav.setData(data);
    nav.expandAll();
    nav.selectByHref(<?= json_encode(slug_to_href($selected)) ?>);

    // Embedded in the main.php shell (loadPage-injected) or standalone page?
    var inShell = !!document.getElementById('contentArea');

    function topicFromHref(href) {
        var m = /[?&]topic=([^&#]+)/.exec(href);
        return m ? decodeURIComponent(m[1]) : 'overview';
    }

    // Topic navigation replaces ONLY the content area: fetch the topic as a
    // fragment (?fragment=1) and swap it in. The fetch URL is absolute — a
    // relative 'documentation.php' inside the shell would resolve against
    // /cma/main.php to the nonexistent /cma/documentation.php. Exposed on
    // window (and refreshed per docs render) for the once-bound document
    // click router below.
    window.cmaDocsNavigate = function (href, skipHistory) {
        var slug = topicFromHref(href);
        var hashPos = href.indexOf('#');
        var hash = hashPos === -1 ? '' : href.slice(hashPos);
        fetch('/cma/tools/documentation.php?fragment=1&topic=' + encodeURIComponent(slug), { credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
            .then(function (html) {
                var content = document.querySelector('.tool-docs .docs-content');
                if (!content) { window.location.href = href; return; }
                content.innerHTML = html;
                nav.selectByHref(slug === 'overview' ? 'documentation.php' : 'documentation.php?topic=' + slug);
                var status = document.querySelector('.toolbar-status');
                if (status && topicLabels[slug]) status.textContent = topicLabels[slug];
                // Leaving via a search result: restore the tree in the sidebar
                var si = document.getElementById('docsSearch');
                if (si) si.value = '';
                var sr = document.getElementById('docsSearchResults');
                if (sr) { sr.hidden = true; sr.innerHTML = ''; }
                var sb = document.getElementById('docsSidebar');
                if (sb) sb.classList.remove('is-searching');
                // Keep the URL addressable. Standalone gets a real history
                // entry so Back/Forward walk topics. In the shell only the
                // CURRENT entry is rewritten (replaceState): pushing would
                // fight main.js's history handling, whose popstate loadPage()s
                // e.state.page — the state keeps that shape so Forward/refresh
                // reopens this topic inside the shell.
                if (!skipHistory) {
                    if (inShell) {
                        var page = 'tools.php?tool=documentation' + (slug === 'overview' ? '' : '&topic=' + slug);
                        history.replaceState({ page: page }, '', '/cma/main.php?page=' + encodeURIComponent(page));
                    } else {
                        history.pushState({ cmaDocsTopic: slug }, '', (slug === 'overview' ? 'documentation.php' : 'documentation.php?topic=' + slug) + hash);
                    }
                }
                window.scrollTo(0, 0);
                content.scrollTop = 0;
                var ca = document.getElementById('contentArea');
                if (ca) ca.scrollTop = 0;
                docsInitCopyButtons();
                var qm = /[#&]q=([^&]+)/.exec(hash);
                if (qm) docsHighlightQuery(decodeURIComponent(qm[1].replace(/\+/g, ' ')).trim());
            })
            .catch(function () { window.location.href = href; });
    };

    // cma-tree calls e.preventDefault() on item clicks and only dispatches
    // an item-click event — the default anchor navigation never runs.
    nav.addEventListener('item-click', function (e) {
        var href = e.detail && e.detail.href;
        if (!href || href === '#') return;
        window.cmaDocsNavigate(href);
    });

    // Route every other topic link (in-content cross-links, search results)
    // through the fragment swap. Capture phase on document: inside the shell
    // this must outrun main.js's #contentArea link interceptor, which would
    // otherwise loadPage() the nonexistent /cma/documentation.php. Bound once
    // per browser session — it always calls the LATEST window.cmaDocsNavigate
    // and stands down when no docs content is on screen.
    if (!window._cmaDocsLinkRouterBound) {
        window._cmaDocsLinkRouterBound = true;
        document.addEventListener('click', function (e) {
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
            var a = e.target.closest ? e.target.closest('a[href^="documentation.php"]') : null;
            if (!a) return;
            if (!document.querySelector('.tool-docs .docs-content')) return;
            e.preventDefault();
            e.stopPropagation();
            window.cmaDocsNavigate(a.getAttribute('href'));
        }, true);
    }

    // "Los op"-buttons in a live-check table: POST the fix, then re-render the
    // topic so the table shows the new state (green instead of red) instead of
    // the operator having to trust a message. Bound once on document, same
    // reason as the link router: the content area gets replaced wholesale.
    if (!window._cmaDocsFixBound) {
        window._cmaDocsFixBound = true;
        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.docs-fix-btn') : null;
            if (!btn || btn.disabled) return;
            e.preventDefault();
            var check = btn.getAttribute('data-fix');
            var original = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Bezig…';
            var body = new FormData();
            body.append('docfix', check);
            fetch('/cma/tools/documentation.php', { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    // Refresh the topic: the checks re-run server-side, so a
                    // successful fix flips its own row to OK. Keep the message
                    // above the (new) table so the operator sees what happened.
                    var slug = /[?&]topic=([^&#]+)/.exec(window.location.search);
                    var href = 'documentation.php?topic=' + (slug ? slug[1] : 'iis_config');
                    var show = function () {
                        var content = document.querySelector('.tool-docs .docs-content');
                        if (!content) return;
                        var msg = document.createElement('lib-message');
                        msg.setAttribute('type', res.ok ? 'success' : 'error');
                        msg.setAttribute('closable', '');
                        msg.innerHTML = res.message || (res.ok ? 'Aangepast.' : 'Mislukt.');
                        content.insertBefore(msg, content.firstChild);
                    };
                    if (res.ok && window.cmaDocsNavigate) {
                        window.cmaDocsNavigate(href, true);
                        // cmaDocsNavigate swaps innerHTML asynchronously.
                        setTimeout(show, 350);
                    } else {
                        btn.disabled = false;
                        btn.textContent = original;
                        show();
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.textContent = original;
                });
        });
    }

    // Standalone only: Back/Forward re-render the topic from the URL. In the
    // shell main.js owns popstate and reloads the docs page at e.state.page.
    if (!inShell && !window._cmaDocsPopstateBound) {
        window._cmaDocsPopstateBound = true;
        window.addEventListener('popstate', function () {
            window.cmaDocsNavigate(window.location.search + window.location.hash, true);
        });
    }
})();

// -----------------------------------------------------------------------
// Cross-document search. The index (every topic rendered to plain text) is
// emitted server-side. Typing filters topics by all query tokens (AND),
// shows a result list with a highlighted snippet, and hides the tree. An
// empty box restores the tree. Selecting a result jumps to that topic with
// #q=… so the destination highlights + scrolls to the first match.
// -----------------------------------------------------------------------
(function () {
    var input   = document.getElementById('docsSearch');
    var results = document.getElementById('docsSearchResults');
    var sidebar = document.getElementById('docsSidebar');
    if (!input || !results || !sidebar) return;
    var index = <?= json_encode($docs_search_index, JSON_UNESCAPED_UNICODE) ?> || [];

    function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]); }); }
    function reEsc(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

    function snippet(text, tokens) {
        var lower = text.toLowerCase(), at = -1;
        tokens.forEach(function (t) { var p = lower.indexOf(t); if (p !== -1 && (at === -1 || p < at)) at = p; });
        if (at === -1) at = 0;
        var start = Math.max(0, at - 40);
        var slice = text.slice(start, start + 170);
        if (start > 0) slice = '… ' + slice;
        if (start + 170 < text.length) slice = slice + ' …';
        var html = esc(slice);
        tokens.forEach(function (t) {
            if (!t) return;
            html = html.replace(new RegExp('(' + reEsc(t) + ')', 'ig'), '<mark>$1</mark>');
        });
        return html;
    }

    function render(query) {
        var tokens = query.toLowerCase().split(/\s+/).filter(Boolean);
        if (tokens.length === 0) {
            sidebar.classList.remove('is-searching');
            results.hidden = true;
            results.innerHTML = '';
            return;
        }
        sidebar.classList.add('is-searching');
        results.hidden = false;
        var matches = [];
        index.forEach(function (doc) {
            var hayLabel = doc.label.toLowerCase(), hayText = doc.text.toLowerCase();
            var ok = tokens.every(function (t) { return hayLabel.indexOf(t) !== -1 || hayText.indexOf(t) !== -1; });
            if (!ok) return;
            var score = 0;
            tokens.forEach(function (t) {
                if (hayLabel.indexOf(t) !== -1) score += 10;
                var idx = hayText.indexOf(t); if (idx !== -1) score += Math.max(1, 5 - Math.floor(idx / 400));
            });
            matches.push({ doc: doc, score: score });
        });
        matches.sort(function (a, b) { return b.score - a.score; });
        if (matches.length === 0) {
            results.innerHTML = '<li class="docs-search__empty">Geen resultaten voor "' + esc(query) + '".</li>';
            return;
        }
        var html = '';
        matches.slice(0, 15).forEach(function (m) {
            var href = m.doc.href + '#q=' + encodeURIComponent(query);
            html += '<li><a href="' + esc(href) + '">' +
                    '<span class="docs-search__topic">' + esc(m.doc.label) + '</span>' +
                    '<span class="docs-search__snippet">' + snippet(m.doc.text, tokens) + '</span>' +
                    '</a></li>';
        });
        results.innerHTML = html;
    }

    var t;
    input.addEventListener('input', function () {
        clearTimeout(t);
        var q = input.value.trim();
        t = setTimeout(function () { render(q); }, 120);
    });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { input.value = ''; render(''); }
        if (e.key === 'Enter') { var a = results.querySelector('a'); if (a) a.click(); }
    });
})();

// Highlight + scroll to the search term in the current topic content.
// A hoisted function declaration: the topic router above re-runs it after
// swapping the content area (initial full-page load calls it below with the
// #q=… hash a search result appends).
function docsHighlightQuery(query) {
    var tokens = (query || '').toLowerCase().split(/\s+/).filter(Boolean);
    var root = document.querySelector('.docs-content');
    if (!tokens.length || !root) return;
    var reEsc = function (s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); };
    var re = new RegExp('(' + tokens.map(reEsc).join('|') + ')', 'ig');
    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode: function (n) {
            if (!n.nodeValue.trim()) return NodeFilter.FILTER_REJECT;
            var p = n.parentNode;
            if (p && (p.nodeName === 'SCRIPT' || p.nodeName === 'STYLE' || p.nodeName === 'MARK')) return NodeFilter.FILTER_REJECT;
            return NodeFilter.FILTER_ACCEPT;
        }
    });
    var nodes = [], n; while ((n = walker.nextNode())) nodes.push(n);
    var first = null;
    nodes.forEach(function (node) {
        re.lastIndex = 0;
        if (!re.test(node.nodeValue)) return;
        re.lastIndex = 0;
        var frag = document.createDocumentFragment(), last = 0, mm;
        while ((mm = re.exec(node.nodeValue))) {
            if (mm.index > last) frag.appendChild(document.createTextNode(node.nodeValue.slice(last, mm.index)));
            var mark = document.createElement('mark');
            mark.textContent = mm[0];
            frag.appendChild(mark);
            if (!first) first = mark;
            last = mm.index + mm[0].length;
        }
        if (last < node.nodeValue.length) frag.appendChild(document.createTextNode(node.nodeValue.slice(last)));
        node.parentNode.replaceChild(frag, node);
    });
    if (first) first.scrollIntoView({ block: 'center' });
}

(function () {
    var m = /[#&]q=([^&]+)/.exec(window.location.hash);
    if (m) docsHighlightQuery(decodeURIComponent(m[1].replace(/\+/g, ' ')).trim());
})();

// Generic copy button on every code block. Idempotent (skips a <pre> that
// already has one); the topic router re-runs it after each content swap.
function docsInitCopyButtons() {
    document.querySelectorAll('.docs-content pre').forEach(function (pre) {
        if (pre.querySelector('.docs-copy-btn')) return;
        // Capture the plain text from the <code> (or the <pre>) BEFORE the
        // button is appended, so the button's own label never leaks in.
        var src  = pre.querySelector('code') || pre;
        var text = src.innerText;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'docs-copy-btn';
        btn.textContent = 'Kopiëren';
        btn.addEventListener('click', function () {
            var done = function () {
                btn.textContent = 'Gekopieerd';
                btn.classList.add('copied');
                setTimeout(function () {
                    btn.textContent = 'Kopiëren';
                    btn.classList.remove('copied');
                }, 1500);
            };
            var fallback = function () {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } catch (e) {}
                ta.remove();
                done();
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                cmaCopyToClipboard(text).then(done).catch(fallback);
            } else {
                fallback();
            }
        });
        pre.appendChild(btn);
    });
}
docsInitCopyButtons();
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
    return ['label' => $label, 'status' => 'fail', 'detail' => 'Regel ontbreekt — een PHP-response zonder Content-Type wordt door iOS Safari als download aangeboden.', 'fix' => 'Standaard in <code>templates/web.config.template</code>. Doe <code>composer update stenversonline/platform</code> en kopieer de outbound rule.'];
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
    $label = 'Parent web.config: hiddenSegments (.env / composer.json / composer.lock / .sessions)';
    $xml = cma_doc_parent_webconfig();
    if ($xml === null) {
        return ['label' => $label, 'status' => 'info', 'detail' => 'Parent <code>web.config</code> niet gevonden.', 'fix' => ''];
    }
    $required = ['.env', 'composer.json', 'composer.lock', '.sessions'];
    $present  = [];
    foreach ($xml->xpath("//security/requestFiltering/hiddenSegments/add") as $node) {
        $present[] = (string)$node['segment'];
    }
    $missing = array_diff($required, $present);
    if (empty($missing)) {
        // Count, niet "alle drie" — de lijst is inmiddels vier segmenten lang.
        return ['label' => $label, 'status' => 'pass', 'detail' => 'Alle ' . count($required) . ' segmenten zijn verborgen.', 'fix' => ''];
    }
    return ['label' => $label, 'status' => 'fail', 'detail' => 'Ontbreekt: <code>' . htmlspecialchars(implode('</code>, <code>', $missing)) . '</code> — publiek bereikbaar.', 'fix' => 'Voeg <code>&lt;add segment="…"/&gt;</code> toe in <code>&lt;hiddenSegments&gt;</code>.'];
}

function cma_doc_check_parent_cma_routes(): array {
    // De CMA friendly-URL rewrite-rules leven in het PARENT
    // web.config (migratie 9.9.0 / de Installer zetten ze daar neer). Daardoor
    // hoeft cma/ GEEN aparte IIS Application meer te zijn — een gewone Virtual
    // Directory volstaat, want de parent-rules matchen op de volledige URL
    // (`^cma/dashboard/?$`). De oude "cma moet een Application zijn"- en
    // "child cma/web.config Dashboard-rule"-checks zijn daarmee vervallen.
    $label = 'Parent web.config: CMA friendly-URL routes';
    $xml = cma_doc_parent_webconfig();
    if ($xml === null) {
        return ['label' => $label, 'status' => 'info', 'detail' => 'Parent <code>web.config</code> niet gevonden in site-root — site draait niet onder IIS of staat ergens anders.', 'fix' => ''];
    }
    $hit = $xml->xpath("//rewrite/rules/rule[@name='CMA Dashboard']");
    if (!empty($hit)) {
        return ['label' => $label, 'status' => 'pass', 'detail' => 'Aanwezig — de CMA-routes (<code>CMA Dashboard</code> e.a.) staan in de parent. <code>/cma/dashboard</code>, <code>/cma/preferences</code>, <code>/cma/tools</code> en de form-routes werken; <code>cma/</code> hoeft GEEN IIS Application te zijn.', 'fix' => ''];
    }
    return ['label' => $label, 'status' => 'fail', 'detail' => 'De CMA-routes ontbreken in de parent <code>web.config</code> — extensionless URLs als <code>/cma/dashboard</code> eindigen in 404.', 'fix' => 'Doe <code>composer update stenversonline/platform</code> (past de routes automatisch + fail-safe toe), of draai migratie <code>9.9.0</code> via <a href="documentation.php?topic=migrations">Migraties</a>.'];
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

    // Skip the live HTTP probe while the search index is being built — it would
    // fire one cURL HEAD per topic on every page load. The visible page render
    // (indexing flag off) still runs the real check.
    if (!empty($GLOBALS['_docs_indexing'])) {
        return ['label' => $label, 'status' => 'info', 'detail' => '', 'fix' => ''];
    }
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
    // curl_close() is a no-op since PHP 8.0 and deprecated since 8.5 — the
    // handle is freed when $ch goes out of scope. Don't call it.

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

/**
 * The IIS major version this site runs on, from SERVER_SOFTWARE
 * ("Microsoft-IIS/10.0"). Null when it isn't IIS or the banner is suppressed.
 * Note this is the version IIS reports to PHP — independent of whether the
 * Server response header is stripped.
 */
function cma_doc_iis_version(): ?float {
    $software = (string)($_SERVER['SERVER_SOFTWARE'] ?? '');
    if (preg_match('~Microsoft-IIS/(\d+(?:\.\d+)?)~i', $software, $m)) {
        return (float)$m[1];
    }
    return null;
}

/**
 * removeServerHeader="true" only exists from IIS 10.0. On an older IIS the
 * config schema doesn't know the attribute and IIS rejects the WHOLE
 * web.config with a 500.19 — the site is down, not just the header showing.
 * So report what this machine actually is instead of leaving it to the reader.
 */
/**
 * What this site actually runs on. Pure fact, no verdict — the portability
 * topic needs it stated rather than assumed.
 */
function cma_doc_check_stack(): array {
    $software = (string)($_SERVER['SERVER_SOFTWARE'] ?? '(onbekend)');
    return ['label' => 'Draaiende stack', 'status' => 'info',
        'detail' => PHP_OS_FAMILY . ' &middot; <code>' . htmlspecialchars($software) . '</code> &middot; PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ')',
        'fix' => ''];
}

/**
 * Which databases.json the runtime actually loaded. A site whose file is
 * missing, unreadable or invalid JSON degrades to the built-in Access paths
 * under /db, and the resulting connection error then names a database nobody
 * configured — so make the loaded source visible before that happens.
 */
function cma_doc_check_db_config_source(): array {
    $label = 'Welke <code>databases.json</code> is geladen?';
    \App\Library\Bootstrap::loadDatabasesConfig();
    $source = (string)($GLOBALS['_db_config_source'] ?? '');
    $root   = rtrim(str_replace('\\', '/', cma_doc_site_root()), '/');
    $shown  = str_replace('\\', '/', $source);
    if ($root !== '' && strpos($shown, $root) === 0) {
        $shown = ltrim(substr($shown, strlen($root)), '/');
    }

    if ($source === '') {
        return ['label' => $label, 'status' => 'fail',
            'detail' => 'Geen bruikbare <code>databases.json</code> gevonden. De site draait op de ingebouwde Access-paden <code>db/main.mdb</code> en <code>db/CMAUsers.mdb</code> (of op de legacy <code>conn_*</code>-globals uit <code>app.php</code>). Elke keuzelijst in de CMA is daarmee leeg, want <code>Cma\ConfigLoader</code> leest uitsluitend <code>data/databases.json</code>.',
            'fix' => 'Maak <code>data/databases.json</code> aan met een entry per connectie (<code>data</code>, <code>users</code>). Staat het bestand er wél, kijk dan in het PHP-errorlog: dat noemt of het onleesbaar was (rechten van de app-pool-identiteit) of geen geldige JSON (UTF-8 BOM, komma te veel).'];
    }
    if (strpos($shown, 'cma/config/') !== false) {
        return ['label' => $label, 'status' => 'warn',
            'detail' => '<code>' . htmlspecialchars($shown) . '</code> — een restant van vóór de verhuizing naar <code>data/</code>. De CMA-schermen lezen dit bestand niet, dus configuratie hier is half onzichtbaar.',
            'fix' => 'Verplaats de entries naar <code>data/databases.json</code> en verwijder <code>cma/config/databases.json</code>.'];
    }
    return ['label' => $label, 'status' => 'pass',
        'detail' => '<code>' . htmlspecialchars($shown) . '</code>',
        'fix' => ''];
}

/**
 * Access connections are the thing that pins this site to Windows — there is
 * no supported Access ODBC driver on Linux. Read the same config Bootstrap
 * reads, so the answer matches what the site really connects to.
 */
function cma_doc_check_db_portability(): array {
    $label = 'Databases: draaien ze ook buiten Windows?';
    try {
        $entries = \App\Library\Bootstrap::loadDatabasesConfig();
    } catch (\Throwable $e) {
        return ['label' => $label, 'status' => 'info', 'detail' => 'Kon <code>databases.json</code> niet lezen.', 'fix' => ''];
    }
    $access = [];
    $other  = [];
    foreach ($entries as $entry) {
        if (!empty($entry['deprecated'])) { continue; }
        $name = (string)($entry['name'] ?? '?');
        $blob = strtolower((string)($entry['type'] ?? '') . ' ' . (string)($entry['connectionString'] ?? ''));
        if (strpos($blob, 'access') !== false || strpos($blob, 'jet.oledb') !== false || strpos($blob, '.mdb') !== false || strpos($blob, '.accdb') !== false) {
            $access[] = $name;
        } else {
            $other[] = $name;
        }
    }
    if ($access === []) {
        return ['label' => $label, 'status' => 'pass',
            'detail' => 'Geen Access-verbindingen' . ($other === [] ? '.' : ' — alleen ' . htmlspecialchars(implode(', ', $other)) . '.'),
            'fix' => ''];
    }
    return ['label' => $label, 'status' => 'warn',
        'detail' => 'Access-verbinding(en): <code>' . htmlspecialchars(implode('</code>, <code>', $access)) . '</code>. Die werken alleen op Windows — er is geen ondersteunde Access-ODBC-driver op Linux.',
        'fix' => 'Wil je naar Linux, migreer deze databases eerst naar MySQL of SQL Server. De verbindingslaag ondersteunt die al.'];
}

function cma_doc_check_iis_version(): array {
    $label = 'IIS-versie — ondersteunt <code>removeServerHeader</code>?';
    $software = (string)($_SERVER['SERVER_SOFTWARE'] ?? '');
    $version  = cma_doc_iis_version();

    if ($version === null) {
        return ['label' => $label, 'status' => 'info',
            'detail' => $software === '' ? 'Serverversie onbekend (geen <code>SERVER_SOFTWARE</code>).' : 'Draait niet op IIS: <code>' . htmlspecialchars($software) . '</code>.',
            'fix' => ''];
    }
    if ($version >= 10) {
        return ['label' => $label, 'status' => 'pass',
            'detail' => '<code>' . htmlspecialchars($software) . '</code> — <code>removeServerHeader="true"</code> wordt hier ondersteund.',
            'fix' => ''];
    }
    return ['label' => $label, 'status' => 'fail',
        'detail' => '<code>' . htmlspecialchars($software) . '</code> — deze IIS kent <code>removeServerHeader</code> <span class="cma-tool__strong">niet</span>. Staat het attribuut in <code>web.config</code>, dan weigert IIS het hele bestand met <code>500.19</code> en is de site onbereikbaar.',
        'fix' => 'Haal <code>removeServerHeader</code> uit <code>&lt;requestFiltering&gt;</code> en gebruik de outbound rewrite of de registrykey — zie <a href="documentation.php?topic=security">Beveiliging</a>.'];
}

/**
 * PHP sets X-Powered-By itself when expose_php=On; the <remove> in web.config
 * only strips headers IIS adds, so it sails straight through and publishes the
 * exact PHP version.
 */
function cma_doc_check_powered_by(): array {
    $label = 'X-Powered-By wordt niet verstuurd';
    if (ini_get('expose_php')) {
        return ['label' => $label, 'status' => 'warn',
            'detail' => '<code>expose_php</code> staat aan — elke response draagt <code>X-Powered-By: PHP/' . htmlspecialchars(PHP_VERSION) . '</code>.',
            'fix' => 'Zet <code>expose_php = Off</code> in php.ini en recycle de app-pool. Het <code>&lt;remove name="X-Powered-By"/&gt;</code> in <code>web.config</code> haalt deze header niet weg — die komt van PHP, niet van IIS.'];
    }
    return ['label' => $label, 'status' => 'pass', 'detail' => '<code>expose_php</code> staat uit.', 'fix' => ''];
}

/**
 * Does this server actually strip the Server header? Both mechanisms the docs
 * describe are version-dependent, so ask the site instead of assuming:
 *   - removeServerHeader="true" needs IIS 10.0+; older IIS doesn't know the
 *     attribute at all.
 *   - the outbound rewrite of RESPONSE_Server needs URL Rewrite, and only
 *     reaches responses that pass through the managed pipeline.
 */
function cma_doc_check_server_header(): array {
    $label = 'Server-header wordt verwijderd (fingerprinting)';

    if (!empty($GLOBALS['_docs_indexing'])) {
        return ['label' => $label, 'status' => 'info', 'detail' => '', 'fix' => ''];
    }
    if (PHP_SAPI === 'cli') {
        return ['label' => $label, 'status' => 'info', 'detail' => 'CLI-context: zelf-test niet uitvoerbaar.', 'fix' => ''];
    }
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '' || !function_exists('curl_init')) {
        return ['label' => $label, 'status' => 'info', 'detail' => 'Host onbekend of cURL niet geladen — zelf-test overgeslagen.', 'fix' => ''];
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $ch = curl_init($scheme . '://' . $host . '/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY         => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $raw = curl_exec($ch);
    if (curl_errno($ch) !== 0) {
        return ['label' => $label, 'status' => 'info', 'detail' => 'cURL-fout bij zelf-test: ' . htmlspecialchars(curl_error($ch)) . '.', 'fix' => ''];
    }

    $server = '';
    foreach (preg_split('/\r?\n/', (string)$raw) as $line) {
        if (stripos($line, 'Server:') === 0) { $server = trim(substr($line, 7)); break; }
    }

    if ($server === '') {
        return ['label' => $label, 'status' => 'pass', 'detail' => 'Geen <code>Server</code>-header in het antwoord op <code>/</code>.', 'fix' => ''];
    }
    if (stripos($server, 'Microsoft-IIS') === false) {
        return ['label' => $label, 'status' => 'pass', 'detail' => '<code>Server: ' . htmlspecialchars($server) . '</code> — geen IIS-versie zichtbaar.', 'fix' => ''];
    }
    return [
        'label'  => $label,
        'status' => 'warn',
        'detail' => 'De server verstuurt nog <code>Server: ' . htmlspecialchars($server) . '</code> — versie en webserver zijn dus af te lezen. <code>removeServerHeader</code> werkt hier niet (of ontbreekt).',
        'fix'    => 'Zie <a href="documentation.php?topic=security">Beveiliging → Server-header</a> voor het alternatief per IIS-versie.',
    ];
}

/**
 * The outbound Content-Type rule belongs in the PARENT config only. IIS merges
 * outboundRules from parent into child, and two rules sharing a name blow up
 * the whole /cma tree with a 500.50 — so the child carrying it is the defect,
 * not the child missing it.
 */
function cma_doc_check_child_default_content_type(): array {
    $label = 'Child cma/web.config: géén dubbele outbound "Default Content-Type" rule';
    $xml = cma_doc_child_webconfig();
    if ($xml === null) {
        return ['label' => $label, 'status' => 'fail', 'detail' => 'Child <code>cma/web.config</code> niet gevonden.', 'fix' => 'Doe <code>composer update stenversonline/platform</code>.'];
    }
    $inChild  = !empty($xml->xpath("//rewrite/outboundRules/rule[@name='Default Content-Type to text/html']"));
    $parent   = cma_doc_parent_webconfig();
    $inParent = $parent !== null && !empty($parent->xpath("//rewrite/outboundRules/rule[@name='Default Content-Type to text/html']"));

    if ($inChild && $inParent) {
        return ['label' => $label, 'status' => 'fail',
            'detail' => 'De regel staat in <span class="cma-tool__strong">beide</span> configs. IIS merget outbound rules van parent naar child; twee rules met dezelfde naam geven een <code>500.50 URL Rewrite Module Error</code> op de hele <code>/cma</code>-tree.',
            'fix' => 'Haal het <code>&lt;outboundRules&gt;</code>-blok uit <code>cma/web.config</code> — de parent hoort de enige plek te zijn.'];
    }
    if ($inChild) {
        return ['label' => $label, 'status' => 'warn',
            'detail' => 'De regel staat in de child-config maar niet in de parent. Dat werkt, maar zodra de parent hem ook krijgt (migratie 9.9.0, of de fix-knop op deze pagina) botsen de namen en volgt een 500.50.',
            'fix' => 'Verplaats de regel naar de site-root <code>web.config</code>.'];
    }
    if ($inParent) {
        return ['label' => $label, 'status' => 'pass', 'detail' => 'Correct: alleen in de parent, niet in de child.', 'fix' => ''];
    }
    return ['label' => $label, 'status' => 'warn',
        'detail' => 'De regel staat nergens — een response zonder Content-Type wordt door iOS Safari als download aangeboden.',
        'fix' => 'Voeg hem toe aan de <span class="cma-tool__strong">parent</span> web.config (zie de knop bij de parent-check hierboven).'];
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
    $root  = cma_doc_site_root();
    // Same order Bootstrap::detectAndLoadEnv() loops the legacy map (L→O→T→A→P).
    $legacy = ['.env.local', '.env.development', '.env.test', '.env.acceptance', '.env.production'];

    // Prefer the file the bootstrap actually loaded. If that global wasn't
    // exposed, re-derive it the SAME way Bootstrap::detectAndLoadEnv does in
    // the single-.env model: a plain .env wins; otherwise the first-existing
    // legacy per-environment file (kept only as fallback for un-migrated boxes).
    $envName = (string)($GLOBALS['_env_file'] ?? '');
    if ($envName === '') {
        if (is_file($root . '/.env')) {
            $envName = '.env';
        } else {
            foreach ($legacy as $cand) {
                if (is_file($root . '/' . $cand)) { $envName = $cand; break; }
            }
            if ($envName === '') { $envName = '.env'; } // genuine fallback
        }
    }

    $envPath = $root . '/' . $envName;
    if (is_file($envPath) && is_readable($envPath)) {
        return ['label' => $label, 'status' => 'pass', 'detail' => '<code>' . htmlspecialchars($envName) . '</code> aanwezig + leesbaar.', 'fix' => ''];
    }
    if (is_file($envPath)) {
        return ['label' => $label, 'status' => 'fail', 'detail' => '<code>' . htmlspecialchars($envName) . '</code> bestaat maar is niet leesbaar voor de IIS-user.', 'fix' => 'Geef leesrechten aan de application-pool identity.'];
    }
    // Geen env-bestand is géén fatale fout: EnvFile::load() geeft dan een lege
    // array terug en alles valt terug op defaults. Meld dus wat je kwijt bent,
    // niet dat de site stuk is — want dat is hij niet.
    return ['label' => $label, 'status' => 'warn',
        'detail' => 'Geen env-bestand op <code>' . htmlspecialchars($root) . '</code> (gezocht: <code>.env</code>, dan legacy <code>.env.local</code>/<code>.env.development</code>/<code>.env.test</code>/<code>.env.acceptance</code>/<code>.env.production</code>). De site draait door op defaults — databaseverbindingen komen uit <code>databases.json</code>, niet hieruit. Wat je wél mist: <code>APP_ENVIRONMENT</code> (dus stille productie-modus, zie de volgende regel), <code>DEPLOY_SECRET</code> (webhook antwoordt 503), en de sleutels voor LLM/OCR, SQL-logging en de profiler.',
        'fix' => 'Alleen nodig als je een van die functies gebruikt. Kopieer dan <code>.env.example</code> naar <code>.env</code> op de site-root.'];
}

function cma_doc_check_app_environment_match(): array {
    // Single-.env model: APP_ENVIRONMENT is a variable INSIDE the loaded file,
    // not a file selector — so there's no file to "match" anymore. Just report
    // the resolved value and which file it came from.
    $label = 'APP_ENVIRONMENT';
    $envName = (string)($GLOBALS['_env_file'] ?? '.env');
    $appEnv  = (string)($GLOBALS['_app_env'] ?? '');
    // $_env_file is de naam die Bootstrap KOOS, niet het bewijs dat hij bestaat.
    // Zonder die controle beweert deze regel "gelezen uit .env" terwijl de check
    // erboven meldt dat er geen env-bestand is.
    $envExists = is_file(cma_doc_site_root() . '/' . $envName);
    $bron = $envExists
        ? 'gelezen uit <code>' . htmlspecialchars($envName) . '</code>'
        : 'er is <span class="cma-tool__strong">geen</span> env-bestand, dus dit is de ingebouwde default';

    if ($appEnv === '' || $appEnv === 'P') {
        $shown = $appEnv === '' ? 'niet gezet → aangenomen P (productie)' : 'P (productie)';
        return ['label' => $label, 'status' => 'info', 'detail' => '<code>' . htmlspecialchars($shown) . '</code> — ' . $bron . '. Errors staan UIT op de pagina (wel gelogd; forceer met <code>FORCE_DEBUG=1</code>).', 'fix' => ''];
    }
    return ['label' => $label, 'status' => 'info', 'detail' => '<code>APP_ENVIRONMENT=' . htmlspecialchars($appEnv) . '</code> — ' . $bron . ' → non-prod, verbose errors AAN.', 'fix' => ''];
}

function cma_doc_check_deploy_secret(): array {
    // Read DEPLOY_SECRET length WITHOUT echoing the value. Privacy.
    $label = 'DEPLOY_SECRET';
    $secret = (string)(getenv('DEPLOY_SECRET') ?: ($_ENV['DEPLOY_SECRET'] ?? ''));
    if ($secret === '') {
        // Belt-and-braces: scan the .env directly in case the value wasn't exposed to getenv()
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
    return cma_doc_check_dir_writable('.logs', '.logs/ — deploy/, perf/, phperrors/');
}

function cma_doc_check_data_logs_dir(): array {
    return cma_doc_check_dir_writable('.logs', '.logs/ — app/performance/debug logs', 'warn');
}

function cma_doc_check_cache_dir(): array {
    return cma_doc_check_dir_writable('cache', 'cache/ — OpCache + form-cache', 'warn');
}

/** Bytes leesbaar maken voor de check-tabellen. */
function cma_doc_format_bytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) { $bytes = (int) round($bytes / 1024); $i++; }
    return $bytes . ' ' . $units[$i];
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
    $detail = '<code>' . htmlspecialchars($cfg) . '</code> schrijfbaar.';
    // Dagbestanden erbij: dan zie je meteen of de rotatie loopt en hoeveel er ligt.
    $daily = glob(dirname($cfg) . '/php_errors_*.log') ?: [];
    if ($daily !== []) {
        $bytes = 0;
        foreach ($daily as $file) { $bytes += (int) @filesize($file); }
        $detail .= ' Rotatie actief: ' . count($daily) . ' dagbestand(en), samen ' . cma_doc_format_bytes($bytes) . '.';
    }
    $legacy = dirname($cfg) . '/php_errors.log';
    if (is_file($legacy) && @filesize($legacy) > 50 * 1024 * 1024) {
        $omvang = cma_doc_format_bytes((int) @filesize($legacy));
        // Onderscheid het geval waarin dit bestand nog de LEVENDE bestemming is: dan
        // is de omvang geen restant maar een lopend probleem. Elke notice is een
        // schrijfactie, dus een pagina die er duizenden per render produceert betaalt
        // dat in schijf-I/O — dat is een prestatieprobleem, niet alleen rommel.
        $live = @realpath($cfg) === @realpath($legacy);
        if ($live) {
            return ['label' => $label, 'status' => 'warn',
                'detail' => $detail . ' Dit bestand is ' . $omvang . ' groot en is de actieve bestemming: er is geen dagrotatie, dus het blijft groeien.',
                'fix' => 'Zet dagrotatie aan (dan schrijft het platform naar <code>php_errors_&lt;datum&gt;.log</code>) en archiveer dit bestand. '
                    . 'Groeit het snel, kijk dan eerst WAT er in staat: een herhaalde notice (bijvoorbeeld "Undefined property") '
                    . 'kost bij elke request schijf-I/O en maakt de pagina traag.'];
        }
        return ['label' => $label, 'status' => 'warn',
            'detail' => $detail . ' Daarnaast ligt er nog een ongeroteerd <code>php_errors.log</code> van ' . $omvang . '.',
            'fix' => 'De rotatie schrijft er niet meer naartoe; verwijder of archiveer het handmatig.'];
    }
    return ['label' => $label, 'status' => 'pass', 'detail' => $detail, 'fix' => ''];
}

function cma_doc_check_vendor_in_sync(): array {
    $label = 'Platform versie (uit composer.json)';
    if (!class_exists('\\App\\Library\\Bootstrap')) {
        return ['label' => $label, 'status' => 'warn', 'detail' => 'Bootstrap-class niet geladen.', 'fix' => ''];
    }
    // getPlatformVersion() leest het "version"-veld uit de eigen
    // composer.json van het pakket (gecached), dus er is niets meer om te laten
    // afwijken. We tonen de gedetecteerde versie en waarschuwen alleen als de
    // fallback-constante gebruikt wordt (composer.json onleesbaar).
    $detected = \App\Library\Bootstrap::getPlatformVersion();
    $pkgComposer = \App\Library\Bootstrap::getRootDir() . '/vendor/stenversonline/platform/composer.json';
    $composerVer = '';
    if (is_file($pkgComposer)) {
        $data = json_decode((string)file_get_contents($pkgComposer), true);
        $composerVer = is_array($data) ? (string)($data['version'] ?? '') : '';
    }
    if ($composerVer === '' && $detected === \App\Library\Bootstrap::VERSION) {
        return ['label' => $label, 'status' => 'warn', 'detail' => 'composer.json onleesbaar; fallback-constante <code>' . htmlspecialchars($detected) . '</code> in gebruik.', 'fix' => 'Controleer <code>vendor/stenversonline/platform/composer.json</code>.'];
    }
    return ['label' => $label, 'status' => 'pass', 'detail' => 'Versie: <code>' . htmlspecialchars($detected) . '</code> (uit composer.json).', 'fix' => ''];
}

/**
 * Every `$schema` reference in the site's JSON config must resolve.
 *
 * A JSON `$schema` value is relative to the referencing file's own directory,
 * so a definition that moves between assets/forms/ and cma/assets/forms/
 * definitions/ needs a different number of ../ steps. When it is wrong the
 * editor silently drops validation and IntelliSense — nothing warns, so this
 * is exactly the kind of drift that only a live check surfaces.
 */
function cma_doc_check_json_schema_refs(): array {
    $label = '$schema-verwijzingen in JSON-config';
    $root = cma_doc_site_root();
    $schemaDir = $root . '/cma/config/schema';
    if (!is_dir($schemaDir)) {
        return ['label' => $label, 'status' => 'warn', 'detail' => '<code>cma/config/schema/</code> ontbreekt.', 'fix' => 'Draai <code>composer update stenversonline/platform</code>.'];
    }

    $broken = [];
    $checked = 0;
    foreach (['data', 'assets/forms', 'assets/datastores', 'assets/contentblocks', 'cma', 'cma/config', 'cma/assets/forms/definitions', 'cma/assets/contentblocks'] as $relDir) {
        foreach (glob($root . '/' . $relDir . '/*.json') ?: [] as $path) {
            $src = (string)file_get_contents($path);
            if (!preg_match('/"\$schema"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/', $src, $m)) {
                continue;
            }
            if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $m[1])) {
                continue;
            }
            $checked++;
            // The reference must land ON a schema, not merely on a file that
            // happens to exist: a `$schema` pointing at an ordinary JSON
            // document resolves fine and validates nothing.
            $target = realpath(dirname($path) . '/' . $m[1]);
            if ($target === false || dirname($target) !== realpath($schemaDir)) {
                $broken[] = $relDir . '/' . basename($path);
            }
        }
    }

    if ($checked === 0) {
        return ['label' => $label, 'status' => 'info', 'detail' => 'Geen JSON-bestanden met een lokale <code>$schema</code> gevonden.', 'fix' => ''];
    }
    if ($broken === []) {
        return ['label' => $label, 'status' => 'pass', 'detail' => $checked . ' verwijzing(en) gecontroleerd, alle oplosbaar.', 'fix' => ''];
    }

    $sample = array_slice($broken, 0, 5);
    $rest = count($broken) - count($sample);
    return [
        'label' => $label,
        'status' => 'fail',
        'detail' => count($broken) . ' van ' . $checked . ' verwijzing(en) wijzen naar een niet-bestaand schema: <code>'
            . implode('</code>, <code>', array_map('htmlspecialchars', $sample)) . '</code>'
            . ($rest > 0 ? ' (+' . $rest . ' meer)' : '') . '.',
        'fix' => 'Draai de openstaande <a href="tools_migrations.php">migraties</a> — die herberekenen elke verwijzing vanuit de map van het bestand zelf.',
    ];
}

/**
 * Elke Installer-handler in composer.json moet in de GEÏNSTALLEERDE vendor bestaan.
 *
 * De `pre-*-cmd`-scripts draaien vóór de update, dus tegen de code die er nu staat — niet
 * tegen de versie die de update zou binnenhalen. Verwijst composer.json naar een methode
 * die de geïnstalleerde versie nog niet kent, dan weigert Composer met "Method … is not
 * callable, can not call pre-update-cmd script" en kán de update die de methode meebrengt
 * dus nooit draaien. Zonder deze controle merk je dat pas op het moment dat je wilt
 * bijwerken.
 */
function cma_doc_check_composer_script_handlers(): array {
    $label = 'composer.json: Installer-handlers bestaan';
    $root = cma_doc_site_root();
    $composer = $root . '/composer.json';
    if (!is_file($composer)) {
        return ['label' => $label, 'status' => 'warn', 'detail' => 'composer.json niet gevonden.', 'fix' => ''];
    }
    $data = json_decode((string)file_get_contents($composer), true);
    $scripts = is_array($data) ? ($data['scripts'] ?? []) : [];
    if (!is_array($scripts) || $scripts === []) {
        return ['label' => $label, 'status' => 'info', 'detail' => 'Geen scripts in composer.json.', 'fix' => ''];
    }

    $ontbreekt = [];
    $gezien = 0;
    foreach ($scripts as $hook => $handlers) {
        foreach ((array)$handlers as $handler) {
            if (!is_string($handler) || strpos($handler, 'App\\Library\\Installer::') !== 0) {
                continue;
            }
            $gezien++;
            $methode = substr($handler, strlen('App\\Library\\Installer::'));
            if (!method_exists('\\App\\Library\\Installer', $methode)) {
                $ontbreekt[] = $hook . ' → ' . $methode . '()';
            }
        }
    }

    if ($gezien === 0) {
        return ['label' => $label, 'status' => 'info', 'detail' => 'composer.json verwijst niet naar de Installer.', 'fix' => ''];
    }
    if ($ontbreekt === []) {
        return ['label' => $label, 'status' => 'pass',
            'detail' => $gezien . ' handler(s) gecontroleerd, alle aanwezig in de geïnstalleerde versie ('
                . htmlspecialchars(\App\Library\Bootstrap::getPlatformVersion()) . ').', 'fix' => ''];
    }
    return [
        'label' => $label,
        'status' => 'fail',
        'detail' => 'Onbekend in de geïnstalleerde versie ('
            . htmlspecialchars(\App\Library\Bootstrap::getPlatformVersion()) . '): <code>'
            . implode('</code>, <code>', array_map('htmlspecialchars', $ontbreekt)) . '</code>.',
        'fix' => 'Een <code>pre-*-cmd</code> hiervan blokkeert <code>composer update</code> volledig. '
            . 'Haal die regel tijdelijk uit <code>composer.json</code>, draai de update, en zet hem terug '
            . '— daarna kent de vendor de methode wel.',
    ];
}

function cma_doc_check_deploy_log(): array {
    $label = '.logs/deploy/deploy.log';
    $path = cma_doc_site_root() . '/.logs/deploy/deploy.log';
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
            // Carry the check's own name so the renderer can pair it with a
            // fixer (see cma_doc_fixers) and draw a "Los op"-button.
            $r['check'] = $fn;
            $results[] = $r;
        }
    }
    return $results;
}


function cma_doc_render_check_table(string $title, array $results): void {
    // During search indexing we don't want the live-status table in the index —
    // only the section title (so the heading stays findable). The cheap checks
    // already ran as args; the expensive cURL probe self-skips (see above).
    if (!empty($GLOBALS['_docs_indexing'])) {
        echo ' ' . htmlspecialchars($title) . ' ';
        return;
    }
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
    <?php if (!cma_doc_fixers_available()): ?>
        <lib-message type="warning">
            <code>cma/tools/documentation_fixes.inc</code> is niet geladen — de "Los op"-knoppen ontbreken hieronder.
            Dit betekent dat de platformbestanden op deze site niet bij elkaar horen: draai
            <code>composer update stenversonline/platform</code> opnieuw en herlaad deze pagina.
            De checks zelf kloppen wel; je kunt de fixes handmatig doorvoeren.
        </lib-message>
    <?php endif; ?>
    <table class="listtable">
        <thead><tr class="listheader"><th>Check</th><th style="width:110px">Status</th><th>Detail</th><th>Fix</th></tr></thead>
        <tbody>
            <?php $fixers = cma_doc_fixers_available() ? cma_doc_fixers() : []; ?>
            <?php foreach ($results as $r): ?>
                <?php $fixer = ($r['status'] === 'fail' || $r['status'] === 'warn') ? ($fixers[$r['check'] ?? ''] ?? null) : null; ?>
                <tr>
                    <td><?= $r['label'] ?></td>
                    <td><lib-label type="<?= $labelType[$r['status']] ?? 'information' ?>"><?= $statusText[$r['status']] ?? '?' ?></lib-label></td>
                    <td><?= $r['detail'] ?></td>
                    <td>
                        <?= $r['fix'] !== '' ? $r['fix'] : '—' ?>
                        <?php if ($fixer !== null): ?>
                            <button type="button" class="btn btn-primary docs-fix-btn" data-fix="<?= htmlspecialchars($r['check']) ?>"><?= htmlspecialchars($fixer['label']) ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function cma_doc_check_php_version(): array {
    $label = 'PHP-versie';
    if (version_compare(PHP_VERSION, '8.4', '>=')) {
        return ['label' => $label, 'status' => 'pass', 'detail' => 'PHP ' . PHP_VERSION . '.', 'fix' => ''];
    }
    return ['label' => $label, 'status' => 'fail', 'detail' => 'PHP ' . PHP_VERSION . ' — het platform vereist 8.4 of hoger.', 'fix' => 'Upgrade de FastCGI-handler naar PHP 8.4+.'];
}

/**
 * The extensions the platform actually calls into. mbstring is hard-required
 * (App\Library\Str is mb_* end to end); the rest degrade a feature, not the app.
 */
function cma_doc_check_php_extensions(): array {
    $label = 'PHP-extensies';
    $required = ['mbstring'];
    $optional = ['gd' => 'beeldbewerking/WebP', 'openssl' => 'encryptie + secrets', 'curl' => 'externe HTTP-calls', 'fileinfo' => 'MIME-detectie bij downloads; zonder deze extensie valt het platform terug op een extensie-tabel'];

    $missingRequired = array_values(array_filter($required, fn($e) => !extension_loaded($e)));
    if ($missingRequired !== []) {
        return ['label' => $label, 'status' => 'fail',
            'detail' => 'Ontbreekt: <code>' . implode('</code>, <code>', $missingRequired) . '</code>. Zonder <code>mbstring</code> faalt elke <code>App\Library\Str</code>-aanroep met <code>Call to undefined function mb_*</code>.',
            'fix' => 'Activeer <code>extension=mbstring</code> in php.ini en recycle de app-pool.'];
    }
    // Access needs one of the two ODBC drivers, not both.
    if (!extension_loaded('odbc') && !extension_loaded('pdo_odbc')) {
        return ['label' => $label, 'status' => 'fail',
            'detail' => 'Geen ODBC-driver: noch <code>odbc</code> noch <code>pdo_odbc</code> is geladen — Access-databases zijn onbereikbaar.',
            'fix' => 'Activeer <code>extension=odbc</code> (of <code>pdo_odbc</code>) in php.ini.'];
    }
    $missingOptional = [];
    foreach ($optional as $ext => $why) {
        if (!extension_loaded($ext)) { $missingOptional[] = '<code>' . $ext . '</code> (' . $why . ')'; }
    }
    if ($missingOptional !== []) {
        return ['label' => $label, 'status' => 'warn',
            'detail' => 'Verplichte extensies aanwezig; optioneel ontbreekt: ' . implode(', ', $missingOptional) . '.',
            'fix' => 'Activeer de betreffende <code>extension=</code>-regels in php.ini als je die functionaliteit gebruikt.'];
    }
    return ['label' => $label, 'status' => 'pass', 'detail' => 'mbstring, ODBC, gd, openssl en curl zijn geladen.', 'fix' => ''];
}

function cma_doc_check_webp_gd(): array {
    $label = 'GD WebP-ondersteuning';
    if (!extension_loaded('gd')) {
        return ['label' => $label, 'status' => 'fail', 'detail' => 'PHP <code>gd</code>-extensie niet geladen — geen beeldbewerking mogelijk.', 'fix' => 'Activeer <code>extension=gd</code> in php.ini.'];
    }
    $ok = class_exists('\\App\\Library\\Image') ? \App\Library\Image::isWebPSupported() : function_exists('imagewebp');
    if ($ok) {
        return ['label' => $label, 'status' => 'pass', 'detail' => 'GD kan WebP schrijven (<code>imagewebp()</code> beschikbaar).', 'fix' => ''];
    }
    return ['label' => $label, 'status' => 'warn', 'detail' => 'GD mist WebP-ondersteuning — uploads vallen terug op JPEG.', 'fix' => 'Installeer een GD-build met WebP (libwebp).'];
}

function cma_doc_check_cwebp(): array {
    $label = 'cwebp CLI (optioneel)';
    $ok = class_exists('\\App\\Library\\Image') ? \App\Library\Image::isCwebpAvailable() : false;
    if ($ok) {
        return ['label' => $label, 'status' => 'pass', 'detail' => '<code>cwebp</code> gevonden — conversies behouden ICC-kleurprofielen.', 'fix' => ''];
    }
    return ['label' => $label, 'status' => 'info', 'detail' => '<code>cwebp</code> niet gevonden — GD doet de conversie (ICC-profielen worden gestript; klein kleurverschil mogelijk).', 'fix' => 'Optioneel: installeer libwebp/<code>cwebp</code> voor kleur-accurate conversie.'];
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
            <tr><td><a href="documentation.php?topic=deployment"><span class="lnr lnr-rocket"></span> Deployment</a></td><td>Hoe <code>/deploy.php</code> werkt, alle DEPLOY_* env-vars, link naar deploy-log.</td></tr>
            <tr><td><a href="documentation.php?topic=backups"><span class="lnr lnr-database"></span> Backups &amp; herstel</a></td><td>BackupService, waar backups leven, SQLite emergency-recovery.</td></tr>
            <tr><td><a href="documentation.php?topic=logs"><span class="lnr lnr-list"></span> Logs &amp; monitoring</a></td><td>Welke log waar ligt, logreader, cmamonitoring, retentie.</td></tr>
            <tr><td><a href="documentation.php?topic=security"><span class="lnr lnr-lock"></span> Beveiliging</a></td><td>Secrets, sessie-cookies, geblokkeerde extensies, hiddenSegments.</td></tr>
            <tr><td><a href="documentation.php?topic=iis_config"><span class="lnr lnr-server"></span> IIS-configuratie</a></td><td>web.config layering, URL Rewrite Module, app-pool recycle.</td></tr>
            <tr><td><a href="documentation.php?topic=portability"><span class="lnr lnr-sync"></span> Portabiliteit (niet-IIS)</a></td><td>Wat er vastzit aan IIS en Windows, en wat het kost om naar Apache of Linux te gaan.</td></tr>
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
            <tr><td><a href="documentation.php?topic=formval"><span class="lnr lnr-checkmark-circle"></span> Formuliervalidatie (front-end)</a></td><td>form_valid(), de foutensamenvatting bovenaan het formulier, data-attributen per veld, de min-bundel die de site laadt.</td></tr>
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

    <h2>Site-eigen documentatie</h2>
    <p>Een consumer-site kan zijn eigen hoofdstukken meeleveren. Zijn die
       aanwezig, dan splitst de boom hierboven in twee takken: <span class="cma-page__strong">CMA</span>
       (deze documentatie) en <span class="cma-page__strong">Front-end</span> (de site). Zonder
       site-documentatie blijft de boom ongewijzigd.</p>
    <p>De site levert het aan als data, niet als code — dit platform kent de inhoud niet:</p>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:280px">Bestand in de site</th><th>Functie</th></tr></thead>
        <tbody>
            <tr><td><code>doc/documentation.json</code></td><td>Hoofdstukregistratie: <code>slug</code>, <code>label</code>, <code>icon</code>, <code>parent</code>, <code>file</code>, <code>order</code>. Per site verschillend.</td></tr>
            <tr><td><code>doc/chapters/*.html</code></td><td>De inhoud, als HTML-<span class="cma-page__em">fragment</span> (geen <code>&lt;html&gt;</code>/<code>&lt;body&gt;</code>). Dezelfde opmaakregels gelden: geen <code>&lt;strong&gt;</code>/<code>&lt;em&gt;</code>, wel <code>cma-page__strong</code> / <code>cma-page__em</code>.</td></tr>
            <tr><td><code>src/includes/site_documentation.inc</code></td><td>De lezer die dit platform aanroept: <code>site_doc_tree()</code>, <code>site_doc_chapters_for_parent()</code>, <code>site_doc_render()</code>.</td></tr>
        </tbody>
    </table>
    <p>Een hoofdstuk kan met <code>parent</code> ook in een bestaande CMA-tak gehangen
       worden (<code>admin</code>, <code>dev</code>, <code>reference</code>). De slug-namespace is
       vlak: botst een site-slug met een CMA-topic, dan wint het CMA-topic en wordt het
       site-hoofdstuk overgeslagen met een regel in het errorlog.</p>

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
    <?php
    cma_doc_render_check_table('Vereisten — live check op deze site', cma_doc_run_checks([
        'cma_doc_check_php_version',
        'cma_doc_check_php_extensions',
        'cma_doc_check_composer_script_handlers',
    ]));
    ?>

    <h2>Vereisten</h2>
    <ul>
        <li>Windows Server (of Windows 10/11) met IIS geïnstalleerd.</li>
        <li><span class="cma-tool__strong">IIS URL Rewrite Module</span> — download van <a href="https://www.iis.net/downloads/microsoft/url-rewrite" target="_blank" rel="noopener">iis.net</a>. Zonder deze module worden alle friendly-URL rewrites in <code>web.config</code> stilzwijgend genegeerd.</li>
        <li>PHP 8.4+ via FastCGI handler (PHP 8.5 wordt ondersteund maar test consumer-code op nieuwe deprecaties).</li>
        <li>PHP-extensies: <code>mbstring</code> (verplicht — <code>App\Library\Str</code> draait volledig op <code>mb_*</code>), <code>gd</code> (beeldbewerking/WebP), <code>odbc</code> of <code>pdo_odbc</code> (Access), <code>openssl</code>, <code>curl</code>, <code>fileinfo</code> (MIME-detectie bij downloads). Controleer met <code>php -m</code>, of lees de check-tabel bovenaan deze pagina.</li>
        <li>Composer 2.x in het <code>PATH</code> van de IIS app-pool user (anders faalt <code>DEPLOY_COMPOSER_UPDATE</code> stilzwijgend tijdens deploys).</li>
        <li>Git voor pull-deploys.</li>
    </ul>

    <h2>Server-level: server variables ontgrendelen</h2>
    <p>De <span class="cma-tool__strong">site-root</span> <code>web.config</code> (uit <code>web.config.template</code>) zet custom server variables (<code>HTTP_X_ORIGINAL_FILE</code>, <code>HTTP_X_TOOL_NAME</code>) in z'n friendly-URL rewrites — die rewrites routen via <code>_bootstrap_wrapper.php</code> dat de variabele weer uitleest. IIS lockt de <code>allowedServerVariables</code>-sectie standaard op server-niveau af, dus eenmalig als Administrator uitvoeren:</p>
    <p class="docs-meta"><code>cma/web.config</code> zelf gebruikt deze variabelen <span class="cma-tool__strong">niet</span> (meer) — die rewrites gaan rechtstreeks naar <code>cma/main.php?page=…</code> zonder server-variabelen, zodat <code>/cma</code>-routing werkt zonder deze unlock. De unlock is dus alleen nodig voor de routing van de consumer-app zelf in de parent <code>web.config</code>.</p>
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
        "pre-install-cmd":  "App\\Library\\Installer::preOperation",
        "pre-update-cmd":   "App\\Library\\Installer::preOperation",
        "post-install-cmd": "App\\Library\\Installer::postInstall",
        "post-update-cmd":  "App\\Library\\Installer::postUpdate"
    }
}</code></pre>
            De <code>scripts</code> sectie is cruciaal — zonder die hooks draait de Installer niet en blijven <code>cma/</code>, <code>library/</code> bestanden achter in <code>vendor/</code> in plaats van naar de project-root gesynct te worden. De twee <code>pre-</code>hooks zetten de <a href="documentation.php?topic=deploy">onderhoudspagina</a> aan zolang de bestanden verwisseld worden.
        </li>
        <li>
            <div class="docs-callout docs-callout--warn">
                <span class="cma-tool__strong">Voeg een <code>pre-*-cmd</code> pas toe als de geïnstalleerde versie die methode kent.</span>
                Een <code>pre-</code>hook draait vóór de update, dus tegen de code die er op dat moment staat — niet tegen de versie die de update zou binnenhalen. Verwijst <code>composer.json</code> naar een methode die de huidige vendor nog niet heeft, dan stopt Composer met:
                <pre><code>Method App\Library\Installer::preOperation is not callable,
can not call pre-update-cmd script</code></pre>
                en kan juist de update die de methode meebrengt niet draaien. Eruit komen: haal die ene regel weg, draai <code>composer update</code>, zet hem terug. De check-tabel bovenaan deze pagina controleert dit vooraf.
            </div>
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
            <tr><td><code>.logs/</code></td><td>app-log, performance-log (in <code>.logs/perf/</code>), debug-log. Wordt door Logger/PerformanceLogger/api-log.php gecreëerd indien afwezig, maar de IIS-user heeft schrijfrechten op de parent <code>data/</code> nodig.</td></tr>
            <tr><td><code>cache/</code></td><td>cache.log, OpCache-files, form-definition cache, perf-log oude locatie waar logreader nog naar zoekt.</td></tr>
            <tr><td><code>.logs/404/</code></td><td>404 log; oude locatie waar logreader nog naar debug-logs zoekt.</td></tr>
            <tr><td><code>cma/</code></td><td>Installer kopieert hier nieuwe bestanden naartoe.</td></tr>
            <tr><td><code>library/</code></td><td>Installer-sync target.</td></tr>
            <tr><td><code>module/</code></td><td>Installer-sync target.</td></tr>
            <tr><td><code>web.config</code></td><td>App-pool recycle via touch tijdens deploys.</td></tr>
            <tr><td><code>db/</code> (bij SQLite)</td><td>SQLite database write, WAL/SHM files.</td></tr>
            <tr><td><code>.sessions/</code></td><td>PHP-session files (een dot-map; een bestaande <code>sessions/</code>-map wordt bij de eerste bootstrap automatisch hernoemd). Opschoning: PHP's eigen GC draait sinds diezelfde versie gegarandeerd 1-op-100 per <code>session_start</code> en verwijdert files ouder dan <code>session.gc_maxlifetime</code>. De map hoort in <code>hiddenSegments</code> (zie de check hierboven).</td></tr>
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
    <h2>Error-weergave</h2>
    <p><code>Bootstrap::configureErrorDisplay()</code> draait twee keer:</p>
    <ul>
        <li><span class="cma-tool__strong">Tijdens bootstrap</span> (vóór <code>.env</code> geladen is): errors staan <span class="cma-tool__strong">AAN</span> + verbose (<code>error_reporting=E_ALL</code>, <code>display_errors</code>, <code>display_startup_errors</code>, <code>html_errors</code>). Zo is een crash vóór <code>.env</code> nooit een stille witte pagina.</li>
        <li><span class="cma-tool__strong">Na het laden van <code>.env</code></span>: opnieuw toegepast met de échte <code>APP_ENVIRONMENT</code>. Alles wat niet <code>P</code> is, of <code>FORCE_DEBUG=1</code>, houdt verbose errors aan. <code>P</code> (productie) zet <code>display_errors</code> UIT — errors gaan nog wél naar de log; forceer per request met <code>FORCE_DEBUG=1</code>.</li>
    </ul>

    <h2>Bestandskeuze: één <code>.env</code> per machine</h2>
    <p>Het platform gebruikt het <span class="cma-tool__strong">single-<code>.env</code> model</span>: elke machine heeft één <code>.env</code> op de site-root met àlle config, inclusief <code>APP_ENVIRONMENT</code>. <code>APP_ENVIRONMENT</code> is nu enkel een <span class="cma-tool__strong">variabele ín dat bestand</span> — het bepaalt niet langer wélk bestand geladen wordt. Dat haalt de "in welke <code>.env.&lt;x&gt;</code> staat mijn secret?"-verwarring weg: app én <code>deploy.php</code> lezen hetzelfde <code>.env</code>.</p>
    <p><code>Bootstrap::detectAndLoadEnv()</code> kiest in deze volgorde:</p>
    <ol>
        <li>Een expliciete <code>env_file</code> meegegeven aan <code>Bootstrap::init()</code> — gebruik die.</li>
        <li>Bestaat er een <code>.env</code> op de site-root? — gebruik die. <span class="cma-tool__strong">(de normale weg)</span></li>
        <li>Anders (legacy fallback, voor nog-niet-gemigreerde sites): is er een OS-level <code>APP_ENVIRONMENT</code> gezet, dan het bijbehorende bestand (<code>L→.env.local</code>, <code>O→.env.development</code>, <code>T→.env.test</code>, <code>A→.env.acceptance</code>, <code>P→.env.production</code>); anders het éérste bestaande bestand in de volgorde <code>.env.local</code> → <code>.env.development</code> → <code>.env.test</code> → <code>.env.acceptance</code> → <code>.env.production</code>.</li>
    </ol>
    <p>Het gekozen bestand staat in <code>$GLOBALS['_env_file']</code> en is zichtbaar op de <a href="tools_serverinfo.php" target="_top">Omgeving-tab</a> als "Actief .env bestand".</p>

    <div class="docs-callout">
        <span class="cma-tool__strong">Migreren:</span> hernoem op elke box <code>.env.production</code> (of <code>.env.local</code>) naar <code>.env</code>. Backward-compatible — een box op de oude per-omgeving bestanden blijft werken tot je migreert.
    </div>

    <div class="docs-callout docs-callout--warn">
        <span class="cma-tool__strong">Let op:</span> <code>APP_ENVIRONMENT</code> in <code>.env</code> wordt pas gelezen <span class="cma-tool__em">nadat</span> de ingebouwde <code>EnvFile</code>-parser het bestand laadt. Vóór dat moment gaat de bootstrap uit van <code>P</code> voor gedrag, maar errors staan tijdens de bootstrap juist AAN zodat een opstartfout zichtbaar is (zie "Error-weergave" hierboven).
    </div>

    <h2>Actief .env bestand op deze site</h2>
    <p>Op de huidige site is dat: <code><?= htmlspecialchars($activeEnv) ?></code></p>

    <h2>De env-wissel knop</h2>
    <p>Op de Omgeving-tab van <a href="tools_serverinfo.php" target="_top">Server informatie</a> staat een "Wissel naar T/P" knop naast het omgeving-label. Die schrijft <code>APP_ENVIRONMENT=&lt;target&gt;</code> naar het <span class="cma-tool__strong">actieve</span> env-bestand (niet hardcoded <code>.env</code>; het juiste bestand wordt gepakt). Na refresh leest bootstrap de nieuwe waarde.</p>
    <p>Toggle-doel: <code>P</code> → <code>T</code>; alles anders → <code>P</code>. Een bevestigingsdialoog meldt dat de wijziging in <code>.env</code> wordt weggeschreven en geldt voor alle gebruikers van de site.</p>

    <h2>Welke env-vars zijn er?</h2>
    <p>Er is geen <code>.env.template</code> en geen centrale lijst in code: elke variabele wordt gelezen op de plek die hem nodig heeft, met een eigen default. Hieronder staat per variabele wat hem leest en wat er gebeurt als je hem weglaat. Alles is optioneel tenzij anders vermeld.</p>

    <h3>Omgeving &amp; foutweergave</h3>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:220px">Variabele</th><th style="width:150px">Default</th><th>Gelezen door &amp; effect</th></tr></thead>
        <tbody>
            <tr><td><code>APP_ENVIRONMENT</code></td><td><code>P</code></td><td><code>Bootstrap</code> — de omgevingscode (<code>O</code>/<code>L</code>/<code>T</code>/<code>A</code>/<code>P</code>) uit de tabel bovenaan. Bepaalt debug-gedrag, niet welk bestand geladen wordt. Een OS-level waarde in de app-pool overrulet het bestand.</td></tr>
            <tr><td><code>FORCE_DEBUG</code></td><td>uit</td><td><code>Bootstrap</code> — <code>1</code> houdt verbose errors aan, ook op <code>P</code>.</td></tr>
            <tr><td><code>CMA_DEBUG</code></td><td>uit</td><td><code>cma/bootstrap.inc</code> — <code>1</code> zet de CMA-debugmodus aan (constante <code>CMA_DEBUG_MODE</code>, gebruikt door de front-end-logging). Los van <code>FORCE_DEBUG</code>: dit gaat over de CMA-UI, niet over PHP-errorweergave.</td></tr>
        </tbody>
    </table>

    <h3>Logging &amp; meten</h3>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:220px">Variabele</th><th style="width:150px">Default</th><th>Gelezen door &amp; effect</th></tr></thead>
        <tbody>
            <tr><td><code>PERF_LOG_ENABLED</code></td><td><code>true</code></td><td><code>Services\SystemSettings</code> — performance-logging. Waarde via <code>FILTER_VALIDATE_BOOLEAN</code>, dus <code>0</code>/<code>off</code>/<code>false</code> werken allemaal.</td></tr>
            <tr><td><code>CACHE_LOG_ENABLED</code></td><td><code>true</code></td><td>Idem, voor de cache-log.</td></tr>
            <tr><td><code>DEBUG_LOG_ENABLED</code></td><td><code>true</code></td><td>Idem, voor de debug-log. Deze drie zijn ook omschakelbaar in de UI; <code>SystemSettings</code> schrijft ze terug naar het actieve env-bestand.</td></tr>
            <tr><td><code>EMAIL_LOG_ENABLED</code></td><td><code>true</code></td><td><code>cma/bootstrap.inc</code> — hangt de afterSend-logging aan <code>Email</code>. Let op: uitsluitend de exacte string <code>false</code> zet dit uit, <code>0</code> niet.</td></tr>
            <tr><td><code>SQL_LOG_ENABLED</code></td><td>uit</td><td><code>Database</code> — logt elke query met duur. Zet dit niet standaard aan: het is één schrijfactie per query.</td></tr>
            <tr><td><code>SQL_LOG_FILE</code></td><td><code>sql_queries.log</code> in de site-root</td><td><code>Database</code> — doelbestand van bovenstaande log. Is het pad niet schrijfbaar, dan meldt <code>Database</code> dat één keer in het PHP-errorlog.</td></tr>
            <tr><td><code>PROFILER_ENABLED</code></td><td>uit; automatisch aan in <code>L</code>/<code>O</code>/<code>T</code></td><td><code>Profiler</code> — request-profiling naar CSV.</td></tr>
            <tr><td><code>PROFILER_LOG_FILE</code></td><td><code>profiler.csv</code> in de base path</td><td><code>Profiler</code> — doelbestand.</td></tr>
            <tr><td><code>PROFILER_THRESHOLD_MS</code></td><td><code>0</code></td><td><code>Profiler</code> — requests sneller dan deze grens worden niet gelogd.</td></tr>
        </tbody>
    </table>

    <h3>Externe diensten &amp; paden</h3>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:220px">Variabele</th><th style="width:150px">Default</th><th>Gelezen door &amp; effect</th></tr></thead>
        <tbody>
            <tr><td><code>GOOGLE_OAUTH_CLIENT_ID</code><br><code>GOOGLE_OAUTH_CLIENT_SECRET</code></td><td>leeg</td><td><code>GoogleOAuth</code> — zonder deze twee is Google-login uit. De oudere namen <code>GOOGLE_CLIENT_ID</code>/<code>GOOGLE_CLIENT_SECRET</code> werken als terugval, dus beide naamparen kunnen op een site voorkomen.</td></tr>
            <tr><td><code>DB_HOST</code>, <code>DB_PORT</code>, <code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASSWORD</code></td><td><code>localhost</code> voor host</td><td><code>Services\BackupService</code> — <span class="cma-tool__em">alleen</span> voor mysqldump/restore van MySQL-databases. De runtime-verbindingen komen uit <a href="documentation.php?topic=json_config">databases.json</a>, niet hieruit.</td></tr>
            <tr><td><code>REDIS_HOST</code>, <code>REDIS_PORT</code></td><td><code>127.0.0.1</code>, <code>6379</code></td><td><code>tools_clearcache.php</code> — alleen relevant als <code>Cache</code> op de redis-backend staat.</td></tr>
            <tr><td><code>CACHE_DIRECTORY</code></td><td>de standaard cache-map</td><td><code>tools_clearcache.php</code> — welke map de cache-leegmaker opruimt.</td></tr>
            <tr><td><code>NODEJS_PATH</code></td><td>leeg</td><td><code>tools_testrunner.php</code> — nodig op IIS, waar de app-pool geen <code>PATH</code> met node erin heeft. Zonder deze var kan de Cypress-runner niet starten.</td></tr>
        </tbody>
    </table>

    <h3>Elders gedocumenteerd</h3>
    <ul>
        <li><span class="cma-tool__strong">Deploy</span>: <code>DEPLOY_SECRET</code>, <code>DEPLOY_BRANCH</code>, <code>DEPLOY_SITE_ROOT</code>, <code>DEPLOY_PIPELINE</code>, <code>DEPLOY_COMPOSER_UPDATE</code>, <code>DEPLOY_COMPOSER_CLEAR_CACHE</code>, <code>DEPLOY_RUN_TESTS</code>, <code>DEPLOY_RECYCLE_TOUCH</code>, <code>DEPLOY_LOG_FILE</code>, <code>DEPLOY_POST_HOOK</code> — zie <a href="documentation.php?topic=deployment">Deployment</a>.</li>
        <li><span class="cma-tool__strong">LLM</span>: <code>LLM_PROVIDER</code>, <code>LLM_URL</code>, <code>LLM_MODEL</code>, <code>LLM_KEY</code>, <code>LLM_FALLBACK_MODEL</code>, <code>OCR_VISION_KEY</code>, <code>OCR_VISION_PROVIDER</code>, <code>LLM_MODELS_DIR</code> — zie <a href="documentation.php?topic=llm">LLM-configuratie</a>.</li>
    </ul>

    <h3>Wat NIET uit .env komt</h3>
    <p>Twee valkuilen, omdat de naam anders suggereert:</p>
    <ul>
        <li><span class="cma-tool__strong">Mail</span>: <code>mail_server</code>, <code>mail_server_port</code>, <code>mail_username</code>, <code>mail_password</code> lopen via <code>Application::get</code> en komen dus uit <code>app.php</code> / <code>global.asa.php</code>.</li>
        <li><span class="cma-tool__strong">Database-verbindingen</span>: die staan in <code>data/databases.json</code>. Wil je een wachtwoord tóch in <code>.env</code> houden, gebruik dan de <code>[env:NAAM]</code>-placeholder ín de connectionString — zie <a href="documentation.php?topic=json_config">JSON-configuratie</a>. De legacy <code>CONN_USERS_PATH</code>/<code>CONN_USERS_DRIVER</code> worden niet door de bootstrap gelezen; alleen <code>tools/reload_env.php</code> zet ze door naar <code>Application</code>.</li>
    </ul>

    <h2>Project-specifieke configs</h2>
    <p>Naast <code>.env</code> heeft het platform een twee-lagen JSON-config (platform-defaults in <code>cma/config/</code>, per-site overrides in <code>data/</code>) voor o.a. <code>app.json</code>, <code>menu.json</code> en <code>reports.json</code>. Dat staat volledig beschreven in <a href="documentation.php?topic=json_config">JSON-configuratie</a>.</p>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=installation">Installatie</a>, <a href="documentation.php?topic=deployment">Deployment</a>, <a href="documentation.php?topic=security">Beveiliging</a>.
    </div>
    <?php
}

function render_doc_images(): void
{
    ?>
    <h1>WebP &amp; responsive afbeeldingen</h1>
    <p class="docs-meta">Hoe het platform geüploade afbeeldingen naar WebP converteert en responsive varianten genereert — plus de batch-tool voor bestaande beelden.</p>

    <?php cma_doc_render_check_table('Beeldconversie — live check op deze site', cma_doc_run_checks([
        'cma_doc_check_webp_gd',
        'cma_doc_check_cwebp',
    ])); ?>

    <h2>Twee helper-classes</h2>
    <ul>
        <li><code>App\Library\Image</code> — laag-niveau GD/cwebp bewerkingen: <code>convertToWebP()</code>, <code>resize()</code>, <code>crop()</code>, <code>cropAndResize()</code>, <code>rotate()</code> (corrigeert EXIF-oriëntatie), <code>thumbnail()</code>, <code>optimize()</code>, plus detectie <code>isWebPSupported()</code> / <code>isCwebpAvailable()</code>.</li>
        <li><code>App\Library\ResponsiveImage</code> — variant-generatie en front-end output: <code>generate()</code>, <code>imgTag()</code> (bouwt een <code>&lt;img srcset&gt;</code>), <code>hasVariants()</code>, <code>deleteVariants()</code>, <code>batchGenerate()</code>, <code>scan()</code>, <code>cleanup()</code>.</li>
    </ul>

    <h2>Wat er bij een upload gebeurt</h2>
    <p>De upload-handler (<code>form_api.php?action=uploadImage</code>) detecteert WebP-support, schaalt de afbeelding terug binnen <code>maxWidth</code>×<code>maxHeight</code> (standaard 800×600), schrijft het resultaat als <code>.webp</code> (kwaliteit 85) en roept dan <code>ResponsiveImage::generate()</code> aan. Zonder GD-WebP-support valt de upload terug op JPEG.</p>
    <p>Het origineel blijft staan; de varianten komen in een <code>.responsive/</code>-submap naast het bestand:</p>
    <pre><code>/images/foto.jpg                      &larr; origineel (blijft staan)
/images/.responsive/foto-300w.webp    &larr; 300px variant
/images/.responsive/foto-400w.webp    &larr; 400px variant
/images/.responsive/foto-800w.webp    &larr; 800px variant
/images/.responsive/foto-1200w.webp   &larr; 1200px variant
/images/.responsive/foto.webp         &larr; volledige WebP</code></pre>
    <p>Breedtes en kwaliteit zijn constanten op <code>ResponsiveImage</code>: <code>SIZES = [300, 400, 800, 1200]</code>, <code>DEFAULT_QUALITY = 85</code>, <code>RESPONSIVE_DIR = '.responsive'</code>. Varianten groter dan het origineel worden overgeslagen.</p>

    <h2>Crop / roteren / schalen</h2>
    <p>In de file-browser (<code>cma/wizards/file-browser.php</code>) werken crop, rotate en resize <span class="cma-tool__em">in-place</span> op het bestand. Na elke bewerking worden de oude varianten verwijderd (<code>deleteVariants()</code>) en opnieuw gegenereerd (<code>generate()</code>), zodat de <code>.responsive/</code>-set altijd klopt met de huidige inhoud. Deze bewerk-acties (<code>rotate</code>/<code>filter</code>/<code>crop</code>/<code>autocrop</code>/<code>restore</code>/<code>resize</code>) vereisen een ingelogde CMA-gebruiker.</p>

    <h2>Losse afbeeldingseditor (<code>image-editor.php</code>)</h2>
    <p>Dezelfde bewerkingen zijn los bruikbaar via <code>/cma/image-editor.php</code> &mdash; een schermvullende editor die een <span class="cma-tool__em">bestaande</span> afbeelding op het pad bewerkt. Hij wordt geopend vanaf drie plekken: (1) de knop <span class="cma-tool__strong">Open volledige editor</span> (icoon <code>lnr-picture</code>) in het rechterpaneel van de file-browser; (2) het <span class="cma-tool__strong">bewerk-icoon</span> (<code>lnr-crop</code>) naast een <span class="cma-tool__em">image</span>-veld op een CMA-formulier &mdash; dat opent de editor op het huidige beeld en neemt bij Klaar de (mogelijk hernoemde) bestandsnaam over als veldwaarde; en (3) vanaf de front-end van een consumer-site (zie <a href="https://www.karaatedelstenen.nl/tools/quick_add_stone.php">quick_add_stone.php</a>). Hij draait geen eigen beeldbewerking maar delegeert elke actie naar het bestaande endpoint in <code>file-browser.php</code>, zodat er niets wordt gedupliceerd.</p>
    <p><span class="cma-tool__strong">Login-guard:</span> bovenaan controleert de pagina <code>SecurityHelper::isLoggedIn()</code>. Een niet-ingelogde bezoeker krijgt een inline <code>&lt;lib-message type="error"&gt;</code> (&ldquo;Geen toegang&rdquo;) i.p.v. een redirect, zodat het in een ingebedde dialog leesbaar blijft.</p>
    <p><span class="cma-tool__strong">Query-parameters:</span> <code>basepath</code> (verplicht), <code>file</code> (verplicht), <code>path</code> (submap), en de veldregel <code>resizetype</code>/<code>resizewidth</code>/<code>resizeheight</code>.</p>
    <p><span class="cma-tool__strong">Veld-definitieregels</span> (<code>FormControlHelper::IMG_*</code>) worden afgedwongen:</p>
    <table class="listtable">
        <thead><tr class="listheader"><th><code>resizetype</code></th><th>Gedrag</th></tr></thead>
        <tbody>
            <tr><td>0 — vrij</td><td>Crop zonder verhoudingsslot, geen vaste uitvoermaat.</td></tr>
            <tr><td>1 — maximum</td><td>Crop vrij; bij <span class="cma-tool__strong">Klaar</span> wordt de afbeelding zo nodig teruggeschaald binnen <code>W×H</code> (verhouding behouden).</td></tr>
            <tr><td>2 — vast</td><td>Crop-selectie zit vast op de verhouding <code>W/H</code> en de uitvoer is exact <code>W×H</code> (<code>Image::cropAndResize</code>).</td></tr>
        </tbody>
    </table>
    <p><span class="cma-tool__strong">Retour-contract:</span> bij Klaar post de editor <code>{type:'image-editor-complete', file, width, height}</code> naar <code>window.parent</code> én <code>window.opener</code>; bij annuleren <code>{type:'image-editor-cancel'}</code>. De file-browser luistert hierop en ververst de details; het CMA-image-veld (<code>FormController.openImageEditor</code>) opent de editor als popup en zet bij Klaar via de <code>message</code>-listener de nieuwe <code>file</code>/afmetingen terug in het veld; een front-end opslaglistener gebruikt <code>file</code> als de opgeslagen bestandsnaam.</p>

    <h2>Front-end gebruik</h2>
    <p><code>ResponsiveImage::imgTag($url, $alt, $sizes, $class, $attrs)</code> bouwt een compleet <code>&lt;img&gt;</code> met <code>srcset</code> over de beschikbare breedtes. Ontbreken er varianten, dan valt de tag netjes terug op de originele URL.</p>

    <h2>cwebp vs GD</h2>
    <p><code>convertToWebP()</code> gebruikt bij voorkeur de <code>cwebp</code> CLI (behoudt ICC-kleurprofielen) en valt terug op GD als die ontbreekt (GD stript ICC → klein kleurverschil mogelijk). Zonder beide is conversie niet mogelijk. De live-check bovenaan toont wat deze site heeft.</p>

    <h2>Batch-tool voor bestaande afbeeldingen</h2>
    <p><span class="cma-tool__strong">Alle beheerstools → WebP beeld-conversie</span> (<code>tools/tools_webp_convert.php</code>) scant een map, toont per afbeelding of er varianten zijn, en genereert/hergenereert of ruimt op (<code>cleanup()</code> verwijdert alle <code>.responsive/</code>-mappen). De tool verwerkt tot 20 bestanden per request en toont GD/cwebp-diagnostiek.</p>
    <p><span class="cma-tool__strong">Geheugen &amp; foutrapportage:</span> een te grote afbeelding laat GD het <code>memory_limit</code> (512&nbsp;MB tijdens conversie) overschrijden — een <span class="cma-tool__em">niet-vangbare</span> fatal die de hele batch met een lege HTTP 500 zou afbreken. De tool schat daarom vooraf het benodigde geheugen (~5&nbsp;bytes/pixel) en slaat een te groot beeld over met een concrete reden in de foutenlijst, zodat de rest van de batch doorloopt. Breekt er tóch een fatal door (bv. een time-out), dan vangt een <code>register_shutdown_function</code> die op en stuurt JSON terug met de <span class="cma-tool__em">naam van het bestand</span> waarop het stukliep, de foutmelding en het geheugenpiekgebruik — die verschijnen op het scherm i.p.v. een generieke 500. De client parseert dat JSON-antwoord ook bij een 500-status en strip HTML uit een eventuele foutpagina.</p>

    <h2>Configuratie</h2>
    <p>Er zijn <span class="cma-tool__strong">geen env-vars</span> voor beeldconversie. Alles zit in de constanten op <code>ResponsiveImage</code> en de <code>maxWidth</code>/<code>maxHeight</code> POST-parameters van de upload-handler.</p>

    <h2>Troubleshooting</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th>Symptoom</th><th>Oorzaak</th><th>Oplossing</th></tr></thead>
        <tbody>
            <tr><td>Uploads worden JPEG i.p.v. WebP</td><td>GD mist WebP-support</td><td>GD-build met libwebp installeren (zie live-check).</td></tr>
            <tr><td>Klein kleurverschil na conversie</td><td>GD stript het ICC-profiel</td><td><code>cwebp</code> installeren — dan blijven kleurprofielen behouden.</td></tr>
            <tr><td>Oude variant blijft zichtbaar na crop</td><td>Varianten niet vernieuwd</td><td>De file-browser doet <code>deleteVariants()</code>+<code>generate()</code>; bij handmatige edits idem aanroepen.</td></tr>
            <tr><td>Geen <code>srcset</code> in de output</td><td>Geen varianten aanwezig</td><td>Draai de batch-tool of <code>ResponsiveImage::generate()</code> op de map.</td></tr>
            <tr><td>Batch-conversie stopt na een paar beelden met HTTP 500</td><td>Grote afbeelding put GD-geheugen uit → niet-vangbare fatal breekt de request af</td><td>De tool slaat te grote beelden over met reden en meldt het bestand waarop een fatal stukliep op het scherm. Verhoog <code>memory_limit</code> of verklein het bronbeeld.</td></tr>
            <tr><td><code>image-editor.php</code> toont &ldquo;Geen toegang&rdquo;</td><td>Geen geldige CMA-sessie (<code>CMAU</code>-cookie)</td><td>Log in op de CMA; de editor en zijn bewerk-acties vereisen een ingelogde gebruiker.</td></tr>
            <tr><td>Editor slaat op maar de maat klopt niet met het veld</td><td><code>resizetype/-width/-height</code> niet meegegeven aan de URL</td><td>Open via een image-veld (form-controller geeft de veldregel mee) of zet de params expliciet in de URL.</td></tr>
        </tbody>
    </table>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=json_config">JSON-configuratie</a>, <a href="documentation.php?topic=architecture">Architectuur</a>.
    </div>
    <?php
}

function render_doc_json_config(): void
{
    ?>
    <h1>JSON-configuratie</h1>
    <p class="docs-meta">De twee-lagen JSON-config (platform-defaults + per-site overrides), welke bestanden er zijn, wie ze leest en welke laag wint. Niet te verwarren met <a href="documentation.php?topic=json_forms">JSON-gedreven formulieren</a>.</p>

    <h2>Twee lagen</h2>
    <ul>
        <li><code>cma/config/&lt;naam&gt;.json</code> — platform-defaults, gebundeld met het pakket. <span class="cma-tool__strong">Worden door <code>composer update</code> overschreven</span> — niet bewerken voor site-specifieke waarden.</li>
        <li><code>data/&lt;naam&gt;.json</code> op de site-root — per-site overrides. De Installer raakt <code>data/</code> nooit aan, dus deze blijven over een update heen staan.</li>
    </ul>
    <div class="docs-callout">
        <span class="cma-tool__strong">Regel:</span> wil je iets site-specifieks aanpassen, zet het in <code>data/</code>. Een wijziging in <code>cma/config/</code> verdwijnt bij de eerstvolgende <code>composer update</code>.
    </div>

    <h2>De configbestanden</h2>
    <p>In <code>cma/config/</code> zitten: <code>cma_branding.json</code>, <code>menu.json</code>, <code>cma_reports.json</code>, <code>control-types.json</code> en <code>migrations.json</code>. Wie wat leest en welke laag wint:</p>
    <table class="listtable">
        <thead><tr class="listheader"><th>Bestand</th><th>Inhoud</th><th>Lezer</th><th>Welke laag wint</th></tr></thead>
        <tbody>
            <tr><td><code>cma_branding.json</code></td><td>Branding: logo, kleuren, bedrijfs-URL</td><td><code>cma_get_app_logo()</code> (bootstrap.inc)</td><td><code>data/cma_branding.json</code> → legacy <code>data/app.json</code> → <code>cma/config/cma_branding.json</code> → legacy <code>cma/config/app.json</code></td></tr>
            <tr><td><code>cma_menu.json</code></td><td>Navigatiestructuur, menu-items</td><td><code>MenuService::CONFIG_PATH</code></td><td>Bij voorkeur <code>data/cma_menu.json</code>, met legacy-terugval naar <code>data/menu.json</code> (géén terugval naar <code>cma/config/</code>)</td></tr>
            <tr><td><code>cma_reports.json</code></td><td>SQL-rapportdefinities</td><td><code>ReportsService::configPath()</code></td><td>Bij voorkeur <code>data/cma_reports.json</code>, met legacy-terugval naar <code>data/reports.json</code> (géén terugval naar <code>cma/config/</code>)</td></tr>
            <tr><td><code>databases.json</code></td><td>DB-connecties (single source of truth)</td><td><code>Bootstrap::initDatabaseConnections()</code> + <code>Cma\ConfigLoader</code></td><td>alleen <code>data/databases.json</code> — het platform levert geen default-connecties</td></tr>
            <tr><td><code>control-types.json</code></td><td>Legacy Access-besturingstype-mapping</td><td><code>Cma\ConfigLoader</code> (alias)</td><td><code>/cma/control-types.json</code> (systeem-eigen)</td></tr>
            <tr><td><code>migrations.json</code></td><td>Schema-versie + migratiestappen</td><td><code>MigrationService</code> + <code>Bootstrap</code> + <code>ConfigLoader</code></td><td><code>/cma/config/migrations.json</code> (systeem-eigen, enige bron; <code>targetVersion</code> stuurt de migratiecheck)</td></tr>
        </tbody>
    </table>
    <p><span class="cma-tool__em">Let op de asymmetrie:</span> <code>cma_branding.json</code> kent een terugval van <code>data/</code> naar <code>cma/config/</code>; <code>cma_menu.json</code> en <code>cma_reports.json</code> wijzen rechtstreeks naar <code>data/</code> zonder terugval naar <code>cma/config/</code>. Voor <code>databases.json</code> is <code>data/</code> in de praktijk ook de enige laag: <code>Cma\ConfigLoader</code> — en dus elk CMA-scherm — leest niets anders, en het platform levert geen <code>cma/config/databases.json</code> meer. <code>Bootstrap</code> leest dat pad nog wél als tweede kandidaat, puur voor een site die zijn connecties daar had staan voordat ze naar <code>data/</code> verhuisden. <code>MenuService</code> leest bij voorkeur <code>data/cma_menu.json</code> en valt alleen terug op de legacy <code>data/menu.json</code>. Ontbreken beide, dan is het menu leeg.</p>

    <h2><code>$schema</code>-verwijzingen</h2>
    <p>De JSON Schema's staan in <code>cma/config/schema/</code> en worden met het pakket meegeleverd. Een configbestand verwijst ernaar met een <code>$schema</code>-regel bovenaan; editors gebruiken die voor validatie en IntelliSense.</p>
    <p>Die verwijzing is <span class="cma-tool__strong">relatief aan de map van het bestand zelf</span> — dus het aantal <code>../</code>-stappen verschilt per locatie: <code>data/</code> zit één niveau van de site-root, <code>assets/forms/</code> twee. Klopt het pad niet, dan valt de editor stil terug op géén validatie: er komt geen waarschuwing, het bestand ziet er gewoon goed uit. Vandaar de controle hieronder.</p>
    <table class="listtable">
        <thead><tr class="listheader"><th>Config staat in</th><th>Correcte <code>$schema</code></th></tr></thead>
        <tbody>
            <tr><td><code>cma/config/</code></td><td><code>./schema/&lt;naam&gt;.schema.json</code></td></tr>
            <tr><td><code>cma/</code></td><td><code>./config/schema/&lt;naam&gt;.schema.json</code></td></tr>
            <tr><td><code>data/</code></td><td><code>../cma/config/schema/&lt;naam&gt;.schema.json</code></td></tr>
            <tr><td><code>assets/forms/</code>, <code>assets/datastores/</code>, <code>assets/contentblocks/</code></td><td><code>../../cma/config/schema/&lt;naam&gt;.schema.json</code></td></tr>
            <tr><td><code>cma/assets/contentblocks/</code></td><td><code>../../config/schema/&lt;naam&gt;.schema.json</code></td></tr>
        </tbody>
    </table>
    <p><code>cma/config/schema/</code> is de enige schemamap. Verwijst iets naar een schema elders, of naar een gewoon JSON-bestand dat toevallig bestaat, dan valt de validatie stil weg — de controle hieronder eist daarom dat elke verwijzing in <code>cma/config/schema/</code> uitkomt.</p>
    <p>Genereer je zelf JSON, bereken het pad dan uit de doelmap met <code>App\Library\File::relativePath()</code> in plaats van het in te typen.</p>
    <?php cma_doc_render_check_table('Controle op deze site', cma_doc_run_checks(['cma_doc_check_json_schema_refs'])); ?>

    <h2>Welk schema hoort bij welk bestand</h2>
    <p>Elk schema in <code>cma/config/schema/</code> hoort bij precies één configbestand (of, bij formulierdefinities, bij een hele map). De verplichte top-level keys komen uit het <code>required</code>-veld van het schema zelf:</p>
    <table class="listtable">
        <thead><tr class="listheader"><th>Configbestand</th><th>Schema</th><th>Verplichte keys</th><th>Te bewerken via</th></tr></thead>
        <tbody>
            <tr><td><code>data/databases.json</code></td><td><code>databases.schema.json</code></td><td><code>databases[]</code></td><td>Alleen met de hand (geen onderhoudsformulier)</td></tr>
            <tr><td><code>data/cma_menu.json</code></td><td><code>menu.schema.json</code></td><td><code>menus[]</code></td><td>CMA-formulieren <code>_menus</code> en <code>_menu_items</code></td></tr>
            <tr><td><code>data/cma_branding.json</code></td><td><code>cma_branding.schema.json</code></td><td><code>company</code></td><td>Alleen met de hand</td></tr>
            <tr><td><code>data/cma_reports.json</code></td><td><code>cma_reports.schema.json</code></td><td><code>reports[]</code></td><td>Rapportontwerper</td></tr>
            <tr><td><code>data/cma_tools.json</code></td><td><code>cma_tools.schema.json</code></td><td><code>groups[]</code></td><td>Alleen met de hand. Gelezen door <code>tools_catalog.inc</code>, met legacy-terugval op <code>data/tools.json</code>.</td></tr>
            <tr><td><code>data/image-profiles.json</code></td><td><span class="cma-tool__em">geen schema</span></td><td>—</td><td>Alleen met de hand. Gelezen door <code>App\Library\ImageProfiles</code>; zie <a href="documentation.php?topic=images">WebP &amp; afbeeldingen</a>.</td></tr>
            <tr><td><code>cma/control-types.json</code></td><td><code>control-types.schema.json</code></td><td><code>controlTypes[]</code></td><td>Alleen met de hand</td></tr>
            <tr><td><code>cma/config/migrations.json</code></td><td><code>migrations.schema.json</code></td><td><code>schemaVersion</code>, <code>targetVersion</code>, <code>migrations[]</code></td><td>Alleen met de hand — zie <a href="documentation.php?topic=migrations">Migraties schrijven</a></td></tr>
            <tr><td><code>assets/datastores/data-sources.json</code></td><td><code>data-sources.schema.json</code></td><td><code>dataSources[]</code></td><td>Alleen met de hand</td></tr>
            <tr><td><code>cma/assets/contentblocks/contentblocks.json</code></td><td><code>contentblocks.schema.json</code></td><td>geen (<code>templates[]</code> is de inhoud)</td><td>CMA-formulier <code>contentblocks</code></td></tr>
            <tr><td><code>assets/forms/*.json</code> en <code>cma/assets/forms/definitions/*.json</code></td><td><code>form-definition.schema.json</code></td><td><code>name</code>, <code>table</code>, <code>fields[]</code></td><td>CMA-formulier <code>formdefinitions</code></td></tr>
        </tbody>
    </table>
    <p><code>modules.schema.json</code> hoort bij een <code>modules.json</code> die het platform niet meelevert en die geen migratie aanmaakt. <code>CmaRepository</code> vraagt er nog naar met <code>ConfigLoader::exists('modules')</code> en slaat dat pad over als het bestand er niet is — het schema is dus een overblijfsel, geen contract.</p>

    <div class="docs-callout docs-callout--warn">
        <span class="cma-tool__strong">Een schema wordt gecontroleerd bij het schrijven, niet bij het lezen.</span> <code>ConfigLoader::save()</code> valideert het hele document tegen het schema en weigert te schrijven als de wijziging een overtreding introduceert; <code>load()</code> controleert niets. Een bestand dat je met de hand kapot maakt wordt dus nog steeds ingelezen — wat er misgaat merk je pas bij de eerste lezer die een key mist. Voor een controle vooraf: <a href="tools_validate_config.php" target="_top">Config valideren</a>.
    </div>

    <h2>Valideren bij opslaan</h2>
    <p>Elke schrijfactie via <code>ConfigLoader::save()</code> — dus ook alles wat de CMA-onderhoudsformulieren en <code>api/config_post.php</code> doen — loopt langs <code>App\Library\JsonSchema</code>. Weigert de validator, dan komt er geen byte op schijf en zegt <code>ConfigLoader::lastErrors()</code> per fout wélke key het is: <code>databases[2].id: moet integer zijn, is string</code>. Die tekst komt terug in het formulier.</p>
    <p>Eén regel is belangrijk om te snappen: geweigerd worden alleen overtredingen die <span class="cma-tool__strong">deze wijziging introduceert</span>. Stond er al iets in het bestand dat het schema schendt, dan blijft dat bestand bewerkbaar — anders zou het aanzetten van validatie precies de schermen blokkeren waarmee je het zou moeten repareren. Wat er al fout stond zie je in <a href="tools_validate_config.php" target="_top">Config valideren</a>.</p>
    <p>Schrijven gaat via een tijdelijk bestand plus <code>rename()</code>, met de vorige versie ernaast als <code>&lt;bestand&gt;.bak</code>. Een mislukte schrijfactie kan dus geen half bestand achterlaten, en een verkeerde wijziging is terug te zetten door <code>.bak</code> over het origineel te kopiëren. Lukt de rename niet (een vergrendeld doelbestand), dan valt <code>save()</code> terug op in-place schrijven — de wijziging kwijtraken is erger dan atomiciteit kwijtraken.</p>
    <p>De validator dekt de keywords die deze schema's gebruiken: <code>type</code> (ook als union, bv. <code>["string","null"]</code>), <code>required</code>, <code>properties</code>, <code>additionalProperties</code> (als schema of <code>false</code>), <code>items</code>, <code>enum</code>, <code>oneOf</code>, <code>$ref</code> (lokaal, <code>#/definitions/…</code>), <code>minimum</code>, <code>maximum</code>, <code>pattern</code>, <code>minItems</code>, <code>maxItems</code> en <code>contains</code> met <code>minContains</code>/<code>maxContains</code>. Andere keywords worden genegeerd, zoals de spec voorschrijft — <code>title</code>, <code>description</code> en <code>default</code> zijn dus vrij te gebruiken. Gebruik je in een nieuw schema iets zwaarders (<code>allOf</code>, <code>if/then</code>, remote <code>$ref</code>), dan wordt dat stil overgeslagen: dan hoort de validator uitgebreid te worden, niet het schema versimpeld.</p>
    <h3>Extra controle voor databases.json</h3>
    <p><code>databases.json</code> is het enige configbestand waarvan de inhoud de hele site kan platleggen, en een structureel correcte entry kan nog steeds naar een database wijzen die niet te openen is. Daarom opent <code>save()</code> elke connectie die <span class="cma-tool__strong">deze wijziging aanraakt</span> één keer, via <code>Database::probeDsn()</code>, vóórdat het bestand geschreven wordt. Lukt dat niet, dan gaat de wijziging niet door:</p>
    <pre><code>Verbinding data kan niet worden geopend: bestand bestaat niet: D:\wwwroot\site\data\db\pdodomain.mdb</code></pre>
    <p>Ontbreekt het databasebestand, dan noemt de melding dat pad — de Access-driver zelf zegt dat namelijk vrijwel nooit. Alleen gewijzigde connecties worden getest: een site waarvan de afgedankte <code>rep</code>-database al jaren niet te openen is, moet <code>data</code> nog steeds kunnen bijwerken. Een entry met <code>"deprecated": true</code> of een lege <code>connectionString</code> wordt niet getest — niet-geconfigureerd is een geldige toestand, geen fout.</p>
    <p>Welk schema bij welke config hoort, beantwoordt <code>ConfigLoader::schemaPath()</code> — één resolver, die de renames volgt (<code>app</code> → <code>cma_branding.schema.json</code>) én weet dat <code>menu</code> zijn eigen <code>menu.schema.json</code> hield. Dat is ook wat <code>api/config_api.php?action=schema</code> teruggeeft, zodat een editor en de save-check nooit een ander schema kunnen pakken. Een config zonder schema (<code>cma_tools.json</code>) wordt zonder controle opgeslagen.</p>
    <p>Omdat de schema's nu meebeslissen over wat opgeslagen kan worden, moeten ze de werkelijkheid beschrijven en geen ideaalbeeld. Twee testen bewaken dat: <code>JsonSchemaTest</code> valideert elke meegeleverde config en elke meegeleverde formulierdefinitie tegen zijn eigen schema.</p>

    <h2>Runtime DB-connecties (single source of truth)</h2>
    <p><code>Bootstrap::initDatabaseConnections()</code> bouwt de logische connecties <code>data</code>, <code>rep</code> en <code>users</code> rechtstreeks uit <code>databases.json</code> — er worden géén <code>conn_data</code>/<code>conn_rep</code>/<code>conn_users</code> meer uit <code>app.php</code> of <code>global.asa.php</code> gelezen. Noem de entries <code>data</code>, <code>rep</code> en <code>users</code>; de legacy-namen <code>Database</code>, <code>Repository</code> en <code>CMAUsers</code> worden nog herkend zodat bestaande sites blijven werken.</p>
    <p>In de <code>connectionString</code> kun je twee placeholders gebruiken:</p>
    <ul>
        <li><code>[db/bestand.mdb]</code> → absoluut pad onder de site-root (bv. een Access-bestand). Een kale <code>[path]</code> is de site-root zelf.</li>
        <li><code>[env:NAAM]</code> → waarde uit de omgeving (<code>.env</code>) — zo blijven wachtwoorden buiten het bestand terwijl de <span class="cma-tool__em">definitie</span> in databases.json staat.</li>
    </ul>
    <p>OLEDB-strings voor Access worden automatisch naar een ODBC-DSN omgezet; een kant-en-klare PDO-DSN (<code>sqlite:</code>/<code>odbc:</code>/<code>mysql:</code>/<code>sqlsrv:</code>) wordt ongewijzigd gebruikt.</p>
    <?php cma_doc_render_check_table('Controle op deze site', cma_doc_run_checks(['cma_doc_check_db_config_source'])); ?>

    <h3>Welke database staat voorgeselecteerd</h3>
    <p>Zet <code>"default": true</code> op één entry in <code>data/databases.json</code> en elke databasekeuzelijst in de CMA — SQL uitvoeren, Databasestructuur, de rapportontwerper — start daarop. Let op dat de keuzelijsten via <code>Cma\ConfigLoader</code> lopen en dus <span class="cma-tool__em">alleen</span> <code>data/databases.json</code> lezen.</p>
    <pre><code>{ "id": 6, "name": "data", "type": "access", "default": true,
  "connectionString": "Provider=Microsoft.Jet.OLEDB.4.0;Data Source=[data/db/pdodomain.mdb]" }</code></pre>
    <p>Zonder die vlag raadt <code>CmaRepository::getDefaultDatabaseId()</code>: eerst een entry met de naam <code>data</code>, anders een connectionString die <code>main.mdb</code>, <code>webdata.mdb</code>, <code>pdodomain.mdb</code> of <code>pdodomein.mdb</code> bevat, anders gewoon de eerste entry. Heet de hoofddatabase van de site anders, dan valt de keuzelijst dus op de verkeerde database open.</p>
    <p>Er mag <span class="cma-tool__strong">maximaal één</span> entry de vlag hebben, en dat staat in het schema — met <code>contains</code> + <code>maxContains: 1</code>, de enige manier om een regel over de hele array uit te drukken. Een tweede <code>"default": true</code> wordt bij opslaan geweigerd met <code>databases: mag maximaal 1 item(s) hebben (één entry met "default": true), heeft 2</code>. Zet je ze met de hand in het bestand, dan wint de eerste in bestandsvolgorde — validatie loopt bij schrijven, niet bij lezen. Omdat <code>maxContains</code> een keyword uit draft 2019-09 is, verwijst <code>databases.schema.json</code> naar dat meta-schema; een editor die alleen draft-07 kent negeert de regel en laat de rest gewoon werken.</p>
    <p>De vlag stuurt alleen de <span class="cma-tool__em">voorselectie</span> in schermen. Welke database de code als <code>data</code>, <code>rep</code> of <code>users</code> opent, blijft de <code>name</code> van de entry bepalen.</p>
    <div class="docs-callout docs-callout--warn">
        <span class="cma-tool__strong">Waarom dit belangrijk is:</span> een <code>global.asa.php</code> die de connecties forkt op <code>omgeving</code> kiest bij een verkeerd gedetecteerde productie-box stilletjes een lege lokale SQLite users-DB → <code>no such table: tblUsers</code> en niemand kan meer inloggen. Eén bron (databases.json) maakt die drift onmogelijk.
    </div>

    <h2>ConfigLoader &amp; aliassen</h2>
    <p><code>Cma\ConfigLoader</code> lost configbestanden standaard op naar de <code>/data/</code>-map. Drie namen zijn gealiast naar een vaste, systeem-eigen locatie (niet <code>data/</code>):</p>
    <pre><code>'data-sources'  &rarr; /assets/datastores/data-sources.json
'control-types' &rarr; /cma/control-types.json
'migrations'    &rarr; /cma/config/migrations.json</code></pre>

    <div class="docs-callout docs-callout--warn">
        <span class="cma-tool__strong">Elke store in data-sources.json moet een gevulde <code>query</code> hebben.</span> De XML-snippets (front-end markers <code>[[Gegevensbron:store{param}]]</code>) draaien via <code>HandleXMLSnippets</code> → <code>XMLStore_XMLRetrieve</code>: is <code>query</code> leeg, dan wordt er geen XML gebouwd en rendert de snippet <span class="cma-tool__em">stil naar niets</span> — de pagina blijft leeg zonder foutmelding. De queries komen uit de legacy-tabel <code>tblXMLStore.Query</code> (repository-DB) en worden geëxporteerd door de migratie <code>3.1.0_export_datastores.php</code>. Controleer bij lege opleiding-/deelnemer-/contactpagina's eerst of de stores een <code>query</code> hebben.
    </div>

    <h2>Bescherming door de Installer</h2>
    <p><code>src/Installer.php</code> synct alleen <code>cma/</code>, <code>library/</code> en <code>module/</code> — nooit <code>data/</code>. De lijst <code>PROTECTED_PATHS</code> bevat <code>data/app.json</code>, <code>data/databases.json</code>, <code>data/menu.json</code> en <code>data/reports.json</code> als belt-and-braces: de check is feitelijk nooit triggerbaar (de Installer raakt <code>data/</code> toch niet aan) maar maakt expliciet welke paden per ontwerp project-bezit zijn.</p>

    <div class="docs-callout docs-callout--warn">
        <span class="cma-tool__strong">Niet hetzelfde als JSON-formulieren:</span> formulier-<span class="cma-tool__em">definities</span> staan in <code>cma/assets/forms/definitions/</code> (intern) en <code>assets/forms/</code> (app-specifiek) en worden door <code>JsonFormLoader</code> geladen — dat is een aparte pijplijn. Zie <a href="documentation.php?topic=json_forms">JSON-gedreven formulieren</a>.
    </div>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=json_forms">JSON-gedreven formulieren</a>, <a href="documentation.php?topic=environment">Omgeving &amp; .env</a>, <a href="documentation.php?topic=installation">Installatie</a>.
    </div>
    <?php
}

function render_doc_deployment(): void
{
    $deployLogHref = 'logreader.php?log=deploy';
    $logFile       = dirname(__DIR__, 2) . '/.logs/deploy/deploy.log';
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
    <p>Deploys verlopen via één GitHub push-webhook: <code>/deploy.php</code> in de site-root. Dit is het <span class="cma-tool__strong">enige</span> endpoint — de oude framework-webhook (<code>cma/tools/deploy_webhook.php</code>) en de standalone (<code>cma/tools/deploy_webhook_standalone.php</code>) zijn vervallen; hun volledige feature-set zit nu hier.</p>
    <p>Waarom een root-bestand: <code>/deploy.php</code> is git-tracked, staat in de site-root en hangt van NIETS in <code>vendor/</code>, <code>/cma/</code> of URL Rewrite af. Een kapotte <code>composer install</code> (die alles onder <code>/cma/</code> 404't) legt 'm dus niet plat — de volgende push landt en repareert. De Installer overschrijft 'm bij elke <code>composer update</code> (bron: <code>templates/deploy.php.template</code>); commit 'm in je consumer-repo zodat een kale <code>git pull</code> 'm in leven houdt.</p>
    <p>De stappen:</p>
    <ol>
        <li><span class="cma-tool__strong">Verificatie</span> — HMAC-SHA256 signature (<code>X-Hub-Signature-256</code>) getoetst tegen <code>DEPLOY_SECRET</code>; alleen POST; alleen de geconfigureerde <code>DEPLOY_BRANCH</code>.</li>
        <li><span class="cma-tool__strong">Vroege response</span> — 202 Accepted en de verbinding losgekoppeld (<code>fastcgi_finish_request</code>); de rest draait async.</li>
        <li><span class="cma-tool__strong">Onderhoudsscherm aan</span> — vóór de muterende stappen zet de deploy een <code>maintenance.flag</code> in de site-root. <code>_bootstrap.php</code> (auto-prepend, draait vóór de Composer-autoloader) serveert dan voor élke bezoeker een schone <code>503</code> "even onderhoud" i.p.v. de fatale fouten die je krijgt terwijl <code>vendor/</code> en de platform-tree half verwisseld zijn. <code>deploy.php</code> / <code>deploy_status.php</code> blijven bereikbaar. De vlag wordt aan het eind via een shutdown-hook weer weggehaald (dus ook bij een crash/abort), en <code>_bootstrap.php</code> negeert een vlag ouder dan 20 min als laatste vangnet tegen een vastgelopen deploy. De pagina zelf komt uit het platform: <code>cma/maintenance_page.inc</code>, een dependency-vrij scriptje (géén autoload — de platform-tree wisselt immers om). De site-eigen pagina (<code>maintenance.php</code> in de site-root, of <code>src/pages/maintenance.php</code>) is een include van dat bestand, met een ingebouwde terugval voor het moment dat <code>cma/</code> er nog niet staat. Branding leest hij — op volgorde — uit <code>data/maintenance.json</code> (een apart, alleen-branding bestand; veilig voor sites zónder <code>data/app.json</code>, want <code>ConfigLoader</code> vervangt i.p.v. merget), dan <code>data/app.json</code> → <code>maintenance</code>, dan de <code>company</code>-blok van <code>data/cma_branding.json</code> of de legacy <code>data/app.json</code>. Velden: <code>{ logo, name, message, whatsapp, email, accent }</code>. Een logo uit <code>maintenance</code> staat op de lichte kaart (zo is het getekend); valt hij terug op <code>company.logo</code>, dan komt die op een balk in <code>backgroundColor</code> — precies zoals de CMA-header, want een wit logo is op een lichte achtergrond onzichtbaar. Ship je <code>assets/images/maintenance.svg</code>, dan staat die illustratie boven de tekst. Het logo wordt per URL geladen; IIS serveert statische bestanden buiten de auto-prepend om, dus dat werkt terwijl de app plat ligt. Ontbreekt <code>maintenance.php</code>, dan valt <code>_bootstrap.php</code> terug op een ingebouwde standaardpagina. <span class="cma-tool__strong">Handmatig aan/uit</span>: <a href="tools.php?tool=maintenance" target="_top">Alle beheertools → Onderhoudsscherm</a> zet de vlag zelf met een <code>"manual"</code>-markering (verdwijnt niet na 20 min én blijft staan als er ondertussen een deploy draait — die raakt alleen z'n eigen vlag aan) en een optioneel bericht op maat (in de vlag-JSON, overschrijft de config-tekst). De gate laat <code>/cma/</code> altijd door, zodat je jezelf nooit buitensluit. Uitschakelen tijdens deploy: <code>DEPLOY_NO_MAINTENANCE=1</code>.</li>
        <li><span class="cma-tool__strong">Pre-pull recycle</span> — (Windows) touch op het recycle-bestand zodat de app-pool z'n file-locks op <code>vendor/</code> / bootstrap loslaat vóór git/composer draaien. Skip met <code>DEPLOY_NO_PRE_PULL_TOUCH=1</code>.</li>
        <li><span class="cma-tool__strong">Reset + pipeline</span> — <code>git checkout -- .</code> (tenzij <code>DEPLOY_NO_RESET=1</code>) gevolgd door de <code>;</code>-gescheiden <code>DEPLOY_PIPELINE</code> (default: <code>git pull --ff-only origin {branch}</code>; <code>{branch}</code> wordt ingevuld).</li>
        <li><span class="cma-tool__strong">Composer update</span> — <code>composer update &lt;DEPLOY_COMPOSER_UPDATE&gt; --no-dev --optimize-autoloader</code> zodat het platform-package mee-loopt. Composer-binary wordt over meerdere bekende paden geprobeerd (app-pool-identity heeft het zelden op PATH). Als <code>DEPLOY_COMPOSER_CLEAR_CACHE=1</code> staat (of de deploy-URL <code>?forcerefresh=Y</code> bevat) draait er eerst een <code>composer clear-cache</code>, zodat een net-gepushte platform-tag niet wordt gemist door een stale VCS-clone-cache. Andersom: een deploy die alleen consumer-/front-end-bestanden raakt (geen platform-update nodig) kan de héle composer-stap overslaan met <code>?nocomposer=Y</code> op de deploy-URL — git pull, test-gate, asset-build en recycle draaien gewoon door, dus zo'n deploy is in seconden klaar i.p.v. wachten op <code>composer update</code>.</li>
        <li><span class="cma-tool__strong">Test-gate</span> — als <code>DEPLOY_RUN_TESTS</code> gezet is draait dat NA pull/composer en VÓÓR recycle. Non-zero exit → deploy <code>FAILED</code>, geen recycle, geen post-hook: productie blijft op de oude code.</li>
        <li><span class="cma-tool__strong">Recycle</span> — touch op <code>DEPLOY_RECYCLE_TOUCH</code> (default <code>web.config</code>) om de IIS app-pool te recyclen.</li>
        <li><span class="cma-tool__strong">Health-check + post-hook</span> — best-effort <code>/cma/</code>-sync-check (<code>DeployHealth</code>) en het project-side <code>deploy_post.php</code> (cache-flushes, migraties) — alleen bij succes en alleen als <code>vendor/autoload.php</code> bestaat, zodat de hatch zelfstandig blijft.</li>
        <li><span class="cma-tool__strong">Logging + scream-loud</span> — alle output landt in <code>logs/deploy.log</code> (banner per run). Bij <code>FAILED</code> schreeuwt 'ie via álle dependency-vrije kanalen: <code>FAILED</code>-banner (zichtbaar via <code>deploy_status.php</code>), <code>error_log()</code>, een <code>logs/deploy.failed</code> marker-bestand, en — als <code>DEPLOY_ALERT_EMAIL</code> gezet is — een best-effort <code>mail()</code>. De marker wordt bij de eerstvolgende groene deploy gewist.</li>
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

    <h2>Remote deploy-status check</h2>
    <p>Publiek read-only endpoint dat de laatste run uit <code>logs/deploy.log</code> als JSON teruggeeft. Geen auth — status / commit-SHA / branch / timestamp zijn niet gevoelig (commit-SHAs staan al in de public git history, branch-namen ook). Credentials die een <code>DEPLOY_PIPELINE</code> kan echoën (een PAT in een <code>git remote set-url https://user:TOKEN@host</code>-stap, of een kale <code>github_pat_…</code>/<code>ghp_…</code>-token) zowel bij het schrijven (<code>deploy.php</code>) als bij het serveren (<code>deploy_status.php</code>) gemaskeerd — vertrouw dus niet meer op "de pipeline echoot toevallig geen secret". Volledig standalone: geen Composer autoload, geen platform-bootstrap, geen <code>.env</code>-reader — werkt dus ook als <code>vendor/</code> of <code>.env</code> stuk is.</p>
    <p>Het endpoint is de <span class="cma-tool__strong">site-root</span> <code>/deploy_status.php</code> (git-tracked, gesynct door de Installer net als <code>/deploy.php</code>) — die overleeft een kapotte <code>/cma/</code>, precies het scenario waarin je de status wilt checken. (De oude <code>/cma/tools/deploy_status.php</code> zat ónder <code>/cma/</code> — 404 als dat stuk is — en is vervallen; de Installer ruimt 'm op.)</p>
    <pre><code>curl 'https://&lt;host&gt;/deploy_status.php'</code></pre>
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
    <p><code>ok</code> weerspiegelt de deploy-<span class="cma-tool__strong">gezondheid</span>, niet alleen "log geparsed": een mislukte deploy geeft <code>{"ok": false, "status": "FAILED", …}</code> <span class="cma-tool__strong">zonder</span> <code>error</code>-veld. Transport-problemen geven óók <code>ok:false</code> maar mét een <code>error</code>-veld. Onderscheid dus: <code>error</code> aanwezig = endpoint-probleem; <code>status: "FAILED"</code> = de deploy zelf is mislukt.</p>
    <p>Foutgevallen:</p>
    <ul>
        <li>HTTP <code>200</code> met <code>{"ok": false, "status": "FAILED", …}</code> — de laatste deploy is mislukt; <code>log_tail</code> toont de omgevallen stap (geen <code>error</code>-veld — dat is voor endpoint-problemen).</li>
        <li>HTTP <code>404</code> met <code>{"ok": false, "error": "deploy.log not found", "path": "..."}</code> — de webhook heeft nog nooit op deze site gedraaid, of de <code>logs/</code> directory bestaat niet.</li>
        <li>HTTP <code>500</code> met <code>{"ok": false, "error": "log file unreadable", "path": "..."}</code> — bestand bestaat maar is niet leesbaar (permissies of lock).</li>
        <li>HTTP <code>200</code> met <code>{"ok": false, "error": "no completed deploy in log"}</code> — log-bestand bestaat wel maar er staat nog geen banner-bracketed run in.</li>
    </ul>

    <h3>Config self-check</h3>
    <p><code>?config=1</code> geeft een read-only diagnose van de deploy-configuratie terug — <span class="cma-tool__strong">wat</span> ontbreekt, nooit de waardes (<code>DEPLOY_SECRET</code> is enkel ja/nee) en zonder iets te schrijven. Handig vóór de eerste deploy, ook als <code>logs/deploy.log</code> nog niet bestaat.</p>
    <pre><code>curl 'https://&lt;host&gt;/deploy_status.php?config=1'
{
    "ok": true,
    "config": {
        "env_file":      ".env",         // welk .env-bestand is gelezen (null = geen)
        "deploy_secret": true,           // alleen aanwezig-ja/nee, nooit de waarde
        "deploy_branch": "main",
        "alert_email":   false,
        "logs_writable": true,
        "git":           true,
        "composer":      true,
        "missing":       [],             // ontbrekende verplichte items
        "ok":            true
    }
}</code></pre>
    <p>De waardes invullen doe je via de developer-only tool <span class="cma-tool__strong">Tools → Developer → Deploy setup</span> (<code>tools/tools_deploy_setup.php</code>): die toont dezelfde status, genereert een <code>DEPLOY_SECRET</code>-suggestie als er nog geen is, en schrijft de ingevulde <code>DEPLOY_*</code>-keys naar het actieve <code>.env</code>-bestand (eerst-bestaande, <code>.env</code> eerst, dan de legacy <code>.env.production</code>…). Bewust gescheiden: het publieke status-endpoint mag geen secrets innemen of config schrijven; dat hoort achter authenticatie.</p>

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
            <tr><td><code>DEPLOY_COMPOSER_CLEAR_CACHE</code></td><td><lib-label type="information">nee</lib-label></td><td><code>(uit)</code></td><td><code>1</code> = draai <code>composer clear-cache</code> VÓÓR de update. Nodig wanneer een net-gepushte platform-tag niet wordt opgepikt omdat Composer's VCS-clone-cache een oude ref vasthoudt (&ldquo;Nothing to modify&rdquo;). Uit by default (re-clone is trager); zet aan als een deploy een gloednieuwe release moet trekken. Best-effort: een falende clear-cache breekt de deploy niet af. <span class="cma-tool__strong">Eenmalig zonder <code>.env</code> te wijzigen</span>: voeg <code>?forcerefresh=Y</code> toe aan de deploy-URL (bijv. de GitHub-webhook Payload URL) — dezelfde cache-clear, gegate door dezelfde HMAC + branch-check.</td></tr>
            <tr><td><code>DEPLOY_RUN_TESTS</code></td><td><lib-label type="information">nee</lib-label></td><td><code>(leeg)</code></td><td>Commando dat NA <code>composer update</code> en VÓÓR recycle draait. Non-zero exit → deploy FAILED, geen recycle, geen post-hook (productie blijft op oude code). Voorbeeld: <code>php cma/tests/TestRunner.php</code>. Leeg / <code>-</code> = geen gate.</td></tr>
            <tr><td><code>DEPLOY_RECYCLE_TOUCH</code></td><td><lib-label type="information">nee</lib-label></td><td><code>web.config</code></td><td>Bestand om te touch'en na succes (IIS app-pool recycle). Set <code>-</code> om over te slaan.</td></tr>
            <tr><td><code>DEPLOY_NO_MAINTENANCE</code></td><td><lib-label type="information">nee</lib-label></td><td><code>(uit)</code></td><td><code>1</code> = zet géén onderhoudsscherm tijdens de deploy. Default aan: <code>deploy.php</code> zet een <code>maintenance.flag</code> in de site-root voor de muterende stappen; <code>_bootstrap.php</code> serveert dan een <code>503</code> "even onderhoud" tot de deploy klaar is (of de vlag ouder is dan 20 min). De pagina (<code>cma/maintenance_page.inc</code>) leest logo/naam/contact uit <code>data/maintenance.json</code> of <code>data/app.json</code> → sectie <code>maintenance</code>.</td></tr>
            <tr><td><code>DEPLOY_LOG_FILE</code></td><td><lib-label type="information">nee</lib-label></td><td><code>logs/deploy.log</code></td><td>Locatie van de deploy-log voor de webhook-writer. <span class="cma-tool__strong">Let op</span>: <code>deploy_status.php</code> leest alleen het default pad <code>logs/deploy.log</code> — een override hier wordt door de reader genegeerd.</td></tr>
            <tr><td><code>DEPLOY_POST_HOOK</code></td><td><lib-label type="information">nee</lib-label></td><td><code>deploy_post.php</code></td><td>Project-side PHP-script NA recycle. Cache-flushes, schema-migraties, image-profile backfills. Set <code>-</code> om over te slaan.</td></tr>
        </tbody>
    </table>
    <p>Actief env-bestand op deze site: <code><?= htmlspecialchars((string)($GLOBALS['_env_file'] ?? '.env')) ?></code>.</p>
    <p class="docs-meta">Alle bovenstaande variabelen worden door <code>/deploy.php</code> gelezen uit het eerst-bestaande <code>.env.production</code> / <code>.env.acceptance</code> / <code>.env.test</code> / <code>.env.local</code> / <code>.env</code> naast het bestand (inline-parser, geen phpdotenv). Extra schakelaars: <code>DEPLOY_NO_RESET=1</code> (sla de pre-pull <code>git checkout -- .</code> over), <code>DEPLOY_NO_PRE_PULL_TOUCH=1</code> (sla de pre-pull recycle over), <code>DEPLOY_SITE_ROOT</code> (git working tree), <code>DEPLOY_ALERT_EMAIL</code> (best-effort <code>mail()</code> bij FAILED).</p>

    <div class="docs-callout docs-callout--warn">
        <span class="cma-tool__strong">Migratie:</span> <code>/deploy.php</code> is nu het enige webhook-endpoint. De oude <code>cma/tools/deploy_webhook.php</code> (framework) én <code>cma/tools/deploy_webhook_standalone.php</code> zijn vervallen; de Installer verwijdert ze bij <code>composer update</code> (<code>REMOVED_PATHS</code>). <span class="cma-tool__strong">Her-richt elke GitHub-webhook die nog op één van die oude URLs staat naar <code>/deploy.php</code></span> — anders krijgt die 404 na de update.
    </div>

    <h2>GitHub-webhook instellen</h2>
    <ol>
        <li>GitHub repo → Settings → Webhooks → Add webhook.</li>
        <li>Payload URL: <code>https://&lt;host&gt;/deploy.php</code></li>
        <li>Content type: <code>application/json</code></li>
        <li>Secret: zelfde waarde als <code>DEPLOY_SECRET</code> in <code>.env</code>.</li>
        <li>Events: alleen "Push" events.</li>
    </ol>
    <p class="docs-meta">Response-codes van <code>/deploy.php</code>: <code>202</code> = geaccepteerd (async) — een push op een andere branch geeft óók <code>202</code> (Skipped, met reden in de log). <code>403</code> = ongeldige HMAC-signature (<code>DEPLOY_SECRET</code> mismatch met GitHub). <code>405</code> = geen POST. <code>503</code> = <code>DEPLOY_SECRET</code> niet geconfigureerd in de <code>.env</code>.</p>

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
            <tr><td>Webhook accepteert (202) maar niks gebeurt</td><td>Pipeline-fout. Kijk in <code>logs/deploy.log</code> onder de <code>RUN:</code> / <code>EXIT: 1</code> regels, of check <code>logs/deploy.failed</code> (de marker die <code>/deploy.php</code> bij een FAILED-deploy achterlaat). <code>deploy_status.php</code> geeft <code>status: FAILED</code> + <code>log_tail</code>.</td></tr>
            <tr><td>Webhook geeft "Parse error" / "Unsupported declare 'strict_types'"</td><td><code>/deploy.php</code> vereist PHP 7.1+ (gebruikt <code>declare(strict_types=1)</code> en list-assignment). Het draait op de site-root, dus onder de hoofd-PHP-handler van de site — zorg dat die een moderne PHP-versie is. (De oude PHP-5.6-compatibele <code>cma/tools/deploy_webhook_standalone.php</code> is vervallen.)</td></tr>
            <tr><td><code>vdev-main</code> in profielmenu i.p.v. versienummer</td><td><code>vendor/</code> niet ververst. Fix: <code>composer update stenversonline/platform</code>; webhook doet dit automatisch.</td></tr>
            <tr><td>Class "App\Library\Email" not found</td><td>Zelfde oorzaak — stale vendor. De bootstrap heeft een class_exists-guard zodat CMA niet crash't.</td></tr>
            <tr><td><code>/cma/dashboard</code> geeft 404, <code>/cma/dashboard.php</code> wel 200</td><td>CMA-routes ontbreken in de parent web.config (de bron van waarheid). Run <code>composer update stenversonline/platform</code> (past ze automatisch toe) of migratie <code>9.9.0</code> — zie <a href="documentation.php?topic=iis_config">IIS-configuratie</a>.</td></tr>
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
    <p><a href="tools.php?tool=backup" target="_top">Tools → Database backup</a> draait op <code>Cma\Services\BackupService</code>. Per geconfigureerde database maakt het een backup op de juiste manier:</p>
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

    <h2>Welke databases verschijnen in de lijst</h2>
    <p>De lijst komt uit <code>Bootstrap::loadDatabasesConfig()</code>, dus uit <code>data/databases.json</code>. Daarnaast vult de tool de logische connecties <code>data</code>/<code>users</code>/<code>rep</code> aan die <span class="cma-tool__em">niet</span> in databases.json staan maar wél door de runtime geconfigureerd zijn — via de legacy <code>conn_*</code>-globals of de standaard Access-bestanden (<code>db/main.mdb</code>, <code>db/CMAUsers.mdb</code>). Zo matcht de backuplijst exact de connecties die <code>Bootstrap::initDatabaseConnections()</code> opbouwt.</p>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:280px">Symptoom</th><th>Oorzaak &amp; oplossing</th></tr></thead>
        <tbody>
            <tr><td><code>Geen databases gevonden</code> terwijl de app wél een <code>data</code>-connectie heeft</td><td>De backup-tool moet meer lezen dan alléén databases.json, anders mist hij de <code>conn_*</code>/Access-default-laag die de runtime óók gebruikt. Sinds de fix worden geconfigureerde <code>data</code>/<code>users</code>/<code>rep</code>-connecties altijd aangevuld via <code>Database::isConfigured()</code>. Blijft de lijst leeg, dan is er écht geen connectie geregistreerd — controleer <code>data/databases.json</code> of de <code>conn_*</code>-globals in <code>app.php</code>.</td></tr>
        </tbody>
    </table>

    <h2>Pre-migration backups</h2>
    <p>Voordat <code>MigrationService</code> een database-wijzigende migration uitvoert, roept hij <code>BackupService::createMigrationBackup()</code> aan met de migration-versie als label. Die backups krijgen een vaste prefix zodat je in <a href="tools.php?tool=backup&tab=manage" target="_top">Backups beheren</a> snel de pre-migration snapshots terugvindt.</p>
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
    <p>De menu-tegel <span class="cma-tool__strong">Database → SQLite reparatie</span> verschijnt <span class="cma-tool__em">alleen</span> als er daadwerkelijk een SQLite-connectie in <code>databases.json</code> staat (type <code>sqlite</code> of een <code>sqlite:</code>/<code>.sqlite</code>/<code>.db</code>-connectionString; deprecated connecties tellen niet mee). Op Access/SQL Server/MySQL-sites is de tool verborgen — daar was hij zinloos en meldde hij altijd "alles goed" op een database die niet eens SQLite is. De gate zit in <code>cma_db_uses_sqlite()</code> in <code>tools_catalog.inc</code>.</p>

    <h2>MS Access compaction</h2>
    <p>Access (<code>.mdb</code>) DB's groeien onbegrensd na veel writes. Comprimeren (Access's "Compact &amp; Repair Database") kan <span class="cma-tool__strong">niet</span> via de ODBC-driver waarmee het platform draait — ODBC kent geen compact-commando, en de JRO/DAO-COM-route is niet beschikbaar in de PHP-omgeving. Comprimeer daarom periodiek (bij actieve sites ± eens per maand) <span class="cma-tool__strong">handmatig in Microsoft Access</span>: open de <code>.mdb</code> en kies <span class="cma-tool__em">Database Tools → Compact and Repair Database</span>. De losse "Database compacteren"-tool is verwijderd omdat hij niets deed (een stub die alleen het pad toonde).</p>

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
            <tr><td>PHP error log</td><td><code>.logs/phperrors/php_errors_&lt;datum&gt;.log</code></td><td>Alle uncaught exceptions, fatal errors, warnings (in dev), en <code>error_log()</code>-output. <span class="cma-tool__strong">Eén bestand per dag, 7 dagen bewaard.</span> De opruiming draait bij de eerste fout van een nieuwe dag, niet bij elke schrijfactie, en bepaalt de leeftijd aan de datum in de bestandsnaam — niet aan mtime, want een tool die het bestand opent zou het anders eeuwig levend houden.</td></tr>
            <tr><td>Deploy log</td><td><code>logs/deploy.log</code></td><td>Output van elke deploy-pipeline; banner per run. Override via <code>DEPLOY_LOG_FILE</code>.</td></tr>
            <tr><td>Application log</td><td><code>.logs/app/app_YYYY-MM-DD.log</code></td><td>Structured JSON-per-regel logs van <code>Cma\Services\Logger</code>. Productie: WARNING+; dev/test: DEBUG+. <code>$logDir</code> gezet door <code>Logger.php</code> line 88.</td></tr>
            <tr><td>Performance log</td><td><code>.logs/perf/perf_YYYY-MM-DD.log</code></td><td>Timing metrics van <code>PerformanceLogger</code> (queries, API-calls, memory). Aan via <code>PERF_LOG_ENABLED=true</code>. Locatie gezet door <code>PerformanceLogger.php</code> line 75.</td></tr>
            <tr><td>Debug log</td><td><code>.logs/debug/debug_YYYY-MM-DD.log</code></td><td>Verbose debug van <code>cma/api/log.php</code> wanneer browser-side <code>LibLog</code> debug-mode aan zet. <code>$logsDir</code> gezet door <code>api/log.php</code> line 44.</td></tr>
            <tr><td>404 log</td><td><code>.logs/404/404_YYYY-MM-DD.log</code></td><td>Niet-gevonden URLs gevangen door <code>cma/404.php</code>.</td></tr>
            <tr><td>Cache log</td><td><code>cache/cache.log</code></td><td>Cache-hit/-miss events. Aan via <code>CACHE_LOG_ENABLED=true</code>.</td></tr>
            <tr><td>JS errors (DB)</td><td>Tabel <code>tblCMAJavascriptErrors</code></td><td>Client-side errors gevangen door <code>CmaErrorHandler</code>. Rate-limited tot 100 per IP per uur.</td></tr>
        </tbody>
    </table>

    <div class="docs-callout docs-callout--warn">
        <span class="cma-tool__strong">Log-locaties (gesynchroniseerd):</span> alle logs staan onder de site-root <code>.logs/</code> met een submap per type — <code>.logs/perf/</code>, <code>.logs/debug/</code>, <code>.logs/404/</code>, <code>.logs/cache/</code>, <code>.logs/deploy/</code>, <code>.logs/app/</code>, <code>.logs/phperrors/</code> en <code>.logs/access/</code>. Writers én de logreader-UI lezen/schrijven dezelfde paden; de discrepantie (writers naar <code>data/logs</code>, reader naar <code>cache/perf_logs</code>/<code>cma/logs</code>) is opgelost.
    </div>

    <h2>Logreader</h2>
    <p>Alle file-based logs hebben een UI: <a href="logreader.php" target="_top">Tools → Logbestanden lezen</a>. Per log-type:</p>
    <ul>
        <li><span class="cma-tool__strong">PHP errors / Deploy / Cache</span> — plain text view, leegmaken via "Log leegmaken" toolbar-knop (truncate, niet delete, zodat de volgende write kan appenden). De PHP-error view kleurt per foutregel: entries met <code>[SQL ERROR]</code> (mislukte database-query, geschreven door <code>Database::logError()</code>), <code>PHP Fatal/Parse error</code> of <code>Uncaught</code> krijgen een rode rij; <code>[SQL WARN]</code> (bv. <code>SELECT *</code> waar Access ODBC een expliciete kolomlijst met MEMO-velden als laatste vereist) en <code>PHP Warning/Deprecated</code> een oranje rij. Typ <code>SQL ERROR</code> of <code>SQL WARN</code> in het filterveld om alleen die te tonen.</li>
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
    <p><code>cma/bootstrap.inc</code> doet bij elke admin-request een check op pending migrations. Resultaat surfacet boven de toolbar als rode banner als de check fataalde. PHP-error-log levert dan de details onder <code>[MigrationService]</code>.</p>

    <h2>Retentie</h2>
    <ul>
        <li>Application log: 30 dagen (<code>Logger::cleanup(30)</code>).</li>
        <li>Performance log: 7 dagen (<code>PerformanceLogger::cleanup(7)</code>).</li>
        <li>Andere logs (php_errors, deploy, debug, 404): platform doet niks automatisch. Stel een cleanup-job in als de schijf vol loopt.</li>
    </ul>
    <p>Wijst <code>php.ini</code>'s <code>error_log</code> rechtstreeks naar één vast bestand, dan geldt de dagrotatie hierboven daar
       niet voor: dat bestand groeit onbeperkt door. De check bovenaan deze pagina meldt dat apart.</p>

    <h2>Een groeiende error-log is een prestatieprobleem</h2>
    <p>Elke notice is een schrijfactie naar schijf. Een pagina die er per render honderden produceert &mdash; een lus over
       records waarin één veldnaam niet klopt, bijvoorbeeld &mdash; betaalt dat in I/O, en onder gelijktijdige requests loopt
       dat op tot time-outs en 500's. De inhoud is dus geen ruis maar de aanwijzing.</p>
    <table class="listtable">
        <tr><th>Symptoom</th><th>Oorzaak</th><th>Oplossing</th></tr>
        <tr>
            <td>Eén pagina is traag of geeft wisselend een 500, terwijl de query's snel zijn</td>
            <td>Herhaalde <code>Undefined property</code>- of <code>Undefined array key</code>-notices per record. In PHP zijn
                property-namen en array-sleutels <span class="cma-tool__strong">hoofdlettergevoelig</span> (methodenamen niet),
                dus <code>$obj-&gt;FooId</code> en <code>$obj-&gt;FooID</code> zijn twee verschillende velden. Uit een
                case-insensitive taal overgezette code raakt zo stil ontkoppeld: de lezer krijgt <code>null</code> en de
                bijbehorende functionaliteit valt uit.</td>
            <td>Meet de groei van de log over één request (<code>filesize</code> ervoor en erna). Staat er dezelfde notice
                honderden keren in, breng dan schrijver en lezers op één schrijfwijze en declareer de property op de klasse.
                Let op de cascade: hernoemen breekt lezers die de oude schrijfwijze gebruikten &mdash; controleer de log
                opnieuw ná de wijziging.</td>
        </tr>
    </table>

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

    <h2>Gemaskeerde geheimen op de diagnostiek-schermen</h2>
    <p>De <span class="cma-tool__strong">Applicatie instellingen</span>-tab op <a href="tools_serverinfo.php" target="_top">Server informatie</a> maskeert gevoelige waarden — dat geldt voor <span class="cma-tool__strong">zowel</span> de Applicatie- als de Server-instellingen (<code>$_SERVER</code>) tabel. Instellingen waarvan de naam matcht op <code>password</code>, <code>secret</code>, <code>token</code>, <code>api_key</code>/<code>apikey</code>, <code>access_key</code>, <code>*_key</code>, <code>credential</code>, <code>client_id</code>, <code>oauth</code>, <code>pipeline</code>, <code>authorization</code>, <code>php_auth</code>, <code>cookie</code> e.d. — óf waarvan de <span class="cma-tool__strong">waarde</span> eruitziet als een token/PAT (GitHub <code>ghp_…</code>/<code>github_pat_…</code>, <code>sk-…</code>, AWS <code>AKIA…</code>, Google <code>AIza…</code>, of een <code>user:pass@host</code>-URL) — tonen alleen de eerste 3 tekens gevolgd door sterretjes, met een slot-icoon (<span class="lnr lnr-lock"></span>) en het label &laquo;verborgen&raquo;. Zo is <span class="cma-tool__strong">verborgen-om-veiligheidsredenen</span> zichtbaar anders dan <code>(niet ingesteld)</code> (waarde daadwerkelijk leeg). De maskering gebeurt bij het renderen; de volledige waarde verlaat de server niet.</p>

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

    <h2>Autorisatie per formulier (API-endpoints)</h2>
    <p>Naast de globale login-gate (bootstrap geeft een 401 voor API's zonder sessie) dwingen de
    formulier-endpoints per-formulier autorisatie af via
    <code>SecurityHelper::currentUserFormRights($formId, $formName)</code> (menu-/groepsrechten,
    subforms vallen terug op het parent-formulier; admins krijgen <code>ACCESS_FULL_BEHEER</code>):</p>
    <table class="listtable">
        <thead><tr class="listheader"><th>Endpoint</th><th>Lezen</th><th>Schrijven/verwijderen</th></tr></thead>
        <tbody>
            <tr><td><code>cma/api/form_list.php</code></td><td>&ge; <code>ACCESS_READ</code></td><td>—</td></tr>
            <tr><td><code>cma/api/form_subform.php</code></td><td>&ge; <code>ACCESS_READ</code></td><td>—</td></tr>
            <tr><td><code>cma/api/form_record.php</code></td><td>&ge; <code>ACCESS_READ</code> (GET)</td><td>&ge; <code>ACCESS_FULL</code> (POST/DELETE)</td></tr>
        </tbody>
    </table>
    <p>Onvoldoende rechten geeft een HTTP 403 met <code>{"success": false, "error": "Geen toegang tot dit formulier"}</code>.
    Leesrechten (readonly) volstaan dus voor lijst- en record-GETs; mutaties vereisen volledige toegang.</p>
    <p>Ook de bewerk-affordances volgen (add/edit/delete-knoppen en inline switches) op
    JSON-formulierlijsten en config-subforms dezelfde rechten (&ge; <code>ACCESS_FULL</code>) in plaats van
    admin-only, en geldt voor formulieren zónder <code>sourceFormId</code> (config-/systeemformulieren)
    een rechten-check op de formuliernaam in plaats van een harde admin-eis.</p>

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

function render_doc_portability(): void
{
    ?>
    <h1>Portabiliteit (niet-IIS)</h1>
    <p class="docs-meta">Wat er vastzit aan IIS en aan Windows, en wat het kost om daar vanaf te komen.</p>

    <?php
    cma_doc_render_check_table('Deze site — live check', cma_doc_run_checks([
        'cma_doc_check_stack',
        'cma_doc_check_db_portability',
    ]));
    ?>

    <p>De rest van de documentatie gaat uit van IIS, omdat elke site daar vandaag op draait. Dat roept de vraag op wat er breekt bij een overstap naar Apache of Linux. Het korte antwoord: <span class="cma-tool__strong">de webserver is het makkelijke deel, de database is de blokkade</span>. Die twee lopen niet gelijk op en het loont om ze los te bekijken.</p>

    <h2>Drie lagen, drie verschillende antwoorden</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:150px">Laag</th><th>Gebonden aan</th><th>Kosten van de overstap</th></tr></thead>
        <tbody>
            <tr>
                <td><span class="cma-tool__strong">PHP-code</span></td>
                <td>Niets specifieks.</td>
                <td>Vrijwel nul. De plekken die het OS aangaan vertakken al expliciet — zie "Wat al portabel is" hieronder.</td>
            </tr>
            <tr>
                <td><span class="cma-tool__strong">Webserver</span></td>
                <td>IIS: <code>web.config</code>-rewriteregels, app-pool recycle, <code>auto_prepend</code>.</td>
                <td>Overzichtelijk. Alles heeft een Apache-equivalent; het is opnieuw opschrijven, niet herontwerpen.</td>
            </tr>
            <tr>
                <td><span class="cma-tool__strong">Database</span></td>
                <td><span class="cma-tool__strong">Windows</span>, niet IIS: Access via de ODBC-driver.</td>
                <td>Dit is het echte werk. Naar Linux betekent: eerst de data migreren.</td>
            </tr>
        </tbody>
    </table>

    <h2>De blokkade: Access, niet IIS</h2>
    <p><code>App\Library\Database</code> bouwt voor Access-verbindingen een DSN met <code>odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)}</code>. Die driver bestaat alleen op Windows. Op Linux is er geen ondersteund alternatief — mdbtools leest wel iets, maar niet betrouwbaar genoeg voor een applicatie die ook schrijft.</p>
    <p>Merk op waar de grens precies ligt: dit hangt aan <span class="cma-tool__em">Windows</span>, niet aan IIS. Apache op Windows draaien kan zonder één regel databasecode aan te raken. Naar Linux gaan kan niet zonder de <code>.mdb</code>-bestanden eerst naar MySQL of SQL Server te brengen.</p>
    <div class="docs-callout">
        Goed nieuws: de abstractie is er al. De verbindingslaag kent <code>mysql</code>, <code>sqlsrv</code>, <code>sqlite</code> en <code>odbc</code> naast elkaar, en welke een site gebruikt staat per verbinding in <a href="documentation.php?topic=json_config"><code>databases.json</code></a>. Het is dus een datamigratie plus het herschrijven van Access-specifieke SQL, niet het ombouwen van de laag zelf. Let daarbij op de Access-eigenaardigheden die het platform nu omzeilt: <code>TOP</code> in plaats van <code>LIMIT</code>, MEMO-kolommen die als laatste in een <code>SELECT</code> moeten staan, en <code>#</code>-datumliterals.
    </div>

    <h2>Wat je op de webserverlaag opnieuw moet maken</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:230px">Op IIS</th><th>Op Apache / nginx</th></tr></thead>
        <tbody>
            <tr>
                <td>Rewriteregels in <code>web.config</code> (friendly URLs, <code>/cma/dashboard</code>, formulier-deeplinks)</td>
                <td><code>.htaccess</code> of een vhost-blok met <code>mod_rewrite</code>. De PHP-kant verandert niet: <code>Cma\FormRoute</code> leest gewoon queryparameters, de rewrite levert die aan. Zie <a href="documentation.php?topic=routing">URL-routing</a> voor het contract dat je moet reproduceren.</td>
            </tr>
            <tr>
                <td><code>_bootstrap.php</code> als <code>auto_prepend</code></td>
                <td><code>auto_prepend_file</code> in <code>php.ini</code> of een <code>php_value</code>. Werkt hetzelfde, maar is dragend: valt de prepend weg, dan faalt élke pagina. Test dit als eerste na de overstap.</td>
            </tr>
            <tr>
                <td>App-pool recycle door <code>web.config</code> aan te raken — dat leegt OPcache en APCu na een deploy</td>
                <td>Bestaat niet. Vervang door <code>opcache_reset()</code> via een deploy-endpoint, of een <code>php-fpm</code>-reload. <span class="cma-tool__strong">Vergeet je dit, dan draait de site na een deploy stilletjes op de oude code</span> — zonder foutmelding. Zie <a href="documentation.php?topic=deployment">Deployment</a>.</td>
            </tr>
            <tr>
                <td>De web.config-checks in <a href="documentation.php?topic=iis_config">IIS-configuratie</a></td>
                <td>Die melden zich netjes af met <code>INFO</code> ("draait niet onder IIS") in plaats van een valse fout. Er staan alleen nog geen Apache-equivalenten tegenover — die checks moet je schrijven als je overstapt.</td>
            </tr>
            <tr>
                <td>Toegangsbeperking via <code>hiddenSegments</code> (<code>.env</code>, <code>composer.json</code>, <code>.sessions</code>)</td>
                <td><code>&lt;FilesMatch&gt;</code>/<code>Require all denied</code> in Apache, of een <code>location</code>-blok in nginx. Vergeet dit niet: dit is het enige dat je secrets buiten de browser houdt.</td>
            </tr>
        </tbody>
    </table>

    <h2>Wat al portabel is</h2>
    <ul>
        <li>Shell-aanroepen vertakken al op OS: <code>where</code> op Windows, <code>which</code> daarbuiten, en <code>cmd /c</code> alleen op Windows. Dat geldt voor de <code>cwebp</code>-detectie in <code>App\Library\Image</code> en voor de node/terser-lookup van de minify-stap.</li>
        <li><code>App\Library\Server</code>, <code>ErrorHandler</code> en <code>Database</code> checken <code>PHP_OS_FAMILY</code> respectievelijk <code>DIRECTORY_SEPARATOR</code> waar het uitmaakt — de hardgecodeerde <code>C:\...\cwebp.exe</code>-paden staan achter zo'n check en worden op Linux niet eens geprobeerd.</li>
        <li>Padopbouw gaat via <code>__DIR__</code> en <code>DIRECTORY_SEPARATOR</code>, niet via letterlijke backslashes.</li>
    </ul>

    <div class="docs-callout docs-callout--warn">
        <span class="cma-tool__strong">Let op bij Linux: het bestandssysteem is hoofdlettergevoelig.</span> Een <code>require</code> die qua schrijfwijze afwijkt van de bestandsnaam werkt op Windows en faalt op Linux. In een uit ASP geconverteerde codebase is dat een reëel risico, en je ziet het op Windows nooit aankomen. Hetzelfde geldt voor <code>.inc</code>-includes en voor asset-URL's die vanuit de browser komen. Controleer dit vóór de overstap, niet erna.
    </div>

    <h2>Volgorde als je het echt doet</h2>
    <ol>
        <li>Eerst de databases: Access → MySQL of SQL Server, inclusief het herschrijven van Access-specifieke SQL. Zolang dit niet klaar is, is Linux geen optie.</li>
        <li>Dan Apache <span class="cma-tool__em">op Windows</span> als tussenstap: dat isoleert de rewrite- en prepend-problemen van de databasemigratie, zodat je niet twee onbekenden tegelijk debugt.</li>
        <li>Pas daarna naar Linux, met de hoofdlettergevoeligheid als eerste controlepunt.</li>
        <li>Tot slot de deploy-pijplijn: de cache-flush moet mee, anders lijkt alles te werken tot iemand merkt dat een wijziging niet landt.</li>
    </ol>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=iis_config">IIS-configuratie</a> (wat er nu in web.config staat), <a href="documentation.php?topic=database">Database &amp; RecordSet</a> (de verbindingslaag), <a href="documentation.php?topic=deployment">Deployment</a> (recycle + cache-flush), <a href="documentation.php?topic=routing">URL-routing</a> (het contract dat rewrites moeten leveren).
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
        'cma_doc_check_parent_cma_routes',
        'cma_doc_check_url_rewrite_module_active',
        'cma_doc_check_parent_skip_cma',
        'cma_doc_check_parent_default_content_type',
        'cma_doc_check_parent_nosniff',
        'cma_doc_check_parent_frame_options',
        'cma_doc_check_parent_hidden_segments',
        'cma_doc_check_iis_version',
        'cma_doc_check_server_header',
        'cma_doc_check_powered_by',
        'cma_doc_check_child_default_content_type',
        'cma_doc_check_child_404_handler',
    ]));
    ?>

    <h2>De "Los op"-knoppen</h2>
    <p>Een deel van de checks hierboven heeft een knop die de wijziging meteen doorvoert in de <span class="cma-tool__strong">site-root</span> <code>web.config</code>. Daarna wordt de pagina ververst, zodat je de regel van rood naar groen ziet gaan in plaats van op een melding te moeten vertrouwen. Dat kan alleen voor dat bestand: het is PROJECT-owned — de Installer schrijft er nooit in — dus het drift, en de alternatieve route is met de hand XML uit een doc-snippet overtikken.</p>
    <table class="listtable">
        <thead><tr class="listheader"><th>Wel met een knop</th><th>Niet — en waarom</th></tr></thead>
        <tbody>
            <tr>
                <td>
                    <ul>
                        <li>Rewrite-regel <code>Skip /cma to child config</code> (wordt bovenaan <code>&lt;rules&gt;</code> gezet — hij moet vóór de eigen friendly-URL-regels van de site staan)</li>
                        <li>Outbound rule <code>Default Content-Type to text/html</code> + bijbehorende <code>preCondition</code></li>
                        <li><code>X-Content-Type-Options: nosniff</code> en <code>X-Frame-Options: SAMEORIGIN</code></li>
                        <li>Ontbrekende <code>hiddenSegments</code> (<code>.env</code>, <code>composer.json</code>, <code>composer.lock</code>, <code>.sessions</code>)</li>
                    </ul>
                </td>
                <td>
                    <ul>
                        <li><span class="cma-tool__strong">IIS URL Rewrite Module</span> — dat is een MSI-installatie op de server, geen configuratie.</li>
                        <li><span class="cma-tool__strong">CMA-routes</span> in de parent config — die komen uit <code>composer update</code> / migratie 9.9.0. Hier schrijven zou met de Installer vechten.</li>
                        <li><span class="cma-tool__strong">Child <code>cma/web.config</code></span> — platform-owned; de Installer overschrijft je wijziging bij de eerstvolgende update. Doe <code>composer update</code>.</li>
                    </ul>
                </td>
            </tr>
        </tbody>
    </table>
    <div class="docs-callout docs-callout--warn">
        Elke fix maakt eerst een backup naast het bestand (<code>web.config.bak-&lt;datum&gt;-&lt;tijd&gt;</code>), is idempotent (nog eens klikken doet niets), en zet de backup terug als het resultaat geen geldige XML zou zijn. Een bestaande waarde wordt <span class="cma-tool__strong">nooit</span> overschreven — staat er al <code>X-Frame-Options: DENY</code>, dan blijft die staan. Let op: het schrijven van <code>web.config</code> recyclet de app-pool; de eerstvolgende request is daardoor traag. Gedrag is afgedekt door <code>cma/tests/DocFixWebConfigTest.php</code>.
    </div>

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
        <span class="cma-tool__strong">Skip /cma to child config</span> — deze regel staat standaard in de <code>templates/web.config.template</code> die nieuwe installs krijgen:
        <pre><code>&lt;rule name="Skip /cma to child config" stopProcessing="true"&gt;
    &lt;match url="^cma($|/)" /&gt;
    &lt;action type="None" /&gt;
&lt;/rule&gt;</code></pre>
        Voor BESTAANDE installs moet je 'm handmatig toevoegen bovenaan <code>&lt;rules&gt;</code> in de site-root web.config (of NA HTTPS/www redirects, zodat <code>/cma/*</code> nog steeds geforceerd naar HTTPS gaat). Symptoom als de regel ontbreekt: <code>/cma/dashboard</code> geeft 404, maar <code>/cma/dashboard.php</code> wel 200.
    </div>

    <div class="docs-callout docs-callout--danger">
        <p><span class="cma-tool__strong">CMA-routes leven in het parent web.config</span> — niet meer in <code>cma/web.config</code>. Eerdere pogingen om dit via distributed rules in de child-config op te lossen liepen vast op (1) inheritance-issues bij Virtual Directory setup, (2) outbound-rule duplicate-name conflicts (500.50), (3) niet-matchende patterns wanneer <code>cma/</code> geen IIS Application is. De definitieve fix is migration <code>9.9.0_cma_routes_to_parent_webconfig.php</code> die de rewrite-rules direct in de parent zet (waar IIS er altijd bij kan zonder scope-complicaties). Idempotent via marker-comment, backup wordt automatisch gemaakt. De migratie valideert de gepatchte config vóór én na de write — XML well-formedness, duplicate rule-names (het 500.50-symptoom), PCRE-syntax van de eigen patterns, read-back, en tenslotte een live HTTP smoke-test op <code>/cma/dashboard</code>; bij een 5xx rolt hij automatisch terug uit de backup. (De <code>appcmd</code> schema-check is advisory: draait de migratie als de app-pool-identity dan mag appcmd de centrale IIS-config vaak niet lezen — exit 5 "insufficient permissions" — wat géén afkeuring van de patch is en dus NIET terugrolt.) Run via <a href="documentation.php?topic=migrations">Migraties</a>-tool of <code>Tools → Migraties uitvoeren</code>.</p>
        <p style="margin:8px 0 0 0;">De Composer <code>Installer</code> past diezelfde routes óók automatisch toe bij elke <code>composer update stenversonline/platform</code> — bestaande sites krijgen de fix dus zonder de migratie handmatig te draaien. De file-level safeguards (simplexml-check, XML well-formedness, duplicate-name, PCRE-regex, backup, atomic write, read-back, rollback) zitten in de gedeelde helper <code>App\Library\WebConfigCmaRoutes</code> die migratie én Installer delen; de <code>appcmd</code>- en live-smoke-test-stappen blijven migration-only (de composer-CLI heeft geen draaiende IIS + HTTP-context). Idempotent via dezelfde marker, dus veilig om elke update te draaien.</p>
    </div>

    <h2>cma/web.config in detail</h2>
    <p>De platform-side web.config doet drie dingen:</p>
    <ol>
        <li><span class="cma-tool__strong">Friendly-URL rewrites</span>: <code>^dashboard/?$</code>, <code>^preferences/?$</code>, <code>^tools/?$</code>, <code>^form/&lt;...&gt;</code> → <code>cma/main.php?page=&lt;target&gt;.php</code> (met <code>appendQueryString="true"</code>). <code>main.php</code> bootstrapt zichzelf. Bewust <span class="cma-tool__strong">geen</span> <code>HTTP_X_ORIGINAL_FILE</code>/server-variabele meer: een rewrite die een niet-toegestane server-variabele zet faalt stil → 404, dus zonder die variabele werkt <code>/cma</code>-routing op elke server zonder <code>allowedServerVariables</code>-unlock.</li>
        <li><span class="cma-tool__strong">Default document</span>: <code>default.php</code> en <code>index.php</code>.</li>
        <li><span class="cma-tool__strong">404 handler</span>: <code>/cma/404.php</code> via <code>responseMode="ExecuteURL"</code>. Dat script logt de miss en redirect verplaatste iconen; de pagina zelf komt uit <code>cma/notfound_page.inc</code>, zodat de CMA en de eigen 404-handler van een site hetzelfde scherm tonen. Die laatste zet <code>$notFoundHomeUrl</code>/<code>$notFoundHomeLabel</code> en, voor een ontbrekend bestand i.p.v. een pagina, <code>$kind = 'file'</code>. <code>existingResponse="Auto"</code> — alleen het custom 404-pagina laden als de app niks gerendered heeft. NOOIT <code>existingResponse="Replace"</code> gebruiken — dat triggert een infinite-loop wanneer 404.php zelf een 404-status terugstuurt.</li>
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
        <li><code>removeServerHeader="true"</code> op <code>&lt;requestFiltering&gt;</code> — bedoeld om de <code>Server</code>-header te onderdrukken. <span class="cma-tool__strong">Werkt niet overal</span>, zie hieronder.</li>
    </ul>

    <h3>Server-header: werkt niet op elke IIS-versie</h3>
    <?php
    $iisVersion = cma_doc_iis_version();
    if ($iisVersion !== null && $iisVersion >= 10):
    ?>
        <lib-message type="success">
            Deze server draait <code><?= htmlspecialchars((string)($_SERVER['SERVER_SOFTWARE'] ?? '')) ?></code>, dus
            <code>removeServerHeader="true"</code> werkt hier gewoon. Het stuk hieronder gaat over oudere servers —
            relevant als je deze <code>web.config</code> naar zo'n machine kopieert.
        </lib-message>
    <?php elseif ($iisVersion !== null): ?>
        <lib-message type="error">
            Deze server draait <code><?= htmlspecialchars((string)($_SERVER['SERVER_SOFTWARE'] ?? '')) ?></code>.
            Daar bestaat <code>removeServerHeader</code> <span class="cma-tool__strong">niet</span>: staat het in je
            <code>web.config</code>, dan is de site nu of straks onbereikbaar met een <code>500.19</code>.
            Gebruik een van de twee alternatieven hieronder.
        </lib-message>
    <?php else: ?>
        <lib-message type="information">
            Serverversie niet vast te stellen vanuit PHP (<code>SERVER_SOFTWARE</code> ontbreekt of dit is geen IIS).
            Lees hieronder welke aanpak bij jouw versie hoort.
        </lib-message>
    <?php endif; ?>
    <p>De <code>removeServerHeader</code>-optie bestaat pas vanaf <span class="cma-tool__strong">IIS 10.0</span> (Windows Server 2016 / Windows 10). Op oudere IIS-versies kent het configuratieschema dat attribuut niet, en dan gaat het niet stilletjes mis: IIS weigert de hele <code>web.config</code> met een <code>500.19 — Unrecognized attribute 'removeServerHeader'</code>. De site is dan dus <span class="cma-tool__strong">volledig onbereikbaar</span>, niet alleen de header staat er nog.</p>
    <p>Wat je op een oudere server in plaats daarvan doet — beide werken ook naast elkaar:</p>
    <ul>
        <li><span class="cma-tool__strong">Outbound rewrite</span> (vereist de URL Rewrite Module). Zet <code>rewriteBeforeCache="true"</code> op <code>&lt;outboundRules&gt;</code>, anders mist de regel responses die uit de kernel-cache komen:
            <pre><code>&lt;outboundRules rewriteBeforeCache="true"&gt;
    &lt;rule name="Remove Server header"&gt;
        &lt;match serverVariable="RESPONSE_Server" pattern=".+" /&gt;
        &lt;action type="Rewrite" value="" /&gt;
    &lt;/rule&gt;
&lt;/outboundRules&gt;</code></pre>
            Let op: dit raakt alleen responses die door de managed pipeline gaan. Antwoorden die http.sys zelf genereert (sommige 400-fouten, request-filtering afwijzingen) dragen de header alsnog.</li>
        <li><span class="cma-tool__strong">Registry</span>, server-breed en dus wél voor http.sys-antwoorden: <code>HKLM\SYSTEM\CurrentControlSet\Services\HTTP\Parameters</code> → DWORD <code>DisableServerHeader = 1</code>, daarna reboot of <code>net stop http /y &amp;&amp; net start w3svc</code>. Dit is een servergerelateerde ingreep, geen site-instelling — overleg met de beheerder van de machine.</li>
    </ul>
    <h3>X-Powered-By verdwijnt niet via web.config</h3>
    <p>In <code>&lt;customHeaders&gt;</code> staat <code>&lt;remove name="X-Powered-By" /&gt;</code>, maar dat haalt alleen weg wat <span class="cma-tool__em">IIS</span> toevoegt. Staat <code>expose_php</code> aan, dan zet PHP zelf een <code>X-Powered-By: PHP/8.4.5</code> op elke response en die glipt er dwars doorheen — je hebt de webserverversie verborgen en de PHP-versie alsnog gepubliceerd. Zet daarom <code>expose_php = Off</code> in <code>php.ini</code> en recycle de app-pool. De check-tabel op <a href="documentation.php?topic=iis_config">IIS-configuratie</a> laat zien of deze site hem nog verstuurt.</p>

    <p>Omdat dit per server verschilt, staat er in de check-tabel op <a href="documentation.php?topic=iis_config">IIS-configuratie</a> een live meting: die doet een request op de eigen site en laat zien of er nog een <code>Server</code>-header meekomt. Vertrouw die meting, niet de aanwezigheid van het attribuut in <code>web.config</code>.</p>

    <h2>Belt-and-suspenders: default Content-Type</h2>
    <p>In <code>templates/web.config.template</code> — en uitsluitend daar, niet in <code>cma/web.config</code> — staat een outbound-rewrite die een lege <code>Content-Type</code> respons-header overschrijft naar <code>text/html; charset=UTF-8</code>:</p>
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
    <p>Waarom: als PHP doodgaat vóór een header gezet is (fatal error, lege <code>default_mimetype</code>, output-buffer breuk), gaat de response zonder Content-Type de deur uit. Mobile Safari — vooral als geïnstalleerde PWA — combineert dit met onze <code>X-Content-Type-Options: nosniff</code> en weigert MIME-sniffing, met als gevolg dat de pagina als <span class="cma-tool__strong">download</span> wordt aangeboden ("Download logreader.php?"). De regel vuurt alleen als de header echt leeg is — door PHP gezette Content-Types blijven onaangeroerd.</p>

    <h2>App-pool recycle</h2>
    <p>Touch op <code>web.config</code> triggert een app-pool recycle in IIS. Het deploy-webhook script doet dit standaard na een succesvolle pipeline; je kan het handmatig forceren met:</p>
    <pre><code>copy /b web.config +,, </code></pre>
    <p>Of via de tool <a href="tools/opcache_reset.php" target="_top">Tools → OPcache reset</a>.</p>
    <p><span class="cma-tool__strong">Gecompileerde Twig-templates</span>: leegt <a href="tools.php?tool=clearcache" target="_top">Cache leegmaken</a> ook <code>.cache/twig</code> (recursief) en toont het die als eigen regel <span class="cma-tool__strong">Twig</span> — maar alléén op sites waar die map bestaat. Twig's <code>auto_reload</code> hercompileert normaal vanzelf bij een gewijzigde <code>.twig</code>-mtime; je hebt de handmatige clear nodig na een deploy die templates met een óudere mtime terugzet, of na een wijziging in de Twig-opties, filters of globals — die worden namelijk in de gecompileerde PHP-klasse gebakken.</p>
    <p><span class="cma-tool__strong">Let op — een recycle flusht OPcache niet altijd.</span> Onder PHP-FastCGI worden de <code>php-cgi.exe</code>-processen (en dus de OPcache-SHM) alleen herstart als IIS ze echt recyclet. Twee veelvoorkomende oorzaken dat dat níét gebeurt: (1) de FastCGI-instelling <code>monitorChangesTo</code> in <code>applicationHost.config</code> wijst naar <code>php.ini</code> i.p.v. het getouchte <code>web.config</code>, dus een touch op web.config herstart PHP niet; (2) <code>opcache.file_cache</code> staat aan, waardoor OPcache zich na een herstart meteen weer vanaf schijf vult. Los daarvan geeft <code>opcache_reset()</code> vaak <code>false</code> terug wanneer <code>opcache.restrict_api</code> is gezet op een pad buiten de CMA. Het scherm <a href="tools.php?tool=clearcache" target="_top">Cache leegmaken</a> toont een tabel <span class="cma-tool__strong">OPcache configuratie</span> die precies deze drie instellingen leest en markeert; een reset die alleen via de web.config-fallback verliep krijgt daar (en in de statusrij) een ⚠ i.p.v. een groen vinkje. <code>.user.ini</code> kan deze <code>PHP_INI_SYSTEM</code>-instellingen niet overschrijven — alleen <code>opcache.validate_timestamps</code>/<code>revalidate_freq</code> zijn daar te zetten (zet <code>validate_timestamps=1</code> zodat gewijzigde bestanden vanzelf worden opgepikt).</p>

    <h2>Troubleshooting</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak / Fix</th></tr></thead>
        <tbody>
            <tr><td><code>/cma/dashboard</code> → 404, <code>/cma/dashboard.php</code> werkt wél</td><td>De CMA-routes leven in het <span class="cma-tool__strong">parent</span> web.config (zie callout boven). Meest voorkomende oorzaken: (1) <span class="cma-tool__strong">CMA-routes ontbreken in de parent web.config</span> — run <code>composer update stenversonline/platform</code> (past ze automatisch + fail-safe toe) of migratie <code>9.9.0</code>; de live-check "Parent web.config: CMA friendly-URL routes" bovenaan toont dit direct. (2) <span class="cma-tool__strong">URL Rewrite Module is niet meer geïnstalleerd</span> (Windows-update kan het verwijderen) — herinstalleer van <a href="https://www.iis.net/downloads/microsoft/url-rewrite" target="_blank" rel="noopener">iis.net/downloads/microsoft/url-rewrite</a>. (3) <span class="cma-tool__strong"><code>applicationHost.config</code> heeft <code>&lt;section name="rewrite" overrideMode="Deny"/&gt;</code></span> waardoor web.config-rewrites genegeerd worden — zet om naar <code>Allow</code>. <span class="cma-tool__strong">Niet meer nodig:</span> cma/ als IIS Application inrichten — de parent-routes werken ongeacht of cma/ een Application of Virtual Directory is.</td></tr>
            <tr><td><code>/cma/preferences</code> → 404 (en <code>/cma/dashboard</code> ook)</td><td>Zelfde diagnose als hierboven — alle extensionless URLs in de child-config falen samen.</td></tr>
            <tr><td><code>/cma/dashboard</code> → 404 op nieuwe install</td><td>"Skip /cma" rule ontbreekt in parent web.config. Zie callout hierboven en <a href="documentation.php?topic=iis_config">live-check</a> bovenaan deze pagina.</td></tr>
            <tr><td><code>/cma/dashboard.php</code> → 500 Server Error</td><td>Allowed server variables niet ontgrendeld. Zie <a href="documentation.php?topic=installation">Installatie</a>.</td></tr>
            <tr><td><code>/cma/tools/&lt;naam&gt;</code> → 404 maar <code>?tool=&lt;naam&gt;</code> werkt wel</td><td>URL Rewrite Module ontbreekt of de "CMA Tools Friendly URL" regel staat niet in de site-root web.config.</td></tr>
            <tr><td><code>/cma/tools?tool=X</code> verliest de <code>?tool=X</code></td><td>De Tools Directory rewrite-rule in <code>cma/web.config</code> mist <code>appendQueryString="true"</code>. Dit staat standaard aan — run <code>composer update stenversonline/platform</code>.</td></tr>
            <tr><td>Site geeft IIS default 404, niet cma/404.php</td><td><code>cma/404.php</code> bestaat niet op disk (Installer-sync incompleet). Run <code>composer update stenversonline/platform</code>.</td></tr>
            <tr><td>Mobile Safari prompts "Download logreader.php?"</td><td>Gefixed via @-suppress op file_put_contents in delete-handler zodat warnings niet de Location-redirect breken.</td></tr>
            <tr><td>OPcache/APCu lijkt nooit leeg — "Gecachte scripts" (of het APCu/App-cache aantal) blijft na Cache leegmaken op hetzelfde aantal staan</td><td><span class="cma-tool__strong">Eerst onderscheiden of dit normaal is of een echte fout.</span> De getoonde aantallen zijn de stand <span class="cma-tool__em">vóór</span> het legen; <code>_bootstrap.php</code> wordt bij élk request auto-geprepend en vult OPcache/APCu/App-cache meteen weer met de kern-werkset, dus een gelijk blijvend aantal is meestal gewoon normaal. <span class="cma-tool__strong">APCu/App-cache</span> worden synchroon geleegd: de regel <span class="cma-tool__em">Direct na legen</span> (onder "Toon details") staat dan direct op 0. <span class="cma-tool__strong">OPcache flusht daarentegen uitgesteld</span>: <code>opcache_reset()</code> plant enkel een herstart die pas bij het vólgende request gebeurt, dus de scripttelling blijft in dít request staan (bv. 146 → 146) óók als de reset slaagde — dat is dus géén bewijs van falen. Kijk daarom niet naar de telling maar naar <span class="cma-tool__em">Reset uitgevoerd</span> (opcache_reset gaf true) en <span class="cma-tool__em">Herstart gepland</span> (restart_pending = ja) onder "Toon details". Staat <span class="cma-tool__em">Reset uitgevoerd</span> op <span class="cma-tool__strong">nee</span> → dan nam de reset écht niet: <code>opcache_reset()</code> geeft <code>false</code> terug (meestal <code>opcache.restrict_api</code> gezet op een pad buiten de CMA), en/of de web.config-touch recyclet de FastCGI-processen niet (<code>monitorChangesTo</code> wijst naar <code>php.ini</code>), en/of <code>opcache.file_cache</code> herlaadt de bytecode meteen weer vanaf schijf. Lees dan de tabel <span class="cma-tool__strong">OPcache configuratie</span> op datzelfde scherm: die markeert welke van de drie het is. Fix in <code>php.ini</code>/<code>applicationHost.config</code> (restrict_api leegmaken of CMA-pad opnemen; monitorChangesTo naar het getouchte bestand wijzen). Zie de sectie <span class="cma-tool__strong">App-pool recycle</span> hierboven.</td></tr>
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
        <li><span class="cma-tool__strong">Bootstrap::init()</span> draait in volgorde: <code>initEncoding</code>, <code>initSession</code>, <code>loadConstants</code>, <code>detectAndLoadEnv</code> (kiest welke .env), <code>configureErrorDisplay</code> (op basis van omgeving), <code>sqliteEmergencyRecovery</code> (als de flag staat), <code>loadDotenv</code> (ingebouwde <code>EnvFile</code>-parser, geen phpdotenv), <code>initApplication</code> (zet <code>$GLOBALS['Application']</code> op), <code>registerErrorHandler</code>, en de loadLegacy* steps.</li>
        <li><span class="cma-tool__strong">cma/bootstrap.inc</span> wordt door tools/admin-pagina's met <code>require_once __DIR__ . '/../bootstrap.inc'</code> geladen — definieert <code>CMA_APP_VERSION</code>, laadt alle <code>Cma\</code> classes via require_once, registreert <code>EmailLogService</code> afterSend hook (met <code>class_exists</code> guard), doet migratie-controle voor admins.</li>
        <li><span class="cma-tool__strong">Het target script</span> (de tool / form.php / main.php) draait. Na een succesvolle login landt de gebruiker op <code>/cma/main.php</code> (niet op de <code>/cma/dashboard</code> friendly-URL). Gebruikers- en groepenbeheer staat onder <span class="cma-tool__strong">Alle beheerstools → Gebruikers en groepen</span>.</li>
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
        <li><code>MenuService</code> — leest bij voorkeur <code>data/cma_menu.json</code> via <code>CONFIG_PATH</code>, met legacy-terugval naar <code>data/menu.json</code> (géén fallback naar <code>cma/config/</code>; ontbreken beide, dan is het menu leeg). Zie <a href="documentation.php?topic=json_config">JSON-configuratie</a>.</li>
        <li><code>SystemSettings</code> — leest env-vars zoals <code>PERF_LOG_ENABLED</code> / <code>CACHE_LOG_ENABLED</code> + persisted UI-settings.</li>
    </ul>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=new_tool">Een CMA-tool toevoegen</a>, <a href="documentation.php?topic=database">Database &amp; RecordSet</a>.
    </div>
    <?php
}

function render_doc_routing(): void
{
    ?>
    <h1>URL-routing &amp; deep links</h1>
    <p class="docs-meta">Hoe een schone URL als <code>/cma/form/&lt;form&gt;/&lt;id&gt;</code> de lijst (de actieve view) én het record opent — en waar dat kan misgaan.</p>

    <p>Er is <span class="cma-page__strong">één canoniek URL-contract</span> met twee helften die elkaar spiegelen:</p>
    <ul>
        <li><span class="cma-page__strong">Server</span> — <code>Cma\FormRoute</code> (<code>cma/classes/FormRoute.php</code>) normaliseert <span class="cma-page__strong">elke</span> parameter-spelling die de rewrite-rules of legacy-links kunnen sturen naar één route-state. <code>form.php</code> leest routing <span class="cma-page__strong">alleen</span> hieruit.</li>
        <li><span class="cma-page__strong">Client</span> — <code>cma/assets/js/url-manager.js</code> (<code>window.CMA.url</code>) is de enige plek die schone URL's parseert (<code>parse</code>) en bouwt (<code>build</code> voor de browser-URL, <code>toPageUrl</code> voor de interne <code>form.php</code>-fetch).</li>
    </ul>

    <h2>De keten</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:230px">Stap</th><th>Wat er gebeurt</th></tr></thead>
        <tbody>
            <tr><td><code>cma/web.config</code> (IIS rewrite)</td><td><code>/cma/form/opleidingen/4984</code> → <code>main.php?page=form.php?form=opleidingen&amp;formID=4984</code>. De record-id komt binnen als <code>formID</code>; subforms als <code>popup</code>/<code>popupID</code>.</td></tr>
            <tr><td><code>main.php</code> (nomenu)</td><td>Splitst de <code>page</code>-querystring, zet de params in <code>$_GET</code>, en <code>include</code>t <code>form.php</code>.</td></tr>
            <tr><td><code>Cma\FormRoute::fromRequest()</code></td><td>Leest <code>form</code>, en de record-id uit (in volgorde) <code>ID</code> → <code>id</code> → <code>formID</code>; <code>new</code> → nieuw record; <code>popup</code>/<code>popupID</code> → subform met parent-id uit <code>formID</code>; <code>New=Y</code>, <code>copy=Y</code>, <code>parentID</code>, <code>parentField</code>, <code>view</code>.</td></tr>
            <tr><td><code>form.php</code></td><td>Zet <code>data-record-id</code> + body-classes (<code>has-record</code>, <code>mode-tree/table</code>) op basis van de route. De controller laadt het record náást de lijst.</td></tr>
        </tbody>
    </table>

    <h2>De root-cause die dit oploste</h2>
    <p>De rewrite-rules stuurden de record-id als <code>formID</code>, maar <code>form.php</code> las een record-id alleen uit <code>id</code>/<code>ID</code> — en <code>formID</code> alléén binnen de popup-tak (als parent-id). Een top-level deep link <code>/cma/form/&lt;form&gt;/&lt;id&gt;</code> liet daardoor wél de lijst zien maar opende het record nooit; het werkte alleen toevallig als de client de URL eerst herschreef. Door <code>FormRoute</code> <code>formID</code> als eersteklas record-id te laten lezen, opent de deep link nu server-side — ongeacht welke JS-bundle een consumer-site draait.</p>

    <h2>Actieve view, geen geforceerde tree</h2>
    <p><code>toPageUrl()</code> forceert bewust <span class="cma-page__strong">geen</span> <code>view=tree</code> meer. Zonder expliciete <code>?view=</code> kiest <code>form.php</code> de persisted lijst-modus (cookie <code>cma_listMode_&lt;form&gt;</code>, fallback <code>cma_lastViewMode</code>) — dus de deep link opent de lijst in de view die de gebruiker laatst gebruikte.</p>

    <h2>Geen dubbele routes voor formulieren</h2>
    <p>Een paar beheer-entiteiten (<code>users</code>, <code>groups</code>, <code>contentblocks</code>, <code>_menus</code>, <code>cmamonitoring</code>, <code>marketingurl</code>) waren zowel als gewone form-route (<code>/cma/form/&lt;form&gt;</code>) áls als tool-alias (<code>/cma/tools/&lt;naam&gt;</code>, gerenderd in de tools-iframe) bereikbaar — twee routes, twee layouts, één formulier. De alias-namen staan nu in één bron, <code>$formBackedTools</code> in <code>tools.php</code>:</p>
    <ul>
        <li>Een deep link <code>/cma/tools/users</code> (of <code>?tool=users</code>) <span class="cma-page__strong">redirect</span> naar de canonieke <code>/cma/form/users</code>.</li>
        <li>De items in het tools-menu voor deze entiteiten navigeren het hoofdvenster naar <code>/cma/form/&lt;form&gt;</code> (via <code>loadPage</code>) in plaats van het formulier full-width te laden.</li>
    </ul>
    <p>Zo is er één route en één layout per formulier. Voeg je een nieuwe form-backed tool toe, zet 'm dan alleen in <code>$formBackedTools</code> (niet in <code>$toolNameMap</code>).</p>

    <lib-message type="info">
        <span class="cma-page__strong">Wijzig je het contract?</span> Houd <code>Cma\FormRoute</code> en <code>url-manager.js</code> (<code>parseUrl</code>/<code>toPageUrl</code>) in lock-step — het zijn de twee helften van één contract. Voeg een nieuwe parameter aan <span class="cma-page__strong">beide</span> kanten toe, en dek 'm af in <code>cma/tests/FormRouteTest.php</code>.
    </lib-message>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=architecture">Architectuur</a>, <a href="documentation.php?topic=json_forms">JSON-gedreven formulieren</a>, <a href="documentation.php?topic=iis_config">IIS-configuratie</a>.
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

    <h2>Registreren in de catalogus</h2>
    <p>Voeg een entry toe aan <code>buildToolsTreeData()</code> in <code>cma/tools_catalog.inc</code> (de gedeelde bron voor de launcher):</p>
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
    <p>Voor formulier-acties: handle de POST <span class="cma-tool__em">vóór</span> <code>cma_html_header</code> aangeroepen wordt. Bij succes <code>header('Location: ...')</code> + <code>exit</code>. Belangrijk: <code>file_put_contents</code>, <code>error_log</code>, <code>echo</code> in je handler-pad voorkomen het redirect (één ongeguarde <code>error_log</code> is genoeg: de Location-header wordt dan niet verstuurd en mobile Safari biedt de pagina als download aan).</p>

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
    <p>Databases zijn benoemd in <code>data/databases.json</code> — connecties zijn per-site data, het platform levert er geen defaults voor. Standaard-namen:</p>
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
    <p>Schakelen met <code>Database::setOdbcMode('pdo')</code> tijdens bootstrap. De instelling geldt voor het hele proces: er is geen per-connectie variant, en een <code>odbcMode</code>-property in <code>databases.json</code> wordt door niets gelezen.</p>

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

    <h2>VBScript-shims: RecordSet, ScriptingDictionary, Regexp</h2>
    <p>ASP&rarr;PHP geconverteerde code verwacht een paar VBScript-objecten die letterlijk in de code blijven staan. Het platform levert ze zodat de converter-output zonder handwerk draait:</p>
    <ul>
        <li><code>RecordSet</code> &mdash; ADO-cursor (zie boven).</li>
        <li><span class="cma-tool__strong"><code>Regexp</code></span> (<code>App\Library\Regexp</code>, globaal gealiast) &mdash; de VBScript <code>RegExp</code>: properties <code>Pattern</code>, <code>IgnoreCase</code>, <code>Global</code>, <code>MultiLine</code> en methods <code>Test()</code>, <code>Replace()</code>, <code>Execute()</code>. Intern PCRE. VBScript-regex is vrijwel een subset van PCRE; de shim overbrugt alleen de echte verschillen: flags-als-properties &rarr; inline modifiers, <code>Global</code> &rarr; replace-all-vs-first, <code>&#92;uFFFF</code> &rarr; <code>&#92;x{FFFF}</code>, en delimiter-keuze. Byte-semantiek (geen <code>/u</code>), passend bij de ISO-8859-1 codebase. <span class="cma-tool__strong">Converter mag <code>new Regexp()</code> gewoon emitten</span> &mdash; niet meer per geval naar <code>preg_*</code> herschrijven.</li>
    </ul>

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
        <li>Site-eigen migrations leven in een eigen map op de site-root (b.v. <code>migrations/</code>) mét een eigen manifest, en worden geregistreerd via <code>migration_sources_extra</code> — zie <a href="#multisource">Multi-source migrations</a>. Zet ze buiten <code>cma/</code>, want die map wordt door <code>composer update</code> overschreven.</li>
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

    <h2 id="multisource">Multi-source migrations</h2>
    <p><code>MigrationService</code> draait altijd de platform-source (<code>cma/config/migrations.json</code>, tracking-table <code>_cma_version</code>) en daarnaast elke <span class="cma-page__strong">extra source</span> die de site registreert. Elke source heeft een eigen manifest én een eigen tracking-table, zodat de high-water-mark per source apart bijgehouden wordt.</p>
    <p>Registreren doe je in <code>app.php</code> (vóór de CMA draait):</p>
    <pre><code>$GLOBALS['Application']['migration_sources_extra'] = [
    [
        'name'          =&gt; 'karaat',                           // uniek; niet 'platform'
        'file'          =&gt; __DIR__ . '/migrations/site_migrations.json',
        'trackingDb'    =&gt; 'data',                             // databases.json-entry
        'trackingTable' =&gt; '_cma_karaat_version',              // optioneel, default _cma_&lt;name&gt;_version
    ],
];</code></pre>
    <p>Het manifest heeft dezelfde vorm als <code>migrations.json</code> (een <code>migrations[]</code> array met <code>version</code> / <code>description</code> / <code>changes[]</code>). De tracking-table wordt automatisch aangemaakt bij de eerste run.</p>
    <div class="docs-callout"><span class="cma-page__strong">Script-paden in een extra source.</span> Voor <code>runPhp</code> / <code>runSqlScript</code> wordt <code>script</code> zó opgelost: (1) een absoluut pad wordt letterlijk gebruikt; (2) anders wint een pad dat bestaat <span class="cma-page__strong">relatief aan de map van het manifest</span> — dáár zet je het script van een site-eigen migratie, naast zijn manifest, buiten <code>cma/</code>; (3) anders valt het terug op <code>cma/</code>-relatief (waar de platform-scripts staan). Dus <code>"script": "1.0.0_fix.php"</code> in <code>migrations/site_migrations.json</code> laadt <code>migrations/1.0.0_fix.php</code>.</div>
    <p>Volgorde: platform-migrations eerst, dan de extra sources in registratie-volgorde. Versie-volgorde is gegarandeerd binnen een source; een site-migratie die van een platform-tabel afhangt draait sowieso ná de platform-source.</p>

    <div class="docs-callout"><span class="cma-page__strong">Versie-nummering voor site-migraties.</span> Geef site-eigen migraties een versie in het <span class="cma-page__strong">0.x.x-bereik</span> (begin bij <code>0.1.0</code>). Het platform gebruikt <code>1.0.0</code> en hoger (en groeit daar over releases naartoe), dus een site-migratie op bijv. <code>1.0.0</code> botst met de platform-versie <code>1.0.0</code>. Elke source heeft weliswaar een eigen tracking-tabel — apply-all voert beide uit — maar bij een gedeeld versienummer is "opnieuw uitvoeren" op alleen dat nummer dubbelzinnig. De <a href="tools.php?tool=migrations" target="_top">Migraties-tool</a> meldt het nu expliciet ("Let op…") wanneer een site-source een versie ≥ 1.0.0 gebruikt of wanneer twee sources hetzelfde versienummer definiëren. (Het platform heeft zelf één historische <code>0.0.1</code>-bootstrap; daarom start je site-migraties bij <code>0.1.0</code>.)</div>

    <h2>Test &amp; deploy workflow</h2>
    <ol>
        <li>Schrijf de migratie + registreer in <code>migrations.json</code>.</li>
        <li>Run op je dev-omgeving via <a href="tools.php?tool=migrations" target="_top">Tools → Migraties</a>. Auto-backup aan voor dev-safety.</li>
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

    <h2>Definitie-schema</h2>
    <p>Het volledige schema staat in <code>cma/config/schema/form-definition.schema.json</code> (titel <span class="cma-tool__em">CMA Form Definition</span>). Zet die als <code>$schema</code> bovenaan je definitie zodat editors IntelliSense + validatie geven. Een minimale, schema-geldige definitie:</p>

    <p><span class="cma-tool__em">Let op het aantal <code>../</code>-stappen.</span> Een <code>$schema</code>-verwijzing wordt opgelost ten opzichte van de map van het bestand zélf, en de twee definitie-mappen liggen niet even diep. Klopt het pad niet, dan valt de editor stil terug op géén validatie en géén IntelliSense — er verschijnt geen waarschuwing, de definitie ziet er gewoon goed uit.</p>
    <table class="listtable">
        <thead><tr><th>Definitie staat in</th><th>Correcte <code>$schema</code></th></tr></thead>
        <tbody>
            <tr><td><code>cma/assets/forms/definitions/</code> (intern)</td><td><code>../../../config/schema/form-definition.schema.json</code></td></tr>
            <tr><td><code>assets/forms/</code> (app-specifiek)</td><td><code>../../cma/config/schema/form-definition.schema.json</code></td></tr>
        </tbody>
    </table>
    <p>Schrijf je zelf een generator, bereken het pad dan uit de doelmap in plaats van het in te typen: <code>JsonFormLoader::schemaRef($doelmap)</code> doet dat (bovenop <code>App\Library\File::relativePath()</code>). De ingebouwde generatoren — de formulier-wizard en <span class="cma-tool__em">Genereer form definities</span> — gebruiken die al.</p>
    <pre><code>{
    "$schema": "../../../config/schema/form-definition.schema.json",
    "name": "opleidingen",
    "title": "Opleidingen",
    "table": "tblOpleidingen",
    "database": "data",
    "idField": "ID",
    "allowAdd": true,
    "allowDelete": true,
    "listColumns": ["naam", "startDatum", "actief"],
    "fields": [
        {"name": "naam",       "type": "textbox",  "caption": "Naam", "required": true, "maxLength": 100},
        {"name": "startDatum", "type": "date",     "caption": "Startdatum", "format": "date"},
        {"name": "actief",     "type": "checkbox", "caption": "Actief"}
    ]
}
</code></pre>

    <h3>Top-level opties</h3>
    <p>(<span class="cma-tool__strong">*</span> = verplicht)</p>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:190px">Key</th><th>Betekenis</th></tr></thead>
        <tbody>
            <tr><td><code>name</code> <span class="cma-tool__strong">*</span></td><td>Unieke form-identifier (lowercase, underscores toegestaan).</td></tr>
            <tr><td><code>table</code> <span class="cma-tool__strong">*</span></td><td>Database-tabel waar records in staan.</td></tr>
            <tr><td><code>fields</code> <span class="cma-tool__strong">*</span></td><td>Array van velddefinities (zie hieronder).</td></tr>
            <tr><td><code>title</code> / <code>titleEnglish</code></td><td>Weergavetitel (NL / EN).</td></tr>
            <tr><td><code>database</code></td><td>Connectienaam uit <code>databases.json</code> (default <code>data</code>).</td></tr>
            <tr><td><code>idField</code></td><td>Naam van het primary-key veld.</td></tr>
            <tr><td><code>listColumns</code></td><td>Kolommen in de list/tree-weergave.</td></tr>
            <tr><td><code>listQuery</code></td><td>Eigen SQL voor de list/tree (overschrijft de standaard SELECT).</td></tr>
            <tr><td><code>quickSearchFields</code></td><td>Comma-gescheiden veldnamen voor het snelzoek-veld.</td></tr>
            <tr><td><code>activeField</code></td><td>Veld dat actief (1=groen) / inactief (0) markeert in de list.</td></tr>
            <tr><td><code>allowAdd</code> / <code>allowDelete</code> / <code>allowCopy</code></td><td>Toolbar-acties aan/uit.</td></tr>
            <tr><td><code>securityByUser</code></td><td>Records filteren op eigenaar (rij-niveau autorisatie).</td></tr>
            <tr><td><code>storeLastModified</code></td><td>Houd last-modified timestamp bij.</td></tr>
            <tr><td><code>protectedRecords</code></td><td>Record-IDs die niet verwijderd mogen worden.</td></tr>
            <tr><td><code>filter</code></td><td>Configuratie van de filter-dropdown.</td></tr>
            <tr><td><code>extraButtons</code></td><td>Custom toolbar-knoppen (<code>icon</code>, <code>title</code>, <code>url</code>, <code>target</code>, <code>openInNewWindow</code>, <code>condition</code>). Ondersteunt placeholders zoals <code>[slug]</code>. <code>icon</code> kent drie vormen: een <code>lnr …</code> CSS-class, een platform-icoonnaam (genormaliseerd naar <code>/cma/assets/icons/</code>, met automatische <code>.png</code>→<code>.svg</code>-swap — de gebundelde set is SVG-only), of een <span class="cma-tool__strong">absoluut site-pad</span> zoals <code>/assets/icons/ico-moodle.png</code> dat exact zo wordt gebruikt. Site-eigen iconen horen in <code>/assets/icons/</code> op de site-root (Installer blijft er vanaf); in <code>/cma/assets/icons/</code> worden ze door composer update overschreven en door de svg-swap ge-404't.</td></tr>
            <tr><td><code>tips</code></td><td>Helptips in de zijbalk.</td></tr>
            <tr><td><code>postHandler</code> / <code>afterPostUrl</code> / <code>previewUrl</code> / <code>onLoadJs</code></td><td>Hooks: eigen POST-handler PHP-bestand, redirect-na-opslaan, preview-URL-template, on-load JavaScript.</td></tr>
            <tr><td><code>clearCache</code></td><td>Lijst glob-patronen (relatief aan de site-<code>cache/</code>-map) die na elke geslaagde save gewist worden &mdash; de moderne opvolger van <code>cma_afterpost.asp</code>. <code>{ID}</code>/<code>{id}</code> wordt vervangen door het record-id, bijv. <code>["prod_detail_v8_{ID}.html", "stenen_*.html"]</code>. Wist ook de data-cachelaag (<code>App\Library\Cache</code>) zodat afgeleide lijsten/carousels niet verouderen. Best-effort en gesandboxed tot <code>cache/</code>.</td></tr>
            <tr><td><code>title</code> / <code>titleSingular</code></td><td>Weergavetitel (meervoud, voor lijst-koppen en tab-titels) en het enkelvoud (voor "X toevoegen/wijzigen"-titels). Ontbreekt <code>titleSingular</code>, dan wordt het Nederlandse enkelvoud automatisch afgeleid van <code>title</code> — dat werkt alleen op het láátste woord, dus zet bij meerwoordige titels als "Resultaten per deelnemer" een expliciete <code>titleSingular</code> ("Resultaat per deelnemer"). Ook de subform-add-popup gebruikt deze titels (via <code>CMA.formConfig.subforms</code>); voordien viel die terug op de rauwe form-naam ("Toetsen_per_deelnemer toevoegen").</td></tr>
            <tr><td><code>parentForm</code></td><td>Naam van het parent-form (alleen voor subforms).</td></tr>
            <tr><td><code>subforms</code></td><td>Geneste subform-definities (zie hieronder).</td></tr>
        </tbody>
    </table>

    <h3>Veld-opties (<code>fields[]</code>)</h3>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:190px">Key</th><th>Betekenis</th></tr></thead>
        <tbody>
            <tr><td><code>name</code> <span class="cma-tool__strong">*</span></td><td>Kolomnaam in de tabel.</td></tr>
            <tr><td><code>type</code> <span class="cma-tool__strong">*</span></td><td>Control-type — zie de lijst hieronder.</td></tr>
            <tr><td><code>caption</code> / <code>captionEnglish</code></td><td>Label (NL / EN). Ontbreekt de caption, dan valt de weergave terug op de ruwe veldnaam; bekende afkortingen uit geconverteerde databases worden daarbij vertaald (<code>JsonFormService::fieldNameCaption</code>, o.a. <code>descr</code> → "Omschrijving") — breid die map uit als er meer opduiken.</td></tr>
            <tr><td><code>hint</code> / <code>hintEnglish</code></td><td>Tooltip-tekst.</td></tr>
            <tr><td><code>required</code> / <code>readonly</code></td><td>Verplicht / alleen-lezen.</td></tr>
            <tr><td><code>maxLength</code> / <code>height</code></td><td>Max. aantal tekens / hoogte (memo-velden).</td></tr>
            <tr><td><code>useContentBlocks</code></td><td>Gebruik de blockedit content-block-editor i.p.v. een gewone richttext (zie <a href="documentation.php?topic=iis_config">…</a> / blockedit).</td></tr>
            <tr><td><code>source</code> / <code>sql</code> / <code>database</code> / <code>filterByField</code></td><td>Opties-bron voor <code>combobox</code>/<code>dropdown</code>/<code>checklist</code>: een data-source, een SQL-query, en optioneel afhankelijk van een ander veld.</td></tr>
            <tr><td><code>options</code></td><td>Inline keuze-opties (i.p.v. een query).</td></tr>
            <tr><td><code>format</code></td><td>Weergave-formaat: <code>date</code>, <code>datetime</code>, <code>currency</code>, <code>percentage</code>.</td></tr>
            <tr><td><code>validation</code></td><td>Array van extra validatieregels.</td></tr>
            <tr><td><code>dependencies</code> / <code>hiddenWhen</code></td><td>Toon/verberg dit veld afhankelijk van de waarde van een ander veld.</td></tr>
            <tr><td><code>image</code> / <code>file</code></td><td>Upload-configuratie (max afmetingen, pad) voor <code>image</code>/<code>file</code>-types.</td></tr>
            <tr><td><code>renderer</code></td><td>Naam van een custom renderer (voor <code>type: custom</code>).</td></tr>
        </tbody>
    </table>

    <h3>Field-types</h3>
    <p>De toegestane <code>type</code>-waarden (enum in <code>form-definition.schema.json</code>):</p>
    <p>
        <code>textbox</code>, <code>memo</code>, <code>checkbox</code>, <code>combobox</code>, <code>dropdown</code>,
        <code>date</code>, <code>time</code>, <code>datetime</code>, <code>email</code>, <code>password</code>,
        <code>url</code>, <code>file</code>, <code>image</code>, <code>checklist</code>, <code>checklisttree</code>,
        <code>checklistinline</code>, <code>sortlist</code>, <code>radiogroup</code>, <code>userlist</code>,
        <code>directory</code>, <code>xmlstore</code>, <code>label</code>, <code>groupseparator</code>,
        <code>readonly</code>, <code>custom</code>.
    </p>
    <p class="docs-meta"><code>cma/config/control-types.json</code> is iets ánders: dat is de legacy <code>pctControlType</code>-id-mapping (pctTextbox, pctMemo, …) uit de Access-tijd, niet de field-types hierboven.</p>
    <p class="docs-meta">Nu rendert <code>sortlist</code> als de <code>&lt;cma-sortlist&gt;</code> web component (drag-and-drop). De items komen record-specifiek mee in de record-data (sleutel <code>srtlst_{controlId}</code>, gesorteerd op <code>SortOrder</code>); de gekozen volgorde reist terug via het verborgen veld <code>srtlst_{controlId}_info</code> en wordt als <code>SortOrder</code>-update in de brontabel opgeslagen.</p>

    <h2>Subforms</h2>
    <p>Een subform koppelt records aan een parent-record via een foreign key. Subforms staan in de <code>subforms</code>-array van de parent-definitie:</p>
    <pre><code>"subforms": [
    {
        "title": "Modules",
        "form": "modules",
        "parentField": "opleidingID",
        "linkField": "ID",
        "order": 1
    }
]
</code></pre>
    <p>Keys: <code>form</code> (naam van de te embedden definitie) of <code>file</code>, <code>parentField</code> (FK in het kind dat naar de parent wijst), <code>linkField</code> (veld in de parent, default de <code>idField</code>), <code>title</code>/<code>titleEnglish</code> en <code>order</code>. De URL <code>form.php?form=opleidingen/&lt;ID&gt;/modules</code> rendert dan de modules-list onder het opleiding-detail.</p>

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
    <p>Placeholders worden vervangen door waardes uit het huidige record. Het platform substitueert hardgecodeerd: <code>[id]</code>, <code>[guid]</code>, <code>[guid2]</code>, <code>[domein]</code>. <span class="cma-tool__em">Alle</span> overige <code>[fieldname]</code> placeholders worden ook geresolveerd door naar het form-veld met die naam te kijken — zo werkt <code>[slug]</code> automatisch als er een veld <code>slug</code> bestaat.</p>

    <h2>JsonFormRenderer</h2>
    <p>Server-side rendering gebeurt door <code>Cma\Services\JsonFormRenderer</code>. Die produceert de HTML; het form-controller.js framework in de browser handelt validatie, AJAX-save, subform navigation, etc. af. Custom render-overrides plaats je in <code>cma/classes/Services/</code> met eigen subclassen — zelden nodig, meestal volstaat een nieuwe field-type via <code>control-types.json</code>.</p>

    <h2>Form_api.php</h2>
    <p>AJAX-endpoint <code>cma/form_api.php</code> handelt alle save/load/list/delete operaties af. Common parameters:</p>
    <ul>
        <li><code>jsonForm=&lt;name&gt;</code> of <code>form=&lt;name&gt;</code> — het form (verplicht).</li>
        <li><code>action=&lt;actie&gt;</code> — operatie (load, save, list, delete, getOptions, etc.).</li>
    </ul>
    <p>Response is altijd JSON: <code>{"success": true|false, "error": "...", ...action-specific fields}</code>. In dev mode (omgeving ≠ P) bevat de response ook <code>_debugPath</code>, <code>_exception</code>, <code>_badFields</code> velden voor debugging.</p>

    <h2>Definitie-validatie (guardrails)</h2>
    <p>Voordat <code>form.php</code> een formulier rendert (na de toegangscontrole) roept het <code>Cma\JsonFormLoader::validateDefinition($formName)</code> aan. Die geeft een lijst van problemen terug; is die niet leeg, dan wordt het formulier <span class="cma-page__strong">geblokkeerd</span> met de volledige lijst op het scherm (via <code>showFormError</code>, HTTP 500) in plaats van stilletjes half te werken. Doel: een definitie die cruciale informatie mist faalt zichtbaar, niet stil — een checklist zonder koppeltabel slaat anders niets op zonder enige melding.</p>
    <p>Gecontroleerd wordt (bewust krap, zodat een correct geconfigureerd form nooit afgaat):</p>
    <table class="doc-table">
        <thead><tr><th>Check</th><th>Probleem</th></tr></thead>
        <tbody>
            <tr><td>Structuur</td><td>Definitie leeg/onleesbaar, of géén <code>fields</code> én géén <code>table</code>.</td></tr>
            <tr><td>Veldnaam</td><td>Een veld zonder <code>name</code> (behalve presentatie-velden: label, groupseparator, tip).</td></tr>
            <tr><td>Checklist</td><td>Type <code>checklist</code> mist <code>sourceTable</code>, <code>idField</code> of <code>displayField</code> — de relatiekolommen die de save nodig heeft.</td></tr>
            <tr><td>Image / File</td><td>Type <code>image</code>/<code>file</code> zonder pad (<code>imagePath</code>/<code>filePath</code>), of een pad dat onder de webroot niet naar een bestaande map verwijst (absolute URLs en een onbekende webroot worden overgeslagen).</td></tr>
        </tbody>
    </table>
    <p>De pure kern <code>validateDefinitionData(?array $data, string $formName)</code> draait zonder cache/filesystem en is gedekt door <code>cma/tests/JsonFormValidationTest.php</code>. Voeg bij een nieuw field-type met verplichte configuratie hier een check toe, zodat een ontbrekende sleutel zichtbaar faalt in plaats van stil te degraderen.</p>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=architecture">Architectuur</a> (waar Services in <code>Cma\</code> namespace leven), <a href="documentation.php?topic=database">Database &amp; RecordSet</a>.
    </div>
    <?php
}

function render_doc_formval(): void
{
    ?>
    <h1>Formuliervalidatie (front-end)</h1>
    <p class="docs-meta">Hoe <code>library/formval_nl.js</code> een formulier valideert, waar de foutmelding terechtkomt, en welke <code>data-</code>attributen een veld sturen.</p>

    <h2>Aanroep</h2>
    <p>Een formulier hangt de validatie aan zijn eigen submit: <code>onsubmit="return form_valid(this)"</code>. <code>form_valid()</code> initialiseert het formulier als dat nog niet gebeurd is, loopt alle velden langs, en geeft <code>true</code> terug als er niets te melden is. Bij fouten geeft hij <code>false</code> terug en verschijnt de samenvatting.</p>

    <h2>Waar de fout terechtkomt</h2>
    <p>Bovenaan het formulier, in een <code>div.form_errors</code> die <code>form_errors_render()</code> invoegt als eerste kind van het <code>&lt;form&gt;</code>. Eén regel per fout, elke regel een link naar zijn veld; het veld zelf krijgt de klasse <code>invalid</code> (rode rand plus icoon, gestyled in <code>library/library.css</code>) en <code>aria-invalid</code>.</p>
    <p><span class="cma-tool__strong">Er staat geen melding naast het veld.</span> Dat was er wel — een klein vlaggetje, gepositioneerd met <code>lib_getAbsoluteOffsetLeft/Top()</code>. Die berekening klopt niet zodra het veld in een tab zit, in een container die scrollt, of in een element waarvan <code>offsetWidth</code> 0 is; het vlaggetje stond dan bij een ander veld. En het toonde er één, terwijl je wilt weten wat er in totaal nog moet.</p>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:230px">Functie</th><th>Doet</th></tr></thead>
        <tbody>
            <tr><td><code>form_errors_render(form)</code></td><td>Bouwt of vervangt de samenvatting; geeft <code>null</code> terug en ruimt de div op als er niets te melden is</td></tr>
            <tr><td><code>form_errors_clear(form)</code></td><td>Haalt de samenvatting weg (formulier is in orde)</td></tr>
            <tr><td><code>form_field_reveal(fld)</code></td><td>Activeert de tab waarin het veld zit en zet de focus erop — gebruikt door de regels in de samenvatting én door de eerste fout na een submit</td></tr>
            <tr><td><code>form_field_show_error(fld)</code></td><td>Markeert alleen het veld. Overschrijfbaar.</td></tr>
            <tr><td><code>form_valid_report(fouten, knop, form)</code></td><td>Rendert de samenvatting. Overschrijfbaar. Zonder <code>form</code> valt hij terug op een dialoog, zodat een oudere aanroeper geen melding verliest.</td></tr>
        </tbody>
    </table>
    <p>Herstelt iemand een veld, dan verdwijnt die regel bij het verlaten van het veld — maar alleen als de samenvatting al zichtbaar is. Tijdens het invullen van een leeg formulier verschijnt er dus niets; de melding komt pas als je op de knop drukt.</p>

    <h2>Attributen op een veld</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:230px">Attribuut</th><th>Betekenis</th></tr></thead>
        <tbody>
            <tr><td><code>data-required</code></td><td>Verplicht. <code>N</code>/<code>false</code>/<code>0</code>/<code>no</code> zetten het uit.</td></tr>
            <tr><td><code>data-label</code></td><td>Naam in de foutmelding. Wordt afgeleid uit de veldnaam als hij ontbreekt.</td></tr>
            <tr><td><code>data-validation-type</code></td><td>Extra typecontrole op het veld.</td></tr>
            <tr><td><code>data-length</code>, <code>data-length-max</code></td><td>Minimale en maximale lengte.</td></tr>
            <tr><td><code>data-disable-checkmark</code></td><td>Geen <code>valid</code>/<code>invalid</code>-klasse op dit veld.</td></tr>
            <tr><td><code>data-error</code>, <code>data-error-short</code></td><td>Door de validatie zelf gezet; niet met de hand vullen.</td></tr>
            <tr><td><code>data-show-tooltip</code>, <code>data-errorypos</code></td><td><lib-label type="warning">Zonder werking</lib-label> Ze stuurden het vlaggetje. Ze staan nog op veel formulieren en worden genegeerd.</td></tr>
        </tbody>
    </table>
    <p>Op formulier-niveau bestaan verder <code>data-button-name</code> (de knopnaam in de melding) en <code>data-validation</code>, dat via <code>eval()</code> een eigen controle draait nadat de velden zijn nagelopen.</p>

    <h2>Een eigen weergave</h2>
    <p>Wil een app de fouten ergens anders kwijt, overschrijf dan <code>form_valid_report()</code> ná het laden van <code>formval_nl.js</code>. Dat is de bedoelde ingang; de rest van de keten hoeft er niet voor open.</p>

    <div class="docs-callout docs-callout--warn">
        <span class="cma-tool__strong">De site laadt <code>formval_nl.min.js</code>, niet de bron.</span> Een wijziging zonder <code>npm run build:minify</code> (in <code>cma/</code>) verandert dus niets aan wat de browser krijgt — en dat is niet te zien in een diff. <code>cma/tests/FormErrorSummaryTest.php</code> controleert daarom of de bundels niet ouder zijn dan hun bron en of het vlaggetje er ook uit is. Let bij het testen op call-sites die de cachebuster hardcoderen (<code>?version=2</code>); die verversen niet mee.
    </div>
    <div class="docs-callout docs-callout--warn">
        <span class="cma-tool__strong">Er zijn losse copieën van deze logica.</span> Naast <code>library/formval_nl.js</code> dragen de inventarisatie-subapp en de karaat-front-end een eigen ingebakken versie van <code>form_valid_field</code>/<code>form_field_show_error</code> in hun eigen script-bundel. Een wijziging hier verandert die niet.
    </div>

    <div class="seealso">
        Zie ook: <a href="documentation.php?topic=json_forms">JSON-gedreven formulieren</a> (server-side formulierdefinities), <a href="documentation.php?topic=testing">Tests &amp; coverage strategie</a>.
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
            <tr><td><code>cma-*</code></td><td><code>cma/webcomponents/</code></td><td>CMA-specifieke componenten die afhankelijk zijn van CMA-context (tools, forms, layout). Voorbeelden: cma-tree, cma-toolbar, cma-tabs, cma-fold, cma-groupbox, cma-launcher.</td></tr>
        </tbody>
    </table>

    <h2>cma-launcher — de doorzoekbare BIG-menu</h2>
    <p><code>cma-launcher</code> is de doorzoekbare, gegroepeerde BIG-menu die zowel het <span class="cma-tool__strong">tools</span>- als het <span class="cma-tool__strong">rapportages</span>-menu aandrijft. Het is één generiek component met twee modi, gestuurd via attributen (<code>catalog-url</code>, <code>nav-mode</code>, <code>target</code>, <code>search-placeholder</code>, <code>aria-label</code>):</p>
    <ul>
        <li><span class="cma-tool__strong"><code>nav-mode="shell"</code></span> (default) — overlay-modus die <code>#contentArea</code> op 100% vult en navigeert via <code>window.loadPage()</code> + schone URL. <span class="cma-tool__strong">Door niets meer gebruikt</span>: de shell-instantie in <code>main.php</code> (<code>#toolsLauncher</code>, met <code>window.openToolsLauncher()</code>/<code>toggleToolsLauncher()</code>) is verwijderd; ook de tools-pagina gebruikt nu de embedded modus hieronder.</li>
        <li><span class="cma-tool__strong"><code>nav-mode="iframe"</code></span> (gebruikt door <code>reports.php</code> en door <code>tools.php</code>) — een <span class="cma-tool__em">embedded</span> menu dat zijn eigen host-box (<code>#reports</code> resp. <code>#tools</code>, class <code>.launcher-host</code>) op 100% vult i.p.v. de hele <code>#contentArea</code>. Haalt de catalogus uit de opgegeven <code>catalog-url</code> (<code>api/reports-catalog.php</code> resp. <code>api/tools-catalog.php</code>) en laadt bij een keuze het <code>href</code> van het item in het iframe genoemd door <code>target</code> (<code>#reports-content</code> &rarr; <code>reportdetails.php?RepID=X</code>; <code>#tools-content</code> &rarr; <code>tools/&lt;tool&gt;.php</code> of <code>form.php?form=&lt;form&gt;</code>), verbergt zichzelf en schrijft het schone <code>url</code> naar de adresbalk (voor tools afgeleid via <code>resolveShellNav()</code>: <code>/cma/tools?tool=&lt;naam&gt;</code> of <code>/cma/tools?form=&lt;form&gt;</code>). Items met een site-root of absolute <code>href</code> (site-specifieke tools buiten <code>/cma/</code>) openen in een nieuw tabblad. Geen backdrop, geen body-scroll-lock. De host is "leeg of een menu": open menu &rarr; iframe verborgen; keuze/sluiten &rarr; iframe zichtbaar als er iets geladen is.</li>
    </ul>
    <p>De catalogus-JSON heeft in beide gevallen dezelfde vorm: <code>{success, groups:[{type:'folder',label,icon,children:[{type:'item',label,href,url?,icon,badge?}]}]}</code>. Het item van wat op dat moment open staat krijgt <code>.cma-launcher__item.is-active</code> (via <code>_markActive()</code> — shell-mode matcht op <code>?tool=</code>/<code>/cma/form/&lt;form&gt;</code>, iframe-mode op de huidige iframe-<code>src</code>). Light DOM; stijlen staan als <code>.cma-launcher__*</code> in <code>assets/css/main.css</code> (embedded variant: <code>.cma-launcher--embedded</code>).</p>
    <p><code>/cma/tools</code> toont een smalle <code>cma-toolbar</code> met één <span class="cma-tool__strong">Alle tools</span>-knop (<code>#toolsMenuBtn</code>) in het content-gebied. De pagina spiegelt <code>reports.php</code>: een embedded <code>&lt;cma-launcher nav-mode="iframe"&gt;</code> in de host-box <code>#tools</code> (<code>.launcher-host</code>) vult die box met het doorzoekbare menu; de knop togglet hem. Zonder <code>?tool=</code>/<code>?form=</code> opent het menu automatisch. Een keuze verbergt het menu en laadt de tool full-width in het onderliggende <code>#tools-content</code>-iframe. De voormalige twee-paneel boomstructuur is gearchiveerd als <code>cma/tools_DEPRECATED.php</code>.</p>

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
    <p><code>cma/minify.php</code> serveert de geminificeerde bundels in <span class="cma-page__strong">elke</span> omgeving (<code>$MINIFY_ACTIVE</code> staat vast op <code>true</code>). Overal hetzelfde artefact draaien betekent dat een kapotte of verouderde <code>.min</code> al tijdens ontwikkelen opvalt en niet pas in productie. De disk-cache is wél productie-only (<code>$DISK_CACHE_ACTIVE</code>): die is puur snelheid en zou tijdens het ontwikkelen een gecachete bundel teruggeven terwijl je de source nog aan het wijzigen bent.</p>
    <p>Gevolg voor ontwikkelaars: wijzig je een <code>.js</code>/<code>.css</code>, draai dan <code>npm run build:minify</code> (of de cache-clear tool) — anders serveert minify.php de <span class="cma-page__strong">raw source</span>, want een <code>.min</code> die ouder is dan zijn source wordt bewust genegeerd.</p>

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
    <p>Beschikbare icon-namen staan in <code>library/css/linearicons.css</code>. De Storybook-pagina (sectie "Linearicons") rendert de complete lijst zodat je visueel kan kiezen.</p>

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
        <li><span class="cma-tool__strong">LibLog</span> (<code>library/webcomponents/lib-log.js</code>) — onderschept <code>console.*</code> calls, batched naar de server. <span class="cma-tool__strong">Zit niet in de CMA-bundle</span> (<code>cma_js_bundle()</code>) en wordt door geen enkele pagina ingeladen; wie hem wil gebruiken moet het bestand zelf includen. Zonder die include bestaat <code>window.LibLog</code> niet en valt <code>cmaLog</code> terug op <code>console.*</code>.</li>
        <li><span class="cma-tool__strong">CmaErrorHandler</span> (<code>library/assets/js/error-handler.js</code>) — vangt <code>window.onerror</code> en <code>unhandledrejection</code>, toont visueel paneel in dev-mode, post naar <code>form_api.php?action=logJsError</code>.</li>
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

    <h2>Server-side fout-responses voor AJAX (admin/supervisor ziet de oorzaak)</h2>
    <p><code>App\Library\ErrorHandler</code> detecteert of een request JSON wil — een
    API-URL (<code>_api.php</code> of <code>/api/</code>), een
    <code>X-Requested-With: XMLHttpRequest</code>, een <code>Accept: application/json</code>,
    of een endpoint dat zélf al een JSON <code>Content-Type</code> zette vóór het faalde.
    Voor die requests stuurt het een gestructureerde JSON-fout in plaats van een
    HTML-pagina, zodat <code>fetch()</code>-clients hem kunnen parsen:</p>
    <pre><code>{ "success": false, "error": "&lt;melding&gt;", "errorType": "&lt;class&gt;",
  "debug": { "file": "…", "line": 42, "trace": "…", "diagnostics": { … } } }</code></pre>
    <p>Het <code>debug</code>-blok gaat <span class="cma-tool__strong">alleen</span> mee in
    een debug-omgeving (L/O/T) <span class="cma-tool__em">of</span> als de ingelogde
    gebruiker admin/supervisor of developer is (<code>SecurityHelper::isAdmin() ||
    isDeveloper()</code>). Een gewone gebruiker op productie krijgt enkel
    <code>"Er is een interne serverfout opgetreden."</code> — er lekt nooit een pad,
    stacktrace of klassennaam. Het hangt bewust <span class="cma-tool__em">niet</span> aan <code>CMA_DEBUG</code>
    (omgeving-gestuurd, UIT op productie): anders ziet een beheerder op productie een
    nietszeggende "HTTP 500".</p>
    <p>De JS-kant toont datzelfde <code>debug</code>-blok wanneer
    <code>window.CMA.formConfig.showDetails</code> waar is (óók admin/developer):
    de <code>cmaApiError</code>-module (<code>formatError()</code>) en de lijst-foutweergave,
    beide in <code>form-controller.js</code>. Zo zijn beide kanten het eens over wie wat ziet;
    volledige file/line/trace staan sowieso in de console en in het uitklap-paneel van
    <code>lib-message</code>.</p>

    <div class="docs-callout docs-callout--warn">
        <span class="cma-tool__strong">"Overal HTTP 500 bij elke lijst/formulier-load" — en géén detail:</span>
        deze JSON-foutafhandeling werkt alleen als de fout vált nadat <code>ErrorHandler</code>
        geregistreerd is. <code>form_api.php</code> las bovenaan — vóór zijn eigen
        <code>require_once bootstrap.inc</code> — de request-tijd via
        <code>\App\Library\Request::server()</code>. Op een site waar IIS <code>auto_prepend</code>
        van <code>_bootstrap.php</code> níet actief is, is de Composer-autoloader op dat punt nog
        niet geladen → <span class="cma-tool__strong">fatale</span> "Class App\Library\Request not found"
        vóórdat <code>ErrorHandler</code> bestaat → kale 500 zonder JSON, op élke form_api-call (dus
        elke lijst/formulier-load). De shell (<code>main.php</code>) overleeft omdat die eerst zijn
        bootstrap require't. Fix: <code>form_api.php</code> leest de request-tijd nu rechtstreeks uit
        <code>$_SERVER</code>. Onderliggende oorzaak blijft de kapotte <code>auto_prepend</code> —
        zelfde oorzaak als het <a href="documentation.php?topic=releasing">vdev-symptoom</a>; herstel
        die in <code>web.config</code> / <code>php.ini</code> voor volledige correctheid.
    </div>

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
    <p>Wordt door <code>PERF_LOG_ENABLED=true</code> aangezet. Output naar <code>.logs/perf/perf_YYYY-MM-DD.log</code> als JSON-per-regel (locatie gezet in <code>PerformanceLogger.php</code> line 75).</p>

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
    <p class="docs-meta">Productie-PHP-classes ter referentie: <code>src/helpers/</code> = <?= $platformClasses ?>, <code>cma/classes/</code> = <?= $cmaClasses ?>, <code>cma/classes/Services/</code> = <?= $cmaServices ?>. De unit-tests dekken <span class="cma-tool__strong">geen</span> van de service-classes (RecordService, FormDataProvider, ListService, MigrationService) of de data-laag (<code>Database</code>, <code>RecordSet</code>). Daar zit de risico-zone.</p>

    <h2>Risico-zones (waar regressies wegglippen)</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th>Zone</th><th>Wat misgaat zonder dekking</th><th>Huidige observatie</th></tr></thead>
        <tbody>
            <tr><td><code>Database</code> + <code>RecordSet</code></td><td>PDOException paths retourneren <code>null</code>/<code>[]</code>; veranderde SQL-quoting; ODBC ↔ SQLite ↔ MySQL edge cases.</td><td>Errors worden ge-logged, maar geen test bewijst dat de catches gaat-niet-stuk-paden hetzelfde gedragen na refactor.</td></tr>
            <tr><td><code>FormDataProvider::saveJsonFormRecord</code></td><td>Add/Edit/Delete branches; custom-renderer save; veld-validatie; monitoring/changelog.</td><td>Ook server-side changelog fallback — geen test die de oud↔nieuw diff bewijst.</td></tr>
            <tr><td><code>Services\RecordService</code> + <code>ListService</code></td><td>Subform piggyback, group-rights save, custom renderer waarde-collectie.</td><td>Cypress dekt de happy-path UI; de service-laag heeft geen geïsoleerde test.</td></tr>
            <tr><td><code>MigrationService</code></td><td>Migratie-volgorde, rerunbaarheid, rollback-gedrag bij partial failure.</td><td>Geen test. Wel changelog in <code>migrations.json</code> maar dat is geen contract.</td></tr>
            <tr><td>Web components met state (cma-blockeditor, cma-tree, lib-fileuploader)</td><td>Attribute-change handlers, JSON.parse fallbacks, drag-and-drop volgorde, fetch-failure UX.</td><td>Storybook-aanwezigheid garandeert syntax, geen gedrag. Eén Cypress-spec voor lib-sheet, rest niet.</td></tr>
            <tr><td><code>Installer</code></td><td>File-sync van platform naar consumer-site; <code>REMOVED_PATHS</code> opschoning; protected-paths bewaring.</td><td>Throwt op copy/mkdir failure, maar geen test bewijst dat de juiste files bewaard blijven.</td></tr>
        </tbody>
    </table>

    <h2>Drie-laags aanpak</h2>
    <p>Niet alle code-laag verdient dezelfde test-stijl. De juiste keuze per laag:</p>
    <ol>
        <li><span class="cma-tool__strong">Pure-logic units</span> — al goed gedekt (12 testklassen). Vuistregel: <span class="cma-tool__em">elke nieuwe pure functie in <code>src/helpers/</code> krijgt een <code>*Test.php</code></span>. Geen DB, geen filesystem, runt in &lt; 1s totaal.</li>
        <li><span class="cma-tool__strong">Pure-data service-tests</span> — methodes die hun input als array binnen krijgen en hun output als array/string terug geven (zoals <code>FormDataProvider::buildEditChangelog</code>, <code>FormDataProvider::buildDeleteChangelog</code>, <code>QueryBuilder</code>, <code>SqlParser</code>) test je <span class="cma-tool__em">zonder</span> connectie. Geen mock-DB nodig — feed de arrays in, vergelijk de output. De changelog-opbouw is hier het duidelijkste voorbeeld van.</li>
        <li><span class="cma-tool__strong">Connection-gebonden service-tests</span> — voor methodes die wél een PDO/RecordSet aanraken (<code>RecordService::save</code>, <code>MigrationService::run</code>) is een echte ODBC-Access verbinding nodig; SQLite zou een ander dialect testen dan productie en is voor form-data niet representatief (alleen <code>cmausers.sqlite</code> draait SQLite). Twee opties: (a) een <span class="cma-tool__em">fixtures-mdb</span> aanpak — een kale <code>.mdb</code> met minimale schema-tabellen die de testrunner kopieert per test, of (b) een <span class="cma-tool__em">PDO-stub</span> waarbij de connectie een in-memory key-value mock is die alleen de queries en parameters opvangt. (b) is sneller op te zetten maar dekt geen ODBC-specifiek gedrag.</li>
        <li><span class="cma-tool__strong">Cypress E2E</span> — al sterk in UI-flows (109 specs). Toevoegen alleen voor regressie-incident-pairs die niet op service-laag te isoleren zijn (multi-form-flows, popup-close-then-reopen edge cases) of waar het écht eind-tot-eind moet draaien tegen een echte CMA-DB.</li>
    </ol>

    <h2>JavaScript-error tripwire in Cypress</h2>
    <p>Elke uncaught exception en unhandled promise rejection uit de app wordt tijdens élke spec verzameld en ná de test ge-assert (in <code>cypress/support/e2e.js</code>): een test die functioneel slaagt maar waaronder de pagina een JS-error gooide, <span class="cma-tool__strong">faalt alsnog</span> met de volledige lijst errors. Zonder die opzet zouden app-errors met <code>return false</code> ingeslikt — een pagina vol console-errors kon groen door de hele suite. De handler laat de test wél uitlopen (geen mid-test abort) zodat de afterEach álle errors in één keer rapporteert; let op: een falende afterEach slaat de rest van dat describe-blok over (standaard Mocha-gedrag). Bekende ruis (zoals <code>ResizeObserver loop</code>) staat in de <code>IGNORED_JS_ERRORS</code>-allowlist bovenin het support-bestand — voeg dáár patronen toe, nooit door de handler weer blanket-<code>false</code> te maken.</p>

    <h2>Quick-win plan (sprint-1 status)</h2>
    <p>Eén PR die het meeste regressie-risico per uur afdekt — start bij wat <span class="cma-tool__em">geen</span> DB-harness nodig heeft:</p>
    <ol>
        <li><span class="cma-tool__strong">Pure-data tests die nu direct kunnen</span> (geen connection vereist):
            <ul>
                <li><code>FormDataProviderChangelogTest</code> — 17 tests dekken <code>buildEditChangelog</code> incl. no-change paths, field-filtering, value-rendering, regression-guards. Pure-data, reflectie voor private method.</li>
                <li><code>FormDataProviderDeleteChangelogTest</code> — 14 tests voor <code>buildDeleteChangelog</code>; ook dekt boolean/array/null-rendering en de twee-koloms structuur.</li>
                <li><code>InstallerRemovedPathsTest</code> — 7 tests met tmp-dir filesystem fixtures, REMOVED_PATHS via reflectie zodat nieuwe retirements automatisch meedoen.</li>
            </ul>
        </li>
        <li><span class="cma-tool__strong">PDO-stub harness</span> in <code>cma/tests/StubConnection.php</code> + <code>StubConnectionTest.php</code> (12 tests). Extends <code>\PDO</code> met <code>newInstanceWithoutConstructor</code>-trick zodat <code>instanceof PDO</code> blijft passen ook zonder pdo_sqlite-driver in CI. Queue van vooraf-gedefinieerde results, recordt alle SQL + params zodat tests kunnen asserten "exact deze UPDATE met deze parameters". Niet representatief voor ODBC-dialect-issues, wel voor query-shape regressies. Klaar voor gebruik in RecordService / saveJsonFormRecord tests.</li>
        <li><span class="cma-tool__strong">Voor ODBC-specifiek</span> (dialect-quirks, identifier-quoting, fetch-encoding): een gedeelde <code>tests/fixtures/blank.mdb</code> die per test wordt gekopieerd naar tmp en daar weer wordt opgeruimd. Dat is een tweede sprint; eerste prioriteit zijn de pure-data tests.</li>
        <li><span class="cma-tool__strong">CI-gate in deploy-webhook</span>: <code>DEPLOY_RUN_TESTS</code> env-var draait een commando NA <code>composer update</code> en VÓÓR recycle. Non-zero exit → deploy FAILED, recycle + post-hook overgeslagen, productie blijft op oude code via cached opcache. Operator ziet <code>status: FAILED</code> in <code>deploy_status.php</code>. Default leeg (= geen gate); opt-in per site door <code>DEPLOY_RUN_TESTS=php cma/tests/TestRunner.php</code> in <code>.env</code>.</li>
    </ol>
    <p class="docs-meta">Draai <code>php cma/tests/TestRunner.php</code> voor de actuele stand — een hier ingetypt aantal is per definitie verouderd. De PDO-stub harness (inclusief throw-mode) maakt de database-foutpaden testbaar zonder database.</p>

    <h2>Coverage-doel</h2>
    <p>Geen absoluut percentage nastreven — een 80%-target dat in 80% van de niet-belangrijke loops zit is misleidend. Wel <span class="cma-tool__em">gedrags-doelen</span>:</p>
    <ul>
        <li>Iedere methode in <code>src/helpers/</code> heeft minstens 1 test op happy-path + 1 op edge case (null, leeg, max-grootte).</li>
        <li>Iedere methode in <code>cma/classes/Services/</code> heeft minstens 1 integration-test per public contract.</li>
        <li>Iedere web component in <code>library/webcomponents/</code> heeft een Cypress-spec die <span class="cma-tool__em">connectedCallback → attribute change → user event → expected state</span> doorloopt (lib-sheet is de blueprint).</li>
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

    <h3>Twee soorten testbestanden</h3>
    <p>De runner kent twee vormen en herkent ze zelf:</p>
    <ul>
        <li><span class="cma-tool__strong">Class-stijl</span> — <code>class &lt;Naam&gt;Test extends TestCase</code> met <code>test*</code>-methodes. Deze worden allemaal in één proces ingeladen; dat is de normale vorm voor nieuwe tests.</li>
        <li><span class="cma-tool__strong">Script-stijl</span> — geen <code>Test</code>-klasse, maar asserties op top-level en een <code>exit()</code> aan het eind (<code>PageLevelsTest</code>, <code>QueryBuilderTest</code>, <code>SqlParserTest</code>). Die kúnnen het proces niet delen: ze declareren stub-klassen/-functies die botsen met de echte (<code>PageLevelsTest</code> stubt <code>Cma\SecurityHelper</code>) en hun <code>exit()</code> zou de hele suite afbreken. De runner draait ze daarom elk in een eigen PHP-proces en telt de exit-code als één test.</li>
    </ul>
    <p>Schrijf een nieuwe test bij voorkeur in class-stijl. Kies je toch script-stijl, houd er dan rekening mee dat je bestand een eigen proces krijgt: stub-klassen botsen dus niet, maar je deelt ook geen state met de rest van de suite.</p>

    <div class="docs-callout docs-callout--warn">
        <span class="cma-tool__strong">Vereist: de <code>mbstring</code>-extensie.</span> <code>App\Library\Str</code> draait volledig op <code>mb_*</code>-functies. Zonder de extensie faalt ~150 tests met <code>Call to undefined function App\Library\mb_strlen()</code> — dat is een omgevingsprobleem, geen regressie. Check met <code>php -m | grep mbstring</code>; zie <a href="documentation.php?topic=installation">Installatie</a> voor de volledige extensielijst.
    </div>

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
        <li>Consumer-sites pullen de nieuwe versie via <code>composer update stenversonline/platform</code>. Hun deploy-webhook doet dit automatisch.</li>
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

    <h2>Waar de versie vandaan komt</h2>
    <p>Er is <span class="cma-tool__strong">één</span> bron: het <code>version</code>-veld in de eigen <code>composer.json</code> van het pakket. <code>Bootstrap::getPlatformVersion()</code> leest dat veld — gememoïseerd per request en cross-request gecached in APCu (of de sessie als APCu ontbreekt), dus het bestand wordt hooguit één keer per app-pool-leven geparsed, niet bij elke call. <code>cma/bootstrap.inc</code> zet daaruit de <code>CMA_APP_VERSION</code>-constante, zichtbaar in het profielmenu.</p>
    <div class="docs-callout">
        <span class="cma-tool__strong">Bij elke release bump je nog maar één plek:</span> het <code>version</code>-veld in <code>composer.json</code> (plus de git-tag). <code>Bootstrap::VERSION</code> is alleen nog een fallback voor als de composer.json onleesbaar is — die hoef je niet meer telkens mee te bumpen. Een deploy recyclet de app-pool en flusht APCu, dus de nieuwe versie verschijnt automatisch bij het volgende request.
    </div>


    <h2>REMOVED_PATHS voor retired bestanden</h2>
    <p>Wanneer je een bestand verwijdert uit <code>library/</code>, <code>cma/</code> of <code>module/</code>: voeg het ook toe aan <code>REMOVED_PATHS</code> in <code>src/Installer.php</code>. De Installer's syncDirectory kopieert alleen forward — zonder REMOVED_PATHS blijft het oude bestand voor altijd op consumer-sites bestaan na een upgrade.</p>
    <pre><code>private const REMOVED_PATHS = [
    'cma/tools/llm_models.php',
    'cma/docs/iis-setup.md',        // inlined in docs
    'cma/docs/logging.md',  
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
            <tr><td><code>vdev-main</code> in profielmenu i.p.v. echte versie</td><td>vendor/ stale t.o.v. cma/ files; Bootstrap::getPlatformVersion() las ooit eerst installed.json, die zegt <code>dev-main</code> bij branch-installs.</td><td><code>composer update stenversonline/platform</code>. leest eerst de package's eigen composer.json — daarna verschijnt vanzelf <code>v&lt;X.Y.Z&gt;</code>.</td></tr>
            <tr><td>Fatal: <code>Class "App\Library\Email" not found</code></td><td>Zelfde oorzaak — vendor refresh ontbrak, Email.php nog niet autoloadable maar bootstrap.inc raakt 'm aan.</td><td>Heeft <code>class_exists</code> guard zodat CMA niet meer crash't; voor permanent: composer update.</td></tr>
            <tr><td>Composer update draait, maar cma/ files niet ververst</td><td><code>composer.json</code> mist de <code>post-install-cmd</code> / <code>post-update-cmd</code> scripts die <code>App\Library\Installer</code> aanroepen.</td><td>Scripts sectie toevoegen aan consumer's composer.json — zie <a href="documentation.php?topic=installation">Installatie</a>.</td></tr>
            <tr><td>Retired bestand blijft op consumer-site na upgrade</td><td>Installer's syncDirectory kopieert alleen forward; verwijderde bestanden propageren niet.</td><td>Voeg het pad toe aan <code>REMOVED_PATHS</code> in <code>src/Installer.php</code>. Bij volgende composer update opgeruimd. Zie <a href="documentation.php?topic=releasing">Releasen &amp; versies</a>.</td></tr>
            <tr><td><code>Method App\Library\Installer::preOperation is not callable, can not call pre-update-cmd script</code> — en de update start niet</td><td>Een <code>pre-*-cmd</code> draait vóór de update, dus tegen de GEÏNSTALLEERDE vendor. Die versie kent de methode nog niet, dus juist de update die haar zou meebrengen wordt geweigerd. Geldt voor elke handler die nieuwer is dan de vendor op de site.</td><td>Haal die ene regel tijdelijk uit <code>composer.json</code>, draai <code>composer update</code>, zet hem terug. De check-tabel op <a href="documentation.php?topic=installation">Installatie</a> controleert vooraf of elke handler in de geïnstalleerde versie bestaat.</td></tr>
            <tr><td>Fatal na upgrade: <code>require_once(...library/lib_htmleditor.inc): Failed to open stream: No such file or directory</code> (of een ander retired bestand)</td><td>Half-voltooide sync: <code>REMOVED_PATHS</code> verwijdert het retired bestand vóórdat <code>syncDirectory</code> draait, maar één onkopieerbaar bestand (locked/permissions/junk) brak de sync af vóórdat het top-level <code>library.inc</code> — dat de dode <code>require_once</code> nog bevatte — overschreven werd. Resultaat: bestand weg + oude requirer blijft → elke request fataal.</td><td>Breekt één onkopieerbaar bestand de sync niet meer af (<code>syncDirectory</code> verzamelt fouten en gaat door, faalt lúid aan het eind), dus <code>library.inc</code> wordt altijd ververst. Herstel: re-run <code>composer update stenversonline/platform</code> (let op "Nothing to modify" — zie de deployment-tabel), of kopieer eenmalig <code>vendor/stenversonline/platform/library/library.inc</code> over <code>library/library.inc</code>.</td></tr>
        </tbody>
    </table>

    <h2>IIS &amp; URL Rewrite</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak</th><th>Fix</th></tr></thead>
        <tbody>
            <tr><td><code>/cma/dashboard</code> → 404, maar <code>/cma/dashboard.php</code> wel 200</td><td>Parent web.config vangt <code>/cma/*</code> op vóór het kind <code>cma/web.config</code>.</td><td>Voeg "Skip /cma to child config" regel bovenaan parent <code>&lt;rules&gt;</code> toe — standaard in <code>templates/web.config.template</code>. Zie <a href="documentation.php?topic=iis_config">IIS-configuratie</a>.</td></tr>
            <tr><td>500 op alle <code>/cma/*</code> requests, parent IIS-error over locked config-sectie</td><td>Allowed server variables nog niet ontgrendeld op server-niveau.</td><td><code>appcmd unlock</code> — zie <a href="documentation.php?topic=installation">Installatie</a>.</td></tr>
            <tr><td>Friendly URL <code>/cma/tools/&lt;naam&gt;</code> → 404 maar <code>?tool=&lt;naam&gt;</code> werkt</td><td>"CMA Tools Friendly URL" regel ontbreekt in site-root web.config, of URL Rewrite Module niet geïnstalleerd.</td><td>Module installeren via iis.net; regel kopiëren uit een werkende consumer-site.</td></tr>
            <tr><td>iOS Safari prompt "Download logreader.php?" bij Log leegmaken</td><td><code>file_put_contents()</code> in delete-handler emitterde PHP-warning, polluatie van response-buffer brak de Location-redirect. Browser kreeg 200 OK met warning-tekst, geen Content-Type → download.</td><td><code>@</code>-suppress op de truncate-call.</td></tr>
        </tbody>
    </table>

    <h2>Database-connecties (Access/ODBC)</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak</th><th>Fix</th></tr></thead>
        <tbody>
            <tr><td><code>Database connection 'data' failed</code> met <code>SQLSTATE[HY000] SQLDriverConnect: 63 … Unable to open registry key Temporary (volatile) Ace DSN for process</code> — hele site plat</td><td>De Access-driver legt per proces een tijdelijke DSN aan in het register onder het gebruikersprofiel van de app-pool-identiteit. Heeft die identiteit geen laadbaar profiel (Load User Profile staat op False) of geen schrijfrechten op de ODBC-sleutel, dan mislukt elke verbinding naar de <code>.mdb</code>. Dit is géén config- of cacheprobleem: <code>Bootstrap::loadDatabasesConfig()</code> leest <code>data/databases.json</code> bij elke request opnieuw van schijf, er wordt niets van gecachet.</td><td>IIS Manager → Application Pools → de pool van de site → Advanced Settings → Process Model → <code>Load User Profile</code> op <code>True</code>, daarna de pool recyclen. Blijft het staan: geef <code>IIS APPPOOL\&lt;sitename&gt;</code> modify-rechten op de temp-map van dat profiel (<code>C:\Windows\Temp</code>, en voor 32-bit pools <code>C:\Windows\SysWOW64\config\systemprofile\AppData\Local\Temp</code>) en full control op <code>HKLM\SOFTWARE\ODBC\ODBC.INI</code> (32-bit: ook onder <code>WOW6432Node</code>).</td></tr>
            <tr><td>DSN in de foutmelding wijst naar <code>&lt;siteroot&gt;\db\main.mdb</code> terwijl <code>data/databases.json</code> een heel ander pad noemt</td><td>Levert <code>databases.json</code> geen <code>data</code>-connectie, dan slaat de Access-conventie <code>&lt;siteroot&gt;\db\main.mdb</code> aan. Precies dat pad in de melding betekent dus: het bestand is overgeslagen — het ontbreekt, is onleesbaar voor de app-pool-identiteit, of is geen geldige JSON (UTF-8 BOM, komma te veel).</td><td>De regel <code>Config:</code> in de foutmelding (en in de connectietabel van de error-pagina) noemt het bestand dat écht geladen is; staat daar <code>no usable databases.json</code>, dan is dit het geval. Het errorlog noemt de reden. Valideer de JSON, controleer NTFS-leesrechten voor <code>IIS APPPOOL\&lt;sitename&gt;</code> op <code>data\</code>, en sla op zonder BOM.</td></tr>
            <tr><td>Migratie stopt met <code>HTTP 500</code> en verder lege melding</td><td>Het PHP-proces zelf viel om (ODBC-driver-crash of time-out), dus er kwam geen JSON-body terug. De migratietool toont de response-body wanneer die er wel is — bij een fatal error staat de PHP-melding erin.</td><td>Los eerst de connectiefout hierboven op. Blijft de body leeg, kijk dan in het PHP-/IIS-errorlog: een lege 500 komt niet uit de migratiecode maar uit de worker.</td></tr>
        </tbody>
    </table>

    <h2>Omgeving / .env</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak</th><th>Fix</th></tr></thead>
        <tbody>
            <tr><td>Env-switch knop drukt, refresh, niks gewijzigd</td><td>De knop schreef ooit altijd naar een hardcoded <code>.env</code>, ook als bootstrap <code>.env.test</code> of <code>.env.production</code> had geladen. Regel landde in het verkeerde bestand.</td><td>Schrijft naar het ACTIEVE env-bestand (zichtbaar als "Actief .env bestand" op de Omgeving-tab).</td></tr>
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
            <tr><td><code>Installer::postUpdate</code> stopt met <code>copy(... desktop.ini): Permission denied</code></td><td>Een Windows-junk-bestand (<code>desktop.ini</code> / <code>Thumbs.db</code>) met system/hidden/readonly-attributen botst tijdens de file-sync; <code>copyFile()</code> gooit en breekt de hele sync af.</td><td><code>syncDirectory</code> slaat <code>desktop.ini</code>/<code>Thumbs.db</code>/<code>.DS_Store</code> over en het meegestuurde junk-bestand is verwijderd. Update het platform. Het achtergebleven <code>desktop.ini</code> op de site mag handmatig weg (<code>attrib -s -h</code> dan <code>del</code>) maar is onschadelijk. Bovendien breekt géén enkel onkopieerbaar bestand de sync meer af — fouten worden verzameld en aan het eind luid gerapporteerd, zodat kritieke bestanden als <code>library.inc</code> nooit half achterblijven.</td></tr>
            <tr><td>Net-gereleasete platform-versie wordt niet opgepikt ("Nothing to modify"), <code>composer.lock</code> blijft op de oude versie</td><td>Composer's VCS-clone-cache houdt een oude ref vast; de nieuwe tag wordt niet gezien.</td><td>Eenmalig (geen server-files wijzigen): voeg <code>?forcerefresh=Y</code> toe aan de deploy-URL / GitHub-webhook Payload URL. Permanent: <code>DEPLOY_COMPOSER_CLEAR_CACHE=1</code> in <code>.env</code>. Handmatig: <code>composer clear-cache &amp;&amp; composer update stenversonline/platform</code>. Niet <code>--no-cache</code> gebruiken (lost de stale clone niet op).</td></tr>
            <tr><td>Migration-banner verschijnt niet ondanks pending migrations</td><td>Werd de bootstrap-side migration-check fout silent ingeslikt.</td><td>Surface't de exception als rode banner + <code>error_log</code>.</td></tr>
        </tbody>
    </table>

    <h2>LLM &amp; LLM-status</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak</th><th>Fix</th></tr></thead>
        <tbody>
            <tr><td>"Probe-fout: Connection timed out…" op een engine die je niet gebruikt</td><td>Toonde tools_llm.php de raw probe-error op elke kaart, ook engines die niet geïnstalleerd zijn.</td><td>Onderdrukt de regel voor niet-in-use engines en gebruikt "niet geïnstalleerd" badge in neutrale styling.</td></tr>
            <tr><td>"Modellen installeren" knop deed niks</td><td>llm_models.php's install-knop liep tegen permissions/PowerShell-paden aan.</td><td> retirde de page; aanbevolen modellen leven nu inline op de Ollama-kaart met <code>ollama pull &lt;tag&gt;</code> copy-paste.</td></tr>
            <tr><td>Recipe-parser specifieke teksten op een niet-recipe site</td><td>llm_analyse.php had hardgecodeerde "receptenparser" wording uit mijntoprecepten-context.</td><td> generieked naar "LLM-pipeline" / "Anthropic-fallback".</td></tr>
        </tbody>
    </table>

    <h2>Logging / log-reader</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak</th><th>Fix</th></tr></thead>
        <tbody>
            <tr><td>Logreader → Performance: "Geen log entries gevonden", maar er IS verkeer.</td><td>logreader leest <code>.logs/perf/perf_*.log</code>; <code>PerformanceLogger</code> schrijft naar <code>.logs/perf/perf_*.log</code>. De UI kijkt in de verkeerde map.</td><td>Bekijk de files direct op disk in <code>.logs/perf/</code> totdat een release de paden synchroniseert. Open issue.</td></tr>
            <tr><td>Logreader → Debug: "Geen log entries gevonden", LibLog console-logging staat aan.</td><td>Zelfde patroon: logreader leest <code>.logs/debug/debug_*.log</code>; <code>cma/api/log.php</code> schrijft naar <code>.logs/debug/debug_*.log</code>.</td><td>Bekijk de files in <code>.logs/</code>. Same fix.</td></tr>
            <tr><td><code>Uncaught TypeError: LibLog.log is not a function</code> (typisch op de storybook, ook via <code>[iframe]</code> in het error-paneel)</td><td>Named element access: een element met <code>id="LibLog"</code> zet <code>window.LibLog</code> op dat DOM-element. De oude check <code>typeof LibLog !== 'undefined'</code> in de <code>cmaLog</code>-fallback zag dat als "logger aanwezig" en riep <code>.log()</code> aan op een <code>&lt;section&gt;</code>. LibLog zit niet in de bundle, dus het echte object was er nooit.</td><td>: <code>cma-utils.js</code> en <code>library.js</code> testen nu op de méthode (<code>typeof window.LibLog.log === 'function'</code>) en de storybook-sectie heet <code>id="liblog"</code>. Regel: gebruik nooit een global-naam als element-id.</td></tr>
            <tr><td>Logreader → Application: log-bron ontbreekt in de dropdown.</td><td>De logreader-config heeft geen 'app' source-entry — Logger's <code>.logs/app/app_*.log</code> wordt nergens via de UI ontsloten.</td><td>Open de bestanden direct, of voeg een source-entry toe aan <code>logreader.php</code>.</td></tr>
        </tbody>
    </table>

    <h2>Web components</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak</th><th>Fix</th></tr></thead>
        <tbody>
            <tr><td>lib-sheet animeert niet bij open (paneel "klapt" direct op zijn plek, geen slide)</td><td><code>@keyframes</code> die binnen een shadow root zijn gedefinieerd worden niet betrouwbaar geëvalueerd (met name iOS/WebKit). Het paneel springt dan meteen naar zijn rest-state zonder zichtbare beweging. Een oudere consumer-site die nog niet ge-<code>composer update</code>'d is, draait nog op die kapotte @keyframes-versie.</td><td>Slidet via de Web Animations API (<code>panel.animate([...])</code> met inline keyframes in <code>lib-sheet.js</code>) i.p.v. CSS @keyframes — die loopt wél in een shadow root. Zie je het symptoom nog? Controleer of de site daadwerkelijk heeft (<code>composer update stenversonline/platform</code>) en hard-refresh zodat de gecachte <code>lib-sheet.min.js</code> wordt vervangen.</td></tr>
            <tr><td>lib-sheet grab-bar niet draggable op mobiel</td><td>Hit-area van ~12px is te klein voor touch.</td><td>Bindt drag óók op het hele <code>.header</code>-element (~50px); heeft de bar zelf ook gepromoot naar ~28px hit-area.</td></tr>
            <tr><td>Knop-klik geeft geen visuele feedback</td><td>CSS <code>:active</code> styles bestaan wel, maar een korte klik laat ze nooit lang genoeg zien.</td><td>Heeft <code>.btn--clicked</code> animatie die door een document-level click handler 220ms wordt aangezet.</td></tr>
        </tbody>
    </table>

    <h2>Lijst-paginering (infinite scroll)</h2>
    <p class="docs-meta">De JSON-form lijst laadt bij >500 rijen de eerste pagina server-side en haalt de rest op de achtergrond op in batches van 500 (keyset-paginering op het unieke id-veld). Access kent geen <code>LIMIT/OFFSET</code>, alleen <code>TOP</code> — daarom keyset (<code>WHERE [id] &gt; lastId ORDER BY [id]</code>) i.p.v. een offset-lus; dát is de reden dat het "gewoon een <code>while(recordsLeft)</code>-lus" al is (<code>_autoPrefetchRows()</code>) maar niet op offset kan draaien. De teller in de toolbar (<code>records 1-N van M</code>, met suffix <code>(laden…)</code> zolang <code>hasMore</code>) is puur een voortgangsindicator: is alles binnen, dan verdwijnt hij. Kern: <code>CmaInfiniteScroll</code> in <code>cma/assets/js/table-preferences.js</code>, aangedreven door <code>_autoPrefetchRows()</code> in <code>cma/assets/js/form-controller.js</code>.</p>
    <div class="docs-callout docs-callout--warn"><span class="cma-tool__strong">Let op bij het diagnosticeren:</span> een blijvende <code>(laden…)</code> is <span class="cma-tool__em">niet</span> per definitie client-side — kijk ook naar de query. Keyset op het unieke id <span class="cma-tool__em">slaat</span> nooit een record over (elk distinct id komt precies één keer langs), maar een <span class="cma-tool__strong">één-op-veel JOIN</span> in de <code>listQuery</code> laat het resultaat méér fysieke rijen per record bevatten. <code>totalCount</code> was een kale <code>SELECT COUNT(*)</code> over die join (telt de opgeblazen rijen), terwijl de tabel — en de client-dedup — één rij per id toont. Zo blijft de teller hangen op bv. <code>records 1-1800 van 1806</code>: 1800 getoonde records halen de opgeblazen 1806 nooit. Geen dataverlies — puur een verkeerde noemer.</div>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak</th><th>Fix</th></tr></thead>
        <tbody>
            <tr><td>Teller blijft hangen op <code>records 1-N van M (laden…)</code>, een paar rijen onder het totaal (bv. 1-1800 van 1809), op desktop</td><td>Twee losse oorzaken met hetzelfde symptoom. (1) <code>verifyDomRowCount()</code> herintroduceerde DOM-gebaseerd tellen: het overschreef <code>currentCount</code> (de eerlijke opgetelde bron) met een ruwe <code>tr</code>-telling uit de DOM. Die telling wijkt legitiem af (group-header-rijen bij <code>hasGrouping</code>, een <code>.nodata</code>-rij), waardoor <code>currentCount</code> onder het echte totaal geklopt werd en de teller net te vroeg bevroor — precies wat de teller niet mag doen. (2) <code>loadMoreRows()</code> deed een kale <code>fetch()</code> zónder timeout: bleef de laatste batch-request hangen (gestalde TCP / trage Access-query op de laatste pagina), dan resolvde de promise nooit, bleef <code>isLoading</code> op <code>true</code> staan en parkeerde de prefetch-lus op <code>await load()</code> — een hangende fetch gooit niks, dus glipte langs elk <code>hasMore</code>-vangnet. Dit verklaart de intermittentie.</td><td><code>verifyDomRowCount()</code> is puur diagnostisch — het logt de divergentie maar overschrijft <code>currentCount</code> niet meer (telt bovendien alleen <code>tr[data-id]</code>). <code>currentCount</code> is weer de enige bron (opgetelde appends), zoals bedoeld. <code>loadMoreRows()</code> krijgt een 20s <code>AbortController</code>-timeout: een hangende request wordt zo de retriable-failure die de bestaande retry-machinerie (4 pogingen, dan <code>hasMore=false</code>) al afhandelt — de teller settlet i.p.v. eeuwig te spinnen.</td></tr>
            <tr><td>Teller op mobiel bereikt het totaal nooit — stopt op 1-200 / 1-400 van M en beweegt niet meer</td><td>Twee stale <code>CmaInfiniteScroll</code>-instances op hetzelfde <code>#listTable</code> (een <code>FormController</code> die bij navigatie nooit werd afgebroken). Ze racen; de verliezer krijgt enkel-duplicaat-batches, raakt de all-duplicate-stop (<code>uniqueRows === 0 → hasMore=false</code>) en stopt vroeg.</td><td>één scroller per tabel-element — de constructor vernietigt een al-geregistreerde <code>table._cmaScroller</code> en registreert zichzelf; <code>destroy()</code> deregistreert. Zo kan er nooit een tweede scroller op dezelfde tabel meelezen.</td></tr>
            <tr><td>Teller blijft hangen een paar rijen onder het totaal (bv. <code>1-1800 van 1806</code>) — ook ná alle client-fixes, op elke omgeving</td><td>De <span class="cma-tool__strong">server</span>, niet de client. De <code>listQuery</code> heeft een één-op-veel JOIN, dus het resultaat bevat dubbele-id-rijen. <code>totalCount</code> was <code>COUNT(*)</code> over die join (1806 opgeblazen rijen), terwijl server- en client-dedup één rij per id tonen (1800 records). De opgetelde-distinct-teller kan de opgeblazen noemer nooit halen → <code>(laden…)</code> settlet nooit. De drie voorgaande fixes zaten allemaal client-side en konden dit per definitie niet raken.</td><td><code>JsonFormService::getTableHtml</code> dedupt de gerenderde rijen per id (geen dubbele <code>&lt;tr&gt;</code> meer in de DOM) én telt <code>totalCount</code> als <span class="cma-tool__strong">DISTINCT id</span> zodra de query een JOIN heeft (Access mist <code>COUNT(DISTINCT)</code>, dus <code>COUNT(*)</code> over een <code>SELECT DISTINCT [id]</code>-subquery). De keyset-cursor (<code>lastId</code>) blijft de ruwe laatste rij, dus paginering loopt gewoon door. Nu is noemer = getoonde records en verdwijnt de teller bij voltooiing. Regressietest: <code>ListTableRenderTest::testJoinDuplicateRowsAreDedupedAndTotalIsDistinct</code>.</td></tr>
            <tr><td>Teller bevriest onder het totaal (bv. <code>1-1400 van 1423 (laden…)</code>) na zoeken, filteren of navigeren naar een ander formulier, met een console-error <code>[Infinite Scroll] Pagination stopped at X/M</code> waarvan de getallen (en soms de formuliernaam) niet kloppen met wat de teller toont</td><td>De stale-tabel-recovery in <code>load()</code>: verdween de eigen tabel uit de DOM (lijst opnieuw gerenderd), dan <span class="cma-tool__em">adopteerde</span> de oude scroller <code>document.getElementById('listTable')</code> — een tabel die al een eigen verse scroller heeft. Dat omzeilt de constructor-guard die twee scrollers op dezelfde tabel moet voorkomen: twee prefetch-lussen vlechten door één tbody met gedeelde dedup-set, elk telt alleen z'n eigen appends. De gedeelde <code>#recordCount</code> toont de laatste schrijver (bevroren onder het totaal), en de stale scroller — wiens batches volledig wegdedupen — gooit de pagination-stopped-error met tellers van een lijst die al van het scherm is (na in-page formulier-navigatie zelfs met de vorige formuliernaam, en potentieel met rijen van het verkeerde formulier in de tabel).</td><td>een scroller waarvan de tabel uit de DOM is, adopteert niets meer maar trekt zich terug — <code>_retireIfStale()</code> (destroy) draait vóór elke fetch én na de await; <code>destroyed</code> onderdrukt de misleidende throw. Alleen de geregistreerde eigenaar (<code>table._cmaScroller</code>) mag <code>#recordCount</code> schrijven, en <code>_autoPrefetchRows()</code> schrijft geen teller meer namens een destroyed scroller.</td></tr>
            <tr><td>Teller bevriest exact op een batch-grens (bv. <code>records 1-2000 van 2174 (laden…)</code>, 2000 = 4×500) en beweegt nooit meer — geen console-error, en ook scrollen naar beneden laadt niets bij</td><td>Eén promise die nooit settlet, brickt de hele pijplijn: <code>load()</code> wacht op de <code>loadMore</code>-callback; settlet die nooit (gestalde fetch wiens 20s-aborttimer verloren ging — systeemslaap of tab-freeze midden in een request), dan blijven <code>isLoading</code> én <code>paused</code> eeuwig <code>true</code>. De prefetch-lus parkeert op zijn <code>await</code>, en <code>onScroll</code> keert op beide flags vroegtijdig terug — zelfs handmatig scrollen kan niets meer starten. Een hangende promise gooit niets, dus geen enkel <code>hasMore</code>-vangnet of error-report vuurt. Zelfde effect als een synchrone throw tussen <code>isLoading=true</code> en het <code>try</code>-blok (<code>showLoading()</code> stond daarbuiten). Beide bewezen met de uitvoerbare harness die de échte client-code draait.</td><td><code>_loadWithStallGuard()</code> in <code>CmaInfiniteScroll</code> racet elke <code>loadMore</code>-aanroep tegen een 30s-timer (bewust boven de interne 20s-abort van <code>loadMoreRows</code>, dus hij vuurt alleen als dat mechanisme al faalde). Verliest de callback, dan wordt dat dezelfde retriable failure die de bestaande retry-machinerie afhandelt: een transiënte stall (slaap/resume) herstelt vanzelf en laadt door tot het totaal; een blijvende stall stopt na 4 pogingen luid via de pagination-stopped-error. <code>showLoading()</code> is bovendien binnen het <code>try</code>-blok getrokken zodat een synchrone throw daar nooit meer <code>isLoading</code> vast kan zetten.</td></tr>
        </tbody>
    </table>

    <h2>Content blocks (blockedit)</h2>
    <p class="docs-meta">Het content-block veld (<code>&lt;div class="blockedit"&gt;</code> rond een <code>data-allow-html</code> textarea, aangestuurd door <code>cma/assets/js/blockedit.js</code>) rendert per blok een CKEditor. Hetzelfde veld is tegelijk een CKEditor-instance én de serialisatie-sink — die dubbele eigenaarschap is de bron van de meeste content-verlies-symptomen. Blockedit hookt zelf het submit-event (én programmatic <code>form.submit()</code>) van het formulier rond een <code>.blockedit</code> container en oogst de blokken vlak vóór verzending — host-pagina's hoeven <code>blockedit_collect_htmls()</code> niet meer zelf aan te roepen, maar mogen dat blijven doen (de aanroep is idempotent).</p>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak</th><th>Fix</th></tr></thead>
        <tbody>
            <tr><td>Blok verslepen (drag-drop) maakt het rich-text veld leeg</td><td><code>blockedit_init_dragdrop</code> zocht bij dragstart <code>CKEDITOR.instances["cke_&lt;naam&gt;"]</code> (de id van de <code>.cke</code> container i.p.v. de textarea-id), dus <code>updateElement()</code> gooide een (ingeslikte) fout en de editor-inhoud werd nooit naar de textarea geflusht. De DOM-move blankt vervolgens de iframe-editor en dragend's <code>destroy()</code> (zonder arg → roept updateElement) schreef die lege inhoud terug.</td><td>dragstart stript de <code>cke_</code>-prefix zodat de flush wél draait, en dragend gebruikt <code>destroy(true)</code> (noUpdate) zodat de geblankte editor de zojuist geflushte textarea niet overschrijft. De up/down-knoppen (<code>blockedit_save_ckeditor_states</code>) waren al correct.</td></tr>
            <tr><td>Nieuw blok toevoegen: de CKEditor verschijnt niet</td><td>De editor van een nieuw blok wordt uitgesteld in <code>pendingCKEditors</code> zolang het accordeon nog niet <code>.opened</code> is, en pas aangemaakt via <code>blockedit_process_pending_ckeditors</code> (150ms setTimeout). Die queue maakt zichzelf synchroon leeg en vult 150ms later weer aan, waardoor een net-geopende editor uit de handshake kon vallen.</td><td><code>blockedit_click</code> maakt de editor(s) van het zojuist geopende blok direct aan (idempotent via de <code>'exists'</code>-check in <code>blockedit_createCKEditor</code>), naast de bestaande queue.</td></tr>
            <tr><td>Array "+"-knop: de CKEditor van het nieuwe array-element verschijnt niet (pas na opslaan + opnieuw ophalen)</td><td>Zelfde defer/handshake-oorzaak als de "nieuw blok"-rij hierboven, maar via een ander pad: <code>blockedit_array_add_array_element</code> voegde het array-element toe en riep enkel <code>blockedit_create_htmls()</code>. Een uitgestelde/uit-de-queue-gevallen editor werd daardoor nooit aangemaakt tot een save+reload het veld vers rendert. Die fix zat alleen in <code>blockedit_click</code> (het top-level-blok-pad), niet in de array-"+".</td><td><code>blockedit_array_add_array_element</code> doet nu dezelfde directe (re)create — <code>blockedit_process_pending_ckeditors()</code> + een korte-delay loop over de textareas in de array-container, idempotent via de <code>'exists'</code>-check.</td></tr>
            <tr><td>Content-block veld wordt leeg opgeslagen / edits verdwijnen</td><td><code>blockedit_collect_htmls</code> schrijft het veld alleen als de serialisatie niet leeg is (<code>cTotalHTML != ""</code>) en doet niets als <code>contentblocks.json</code> nog niet geladen is. Na een record-switch / <code>clearForm</code> (<code>setData('')</code> + <code>blockedit_clear</code>) zonder dat de blokken herbouwd zijn, produceert collect <code>""</code>, slaat de schrijfactie over en wordt een lege waarde opgeslagen.</td><td><code>blockedit_collect_htmls</code> houdt per veld een laatst-bekende-goede snapshot bij (<code>blockedit_lastGood</code>, getagd met het record-id). Produceert collect leeg terwijl er géén blokken gerenderd zijn (<code>.blockedit_block</code> count 0 — gewist/nog niet herbouwd) én het veld leeg is, dan herstelt het de snapshot i.p.v. leeg op te slaan. De record-id-guard zorgt dat een nieuw record (id <code>null</code>) nooit de inhoud van een vorig record erft. (Bewust niet "harvesten in <code>blockedit_clear</code>": <code>newRecord()</code> roept <code>clearForm()</code> zonder <code>populateForm</code>, dus harvesten zou oude inhoud naar het nieuwe record lekken.) De gated <code>[BlockEdit][LOSS-RISK]</code> tripwire-logging blijft en meldt nu "prevented empty save".</td></tr>
            <tr><td>Nieuw blok toevoegen: editor blijft een lege div</td><td>Twee gaten in <code>blockedit_createCKEditor</code>. (1) <code>CKEDITOR.replace()</code> registreert de instance synchroon, vóór de asynchrone skin/iframe-build; draait die build in een verborgen of nog-animerende container (CKEditor 4.5.7 kent geen hidden-element handling), dan blijft er een geregistreerde maar lege editor achter. De <code>'exists'</code>-check zag zo'n instance als gezond, waardoor elke latere create-poging (queue, directe passes, retries) een no-op werd — de blank div was permanent. (2) De defer-check keek alleen naar de <code>.opened</code>-class van het accordeon, niet naar echte zichtbaarheid: een verborgen ancestor (tab, dialog, form-load) passeerde de check. Daarnaast maakten de array-pijltjes (innerHTML-swap) de iframe-editor kapot terwijl de instance geregistreerd bleef — zelfde blanco resultaat.</td><td><code>blockedit_createCKEditor</code> is self-healing — de <code>'exists'</code>-check valideert nu dat de editor-chrome bestaat én aan het document hangt (anders destroy + recreate), defer gebeurt op echte zichtbaarheid (<code>offsetParent === null</code>), en een ready-watchdog vernietigt en herbouwt een instance die 1s na aanmaak nog niet <code>'ready'</code> is (max 2 retries, daarna blijft de ruwe textarea bruikbaar). <code>blockedit_click</code> maakt editors aan vanuit de <code>show(100)</code>-completion callback i.p.v. een parallelle timer en wist de inline display die jQuery 1.9 achterlaat. De array-pijltjes verplaatsen nu DOM-nodes met flush/destroy/recreate i.p.v. innerHTML-swap, en verwijder-paden (<code>blockedit_clear</code>, blok- en array-delete) ruimen hun CKEditor-instances op.</td></tr>
            <tr><td>Veld is onzichtbaar én onbewerkbaar; opslaan bewaart steeds de oude waarde (console: 500/404 op <code>contentblocks.json</code>, of een 200 met ongeldige JSON)</td><td>De bloktemplates (<code>contentblocks.json</code>) konden niet geladen worden, dus blockedit rendert geen blokken en <code>blockedit_collect_htmls</code> serialiseert bewust niets (de loss-prevention bewaart dan de oude veldwaarde i.p.v. leeg op te slaan). De hoofdveld-editor is door CSS tot 0px ingeklapt, dus de operator ziet niets en kan niets bewerken.</td><td>loader probeert meerdere locaties (<code>/cma/assets/contentblocks/contentblocks.json</code>, dan <code>/cma_contentblocks.json</code> voor oudere CMA's). Bij een 200-respons met onbruikbare JSON: falen álle locaties, dan degradeert het veld naar gewone bewerking — de hoofdveld-editor wordt uitgeklapt (of de ruwe textarea getoond) zodat inhoud zichtbaar en bewerkbaar blijft. De échte fix is altijd server-side: controleer waarom de JSON-URL faalt (rewrite-rule, rechten, ontbrekend bestand).</td></tr>
            <tr><td>Opslaan bewaart altijd de oude veldwaarde, zonder zichtbare fout</td><td>In <code>blockedit_collect_htmls</code> stond een niet-gedeclareerde toewijzing (<code>cSpecifier = …</code>). Het bestand draait in strict mode, dus dit gooide een <code>ReferenceError</code> bij het éérste getypte blok — de hele harvest stierf en het veld behield zijn oude waarde. De exception wordt door form-controller's try/catch opgevangen en alleen als warning gelogd, waardoor het stil blijft; bij een directe aanroep verdwijnt hij in de submit-flow.</td><td><code>var cSpecifier</code> gedeclareerd. Daarnaast: serialisatie per blok in try/catch met <code>cmaLog.error</code> (één kapot blok doodt niet langer de hele save), onbekende bloktypes worden gemeld en overgeslagen i.p.v. te crashen, en <code>cTotalHTML</code> wordt per veld gereset (zonder die reset lekt veld A's inhoud naar veld B op pagina's met meerdere blockedit-velden).</td></tr>
        </tbody>
    </table>

    <h2>File-browser wizard</h2>
    <table class="listtable">
        <thead><tr class="listheader"><th style="width:340px">Symptoom</th><th>Oorzaak</th><th>Fix</th></tr></thead>
        <tbody>
            <tr><td>Upload met zelfde extensie als geselecteerd bestand vraagt niet "overschrijven?"</td><td>Feature bestond nog niet.</td><td>Vraagt "Ja, overschrijf bestand" / "Nee, plaats ernaast" wanneer een single-file upload dezelfde extensie heeft.</td></tr>
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
            <tr><td><code>email_from</code></td><td><code>webmaster@stenversonline.nl</code></td><td>Default From-<span class="cma-tool__strong">adres</span>. De Email-klasse leest dit veld.</td></tr>
            <tr><td><code>email_fromname</code></td><td><code>(leeg)</code></td><td>Default From-<span class="cma-tool__strong">naam</span> (weergavenaam). Bevat het per ongeluk een e-mailadres (oude config), dan valt de naam terug op <code>company</code> en wordt dat adres als afzender gebruikt — back-compat.</td></tr>
            <tr><td><code>company</code></td><td><code>RINO amsterdam</code></td><td>Bedrijfsnaam; fallback voor de From-naam.</td></tr>
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
    <p><code>cma/bootstrap.inc</code> registreert altijd een afterSend-callback op de static <code>Email::$afterSend</code> property:</p>
    <pre><code>\App\Library\Email::$afterSend = function(array $data) {
    \Cma\Services\EmailLogService::log($data);
};
</code></pre>
    <p>Elke <code>Email::send()</code> roept deze hook aan met <code>$data</code> dat bevat: <code>success</code>, <code>from</code>, <code>to</code> (originele recipients, vóór test-clearing), <code>cc</code>, <code>bcc</code>, <code>subject</code>, <code>body</code>, <code>error</code>. <code>EmailLogService</code> persist deze naar <code>tblEmailLog</code> voor admin-review.</p>
    <p><span class="cma-tool__strong">Beheer-UI:</span> het archief is bereikbaar via <span class="cma-tool__strong">Alle beheerstools → Site gezondheid → E-mail log</span> (het standaard-CMA-formulier <code>emaillog</code>, route <code>/cma/form/emaillog</code>). De lijst toont datum/aan/onderwerp/status; het detail-scherm is read-only met een <span class="cma-tool__strong">Opnieuw verzenden</span>-knop (extra-button die <code>CMA.emailLog.resend()</code> aanroept → <code>api/email-actions.php</code> → <code>EmailLogService::resend()</code>). faalde die knop op niet-productie-omgevingen met "Kan de e-mail actie niet uitvoeren": <code>Email::send()</code> echoot daar een gesimuleerde-mail-debugtabel, die vóór de JSON-response belandde en de strikte JSON-parse van de client brak — de service buffert die output nu weg. Verwijderen kan via de standaard verwijder-knop (<code>EmailLogService::delete()</code>). Records ouder dan 30 dagen worden opgeruimd door <code>EmailLogService::cleanup()</code>.</p>
    <p>Controleerbaar via env-var <code>EMAIL_LOG_ENABLED</code> (default <code>true</code>). Er staat een <code>class_exists</code> guard om de afterSend-assignment heen zodat half-updated installs (waar <code>Email.php</code> nog niet autoloadable is) niet crash'en op deze regel.</p>

    <h2>Legacy LibMailer / SendMail()</h2>
    <p>De oude <code>library/classes/class_mailer.inc</code> (<code>LibMailer</code> plus de globale <code>SendMail()</code> / <code>SendMailNoTemplate()</code> functies, een VBScript-conversie) is nu een dunne wrapper rond <code>App\Library\Email</code>. Bij <code>Send()</code> bouwt <code>LibMailer</code> een <code>Email</code>-object en delegeert. Gevolg: er is <span class="cma-tool__strong">één</span> mail-codepad, en ook deze legacy-mails lopen door de afterSend-hook en landen dus in <code>tblEmailLog</code>. sprak <code>LibMailer</code> PHPMailer rechtstreeks aan en werden die mails NIET gelogd.</p>
    <p>De publieke API is ongewijzigd (<code>-&gt;Subject</code>, <code>-&gt;Body</code>, <code>-&gt;From</code>, <code>-&gt;AddRecipient(s)</code>, <code>-&gt;AddRecipientCC/BCC</code>, <code>-&gt;AddAttachment</code>, <code>-&gt;Send()</code>, <code>-&gt;strError</code>, <code>-&gt;CheckSend()</code>). Body wordt bewust <span class="cma-tool__strong">zonder</span> template verstuurd (<code>setUseTemplate(false)</code>), gelijk aan wat de oude class effectief deed.</p>
    <p>Je kunt optioneel een Reply-To zetten via <code>$mailer-&gt;replyTo = 'antwoord@example.nl'</code> — die wordt doorgegeven aan <code>Email::setReplyTo()</code>. Leeg = geen Reply-To. In local/simulatie-modus rendert <code>LibMailer</code> zijn eigen klassieke on-screen preview-tabel (<code>ShowPreview()</code>) i.p.v. te versturen, zodat de vertrouwde preview-opzet behouden blijft.</p>
    <p>De globale functies staan <code>SendMail()</code>, <code>SendMailNoTemplate()</code> en <code>internal_SendMail()</code> in een <code>function_exists</code>-guard. Niet elke geconverteerde ASP-site spreekt dit 8-parameter dialect: mijnRINO's ASP-mailer had bijvoorbeeld 9 parameters (<code>strFromName</code> tussen from en cc). Zo'n site definieert zijn eigen variant in de eigen bootstrap vóórdat <code>class_mailer.inc</code> laadt; de voorgedefinieerde functie wint dan. Verder slaat <code>Email</code> bij verzenden bijlagen over via <code>is_file()</code> i.p.v. <code>file_exists()</code>, zodat ook directory-paden stil worden overgeslagen (gelijk aan <code>lib_FileExists</code> in het ASP-origineel).</p>

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
    <p>De Ollama-kaart toont de curated modellenlijst inline. Wanneer <code>llamacpp</code>'s probe succesvol is, wordt de Ollama-kaart overgeslagen — de cook heeft dan al een werkende engine.</p>

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
            <tr><td><a href="tools/llm_analyse.php" target="_top"><code>llm_analyse.php</code></a></td><td>Status-dashboard: config-tabel, endpoint-probe, lokale modellen-lijst, recente <code>[Llm]</code> fouten uit php_errors.log. Standaard CMA-login.</td></tr>
            <tr><td><a href="tools.php?tool=llm" target="_top"><code>tools_llm.php</code></a></td><td>Management-page: probe per engine, inline modellen-lijst per engine, install-steps per OS in collapsible details. De Ollama-kaart heeft de curated <code>ollama pull</code>-lijst inline.</td></tr>
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
