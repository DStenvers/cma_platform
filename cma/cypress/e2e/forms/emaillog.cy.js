/**
 * E-mail log form tests
 *
 * Tests the emaillog form:
 * - Form loads correctly in tree mode
 * - Toolbar has delete button and redo (Opnieuw verzenden) extra button
 * - Delete API returns valid JSON (not a 500 server error)
 */

describe('E-mail log form', () => {
    beforeEach(() => {
        cy.loginAsDeveloper();
    });

    it('should load the emaillog form in tree mode', () => {
        cy.openFormTree('emaillog');
        cy.get('#listContent', { timeout: 10000 }).should('exist');
    });

    it('should show delete button and redo icon extra button in toolbar', () => {
        cy.openFormTree('emaillog');

        // Delete button should exist in toolbar
        cy.get('[data-action="delete"]').should('exist');

        // Extra button with redo icon (Opnieuw verzenden) should exist
        cy.get('.extra-button').should('exist');
        cy.get('.extra-button .lnr-redo, .extra-button .lnr.lnr-redo').should('exist');
    });

    it('should return valid JSON from delete API (not a 500 error)', () => {
        // Verify the delete endpoint returns proper JSON
        cy.request({
            method: 'GET',
            url: '/form_api.php?action=delete&form=emaillog&id=999999',
            failOnStatusCode: false
        }).then(response => {
            expect(response.status).to.eq(200);
            const body = typeof response.body === 'string'
                ? JSON.parse(response.body)
                : response.body;
            expect(body).to.have.property('success');
            // ID 999999 should not exist, so success should be false
            expect(body.success).to.eq(false);
        });
    });

    it('should return valid JSON from tree API', () => {
        cy.request({
            method: 'GET',
            url: '/form_api.php?action=tree&jsonForm=emaillog',
            failOnStatusCode: false
        }).then(response => {
            expect(response.status).to.eq(200);
            const body = typeof response.body === 'string'
                ? JSON.parse(response.body)
                : response.body;
            expect(body).to.have.property('success');
        });
    });
});
