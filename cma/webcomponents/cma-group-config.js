/**
 * cma-group-config Web Component
 *
 * Grouping configuration component for the Query Designer.
 * Allows adding fields for grouping with aggregation options.
 * No Shadow DOM - allows Select2 integration.
 *
 * Usage:
 *   <cma-group-config id="groupConfig"></cma-group-config>
 *
 * Attributes:
 *   - readonly: Make the component read-only
 *
 * Events:
 *   - change: Fired when group configuration changes
 *   - group-add: Fired when a group field is added
 *   - group-remove: Fired when a group field is removed
 *
 * Methods:
 *   - setAvailableFields(fields): Set the available fields for grouping
 *   - setGrouping(grouping): Set the current group configuration
 *   - getGrouping(): Get current group configuration
 *   - addGroup(field): Add a field to group by
 *   - removeGroup(index): Remove a group field
 *   - initSelect2(): Initialize Select2 on the field dropdown
 */

// Guard against double registration
if (!customElements.get('cma-group-config')) {

class CmaGroupConfig extends HTMLElement {
    static get observedAttributes() {
        return ['readonly'];
    }

    constructor() {
        super();
        // No shadow DOM - inherit styles from page, allows Select2 integration

        // State
        this._availableFields = [];
        this._grouping = [];
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
     * Set available fields that can be grouped
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
     * Set the current group configuration
     * @param {Array} grouping - Array of group objects {table, field}
     */
    setGrouping(grouping) {
        this._grouping = (grouping || []).map(g => ({
            table: g.table || '',
            field: g.field || '',
            alias: g.alias || g.field || ''
        }));

        this._renderGroupList();
        this._renderFieldSelect();
    }

    /**
     * Get current group configuration
     * @returns {Array} Array of group objects
     */
    getGrouping() {
        return this._grouping.map(g => ({
            table: g.table,
            field: g.field,
            alias: g.alias
        }));
    }

    /**
     * Add a field to group by
     * @param {Object} field - Field object {table, field, alias}
     */
    addGroup(field) {
        // Check if already exists
        if (this._grouping.some(g => g.table === field.table && g.field === field.field)) {
            return;
        }

        const groupItem = {
            table: field.table,
            field: field.field,
            alias: field.alias || field.field
        };

        this._grouping.push(groupItem);
        this._renderGroupList();
        this._renderFieldSelect();

        this.dispatchEvent(new CustomEvent('group-add', {
            bubbles: true,
            detail: { group: groupItem, index: this._grouping.length - 1 }
        }));

        this._dispatchChange();
    }

    /**
     * Remove a group field by index
     * @param {number} index - Group field index
     */
    removeGroup(index) {
        if (index >= 0 && index < this._grouping.length) {
            const removed = this._grouping.splice(index, 1)[0];

            this._renderGroupList();
            this._renderFieldSelect();

            this.dispatchEvent(new CustomEvent('group-remove', {
                bubbles: true,
                detail: { group: removed, index }
            }));

            this._dispatchChange();
        }
    }

    /**
     * Initialize Select2 on the field dropdown
     */
    initSelect2() {
        const select = this.querySelector('.group-field-select');
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
                const addBtn = this.querySelector('.group-add-btn');
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
            <div class="group-config-container">
                <div class="group-header">
                    <select class="group-field-select">
                        <option value="">-- Selecteer veld --</option>
                    </select>
                    <button type="button" class="group-add-btn btn" disabled>
                        + Toevoegen
                    </button>
                </div>
                <div class="group-list">
                </div>
            </div>
        `;

        this._renderGroupList();
        this._renderFieldSelect();
    }

    _renderGroupList() {
        const list = this.querySelector('.group-list');
        if (!list) return;

        if (this._grouping.length === 0) {
            list.innerHTML = `
                <div class="empty-state">
                    <div class="empty-text">Geen groepering ingesteld</div>
                </div>
            `;
            return;
        }

        list.innerHTML = this._grouping.map((group, index) => `
            <div class="group-item" data-index="${index}" draggable="true">
                <div class="group-handle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <div class="group-field-info">
                    <span class="group-field-name">${this._escapeHtml(group.alias)}</span>
                    <span class="group-field-table">${this._escapeHtml(this._displayTableName(group.table))}</span>
                </div>
                <button type="button" class="group-remove" data-index="${index}" title="Verwijderen">×</button>
            </div>
        `).join('');
    }

    _renderFieldSelect() {
        const select = this.querySelector('.group-field-select');
        const addBtn = this.querySelector('.group-add-btn');
        if (!select) return;

        // Filter out fields that are already in grouping
        const availableFields = this._availableFields.filter(f =>
            !this._grouping.some(g => g.table === f.table && g.field === f.field)
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
        const container = this.querySelector('.group-config-container');
        const select = this.querySelector('.group-field-select');
        const addBtn = this.querySelector('.group-add-btn');
        const list = this.querySelector('.group-list');

        if (!container) return;

        // Field select change (native, Select2 has its own handler)
        if (select) {
            select.addEventListener('change', () => {
                if (addBtn) {
                    addBtn.disabled = !select.value;
                }
            });
        }

        // Add group button
        if (addBtn) {
            addBtn.addEventListener('click', () => {
                const selectedOption = select.selectedOptions[0];
                if (!selectedOption || !selectedOption.value) return;

                // Find the field in available fields that's not already grouped
                const availableFields = this._availableFields.filter(f =>
                    !this._grouping.some(g => g.table === f.table && g.field === f.field)
                );

                const fieldIndex = parseInt(selectedOption.value, 10);
                const field = availableFields[fieldIndex];

                if (field) {
                    this.addGroup(field);
                    select.value = '';
                    if (this._$select2) {
                        this._$select2.val('').trigger('change');
                    }
                    addBtn.disabled = true;
                }
            });
        }

        // Event delegation for group list
        if (list) {
            // Remove click
            list.addEventListener('click', (e) => {
                const removeBtn = e.target.closest('.group-remove');
                if (removeBtn) {
                    const index = parseInt(removeBtn.dataset.index, 10);
                    if (!isNaN(index)) {
                        this.removeGroup(index);
                    }
                }
            });

            // Drag and drop
            list.addEventListener('dragstart', (e) => {
                const item = e.target.closest('.group-item');
                if (!item) return;

                this._draggedIndex = parseInt(item.dataset.index, 10);
                item.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';

                this._placeholder = document.createElement('div');
                this._placeholder.className = 'group-placeholder';
            });

            list.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';

                const item = e.target.closest('.group-item');
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
                const items = list.querySelectorAll('.group-item');
                items.forEach(item => item.classList.remove('dragging'));

                if (this._placeholder && this._placeholder.parentNode && this._draggedIndex !== null) {
                    // Get new order
                    const newOrder = [];
                    const allElements = list.querySelectorAll('.group-item, .group-placeholder');

                    allElements.forEach(el => {
                        if (el === this._placeholder) {
                            newOrder.push(this._grouping[this._draggedIndex]);
                        } else {
                            const idx = parseInt(el.dataset.index, 10);
                            if (idx !== this._draggedIndex) {
                                newOrder.push(this._grouping[idx]);
                            }
                        }
                    });

                    this._placeholder.remove();
                    this._grouping = newOrder;
                    this._renderGroupList();
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
                grouping: this.getGrouping()
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
customElements.define('cma-group-config', CmaGroupConfig);

} // End guard against double registration
