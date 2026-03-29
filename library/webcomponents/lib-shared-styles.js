/**
 * Shared Styles for Web Components
 *
 * Uses adoptedStyleSheets for efficient CSS sharing across Shadow DOM components.
 * This reduces memory usage when multiple instances of components exist.
 *
 * Usage in a component:
 *   import { adoptSharedStyles, componentStyles } from './lib-shared-styles.js';
 *
 *   // In connectedCallback or constructor:
 *   adoptSharedStyles(this.shadowRoot, componentStyles.button, componentStyles.input);
 *
 * Or for components that use inline styles:
 *   // At top of component file:
 *   const sharedCSS = LibSharedStyles.getInlineCSS('base', 'button');
 *
 *   // In render():
 *   this.shadowRoot.innerHTML = `<style>${sharedCSS}</style><style>...component specific...</style>`;
 */

(function() {
    'use strict';

    // Check for adoptedStyleSheets support
    const supportsAdoptedStyleSheets = 'adoptedStyleSheets' in Document.prototype;

    // Cache for created CSSStyleSheet objects
    const styleSheetCache = new Map();

    /**
     * Base CSS with reset and CSS custom properties
     */
    const baseCSS = `
        /* Reset */
        *, *::before, *::after {
            box-sizing: border-box;
        }

        /* CSS Custom Properties
         * Colors, typography, borders, backgrounds etc. are inherited
         * from the document (:root in colors.css). Do NOT redefine them here.
         * Only define properties that are specific to web components.
         */
        :host {
            /* Font sizes (supplemental to --font-size from colors.css) */
            --font-size-2xs: 10px;
            --font-size-xs: 11px;
            --font-size-sm: 12px;
            --font-size-md: 14px;
            --font-size-lg: 16px;
            --font-size-xl: 18px;
            --font-size-2xl: 20px;
            --font-size-3xl: 24px;

            /* Spacing */
            --spacing-xs: 4px;
            --spacing-sm: 8px;
            --spacing-md: 12px;
            --spacing-lg: 16px;
            --spacing-xl: 24px;

            /* Border radius */
            --radius-sm: 3px;
            --radius-md: 4px;
            --radius-lg: 8px;

            /* Transitions */
            --transition-fast: 0.1s ease;
            --transition-base: 0.15s ease;
            --transition-slow: 0.3s ease;

            /* Z-index layers */
            --z-dropdown: 100;
            --z-modal: 1000;
            --z-tooltip: 2000;

            font-family: var(--font-family);
            font-size: var(--font-size);
            color: var(--text-primary);
        }
    `;

    /**
     * Button styles (reusable across components)
     */
    const buttonCSS = `
        /* Base button reset */
        button, .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-xs);
            padding: var(--spacing-xs) var(--spacing-sm);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--bg-surface);
            color: var(--text-primary);
            font-family: inherit;
            font-size: var(--font-size-sm);
            font-weight: 500;
            cursor: pointer;
            transition: background var(--transition-base),
                        border-color var(--transition-base),
                        color var(--transition-base);
            white-space: nowrap;
            text-decoration: none;
        }

        button:hover, .btn:hover {
            background: var(--bg-hover);
        }

        button:focus, .btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px var(--input-focus-shadow);
        }

        button:disabled, .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Primary button */
        button.btn-primary, .btn-primary {
            background: var(--bg-button-primary);
            border-color: var(--bg-button-primary);
            color: var(--text-inverse);
        }

        button.btn-primary:hover, .btn-primary:hover {
            background: var(--bg-button-primary-hover);
            border-color: var(--bg-button-primary-hover);
        }

        button.btn-primary:active, .btn-primary:active {
            background: var(--bg-button-primary-active);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.35);
        }

        /* Secondary button */
        button.btn-secondary, .btn-secondary {
            background: var(--bg-button-secondary);
            border-color: var(--bg-button-secondary);
            color: var(--text-inverse);
        }

        button.btn-secondary:hover, .btn-secondary:hover {
            background: var(--bg-button-secondary-hover);
            border-color: var(--bg-button-secondary-hover);
        }

        button.btn-secondary:active, .btn-secondary:active {
            background: var(--bg-button-secondary-active);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.35);
        }

        /* Success button */
        button.btn-success, .btn-success {
            background: var(--color-success);
            border-color: var(--color-success);
            color: var(--text-inverse);
        }

        button.btn-success:hover, .btn-success:hover {
            background: #218838;
            border-color: #1e7e34;
        }

        button.btn-success:active, .btn-success:active {
            background: #1e7e34;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.35);
        }

        /* Danger/Cancel button */
        button.btn-danger, .btn-danger,
        button.btn-cancel, .btn-cancel {
            background: var(--bg-button-cancel, #c4c4c4);
            border-color: var(--bg-button-cancel, #c4c4c4);
            color: var(--text-primary);
        }

        button.btn-danger:hover, .btn-danger:hover,
        button.btn-cancel:hover, .btn-cancel:hover {
            background: var(--bg-button-cancel-hover, #e8e8e8);
            border-color: var(--bg-button-cancel-hover, #e8e8e8);
        }

        button.btn-danger:active, .btn-danger:active,
        button.btn-cancel:active, .btn-cancel:active {
            background: var(--bg-button-cancel-active, #aaa);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.25);
        }

        /* Ghost/transparent button */
        button.btn-ghost, .btn-ghost {
            background: transparent;
            border-color: transparent;
        }

        button.btn-ghost:hover, .btn-ghost:hover {
            background: var(--bg-hover);
        }

        /* Icon-only button */
        button.btn-icon, .btn-icon {
            padding: var(--spacing-xs);
            min-width: 28px;
            min-height: 28px;
        }
    `;

    /**
     * Form input styles
     */
    const inputCSS = `
        input, textarea, select {
            padding: var(--spacing-sm) var(--spacing-md);
            border: 1px solid var(--input-border);
            border-radius: var(--radius-md);
            background: var(--input-bg);
            color: var(--text-primary);
            font-family: inherit;
            font-size: var(--font-size);
            transition: border-color var(--transition-base),
                        box-shadow var(--transition-base);
        }

        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--input-focus-border);
            box-shadow: 0 0 0 3px var(--input-focus-shadow);
        }

        input:disabled, textarea:disabled, select:disabled {
            background: var(--bg-disabled);
            cursor: not-allowed;
        }

        input::placeholder, textarea::placeholder {
            color: var(--text-muted);
        }

        /* Search input */
        input[type="search"] {
            padding-left: var(--spacing-lg);
        }
    `;

    /**
     * Common animation keyframes
     */
    const animationCSS = `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }

        @keyframes slideIn {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes slideOut {
            from { transform: translateY(0); opacity: 1; }
            to { transform: translateY(-10px); opacity: 0; }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.4; }
            50% { opacity: 1; }
        }

        /* Utility classes */
        .fade-in { animation: fadeIn var(--transition-base); }
        .fade-out { animation: fadeOut var(--transition-base); }
        .slide-in { animation: slideIn 0.2s ease; }
        .slide-out { animation: slideOut 0.2s ease; }
    `;

    /**
     * Dropdown/popup styles
     */
    const dropdownCSS = `
        .dropdown {
            position: absolute;
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: var(--z-dropdown);
            overflow: hidden;
        }

        .dropdown-item {
            display: block;
            padding: var(--spacing-sm) var(--spacing-md);
            color: var(--text-primary);
            text-decoration: none;
            cursor: pointer;
            transition: background var(--transition-fast);
        }

        .dropdown-item:hover {
            background: var(--bg-hover);
        }

        .dropdown-item.selected {
            background: var(--bg-active);
            color: var(--color-primary);
        }

        .dropdown-item.disabled {
            color: var(--text-muted);
            cursor: not-allowed;
        }

        .dropdown-divider {
            height: 1px;
            background: var(--border-light);
            margin: var(--spacing-xs) 0;
        }
    `;

    /**
     * Badge styles
     */
    const badgeCSS = `
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 18px;
            height: 18px;
            padding: 0 6px;
            border-radius: 9px;
            font-size: var(--font-size-xs);
            font-weight: 600;
            background: var(--color-info);
            color: var(--text-inverse);
        }

        .badge-success { background: var(--color-success); }
        .badge-warning { background: var(--color-warning); color: var(--text-primary); }
        .badge-danger { background: var(--color-error); }
        .badge-muted { background: var(--bg-surface-alt); color: var(--text-secondary); }
    `;

    /**
     * Scrollbar styles
     */
    const scrollbarCSS = `
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-surface-alt);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--border-dark);
        }

        /* Firefox */
        * {
            scrollbar-width: thin;
            scrollbar-color: var(--border-color) var(--bg-surface-alt);
        }
    `;

    // Style categories map
    const styleCategories = {
        base: baseCSS,
        button: buttonCSS,
        input: inputCSS,
        animation: animationCSS,
        dropdown: dropdownCSS,
        badge: badgeCSS,
        scrollbar: scrollbarCSS
    };

    /**
     * Get or create a CSSStyleSheet for a category
     * @param {string} category - Style category name
     * @returns {CSSStyleSheet|null}
     */
    function getStyleSheet(category) {
        if (!supportsAdoptedStyleSheets) return null;

        if (!styleSheetCache.has(category)) {
            const css = styleCategories[category];
            if (!css) {
                console.warn(`[lib-shared-styles] Unknown category: ${category}`);
                return null;
            }

            const sheet = new CSSStyleSheet();
            sheet.replaceSync(css);
            styleSheetCache.set(category, sheet);
        }

        return styleSheetCache.get(category);
    }

    /**
     * Adopt shared stylesheets into a shadow root
     * @param {ShadowRoot} shadowRoot - The shadow root to adopt styles into
     * @param {...string} categories - Style categories to adopt
     */
    function adoptSharedStyles(shadowRoot, ...categories) {
        if (!supportsAdoptedStyleSheets) {
            console.warn('[lib-shared-styles] adoptedStyleSheets not supported, falling back to inline');
            return false;
        }

        const sheets = [];

        // Always include base styles
        if (!categories.includes('base')) {
            categories.unshift('base');
        }

        for (const category of categories) {
            const sheet = getStyleSheet(category);
            if (sheet) {
                sheets.push(sheet);
            }
        }

        // Preserve any existing component-specific stylesheets
        const existingSheets = [...shadowRoot.adoptedStyleSheets];
        shadowRoot.adoptedStyleSheets = [...sheets, ...existingSheets];

        return true;
    }

    /**
     * Create a component-specific CSSStyleSheet
     * @param {string} css - Component-specific CSS
     * @returns {CSSStyleSheet}
     */
    function createComponentSheet(css) {
        if (!supportsAdoptedStyleSheets) return null;

        const sheet = new CSSStyleSheet();
        sheet.replaceSync(css);
        return sheet;
    }

    /**
     * Get inline CSS string for fallback (when adoptedStyleSheets not supported)
     * @param {...string} categories - Style categories to include
     * @returns {string}
     */
    function getInlineCSS(...categories) {
        // Always include base
        if (!categories.includes('base')) {
            categories.unshift('base');
        }

        return categories
            .map(cat => styleCategories[cat] || '')
            .filter(Boolean)
            .join('\n');
    }

    /**
     * Check if adoptedStyleSheets is supported
     * @returns {boolean}
     */
    function isSupported() {
        return supportsAdoptedStyleSheets;
    }

    // Export to global scope
    window.LibSharedStyles = {
        adopt: adoptSharedStyles,
        createSheet: createComponentSheet,
        getInlineCSS: getInlineCSS,
        getStyleSheet: getStyleSheet,
        isSupported: isSupported,
        categories: Object.keys(styleCategories)
    };

    // Log initialization
    if (typeof cmaLog !== 'undefined') {
        // cmaLog.log('[lib-shared-styles] Initialized, adoptedStyleSheets supported:', supportsAdoptedStyleSheets);
    }

})();
