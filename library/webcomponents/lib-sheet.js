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
        this._handle   = this.shadowRoot.querySelector('.handle');
        this._header   = this.shadowRoot.querySelector('.header');
        this._title    = this.shadowRoot.querySelector('.title');
        this._closeBtn = this.shadowRoot.querySelector('.close');
        this._lastFocus = null;
        this._prevOverflow = '';
        this._dragStartY = null;
        this._dragDelta  = 0;
        this._onKeydown    = this._onKeydown.bind(this);
        this._onDragStart  = this._onDragStart.bind(this);
        this._onDragMove   = this._onDragMove.bind(this);
        this._onDragEnd    = this._onDragEnd.bind(this);
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
        // Drag-to-dismiss: bound on BOTH the grab handle and the header
        // row. The visible handle bar is intentionally thin (~12px) and
        // easy to miss on touch — extending the drag region to the
        // whole header gives users a comfortable hit area without
        // co-opting the scrollable body. _onDragStart bails out when
        // the pointer landed on the close button or any other
        // interactive control inside the header.
        this._handle.addEventListener('pointerdown', this._onDragStart);
        this._header.addEventListener('pointerdown', this._onDragStart);
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

    open() {
        // Wipe any leftover inline styles from an interrupted drag, then
        // flip the attribute. _activate handles the slide animation.
        if (this._panel) {
            this._panel.style.transition = '';
            this._panel.style.transform  = '';
        }
        this.setAttribute('open', '');
    }
    close() {
        if (this._panel) {
            this._panel.style.transition = '';
            this._panel.style.transform  = '';
        }
        this.removeAttribute('open');
    }
    toggle() { this.hasAttribute('open') ? this.close() : this.open(); }

    /* ---- internal ---- */

    _prefersReducedMotion() {
        try {
            return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        } catch (_) {
            return false;
        }
    }

    _activate() {
        this.removeAttribute('aria-hidden');
        this._lastFocus = this.ownerDocument.activeElement;
        this._lockScroll(true);
        document.addEventListener('keydown', this._onKeydown);

        // Slide-in via Web Animations API. The CSS rule for :host([open])
        // already moved the panel to translateY(0), so this animate()
        // call paints the in-flight slide from 100% → 0 explicitly.
        // WAAPI fires reliably regardless of whether the browser had
        // committed the closed state — that's the property the CSS
        // transition couldn't guarantee on first-open after attach,
        // direct setAttribute, or after an interrupted drag (which left
        // the panel with inline transition:none).
        if (this._panel
            && typeof this._panel.animate === 'function'
            && !this._prefersReducedMotion()) {
            this._panel.animate(
                [{ transform: 'translateY(100%)' }, { transform: 'translateY(0)' }],
                { duration: 260, easing: 'cubic-bezier(0.32, 0.72, 0, 1)' }
            );
        }

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

        // Slide-out via WAAPI. CSS already moved the panel to
        // translateY(100%); we paint the transition from the previously-
        // visible position back to fully off-screen. This still runs
        // when the user dismissed the sheet via close() OR by simply
        // removing the [open] attribute externally.
        if (this._panel
            && typeof this._panel.animate === 'function'
            && !this._prefersReducedMotion()) {
            this._panel.animate(
                [{ transform: 'translateY(0)' }, { transform: 'translateY(100%)' }],
                { duration: 260, easing: 'cubic-bezier(0.32, 0.72, 0, 1)' }
            );
        }

        this.dispatchEvent(new CustomEvent('sheet-close', { bubbles: true }));
    }

    _onKeydown(e) {
        if (e.key === 'Escape' && this.isClosable) {
            e.preventDefault();
            this.close();
        }
    }

    /* ---- drag-to-dismiss ----
     * Pointer Events give us unified touch/mouse handling. We translate
     * the panel down by the pointer's delta during the drag (downward
     * only — upward delta clamps to 0 so the sheet doesn't lift off the
     * bottom edge) and decide on release whether to close or snap back.
     *
     * Threshold: 80px of downward movement OR a release velocity above
     * 0.5 px/ms commits the dismiss. Anything less snaps back. Matches
     * what most native bottom sheets feel like.
     */
    _onDragStart(e) {
        if (!this.isClosable) { return; }
        if (typeof e.button === 'number' && e.button !== 0) { return; } // primary button only

        // Don't co-opt taps on interactive elements inside the header
        // (close button, future links/buttons in heading area). Walk the
        // event path to handle shadow-DOM correctly.
        const path = typeof e.composedPath === 'function' ? e.composedPath() : [e.target];
        for (const node of path) {
            if (node === this._handle || node === this._header) { break; }
            if (node && node.nodeType === 1 && /^(button|a|input|select|textarea|label)$/i.test(node.tagName)) {
                return;
            }
        }

        // Bind move/end on the same element pointerdown fired on, so
        // setPointerCapture targets the right node. Stored on the
        // instance so _onDragEnd knows where to detach from.
        this._dragSurface = e.currentTarget;
        this._dragStartY = e.clientY;
        this._dragStartTime = (typeof performance !== 'undefined' ? performance.now() : Date.now());
        this._dragDelta = 0;
        this._panel.style.transition = 'none';
        try { this._dragSurface.setPointerCapture(e.pointerId); } catch (_) {}
        this._dragSurface.addEventListener('pointermove',   this._onDragMove);
        this._dragSurface.addEventListener('pointerup',     this._onDragEnd);
        this._dragSurface.addEventListener('pointercancel', this._onDragEnd);
    }

    _onDragMove(e) {
        if (this._dragStartY === null) { return; }
        const delta = e.clientY - this._dragStartY;
        this._dragDelta = delta > 0 ? delta : 0;
        this._panel.style.transform = 'translateY(' + this._dragDelta + 'px)';
    }

    _onDragEnd(e) {
        if (this._dragStartY === null) { return; }
        const elapsed = (typeof performance !== 'undefined' ? performance.now() : Date.now()) - this._dragStartTime;
        const velocity = elapsed > 0 ? this._dragDelta / elapsed : 0; // px/ms
        const shouldDismiss = this._dragDelta > 80 || velocity > 0.5;
        const surface = this._dragSurface || this._handle;
        surface.removeEventListener('pointermove',   this._onDragMove);
        surface.removeEventListener('pointerup',     this._onDragEnd);
        surface.removeEventListener('pointercancel', this._onDragEnd);
        try { surface.releasePointerCapture(e.pointerId); } catch (_) {}
        this._panel.style.transition = '';
        this._panel.style.transform  = '';
        this._dragStartY = null;
        this._dragDelta  = 0;
        this._dragSurface = null;
        if (shouldDismiss) {
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
                    cursor: grab;
                    touch-action: none;
                    -webkit-tap-highlight-color: transparent;
                }
                .header:active { cursor: grabbing; }
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

                /* Grab handle — also the drag target. Padding around the
                 * visible bar widens the hit area so it's tappable on
                 * mobile without making the bar itself look chunky. */
                .handle {
                    display: block;
                    width: 100%;
                    padding: 0.5rem 0 0.25rem;
                    cursor: grab;
                    touch-action: none;
                    background: transparent;
                    border: none;
                    -webkit-tap-highlight-color: transparent;
                }
                .handle:active { cursor: grabbing; }
                .handle::before {
                    content: "";
                    display: block;
                    width: 2.25rem;
                    height: 0.25rem;
                    border-radius: 999px;
                    background: currentColor;
                    opacity: 0.2;
                    margin: 0 auto;
                }

                @media (prefers-reduced-motion: reduce) {
                    .backdrop, .panel { transition: none; }
                }
            </style>
            <div class="backdrop" part="backdrop"></div>
            <div class="panel" part="panel">
                <div class="handle" part="handle" role="button" tabindex="-1" aria-label="Drag to dismiss"></div>
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
