/**
 * Authentication Commands
 *
 * Commands for login, logout, and session management.
 */

/**
 * Login to CMA with credentials
 * @param {string} username - Login username
 * @param {string} password - Login password
 */
Cypress.Commands.add('login', (username, password) => {
    // Default to admin credentials if not provided
    const user = username || Cypress.env('adminUser');
    const pass = password || Cypress.env('adminPass');

    if (!user || !pass) {
        throw new Error('Login credentials not configured. Set adminUser/adminPass in cypress.env.json or cypress.config.js');
    }

    cy.session([user, pass], () => {
        cy.visit('/login.php');
        cy.get('#txtLogin').clear().type(user);
        cy.get('#txtPW').clear().type(pass);
        cy.get('#btnLogin').click();
        cy.url().should('include', 'main.php');
        cy.get('[data-cy="sidebar"], #sidebar, .cma-sidebar').should('be.visible');
    }, {
        validate() {
            cy.getCookie('CMAU').should('exist');
        }
    });
});

/**
 * Login as admin user (developer level)
 */
Cypress.Commands.add('loginAsAdmin', () => {
    cy.login(Cypress.env('adminUser'), Cypress.env('adminPass'));
});

/**
 * Login as developer user (alias for loginAsAdmin)
 */
Cypress.Commands.add('loginAsDeveloper', () => {
    cy.login(Cypress.env('adminUser'), Cypress.env('adminPass'));
});

/**
 * Login as regular test user
 */
Cypress.Commands.add('loginAsUser', () => {
    cy.login(Cypress.env('testUser'), Cypress.env('testPass'));
});

/**
 * Logout from CMA
 */
Cypress.Commands.add('logout', () => {
    cy.clearCookies();
    cy.visit('/login.php');
});

/**
 * Check if user is logged in
 */
Cypress.Commands.add('shouldBeLoggedIn', () => {
    cy.getCookie('CMAU').should('exist');
});

/**
 * Check if user is logged out
 */
Cypress.Commands.add('shouldBeLoggedOut', () => {
    cy.getCookie('CMAU').should('not.exist');
});

/**
 * Check if user has admin access
 */
Cypress.Commands.add('shouldBeAdmin', () => {
    cy.getCookie('CMAADM').should('exist');
});

/**
 * Login to front-end site (not CMA) with credentials
 * The front-end uses a different login form than CMA:
 * - Form id: login, fields: #login_id (username), #pwd_id (password)
 * - Session cookie: USID (not CMAU)
 * @param {string} username - Login username or email
 * @param {string} password - Login password
 */
Cypress.Commands.add('frontendLogin', (username, password) => {
    const user = username || Cypress.env('frontendUser');
    const pass = password || Cypress.env('frontendPass');

    if (!user || !pass) {
        throw new Error('Frontend login credentials not configured. Set frontendUser/frontendPass in cypress.config.js');
    }

    cy.session(['frontend', user, pass], () => {
        cy.visit('http://172.29.208.1/index.php');
        cy.get('#login_id', { timeout: 10000 }).should('be.visible').clear().type(user);
        cy.get('#pwd_id').clear().type(pass);
        cy.get('form#login').submit();
        // After login, cookie USID should be set (may redirect to wissel_rol or homepage)
        cy.getCookie('USID', { timeout: 15000 }).should('exist');
        // Wait for page to finish loading
        cy.get('body', { timeout: 15000 }).should('exist');
    }, {
        validate() {
            cy.getCookie('USID').should('exist');
        }
    });
});
