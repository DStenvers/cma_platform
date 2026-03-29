/**
 * Logout Tests
 *
 * Tests for logout functionality and session cleanup.
 */

describe('Logout', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit('/main.php');
    });

    /**
     * Helper function to open user dropdown menu
     * CSS :hover doesn't work with Cypress triggers, so we add the 'open' class
     */
    const openUserDropdown = () => {
        cy.get('#userDropdown').invoke('addClass', 'open');
        cy.get('#userDropdown').should('be.visible');
    };

    describe('Logout Action', () => {
        it('should logout via user menu', () => {
            openUserDropdown();
            cy.get('#menuLogout').click();

            cy.url().should('include', 'login.php');
        });

        it('should clear session cookie on logout', () => {
            cy.getCookie('CMAU').should('exist');

            openUserDropdown();
            cy.get('#menuLogout').click();

            cy.getCookie('CMAU').should('not.exist');
        });

        it('should redirect to login page after logout', () => {
            openUserDropdown();
            cy.get('#menuLogout').click();

            cy.url().should('include', 'login.php');
            cy.get('#txtLogin').should('be.visible');
        });
    });

    describe('Session Expiry', () => {
        it('should redirect to login when accessing protected page without session', () => {
            cy.clearCookies();
            cy.visit('/main.php');

            cy.url().should('include', 'login.php');
        });

        it('should redirect to login when accessing form without session', () => {
            cy.clearCookies();
            cy.visit('/form.php?form=users');

            cy.url().should('include', 'login.php');
        });
    });

    describe('Post-Logout Behavior', () => {
        it('should not be able to use back button to access protected content', () => {
            // Logout
            openUserDropdown();
            cy.get('#menuLogout').click();

            // Try to go back
            cy.go('back');

            // Should either redirect to login or show login page
            cy.url().should('include', 'login');
        });

        it('should require fresh login after logout', () => {
            // Logout
            openUserDropdown();
            cy.get('#menuLogout').click();

            // Try to access protected page
            cy.visit('/main.php');
            cy.url().should('include', 'login.php');

            // Login again should work
            cy.get('#txtLogin').type(Cypress.env('adminUser'));
            cy.get('#txtPW').type(Cypress.env('adminPass'));
            cy.get('#btnLogin').click();

            cy.url().should('include', 'main.php');
        });
    });
});
