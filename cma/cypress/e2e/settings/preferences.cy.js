/**
 * Preferences Page Tests
 *
 * Tests for the per-user display preferences. The developer switches and the
 * site-wide system settings live on tools/tools_settings.php (see
 * system-settings.cy.js); this page only links there.
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
        it('should keep the localStorage reset for admins', () => {
            cy.visit('/preferences');
            cy.get('cma-groupbox[caption="Ontwikkelaar"]').should('exist');
        });

        it('should not carry the developer switches any more', () => {
            cy.visit('/preferences');
            cy.get('#debugMode').should('not.exist');
            cy.get('#showDebugOverlay').should('not.exist');
            cy.get('#sqlThreshold').should('not.exist');
        });

        it('should offer a toolbar button to the system settings for admins', () => {
            cy.visit('/preferences');
            cy.get('#btnSystemSettings').should('exist')
                .find('a').should('have.attr', 'href', 'tools/tools_settings.php');
        });
    });

    describe('Autosave', () => {
        it('should show the autosave status instead of a save button', () => {
            cy.visit('/preferences');

            cy.get('#preferencesForm', { timeout: 10000 }).should('exist');
            cy.get('#toolbar_save').should('not.exist');
            // Only the spinner is left: the standing "Wijzigingen worden meteen
            // opgeslagen" line said the same thing on every visit and was dropped.
            cy.get('#autosaveStatus').should('exist').find('#autosaveSpinner').should('exist');
            cy.get('#autosaveStatus').should('not.contain', 'meteen opgeslagen');
            cy.get('#autosaveStatus').invoke('text').invoke('trim').should('eq', '');
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

            // popupStyle needs no page refresh, so the page stays put
            cy.get('#popupStyle').select('popup');

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

            cy.get('#popupStyle').select('sidepanel');
            cy.wait('@saveReq');

            // Navigate via menu - nothing is unsaved, so no confirmation
            cy.get('#sidebar').contains('a', 'Dashboard').click({ force: true });
            cy.get('lib-dialog').should('not.exist');
            cy.get('.dashboard-container, .stats-card, .menu-card', { timeout: 10000 }).should('exist');
        });
    });
});
