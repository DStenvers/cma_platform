/**
 * CMA Inline Edit Controller
 *
 * Provides inline editing for table rows in list.php
 * Features:
 * - Click to edit row
 * - Context menu on hover (add, edit, copy, delete)
 * - Save/Cancel buttons above editing row
 * - Validation integration
 * - cma_afterpost integration
 * - Fixed header for filtered tables
 *
 * Native JavaScript version - no jQuery dependency
 */

// DEBUG: Log when this file is loaded
// console.log('[inline-edit.js] FILE LOADED - version 20260209a');

(function(window) {
    'use strict';

    // Use cmaLog if available (from cma-utils.js), otherwise create a local copy
    // CMA_CONSOLE_LOGGING respects user preference (cookie), CMA_DEBUG is environment-based
    const log = window.cmaLog || {
        log: function(...args) {
            if (typeof cmaLog !== 'undefined') { cmaLog.log(...args); }
            else if (window.CMA_CONSOLE_LOGGING) { console.log(...args); }
        },
        warn: function(...args) {
            if (typeof cmaLog !== 'undefined') { cmaLog.warn(...args); }
            else if (window.CMA_CONSOLE_LOGGING) { console.warn(...args); }
        },
        error: function(...args) {
            if (typeof cmaLog !== 'undefined') { cmaLog.error(...args); }
            else { console.error(...args); }
        }
    };

    /**
     * Helper: Create element from HTML string
     */
    function createElement(html) {
        const template = document.createElement('template');
        template.innerHTML = html.trim();
        return template.content.firstChild;
    }

    /**
     * Helper: Escape HTML for use in attribute values
     * escapeHtml() provided by cma-utils.js (attribute-safe)
     */

    /**
     * Helper: Fetch with proper error handling and retry logic
     * @param {string} url - URL to fetch
     * @param {object} options - Fetch options
     * @param {object} config - { retries: number, retryDelay: ms, context: string }
     * @returns {Promise<Response>} - Response object (guaranteed to be .ok)
     */
    async function fetchWithRetry(url, options = {}, config = {}) {
        const retries = config.retries || 1;
        const retryDelay = config.retryDelay || 1000;
        const context = config.context || 'fetch';

        let lastError = null;

        for (let attempt = 1; attempt <= retries; attempt++) {
            try {
                const response = await fetch(url, options);

                if (!response.ok) {
                    // Try to get error details from response
                    let errorMessage = `HTTP ${response.status} ${response.statusText}`;
                    try {
                        const text = await response.text();
                        // Try to extract PHP error or JSON error
                        if (text.includes('"error"')) {
                            const json = JSON.parse(text);
                            if (json.error) errorMessage = json.error;
                        } else if (text.includes('Fatal error') || text.includes('Parse error')) {
                            // PHP error in response
                            const match = text.match(/(?:Fatal error|Parse error)[^<]*/);
                            if (match) errorMessage = match[0];
                        }
                    } catch (e) {
                        // Ignore parse errors
                    }

                    const httpError = new Error(`[${context}] ${errorMessage}`);
                    httpError.statusCode = response.status;
                    throw httpError;
                }

                return response;
            } catch (error) {
                lastError = error;

                if (attempt < retries) {
                    log.warn(`[${context}] Attempt ${attempt} failed, retrying in ${retryDelay}ms:`, error.message);
                    await new Promise(resolve => setTimeout(resolve, retryDelay));
                }
            }
        }

        // All retries exhausted
        log.error(`[${context}] All ${retries} attempts failed:`, lastError.message);
        throw lastError;
    }

    /**
     * Helper: Fetch JSON with error handling
     * @param {string} url - URL to fetch
     * @param {object} options - Fetch options
     * @param {string} context - Context for error messages
     * @returns {Promise<object>} - Parsed JSON data
     */
    async function fetchJson(url, options = {}, context = 'fetch') {
        const response = await fetchWithRetry(url, options, { context, retries: 1 });

        try {
            return await response.json();
        } catch (e) {
            const text = await response.clone().text().catch(() => '[unreadable]');
            log.error(`[${context}] Invalid JSON response:`, text.substring(0, 500));
            throw new Error(`[${context}] Invalid JSON response from server`);
        }
    }

    /**
     * PERFORMANCE: Request Batcher for inline edits
     * Combines multiple field updates for the same record into a single API call.
     * Flushes after BATCH_DELAY ms or when explicitly flushed.
     */
    class RequestBatcher {
        constructor(apiUrl, options = {}) {
            this.apiUrl = apiUrl;
            this.batchDelay = options.batchDelay || 300; // ms
            this._pending = new Map(); // recordId -> { formId, fields: {fieldName: value}, callbacks: [] }
            this.flushTimer = null;
        }

        /**
         * Queue a field update for batching
         * @param {string} recordId - Record ID
         * @param {string} fieldName - Field name
         * @param {any} value - Field value
         * @param {string} jsonForm - JSON form name
         * @param {object} callbacks - { onSuccess, onError } callbacks
         */
        queue(recordId, fieldName, value, jsonForm, callbacks = {}) {
            if (!this._pending.has(recordId)) {
                this._pending.set(recordId, {
                    jsonForm: jsonForm,
                    fields: {},
                    callbacks: []
                });
            }

            const entry = this._pending.get(recordId);
            entry.fields[fieldName] = value;
            if (callbacks.onSuccess || callbacks.onError) {
                entry.callbacks.push(callbacks);
            }

            // Schedule flush
            this.scheduleFlush();
        }

        scheduleFlush() {
            if (this.flushTimer) return; // Already scheduled

            this.flushTimer = setTimeout(() => {
                this.flush();
            }, this.batchDelay);
        }

        async flush() {
            if (this.flushTimer) {
                clearTimeout(this.flushTimer);
                this.flushTimer = null;
            }

            if (this._pending.size === 0) return;

            // Take current queue and reset
            const batch = new Map(this._pending);
            this._pending.clear();

            // Process each record
            for (const [recordId, entry] of batch) {
                try {
                    const formData = new URLSearchParams({
                        action: 'save',
                        ID: recordId,
                        jsonForm: entry.jsonForm
                    });

                    // Add all fields
                    for (const [fieldName, value] of Object.entries(entry.fields)) {
                        formData.append(fieldName, value);
                    }

                    // Use fetchJson helper with proper error handling
                    const data = await fetchJson(this.apiUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData.toString()
                    }, 'RequestBatcher.flush');

                    // Call all callbacks with protection
                    for (const cb of entry.callbacks) {
                        try {
                            if (data.success && cb.onSuccess) {
                                cb.onSuccess(data);
                            } else if (!data.success && cb.onError) {
                                cb.onError(data.error || 'Fout bij opslaan');
                            }
                        } catch (callbackError) {
                            log.error('[RequestBatcher] Callback threw error:', callbackError.message);
                        }
                    }

                    // If data indicates error, log it
                    if (!data.success) {
                        log.error('[RequestBatcher] Save failed for record', recordId, ':', data.error || 'Unknown error');
                    }
                } catch (error) {
                    log.error('[RequestBatcher] Fetch error for record', recordId, ':', error.message);
                    // Call error callbacks with protection
                    for (const cb of entry.callbacks) {
                        try {
                            if (cb.onError) {
                                cb.onError(error.message || 'Netwerkfout bij opslaan');
                            }
                        } catch (callbackError) {
                            log.error('[RequestBatcher] Error callback threw:', callbackError.message);
                        }
                    }
                }
            }
        }

        destroy() {
            if (this.flushTimer) {
                clearTimeout(this.flushTimer);
                this.flushTimer = null;
            }
            this._pending.clear();
        }
    }

    // Per-jsonForm batcher instances (prevents cross-form save conflicts)
    const _requestBatchers = new Map();
    function getRequestBatcher(apiUrl, jsonForm) {
        const key = jsonForm || '_default';
        if (!_requestBatchers.has(key)) {
            _requestBatchers.set(key, new RequestBatcher(apiUrl));
        }
        return _requestBatchers.get(key);
    }

    /**
     * Global cleanup function - call when switching forms to reset all state
     * This prevents stale references from causing saves to wrong forms
     */
    function resetInlineEditState() {
        // log.log('resetInlineEditState: Clearing global inline edit state');
        for (const batcher of _requestBatchers.values()) {
            batcher.destroy();
        }
        _requestBatchers.clear();
        // Remove any lingering inline edit button rows
        document.querySelectorAll('.inline-edit-button-row').forEach(el => el.remove());
        // Also remove editing class from any rows
        document.querySelectorAll('.libTableTR.editing').forEach(el => el.classList.remove('editing'));
    }

    // Expose cleanup function globally
    window.cmaResetInlineEditState = resetInlineEditState;

    // Automatically reset state on page navigation to prevent stale references
    window.addEventListener('beforeunload', resetInlineEditState);
    // Also listen for SPA-style navigation if using turbolinks or similar
    document.addEventListener('turbo:before-visit', resetInlineEditState);
    document.addEventListener('turbolinks:before-visit', resetInlineEditState);

    /**
     * InlineEdit controller class
     */
    // Global instance counter for debugging
    let _inlineEditInstanceCounter = 0;

    // Global registry of all active CmaInlineEdit instances
    const _activeInstances = new Set();

    class CmaInlineEdit {
        constructor(options) {
            this.options = Object.assign({
                tableSelector: '.libTable',
                formId: 0,
                jsonForm: null, // For JSON-defined forms (e.g., 'users', 'groups')
                formNameSingular: '', // Singular form name for tooltips (e.g., 'Gebruiker')
                accessLevel: 0,
                canAdd: true,
                canEdit: true,
                canCopy: true,
                canDelete: true,
                extraButtons: [],
                afterPostUrl: '',
                idField: 'ID',
                apiUrl: '/cma/form_api.php',
                fields: [],
                comboOptions: {}, // Pre-loaded combo options: fieldName => [{id, text}, ...]
                filterByFieldMap: {}, // fieldName => parentFieldName for cascading combo filters
                addRelatedForms: {} // fieldName => formName for plus buttons next to comboboxes
            }, options);

            // Assign unique instance ID for debugging
            this._instanceId = ++_inlineEditInstanceCounter;

            this.editingRowId = null;
            this.originalData = {};
            this.contextMenu = null;
            this.editControls = null;
            this.isDirty = false;

            // Store event handlers for cleanup
            this._eventHandlers = new Map();

            // OPTIMIZATION: Pre-build lookup maps for O(1) combo lookups
            this.comboLookupMaps = this.buildComboLookupMaps(this.options.comboOptions);

            // Always true: at minimum there's a "Bekijk" action for readonly forms
            this.hasAnyActions = true;

            // log.log('CmaInlineEdit: Initializing for', this.options.tableSelector, 'formId:', this.options.formId, 'jsonForm:', this.options.jsonForm, 'instanceId:', this._instanceId, 'hasAnyActions:', this.hasAnyActions);

            // DEBUG: Log instance creation
            if (typeof window._cmaDebugLog === 'function') {
                window._cmaDebugLog('CmaInlineEdit:created', {
                    instanceId: this._instanceId,
                    jsonForm: this.options.jsonForm,
                    formId: this.options.formId,
                    tableSelector: this.options.tableSelector
                });
            }

            _activeInstances.add(this);
            this.init();
        }

        /**
         * Cancel editing on ALL other CmaInlineEdit instances.
         * Returns false if any instance refused to cancel (validation failure).
         */
        static cancelOtherEditing(except) {
            for (const inst of _activeInstances) {
                if (inst === except) continue;
                if (!inst.editingRowId) continue;
                if (inst.isDirty) {
                    // Validate the dirty row - if invalid, scroll to it and refuse
                    if (!inst.validateRow(inst.editingRow)) {
                        inst.editingRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return false;
                    }
                }
                inst.cancelInlineEditing();
            }
            return true;
        }

        /**
         * Build lookup maps from combo options for O(1) value lookups
         * @param {Object} comboOptions - fieldName => [options array]
         * @returns {Object} fieldName => Map(id => displayText)
         */
        buildComboLookupMaps(comboOptions) {
            const maps = {};
            if (!comboOptions) return maps;

            for (const fieldName in comboOptions) {
                const options = comboOptions[fieldName];
                if (!Array.isArray(options)) continue;

                const map = new Map();
                for (const opt of options) {
                    const id = String(opt.id !== undefined ? opt.id : (opt.value || ''));
                    const text = opt.text !== undefined ? opt.text : (opt.label || id);
                    map.set(id, text);
                }
                maps[fieldName] = map;
            }
            return maps;
        }

        /**
         * Get form identifier parameter for API calls
         * All forms are JSON forms now - no legacy FormID support
         */
        getFormIdParam() {
            if (this.options.jsonForm) {
                return 'jsonForm=' + encodeURIComponent(this.options.jsonForm);
            }
            throw new Error('jsonForm is required but not configured');
        }

        init() {
            this.createContextMenu();
            this.createEditControls();
            this.bindEvents();
        }

        /**
         * Create context menu element
         * Reuses existing element if already present in DOM
         * PERFORMANCE FIX: Build HTML string first, then assign once (avoids multiple reflows)
         */
        createContextMenu() {
            // Check if row context menu already exists in DOM (use specific class to avoid conflict with export-menu)
            let menu = document.querySelector('.cma-context-menu.row-menu');
            let isNew = false;

            if (!menu) {
                const menuHtml = '<div class="cma-context-menu row-menu" style="display:none"><ul></ul></div>';
                menu = createElement(menuHtml);
                document.body.appendChild(menu);
                isNew = true;
            } else {
                // Reuse existing - reset properties to clean state
                menu.style.display = 'none';
                menu.style.left = '';
                menu.style.top = '';
                delete menu.dataset.rowId;
            }

            const ul = menu.querySelector('ul');

            // Build HTML string first to avoid multiple innerHTML reflows
            let html = '';

            if (this.options.canAdd) {
                const singularName = (this.options.formNameSingular || 'record').toLowerCase();
                html += '<li data-action="add"><span class="lnr lnr-file-add"></span> Voeg ' + escapeHtml(singularName) + ' toe</li>';
            }
            if (this.options.canEdit) {
                html += '<li data-action="editInline"><span class="lnr lnr-pencil"></span> Direct wijzigen</li>';
                html += '<li data-action="edit"><span class="lnr lnr-frame-expand"></span> Bewerken</li>';
            } else {
                // Readonly form - offer view action
                const viewName = (this.options.formNameSingular || 'record').toLowerCase();
                html += '<li data-action="view"><span class="lnr lnr-frame-expand"></span> Bekijk ' + escapeHtml(viewName) + '</li>';
            }
            if (this.options.canCopy) {
                html += '<li data-action="copy"><span class="lnr lnr-layers"></span> Dupliceer</li>';
            }
            if (this.options.canDelete) {
                html += '<li data-action="delete" class="danger"><span class="lnr lnr-trash"></span> Verwijderen</li>';
            }

            // Extra buttons from form definition
            if (this.options.extraButtons && this.options.extraButtons.length > 0) {
                html += '<li class="separator"></li>';
                this.options.extraButtons.forEach((btn, i) => {
                    if (btn.title || btn.icon) {
                        html += '<li data-action="extra" data-index="' + i + '">' +
                            '<span class="' + (btn.icon || 'lnr lnr-cog') + '"></span> ' +
                            (btn.title || 'Extra ' + (i + 1)) +
                            '</li>';
                    }
                });
            }

            // Single innerHTML assignment (one reflow instead of many)
            // This also clears old content when reusing
            ul.innerHTML = html;

            this.contextMenu = menu;
            this._contextMenuReused = !isNew;
        }

        /**
         * Create edit controls (Save/Cancel buttons)
         * Reuses existing element if already present in DOM
         */
        createEditControls() {
            // Check if edit controls already exist in DOM
            const existing = document.querySelector('.cma-edit-controls');
            if (existing) {
                // Reuse existing controls - just ensure they're hidden
                existing.style.display = 'none';
                this.editControls = existing;
                this._editControlsReused = true;
                return;
            }

            const controlsHtml = '<div class="cma-edit-controls" style="display:none">' +
                '<button type="button" class="btn-save" data-tooltip="Opslaan (Ctrl+S)">' +
                '<span class="lnr lnr-checkmark-circle"></span>' +
                '</button>' +
                '<button type="button" class="btn-cancel" data-tooltip="Annuleren (Esc)">' +
                '<span class="lnr lnr-cross-circle"></span>' +
                '</button>' +
                '</div>';

            const controls = createElement(controlsHtml);
            document.body.appendChild(controls);
            this.editControls = controls;
            this._editControlsReused = false;
        }

        /**
         * Get unique event namespace for this instance
         */
        getEventNamespace() {
            if (!this._eventNamespace) {
                this._eventNamespace = 'cmaInlineEdit' + (this.options.formId || 'json') + '_' + Date.now();
            }
            return this._eventNamespace;
        }

        /**
         * Add event listener with tracking for cleanup
         */
        addTrackedListener(element, event, handler, options) {
            element.addEventListener(event, handler, options);
            const key = `${event}_${this._eventHandlers.size}`;
            this._eventHandlers.set(key, { element, event, handler, options });
        }

        /**
         * Destroy instance and clean up event handlers
         */
        destroy() {
            _activeInstances.delete(this);

            // DEBUG: Log instance destruction
            if (typeof window._cmaDebugLog === 'function') {
                window._cmaDebugLog('CmaInlineEdit:destroyed', {
                    instanceId: this._instanceId,
                    jsonForm: this.options.jsonForm,
                    eventHandlerCount: this._eventHandlers.size
                });
            }

            // Remove all tracked event listeners
            this._eventHandlers.forEach(({ element, event, handler, options }) => {
                element.removeEventListener(event, handler, options);
            });
            this._eventHandlers.clear();

            // Remove contextMenu and editControls from DOM only if we created them
            // (don't remove if they were reused from another instance)
            if (this.contextMenu) {
                if (!this._contextMenuReused) {
                    this.contextMenu.remove();
                }
                this.contextMenu = null;
            }

            if (this.editControls) {
                if (!this._editControlsReused) {
                    this.editControls.remove();
                }
                this.editControls = null;
            }

            // Clear state
            this.editingRowId = null;
            this.originalData = {};
            this.isDirty = false;
        }

        /**
         * Bind event handlers
         * PERFORMANCE FIX: Attach mouse handlers to table element instead of document
         */
        bindEvents() {
            const self = this;
            const table = document.querySelector(this.options.tableSelector);
            // log.log('CmaInlineEdit: Binding events, table found:', !!table, 'rows:', table ? table.querySelectorAll('tbody tr').length : 0);

            // PERFORMANCE FIX: Attach to table instead of document to avoid firing on every mouse event
            const eventTarget = table || document;

            // Row hover - create context menu trigger if needed (visibility handled by CSS)
            // Only create trigger if there are any available actions
            const mouseenterHandler = (e) => {
                if (!self.hasAnyActions) return; // Don't show menu trigger for readonly tables
                const row = e.target.closest('tbody tr');
                if (!row) return;

                // Create three-dot menu trigger in first cell if not exists
                // CSS handles visibility on hover and hiding during editing
                if (!row.querySelector('.row-menu-trigger')) {
                    const targetCell = row.querySelector('td:first-child');
                    if (targetCell) {
                        const trigger = createElement('<span class="row-menu-trigger" data-tooltip="Menu">&#8942;</span>');
                        targetCell.appendChild(trigger);
                    }
                }
            };
            this.addTrackedListener(eventTarget, 'mouseover', mouseenterHandler);

            // Context menu trigger click
            // IMPORTANT: Only handle triggers in OUR table (main list table), not subform tables
            // This prevents duplicate menus when clicking subform row triggers
            const mainTable = document.querySelector(self.options.tableSelector);
            const triggerClickHandler = (e) => {
                const trigger = e.target.closest('.row-menu-trigger');
                if (!trigger) return;

                // Check if trigger is in our table, not a subform table
                const row = trigger.closest('tr');
                const table = row ? row.closest('table') : null;
                if (!table || table !== mainTable) {
                    // Not our table - let form-controller handle subform menus
                    return;
                }

                e.stopPropagation();
                const rowId = self.getRowId(row);
                // Position menu relative to trigger element, not mouse position
                const triggerRect = trigger.getBoundingClientRect();
                const menuX = triggerRect.right + 2;
                const menuY = triggerRect.top;
                self.showContextMenu(menuX, menuY, rowId, row);
            };
            this.addTrackedListener(document, 'click', triggerClickHandler);

            // Right-click on row shows context menu with all available actions
            const contextmenuHandler = (e) => {
                // Prevent multiple CmaInlineEdit instances from handling the same event
                if (e._cmaInlineEditHandled) return;
                if (self.editingRowId) return;
                // Don't intercept right-click if there are no available actions
                if (!self.hasAnyActions) return;
                const row = e.target.closest(self.options.tableSelector + ' tbody tr');
                if (!row) return;
                e.preventDefault();
                e._cmaInlineEditHandled = true; // Mark event as handled by this instance
                const rowId = self.getRowId(row);
                if (rowId) {
                    self.showContextMenu(e.clientX, e.clientY, rowId, row);
                }
            };
            this.addTrackedListener(document, 'contextmenu', contextmenuHandler);

            // Context menu item click
            // CRITICAL: Only the instance that SHOWED the menu should handle the click.
            // The context menu DOM element is shared across instances, so multiple handlers
            // can be attached. Use _menuOwnerId to ensure only the owning instance responds.
            const menuClickHandler = (e) => {
                const li = e.target.closest('li[data-action]');
                if (!li) return;

                // Only handle if this instance owns the menu (was the one that showed it)
                if (self.contextMenu._menuOwnerId !== self._instanceId) return;

                e.stopPropagation(); // Prevent event from bubbling to document handlers

                const action = li.dataset.action;
                const rowId = self.contextMenu.dataset.rowId;
                const row = self._contextMenuRow;

                self.hideContextMenu();

                switch (action) {
                    case 'add':
                        self.addNewRow();
                        break;
                    case 'editInline':
                        self.startInlineEditing(rowId, row);
                        break;
                    case 'view':
                    case 'edit':
                        // Set active class on row
                        if (row) {
                            const table = row.closest('table');
                            if (table) table.querySelectorAll('tbody tr.active').forEach(r => r.classList.remove('active'));
                            row.classList.add('active');
                        }
                        self.openFormPopup(rowId, false);
                        break;
                    case 'copy':
                        // Set active class on row
                        if (row) {
                            const table = row.closest('table');
                            if (table) table.querySelectorAll('tbody tr.active').forEach(r => r.classList.remove('active'));
                            row.classList.add('active');
                        }
                        self.copyRow(rowId);
                        break;
                    case 'delete':
                        self.deleteRow(rowId);
                        break;
                    case 'extra':
                        const index = parseInt(li.dataset.index, 10);
                        self.executeExtraButton(index, rowId);
                        break;
                }
            };
            this.addTrackedListener(this.contextMenu, 'click', menuClickHandler);

            // Hide context menu on click outside
            const outsideClickHandler = (e) => {
                // Don't hide if clicking on the menu itself or the trigger
                if (e.target.closest('.cma-context-menu') || e.target.closest('.row-menu-trigger')) {
                    return;
                }
                self.hideContextMenu();
            };
            this.addTrackedListener(document, 'click', outsideClickHandler);

            // Inline switch toggle - save boolean value without opening detail screen
            // Uses lib-switch web component which fires 'change' events
            // PERFORMANCE: Uses RequestBatcher to combine rapid toggle changes
            // CRITICAL: Read jsonForm from data-attribute, NOT from closure (prevents stale form reference bug)
            // NOTE: This handler only processes switches in the MAIN form table, not subform tables
            // Subform switches are handled by the global handler at the end of this file
            const switchChangeHandler = (e) => {
                // CRITICAL: Check if already handled by another CmaInlineEdit instance
                // This prevents duplicate processing when multiple instances exist
                if (e._cmaInlineEditHandled) return;

                const libSwitch = e.target.closest('lib-switch');
                if (!libSwitch) return;

                const row = libSwitch.closest('tr');
                if (!row) return;

                // CRITICAL FIX: Get jsonForm from table's data-attribute, NOT from instance closure
                // This prevents the bug where switching forms causes saves to go to wrong form
                const table = libSwitch.closest('table[data-json-form]');
                if (!table) {
                    // Not in a form table context (e.g., preferences page) - silently skip
                    return;
                }

                // Only handle switches in the MAIN table (listTable), not subform tables
                // Subform tables have class 'subform-table' and are handled by global handler
                if (table.classList.contains('subform-table')) {
                    // Let the global handler process this
                    return;
                }

                const jsonFormFromTable = table.dataset.jsonForm;
                if (!jsonFormFromTable) {
                    // Table exists but no form configured - skip
                    return;
                }

                // Mark event as handled IMMEDIATELY to prevent other handlers from processing
                e._cmaInlineEditHandled = true;

                const rowId = self.getRowId(row);
                const fieldName = libSwitch.getAttribute('data-field');
                const td = libSwitch.closest('td');
                const newValue = libSwitch.checked;

                if (!rowId || !fieldName) return;

                // Show saving state
                libSwitch.setSaving(true);
                if (td) td.dataset.value = newValue ? '1' : '0';

                // PERFORMANCE: Use batcher to combine multiple switch toggles
                // Use jsonFormFromTable from data attribute, NOT self.options.jsonForm
                const batcher = getRequestBatcher(self.options.apiUrl, jsonFormFromTable);
                batcher.queue(rowId, fieldName, newValue ? 'True' : 'False', jsonFormFromTable, {
                    onSuccess: () => {
                        libSwitch.setSaving(false);
                        // Mark the column filter as stale so it rebuilds with new value on next open
                        if (td && typeof jQuery !== 'undefined' && typeof jQuery.fn.excelTableFilterMarkColumnStale === 'function') {
                            const columnIndex = td.cellIndex;
                            jQuery(table).excelTableFilterMarkColumnStale(columnIndex);
                        }
                        // Show confirmation toaster - use libSwitch as dedup key to prevent multiple toasts
                        if (typeof libToast !== 'undefined' && !libSwitch._toastShown) {
                            libSwitch._toastShown = true;
                            libToast.success('Opgeslagen');
                            // Reset after a short delay
                            setTimeout(() => { libSwitch._toastShown = false; }, 500);
                        }
                    },
                    onError: (error) => {
                        libSwitch.setSaving(false);
                        // Revert on error
                        libSwitch.checked = !newValue;
                        if (td) td.dataset.value = !newValue ? '1' : '0';
                        self.showError(error);
                    }
                });
            };
            this.addTrackedListener(document, 'change', switchChangeHandler);

            // Plus button click - add related record from inline editing combobox
            const addRelatedClickHandler = (e) => {
                const addBtn = e.target.closest('.btn-add-related');
                if (!addBtn) return;
                if (addBtn.dataset.opening) return;

                // Only handle if it's inside our editing row
                const row = addBtn.closest('tr.editing');
                if (!row) return;
                const table = row.closest('table');
                if (!table || table !== document.querySelector(self.options.tableSelector)) return;

                e.preventDefault();
                e.stopPropagation();
                self.openAddRelatedPopup(addBtn);
            };
            this.addTrackedListener(document, 'click', addRelatedClickHandler);

            // Row click - open form popup (when not clicking on menu or inline switch)
            // IMPORTANT: Attach to the TABLE element, not document, to scope the handler to this form only
            // This prevents old instances from handling clicks on new forms (the context menu bug fix)
            const the_table = document.querySelector(self.options.tableSelector);
            if (the_table) {
                const rowClickHandler = (e) => {
                    if (e.target.closest('.row-menu-trigger')) return;
                    if (e.target.closest('lib-switch')) return; // Don't open detail when clicking switch
                    if (e.target.closest('.btn-add-related')) return; // Don't open detail when clicking plus button
                    if (self.editingRowId) return; // Don't open detail during inline editing

                    // Don't open sidepanel when clicking on inline edit elements
                    if (e.target.closest('.editing')) return;
                    if (e.target.closest('.inline-edit-button-row')) return;
                    if (e.target.matches('input, select, textarea')) return;
                    if (e.target.closest('lib-combo')) return;

                    const row = e.target.closest('tbody tr');
                    if (!row) return;

                    // Set active class on clicked row, remove from siblings
                    the_table.querySelectorAll('tbody tr.active').forEach(r => r.classList.remove('active'));
                    row.classList.add('active');

                    const rowId = self.getRowId(row);
                    self.openFormPopup(rowId, false);
                };
                this.addTrackedListener(the_table, 'click', rowClickHandler);

                // Listen for sidepanel close to reset active rows
                const resetActiveRows = () => {
                    the_table.querySelectorAll('tbody tr.active').forEach(r => r.classList.remove('active'));
                };
                this.addTrackedListener(document, 'sidepanel-closed', resetActiveRows);
            }

            // Edit controls - Save
            const saveHandler = () => self.saveInlineEdit();
            this.editControls.querySelector('.btn-save').addEventListener('click', saveHandler);

            // Edit controls - Cancel
            const cancelHandler = () => self.cancelInlineEditing();
            this.editControls.querySelector('.btn-cancel').addEventListener('click', cancelHandler);

            // Keyboard shortcuts
            const keydownHandler = (e) => {
                if (!self.editingRowId) return;
                // Prevent multiple CmaInlineEdit instances from handling the same keyboard event
                if (e._cmaInlineEditHandled) return;

                // Ctrl+S - Save
                if (e.ctrlKey && e.key === 's') {
                    e.preventDefault();
                    e._cmaInlineEditHandled = true;
                    // console.log('[INLINE-EDIT] Ctrl+S → saveInlineEdit()');
                    self.saveInlineEdit();
                }
                // Escape - Cancel
                if (e.key === 'Escape') {
                    e.preventDefault();
                    e._cmaInlineEditHandled = true;
                    self.cancelInlineEditing();
                }
                // Tab navigation in inline edit mode
                if (self.inlineEditMode && e.key === 'Tab') {
                    e._cmaInlineEditHandled = true;
                    self.handleTabNavigation(e);
                }
                // Enter in input - Save (only for single-line inputs)
                if (e.key === 'Enter' && e.target.matches('input:not([type="checkbox"])')) {
                    e.preventDefault();
                    e._cmaInlineEditHandled = true;
                    // console.log('[INLINE-EDIT] Enter → saveInlineEdit()');
                    self.saveInlineEdit();
                }
            };
            this.addTrackedListener(document, 'keydown', keydownHandler);

            // Track changes in editing row
            const changeHandler = (e) => {
                if (e.target.closest('.editing-row, tr.editing')) {
                    self.isDirty = true;
                }
            };
            this.addTrackedListener(document, 'change', changeHandler);
            this.addTrackedListener(document, 'input', changeHandler);
        }

        /**
         * Get row ID from table row
         */
        getRowId(row) {
            // Try data attribute first
            let id = row.dataset.id;
            if (id) return id;

            // Try id attribute (lt_row_123 format)
            const rowId = row.id;
            if (rowId && rowId.startsWith('lt_row_')) {
                return rowId.replace('lt_row_', '');
            }

            // Try first cell (ID column)
            const firstCell = row.querySelector('td:first-child');
            return firstCell ? firstCell.textContent.trim() : null;
        }

        /**
         * Show context menu
         */
        showContextMenu(x, y, rowId, row) {
            // Hide any other context menus (prevents duplicate menus)
            document.querySelectorAll('.cma-context-menu').forEach(m => {
                if (m !== this.contextMenu) {
                    m.style.display = 'none';
                }
            });

            this.contextMenu.dataset.rowId = rowId;
            this.contextMenu._menuOwnerId = this._instanceId; // Mark which instance owns the menu
            this._contextMenuRow = row; // Store row reference
            this.contextMenu.style.left = x + 'px';
            this.contextMenu.style.top = y + 'px';
            this.contextMenu.style.display = 'block';

            // Adjust position if menu goes off screen
            const rect = this.contextMenu.getBoundingClientRect();
            if (rect.right > window.innerWidth) {
                this.contextMenu.style.left = (x - rect.width) + 'px';
            }
            if (rect.bottom > window.innerHeight) {
                this.contextMenu.style.top = (y - rect.height) + 'px';
            }
        }

        /**
         * Hide context menu
         */
        hideContextMenu() {
            this.contextMenu.style.display = 'none';
        }

        /**
         * Start editing a row (legacy method - positions controls above row)
         */
        async startEditing(rowId, row) {
            if (this.editingRowId) {
                if (this.isDirty) {
                    const changeSummary = this.formatChangeSummary();
                    const confirmed = await libConfirm('Er zijn niet-opgeslagen wijzigingen.' + changeSummary, {
                        title: 'Niet-opgeslagen wijzigingen',
                        confirmText: 'Negeren',
                        cancelText: 'Terug',
                        type: 'warning',
                        html: true
                    });
                    if (!confirmed) {
                        return;
                    }
                }
                this.cancelInlineEditing();
            }

            this.editingRowId = rowId;
            this.isDirty = false;
            row.classList.add('editing-row');

            this.loadRecordData(rowId, row);
            this.positionEditControls(row);
            this.editControls.style.display = 'block';
        }

        /**
         * Start inline editing - true spreadsheet-like editing with button row below
         */
        async startInlineEditing(rowId, row) {
            // DEBUG: trace who calls startInlineEditing
            // console.log('[INLINE-EDIT] startInlineEditing called for rowId:', rowId, 'instance:', this._instanceId);
            // console.log('[INLINE-EDIT] caller stack:', new Error().stack);

            // Guard: prevent re-entrant/double calls
            if (this._startingEdit) {
                console.warn('[INLINE-EDIT] BLOCKED: startInlineEditing already in progress!');
                return;
            }
            this._startingEdit = true;

            // Check canEdit permission first (defense in depth)
            if (!this.options.canEdit) {
                // cmaLog.log('[CmaInlineEdit] Inline editing disabled - form is readonly');
                this._startingEdit = false;
                return;
            }
            if (!row) {
                cmaLog.warn('[CmaInlineEdit] startInlineEditing called without row element');
                this._startingEdit = false;
                return;
            }
            // Cancel editing on any OTHER instance first
            if (!CmaInlineEdit.cancelOtherEditing(this)) {
                // Another instance has a dirty row that failed validation - abort
                this._startingEdit = false;
                return;
            }

            if (this.editingRowId) {
                if (this.isDirty) {
                    // Validate current editing row before allowing switch
                    if (!this.validateRow(this.editingRow)) {
                        // Validation failed - scroll to current editing row and stay
                        this.editingRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        this._startingEdit = false;
                        return;
                    }
                    const changeSummary = this.formatChangeSummary();
                    const confirmed = await libConfirm('Er zijn niet-opgeslagen wijzigingen.' + changeSummary, {
                        title: 'Niet-opgeslagen wijzigingen',
                        confirmText: 'Negeren',
                        cancelText: 'Terug',
                        type: 'warning',
                        html: true
                    });
                    if (!confirmed) {
                        this._startingEdit = false;
                        return;
                    }
                }
                this.cancelInlineEditing();
            }

            // Lock column widths before editing
            this.lockColumnWidths(row);

            this.editingRowId = rowId;
            this.editingRow = row;
            this.isDirty = false;
            this.inlineEditMode = true;

            row.classList.add('editing');
            document.body.classList.add('inline-editing');

            this._startingEdit = false;
            this.loadRecordForInlineEdit(rowId, row);
        }

        /**
         * Lock column widths to prevent layout shift during editing
         * PERFORMANCE FIX: Use CSS stylesheet injection instead of per-cell inline styles
         */
        lockColumnWidths(row) {
            if (!row) return;
            const table = row.closest('table');
            if (!table) return;

            // Get current widths from the row
            const cells = row.querySelectorAll('td');
            const cellWidths = Array.from(cells).map(td => td.offsetWidth);

            // Ensure table has an ID for CSS targeting
            if (!table.id) {
                table.id = 'table_' + Date.now();
            }
            this._lockedTableId = table.id;

            // PERFORMANCE FIX: Use a single <style> element instead of per-cell inline styles
            // This triggers only ONE reflow instead of potentially hundreds
            let styleEl = document.getElementById('cma-column-lock-styles');
            if (!styleEl) {
                styleEl = document.createElement('style');
                styleEl.id = 'cma-column-lock-styles';
                document.head.appendChild(styleEl);
            }

            // Build CSS rules for each column
            const rules = cellWidths.map((width, index) => {
                if (!width) return '';
                const colNum = index + 1;
                return `#${table.id} th:nth-child(${colNum}), #${table.id} td:nth-child(${colNum}) { width: ${width}px !important; }`;
            }).filter(Boolean).join('\n');

            styleEl.textContent = rules;
            this.lockedCellWidths = cellWidths;
        }

        /**
         * Unlock column widths after editing
         * PERFORMANCE FIX: Remove style element instead of clearing per-cell styles
         */
        unlockColumnWidths() {
            // Remove the injected style element
            const styleEl = document.getElementById('cma-column-lock-styles');
            if (styleEl) {
                styleEl.textContent = '';
            }
            this.lockedCellWidths = null;
            this._lockedTableId = null;
        }

        /**
         * Load record data for inline editing
         */
        async loadRecordForInlineEdit(rowId, row) {
            const self = this;

            // Store original cell contents for cancel
            this.originalData = {};
            row.querySelectorAll('td').forEach((td, i) => {
                self.originalData[i] = td.innerHTML;
            });

            // CRITICAL FIX: Read jsonForm from table's data-attribute, NOT from closure
            const table = row.closest('table');
            const jsonFormFromTable = table?.dataset?.jsonForm;
            const jsonFormToUse = jsonFormFromTable || this.options.jsonForm;

            // console.log('[INLINE-EDIT] === loadRecordForInlineEdit ===');
            // console.log('[INLINE-EDIT] rowId:', rowId, 'jsonForm:', jsonFormToUse, 'instance:', this._instanceId);
            // console.log('[INLINE-EDIT] fields:', (this.options.fields || []).length, 'names:', (this.options.fields || []).map(f => f.name));
            // console.log('[INLINE-EDIT] TD data-fields:', Array.from(row.querySelectorAll('td[data-field]')).map(td => td.dataset.field));
            // console.log('[INLINE-EDIT] TD BEFORE edit (innerHTML):', Array.from(row.querySelectorAll('td[data-field]')).map(td => td.dataset.field + '=' + td.innerHTML.substring(0, 60)));
            // console.log('[INLINE-EDIT] Already has inputs?', row.querySelectorAll('input, select, textarea').length > 0 ? 'YES (' + row.querySelectorAll('input, select, textarea').length + ')' : 'NO');

            // Load full record via API
            const requestData = new URLSearchParams({
                action: 'record',
                ID: rowId
            });
            requestData.append('jsonForm', jsonFormToUse);

            try {
                const data = await fetchJson(
                    this.options.apiUrl + '?' + requestData.toString(),
                    {},
                    'loadRecordForInlineEdit'
                );

                // log.log('Inline edit record loaded:', data);
                if (data.success) {
                    const record = data.fields || data.data || data.record;
                    // log.log('Record data:', record);
                    self.renderInlineEditRow(row, record);
                    self.insertButtonRow(row);
                } else {
                    log.error('[loadRecordForInlineEdit] Server returned error:', data.error);
                    self.showError(data.error || 'Fout bij laden record');
                    self.cancelInlineEditing();
                }
            } catch (error) {
                log.error('[loadRecordForInlineEdit] Failed:', error.message);
                self.showError(error.message || 'Netwerkfout bij laden record');
                self.cancelInlineEditing();
            }
        }

        /**
         * Render inline edit controls in row cells
         */
        renderInlineEditRow(row, record) {
            const self = this;
            this.editRecord = record;

            // console.log('[INLINE-EDIT] === renderInlineEditRow ===');
            // console.log('[INLINE-EDIT] record keys:', record ? Object.keys(record) : 'NULL');
            // console.log('[INLINE-EDIT] record values:', record ? Object.entries(record).map(function(e) { return e[0] + '=' + JSON.stringify(e[1]).substring(0, 50); }) : 'NULL');
            // console.log('[INLINE-EDIT] fields:', (this.options.fields || []).map(f => f.name + ':' + f.type + (f.readonly ? ':RO' : '') + (f.newOnly ? ':NEW' : '')));

            if (!this.options.fields || this.options.fields.length === 0) {
                console.error('[INLINE-EDIT] fields array is empty - inline editing will not work');
            }

            // Show TD content BEFORE any modification (innerHTML to detect existing inputs)
            Array.from(row.querySelectorAll('td[data-field]')).forEach(function(td) {
                const fn = td.dataset.field;
                const fieldDef = self.getFieldDefinition(fn);
                const exactMatch = record && record[fn] !== undefined;
                const lcMatch = !exactMatch && record ? Object.keys(record).find(k => k.toLowerCase() === fn.toLowerCase()) : null;
                const val = exactMatch ? record[fn] : (lcMatch ? record[lcMatch] : undefined);
                const hasInput = td.querySelector('input, select, textarea') !== null;
                // console.log('[INLINE-EDIT] TD "' + fn + '":' +
                //     (hasInput ? ' *** HAS INPUT ALREADY ***' : '') +
                //     ' html="' + td.innerHTML.substring(0, 50) + '"' +
                //     ' def=' + (fieldDef ? fieldDef.type + (fieldDef.readonly ? ',RO' : '') + (fieldDef.newOnly ? ',NEW' : '') : 'MISSING') +
                //     ' record=' + (val !== undefined ? JSON.stringify(val).substring(0, 40) : 'NOT FOUND'));
            });

            let editableCount = 0;
            row.querySelectorAll('td').forEach((td, i) => {
                try {
                    const fieldName = td.dataset.field;
                    if (!fieldName) return;

                    const fieldDef = self.getFieldDefinition(fieldName);
                    if (!fieldDef) {
                        console.warn('[TD' + i + '] "' + fieldName + '": no fieldDef, skipping');
                        return;
                    }

                    // Store original display value (before we modify the cell)
                    td.dataset.originalHtml = td.innerHTML;

                    // Check for existing lib-switch before readonly check (need to detect type)
                    const hasSwitch = td.querySelector('lib-switch') !== null;

                    // Skip readonly and newOnly fields (newOnly fields can only be set on new records)
                    if (fieldDef.readonly || fieldDef.newOnly) {
                        // console.log('[TD' + i + '] "' + fieldName + '": skipped (' + (fieldDef.readonly ? 'readonly' : 'newOnly') + ')');
                        return;
                    }

                    // Case-insensitive record value lookup (record keys may differ in case)
                    let value = '';
                    let matchMethod = 'none';
                    if (record) {
                        if (record[fieldName] !== undefined) {
                            value = record[fieldName];
                            matchMethod = 'exact';
                        } else {
                            // Fallback: case-insensitive lookup
                            const lcField = fieldName.toLowerCase();
                            const matchKey = Object.keys(record).find(k => k.toLowerCase() === lcField);
                            if (matchKey) {
                                value = record[matchKey];
                                matchMethod = 'CI:' + matchKey;
                            }
                        }
                    }

                    const control = self.renderEditControl(fieldName, fieldDef, value, hasSwitch ? td : null);

                    // console.log('[TD' + i + '] "' + fieldName + '": type=' + fieldDef.type + ' match=' + matchMethod + ' value=' + JSON.stringify(value).substring(0, 60) + ' → HTML=' + control.substring(0, 100));

                    td.innerHTML = control;
                    editableCount++;

                    // Verify: check what the DOM actually has after innerHTML
                    const input = td.querySelector('input[type="text"], textarea');
                    if (input) {
                        const domValue = input.value;
                        const attrValue = input.getAttribute('value');
                        if (value !== '' && value !== null && value !== undefined && !domValue) {
                            console.error('[TD' + i + '] "' + fieldName + '": VALUE LOST! record=' + JSON.stringify(value) + ' domValue=' + JSON.stringify(domValue) + ' attrValue=' + JSON.stringify(attrValue) + ' outerHTML=' + input.outerHTML.substring(0, 150));
                            // Safeguard: set value directly
                            input.value = value;
                            // console.log('[TD' + i + '] "' + fieldName + '": safeguard applied, value now=' + JSON.stringify(input.value));
                        }
                    }
                } catch (e) {
                    console.error('[TD' + i + '] Error:', e.message, e.stack);
                }
            });

            if (editableCount === 0) {
                console.error('No editable fields found!');
                // console.log('TD fields:', Array.from(row.querySelectorAll('td[data-field]')).map(td => td.dataset.field));
                // console.log('Def fields:', (this.options.fields || []).map(f => f.name));
            }
            console.groupEnd();

            // Set up cascading combo filters for this editing row
            this.setupInlineComboFilters(row);

            // Focus first editable field
            const firstInput = row.querySelector('input:not([type="hidden"]), lib-combo, textarea');
            if (firstInput) {
                firstInput.focus();
                if (firstInput.select) firstInput.select();
            }

            // POST-RENDER VERIFICATION: check all text inputs have their values
            setTimeout(() => {
                const inputs = row.querySelectorAll('input[type="text"]');
                inputs.forEach(inp => {
                    // console.log('[INLINE-EDIT] POST-RENDER "' + inp.name + '": value=' + JSON.stringify(inp.value) + ' attr=' + JSON.stringify(inp.getAttribute('value')) + ' visible=' + (inp.offsetWidth > 0));
                });
            }, 100);
        }

        /**
         * Insert button row below the editing row
         */
        insertButtonRow(row) {
            const self = this;

            // Remove existing button row if any
            document.querySelectorAll('.inline-edit-button-row').forEach(el => el.remove());

            const buttonRowHtml = '<div class="inline-edit-button-row">' +
                '<div class="inline-edit-buttons">' +
                '<button type="button" class="btn btn-primary btn-save-inline">' +
                '<span class="lnr lnr-checkmark-circle"></span> Opslaan' +
                '</button>' +
                '<button type="button" class="btn btn-secondary btn-cancel-inline">' +
                '<span class="lnr lnr-cross-circle"></span> Annuleren' +
                '</button>' +
                '</div>' +
                '</div>';

            const buttonRow = createElement(buttonRowHtml);

            // Position below the editing row
            const rect = row.getBoundingClientRect();
            buttonRow.style.top = (rect.top + window.scrollY + rect.height + 2) + 'px';
            buttonRow.style.left = (rect.left + window.scrollX) + 'px';

            document.body.appendChild(buttonRow);

            // Bind button events
            buttonRow.querySelector('.btn-save-inline').addEventListener('click', () => self.saveInlineEdit());
            buttonRow.querySelector('.btn-cancel-inline').addEventListener('click', () => self.cancelInlineEditing());
        }

        /**
         * Save inline edit
         */
        async saveInlineEdit() {
            // cmaLog.log('[saveInlineEdit] CALLED - editingRowId:', this.editingRowId, 'editingRow:', !!this.editingRow, '_saving:', !!this._saving);
            if (this._saving) {
                cmaLog.warn('[saveInlineEdit] BLOCKED - already saving');
                return;
            }
            if (!this.editingRowId || !this.editingRow) {
                // cmaLog.log('[saveInlineEdit] EARLY RETURN - no editingRowId or editingRow');
                return;
            }
            this._saving = true;

            const self = this;
            const row = this.editingRow;

            // Skip save if row is no longer in DOM (user navigated to different form)
            if (!row.isConnected) {
                cmaLog.warn('[saveInlineEdit] Row is detached from DOM - skipping save');
                this.editingRowId = null;
                this.editingRow = null;
                return;
            }

            // CRITICAL FIX: Read jsonForm from table's data-attribute at save time, NOT from closure
            // This prevents stale form reference when user has navigated to a different form
            const table = row.closest('table');
            const jsonFormFromTable = table?.dataset?.jsonForm;
            const jsonFormToUse = jsonFormFromTable || this.options.jsonForm;

            if (!jsonFormToUse) {
                log.error('[saveInlineEdit] No jsonForm found - table:', table?.id, 'options:', this.options.jsonForm);
                this.showError('Formulier niet gevonden');
                return;
            }

            // Debug: Log which form we're saving to
            if (jsonFormFromTable && jsonFormFromTable !== this.options.jsonForm) {
                log.warn('[saveInlineEdit] Using table data-jsonForm:', jsonFormFromTable, 'instead of closure:', this.options.jsonForm);
            }

            const formData = new URLSearchParams({
                action: 'save',
                ID: this.editingRowId
            });

            // Add form identifier - use table data attribute, not closure
            formData.append('jsonForm', jsonFormToUse);

            // Collect field values from the row
            const rowData = this.collectRowData(row);
            // console.log('[INLINE-EDIT] collectRowData result:', JSON.stringify(rowData));
            // console.log('[INLINE-EDIT] rowData keys:', Object.keys(rowData));
            for (const [key, value] of Object.entries(rowData)) {
                formData.append(key, value);
            }
            // console.log('[INLINE-EDIT] POST body:', formData.toString());

            // Show saving state
            row.classList.add('saving');
            const buttonRow = document.querySelector('.inline-edit-button-row');
            const saveBtn = buttonRow?.querySelector('.btn-save-inline');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.textContent = 'Opslaan...';
            }

            try {
                // console.log('[INLINE-EDIT] Calling fetchJson POST to:', this.options.apiUrl);
                const data = await fetchJson(this.options.apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData.toString()
                }, 'saveInlineEdit');

                // console.log('[INLINE-EDIT] SAVE RESPONSE:', JSON.stringify(data));
                if (data.success) {
                    // console.log('[INLINE-EDIT] SAVE SUCCESS - calling updateRowAfterSave');
                    self.showSuccess('Opgeslagen');
                    self.updateRowAfterSave(row, rowData);
                } else {
                    console.error('[INLINE-EDIT] SAVE FAILED:', data.error);
                    self.showError(data.error || 'Fout bij opslaan');
                    self._saving = false;
                    row.classList.remove('saving');
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = '<span class="lnr lnr-checkmark-circle"></span> Opslaan';
                    }
                }
            } catch (error) {
                console.error('[INLINE-EDIT] SAVE EXCEPTION:', error.message, error.stack);
                self.showError(error.message || 'Netwerkfout bij opslaan');
                self._saving = false;
                row.classList.remove('saving');
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<span class="lnr lnr-checkmark-circle"></span> Opslaan';
                }
            }
        }

        /**
         * Update row after successful save - fetch fresh HTML from server
         */
        async updateRowAfterSave(row, formData) {
            const self = this;
            const rowId = this.editingRowId;

            // CRITICAL FIX: Read jsonForm from table's data-attribute, NOT from closure
            const table = row.closest('table');
            const jsonFormFromTable = table?.dataset?.jsonForm;
            const jsonFormToUse = jsonFormFromTable || this.options.jsonForm;

            // Collect current column names from TABLE HEADERS (not TDs) - THs don't change during editing
            const currentColumns = [];
            const thead = table?.querySelector('thead');
            if (thead) {
                const allThs = thead.querySelectorAll('th[data-field]');
                allThs.forEach((th) => {
                    const field = th.dataset.field;
                    if (field) currentColumns.push(field);
                });
            }
            // Fallback: if no headers found, try TDs
            if (currentColumns.length === 0) {
                const allTds = row.querySelectorAll('td[data-field]');
                allTds.forEach((td) => {
                    const field = td.dataset.field;
                    if (field) currentColumns.push(field);
                });
            }

            // Fetch fresh row HTML from server (single source of truth for rendering)
            const params = new URLSearchParams({
                action: 'getRow',
                ID: rowId,
                displayMode: 2 // Table mode
            });
            params.append('jsonForm', jsonFormToUse);
            // Pass current columns so server uses the same columns as displayed
            if (currentColumns.length > 0) {
                params.append('columns', currentColumns.join(','));
            }

            try {
                // console.log('[INLINE-EDIT] updateRowAfterSave: fetching row ID:', rowId, 'form:', jsonFormToUse);
                // console.log('[INLINE-EDIT] updateRowAfterSave: columns from TH:', currentColumns);
                // console.log('[INLINE-EDIT] updateRowAfterSave: columns count:', currentColumns.length);
                // Debug: show all TH headers in the table
                if (table) {
                    const allThs = table.querySelectorAll('thead th');
                    // console.log('[INLINE-EDIT] updateRowAfterSave: ALL TH elements:', allThs.length);
                    allThs.forEach((th, idx) => {
                        // console.log('[INLINE-EDIT]   TH[' + idx + ']: data-field="' + (th.dataset.field || 'NONE') + '" text="' + th.textContent.trim().substring(0, 30) + '"');
                    });
                }
                // Debug: show current row TDs and their data-field attributes
                const currentTds = row.querySelectorAll('td');
                // console.log('[INLINE-EDIT] updateRowAfterSave: current row TD count:', currentTds.length);
                currentTds.forEach((td, idx) => {
                    // console.log('[INLINE-EDIT]   TD[' + idx + ']: data-field="' + (td.dataset.field || 'NONE') + '" html="' + td.innerHTML.substring(0, 60) + '"');
                });

                const fullUrl = this.options.apiUrl + '?' + params.toString();
                // console.log('[INLINE-EDIT] updateRowAfterSave URL:', fullUrl);
                const data = await fetchJson(fullUrl, {}, 'updateRowAfterSave');

                // console.log('[INLINE-EDIT] updateRowAfterSave RESPONSE success:', data.success);
                // console.log('[INLINE-EDIT] updateRowAfterSave RESPONSE html length:', data.html ? data.html.length : 'NO HTML');
                // console.log('[INLINE-EDIT] updateRowAfterSave RESPONSE html:', data.html ? data.html.substring(0, 1000) : 'EMPTY');
                // console.log('[INLINE-EDIT] updateRowAfterSave RESPONSE full:', JSON.stringify(data).substring(0, 2000));
                if (data._debug) {
                    // console.log('[INLINE-EDIT] updateRowAfterSave SERVER DEBUG:', JSON.stringify(data._debug));
                }

                if (data.success && data.html) {
                    // Parse the HTML and extract td contents
                    const temp = document.createElement('tbody');
                    temp.innerHTML = data.html;
                    const newRow = temp.querySelector('tr');

                    if (newRow) {
                        // Debug: compare old and new row
                        const newTds = newRow.querySelectorAll('td');
                        // console.log('[INLINE-EDIT] updateRowAfterSave: NEW row TD count:', newTds.length, 'OLD row TD count:', currentTds.length);
                        newTds.forEach((td, idx) => {
                            const field = td.dataset.field || 'NONE';
                            const content = td.innerHTML.substring(0, 80);
                            const isEmpty = td.textContent.trim() === '' && !td.querySelector('lib-switch');
                            // console.log('[INLINE-EDIT]   NEW TD[' + idx + ']: data-field="' + field + '" empty=' + isEmpty + ' html="' + content + '"');
                        });
                        // console.log('[INLINE-EDIT] updateRowAfterSave: replacing row. New HTML:', newRow.innerHTML.substring(0, 500));
                        // Copy attributes from new row
                        row.className = newRow.className;
                        // Replace all td elements
                        row.innerHTML = newRow.innerHTML;
                        // console.log('[INLINE-EDIT] updateRowAfterSave: row replaced successfully');
                        // Debug: verify after replacement
                        const verifyTds = row.querySelectorAll('td');
                        // console.log('[INLINE-EDIT] updateRowAfterSave: VERIFY after replace - TD count:', verifyTds.length);
                        verifyTds.forEach((td, idx) => {
                            const isEmpty = td.textContent.trim() === '' && !td.querySelector('lib-switch');
                            if (isEmpty) {
                                console.warn('[INLINE-EDIT]   EMPTY TD[' + idx + ']: data-field="' + (td.dataset.field || 'NONE') + '"');
                            }
                        });
                    } else {
                        console.warn('[INLINE-EDIT] updateRowAfterSave: No TR in response HTML:', data.html.substring(0, 500));
                    }
                } else if (!data.success) {
                    console.error('[INLINE-EDIT] updateRowAfterSave: server error:', data.error);
                    if (data._exception) {
                        console.error('[INLINE-EDIT] updateRowAfterSave: server exception:', JSON.stringify(data._exception));
                    }
                } else {
                    console.warn('[INLINE-EDIT] updateRowAfterSave: missing html in response:', JSON.stringify(data).substring(0, 500));
                }
            } catch (error) {
                console.error('[INLINE-EDIT] updateRowAfterSave FAILED:', error.message, error.stack);
            }

            // Clean up editing state
            self.finishInlineEditing();
        }

        /**
         * Clean up after inline editing (shared by save success and cancel)
         */
        finishInlineEditing() {
            if (this.editingRow) {
                this.editingRow.classList.remove('editing', 'saving');
            }
            document.querySelectorAll('.inline-edit-button-row').forEach(el => el.remove());
            document.body.classList.remove('inline-editing');

            this.unlockColumnWidths();

            this.editingRowId = null;
            this.editingRow = null;
            this.isDirty = false;
            this.originalData = null;
            this.editRecord = null;
            this.inlineEditMode = false;
            this._saving = false;
            this._startingEdit = false;
        }

        /**
         * Cancel inline editing and restore original cell values
         */
        cancelInlineEditing() {
            if (!this.editingRow) return;

            const self = this;
            const row = this.editingRow;

            // Restore original cell contents
            if (this.originalData) {
                row.querySelectorAll('td').forEach((td, i) => {
                    if (self.originalData[i] !== undefined) {
                        td.innerHTML = self.originalData[i];
                    }
                });
            }

            this.finishInlineEditing();
        }

        /**
         * Get list of changed fields comparing current values with editRecord
         * @returns {Array} Array of { field, label, oldValue, newValue } objects
         */
        getChangedFields() {
            if (!this.editingRow || !this.editRecord) return [];

            const changes = [];
            const row = this.editingRow;

            row.querySelectorAll('td[data-field]').forEach(td => {
                const fieldName = td.dataset.field;
                if (!fieldName) return;

                const fieldDef = this.getFieldDefinition(fieldName);
                if (!fieldDef) return;

                // Get current value from input
                const input = td.querySelector('input, select, textarea');
                if (!input) return;

                let currentValue = '';
                if (input.type === 'checkbox') {
                    currentValue = input.checked ? 'Ja' : 'Nee';
                } else if (input.tagName === 'SELECT') {
                    const selected = input.options[input.selectedIndex];
                    currentValue = selected ? selected.text : '';
                } else {
                    currentValue = input.value || '';
                }

                // Get original value from editRecord
                let originalValue = this.editRecord[fieldName] ?? this.editRecord[fieldName.toLowerCase()] ?? '';

                // For checkboxes, normalize original value
                if (input.type === 'checkbox') {
                    const isTrue = originalValue === true || originalValue === 'true' || originalValue === '1' || originalValue === '-1' || originalValue === 'J';
                    originalValue = isTrue ? 'Ja' : 'Nee';
                }

                // For selects, try to get display text if we have a lookup
                if (input.tagName === 'SELECT' && fieldDef.options) {
                    const opt = fieldDef.options.find(o => String(o.value) === String(originalValue));
                    if (opt) originalValue = opt.text || opt.label || originalValue;
                }

                const origStr = String(originalValue || '').trim();
                const currStr = String(currentValue || '').trim();

                if (origStr !== currStr) {
                    const label = fieldDef.label || fieldDef.name || fieldName;
                    changes.push({
                        field: fieldName,
                        label: label,
                        oldValue: origStr || '<leeg>',
                        newValue: currStr || '<leeg>'
                    });
                }
            });

            return changes;
        }

        /**
         * Format changed fields for display in confirm dialog
         * @param {number} maxItems - Maximum number of changes to show (default 5)
         * @param {number} maxValueLength - Maximum length of value to show (default 40)
         * @returns {string} HTML string with change summary
         */
        formatChangeSummary(maxItems = 5, maxValueLength = 40) {
            const changes = this.getChangedFields();
            if (changes.length === 0) return '';

            const truncate = (str, len) => {
                if (!str || str.length <= len) return str;
                return str.substring(0, len) + '...';
            };

            let html = '<div style="margin-top:12px;font-size:var(--font-size-sm);color:#666;text-align:left;">';
            const fieldLabel = changes.length === 1 ? 'Gewijzigd veld:' : 'Gewijzigde velden:';
            html += '<div style="margin-bottom:6px;font-weight:500;">' + fieldLabel + '</div>';
            html += '<ul style="margin:0;padding-left:20px;list-style:disc;">';

            const showCount = Math.min(changes.length, maxItems);
            for (let i = 0; i < showCount; i++) {
                const change = changes[i];
                const isLongValue = change.newValue.length > maxValueLength || change.oldValue.length > maxValueLength;
                if (isLongValue) {
                    html += `<li><strong>${change.label}</strong></li>`;
                } else {
                    html += `<li><strong>${change.label}</strong>: ${truncate(change.oldValue, maxValueLength)} → ${truncate(change.newValue, maxValueLength)}</li>`;
                }
            }

            html += '</ul>';

            if (changes.length > maxItems) {
                html += `<div style="margin-top:4px;font-style:italic;">... en ${changes.length - maxItems} meer ...</div>`;
            }

            html += '</div>';
            return html;
        }

        /**
         * Handle Tab navigation between editable cells
         */
        handleTabNavigation(e) {
            if (!this.editingRow) return;

            const row = this.editingRow;
            const current = e.target;
            const inputs = Array.from(row.querySelectorAll('input:not([type="hidden"]), select, textarea'));
            const currentIndex = inputs.indexOf(current);

            if (currentIndex === -1) return;

            if (e.shiftKey) {
                if (currentIndex > 0) {
                    e.preventDefault();
                    const prev = inputs[currentIndex - 1];
                    prev.focus();
                    if (prev.select) prev.select();
                }
            } else {
                if (currentIndex < inputs.length - 1) {
                    e.preventDefault();
                    const next = inputs[currentIndex + 1];
                    next.focus();
                    if (next.select) next.select();
                }
            }
        }

        /**
         * Load record data for editing
         */
        async loadRecordData(rowId, row) {
            const self = this;

            this.originalData = {};
            row.querySelectorAll('td').forEach((td, i) => {
                self.originalData[i] = td.innerHTML;
            });

            // CRITICAL FIX: Read jsonForm from table's data-attribute, NOT from closure
            const table = row.closest('table');
            const jsonFormFromTable = table?.dataset?.jsonForm;
            const jsonFormToUse = jsonFormFromTable || this.options.jsonForm;

            const requestData = new URLSearchParams({
                action: 'record',
                ID: rowId
            });
            requestData.append('jsonForm', jsonFormToUse);

            try {
                const data = await fetchJson(
                    this.options.apiUrl + '?' + requestData.toString(),
                    {},
                    'loadRecordData'
                );

                if (data.success) {
                    self.renderEditableRow(row, data.fields || data.data || data.record);
                } else {
                    log.error('[loadRecordData] Server returned error:', data.error);
                    self.showError(data.error || 'Fout bij laden record');
                    self.cancelInlineEditing();
                }
            } catch (error) {
                log.error('[loadRecordData] Failed:', error.message);
                self.showError(error.message || 'Netwerkfout bij laden record');
                self.cancelInlineEditing();
            }
        }

        /**
         * Render editable controls in row cells
         */
        renderEditableRow(row, record) {
            const self = this;

            row.querySelectorAll('td').forEach(td => {
                const fieldName = td.dataset.field;
                if (!fieldName) return;

                const fieldDef = self.getFieldDefinition(fieldName);
                if (!fieldDef || fieldDef.readonly || fieldDef.newOnly) return;

                // Check for existing lib-switch before modifying cell
                const hasSwitch = td.querySelector('lib-switch') !== null;

                // Case-insensitive record value lookup
                let value = '';
                if (record) {
                    if (record[fieldName] !== undefined) {
                        value = record[fieldName];
                    } else {
                        const lcField = fieldName.toLowerCase();
                        const matchKey = Object.keys(record).find(k => k.toLowerCase() === lcField);
                        if (matchKey) value = record[matchKey];
                    }
                }
                const control = self.renderEditControl(fieldName, fieldDef, value, hasSwitch ? td : null);

                td.innerHTML = control;
            });

            // Set up cascading combo filters for this editing row
            this.setupInlineComboFilters(row);

            // Focus first editable field
            const firstInput = row.querySelector('input:not([type="hidden"]), lib-combo, textarea');
            if (firstInput) firstInput.focus();
        }

        /**
         * Set up cascading combo filters for inline editing row.
         * When a parent combo changes, reloads dependent combo with filtered options.
         * @param {HTMLElement} row - The editing row
         */
        setupInlineComboFilters(row) {
            const filterMap = this.options.filterByFieldMap;
            if (!filterMap || Object.keys(filterMap).length === 0) return;

            const self = this;
            Object.keys(filterMap).forEach(function(childField) {
                var parentField = filterMap[childField];
                var parentCombo = row.querySelector('lib-combo[name="' + parentField + '"]');
                if (!parentCombo) return;

                parentCombo.addEventListener('change', function() {
                    var parentValue = parentCombo.value || '';
                    var childCombo = row.querySelector('lib-combo[name="' + childField + '"]');
                    if (!childCombo) return;

                    // Clear child combo
                    childCombo.value = '';

                    if (!parentValue) {
                        childCombo.setOptions([]);
                        return;
                    }

                    // Fetch filtered options
                    var url = self.options.apiUrl + '?action=combo&form=' +
                        encodeURIComponent(self.options.jsonForm) +
                        '&field=' + encodeURIComponent(childField) +
                        '&filterField=' + encodeURIComponent(parentField) +
                        '&filterValue=' + encodeURIComponent(parentValue);

                    fetch(url)
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.success && data.options) {
                                var opts = data.options.map(function(o) {
                                    return { value: String(o.id), label: o.text };
                                });
                                childCombo.setOptions(opts);
                            } else {
                                childCombo.setOptions([]);
                            }
                        })
                        .catch(function(err) {
                            cmaLog.error('Inline combo filter failed:', err);
                        });
                });
            });
        }

        /**
         * Get field definition by name
         */
        getFieldDefinition(fieldName) {
            if (!this.options.fields || !Array.isArray(this.options.fields)) {
                return null;
            }
            // Case-insensitive match - field names from TDs and field definitions
            // may have different casing (e.g. user column preferences vs JSON definition)
            const lcName = fieldName.toLowerCase();
            return this.options.fields.find(f => f && f.name && f.name.toLowerCase() === lcName) || null;
        }

        /**
         * Render edit control for a field
         */
        renderEditControl(fieldName, fieldDef, value, existingCell) {
            let type = fieldDef.type || 'textbox';
            const required = fieldDef.required ? 'required' : '';
            const maxLength = fieldDef.maxLength || '';

            // Detect boolean fields from multiple indicators
            const isBooleanType = fieldDef.dataType === 'boolean' ||
                                  type === 'checkbox' ||
                                  type === 'switch' ||
                                  type === 'boolean';

            // Also detect if the cell already contains a lib-switch (before editing started)
            const hasExistingSwitch = existingCell && existingCell.querySelector('lib-switch');

            if (isBooleanType || hasExistingSwitch) {
                type = 'checkbox';
            }

            switch (type) {
                case 'text':
                case 'textbox':
                case 'email':
                case 'url':
                    return '<input type="text" name="' + fieldName + '" value="' +
                        escapeHtml(value) + '" ' + required +
                        (maxLength ? ' maxlength="' + maxLength + '"' : '') +
                        ' class="inline-input">';

                case 'checkbox':
                    // Handle various truthy values for booleans
                    const isChecked = value === true || value === 'True' || value === 'true' ||
                                      value === 1 || value === '1' || value === -1 || value === '-1' ||
                                      value === 'J' || value === 'Ja' || value === 'Yes' || value === 'Y';
                    return '<lib-switch name="' + fieldName + '"' + (isChecked ? ' checked' : '') + '></lib-switch>';

                case 'combobox':
                case 'radiogroup': {
                    // Build lib-combo with options
                    let comboHtml = '<lib-combo name="' + escapeHtml(fieldName) + '"' +
                        (value ? ' value="' + escapeHtml(String(value)) + '"' : '') +
                        (required ? ' required' : '') + '>';

                    comboHtml += '<option value=""></option>';
                    const options = this.options.comboOptions[fieldName] || [];
                    for (const opt of options) {
                        const optId = opt.id !== undefined ? opt.id : (opt.value || '');
                        const optText = opt.text !== undefined ? opt.text : (opt.label || optId);
                        const selected = (String(optId) === String(value)) ? ' selected' : '';
                        comboHtml += '<option value="' + escapeHtml(optId) + '"' + selected + '>' +
                            escapeHtml(optText) + '</option>';
                    }
                    comboHtml += '</lib-combo>';

                    // Add plus button if this combobox has an associated form
                    const relatedForm = this.options.addRelatedForms[fieldName];
                    if (relatedForm) {
                        comboHtml = '<span class="input-group">' + comboHtml +
                            '<a href="javascript:void(0)" class="btn-add-related btn-icon" data-field="' +
                            escapeHtml(fieldName) + '" data-form-name="' + escapeHtml(relatedForm) +
                            '" title="Nieuw toevoegen">+</a></span>';
                    }
                    return comboHtml;
                }

                case 'memo':
                    return '<textarea name="' + fieldName + '" class="inline-textarea" rows="2">' +
                        escapeHtml(value) + '</textarea>';

                case 'date':
                case 'datetime':
                    // Convert DD-MM-YYYY to YYYY-MM-DD for lib-datepicker
                    var isoValue = '';
                    if (value && value.length === 10) {
                        var parts = value.split('-');
                        if (parts.length === 3) {
                            isoValue = parts[2] + '-' + parts[1] + '-' + parts[0];
                        }
                    }
                    return '<lib-datepicker name="' + fieldName + '"' +
                        (isoValue ? ' value="' + escapeHtml(isoValue) + '"' : '') +
                        ' format="dd-mm-yyyy" locale="nl"></lib-datepicker>';

                case 'time':
                    // Use lib-timepicker for time fields
                    return '<lib-timepicker name="' + fieldName + '"' +
                        (value ? ' value="' + escapeHtml(value) + '"' : '') +
                        '></lib-timepicker>';

                case 'number':
                case 'integer':
                case 'decimal':
                case 'float':
                case 'money':
                    return '<input type="text" name="' + fieldName + '" value="' +
                        escapeHtml(value) + '" ' + required +
                        ' class="inline-input" data-validation-type="number" maxlength="20" style="text-align:right">';

                default:
                    return '<input type="text" name="' + fieldName + '" value="' +
                        escapeHtml(value) + '" class="inline-input">';
            }
        }

        /**
         * Position edit controls above the editing row
         */
        positionEditControls(row) {
            const rect = row.getBoundingClientRect();
            this.editControls.style.position = 'fixed';
            this.editControls.style.left = rect.left + 'px';
            this.editControls.style.top = (rect.top - 40) + 'px';
            this.editControls.style.zIndex = '1000';
        }

        /**
         * Validate editing row
         */
        validateRow(row) {
            let valid = true;
            let errorMessages = [];
            let missingFields = [];

            // Check required fields
            row.querySelectorAll('[required]').forEach(el => {
                if (!el.value) {
                    el.classList.add('invalid');
                    valid = false;
                    // Get field label
                    const fieldName = this.getFieldLabel(el, row);
                    if (fieldName) {
                        missingFields.push(fieldName);
                    }
                } else {
                    el.classList.remove('invalid');
                }
            });

            if (missingFields.length > 0) {
                errorMessages.push('Vul alle verplichte velden in: ' + missingFields.join(', '));
            }

            // Check number fields
            row.querySelectorAll('[data-validation-type="number"]').forEach(el => {
                const value = el.value.trim();
                if (value !== '' && isNaN(parseFloat(value.replace(',', '.')))) {
                    el.classList.add('invalid');
                    valid = false;
                    const fieldName = el.name || 'Veld';
                    errorMessages.push(`${fieldName} moet een getal zijn`);
                } else {
                    el.classList.remove('invalid');
                }
            });

            // Check date fields
            row.querySelectorAll('[data-validation-type="datum"]').forEach(el => {
                const value = el.value.trim();
                if (value !== '') {
                    // Dutch date format: dd-mm-yyyy
                    const datePattern = /^\d{1,2}[-\/]\d{1,2}[-\/]\d{4}$/;
                    if (!datePattern.test(value)) {
                        el.classList.add('invalid');
                        valid = false;
                        const fieldName = el.name || 'Datum';
                        errorMessages.push(`${fieldName} moet in formaat dd-mm-jjjj zijn`);
                    } else {
                        el.classList.remove('invalid');
                    }
                }
            });

            if (!valid && errorMessages.length > 0) {
                // Show unique error messages
                this.showError([...new Set(errorMessages)].join('. '));
            }

            return valid;
        }

        /**
         * Add new row - opens form.php in popup
         */
        addNewRow() {
            this.openFormPopup(null);
        }

        /**
         * Copy/duplicate row
         */
        copyRow(rowId) {
            this.openFormPopup(rowId, true);
        }

        /**
         * Open form.php in a popup window
         */
        openFormPopup(recordId, copy = false) {
            // CRITICAL FIX: Read jsonForm from table's data-attribute, NOT from closure
            const table = document.querySelector(this.options.tableSelector);
            const jsonFormFromTable = table?.dataset?.jsonForm;
            const jsonFormToUse = jsonFormFromTable || this.options.jsonForm;

            if (!jsonFormToUse) {
                log.error('[openFormPopup] No jsonForm found - tableSelector:', this.options.tableSelector, 'options:', this.options.jsonForm);
                this.showError('Formulier niet gevonden');
                return;
            }

            // Debug: Log if we're using different form than closure
            if (jsonFormFromTable && jsonFormFromTable !== this.options.jsonForm) {
                log.warn('[openFormPopup] Using table data-jsonForm:', jsonFormFromTable, 'instead of closure:', this.options.jsonForm);
            }

            let url = 'form.php?form=' + encodeURIComponent(jsonFormToUse);

            if (recordId) {
                url += '&ID=' + encodeURIComponent(recordId);
                if (copy) {
                    url += '&copy=Y';
                }
            } else {
                url += '&New=Y';
            }

            // Pass filter context to the popup so new records can inherit the filter field value
            // This gets the filter from the parent CmaFormController's searchFilters
            const controller = CMA.FormController?.getController();
            if (controller) {
                const filterFieldName = controller.config?.filterFieldName;
                const filterValue = controller.searchFilters?.[filterFieldName];
                if (filterFieldName && filterValue) {
                    url += '&filterField=' + encodeURIComponent(filterFieldName);
                    url += '&filterValue=' + encodeURIComponent(filterValue);
                }
            }

            // DEBUG: Log URL being opened
            if (typeof window._cmaDebugLog === 'function') {
                window._cmaDebugLog('openFormPopup', {
                    url: url,
                    jsonForm: jsonFormToUse,
                    recordId: recordId,
                    copy: copy,
                    instanceId: this._instanceId || 'unknown'
                });
            }

            // Update browser URL to include record ID (not for copy operations)
            // This allows bookmarking and sharing direct links to records
            // Supports up to 3 levels: /form/x/id/subform/subId/subsubform/subsubId
            const topWindow = window.top || window;
            if (!copy && topWindow.CMA && topWindow.CMA.url) {
                const formNameForUrl = jsonFormToUse;
                const currentState = topWindow.CMA.url.parse();
                const currentDepth = topWindow.CMA.url.getDepth();

                // Determine what level we're at based on sidepanel nesting
                const isInSidepanel = window !== topWindow;

                if (!isInSidepanel) {
                    // Main content area - this is a main form record (level 1)
                    topWindow.CMA.url.update({
                        form: formNameForUrl,
                        recordId: recordId,
                        isNew: !recordId
                    });
                } else if (currentDepth === 1) {
                    // First sidepanel - opening a subform record (level 2)
                    topWindow.CMA.url.update({
                        form: currentState.form,
                        recordId: currentState.recordId,
                        subform: formNameForUrl,
                        subformId: recordId,
                        isSubformNew: !recordId
                    });
                } else if (currentDepth === 2) {
                    // Second sidepanel - opening a sub-subform record (level 3)
                    topWindow.CMA.url.update({
                        form: currentState.form,
                        recordId: currentState.recordId,
                        subform: currentState.subform,
                        subformId: currentState.subformId,
                        subsubform: formNameForUrl,
                        subsubformId: recordId,
                        isSubsubformNew: !recordId
                    });
                }
                // For 4th+ levels, don't update URL (not supported)
            }

            const width = Math.round(window.innerWidth * 0.85);
            const height = Math.round(window.innerHeight * 0.85);
            // Use singular form name with action suffix
            const formName = this.options.formNameSingular || this.options.formName || jsonFormToUse || 'Record';
            let actionSuffix = '';
            if (!recordId) {
                actionSuffix = ' toevoegen';
            } else if (copy) {
                actionSuffix = ' kopiëren';
            } else if (!this.options.canEdit) {
                actionSuffix = ' bekijken';
            } else {
                actionSuffix = ' wijzigen';
            }
            const title = formName + actionSuffix;

            // Check user preference for popup style
            const prefAvailable = typeof lib_getPopupStylePreference === 'function';
            const pref = prefAvailable ? lib_getPopupStylePreference() : 'popup';
            const useSidepanel = pref === 'sidepanel';

            if (useSidepanel && typeof lib_OpenSidePanel === 'function') {
                lib_OpenSidePanel(url, 'form_popup', width, title);
            } else if (typeof lib_OpenWindowCentered === 'function') {
                lib_OpenWindowCentered(url, 'form_popup', width, height, title);
            } else {
                const left = (screen.width - width) / 2;
                const top = (screen.height - height) / 2;
                window.open(url, 'form_popup',
                    'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes');
            }
        }

        /**
         * Delete row
         */
        async deleteRow(rowId) {
            const confirmed = await libConfirm('Weet je zeker dat je dit record wilt verwijderen?', {
                title: 'Record verwijderen',
                confirmText: 'Ja, verwijderen',
                cancelText: 'Nee',
                type: 'danger'
            });
            if (!confirmed) {
                return;
            }

            // CRITICAL FIX: Read jsonForm from table's data-attribute, NOT from closure
            const row = document.getElementById('lt_row_' + rowId) ||
                       document.querySelector('tr[data-id="' + rowId + '"]');
            const table = row?.closest('table') || document.querySelector(this.options.tableSelector);
            const jsonFormFromTable = table?.dataset?.jsonForm;
            const jsonFormToUse = jsonFormFromTable || this.options.jsonForm;

            if (!jsonFormToUse) {
                log.error('[deleteRow] No jsonForm found - tableSelector:', this.options.tableSelector, 'options:', this.options.jsonForm);
                this.showError('Formulier niet gevonden');
                return;
            }

            // Debug: Log if we're using different form than closure
            if (jsonFormFromTable && jsonFormFromTable !== this.options.jsonForm) {
                log.warn('[deleteRow] Using table data-jsonForm:', jsonFormFromTable, 'instead of closure:', this.options.jsonForm);
            }

            const self = this;
            const requestData = new URLSearchParams({
                action: 'delete',
                ID: rowId
            });

            requestData.append('jsonForm', jsonFormToUse);

            try {
                const data = await fetchJson(
                    this.options.apiUrl + '?' + requestData.toString(),
                    {},
                    'deleteRow'
                );

                if (data.success) {
                    self.showSuccess('Verwijderd');
                    const row = document.getElementById('lt_row_' + rowId) ||
                               document.querySelector('tr[data-id="' + rowId + '"]');
                    if (row) {
                        row.style.opacity = '0';
                        row.style.transition = 'opacity 0.3s ease-out';
                        setTimeout(() => row.remove(), 300);
                    }
                } else {
                    log.error('[deleteRow] Server returned error:', data.error);
                    self.showError(data.error || 'Fout bij verwijderen');
                }
            } catch (error) {
                log.error('[deleteRow] Failed:', error.message);
                self.showError(error.message || 'Netwerkfout bij verwijderen');
            }
        }

        /**
         * Get the label for a field element
         */
        getFieldLabel(field, row) {
            // Try data-label attribute first
            if (field.dataset.label) {
                return field.dataset.label;
            }
            // Try to find header label from column index
            const cell = field.closest('td');
            if (cell && row) {
                const cellIndex = cell.cellIndex;
                const table = row.closest('table');
                if (table) {
                    const header = table.querySelector(`thead th:nth-child(${cellIndex + 1})`);
                    if (header) {
                        return header.textContent.replace(/[*:]/g, '').trim();
                    }
                }
            }
            // Fallback to field name or placeholder
            return field.placeholder || field.name || null;
        }

        /**
         * Execute extra button action
         */
        executeExtraButton(index, rowId) {
            const btn = this.options.extraButtons[index];
            if (!btn || !btn.url) return;

            let url = btn.url.replace(/\[id\]/gi, rowId);

            const table = document.querySelector(this.options.tableSelector);
            const row = table.querySelector('tr[data-id="' + rowId + '"]');
            const guid = row ? (row.dataset.guid || rowId) : rowId;
            const guid2 = row ? (row.dataset.guid2 || '') : '';

            url = url.replace(/\[guid\]/gi, guid);
            url = url.replace(/\[guid2\]/gi, guid2);
            url = url.replace(/\[domein\]/gi, window.location.hostname);

            // Match protocol to current page (avoid https on localhost/IP)
            if (window.location.protocol === 'http:') {
                url = url.replace(/^https:\/\//i, 'http://');
            }

            // Handle javascript: URLs - execute as code instead of opening window
            if (url.startsWith('javascript:')) {
                try {
                    const code = url.substring(11);
                    const fn = new Function('recordId', 'guid', 'guid2', code);
                    fn(rowId, guid, guid2);
                } catch (e) {
                    cmaLog.error('[executeExtraButton] JS error:', e);
                }
                return;
            }

            // Open URL in new tab or popup overlay
            if (btn.openInNewWindow) {
                window.open(url, '_blank');
            } else {
                const width = Math.round(window.innerWidth * 0.85);
                const height = Math.round(window.innerHeight * 0.85);

                if (typeof lib_OpenWindowCentered === 'function') {
                    lib_OpenWindowCentered(url, 'extra_action_' + index, width, height, btn.title || 'Extra');
                } else {
                    window.open(url, '_blank');
                }
            }
        }

        /**
         * Execute afterpost URL and show popup if the response contains visible content.
         * Recognizes and skips responses that only contain redirects or window-close scripts.
         */
        async executeAfterPost(recordId) {
            const url = this.options.afterPostUrl.replace('[ID]', recordId);
            try {
                const response = await fetchWithRetry(url, {}, {
                    context: 'executeAfterPost',
                    retries: 3,
                    retryDelay: 1000
                });
                const html = await response.text();

                // Check if the response has visible content worth showing
                if (html && this._afterPostHasContent(html)) {
                    this._showAfterPostPopup(html);
                }
            } catch (error) {
                log.error('[executeAfterPost] Failed after retries:', error.message);
                this.showError('Nabewerking mislukt: ' + error.message);
            }
        }

        /**
         * Check if afterpost HTML has visible content (not just redirects/close scripts).
         * @param {string} html - Response HTML
         * @returns {boolean} true if there is content to show
         */
        _afterPostHasContent(html) {
            // Strip all script tags and their content
            const withoutScripts = html.replace(/<script[\s\S]*?<\/script>/gi, '');
            // Strip all HTML tags
            const textOnly = withoutScripts.replace(/<[^>]*>/g, '').trim();
            // If there's visible text remaining, show it
            return textOnly.length > 0;
        }

        /**
         * Show afterpost response HTML in a centered popup window.
         * @param {string} html - HTML content to display
         */
        _showAfterPostPopup(html) {
            if (typeof lib_OpenWindowCentered === 'function') {
                const win = lib_OpenWindowCentered('about:blank', 'afterpost_result', 800, 600, 'Nabewerking');
                if (win) {
                    win.document.open();
                    win.document.write(html);
                    win.document.close();
                }
            } else {
                const width = 800, height = 600;
                const left = (screen.width - width) / 2;
                const top = (screen.height - height) / 2;
                const win = window.open('about:blank', 'afterpost_result',
                    `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`);
                if (win) {
                    win.document.open();
                    win.document.write(html);
                    win.document.close();
                }
            }
        }

        /**
         * Show success notification
         */
        showSuccess(message) {
            this.showNotification(message, 'success');
        }

        /**
         * Open popup to add a related record (for inline edit combobox plus button)
         * @param {HTMLElement} btn The plus button element
         */
        openAddRelatedPopup(btn) {
            // Guard against double-opens (e.g., from duplicate event handlers)
            if (this._addRelatedOpening) return;
            this._addRelatedOpening = true;
            setTimeout(() => { this._addRelatedOpening = false; }, 500);

            const fieldName = btn.dataset.field;
            const formName = btn.dataset.formName;

            if (!formName || !fieldName) {
                log.warn('Missing data for add related popup', btn.dataset);
                return;
            }

            const self = this;
            const popupUrl = 'form.php?form=' + encodeURIComponent(formName) +
                '&New=Y&updatevalues=' + encodeURIComponent(fieldName);

            // Store callback on form-layout element for popup close handler
            const formLayout = document.querySelector('.form-layout');
            if (formLayout) {
                formLayout._addRelatedCallback = (newRecordId) => {
                    self.refreshInlineComboOptions(fieldName, newRecordId);
                    delete formLayout._addRelatedCallback;
                };
            }

            if (typeof lib_OpenPanel === 'function') {
                lib_OpenPanel(popupUrl, 'addRelated', 800, 600);
            } else if (typeof lib_OpenWindowCentered === 'function') {
                lib_OpenWindowCentered(popupUrl, 'addRelated', 800, 600);
            } else {
                window.open(popupUrl, 'addRelated', 'width=800,height=600');
            }
        }

        /**
         * Refresh inline combobox options after adding a related record
         * @param {string} fieldName Field name of the combobox
         * @param {string|null} newRecordId Optional ID of newly added record to select
         */
        refreshInlineComboOptions(fieldName, newRecordId = null) {
            const self = this;
            const table = document.querySelector(this.options.tableSelector);
            const jsonForm = table?.dataset?.jsonForm || this.options.jsonForm;

            const params = new URLSearchParams();
            params.append('action', 'combo');
            params.append('field', fieldName);
            if (jsonForm) params.append('jsonForm', jsonForm);
            params.append('_t', Date.now());

            fetch('/cma/form_api.php?' + params.toString(), { cache: 'no-store' })
                .then(response => {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                })
                .then(data => {
                    if (data.success && Array.isArray(data.options)) {
                        // Update the in-memory combo options
                        self.options.comboOptions[fieldName] = data.options;
                        // Rebuild lookup map
                        const map = new Map();
                        for (const opt of data.options) {
                            const id = String(opt.id !== undefined ? opt.id : (opt.value || ''));
                            const text = opt.text !== undefined ? opt.text : (opt.label || id);
                            map.set(id, text);
                        }
                        self.comboLookupMaps[fieldName] = map;

                        // Update the lib-combo element in the editing row
                        const editingRow = table?.querySelector('tr.editing');
                        if (editingRow) {
                            const combo = editingRow.querySelector('lib-combo[name="' + fieldName + '"]');
                            if (combo) {
                                // Rebuild options
                                combo.setOptions(data.options.map(opt => ({
                                    value: String(opt.id !== undefined ? opt.id : (opt.value || '')),
                                    label: opt.text !== undefined ? opt.text : (opt.label || String(opt.id || ''))
                                })));

                                // Select the new record
                                if (newRecordId) {
                                    combo.value = String(newRecordId);
                                }
                            }
                        }
                    }
                })
                .catch(err => {
                    log.error('refreshInlineComboOptions failed:', err.message);
                });
        }

        /**
         * Show error notification
         * @param {string} message - Error message
         * @param {string} details - Optional technical details (shown to admins/devs only)
         */
        showError(message, details = '') {
            this.showNotification(message, 'error', details);

            // Report to dev error panel
            if (typeof CmaErrorHandler !== 'undefined') {
                CmaErrorHandler.reportServerError('InlineEdit', message, {
                    url: window.location.href,
                    debug: details || null,
                    form: this.options?.jsonForm
                });
            }
        }

        /**
         * Show HTML response in a popup window
         */
        showHtmlResponsePopup(htmlContent, title) {
            title = title || 'Resultaat';
            const width = 800;
            const height = 600;

            if (typeof lib_OpenWindowCentered === 'function') {
                const win = lib_OpenWindowCentered('about:blank', 'post_result', width, height, title);
                if (win) {
                    win.document.open();
                    win.document.write(htmlContent);
                    win.document.close();
                }
            } else {
                const left = (screen.width - width) / 2;
                const top = (screen.height - height) / 2;
                const win = window.open('about:blank', 'post_result',
                    'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes');
                if (win) {
                    win.document.open();
                    win.document.write(htmlContent);
                    win.document.close();
                }
            }

            this.refreshList();
        }

        /**
         * Show notification using lib-toaster component
         * @param {string} message - Message to display
         * @param {string} type - Notification type (success, error, warning, info)
         * @param {string} details - Optional technical details (currently unused)
         */
        showNotification(message, type, details = '') {
            if (typeof libToast !== 'undefined' && libToast[type]) {
                libToast[type](message);
            } else if (typeof libToast !== 'undefined') {
                libToast.info(message);
            }
        }

        /**
         * Refresh the list (placeholder - can be overridden)
         */
        refreshList() {
            // Override this method if needed
        }

        /**
         * Collect form field values from a row
         */
        collectRowData(row) {
            const data = {};
            // Collect regular form inputs
            row.querySelectorAll('input, select, textarea').forEach(el => {
                const name = el.name;
                if (!name) return;
                if (el.type === 'checkbox') {
                    data[name] = el.checked ? 'True' : 'False';
                } else {
                    data[name] = el.value;
                }
                // console.log('[collectRowData] ' + name + '=' + JSON.stringify(data[name]) + ' (tag=' + el.tagName + ' type=' + el.type + ')');
            });
            // Collect lib-switch values (boolean toggles)
            row.querySelectorAll('lib-switch[name]').forEach(el => {
                const name = el.getAttribute('name');
                if (!name) return;
                data[name] = el.checked ? 'True' : 'False';
            });
            // Collect lib-datepicker values
            row.querySelectorAll('lib-datepicker[name]').forEach(el => {
                const name = el.getAttribute('name');
                if (!name) return;
                // lib-datepicker stores value in ISO format, convert to DD-MM-YYYY for backend
                const isoValue = el.value || '';
                if (isoValue && isoValue.length === 10) {
                    const parts = isoValue.split('-');
                    if (parts.length === 3) {
                        data[name] = parts[2] + '-' + parts[1] + '-' + parts[0]; // DD-MM-YYYY
                    } else {
                        data[name] = isoValue;
                    }
                } else {
                    data[name] = isoValue;
                }
            });
            // Collect lib-timepicker values
            row.querySelectorAll('lib-timepicker[name]').forEach(el => {
                const name = el.getAttribute('name');
                if (!name) return;
                data[name] = el.value || '';
            });
            // Collect lib-combo values
            row.querySelectorAll('lib-combo[name]').forEach(el => {
                const name = el.getAttribute('name');
                if (!name) return;
                data[name] = el.value || '';
            });
            return data;
        }

        /**
         * Escape HTML (instance method for backward compat)
         */
        escapeHtml(str) {
            return escapeHtml(str);
        }
    }

    // Export to global scope
    window.CmaInlineEdit = CmaInlineEdit;

    /**
     * Global lib-switch handler for ALL tables with data-json-form
     *
     * This handler runs independently of CmaInlineEdit to handle switch toggles:
     * 1. Subform tables (class 'subform-table') - always handled here
     * 2. Main form tables when in tree mode (CmaInlineEdit not initialized)
     *
     * CmaInlineEdit handler only processes main table switches when it's active,
     * and explicitly skips subform-table switches to avoid duplicates.
     *
     * Uses the same RequestBatcher for efficiency.
     */
    function initGlobalSwitchHandler() {
        // Prevent double initialization
        if (window._cmaGlobalSwitchHandlerInitialized) return;
        window._cmaGlobalSwitchHandlerInitialized = true;

        // log.log('initGlobalSwitchHandler: Attaching global lib-switch handler');

        document.addEventListener('change', function(e) {
            // Skip if already handled by CmaInlineEdit instance handler
            if (e._cmaInlineEditHandled) {
                return;
            }

            const libSwitch = e.target.closest('lib-switch');
            if (!libSwitch) return;

            const row = libSwitch.closest('tr');
            if (!row) return;

            // Find the table with data-json-form attribute
            const table = libSwitch.closest('table[data-json-form]');
            if (!table) {
                // Not in a form table context (e.g., preferences page) - silently skip
                return;
            }

            const jsonFormName = table.dataset.jsonForm;
            if (!jsonFormName) {
                // Table exists but no form configured - skip
                return;
            }

            // Get row ID from data attribute
            let rowId = row.dataset.id;
            if (!rowId && row.id && row.id.startsWith('lt_row_')) {
                rowId = row.id.replace('lt_row_', '');
            }

            const fieldName = libSwitch.getAttribute('data-field');
            const td = libSwitch.closest('td');
            const newValue = libSwitch.checked;

            if (!rowId || !fieldName) {
                log.warn('initGlobalSwitchHandler: Missing rowId or fieldName', { rowId, fieldName });
                return;
            }

            // log.log('initGlobalSwitchHandler: Switch toggled', {
            //     jsonForm: jsonFormName,
            //     rowId: rowId,
            //     field: fieldName,
            //     value: newValue,
            //     isSubform: table.classList.contains('subform-table')
            // });

            // Show saving state
            libSwitch.setSaving(true);
            if (td) td.dataset.value = newValue ? '1' : '0';

            // Use batcher to combine multiple switch toggles
            const batcher = getRequestBatcher('/cma/form_api.php', jsonFormName);
            batcher.queue(rowId, fieldName, newValue ? 'True' : 'False', jsonFormName, {
                onSuccess: () => {
                    libSwitch.setSaving(false);
                    // log.log('initGlobalSwitchHandler: Save succeeded', { rowId, fieldName });
                    // Mark the column filter as stale so it rebuilds with new value on next open
                    if (td && typeof jQuery !== 'undefined' && typeof jQuery.fn.excelTableFilterMarkColumnStale === 'function') {
                        const columnIndex = td.cellIndex;
                        jQuery(table).excelTableFilterMarkColumnStale(columnIndex);
                    }
                    // Show confirmation toaster - use libSwitch as dedup key to prevent multiple toasts
                    if (typeof libToast !== 'undefined' && !libSwitch._toastShown) {
                        libSwitch._toastShown = true;
                        libToast.success('Opgeslagen');
                        // Reset after a short delay
                        setTimeout(() => { libSwitch._toastShown = false; }, 500);
                    }
                },
                onError: (error) => {
                    libSwitch.setSaving(false);
                    // Revert on error
                    libSwitch.checked = !newValue;
                    if (td) td.dataset.value = !newValue ? '1' : '0';
                    log.error('initGlobalSwitchHandler: Save failed', { rowId, fieldName, error });
                    // Show error message
                    if (typeof lib === 'object' && lib.message) {
                        lib.message(error || 'Fout bij opslaan', 'error');
                    }
                }
            });
        });
    }

    // Initialize global switch handler when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGlobalSwitchHandler);
    } else {
        initGlobalSwitchHandler();
    }

})(window);
