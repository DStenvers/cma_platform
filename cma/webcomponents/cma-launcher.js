/**
 * <cma-launcher> — the BIG searchable menu (tools AND reports).
 *
 * A searchable panel listing a catalog of items grouped into sections. It fills
 * its host box (.launcher-host, e.g. #tools in tools.php, #reports in
 * reports.php) and overlays the iframe named by `target`. Picking an item loads
 * its href into that iframe, hides the menu and writes the item's deep-link
 * `url` to the address bar.
 *
 * Attributes: catalog-url, target (iframe selector), search-placeholder,
 * aria-label, empty-text.
 *
 * Light DOM on purpose: document CSS provides the lnr icon glyphs and theme
 * variables; the panel is styled by namespaced .cma-launcher__* rules in
 * cma/assets/css/main.css.
 *
 * Public API: open(), close(), toggle(), isOpen().
 */
(function () {
    'use strict';

    if (customElements.get('cma-launcher')) return;

    var DEFAULT_CATALOG_URL = '/cma/api/tools-catalog.php';

    // Work out the deep-link for a catalog href. Form-backed items route through
    // tools.php (/cma/tools?form=…); tool pages through /cma/tools?tool=<name>.
    function resolveNav(href) {
        if (!href) return null;
        // Site-specific tools live at the site root (/tools/…, outside /cma/) or
        // are full URLs — plain scripts, not CMA pages. Open them in a new tab.
        if (/^https?:\/\//i.test(href) || href.charAt(0) === '/') {
            return { external: true, url: href };
        }
        if (href.indexOf('form.php?form=') === 0) {
            var f = href.match(/form=([^&]+)/);
            var fn = f ? f[1] : null;
            // Route form tiles THROUGH tools.php so the form loads inside the
            // tools chrome (the "Menu" button stays available). The canonical
            // /cma/form/<form> route still works directly — forms are callable
            // both ways.
            return { url: fn ? '/cma/tools?form=' + encodeURIComponent(fn) : null };
        }
        var base = href.split('?')[0];
        var m = base.match(/tools\/tools_([^.]+)\.php$/) || base.match(/tools\/([^.]+)\.php$/);
        if (m) {
            var tool = m[1];
            // Preserve any extra query params (e.g. tools_backup.php?tab=manage) —
            // tools.php forwards them to the iframe src.
            var extra = href.indexOf('?') !== -1 ? '&' + href.substring(href.indexOf('?') + 1) : '';
            return { url: '/cma/tools?tool=' + encodeURIComponent(tool) + extra };
        }
        return { url: null };
    }

    class CmaLauncher extends HTMLElement {
        constructor() {
            super();
            this._loaded = false;
            this._open = false;
            this._onKeydown = this._onKeydown.bind(this);
        }

        connectedCallback() {
            this._catalogUrl = this.getAttribute('catalog-url') || DEFAULT_CATALOG_URL;
            this._target = this.getAttribute('target') || null;
            this._searchPlaceholder = this.getAttribute('search-placeholder') || 'Zoek een tool…';
            this._ariaLabel = this.getAttribute('aria-label') || 'Alle beheerstools';
            this._emptyText = this.getAttribute('empty-text') || 'Geen items beschikbaar.';
        }

        isOpen() { return this._open; }

        _targetEl() {
            return this._target ? document.querySelector(this._target) : null;
        }

        async open() {
            if (this._open) return;
            this._open = true;
            // The menu sits over its host box; the target iframe stays mounted
            // underneath, so there is no iframe show/hide state to keep in sync.
            this.classList.add('is-open');
            if (!this._loaded) {
                this._renderShell('<div class="cma-launcher__loading">Laden…</div>');
                await this._loadCatalog();
                // The catalog load is async: if we were toggled shut while it ran,
                // abort the rest of open() so a dismissed menu isn't resurrected.
                if (!this._open) return;
            }
            document.addEventListener('keydown', this._onKeydown, true);
            var search = this.querySelector('.cma-launcher__search');
            if (search) {
                if (typeof search.clear === 'function') search.clear();
                this._applyFilter('');
                if (typeof search.focus === 'function') search.focus();
            }
            // Highlight the item for whatever is currently open.
            this._markActive();
        }

        // Does a catalog item's target point at what's open in the iframe?
        _activeMatches(el) {
            var t = this._targetEl();
            var src = el.getAttribute('data-src') || '';
            return !!(t && src && (t.getAttribute('src') || '') === src);
        }

        _markActive() {
            var self = this;
            this.querySelectorAll('.cma-launcher__item').forEach(function (a) {
                a.classList.toggle('is-active', self._activeMatches(a));
            });
        }

        close() {
            if (!this._open) return;
            this._open = false;
            this.classList.remove('is-open');
            document.removeEventListener('keydown', this._onKeydown, true);
            // Nothing else to do: closing just hides the panel (display:none via the
            // dropped .is-open class), revealing the always-mounted iframe underneath.
        }

        toggle() { this._open ? this.close() : this.open(); }

        _onKeydown(e) {
            if (e.key !== 'Escape') return;
            // Escape breekt eerst het ZOEKEN af, pas daarna sluit hij het menu. Deze
            // listener hangt op document in de capture-fase (zie open()), dus hij is aan
            // de beurt vóór het zoekveld: zonder onderscheid sluit Escape meteen het hele
            // menu terwijl de gebruiker alleen zijn zoekterm wilde wissen. Juist bij een
            // term zonder treffers is dat de eerste reflex, en dan moet je het menu
            // opnieuw openen om verder te zoeken.
            var search = this.querySelector('.cma-launcher__search');
            var term = (search && typeof search.value === 'string') ? search.value : '';
            if (term) {
                e.preventDefault();
                // De capture-fase afkappen, anders wist het zoekveld zichzelf daarna
                // nog een tweede keer met zijn eigen Escape-afhandeling.
                e.stopPropagation();
                if (typeof search.clear === 'function') { search.clear(); } else { search.value = ''; }
                this._applyFilter('');
                if (typeof search.focus === 'function') search.focus();
                return;
            }
            e.preventDefault();
            this.close();
        }

        async _loadCatalog() {
            try {
                var resp = await fetch(this._catalogUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                var data = await resp.json();
                if (!data || !data.success) throw new Error((data && data.error) || 'Kon de lijst niet laden');
                this._renderCatalog(data.groups || []);
                this._loaded = true;
            } catch (err) {
                this._renderShell('<div class="cma-launcher__error">Kon de lijst niet laden: ' +
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
                '<div class="cma-launcher__panel" role="dialog" aria-modal="false" aria-label="' + this._esc(this._ariaLabel) + '">' +
                    '<div class="cma-launcher__head">' +
                        '<lib-search-input class="cma-launcher__search" icon="left" placeholder="' + this._esc(this._searchPlaceholder) + '" aria-label="' + this._esc(this._searchPlaceholder) + '"></lib-search-input>' +
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

        // Resolve one catalog item to its navigation descriptor: the iframe src,
        // or a new tab for site-root / absolute hrefs.
        _resolveNav(it) {
            var nav = resolveNav(it.href);
            if (nav && nav.external) return nav;
            // The deep-link comes from the item's own url, falling back to the
            // tools route (/cma/tools?tool=…).
            return { src: it.href || '', url: it.url || (nav && nav.url) || null };
        }

        _renderCatalog(groups) {
            var flat = [];
            this._collectGroups(groups, '', flat);
            var self = this;
            var html = flat.map(function (g) {
                var items = g.items.map(function (it) {
                    var nav = self._resolveNav(it);
                    var ext = nav && nav.external;
                    // data-tooltip, niet title: dat is de tooltip die de rest van de CMA ook
                    // gebruikt (style.css), dus dezelfde vorm en geen browser-vertraging.
                    var badge = it.badge ? '<span class="cma-launcher__badge" data-tooltip="Toegangsniveau">' + self._esc(it.badge) + '</span>' : '';
                    var icon = it.icon ? '<span class="cma-launcher__item-icon lnr ' + self._esc(it.icon) + '"></span>' : '';
                    var linkHref = ext ? (nav.url || it.href || '#') : (nav.url || nav.src || '#');
                    return '<a class="cma-launcher__item" href="' + self._esc(linkHref) + '"' +
                        (ext ? ' target="_blank" rel="noopener" data-external="1"' : '') +
                        ' data-src="' + self._esc(ext ? '' : nav.src) + '"' +
                        ' data-url="' + self._esc(nav.url || '') + '"' +
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
            this._renderShell(html || '<div class="cma-launcher__error">' + this._esc(this._emptyText) + '</div>');
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
                    // External site tools are real <a target="_blank"> links — let
                    // the browser open the new tab; just close the launcher.
                    if (a.getAttribute('data-external')) { self.close(); return; }
                    e.preventDefault();
                    self._navigate(a.getAttribute('data-src'), a.getAttribute('data-url'));
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

        _navigate(src, url) {
            var target = this._targetEl();
            if (target && src && target.getAttribute('src') !== src) {
                this._showIframeSpinner(target);
                target.setAttribute('src', src);
            }
            // Closing hides the panel, revealing the iframe (now loading `src`).
            this.close();
            if (url) { try { history.replaceState(null, '', url); } catch (e) {} }
        }

        // Overlay a spinner on the iframe host while the selected tool loads.
        // Some tools (e.g. "Cache leegmaken") do all their work server-side before
        // emitting any HTML, so the iframe stays blank for several seconds — this
        // gives immediate feedback. Hidden on the iframe's `load` event; a 150ms
        // show-delay avoids a flash for fast tools, and a timeout is a safety net.
        _showIframeSpinner(iframe) {
            var host = iframe.parentNode;
            if (!host) return;
            var self = this;
            var sp = host.querySelector('.cma-launcher__iframe-spinner');
            if (!sp) {
                sp = document.createElement('div');
                sp.className = 'cma-launcher__iframe-spinner';
                sp.setAttribute('role', 'status');
                sp.setAttribute('aria-live', 'polite');
                // delay="0": het wachten van 150ms zit al op deze laag, zodat ook
                // het dekkende vlak eronder niet flitst bij een snelle tool.
                sp.innerHTML = '<lib-loader active delay="0" size="medium" text="Laden&hellip;"></lib-loader>';
                sp.style.display = 'none';
                host.appendChild(sp);
            }
            clearTimeout(this._spinnerShowTimer);
            clearTimeout(this._spinnerHideTimer);
            this._spinnerShowTimer = setTimeout(function () { sp.style.display = 'flex'; }, 150);
            var hide = function () {
                clearTimeout(self._spinnerShowTimer);
                clearTimeout(self._spinnerHideTimer);
                sp.style.display = 'none';
            };
            iframe.addEventListener('load', hide, { once: true });
            this._spinnerHideTimer = setTimeout(hide, 60000);
        }
    }

    customElements.define('cma-launcher', CmaLauncher);
})();
