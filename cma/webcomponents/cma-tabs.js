/**
 * cma-tabs Web Component
 *
 * A tab strip that shows tabs on desktop and a select dropdown on mobile.
 * Supports slotted content panels that are automatically shown/hidden.
 * Also supports wizard mode with step indicators.
 *
 * Usage with tab-item children:
 *   <cma-tabs selected="0" breakpoint="500">
 *       <tab-item title="Subform 1" data-id="69" data-count="5"></tab-item>
 *       <tab-item title="Subform 2" data-id="71"></tab-item>
 *   </cma-tabs>
 *
 * Usage with tabs attribute and slotted content:
 *   <cma-tabs tabs='["Tab 1", "Tab 2"]'>
 *       <div slot="tab-0">Content for tab 1</div>
 *       <div slot="tab-1">Content for tab 2</div>
 *   </cma-tabs>
 *
 * Wizard mode (step indicator):
 *   <cma-tabs mode="wizard" tabs='[
 *       {"title": "Tabellen", "completed": true},
 *       {"title": "Velden", "completed": false},
 *       {"title": "Parameters", "completed": false}
 *   ]'>
 *       <div slot="tab-0">Step 1 content</div>
 *       <div slot="tab-1">Step 2 content</div>
 *       <div slot="tab-2">Step 3 content</div>
 *   </cma-tabs>
 *
 * Or dynamically:
 *   const tabs = document.querySelector('cma-tabs');
 *   tabs.setTabs([
 *       { title: 'Tab 1', id: '69', count: 5 },
 *       { title: 'Tab 2', id: '71' }
 *   ]);
 *   tabs.addEventListener('tab-select', e => console.log(e.detail));
 *
 * Attributes:
 *   - selected: Index of initially selected tab (default: 0)
 *   - breakpoint: Viewport width below which select is shown (default: 500)
 *   - tabs: JSON array of tab titles or tab objects [{title, id, count, beheer, completed}]
 *   - mode: "default" | "wizard" - display mode (default: "default")
 *
 * Slots:
 *   - tab-0, tab-1, etc: Content panels shown when corresponding tab is selected
 *
 * Events:
 *   - tab-select: Fired when tab changes. Detail: { index, id, title }
 *   - step-change: Fired in wizard mode. Detail: { index, title, completed, canProceed }
 */
// Guard against double registration
if (!customElements.get('cma-tabs')) {

class CmaTabs extends HTMLElement {
    static get observedAttributes() {
        return ['selected', 'breakpoint', 'tabs', 'mode'];
    }

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._tabs = [];
        this._selectedIndex = 0;
        this._initialized = false;
        this._mode = 'default';
    }

    connectedCallback() {
        if (!this._initialized) {
            // Defer parsing to allow light DOM children to be parsed
            // This handles the case where connectedCallback runs before <tab-item> children are available
            if (this.children.length > 0) {
                // Children already available, parse immediately
                this._initializeTabs();
            } else {
                // Wait for children to be parsed
                // Use requestAnimationFrame to wait for next frame when DOM should be ready
                requestAnimationFrame(() => {
                    if (!this._initialized) {
                        this._initializeTabs();
                    }
                });
                // Also set up a MutationObserver as fallback for dynamic children
                this._setupChildObserver();
            }
        }
    }

    _initializeTabs() {
        // Adopt shared styles if available
        if (typeof LibSharedStyles !== 'undefined' && LibSharedStyles.isSupported()) {
            LibSharedStyles.adopt(this.shadowRoot, 'base', 'input', 'animation');
        }

        // Parse mode attribute
        this._mode = this.getAttribute('mode') || 'default';

        // First try to parse tabs from attribute (JSON array)
        // Skip if _tabs was already populated by attributeChangedCallback
        const tabsAttr = this.getAttribute('tabs');
        if (tabsAttr && this._tabs.length === 0) {
            try {
                const tabsData = JSON.parse(tabsAttr);
                if (Array.isArray(tabsData)) {
                    this._tabs = tabsData.map((t, i) => {
                        if (typeof t === 'string') {
                            return { title: t, id: String(i), count: null, beheer: false, completed: false, disabled: false };
                        }
                        return {
                            title: t.title || `Tab ${i + 1}`,
                            id: t.id || String(i),
                            count: t.count ?? null,
                            beheer: t.beheer || false,
                            completed: t.completed || false,
                            disabled: t.disabled || false
                        };
                    });
                }
            } catch (e) {
                cmaLog.warn('cma-tabs: Invalid tabs JSON', e);
            }
        }

        // Fall back to parsing tab-item children if no tabs attribute
        if (this._tabs.length === 0) {
            this._parseTabItems();
        }

        // Don't mark as initialized if we found 0 tabs from child parsing —
        // children may not be available yet, keep waiting for MutationObserver
        if (this._tabs.length === 0 && !this.getAttribute('tabs')) {
            this._setupChildObserver();
            return;
        }

        this._render();
        this._setupResponsive();
        this._updateContentPanels();
        this._initialized = true;
        // Disconnect observer if it was set up
        if (this._childObserver) {
            this._childObserver.disconnect();
            this._childObserver = null;
        }
    }

    _setupChildObserver() {
        if (this._childObserver) return;
        this._childObserver = new MutationObserver((mutations) => {
            if (!this._initialized && this.children.length > 0) {
                this._initializeTabs();
            }
        });
        this._childObserver.observe(this, { childList: true });
    }

    disconnectedCallback() {
        if (this._resizeObserver) {
            this._resizeObserver.disconnect();
        }
        if (this._resizeHandler) {
            window.removeEventListener('resize', this._resizeHandler);
            this._resizeHandler = null;
        }
        if (this._childObserver) {
            this._childObserver.disconnect();
            this._childObserver = null;
        }
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;

        if (name === 'selected') {
            const index = parseInt(newValue, 10);
            if (!isNaN(index) && index !== this._selectedIndex) {
                this.selectTab(index, false);
            }
        } else if (name === 'mode') {
            this._mode = newValue || 'default';
            if (this._initialized) {
                this._render();
                this._updateContentPanels();
            }
        } else if (name === 'tabs' && newValue) {
            // Parse tabs from JSON attribute
            try {
                const tabsData = JSON.parse(newValue);
                if (Array.isArray(tabsData)) {
                    this._tabs = tabsData.map((t, i) => {
                        if (typeof t === 'string') {
                            return { title: t, id: String(i), count: null, beheer: false, completed: false, disabled: false };
                        }
                        return {
                            title: t.title || `Tab ${i + 1}`,
                            id: t.id || String(i),
                            count: t.count ?? null,
                            beheer: t.beheer || false,
                            completed: t.completed || false,
                            disabled: t.disabled || false
                        };
                    });
                    if (this._initialized) {
                        this._render();
                        this._updateContentPanels();
                    }
                }
            } catch (e) {
                cmaLog.warn('cma-tabs: Invalid tabs JSON', e);
            }
        }
    }

    get breakpoint() {
        return parseInt(this.getAttribute('breakpoint'), 10) || 500;
    }

    get selectedIndex() {
        return this._selectedIndex;
    }

    get tabs() {
        return [...this._tabs];
    }

    /**
     * Set tabs programmatically
     * @param {Array} tabs - [{ title, id, count, beheer, completed }]
     */
    setTabs(tabs) {
        this._tabs = tabs.map((t, i) => ({
            title: t.title || `Tab ${i + 1}`,
            id: t.id || String(i),
            count: t.count ?? null,
            beheer: t.beheer || false,
            completed: t.completed || false,
            disabled: t.disabled || false
        }));
        this._render();
        this.selectTab(0, false);
    }

    /**
     * Get current mode
     * @returns {string} "default" or "wizard"
     */
    get mode() {
        return this._mode;
    }

    /**
     * Set mode programmatically
     * @param {string} value - "default" or "wizard"
     */
    set mode(value) {
        this._mode = value || 'default';
        this.setAttribute('mode', this._mode);
        if (this._initialized) {
            this._render();
            this._updateContentPanels();
        }
    }

    /**
     * Mark a step as completed (wizard mode)
     * @param {number} index - Step index
     * @param {boolean} completed - Whether the step is completed
     */
    setStepCompleted(index, completed = true) {
        if (index >= 0 && index < this._tabs.length) {
            this._tabs[index].completed = completed;
            this._updateStepIndicator(index);
        }
    }

    /**
     * Check if a step is completed (wizard mode)
     * @param {number} index - Step index
     * @returns {boolean}
     */
    isStepCompleted(index) {
        if (index >= 0 && index < this._tabs.length) {
            return this._tabs[index].completed || false;
        }
        return false;
    }

    /**
     * Set step hidden state and re-render (wizard mode)
     * This allows dynamic hiding of steps and proper step number recalculation
     * @param {number} index - Step index
     * @param {boolean} hidden - Whether the step should be hidden
     */
    setStepHidden(index, hidden = true) {
        if (index >= 0 && index < this._tabs.length) {
            this._tabs[index].hidden = hidden;
            // Re-render to recalculate step numbers
            if (this._mode === 'wizard') {
                this._renderWizard();
            }
        }
    }

    /**
     * Navigate to next step (wizard mode)
     * Skips hidden steps automatically
     * @param {boolean} markCurrentComplete - Mark current step as completed before advancing
     * @returns {boolean} True if navigation succeeded
     */
    nextStep(markCurrentComplete = true) {
        // Find next non-hidden tab
        let nextIndex = this._selectedIndex + 1;
        while (nextIndex < this._tabs.length && this._tabs[nextIndex].hidden) {
            nextIndex++;
        }

        if (nextIndex < this._tabs.length) {
            if (markCurrentComplete) {
                this.setStepCompleted(this._selectedIndex, true);
            }
            this.selectTab(nextIndex);
            return true;
        }
        return false;
    }

    /**
     * Navigate to previous step (wizard mode)
     * Skips hidden steps automatically
     * @returns {boolean} True if navigation succeeded
     */
    prevStep() {
        // Find previous non-hidden tab
        let prevIndex = this._selectedIndex - 1;
        while (prevIndex >= 0 && this._tabs[prevIndex].hidden) {
            prevIndex--;
        }

        if (prevIndex >= 0) {
            this.selectTab(prevIndex);
            return true;
        }
        return false;
    }

    /**
     * Update count badge for a tab
     * @param {number} index - Tab index
     * @param {number|string|null} count - Count value
     */
    setCount(index, count) {
        if (index >= 0 && index < this._tabs.length) {
            this._tabs[index].count = count;
            this._updateCountBadge(index, count);
        }
    }

    /**
     * Select a tab
     * @param {number} index - Tab index
     * @param {boolean} emit - Whether to emit event (default: true)
     */
    selectTab(index, emit = true) {
        if (index < 0 || index >= this._tabs.length) return;

        // Ignore if already selected
        if (index === this._selectedIndex) return;

        const previousIndex = this._selectedIndex;

        // Dispatch cancelable beforechange event
        if (emit) {
            const beforeEvent = new CustomEvent('beforechange', {
                bubbles: true,
                cancelable: true,
                detail: {
                    fromIndex: previousIndex,
                    toIndex: index,
                    fromTab: this._tabs[previousIndex],
                    toTab: this._tabs[index]
                }
            });
            if (!this.dispatchEvent(beforeEvent)) {
                // Event was canceled, don't change tab
                return;
            }
        }

        this._selectedIndex = index;
        this.setAttribute('selected', index);

        if (this._mode === 'wizard') {
            // Update wizard step visual state
            this._updateWizardSteps(previousIndex, index);
        } else {
            // Update tab visual state
            const tabList = this.shadowRoot.querySelector('.tabs-list');
            if (tabList) {
                tabList.querySelectorAll('li').forEach((li, i) => {
                    li.classList.toggle('selected', i === index);
                });
            }
        }

        // Update select value (both modes)
        const select = this.shadowRoot.querySelector('select');
        if (select) {
            select.value = index;
        }

        // Update content panels (slotted content)
        this._updateContentPanels();

        // Emit event
        if (emit) {
            const tab = this._tabs[index];
            this.dispatchEvent(new CustomEvent('tab-select', {
                bubbles: true,
                detail: {
                    index,
                    id: tab.id,
                    title: tab.title
                }
            }));

            // Also emit step-change event in wizard mode
            if (this._mode === 'wizard') {
                this.dispatchEvent(new CustomEvent('step-change', {
                    bubbles: true,
                    detail: {
                        index,
                        previousIndex,
                        title: tab.title,
                        completed: tab.completed,
                        isFirst: index === 0,
                        isLast: index === this._tabs.length - 1
                    }
                }));
            }
        }
    }

    /**
     * Update wizard step visual states
     * @param {number} previousIndex - Previous step index
     * @param {number} currentIndex - Current step index
     */
    _updateWizardSteps(previousIndex, currentIndex) {
        const steps = this.shadowRoot.querySelectorAll('.wizard-step');
        steps.forEach((step, i) => {
            const isCurrent = i === currentIndex;
            const isCompleted = this._tabs[i].completed;
            const isPast = i < currentIndex && !isCompleted;

            step.classList.toggle('current', isCurrent);
            step.classList.toggle('completed', isCompleted);
            step.classList.toggle('past', isPast);
        });
    }

    /**
     * Show/hide slotted content panels based on selected tab
     * Panels should have slot="tab-0", slot="tab-1", etc.
     * @param {boolean} animate - Whether to animate the transition (default: true after first render)
     */
    _updateContentPanels(animate) {
        const slots = this.shadowRoot.querySelectorAll('slot[name^="tab-"]');
        const shouldAnimate = animate !== false && this._initialized;

        if (!shouldAnimate) {
            // No animation: instant switch (initial render)
            slots.forEach(slot => {
                const panelIndex = parseInt(slot.getAttribute('name').replace('tab-', ''), 10);
                slot.style.display = panelIndex === this._selectedIndex ? '' : 'none';
                slot.classList.remove('tab-fade-in', 'tab-fade-out');
            });
            return;
        }

        // Find the currently visible (old) slot and the new target slot
        let oldSlot = null;
        let newSlot = null;

        slots.forEach(slot => {
            const panelIndex = parseInt(slot.getAttribute('name').replace('tab-', ''), 10);
            if (slot.style.display !== 'none' && panelIndex !== this._selectedIndex) {
                oldSlot = slot;
            }
            if (panelIndex === this._selectedIndex) {
                newSlot = slot;
            }
        });

        // If there's no old slot visible, just show the new one
        if (!oldSlot || !newSlot) {
            slots.forEach(slot => {
                const panelIndex = parseInt(slot.getAttribute('name').replace('tab-', ''), 10);
                slot.style.display = panelIndex === this._selectedIndex ? '' : 'none';
                slot.classList.remove('tab-fade-in', 'tab-fade-out');
            });
            return;
        }

        // Animate: fade out old, then fade in new
        oldSlot.classList.remove('tab-fade-in');
        oldSlot.classList.add('tab-fade-out');

        const onFadeOutEnd = () => {
            oldSlot.removeEventListener('animationend', onFadeOutEnd);
            oldSlot.classList.remove('tab-fade-out');
            oldSlot.style.display = 'none';

            // Show and fade in new slot
            newSlot.style.display = '';
            newSlot.classList.add('tab-fade-in');

            const onFadeInEnd = () => {
                newSlot.removeEventListener('animationend', onFadeInEnd);
                newSlot.classList.remove('tab-fade-in');
            };
            newSlot.addEventListener('animationend', onFadeInEnd, { once: true });
        };
        oldSlot.addEventListener('animationend', onFadeOutEnd, { once: true });
    }

    _parseTabItems() {
        this._tabs = [];
        const items = this.querySelectorAll('tab-item');
        items.forEach((item, i) => {
            this._tabs.push({
                title: item.getAttribute('title') || `Tab ${i + 1}`,
                id: item.dataset.id || String(i),
                count: item.dataset.count ?? null,
                beheer: item.hasAttribute('beheer'),
                completed: item.hasAttribute('completed') || item.dataset.completed === 'true',
                disabled: item.hasAttribute('disabled') || item.dataset.disabled === 'true'
            });
        });

        // Initial selection
        const selected = parseInt(this.getAttribute('selected'), 10);
        if (!isNaN(selected)) {
            this._selectedIndex = selected;
        }
    }

    _render() {
        // Add single-tab class when there's only one tab (no pointer cursor needed)
        this.classList.toggle('single-tab', this._tabs.length <= 1);

        // Render based on mode
        if (this._mode === 'wizard') {
            this._renderWizard();
        } else {
            this._renderDefault();
        }
    }

    _renderDefault() {
        this.shadowRoot.innerHTML = `
            <style>
                ${this._getBaseStyles()}
                ${this._getDefaultTabStyles()}
            </style>
            <div class="tabs-container">
                <div class="scroll-arrow left" title="Scroll links"></div>
                <div class="scroll-arrow right" title="Scroll rechts"></div>
                <ul class="tabs-list">
                    ${this._tabs.map((tab, i) => `
                        <li class="${i === this._selectedIndex ? 'selected' : ''}" data-index="${i}">
                            <a href="javascript:void(0)">
                                <span class="tab-title">${this._escapeHtml(tab.title)}</span>
                                ${tab.beheer ? '<span class="tab-beheer" title="beheer"></span>' : ''}
                                <span class="tab-count${tab.count === null || tab.count === '.' ? ' loading' : (tab.count === 0 || tab.count === '0') ? ' empty' : ''}">${tab.count ?? '.'}</span>
                            </a>
                        </li>
                    `).join('')}
                </ul>
                <select class="tabs-select">
                    ${this._tabs.map((tab, i) => `
                        <option value="${i}" ${i === this._selectedIndex ? 'selected' : ''}>
                            ${this._escapeHtml(tab.title)} (${tab.count ?? '.'})
                        </option>
                    `).join('')}
                </select>
            </div>
            <div class="tabs-content">
                ${this._tabs.map((tab, i) => `<slot name="tab-${i}" ${i === this._selectedIndex ? '' : 'style="display:none"'}></slot>`).join('')}
            </div>
        `;

        this._bindDefaultEvents();
    }

    _renderWizard() {
        // Calculate visible step numbers - only count non-hidden steps
        let visibleStepNumber = 0;
        const stepNumbers = this._tabs.map((tab) => {
            if (tab.hidden) return 0;
            return ++visibleStepNumber;
        });
        const totalVisibleSteps = visibleStepNumber;

        this.shadowRoot.innerHTML = `
            <style>
                ${this._getBaseStyles()}
                ${this._getWizardStyles()}
            </style>
            <div class="wizard-container">
                <div class="wizard-steps">
                    ${this._tabs.map((tab, i) => {
                        const isSelected = i === this._selectedIndex;
                        const isCompleted = tab.completed;
                        const isPast = i < this._selectedIndex;
                        const isHidden = tab.hidden;
                        const classes = [
                            'wizard-step',
                            isSelected ? 'current' : '',
                            isCompleted ? 'completed' : '',
                            isPast ? 'past' : ''
                        ].filter(Boolean).join(' ');
                        const stepNum = stepNumbers[i];
                        const isLast = stepNum === totalVisibleSteps;

                        const tooltipAttr = tab.tooltip ? `data-tooltip="${this._escapeHtml(tab.tooltip)}" data-tooltip-pos="top"` : '';
                        return `
                            <div class="${classes}" data-index="${i}" data-step="${stepNum}" ${tooltipAttr} ${isHidden ? 'style="display:none"' : ''}>
                                <div class="step-indicator">
                                    ${isCompleted ? '<span class="checkmark">✓</span>' : `<span class="step-number">${stepNum}</span>`}
                                </div>
                                <div class="step-label">${this._escapeHtml(tab.title)}</div>
                                ${isLast ? '' : '<div class="step-connector"></div>'}
                            </div>
                        `;
                    }).join('')}
                </div>
                <select class="wizard-select">
                    ${this._tabs.map((tab, i) => tab.hidden ? '' : `<option value="${i}" ${i === this._selectedIndex ? 'selected' : ''}>Stap ${stepNumbers[i]}: ${this._escapeHtml(tab.title)}</option>`).join('')}
                </select>
            </div>
            <div class="tabs-content wizard-content">
                ${this._tabs.map((tab, i) => `<slot name="tab-${i}" ${i === this._selectedIndex ? '' : 'style="display:none"'}></slot>`).join('')}
            </div>
        `;

        this._bindWizardEvents();
    }

    _getBaseStyles() {
        return `
            :host {
                display: flex;
                flex-direction: column;
                flex: 1;
                min-height: 0;
                overflow: hidden;
            }

            /* Wizard mode has the same flex setup */
            :host([mode="wizard"]) {
                display: flex;
                flex-direction: column;
                flex: 1;
                min-height: 0;
            }

            /* Content area for slotted panels */
            .tabs-content {
                background: var(--bg-disabled, #f5f5f5);
                flex: 1;
                display: flex;
                flex-direction: column;
                min-height: 0;
                overflow: hidden;
            }

            /* Slots need proper height constraints */
            .tabs-content slot {
                display: flex;
                flex-direction: column;
                flex: 1;
                min-height: 0;
                overflow: hidden;
            }

            /* Slotted content should fill available space */
            .tabs-content ::slotted(*) {
                flex: 1;
                min-height: 0;
                overflow: auto;
            }

            /* Tab content transition animations */
            .tabs-content slot {
                opacity: 1;
            }

            .tabs-content slot.tab-fade-out {
                animation: tabFadeOut 0.15s ease-out forwards;
            }

            .tabs-content slot.tab-fade-in {
                animation: tabFadeIn 0.15s ease-in forwards;
            }

            @keyframes tabFadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }

            @keyframes tabFadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            /* Select (mobile) - shared base */
            .tabs-select, .wizard-select {
                display: none;
                width: 100%;
                padding: 8px 12px;
                font-size: var(--font-size-md);
                border: 1px solid var(--input-border, #ccc);
                border-radius: 4px;
                background: var(--input-bg, white);
                color: var(--text-primary, #333);
                cursor: pointer;
            }

            /* Responsive: show select on mobile */
            :host(.mobile-mode) .tabs-list,
            :host(.mobile-mode) .wizard-steps {
                display: none;
            }

            :host(.mobile-mode) .tabs-select,
            :host(.mobile-mode) .wizard-select {
                display: block;
            }
        `;
    }

    _getDefaultTabStyles() {
        return `
            .tabs-container {
                position: relative;
                flex-shrink: 0;
            }

            /* Chrome-style tab bar */
            .tabs-list {
                display: flex;
                list-style: none;
                margin: 0;
                padding: 0 8px;
                padding-top: 4px;
                height: auto;
                gap: 0;
                overflow-x: hidden;
                scrollbar-width: none;
                background: var(--tab-bar-bg, #dee1e6);
                position: relative;
                scroll-behavior: smooth;
            }

            .tabs-list::-webkit-scrollbar {
                display: none;
            }

            /* Scroll arrows */
            .scroll-arrow {
                position: absolute;
                top: 0;
                bottom: 0;
                width: 28px;
                display: none;
                align-items: center;
                justify-content: center;
                background: linear-gradient(to right, var(--tab-bar-bg, #dee1e6) 60%, transparent);
                cursor: pointer;
                z-index: 10;
                opacity: 0.8;
                transition: opacity 0.15s;
            }

            .scroll-arrow:hover {
                opacity: 1;
            }

            .scroll-arrow.left {
                left: 0;
                background: linear-gradient(to right, var(--tab-bar-bg, #dee1e6) 60%, transparent);
            }

            .scroll-arrow.right {
                right: 0;
                background: linear-gradient(to left, var(--tab-bar-bg, #dee1e6) 60%, transparent);
            }

            .scroll-arrow.visible {
                display: flex;
            }

            .scroll-arrow::before {
                content: '';
                width: 8px;
                height: 8px;
                border-right: 2px solid var(--color-info, #077ab2);
                border-bottom: 2px solid var(--color-info, #077ab2);
            }

            .scroll-arrow.left::before {
                transform: rotate(135deg);
                margin-left: 4px;
            }

            .scroll-arrow.right::before {
                transform: rotate(-45deg);
                margin-right: 4px;
            }

            /* Tab item - Chrome style with curves */
            .tabs-list li {
                flex-shrink: 0;
                position: relative;
                margin-right: -14px;
                z-index: 1;
            }

            .tabs-list li:hover {
                z-index: 2;
            }

            .tabs-list li.selected {
                z-index: 3;
            }

            /* Tab shape using SVG background */
            .tabs-list li a {
                display: flex;
                align-items: center;
                gap: 6px;
                padding-left: 18px;
                padding-right: 18px;
                margin-left: 14px;
                text-decoration: none;
                color: var(--text-primary);
                font-size: var(--font-size-sm);
                white-space: nowrap;
                cursor: pointer;
                position: relative;
                background: transparent;
                border-radius: 8px 8px 0 0;
                transition: background 0.15s, color 0.15s;
                min-height: 28px;
            }

            /* Left curve */
            .tabs-list li a::before {
                content: '';
                position: absolute;
                left: -14px;
                bottom: 0;
                width: 14px;
                height: 100%;
                background: transparent;
                clip-path: path('M14,0 C14,0 14,28 0,28 L14,28 L14,0 Z');
                transition: background 0.15s;
            }

            /* Right curve */
            .tabs-list li a::after {
                content: '';
                position: absolute;
                right: -14px;
                bottom: 0;
                width: 14px;
                height: 100%;
                background: transparent;
                clip-path: path('M0,0 C0,0 0,28 14,28 L0,28 L0,0 Z');
                transition: background 0.15s;
            }

            /* Hover state */
            .tabs-list li:not(.selected) a:hover {
                background: var(--tab-hover-bg, #bbc2ca);
            }

            .tabs-list li:not(.selected) a:hover::before,
            .tabs-list li:not(.selected) a:hover::after {
                background: var(--tab-hover-bg, #bbc2ca);
            }

            /* Selected tab */
            .tabs-list li.selected a {
                background-color: var(--bg-disabled);
                color: var(--text-primary, #202124);
                font-weight: normal;
            }

            /* Single tab - no pointer cursor since there's nothing to switch to */
            :host(.single-tab) .tabs-list li a {
                cursor: default;
            }

            .tabs-list li.selected a::before,
            .tabs-list li.selected a::after {
                background: var(--bg-disabled);
            }

            /* Count badge */
            .tab-count {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 16px;
                height: 16px;
                padding: 0;
                margin-left: -2px;
                font-size: 8px;
                font-weight: 500;
                background-color: transparent;
                color: var(--badge-text, #5f6368);
                border-radius: 8px;
                border: 1px solid var(--text-secondary, #888);
            }

            .tabs-list li.selected .tab-count {
                background-color: var(--color-info, #077ab2);
                border-color: var(--color-info, #077ab2);
                color: white;
            }

            .tab-count.empty {
                visibility: hidden;
            }

            .tab-count.loading {
                animation: pulse 1s infinite;
            }

            /* Hide loading placeholder by default, only show for subform tabs */
            :host(:not(#subformTabs)) .tab-count.loading {
                display: none;
            }

            @keyframes pulse {
                0%, 100% { opacity: 0.4; }
                50% { opacity: 1; }
            }

            /* Beheer indicator */
            .tab-beheer {
                width: 6px;
                height: 6px;
                background: var(--color-warning, #f0ad4e);
                border-radius: 50%;
                margin-left: 2px;
            }

            /* Dark mode - triggered by html.dark-mode class */
            :host-context(html.dark-mode) .tabs-list {
                background: var(--tab-bar-bg, #292b2e);
                border-bottom-color: var(--border-color, #3c4043);
            }

            :host-context(html.dark-mode) .tabs-list li a {
                background: var(--bg-surface-alt, #35363a);
                color: var(--text-secondary, #9aa0a6);
            }

            :host-context(html.dark-mode) .tabs-list li a::before,
            :host-context(html.dark-mode) .tabs-list li a::after {
                background: var(--bg-surface-alt, #35363a);
            }

            :host-context(html.dark-mode) .tabs-list li:not(.selected) a:hover {
                background: var(--bg-hover, #3c3d41);
                color: var(--text-primary, #e8eaed);
            }

            :host-context(html.dark-mode) .tabs-list li:not(.selected) a:hover::before,
            :host-context(html.dark-mode) .tabs-list li:not(.selected) a:hover::after {
                background: var(--bg-hover, #3c3d41);
            }

            :host-context(html.dark-mode) .tabs-list li.selected a {
                background: var(--bg-surface, #202124);
                color: var(--text-primary, #e8eaed);
            }

            :host-context(html.dark-mode) .tabs-list li.selected a::before,
            :host-context(html.dark-mode) .tabs-list li.selected a::after {
                background: var(--bg-surface, #202124);
            }

            :host-context(html.dark-mode) .tab-count {
                background: rgba(255,255,255,0.1);
                color: var(--text-secondary, #9aa0a6);
            }

            :host-context(html.dark-mode) .scroll-arrow.left {
                background: linear-gradient(to right, var(--tab-bar-bg, #292b2e) 60%, transparent);
            }

            :host-context(html.dark-mode) .scroll-arrow.right {
                background: linear-gradient(to left, var(--tab-bar-bg, #292b2e) 60%, transparent);
            }

            :host-context(html.dark-mode) .scroll-arrow::before {
                border-color: var(--color-info, #077ab2);
            }
        `;
    }

    _getWizardStyles() {
        return `
            .wizard-container {
                background: var(--bg-surface, #f8f9fa);
                border-bottom: 1px solid var(--border-color, #dee2e6);
                padding: 0;
                flex-shrink: 0;
            }

            .wizard-steps {
                display: flex;
                align-items: flex-start;
                justify-content: center;
                gap: 0;
                flex-wrap: nowrap;
                overflow-x: auto;
                scrollbar-width: none;
                padding-top: 12px;
                padding-bottom: 12px;
            }

            .wizard-steps::-webkit-scrollbar {
                display: none;
            }

            .wizard-step {
                display: flex;
                flex-direction: column;
                align-items: center;
                position: relative;
                cursor: pointer;
                min-width: 80px;
                flex-shrink: 0;
            }

            .wizard-step:hover .step-indicator {
                transform: scale(1.1);
                border-color: var(--color-primary, #077ab2);
                background: var(--bg-highlight, #e8f4fc);
            }

            /* Step indicator circle */
            .step-indicator {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: var(--font-size-md);
                font-weight: 600;
                background: var(--bg-surface, white);
                border: 2px solid var(--border-color, #ced4da);
                color: var(--text-muted, #6c757d);
                transition: all 0.2s ease;
                position: relative;
                z-index: 2;
            }

            /* Current step */
            .wizard-step.current .step-indicator {
                background: var(--color-primary, #077ab2);
                border-color: var(--color-primary, #077ab2);
                color: white;
                box-shadow: 0 0 0 4px rgba(7, 122, 178, 0.2);
            }

            /* Completed step */
            .wizard-step.completed .step-indicator {
                background: var(--color-success, #28a745);
                border-color: var(--color-success, #28a745);
                color: white;
            }

            .wizard-step.completed .checkmark {
                font-size: var(--font-size-lg);
                line-height: 1;
            }

            /* Past step (visited but not completed) */
            .wizard-step.past:not(.completed) .step-indicator {
                border-color: var(--color-primary, #077ab2);
                color: var(--color-primary, #077ab2);
            }

            /* Step label */
            .step-label {
                margin-top: 8px;
                font-size: var(--font-size-sm);
                color: var(--text-muted, #6c757d);
                text-align: center;
                white-space: nowrap;
                max-width: 100px;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .wizard-step.current .step-label {
                color: var(--color-primary, #077ab2);
                font-weight: 600;
            }

            .wizard-step.completed .step-label {
                color: var(--color-success, #28a745);
            }

            /* Connector line between steps */
            .step-connector {
                position: absolute;
                top: 15px;
                left: calc(50% + 20px);
                width: calc(100% - 40px);
                height: 2px;
                background: var(--border-color, #ced4da);
                z-index: 1;
            }

            .wizard-step.completed .step-connector,
            .wizard-step.current .step-connector {
                background: var(--color-primary, #077ab2);
            }

            /* Wizard content area */
            .wizard-content {
                padding: 0;
                background: var(--bg-surface, white);
                flex: 1;
                overflow: hidden;
                min-height: 0;
                display: flex;
                flex-direction: column;
            }

            /* Slots need to be flex containers for slotted content */
            .wizard-content slot {
                display: flex;
                flex-direction: column;
                flex: 1;
                min-height: 0;
                overflow: hidden;
            }

            /* Slotted content should fill the slot */
            .wizard-content ::slotted(*) {
                flex: 1;
                min-height: 0;
                overflow: auto;
            }

            /* Dark mode */
            :host-context(html.dark-mode) .wizard-container {
                background: var(--bg-surface, #1e1e1e);
                border-bottom-color: var(--border-color, #3c4043);
            }

            :host-context(html.dark-mode) .step-indicator {
                background: var(--bg-surface, #2d2d2d);
                border-color: var(--border-color, #4a4a4a);
                color: var(--text-muted, #9aa0a6);
            }

            :host-context(html.dark-mode) .wizard-step.current .step-indicator {
                background: var(--color-primary, #077ab2);
                border-color: var(--color-primary, #077ab2);
                color: white;
            }

            :host-context(html.dark-mode) .wizard-step.completed .step-indicator {
                background: var(--color-success, #28a745);
                border-color: var(--color-success, #28a745);
            }

            :host-context(html.dark-mode) .step-label {
                color: var(--text-muted, #9aa0a6);
            }

            :host-context(html.dark-mode) .step-connector {
                background: var(--border-color, #4a4a4a);
            }

            :host-context(html.dark-mode) .wizard-content {
                background: var(--bg-surface, #1e1e1e);
            }
        `;
    }

    _bindDefaultEvents() {
        // Bind events
        const tabsList = this.shadowRoot.querySelector('.tabs-list');
        if (tabsList) {
            tabsList.addEventListener('click', (e) => {
                const li = e.target.closest('li');
                if (li) {
                    e.preventDefault();
                    this.selectTab(parseInt(li.dataset.index, 10));
                }
            });
        }

        const select = this.shadowRoot.querySelector('.tabs-select');
        if (select) {
            select.addEventListener('change', (e) => {
                this.selectTab(parseInt(e.target.value, 10));
            });
        }

        // Scroll arrow events
        const leftArrow = this.shadowRoot.querySelector('.scroll-arrow.left');
        const rightArrow = this.shadowRoot.querySelector('.scroll-arrow.right');

        if (leftArrow && tabsList) {
            leftArrow.addEventListener('click', () => {
                tabsList.scrollBy({ left: -150, behavior: 'smooth' });
            });
        }

        if (rightArrow && tabsList) {
            rightArrow.addEventListener('click', () => {
                tabsList.scrollBy({ left: 150, behavior: 'smooth' });
            });
        }

        // Update arrow visibility on scroll
        if (tabsList) {
            tabsList.addEventListener('scroll', () => this._updateScrollArrows());
        }

        // Initial arrow check after render
        requestAnimationFrame(() => this._updateScrollArrows());
    }

    _bindWizardEvents() {
        // Click on wizard steps
        const steps = this.shadowRoot.querySelectorAll('.wizard-step');
        steps.forEach(step => {
            step.addEventListener('click', () => {
                const index = parseInt(step.dataset.index, 10);
                this.selectTab(index);
            });
        });

        // Select dropdown
        const select = this.shadowRoot.querySelector('.wizard-select');
        if (select) {
            select.addEventListener('change', (e) => {
                this.selectTab(parseInt(e.target.value, 10));
            });
        }
    }

    /**
     * Update step indicator visual state (wizard mode)
     * @param {number} index - Step index
     */
    _updateStepIndicator(index) {
        if (this._mode !== 'wizard') return;

        const step = this.shadowRoot.querySelector(`.wizard-step[data-index="${index}"]`);
        if (!step) return;

        const tab = this._tabs[index];
        const indicator = step.querySelector('.step-indicator');

        // Update completed state
        step.classList.toggle('completed', tab.completed);

        // Update indicator content - use data-step for correct numbering
        const stepNum = step.dataset.step || (index + 1);
        if (tab.completed) {
            indicator.innerHTML = '<span class="checkmark">✓</span>';
        } else {
            indicator.innerHTML = `<span class="step-number">${stepNum}</span>`;
        }
    }

    _updateScrollArrows() {
        const tabsList = this.shadowRoot.querySelector('.tabs-list');
        const leftArrow = this.shadowRoot.querySelector('.scroll-arrow.left');
        const rightArrow = this.shadowRoot.querySelector('.scroll-arrow.right');

        if (!tabsList || !leftArrow || !rightArrow) return;

        const scrollLeft = tabsList.scrollLeft;
        const scrollWidth = tabsList.scrollWidth;
        const clientWidth = tabsList.clientWidth;
        const hasOverflow = scrollWidth > clientWidth + 5; // 5px tolerance

        // Show/hide left arrow
        leftArrow.classList.toggle('visible', hasOverflow && scrollLeft > 5);

        // Show/hide right arrow
        rightArrow.classList.toggle('visible', hasOverflow && scrollLeft < scrollWidth - clientWidth - 5);
    }

    _setupResponsive() {
        // Check mobile mode on initial load and resize
        this._checkMobileMode();

        // Update scroll arrows and mobile mode when component resizes
        if (typeof ResizeObserver !== 'undefined') {
            this._resizeObserver = new ResizeObserver(() => {
                this._updateScrollArrows();
                this._checkMobileMode();
            });
            this._resizeObserver.observe(this);
        } else {
            this._resizeHandler = () => {
                this._updateScrollArrows();
                this._checkMobileMode();
            };
            window.addEventListener('resize', this._resizeHandler);
        }
    }

    _checkMobileMode() {
        const breakpoint = this.breakpoint;
        if (window.innerWidth < breakpoint) {
            this.classList.add('mobile-mode');
        } else {
            this.classList.remove('mobile-mode');
        }
    }

    _updateCountBadge(index, count) {
        const li = this.shadowRoot.querySelector(`li[data-index="${index}"]`);
        if (li) {
            let badge = li.querySelector('.tab-count');
            if (count !== null) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'tab-count';
                    li.querySelector('a').appendChild(badge);
                }
                badge.textContent = count;
                badge.classList.toggle('empty', count === 0 || count === '0');
                badge.classList.remove('loading');
            } else if (badge) {
                badge.remove();
            }
        }

        // Also update select option
        const option = this.shadowRoot.querySelector(`option[value="${index}"]`);
        if (option) {
            const tab = this._tabs[index];
            option.textContent = tab.title + (count !== null ? ` (${count})` : '');
        }
    }

    _escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

// Register the component
customElements.define('cma-tabs', CmaTabs);

} // end guard
