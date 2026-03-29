/**
 * CMA Cypress E2E Support File
 *
 * This file runs before every test file.
 * Use it to load custom commands and global configuration.
 */

// Import legacy commands file first (contains general utility commands)
import './commands';

// Import modular command files (loaded after legacy to override duplicates)
import './commands/auth';
import './commands/navigation';
import './commands/api';
import './commands/forms';  // forms.js last - has the most up-to-date form commands

// Prevent Cypress from failing on uncaught exceptions from the app
Cypress.on('uncaught:exception', (err, runnable) => {
    // Ignore specific known errors
    if (err.message.includes('ResizeObserver loop')) {
        return false;
    }
    if (err.message.includes('Cannot read properties of null')) {
        return false;
    }
    // Log the error but don't fail the test
    console.log('Uncaught exception:', err.message);
    return false;
});

// Configure default timeouts
Cypress.config('defaultCommandTimeout', 10000);
Cypress.config('pageLoadTimeout', 30000);

// Log test start for debugging
beforeEach(() => {
    cy.log(`Starting test: ${Cypress.currentTest.title}`);

    // Intercept tips API to return ALL tips as skipped - prevents tip overlays from blocking tests
    // Tour IDs used in cma-tours.js: dashboard, main-navigation, form-*, report-designer, tools.php, etc.
    cy.intercept('GET', '**/api/user_tips.php*', {
        statusCode: 200,
        body: {
            skipList: [
                'dashboard', 'dashboard.php', 'main-navigation', 'navigation-tour', 'welcome-tour',
                'form.php', 'form-generic', 'form-users', 'form-groups', 'form-opleidingen',
                'form-contactpersonen', 'form-deelnemers', 'form-klanten', 'form-afspraak',
                'form-rooster', 'form-urentemplate', 'form-cmamonitoring',
                'report-designer', 'report-designer-loaded', 'report-designer-field-search',
                'reports.php', 'tools.php', 'tools-query', 'tools-dbsummary', 'tools-migrations',
                'tools-formwiz', 'tools-storybook', 'imageupload', 'preferences',
                'field-search-tip', 'column-selector-tip'
            ]
        }
    }).as('skipTips');

    // Also intercept POST to dismiss tips
    cy.intercept('POST', '**/api/user_tips.php*', {
        statusCode: 200,
        body: { success: true }
    }).as('dismissTip');
});

// Global before hook - runs once before all tests
before(() => {
    // Clear any stale sessions
    Cypress.session.clearAllSavedSessions();
});

// After each test - capture state on failure
afterEach(function() {
    if (this.currentTest.state === 'failed') {
        cy.log('Test failed - check screenshot');
    }
});

// Override cy.visit to disable tips completely
// The lib-tip overlay (z-index 10000, pointer-events: auto) blocks all interactions
// Strategy: inject a MutationObserver that removes lib-tip elements as soon as they appear
Cypress.Commands.overwrite('visit', (originalFn, url, options) => {
    const opts = typeof options === 'object' ? { ...options } : {};
    const originalOnBeforeLoad = opts.onBeforeLoad;

    // Allow tests to opt out of lib-tip removal (e.g. lib-tip component tests)
    if (opts.skipTipRemoval) {
        delete opts.skipTipRemoval;
        if (originalOnBeforeLoad) {
            opts.onBeforeLoad = originalOnBeforeLoad;
        } else {
            delete opts.onBeforeLoad;
        }
        return originalFn(url, opts);
    }

    opts.onBeforeLoad = (win) => {
        // Set up a MutationObserver that removes lib-tip elements immediately
        // This runs before any page scripts, so it catches all tip creation
        const observer = new win.MutationObserver((mutations) => {
            for (const mutation of mutations) {
                for (const node of mutation.addedNodes) {
                    if (node.nodeType === 1) {
                        if (node.tagName === 'LIB-TIP') {
                            node.remove();
                        }
                        // Also check children in case lib-tip is nested
                        const tips = node.querySelectorAll && node.querySelectorAll('lib-tip');
                        if (tips) tips.forEach(tip => tip.remove());
                    }
                }
            }
        });
        // Start observing as soon as body exists
        const startObserving = () => {
            if (win.document.body) {
                observer.observe(win.document.body, { childList: true, subtree: true });
                // Remove any lib-tip elements that already exist
                win.document.querySelectorAll('lib-tip').forEach(el => el.remove());
            } else {
                win.requestAnimationFrame(startObserving);
            }
        };
        startObserving();
        if (originalOnBeforeLoad) originalOnBeforeLoad(win);
    };

    return originalFn(url, opts);
});
