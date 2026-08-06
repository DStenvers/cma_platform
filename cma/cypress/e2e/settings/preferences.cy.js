/**
 * Preferences Page Tests
 *
 * Tests for user preferences and system settings functionality.
 */

describe('Preferences Page', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    describe('User Preferences', () => {
        it('should load the preferences page via clean URL', () => {
            cy.visit('/preferences');
            cy.get('#preferencesForm', { timeout: 10000 }).should('exist');
            cy.get('cma-groupbox[caption="Weergave"]').should('exist');
        });

        it('should have theme selection', () => {
            cy.visit('/preferences');
            cy.get('#theme').should('exist');
            cy.get('#theme option').should('have.length', 3);
        });

        it('should have popup style selection', () => {
            cy.visit('/preferences');
            cy.get('#popupStyle').should('exist');
        });
    });

    describe('Developer Options', () => {
        it('should show developer section for admin', () => {
            cy.visit('/preferences');
            cy.get('cma-groupbox[caption="Ontwikkelaar"]').should('exist');
        });

        it('should have console logging toggle', () => {
            cy.visit('/preferences');
            cy.get('#debugMode').should('exist');
        });

        it('should have debug overlay toggle', () => {
            cy.visit('/preferences');
            cy.get('#showDebugOverlay').should('exist');
        });

        it('should have SQL threshold selection', () => {
            cy.visit('/preferences');
            cy.get('#sqlThreshold').should('exist');
        });
    });

    describe('System Settings (Admin Only)', () => {
        it('should show system settings section for admin', () => {
            cy.visit('/preferences');
            cy.get('cma-groupbox[caption="Systeeminstellingen"]').should('exist');
        });

        it('should have performance logging toggle', () => {
            cy.visit('/preferences');
            cy.get('#perfLogEnabled').should('exist');
        });

        it('should have cache logging toggle', () => {
            cy.visit('/preferences');
            cy.get('#cacheLogEnabled').should('exist');
        });

        it('should have debug logging toggle', () => {
            cy.visit('/preferences');
            cy.get('#debugLogEnabled').should('exist');
        });

        it('should show env file name in description', () => {
            cy.visit('/preferences');
            // System settings section should contain env file reference
            cy.get('cma-groupbox[caption="Systeeminstellingen"]')
                .closest('tr')
                .nextAll('tr')
                .find('p, td')
                .invoke('text')
                .should('match', /\.env\.(local|development|test|acceptance|production)/);
        });

        it('should autosave system settings when a switch is toggled', () => {
            cy.visit('/preferences');

            // Wait for page to load and dismiss any tips
            cy.get('#preferencesForm', { timeout: 10000 }).should('exist');
            cy.dismissTips();

            // Stub all save POSTs to return success (env file may not be writable in test)
            cy.intercept('POST', '**/preferences.php*', {
                statusCode: 200,
                body: { success: true, message: 'Opgeslagen.' }
            }).as('saveReq');

            // Toggling a setting saves by itself - user preferences and system
            // settings go out as two POSTs
            cy.get('#perfLogEnabled').find('.lib-switch').click({ force: true });

            cy.wait('@saveReq');
            cy.wait('@saveReq');
        });
    });

    describe('Autosave', () => {
        it('should show the autosave notice instead of a save button', () => {
            cy.visit('/preferences');

            cy.get('#preferencesForm', { timeout: 10000 }).should('exist');
            cy.get('#toolbar_save').should('not.exist');
            cy.get('#autosaveStatus').should('contain', 'Wijzigingen worden meteen opgeslagen');
        });

        it('should keep the spinner hidden until a save is in flight', () => {
            cy.visit('/preferences');

            cy.get('#preferencesForm', { timeout: 10000 }).should('exist');
            cy.get('#autosaveSpinner').should('not.be.visible');
        });

        it('should save a changed preference without any further action', () => {
            cy.visit('/preferences');

            // Wait for page to load and dismiss any tips
            cy.get('#preferencesForm', { timeout: 10000 }).should('exist');
            cy.dismissTips();

            cy.intercept('POST', '**/preferences.php*', {
                statusCode: 200,
                body: { success: true, message: 'Opgeslagen.' }
            }).as('saveReq');

            // sqlThreshold needs no page refresh, so the page stays put
            cy.get('#sqlThreshold').select('100');

            cy.wait('@saveReq');
            // Spinner goes back to hidden once every request has answered
            cy.get('#autosaveSpinner', { timeout: 10000 }).should('not.be.visible');
        });

        it('should navigate away without an unsaved-changes dialog', () => {
            cy.visit('/preferences');

            cy.get('#preferencesForm', { timeout: 10000 }).should('exist');
            cy.dismissTips();

            cy.intercept('POST', '**/preferences.php*', {
                statusCode: 200,
                body: { success: true, message: 'Opgeslagen.' }
            }).as('saveReq');

            cy.get('#sqlThreshold').select('250');
            cy.wait('@saveReq');

            // Navigate via menu - nothing is unsaved, so no confirmation
            cy.get('#sidebar').contains('a', 'Dashboard').click({ force: true });
            cy.get('lib-dialog').should('not.exist');
            cy.get('.dashboard-container, .stats-card, .menu-card', { timeout: 10000 }).should('exist');
        });
    });
});
