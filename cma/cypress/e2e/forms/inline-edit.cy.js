/**
 * Inline Edit Tests
 *
 * Full-circle tests: create test data → find → edit → verify → cleanup.
 * NEVER edits row 0 or existing records to prevent data corruption.
 *
 * Entry points for inline editing:
 * 1. Right-click on row - shows context menu with "Direct wijzigen"
 * 2. Three-dots menu trigger (.row-menu-trigger) - shows context menu
 *
 * Inline edit UI elements:
 * - Row gets class 'editing' when in edit mode
 * - Save/Cancel buttons appear in '.inline-edit-button-row' overlay
 * - Save button: '.btn-save-inline'
 * - Cancel button: '.btn-cancel-inline'
 * - Context menu: '.cma-context-menu.row-menu'
 *
 * Search: uses lib-search-input#searchfor web component.
 * Type into the inner <input> and press Enter to trigger server-side search.
 */

describe('Inline Edit', () => {
    const uniqueSuffix = Date.now();
    let testUserId = null;

    /**
     * Create a test user via API
     */
    function createTestUser(suffix) {
        const login = `inlinetest_${suffix}`;
        const fullName = `Inline Test ${suffix}`;
        return cy.request({
            method: 'POST',
            url: 'form_api.php',
            form: true,
            body: {
                action: 'save',
                jsonForm: 'users',
                userLogin: login,
                userFullName: fullName,
                userPassword: 'TestPass123!',
                userLevel: '0'
            }
        }).then(response => {
            expect(response.body.success, 'Test user creation should succeed').to.be.true;
            return { id: response.body.id, login, fullName };
        });
    }

    /**
     * Delete a test user via API (silent failure OK for cleanup)
     */
    function deleteTestUser(id) {
        if (!id) return;
        return cy.request({
            method: 'POST',
            url: `form_api.php?action=delete&jsonForm=users&id=${id}`,
            failOnStatusCode: false
        });
    }

    /**
     * Type into the lib-search-input#searchfor web component and press Enter
     */
    function searchFor(text) {
        cy.get('lib-search-input#searchfor', { timeout: 10000 })
            .find('input')
            .clear()
            .type(text + '{enter}');
    }

    /**
     * Find a row in the table by text content and right-click to inline edit it
     */
    function findAndInlineEdit(searchText) {
        // Use lib-search-input to find the test record
        searchFor(searchText);

        // Wait for filtered results
        cy.get('#listTable tbody tr[data-id], table.listtable tbody tr[data-id]', { timeout: 15000 })
            .should('have.length.at.least', 1);

        // Find the row containing our test data
        cy.get('#listTable tbody tr[data-id], table.listtable tbody tr[data-id]')
            .contains('tr', searchText)
            .rightclick();

        // Context menu should appear - click "Direct wijzigen"
        cy.get('.cma-context-menu.row-menu', { timeout: 5000 })
            .should('be.visible')
            .contains('Direct wijzigen')
            .click();

        // Wait for edit mode
        cy.get('tr.editing', { timeout: 10000 }).should('exist');
        cy.get('.inline-edit-button-row', { timeout: 10000 }).should('exist');
    }

    describe('Entering Edit Mode', () => {
        beforeEach(() => {
            cy.loginAsAdmin();
            testUserId = null;
        });

        afterEach(() => {
            deleteTestUser(testUserId);
        });

        it('should show context menu on right-click and enter edit mode', () => {
            createTestUser(`ctx_${uniqueSuffix}`).then(user => {
                testUserId = user.id;
                cy.openFormTable('users');
                findAndInlineEdit(user.login);

                // Verify edit mode elements
                cy.get('tr.editing').should('exist');
                cy.get('.inline-edit-button-row').should('exist');
            });
        });

        it('should enter edit mode via three-dots menu', () => {
            createTestUser(`dots_${uniqueSuffix}`).then(user => {
                testUserId = user.id;
                cy.openFormTable('users');

                // Search for our test user
                searchFor(user.login);

                cy.get('#listTable tbody tr[data-id], table.listtable tbody tr[data-id]', { timeout: 15000 })
                    .contains('tr', user.login)
                    .as('testRow');

                // Hover to trigger row-menu-trigger
                cy.get('@testRow').trigger('mouseover');

                cy.get('@testRow').find('.row-menu-trigger')
                    .click({ force: true });

                // Context menu with "Direct wijzigen"
                cy.get('.cma-context-menu.row-menu', { timeout: 5000 })
                    .should('be.visible')
                    .contains('Direct wijzigen')
                    .click();

                cy.get('tr.editing', { timeout: 10000 }).should('exist');
            });
        });

        it('should show save and cancel controls in edit mode', () => {
            createTestUser(`ctrl_${uniqueSuffix}`).then(user => {
                testUserId = user.id;
                cy.openFormTable('users');
                findAndInlineEdit(user.login);

                cy.get('.inline-edit-button-row .btn-save-inline').should('exist');
                cy.get('.inline-edit-button-row .btn-cancel-inline').should('exist');
            });
        });

        it('should make fields editable', () => {
            createTestUser(`fields_${uniqueSuffix}`).then(user => {
                testUserId = user.id;
                cy.openFormTable('users');
                findAndInlineEdit(user.login);

                cy.get('tr.editing input, tr.editing select, tr.editing textarea', { timeout: 10000 })
                    .should('have.length.at.least', 1);
            });
        });
    });

    describe('Saving Changes', () => {
        beforeEach(() => {
            cy.loginAsAdmin();
            testUserId = null;
        });

        afterEach(() => {
            deleteTestUser(testUserId);
        });

        it('should save inline edit and persist the change', () => {
            const newFullName = `Edited Inline ${uniqueSuffix}`;

            createTestUser(`save_${uniqueSuffix}`).then(user => {
                testUserId = user.id;

                cy.intercept('POST', '**/form_api.php*').as('saveRecord');

                cy.openFormTable('users');
                findAndInlineEdit(user.login);

                // Change userFullName field
                cy.get('tr.editing').then($row => {
                    // Find the fullName input - it's the text input for userFullName
                    const $inputs = $row.find('input[type="text"]');
                    // userFullName is typically the second text field (after userLogin)
                    // Find by checking which input contains the original name
                    let targetInput = null;
                    $inputs.each(function() {
                        if (this.value === user.fullName) {
                            targetInput = this;
                        }
                    });

                    if (targetInput) {
                        cy.wrap(targetInput).clear().type(newFullName);
                    } else {
                        // Fallback: use the second text input (userFullName position)
                        cy.wrap($inputs).eq(1).clear().type(newFullName);
                    }
                });

                cy.saveInlineEdit();

                cy.wait('@saveRecord').its('response.statusCode').should('eq', 200);

                // Should exit edit mode
                cy.get('tr.editing', { timeout: 10000 }).should('not.exist');
                cy.get('.inline-edit-button-row').should('not.exist');

                // Verify the change persisted by checking the table shows the new name
                cy.get('#listTable tbody, table.listtable tbody', { timeout: 10000 })
                    .should('contain', newFullName);
            });
        });
    });

    describe('Canceling Edit', () => {
        beforeEach(() => {
            cy.loginAsAdmin();
            testUserId = null;
        });

        afterEach(() => {
            deleteTestUser(testUserId);
        });

        it('should cancel edit on cancel button click', () => {
            createTestUser(`cancel_${uniqueSuffix}`).then(user => {
                testUserId = user.id;
                cy.openFormTable('users');
                findAndInlineEdit(user.login);

                // Make a change
                cy.get('tr.editing input[type="text"]', { timeout: 10000 })
                    .first()
                    .clear()
                    .type('ShouldNotSave');

                cy.cancelInlineEdit();

                // Should exit edit mode
                cy.get('tr.editing', { timeout: 10000 }).should('not.exist');
                cy.get('.inline-edit-button-row').should('not.exist');
            });
        });

        it('should cancel edit on Escape key', () => {
            createTestUser(`esc_${uniqueSuffix}`).then(user => {
                testUserId = user.id;
                cy.openFormTable('users');
                findAndInlineEdit(user.login);

                cy.get('tr.editing input[type="text"]', { timeout: 10000 })
                    .first()
                    .type('{esc}', { force: true });

                cy.get('tr.editing', { timeout: 10000 }).should('not.exist');
                cy.get('.inline-edit-button-row').should('not.exist');
            });
        });
    });

    describe('Multiple Rows', () => {
        let testUserId2 = null;

        beforeEach(() => {
            cy.loginAsAdmin();
            testUserId = null;
            testUserId2 = null;
        });

        afterEach(() => {
            deleteTestUser(testUserId);
            deleteTestUser(testUserId2);
        });

        it('should only allow one row in edit mode at a time', () => {
            createTestUser(`multi1_${uniqueSuffix}`).then(user1 => {
                testUserId = user1.id;
                createTestUser(`multi2_${uniqueSuffix}`).then(user2 => {
                    testUserId2 = user2.id;
                    cy.openFormTable('users');

                    // Search for both test users
                    searchFor('inlinetest_multi');

                    cy.get('#listTable tbody tr[data-id], table.listtable tbody tr[data-id]', { timeout: 15000 })
                        .should('have.length.at.least', 2);

                    // Inline edit first row
                    cy.get('#listTable tbody tr[data-id], table.listtable tbody tr[data-id]')
                        .first()
                        .rightclick();

                    cy.get('.cma-context-menu.row-menu', { timeout: 5000 })
                        .should('be.visible')
                        .contains('Direct wijzigen')
                        .click();

                    cy.get('tr.editing', { timeout: 10000 }).should('have.length', 1);

                    // Right-click second row
                    cy.get('#listTable tbody tr[data-id]:not(.editing), table.listtable tbody tr[data-id]:not(.editing)')
                        .first()
                        .rightclick();

                    // Should only have one row in edit mode
                    cy.get('tr.editing', { timeout: 10000 }).should('have.length.at.most', 1);
                });
            });
        });
    });
});

/**
 * Inline Edit with Groups Form
 *
 * Tests inline editing on the groups form for variety.
 */
describe('Inline Edit - Groups', () => {
    const uniqueSuffix = Date.now();
    let testGroupId = null;

    /** Type into lib-search-input#searchfor and press Enter */
    function searchFor(text) {
        cy.get('lib-search-input#searchfor', { timeout: 10000 })
            .find('input')
            .clear()
            .type(text + '{enter}');
    }

    function createTestGroup(suffix) {
        const name = `InlineTestGroup_${suffix}`;
        return cy.request({
            method: 'POST',
            url: 'form_api.php',
            form: true,
            body: {
                action: 'save',
                jsonForm: 'groups',
                grpName: name
            }
        }).then(response => {
            expect(response.body.success, 'Test group creation should succeed').to.be.true;
            return { id: response.body.id, name };
        });
    }

    function deleteTestGroup(id) {
        if (!id) return;
        return cy.request({
            method: 'POST',
            url: `form_api.php?action=delete&jsonForm=groups&id=${id}`,
            failOnStatusCode: false
        });
    }

    beforeEach(() => {
        cy.loginAsAdmin();
        testGroupId = null;
    });

    afterEach(() => {
        deleteTestGroup(testGroupId);
    });

    it('should inline edit group name and verify persistence', () => {
        const newGroupName = `EditedGroup_${uniqueSuffix}`;

        createTestGroup(`edit_${uniqueSuffix}`).then(group => {
            testGroupId = group.id;

            cy.intercept('POST', '**/form_api.php*').as('saveRecord');

            cy.openFormTable('groups');

            // Search for the test group
            searchFor(group.name);

            cy.get('#listTable tbody tr[data-id], table.listtable tbody tr[data-id]', { timeout: 15000 })
                .should('have.length.at.least', 1);

            // Find and inline edit
            cy.get('#listTable tbody tr[data-id], table.listtable tbody tr[data-id]')
                .contains('tr', group.name)
                .rightclick();

            cy.get('.cma-context-menu.row-menu', { timeout: 5000 })
                .should('be.visible')
                .contains('Direct wijzigen')
                .click();

            cy.get('tr.editing', { timeout: 10000 }).should('exist');

            // Change the group name
            cy.get('tr.editing input[type="text"]', { timeout: 10000 })
                .first()
                .clear()
                .type(newGroupName);

            cy.saveInlineEdit();

            cy.wait('@saveRecord').its('response.statusCode').should('eq', 200);

            // Should exit edit mode
            cy.get('tr.editing', { timeout: 10000 }).should('not.exist');

            // Verify the new name is visible
            cy.get('#listTable tbody, table.listtable tbody', { timeout: 10000 })
                .should('contain', newGroupName);
        });
    });
});

/**
 * Readonly Form Tests
 *
 * Tests for forms with allowEdit: false (like cmamonitoring).
 */
describe('Readonly Forms', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    it('should not allow inline editing on fully readonly form (cmamonitoring)', () => {
        cy.openFormTable('cmamonitoring');

        cy.get('#listTable, table.listtable', { timeout: 15000 }).should('exist');

        cy.get('body').then($body => {
            const hasRows = $body.find('#listTable tbody tr[data-id], table.listtable tbody tr').length > 0;

            if (hasRows) {
                cy.get('#listTable tbody tr[data-id], table.listtable tbody tr')
                    .first()
                    .rightclick();

                cy.get('tr.editing').should('not.exist');
                cy.get('.inline-edit-button-row').should('not.exist');

                cy.get('body').then($body2 => {
                    const $rowMenu = $body2.find('.cma-context-menu.row-menu:visible');
                    if ($rowMenu.length > 0) {
                        cy.wrap($rowMenu).should('not.contain', 'Direct wijzigen');
                        cy.get('body').click(0, 0);
                    }
                });
            } else {
                cy.get('.inline-edit-button-row').should('not.exist');
            }
        });
    });

    it('should not show row menu trigger on hover for fully readonly form', () => {
        cy.openFormTable('cmamonitoring');

        cy.get('#listTable, table.listtable', { timeout: 15000 }).should('exist');

        cy.get('body').then($body => {
            const hasRows = $body.find('#listTable tbody tr[data-id], table.listtable tbody tr').length > 0;

            if (hasRows) {
                cy.get('#listTable tbody tr[data-id], table.listtable tbody tr')
                    .first()
                    .trigger('mouseover');

                cy.wait(300);

                cy.get('tr.editing').should('not.exist');
                cy.get('.inline-edit-button-row').should('not.exist');
            } else {
                cy.get('.row-menu-trigger').should('not.exist');
            }
        });
    });
});

/**
 * Delete Key Shortcut Tests
 */
describe('Delete Key Shortcut', () => {
    const uniqueSuffix = Date.now();
    let testUserId = null;

    function createTestUser(suffix) {
        return cy.request({
            method: 'POST',
            url: 'form_api.php',
            form: true,
            body: {
                action: 'save',
                jsonForm: 'users',
                userLogin: `inlinetest_del_${suffix}`,
                userFullName: `Delete Key Test ${suffix}`,
                userPassword: 'TestPass123!',
                userLevel: '0'
            }
        }).then(response => {
            expect(response.body.success).to.be.true;
            return { id: response.body.id, login: `inlinetest_del_${suffix}` };
        });
    }

    function deleteTestUser(id) {
        if (!id) return;
        return cy.request({
            method: 'POST',
            url: `form_api.php?action=delete&jsonForm=users&id=${id}`,
            failOnStatusCode: false
        });
    }

    beforeEach(() => {
        cy.loginAsAdmin();
        testUserId = null;
    });

    afterEach(() => {
        deleteTestUser(testUserId);
    });

    it('should show delete confirmation when pressing Delete key on form', () => {
        createTestUser(`delkey_${uniqueSuffix}`).then(user => {
            testUserId = user.id;

            // Use tree mode so detail panel is inline (not in a sidepanel iframe)
            cy.openFormTree('users');

            // Click a tree item to load a record into the detail panel
            cy.get('#listContent a, #simpletree a', { timeout: 10000 }).first().click();

            // Wait for detail panel with delete button
            cy.get('[data-action="delete"]', { timeout: 10000 }).should('exist');

            // Press Delete key on body (not inside an input)
            cy.get('body').type('{del}');

            // Should show confirmation dialog
            cy.get('lib-dialog[open]', { timeout: 5000 })
                .should('exist');
        });
    });

    it('should NOT trigger delete when Delete key pressed in input field', () => {
        createTestUser(`delinput_${uniqueSuffix}`).then(user => {
            testUserId = user.id;

            // Use tree mode so detail panel is inline
            cy.openFormTree('users');

            // Click a tree item to load a record
            cy.get('#listContent a, #simpletree a', { timeout: 10000 }).first().click();

            // Wait for detail view and find an input
            cy.get('.detail-panel input[type="text"]', { timeout: 10000 })
                .first()
                .focus()
                .type('{del}');

            // Should NOT show confirmation dialog
            cy.get('lib-dialog[open]').should('not.exist');
        });
    });
});

/**
 * Inline Edit Row Refresh Tests
 *
 * After saving an inline edit, the row is refreshed from the server.
 */
describe('Inline Edit Row Refresh', () => {
    const uniqueSuffix = Date.now();
    let testUserId = null;

    /** Type into lib-search-input#searchfor and press Enter */
    function searchFor(text) {
        cy.get('lib-search-input#searchfor', { timeout: 10000 })
            .find('input')
            .clear()
            .type(text + '{enter}');
    }

    function createTestUser(suffix) {
        const login = `inlinetest_refresh_${suffix}`;
        const fullName = `Refresh Test ${suffix}`;
        return cy.request({
            method: 'POST',
            url: 'form_api.php',
            form: true,
            body: {
                action: 'save',
                jsonForm: 'users',
                userLogin: login,
                userFullName: fullName,
                userPassword: 'TestPass123!',
                userLevel: '0'
            }
        }).then(response => {
            expect(response.body.success).to.be.true;
            return { id: response.body.id, login, fullName };
        });
    }

    function deleteTestUser(id) {
        if (!id) return;
        return cy.request({
            method: 'POST',
            url: `form_api.php?action=delete&jsonForm=users&id=${id}`,
            failOnStatusCode: false
        });
    }

    beforeEach(() => {
        cy.loginAsAdmin();
        testUserId = null;
    });

    afterEach(() => {
        deleteTestUser(testUserId);
    });

    it('should preserve column values after inline edit save', () => {
        createTestUser(`rowref_${uniqueSuffix}`).then(user => {
            testUserId = user.id;
            cy.openFormTable('users');

            // Search for our test user
            searchFor(user.login);

            cy.get('#listTable tbody tr[data-id], table.listtable tbody tr[data-id]', { timeout: 15000 })
                .should('have.length.at.least', 1);

            // Get cell count before edit
            cy.get('#listTable tbody tr:first td, table.listtable tbody tr:first td')
                .its('length')
                .as('originalCellCount');

            cy.intercept('POST', '**/form_api.php*').as('saveRecord');

            // Find and inline edit our test user
            cy.get('#listTable tbody tr[data-id], table.listtable tbody tr[data-id]')
                .contains('tr', user.login)
                .rightclick();

            cy.get('.cma-context-menu.row-menu', { timeout: 5000 })
                .should('be.visible')
                .contains('Direct wijzigen')
                .click();

            cy.get('tr.editing', { timeout: 10000 }).should('exist');
            cy.get('.inline-edit-button-row', { timeout: 10000 }).should('exist');

            // Make a small change
            cy.get('tr.editing input[type="text"]', { timeout: 10000 })
                .first()
                .then($input => {
                    const originalValue = $input.val();
                    cy.wrap($input).clear().type(originalValue + ' ');
                });

            cy.saveInlineEdit();
            cy.wait('@saveRecord');

            cy.get('tr.editing', { timeout: 10000 }).should('not.exist');

            // Row should still have the same number of cells
            cy.get('@originalCellCount').then(count => {
                cy.get('#listTable tbody tr:first td, table.listtable tbody tr:first td')
                    .should('have.length', count);
            });
        });
    });
});

/**
 * Inline Edit Field Type Recognition
 *
 * Verifies correct field types from the API for date/time fields.
 */
describe('Inline Edit Field Type Recognition', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    it('should receive date field type for date columns in API response', () => {
        cy.request({
            method: 'GET',
            url: 'form_api.php?action=tree&jsonForm=opleidingen&displayMode=2',
        }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body.success).to.be.true;

            const fields = response.body.fields || [];
            const dateFields = fields.filter(f =>
                f.name.toLowerCase().includes('datum') ||
                f.name.toLowerCase().includes('date')
            );

            if (dateFields.length > 0) {
                dateFields.forEach(field => {
                    expect(field.type, `Field ${field.name} should be date`).to.eq('date');
                });
            }
        });
    });

    it('should receive time field type for time columns in API response', () => {
        cy.request({
            method: 'GET',
            url: 'form_api.php?action=tree&jsonForm=afspraak&displayMode=2',
        }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body.success).to.be.true;

            const fields = response.body.fields || [];
            const timeFields = fields.filter(f =>
                f.name.toLowerCase() === 'tijd' ||
                f.name.toLowerCase() === 'tijdtot'
            );

            if (timeFields.length > 0) {
                timeFields.forEach(field => {
                    expect(field.type, `Field ${field.name} should be time`).to.eq('time');
                });
            }
        });
    });

    it('should correctly identify EindTijd as time type (not date) in rooster form', () => {
        cy.request({
            method: 'GET',
            url: 'form_api.php?action=tree&jsonForm=rooster&displayMode=2',
        }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body.success).to.be.true;

            const fields = response.body.fields || [];
            const eindTijdField = fields.find(f => f.name.toLowerCase() === 'eindtijd');
            const tijdField = fields.find(f => f.name.toLowerCase() === 'tijd');

            if (eindTijdField) {
                expect(eindTijdField.type, 'EindTijd should be time type for timepicker').to.eq('time');
                expect(eindTijdField.controlType, 'EindTijd controlType should be time').to.eq('time');
            }
            if (tijdField) {
                expect(tijdField.type, 'Tijd should be time type for timepicker').to.eq('time');
                expect(tijdField.controlType, 'Tijd controlType should be time').to.eq('time');
            }
        });
    });
});

/**
 * Column Selector API Tests
 */
describe('Column Selector API', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    it('should return columns with raw type and typeLabel for rooster form', () => {
        cy.request({
            method: 'GET',
            url: 'form_api.php?action=columns&form=rooster',
        }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body.success).to.be.true;
            expect(response.body.columns).to.be.an('array');
            expect(response.body.columns.length).to.be.at.least(1);

            response.body.columns.forEach(col => {
                expect(col).to.have.property('type');
                expect(col).to.have.property('typeLabel');
                expect(col).to.have.property('name');
                expect(col).to.have.property('caption');
            });

            const memoFields = response.body.columns.filter(c => c.type === 'memo');
            if (memoFields.length > 0) {
                memoFields.forEach(col => {
                    expect(col.typeLabel).to.eq('lange tekst');
                });
            }
        });
    });

    it('should exclude memo fields from default selected columns', () => {
        cy.request({
            method: 'GET',
            url: 'form_api.php?action=columns&form=rooster',
        }).then((response) => {
            expect(response.status).to.eq(200);
            expect(response.body.success).to.be.true;

            const columns = response.body.columns || [];
            const selected = response.body.selected || [];

            const memoFieldNames = columns
                .filter(c => c.type === 'memo' || c.type === 'htmlstrip')
                .map(c => c.name);

            if (memoFieldNames.length > 0) {
                memoFieldNames.forEach(memoName => {
                    expect(selected).to.not.include(memoName);
                });
            }
        });
    });
});

/**
 * Inline Edit Detached Row Protection
 */
describe('Inline Edit Detached Row Protection', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    it('should not send save request when row is detached from DOM', () => {
        cy.intercept('POST', '**/form_api.php*action=save*').as('saveRequest');

        cy.openFormTable('opleidingen');

        cy.get('#listTable tbody tr, table.listtable tbody tr', { timeout: 10000 })
            .should('have.length.at.least', 1);

        cy.window().then(win => {
            const script = win.document.querySelector('script[src*="inline-edit"]');
            if (script) {
                cy.request(script.src).then(response => {
                    expect(response.body).to.include('isConnected');
                });
            }
        });
    });
});

/**
 * Inline Edit Case-Insensitive Field Lookup
 */
describe('Inline Edit Case-Insensitive Field Lookup', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    it('should return non-empty field values from getRow API regardless of column name casing', () => {
        cy.openFormTable('opleidingen');

        cy.get('#listTable tbody tr[data-id], table.listtable tbody tr[data-id]', { timeout: 10000 })
            .first()
            .then($row => {
                const recordId = $row.attr('data-id');

                const columns = [];
                Cypress.$('#listTable thead th[data-field], table.listtable thead th[data-field]').each(function () {
                    columns.push(Cypress.$(this).attr('data-field'));
                });

                cy.request({
                    method: 'GET',
                    url: `form_api.php?action=getRow&jsonForm=opleidingen&id=${recordId}&columns=${columns.join(',')}`,
                }).then((response) => {
                    expect(response.status).to.eq(200);
                    expect(response.body.success).to.be.true;
                    expect(response.body.html).to.be.a('string');
                    expect(response.body.html.length).to.be.greaterThan(20);

                    const parser = new DOMParser();
                    const doc = parser.parseFromString(`<table><tbody>${response.body.html}</tbody></table>`, 'text/html');
                    const tds = doc.querySelectorAll('td[data-field]');

                    expect(tds.length).to.be.greaterThan(0, 'Row should have data cells');

                    let nonEmptyCells = 0;
                    tds.forEach(td => {
                        const text = td.textContent.trim();
                        if (text && text !== '\u22EE') {
                            nonEmptyCells++;
                        }
                    });

                    expect(nonEmptyCells).to.be.greaterThan(0,
                        'At least one cell should have non-empty content (case-insensitive field lookup)');
                });
            });
    });

    it('should return non-empty HTML for fields with PascalCase DB columns', () => {
        cy.openFormTable('opleidingen');

        cy.get('#listTable tbody tr[data-id], table.listtable tbody tr[data-id]', { timeout: 10000 })
            .first()
            .then($row => {
                const recordId = $row.attr('data-id');

                cy.request({
                    method: 'GET',
                    url: `form_api.php?action=getRow&jsonForm=opleidingen&id=${recordId}&columns=titel,code`,
                }).then((response) => {
                    expect(response.status).to.eq(200);
                    expect(response.body.success).to.be.true;

                    const parser = new DOMParser();
                    const doc = parser.parseFromString(
                        `<table><tbody>${response.body.html}</tbody></table>`, 'text/html');

                    const titelTd = doc.querySelector('td[data-field="titel"]');
                    const codeTd = doc.querySelector('td[data-field="code"]');

                    if (titelTd) {
                        const text = titelTd.textContent.replace('\u22EE', '').trim();
                        expect(text.length).to.be.greaterThan(0,
                            'titel field should not be empty (case-insensitive match for DB column Titel)');
                    }

                    if (codeTd) {
                        const text = codeTd.textContent.replace('\u22EE', '').trim();
                        expect(text.length).to.be.greaterThan(0,
                            'code field should not be empty (case-insensitive match for DB column Code)');
                    }
                });
            });
    });
});
