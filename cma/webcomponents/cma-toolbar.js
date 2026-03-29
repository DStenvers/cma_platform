/**
 * cma-toolbar Web Component
 *
 * A flexible toolbar component with left, center, and right sections.
 *
 * Usage:
 *   <cma-toolbar>
 *       <left>
 *           <button>Save</button>
 *           <button>Cancel</button>
 *       </left>
 *       <right>
 *           <input type="search" placeholder="Zoeken...">
 *       </right>
 *   </cma-toolbar>
 *
 *   <cma-toolbar variant="subform">
 *       <left>
 *           <button class="tb-btn"><span class="lnr lnr-plus-circle"></span></button>
 *       </left>
 *   </cma-toolbar>
 *
 * Attributes:
 *   - variant: "default" | "list" | "detail" | "subform" - toolbar style variant
 *   - sticky: If present, toolbar sticks to top when scrolling
 *
 * Slots:
 *   - left: Content for the left section
 *   - center: Content for the center section (optional)
 *   - right: Content for the right section
 */
// Guard against double registration
if (!customElements.get('cma-toolbar')) {

class CmaToolbar extends HTMLElement {
    static get observedAttributes() {
        return ['variant', 'sticky'];
    }

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._childObserver = null;
    }

    connectedCallback() {
        // Adopt shared styles if available
        if (typeof LibSharedStyles !== 'undefined' && LibSharedStyles.isSupported()) {
            LibSharedStyles.adopt(this.shadowRoot, 'base', 'button');
        }

        // Inject light DOM styles once per page for buttons inside <left>/<right>/<center>
        CmaToolbar._injectLightDomStyles();

        this.render();
        this._setupChildObserver();
    }

    static _injectLightDomStyles() {
        if (CmaToolbar._stylesInjected) return;
        CmaToolbar._stylesInjected = true;
        var style = document.createElement('style');
        style.textContent = `
            cma-toolbar left, cma-toolbar right, cma-toolbar center {
                display: contents;
            }
            cma-toolbar left > .tb-btn,
            cma-toolbar right > .tb-btn,
            cma-toolbar center > .tb-btn,
            cma-toolbar left > button,
            cma-toolbar right > button,
            cma-toolbar center > button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 4px 8px;
                border: none;
                background: transparent;
                cursor: pointer;
                border-radius: 4px;
                color: var(--text-primary, #333);
                font-size: 13px;
                gap: 4px;
                transition: background-color 0.15s ease;
            }
            cma-toolbar left > .tb-btn:hover,
            cma-toolbar right > .tb-btn:hover,
            cma-toolbar center > .tb-btn:hover,
            cma-toolbar left > button:hover,
            cma-toolbar right > button:hover,
            cma-toolbar center > button:hover {
                background: var(--hover-bg, rgba(0, 0, 0, 0.08));
            }
            cma-toolbar left > button:disabled,
            cma-toolbar right > button:disabled,
            cma-toolbar center > button:disabled,
            cma-toolbar left > .tb-btn.disabled,
            cma-toolbar right > .tb-btn.disabled,
            cma-toolbar center > .tb-btn.disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
            cma-toolbar .tb-btn a {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                text-decoration: none;
                color: inherit;
            }
        `;
        document.head.appendChild(style);
    }

    disconnectedCallback() {
        if (this._childObserver) {
            this._childObserver.disconnect();
            this._childObserver = null;
        }
    }

    _setupChildObserver() {
        // Watch for dynamically added children and auto-assign slots
        this._childObserver = new MutationObserver(() => {
            this.assignSlots();
        });
        this._childObserver.observe(this, { childList: true });
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue !== newValue) {
            this.render();
        }
    }

    get variant() {
        return this.getAttribute('variant') || 'default';
    }

    get sticky() {
        return this.hasAttribute('sticky');
    }

    render() {
        const variant = this.variant;
        const sticky = this.sticky;

        this.shadowRoot.innerHTML = `
            <style>
                :host {
                    display: block;
                }

                .toolbar {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 4px 8px;
                    background: linear-gradient(to bottom, var(--bg-surface, #f5f5f5) 0%, var(--bg-surface-alt, #eaeaea) 100%);
                    min-height: 35px;
                    flex-shrink: 0;
                    box-sizing: border-box;
                    font-family: var(--font-family);
                }

                :host([sticky]) .toolbar {
                    position: sticky;
                    top: 0;
                    z-index: 10;
                }

                /* Variant styles */
                :host([variant="subform"]) .toolbar {
                    padding: 2px 4px;
                    min-height: 39px;
                }

                :host([variant="detail"]) .toolbar {
                    border-bottom: 1px solid var(--border-dark, #ccc);
                }

                :host([variant="list"]) .toolbar {
                    border-bottom: 1px solid var(--border-dark, #ccc);
                }

                /* Has center - don't use space-between */
                .toolbar:has(::slotted(center)) {
                    justify-content: flex-start;
                }

                .toolbar-left {
                    display: flex;
                    align-items: center;
                    gap: 2px;
                    flex-wrap: nowrap;
                }

                .toolbar-center {
                    display: flex;
                    align-items: center;
                    flex: 1;
                    justify-content: center;
                    padding: 0 8px;
                }

                .toolbar-right {
                    display: flex;
                    align-items: center;
                    gap: 4px;
                    flex-shrink: 0;
                }

                /* Slotted left/right/center containers */
                ::slotted(left),
                ::slotted(right),
                ::slotted(center) {
                    display: contents;
                }

                /* Slotted button styles - direct slotted elements */
                ::slotted(button),
                ::slotted(.tb-btn) {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    padding: 4px 8px;
                    border: none;
                    background: transparent;
                    cursor: pointer;
                    border-radius: 4px;
                    color: var(--text-primary, #333);
                    font-size: 13px;
                    gap: 4px;
                    transition: background-color 0.15s ease;
                }

                ::slotted(button:hover),
                ::slotted(.tb-btn:hover) {
                    background: var(--hover-bg, rgba(0, 0, 0, 0.08));
                }

                ::slotted(button:disabled),
                ::slotted(.tb-btn:disabled) {
                    opacity: 0.5;
                    cursor: not-allowed;
                }

                /* Dark mode support */
                :host-context(html.dark-mode) .toolbar {
                    background: linear-gradient(to bottom, var(--bg-surface, #2a2a2a) 0%, var(--bg-surface-alt, #222) 100%);
                    border-color: var(--border-dark, #444);
                }

                /* Responsive: hide center on small screens */
                @media (max-width: 600px) {
                    .toolbar-center {
                        display: none;
                    }
                }
            </style>
            <div class="toolbar">
                <div class="toolbar-left">
                    <slot name="left"></slot>
                </div>
                <div class="toolbar-center">
                    <slot name="center"></slot>
                </div>
                <div class="toolbar-right">
                    <slot name="right"></slot>
                </div>
            </div>
        `;

        // Move children to appropriate slots based on tag name
        this.assignSlots();
    }

    assignSlots() {
        // Auto-assign slot attributes to left/center/right children
        Array.from(this.children).forEach(child => {
            const tagName = child.tagName.toLowerCase();
            if (tagName === 'left' && !child.hasAttribute('slot')) {
                child.setAttribute('slot', 'left');
            } else if (tagName === 'center' && !child.hasAttribute('slot')) {
                child.setAttribute('slot', 'center');
            } else if (tagName === 'right' && !child.hasAttribute('slot')) {
                child.setAttribute('slot', 'right');
            }
        });
    }
}

// Register the component
customElements.define('cma-toolbar', CmaToolbar);

} // end guard
