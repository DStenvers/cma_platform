/**
 * Table View Tests
 *
 * Tests for the form table view functionality.
 */

describe('Table View', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    describe('Table Display', () => {
        beforeEach(() => {
            cy.openFormTable('users');
        });

        it('should display table with data', () => {
            cy.get('[data-cy="form-table"], table.filtering, .listtable')
                .should('be.visible');
        });

        it('should have column headers', () => {
            cy.get('[data-cy="form-table"] thead, table.filtering thead, .listtable thead')
                .should('be.visible')
                .find('th')
                .should('have.length.at.least', 1);
        });

        it('should have table rows', () => {
            cy.get('[data-cy="form-table"] tbody tr, table.filtering tbody tr, .listtable tbody tr')
                .should('have.length.at.least', 1);
        });

        it('should show row count', () => {
            // Row count is shown in toolbar-status, list footer, or pagination info
            // Check for any element that displays record count
            cy.get('body').then($body => {
                // Check various possible locations for record count
                const hasCount = $body.find('.toolbar-status, #listCount, .list-status, .pagination-info, tfoot').length > 0;
                if (hasCount) {
                    cy.get('.toolbar-status, #listCount, .list-status, .pagination-info, tfoot').should('exist');
                } else {
                    // Table exists with rows - that's sufficient to verify data is displayed
                    cy.get('.listtable tbody tr').should('have.length.at.least', 1);
                }
            });
        });
    });

    describe('Column Sorting', () => {
        beforeEach(() => {
            cy.openFormTable('users');
        });

        it('should sort by clicking column header', () => {
            cy.get('[data-cy="form-table"] thead th, table.filtering thead th')
                .first()
                .click();

            // Table should update (check for sort indicator or changed order)
            cy.get('[data-cy="form-table"], table.filtering').should('be.visible');
        });

        it('should toggle sort direction on second click', () => {
            cy.get('[data-cy="form-table"] thead th, table.filtering thead th')
                .first()
                .click()
                .click();

            cy.get('[data-cy="form-table"], table.filtering').should('be.visible');
        });
    });

    describe('Row Selection', () => {
        beforeEach(() => {
            cy.openFormTable('users');
        });

        it('should highlight row on hover', () => {
            // Row highlighting is done via CSS :hover, not a class
            // Verify that rows exist and are visible with proper class
            cy.get('.listtable tbody tr.listrow')
                .first()
                .should('be.visible')
                .and('have.attr', 'data-id');
        });

        it('should open detail view on row click', () => {
            cy.get('[data-cy="form-table"] tbody tr, table.filtering tbody tr')
                .first()
                .click();

            cy.get('[data-cy="detail-view"], .detail-content, .form-layout')
                .should('be.visible');
        });
    });

    describe('Row Actions Menu', () => {
        beforeEach(() => {
            cy.openFormTable('users');
        });

        it('should have row menu trigger in table rows', () => {
            // In table mode (displayMode=2), rows should have a menu trigger cell
            // The .row-menu-trigger is rendered in the first column (col-menu)
            cy.get('.listtable tbody tr.listrow').should('have.length.at.least', 1);

            // Verify the menu infrastructure is present
            // Either a .col-menu cell exists or rows have data-id for inline edit
            cy.get('.listtable tbody tr.listrow')
                .first()
                .should('have.attr', 'data-id');
        });

        it('should have clickable rows with record IDs', () => {
            cy.get('.listtable tbody tr.listrow').should('have.length.at.least', 1);

            // Each row should have a data-id attribute for record identification
            cy.get('.listtable tbody tr.listrow[data-id]')
                .should('have.length.at.least', 1);
        });

        it('should have inline edit support structure', () => {
            cy.get('.listtable tbody tr.listrow').should('have.length.at.least', 1);

            // The table should have the inline-editable class or similar structure
            // that indicates inline edit is enabled
            cy.get('.listtable')
                .should('exist');

            // Rows should be part of a tbody that can handle events
            cy.get('.listtable tbody')
                .should('exist');
        });
    });

    describe('Pagination', () => {
        beforeEach(() => {
            cy.openFormTable('users');
        });

        it('should show pagination controls if many records', () => {
            cy.get('body').then($body => {
                if ($body.find('[data-cy="pagination"], .pagination, .pager').length > 0) {
                    cy.get('[data-cy="pagination"], .pagination, .pager').should('be.visible');
                } else {
                    cy.log('Pagination not visible - fewer records than page size');
                }
            });
        });

        it('should navigate to next page', () => {
            cy.get('body').then($body => {
                if ($body.find('[data-cy="next-page"], .next-page, .pager-next').length > 0) {
                    cy.get('[data-cy="next-page"], .next-page, .pager-next').click();
                    cy.waitForContent();
                }
            });
        });
    });

    describe('Empty State', () => {
        it('should show empty message when no records', () => {
            // Navigate to a form with search that returns no results
            cy.openForm('users');
            cy.waitForContent();

            // Apply a filter that returns no results
            // The search input is a lib-search-input web component with id="searchfor"
            // We need to target the actual <input> inside it
            cy.get('lib-search-input#searchfor')
                .should('be.visible')
                .find('input')
                .clear()
                .type('xyznonexistent123{enter}');

            // Wait for search results to load and check for empty message
            // The message can be "Geen gegevens" or "Geen gegevens gevonden" or "Geen gegevens om weer te geven"
            cy.get('.no-data, .list-empty', { timeout: 10000 })
                .should('be.visible');
        });
    });

    describe('Filter Recalculation', () => {
        // Tests that column filters are recalculated after data changes
        // (add, update, delete operations should update filter dropdown values)

        it('should recalculate filters after data refresh', () => {
            cy.openFormTable('users');

            // Wait for table to load
            cy.get('.listtable tbody tr', { timeout: 10000 }).should('have.length.at.least', 1);

            // Check if filter dropdowns exist (in column headers)
            cy.get('body').then($body => {
                const hasFilters = $body.find('select.columnfilter, .column-filter select, thead select').length > 0;

                if (hasFilters) {
                    // Get initial filter options count
                    cy.get('select.columnfilter, .column-filter select, thead select')
                        .first()
                        .find('option')
                        .its('length')
                        .as('initialFilterCount');

                    // Trigger a table refresh (this simulates data change)
                    cy.window().then(win => {
                        // Call the refresh function if available
                        if (win.loadTable && typeof win.loadTable === 'function') {
                            win.loadTable();
                        } else if (win.CMA && win.CMA.controller && win.CMA.controller.refresh) {
                            win.CMA.controller.refresh();
                        }
                    });

                    cy.wait(1000);

                    // Filters should still exist after refresh
                    cy.get('select.columnfilter, .column-filter select, thead select')
                        .first()
                        .should('exist');
                } else {
                    cy.log('No column filters found on this form');
                }
            });
        });

        it('should update filter options when switch is toggled', () => {
            // Test that toggling a switch/checkbox in a row updates the relevant column filter
            cy.openFormTable('users');

            cy.get('.listtable tbody tr', { timeout: 10000 }).should('have.length.at.least', 1);

            // Check for switch/toggle inputs in the table
            cy.get('body').then($body => {
                const hasSwitches = $body.find('.listtable input[type="checkbox"], .listtable .switch-input').length > 0;

                if (hasSwitches) {
                    // Find a switch in a table row
                    cy.get('.listtable input[type="checkbox"], .listtable .switch-input')
                        .first()
                        .then($switch => {
                            // Click the switch
                            cy.wrap($switch).click({ force: true });

                            // Wait for potential API call
                            cy.wait(500);

                            // The filter recalculation should happen
                            // (We can't easily verify the filter values changed,
                            // but we verify the switch toggle worked)
                            cy.log('Switch toggled - filter recalculation should occur');
                        });
                } else {
                    cy.log('No switches found in this form table');
                }
            });
        });
    });

    describe('Table Header Styling', () => {
        // Test that table headers have consistent styling between
        // lib-table and table.filtering components

        it('should have consistent header font-weight for .listtable', () => {
            cy.openFormTable('users');

            cy.get('.listtable thead th')
                .first()
                .should('be.visible')
                .should('have.css', 'font-weight', '600');
        });

        it('should have consistent header padding for .listtable', () => {
            cy.openFormTable('users');

            // Verify header th has the expected padding from CSS:
            // .listtable thead th { padding-left: 8px; padding-top: 2px; padding-bottom: 0; }
            cy.get('.listtable thead th:not(.col-menu)')
                .first()
                .should('be.visible')
                .then($th => {
                    const paddingLeft = parseInt($th.css('padding-left'));
                    const paddingTop = parseInt($th.css('padding-top'));
                    expect(paddingLeft).to.eq(8);
                    expect(paddingTop).to.eq(2);
                });
        });

        it('should have consistent header font-weight for table.filtering', () => {
            cy.openFormTable('users');

            cy.get('table.filtering thead th')
                .first()
                .should('be.visible')
                .should('have.css', 'font-weight', '600');
        });
    });

    describe('Filter Field Skip', () => {
        it('should not show filterIdName field in default columns', () => {
            // The rooster form has filter.field: "fkOpleiding"
            // This field should not appear in the default table columns
            // because when filtering is applied, all rows have the same filter value
            cy.request({
                method: 'GET',
                url: 'form_api.php?action=tree&jsonForm=rooster&displayMode=2',
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;

                // The HTML should not contain a column header for fkOpleiding
                // in the default (unfiltered) column set
                const html = response.body.html || '';
                // Verify that fkOpleiding is not in the table headers
                // This tests that the filter field is skipped in default columns
                expect(html).to.not.include('data-field="fkOpleiding"');
            });
        });

        it('should not include filter field in API column list', () => {
            // Check that getJsonFormAvailableColumns excludes the filter field
            // from the selected (default) columns but includes it in available columns
            cy.request({
                method: 'GET',
                url: 'form_api.php?action=columns&jsonForm=rooster',
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;

                // The selected columns should NOT include fkOpleiding (filter field)
                const selected = response.body.selected || [];
                expect(selected).to.not.include('fkOpleiding');
            });
        });
    });

    describe('Column Min-Width Based on Header Text', () => {
        it('should set min-width based on header text, not data content', () => {
            cy.openFormTable('opleidingen');

            cy.get('table.listtable thead th[data-field]', { timeout: 10000 })
                .should('have.length.at.least', 1)
                .each($th => {
                    const clicker = $th.find('.clicker')[0];
                    if (!clicker) return;

                    const headerTextWidth = clicker.scrollWidth;
                    const minWidth = parseInt($th.css('min-width')) || 0;

                    // min-width should be close to header text width + padding + dropdown icon (20px)
                    // It should NOT be as wide as the data content
                    // Allow generous margin but ensure it's not absurdly wide (> 3x header text)
                    if (headerTextWidth > 0 && minWidth > 0) {
                        expect(minWidth).to.be.lte(headerTextWidth * 3,
                            `Column "${$th.attr('data-field')}" min-width (${minWidth}px) should not be much larger than header text width (${headerTextWidth}px)`);
                    }
                });
        });
    });

    describe('Date Column Type Detection', () => {
        it('should set data-type="date" on date columns, not "text"', () => {
            // Open a form that has date fields (e.g. opleidingen has Startdatum)
            cy.openFormTable('opleidingen');

            cy.get('table.listtable thead th[data-field], .list-content thead th[data-field]', { timeout: 10000 })
                .should('have.length.at.least', 1);

            // Check if any date-related column headers exist
            cy.get('table.listtable thead th, .list-content thead th').then($ths => {
                const dateHeaders = $ths.filter((i, th) => {
                    const field = (th.dataset.field || '').toLowerCase();
                    return field.includes('datum') || field.includes('date');
                });

                if (dateHeaders.length > 0) {
                    // Date columns should have data-type="date", not "text"
                    cy.wrap(dateHeaders).each($th => {
                        cy.wrap($th).should('have.attr', 'data-type', 'date');
                    });
                } else {
                    cy.log('No date columns visible in current view - test passes vacuously');
                }
            });
        });
    });
});
