/**
 * lib-table.js - Table Web Components
 *
 * Contains two components:
 *
 * 1. LibDataTable (<lib-data-table>) - High-Performance JSON Data Table
 *    - Virtual scrolling (only renders visible rows)
 *    - JSON data transport (not HTML)
 *    - Client-side filtering, sorting, pagination
 *    - Column resizing with localStorage persistence
 *    - Column reordering via drag-drop
 *    - Infinite scroll with server-side pagination
 *    - Shadow DOM encapsulation
 *
 * 2. LibTable (<lib-table>) - HTML Table Wrapper
 *    - Wraps existing HTML tables
 *    - Adds filtering, sorting, export
 *    - Consistent styling
 *
 * Usage LibDataTable:
 * <lib-data-table
 *   data-url="api.php?action=tableData"
 *   data-form-id="123"
 *   page-size="50"
 *   row-height="36"
 *   sortable
 *   filterable
 *   resizable
 *   reorderable>
 * </lib-data-table>
 *
 * Usage LibTable:
 * <lib-table>
 *   <table>...</table>
 * </lib-table>
 */

// Guard against double registration
if (!customElements.get('lib-data-table')) {

class LibDataTable extends HTMLElement {
    // Private state
    #state = {
        rows: [],           // All loaded rows (in-memory)
        filteredRows: [],   // Rows after applying filters
        columns: [],        // Column definitions
        filters: {},        // Active filters per column
        sort: { column: null, direction: 'asc' },
        selection: new Set(),
        visibleRange: { start: 0, end: 50 },
        totalCount: 0,
        hasMore: false,
        lastId: null,
        isLoading: false
    };

    // Configuration
    #config = {
        pageSize: 50,
        rowHeight: 36,
        bufferRows: 10,     // Extra rows to render above/below viewport
        formId: null,
        dataUrl: null,
        sortable: true,
        filterable: true,
        resizable: true,
        reorderable: true,
        selectable: false,
        virtualScroll: true,
        density: 'normal'   // 'compact', 'normal', 'comfortable'
    };

    // Density settings: rowHeight and cellPadding
    #densitySettings = {
        compact: { rowHeight: 28, cellPadding: '4px 8px' },
        normal: { rowHeight: 36, cellPadding: '8px 12px' },
        comfortable: { rowHeight: 48, cellPadding: '12px 16px' }
    };

    // DOM references
    #elements = {
        container: null,
        header: null,
        body: null,
        footer: null,
        scrollContainer: null,
        loadingIndicator: null
    };

    // Preferences (localStorage)
    #preferences = {
        columnWidths: {},
        columnOrder: [],
        hiddenColumns: []
    };

    // Event handlers (for cleanup)
    #handlers = {};

    // Resize state
    #resizeState = null;

    // Drag state
    #dragState = null;

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
    }

    // =========================================================================
    // Lifecycle
    // =========================================================================

    connectedCallback() {
        this.#parseAttributes();
        this.#loadPreferences();
        this.#render();
        this.#bindEvents();

        // Auto-load data if URL provided
        if (this.#config.dataUrl) {
            this.load();
        }
    }

    disconnectedCallback() {
        this.#cleanup();
    }

    static get observedAttributes() {
        return ['data-url', 'data-form-id', 'page-size', 'row-height'];
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;

        switch (name) {
            case 'data-url':
                this.#config.dataUrl = newValue;
                break;
            case 'data-form-id':
                this.#config.formId = newValue;
                this.#loadPreferences();
                break;
            case 'page-size':
                this.#config.pageSize = parseInt(newValue) || 50;
                break;
            case 'row-height':
                this.#config.rowHeight = parseInt(newValue) || 36;
                break;
        }
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Load data from server
     * @param {Object} options - { append: false, filters: {} }
     */
    async load(options = {}) {
        if (this.#state.isLoading) return;

        this.#state.isLoading = true;
        this.#showLoading();

        try {
            const params = new URLSearchParams();
            params.set('pageSize', this.#config.pageSize);

            if (options.append && this.#state.lastId) {
                params.set('lastId', this.#state.lastId);
            }

            if (this.#config.formId) {
                params.set('formId', this.#config.formId);
            }

            // Add filters
            const filters = options.filters || this.#state.filters;
            if (Object.keys(filters).length > 0) {
                params.set('filters', JSON.stringify(filters));
            }

            // Add sort
            if (this.#state.sort.column) {
                params.set('sortColumn', this.#state.sort.column);
                params.set('sortDirection', this.#state.sort.direction);
            }

            const url = `${this.#config.dataUrl}&${params}`;
            const response = await fetch(url);
            const data = await response.json();

            if (data.success) {
                if (options.append) {
                    this.#state.rows = [...this.#state.rows, ...data.rows];
                } else {
                    this.#state.rows = data.rows || [];
                    if (data.columns) {
                        this.#state.columns = this.#applyColumnPreferences(data.columns);
                    }
                }

                this.#state.totalCount = data.totalCount || this.#state.rows.length;
                this.#state.hasMore = data.hasMore || false;
                this.#state.lastId = data.lastId || null;

                this.#applyFiltersAndSort();
                this.#renderContent();

                // Dispatch data-loaded event
                this.#dispatchEvent('data-loaded', {
                    rowCount: this.#state.rows.length,
                    totalCount: this.#state.totalCount,
                    hasMore: this.#state.hasMore
                });
            } else {
                this.#showError(data.error || 'Failed to load data');
            }
        } catch (error) {
            console.error('LibDataTable load error:', error);
            this.#showError('Network error');
        } finally {
            this.#state.isLoading = false;
            this.#hideLoading();
        }
    }

    /**
     * Set data directly (without fetching)
     * @param {Array} rows - Array of row objects
     * @param {Array} columns - Optional column definitions
     */
    setData(rows, columns = null) {
        this.#state.rows = rows || [];
        if (columns) {
            this.#state.columns = this.#applyColumnPreferences(columns);
        }
        this.#state.totalCount = this.#state.rows.length;
        this.#state.hasMore = false;
        this.#applyFiltersAndSort();
        this.#renderContent();
    }

    /**
     * Set column definitions
     * @param {Array} columns - [{name, caption, type, width, sortable, filterable}]
     */
    setColumns(columns) {
        this.#state.columns = this.#applyColumnPreferences(columns);
        this.#renderHeader();
        this.#renderBody();
    }

    /**
     * Apply a filter
     * @param {string} column - Column name
     * @param {*} value - Filter value (null to clear)
     */
    setFilter(column, value) {
        if (value === null || value === undefined || value === '') {
            delete this.#state.filters[column];
        } else {
            this.#state.filters[column] = value;
        }
        this.#applyFiltersAndSort();
        this.#renderBody();
        this.#dispatchEvent('filter-change', { filters: this.#state.filters });
    }

    /**
     * Clear all filters
     */
    clearFilters() {
        this.#state.filters = {};
        this.#applyFiltersAndSort();
        this.#renderBody();
        this.#dispatchEvent('filter-change', { filters: {} });
    }

    /**
     * Set sort column
     * @param {string} column - Column name
     * @param {string} direction - 'asc' or 'desc'
     */
    setSort(column, direction = 'asc') {
        this.#state.sort = { column, direction };
        this.#applyFiltersAndSort();
        this.#renderBody();
        this.#updateSortIndicators();
        this.#dispatchEvent('sort-change', { sort: this.#state.sort });
    }

    /**
     * Get selected row IDs
     * @returns {Array}
     */
    getSelection() {
        return Array.from(this.#state.selection);
    }

    /**
     * Set selection
     * @param {Array} ids - Row IDs to select
     */
    setSelection(ids) {
        this.#state.selection = new Set(ids);
        this.#renderBody();
        this.#dispatchEvent('selection-change', { selection: this.getSelection() });
    }

    /**
     * Refresh data from server
     */
    refresh() {
        this.#state.lastId = null;
        this.load();
    }

    /**
     * Get current state (for debugging/persistence)
     */
    getState() {
        return {
            rowCount: this.#state.rows.length,
            filteredCount: this.#state.filteredRows.length,
            filters: { ...this.#state.filters },
            sort: { ...this.#state.sort },
            selection: this.getSelection(),
            density: this.#config.density
        };
    }

    /**
     * Set display density
     * @param {string} density - 'compact', 'normal', or 'comfortable'
     */
    setDensity(density) {
        if (!this.#densitySettings[density]) return;

        this.#setDensity(density, true);

        // Update button active states
        this.shadowRoot.querySelectorAll('.density-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.density === density);
        });

        this.#dispatchEvent('density-change', { density });
    }

    /**
     * Get current density
     * @returns {string}
     */
    getDensity() {
        return this.#config.density;
    }

    // =========================================================================
    // Private: Initialization
    // =========================================================================

    #parseAttributes() {
        this.#config.dataUrl = this.getAttribute('data-url');
        this.#config.formId = this.getAttribute('data-form-id');
        this.#config.pageSize = parseInt(this.getAttribute('page-size')) || 50;
        this.#config.sortable = this.hasAttribute('sortable');
        this.#config.filterable = this.hasAttribute('filterable');
        this.#config.resizable = this.hasAttribute('resizable');
        this.#config.reorderable = this.hasAttribute('reorderable');
        this.#config.selectable = this.hasAttribute('selectable');
        this.#config.virtualScroll = !this.hasAttribute('no-virtual-scroll');

        // Density: compact, normal, comfortable
        const density = this.getAttribute('density') || 'normal';
        this.#setDensity(density, false);
    }

    #setDensity(density, rerender = true) {
        if (!this.#densitySettings[density]) density = 'normal';
        this.#config.density = density;
        this.#config.rowHeight = this.#densitySettings[density].rowHeight;

        // Save to preferences
        if (this.#config.formId) {
            this.#preferences.density = density;
            this.#savePreferences();
        }

        if (rerender && this.#elements.container) {
            // Update CSS variable for padding
            this.#elements.container.style.setProperty('--cell-padding', this.#densitySettings[density].cellPadding);
            // Re-render body for new row heights
            this.#renderBody();
        }
    }

    #loadPreferences() {
        if (!this.#config.formId) return;

        try {
            const key = `lib_table_prefs_${this.#config.formId}`;
            const stored = localStorage.getItem(key);
            if (stored) {
                const prefs = JSON.parse(stored);
                this.#preferences = { ...this.#preferences, ...prefs };

                // Restore density from preferences
                if (prefs.density && this.#densitySettings[prefs.density]) {
                    this.#config.density = prefs.density;
                    this.#config.rowHeight = this.#densitySettings[prefs.density].rowHeight;
                }
            }
        } catch (e) {
            console.warn('LibDataTable: Failed to load preferences', e);
        }
    }

    #savePreferences() {
        if (!this.#config.formId) return;

        try {
            const key = `lib_table_prefs_${this.#config.formId}`;
            localStorage.setItem(key, JSON.stringify(this.#preferences));
        } catch (e) {
            console.warn('LibDataTable: Failed to save preferences', e);
        }
    }

    #applyColumnPreferences(columns) {
        // Apply stored widths
        columns.forEach(col => {
            if (this.#preferences.columnWidths[col.name]) {
                col.width = this.#preferences.columnWidths[col.name];
            }
        });

        // Apply stored order
        if (this.#preferences.columnOrder.length > 0) {
            const orderMap = {};
            this.#preferences.columnOrder.forEach((name, index) => {
                orderMap[name] = index;
            });

            columns.sort((a, b) => {
                const aIndex = orderMap[a.name] ?? 999;
                const bIndex = orderMap[b.name] ?? 999;
                return aIndex - bIndex;
            });
        }

        // Apply hidden columns
        columns.forEach(col => {
            col.hidden = this.#preferences.hiddenColumns.includes(col.name);
        });

        return columns;
    }

    // =========================================================================
    // Private: Rendering
    // =========================================================================

    #render() {
        const density = this.#config.density;
        this.shadowRoot.innerHTML = `
            <style>${this.#getStyles()}</style>
            <div class="lib-table-container" style="--cell-padding: ${this.#densitySettings[density].cellPadding}">
                <div class="lib-table-header"></div>
                <div class="lib-table-scroll-container">
                    <div class="lib-table-body"></div>
                </div>
                <div class="lib-table-footer">
                    <span class="lib-table-count"></span>
                    <div class="lib-table-density">
                        <button class="density-btn${density === 'compact' ? ' active' : ''}" data-density="compact" title="Compact">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><rect x="2" y="3" width="12" height="2"/><rect x="2" y="7" width="12" height="2"/><rect x="2" y="11" width="12" height="2"/></svg>
                        </button>
                        <button class="density-btn${density === 'normal' ? ' active' : ''}" data-density="normal" title="Normaal">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><rect x="2" y="2" width="12" height="3"/><rect x="2" y="7" width="12" height="3"/><rect x="2" y="12" width="12" height="3"/></svg>
                        </button>
                        <button class="density-btn${density === 'comfortable' ? ' active' : ''}" data-density="comfortable" title="Ruim">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><rect x="2" y="1" width="12" height="4"/><rect x="2" y="6" width="12" height="4"/><rect x="2" y="11" width="12" height="4"/></svg>
                        </button>
                    </div>
                    <span class="lib-table-loading">Laden...</span>
                </div>
            </div>
        `;

        this.#elements.container = this.shadowRoot.querySelector('.lib-table-container');
        this.#elements.header = this.shadowRoot.querySelector('.lib-table-header');
        this.#elements.scrollContainer = this.shadowRoot.querySelector('.lib-table-scroll-container');
        this.#elements.body = this.shadowRoot.querySelector('.lib-table-body');
        this.#elements.footer = this.shadowRoot.querySelector('.lib-table-footer');
        this.#elements.loadingIndicator = this.shadowRoot.querySelector('.lib-table-loading');

        // Bind density button events
        this.shadowRoot.querySelectorAll('.density-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const newDensity = btn.dataset.density;
                this.setDensity(newDensity);
            });
        });
    }

    #renderContent() {
        this.#renderHeader();
        this.#renderBody();
        this.#updateFooter();
    }

    #renderHeader() {
        const columns = this.#state.columns.filter(c => !c.hidden);
        if (columns.length === 0) return;

        let html = '<div class="lib-table-row lib-table-header-row">';

        // Selection checkbox column
        if (this.#config.selectable) {
            html += `<div class="lib-table-cell lib-table-cell-checkbox">
                <input type="checkbox" class="select-all">
            </div>`;
        }

        columns.forEach((col, index) => {
            const width = col.width ? `width:${col.width}px;min-width:${col.width}px;` : '';
            const sortable = this.#config.sortable && col.sortable !== false ? 'sortable' : '';
            const sortDir = this.#state.sort.column === col.name ? this.#state.sort.direction : '';
            const numericClass = this.#isNumericType(col.type) ? 'numeric' : '';

            html += `
                <div class="lib-table-cell lib-table-header-cell ${sortable} ${numericClass}"
                     data-field="${col.name}"
                     data-index="${index}"
                     style="${width}"
                     draggable="${this.#config.reorderable}">
                    <span class="lib-table-header-text">${this.#escapeHtml(col.caption || col.name)}</span>
                    ${sortable ? `<span class="lib-table-sort-icon" data-dir="${sortDir}"></span>` : ''}
                    ${this.#config.filterable && col.filterable !== false ? this.#renderFilterDropdown(col) : ''}
                    ${this.#config.resizable ? '<div class="lib-table-resize-handle"></div>' : ''}
                </div>
            `;
        });

        html += '</div>';
        this.#elements.header.innerHTML = html;
    }

    #renderFilterDropdown(col) {
        return `
            <div class="lib-table-filter-dropdown">
                <span class="lib-table-filter-icon"></span>
                <div class="lib-table-filter-content">
                    <input type="text" class="lib-table-filter-search" placeholder="Zoeken..." data-field="${col.name}">
                    <div class="lib-table-filter-options"></div>
                </div>
            </div>
        `;
    }

    #renderBody() {
        const rows = this.#state.filteredRows;
        const columns = this.#state.columns.filter(c => !c.hidden);

        if (rows.length === 0) {
            this.#elements.body.innerHTML = '<div class="lib-table-empty">Geen gegevens</div>';
            return;
        }

        if (this.#config.virtualScroll) {
            this.#renderVirtualBody(rows, columns);
        } else {
            this.#renderFullBody(rows, columns);
        }
    }

    #renderVirtualBody(rows, columns) {
        const containerHeight = this.#elements.scrollContainer.clientHeight || 400;
        const rowHeight = this.#config.rowHeight;
        const totalHeight = rows.length * rowHeight;
        const buffer = this.#config.bufferRows;

        // Calculate visible range
        const scrollTop = this.#elements.scrollContainer.scrollTop || 0;
        const startIndex = Math.max(0, Math.floor(scrollTop / rowHeight) - buffer);
        const endIndex = Math.min(rows.length, Math.ceil((scrollTop + containerHeight) / rowHeight) + buffer);

        this.#state.visibleRange = { start: startIndex, end: endIndex };

        // Create spacer for virtual scrolling
        let html = `<div class="lib-table-spacer" style="height:${startIndex * rowHeight}px"></div>`;

        // Render visible rows
        for (let i = startIndex; i < endIndex; i++) {
            html += this.#renderRow(rows[i], columns, i);
        }

        // Bottom spacer
        const bottomSpace = (rows.length - endIndex) * rowHeight;
        html += `<div class="lib-table-spacer" style="height:${bottomSpace}px"></div>`;

        this.#elements.body.innerHTML = html;
    }

    #renderFullBody(rows, columns) {
        let html = '';
        rows.forEach((row, index) => {
            html += this.#renderRow(row, columns, index);
        });
        this.#elements.body.innerHTML = html;
    }

    #renderRow(row, columns, index) {
        const rowId = row.id || row.ID || index;
        const isSelected = this.#state.selection.has(rowId);
        const selectedClass = isSelected ? 'selected' : '';

        let html = `<div class="lib-table-row ${selectedClass}" data-id="${rowId}" data-index="${index}">`;

        // Selection checkbox
        if (this.#config.selectable) {
            html += `<div class="lib-table-cell lib-table-cell-checkbox">
                <input type="checkbox" class="row-select" ${isSelected ? 'checked' : ''}>
            </div>`;
        }

        // Data cells
        columns.forEach(col => {
            const value = row[col.name] ?? '';
            const width = col.width ? `width:${col.width}px;min-width:${col.width}px;` : '';
            const cellContent = this.#formatCellValue(value, col);
            const numericClass = this.#isNumericType(col.type) ? 'numeric' : '';

            html += `<div class="lib-table-cell ${numericClass}" data-field="${col.name}" style="${width}">${cellContent}</div>`;
        });

        html += '</div>';
        return html;
    }

    #formatCellValue(value, column) {
        if (value === null || value === undefined) return '';

        const type = column.type || 'text';

        switch (type) {
            case 'boolean':
            case 'checkbox':
                const checked = value === true || value === 1 || value === '1' || value === -1;
                return `<lib-switch ${checked ? 'checked' : ''} disabled></lib-switch>`;

            case 'date':
                return this.#formatDate(value);

            case 'datetime':
                return this.#formatDateTime(value);

            case 'number':
            case 'decimal':
                return this.#formatNumber(value, column.decimals);

            case 'currency':
                return this.#formatCurrency(value);

            default:
                const strValue = String(value);
                // Truncate long values
                if (strValue.length > 100) {
                    return this.#escapeHtml(strValue.substring(0, 97) + '...');
                }
                return this.#escapeHtml(strValue);
        }
    }

    #isNumericType(type) {
        return ['number', 'decimal', 'currency', 'integer', 'float', 'int'].includes(type);
    }

    #updateFooter() {
        const count = this.#state.filteredRows.length;
        const total = this.#state.totalCount;
        const countEl = this.#elements.footer.querySelector('.lib-table-count');

        if (count === total) {
            countEl.textContent = `${count} items`;
        } else {
            countEl.textContent = `${count} van ${total} items`;
        }
    }

    #updateSortIndicators() {
        const headers = this.#elements.header.querySelectorAll('.lib-table-header-cell');
        headers.forEach(header => {
            const icon = header.querySelector('.lib-table-sort-icon');
            if (icon) {
                const field = header.dataset.field;
                if (field === this.#state.sort.column) {
                    icon.dataset.dir = this.#state.sort.direction;
                } else {
                    icon.dataset.dir = '';
                }
            }
        });
    }

    #showLoading() {
        if (this.#elements.loadingIndicator) {
            this.#elements.loadingIndicator.style.display = 'inline';
        }
    }

    #hideLoading() {
        if (this.#elements.loadingIndicator) {
            this.#elements.loadingIndicator.style.display = 'none';
        }
    }

    #showError(message) {
        this.#elements.body.innerHTML = `<div class="lib-table-error">${this.#escapeHtml(message)}</div>`;
    }

    // =========================================================================
    // Private: Data Processing
    // =========================================================================

    #applyFiltersAndSort() {
        let rows = [...this.#state.rows];

        // Apply filters
        const filters = this.#state.filters;
        if (Object.keys(filters).length > 0) {
            rows = rows.filter(row => {
                return Object.entries(filters).every(([column, filterValue]) => {
                    const cellValue = String(row[column] || '').toLowerCase();
                    const searchValue = String(filterValue).toLowerCase();
                    return cellValue.includes(searchValue);
                });
            });
        }

        // Apply sort
        const { column, direction } = this.#state.sort;
        if (column) {
            rows.sort((a, b) => {
                let aVal = a[column];
                let bVal = b[column];

                // Handle nulls
                if (aVal === null || aVal === undefined) aVal = '';
                if (bVal === null || bVal === undefined) bVal = '';

                // Numeric comparison
                if (typeof aVal === 'number' && typeof bVal === 'number') {
                    return direction === 'asc' ? aVal - bVal : bVal - aVal;
                }

                // String comparison
                aVal = String(aVal).toLowerCase();
                bVal = String(bVal).toLowerCase();

                if (aVal < bVal) return direction === 'asc' ? -1 : 1;
                if (aVal > bVal) return direction === 'asc' ? 1 : -1;
                return 0;
            });
        }

        this.#state.filteredRows = rows;
    }

    // =========================================================================
    // Private: Event Handling
    // =========================================================================

    #bindEvents() {
        // Scroll handler for virtual scrolling and infinite scroll
        this.#handlers.scroll = this.#throttle(() => {
            if (this.#config.virtualScroll) {
                this.#renderBody();
            }
            this.#checkInfiniteScroll();
        }, 16);
        this.#elements.scrollContainer.addEventListener('scroll', this.#handlers.scroll);

        // Header click (sort)
        this.#handlers.headerClick = (e) => this.#handleHeaderClick(e);
        this.#elements.header.addEventListener('click', this.#handlers.headerClick);

        // Body click (row selection)
        this.#handlers.bodyClick = (e) => this.#handleBodyClick(e);
        this.#elements.body.addEventListener('click', this.#handlers.bodyClick);

        // Resize handles
        if (this.#config.resizable) {
            this.#handlers.resizeStart = (e) => this.#handleResizeStart(e);
            this.#elements.header.addEventListener('mousedown', this.#handlers.resizeStart);
        }

        // Drag reorder
        if (this.#config.reorderable) {
            this.#handlers.dragStart = (e) => this.#handleDragStart(e);
            this.#handlers.dragOver = (e) => this.#handleDragOver(e);
            this.#handlers.drop = (e) => this.#handleDrop(e);
            this.#handlers.dragEnd = (e) => this.#handleDragEnd(e);

            this.#elements.header.addEventListener('dragstart', this.#handlers.dragStart);
            this.#elements.header.addEventListener('dragover', this.#handlers.dragOver);
            this.#elements.header.addEventListener('drop', this.#handlers.drop);
            this.#elements.header.addEventListener('dragend', this.#handlers.dragEnd);
        }

        // Filter input
        if (this.#config.filterable) {
            this.#handlers.filterInput = this.#debounce((e) => this.#handleFilterInput(e), 200);
            this.#elements.header.addEventListener('input', this.#handlers.filterInput);

            // Position filter dropdown when opened (to avoid clipping by overflow:hidden)
            this.#handlers.filterFocus = (e) => this.#handleFilterFocus(e);
            this.#elements.header.addEventListener('focusin', this.#handlers.filterFocus);
        }
    }

    #cleanup() {
        // Remove all event listeners
        if (this.#elements.scrollContainer) {
            this.#elements.scrollContainer.removeEventListener('scroll', this.#handlers.scroll);
        }
        if (this.#elements.header) {
            this.#elements.header.removeEventListener('click', this.#handlers.headerClick);
            this.#elements.header.removeEventListener('mousedown', this.#handlers.resizeStart);
            this.#elements.header.removeEventListener('dragstart', this.#handlers.dragStart);
            this.#elements.header.removeEventListener('dragover', this.#handlers.dragOver);
            this.#elements.header.removeEventListener('drop', this.#handlers.drop);
            this.#elements.header.removeEventListener('dragend', this.#handlers.dragEnd);
            this.#elements.header.removeEventListener('input', this.#handlers.filterInput);
            this.#elements.header.removeEventListener('focusin', this.#handlers.filterFocus);
        }
        if (this.#elements.body) {
            this.#elements.body.removeEventListener('click', this.#handlers.bodyClick);
        }

        // Clean up document-level listeners
        document.removeEventListener('mousemove', this.#handlers.resizeMove);
        document.removeEventListener('mouseup', this.#handlers.resizeEnd);
    }

    #handleHeaderClick(e) {
        const cell = e.target.closest('.lib-table-header-cell');
        if (!cell) return;

        // Ignore if clicking resize handle or filter
        if (e.target.closest('.lib-table-resize-handle') || e.target.closest('.lib-table-filter-dropdown')) {
            return;
        }

        if (!cell.classList.contains('sortable')) return;

        const column = cell.dataset.field;
        const currentSort = this.#state.sort;

        let direction = 'asc';
        if (currentSort.column === column && currentSort.direction === 'asc') {
            direction = 'desc';
        }

        this.setSort(column, direction);
    }

    #handleBodyClick(e) {
        const row = e.target.closest('.lib-table-row');
        if (!row) return;

        const rowId = row.dataset.id;

        // Handle checkbox click
        if (e.target.classList.contains('row-select')) {
            if (e.target.checked) {
                this.#state.selection.add(rowId);
            } else {
                this.#state.selection.delete(rowId);
            }
            this.#dispatchEvent('selection-change', { selection: this.getSelection() });
            return;
        }

        // Handle row click
        const rowData = this.#state.filteredRows.find(r => String(r.id || r.ID) === rowId);
        this.#dispatchEvent('row-click', { id: rowId, row: rowData, element: row });
    }

    #handleResizeStart(e) {
        const handle = e.target.closest('.lib-table-resize-handle');
        if (!handle) return;

        e.preventDefault();

        const cell = handle.closest('.lib-table-header-cell');
        const field = cell.dataset.field;

        this.#resizeState = {
            cell,
            field,
            startX: e.pageX,
            startWidth: cell.offsetWidth
        };

        this.#handlers.resizeMove = (e) => this.#handleResizeMove(e);
        this.#handlers.resizeEnd = (e) => this.#handleResizeEnd(e);

        document.addEventListener('mousemove', this.#handlers.resizeMove);
        document.addEventListener('mouseup', this.#handlers.resizeEnd);
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';

        // Disable filter dropdowns during resize
        this.#elements.container.classList.add('resizing');
    }

    #handleResizeMove(e) {
        if (!this.#resizeState) return;

        const diff = e.pageX - this.#resizeState.startX;
        const newWidth = Math.max(50, this.#resizeState.startWidth + diff);

        this.#resizeState.cell.style.width = `${newWidth}px`;
        this.#resizeState.cell.style.minWidth = `${newWidth}px`;

        // Update body cells
        const field = this.#resizeState.field;
        this.#elements.body.querySelectorAll(`.lib-table-cell[data-field="${field}"]`).forEach(cell => {
            cell.style.width = `${newWidth}px`;
            cell.style.minWidth = `${newWidth}px`;
        });
    }

    #handleResizeEnd(e) {
        if (!this.#resizeState) return;

        const field = this.#resizeState.field;
        const newWidth = this.#resizeState.cell.offsetWidth;

        // Save to preferences
        this.#preferences.columnWidths[field] = newWidth;
        this.#savePreferences();

        // Update column definition
        const col = this.#state.columns.find(c => c.name === field);
        if (col) col.width = newWidth;

        this.#resizeState = null;

        document.removeEventListener('mousemove', this.#handlers.resizeMove);
        document.removeEventListener('mouseup', this.#handlers.resizeEnd);
        document.body.style.cursor = '';
        document.body.style.userSelect = '';

        // Remove resizing class after a delay to prevent filter menu from opening
        setTimeout(() => {
            this.#elements.container.classList.remove('resizing');
        }, 150);

        this.#dispatchEvent('column-resize', { field, width: newWidth });
    }

    #handleDragStart(e) {
        const cell = e.target.closest('.lib-table-header-cell');
        if (!cell || e.target.closest('.lib-table-resize-handle')) {
            e.preventDefault();
            return;
        }

        this.#dragState = {
            field: cell.dataset.field,
            index: parseInt(cell.dataset.index)
        };

        cell.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', cell.dataset.field);
    }

    #handleDragOver(e) {
        e.preventDefault();
        const cell = e.target.closest('.lib-table-header-cell');
        if (cell && this.#dragState && cell.dataset.field !== this.#dragState.field) {
            // Clear other drag-over classes
            this.#elements.header.querySelectorAll('.drag-over').forEach(c => c.classList.remove('drag-over'));
            cell.classList.add('drag-over');
        }
    }

    #handleDrop(e) {
        e.preventDefault();
        const targetCell = e.target.closest('.lib-table-header-cell');
        if (!targetCell || !this.#dragState) return;

        targetCell.classList.remove('drag-over');

        const fromField = this.#dragState.field;
        const toField = targetCell.dataset.field;

        if (fromField === toField) return;

        // Reorder columns
        const columns = this.#state.columns;
        const fromIndex = columns.findIndex(c => c.name === fromField);
        const toIndex = columns.findIndex(c => c.name === toField);

        if (fromIndex !== -1 && toIndex !== -1) {
            const [moved] = columns.splice(fromIndex, 1);
            columns.splice(toIndex, 0, moved);

            // Save order to preferences
            this.#preferences.columnOrder = columns.map(c => c.name);
            this.#savePreferences();

            // Re-render
            this.#renderHeader();
            this.#renderBody();

            this.#dispatchEvent('column-reorder', { columns: this.#preferences.columnOrder });
        }
    }

    #handleDragEnd(e) {
        this.#elements.header.querySelectorAll('.dragging, .drag-over').forEach(c => {
            c.classList.remove('dragging', 'drag-over');
        });
        this.#dragState = null;
    }

    #handleFilterInput(e) {
        const input = e.target.closest('.lib-table-filter-search');
        if (!input) return;

        const field = input.dataset.field;
        const value = input.value.trim();

        this.setFilter(field, value || null);
    }

    #handleFilterFocus(e) {
        const dropdown = e.target.closest('.lib-table-filter-dropdown');
        if (!dropdown) return;

        const content = dropdown.querySelector('.lib-table-filter-content');
        if (!content) return;

        // Position the dropdown aligned to the th cell
        const th = dropdown.closest('th') || dropdown.parentElement;
        const thRect = th.getBoundingClientRect();

        // Calculate position
        let top = thRect.bottom + 4;
        let left = thRect.left;

        // Ensure it doesn't go off-screen right
        const contentWidth = 200; // min-width from CSS
        if (left + contentWidth > window.innerWidth - 10) {
            left = window.innerWidth - contentWidth - 10;
        }

        // Ensure it doesn't go off-screen bottom
        const contentHeight = 200; // approximate height
        if (top + contentHeight > window.innerHeight - 10) {
            top = thRect.top - contentHeight - 4;
        }

        content.style.top = top + 'px';
        content.style.left = left + 'px';
    }

    #checkInfiniteScroll() {
        if (this.#state.isLoading || !this.#state.hasMore) return;

        const container = this.#elements.scrollContainer;
        const scrollBottom = container.scrollTop + container.clientHeight;
        const scrollHeight = container.scrollHeight;

        if (scrollBottom >= scrollHeight - 200) {
            this.load({ append: true });
        }
    }

    // =========================================================================
    // Private: Utilities
    // =========================================================================

    #escapeHtml(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    #formatDate(value) {
        if (!value) return '';
        try {
            const date = new Date(value);
            // Year 1899 indicates a time-only field (MS Access stores times this way)
            if (date.getFullYear() === 1899) {
                return date.toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });
            }
            return date.toLocaleDateString('nl-NL');
        } catch {
            return value;
        }
    }

    #formatDateTime(value) {
        if (!value) return '';
        try {
            const date = new Date(value);
            // Year 1899 indicates a time-only field (MS Access stores times this way)
            if (date.getFullYear() === 1899) {
                return date.toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });
            }
            return date.toLocaleString('nl-NL');
        } catch {
            return value;
        }
    }

    #formatNumber(value, decimals = 2) {
        if (value === null || value === undefined) return '';
        return Number(value).toLocaleString('nl-NL', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    #formatCurrency(value) {
        if (value === null || value === undefined) return '';
        return Number(value).toLocaleString('nl-NL', {
            style: 'currency',
            currency: 'EUR'
        });
    }

    #throttle(fn, wait) {
        let lastTime = 0;
        return (...args) => {
            const now = Date.now();
            if (now - lastTime >= wait) {
                lastTime = now;
                fn.apply(this, args);
            }
        };
    }

    #debounce(fn, wait) {
        let timeout;
        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    #dispatchEvent(name, detail) {
        this.dispatchEvent(new CustomEvent(name, {
            detail,
            bubbles: true,
            composed: true
        }));
    }

    // =========================================================================
    // Styles
    // =========================================================================

    #getStyles() {
        return `
            :host {
                display: block;
                height: 100%;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                font-size: var(--font-size);
                --lib-table-border: #ddd;
                --lib-table-header-bg: #f5f5f5;
                --lib-table-row-hover: #f0f7ff;
                --lib-table-row-selected: #e3f2fd;
                --lib-table-row-even: #fafafa;
                --lib-table-primary: #007bff;
            }

            .lib-table-container {
                display: flex;
                flex-direction: column;
                height: 100%;
                border: 1px solid var(--lib-table-border);
                border-radius: 4px;
                overflow: hidden;
                background: #fff;
            }

            .lib-table-header {
                flex-shrink: 0;
                background: var(--lib-table-header-bg);
                border-bottom: 2px solid var(--lib-table-border);
            }

            .lib-table-scroll-container {
                flex: 1;
                overflow: auto;
                position: relative;
            }

            .lib-table-body {
                position: relative;
            }

            .lib-table-footer {
                flex-shrink: 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 12px;
                background: var(--lib-table-header-bg);
                border-top: 1px solid var(--lib-table-border);
                font-size: var(--font-size-sm);
                color: #666;
            }

            .lib-table-loading {
                display: none;
                color: var(--lib-table-primary);
            }

            .lib-table-density {
                display: flex;
                gap: 2px;
            }

            .density-btn {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 28px;
                height: 24px;
                padding: 0;
                border: 1px solid var(--lib-table-border);
                border-radius: 4px;
                background: #fff;
                color: #666;
                cursor: pointer;
                transition: all 0.15s ease;
            }

            .density-btn:hover {
                border-color: var(--lib-table-primary);
                color: var(--lib-table-primary);
            }

            .density-btn.active {
                background: var(--lib-table-primary);
                border-color: var(--lib-table-primary);
                color: #fff;
            }

            .lib-table-row {
                display: flex;
                border-bottom: 1px solid #eee;
            }

            .lib-table-row:nth-child(even) {
                background: var(--lib-table-row-even);
            }

            .lib-table-row:hover {
                background: var(--lib-table-row-hover);
            }

            .lib-table-row.selected {
                background: var(--lib-table-row-selected);
            }

            .lib-table-header-row {
                background: var(--lib-table-header-bg);
            }

            .lib-table-header-row:hover {
                background: var(--lib-table-header-bg);
            }

            .lib-table-cell {
                flex: 1;
                padding: var(--cell-padding, 8px 12px);
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                border-right: 1px solid #eee;
                min-width: 80px;
            }

            .lib-table-cell:last-child {
                border-right: none;
            }

            /* Numeric columns: right-align with monospace font */
            .lib-table-cell.numeric {
                text-align: right;
                font-family: "SF Mono", "Monaco", "Inconsolata", "Roboto Mono", monospace;
                font-variant-numeric: tabular-nums;
            }

            .lib-table-cell-checkbox {
                flex: 0 0 40px;
                min-width: 40px;
                text-align: center;
            }

            .lib-table-header-cell {
                position: relative;
                font-weight: 600;
                color: #333;
                cursor: default;
                user-select: none;
            }

            .lib-table-header-cell.sortable {
                cursor: pointer;
            }

            .lib-table-header-cell.sortable:hover {
                background: #e8e8e8;
            }

            .lib-table-header-cell.dragging {
                opacity: 0.5;
            }

            .lib-table-header-cell.drag-over {
                box-shadow: inset -3px 0 0 var(--lib-table-primary);
            }

            .lib-table-header-cell[draggable="true"] {
                cursor: grab;
            }

            .lib-table-header-text {
                display: inline-block;
                max-width: calc(100% - 40px);
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .lib-table-sort-icon {
                display: inline-block;
                width: 16px;
                margin-left: 4px;
                vertical-align: middle;
            }

            .lib-table-sort-icon[data-dir="asc"]::after {
                content: "▲";
                font-size: var(--font-size-2xs);
                color: var(--lib-table-primary);
            }

            .lib-table-sort-icon[data-dir="desc"]::after {
                content: "▼";
                font-size: var(--font-size-2xs);
                color: var(--lib-table-primary);
            }

            .lib-table-resize-handle {
                position: absolute;
                right: 0;
                top: 0;
                bottom: 0;
                width: 5px;
                cursor: col-resize;
                background: transparent;
            }

            .lib-table-resize-handle:hover {
                background: var(--lib-table-primary);
                opacity: 0.3;
            }

            .lib-table-filter-dropdown {
                display: inline-block;
                position: relative;
                vertical-align: middle;
                margin-left: 4px;
            }

            .lib-table-filter-icon {
                display: inline-block;
                width: 14px;
                height: 14px;
                cursor: pointer;
                opacity: 0.5;
            }

            .lib-table-filter-icon::after {
                content: "▼";
                font-size: 8px;
            }

            .lib-table-filter-icon:hover {
                opacity: 1;
            }

            .lib-table-filter-content {
                display: none;
                position: fixed;
                z-index: 10000;
                background: #fff;
                border: 1px solid var(--lib-table-border);
                border-radius: 4px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                min-width: 200px;
                padding: 8px;
            }

            .lib-table-filter-dropdown:focus-within .lib-table-filter-content {
                display: block;
            }

            /* Disable filter dropdowns during resize */
            .lib-table-container.resizing .lib-table-filter-dropdown {
                pointer-events: none;
            }

            .lib-table-container.resizing .lib-table-filter-content {
                display: none !important;
            }

            .lib-table-filter-search {
                width: 100%;
                padding: 6px 8px;
                border: 1px solid var(--lib-table-border);
                border-radius: 4px;
                font-size: var(--font-size-sm);
            }

            .lib-table-empty,
            .lib-table-error {
                padding: 40px;
                text-align: center;
                color: #666;
            }

            .lib-table-error {
                color: #c00;
            }

            .lib-table-spacer {
                pointer-events: none;
            }

            /* Dark mode support */
            @media (prefers-color-scheme: dark) {
                :host {
                    --lib-table-border: #444;
                    --lib-table-header-bg: #2a2a2a;
                    --lib-table-row-hover: #333;
                    --lib-table-row-selected: #1a3a5c;
                    --lib-table-row-even: #252525;
                }

                .lib-table-container {
                    background: #1e1e1e;
                    color: #ddd;
                }

                .lib-table-header-cell {
                    color: #eee;
                }

                .lib-table-header-cell.sortable:hover {
                    background: #333;
                }

                .lib-table-filter-content {
                    background: #2a2a2a;
                }
            }
        `;
    }
}

// Register the JSON data table component
customElements.define('lib-data-table', LibDataTable);

} // end guard


// =============================================================================
// LibTable - HTML Table Wrapping Mode
// =============================================================================
// This component wraps existing HTML tables with filtering, sorting, and export
// functionality. It provides consistent styling and features for all tables.
//
// Usage:
//   <lib-table>
//     <table>
//       <thead><tr><th>Column</th></tr></thead>
//       <tbody><tr><td>Data</td></tr></tbody>
//     </table>
//   </lib-table>
//
// Attributes:
//   - export="n" - Disable export menu
//   - name="tableName" - Name for exported files
//   - data-type="date|number|text" on TH - Column type for sorting
//   - data-no-sort on TH - Disable sorting for column
//   - data-no-filter on TH - Disable filter dropdown for column (no icon shown)
//   - data-filter="N" on TH - Alias for data-no-filter (backward compatibility)
//   - resizable - Enable column resizing (requires CmaTableColumnManager)
//   - reorderable - Enable column reordering (requires CmaTableColumnManager)
//   - storage-key="name" - Key for localStorage persistence of column preferences

// Guard against double registration
if (!customElements.get('lib-table')) {

class LibTable extends HTMLElement {
    constructor() {
        super();
        this._initialized = false;
        this._filterMenus = [];
        this._rows = [];
        this._table = null;
        this._documentClickHandler = null;
        this._columnManager = null;
    }

    connectedCallback() {
        LibTable._ensureStyles();
        LibTable._ensureDependencies();
        // Wait for slotted content
        requestAnimationFrame(() => this._initialize());
    }

    /**
     * Auto-load web component dependencies (lib-datepicker, lib-switch) if not already registered.
     * These are used by date range filters and checkbox column rendering.
     */
    static _ensureDependencies() {
        if (LibTable._depsLoaded) return;
        LibTable._depsLoaded = true;

        const scriptTag = document.querySelector('script[src*="lib-table"]');
        const isMin = scriptTag && scriptTag.src.includes('.min.');
        const basePath = scriptTag ? scriptTag.src.replace(/lib-table[^/]*\.js.*$/, '') : '/library/webcomponents/';

        const deps = ['lib-datepicker', 'lib-switch', 'lib-timepicker'];
        deps.forEach(name => {
            if (customElements.get(name)) return;
            if (document.querySelector('script[src*="' + name + '"]')) return;
            const s = document.createElement('script');
            s.src = basePath + name + (isMin ? '.min' : '') + '.js';
            document.head.appendChild(s);
        });
    }

    /**
     * Auto-inject lib-table.css into <head> if not already present.
     * Detects .min. from own script tag to load minified version.
     */
    static _ensureStyles() {
        if (LibTable._stylesInjected) return;
        LibTable._stylesInjected = true;

        const id = 'lib-table-styles';
        if (document.getElementById(id)) return;

        // Check if lib-table.css is already loaded via a CSS bundle (e.g. minify.php)
        for (const link of document.querySelectorAll('link[rel="stylesheet"]')) {
            if (link.href && link.href.includes('lib-table')) return;
        }

        // Find the standalone lib-table.js script tag (not a bundled/minified URL)
        const scriptTag = document.querySelector('script[src$="lib-table.js"], script[src$="lib-table.min.js"]');
        const isMin = scriptTag && scriptTag.src.includes('.min.');
        const basePath = scriptTag ? scriptTag.src.replace(/lib-table[^/]*\.js.*$/, '') : '/library/webcomponents/';
        const version = (typeof Application !== 'undefined' && Application.asset_version) ? '?v=' + Application.asset_version : '';

        const link = document.createElement('link');
        link.id = id;
        link.rel = 'stylesheet';
        link.href = basePath + 'lib-table' + (isMin ? '.min' : '') + '.css' + version;
        document.head.appendChild(link);
    }

    disconnectedCallback() {
        if (this._documentClickHandler) {
            document.removeEventListener('click', this._documentClickHandler);
        }
        if (this._columnManager && typeof this._columnManager.destroy === 'function') {
            this._columnManager.destroy();
            this._columnManager = null;
        }
    }

    _initialize() {
        if (this._initialized) return;

        const table = this.querySelector('table');
        if (!table) {
            requestAnimationFrame(() => this._initialize());
            return;
        }

        this._table = table;
        this._initialized = true;

        // Ensure table has an ID
        if (!table.id) {
            table.id = 'lib_table_' + Math.round(Math.random() * 65000);
        }

        // Add listtable class for generic table styling
        table.classList.add('listtable');

        // Add cellspacing attribute (needed for consistent styling)
        table.setAttribute('cellspacing', '0');
        table.setAttribute('cellpadding', '0');

        // Add listheader class to thead tr (for consistent styling)
        const theadRow = table.querySelector('thead tr');
        if (theadRow) {
            theadRow.classList.add('listheader');
        }

        // Initialize filtering on each column
        this._initializeFilters();

        // Initialize export menu
        this._initializeExportMenu();

        // Update row striping
        this._updateView();

        // Document click handler for closing menus
        this._documentClickHandler = (e) => {
            if (!e.target.closest('.dropdown-filter-content') && !e.target.closest('.dropdown-filter-icon')) {
                this._closeAllFilterMenus();
            }
            if (!e.target.closest('.menutrigger')) {
                this._closeExportMenu();
            }
        };
        document.addEventListener('click', this._documentClickHandler);

        // Initialize column manager for resize/reorder if enabled
        this._initializeColumnManager();
    }

    /**
     * Initialize column manager for resize and reorder functionality
     * Requires CmaTableColumnManager to be loaded
     */
    _initializeColumnManager() {
        const resizable = this.hasAttribute('resizable');
        const reorderable = this.hasAttribute('reorderable');

        if (!resizable && !reorderable) return;

        if (typeof CmaTableColumnManager === 'undefined') {
            // CmaTableColumnManager not loaded - skip silently
            return;
        }

        // Get storage key for preferences persistence
        const storageKey = this.getAttribute('storage-key') || this.id || this._table.id;

        this._columnManager = new CmaTableColumnManager(this._table, storageKey, {
            resizable: resizable,
            reorderable: reorderable
        });
    }

    _initializeFilters() {
        // Skip if filters already initialized (e.g., from PHP-generated table)
        if (this._table.querySelector('thead .dropdown-filter-dropdown')) return;

        // Skip columns with data-filter="N" or data-no-filter attribute
        const ths = this._table.querySelectorAll('thead th:not([data-filter="N"]):not([data-no-filter])');
        this._rows = Array.from(this._table.querySelectorAll('tbody tr'));

        ths.forEach((th, index) => {
            const column = th.cellIndex;
            const filterMenu = this._createFilterMenu(th, column, index);
            this._filterMenus.push(filterMenu);
        });
    }

    _createFilterMenu(th, column, index) {
        const tds = Array.from(this._table.querySelectorAll(`tbody tr td:nth-child(${column + 1})`));
        const isDateColumn = th.dataset.type === 'date' || th.dataset.type === 'datetime';
        const isTimeColumn = th.dataset.type === 'time';
        const isNumberColumn = th.dataset.type === 'number' || th.dataset.type === 'decimal' || th.dataset.type === 'currency' || th.dataset.type === 'integer';
        const noSort = th.hasAttribute('data-no-sort');
        const noFilter = th.hasAttribute('data-no-filter');
        const noSearch = th.hasAttribute('data-no-search');
        const MAX_FILTER_LENGTH = 500;

        const menu = {
            th,
            column,
            index,
            tds,
            isDateColumn,
            isTimeColumn,
            isNumberColumn,
            inputs: [],
            selectAllCheckbox: null,
            searchFilter: null,
            dateFromInput: null,
            dateToInput: null,
            timeFromInput: null,
            timeToInput: null,
            numberFromInput: null,
            numberToInput: null
        };

        // Wrap header content in clicker inside a nowrap wrapper
        const originalContent = th.innerHTML;
        th.innerHTML = '';

        const wrapper = document.createElement('div');
        wrapper.className = 'th-header-wrapper';

        const clicker = document.createElement('span');
        clicker.className = 'clicker';
        clicker.innerHTML = originalContent;
        // Tooltip is set conditionally below - only when text is truncated
        wrapper.appendChild(clicker);

        // Create dropdown
        const dropdown = document.createElement('div');
        dropdown.className = 'dropdown-filter-dropdown';

        // Create icon using Linearicons
        const icon = document.createElement('span');
        icon.className = 'dropdown-filter-icon';
        icon.innerHTML = '<span class="lnr lnr-chevron-down"></span>';
        dropdown.appendChild(icon);

        // Create content
        const content = document.createElement('div');
        content.className = 'dropdown-filter-content';

        // Add sort options
        if (!noSort) {
            const sortAZ = this._createSortOption('A - Z', 'a---z', column, index);
            const sortZA = this._createSortOption('Z - A', 'z---a', column, index);
            content.appendChild(sortAZ);
            content.appendChild(sortZA);
        }

        // Add filter content
        if (!noFilter) {
            if (isDateColumn) {
                const dateRange = this._createDateRangeFilter(column, index, menu);
                content.appendChild(dateRange);
            } else if (isTimeColumn) {
                const timeRange = this._createTimeRangeFilter(column, index, menu);
                content.appendChild(timeRange);
            } else if (isNumberColumn) {
                const numberRange = this._createNumberRangeFilter(column, index, menu);
                content.appendChild(numberRange);
            } else {
                // Check if table is in continuous scrolling mode
                const isContinuousMode = this._table.hasAttribute('data-continuous') ||
                                        this._table.dataset.continuous === 'true' ||
                                        tds.length > MAX_FILTER_LENGTH;

                if (!isContinuousMode) {
                    // Get unique values (trimmed for consistency)
                    const values = [...new Set(tds.map(td => this._getCellValue(td).trim()))].sort((a, b) => {
                        const A = a.toLowerCase();
                        const B = b.toLowerCase();
                        if (!isNaN(Number(A)) && !isNaN(Number(B))) {
                            return Number(A) - Number(B);
                        }
                        return A.localeCompare(B);
                    });

                    // Skip checkbox filter if too many unique values (> 30)
                    // This prevents performance issues and unusable UI
                    const MAX_CHECKBOX_VALUES = 30;
                    if (values.length > MAX_CHECKBOX_VALUES) {
                        // Use direct text filtering mode (no checkboxes)
                        menu.isTextFilterMode = true;
                        if (!noSearch) {
                            const search = this._createTextFilterInput(column, index, menu);
                            content.appendChild(search);
                        }
                    } else {
                        if (!noSearch) {
                            const search = this._createSearchInput(column, index, menu);
                            content.appendChild(search);
                        }

                        const container = document.createElement('div');
                        container.className = 'checkbox-container';

                        // Select all
                        const selectAll = this._createCheckbox('Alles', 'select-all', column, index, true);
                        menu.selectAllCheckbox = selectAll.querySelector('input');
                        container.appendChild(selectAll);

                        // Individual items
                        values.forEach(value => {
                            const item = this._createCheckbox(value, 'item', column, index, true);
                            menu.inputs.push(item.querySelector('input'));
                            container.appendChild(item);
                        });

                        content.appendChild(container);
                    }
                }
                // In continuous mode, the checkbox-container is left blank (no filter values listed)
            }
        }

        // Add close button
        const close = document.createElement('div');
        close.className = 'close';
        close.addEventListener('click', (e) => {
            e.stopPropagation();
            content.style.display = 'none';
        });
        content.appendChild(close);

        dropdown.appendChild(content);

        // For numeric columns, place dropdown before the clicker (left side)
        if (isNumberColumn) {
            wrapper.insertBefore(dropdown, clicker);
        } else {
            wrapper.appendChild(dropdown);
        }

        // Append wrapper to th
        th.appendChild(wrapper);

        // Only show tooltip when text is truncated
        requestAnimationFrame(() => {
            if (clicker.scrollWidth > clicker.clientWidth) {
                th.classList.add('truncated');
                th.dataset.tooltip = clicker.textContent.trim().replace(/_/g, ' ');
            }
        });

        // Click handlers
        th.addEventListener('click', (e) => {
            if (e.target.closest('.close') || e.target.closest('.dropdown-filter-content')) {
                return;
            }
            e.stopPropagation();

            // Toggle this menu (capture state before closing others)
            const isVisible = content.style.display === 'block';

            // Close other menus
            this._closeAllFilterMenus();
            this._closeExportMenu();

            if (isVisible) return; // Was open, now closed — done

            // Open this menu
            content.style.display = 'block';
            {
                // Position the content using fixed positioning aligned to the th
                const thRect = th.getBoundingClientRect();
                const spaceBelow = window.innerHeight - thRect.bottom;
                const spaceAbove = thRect.top;
                const minHeight = 200; // Minimum useful dropdown height
                const contentWidth = 220; // Approximate dropdown width

                // Align left edge to th left edge
                let left = thRect.left;
                if (left + contentWidth > window.innerWidth - 10) {
                    left = window.innerWidth - contentWidth - 10;
                }
                content.style.left = left + 'px';

                // Flip upward if not enough space below but enough above
                if (spaceBelow < minHeight && spaceAbove > spaceBelow) {
                    content.classList.add('flip-up');
                    content.style.top = 'auto';
                    content.style.bottom = (window.innerHeight - thRect.top + 4) + 'px';
                    content.style.maxHeight = Math.max(150, spaceAbove - 20) + 'px';
                } else {
                    content.classList.remove('flip-up');
                    content.style.top = (thRect.bottom + 2) + 'px';
                    content.style.bottom = 'auto';
                    content.style.maxHeight = Math.max(150, spaceBelow - 20) + 'px';
                }
            }
        });

        // Bind filter events
        this._bindFilterEvents(menu, content);

        return menu;
    }

    _createSortOption(label, className, column, index) {
        const div = document.createElement('div');
        div.className = 'dropdown-filter-sort';
        const span = document.createElement('span');
        span.className = className;
        span.dataset.column = column;
        span.dataset.index = index;
        span.textContent = label;
        span.addEventListener('click', () => this._sort(column, className));
        div.appendChild(span);
        return div;
    }

    _createSearchInput(column, index, menu) {
        const div = document.createElement('div');
        div.className = 'dropdown-filter-search';
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'dropdown-filter-menu-search form-control';
        input.placeholder = 'Zoek';
        input.dataset.column = column;
        input.dataset.index = index;
        menu.searchFilter = input;

        input.addEventListener('keyup', () => {
            this._searchToggle(menu, input.value);
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === 'Escape') {
                div.closest('.dropdown-filter-content').style.display = 'none';
            }
        });

        div.appendChild(input);
        return div;
    }

    /**
     * Create a text filter input for columns with too many unique values (> 30)
     * This directly filters the table by text content instead of using checkboxes
     */
    _createTextFilterInput(column, index, menu) {
        const div = document.createElement('div');
        div.className = 'dropdown-filter-search';
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'dropdown-filter-menu-search form-control';
        input.placeholder = 'Filter tekst...';
        input.dataset.column = column;
        input.dataset.index = index;
        menu.textFilter = input;

        // Filter on input change
        input.addEventListener('input', () => {
            this._updateRowVisibility();
        });

        // Close on Enter/Escape
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === 'Escape') {
                div.closest('.dropdown-filter-content').style.display = 'none';
            }
        });

        div.appendChild(input);
        return div;
    }

    _createDateRangeFilter(column, index, menu) {
        const container = document.createElement('div');
        container.className = 'date-range-filter';

        const vanLabel = document.createElement('label');
        vanLabel.textContent = 'Van:';
        const vanInput = document.createElement('lib-datepicker');
        vanInput.setAttribute('placeholder', 'dd-mm-jjjj');
        vanInput.setAttribute('format', 'dd-mm-yyyy');
        vanInput.setAttribute('locale', 'nl');
        menu.dateFromInput = vanInput;

        const totLabel = document.createElement('label');
        totLabel.textContent = 'Tot:';
        const totInput = document.createElement('lib-datepicker');
        totInput.setAttribute('placeholder', 'dd-mm-jjjj');
        totInput.setAttribute('format', 'dd-mm-yyyy');
        totInput.setAttribute('locale', 'nl');
        menu.dateToInput = totInput;

        container.appendChild(vanLabel);
        container.appendChild(vanInput);
        container.appendChild(totLabel);
        container.appendChild(totInput);

        // Bind change events
        vanInput.addEventListener('change', () => this._updateRowVisibility());
        totInput.addEventListener('change', () => this._updateRowVisibility());

        return container;
    }

    _createTimeRangeFilter(column, index, menu) {
        const container = document.createElement('div');
        container.className = 'time-range-filter';

        const vanLabel = document.createElement('label');
        vanLabel.textContent = 'Van:';
        const vanInput = document.createElement('lib-timepicker');
        vanInput.setAttribute('placeholder', 'uu:mm');
        menu.timeFromInput = vanInput;

        const totLabel = document.createElement('label');
        totLabel.textContent = 'Tot:';
        const totInput = document.createElement('lib-timepicker');
        totInput.setAttribute('placeholder', 'uu:mm');
        menu.timeToInput = totInput;

        container.appendChild(vanLabel);
        container.appendChild(vanInput);
        container.appendChild(totLabel);
        container.appendChild(totInput);

        vanInput.addEventListener('change', () => this._updateRowVisibility());
        totInput.addEventListener('change', () => this._updateRowVisibility());

        return container;
    }

    _createNumberRangeFilter(column, index, menu) {
        const container = document.createElement('div');
        container.className = 'number-range-filter';

        const vanLabel = document.createElement('label');
        vanLabel.textContent = 'Van:';
        const vanInput = document.createElement('input');
        vanInput.type = 'number';
        vanInput.className = 'number-range-input';
        vanInput.placeholder = 'min';
        vanInput.step = 'any';
        menu.numberFromInput = vanInput;

        const totLabel = document.createElement('label');
        totLabel.textContent = 'Tot:';
        const totInput = document.createElement('input');
        totInput.type = 'number';
        totInput.className = 'number-range-input';
        totInput.placeholder = 'max';
        totInput.step = 'any';
        menu.numberToInput = totInput;

        container.appendChild(vanLabel);
        container.appendChild(vanInput);
        container.appendChild(totLabel);
        container.appendChild(totInput);

        // Bind change events
        vanInput.addEventListener('input', () => this._updateRowVisibility());
        totInput.addEventListener('input', () => this._updateRowVisibility());
        vanInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === 'Escape') {
                container.closest('.dropdown-filter-content').style.display = 'none';
            }
        });
        totInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === 'Escape') {
                container.closest('.dropdown-filter-content').style.display = 'none';
            }
        });

        return container;
    }

    _createCheckbox(value, type, column, index, checked) {
        const div = document.createElement('div');
        div.className = 'dropdown-filter-item';

        const id = 'filter_' + Math.round(Math.random() * 65000);
        const input = document.createElement('input');
        input.type = 'checkbox';
        input.id = id;
        input.value = value.trim().replace(/ +(?= )/g, '');
        input.checked = checked;
        input.className = 'dropdown-filter-menu-item ' + type;
        input.dataset.column = column;
        input.dataset.index = index;

        const label = document.createElement('label');
        label.htmlFor = id;
        label.textContent = value;

        div.appendChild(input);
        div.appendChild(label);

        return div;
    }

    _bindFilterEvents(menu, content) {
        // Checkbox change
        content.querySelectorAll('.dropdown-filter-menu-item.item').forEach(input => {
            input.addEventListener('change', () => {
                this._updateSelectAll(menu);
                this._updateRowVisibility();
            });
        });

        // Select all change
        const selectAll = content.querySelector('.dropdown-filter-menu-item.select-all');
        if (selectAll) {
            selectAll.addEventListener('change', () => {
                this._toggleAll(menu, selectAll.checked);
                this._updateRowVisibility();
            });
        }
    }

    _searchToggle(menu, value) {
        if (menu.selectAllCheckbox) {
            menu.selectAllCheckbox.checked = false;
        }

        if (!value) {
            this._toggleAll(menu, true);
            if (menu.selectAllCheckbox) {
                menu.selectAllCheckbox.checked = true;
            }
            this._updateRowVisibility();
            return;
        }

        this._toggleAll(menu, false);
        menu.inputs.filter(input =>
            input.value.toLowerCase().includes(value.toLowerCase())
        ).forEach(input => {
            input.checked = true;
        });

        this._updateRowVisibility();
    }

    _toggleAll(menu, checked) {
        menu.inputs.forEach(input => {
            input.checked = checked;
        });
    }

    _updateSelectAll(menu) {
        // Clear search filter if it exists
        if (menu.searchFilter) {
            menu.searchFilter.value = '';
        }
        // Update select-all checkbox state
        if (menu.selectAllCheckbox) {
            menu.selectAllCheckbox.checked = menu.inputs.every(input => input.checked);
        }
    }

    _sort(column, order) {
        const flip = order === 'z---a' ? -1 : 1;
        const tbody = this._table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));

        rows.sort((a, b) => {
            const aCell = a.children[column];
            const bCell = b.children[column];
            let A = aCell ? this._getCellValue(aCell).toUpperCase().replace(/[%€£$]/g, '') : '';
            let B = bCell ? this._getCellValue(bCell).toUpperCase().replace(/[%€£$]/g, '') : '';

            // Use data-sort attribute if available
            if (aCell?.dataset?.sort) A = aCell.dataset.sort;
            if (bCell?.dataset?.sort) B = bCell.dataset.sort;

            // Numeric comparison
            if (!isNaN(Number(A)) && !isNaN(Number(B))) {
                return (Number(A) - Number(B)) * flip;
            }

            // Date comparison (DD-MM-YYYY format)
            if (A.match(/(\d{1,2})-(\d{1,2})-(\d{2,4})/) && B.match(/(\d{1,2})-(\d{1,2})-(\d{2,4})/)) {
                const dateA = this._sortableDate(A);
                const dateB = this._sortableDate(B);
                if (dateA < dateB) return -1 * flip;
                if (dateA > dateB) return 1 * flip;
                return 0;
            }

            // String comparison
            return A.localeCompare(B) * flip;
        });

        rows.forEach(row => tbody.appendChild(row));

        // Update active state
        this._table.querySelectorAll('.dropdown-filter-sort span.active').forEach(el => {
            el.classList.remove('active');
        });
        this._table.querySelectorAll(`.dropdown-filter-sort span.${order}`).forEach(el => {
            if (parseInt(el.dataset.column) === column) {
                el.classList.add('active');
            }
        });

        this._table.querySelectorAll('.dropdown-filter-icon').forEach(el => {
            el.classList.remove('sorted');
        });
        const th = this._table.querySelector(`thead th:nth-child(${column + 1})`);
        if (th) {
            const icon = th.querySelector('.dropdown-filter-icon');
            if (icon) icon.classList.add('sorted');
        }

        this._updateView();
    }

    _sortableDate(dateStr) {
        if (!dateStr || dateStr.length < 8) return '';

        // Handle YYYY-MM-DD format (ISO, from lib-datepicker)
        if (dateStr.match(/^\d{4}-\d{2}-\d{2}$/)) {
            // Already sortable format, just remove dashes
            return dateStr.replace(/-/g, '');
        }

        // Handle DD-MM-YYYY format (Dutch display format)
        const parts = dateStr.split('-');
        if (parts.length === 3 && parts[0].length <= 2) {
            return parts[2] + parts[1].padStart(2, '0') + parts[0].padStart(2, '0');
        }

        return dateStr;
    }

    _updateRowVisibility() {
        // Build filter criteria for checkbox columns
        const checkboxFilters = this._filterMenus.filter(fm => !fm.isDateColumn && !fm.isNumberColumn && !fm.isTextFilterMode && fm.inputs.length > 0).map(fm => ({
            column: fm.column,
            selected: fm.inputs.filter(input => input.checked).map(input => input.value.trim().replace(/ +(?= )/g, ''))
        }));

        // Build filter criteria for text filter columns (> 30 unique values)
        const textFilters = this._filterMenus.filter(fm => fm.isTextFilterMode && fm.textFilter?.value).map(fm => ({
            column: fm.column,
            searchText: fm.textFilter.value.toLowerCase().trim()
        }));

        // Build filter criteria for date columns
        const dateFilters = this._filterMenus.filter(fm => fm.isDateColumn).map(fm => ({
            column: fm.column,
            from: fm.dateFromInput?.value ? this._sortableDate(fm.dateFromInput.value.trim()) : null,
            to: fm.dateToInput?.value ? this._sortableDate(fm.dateToInput.value.trim()) : null
        }));

        // Build filter criteria for time columns
        const timeFilters = this._filterMenus.filter(fm => fm.isTimeColumn).map(fm => ({
            column: fm.column,
            from: fm.timeFromInput?.value || null,
            to: fm.timeToInput?.value || null
        }));

        // Build filter criteria for number columns
        const numberFilters = this._filterMenus.filter(fm => fm.isNumberColumn).map(fm => ({
            column: fm.column,
            from: fm.numberFromInput?.value !== '' ? parseFloat(fm.numberFromInput.value) : null,
            to: fm.numberToInput?.value !== '' ? parseFloat(fm.numberToInput.value) : null
        }));

        this._rows.forEach(row => {
            const tds = row.children;
            let visible = true;

            // Check checkbox filters
            for (const filter of checkboxFilters) {
                if (tds[filter.column]) {
                    const content = this._getCellValue(tds[filter.column]).trim().replace(/ +(?= )/g, '');
                    if (!filter.selected.includes(content)) {
                        visible = false;
                        break;
                    }
                }
            }

            // Check text filters (for columns with > 30 unique values)
            if (visible) {
                for (const tf of textFilters) {
                    if (tds[tf.column]) {
                        const content = this._getCellValue(tds[tf.column]).trim().toLowerCase();
                        if (!content.includes(tf.searchText)) {
                            visible = false;
                            break;
                        }
                    }
                }
            }

            // Check date filters
            if (visible) {
                for (const df of dateFilters) {
                    if (df.from || df.to) {
                        if (tds[df.column]) {
                            const dateContent = tds[df.column].innerText.trim();
                            const rowDate = this._sortableDate(dateContent);
                            if (rowDate) {
                                if (df.from && rowDate < df.from) {
                                    visible = false;
                                    break;
                                }
                                if (df.to && rowDate > df.to) {
                                    visible = false;
                                    break;
                                }
                            } else if (!dateContent && (df.from || df.to)) {
                                visible = false;
                                break;
                            }
                        }
                    }
                }
            }

            // Check time filters
            if (visible) {
                for (const tf of timeFilters) {
                    if (tf.from || tf.to) {
                        if (tds[tf.column]) {
                            const timeContent = tds[tf.column].innerText.trim();
                            if (timeContent) {
                                if (tf.from && timeContent < tf.from) {
                                    visible = false;
                                    break;
                                }
                                if (tf.to && timeContent > tf.to) {
                                    visible = false;
                                    break;
                                }
                            } else if (tf.from || tf.to) {
                                visible = false;
                                break;
                            }
                        }
                    }
                }
            }

            // Check number filters
            if (visible) {
                for (const nf of numberFilters) {
                    if (nf.from !== null || nf.to !== null) {
                        if (tds[nf.column]) {
                            const numContent = tds[nf.column].innerText.trim().replace(/[€$£%,\s]/g, '').replace(',', '.');
                            const rowNum = parseFloat(numContent);
                            if (!isNaN(rowNum)) {
                                if (nf.from !== null && rowNum < nf.from) {
                                    visible = false;
                                    break;
                                }
                                if (nf.to !== null && rowNum > nf.to) {
                                    visible = false;
                                    break;
                                }
                            } else if (numContent === '' && (nf.from !== null || nf.to !== null)) {
                                // Empty cells are hidden when a number filter is active
                                visible = false;
                                break;
                            }
                        }
                    }
                }
            }

            row.style.display = visible ? '' : 'none';
        });

        this._updateView();
    }

    _getCellValue(cell) {
        // Check for data-filter attribute first
        if (cell.dataset && cell.dataset.filter) {
            return cell.dataset.filter;
        }
        // For boolean columns, use data-value and convert to Ja/Nee
        if (cell.dataset && cell.dataset.type === 'boolean' && cell.dataset.value !== undefined) {
            const val = cell.dataset.value;
            // Convert boolean values: 1/-1/true/J → Ja, 0/false/N → Nee
            return (val === '1' || val === '-1' || val === 'true' || val === 'J') ? 'Ja' : 'Nee';
        }
        // Then check for inputs
        const input = cell.querySelector('input, select');
        if (input) {
            return input.value || '';
        }
        // Finally use text content
        return cell.innerText || cell.textContent || '';
    }

    _updateView() {
        // Note: Even/odd striping now handled by CSS :nth-child selectors
        // No manual class manipulation needed - CSS handles visibility changes natively

        // Update filter icon active state
        this._filterMenus.forEach(menu => {
            const icon = menu.th.querySelector('.dropdown-filter-icon');
            if (icon && menu.selectAllCheckbox) {
                if (menu.selectAllCheckbox.checked) {
                    icon.classList.remove('active');
                } else {
                    icon.classList.add('active');
                }
            }
        });

        // Update totals if present
        this._updateTotals();
    }

    _updateTotals() {
        const totalCells = this._table.querySelectorAll('tfoot td[data-total="Y"]');
        totalCells.forEach(cell => {
            const colIndex = cell.cellIndex;
            let total = 0;
            this._table.querySelectorAll(`tbody tr:not([style*="display: none"]) td:nth-child(${colIndex + 1})`).forEach(td => {
                const val = td.textContent.replace(/[%€£$]/g, '');
                total += parseInt(val) || 0;
            });

            let value = '';
            if (cell.dataset.totalPreText) value += cell.dataset.totalPreText + '  ';
            value += total;
            if (cell.dataset.totalPostText) value += '  ' + cell.dataset.totalPostText;
            cell.textContent = value;
        });
    }

    _closeAllFilterMenus() {
        const menus = this.querySelectorAll('.dropdown-filter-content');
        menus.forEach((el) => {
            el.style.display = 'none';
        });
    }

    _initializeExportMenu() {
        // Check if export is disabled or no data rows
        // Check both 'export' and 'data-export' attributes on both the component and the table
        if (this.getAttribute('export')?.toLowerCase() === 'n') return;
        if (this.getAttribute('data-export')?.toLowerCase() === 'n') return;
        if (this._table.getAttribute('data-export')?.toLowerCase() === 'n') return;

        // Note: export menu is shown even for empty tables (thead is always present)

        const firstTh = this._table.querySelector('thead th');
        if (!firstTh) return;

        // Skip if export menu already exists (e.g., from PHP-generated table)
        if (firstTh.querySelector('.menutrigger, .export-menu, .cma-context-menu')) return;

        const tableName = this.getAttribute('name') || this._table.dataset.name || this._table.id;

        const trigger = document.createElement('div');
        trigger.className = 'menutrigger';
        trigger.innerHTML = `
            <div class="cma-context-menu export-menu">
                <ul>
                    <li class="exportXLS"><span class="lnr lnr-excel"></span>Export naar Excel</li>
                    <li class="exportCSV"><span class="lnr lnr-csv"></span>Export naar CSV</li>
                    <li class="exportDOC"><span class="lnr lnr-word"></span>Export naar Word</li>
                </ul>
            </div>
        `;

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            // Close all filter dropdowns and other export menus
            this._closeAllFilterMenus();
            // Remember current state before closing all export menus
            const wasOpen = trigger.classList.contains('open');
            this._closeExportMenu();
            // Toggle: if it was open, it's now closed (done above); if it was closed, open it
            if (!wasOpen) {
                trigger.classList.add('open');
            }

            // Flip position if not enough space below
            if (trigger.classList.contains('open')) {
                const menu = trigger.querySelector('.cma-context-menu');
                if (menu) {
                    menu.style.bottom = '';
                    menu.style.top = '';
                    requestAnimationFrame(() => {
                        const menuRect = menu.getBoundingClientRect();
                        if (menuRect.bottom > window.innerHeight) {
                            menu.style.top = 'auto';
                            menu.style.bottom = '100%';
                        }
                    });
                }
            }
        });

        // Export handlers
        trigger.querySelector('.exportXLS').addEventListener('click', () => {
            this._export('xlsx', tableName);
        });
        trigger.querySelector('.exportCSV').addEventListener('click', () => {
            this._export('csv', tableName);
        });
        trigger.querySelector('.exportDOC').addEventListener('click', () => {
            this._export('doc', tableName);
        });

        // Insert inside th-header-wrapper if it exists, otherwise as first child of th
        const headerWrapper = firstTh.querySelector('.th-header-wrapper');
        if (headerWrapper) {
            headerWrapper.insertBefore(trigger, headerWrapper.firstChild);
        } else {
            firstTh.insertBefore(trigger, firstTh.firstChild);
        }
    }

    _export(format, tableName) {
        // Lazy load table_functions.js if needed
        if (typeof window.lib_LoadTableFunctions === 'function') {
            window.lib_LoadTableFunctions(() => {
                this._doExport(format, tableName);
            });
        } else {
            this._doExport(format, tableName);
        }
    }

    /**
     * Extract a cell's export value from its content.
     * Handles: <a href>, <input>, <select>, <lib-switch>, checkbox, and falls back to innerText.
     */
    _extractCellValue(cell) {
        var link = cell.querySelector('a[href]');
        if (link) {
            var href = link.getAttribute('href');
            if (href && href !== '#' && href !== 'javascript:void(0)') {
                return href;
            }
        }

        var libSwitch = cell.querySelector('lib-switch');
        if (libSwitch) {
            return libSwitch.checked ? (libSwitch.getAttribute('label-on') || 'Ja') : (libSwitch.getAttribute('label-off') || 'Nee');
        }

        var checkbox = cell.querySelector('input[type="checkbox"]');
        if (checkbox) {
            return checkbox.checked ? 'Ja' : 'Nee';
        }

        var select = cell.querySelector('select');
        if (select) {
            var opt = select.options[select.selectedIndex];
            return opt ? opt.text : '';
        }

        var input = cell.querySelector('input, textarea');
        if (input) {
            return input.value || '';
        }

        return cell.innerText.trim();
    }

    _doExport(format, tableName) {
        if (typeof window.TableExport === 'undefined') {
            console.error('TableExport library not available');
            return;
        }

        var table = this._table;

        // Create clean copy of the table
        var clone = table.cloneNode(true);
        clone.id = 'export_' + Math.random().toString(36).substr(2, 9);

        // Remove UI elements that shouldn't be exported
        clone.querySelectorAll('.menutrigger, .dropdown-filter-dropdown, .column-resize-handle').forEach(function(el) { el.remove(); });
        clone.querySelectorAll('.clicker').forEach(function(el) { el.classList.remove('clicker'); });

        // Remove hidden rows/cells from source
        var sourceRows = table.querySelectorAll('tbody tr');
        var cloneRows = clone.querySelectorAll('tbody tr');
        for (var i = cloneRows.length - 1; i >= 0; i--) {
            if (sourceRows[i] && sourceRows[i].style.display === 'none') {
                cloneRows[i].remove();
            }
        }

        // Replace cell contents with extracted values (form controls, links, etc.)
        var self = this;
        clone.querySelectorAll('tbody tr').forEach(function(cloneRow, rowIndex) {
            var sourceTbody = table.querySelector('tbody');
            var sourceRow = sourceTbody ? sourceTbody.children[rowIndex] : null;

            cloneRow.querySelectorAll('td').forEach(function(td, colIndex) {
                var sourceCell = sourceRow ? sourceRow.children[colIndex] : null;
                var cellToRead = sourceCell || td;

                var hasControls = cellToRead.querySelector('a[href], input, select, textarea, lib-switch');
                if (hasControls) {
                    td.textContent = self._extractCellValue(cellToRead);
                }
            });
        });

        // Append to body for TableExport to read
        clone.style.position = 'absolute';
        clone.style.left = '-9999px';
        document.body.appendChild(clone);

        TableExport.prototype.charset = 'charset=utf-8';

        var formatMap = {
            'xlsx': 'xlsx',
            'csv': 'csv',
            'doc': 'txt'
        };

        var instance = new TableExport(clone, {
            formats: [formatMap[format]],
            exportButtons: false,
            trimWhitespace: true
        });

        var formatKey = format === 'xlsx' ? 'XLSX' : format === 'csv' ? 'CSV' : 'TXT';
        var exportData = instance.getExportData()[clone.id][instance.CONSTANTS.FORMAT[formatKey]];

        clone.remove();
        instance.export2file(exportData.data, exportData.mimeType, tableName, exportData.fileExtension);
    }

    _closeExportMenu() {
        this.querySelectorAll('.menutrigger.open').forEach(el => {
            el.classList.remove('open');
        });
    }

    // Public API
    refresh() {
        if (!this._table) return;
        // Remove old filter menus from header
        this._filterMenus = [];
        this._table.querySelectorAll('thead th').forEach(th => {
            const wrapper = th.querySelector('.th-header-wrapper');
            if (wrapper) {
                // Restore original header text
                const clicker = wrapper.querySelector('.clicker');
                th.innerHTML = clicker ? clicker.innerHTML : '';
                th.classList.remove('truncated');
                delete th.dataset.tooltip;
            }
        });
        // Re-collect rows and rebuild filters
        this._rows = Array.from(this._table.querySelectorAll('tbody tr'));
        this._initializeFilters();
        this._updateView();
    }

    clearFilters() {
        this._filterMenus.forEach(menu => {
            if (menu.selectAllCheckbox) {
                menu.selectAllCheckbox.checked = true;
            }
            this._toggleAll(menu, true);
            if (menu.searchFilter) {
                menu.searchFilter.value = '';
            }
            if (menu.textFilter) {
                menu.textFilter.value = '';
            }
            if (menu.dateFromInput) {
                menu.dateFromInput.value = '';
            }
            if (menu.dateToInput) {
                menu.dateToInput.value = '';
            }
            if (menu.timeFromInput) {
                menu.timeFromInput.value = '';
            }
            if (menu.timeToInput) {
                menu.timeToInput.value = '';
            }
            if (menu.numberFromInput) {
                menu.numberFromInput.value = '';
            }
            if (menu.numberToInput) {
                menu.numberToInput.value = '';
            }
        });
        this._updateRowVisibility();
    }
}

// Register the HTML table wrapper component
customElements.define('lib-table', LibTable);

} // end guard

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { LibDataTable, LibTable };
}
