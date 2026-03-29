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

        it('should have menu style selection', () => {
            cy.visit('/preferences');
            cy.get('#menuStyle').should('exist');
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

        it('should save system settings via toolbar save button', () => {
            cy.visit('/preferences');

            // Wait for page to load and dismiss any tips
            cy.get('#preferencesForm', { timeout: 10000 }).should('exist');
            cy.dismissTips();

            // Toggle a setting to make form dirty
            cy.get('#perfLogEnabled').find('.lib-switch').click({ force: true });
            cy.wait(100);

            // Verify the save button is enabled (form is dirty)
            cy.get('#toolbar_save').should('not.have.class', 'disabled');

            // Stub all save POSTs to return success (env file may not be writable in test)
            cy.intercept('POST', '**/preferences.php*', {
                statusCode: 200,
                body: { success: true, message: 'Opgeslagen.' }
            }).as('saveReq');

            // Click the toolbar save button (saves all settings)
            cy.get('#toolbar_save a').click({ force: true });

            // Wait for both POSTs to complete and verify dirty state cleared
            cy.wait('@saveReq');
            cy.wait('@saveReq');
            cy.window().its('prefsDirty', { timeout: 10000 }).should('eq', false);
        });

        it('should toggle and save performance logging', () => {
            cy.visit('/preferences');

            // Wait for page to load and dismiss any tips
            cy.get('#preferencesForm', { timeout: 10000 }).should('exist');
            cy.dismissTips();

            // Close any error header that might be covering elements
            cy.get('body').then($body => {
                if ($body.find('.error-header').length > 0) {
                    cy.get('.error-header').click();
                    cy.wait(200);
                }
            });

            // Toggle the performance logging switch
            cy.get('#perfLogEnabled').find('.lib-switch').click({ force: true });
            cy.wait(200);

            // Dismiss tips before checking save button
            cy.dismissTips();

            // Verify toggling made the form dirty and save button is enabled
            cy.window().its('prefsDirty').should('eq', true);
            cy.get('#toolbar_save').should('not.have.class', 'disabled');

            // Stub save to return success (env file may not be writable in test)
            cy.intercept('POST', '**/preferences.php*', {
                statusCode: 200,
                body: { success: true, message: 'Opgeslagen.' }
            }).as('saveToggle');

            // Save via toolbar
            cy.get('#toolbar_save a').click({ force: true });

            // Wait for both saves to complete
            cy.wait('@saveToggle');
            cy.wait('@saveToggle');

            // Verify dirty state cleared
            cy.window().its('prefsDirty', { timeout: 10000 }).should('eq', false);
        });
    });

    describe('Save Preferences', () => {
        it('should enable save button when form is changed', () => {
            cy.visit('/preferences');

            // Initially save button should be disabled
            cy.get('#toolbar_save').should('have.class', 'disabled');

            // Change a value
            cy.get('#theme').select('dark');

            // Save button should be enabled
            cy.get('#toolbar_save').should('not.have.class', 'disabled');
        });

        it('should save preferences successfully', () => {
            cy.visit('/preferences');

            // Wait for page to load and dismiss any tips
            cy.get('#preferencesForm', { timeout: 10000 }).should('exist');
            cy.dismissTips();

            // Close any error header that might be covering elements
            cy.get('body').then($body => {
                if ($body.find('.error-header').length > 0) {
                    cy.get('.error-header').click();
                    cy.wait(200);
                }
            });

            // Change a value to enable the save button
            // Toggle between 'dark' and 'light' to guarantee a change event fires
            cy.get('#theme').then($select => {
                const currentValue = $select.val();
                const newValue = currentValue === 'dark' ? 'light' : 'dark';
                cy.get('#theme').select(newValue);
            });
            cy.wait(100);

            // Verify button is now enabled
            cy.get('#toolbar_save').should('not.have.class', 'disabled');

            // Dismiss tips before clicking save
            cy.dismissTips();

            // Stub all save POSTs to return success (env file may not be writable in test)
            cy.intercept('POST', '**/preferences.php*', {
                statusCode: 200,
                body: { success: true, message: 'Opgeslagen.' }
            }).as('saveReq');

            // Click save (with force for any overlay issues)
            cy.get('#toolbar_save a').click({ force: true });

            // Wait for both POSTs and verify dirty state cleared
            cy.wait('@saveReq');
            cy.wait('@saveReq');
            cy.window().its('prefsDirty', { timeout: 10000 }).should('eq', false);
        });
    });

    describe('Unsaved Changes Warning', () => {
        it('should track dirty state when form is changed', () => {
            cy.visit('/preferences');

            // Initially not dirty
            cy.window().its('prefsDirty').should('eq', false);

            // Change a value
            cy.get('#theme').select('dark');

            // Should be dirty now
            cy.window().its('prefsDirty').should('eq', true);
        });

        it('should show confirmation dialog when navigating away with unsaved changes', () => {
            cy.visit('/preferences');

            // Make a change
            cy.get('#theme').select('dark');
            cy.wait(100);

            // Try to navigate via menu
            cy.get('#sidebar').contains('a', 'Dashboard').click({ force: true });

            // Should show confirmation dialog
            cy.get('lib-dialog', { timeout: 5000 }).should('exist');
            cy.get('lib-dialog').shadow().find('.dialog-title').should('contain', 'Niet-opgeslagen');
        });

        it('should cancel navigation when clicking Stay button', () => {
            cy.visit('/preferences');

            // Make a change
            cy.get('#theme').select('dark');
            cy.wait(100);

            // Try to navigate via menu
            cy.get('#sidebar').contains('a', 'Dashboard').click({ force: true });

            // Click Stay/Cancel in the dialog (buttons are slotted, not in shadow DOM)
            cy.get('lib-dialog').find('.btn-cancel').click();

            // Should still be on preferences page
            cy.get('#preferencesForm').should('exist');
        });

        it('should proceed with navigation when clicking Leave button', () => {
            cy.visit('/preferences');

            // Make a change
            cy.get('#theme').select('dark');
            cy.wait(100);

            // Try to navigate via menu
            cy.get('#sidebar').contains('a', 'Dashboard').click({ force: true });

            // Click Leave/Confirm in the dialog (buttons are slotted, not in shadow DOM)
            cy.get('lib-dialog').find('.btn-primary').click();

            // Should navigate to dashboard
            cy.get('.dashboard-container, .stats-card, .menu-card', { timeout: 10000 }).should('exist');
        });

        it('should clear dirty state after saving', () => {
            cy.visit('/preferences');

            // Wait for page to load and dismiss any tips
            cy.get('#preferencesForm', { timeout: 10000 }).should('exist');
            cy.dismissTips();

            // Close any error header that might be covering elements
            cy.get('body').then($body => {
                if ($body.find('.error-header').length > 0) {
                    cy.get('.error-header').click();
                    cy.wait(200);
                }
            });

            // Make a change - toggle between values to guarantee a change event fires
            cy.get('#theme').then($select => {
                const currentValue = $select.val();
                const newValue = currentValue === 'dark' ? 'light' : 'dark';
                cy.get('#theme').select(newValue);
            });
            cy.window().its('prefsDirty').should('eq', true);

            // Dismiss tips before clicking save
            cy.dismissTips();

            // Stub all save POSTs to return success (env file may not be writable in test)
            cy.intercept('POST', '**/preferences.php*', {
                statusCode: 200,
                body: { success: true, message: 'Opgeslagen.' }
            }).as('saveReq');

            // Save (with force for any overlay issues)
            cy.get('#toolbar_save a').click({ force: true });

            // Wait for both POSTs and verify dirty state cleared
            cy.wait('@saveReq');
            cy.wait('@saveReq');
            cy.window().its('prefsDirty', { timeout: 10000 }).should('eq', false);
        });

        it('should not show warning when navigating without changes', () => {
            cy.visit('/preferences');

            // Don't make any changes, just navigate
            cy.get('#sidebar').contains('a', 'Dashboard').click({ force: true });

            // Should navigate directly without dialog
            cy.get('lib-dialog').should('not.exist');
            cy.get('.dashboard-container, .stats-card, .menu-card', { timeout: 10000 }).should('exist');
        });
    });
});
