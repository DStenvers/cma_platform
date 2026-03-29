/**
 * Clean URL Tests
 *
 * Tests the clean URL routing system (supports up to 3 levels of nesting):
 * - /cma/ → Redirects to /cma/dashboard
 * - /cma/dashboard → Dashboard
 * - /cma/tools → Tools page
 * - /cma/form/formname → List view
 * - /cma/form/formname/recordId → Detail view (tree + record in detail panel)
 * - /cma/form/formname/new → New record (tree + new form in detail panel)
 * - /cma/form/formname/recordId/subform → Subform list
 * - /cma/form/formname/recordId/subform/subId → Subform detail
 * - /cma/form/formname/recordId/subform/subId/subsubform → Sub-subform list (3rd level)
 * - /cma/form/formname/recordId/subform/subId/subsubform/subsubId → Sub-subform detail (3rd level)
 */

describe('Clean URL Routing', () => {
    describe('Simple Page Clean URLs', () => {
        it('should redirect /cma/ to /cma/dashboard', () => {
            cy.loginAsAdmin();
            cy.visit('/');

            // Should redirect to dashboard
            cy.url().should('include', '/dashboard');
        });

        it('should load dashboard via /cma/dashboard', () => {
            cy.loginAsAdmin();
            cy.visit('/dashboard');

            // Should load main.php with dashboard content
            cy.get('#contentArea', { timeout: 10000 }).should('exist');
            // Dashboard should have dashboard-container or stats-card or menu-card
            cy.get('#contentArea').within(() => {
                cy.get('.dashboard-container, .stats-card, .menu-card, .dashboard-card').should('exist');
            });
        });

        it('should load tools via /cma/tools', () => {
            cy.loginAsAdmin();
            cy.visit('/tools');

            // Should load main.php with tools content
            cy.get('#contentArea', { timeout: 10000 }).should('exist');
            // Tools should have tree navigation
            cy.get('#contentArea').within(() => {
                cy.get('#tools-tree, cma-tree').should('exist');
            });
        });
    });

    describe('Basic Clean URL Access', () => {
        it('should load form via clean URL after login', () => {
            // Login using session-cached login for reliability
            cy.loginAsAdmin();

            // Visit clean URL directly
            cy.visit('/form/opleidingen');

            // Must NOT be on login page
            cy.url().should('not.include', 'login');
            cy.url().should('not.include', 'default');

            // Must find content area with form content
            cy.get('#contentArea', { timeout: 10000 }).should('exist');
            cy.get('#contentArea .form-layout, #contentArea table.listtable, #contentArea lib-table', { timeout: 15000 }).should('exist');
        });

        it('should navigate to clean URL via menu', () => {
            // Login
            cy.visit('/login.php');
            cy.get('#txtLogin').clear().type(Cypress.env('adminUser'));
            cy.get('#txtPW').clear().type(Cypress.env('adminPass'));
            cy.get('#btnLogin').click();
            cy.get('#sidebar, .cma-sidebar', { timeout: 10000 }).should('be.visible');

            // Click on "Taken" menu item - it's a direct form link, not a submenu group
            cy.get('#sidebar').contains('a', 'Taken').click({ force: true });

            // Wait for form to load
            cy.get('#contentArea .form-layout, #contentArea table.listtable, #contentArea lib-table', { timeout: 15000 }).should('exist');

            // URL should be in clean format (after JS updates it)
            cy.url().should('match', /\/cma\/form\/taken/);
        });
    });

    describe('Record URLs', () => {
        it('should include record ID in URL when navigating to a record', () => {
            // Verify that direct URL navigation with record ID works
            // (clicking a row to update URL is tested in url-state.cy.js)
            cy.loginAsAdmin();

            // Get a valid record ID from groups form via API
            cy.request({
                method: 'POST',
                url: 'form_api.php',
                form: true,
                body: { action: 'list', jsonForm: 'groups', limit: 1 }
            }).then(response => {
                if (response.body && response.body.data && response.body.data.length > 0) {
                    const recordId = response.body.data[0].ID || response.body.data[0].id;
                    // Direct URL with record ID should load the form
                    cy.visit(`/form/groups/${recordId}`);
                    cy.url().should('match', /\/cma\/form\/groups\/\d+/);
                    // Form should load successfully
                    cy.get('#contentArea', { timeout: 15000 }).should('exist');
                }
            });
        });
    });

    describe('Record Detail URLs', () => {
        it('should load record in detail panel with tree view when navigating to /form/xxx/recordId', () => {
            // Login
            cy.visit('/login.php');
            cy.get('#txtLogin').clear().type(Cypress.env('adminUser'));
            cy.get('#txtPW').clear().type(Cypress.env('adminPass'));
            cy.get('#btnLogin').click();
            cy.get('#sidebar, .cma-sidebar', { timeout: 10000 }).should('be.visible');

            // Visit a form with a record ID (users form, record 1)
            cy.visit('/form/users/1');

            // Wait for content to load
            cy.get('#contentArea', { timeout: 10000 }).should('exist');

            // URL should be correct
            cy.url().should('include', '/form/users/1');

            // Should show tree view on the left (not sidepanel)
            cy.get('#leftlist', { timeout: 10000 }).should('exist');
            cy.get('body').should('have.class', 'mode-tree');

            // Should show user form fields in the detail panel on the right
            cy.get('#mainForm input[name="userLogin"]', { timeout: 10000 }).should('exist');

            // Should NOT have opened a sidepanel
            cy.get('.lib_sidepanel_container').should('not.exist');
        });

        it('should open record detail when clicking table row', () => {
            // Login
            cy.visit('/login.php');
            cy.get('#txtLogin').clear().type(Cypress.env('adminUser'));
            cy.get('#txtPW').clear().type(Cypress.env('adminPass'));
            cy.get('#btnLogin').click();
            cy.get('#sidebar, .cma-sidebar', { timeout: 10000 }).should('be.visible');

            // Visit a form list
            cy.visit('/form/users');

            // Wait for table to load - could be lib-table or regular table
            cy.get('#contentArea', { timeout: 10000 }).should('exist');

            // Wait for list content
            cy.get('#listContent a[target="R"], #listContent tr[data-id], .listtable tr[data-id]', { timeout: 15000 })
                .should('have.length.at.least', 1);

            // Click first row/link to open record
            cy.get('#listContent a[target="R"], #listContent tr[data-id], .listtable tr[data-id]').first().click();

            // Wait for detail form to load
            cy.wait(500);

            // Should show user form fields (list+detail view or full-page)
            cy.get('input[name="userLogin"]', { timeout: 10000 }).should('exist');
        });
    });

    describe('New Record URLs', () => {
        it('should support /new URL for new records', () => {
            // Login
            cy.visit('/login.php');
            cy.get('#txtLogin').clear().type(Cypress.env('adminUser'));
            cy.get('#txtPW').clear().type(Cypress.env('adminPass'));
            cy.get('#btnLogin').click();
            cy.get('#sidebar, .cma-sidebar', { timeout: 10000 }).should('be.visible');

            // Visit new record URL directly
            cy.visit('/form/groups/new');

            // Should not redirect to login
            cy.url().should('not.include', 'login');

            // Content area should exist
            cy.get('#contentArea', { timeout: 10000 }).should('exist');
        });
    });

    describe('Third Level URL Nesting', () => {
        it('should parse 3-level URLs correctly', () => {
            // Login
            cy.visit('/login.php');
            cy.get('#txtLogin').clear().type(Cypress.env('adminUser'));
            cy.get('#txtPW').clear().type(Cypress.env('adminPass'));
            cy.get('#btnLogin').click();
            cy.get('#sidebar, .cma-sidebar', { timeout: 10000 }).should('be.visible');

            // Visit a form first to ensure CMA.url is loaded
            cy.visit('/form/users');
            cy.get('#contentArea', { timeout: 10000 }).should('exist');

            // Test URL parsing for 3-level URL
            cy.window().then(win => {
                // Build a 3-level URL state
                const state = {
                    form: 'opleidingen',
                    recordId: '121',
                    subform: 'opleidingen_deelnemers',
                    subformId: '1832',
                    subsubform: 'documenten',
                    subsubformId: '19'
                };

                // Build the URL
                const url = win.CMA.url.build(state);
                expect(url).to.equal('/cma/form/opleidingen/121/opleidingen_deelnemers/1832/documenten/19');
            });
        });

        it('should report correct depth for 3-level URLs', () => {
            // Login and load CMA
            cy.visit('/login.php');
            cy.get('#txtLogin').clear().type(Cypress.env('adminUser'));
            cy.get('#txtPW').clear().type(Cypress.env('adminPass'));
            cy.get('#btnLogin').click();
            cy.get('#sidebar, .cma-sidebar', { timeout: 10000 }).should('be.visible');

            // Visit different URL depths and check getDepth()
            cy.visit('/form/users');
            cy.get('#contentArea', { timeout: 10000 }).should('exist');
            cy.window().then(win => {
                expect(win.CMA.url.getDepth()).to.equal(0); // List view
            });

            cy.visit('/form/users/1');
            cy.wait(500);
            cy.window().then(win => {
                expect(win.CMA.url.getDepth()).to.equal(1); // Record detail
            });
        });

        it('should handle 3-level subform URL routing via IIS', () => {
            // Login
            cy.visit('/login.php');
            cy.get('#txtLogin').clear().type(Cypress.env('adminUser'));
            cy.get('#txtPW').clear().type(Cypress.env('adminPass'));
            cy.get('#btnLogin').click();
            cy.get('#sidebar, .cma-sidebar', { timeout: 10000 }).should('be.visible');

            // Visit a 3-level URL directly (test IIS rewrite rules)
            // This should NOT 404 - it should load main.php with the correct parameters
            cy.visit('/form/users/1/groups/1/users/1');

            // Should not redirect to login or 404
            cy.url().should('not.include', 'login');
            cy.url().should('include', '/form/users/1/groups/1/users/1');

            // Content area should exist (main.php loaded correctly)
            cy.get('#contentArea', { timeout: 10000 }).should('exist');
        });
    });
});
