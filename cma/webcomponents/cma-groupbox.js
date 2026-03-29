/**
 * cma-groupbox Web Component
 *
 * A collapsible group header. Works in two modes:
 *
 * 1. Form mode (legacy): Toggles sibling rows by group-id/form-id
 *    <cma-groupbox group-id="1" form-id="123" caption="Personal Details"></cma-groupbox>
 *
 * 2. Standalone mode: Toggles the next sibling element
 *    <cma-groupbox caption="Section Title" count="5"></cma-groupbox>
 *    <div>Content that collapses</div>
 *
 * Attributes:
 *   - caption: Group title text
 *   - count: Optional count badge shown after the title
 *   - collapsed: Initial collapsed state (presence = collapsed)
 *   - storage-key: localStorage key for persisting state (auto-generated from group-id/form-id if not set)
 *   - group-id: (Form mode) Unique group ID within the form
 *   - form-id: (Form mode) Form ID for storage key
 *
 * Properties:
 *   - isOpen: Get/set the open state
 *
 * Methods:
 *   - toggle(): Toggle open/closed
 *   - open(): Open the group
 *   - close(): Close the group
 *
 * Events:
 *   - groupbox-toggle: Fired when state changes, detail: { open: boolean }
 */
// Guard against double registration
if (!customElements.get('cma-groupbox')) {

class CmaGroupbox extends HTMLElement {
    static get observedAttributes() {
        return ['collapsed', 'caption', 'count'];
    }

    constructor() {
        super();
        this._isOpen = true;
        this._groupId = 0;
        this._formId = 0;
        this._caption = '';
        this._count = '';
        this._clickHandlerBound = false;
        this._isFormMode = false;
    }

    get groupId() { return this._groupId; }
    get formId() { return this._formId; }
    get caption() { return this._caption; }

    get isOpen() { return this._isOpen; }
    set isOpen(value) {
        this._isOpen = !!value;
        this._applyState();
        this._saveState();
        this.dispatchEvent(new CustomEvent('groupbox-toggle', { detail: { open: this._isOpen }, bubbles: true }));
    }

    connectedCallback() {
        this._groupId = parseInt(this.getAttribute('group-id') || '0', 10);
        this._formId = parseInt(this.getAttribute('form-id') || '0', 10);
        this._caption = this.getAttribute('caption') || '';
        this._count = this.getAttribute('count') || '';
        this._isFormMode = this._groupId > 0;

        // Determine storage key
        var storageKey = this.getAttribute('storage-key');
        if (storageKey) {
            this._storageKey = storageKey;
        } else if (this._isFormMode) {
            this._storageKey = 'cma_grp_' + this._formId + '_' + this._groupId;
        } else {
            // Auto-generate from caption
            this._storageKey = 'cma_grp_' + this._caption.replace(/[^a-z0-9]/gi, '_').toLowerCase();
        }

        // Check initial state from attribute or storage
        var storedState = localStorage.getItem(this._storageKey);
        if (storedState !== null) {
            this._isOpen = storedState !== 'closed';
        } else {
            this._isOpen = !this.hasAttribute('collapsed');
        }

        this._render();
        this.classList.toggle('group_open', this._isOpen);
        this.classList.toggle('group_closed', !this._isOpen);

        if (this._isFormMode) {
            // Form mode: defer row visibility until DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this._applyRowVisibility(), { once: true });
            } else {
                requestAnimationFrame(() => this._applyRowVisibility());
            }
        } else {
            // Standalone mode: toggle next sibling
            requestAnimationFrame(() => this._applyStandaloneVisibility());
        }

        if (!this._clickHandlerBound) {
            this._clickHandlerBound = true;
            this.addEventListener('click', () => this.toggle());
        }
    }

    toggle() { this.isOpen = !this._isOpen; }
    open() { this.isOpen = true; }
    close() { this.isOpen = false; }

    _render() {
        var countHtml = '';
        if (this._count) {
            countHtml = ' <span class="groupbox-count">' + this._escapeHtml(this._count) + '</span>';
        }
        this.innerHTML =
            '<span class="groupbox-title">' + this._escapeHtml(this._caption) + countHtml + '</span>' +
            '<span class="groupbox-chevron"></span>';
    }

    _applyState() {
        this.classList.toggle('group_open', this._isOpen);
        this.classList.toggle('group_closed', !this._isOpen);

        if (this._isFormMode) {
            this._applyRowVisibility();
        } else {
            this._applyStandaloneVisibility();
        }
    }

    _applyRowVisibility() {
        var prefix = '_g' + this._groupId + '_';
        var index = 1;
        var row = document.getElementById(prefix + index);
        while (row) {
            row.style.display = this._isOpen ? '' : 'none';
            index++;
            row = document.getElementById(prefix + index);
        }
        var groupRows = document.querySelectorAll('[data-group-row="' + this._groupId + '"]');
        groupRows.forEach(row => {
            row.style.display = this._isOpen ? '' : 'none';
        });
    }

    _applyStandaloneVisibility() {
        // Toggle the next sibling element
        var next = this.nextElementSibling;
        if (next && !next.matches('cma-groupbox')) {
            next.style.display = this._isOpen ? '' : 'none';
        }
    }

    _saveState() {
        localStorage.setItem(this._storageKey, this._isOpen ? 'open' : 'closed');
    }

    _escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;
        if (name === 'collapsed') {
            this._isOpen = newValue === null;
            this._applyState();
        } else if (name === 'caption') {
            this._caption = newValue || '';
            this._render();
        } else if (name === 'count') {
            this._count = newValue || '';
            this._render();
        }
    }
}

customElements.define('cma-groupbox', CmaGroupbox);

} // end guard

// Backward compatibility - expose functions globally but delegate to components
window.grp_flip = function(groupId, formId) {
    const groupbox = document.querySelector('cma-groupbox[group-id="' + groupId + '"]');
    if (groupbox) {
        groupbox.toggle();
    } else {
        CMA.groups.flip(groupId, formId);
    }
};

window.grp_set = function(groupId, display) {
    const groupbox = document.querySelector('cma-groupbox[group-id="' + groupId + '"]');
    if (groupbox) {
        groupbox.isOpen = display !== 'none';
    } else {
        CMA.groups.set(groupId, display);
    }
};

window.grp_init = function(formId) {
    const hasComponents = document.querySelector('cma-groupbox');
    if (!hasComponents) {
        CMA.groups.init(formId);
    }
};
