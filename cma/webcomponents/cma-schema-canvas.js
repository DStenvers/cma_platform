/**
 * cma-schema-canvas Web Component
 *
 * Visual schema canvas for displaying database tables and relationships.
 * Used in the Query Designer for table selection and relationship visualization.
 *
 * Usage:
 *   <cma-schema-canvas database-id="1">
 *   </cma-schema-canvas>
 *
 * Attributes:
 *   - database-id: ID of the database to load schema from
 *   - show-relationships: Show FK relationship lines (default: true)
 *   - show-columns: Show column list in table boxes (default: true)
 *   - max-columns: Maximum columns to show per table (default: 10)
 *
 * Events:
 *   - table-add: Fired when a table is added to canvas
 *   - table-remove: Fired when a table is removed from canvas
 *   - table-select: Fired when a table is clicked/selected
 *   - schema-loaded: Fired when database schema is loaded
 *   - change: Fired when selected tables change
 *
 * Methods:
 *   - addTable(tableName): Add a table to the canvas
 *   - removeTable(tableName): Remove a table from the canvas
 *   - getSelectedTables(): Get array of selected table names
 *   - setSelectedTables(tables): Set selected tables
 *   - loadSchema(databaseId): Load schema for a database
 *   - getTableSchema(tableName): Get schema info for a table
 *   - autoLayout(): Automatically arrange tables
 */

// Guard against double registration
if (!customElements.get('cma-schema-canvas')) {

class CmaSchemaCanvas extends HTMLElement {
    static get observedAttributes() {
        return ['database-id', 'show-relationships', 'show-columns', 'max-columns', 'advanced-mode', 'selectable-fields'];
    }

    // Type translations (English to Dutch)
    static _typeTranslations = {
        'text': 'tekst',
        'number': 'getal',
        'date': 'datum',
        'boolean': 'ja/nee',
        'binary': 'binair'
    };

    _translateType(typeCategory) {
        return CmaSchemaCanvas._typeTranslations[typeCategory] || typeCategory;
    }

    _displayTableName(tableName) {
        // Use shared function from CMA namespace
        return (typeof CMA !== 'undefined' && CMA.displayTableName)
            ? CMA.displayTableName(tableName)
            : tableName || '';
    }

    static _instanceCounter = 0;

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._instanceId = ++CmaSchemaCanvas._instanceCounter;

        // State
        this._tables = new Map(); // tableName -> { columns, primaryKeys, position }
        this._selectedTables = new Set();
        this._expandedTables = new Set(); // Track which tables show all columns
        this._relationships = []; // Array of { from: {table, column}, to: {table, column} }
        this._schema = null; // Full schema data
        this._loading = false;

        // Drag state
        this._draggedTable = null;
        this._dragOffset = { x: 0, y: 0 };
        this._isDragging = false;

        // Canvas dimensions
        this._canvasWidth = 2000;
        this._canvasHeight = 1500;

        // Relationships panel state
        this._relationshipsPanelClosed = false;
        this._panelPosition = null; // { left, top } or null for default

        // Field selection state
        this._selectedFields = new Map(); // tableName -> Set of selected column names
        this._tableAliases = new Map(); // tableName -> alias string
        this._tableSizes = new Map(); // tableName -> { width, height }
        this._editingAlias = null; // tableName currently editing alias
        this._resizingTable = null; // tableName currently being resized
        this._resizeStart = null; // { x, y, width, height }
    }

    connectedCallback() {
        // Adopt shared styles if available
        if (typeof LibSharedStyles !== 'undefined' && LibSharedStyles.isSupported()) {
            LibSharedStyles.adopt(this.shadowRoot, 'base', 'button');
        }

        this._render();
        this._setupEventListeners();

        // Load schema if database-id is set
        const databaseId = this.getAttribute('database-id');
        if (databaseId) {
            this.loadSchema(parseInt(databaseId, 10));
        }
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;

        switch (name) {
            case 'database-id':
                if (newValue) {
                    this.loadSchema(parseInt(newValue, 10));
                }
                break;
            case 'show-relationships':
            case 'show-columns':
            case 'max-columns':
            case 'advanced-mode':
            case 'selectable-fields':
                this._renderCanvas();
                break;
        }
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Add a table to the canvas
     * @param {string} tableName - Table name
     * @param {Object} [tableSchema] - Optional pre-loaded schema (columns, primaryKeys, relationships)
     */
    addTable(tableName, tableSchema = null) {
        if (this._selectedTables.has(tableName)) {
            return; // Already added
        }

        // Check for preloaded position first
        const preloadedPos = this._preloadedPositions?.[tableName];
        const getPosition = () => preloadedPos ? { x: preloadedPos.x, y: preloadedPos.y } : this._getNextPosition();

        // Get table schema if we have it
        let tableInfo = this._tables.get(tableName);

        // If schema was provided, use it
        if (tableSchema) {
            tableInfo = {
                columns: tableSchema.columns || [],
                primaryKeys: tableSchema.primaryKeys || [],
                relationships: tableSchema.relationships || [],
                position: getPosition()
            };
            this._tables.set(tableName, tableInfo);
        } else if (!tableInfo && this._schema) {
            const schemaTable = this._schema.tables?.find(t => t.name === tableName);
            if (schemaTable) {
                tableInfo = {
                    columns: schemaTable.columns || [],
                    primaryKeys: schemaTable.primaryKeys || [],
                    relationships: schemaTable.relationships || [],
                    position: getPosition()
                };
                this._tables.set(tableName, tableInfo);
            }
        }

        if (!tableInfo) {
            // Create placeholder
            tableInfo = {
                columns: [],
                primaryKeys: [],
                relationships: [],
                position: getPosition()
            };
            this._tables.set(tableName, tableInfo);
        }

        this._selectedTables.add(tableName);
        this._updateRelationshipsFromSelectedTables();

        // Only auto-layout if we don't have preloaded positions
        if (!this._preloadedPositions) {
            this.autoLayout();
        } else {
            // Just render without re-layout
            this._renderCanvas();
            this._updateRelationshipLines();
        }

        this.dispatchEvent(new CustomEvent('table-add', {
            bubbles: true,
            detail: { tableName, tableInfo }
        }));

        this.dispatchEvent(new CustomEvent('change', {
            bubbles: true,
            detail: { tables: this.getSelectedTables() }
        }));
    }

    /**
     * Update a table's schema after it has been added as a placeholder
     * @param {string} tableName - Table name
     * @param {Object} tableSchema - Schema data (columns, primaryKeys, relationships)
     */
    updateTableSchema(tableName, tableSchema) {
        if (!this._selectedTables.has(tableName)) {
            return; // Table not on canvas
        }

        const existingInfo = this._tables.get(tableName);
        const position = existingInfo?.position || this._getNextPosition();

        // Update table info with schema
        this._tables.set(tableName, {
            columns: tableSchema.columns || [],
            primaryKeys: tableSchema.primaryKeys || [],
            relationships: tableSchema.relationships || [],
            position
        });

        // Update relationships
        this._updateRelationshipsFromSelectedTables();

        // Re-render to show the columns
        this._renderCanvas();
        this._updateRelationshipLines(); // This also updates the relationships panel

        this.dispatchEvent(new CustomEvent('table-schema-loaded', {
            bubbles: true,
            detail: { tableName, schema: tableSchema }
        }));
    }

    /**
     * Remove a table from the canvas
     */
    removeTable(tableName) {
        if (!this._selectedTables.has(tableName)) {
            return;
        }

        // Clear position so it becomes available for new tables
        const tableInfo = this._tables.get(tableName);
        if (tableInfo) {
            tableInfo.position = null;
        }

        this._selectedTables.delete(tableName);
        this._updateRelationshipsFromSelectedTables();
        // Re-layout remaining tables based on relationships
        this.autoLayout();

        this.dispatchEvent(new CustomEvent('table-remove', {
            bubbles: true,
            detail: { tableName }
        }));

        this.dispatchEvent(new CustomEvent('change', {
            bubbles: true,
            detail: { tables: this.getSelectedTables() }
        }));
    }

    /**
     * Get array of selected table names
     */
    getSelectedTables() {
        return Array.from(this._selectedTables);
    }

    /**
     * Get the current position of the relationships panel
     * @returns {Object|null} { left, top } or null if default position
     */
    getPanelPosition() {
        return this._panelPosition;
    }

    /**
     * Set the position of the relationships panel
     * @param {Object|null} position - { left, top } or null for default
     */
    setPanelPosition(position) {
        this._panelPosition = position;
        const panel = this.shadowRoot?.querySelector('.relationships-panel');
        if (panel && position) {
            panel.style.right = 'auto';
            panel.style.left = position.left + 'px';
            panel.style.top = position.top + 'px';
        } else if (panel) {
            // Reset to default position
            panel.style.left = '';
            panel.style.right = '8px';
            panel.style.top = '8px';
        }
    }

    /**
     * Highlight a table on the canvas with a flash animation
     * @param {string} tableName - Table name to highlight
     */
    highlightTable(tableName) {
        const tableBox = this.shadowRoot.querySelector(`.schema-table[data-table="${tableName}"]`);
        if (!tableBox) return;

        // Scroll the table into view on the canvas
        tableBox.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });

        // Add highlight animation
        tableBox.classList.add('highlight-flash');

        // Remove animation class after it completes
        setTimeout(() => {
            tableBox.classList.remove('highlight-flash');
        }, 1500);
    }

    /**
     * Set selected tables
     * @param {Array} tables - Array of table names or objects with name and schema
     */
    setSelectedTables(tables) {
        this._selectedTables.clear();

        // If setting empty, also clear all table data and relationships
        if (!tables || tables.length === 0) {
            this._tables.clear();
            this._relationships = [];
        }

        for (const table of tables) {
            const tableName = typeof table === 'string' ? table : table.name;

            if (tableName) {
                this._selectedTables.add(tableName);

                // Ensure table info exists
                if (!this._tables.has(tableName)) {
                    const tableSchema = typeof table === 'object' ? table : null;
                    this._tables.set(tableName, {
                        columns: tableSchema?.columns || [],
                        primaryKeys: tableSchema?.primaryKeys || [],
                        relationships: tableSchema?.relationships || [],
                        position: this._getNextPosition()
                    });
                }
            }
        }

        this._updateRelationshipsFromSelectedTables();
        // Re-layout tables based on relationships (PK/hoofd tables on the left)
        this.autoLayout();

        this.dispatchEvent(new CustomEvent('change', {
            bubbles: true,
            detail: { tables: this.getSelectedTables() }
        }));
    }

    /**
     * Load schema for a database
     */
    async loadSchema(databaseId) {
        if (!databaseId || this._loading) return;

        this._loading = true;
        this._renderLoadingState();

        try {
            const response = await fetch(`api/report-schema.php?action=getTables&database=${databaseId}`);
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to load schema');
            }

            this._schema = {
                databaseId: databaseId,
                tables: data.tables || []
            };

            // Build tables map
            this._tables.clear();
            for (const table of this._schema.tables) {
                this._tables.set(table.name, {
                    columns: table.columns || [],
                    primaryKeys: table.primaryKeys || [],
                    position: null // Will be set when added to canvas
                });
            }

            // Note: Relationships are loaded per-table when needed
            // The getRelationships API requires a table name

            this._loading = false;
            this._render();

            this.dispatchEvent(new CustomEvent('schema-loaded', {
                bubbles: true,
                detail: {
                    databaseId,
                    tableCount: this._schema.tables.length
                }
            }));

        } catch (error) {
            this._loading = false;
            cmaLog.error('[cma-schema-canvas] Error loading schema:', error);
            this._renderError(error.message);
        }
    }

    /**
     * Get schema info for a specific table
     */
    getTableSchema(tableName) {
        return this._tables.get(tableName) || null;
    }

    /**
     * Get all available tables (not just selected)
     */
    getAvailableTables() {
        return Array.from(this._tables.keys());
    }

    /**
     * Get positions of all selected tables
     * @returns {Object} Map of tableName -> {x, y}
     */
    getTablePositions() {
        const positions = {};
        for (const tableName of this._selectedTables) {
            const tableInfo = this._tables.get(tableName);
            if (tableInfo && tableInfo.position) {
                positions[tableName] = { ...tableInfo.position };
            }
        }
        return positions;
    }

    /**
     * Set positions for tables
     * @param {Object} positions - Map of tableName -> {x, y}
     */
    setTablePositions(positions) {
        if (!positions || typeof positions !== 'object') return;

        for (const [tableName, pos] of Object.entries(positions)) {
            const tableInfo = this._tables.get(tableName);
            if (tableInfo && this._selectedTables.has(tableName)) {
                tableInfo.position = { x: pos.x, y: pos.y };
            }
        }

        // Re-render to apply positions
        this._renderCanvas();
        this._updateRelationshipLines();
    }

    /**
     * Preload positions for tables that will be added later
     * Call this BEFORE adding tables to avoid visual flickering
     * @param {Object} positions - Map of tableName -> {x, y}
     */
    preloadPositions(positions) {
        if (!positions || typeof positions !== 'object') return;
        this._preloadedPositions = positions;
    }

    /**
     * Clear preloaded positions
     */
    clearPreloadedPositions() {
        this._preloadedPositions = null;
    }

    /**
     * Auto-layout tables on canvas based on relationships
     * Positions hoofd (PK) tables on the left, following relationships left to right
     * Wraps to next row with 200px offset when tables don't fit
     */
    autoLayout() {
        const tables = this.getSelectedTables();
        if (tables.length === 0) {
            // Still need to clear the canvas when no tables
            this._renderCanvas();
            this._updateRelationshipLines();
            return;
        }

        const startX = 40;
        const startY = 40;
        const spacingX = 250;
        const rowOffsetY = 200;
        const maxWidth = this._canvasWidth - 100;

        // Build relationship graph to determine table order
        // hoofd (PK) tables should be on the left
        const orderedTables = this._getRelationshipOrderedTables(tables);

        let currentX = startX;
        let currentY = startY;
        let rowDirection = 1; // 1 = left-to-right, -1 = right-to-left
        let rowStartX = startX;

        orderedTables.forEach((tableName, index) => {
            const tableInfo = this._tables.get(tableName);
            if (!tableInfo) return;

            // Check if table has a preloaded position (from saved report)
            const preloadedPos = this._preloadedPositions?.[tableName];
            if (preloadedPos && typeof preloadedPos.x === 'number' && typeof preloadedPos.y === 'number') {
                // Use saved position
                tableInfo.position = { x: preloadedPos.x, y: preloadedPos.y };
                return; // Skip auto-layout for this table
            }

            // Check if we need to wrap to next row
            if (rowDirection === 1 && currentX + spacingX > maxWidth) {
                // Switch to right-to-left on next row
                currentY += rowOffsetY;
                rowDirection = -1;
                currentX = maxWidth - spacingX;
                rowStartX = currentX;
            } else if (rowDirection === -1 && currentX < startX) {
                // Switch back to left-to-right on next row
                currentY += rowOffsetY;
                rowDirection = 1;
                currentX = startX;
                rowStartX = currentX;
            }

            tableInfo.position = {
                x: currentX,
                y: currentY
            };

            currentX += rowDirection * spacingX;
        });

        this._renderCanvas();
        this._updateRelationshipLines();
    }

    /**
     * Order tables based on relationships panel order
     * PK (hoofd) tables on the left, FK tables follow to the right
     * Follows the order as shown in the relationships panel
     * @param {Array} tables - Array of table names
     * @returns {Array} - Ordered array of table names
     */
    _getRelationshipOrderedTables(tables) {
        const tableSet = new Set(tables);

        // Get relationships between selected tables in panel order
        const relevantRelationships = this._relationships.filter(rel =>
            tableSet.has(rel.from.table) && tableSet.has(rel.to.table)
        );

        if (relevantRelationships.length === 0) {
            // No relationships - return original order
            return [...tables];
        }

        // Build ordered list following the relationship panel order
        // For each relationship: PK (to) comes before FK (from)
        const ordered = [];
        const visited = new Set();

        // Process relationships in panel order
        for (const rel of relevantRelationships) {
            const pkTable = rel.to.table;   // PK/hoofd table
            const fkTable = rel.from.table; // FK table

            // Add PK table first if not already added
            if (!visited.has(pkTable)) {
                ordered.push(pkTable);
                visited.add(pkTable);
            }

            // Add FK table after its PK
            if (!visited.has(fkTable)) {
                ordered.push(fkTable);
                visited.add(fkTable);
            }
        }

        // Add any remaining tables without relationships
        tables.forEach(t => {
            if (!visited.has(t)) {
                ordered.push(t);
            }
        });

        return ordered;
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    _render() {
        const showRelationships = this.getAttribute('show-relationships') !== 'false';

        this.shadowRoot.innerHTML = `
            <style>
                :host {
                    display: block;
                    width: 100%;
                    height: 100%;
                    min-height: 300px;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    font-size: var(--font-size);
                }

                * {
                    box-sizing: border-box;
                }

                /* Linearicons - from shared-icons.js */
                ${CMA.getIconStylesFor(['plus-circle', 'warning', 'pencil', 'checkmark-circle', 'cross-circle', 'chevron-up', 'chevron-down'])}

                .canvas-container {
                    width: 100%;
                    height: 100%;
                    position: relative;
                    overflow: auto;
                    background:
                        linear-gradient(90deg, #e5e5e5 1px, transparent 1px) 0 0 / 20px 20px,
                        linear-gradient(#e5e5e5 1px, transparent 1px) 0 0 / 20px 20px,
                        #f9f9f9;
                }

                .canvas-inner {
                    position: relative;
                    width: 100%;
                    height: 100%;
                    min-width: 500px;  /* Wider to accommodate relationship lines */
                    min-height: 400px;
                    padding-left: 20px;  /* Space for left-curving relationship lines */
                }

                /* SVG for relationship lines */
                .relationship-svg {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    pointer-events: none;
                    z-index: 1;
                }

                .relationship-line {
                    fill: none;
                    stroke: #204496;
                    stroke-width: 2;
                }

                /* LEFT JOIN relationships shown as dotted lines */
                .relationship-line.left-join {
                    stroke-dasharray: 4, 4;
                }

                .relationship-line:hover {
                    stroke: #204496;
                    stroke-width: 3;
                }

                .relationship-endpoint {
                    fill: #204496;
                    pointer-events: all;
                    cursor: pointer;
                }

                /* LEFT JOIN endpoints shown as hollow circles */
                .relationship-endpoint.left-join {
                    fill: none;
                    stroke: #204496;
                    stroke-width: 2;
                }

                .relationship-endpoint:hover,
                .relationship-line:hover + .relationship-endpoint,
                .relationship-endpoint:hover ~ .relationship-line {
                    fill: #204496;
                }

                .relationship-label {
                    font-size: var(--font-size-sm);
                    font-weight: 600;
                    fill: #204496;
                    pointer-events: none;
                }

                /* Table box */
                .schema-table {
                    position: absolute;
                    min-width: 180px;
                    max-width: 280px;
                    background: #fff;
                    border: 2px solid #204496;
                    border-radius: 6px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    cursor: grab;
                    z-index: 10;
                    user-select: none;
                }

                .schema-table:hover {
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                }

                .schema-table.dragging {
                    opacity: 0.8;
                    z-index: 100;
                    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
                    cursor: grabbing;
                }

                .schema-table.highlight-flash {
                    animation: highlight-pulse 1.5s ease-out;
                    z-index: 50;
                }

                @keyframes highlight-pulse {
                    0% {
                        box-shadow: 0 0 0 0 rgba(32, 68, 150, 0.7);
                        transform: scale(1);
                    }
                    20% {
                        box-shadow: 0 0 0 15px rgba(32, 68, 150, 0.3);
                        transform: scale(1.02);
                    }
                    50% {
                        box-shadow: 0 0 0 10px rgba(32, 68, 150, 0.2);
                        transform: scale(1.01);
                    }
                    100% {
                        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                        transform: scale(1);
                    }
                }

                .schema-table-header {
                    padding: 4px 12px 4px 10px;
                    background: #204496;
                    color: white;
                    font-weight: 600;
                    font-size: var(--font-size);
                    border-radius: 4px 4px 0 0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }

                .schema-table-header .table-name {
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    flex: 1;
                }

                .schema-table-header .remove-btn {
                    background: none;
                    border: none;
                    color: rgba(255, 255, 255, 0.7);
                    cursor: pointer;
                    font-size: var(--font-size-2xl);
                    border-radius: 3px;
                    margin-left: 3px;
                    margin-top: -1px;
                    margin-right: -4px;
                    width: 18px;
                    height: 18px;
                    vertical-align: middle;
                    padding: 0px;
                    padding-bottom: 4px;
                    padding-top: 0px;
                }

                .schema-table-header .remove-btn:hover {
                    color: var(--close-btn-hover-color, white);
                    background: var(--close-btn-hover-bg, red);
                }

                .schema-table-columns {
                    overflow-y: auto;
                    overflow-x: hidden;
                    min-height: 50px;
                    max-height: 400px;
                }

                .schema-table-loading {
                    padding: 20px 10px;
                    text-align: center;
                    color: var(--text-secondary, #888);
                    font-size: var(--font-size-sm);
                }

                .schema-table-loading .spinner {
                    display: inline-block;
                    width: 16px;
                    height: 16px;
                    border: 2px solid var(--border-light, #ddd);
                    border-top-color: var(--color-info, #204496);
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin-right: 8px;
                    vertical-align: middle;
                }

                @keyframes spin {
                    to { transform: rotate(360deg); }
                }

                .schema-table-column {
                    padding: 4px 10px;
                    border-bottom: 1px solid #eee;
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    font-size: var(--font-size-sm);
                }

                .schema-table-column:last-child {
                    border-bottom: none;
                }

                .schema-table-column .col-name {
                    flex: 1;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }

                .schema-table-column .col-name.pk {
                    font-weight: 600;
                    color: #b45309;
                }

                .schema-table-column .col-type {
                    font-size: var(--font-size-2xs);
                    color: #888;
                    font-family: monospace;
                }

                /* Empty state - simple centered text */
                .canvas-empty {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    color: var(--text-muted, #888);
                    font-size: var(--font-size-md);
                }

                /* Loading state */
                .canvas-loading {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    text-align: center;
                    color: #666;
                }

                .canvas-loading .spinner {
                    width: 40px;
                    height: 40px;
                    border: 3px solid #e0e0e0;
                    border-top-color: #204496;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin: 0 auto 15px;
                }

                @keyframes spin {
                    to { transform: rotate(360deg); }
                }

                /* Error state */
                .canvas-error {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    text-align: center;
                    color: #dc2626;
                    padding: 40px;
                }

                .canvas-error .error-icon {
                    font-size: 48px;
                    margin-bottom: 15px;
                }

                /* Relationships info panel */
                .relationships-panel {
                    position: absolute;
                    top: 8px;
                    right: 8px;
                    background: #fff;
                    border: 1px solid #d0d5dd;
                    border-radius: 6px;
                    font-size: var(--font-size-sm);
                    max-width: 326px;
                    min-width: 260px;
                    z-index: 200;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
                    overflow: hidden;
                }

                .relationships-panel.dragging {
                    opacity: 0.9;
                    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
                }

                .relationships-panel.dragging .relationships-panel-header {
                    cursor: grabbing;
                }

                .relationships-panel.hidden {
                    display: none;
                }

                .relationships-panel-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 10px 12px;
                    font-weight: 600;
                    font-size: var(--font-size);
                    color: #204496;
                    background: #f8f9fc;
                    border-bottom: 1px solid #e8ecf0;
                    cursor: grab;
                    user-select: none;
                    transition: border-color 0.2s ease;
                }

                .relationships-panel.minimized .relationships-panel-header {
                    border-bottom-color: transparent;
                }

                .relationships-panel-header span {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                }

                .relationships-panel-toggle {
                    background: none;
                    border: none;
                    cursor: pointer;
                    font-size: var(--font-size-md);
                    color: #888;
                    padding: 2px 6px;
                    line-height: 1;
                    border-radius: 3px;
                    transition: all 0.15s;
                }

                .relationships-panel-toggle:hover {
                    background: var(--bg-active);
                }

                /* Toggle icons - show chevron-up when expanded, chevron-down when minimized */
                .relationships-panel-toggle .icon-expand {
                    display: none;
                }
                .relationships-panel-toggle .icon-collapse {
                    display: inline;
                }
                .relationships-panel.minimized .relationships-panel-toggle .icon-expand {
                    display: inline;
                }
                .relationships-panel.minimized .relationships-panel-toggle .icon-collapse {
                    display: none;
                }

                .relationships-panel-content {
                    padding: 8px;
                    color: #444;
                    line-height: 1.4;
                    max-height: 300px;
                    overflow-y: auto;
                    transition: max-height 0.3s ease, padding 0.3s ease, opacity 0.2s ease;
                }

                .relationships-panel.minimized .relationships-panel-content {
                    max-height: 0;
                    padding: 0 8px;
                    opacity: 0;
                    overflow: hidden;
                }

                .relationships-panel-content .rel-header {
                    display: flex;
                    justify-content: space-between;
                    font-size: var(--font-size-2xs);
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    color: #888;
                    padding: 0 6px 6px 6px;
                    margin-bottom: 4px;
                }

                .relationships-panel-content .rel-header .cardinality-one,
                .relationships-panel-content .rel-header .cardinality-many {
                    font-weight: 600;
                }

                .relationships-panel-content .rel-item {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    margin-bottom: 0;
                    padding: 6px;
                    cursor: pointer;
                    border-radius: 4px;
                    border: 1px solid transparent;
                    transition: all 0.15s;
                }

                .relationships-panel-content .rel-item:hover {
                    background: #f0f4ff;
                    border-color: #c5d4f0;
                }

                .relationships-panel-content .rel-item span {
                    font-size: var(--font-size-xs);
                }

                .relationships-panel-content .rel-item strong {
                    color: #204496;
                }

                .relationships-panel-content .rel-cardinality {
                    color: #204496;
                    font-size: var(--font-size-sm);
                    font-weight: 600;
                    flex-shrink: 0;
                    min-width: 12px;
                    text-align: center;
                }

                .relationships-panel-content .rel-join-symbol {
                    color: #666;
                    font-size: var(--font-size-md);
                    font-weight: 600;
                    flex-shrink: 0;
                    margin: 0 4px;
                }

                .relationships-panel-content .no-relations {
                    font-style: italic;
                    color: #888;
                    padding: 16px;
                    text-align: center;
                }

                .relationships-panel-content .unrelated-warning {
                    display: block;
                    padding: 10px 12px;
                    margin-top: 8px;
                    background: #fff8e6;
                    border: 1px solid #ffd666;
                    border-radius: 4px;
                    font-size: var(--font-size-xs);
                    color: #8c6c00;
                    line-height: 1.5;
                    word-wrap: break-word;
                    overflow-wrap: break-word;
                    box-sizing: border-box;
                    max-width: 100%;
                }

                .relationships-panel-content .unrelated-warning::before {
                    content: "\\e955";
                    font-family: 'Linearicons';
                    font-size: var(--font-size);
                    margin-right: 6px;
                    color: #faad14;
                    vertical-align: middle;
                }

                .relationships-panel-content .unrelated-warning strong {
                    color: #6b5500;
                    word-break: break-word;
                    overflow-wrap: break-word;
                }

                .relationships-panel-content .join-type-badge {
                    font-size: 9px;
                    font-weight: 600;
                    padding: 2px 6px;
                    border-radius: 3px;
                    background: #e0e0e0;
                    color: #666;
                    margin-left: auto;
                    flex-shrink: 0;
                    text-transform: uppercase;
                    letter-spacing: 0.3px;
                }

                .relationships-panel-content .rel-item.join-inner .join-type-badge {
                    background: #d4edda;
                    color: #1e7e34;
                }

                .relationships-panel-content .rel-item.join-left .join-type-badge {
                    background: #fff3cd;
                    color: #856404;
                }

                .relationships-panel-footer {
                    padding: 8px 12px;
                    border-top: 1px solid #e8ecf0;
                    background: #fafbfc;
                    transition: max-height 0.3s ease, padding 0.3s ease, opacity 0.2s ease, border 0.2s ease;
                    overflow: hidden;
                }

                .relationships-panel.minimized .relationships-panel-footer {
                    max-height: 0;
                    padding: 0 12px;
                    opacity: 0;
                    border-top-color: transparent;
                }

                .relationships-panel-add {
                    width: 100%;
                    background: #fff;
                    border: 1px dashed #c5d4f0;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: var(--font-size-sm);
                    color: #204496;
                    padding: 6px 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 6px;
                    transition: all 0.15s;
                }

                .relationships-panel-add:hover {
                    background: #f0f4ff;
                    border-color: #204496;
                    border-style: solid;
                }

                /* Make SVG relationship lines clickable */
                .relationship-line {
                    pointer-events: stroke;
                    cursor: pointer;
                }

                /* Invisible wider hit area for easier clicking */
                .relationship-line-hitarea {
                    fill: none;
                    stroke: transparent;
                    stroke-width: 16;
                    pointer-events: stroke;
                    cursor: pointer;
                }

                .relationship-line-hitarea:hover + .relationship-line {
                    stroke: #204496;
                    stroke-width: 3;
                }

                /* Field selection checkboxes */
                .field-checkbox {
                    width: 14px;
                    height: 14px;
                    margin: 0;
                    cursor: pointer;
                    flex-shrink: 0;
                    accent-color: #204496;
                }

                .schema-table-column.selectable {
                    cursor: pointer;
                }

                .schema-table-column.selectable:hover {
                    background: #f0f4ff;
                }

                .schema-table-column.selected {
                    background: #e8f0ff;
                }

                /* Master checkbox in header */
                .header-checkbox {
                    width: 14px;
                    height: 14px;
                    margin: 0 6px 0 0;
                    cursor: pointer;
                    flex-shrink: 0;
                    accent-color: white;
                }

                /* Alias editing */
                .alias-edit-btn {
                    background: none;
                    border: none;
                    color: rgba(255,255,255,0.6);
                    cursor: pointer;
                    padding: 2px 4px;
                    font-size: var(--font-size-sm);
                    line-height: 1;
                    border-radius: 3px;
                    margin-left: 4px;
                    opacity: 0;
                    transition: opacity 0.15s;
                }

                .schema-table-header:hover .alias-edit-btn {
                    opacity: 1;
                }

                .alias-edit-btn:hover {
                    color: white;
                    background: rgba(255,255,255,0.2);
                }

                .alias-edit-container {
                    display: flex;
                    align-items: center;
                    gap: 4px;
                    flex: 1;
                    min-width: 0;
                }

                .alias-edit-input {
                    flex: 1;
                    min-width: 60px;
                    padding: 2px 6px;
                    border: 1px solid rgba(255,255,255,0.5);
                    border-radius: 3px;
                    background: rgba(255,255,255,0.95);
                    color: #333;
                    font-size: var(--font-size-sm);
                    font-weight: 600;
                }

                .alias-edit-input:focus {
                    outline: none;
                    border-color: white;
                    background: white;
                }

                .alias-save-btn,
                .alias-cancel-btn {
                    background: none;
                    border: none;
                    color: rgba(255,255,255,0.8);
                    cursor: pointer;
                    padding: 2px 4px;
                    font-size: var(--font-size-sm);
                    line-height: 1;
                    border-radius: 3px;
                }

                .alias-save-btn:hover {
                    color: #4ade80;
                    background: rgba(255,255,255,0.2);
                }

                .alias-cancel-btn:hover {
                    color: #f87171;
                    background: rgba(255,255,255,0.2);
                }

                /* Table alias display */
                .table-alias {
                    font-size: var(--font-size-xs);
                    font-weight: normal;
                    opacity: 0.8;
                    margin-left: 4px;
                }

                /* Resize handle */
                .resize-handle {
                    position: absolute;
                    bottom: 0;
                    right: 0;
                    width: 16px;
                    height: 16px;
                    cursor: se-resize;
                    z-index: 15;
                }

                .resize-handle::after {
                    content: '';
                    position: absolute;
                    bottom: 4px;
                    right: 4px;
                    width: 8px;
                    height: 8px;
                    border-right: 2px solid #ccc;
                    border-bottom: 2px solid #ccc;
                }

                .schema-table:hover .resize-handle::after {
                    border-color: #204496;
                }

                .schema-table.resizing {
                    z-index: 100;
                }

                /* Linearicons loaded from shared-icons.js in first render block */
            </style>
            <!-- lib-dialog for relationship editing is created in document body -->
            <div class="canvas-container">
                <div class="canvas-inner">
                    ${showRelationships ? '<svg class="relationship-svg"></svg>' : ''}
                    <div class="tables-container"></div>
                    <div class="relationships-panel hidden">
                        <div class="relationships-panel-header">
                            <span>Relaties</span>
                            <button type="button" class="relationships-panel-toggle" title="Minimaliseren/Herstellen"><span class="lnr lnr-chevron-up icon-collapse"></span><span class="lnr lnr-chevron-down icon-expand"></span></button>
                        </div>
                        <div class="relationships-panel-content"></div>
                        <div class="relationships-panel-footer">
                            <button type="button" class="relationships-panel-add" title="Relatie toevoegen">
                                <span class="lnr lnr-plus-circle"></span> Toevoegen
                            </button>
                        </div>
                    </div>
                    ${this._selectedTables.size === 0 ? this._renderEmptyState() : ''}
                </div>
            </div>
        `;

        this._renderCanvas();
    }

    _renderEmptyState() {
        return `<div class="canvas-empty">Selecteer een tabel links</div>`;
    }

    _renderLoadingState() {
        const container = this.shadowRoot.querySelector('.canvas-inner');
        if (container) {
            container.innerHTML = `
                <div class="canvas-loading">
                    <div class="spinner"></div>
                    <div>Schema laden...</div>
                </div>
            `;
        }
    }

    _renderError(message) {
        const container = this.shadowRoot.querySelector('.canvas-inner');
        if (container) {
            container.innerHTML = `
                <div class="canvas-error">
                    <div class="error-icon">⚠️</div>
                    <div>${this._escapeHtml(message)}</div>
                </div>
            `;
        }
    }

    _renderCanvas() {
        const container = this.shadowRoot.querySelector('.tables-container');
        if (!container) return;

        const showColumns = this.getAttribute('show-columns') !== 'false';
        const maxColumns = parseInt(this.getAttribute('max-columns'), 10) || 10;
        const advancedMode = this.getAttribute('advanced-mode') === 'true';
        const selectableFields = this.getAttribute('selectable-fields') === 'true';

        // Clear existing tables
        container.innerHTML = '';

        // Remove empty state if we have tables
        const emptyState = this.shadowRoot.querySelector('.canvas-empty');
        if (emptyState) {
            if (this._selectedTables.size > 0) {
                emptyState.style.display = 'none';
            } else {
                emptyState.style.display = '';
            }
        }

        // Render each selected table
        for (const tableName of this._selectedTables) {
            const tableInfo = this._tables.get(tableName);
            if (!tableInfo) continue;

            // Set position if not set
            if (!tableInfo.position) {
                tableInfo.position = this._getNextPosition();
            }

            // Get stored size if any
            const tableSize = this._tableSizes.get(tableName);

            const tableEl = document.createElement('div');
            tableEl.className = 'schema-table';
            tableEl.dataset.table = tableName;
            tableEl.style.left = tableInfo.position.x + 'px';
            tableEl.style.top = tableInfo.position.y + 'px';

            // Apply stored size if available
            if (tableSize) {
                tableEl.style.width = tableSize.width + 'px';
                tableEl.style.minWidth = tableSize.width + 'px';
                tableEl.style.maxWidth = tableSize.width + 'px';
            }

            // Get selected fields for this table
            const selectedFields = this._selectedFields.get(tableName) || new Set();
            const allFieldsSelected = tableInfo.columns.length > 0 &&
                tableInfo.columns.every(col => selectedFields.has(col.name));
            const someFieldsSelected = tableInfo.columns.some(col => selectedFields.has(col.name));

            // Get alias for this table
            const tableAlias = this._tableAliases.get(tableName);
            const isEditingAlias = this._editingAlias === tableName;

            // Header
            const header = document.createElement('div');
            header.className = 'schema-table-header';

            if (isEditingAlias && advancedMode) {
                // Editing mode
                header.innerHTML = `
                    ${selectableFields ? `<input type="checkbox" class="header-checkbox" ${allFieldsSelected ? 'checked' : ''} ${someFieldsSelected && !allFieldsSelected ? 'indeterminate' : ''} title="Alle velden selecteren/deselecteren">` : ''}
                    <div class="alias-edit-container">
                        <input type="text" class="alias-edit-input" value="${this._escapeHtml(tableAlias || tableName)}" data-table="${this._escapeHtml(tableName)}">
                        <button type="button" class="alias-save-btn" title="Opslaan"><span class="lnr lnr-checkmark-circle"></span></button>
                        <button type="button" class="alias-cancel-btn" title="Annuleren"><span class="lnr lnr-cross-circle"></span></button>
                    </div>
                    <button type="button" class="remove-btn" title="Verwijderen">&times;</button>
                `;
                // Set indeterminate state via JS after render
                setTimeout(() => {
                    const checkbox = header.querySelector('.header-checkbox');
                    if (checkbox && someFieldsSelected && !allFieldsSelected) {
                        checkbox.indeterminate = true;
                    }
                }, 0);
            } else {
                // Normal display mode
                const aliasDisplay = tableAlias && tableAlias !== tableName
                    ? `<span class="table-alias">(${this._escapeHtml(tableAlias)})</span>`
                    : '';
                header.innerHTML = `
                    ${selectableFields ? `<input type="checkbox" class="header-checkbox" ${allFieldsSelected ? 'checked' : ''} title="Alle velden selecteren/deselecteren">` : ''}
                    <span class="table-name" title="${this._escapeHtml(tableName)}">${this._escapeHtml(tableName)}${aliasDisplay}</span>
                    ${advancedMode ? `<button type="button" class="alias-edit-btn" title="Alias bewerken"><span class="lnr lnr-pencil"></span></button>` : ''}
                    <button type="button" class="remove-btn" title="Verwijderen">&times;</button>
                `;
                // Set indeterminate state via JS after render
                setTimeout(() => {
                    const checkbox = header.querySelector('.header-checkbox');
                    if (checkbox && someFieldsSelected && !allFieldsSelected) {
                        checkbox.indeterminate = true;
                    }
                }, 0);
            }
            tableEl.appendChild(header);

            // Columns - show loading state if no columns yet, otherwise show columns
            if (showColumns) {
                if (tableInfo.columns.length > 0) {
                    const columnsEl = document.createElement('div');
                    columnsEl.className = 'schema-table-columns';

                    // Apply stored height if available
                    if (tableSize && tableSize.height) {
                        columnsEl.style.maxHeight = tableSize.height + 'px';
                    }

                    for (const col of tableInfo.columns) {
                        const isPK = tableInfo.primaryKeys.includes(col.name);
                        const isSelected = selectedFields.has(col.name);

                        const colEl = document.createElement('div');
                        colEl.className = 'schema-table-column' + (selectableFields ? ' selectable' : '') + (isSelected ? ' selected' : '');
                        colEl.dataset.column = col.name;
                        colEl.innerHTML = `
                            ${selectableFields ? `<input type="checkbox" class="field-checkbox" ${isSelected ? 'checked' : ''}>` : ''}
                            <span class="col-name${isPK ? ' pk' : ''}" title="${this._escapeHtml(col.name)}">${this._escapeHtml(col.name)}</span>
                            <span class="col-type">${this._escapeHtml(this._translateType(col.typeCategory || col.type || ''))}</span>
                        `;
                        columnsEl.appendChild(colEl);
                    }

                    // Add scroll listener to update relationship lines
                    columnsEl.addEventListener('scroll', () => this._updateRelationshipLines());

                    tableEl.appendChild(columnsEl);
                } else {
                    // Show loading placeholder
                    const loadingEl = document.createElement('div');
                    loadingEl.className = 'schema-table-loading';
                    loadingEl.innerHTML = '<span class="spinner"></span>Laden...';
                    tableEl.appendChild(loadingEl);
                }
            }

            // Add resize handle
            const resizeHandle = document.createElement('div');
            resizeHandle.className = 'resize-handle';
            resizeHandle.dataset.table = tableName;
            tableEl.appendChild(resizeHandle);

            container.appendChild(tableEl);
        }
    }

    _updateRelationshipLines() {
        const svg = this.shadowRoot.querySelector('.relationship-svg');
        if (!svg) return;

        const showRelationships = this.getAttribute('show-relationships') !== 'false';
        if (!showRelationships) {
            svg.innerHTML = '';
            return;
        }

        // Filter relationships to only show those between selected tables
        const selectedTables = this.getSelectedTables();
        const visibleRelationships = this._relationships.filter(rel =>
            selectedTables.includes(rel.from.table) && selectedTables.includes(rel.to.table)
        );

        cmaLog.log('[cma-schema-canvas] Updating relationship lines:', {
            totalRelationships: this._relationships.length,
            selectedTables,
            visibleRelationships: visibleRelationships.length
        });

        const container = this.shadowRoot.querySelector('.canvas-container');
        const containerScrollLeft = container ? container.scrollLeft : 0;
        const containerScrollTop = container ? container.scrollTop : 0;

        // Build SVG paths
        let paths = '';
        for (const rel of visibleRelationships) {
            const fromTable = this.shadowRoot.querySelector(`.schema-table[data-table="${CSS.escape(rel.from.table)}"]`);
            const toTable = this.shadowRoot.querySelector(`.schema-table[data-table="${CSS.escape(rel.to.table)}"]`);

            if (!fromTable || !toTable) continue;

            // Find the specific column elements
            const fromColumn = fromTable.querySelector(`.schema-table-column[data-column="${CSS.escape(rel.from.column)}"]`);
            const toColumn = toTable.querySelector(`.schema-table-column[data-column="${CSS.escape(rel.to.column)}"]`);

            // Get the columns container for clipping detection
            const fromColumnsContainer = fromTable.querySelector('.schema-table-columns');
            const toColumnsContainer = toTable.querySelector('.schema-table-columns');

            // Calculate connection points - connect to specific fields if visible
            // Use getBoundingClientRect for accurate positioning
            let fromX, fromY, toX, toY;
            let fromVisible = true, toVisible = true;

            // Get container rect for converting absolute coords to canvas-relative
            const containerRect = container.getBoundingClientRect();

            // Determine table positions relative to each other
            const fromTableLeft = fromTable.offsetLeft;
            const fromTableRight = fromTable.offsetLeft + fromTable.offsetWidth;
            const toTableLeft = toTable.offsetLeft;
            const toTableRight = toTable.offsetLeft + toTable.offsetWidth;

            // Check if tables overlap horizontally (similar X positions)
            const horizontalOverlap = !(fromTableRight < toTableLeft || toTableRight < fromTableLeft);

            // Determine connection sides:
            // - If tables overlap horizontally: both connect from left side (curves left)
            // - Otherwise: from=right to=left if from is to the left, else from=left to=right
            let fromSide, toSide;
            if (horizontalOverlap) {
                // Tables at similar X positions - connect both from left for cleaner curves
                fromSide = 'left';
                toSide = 'left';
            } else if (fromTableRight <= toTableLeft) {
                // From table is to the left of to table
                fromSide = 'right';
                toSide = 'left';
            } else {
                // From table is to the right of to table
                fromSide = 'left';
                toSide = 'right';
            }

            if (fromColumn && fromColumnsContainer) {
                // Use getBoundingClientRect for accurate position
                const colRect = fromColumn.getBoundingClientRect();
                const containerBounds = fromColumnsContainer.getBoundingClientRect();

                // Calculate Y position, clamping to visible area of columns container
                const colCenterY = colRect.top + colRect.height / 2;
                const clampedY = Math.max(containerBounds.top, Math.min(containerBounds.bottom, colCenterY));

                // Convert to canvas-relative coordinates
                fromY = clampedY - containerRect.top + containerScrollTop;
                fromX = fromSide === 'right' ? fromTableRight : fromTableLeft;
            } else {
                // Fallback: connect to table header area
                const headerRect = fromTable.querySelector('.schema-table-header')?.getBoundingClientRect();
                if (headerRect) {
                    fromY = headerRect.bottom - containerRect.top + containerScrollTop;
                    fromX = fromSide === 'right' ? fromTableRight : fromTableLeft;
                } else {
                    fromVisible = false;
                }
            }

            if (toColumn && toColumnsContainer) {
                // Use getBoundingClientRect for accurate position
                const colRect = toColumn.getBoundingClientRect();
                const containerBounds = toColumnsContainer.getBoundingClientRect();

                // Calculate Y position, clamping to visible area of columns container
                const colCenterY = colRect.top + colRect.height / 2;
                const clampedY = Math.max(containerBounds.top, Math.min(containerBounds.bottom, colCenterY));

                // Convert to canvas-relative coordinates
                toY = clampedY - containerRect.top + containerScrollTop;
                toX = toSide === 'left' ? toTableLeft : toTableRight;
            } else {
                // Fallback: connect to table header area
                const headerRect = toTable.querySelector('.schema-table-header')?.getBoundingClientRect();
                if (headerRect) {
                    toY = headerRect.bottom - containerRect.top + containerScrollTop;
                    toX = toSide === 'left' ? toTableLeft : toTableRight;
                } else {
                    toVisible = false;
                }
            }

            // If we couldn't find positions, skip
            if (!fromVisible || !toVisible) {
                continue;
            }

            // Create curved path
            const midX = (fromX + toX) / 2;
            const path = `M ${fromX} ${fromY} C ${midX} ${fromY}, ${midX} ${toY}, ${toX} ${toY}`;

            // Calculate label positions (offset from endpoints)
            const labelOffset = 12;
            const fromLabelX = fromSide === 'right' ? fromX + labelOffset : fromX - labelOffset;
            const toLabelX = toSide === 'left' ? toX - labelOffset : toX + labelOffset;

            // Data attributes for click handling
            const dataAttrs = `data-from="${this._escapeHtml(rel.from.table)}.${this._escapeHtml(rel.from.column)}"
                          data-to="${this._escapeHtml(rel.to.table)}.${this._escapeHtml(rel.to.column)}"`;

            // Determine join type class (dotted for LEFT JOIN)
            const isInnerJoin = rel.innerJoin !== false;
            const joinClass = isInnerJoin ? '' : ' left-join';

            // Add invisible wider hit area first (so it's behind the visible line)
            paths += `<path class="relationship-line-hitarea" d="${path}" ${dataAttrs}/>`;

            // Add circle at FK/many side, just line at PK/one side
            paths += `<circle class="relationship-endpoint${joinClass}" cx="${fromX}" cy="${fromY}" r="6" ${dataAttrs}/>`;
            paths += `<path class="relationship-line${joinClass}" d="${path}" ${dataAttrs}/>`;

            // Add cardinality labels: ∞ at FK (from/many), 1 at PK (to/one)
            paths += `<text class="relationship-label" x="${fromLabelX}" y="${fromY + 4}" text-anchor="middle">∞</text>`;
            paths += `<text class="relationship-label" x="${toLabelX}" y="${toY + 4}" text-anchor="middle">1</text>`;
        }

        svg.innerHTML = paths;

        // Update the relationships info panel
        this._updateRelationshipsPanel(visibleRelationships);
    }

    /**
     * Update the relationships info panel in the top-right corner
     */
    _updateRelationshipsPanel(visibleRelationships) {
        const panel = this.shadowRoot.querySelector('.relationships-panel');
        const content = this.shadowRoot.querySelector('.relationships-panel-content');
        if (!panel || !content) return;

        const selectedCount = this._selectedTables.size;

        // Only show when 2+ tables are selected
        if (selectedCount < 2) {
            panel.classList.add('hidden');
            return;
        }

        // Check if user has closed the panel this session
        if (this._relationshipsPanelClosed) {
            return;
        }

        panel.classList.remove('hidden');

        // Find unrelated tables (tables not appearing in any relationship)
        const tablesInRelationships = new Set();
        for (const rel of visibleRelationships) {
            tablesInRelationships.add(rel.from.table);
            tablesInRelationships.add(rel.to.table);
        }
        const unrelatedTables = [...this._selectedTables].filter(t => !tablesInRelationships.has(t));

        if (visibleRelationships.length === 0) {
            content.innerHTML = '<div class="no-relations">Geen relaties gevonden tussen de geselecteerde tabellen, dit zal leiden tot heel veel resultaten, er wordt dan namelijk een kruistabel aangemaakt.</div>';
        } else {
            // Header row with cardinality indicators (Hoofd/1 first, then Gerelateerd/∞)
            const header = `<div class="rel-header">
                <span class="cardinality-one">Hoofd (1)</span>
                <span class="cardinality-many">Gerelateerd (∞)</span>
            </div>`;
            const items = visibleRelationships.map((rel, index) => {
                const joinType = rel.innerJoin !== false ? 'INNER' : 'LEFT';
                const joinClass = rel.innerJoin !== false ? 'join-inner' : 'join-left';
                // Use = for INNER JOIN, ⊃ for LEFT JOIN
                const joinSymbol = rel.innerJoin !== false ? '=' : '⊃';
                return `<div class="rel-item ${joinClass}" data-rel-index="${index}"
                     data-from-table="${this._escapeHtml(rel.from.table)}"
                     data-from-column="${this._escapeHtml(rel.from.column)}"
                     data-to-table="${this._escapeHtml(rel.to.table)}"
                     data-to-column="${this._escapeHtml(rel.to.column)}"
                     data-inner-join="${rel.innerJoin !== false}"
                     title="Klik om te wijzigen (${joinType} JOIN)">
                    <span><strong>${this._escapeHtml(this._displayTableName(rel.to.table))}</strong>.${this._escapeHtml(rel.to.column)}</span>
                    <span class="rel-cardinality">1</span>
                    <span class="rel-join-symbol">${joinSymbol}</span>
                    <span class="rel-cardinality">∞</span>
                    <span><strong>${this._escapeHtml(this._displayTableName(rel.from.table))}</strong>.${this._escapeHtml(rel.from.column)}</span>
                </div>`;
            }).join('');

            // Add warning for unrelated tables
            let warning = '';
            if (unrelatedTables.length > 0) {
                const tableList = unrelatedTables.map(t => `<strong>${this._escapeHtml(this._displayTableName(t))}</strong>`).join(', ');
                warning = `<div class="unrelated-warning">
                    ${unrelatedTables.length === 1 ? 'Tabel' : 'Tabellen'} ${tableList}
                    ${unrelatedTables.length === 1 ? 'heeft' : 'hebben'} geen relatie met de andere tabellen.
                    Voeg een relatie toe of de query geeft mogelijk onverwachte resultaten.
                </div>`;
            }

            content.innerHTML = header + items + warning;
        }
    }

    /**
     * Open the add relationship dialog with grouped field selects
     * Uses lib-dialog component for consistent styling
     * @param {Object} [editRel] - Optional existing relationship to edit
     */
    _openAddRelationshipDialog(editRel = null) {
        // Store edit state
        this._editingRelationship = editRel;

        // Build grouped options from selected tables, optionally excluding a specific table
        // Filter out text and memo fields - they shouldn't be used for relationships
        const buildOptionsHtml = (excludeTable) => {
            let html = '<option value="">-- Selecteer veld --</option>';
            for (const tableName of this._selectedTables) {
                if (excludeTable && tableName === excludeTable) continue;
                const tableInfo = this._tables.get(tableName);
                if (tableInfo && tableInfo.columns && tableInfo.columns.length > 0) {
                    // Only exclude binary columns - any other type can be a join key
                    const validColumns = tableInfo.columns.filter(col => {
                        const type = (col.typeCategory || '').toLowerCase();
                        return type !== 'binary';
                    });
                    if (validColumns.length > 0) {
                        html += `<optgroup label="${this._escapeHtml(this._displayTableName(tableName))}">`;
                        for (const col of validColumns) {
                            const value = `${tableName}.${col.name}`;
                            html += `<option value="${this._escapeHtml(value)}">${this._escapeHtml(col.name)}</option>`;
                        }
                        html += '</optgroup>';
                    }
                }
            }
            return html;
        };

        const optionsHtml = buildOptionsHtml(null);

        // Remove existing relationship dialog if present
        const dialogId = 'cma-rel-dialog-' + this._instanceId;
        const existingDialog = document.getElementById(dialogId);
        if (existingDialog) existingDialog.remove();

        // Create lib-dialog
        const dialog = document.createElement('lib-dialog');
        dialog.id = dialogId;
        dialog.setAttribute('heading', editRel ? 'Relatie wijzigen' : 'Relatie toevoegen');
        dialog.setAttribute('size', 'large');

        const fromValue = editRel ? `${editRel.from.table}.${editRel.from.column}` : '';
        const toValue = editRel ? `${editRel.to.table}.${editRel.to.column}` : '';
        const innerJoinValue = editRel ? (editRel.innerJoin !== false) : true; // Default to inner join

        dialog.innerHTML = `
            <style>
                .rel-dialog-form {
                    min-width: 400px;
                }
                .rel-dialog-row {
                    display: flex;
                    flex-direction: column;
                    gap: 16px;
                }
                .rel-dialog-field {
                    width: 100%;
                }
                .rel-dialog-field label {
                    display: block;
                    font-size: var(--font-size-xs);
                    font-weight: 500;
                    margin-bottom: 4px;
                    color: #666;
                }
                .rel-dialog-field label .cardinality {
                    color: #999;
                    font-weight: normal;
                }
                .rel-dialog-field select {
                    width: 100%;
                }
                .rel-dialog-join {
                    width: 100%;
                }
                .rel-dialog-join label {
                    display: block;
                    font-size: var(--font-size-xs);
                    font-weight: 500;
                    margin-bottom: 4px;
                    color: #666;
                }
                .rel-dialog-join select.rel-join-type {
                    width: 100%;
                    font-size: var(--font-size-sm);
                    padding: 6px 10px;
                    border: 1px solid #ccc;
                    border-radius: 4px;
                    background: #f9f9f9;
                }
                .rel-dialog-help {
                    margin-top: 16px;
                    padding: 12px;
                    background: #f5f8fc;
                    border: 1px solid #d0e0f0;
                    border-radius: 4px;
                    font-size: var(--font-size-sm);
                    color: #555;
                    min-height: 63px;
                }
                .rel-dialog-help .field-name {
                    font-weight: 600;
                    color: #204496;
                }
                .rel-dialog-help .result-type {
                    font-weight: 600;
                }
                .rel-dialog-help .result-type.include {
                    color: #28a745;
                }
                .rel-dialog-help .result-type.exclude {
                    color: #dc3545;
                }
                .btn, .btn-primary, .btn-secondary, .btn-cancel, button:not([class]) {
                    padding: 2px 12px;
                    height: 28px;
                }
            </style>
            <div class="rel-dialog-form">
                <div class="rel-dialog-row">
                    <div class="rel-dialog-field">
                        <label>Hoofdtabel veld <span class="cardinality">(1)</span></label>
                        <select class="rel-to">${optionsHtml}</select>
                    </div>
                    <div class="rel-dialog-join">
                        <label>Koppelwijze</label>
                        <select class="rel-join-type">
                            <option value="inner" ${innerJoinValue ? 'selected' : ''}>= (INNER)</option>
                            <option value="left" ${!innerJoinValue ? 'selected' : ''}>⊃ (LEFT)</option>
                        </select>
                    </div>
                    <div class="rel-dialog-field">
                        <label>Gerelateerd veld <span class="cardinality">(∞)</span></label>
                        <select class="rel-from">${optionsHtml}</select>
                    </div>
                </div>
                <div class="rel-dialog-help" id="relDialogHelp">
                    Selecteer beide velden om de relatie in te stellen.
                </div>
            </div>
            <div slot="footer" style="display: flex; justify-content: space-between; width: 100%;">
                <div>
                    ${editRel ? '<button class="btn btn-danger" data-action="delete" style="background:#dc3545;color:#fff;">Verwijderen</button>' : ''}
                </div>
                <div style="display: flex; gap: 8px;">
                    <button class="btn-cancel" data-action="cancel">Annuleren</button>
                    <button class="btn-primary" data-action="save" ${editRel ? '' : 'disabled'}>${editRel ? 'Opslaan' : 'Toevoegen'}</button>
                </div>
            </div>
        `;

        document.body.appendChild(dialog);

        const fromSelect = dialog.querySelector('.rel-from');
        const toSelect = dialog.querySelector('.rel-to');
        const saveBtn = dialog.querySelector('[data-action="save"]');
        const cancelBtn = dialog.querySelector('[data-action="cancel"]');

        // Initialize Select2 on the dropdowns (if jQuery and Select2 are available)
        // Note: dropdownParent must be document.body because lib-dialog uses Shadow DOM
        // with z-index 2000. We add CSS to ensure Select2 dropdown appears above it.
        let $fromSelect, $toSelect;
        if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
            // Add global CSS for dialog positioning and Select2 z-index if not already present
            if (!document.getElementById('cma-select2-dialog-fix')) {
                const style = document.createElement('style');
                style.id = 'cma-select2-dialog-fix';
                style.textContent = `
                    lib-dialog[id^="cma-rel-dialog-"] { position: absolute; z-index: 100000; }
                    .select2-container--open .select2-dropdown, .select2-drop.select2-drop-active { z-index: 100001 !important; }
                    .select2-container--open { z-index: 100001 !important; }
                `;
                document.head.appendChild(style);
            }

            $fromSelect = jQuery(fromSelect).select2({
                placeholder: '-- Selecteer veld --',
                allowClear: false,
                width: '100%',
                dropdownParent: jQuery(document.body)
            });
            $toSelect = jQuery(toSelect).select2({
                placeholder: '-- Selecteer veld --',
                allowClear: false,
                width: '100%',
                dropdownParent: jQuery(document.body)
            });
        }

        // Set initial values if editing - filter each dropdown to exclude the other's table
        if (editRel) {
            const toTable = toValue ? toValue.split('.')[0] : null;
            const fromTable = fromValue ? fromValue.split('.')[0] : null;

            // Filter child dropdown to exclude parent table
            if (toTable) {
                fromSelect.innerHTML = buildOptionsHtml(toTable);
            }
            // Filter parent dropdown to exclude child table
            if (fromTable) {
                toSelect.innerHTML = buildOptionsHtml(fromTable);
            }

            if ($fromSelect && $toSelect) {
                // Reinitialize Select2 with filtered options
                $fromSelect.select2('destroy');
                $toSelect.select2('destroy');
                $fromSelect = jQuery(fromSelect).select2({
                    placeholder: '-- Selecteer veld --',
                    allowClear: false,
                    width: '100%',
                    dropdownParent: jQuery(document.body)
                });
                $toSelect = jQuery(toSelect).select2({
                    placeholder: '-- Selecteer veld --',
                    allowClear: false,
                    width: '100%',
                    dropdownParent: jQuery(document.body)
                });
                $fromSelect.val(fromValue).trigger('change.select2');
                $toSelect.val(toValue).trigger('change.select2');
            } else {
                fromSelect.value = fromValue;
                toSelect.value = toValue;
            }
        }

        const joinTypeSelect = dialog.querySelector('.rel-join-type');
        const helpDiv = dialog.querySelector('#relDialogHelp');

        // Update help text based on current selections
        const updateHelpText = () => {
            const fromVal = $fromSelect ? $fromSelect.val() : fromSelect.value;
            const toVal = $toSelect ? $toSelect.val() : toSelect.value;
            const joinType = joinTypeSelect.value;

            if (!fromVal || !toVal) {
                helpDiv.innerHTML = 'Selecteer beide velden om de relatie in te stellen.';
                return;
            }

            const [fromTable, fromColumn] = fromVal.split('.');
            const [toTable, toColumn] = toVal.split('.');

            if (joinType === 'inner') {
                helpDiv.innerHTML = `Als <span class="field-name">${this._escapeHtml(fromColumn)}</span> niet bestaat in <span class="field-name">${this._escapeHtml(toTable)}</span>, wordt de rij <span class="result-type exclude">NIET</span> in het resultaat opgenomen.`;
            } else {
                helpDiv.innerHTML = `Als <span class="field-name">${this._escapeHtml(fromColumn)}</span> niet bestaat in <span class="field-name">${this._escapeHtml(toTable)}</span>, wordt de rij <span class="result-type include">WEL</span> in het resultaat opgenomen (met lege waarden).`;
            }
        };

        // Enable/disable save button based on selection
        const updateSaveButton = () => {
            const fromVal = $fromSelect ? $fromSelect.val() : fromSelect.value;
            const toVal = $toSelect ? $toSelect.val() : toSelect.value;
            saveBtn.disabled = !fromVal || !toVal;
            updateHelpText();
        };

        // When one dropdown changes, filter the other to exclude the selected table
        const filterOppositeDropdown = (changedSelect, oppositeSelect, $changed, $opposite) => {
            const val = $changed ? $changed.val() : changedSelect.value;
            const selectedTable = val ? val.split('.')[0] : null;

            // Remember current value of opposite dropdown
            const oppositeVal = $opposite ? $opposite.val() : oppositeSelect.value;

            // Rebuild opposite dropdown excluding the selected table
            const newHtml = buildOptionsHtml(selectedTable);

            if ($opposite) {
                $opposite.select2('destroy');
            }
            oppositeSelect.innerHTML = newHtml;

            // Restore value if still valid, otherwise clear
            if (oppositeVal) {
                const oppositeTable = oppositeVal.split('.')[0];
                if (oppositeTable !== selectedTable) {
                    oppositeSelect.value = oppositeVal;
                } else {
                    oppositeSelect.value = '';
                }
            }

            if ($opposite) {
                const $new = jQuery(oppositeSelect).select2({
                    placeholder: '-- Selecteer veld --',
                    allowClear: false,
                    width: '100%',
                    dropdownParent: jQuery(document.body)
                });
                if (oppositeSelect.value) {
                    $new.val(oppositeSelect.value).trigger('change.select2');
                }
                // Update reference
                if (oppositeSelect === fromSelect) {
                    $fromSelect = $new;
                    $fromSelect.on('change', onFromChange);
                } else {
                    $toSelect = $new;
                    $toSelect.on('change', onToChange);
                }
            }

            updateSaveButton();
        };

        const onFromChange = () => {
            filterOppositeDropdown(fromSelect, toSelect, $fromSelect, $toSelect);
        };
        const onToChange = () => {
            filterOppositeDropdown(toSelect, fromSelect, $toSelect, $fromSelect);
        };

        // Listen for changes (Select2 or native)
        if ($fromSelect && $toSelect) {
            $fromSelect.on('change', onFromChange);
            $toSelect.on('change', onToChange);
        } else {
            fromSelect.addEventListener('change', onFromChange);
            toSelect.addEventListener('change', onToChange);
        }

        // Listen for join type changes
        joinTypeSelect.addEventListener('change', updateHelpText);

        // Trigger initial update if editing (works for both Select2 and native)
        if (editRel) {
            // Small delay for Select2 to finish initializing
            setTimeout(updateHelpText, 50);
        }

        // Cancel button
        cancelBtn.addEventListener('click', () => {
            dialog.close(false);
        });

        // Delete button (only present when editing)
        const deleteBtn = dialog.querySelector('[data-action="delete"]');
        if (deleteBtn && editRel) {
            deleteBtn.addEventListener('click', async () => {
                const confirmed = await libConfirm('Weet je zeker dat je deze relatie wilt verwijderen?', {
                    title: 'Relatie verwijderen',
                    confirmText: 'Verwijderen',
                    cancelText: 'Annuleren',
                    type: 'danger'
                });

                if (confirmed) {
                    // Remove from _relationships array
                    const idx = this._relationships.findIndex(r =>
                        r.from.table === editRel.from.table &&
                        r.from.column === editRel.from.column &&
                        r.to.table === editRel.to.table &&
                        r.to.column === editRel.to.column
                    );
                    if (idx !== -1) {
                        this._relationships.splice(idx, 1);
                    }

                    // Also remove from table's relationships
                    const tableInfo = this._tables.get(editRel.from.table);
                    if (tableInfo && tableInfo.relationships) {
                        const tableIdx = tableInfo.relationships.findIndex(r =>
                            r.fkTable === editRel.from.table &&
                            r.fkColumn === editRel.from.column &&
                            r.pkTable === editRel.to.table &&
                            r.pkColumn === editRel.to.column
                        );
                        if (tableIdx !== -1) {
                            tableInfo.relationships.splice(tableIdx, 1);
                        }
                    }

                    // Update lines
                    this._updateRelationshipLines();

                    // Dispatch delete event
                    this.dispatchEvent(new CustomEvent('relationship-delete', {
                        bubbles: true,
                        detail: {
                            from: editRel.from,
                            to: editRel.to
                        }
                    }));

                    dialog.close(true);
                }
            });
        }

        // Save button
        saveBtn.addEventListener('click', () => {
            const fromVal = $fromSelect ? $fromSelect.val() : fromSelect.value;
            const toVal = $toSelect ? $toSelect.val() : toSelect.value;
            const innerJoin = joinTypeSelect.value === 'inner';

            if (fromVal && toVal) {
                const [fromTable, fromColumn] = fromVal.split('.');
                const [toTable, toColumn] = toVal.split('.');

                // If editing, remove the old relationship first
                if (this._editingRelationship) {
                    const oldRel = this._editingRelationship;
                    const idx = this._relationships.findIndex(r =>
                        r.from.table === oldRel.from.table &&
                        r.from.column === oldRel.from.column &&
                        r.to.table === oldRel.to.table &&
                        r.to.column === oldRel.to.column
                    );
                    if (idx !== -1) {
                        this._relationships.splice(idx, 1);
                    }

                    // Also remove from table's relationships
                    const oldTableInfo = this._tables.get(oldRel.from.table);
                    if (oldTableInfo && oldTableInfo.relationships) {
                        const tableIdx = oldTableInfo.relationships.findIndex(r =>
                            r.fkTable === oldRel.from.table &&
                            r.fkColumn === oldRel.from.column &&
                            r.pkTable === oldRel.to.table &&
                            r.pkColumn === oldRel.to.column
                        );
                        if (tableIdx !== -1) {
                            oldTableInfo.relationships.splice(tableIdx, 1);
                        }
                    }
                }

                // Add to relationships array
                this._relationships.push({
                    from: { table: fromTable, column: fromColumn },
                    to: { table: toTable, column: toColumn },
                    innerJoin: innerJoin,
                    manual: true
                });

                // Also add to the table's relationships so it persists
                const tableInfo = this._tables.get(fromTable);
                if (tableInfo) {
                    if (!tableInfo.relationships) {
                        tableInfo.relationships = [];
                    }
                    tableInfo.relationships.push({
                        fkTable: fromTable,
                        fkColumn: fromColumn,
                        pkTable: toTable,
                        pkColumn: toColumn,
                        innerJoin: innerJoin,
                        manual: true
                    });
                }

                // Update lines and panel
                this._updateRelationshipLines();

                // Dispatch change event so report-designer can pick up the new relationship
                this.dispatchEvent(new CustomEvent('relationship-add', {
                    bubbles: true,
                    detail: {
                        from: { table: fromTable, column: fromColumn },
                        to: { table: toTable, column: toColumn },
                        innerJoin: innerJoin
                    }
                }));

                dialog.close(true);
            }
        });

        // Open dialog
        dialog.open().then(() => {
            // Cleanup Select2 instances before removing dialog
            if ($fromSelect) {
                $fromSelect.select2('destroy');
            }
            if ($toSelect) {
                $toSelect.select2('destroy');
            }
            dialog.remove();
            this._editingRelationship = null;
        });
    }

    /**
     * Set relationships from parsed SQL JOIN clauses
     * Converts SQL join format to internal relationship format
     * @param {Array} joins - Array of parsed joins: [{table, type, on}, ...]
     */
    setRelationshipsFromJoins(joins) {
        if (!joins || joins.length === 0) {
            // No joins - clear relationships and use schema-based ones
            this._updateRelationshipsFromSelectedTables();
            this._updateRelationshipLines();
            return;
        }

        this._relationships = [];
        const processedPairs = new Set();

        for (const join of joins) {
            // Parse the ON condition to extract table.column pairs
            // ON condition format: "[table1].[column1] = [table2].[column2]"
            // or: "table1.column1 = table2.column2"
            const onCondition = join.on || '';

            // Try to extract both sides of the equality
            // Pattern matches: [table].[column] or table.column
            const pattern = /\[?(\w+)\]?\.\[?(\w+)\]?\s*=\s*\[?(\w+)\]?\.\[?(\w+)\]?/i;
            const match = onCondition.match(pattern);

            if (match) {
                const [, table1, column1, table2, column2] = match;

                // Determine which is FK (from) and which is PK (to)
                // The JOIN table is typically the FK table
                let fromTable, fromColumn, toTable, toColumn;

                if (table1.toLowerCase() === join.table.toLowerCase()) {
                    // table1 is the JOIN table (FK)
                    fromTable = table1;
                    fromColumn = column1;
                    toTable = table2;
                    toColumn = column2;
                } else {
                    // table2 is the JOIN table (FK)
                    fromTable = table2;
                    fromColumn = column2;
                    toTable = table1;
                    toColumn = column1;
                }

                // Create unique key to avoid duplicates
                const key = `${fromTable}.${fromColumn}->${toTable}.${toColumn}`;
                if (!processedPairs.has(key)) {
                    processedPairs.add(key);

                    // Determine if it's an inner join
                    const joinType = (join.type || 'INNER').toUpperCase();
                    const isInnerJoin = joinType === 'INNER';

                    this._relationships.push({
                        from: { table: fromTable, column: fromColumn },
                        to: { table: toTable, column: toColumn },
                        innerJoin: isInnerJoin
                    });
                }
            }
        }

        cmaLog.log('[cma-schema-canvas] Set relationships from parsed joins:', this._relationships);
        this._updateRelationshipLines();
    }

    /**
     * Load relationships for all selected tables
     * Aggregates relationships from each table's schema
     */
    _updateRelationshipsFromSelectedTables() {
        this._relationships = [];
        const processedPairs = new Set();

        for (const tableName of this._selectedTables) {
            const tableInfo = this._tables.get(tableName);
            cmaLog.log('[cma-schema-canvas] Table relationships for', tableName, ':', tableInfo?.relationships);
            if (tableInfo && tableInfo.relationships) {
                for (const rel of tableInfo.relationships) {
                    // Create a unique key for this relationship
                    const key = `${rel.fkTable}.${rel.fkColumn}->${rel.pkTable}.${rel.pkColumn}`;
                    if (!processedPairs.has(key)) {
                        processedPairs.add(key);
                        this._relationships.push({
                            from: { table: rel.fkTable, column: rel.fkColumn },
                            to: { table: rel.pkTable, column: rel.pkColumn },
                            innerJoin: rel.innerJoin !== false // Default to inner join for DB-discovered relationships
                        });
                    }
                }
            }
        }

        cmaLog.log('[cma-schema-canvas] Total relationships found:', this._relationships.length, this._relationships);
    }

    _getNextPosition() {
        // Find positions of currently selected tables only
        const existingPositions = [];
        for (const tableName of this._selectedTables) {
            const tableInfo = this._tables.get(tableName);
            if (tableInfo && tableInfo.position) {
                existingPositions.push(tableInfo.position);
            }
        }

        // Grid-based positioning with space reserved for relationship lines
        // Left padding: 60px to allow curves to the left
        // Column spacing: 280px to leave room between tables for relationship lines
        const leftPadding = 60;
        const topPadding = 40;
        const gridX = 280;  // Space between table columns (includes table width + gap for lines)
        const gridY = 280;  // Space between table rows
        const cols = Math.floor((this._canvasWidth - leftPadding - 100) / gridX);

        // Start from index 0 to find first available slot (fills gaps from removed tables)
        let index = 0;
        let attempts = 0;

        while (attempts < 50) {
            const row = Math.floor(index / cols);
            const col = index % cols;
            const x = leftPadding + col * gridX;
            const y = topPadding + row * gridY;

            // Check if position is free
            const isFree = !existingPositions.some(pos =>
                Math.abs(pos.x - x) < 100 && Math.abs(pos.y - y) < 100
            );

            if (isFree) {
                return { x, y };
            }

            index++;
            attempts++;
        }

        // Fallback: random position
        return {
            x: 40 + Math.random() * (this._canvasWidth - 300),
            y: 40 + Math.random() * (this._canvasHeight - 300)
        };
    }

    _setupEventListeners() {
        const container = this.shadowRoot.querySelector('.canvas-container');
        if (!container) return;

        // Scroll listener on canvas container to update relationship lines
        container.addEventListener('scroll', () => this._updateRelationshipLines());

        // Relationships panel toggle button (minimize/restore)
        const toggleBtn = this.shadowRoot.querySelector('.relationships-panel-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                const panel = this.shadowRoot.querySelector('.relationships-panel');
                if (panel) {
                    panel.classList.toggle('minimized');
                }
            });
        }

        // Relationships panel dragging
        const panelHeader = this.shadowRoot.querySelector('.relationships-panel-header');
        const panel = this.shadowRoot.querySelector('.relationships-panel');
        if (panelHeader && panel) {
            let isDraggingPanel = false;
            let panelDragOffset = { x: 0, y: 0 };

            panelHeader.addEventListener('mousedown', (e) => {
                // Ignore if clicking the toggle button
                if (e.target.closest('.relationships-panel-toggle')) return;

                isDraggingPanel = true;
                panel.classList.add('dragging');

                const rect = panel.getBoundingClientRect();
                const containerRect = container.getBoundingClientRect();
                panelDragOffset = {
                    x: e.clientX - rect.left,
                    y: e.clientY - rect.top
                };

                e.preventDefault();
            });

            document.addEventListener('mousemove', (e) => {
                if (!isDraggingPanel) return;

                const containerRect = container.getBoundingClientRect();
                let newX = e.clientX - containerRect.left - panelDragOffset.x;
                let newY = e.clientY - containerRect.top - panelDragOffset.y;

                // Keep panel within container bounds
                const panelRect = panel.getBoundingClientRect();
                newX = Math.max(0, Math.min(newX, containerRect.width - panelRect.width));
                newY = Math.max(0, Math.min(newY, containerRect.height - panelRect.height));

                // Snap to grid (20px) - same as tables
                newX = Math.round(newX / 20) * 20;
                newY = Math.round(newY / 20) * 20;

                // Switch from right/top to left/top positioning
                panel.style.right = 'auto';
                panel.style.left = newX + 'px';
                panel.style.top = newY + 'px';
            });

            document.addEventListener('mouseup', () => {
                if (isDraggingPanel) {
                    isDraggingPanel = false;
                    panel.classList.remove('dragging');

                    // Dispatch position change event
                    const containerRect = container.getBoundingClientRect();
                    this.dispatchEvent(new CustomEvent('panel-position-change', {
                        bubbles: true,
                        detail: {
                            left: parseInt(panel.style.left, 10) || 0,
                            top: parseInt(panel.style.top, 10) || 0
                        }
                    }));
                }
            });
        }

        // Add relationship button
        const addRelBtn = this.shadowRoot.querySelector('.relationships-panel-add');
        if (addRelBtn) {
            addRelBtn.addEventListener('click', () => {
                this._openAddRelationshipDialog();
            });
        }

        // Click on relationship items in panel to edit
        const relContent = this.shadowRoot.querySelector('.relationships-panel-content');
        if (relContent) {
            relContent.addEventListener('click', (e) => {
                const relItem = e.target.closest('.rel-item');
                if (relItem) {
                    const rel = {
                        from: {
                            table: relItem.dataset.fromTable,
                            column: relItem.dataset.fromColumn
                        },
                        to: {
                            table: relItem.dataset.toTable,
                            column: relItem.dataset.toColumn
                        }
                    };
                    this._openAddRelationshipDialog(rel);
                }
            });
        }

        // Click on SVG relationship lines to edit
        const svg = this.shadowRoot.querySelector('.relationship-svg');
        if (svg) {
            svg.addEventListener('click', (e) => {
                // Handle clicks on relationship lines, hit areas, or endpoints
                const element = e.target.closest('.relationship-line, .relationship-line-hitarea, .relationship-endpoint');
                if (element) {
                    const fromParts = element.dataset.from?.split('.') || [];
                    const toParts = element.dataset.to?.split('.') || [];
                    if (fromParts.length === 2 && toParts.length === 2) {
                        const rel = {
                            from: { table: fromParts[0], column: fromParts[1] },
                            to: { table: toParts[0], column: toParts[1] }
                        };
                        this._openAddRelationshipDialog(rel);
                    }
                }
            });
        }

        // Remove button click
        container.addEventListener('click', (e) => {
            const removeBtn = e.target.closest('.remove-btn');
            if (removeBtn) {
                const tableEl = removeBtn.closest('.schema-table');
                if (tableEl) {
                    this.removeTable(tableEl.dataset.table);
                }
                return;
            }

            // Header checkbox (select all/none)
            const headerCheckbox = e.target.closest('.header-checkbox');
            if (headerCheckbox) {
                const tableEl = headerCheckbox.closest('.schema-table');
                if (tableEl) {
                    const tableName = tableEl.dataset.table;
                    const tableInfo = this._tables.get(tableName);
                    if (tableInfo) {
                        const isChecked = headerCheckbox.checked;
                        if (!this._selectedFields.has(tableName)) {
                            this._selectedFields.set(tableName, new Set());
                        }
                        const selectedSet = this._selectedFields.get(tableName);

                        if (isChecked) {
                            // Select all fields
                            tableInfo.columns.forEach(col => selectedSet.add(col.name));
                        } else {
                            // Deselect all fields
                            selectedSet.clear();
                        }

                        this._renderCanvas();
                        this._updateRelationshipLines();
                        this._dispatchFieldSelectionChange(tableName);
                    }
                }
                return;
            }

            // Field checkbox
            const fieldCheckbox = e.target.closest('.field-checkbox');
            if (fieldCheckbox) {
                const colEl = fieldCheckbox.closest('.schema-table-column');
                const tableEl = fieldCheckbox.closest('.schema-table');
                if (colEl && tableEl) {
                    const tableName = tableEl.dataset.table;
                    const columnName = colEl.dataset.column;
                    this._toggleFieldSelection(tableName, columnName);
                }
                return;
            }

            // Column name click (toggle field selection)
            const colName = e.target.closest('.col-name');
            if (colName && this.getAttribute('selectable-fields') === 'true') {
                const colEl = colName.closest('.schema-table-column');
                const tableEl = colName.closest('.schema-table');
                if (colEl && tableEl) {
                    const tableName = tableEl.dataset.table;
                    const columnName = colEl.dataset.column;
                    this._toggleFieldSelection(tableName, columnName);
                }
                return;
            }

            // Alias edit button click
            const aliasEditBtn = e.target.closest('.alias-edit-btn');
            if (aliasEditBtn) {
                const tableEl = aliasEditBtn.closest('.schema-table');
                if (tableEl) {
                    this._editingAlias = tableEl.dataset.table;
                    this._renderCanvas();
                    this._updateRelationshipLines();

                    // Focus the input after render
                    setTimeout(() => {
                        const input = this.shadowRoot.querySelector('.alias-edit-input');
                        if (input) {
                            input.focus();
                            input.select();
                        }
                    }, 10);
                }
                return;
            }

            // Alias save button click
            const aliasSaveBtn = e.target.closest('.alias-save-btn');
            if (aliasSaveBtn) {
                const input = this.shadowRoot.querySelector('.alias-edit-input');
                if (input) {
                    const tableName = input.dataset.table;
                    // Sanitize alias - remove spaces
                    const newAlias = (typeof CMA !== 'undefined' && CMA.sanitizeAlias)
                        ? CMA.sanitizeAlias(input.value.trim())
                        : input.value.trim().replace(/\s+/g, '_');

                    if (newAlias && newAlias !== tableName) {
                        this._tableAliases.set(tableName, newAlias);
                    } else {
                        this._tableAliases.delete(tableName);
                    }

                    this._editingAlias = null;
                    this._renderCanvas();
                    this._updateRelationshipLines();
                    this._dispatchAliasChange(tableName, newAlias);
                }
                return;
            }

            // Alias cancel button click
            const aliasCancelBtn = e.target.closest('.alias-cancel-btn');
            if (aliasCancelBtn) {
                this._editingAlias = null;
                this._renderCanvas();
                this._updateRelationshipLines();
                return;
            }

            // Show more/less click
            const moreEl = e.target.closest('.schema-table-more');
            if (moreEl) {
                const tableName = moreEl.dataset.table;
                if (this._expandedTables.has(tableName)) {
                    this._expandedTables.delete(tableName);
                } else {
                    this._expandedTables.add(tableName);
                }
                this._updateTableElements();
                return;
            }

            // Table select
            const tableEl = e.target.closest('.schema-table');
            if (tableEl && !this._isDragging) {
                this.dispatchEvent(new CustomEvent('table-select', {
                    bubbles: true,
                    detail: {
                        tableName: tableEl.dataset.table,
                        tableInfo: this._tables.get(tableEl.dataset.table)
                    }
                }));
            }
        });

        // Handle Enter key in alias input
        container.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && e.target.classList.contains('alias-edit-input')) {
                const tableName = e.target.dataset.table;
                // Sanitize alias - remove spaces
                const newAlias = (typeof CMA !== 'undefined' && CMA.sanitizeAlias)
                    ? CMA.sanitizeAlias(e.target.value.trim())
                    : e.target.value.trim().replace(/\s+/g, '_');

                if (newAlias && newAlias !== tableName) {
                    this._tableAliases.set(tableName, newAlias);
                } else {
                    this._tableAliases.delete(tableName);
                }

                this._editingAlias = null;
                this._renderCanvas();
                this._updateRelationshipLines();
                this._dispatchAliasChange(tableName, newAlias);
            } else if (e.key === 'Escape' && e.target.classList.contains('alias-edit-input')) {
                this._editingAlias = null;
                this._renderCanvas();
                this._updateRelationshipLines();
            }
        });

        // Resize start
        container.addEventListener('mousedown', (e) => {
            const resizeHandle = e.target.closest('.resize-handle');
            if (resizeHandle) {
                const tableEl = resizeHandle.closest('.schema-table');
                if (tableEl) {
                    this._resizingTable = tableEl;
                    this._resizeStart = {
                        x: e.clientX,
                        y: e.clientY,
                        width: tableEl.offsetWidth,
                        height: tableEl.querySelector('.schema-table-columns')?.offsetHeight || 200
                    };
                    tableEl.classList.add('resizing');
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
            }

            // Drag start - from anywhere on the table (except interactive elements)
            const tableEl = e.target.closest('.schema-table');
            const isRemoveBtn = e.target.closest('.remove-btn');
            const isCheckbox = e.target.closest('input[type="checkbox"]');
            const isInput = e.target.closest('input[type="text"]');
            const isButton = e.target.closest('button');
            const isResizeHandle = e.target.closest('.resize-handle');

            if (tableEl && !isRemoveBtn && !isCheckbox && !isInput && !isButton && !isResizeHandle) {
                this._draggedTable = tableEl;
                this._isDragging = false;

                const rect = tableEl.getBoundingClientRect();
                const containerRect = container.getBoundingClientRect();

                this._dragOffset = {
                    x: e.clientX - rect.left,
                    y: e.clientY - rect.top
                };

                // Prevent text selection
                e.preventDefault();
            }
        });

        // Resize move
        container.addEventListener('mousemove', (e) => {
            // Handle resize
            if (this._resizingTable && this._resizeStart) {
                const deltaX = e.clientX - this._resizeStart.x;
                const deltaY = e.clientY - this._resizeStart.y;

                // Calculate new size
                const newWidth = Math.max(150, this._resizeStart.width + deltaX);
                const newHeight = Math.max(50, this._resizeStart.height + deltaY);

                // Apply size
                this._resizingTable.style.width = newWidth + 'px';
                this._resizingTable.style.minWidth = newWidth + 'px';
                this._resizingTable.style.maxWidth = newWidth + 'px';

                const columnsEl = this._resizingTable.querySelector('.schema-table-columns');
                if (columnsEl) {
                    columnsEl.style.maxHeight = newHeight + 'px';
                }

                // Update relationship lines during resize
                this._updateRelationshipLines();
                return;
            }

            // Handle drag
            if (!this._draggedTable) return;

            this._isDragging = true;
            this._draggedTable.classList.add('dragging');

            const containerRect = container.getBoundingClientRect();
            const scrollLeft = container.scrollLeft;
            const scrollTop = container.scrollTop;

            let x = e.clientX - containerRect.left + scrollLeft - this._dragOffset.x;
            let y = e.clientY - containerRect.top + scrollTop - this._dragOffset.y;

            // Constrain to canvas
            x = Math.max(0, Math.min(x, this._canvasWidth - this._draggedTable.offsetWidth));
            y = Math.max(0, Math.min(y, this._canvasHeight - this._draggedTable.offsetHeight));

            // Snap to grid (20px)
            x = Math.round(x / 20) * 20;
            y = Math.round(y / 20) * 20;

            this._draggedTable.style.left = x + 'px';
            this._draggedTable.style.top = y + 'px';

            // Update stored position
            const tableName = this._draggedTable.dataset.table;
            const tableInfo = this._tables.get(tableName);
            if (tableInfo) {
                tableInfo.position = { x, y };
            }

            // Update relationship lines
            this._updateRelationshipLines();
        });

        // Drag/resize end
        const endDragOrResize = () => {
            // Handle resize end
            if (this._resizingTable) {
                const tableName = this._resizingTable.dataset.table;
                const columnsEl = this._resizingTable.querySelector('.schema-table-columns');

                // Store the size
                this._tableSizes.set(tableName, {
                    width: this._resizingTable.offsetWidth,
                    height: columnsEl ? columnsEl.offsetHeight : 200
                });

                this._resizingTable.classList.remove('resizing');
                this._resizingTable = null;
                this._resizeStart = null;

                // Dispatch size change event
                this.dispatchEvent(new CustomEvent('table-size-change', {
                    bubbles: true,
                    detail: {
                        tableName,
                        sizes: this.getTableSizes()
                    }
                }));

                this._updateRelationshipLines();
            }

            // Handle drag end
            if (this._draggedTable) {
                const tableName = this._draggedTable.dataset.table;
                this._draggedTable.classList.remove('dragging');
                this._draggedTable = null;

                // Dispatch positions-change event
                this.dispatchEvent(new CustomEvent('positions-change', {
                    bubbles: true,
                    detail: {
                        positions: this.getTablePositions(),
                        movedTable: tableName
                    }
                }));

                // Reset dragging flag after a short delay (to prevent click)
                setTimeout(() => {
                    this._isDragging = false;
                }, 50);
            }
        };

        container.addEventListener('mouseup', endDragOrResize);
        container.addEventListener('mouseleave', endDragOrResize);

        // Double-click to show all columns
        container.addEventListener('dblclick', (e) => {
            const moreEl = e.target.closest('.schema-table-more');
            if (moreEl) {
                const tableEl = moreEl.closest('.schema-table');
                const tableName = tableEl?.dataset.table;
                if (tableName) {
                    // Toggle max-columns for this table (show all)
                    const tableInfo = this._tables.get(tableName);
                    if (tableInfo) {
                        tableInfo.showAllColumns = !tableInfo.showAllColumns;
                        this._renderCanvas();
                    }
                }
            }
        });
    }

    // =========================================================================
    // Field Selection Methods
    // =========================================================================

    /**
     * Toggle field selection for a specific column
     */
    _toggleFieldSelection(tableName, columnName) {
        if (!this._selectedFields.has(tableName)) {
            this._selectedFields.set(tableName, new Set());
        }

        const selectedSet = this._selectedFields.get(tableName);
        if (selectedSet.has(columnName)) {
            selectedSet.delete(columnName);
        } else {
            selectedSet.add(columnName);
        }

        this._renderCanvas();
        this._updateRelationshipLines();
        this._dispatchFieldSelectionChange(tableName);
    }

    /**
     * Dispatch field selection change event
     */
    _dispatchFieldSelectionChange(tableName) {
        this.dispatchEvent(new CustomEvent('field-selection-change', {
            bubbles: true,
            detail: {
                tableName,
                selectedFields: Array.from(this._selectedFields.get(tableName) || []),
                allSelectedFields: this.getSelectedFields()
            }
        }));
    }

    /**
     * Dispatch alias change event
     */
    _dispatchAliasChange(tableName, newAlias) {
        this.dispatchEvent(new CustomEvent('alias-change', {
            bubbles: true,
            detail: {
                tableName,
                alias: newAlias,
                allAliases: this.getTableAliases()
            }
        }));
    }

    /**
     * Get all selected fields grouped by table
     * @returns {Object} { tableName: [columnName, ...], ... }
     */
    getSelectedFields() {
        const result = {};
        for (const [tableName, columns] of this._selectedFields) {
            if (columns.size > 0) {
                result[tableName] = Array.from(columns);
            }
        }
        return result;
    }

    /**
     * Set selected fields
     * @param {Object} fields - { tableName: [columnName, ...], ... }
     */
    setSelectedFields(fields) {
        this._selectedFields.clear();
        if (fields) {
            for (const [tableName, columns] of Object.entries(fields)) {
                this._selectedFields.set(tableName, new Set(columns));
            }
        }
        this._renderCanvas();
        this._updateRelationshipLines();
    }

    /**
     * Get all table aliases
     * @returns {Object} { tableName: alias, ... }
     */
    getTableAliases() {
        const result = {};
        for (const [tableName, alias] of this._tableAliases) {
            if (alias && alias !== tableName) {
                result[tableName] = alias;
            }
        }
        return result;
    }

    /**
     * Set table aliases
     * @param {Object} aliases - { tableName: alias, ... }
     */
    setTableAliases(aliases) {
        this._tableAliases.clear();
        if (aliases) {
            for (const [tableName, alias] of Object.entries(aliases)) {
                if (alias && alias !== tableName) {
                    this._tableAliases.set(tableName, alias);
                }
            }
        }
        this._renderCanvas();
        this._updateRelationshipLines();
    }

    /**
     * Get all table sizes
     * @returns {Object} { tableName: { width, height }, ... }
     */
    getTableSizes() {
        const result = {};
        for (const [tableName, size] of this._tableSizes) {
            result[tableName] = { ...size };
        }
        return result;
    }

    /**
     * Set table sizes
     * @param {Object} sizes - { tableName: { width, height }, ... }
     */
    setTableSizes(sizes) {
        this._tableSizes.clear();
        if (sizes) {
            for (const [tableName, size] of Object.entries(sizes)) {
                if (size && (size.width || size.height)) {
                    this._tableSizes.set(tableName, { ...size });
                }
            }
        }
        this._renderCanvas();
        this._updateRelationshipLines();
    }

    _escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
}

// Register the component
customElements.define('cma-schema-canvas', CmaSchemaCanvas);

} // End guard against double registration
