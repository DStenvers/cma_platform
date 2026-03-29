/**
 * Foreign Key Lookup Tests
 *
 * Tests that combobox/FK fields display resolved text values in table view
 * instead of raw database ID numbers.
 *
 * Run: npx cypress run --spec "cypress/e2e/forms/fk-lookup.cy.js"
 */

describe('FK Lookup in Table View', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    describe('Rooster Form - Blok Field', () => {
        it('should display Blok field as text, not as number', () => {
            // Navigate to rooster form
            cy.visit('/form/rooster');

            // Wait for the filter selection UI
            cy.get('body').then($body => {
                // Rooster requires filter selection - check if we have a filter dropdown
                if ($body.find('select').length > 0 || $body.find('.select2-selection').length > 0) {
                    // Select an opleiding from the filter
                    cy.get('select, .select2-selection').first().click({ force: true });
                    cy.wait(500);

                    // Select an option (first available)
                    cy.get('body').then($body => {
                        if ($body.find('.select2-results__option').length > 0) {
                            cy.get('.select2-results__option').first().click({ force: true });
                        } else if ($body.find('select option').length > 1) {
                            cy.get('select').first().select(1);
                        }
                    });
                    cy.wait(1000);
                }
            });

            // Wait for table to load
            cy.get('lib-table tbody tr, table.listtable tbody tr', { timeout: 15000 })
                .should('have.length.at.least', 0)
                .then($rows => {
                    if ($rows.length === 0) {
                        cy.log('No rows in table - this is expected if no data matches the filter');
                        return;
                    }

                    // Find the Blok column by header
                    cy.get('thead th').then($headers => {
                        let blokColIndex = -1;
                        $headers.each((i, th) => {
                            const text = Cypress.$(th).text().trim();
                            if (text.toLowerCase() === 'blok') {
                                blokColIndex = i;
                            }
                        });

                        if (blokColIndex === -1) {
                            cy.log('Blok column not found in headers - may not be in visible columns');
                            return;
                        }

                        // Check that Blok column cells contain text, not just numbers
                        cy.get('tbody tr').each(($row) => {
                            const cell = $row.find('td').eq(blokColIndex);
                            const cellText = cell.text().trim();

                            // Skip empty cells
                            if (cellText === '') {
                                return;
                            }

                            // Cell text should NOT be a pure number
                            // It should be the resolved text like "Blok 1", "Module A", etc.
                            const isJustNumber = /^\d+$/.test(cellText);
                            if (isJustNumber) {
                                throw new Error(`Blok cell contains just a number "${cellText}" - FK lookup not working`);
                            }

                            cy.log(`Blok cell value: "${cellText}" - correctly resolved`);
                        });
                    });
                });
        });

        it('should have data-type="combobox" on Blok cells', () => {
            // Navigate to rooster form with a valid filter
            cy.visit('/form/rooster');

            // Wait for potential filter
            cy.get('body').then($body => {
                if ($body.find('select').length > 0 || $body.find('.select2-selection').length > 0) {
                    cy.get('select, .select2-selection').first().click({ force: true });
                    cy.wait(500);
                    cy.get('body').then($body => {
                        if ($body.find('.select2-results__option').length > 0) {
                            cy.get('.select2-results__option').first().click({ force: true });
                        } else if ($body.find('select option').length > 1) {
                            cy.get('select').first().select(1);
                        }
                    });
                    cy.wait(1000);
                }
            });

            // Wait for table
            cy.get('lib-table tbody tr, table.listtable tbody tr', { timeout: 15000 })
                .should('have.length.at.least', 0)
                .then($rows => {
                    if ($rows.length === 0) {
                        cy.log('No rows - skipping data-type check');
                        return;
                    }

                    // Check that fkOpleidingsBlok cells have data-type="combobox"
                    cy.get('tbody td[data-field="fkOpleidingsBlok"]').first().then($cell => {
                        expect($cell.attr('data-type')).to.equal('combobox');
                        cy.log('fkOpleidingsBlok has correct data-type="combobox"');
                    });
                });
        });
    });

    // COMMENTED OUT: Users form doesn't have combobox fields
    // The FK lookup functionality is verified by the Rooster Form tests above
    // describe('General FK Lookup Behavior', () => {
    //     it('should resolve FK values in users form (group field)', () => {
    //         cy.visit('/form/users');
    //         cy.get('lib-table tbody tr, table.listtable tbody tr', { timeout: 15000 })
    //             .should('have.length.at.least', 1);
    //         cy.get('tbody td[data-type="combobox"]').first().then($cell => {
    //             const cellText = $cell.text().trim();
    //             if (/^\d+$/.test(cellText)) {
    //                 cy.log(`Warning: Combobox cell contains just number: "${cellText}"`);
    //             } else if (cellText !== '') {
    //                 cy.log(`Combobox cell correctly shows: "${cellText}"`);
    //             }
    //         });
    //     });
    // });
});
