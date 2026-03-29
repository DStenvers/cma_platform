/**
 * lib-timepicker Web Component
 *
 * A reusable time picker web component with styled input and icon.
 * Generic component that can be used outside of CMA.
 *
 * Usage:
 *   <lib-timepicker name="mytime" value="09:30"></lib-timepicker>
 *
 * Attributes:
 *   - name: Form field name
 *   - value: Time value (HH:mm format)
 *   - min: Minimum time (HH:mm)
 *   - max: Maximum time (HH:mm)
 *   - step: Step in minutes (default: 1)
 *   - required: Field is required
 *   - disabled: Field is disabled
 *   - readonly: Field is readonly
 *
 * Events:
 *   - change: Fired when time changes
 */
// Guard against double registration
if (!customElements.get('lib-timepicker')) {

class LibTimepicker extends HTMLElement {
    static get observedAttributes() {
        return ['value', 'min', 'max', 'disabled', 'readonly', 'step', 'required'];
    }

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._value = '';
    }

    connectedCallback() {
        this.render();
        this.setupEventListeners();
    }

    disconnectedCallback() {
        // Clean up if needed
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;

        const input = this.shadowRoot?.querySelector('input[type="time"]');
        if (!input) return;

        switch (name) {
            case 'value':
                this._value = this._normalizeTime(newValue) || '';
                input.value = this._value;
                this._updateHiddenInput();
                this._updateDisplayInput();
                break;
            case 'min':
                input.min = newValue || '';
                break;
            case 'max':
                input.max = newValue || '';
                break;
            case 'step':
                // Convert step attribute (in minutes for backwards compat) to seconds for native input
                const stepMinutes = parseInt(newValue, 10) || 1;
                input.step = stepMinutes * 60;
                break;
            case 'disabled':
                input.disabled = this.hasAttribute('disabled');
                break;
            case 'readonly':
                input.readOnly = this.hasAttribute('readonly');
                break;
            case 'required':
                input.required = this.hasAttribute('required');
                break;
        }
    }

    get value() {
        return this._value;
    }

    set value(val) {
        this._value = this._normalizeTime(val) || '';
        this.setAttribute('value', this._value);
        const input = this.shadowRoot?.querySelector('input[type="time"]');
        if (input) {
            input.value = this._value;
        }
        this._updateHiddenInput();
        this._updateDisplayInput();
    }

    get name() {
        return this.getAttribute('name') || '';
    }

    set name(val) {
        if (val) {
            this.setAttribute('name', val);
        } else {
            this.removeAttribute('name');
        }
    }

    get type() {
        return 'time';
    }

    /**
     * Normalize time value - extract HH:mm from various formats
     */
    _normalizeTime(value) {
        if (!value) return '';

        // ISO format with 1899 date: "1899-12-30 09:30:00"
        let match = value.match(/^1899-\d{2}-\d{2}\s+(\d{2}):(\d{2})/);
        if (match) return `${match[1]}:${match[2]}`;

        // European format with 1899 date: "30-12-1899 09:30:00"
        match = value.match(/^\d{2}-\d{2}-1899\s+(\d{2}):(\d{2})/);
        if (match) return `${match[1]}:${match[2]}`;

        // Already time only: "09:30" or "09:30:00"
        match = value.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
        if (match) {
            const hours = match[1].padStart(2, '0');
            const minutes = match[2];
            return `${hours}:${minutes}`;
        }

        return '';
    }

    _updateHiddenInput() {
        const hidden = this.shadowRoot?.querySelector('input[type="hidden"]');
        if (hidden) {
            hidden.value = this._value || '';
        }
    }

    _updateDisplayInput() {
        const display = this.shadowRoot?.querySelector('.timepicker-input');
        if (display) {
            display.value = this._value || '';
        }
    }

    render() {
        const name = this.getAttribute('name') || '';
        const disabled = this.hasAttribute('disabled');
        const readonly = this.hasAttribute('readonly');
        const required = this.hasAttribute('required');
        const min = this.getAttribute('min') || '';
        const max = this.getAttribute('max') || '';
        const stepAttr = this.getAttribute('step');
        const stepMinutes = stepAttr ? parseInt(stepAttr, 10) : 1;
        const stepSeconds = stepMinutes * 60;

        // Initialize value from attribute
        this._value = this._normalizeTime(this.getAttribute('value')) || '';

        this.shadowRoot.innerHTML = `
            <style>
                @font-face {
                    font-family: 'Linearicons';
                    src: url('../library/fonts/Linearicons/Font/Linearicons.woff2') format('woff2'),
                         url('../library/fonts/Linearicons/Font/Linearicons.woff') format('woff');
                    font-weight: normal;
                    font-style: normal;
                }

                :host {
                    display: inline-block;
                    position: relative;
                    font-family: "Trebuchet MS", Verdana, sans-serif;
                    font-size: var(--font-size);
                }

                /* Small variant */
                :host([small]) .timepicker-wrapper {
                    border-radius: 3px;
                }

                :host([small]) .timepicker-input {
                    width: 50px;
                    height: 22px;
                    line-height: 22px;
                    font-size: var(--font-size-sm);
                    padding-left: 6px;
                }

                :host([small]) .timepicker-icon {
                    width: 20px;
                }

                :host([small]) .timepicker-icon::before {
                    font-size: var(--font-size-sm);
                }

                .timepicker-wrapper {
                    position: relative;
                    display: inline-flex;
                    align-items: stretch;
                    border: 1px solid var(--input-border, #ddd);
                    border-radius: 4px;
                    background: var(--input-bg, #fff);
                }

                /* Required indicator - red left border when empty */
                :host([data-required="true"]) .timepicker-wrapper {
                    border-left: 3px solid var(--color-error, #dc3545);
                }

                /* Required with value - standard border */
                :host([data-required="true"]) .timepicker-wrapper:has(.timepicker-input:not(:placeholder-shown)) {
                    border-left: 3px solid var(--border-color, #ddd);
                }

                /* Readonly required - no border */
                :host([data-required="true"][readonly]) .timepicker-wrapper {
                    border-left: none;
                }

                .timepicker-input {
                    padding-left: 12px;
                    padding-right: 4px;
                    border: none;
                    border-radius: 4px 0 0 4px;
                    font-size: var(--font-size);
                    font-family: "Trebuchet MS", Verdana, sans-serif;
                    width: 55px;
                    box-sizing: border-box;
                    color: var(--text-primary, #333);
                    height: 24px;
                    line-height: 24px;
                    background: transparent;
                }

                .timepicker-input:focus {
                    outline: none;
                }

                .timepicker-input:disabled {
                    color: #999;
                    cursor: not-allowed;
                }

                .timepicker-input[readonly] {
                    background-color: var(--input-bg-readonly, transparent);
                    border-color: transparent;
                    color: var(--text-secondary, #666);
                    cursor: default;
                }

                .timepicker-input::placeholder {
                    color: var(--text-muted, #999);
                    font-style: italic;
                }

                /* Readonly mode: hide icon and remove wrapper styling */
                :host([readonly]) .timepicker-wrapper {
                    border: none;
                    background: transparent;
                }

                :host([readonly]) .timepicker-icon {
                    display: none;
                }

                :host([readonly]) .timepicker-input {
                    border-radius: 0;
                    width: auto;
                }

                .timepicker-icon {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 24px;
                    cursor: pointer;
                    background-color: transparent;
                    border-left: 1px solid var(--input-border, #ddd);
                    border-radius: 0 4px 4px 0;
                }

                .timepicker-icon::before {
                    font-family: 'Linearicons';
                    content: "\\e8e6";
                    color: var(--text-secondary, #666);
                    font-size: var(--font-size-md);
                }

                .timepicker-icon:hover {
                    background-color: transparent;
                }

                .timepicker-icon:hover::before {
                    color: var(--color-primary, #204496);
                }

                /* Disabled state */
                :host([disabled]) .timepicker-wrapper {
                    background: transparent;
                }

                :host([disabled]) .timepicker-icon {
                    background: transparent;
                    cursor: not-allowed;
                }

                :host([disabled]) .timepicker-icon::before {
                    color: var(--text-disabled, #999);
                }

                :host([disabled]) .timepicker-icon:hover {
                    background: transparent;
                }

                :host([disabled]) .timepicker-icon:hover::before {
                    color: var(--text-disabled, #999);
                }

                /* Hidden native time input for functionality */
                input[type="time"] {
                    position: absolute;
                    opacity: 0;
                    width: 0;
                    height: 0;
                    pointer-events: none;
                }

                /* Hidden input for form submission */
                input[type="hidden"] {
                    display: none;
                }
            </style>

            <div class="timepicker-wrapper">
                <input type="text" class="timepicker-input"
                       value="${this._value}"
                       placeholder="uu:mm"
                       ${disabled ? 'disabled' : ''}
                       ${readonly ? 'readonly' : ''}>
                <span class="timepicker-icon" title="Tijd selecteren"></span>
                <input type="time"
                       value="${this._value}"
                       ${min ? `min="${min}"` : ''}
                       ${max ? `max="${max}"` : ''}
                       step="${stepSeconds}"
                       ${disabled ? 'disabled' : ''}
                       ${readonly ? 'readonly' : ''}
                       ${required ? 'required' : ''}>
                <input type="hidden" name="${name}" value="${this._value}">
            </div>
        `;
    }

    setupEventListeners() {
        const displayInput = this.shadowRoot.querySelector('.timepicker-input');
        const timeInput = this.shadowRoot.querySelector('input[type="time"]');
        const icon = this.shadowRoot.querySelector('.timepicker-icon');

        // Click on icon opens native time picker
        icon.addEventListener('click', () => {
            if (!this.hasAttribute('disabled') && !this.hasAttribute('readonly')) {
                timeInput.showPicker?.() || timeInput.click();
            }
        });

        // Native time input change
        timeInput.addEventListener('change', (e) => {
            this._value = e.target.value || '';
            this._updateHiddenInput();
            this._updateDisplayInput();

            // Dispatch change event
            this.dispatchEvent(new CustomEvent('change', {
                detail: { value: this._value },
                bubbles: true,
                composed: true
            }));
        });

        // Display input change (manual typing)
        displayInput.addEventListener('change', (e) => {
            const typed = e.target.value || '';
            const normalized = this._normalizeTime(typed);
            if (normalized) {
                this._value = normalized;
                timeInput.value = normalized;
                this._updateHiddenInput();
                this._updateDisplayInput();

                this.dispatchEvent(new CustomEvent('change', {
                    detail: { value: this._value },
                    bubbles: true,
                    composed: true
                }));
            } else if (typed === '') {
                this._value = '';
                timeInput.value = '';
                this._updateHiddenInput();

                this.dispatchEvent(new CustomEvent('change', {
                    detail: { value: '' },
                    bubbles: true,
                    composed: true
                }));
            } else {
                // Invalid input - revert to current value
                this._updateDisplayInput();
            }
        });

        // Allow clicking on display input to also open picker
        displayInput.addEventListener('click', () => {
            if (!this.hasAttribute('disabled') && !this.hasAttribute('readonly')) {
                timeInput.showPicker?.() || timeInput.focus();
            }
        });
    }
}

// Register the component
customElements.define('lib-timepicker', LibTimepicker);

} // end guard
