/**
 * cma-param-config Web Component
 *
 * Parameter configuration component for the Query Designer.
 * Allows defining runtime parameters that users can fill in when running reports.
 *
 * Usage:
 *   <cma-param-config id="paramConfig"></cma-param-config>
 *
 * Attributes:
 *   - readonly: Make the component read-only
 *
 * Events:
 *   - param-add: Fired when a parameter is added
 *   - param-remove: Fired when a parameter is removed
 *   - param-change: Fired when a parameter property changes
 *   - change: Fired when parameters change
 *
 * Methods:
 *   - setParameters(params): Set the parameter list
 *   - getParameters(): Get current parameters
 *   - addParameter(): Add a new parameter
 *   - removeParameter(index): Remove a parameter
 */

// Guard against double registration
if (!customElements.get('cma-param-config')) {

class CmaParamConfig extends HTMLElement {
    static get observedAttributes() {
        return ['readonly'];
    }

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });

        // State
        this._parameters = [];
    }

    connectedCallback() {
        // Adopt shared styles if available
        if (typeof LibSharedStyles !== 'undefined' && LibSharedStyles.isSupported()) {
            LibSharedStyles.adopt(this.shadowRoot, 'base', 'button');
        }

        this._render();
        this._setupEventListeners();
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
     * Set the parameters
     * @param {Array} params - Array of parameter objects
     */
    setParameters(params) {
        this._parameters = (params || []).map((p, index) => ({
            name: p.name || '@param' + (index + 1),
            label: p.label || 'Parameter ' + (index + 1),
            type: p.type || 'text',
            default: p.default || '',
            required: p.required || false,
            _index: index
        }));

        this._renderList();
    }

    /**
     * Get current parameters
     * @returns {Array} Array of parameter objects
     */
    getParameters() {
        return this._parameters.map(p => ({
            name: p.name,
            label: p.label,
            type: p.type,
            default: p.default,
            required: p.required
        }));
    }

    /**
     * Add a new parameter
     */
    addParameter() {
        const newParam = {
            name: '@param' + (this._parameters.length + 1),
            label: 'Parameter ' + (this._parameters.length + 1),
            type: 'text',
            default: '',
            required: false,
            _index: this._parameters.length
        };

        this._parameters.push(newParam);
        this._renderList();

        this.dispatchEvent(new CustomEvent('param-add', {
            bubbles: true,
            detail: { parameter: newParam, index: this._parameters.length - 1 }
        }));

        this._dispatchChange();
    }

    /**
     * Remove a parameter by index
     * @param {number} index - Parameter index
     */
    removeParameter(index) {
        if (index >= 0 && index < this._parameters.length) {
            const removed = this._parameters.splice(index, 1)[0];

            // Re-index remaining parameters
            this._parameters.forEach((p, i) => p._index = i);

            this._renderList();

            this.dispatchEvent(new CustomEvent('param-remove', {
                bubbles: true,
                detail: { parameter: removed, index }
            }));

            this._dispatchChange();
        }
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    _render() {
        this.shadowRoot.innerHTML = `
            <style>
                :host {
                    display: block;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    font-size: var(--font-size);
                }

                * {
                    box-sizing: border-box;
                }

                /* Linearicons - from shared-icons.js */
                ${CMA.getIconStylesFor(['cog'])}

                .param-config-container {
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    overflow: hidden;
                }

                .param-header {
                    display: grid;
                    grid-template-columns: 150px 180px 100px 150px 80px 50px;
                    gap: 10px;
                    padding: 12px 15px;
                    background: #f8f9fa;
                    border-bottom: 1px solid #ddd;
                    font-weight: 600;
                    font-size: var(--font-size-sm);
                    color: #555;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }

                .param-list {
                    max-height: 400px;
                    overflow-y: auto;
                }

                .param-item {
                    display: grid;
                    grid-template-columns: 150px 180px 100px 150px 80px 50px;
                    gap: 10px;
                    padding: 12px 15px;
                    border-bottom: 1px solid #eee;
                    align-items: center;
                    transition: background 0.15s;
                }

                .param-item:last-child {
                    border-bottom: none;
                }

                .param-item:hover {
                    background: #f8f9fa;
                }

                .param-input {
                    width: 100%;
                    padding: 6px 10px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-size: var(--font-size);
                }

                .param-input:focus {
                    outline: none;
                    border-color: #204496;
                    box-shadow: 0 0 0 2px rgba(32, 68, 150, 0.1);
                }

                .param-select {
                    width: 100%;
                    padding: 6px 10px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-size: var(--font-size);
                    background: white;
                }

                .param-select:focus {
                    outline: none;
                    border-color: #204496;
                }

                lib-switch {
                    --switch-width: 36px;
                    --switch-height: 20px;
                }

                .param-remove {
                    padding: 6px 10px;
                    border: none;
                    background: #f0f0f0;
                    border-radius: 4px;
                    cursor: pointer;
                    color: #666;
                    font-size: var(--font-size-lg);
                    line-height: 1;
                    transition: all 0.15s;
                }

                .param-remove:hover {
                    background: var(--close-btn-hover-bg, red);
                    color: var(--close-btn-hover-color, white);
                }

                .param-footer {
                    padding: 12px 15px;
                    background: #f8f9fa;
                    border-top: 1px solid #ddd;
                }

                .add-param-btn {
                    padding: 8px 16px;
                    border: 1px solid #204496;
                    background: white;
                    color: #204496;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: var(--font-size);
                    font-weight: 500;
                    transition: all 0.15s;
                }

                .add-param-btn:hover {
                    background: #204496;
                    color: white;
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
                    margin-bottom: 15px;
                }

                .hint-text {
                    font-size: var(--font-size-xs);
                    color: #888;
                    margin-top: 4px;
                }

                /* Readonly state */
                :host([readonly]) .param-input,
                :host([readonly]) .param-select,
                :host([readonly]) lib-switch,
                :host([readonly]) .param-remove,
                :host([readonly]) .add-param-btn {
                    pointer-events: none;
                    opacity: 0.7;
                }
            </style>
            <div class="param-config-container">
                <div class="param-header">
                    <span>Naam</span>
                    <span>Label</span>
                    <span>Type</span>
                    <span>Standaard</span>
                    <span>Verplicht</span>
                    <span></span>
                </div>
                <div class="param-list" id="paramList">
                </div>
                <div class="param-footer">
                    <button type="button" class="add-param-btn" id="addParamBtn">
                        + Parameter toevoegen
                    </button>
                </div>
            </div>
        `;

        this._renderList();
    }

    _renderList() {
        const list = this.shadowRoot.getElementById('paramList');
        if (!list) return;

        if (this._parameters.length === 0) {
            list.innerHTML = `
                <div class="empty-state">
                    <span class="empty-icon lnr lnr-cog"></span>
                    <div class="empty-text">Geen parameters gedefinieerd</div>
                    <div class="hint-text">Parameters zijn variabelen die gebruikers kunnen invullen bij het uitvoeren van het rapport.<br>Gebruik @parameternaam in filters om naar een parameter te verwijzen.</div>
                </div>
            `;
            return;
        }

        list.innerHTML = this._parameters.map((param, index) => `
            <div class="param-item" data-index="${index}">
                <input type="text" class="param-input" value="${this._escapeHtml(param.name)}"
                       data-field="name" placeholder="@naam">
                <input type="text" class="param-input" value="${this._escapeHtml(param.label)}"
                       data-field="label" placeholder="Label">
                <select class="param-select" data-field="type">
                    <option value="text" ${param.type === 'text' ? 'selected' : ''}>Tekst</option>
                    <option value="number" ${param.type === 'number' ? 'selected' : ''}>Nummer</option>
                    <option value="date" ${param.type === 'date' ? 'selected' : ''}>Datum</option>
                    <option value="select" ${param.type === 'select' ? 'selected' : ''}>Keuzelijst</option>
                </select>
                <input type="text" class="param-input" value="${this._escapeHtml(param.default)}"
                       data-field="default" placeholder="${this._getDefaultPlaceholder(param.type)}">
                <lib-switch ${param.required ? 'checked' : ''} data-field="required"></lib-switch>
                <button type="button" class="param-remove" data-index="${index}" title="Verwijderen">×</button>
            </div>
        `).join('');
    }

    _setupEventListeners() {
        const container = this.shadowRoot.querySelector('.param-config-container');
        if (!container) return;

        // Add parameter button
        const addBtn = this.shadowRoot.getElementById('addParamBtn');
        if (addBtn) {
            addBtn.addEventListener('click', () => this.addParameter());
        }

        // Delegate events to the list
        container.addEventListener('change', (e) => {
            const item = e.target.closest('.param-item');
            if (!item) return;

            const index = parseInt(item.dataset.index, 10);
            if (isNaN(index)) return;

            const fieldName = e.target.dataset.field;

            if (fieldName === 'name') {
                // Ensure name starts with @
                let name = e.target.value;
                if (name && !name.startsWith('@')) {
                    name = '@' + name;
                    e.target.value = name;
                }
                this._parameters[index].name = name;
            } else if (fieldName === 'label') {
                this._parameters[index].label = e.target.value;
            } else if (fieldName === 'type') {
                this._parameters[index].type = e.target.value;
                // Clear default if type changes (it might not be valid anymore)
                const defaultInput = item.querySelector('[data-field="default"]');
                if (defaultInput) {
                    defaultInput.placeholder = this._getDefaultPlaceholder(e.target.value);
                }
            } else if (fieldName === 'default') {
                this._parameters[index].default = e.target.value;
            } else if (fieldName === 'required') {
                this._parameters[index].required = e.target.checked;
            }

            this.dispatchEvent(new CustomEvent('param-change', {
                bubbles: true,
                detail: {
                    index,
                    property: fieldName,
                    value: this._parameters[index][fieldName],
                    parameter: this._parameters[index]
                }
            }));

            this._dispatchChange();
        });

        // Handle input events for live updates
        container.addEventListener('input', (e) => {
            if (['name', 'label', 'default'].includes(e.target.dataset.field)) {
                const item = e.target.closest('.param-item');
                if (!item) return;

                const index = parseInt(item.dataset.index, 10);
                if (isNaN(index)) return;

                const fieldName = e.target.dataset.field;
                this._parameters[index][fieldName] = e.target.value;
            }
        });

        // Remove parameter
        container.addEventListener('click', (e) => {
            const removeBtn = e.target.closest('.param-remove');
            if (removeBtn) {
                const index = parseInt(removeBtn.dataset.index, 10);
                if (!isNaN(index)) {
                    this.removeParameter(index);
                }
            }
        });
    }

    _getDefaultPlaceholder(type) {
        switch (type) {
            case 'date':
                return 'bijv. vandaag of 2024-01-01';
            case 'number':
                return 'bijv. 0 of 100';
            case 'select':
                return 'waarde1,waarde2,waarde3';
            default:
                return 'Standaardwaarde';
        }
    }

    _dispatchChange() {
        this.dispatchEvent(new CustomEvent('change', {
            bubbles: true,
            detail: {
                parameters: this.getParameters()
            }
        }));
    }

    _updateReadonlyState() {
        // CSS handles the visual readonly state
    }

    _escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Register the component
customElements.define('cma-param-config', CmaParamConfig);

} // End guard against double registration
