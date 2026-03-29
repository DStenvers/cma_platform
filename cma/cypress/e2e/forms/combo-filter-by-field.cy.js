/**
 * Cascading Combo Filter (filterByField) Tests
 *
 * Tests that combo fields with filterByField attribute correctly filter
 * their options based on a parent field's value.
 *
 * Run: npx cypress run --spec "cypress/e2e/forms/combo-filter-by-field.cy.js"
 */

describe('Cascading Combo Filter (filterByField)', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    describe('API endpoint', () => {
        it('should return filtered options when filterField and filterValue are provided', () => {
            // First get a valid opleiding ID
            cy.request({
                url: '/form_api.php?action=combo&form=rooster&field=fkOpleiding',
                failOnStatusCode: false
            }).then(response => {
                expect(response.body.success).to.be.true;
                expect(response.body.options.length).to.be.greaterThan(0);

                const opleidingId = response.body.options[0].id;

                // Request filtered blocks
                cy.request({
                    url: `/form_api.php?action=combo&form=rooster&field=fkOpleidingsBlok&filterField=fkOpleiding&filterValue=${opleidingId}`
                }).then(filteredResponse => {
                    expect(filteredResponse.body.success).to.be.true;
                    expect(filteredResponse.body).to.have.property('options');

                    // Also request unfiltered blocks
                    cy.request({
                        url: '/form_api.php?action=combo&form=rooster&field=fkOpleidingsBlok'
                    }).then(unfilteredResponse => {
                        expect(unfilteredResponse.body.success).to.be.true;

                        // Filtered should have <= unfiltered options
                        const filteredCount = filteredResponse.body.options.length;
                        const unfilteredCount = unfilteredResponse.body.options.length;
                        cy.log(`Filtered: ${filteredCount}, Unfiltered: ${unfilteredCount}`);
                        expect(filteredCount).to.be.at.most(unfilteredCount);
                    });
                });
            });
        });

        it('should return empty options for non-matching filterValue', () => {
            cy.request({
                url: '/form_api.php?action=combo&form=rooster&field=fkOpleidingsBlok&filterField=fkOpleiding&filterValue=999999'
            }).then(response => {
                expect(response.body.success).to.be.true;
                expect(response.body.options).to.have.length(0);
            });
        });
    });

    describe('Rooster form - UI', () => {
        /**
         * Helper: get a record ID from the rooster form (needs filter context)
         */
        function getFirstRecordId() {
            // First get an opleiding ID
            return cy.request({
                url: '/form_api.php?action=combo&form=rooster&field=fkOpleiding'
            }).then(response => {
                expect(response.body.success).to.be.true;
                const opleidingId = response.body.options[0].id;
                // Get list with filter
                return cy.request({
                    url: `/form_api.php?action=list&form=rooster&filterField=fkOpleiding&filterValue=${opleidingId}`
                });
            }).then(response => {
                if (!response.body.success) return null;
                const rows = response.body.data || response.body.rows || [];
                if (rows.length === 0) return null;
                const firstRow = rows[0];
                // ID is typically the last column in list query
                return firstRow[firstRow.length - 1] || firstRow.ID || firstRow.id;
            });
        }

        it('should render data-filter-by-field attribute on dependent combo', () => {
            getFirstRecordId().then(recordId => {
                if (!recordId) {
                    cy.log('No rooster records, skipping');
                    return;
                }
                cy.visit(`/form.php?form=rooster&formID=${recordId}`);
                cy.get('body.has-record', { timeout: 15000 }).should('exist');

                // The fkOpleidingsBlok combo should have data-filter-by-field
                cy.get('lib-combo[name="fkOpleidingsBlok"]', { timeout: 10000 })
                    .should('have.attr', 'data-filter-by-field', 'fkOpleiding');
            });
        });

        it('should make filtered API call after record load', () => {
            // Intercept the filtered combo API call
            cy.intercept('GET', '**/form_api.php?action=combo*field=fkOpleidingsBlok*filterField*').as('filteredCombo');

            getFirstRecordId().then(recordId => {
                if (!recordId) {
                    cy.log('No rooster records, skipping');
                    return;
                }
                cy.visit(`/form.php?form=rooster&formID=${recordId}`);
                cy.get('body.has-record', { timeout: 15000 }).should('exist');

                // After record load, applyFilteredComboReload should fire
                cy.get('lib-combo[name="fkOpleiding"]', { timeout: 10000 }).then($combo => {
                    const combo = $combo[0];
                    if (combo.value) {
                        cy.wait('@filteredCombo', { timeout: 10000 }).then(interception => {
                            expect(interception.request.url).to.include('filterField=fkOpleiding');
                            expect(interception.response.statusCode).to.equal(200);
                            expect(interception.response.body.success).to.be.true;
                        });
                    } else {
                        cy.log('fkOpleiding has no value, skipping filtered check');
                    }
                });
            });
        });

        it('should filter block options when opleiding changes', () => {
            // Intercept filtered combo calls
            cy.intercept('GET', '**/form_api.php?action=combo*field=fkOpleidingsBlok*filterField*').as('filteredCombo');

            getFirstRecordId().then(recordId => {
                if (!recordId) {
                    cy.log('No rooster records, skipping');
                    return;
                }
                cy.visit(`/form.php?form=rooster&formID=${recordId}`);
                cy.get('body.has-record', { timeout: 15000 }).should('exist');

                // Wait for initial filtered combo request to complete
                cy.wait('@filteredCombo', { timeout: 10000 });
                cy.wait(500);

                // Change opleiding to trigger re-filter
                cy.get('lib-combo[name="fkOpleiding"]').then($combo => {
                    const combo = $combo[0];
                    const options = combo._options || [];
                    if (options.length < 2) {
                        cy.log('Not enough opleiding options');
                        return;
                    }
                    const currentValue = combo.value;
                    const newOption = options.find(o => String(o.value) !== String(currentValue));
                    if (!newOption) return;

                    // Simulate user clicking a different option
                    combo.value = newOption.value;
                    combo.dispatchEvent(new CustomEvent('change', {
                        detail: { value: newOption.value },
                        bubbles: true
                    }));

                    // Should trigger new filtered request
                    cy.wait('@filteredCombo', { timeout: 10000 }).then(interception => {
                        expect(interception.request.url).to.include('filterValue=' + newOption.value);
                        expect(interception.response.body.success).to.be.true;
                    });
                });
            });
        });
    });
});
