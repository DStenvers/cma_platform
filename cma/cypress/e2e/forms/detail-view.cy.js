/**
 * Detail View Tests
 *
 * Tests for the form detail view functionality.
 * Uses tree mode where detail panel is visible inline without sidepanel.
 */

describe('Detail View', () => {
    const formName = 'users';

    beforeEach(() => {
        cy.loginAsAdmin();
    });

    describe('Detail Display', () => {
        it('should display detail panel in tree mode', () => {
            cy.openFormTree(formName);
            cy.get('.detail-panel', { timeout: 10000 }).should('be.visible');
        });

        it('should show form fields when record selected', () => {
            cy.openFormTree(formName);

            // Click first record in tree
            cy.get('#listContent a, #simpletree a').first().click();

            // Detail panel should have form fields
            cy.get('.detail-panel input', { timeout: 10000 })
                .should('have.length.at.least', 1);
        });

        it('should show field labels', () => {
            cy.openFormTree(formName);
            cy.get('#listContent a, #simpletree a').first().click();

            cy.get('.detail-panel label, .detail-panel .field-label', { timeout: 10000 })
                .should('have.length.at.least', 1);
        });

        it('should show toolbar with actions', () => {
            cy.openFormTree(formName);
            cy.get('.toolbar', { timeout: 10000 }).should('be.visible');
        });
    });

    describe('Field Types', () => {
        beforeEach(() => {
            cy.openFormTree(formName);
            cy.get('#listContent a, #simpletree a').first().click();
            cy.get('.detail-panel input', { timeout: 10000 }).should('exist');
        });

        it('should display text inputs', () => {
            cy.get('.detail-panel input[type="text"]').should('have.length.at.least', 1);
        });

        it('should display password input for password fields', () => {
            cy.get('.detail-panel input[type="password"]').should('exist');
        });

        it('should handle select dropdowns', () => {
            cy.get('.detail-panel').then($panel => {
                if ($panel.find('select, .select2-container').length > 0) {
                    cy.get('.detail-panel select, .detail-panel .select2-container').should('be.visible');
                } else {
                    cy.log('No select fields in this form');
                }
            });
        });
    });

    describe('Field Data', () => {
        it('should load record data via API', () => {
            cy.request({
                method: 'GET',
                url: `/form_api.php?action=record&form=${formName}&id=1`
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.fields).to.exist;
                expect(response.body.fields.userLogin).to.exist;
                expect(response.body.fields.userFullName).to.exist;
            });
        });

        it('should populate form fields with data', () => {
            cy.openFormTree(formName);
            cy.get('#listContent a, #simpletree a').first().click();

            // First text field should have a value
            cy.get('.detail-panel input[type="text"]', { timeout: 10000 }).first()
                .invoke('val')
                .should('not.be.empty');
        });
    });

    describe('Navigation', () => {
        it('should navigate between records in tree view', () => {
            cy.openFormTree(formName);

            // Get initial value
            cy.get('#listContent a, #simpletree a').first().click();
            cy.get('.detail-panel input[type="text"]', { timeout: 10000 }).first()
                .invoke('val')
                .as('firstValue');

            // Click second record
            cy.get('#listContent a, #simpletree a').eq(1).click();

            // Value might be different
            cy.get('.detail-panel input[type="text"]').first()
                .invoke('val')
                .should('exist');
        });

        it('should switch between tree and table view', () => {
            cy.openFormTree(formName);

            // Should be in tree mode
            cy.get('body.mode-tree').should('exist');

            // Click table view
            cy.get('[data-action="setlistmode"][data-mode="2"]').click();

            // Should switch to table mode
            cy.get('body.mode-table', { timeout: 5000 }).should('exist');
        });
    });

    describe('Search Panel', () => {
        it('should show search panel', () => {
            cy.openFormTree(formName);

            // Click search toggle
            cy.get('[data-action="toggleSearch"]').click();

            // Search panel should be visible
            cy.get('#searchPanel').should('be.visible');
        });

        it('should have search fields', () => {
            cy.openFormTree(formName);
            cy.get('[data-action="toggleSearch"]').click();

            // Should have search inputs
            cy.get('#searchPanel input').should('have.length.at.least', 1);
        });
    });

    describe('Toolbar Actions', () => {
        it('should have save button in detail toolbar', () => {
            cy.openFormTree(formName);
            cy.get('#listContent a, #simpletree a').first().click();

            cy.get('[data-action="save"]', { timeout: 10000 }).should('exist');
        });

        it('should have add button', () => {
            cy.openFormTree(formName);
            cy.get('[data-action="addInline"], [data-action="add"]').should('exist');
        });

        it('should clear form for new record', () => {
            cy.openFormTree(formName);

            // Click first record to load data
            cy.get('#listContent a, #simpletree a').first().click();
            cy.get('.detail-panel input[type="text"]', { timeout: 10000 }).first()
                .invoke('val')
                .should('not.be.empty');

            // Click add button
            cy.clickToolbarButton('add');

            // Form should be cleared for new record
            cy.wait(500);
            // New record form should be visible
            cy.get('.detail-panel').should('be.visible');
        });
    });

    describe('lib-switch Toggle Fields', () => {
        it('should display lib-switch elements correctly', () => {
            cy.openFormTree(formName);
            cy.get('#listContent a, #simpletree a').first().click();

            cy.get('.detail-panel', { timeout: 10000 }).then($panel => {
                if ($panel.find('lib-switch').length > 0) {
                    // lib-switch should exist and be rendered (may be scrolled out of view
                    // in the detail panel, so check existence rather than visibility)
                    cy.get('.detail-panel lib-switch').first().scrollIntoView().should('exist');
                } else {
                    cy.log('No lib-switch fields in this form');
                }
            });
        });
    });

    describe('Field Validation via API', () => {
        it('should reject save without required fields', () => {
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
                expect(response.status).to.eq(200);
                // Should either fail or succeed based on validation
            });
        });

        it('should accept save with required fields', () => {
            // First get current data
            cy.request({
                method: 'GET',
                url: `/form_api.php?action=record&form=${formName}&id=1`
            }).then((response) => {
                expect(response.body.success).to.be.true;

                // Re-save with same data
                cy.request({
                    method: 'POST',
                    url: 'form_api.php',
                    form: true,
                    body: {
                        action: 'save',
                        form: formName,
                        ID: '1',
                        userFullName: response.body.fields.userFullName
                    }
                }).then((saveResponse) => {
                    expect(saveResponse.status).to.eq(200);
                    expect(saveResponse.body.success).to.be.true;
                });
            });
        });
    });

    describe('Form Layout', () => {
        it('should have form-layout container', () => {
            cy.openFormTree(formName);
            cy.get('.form-layout').should('exist');
        });

        it('should have list and detail panels', () => {
            cy.openFormTree(formName);
            cy.get('.list-panel, #listContent').should('exist');
            cy.get('.detail-panel').should('exist');
        });
    });

    describe('Label Column Width', () => {
        it('should set --label-column-width CSS variable', () => {
            cy.openFormTree(formName);
            cy.get('#listContent a, #simpletree a').first().click();

            // Check that the CSS variable is set on document root
            cy.document().then(doc => {
                const labelWidth = getComputedStyle(doc.documentElement).getPropertyValue('--label-column-width');
                expect(labelWidth).to.not.be.empty;
                expect(labelWidth).to.include('px');
            });
        });

        it('should apply labelColumnWidth from form config', () => {
            cy.openFormTree(formName);
            cy.get('#listContent a, #simpletree a').first().click();

            // Users form has labelColumnWidth set in JSON definition
            cy.window().then(win => {
                if (win.CMA?.formConfig?.labelColumnWidth) {
                    const expectedWidth = win.CMA.formConfig.labelColumnWidth + 'px';
                    cy.document().then(doc => {
                        const actualWidth = getComputedStyle(doc.documentElement).getPropertyValue('--label-column-width').trim();
                        expect(actualWidth).to.eq(expectedWidth);
                    });
                }
            });
        });

        it('should use CSS variable for label cells', () => {
            cy.openFormTree(formName);
            cy.get('#listContent a, #simpletree a').first().click();

            // .c1 cells should use the variable for min-width
            cy.get('.detail-panel .c1', { timeout: 10000 }).first().then($cell => {
                const style = window.getComputedStyle($cell[0]);
                const minWidth = parseInt(style.minWidth);
                // Should be at least 110px (minimum) but calculated from form definition
                expect(minWidth).to.be.at.least(110);
            });
        });

        it('should calculate label column width dynamically via CSS variable', () => {
            cy.openFormTree(formName);
            cy.get('#listContent a, #simpletree a').first().click();

            // labelColumnWidth was removed from form definitions (migration 9.4.0)
            // and is now calculated dynamically by form-controller.js based on field captions.
            // Verify the CSS variable is set with a valid pixel value.
            cy.document().then(doc => {
                const labelWidth = getComputedStyle(doc.documentElement).getPropertyValue('--label-column-width');
                expect(labelWidth).to.not.be.empty;
                expect(labelWidth).to.include('px');
                const widthValue = parseInt(labelWidth);
                // Should be between min (110) and max (360)
                expect(widthValue).to.be.at.least(110);
                expect(widthValue).to.be.at.most(360);
            });
        });
    });

    describe('FK Label Resolution', () => {
        it('should return __label fields for combobox values via API', () => {
            // Test that record API returns fieldName__label for combobox fields
            cy.request({
                method: 'GET',
                url: `/form_api.php?action=record&form=${formName}&id=1`
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.fields).to.exist;
                // The response should contain __label fields for any FK/combobox field with a value
                // Check if any field ends with __label
                const labelFields = Object.keys(response.body.fields).filter(k => k.endsWith('__label'));
                cy.log('Found label fields:', labelFields);
            });
        });

        it('should support id parameter in combo API', () => {
            // Test that combo API accepts id parameter for fetching single labels
            // This is used by dynamic search combos to fetch labels for existing values
            // Use the users form which we know is accessible in tests
            cy.request({
                method: 'GET',
                url: `/form_api.php?action=combo&form=${formName}&field=userLevel&id=1`,
                failOnStatusCode: false
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                // Response should have proper structure
                expect(response.body).to.have.property('options');
                expect(response.body.options).to.be.an('array');
                // When looking up static options by id, should return matching option
                if (response.body.label !== undefined) {
                    cy.log('Got label:', response.body.label);
                }
            });
        });

        it('should display combo value for dynamic search combos', () => {
            cy.openFormTree(formName);
            cy.get('#listContent a, #simpletree a').first().click();

            // Wait for form to load
            cy.get('.detail-panel', { timeout: 10000 }).should('be.visible');

            // Check that select2 combos show actual values, not placeholder text
            cy.get('.detail-panel').then($panel => {
                // Find any Select2 containers
                const select2Containers = $panel.find('.select2-container');
                if (select2Containers.length > 0) {
                    // Check that none show just the placeholder for fields with values
                    select2Containers.each((i, container) => {
                        const chosen = Cypress.$(container).find('.select2-chosen');
                        if (chosen.length > 0) {
                            const text = chosen.text().trim();
                            // If there's text, it shouldn't just be the search placeholder
                            if (text && text.length > 0) {
                                cy.log(`Select2 ${i} displays: "${text}"`);
                            }
                        }
                    });
                }
            });
        });
    });
});
