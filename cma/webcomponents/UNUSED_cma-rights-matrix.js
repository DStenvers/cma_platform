/**
 * cma-rights-matrix Web Component
 *
 * A matrix control for managing access rights with radio buttons and optional button checkboxes.
 * Used for group menu/form permissions in CMA.
 *
 * Usage:
 *   <cma-rights-matrix name="menu_rights"
 *                      columns='[{"value":0,"label":"Geen"},{"value":10,"label":"Lezen"},{"value":30,"label":"Volledig"}]'
 *                      button-columns='["Knop 1","Knop 2","Knop 3","Knop 4","Knop 5"]'>
 *     <cma-rights-row id="1" label="Menu Item" indent="0" security-by-user="false"></cma-rights-row>
 *   </cma-rights-matrix>
 *
 * Attributes:
 *   - name: Base name for form fields
 *   - columns: JSON array of access level columns [{value, label, conditional?}]
 *   - button-columns: JSON array of button labels (up to 5)
 *   - group-by-menu: Group rows by menu (default: true)
 *   - include-subforms: Show subforms with indentation (default: true)
 *   - readonly: Disable all inputs
 *
 * Child elements:
 *   - cma-rights-row: Individual row with id, label, indent, security-by-user, parent attributes
 *
 * Events:
 *   - change: Fired when any value changes
 */
class CmaRightsMatrix extends HTMLElement {
    static get observedAttributes() {
        return ['readonly', 'columns', 'button-columns'];
    }

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._rows = [];
        this._columns = [];
        this._buttonColumns = [];
        this._values = {};
    }

    connectedCallback() {
        this._parseConfig();
        this._collectRows();
        this.render();
        this._setupEventListeners();
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;

        if (name === 'columns' || name === 'button-columns') {
            this._parseConfig();
            this.render();
        } else if (name === 'readonly') {
            this._updateReadonlyState();
        }
    }

    get value() {
        return this._values;
    }

    set value(val) {
        this._values = val || {};
        this._applyValues();
    }

    _parseConfig() {
        // Parse access level columns (removed "Alleen eigen" conditional column)
        try {
            const colAttr = this.getAttribute('columns');
            this._columns = colAttr ? JSON.parse(colAttr) : [
                { value: 0, label: 'Geen' },
                { value: 10, label: 'Lezen' },
                { value: 30, label: 'Volledig' }
            ];
        } catch (e) {
            cmaLog.error('[cma-rights-matrix] Invalid columns JSON:', e.message);
            this._columns = [];
        }

        // Parse button columns
        try {
            const btnAttr = this.getAttribute('button-columns');
            this._buttonColumns = btnAttr ? JSON.parse(btnAttr) : [];
        } catch (e) {
            cmaLog.error('[cma-rights-matrix] Invalid button-columns JSON:', e.message);
            this._buttonColumns = [];
        }
    }

    _collectRows() {
        this._rows = [];
        const rowElements = this.querySelectorAll('cma-rights-row');

        rowElements.forEach(el => {
            const mainmenu = el.getAttribute('mainmenu') || '';
            const submenu = el.getAttribute('submenu') || '';
            let label = el.getAttribute('label') || el.textContent.trim() || '';

            // If label is empty, construct from mainmenu/submenu
            if (!label && (mainmenu || submenu)) {
                label = submenu ? `[${mainmenu} - ${submenu}]` : `[${mainmenu}]`;
            }

            const row = {
                id: el.getAttribute('id') || '',
                label: label,
                indent: parseInt(el.getAttribute('indent') || '0', 10),
                securityByUser: el.getAttribute('security-by-user') === 'true',
                parent: el.getAttribute('parent') || null,
                subforms: el.getAttribute('subforms') || null,
                value: parseInt(el.getAttribute('value') || '0', 10),
                buttons: (el.getAttribute('buttons') || '').split(',').filter(b => b)
            };

            this._rows.push(row);

            // Initialize values
            this._values[row.id] = {
                access: row.value,
                buttons: row.buttons.reduce((acc, b) => { acc[b] = true; return acc; }, {})
            };
        });
    }

    render() {
        const name = this.getAttribute('name') || 'rights';
        const isReadonly = this.hasAttribute('readonly');
        const showButtons = this._buttonColumns.length > 0;

        this.shadowRoot.innerHTML = `
            <style>
                :host {
                    display: block;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    font-size: 13px;
                }

                * {
                    box-sizing: border-box;
                }

                .matrix-container {
                    overflow-x: auto;
                    border: 1px solid var(--border-color, #e0e0e0);
                    border-radius: 6px;
                }

                .matrix-container.readonly {
                    opacity: 0.8;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                    min-width: 600px;
                }

                thead {
                    background: linear-gradient(to bottom, #f8f9fa 0%, #e8ecf0 100%);
                    position: sticky;
                    top: 0;
                    z-index: 10;
                }

                th {
                    padding: 10px 8px;
                    text-align: center;
                    font-weight: 600;
                    font-size: 12px;
                    color: #1a365d;
                    border: 0;
                    border-bottom: 2px solid #ddd;
                    white-space: nowrap;
                }

                th.label-col {
                    text-align: left;
                    min-width: 200px;
                }

                th.access-col {
                    min-width: 80px;
                }

                th.button-col {
                    min-width: 60px;
                    font-size: 11px;
                }

                tbody tr {
                    border-bottom: 1px solid #eee;
                    transition: background-color 0.1s ease;
                }

                tbody tr:hover {
                    background-color: #f8fafc;
                }

                tbody tr.subform {
                    background-color: #fafbfc;
                }

                tbody tr.subform:hover {
                    background-color: #f0f4f8;
                }

                td {
                    padding: 8px;
                    vertical-align: middle;
                }

                td.label-col {
                    text-align: left;
                }

                td.access-col,
                td.button-col {
                    text-align: center;
                }

                .row-label {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                }

                .row-label.indent-1 { padding-left: 20px; }
                .row-label.indent-2 { padding-left: 40px; }
                .row-label.indent-3 { padding-left: 60px; }

                .row-indent-marker {
                    color: #999;
                    font-size: 10px;
                }

                .row-text {
                    flex: 1;
                }

                .row-text.subform {
                    font-style: italic;
                    color: #666;
                }

                /* Radio button styling */
                input[type="radio"] {
                    appearance: none;
                    -webkit-appearance: none;
                    width: 18px;
                    height: 18px;
                    border: 2px solid #ccc;
                    border-radius: 50%;
                    cursor: pointer;
                    transition: all 0.15s ease;
                    position: relative;
                }

                input[type="radio"]:hover {
                    border-color: #204496;
                }

                input[type="radio"]:checked {
                    border-color: #204496;
                    background-color: #204496;
                }

                input[type="radio"]:checked::after {
                    content: '';
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    width: 6px;
                    height: 6px;
                    background-color: white;
                    border-radius: 50%;
                }

                input[type="radio"]:disabled {
                    opacity: 0.4;
                    cursor: not-allowed;
                }

                /* Checkbox styling */
                input[type="checkbox"] {
                    appearance: none;
                    -webkit-appearance: none;
                    width: 16px;
                    height: 16px;
                    border: 2px solid #ccc;
                    border-radius: 3px;
                    cursor: pointer;
                    transition: all 0.15s ease;
                    position: relative;
                }

                input[type="checkbox"]:hover {
                    border-color: #204496;
                }

                input[type="checkbox"]:checked {
                    border-color: #204496;
                    background-color: #204496;
                }

                input[type="checkbox"]:checked::after {
                    content: '';
                    position: absolute;
                    top: 2px;
                    left: 5px;
                    width: 4px;
                    height: 8px;
                    border: solid white;
                    border-width: 0 2px 2px 0;
                    transform: rotate(45deg);
                }

                input[type="checkbox"]:disabled {
                    opacity: 0.4;
                    cursor: not-allowed;
                }

                /* Conditional column (grayed out when not applicable) */
                .not-applicable {
                    background-color: #f5f5f5;
                }

                .not-applicable input {
                    visibility: hidden;
                }

                /* Focus states */
                input:focus {
                    outline: 2px solid rgba(32, 68, 150, 0.3);
                    outline-offset: 2px;
                }

                /* Dark mode */
                :host-context(html.dark-mode) .matrix-container {
                    --border-color: #3d3d4d;
                }

                :host-context(html.dark-mode) thead {
                    background: linear-gradient(to bottom, #353545 0%, #2d2d3d 100%);
                }

                :host-context(html.dark-mode) th {
                    color: #e0e0e0;
                    border-bottom-color: #4d4d5d;
                }

                :host-context(html.dark-mode) tbody tr {
                    border-bottom-color: #3d3d4d;
                }

                :host-context(html.dark-mode) tbody tr:hover {
                    background-color: #35354a;
                }

                :host-context(html.dark-mode) tbody tr.subform {
                    background-color: #2a2a3a;
                }

                :host-context(html.dark-mode) .row-text.subform {
                    color: #aaa;
                }

                :host-context(html.dark-mode) .not-applicable {
                    background-color: #252535;
                }

                :host-context(html.dark-mode) input[type="radio"],
                :host-context(html.dark-mode) input[type="checkbox"] {
                    border-color: #555;
                }

                /* Responsive */
                @media (max-width: 768px) {
                    th.button-col {
                        display: none;
                    }
                    td.button-col {
                        display: none;
                    }
                }
            </style>
            <div class="matrix-container ${isReadonly ? 'readonly' : ''}">
                <table>
                    <thead>
                        <tr>
                            <th class="label-col">Menu / Formulier</th>
                            ${this._columns.map(col =>
                                `<th class="access-col">${this._escapeHtml(col.label)}</th>`
                            ).join('')}
                            ${showButtons ? `<th class="button-col" colspan="${this._buttonColumns.length}">Extra knoppen</th>` : ''}
                        </tr>
                    </thead>
                    <tbody>
                        ${this._renderRows(name, isReadonly, showButtons)}
                    </tbody>
                </table>
            </div>
            <slot style="display:none"></slot>
        `;
    }

    _renderRows(name, isReadonly, showButtons) {
        return this._rows.map(row => {
            const isSubform = row.indent > 0;
            const currentAccess = this._values[row.id]?.access ?? 0;
            const currentButtons = this._values[row.id]?.buttons ?? {};

            return `
                <tr class="${isSubform ? 'subform' : ''}" data-row-id="${row.id}" data-parent="${row.parent || ''}" data-subforms="${row.subforms || ''}">
                    <td class="label-col">
                        <div class="row-label indent-${row.indent}">
                            ${row.indent > 0 ? '<span class="row-indent-marker">└</span>' : ''}
                            <span class="row-text ${isSubform ? 'subform' : ''}">${this._escapeHtml(row.label)}</span>
                        </div>
                    </td>
                    ${this._columns.map(col => {
                        const isConditional = col.conditional && !row.securityByUser;
                        const inputName = `${name}_${row.id}`;
                        const isChecked = currentAccess === col.value;

                        return `
                            <td class="access-col ${isConditional ? 'not-applicable' : ''}">
                                <input type="radio"
                                       name="${inputName}"
                                       value="${col.value}"
                                       ${isChecked ? 'checked' : ''}
                                       ${isReadonly || isConditional ? 'disabled' : ''}
                                       data-row="${row.id}"
                                       data-type="access">
                            </td>
                        `;
                    }).join('')}
                    ${showButtons ? this._buttonColumns.map((btn, i) => {
                        const buttonId = `btn${i + 1}`;
                        const isChecked = currentButtons[buttonId];
                        const isDisabled = isReadonly || currentAccess === 0;

                        return `
                            <td class="button-col">
                                <input type="checkbox"
                                       name="${name}_${row.id}_${buttonId}"
                                       value="1"
                                       ${isChecked ? 'checked' : ''}
                                       ${isDisabled ? 'disabled' : ''}
                                       data-row="${row.id}"
                                       data-type="button"
                                       data-button="${buttonId}">
                            </td>
                        `;
                    }).join('') : ''}
                </tr>
            `;
        }).join('');
    }

    _setupEventListeners() {
        const table = this.shadowRoot.querySelector('table');

        table.addEventListener('change', (e) => {
            const input = e.target;
            const rowId = input.dataset.row;
            const type = input.dataset.type;

            if (!this._values[rowId]) {
                this._values[rowId] = { access: 0, buttons: {} };
            }

            if (type === 'access') {
                const newValue = parseInt(input.value, 10);
                this._values[rowId].access = newValue;

                // If access is "None" (0), disable all button checkboxes for this row
                this._updateButtonStates(rowId, newValue);

                // Handle subform cascading
                this._cascadeToSubforms(rowId, newValue);

            } else if (type === 'button') {
                const buttonId = input.dataset.button;
                this._values[rowId].buttons[buttonId] = input.checked;
            }

            this._dispatchChange();
        });
    }

    _updateButtonStates(rowId, accessLevel) {
        const buttons = this.shadowRoot.querySelectorAll(`input[data-row="${rowId}"][data-type="button"]`);
        buttons.forEach(btn => {
            btn.disabled = accessLevel === 0 || this.hasAttribute('readonly');
            if (accessLevel === 0) {
                btn.checked = false;
                const buttonId = btn.dataset.button;
                if (this._values[rowId]?.buttons) {
                    this._values[rowId].buttons[buttonId] = false;
                }
            }
        });
    }

    _cascadeToSubforms(rowId, accessLevel) {
        // Find the row element
        const row = this.shadowRoot.querySelector(`tr[data-row-id="${rowId}"]`);
        if (!row) return;

        const subformsAttr = row.dataset.subforms;
        if (!subformsAttr) return;

        const subformIds = subformsAttr.split(',').filter(id => id);

        subformIds.forEach(subId => {
            // If parent access is None, disable subform radios
            const subInputs = this.shadowRoot.querySelectorAll(`input[data-row="${subId}"][data-type="access"]`);
            subInputs.forEach(input => {
                input.disabled = accessLevel === 0 || this.hasAttribute('readonly');
                if (accessLevel === 0) {
                    // Reset to None
                    if (input.value === '0') {
                        input.checked = true;
                    } else {
                        input.checked = false;
                    }
                }
            });

            if (accessLevel === 0) {
                this._values[subId] = { access: 0, buttons: {} };
                this._updateButtonStates(subId, 0);
            }
        });
    }

    _updateReadonlyState() {
        const container = this.shadowRoot.querySelector('.matrix-container');
        if (container) {
            container.classList.toggle('readonly', this.hasAttribute('readonly'));
        }

        const inputs = this.shadowRoot.querySelectorAll('input');
        inputs.forEach(input => {
            if (this.hasAttribute('readonly')) {
                input.disabled = true;
            } else {
                // Re-enable based on access level
                const rowId = input.dataset.row;
                const accessLevel = this._values[rowId]?.access ?? 0;

                if (input.dataset.type === 'button') {
                    input.disabled = accessLevel === 0;
                } else {
                    // Check for conditional columns
                    const td = input.closest('td');
                    input.disabled = td?.classList.contains('not-applicable') ?? false;
                }
            }
        });
    }

    _applyValues() {
        Object.keys(this._values).forEach(rowId => {
            const rowValue = this._values[rowId];

            // Set access level radio
            const accessRadio = this.shadowRoot.querySelector(
                `input[data-row="${rowId}"][data-type="access"][value="${rowValue.access}"]`
            );
            if (accessRadio) {
                accessRadio.checked = true;
            }

            // Set button checkboxes
            if (rowValue.buttons) {
                Object.keys(rowValue.buttons).forEach(btnId => {
                    const checkbox = this.shadowRoot.querySelector(
                        `input[data-row="${rowId}"][data-button="${btnId}"]`
                    );
                    if (checkbox) {
                        checkbox.checked = rowValue.buttons[btnId];
                    }
                });
            }

            // Update button states
            this._updateButtonStates(rowId, rowValue.access);
        });
    }

    _dispatchChange() {
        this.dispatchEvent(new CustomEvent('change', {
            detail: { value: this._values },
            bubbles: true
        }));
    }

    _escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Set value for a specific row
     */
    setRowValue(rowId, accessLevel, buttons = {}) {
        this._values[rowId] = { access: accessLevel, buttons };
        this._applyValues();
    }

    /**
     * Get value for a specific row
     */
    getRowValue(rowId) {
        return this._values[rowId] || { access: 0, buttons: {} };
    }

    /**
     * Add a new row dynamically
     */
    addRow(id, label, indent = 0, securityByUser = false) {
        this._rows.push({ id, label, indent, securityByUser, parent: null, subforms: null, value: 0, buttons: [] });
        this._values[id] = { access: 0, buttons: {} };
        this.render();
        this._setupEventListeners();
    }

    /**
     * Load values from form data
     */
    loadFromFormData(formData) {
        const name = this.getAttribute('name') || 'rights';

        this._rows.forEach(row => {
            const accessKey = `${name}_${row.id}`;
            const accessValue = formData.get(accessKey);

            if (accessValue !== null) {
                this._values[row.id] = {
                    access: parseInt(accessValue, 10),
                    buttons: {}
                };

                // Load button values
                for (let i = 1; i <= 5; i++) {
                    const btnKey = `${name}_${row.id}_btn${i}`;
                    const btnValue = formData.get(btnKey);
                    if (btnValue) {
                        this._values[row.id].buttons[`btn${i}`] = true;
                    }
                }
            }
        });

        this._applyValues();
    }
}

// Custom element for rows (used for declarative setup)
class CmaRightsRow extends HTMLElement {
    constructor() {
        super();
    }
}

// Register the components
customElements.define('cma-rights-matrix', CmaRightsMatrix);
customElements.define('cma-rights-row', CmaRightsRow);
