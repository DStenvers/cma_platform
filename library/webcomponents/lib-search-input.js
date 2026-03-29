/**
 * lib-search-input Web Component
 *
 * A search input field with a clear button and search icon.
 * Uses shared CSS classes for styling (see lib-components.css).
 *
 * Usage:
 *   <lib-search-input name="search" placeholder="Zoeken..."></lib-search-input>
 *   <lib-search-input id="tableSearch" placeholder="Zoek op tabelnaam"></lib-search-input>
 *   <lib-search-input name="q" icon="right" autofocus></lib-search-input>
 *
 * Attributes:
 *   - name: Field name for form submission
 *   - id: Element ID (also applied to internal input)
 *   - placeholder: Placeholder text
 *   - value: Initial value
 *   - disabled: Prevent interaction
 *   - autofocus: Focus on connect
 *   - icon: Icon position - "left" (default), "right", or "none"
 *   - autocomplete: Autocomplete attribute (default: "off")
 *
 * Events:
 *   - input: Fired on every keystroke, detail: { value: string }
 *   - change: Fired on blur or enter, detail: { value: string }
 *   - search: Fired on enter key, detail: { value: string }
 *   - clear: Fired when clear button is clicked, detail: { previousValue: string }
 *
 * Methods:
 *   - focus(): Focus the input
 *   - clear(): Clear the input value
 *   - select(): Select all text
 */
// Guard against double registration
if (!customElements.get('lib-search-input')) {

class LibSearchInput extends HTMLElement {
    static get observedAttributes() {
        return ['value', 'placeholder', 'disabled', 'name', 'icon', 'autocomplete'];
    }

    constructor() {
        super();
        this._value = '';
        this._rendered = false;
    }

    connectedCallback() {
        if (!this._rendered) {
            this.render();
            this._rendered = true;
        }
        this._updateClearButton();

        if (this.hasAttribute('autofocus')) {
            // Delay focus to ensure element is fully in DOM
            requestAnimationFrame(() => this.focus());
        }
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;

        if (name === 'value') {
            this._value = newValue || '';
            if (this._rendered) {
                const input = this.querySelector('input');
                if (input && input.value !== this._value) {
                    input.value = this._value;
                }
                this._updateClearButton();
            }
        } else if (name === 'placeholder') {
            const input = this.querySelector('input');
            if (input) input.placeholder = newValue || '';
        } else if (name === 'disabled') {
            const input = this.querySelector('input');
            if (input) input.disabled = newValue !== null;
        } else if (this._rendered) {
            this.render();
        }
    }

    get value() {
        const input = this.querySelector('input');
        return input ? input.value : this._value;
    }

    set value(val) {
        this._value = val || '';
        const input = this.querySelector('input');
        if (input) {
            input.value = this._value;
        }
        this.setAttribute('value', this._value);
        this._updateClearButton();
    }

    get name() {
        return this.getAttribute('name') || '';
    }

    set name(val) {
        this.setAttribute('name', val);
    }

    focus() {
        const input = this.querySelector('input');
        if (input) input.focus();
    }

    select() {
        const input = this.querySelector('input');
        if (input) input.select();
    }

    clear() {
        const previousValue = this.value;
        this.value = '';
        this._dispatchEvent('clear', { previousValue });
        this._dispatchEvent('input', { value: '' });
        this.focus();
    }

    render() {
        const name = this.getAttribute('name') || '';
        const id = this.getAttribute('id') || '';
        const placeholder = this.getAttribute('placeholder') || 'Zoeken...';
        const disabled = this.hasAttribute('disabled');
        const iconPos = this.getAttribute('icon') || 'left';
        const autocomplete = this.getAttribute('autocomplete') || 'off';
        const value = this.getAttribute('value') || this._value || '';

        // Build classes
        const containerClasses = ['lib-search-input'];
        if (iconPos === 'left') containerClasses.push('icon-left');
        if (iconPos === 'right') containerClasses.push('icon-right');
        if (iconPos === 'none') containerClasses.push('no-icon');
        if (disabled) containerClasses.push('disabled');

        this.innerHTML = `
            <div class="${containerClasses.join(' ')}">
                ${iconPos !== 'none' ? '<span class="search-icon lnr lnr-magnifier"></span>' : ''}
                <input type="text"
                    ${name ? `name="${name}"` : ''}
                    ${id ? `data-input-id="${id}"` : ''}
                    placeholder="${this._escapeAttr(placeholder)}"
                    autocomplete="${autocomplete}"
                    value="${this._escapeAttr(value)}"
                    ${disabled ? 'disabled' : ''}>
                <button type="button" class="clear-btn" title="Wissen" tabindex="-1">
                    <span class="lnr lnr-cross"></span>
                </button>
            </div>
        `;

        this._setupEventListeners();
        this._updateClearButton();
    }

    _setupEventListeners() {
        const input = this.querySelector('input');
        const clearBtn = this.querySelector('.clear-btn');
        const searchIcon = this.querySelector('.search-icon');

        if (searchIcon && input) {
            searchIcon.addEventListener('click', (e) => {
                if (input.value) {
                    e.preventDefault();
                    e.stopPropagation();
                    this._dispatchEvent('search', { value: input.value });
                }
            });
        }

        if (input) {
            input.addEventListener('input', (e) => {
                this._value = e.target.value;
                this._updateClearButton();
                this._dispatchEvent('input', { value: this._value });
            });

            input.addEventListener('change', (e) => {
                this._dispatchEvent('change', { value: e.target.value });
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this._dispatchEvent('search', { value: input.value });
                }
                if (e.key === 'Escape' && input.value) {
                    e.preventDefault();
                    this.clear();
                }
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.clear();
            });
        }
    }

    _updateClearButton() {
        const clearBtn = this.querySelector('.clear-btn');
        const input = this.querySelector('input');
        if (clearBtn && input) {
            clearBtn.classList.toggle('visible', !!input.value);
        }
        this._updateSearchIcon();
    }

    _updateSearchIcon() {
        const icon = this.querySelector('.search-icon');
        const input = this.querySelector('input');
        if (!icon || !input) return;
        icon.classList.toggle('has-value', !!input.value);
    }

    _dispatchEvent(eventName, detail) {
        this.dispatchEvent(new CustomEvent(eventName, {
            bubbles: true,
            composed: true,
            detail
        }));
    }

    _escapeAttr(str) {
        if (!str) return '';
        return str.replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
}

// Register the component
customElements.define('lib-search-input', LibSearchInput);

} // end guard
