/**
 * <cma-launcher> — the BIG searchable menu (tools AND reports).
 *
 * A full-size, searchable panel listing a catalog of items grouped into
 * sections. One component, two configurations:
 *
 *   nav-mode="shell"  (default) — the admin tools/forms menu. Fetches
 *       /cma/api/tools-catalog.php, renders as an overlay filling #contentArea
 *       (opened from the shell header "Menu" button), and navigates by loading
 *       the selected page into the shell via window.loadPage() + clean URL.
 *
 *   nav-mode="iframe" — an embedded menu that fills its own host container
 *       (e.g. reports.php's #reports). Fetches the catalog named by
 *       `catalog-url`, and on selection loads the item's href into the iframe
 *       named by `target`, hides itself, and writes the item's deep-link `url`
 *       to the address bar. No backdrop, no body scroll-lock.
 *
 * Attributes: catalog-url, nav-mode (shell|iframe), target (iframe selector for
 * iframe mode), search-placeholder, aria-label.
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

    // Shell-mode navigation: work out how a catalog href navigates in the shell.
    // Form-backed items are canonical /cma/form/<form> routes; everything else is
    // a tool page that loads through tools.php (keyed by tool name), full-width.
    function resolveShellNav(href) {
        if (!href) return null;
        // Site-specific tools live at the site root (/tools/…, outside /cma/) or
        // are full URLs — plain scripts, not shell pages. Open them in a new tab.
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
            return fn ? { page: 'tools.php?form=' + fn, url: '/cma/tools?form=' + encodeURIComponent(fn) }
                      : { page: href, url: null };
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

        connectedCallback() {
            this._catalogUrl = this.getAttribute('catalog-url') || DEFAULT_CATALOG_URL;
            this._navMode = this.getAttribute('nav-mode') === 'iframe' ? 'iframe' : 'shell';
            this._embedded = this._navMode === 'iframe';
            this._target = this.getAttribute('target') || null;
            this._searchPlaceholder = this.getAttribute('search-placeholder') || 'Zoek een tool…';
            this._ariaLabel = this.getAttribute('aria-label') || 'Alle beheerstools';
            this._emptyText = this.getAttribute('empty-text') || 'Geen items beschikbaar.';
            this.classList.toggle('cma-launcher--embedded', this._embedded);
        }

        isOpen() { return this._open; }

        _targetEl() {
            return (this._embedded && this._target) ? document.querySelector(this._target) : null;
        }

        async open() {
            if (this._open) return;
            this._open = true;
            this.classList.add('is-open');
            // Overlay (shell) menus dim + lock the page. Embedded menus just sit
            // over their host box — the target iframe stays mounted underneath, so
            // there is no iframe show/hide state to keep in sync.
            if (!this._embedded) {
                document.body.classList.add('cma-launcher-open');
            }
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

        // Does a catalog item's target point at what's open right now?
        _activeMatches(el) {
            if (this._embedded) {
                var t = this._targetEl();
                var src = el.getAttribute('data-src') || '';
                return !!(t && src && (t.getAttribute('src') || '') === src);
            }
            var url = el.getAttribute('data-url') || '';
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
                a.classList.toggle('is-active', self._activeMatches(a));
            });
        }

        close() {
            if (!this._open) return;
            this._open = false;
            this.classList.remove('is-open');
            document.body.classList.remove('cma-launcher-open');
            document.removeEventListener('keydown', this._onKeydown, true);
            // Nothing else to do: closing just hides the panel (display:none via the
            // dropped .is-open class). Embedded menus reveal the always-mounted
            // iframe underneath; overlay menus reveal #contentArea. No state juggling.
        }

        toggle() { this._open ? this.close() : this.open(); }

        _onKeydown(e) {
            if (e.key === 'Escape') { e.preventDefault(); this.close(); }
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
            // Embedded menus fill their host, so they need no dimming backdrop.
            var backdrop = this._embedded ? '' : '<div class="cma-launcher__backdrop" data-close></div>';
            this.innerHTML =
                backdrop +
                '<div class="cma-launcher__panel" role="dialog" aria-modal="' + (this._embedded ? 'false' : 'true') + '" aria-label="' + this._esc(this._ariaLabel) + '">' +
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

        // Resolve one catalog item to its navigation descriptor for the active mode.
        _resolveNav(it) {
            if (this._embedded) {
                // Site-root / absolute hrefs are plain scripts, not CMA pages —
                // resolveShellNav flags them external; open in a new tab here too.
                var nav = resolveShellNav(it.href);
                if (nav && nav.external) return nav;
                // iframe mode: href is the iframe src; deep-link from the item's
                // own url, falling back to the shell route (/cma/tools?tool=…).
                return { iframe: true, src: it.href || '', url: it.url || (nav && nav.url) || null };
            }
            return resolveShellNav(it.href);
        }

        _renderCatalog(groups) {
            var flat = [];
            this._collectGroups(groups, '', flat);
            var self = this;
            var html = flat.map(function (g) {
                var items = g.items.map(function (it) {
                    var nav = self._resolveNav(it);
                    var ext = nav && nav.external;
                    var badge = it.badge ? '<span class="cma-launcher__badge" title="Toegangsniveau">' + self._esc(it.badge) + '</span>' : '';
                    var icon = it.icon ? '<span class="cma-launcher__item-icon lnr ' + self._esc(it.icon) + '"></span>' : '';
                    var linkHref = ext ? (nav.url || it.href || '#')
                        : (nav && nav.iframe ? (nav.url || nav.src || '#')
                        : (nav && nav.url ? nav.url : (it.href || '#')));
                    return '<a class="cma-launcher__item" href="' + self._esc(linkHref) + '"' +
                        (ext ? ' target="_blank" rel="noopener" data-external="1"' : '') +
                        ' data-page="' + self._esc(nav ? (nav.page || '') : '') + '"' +
                        ' data-src="' + self._esc(nav && nav.iframe ? nav.src : '') + '"' +
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
                    if (self._embedded) {
                        self._navigateIframe(a.getAttribute('data-src'), a.getAttribute('data-url'));
                    } else {
                        self._navigateShell(a.getAttribute('data-page'), a.getAttribute('data-url'));
                    }
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

        _navigateShell(page, url) {
            this.close();
            if (page && typeof window.loadPage === 'function') {
                window.loadPage(page);
                if (url) { try { history.pushState(null, '', url); } catch (e) {} }
            } else if (url) {
                window.location.href = url;
            }
        }

        _navigateIframe(src, url) {
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
                sp.innerHTML = '<div class="cma-launcher__spinner-ring"></div>' +
                    '<div class="cma-launcher__spinner-text">Laden&hellip;</div>';
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
