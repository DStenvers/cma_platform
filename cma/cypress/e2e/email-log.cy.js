/**
 * E-mail Log Management Tests
 *
 * Tests for the email log admin screen:
 * - Page loads with correct components (lib-table, cma-toolbar)
 * - Table displays expected columns
 * - Context menu has resend/delete options
 * - Resend API endpoint works
 */

describe('E-mail Log Management', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    describe('Page Access', () => {
        it('should load the email log form page', () => {
            cy.visit('/form.php?form=emaillog');
            cy.get('lib-table', { timeout: 10000 }).should('exist');
        });

        it('should show the toolbar with search', () => {
            cy.visit('/form.php?form=emaillog');
            // JSON-form pages render the toolbar as #listToolbar (FormTemplate),
            // not as the <cma-toolbar> component — that one belongs to tools pages.
            cy.get('#listToolbar.toolbar', { timeout: 10000 }).should('exist');
            cy.get('lib-search-input').should('exist');
        });
    });

    describe('Table Columns', () => {
        it('should display expected column headers', () => {
            cy.visit('/form.php?form=emaillog');
            cy.get('lib-table', { timeout: 10000 }).should('exist');

            const expectedHeaders = ['Datum', 'Aan', 'Onderwerp', 'Status', 'Fout'];
            expectedHeaders.forEach(header => {
                cy.get('.listheader th').should('contain.text', header);
            });
        });
    });

    describe('Menu Entry', () => {
        it('should appear in the Systeem menu for admins', () => {
            cy.visit('/main.php');
            // Look for the email log menu item
            cy.contains('E-mail log').should('exist');
        });
    });

    describe('Resend API', () => {
        it('should return error for invalid ID', () => {
            cy.request({
                method: 'POST',
                url: '/api/email-actions.php',
                form: true,
                body: { action: 'resend', id: 999999 },
                failOnStatusCode: false
            }).then(response => {
                expect(response.status).to.eq(200);
                const body = typeof response.body === 'string'
                    ? JSON.parse(response.body)
                    : response.body;
                expect(body.success).to.eq(false);
                expect(body.error).to.contain('niet gevonden');
            });
        });

        it('should reject non-admin access', () => {
            // Logout and try without auth
            cy.clearCookies();
            cy.request({
                method: 'POST',
                url: '/api/email-actions.php',
                form: true,
                body: { action: 'resend', id: 1 },
                failOnStatusCode: false
            }).then(response => {
                // Cookies are cleared, so this is an ANONYMOUS call: bootstrap.inc
                // answers API endpoints with 401 + JSON so a fetch()-client can tell
                // "not logged in" from "no results". 403 is the other case — logged
                // in, but not an admin — which email-actions.php returns itself.
                expect(response.status).to.equal(401);
                expect(response.body).to.have.property('requireLogin', true);
            });
        });
    });

    describe('Context Menu', () => {
        it('should have resend extra button defined', () => {
            cy.visit('/form.php?form=emaillog');
            cy.get('lib-table', { timeout: 10000 }).should('exist');

            // Check that CMA.emailLog.resend function exists
            cy.window().then(win => {
                expect(win.CMA.emailLog).to.exist;
                expect(win.CMA.emailLog.resend).to.be.a('function');
            });
        });
    });
});
