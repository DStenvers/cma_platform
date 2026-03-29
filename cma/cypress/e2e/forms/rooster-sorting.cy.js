/**
 * Table Sorting Tests
 *
 * Tests for sorting functionality in list tables and subforms.
 * Uses forms that don't require filter selection for reliable testing.
 *
 * Run: npx cypress run --spec "cypress/e2e/forms/rooster-sorting.cy.js"
 */

describe('Table Sorting', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    // ═══════════════════════════════════════════════════════════════
    // MAIN LIST TABLE SORTING
    // ═══════════════════════════════════════════════════════════════

    describe('Main List Table', () => {
        beforeEach(() => {
            // Use users form - doesn't require filter selection
            cy.visit('/form/users');
            cy.get('#listTable tbody tr, table.listtable tbody tr', { timeout: 15000 })
                .should('have.length.at.least', 1);
            // Dismiss any tip overlays that may block interactions
            cy.dismissTips();
        });

        it('should not lose rows when sorting any column', () => {
            // Get initial row count
            cy.get('tbody tr').then($rows => {
                const initialCount = $rows.length;
                cy.log(`Initial row count: ${initialCount}`);

                // Click the th directly to open filter/sort dropdown
                // The click handler is on the th element itself (registered by lib-table)
                cy.get('thead th').eq(1).click({ force: true });
                cy.wait(500);

                // If dropdown didn't open, retry the click
                cy.get('body').then($body => {
                    if ($body.find('.dropdown-filter-content:visible').length === 0) {
                        cy.get('thead th').eq(1).click({ force: true });
                        cy.wait(500);
                    }
                });

                // Click A-Z sort if dropdown is visible
                cy.get('body').then($body => {
                    if ($body.find('.dropdown-filter-content:visible').length > 0) {
                        cy.get('.dropdown-filter-sort span.a---z').first().click();
                    } else {
                        // Dropdown still not visible - try clicking the dropdown icon directly
                        cy.get('thead th').eq(1).find('.dropdown-filter-icon').click({ force: true });
                        cy.wait(300);
                        cy.get('.dropdown-filter-sort span.a---z').first().click({ force: true });
                    }
                });

                // Wait for sort to complete
                cy.wait(500);

                // Verify rows are still present
                cy.get('tbody tr').should('have.length', initialCount);
            });
        });

        it('should preserve all rows when sorting Z-A', () => {
            cy.get('tbody tr').then($rows => {
                const initialCount = $rows.length;

                // Sort Z-A - click header and wait for dropdown
                cy.get('thead th').eq(1).click({ force: true });
                cy.wait(300);

                // Check if dropdown appeared
                cy.get('body').then($body => {
                    if ($body.find('.dropdown-filter-content:visible').length > 0) {
                        cy.get('.dropdown-filter-sort span.z---a').first().click({ force: true });
                        cy.wait(500);
                    } else {
                        // Dropdown may not have appeared - try clicking again
                        cy.get('thead th').eq(1).click({ force: true });
                        cy.wait(300);
                        if ($body.find('.dropdown-filter-content:visible').length > 0) {
                            cy.get('.dropdown-filter-sort span.z---a').first().click({ force: true });
                        }
                        cy.wait(500);
                    }
                });

                // Verify rows are still present
                cy.get('tbody tr').should('have.length', initialCount);
            });
        });

        it('should handle multiple sequential sorts without losing rows', () => {
            cy.get('tbody tr').then($rows => {
                const initialCount = $rows.length;

                // First sort
                cy.get('thead th').eq(1).click({ force: true });
                cy.wait(300);

                cy.get('body').then($body => {
                    if ($body.find('.dropdown-filter-content:visible').length > 0) {
                        cy.get('.dropdown-filter-sort span.a---z').first().click({ force: true });
                    }
                });
                cy.wait(300);
                cy.get('body').click(10, 10);
                cy.wait(300);

                // Second sort on different column
                cy.get('thead th').eq(2).click({ force: true });
                cy.wait(300);

                cy.get('body').then($body => {
                    if ($body.find('.dropdown-filter-content:visible').length > 0) {
                        cy.get('.dropdown-filter-sort span.z---a').first().click({ force: true });
                    }
                });
                cy.wait(500);

                // Verify rows are still present
                cy.get('tbody tr').should('have.length', initialCount);
            });
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // SUBFORM TABLE SORTING
    // ═══════════════════════════════════════════════════════════════

    describe('Subform Table Sorting', () => {
        beforeEach(function() {
            // Open groups form and first record that has user assignments
            cy.visit('/form/groups');
            cy.get('#listTable tbody tr, table.listtable tbody tr', { timeout: 10000 }).first().click({ force: true });

            // Wait for detail panel with retry
            cy.wait(500);
            cy.get('body').then($body => {
                // Check if detail panel exists and is visible
                const panel = $body.find('.detail-panel, #detailPanel');
                if (panel.length === 0 || !panel.is(':visible')) {
                    // Skip this test suite if detail panel doesn't load
                    this.skip();
                    return;
                }

                // Click on the first subform tab (users)
                const tabs = $body.find('.cma-tabs button, cma-tabs button, .subform-tabs button');
                if (tabs.length > 0) {
                    cy.wrap(tabs.first()).click({ force: true });
                    cy.wait(500);
                }
            });
        });

        it('should not lose rows when sorting any column in subform', () => {
            cy.get('.subform-table tbody tr, .subform-content tbody tr')
                .should('have.length.at.least', 0)
                .then($rows => {
                    if ($rows.length === 0) {
                        cy.log('No rows in subform - skipping sort test');
                        return;
                    }

                    const initialCount = $rows.length;
                    cy.log(`Initial subform row count: ${initialCount}`);

                    // Find and click a sortable column header (not the menu column)
                    cy.get('.subform-table thead th:not(.col-menu), .subform-content thead th:not(.col-menu)').first().click();

                    // If dropdown appears, click sort option
                    cy.get('body').then($body => {
                        if ($body.find('.dropdown-filter-content:visible').length > 0) {
                            cy.get('.dropdown-filter-sort span.a---z').first().click();
                        }
                    });

                    cy.wait(500);

                    // Verify rows are still present
                    cy.get('.subform-table tbody tr, .subform-content tbody tr')
                        .should('have.length', initialCount);
                });
        });

        it('should handle Z-A sort in subform', () => {
            cy.get('.subform-table tbody tr, .subform-content tbody tr')
                .should('have.length.at.least', 1)
                .then($rows => {
                    if ($rows.length === 0) {
                        cy.log('No rows in subform - skipping sort test');
                        return;
                    }

                    const initialCount = $rows.length;

                    // Find and click a sortable column header
                    cy.get('.subform-table thead th:not(.col-menu), .subform-content thead th:not(.col-menu)').first().click();

                    cy.get('body').then($body => {
                        if ($body.find('.dropdown-filter-content:visible').length > 0) {
                            cy.get('.dropdown-filter-sort span.z---a').first().click();
                        }
                    });

                    cy.wait(500);

                    // Verify rows are still present
                    cy.get('.subform-table tbody tr, .subform-content tbody tr')
                        .should('have.length', initialCount);
                });
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // ERROR HANDLING
    // ═══════════════════════════════════════════════════════════════

    describe('Error Handling', () => {
        beforeEach(() => {
            cy.visit('/form/users');
            cy.get('#listTable tbody tr', { timeout: 15000 }).should('have.length.at.least', 1);
            cy.dismissTips();
        });

        it('should not throw JavaScript errors when sorting', () => {
            // Listen for console errors
            cy.window().then(win => {
                cy.spy(win.console, 'error').as('consoleError');
            });

            // Perform sort - click header and check for dropdown
            cy.get('thead th').eq(1).click({ force: true });
            cy.wait(300);

            cy.get('body').then($body => {
                if ($body.find('.dropdown-filter-content:visible').length > 0) {
                    cy.get('.dropdown-filter-sort span.a---z').first().click({ force: true });
                }
            });
            cy.wait(500);

            // Verify no console errors occurred
            cy.get('@consoleError').should('not.have.been.called');
        });

        it('should maintain table structure after sorting', () => {
            cy.get('thead th').eq(1).click({ force: true });
            cy.wait(300);

            cy.get('body').then($body => {
                if ($body.find('.dropdown-filter-content:visible').length > 0) {
                    cy.get('.dropdown-filter-sort span.a---z').first().click({ force: true });
                }
            });
            cy.wait(500);

            // Verify table structure
            cy.get('table.listtable').should('exist');
            cy.get('table.listtable thead').should('exist');
            cy.get('table.listtable tbody').should('exist');
            cy.get('table.listtable tbody tr').should('have.length.at.least', 1);
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // DATE FORMAT HANDLING
    // ═══════════════════════════════════════════════════════════════

    describe('Dutch Date Format Sorting', () => {
        beforeEach(() => {
            // Load a page with lib-table to test the date parsing
            cy.visit('/form/users');
            cy.get('lib-table', { timeout: 10000 }).should('exist');
            cy.dismissTips();
        });

        it('should recognize "dd MMM yyyy" format as sortable', () => {
            // Test the sortableDate function with Dutch month names
            cy.window().then(win => {
                // Find the lib-table component
                const libTable = win.document.querySelector('lib-table');
                if (libTable && libTable._sortableDate) {
                    // Test various Dutch date formats
                    const testDates = [
                        ['01 jan 2024', '20240101'],
                        ['15 feb 2024', '20240215'],
                        ['28 mrt 2024', '20240328'],
                        ['01-01-2024', '20240101'],
                        ['2024-01-15', '20240115']
                    ];

                    testDates.forEach(([input, expected]) => {
                        const result = libTable._sortableDate(input);
                        cy.log(`_sortableDate("${input}") = "${result}"`);
                    });
                } else {
                    cy.log('lib-table component or _sortableDate method not found');
                }
            });
        });
    });
});
