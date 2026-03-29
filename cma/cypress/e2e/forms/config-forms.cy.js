/**
 * Config Forms Tests
 *
 * Tests for JSON config-based forms (_menus, _menu_items, etc.)
 * These forms read/write to JSON config files instead of database.
 */

describe('Config Forms', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    describe('_menus Form', () => {
        it('should load menus list without error', () => {
            cy.openFormTree('_menus');
            // Wait for main loader to disappear (not tab-count.loading which stays during count fetch)
            cy.get('lib-loader[active]', { timeout: 10000 }).should('not.exist');

            // Wait for list to load
            cy.get('#listContent', { timeout: 10000 }).should('exist');

            // Should have at least one menu
            cy.get('[data-id], a[onclick*="loadRecord"]').should('have.length.at.least', 1);
        });

        it('should load menu detail (id=51 - Inventarisatie)', () => {
            // This is the specific case that was reported as having ODBC buffer error
            // Use tree view to properly show detail form
            cy.visit('/form.php?form=_menus&view=tree&id=51');
            cy.get('lib-loader[active]', { timeout: 10000 }).should('not.exist');

            // Wait for detail form to load
            cy.get('.form-layout, .detail-content', { timeout: 10000 }).should('be.visible');

            // Should have name field populated with "Inventarisatie"
            cy.get('input[name="name"]', { timeout: 10000 })
                .should('be.visible')
                .and('have.value', 'Inventarisatie');
        });

        it('should load submenu items for menu 51', () => {
            // Use tree view to properly show detail form and subforms
            cy.visit('/form.php?form=_menus&view=tree&id=51');
            cy.get('lib-loader[active]', { timeout: 10000 }).should('not.exist');

            // Wait for detail form
            cy.get('.form-layout', { timeout: 10000 }).should('be.visible');

            // Check for subform section - look for subform tab or table with items
            cy.get('.subform-area, .tab-pane, table', { timeout: 15000 })
                .should('exist');

            // Should show Menu-items subform with data rows
            cy.contains('Menu-items', { timeout: 10000 }).should('be.visible');

            // Should have at least one row in the subform table (Inventarisatie has 3 items)
            cy.get('table tbody tr', { timeout: 10000 }).should('have.length.at.least', 1);

            // Verify no ODBC/SQL errors in the response
            cy.window().then((win) => {
                // Check for error messages in the page
                const bodyText = win.document.body.innerText;
                expect(bodyText).not.to.include('ODBC');
                expect(bodyText).not.to.include('buffer');
                expect(bodyText).not.to.include('[Microsoft]');
            });
        });
    });

    describe('_menu_items Form', () => {
        it('should load menu items via API', () => {
            // Test the subform API directly
            cy.request({
                method: 'GET',
                url: 'form_api.php?action=subform&form=_menus&ParentID=51&SubformIndex=0',
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                // Should have items array
                expect(response.body.items || response.body.data).to.be.an('array');
                // Inventarisatie menu has 3 items
                expect((response.body.items || response.body.data).length).to.be.at.least(1);
            });
        });

        it('should render subform table without ODBC error', () => {
            // Load parent menu first
            cy.openFormTree('_menus');

            // Click on Inventarisatie menu (id=51)
            cy.contains('#listContent a', 'Inventarisatie', { timeout: 10000 }).click();

            // Wait for detail to load
            cy.get('.form-layout', { timeout: 10000 }).should('be.visible');

            // Wait for name field to be populated
            cy.get('input[name="name"]', { timeout: 10000 }).should('have.value', 'Inventarisatie');

            // Subform should be rendered - look for Menu-items label and table
            cy.contains('Menu-items', { timeout: 15000 }).should('be.visible');
            cy.get('table tbody tr', { timeout: 10000 }).should('have.length.at.least', 1);

            // Verify no error messages
            cy.get('lib-message[type="error"]').should('not.exist');
        });

        it('should show menuId as hidden field for new menu items', () => {
            // Test that the menuId field exists (it's a hidden field per _menu_items.json)
            cy.visit('/main.php?page=' + encodeURIComponent('form.php?form=_menu_items&New=Y'));
            cy.get('lib-loader[active]', { timeout: 10000 }).should('not.exist');

            // Wait for form to load
            cy.get('.form-layout', { timeout: 10000 }).should('be.visible');

            // menuId is a hidden field per the form definition
            cy.get('input[name="menuId"]', { timeout: 10000 }).should('exist');
        });

        it('should not convert numeric values to boolean when saving', () => {
            // This test verifies the fix for the boolean conversion bug
            // where order=1 was being saved as order=true

            // First get the current value of a menu item
            cy.request({
                method: 'GET',
                url: 'form_api.php?action=record&jsonForm=_menu_items&ID=83', // Opleiding item
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;

                // order field should be a number, not boolean 'true'
                const orderValue = response.body.fields?.order || response.body.data?.order;
                expect(typeof orderValue).to.be.oneOf(['number', 'string']);
                expect(orderValue).to.not.eq(true);
                expect(orderValue).to.not.eq('true');
            });
        });

        it('should save menu item visible toggle via inline edit', () => {
            // This test verifies that lib-switch toggles in subform tables trigger AJAX saves
            // The global switch handler in inline-edit.js handles subform tables
            // (CmaInlineEdit only handles main table switches)

            // Load parent menu first
            cy.openFormTree('_menus');

            // Click on Inventarisatie menu (id=51)
            cy.contains('#listContent a', 'Inventarisatie', { timeout: 10000 }).click();

            // Wait for subform to load
            cy.get('.form-layout', { timeout: 10000 }).should('be.visible');
            cy.contains('Menu-items', { timeout: 15000 }).should('be.visible');
            cy.get('table.subform-table tbody tr', { timeout: 10000 }).should('have.length.at.least', 1);

            // Verify the subform table has the required data-json-form attribute
            cy.get('table.subform-table[data-json-form="_menu_items"]').should('exist');

            // Find a lib-switch in the subform table
            cy.get('table.subform-table tbody lib-switch[data-field="visible"]').first().then($switch => {
                const initialChecked = $switch.prop('checked');
                const row = $switch.closest('tr');
                const rowId = row.attr('data-id');

                // Intercept the save request (note: full path /cma/form_api.php)
                cy.intercept('POST', '**/form_api.php*').as('saveRequest');

                // Toggle the switch
                cy.wrap($switch).click();

                // Wait for save to complete and verify request data
                cy.wait('@saveRequest').then((interception) => {
                    expect(interception.response.statusCode).to.eq(200);

                    // Verify request body contains correct parameters
                    const requestBody = interception.request.body;
                    expect(requestBody).to.include('action=save');
                    expect(requestBody).to.include('jsonForm=_menu_items');
                    expect(requestBody).to.include('ID=' + rowId);
                    expect(requestBody).to.include('visible=');

                    // Parse response
                    const body = interception.response.body;
                    if (typeof body === 'object') {
                        expect(body.success).to.be.true;
                    }
                });

                // Switch should now have opposite state
                cy.wrap($switch).should(initialChecked ? 'not.have.attr' : 'have.attr', 'checked');
            });
        });
    });

    describe('contentblocks Form', () => {
        it('should load contentblocks list', () => {
            cy.openFormTree('contentblocks');
            cy.get('lib-loader[active]', { timeout: 10000 }).should('not.exist');

            // Wait for list to load
            cy.get('#listContent', { timeout: 10000 }).should('exist');

            // Should have contentblocks with string IDs (C47, B99, etc.)
            cy.get('[data-id]').should('have.length.at.least', 1);
        });

        it('should load detail for string ID B99 (Button)', () => {
            cy.openFormTree('contentblocks');
            cy.get('lib-loader[active]', { timeout: 10000 }).should('not.exist');

            // Find and click on Button item (ID = B99)
            cy.contains('#listContent a', 'Button', { timeout: 10000 }).click();

            // Wait for detail form
            cy.get('.form-layout', { timeout: 10000 }).should('be.visible');

            // ID field should show "B99" not "99"
            cy.get('input[name="id"]', { timeout: 10000 })
                .should('be.visible')
                .and('have.value', 'B99');

            // Title should be "Button"
            cy.get('input[name="title"]', { timeout: 10000 })
                .should('have.value', 'Button');
        });

        it('should load via direct URL with string ID', () => {
            // Test direct URL access with string ID using tree view
            cy.visit('/form.php?form=contentblocks&view=tree&id=B99');
            cy.get('lib-loader[active]', { timeout: 10000 }).should('not.exist');

            // Wait for detail form
            cy.get('.form-layout', { timeout: 10000 }).should('be.visible');

            // Verify no error messages
            cy.get('lib-message[type="error"]').should('not.exist');

            // Should show B99 details
            cy.get('input[name="id"]', { timeout: 10000 }).should('have.value', 'B99');
            cy.get('input[name="title"]', { timeout: 10000 }).should('have.value', 'Button');
        });

        it('should handle API record lookup with string ID', () => {
            // First visit the form to establish session
            cy.openFormTree('contentblocks');

            // Test the API with authenticated session
            // Note: The API returns 'fields' not 'data' for record responses
            cy.request({
                method: 'GET',
                url: 'form_api.php?action=record&form=contentblocks&id=B99',
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.fields).to.exist;
                expect(response.body.fields.id).to.eq('B99');
                expect(response.body.fields.title).to.eq('Button');
            });
        });

        it('should NOT show blockedit interface on html field', () => {
            // The contentblocks form edits content block templates
            // It should NOT show nested blockedit UI for the html field
            cy.visit('/form.php?form=contentblocks&view=tree&id=C74');
            cy.get('lib-loader[active]', { timeout: 10000 }).should('not.exist');

            // Wait for detail form
            cy.get('.form-layout', { timeout: 10000 }).should('be.visible');

            // The html textarea should NOT be wrapped in a .blockedit container
            cy.get('textarea[name="html"]').should('exist');
            cy.get('textarea[name="html"]').parent('.blockedit').should('not.exist');

            // There should be no blockedit_block elements in the form
            cy.get('.form-layout').within(() => {
                cy.get('.blockedit').should('not.exist');
                cy.get('.blockedit_block').should('not.exist');
            });
        });
    });

    describe('Dynamic Subform Height', () => {
        it('should calculate dynamic subform height based on field count', () => {
            // Clear any saved form state for _menus to test fresh calculation
            cy.window().then((win) => {
                // Clear localStorage for this form
                Object.keys(win.localStorage).forEach(key => {
                    if (key.includes('_menus')) {
                        win.localStorage.removeItem(key);
                    }
                });
            });

            // Load _menus form which has only 3 fields and subforms
            cy.openFormTree('_menus');
            cy.get('lib-loader[active]', { timeout: 10000 }).should('not.exist');

            // Wait for form to fully load
            cy.get('.form-layout', { timeout: 10000 }).should('be.visible');

            // Click on a menu item to show detail with subforms
            cy.contains('#listContent a', 'Opleidingen', { timeout: 10000 }).click();

            // Wait for detail form to load
            cy.get('#maintable', { timeout: 10000 }).should('be.visible');

            // Wait for subform section to appear
            cy.get('#subformSection', { timeout: 15000 }).should('exist');

            // Allow time for dynamic height calculation
            cy.wait(500);

            // Check that --subform-height CSS variable is set
            cy.window().then((win) => {
                const height = win.getComputedStyle(win.document.documentElement)
                    .getPropertyValue('--subform-height');

                // Height should be set (not empty or default)
                expect(height).to.not.be.empty;

                // Parse the height value
                const heightNum = parseInt(height, 10);
                expect(heightNum).to.be.a('number');

                // For a form with only 3 fields (3 * 40px = 120px estimated),
                // subform height should be larger than default 250px if there's space
                // At minimum it should be at least 150px (the minHeight constant)
                expect(heightNum).to.be.at.least(150);
            });
        });

        it('should restore user preference over calculated default', () => {
            // First set a user preference in localStorage before loading the form
            cy.window().then((win) => {
                // Use correct key format: form_state_ + formName
                const formKey = 'form_state__menus';
                win.localStorage.setItem(formKey, JSON.stringify({
                    subformHeight: '333px'
                }));
            });

            // Load _menus form
            cy.openFormTree('_menus');
            cy.get('lib-loader[active]', { timeout: 10000 }).should('not.exist');

            // Wait for form to fully load
            cy.get('.form-layout', { timeout: 10000 }).should('be.visible');

            // Click on a menu item to trigger form state
            cy.contains('#listContent a', 'Opleidingen', { timeout: 10000 }).click();
            cy.get('#maintable', { timeout: 10000 }).should('be.visible');

            // Allow time for restore
            cy.wait(500);

            // Verify user preference was restored (not the dynamic calculation)
            cy.window().then((win) => {
                const height = win.getComputedStyle(win.document.documentElement)
                    .getPropertyValue('--subform-height');
                expect(height.trim()).to.eq('333px');
            });
        });
    });
});
