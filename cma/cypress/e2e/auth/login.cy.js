/**
 * Login Tests
 *
 * Tests for the login functionality and session management.
 */

describe('Login', () => {
    beforeEach(() => {
        cy.visit('/login.php');
    });

    describe('Login Page', () => {
        it('should display login form', () => {
            cy.get('#txtLogin').should('be.visible');
            cy.get('#txtPW').should('be.visible');
            cy.get('#btnLogin').should('be.visible');
        });

        it('should have login button as button element with btn-primary class', () => {
            // Login button should be a <button> element, not an <a>
            cy.get('#btnLogin')
                .should('have.prop', 'tagName', 'BUTTON')
                .and('have.class', 'btn')
                .and('have.class', 'btn-primary')
                .and('have.attr', 'type', 'submit');
        });

        it('should have SSO button labeled "SSO login" with btn-primary class if enabled', () => {
            // Check if SSO login is available on this installation
            cy.get('body').then($body => {
                if ($body.find('.sso-button').length > 0) {
                    cy.get('.sso-button')
                        .should('have.class', 'btn')
                        .and('have.class', 'btn-primary')
                        .and('contain', 'SSO');
                } else {
                    cy.log('SSO not enabled on this installation');
                }
            });
        });

        it('should have focus on username field', () => {
            cy.get('#txtLogin').should('have.focus');
        });

        it('should have empty fields on load', () => {
            cy.get('#txtLogin').should('have.value', '');
            cy.get('#txtPW').should('have.value', '');
        });
    });

    describe('Valid Login', () => {
        it('should login with valid credentials', () => {
            cy.get('#txtLogin').type(Cypress.env('adminUser'));
            cy.get('#txtPW').type(Cypress.env('adminPass'));
            cy.get('#btnLogin').click();

            cy.url().should('include', 'main.php');
            cy.get('[data-cy="sidebar"], #sidebar, .cma-sidebar').should('be.visible');
        });

        it('should set session cookie after login', () => {
            cy.get('#txtLogin').type(Cypress.env('adminUser'));
            cy.get('#txtPW').type(Cypress.env('adminPass'));
            cy.get('#btnLogin').click();

            cy.url().should('include', 'main.php');
            cy.getCookie('CMAU').should('exist');
        });

        it('should redirect to main after successful login', () => {
            cy.get('#txtLogin').type(Cypress.env('adminUser'));
            cy.get('#txtPW').type(Cypress.env('adminPass'));
            cy.get('#btnLogin').click();

            cy.url().should('include', 'main.php');
        });
    });

    describe('Invalid Login', () => {
        it('should show error with shake animation for invalid username', () => {
            cy.get('#txtLogin').type('invaliduser');
            cy.get('#txtPW').type('invalidpass');
            cy.get('#btnLogin').click();

            cy.url().should('include', 'login.php');
            cy.get('.loginerror').should('contain', 'Ongeldig');
            cy.get('form#login div.kader').should('have.class', 'shake');
        });

        it('should show error with shake animation for invalid password', () => {
            cy.get('#txtLogin').type(Cypress.env('adminUser'));
            cy.get('#txtPW').type('wrongpassword');
            cy.get('#btnLogin').click();

            cy.url().should('include', 'login.php');
            cy.get('.loginerror').should('contain', 'Ongeldig');
            cy.get('form#login div.kader').should('have.class', 'shake');
        });

        it('should show error for empty credentials', () => {
            cy.get('#btnLogin').click();

            cy.url().should('include', 'login.php');
        });

        it('should not set session cookie for failed login', () => {
            cy.get('#txtLogin').type('invaliduser');
            cy.get('#txtPW').type('invalidpass');
            cy.get('#btnLogin').click();

            cy.getCookie('CMAU').should('not.exist');
        });
    });

    describe('Form Validation', () => {
        it('should allow submit with Enter key', () => {
            cy.get('#txtLogin').type(Cypress.env('adminUser'));
            cy.get('#txtPW').type(Cypress.env('adminPass') + '{enter}');

            cy.url().should('include', 'main.php');
        });

        it('should trim whitespace from username', () => {
            cy.get('#txtLogin').type('  ' + Cypress.env('adminUser') + '  ');
            cy.get('#txtPW').type(Cypress.env('adminPass'));
            cy.get('#btnLogin').click();

            cy.url().should('include', 'main.php');
        });
    });

    describe('Session Persistence', () => {
        it('should maintain session across page navigation', () => {
            cy.loginAsAdmin();
            cy.visit('/main.php');

            cy.get('[data-cy="sidebar"], #sidebar, .cma-sidebar').should('be.visible');
            cy.getCookie('CMAU').should('exist');

            // Navigate to another page
            cy.visit('/main.php?page=dashboard.php');
            cy.getCookie('CMAU').should('exist');
        });
    });
});
