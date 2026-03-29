/**
 * cma-checklist Web Component
 *
 * A multi-select checkbox list with groups and tree structure.
 *
 * Usage:
 *   <cma-checklist name="categories" value="1,3,5">
 *     <optgroup label="Group 1">
 *       <option value="1">Item 1</option>
 *       <option value="2">Item 2</option>
 *     </optgroup>
 *     <option value="3">Item 3</option>
 *   </cma-checklist>
 *
 * Attributes:
 *   - name: Form field name
 *   - value: Comma-separated selected values
 *   - disabled: Disable the component
 *   - searchable: Show search box
 *   - columns: Number of columns (1-4, default: 1)
 *   - max-height: Maximum height in px (default: 300)
 *
 * Events:
 *   - change: Fired when selection changes
 */
class CmaChecklist extends HTMLElement {
    static get observedAttributes() {
        return ['value', 'disabled', 'columns'];
    }

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._items = [];
        this._groups = [];
        this._selectedValues = new Set();
    }

    connectedCallback() {
        // Adopt shared styles if available
        if (typeof LibSharedStyles !== 'undefined' && LibSharedStyles.isSupported()) {
            LibSharedStyles.adopt(this.shadowRoot, 'base', 'input');
        }

        this._collectItems();
        this.render();
        this._setupEventListeners();

        // Set initial value
        const initialValue = this.getAttribute('value');
        if (initialValue) {
            this._setValuesFromString(initialValue);
        }
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;

        switch (name) {
            case 'value':
                this._setValuesFromString(newValue);
                break;
            case 'disabled':
                this._updateDisabledState();
                break;
            case 'columns':
                this._updateColumns();
                break;
        }
    }

    get value() {
        return Array.from(this._selectedValues);
    }

    set value(val) {
        if (Array.isArray(val)) {
            this._selectedValues = new Set(val.map(String));
        } else if (typeof val === 'string') {
            this._setValuesFromString(val);
            return;
        } else {
            this._selectedValues = new Set();
        }
        this._updateCheckboxes();
        this._updateHiddenInput();
    }

    get selectedItems() {
        return this._items.filter(item => this._selectedValues.has(item.value));
    }

    _collectItems() {
        this._items = [];
        this._groups = [];

        // Process optgroups and direct options
        const children = this.children;
        let currentGroup = null;

        for (const child of children) {
            if (child.tagName === 'OPTGROUP') {
                currentGroup = {
                    label: child.label || 'Groep',
                    items: []
                };
                this._groups.push(currentGroup);

                for (const opt of child.querySelectorAll('option')) {
                    const item = {
                        value: opt.value,
                        label: opt.textContent,
                        disabled: opt.disabled,
                        group: currentGroup.label
                    };
                    this._items.push(item);
                    currentGroup.items.push(item);
                }
            } else if (child.tagName === 'OPTION') {
                const item = {
                    value: child.value,
                    label: child.textContent,
                    disabled: child.disabled,
                    group: null
                };
                this._items.push(item);
            }
        }
    }

    render() {
        const columns = parseInt(this.getAttribute('columns') || '1', 10);
        const maxHeight = this.getAttribute('max-height') || '300';
        const isSearchable = this.hasAttribute('searchable');
        const isDisabled = this.hasAttribute('disabled');
        const name = this.getAttribute('name') || '';

        this.shadowRoot.innerHTML = `
            <style>
                :host {
                    display: block;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    font-size: 14px;
                }

                * {
                    box-sizing: border-box;
                }

                .checklist-container {
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    background-color: #fff;
                    overflow: hidden;
                }

                .checklist-container.disabled {
                    opacity: 0.6;
                    pointer-events: none;
                }

                .checklist-toolbar {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 8px 12px;
                    background-color: #f8f9fa;
                    border-bottom: 1px solid #eee;
                }

                .checklist-search {
                    flex: 1;
                }

                .checklist-search input {
                    width: 100%;
                    padding: 6px 10px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-size: 13px;
                    outline: none;
                }

                .checklist-search input:focus {
                    border-color: #204496;
                }

                .checklist-actions {
                    display: flex;
                    gap: 4px;
                }

                .checklist-action {
                    padding: 4px 10px;
                    background: none;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 12px;
                    color: #666;
                    white-space: nowrap;
                }

                .checklist-action:hover {
                    background-color: #e9ecef;
                    color: #333;
                }

                .checklist-body {
                    max-height: ${maxHeight}px;
                    overflow-y: auto;
                    padding: 8px;
                }

                .checklist-items {
                    display: grid;
                    grid-template-columns: repeat(${Math.min(Math.max(columns, 1), 4)}, 1fr);
                    gap: 4px 16px;
                }

                .checklist-group {
                    grid-column: 1 / -1;
                    margin-top: 8px;
                }

                .checklist-group:first-child {
                    margin-top: 0;
                }

                .checklist-group-label {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 6px 8px;
                    background-color: #f0f4f8;
                    border-radius: 4px;
                    font-weight: 600;
                    font-size: 13px;
                    color: #1a365d;
                    cursor: pointer;
                    user-select: none;
                }

                .checklist-group-label:hover {
                    background-color: #e3ecf6;
                }

                .checklist-group-toggle {
                    width: 16px;
                    height: 16px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 10px;
                    transition: transform 0.15s ease;
                }

                .checklist-group.collapsed .checklist-group-toggle {
                    transform: rotate(-90deg);
                }

                .checklist-group-items {
                    display: grid;
                    grid-template-columns: repeat(${Math.min(Math.max(columns, 1), 4)}, 1fr);
                    gap: 4px 16px;
                    padding: 8px 0 0 24px;
                }

                .checklist-group.collapsed .checklist-group-items {
                    display: none;
                }

                .checklist-item {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 6px 8px;
                    border-radius: 4px;
                    cursor: pointer;
                    transition: background-color 0.1s ease;
                }

                .checklist-item:hover {
                    background-color: #f0f4f8;
                }

                .checklist-item.disabled {
                    opacity: 0.5;
                    cursor: not-allowed;
                }

                .checklist-item.hidden {
                    display: none;
                }

                .checklist-checkbox {
                    width: 18px;
                    height: 18px;
                    border: 2px solid #ccc;
                    border-radius: 3px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                    transition: all 0.15s ease;
                }

                .checklist-item.checked .checklist-checkbox {
                    background-color: #204496;
                    border-color: #204496;
                }

                .checklist-checkbox::after {
                    content: '';
                    width: 5px;
                    height: 9px;
                    border: solid #fff;
                    border-width: 0 2px 2px 0;
                    transform: rotate(45deg) translateY(-1px);
                    opacity: 0;
                    transition: opacity 0.1s ease;
                }

                .checklist-item.checked .checklist-checkbox::after {
                    opacity: 1;
                }

                .checklist-label {
                    flex: 1;
                    line-height: 1.3;
                }

                .checklist-count {
                    padding: 8px 12px;
                    background-color: #f8f9fa;
                    border-top: 1px solid #eee;
                    font-size: 12px;
                    color: #666;
                    text-align: right;
                }

                .checklist-empty {
                    padding: 20px;
                    text-align: center;
                    color: #999;
                }

                /* Search highlight */
                .checklist-label mark {
                    background-color: #fff3cd;
                    padding: 0 2px;
                    border-radius: 2px;
                }
            </style>
            <div class="checklist-container ${isDisabled ? 'disabled' : ''}">
                <div class="checklist-toolbar">
                    ${isSearchable ? `
                        <div class="checklist-search">
                            <input type="text" placeholder="Zoeken..." autocomplete="off">
                        </div>
                    ` : ''}
                    <div class="checklist-actions">
                        <button type="button" class="checklist-action" data-action="select-all">Alles</button>
                        <button type="button" class="checklist-action" data-action="deselect-all">Niets</button>
                    </div>
                </div>
                <div class="checklist-body">
                    ${this._renderItems()}
                </div>
                <div class="checklist-count">
                    <span class="count-selected">0</span> / <span class="count-total">${this._items.length}</span> geselecteerd
                </div>
                <input type="hidden" name="${name}">
            </div>
        `;

        this._updateCount();
    }

    _renderItems() {
        if (this._items.length === 0) {
            return '<div class="checklist-empty">Geen items</div>';
        }

        // Render grouped items first
        let html = '';

        if (this._groups.length > 0) {
            for (const group of this._groups) {
                html += `
                    <div class="checklist-group" data-group="${this._escapeHtml(group.label)}">
                        <div class="checklist-group-label">
                            <span class="checklist-group-toggle">&#9660;</span>
                            <span>${this._escapeHtml(group.label)}</span>
                        </div>
                        <div class="checklist-group-items">
                            ${group.items.map(item => this._renderItem(item)).join('')}
                        </div>
                    </div>
                `;
            }
        }

        // Render ungrouped items
        const ungrouped = this._items.filter(item => !item.group);
        if (ungrouped.length > 0) {
            html += `<div class="checklist-items">`;
            for (const item of ungrouped) {
                html += this._renderItem(item);
            }
            html += '</div>';
        }

        return html || '<div class="checklist-items"></div>';
    }

    _renderItem(item) {
        const isChecked = this._selectedValues.has(item.value);
        const classes = ['checklist-item'];
        if (isChecked) classes.push('checked');
        if (item.disabled) classes.push('disabled');

        return `
            <div class="${classes.join(' ')}" data-value="${this._escapeHtml(item.value)}">
                <span class="checklist-checkbox"></span>
                <span class="checklist-label">${this._escapeHtml(item.label)}</span>
            </div>
        `;
    }

    _setupEventListeners() {
        const container = this.shadowRoot.querySelector('.checklist-container');

        // Item click
        container.addEventListener('click', (e) => {
            // Group toggle
            const groupLabel = e.target.closest('.checklist-group-label');
            if (groupLabel) {
                const group = groupLabel.closest('.checklist-group');
                group.classList.toggle('collapsed');
                return;
            }

            // Item toggle
            const item = e.target.closest('.checklist-item');
            if (item && !item.classList.contains('disabled')) {
                this._toggleItem(item.dataset.value);
            }
        });

        // Actions
        container.querySelectorAll('.checklist-action').forEach(btn => {
            btn.addEventListener('click', () => {
                const action = btn.dataset.action;
                if (action === 'select-all') {
                    this._selectAll();
                } else if (action === 'deselect-all') {
                    this._deselectAll();
                }
            });
        });

        // Search
        const searchInput = container.querySelector('.checklist-search input');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this._filter(e.target.value);
            });
        }
    }

    _toggleItem(value) {
        if (this._selectedValues.has(value)) {
            this._selectedValues.delete(value);
        } else {
            this._selectedValues.add(value);
        }

        this._updateCheckboxes();
        this._updateHiddenInput();
        this._updateCount();
        this._dispatchChange();
    }

    _selectAll() {
        const visibleItems = this.shadowRoot.querySelectorAll('.checklist-item:not(.hidden):not(.disabled)');
        visibleItems.forEach(item => {
            this._selectedValues.add(item.dataset.value);
        });

        this._updateCheckboxes();
        this._updateHiddenInput();
        this._updateCount();
        this._dispatchChange();
    }

    _deselectAll() {
        const visibleItems = this.shadowRoot.querySelectorAll('.checklist-item:not(.hidden):not(.disabled)');
        visibleItems.forEach(item => {
            this._selectedValues.delete(item.dataset.value);
        });

        this._updateCheckboxes();
        this._updateHiddenInput();
        this._updateCount();
        this._dispatchChange();
    }

    _filter(term) {
        const filterLower = term.toLowerCase().trim();
        const items = this.shadowRoot.querySelectorAll('.checklist-item');

        items.forEach(item => {
            const label = item.querySelector('.checklist-label');
            const originalText = this._items.find(i => i.value === item.dataset.value)?.label || '';

            if (!filterLower) {
                item.classList.remove('hidden');
                label.innerHTML = this._escapeHtml(originalText);
            } else if (originalText.toLowerCase().includes(filterLower)) {
                item.classList.remove('hidden');
                const regex = new RegExp(`(${this._escapeRegex(term)})`, 'gi');
                label.innerHTML = this._escapeHtml(originalText).replace(regex, '<mark>$1</mark>');
            } else {
                item.classList.add('hidden');
            }
        });

        // Hide empty groups
        this.shadowRoot.querySelectorAll('.checklist-group').forEach(group => {
            const visibleItems = group.querySelectorAll('.checklist-item:not(.hidden)');
            group.style.display = visibleItems.length === 0 ? 'none' : '';
        });
    }

    _updateCheckboxes() {
        this.shadowRoot.querySelectorAll('.checklist-item').forEach(item => {
            const isSelected = this._selectedValues.has(item.dataset.value);
            item.classList.toggle('checked', isSelected);
        });
    }

    _updateHiddenInput() {
        const input = this.shadowRoot.querySelector('input[type="hidden"]');
        if (input) {
            input.value = Array.from(this._selectedValues).join(',');
        }
    }

    _updateCount() {
        const countEl = this.shadowRoot.querySelector('.count-selected');
        if (countEl) {
            countEl.textContent = this._selectedValues.size;
        }
    }

    _setValuesFromString(str) {
        if (!str) {
            this._selectedValues = new Set();
        } else {
            this._selectedValues = new Set(str.split(',').map(v => v.trim()).filter(v => v));
        }
        this._updateCheckboxes();
        this._updateHiddenInput();
        this._updateCount();
    }

    _updateDisabledState() {
        const container = this.shadowRoot.querySelector('.checklist-container');
        if (container) {
            container.classList.toggle('disabled', this.hasAttribute('disabled'));
        }
    }

    _updateColumns() {
        // Re-render to update grid columns
        if (this.shadowRoot.innerHTML) {
            this.render();
            this._setupEventListeners();
        }
    }

    _dispatchChange() {
        this.dispatchEvent(new CustomEvent('change', {
            detail: {
                value: this.value,
                selectedItems: this.selectedItems
            },
            bubbles: true
        }));
    }

    /**
     * Add item programmatically
     */
    addItem(value, label, group = null) {
        const item = { value: String(value), label, disabled: false, group };
        this._items.push(item);

        if (group) {
            let existingGroup = this._groups.find(g => g.label === group);
            if (!existingGroup) {
                existingGroup = { label: group, items: [] };
                this._groups.push(existingGroup);
            }
            existingGroup.items.push(item);
        }

        this.render();
        this._setupEventListeners();
    }

    /**
     * Remove item
     */
    removeItem(value) {
        this._items = this._items.filter(i => i.value !== String(value));
        this._groups.forEach(g => {
            g.items = g.items.filter(i => i.value !== String(value));
        });
        this._groups = this._groups.filter(g => g.items.length > 0);
        this._selectedValues.delete(String(value));

        this.render();
        this._setupEventListeners();
    }

    /**
     * Set items from array
     */
    setItems(items) {
        this._items = items.map(i => ({
            value: String(i.value),
            label: i.label || String(i.value),
            disabled: i.disabled || false,
            group: i.group || null
        }));

        // Rebuild groups
        this._groups = [];
        const groupMap = new Map();

        for (const item of this._items) {
            if (item.group) {
                if (!groupMap.has(item.group)) {
                    const group = { label: item.group, items: [] };
                    groupMap.set(item.group, group);
                    this._groups.push(group);
                }
                groupMap.get(item.group).items.push(item);
            }
        }

        this.render();
        this._setupEventListeners();
    }

    _escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    _escapeRegex(text) {
        return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
}

// Register the component
customElements.define('cma-checklist', CmaChecklist);
