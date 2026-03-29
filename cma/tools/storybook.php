<?php
/**
 * Web Components Storybook
 *
 * Visual showcase of all web components available in CMA and the library.
 * Useful for testing, documentation, and design consistency.
 */

require_once __DIR__ . '/../bootstrap.inc';

use Cma\SecurityHelper;
use Cma\ToolbarHelper;

// Developer access required
if (!SecurityHelper::isDeveloper()) {
    http_response_code(403);
    echo '<lib-message type="error">Alleen toegankelijk voor developers</lib-message>';
    exit;
}

cma_html_header('Component Storybook');
?>
<script src="/cma/ckeditor/ckeditor.js" defer></script>
</HEAD>
<BODY class="contentbody cma-form tools storybook">

<?php
ToolbarHelper::start(true);
ToolbarHelper::title('Component Storybook');
ToolbarHelper::end();
?>
<div style="position: fixed; top: 6px; right: 32px; z-index: 1000;">
    <button class="btn btn-secondary" id="darkModeToggle" onclick="document.documentElement.classList.toggle('dark-mode'); this.querySelector('.lnr').className = document.documentElement.classList.contains('dark-mode') ? 'lnr lnr-sun' : 'lnr lnr-moon'; localStorage.setItem('storybook-darkmode', document.documentElement.classList.contains('dark-mode') ? '1' : '0');" style="height: 24px; padding: 0 10px; font-size: var(--font-size-xs);">
        <span class="lnr lnr-moon"></span> Dark mode
    </button>
</div>
<script>
if (localStorage.getItem('storybook-darkmode') === '1') {
    document.documentElement.classList.add('dark-mode');
    document.addEventListener('DOMContentLoaded', function() {
        var icon = document.querySelector('#darkModeToggle .lnr');
        if (icon) icon.className = 'lnr lnr-sun';
    });
}
</script>

<style>

.nav-sidebar {
    position: fixed;
    width: 180px;
    height: calc(100% - 29px);
    background: var(--bg-surface);
    border-right: 1px solid var(--border-color);
    padding: 10px 5px;
    overflow-y: auto;
    top: 29px;
}

.component-section {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    margin-bottom: 30px;
    overflow: hidden;
    scroll-margin-top: 40px;
}

.component-header {
    background: var(--bg-surface-alt);
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.component-header h2 {
    margin: 0;
    font-size: var(--font-size-lg);
    color: var(--text-primary);
}

.component-header .dark-toggle {
    margin-left: auto;
    cursor: pointer;
    font-size: var(--font-size-xs);
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 3px;
    border: 1px solid var(--border-color);
    background: transparent;
    transition: background 0.15s;
}

.component-header .dark-toggle:hover {
    background: var(--bg-hover, rgba(0,0,0,0.05));
}

.component-section.section-dark .component-body {
    background-color: var(--bg-dark, #1a1a2e) !important;
    color: var(--text-dark-primary, #e0e0e0);
}

.component-section.section-dark .component-body,
.component-section.section-dark .component-content,
.component-section.section-dark .component-options {
    --bg-surface: #2a2a3e;
    --bg-surface-alt: #222236;
    --text-primary: #e0e0e0;
    --text-secondary: #aaa;
    --text-muted: #888;
    --border-color: #444;
    --border-dark: #555;
    --bg-hover: rgba(255,255,255,0.08);
    --input-border: #555;
    --color-primary: #5b9bd5;
    --popup-caption-bg: #2a2a3e;
}

.component-header .tag {
    font-size: var(--font-size-xs);
    padding: 2px 8px;
    border-radius: 3px;
    background: var(--color-primary);
    color: #fff;
}

.component-header .tag.lib {
    background: var(--color-success);
}

.component-header .tag.cma {
    background: var(--color-accent);
}

.component-body {
    padding: 20px;
    background-color: #fefefe;
}

html.dark-mode .component-body {
    background-color: var(--bg-surface);
}

/* Two-column layout when options panel is present */
.component-body:has(.component-options) {
    display: flex;
    gap: 20px;
    align-items: stretch;
}

.component-content {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
}

/* Make content children not shrink */
.component-content > * {
    flex-shrink: 0;
}

.component-options {
    flex: 0 0 280px;
    background: var(--bg-surface-alt);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 15px;
    font-size: var(--font-size-sm);
    overflow-y: auto;
}

.component-options h4 {
    margin: 0 0 8px 0;
    font-size: var(--font-size-xs);
    text-transform: uppercase;
    color: var(--text-muted);
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 4px;
}

.component-options h4:not(:first-child) {
    margin-top: 15px;
}

.component-options dl {
    margin: 0 0 10px 0;
}

.component-options dt {
    font-family: 'Monaco', 'Consolas', monospace;
    font-weight: 600;
    color: var(--color-primary);
    margin-bottom: 2px;
}

.component-options dd {
    margin: 0 0 8px 0;
    color: var(--text-secondary);
    line-height: 1.4;
}

.component-options code {
    background: var(--bg-surface);
    padding: 1px 4px;
    border-radius: 3px;
    font-size: var(--font-size-xs);
}

.component-example {
    background: var(--bg-alternate);
    border: 1px dashed var(--border-color);
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 15px;
}

.component-description {
    color: var(--text-muted);
    font-size: var(--font-size-sm);
    margin: 0;
    margin-left: auto;
    max-width: 50%;
    text-align: right;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.demo-row {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 15px;
}

.demo-label {
    font-weight: 500;
    min-width: 100px;
    color: var(--text-secondary);
    font-size: var(--font-size-sm);
}

.code-block {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 12px 16px;
    border-radius: 4px;
    font-family: 'Monaco', 'Consolas', monospace;
    font-size: var(--font-size-sm);
    overflow-x: auto;
    margin-top: 10px;
}

/* Playground - editable code with live preview */
.playground {
    margin-top: 0;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    /* Note: no overflow:hidden to allow dropdowns to escape container */
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 200px;
}

.playground-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--bg-surface-alt);
    padding: 8px 12px;
    border-bottom: 1px solid var(--border-color);
    border-radius: 6px 6px 0 0;
}

.playground-header > span {
    font-size: var(--font-size-xs);
    text-transform: uppercase;
    color: var(--text-muted);
    font-weight: 600;
}

.playground-actions {
    display: flex;
    gap: 6px;
}

.playground-code {
    background: #1e1e1e;
    padding: 0;
    margin: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.playground-code textarea {
    width: 100%;
    min-height: 80px;
    flex: 1;
    background: #1e1e1e;
    color: #d4d4d4;
    border: none;
    padding: 12px 16px;
    font-family: 'Monaco', 'Consolas', monospace;
    font-size: var(--font-size-sm);
    line-height: 1.5;
    resize: vertical;
    outline: none;
    box-sizing: border-box;
}

.playground-code textarea:focus {
    background: #252526 !important;
}

.playground-preview {
    padding: 20px;
    background: var(--bg-alternate);
    border-top: 1px solid var(--border-color);
    min-height: 60px;
    overflow: visible;
    border-radius: 0 0 6px 6px;
}

.playground-preview:empty::before {
    content: 'Preview wordt hier getoond...';
    color: var(--text-muted);
    font-style: italic;
    font-size: var(--font-size-sm);
}

.playground-error {
    background: var(--color-error-bg, #fef2f2);
    color: var(--color-error, #dc2626);
    padding: 10px 15px;
    font-size: var(--font-size-sm);
    border-top: 1px solid var(--color-error, #dc2626);
}

.main-content {
    margin-left: 200px;
}

/* Color palette section */
.color-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
}

.color-swatch {
    height: 60px;
    border-radius: 6px;
    display: flex;
    align-items: flex-end;
    padding: 8px;
    font-size: var(--font-size-xs);
    font-family: monospace;
    color: #fff;
    text-shadow: 0 1px 2px rgba(0,0,0,0.5);
}

.color-description {
    color: var(--text-secondary);
    font-size: var(--font-size-sm);
    margin-bottom: 10px;
}

.color-grid-text {
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
}

.color-swatch-text {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 12px;
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-family: monospace;
    font-size: var(--font-size-sm);
}

.color-swatch-text .hex-val {
    color: var(--text-muted);
    font-size: var(--font-size-2xs);
}

.hex-dark { display: none; }
html.dark-mode .hex-light { display: none; }
html.dark-mode .hex-dark { display: inline; }
</style>

<!-- Navigatie zijbalk -->
<nav class="nav-sidebar">
    <cma-tree id="storybook-nav" storage-key="storybook_nav"></cma-tree>
</nav>
<script>
(function() {
    const navData = [
        {
            type: 'folder',
            label: 'Library componenten',
            children: [
                { label: 'lib-combo', href: '#lib-combo', icon: 'lnr-list' },
                { label: 'lib-datepicker', href: '#lib-datepicker', icon: 'lnr-history' },
                { label: 'lib-dialog', href: '#lib-dialog', icon: 'lnr-layers' },
                { label: 'lib-fileuploader', href: '#lib-fileuploader', icon: 'lnr-upload' },
                { label: 'lib-gauge', href: '#lib-gauge', icon: 'lnr-chart-bars' },
                { label: 'lib-histogram', href: '#lib-histogram', icon: 'lnr-chart-bars' },
                { label: 'lib-label', href: '#lib-label', icon: 'lnr-tag' },
                { label: 'lib-loader', href: '#lib-loader', icon: 'lnr-hourglass' },
                { label: 'lib-menu', href: '#lib-menu', icon: 'lnr-menu' },
                { label: 'lib-message', href: '#lib-message', icon: 'lnr-bubble' },
                { label: 'lib-search-input', href: '#lib-search-input', icon: 'lnr-magnifier' },
                { label: 'lib-switch', href: '#lib-switch', icon: 'lnr-sync' },
                { label: 'lib-table', href: '#lib-table', icon: 'lnr-table' },
                { label: 'lib-timepicker', href: '#lib-timepicker', icon: 'lnr-clock' },
                { label: 'lib-tip', href: '#lib-tip', icon: 'lnr-question-circle' },
                { label: 'lib-toaster', href: '#lib-toaster', icon: 'lnr-bubble' }
            ]
        },
        {
            type: 'folder',
            label: 'Library functies',
            icon: 'lnr-code',
            children: [
                { label: 'libAlert', href: '#libAlert', icon: 'lnr-warning' },
                { label: 'libConfirm', href: '#libConfirm', icon: 'lnr-question-circle' },
                { label: 'libPrompt', href: '#libPrompt', icon: 'lnr-text-format' }
            ]
        },
        {
            type: 'folder',
            label: 'CMA componenten',
            icon: 'lnr-layers',
            children: [
                { label: 'blockeditor (CKEditor)', href: '#blockeditor', icon: 'lnr-text-format' },
                { label: 'cma-fold', href: '#cma-fold', icon: 'lnr-folder' },
                { label: 'cma-groupbox', href: '#cma-groupbox', icon: 'lnr-layers' },
                { label: 'cma-htmledit', href: '#cma-htmledit', icon: 'lnr-code' },
                { label: 'cma-sortlist', href: '#cma-sortlist', icon: 'lnr-list' },
                { label: 'cma-tabs', href: '#cma-tabs', icon: 'lnr-layers' },
                { label: 'cma-toolbar', href: '#cma-toolbar', icon: 'lnr-wrench' },
                { label: 'cma-tree', href: '#cma-tree', icon: 'lnr-layers' }
            ]
        },
        {
            type: 'folder',
            label: 'Ontwerpsysteem',
            icon: 'lnr-palette',
            children: [
                { label: 'Badges', href: '#badges', icon: 'lnr-tag' },
                { label: 'Formuliervelden', href: '#form-controls', icon: 'lnr-document' },
                { label: 'Kleuren', href: '#colors', icon: 'lnr-palette' },
                { label: 'Knoppen', href: '#buttons', icon: 'lnr-rocket' },
                { label: 'Linearicons', href: '#linearicons', icon: 'lnr-palette' },
                { label: 'Tabelstijlen', href: '#table-styling', icon: 'lnr-table' },
                { label: 'Tooltips', href: '#tooltips', icon: 'lnr-pointer-up' },
                { label: 'Typografie', href: '#typography', icon: 'lnr-text-format' }
            ]
        },
        {
            type: 'folder',
            label: 'PHP Helpers',
            icon: 'lnr-code',
            children: [
                { label: 'ResponsiveImage', href: '#responsive-image', icon: 'lnr-picture' }
            ]
        },
        {
            type: 'folder',
            label: 'Wizards',
            icon: 'lnr-magic-wand',
            children: [
                { label: 'Bestandsbrowser', href: '#file-browser', icon: 'lnr-folder' }
            ]
        }
    ];

    var tree = document.getElementById('storybook-nav');
    tree.setData(navData);

    // Handle item clicks - scroll to section (works immediately)
    var isClickNavigation = false;
    var clickTimeout = null;

    tree.addEventListener('item-click', function(e) {
        var href = e.detail.href;
        if (href && href.charAt(0) === '#') {
            // Pause scroll-spy during click navigation
            isClickNavigation = true;
            clearTimeout(clickTimeout);
            clickTimeout = setTimeout(function() {
                isClickNavigation = false;
            }, 1000);

            var target = document.getElementById(href.substring(1));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                history.pushState(null, '', href);
            }
        }
    });

    // Select current item based on URL hash
    if (window.location.hash) {
        tree.selectByHref(window.location.hash);
    }

    // Expand all folders by default
    tree.expandAll();

    // Wait for DOM to be fully loaded before setting up scroll-spy
    document.addEventListener('DOMContentLoaded', function() {
        // Add dark mode toggle to each component header
        document.querySelectorAll('.component-header').forEach(function(header) {
            var section = header.closest('.component-section');
            if (!section) return;
            var btn = document.createElement('button');
            btn.className = 'dark-toggle';
            btn.innerHTML = '<span class="lnr lnr-sun"></span> Dark';
            btn.title = 'Schakel donkere modus in/uit voor dit component';
            btn.addEventListener('click', function() {
                section.classList.toggle('section-dark');
                btn.innerHTML = section.classList.contains('section-dark')
                    ? '<span class="lnr lnr-sun"></span> Light'
                    : '<span class="lnr lnr-sun"></span> Dark';
            });
            header.appendChild(btn);
        });

        var sections = document.querySelectorAll('.component-section[id]');
        var currentSection = null;

        // Scroll-spy: highlight tree item when component-header is visible
        function updateActiveSection() {
            if (isClickNavigation) {
                return;
            }

            var bestSection = null;
            var lastAbove = null;
            var viewportHeight = window.innerHeight;

            for (var i = 0; i < sections.length; i++) {
                var section = sections[i];
                var header = section.querySelector('.component-header');
                if (header) {
                    var rect = header.getBoundingClientRect();
                    // Header is visible in viewport
                    if (rect.top >= 0 && rect.top < viewportHeight) {
                        bestSection = section;
                        break; // First visible header wins
                    }
                    // Track last header that scrolled above viewport
                    if (rect.top < 0) {
                        lastAbove = section;
                    }
                }
            }

            // If no header visible, use the last one that scrolled past
            if (!bestSection) {
                bestSection = lastAbove || sections[0];
            }

            if (bestSection && bestSection.id !== currentSection) {
                currentSection = bestSection.id;
                tree.selectByHref('#' + bestSection.id);
            }
        }

        var ticking = false;

        function onScroll() {
            if (!ticking) {
                requestAnimationFrame(function() {
                    updateActiveSection();
                    ticking = false;
                });
                ticking = true;
            }
        }

        // Try multiple scroll targets
        window.addEventListener('scroll', onScroll, true);
        document.addEventListener('scroll', onScroll, true);

        // Initial update
        updateActiveSection();
    });
})();
</script>

<div class="main-content">
<div class="storybook">

    <!-- ================================================================ -->
    <!-- BIBLIOTHEEK COMPONENTEN -->
    <!-- ================================================================ -->

    <!-- lib-combo -->

    <section class="component-section" id="lib-combo">
        <div class="component-header">
            <h2>lib-combo</h2>
            <span class="tag lib">library</span>
            <p class="component-description">Keuzelijst met zoekfunctie en async laden</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><div class="demo-row">
    <span class="demo-label">Standaard:</span>
    <div style="width: 250px;">
        <lib-combo placeholder="Selecteer een optie..."></lib-combo>
    </div>
</div>
<div class="demo-row">
    <span class="demo-label">Met waarde:</span>
    <div style="width: 250px;">
        <lib-combo placeholder="Selecteer..." value="2"></lib-combo>
    </div>
</div>
<div class="demo-row">
    <span class="demo-label">Readonly:</span>
    <div style="width: 250px;">
        <lib-combo placeholder="Alleen lezen" value="1" readonly></lib-combo>
    </div>
</div>
<div class="demo-row">
    <span class="demo-label">Disabled:</span>
    <div style="width: 250px;">
        <lib-combo placeholder="Niet beschikbaar" disabled></lib-combo>
    </div>
</div>
<div class="demo-row">
    <span class="demo-label">Verplicht (leeg):</span>
    <div style="width: 250px;">
        <lib-combo placeholder="Verplicht veld..." required></lib-combo>
    </div>
</div>
<div class="demo-row">
    <span class="demo-label">Verplicht (met waarde):</span>
    <div style="width: 250px;">
        <lib-combo placeholder="Verplicht veld..." value="1" required></lib-combo>
    </div>
</div>
<div class="demo-row">
    <span class="demo-label">Multiple:</span>
    <div style="width: 350px;">
        <lib-combo id="comboMulti" placeholder="Selecteer meerdere..." multiple></lib-combo>
    </div>
</div>

<p><strong>Methodes</strong></p>
<div class="demo-row">
    <span class="demo-label">setOptions:</span>
    <div style="width: 250px;">
        <lib-combo id="comboMethods" placeholder="Klik een knop..."></lib-combo>
    </div>
</div>
<div class="demo-row" style="gap: 6px; flex-wrap: wrap;">
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#comboMethods').setOptions([{value:'nl',label:'Nederland'},{value:'be',label:'België'},{value:'de',label:'Duitsland'},{value:'fr',label:'Frankrijk'}])">setOptions()</button>
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#comboMethods').addOption('uk','Verenigd Koninkrijk')">addOption()</button>
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#comboMethods').removeOption('fr')">removeOption('fr')</button>
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#comboMethods').clearOptions()">clearOptions()</button>
    <button class="btn btn-cancel" onclick="var c=this.closest('.playground-preview').querySelector('#comboMethods'); libAlert('value: '+JSON.stringify(c.value)+'\nselectedOptions: '+JSON.stringify(c.selectedOptions))">getValue()</button>
</div>

<p><strong>Multiple methodes</strong></p>
<div class="demo-row" style="gap: 6px; flex-wrap: wrap;">
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#comboMulti').setOptions([{value:'a',label:'Appel'},{value:'b',label:'Banaan'},{value:'c',label:'Citroen'},{value:'d',label:'Druif'},{value:'e',label:'Aardbei'}])">setOptions()</button>
    <button class="btn btn-secondary" onclick="var c=this.closest('.playground-preview').querySelector('#comboMulti'); c.value=['a','c','e']">Selecteer 3</button>
    <button class="btn btn-cancel" onclick="var c=this.closest('.playground-preview').querySelector('#comboMulti'); libAlert('value: '+JSON.stringify(c.value))">getValue()</button>
</div></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>name</dt>
                    <dd>Veldnaam voor formulier</dd>
                    <dt>value</dt>
                    <dd>Geselecteerde waarde</dd>
                    <dt>placeholder</dt>
                    <dd>Placeholder tekst (default: <code>"Selecteer..."</code>)</dd>
                    <dt>disabled</dt>
                    <dd>Schakel component uit (default: <code>false</code>)</dd>
                    <dt>readonly</dt>
                    <dd>Alleen lezen modus (default: <code>false</code>)</dd>
                    <dt>required</dt>
                    <dd>Markeer als verplicht veld, rode rand (default: <code>false</code>)</dd>
                    <dt>multiple</dt>
                    <dd>Meerdere selectie toestaan (default: <code>false</code>)</dd>
                </dl>
                <h4>AJAX attributen</h4>
                <dl>
                    <dt>ajax-url</dt>
                    <dd>URL voor asynchroon laden van opties</dd>
                    <dt>ajax-id</dt>
                    <dd>Property naam voor waarde in response (default: "id")</dd>
                    <dt>ajax-text</dt>
                    <dd>Property naam voor label in response (default: "text")</dd>
                    <dt>min-search</dt>
                    <dd>Minimum tekens voor zoeken (default: 0)</dd>
                </dl>
                <h4>Methodes</h4>
                <dl>
                    <dt>setOptions(array)</dt>
                    <dd>Stel opties in: <code>[{value, label, disabled, group}]</code></dd>
                    <dt>addOption(value, label, group)</dt>
                    <dd>Voeg een optie toe</dd>
                    <dt>removeOption(value)</dt>
                    <dd>Verwijder een optie op waarde</dd>
                    <dt>clearOptions()</dt>
                    <dd>Verwijder alle opties</dd>
                </dl>
                <h4>Properties</h4>
                <dl>
                    <dt>value</dt>
                    <dd>Geselecteerde waarde (string of array bij multiple)</dd>
                    <dt>selectedOptions</dt>
                    <dd>Array van geselecteerde optie-objecten</dd>
                </dl>
                <h4>Events</h4>
                <dl>
                    <dt>change</dt>
                    <dd>Bij wijziging. Detail: <code>{value, selectedOptions}</code></dd>
                    <dt>search</dt>
                    <dd>Bij zoekterm wijziging. Detail: <code>{term}</code></dd>
                </dl>
            </div>
        </div>
    </section>

    <!-- lib-datepicker -->

    <section class="component-section" id="lib-datepicker">
        <div class="component-header">
            <h2>lib-datepicker</h2>
            <span class="tag lib">library</span>
            <p class="component-description">Datumkiezer met kalender popup</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><div class="demo-row">
    <span class="demo-label">Standaard:</span>
    <lib-datepicker name="datum1"></lib-datepicker>
</div>
<div class="demo-row">
    <span class="demo-label">Met waarde:</span>
    <lib-datepicker name="datum2" value="2026-01-15"></lib-datepicker>
</div>
<div class="demo-row">
    <span class="demo-label">Met bereik:</span>
    <lib-datepicker name="datum3" min="2026-01-01" max="2026-12-31"></lib-datepicker>
</div>
<div class="demo-row">
    <span class="demo-label">Readonly:</span>
    <lib-datepicker name="datum4" value="2026-01-15" readonly></lib-datepicker>
</div>
<div class="demo-row">
    <span class="demo-label">Disabled:</span>
    <lib-datepicker name="datum5" value="2026-01-15" disabled></lib-datepicker>
</div>
<div class="demo-row">
    <span class="demo-label">Verplicht (leeg):</span>
    <lib-datepicker name="datum6" data-required="true" required></lib-datepicker>
</div>
<div class="demo-row">
    <span class="demo-label">Verplicht (met waarde):</span>
    <lib-datepicker name="datum7" value="2026-01-15" data-required="true" required></lib-datepicker>
</div>

<p><strong>Methodes</strong></p>
<div class="demo-row">
    <span class="demo-label">Methode demo:</span>
    <lib-datepicker id="dpMethods" name="datumMethods" value="2026-01-15"></lib-datepicker>
</div>
<div class="demo-row" style="gap: 6px; flex-wrap: wrap;">
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#dpMethods').open()">open()</button>
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#dpMethods').close()">close()</button>
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#dpMethods').toggle()">toggle()</button>
    <button class="btn btn-cancel" onclick="var dp=this.closest('.playground-preview').querySelector('#dpMethods'); libAlert('value: '+dp.value)">getValue()</button>
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#dpMethods').value='2026-06-15'">set 15-06-2026</button>
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#dpMethods').value=''">clear</button>
</div></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>name</dt>
                    <dd>Veldnaam voor formulier</dd>
                    <dt>value</dt>
                    <dd>Datumwaarde (YYYY-MM-DD formaat)</dd>
                    <dt>min</dt>
                    <dd>Minimum datum</dd>
                    <dt>max</dt>
                    <dd>Maximum datum</dd>
                    <dt>format</dt>
                    <dd>Weergaveformaat: <code>dd-mm-yyyy</code> (default), <code>mm-dd-yyyy</code>, <code>yyyy-mm-dd</code></dd>
                    <dt>locale</dt>
                    <dd>Taal: <code>nl</code> (default) of <code>en</code></dd>
                    <dt>placeholder</dt>
                    <dd>Placeholder tekst voor invoerveld (default: <code>"dd-mm-yyyy"</code>)</dd>
                    <dt>small</dt>
                    <dd>Kleine variant voor filters (default: <code>false</code>)</dd>
                    <dt>disabled</dt>
                    <dd>Schakel component uit (default: <code>false</code>)</dd>
                    <dt>readonly</dt>
                    <dd>Alleen lezen modus (default: <code>false</code>)</dd>
                    <dt>required</dt>
                    <dd>Verplicht veld (default: <code>false</code>)</dd>
                </dl>
                <h4>Methodes</h4>
                <dl>
                    <dt>open()</dt>
                    <dd>Open de kalender popup</dd>
                    <dt>close()</dt>
                    <dd>Sluit de kalender popup</dd>
                    <dt>toggle()</dt>
                    <dd>Schakel open/dicht</dd>
                </dl>
                <h4>Properties</h4>
                <dl>
                    <dt>value</dt>
                    <dd>Datumwaarde (YYYY-MM-DD)</dd>
                </dl>
                <h4>Events</h4>
                <dl>
                    <dt>change</dt>
                    <dd>Bij wijziging van datum. Detail: <code>{value}</code></dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="lib-dialog">
        <div class="component-header">
            <h2>lib-dialog</h2>
            <span class="tag lib">library</span>
            <p class="component-description">Modaal dialoogvenster met grootte, sluitknop en footer</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><div class="demo-row">
    <button class="btn btn-primary" onclick="document.getElementById('demoDialog1').open()">
        <span class="lnr lnr-frame-expand"></span> Kleine dialoog
    </button>
    <button class="btn btn-primary" onclick="document.getElementById('demoDialog2').open()">
        <span class="lnr lnr-frame-expand"></span> Medium dialoog
    </button>
    <button class="btn btn-primary" onclick="document.getElementById('demoDialog3').open()">
        <span class="lnr lnr-frame-expand"></span> Grote dialoog
    </button>
    <button class="btn btn-secondary" onclick="document.getElementById('demoDialog4').open()">
        <span class="lnr lnr-frame-expand"></span> Auto (schaalt mee)
    </button>
    <button class="btn btn-primary" onclick="document.getElementById('demoDialog5').open()">
        <span class="lnr lnr-frame-expand"></span> Maximaliseerbaar
    </button>
</div>
<lib-dialog id="demoDialog1" heading="Kleine dialoog" size="small">
    <p>Dit is een klein dialoogvenster.</p>
    <div slot="footer">
        <button class="btn btn-cancel" onclick="this.closest('lib-dialog').close()">Annuleren</button>
        <button class="btn btn-primary" onclick="this.closest('lib-dialog').close()">OK</button>
    </div>
</lib-dialog>
<lib-dialog id="demoDialog2" heading="Medium dialoog" size="medium">
    <p>Dit is een medium dialoogvenster met meer ruimte voor inhoud.</p>
    <div slot="footer">
        <button class="btn btn-cancel" onclick="this.closest('lib-dialog').close()">Annuleren</button>
        <button class="btn btn-primary" onclick="this.closest('lib-dialog').close()">Opslaan</button>
    </div>
</lib-dialog>
<lib-dialog id="demoDialog3" heading="Grote dialoog" size="large">
    <p>Dit is een groot dialoogvenster voor complexe formulieren.</p>
    <div slot="footer">
        <button class="btn btn-cancel" onclick="this.closest('lib-dialog').close()">Annuleren</button>
        <button class="btn btn-primary" onclick="this.closest('lib-dialog').close()">Publiceren</button>
    </div>
</lib-dialog>
<lib-dialog id="demoDialog4" heading="Auto-schaling" size="auto">
    <p>Dit dialoog schaalt automatisch mee met de inhoud.</p>
    <p>Korte tekst? Kleine dialoog. Lange tekst? Grotere dialoog.</p>
    <div slot="footer">
        <button class="btn btn-cancel" onclick="this.closest('lib-dialog').close()">Sluiten</button>
        <button class="btn btn-primary" onclick="this.closest('lib-dialog').close()">OK</button>
    </div>
</lib-dialog>
<lib-dialog id="demoDialog5" heading="Maximaliseerbaar" size="large" maximizable>
    <p>Dit dialoog kan gemaximaliseerd worden via de knop in de header.</p>
    <p>Klik op het maximaliseer-icoon om het venster te vergroten naar bijna volledig scherm.</p>
    <div slot="footer">
        <button class="btn btn-cancel" onclick="this.closest('lib-dialog').close()">Sluiten</button>
        <button class="btn btn-primary" onclick="this.closest('lib-dialog').close()">OK</button>
    </div>
</lib-dialog></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>id</dt>
                    <dd>Element ID voor JavaScript referentie</dd>
                    <dt>heading</dt>
                    <dd>Titel in de header (gebruik <code>heading</code> i.p.v. <code>title</code> om tooltip te vermijden)</dd>
                    <dt>type</dt>
                    <dd>Type: <code>info</code>, <code>warning</code>, <code>danger</code>, <code>success</code> (default: geen)</dd>
                    <dt>size</dt>
                    <dd>Grootte: <code>small</code>, <code>medium</code>, <code>large</code>, <code>auto</code>, <code>fullscreen</code> (default: <code>"medium"</code>)</dd>
                    <dt>closable</dt>
                    <dd>Toon sluitknop. Stel in op <code>"false"</code> om te verbergen (default: <code>true</code>)</dd>
                    <dt>no-maximize</dt>
                    <dd>Verberg de maximaliseerknop. Standaard is de knop zichtbaar (default: <code>false</code>)</dd>
                </dl>
                <h4>Slots</h4>
                <dl>
                    <dt>(default)</dt>
                    <dd>Inhoud van het dialoog</dd>
                    <dt>footer</dt>
                    <dd>Footer met knoppen</dd>
                </dl>
                <h4>Methodes</h4>
                <dl>
                    <dt>open()</dt>
                    <dd>Open het dialoog. Retourneert <code>Promise</code></dd>
                    <dt>close(confirmed)</dt>
                    <dd>Sluit het dialoog. <code>confirmed</code> = true/false</dd>
                    <dt>maximize()</dt>
                    <dd>Maximaliseer het dialoog (niet beschikbaar met <code>no-maximize</code>)</dd>
                    <dt>restore()</dt>
                    <dd>Herstel het dialoog naar originele grootte</dd>
                </dl>
                <h4>Statische methodes</h4>
                <dl>
                    <dt>LibDialog.alert(message, options)</dt>
                    <dd>Toon melding. Retourneert <code>Promise&lt;void&gt;</code></dd>
                    <dt>LibDialog.confirm(message, options)</dt>
                    <dd>Bevestigingsdialoog. Retourneert <code>Promise&lt;boolean&gt;</code></dd>
                    <dt>LibDialog.prompt(message, options)</dt>
                    <dd>Invoerdialoog. Retourneert <code>Promise&lt;string|null&gt;</code></dd>
                </dl>
                <h4>Properties</h4>
                <dl>
                    <dt>isOpen</dt>
                    <dd>Of het dialoog open is (boolean)</dd>
                    <dt>isClosable</dt>
                    <dd>Of het dialoog sluitbaar is (boolean)</dd>
                    <dt>isMaximized</dt>
                    <dd>Of het dialoog gemaximaliseerd is (boolean)</dd>
                </dl>
                <h4>Events</h4>
                <dl>
                    <dt>dialog-open</dt>
                    <dd>Bij openen</dd>
                    <dt>dialog-close</dt>
                    <dd>Bij sluiten. Detail: <code>{confirmed}</code></dd>
                </dl>
            </div>
        </div>
    </section>

    <!-- lib-fileuploader -->
    <section class="component-section" id="lib-fileuploader">
        <div class="component-header">
            <h2>lib-fileuploader</h2>
            <span class="tag lib">library</span>
            <p class="component-description">Bestandsupload component (vervangt FineUploader jQuery plugin)</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><p><strong>Standaard upload (documenten)</strong></p>
<input type="hidden" id="demo-upload-field" value="">
<lib-fileuploader
    field="demo-upload-field"
    path="uploads/"
    extensions="pdf,doc,docx,rtf,jpg,jpeg"
    max-size="10485760"
    button-text="Plaats bestand"
    show-link="false">
</lib-fileuploader>
<div class="demo-row" style="margin-top: 8px; font-size: var(--font-size-xs); color: var(--text-secondary);">
    Hidden field waarde: <code id="demo-upload-value">(leeg)</code>
</div>
<script>
    (function() {
        var field = document.getElementById('demo-upload-field');
        var display = document.getElementById('demo-upload-value');
        if (field && display) {
            new MutationObserver(function() {
                display.textContent = field.value || '(leeg)';
            }).observe(field, { attributes: true, attributeFilter: ['value'] });
            setInterval(function() {
                display.textContent = field.value || '(leeg)';
            }, 500);
        }
    })();
</script>

<p style="margin-top: 20px"><strong>Alleen afbeeldingen (100 MB, aangepaste tekst)</strong></p>
<input type="hidden" id="demo-upload-images" value="">
<lib-fileuploader
    field="demo-upload-images"
    path="uploads/"
    extensions="jpg,jpeg,png,gif"
    max-size="104857600"
    button-text="Selecteer afbeelding"
    type-error="Alleen afbeeldingen zijn toegestaan (JPG, PNG, GIF)"
    show-link="false">
</lib-fileuploader>

<p style="margin-top: 20px"><strong>Reset</strong></p>
<div class="demo-row" style="gap: 6px;">
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('lib-fileuploader').reset()">reset()</button>
</div></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>field</dt>
                    <dd>ID van hidden input voor bestandsnaam (vereist)</dd>
                    <dt>endpoint</dt>
                    <dd>Upload handler URL (default: <code>upload_handler.php</code>)</dd>
                    <dt>path</dt>
                    <dd>Basis upload map</dd>
                    <dt>path-extra</dt>
                    <dd>Dynamische prefix voor bestandsnaam</dd>
                    <dt>extensions</dt>
                    <dd>Komma-gescheiden extensies (default: <code>pdf,doc,docx,rtf,jpg,jpeg</code>)</dd>
                    <dt>max-size</dt>
                    <dd>Max bestandsgrootte in bytes (default: 10485760 = 10 MB)</dd>
                    <dt>button-text</dt>
                    <dd>Knoptekst (default: <code>Plaats bestand</code>)</dd>
                    <dt>type-error</dt>
                    <dd>Aangepaste foutmelding bij verkeerd type</dd>
                    <dt>multiple</dt>
                    <dd>Sta meerdere bestanden toe</dd>
                    <dt>show-link</dt>
                    <dd>Toon "Bekijk bestand" link na upload (default: true)</dd>
                    <dt>link-base</dt>
                    <dd>Basis URL voor bestandslink</dd>
                </dl>
                <h4>Methodes</h4>
                <dl>
                    <dt>reset()</dt>
                    <dd>Reset naar beginstatus</dd>
                </dl>
                <h4>Events</h4>
                <dl>
                    <dt>upload-complete</dt>
                    <dd>Na succesvolle upload. <code>detail: { filename, originalName, path }</code></dd>
                    <dt>upload-error</dt>
                    <dd>Bij uploadfout. <code>detail: { error, fileName }</code></dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="lib-gauge">
        <div class="component-header">
            <h2>lib-gauge</h2>
            <span class="tag lib">library</span>
            <p class="component-description">Ratio/percentage gauge bar voor het vergelijken van twee waarden</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><div class="demo-row">
    <span class="demo-label">Auto kleur (bestandsgrootte):</span>
</div>
<div style="display:flex;gap:24px;flex-wrap:wrap;margin-bottom:16px;">
    <lib-gauge value="20000" max="50000" format="size" label="WebP" min-width="150"></lib-gauge>
    <lib-gauge value="42000" max="50000" format="size" label="WebP" min-width="150"></lib-gauge>
    <lib-gauge value="60000" max="50000" format="size" label="WebP" min-width="150"></lib-gauge>
</div>

<div class="demo-row">
    <span class="demo-label">Vaste kleuren:</span>
</div>
<div style="display:flex;gap:24px;flex-wrap:wrap;margin-bottom:16px;">
    <lib-gauge value="30" max="100" type="info" format="raw" label="30/100" min-width="120"></lib-gauge>
    <lib-gauge value="25" max="100" type="success" format="raw" label="25/100" min-width="120"></lib-gauge>
    <lib-gauge value="70" max="100" type="warning" format="raw" label="70/100" min-width="120"></lib-gauge>
    <lib-gauge value="95" max="100" type="error" format="raw" label="95/100" min-width="120"></lib-gauge>
</div>

<div class="demo-row">
    <span class="demo-label">Zonder balk:</span>
</div>
<lib-gauge value="15000" max="50000" format="size" label="WebP" show-bar="false" min-width="150"></lib-gauge>

<div class="demo-row" style="margin-top:16px;">
    <span class="demo-label">Breed (tabel stijl):</span>
</div>
<div style="max-width:300px;">
    <lib-gauge value="12500" max="75000" format="size" min-width="200"></lib-gauge>
</div>

<div class="demo-row" style="margin-top:16px;">
    <span class="demo-label">Groot (voortgang):</span>
</div>
<div style="max-width:500px;display:flex;flex-direction:column;gap:12px;">
    <lib-gauge value="37" max="403" size="lg" type="info" format="raw" label="37 / 403 endpoints" min-width="300"></lib-gauge>
    <lib-gauge value="350" max="403" size="lg" type="success" format="raw" label="350 / 403 geconverteerd" min-width="300"></lib-gauge>
    <lib-gauge value="8" max="403" size="lg" type="error" format="raw" label="8 / 403 fouten" min-width="300"></lib-gauge>
</div>

<p><strong>Methodes</strong></p>
<div class="demo-row">
    <span class="demo-label">update() demo:</span>
    <lib-gauge id="gaugeMethods" value="30" max="100" type="info" format="raw" label="Voortgang" min-width="200"></lib-gauge>
</div>
<div class="demo-row" style="gap: 6px; flex-wrap: wrap;">
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#gaugeMethods').update(25, 100)">update(25, 100)</button>
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#gaugeMethods').update(50, 100)">update(50, 100)</button>
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#gaugeMethods').update(75, 100)">update(75, 100)</button>
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#gaugeMethods').update(100, 100)">update(100, 100)</button>
</div></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>value</dt>
                    <dd>Huidige waarde (teller)</dd>
                    <dt>max</dt>
                    <dd>Referentiewaarde (noemer)</dd>
                    <dt>type</dt>
                    <dd>auto | info | success | warning | error (default: auto)</dd>
                    <dt>size</dt>
                    <dd>sm | lg (default: sm) — sm: compact 6px balk, lg: grote 20px balk</dd>
                    <dt>format</dt>
                    <dd>percent | size | raw (default: percent)</dd>
                    <dt>label</dt>
                    <dd>Label links naast de waarde</dd>
                    <dt>show-bar</dt>
                    <dd>Toon de balk (default: true)</dd>
                    <dt>min-width</dt>
                    <dd>Minimale breedte in px (default: 100)</dd>
                </dl>
                <h4>Properties</h4>
                <dl>
                    <dt>ratio</dt>
                    <dd>(readonly) value / max</dd>
                    <dt>percentage</dt>
                    <dd>(readonly) besparingspercentage</dd>
                </dl>
                <h4>Methoden</h4>
                <dl>
                    <dt>update(value, max)</dt>
                    <dd>Waarden programmatisch bijwerken</dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="lib-histogram">
        <div class="component-header">
            <h2>lib-histogram</h2>
            <span class="tag lib">library</span>
            <p class="component-description">Histogram voor frequentieverdelingen</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><div class="demo-row">
    <span class="demo-label">Frequentiemodus:</span>
</div>
<lib-histogram
    data="3,4,5,4,3,4,5,5,4,3,2,4,5,4,3"
    min-value="1"
    max-value="5"
    show-stats="right"
    labels="Slecht,Matig,Voldoende,Goed,Uitstekend"
    style="height: 180px;">
</lib-histogram>

<div class="demo-row" style="margin-top: 20px;">
    <span class="demo-label">Waardenmodus:</span>
</div>
<lib-histogram
    mode="values"
    data="120,150,130,140,125,135,145,128,132,138"
    show-stats="bottom"
    unit="ms"
    title="Responstijden"
    style="height: 180px;">
</lib-histogram>

<p><strong>Methodes</strong></p>
<lib-histogram id="histMethods"
    data="3,4,5,4,3,4,5,5,4,3"
    min-value="1"
    max-value="5"
    show-stats="right"
    labels="Slecht,Matig,Voldoende,Goed,Uitstekend"
    style="height: 180px;">
</lib-histogram>
<div class="demo-row" style="gap: 6px; flex-wrap: wrap; margin-top: 10px;">
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#histMethods').setData([1,2,3,4,5,5,4,3,2,1,3,4,5,3,4], {minValue:1, maxValue:5, labels:'Slecht,Matig,Voldoende,Goed,Uitstekend'})">setData(nieuw)</button>
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#histMethods').setData([2,3,3,4,4,4,5,5,5,5], {minValue:1, maxValue:5})">setData(hoog)</button>
    <button class="btn btn-cancel" onclick="var h=this.closest('.playground-preview').querySelector('#histMethods'); libAlert('data: '+JSON.stringify(h.getData()))">getData()</button>
    <button class="btn btn-cancel" onclick="var h=this.closest('.playground-preview').querySelector('#histMethods'); libAlert(JSON.stringify(h.getStats(), null, 2))">getStats()</button>
</div></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>mode</dt>
                    <dd>frequency | values (default: frequency)</dd>
                    <dt>data</dt>
                    <dd>Komma-gescheiden waarden (verplicht)</dd>
                    <dt>min-value</dt>
                    <dd>Minimum waarde op x-as (default: 1)</dd>
                    <dt>max-value</dt>
                    <dd>Maximum waarde op x-as (default: 5)</dd>
                    <dt>height</dt>
                    <dd>Hoogte in pixels (default: 140)</dd>
                    <dt>show-stats</dt>
                    <dd>right | bottom | none (default: right)</dd>
                    <dt>labels</dt>
                    <dd>Komma-gescheiden labels per staaf</dd>
                    <dt>colors</dt>
                    <dd>Komma-gescheiden kleuren per staaf (<code>success</code>, <code>error</code>, <code>warning</code> of hex)</dd>
                    <dt>bar-color</dt>
                    <dd>Standaard staafkleur (default: var(--color-primary))</dd>
                    <dt>bar-hover-color</dt>
                    <dd>Hover kleur (default: var(--color-primary-dark))</dd>
                    <dt>title</dt>
                    <dd>Grafiek titel</dd>
                    <dt>unit</dt>
                    <dd>Eenheid suffix (bijv. "ms", "%")</dd>
                    <dt>nvt-value</dt>
                    <dd>Waarde voor "niet van toepassing" (default: 0)</dd>
                    <dt>show-average-line</dt>
                    <dd>Toon gemiddelde lijn (default: true)</dd>
                    <dt>show-labels</dt>
                    <dd>Toon labels onder staven (default: true)</dd>
                </dl>
                <h4>Methodes</h4>
                <dl>
                    <dt>setData(values, options)</dt>
                    <dd>Stel data in. <code>values</code>: array van getallen. <code>options</code>: <code>{mode, minValue, maxValue, labels, colors, showStats, title, unit}</code></dd>
                    <dt>getData()</dt>
                    <dd>Geeft array van huidige waarden</dd>
                    <dt>getStats()</dt>
                    <dd>Geeft statistieken (excl. nvt-waarden)</dd>
                    <dt>calculateStats(values)</dt>
                    <dd>Bereken: <code>{count, mean, deviation, modus, modusCount, min, max}</code></dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="lib-label">
        <div class="component-header">
            <h2>lib-label</h2>
            <span class="tag lib">library</span>
            <p class="component-description">Pure CSS label/badge voor status en categorieën.</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><div class="demo-row">
    <span class="demo-label">Types:</span>
    <lib-label type="information">Informatie</lib-label>
    <lib-label type="success">Succes</lib-label>
    <lib-label type="warning">Waarschuwing</lib-label>
    <lib-label type="error">Fout</lib-label>
</div>
<div class="demo-row">
    <span class="demo-label">Formaten:</span>
    <lib-label type="information" size="small">Klein</lib-label>
    <lib-label type="information" size="normal">Normaal</lib-label>
    <lib-label type="information" size="large">Groot</lib-label>
</div>
<div class="demo-row">
    <span class="demo-label">Met icoon:</span>
    <lib-label type="warning"><span class="lnr lnr-lock"></span> Alleen lezen</lib-label>
    <lib-label type="success"><span class="lnr lnr-checkmark-circle"></span> Actief</lib-label>
    <lib-label type="error"><span class="lnr lnr-cross-circle"></span> Geblokkeerd</lib-label>
</div></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>type</dt>
                    <dd><code>information</code>, <code>success</code>, <code>warning</code>, <code>error</code> (default: geen, neutrale stijl)</dd>
                    <dt>size</dt>
                    <dd><code>small</code> (10px, 2px 8px), <code>normal</code> (12px, 4px 12px), <code>large</code> (14px, 6px 16px, vet) (default: <code>"normal"</code>)</dd>
                </dl>
                <h4>CSS variabelen</h4>
                <dl>
                    <dt>--label-info-bg / --label-info-text</dt>
                    <dd>Overschrijf informatie kleuren</dd>
                    <dt>--label-success-bg / --label-success-text</dt>
                    <dd>Overschrijf succes kleuren</dd>
                    <dt>--label-warning-bg / --label-warning-text</dt>
                    <dd>Overschrijf waarschuwing kleuren</dd>
                    <dt>--label-error-bg / --label-error-text</dt>
                    <dd>Overschrijf fout kleuren</dd>
                </dl>
                <h4>Notities</h4>
                <dl>
                    <dt>Pure CSS</dt>
                    <dd>Geen JavaScript nodig. Verbergt zichzelf als leeg (<code>:empty { display: none }</code>)</dd>
                    <dt>Iconen</dt>
                    <dd>Voeg <code>&lt;span class="lnr lnr-*"&gt;</code> toe als child, kleur wordt automatisch overgenomen</dd>
                    <dt>Achtergrond</dt>
                    <dd>Gradient (135deg), kleur aanpasbaar via CSS variabelen</dd>
                    <dt>Alternatief</dt>
                    <dd>Type kan ook als class: <code>&lt;lib-label class="success"&gt;</code></dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="lib-loader">
        <div class="component-header">
            <h2>lib-loader</h2>
            <span class="tag lib">library</span>
            <p class="component-description">Laadspinner met vertraagde weergave</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><div class="demo-row">
    <span class="demo-label">Standaard:</span>
    <button class="btn btn-secondary" onclick="showLoader('loaderDefault')">Toon loader</button>
    <lib-loader id="loaderDefault" text="Laden..."></lib-loader>
</div>
<div class="demo-row">
    <span class="demo-label">Klein:</span>
    <button class="btn btn-secondary" onclick="showLoader('loaderSmall')">Toon loader</button>
    <lib-loader id="loaderSmall" size="small"></lib-loader>
</div>

<p><strong>Methodes</strong></p>
<div class="demo-row">
    <span class="demo-label">Methode demo:</span>
    <lib-loader id="loaderMethods" text="Bezig..."></lib-loader>
</div>
<div class="demo-row" style="gap: 6px; flex-wrap: wrap;">
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#loaderMethods').show()">show()</button>
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#loaderMethods').showImmediately()">showImmediately()</button>
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#loaderMethods').hide()">hide()</button>
</div></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>active</dt>
                    <dd>Toon de loader (aanwezigheid = actief)</dd>
                    <dt>delay</dt>
                    <dd>Wachttijd in ms voordat loader verschijnt (default: 500)</dd>
                    <dt>size</dt>
                    <dd>small | medium | large (default: medium)</dd>
                    <dt>text</dt>
                    <dd>Optionele tekst onder de spinner</dd>
                    <dt>overlay</dt>
                    <dd>Toon als volledige overlay</dd>
                </dl>
                <h4>Methoden</h4>
                <dl>
                    <dt>show()</dt>
                    <dd>Start tonen (met delay)</dd>
                    <dt>hide()</dt>
                    <dd>Verberg direct</dd>
                    <dt>showImmediately()</dt>
                    <dd>Toon zonder delay</dd>
                </dl>
                <h4>Events</h4>
                <dl>
                    <dt>show</dt>
                    <dd>Bij tonen van loader</dd>
                    <dt>hide</dt>
                    <dd>Bij verbergen van loader</dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="lib-menu">
        <div class="component-header">
            <h2>lib-menu</h2>
            <span class="tag lib">library</span>
            <p class="component-description">Contextmenu met iconen en acties</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><button class="btn btn-primary" id="menuTrigger">
    <span class="lnr lnr-menu"></span> Open menu
</button>
<lib-menu trigger="#menuTrigger">
    <lib-menu-item icon="lnr-pencil" action="edit">Bewerken</lib-menu-item>
    <lib-menu-item icon="lnr-copy" action="copy">Kopiëren</lib-menu-item>
    <lib-menu-item icon="lnr-download" action="download" disabled>Downloaden (disabled)</lib-menu-item>
    <lib-menu-item separator></lib-menu-item>
    <lib-menu-item icon="lnr-trash" action="delete" danger>Verwijderen</lib-menu-item>
</lib-menu></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>lib-menu attributen</h4>
                <dl>
                    <dt>trigger</dt>
                    <dd>CSS selector voor trigger element</dd>
                </dl>
                <h4>lib-menu-item attributen</h4>
                <dl>
                    <dt>icon</dt>
                    <dd>Icon class (bijv. "lnr-pencil")</dd>
                    <dt>action</dt>
                    <dd>Action identifier voor event</dd>
                    <dt>danger</dt>
                    <dd>Rode styling voor verwijder-acties</dd>
                    <dt>disabled</dt>
                    <dd>Item uitschakelen</dd>
                    <dt>separator</dt>
                    <dd>Scheidingslijn maken</dd>
                </dl>
                <h4>Methoden</h4>
                <dl>
                    <dt>show(x, y)</dt>
                    <dd>Open menu op positie</dd>
                    <dt>hide()</dt>
                    <dd>Sluit menu</dd>
                    <dt>toggle()</dt>
                    <dd>Open/sluit menu</dd>
                </dl>
                <h4>Events</h4>
                <dl>
                    <dt>menu-select</dt>
                    <dd>Bij selecteren item (detail: action, item)</dd>
                    <dt>menu-open</dt>
                    <dd>Bij openen menu</dd>
                    <dt>menu-close</dt>
                    <dd>Bij sluiten menu</dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="lib-message">
        <div class="component-header">
            <h2>lib-message</h2>
            <span class="tag lib">library</span>
            <p class="component-description">Inline berichten voor status, waarschuwingen en fouten</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><lib-message type="info">Dit is een informatief bericht.</lib-message>
<lib-message type="success">Actie succesvol uitgevoerd!</lib-message>
<lib-message type="warning" closable>Let op: deze actie kan niet ongedaan worden gemaakt.</lib-message>
<lib-message type="error" closable>Er is een fout opgetreden bij het opslaan.</lib-message>

<p><strong>Methodes</strong></p>
<lib-message id="msgDemo" type="info" closable>Dit bericht kan gesloten en weer getoond worden.</lib-message>
<div class="demo-row" style="gap: 6px; flex-wrap: wrap; margin-top: 10px;">
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#msgDemo').show()">show()</button>
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#msgDemo').close()">close()</button>
</div>

<p><strong>JavaScript API</strong></p>
<div class="demo-row" style="gap: 6px; flex-wrap: wrap;">
    <button class="btn btn-secondary" onclick="if(typeof libMessage!=='undefined') libMessage.info('Info bericht via JavaScript API'); else libToast.info('libMessage niet beschikbaar')">libMessage.info()</button>
    <button class="btn btn-secondary" onclick="if(typeof libMessage!=='undefined') libMessage.success('Succes bericht via API'); else libToast.success('libMessage niet beschikbaar')">libMessage.success()</button>
    <button class="btn btn-secondary" onclick="if(typeof libMessage!=='undefined') libMessage.error('Fout bericht via API'); else libToast.error('libMessage niet beschikbaar')">libMessage.error()</button>
</div></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>type</dt>
                    <dd>Type: <code>info</code>, <code>success</code>, <code>warning</code>, <code>error</code> (default: <code>"info"</code>)</dd>
                    <dt>closable</dt>
                    <dd>Toon sluitknop (default: <code>false</code>)</dd>
                    <dt>auto-dismiss</dt>
                    <dd>Auto-sluiten na X ms, bijv. <code>"5000"</code> (default: geen)</dd>
                    <dt>icon</dt>
                    <dd>Toon icoon (default: <code>true</code>)</dd>
                    <dt>compact</dt>
                    <dd>Compacte styling (default: <code>false</code>)</dd>
                    <dt>details</dt>
                    <dd>Technische details, inklapbaar (default: geen)</dd>
                </dl>
                <h4>Methodes</h4>
                <dl>
                    <dt>close()</dt>
                    <dd>Sluit het bericht</dd>
                    <dt>show()</dt>
                    <dd>Toon verborgen bericht</dd>
                </dl>
                <h4>Events</h4>
                <dl>
                    <dt>lib-message-close</dt>
                    <dd>Bij sluiten van bericht</dd>
                </dl>
                <h4>JavaScript API</h4>
                <dl>
                    <dt>libMessage.info(msg)</dt>
                    <dd>Creëer info bericht</dd>
                    <dt>libMessage.success(msg)</dt>
                    <dd>Creëer succes bericht</dd>
                    <dt>libMessage.error(msg)</dt>
                    <dd>Creëer fout bericht</dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="lib-search-input">
        <div class="component-header">
            <h2>lib-search-input</h2>
            <span class="tag lib">library</span>
            <p class="component-description">Zoekveld met clear-knop en Enter/Escape ondersteuning</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><div class="demo-row">
    <span class="demo-label">Standaard:</span>
    <lib-search-input placeholder="Zoeken..." style="width: 200px;"></lib-search-input>
</div>
<div class="demo-row">
    <span class="demo-label">Met waarde:</span>
    <lib-search-input value="Test waarde" placeholder="Zoeken..." style="width: 200px;"></lib-search-input>
</div>
<div class="demo-row">
    <span class="demo-label">Icoon rechts:</span>
    <lib-search-input icon="right" placeholder="Zoeken..." style="width: 200px;"></lib-search-input>
</div>
<div class="demo-row">
    <span class="demo-label">Geen icoon:</span>
    <lib-search-input icon="none" placeholder="Zoeken..." style="width: 200px;"></lib-search-input>
</div>
<div class="demo-row">
    <span class="demo-label">Readonly:</span>
    <lib-search-input value="Readonly waarde" readonly placeholder="Zoeken..." style="width: 200px;"></lib-search-input>
</div>
<div class="demo-row">
    <span class="demo-label">Disabled:</span>
    <lib-search-input disabled placeholder="Zoeken..." style="width: 200px;"></lib-search-input>
</div>

<p><strong>Methodes</strong></p>
<div class="demo-row">
    <span class="demo-label">Methode demo:</span>
    <lib-search-input id="searchMethods" value="Test waarde" placeholder="Zoeken..." style="width: 200px;"></lib-search-input>
</div>
<div class="demo-row" style="gap: 6px; flex-wrap: wrap;">
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#searchMethods').focus()">focus()</button>
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#searchMethods').clear()">clear()</button>
    <button class="btn btn-cancel" onclick="var s=this.closest('.playground-preview').querySelector('#searchMethods'); libAlert('value: '+s.value)">getValue()</button>
</div></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>name</dt>
                    <dd>Veldnaam voor form submission</dd>
                    <dt>placeholder</dt>
                    <dd>Placeholder tekst (default: <code>"Zoeken..."</code>)</dd>
                    <dt>value</dt>
                    <dd>Huidige waarde</dd>
                    <dt>disabled</dt>
                    <dd>Schakel interactie uit (default: <code>false</code>)</dd>
                    <dt>icon</dt>
                    <dd><code>"left"</code>, <code>"right"</code>, of <code>"none"</code> (default: <code>"left"</code>)</dd>
                </dl>
                <h4>Methods</h4>
                <dl>
                    <dt>focus()</dt>
                    <dd>Focus het invoerveld</dd>
                    <dt>clear()</dt>
                    <dd>Wis de waarde</dd>
                </dl>
                <h4>Events</h4>
                <dl>
                    <dt>input</dt>
                    <dd>Bij elke toetsaanslag, detail: { value }</dd>
                    <dt>change</dt>
                    <dd>Bij blur of Enter, detail: { value }</dd>
                    <dt>search</dt>
                    <dd>Bij Enter toets, detail: { value }</dd>
                    <dt>clear</dt>
                    <dd>Bij klik op wis-knop, detail: { previousValue }</dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="lib-switch">
        <div class="component-header">
            <h2>lib-switch</h2>
            <span class="tag lib">library</span>
            <p class="component-description">Schakelaar voor aan/uit waarden</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><div class="demo-row">
    <span class="demo-label">Standaard:</span>
    <lib-switch name="switch1"></lib-switch>
</div>
<div class="demo-row">
    <span class="demo-label">Ingeschakeld:</span>
    <lib-switch name="switch2" checked></lib-switch>
</div>
<div class="demo-row">
    <span class="demo-label">Readonly:</span>
    <lib-switch name="switch3" readonly></lib-switch>
    <lib-switch name="switch3b" readonly checked></lib-switch>
</div>
<div class="demo-row">
    <span class="demo-label">Disabled:</span>
    <lib-switch name="switch4" disabled></lib-switch>
    <lib-switch name="switch4b" disabled checked></lib-switch>
</div>

<p><strong>Methodes</strong></p>
<div class="demo-row">
    <span class="demo-label">Methode demo:</span>
    <lib-switch id="switchMethods" name="switchDemo"></lib-switch>
</div>
<div class="demo-row" style="gap: 6px; flex-wrap: wrap;">
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#switchMethods').toggle()">toggle()</button>
    <button class="btn btn-secondary" onclick="var s=this.closest('.playground-preview').querySelector('#switchMethods'); s.setSaving(true); setTimeout(function(){s.setSaving(false)},2000)">setSaving(2s)</button>
    <button class="btn btn-cancel" onclick="var s=this.closest('.playground-preview').querySelector('#switchMethods'); libAlert('checked: '+s.checked+'\nfield: '+s.field)">getState()</button>
</div></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>name</dt>
                    <dd>Veldnaam voor formulier</dd>
                    <dt>checked</dt>
                    <dd>Ingeschakeld (boolean attribuut)</dd>
                    <dt>disabled</dt>
                    <dd>Schakel component uit</dd>
                    <dt>readonly</dt>
                    <dd>Alleen lezen modus</dd>
                    <dt>value</dt>
                    <dd>Waarde bij checked (default: "J", unchecked: "N")</dd>
                    <dt>labels</dt>
                    <dd>Labels formaat "aan:uit" (default: "Ja:Nee")</dd>
                    <dt>data-field</dt>
                    <dd>Veldnaam voor inline editing (default: <code>name</code>)</dd>
                </dl>
                <h4>Methodes</h4>
                <dl>
                    <dt>toggle()</dt>
                    <dd>Schakel de status om</dd>
                    <dt>setSaving(saving)</dt>
                    <dd>Toon/verberg opslaan-indicator</dd>
                </dl>
                <h4>Properties</h4>
                <dl>
                    <dt>checked</dt>
                    <dd>Boolean voor huidige status</dd>
                    <dt>field</dt>
                    <dd>Veldnaam (uit <code>data-field</code> of <code>name</code>)</dd>
                    <dt>labels</dt>
                    <dd>Object: <code>{on, off}</code></dd>
                </dl>
                <h4>Events</h4>
                <dl>
                    <dt>change</dt>
                    <dd>Bij wijziging. Detail: <code>{checked, value, field}</code></dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="lib-table">
        <div class="component-header">
            <h2>lib-table</h2>
            <span class="tag lib">library</span>
            <p class="component-description">Tabel wrapper met filtering, sortering en export.</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><lib-table resizable reorderable>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Naam</th>
                <th>E-mail</th>
                <th data-type="text">Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>Jan Jansen</td>
                <td>jan@voorbeeld.nl</td>
                <td>Actief</td>
            </tr>
            <tr>
                <td>2</td>
                <td>Piet Pieters</td>
                <td>piet@voorbeeld.nl</td>
                <td>Inactief</td>
            </tr>
            <tr>
                <td>3</td>
                <td>Klaas Kansen</td>
                <td>klaas@voorbeeld.nl</td>
                <td>Actief</td>
            </tr>
        </tbody>
    </table>
</lib-table></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>resizable</dt>
                    <dd>Kolommen kunnen in breedte worden aangepast (default: <code>false</code>)</dd>
                    <dt>reorderable</dt>
                    <dd>Kolommen kunnen worden herschikt via drag & drop (default: <code>false</code>)</dd>
                    <dt>storage-key</dt>
                    <dd>Sleutel voor opslaan van kolomvoorkeuren in localStorage (default: geen)</dd>
                </dl>
                <h4>Kolom attributen (th)</h4>
                <dl>
                    <dt>data-type</dt>
                    <dd>Kolomtype: <code>text</code>, <code>number</code>, <code>date</code>, <code>currency</code></dd>
                    <dt>data-no-sort</dt>
                    <dd>Schakel sorteren uit voor deze kolom</dd>
                    <dt>data-no-filter</dt>
                    <dd>Schakel filteren uit voor deze kolom</dd>
                    <dt>data-filter="N"</dt>
                    <dd>Alternatief voor data-no-filter</dd>
                </dl>
                <h4>Functies</h4>
                <dl>
                    <dt>Filtering</dt>
                    <dd>Klik op kolomkop voor sorteer/filter opties</dd>
                    <dt>Export</dt>
                    <dd>Rechtsklik op tabel voor export naar Excel, CSV of Word</dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="lib-timepicker">
        <div class="component-header">
            <h2>lib-timepicker</h2>
            <span class="tag lib">library</span>
            <p class="component-description">Tijdkiezer met uur/minuut selectie</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><div class="demo-row">
    <span class="demo-label">Standaard:</span>
    <lib-timepicker name="tijd1"></lib-timepicker>
</div>
<div class="demo-row">
    <span class="demo-label">Met waarde:</span>
    <lib-timepicker name="tijd2" value="14:30"></lib-timepicker>
</div>
<div class="demo-row">
    <span class="demo-label">Met bereik:</span>
    <lib-timepicker name="tijd3" min="09:00" max="17:00" step="15"></lib-timepicker>
</div>
<div class="demo-row">
    <span class="demo-label">Readonly:</span>
    <lib-timepicker name="tijd4" value="09:00" readonly></lib-timepicker>
</div>
<div class="demo-row">
    <span class="demo-label">Disabled:</span>
    <lib-timepicker name="tijd5" value="09:00" disabled></lib-timepicker>
</div>
<div class="demo-row">
    <span class="demo-label">Verplicht (leeg):</span>
    <lib-timepicker name="tijd6" data-required="true" required></lib-timepicker>
</div>
<div class="demo-row">
    <span class="demo-label">Verplicht (met waarde):</span>
    <lib-timepicker name="tijd7" value="14:30" data-required="true" required></lib-timepicker>
</div>

<p><strong>Properties</strong></p>
<div class="demo-row">
    <span class="demo-label">Property demo:</span>
    <lib-timepicker id="tpMethods" name="tijdMethods" value="14:30"></lib-timepicker>
</div>
<div class="demo-row" style="gap: 6px; flex-wrap: wrap;">
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#tpMethods').value='09:00'">set 09:00</button>
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#tpMethods').value='17:45'">set 17:45</button>
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#tpMethods').value=''">clear</button>
    <button class="btn btn-cancel" onclick="var tp=this.closest('.playground-preview').querySelector('#tpMethods'); libAlert('value: '+tp.value)">getValue()</button>
</div></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>name</dt>
                    <dd>Veldnaam voor formulier</dd>
                    <dt>value</dt>
                    <dd>Tijdwaarde (HH:mm formaat)</dd>
                    <dt>min</dt>
                    <dd>Minimum tijd</dd>
                    <dt>max</dt>
                    <dd>Maximum tijd</dd>
                    <dt>step</dt>
                    <dd>Stap in minuten (default: 1)</dd>
                    <dt>disabled</dt>
                    <dd>Schakel component uit (default: <code>false</code>)</dd>
                    <dt>readonly</dt>
                    <dd>Alleen lezen modus (default: <code>false</code>)</dd>
                    <dt>required</dt>
                    <dd>Verplicht veld (default: <code>false</code>)</dd>
                    <dt>small</dt>
                    <dd>Compacte variant (default: <code>false</code>)</dd>
                </dl>
                <h4>Events</h4>
                <dl>
                    <dt>change</dt>
                    <dd>Wordt getriggerd bij wijziging van tijd</dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="lib-tip">
        <div class="component-header">
            <h2>lib-tip</h2>
            <span class="tag lib">library</span>
            <p class="component-description">Tips en tours met element highlighting en navigatie</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <!-- Live demo -->
                <div class="component-demo">
                    <div class="demo-row">
                        <span class="demo-label">Enkele tip:</span>
                        <button class="btn btn-secondary" id="btnShowTip">Toon tip</button>
                    </div>
                    <div class="demo-row">
                        <span class="demo-label">Tour (3 stappen):</span>
                        <button class="btn btn-secondary" id="btnShowTour">Start tour</button>
                    </div>
                    <div class="demo-row">
                        <span class="demo-label">Sluiten/Reset:</span>
                        <button class="btn btn-secondary" id="btnCloseTip">Sluit huidige</button>
                        <button class="btn btn-secondary" id="btnResetTips">Reset skip list</button>
                    </div>
                </div>
                <!-- Code example -->
                <div class="code-block" style="white-space: pre;">// Enkele tip tonen
LibTip.show({
    id: 'my-tip-id',
    target: '.my-element',
    title: 'Tip titel',
    content: 'Uitleg over de functie.',
    position: 'bottom'
});

// Tour starten
LibTip.tour('my-tour-id', [
    { target: '.step1', title: 'Stap 1', content: '...', position: 'right' },
    { target: '.step2', title: 'Stap 2', content: '...', position: 'bottom' }
]);

// Sluiten en resetten
LibTip.close();
LibTip.dismiss('my-tip-id');
LibTip.reset();</div>
            </div>
            <div class="component-options">
                <h4>API - LibTip.show(options)</h4>
                <dl>
                    <dt>id</dt>
                    <dd>Unieke ID voor skip/dismiss tracking</dd>
                    <dt>target</dt>
                    <dd>CSS selector voor doelelement (verplicht)</dd>
                    <dt>title</dt>
                    <dd>Tip titel</dd>
                    <dt>content</dt>
                    <dd>HTML inhoud van de tip</dd>
                    <dt>position</dt>
                    <dd>top | bottom | left | right (default: bottom)</dd>
                </dl>
                <h4>API - LibTip.tour(id, steps)</h4>
                <dl>
                    <dt>id</dt>
                    <dd>Unieke tour ID voor skip tracking</dd>
                    <dt>steps</dt>
                    <dd>Array van stappen met target, title, content, position</dd>
                </dl>
                <h4>API - Overige methoden</h4>
                <dl>
                    <dt>LibTip.close()</dt>
                    <dd>Sluit huidige tip/tour</dd>
                    <dt>LibTip.dismiss(id)</dt>
                    <dd>Markeer tip/tour als permanent gesloten</dd>
                    <dt>LibTip.isSkipped(id)</dt>
                    <dd>Check of tip/tour is overgeslagen (async)</dd>
                    <dt>LibTip.reset(id?)</dt>
                    <dd>Reset skip status (optioneel specifieke ID)</dd>
                </dl>
                <h4>Keyboard shortcuts</h4>
                <dl>
                    <dt>Escape</dt>
                    <dd>Sluit tip/tour</dd>
                    <dt>→ / Enter</dt>
                    <dd>Volgende stap (tour)</dd>
                    <dt>←</dt>
                    <dd>Vorige stap (tour)</dd>
                </dl>
                <h4>Events</h4>
                <dl>
                    <dt>tip-close</dt>
                    <dd>Wanneer tip/tour wordt gesloten</dd>
                </dl>
                <h4>Kenmerken</h4>
                <ul style="padding-left: 16px; margin: 8px 0; font-size: var(--font-size-sm); color: var(--text-secondary);">
                    <li>Element highlighting met pulserende rand</li>
                    <li>Automatische viewport aanpassing</li>
                    <li>Skip list opslag per gebruiker (database)</li>
                    <li>Tour met stappen gauge en navigatie</li>
                    <li>Scroll target automatisch in view</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="component-section" id="lib-toaster">
        <div class="component-header">
            <h2>lib-toaster</h2>
            <span class="tag lib">library</span>
            <p class="component-description">Toast notificaties via libToast.info/success/warning/error()</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><div class="demo-row">
    <button class="btn btn-primary" onclick="libToast.info('Dit is een informatie toast')">Info toast</button>
    <button class="btn btn-primary" onclick="libToast.success('Succesvol opgeslagen!')">Succes toast</button>
    <button class="btn btn-primary" onclick="libToast.warning('Waarschuwing!')">Waarschuwing toast</button>
    <button class="btn btn-primary" onclick="libToast.error('Fout bij opslaan')">Fout toast</button>
</div>

<p><strong>Methodes</strong></p>
<div class="demo-row" style="gap: 6px; flex-wrap: wrap;">
    <button class="btn btn-secondary" onclick="libToast.info('Persistent toast', 0)">Persistent (0ms)</button>
    <button class="btn btn-secondary" onclick="libToast.configure({position:'bottom-right'}); libToast.info('Rechtsonder!')">Positie: bottom-right</button>
    <button class="btn btn-secondary" onclick="libToast.configure({position:'top-right'}); libToast.info('Rechtsboven (standaard)')">Positie: top-right</button>
    <button class="btn btn-cancel" onclick="libToast.clear()">clear()</button>
</div></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Posities</h4>
                <dl>
                    <dt>top-right</dt>
                    <dd>Rechtsboven (standaard)</dd>
                    <dt>top-left</dt>
                    <dd>Linksboven</dd>
                    <dt>top-center</dt>
                    <dd>Midden boven</dd>
                    <dt>bottom-right</dt>
                    <dd>Rechtsonder</dd>
                    <dt>bottom-left</dt>
                    <dd>Linksonder</dd>
                    <dt>bottom-center</dt>
                    <dd>Midden onder</dd>
                </dl>
                <h4>Methoden (libToast)</h4>
                <dl>
                    <dt>info(msg, duration?)</dt>
                    <dd>Toon info toast</dd>
                    <dt>success(msg, duration?)</dt>
                    <dd>Toon succes toast</dd>
                    <dt>warning(msg, duration?)</dt>
                    <dd>Toon waarschuwing toast</dd>
                    <dt>error(msg, duration?)</dt>
                    <dd>Toon fout toast</dd>
                    <dt>clear()</dt>
                    <dd>Verwijder alle toasts</dd>
                    <dt>configure(options)</dt>
                    <dd>Configureer positie en duur</dd>
                </dl>
                <h4>Opties</h4>
                <dl>
                    <dt>duration</dt>
                    <dd>Auto-dismiss in ms (default: 4000, 0 = blijft)</dd>
                </dl>
            </div>
        </div>
    </section>


    <!-- ================================================================ -->
    <!-- LIBRARY FUNCTIES -->
    <!-- ================================================================ -->

    <section class="component-section" id="libAlert">
        <div class="component-header">
            <h2>libAlert</h2>
            <span class="tag lib">library</span>
            <p class="component-description">Toon een melding aan de gebruiker. Retourneert een Promise die resolved wanneer de gebruiker de dialoog sluit.</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><p><strong>Type varianten</strong></p>
<div class="demo-row">
    <button class="btn btn-primary" onclick="libAlert('Bestand opgeslagen')">Info (standaard)</button>
    <button class="btn btn-primary" onclick="libAlert('Actie voltooid', {type:'success', title:'Gelukt'})">Success</button>
    <button class="btn btn-primary" onclick="libAlert('Let op: sessie verloopt over 5 minuten', {type:'warning', title:'Waarschuwing'})">Warning</button>
    <button class="btn btn-primary" onclick="libAlert('Fout bij opslaan van het formulier', {type:'danger', title:'Fout'})">Danger</button>
</div>

<p><strong>Aangepaste knoptekst</strong></p>
<div class="demo-row">
    <button class="btn btn-primary" onclick="libAlert('Export is gereed', {title:'Download', buttonText:'Sluiten', type:'success'})">Aangepaste knop</button>
</div>

<p><strong>HTML inhoud</strong></p>
<div class="demo-row">
    <button class="btn btn-primary" onclick="libAlert('&lt;p&gt;Gebruik &lt;strong&gt;Ctrl+S&lt;/strong&gt; om op te slaan.&lt;/p&gt;&lt;p&gt;Of klik op de &lt;em&gt;Opslaan&lt;/em&gt; knop in de toolbar.&lt;/p&gt;', {title:'Tip', html:true})">HTML melding</button>
</div>

<p><strong>Technische details (inklapbaar)</strong></p>
<div class="demo-row">
    <button class="btn btn-primary" onclick="libAlert('Kan het bestand niet opslaan', {type:'danger', title:'Fout', details:'Error: EPERM - permission denied\nPath: /var/data/export.csv\nTimestamp: 2026-02-09T08:42:00Z'})">Met details</button>
</div>

<p><strong>Await / chaining</strong></p>
<div class="demo-row">
    <button class="btn btn-primary" onclick="libAlert('Stap 1: Eerste melding').then(() => libAlert('Stap 2: Tweede melding', {type:'success', title:'Voltooid'}))">Chained alerts</button>
</div></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Signatuur</h4>
                <dl>
                    <dt>libAlert(message, options?)</dt>
                    <dd>Alias voor LibDialog.alert()</dd>
                </dl>
                <h4>Parameters</h4>
                <dl>
                    <dt>message</dt>
                    <dd>De melding die wordt getoond (string of HTML)</dd>
                    <dt>options.title</dt>
                    <dd>Titel van de dialoog (default: 'Melding')</dd>
                    <dt>options.type</dt>
                    <dd>'info' | 'warning' | 'danger' | 'success' (default: 'info')</dd>
                    <dt>options.buttonText</dt>
                    <dd>Tekst op de knop (default: 'OK')</dd>
                    <dt>options.html</dt>
                    <dd>true = message als HTML renderen (auto-detect als niet opgegeven)</dd>
                    <dt>options.details</dt>
                    <dd>Technische details in een inklapbare sectie</dd>
                </dl>
                <h4>Return</h4>
                <dl>
                    <dt>Promise&lt;void&gt;</dt>
                    <dd>Resolved wanneer de gebruiker op OK klikt of Escape drukt</dd>
                </dl>
                <h4>Gedrag</h4>
                <ul style="font-size:var(--font-size); color: var(--text-secondary); margin:0; padding-left:18px;">
                    <li>Dialoog is <strong>closable</strong> &mdash; sluitbaar via Escape, backdrop-klik of knop</li>
                    <li>HTML in message wordt automatisch gedetecteerd, tenzij <code>html</code> expliciet is opgegeven</li>
                    <li>Bij <code>details</code> wordt de dialoog groter (size: medium i.p.v. small)</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="component-section" id="libConfirm">
        <div class="component-header">
            <h2>libConfirm</h2>
            <span class="tag lib">library</span>
            <p class="component-description">Toon een bevestigingsdialoog. Retourneert een Promise met true (bevestigd) of false (geannuleerd).</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><p><strong>Standaard bevestiging (warning)</strong></p>
<div class="demo-row">
    <button class="btn btn-primary" onclick="libConfirm('Weet u het zeker?').then(ok => libAlert(ok ? 'Bevestigd' : 'Geannuleerd'))">Standaard (Ja/Nee)</button>
</div>

<p><strong>Type varianten</strong></p>
<div class="demo-row">
    <button class="btn btn-primary" onclick="libConfirm('Dit record verwijderen?', {type:'danger', title:'Verwijderen', confirmText:'Verwijderen', cancelText:'Behouden'}).then(ok => libAlert(ok ? 'Verwijderd' : 'Behouden'))">Danger</button>
    <button class="btn btn-primary" onclick="libConfirm('Wilt u doorgaan?', {type:'info', title:'Doorgaan'}).then(ok => libAlert(ok ? 'Doorgegaan' : 'Gestopt'))">Info</button>
</div>

<p><strong>Aangepaste knopteksten</strong></p>
<div class="demo-row">
    <button class="btn btn-primary" onclick="libConfirm('Wijzigingen opslaan voordat u verdergaat?', {title:'Onopgeslagen wijzigingen', confirmText:'Opslaan', cancelText:'Niet opslaan'}).then(ok => libAlert(ok ? 'Opgeslagen' : 'Niet opgeslagen'))">Opslaan / Niet opslaan</button>
    <button class="btn btn-primary" onclick="libConfirm('Alle geselecteerde items archiveren?', {title:'Archiveren', confirmText:'Archiveer alles', cancelText:'Annuleren', type:'info'}).then(ok => libAlert(ok ? 'Gearchiveerd' : 'Geannuleerd'))">Archiveren</button>
</div>

<p><strong>HTML inhoud</strong></p>
<div class="demo-row">
    <button class="btn btn-primary" onclick="libConfirm('&lt;p&gt;U staat op het punt &lt;strong&gt;3 records&lt;/strong&gt; te verwijderen.&lt;/p&gt;&lt;p style=\'color:var(--color-error)\'&gt;Dit kan niet ongedaan worden gemaakt.&lt;/p&gt;', {type:'danger', title:'Definitief verwijderen', html:true, confirmText:'Verwijderen', cancelText:'Annuleren'}).then(ok => libAlert(ok ? 'Verwijderd' : 'Geannuleerd'))">HTML bevestiging</button>
</div></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Signatuur</h4>
                <dl>
                    <dt>libConfirm(message, options?)</dt>
                    <dd>Alias voor LibDialog.confirm()</dd>
                </dl>
                <h4>Parameters</h4>
                <dl>
                    <dt>message</dt>
                    <dd>De vraag die wordt getoond (string of HTML)</dd>
                    <dt>options.title</dt>
                    <dd>Titel van de dialoog (default: 'Bevestigen')</dd>
                    <dt>options.confirmText</dt>
                    <dd>Tekst bevestigknop (default: 'Ja')</dd>
                    <dt>options.cancelText</dt>
                    <dd>Tekst annuleerknop (default: 'Nee')</dd>
                    <dt>options.type</dt>
                    <dd>'info' | 'warning' | 'danger' (default: 'warning')</dd>
                    <dt>options.html</dt>
                    <dd>true = message als HTML renderen (auto-detect als niet opgegeven)</dd>
                </dl>
                <h4>Return</h4>
                <dl>
                    <dt>Promise&lt;boolean&gt;</dt>
                    <dd>true bij bevestigen, false bij annuleren</dd>
                </dl>
                <h4>Gedrag</h4>
                <ul style="font-size:var(--font-size); color: var(--text-secondary); margin:0; padding-left:18px;">
                    <li>Dialoog is <strong>niet closable</strong> &mdash; Escape en backdrop-klik zijn uitgeschakeld</li>
                    <li>Gebruiker <em>moet</em> een keuze maken via een van de twee knoppen</li>
                    <li>Enter-toets bevestigt de actie</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="component-section" id="libPrompt">
        <div class="component-header">
            <h2>libPrompt</h2>
            <span class="tag lib">library</span>
            <p class="component-description">Toon een invoerdialoog met een tekstveld. Retourneert de ingevoerde waarde of null bij annuleren.</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><p><strong>Standaard tekstinvoer</strong></p>
<div class="demo-row">
    <button class="btn btn-primary" onclick="libPrompt('Wat is uw naam?', {title:'Naam', placeholder:'Typ uw naam...'}).then(v => v !== null ? libAlert('Hallo, ' + v + '!', {type:'success'}) : libAlert('Geannuleerd'))">Tekst prompt</button>
</div>

<p><strong>Met standaardwaarde</strong></p>
<div class="demo-row">
    <button class="btn btn-primary" onclick="libPrompt('Bestandsnaam:', {title:'Hernoemen', defaultValue:'document.pdf', placeholder:'Bestandsnaam...'}).then(v => v !== null ? libAlert('Hernoemd naar: ' + v, {type:'success'}) : libAlert('Geannuleerd'))">Met defaultValue</button>
</div>

<p><strong>Verplicht veld (required)</strong></p>
<div class="demo-row">
    <button class="btn btn-primary" onclick="libPrompt('E-mailadres:', {title:'Inschrijven', inputType:'email', required:true, placeholder:'naam@voorbeeld.nl', confirmText:'Inschrijven', cancelText:'Annuleren'}).then(v => v !== null ? libAlert('Ingeschreven: ' + v, {type:'success'}) : libAlert('Geannuleerd'))">Verplicht e-mail</button>
</div>

<p><strong>Multiline (textarea)</strong></p>
<div class="demo-row">
    <button class="btn btn-primary" onclick="libPrompt('Opmerking:', {title:'Notitie toevoegen', multiline:true, placeholder:'Typ uw opmerking...', confirmText:'Toevoegen'}).then(v => v !== null ? libAlert('Opmerking opgeslagen', {type:'success'}) : libAlert('Geannuleerd'))">Multiline</button>
</div>

<p><strong>Numerieke invoer</strong></p>
<div class="demo-row">
    <button class="btn btn-primary" onclick="libPrompt('Aantal:', {title:'Hoeveelheid', inputType:'number', defaultValue:'1', confirmText:'Toepassen'}).then(v => v !== null ? libAlert('Aantal: ' + v) : libAlert('Geannuleerd'))">Nummer invoer</button>
</div>

<p><strong>Aangepaste knopteksten en type</strong></p>
<div class="demo-row">
    <button class="btn btn-primary" onclick="libPrompt('Reden van afwijzing:', {title:'Afwijzen', type:'danger', multiline:true, required:true, confirmText:'Afwijzen', cancelText:'Terug', placeholder:'Geef een reden op...'}).then(v => v !== null ? libAlert('Afgewezen met reden: ' + v, {type:'danger'}) : libAlert('Geannuleerd'))">Danger prompt</button>
</div></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Signatuur</h4>
                <dl>
                    <dt>libPrompt(message, options?)</dt>
                    <dd>Alias voor LibDialog.prompt()</dd>
                </dl>
                <h4>Parameters</h4>
                <dl>
                    <dt>message</dt>
                    <dd>De vraag/label boven het invoerveld (string)</dd>
                    <dt>options.title</dt>
                    <dd>Titel van de dialoog (default: 'Invoer')</dd>
                    <dt>options.defaultValue</dt>
                    <dd>Vooraf ingevulde waarde (default: '')</dd>
                    <dt>options.placeholder</dt>
                    <dd>Placeholder tekst in het veld (default: '')</dd>
                    <dt>options.confirmText</dt>
                    <dd>Tekst bevestigknop (default: 'OK')</dd>
                    <dt>options.cancelText</dt>
                    <dd>Tekst annuleerknop (default: 'Annuleren')</dd>
                    <dt>options.type</dt>
                    <dd>'info' | 'warning' | 'danger' | 'success' (default: 'info')</dd>
                    <dt>options.inputType</dt>
                    <dd>'text' | 'number' | 'email' | 'password' | etc. (default: 'text')</dd>
                    <dt>options.required</dt>
                    <dd>true = veld moet ingevuld zijn om te bevestigen (default: false)</dd>
                    <dt>options.multiline</dt>
                    <dd>true = textarea i.p.v. input (default: false)</dd>
                </dl>
                <h4>Return</h4>
                <dl>
                    <dt>Promise&lt;string|null&gt;</dt>
                    <dd>De ingevoerde waarde, of null bij annuleren</dd>
                </dl>
                <h4>Gedrag</h4>
                <ul style="font-size:var(--font-size); color: var(--text-secondary); margin:0; padding-left:18px;">
                    <li>Dialoog is <strong>closable</strong> &mdash; Escape, sluitknop en backdrop-klik sluiten de dialoog (retourneert null)</li>
                    <li>Enter-toets in het invoerveld bevestigt (behalve bij multiline)</li>
                    <li>Bij <code>required:true</code> wordt het veld rood gemarkeerd met foutmelding bij leeg bevestigen</li>
                    <li>Het invoerveld krijgt automatisch focus bij openen</li>
                </ul>
            </div>
        </div>
    </section>

    <!-- ================================================================ -->
    <!-- CMA COMPONENTEN -->
    <!-- ================================================================ -->

    <section class="component-section" id="blockeditor">
        <div class="component-header">
            <h2>blockeditor (CKEditor)</h2>
            <span class="tag cma">CMA</span>
            <p class="component-description">Contentblok-editor met CKEditor rich text editing en drag-and-drop herordening</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <h4 style="margin: 0 0 10px;">Demo</h4>
                <div id="blockeditor-demo" style="border: 1px solid var(--border-color); border-radius: 6px; padding: 15px; background: var(--bg-surface);">
                    <div class="blockedit" data-field="demo-blockeditor">
                        <textarea name="demo-blockeditor" style="display:none;"><!--BLOCK{"type":"Beeldblok","variables":{"beeldblock_url":"/images/html/-300_cube_series_2019500px.jpg","beeldblock_titel":"Cube Series","beeldblock_tekst":"<p>Een serie kubussen uit 2019.</p>"},"visible":true}--><div class="row"><div class="imgBlock col-xs-12 col-lg-8"><div class="imgBlock__img" role="img" style="background-image:url(/images/html/-300_cube_series_2019500px.jpg)"></div><div class="imgBlock__container"><h3 class="sectionSubTitle__title">Cube Series</h3><div class="text imgBlock__description"><p>Een serie kubussen uit 2019.</p></div></div></div></div><!--BLOCK{"type":"Anker","variables":{"anker_naam":"ankertje"},"visible":true}--><div class="row"><a name="ankertje"></a></div></textarea>
                    </div>
                </div>

                <h4 style="margin: 15px 0 10px;">Resultaat (ruwe HTML)</h4>
                <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                    <button class="btn btn-secondary" id="blockeditor-show-result" style="font-size: var(--font-size-xs);">Toon resultaat</button>
                </div>
                <pre id="blockeditor-result" style="border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; background: var(--bg-surface-alt); font-size: var(--font-size-xs); max-height: 300px; overflow: auto; white-space: pre-wrap; word-break: break-all; display: none;"></pre>

                <script>
                (function() {
                    // Wait for CKEditor to be fully loaded + blockedit available
                    function initBlockEditor() {
                        if (typeof CKEDITOR === 'undefined' || typeof blockedit_init !== 'function') {
                            setTimeout(initBlockEditor, 100);
                            return;
                        }
                        // CKEditor script is loaded but may not be fully initialized yet
                        function doInit() {
                            // Set editor config if available
                            if (typeof SetFKEditorConfig === 'function') {
                                SetFKEditorConfig({ customCSS: '/cma/CKEditor/contents.css', allowBR: false });
                            } else if (typeof CMA !== 'undefined' && CMA.editor && CMA.editor.setConfig) {
                                CMA.editor.setConfig({ customCSS: '/cma/CKEditor/contents.css', allowBR: false });
                            }
                            blockedit_init();
                        }
                        if (CKEDITOR.status === 'loaded') {
                            doInit();
                        } else {
                            CKEDITOR.on('loaded', doInit);
                        }
                    }
                    jQuery(initBlockEditor);

                    // Format HTML for display with indentation
                    // Show result button - collect block data first, then read textarea
                    jQuery(document).on('click', '#blockeditor-show-result', function() {
                        if (typeof blockedit_collect_htmls === 'function') {
                            blockedit_collect_htmls();
                        }
                        var textarea = jQuery('textarea[name="demo-blockeditor"]');
                        var resultEl = document.getElementById('blockeditor-result');
                        if (textarea.length && resultEl) {
                            resultEl.textContent = CMA.utils.formatHtml(textarea.val());
                            resultEl.style.display = 'block';
                        }
                    });
                })();
                </script>
            </div>
            <div class="component-options">
                <h4>Gebruik</h4>
                <dl>
                    <dt>.blockedit</dt>
                    <dd>Wrapper div met <code>data-field</code> attribuut rond een textarea</dd>
                    <dt>data-field</dt>
                    <dd>Naam van het formulierveld</dd>
                </dl>
                <h4>Dataformaat</h4>
                <dl>
                    <dt>Blokken</dt>
                    <dd>HTML met <code>&lt;!--BLOCK{json}--&gt;</code> comment markers tussen blokken</dd>
                    <dt>contentblocks.json</dt>
                    <dd>Bloktemplates worden geladen uit <code>assets/contentblocks/contentblocks.json</code>. Deze templates zijn te beheren via het formulier <a href="/cma/form.php?form=contentblocks" target="_blank" style="color: var(--color-primary);">Contentblocks</a> in CMA.</dd>
                </dl>
                <h4>Bloktemplate velden</h4>
                <dl>
                    <dt>text / longtext</dt>
                    <dd>Tekstveld / CKEditor rich text</dd>
                    <dt>image / file</dt>
                    <dd>Afbeelding of bestandsupload</dd>
                    <dt>url</dt>
                    <dd>URL invoer</dd>
                    <dt>boolean / switch</dt>
                    <dd>Aan/uit schakelaar</dd>
                    <dt>array</dt>
                    <dd>Herhaalbare items</dd>
                </dl>
                <h4>Functies</h4>
                <dl>
                    <dt>blockedit_init()</dt>
                    <dd>Initialiseert alle <code>.blockedit</code> containers op de pagina</dd>
                    <dt>blockedit_add_new_element()</dt>
                    <dd>Voegt een nieuw blok toe aan een container</dd>
                </dl>
                <h4>Afhankelijkheden</h4>
                <dl>
                    <dt>jQuery</dt>
                    <dd>Vereist voor DOM-manipulatie</dd>
                    <dt>CKEditor</dt>
                    <dd>Rich text editing per veld</dd>
                    <dt>blockedit.js</dt>
                    <dd><code>assets/js/blockedit.js</code></dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="cma-fold">
        <div class="component-header">
            <h2>cma-fold</h2>
            <span class="tag cma">CMA</span>
            <p class="component-description">Scheidingslijn voor paneelgroottes</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><div style="display: flex; height: 150px; border: 1px solid var(--border-color);">
    <div class="fold-left-panel" style="width: 200px; min-width: 100px; max-width: 400px; background: var(--bg-surface-alt); padding: 10px; overflow: auto;">
        <strong>Links paneel</strong>
        <p style="font-size: var(--font-size-sm);">Sleep de scheidingslijn om dit paneel te vergroten of verkleinen.</p>
    </div>
    <cma-fold
        orientation="vertical"
        target=".fold-left-panel"
        min-size="100"
        max-size="400">
    </cma-fold>
    <div style="flex: 1; background: var(--bg-surface); padding: 10px; overflow: auto;">
        <strong>Rechts paneel</strong>
        <p style="font-size: var(--font-size-sm);">Dit paneel groeit/krimpt automatisch mee.</p>
    </div>
</div>

<!-- Met reverse attribuut (target rechts van fold) -->
<div style="display: flex; height: 150px; border: 1px solid var(--border-color); margin-top: 15px;">
    <div style="flex: 1; background: var(--bg-surface); padding: 10px; overflow: auto;">
        <strong>Links paneel</strong>
        <p style="font-size: var(--font-size-sm);">Dit paneel groeit/krimpt automatisch mee.</p>
    </div>
    <cma-fold
        orientation="vertical"
        target=".fold-right-panel"
        min-size="100"
        max-size="400"
        reverse>
    </cma-fold>
    <div class="fold-right-panel" style="width: 200px; min-width: 100px; max-width: 400px; background: var(--bg-surface-alt); padding: 10px; overflow: auto;">
        <strong>Rechts paneel (met reverse)</strong>
        <p style="font-size: var(--font-size-sm);">Target staat NA de fold, dus reverse is nodig.</p>
    </div>
</div></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>orientation</dt>
                    <dd>vertical (breedte) | horizontal (hoogte)</dd>
                    <dt>target</dt>
                    <dd>CSS selector voor te resizen element</dd>
                    <dt>min-size</dt>
                    <dd>Minimum breedte/hoogte in px (default: 150)</dd>
                    <dt>max-size</dt>
                    <dd>Maximum breedte/hoogte in px (default: 600)</dd>
                    <dt>storage-key</dt>
                    <dd>localStorage key voor opslaan staat</dd>
                    <dt>collapsed-size</dt>
                    <dd>Grootte bij ingeklapt (default: 0)</dd>
                    <dt>reverse</dt>
                    <dd>Keer sleeprichting om (gebruik als target NA de fold staat)</dd>
                </dl>
                <h4>Events</h4>
                <dl>
                    <dt>fold-resize</dt>
                    <dd>Tijdens resize (detail: size, collapsed)</dd>
                    <dt>fold-collapse</dt>
                    <dd>Bij in-/uitklappen (detail: collapsed)</dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="cma-groupbox">
        <div class="component-header">
            <h2>cma-groupbox</h2>
            <span class="tag cma">CMA</span>
            <p class="component-description">Inklapbare groepskop. Werkt standalone (toggelt volgende element) of in formuliermodus (toggelt rijen op ID).</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <h4>Standalone modus</h4>
                <p style="font-size: var(--font-size-sm); color: var(--text-muted); margin-bottom: 8px;">De groupbox toggelt automatisch het volgende element. Gebruik <code>count</code> voor een badge en <code>storage-key</code> voor persistente staat.</p>
                <div class="playground">
                    <textarea><cma-groupbox caption="Sectiename" count="5" storage-key="sb_demo1"></cma-groupbox>
<div style="padding: 12px; border: 1px solid var(--border-color); border-top: 0; border-radius: 0 0 6px 6px;">
    <p>Inhoud die in- en uitgeklapt kan worden.</p>
    <p>Staat wordt onthouden via localStorage.</p>
</div>

<div style="margin-top: 16px;">
<cma-groupbox caption="Ingeklapt voorbeeld" count="3" storage-key="sb_demo2" collapsed></cma-groupbox>
<div style="padding: 12px; border: 1px solid var(--border-color); border-top: 0; border-radius: 0 0 6px 6px;">
    <p>Deze sectie start ingeklapt.</p>
</div>
</div></textarea>
                </div>
                <h4 style="margin-top: 20px;">Formulier modus</h4>
                <p style="font-size: var(--font-size-sm); color: var(--text-muted); margin-bottom: 8px;">Met <code>group-id</code> en <code>form-id</code> worden rijen met <code>id="_g{groupId}_{index}"</code> automatisch getoond/verborgen.</p>
                <div class="playground">
                    <textarea><table class="frm" style="width: 100%; border-collapse: collapse;">
    <tbody>
        <tr class="groupbox-row">
            <td colspan="2" style="padding: 0;">
                <cma-groupbox group-id="101" form-id="0" caption="Persoonlijke gegevens"></cma-groupbox>
            </td>
        </tr>
        <tr id="_g101_1" data-group-row="101">
            <td class="c1_g">Naam:</td>
            <td class="c2_g">Jan Jansen</td>
        </tr>
        <tr id="_g101_2" data-group-row="101">
            <td class="c1_g">E-mail:</td>
            <td class="c2_g">jan@voorbeeld.nl</td>
        </tr>
        <tr class="groupbox-end" data-group-row="101"><td colspan="2"></td></tr>
    </tbody>
</table></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>caption</dt>
                    <dd>Titel van de groep</dd>
                    <dt>count</dt>
                    <dd>Optionele badge achter de titel (bijv. aantal items)</dd>
                    <dt>collapsed</dt>
                    <dd>Initieel ingeklapt (aanwezigheid = true)</dd>
                    <dt>storage-key</dt>
                    <dd>localStorage key voor persistente staat. Auto-gegenereerd uit caption als niet opgegeven.</dd>
                    <dt>group-id</dt>
                    <dd>Formuliermodus: unieke groep ID</dd>
                    <dt>form-id</dt>
                    <dd>Formuliermodus: formulier ID</dd>
                </dl>
                <h4>Properties</h4>
                <dl>
                    <dt>isOpen</dt>
                    <dd>Get/set de open staat (boolean)</dd>
                </dl>
                <h4>Methoden</h4>
                <dl>
                    <dt>toggle()</dt>
                    <dd>Wissel open/dicht</dd>
                    <dt>open()</dt>
                    <dd>Open de groep</dd>
                    <dt>close()</dt>
                    <dd>Sluit de groep</dd>
                </dl>
                <h4>Events</h4>
                <dl>
                    <dt>groupbox-toggle</dt>
                    <dd>Vuurt bij statusverandering. <code>detail: { open: boolean }</code></dd>
                </dl>
                <h4>Modi</h4>
                <p style="font-size: var(--font-size-xs); color: var(--text-muted);">
                    <strong>Standalone:</strong> Zonder group-id. Toggelt het volgende sibling element.<br>
                    <strong>Formulier:</strong> Met group-id/form-id. Toggelt rijen met <code>id="_g{groupId}_{index}"</code>.
                </p>
            </div>
        </div>
    </section>

    <section class="component-section" id="cma-htmledit">
        <div class="component-header">
            <h2>cma-htmledit</h2>
            <span class="tag cma">CMA</span>
            <p class="component-description">CKEditor wrapper met full/simple/minimal modus</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <script>
                // Set editor config for cma-htmledit demos (CKEditor loaded in blockeditor section)
                if (typeof CMA !== 'undefined' && CMA.editor && CMA.editor.setConfig) {
                    CMA.editor.setConfig({ customCSS: '/cma/CKEditor/contents.css', allowBR: false });
                }
                </script>
                <div class="playground">
                    <textarea><div class="demo-row">
    <span class="demo-label">Full modus:</span>
</div>
<cma-htmledit name="demo-htmledit-full" height="200">
    <textarea name="demo-htmledit-full"><p>Voorbeeld tekst in <strong>full</strong> modus.</p></textarea>
</cma-htmledit>

<div class="demo-row" style="margin-top: 20px;">
    <span class="demo-label">Simple modus:</span>
</div>
<cma-htmledit name="demo-htmledit-simple" height="150" mode="simple">
    <textarea name="demo-htmledit-simple"><p>Voorbeeld tekst in <em>simple</em> modus.</p></textarea>
</cma-htmledit>

<div class="demo-row" style="margin-top: 20px;">
    <span class="demo-label">Minimal modus:</span>
</div>
<cma-htmledit name="demo-htmledit-minimal" height="100" mode="minimal">
    <textarea name="demo-htmledit-minimal"><p>Minimale editor zonder toolbar.</p></textarea>
</cma-htmledit></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>name</dt>
                    <dd>Veldnaam (verplicht)</dd>
                    <dt>height</dt>
                    <dd>Editor hoogte in pixels (default: 300)</dd>
                    <dt>mode</dt>
                    <dd><code>full</code> (default), <code>simple</code>, of <code>minimal</code></dd>
                    <dt>custom-css</dt>
                    <dd>Pad naar eigen CSS voor editor-inhoud</dd>
                    <dt>allow-br</dt>
                    <dd>Gebruik BR voor Enter i.p.v. P (boolean)</dd>
                    <dt>value</dt>
                    <dd>Initiële HTML-waarde (alternatief voor textarea child)</dd>
                    <dt>disabled</dt>
                    <dd>Schakel naar readonly modus</dd>
                </dl>
                <h4>Properties</h4>
                <dl>
                    <dt>editor</dt>
                    <dd>CKEditor instantie (readonly)</dd>
                    <dt>value</dt>
                    <dd>HTML-inhoud (getter/setter)</dd>
                </dl>
                <h4>Events</h4>
                <dl>
                    <dt>editor-ready</dt>
                    <dd>CKEditor geïnitialiseerd. Detail: <code>{editor}</code></dd>
                    <dt>editor-change</dt>
                    <dd>Inhoud gewijzigd. Detail: <code>{value}</code></dd>
                </dl>
                <h4>Gebruik</h4>
                <dl>
                    <dt>Met textarea child</dt>
                    <dd><code>&lt;cma-htmledit name="x"&gt;&lt;textarea name="x"&gt;HTML&lt;/textarea&gt;&lt;/cma-htmledit&gt;</code></dd>
                    <dt>Met value attribuut</dt>
                    <dd><code>&lt;cma-htmledit name="x" value="&lt;p&gt;...&lt;/p&gt;"&gt;&lt;/cma-htmledit&gt;</code></dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="cma-sortlist">
        <div class="component-header">
            <h2>cma-sortlist</h2>
            <span class="tag cma">CMA</span>
            <p class="component-description">Versleepbare sorteerlijst component.</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><div style="display: flex; flex-wrap: wrap; gap: 20px;">
    <div style="flex: 1; min-width: 200px; max-width: 300px;">
        <div class="demo-row">
            <span class="demo-label">Standaard:</span>
        </div>
        <cma-sortlist>
            <option value="1">Koek</option>
            <option value="2">Taart</option>
            <option value="3">Cake</option>
        </cma-sortlist>
    </div>
    <div style="flex: 1; min-width: 200px; max-width: 300px;">
        <div class="demo-row">
            <span class="demo-label">Disabled:</span>
        </div>
        <cma-sortlist disabled>
            <option value="1">Koek</option>
            <option value="2">Taart</option>
            <option value="3">Cake</option>
        </cma-sortlist>
    </div>
</div></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>name</dt>
                    <dd>Veldnaam voor formulier</dd>
                    <dt>value</dt>
                    <dd>Komma-gescheiden waarden in sorteervolgorde</dd>
                    <dt>disabled</dt>
                    <dd>Component uitschakelen</dd>
                    <dt>max-height</dt>
                    <dd>Maximum hoogte in px (default: 400)</dd>
                </dl>
                <h4>Child elements</h4>
                <dl>
                    <dt>&lt;option value="x"&gt;</dt>
                    <dd>Items met waarde en label</dd>
                </dl>
                <h4>Events</h4>
                <dl>
                    <dt>change</dt>
                    <dd>Bij wijziging volgorde</dd>
                    <dt>sort</dt>
                    <dd>Tijdens sleep operatie</dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="cma-tabs">
        <div class="component-header">
            <h2>cma-tabs</h2>
            <span class="tag cma">CMA</span>
            <p class="component-description">Tabnavigatie met wizard modus</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><cma-tabs>
    <tab-item title="Overzicht"></tab-item>
    <tab-item title="Details" data-count="3"></tab-item>
    <tab-item title="Instellingen"></tab-item>
    <div slot="tab-0">
        <h3>Overzicht</h3>
        <p>Dit is de inhoud van het eerste tabblad.</p>
    </div>
    <div slot="tab-1">
        <h3>Details</h3>
        <p>Dit is de inhoud van het tweede tabblad.</p>
    </div>
    <div slot="tab-2">
        <h3>Instellingen</h3>
        <p>Dit is de inhoud van het derde tabblad.</p>
    </div>
</cma-tabs></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>selected</dt>
                    <dd>Index van geselecteerde tab (default: 0)</dd>
                    <dt>breakpoint</dt>
                    <dd>Breedte waarbij select verschijnt (default: 500)</dd>
                    <dt>tabs</dt>
                    <dd>JSON array met tab titels of objecten</dd>
                    <dt>mode</dt>
                    <dd>default | wizard (default: default)</dd>
                </dl>
                <h4>Slots</h4>
                <dl>
                    <dt>tab-0, tab-1, ...</dt>
                    <dd>Inhoud per tabblad</dd>
                </dl>
                <h4>Methoden</h4>
                <dl>
                    <dt>setTabs(tabs)</dt>
                    <dd>Set tabs array programmatisch</dd>
                    <dt>selectTab(index)</dt>
                    <dd>Selecteer tab op index</dd>
                </dl>
                <h4>Events</h4>
                <dl>
                    <dt>tab-select</dt>
                    <dd>Bij selecteren tab (detail: index, id, title)</dd>
                    <dt>step-change</dt>
                    <dd>In wizard mode bij stap wijziging</dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="cma-toolbar">
        <div class="component-header">
            <h2>cma-toolbar</h2>
            <span class="tag cma">CMA</span>
            <p class="component-description">Werkbalk met left/center/right secties.</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><cma-toolbar>
    <left>
        <span class="tb-btn">
            <a href="javascript:void(0)" title="Nieuw">
                <span class="lnr lnr-plus-circle"></span>
                <span class="tb-btn-text">Nieuw</span>
            </a>
        </span>
        <span class="tb-btn">
            <a href="javascript:void(0)" title="Bewerken">
                <span class="lnr lnr-pencil"></span>
                <span class="tb-btn-text">Bewerken</span>
            </a>
        </span>
        <span class="tb-sep"></span>
        <span class="tb-btn disabled">
            <a href="javascript:void(0)" title="Verwijderen">
                <span class="lnr lnr-trash"></span>
            </a>
        </span>
    </left>
    <center>
        <span style="color: var(--text-muted); font-size: var(--font-size-sm);">3 items geselecteerd</span>
    </center>
    <right>
        <span class="tb-btn">
            <a href="javascript:void(0)" title="Vernieuwen">
                <span class="lnr lnr-sync"></span>
            </a>
        </span>
    </right>
</cma-toolbar>

<div style="margin-top: 15px;">
    <span class="demo-label">Met titel:</span>
</div>
<cma-toolbar>
    <left><span class="toolbar-title">Details</span></left>
</cma-toolbar>

<div style="margin-top: 15px;">
    <span class="demo-label">Subformulier variant:</span>
</div>
<cma-toolbar variant="subform">
    <left>
        <span class="tb-btn"><a href="javascript:void(0)" data-tooltip="Voeg item toe"><span class="lnr lnr-plus-circle"></span></a></span>
    </left>
    <right>
        <span class="tb-btn"><a href="javascript:void(0)" data-tooltip="Verwijderen"><span class="lnr lnr-trash"></span></a></span>
    </right>
</cma-toolbar></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>variant</dt>
                    <dd><code>default</code> | <code>list</code> | <code>detail</code> | <code>subform</code> (default: <code>"default"</code>)</dd>
                    <dt>sticky</dt>
                    <dd>Toolbar blijft bovenaan bij scrollen (default: <code>false</code>)</dd>
                </dl>
                <h4>Slots (child elements)</h4>
                <dl>
                    <dt>&lt;left&gt;</dt>
                    <dd>Inhoud links uitgelijnd</dd>
                    <dt>&lt;center&gt;</dt>
                    <dd>Inhoud gecentreerd (optioneel)</dd>
                    <dt>&lt;right&gt;</dt>
                    <dd>Inhoud rechts uitgelijnd</dd>
                </dl>
                <h4>Knop classes</h4>
                <dl>
                    <dt>.tb-btn</dt>
                    <dd>Toolbar knop container</dd>
                    <dt>.tb-btn.disabled</dt>
                    <dd>Uitgeschakelde knop</dd>
                    <dt>.tb-sep</dt>
                    <dd>Verticale scheidingslijn</dd>
                    <dt>.tb-btn-text</dt>
                    <dd>Optionele tekst naast icoon</dd>
                    <dt>.toolbar-title</dt>
                    <dd>Titel styling</dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="cma-tree">
        <div class="component-header">
            <h2>cma-tree</h2>
            <span class="tag cma">CMA</span>
            <p class="component-description">Boomnavigatie voor hiërarchische data.</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><div style="height: 200px; border: 1px solid var(--border-color); overflow: auto;">
    <cma-tree id="demoTree"></cma-tree>
</div>
<script>
document.getElementById('demoTree').setData([
    {
        type: 'folder', id: 1, label: 'Documenten',
        children: [
            { type: 'item', id: 2, label: 'Jan Jansen', icon: 'user' },
            { type: 'item', id: 3, label: 'Piet de Vries', icon: 'user' },
            {
                type: 'folder', id: 4, label: 'Archief',
                children: [
                    { type: 'item', id: 5, label: 'Klaas Bakker', icon: 'user' }
                ]
            }
        ]
    },
    { type: 'item', id: 6, label: 'Instellingen', icon: 'cog' }
]);
</script>
<div class="demo-row" style="gap: 6px; flex-wrap: wrap; margin-top: 10px;">
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#demoTree').expandAll()">expandAll()</button>
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#demoTree').collapseAll()">collapseAll()</button>
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#demoTree').selectById(3)">selectById(3)</button>
    <button class="btn btn-secondary" onclick="this.closest('.playground-preview').querySelector('#demoTree').setData([{type:'folder',id:10,label:'Nieuw',children:[{type:'item',id:11,label:'Item A',icon:'file-empty'},{type:'item',id:12,label:'Item B',icon:'file-empty'}]},{type:'item',id:13,label:'Los item',icon:'cog'}])">setData(nieuw)</button>
</div></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>id</dt>
                    <dd>Element ID voor JavaScript referentie</dd>
                </dl>
                <h4>Methodes</h4>
                <dl>
                    <dt>setData(items)</dt>
                    <dd>Stel de boomdata in. Elk item heeft: <code>type</code> (folder/item), <code>id</code>, <code>label</code>, <code>icon</code>, <code>children</code></dd>
                    <dt>expandAll()</dt>
                    <dd>Klap alle mappen uit</dd>
                    <dt>collapseAll()</dt>
                    <dd>Klap alle mappen in</dd>
                </dl>
                <h4>Events</h4>
                <dl>
                    <dt>tree-select</dt>
                    <dd>Wordt getriggerd bij selectie van een item</dd>
                </dl>
            </div>
        </div>
    </section>

    <!-- ================================================================ -->
    <!-- ONTWERPSYSTEEM -->
    <!-- ================================================================ -->

    <section class="component-section" id="badges">
        <div class="component-header">
            <h2>Badges</h2>
            <span class="tag">Ontwerp</span>
            <p class="component-description">Status- en notificatiebadges voor labels, tellers en indicatoren.</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <h4>Status badges</h4>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px;">
                    <span class="badge badge-success">Actief</span>
                    <span class="badge badge-error">Fout</span>
                    <span class="badge badge-warning">Waarschuwing</span>
                    <span class="badge badge-info">Informatie</span>
                    <span class="badge badge-muted">Inactief</span>
                </div>

                <h4>lib-label varianten</h4>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 16px;">
                    <lib-label type="information">Informatie</lib-label>
                    <lib-label type="success">Succes</lib-label>
                    <lib-label type="warning">Waarschuwing</lib-label>
                    <lib-label type="error">Fout</lib-label>
                </div>

                <h4>lib-label formaten</h4>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 16px;">
                    <lib-label type="information" size="small">Klein</lib-label>
                    <lib-label type="information" size="normal">Normaal</lib-label>
                    <lib-label type="information" size="large">Groot</lib-label>
                </div>

                <h4>lib-label met icoon</h4>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 16px;">
                    <lib-label type="success"><span class="lnr lnr-checkmark-circle"></span> Goedgekeurd</lib-label>
                    <lib-label type="error"><span class="lnr lnr-cross-circle"></span> Afgekeurd</lib-label>
                    <lib-label type="warning"><span class="lnr lnr-warning"></span> Let op</lib-label>
                </div>

                <h4>Notificatie badge (.have_data)</h4>
                <div style="display: flex; gap: 20px; align-items: center;">
                    <span style="position:relative;">Menu-item <span class="have_data" style="position:static; float:none;">3</span></span>
                    <span style="position:relative;">Taken <span class="have_data loading" style="position:static; float:none;">...</span> (loading)</span>
                </div>
            </div>
            <div class="component-options">
                <h4>.badge classes</h4>
                <dl>
                    <dt>.badge</dt>
                    <dd>Basistijl: inline-block, uppercase, vet, afgeronde hoeken</dd>
                    <dt>.badge-success</dt>
                    <dd>Groen, witte tekst</dd>
                    <dt>.badge-error</dt>
                    <dd>Rood, witte tekst</dd>
                    <dt>.badge-warning</dt>
                    <dd>Geel, zwarte tekst</dd>
                    <dt>.badge-info</dt>
                    <dd>Blauw (primary), witte tekst</dd>
                    <dt>.badge-muted</dt>
                    <dd>Grijs met rand, gedempte tekst</dd>
                </dl>
                <h4>lib-label attributen</h4>
                <dl>
                    <dt>type</dt>
                    <dd><code>information</code>, <code>success</code>, <code>warning</code>, <code>error</code> (default: geen, neutrale stijl)</dd>
                    <dt>size</dt>
                    <dd><code>small</code>, <code>normal</code>, <code>large</code> (default: <code>"normal"</code>)</dd>
                </dl>
                <h4>.have_data</h4>
                <dl>
                    <dt>.have_data</dt>
                    <dd>Ronde notificatiebadge (accent kleur, wit getal)</dd>
                    <dt>.have_data.loading</dt>
                    <dd>Pulserende animatie voor laden</dd>
                </dl>
                <h4>Notities</h4>
                <dl>
                    <dt>lib-label</dt>
                    <dd>Pure CSS, geen JavaScript nodig. Verbergt zichzelf als leeg (<code>:empty</code>)</dd>
                    <dt>.badge</dt>
                    <dd>Gebruikt CSS variabelen (<code>--color-success</code>, etc.) voor thema-integratie</dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="form-controls">
        <div class="component-header">
            <h2>Formuliervelden</h2>
            <span class="tag">Ontwerp</span>
            <p class="component-description">Standaard stijlen voor formulierinvoer. Verplichte velden tonen een rode linkerborder (3px) die verandert naar standaard kleur wanneer gevuld.</p>
        </div>
        <div class="component-body">
            <cma-tabs tabs='["Standaard elementen", "Webcomponenten"]'>
                <!-- Tab 0: Standaard HTML elementen -->
                <div slot="tab-0">
                    <table class="form-controls-table" style="width: 100%; border-collapse: collapse; background: var(--bg-surface, #fff);">
                        <thead>
                            <tr style="background: var(--bg-header);">
                                <th style="padding: 10px; text-align: left; border: 1px solid var(--border-color); width: 150px;">Veldtype</th>
                                <th style="padding: 10px; text-align: left; border: 1px solid var(--border-color);">Verplicht (leeg)</th>
                                <th style="padding: 10px; text-align: left; border: 1px solid var(--border-color);">Normaal</th>
                                <th style="padding: 10px; text-align: left; border: 1px solid var(--border-color);">Readonly</th>
                                <th style="padding: 10px; text-align: left; border: 1px solid var(--border-color);">Disabled</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 10px; border: 1px solid var(--border-color); font-weight: 500;">Tekstveld</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><input type="text" class="form-control" data-required="true" required placeholder="Verplicht..." style="width: 150px;"></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><input type="text" class="form-control" placeholder="Tekst invoeren..." style="width: 150px;"></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><input type="text" class="form-control" value="Readonly waarde" readonly style="width: 150px;"></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><input type="text" class="form-control" value="Disabled waarde" disabled style="width: 150px;"></td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid var(--border-color); font-weight: 500;">E-mail</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><input type="email" class="form-control" data-required="true" required placeholder="Verplicht..." style="width: 150px;"></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><input type="email" class="form-control" placeholder="naam@domein.nl" style="width: 150px;"></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><input type="email" class="form-control" value="jan@voorbeeld.nl" readonly style="width: 150px;"></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><input type="email" class="form-control" value="jan@voorbeeld.nl" disabled style="width: 150px;"></td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid var(--border-color); font-weight: 500;">Keuzelijst</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">
                                    <select class="form-control" data-required="true" required style="width: 150px;">
                                        <option value="">-- Kies --</option>
                                        <option value="1">Optie 1</option>
                                        <option value="2">Optie 2</option>
                                    </select>
                                </td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">
                                    <select class="form-control" style="width: 150px;">
                                        <option>Optie 1</option>
                                        <option>Optie 2</option>
                                        <option>Optie 3</option>
                                    </select>
                                </td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><input type="text" class="form-control" value="Optie 2" readonly style="width: 150px;"></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">
                                    <select class="form-control" style="width: 150px;" disabled>
                                        <option>Optie 2</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid var(--border-color); font-weight: 500;">Selectievakje</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">
                                    <div class="checkboxcontrolgroup" data-required="true">
                                        <label><input type="checkbox"> Niet aangevinkt</label>
                                        <label><input type="checkbox"> Aangevinkt</label>
                                    </div>
                                </td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">
                                    <div class="checkboxcontrolgroup">
                                        <label><input type="checkbox"> Niet aangevinkt</label>
                                        <label><input type="checkbox" checked> Aangevinkt</label>
                                    </div>
                                </td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">
                                    <div class="checkboxcontrolgroup">
                                        <label><input type="checkbox" onclick="return false;"> Niet aangevinkt</label>
                                        <label><input type="checkbox" checked onclick="return false;"> Aangevinkt</label>
                                    </div>
                                </td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">
                                    <div class="checkboxcontrolgroup">
                                        <label><input type="checkbox" disabled> Niet aangevinkt</label>
                                        <label><input type="checkbox" checked disabled> Aangevinkt</label>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid var(--border-color); font-weight: 500;">Keuzerondje</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">
                                    <div class="radiocontrolgroup" data-required="true" style="padding: 8px;">
                                        <label><input type="radio" name="demo-radio-req"> Keuze 1</label>
                                        <label><input type="radio" name="demo-radio-req"> Keuze 2</label>
                                    </div>
                                </td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">
                                    <label><input type="radio" name="demo-radio-normal"> Keuze 1</label><br>
                                    <label><input type="radio" name="demo-radio-normal" checked> Keuze 2</label>
                                </td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">
                                    <label><input type="radio" name="demo-radio-readonly" onclick="return false;"> Keuze 1</label><br>
                                    <label><input type="radio" name="demo-radio-readonly" checked onclick="return false;"> Keuze 2</label>
                                </td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">
                                    <label><input type="radio" name="demo-radio-disabled" disabled> Keuze 1</label><br>
                                    <label><input type="radio" name="demo-radio-disabled" checked disabled> Keuze 2</label>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid var(--border-color); font-weight: 500;">Tekstvak</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><textarea class="form-control" data-required="true" required rows="2" style="width: 150px;" placeholder="Verplicht..."></textarea></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><textarea class="form-control" rows="2" style="width: 150px;" placeholder="Meerdere regels..."></textarea></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><textarea class="form-control" rows="2" style="width: 150px;" readonly>Readonly tekst over meerdere regels</textarea></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><textarea class="form-control" rows="2" style="width: 150px;" disabled>Disabled tekst</textarea></td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid var(--border-color); font-weight: 500;">Bestand</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><input type="file" class="form-control" data-required="true" required style="width: 150px;"></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><input type="file" class="form-control" style="width: 150px;"></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><input type="text" class="form-control" value="document.pdf" readonly style="width: 150px;"></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><input type="file" class="form-control" style="width: 150px;" disabled></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Tab 1: Webcomponenten -->
                <div slot="tab-1">
                    <table class="form-controls-table" style="width: 100%; border-collapse: collapse; background: var(--bg-surface, #fff);">
                        <thead>
                            <tr style="background: var(--bg-header);">
                                <th style="padding: 10px; text-align: left; border: 1px solid var(--border-color); width: 150px;">Veldtype</th>
                                <th style="padding: 10px; text-align: left; border: 1px solid var(--border-color);">Verplicht (leeg)</th>
                                <th style="padding: 10px; text-align: left; border: 1px solid var(--border-color);">Normaal</th>
                                <th style="padding: 10px; text-align: left; border: 1px solid var(--border-color);">Readonly</th>
                                <th style="padding: 10px; text-align: left; border: 1px solid var(--border-color);">Disabled</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 10px; border: 1px solid var(--border-color); font-weight: 500;">lib-combo</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><lib-combo name="combo-req" placeholder="Verplicht..." data-required="true" required style="width: 150px;"></lib-combo></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><lib-combo name="combo-normal" placeholder="Selecteer..." value="2" style="width: 150px;"></lib-combo></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><lib-combo name="combo-readonly" value="1" readonly style="width: 150px;"></lib-combo></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><lib-combo name="combo-disabled" placeholder="Niet beschikbaar" disabled style="width: 150px;"></lib-combo></td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid var(--border-color); font-weight: 500;">lib-switch</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><lib-switch name="switch-req" data-required="true"></lib-switch></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><lib-switch name="switch-normal" checked></lib-switch></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><lib-switch name="switch-readonly" readonly checked></lib-switch></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><lib-switch name="switch-disabled" disabled></lib-switch></td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid var(--border-color); font-weight: 500;">lib-datepicker</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><lib-datepicker data-required="true" required></lib-datepicker></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><lib-datepicker value="2024-01-15"></lib-datepicker></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><lib-datepicker value="2024-01-15" readonly></lib-datepicker></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><lib-datepicker value="2024-01-15" disabled></lib-datepicker></td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid var(--border-color); font-weight: 500;">lib-timepicker</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><lib-timepicker data-required="true" required></lib-timepicker></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><lib-timepicker value="14:30"></lib-timepicker></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><lib-timepicker value="14:30" readonly></lib-timepicker></td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);"><lib-timepicker value="14:30" disabled></lib-timepicker></td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid var(--border-color); font-weight: 500;">cma-htmledit</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">
                                    <textarea id="ckeditor-req-empty" data-required="true" required style="width: 150px; height: 80px;"></textarea>
                                </td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">
                                    <textarea id="ckeditor-normal" style="width: 150px; height: 80px;">Dit is tekst in de editor.</textarea>
                                </td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">
                                    <textarea id="ckeditor-readonly" style="width: 150px; height: 80px;">Readonly tekst</textarea>
                                </td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">
                                    <textarea id="ckeditor-disabled" style="width: 150px; height: 80px;">Disabled tekst</textarea>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <script>
                    (function() {
                        // CKEditor loaded in blockeditor section
                        if (typeof CKEDITOR === 'undefined' || typeof CMA === 'undefined' || !CMA.initCKEditor) return;

                        // Required empty
                        CMA.initCKEditor('ckeditor-req-empty', { height: 80 });

                        // Normal with content
                        CMA.initCKEditor('ckeditor-normal', { height: 80 });

                        // Readonly
                        CMA.initCKEditor('ckeditor-readonly', { height: 80, readOnly: true });

                        // Disabled (simulate with readonly + opacity)
                        var disabledEditor = CMA.initCKEditor('ckeditor-disabled', { height: 80, readOnly: true });
                        if (disabledEditor) {
                            disabledEditor.on('instanceReady', function() {
                                var container = document.getElementById('cke_ckeditor-disabled');
                                if (container) {
                                    container.style.opacity = '0.6';
                                }
                            });
                        }
                    })();
                    </script>
                    <script>
                    // Initialize lib-combo demo options in form-controls table
                    (function() {
                        var demoOptions = [
                            { value: '1', label: 'Optie 1' },
                            { value: '2', label: 'Optie 2' },
                            { value: '3', label: 'Optie 3' }
                        ];
                        customElements.whenDefined('lib-combo').then(function() {
                            document.querySelectorAll('#form-controls .form-controls-table lib-combo').forEach(function(combo) {
                                requestAnimationFrame(function() {
                                    if (typeof combo.setOptions === 'function') {
                                        combo.setOptions(demoOptions);
                                    }
                                });
                            });
                        });
                    })();
                    </script>
                </div>
            </cma-tabs>
        </div>
    </section>

    <section class="component-section" id="colors">
        <div class="component-header">
            <h2>Kleuren</h2>
            <span class="tag">Ontwerp</span>
            <p class="component-description">CSS variabelen voor licht/donker modus.</p>
        </div>
        <div class="component-body">
            <!-- Primaire kleuren -->
            <h3 style="margin: 20px 0 10px 0; color: var(--heading-color);">Primaire kleuren</h3>
            <p class="color-description">Hoofdkleuren voor links, knoppen en interactieve elementen.</p>
            <div class="component-example">
                <div class="color-grid">
                    <div class="color-swatch" style="background: var(--color-primary);">--color-primary</div>
                    <div class="color-swatch" style="background: var(--color-primary-hover);">--color-primary-hover</div>
                    <div class="color-swatch" style="background: var(--color-primary-dark);">--color-primary-dark</div>
                    <div class="color-swatch" style="background: var(--color-primary-light); color: var(--text-primary);">--color-primary-light</div>
                    <div class="color-swatch" style="background: var(--color-accent);">--color-accent</div>
                    <div class="color-swatch" style="background: var(--color-accent-hover);">--color-accent-hover</div>
                    <div class="color-swatch" style="background: var(--color-danger);">--color-danger</div>
                    <div class="color-swatch" style="background: var(--color-danger-hover);">--color-danger-hover</div>
                </div>
            </div>

            <!-- Statuskleuren -->
            <h3 style="margin: 20px 0 10px 0; color: var(--heading-color);">Statuskleuren</h3>
            <p class="color-description">Semantische kleuren voor succes, fout, waarschuwing en info. Elk heeft bg, text en border varianten.</p>
            <div class="component-example">
                <div class="color-grid">
                    <div class="color-swatch" style="background: var(--color-success);">--color-success</div>
                    <div class="color-swatch" style="background: var(--color-success-bg); color: var(--color-success-text); border: 1px solid var(--color-success-border);">--color-success-bg</div>
                    <div class="color-swatch" style="background: var(--color-error);">--color-error</div>
                    <div class="color-swatch" style="background: var(--color-error-bg); color: var(--color-error-text); border: 1px solid var(--color-error-border);">--color-error-bg</div>
                    <div class="color-swatch" style="background: var(--color-warning); color: #333;">--color-warning</div>
                    <div class="color-swatch" style="background: var(--color-warning-bg); color: var(--color-warning-text); border: 1px solid var(--color-warning-border);">--color-warning-bg</div>
                    <div class="color-swatch" style="background: var(--color-info);">--color-info</div>
                    <div class="color-swatch" style="background: var(--color-info-bg); color: var(--color-info-text); border: 1px solid var(--color-info-border);">--color-info-bg</div>
                </div>
            </div>

            <!-- Label/badge kleuren -->
            <h3 style="margin: 20px 0 10px 0; color: var(--heading-color);">Label/badge kleuren</h3>
            <p class="color-description">Verloopkleuren voor statuslabels in lib-components.</p>
            <div class="component-example">
                <div class="color-grid">
                    <div class="color-swatch" style="background: var(--label-info-bg); color: var(--label-info-text);">--label-info-bg</div>
                    <div class="color-swatch" style="background: var(--label-warning-bg); color: var(--label-warning-text);">--label-warning-bg</div>
                    <div class="color-swatch" style="background: var(--label-error-bg); color: var(--label-error-text);">--label-error-bg</div>
                    <div class="color-swatch" style="background: var(--label-success-bg); color: var(--label-success-text);">--label-success-bg</div>
                </div>
            </div>

            <!-- Achtergrondkleuren -->
            <h3 style="margin: 20px 0 10px 0; color: var(--heading-color);">Achtergrondkleuren</h3>
            <p class="color-description">Achtergrondkleuren voor verschillende UI-lagen: body, oppervlakken, kaarten, invoervelden.</p>
            <div class="component-example">
                <div class="color-grid">
                    <div class="color-swatch" style="background: var(--bg-body); color: var(--text-primary); border: 1px solid var(--border-color);">--bg-body</div>
                    <div class="color-swatch" style="background: var(--bg-content); color: var(--text-primary); border: 1px solid var(--border-color);">--bg-content</div>
                    <div class="color-swatch" style="background: var(--bg-surface); color: var(--text-primary); border: 1px solid var(--border-color);">--bg-surface</div>
                    <div class="color-swatch" style="background: var(--bg-surface-alt); color: var(--text-primary); border: 1px solid var(--border-color);">--bg-surface-alt</div>
                    <div class="color-swatch" style="background: var(--bg-card); color: var(--text-primary); border: 1px solid var(--border-color);">--bg-card</div>
                    <div class="color-swatch" style="background: var(--bg-hover); color: var(--text-primary); border: 1px solid var(--border-color);">--bg-hover</div>
                    <div class="color-swatch" style="background: var(--bg-input); color: var(--text-primary); border: 1px solid var(--border-color);">--bg-input</div>
                    <div class="color-swatch" style="background: var(--bg-disabled); color: var(--text-disabled); border: 1px solid var(--border-color);">--bg-disabled</div>
                    <div class="color-swatch" style="background: var(--bg-dropdown); color: var(--text-primary); border: 1px solid var(--border-color);">--bg-dropdown</div>
                </div>
            </div>

            <!-- Knopachtergronden -->
            <h3 style="margin: 20px 0 10px 0; color: var(--heading-color);">Knopachtergronden</h3>
            <p class="color-description">Achtergrondkleuren voor de vier knoptypes. Elke knop heeft 3 states: standaard, hover en active.</p>
            <div class="component-example">
                <div class="color-grid">
                    <div class="color-swatch" style="background: var(--bg-button-primary); color: #fff;">--bg-button-primary</div>
                    <div class="color-swatch" style="background: var(--bg-button-primary-hover); color: #fff;">--bg-button-primary-hover</div>
                    <div class="color-swatch" style="background: var(--bg-button-primary-active); color: #fff;">--bg-button-primary-active</div>
                    <div class="color-swatch" style="background: var(--bg-button-secondary); color: #fff;">--bg-button-secondary</div>
                    <div class="color-swatch" style="background: var(--bg-button-secondary-hover); color: #fff;">--bg-button-secondary-hover</div>
                    <div class="color-swatch" style="background: var(--bg-button-secondary-active); color: #fff;">--bg-button-secondary-active</div>
                    <div class="color-swatch" style="background: var(--bg-button-cancel); color: var(--text-primary);">--bg-button-cancel</div>
                    <div class="color-swatch" style="background: var(--bg-button-cancel-hover); color: var(--text-primary);">--bg-button-cancel-hover</div>
                    <div class="color-swatch" style="background: var(--bg-button-cancel-active); color: var(--text-primary);">--bg-button-cancel-active</div>
                    <div class="color-swatch" style="background: var(--bg-button-success); color: #fff;">--bg-button-success</div>
                    <div class="color-swatch" style="background: var(--bg-button-success-hover); color: #fff;">--bg-button-success-hover</div>
                    <div class="color-swatch" style="background: var(--bg-button-success-active); color: #fff;">--bg-button-success-active</div>
                </div>
                <div style="display: flex; gap: 15px; margin-top: 15px;">
                    <button class="btn btn-primary">Primair</button>
                    <button class="btn btn-secondary">Secundair</button>
                    <button class="btn btn-cancel">Annuleren</button>
                    <button class="btn btn-success">Succes</button>
                </div>
            </div>

            <!-- Tekstkleuren -->
            <h3 style="margin: 20px 0 10px 0; color: var(--heading-color);">Tekstkleuren</h3>
            <p class="color-description">Tekstkleuren voor primaire, secundaire en gedempte content. Inclusief linkkleuren.</p>
            <div class="component-example">
                <div class="color-grid color-grid-text">
                    <div class="color-swatch-text"><span style="color: var(--text-primary);">--text-primary</span><span class="hex-val"><span class="hex-light">#333</span><span class="hex-dark">#dedede</span></span></div>
                    <div class="color-swatch-text"><span style="color: var(--text-secondary);">--text-secondary</span><span class="hex-val"><span class="hex-light">#666</span><span class="hex-dark">#aaa</span></span></div>
                    <div class="color-swatch-text"><span style="color: var(--text-muted);">--text-muted</span><span class="hex-val"><span class="hex-light">#999</span><span class="hex-dark">#888</span></span></div>
                    <div class="color-swatch-text"><span style="color: var(--text-disabled);">--text-disabled</span><span class="hex-val"><span class="hex-light">#ccc</span><span class="hex-dark">#555</span></span></div>
                    <div class="color-swatch-text"><span style="background: var(--color-primary); padding: 2px 6px; color: var(--text-inverse);">--text-inverse</span><span class="hex-val"><span class="hex-light">#fff</span><span class="hex-dark">#1a1a1a</span></span></div>
                    <div class="color-swatch-text"><span style="color: var(--text-link);">--text-link</span><span class="hex-val"><span class="hex-light">#204496</span><span class="hex-dark">#5a8dee</span></span></div>
                    <div class="color-swatch-text"><span style="color: var(--text-link-hover);">--text-link-hover</span><span class="hex-val"><span class="hex-light">#077ab2</span><span class="hex-dark">#ff7a1a</span></span></div>
                    <div class="color-swatch-text"><span style="color: var(--heading-color);">--heading-color</span><span class="hex-val"><span class="hex-light">#669</span><span class="hex-dark">#88b</span></span></div>
                </div>
            </div>

            <!-- Tabelkleuren -->
            <h3 style="margin: 20px 0 10px 0; color: var(--heading-color);">Tabelkleuren</h3>
            <p class="color-description">Kleuren voor tabelkoppen, afwisselende rijen, hover en actieve toestanden.</p>
            <div class="component-example">
                <div class="color-grid">
                    <div class="color-swatch" style="background: var(--table-header-bg); color: var(--text-primary); border: 1px solid var(--border-color);">--table-header-bg</div>
                    <div class="color-swatch" style="background: var(--table-row-odd); color: var(--text-primary); border: 1px solid var(--border-color);">--table-row-odd</div>
                    <div class="color-swatch" style="background: var(--table-row-even); color: var(--text-primary); border: 1px solid var(--border-color);">--table-row-even</div>
                    <div class="color-swatch" style="background: var(--table-row-hover); color: var(--text-primary); border: 1px solid var(--border-color);">--table-row-hover</div>
                    <div class="color-swatch" style="background: var(--table-row-active); color: var(--text-primary); border: 1px solid var(--border-color);">--table-row-active</div>
                    <div class="color-swatch" style="background: var(--table-border); height: 40px;">--table-border</div>
                </div>
            </div>

            <!-- Boom/menu kleuren -->
            <h3 style="margin: 20px 0 10px 0; color: var(--heading-color);">Boom/menu kleuren</h3>
            <p class="color-description">Kleuren voor boomnavigatie items en menu's.</p>
            <div class="component-example">
                <div class="color-grid">
                    <div class="color-swatch" style="background: var(--tree-hover-bg); color: var(--text-primary); border: 1px solid var(--border-color);">--tree-hover-bg</div>
                    <div class="color-swatch" style="background: var(--tree-active-bg); color: var(--tree-active-text);">--tree-active-bg</div>
                    <div class="color-swatch" style="background: var(--tree-active-bg); color: var(--tree-active-text); border: 2px solid var(--tree-active-bordercolor);">--tree-active-bordercolor</div>
                    <div class="color-swatch" style="background: var(--tab-bar-bg); color: var(--text-primary); border: 1px solid var(--border-color);">--tab-bar-bg</div>
                </div>
            </div>

            <!-- Popup/dialoog kleuren -->
            <h3 style="margin: 20px 0 10px 0; color: var(--heading-color);">Popup/dialoog kleuren</h3>
            <p class="color-description">Kleuren voor dialoogvensters, zijpanelen en overlays.</p>
            <div class="component-example">
                <div class="color-grid">
                    <div class="color-swatch" style="background: var(--popup-bg); color: var(--popup-text); border: 1px solid var(--popup-border);">--popup-bg</div>
                    <div class="color-swatch" style="background: var(--popup-caption-bg); color: var(--popup-caption-text); border: 1px solid var(--popup-border);">--popup-caption-bg</div>
                    <div class="color-swatch" style="background: var(--popup-close-hover);">--popup-close-hover</div>
                </div>
            </div>

            <!-- Werkbalkkleuren -->
            <h3 style="margin: 20px 0 10px 0; color: var(--heading-color);">Werkbalkkleuren</h3>
            <p class="color-description">Kleuren voor werkbalken en actiebalken.</p>
            <div class="component-example">
                <div class="color-grid">
                    <div class="color-swatch" style="background: var(--toolbar-bg); color: var(--text-primary); border: 1px solid var(--toolbar-border);">--toolbar-bg</div>
                    <div class="color-swatch" style="background: var(--toolbar-border); color: var(--text-inverse);">--toolbar-border</div>
                </div>
            </div>

            <!-- Groepskopkleuren -->
            <h3 style="margin: 20px 0 10px 0; color: var(--heading-color);">Groepskopkleuren</h3>
            <p class="color-description">Verloopkleuren voor groepskoppen.</p>
            <div class="component-example">
                <div class="color-grid">
                    <div class="color-swatch" style="background: var(--bg-header); color: var(--text-primary); border: 1px solid var(--border-color);">--bg-header</div>
                    <div class="color-swatch" style="background: var(--bg-header-end); color: var(--text-primary); border: 1px solid var(--border-color);">--bg-header-end</div>
                    <div class="color-swatch" style="background: var(--bg-header-hover); color: var(--text-primary); border: 1px solid var(--border-color);">--bg-header-hover</div>
                </div>
            </div>

            <!-- Schaduwen -->
            <h3 style="margin: 20px 0 10px 0; color: var(--heading-color);">Schaduwen</h3>
            <p class="color-description">Box shadow presets voor verschillende elevatieniveaus.</p>
            <div class="component-example">
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <div style="width: 100px; height: 60px; background: var(--bg-surface); box-shadow: var(--shadow-md); display: flex; align-items: center; justify-content: center; font-size: var(--font-size-xs);">--shadow-md</div>
                    <div style="width: 100px; height: 60px; background: var(--bg-surface); box-shadow: var(--shadow-lg); display: flex; align-items: center; justify-content: center; font-size: var(--font-size-xs);">--shadow-lg</div>
                </div>
            </div>

            <div class="code-block">/* CSS variabelen gebruiken */
background-color: var(--bg-surface);
color: var(--text-primary);
border: 1px solid var(--border-color);
box-shadow: var(--shadow-md);</div>
        </div>
    </section>

    <section class="component-section" id="buttons">
        <div class="component-header">
            <h2>Knoppen</h2>
            <span class="tag">Ontwerp</span>
            <p class="component-description">Standaard knopstijlen voor consistente UI.</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="playground">
                    <textarea><div class="demo-row">
    <span class="demo-label">Primair:</span>
    <button class="btn btn-primary">
        <span class="lnr lnr-checkmark-circle"></span> Opslaan
    </button>
    <button class="btn btn-primary" disabled>
        <span class="lnr lnr-checkmark-circle"></span> Disabled
    </button>
</div>
<div class="demo-row">
    <span class="demo-label">Secundair:</span>
    <button class="btn btn-secondary">
        <span class="lnr lnr-cog"></span> Instellingen
    </button>
    <button class="btn btn-secondary" disabled>
        <span class="lnr lnr-cog"></span> Disabled
    </button>
</div>
<div class="demo-row">
    <span class="demo-label">Annuleren:</span>
    <button class="btn btn-cancel">
        <span class="lnr lnr-cross"></span> Annuleren
    </button>
    <button class="btn btn-cancel" disabled>
        <span class="lnr lnr-cross"></span> Disabled
    </button>
</div>
<div class="demo-row">
    <span class="demo-label">Succes:</span>
    <button class="btn btn-success">
        <span class="lnr lnr-checkmark-circle"></span> Succes
    </button>
    <button class="btn btn-success" disabled>
        <span class="lnr lnr-checkmark-circle"></span> Disabled
    </button>
</div>
<div class="demo-row">
    <span class="demo-label">Standaard:</span>
    <button class="btn">Standaard knop</button>
    <button class="btn" disabled>
        <span class="lnr lnr-lock"></span> Disabled
    </button>
</div>
<div class="demo-row" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border-light);">
    <span class="demo-label">Active state:</span>
    <span style="font-size: var(--font-size-sm); color: var(--text-muted);">Klik en houd ingedrukt om het :active effect te zien (inset schaduw + donkerder achtergrond)</span>
</div></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>CSS Classes</h4>
                <dl>
                    <dt>.btn</dt>
                    <dd>Basis knopstijl (verplicht)</dd>
                    <dt>.btn-primary</dt>
                    <dd>Primaire actie (--color-primary)</dd>
                    <dt>.btn-secondary</dt>
                    <dd>Secundaire actie (--color-primary)</dd>
                    <dt>.btn-cancel</dt>
                    <dd>Annuleer actie (grijs)</dd>
                    <dt>.btn-success</dt>
                    <dd>Succes actie (groen)</dd>
                    <dt>.btn-sm</dt>
                    <dd>Kleinere variant</dd>
                </dl>
                <h4>Attributen</h4>
                <dl>
                    <dt>disabled</dt>
                    <dd>Schakel knop uit</dd>
                    <dt>type</dt>
                    <dd>button | submit | reset</dd>
                </dl>
                <h4>States</h4>
                <dl>
                    <dt>:hover</dt>
                    <dd>Donkerder achtergrond</dd>
                    <dt>:active</dt>
                    <dd>Inset schaduw + donkerste achtergrond</dd>
                    <dt>:disabled</dt>
                    <dd>Verlaagde opacity, geen hover/active</dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="linearicons">
        <div class="component-header">
            <h2>Linearicons</h2>
        </div>
        <div class="component-body">
            <div class="component-content">
                <!-- Description skipped for cleaner layout -->

                <div class="icon-search-container" style="margin-bottom: 20px;">
                    <lib-search-input id="iconSearch" placeholder="Zoek icoon..." style="max-width: 400px;"></lib-search-input>
                    <div id="iconSearchResults" style="display: none; margin-top: 15px;">
                        <h3>Zoekresultaten (<span id="searchResultCount">0</span>)</h3>
                        <div class="icons-grid" id="iconsGridSearch"></div>
                    </div>
                </div>

                <div id="iconDefaultView">
                <div class="component-demo">
                    <h3>Iconen in gebruik (<span id="usedIconCount">0</span>)</h3>
                    <p style="font-size: var(--font-size-sm); color: var(--text-muted); margin-bottom: 10px;">Deze iconen zijn gedefinieerd in style.css en kunnen direct gebruikt worden.</p>
                    <div class="icons-grid" id="iconsGridUsed">
                        <!-- Gegenereerd door JavaScript -->
                    </div>
                </div>

                <details class="icons-collapsible" style="margin-top: 30px;">
                    <summary>Alle overige iconen (<span id="remainingIconCount">0</span>)</summary>
                    <p style="font-size: var(--font-size-sm); color: var(--text-muted); margin: 10px 0;">Deze iconen zijn beschikbaar in het lettertype maar moeten eerst aan style.css worden toegevoegd voordat ze werken.</p>
                    <div class="icons-grid" id="iconsGridAll">
                        <!-- Gegenereerd door JavaScript -->
                    </div>
                </details>
                </div><!-- /iconDefaultView -->

            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>class="lnr lnr-{naam}"</dt>
                    <dd>CSS classes voor het icoon</dd>
                </dl>
                <h4>Notities</h4>
                <dl>
                    <dt>Font</dt>
                    <dd>Linearicons font (1002 iconen). Productie gebruikt een geoptimaliseerde subset (~138 iconen). Storybook laadt het volledige lettertype.</dd>
                    <dt>In gebruik</dt>
                    <dd>Iconen gedefinieerd in shared-icons.js en style.css</dd>
                    <dt>Toevoegen</dt>
                    <dd>Klik op de <strong>+</strong> knop bij een icoon, of handmatig toevoegen aan shared-icons.js en style.css. Daarna: <code>python3 tools/build-icon-font.py</code></dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="table-styling">
        <div class="component-header">
            <h2>Tabelstijlen</h2>
            <span class="tag">Ontwerp</span>
            <p class="component-description">CSS klassen en patronen voor <code>lib-table</code> en <code>.listtable</code>.</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <h4>Voorbeeld</h4>
                <table class="listtable" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Naam</th>
                            <th data-type="number">Aantal</th>
                            <th data-type="date">Datum</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="listrow odd"><td>Project Alpha</td><td data-type="number">42</td><td data-type="date">15-03-2026</td><td><span class="badge badge-success">Actief</span></td></tr>
                        <tr class="listrow even"><td>Project Beta</td><td data-type="number">128</td><td data-type="date">10-02-2026</td><td><span class="badge badge-warning">In afwachting</span></td></tr>
                        <tr class="listrow odd active"><td>Project Gamma (actief)</td><td data-type="number">7</td><td data-type="date">01-03-2026</td><td><span class="badge badge-info">Nieuw</span></td></tr>
                        <tr class="listrow even"><td>Project Delta</td><td data-type="number">0</td><td data-type="date">28-01-2026</td><td><span class="badge badge-error">Gestopt</span></td></tr>
                    </tbody>
                </table>

                <h4 style="margin-top: 20px;">Rij states</h4>
                <table style="width: 100%; border-collapse: collapse; font-size: var(--font-size-sm);">
                    <tr><td style="padding: 4px 8px;"><code>.listrow</code></td><td style="padding: 4px;">Klikbare rij (cursor: pointer)</td></tr>
                    <tr><td style="padding: 4px 8px;"><code>.listrow:hover</code></td><td style="padding: 4px;">Blauwe outline, afgeronde hoeken</td></tr>
                    <tr><td style="padding: 4px 8px;"><code>.listrow.active</code></td><td style="padding: 4px;">Geselecteerde rij (donker, inverse tekst)</td></tr>
                    <tr><td style="padding: 4px 8px;"><code>.odd</code> / <code>.even</code></td><td style="padding: 4px;">Zebra-striping (#f6f6f6 / #fdfdfd)</td></tr>
                    <tr><td style="padding: 4px 8px;"><code>.editing-row</code></td><td style="padding: 4px;">Gele achtergrond bij inline bewerken</td></tr>
                </table>
            </div>
            <div class="component-options">
                <h4>Tabel klassen</h4>
                <dl>
                    <dt>.listtable</dt>
                    <dd>Basistafel. Wordt automatisch toegevoegd door <code>lib-table</code></dd>
                    <dt>.listrow</dt>
                    <dd>Klikbare datarij met hover-effect</dd>
                    <dt>.filter-row</dt>
                    <dd>Rij met filterinvoer onder de header</dd>
                    <dt>.filter-input</dt>
                    <dd>Invoerveld in filterrij</dd>
                </dl>
                <h4>Header klassen</h4>
                <dl>
                    <dt>.sortheader</dt>
                    <dd>Sorteerbare kolomheader (flex layout)</dd>
                    <dt>.sorttable_sorted</dt>
                    <dd>Oplopend gesorteerde kolom (&#x25BC; indicator)</dd>
                    <dt>.sorttable_sorted_reverse</dt>
                    <dd>Aflopend gesorteerde kolom (&#x25B2; indicator)</dd>
                    <dt>.th-header-wrapper</dt>
                    <dd>Flex container in header (label + filter + menu)</dd>
                </dl>
                <h4>Kolom data-types</h4>
                <dl>
                    <dt>data-type="number"</dt>
                    <dd>Rechts uitgelijnd, tabular-nums</dd>
                    <dt>data-type="date"</dt>
                    <dd>Geen woordafbreking (nowrap)</dd>
                    <dt>data-type="boolean"</dt>
                    <dd>Geen woordafbreking</dd>
                    <dt>data-type="currency"</dt>
                    <dd>Rechts uitgelijnd</dd>
                </dl>
                <h4>Interactieve features</h4>
                <dl>
                    <dt>.column-resize-handle</dt>
                    <dd>Sleep-handvat voor kolombreedte</dd>
                    <dt>th[draggable]</dt>
                    <dd>Kolommen herschikken via drag &amp; drop</dd>
                    <dt>.row-menu-trigger</dt>
                    <dd>Kebab-menu per rij (verschijnt bij hover)</dd>
                    <dt>.menutrigger</dt>
                    <dd>3-punts menu in header (radial-gradient dots)</dd>
                </dl>
                <h4>CSS variabelen</h4>
                <dl>
                    <dt>--table-header-bg</dt>
                    <dd>Header achtergrond (default: <code>#e8ecf0</code>)</dd>
                    <dt>--table-row-hover</dt>
                    <dd>Rij hover achtergrond</dd>
                    <dt>--table-border</dt>
                    <dd>Tabelrand kleur</dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="tooltips">
        <div class="component-header">
            <h2>Tooltips</h2>
            <span class="tag">Ontwerp</span>
            <p class="component-description">Tooltip via data-tooltip attribuut.</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div class="component-example">
                    <h3>Posities</h3>
                    <div style="display: flex; gap: 30px; flex-wrap: wrap; padding: 40px 20px;">
                        <button class="btn" data-tooltip="Tooltip onderaan (standaard)">Bottom</button>
                        <button class="btn" data-tooltip="Tooltip bovenaan" data-tooltip-pos="top">Top</button>
                        <button class="btn" data-tooltip="Tooltip rechts" data-tooltip-pos="right">Right</button>
                        <button class="btn" data-tooltip="Tooltip links" data-tooltip-pos="left">Left</button>
                    </div>
                </div>

                <div class="component-example">
                    <h3>Op verschillende elementen</h3>
                    <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: center; padding: 30px 20px;">
                        <span class="lnr lnr-cog" data-tooltip="Instellingen" style="font-size: var(--font-size-2xl); cursor: pointer;"></span>
                        <a href="#" data-tooltip="Link met tooltip" onclick="return false;">Link</a>
                        <span data-tooltip="Tekst met tooltip" style="border-bottom: 1px dotted; cursor: help;">Hover mij</span>
                    </div>
                </div>

                <div class="component-example">
                    <h3>Op invoervelden (JS-enhanced)</h3>
                    <p style="font-size: var(--font-size-sm); color: var(--text-muted); margin-bottom: 15px;">Input, select en textarea elementen krijgen automatisch een JavaScript tooltip (CSS pseudo-elementen werken niet op replaced elements).</p>
                    <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: center; padding: 20px;">
                        <input type="text" data-tooltip="Dit is een tekstveld" placeholder="Tekstveld met tooltip" class="form-control" style="width: 200px;">
                        <select data-tooltip="Selecteer een optie" class="form-control" style="width: 150px;">
                            <option>Optie 1</option>
                            <option>Optie 2</option>
                        </select>
                        <input type="text" data-tooltip="Tooltip rechts" data-tooltip-pos="right" placeholder="Positie: right" class="form-control" style="width: 150px;">
                    </div>
                </div>

                <div class="component-example">
                    <h3>In container met overflow:hidden (JS-enhanced)</h3>
                    <p style="font-size: var(--font-size-sm); color: var(--text-muted); margin-bottom: 15px;">Tooltips in containers met overflow:hidden worden automatisch via JavaScript met position:fixed getoond, zodat ze niet worden afgeknipt.</p>
                    <div style="overflow: hidden; border: 2px dashed var(--border-color); padding: 20px; border-radius: 4px;">
                        <span style="font-size: var(--font-size-xs); color: var(--text-muted); display: block; margin-bottom: 10px;">Container met overflow:hidden</span>
                        <button class="btn" data-tooltip="Deze tooltip wordt niet afgeknipt!">Hover mij</button>
                        <button class="btn" data-tooltip="Tooltip bovenaan" data-tooltip-pos="top" style="margin-left: 10px;">Top positie</button>
                    </div>
                </div>

                <div class="playground">
                    <textarea><button class="btn btn-primary" data-tooltip="Primaire actie tooltip">
    Hover voor tooltip
</button>

<!-- Op invoervelden -->
<input type="text" data-tooltip="Veld tooltip" placeholder="Input met tooltip" class="form-control" style="width: 200px; margin-top: 10px;"></textarea>
                </div>
            </div>
            <div class="component-options">
                <h4>Attributen</h4>
                <dl>
                    <dt>data-tooltip</dt>
                    <dd>Tooltip tekst (verplicht)</dd>
                    <dt>data-tooltip-pos</dt>
                    <dd>Positie: <code>top</code>, <code>left</code>, <code>right</code>, <code>bottom</code> (default: <code>"bottom"</code>)</dd>
                </dl>
                <h4>Automatische JS-fallback</h4>
                <dl>
                    <dt>Input/select/textarea</dt>
                    <dd>Automatisch JS tooltip (CSS pseudo-elementen niet mogelijk)</dd>
                    <dt>overflow:hidden containers</dt>
                    <dd>Automatisch position:fixed tooltip om clipping te voorkomen</dd>
                </dl>
            </div>
        </div>
    </section>

    <section class="component-section" id="typography">
        <div class="component-header">
            <h2>Typografie</h2>
            <span class="tag">Ontwerp</span>
            <p class="component-description">Tekststijlen, lettertype schaal en design tokens.</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <h4>Lettertype schaal</h4>
                <table style="width: 100%; border-collapse: collapse; font-size: var(--font-size-sm);">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <th style="text-align: left; padding: 6px;">Variabele</th>
                            <th style="text-align: left; padding: 6px;">Waarde</th>
                            <th style="text-align: left; padding: 6px;">Voorbeeld</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td style="padding: 6px;"><code>--font-size-2xs</code></td><td style="padding: 6px;">10px</td><td style="padding: 6px; font-size: var(--font-size-2xs);">De snelle bruine vos</td></tr>
                        <tr><td style="padding: 6px;"><code>--font-size-xs</code></td><td style="padding: 6px;">11px</td><td style="padding: 6px; font-size: var(--font-size-xs);">De snelle bruine vos</td></tr>
                        <tr><td style="padding: 6px;"><code>--font-size-sm</code></td><td style="padding: 6px;">12px</td><td style="padding: 6px; font-size: var(--font-size-sm);">De snelle bruine vos</td></tr>
                        <tr style="background: var(--bg-surface-alt);"><td style="padding: 6px;"><code>--font-size</code></td><td style="padding: 6px;">13px</td><td style="padding: 6px; font-size: var(--font-size);"><strong>De snelle bruine vos</strong> (basis)</td></tr>
                        <tr><td style="padding: 6px;"><code>--font-size-md</code></td><td style="padding: 6px;">14px</td><td style="padding: 6px; font-size: var(--font-size-md);">De snelle bruine vos</td></tr>
                        <tr><td style="padding: 6px;"><code>--font-size-lg</code></td><td style="padding: 6px;">16px</td><td style="padding: 6px; font-size: var(--font-size-lg);">De snelle bruine vos</td></tr>
                        <tr><td style="padding: 6px;"><code>--font-size-xl</code></td><td style="padding: 6px;">18px</td><td style="padding: 6px; font-size: var(--font-size-xl);">De snelle bruine vos</td></tr>
                        <tr><td style="padding: 6px;"><code>--font-size-2xl</code></td><td style="padding: 6px;">20px</td><td style="padding: 6px; font-size: var(--font-size-2xl);">De snelle bruine vos</td></tr>
                        <tr><td style="padding: 6px;"><code>--font-size-3xl</code></td><td style="padding: 6px;">24px</td><td style="padding: 6px; font-size: var(--font-size-3xl);">De snelle bruine vos</td></tr>
                    </tbody>
                </table>

                <h4 style="margin-top: 20px;">Koppen en tekst</h4>
                <div style="margin-bottom: 16px;">
                    <h1 style="margin: 0 0 8px 0;">Kop 1</h1>
                    <h2 style="margin: 0 0 8px 0;">Kop 2</h2>
                    <h3 style="margin: 0 0 8px 0;">Kop 3</h3>
                    <p style="margin: 0 0 8px 0;">Bodytekst - Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
                    <p class="text-muted" style="margin: 0 0 8px 0;">Gedempte tekst voor secundaire informatie.</p>
                    <p style="margin: 0;"><a href="#">Linktekst</a> &middot; <strong>Vetgedrukt</strong> &middot; <em>Cursief</em> &middot; <code>code</code></p>
                </div>
            </div>
            <div class="component-options">
                <h4>Lettertype families</h4>
                <dl>
                    <dt>--font-family</dt>
                    <dd><code>"Trebuchet MS", Verdana</code> (standaard)</dd>
                    <dt>--font-family-sans</dt>
                    <dd><code>"Trebuchet MS", Verdana, sans-serif</code></dd>
                    <dt>--font-family-mono</dt>
                    <dd><code>"Consolas", "Monaco", monospace</code></dd>
                </dl>
                <h4>Kleuren</h4>
                <dl>
                    <dt>--heading-color</dt>
                    <dd><code>#666699</code> (H1, H2, toolbar titels)</dd>
                    <dt>--text-primary</dt>
                    <dd>Hoofdtekst</dd>
                    <dt>--text-secondary</dt>
                    <dd>Secundaire tekst</dd>
                    <dt>--text-muted</dt>
                    <dd>Gedempte tekst</dd>
                </dl>
                <h4>Gebruik</h4>
                <dl>
                    <dt>--font-size</dt>
                    <dd>Standaard body tekst (13px)</dd>
                    <dt>--font-size-sm</dt>
                    <dd>Labels, hulptekst, metadata</dd>
                    <dt>--font-size-xs / 2xs</dt>
                    <dd>Badges, tellers, filter hints</dd>
                    <dt>--font-size-lg+</dt>
                    <dd>Koppen, dialoogtitels</dd>
                </dl>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- RESPONSIVE IMAGE (PHP Helper)                                   -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <section class="component-section" id="responsive-image">
        <div class="component-header">
            <h2>ResponsiveImage</h2>
            <span class="tag lib">PHP</span>
            <p class="component-description">Responsive &lt;img&gt; tags met WebP srcset generatie</p>
        </div>
        <div class="component-body">
            <div class="component-content">
<?php
use App\Library\ResponsiveImage;
use App\Library\Server;
use App\Library\Image;

$demoImage = '/uploads/storybook-demo/s6ae2hwbqrouwdqy920230514181618.jpg';
$demoPath = Server::mapPath($demoImage);

// Generate variants if not yet present
if (!ResponsiveImage::hasVariants($demoPath)) {
    ResponsiveImage::generate($demoPath);
}

// Gather variant info for display
$responsiveDir = ResponsiveImage::getResponsiveDir($demoPath);
$baseName = pathinfo($demoImage, PATHINFO_FILENAME);
$variants = [];
foreach (ResponsiveImage::SIZES as $w) {
    $vPath = $responsiveDir . DIRECTORY_SEPARATOR . $baseName . '-' . $w . 'w.webp';
    if (file_exists($vPath)) {
        $variants[] = ['width' => $w, 'size' => filesize($vPath)];
    }
}
$fullWebP = $responsiveDir . DIRECTORY_SEPARATOR . $baseName . '.webp';
$origInfo = Image::getInfo($demoPath);
if (file_exists($fullWebP)) {
    $variants[] = ['width' => $origInfo ? $origInfo['width'] : 0, 'size' => filesize($fullWebP), 'full' => true];
}
$origSize = filesize($demoPath);
?>
                <h3 style="margin: 0 0 8px; font-size: var(--font-size-md);">1. Eenvoudig</h3>
                <p style="font-size: var(--font-size-sm); color: var(--text-muted); margin: 0 0 8px;">Alleen pad en alt-tekst. Genereert automatisch srcset met alle beschikbare varianten.</p>
                <div class="component-example">
                    <?= ResponsiveImage::imgTag($demoImage, 'Demo afbeelding - eenvoudig', '100vw', '', ['style' => 'max-width:100%; height:auto;']) ?>
                </div>
                <div class="code-block">&lt;?php echo ResponsiveImage::imgTag('/images/photo.jpg', 'Beschrijving'); ?&gt;</div>
                <div class="code-block" style="white-space: pre-wrap; word-break: break-all; margin-top: 4px;"><?= htmlspecialchars(ResponsiveImage::imgTag($demoImage, 'Demo afbeelding - eenvoudig', '100vw', '', ['style' => 'max-width:100%; height:auto;'])) ?></div>

                <h3 style="margin: 15px 0 8px; font-size: var(--font-size-md);">2. Met sizes hint</h3>
                <p style="font-size: var(--font-size-sm); color: var(--text-muted); margin: 0 0 8px;">Helpt de browser de juiste variant te kiezen op basis van viewport en layout.</p>
                <div class="component-example">
                    <?= ResponsiveImage::imgTag($demoImage, 'Demo afbeelding - met sizes', '(max-width: 600px) 100vw, 50vw', '', ['style' => 'max-width:50%; height:auto;']) ?>
                </div>
                <div class="code-block">&lt;?php echo ResponsiveImage::imgTag('/images/photo.jpg', 'Beschrijving', '(max-width: 600px) 100vw, 50vw'); ?&gt;</div>
                <div class="code-block" style="white-space: pre-wrap; word-break: break-all; margin-top: 4px;"><?= htmlspecialchars(ResponsiveImage::imgTag($demoImage, 'Demo afbeelding - met sizes', '(max-width: 600px) 100vw, 50vw', '', ['style' => 'max-width:50%; height:auto;'])) ?></div>

                <h3 style="margin: 15px 0 8px; font-size: var(--font-size-md);">3. Met CSS class en extra attributen</h3>
                <p style="font-size: var(--font-size-sm); color: var(--text-muted); margin: 0 0 8px;">Voeg CSS class, width/height, fetchpriority en andere attributen toe.</p>
                <div class="component-example">
                    <?= ResponsiveImage::imgTag($demoImage, 'Demo afbeelding - hero', '100vw', 'hero-image', [
                        'width' => 400,
                        'height' => 562,
                        'fetchpriority' => 'high',
                        'loading' => 'eager',
                        'style' => 'max-width:400px; height:auto;',
                    ]) ?>
                </div>
                <div class="code-block">&lt;?php echo ResponsiveImage::imgTag('/images/photo.jpg', 'Beschrijving', '100vw', 'hero-image', [
    'width' =&gt; 1200,
    'height' =&gt; 800,
    'fetchpriority' =&gt; 'high',
    'loading' =&gt; 'eager',
]); ?&gt;</div>
                <div class="code-block" style="white-space: pre-wrap; word-break: break-all; margin-top: 4px;"><?= htmlspecialchars(ResponsiveImage::imgTag($demoImage, 'Demo afbeelding - hero', '100vw', 'hero-image', ['width' => 400, 'height' => 562, 'fetchpriority' => 'high', 'loading' => 'eager', 'style' => 'max-width:400px; height:auto;'])) ?></div>

            </div>
            <div class="component-options">
                <h4>Parameters</h4>
                <dl>
                    <dt>$imageUrl</dt>
                    <dd>URL-pad naar het originele bestand, bijv. <code>/images/photo.jpg</code></dd>
                    <dt>$alt</dt>
                    <dd>Alt-tekst voor de afbeelding</dd>
                    <dt>$sizes</dt>
                    <dd>Sizes attribuut (standaard: <code>100vw</code>). Helpt de browser de juiste variant kiezen.</dd>
                    <dt>$class</dt>
                    <dd>CSS class(es) voor de <code>&lt;img&gt;</code> tag</dd>
                    <dt>$attrs</dt>
                    <dd>Array met extra HTML-attributen, bijv. <code>['width' => 1200, 'fetchpriority' => 'high']</code></dd>
                </dl>
                <h4>Constanten</h4>
                <dl>
                    <dt>SIZES</dt>
                    <dd><?= implode(', ', ResponsiveImage::SIZES) ?> px breedtes</dd>
                    <dt>DEFAULT_QUALITY</dt>
                    <dd><?= ResponsiveImage::DEFAULT_QUALITY ?>%</dd>
                    <dt>RESPONSIVE_DIR</dt>
                    <dd><code><?= ResponsiveImage::RESPONSIVE_DIR ?></code></dd>
                </dl>
                <h4>Varianten demo-afbeelding</h4>
                <dl>
                    <dt>Origineel</dt>
                    <dd><?= $origInfo ? $origInfo['width'] . 'x' . $origInfo['height'] : '?' ?> &mdash; <?= round($origSize / 1024) ?> KB</dd>
<?php foreach ($variants as $v): ?>
                    <dt><?= !empty($v['full']) ? 'WebP (volledig)' : $v['width'] . 'w variant' ?></dt>
                    <dd><?= round($v['size'] / 1024) ?> KB<?= $origSize > 0 ? ' (' . round(100 - ($v['size'] / $origSize * 100)) . '% kleiner)' : '' ?></dd>
<?php endforeach; ?>
                </dl>
                <h4>Hulpmethoden</h4>
                <dl>
                    <dt>generate($path)</dt>
                    <dd>Maakt alle WebP-varianten aan</dd>
                    <dt>hasVariants($path)</dt>
                    <dd>Controleert of varianten bestaan</dd>
                    <dt>deleteVariants($path)</dt>
                    <dd>Verwijdert alle varianten</dd>
                    <dt>getWebPUrl($url)</dt>
                    <dd>Geeft URL van full-size WebP</dd>
                    <dt>getVariantUrl($url, $w)</dt>
                    <dd>Geeft URL van breedte-variant</dd>
                    <dt>batchGenerate($dir)</dt>
                    <dd>Batch-conversie van een hele map</dd>
                </dl>
                <h4>Notities</h4>
                <dl>
                    <dt>Lazy loading</dt>
                    <dd>Standaard <code>loading="lazy"</code>. Overschrijf met <code>['loading' => 'eager']</code> voor above-the-fold afbeeldingen.</dd>
                    <dt>Grootte-check</dt>
                    <dd>Varianten die groter zijn dan het origineel worden automatisch overgeslagen in de srcset.</dd>
                    <dt>Opslag</dt>
                    <dd>Varianten staan in <code>.responsive/</code> submap naast het origineel.</dd>
                </dl>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- FILE BROWSER (Wizard)                                          -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <section class="component-section" id="file-browser">
        <div class="component-header">
            <h2>Bestandsbrowser</h2>
            <span class="tag cma">wizard</span>
            <p class="component-description">Bestandsbrowser met upload, mappen, preview en afbeelding bewerken</p>
        </div>
        <div class="component-body">
            <div class="component-content">
                <div style="margin-bottom: 12px;">
                    <label style="font-weight: 600; font-size: var(--font-size-sm); margin-right: 10px;">Preset:</label>
                    <select id="fb-variation" onchange="window.fbApplyPreset(this.value)" style="padding: 4px 8px; border: 1px solid var(--border-color); border-radius: 3px; font-size: var(--font-size-sm);">
                        <option value="default">Standaard (alle bestanden)</option>
                        <option value="images">Alleen afbeeldingen</option>
                        <option value="images-layout">Afbeeldingen + layout opties</option>
                        <option value="images-resize-max">Afbeeldingen + max formaat (800x600)</option>
                        <option value="images-resize-fixed">Afbeeldingen + vast formaat (400x300)</option>
                        <option value="filter-pdf">Filter: alleen PDF</option>
                        <option value="no-layout">Afbeeldingen zonder layout opties</option>
                    </select>
                    <button class="btn btn-primary" onclick="window.fbOpenDialog()" style="margin-left: 10px; font-size: var(--font-size-sm);">
                        <span class="lnr lnr-folder"></span> Open bestandsbrowser
                    </button>
                </div>
                <div style="margin-top: 8px; display: grid; grid-template-columns: 120px 1fr; gap: 6px 10px; align-items: center; font-size: var(--font-size-sm); max-width: 500px;">
                    <label style="font-weight: 600;">basepath</label>
                    <input type="text" id="fb-param-basepath" value="/uploads/storybook-demo/" style="padding: 3px 6px; border: 1px solid var(--border-color); border-radius: 3px; font-size: var(--font-size-sm);">

                    <label style="font-weight: 600;">fieldname</label>
                    <input type="text" id="fb-param-fieldname" value="demo" style="padding: 3px 6px; border: 1px solid var(--border-color); border-radius: 3px; font-size: var(--font-size-sm);">

                    <label style="font-weight: 600;">image</label>
                    <label><input type="checkbox" id="fb-param-image"> Alleen afbeeldingen</label>

                    <label style="font-weight: 600;">layout</label>
                    <label><input type="checkbox" id="fb-param-layout" checked> Weergave opties tonen</label>

                    <label style="font-weight: 600;">filespec</label>
                    <input type="text" id="fb-param-filespec" value="*.*" style="padding: 3px 6px; border: 1px solid var(--border-color); border-radius: 3px; font-size: var(--font-size-sm);">

                    <label style="font-weight: 600;">file</label>
                    <input type="text" id="fb-param-file" value="" placeholder="Voorgeselecteerd bestand" style="padding: 3px 6px; border: 1px solid var(--border-color); border-radius: 3px; font-size: var(--font-size-sm);">

                    <label style="font-weight: 600;">resizetype</label>
                    <select id="fb-param-resizetype" style="padding: 3px 6px; border: 1px solid var(--border-color); border-radius: 3px; font-size: var(--font-size-sm);">
                        <option value="0">0 - Geen beperking</option>
                        <option value="1">1 - Maximaal</option>
                        <option value="2">2 - Vast formaat</option>
                    </select>

                    <label style="font-weight: 600;">resizewidth</label>
                    <input type="number" id="fb-param-resizewidth" value="0" min="0" style="padding: 3px 6px; border: 1px solid var(--border-color); border-radius: 3px; font-size: var(--font-size-sm); width: 80px;">

                    <label style="font-weight: 600;">resizeheight</label>
                    <input type="number" id="fb-param-resizeheight" value="0" min="0" style="padding: 3px 6px; border: 1px solid var(--border-color); border-radius: 3px; font-size: var(--font-size-sm); width: 80px;">
                </div>
                <div style="margin-top: 8px; font-size: var(--font-size-xs); color: var(--text-muted);" id="fb-url-display"></div>

                <div id="fb-result" style="display: none; margin-top: 12px; padding: 12px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-surface-alt); font-size: var(--font-size-sm);">
                    <h4 style="margin: 0 0 8px;">Selectieresultaat</h4>
                    <dl id="fb-result-data" style="margin: 0;"></dl>
                </div>

                <lib-dialog id="fbDialog" heading="Bestandsbrowser" size="fullscreen" modal maximizable>
                    <iframe id="fb-iframe" style="width: 100%; height: 100%; border: none; display: block;"></iframe>
                </lib-dialog>
            </div>
            <div class="component-options">
                <h4>URL Parameters</h4>
                <dl>
                    <dt>basepath</dt>
                    <dd>Basispad voor bestanden (vereist). Wordt automatisch aangemaakt als het niet bestaat.</dd>

                    <dt>fieldname</dt>
                    <dd>Naam van het veld dat wordt bijgewerkt bij selectie.</dd>

                    <dt>image</dt>
                    <dd>Indien aanwezig: alleen afbeeldingen tonen.</dd>

                    <dt>file</dt>
                    <dd>Voorgeselecteerd bestand. Kan een pad bevatten relatief aan basepath.</dd>

                    <dt>filespec</dt>
                    <dd>Bestandsfilter patroon, bijv. <code>*.pdf</code>, <code>*.jpg</code>. Standaard: <code>*.*</code></dd>

                    <dt>layout</dt>
                    <dd>Toon uitlijning/rand/marge opties voor afbeeldingen. Standaard: <code>1</code>. Zet op <code>0</code> om te verbergen.</dd>

                    <dt>resizetype</dt>
                    <dd>Afmetingsbeperking. <code>0</code>=geen, <code>1</code>=maximaal, <code>2</code>=vast.</dd>

                    <dt>resizewidth</dt>
                    <dd>Maximale of vaste breedte in pixels.</dd>

                    <dt>resizeheight</dt>
                    <dd>Maximale of vaste hoogte in pixels.</dd>
                </dl>

                <h4>Functies</h4>
                <dl>
                    <dt>Bestandslijst</dt>
                    <dd>Navigatie door mappen, lijst- en miniatuurweergave.</dd>

                    <dt>Upload</dt>
                    <dd>Drag & drop of knop. Overschrijfbevestiging bij bestaand bestand.</dd>

                    <dt>Map aanmaken</dt>
                    <dd>Nieuwe submap aanmaken.</dd>

                    <dt>Verwijderen</dt>
                    <dd>Bestanden en lege mappen verwijderen.</dd>

                    <dt>Afbeelding bewerken</dt>
                    <dd>Bijsnijden (vrij/verhouding), draaien, helderheid, verscherpen. Alleen beschikbaar bij afbeeldingen met layout modus.</dd>

                    <dt>Afmetingsvalidatie</dt>
                    <dd>Bij <code>resizetype=1</code> of <code>2</code>: waarschuwing/fout als afbeelding niet voldoet.</dd>
                </dl>

                <h4>Weergavemodi</h4>
                <dl>
                    <dt>Lijst</dt>
                    <dd>Bestandsnaam + bestandsgrootte. Kleine icoonminiatuur voor afbeeldingen.</dd>

                    <dt>Miniaturen</dt>
                    <dd>Visuele preview met 50x50 thumbnails voor afbeeldingen onder 2 MB.</dd>
                </dl>
            </div>
        </div>
    </section>


<?php
// Parse linearicons.css to extract all icons
$lineariconsPath = __DIR__ . '/../docs/linearicons.css';
$allIcons = [];
if (file_exists($lineariconsPath)) {
    $css = file_get_contents($lineariconsPath);
    // Match pattern: .lnr-iconname::before { content: "\e6XX"; }
    if (preg_match_all('/\.lnr-([a-z0-9-]+)::before\s*\{\s*content:\s*"\\\\([a-f0-9]+)"/', $css, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $allIcons[$match[1]] = $match[2];
        }
    }
}
?>
<!-- Override @font-face to use the FULL Linearicons font in storybook (all 1002 icons) -->
<style>
@font-face {
    font-family: 'Linearicons';
    src: url('../../library/fonts/Linearicons/Font/Linearicons.woff2') format('woff2'),
         url('../../library/fonts/Linearicons/Font/Linearicons.woff') format('woff'),
         url('../../library/fonts/Linearicons/Font/Linearicons.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
    font-display: swap;
}
</style>
<!-- Include ALL icon definitions from linearicons.css so they display in storybook -->
<style>
<?php foreach ($allIcons as $name => $code): ?>
.lnr-<?= $name ?>::before { content: "\<?= $code ?>"; }
<?php endforeach; ?>
</style>
<script>
window.ALL_LINEARICONS = <?= json_encode($allIcons) ?>;
</script>


</div>
</div>

<style>
.icons-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 10px;
    margin-top: 15px;
}

.icon-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 15px 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.15s ease;
    background: var(--bg-input);
}

.icon-item:hover {
    border-color: var(--primary);
    background: var(--bg-hover);
}

.icon-item .lnr {
    font-size: var(--font-size-3xl);
    margin-bottom: 8px;
    color: var(--text-primary);
}

.icon-item .icon-name {
    font-size: var(--font-size-xs);
    color: var(--text-muted);
    text-align: center;
    word-break: break-all;
}

.icon-item .icon-code {
    font-size: var(--font-size-2xs);
    color: var(--text-muted);
    font-family: monospace;
    opacity: 0.7;
}

.icon-item:hover .icon-code {
    color: var(--primary);
    opacity: 1;
}

.icon-item.copied {
    border-color: var(--success);
    background: rgba(40, 167, 69, 0.1);
}

.icon-item.copied .icon-name,
.icon-item.copied .icon-code {
    color: var(--success);
}

/* Unused icons (from linearicons.css but not yet in style.css) */
.icon-item-unused {
    opacity: 0.7;
    border-style: dashed;
    position: relative;
}
.icon-item-unused:hover {
    opacity: 1;
}

/* Add icon button */
.btn-add-icon {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 20px;
    height: 20px;
    border: 1px solid var(--border-color);
    border-radius: 50%;
    background: var(--bg-surface);
    color: var(--text-muted);
    font-size: var(--font-size-md);
    line-height: 18px;
    text-align: center;
    cursor: pointer;
    padding: 0;
    opacity: 0;
    transition: opacity 0.15s ease;
}
.icon-item-unused:hover .btn-add-icon {
    opacity: 1;
}
.btn-add-icon:hover {
    background: var(--color-success);
    color: #fff;
    border-color: var(--color-success);
}
.btn-add-icon-done {
    opacity: 1;
    background: var(--color-success);
    color: #fff;
    border-color: var(--color-success);
    cursor: default;
}

/* Badge for icons in use (shown in search results) */
.icon-item .icon-badge {
    font-size: 9px;
    background: var(--success);
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    margin-top: 4px;
}

/* Collapsible icons section */
.icons-collapsible {
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--bg-surface-alt);
}
.icons-collapsible summary {
    padding: 12px 16px;
    cursor: pointer;
    font-weight: 600;
    color: var(--text-primary);
    list-style: none;
    display: flex;
    align-items: center;
    gap: 8px;
}
.icons-collapsible summary::-webkit-details-marker {
    display: none;
}
.icons-collapsible summary::before {
    content: "\e93c"; /* chevron-right */
    font-family: 'Linearicons';
    font-size: var(--font-size-sm);
    transition: transform 0.2s ease;
}
.icons-collapsible[open] summary::before {
    transform: rotate(90deg);
}
.icons-collapsible summary:hover {
    background: var(--bg-hover);
}
.icons-collapsible > *:not(summary) {
    padding: 0 16px 16px 16px;
}
</style>

<script>
(function() {
    'use strict';

    // Render icons: first icons in use (CMA.ICON_CODES), then remaining from ALL_LINEARICONS
    function renderIcons() {
        // Wait for both CMA.ICON_CODES and ALL_LINEARICONS to be available
        if (!window.ALL_LINEARICONS) {
            setTimeout(renderIcons, 100);
            return;
        }
        if (!window.CMA || !window.CMA.ICON_CODES) {
            setTimeout(renderIcons, 100);
            return;
        }

        const gridUsed = document.getElementById('iconsGridUsed');
        const gridAll = document.getElementById('iconsGridAll');
        const usedCountDisplay = document.getElementById('usedIconCount');
        const remainingCountDisplay = document.getElementById('remainingIconCount');

        if (!gridUsed || !gridAll) return;

        // Icons in use (from CMA.ICON_CODES in style.css)
        const usedIcons = Object.entries(CMA.ICON_CODES);
        usedIcons.sort((a, b) => a[0].localeCompare(b[0]));

        // Build set of used icon names for exclusion
        const usedIconNames = new Set(usedIcons.map(([name]) => name));

        // All icons from linearicons.css, excluding ones already in use
        const allIcons = Object.entries(ALL_LINEARICONS);
        const remainingIcons = allIcons.filter(([name]) => !usedIconNames.has(name));
        remainingIcons.sort((a, b) => a[0].localeCompare(b[0]));

        // Render icons in use
        let usedHtml = '';
        for (const [name, code] of usedIcons) {
            const hexCode = code.codePointAt(0).toString(16);
            usedHtml += `
                <div class="icon-item" data-name="${name}" data-content="\\${hexCode}" title="Klik om CSS content te kopiëren">
                    <span class="lnr lnr-${name}"></span>
                    <span class="icon-name">lnr-${name}</span>
                    <span class="icon-code">\\${hexCode}</span>
                </div>
            `;
        }
        gridUsed.innerHTML = usedHtml || '<div style="padding: 20px; color: var(--text-muted);">Geen iconen in gebruik</div>';
        if (usedCountDisplay) usedCountDisplay.textContent = usedIcons.length;

        // Render remaining icons (with add button)
        let remainingHtml = '';
        for (const [name, hexCode] of remainingIcons) {
            remainingHtml += `
                <div class="icon-item icon-item-unused" data-name="${name}" data-content="\\${hexCode}" title="Klik om CSS content te kopiëren (icoon moet nog aan style.css worden toegevoegd)">
                    <span class="lnr lnr-${name}"></span>
                    <span class="icon-name">lnr-${name}</span>
                    <span class="icon-code">\\${hexCode}</span>
                    <button class="btn-add-icon" data-icon-name="${name}" data-icon-code="${hexCode}" title="Toevoegen aan icoonset">+</button>
                </div>
            `;
        }
        gridAll.innerHTML = remainingHtml || '<div style="padding: 20px; color: var(--text-muted);">Geen overige iconen</div>';
        if (remainingCountDisplay) remainingCountDisplay.textContent = remainingIcons.length;

        // Add click handlers for copy functionality
        function handleIconClick(e) {
            const item = e.target.closest('.icon-item');
            if (!item) return;

            // Copy the CSS content specification
            const contentCode = item.dataset.content;
            const cssSpec = `content: "${contentCode}";`;
            navigator.clipboard.writeText(cssSpec).then(function() {
                item.classList.add('copied');
                const nameEl = item.querySelector('.icon-name');
                const originalText = nameEl.textContent;
                nameEl.textContent = 'Gekopieerd!';

                setTimeout(function() {
                    item.classList.remove('copied');
                    nameEl.textContent = originalText;
                }, 1500);
            });
        }

        gridUsed.addEventListener('click', handleIconClick);
        gridAll.addEventListener('click', function(e) {
            // Handle add-icon button click
            const addBtn = e.target.closest('.btn-add-icon');
            if (addBtn) {
                e.stopPropagation();
                const iconName = addBtn.dataset.iconName;
                const iconCode = addBtn.dataset.iconCode;
                addIconToSet(iconName, iconCode, addBtn);
                return;
            }
            handleIconClick(e);
        });

        // Search functionality
        const searchInput = document.getElementById('iconSearch');
        const searchResults = document.getElementById('iconSearchResults');
        const searchGrid = document.getElementById('iconsGridSearch');
        const searchCount = document.getElementById('searchResultCount');
        const defaultView = document.getElementById('iconDefaultView');

        if (searchInput && searchGrid) {
            searchGrid.addEventListener('click', function(e) {
                const addBtn = e.target.closest('.btn-add-icon');
                if (addBtn) {
                    e.stopPropagation();
                    addIconToSet(addBtn.dataset.iconName, addBtn.dataset.iconCode, addBtn);
                    return;
                }
                handleIconClick(e);
            });

            // Combine all icons for search
            const allSearchableIcons = [
                ...usedIcons.map(([name, code]) => ({
                    name,
                    code: code.codePointAt(0).toString(16),
                    inUse: true
                })),
                ...remainingIcons.map(([name, code]) => ({
                    name,
                    code: code,
                    inUse: false
                }))
            ];

            function performSearch(query) {
                query = query.toLowerCase().trim();

                if (!query) {
                    // Show default view, hide search results
                    searchResults.style.display = 'none';
                    defaultView.style.display = 'block';
                    return;
                }

                // Filter icons matching query
                const matches = allSearchableIcons.filter(icon =>
                    icon.name.toLowerCase().includes(query)
                );

                // Sort: exact start matches first, then alphabetically
                matches.sort((a, b) => {
                    const aStarts = a.name.toLowerCase().startsWith(query);
                    const bStarts = b.name.toLowerCase().startsWith(query);
                    if (aStarts && !bStarts) return -1;
                    if (!aStarts && bStarts) return 1;
                    return a.name.localeCompare(b.name);
                });

                // Render search results
                let html = '';
                for (const icon of matches) {
                    const unusedClass = icon.inUse ? '' : ' icon-item-unused';
                    const title = icon.inUse
                        ? 'Klik om CSS content te kopiëren'
                        : 'Klik om CSS content te kopiëren (icoon moet nog aan style.css worden toegevoegd)';
                    const addBtn = icon.inUse ? '' : `<button class="btn-add-icon" data-icon-name="${icon.name}" data-icon-code="${icon.code}" title="Toevoegen aan icoonset">+</button>`;
                    html += `
                        <div class="icon-item${unusedClass}" data-name="${icon.name}" data-content="\\${icon.code}" title="${title}">
                            <span class="lnr lnr-${icon.name}"></span>
                            <span class="icon-name">lnr-${icon.name}</span>
                            <span class="icon-code">\\${icon.code}</span>
                            ${icon.inUse ? '<span class="icon-badge">in gebruik</span>' : addBtn}
                        </div>
                    `;
                }

                searchGrid.innerHTML = html || '<div style="padding: 20px; color: var(--text-muted);">Geen iconen gevonden</div>';
                searchCount.textContent = matches.length;

                // Show search results, hide default view
                searchResults.style.display = 'block';
                defaultView.style.display = 'none';
            }

            // Listen for input events on lib-search-input
            searchInput.addEventListener('input', function(e) {
                performSearch(e.target.value || searchInput.value || '');
            });

            // Also listen for the clear event
            searchInput.addEventListener('clear', function() {
                performSearch('');
            });
        }
    }

    function addIconToSet(name, code, btn) {
        btn.disabled = true;
        btn.textContent = '...';

        const formData = new FormData();
        formData.append('name', name);
        formData.append('code', code);

        fetch('../api/icon_add.php', {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                btn.textContent = '\u2713';
                btn.classList.add('btn-add-icon-done');
                const item = btn.closest('.icon-item');
                if (item) {
                    item.classList.remove('icon-item-unused');
                    item.style.borderColor = 'var(--success)';
                }
                if (window.libToaster) {
                    libToaster.toast('Icoon \'' + name + '\' toegevoegd. Voer python3 tools/build-icon-font.py uit om het lettertype bij te werken.', 'success');
                }
            } else {
                btn.textContent = '!';
                btn.title = data.error || 'Fout bij toevoegen';
                if (window.libToaster) {
                    libToaster.toast(data.error || 'Fout bij toevoegen', 'error');
                }
            }
        })
        .catch(function(err) {
            btn.textContent = '!';
            btn.title = err.message;
        });
    }

    renderIcons();
})();
</script>

<!-- Playground JavaScript -->
<script>
(function() {
    'use strict';

    /**
     * Playground - Live code editor with preview
     *
     * Usage:
     * <div class="playground" data-init="initFunction">
     *     <textarea>HTML code here</textarea>
     * </div>
     *
     * The data-init attribute specifies a function to call after rendering (optional)
     */

    class Playground {
        constructor(element) {
            this.element = element;
            this.initFn = element.dataset.init;
            this.originalCode = '';
            this.setup();
        }

        setup() {
            // Get the original code from textarea or pre-existing code
            const existingTextarea = this.element.querySelector('textarea');
            if (existingTextarea) {
                this.originalCode = existingTextarea.value;
            }

            // Create the playground structure
            this.element.innerHTML = `
                <div class="playground-header">
                    <span>Code</span>
                    <div class="playground-actions">
                        <button type="button" class="btn btn-secondary playground-reset" data-tooltip="Reset"><span class="lnr lnr-sync"></span></button>
                        <button type="button" class="btn btn-primary playground-run" data-tooltip="Toepassen"><span class="lnr lnr-play"></span></button>
                    </div>
                </div>
                <div class="playground-code">
                    <textarea spellcheck="false">${this.escapeHtml(this.originalCode)}</textarea>
                </div>
                <div class="playground-preview"></div>
            `;

            this.textarea = this.element.querySelector('textarea');
            this.preview = this.element.querySelector('.playground-preview');
            this.runBtn = this.element.querySelector('.playground-run');
            this.resetBtn = this.element.querySelector('.playground-reset');

            // Event listeners
            this.runBtn.addEventListener('click', () => this.run());
            this.resetBtn.addEventListener('click', () => this.reset());

            // Ctrl+Enter to run
            this.textarea.addEventListener('keydown', (e) => {
                if (e.ctrlKey && e.key === 'Enter') {
                    e.preventDefault();
                    this.run();
                }
            });

            // Initial render
            this.run();
        }

        escapeHtml(str) {
            return window.escapeHtml(str);
        }

        run() {
            const code = this.textarea.value;

            // Clear any previous errors
            const existingError = this.element.querySelector('.playground-error');
            if (existingError) existingError.remove();

            try {
                // Parse and render the HTML
                this.preview.innerHTML = code;

                // Execute any script tags in the code
                const scripts = this.preview.querySelectorAll('script');
                scripts.forEach(script => {
                    const newScript = document.createElement('script');
                    newScript.textContent = script.textContent;
                    script.parentNode.replaceChild(newScript, script);
                });

                // Call init function if specified
                if (this.initFn && typeof window[this.initFn] === 'function') {
                    window[this.initFn](this.preview);
                }

                // Re-initialize any web components that need it
                this.initializeComponents();

            } catch (error) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'playground-error';
                errorDiv.textContent = 'Fout: ' + error.message;
                this.element.appendChild(errorDiv);
            }
        }

        reset() {
            this.textarea.value = this.originalCode;
            this.run();
        }

        initializeComponents() {
            // Re-initialize lib-table filtering
            const tables = this.preview.querySelectorAll('lib-table');
            tables.forEach(table => {
                if (table._initialized) {
                    table._initialized = false;
                    table.connectedCallback && table.connectedCallback();
                }
            });

            // Re-initialize lib-combo with options (skip combos with id — those have their own init)
            const combos = this.preview.querySelectorAll('lib-combo:not([id])');
            combos.forEach(combo => {
                if (combo.dataset.initialized) return;
                combo.dataset.initialized = 'true';

                // Wait for custom element to be defined
                customElements.whenDefined('lib-combo').then(() => {
                    // Force upgrade if needed
                    customElements.upgrade(combo);

                    // Use double requestAnimationFrame to ensure element is fully rendered
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            if (!combo.hasAttribute('data-options-set') && typeof combo.setOptions === 'function') {
                                combo.setAttribute('data-options-set', 'true');
                                combo.setOptions([
                                    { value: '1', label: 'Optie 1' },
                                    { value: '2', label: 'Optie 2' },
                                    { value: '3', label: 'Optie 3' }
                                ]);
                            }
                        });
                    });
                });
            });

            // cma-sortlist reads from slotted <option> elements automatically

            // cma-tree data is set via script in the playground HTML

            // cma-tabs reads from <tab-item> children automatically
        }
    }

    // Initialize all playgrounds on page load
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.playground').forEach(el => {
            new Playground(el);
        });
    });

    // Expose for manual initialization
    window.Playground = Playground;
})();

// lib-loader demo helper
function showLoader(id) {
    var loader = document.getElementById(id);
    if (loader) {
        loader.setAttribute('active', '');
        setTimeout(function() {
            loader.removeAttribute('active');
        }, 2000);
    }
}

// File browser demo
(function() {
    'use strict';

    var presets = {
        'default':              { basepath: '/uploads/storybook-demo/', fieldname: 'demo', image: false, layout: true, filespec: '*.*', file: '', resizetype: 0, resizewidth: 0, resizeheight: 0 },
        'images':               { basepath: '/uploads/storybook-demo/', fieldname: 'demo', image: true,  layout: true, filespec: '*.*', file: '', resizetype: 0, resizewidth: 0, resizeheight: 0 },
        'images-layout':        { basepath: '/uploads/storybook-demo/', fieldname: 'demo', image: true,  layout: true, filespec: '*.*', file: '', resizetype: 0, resizewidth: 0, resizeheight: 0 },
        'images-resize-max':    { basepath: '/uploads/storybook-demo/', fieldname: 'demo', image: true,  layout: true, filespec: '*.*', file: '', resizetype: 1, resizewidth: 800, resizeheight: 600 },
        'images-resize-fixed':  { basepath: '/uploads/storybook-demo/', fieldname: 'demo', image: true,  layout: true, filespec: '*.*', file: '', resizetype: 2, resizewidth: 400, resizeheight: 300 },
        'filter-pdf':           { basepath: '/uploads/storybook-demo/', fieldname: 'demo', image: false, layout: true, filespec: '*.pdf', file: '', resizetype: 0, resizewidth: 0, resizeheight: 0 },
        'no-layout':            { basepath: '/uploads/storybook-demo/', fieldname: 'demo', image: true,  layout: false, filespec: '*.*', file: '', resizetype: 0, resizewidth: 0, resizeheight: 0 }
    };

    // Apply preset values to the form fields
    window.fbApplyPreset = function(name) {
        var p = presets[name];
        if (!p) return;
        document.getElementById('fb-param-basepath').value = p.basepath;
        document.getElementById('fb-param-fieldname').value = p.fieldname;
        document.getElementById('fb-param-image').checked = p.image;
        document.getElementById('fb-param-layout').checked = p.layout;
        document.getElementById('fb-param-filespec').value = p.filespec;
        document.getElementById('fb-param-file').value = p.file;
        document.getElementById('fb-param-resizetype').value = p.resizetype;
        document.getElementById('fb-param-resizewidth').value = p.resizewidth;
        document.getElementById('fb-param-resizeheight').value = p.resizeheight;
    };

    // Build URL from form fields
    function fbBuildParams() {
        var params = 'basepath=' + encodeURIComponent(document.getElementById('fb-param-basepath').value);
        params += '&fieldname=' + encodeURIComponent(document.getElementById('fb-param-fieldname').value);
        if (document.getElementById('fb-param-image').checked) params += '&image=1';
        params += '&layout=' + (document.getElementById('fb-param-layout').checked ? '1' : '0');
        var filespec = document.getElementById('fb-param-filespec').value;
        if (filespec && filespec !== '*.*') params += '&filespec=' + encodeURIComponent(filespec);
        var file = document.getElementById('fb-param-file').value;
        if (file) params += '&file=' + encodeURIComponent(file);
        var resizetype = document.getElementById('fb-param-resizetype').value;
        if (resizetype !== '0') {
            params += '&resizetype=' + resizetype;
            params += '&resizewidth=' + document.getElementById('fb-param-resizewidth').value;
            params += '&resizeheight=' + document.getElementById('fb-param-resizeheight').value;
        }
        return params;
    }

    window.fbOpenDialog = function() {
        var iframe = document.getElementById('fb-iframe');
        var urlDisplay = document.getElementById('fb-url-display');
        var dialog = document.getElementById('fbDialog');
        if (!iframe || !dialog) return;

        var params = fbBuildParams();
        var url = '../wizards/file-browser.php?' + params;
        iframe.src = url;
        if (urlDisplay) {
            urlDisplay.textContent = 'URL: wizards/file-browser.php?' + params;
        }
        dialog.open();
    };

    // Listen for file-browser postMessage to receive selected file
    window.addEventListener('message', function(e) {
        if (e.origin !== window.location.origin) return;
        if (!e.data || e.data.type !== 'file-browser-select') return;

        var dialog = document.getElementById('fbDialog');
        var resultDiv = document.getElementById('fb-result');
        var resultData = document.getElementById('fb-result-data');
        if (!resultDiv || !resultData) return;

        var html = '<dt style="font-weight:600;">Geselecteerd bestand</dt>' +
            '<dd style="margin:0 0 4px 0;"><code>' + e.data.value + '</code></dd>';
        if (e.data.layout && Object.keys(e.data.layout).length > 0) {
            html += '<dt style="font-weight:600; margin-top:8px;">Layout opties</dt>' +
                '<dd style="margin:0 0 4px 0;"><pre style="margin:0;font-size:var(--font-size-xs);background:var(--bg-surface);padding:6px;border-radius:4px;">' +
                JSON.stringify(e.data.layout, null, 2) + '</pre></dd>';
        }
        resultData.innerHTML = html;

        resultDiv.style.display = 'block';

        if (dialog && typeof dialog.close === 'function') {
            dialog.close();
        }
    });
})();

// lib-tip demo handlers
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var btnShowTip = document.getElementById('btnShowTip');
        var btnShowTour = document.getElementById('btnShowTour');
        var btnCloseTip = document.getElementById('btnCloseTip');
        var btnResetTips = document.getElementById('btnResetTips');

        if (btnShowTip) {
            btnShowTip.addEventListener('click', function() {
                if (typeof LibTip === 'undefined') {
                    libToast.error('LibTip is niet geladen');
                    return;
                }
                LibTip.show({
                    id: 'storybook-demo-tip',
                    target: '.nav-sidebar',
                    title: 'Navigatie',
                    content: '<p>Dit is het navigatiemenu.</p><p>Klik op een item om naar het component te scrollen.</p>',
                    position: 'right'
                });
            });
        }

        if (btnShowTour) {
            btnShowTour.addEventListener('click', function() {
                if (typeof LibTip === 'undefined') {
                    libToast.error('LibTip is niet geladen');
                    return;
                }
                LibTip.tour('storybook-demo-tour', [
                    {
                        target: '.nav-sidebar',
                        title: 'Stap 1: Navigatie',
                        content: 'Het navigatiemenu links toont alle beschikbare componenten.',
                        position: 'right'
                    },
                    {
                        target: '#lib-tip .component-header',
                        title: 'Stap 2: Component header',
                        content: 'Elk component heeft een header met naam, tag en beschrijving.',
                        position: 'bottom'
                    },
                    {
                        target: '#lib-tip .component-demo',
                        title: 'Stap 3: Demo knoppen',
                        content: 'Gebruik deze knoppen om tips en tours te testen.',
                        position: 'top'
                    }
                ]);
            });
        }

        if (btnCloseTip) {
            btnCloseTip.addEventListener('click', function() {
                if (typeof LibTip !== 'undefined') {
                    LibTip.close();
                }
            });
        }

        if (btnResetTips) {
            btnResetTips.addEventListener('click', function() {
                if (typeof LibTip !== 'undefined') {
                    LibTip.reset().then(function() {
                        libToast.success('Skip list gereset');
                    });
                }
            });
        }
    });
})();
</script>

</BODY>
</HTML>
