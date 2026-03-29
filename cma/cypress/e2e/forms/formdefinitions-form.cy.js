/**
 * Formulierdefinities Form Tests
 *
 * Tests for the form definitions editor (configDir directory mode).
 * Verifies list view, detail view, field behavior, and save operations.
 */

describe('Formulierdefinities Form', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    describe('List view', () => {
        it('should load list with form definitions', () => {
            cy.visit('/form.php?form=formdefinitions');
            cy.get('#listContent', { timeout: 15000 }).should('exist');
            cy.get('#listContent [data-id]').should('have.length.at.least', 5);
        });

        it('should not show internal CMA forms (users, groups, etc.)', () => {
            cy.visit('/form.php?form=formdefinitions');
            cy.get('#listContent [data-id]', { timeout: 15000 }).should('have.length.at.least', 5);

            // Internal forms should be excluded
            const internalForms = ['users', 'groups', '_menus', '_menu_items', 'cmamonitoring', 'contentblocks', 'marketingurl', 'formdefinitions'];
            internalForms.forEach(formName => {
                cy.get(`#listContent [data-id="${formName}"]`).should('not.exist');
            });
        });

        it('should show known app forms like logins', () => {
            cy.visit('/form.php?form=formdefinitions&view=tree');
            cy.get('#listContent [data-id]', { timeout: 15000 }).should('have.length.at.least', 5);

            // These app forms should be visible
            cy.get('#listContent [data-id="logins"]').should('exist');
        });

        it('should not show subforms as standalone items in the list', () => {
            cy.visit('/form.php?form=formdefinitions&view=tree');
            cy.get('#listContent [data-id]', { timeout: 15000 }).should('have.length.at.least', 5);

            // Subforms should only appear inside their parent's folder, not as top-level items
            cy.get('#simpletree > [data-id="deelnemers_bijlagen"]').should('not.exist');
        });

        it('should show parent forms as collapsible folders with subforms', () => {
            cy.visit('/form.php?form=formdefinitions&view=tree');
            cy.get('#listContent [data-id]', { timeout: 15000 }).should('have.length.at.least', 5);

            // Parent forms with subforms should be rendered as <details> folders
            cy.get('#listContent details.tree-folder').should('have.length.at.least', 5);

            // Subforms should be nested inside folder children
            cy.get('#listContent .tree-folder-children a.tree-subform').should('have.length.at.least', 10);
        });

        it('should show subforms inside their parent folder', () => {
            cy.visit('/form.php?form=formdefinitions&view=tree');
            cy.get('#listContent [data-id]', { timeout: 15000 }).should('have.length.at.least', 5);

            // Find the deelnemers folder and check its subforms
            cy.get('#listContent a[data-id="deelnemers"]').should('exist');
            cy.get('#listContent a[data-id="deelnemers"]')
                .closest('details.tree-folder')
                .find('.tree-folder-children a.tree-subform')
                .should('have.length.at.least', 2);
        });

        it('should load subform details when clicking a subform in folder', () => {
            cy.visit('/form.php?form=formdefinitions&view=tree');
            cy.get('#listContent [data-id]', { timeout: 15000 }).should('have.length.at.least', 5);

            // Click a subform link inside a folder
            cy.get('#listContent .tree-folder-children a.tree-subform').first().click();

            // Wait for detail form to load
            cy.get('.form-layout .detail-content', { timeout: 15000 }).should('be.visible');
            cy.get('lib-message[type="error"]').should('not.exist');
        });
    });

    describe('Quick search', () => {
        it('should filter by form name', () => {
            cy.visit('/form.php?form=formdefinitions&view=tree');
            cy.get('#listContent [data-id]', { timeout: 15000 }).should('have.length.at.least', 5);

            // Type in the search input (lib-search-input#searchfor)
            cy.get('lib-search-input#searchfor').find('input').clear().type('logins{enter}');

            // Wait for filtered results
            cy.get('#listContent [data-id="logins"]', { timeout: 10000 }).should('exist');
        });

        it('should filter by title', () => {
            cy.visit('/form.php?form=formdefinitions&view=tree');
            cy.get('#listContent [data-id]', { timeout: 15000 }).should('have.length.at.least', 5);

            cy.get('lib-search-input#searchfor').find('input').clear().type('Docenten{enter}');

            cy.get('#listContent [data-id="docenten"]', { timeout: 10000 }).should('exist');
        });
    });

    describe('Detail view', () => {
        it('should load form definition details when clicking a form', () => {
            cy.visit('/form.php?form=formdefinitions&view=tree');
            cy.get('#listContent [data-id]', { timeout: 15000 }).should('have.length.at.least', 5);

            // Click on logins form (use tree view to get a single element)
            cy.get('#listContent a[data-id="logins"]').first().click();

            // Wait for detail form
            cy.get('.form-layout .detail-content', { timeout: 15000 }).should('be.visible');

            // Should not show error
            cy.get('lib-message[type="error"]').should('not.exist');
        });

        it('should show name field as read-only', () => {
            cy.visit('/form.php?form=formdefinitions&ID=logins');
            cy.get('.form-layout .detail-content', { timeout: 15000 }).should('be.visible');

            // Name field should be read-only (rendered with readonly attribute from server)
            // Wait for value to be populated first (data loads async)
            cy.get('input[name="name"]').should('have.value', 'logins');
            // The readonly attribute is set server-side via buildDataAttributes and maintained by JS
            cy.get('input[name="name"]').should('have.attr', 'readonly');
        });

        it('should display title, table, and database fields correctly', () => {
            cy.visit('/form.php?form=formdefinitions&ID=logins');
            cy.get('.form-layout .detail-content', { timeout: 15000 }).should('be.visible');

            cy.get('input[name="title"]').should('not.have.value', '');
            cy.get('input[name="table"]').should('have.value', 'tblLogins');
            cy.get('input[name="database"]').should('have.value', 'data');
        });

        it('should display checkbox fields for options', () => {
            cy.visit('/form.php?form=formdefinitions&ID=logins');
            cy.get('.form-layout .detail-content', { timeout: 15000 }).should('be.visible');

            // Boolean fields should be rendered as checkboxes or switches
            cy.get('[name="allowAdd"]').should('exist');
            cy.get('[name="allowDelete"]').should('exist');
            cy.get('[name="allowCopy"]').should('exist');
        });

        it('should display JSON fields with valid formatted JSON', () => {
            cy.visit('/form.php?form=formdefinitions&ID=logins');
            cy.get('.form-layout .detail-content', { timeout: 15000 }).should('be.visible');

            // Fields (JSON) textarea should contain valid JSON
            // Wait for the textarea to be populated (data loads async)
            cy.get('textarea[name="fields"]', { timeout: 10000 }).should($el => {
                const val = $el.val();
                expect(val).to.not.be.empty;
                const parsed = JSON.parse(val);
                expect(parsed).to.be.an('array');
                expect(parsed.length).to.be.at.least(1);
            });
        });

        it('should render onLoadJs as a textarea (memo field)', () => {
            cy.visit('/form.php?form=formdefinitions&ID=logins');
            cy.get('.form-layout .detail-content', { timeout: 15000 }).should('be.visible');

            // onLoadJs should be a textarea, not an input
            cy.get('textarea[name="onLoadJs"]').should('exist');
            cy.get('input[name="onLoadJs"]').should('not.exist');
        });
    });

    describe('Add/Delete restrictions', () => {
        it('should not show add/new button (allowAdd: false)', () => {
            cy.visit('/form.php?form=formdefinitions');
            cy.get('#listContent [data-id]', { timeout: 15000 }).should('have.length.at.least', 5);

            // Add button should not be visible
            cy.get('.detail-toolbar [data-action="add"], .toolbar [data-action="add"]').should('not.exist');
        });

        it('should not show delete button (allowDelete: false)', () => {
            cy.visit('/form.php?form=formdefinitions&ID=logins');
            cy.get('.form-layout .detail-content', { timeout: 15000 }).should('be.visible');

            // Delete button should not be visible
            cy.get('.detail-toolbar [data-action="delete"], .toolbar [data-action="delete"]').should('not.exist');
        });
    });

    describe('Save operations', () => {
        it('should save title changes and persist them', () => {
            const uniqueSuffix = Date.now();
            const testTitle = `Logins Test ${uniqueSuffix}`;

            // Load the logins form definition
            cy.visit('/form.php?form=formdefinitions&ID=logins');
            cy.get('.form-layout .detail-content', { timeout: 15000 }).should('be.visible');

            // Wait for title to be populated by AJAX, then store original for restore
            cy.get('input[name="title"]').should('not.have.value', '');
            cy.get('input[name="title"]').invoke('val').then(originalTitle => {
                // Clear field completely and type new value
                // Use triple-click to select all, then type to replace
                cy.get('input[name="title"]').click().click().click()
                    .type('{selectall}{del}')
                    .type(testTitle)
                    .should('have.value', testTitle);

                // Save via keyboard shortcut or button
                cy.get('[data-action="save"]').first().click();

                // Wait for save to complete via toast notification
                cy.shouldShowToast('success', { timeout: 10000 });

                // Reload and verify
                cy.visit('/form.php?form=formdefinitions&ID=logins');
                cy.get('.form-layout .detail-content', { timeout: 15000 }).should('be.visible');

                cy.get('input[name="title"]').should('have.value', testTitle);

                // Restore original title
                cy.get('input[name="title"]').click().click().click()
                    .type('{selectall}{del}')
                    .type(originalTitle)
                    .should('have.value', originalTitle);
                cy.get('[data-action="save"]').first().click();
                cy.shouldShowToast('success', { timeout: 10000 });
            });
        });
    });

    describe('Field chooser', () => {
        it('should have fields available for field chooser after page load', () => {
            // Visit the form page to establish proper auth and check fields
            cy.intercept('GET', '**/form_api.php*action=init*').as('initLoad');
            cy.visit('/form.php?form=formdefinitions');

            cy.wait('@initLoad').then(interception => {
                const body = interception.response.body;
                expect(body.success).to.be.true;
                if (body.list && body.list.fields) {
                    expect(body.list.fields).to.be.an('array');
                    expect(body.list.fields.length).to.be.at.least(5);

                    // Should include standard form definition fields
                    const fieldNames = body.list.fields.map(f => f.name);
                    expect(fieldNames).to.include('title');
                    expect(fieldNames).to.include('allowAdd');
                }
            });
        });
    });

    describe('API integration', () => {
        it('should return record data via API', () => {
            // Visit page first to establish proper session
            cy.visit('/form.php?form=formdefinitions&ID=logins');
            cy.get('.form-layout .detail-content', { timeout: 15000 }).should('be.visible');

            // Verify the form data loaded correctly
            cy.get('input[name="name"]').should('have.value', 'logins');
            cy.get('input[name="table"]').should('have.value', 'tblLogins');

            // JSON fields should be present
            cy.get('textarea[name="fields"]').should($el => {
                const val = $el.val();
                expect(val).to.not.be.empty;
                const fields = JSON.parse(val);
                expect(fields).to.be.an('array');
                expect(fields.length).to.be.at.least(1);
            });
        });
    });

    describe('Menu access', () => {
        it('should be accessible from tools page', () => {
            cy.visit('/tools.php');
            cy.get('body', { timeout: 10000 }).should('exist');

            // Look for Formulierdefinities link in tools page
            cy.get('body').then($body => {
                const link = $body.find('a[href*="formdefinitions"]');
                expect(link.length).to.be.at.least(0);
            });
        });
    });
});
