/**
 * System Settings Tests (tools/tools_settings.php)
 *
 * Site-wide settings (notifications, logging, error display) in grouped
 * controls, plus the current user's developer switches in the last group.
 * Saving goes through one POST; the env file may not be writable on the test
 * site, so the save request is stubbed.
 */

describe('System Settings', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit('/tools/tools_settings.php');
        cy.get('#settingsForm', { timeout: 10000 }).should('exist');
    });

    describe('Groups', () => {
        it('should show the eight groups', () => {
            cy.get('cma-groupbox[caption="Meldingen per e-mail"]').should('exist');
            cy.get('cma-groupbox[caption="Logging"]').should('exist');
            cy.get('cma-groupbox[caption="Mailserver"]').should('exist');
            cy.get('cma-groupbox[caption="Lijsten"]').should('exist');
            cy.get('cma-groupbox[caption="Cache"]').should('exist');
            cy.get('cma-groupbox[caption="Time-outs"]').should('exist');
            cy.get('cma-groupbox[caption="Foutweergave"]').should('exist');
            cy.get('cma-groupbox[caption="Ontwikkelaar (alleen voor jou)"]').should('exist');
        });

        it('should have the notification controls', () => {
            cy.get('lib-switch#error_mail_enabled').should('exist');
            cy.get('input#error_mail_to').should('exist');
            cy.get('lib-switch#notfound_mail_enabled').should('exist');
            cy.get('input#notfound_mail_to').should('exist');
            cy.get('input#deploy_alert_email').should('exist');
        });

        it('should have the logging toggles and the retention field', () => {
            cy.get('lib-switch#perf_log_enabled').should('exist');
            cy.get('lib-switch#cache_log_enabled').should('exist');
            cy.get('lib-switch#debug_log_enabled').should('exist');
            cy.get('lib-switch#email_log_enabled').should('exist');
            cy.get('lib-switch#sql_log_enabled').should('exist');
            cy.get('input#error_log_retention_days').should('have.attr', 'type', 'number');
        });

        it('should have the mail server fields, password write-only', () => {
            cy.get('input#mail_host').should('have.attr', 'type', 'text');
            cy.get('input#mail_port').should('have.attr', 'type', 'number');
            cy.get('input#mail_username').should('have.attr', 'type', 'text');
            cy.get('input#mail_password').should('have.attr', 'type', 'password').and('have.value', '');
        });

        it('should start with only the notifications group open, and open a group on filter', () => {
            cy.get('cma-groupbox[caption="Cache"]').should('have.attr', 'collapsed');
            cy.get('cma-groupbox[caption="Meldingen per e-mail"]').should('not.have.attr', 'collapsed');
            cy.get('#settingsFilter').type('cachetijd');
            cy.get('input#cache_default_ttl').closest('tr').should('not.have.class', 'cma-tool__settings-hidden');
            cy.get('input#error_mail_to').closest('tr').should('have.class', 'cma-tool__settings-hidden');
        });

        it('should have the developer switches', () => {
            cy.get('lib-switch#debugMode').should('exist');
            cy.get('lib-switch#showDebugOverlay').should('exist');
            cy.get('select#sqlThreshold option').should('have.length', 5);
        });
    });

    describe('Saving', () => {
        it('should post every control in one request', () => {
            cy.intercept('POST', '**/tools_settings.php*', {
                statusCode: 200,
                body: { success: true, errors: {} }
            }).as('save');

            cy.get('#btnSaveSettings').click();

            cy.wait('@save').its('request.body').then(body => {
                expect(body).to.include('action=save');
                expect(body).to.include('error_mail_enabled=');
                expect(body).to.include('error_log_retention_days=');
                expect(body).to.include('force_debug=');
                expect(body).to.include('sqlThreshold=');
            });
            cy.get('lib-toaster, lib-toast').should('exist');
        });

        it('should mark the field a validation error names', () => {
            cy.intercept('POST', '**/tools_settings.php*', {
                statusCode: 200,
                body: { success: false, errors: { error_mail_to: 'Ongeldig e-mailadres: x' } }
            }).as('save');

            cy.get('#btnSaveSettings').click();
            cy.wait('@save');
            cy.get('input#error_mail_to').should('have.class', 'cma-tool__settings-invalid');
        });
    });
});
