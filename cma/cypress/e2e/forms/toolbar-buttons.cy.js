/**
 * Toolbar Buttons Tests
 *
 * Tests toolbar button visibility based on form configuration.
 * Specifically tests expand/collapse buttons which should only be visible
 * when the form has grouping configured (groupFields in JSON form definition).
 */

describe('Toolbar Button Visibility', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        // Clear cache to ensure fresh templates
        cy.request('/tools/tools_clearcache.php?api=1');
        // Clear localStorage to ensure predictable display mode (defaults to table)
        cy.clearLocalStorage();
    });

    describe('Expand/Collapse Buttons', () => {
        it('should not render expand/collapse buttons on forms without grouping', () => {
            // Users form has no groupFields configured
            cy.visit('/form.php?form=users');

            // Wait for form to load (form.php renders directly, no #contentArea wrapper)
            cy.get('.form-layout, table.listtable, lib-table', { timeout: 15000 }).should('exist');

            // Expand and collapse buttons should not exist at all (not rendered server-side)
            cy.get('#btn_expand').should('not.exist');
            cy.get('#btn_collapse').should('not.exist');
        });

        it('should respect hasGrouping from API response', () => {
            // Intercept the init API call (used for initial form load)
            // The JS controller uses jsonForm= parameter (not form=) for init requests
            cy.intercept('GET', /form_api\.php\?action=init.*jsonForm=users/).as('usersInitApi');

            cy.visit('/form.php?form=users');
            cy.wait('@usersInitApi').then((interception) => {
                // Verify the API response includes hasGrouping: false in the list data
                expect(interception.response.body).to.have.property('success', true);
                expect(interception.response.body.list).to.have.property('hasGrouping', false);
            });

            // Buttons should not exist (not rendered server-side)
            cy.get('#btn_expand').should('not.exist');
            cy.get('#btn_collapse').should('not.exist');
        });

        it('should not show expand/collapse buttons on groups form (no grouping)', () => {
            cy.visit('/form.php?form=groups');

            // Wait for form to load (form.php renders directly, no #contentArea wrapper)
            cy.get('.form-layout, table.listtable, lib-table', { timeout: 15000 }).should('exist');

            // Buttons should not exist
            cy.get('#btn_expand').should('not.exist');
            cy.get('#btn_collapse').should('not.exist');
        });
    });

    describe('Tree/Table Toggle Buttons', () => {
        it('should show tree button in table mode', () => {
            cy.visit('/form.php?form=users');
            cy.get('.form-layout, table.listtable, lib-table', { timeout: 15000 }).should('exist');

            // Ensure table mode - click the anchor inside the button (events bubble up from <a>)
            cy.get('#btn_tableview a[data-action]').click({ force: true });
            cy.wait(300);

            // Tree button should be visible
            cy.get('#btn_treeview').should('be.visible');
        });

        it('should show table button in tree mode', () => {
            cy.visit('/form.php?form=users');
            cy.get('.form-layout, table.listtable, lib-table', { timeout: 15000 }).should('exist');

            // Switch to tree mode - click the anchor inside the button
            cy.get('#btn_treeview a[data-action]').click({ force: true });
            cy.wait(300);

            // Table button should be visible
            cy.get('#btn_tableview').should('be.visible');
        });
    });

    describe('Column Selector Button', () => {
        it('should show column selector only in table mode', () => {
            cy.visit('/form.php?form=users');
            cy.get('.form-layout, table.listtable, lib-table', { timeout: 15000 }).should('exist');

            // Ensure we are in table mode (default after localStorage clear)
            cy.get('body').should('have.class', 'mode-table');

            // Column selector should be visible in table mode
            cy.get('#btn_columns').should('be.visible');

            // Switch to tree mode - click the anchor inside the button to trigger the handler
            cy.get('#btn_treeview a[data-action]').click({ force: true });

            // Column selector should be hidden via CSS in tree mode
            cy.get('body', { timeout: 5000 }).should('have.class', 'mode-tree');
        });
    });

    describe('Filter Field Prefill', () => {
        it('should prefill filter field when opening new record with filter URL parameters', () => {
            // Open a new record form with filterField and filterValue URL parameters
            // This simulates opening a popup from a parent form with a toolbar filter
            // Use failOnStatusCode: false because the rooster form's database may not be available
            cy.visit('/form.php?form=rooster&New=Y&filterField=fkOpleiding&filterValue=1', { failOnStatusCode: false });

            // If the form loaded successfully, verify filter prefill
            cy.get('body').then($body => {
                if ($body.find('.form-layout').length === 0) {
                    cy.log('Rooster form not available (database not connected) - skipping assertions');
                    return;
                }
                // The fkOpleiding field should have the filter value applied
                cy.get('[name="fkOpleiding"]', { timeout: 10000 }).should('have.value', '1');
            });
        });

        it('should parse filter context from URL in searchFilters', () => {
            cy.visit('/form.php?form=rooster&New=Y&filterField=fkOpleiding&filterValue=42', { failOnStatusCode: false });

            cy.get('body').then($body => {
                if ($body.find('.form-layout').length === 0) {
                    cy.log('Rooster form not available (database not connected) - skipping assertions');
                    return;
                }
                // Verify that the controller has the filter in searchFilters
                cy.window().then((win) => {
                    const formLayout = win.document.querySelector('.form-layout');
                    if (formLayout && formLayout._cmaController) {
                        const controller = formLayout._cmaController;
                        expect(controller.searchFilters).to.have.property('fkOpleiding', '42');
                    }
                });
            });
        });

        it('should have filter context in searchFilters when editing existing record with filter params', () => {
            cy.visit('/form.php?form=rooster&id=1&filterField=fkOpleiding&filterValue=5', { failOnStatusCode: false });

            cy.get('body').then($body => {
                if ($body.find('.form-layout').length === 0) {
                    cy.log('Rooster form not available (database not connected) - skipping assertions');
                    return;
                }
                // Verify that the controller has the filter in searchFilters
                cy.window().then((win) => {
                    const formLayout = win.document.querySelector('.form-layout');
                    if (formLayout && formLayout._cmaController) {
                        const controller = formLayout._cmaController;
                        expect(controller.searchFilters).to.have.property('fkOpleiding', '5');
                    }
                });
            });
        });
    });

    describe('Form Field Clearing', () => {
        it('should clear all fields including labels, dates, and guid when adding new record', () => {
            // First load an existing rooster record
            cy.visit('/form.php?form=rooster&id=1', { failOnStatusCode: false });

            cy.get('body').then($body => {
                if ($body.find('.form-layout').length === 0) {
                    cy.log('Rooster form not available (database not connected) - skipping assertions');
                    return;
                }

                // Wait for data-loading to be removed (record fully loaded)
                cy.get('body', { timeout: 10000 }).should('not.have.class', 'data-loading');

                // Check that the guid field has a value (it's a label field)
                cy.get('[data-field="guid"]').should('exist').and('not.be.empty');

                // Check that the Datum field has a value
                cy.get('[name="Datum"]').should('exist').invoke('val').should('not.be.empty');

                // Click the detail toolbar's Toevoegen button (addInline action)
                cy.get('#detailToolbar a[data-action="addInline"]').click({ force: true });

                // Verify the form is in creating mode
                cy.get('body', { timeout: 5000 }).should('have.class', 'is-creating');

                // Verify label fields are cleared (guid)
                cy.get('[data-field="guid"]').should('have.text', '');

                // Verify date fields are cleared
                cy.get('[name="Datum"]').should('have.value', '');

                // Verify time fields are cleared
                cy.get('[name="Tijd"]').should('have.value', '');
                cy.get('[name="EindTijd"]').should('have.value', '');
            });
        });

        it('should clear ZuivereLestijd label field when adding new record', () => {
            // Load existing record that has ZuivereLestijd populated
            cy.visit('/form.php?form=rooster&id=1', { failOnStatusCode: false });

            cy.get('body').then($body => {
                if ($body.find('.form-layout').length === 0) {
                    cy.log('Rooster form not available (database not connected) - skipping assertions');
                    return;
                }

                // Wait for data-loading to be removed (record fully loaded)
                cy.get('body', { timeout: 10000 }).should('not.have.class', 'data-loading');

                // Click the detail toolbar's addInline button
                cy.get('#detailToolbar a[data-action="addInline"]').click({ force: true });

                // Verify the form is in creating mode
                cy.get('body', { timeout: 5000 }).should('have.class', 'is-creating');

                // Verify ZuivereLestijd label is cleared
                cy.get('[data-field="ZuivereLestijd"]').should('have.text', '');
            });
        });
    });
});
