/**
 * Readonly Form Tests
 *
 * Tests that forms with allowEdit=false are properly displayed in readonly mode.
 * Specifically tests the cmamonitoring form which has allowEdit: false.
 */

describe('Readonly Forms', () => {
    beforeEach(() => {
        // Login as admin before each test
        cy.loginAsAdmin();
    });

    describe('CMA Monitoring Form - Config Check', () => {
        it('should have allowEdit=false in form definition', () => {
            // Verify the form's allowEdit setting by checking the form definition file
            cy.readFile('assets/forms/definitions/cmamonitoring.json').then(formDef => {
                expect(formDef.allowEdit).to.be.false;
                expect(formDef.allowAdd).to.be.false;
                expect(formDef.allowDelete).to.be.false;
            });
        });
    });

    describe('CMA Monitoring Form - UI Tests', () => {
        it('should hide the save button for readonly form', () => {
            // First check if there's any monitoring data via API
            cy.apiGetTree('cmamonitoring').then(response => {
                if (!response.body.rows || response.body.rows.length === 0) {
                    cy.log('No monitoring records available - skipping UI test');
                    return; // Skip if no data
                }

                // Open the cmamonitoring form and check toolbar
                cy.openForm('cmamonitoring');

                // Wait for table to load (with longer timeout)
                cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');

                // Click on the first data row
                cy.get('table.listtable tbody tr').first().click({ force: true });

                // Wait for content to load (could be sidepanel or inline)
                cy.waitForContent();

                // Check for sidepanel with detail view OR inline detail
                cy.get('body').then($body => {
                    // Check if sidepanel exists
                    if ($body.find('.sidepanel').length > 0) {
                        // Detail is in sidepanel - check iframe content
                        cy.get('.sidepanel iframe').then($iframe => {
                            cy.wrap($iframe.contents().find('body'))
                                .find('#toolbar_save')
                                .should('not.be.visible');
                        });
                    } else {
                        // Detail is inline - check directly
                        cy.get('#toolbar_save').should('not.be.visible');
                    }
                });
            });
        });

        it('should hide the cancel button for readonly form', () => {
            cy.apiGetTree('cmamonitoring').then(response => {
                if (!response.body.rows || response.body.rows.length === 0) {
                    cy.log('No monitoring records available - skipping UI test');
                    return;
                }

                cy.openForm('cmamonitoring');
                cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');

                cy.get('table.listtable tbody tr').first().click({ force: true });
                cy.waitForContent();

                cy.get('body').then($body => {
                    if ($body.find('.sidepanel').length > 0) {
                        cy.get('.sidepanel iframe').then($iframe => {
                            cy.wrap($iframe.contents().find('body'))
                                .find('#toolbar_cancel')
                                .should('not.be.visible');
                        });
                    } else {
                        cy.get('#toolbar_cancel').should('not.be.visible');
                    }
                });
            });
        });

        it('should not show add button in toolbar for non-addable form', () => {
            // This test doesn't need data, just needs to check toolbar buttons
            cy.visit('/form.php?form=cmamonitoring');

            // Wait for page to load (either table or empty state)
            cy.get('.toolbar, cma-toolbar', { timeout: 15000 }).should('exist');

            // The add button should not exist since allowAdd: false
            cy.get('[data-action="addInline"], [data-action="add"]').should('not.exist');
        });
    });

    describe('Opleidingcode Wijzigen Form - No Add/Delete', () => {
        it('should have allowAdd=false and allowDelete=false in form definition', () => {
            // Verify the form's allowAdd and allowDelete settings
            cy.readFile('../assets/forms/opleidingcode_wijzigen.json').then(formDef => {
                expect(formDef.allowAdd).to.be.false;
                expect(formDef.allowDelete).to.be.false;
            });
        });

        it('should not show add button in toolbar', () => {
            cy.visit('/form.php?form=opleidingcode_wijzigen');

            // Wait for page to load
            cy.get('.toolbar, cma-toolbar', { timeout: 15000 }).should('exist');

            // The add button should not exist since allowAdd: false
            cy.get('[data-action="addInline"], [data-action="add"]').should('not.exist');
        });

        it('should not show delete option in row menu', () => {
            cy.apiGetTree('opleidingcode_wijzigen').then(response => {
                if (!response.body.rows || response.body.rows.length === 0) {
                    cy.log('No records available - skipping delete button test');
                    return;
                }

                cy.visit('/form.php?form=opleidingcode_wijzigen');
                cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');

                // Click on row menu (three dots)
                cy.get('table.listtable tbody tr').first().find('.row-menu-btn, .lnr-menu').first().click({ force: true });

                // Delete option should not exist
                cy.get('.row-menu [data-action="delete"], .context-menu [data-action="delete"]').should('not.exist');
            });
        });
    });

    describe('Readonly Form Direct Access', () => {
        it('should load record in readonly mode via direct URL', () => {
            // Get a valid record ID first
            cy.apiGetTree('cmamonitoring').then(response => {
                if (response.body.rows && response.body.rows.length > 0) {
                    const recordId = response.body.rows[0].ID;

                    // Access the record directly
                    cy.visit(`/form.php?form=cmamonitoring&id=${recordId}`);
                    cy.waitForContent();

                    // Should have form-readonly class on body
                    cy.get('body', { timeout: 10000 }).should('have.class', 'form-readonly');

                    // Should show readonly indicator with tooltip
                    cy.get('#readonlyIndicator, .toolbar-readonly-indicator', { timeout: 10000 })
                        .should('exist')
                        .and('have.attr', 'data-tooltip', 'Alleen lezen');

                    // Save and cancel buttons should be hidden
                    cy.get('#toolbar_save').should('not.be.visible');
                    cy.get('#toolbar_cancel').should('not.be.visible');
                } else {
                    cy.log('No records in cmamonitoring table - skipping direct access test');
                }
            });
        });
    });
});
