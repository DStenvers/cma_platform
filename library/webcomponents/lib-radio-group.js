/**
 * lib-radio-group Web Component (No Shadow DOM)
 *
 * A segmented radio-group control. Visually a single bordered, rounded
 * container with side-by-side option buttons; the selected option appears
 * "pressed in" via an inset shadow. Functions as a form-native radio
 * group — submits the selected option's value under the component's
 * `name` attribute.
 *
 * Usage:
 *   <lib-radio-group name="status" value="closed"
 *                    options="open:Open|closed:Closed|pending:Pending">
 *   </lib-radio-group>
 *
 * Attributes:
 *   - name:     Field name for form submission (formAssociated)
 *   - value:    Currently selected option value
 *   - options:  Pipe-separated value:label pairs
 *               (e.g. "1:Een|2:Twee|3:Drie")
 *   - disabled: Prevent interaction
 *   - readonly: Visible but not selectable
 *   - data-field: Field name for inline editing (same as name if not set)
 *   - data-default: Default value (used by JSON-form populateForm)
 *
 * Properties:
 *   - value    (string)  — get/set the selected value
 *   - options  (Array<{value,label}>) — get/set option list
 *   - field    (string)  — data-field if set, else name
 *
 * Events:
 *   - change: { value, field }
 *
 * Keyboard:
 *   - ArrowLeft / ArrowRight: move selection
 *   - Home / End: jump to first / last option
 *   - Space / Enter on focused option: select it
 */
if (!customElements.get('lib-radio-group')) {

class LibRadioGroup extends HTMLElement {
    static get observedAttributes() {
        return ['value', 'options', 'disabled', 'readonly', 'name'];
    }

    static formAssociated = true;

    constructor() {
        super();
        this._value = '';
        this._options = [];
        this._rendered = false;
        this._internals = this.attachInternals ? this.attachInternals() : null;
        this._onClick = this._onClick.bind(this);
        this._onKeydown = this._onKeydown.bind(this);
    }

    connectedCallback() {
        this._parseAttributes();
        if (!this._rendered) {
            this.render();
            this._rendered = true;
        }
        this._updateFormValue();
        this.addEventListener('click', this._onClick);
        this.addEventListener('keydown', this._onKeydown);
    }

    disconnectedCallback() {
        this.removeEventListener('click', this._onClick);
        this.removeEventListener('keydown', this._onKeydown);
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;
        if (name === 'options') {
            this._options = LibRadioGroup.parseOptions(newValue || '');
            if (this._rendered) this.render();
        } else if (name === 'value') {
            this._value = newValue == null ? '' : String(newValue);
            this._updateFormValue();
            if (this._rendered) this._syncSelection();
        } else if (this._rendered) {
            // disabled/readonly/name — re-render to refresh aria/disabled states
            this.render();
        }
    }

    // ------------------------------------------------------------------
    // Public properties
    // ------------------------------------------------------------------

    get value() { return this._value; }
    set value(v) {
        const next = v == null ? '' : String(v);
        if (next === this._value) return;
        this._value = next;
        if (next === '') {
            this.removeAttribute('value');
        } else {
            this.setAttribute('value', next);
        }
        this._updateFormValue();
        if (this._rendered) this._syncSelection();
    }

    get options() { return this._options.slice(); }
    set options(list) {
        if (!Array.isArray(list)) return;
        this._options = list.map((o) => ({
            value: String(o.value ?? ''),
            label: String(o.label ?? o.text ?? o.value ?? ''),
        }));
        // Reflect back to attribute so the DOM stays the source of truth.
        const attr = this._options.map((o) => `${o.value}:${o.label}`).join('|');
        this.setAttribute('options', attr);
        if (this._rendered) this.render();
    }

    get field() { return this.dataset.field || this.getAttribute('name') || ''; }
    get disabled() { return this.hasAttribute('disabled'); }
    get readonly() { return this.hasAttribute('readonly'); }

    // ------------------------------------------------------------------
    // Static helpers
    // ------------------------------------------------------------------

    /**
     * Parse the `options` attribute. Format: "value1:label1|value2:label2".
     * A bare segment without ":" treats the whole segment as both value and
     * label (e.g. "Yes|No|Maybe").
     */
    static parseOptions(spec) {
        if (!spec) return [];
        return spec.split('|').map((seg) => {
            const idx = seg.indexOf(':');
            if (idx === -1) {
                const v = seg.trim();
                return { value: v, label: v };
            }
            return {
                value: seg.slice(0, idx).trim(),
                label: seg.slice(idx + 1).trim(),
            };
        }).filter((o) => o.value !== '' || o.label !== '');
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    _parseAttributes() {
        this._options = LibRadioGroup.parseOptions(this.getAttribute('options') || '');
        const v = this.getAttribute('value');
        this._value = v == null ? '' : String(v);
    }

    _updateFormValue() {
        if (this._internals && typeof this._internals.setFormValue === 'function') {
            this._internals.setFormValue(this._value);
        }
    }

    render() {
        const selected = this._value;
        const disabled = this.disabled;
        const readonly = this.readonly;
        const cls = ['lib-radio-group'];
        if (disabled) cls.push('lib-radio-group--disabled');
        if (readonly) cls.push('lib-radio-group--readonly');
        this.className = cls.join(' ');
        this.setAttribute('role', 'radiogroup');
        if (disabled) this.setAttribute('aria-disabled', 'true'); else this.removeAttribute('aria-disabled');

        const parts = this._options.map((opt, i) => {
            const isSelected = opt.value === selected;
            const tabindex = isSelected ? '0' : '-1';
            const optCls = ['lib-radio-group__option'];
            if (isSelected) optCls.push('lib-radio-group__option--selected');
            return `<button type="button"
                class="${optCls.join(' ')}"
                role="radio"
                aria-checked="${isSelected ? 'true' : 'false'}"
                data-value="${escapeAttr(opt.value)}"
                tabindex="${tabindex}"
                ${disabled || readonly ? 'aria-disabled="true"' : ''}
            >${escapeText(opt.label)}</button>`;
        });

        // If no option is selected yet, make the first one focusable so
        // keyboard users have an entry point.
        if (!this._options.some((o) => o.value === selected) && parts.length > 0) {
            parts[0] = parts[0].replace('tabindex="-1"', 'tabindex="0"');
        }

        this.innerHTML = parts.join('');
    }

    _syncSelection() {
        const selected = this._value;
        this.querySelectorAll('.lib-radio-group__option').forEach((btn) => {
            const isSel = btn.dataset.value === selected;
            btn.classList.toggle('lib-radio-group__option--selected', isSel);
            btn.setAttribute('aria-checked', isSel ? 'true' : 'false');
            btn.tabIndex = isSel ? 0 : -1;
        });
    }

    _onClick(e) {
        if (this.disabled || this.readonly) return;
        const btn = e.target.closest('.lib-radio-group__option');
        if (!btn || !this.contains(btn)) return;
        const v = btn.dataset.value;
        if (v == null || v === this._value) return;
        this._select(v);
    }

    _onKeydown(e) {
        if (this.disabled || this.readonly) return;
        const focusable = Array.from(this.querySelectorAll('.lib-radio-group__option'));
        if (focusable.length === 0) return;
        const currentIdx = focusable.findIndex((b) => b.dataset.value === this._value);
        let nextIdx = -1;

        switch (e.key) {
            case 'ArrowRight':
            case 'ArrowDown':
                nextIdx = (currentIdx + 1 + focusable.length) % focusable.length;
                break;
            case 'ArrowLeft':
            case 'ArrowUp':
                nextIdx = (currentIdx - 1 + focusable.length) % focusable.length;
                break;
            case 'Home':
                nextIdx = 0;
                break;
            case 'End':
                nextIdx = focusable.length - 1;
                break;
            case ' ':
            case 'Enter': {
                const btn = document.activeElement?.closest?.('.lib-radio-group__option');
                if (btn && this.contains(btn)) {
                    e.preventDefault();
                    this._select(btn.dataset.value);
                }
                return;
            }
            default:
                return;
        }
        e.preventDefault();
        const target = focusable[nextIdx];
        if (target) {
            this._select(target.dataset.value);
            target.focus();
        }
    }

    _select(newValue) {
        if (newValue === this._value) return;
        this.value = newValue; // setter handles attribute + form value + DOM sync
        this.dispatchEvent(new CustomEvent('change', {
            bubbles: true,
            detail: { value: this._value, field: this.field },
        }));
    }
}

function escapeAttr(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function escapeText(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

customElements.define('lib-radio-group', LibRadioGroup);

} // end if !customElements.get
