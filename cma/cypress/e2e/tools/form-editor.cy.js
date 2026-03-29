/**
 * Form Definition Editor Tests
 *
 * Tests for tools_formedit.php - visual editor for JSON form definitions
 * with tree navigation for form hierarchy.
 *
 * Run: npx cypress run --spec "cypress/e2e/tools/form-editor.cy.js"
 */

describe('Form Definition Editor', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        // Intercept buildTree before visiting so we can wait for it
        cy.intercept('GET', '**/tools_formedit.php?action=buildTree*').as('buildTree');
        cy.visit('/tools/tools_formedit.php');
        // Wait for the tree data to load
        cy.wait('@buildTree', { timeout: 10000 });
        cy.wait(500); // Allow DOM to render after data arrives
    });

    /**
     * Helper: wait for tree to load and select a form by clicking it in the tree.
     * Uses shadow DOM piercing to find nodes inside cma-tree.
     */
    function selectFormInTree(formName) {
        // Tree should already be populated from beforeEach

        // Intercept the load request before triggering click
        cy.intercept('GET', '**/tools_formedit.php?action=loadForm*').as('loadForm');

        // Use selectById via the component API, then click
        cy.get('#formTree').then($tree => {
            const tree = $tree[0];
            if (typeof tree.selectById === 'function') {
                tree.selectById(formName);
            }
            // Find the node in shadow DOM and click
            const shadowRoot = tree.shadowRoot;
            if (shadowRoot) {
                const nodeEl = shadowRoot.querySelector(`[data-node-id="${formName}"]`);
                if (nodeEl) {
                    nodeEl.click();
                }
            }
        });

        cy.wait('@loadForm', { timeout: 10000 });
        cy.get('#editorArea').should('be.visible');
    }

    // ═══════════════════════════════════════════════════════════════
    // PAGE STRUCTURE
    // ═══════════════════════════════════════════════════════════════

    describe('Page Structure', () => {
        it('should display split layout with tree and editor', () => {
            cy.get('#leftlist').should('exist');
            cy.get('#rightPanel').should('exist');
            cy.get('#formTree').should('exist');
        });

        it('should display standard toolbar with buttons', () => {
            cy.get('#toolbar').should('exist');
            cy.get('#newBtn').should('exist').and('contain.text', 'Toevoegen');
            cy.get('#saveBtn').should('exist').and('have.class', 'disabled');
            cy.get('#jsonBtn').should('exist').and('contain.text', 'JSON');
        });

        it('should have save notice element hidden on load', () => {
            cy.get('#fe-save-notice').should('have.attr', 'hidden');
        });

        it('should show welcome message when no form selected', () => {
            cy.get('#welcomeMsg').should('be.visible');
            cy.get('#editorArea').should('not.be.visible');
        });

        it('should hide save notice by default', () => {
            cy.get('#fe-save-notice').should('have.attr', 'hidden');
        });

        it('should have loading spinner element in toolbar (hidden by default)', () => {
            cy.get('#formLoadSpinner').should('exist').and('not.be.visible');
        });

        it('should have tree populated with forms', () => {
            // Tree is already loaded from beforeEach (waits for buildTree)
            cy.get('#formTree').then($tree => {
                const tree = $tree[0];
                const shadowRoot = tree.shadowRoot;
                // Should have items in the tree
                expect(shadowRoot.querySelectorAll('li').length).to.be.greaterThan(0);
            });
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // TREE HIERARCHY
    // ═══════════════════════════════════════════════════════════════

    describe('Tree Hierarchy', () => {
        it('should have folders for parent forms with subforms', () => {
            // Tree is already loaded from beforeEach
            cy.get('#formTree').then($tree => {
                const shadowRoot = $tree[0].shadowRoot;
                // Folders have f_open or f_closed class
                const folders = shadowRoot.querySelectorAll('li.f_open, li.f_closed');
                expect(folders.length).to.be.greaterThan(1); // At least root + some parents
            });
        });

        it('should have leaf items for forms without subforms', () => {
            cy.get('#formTree').then($tree => {
                const shadowRoot = $tree[0].shadowRoot;
                // Items have data-node-id on their anchors
                const items = shadowRoot.querySelectorAll('a[data-node-id]');
                expect(items.length).to.be.greaterThan(0);
            });
        });

        it('should show nested subforms in tree', () => {
            // Deelnemers has subforms - check recursive nesting
            cy.get('#formTree').then($tree => {
                const tree = $tree[0];
                tree.expandAll();
            });
            cy.wait(500);
            cy.get('#formTree').then($tree => {
                const shadowRoot = $tree[0].shadowRoot;
                // Find a clickable folder (parent form with id)
                const clickableFolders = shadowRoot.querySelectorAll('[data-clickable-folder]');
                expect(clickableFolders.length).to.be.greaterThan(0);
            });
        });

        it('should expand and collapse via toolbar buttons', () => {
            // Collapse all
            cy.window().then(win => {
                if (typeof win.fCollapseAll === 'function') {
                    win.fCollapseAll();
                }
            });
            cy.wait(300);
            // Expand all
            cy.window().then(win => {
                if (typeof win.fExpandAll === 'function') {
                    win.fExpandAll();
                }
            });
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // VIEW TOGGLE (TREE / TABLE)
    // ═══════════════════════════════════════════════════════════════

    describe('View Toggle', () => {
        it('should have tree and table view toggle buttons', () => {
            cy.get('#btn_viewTree').should('exist');
            cy.get('#btn_viewTable').should('exist');
        });

        it('should show tree view by default', () => {
            cy.get('#leftlist .tree-area').should('be.visible');
            cy.get('#leftlist .table-area').should('not.be.visible');
        });

        it('should switch to table view when table button clicked', () => {
            cy.get('#btn_viewTable a').click();
            cy.get('#leftlist .tree-area').should('not.be.visible');
            cy.get('#leftlist .table-area').should('be.visible');
        });

        it('should switch back to tree view when tree button clicked', () => {
            cy.get('#btn_viewTable a').click();
            cy.get('#leftlist .table-area').should('be.visible');
            cy.get('#btn_viewTree a').click();
            cy.get('#leftlist .tree-area').should('be.visible');
            cy.get('#leftlist .table-area').should('not.be.visible');
        });

        it('should highlight active view toggle button', () => {
            cy.get('#btn_viewTree').should('have.class', 'fe-view-active');
            cy.get('#btn_viewTable').should('not.have.class', 'fe-view-active');

            cy.get('#btn_viewTable a').click();
            cy.get('#btn_viewTable').should('have.class', 'fe-view-active');
            cy.get('#btn_viewTree').should('not.have.class', 'fe-view-active');
        });

        it('should populate table with form rows', () => {
            cy.get('#btn_viewTable a').click();
            // Table is populated from same buildTree data that was already loaded
            cy.get('#formTableBody tr', { timeout: 5000 }).should('have.length.greaterThan', 0);
        });

        it('should show form name, title, table columns in table view', () => {
            cy.get('#btn_viewTable a').click();
            cy.get('#formTableBody tr', { timeout: 5000 }).first().within(() => {
                cy.get('td').should('have.length', 4);
                cy.get('td').eq(0).invoke('text').should('not.be.empty'); // Naam
                cy.get('td').eq(1).invoke('text').should('not.be.empty'); // Titel
            });
        });

        it('should load form when clicking table row', () => {
            cy.get('#btn_viewTable a').click();
            cy.get('#formTableBody tr', { timeout: 5000 }).should('have.length.greaterThan', 0);
            cy.intercept('GET', '**/tools_formedit.php?action=loadForm*').as('loadForm');
            cy.get('#formTableBody tr').first().click({force: true});
            cy.wait('@loadForm');
            cy.get('#editorArea').should('be.visible');
        });

        it('should highlight selected row in table view', () => {
            cy.get('#btn_viewTable a').click();
            cy.get('#formTableBody tr', { timeout: 5000 }).should('have.length.greaterThan', 0);
            cy.intercept('GET', '**/tools_formedit.php?action=loadForm*').as('loadForm');
            cy.get('#formTableBody tr').first().click({force: true});
            cy.wait('@loadForm');
            cy.get('#formTableBody tr.selected').should('have.length', 1);
        });

        it('should hide expand/collapse buttons in table mode', () => {
            cy.get('#btn_viewTable a').click();
            cy.get('#btn_expand').should('not.be.visible');
            cy.get('#btn_collapse').should('not.be.visible');
        });

        it('should persist view mode in localStorage', () => {
            cy.get('#btn_viewTable a').click();
            cy.window().then(win => {
                expect(win.localStorage.getItem('fe_view_mode')).to.eq('table');
            });
            cy.get('#btn_viewTree a').click();
            cy.window().then(win => {
                expect(win.localStorage.getItem('fe_view_mode')).to.eq('tree');
            });
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // FORM LOADING
    // ═══════════════════════════════════════════════════════════════

    describe('Form Loading', () => {
        it('should load a form when clicked in tree', () => {
            selectFormInTree('logins');

            // Editor should be visible now
            cy.get('#editorArea').should('be.visible');
            cy.get('#welcomeMsg').should('not.be.visible');
        });

        it('should populate general settings when form loaded', () => {
            selectFormInTree('logins');

            // Check general fields populated
            cy.get('#gs-name').should('have.value', 'logins');
            cy.get('#gs-title').invoke('val').should('not.be.empty');
        });

        it('should deep-link via formName parameter', () => {
            cy.intercept('GET', '**/tools_formedit.php?action=buildTree*').as('buildTreeDeepLink');
            cy.intercept('GET', '**/tools_formedit.php?action=loadForm*').as('loadFormDeepLink');
            cy.visit('/tools/tools_formedit.php?formName=logins');
            cy.wait('@buildTreeDeepLink', { timeout: 10000 });
            cy.wait('@loadFormDeepLink', { timeout: 10000 });

            // Editor should auto-load
            cy.get('#editorArea').should('be.visible');
            cy.get('#gs-name').should('have.value', 'logins');
        });

        it('should load parent form when clicking folder in tree', () => {
            // Intercept load to detect which form gets loaded
            cy.intercept('GET', '**/tools_formedit.php?action=loadForm*').as('loadForm');

            // Find a clickable folder and click it
            cy.get('#formTree').then($tree => {
                const tree = $tree[0];
                tree.expandAll();
            });
            cy.wait(500);

            cy.get('#formTree').then($tree => {
                const shadowRoot = $tree[0].shadowRoot;
                const clickableFolder = shadowRoot.querySelector('[data-clickable-folder]');
                if (clickableFolder) {
                    clickableFolder.click();
                }
            });

            cy.wait('@loadForm');
            cy.get('#editorArea').should('be.visible');
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // GENERAL SETTINGS
    // ═══════════════════════════════════════════════════════════════

    describe('General Settings', () => {
        beforeEach(() => {
            selectFormInTree('logins');
        });

        it('should have name field readonly', () => {
            cy.get('#gs-name').should('have.attr', 'readonly');
        });

        it('should have required attribute on schema-required fields', () => {
            // Top-level required: name, table
            cy.get('#gs-name').should('have.attr', 'required');
            cy.get('#gs-table').should('have.attr', 'required');
        });

        it('should display switches for boolean options', () => {
            cy.get('#gs-allowAdd').should('exist');
            cy.get('#gs-allowDelete').should('exist');
            cy.get('#gs-allowCopy').should('exist');
            cy.get('#gs-storeLastModified').should('exist');
        });

        it('should mark dirty when title is edited', () => {
            cy.get('#saveBtn').should('have.class', 'disabled');
            cy.get('#gs-title').clear().type('Test gewijzigd');
            cy.get('#saveBtn').should('not.have.class', 'disabled');
            // Save button link should turn red when dirty
            cy.get('#saveBtn a').should('have.css', 'color').and('not.eq', '');
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // ACCORDION SECTIONS
    // ═══════════════════════════════════════════════════════════════

    describe('Accordion Sections', () => {
        beforeEach(() => {
            selectFormInTree('logins');
        });

        it('should display all accordion sections', () => {
            cy.get('cma-groupbox[data-section="general"]').should('exist');
            cy.get('cma-groupbox[data-section="list"]').should('exist');
            cy.get('cma-groupbox[data-section="fields"]').should('exist');
            cy.get('cma-groupbox[data-section="subforms"]').should('exist');
            cy.get('cma-groupbox[data-section="advanced"]').should('exist');
        });

        it('should toggle advanced section on click', () => {
            cy.get('#section-advanced').should('not.be.visible');
            cy.get('cma-groupbox[data-section="advanced"]').click();
            cy.get('#section-advanced').should('be.visible');
            cy.get('cma-groupbox[data-section="advanced"]').click();
            cy.get('#section-advanced').should('not.be.visible');
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // FIELDS MANAGEMENT
    // ═══════════════════════════════════════════════════════════════

    describe('Fields Management', () => {
        beforeEach(() => {
            selectFormInTree('logins');
        });

        it('should display fields list with field count', () => {
            cy.get('#fieldsListBody tr').should('have.length.greaterThan', 0);
        });

        it('should wrap fields table in lib-table for filtering', () => {
            cy.get('#fieldsListBody').closest('table.filtering').parent('lib-table').should('exist');
        });

        it('should display field type badges', () => {
            cy.get('#fieldsListBody lib-label').should('have.length.greaterThan', 0);
        });

        it('should open field editor dialog on edit click', () => {
            cy.get('#fieldsListBody tr:first button[data-action="edit"]').click();
            cy.get('#fieldEditorDialog').should('have.attr', 'open');
            // Should have tabs
            cy.get('#fieldEditorTabs').should('exist');
        });

        it('should populate field editor with field data', () => {
            cy.get('#fieldsListBody tr:first button[data-action="edit"]').click();
            cy.get('#fe-name').invoke('val').should('not.be.empty');
            cy.get('#fe-type').invoke('val').should('not.be.empty');
        });

        it('should add a new field', () => {
            cy.get('#fieldsListBody tr').its('length').then(initialCount => {
                cy.get('#addFieldBtn').click();
                cy.get('#fieldEditorDialog').should('have.attr', 'open');
                cy.get('#fe-name').clear({force: true}).type('testVeld', {force: true});
                cy.get('#fe-caption').clear({force: true}).type('Test veld', {force: true});
                cy.get('#saveFieldBtn').click({force: true});
                cy.get('#fieldEditorDialog').should('not.have.attr', 'open');
                cy.get('#fieldsListBody tr').should('have.length', initialCount + 1);
            });
        });

        it('should delete a field', () => {
            cy.get('#fieldsListBody tr').its('length').then(initialCount => {
                // Stub libConfirm to return true (used instead of window.confirm)
                cy.window().then(win => {
                    win.libConfirm = () => Promise.resolve(true);
                });
                cy.get('#fieldsListBody tr:last button[data-action="delete"]').click();
                // Wait for DOM update after delete
                cy.wait(1000);
                cy.get('#fieldsListBody tr', { timeout: 5000 }).should('have.length', initialCount - 1);
            });
        });

        it('should move field up', () => {
            cy.get('#fieldsListBody tr').its('length').then(count => {
                if (count < 2) return; // Need at least 2 fields

                // Get second field name
                cy.get('#fieldsListBody tr:nth-child(2) td:first').invoke('text').then(secondName => {
                    cy.get('#fieldsListBody tr:nth-child(2) button[data-action="up"]').click();
                    // Second field should now be first
                    cy.get('#fieldsListBody tr:first td:first').should('contain.text', secondName);
                });
            });
        });

        it('should move field down', () => {
            cy.get('#fieldsListBody tr').its('length').then(count => {
                if (count < 2) return;

                cy.get('#fieldsListBody tr:first td:first').invoke('text').then(firstName => {
                    cy.get('#fieldsListBody tr:first button[data-action="down"]').click();
                    cy.get('#fieldsListBody tr:nth-child(2) td:first').should('contain.text', firstName);
                });
            });
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // FIELD EDITOR TABS
    // ═══════════════════════════════════════════════════════════════

    describe('Field Editor Dialog', () => {
        beforeEach(() => {
            selectFormInTree('logins');
        });

        it('should show cma-tabs in field editor', () => {
            cy.get('#fieldsListBody tr:first button[data-action="edit"]').click();
            cy.get('#fieldEditorTabs').should('exist');
        });

        it('should display dialog heading when editing a field', () => {
            cy.get('#fieldsListBody tr:first button[data-action="edit"]').click();
            cy.get('#fieldEditorDialog').should('have.attr', 'heading').and('contain', 'Veld bewerken');
        });

        it('should have btn-cancel class on cancel button', () => {
            cy.get('#fieldsListBody tr:first button[data-action="edit"]').click();
            cy.get('#fieldEditorDialog .btn-cancel').should('exist').and('contain.text', 'Annuleren');
        });

        it('should have required attribute on name and type fields', () => {
            cy.get('#fieldsListBody tr:first button[data-action="edit"]').click();
            cy.get('#fe-name').should('have.attr', 'required');
            cy.get('#fe-type').should('have.attr', 'required');
        });

        it('should hide source tab for non-source field types', () => {
            cy.get('#addFieldBtn').click();
            cy.get('#fe-type').select('textbox', {force: true});
            // Source tab panel should be hidden via display:none style
            cy.get('#fieldEditorTabs [data-tab-id="source"]').should('have.css', 'display', 'none');
        });

        it('should show source tab for combobox type', () => {
            cy.get('#addFieldBtn').click();
            cy.get('#fe-type').select('combobox', {force: true});
            // Source tab panel should NOT be hidden (display:none removed)
            cy.get('#fieldEditorTabs [data-tab-id="source"]').should('not.have.css', 'display', 'none');
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // LIST COLUMNS
    // ═══════════════════════════════════════════════════════════════

    describe('List Columns', () => {
        beforeEach(() => {
            selectFormInTree('logins');
        });

        it('should add a list column row', () => {
            // logins may have 0 list columns initially - click add and verify at least 1 row
            cy.get('#addListColBtn a').click({force: true});
            cy.get('#listColumnsBody tr').should('have.length.gte', 1);
        });

        it('should delete a list column row', () => {
            // Add two columns with data so collectListColumns retains them
            cy.get('#addListColBtn a').click({force: true});
            cy.get('#listColumnsBody tr').should('have.length.gte', 1);
            cy.get('#listColumnsBody tr:last .lc-field').clear().type('col1');
            cy.get('#addListColBtn a').click({force: true});
            cy.get('#listColumnsBody tr').should('have.length', 2);
            cy.get('#listColumnsBody tr:last .lc-field').clear().type('col2');
            // Now delete the last column - should go from 2 to 1
            cy.get('#listColumnsBody tr:last button[data-action="deleteCol"]').click({force: true});
            cy.get('#listColumnsBody tr').should('have.length', 1);
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // SUBFORMS
    // ═══════════════════════════════════════════════════════════════

    describe('Subforms', () => {
        beforeEach(() => {
            selectFormInTree('logins');
        });

        it('should display add subform button', () => {
            cy.get('#addSubformBtn').should('exist');
        });

        it('should open subform editor dialog with heading', () => {
            cy.get('#addSubformBtn').click();
            cy.get('#subformEditorDialog').should('have.attr', 'open');
            cy.get('#subformEditorDialog').should('have.attr', 'heading').and('contain', 'Nieuw subformulier');
        });

        it('should have btn-cancel class on subform cancel button', () => {
            cy.get('#addSubformBtn').click();
            cy.get('#subformEditorDialog').should('have.attr', 'open');
            cy.get('#subformEditorDialog .btn-cancel').should('exist').and('contain.text', 'Annuleren');
        });

        it('should have lib-combo for form selection in subform editor', () => {
            cy.get('#addSubformBtn').click();
            cy.get('#subformEditorDialog').should('have.attr', 'open');
            cy.get('#subformEditorDialog lib-combo#sf-form').should('exist');
        });

        it('should have parent field input in subform editor', () => {
            cy.get('#addSubformBtn').click();
            cy.get('#subformEditorDialog').should('have.attr', 'open');
            cy.get('#subformEditorDialog #sf-parentField').should('exist');
        });

        it('should populate form combo with available forms', () => {
            cy.get('#addSubformBtn').click();
            cy.get('#subformEditorDialog').should('have.attr', 'open');
            cy.get('#subformEditorDialog lib-combo#sf-form').then($combo => {
                // lib-combo should have options loaded from form list
                cy.wrap($combo).should('exist');
            });
        });

        it('should have up/down reorder buttons in subforms table', () => {
            // First add a subform since logins may have none initially
            cy.get('#addSubformBtn').click();
            cy.get('#subformEditorDialog').should('have.attr', 'open');
            cy.get('#subformEditorDialog lib-combo#sf-form').then($combo => {
                const combo = $combo[0];
                if (combo && combo.setOptions) {
                    combo.setOptions([{ value: 'test_sub', label: 'Test (test_sub)' }]);
                    combo.value = 'test_sub';
                }
            });
            cy.get('#sf-title').clear({force: true}).type('Test', {force: true});
            cy.get('#saveSubformBtn').click({force: true});
            cy.get('#subformEditorDialog').should('not.have.attr', 'open');
            // Now check the subforms table has up/down buttons
            cy.get('#subformsListBody tr').first().within(() => {
                cy.get('button[data-action="up"]').should('exist');
                cy.get('button[data-action="down"]').should('exist');
            });
        });

        it('should not have Volgorde column in subforms table header', () => {
            // The table header is always present regardless of data
            cy.get('#subformsListBody').closest('table').find('thead').should('not.contain', 'Volgorde');
            cy.get('#subformsListBody').closest('table').find('thead').should('contain', 'Titel');
            cy.get('#subformsListBody').closest('table').find('thead').should('contain', 'Formulier');
            cy.get('#subformsListBody').closest('table').find('thead').should('contain', 'Acties');
        });

        it('should save new subform from editor', () => {
            // Get initial count (may be 0)
            cy.get('#subformsListBody').then($body => {
                const initialCount = $body.find('tr').length;
                cy.get('#addSubformBtn').click();
                cy.get('#subformEditorDialog').should('have.attr', 'open');
                // Use lib-combo for form selection
                cy.get('#subformEditorDialog lib-combo#sf-form').then($combo => {
                    const combo = $combo[0];
                    if (combo && combo.setOptions) {
                        combo.setOptions([{ value: 'test_subform', label: 'Test (test_subform)' }]);
                        combo.value = 'test_subform';
                    }
                });
                cy.get('#sf-title').clear({force: true}).type('Test subformulier', {force: true});
                cy.get('#saveSubformBtn').click({force: true});
                cy.get('#subformEditorDialog').should('not.have.attr', 'open');
                cy.get('#subformsListBody tr').should('have.length', initialCount + 1);
            });
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // JSON PREVIEW
    // ═══════════════════════════════════════════════════════════════

    describe('JSON Preview', () => {
        beforeEach(() => {
            selectFormInTree('logins');
        });

        it('should open JSON preview dialog', () => {
            cy.get('#jsonBtn').click();
            cy.get('#jsonPreviewDialog').should('have.attr', 'open');
        });

        it('should show valid formatted JSON', () => {
            cy.get('#jsonBtn').click();
            cy.get('#jsonPreviewDialog').should('have.attr', 'open');
            cy.get('#jsonPreviewArea').invoke('val').then(json => {
                expect(json).to.not.be.empty;
                // Should be valid JSON
                const parsed = JSON.parse(json);
                expect(parsed).to.have.property('name');
                expect(parsed).to.have.property('fields');
            });
        });

        it('should have a download button in JSON dialog', () => {
            cy.get('#jsonBtn').click();
            cy.get('#jsonPreviewDialog').should('have.attr', 'open');
            cy.get('#jsonDownloadBtn').should('exist');
            cy.get('#jsonDownloadBtn').should('contain.text', 'Downloaden');
        });

        it('should have a copy button in JSON dialog', () => {
            cy.get('#jsonBtn').click();
            cy.get('#jsonCopyBtn').should('exist').and('contain.text', 'Kopieer naar klembord');
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // SAVE
    // ═══════════════════════════════════════════════════════════════

    describe('Save Form', () => {
        beforeEach(() => {
            selectFormInTree('logins');
        });

        it('should intercept save and verify payload structure', () => {
            // Intercept save call
            cy.intercept('POST', '**/tools_formedit.php', (req) => {
                const body = req.body;
                expect(body).to.have.property('action', 'save');
                expect(body).to.have.property('formName');
                expect(body).to.have.property('definition');
                expect(body.definition).to.have.property('name');
                expect(body.definition).to.have.property('fields');

                // Reply with success without actually saving
                req.reply({ success: true, message: 'Formulier opgeslagen' });
            }).as('saveForm');

            // Make a change to enable save
            cy.get('#gs-title').clear().type('Test titel wijziging');
            cy.get('#saveBtn').click();

            cy.wait('@saveForm');
        });

        it('should show save notice with filename after save', () => {
            // Save notice should be hidden before save
            cy.get('#fe-save-notice').should('have.attr', 'hidden');

            cy.intercept('POST', '**/tools_formedit.php', (req) => {
                req.reply({ success: true, message: 'Formulier opgeslagen' });
            }).as('saveForm');

            cy.get('#gs-title').clear().type('Test titel');
            cy.get('#saveBtn').click();
            cy.wait('@saveForm');

            // Save notice should appear as lib-message with the form filename
            cy.get('#fe-save-notice').should('not.have.attr', 'hidden');
            cy.get('#fe-save-notice').should('contain.text', 'logins.json');
        });

        it('should validate required fields before save', () => {
            // First make a change to enable the save button
            cy.get('#gs-title').clear().type('Validatietest');
            cy.get('#saveBtn').should('not.have.class', 'disabled');

            // Clear the required table field via lib-combo API
            cy.get('#gs-table').then($combo => {
                const combo = $combo[0];
                if (typeof combo.clear === 'function') {
                    combo.clear();
                } else if (combo.value !== undefined) {
                    combo.value = '';
                }
            });

            // Intercept to ensure no actual save happens
            cy.intercept('POST', '**/tools_formedit.php', (req) => {
                // Should not reach here if validation works
                req.reply({ success: false, message: 'Should not save' });
            }).as('saveAttempt');

            cy.get('#saveBtn').click();

            // Should show toast error or the save should be blocked
            // Wait briefly and verify no save request was made
            cy.wait(1000);
            cy.get('@saveAttempt.all').should('have.length', 0);
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // UNSAVED CHANGES WARNING
    // ═══════════════════════════════════════════════════════════════

    describe('Unsaved Changes', () => {
        beforeEach(() => {
            selectFormInTree('logins');
        });

        it('should turn save button red after editing', () => {
            cy.get('#saveBtn').should('have.class', 'disabled');
            cy.get('#gs-title').clear().type('Gewijzigde titel');
            cy.get('#saveBtn').should('not.have.class', 'disabled');
            // The inner <a> should have red color
            cy.get('#saveBtn a').invoke('css', 'color').should('not.eq', 'rgb(0, 0, 0)');
        });

        it('should enable save button after editing', () => {
            cy.get('#saveBtn').should('have.class', 'disabled');
            cy.get('#gs-title').clear().type('Gewijzigde titel');
            cy.get('#saveBtn').should('not.have.class', 'disabled');
        });

        it('should warn when switching forms with unsaved changes', () => {
            // Make a change
            cy.get('#gs-title').clear().type('Gewijzigde titel');
            cy.get('#saveBtn').should('not.have.class', 'disabled');

            // Stub confirm to capture the call
            cy.on('window:confirm', () => true);

            // Click another form in the tree
            cy.get('#formTree').then($tree => {
                const shadowRoot = $tree[0].shadowRoot;
                // Find a different form to click
                const allNodes = shadowRoot.querySelectorAll('a[data-node-id]');
                for (const node of allNodes) {
                    if (node.getAttribute('data-node-id') !== 'logins') {
                        node.click();
                        break;
                    }
                }
            });
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // NEW FORM (WIZARD)
    // ═══════════════════════════════════════════════════════════════

    describe('New Form Wizard', () => {
        it('should open form wizard dialog on new button click', () => {
            cy.get('#newBtn').click();
            cy.get('#formwizDialog').should('have.attr', 'open');
            cy.get('#formwizIframe').should('have.attr', 'src').and('include', 'tools_formwiz.php');
        });
    });
});
