/**
 * lib-switch Web Component (No Shadow DOM)
 *
 * A toggle switch component for boolean values matching the CMA detail form style.
 * Uses shared CSS classes for styling (see lib-components.css).
 *
 * Usage:
 *   <lib-switch name="fieldName" checked></lib-switch>
 *   <lib-switch name="active" disabled></lib-switch>
 *   <lib-switch name="enabled" value="J"></lib-switch>
 *   <lib-switch name="field" labels="on:off"></lib-switch>
 *
 * Attributes:
 *   - name: Field name for form submission
 *   - checked: Boolean state (presence = true)
 *   - disabled: Prevent interaction
 *   - value: Value when checked (default: "J")
 *   - labels: Custom labels as "on:off" (default: "Ja:Nee")
 *   - data-field: Field name for inline editing (same as name if not set)
 *
 * Events:
 *   - change: Fired when value changes, detail: { checked: boolean, value: string, field: string }
 */
// Guard against double registration
if (!customElements.get('lib-switch')) {

class LibSwitch extends HTMLElement {
    static get observedAttributes() {
        return ['checked', 'disabled', 'readonly', 'name', 'value', 'labels'];
    }

    static formAssociated = true;

    constructor() {
        super();
        this._checked = false;
        this._rendered = false;
        this._internals = this.attachInternals ? this.attachInternals() : null;
    }

    connectedCallback() {
        this._checked = this.hasAttribute('checked');
        if (!this._rendered) {
            this.render();
            this._rendered = true;
        }
        this.updateFormValue();
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (name === 'checked') {
            this._checked = newValue !== null;
            this.updateFormValue();
        }
        if (oldValue !== newValue && this._rendered) {
            this.updateClasses();
        }
    }

    get checked() {
        return this._checked;
    }

    set checked(val) {
        const wasChecked = this._checked;
        this._checked = !!val;
        if (val) {
            this.setAttribute('checked', '');
        } else {
            this.removeAttribute('checked');
        }
        this.updateFormValue();
        // Only update classes if state actually changed
        if (wasChecked !== this._checked && this._rendered) {
            // Disable animation for programmatic changes
            this._setNoAnimate(true);
            this.updateClasses();
            // Re-enable animation after a frame (for future user clicks)
            requestAnimationFrame(() => {
                this._setNoAnimate(false);
            });
        }
    }

    // Helper to toggle animation class
    _setNoAnimate(disable) {
        const switchEl = this.querySelector('.lib-switch');
        if (switchEl) {
            switchEl.classList.toggle('lib-switch--no-animate', disable);
        }
    }

    get name() {
        return this.getAttribute('name') || '';
    }

    get field() {
        return this.getAttribute('data-field') || this.name;
    }

    get value() {
        return this.getAttribute('value') || 'J';
    }

    get disabled() {
        return this.hasAttribute('disabled');
    }

    get readonly() {
        return this.hasAttribute('readonly');
    }

    set disabled(val) {
        if (val) {
            this.setAttribute('disabled', '');
        } else {
            this.removeAttribute('disabled');
        }
        if (this._rendered) {
            this.updateClasses();
        }
    }

    get labels() {
        const labelsAttr = this.getAttribute('labels') || 'Ja:Nee';
        const parts = labelsAttr.split(':');
        return {
            on: parts[0] || 'Ja',
            off: parts[1] || 'Nee'
        };
    }

    updateFormValue() {
        if (this._internals) {
            this._internals.setFormValue(this._checked ? this.value : 'N');
        }
    }

    toggle() {
        if (this.disabled || this.readonly) return;
        this.checked = !this._checked;
        this.dispatchEvent(new CustomEvent('change', {
            bubbles: true,
            detail: {
                checked: this._checked,
                value: this._checked ? this.value : 'N',
                field: this.field
            }
        }));
    }

    // For external saving state indication
    setSaving(saving) {
        const switchEl = this.querySelector('.lib-switch');
        if (switchEl) {
            switchEl.classList.toggle('lib-switch--saving', saving);
        }
    }

    updateClasses() {
        const switchEl = this.querySelector('.lib-switch');
        if (!switchEl) return;

        const checked = this._checked;
        const disabled = this.disabled;
        const readonly = this.readonly;
        const { on, off } = this.labels;

        // Update switch classes
        switchEl.classList.toggle('lib-switch--checked', checked);
        switchEl.classList.toggle('lib-switch--disabled', disabled);
        switchEl.classList.toggle('lib-switch--readonly', readonly);
        switchEl.setAttribute('aria-checked', String(checked));
        switchEl.setAttribute('aria-label', checked ? on : off);
        switchEl.setAttribute('aria-readonly', String(readonly));
        switchEl.setAttribute('tabindex', (disabled || readonly) ? '-1' : '0');
    }

    render() {
        const disabled = this.disabled;
        const readonly = this.readonly;
        const checked = this._checked;
        const { on, off } = this.labels;

        this.innerHTML = `
            <span class="lib-switch${checked ? ' lib-switch--checked' : ''}${disabled ? ' lib-switch--disabled' : ''}${readonly ? ' lib-switch--readonly' : ''}"
                  role="switch"
                  tabindex="${(disabled || readonly) ? -1 : 0}"
                  aria-checked="${checked}"
                  aria-readonly="${readonly}"
                  aria-label="${checked ? on : off}">
                <span class="lib-switch__track"></span>
                <span class="lib-switch__thumb"></span>
            </span>
        `;

        // Add event listeners
        const switchEl = this.querySelector('.lib-switch');
        if (switchEl) {
            switchEl.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggle();
            });
            switchEl.addEventListener('keydown', (e) => {
                if (e.key === ' ' || e.key === 'Enter') {
                    e.preventDefault();
                    this.toggle();
                } else if (e.key === 'ArrowRight' || e.key === 'ArrowUp') {
                    // Arrow right/up = check (on)
                    e.preventDefault();
                    if (!this._checked) this.toggle();
                } else if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') {
                    // Arrow left/down = uncheck (off)
                    e.preventDefault();
                    if (this._checked) this.toggle();
                }
            });
        }
    }
}

// Register the component
customElements.define('lib-switch', LibSwitch);

} // end guard
