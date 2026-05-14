/**
 * lib-field Web Component
 *
 * A material-style text field with a floating label that sits inside the
 * field at rest and slides up + shrinks when the field gets focus or has
 * content.  Wraps a real native <input> or <textarea> so form
 * submission, autofill, validation, and keyboard behaviour are all the
 * browser's native paths — the component is purely a styling shell.
 *
 * Usage:
 *   <lib-field label="E-mailadres" name="email" type="email" required></lib-field>
 *   <lib-field label="Wachtwoord" name="pwd" type="password" minlength="12" helper="Minimaal 12 tekens"></lib-field>
 *   <lib-field label="Bio" name="bio" multiline rows="4" maxlength="500"></lib-field>
 *   <lib-field label="Naam" name="name" value="Diederik" autofocus></lib-field>
 *
 * Attributes (host):
 *   - label       (string, required)  — the floating label text
 *   - name        — form field name (set on the inner input/textarea)
 *   - type        — input type: text | email | password | number | url | search | tel  (default text)
 *   - value       — initial value
 *   - placeholder — extra in-field hint (rare; usually unnecessary with floating label)
 *   - helper      — small hint text rendered below the field
 *   - error       — error message; turns the field red
 *   - multiline   — boolean, switches the inner control from <input> to <textarea>
 *   - rows        — for multiline (default 3)
 *   - required, disabled, readonly, autofocus
 *   - minlength, maxlength, min, max, step, pattern
 *   - autocomplete
 *   - inputmode
 *
 * The floating-label trick is CSS-only — see lib-components.css.  We
 * always set the inner control's placeholder to a single space so
 * `:placeholder-shown` is the canonical "field is empty" predicate.
 *
 * Events: bubbles `input` / `change` from the inner control.  No custom
 * events fired by lib-field itself.
 *
 * Properties (read/write):
 *   - value : string
 *   - name  : string
 *
 * Methods:
 *   - focus() / blur() / select() — proxy to the inner control
 */

if (!customElements.get('lib-field')) {

let _libFieldId = 0;

class LibField extends HTMLElement {
    static get observedAttributes() {
        return ['label', 'value', 'helper', 'error', 'disabled', 'readonly', 'placeholder'];
    }

    constructor() {
        super();
        this._rendered = false;
    }

    connectedCallback() {
        if (this._rendered) { return; }
        // Wait for child text content (if any) to be parsed when used as
        // <lib-field>some content</lib-field>.  This mirrors the lib-message
        // approach to avoid the custom-element-upgrade-vs-text-parse race.
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this._init(), { once: true });
        } else {
            this._init();
        }
    }

    _init() {
        if (this._rendered) { return; }
        this._render();
        this._rendered = true;
    }

    _render() {
        const isMulti = this.hasAttribute('multiline');
        const tag     = isMulti ? 'textarea' : 'input';
        const id      = `lib-field-${++_libFieldId}`;

        // Pass-through attributes from host onto the inner control.  We
        // keep `value` here too; for textarea it lands in the body below.
        const passThrough = ['name', 'type', 'value', 'required', 'disabled',
            'readonly', 'autofocus', 'autocomplete', 'minlength', 'maxlength',
            'min', 'max', 'step', 'pattern', 'inputmode'];

        const attrs = passThrough
            .filter(a => this.hasAttribute(a) && !(isMulti && (a === 'value' || a === 'type')))
            .map(a => {
                const v = this.getAttribute(a);
                if (v === '' || v === null) { return a; }
                return `${a}="${this._escape(v)}"`;
            })
            .join(' ');

        // The placeholder is the indicator for `:placeholder-shown`.  If
        // the caller passed a real placeholder attribute, use that;
        // otherwise inject a single space so the CSS trick still works.
        const ph = this.hasAttribute('placeholder') ? this.getAttribute('placeholder') : ' ';

        let control;
        if (isMulti) {
            const rows = this.getAttribute('rows') || '3';
            const initial = this.getAttribute('value') ?? this.textContent.trim();
            control = `<textarea class="lib-field__control" id="${id}" placeholder="${this._escape(ph)}" rows="${this._escape(rows)}" ${attrs}>${this._escape(initial)}</textarea>`;
        } else {
            control = `<input class="lib-field__control" id="${id}" placeholder="${this._escape(ph)}" ${attrs}>`;
        }

        const label  = this._escape(this.getAttribute('label') || '');
        const helper = this.getAttribute('helper');
        const error  = this.getAttribute('error');

        this.innerHTML =
            `<div class="lib-field__inner">${control}` +
            `<label class="lib-field__label" for="${id}">${label}</label>` +
            `</div>` +
            (helper ? `<small class="lib-field__helper">${this._escape(helper)}</small>` : '') +
            (error  ? `<small class="lib-field__error">${this._escape(error)}</small>`   : '');

        if (error) { this.classList.add('has-error'); }
        else       { this.classList.remove('has-error'); }
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (!this._rendered || oldValue === newValue) { return; }

        switch (name) {
            case 'label': {
                const lbl = this.querySelector('.lib-field__label');
                if (lbl) { lbl.textContent = newValue || ''; }
                return;
            }
            case 'value': {
                const ctrl = this.querySelector('.lib-field__control');
                if (ctrl && ctrl.value !== (newValue || '')) {
                    ctrl.value = newValue || '';
                }
                return;
            }
            case 'helper':
            case 'error':
            case 'placeholder':
            case 'disabled':
            case 'readonly':
                // Cheap path: full re-render.  Loses inner focus + caret position,
                // but these attributes change rarely and never mid-typing.
                this._render();
                return;
        }
    }

    _escape(s) {
        return String(s).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[c]);
    }

    /* ---- public API ---- */

    get value() {
        const ctrl = this.querySelector('.lib-field__control');
        return ctrl ? ctrl.value : (this.getAttribute('value') || '');
    }
    set value(v) {
        const ctrl = this.querySelector('.lib-field__control');
        if (ctrl) { ctrl.value = v ?? ''; }
        if (v === null || v === undefined || v === '') {
            this.removeAttribute('value');
        } else {
            this.setAttribute('value', v);
        }
    }

    get name() { return this.getAttribute('name') || ''; }
    set name(v) {
        this.setAttribute('name', v);
        const ctrl = this.querySelector('.lib-field__control');
        if (ctrl) { ctrl.name = v; }
    }

    focus() {
        const ctrl = this.querySelector('.lib-field__control');
        if (ctrl) { ctrl.focus(); }
    }
    blur() {
        const ctrl = this.querySelector('.lib-field__control');
        if (ctrl) { ctrl.blur(); }
    }
    select() {
        const ctrl = this.querySelector('.lib-field__control');
        if (ctrl && ctrl.select) { ctrl.select(); }
    }
}

customElements.define('lib-field', LibField);

}
