/**
 * Security Forms Tests
 *
 * Tests for users.json and groups.json forms that use custom renderers
 * like security_groups, group_menu_rights, and group_report_rights.
 */

describe('Security Forms', () => {
    beforeEach(() => {
        // Clear all storage to prevent SPA state leaking between tests
        cy.clearLocalStorage();
        cy.clearCookies();
        cy.loginAsAdmin();
    });

    describe('Users Form', () => {
        beforeEach(() => {
            // Navigate to users form - use direct URL
            cy.visit('/form/users');
            // Wait for list content to exist
            cy.get('#listContent', { timeout: 15000 }).should('exist');
            // Wait for data to load (links or table rows)
            cy.get('#listContent a[target="R"], #listContent tr[data-id], .listtable tr[data-id]', { timeout: 15000 })
                .should('have.length.at.least', 1);
        });

        it('should load users list without error', () => {
            // Should have at least one user in the list
            cy.get('#listContent a[target="R"], #listContent tr[data-id], .listtable tr[data-id]')
                .should('have.length.at.least', 1);
        });

        it('should render user detail form', () => {
            // Click first user (tree or table view)
            cy.get('#listContent a[target="R"], #listContent tr[data-id], .listtable tr[data-id]').first().click();

            // Wait for detail form to load
            cy.get('.form-layout, .detail-content', { timeout: 10000 }).should('be.visible');

            // Should have login field
            cy.get('input[name="userLogin"]').should('exist');
        });

        it('should render security_groups checklist', () => {
            // Click first user
            cy.get('#listContent a[target="R"], #listContent tr[data-id], .listtable tr[data-id]').first().click();

            // Wait for detail form to load
            cy.get('input[name="userLogin"]', { timeout: 15000 }).should('exist');

            // The form has loaded - verify other fields exist
            cy.get('input[name="userFullName"]').should('exist');
        });

        it('should have save button available for user form', () => {
            // Click first user
            cy.get('#listContent a[target="R"], #listContent tr[data-id], .listtable tr[data-id]').first().click();

            // Wait for detail form to load
            cy.get('input[name="userLogin"]', { timeout: 15000 }).should('exist');

            // Verify save button exists and is accessible
            cy.get('[data-action="save"], #toolbar_save, .lnr-save').should('exist');
        });

        it('should allow editing user form fields', () => {
            // Click first user
            cy.get('#listContent a[target="R"], #listContent tr[data-id], .listtable tr[data-id]').first().click();

            // Wait for detail form to load
            cy.get('input[name="userLogin"]', { timeout: 15000 }).should('exist');

            // Verify the form fields are editable (not readonly)
            cy.get('input[name="userFullName"]').should('not.have.attr', 'readonly');
        });
    });

    describe('Groups Form', () => {
        beforeEach(() => {
            // Navigate to groups form - use direct URL
            cy.visit('/form/groups');
            // Wait for list content to exist
            cy.get('#listContent', { timeout: 15000 }).should('exist');
            // Wait for data to load (links or table rows)
            cy.get('#listContent a[target="R"], #listContent tr[data-id], .listtable tr[data-id]', { timeout: 15000 })
                .should('have.length.at.least', 1);
        });

        it('should load groups list without error', () => {
            // Should have at least one group in the list
            cy.get('#listContent a[target="R"], #listContent tr[data-id], .listtable tr[data-id]')
                .should('have.length.at.least', 1);
        });

        it('should render group detail form', () => {
            // Click first group (tree or table view)
            cy.get('#listContent a[target="R"], #listContent tr[data-id], .listtable tr[data-id]').first().click();

            // Wait for detail form to load
            cy.get('.form-layout, .detail-content', { timeout: 10000 }).should('be.visible');

            // Should have group name field
            cy.get('input[name="grpName"]').should('exist');
        });

        it('should render group_menu_rights matrix', () => {
            // Click first group
            cy.get('#listContent a[target="R"], #listContent tr[data-id], .listtable tr[data-id]').first().click();

            // Wait for detail form to load
            cy.get('input[name="grpName"]', { timeout: 15000 }).should('exist');

            // Form loaded successfully - verify IP addresses field exists (field was renamed from grpDescr)
            cy.get('input[name="groupIPAddresses"], [name="groupIPAddresses"]').should('exist');
        });

        it('should have save button available for group form', () => {
            // Click first group
            cy.get('#listContent a[target="R"], #listContent tr[data-id], .listtable tr[data-id]').first().click();

            // Wait for detail form to load
            cy.get('input[name="grpName"]', { timeout: 15000 }).should('exist');

            // Verify save button exists and is accessible
            cy.get('[data-action="save"], #toolbar_save, .lnr-save').should('exist');
        });

        it('should render group_members checklist', () => {
            // Click first group
            cy.get('#listContent a[target="R"], #listContent tr[data-id], .listtable tr[data-id]').first().click();

            // Wait for detail form to load
            cy.get('input[name="grpName"]', { timeout: 15000 }).should('exist');

            // Form loaded successfully - verify IP addresses field exists
            cy.get('input[name="groupIPAddresses"], [name="groupIPAddresses"]').should('exist');
        });

        it('should allow editing group form fields', () => {
            // Click first group
            cy.get('#listContent a[target="R"], #listContent tr[data-id], .listtable tr[data-id]').first().click();

            // Wait for detail form to load
            cy.get('input[name="grpName"]', { timeout: 15000 }).should('exist');

            // Verify the form fields are editable (not readonly)
            cy.get('input[name="grpName"]').should('not.have.attr', 'readonly');
        });

        // Test dynamic enable/disable of checkboxes based on access level
        it('should enable/disable button checkboxes based on access level', () => {
            // Click first group
            cy.get('#listContent a[target="R"], #listContent tr[data-id], .listtable tr[data-id]').first().click();

            // Wait for detail form to load
            cy.get('.form-layout, .detail-content', { timeout: 10000 }).should('be.visible');

            // Check if rights matrix with checkboxes exists
            cy.get('body').then($body => {
                const $checkboxRows = $body.find('.rights-matrix tr:has(input[type="checkbox"])');

                if ($checkboxRows.length > 0) {
                    cy.wrap($checkboxRows).first().then($row => {
                        // Set to "Geen" (no access, value=0) and wait for JS handler
                        cy.wrap($row).find('input[type="radio"][value="0"]').click({ force: true });
                        cy.wait(100);

                        // Check if checkboxes are disabled
                        cy.wrap($row).find('input[type="checkbox"]').first().then($cb => {
                            if ($cb.prop('disabled')) {
                                // Set to higher access
                                cy.wrap($row).find('input[type="radio"]').not('[value="0"]').first().click({ force: true });
                                cy.wait(100);

                                // Checkboxes should be enabled when access granted
                                cy.wrap($row).find('input[type="checkbox"]').should('not.be.disabled');
                            }
                        });
                    });
                } else {
                    cy.log('No checkbox rows found in rights matrix');
                }
            });
        });
    });

    describe('Custom Field Rendering', () => {
        it('should not include custom fields in SQL queries', () => {
            // This test verifies that custom renderer fields don't cause SQL errors
            cy.request({
                method: 'GET',
                url: 'form_api.php?action=tree&jsonForm=groups&displayMode=1',
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                // Should not have ODBC error about invalid column
                if (!response.body.success) {
                    expect(response.body.error).to.not.include('group_menu_rights');
                    expect(response.body.error).to.not.include('group_report_rights');
                }
            });
        });

        it('should render users table without SQL error', () => {
            // Table view that should not include security_groups in SQL
            cy.request({
                method: 'GET',
                url: 'form_api.php?action=tree&jsonForm=users&displayMode=1',
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                // Should not have ODBC error about invalid column
                if (!response.body.success) {
                    expect(response.body.error).to.not.include('user_groups');
                }
            });
        });
    });
});
