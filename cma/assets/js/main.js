/**
 * CMA Main Application JavaScript
 * Handles sidebar, navigation, page loading, and user interactions
 */

(function() {
    'use strict';

    // =========================================================================
    // Service Worker Registration - enables persistent form template caching
    // =========================================================================
    if ('serviceWorker' in navigator && location.protocol !== 'file:') {
        navigator.serviceWorker.register('/cma/sw.js', { scope: '/cma/' })
            .then(function(reg) {
                // Log via cmaLog to respect debug preference
                // if (typeof cmaLog !== 'undefined') {
                //     cmaLog.log('[SW] Registered:', reg.scope);
                // }
            })
            .catch(function(err) {
                // Always log errors - SW issues need to be visible
                cmaLog.error('[SW] Registration FAILED:', err.message || err);
            });
    }

    // =========================================================================
    // Debug Logging - writes to server-side file for analysis
    // Only active when CMA_DEBUG is true (set via environment/cookie)
    // =========================================================================
    function debugLog(source, info, level) {
        // Only send debug logs when explicitly enabled
        if (!window.CMA_DEBUG) return;

        try {
            navigator.sendBeacon('api/log.php?type=debug', JSON.stringify({
                source: source,
                info: info,
                level: level || 'debug',
                timestamp: new Date().toISOString(),
                url: window.location.href
            }));
        } catch (e) {
            // Log to console if beacon fails - don't lose debug info completely
            cmaLog.warn('[debugLog] sendBeacon failed:', e.message, '| source:', source);
        }
    }
    // Expose for use in other modules
    window._cmaDebugLog = debugLog;

    // Current page state
    let currentPage = '';

    // =========================================================================
    // DOM Page Cache - Instant form navigation by caching DOM + controller
    // =========================================================================
    const _pageCache = new Map();
    const MAX_CACHED_PAGES = 5;

    /**
     * Check if a page should be cached (forms, dashboard, preferences — NOT tools)
     */
    function shouldCachePage(page) {
        if (!page) return false;
        return /^form\.php\?form=/i.test(page) ||
               /^dashboard\.php$/i.test(page) ||
               /^preferences\.php$/i.test(page);
    }

    /**
     * Cache the current page by suspending its controller and detaching DOM
     */
    function cacheCurrentPage(page) {
        if (!page || !shouldCachePage(page)) return;

        const contentArea = getCachedContentArea();
        if (!contentArea) { return; }

        const wrapper = contentArea.querySelector('.cma-content-inner');
        if (!wrapper) { return; }

        // Suspend the controller (removes doc/window listeners, saves state)
        const formLayout = wrapper.querySelector('.form-layout');
        const controller = formLayout?._cmaController;
        if (controller && typeof controller.suspend === 'function') {
            controller.suspend();
        }

        // Detach the wrapper from the DOM (keeps all children and listeners intact)
        wrapper.remove();

        // Store in cache
        _pageCache.set(page, {
            wrapper: wrapper,
            timestamp: Date.now()
        });

        // Evict oldest if over limit
        evictLRU();
    }

    /**
     * Restore a cached page by reattaching DOM and resuming controller
     * @returns {boolean} true if restored from cache
     */
    function restoreFromCache(page) {
        const entry = _pageCache.get(page);
        if (!entry) return false;

        const contentArea = getCachedContentArea();
        if (!contentArea) return false;

        // Clear content area and reattach cached wrapper
        contentArea.innerHTML = '';
        contentArea.appendChild(entry.wrapper);

        // Remove from cache (it's now live in the DOM)
        _pageCache.delete(page);

        // Resume the controller (re-adds listeners, refreshes list)
        const formLayout = entry.wrapper.querySelector('.form-layout');
        const controller = formLayout?._cmaController;
        if (controller && typeof controller.resume === 'function') {
            controller.resume();
        }

        return true;
    }

    /**
     * Evict least-recently-used entries when cache exceeds limit
     */
    function evictLRU() {
        while (_pageCache.size > MAX_CACHED_PAGES) {
            let oldestKey = null;
            let oldestTime = Infinity;
            for (const [key, entry] of _pageCache) {
                if (entry.timestamp < oldestTime) {
                    oldestTime = entry.timestamp;
                    oldestKey = key;
                }
            }
            if (oldestKey) {
                const evicted = _pageCache.get(oldestKey);
                // Properly destroy the controller before discarding
                const formLayout = evicted.wrapper.querySelector('.form-layout');
                const controller = formLayout?._cmaController;
                if (controller && typeof controller.destroy === 'function') {
                    controller.destroy();
                }
                _pageCache.delete(oldestKey);
            }
        }
    }

    /**
     * Clear the entire page cache, destroying all controllers
     */
    function clearPageCache() {
        for (const [key, entry] of _pageCache) {
            const formLayout = entry.wrapper.querySelector('.form-layout');
            const controller = formLayout?._cmaController;
            if (controller && typeof controller.destroy === 'function') {
                controller.destroy();
            }
        }
        _pageCache.clear();
    }

    // PERFORMANCE FIX: Cache DOM element references to avoid repeated queries
    let _activeMenuItem = null;
    let _sidebar = null;
    let _menuGroups = null; // Map of menuId -> group element
    let _breadcrumb = null;
    let _contentArea = null;

    // Initialize cached references
    function initCachedElements() {
        _sidebar = document.getElementById('sidebar');
        _breadcrumb = getCachedBreadcrumb();
        _contentArea = document.getElementById('contentArea');

        // Cache menu groups by ID for O(1) lookup
        _menuGroups = new Map();
        document.querySelectorAll('.cma-menu-group[data-menu-id]').forEach(group => {
            _menuGroups.set(group.dataset.menuId, group);
        });
    }

    function getCachedMenuGroup(menuId) {
        if (!_menuGroups) initCachedElements();
        return _menuGroups.get(String(menuId));
    }

    function getCachedSidebar() {
        if (!_sidebar) _sidebar = document.getElementById('sidebar');
        return _sidebar;
    }

    function getCachedBreadcrumb() {
        if (!_breadcrumb) _breadcrumb = document.getElementById('breadcrumb');
        return _breadcrumb;
    }

    function getCachedContentArea() {
        if (!_contentArea) _contentArea = document.getElementById('contentArea');
        return _contentArea;
    }

    // Delayed loading spinner timeout (shows after 2 seconds)
    let _loadingSpinnerTimeout = null;
    const LOADING_SPINNER_DELAY = 2000; // 2 seconds

    // PERFORMANCE FIX: Off-screen staging container for smooth content loading
    // Content is rendered off-screen first, then swapped in when ready
    let _stagingContainer = null;

    function getStagingContainer() {
        if (!_stagingContainer) {
            _stagingContainer = document.createElement('div');
            _stagingContainer.id = 'cma-staging';
            _stagingContainer.className = 'cma-content-inner';
            // Position off-screen but still renderable (not display:none)
            // This allows scripts to execute and elements to compute sizes
            _stagingContainer.style.cssText = 'position:fixed;left:-9999px;top:0;width:100%;visibility:hidden;pointer-events:none;';
            document.body.appendChild(_stagingContainer);
        }
        return _stagingContainer;
    }

    /**
     * Clear loading state from content area (both immediate and delayed)
     * @param {HTMLElement} contentArea - The content area element
     */
    function clearLoadingState(contentArea) {
        if (_loadingSpinnerTimeout) {
            clearTimeout(_loadingSpinnerTimeout);
            _loadingSpinnerTimeout = null;
        }
        contentArea.classList.remove('loading');
        contentArea.classList.remove('loading-delayed');
    }

    // Lazy-load form-controller.js when needed
    let formControllerLoaded = false;
    let formControllerLoading = null;

    // PHP error extraction/formatting now uses shared cmaErrorParser from cma-utils.js

    function loadFormController() {
        if (formControllerLoaded) {
            return Promise.resolve();
        }
        if (formControllerLoading) {
            return formControllerLoading;
        }
        formControllerLoading = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'assets/js/form-controller.js';
            script.onload = () => {
                formControllerLoaded = true;
                formControllerLoading = null;
                resolve();
            };
            script.onerror = () => {
                formControllerLoading = null;
                reject(new Error('Failed to load form-controller.js'));
            };
            document.head.appendChild(script);
        });
        return formControllerLoading;
    }

    // =========================================================================
    // User Dropdown
    // =========================================================================

    // Close dropdown when clicking outside (for touch devices)
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('userDropdown');
        if (dropdown && !e.target.closest('.cma-user-menu')) {
            dropdown.classList.remove('open');
        }
    });

    // =========================================================================
    // Password Modal (lazy loaded)
    // =========================================================================

    let passwordModalLoaded = false;

    function createPasswordModal() {
        if (passwordModalLoaded) return;
        passwordModalLoaded = true;

        const modal = document.createElement('lib-dialog');
        modal.id = 'passwordModal';
        modal.setAttribute('heading', 'Wachtwoord wijzigen');
        modal.setAttribute('size', 'small');
        modal.innerHTML = `
            <div class="dialog-message" id="pwdMessage" style="display: none;"></div>
            <form id="passwordForm" class="pwd-dialog-form">
                <div class="form-group">
                    <label for="oldPassword">Huidig wachtwoord</label>
                    <input type="password" name="old_password" id="oldPassword" autocomplete="current-password" required>
                </div>
                <div class="form-group">
                    <label for="newPassword">Nieuw wachtwoord</label>
                    <input type="password" name="new_password" id="newPassword" autocomplete="new-password" required>
                </div>
            </form>
            <div slot="footer">
                <button type="button" class="btn-cancel" onclick="document.getElementById('passwordModal').close()">Annuleren</button>
                <button type="submit" form="passwordForm" class="btn-primary">Wijzigen</button>
            </div>
        `;
        document.body.appendChild(modal);

        // Attach form submit handler
        document.getElementById('passwordForm').addEventListener('submit', submitPasswordChange);
    }

    window.openPasswordModal = function() {
        document.getElementById('userDropdown').classList.remove('open');
        createPasswordModal();
        const modal = document.getElementById('passwordModal');
        modal.open();
        document.getElementById('oldPassword').value = '';
        document.getElementById('newPassword').value = '';
        document.getElementById('pwdMessage').style.display = 'none';
        document.getElementById('oldPassword').focus();
    };

    function submitPasswordChange(e) {
        e.preventDefault();
        const oldPwd = document.getElementById('oldPassword').value;
        const newPwd = document.getElementById('newPassword').value;
        const msgEl = document.getElementById('pwdMessage');

        if (!oldPwd || !newPwd) {
            msgEl.className = 'dialog-message error';
            msgEl.textContent = 'Vul beide velden in';
            msgEl.style.display = 'block';
            return false;
        }

        fetch('api/change-password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'old_password=' + encodeURIComponent(oldPwd) + '&new_password=' + encodeURIComponent(newPwd)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                msgEl.className = 'dialog-message success';
                msgEl.textContent = 'Wachtwoord is gewijzigd';
                msgEl.style.display = 'block';
                setTimeout(() => document.getElementById('passwordModal').close(), 1500);
            } else {
                msgEl.className = 'dialog-message error';
                msgEl.textContent = data.message || 'Wachtwoord kon niet worden gewijzigd';
                msgEl.style.display = 'block';
            }
        })
        .catch(() => {
            msgEl.className = 'dialog-message error';
            msgEl.textContent = 'Fout bij het wijzigen van het wachtwoord';
            msgEl.style.display = 'block';
        });

        return false;
    }

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('passwordModal');
            if (modal) modal.close();
        }
    });

    // =========================================================================
    // Menu Group Expand/Collapse
    // =========================================================================

    // Check if we're in classic mode (horizontal tabs)
    function isClassicMode() {
        return document.documentElement.classList.contains('classic-mode');
    }

    window.toggleMenuGroup = function(menuId) {
        const group = getCachedMenuGroup(menuId);
        if (!group) return;

        // Classic mode: toggle 'open' class for dropdown
        if (isClassicMode()) {
            const wasOpen = group.classList.contains('open');
            // Close all other open menus first
            if (!_menuGroups) initCachedElements();
            _menuGroups.forEach((g) => {
                if (g !== group) g.classList.remove('open');
            });
            // Toggle this menu
            group.classList.toggle('open');
            return;
        }

        // Sidebar mode: toggle 'expanded' class
        const wasExpanded = group.classList.contains('expanded');
        group.classList.toggle('expanded');
        saveMenuState();

        // If now expanded, scroll to ensure last menu item is fully visible
        if (!wasExpanded && group.classList.contains('expanded')) {
            // Wait for CSS transition to complete
            setTimeout(function() {
                const items = group.querySelectorAll('.cma-menu-group-items .cma-menu-item');
                if (items.length > 0) {
                    const lastItem = items[items.length - 1];
                    // Use sidebarNav (the scrollable element), not sidebar
                    const sidebarNav = document.getElementById('sidebarNav');
                    if (sidebarNav) {
                        const lastItemRect = lastItem.getBoundingClientRect();
                        const sidebarNavRect = sidebarNav.getBoundingClientRect();

                        // Check if last item bottom is below sidebar nav visible area
                        const itemBottom = lastItemRect.bottom;
                        const sidebarNavBottom = sidebarNavRect.bottom;

                        if (itemBottom > sidebarNavBottom) {
                            // Calculate how much to scroll: difference + item height for padding
                            const scrollAmount = (itemBottom - sidebarNavBottom) + lastItemRect.height;
                            sidebarNav.scrollBy({ top: scrollAmount, behavior: 'smooth' });
                        }
                    }
                }
            }, 150);
        }
    };

    // Classic mode: close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!isClassicMode()) return;

        const clickedGroup = e.target.closest('.cma-menu-group');
        if (!clickedGroup) {
            // Clicked outside any menu group - close all
            if (!_menuGroups) initCachedElements();
            _menuGroups.forEach((g) => g.classList.remove('open'));
        }
    });

    function saveMenuState() {
        const state = {};
        // PERFORMANCE: Use cached menu groups map
        if (!_menuGroups) initCachedElements();
        _menuGroups.forEach((group, menuId) => {
            state[menuId] = group.classList.contains('expanded');
        });
        try {
            localStorage.setItem('cma_v2_menu_state', JSON.stringify(state));
        } catch (e) {
            cmaLog.warn('[Menu] Failed to save menu state to localStorage:', e.message);
        }
    }

    function restoreMenuState() {
        const saved = localStorage.getItem('cma_v2_menu_state');
        if (saved) {
            try {
                const state = JSON.parse(saved);
                Object.keys(state).forEach(menuId => {
                    // PERFORMANCE: Use cached menu group lookup
                    const group = getCachedMenuGroup(menuId);
                    if (group && state[menuId]) {
                        group.classList.add('expanded');
                    }
                });
            } catch (e) {
                cmaLog.error('[Menu] Error restoring menu state:', e.message);
            }
        }
    }

    // =========================================================================
    // Sidebar Toggle
    // =========================================================================

    window.toggleSidebar = function(forceState) {
        // PERFORMANCE: Use cached sidebar reference
        const sidebar = getCachedSidebar();
        const menuCheckbox = document.getElementById('menuToggleCheckbox');
        const isMobile = window.innerWidth <= 768;

        if (isMobile) {
            if (typeof forceState === 'boolean') {
                sidebar.classList.toggle('open', forceState);
            } else {
                sidebar.classList.toggle('open');
            }
            // Sync checkbox state with sidebar
            if (menuCheckbox) {
                menuCheckbox.checked = sidebar.classList.contains('open');
            }
            // On mobile, remove collapsed class when menu is open to show full menu
            if (sidebar.classList.contains('open')) {
                sidebar.classList.remove('collapsed');
            } else {
                // Restore collapsed state from localStorage when closing on mobile
                if (localStorage.getItem('cma_v2_menu_collapsed') === 'true') {
                    sidebar.classList.add('collapsed');
                }
            }
        } else {
            sidebar.classList.toggle('collapsed');
            try {
                localStorage.setItem('cma_v2_menu_collapsed', sidebar.classList.contains('collapsed'));
            } catch (e) {
                cmaLog.warn('[Menu] Failed to save sidebar state to localStorage:', e.message);
            }
            // Hide any open popups when toggling sidebar
            hideAllPopups();
        }
    };

    // Handle hamburger menu checkbox toggle
    function initMenuToggle() {
        const menuCheckbox = document.getElementById('menuToggleCheckbox');
        if (menuCheckbox) {
            menuCheckbox.addEventListener('change', function() {
                toggleSidebar(this.checked);
            });
        }
    }

    // =========================================================================
    // Collapsed Sidebar Popup Menu
    // =========================================================================

    let _popupHideTimeout = null;
    let _currentPopupMenuGroup = null;
    const POPUP_HIDE_DELAY = 800; // milliseconds

    function showMenuPopup(menuGroup) {
        // Only show popups when sidebar is collapsed
        // PERFORMANCE: Use cached sidebar reference
        const sidebar = getCachedSidebar();
        if (!sidebar || !sidebar.classList.contains('collapsed')) {
            return;
        }

        // Clear any pending hide timeout
        clearTimeout(_popupHideTimeout);

        // Hide all other popups
        document.querySelectorAll('.cma-menu-group.popup-active').forEach(function(g) {
            if (g !== menuGroup) {
                g.classList.remove('popup-active');
            }
        });

        // Show this popup
        menuGroup.classList.add('popup-active');
        _currentPopupMenuGroup = menuGroup;
    }

    function cancelHidePopup() {
        clearTimeout(_popupHideTimeout);
    }

    function scheduleHidePopup(menuGroup) {
        clearTimeout(_popupHideTimeout);
        _popupHideTimeout = setTimeout(function() {
            // Only hide if mouse is not over either the menu group or the popup
            if (_currentPopupMenuGroup === menuGroup) {
                menuGroup.classList.remove('popup-active');
                _currentPopupMenuGroup = null;
            }
        }, POPUP_HIDE_DELAY);
    }

    function hideAllPopups() {
        clearTimeout(_popupHideTimeout);
        _currentPopupMenuGroup = null;
        document.querySelectorAll('.cma-menu-group.popup-active').forEach(function(g) {
            g.classList.remove('popup-active');
        });
    }

    function initPopupMenus() {
        // Attach hover events to menu groups
        document.querySelectorAll('.cma-menu-group:not(.single-item)').forEach(function(menuGroup) {
            const popup = menuGroup.querySelector('.cma-menu-popup');
            const header = menuGroup.querySelector('.cma-menu-group-header');
            if (!popup) return;

            // Show popup on hover over menu group
            menuGroup.addEventListener('mouseenter', function() {
                showMenuPopup(menuGroup);
            });

            // Schedule hide on mouse leave from menu group
            menuGroup.addEventListener('mouseleave', function() {
                scheduleHidePopup(menuGroup);
            });

            // Cancel hide when hovering over the header (icon area)
            if (header) {
                header.addEventListener('mouseenter', function() {
                    cancelHidePopup();
                });
            }

            // Cancel hide when hovering over the popup itself
            popup.addEventListener('mouseenter', function() {
                cancelHidePopup();
            });

            // Schedule hide when leaving popup
            popup.addEventListener('mouseleave', function() {
                scheduleHidePopup(menuGroup);
            });

            // Handle popup item clicks
            popup.querySelectorAll('.cma-menu-popup-item').forEach(function(item) {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const page = this.dataset.page || this.getAttribute('href');

                    // Update active state
                    if (_activeMenuItem) {
                        _activeMenuItem.classList.remove('active');
                    }

                    // Find corresponding main menu item and mark as active
                    const mainItem = menuGroup.querySelector('.cma-menu-item[data-page="' + CSS.escape(page) + '"]');
                    if (mainItem) {
                        mainItem.classList.add('active');
                        _activeMenuItem = mainItem;
                    }

                    // Update breadcrumb
                    const breadcrumb = getCachedBreadcrumb();
                    const itemText = this.querySelector('.cma-menu-popup-text');
                    if (breadcrumb && itemText) {
                        breadcrumb.textContent = itemText.textContent;
                    }

                    // Hide popup and load page
                    hideAllPopups();
                    loadPage(page);
                });
            });
        });
    }

    // =========================================================================
    // Page Loading
    // =========================================================================

    function isExternalUrl(page) {
        return page.startsWith('../') ||
               page.startsWith('http://') ||
               page.startsWith('https://') ||
               page.startsWith('/') ||
               page.includes('/cma_');
    }

    function loadPageInIframe(page, updateHistory) {
        const contentArea = document.getElementById('contentArea');
        clearLoadingState(contentArea);

        const iframe = document.createElement('iframe');
        iframe.className = 'cma-external-iframe';
        iframe.src = page;
        iframe.setAttribute('frameborder', '0');
        iframe.setAttribute('allowfullscreen', 'true');

        contentArea.innerHTML = '';
        contentArea.appendChild(iframe);

        currentPage = page;

        if (updateHistory) {
            // Use absolute path to avoid relative URL issues
            const newUrl = '/cma/main.php?page=' + encodeURIComponent(page);
            history.pushState({ page: page }, '', newUrl);
        }

        setActiveMenuItem(page);
        if (typeof cmaPerf !== 'undefined') cmaPerf.count('loadPage.iframe');
    }

    window.loadPage = async function(page, updateHistory) {
        if (updateHistory === undefined) updateHistory = true;
        if (!page || page === 'about:blank') return;

        // Check for unsaved changes before navigating
        if (typeof window.cmaCheckUnsavedChanges === 'function') {
            const canLeave = await window.cmaCheckUnsavedChanges();
            if (!canLeave) {
                // cmaLog.log('[loadPage] Navigation cancelled - unsaved changes');
                return;
            }
        }

        // CRITICAL: Reset inline edit global state FIRST, before any other operations
        // This clears the request batcher which may have pending saves for the old form
        // Must happen immediately to prevent stale form references in event handlers
        if (typeof window.cmaResetInlineEditState !== 'function') {
            throw new Error('cmaResetInlineEditState is not available - inline-edit.js not loaded');
        }
        window.cmaResetInlineEditState();

        // cmaLog.log('[loadPage] Loading:', page);

        if (typeof cmaPerf !== 'undefined') {
            cmaPerf.mark('loadPage_start');
        }

        const contentArea = document.getElementById('contentArea');
        if (!contentArea) {
            cmaLog.warn('[loadPage] contentArea not found');
            return;
        }

        // ── DOM Page Cache: check for cached version ──
        if (_pageCache.has(page)) {
            if (typeof cmaPerf !== 'undefined') cmaPerf.count('loadPage.cacheHit');

            // Cache the current page before restoring the target
            if (currentPage && shouldCachePage(currentPage)) {
                cacheCurrentPage(currentPage);
            }

            // Clear form state classes (resume() will restore the right ones)
            document.body.classList.remove('has-record', 'is-creating', 'mode-detail', 'mode-tree', 'mode-table', 'has-subform', 'is-dirty', 'data-loading');
            document.body.classList.remove('tools');

            // Restore from cache
            restoreFromCache(page);
            clearLoadingState(contentArea);

            // Update page state and URL
            currentPage = page;
            if (updateHistory) {
                let newUrl;
                const formMatch = page.match(/form\.php\?form=([^&]+)/i);
                const simplePageMatch = page.match(/^(dashboard|tools|preferences)\.php$/i);
                if (formMatch && window.CMA && window.CMA.url) {
                    const formName = decodeURIComponent(formMatch[1]);
                    newUrl = window.CMA.url.build({ form: formName });
                } else if (simplePageMatch) {
                    newUrl = '/cma/' + simplePageMatch[1].toLowerCase();
                } else {
                    newUrl = '/cma/main.php?page=' + encodeURIComponent(page);
                }
                history.pushState({ page: page }, '', newUrl);
            }

            setActiveMenuItem(page);

            // Update breadcrumb and document title from cached content
            const cachedTitle = contentArea.querySelector('.toolbar-title');
            if (cachedTitle) {
                const titleText = cachedTitle.textContent.trim();
                const breadcrumb = getCachedBreadcrumb();
                if (breadcrumb) {
                    breadcrumb.textContent = titleText;
                }
                document.title = titleText + ' | CMA';
            }

            if (typeof cmaPerf !== 'undefined') {
                cmaPerf.mark('loadPage_end');
                cmaPerf.measure('loadPage.total', 'loadPage_start', 'loadPage_end');
            }
            return;
        }

        // ── Cache MISS: proceed with server fetch ──
        // Cache miss — fetch from server
        if (typeof cmaPerf !== 'undefined') cmaPerf.count('loadPage.cacheMiss');

        // Cache the current page before destroying it (if cacheable)
        if (currentPage && shouldCachePage(currentPage)) {
            cacheCurrentPage(currentPage);
        }

        contentArea.classList.add('loading');

        // Clear any existing delayed spinner timeout
        if (_loadingSpinnerTimeout) {
            clearTimeout(_loadingSpinnerTimeout);
            _loadingSpinnerTimeout = null;
        }
        contentArea.classList.remove('loading-delayed');

        // Start delayed spinner - shows after 2 seconds if still loading
        _loadingSpinnerTimeout = setTimeout(function() {
            if (contentArea.classList.contains('loading')) {
                contentArea.classList.add('loading-delayed');
            }
        }, LOADING_SPINNER_DELAY);

        if (isExternalUrl(page)) {
            // cmaLog.log('[loadPage] External URL, using iframe');
            loadPageInIframe(page, updateHistory);
            return;
        }

        // Use absolute path to handle clean URLs (e.g., /cma/form/xxx resolves correctly)
        const url = '/cma/main.php?nomenu&page=' + encodeURIComponent(page);
        // cmaLog.log('[loadPage] Fetching URL:', url);

        if (typeof cmaPerf !== 'undefined') cmaPerf.mark('loadPage_fetchStart');

        fetch(url, {
            credentials: 'same-origin' // Explicitly include cookies for same-origin
        })
            .then(response => {
                if (typeof cmaPerf !== 'undefined') {
                    cmaPerf.mark('loadPage_fetchEnd');
                    cmaPerf.measure('loadPage.network', 'loadPage_fetchStart', 'loadPage_fetchEnd');
                }
                if (!response.ok) {
                    cmaLog.warn('[loadPage] HTTP Error:', response.status, response.statusText);

                    // For HTTP errors, try to get the response body for error details
                    return response.text().then(html => {
                        // For 401/403, use server's error HTML if it contains a proper message
                        // This shows user-friendly "Sessie verlopen" or "Geen toegang" messages
                        if ((response.status === 401 || response.status === 403) &&
                            (html.includes('lib-message') || html.includes('session-expired-message'))) {
                            const error = new Error(response.status === 401 ? 'Sessie verlopen' : 'Geen toegang');
                            error.serverHtml = html;
                            throw error;
                        }
                        // For 404, use server's friendly HTML response directly
                        if (response.status === 404 && html.includes('Oeps')) {
                            const error = new Error('404');
                            error.serverHtml = html;
                            throw error;
                        }
                        // Try to extract PHP error from response
                        const phpError = window.cmaErrorParser.extract(html);
                        if (phpError) {
                            const error = new Error(phpError.message);
                            error.phpError = phpError;
                            throw error;
                        }
                        // Generic HTTP error - show user-friendly message
                        const statusMessages = {
                            400: 'Ongeldige aanvraag',
                            401: 'Je bent niet ingelogd. Log opnieuw in.',
                            403: 'Je hebt geen toegang tot deze pagina.',
                            404: 'Pagina niet gevonden',
                            500: 'Er is een serverfout opgetreden. Probeer het later opnieuw.',
                            502: 'De server is tijdelijk niet beschikbaar.',
                            503: 'De server is tijdelijk niet beschikbaar.',
                        };
                        const message = statusMessages[response.status] || 'Er is een fout opgetreden (HTTP ' + response.status + ')';
                        throw new Error(message);
                    });
                }
                return response.text();
            })
            .then(html => {
                if (typeof cmaPerf !== 'undefined') {
                    cmaPerf.mark('loadPage_renderStart');
                }

                // Destroy previous form controller IF it wasn't already cached/suspended
                // cacheCurrentPage() detaches the DOM, so querySelector won't find it if cached
                const existingFormLayout = document.querySelector('.form-layout');
                const existingController = existingFormLayout?._cmaController;
                if (existingController && typeof existingController.destroy === 'function') {
                    const currentFormName = (existingController.jsonForm || existingController.formName || '').toLowerCase();
                    // cmaLog.log('[loadPage] Destroying controller from .form-layout element (was:', currentFormName, ')');
                    existingController.destroy();
                    if (existingFormLayout) {
                        existingFormLayout._cmaController = null;
                    }
                }

                // Clear previous form state classes before loading new content
                document.body.classList.remove('has-record', 'is-creating', 'mode-detail', 'mode-tree', 'mode-table', 'has-subform', 'is-dirty', 'data-loading');

                // Extract and execute body class script BEFORE inserting HTML
                // This prevents flash where content is hidden due to missing classes
                const bodyClassMatch = html.match(/<script>\(function\(\)\{var c=(\[[^\]]+\]);c\.forEach[^<]+<\/script>/);
                if (bodyClassMatch && bodyClassMatch[1]) {
                    try {
                        const classes = JSON.parse(bodyClassMatch[1]);
                        classes.forEach(cls => document.body.classList.add(cls));
                    } catch (e) {
                        cmaLog.warn('Failed to parse body classes:', e);
                    }
                }

                // CRITICAL: Clear old content BEFORE staging new content
                // This ensures document.querySelector('.form-layout') in CmaFormController
                // finds the NEW form-layout (in staging), not the OLD one (in contentArea)
                contentArea.innerHTML = '';

                // Note: cmaCheckUnsavedChanges is now a persistent DOM-driven handler,
                // no need to clear per page.

                // PERFORMANCE FIX: Render content off-screen first, then swap in
                // This prevents the user from seeing content "building" progressively
                const staging = getStagingContainer();
                staging.innerHTML = html;

                if (typeof cmaPerf !== 'undefined') {
                    cmaPerf.mark('loadPage_renderEnd');
                    cmaPerf.measure('loadPage.domInsert', 'loadPage_renderStart', 'loadPage_renderEnd');
                }

                // Extract title and update breadcrumb
                let pageTitle = '';
                const titleMatch = html.match(/<title>([^<]*)<\/title>/i);
                if (titleMatch && titleMatch[1]) {
                    pageTitle = titleMatch[1].trim();
                    // Remove " | Content Management Applicatie" suffix if present
                    pageTitle = pageTitle.replace(/\s*\|\s*Content Management Applicatie$/i, '');
                }
                if (!pageTitle) {
                    const toolbarTitle = staging.querySelector('.toolbar-title');
                    if (toolbarTitle) {
                        pageTitle = toolbarTitle.textContent.trim();
                    }
                }
                // For form pages: extract name from CMA.formConfig in the response
                if (!pageTitle) {
                    const configMatch = html.match(/"?formName"?\s*:\s*"([^"]+)"/);
                    if (configMatch) {
                        pageTitle = configMatch[1];
                    }
                }
                if (pageTitle) {
                    const breadcrumb = getCachedBreadcrumb();
                    if (breadcrumb) {
                        breadcrumb.textContent = pageTitle;
                    }
                    document.title = pageTitle + ' | CMA';
                }

                // Check if loaded page has body.tools class and apply to main body
                const bodyMatch = html.match(/<body[^>]*class=["']([^"']*)["']/i);
                if (bodyMatch && bodyMatch[1] && bodyMatch[1].includes('tools')) {
                    document.body.classList.add('tools');
                } else {
                    document.body.classList.remove('tools');
                }

                currentPage = page;

                if (updateHistory) {
                    // Use clean URL format where supported
                    let newUrl;
                    const formMatch = page.match(/form\.php\?form=([^&]+)/i);
                    const simplePageMatch = page.match(/^(dashboard|tools|preferences)\.php$/i);

                    if (formMatch && window.CMA && window.CMA.url) {
                        // Form pages: /cma/form/formname
                        const formName = decodeURIComponent(formMatch[1]);
                        newUrl = window.CMA.url.build({ form: formName });
                    } else if (simplePageMatch) {
                        // Simple pages: /cma/dashboard, /cma/tools, etc.
                        newUrl = '/cma/' + simplePageMatch[1].toLowerCase();
                    } else {
                        // Fallback: use absolute path to avoid relative URL issues
                        newUrl = '/cma/main.php?page=' + encodeURIComponent(page);
                    }
                    history.pushState({ page: page }, '', newUrl);
                }

                if (typeof cmaPerf !== 'undefined') cmaPerf.mark('loadPage_scriptsStart');

                // Check if page needs form-controller.js (contains CmaFormController references)
                const needsFormController = html.includes('CmaFormController') || html.includes('initForm');

                const runScripts = () => {
                    // Execute scripts in staging container (off-screen)
                    executeScripts(staging);

                    if (typeof cmaPerf !== 'undefined') {
                        cmaPerf.mark('loadPage_scriptsEnd');
                        cmaPerf.measure('loadPage.scripts', 'loadPage_scriptsStart', 'loadPage_scriptsEnd');
                    }

                    // PERFORMANCE FIX: Use requestAnimationFrame to batch the DOM swap
                    // This ensures browser has finished processing scripts before showing
                    requestAnimationFrame(() => {
                        // Swap staging content into visible area (contentArea already cleared earlier)
                        const wrapper = document.createElement('div');
                        wrapper.className = 'cma-content-inner';
                        // Move all children from staging to wrapper
                        while (staging.firstChild) {
                            wrapper.appendChild(staging.firstChild);
                        }
                        contentArea.appendChild(wrapper);

                        clearLoadingState(contentArea);
                        setActiveMenuItem(page);

                        if (typeof cmaPerf !== 'undefined') {
                            cmaPerf.mark('loadPage_end');
                            cmaPerf.measure('loadPage.total', 'loadPage_start', 'loadPage_end');
                            cmaPerf.count('loadPage.success');
                        }
                    });
                };

                if (needsFormController && typeof CmaFormController === 'undefined') {
                    loadFormController().then(runScripts).catch(err => {
                        cmaLog.error('[loadPage] Failed to load form-controller.js:', err);
                        // Show error to user - don't silently continue
                        const staging = getStagingContainer();
                        staging.innerHTML = '<lib-message type="error">Kritieke fout: form-controller.js kon niet worden geladen. Ververs de pagina.</lib-message>';
                        // Still swap content to show the error
                        requestAnimationFrame(() => {
                            const wrapper = document.createElement('div');
                            wrapper.className = 'cma-content-inner';
                            while (staging.firstChild) {
                                wrapper.appendChild(staging.firstChild);
                            }
                            contentArea.appendChild(wrapper);
                            clearLoadingState(contentArea);
                        });
                    });
                } else {
                    runScripts();
                }
            })
            .catch(error => {
                if (typeof cmaPerf !== 'undefined') {
                    cmaPerf.count('loadPage.errors');
                }

                // Report to dev error panel
                if (typeof CmaErrorHandler !== 'undefined') {
                    CmaErrorHandler.reportServerError('loadPage', error.message, {
                        url: page,
                        debug: error.phpError || null
                    });
                }

                // Use server's HTML response directly (for friendly 404 pages)
                if (error.serverHtml) {
                    contentArea.innerHTML = '<div class="cma-content-inner">' + error.serverHtml + '</div>';
                    clearLoadingState(contentArea);
                    return;
                }

                // Format error message - use PHP error details if available
                let errorHtml = 'Fout bij laden: ' + error.message;
                if (error.phpError) {
                    errorHtml = window.cmaErrorParser.format(error.phpError);
                    cmaLog.error('[loadPage] PHP Error:', error.phpError);
                }

                // Use lib-message web component for better error display
                contentArea.innerHTML = '<div class="cma-content-inner" style="padding: 20px;"><lib-message type="error">' + errorHtml + '</lib-message></div>';
                clearLoadingState(contentArea);
            });
    };

    /**
     * Load a page via POST request (for form submissions in AJAX mode)
     * @param {string} page - The page URL
     * @param {FormData|HTMLFormElement} formData - FormData object or form element
     */
    window.loadPagePost = function(page, formData) {
        // CRITICAL: Reset inline edit global state FIRST, before any other operations
        if (typeof window.cmaResetInlineEditState === 'function') {
            window.cmaResetInlineEditState();
        }

        const contentArea = document.getElementById('contentArea');

        // If no contentArea (e.g., tools page loaded directly), fall back to form submit
        if (!contentArea) {
            if (formData instanceof HTMLFormElement) {
                formData.submit();
            }
            return;
        }

        contentArea.classList.add('loading');

        // Clear any existing delayed spinner timeout
        if (_loadingSpinnerTimeout) {
            clearTimeout(_loadingSpinnerTimeout);
            _loadingSpinnerTimeout = null;
        }
        contentArea.classList.remove('loading-delayed');

        // Start delayed spinner - shows after 2 seconds if still loading
        _loadingSpinnerTimeout = setTimeout(function() {
            if (contentArea.classList.contains('loading')) {
                contentArea.classList.add('loading-delayed');
            }
        }, LOADING_SPINNER_DELAY);

        // Convert form element to FormData if needed
        if (formData instanceof HTMLFormElement) {
            formData = new FormData(formData);
        }

        // Use absolute path to handle clean URLs (e.g., /cma/form/xxx resolves correctly)
        const url = '/cma/main.php?nomenu&page=' + encodeURIComponent(page);

        fetch(url, {
            method: 'POST',
            body: formData
        })
            .then(response => {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.text();
            })
            .then(html => {
                contentArea.innerHTML = '<div class="cma-content-inner">' + html + '</div>';

                // Extract title and update breadcrumb
                let pageTitle = '';
                const titleMatch = html.match(/<title>([^<]*)<\/title>/i);
                if (titleMatch && titleMatch[1]) {
                    pageTitle = titleMatch[1].trim();
                    pageTitle = pageTitle.replace(/\s*\|\s*Content Management Applicatie$/i, '');
                }
                if (pageTitle) {
                    const breadcrumb = getCachedBreadcrumb();
                    if (breadcrumb) {
                        breadcrumb.textContent = pageTitle;
                    }
                    document.title = pageTitle + ' | CMA';
                }

                // Check if loaded page has body.tools class and apply to main body
                const bodyMatch = html.match(/<body[^>]*class=["']([^"']*)["']/i);
                if (bodyMatch && bodyMatch[1] && bodyMatch[1].includes('tools')) {
                    document.body.classList.add('tools');
                } else {
                    document.body.classList.remove('tools');
                }

                clearLoadingState(contentArea);
                currentPage = page;

                executeScripts(contentArea);
            })
            .catch(error => {
                // Report to dev error panel
                if (typeof CmaErrorHandler !== 'undefined') {
                    CmaErrorHandler.reportServerError('loadPagePost', error.message, { url: page });
                }
                contentArea.innerHTML = '<div class="cma-content-inner"><lib-message type="error">Fout bij laden: ' + error.message + '</lib-message></div>';
                clearLoadingState(contentArea);
            });
    };

    function executeScripts(container) {
        const scripts = container.querySelectorAll('script');
        // Skip scripts that are already loaded by main.php to prevent duplicate loading
        const skipPatterns = ['jquery.min.js', 'minify.php', 'library.js', 'error-handler.js'];

        // Separate external and inline scripts
        const externalScripts = [];
        const inlineScripts = [];

        scripts.forEach((oldScript) => {
            if (oldScript.src) {
                const shouldSkip = skipPatterns.some(pattern => oldScript.src.includes(pattern));
                if (shouldSkip) {
                    oldScript.remove();
                    return;
                }
                externalScripts.push(oldScript);
            } else {
                inlineScripts.push(oldScript);
            }
        });

        // Load external scripts first (especially CKEditor), wait for them to load
        let scriptsToWait = 0;
        let scriptsLoaded = 0;

        const onAllExternalLoaded = function() {
            // Once all external scripts are loaded, execute inline scripts
            inlineScripts.forEach((oldScript) => {
                const newScript = document.createElement('script');
                newScript.textContent = oldScript.textContent;
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });
        };

        if (externalScripts.length === 0) {
            // No external scripts, execute inline immediately
            onAllExternalLoaded();
            return;
        }

        externalScripts.forEach((oldScript) => {
            const newScript = document.createElement('script');
            newScript.src = oldScript.src;
            // Preserve defer attribute if present
            if (oldScript.defer) {
                newScript.defer = true;
            }
            // Preserve async attribute if present
            if (oldScript.async) {
                newScript.async = true;
            }

            // Track script loading for important scripts (CKEditor, components)
            const waitPatterns = ['ckeditor', 'cma-', 'lib-'];
            const shouldWait = waitPatterns.some(pattern => oldScript.src.includes(pattern));
            if (shouldWait) {
                scriptsToWait++;
                newScript.onload = function() {
                    scriptsLoaded++;
                    if (scriptsLoaded >= scriptsToWait) {
                        onAllExternalLoaded();
                    }
                };
                newScript.onerror = function() {
                    scriptsLoaded++;
                    if (scriptsLoaded >= scriptsToWait) {
                        onAllExternalLoaded();
                    }
                };
            }

            oldScript.parentNode.replaceChild(newScript, oldScript);
        });

        // If no scripts to wait for, execute inline immediately
        if (scriptsToWait === 0) {
            onAllExternalLoaded();
        }
    }

    function setActiveMenuItem(page) {
        // PERFORMANCE FIX: Use cached reference instead of querySelectorAll
        if (_activeMenuItem) {
            _activeMenuItem.classList.remove('active');
        }

        // Find new active item - try multiple matching strategies
        let newItem = null;

        // Strategy 1: Exact match on data-page
        newItem = document.querySelector('.cma-menu-item[data-page="' + CSS.escape(page) + '"]');

        // Strategy 2: Match by form parameter (for links like form.php?form=opleidingen)
        if (!newItem) {
            const formMatch = page.match(/[?&]form=([^&]+)/i);
            if (formMatch) {
                const formName = decodeURIComponent(formMatch[1]).toLowerCase();
                // Look for menu items that link to this form
                document.querySelectorAll('.cma-menu-item[data-page]').forEach(function(item) {
                    if (newItem) return; // Already found
                    const itemPage = item.dataset.page || '';
                    const itemFormMatch = itemPage.match(/[?&]form=([^&]+)/i);
                    if (itemFormMatch) {
                        const itemFormName = decodeURIComponent(itemFormMatch[1]).toLowerCase();
                        if (itemFormName === formName) {
                            newItem = item;
                        }
                    }
                });
            }
        }

        // Strategy 3: Match by page filename (for pages like tools.php, dashboard.php)
        // IMPORTANT: Skip this for form.php pages - Strategy 2 should have handled those.
        // Otherwise we incorrectly match the first form.php menu item for forms not in the menu.
        if (!newItem) {
            const pageFile = page.split('?')[0].toLowerCase();
            const hasFormParam = /[?&]form=/i.test(page);

            // Only use filename matching for non-form pages
            if (!hasFormParam) {
                document.querySelectorAll('.cma-menu-item[data-page]').forEach(function(item) {
                    if (newItem) return;
                    const itemPage = (item.dataset.page || '').split('?')[0].toLowerCase();
                    if (itemPage === pageFile) {
                        newItem = item;
                    }
                });
            }
        }

        if (newItem) {
            newItem.classList.add('active');
            _activeMenuItem = newItem;

            // Update breadcrumb
            const breadcrumb = getCachedBreadcrumb();
            const itemText = newItem.querySelector('.cma-menu-item-text') || newItem.querySelector('.cma-menu-group-title');
            if (breadcrumb && itemText) {
                breadcrumb.textContent = itemText.textContent;
            }

            // Expand parent menu group if collapsed
            const menuGroup = newItem.closest('.cma-menu-group');
            if (menuGroup && !menuGroup.classList.contains('expanded') && !menuGroup.classList.contains('single-item')) {
                menuGroup.classList.add('expanded');
                saveMenuState();
            }
        } else {
            // No menu item matches - page isn't in the menu (e.g., accessed from dashboard link)
            // Clear active state and set breadcrumb from form name or page title
            _activeMenuItem = null;

            const breadcrumb = getCachedBreadcrumb();
            if (breadcrumb) {
                // Try to get form name from URL
                const formMatch = page.match(/[?&]form=([^&]+)/i);
                if (formMatch) {
                    // Use form name as breadcrumb, title-cased
                    const formName = decodeURIComponent(formMatch[1]);
                    breadcrumb.textContent = formName.charAt(0).toUpperCase() + formName.slice(1).replace(/_/g, ' ');
                } else {
                    // Use page filename without extension
                    const pageFile = page.split('?')[0].split('/').pop().replace('.php', '');
                    breadcrumb.textContent = pageFile.charAt(0).toUpperCase() + pageFile.slice(1).replace(/_/g, ' ');
                }
            }
        }
    }

    // =========================================================================
    // Event Bindings
    // =========================================================================

    function initMenuItems() {
        document.querySelectorAll('.cma-menu-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();

                // Read page URL directly from the clicked element's attributes
                const page = this.dataset.page || this.getAttribute('href');

                // cmaLog.log('[Menu] Clicked:', this.textContent.trim(), '-> page:', page);

                // PERFORMANCE FIX: Use cached reference instead of querySelectorAll
                if (_activeMenuItem) {
                    _activeMenuItem.classList.remove('active');
                }
                this.classList.add('active');
                _activeMenuItem = this;

                const breadcrumb = getCachedBreadcrumb();
                const itemText = this.querySelector('.cma-menu-item-text') || this.querySelector('.cma-menu-group-title');
                if (breadcrumb && itemText) {
                    breadcrumb.textContent = itemText.textContent;
                }

                // Close mobile menu if open
                const sidebar = document.getElementById('sidebar');
                if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
                    sidebar.classList.remove('open');
                    const menuBtn = document.querySelector('.cma-mobile-menu-btn');
                    if (menuBtn) menuBtn.classList.remove('is-open');
                }

                loadPage(page);
            });
        });
    }

    // Handle browser back/forward
    window.addEventListener('popstate', function(e) {
        if (e.state && e.state.page) {
            loadPage(e.state.page, false);
        }
    });

    // =========================================================================
    // Initialization
    // =========================================================================

    function init() {
        // PERFORMANCE: Initialize cached DOM references first
        initCachedElements();

        // Restore sidebar state (using cached reference)
        // Only apply collapsed state on desktop, not on mobile
        const sidebar = getCachedSidebar();
        const isMobile = window.innerWidth <= 768;
        if (sidebar && localStorage.getItem('cma_v2_menu_collapsed') === 'true' && !isMobile) {
            sidebar.classList.add('collapsed');
        }

        // Restore menu group states
        restoreMenuState();

        // Initialize menu item click handlers
        initMenuItems();

        // Initialize collapsed sidebar popup menus
        initPopupMenus();

        // Initialize hamburger menu toggle for mobile
        initMenuToggle();

        // Initialize global link interceptor for content area
        initLinkInterceptor();
    }

    // =========================================================================
    // Global Link Interceptor
    // =========================================================================
    // Catches all link clicks in the content area and routes them through loadPage
    // This ensures links always stay within the main.php shell

    function initLinkInterceptor() {
        const contentArea = getCachedContentArea();
        if (!contentArea) return;

        // Use event delegation on content area to catch all link clicks
        contentArea.addEventListener('click', function(e) {
            // Find the clicked link (could be the target or a parent)
            const link = e.target.closest('a[href]');
            if (!link) return;

            const href = link.getAttribute('href');
            if (!href) return;

            // Skip if modifier keys are pressed (let browser handle new tab, etc.)
            if (e.ctrlKey || e.metaKey || e.shiftKey) return;

            // Skip external links (different origin)
            if (href.startsWith('http://') || href.startsWith('https://')) {
                try {
                    const linkUrl = new URL(href, window.location.origin);
                    if (linkUrl.origin !== window.location.origin) {
                        // External link - let it open normally (or in new tab)
                        link.setAttribute('target', '_blank');
                        return;
                    }
                } catch (err) {
                    cmaLog.warn('[LinkInterceptor] Invalid URL, letting browser handle:', href);
                    return;
                }
            }

            // Skip special links
            if (href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
                return;
            }

            // Skip download links
            if (link.hasAttribute('download')) return;

            // Skip links explicitly targeting other frames/windows
            const target = link.getAttribute('target');
            if (target && target !== '_self' && target !== '') return;

            // Intercept the link and use loadPage instead
            e.preventDefault();
            e.stopPropagation();

            // Resolve relative URLs
            let pageUrl = href;
            if (!href.startsWith('/')) {
                // Relative URL - resolve against current location
                const base = window.location.pathname.replace(/\/[^\/]*$/, '/');
                pageUrl = base + href;
            }

            // Remove /cma/ prefix if present (loadPage expects relative paths)
            pageUrl = pageUrl.replace(/^\/cma\//, '');

            // cmaLog.log('[LinkInterceptor] Intercepted:', href, '-> loadPage:', pageUrl);
            loadPage(pageUrl);
        }, true); // Use capture phase to intercept before other handlers
    }

    // Run init when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose loadInitialPage for main.php to call
    window.loadInitialPage = function(page) {
        // Check for clean URL format first
        const path = window.location.pathname;

        // Handle simple clean URLs: /cma/dashboard, /cma/tools, /cma/preferences
        // Preserve any query parameters from the page argument (passed from PHP)
        const simpleMatch = path.match(/^\/cma\/(dashboard|tools|preferences)(?:\/|$)/);
        if (simpleMatch) {
            // Extract query params from original page (if any)
            const pageQueryIndex = page.indexOf('?');
            const pageQuery = pageQueryIndex > -1 ? page.substring(pageQueryIndex) : '';
            // Also check URL query string for additional params
            const urlQuery = window.location.search;
            // Combine: start with base page, add page params, then URL params
            page = simpleMatch[1] + '.php' + pageQuery;
            // If URL has additional params not in page, append them
            if (urlQuery && !pageQuery.includes(urlQuery.substring(1))) {
                const separator = page.includes('?') ? '&' : '?';
                page += separator + urlQuery.substring(1);
            }
            // cmaLog.log('[loadInitialPage] Simple clean URL detected:', page);
        }
        // Handle form clean URLs: /cma/form/...
        else if (window.CMA && window.CMA.url) {
            const urlState = window.CMA.url.parse();
            if (urlState.form) {
                // We have a clean URL - build the page URL from parsed state
                page = 'form.php?form=' + encodeURIComponent(urlState.form);
                // If a record ID is present, load it in the detail panel (tree + record)
                // instead of opening a sidepanel
                if (urlState.recordId) {
                    page += '&id=' + encodeURIComponent(urlState.recordId) + '&view=tree';
                } else if (urlState.isNew) {
                    page += '&New=Y&view=tree';
                }
                // cmaLog.log('[loadInitialPage] Parsed clean URL, page:', page, 'state:', urlState);
            }
        }

        if (page && page !== 'about:blank') {
            // Check for formID/formView params in URL (for legacy query param format)
            const mainParams = new URLSearchParams(window.location.search);
            const formID = mainParams.get('formID');
            const formView = mainParams.get('formView');

            if (formID && page.includes('form.php')) {
                // Append record ID and view mode to the page URL
                const pageUrl = new URL(page, window.location.origin);
                pageUrl.searchParams.set('ID', formID);
                if (formView) {
                    pageUrl.searchParams.set('view', formView);
                }
                page = pageUrl.pathname.replace(/^.*\/cma\//, '') + pageUrl.search;
                // cmaLog.log('[loadInitialPage] Restored record from legacy URL, page:', page);
            }

            loadPage(page, false);
        }

        // After initial page load, check for sidepanel state to auto-open
        // Use setTimeout to ensure page is fully loaded first
        setTimeout(checkForPendingSidepanel, 500);
    };

    /**
     * Check URL for sidepanel state and auto-open sidepanel(s) if present
     * Supports clean URL format (/form/xxx/123/subform/456) and legacy query params
     */
    function checkForPendingSidepanel() {
        // Check clean URL format first
        if (window.CMA && window.CMA.url) {
            const urlState = window.CMA.url.parse();

            // Main record is loaded in the detail panel (tree + record mode) by loadInitialPage.
            // Only open sidepanels for subforms/subsubforms if present in the URL.
            if ((urlState.recordId || urlState.isNew) && urlState.subform) {
                const recordId = urlState.isNew ? null : urlState.recordId;

                if (typeof lib_OpenSidePanel === 'function') {
                    const width = Math.round(window.innerWidth * 0.85);

                    let subUrl = 'form.php?form=' + encodeURIComponent(urlState.subform);
                    if (urlState.subformId && !urlState.isSubformNew) {
                        subUrl += '&id=' + urlState.subformId;
                    } else {
                        subUrl += '&New=Y';
                    }
                    subUrl += '&parentID=' + recordId;

                    lib_OpenSidePanel(subUrl, 'form_popup_sub', width, 'Bewerken');

                    // If there's a subsubform (3rd level), open that too
                    if (urlState.subsubform) {
                        setTimeout(() => {
                            let subsubUrl = 'form.php?form=' + encodeURIComponent(urlState.subsubform);
                            if (urlState.subsubformId && !urlState.isSubsubformNew) {
                                subsubUrl += '&id=' + urlState.subsubformId;
                            } else {
                                subsubUrl += '&New=Y';
                            }
                            subsubUrl += '&parentID=' + (urlState.subformId || '');

                            lib_OpenSidePanel(subsubUrl, 'form_popup_subsub', width, 'Bewerken');
                        }, 400);
                    }
                }
                return;
            }
        }

        // Fall back to legacy query parameter format
        const params = new URLSearchParams(window.location.search);

        // Check for stack format
        const popupStack = params.get('popupStack');
        if (popupStack) {
            // cmaLog.log('[main.js] Found popupStack, restoring sidepanels:', popupStack);

            const stackItems = popupStack.split('|');
            let delay = 0;

            stackItems.forEach((item, index) => {
                const parts = item.split(':');
                const formId = parts[0] || '';
                const recordId = parts[1] || '0';
                const parentId = parts[2] || '';
                const parentField = parts[3] || '';

                if (!formId) return;

                setTimeout(() => {
                    let url = 'form.php?form=' + encodeURIComponent(formId);
                    if (recordId && recordId !== '0') {
                        url += '&id=' + recordId;
                    } else {
                        url += '&New=Y';
                    }
                    if (parentId) url += '&parentID=' + parentId;
                    if (parentField) url += '&parentField=' + encodeURIComponent(parentField);

                    if (typeof lib_OpenSidePanel === 'function') {
                        const width = Math.round(window.innerWidth * 0.85);
                        lib_OpenSidePanel(url, 'form_popup_' + index, width, 'Bewerken');
                        // cmaLog.log('[main.js] Opened sidepanel', index + 1, 'of', stackItems.length, ':', formId);
                    }
                }, delay);

                delay += 300;
            });

            return;
        }

        // Legacy single popup format
        const popupForm = params.get('popup');
        const popupId = params.get('popupID');

        if (!popupForm) return;

        // cmaLog.log('[main.js] Found legacy popup params, auto-opening sidepanel:', popupForm, popupId);

        let url = 'form.php?form=' + encodeURIComponent(popupForm);
        if (popupId && popupId !== '0') {
            url += '&id=' + popupId;
        } else {
            url += '&New=Y';
        }

        const parentId = params.get('popupParentID');
        const parentField = params.get('popupParentField');
        if (parentId) url += '&parentID=' + parentId;
        if (parentField) url += '&parentField=' + encodeURIComponent(parentField);

        if (typeof lib_OpenSidePanel === 'function') {
            const width = Math.round(window.innerWidth * 0.85);
            lib_OpenSidePanel(url, 'form_popup', width, 'Bewerken');
        } else {
            cmaLog.warn('[main.js] lib_OpenSidePanel not available for auto-open');
        }
    }

    /**
     * Activate menu item by URL or form name
     * Can be called from any loaded content page to ensure menu is synced
     * @param {string} pageOrForm - Page URL (e.g., 'form.php?form=opleidingen') or form name (e.g., 'opleidingen')
     */
    window.activateMenuItem = function(pageOrForm) {
        if (!pageOrForm) return;

        // If just a form name (no path/query), convert to full URL
        let page = pageOrForm;
        if (!page.includes('.php') && !page.includes('?') && !page.includes('/')) {
            page = 'form.php?form=' + encodeURIComponent(pageOrForm);
        }

        setActiveMenuItem(page);
    };

    // Expose page cache API on CMA namespace
    window.CMA = window.CMA || {};
    window.CMA.clearPageCache = clearPageCache;
    window.CMA.pageCacheStats = function() {
        const entries = [];
        for (const [key, entry] of _pageCache) {
            entries.push({ page: key, age: Math.round((Date.now() - entry.timestamp) / 1000) + 's' });
        }
        return { size: _pageCache.size, max: MAX_CACHED_PAGES, entries: entries };
    };

})();
