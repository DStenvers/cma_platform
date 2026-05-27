/**
 * lib-sheet Web Component
 *
 * A bottom sliding panel ("action sheet" / "bottom sheet"). Slides up from
 * the bottom of the viewport over a dimmed backdrop. Mobile-first, but on
 * wider screens it caps its width and centers. Content is projected through
 * a default slot, so the host page styles the body normally — only the
 * panel chrome (backdrop, container, header, close button) lives in the
 * shadow DOM.
 *
 * Declarative usage:
 *   <lib-sheet id="mySheet" heading="Options">
 *       <button>Edit</button>
 *       <button>Archive</button>
 *       <button>Delete</button>
 *   </lib-sheet>
 *
 *   document.getElementById('mySheet').open();
 *
 * Attributes:
 *   - heading:  Optional header title. Omit for a chrome-less sheet (no
 *               header row is rendered when both heading and the close
 *               button are absent).
 *   - open:     Reflects visible state. Drives the CSS transition; set it
 *               (or call open()) to show, remove it (or call close()) to hide.
 *   - closable: "false" hides the close button and disables Escape / backdrop
 *               close (default: true).
 *
 * Methods:
 *   - open(), close(), toggle()
 *
 * Events:
 *   - sheet-open:  Fired when the sheet opens.
 *   - sheet-close: Fired when the sheet closes.
 *
 * Theming (set these CSS custom properties on the element):
 *   --lib-sheet-bg, --lib-sheet-color, --lib-sheet-backdrop,
 *   --lib-sheet-radius, --lib-sheet-max-height, --lib-sheet-max-width,
 *   --lib-sheet-shadow, --lib-sheet-z, --lib-sheet-border
 *
 * Shadow parts: backdrop, panel, header, title, close, body
 */

// Guard against double declaration when the script is loaded more than once.
if (!customElements.get('lib-sheet')) {

class LibSheet extends HTMLElement {
    static get observedAttributes() {
        return ['heading', 'open', 'closable'];
    }

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        // Build the static structure once, in the constructor, so element
        // references exist even if `open` is set in markup before
        // connectedCallback runs. Slotted content sidesteps the
        // child-not-yet-parsed timing trap entirely.
        this.shadowRoot.innerHTML = this._template();
        this._backdrop = this.shadowRoot.querySelector('.backdrop');
        this._panel    = this.shadowRoot.querySelector('.panel');
        this._header   = this.shadowRoot.querySelector('.header');
        this._title    = this.shadowRoot.querySelector('.title');
        this._closeBtn = this.shadowRoot.querySelector('.close');
        this._lastFocus = null;
        this._prevOverflow = '';
        this._onKeydown = this._onKeydown.bind(this);
    }

    connectedCallback() {
        if (!this.hasAttribute('open')) {
            this.setAttribute('aria-hidden', 'true');
        }
        this._panel.setAttribute('role', 'dialog');
        this._panel.setAttribute('aria-modal', 'true');
        this._syncHeading();
        this._syncClosable();
        this._backdrop.addEventListener('click', () => {
            if (this.isClosable) { this.close(); }
        });
        this._closeBtn.addEventListener('click', () => this.close());
    }

    disconnectedCallback() {
        document.removeEventListener('keydown', this._onKeydown);
        this._lockScroll(false);
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (name === 'heading')  { this._syncHeading(); }
        if (name === 'closable') { this._syncClosable(); }
        if (name === 'open') {
            const isOpen = newValue !== null;
            if (isOpen) { this._activate(); } else { this._deactivate(); }
        }
    }

    get isClosable() {
        return this.getAttribute('closable') !== 'false';
    }

    open()  { this.setAttribute('open', ''); }
    close() { this.removeAttribute('open'); }
    toggle() { this.hasAttribute('open') ? this.close() : this.open(); }

    /* ---- internal ---- */

    _activate() {
        this.removeAttribute('aria-hidden');
        this._lastFocus = this.ownerDocument.activeElement;
        this._lockScroll(true);
        document.addEventListener('keydown', this._onKeydown);
        // Defer focus a frame so the slide-in has started and the panel is
        // hit-testable.
        requestAnimationFrame(() => {
            const focusable = this.querySelector(
                'input, button, select, textarea, a[href], [tabindex]:not([tabindex="-1"])'
            ) || (this.isClosable ? this._closeBtn : this._panel);
            if (focusable && typeof focusable.focus === 'function') { focusable.focus(); }
        });
        this.dispatchEvent(new CustomEvent('sheet-open', { bubbles: true }));
    }

    _deactivate() {
        this.setAttribute('aria-hidden', 'true');
        this._lockScroll(false);
        document.removeEventListener('keydown', this._onKeydown);
        if (this._lastFocus && typeof this._lastFocus.focus === 'function') {
            this._lastFocus.focus();
        }
        this.dispatchEvent(new CustomEvent('sheet-close', { bubbles: true }));
    }

    _onKeydown(e) {
        if (e.key === 'Escape' && this.isClosable) {
            e.preventDefault();
            this.close();
        }
    }

    _syncHeading() {
        const heading = this.getAttribute('heading') || '';
        this._title.textContent = heading;
        this._updateHeaderVisibility();
    }

    _syncClosable() {
        this._closeBtn.hidden = !this.isClosable;
        this._updateHeaderVisibility();
    }

    _updateHeaderVisibility() {
        // No header row when there's nothing to show in it.
        const hasHeading = (this.getAttribute('heading') || '') !== '';
        this._header.hidden = !hasHeading && !this.isClosable;
    }

    _lockScroll(lock) {
        const body = document.body;
        if (!body) { return; }
        if (lock) {
            this._prevOverflow = body.style.overflow;
            body.style.overflow = 'hidden';
        } else {
            body.style.overflow = this._prevOverflow || '';
        }
    }

    _template() {
        return `
            <style>
                :host {
                    position: fixed;
                    inset: 0;
                    z-index: var(--lib-sheet-z, 1000);
                    pointer-events: none;
                    --_radius: var(--lib-sheet-radius, 14px);
                }
                :host([open]) { pointer-events: auto; }

                .backdrop {
                    position: absolute;
                    inset: 0;
                    background: var(--lib-sheet-backdrop, rgba(0, 0, 0, 0.45));
                    opacity: 0;
                    transition: opacity 0.2s ease;
                }
                :host([open]) .backdrop { opacity: 1; }

                .panel {
                    position: absolute;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    margin: 0 auto;
                    width: 100%;
                    max-width: var(--lib-sheet-max-width, none);
                    max-height: var(--lib-sheet-max-height, 80vh);
                    display: flex;
                    flex-direction: column;
                    background: var(--lib-sheet-bg, #ffffff);
                    color: var(--lib-sheet-color, inherit);
                    border-top: var(--lib-sheet-border, none);
                    border-radius: var(--_radius) var(--_radius) 0 0;
                    box-shadow: var(--lib-sheet-shadow, 0 -8px 28px rgba(0, 0, 0, 0.28));
                    padding-bottom: env(safe-area-inset-bottom, 0px);
                    transform: translateY(100%);
                    transition: transform 0.26s cubic-bezier(0.32, 0.72, 0, 1);
                }
                :host([open]) .panel { transform: translateY(0); }

                .header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 0.5rem;
                    padding: 0.85rem 1rem 0.5rem;
                }
                .title {
                    margin: 0;
                    font-size: 1rem;
                    font-weight: 600;
                }
                .close {
                    flex: 0 0 auto;
                    background: none;
                    border: none;
                    color: inherit;
                    opacity: 0.7;
                    font-size: 1.5rem;
                    line-height: 1;
                    cursor: pointer;
                    padding: 0 0.25rem;
                    border-radius: 6px;
                }
                .close:hover  { opacity: 1; }
                .close:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }

                .body {
                    overflow-y: auto;
                    -webkit-overflow-scrolling: touch;
                    padding: 0.25rem 1rem 1rem;
                }

                /* A small grab handle for the mobile bottom-sheet affordance. */
                .panel::before {
                    content: "";
                    width: 2.25rem;
                    height: 0.25rem;
                    border-radius: 999px;
                    background: currentColor;
                    opacity: 0.2;
                    margin: 0.5rem auto 0;
                }

                @media (prefers-reduced-motion: reduce) {
                    .backdrop, .panel { transition: none; }
                }
            </style>
            <div class="backdrop" part="backdrop"></div>
            <div class="panel" part="panel">
                <div class="header" part="header">
                    <h2 class="title" part="title"></h2>
                    <button class="close" part="close" type="button" aria-label="Close" title="Close">&times;</button>
                </div>
                <div class="body" part="body">
                    <slot></slot>
                </div>
            </div>
        `;
    }
}

customElements.define('lib-sheet', LibSheet);

}
