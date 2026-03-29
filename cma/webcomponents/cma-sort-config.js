/**
 * cma-sort-config Web Component
 *
 * Sorting configuration component for the Query Designer.
 * Allows adding fields with sort direction and reordering via drag-and-drop.
 * No Shadow DOM - allows Select2 integration.
 *
 * Usage:
 *   <cma-sort-config id="sortConfig"></cma-sort-config>
 *
 * Attributes:
 *   - readonly: Make the component read-only
 *
 * Events:
 *   - change: Fired when sort configuration changes
 *   - sort-add: Fired when a sort field is added
 *   - sort-remove: Fired when a sort field is removed
 *
 * Methods:
 *   - setAvailableFields(fields): Set the available fields for sorting
 *   - setSorting(sorting): Set the current sort configuration
 *   - getSorting(): Get current sort configuration
 *   - addSort(field): Add a field to sort by
 *   - removeSort(index): Remove a sort field
 *   - initSelect2(): Initialize Select2 on the field dropdown
 */

// Guard against double registration
if (!customElements.get('cma-sort-config')) {

class CmaSortConfig extends HTMLElement {
    static get observedAttributes() {
        return ['readonly'];
    }

    constructor() {
        super();
        // No shadow DOM - inherit styles from page, allows Select2 integration

        // State
        this._availableFields = [];
        this._sorting = [];
        this._draggedIndex = null;
        this._placeholder = null;
        this._$select2 = null;
    }

    connectedCallback() {
        this._render();
        this._setupEventListeners();
    }

    disconnectedCallback() {
        // Cleanup Select2
        if (this._$select2) {
            try {
                this._$select2.select2('destroy');
            } catch (e) { /* ignore */ }
            this._$select2 = null;
        }
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;

        if (name === 'readonly') {
            this._updateReadonlyState();
        }
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Set available fields that can be sorted
     * @param {Array} fields - Array of field objects {table, field, alias}
     */
    setAvailableFields(fields) {
        this._availableFields = (fields || []).map(f => ({
            table: f.table || '',
            field: f.field || f.name || '',
            alias: f.alias || f.field || f.name || ''
        }));

        this._renderFieldSelect();
    }

    /**
     * Set the current sort configuration
     * @param {Array} sorting - Array of sort objects {table, field, direction}
     */
    setSorting(sorting) {
        this._sorting = (sorting || []).map(s => ({
            table: s.table || '',
            field: s.field || '',
            alias: s.alias || s.field || '',
            direction: s.direction || 'ASC'
        }));

        this._renderSortList();
        this._renderFieldSelect();
    }

    /**
     * Get current sort configuration
     * @returns {Array} Array of sort objects
     */
    getSorting() {
        return this._sorting.map(s => ({
            table: s.table,
            field: s.field,
            alias: s.alias,
            direction: s.direction
        }));
    }

    /**
     * Add a field to sort by
     * @param {Object} field - Field object {table, field, alias}
     * @param {string} direction - Sort direction ('ASC' or 'DESC')
     */
    addSort(field, direction = 'ASC') {
        // Check if already exists
        if (this._sorting.some(s => s.table === field.table && s.field === field.field)) {
            return;
        }

        const sortItem = {
            table: field.table,
            field: field.field,
            alias: field.alias || field.field,
            direction
        };

        this._sorting.push(sortItem);
        this._renderSortList();
        this._renderFieldSelect();

        this.dispatchEvent(new CustomEvent('sort-add', {
            bubbles: true,
            detail: { sort: sortItem, index: this._sorting.length - 1 }
        }));

        this._dispatchChange();
    }

    /**
     * Remove a sort field by index
     * @param {number} index - Sort field index
     */
    removeSort(index) {
        if (index >= 0 && index < this._sorting.length) {
            const removed = this._sorting.splice(index, 1)[0];

            this._renderSortList();
            this._renderFieldSelect();

            this.dispatchEvent(new CustomEvent('sort-remove', {
                bubbles: true,
                detail: { sort: removed, index }
            }));

            this._dispatchChange();
        }
    }

    /**
     * Initialize Select2 on the field dropdown
     */
    initSelect2() {
        const select = this.querySelector('.sort-field-select');
        if (!select) return;

        if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
            // Destroy existing Select2 instance
            if (this._$select2) {
                try {
                    this._$select2.select2('destroy');
                } catch (e) { /* ignore */ }
            }

            this._$select2 = jQuery(select).select2({
                placeholder: '-- Selecteer veld --',
                allowClear: false,
                width: '100%',
                dropdownParent: jQuery(document.body)
            });

            // Handle Select2 change
            this._$select2.on('change', () => {
                const addBtn = this.querySelector('.sort-add-btn');
                if (addBtn) {
                    addBtn.disabled = !select.value;
                }
            });
        }
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    _render() {
        this.innerHTML = `
            <div class="sort-config-container">
                <div class="sort-header">
                    <select class="sort-field-select">
                        <option value="">-- Selecteer veld --</option>
                    </select>
                    <button type="button" class="sort-add-btn btn" disabled>
                        + Toevoegen
                    </button>
                </div>
                <div class="sort-list">
                </div>
            </div>
        `;

        this._renderSortList();
        this._renderFieldSelect();
    }

    _renderSortList() {
        const list = this.querySelector('.sort-list');
        if (!list) return;

        if (this._sorting.length === 0) {
            list.innerHTML = `
                <div class="empty-state">
                    <span class="empty-icon lnr lnr-sort-amount-asc"></span>
                    <div class="empty-text">Geen sortering ingesteld</div>
                </div>
            `;
            return;
        }

        list.innerHTML = this._sorting.map((sort, index) => `
            <div class="sort-item" data-index="${index}" draggable="true">
                <div class="sort-handle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <div class="sort-field-info">
                    <span class="sort-field-name">${this._escapeHtml(sort.alias)}</span>
                    <span class="sort-field-table">${this._escapeHtml(this._displayTableName(sort.table))}</span>
                </div>
                <select class="sort-direction" data-index="${index}">
                    <option value="ASC" ${sort.direction === 'ASC' ? 'selected' : ''}>Oplopend ↑</option>
                    <option value="DESC" ${sort.direction === 'DESC' ? 'selected' : ''}>Aflopend ↓</option>
                </select>
                <button type="button" class="sort-remove" data-index="${index}" title="Verwijderen">×</button>
            </div>
        `).join('');
    }

    _renderFieldSelect() {
        const select = this.querySelector('.sort-field-select');
        const addBtn = this.querySelector('.sort-add-btn');
        if (!select) return;

        // Filter out fields that are already in sorting
        const availableFields = this._availableFields.filter(f =>
            !this._sorting.some(s => s.table === f.table && s.field === f.field)
        );

        const currentValue = select.value;
        select.innerHTML = `
            <option value="">-- Selecteer veld --</option>
            ${availableFields.map((f, i) => `
                <option value="${i}" data-table="${this._escapeHtml(f.table)}" data-field="${this._escapeHtml(f.field)}">
                    ${this._escapeHtml(this._displayTableName(f.table))}: ${this._escapeHtml(f.alias || f.field)}
                </option>
            `).join('')}
        `;

        // Sync Select2 with new options
        if (this._$select2) {
            // Destroy and reinit to pick up new options (trigger('change') doesn't work for replaced innerHTML)
            try {
                this._$select2.select2('destroy');
            } catch (e) { /* ignore */ }
            this._$select2 = null;
            // Reinitialize after a small delay to ensure DOM is ready
            const self = this;
            setTimeout(function() {
                self.initSelect2();
            }, 10);
        }

        if (addBtn) {
            addBtn.disabled = availableFields.length === 0 || !select.value;
        }
    }

    _setupEventListeners() {
        const container = this.querySelector('.sort-config-container');
        const select = this.querySelector('.sort-field-select');
        const addBtn = this.querySelector('.sort-add-btn');
        const list = this.querySelector('.sort-list');

        if (!container) return;

        // Field select change (native, Select2 has its own handler)
        if (select) {
            select.addEventListener('change', () => {
                if (addBtn) {
                    addBtn.disabled = !select.value;
                }
            });
        }

        // Add sort button
        if (addBtn) {
            addBtn.addEventListener('click', () => {
                const selectedOption = select.selectedOptions[0];
                if (!selectedOption || !selectedOption.value) return;

                // Find the field in available fields that's not already sorted
                const availableFields = this._availableFields.filter(f =>
                    !this._sorting.some(s => s.table === f.table && s.field === f.field)
                );

                const fieldIndex = parseInt(selectedOption.value, 10);
                const field = availableFields[fieldIndex];

                if (field) {
                    this.addSort(field);
                    select.value = '';
                    if (this._$select2) {
                        this._$select2.val('').trigger('change');
                    }
                    addBtn.disabled = true;
                }
            });
        }

        // Event delegation for sort list
        if (list) {
            // Direction change
            list.addEventListener('change', (e) => {
                if (e.target.classList.contains('sort-direction')) {
                    const index = parseInt(e.target.dataset.index, 10);
                    if (!isNaN(index) && index < this._sorting.length) {
                        this._sorting[index].direction = e.target.value;
                        this._dispatchChange();
                    }
                }
            });

            // Remove click
            list.addEventListener('click', (e) => {
                const removeBtn = e.target.closest('.sort-remove');
                if (removeBtn) {
                    const index = parseInt(removeBtn.dataset.index, 10);
                    if (!isNaN(index)) {
                        this.removeSort(index);
                    }
                }
            });

            // Drag and drop
            list.addEventListener('dragstart', (e) => {
                const item = e.target.closest('.sort-item');
                if (!item) return;

                this._draggedIndex = parseInt(item.dataset.index, 10);
                item.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';

                this._placeholder = document.createElement('div');
                this._placeholder.className = 'sort-placeholder';
            });

            list.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';

                const item = e.target.closest('.sort-item');
                if (!item || parseInt(item.dataset.index, 10) === this._draggedIndex) return;

                // Remove existing placeholder
                if (this._placeholder && this._placeholder.parentNode) {
                    this._placeholder.remove();
                }

                // Insert placeholder
                const rect = item.getBoundingClientRect();
                const midY = rect.top + rect.height / 2;

                if (e.clientY < midY) {
                    item.parentNode.insertBefore(this._placeholder, item);
                } else {
                    item.parentNode.insertBefore(this._placeholder, item.nextSibling);
                }
            });

            list.addEventListener('dragend', (e) => {
                const items = list.querySelectorAll('.sort-item');
                items.forEach(item => item.classList.remove('dragging'));

                if (this._placeholder && this._placeholder.parentNode && this._draggedIndex !== null) {
                    // Get new order
                    const newOrder = [];
                    const allElements = list.querySelectorAll('.sort-item, .sort-placeholder');

                    allElements.forEach(el => {
                        if (el === this._placeholder) {
                            newOrder.push(this._sorting[this._draggedIndex]);
                        } else {
                            const idx = parseInt(el.dataset.index, 10);
                            if (idx !== this._draggedIndex) {
                                newOrder.push(this._sorting[idx]);
                            }
                        }
                    });

                    this._placeholder.remove();
                    this._sorting = newOrder;
                    this._renderSortList();
                    this._dispatchChange();
                }

                this._draggedIndex = null;
                this._placeholder = null;
            });
        }
    }

    _dispatchChange() {
        this.dispatchEvent(new CustomEvent('change', {
            bubbles: true,
            detail: {
                sorting: this.getSorting()
            }
        }));
    }

    _updateReadonlyState() {
        // CSS handles the visual readonly state via [readonly] attribute selector
    }

    _escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    _displayTableName(tableName) {
        // Use shared function from CMA namespace for nicer display
        return (typeof CMA !== 'undefined' && CMA.displayTableName)
            ? CMA.displayTableName(tableName)
            : tableName || '';
    }
}

// Register the component
customElements.define('cma-sort-config', CmaSortConfig);

} // End guard against double registration
