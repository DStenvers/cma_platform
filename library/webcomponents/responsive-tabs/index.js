/**
 * Responsive Tabs Component Loader
 *
 * This file handles loading the responsive-tabs web component
 * with its CSS and JS files.
 *
 * Usage:
 *   <script src="/library/webcomponents/responsive-tabs/index.js"></script>
 *
 * Or as a module:
 *   import '/library/webcomponents/responsive-tabs/index.js';
 */

(function() {
    'use strict';

    // Determine the base path for this component
    const currentScript = document.currentScript;
    let basePath = '';

    if (currentScript) {
        basePath = currentScript.src.substring(0, currentScript.src.lastIndexOf('/') + 1);
    } else {
        // Fallback: try to find the path from known locations
        basePath = '/library/webcomponents/responsive-tabs/';
    }

    // Load CSS
    function loadCSS() {
        const cssPath = basePath + 'responsive-tabs.css';

        // Check if already loaded
        if (document.querySelector(`link[href*="responsive-tabs.css"]`)) {
            return Promise.resolve();
        }

        return new Promise((resolve, reject) => {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = cssPath;
            link.onload = resolve;
            link.onerror = reject;
            document.head.appendChild(link);
        });
    }

    // Load JS
    function loadJS() {
        const jsPath = basePath + 'responsive-tabs.js';

        // Check if already loaded (custom element defined)
        if (customElements.get('responsive-tabs')) {
            return Promise.resolve();
        }

        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = jsPath;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    // Load both CSS and JS
    Promise.all([loadCSS(), loadJS()])
        .then(() => {
            // Dispatch event when component is ready
            window.dispatchEvent(new CustomEvent('responsive-tabs-ready'));
        })
        .catch((error) => {
            console.error('Failed to load responsive-tabs component:', error);
        });

    // Helper function to upgrade existing glow_tabs to responsive-tabs
    window.upgradeToResponsiveTabs = function(selector, options = {}) {
        const element = document.querySelector(selector);
        if (!element) {
            console.warn('upgradeToResponsiveTabs: Element not found:', selector);
            return null;
        }

        // Wait for component to be defined
        return customElements.whenDefined('responsive-tabs').then(() => {
            const wrapper = document.createElement('responsive-tabs');

            // Copy attributes
            if (options.breakpoint) {
                wrapper.setAttribute('breakpoint', options.breakpoint);
            }
            if (options.mode) {
                wrapper.setAttribute('mode', options.mode);
            }
            if (options.id) {
                wrapper.id = options.id;
            }

            // Move the original element inside the wrapper
            element.parentNode.insertBefore(wrapper, element);
            wrapper.appendChild(element);

            return wrapper;
        });
    };
})();
