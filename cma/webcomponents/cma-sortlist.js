/**
 * cma-sortlist Web Component
 *
 * A drag-and-drop sortable list component.
 *
 * Usage:
 *   <cma-sortlist name="items" value="3,1,2">
 *     <option value="1">Item 1</option>
 *     <option value="2">Item 2</option>
 *     <option value="3">Item 3</option>
 *   </cma-sortlist>
 *
 * Attributes:
 *   - name: Form field name
 *   - value: Comma-separated values in sort order
 *   - disabled: Disable the component
 *   - max-height: Maximum height in px (default: 400)
 *
 * Events:
 *   - change: Fired when order changes
 *   - sort: Fired during drag operation
 */

// Guard against double registration
if (!customElements.get('cma-sortlist')) {

class CmaSortlist extends HTMLElement {
    static get observedAttributes() {
        return ['value', 'disabled'];
    }

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._items = [];
        this._draggedItem = null;
        this._draggedElement = null;
        this._placeholder = null;
    }

    connectedCallback() {
        // Adopt shared styles if available
        if (typeof LibSharedStyles !== 'undefined' && LibSharedStyles.isSupported()) {
            LibSharedStyles.adopt(this.shadowRoot, 'base', 'button');
        }

        this._collectItems();
        this.render();
        this._setupEventListeners();

        // Set initial order from value attribute
        const initialValue = this.getAttribute('value');
        if (initialValue) {
            this._sortByValue(initialValue);
        }
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;

        switch (name) {
            case 'value':
                this._sortByValue(newValue);
                break;
            case 'disabled':
                this._updateDisabledState();
                break;
        }
    }

    get value() {
        return this._items.map(item => item.value);
    }

    set value(val) {
        if (Array.isArray(val)) {
            this._sortByValue(val.join(','));
        } else if (typeof val === 'string') {
            this._sortByValue(val);
        }
    }

    get items() {
        return [...this._items];
    }

    _collectItems() {
        this._items = [];
        const options = this.querySelectorAll('option');
        options.forEach(opt => {
            this._items.push({
                value: opt.value,
                label: opt.textContent,
                disabled: opt.disabled
            });
        });
    }

    render() {
        const maxHeight = this.getAttribute('max-height') || '400';
        const isDisabled = this.hasAttribute('disabled');
        const name = this.getAttribute('name') || '';

        this.shadowRoot.innerHTML = `
            <style>
                :host {
                    display: block;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    font-size: var(--font-size-md);
                }

                * {
                    box-sizing: border-box;
                }

                .sortlist-container {
                    border: 1px solid var(--border-color, #ddd);
                    border-radius: 4px;
                    background-color: var(--bg-surface, #fff);
                    overflow: hidden;
                }

                .sortlist-container.disabled {
                    opacity: 0.6;
                    pointer-events: none;
                }

                .sortlist-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 8px 12px;
                    background-color: var(--bg-surface-alt, #f8f9fa);
                    border-bottom: 1px solid var(--border-light, #eee);
                    font-size: var(--font-size-sm);
                    color: var(--text-secondary, #666);
                }

                .sortlist-body {
                    max-height: ${maxHeight}px;
                    overflow-y: auto;
                    padding: 8px;
                }

                .sortlist-items {
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }

                .sortlist-item {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    padding: 10px 12px;
                    background-color: var(--bg-surface, #fff);
                    border: 1px solid var(--border-color, #e0e0e0);
                    border-radius: 6px;
                    cursor: grab;
                    transition: all 0.15s ease;
                    user-select: none;
                    color: var(--text-primary, #333);
                }

                .sortlist-item:hover {
                    border-color: var(--color-primary, #204496);
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
                }

                .sortlist-item.dragging {
                    opacity: 0.5;
                    cursor: grabbing;
                }

                .sortlist-item.disabled {
                    opacity: 0.5;
                    cursor: not-allowed;
                }

                .sortlist-item.drag-over {
                    border-color: var(--color-primary, #204496);
                    background-color: var(--color-primary-light, #f0f4ff);
                }

                .sortlist-handle {
                    display: flex;
                    flex-direction: column;
                    gap: 2px;
                    padding: 4px;
                    cursor: grab;
                }

                .sortlist-handle span {
                    width: 14px;
                    height: 2px;
                    background-color: var(--text-muted, #999);
                    border-radius: 1px;
                }

                .sortlist-item:hover .sortlist-handle span {
                    background-color: var(--color-primary, #204496);
                }

                .sortlist-number {
                    width: 24px;
                    height: 24px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background-color: var(--color-primary-light, #e3ecf6);
                    border-radius: 50%;
                    font-size: var(--font-size-sm);
                    font-weight: 600;
                    color: var(--color-primary-dark, #1a365d);
                    flex-shrink: 0;
                }

                .sortlist-label {
                    flex: 1;
                    line-height: 1.3;
                }

                .sortlist-actions {
                    display: flex;
                    gap: 4px;
                    opacity: 0;
                    transition: opacity 0.15s ease;
                }

                .sortlist-item:hover .sortlist-actions {
                    opacity: 1;
                }

                .sortlist-action {
                    width: 28px;
                    height: 28px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border: none;
                    background-color: var(--bg-surface-alt, #f0f0f0);
                    border-radius: 4px;
                    cursor: pointer;
                    color: var(--text-secondary, #666);
                    font-size: var(--font-size-md);
                    transition: all 0.1s ease;
                }

                .sortlist-action:hover {
                    background-color: var(--color-primary, #204496);
                    color: var(--text-inverse, #fff);
                }

                .sortlist-action:disabled {
                    opacity: 0.3;
                    cursor: not-allowed;
                }

                .sortlist-placeholder {
                    height: 46px;
                    border: 2px dashed var(--color-primary, #204496);
                    border-radius: 6px;
                    background-color: var(--color-primary-light, #f0f4ff);
                }

                .sortlist-empty {
                    padding: 30px;
                    text-align: center;
                    color: var(--text-muted, #999);
                }

                /* Touch-friendly styles */
                @media (pointer: coarse) {
                    .sortlist-item {
                        padding: 12px 14px;
                    }

                    .sortlist-actions {
                        opacity: 1;
                    }

                    .sortlist-action {
                        width: 36px;
                        height: 36px;
                    }
                }
            </style>
            <div class="sortlist-container ${isDisabled ? 'disabled' : ''}">
                <div class="sortlist-header">
                    <span>Sleep items om de volgorde te wijzigen</span>
                    <span>${this._items.length} items</span>
                </div>
                <div class="sortlist-body">
                    <div class="sortlist-items">
                        ${this._renderItems()}
                    </div>
                </div>
                <input type="hidden" name="${name}">
            </div>
        `;

        this._updateHiddenInput();
    }

    _renderItems() {
        if (this._items.length === 0) {
            return '<div class="sortlist-empty">Geen items</div>';
        }

        return this._items.map((item, index) => `
            <div class="sortlist-item ${item.disabled ? 'disabled' : ''}"
                 data-value="${this._escapeHtml(item.value)}"
                 draggable="${item.disabled ? 'false' : 'true'}">
                <div class="sortlist-handle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <span class="sortlist-number">${index + 1}</span>
                <span class="sortlist-label">${this._escapeHtml(item.label)}</span>
                <div class="sortlist-actions">
                    <button type="button" class="sortlist-action" data-action="up" title="Omhoog" ${index === 0 ? 'disabled' : ''}>
                        &#9650;
                    </button>
                    <button type="button" class="sortlist-action" data-action="down" title="Omlaag" ${index === this._items.length - 1 ? 'disabled' : ''}>
                        &#9660;
                    </button>
                </div>
            </div>
        `).join('');
    }

    _setupEventListeners() {
        const container = this.shadowRoot.querySelector('.sortlist-items');

        // Drag start
        container.addEventListener('dragstart', (e) => {
            const item = e.target.closest('.sortlist-item');
            if (!item || item.classList.contains('disabled')) {
                e.preventDefault();
                return;
            }

            this._draggedElement = item;
            this._draggedItem = this._items.find(i => i.value === item.dataset.value);

            item.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', item.dataset.value);

            // Create placeholder
            this._placeholder = document.createElement('div');
            this._placeholder.className = 'sortlist-placeholder';
        });

        // Drag over
        container.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';

            const item = e.target.closest('.sortlist-item');
            if (!item || item === this._draggedElement) return;

            // Remove existing placeholder
            if (this._placeholder && this._placeholder.parentNode) {
                this._placeholder.remove();
            }

            // Calculate where to insert placeholder
            const rect = item.getBoundingClientRect();
            const midY = rect.top + rect.height / 2;

            if (e.clientY < midY) {
                item.parentNode.insertBefore(this._placeholder, item);
            } else {
                item.parentNode.insertBefore(this._placeholder, item.nextSibling);
            }
        });

        // Drag end
        container.addEventListener('dragend', (e) => {
            if (this._draggedElement) {
                this._draggedElement.classList.remove('dragging');
            }

            if (this._placeholder && this._placeholder.parentNode) {
                // Get new order
                const newOrder = [];
                const items = container.querySelectorAll('.sortlist-item, .sortlist-placeholder');

                items.forEach(el => {
                    if (el === this._placeholder) {
                        newOrder.push(this._draggedItem);
                    } else if (el.dataset.value !== this._draggedItem.value) {
                        const item = this._items.find(i => i.value === el.dataset.value);
                        if (item) newOrder.push(item);
                    }
                });

                this._placeholder.remove();
                this._items = newOrder;
                this._rerender();
                this._dispatchChange();
            }

            this._draggedElement = null;
            this._draggedItem = null;
            this._placeholder = null;
        });

        // Touch support
        let touchStartY = 0;
        let touchItem = null;
        let touchClone = null;

        container.addEventListener('touchstart', (e) => {
            const item = e.target.closest('.sortlist-item');
            if (!item || item.classList.contains('disabled')) return;

            const handle = e.target.closest('.sortlist-handle');
            if (!handle) return; // Only drag from handle on touch

            touchStartY = e.touches[0].clientY;
            touchItem = item;

            // Create visual clone
            const rect = item.getBoundingClientRect();
            touchClone = item.cloneNode(true);
            touchClone.style.position = 'fixed';
            touchClone.style.left = rect.left + 'px';
            touchClone.style.top = rect.top + 'px';
            touchClone.style.width = rect.width + 'px';
            touchClone.style.zIndex = '10000';
            touchClone.style.opacity = '0.9';
            touchClone.style.boxShadow = '0 4px 12px rgba(0,0,0,0.2)';
            document.body.appendChild(touchClone);

            item.classList.add('dragging');
            this._draggedItem = this._items.find(i => i.value === item.dataset.value);

            this._placeholder = document.createElement('div');
            this._placeholder.className = 'sortlist-placeholder';
        }, { passive: true });

        container.addEventListener('touchmove', (e) => {
            if (!touchItem || !touchClone) return;

            const touch = e.touches[0];
            touchClone.style.top = (touch.clientY - 20) + 'px';

            // Find item under touch
            touchClone.style.display = 'none';
            const el = document.elementFromPoint(touch.clientX, touch.clientY);
            touchClone.style.display = '';

            const targetItem = el?.closest('.sortlist-item');
            if (targetItem && targetItem !== touchItem) {
                if (this._placeholder.parentNode) {
                    this._placeholder.remove();
                }

                const rect = targetItem.getBoundingClientRect();
                const midY = rect.top + rect.height / 2;

                if (touch.clientY < midY) {
                    targetItem.parentNode.insertBefore(this._placeholder, targetItem);
                } else {
                    targetItem.parentNode.insertBefore(this._placeholder, targetItem.nextSibling);
                }
            }
        }, { passive: true });

        container.addEventListener('touchend', (e) => {
            if (!touchItem) return;

            touchItem.classList.remove('dragging');
            if (touchClone) {
                touchClone.remove();
                touchClone = null;
            }

            if (this._placeholder && this._placeholder.parentNode) {
                const newOrder = [];
                const items = container.querySelectorAll('.sortlist-item, .sortlist-placeholder');

                items.forEach(el => {
                    if (el === this._placeholder) {
                        newOrder.push(this._draggedItem);
                    } else if (el.dataset.value !== this._draggedItem.value) {
                        const item = this._items.find(i => i.value === el.dataset.value);
                        if (item) newOrder.push(item);
                    }
                });

                this._placeholder.remove();
                this._items = newOrder;
                this._rerender();
                this._dispatchChange();
            }

            touchItem = null;
            this._draggedItem = null;
            this._placeholder = null;
        });

        // Up/down button clicks
        container.addEventListener('click', (e) => {
            const action = e.target.closest('.sortlist-action');
            if (!action || action.disabled) return;

            const item = action.closest('.sortlist-item');
            const value = item.dataset.value;
            const index = this._items.findIndex(i => i.value === value);

            if (action.dataset.action === 'up' && index > 0) {
                [this._items[index], this._items[index - 1]] = [this._items[index - 1], this._items[index]];
                this._rerender();
                this._dispatchChange();
            } else if (action.dataset.action === 'down' && index < this._items.length - 1) {
                [this._items[index], this._items[index + 1]] = [this._items[index + 1], this._items[index]];
                this._rerender();
                this._dispatchChange();
            }
        });
    }

    _rerender() {
        const itemsContainer = this.shadowRoot.querySelector('.sortlist-items');
        if (itemsContainer) {
            itemsContainer.innerHTML = this._renderItems();
        }
        this._updateHiddenInput();
    }

    _sortByValue(valueStr) {
        if (!valueStr) return;

        const orderedValues = valueStr.split(',').map(v => v.trim()).filter(v => v);
        const orderedItems = [];

        // First add items in the specified order
        for (const value of orderedValues) {
            const item = this._items.find(i => i.value === value);
            if (item) {
                orderedItems.push(item);
            }
        }

        // Then add any remaining items
        for (const item of this._items) {
            if (!orderedItems.includes(item)) {
                orderedItems.push(item);
            }
        }

        this._items = orderedItems;
        this._rerender();
    }

    _updateHiddenInput() {
        const input = this.shadowRoot.querySelector('input[type="hidden"]');
        if (input) {
            input.value = this._items.map(i => i.value).join(',');
        }
    }

    _updateDisabledState() {
        const container = this.shadowRoot.querySelector('.sortlist-container');
        if (container) {
            container.classList.toggle('disabled', this.hasAttribute('disabled'));
        }
    }

    _dispatchChange() {
        this.dispatchEvent(new CustomEvent('change', {
            detail: {
                value: this.value,
                items: this.items
            },
            bubbles: true
        }));
    }

    /**
     * Add item
     */
    addItem(value, label, position = -1) {
        const item = { value: String(value), label, disabled: false };

        if (position < 0 || position >= this._items.length) {
            this._items.push(item);
        } else {
            this._items.splice(position, 0, item);
        }

        this._rerender();
    }

    /**
     * Remove item
     */
    removeItem(value) {
        this._items = this._items.filter(i => i.value !== String(value));
        this._rerender();
    }

    /**
     * Move item to position
     */
    moveItem(value, newPosition) {
        const index = this._items.findIndex(i => i.value === String(value));
        if (index === -1) return;

        const [item] = this._items.splice(index, 1);
        const pos = Math.max(0, Math.min(newPosition, this._items.length));
        this._items.splice(pos, 0, item);

        this._rerender();
        this._dispatchChange();
    }

    /**
     * Set items from array
     */
    setItems(items) {
        this._items = items.map(i => ({
            value: String(i.value),
            label: i.label || String(i.value),
            disabled: i.disabled || false
        }));

        this._rerender();
    }

    _escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Register the component
customElements.define('cma-sortlist', CmaSortlist);

} // End guard against double registration
