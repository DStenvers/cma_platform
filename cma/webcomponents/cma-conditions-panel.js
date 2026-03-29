/**
 * cma-conditions-panel Web Component
 *
 * Displays and manages WHERE clause conditions for the Query Designer.
 * Supports multiple conditions per field, inline editing, reordering, and bracket support.
 *
 * Usage:
 *   <cma-conditions-panel id="conditionsPanel"></cma-conditions-panel>
 *
 * Events:
 *   - conditions-change: Fired when conditions change
 *
 * Methods:
 *   - setConditions(conditions): Set the conditions list
 *   - getConditions(): Get current conditions with logic
 *   - setAvailableFields(fields): Set available fields for adding new conditions
 *   - validate(): Validate bracket matching
 */

// Guard against double registration
if (!customElements.get('cma-conditions-panel')) {

class CmaConditionsPanel extends HTMLElement {
    constructor() {
        super();
        // No shadow DOM - inherit styles from page

        // State
        this._conditions = []; // Array of {id, field, table, operator, value, logic, prefix, suffix}
        this._availableFields = []; // Fields that can have conditions added
        this._defaultLogic = 'AND';
        this._wherePreviewTimer = null;
    }

    connectedCallback() {
        this._render();
        this._setupEventListeners();
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Set conditions
     * @param {Array} conditions - Array of condition objects
     */
    setConditions(conditions) {
        this._conditions = (conditions || []).map((c, index) => ({
            id: c.id || 'cond_' + Date.now() + '_' + index,
            field: c.field || '',
            table: c.table || '',
            alias: c.alias || c.field || '',
            dataType: c.dataType,
            typeCategory: c.typeCategory || 'text',
            operator: c.operator || '',
            operatorLabel: c.operatorLabel || c.operator || '',
            value: c.value || '',
            value2: c.value2 || '',
            logic: c.logic || this._defaultLogic,
            prefix: c.prefix || '',
            suffix: c.suffix || ''
        }));
        this._renderConditions();
    }

    /**
     * Get current conditions
     * @returns {Array}
     */
    getConditions() {
        return this._conditions.map(c => ({
            id: c.id,
            field: c.field,
            table: c.table,
            alias: c.alias,
            dataType: c.dataType,
            typeCategory: c.typeCategory,
            operator: c.operator,
            value: c.value,
            value2: c.value2,
            logic: c.logic,
            prefix: c.prefix,
            suffix: c.suffix
        }));
    }

    /**
     * Set available fields for adding new conditions
     * @param {Array} fields - Array of field objects {table, field, alias, dataType, typeCategory}
     */
    setAvailableFields(fields) {
        this._availableFields = fields || [];
    }

    /**
     * Set default logic (AND or OR)
     * @param {string} logic
     */
    setDefaultLogic(logic) {
        this._defaultLogic = logic === 'OR' ? 'OR' : 'AND';
    }

    /**
     * Validate bracket matching
     * @returns {{valid: boolean, error: string|null}}
     */
    validate() {
        let openCount = 0;

        for (const cond of this._conditions) {
            openCount += (cond.prefix.match(/\(/g) || []).length;
            openCount -= (cond.suffix.match(/\)/g) || []).length;

            if (openCount < 0) {
                return { valid: false, error: 'Er staat een sluithaakje teveel' };
            }
        }

        if (openCount > 0) {
            const haakje = openCount === 1 ? 'haakje' : 'haakjes';
            return { valid: false, error: `Nog ${openCount} ${haakje} sluiten` };
        }

        return { valid: true, error: null };
    }

    /**
     * Auto-fix bracket matching
     */
    autoFixBrackets() {
        let openCount = 0;

        for (const cond of this._conditions) {
            openCount += (cond.prefix.match(/\(/g) || []).length;
            openCount -= (cond.suffix.match(/\)/g) || []).length;
        }

        if (openCount > 0 && this._conditions.length > 0) {
            const lastCond = this._conditions[this._conditions.length - 1];
            lastCond.suffix += ')'.repeat(openCount);
        } else if (openCount < 0 && this._conditions.length > 0) {
            const firstCond = this._conditions[0];
            firstCond.prefix = '('.repeat(-openCount) + firstCond.prefix;
        }

        this._renderConditions();
        this._dispatchChange();
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    _render() {
        this.innerHTML = `
            <style>
                cma-conditions-panel {
                    display: flex;
                    flex-direction: column;
                    height: 100%;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    font-size: var(--font-size);
                    margin-top: 0;
                }

                .conditions-container {
                    border: 1px solid var(--border-color, #ddd);
                    border-top: 0;
                    border-left: 0;
                    border-radius: 4px;
                    display: flex;
                    flex-direction: column;
                    height: 100%;
                    min-width: 320px;
                }

                .conditions-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 10px 12px;
                    background: var(--bg-surface-alt, #f8f9fa);
                    border-bottom: 1px solid var(--border-light, #eee);
                    font-weight: 600;
                    font-size: var(--font-size-sm);
                    height: 40px;
                    box-sizing: border-box;
                }

                .header-title {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                }

                .conditions-list {
                    flex: 1;
                    overflow-y: auto;
                    padding: 8px;
                }

                .condition-item {
                    display: flex;
                    flex-direction: column;
                    gap: 6px;
                    padding: 10px;
                    background: var(--bg-surface, #fff);
                    border: 1px solid var(--border-light, #eee);
                    border-radius: 4px;
                    position: relative;
                }

                .condition-item:hover {
                    border-color: var(--color-primary, #204496);
                }

                .condition-row {
                    display: flex;
                    flex-direction: column;
                    gap: 6px;
                }

                .condition-field-row {
                    display: flex;
                    align-items: center;
                    gap: 4px;
                }

                .condition-details {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    gap: 6px;
                    margin-left: 0;
                    width: 100%;
                }

                .condition-bracket {
                    font-family: monospace;
                    font-size: var(--font-size-lg);
                    font-weight: bold;
                    color: var(--color-primary, #204496);
                    min-width: 16px;
                    text-align: center;
                    cursor: pointer;
                }

                .condition-bracket:hover {
                    background: var(--bg-hover, #eef6ff);
                    border-radius: 2px;
                }

                .condition-field {
                    font-weight: 500;
                    color: var(--text-primary, #333);
                    flex: 1;
                    cursor: grab;
                    position: relative;
                    padding-right: 20px;
                }

                .condition-field:active {
                    cursor: grabbing;
                }

                .condition-field .field-remove {
                    position: absolute;
                    right: 0;
                    top: 4px;
                    width: 20px;
                    height: 20px;
                    font-size: var(--font-size-lg);
                    line-height: 19px;
                    text-align: center;
                    border-radius: 2px;
                    opacity: 0;
                    cursor: pointer;
                    transition: all 0.15s;
                }

                .condition-field:hover .field-remove {
                    opacity: 1;
                }

                .condition-field .field-remove:hover {
                    background: var(--color-danger, #dc3545);
                    color: white;
                }

                .condition-operator-select,
                .condition-value-input {
                    padding: 6px 8px;
                    border: 1px solid var(--border-color, #ddd);
                    border-radius: 4px;
                    font-size: var(--font-size-sm);
                    background: white;
                }

                .condition-operator-select {
                    min-width: 120px;
                }

                .condition-operator-select:focus,
                .condition-value-input:focus {
                    outline: none;
                    border-color: var(--color-primary, #204496);
                }

                .condition-value-input {
                    flex: 1;
                    min-width: 80px;
                }

                .condition-value-input:disabled {
                    background: #f5f5f5;
                    cursor: not-allowed;
                }

                .condition-details .condition-bracket.suffix {
                    margin-left: auto;
                }

                .condition-actions {
                    display: flex;
                    gap: 4px;
                }

                .action-btn {
                    width: 26px;
                    height: 26px;
                    padding: 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: var(--font-size-md);
                    border: 1px solid var(--border-color, #ddd);
                    border-radius: 4px;
                    background: var(--bg-surface, #fff);
                    cursor: pointer;
                    color: var(--text-muted, #666);
                    transition: all 0.15s;
                }

                .action-btn:hover {
                    background: var(--bg-hover, #eef6ff);
                    border-color: var(--color-primary, #204496);
                    color: var(--color-primary, #204496);
                }

                .action-btn:disabled {
                    opacity: 0.3;
                    cursor: not-allowed;
                }

                .action-btn.remove-btn:hover {
                    background: #fff0f0;
                    border-color: var(--color-danger, #dc3545);
                    color: var(--color-danger, #dc3545);
                }

                .condition-logic {
                    display: flex;
                    align-items: center;
                    padding: 6px 8px;
                    margin: 4px 0;
                }

                .logic-btn {
                    padding: 2px 8px;
                    font-size: var(--font-size-xs);
                    font-weight: 600;
                    border: 1px solid var(--border-color, #ddd);
                    border-radius: 3px;
                    background: #f8f9fa;
                    color: #333;
                    cursor: pointer;
                    transition: all 0.15s;
                }

                .logic-btn:hover {
                    background: #eef6ff;
                }

                .logic-btn.active {
                    background: var(--color-primary, #204496);
                    color: white;
                    border-color: var(--color-primary, #204496);
                }

                .bracket-btn {
                    width: 24px;
                    height: 24px;
                    padding: 0;
                    font-family: monospace;
                    font-size: var(--font-size-md);
                    font-weight: bold;
                    border: 1px solid var(--border-color, #ddd);
                    border-radius: 3px;
                    background: var(--bg-surface, #fff);
                    cursor: pointer;
                    color: var(--color-primary, #204496);
                }

                .bracket-btn:hover {
                    background: var(--bg-hover, #eef6ff);
                }

                .conditions-footer {
                    padding: 10px 12px;
                    border-top: 1px solid var(--border-light, #eee);
                    background: transparent;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                }

                .validation-status {
                    font-size: var(--font-size-sm);
                    display: flex;
                    align-items: center;
                    gap: 4px;
                }

                .validation-status.valid {
                    color: var(--color-success, #28a745);
                }

                .validation-status.invalid {
                    color: var(--color-danger, #dc3545);
                    width: 100%;
                }

                .validation-status.invalid .lnr-warning::before {
                    color: var(--color-error, #dc3545);
                }

                .empty-state {
                    padding: 30px 20px;
                    text-align: center;
                    color: var(--text-muted, #888);
                }

                .empty-icon {
                    font-size: 32px;
                    margin-bottom: 8px;
                    opacity: 0.5;
                }

                /* Grouped conditions visual */
                .condition-group {
                    border-left: 3px solid var(--color-primary, #204496);
                    margin-left: 8px;
                    padding-left: 8px;
                }

                .condition-group-1 { border-left-color: #204496; }
                .condition-group-2 { border-left-color: #28a745; }
                .condition-group-3 { border-left-color: #fd7e14; }
                .condition-group-4 { border-left-color: #6f42c1; }

                /* Preview pane */
                .conditions-preview {
                    height: auto;
                    border-top: 1px solid var(--border-light, #eee);
                    background: var(--bg-surface-alt, #f8f9fa);
                }

                .conditions-preview-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 6px 12px;
                    cursor: pointer;
                    user-select: none;
                }

                .conditions-preview-header:hover {
                    background: var(--bg-hover, #eef6ff);
                }

                .conditions-preview-title {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    font-size: var(--font-size-sm);
                    font-weight: 600;
                    color: var(--text-secondary, #666);
                }

                .conditions-preview-toggle {
                    font-size: var(--font-size-2xs);
                    color: var(--text-muted, #888);
                    transition: transform 0.2s;
                }

                .conditions-preview.collapsed .conditions-preview-toggle {
                    transform: rotate(-90deg);
                }

                .conditions-preview-content {
                    height: auto;
                    padding: 8px 12px;
                    font-family: 'Consolas', 'Monaco', monospace;
                    font-size: var(--font-size-xs);
                    color: var(--text-primary, #333);
                    background: var(--bg-surface, #fff);
                    border-top: 1px solid var(--border-light, #eee);
                    overflow-y: auto;
                    white-space: pre-wrap;
                    word-break: break-word;
                }

                .conditions-preview.collapsed .conditions-preview-content {
                    display: none;
                }

                .conditions-preview-content .keyword {
                    color: #204496;
                    font-weight: 600;
                }

                .conditions-preview-content .field {
                    color: #28a745;
                }

                .conditions-preview-content .value {
                    color: #d63384;
                }

                .conditions-preview-content .bracket {
                    color: #fd7e14;
                    font-weight: bold;
                }

                .conditions-preview-content .empty {
                    color: var(--text-muted, #888);
                    font-style: italic;
                }

                .conditions-preview-content .loading {
                    color: var(--text-muted, #888);
                    font-style: italic;
                }

                .conditions-preview-status {
                    display: flex;
                    align-items: center;
                    gap: 4px;
                    font-size: var(--font-size-xs);
                    padding: 4px 12px 8px;
                }

                .conditions-preview-status.valid {
                    color: var(--color-success, #28a745);
                }

                .conditions-preview-status.invalid {
                    color: var(--color-danger, #dc3545);
                }

                .conditions-preview-status:empty {
                    display: none;
                }
            </style>
            <div class="conditions-container">
                <div class="conditions-header">
                    <div class="header-title">
                        Voorwaarden
                    </div>
                </div>
                <div class="conditions-list" id="conditionsList">
                    <div class="empty-state">
                        <div>Klik op + naast een veld om een filter toe te voegen</div>
                    </div>
                </div>
                <div class="conditions-preview" id="conditionsPreview">
                    <div class="conditions-preview-header" id="previewHeader">
                        <span class="conditions-preview-title">Voorbeeld</span>
                        <span class="conditions-preview-toggle lnr lnr-chevron-down"></span>
                    </div>
                    <div class="conditions-preview-content" id="previewContent">
                        <span class="empty">Geen voorwaarden</span>
                    </div>
                    <div class="conditions-preview-status" id="validationStatus"></div>
                </div>
            </div>
        `;
    }

    _renderConditions() {
        const list = this.querySelector('#conditionsList');
        if (!list) return;

        if (this._conditions.length === 0) {
            list.innerHTML = `
                <div class="empty-state">
                    <div>Klik op + naast een veld om een filter toe te voegen</div>
                </div>
            `;
            this._updateValidationStatus();
            return;
        }

        // Calculate bracket depth for visual grouping
        let depth = 0;
        const conditionsWithDepth = this._conditions.map((c, index) => {
            const openBrackets = (c.prefix.match(/\(/g) || []).length;
            const closeBrackets = (c.suffix.match(/\)/g) || []).length;
            const startDepth = depth;
            depth += openBrackets;
            const maxDepth = depth;
            depth -= closeBrackets;
            return { ...c, index, startDepth, maxDepth };
        });

        // Build HTML with logic between items
        const items = [];
        conditionsWithDepth.forEach((c, i) => {
            // Add logic toggle between items
            // The logic property on condition[i] determines what comes BEFORE it
            // So the button between condition[i-1] and condition[i] controls condition[i].logic
            if (i > 0) {
                items.push(`
                    <div class="condition-logic">
                        <button type="button" class="logic-btn ${c.logic === 'AND' ? 'active' : ''}" data-logic="AND" data-index="${c.index}">AND</button>
                        <button type="button" class="logic-btn ${c.logic === 'OR' ? 'active' : ''}" data-logic="OR" data-index="${c.index}">OR</button>
                    </div>
                `);
            }

            const isFirst = i === 0;
            const isLast = i === conditionsWithDepth.length - 1;
            const noValueOperator = this._isNoValueOperator(c.operator);

            // Add condition item
            items.push(`
                <div class="condition-item ${c.maxDepth > 0 ? `condition-group condition-group-${Math.min(c.maxDepth, 4)}` : ''}" data-id="${c.id}" data-index="${c.index}" draggable="true">
                    <div class="condition-row">
                        <div class="condition-field-row">
                            <button type="button" class="bracket-btn add-open" title="( toevoegen" data-id="${c.id}">(</button>
                            <span class="condition-bracket prefix" title="Klik om haakje te verwijderen">${this._escapeHtml(c.prefix)}</span>
                            <span class="condition-field" data-id="${c.id}">${this._escapeHtml(c.alias || c.field)}<span class="field-remove" data-id="${c.id}">×</span></span>
                        </div>
                        <div class="condition-details">
                            <select class="condition-operator-select" data-id="${c.id}">
                                <option value="">-- Selecteer --</option>
                                ${this._getOperatorOptions(c.typeCategory, c.operator)}
                            </select>
                            ${c.typeCategory !== 'boolean' ? `<input type="text" class="condition-value-input"
                                   data-id="${c.id}"
                                   value="${this._escapeHtml(c.value)}"
                                   placeholder="Waarde"
                                   ${!c.operator || noValueOperator ? 'disabled' : ''}>` : ''}
                            <span class="condition-bracket suffix" title="Klik om haakje te verwijderen">${this._escapeHtml(c.suffix)}</span>
                            <button type="button" class="bracket-btn add-close" title=") toevoegen" data-id="${c.id}">)</button>
                            <div class="condition-actions">
                            </div>
                        </div>
                    </div>
                </div>
            `);
        });

        list.innerHTML = items.join('');

        // Update validation status
        this._updateValidationStatus();
    }

    _setupEventListeners() {
        // Preview pane toggle
        const previewHeader = this.querySelector('#previewHeader');
        if (previewHeader) {
            previewHeader.addEventListener('click', () => {
                const preview = this.querySelector('#conditionsPreview');
                if (preview) {
                    preview.classList.toggle('collapsed');
                }
            });
        }

        // Event delegation for condition list
        this.addEventListener('click', (e) => {
            const target = e.target;

            // Logic button click
            if (target.classList.contains('logic-btn')) {
                const index = parseInt(target.dataset.index, 10);
                const logic = target.dataset.logic;
                if (!isNaN(index) && this._conditions[index]) {
                    this._conditions[index].logic = logic;
                    this._renderConditions();
                    this._dispatchChange();
                }
            }

            // Add opening bracket
            if (target.classList.contains('add-open')) {
                const id = target.dataset.id;
                const cond = this._conditions.find(c => c.id === id);
                if (cond) {
                    cond.prefix += '(';
                    this._renderConditions();
                    this._dispatchChange();
                }
            }

            // Add closing bracket
            if (target.classList.contains('add-close')) {
                const id = target.dataset.id;
                const cond = this._conditions.find(c => c.id === id);
                if (cond) {
                    cond.suffix += ')';
                    this._renderConditions();
                    this._dispatchChange();
                }
            }

            // Click on bracket to remove
            if (target.classList.contains('condition-bracket')) {
                const item = target.closest('.condition-item');
                if (!item) return;
                const id = item.dataset.id;
                const cond = this._conditions.find(c => c.id === id);
                if (!cond) return;

                const isPrefix = target.classList.contains('prefix');
                if (isPrefix && cond.prefix.length > 0) {
                    cond.prefix = cond.prefix.slice(0, -1);
                } else if (!isPrefix && cond.suffix.length > 0) {
                    cond.suffix = cond.suffix.slice(0, -1);
                }
                this._renderConditions();
                this._dispatchChange();
            }

            // Move up button
            if (target.classList.contains('move-up-btn') && !target.disabled) {
                const id = target.dataset.id;
                this._moveCondition(id, -1);
            }

            // Move down button
            if (target.classList.contains('move-down-btn') && !target.disabled) {
                const id = target.dataset.id;
                this._moveCondition(id, 1);
            }

            // Remove button (legacy, kept for compatibility)
            if (target.classList.contains('remove-btn')) {
                const id = target.dataset.id;
                this._removeCondition(id);
            }

            // Field remove X button
            if (target.classList.contains('field-remove')) {
                e.stopPropagation();
                const id = target.dataset.id;
                this._removeCondition(id);
            }
        });

        // Drag and drop for reordering
        this._draggedId = null;

        this.addEventListener('dragstart', (e) => {
            const item = e.target.closest('.condition-item');
            if (!item) return;
            this._draggedId = item.dataset.id;
            item.style.opacity = '0.5';
            e.dataTransfer.effectAllowed = 'move';
        });

        this.addEventListener('dragend', (e) => {
            const item = e.target.closest('.condition-item');
            if (item) item.style.opacity = '1';
            this._draggedId = null;
        });

        this.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });

        this.addEventListener('drop', (e) => {
            e.preventDefault();
            if (!this._draggedId) return;

            const dropTarget = e.target.closest('.condition-item');
            if (!dropTarget || dropTarget.dataset.id === this._draggedId) return;

            const draggedIndex = this._conditions.findIndex(c => c.id === this._draggedId);
            const targetIndex = this._conditions.findIndex(c => c.id === dropTarget.dataset.id);

            if (draggedIndex === -1 || targetIndex === -1) return;

            // Remove and insert at new position
            const [moved] = this._conditions.splice(draggedIndex, 1);
            this._conditions.splice(targetIndex, 0, moved);

            // Update logic
            this._conditions.forEach((c, i) => {
                if (i === 0) {
                    c.logic = '';
                } else if (!c.logic) {
                    c.logic = this._defaultLogic;
                }
            });

            this._renderConditions();
            this._dispatchChange();
        });

        // Change event for select and input
        this.addEventListener('change', (e) => {
            const target = e.target;

            // Operator change
            if (target.classList.contains('condition-operator-select')) {
                const id = target.dataset.id;
                const cond = this._conditions.find(c => c.id === id);
                if (cond) {
                    cond.operator = target.value;
                    cond.operatorLabel = target.options[target.selectedIndex]?.text || target.value;

                    // Clear value if operator doesn't need it
                    if (this._isNoValueOperator(target.value)) {
                        cond.value = '';
                    }

                    this._renderConditions();
                    this._dispatchChange();
                }
            }

            // Value change
            if (target.classList.contains('condition-value-input')) {
                const id = target.dataset.id;
                const cond = this._conditions.find(c => c.id === id);
                if (cond) {
                    cond.value = target.value;
                    this._dispatchChange();
                }
            }
        });

        // Input event for live value updates
        this.addEventListener('input', (e) => {
            if (e.target.classList.contains('condition-value-input')) {
                const id = e.target.dataset.id;
                const cond = this._conditions.find(c => c.id === id);
                if (cond) {
                    cond.value = e.target.value;
                    // Don't dispatch on every keystroke - rely on change event
                }
            }
        });
    }

    _moveCondition(id, direction) {
        const index = this._conditions.findIndex(c => c.id === id);
        if (index === -1) return;

        const newIndex = index + direction;
        if (newIndex < 0 || newIndex >= this._conditions.length) return;

        // Swap conditions
        const temp = this._conditions[index];
        this._conditions[index] = this._conditions[newIndex];
        this._conditions[newIndex] = temp;

        // Update logic - first condition should have empty logic
        this._conditions.forEach((c, i) => {
            if (i === 0) {
                c.logic = '';
            } else if (!c.logic) {
                c.logic = this._defaultLogic;
            }
        });

        this._renderConditions();
        this._dispatchChange();
    }

    _removeCondition(id) {
        const index = this._conditions.findIndex(c => c.id === id);
        if (index === -1) return;

        this._conditions.splice(index, 1);

        // Update logic - first condition should have empty logic
        if (this._conditions.length > 0) {
            this._conditions[0].logic = '';
        }

        this._renderConditions();
        this._dispatchChange();
    }

    _getOperatorOptions(typeCategory, selectedOperator) {
        const operators = {
            text: [
                { value: '=', label: 'is gelijk aan' },
                { value: '<>', label: 'is niet gelijk aan' },
                { value: 'contains', label: 'bevat' },
                { value: 'starts', label: 'begint met' },
                { value: 'ends', label: 'eindigt met' },
                { value: 'empty', label: 'is leeg' },
                { value: 'notempty', label: 'is niet leeg' }
            ],
            number: [
                { value: '=', label: 'is gelijk aan' },
                { value: '<>', label: 'is niet gelijk aan' },
                { value: '<', label: 'kleiner dan' },
                { value: '>', label: 'groter dan' },
                { value: '<=', label: 'kleiner of gelijk aan' },
                { value: '>=', label: 'groter of gelijk aan' },
                { value: 'between', label: 'tussen' }
            ],
            date: [
                { value: '=', label: 'is gelijk aan' },
                { value: '<>', label: 'is niet gelijk aan' },
                { value: 'before', label: 'voor' },
                { value: 'after', label: 'na' },
                { value: 'between', label: 'tussen' },
                { value: 'today', label: 'vandaag' },
                { value: 'thisweek', label: 'deze week' },
                { value: 'thismonth', label: 'deze maand' }
            ],
            boolean: [
                { value: 'yes', label: 'ja' },
                { value: 'no', label: 'nee' }
            ]
        };

        const ops = operators[typeCategory] || operators.text;
        return ops.map(op =>
            `<option value="${op.value}" ${op.value === selectedOperator ? 'selected' : ''}>${this._escapeHtml(op.label)}</option>`
        ).join('');
    }

    _isNoValueOperator(operator) {
        return ['empty', 'notempty', 'today', 'thisweek', 'thismonth', 'yes', 'no'].includes(operator);
    }

    _updateValidationStatus() {
        const status = this.querySelector('#validationStatus');
        const preview = this.querySelector('#previewContent');

        // Update validation status - only show errors (bracket counting, etc.)
        if (status) {
            const result = this.validate();
            if (this._conditions.length === 0 || result.valid) {
                status.innerHTML = '';
                status.className = 'conditions-preview-status';
            } else {
                status.innerHTML = `<span class="lnr lnr-warning"></span> ${this._escapeHtml(result.error)}`;
                status.className = 'conditions-preview-status invalid';
            }
        }

        // Update preview - fetch actual SQL from server (debounced)
        if (preview) {
            if (this._conditions.length === 0) {
                preview.innerHTML = '<span class="empty">Geen voorwaarden</span>';
            } else {
                // Show loading indicator, then fetch from server
                preview.innerHTML = '<span class="loading">Laden...</span>';
                this._fetchWherePreview(preview);
            }
        }
    }

    /**
     * Fetch WHERE clause preview from server (debounced)
     * Uses actual SQL generation to ensure preview matches executed SQL
     */
    _fetchWherePreview(previewElement) {
        // Debounce to avoid too many API calls
        if (this._wherePreviewTimer) {
            clearTimeout(this._wherePreviewTimer);
        }

        this._wherePreviewTimer = setTimeout(() => {
            const definition = {
                conditions: this.getConditions()
            };

            fetch('api/report-query.php?action=getWhere', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(definition)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.where) {
                    // Format the WHERE clause with syntax highlighting
                    previewElement.innerHTML = this._formatWhereClause(data.where);
                } else {
                    // Fallback to local generation if server fails
                    previewElement.innerHTML = this._generateWherePreview();
                }
            })
            .catch(() => {
                // Fallback to local generation on error
                previewElement.innerHTML = this._generateWherePreview();
            });
        }, 150); // 150ms debounce
    }

    /**
     * Format WHERE clause with syntax highlighting
     */
    _formatWhereClause(sql) {
        if (!sql) return '';

        // Escape HTML first
        let html = this._escapeHtml(sql);

        // Highlight keywords
        const keywords = ['WHERE', 'AND', 'OR', 'LIKE', 'BETWEEN', 'IS NULL', 'IS NOT NULL', 'IN'];
        keywords.forEach(kw => {
            const regex = new RegExp('\\b' + kw + '\\b', 'gi');
            html = html.replace(regex, `<span class="keyword">${kw}</span>`);
        });

        // Highlight field names (in square brackets)
        html = html.replace(/\[([^\]]+)\]/g, '<span class="field">[$1]</span>');

        // Highlight string values (in single quotes)
        html = html.replace(/'([^']+)'/g, `<span class="value">'$1'</span>`);

        // Highlight numeric values (standalone numbers)
        html = html.replace(/\b(\d+)\b/g, '<span class="value">$1</span>');

        // Add line breaks for readability
        html = html.replace(/<span class="keyword">(AND|OR)<\/span>/g, '\n      <span class="keyword">$1</span>');

        return html;
    }

    _generateWherePreview() {
        if (this._conditions.length === 0) return '';

        const parts = [];
        this._conditions.forEach((c, i) => {
            let part = '';

            // Add logic (AND/OR) before all except first
            if (i > 0 && c.logic) {
                part += `<span class="keyword">${c.logic}</span> `;
            }

            // Add prefix brackets
            if (c.prefix) {
                part += `<span class="bracket">${this._escapeHtml(c.prefix)}</span>`;
            }

            // Add field
            const fieldName = c.table ? `${c.table}.${c.field}` : c.field;
            part += `<span class="field">${this._escapeHtml(fieldName)}</span>`;

            // Add operator and value
            if (c.operator) {
                const opDisplay = this._getOperatorDisplay(c.operator);
                part += ` <span class="keyword">${opDisplay}</span>`;

                if (!this._isNoValueOperator(c.operator) && c.value) {
                    // Don't quote numeric values
                    const isNumeric = c.typeCategory === 'number';
                    if (isNumeric) {
                        part += ` <span class="value">${this._escapeHtml(c.value)}</span>`;
                    } else {
                        part += ` <span class="value">'${this._escapeHtml(c.value)}'</span>`;
                    }
                }
            }

            // Add suffix brackets
            if (c.suffix) {
                part += `<span class="bracket">${this._escapeHtml(c.suffix)}</span>`;
            }

            parts.push(part);
        });

        return '<span class="keyword">WHERE</span> ' + parts.join('\n      ');
    }

    _getOperatorDisplay(operator) {
        const displayMap = {
            '=': '=',
            '<>': '<>',
            '<': '<',
            '>': '>',
            '<=': '<=',
            '>=': '>=',
            'contains': 'LIKE',
            'starts': 'LIKE',
            'ends': 'LIKE',
            'empty': 'IS NULL',
            'notempty': 'IS NOT NULL',
            'before': '<',
            'after': '>',
            'between': 'BETWEEN',
            'today': '= TODAY',
            'thisweek': 'IN THIS WEEK',
            'thismonth': 'IN THIS MONTH',
            'yes': '= <span class="value">TRUE</span>',
            'no': '= <span class="value">FALSE</span>'
        };
        return displayMap[operator] || operator;
    }

    _dispatchChange() {
        this.dispatchEvent(new CustomEvent('conditions-change', {
            bubbles: true,
            composed: true,
            detail: {
                conditions: this.getConditions(),
                defaultLogic: this._defaultLogic
            }
        }));
    }

    _escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Register the component
customElements.define('cma-conditions-panel', CmaConditionsPanel);

} // End guard against double registration
