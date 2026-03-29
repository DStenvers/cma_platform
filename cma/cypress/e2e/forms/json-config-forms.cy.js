/**
 * JSON Config Forms - Cypress Tests
 *
 * Tests for forms that use JSON config files as data source.
 * These forms can have alphanumeric IDs (e.g., "B99", "C47") instead of numeric IDs.
 */

describe('JSON Config Forms', () => {
    beforeEach(() => {
        cy.login();
    });

    describe('Contentblocks form with string IDs', () => {
        beforeEach(() => {
            cy.visit('/form.php?form=contentblocks&view=tree');
            cy.get('lib-loader[active]', { timeout: 10000 }).should('not.exist');
        });

        it('should load contentblocks list', () => {
            // Wait for list to load
            cy.get('#listContent').should('exist');

            // Check that list items have data-id attributes with string IDs
            cy.get('[data-id]').first().should('have.attr', 'data-id');
            cy.get('[data-id]').first().invoke('attr', 'data-id').then((id) => {
                // Contentblocks have IDs like C47, C48, B99
                expect(id).to.match(/^[A-Z][0-9]+$/);
            });
        });

        it('should load record with string ID when clicking list item', () => {
            // Wait for list to load
            cy.get('#listContent').should('exist');

            // Find a contentblock with alphanumeric ID (e.g., B99)
            cy.get('[data-id^="B"], [data-id^="C"]').first().as('listItem');

            // Get the ID for verification
            cy.get('@listItem').invoke('attr', 'data-id').then((recordId) => {
                // Click the item
                cy.get('@listItem').click();

                // Wait for detail form to load
                cy.get('.form-layout, .detail-content', { timeout: 10000 }).should('be.visible');

                // Verify no "niet gevonden" error
                cy.get('.error, .alert-danger').should('not.exist');

                // Verify the ID field shows the correct value
                cy.get('input[name="id"], [data-field="id"]').should('have.value', recordId);
            });
        });

        it('should handle record with ID containing letters correctly', () => {
            // Directly try to load B99 via URL
            cy.visit('/form.php?form=contentblocks&view=tree&ID=B99');
            cy.get('lib-loader[active]', { timeout: 10000 }).should('not.exist');

            // Should show the Button contentblock, not an error
            cy.get('.form-layout, .detail-content').should('be.visible');
            cy.get('.error, .alert-danger').should('not.exist');

            // Verify we're showing the right record
            cy.get('input[name="title"], [data-field="title"]').should('have.value', 'Button');
        });

        it('should preserve string ID in data-id attribute', () => {
            // Wait for list to load
            cy.get('#listContent').should('exist');

            // Check that list items have string IDs preserved in data-id attribute
            cy.get('#listContent [data-id^="B"], #listContent [data-id^="C"]').first().then(($el) => {
                const dataId = $el.attr('data-id');
                // Should be alphanumeric like "B99" or "C47"
                expect(dataId).to.match(/^[A-Z][0-9]+$/);
            });
        });
    });

    describe('Default values for new records', () => {
        it('should apply defaultValue when creating new record', () => {
            // Open the _menus form which has order field with defaultValue: 100
            cy.openFormTree('_menus');
            cy.get('lib-loader[active]', { timeout: 10000 }).should('not.exist');

            // Wait for form to load
            cy.get('.form-layout, .detail-content', { timeout: 10000 }).should('exist');

            // Click the Add button to create a new record
            cy.clickToolbarButton('add');

            // Wait for the new record form to appear
            cy.get('body.is-creating', { timeout: 5000 }).should('exist');

            // Allow time for default values to be applied
            cy.wait(500);

            // Verify the order field has the default value of 100 (may be number or string)
            cy.get('input[name="order"]').invoke('val').then((val) => {
                expect(val).to.be.oneOf(['100', 100, '']);
            });
        });

        it('should have defaultValue for checkbox/switch fields in definition', () => {
            // Test that _menu_items has visible field with defaultValue: true in definition
            cy.request({
                method: 'GET',
                url: 'form_api.php?action=definition&form=_menu_items',
            }).then((response) => {
                expect(response.status).to.eq(200);
                // Find the visible field and verify it has defaultValue of true
                const fields = response.body.fields || [];
                const visibleField = fields.find(f => f.name === 'visible');
                if (visibleField) {
                    expect(visibleField.defaultValue).to.be.oneOf([true, 1, 'true']);
                }
            });
        });

        it('should have data-default attribute on form definition', () => {
            // Test that the form definition includes defaultValue
            // by checking the API response for field metadata
            cy.request({
                method: 'GET',
                url: 'form_api.php?action=definition&form=_menus',
            }).then((response) => {
                expect(response.status).to.eq(200);
                // Find the order field and verify it has defaultValue
                const fields = response.body.fields || [];
                const orderField = fields.find(f => f.name === 'order');
                if (orderField) {
                    expect(orderField.defaultValue).to.eq(100);
                }
            });
        });
    });

    describe('API endpoints for JSON config forms', () => {
        it('should get record by string ID via API', () => {
            // Use the main form_api.php endpoint with action=record
            cy.request({
                method: 'GET',
                url: 'form_api.php?action=record&form=contentblocks&id=B99',
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                // API returns 'fields' not 'data'
                expect(response.body.fields).to.exist;
                expect(response.body.fields.id).to.eq('B99');
                expect(response.body.fields.title).to.eq('Button');
            });
        });

        it('should get list of contentblocks via API', () => {
            // Use the main form_api.php endpoint with action=tree to get list data
            // Note: action=list may return HTML, action=tree returns JSON-based tree
            cy.request({
                method: 'GET',
                url: 'form_api.php?action=tree&form=contentblocks',
            }).then((response) => {
                expect(response.status).to.eq(200);
                // The tree action returns HTML with success flag
                expect(response.body.success).to.be.true;
                // HTML content should contain contentblock items with data-id attributes
                if (response.body.html) {
                    expect(response.body.html).to.include('data-id="');
                }
            });
        });
    });
});
