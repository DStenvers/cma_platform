/**
 * CMA Fold Web Component
 *
 * A draggable divider for resizing adjacent panels.
 * Supports vertical (left/right) and horizontal (top/bottom) orientations.
 *
 * Usage:
 * <cma-fold
 *     orientation="vertical"
 *     target="#leftlist"
 *     min-size="150"
 *     max-size="600"
 *     storage-key="tools_fold">
 * </cma-fold>
 *
 * Attributes:
 *   - orientation: 'vertical' (resize width) or 'horizontal' (resize height)
 *   - target: CSS selector for the element to resize
 *   - min-size: Minimum width/height in pixels (default: 150)
 *   - max-size: Maximum width/height in pixels (default: 600)
 *   - storage-key: localStorage key for saving state
 *   - collapsed-size: Size when collapsed (default: 0)
 *   - reverse: Invert drag direction (use when target is AFTER the fold)
 *
 * Events:
 *   - fold-resize: Fired during resize with { size, collapsed }
 *   - fold-collapse: Fired when collapsed/expanded with { collapsed }
 */
// Guard against double registration
if (!customElements.get('cma-fold')) {

class CmaFold extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._isDragging = false;
        this._startPos = 0;
        this._startSize = 0;
        this._target = null;
        this._collapsed = false;
        this._savedSize = null;

        // Bound handlers for cleanup
        this._onMouseMove = this._onMouseMove.bind(this);
        this._onMouseUp = this._onMouseUp.bind(this);
        this._onTouchMove = this._onTouchMove.bind(this);
        this._onTouchEnd = this._onTouchEnd.bind(this);
    }

    static get observedAttributes() {
        return ['orientation', 'target', 'min-size', 'max-size', 'storage-key', 'collapsed-size', 'reverse'];
    }

    get orientation() {
        return this.getAttribute('orientation') || 'vertical';
    }

    get minSize() {
        return parseInt(this.getAttribute('min-size') || '150', 10);
    }

    get maxSize() {
        return parseInt(this.getAttribute('max-size') || '600', 10);
    }

    get collapsedSize() {
        return parseInt(this.getAttribute('collapsed-size') || '0', 10);
    }

    get storageKey() {
        return this.getAttribute('storage-key') || '';
    }

    get isVertical() {
        return this.orientation === 'vertical';
    }

    get reverse() {
        return this.hasAttribute('reverse');
    }

    // Expose internal target for testing
    get _target() {
        return this.__target;
    }

    set _target(val) {
        this.__target = val;
    }

    connectedCallback() {
        // Adopt shared styles if available
        if (typeof LibSharedStyles !== 'undefined' && LibSharedStyles.isSupported()) {
            LibSharedStyles.adopt(this.shadowRoot, 'base');
        }

        this._render();
        this._findTarget();
        this._loadState();
        this._setupEventListeners();
    }

    disconnectedCallback() {
        document.removeEventListener('mousemove', this._onMouseMove);
        document.removeEventListener('mouseup', this._onMouseUp);

        // Clean up if disconnected during drag
        if (this._isDragging) {
            document.body.classList.remove('resizing', 'resizing-h');
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            document.body.style.webkitUserSelect = '';
        }
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;

        if (name === 'target') {
            this._findTarget();
        } else if (name === 'orientation') {
            this._render();
        }
    }

    _render() {
        const isVert = this.isVertical;

        this.shadowRoot.innerHTML = `
            <style>
                :host {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                    box-sizing: border-box;
                    background: linear-gradient(
                        ${isVert ? 'to right' : 'to bottom'},
                        var(--bg-surface-alt, #e8e8e8),
                        var(--bg-surface, #f4f4f4),
                        var(--bg-surface-alt, #e8e8e8)
                    );
                    transition: background 0.15s ease;
                }

                :host([orientation="vertical"]) {
                    width: 8px;
                    min-width: 8px;
                    max-width: 8px;
                    height: 100%;
                    cursor: col-resize;
                    border-left: 1px solid var(--border-color, #ddd);
                    border-right: 1px solid var(--border-color, #ddd);
                }

                :host([orientation="horizontal"]) {
                    height: 8px;
                    min-height: 8px;
                    max-height: 8px;
                    width: 100%;
                    cursor: row-resize;
                    border-top: 1px solid var(--border-color, #ddd);
                    border-bottom: 1px solid var(--border-color, #ddd);
                }

                :host(:hover) {
                    background: linear-gradient(
                        ${isVert ? 'to right' : 'to bottom'},
                        var(--bg-hover),
                        var(--bg-hover),
                        var(--bg-hover)
                    );
                    border-color: var(--border-hover);
                }

                :host(.dragging) {
                    background: var(--bg-hover, #d0e8f8);
                    border-color: var(--color-info, #077ab2);
                }

                :host {
                    user-select: none;
                    -webkit-user-select: none;
                }

                .grip-container {
                    width: 100%;
                    height: 100%;
                    display: flex;
                    flex-direction: ${isVert ? 'column' : 'row'};
                    align-items: center;
                    justify-content: center;
                    gap: 4px;
                }

                .grip {
                    display: flex;
                    flex-direction: ${isVert ? 'column' : 'row'};
                    gap: 2px;
                    opacity: 0.4;
                    transition: opacity 0.15s ease;
                    pointer-events: none;
                }

                :host(:hover) .grip,
                :host(.dragging) .grip {
                    opacity: 0.8;
                }

                .grip span {
                    width: ${isVert ? '2px' : '4px'};
                    height: ${isVert ? '4px' : '2px'};
                    background: var(--color-primary, #204496);
                    border-radius: 1px;
                }

                .fold-arrow {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 20px;
                    height: 20px;
                    font-family: 'Linearicons';
                    font-style: normal;
                    font-weight: normal;
                    font-size: 10px;
                    line-height: 1;
                    color: var(--text-muted, #999);
                    border: 1px solid var(--border-color, #ddd);
                    border-radius: 50%;
                    background: var(--bg-surface, #f4f4f4);
                    cursor: pointer;
                    opacity: 0;
                    transition: opacity 0.15s ease, color 0.15s ease, background 0.15s ease, border-color 0.15s ease;
                    pointer-events: auto;
                }

                :host(:hover) .fold-arrow {
                    opacity: 0.8;
                }

                .fold-arrow:hover {
                    opacity: 1;
                    color: #fff;
                    background: var(--color-primary, #204496);
                    border-color: var(--color-primary, #204496);
                }

                /* Touch devices: always show arrows since hover is unavailable */
                @media (hover: none) {
                    .fold-arrow {
                        opacity: 0.7;
                    }
                }

                .fold-arrow[data-action="collapse"]::before {
                    content: "${isVert ? '\\e93b' : '\\e939'}"; /* ${isVert ? 'chevron-left' : 'chevron-up'} */
                }

                .fold-arrow[data-action="expand"]::before {
                    content: "${isVert ? '\\e93c' : '\\e93a'}"; /* ${isVert ? 'chevron-right' : 'chevron-down'} */
                }
            </style>
            <div class="grip-container">
                <span class="fold-arrow" data-action="collapse" title="Minimaliseren"></span>
                <div class="grip">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <span class="fold-arrow" data-action="expand" title="Maximaliseren"></span>
            </div>
        `;
    }

    _findTarget() {
        const selector = this.getAttribute('target');
        if (selector) {
            this._target = document.querySelector(selector);
            // If not found, try again after a short delay (for AJAX-loaded content)
            if (!this._target) {
                setTimeout(() => {
                    this._target = document.querySelector(selector);
                    if (this._target) {
                        this._loadState();
                    }
                }, 100);
            }
        } else {
            // Default: previous sibling element
            this._target = this.previousElementSibling;
        }
    }

    _setupEventListeners() {
        // Get the drag area element
        const dragArea = this.shadowRoot.querySelector('.grip-container');
        if (!dragArea) {
            // Fallback to host element
            this.addEventListener('mousedown', (e) => {
                e.preventDefault();
                this._startDrag(e);
            });
            this.addEventListener('dblclick', (e) => {
                e.preventDefault();
                this._toggleCollapse();
            });
            return;
        }

        // Mouse down on the drag area (skip if clicking arrows)
        dragArea.addEventListener('mousedown', (e) => {
            if (e.target.closest('[data-action]')) return;
            e.preventDefault();
            this._startDrag(e);
        });

        // Touch support for drag
        dragArea.addEventListener('touchstart', (e) => {
            if (e.target.closest('[data-action]')) return;
            if (e.touches.length !== 1) return;
            e.preventDefault();
            this._startDrag(e.touches[0], true);
        }, { passive: false });

        // Double-click to collapse/expand
        dragArea.addEventListener('dblclick', (e) => {
            e.preventDefault();
            this._toggleCollapse();
        });

        // Collapse/expand arrow buttons
        dragArea.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (!this._target) return;
                const action = btn.dataset.action;
                const isCollapse = this.reverse ? action === 'expand' : action === 'collapse';
                const size = isCollapse ? this.minSize : this.maxSize;
                this._target.style.transition = (this.isVertical ? 'width' : 'height') + ' 0.2s ease, flex 0.2s ease';
                if (this.isVertical) {
                    this._target.style.width = size + 'px';
                    this._target.style.flex = '0 0 ' + size + 'px';
                } else {
                    this._target.style.height = size + 'px';
                    this._target.style.flex = '0 0 ' + size + 'px';
                }
                this._collapsed = isCollapse;
                this._savedSize = null;
                this._saveState();
                setTimeout(() => { if (this._target) this._target.style.transition = ''; }, 250);
                this.dispatchEvent(new CustomEvent('fold-resize', {
                    detail: { size: size, collapsed: this._collapsed },
                    bubbles: true
                }));
            });
        });
    }

    _startDrag(e, isTouch) {
        if (!this._target) return;

        this._isDragging = true;
        this._isTouch = !!isTouch;
        this._startPos = this.isVertical ? e.clientX : e.clientY;
        this._startSize = this.isVertical ? this._target.offsetWidth : this._target.offsetHeight;

        this.classList.add('dragging');
        document.body.classList.add(this.isVertical ? 'resizing' : 'resizing-h');
        document.body.style.cursor = this.isVertical ? 'col-resize' : 'row-resize';

        // Prevent text selection during drag
        document.body.style.userSelect = 'none';
        document.body.style.webkitUserSelect = 'none';

        // Disable transitions during drag
        this._target.style.transition = 'none';

        if (isTouch) {
            document.addEventListener('touchmove', this._onTouchMove, { passive: false });
            document.addEventListener('touchend', this._onTouchEnd);
        } else {
            // Use pointer capture for reliable tracking even when mouse moves outside element
            if (e.target && e.target.setPointerCapture && e.pointerId !== undefined) {
                e.target.setPointerCapture(e.pointerId);
                this._capturedElement = e.target;
                this._capturedPointerId = e.pointerId;
            }

            document.addEventListener('mousemove', this._onMouseMove);
            document.addEventListener('mouseup', this._onMouseUp);
        }
    }

    _onMouseMove(e) {
        if (!this._isDragging || !this._target) return;

        const currentPos = this.isVertical ? e.clientX : e.clientY;
        const delta = currentPos - this._startPos;
        // When reverse is true (target is after the fold), invert the delta
        let newSize = this.reverse ? this._startSize - delta : this._startSize + delta;

        // Clamp to min/max
        newSize = Math.max(this.minSize, Math.min(this.maxSize, newSize));

        // Apply size - use flex shorthand to fully override CSS flex property
        if (this.isVertical) {
            this._target.style.width = newSize + 'px';
            this._target.style.flex = '0 0 ' + newSize + 'px';
        } else {
            this._target.style.height = newSize + 'px';
            this._target.style.flex = '0 0 ' + newSize + 'px';
        }

        // Update collapsed state
        this._collapsed = false;

        this.dispatchEvent(new CustomEvent('fold-resize', {
            detail: { size: newSize, collapsed: false },
            bubbles: true
        }));
    }

    _onMouseUp(e) {
        if (!this._isDragging) return;

        this._isDragging = false;
        this.classList.remove('dragging');
        document.body.classList.remove('resizing', 'resizing-h');
        document.body.style.cursor = '';

        // Restore text selection
        document.body.style.userSelect = '';
        document.body.style.webkitUserSelect = '';

        // Release pointer capture
        if (this._capturedElement && this._capturedPointerId !== undefined) {
            try {
                this._capturedElement.releasePointerCapture(this._capturedPointerId);
            } catch (err) {
                // Ignore - pointer may have been released already
            }
            this._capturedElement = null;
            this._capturedPointerId = undefined;
        }

        // Re-enable transitions
        if (this._target) {
            this._target.style.transition = '';
        }

        document.removeEventListener('mousemove', this._onMouseMove);
        document.removeEventListener('mouseup', this._onMouseUp);

        // Save state
        this._saveState();
    }

    _onTouchMove(e) {
        if (!this._isDragging || !this._target || !e.touches.length) return;
        e.preventDefault();
        // Reuse mouse move logic with touch coordinates
        this._onMouseMove(e.touches[0]);
    }

    _onTouchEnd() {
        if (!this._isDragging) return;

        this._isDragging = false;
        this._isTouch = false;
        this.classList.remove('dragging');
        document.body.classList.remove('resizing', 'resizing-h');
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
        document.body.style.webkitUserSelect = '';

        if (this._target) {
            this._target.style.transition = '';
        }

        document.removeEventListener('touchmove', this._onTouchMove);
        document.removeEventListener('touchend', this._onTouchEnd);

        this._saveState();
    }

    _toggleCollapse() {
        if (!this._target) return;

        if (this._collapsed) {
            // Expand
            const size = this._savedSize || (this.isVertical ? 280 : 200);
            if (this.isVertical) {
                this._target.style.width = size + 'px';
                this._target.style.flex = '0 0 ' + size + 'px';
            } else {
                this._target.style.height = size + 'px';
                this._target.style.flex = '0 0 ' + size + 'px';
            }
            this._collapsed = false;
        } else {
            // Save current size and collapse
            this._savedSize = this.isVertical ? this._target.offsetWidth : this._target.offsetHeight;
            if (this.isVertical) {
                this._target.style.width = this.collapsedSize + 'px';
                this._target.style.flex = '0 0 ' + this.collapsedSize + 'px';
            } else {
                this._target.style.height = this.collapsedSize + 'px';
                this._target.style.flex = '0 0 ' + this.collapsedSize + 'px';
            }
            this._collapsed = true;
        }

        this._saveState();

        this.dispatchEvent(new CustomEvent('fold-collapse', {
            detail: { collapsed: this._collapsed },
            bubbles: true
        }));
    }

    _saveState() {
        if (!this.storageKey || !this._target) return;

        try {
            const state = {
                size: this.isVertical ? this._target.offsetWidth : this._target.offsetHeight,
                collapsed: this._collapsed,
                savedSize: this._savedSize
            };
            localStorage.setItem('cma_fold_' + this.storageKey, JSON.stringify(state));
        } catch (e) {
            // Ignore localStorage errors
        }
    }

    _loadState() {
        if (!this.storageKey || !this._target) return;

        try {
            const stored = localStorage.getItem('cma_fold_' + this.storageKey);
            if (stored) {
                const state = JSON.parse(stored);
                this._collapsed = state.collapsed || false;
                this._savedSize = state.savedSize || null;

                if (this._collapsed) {
                    if (this.isVertical) {
                        this._target.style.width = this.collapsedSize + 'px';
                        this._target.style.flex = '0 0 ' + this.collapsedSize + 'px';
                    } else {
                        this._target.style.height = this.collapsedSize + 'px';
                        this._target.style.flex = '0 0 ' + this.collapsedSize + 'px';
                    }
                } else if (state.size) {
                    if (this.isVertical) {
                        this._target.style.width = state.size + 'px';
                        this._target.style.flex = '0 0 ' + state.size + 'px';
                    } else {
                        this._target.style.height = state.size + 'px';
                        this._target.style.flex = '0 0 ' + state.size + 'px';
                    }
                }
            }
        } catch (e) {
            // Ignore localStorage errors
        }
    }

    // Public API
    collapse() {
        if (!this._collapsed) {
            this._toggleCollapse();
        }
    }

    expand() {
        if (this._collapsed) {
            this._toggleCollapse();
        }
    }

    toggle() {
        this._toggleCollapse();
    }

    setSize(size) {
        if (!this._target) return;

        const clamped = Math.max(this.minSize, Math.min(this.maxSize, size));
        if (this.isVertical) {
            this._target.style.width = clamped + 'px';
        } else {
            this._target.style.height = clamped + 'px';
        }
        this._collapsed = false;
        this._saveState();
    }

    /**
     * Validate CSS setup for the fold to work correctly.
     * Checks parent container and target element CSS properties.
     *
     * @returns {Object} Validation result with:
     *   - valid: boolean - true if CSS is correct
     *   - errors: string[] - critical errors that prevent functioning
     *   - warnings: string[] - issues that may cause problems
     *   - suggestions: string[] - CSS fixes to apply
     */
    validateSetup() {
        const result = {
            valid: true,
            errors: [],
            warnings: [],
            suggestions: []
        };

        // Check if target exists
        if (!this._target) {
            result.valid = false;
            result.errors.push('No target element found. Use target="CSS_SELECTOR" or place fold after target element.');
            result.suggestions.push('Add target="#your-panel-id" attribute to cma-fold');
            return result;
        }

        // Get computed styles
        const parent = this.parentElement;
        if (!parent) {
            result.valid = false;
            result.errors.push('cma-fold has no parent element');
            return result;
        }

        const parentStyle = window.getComputedStyle(parent);
        const targetStyle = window.getComputedStyle(this._target);
        const foldStyle = window.getComputedStyle(this);
        const isVertical = this.isVertical;

        // Check 1: Parent must use flexbox
        if (parentStyle.display !== 'flex' && parentStyle.display !== 'inline-flex') {
            result.valid = false;
            result.errors.push(`Parent container must use display: flex (found: display: ${parentStyle.display})`);
            result.suggestions.push(`Add to parent: display: flex; flex-direction: ${isVertical ? 'row' : 'column'};`);
        }

        // Check 2: Parent flex-direction must match orientation
        const flexDir = parentStyle.flexDirection;
        if (isVertical && flexDir !== 'row' && flexDir !== 'row-reverse') {
            result.valid = false;
            result.errors.push(`For vertical orientation, parent needs flex-direction: row (found: ${flexDir})`);
            result.suggestions.push('Add to parent: flex-direction: row;');
        }
        if (!isVertical && flexDir !== 'column' && flexDir !== 'column-reverse') {
            result.valid = false;
            result.errors.push(`For horizontal orientation, parent needs flex-direction: column (found: ${flexDir})`);
            result.suggestions.push('Add to parent: flex-direction: column;');
        }

        // Check 3: Target should have explicit size
        if (isVertical) {
            const targetWidth = this._target.offsetWidth;
            if (targetWidth === 0) {
                result.valid = false;
                result.errors.push('Target element has 0 width. Set an initial width.');
                result.suggestions.push('Add to target: width: 200px; OR flex: 0 0 200px;');
            }
            // Check if target uses flex shorthand (recommended)
            const targetFlex = targetStyle.flex;
            if (!targetFlex || targetFlex === '0 1 auto' || targetFlex === 'auto') {
                result.warnings.push('Target should use flex: 0 0 Xpx for reliable resizing');
                result.suggestions.push(`Add to target: flex: 0 0 ${targetWidth}px;`);
            }
        } else {
            const targetHeight = this._target.offsetHeight;
            if (targetHeight === 0) {
                result.valid = false;
                result.errors.push('Target element has 0 height. Set an initial height.');
                result.suggestions.push('Add to target: height: 100px; OR flex: 0 0 100px;');
            }
            const targetFlex = targetStyle.flex;
            if (!targetFlex || targetFlex === '0 1 auto' || targetFlex === 'auto') {
                result.warnings.push('Target should use flex: 0 0 Xpx for reliable resizing');
                result.suggestions.push(`Add to target: flex: 0 0 ${targetHeight}px;`);
            }
        }

        // Check 4: Parent should have explicit height (for vertical) or width (for horizontal)
        if (isVertical) {
            const parentHeight = parent.offsetHeight;
            if (parentHeight === 0) {
                result.valid = false;
                result.errors.push('Parent container has 0 height. Fold needs explicit height to work.');
                result.suggestions.push('Add to parent: height: 100%; OR height: 400px;');
            }
        } else {
            const parentWidth = parent.offsetWidth;
            if (parentWidth === 0) {
                result.valid = false;
                result.errors.push('Parent container has 0 width. Fold needs explicit width to work.');
                result.suggestions.push('Add to parent: width: 100%;');
            }
        }

        // Check 5: Target should have overflow handling
        if (targetStyle.overflow === 'visible') {
            result.warnings.push('Target has overflow: visible. Content may overflow when resizing.');
            result.suggestions.push('Add to target: overflow: auto; OR overflow: hidden;');
        }

        // Check 6: Parent should prevent overflow
        if (parentStyle.overflow === 'visible') {
            result.warnings.push('Parent has overflow: visible. Content may overflow container.');
            result.suggestions.push('Add to parent: overflow: hidden;');
        }

        // Check 7: Fold should have flex-shrink: 0
        if (foldStyle.flexShrink !== '0') {
            result.warnings.push('cma-fold should have flex-shrink: 0 to prevent being squished');
        }

        // Check 8: Min/max size sanity
        if (this.minSize >= this.maxSize) {
            result.errors.push(`min-size (${this.minSize}) must be less than max-size (${this.maxSize})`);
            result.valid = false;
        }

        // Log errors to console if invalid
        if (!result.valid) {
            cmaLog.error('[cma-fold] CSS validation failed:', result.errors);
            cmaLog.log('[cma-fold] Suggestions:', result.suggestions);
        } else if (result.warnings.length > 0) {
            cmaLog.warn('[cma-fold] CSS warnings:', result.warnings);
        }

        return result;
    }

    /**
     * Get diagnostic information about the fold setup
     * @returns {Object} Diagnostic info
     */
    getDiagnostics() {
        const parent = this.parentElement;
        const target = this._target;

        return {
            orientation: this.orientation,
            isVertical: this.isVertical,
            minSize: this.minSize,
            maxSize: this.maxSize,
            collapsedSize: this.collapsedSize,
            storageKey: this.storageKey,
            isCollapsed: this._collapsed,
            hasTarget: !!target,
            targetSelector: this.getAttribute('target'),
            targetElement: target ? {
                tagName: target.tagName,
                id: target.id,
                className: target.className,
                width: target.offsetWidth,
                height: target.offsetHeight,
                computedFlex: target ? window.getComputedStyle(target).flex : null
            } : null,
            parentElement: parent ? {
                tagName: parent.tagName,
                id: parent.id,
                className: parent.className,
                display: parent ? window.getComputedStyle(parent).display : null,
                flexDirection: parent ? window.getComputedStyle(parent).flexDirection : null,
                width: parent.offsetWidth,
                height: parent.offsetHeight
            } : null
        };
    }
}

customElements.define('cma-fold', CmaFold);

} // end guard
