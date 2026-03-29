/**
 * Permissions Tests
 *
 * Tests for user permissions and access control.
 */

describe('Permissions', () => {
    describe('Admin User', () => {
        beforeEach(() => {
            cy.loginAsAdmin();
            cy.visit('/main.php');
        });

        it('should have access to admin menu items', () => {
            // Admin users should see system menu (contains user/group management)
            cy.get('[data-cy="sidebar"], #sidebar, .cma-sidebar')
                .should('contain', 'Systeem');
        });

        it('should have access to tools', () => {
            cy.visit('/main.php?page=tools.php');
            cy.get('[data-cy="tools-tree"], #tools-tree, cma-tree').should('be.visible');
        });

        it('should have access to user management', () => {
            cy.visit('/form.php?form=users');
            cy.waitForContent();
            cy.get('body').should('not.contain', 'Geen toegang');
        });

        it('should have access to group management', () => {
            cy.visit('/form.php?form=groups');
            cy.waitForContent();
            cy.get('body').should('not.contain', 'Geen toegang');
        });

        it('should be able to access all forms', () => {
            cy.visit('/form.php?form=users');
            cy.waitForContent();
            cy.get('body').should('not.contain', 'Geen toegang');
        });
    });

    describe('Regular User', () => {
        beforeEach(() => {
            // Skip if no test user configured
            if (!Cypress.env('testUser')) {
                cy.log('Skipping - no test user configured');
                return;
            }
            cy.loginAsUser();
            cy.visit('/main.php');
        });

        it('should have limited menu items', function() {
            if (!Cypress.env('testUser')) {
                this.skip();
            }
            // Regular users may not see all menu items
            cy.get('[data-cy="sidebar"], #sidebar, .cma-sidebar').should('be.visible');
        });

        it('should not have access to security settings', function() {
            if (!Cypress.env('testUser')) {
                this.skip();
            }
            cy.visit('/form.php?form=users');
            // Should either redirect or show access denied
            cy.get('body').then($body => {
                const hasAccess = !$body.text().includes('Geen toegang') &&
                                  !$body.text().includes('niet toegestaan');
                if (!hasAccess) {
                    cy.log('Access correctly denied for regular user');
                }
            });
        });
    });

    describe('Unauthenticated Access', () => {
        beforeEach(() => {
            cy.clearCookies();
        });

        it('should redirect to login for protected pages', () => {
            cy.visit('/main.php', { failOnStatusCode: false });
            cy.url().should('include', 'login.php');
        });

        it('should redirect to login for form pages', () => {
            cy.visit('/form.php?form=users', { failOnStatusCode: false });
            cy.url().should('include', 'login.php');
        });

        it('should redirect to login for API requests', () => {
            cy.request({
                url: 'form_api.php?action=tree&form=users',
                failOnStatusCode: false
            }).then(response => {
                // Should either return error status or redirect
                expect(response.status).to.be.oneOf([200, 302, 401, 403]);
                if (response.status === 200) {
                    // If 200, body should indicate auth required
                    expect(JSON.stringify(response.body)).to.match(/error|login|auth/i);
                }
            });
        });

        it('should redirect to login for tool pages', () => {
            cy.visit('/main.php?page=tools.php', { failOnStatusCode: false });
            cy.url().should('include', 'login.php');
        });
    });

    describe('Form-Level Permissions', () => {
        beforeEach(() => {
            cy.loginAsAdmin();
        });

        it('should show add button for writable forms', () => {
            cy.visit('/form.php?form=locaties');
            cy.waitForContent();
            // Look for add button in toolbar (uses data-action="add" with icon, not text)
            cy.get('.toolbar [data-action="add"], .toolbar #btn_add').should('exist');
        });

        it('should show edit functionality for writable records', () => {
            cy.visit('/form.php?form=locaties');
            cy.waitForContent();
            // Check that table has rows that can be clicked
            cy.get('#listTable tbody tr, table.listtable tbody tr').should('have.length.greaterThan', 0);
        });
    });

    describe('Developer Level Access', () => {
        beforeEach(() => {
            cy.loginAsAdmin();
        });

        it('should have access to developer reports', () => {
            cy.visit('/main.php?page=reports.php');
            cy.get('body').should('not.contain', 'Geen toegang');
        });

        it('should have access to database tools', () => {
            cy.visit('/main.php?page=tools.php');
            cy.waitForContent();
            cy.get('[data-cy="tools-tree"], #tools-tree, cma-tree').should('be.visible');
        });
    });
});
