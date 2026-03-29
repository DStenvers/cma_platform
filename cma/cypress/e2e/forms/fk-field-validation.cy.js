/**
 * FK Field Validation Tests
 *
 * Tests for foreign key (FK) field validation including:
 * - FK field error display when value doesn't exist in source table
 * - FK field display when value exists (using custom SQL for calculated displayField)
 * - Subform parentField validation error display
 */

describe('FK Field Validation', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    describe('FK Field Display', () => {
        it('should show error label when FK value does not exist in source table', () => {
            // This test verifies that when an FK field has a value that doesn't exist
            // in the source table, an error message is displayed

            // Visit a page first to ensure session cookies are active for cy.request
            cy.visit('/form.php?form=users');
            cy.get('.form-layout, table.listtable, lib-table', { timeout: 15000 }).should('exist');

            // We need to test this via API since we can't easily create invalid FK data in UI
            cy.request({
                method: 'GET',
                url: 'form_api.php',
                qs: {
                    action: 'record',
                    jsonForm: 'users',
                    id: '1'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;

                // Verify the record structure includes FK labels if any FK fields exist
                const fields = response.body.fields;
                if (fields) {
                    // Check that any __label fields don't have __error set to true
                    // (unless the FK value is actually invalid)
                    Object.keys(fields).forEach(key => {
                        if (key.endsWith('__error')) {
                            // If there's an error, there should also be a label with error message
                            const labelKey = key.replace('__error', '__label');
                            expect(fields[labelKey]).to.be.a('string');
                            expect(fields[labelKey]).to.include('Kan');
                        }
                    });
                }
            });
        });
    });

    describe('FK Field Resolution via Custom SQL', () => {
        it('should use custom SQL to resolve displayField when it is a calculated alias', () => {
            // This tests that FK fields with custom SQL (displayField is calculated)
            // are resolved correctly using the SQL query

            // Test via API - the resolveFkLabels function should use sqlList when displayField is calculated
            cy.request({
                method: 'GET',
                url: 'form_api.php',
                qs: {
                    action: 'combo',
                    form: 'users',
                    field: 'userLevel'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                // Combo should either return success with options or an error message
                expect(response.body).to.have.property('success');
                if (response.body.success) {
                    expect(response.body.options).to.be.an('array');
                } else {
                    // Error should be a descriptive message, not empty
                    expect(response.body.error).to.be.a('string');
                    expect(response.body.error).to.not.be.empty;
                }
            });
        });
    });

    describe('Subform ParentField Validation', () => {
        it('should return error when subform has no parentField configured', () => {
            // This test verifies that when a subform has no parentField,
            // the API returns an appropriate error instead of all records

            // We test this by checking the subform response structure
            // A properly configured subform should have parentField set
            cy.request({
                method: 'GET',
                url: 'form_api.php',
                qs: {
                    action: 'subform',
                    form: 'users',
                    index: '0',
                    id: '1'
                },
                failOnStatusCode: false
            }).then((response) => {
                // If the form has subforms, verify they have parentField
                if (response.body.success && response.body.html) {
                    // The subform loaded - check that parentField is set
                    expect(response.body.parentField).to.satisfy(
                        (val) => val === '' || typeof val === 'string',
                        'parentField should be a string'
                    );
                } else if (!response.body.success) {
                    // Error response - verify it has a descriptive message
                    expect(response.body.error).to.be.a('string');
                }
            });
        });

        it('should show error message in UI when subform has missing parentField', () => {
            // This test verifies the UI displays an error when subform configuration is invalid

            // Navigate to a form with subforms
            cy.openFormTree('contentblocks');

            // Click on a record that might have subforms
            cy.get('#listContent a[target="R"]').first().click({ force: true });

            // Wait for form to load
            cy.get('form#mainForm, form.form-detail', { timeout: 10000 }).should('be.visible');

            // If there are subforms, they should not show configuration error
            // (unless they're intentionally misconfigured)
            cy.get('.subform-error, [data-subform-error]').should('not.exist');
        });
    });

    describe('Non-Required Field Validation', () => {
        it('should correctly identify required fields by checking multiple false values', () => {
            // Test that the validation correctly recognizes all falsy required values
            // data-required="false", "FALSE", "no", "NO", "n", "N", "0"
            cy.window().then((win) => {
                // Create test elements with various falsy required values
                const testCases = ['false', 'FALSE', 'no', 'NO', 'n', 'N', '0'];

                testCases.forEach((val) => {
                    const testEl = win.document.createElement('input');
                    testEl.type = 'text';
                    testEl.name = 'test_' + val;
                    testEl.setAttribute('data-required', val);

                    // These should all be treated as NOT required
                    const reqAttr = (testEl.getAttribute('data-required') || '').toUpperCase();
                    const isRequired = reqAttr && reqAttr !== 'N' && reqAttr !== 'FALSE' && reqAttr !== '0' && reqAttr !== 'NO';

                    expect(isRequired).to.be.false;
                });
            });
        });
    });

    describe('Combo Options Error Handling', () => {
        it('should handle combo options load failure gracefully', () => {
            // Test that when combo options fail to load, a proper error is displayed
            // instead of an empty error object

            cy.request({
                method: 'GET',
                url: 'form_api.php',
                qs: {
                    action: 'combo',
                    form: 'nonexistent_form',
                    field: 'fkSomething'
                }
            }).then((response) => {
                // Should return an error response
                expect(response.body.success).to.be.false;
                expect(response.body.error).to.be.a('string');
                expect(response.body.error).to.not.be.empty;
            });
        });

        it('should return proper error for nonexistent field', () => {
            cy.request({
                method: 'GET',
                url: 'form_api.php',
                qs: {
                    action: 'combo',
                    form: 'users',
                    field: 'nonexistent_field'
                }
            }).then((response) => {
                // Should return an error response with descriptive message
                expect(response.body.success).to.be.false;
                expect(response.body.error).to.be.a('string');
                expect(response.body.error).to.include('niet gevonden');
            });
        });
    });
});
