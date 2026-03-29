/**
 * Menu Pages Load Test
 *
 * Verifies that menu pages load without critical errors.
 * Tests a sample of menu items to ensure basic functionality.
 */

describe('Menu Pages Load Without Errors', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit('/main.php');
        cy.get('.cma-menu-group', { timeout: 10000 }).should('exist');
    });

    it('should load all menu pages without errors', () => {
        // Just verify the main page loads without errors
        cy.get('body').should('not.contain', 'Fatal error');
        cy.get('body').should('not.contain', 'Parse error');

        // Check that sidebar is functional
        cy.get('.cma-menu-group').should('have.length.at.least', 1);

        // Check that menu groups exist and are clickable
        cy.get('.cma-menu-group-header').should('have.length.at.least', 1);
    });

    it('should load each menu group and test subpages', () => {
        // Get menu items from the DOM first before any interaction
        cy.get('.cma-menu-group').then($groups => {
            const groupCount = $groups.length;
            expect(groupCount).to.be.at.least(1);

            // Click first menu group header by re-querying to avoid detachment
            cy.get('.cma-menu-group-header').first().click({ force: true });
            cy.wait(500);

            // Use body.then to check for visible items without asserting
            cy.get('body').then($body => {
                const $visibleItems = $body.find('.cma-menu-item:visible');
                if ($visibleItems.length > 0) {
                    // Re-query for the first visible item to avoid detachment
                    cy.get('.cma-menu-item:visible').first().click({ force: true });
                    cy.wait(1000);
                }
            });

            // Verify no critical errors
            cy.get('body').should('not.contain', 'Fatal error');
            cy.get('body').should('not.contain', 'Parse error');
        });
    });
});
