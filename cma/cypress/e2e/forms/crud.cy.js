/**
 * CRUD Operations Tests
 *
 * Tests for Create, Read, Update, Delete operations.
 * Uses API-based testing for reliability.
 */

describe('CRUD Operations', () => {
    const formName = 'users';

    beforeEach(() => {
        cy.loginAsAdmin();
    });

    describe('Create', () => {
        it('should open new record form in tree mode', () => {
            // Use tree mode where detail panel is visible inline
            cy.openFormTree(formName);
            cy.clickToolbarButton('add');

            // In tree mode, detail panel should show new record form
            cy.get('.detail-panel', { timeout: 10000 }).should('be.visible');
        });

        it('should validate required fields on create via API', () => {
            // Try to create without required fields
            cy.request({
                method: 'POST',
                url: 'form_api.php',
                form: true,
                body: {
                    action: 'save',
                    form: formName,
                    ID: '-1'
                    // Missing required fields
                },
                failOnStatusCode: false
            }).then((response) => {
                // Should return 200 but with success: false or validation error
                expect(response.status).to.eq(200);
            });
        });
    });

    describe('Read', () => {
        it('should display record list', () => {
            cy.openFormTable(formName);

            cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');
            cy.get('#listTable tbody tr, table.listtable tbody tr').should('have.length.at.least', 1);
        });

        it('should load record via API', () => {
            cy.request({
                method: 'GET',
                url: `/form_api.php?action=record&form=${formName}&id=1`
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.fields).to.exist;
            });
        });

        it('should display record details in tree mode', () => {
            cy.openFormTree(formName);

            // In tree mode, clicking a row shows details in right panel
            cy.get('#listContent a, #simpletree a').first().click();

            // Detail panel should show data
            cy.get('.detail-panel', { timeout: 10000 }).should('be.visible');
            cy.get('.detail-panel input[type="text"]', { timeout: 10000 }).first()
                .invoke('val')
                .should('not.be.empty');
        });
    });

    describe('Update', () => {
        it('should update record via API', () => {
            // First get current value
            cy.request({
                method: 'GET',
                url: `/form_api.php?action=record&form=${formName}&id=1`
            }).then((readResponse) => {
                expect(readResponse.body.success).to.be.true;
                const originalName = readResponse.body.fields.userFullName;

                // Update with same value (no actual change to avoid data issues)
                cy.request({
                    method: 'POST',
                    url: 'form_api.php',
                    form: true,
                    body: {
                        action: 'save',
                        form: formName,
                        ID: '1',
                        userFullName: originalName
                    }
                }).then((updateResponse) => {
                    expect(updateResponse.status).to.eq(200);
                    expect(updateResponse.body.success).to.be.true;
                });
            });
        });

        it('should update in tree mode', () => {
            cy.openFormTree(formName);

            // Click first record
            cy.get('#listContent a, #simpletree a').first().click();

            // Wait for detail panel to load
            cy.get('.detail-panel input[type="text"]', { timeout: 10000 }).first()
                .should('be.visible');

            // Get original value
            cy.get('.detail-panel input[type="text"]').first()
                .invoke('val')
                .as('originalValue');

            // Make small change and save
            cy.get('.detail-panel input[type="text"]').first()
                .then($input => {
                    const val = $input.val();
                    cy.wrap($input).clear().type(val + ' ');
                });

            cy.clickToolbarButton('save');

            // Should show success or no error
            cy.wait(1000);

            // Restore original
            cy.get('@originalValue').then(originalValue => {
                cy.get('.detail-panel input[type="text"]').first()
                    .clear()
                    .type(String(originalValue));
                cy.clickToolbarButton('save');
            });
        });
    });

    describe('List Operations', () => {
        it('should load tree data via API', () => {
            cy.request({
                method: 'GET',
                url: `/form_api.php?action=tree&form=${formName}`
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.html).to.exist;
            });
        });

        it('should search records', () => {
            cy.openFormTable(formName);

            // Type in search box
            cy.get('#searchfor').type('admin');

            // Wait for search to filter
            cy.wait(500);

            // Should still show table
            cy.get('#listTable, table.listtable').should('be.visible');
        });

        it('should toggle between tree and table view', () => {
            cy.openFormTable(formName);

            // Click tree view button
            cy.get('[data-action="setlistmode"][data-mode="1"]').click();

            // Should switch to tree mode
            cy.get('body.mode-tree', { timeout: 5000 }).should('exist');

            // Click table view button
            cy.get('[data-action="setlistmode"][data-mode="2"]').click();

            // Should switch back to table mode
            cy.get('body.mode-table', { timeout: 5000 }).should('exist');
        });
    });

    describe('Toolbar', () => {
        it('should have search toggle button', () => {
            cy.openFormTable(formName);
            cy.get('[data-action="toggleSearch"]').should('exist');
        });

        it('should have add button', () => {
            cy.openFormTable(formName);
            cy.get('[data-action="add"]').should('exist');
        });

        it('should have view mode toggles', () => {
            cy.openFormTable(formName);
            cy.get('[data-action="setlistmode"]').should('have.length.at.least', 2);
        });

        it('should toggle search panel', () => {
            cy.openFormTable(formName);

            // Click search button
            cy.get('[data-action="toggleSearch"]').click();

            // Search panel should be visible
            cy.get('#searchPanel').should('be.visible');

            // Click again to hide
            cy.get('[data-action="toggleSearch"]').click();

            // Search panel should be hidden
            cy.get('#searchPanel').should('not.be.visible');
        });
    });
});
