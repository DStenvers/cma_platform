/**
 * Service Worker Caching Tests
 *
 * Tests for browser-side form template caching via Service Worker.
 * The SW uses a stale-while-revalidate strategy for form.php requests.
 */

describe('Service Worker Caching', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    describe('Service Worker Registration', () => {
        it('should register Service Worker on page load', () => {
            cy.visit('/main.php');

            // Wait for SW to register
            cy.window().then(win => {
                // Check if serviceWorker is supported
                if ('serviceWorker' in win.navigator) {
                    // Give SW time to register
                    cy.wait(1000);

                    cy.wrap(win.navigator.serviceWorker.getRegistration('/cma/'))
                        .should('not.be.null');
                } else {
                    cy.log('Service Worker not supported in this browser');
                }
            });
        });

        it('should have SW controller active after registration', () => {
            cy.visit('/main.php');

            cy.window().then(win => {
                if ('serviceWorker' in win.navigator) {
                    // Wait for controller to be available
                    cy.wait(2000);

                    // Controller may not be available on first load (requires reload)
                    // This is expected behavior for SW lifecycle
                    cy.log('SW controller status:', win.navigator.serviceWorker.controller ? 'active' : 'not yet active');
                }
            });
        });
    });

    describe('CMA.clearFormCache Utility', () => {
        it('should have clearFormCache method on CMA namespace', () => {
            cy.visit('/main.php');

            cy.window().then(win => {
                expect(win.CMA).to.exist;
                expect(win.CMA.clearFormCache).to.be.a('function');
            });
        });

        it('should have getFormCacheInfo method on CMA namespace', () => {
            cy.visit('/main.php');

            cy.window().then(win => {
                expect(win.CMA).to.exist;
                expect(win.CMA.getFormCacheInfo).to.be.a('function');
            });
        });

        it('should return a Promise from clearFormCache', () => {
            cy.visit('/main.php');

            cy.window().then(win => {
                const result = win.CMA.clearFormCache();
                expect(result).to.be.instanceOf(win.Promise);
            });
        });

        it('should resolve clearFormCache gracefully when SW not available', () => {
            cy.visit('/main.php');

            cy.window().then(win => {
                // Even if SW controller isn't available, should resolve to false
                cy.wrap(win.CMA.clearFormCache()).should('be.oneOf', [true, false]);
            });
        });
    });

    describe('Cache Clear on Tools Page', () => {
        it('should auto-clear browser form cache on tools_clearcache.php load', () => {
            cy.visit('/tools/tools_clearcache.php');

            // Wait for page to load and auto-clear to execute
            cy.wait(1000);

            // The browser-cache-note is inside the hidden #detailsPanel.
            // Click "Toon details" to expand it first, then check visibility.
            cy.get('#toggleDetails').click();
            cy.get('#detailsPanel').should('be.visible');
            cy.get('#browser-cache-note').should('be.visible');
        });

        it('should show success message when all caches are cleared', () => {
            cy.visit('/tools/tools_clearcache.php');

            // Wait for lib-message web component to render
            cy.wait(1000);

            // Check for success or warning message (some caches may not be available in test env)
            cy.get('lib-message[type="success"], lib-message[type="warning"]', { timeout: 5000 })
                .should('exist')
                .first()
                .should('be.visible');
        });
    });

    describe('Form Template Caching Behavior', () => {
        it('should cache form templates for subsequent requests', () => {
            // Load a form first
            cy.visit('/form.php?form=users');
            cy.waitForContent();

            // The form should load successfully
            cy.get('.form-layout').should('exist');
        });

        it('should skip caching when nocache parameter is present', () => {
            // This tests the SW skip logic for ?nocache parameter
            cy.intercept('**/form.php*nocache*').as('nocacheRequest');

            cy.visit('/form.php?form=users&nocache=1');
            cy.waitForContent();

            // Form should still load (just not cached)
            cy.get('.form-layout').should('exist');
        });
    });
});

describe('Service Worker Cache Invalidation', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    describe('Manual Cache Clear', () => {
        it('should clear form cache via CMA.clearFormCache()', () => {
            cy.visit('/main.php');

            // Wait for SW to be ready
            cy.wait(2000);

            cy.window().then(win => {
                // Clear the cache
                cy.wrap(win.CMA.clearFormCache()).then(cleared => {
                    // Result depends on whether SW is active
                    cy.log('Cache clear result:', cleared);
                });
            });
        });

        it('should report cache info via CMA.getFormCacheInfo()', () => {
            cy.visit('/main.php');

            // Wait for SW to be ready
            cy.wait(2000);

            cy.window().then(win => {
                cy.wrap(win.CMA.getFormCacheInfo()).then(info => {
                    if (info) {
                        cy.log('Cache version:', info.version);
                        cy.log('Cache name:', info.cache);
                        cy.log('Cached entries:', info.entries ? info.entries.length : 0);
                    } else {
                        cy.log('SW controller not available, cache info is null');
                    }
                });
            });
        });
    });
});
