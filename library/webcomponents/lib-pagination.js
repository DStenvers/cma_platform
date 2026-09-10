/**
 * lib-pagination Web Component
 *
 * Bladerknoppen voor een gepagineerde lijst: eerste, vorige, een venster van
 * paginanummers rond de huidige, volgende, laatste. Zelfstandig via Shadow DOM,
 * dus bruikbaar in de CMA én op een front-end-pagina zonder extra CSS; het
 * uiterlijk is af te stemmen met CSS-variabelen en ::part().
 *
 * Twee manieren van bladeren:
 *
 *   1. Server-side (een link per pagina). Geef een href-sjabloon met {page}:
 *        <lib-pagination page="3" pages="16"
 *            href="?pageaction=deelnemers&p={page}&zoek=jan#actief"></lib-pagination>
 *      Elke knop is dan een gewone <a>, dus werkt ook zonder JavaScript-handler,
 *      met middelklik en met de terugknop.
 *
 *   2. Client-side (JavaScript haalt de pagina op). Laat href weg:
 *        <lib-pagination page="1" total="3085" page-size="200"></lib-pagination>
 *        el.addEventListener('page-change', e => laad(e.detail.page));
 *      Het component zet dan zelf het attribuut page en vuurt page-change.
 *
 * Attributen:
 *   - page      : huidige pagina, 1-gebaseerd (default 1)
 *   - pages     : aantal pagina's; óf
 *   - total     : aantal records, met
 *   - page-size : records per pagina (default 50) → pages = ceil(total / page-size)
 *   - href      : sjabloon voor de link per pagina; {page} wordt vervangen
 *   - window    : aantal nummers links en rechts van de huidige (default 3)
 *   - no-ends   : laat de knoppen eerste/laatste weg
 *
 * Properties: page, pages, total, pageSize (lezen en zetten; zetten rendert).
 *
 * Events:
 *   - page-change : detail { page, href } — bij een klik op een andere pagina.
 *                   Met href is het event annuleerbaar: preventDefault() houdt de
 *                   navigatie tegen. Zonder href is het al gezet als het vuurt.
 *
 * Thema (CSS-variabelen op het element of een ouder):
 *   --lib-pagination-accent      kleur van de huidige pagina en van hover (default --color-accent, #ff6400)
 *   --lib-pagination-color       tekstkleur van de knoppen (default de accentkleur)
 *   --lib-pagination-border      rand van een knop (default 1px solid #ddd)
 *   --lib-pagination-radius      hoekradius (default 4px)
 *   --lib-pagination-gap         ruimte tussen de knoppen (default 4px)
 *   --lib-pagination-min-width   minimale knopbreedte (default 30px)
 *   --lib-pagination-font-size   lettergrootte (default inherit)
 *
 * Shadow parts: nav, button, current, disabled
 *
 * Een <lib-pagination> zonder pagina's (pages <= 1) rendert niets: één pagina
 * hoeft niet gebladerd te worden.
 */

// Guard tegen dubbele registratie (een pagina die het bestand ook los laadt).
if (!customElements.get('lib-pagination')) {

    class LibPagination extends HTMLElement {
        static get observedAttributes() {
            return ['page', 'pages', 'total', 'page-size', 'href', 'window', 'no-ends'];
        }

        constructor() {
            super();
            this.attachShadow({ mode: 'open' });
            this._rendered = false;
            this._onClick = this._onClick.bind(this);
        }

        connectedCallback() {
            this._render();
            this._rendered = true;
            this.shadowRoot.addEventListener('click', this._onClick);
        }

        disconnectedCallback() {
            this.shadowRoot.removeEventListener('click', this._onClick);
        }

        attributeChangedCallback() {
            if (this._rendered) { this._render(); }
        }

        /* ---- properties ---- */

        get page()  { return Math.max(1, Math.min(this.pages, this._int('page', 1))); }
        set page(v) { this.setAttribute('page', String(Math.max(1, parseInt(v, 10) || 1))); }

        get pages() {
            if (this.hasAttribute('pages')) { return Math.max(0, this._int('pages', 0)); }
            const total = this._int('total', 0);
            return total > 0 ? Math.ceil(total / this.pageSize) : 0;
        }
        set pages(v) { this.setAttribute('pages', String(Math.max(0, parseInt(v, 10) || 0))); }

        get total()  { return this._int('total', 0); }
        set total(v) { this.setAttribute('total', String(Math.max(0, parseInt(v, 10) || 0))); }

        get pageSize()  { return Math.max(1, this._int('page-size', 50)); }
        set pageSize(v) { this.setAttribute('page-size', String(Math.max(1, parseInt(v, 10) || 1))); }

        /** De link voor een pagina, of null zonder href-sjabloon. */
        hrefFor(page) {
            const sjabloon = this.getAttribute('href');
            if (sjabloon === null || sjabloon === '') { return null; }
            return sjabloon.split('{page}').join(String(page));
        }

        /* ---- intern ---- */

        _int(naam, standaard) {
            const v = parseInt(this.getAttribute(naam), 10);
            return isNaN(v) ? standaard : v;
        }

        _onClick(e) {
            const knop = e.composedPath().find((n) => n && n.nodeType === 1 && n.hasAttribute && n.hasAttribute('data-page'));
            if (!knop || knop.hasAttribute('aria-disabled') || knop.hasAttribute('aria-current')) {
                if (knop) { e.preventDefault(); }
                return;
            }
            const page = parseInt(knop.getAttribute('data-page'), 10);
            const href = this.hrefFor(page);
            const event = new CustomEvent('page-change', {
                bubbles: true, composed: true, cancelable: true,
                detail: { page, href }
            });
            if (href !== null) {
                // Een echte link: alleen tegenhouden als de luisteraar dat vraagt.
                if (!this.dispatchEvent(event)) { e.preventDefault(); }
                return;
            }
            e.preventDefault();
            this.page = page;
            this.dispatchEvent(event);
        }

        _knop(page, label, opties) {
            const o = opties || {};
            const href = this.hrefFor(page);
            const parts = ['button'];
            const attrs = ['data-page="' + page + '"', 'aria-label="' + this._esc(o.title || ('Pagina ' + page)) + '"'];
            if (o.current) { parts.push('current'); attrs.push('aria-current="page"'); }
            if (o.disabled) { parts.push('disabled'); attrs.push('aria-disabled="true"', 'tabindex="-1"'); }
            attrs.push('part="' + parts.join(' ') + '"', 'class="' + parts.join(' ') + '"');
            if (href !== null && !o.disabled && !o.current) {
                return '<a href="' + this._esc(href) + '" ' + attrs.join(' ') + '>' + label + '</a>';
            }
            return '<span ' + attrs.join(' ') + '>' + label + '</span>';
        }

        _render() {
            const pages = this.pages;
            if (pages <= 1) {
                this.shadowRoot.innerHTML = this._stijl();
                return;
            }
            const page = this.page;
            const venster = Math.max(0, this._int('window', 3));
            const van = Math.max(1, page - venster);
            const tot = Math.min(pages, page + venster);
            const eindes = !this.hasAttribute('no-ends');

            let html = '';
            if (eindes) { html += this._knop(1, '&laquo;', { disabled: page <= 1, title: 'Eerste pagina' }); }
            html += this._knop(page - 1, '&lsaquo;', { disabled: page <= 1, title: 'Vorige pagina' });
            for (let p = van; p <= tot; p++) {
                html += this._knop(p, String(p), { current: p === page });
            }
            html += this._knop(page + 1, '&rsaquo;', { disabled: page >= pages, title: 'Volgende pagina' });
            if (eindes) { html += this._knop(pages, '&raquo;', { disabled: page >= pages, title: 'Laatste pagina' }); }

            this.shadowRoot.innerHTML = this._stijl()
                + '<nav part="nav" class="nav" aria-label="Paginering">' + html + '</nav>';
        }

        _esc(s) {
            return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        _stijl() {
            return '<style>'
                + ':host { display: inline-block; font-size: var(--lib-pagination-font-size, inherit); }'
                + ':host([hidden]) { display: none; }'
                + '.nav { display: flex; flex-wrap: wrap; gap: var(--lib-pagination-gap, 4px); }'
                + '.button { display: inline-block; box-sizing: border-box; min-width: var(--lib-pagination-min-width, 30px);'
                + '  text-align: center; padding: 4px 8px; line-height: 1.3;'
                + '  border: var(--lib-pagination-border, 1px solid #ddd); border-radius: var(--lib-pagination-radius, 4px);'
                + '  text-decoration: none; cursor: pointer; user-select: none;'
                + '  color: var(--lib-pagination-color, var(--lib-pagination-accent, var(--color-accent, #ff6400)));'
                + '  background: transparent; transition: background-color 0.15s, color 0.15s; }'
                + 'a.button:hover, a.button:focus-visible { background: var(--lib-pagination-accent, var(--color-accent, #ff6400)); color: #fff; outline: none; }'
                + '.current { background: var(--lib-pagination-accent, var(--color-accent, #ff6400)); color: #fff;'
                + '  border-color: var(--lib-pagination-accent, var(--color-accent, #ff6400)); cursor: default; }'
                + '.disabled { color: #bbb; cursor: default; pointer-events: none; }'
                + '</style>';
        }
    }

    customElements.define('lib-pagination', LibPagination);
}
