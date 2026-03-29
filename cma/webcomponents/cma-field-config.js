/**
 * cma-field-config Web Component
 *
 * Field configuration component for the Query Designer.
 * Displays fields from selected tables with visibility toggle, aliases, and filters.
 *
 * Usage:
 *   <cma-field-config id="fieldConfig"></cma-field-config>
 *
 * Attributes:
 *   - readonly: Make the component read-only
 *
 * Events:
 *   - field-change: Fired when any field property changes
 *   - change: Fired when field configuration changes
 *
 * Methods:
 *   - setFields(fields): Set the field list
 *   - getFields(): Get current field configuration
 *   - getVisibleFields(): Get only visible fields
 *   - setFieldProperty(index, prop, value): Update a field property
 */

// Guard against double registration
if (!customElements.get('cma-field-config')) {

class CmaFieldConfig extends HTMLElement {
    static get observedAttributes() {
        return ['readonly'];
    }

    constructor() {
        super();
        // No shadow DOM - inherit styles from page

        // State
        this._fields = [];
        this._parameters = []; // Available parameters for filter values
        this._sorting = []; // Current sorting configuration
        this._grouping = []; // Current grouping configuration
        this._listenersAttached = false;
        this._searchFilter = ''; // Search filter for field list
        this._showSelectedOnly = false; // Filter to show only selected (visible) fields
    }

    connectedCallback() {
        this._render();
        // Don't setup listeners here - wait until setFields populates the data
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;

        if (name === 'readonly') {
            this._updateReadonlyState();
        }
    }

    // =========================================================================
    // Type translations (English to Dutch)
    // =========================================================================
    static _typeTranslations = {
        'text': 'tekst',
        'number': 'getal',
        'date': 'datum',
        'boolean': 'ja/nee',
        'binary': 'binair'
    };

    _translateType(typeCategory) {
        return CmaFieldConfig._typeTranslations[typeCategory] || typeCategory;
    }

    /**
     * Strip 'tbl' prefix from table name for display
     */
    _displayTableName(tableName) {
        // Use shared function from CMA namespace
        return (typeof CMA !== 'undefined' && CMA.displayTableName)
            ? CMA.displayTableName(tableName)
            : tableName || '';
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Set the fields to configure
     * @param {Array} fields - Array of field objects
     */
    setFields(fields) {
        this._fields = (fields || []).map((f, index) => {
            const fieldName = f.field || f.name || '';
            const typeCategory = f.typeCategory || 'text';
            let alias = f.alias || fieldName;

            // For numerical fields starting with 'fk', strip the 'fk' prefix from alias
            if (typeCategory === 'number' && fieldName.toLowerCase().startsWith('fk') && !f.alias) {
                alias = fieldName.substring(2);
            }

            return {
                table: f.table || '',
                field: fieldName,
                alias: alias,
                dataType: f.dataType || 0,
                typeCategory: typeCategory,
                visible: f.visible !== false,
                showTotal: f.showTotal || false,
                _index: index
            };
        });

        this._renderTable();
        this._updateSelectAllState();

        // Setup event listeners AFTER fields are rendered
        if (!this._listenersAttached) {
            this._setupEventListeners();
        }
    }

    /**
     * Get current field configuration
     * @returns {Array} Array of field objects
     */
    getFields() {
        return this._fields.map(f => ({
            table: f.table,
            field: f.field,
            alias: f.alias,
            dataType: f.dataType,
            typeCategory: f.typeCategory,
            visible: f.visible,
            showTotal: f.showTotal
        }));
    }

    /**
     * Get only visible fields
     * @returns {Array} Array of visible field objects
     */
    getVisibleFields() {
        return this.getFields().filter(f => f.visible);
    }

    /**
     * Update a specific field property
     * @param {number} index - Field index
     * @param {string} prop - Property name
     * @param {*} value - New value
     */
    setFieldProperty(index, prop, value) {
        if (index >= 0 && index < this._fields.length) {
            this._fields[index][prop] = value;
            this._dispatchFieldChange(index, prop, value);
        }
    }

    /**
     * Set available parameters (kept for API compatibility, filters are now in conditions panel)
     * @param {Array} params - Array of parameter objects {name, label}
     */
    setParameters(params) {
        this._parameters = params || [];
    }

    /**
     * Get available parameters
     * @returns {Array}
     */
    getParameters() {
        return this._parameters;
    }

    /**
     * Set current sorting configuration (to show sort indicators)
     * @param {Array} sorting - Array of {table, field, direction} objects
     */
    setSorting(sorting) {
        this._sorting = sorting || [];
        this._renderTable();
    }

    /**
     * Set current grouping configuration (to show group indicators)
     * @param {Array} grouping - Array of {table, field} objects
     */
    setGrouping(grouping) {
        this._grouping = grouping || [];
        this._renderTable();
    }

    /**
     * Check if a field is being sorted and get its direction
     * @param {string} table
     * @param {string} field
     * @returns {string|null} 'asc', 'desc', or null
     */
    _getFieldSortDirection(table, field) {
        const sortItem = this._sorting.find(s =>
            s.table === table && s.field === field
        );
        return sortItem ? (sortItem.direction || 'ASC') : null;
    }

    /**
     * Check if a field is being grouped
     * @param {string} table
     * @param {string} field
     * @returns {boolean}
     */
    _isFieldGrouped(table, field) {
        return this._grouping.some(g =>
            g.table === table && g.field === field
        );
    }

    /**
     * Select all fields (make visible)
     */
    selectAll() {
        this._fields.forEach(field => {
            field.visible = true;
        });
        this._renderTable();
        this._updateSelectAllState();
        this._dispatchChange();
    }

    /**
     * Deselect all fields (make invisible)
     */
    deselectAll() {
        this._fields.forEach(field => {
            field.visible = false;
        });
        this._renderTable();
        this._updateSelectAllState();
        this._dispatchChange();
    }

    /**
     * Set search filter for the field list
     * @param {string} filter - Search term to filter fields
     */
    setSearchFilter(filter) {
        this._searchFilter = (filter || '').toLowerCase().trim();
        this._renderTable();
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    _render() {
        this.innerHTML = `
            <style>
                cma-field-config {
                    display: block;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    font-size: var(--font-size);
                }

                * {
                    box-sizing: border-box;
                }

                /* Linearicons inherited from page CSS */

                .field-config-container {
                    border: 1px solid var(--border-color, #ddd);
                    border-top: 0;
                    border-radius: 4px;
                    overflow: hidden;
                    height: 100%;
                    display: flex;
                    flex-direction: column;
                }

                .field-config-table {
                    width: 100%;
                    border-collapse: collapse;
                }

                .field-config-table th,
                .field-config-table td {
                    padding: 8px 10px;
                    text-align: left;
                    border-bottom: 1px solid var(--border-light, #eee);
                }

                .field-config-table th {
                    background: var(--bg-surface-alt, #f8f9fa);
                    font-weight: 600;
                    font-size: var(--font-size-sm);
                    position: sticky;
                    top: 0;
                    z-index: 1;
                }

                .field-config-table tbody tr:hover {
                    background: var(--bg-hover);
                    outline: 1px solid var(--color-primary, #204496);
                    outline-offset: -1px;
                }

                /* Hidden field: no visual change, checkbox state is sufficient */

                /* Ensure visibility checkbox is always clickable */
                .visibility-checkbox {
                    cursor: pointer;
                    pointer-events: auto !important;
                }

                .col-checkbox {
                    width: 50px;
                    text-align: center !important;
                }

                .select-all-checkbox {
                    width: 18px;
                    height: 18px;
                    cursor: pointer;
                }

                .col-field {
                    width: 200px;
                }

                .col-alias {
                    width: 200px;
                }

                .col-type {
                    width: 70px;
                }

                .col-total {
                    width: 60px;
                    text-align: center !important;
                }

                .col-total input[type="checkbox"] {
                    width: 16px;
                    height: 16px;
                    cursor: pointer;
                }

                .col-total input[type="checkbox"]:disabled {
                    cursor: not-allowed;
                    opacity: 0.3;
                }

                .col-filter {
                    width: 50px;
                    text-align: center !important;
                }

                .add-filter-btn {
                    width: 28px;
                    height: 28px;
                    padding: 0;
                    border: 1px solid var(--border-color, #ddd);
                    border-radius: 4px;
                    background: var(--bg-surface, #fff);
                    cursor: pointer;
                    color: var(--text-muted, #666);
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    font-size: var(--font-size-lg);
                    transition: all 0.15s ease;
                }

                .add-filter-btn:hover {
                    background: var(--bg-hover, #eef6ff);
                    border-color: var(--color-primary, #204496);
                    color: var(--color-primary, #204496);
                }

                .add-filter-btn:active {
                    transform: scale(0.95);
                }

                .field-table {
                    font-size: var(--font-size-xs);
                    color: var(--text-muted, #888);
                }

                .field-column {
                    font-weight: 500;
                }

                .field-indicator {
                    display: inline-block;
                    margin-left: 6px;
                    font-size: var(--font-size-2xs);
                    padding: 1px 4px;
                    border-radius: 3px;
                    vertical-align: middle;
                }

                .sort-indicator {
                    background: var(--color-primary-light, #e3f2fd);
                    color: #ffffff;
                    font-weight: 600;
                }

                .group-indicator {
                    background: var(--color-success-light, #e8f5e9);
                    color: var(--color-success, #2e7d32);
                }

                .group-indicator .lnr {
                    font-size: var(--font-size-xs);
                }

                .type-label {
                    font-size: var(--font-size-xs);
                    color: var(--text-muted, #888);
                }

                .alias-input {
                    width: 200px;
                    padding: 6px 10px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-size: var(--font-size);
                }

                .alias-input:focus {
                    outline: none;
                    border-color: #204496;
                    box-shadow: 0 0 0 2px rgba(32, 68, 150, 0.1);
                }


                .visibility-checkbox {
                    width: 18px;
                    height: 18px;
                    cursor: pointer;
                }

                .empty-state {
                    padding: 40px 20px;
                    text-align: center;
                    color: #888;
                }

                .empty-state .empty-icon {
                    font-size: 36px;
                    margin-bottom: 10px;
                    opacity: 0.5;
                    color: var(--text-muted, #888);
                }

                .empty-state .empty-text {
                    font-size: var(--font-size-md);
                }

                /* Scrollable body */
                .table-wrapper {
                    flex: 1;
                    overflow-y: auto;
                }

                /* Readonly state */
                cma-field-config[readonly] .alias-input,
                cma-field-config[readonly] .filter-operator,
                cma-field-config[readonly] .filter-value,
                cma-field-config[readonly] .visibility-checkbox {
                    pointer-events: none;
                    opacity: 0.7;
                }
            </style>
            <div class="field-config-container">
                <div class="table-wrapper">
                    <table class="field-config-table">
                        <thead>
                            <tr>
                                <th class="col-checkbox">
                                    <input type="checkbox" class="select-all-checkbox" id="selectAllCheckbox" title="Alles selecteren/deselecteren">
                                </th>
                                <th class="col-field">Veld</th>
                                <th class="col-alias">Alias</th>
                                <th class="col-type">Type</th>
                                <th class="col-total">Totaal</th>
                                <th class="col-filter">Filter</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        this._renderTable();
    }

    _renderTable() {
        const tbody = this.querySelector('#tableBody');
        if (!tbody) return;

        if (this._fields.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <span class="empty-icon lnr lnr-list"></span>
                            <div class="empty-text">Selecteer eerst tabellen om velden te configureren</div>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        // Filter fields based on search term and selected-only filter
        let filteredFields = this._fields.map((field, index) => ({ field, index }));

        if (this._searchFilter) {
            filteredFields = filteredFields.filter(({ field }) => {
                const searchTerm = this._searchFilter;
                return field.field.toLowerCase().includes(searchTerm) ||
                       field.table.toLowerCase().includes(searchTerm) ||
                       (field.alias && field.alias.toLowerCase().includes(searchTerm));
            });
        }

        if (this._showSelectedOnly) {
            filteredFields = filteredFields.filter(({ field }) => field.visible);
        }

        if (filteredFields.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <span class="empty-icon lnr lnr-magnifier"></span>
                            <div class="empty-text">Geen velden gevonden voor "${this._escapeHtml(this._searchFilter)}"</div>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        // Check if there's only one unique table
        const uniqueTables = new Set(this._fields.map(f => f.table));
        const showTableName = uniqueTables.size > 1;

        tbody.innerHTML = filteredFields.map(({ field, index }) => {
            const sortDir = this._getFieldSortDirection(field.table, field.field);
            const isGrouped = this._isFieldGrouped(field.table, field.field);
            return `
            <tr data-index="${index}" class="${field.visible ? '' : 'hidden-field'}">
                <td class="col-checkbox">
                    <input type="checkbox" class="visibility-checkbox"
                           ${field.visible ? 'checked' : ''}
                           data-field="visible">
                </td>
                <td class="col-field">
                    <div class="field-name">
                        ${showTableName ? `<span class="field-table">${this._escapeHtml(this._displayTableName(field.table))}.</span>` : ''}
                        <span class="field-column">${this._escapeHtml(field.field)}</span>
                        ${sortDir ? `<span class="field-indicator sort-indicator" title="Gesorteerd ${sortDir.toUpperCase() === 'ASC' ? 'oplopend' : 'aflopend'}">${sortDir.toUpperCase() === 'ASC' ? 'A↓Z' : 'Z↓A'}</span>` : ''}
                        ${isGrouped ? `<span class="field-indicator group-indicator" title="Gegroepeerd"><span class="lnr lnr-layers"></span></span>` : ''}
                    </div>
                </td>
                <td class="col-alias">
                    <input type="text" class="alias-input"
                           value="${this._escapeHtml(field.alias)}"
                           data-field="alias"
                           placeholder="Alias">
                </td>
                <td class="col-type">
                    <span class="type-label">${this._escapeHtml(this._translateType(field.typeCategory))}</span>
                </td>
                <td class="col-total">
                    <input type="checkbox" class="total-checkbox"
                           ${field.showTotal ? 'checked' : ''}
                           ${this._canShowTotal(field) ? '' : 'disabled'}
                           data-field="showTotal"
                           title="${this._canShowTotal(field) ? 'Toon totaal voor dit veld' : 'Niet beschikbaar voor dit veld'}">
                </td>
                <td class="col-filter">
                    <button type="button" class="add-filter-btn"
                            data-table="${this._escapeHtml(field.table)}"
                            data-field="${this._escapeHtml(field.field)}"
                            data-alias="${this._escapeHtml(field.alias)}"
                            data-datatype="${field.dataType}"
                            data-typecategory="${this._escapeHtml(field.typeCategory)}"
                            title="Filter toevoegen">+</button>
                </td>
            </tr>
        `}).join('');
    }

    /**
     * Set the selected-only filter
     * @param {boolean} showSelectedOnly - Whether to show only selected fields
     */
    setShowSelectedOnly(showSelectedOnly) {
        this._showSelectedOnly = showSelectedOnly;
        this._renderTable();
    }

    _setupEventListeners() {
        // Select all checkbox
        const selectAllCheckbox = this.querySelector('#selectAllCheckbox');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                const visible = e.target.checked;
                this._fields.forEach((field, index) => {
                    field.visible = visible;
                });
                this._renderTable();
                this._updateSelectAllState();
                this._dispatchChange();
            });
        }

        // Delegate events to the component itself (events bubble up from children)
        this.addEventListener('change', (e) => {
            const row = e.target.closest('tr');
            if (!row) return;

            const index = parseInt(row.dataset.index, 10);
            if (isNaN(index)) return;

            const fieldName = e.target.dataset.field;

            if (fieldName === 'visible') {
                if (typeof cmaLog !== 'undefined') {
                    cmaLog.log('cma-field-config: visibility checkbox clicked, index:', index, 'checked:', e.target.checked);
                }
                this._fields[index].visible = e.target.checked;
                row.classList.toggle('hidden-field', !e.target.checked);
                this._updateSelectAllState();
                this._dispatchFieldChange(index, 'visible', e.target.checked);
                this._dispatchChange(); // Also dispatch main change event for SQL update

            } else if (fieldName === 'alias') {
                // Sanitize alias - remove spaces
                const sanitized = (typeof CMA !== 'undefined' && CMA.sanitizeAlias)
                    ? CMA.sanitizeAlias(e.target.value)
                    : e.target.value.replace(/\s+/g, '_');
                e.target.value = sanitized;
                this._fields[index].alias = sanitized;
                this._dispatchFieldChange(index, 'alias', sanitized);

            } else if (fieldName === 'showTotal') {
                this._fields[index].showTotal = e.target.checked;
                this._dispatchFieldChange(index, 'showTotal', e.target.checked);
            }
        });

        // NOTE: Filter operator and value handling removed - filters are now managed by cma-conditions-panel

        // Also handle input events for live updates
        this.addEventListener('input', (e) => {
            if (e.target.dataset.field === 'alias') {
                const row = e.target.closest('tr');
                if (!row) return;

                const index = parseInt(row.dataset.index, 10);
                if (isNaN(index)) return;

                // Sanitize alias - remove spaces
                const sanitized = (typeof CMA !== 'undefined' && CMA.sanitizeAlias)
                    ? CMA.sanitizeAlias(e.target.value)
                    : e.target.value.replace(/\s+/g, '_');
                e.target.value = sanitized;
                this._fields[index].alias = sanitized;
            }
        });

        // Click handler for buttons only
        this.addEventListener('click', (e) => {
            // Add filter button - dispatch add-condition event
            if (e.target.classList.contains('add-filter-btn')) {
                e.stopPropagation();
                const btn = e.target;
                this.dispatchEvent(new CustomEvent('add-condition', {
                    bubbles: true,
                    composed: true,
                    detail: {
                        table: btn.dataset.table,
                        field: btn.dataset.field,
                        alias: btn.dataset.alias,
                        dataType: parseInt(btn.dataset.datatype, 10) || 0,
                        typeCategory: btn.dataset.typecategory || 'text'
                    }
                }));
            }
        });

        this._listenersAttached = true;
    }

    /**
     * Check if a field can have totals shown
     * Excludes: non-number types, fields starting with 'fk', ending with 'id', or exactly 'ID'
     */
    _canShowTotal(field) {
        if (field.typeCategory !== 'number') return false;
        const name = field.field.toLowerCase();
        if (name.startsWith('fk')) return false;
        if (name.endsWith('id')) return false;
        if (name === 'id') return false;
        return true;
    }

    _updateSelectAllState() {
        const selectAllCheckbox = this.querySelector('#selectAllCheckbox');
        if (!selectAllCheckbox || this._fields.length === 0) return;

        const visibleCount = this._fields.filter(f => f.visible).length;
        const totalCount = this._fields.length;

        if (visibleCount === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (visibleCount === totalCount) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        }
    }

    _dispatchChange() {
        if (typeof cmaLog !== 'undefined') {
            cmaLog.log('cma-field-config: dispatching change event', this.getFields().length, 'fields');
        }
        this.dispatchEvent(new CustomEvent('change', {
            bubbles: true,
            composed: true,
            detail: {
                fields: this.getFields()
            }
        }));
    }

    _dispatchFieldChange(index, property, value) {
        this.dispatchEvent(new CustomEvent('field-change', {
            bubbles: true,
            composed: true,
            detail: {
                index,
                property,
                value,
                field: this._fields[index]
            }
        }));

        this.dispatchEvent(new CustomEvent('change', {
            bubbles: true,
            composed: true,
            detail: {
                fields: this.getFields()
            }
        }));
    }

    _updateReadonlyState() {
        // The CSS handles the visual readonly state
    }

    _escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Register the component
customElements.define('cma-field-config', CmaFieldConfig);

} // End guard against double registration
