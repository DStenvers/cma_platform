/**
 * CMA URL Manager
 * Handles clean URL generation and parsing for the form system
 *
 * Clean URL format (supports up to 3 levels of nesting):
 * - /cma/form/formname                                        → List view
 * - /cma/form/formname/recordId                               → Detail view (sidepanel)
 * - /cma/form/formname/new                                    → New record (sidepanel)
 * - /cma/form/formname/recordId/subform                       → Subform list
 * - /cma/form/formname/recordId/subform/subId                 → Subform detail
 * - /cma/form/formname/recordId/subform/new                   → New subform record
 * - /cma/form/formname/recordId/subform/subId/subsubform      → Sub-subform list
 * - /cma/form/formname/recordId/subform/subId/subsubform/subsubId → Sub-subform detail
 * - /cma/form/formname/recordId/subform/subId/subsubform/new  → New sub-subform record
 */
(function() {
    'use strict';

    const CMA_BASE = '/cma';

    /**
     * Parse current URL into form state object
     * @returns {Object} State object with form, recordId, subform, subformId, subsubform, subsubformId
     */
    function parseUrl() {
        const path = window.location.pathname;
        const state = {
            form: null,
            recordId: null,
            subform: null,
            subformId: null,
            subsubform: null,
            subsubformId: null,
            isNew: false,
            isSubformNew: false,
            isSubsubformNew: false
        };

        // Try clean URL format: /cma/form/...
        // Matches up to 6 segments after /cma/form/
        const cleanMatch = path.match(/^\/cma\/form\/([^\/]+)(?:\/([^\/]+))?(?:\/([^\/]+))?(?:\/([^\/]+))?(?:\/([^\/]+))?(?:\/([^\/]+))?/);
        if (cleanMatch) {
            state.form = cleanMatch[1];

            // Level 1: recordId or 'new'
            if (cleanMatch[2]) {
                if (cleanMatch[2].toLowerCase() === 'new') {
                    state.isNew = true;
                } else {
                    state.recordId = cleanMatch[2];
                }
            }

            // Level 2: subform name
            if (cleanMatch[3]) {
                state.subform = cleanMatch[3];
            }

            // Level 2: subformId or 'new'
            if (cleanMatch[4]) {
                if (cleanMatch[4].toLowerCase() === 'new') {
                    state.isSubformNew = true;
                } else {
                    state.subformId = cleanMatch[4];
                }
            }

            // Level 3: subsubform name
            if (cleanMatch[5]) {
                state.subsubform = cleanMatch[5];
            }

            // Level 3: subsubformId or 'new'
            if (cleanMatch[6]) {
                if (cleanMatch[6].toLowerCase() === 'new') {
                    state.isSubsubformNew = true;
                } else {
                    state.subsubformId = cleanMatch[6];
                }
            }

            return state;
        }

        // Fall back to query parameter format
        const params = new URLSearchParams(window.location.search);
        const page = params.get('page') || '';

        // Extract form from page parameter
        const formMatch = page.match(/form=([^&]+)/);
        if (formMatch) {
            state.form = decodeURIComponent(formMatch[1]);
        }

        // Get record ID
        const formID = params.get('formID');
        if (formID) {
            if (formID.toLowerCase() === 'new') {
                state.isNew = true;
            } else {
                state.recordId = formID;
            }
        }

        // Get popup/subform info
        const popup = params.get('popup');
        if (popup) {
            state.subform = popup;
            const popupID = params.get('popupID');
            if (popupID) {
                if (popupID.toLowerCase() === 'new') {
                    state.isSubformNew = true;
                } else {
                    state.subformId = popupID;
                }
            }
        }

        return state;
    }

    /**
     * Build a clean URL from form state
     * @param {Object} state - State object with form, recordId, subform, subformId, subsubform, subsubformId
     * @returns {string} Clean URL path
     */
    function buildUrl(state) {
        if (!state.form) return CMA_BASE + '/';

        // Convert form name to lowercase for cleaner URLs
        let url = CMA_BASE + '/form/' + encodeURIComponent(state.form.toLowerCase());

        if (state.isNew) {
            url += '/new';
        } else if (state.recordId) {
            url += '/' + encodeURIComponent(state.recordId);

            if (state.subform) {
                url += '/' + encodeURIComponent(state.subform.toLowerCase());

                if (state.isSubformNew) {
                    url += '/new';
                } else if (state.subformId) {
                    url += '/' + encodeURIComponent(state.subformId);

                    // Level 3: sub-subform
                    if (state.subsubform) {
                        url += '/' + encodeURIComponent(state.subsubform.toLowerCase());

                        if (state.isSubsubformNew) {
                            url += '/new';
                        } else if (state.subsubformId) {
                            url += '/' + encodeURIComponent(state.subsubformId);
                        }
                    }
                }
            }
        }

        return url;
    }

    /**
     * Update browser URL without page reload
     * @param {Object} state - State object
     * @param {boolean} replace - If true, use replaceState instead of pushState
     */
    function updateUrl(state, replace) {
        const url = buildUrl(state);
        const historyState = { cmaState: state };

        if (replace) {
            history.replaceState(historyState, '', url);
        } else {
            history.pushState(historyState, '', url);
        }

        // cmaLog.log('[url-manager] Updated URL:', url, 'state:', state);
    }

    /**
     * Navigate to a form list view
     * @param {string} form - Form name
     */
    function navigateToForm(form) {
        updateUrl({ form: form }, false);
    }

    /**
     * Navigate to a specific record
     * @param {string} form - Form name
     * @param {string|null} recordId - Record ID or null for new
     */
    function navigateToRecord(form, recordId) {
        const state = {
            form: form,
            recordId: recordId,
            isNew: !recordId || recordId === 'new'
        };
        updateUrl(state, false);
    }

    /**
     * Navigate to a subform
     * @param {string} form - Parent form name
     * @param {string} recordId - Parent record ID
     * @param {string} subform - Subform name
     * @param {string|null} subformId - Subform record ID or null for list/new
     */
    function navigateToSubform(form, recordId, subform, subformId) {
        const state = {
            form: form,
            recordId: recordId,
            subform: subform,
            subformId: subformId,
            isSubformNew: !subformId || subformId === 'new'
        };
        updateUrl(state, false);
    }

    /**
     * Update URL when opening a sidepanel (without navigation)
     * Uses replaceState to not add to history
     * @param {string} form - Form name
     * @param {string|null} recordId - Record ID
     * @param {string|null} subform - Subform name (optional)
     * @param {string|null} subformId - Subform record ID (optional)
     */
    function setSidepanelState(form, recordId, subform, subformId) {
        // Get current state and update it
        const currentState = parseUrl();

        if (subform) {
            // Opening a subform sidepanel
            currentState.subform = subform;
            currentState.subformId = subformId;
            currentState.isSubformNew = !subformId || subformId === 'new';
        } else {
            // Opening a main form sidepanel
            currentState.form = form;
            currentState.recordId = recordId;
            currentState.isNew = !recordId || recordId === 'new';
        }

        updateUrl(currentState, true);
    }

    /**
     * Clear sidepanel state from URL
     * @param {number} level - Level to clear: 1=main record, 2=subform, 3=subsubform
     */
    function clearSidepanelState(level) {
        const currentState = parseUrl();

        if (level === 3 || level === 'subsubform') {
            // Clear only sub-subform state (back to subform detail)
            currentState.subsubform = null;
            currentState.subsubformId = null;
            currentState.isSubsubformNew = false;
        } else if (level === 2 || level === 'subform' || level === true) {
            // Clear subform state (back to main record detail)
            currentState.subform = null;
            currentState.subformId = null;
            currentState.isSubformNew = false;
            currentState.subsubform = null;
            currentState.subsubformId = null;
            currentState.isSubsubformNew = false;
        } else {
            // Clear main record state (back to list view)
            currentState.recordId = null;
            currentState.isNew = false;
            currentState.subform = null;
            currentState.subformId = null;
            currentState.isSubformNew = false;
            currentState.subsubform = null;
            currentState.subsubformId = null;
            currentState.isSubsubformNew = false;
        }

        updateUrl(currentState, true);
    }

    /**
     * Get the current nesting depth
     * @returns {number} 0=list, 1=record, 2=subform, 3=subsubform
     */
    function getDepth() {
        const state = parseUrl();
        if (state.subsubformId || state.isSubsubformNew) return 3;
        if (state.subformId || state.isSubformNew) return 2;
        if (state.recordId || state.isNew) return 1;
        return 0;
    }

    /**
     * Check if current URL is a clean URL format
     * @returns {boolean}
     */
    function isCleanUrl() {
        return window.location.pathname.startsWith(CMA_BASE + '/form/');
    }

    /**
     * Convert legacy query URL to clean URL format (redirect)
     * Called on page load to normalize URLs
     */
    function normalizeUrl() {
        // Skip if already clean URL or no form parameter
        if (isCleanUrl()) return;

        const state = parseUrl();
        if (!state.form) return;

        // Replace current URL with clean format
        updateUrl(state, true);
    }

    // Export to global CMA namespace
    window.CMA = window.CMA || {};
    window.CMA.url = {
        parse: parseUrl,
        build: buildUrl,
        update: updateUrl,
        navigateToForm: navigateToForm,
        navigateToRecord: navigateToRecord,
        navigateToSubform: navigateToSubform,
        setSidepanelState: setSidepanelState,
        clearSidepanelState: clearSidepanelState,
        getDepth: getDepth,
        isCleanUrl: isCleanUrl,
        normalize: normalizeUrl
    };

})();
