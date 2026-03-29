/**
 * Responsive Tabs Web Component
 *
 * A web component that displays tabs on larger screens and switches to a
 * select dropdown on smaller screens.
 *
 * Usage:
 *   <responsive-tabs breakpoint="600">
 *       <tab-item title="Tab 1" data-count="5">Content 1</tab-item>
 *       <tab-item title="Tab 2">Content 2</tab-item>
 *   </responsive-tabs>
 *
 * Or programmatically:
 *   const tabs = document.querySelector('responsive-tabs');
 *   tabs.addTab({ title: 'New Tab', content: 'Content' });
 *   tabs.selectTab(0);
 *
 * Attributes:
 *   - breakpoint: Viewport width (px) below which select mode is used (default: 600)
 *   - selected: Index of initially selected tab (default: 0)
 *   - mode: Force 'tabs' or 'select' mode (default: auto)
 *
 * Events:
 *   - tab-change: Fired when tab selection changes. Detail: { index, title, previousIndex }
 *   - tab-before-change: Fired before tab changes. Call event.preventDefault() to cancel.
 */

// Guard against double registration
if (typeof ResponsiveTabs === 'undefined') {

class ResponsiveTabs extends HTMLElement {
    static get observedAttributes() {
        return ['breakpoint', 'selected', 'mode'];
    }

    constructor() {
        super();
        this._selectedIndex = 0;
        this._tabs = [];
        this._initialized = false;
        this._resizeObserver = null;
    }

    connectedCallback() {
        if (!this._initialized) {
            this._initialize();
            this._initialized = true;
        }
    }

    disconnectedCallback() {
        if (this._resizeObserver) {
            this._resizeObserver.disconnect();
        }
        window.removeEventListener('resize', this._handleResize);
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;

        switch (name) {
            case 'breakpoint':
                this._updateResponsiveMode();
                break;
            case 'selected':
                const index = parseInt(newValue, 10);
                if (!isNaN(index) && index !== this._selectedIndex) {
                    this.selectTab(index, false);
                }
                break;
            case 'mode':
                this._updateForcedMode(newValue);
                break;
        }
    }

    get breakpoint() {
        return parseInt(this.getAttribute('breakpoint'), 10) || 600;
    }

    set breakpoint(value) {
        this.setAttribute('breakpoint', value);
    }

    get selectedIndex() {
        return this._selectedIndex;
    }

    set selectedIndex(index) {
        this.selectTab(index);
    }

    get tabs() {
        return [...this._tabs];
    }

    _initialize() {
        // Parse tab-item children
        this._parseTabItems();

        // Build the DOM structure
        this._render();

        // Set up responsive behavior
        this._setupResponsive();

        // Select initial tab
        const initialIndex = parseInt(this.getAttribute('selected'), 10) || 0;
        this.selectTab(initialIndex, false);
    }

    _parseTabItems() {
        this._tabs = [];
        const tabItems = this.querySelectorAll('tab-item');

        tabItems.forEach((item, index) => {
            this._tabs.push({
                title: item.getAttribute('title') || `Tab ${index + 1}`,
                content: item.innerHTML,
                count: item.getAttribute('data-count') || null,
                data: { ...item.dataset }
            });
        });

        // If no tab-items found, look for existing ul/li structure
        if (this._tabs.length === 0) {
            const existingList = this.querySelector('ul');
            if (existingList) {
                const items = existingList.querySelectorAll('li:not(.shadow)');
                items.forEach((li, index) => {
                    const link = li.querySelector('a');
                    const countEl = li.querySelector('.tab-count, .badge');
                    this._tabs.push({
                        title: link ? link.textContent.replace(/\s*\d+$/, '').trim() : li.textContent.trim(),
                        content: null, // Content managed externally
                        count: countEl ? countEl.textContent.trim() : (li.dataset.count || null),
                        data: { tabIndex: index, ...li.dataset },
                        href: link ? link.getAttribute('href') : null
                    });
                });
            }
        }
    }

    _render() {
        // Store original content reference
        const originalContent = this.innerHTML;

        // Create wrapper
        this.classList.add('responsive-tabs');

        // Build tabs container
        const tabsContainer = document.createElement('ul');
        tabsContainer.className = 'tabs-container';
        tabsContainer.id = this.id ? `${this.id}_tabs` : 'glow_tabs';

        this._tabs.forEach((tab, index) => {
            const li = document.createElement('li');
            li.dataset.tabIndex = index;
            Object.keys(tab.data).forEach(key => {
                li.dataset[key] = tab.data[key];
            });

            const a = document.createElement('a');
            a.href = tab.href || 'javascript:void(0)';
            a.textContent = tab.title;

            if (tab.count !== null) {
                const countSpan = document.createElement('span');
                countSpan.className = 'tab-count';
                countSpan.textContent = tab.count;
                a.appendChild(countSpan);
            }

            a.addEventListener('click', (e) => {
                if (!tab.href || tab.href === 'javascript:void(0)') {
                    e.preventDefault();
                }
                this.selectTab(index);
            });

            li.appendChild(a);
            tabsContainer.appendChild(li);
        });

        // Add shadow element
        const shadow = document.createElement('li');
        shadow.className = 'shadow';
        tabsContainer.appendChild(shadow);

        // Build select container
        const selectContainer = document.createElement('div');
        selectContainer.className = 'select-container';

        const select = document.createElement('select');
        select.id = this.id ? `${this.id}_select` : 'tabs_select';
        select.setAttribute('aria-label', 'Select tab');

        this._tabs.forEach((tab, index) => {
            const option = document.createElement('option');
            option.value = index;
            option.textContent = tab.count !== null
                ? `${tab.title} (${tab.count})`
                : tab.title;
            select.appendChild(option);
        });

        select.addEventListener('change', () => {
            this.selectTab(parseInt(select.value, 10));
        });

        selectContainer.appendChild(select);

        // Build tab panes container (if content is managed internally)
        let tabPanes = null;
        if (this._tabs.some(tab => tab.content !== null)) {
            tabPanes = document.createElement('div');
            tabPanes.className = 'tab-panes';

            this._tabs.forEach((tab, index) => {
                const pane = document.createElement('div');
                pane.className = 'tab-pane';
                pane.dataset.tabIndex = index;
                pane.id = `tabpane_${index}`;
                if (tab.content) {
                    pane.innerHTML = tab.content;
                }
                tabPanes.appendChild(pane);
            });
        }

        // Clear and rebuild
        this.innerHTML = '';
        this.appendChild(tabsContainer);
        this.appendChild(selectContainer);
        if (tabPanes) {
            this.appendChild(tabPanes);
        }

        // Store references
        this._tabsContainer = tabsContainer;
        this._selectContainer = selectContainer;
        this._select = select;
        this._tabPanes = tabPanes;
    }

    _setupResponsive() {
        // Handle resize
        this._handleResize = () => this._updateResponsiveMode();

        // Use ResizeObserver if available for better performance
        if (typeof ResizeObserver !== 'undefined') {
            this._resizeObserver = new ResizeObserver(() => {
                this._updateResponsiveMode();
            });
            this._resizeObserver.observe(this);
        } else {
            window.addEventListener('resize', this._handleResize);
        }

        // Initial mode
        this._updateResponsiveMode();
    }

    _updateResponsiveMode() {
        const mode = this.getAttribute('mode');
        if (mode === 'tabs' || mode === 'select') {
            return; // Forced mode, don't auto-switch
        }

        // Check viewport width
        const width = window.innerWidth;
        const breakpoint = this.breakpoint;

        if (width <= breakpoint) {
            this.classList.add('force-select');
            this.classList.remove('force-tabs');
        } else {
            this.classList.remove('force-select');
            this.classList.remove('force-tabs');
        }
    }

    _updateForcedMode(mode) {
        this.classList.remove('force-tabs', 'force-select');
        if (mode === 'tabs') {
            this.classList.add('force-tabs');
        } else if (mode === 'select') {
            this.classList.add('force-select');
        }
    }

    /**
     * Select a tab by index
     * @param {number} index - Tab index to select
     * @param {boolean} fireEvent - Whether to fire the tab-change event (default: true)
     * @returns {boolean} True if tab was changed, false if prevented
     */
    selectTab(index, fireEvent = true) {
        if (index < 0 || index >= this._tabs.length) {
            console.warn('ResponsiveTabs: Invalid tab index', index);
            return false;
        }

        const previousIndex = this._selectedIndex;

        // Fire before-change event
        if (fireEvent && previousIndex !== index) {
            const beforeEvent = new CustomEvent('tab-before-change', {
                bubbles: true,
                cancelable: true,
                detail: {
                    index,
                    title: this._tabs[index].title,
                    previousIndex
                }
            });
            this.dispatchEvent(beforeEvent);

            if (beforeEvent.defaultPrevented) {
                return false;
            }
        }

        // Update selected state
        this._selectedIndex = index;

        // Update tabs visual state
        if (this._tabsContainer) {
            const tabItems = this._tabsContainer.querySelectorAll('li[data-tab-index]');
            tabItems.forEach((li, i) => {
                li.classList.toggle('selected', i === index);
            });
        }

        // Update select value
        if (this._select) {
            this._select.value = index;
        }

        // Update panes if managed internally
        if (this._tabPanes) {
            const panes = this._tabPanes.querySelectorAll('.tab-pane');
            panes.forEach((pane, i) => {
                pane.classList.toggle('active', i === index);
            });
        }

        // Update attribute
        this.setAttribute('selected', index);

        // Fire change event
        if (fireEvent && previousIndex !== index) {
            const changeEvent = new CustomEvent('tab-change', {
                bubbles: true,
                detail: {
                    index,
                    title: this._tabs[index].title,
                    previousIndex
                }
            });
            this.dispatchEvent(changeEvent);
        }

        return true;
    }

    /**
     * Add a new tab
     * @param {Object} tab - Tab configuration { title, content, count, data }
     * @param {number} position - Position to insert (default: end)
     */
    addTab(tab, position = -1) {
        const newTab = {
            title: tab.title || `Tab ${this._tabs.length + 1}`,
            content: tab.content || null,
            count: tab.count || null,
            data: tab.data || {}
        };

        if (position < 0 || position >= this._tabs.length) {
            this._tabs.push(newTab);
        } else {
            this._tabs.splice(position, 0, newTab);
        }

        // Re-render to include new tab
        this._render();
        this._updateResponsiveMode();

        // Re-select current tab
        this.selectTab(this._selectedIndex, false);
    }

    /**
     * Remove a tab by index
     * @param {number} index - Tab index to remove
     */
    removeTab(index) {
        if (index < 0 || index >= this._tabs.length) return;

        this._tabs.splice(index, 1);

        // Adjust selected index if needed
        if (this._selectedIndex >= this._tabs.length) {
            this._selectedIndex = Math.max(0, this._tabs.length - 1);
        }

        // Re-render
        this._render();
        this._updateResponsiveMode();

        // Re-select
        if (this._tabs.length > 0) {
            this.selectTab(this._selectedIndex, false);
        }
    }

    /**
     * Update a tab's properties
     * @param {number} index - Tab index
     * @param {Object} updates - Properties to update { title, count, content, data }
     */
    updateTab(index, updates) {
        if (index < 0 || index >= this._tabs.length) return;

        Object.assign(this._tabs[index], updates);

        // Update DOM elements
        const tabLi = this._tabsContainer?.querySelector(`li[data-tab-index="${index}"]`);
        if (tabLi) {
            const a = tabLi.querySelector('a');
            if (updates.title !== undefined && a) {
                // Preserve count span if it exists
                const countSpan = a.querySelector('.tab-count');
                a.textContent = updates.title;
                if (countSpan) a.appendChild(countSpan);
            }
            if (updates.count !== undefined) {
                let countSpan = a?.querySelector('.tab-count');
                if (updates.count !== null) {
                    if (!countSpan) {
                        countSpan = document.createElement('span');
                        countSpan.className = 'tab-count';
                        a?.appendChild(countSpan);
                    }
                    countSpan.textContent = updates.count;
                } else if (countSpan) {
                    countSpan.remove();
                }
            }
        }

        // Update select option
        const option = this._select?.querySelector(`option[value="${index}"]`);
        if (option) {
            const tab = this._tabs[index];
            option.textContent = tab.count !== null
                ? `${tab.title} (${tab.count})`
                : tab.title;
        }

        // Update pane content
        if (updates.content !== undefined && this._tabPanes) {
            const pane = this._tabPanes.querySelector(`.tab-pane[data-tab-index="${index}"]`);
            if (pane) {
                pane.innerHTML = updates.content;
            }
        }
    }

    /**
     * Get tab data by index
     * @param {number} index - Tab index
     * @returns {Object|null} Tab data or null
     */
    getTab(index) {
        return this._tabs[index] || null;
    }

    /**
     * Update tab count badge
     * @param {number} index - Tab index
     * @param {string|number|null} count - Count value or null to remove
     */
    setTabCount(index, count) {
        this.updateTab(index, { count: count !== null ? String(count) : null });
    }
}

// Define the custom element
customElements.define('responsive-tabs', ResponsiveTabs);

} // end guard

// Also export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ResponsiveTabs;
}
