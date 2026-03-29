/**
 * lib-menu - Context Menu Web Component
 *
 * A reusable dropdown/context menu component with:
 * - Declarative menu items via slots or items attribute
 * - Keyboard navigation (arrow keys, enter, escape)
 * - Auto-positioning (avoids viewport edges)
 * - Click-outside to close
 * - Support for icons, separators, and danger items
 * - Dark mode support via CSS variables
 *
 * Usage:
 *
 * 1. Declarative (slot-based):
 *    <lib-menu>
 *      <lib-menu-item icon="lnr-pencil" action="edit">Wijzigen</lib-menu-item>
 *      <lib-menu-item separator></lib-menu-item>
 *      <lib-menu-item icon="lnr-trash" action="delete" danger>Verwijderen</lib-menu-item>
 *    </lib-menu>
 *
 * 2. Programmatic:
 *    const menu = document.createElement('lib-menu');
 *    menu.items = [
 *      { label: 'Wijzigen', icon: 'lnr-pencil', action: 'edit' },
 *      { separator: true },
 *      { label: 'Verwijderen', icon: 'lnr-trash', action: 'delete', danger: true }
 *    ];
 *    menu.show(x, y);
 *
 * 3. With trigger element:
 *    <button id="trigger">Menu</button>
 *    <lib-menu trigger="#trigger">...</lib-menu>
 *
 * Events:
 *   - 'menu-select': Fired when item is selected. detail: { action, item, originalEvent }
 *   - 'menu-open': Fired when menu opens
 *   - 'menu-close': Fired when menu closes
 *
 * CSS Variables:
 *   --menu-bg: Background color
 *   --menu-border: Border color
 *   --menu-text: Text color
 *   --menu-hover-bg: Hover background
 *   --menu-danger: Danger item color
 *   --menu-shadow: Box shadow
 */

// Guard against double registration
if (!customElements.get('lib-menu')) {

class LibMenu extends HTMLElement {
    static get observedAttributes() {
        return ['trigger', 'position'];
    }

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._items = [];
        this._isOpen = false;
        this._focusedIndex = -1;
        this._triggerElement = null;
        this._boundHandleOutsideClick = this._handleOutsideClick.bind(this);
        this._boundHandleKeydown = this._handleKeydown.bind(this);
    }

    connectedCallback() {
        // Adopt shared styles if available
        if (typeof LibSharedStyles !== 'undefined' && LibSharedStyles.isSupported()) {
            LibSharedStyles.adopt(this.shadowRoot, 'base', 'dropdown');
        }

        this._render();
        this._setupTrigger();
    }

    disconnectedCallback() {
        this._cleanup();
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (name === 'trigger' && oldValue !== newValue) {
            this._setupTrigger();
        }
    }

    // Public API
    get items() {
        return this._items;
    }

    set items(value) {
        this._items = Array.isArray(value) ? value : [];
        this._renderItems();
    }

    get isOpen() {
        return this._isOpen;
    }

    show(x, y, anchorElement = null) {
        if (this._isOpen) return;

        const menu = this.shadowRoot.querySelector('.menu');
        menu.style.display = 'block';

        // Position the menu
        if (anchorElement) {
            this._positionRelativeTo(anchorElement);
        } else if (x !== undefined && y !== undefined) {
            this._positionAt(x, y);
        }

        this._isOpen = true;
        this._focusedIndex = -1;

        // Add global listeners
        document.addEventListener('click', this._boundHandleOutsideClick, true);
        document.addEventListener('keydown', this._boundHandleKeydown);

        this.dispatchEvent(new CustomEvent('menu-open', { bubbles: true }));
    }

    hide() {
        if (!this._isOpen) return;

        const menu = this.shadowRoot.querySelector('.menu');
        menu.style.display = 'none';

        this._isOpen = false;
        this._focusedIndex = -1;

        // Remove global listeners
        document.removeEventListener('click', this._boundHandleOutsideClick, true);
        document.removeEventListener('keydown', this._boundHandleKeydown);

        this.dispatchEvent(new CustomEvent('menu-close', { bubbles: true }));
    }

    toggle(x, y, anchorElement = null) {
        if (this._isOpen) {
            this.hide();
        } else {
            this.show(x, y, anchorElement);
        }
    }

    // Private methods
    _render() {
        // Use CMA icon system if available, otherwise use @font-face fallback
        const iconStyles = (typeof CMA !== 'undefined' && typeof CMA.getIconStyles === 'function')
            ? CMA.getIconStyles()
            : `@font-face {
                    font-family: 'Linearicons';
                    src: url('../library/fonts/Linearicons/Font/Linearicons.woff2') format('woff2'),
                         url('../library/fonts/Linearicons/Font/Linearicons.woff') format('woff');
                }`;

        this.shadowRoot.innerHTML = `
            <style>
                ${iconStyles}

                :host {
                    display: contents;
                }

                .menu {
                    display: none;
                    position: fixed;
                    z-index: 10000;
                    background: var(--menu-bg, var(--bg-surface, #ffffff));
                    border: 1px solid var(--menu-border, var(--border-color, #ccc));
                    border-radius: 4px;
                    box-shadow: var(--menu-shadow, var(--shadow-lg, 0 4px 12px rgba(0,0,0,0.15)));
                    min-width: 150px;
                    max-width: 300px;
                    overflow: hidden;
                }

                .menu-list {
                    list-style: none;
                    margin: 0;
                    padding: 5px 0;
                }

                ::slotted(lib-menu-item),
                .menu-item {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 8px 15px;
                    cursor: pointer;
                    transition: background-color 0.15s;
                    white-space: nowrap;
                    font-size: var(--font-size);
                    color: var(--menu-text, var(--text-primary, #333));
                }

                .menu-item:hover,
                .menu-item.focused {
                    background: var(--menu-hover-bg, var(--bg-hover, #f5f5f5));
                }

                .menu-item.danger {
                    color: var(--menu-danger, var(--color-error, #dc3545));
                }

                .menu-item.danger:hover,
                .menu-item.danger.focused {
                    background: var(--color-error-bg, #fff5f5);
                }

                .menu-item.separator {
                    padding: 0;
                    margin: 5px 10px;
                    border-top: 1px solid var(--menu-border, var(--border-light, #eee));
                    cursor: default;
                    height: 0;
                }

                .menu-item.separator:hover {
                    background: transparent;
                }

                .menu-item .icon {
                    font-size: var(--font-size-md);
                    width: 16px;
                    text-align: center;
                }

                .menu-item.disabled {
                    opacity: 0.5;
                    cursor: not-allowed;
                }

                .menu-item.disabled:hover {
                    background: transparent;
                }
            </style>
            <div class="menu" role="menu">
                <ul class="menu-list">
                    <slot></slot>
                </ul>
            </div>
        `;

        // Set up click handling for slotted items
        const slot = this.shadowRoot.querySelector('slot');
        slot.addEventListener('slotchange', () => {
            this._setupSlottedItems();
        });

        // Render programmatic items if any
        this._renderItems();
    }

    _renderItems() {
        const list = this.shadowRoot.querySelector('.menu-list');
        const slot = this.shadowRoot.querySelector('slot');

        // Remove any previously rendered items (but keep slot)
        list.querySelectorAll('.menu-item').forEach(item => item.remove());

        // Render programmatic items before slot
        this._items.forEach((item, index) => {
            const li = document.createElement('li');
            li.className = 'menu-item';
            li.setAttribute('role', 'menuitem');
            li.dataset.index = index;

            if (item.separator) {
                li.classList.add('separator');
            } else {
                if (item.danger) li.classList.add('danger');
                if (item.disabled) li.classList.add('disabled');

                if (item.icon) {
                    const icon = document.createElement('span');
                    icon.className = `icon lnr ${item.icon}`;
                    li.appendChild(icon);
                }

                const label = document.createElement('span');
                label.textContent = item.label || '';
                li.appendChild(label);

                if (item.action) {
                    li.dataset.action = item.action;
                }

                li.addEventListener('click', (e) => this._handleItemClick(e, item));
            }

            list.insertBefore(li, slot);
        });
    }

    _setupSlottedItems() {
        const slottedItems = this.querySelectorAll('lib-menu-item');
        slottedItems.forEach(item => {
            item.addEventListener('click', (e) => {
                if (item.hasAttribute('separator') || item.hasAttribute('disabled')) return;

                const action = item.getAttribute('action');
                this.dispatchEvent(new CustomEvent('menu-select', {
                    bubbles: true,
                    detail: {
                        action,
                        item: item,
                        originalEvent: e
                    }
                }));
                this.hide();
            });
        });
    }

    _setupTrigger() {
        // Clean up old trigger
        if (this._triggerElement) {
            this._triggerElement.removeEventListener('click', this._boundTriggerClick);
        }

        const triggerSelector = this.getAttribute('trigger');
        if (!triggerSelector) return;

        this._triggerElement = document.querySelector(triggerSelector);
        if (!this._triggerElement) return;

        this._boundTriggerClick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggle(null, null, this._triggerElement);
        };

        this._triggerElement.addEventListener('click', this._boundTriggerClick);
    }

    _positionAt(x, y) {
        const menu = this.shadowRoot.querySelector('.menu');
        const rect = menu.getBoundingClientRect();

        // Adjust for viewport edges
        let finalX = x;
        let finalY = y;

        if (x + rect.width > window.innerWidth) {
            finalX = window.innerWidth - rect.width - 10;
        }
        if (y + rect.height > window.innerHeight) {
            finalY = window.innerHeight - rect.height - 10;
        }

        menu.style.left = `${Math.max(10, finalX)}px`;
        menu.style.top = `${Math.max(10, finalY)}px`;
    }

    _positionRelativeTo(element) {
        const menu = this.shadowRoot.querySelector('.menu');
        const anchorRect = element.getBoundingClientRect();
        const menuRect = menu.getBoundingClientRect();

        let x = anchorRect.left;
        let y = anchorRect.bottom + 2;

        // Check if menu would go off right edge
        if (x + menuRect.width > window.innerWidth) {
            x = anchorRect.right - menuRect.width;
        }

        // Check if menu would go off bottom edge
        if (y + menuRect.height > window.innerHeight) {
            y = anchorRect.top - menuRect.height - 2;
        }

        menu.style.left = `${Math.max(10, x)}px`;
        menu.style.top = `${Math.max(10, y)}px`;
    }

    _handleItemClick(e, item) {
        if (item.separator || item.disabled) return;

        this.dispatchEvent(new CustomEvent('menu-select', {
            bubbles: true,
            detail: {
                action: item.action,
                item: item,
                originalEvent: e
            }
        }));

        this.hide();
    }

    _handleOutsideClick(e) {
        const path = e.composedPath();
        if (!path.includes(this) && !path.includes(this._triggerElement)) {
            this.hide();
        }
    }

    _handleKeydown(e) {
        if (!this._isOpen) return;

        const items = this._getNavigableItems();

        switch (e.key) {
            case 'Escape':
                e.preventDefault();
                this.hide();
                this._triggerElement?.focus();
                break;

            case 'ArrowDown':
                e.preventDefault();
                this._focusedIndex = Math.min(this._focusedIndex + 1, items.length - 1);
                this._updateFocus(items);
                break;

            case 'ArrowUp':
                e.preventDefault();
                this._focusedIndex = Math.max(this._focusedIndex - 1, 0);
                this._updateFocus(items);
                break;

            case 'Enter':
            case ' ':
                e.preventDefault();
                if (this._focusedIndex >= 0 && items[this._focusedIndex]) {
                    items[this._focusedIndex].click();
                }
                break;

            case 'Tab':
                this.hide();
                break;
        }
    }

    _getNavigableItems() {
        const programmaticItems = Array.from(
            this.shadowRoot.querySelectorAll('.menu-item:not(.separator):not(.disabled)')
        );
        const slottedItems = Array.from(
            this.querySelectorAll('lib-menu-item:not([separator]):not([disabled])')
        );
        return [...programmaticItems, ...slottedItems];
    }

    _updateFocus(items) {
        // Remove focus from all
        this.shadowRoot.querySelectorAll('.menu-item').forEach(item => {
            item.classList.remove('focused');
        });
        this.querySelectorAll('lib-menu-item').forEach(item => {
            item.classList.remove('focused');
        });

        // Add focus to current
        if (this._focusedIndex >= 0 && items[this._focusedIndex]) {
            items[this._focusedIndex].classList.add('focused');
        }
    }

    _cleanup() {
        document.removeEventListener('click', this._boundHandleOutsideClick, true);
        document.removeEventListener('keydown', this._boundHandleKeydown);

        if (this._triggerElement) {
            this._triggerElement.removeEventListener('click', this._boundTriggerClick);
        }
    }
}

/**
 * lib-menu-item - Menu Item Element
 *
 * Used as child of lib-menu for declarative menu items.
 *
 * Attributes:
 *   - icon: Icon class (e.g., "lnr-pencil")
 *   - action: Action identifier for menu-select event
 *   - danger: Makes item red (delete actions)
 *   - disabled: Disables the item
 *   - separator: Creates a separator line
 */
class LibMenuItem extends HTMLElement {
    static get observedAttributes() {
        return ['icon', 'action', 'danger', 'disabled', 'separator'];
    }

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
    }

    connectedCallback() {
        this._render();
    }

    attributeChangedCallback() {
        this._render();
    }

    _render() {
        const isSeparator = this.hasAttribute('separator');
        const icon = this.getAttribute('icon');
        const isDanger = this.hasAttribute('danger');
        const isDisabled = this.hasAttribute('disabled');

        if (isSeparator) {
            this.shadowRoot.innerHTML = `
                <style>
                    :host {
                        display: block;
                        padding: 0 !important;
                        margin: 5px 10px;
                        border-top: 1px solid var(--menu-border, var(--border-light, #eee));
                        cursor: default !important;
                        height: 0;
                    }
                </style>
            `;
            return;
        }

        // Get icon styles for the specific icon used
        let iconStyles = '';
        if (icon) {
            const iconName = icon.replace(/^lnr-/, '');
            iconStyles = (typeof CMA !== 'undefined' && typeof CMA.getIconStylesFor === 'function')
                ? CMA.getIconStylesFor([iconName])
                : `@font-face {
                        font-family: 'Linearicons';
                        src: url('../library/fonts/Linearicons/Font/Linearicons.woff2') format('woff2'),
                             url('../library/fonts/Linearicons/Font/Linearicons.woff') format('woff');
                    }`;
        }

        this.shadowRoot.innerHTML = `
            <style>
                ${iconStyles}

                :host {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 8px 15px;
                    cursor: pointer;
                    transition: background-color 0.15s;
                    white-space: nowrap;
                    font-size: var(--font-size);
                    color: var(--menu-text, var(--text-primary, #333));
                }

                :host(:hover),
                :host(.focused) {
                    background: var(--menu-hover-bg, var(--bg-hover, #f5f5f5));
                }

                :host([danger]) {
                    color: var(--menu-danger, var(--color-error, #dc3545));
                }

                :host([danger]:hover),
                :host([danger].focused) {
                    background: var(--color-error-bg, #fff5f5);
                }

                :host([disabled]) {
                    opacity: 0.5;
                    cursor: not-allowed;
                    pointer-events: none;
                }

                .icon {
                    font-size: var(--font-size-md);
                    width: 16px;
                    text-align: center;
                }
            </style>
            ${icon ? `<span class="icon lnr ${icon}"></span>` : ''}
            <slot></slot>
        `;
    }
}

// Register custom elements
customElements.define('lib-menu', LibMenu);
customElements.define('lib-menu-item', LibMenuItem);

} // end guard

// Global helper for programmatic usage
window.libMenu = {
    /**
     * Create and show a context menu at position
     * @param {Array} items - Menu items
     * @param {number} x - X position
     * @param {number} y - Y position
     * @param {Function} onSelect - Callback when item selected
     * @returns {LibMenu} The menu element
     */
    show: function(items, x, y, onSelect) {
        // Remove any existing temporary menus
        document.querySelectorAll('lib-menu[data-temp]').forEach(m => m.remove());

        const menu = document.createElement('lib-menu');
        menu.setAttribute('data-temp', 'true');
        menu.items = items;

        if (onSelect) {
            menu.addEventListener('menu-select', (e) => {
                onSelect(e.detail.action, e.detail.item, e.detail.originalEvent);
            });
        }

        menu.addEventListener('menu-close', () => {
            setTimeout(() => menu.remove(), 100);
        });

        document.body.appendChild(menu);
        menu.show(x, y);

        return menu;
    },

    /**
     * Hide all open menus
     */
    hideAll: function() {
        document.querySelectorAll('lib-menu').forEach(menu => menu.hide());
    }
};
