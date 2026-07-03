/**
 * <cma-launcher> — the BIG tools menu.
 *
 * A full-screen, searchable overlay listing the admin tools/forms catalog
 * (fetched once from /cma/api/tools-catalog.php — same source as the tools.php
 * tree). Opened from the "Menu" button in the shell header. Selecting an item
 * loads it into #contentArea via the shell's window.loadPage() and updates the
 * clean URL, so the header + sidebar stay put.
 *
 * Light DOM on purpose: document CSS provides the lnr icon glyphs and theme
 * variables; the overlay is styled by namespaced .cma-launcher__* rules in
 * cma/assets/css/main.css.
 *
 * Public API: open(), close(), toggle(), isOpen().
 */
(function () {
    'use strict';

    if (customElements.get('cma-launcher')) return;

    var CATALOG_URL = '/cma/api/tools-catalog.php';

    // Work out how a catalog href navigates in the shell. Form-backed items are
    // canonical /cma/form/<form> routes; everything else is a tool page that
    // loads through tools.php (keyed by tool name), which then shows the tool
    // full-width.
    function resolveNav(href) {
        if (!href) return null;
        if (href.indexOf('form.php?form=') === 0) {
            var f = href.match(/form=([^&]+)/);
            return { page: href, url: f ? '/cma/form/' + f[1] : null };
        }
        var base = href.split('?')[0];
        var m = base.match(/tools\/tools_([^.]+)\.php$/) || base.match(/tools\/([^.]+)\.php$/);
        if (m) {
            var tool = m[1];
            // Preserve any extra query params (e.g. tools_backup.php?tab=manage) —
            // tools.php forwards them to the iframe src.
            var extra = href.indexOf('?') !== -1 ? '&' + href.substring(href.indexOf('?') + 1) : '';
            return { page: 'tools.php?tool=' + tool + extra, url: '/cma/tools?tool=' + encodeURIComponent(tool) + extra };
        }
        return { page: href, url: null };
    }

    class CmaLauncher extends HTMLElement {
        constructor() {
            super();
            this._loaded = false;
            this._open = false;
            this._onKeydown = this._onKeydown.bind(this);
        }

        isOpen() { return this._open; }

        async open() {
            if (this._open) return;
            this._open = true;
            this.classList.add('is-open');
            document.body.classList.add('cma-launcher-open');
            if (!this._loaded) {
                this._renderShell('<div class="cma-launcher__loading">Laden…</div>');
                await this._loadCatalog();
            }
            document.addEventListener('keydown', this._onKeydown, true);
            var search = this.querySelector('.cma-launcher__search');
            if (search) {
                if (typeof search.clear === 'function') search.clear();
                this._applyFilter('');
                if (typeof search.focus === 'function') search.focus();
            }
            // Highlight the item for the tool/form currently open in the shell.
            this._markActive();
        }

        // Does a catalog item's target URL point at what's open right now?
        // Matches tool pages by their ?tool= key and form pages by /cma/form/<x>,
        // so it works whether the shell URL is clean or the main.php?page=… form.
        _activeMatches(url) {
            if (!url) return false;
            var loc;
            try { loc = decodeURIComponent(window.location.href); } catch (e) { loc = window.location.href; }
            var tool = url.match(/[?&]tool=([^&]+)/);
            if (tool) {
                var lt = loc.match(/[?&]tool=([^&]+)/);
                return !!lt && decodeURIComponent(lt[1]) === decodeURIComponent(tool[1]);
            }
            var form = url.match(/\/cma\/form\/([^/?]+)/);
            if (form) return loc.indexOf('/cma/form/' + form[1]) !== -1;
            return false;
        }

        _markActive() {
            var self = this;
            this.querySelectorAll('.cma-launcher__item').forEach(function (a) {
                a.classList.toggle('is-active', self._activeMatches(a.getAttribute('data-url') || ''));
            });
        }

        close() {
            if (!this._open) return;
            this._open = false;
            this.classList.remove('is-open');
            document.body.classList.remove('cma-launcher-open');
            document.removeEventListener('keydown', this._onKeydown, true);
        }

        toggle() { this._open ? this.close() : this.open(); }

        _onKeydown(e) {
            if (e.key === 'Escape') { e.preventDefault(); this.close(); }
        }

        async _loadCatalog() {
            try {
                var resp = await fetch(CATALOG_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                var data = await resp.json();
                if (!data || !data.success) throw new Error((data && data.error) || 'Kon de tools niet laden');
                this._renderCatalog(data.groups || []);
                this._loaded = true;
            } catch (err) {
                this._renderShell('<div class="cma-launcher__error">Kon de tools-lijst niet laden: ' +
                    this._esc(err && err.message ? err.message : 'onbekende fout') + '</div>');
            }
        }

        _esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        _renderShell(bodyHtml) {
            this.innerHTML =
                '<div class="cma-launcher__backdrop" data-close></div>' +
                '<div class="cma-launcher__panel" role="dialog" aria-modal="true" aria-label="Alle beheerstools">' +
                    '<div class="cma-launcher__head">' +
                        '<lib-search-input class="cma-launcher__search" icon="left" placeholder="Zoek een tool…" aria-label="Zoek een tool"></lib-search-input>' +
                        '<a href="javascript:void(0)" class="cma-launcher__close" data-close aria-label="Sluiten" title="Sluiten"><span class="cma-launcher__close-x"></span></a>' +
                    '</div>' +
                    '<div class="cma-launcher__body">' + bodyHtml + '</div>' +
                '</div>';
            this._wire();
        }

        // Flatten one level of sub-folders into their own sections (e.g.
        // Developer -> Testen) so the mega-menu stays a flat grid of groups.
        _collectGroups(nodes, prefix, out) {
            (nodes || []).forEach(function (node) {
                if (node.type === 'folder') {
                    var title = prefix ? prefix + ' · ' + node.label : node.label;
                    var items = (node.children || []).filter(function (c) { return c.type === 'item'; });
                    if (items.length) out.push({ title: title, icon: node.icon, items: items });
                    var subs = (node.children || []).filter(function (c) { return c.type === 'folder'; });
                    if (subs.length) this._collectGroups(subs, title, out);
                }
            }, this);
        }

        _renderCatalog(groups) {
            var flat = [];
            this._collectGroups(groups, '', flat);
            var self = this;
            var html = flat.map(function (g) {
                var items = g.items.map(function (it) {
                    var nav = resolveNav(it.href);
                    var badge = it.badge ? '<span class="cma-launcher__badge" title="Toegangsniveau">' + self._esc(it.badge) + '</span>' : '';
                    var icon = it.icon ? '<span class="cma-launcher__item-icon lnr ' + self._esc(it.icon) + '"></span>' : '';
                    return '<a class="cma-launcher__item" href="' + self._esc(nav && nav.url ? nav.url : (it.href || '#')) + '"' +
                        ' data-page="' + self._esc(nav ? nav.page : '') + '"' +
                        ' data-url="' + self._esc(nav && nav.url ? nav.url : '') + '"' +
                        ' data-search="' + self._esc((it.label || '').toLowerCase()) + '">' +
                        icon + '<span class="cma-launcher__item-label">' + self._esc(it.label) + '</span>' + badge +
                        '</a>';
                }).join('');
                var gicon = g.icon ? '<span class="lnr ' + self._esc(g.icon) + '"></span> ' : '';
                return '<section class="cma-launcher__group">' +
                    '<h3 class="cma-launcher__group-title">' + gicon + self._esc(g.title) + '</h3>' +
                    '<div class="cma-launcher__items">' + items + '</div>' +
                    '</section>';
            }).join('');
            this._renderShell(html || '<div class="cma-launcher__error">Geen tools beschikbaar.</div>');
        }

        _wire() {
            var self = this;
            this.querySelectorAll('[data-close]').forEach(function (el) {
                el.addEventListener('click', function () { self.close(); });
            });
            var search = this.querySelector('.cma-launcher__search');
            if (search) {
                // <lib-search-input> emits 'input' (detail.value) per keystroke and
                // 'search' on Enter.
                search.addEventListener('input', function (e) {
                    // Read the term robustly: <lib-search-input> may deliver it via
                    // a custom event (detail.value), or the inner native <input>'s
                    // 'input' may bubble up here with no detail — in which case use
                    // target.value / the component's own .value. (Without this
                    // fallback the filter silently receives '' and quick-find dies.)
                    var v = (e.detail && typeof e.detail.value === 'string') ? e.detail.value
                        : (e.target && typeof e.target.value === 'string') ? e.target.value
                        : (typeof search.value === 'string' ? search.value : '');
                    self._applyFilter(v);
                });
                search.addEventListener('search', function () {
                    var first = self.querySelector('.cma-launcher__item:not([hidden])');
                    if (first) first.click();
                });
            }
            this.querySelectorAll('.cma-launcher__item').forEach(function (a) {
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    self._navigate(a.getAttribute('data-page'), a.getAttribute('data-url'));
                });
            });
        }

        _applyFilter(term) {
            term = (term || '').trim().toLowerCase();
            this.querySelectorAll('.cma-launcher__group').forEach(function (group) {
                var any = false;
                group.querySelectorAll('.cma-launcher__item').forEach(function (item) {
                    var match = !term || (item.getAttribute('data-search') || '').indexOf(term) !== -1;
                    item.hidden = !match;
                    if (match) any = true;
                });
                group.hidden = !any;
            });
        }

        _navigate(page, url) {
            this.close();
            if (page && typeof window.loadPage === 'function') {
                window.loadPage(page);
                if (url) { try { history.pushState(null, '', url); } catch (e) {} }
            } else if (url) {
                window.location.href = url;
            }
        }
    }

    customElements.define('cma-launcher', CmaLauncher);
})();
