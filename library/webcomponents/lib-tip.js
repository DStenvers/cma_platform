/**
 * lib-tip Web Component
 *
 * A tip/tour system that highlights elements and provides contextual help.
 * Supports single tips and multi-step tours with navigation.
 *
 * Single Tip Usage:
 *   LibTip.show({
 *       target: '#myElement',
 *       title: 'Feature Name',
 *       content: 'Explanation of this feature...',
 *       position: 'bottom' // top, bottom, left, right
 *   });
 *
 * Tour Usage:
 *   LibTip.tour('form.php', [
 *       { target: '#saveBtn', title: 'Opslaan', content: 'Sla je wijzigingen op' },
 *       { target: '#deleteBtn', title: 'Verwijderen', content: 'Verwijder dit record' }
 *   ]);
 *
 * API Methods:
 *   LibTip.show(options)     - Show a single tip
 *   LibTip.tour(id, steps)   - Start a tour
 *   LibTip.dismiss(id)       - Dismiss a tip/tour permanently
 *   LibTip.isSkipped(id)     - Check if tip/tour is skipped
 *   LibTip.close()           - Close current tip/tour
 */

// Guard against double registration
if (!customElements.get('lib-tip')) {

    /**
     * Get the top window body element for rendering overlays.
     * When inside an iframe (e.g. sidepanel), this returns top.document.body
     * so the tip overlay covers the entire screen instead of just the iframe.
     */
    function _getTopBody() {
        try {
            if (window.self !== window.top && top.document && top.document.body) {
                return top.document.body;
            }
        } catch (e) { /* cross-origin iframe - fall back to current document */ }
        return document.body;
    }

    // Cached skip list from server
    let skipList = null;

    // localStorage key for fallback persistence
    const SKIP_STORAGE_KEY = 'libtip_skip_list';

    class LibTipElement extends HTMLElement {
        constructor() {
            super();
            this.attachShadow({ mode: 'open' });
            this._tourSteps = [];
            this._currentStep = 0;
            this._tourId = null;
            this._isTour = false;
        }

        connectedCallback() {
            this.render();
            this._bindEvents();
        }

        disconnectedCallback() {
            this._cleanup();
        }

        render() {
            this.shadowRoot.innerHTML = `
                <style>
                    :host {
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        z-index: 10000;
                        pointer-events: none;
                    }

                    .tip-overlay {
                        position: absolute;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        pointer-events: auto;
                    }

                    .tip-highlight {
                        position: fixed;
                        box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.6);
                        border-radius: 4px;
                        pointer-events: none;
                        transition: all 0.3s ease;
                        z-index: 10001;
                    }

                    .tip-highlight::before {
                        content: '';
                        position: absolute;
                        top: -4px;
                        left: -4px;
                        right: -4px;
                        bottom: -4px;
                        border: 3px solid var(--color-primary, #204496);
                        border-radius: 6px;
                        animation: tip-pulse 1.5s ease-in-out infinite;
                    }

                    @keyframes tip-pulse {
                        0%, 100% {
                            box-shadow: 0 0 0 0 rgba(32, 68, 150, 0.4);
                        }
                        50% {
                            box-shadow: 0 0 8px 4px rgba(32, 68, 150, 0.6);
                        }
                    }

                    .tip-popover {
                        position: fixed;
                        background: var(--bg-surface, #fff);
                        border-radius: 8px;
                        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
                        max-width: 360px;
                        min-width: 280px;
                        pointer-events: auto;
                        animation: tip-fadeIn 0.2s ease-out;
                        z-index: 10002;
                    }

                    @keyframes tip-fadeIn {
                        from { opacity: 0; transform: translateY(8px); }
                        to { opacity: 1; transform: translateY(0); }
                    }

                    .tip-arrow {
                        position: absolute;
                        width: 12px;
                        height: 12px;
                        background: var(--bg-surface, #fff);
                        transform: rotate(45deg);
                    }

                    .tip-popover[data-position="bottom"] .tip-arrow {
                        top: -6px;
                        left: 50%;
                        margin-left: -6px;
                    }

                    .tip-popover[data-position="top"] .tip-arrow {
                        bottom: -6px;
                        left: 50%;
                        margin-left: -6px;
                    }

                    .tip-popover[data-position="left"] .tip-arrow {
                        right: -6px;
                        top: 50%;
                        margin-top: -6px;
                    }

                    .tip-popover[data-position="right"] .tip-arrow {
                        left: -6px;
                        top: 50%;
                        margin-top: -6px;
                    }

                    .tip-header {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        padding: 14px 16px 10px;
                        border-bottom: 1px solid var(--border-color, #e0e0e0);
                    }

                    .tip-title {
                        font-weight: 600;
                        font-size: 15px;
                        color: var(--text-primary, #333);
                        flex: 1;
                    }

                    .tip-close {
                        background: none;
                        border: none;
                        cursor: pointer;
                        padding: 4px;
                        color: var(--text-muted, #999);
                        font-size: var(--font-size-lg);
                        line-height: 1;
                        border-radius: 4px;
                    }

                    .tip-close:hover {
                        background: var(--bg-hover, #f0f0f0);
                        color: var(--text-primary, #333);
                    }

                    .tip-close::before {
                        font-family: 'Linearicons';
                        content: "\\e92a"; /* lnr-cross */
                    }

                    .tip-content {
                        padding: 14px 16px;
                        font-size: var(--font-size);
                        line-height: 1.6;
                        color: var(--text-secondary, #666);
                    }

                    .tip-footer {
                        display: flex;
                        flex-direction: column;
                        padding: 10px 16px 14px;
                        border-top: 1px solid var(--border-color, #e0e0e0);
                        gap: 12px;
                    }

                    .tip-footer-row {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 12px;
                    }

                    .tip-dismiss {
                        font-size: var(--font-size-sm);
                        color: var(--text-muted, #999);
                        background: none;
                        border: none;
                        cursor: pointer;
                        padding: 4px 8px;
                    }

                    .tip-dismiss:hover {
                        color: var(--text-primary, #333);
                        text-decoration: underline;
                    }

                    .tip-actions {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                    }

                    .tip-btn {
                        padding: 6px 14px;
                        border-radius: 4px;
                        font-size: var(--font-size);
                        font-weight: 500;
                        cursor: pointer;
                        border: 1px solid var(--border-color, #ddd);
                        background: var(--bg-surface, #fff);
                        color: var(--text-primary, #333);
                        transition: all 0.15s ease;
                    }

                    .tip-btn:hover {
                        background: var(--bg-hover, #f5f5f5);
                        border-color: var(--border-hover, #ccc);
                    }

                    .tip-btn-primary {
                        background: var(--color-primary, #204496);
                        border-color: var(--color-primary, #204496);
                        color: #fff;
                    }

                    .tip-btn-primary:hover {
                        background: var(--color-primary-hover, #1a3a7a);
                        border-color: var(--color-primary-hover, #1a3a7a);
                    }

                    .tip-nav-btn {
                        width: 32px;
                        height: 32px;
                        padding: 0;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: var(--font-size-md);
                    }

                    .tip-nav-btn::before {
                        font-family: 'Linearicons';
                    }

                    .tip-nav-prev::before {
                        content: "\\e93b"; /* lnr-chevron-left */
                    }

                    .tip-nav-next::before {
                        content: "\\e93c"; /* lnr-chevron-right */
                    }

                    .tip-nav-btn:disabled {
                        opacity: 0.4;
                        cursor: not-allowed;
                    }

                    /* Tour gauge - progress line */
                    .tip-gauge {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        width: 100%;
                    }

                    .tip-gauge-bar {
                        flex: 1;
                        height: 4px;
                        background: var(--border-color, #ddd);
                        border-radius: 2px;
                        position: relative;
                        overflow: hidden;
                    }

                    .tip-gauge-progress {
                        position: absolute;
                        top: 0;
                        left: 0;
                        height: 100%;
                        background: var(--color-primary, #204496);
                        border-radius: 2px;
                        transition: width 0.3s ease;
                    }

                    .tip-gauge-text {
                        font-size: var(--font-size-xs);
                        color: var(--text-muted, #999);
                        white-space: nowrap;
                    }

                    /* Hidden state */
                    :host(:not([open])) {
                        display: none;
                    }
                </style>

                <div class="tip-overlay"></div>
                <div class="tip-highlight"></div>
                <div class="tip-popover" data-position="bottom">
                    <div class="tip-arrow"></div>
                    <div class="tip-header">
                        <span class="tip-title"></span>
                        <button class="tip-close" title="Sluiten"></button>
                    </div>
                    <div class="tip-content"></div>
                    <div class="tip-footer">
                        <div class="tip-gauge" style="display: none;">
                            <div class="tip-gauge-bar">
                                <div class="tip-gauge-progress"></div>
                            </div>
                            <span class="tip-gauge-text"></span>
                        </div>
                        <div class="tip-footer-row">
                            <button class="tip-dismiss">Niet meer tonen</button>
                            <div class="tip-actions">
                                <button class="tip-btn tip-nav-btn tip-nav-prev" title="Vorige" style="display: none;"></button>
                                <button class="tip-btn tip-nav-btn tip-nav-next" title="Volgende" style="display: none;"></button>
                                <button class="tip-btn tip-btn-primary tip-btn-ok">Begrepen</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        _bindEvents() {
            const overlay = this.shadowRoot.querySelector('.tip-overlay');
            const closeBtn = this.shadowRoot.querySelector('.tip-close');
            const dismissBtn = this.shadowRoot.querySelector('.tip-dismiss');
            const okBtn = this.shadowRoot.querySelector('.tip-btn-ok');
            const prevBtn = this.shadowRoot.querySelector('.tip-nav-prev');
            const nextBtn = this.shadowRoot.querySelector('.tip-nav-next');

            overlay.addEventListener('click', () => this.close());
            closeBtn.addEventListener('click', () => this.close());
            dismissBtn.addEventListener('click', () => this._dismiss());
            okBtn.addEventListener('click', () => this._next());
            prevBtn.addEventListener('click', () => this._prev());
            nextBtn.addEventListener('click', () => this._next());

            // Keyboard navigation
            document.addEventListener('keydown', this._keyHandler = (e) => {
                if (!this.hasAttribute('open')) return;
                if (e.key === 'Escape') this.close();
                if (this._isTour) {
                    if (e.key === 'ArrowRight' || e.key === 'Enter') this._next();
                    if (e.key === 'ArrowLeft') this._prev();
                }
            });

            // Handle window resize
            window.addEventListener('resize', this._resizeHandler = () => {
                if (this.hasAttribute('open')) {
                    this._positionPopover();
                }
            });
        }

        _cleanup() {
            if (this._keyHandler) {
                document.removeEventListener('keydown', this._keyHandler);
            }
            if (this._resizeHandler) {
                window.removeEventListener('resize', this._resizeHandler);
            }
        }

        /**
         * Show a single tip
         */
        showTip(options) {
            this._isTour = false;
            this._tourId = options.id || null;
            this._tourSteps = [options];
            this._currentStep = 0;
            this._showCurrentStep();
        }

        /**
         * Start a tour
         */
        startTour(tourId, steps) {
            this._isTour = true;
            this._tourId = tourId;
            this._tourSteps = steps.filter(step => {
                // Filter out steps where target doesn't exist or is not visible
                if (!step.target || typeof step.target !== 'string') return false;
                let el;
                try {
                    el = document.querySelector(step.target);
                } catch (e) {
                    return false;
                }
                if (!el) return false;
                // Check if element is visible (handles fixed/absolute positioned elements)
                const style = window.getComputedStyle(el);
                if (style.display === 'none' || style.visibility === 'hidden') return false;
                // Element exists and is not hidden
                return true;
            });
            this._currentStep = 0;

            if (this._tourSteps.length === 0) {
                console.warn('LibTip: No visible steps in tour');
                return;
            }

            this._showCurrentStep();
        }

        _showCurrentStep() {
            const step = this._tourSteps[this._currentStep];
            if (!step) return;

            let targetEl;
            try {
                targetEl = document.querySelector(step.target);
            } catch (e) {
                this._next();
                return;
            }
            if (!targetEl) {
                this._next();
                return;
            }

            // Update content
            this.shadowRoot.querySelector('.tip-title').textContent = step.title || '';
            this.shadowRoot.querySelector('.tip-content').innerHTML = step.content || '';

            // Update navigation buttons
            const prevBtn = this.shadowRoot.querySelector('.tip-nav-prev');
            const nextBtn = this.shadowRoot.querySelector('.tip-nav-next');
            const okBtn = this.shadowRoot.querySelector('.tip-btn-ok');
            const gauge = this.shadowRoot.querySelector('.tip-gauge');

            if (this._isTour && this._tourSteps.length > 1) {
                prevBtn.style.display = '';
                nextBtn.style.display = '';
                gauge.style.display = '';

                prevBtn.disabled = this._currentStep === 0;

                const isLastStep = this._currentStep === this._tourSteps.length - 1;
                nextBtn.style.display = isLastStep ? 'none' : '';
                okBtn.textContent = isLastStep ? 'Voltooien' : 'Begrepen';

                // Update gauge
                this._updateGauge();
            } else {
                prevBtn.style.display = 'none';
                nextBtn.style.display = 'none';
                gauge.style.display = 'none';
                okBtn.textContent = 'Begrepen';
            }

            // Show the tip
            this.setAttribute('open', '');

            // Scroll target into view first, then position after scroll
            targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Position after a brief delay to let scroll complete
            setTimeout(() => {
                this._positionHighlight(targetEl);
                this._positionPopover(targetEl, step.position || 'bottom');
            }, 100);

            // Also re-position on scroll in case smooth scroll takes longer
            const repositionOnce = () => {
                this._positionHighlight(targetEl);
                this._positionPopover(targetEl, step.position || 'bottom');
                window.removeEventListener('scroll', repositionOnce);
            };
            window.addEventListener('scroll', repositionOnce, { once: true, passive: true });
        }

        _updateGauge() {
            const progressBar = this.shadowRoot.querySelector('.tip-gauge-progress');
            const gaugeText = this.shadowRoot.querySelector('.tip-gauge-text');

            // Calculate progress percentage (current step is 0-indexed, add 1 for display)
            const progress = ((this._currentStep + 1) / this._tourSteps.length) * 100;
            progressBar.style.width = progress + '%';

            gaugeText.textContent = `${this._currentStep + 1} / ${this._tourSteps.length}`;
        }

        _positionHighlight(targetEl) {
            const rect = targetEl.getBoundingClientRect();
            const highlight = this.shadowRoot.querySelector('.tip-highlight');
            const padding = 6;

            highlight.style.top = (rect.top - padding) + 'px';
            highlight.style.left = (rect.left - padding) + 'px';
            highlight.style.width = (rect.width + padding * 2) + 'px';
            highlight.style.height = (rect.height + padding * 2) + 'px';
        }

        _positionPopover(targetEl, preferredPosition = 'bottom') {
            if (!targetEl) {
                try {
                    targetEl = document.querySelector(this._tourSteps[this._currentStep]?.target);
                } catch (e) { /* invalid selector */ }
            }
            if (!targetEl) return;

            const rect = targetEl.getBoundingClientRect();
            const popover = this.shadowRoot.querySelector('.tip-popover');
            const popoverRect = popover.getBoundingClientRect();
            const gap = 16;
            const viewportPadding = 20;

            let position = preferredPosition;
            let top, left;

            // Calculate positions for each direction
            const positions = {
                bottom: {
                    top: rect.bottom + gap,
                    left: rect.left + rect.width / 2 - popoverRect.width / 2,
                    fits: rect.bottom + gap + popoverRect.height < window.innerHeight - viewportPadding
                },
                top: {
                    top: rect.top - gap - popoverRect.height,
                    left: rect.left + rect.width / 2 - popoverRect.width / 2,
                    fits: rect.top - gap - popoverRect.height > viewportPadding
                },
                right: {
                    top: rect.top + rect.height / 2 - popoverRect.height / 2,
                    left: rect.right + gap,
                    fits: rect.right + gap + popoverRect.width < window.innerWidth - viewportPadding
                },
                left: {
                    top: rect.top + rect.height / 2 - popoverRect.height / 2,
                    left: rect.left - gap - popoverRect.width,
                    fits: rect.left - gap - popoverRect.width > viewportPadding
                }
            };

            // Use preferred position if it fits, otherwise find one that does
            if (!positions[position].fits) {
                const fallbackOrder = ['bottom', 'top', 'right', 'left'];
                for (const pos of fallbackOrder) {
                    if (positions[pos].fits) {
                        position = pos;
                        break;
                    }
                }
            }

            top = positions[position].top;
            left = positions[position].left;

            // Constrain to viewport
            left = Math.max(viewportPadding, Math.min(left, window.innerWidth - popoverRect.width - viewportPadding));
            top = Math.max(viewportPadding, Math.min(top, window.innerHeight - popoverRect.height - viewportPadding));

            popover.style.top = top + 'px';
            popover.style.left = left + 'px';
            popover.setAttribute('data-position', position);
        }

        _next() {
            if (this._isTour && this._currentStep < this._tourSteps.length - 1) {
                this._currentStep++;
                this._showCurrentStep();
            } else {
                this.close();
            }
        }

        _prev() {
            if (this._isTour && this._currentStep > 0) {
                this._currentStep--;
                this._showCurrentStep();
            }
        }

        _dismiss() {
            if (this._tourId) {
                LibTip.dismiss(this._tourId);
            }
            this.close();
        }

        close() {
            this.removeAttribute('open');
            this.dispatchEvent(new CustomEvent('tip-close', { bubbles: true }));
        }
    }

    // Register element
    customElements.define('lib-tip', LibTipElement);

    // Static API
    const LibTip = {
        _element: null,
        _skipListLoaded: false,

        /**
         * Get or create the tip element
         */
        _getElement() {
            if (!this._element || !this._element.isConnected) {
                this._element = document.createElement('lib-tip');
                _getTopBody().appendChild(this._element);
            }
            return this._element;
        },

        /**
         * Load skip list from server
         */
        async _loadSkipList() {
            if (this._skipListLoaded && skipList !== null) {
                return skipList;
            }

            // Load from localStorage as immediate fallback
            let localList = [];
            try {
                const stored = localStorage.getItem(SKIP_STORAGE_KEY);
                if (stored) localList = JSON.parse(stored) || [];
            } catch (e) { /* ignore */ }

            try {
                const response = await fetch('/cma/api/user_tips.php?action=get_skip_list');
                if (response.ok) {
                    const data = await response.json();
                    skipList = data.skipList || [];
                    // Merge local dismissals that server might not have
                    for (const id of localList) {
                        if (!skipList.includes(id)) {
                            skipList.push(id);
                        }
                    }
                    this._skipListLoaded = true;
                } else {
                    // Server error - use localStorage fallback
                    skipList = localList;
                }
            } catch (e) {
                console.warn('LibTip: Could not load skip list', e);
                skipList = localList;
            }

            return skipList || [];
        },

        /**
         * Check if a tip/tour is skipped
         */
        async isSkipped(id) {
            const list = await this._loadSkipList();
            return list && list.includes(id);
        },

        /**
         * Show a single tip
         */
        async show(options) {
            const id = options.id || options.target;
            if (await this.isSkipped(id)) {
                return false;
            }

            const el = this._getElement();
            el.showTip({ ...options, id });
            return true;
        },

        /**
         * Start a tour
         */
        async tour(tourId, steps) {
            if (await this.isSkipped(tourId)) {
                return false;
            }

            const el = this._getElement();
            el.startTour(tourId, steps);
            return true;
        },

        /**
         * Dismiss a tip/tour permanently
         */
        async dismiss(id) {
            if (!skipList) {
                skipList = [];
            }
            if (!skipList.includes(id)) {
                skipList.push(id);
            }

            // Always save to localStorage as immediate fallback
            try {
                localStorage.setItem(SKIP_STORAGE_KEY, JSON.stringify(skipList));
            } catch (e) { /* ignore */ }

            try {
                const response = await fetch('/cma/api/user_tips.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'dismiss', id })
                });
                if (!response.ok) {
                    console.warn('LibTip: Server returned', response.status, 'for dismiss');
                }
            } catch (e) {
                console.warn('LibTip: Could not save dismissal', e);
            }
        },

        /**
         * Close current tip/tour
         */
        close() {
            if (this._element) {
                this._element.close();
            }
        },

        /**
         * Reset skip list (for testing)
         */
        async reset(id = null) {
            try {
                await fetch('/cma/api/user_tips.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'reset', id })
                });
                if (id && skipList) {
                    skipList = skipList.filter(i => i !== id);
                } else {
                    skipList = [];
                }
                this._skipListLoaded = !!skipList.length;
                // Update localStorage fallback
                try {
                    localStorage.setItem(SKIP_STORAGE_KEY, JSON.stringify(skipList));
                } catch (e) { /* ignore */ }
            } catch (e) {
                console.warn('LibTip: Could not reset', e);
            }
        }
    };

    // Export
    window.LibTip = LibTip;

} // end guard
