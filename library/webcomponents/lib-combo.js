/**
 * lib-combo Web Component
 *
 * A searchable dropdown component with AJAX support.
 * Similar to Select2 but as a web component.
 *
 * Usage:
 *   <lib-combo name="category" placeholder="Selecteer...">
 *     <option value="1">Option 1</option>
 *     <option value="2">Option 2</option>
 *   </lib-combo>
 *
 *   // With AJAX:
 *   <lib-combo name="user" ajax-url="api.php?action=search" ajax-id="id" ajax-text="name"></lib-combo>
 *
 * Attributes:
 *   - name: Form field name
 *   - value: Selected value
 *   - placeholder: Placeholder text
 *   - multiple: Allow multiple selection
 *   - disabled: Disable the component
 *   - ajax-url: URL for AJAX loading
 *   - ajax-id: Property name for value in AJAX response
 *   - ajax-text: Property name for label in AJAX response
 *   - min-search: Minimum characters before search (default: 0)
 *
 * Events:
 *   - change: Fired when selection changes
 *   - search: Fired when search term changes
 */

// Guard against double registration
if (!customElements.get('lib-combo')) {

class LibCombo extends HTMLElement {
    static get observedAttributes() {
        return ['value', 'placeholder', 'disabled', 'readonly', 'multiple', 'ajax-url', 'required'];
    }

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._options = [];
        this._selectedValues = [];
        this._isOpen = false;
        this._highlightedIndex = -1;
        this._searchTimeout = null;
        this._loading = false;
        this._unmatchedValues = []; // Track values that couldn't be matched
        this._rendered = false; // Guard for attributeChangedCallback
    }

    /**
     * Check if running in development mode
     */
    _isDevMode() {
        if (typeof window.CMA_DEBUG !== 'undefined') {
            return window.CMA_DEBUG;
        }
        const hostname = window.location.hostname.toLowerCase();
        return hostname === 'localhost' ||
               hostname === '127.0.0.1' ||
               hostname.indexOf('172.') === 0;
    }

    /**
     * Report an error (shows in error panel in dev mode)
     */
    _reportError(message, details = {}) {
        const fullMessage = `[lib-combo name="${this.getAttribute('name') || 'unknown'}"] ${message}`;
        const error = new Error(fullMessage);

        // Use cmaLog if available, otherwise console.error
        if (typeof cmaLog !== 'undefined') {
            cmaLog.error('[lib-combo]', fullMessage, details);
        } else {
            console.error('[lib-combo]', fullMessage, details);
        }

        // Throw as uncaught error (async to not interrupt execution)
        setTimeout(() => { throw error; }, 0);

        // In dev mode, dispatch to error panel if available
        if (this._isDevMode()) {
            window.dispatchEvent(new CustomEvent('cma-component-error', {
                detail: {
                    tagName: 'LIB-COMBO',
                    context: 'value matching',
                    error: error
                }
            }));
        }
    }

    connectedCallback() {
        // Adopt shared styles if available
        if (typeof LibSharedStyles !== 'undefined' && LibSharedStyles.isSupported()) {
            LibSharedStyles.adopt(this.shadowRoot, 'base', 'input', 'dropdown', 'animation');
        }

        this.render();
        this._rendered = true;
        this._collectOptions();
        this._setupEventListeners();

        if (this._options.length > 0) {
            // Options available immediately — render and restore value
            this._renderOptions();
            this._restoreInitialValue();
        } else {
            // No options found — innerHTML parsing may not be complete yet.
            // Defer to allow the parser to finish creating child <option> elements.
            Promise.resolve().then(() => {
                // Re-check: skip if options were set programmatically in the meantime
                if (this._options.length > 0) return;
                this._collectOptions();
                if (this._options.length > 0) {
                    this._renderOptions();
                    this._restoreInitialValue();
                } else {
                    this._renderOptions(); // render "no results" state
                }
            });
        }
    }

    /**
     * Restore value from attribute or previously-set JS property
     */
    _restoreInitialValue() {
        const initialValue = this.getAttribute('value');
        if (initialValue) {
            this.value = initialValue;
        } else if (this._selectedValues && this._selectedValues.length > 0) {
            // Value was set via JS property before connectedCallback — re-render display
            this._updateDisplay();
            this._updateHiddenInput();
        }
    }

    disconnectedCallback() {
        this._removeDocumentListeners();
        // Clear search timeout to prevent memory leaks
        if (this._searchTimeout) {
            clearTimeout(this._searchTimeout);
            this._searchTimeout = null;
        }
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;
        // Guard: Don't process if shadow DOM not yet rendered
        if (!this._rendered) return;

        switch (name) {
            case 'value':
                this._setValueFromAttribute(newValue);
                break;
            case 'disabled':
                this._updateDisabledState();
                break;
            case 'placeholder':
                this._updatePlaceholder();
                break;
            case 'required':
                this._updateRequiredState();
                break;
        }
    }

    get value() {
        if (this.hasAttribute('multiple')) {
            return this._selectedValues;
        }
        return this._selectedValues[0] || '';
    }

    set value(val) {
        if (this.hasAttribute('multiple')) {
            this._selectedValues = Array.isArray(val) ? val : [val].filter(v => v);
        } else {
            this._selectedValues = val ? [String(val)] : []; // Convert to string for consistent matching
        }

        this._updateDisplay();
        this._updateHiddenInput();
    }

    get selectedOptions() {
        return this._options.filter(opt =>
            this._selectedValues.some(v => String(v) === String(opt.value))
        );
    }

    render() {
        const placeholder = this.getAttribute('placeholder') || 'Selecteer...';
        const isMultiple = this.hasAttribute('multiple');
        const isDisabled = this.hasAttribute('disabled');
        const isReadonly = this.hasAttribute('readonly');

        // Use CMA icon system if available, otherwise use @font-face fallback
        const iconStyles = (typeof CMA !== 'undefined' && typeof CMA.getIconStylesFor === 'function')
            ? CMA.getIconStylesFor(['chevron-down', 'cross', 'magnifier'])
            : `@font-face {
                    font-family: 'Linearicons';
                    src: url('../library/fonts/Linearicons/Font/Linearicons.woff2') format('woff2'),
                         url('../library/fonts/Linearicons/Font/Linearicons.woff') format('woff');
                }`;

        // Arrow markup: use lnr class when CMA icons available, otherwise use ::before content
        const hasCmaIcons = (typeof CMA !== 'undefined' && typeof CMA.getIconStylesFor === 'function');
        const arrowClass = hasCmaIcons ? 'combo-arrow lnr lnr-chevron-down' : 'combo-arrow';

        // Component-specific CSS (uses CSS vars from shared styles)
        this.shadowRoot.innerHTML = `
            <style>
                ${iconStyles}

                :host {
                    display: inline-block;
                    position: relative;
                    width: 100%;
                }

                .combo-container {
                    position: relative;
                }

                .combo-display {
                    display: flex;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: var(--spacing-xs, 4px);
                    padding: var(--spacing-xs, 2px) 32px var(--spacing-xs, 2px) var(--spacing-sm, 10px);
                    border: 1px solid var(--border-color, #ccc);
                    border-radius: var(--radius-md, 4px);
                    border-top-right-radius: var(--combo-right-radius, var(--radius-md, 4px));
                    border-bottom-right-radius: var(--combo-right-radius, var(--radius-md, 4px));
                    background-color: var(--input-bg, #fff);
                    cursor: pointer;
                    transition: border-color var(--transition-base, 0.15s ease), box-shadow var(--transition-base, 0.15s ease);
                    min-height: 26px;
                    height: 26px;
                }

                .combo-display:hover {
                    border-color: var(--border-dark, #999);
                }

                .combo-display.open {
                    border-bottom-right-radius: 0;
                    border-bottom-left-radius: 0;
                }

                .combo-display.disabled {
                    background-color: var(--bg-disabled, #f5f5f5);
                    cursor: not-allowed;
                    opacity: 0.7;
                }

                .combo-display.readonly {
                    background-color: var(--bg-disabled, #f5f5f5);
                    cursor: default;
                    opacity: 0.7;
                }

                /* Required indicator - red left border when empty */
                :host([required]) .combo-display {
                    border-left: 3px solid var(--color-error, #dc3545);
                }

                :host([required]) .combo-display.has-value {
                    border-left: 3px solid var(--border-color, #ccc);
                    padding-right: 40px;
                }

                :host([required][readonly]) .combo-display {
                    border-left: none;
                }

                .combo-placeholder {
                    color: var(--text-muted, #999);
                    font-style: italic;
                }

                .combo-value {
                    color: var(--text-primary, #333);
                }

                .combo-tag {
                    display: inline-flex;
                    align-items: center;
                    gap: var(--spacing-xs, 4px);
                    padding: 2px var(--spacing-sm, 8px);
                    background-color: var(--bg-hover, #d0e8f8);
                    border-radius: var(--radius-sm, 3px);
                    font-size: var(--font-size-sm, 13px);
                    color: var(--color-primary-dark, #1a365d);
                }

                .combo-tag-remove {
                    cursor: pointer;
                    font-size: var(--font-size-lg);
                    line-height: 1;
                    opacity: 0.6;
                }

                .combo-tag-remove:hover {
                    opacity: 1;
                }

                .combo-arrow {
                    position: absolute;
                    right: 8px;
                    top: 50%;
                    transform: translateY(-50%);
                    font-size: var(--font-size-xs);
                    color: var(--text-secondary, #666);
                    transition: transform 0.2s ease;
                    pointer-events: none;
                }

                /* Fallback arrow icon when CMA icon system is not available */
                .combo-arrow:not(.lnr)::before {
                    font-family: 'Linearicons';
                    content: "\\e93a";
                }

                .combo-display:hover .combo-arrow::before {
                    color: var(--color-accent, #e65100);
                }

                .combo-display.open .combo-arrow {
                    transform: translateY(-50%) rotate(180deg);
                }

                .combo-dropdown {
                    position: fixed;
                    background-color: var(--bg-surface, #fff);
                    border: 1px solid var(--border-color, #ccc);
                    border-radius: var(--radius-md, 4px);
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                    z-index: var(--z-dropdown, 1000);
                    display: none;
                    max-height: 300px;
                    max-width: 90vw;
                    overflow: hidden;
                    width: max-content;
                }

                .combo-dropdown.open {
                    display: block;
                    margin-top: -14px;
                    border-top-left-radius: 0;
                    border-top-right-radius: 0;
                    animation: fadeIn var(--transition-base, 0.15s ease);
                }

                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(-4px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                .combo-search {
                    padding: 8px;
                    border: none;
                    position: relative;
                    display: flex;
                    align-items: center;
                    gap: 4px;
                }

                .combo-search .search-icon {
                    position: absolute;
                    left: 14px;
                    top: 50%;
                    transform: translateY(-50%);
                    pointer-events: none;
                }

                .combo-search .search-icon::before {
                    font-family: 'Linearicons';
                    content: "\\e922";
                    display: inline-block;
                    color: var(--text-muted, #999);
                    font-size: var(--font-size-sm);
                    font-weight: normal;
                }

                .combo-search .clear-btn {
                    position: absolute;
                    right: 12px;
                    top: 50%;
                    transform: translateY(-50%);
                    background: none;
                    border: none;
                    cursor: pointer;
                    padding: 2px;
                    display: none;
                    align-items: center;
                    justify-content: center;
                }

                .combo-search .clear-btn.visible {
                    display: flex;
                }

                .combo-search .clear-btn .lnr {
                    width: 12px;
                    height: 12px;
                }

                .combo-search .clear-btn .lnr::before {
                    font-family: 'Linearicons';
                    content: "\\e92a";
                    display: inline-block;
                    font-size: var(--font-size-2xs);
                    color: var(--text-muted, #999);
                    width: 12px;
                    height: 12px;
                    line-height: 12px;
                    font-weight: normal;
                }

                .combo-search .clear-btn:hover .lnr::before {
                    color: var(--color-error, #dc3545);
                }

                .combo-search input {
                    width: 100%;
                    padding: 4px 24px 4px 24px;
                    border: 1px solid var(--border-color, #ddd);
                    border-radius: var(--radius-md, 4px);
                    font-size: var(--font-size);
                    outline: none;
                    box-sizing: border-box;
                }

                .combo-search input::placeholder {
                    color: var(--text-muted, #999);
                    font-style: italic;
                }

                .combo-search input:focus {
                    border-color: var(--color-primary, #204496);
                    border-style: dashed;
                }

                .combo-options {
                    max-height: 240px;
                    overflow-y: auto;
                }

                .combo-option {
                    padding: 6px 6px 6px 8px;
                    min-height: 24px;
                    box-sizing: border-box;
                    cursor: pointer;
                    transition: background-color var(--transition-fast, 0.1s ease), border-color var(--transition-fast, 0.1s ease);
                    border: 1px solid transparent;
                }

                .combo-option:hover,
                .combo-option.highlighted {
                    background-color: var(--bg-active, #d0e8f8);
                    border-color: var(--border-hover, #077ab2);
                }

                .combo-option.selected {
                    background-color: var(--bg-hover, #d0e8f8);
                    color: var(--color-primary-dark, #1a365d);
                }

                .combo-option.disabled {
                    color: var(--text-muted, #999);
                    cursor: not-allowed;
                }

                .combo-option-group {
                    padding: var(--spacing-sm, 8px) var(--spacing-md, 12px) var(--spacing-xs, 4px);
                    font-size: var(--font-size-sm, 12px);
                    font-weight: 600;
                    color: var(--text-secondary, #666);
                    text-transform: uppercase;
                }

                .combo-empty {
                    padding: var(--spacing-lg, 20px);
                    text-align: center;
                    color: var(--text-muted, #999);
                }

                .combo-loading {
                    padding: var(--spacing-lg, 16px);
                    text-align: center;
                    color: var(--text-secondary, #666);
                }

                .combo-loading::before {
                    content: '';
                    display: inline-block;
                    width: 14px;
                    height: 14px;
                    border: 2px solid var(--border-color, #ddd);
                    border-top-color: var(--color-primary, #204496);
                    border-radius: 50%;
                    animation: spin 0.6s linear infinite;
                    margin-right: var(--spacing-sm, 8px);
                    vertical-align: middle;
                }

                @keyframes spin {
                    to { transform: rotate(360deg); }
                }

                .combo-clear {
                    position: absolute;
                    right: 22px;
                    top: 46%;
                    transform: translateY(-50%);
                    cursor: pointer;
                    color: var(--text-muted, #999);
                    line-height: 1;
                    padding: 2px;
                }

                .combo-clear::before {
                    font-family: 'Linearicons';
                    content: "\\e92a";
                    display: inline-block;
                    color: var(--text-primary);
                    font-size: 12px;
                    line-height: 23px;
                    width: 23px;
                    height: 23px;
                    text-align: center;
                    font-weight: normal !important;
                }

                .combo-clear:hover::before {
                    color: var(--color-accent, #e65100);
                }

                input[type="hidden"] {
                    display: none;
                }
            </style>
            <div class="combo-container">
                <div class="combo-display ${isDisabled ? 'disabled' : ''}${isReadonly ? ' readonly' : ''}" tabindex="${(isDisabled || isReadonly) ? -1 : 0}">
                    <span class="combo-placeholder">${placeholder}</span>
                    <span class="${arrowClass}"></span>
                </div>
                <div class="combo-dropdown">
                    <div class="combo-search">
                        <span class="search-icon"></span>
                        <input type="text" placeholder="Zoeken..." autocomplete="off">
                        <button type="button" class="clear-btn" tabindex="-1" data-tooltip="Wissen">
                            <span class="lnr lnr-cross"></span>
                        </button>
                    </div>
                    <div class="combo-options"></div>
                </div>
                <input type="hidden" name="${this.getAttribute('name') || ''}">
            </div>
        `;

        // Store arrow class for re-creation in _updateDisplay
        this._arrowClass = arrowClass;
    }

    _collectOptions() {
        // Collect options from light DOM
        this._options = [];

        const options = this.querySelectorAll('option');
        options.forEach(opt => {
            this._options.push({
                value: opt.value,
                label: opt.textContent,
                disabled: opt.disabled,
                group: opt.parentElement.tagName === 'OPTGROUP' ? opt.parentElement.label : null
            });
        });
    }

    _renderOptions(filter = '') {
        const container = this.shadowRoot.querySelector('.combo-options');
        if (!container) return;

        if (this._loading) {
            container.innerHTML = '<div class="combo-loading">Laden...</div>';
            return;
        }

        const filterLower = filter.toLowerCase();
        const filteredOptions = this._options.filter(opt =>
            !filter || opt.label.toLowerCase().includes(filterLower)
        );

        if (filteredOptions.length === 0) {
            container.innerHTML = '<div class="combo-empty">Geen resultaten</div>';
            return;
        }

        let html = '';
        let currentGroup = null;

        filteredOptions.forEach((opt, index) => {
            if (opt.group && opt.group !== currentGroup) {
                currentGroup = opt.group;
                html += `<div class="combo-option-group">${this._escapeHtml(currentGroup)}</div>`;
            }

            // Use string comparison to handle string/number type mismatches
            const isSelected = this._selectedValues.some(v => String(v) === String(opt.value));
            const isDisabled = opt.disabled;
            const classes = ['combo-option'];
            if (isSelected) classes.push('selected');
            if (isDisabled) classes.push('disabled');

            html += `<div class="${classes.join(' ')}" data-value="${this._escapeHtml(opt.value)}" data-index="${index}">
                ${this._escapeHtml(opt.label)}
            </div>`;
        });

        container.innerHTML = html;
        this._highlightedIndex = -1;
    }

    _setupEventListeners() {
        const display = this.shadowRoot.querySelector('.combo-display');
        const dropdown = this.shadowRoot.querySelector('.combo-dropdown');
        const searchInput = this.shadowRoot.querySelector('.combo-search input');
        const optionsContainer = this.shadowRoot.querySelector('.combo-options');

        // Toggle dropdown
        display.addEventListener('click', () => {
            if (this.hasAttribute('disabled') || this.hasAttribute('readonly')) return;
            this._isOpen ? this._close() : this._open();
        });

        // Keyboard navigation on display
        display.addEventListener('keydown', (e) => {
            if (this.hasAttribute('disabled') || this.hasAttribute('readonly')) return;

            switch (e.key) {
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    this._isOpen ? this._close() : this._open();
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    if (!this._isOpen) this._open();
                    else this._highlightNext();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    if (!this._isOpen) this._open();
                    else this._highlightPrev();
                    break;
                case 'Escape':
                    this._close();
                    break;
            }
        });

        // Clear button
        const clearBtn = this.shadowRoot.querySelector('.combo-search .clear-btn');
        if (clearBtn) {
            clearBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                searchInput.value = '';
                clearBtn.classList.remove('visible');
                searchInput.dispatchEvent(new Event('input'));
                searchInput.focus();
            });
        }

        // Search input with debounce (prevents excessive filtering/API calls)
        searchInput.addEventListener('input', (e) => {
            const term = e.target.value;
            if (clearBtn) clearBtn.classList.toggle('visible', term.length > 0);

            // Clear previous timeout
            clearTimeout(this._searchTimeout);

            if (this.hasAttribute('ajax-url')) {
                // AJAX: debounce at 300ms to prevent API spam
                this._searchTimeout = setTimeout(() => {
                    this._searchAjax(term);
                    this.dispatchEvent(new CustomEvent('search', {
                        detail: { term },
                        bubbles: true
                    }));
                }, 300);
            } else {
                // Local filtering: debounce at 50ms (fast but avoids excessive DOM updates)
                this._searchTimeout = setTimeout(() => {
                    this._renderOptions(term);
                    this.dispatchEvent(new CustomEvent('search', {
                        detail: { term },
                        bubbles: true
                    }));
                }, 50);
            }
        });

        searchInput.addEventListener('keydown', (e) => {
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this._highlightNext();
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this._highlightPrev();
                    break;
                case 'Enter':
                    e.preventDefault();
                    this._selectHighlighted();
                    break;
                case 'Escape':
                    this._close();
                    display.focus();
                    break;
            }
        });

        // Option click
        optionsContainer.addEventListener('click', (e) => {
            const option = e.target.closest('.combo-option');
            if (!option || option.classList.contains('disabled')) return;

            this._selectOption(option.dataset.value);
        });

        // Document click to close
        this._documentClickHandler = (e) => {
            if (!this.contains(e.target) && !this.shadowRoot.contains(e.target)) {
                this._close();
            }
        };
    }

    _open() {
        if (this._isOpen) return;

        this._isOpen = true;
        const display = this.shadowRoot.querySelector('.combo-display');
        const dropdown = this.shadowRoot.querySelector('.combo-dropdown');
        const searchInput = this.shadowRoot.querySelector('.combo-search input');

        display.classList.add('open');
        dropdown.classList.add('open');

        // Position dropdown using fixed positioning
        this._positionDropdown();

        // Clear search and refresh options
        searchInput.value = '';
        this._renderOptions();

        // Focus search input
        setTimeout(() => searchInput.focus(), 10);

        // Add document listener
        document.addEventListener('click', this._documentClickHandler);

        // Add scroll/resize listeners to reposition
        this._scrollHandler = () => this._positionDropdown();
        window.addEventListener('scroll', this._scrollHandler, true);
        window.addEventListener('resize', this._scrollHandler);
    }

    _positionDropdown() {
        const display = this.shadowRoot.querySelector('.combo-display');
        const dropdown = this.shadowRoot.querySelector('.combo-dropdown');
        if (!display || !dropdown) return;

        const rect = display.getBoundingClientRect();
        const dropdownHeight = 300; // max-height
        const viewportHeight = window.innerHeight;

        // Detect containing block offset for position:fixed
        // CSS 'contain: layout', 'transform', 'filter' on ancestors create a new
        // containing block, making fixed positioning relative to that ancestor
        // instead of the viewport. We must compensate for this offset.
        const containerOffset = this._getContainingBlockOffset(dropdown);

        // Position below by default
        let top = rect.bottom + 4 - containerOffset.top;
        let left = rect.left - containerOffset.left;
        let openAbove = false;

        // Check if dropdown would be clipped at bottom
        if (rect.bottom + 4 + dropdownHeight > viewportHeight && rect.top > dropdownHeight) {
            // Open above
            top = rect.top - dropdownHeight - 4 - containerOffset.top;
            openAbove = true;
        }

        dropdown.style.top = top + 'px';
        dropdown.style.left = left + 'px';
        dropdown.style.minWidth = rect.width + 'px';
        dropdown.classList.toggle('open-above', openAbove);
    }

    /**
     * Detect if a containing block (from CSS contain, transform, filter, etc.)
     * offsets fixed positioning. Returns the offset to compensate for.
     */
    _getContainingBlockOffset(element) {
        // Temporarily position at a known fixed point and measure the actual position
        // This is the most reliable method - works regardless of which CSS property
        // creates the containing block (contain, transform, filter, will-change, etc.)
        const saved = { top: element.style.top, left: element.style.left, display: element.style.display };
        element.style.top = '0px';
        element.style.left = '0px';
        element.style.display = 'block';

        const actualRect = element.getBoundingClientRect();

        element.style.top = saved.top;
        element.style.left = saved.left;
        element.style.display = saved.display;

        return { top: actualRect.top, left: actualRect.left };
    }

    _close() {
        if (!this._isOpen) return;

        this._isOpen = false;
        const display = this.shadowRoot.querySelector('.combo-display');
        const dropdown = this.shadowRoot.querySelector('.combo-dropdown');

        display.classList.remove('open');
        dropdown.classList.remove('open');
        dropdown.classList.remove('open-above');

        this._removeDocumentListeners();
    }

    _removeDocumentListeners() {
        if (this._documentClickHandler) {
            document.removeEventListener('click', this._documentClickHandler);
        }
        if (this._scrollHandler) {
            window.removeEventListener('scroll', this._scrollHandler, true);
            window.removeEventListener('resize', this._scrollHandler);
            this._scrollHandler = null;
        }
    }

    _highlightNext() {
        const options = this.shadowRoot.querySelectorAll('.combo-option:not(.disabled)');
        if (options.length === 0) return;

        this._highlightedIndex = Math.min(this._highlightedIndex + 1, options.length - 1);
        this._updateHighlight();
    }

    _highlightPrev() {
        const options = this.shadowRoot.querySelectorAll('.combo-option:not(.disabled)');
        if (options.length === 0) return;

        this._highlightedIndex = Math.max(this._highlightedIndex - 1, 0);
        this._updateHighlight();
    }

    _updateHighlight() {
        const options = this.shadowRoot.querySelectorAll('.combo-option:not(.disabled)');
        options.forEach((opt, i) => {
            opt.classList.toggle('highlighted', i === this._highlightedIndex);
        });

        // Scroll into view
        if (this._highlightedIndex >= 0 && options[this._highlightedIndex]) {
            options[this._highlightedIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    _selectHighlighted() {
        const options = this.shadowRoot.querySelectorAll('.combo-option:not(.disabled)');
        if (this._highlightedIndex >= 0 && options[this._highlightedIndex]) {
            this._selectOption(options[this._highlightedIndex].dataset.value);
        }
    }

    _selectOption(value) {
        const isMultiple = this.hasAttribute('multiple');
        // Normalize value to string for consistent comparison
        const strValue = String(value);

        if (isMultiple) {
            // Use string comparison to find existing value
            const index = this._selectedValues.findIndex(v => String(v) === strValue);
            if (index === -1) {
                this._selectedValues.push(strValue);
            } else {
                this._selectedValues.splice(index, 1);
            }
            this._renderOptions(this.shadowRoot.querySelector('.combo-search input').value);
        } else {
            this._selectedValues = [strValue];
            this._close();
        }

        this._updateDisplay();
        this._updateHiddenInput();

        this.dispatchEvent(new CustomEvent('change', {
            detail: { value: this.value, selectedOptions: this.selectedOptions },
            bubbles: true
        }));
    }

    _updateDisplay() {
        const display = this.shadowRoot.querySelector('.combo-display');
        const placeholder = this.getAttribute('placeholder') || 'Selecteer...';
        const isMultiple = this.hasAttribute('multiple');

        // Remove existing content except arrow
        display.innerHTML = '';

        // Track unmatched values for error reporting
        const unmatchedValues = [];

        if (this._selectedValues.length === 0) {
            display.innerHTML = `<span class="combo-placeholder">${placeholder}</span>`;
        } else if (isMultiple) {
            // Show tags
            this._selectedValues.forEach(val => {
                // Use loose equality to handle string/number mismatches
                const option = this._options.find(o => String(o.value) === String(val));
                if (option) {
                    const tag = document.createElement('span');
                    tag.className = 'combo-tag';
                    tag.innerHTML = `
                        ${this._escapeHtml(option.label)}
                        <span class="combo-tag-remove" data-value="${this._escapeHtml(val)}">&times;</span>
                    `;
                    tag.querySelector('.combo-tag-remove').addEventListener('click', (e) => {
                        e.stopPropagation();
                        this._selectOption(val);
                    });
                    display.appendChild(tag);
                } else {
                    unmatchedValues.push(val);
                }
            });
        } else {
            // Show single value - use loose equality to handle string/number mismatches
            const searchValue = this._selectedValues[0];
            const option = this._options.find(o => String(o.value) === String(searchValue));
            if (!option && searchValue) {
                unmatchedValues.push(searchValue);
            }
            const displayLabel = option ? this._escapeHtml(option.label) : searchValue;
            display.innerHTML = `<span class="combo-value">${displayLabel}</span>`;

            // Add clear button for single select
            if (option) {
                const clear = document.createElement('span');
                clear.className = 'combo-clear';
                clear.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this._selectedValues = [];
                    this._updateDisplay();
                    this._updateHiddenInput();
                    this.dispatchEvent(new CustomEvent('change', {
                        detail: { value: '', selectedOptions: [] },
                        bubbles: true
                    }));
                });
                display.appendChild(clear);
            }
        }

        // Re-add arrow
        const newArrow = document.createElement('span');
        newArrow.className = this._arrowClass || 'combo-arrow';
        display.appendChild(newArrow);

        // Toggle has-value class for required styling
        display.classList.toggle('has-value', this._selectedValues.length > 0);

        // Warn about unmatched values (don't throw — caller may set values before options are loaded)
        if (unmatchedValues.length > 0 && this._options.length > 0) {
            console.warn(`[lib-combo name="${this.getAttribute('name') || 'unknown'}"] Value(s) not found in options: "${unmatchedValues.join('", "')}"`,
                { unmatchedValues, availableCount: this._options.length });
        }
    }

    _updateRequiredState() {
        // Required state is handled purely via CSS :host([required])
        // Just ensure the has-value class is current
        const display = this.shadowRoot.querySelector('.combo-display');
        if (display) {
            display.classList.toggle('has-value', this._selectedValues.length > 0);
        }
    }

    _updateHiddenInput() {
        const input = this.shadowRoot.querySelector('input[type="hidden"]');
        if (input) {
            if (this.hasAttribute('multiple')) {
                input.value = this._selectedValues.join(',');
            } else {
                input.value = this._selectedValues[0] || '';
            }
        }
    }

    _setValueFromAttribute(value) {
        if (this.hasAttribute('multiple')) {
            this._selectedValues = value ? value.split(',') : [];
        } else {
            this._selectedValues = value ? [value] : [];
        }
        this._updateDisplay();
        this._updateHiddenInput();
    }

    _updateDisabledState() {
        const display = this.shadowRoot.querySelector('.combo-display');
        if (display) {
            const isDisabled = this.hasAttribute('disabled');
            display.classList.toggle('disabled', isDisabled);
            display.setAttribute('tabindex', isDisabled ? -1 : 0);
        }
    }

    _updatePlaceholder() {
        if (this._selectedValues.length === 0) {
            this._updateDisplay();
        }
    }

    _searchAjax(term) {
        const minSearch = parseInt(this.getAttribute('min-search') || '0', 10);

        if (term.length < minSearch) {
            this._renderOptions();
            return;
        }

        // Debounce is handled by the input event listener
        this._loading = true;
        this._renderOptions();

        const url = this.getAttribute('ajax-url');
        const separator = url.includes('?') ? '&' : '?';

        fetch(`${url}${separator}q=${encodeURIComponent(term)}`)
            .then(res => res.json())
            .then(data => {
                const idField = this.getAttribute('ajax-id') || 'id';
                const textField = this.getAttribute('ajax-text') || 'text';

                this._options = (data.results || data.options || data).map(item => ({
                    value: String(item[idField]),
                    label: item[textField],
                    disabled: false,
                    group: null
                }));

                this._loading = false;
                this._renderOptions();
            })
            .catch(err => {
                const logFn = (typeof cmaLog !== 'undefined') ? cmaLog : console;
                logFn.error('[lib-combo] AJAX error:', err.message);
                this._loading = false;
                this._options = [];
                this._renderOptions();
            });
    }

    /**
     * Add option programmatically
     * @param {string} value
     * @param {string} label
     * @param {string} group
     */
    addOption(value, label, group = null) {
        this._options.push({ value, label, disabled: false, group });
        if (this._isOpen) {
            this._renderOptions(this.shadowRoot.querySelector('.combo-search input').value);
        }
    }

    /**
     * Remove option programmatically
     * @param {string} value
     */
    removeOption(value) {
        // Use string comparison for type-safe filtering
        this._options = this._options.filter(o => String(o.value) !== String(value));
        this._selectedValues = this._selectedValues.filter(v => String(v) !== String(value));
        if (this._isOpen) {
            this._renderOptions(this.shadowRoot.querySelector('.combo-search input').value);
        }
        this._updateDisplay();
        this._updateHiddenInput();
    }

    /**
     * Clear all options
     */
    clearOptions() {
        this._options = [];
        this._selectedValues = [];
        this._updateDisplay();
        this._updateHiddenInput();
        if (this._isOpen) {
            this._renderOptions();
        }
    }

    /**
     * Set options from array
     * @param {Array} options Array of {value, label} objects
     */
    setOptions(options) {
        this._options = options.map(o => ({
            value: String(o.value),
            label: o.label || o.text || String(o.value),
            disabled: o.disabled || false,
            group: o.group || null
        }));
        this._renderOptions();
        // Re-check value matching now that options are loaded
        if (this._selectedValues.length > 0) {
            this._updateDisplay();
        }
    }

    _escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Register the component
customElements.define('lib-combo', LibCombo);

// Register cma-combo as an alias for backward compatibility
customElements.define('cma-combo', class extends LibCombo {});

} // End guard against double registration
