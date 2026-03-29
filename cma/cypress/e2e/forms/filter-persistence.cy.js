/**
 * Filter Persistence Tests
 *
 * Tests for the filter persistence mechanism that stores selected record IDs
 * in localStorage and uses them to pre-populate filter fields on dependent forms.
 *
 * Key mechanism:
 * - When selecting a record in a form with `filterIdName` configured, the record ID
 *   is saved to localStorage with key `cma_filter_field_` + filterIdName
 * - When opening a form with `filter.field` configured, the filter is pre-populated
 *   from localStorage using key `cma_filter_field_` + filter.field
 */

describe('Filter Persistence', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        // Clear any existing filter values from localStorage
        cy.window().then(win => {
            // Remove filter-related localStorage items
            Object.keys(win.localStorage)
                .filter(key => key.startsWith('cma_filter_field_'))
                .forEach(key => win.localStorage.removeItem(key));
        });
    });

    describe('Opleiding to Rooster filter persistence', () => {
        it('should save opleiding ID to localStorage when selecting a record', () => {
            // Open opleidingen form
            cy.openFormTable('opleidingen');

            // Wait for list to load
            cy.get('#listTable tbody tr', { timeout: 15000 })
                .should('have.length.at.least', 1);

            // Get the ID of the first row before clicking
            cy.get('#listTable tbody tr').first().then($row => {
                // Extract record ID from the row's onclick or data attribute
                const onclick = $row.attr('onclick') || '';
                const dataId = $row.attr('data-id');
                let expectedId = dataId;

                // If no data-id, extract from onclick handler (e.g., loadRecord('123') or loadRecord(123))
                if (!expectedId) {
                    const match = onclick.match(/loadRecord\(['"]?(\d+)['"]?\)/);
                    if (match) {
                        expectedId = match[1];
                    }
                }

                // Click the first row to open the record detail
                // Use scrollIntoView + force:true because the table row may be partially
                // obscured by the sidebar or toolbar depending on viewport
                cy.get('#listTable tbody tr').first().scrollIntoView().click({ force: true });

                // Wait for the detail/sidepanel to appear - this confirms applyRecordData ran
                cy.get('.lib_sidepanel_container, .detail-content, .form-layout[data-dataloaded="true"]', { timeout: 15000 })
                    .should('be.visible');

                // Verify the ID was saved to localStorage
                cy.window({ timeout: 10000 }).should(win => {
                    const savedValue = win.localStorage.getItem('cma_filter_field_fkOpleiding');
                    expect(savedValue).to.not.be.null;
                    expect(savedValue).to.not.eq('');
                    // If we extracted an expected ID, verify it matches
                    if (expectedId) {
                        expect(savedValue).to.eq(String(expectedId));
                    }
                });
            });
        });

        it('should pre-populate rooster filter with saved opleiding ID', () => {
            // Get a valid opleiding ID from the API
            cy.request({
                method: 'GET',
                url: 'form_api.php?action=list&jsonForm=opleidingen&limit=1',
            }).then(response => {
                expect(response.status).to.eq(200);
                if (!response.body.data || response.body.data.length === 0) {
                    cy.log('No opleidingen found - skipping test');
                    return;
                }

                const opleidingId = String(response.body.data[0].id);

                // Set the filter in localStorage before navigating to rooster
                cy.window().then(win => {
                    win.localStorage.setItem('cma_filter_field_fkOpleiding', opleidingId);
                });

                // Now open rooster form
                cy.openFormTable('rooster');

                // Wait for the toolbar filter to exist
                cy.get('.toolbar-filter', { timeout: 15000 }).should('exist');

                // Check that the filter select has the saved value
                cy.get('#toolbarFilter').should('have.value', opleidingId);
            });
        });

        it('should persist filter across page navigation', () => {
            // Get two valid opleiding IDs from the API
            cy.request({
                method: 'GET',
                url: 'form_api.php?action=list&jsonForm=opleidingen&limit=1',
            }).then(response => {
                expect(response.status).to.eq(200);
                if (!response.body.data || response.body.data.length === 0) {
                    cy.log('No opleidingen found - skipping test');
                    return;
                }

                const opleidingId = String(response.body.data[0].id);

                // Set the filter in localStorage
                cy.window().then(win => {
                    win.localStorage.setItem('cma_filter_field_fkOpleiding', opleidingId);
                });

                // Open rooster and verify filter is set
                cy.openFormTable('rooster');
                cy.get('.toolbar-filter', { timeout: 15000 }).should('exist');
                cy.get('#toolbarFilter').should('have.value', opleidingId);

                // Navigate away to opleidingen
                cy.openFormTable('opleidingen');
                cy.get('#listTable', { timeout: 15000 }).should('be.visible');

                // Navigate back to rooster - filter should still be set from localStorage
                cy.openFormTable('rooster');
                cy.get('.toolbar-filter', { timeout: 15000 }).should('exist');
                cy.get('#toolbarFilter').should('have.value', opleidingId);
            });
        });

        it('should update filter when selecting different opleiding', () => {
            // Get at least 2 opleiding IDs from the API
            cy.request({
                method: 'GET',
                url: 'form_api.php?action=list&jsonForm=opleidingen&limit=2',
            }).then(response => {
                expect(response.status).to.eq(200);
                if (!response.body.data || response.body.data.length < 2) {
                    cy.log('Need at least 2 opleidingen - skipping test');
                    return;
                }

                const firstId = String(response.body.data[0].id);
                const secondId = String(response.body.data[1].id);
                expect(firstId).to.not.eq(secondId);

                // Set first ID in localStorage
                cy.window().then(win => {
                    win.localStorage.setItem('cma_filter_field_fkOpleiding', firstId);
                });

                // Open rooster and verify first ID is set
                cy.openFormTable('rooster');
                cy.get('.toolbar-filter', { timeout: 15000 }).should('exist');
                cy.get('#toolbarFilter').should('have.value', firstId);

                // Now update localStorage with second ID (simulating selecting a different opleiding)
                cy.window().then(win => {
                    win.localStorage.setItem('cma_filter_field_fkOpleiding', secondId);
                });

                // Reopen rooster - should pick up new value
                cy.openFormTable('rooster');
                cy.get('.toolbar-filter', { timeout: 15000 }).should('exist');
                cy.get('#toolbarFilter').should('have.value', secondId);
            });
        });
    });

    describe('Invalid filter column protection', () => {
        it('should not fail when filter references a column not in the table', () => {
            // Simulate the bug: pass fkOpleiding as a filter to the opleidingen form
            // fkOpleiding is NOT a column in tblOpleidingen (it's filterIdName metadata)
            // This should NOT cause a SQL error
            cy.request({
                method: 'GET',
                url: 'form_api.php?action=tree&jsonForm=opleidingen&displayMode=2&filters=' +
                    encodeURIComponent(JSON.stringify({ fkOpleiding: '5' })),
                failOnStatusCode: false,
            }).then(response => {
                expect(response.status).to.eq(200);
                const body = response.body;
                // Should succeed (filter is silently ignored, not cause SQL error)
                expect(body.success).to.be.true;
                // Should not contain SQL error about "too few parameters"
                if (body.error) {
                    expect(body.error).to.not.include('parameters');
                    expect(body.error).to.not.include('ODBC');
                }
            });
        });

        it('should still apply valid filters correctly', () => {
            // First get a valid opleiding ID
            cy.request({
                method: 'GET',
                url: 'form_api.php?action=tree&jsonForm=opleidingen&displayMode=2&limit=1',
            }).then(response => {
                expect(response.status).to.eq(200);
                if (response.body.count > 0) {
                    // Searching with a valid field (code) should work
                    cy.request({
                        method: 'GET',
                        url: 'form_api.php?action=tree&jsonForm=opleidingen&displayMode=2&search=test',
                    }).then(searchResponse => {
                        expect(searchResponse.status).to.eq(200);
                        expect(searchResponse.body.success).to.be.true;
                    });
                }
            });
        });
    });

    describe('End-to-end filter workflow', () => {
        it('should complete full workflow: select opleiding then filter rooster', () => {
            // Step 1: Get a valid opleiding ID from the API
            cy.request({
                method: 'GET',
                url: 'form_api.php?action=list&jsonForm=opleidingen&limit=1',
            }).then(response => {
                expect(response.status).to.eq(200);
                if (!response.body.data || response.body.data.length === 0) {
                    cy.log('No opleidingen found - skipping test');
                    return;
                }

                const opleidingId = String(response.body.data[0].id);

                // Step 2: Open opleidingen and click the first record to trigger localStorage save
                cy.openFormTable('opleidingen');
                cy.get('#listTable tbody tr', { timeout: 15000 })
                    .should('have.length.at.least', 1);

                cy.get('#listTable tbody tr').first().scrollIntoView().click({ force: true });

                // Wait for record to load (detail panel visible)
                cy.get('.lib_sidepanel_container, .detail-content, .form-layout[data-dataloaded="true"]', { timeout: 15000 })
                    .should('be.visible');

                // Verify localStorage was set
                cy.window().should(win => {
                    const savedValue = win.localStorage.getItem('cma_filter_field_fkOpleiding');
                    expect(savedValue).to.not.be.null;
                });

                // Step 3: Navigate to rooster form
                cy.openFormTable('rooster');

                // Step 4: Verify filter is pre-populated
                cy.get('.toolbar-filter', { timeout: 15000 }).should('exist');

                // The filter select should have a value (the saved opleiding ID)
                cy.get('#toolbarFilter').invoke('val').should('not.be.empty');

                // Step 5: Verify the page loaded correctly
                cy.get('#listTable, table.listtable, .toolbar-filter', { timeout: 15000 }).should('exist');
            });
        });

        it('should clear filter when localStorage is cleared', () => {
            // First, get a valid opleiding ID from the API
            cy.request({
                method: 'GET',
                url: 'form_api.php?action=list&jsonForm=opleidingen&limit=1',
            }).then(response => {
                expect(response.status).to.eq(200);

                if (!response.body.data || response.body.data.length === 0) {
                    cy.log('No opleidingen found - skipping test');
                    return;
                }

                const validId = String(response.body.data[0].id);

                // Set a valid filter value
                cy.window().then(win => {
                    win.localStorage.setItem('cma_filter_field_fkOpleiding', validId);
                });

                // Open rooster
                cy.openFormTable('rooster');
                cy.get('.toolbar-filter', { timeout: 15000 }).should('exist');

                // Verify filter has the value
                cy.get('#toolbarFilter').should('have.value', validId);

                // Clear localStorage
                cy.window().then(win => {
                    win.localStorage.removeItem('cma_filter_field_fkOpleiding');
                });

                // Refresh/reopen form
                cy.openFormTable('rooster');

                // Filter should be empty or different (not the previously set value)
                // After clearing, the form should either show empty or prompt for selection
                cy.get('#toolbarFilter').should($select => {
                    const value = $select.val();
                    // Either empty, or different from the cleared value
                    // Note: Some filters may default to first option
                    expect(value === '' || value !== validId || value === null).to.be.true;
                });
            });
        });
    });
});
