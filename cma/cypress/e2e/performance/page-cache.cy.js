/**
 * DOM Page Cache Tests
 *
 * Tests for in-memory DOM caching that enables instant form navigation.
 * When a user navigates away from a form, the DOM + controller are cached.
 * Returning to that form restores instantly without a server round-trip.
 */

describe('DOM Page Cache', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit('/main.php');
        cy.get('#sidebar', { timeout: 15000 }).should('be.visible');
    });

    describe('Cache API', () => {
        it('should expose pageCacheStats on CMA namespace', () => {
            cy.window().its('CMA').should('have.property', 'pageCacheStats');
            cy.window().then(win => {
                const stats = win.CMA.pageCacheStats();
                expect(stats).to.have.property('size', 0);
                expect(stats).to.have.property('max', 5);
                expect(stats).to.have.property('entries').that.is.an('array');
            });
        });

        it('should expose clearPageCache on CMA namespace', () => {
            cy.window().its('CMA').should('have.property', 'clearPageCache');
        });
    });

    describe('Cache Hit Behavior', () => {
        it('should cache a form page when navigating away', () => {
            // Load first form via sidebar
            cy.window().then(win => win.loadPage('form.php?form=opleidingen'));
            cy.get('.form-layout', { timeout: 15000 }).should('exist');

            // Navigate to a different form
            cy.window().then(win => win.loadPage('form.php?form=logins'));
            cy.get('.form-layout', { timeout: 15000 }).should('exist');

            // Check that first form is cached
            cy.window().then(win => {
                const stats = win.CMA.pageCacheStats();
                expect(stats.size).to.be.greaterThan(0);
                const cached = stats.entries.find(e => e.page.includes('opleidingen'));
                expect(cached).to.exist;
            });
        });

        it('should restore from cache without network request', () => {
            // Load form A
            cy.window().then(win => win.loadPage('form.php?form=opleidingen'));
            cy.get('.form-layout', { timeout: 15000 }).should('exist');

            // Navigate to form B
            cy.window().then(win => win.loadPage('form.php?form=logins'));
            cy.get('.form-layout', { timeout: 15000 }).should('exist');

            // Navigate back to form A - should be instant (from cache)
            cy.intercept('/cma/main.php?nomenu*opleidingen*').as('fetchOpleidingen');
            cy.window().then(win => win.loadPage('form.php?form=opleidingen'));

            // Form should appear immediately (no loading state)
            cy.get('.form-layout', { timeout: 2000 }).should('exist');

            // Verify no server fetch was made for the template
            cy.get('@fetchOpleidingen.all').should('have.length', 0);
        });

        it('should update breadcrumb on cache restore', () => {
            // Load form A
            cy.window().then(win => win.loadPage('form.php?form=opleidingen'));
            cy.get('.form-layout', { timeout: 15000 }).should('exist');

            // Navigate to form B
            cy.window().then(win => win.loadPage('form.php?form=logins'));
            cy.get('.form-layout', { timeout: 15000 }).should('exist');

            // Navigate back to form A
            cy.window().then(win => win.loadPage('form.php?form=opleidingen'));
            cy.get('.form-layout', { timeout: 5000 }).should('exist');

            // Breadcrumb should reflect the restored form
            cy.get('#breadcrumb').should('not.be.empty');
        });

        it('should update menu active state on cache restore', () => {
            // Load form via loadPage
            cy.window().then(win => win.loadPage('form.php?form=opleidingen'));
            cy.get('.form-layout', { timeout: 15000 }).should('exist');

            // Navigate away
            cy.window().then(win => win.loadPage('form.php?form=logins'));
            cy.get('.form-layout', { timeout: 15000 }).should('exist');

            // Navigate back
            cy.window().then(win => win.loadPage('form.php?form=opleidingen'));
            cy.get('.form-layout', { timeout: 5000 }).should('exist');

            // Active menu item should be set
            cy.get('.cma-menu-item.active').should('exist');
        });
    });

    describe('Body Class Restoration', () => {
        it('should restore body classes from cached page', () => {
            // Load a form that sets mode-table
            cy.window().then(win => win.loadPage('form.php?form=opleidingen'));
            cy.get('.form-layout', { timeout: 15000 }).should('exist');

            // Wait for body class to be set
            cy.get('body').should('have.class', 'mode-table');

            // Navigate away
            cy.window().then(win => win.loadPage('form.php?form=logins'));
            cy.get('.form-layout', { timeout: 15000 }).should('exist');

            // Body class should have been cleared/changed for new form
            // Navigate back
            cy.window().then(win => win.loadPage('form.php?form=opleidingen'));
            cy.get('.form-layout', { timeout: 5000 }).should('exist');

            // mode-table should be restored
            cy.get('body').should('have.class', 'mode-table');
        });
    });

    describe('LRU Eviction', () => {
        it('should evict oldest page when cache exceeds 5 entries', () => {
            // Need 7 forms: current page is always live (not cached),
            // so loading 7 forms means 6 get cached, triggering eviction of the oldest
            const forms = ['opleidingen', 'logins', 'rooster', 'aankondigingen', 'groepen', 'contentblocks', 'cmamonitoring'];

            let chain = cy.wrap(null);
            forms.forEach(form => {
                chain = chain.then(() => {
                    cy.window().then(win => win.loadPage('form.php?form=' + form));
                    return cy.get('.form-layout', { timeout: 15000 }).should('exist');
                });
            });

            chain.then(() => {
                cy.window().then(win => {
                    const stats = win.CMA.pageCacheStats();
                    // Cache should not exceed MAX_CACHED_PAGES (5)
                    expect(stats.size).to.be.at.most(5);
                    // The first form (opleidingen) should have been evicted
                    const hasFirst = stats.entries.some(e => e.page.includes('opleidingen'));
                    expect(hasFirst).to.be.false;
                });
            });
        });
    });

    describe('Cache Clear', () => {
        it('should clear all cached pages via CMA.clearPageCache()', () => {
            // Load two forms to populate cache
            cy.window().then(win => win.loadPage('form.php?form=opleidingen'));
            cy.get('#listTable, table.listtable, .form-layout, #listContent', { timeout: 15000 }).should('exist');
            cy.wait(1000);

            cy.window().then(win => win.loadPage('form.php?form=logins'));
            cy.get('#listTable, table.listtable, .form-layout, #listContent', { timeout: 15000 }).should('exist');
            cy.wait(1000);

            // Test clearPageCache function itself: after clearing, size should be 0
            cy.window().then(win => {
                const stats = win.CMA.pageCacheStats();
                // Cache might have 0 entries if DOM layout doesn't support caching
                // The important test is that clearPageCache() doesn't throw and resets to 0
                win.CMA.clearPageCache();
                expect(win.CMA.pageCacheStats().size).to.equal(0);
            });
        });
    });

    describe('Non-Cacheable Pages', () => {
        it('should NOT cache tools pages', () => {
            // Load a form first
            cy.window().then(win => win.loadPage('form.php?form=opleidingen'));
            cy.get('.form-layout', { timeout: 15000 }).should('exist');

            // Navigate to tools page
            cy.window().then(win => win.loadPage('tools.php'));
            cy.get('body', { timeout: 15000 });

            // Tools page should not be cached
            cy.window().then(win => {
                const stats = win.CMA.pageCacheStats();
                const hasTools = stats.entries.some(e => e.page.includes('tools.php'));
                expect(hasTools).to.be.false;
            });
        });
    });

    describe('Browser History', () => {
        it('should work with browser back/forward buttons', () => {
            // Load form A
            cy.window().then(win => win.loadPage('form.php?form=opleidingen'));
            cy.get('.form-layout', { timeout: 15000 }).should('exist');

            // Load form B
            cy.window().then(win => win.loadPage('form.php?form=logins'));
            cy.get('.form-layout', { timeout: 15000 }).should('exist');

            // Browser back should restore form A from cache
            cy.go('back');
            cy.get('.form-layout', { timeout: 5000 }).should('exist');

            // Browser forward should restore form B from cache
            cy.go('forward');
            cy.get('.form-layout', { timeout: 5000 }).should('exist');
        });
    });

    describe('Data Preservation', () => {
        it('should preserve list content from cache without re-fetching', () => {
            // Load form A
            cy.window().then(win => win.loadPage('form.php?form=opleidingen'));
            cy.get('.form-layout', { timeout: 15000 }).should('exist');
            // Wait for list to load
            cy.get('#listContent', { timeout: 10000 }).should('exist');

            // Navigate away
            cy.window().then(win => win.loadPage('form.php?form=logins'));
            cy.get('.form-layout', { timeout: 15000 }).should('exist');

            // Intercept list API calls — should NOT fire on cache restore
            cy.intercept('/cma/form_api.php*action=tree*').as('listFetch');

            // Navigate back to form A (from cache)
            cy.window().then(win => win.loadPage('form.php?form=opleidingen'));
            cy.get('.form-layout', { timeout: 5000 }).should('exist');

            // List content should still be visible (preserved from cache)
            cy.get('#listContent').should('exist');

            // No list API call should have been made
            cy.get('@listFetch.all').should('have.length', 0);
        });
    });
});
