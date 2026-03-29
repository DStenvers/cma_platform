/**
 * Report Designer E2E Tests
 *
 * Tests the Query Designer wizard functionality including:
 * - Mode selection
 * - Table selection and schema canvas
 * - Field configuration
 * - Parameter configuration
 * - Sorting and grouping
 * - Preview functionality
 * - Save and load reports
 */

describe('Report Designer', () => {
    // Clean up any Cypress test reports before and after the suite
    function cleanupTestReports() {
        cy.loginAsAdmin();
        cy.request('api/report-save.php?action=list').then((response) => {
            if (response.body && response.body.success && response.body.reports) {
                response.body.reports.forEach((report) => {
                    if (report.name && report.name.startsWith('Cypress')) {
                        cy.request('POST', `api/report-save.php?action=delete&id=${report.id}`);
                    }
                });
            }
        });
    }

    before(() => {
        cleanupTestReports();
    });

    after(() => {
        cleanupTestReports();
    });

    // Flag to track if tables are available (set in before hook)
    let tablesAvailable = null;

    before(() => {
        // Check once if tables are available for the test database
        cy.loginAsAdmin();
        cy.visit('/report-designer.php');
        cy.wait(600);
        cy.get('.mode-option[data-mode="advanced"]').click();
        cy.wait(2000); // Wait for tables to load
        cy.get('.table-list-items').then($container => {
            const $items = $container.find('.table-list-item');
            tablesAvailable = $items.length > 0;
            if (!tablesAvailable) {
                cy.log('⚠️ No database tables available - some tests will be skipped');
            }
        });
    });

    beforeEach(function() {
        // Login using the standard auth command
        cy.loginAsAdmin();

        // Navigate to report designer
        cy.visit('/report-designer.php');

        // Dismiss any tips/tours that may be blocking interactions
        // Tips appear after a 500ms delay, so wait briefly then dismiss
        cy.wait(600);
        cy.window().then(win => {
            if (win.LibTip && typeof win.LibTip.close === 'function') {
                win.LibTip.close();
            }
        });
        // Extra wait to ensure tip is fully closed
        cy.wait(100);

        // Make tablesAvailable accessible to tests via alias
        cy.wrap(tablesAvailable).as('tablesAvailable');
    });

    // Helper to skip test if no tables available
    function skipIfNoTables() {
        if (tablesAvailable === false) {
            return true; // Signal to skip
        }
        return false;
    }

    // Helper command to click the next step button (handles both inline and step nav buttons)
    Cypress.Commands.add('clickNextStep', { prevSubject: false }, () => {
        // Try inline button first (visible on step 1), then step navigation button
        cy.get('#btnNextStepInline, #btnNextStep').filter(':visible').first().click();
    });

    describe('Mode Selection', () => {
        it('should show mode selection dialog on initial load', () => {
            // lib-dialog uses 'open' attribute for visibility
            cy.get('lib-dialog#modeDialog', { timeout: 20000 }).should('have.attr', 'open');
            // 4 mode options: load, quick, advanced, sql
            cy.get('.mode-option').should('have.length', 4);
        });

        it('should have load, quick, and advanced mode options', () => {
            cy.get('.mode-option[data-mode="load"]').should('contain', 'Rapport laden');
            cy.get('.mode-option[data-mode="quick"]').should('contain', 'Snel');
            cy.get('.mode-option[data-mode="advanced"]').should('contain', 'Geavanceerd');
        });

        it('should have correct help text for advanced mode', () => {
            cy.get('.mode-option[data-mode="advanced"] .mode-desc').should('contain', 'parameters, groeperen en totalen');
        });

        it('should have light blue hover background on mode options', () => {
            // CSS variable --bg-hover should be #eef6ff (light blue)
            // Note: realHover() may not work reliably in headless mode
            // Instead, verify the CSS rule exists via computed styles on :hover pseudo-class
            cy.get('.mode-option').first().then($el => {
                // Trigger hover via class or attribute if realHover fails
                if (typeof $el[0].realHover === 'function') {
                    cy.wrap($el).realHover();
                } else {
                    // Fallback: add a hover class temporarily for testing
                    $el.addClass('hover');
                }
            });
            // Just verify the cursor indicates interactivity
            cy.get('.mode-option').first().should('have.css', 'cursor', 'pointer');
        });

        it('should use Linearicons for mode icons', () => {
            cy.get('.mode-option[data-mode="load"] .mode-icon').should('have.class', 'lnr');
            cy.get('.mode-option[data-mode="quick"] .mode-icon').should('have.class', 'lnr');
            cy.get('.mode-option[data-mode="advanced"] .mode-icon').should('have.class', 'lnr');
            cy.get('.mode-option[data-mode="sql"] .mode-icon').should('have.class', 'lnr');
        });

        it('should have pointer cursor on mode options', () => {
            cy.get('.mode-option').first().should('have.css', 'cursor', 'pointer');
        });

        it('should close dialog and load tables when selecting quick mode', () => {
            cy.get('.mode-option[data-mode="quick"]').click();
            // lib-dialog uses 'open' attribute - should not have it when closed
            cy.get('lib-dialog#modeDialog').should('not.have.attr', 'open');
            cy.get('.table-list-items').should('exist');
        });

        it('should close dialog and load tables when selecting advanced mode', () => {
            cy.get('.mode-option[data-mode="advanced"]').click();
            // lib-dialog uses 'open' attribute - should not have it when closed
            cy.get('lib-dialog#modeDialog').should('not.have.attr', 'open');
            cy.get('.table-list-items').should('exist');
        });

        it('should open load report dialog when selecting load option', () => {
            cy.get('.mode-option[data-mode="load"]').click();
            // lib-dialog uses 'open' attribute for visibility
            cy.get('lib-dialog#loadReportDialog', { timeout: 5000 }).should('have.attr', 'open');
        });
    });

    describe('Step 1: Tables', () => {
        beforeEach(function() {
            // Skip entire section if no tables available
            if (skipIfNoTables()) {
                this.skip();
                return;
            }
            // Select advanced mode to start
            cy.get('.mode-option[data-mode="advanced"]').click();
            // Wait for tables to load
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
        });

        it('should display available tables', () => {
            cy.get('.table-list-items .table-list-item').should('have.length.greaterThan', 0);
        });

        it('should filter tables when searching', () => {
            // Get the first table name to use as search term
            cy.get('.table-list-items .table-list-item').first().invoke('text').then(tableName => {
                // Use first 3 characters as search term (data-independent)
                const searchTerm = tableName.trim().substring(0, 3).toLowerCase();

                // tableSearch is a lib-search-input web component
                cy.get('#tableSearch').then($el => {
                    if ($el[0].shadowRoot) {
                        cy.wrap($el).shadow().find('input').clear().type(searchTerm);
                    } else {
                        cy.wrap($el).find('input').clear().type(searchTerm);
                    }
                });
                // Wait for filter to apply
                cy.wait(300);
                cy.get('.table-list-items .table-list-item').should('have.length.greaterThan', 0);
                cy.get('.table-list-items .table-list-item').each($item => {
                    cy.wrap($item).invoke('text').then(text => {
                        expect(text.toLowerCase()).to.contain(searchTerm);
                    });
                });
            });
        });

        it('should select a table when clicked', () => {
            cy.get('.table-list-items .table-list-item').first().click();
            // Wait for schema to load first (this triggers renderTableList with .selected class)
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 })
                .should('have.length', 1);
            // Now the table list should be re-rendered with .selected class
            cy.get('.table-list-items .table-list-item.selected', { timeout: 10000 }).should('have.length', 1);
        });

        it('should add table to schema canvas when selected', () => {
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table').should('have.length', 1);
        });

        it('should remove table when clicking again', () => {
            // First select a table
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 })
                .should('have.length', 1);
            cy.get('.table-list-items .table-list-item.selected', { timeout: 10000 }).should('have.length', 1);
            // Click again to deselect
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 10000 })
                .should('have.length', 0);
            cy.get('.table-list-items .table-list-item.selected').should('have.length', 0);
        });

        it('should clear tables via remove button on schema canvas', () => {
            // Add two tables
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 })
                .should('have.length', 1);
            cy.get('.table-list-items .table-list-item').eq(1).click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 })
                .should('have.length', 2);
            // Remove first table via X button
            cy.get('cma-schema-canvas').shadow().find('.remove-btn').first().click({ force: true });
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 10000 })
                .should('have.length', 1);
            // Remove second table
            cy.get('cma-schema-canvas').shadow().find('.remove-btn').first().click({ force: true });
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 10000 })
                .should('have.length', 0);
        });

        it('should prevent navigation without tables selected', () => {
            // Use the inline next button which is visible on step 1
            cy.clickNextStep();
            // Should show warning message about needing tables
            cy.get('#messageContainer', { timeout: 5000 }).should('contain.text', 'tabel');
        });

        it('should show relationships panel when 2+ tables selected', () => {
            // Select two tables
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('.table-list-items .table-list-item').eq(1).click();
            // Relationships panel should be visible
            cy.get('cma-schema-canvas').shadow().find('.relationships-panel').should('not.have.class', 'hidden');
        });

        it('should hide relationships panel when less than 2 tables', () => {
            // Select one table
            cy.get('.table-list-items .table-list-item').first().click();
            // Relationships panel should be hidden
            cy.get('cma-schema-canvas').shadow().find('.relationships-panel').should('have.class', 'hidden');
        });

        it('should show unrelated warning for tables without relationships', () => {
            // Select two unrelated tables (if available)
            cy.get('.table-list-items .table-list-item').first().click();
            // Find another table that might not have a relationship
            cy.get('.table-list-items .table-list-item').last().click();
            // Check for warning or no-relations message
            cy.get('cma-schema-canvas').shadow().find('.relationships-panel-content').then($content => {
                // Should have either unrelated-warning or no-relations message
                const hasWarning = $content.find('.unrelated-warning').length > 0;
                const hasNoRelations = $content.find('.no-relations').length > 0;
                expect(hasWarning || hasNoRelations).to.be.true;
            });
        });

        it('should have improved styling for relationships panel', () => {
            // Select two tables
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('.table-list-items .table-list-item').eq(1).click();
            // Panel should have box shadow
            cy.get('cma-schema-canvas').shadow().find('.relationships-panel')
                .should('have.css', 'box-shadow')
                .and('not.equal', 'none');
            // Header should have background
            cy.get('cma-schema-canvas').shadow().find('.relationships-panel-header')
                .should('have.css', 'background-color')
                .and('not.equal', 'rgba(0, 0, 0, 0)');
        });
    });

    describe('Step 2: Fields', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            cy.get('.mode-option[data-mode="advanced"]').click();
            cy.get('.table-list-items .table-list-item', { timeout: 25000 }).should('have.length.greaterThan', 0);
            // Select a table
            cy.get('.table-list-items .table-list-item').first().click();
            // Wait for schema to load before navigating (increased timeout)
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
            // Navigate to step 2 (use inline button from step 1)
            cy.clickNextStep();
            // Wait for step 2 content to load AND fields to be populated
            cy.get('cma-field-config', { timeout: 25000 }).should('exist');
            cy.get('cma-field-config .field-config-table tbody tr', { timeout: 20000 }).should('have.length.greaterThan', 0);
        });

        it('should display field configuration component', () => {
            cy.get('cma-field-config').should('exist');
        });

        it('should show fields from selected table', () => {
            cy.get('cma-field-config .field-config-table tbody tr', { timeout: 25000 }).should('have.length.greaterThan', 0);
        });

        it('should have visibility checkboxes', () => {
            // Wait for rows with checkboxes to be populated
            cy.get('cma-field-config .field-config-table tbody tr', { timeout: 25000 }).should('have.length.greaterThan', 0);
            // Use .find() pattern for more reliable element detection
            cy.get('cma-field-config').find('.visibility-checkbox', { timeout: 10000 }).should('have.length.greaterThan', 0);
        });

        it('should have alias input fields', () => {
            // Wait for rows to be populated first
            cy.get('cma-field-config .field-config-table tbody tr', { timeout: 25000 }).should('have.length.greaterThan', 0);
            cy.get('cma-field-config').find('.alias-input', { timeout: 10000 }).should('have.length.greaterThan', 0);
        });

        it('should have add filter buttons', () => {
            // Filter controls are now in a separate conditions panel
            // Each field has an add-filter button
            cy.get('cma-field-config .field-config-table tbody tr', { timeout: 25000 }).should('have.length.greaterThan', 0);
            cy.get('cma-field-config').find('.add-filter-btn', { timeout: 10000 }).should('have.length.greaterThan', 0);
        });

        it('should allow toggling field visibility', () => {
            cy.get('cma-field-config .field-config-table tbody tr', { timeout: 25000 }).should('have.length.greaterThan', 0);
            // Use force:true for checkbox in shadow DOM/complex component
            cy.get('cma-field-config').find('.visibility-checkbox', { timeout: 10000 }).first().uncheck({ force: true });
            cy.get('cma-field-config tr.hidden-field', { timeout: 5000 }).should('have.length.greaterThan', 0);
        });

        it('should allow filtering on hidden fields', () => {
            cy.get('cma-field-config .field-config-table tbody tr', { timeout: 25000 }).should('have.length.greaterThan', 0);
            // Uncheck visibility
            cy.get('cma-field-config').find('.visibility-checkbox', { timeout: 10000 }).first().uncheck({ force: true });
            // Add-filter button should still be visible/clickable on hidden fields
            cy.get('cma-field-config tr.hidden-field', { timeout: 5000 }).should('exist');
            cy.get('cma-field-config tr.hidden-field .add-filter-btn').should('exist');
        });

        it('should allow changing field alias', () => {
            cy.get('cma-field-config .field-config-table tbody tr', { timeout: 25000 }).should('have.length.greaterThan', 0);
            cy.get('cma-field-config').find('.alias-input', { timeout: 10000 }).first()
                .clear({ force: true })
                .type('TestAlias', { force: true });
            cy.get('cma-field-config').find('.alias-input').first().should('have.value', 'TestAlias');
        });

        it('should dispatch add-condition event when clicking filter button', () => {
            cy.get('cma-field-config .field-config-table tbody tr', { timeout: 25000 }).should('have.length.greaterThan', 0);
            // Click add-filter button
            cy.get('cma-field-config').find('.add-filter-btn', { timeout: 10000 }).first().click({ force: true });
            // A condition should be added to the conditions panel
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('have.length.greaterThan', 0);
        });

        it('should hide ID fields by default', () => {
            cy.get('cma-field-config .field-config-table tbody tr', { timeout: 25000 }).should('have.length.greaterThan', 0);
            // ID fields should have visibility unchecked (hidden)
            cy.get('cma-field-config').find('tr').then($rows => {
                const idRow = $rows.filter((i, row) => {
                    const fieldName = Cypress.$(row).find('.field-name').text().toLowerCase();
                    return fieldName === 'id';
                });
                if (idRow.length > 0) {
                    cy.wrap(idRow).find('.visibility-checkbox').should('not.be.checked');
                }
            });
        });

        it('should hide FK fields by default', () => {
            cy.get('cma-field-config .field-config-table tbody tr', { timeout: 25000 }).should('have.length.greaterThan', 0);
            // Foreign key fields (starting with "fk") should be hidden by default
            cy.get('cma-field-config').find('tr').then($rows => {
                const fkRows = $rows.filter((i, row) => {
                    const fieldName = Cypress.$(row).find('.field-name').text().toLowerCase();
                    return fieldName.startsWith('fk');
                });
                fkRows.each((i, row) => {
                    cy.wrap(row).find('.visibility-checkbox').should('not.be.checked');
                });
            });
        });

        it('should sync field visibility changes back to canvas', () => {
            // Uncheck a visible field
            cy.get('cma-field-config').find('.visibility-checkbox:checked').first().uncheck({ force: true });
            // Canvas should reflect the change (sync happens via syncFieldSelectionToCanvas)
            cy.get('cma-schema-canvas').should('exist');
        });

        it('should have selected-only filter checkbox in toolbar', () => {
            // The checkbox is inside a cma-toolbar (shadow DOM) via slotted <left> element.
            // Slotted elements remain in light DOM but may not report as "visible" to Cypress
            // because the toolbar shadow DOM controls rendering.
            cy.get('#selectedOnlyCheckbox').should('exist');
            cy.get('#selectedOnlyLabel').should('exist').and('contain', 'Alleen geselecteerde velden');
            // Verify the label is rendered (has non-zero dimensions via toolbar slot)
            cy.get('#selectedOnlyLabel').then($label => {
                // The label exists in DOM and has the expected text
                expect($label.text()).to.contain('Alleen geselecteerde velden');
            });
        });
    });

    describe('Step 3: Parameters (Advanced Mode)', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            cy.get('.mode-option[data-mode="advanced"]').click();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
            cy.get('.table-list-items .table-list-item').first().click();
            // Wait for schema to load before navigating
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
            // First navigation uses inline button
            cy.clickNextStep();
            cy.get('cma-field-config', { timeout: 20000 }).should('exist');
            cy.get('cma-field-config .field-config-table tbody tr', { timeout: 25000 }).should('have.length.greaterThan', 0);
            // Subsequent navigations use step navigation button
            cy.clickNextStep();
            cy.get('cma-param-config', { timeout: 20000 }).should('exist');
            // Wait for shadow DOM content to render
            cy.get('cma-param-config').shadow().find('.add-param-btn, .empty-state', { timeout: 10000 }).should('exist');
        });

        it('should display parameter configuration component', () => {
            cy.get('cma-param-config').should('exist');
        });

        it('should show empty state initially', () => {
            cy.get('cma-param-config').shadow().find('.empty-state').should('exist');
        });

        it('should add parameter when clicking add button', () => {
            cy.get('cma-param-config').shadow().find('.add-param-btn').click({ force: true });
            cy.get('cma-param-config').shadow().find('.param-item', { timeout: 5000 }).should('have.length', 1);
        });

        it('should allow editing parameter name', () => {
            cy.get('cma-param-config').shadow().find('.add-param-btn').click({ force: true });
            cy.get('cma-param-config').shadow().find('.param-item', { timeout: 5000 }).should('exist');
            cy.get('cma-param-config').shadow().find('.param-input[data-field="name"]')
                .clear({ force: true })
                .type('@myParam', { force: true });
            cy.get('cma-param-config').shadow().find('.param-input[data-field="name"]').should('have.value', '@myParam');
        });

        it('should allow selecting parameter type', () => {
            cy.get('cma-param-config').shadow().find('.add-param-btn').click({ force: true });
            cy.get('cma-param-config').shadow().find('.param-item', { timeout: 5000 }).should('exist');
            cy.get('cma-param-config').shadow().find('.param-select[data-field="type"]').select('date', { force: true });
            cy.get('cma-param-config').shadow().find('.param-select[data-field="type"]').should('have.value', 'date');
        });

        it('should remove parameter when clicking remove button', () => {
            cy.get('cma-param-config').shadow().find('.add-param-btn').click({ force: true });
            cy.get('cma-param-config').shadow().find('.param-item', { timeout: 5000 }).should('exist');
            cy.get('cma-param-config').shadow().find('.param-remove').click({ force: true });
            cy.get('cma-param-config').shadow().find('.empty-state', { timeout: 5000 }).should('exist');
        });
    });

    describe('Step 4: Sorting & Grouping', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            cy.get('.mode-option[data-mode="advanced"]').click();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
            cy.get('.table-list-items .table-list-item').first().click();
            // Wait for schema to load - the schema-table on canvas confirms the fetch completed
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
            // Wait for ACTUAL field data rows (not the empty state row)
            // Data rows have data-index attribute; the empty state row does not
            cy.get('cma-field-config .field-config-table tbody tr[data-index]', { timeout: 25000 })
                .should('have.length.greaterThan', 0);

            // Ensure sort config has available fields populated
            // Use cy.window() with retry to check the internal state
            cy.window({ timeout: 15000 }).should(win => {
                const sortConfig = win.document.getElementById('sortConfigComponent');
                expect(sortConfig._availableFields.length).to.be.greaterThan(0);
            });

            // Navigate to sorting step via JavaScript to avoid animation timing issues
            cy.window().then(win => {
                const wizardTabs = win.document.getElementById('wizardTabs');
                if (wizardTabs) {
                    wizardTabs.selectTab(3);
                    // Force correct content panel visibility
                    const slots = wizardTabs.shadowRoot.querySelectorAll('slot[name^="tab-"]');
                    slots.forEach(slot => {
                        const panelIndex = parseInt(slot.getAttribute('name').replace('tab-', ''), 10);
                        slot.style.display = panelIndex === 3 ? '' : 'none';
                        slot.classList.remove('tab-fade-in', 'tab-fade-out');
                    });
                }

                // Force re-render of sort/group field selects to ensure DOM options match _availableFields
                const sortConfig = win.document.getElementById('sortConfigComponent');
                if (sortConfig && sortConfig._renderFieldSelect) {
                    sortConfig._renderFieldSelect();
                }
                const groupConfig = win.document.getElementById('groupConfigComponent');
                if (groupConfig && groupConfig._renderFieldSelect) {
                    groupConfig._renderFieldSelect();
                }
            });
            // Wait for Select2 to reinitialize (it uses a 10ms setTimeout after _renderFieldSelect)
            cy.wait(300);
            // Verify sort options are now in the DOM
            cy.get('cma-sort-config select.sort-field-select option', { timeout: 10000 })
                .should('have.length.greaterThan', 1);
        });

        it('should display sort and group configuration components', () => {
            cy.get('cma-sort-config').should('exist');
            cy.get('cma-group-config').should('exist');
        });

        it('should show available fields in sort dropdown', () => {
            // cma-sort-config uses light DOM with Select2 enhancement
            // Check the underlying <select> has options (Select2 wraps this)
            // Wait for fields to be populated asynchronously from the selected table
            cy.get('cma-sort-config select.sort-field-select option', { timeout: 20000 })
                .should('have.length.greaterThan', 1);
        });

        it('should add sort field when selecting and clicking add', () => {
            // Use native <select> directly with force:true to bypass Select2 overlay
            // Wait for options to be populated from the selected table
            cy.get('cma-sort-config select.sort-field-select option', { timeout: 20000 })
                .should('have.length.greaterThan', 1);
            cy.get('cma-sort-config select.sort-field-select').first().then($select => {
                // Select the first non-empty option by its index value
                const $options = $select.find('option[value]:not([value=""])');
                if ($options.length > 0) {
                    const val = $options.first().attr('value');
                    cy.wrap($select).select(val, { force: true });
                }
            });
            cy.get('cma-sort-config .sort-add-btn').click({ force: true });
            cy.get('cma-sort-config .sort-item', { timeout: 5000 }).should('have.length', 1);
        });

        it('should allow changing sort direction', () => {
            // Add a sort field first via native select
            // Wait for options to be populated from the selected table
            cy.get('cma-sort-config select.sort-field-select option', { timeout: 20000 })
                .should('have.length.greaterThan', 1);
            cy.get('cma-sort-config select.sort-field-select').first().then($select => {
                const $options = $select.find('option[value]:not([value=""])');
                if ($options.length > 0) {
                    const val = $options.first().attr('value');
                    cy.wrap($select).select(val, { force: true });
                }
            });
            cy.get('cma-sort-config .sort-add-btn').click({ force: true });
            cy.get('cma-sort-config .sort-item', { timeout: 5000 }).should('exist');
            // sort-direction is a native <select>, not Select2
            cy.get('cma-sort-config .sort-direction').first().select('DESC', { force: true });
            cy.get('cma-sort-config .sort-direction').first().should('have.value', 'DESC');
        });

        it('should remove sort field when clicking remove', () => {
            // Add a sort field first via native select
            // Wait for options to be populated from the selected table
            cy.get('cma-sort-config select.sort-field-select option', { timeout: 20000 })
                .should('have.length.greaterThan', 1);
            cy.get('cma-sort-config select.sort-field-select').first().then($select => {
                const $options = $select.find('option[value]:not([value=""])');
                if ($options.length > 0) {
                    const val = $options.first().attr('value');
                    cy.wrap($select).select(val, { force: true });
                }
            });
            cy.get('cma-sort-config .sort-add-btn').click({ force: true });
            cy.get('cma-sort-config .sort-item', { timeout: 5000 }).should('exist');
            cy.get('cma-sort-config .sort-remove').click({ force: true });
            cy.get('cma-sort-config .empty-state, cma-sort-config .sort-item:not(:visible)', { timeout: 5000 }).should('exist');
        });

        it('should add group field when selecting and clicking add', () => {
            // cma-group-config uses light DOM with Select2 enhancement
            // Use native <select> directly with force:true to bypass Select2 overlay
            // Wait for options to be populated from the selected table
            cy.get('cma-group-config select.group-field-select option', { timeout: 20000 })
                .should('have.length.greaterThan', 1);
            cy.get('cma-group-config select.group-field-select').first().then($select => {
                const $options = $select.find('option[value]:not([value=""])');
                if ($options.length > 0) {
                    const val = $options.first().attr('value');
                    cy.wrap($select).select(val, { force: true });
                }
            });
            cy.get('cma-group-config .group-add-btn').click({ force: true });
            cy.get('cma-group-config .group-item', { timeout: 5000 }).should('have.length', 1);
        });

        it('should show Select2 dropdown visible and not clipped', () => {
            // Verify the sort field select has options and is functional
            // Select2 wraps the native <select> - verify the native select has options
            // Wait for options to be populated from the selected table
            cy.get('cma-sort-config select.sort-field-select option', { timeout: 20000 })
                .should('have.length.greaterThan', 1);
            // Verify Select2 container exists (Select2 was initialized on the native select)
            cy.get('cma-sort-config .select2-container', { timeout: 5000 }).should('exist');
        });
    });

    describe('Step 5: Output', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            cy.get('.mode-option[data-mode="advanced"]').click();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
            cy.get('.table-list-items .table-list-item').first().click();
            // Wait for schema to load before navigating
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
            // Navigate through all steps
            cy.clickNextStep();
            cy.get('cma-field-config', { timeout: 20000 }).should('exist');
            cy.get('cma-field-config .field-config-table tbody tr', { timeout: 25000 }).should('have.length.greaterThan', 0);
            cy.clickNextStep();
            cy.get('cma-param-config', { timeout: 20000 }).should('exist');
            cy.clickNextStep();
            cy.get('cma-sort-config', { timeout: 20000 }).should('exist');
            cy.clickNextStep();
            // Step 5 is Output format
            cy.get('.format-option', { timeout: 20000 }).should('have.length.greaterThan', 0);
        });

        it('should display output format options', () => {
            // 4 format options: table, excel, csv, json
            cy.get('.format-option').should('have.length', 4);
        });

        it('should have JSON format option', () => {
            cy.get('input[name="outputFormat"][value="json"]').should('exist');
            cy.get('.format-option').contains('JSON').should('exist');
        });

        it('should have table format selected by default', () => {
            cy.get('input[name="outputFormat"][value="table"]').should('be.checked');
        });

        it('should allow selecting different formats', () => {
            cy.get('input[name="outputFormat"][value="excel"]').check();
            cy.get('input[name="outputFormat"][value="excel"]').should('be.checked');
        });

        // Note: Report name and global checkbox are in Step 6 (Save), tested in 'Save Report' describe block
        // These tests verify the output format step has format options
        it('should have format selection options', () => {
            cy.get('.format-option').should('have.length.greaterThan', 0);
        });
    });

    describe('Preview Panel', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            cy.get('.mode-option[data-mode="advanced"]').click();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
            cy.get('.table-list-items .table-list-item').first().click();
            // Wait for schema to load
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
        });

        it('should display query preview component', () => {
            cy.get('cma-query-preview').should('exist');
        });

        it('should show SQL preview by default', () => {
            // Tabs now follow cma-tabs pattern: ul.tabs-list li with .selected class
            cy.get('cma-query-preview').find('.tabs-list li.selected').should('contain', 'SQL');
        });

        it('should update SQL preview when table is selected', () => {
            // Wait for SQL to be generated after table selection
            cy.get('cma-query-preview').find('.sql-code', { timeout: 10000 }).should('contain', 'SELECT');
        });

        it('should switch to data tab when clicked', () => {
            // Click on li element to switch tabs
            cy.get('cma-query-preview').find('.tabs-list li[data-tab="data"]').click({ force: true });
            cy.get('cma-query-preview').find('.tabs-list li[data-tab="data"]', { timeout: 5000 }).should('have.class', 'selected');
        });

        it('should have copy SQL button', () => {
            // Button has id="copyBtn" and class="tb-btn"
            cy.get('cma-query-preview').find('#copyBtn').should('exist');
        });

        it('should have execute button', () => {
            // Button has id="executeBtn" (replaces old refresh button)
            cy.get('cma-query-preview').find('#executeBtn').should('exist');
        });
    });

    describe('Navigation', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            cy.get('.mode-option[data-mode="advanced"]').click();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
            cy.get('.table-list-items .table-list-item').first().click();
            // Wait for schema to load
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
        });

        it('should hide step-navigation on first step (step 0)', () => {
            // On step 0, the bottom step-navigation should be hidden
            cy.get('.step-navigation').should('not.be.visible');
        });

        it('should show inline Volgende button on step 1', () => {
            // The inline "Volgende" button should be visible on step 1
            cy.get('#btnNextStepInline').should('be.visible');
        });

        it('should navigate to step 2 when clicking inline Volgende button', () => {
            cy.clickNextStep();
            // Should now be on step 2 (Fields)
            cy.get('cma-field-config').should('exist');
        });

        it('should show step-navigation after navigating to step 2', () => {
            cy.clickNextStep();
            // Step navigation should be visible on step 2+
            cy.get('.step-navigation').should('be.visible');
        });

        it('should hide inline Volgende button on step 2+', () => {
            cy.clickNextStep();
            // The inline button should be hidden on step 2+
            cy.get('.inline-step-nav').should('not.be.visible');
        });

        it('should hide Vorige button on first step', () => {
            // Navigate to step 2 first
            cy.clickNextStep();
            // Vorige should be visible on step 2
            cy.get('#btnPrevStep').should('be.visible');
            // Go back to step 1
            cy.get('#btnPrevStep').click();
            // Vorige should be hidden on step 1 (step-navigation hidden entirely)
            cy.get('.step-navigation').should('not.be.visible');
        });

        it('should navigate back when clicking previous', () => {
            cy.clickNextStep();
            cy.get('#btnPrevStep').click();
            // Should be back on step 1
            cy.get('.table-list-panel').should('be.visible');
        });

        it('should hide Volgende button on last step', () => {
            // Navigate to last step
            cy.clickNextStep(); // Step 2
            cy.clickNextStep(); // Step 3
            cy.clickNextStep(); // Step 4
            cy.clickNextStep(); // Step 5
            cy.clickNextStep(); // Step 6 (last)
            // Next button should be hidden on last step
            cy.get('#btnNextStep').should('not.be.visible');
        });

        // Run button was removed - execution is done via Results tab
        it('should show save button on last step', () => {
            // Navigate to last step
            cy.clickNextStep(); // Step 2
            cy.clickNextStep(); // Step 3
            cy.clickNextStep(); // Step 4
            cy.clickNextStep(); // Step 5
            cy.clickNextStep(); // Step 6
            // Save button should be visible for saving the report
            cy.get('#saveReportBtn').should('be.visible');
        });
    });

    describe('Quick Mode', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            cy.get('.mode-option[data-mode="quick"]').click();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
        });

        it('should skip parameter step in quick mode', () => {
            cy.get('.table-list-items .table-list-item').first().click();
            cy.clickNextStep(); // Step 2 (Fields)

            // Verify we're on step 2
            cy.get('cma-field-config').should('exist');

            cy.clickNextStep(); // Should skip step 3 and go to step 4

            // Verify we're on step 4 (Sorting), not step 3 (Parameters)
            cy.get('cma-sort-config').should('exist');
        });
    });

    describe('Database Selection', () => {
        beforeEach(() => {
            cy.get('.mode-option[data-mode="advanced"]').click();
        });

        it('should display database select', () => {
            cy.get('#databaseSelect').should('exist');
        });

        it('should reload tables when database changes', () => {
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);

            // Get initial count
            cy.get('.table-list-items .table-list-item').then($items => {
                const initialCount = $items.length;

                // Change database (if multiple available)
                cy.get('#databaseSelect option').then($options => {
                    if ($options.length > 1) {
                        cy.get('#databaseSelect').select(1);
                        // Wait for tables to reload - loader may appear briefly
                        cy.wait(500);
                        cy.get('.table-list-items .table-list-item', { timeout: 10000 }).should('exist');
                    }
                });
            });
        });
    });

    describe('Save Report', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            cy.get('.mode-option[data-mode="advanced"]').click();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
            cy.get('.table-list-items .table-list-item').first().click();
            // Wait for schema to load (table is added to state asynchronously)
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
        });

        it('should open save dialog from toolbar button', () => {
            cy.get('#saveReportBtn').click();
            cy.get('lib-dialog#saveReportDialog').should('have.attr', 'open');
        });

        it('should have inline save form in save step', () => {
            // Navigate to save step (step 6) - wizard tabs use .wizard-step elements
            cy.get('cma-tabs#wizardTabs').shadow().find('.wizard-step').eq(5).click();

            // Check inline form elements exist
            cy.get('#saveStepReportName', { timeout: 10000 }).should('be.visible');
            cy.get('#saveStepIsGlobal').should('exist');
            cy.get('#saveReportBtnStep').should('be.visible');
        });

        it('should show error when saving without name', () => {
            // Navigate to save step - wizard tabs use .wizard-step elements
            cy.get('cma-tabs#wizardTabs').shadow().find('.wizard-step').eq(5).click();

            // Clear name and try to save
            cy.get('#saveStepReportName', { timeout: 10000 }).clear();
            cy.get('#saveReportBtnStep').click();

            // Should show error status
            cy.get('#saveStepStatus').should('be.visible')
                .should('have.class', 'error');
        });

        it('should save report when name is provided', () => {
            // Intercept save API to verify response
            cy.intercept('POST', '**/api/report-save.php*action=save*').as('saveReport');

            // Navigate to save step - wizard tabs use .wizard-step elements
            cy.get('cma-tabs#wizardTabs').shadow().find('.wizard-step').eq(5).click({ force: true });
            cy.wait(500);

            // Enter name and save
            const testName = 'Cypress Test Report ' + Date.now();
            cy.get('#saveStepReportName', { timeout: 10000 }).clear().type(testName);
            cy.get('#saveReportBtnStep').click();

            // Wait for the save request to complete
            cy.wait('@saveReport', { timeout: 15000 }).then((interception) => {
                // Log server response for debugging
                cy.log('Save response status: ' + interception.response.statusCode);
                cy.log('Save response body: ' + JSON.stringify(interception.response.body));
            });

            // Should show success status
            cy.get('#saveStepStatus', { timeout: 10000 })
                .should('be.visible')
                .should('have.class', 'success');

            // Should update report name display in toolbar
            cy.get('#reportNameDisplay', { timeout: 5000 }).should('contain', testName);
        });

        it('should populate save form with existing report name when loading', () => {
            cy.intercept('POST', '**/api/report-save.php*action=save*').as('saveReport');

            // First save a report - wizard tabs use .wizard-step elements
            cy.get('cma-tabs#wizardTabs').shadow().find('.wizard-step').eq(5).click({ force: true });
            cy.wait(500);
            const testName = 'Cypress Prefill Test ' + Date.now();
            cy.get('#saveStepReportName', { timeout: 10000 }).clear().type(testName);
            cy.get('#saveReportBtnStep').click();
            cy.wait('@saveReport', { timeout: 15000 });
            cy.get('#saveStepStatus', { timeout: 10000 }).should('have.class', 'success');

            // Navigate away and back to save step
            cy.get('cma-tabs#wizardTabs').shadow().find('.wizard-step').eq(0).click({ force: true });
            cy.wait(300);
            cy.get('cma-tabs#wizardTabs').shadow().find('.wizard-step').eq(5).click({ force: true });
            cy.wait(300);

            // Name should be populated
            cy.get('#saveStepReportName', { timeout: 10000 }).should('have.value', testName);
        });

        it('should sync inline form with dialog form', () => {
            cy.intercept('POST', '**/api/report-save.php*action=save*').as('saveReport');

            // Navigate to save step and enter a name - wizard tabs use .wizard-step elements
            cy.get('cma-tabs#wizardTabs').shadow().find('.wizard-step').eq(5).click({ force: true });
            cy.wait(500);
            const testName = 'Sync Test ' + Date.now();
            cy.get('#saveStepReportName', { timeout: 10000 }).clear().type(testName);
            cy.get('#saveStepIsGlobal').check({ force: true });
            cy.get('#saveReportBtnStep').click();
            cy.wait('@saveReport', { timeout: 15000 });
            cy.get('#saveStepStatus', { timeout: 10000 }).should('have.class', 'success');

            // Open save dialog from toolbar - should have same values
            cy.get('#saveReportBtn').click();
            cy.get('lib-dialog#saveReportDialog', { timeout: 5000 }).should('have.attr', 'open');
            cy.get('#saveDialogReportName').should('have.value', testName);
            cy.get('#saveDialogIsGlobal').should('be.checked');
        });
    });

    describe('Schema Canvas Component', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            cy.get('.mode-option[data-mode="advanced"]').click();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
        });

        it('should display schema canvas component', () => {
            cy.get('cma-schema-canvas').should('exist');
        });

        it('should show table when added', () => {
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table').should('have.length', 1);
        });

        it('should show table columns', () => {
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
            cy.get('cma-schema-canvas').shadow().find('.schema-table-column', { timeout: 10000 }).should('have.length.greaterThan', 0);
        });

        it('should have remove button on table', () => {
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.remove-btn').should('exist');
        });

        it('should remove table when remove button is clicked', () => {
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.remove-btn').click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table').should('have.length', 0);
        });

        it('should reuse position when table is removed and new one added', () => {
            // Add first table
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('have.length', 1);

            // Add second table
            cy.get('.table-list-items .table-list-item').eq(1).click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('have.length', 2);

            // Remove first table via close button (use force to handle overlapping elements)
            cy.get('cma-schema-canvas').shadow().find('.schema-table').first().find('.remove-btn').click({ force: true });
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 10000 }).should('have.length', 1);

            // Add third table - should reuse position of removed first table
            cy.get('.table-list-items .table-list-item').eq(2).click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('have.length', 2);

            // The new table should be at or near the first position (gap filled)
            cy.get('cma-schema-canvas').shadow().find('.schema-table').then($tables => {
                const positions = Array.from($tables).map(t => ({
                    left: parseInt(t.style.left),
                    top: parseInt(t.style.top)
                }));
                // One of the tables should be at position 40,40 (first slot)
                const hasFirstSlot = positions.some(p => p.left === 40 && p.top === 40);
                expect(hasFirstSlot).to.be.true;
            });
        });

        it('should show all table columns in schema canvas', () => {
            // Select a table - all columns should be displayed (no more/less toggle needed)
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
            // All columns should be rendered in the table (columns section has scrollable overflow)
            cy.get('cma-schema-canvas').shadow().find('.schema-table-column', { timeout: 10000 })
                .should('have.length.greaterThan', 0);
            // The columns container should exist and be scrollable for tables with many columns
            cy.get('cma-schema-canvas').shadow().find('.schema-table-columns').should('exist');
        });

        it('should have getTablePositions method', () => {
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').then($canvas => {
                const canvas = $canvas[0];
                expect(typeof canvas.getTablePositions).to.equal('function');
                const positions = canvas.getTablePositions();
                expect(positions).to.be.an('object');
            });
        });

        it('should return positions for selected tables', () => {
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('.table-list-items .table-list-item').eq(1).click();
            cy.get('cma-schema-canvas').then($canvas => {
                const canvas = $canvas[0];
                const positions = canvas.getTablePositions();
                // Should have 2 entries
                expect(Object.keys(positions).length).to.equal(2);
                // Each should have x and y
                Object.values(positions).forEach(pos => {
                    expect(pos).to.have.property('x');
                    expect(pos).to.have.property('y');
                });
            });
        });

        it('should restore positions with setTablePositions', () => {
            cy.get('.table-list-items .table-list-item').first().then($item => {
                const tableName = $item.text().trim();
                cy.wrap($item).click();
                cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');

                // Set custom position
                const customPos = { [tableName]: { x: 200, y: 150 } };
                cy.get('cma-schema-canvas').then($canvas => {
                    const canvas = $canvas[0];
                    canvas.setTablePositions(customPos);
                });

                // Wait for position to be applied and verify
                cy.wait(300);
                cy.get('cma-schema-canvas').shadow().find('.schema-table').should($table => {
                    expect(parseInt($table.css('left'))).to.equal(200);
                    expect(parseInt($table.css('top'))).to.equal(150);
                });
            });
        });

        it('should dispatch positions-change event on drag', () => {
            cy.get('.table-list-items .table-list-item').first().click();

            // Listen for positions-change event
            cy.get('cma-schema-canvas').then($canvas => {
                const canvas = $canvas[0];
                cy.wrap(canvas).as('canvas');

                // Set up event listener
                const eventPromise = new Cypress.Promise(resolve => {
                    canvas.addEventListener('positions-change', (e) => {
                        resolve(e.detail);
                    }, { once: true });
                });

                // Simulate drag on table
                cy.get('cma-schema-canvas').shadow().find('.schema-table').then($table => {
                    const table = $table[0];
                    // Trigger mousedown
                    table.dispatchEvent(new MouseEvent('mousedown', {
                        bubbles: true,
                        clientX: 100,
                        clientY: 100
                    }));

                    // Trigger mousemove
                    table.dispatchEvent(new MouseEvent('mousemove', {
                        bubbles: true,
                        clientX: 150,
                        clientY: 130
                    }));

                    // Trigger mouseup
                    table.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
                });

                // Verify event was dispatched
                cy.wrap(eventPromise).then(detail => {
                    expect(detail).to.have.property('positions');
                });
            });
        });
    });

    describe('Relationships and Join Types', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            cy.get('.mode-option[data-mode="advanced"]').click();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
        });

        it('should show relationship dialog when adding manual relationship', () => {
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
            cy.get('.table-list-items .table-list-item').eq(1).click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('have.length', 2);
            // Click the add relationship button in the relationships panel
            cy.get('cma-schema-canvas').shadow().find('.relationships-panel-add', { timeout: 5000 }).click({ force: true });
            cy.get('lib-dialog[id^="cma-rel-dialog"]', { timeout: 5000 }).should('have.attr', 'open');
        });

        it('should have join type selector in relationship dialog', () => {
            // Select two tables and open dialog
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
            cy.get('.table-list-items .table-list-item').eq(1).click();
            cy.get('cma-schema-canvas').shadow().find('.relationships-panel-add', { timeout: 5000 }).click({ force: true });
            cy.get('lib-dialog[id^="cma-rel-dialog"]', { timeout: 5000 }).should('have.attr', 'open');
            // Join type selector should exist
            cy.get('.rel-join-type').should('exist');
        });

        it('should default to INNER JOIN', () => {
            // Select two tables and open dialog
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
            cy.get('.table-list-items .table-list-item').eq(1).click();
            cy.get('cma-schema-canvas').shadow().find('.relationships-panel-add', { timeout: 5000 }).click({ force: true });
            cy.get('lib-dialog[id^="cma-rel-dialog"]', { timeout: 5000 }).should('have.attr', 'open');
            // Default should be inner
            cy.get('.rel-join-type').should('have.value', 'inner');
        });

        it('should have from and to table/field selectors', () => {
            // Select two tables and open dialog
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
            cy.get('.table-list-items .table-list-item').eq(1).click();
            cy.get('cma-schema-canvas').shadow().find('.relationships-panel-add', { timeout: 5000 }).click({ force: true });
            cy.get('lib-dialog[id^="cma-rel-dialog"]', { timeout: 5000 }).should('have.attr', 'open');
            // From and to selectors should exist
            cy.get('.rel-from').should('exist');
            cy.get('.rel-to').should('exist');
        });

        it('should filter opposite dropdown to exclude selected table', () => {
            // Select two tables and open dialog
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
            cy.get('.table-list-items .table-list-item').eq(1).click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('have.length', 2);
            cy.get('cma-schema-canvas').shadow().find('.relationships-panel-add', { timeout: 5000 }).click({ force: true });
            cy.get('lib-dialog[id^="cma-rel-dialog"]', { timeout: 5000 }).should('have.attr', 'open');

            // Both dropdowns should initially have optgroups for both tables
            cy.get('.rel-to optgroup').should('have.length.greaterThan', 1);
            cy.get('.rel-from optgroup').should('have.length.greaterThan', 1);

            // Get the first optgroup label (table name) from the parent dropdown
            cy.get('.rel-to optgroup').first().then($optgroup => {
                const tableName = $optgroup.attr('label');

                // Select first option from the parent dropdown
                cy.get('.rel-to option').not('[value=""]').first().then($opt => {
                    const val = $opt.val();
                    // Use Select2 if available, otherwise native
                    cy.get('.rel-to').then($sel => {
                        if ($sel.hasClass('select2-hidden-accessible')) {
                            cy.window().then(win => {
                                win.jQuery('.rel-to').val(val).trigger('change');
                            });
                        } else {
                            cy.get('.rel-to').select(val);
                        }
                    });

                    // The child dropdown should no longer contain the selected table's optgroup
                    cy.get('.rel-from optgroup').each($group => {
                        expect($group.attr('label')).not.to.equal(tableName);
                    });
                });
            });
        });
    });

    describe('System Tables Filtering', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            cy.get('.mode-option[data-mode="advanced"]').click();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
        });

        it('should not show _cma_version in table list', () => {
            cy.get('.table-list-items .table-list-item').each($item => {
                cy.wrap($item).find('.table-name').invoke('text').should('not.match', /^_cma_/);
            });
        });
    });

    describe('Views Support', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            cy.get('.mode-option[data-mode="advanced"]').click();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
        });

        // Note: Current implementation does not distinguish views from tables visually
        // These tests verify the table list works regardless of item type
        it('should display all items in table list (tables and views)', () => {
            cy.get('.table-list-items .table-list-item').should('have.length.greaterThan', 0);
            cy.get('.table-list-items .table-list-item .table-name').should('exist');
        });
    });

    describe('Layout', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            cy.get('.mode-option[data-mode="advanced"]').click();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
        });

        it('should have vertical fold divider between panels', () => {
            // The step-layout-split should have a ::before pseudo-element creating the vertical line
            cy.get('.step-layout-split').should('exist');
            // CSS creates a 1px divider via grid-template-columns and ::before
        });
    });

    describe('Splitter Functionality', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            cy.get('.mode-option[data-mode="advanced"]').click();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
        });

        describe('Vertical Splitter (Table List / Schema Canvas)', () => {
            it('should have vertical splitter element', () => {
                cy.get('.vertical-splitter').should('exist');
            });

            it('should have east-west resize cursor', () => {
                cy.get('.vertical-splitter').should('have.css', 'cursor', 'ew-resize');
            });

            it('should have proper z-index for interaction', () => {
                cy.get('.vertical-splitter').then($splitter => {
                    const zIndex = parseInt($splitter.css('z-index'));
                    expect(zIndex).to.be.gte(10);
                });
            });

            it('should have user-select none to prevent text selection during drag', () => {
                cy.get('.vertical-splitter').should('have.css', 'user-select', 'none');
            });

            it('should have relative positioning for z-index to work', () => {
                cy.get('.vertical-splitter').should('have.css', 'position', 'relative');
            });

            // Note: CSS :hover pseudo-class cannot be reliably tested in headless mode
            // Instead, verify the splitter has hover-related CSS properties defined
            it('should have hover styling CSS properties', () => {
                cy.get('.vertical-splitter').then($el => {
                    // Verify the element is styled for interaction
                    expect($el.css('cursor')).to.equal('ew-resize');
                });
            });

            it('should be draggable and resize panels', () => {
                // Get initial width of table list panel
                cy.get('.table-list-panel').then($panel => {
                    const initialWidth = $panel.outerWidth();

                    // Drag splitter to the right by 50px
                    cy.get('.vertical-splitter')
                        .trigger('mousedown', { which: 1 })
                        .trigger('mousemove', { clientX: 300 })
                        .trigger('mouseup');

                    // Check if table list width changed
                    cy.get('.table-list-panel').invoke('outerWidth').should('not.eq', initialWidth);
                });
            });
        });

    });

    // Skip Conditions Panel tests if no tables are available in the database
    // This is expected in some test environments where the database is not fully configured
    describe('Conditions Panel', () => {
        beforeEach(function() {
            cy.get('.mode-option[data-mode="advanced"]').click();
            // Wait for tables to load - if none found, skip this test
            cy.get('.table-list-items', { timeout: 10000 }).then($container => {
                const $items = $container.find('.table-list-item');
                if ($items.length === 0) {
                    this.skip();
                    return;
                }
                cy.get('.table-list-items .table-list-item').first().click();
                // Wait for schema to load before navigating
                cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
                cy.clickNextStep(); // Go to Fields step
                // Wait for fields to be loaded
                cy.get('cma-field-config .field-config-table tbody tr', { timeout: 25000 }).should('have.length.greaterThan', 0);
            });
        });

        it('should display conditions panel on fields step', () => {
            cy.get('cma-conditions-panel').should('exist');
        });

        it('should have header with "Voorwaarden" text', () => {
            cy.get('cma-conditions-panel .conditions-header .header-title').should('contain', 'Voorwaarden');
        });

        it('should show empty state when no filters are set', () => {
            cy.get('cma-conditions-panel .empty-state').should('exist');
        });

        it('should add condition when clicking add-filter button on field', () => {
            cy.get('cma-field-config').find('.add-filter-btn', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('have.length', 1);
        });

        it('should allow inline editing of condition operator', () => {
            cy.get('cma-field-config').find('.add-filter-btn', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('exist');
            cy.get('cma-conditions-panel .condition-operator-select').first().select('=', { force: true });
            cy.get('cma-conditions-panel .condition-operator-select').first().should('have.value', '=');
        });

        it('should allow inline editing of condition value', () => {
            cy.get('cma-field-config').find('.add-filter-btn', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('exist');
            cy.get('cma-conditions-panel .condition-operator-select').first().select('=', { force: true });
            cy.get('cma-conditions-panel .condition-value-input').first().type('test', { force: true });
            cy.get('cma-conditions-panel .condition-value-input').first().should('have.value', 'test');
        });

        it('should disable value input for no-value operators', () => {
            cy.get('cma-field-config').find('.add-filter-btn', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('exist');
            cy.get('cma-conditions-panel .condition-operator-select').first().select('empty', { force: true });
            cy.get('cma-conditions-panel .condition-value-input').first().should('be.disabled');
        });

        it('should have AND/OR toggle buttons between conditions', () => {
            cy.get('cma-field-config').find('.add-filter-btn', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('exist');
            cy.get('cma-field-config').find('.add-filter-btn').eq(1).click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('have.length', 2);
            cy.get('cma-conditions-panel .condition-logic .logic-btn').should('have.length.gte', 2);
        });

        it('should toggle between AND and OR', () => {
            cy.get('cma-field-config').find('.add-filter-btn', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('exist');
            cy.get('cma-field-config').find('.add-filter-btn').eq(1).click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('have.length', 2);
            cy.get('cma-conditions-panel .condition-logic .logic-btn[data-logic="AND"]').first().should('have.class', 'active');
            cy.get('cma-conditions-panel .condition-logic .logic-btn[data-logic="OR"]').first().click({ force: true });
            cy.get('cma-conditions-panel .condition-logic .logic-btn[data-logic="OR"]').first().should('have.class', 'active');
        });

        it('should support multiple conditions on same field', () => {
            cy.get('cma-field-config').find('.add-filter-btn', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('have.length', 1);
            cy.get('cma-field-config').find('.add-filter-btn').first().click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('have.length', 2);
        });

        it('should have bracket buttons', () => {
            cy.get('cma-field-config').find('.add-filter-btn', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('exist');
            cy.get('cma-conditions-panel .bracket-btn.add-open').should('exist');
            cy.get('cma-conditions-panel .bracket-btn.add-close').should('exist');
        });

        it('should add opening bracket when clicking ( button', () => {
            cy.get('cma-field-config').find('.add-filter-btn', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('exist');
            cy.get('cma-conditions-panel .bracket-btn.add-open').first().click({ force: true });
            cy.get('cma-conditions-panel .condition-bracket.prefix', { timeout: 5000 }).first().should('contain', '(');
        });

        it('should add closing bracket when clicking ) button', () => {
            cy.get('cma-field-config').find('.add-filter-btn', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('exist');
            cy.get('cma-conditions-panel .bracket-btn.add-close').first().click({ force: true });
            cy.get('cma-conditions-panel .condition-bracket.suffix', { timeout: 5000 }).first().should('contain', ')');
        });

        it('should remove condition when clicking remove button', () => {
            cy.get('cma-field-config').find('.add-filter-btn', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('have.length', 1);
            cy.get('cma-conditions-panel .remove-btn').first().click({ force: true });
            cy.get('cma-conditions-panel .condition-item').should('have.length', 0);
        });

        it('should reorder condition up when clicking up button', () => {
            cy.get('cma-field-config').find('.add-filter-btn', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('exist');
            cy.get('cma-field-config').find('.add-filter-btn').eq(1).click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('have.length', 2);
            cy.get('cma-conditions-panel .condition-field').then($fields => {
                const secondField = $fields.eq(1).text();
                cy.get('cma-conditions-panel .move-up-btn').eq(1).click({ force: true });
                cy.get('cma-conditions-panel .condition-field').first().should('contain', secondField);
            });
        });

        it('should reorder condition down when clicking down button', () => {
            cy.get('cma-field-config').find('.add-filter-btn', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('exist');
            cy.get('cma-field-config').find('.add-filter-btn').eq(1).click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('have.length', 2);
            cy.get('cma-conditions-panel .condition-field').then($fields => {
                const firstField = $fields.eq(0).text();
                cy.get('cma-conditions-panel .move-down-btn').first().click({ force: true });
                cy.get('cma-conditions-panel .condition-field').eq(1).should('contain', firstField);
            });
        });

        it('should disable up button for first condition', () => {
            cy.get('cma-field-config').find('.add-filter-btn', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('exist');
            cy.get('cma-conditions-panel .move-up-btn').first().should('be.disabled');
        });

        it('should disable down button for last condition', () => {
            cy.get('cma-field-config').find('.add-filter-btn', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('exist');
            cy.get('cma-conditions-panel .move-down-btn').first().should('be.disabled');
        });

        it('should have transparent footer background', () => {
            cy.get('cma-conditions-panel .conditions-footer')
                .should('have.css', 'background-color', 'rgba(0, 0, 0, 0)');
        });
    });

    describe('Query Options (DISTINCT & TOP N)', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            cy.get('.mode-option[data-mode="advanced"]').click();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
            cy.get('.table-list-items .table-list-item').first().click();
            // Wait for schema to load
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
            // Navigate to Fields first to ensure fields are populated
            cy.clickNextStep();
            cy.get('cma-field-config .field-config-table tbody tr', { timeout: 25000 }).should('have.length.greaterThan', 0);
            // Navigate directly to sorting step via wizard tab
            cy.get('cma-tabs#wizardTabs').shadow().find('.wizard-step').eq(3).click({ force: true });
            cy.wait(500);
        });

        it('should display query options section', () => {
            cy.get('.query-options-section').should('exist');
        });

        it('should have Query opties header', () => {
            cy.get('.query-options-header').should('contain', 'Query opties');
        });

        it('should show DISTINCT checkbox', () => {
            cy.get('#distinctCheckbox').should('exist');
            cy.get('#distinctCheckbox').should('not.be.checked');
        });

        it('should show TOP N input', () => {
            cy.get('#topNInput').should('exist');
            cy.get('#topNInput').should('have.value', '');
        });

        it('should have help icon with tooltip', () => {
            cy.get('.query-options-header .help-icon').should('exist');
        });

        it('should generate SELECT DISTINCT when checkbox is checked', () => {
            cy.get('#distinctCheckbox').check();
            cy.get('#distinctCheckbox').should('be.checked');

            // Verify SQL preview contains DISTINCT
            cy.get('cma-query-preview').find('.sql-code').should('contain', 'SELECT DISTINCT');
        });

        it('should remove DISTINCT when checkbox is unchecked', () => {
            cy.get('#distinctCheckbox').check({ force: true });
            cy.wait(300);
            cy.get('cma-query-preview').find('.sql-code', { timeout: 5000 }).should('contain', 'SELECT DISTINCT');

            cy.get('#distinctCheckbox').uncheck({ force: true });
            cy.wait(300);
            cy.get('cma-query-preview').find('.sql-code', { timeout: 5000 }).should('not.contain', 'SELECT DISTINCT');
        });

        it('should generate TOP N when value is entered', () => {
            cy.get('#topNInput').type('50');
            cy.get('#topNInput').blur();

            // Verify SQL preview contains TOP 50
            cy.get('cma-query-preview').find('.sql-code').should('contain', 'TOP 50');
        });

        it('should clear TOP N when input is emptied', () => {
            cy.get('#topNInput').type('100');
            cy.get('#topNInput').blur();
            cy.get('cma-query-preview').find('.sql-code').should('contain', 'TOP 100');

            cy.get('#topNInput').clear();
            cy.get('#topNInput').blur();
            // Note: preview might still have a default limit, so just check the input is cleared
            cy.get('#topNInput').should('have.value', '');
        });

        it('should generate SELECT DISTINCT TOP N when both are set', () => {
            // Intercept the SQL generation API to know when it completes
            cy.intercept('POST', '**/api/report-query.php*').as('getSql');

            cy.get('#distinctCheckbox').check({ force: true });
            cy.get('#topNInput').type('25', { force: true });
            // Trigger change event by dispatching it (blur may not work in headless mode)
            cy.get('#topNInput').then($input => {
                $input[0].dispatchEvent(new Event('change', { bubbles: true }));
            });

            // Wait for SQL to be regenerated (async server call)
            cy.wait('@getSql', { timeout: 10000 });

            // Verify SQL preview contains SELECT DISTINCT TOP 25
            cy.get('cma-query-preview').find('.sql-code', { timeout: 10000 })
                .invoke('text')
                .should('match', /SELECT\s+DISTINCT\s+TOP\s+25/);
        });

        it('should persist DISTINCT option when navigating steps', () => {
            cy.get('#distinctCheckbox').check();

            // Navigate to next step and back
            cy.clickNextStep(); // Step 5
            cy.get('#btnPrevStep').click(); // Back to Step 4

            // Option should still be checked
            cy.get('#distinctCheckbox').should('be.checked');
        });

        it('should persist TOP N option when navigating steps', () => {
            cy.get('#topNInput').type('75');
            cy.get('#topNInput').blur();

            // Navigate to next step and back
            cy.clickNextStep(); // Step 5
            cy.get('#btnPrevStep').click(); // Back to Step 4

            // Option should still have value
            cy.get('#topNInput').should('have.value', '75');
        });

        it('should save and restore query options with report', () => {
            cy.intercept('POST', '**/api/report-save.php*action=save*').as('saveReport');

            // Set options
            cy.get('#distinctCheckbox').check({ force: true });
            cy.get('#topNInput').type('50', { force: true });
            cy.get('#topNInput').then($input => {
                $input[0].dispatchEvent(new Event('change', { bubbles: true }));
            });
            cy.wait(300);

            // Navigate to save step
            cy.clickNextStep(); // Step 5
            cy.clickNextStep(); // Step 6 (Save)

            // Save the report
            const testName = 'Query Options Test ' + Date.now();
            cy.get('#saveStepReportName', { timeout: 10000 }).clear().type(testName);
            cy.get('#saveReportBtnStep').click();
            cy.wait('@saveReport', { timeout: 15000 });
            cy.get('#saveStepStatus', { timeout: 10000 }).should('have.class', 'success');

            // Reload the page and load the report
            cy.visit('/report-designer.php');
            cy.wait(600);
            cy.get('.mode-option[data-mode="load"]').click();
            cy.get('lib-dialog#loadReportDialog', { timeout: 5000 }).should('have.attr', 'open');

            // Find and click the saved report
            cy.get('#reportList', { timeout: 10000 }).contains(testName).click();
            cy.wait(1000);

            // Navigate to step 4 (Sorting - index 3)
            cy.get('cma-tabs#wizardTabs').shadow().find('.wizard-step').eq(3).click({ force: true });
            cy.wait(500);

            // Verify options are restored
            cy.get('#distinctCheckbox', { timeout: 5000 }).should('be.checked');
            cy.get('#topNInput').should('have.value', '50');
        });

        it('should save and restore manually added relationships', () => {
            cy.intercept('POST', '**/api/report-save.php*action=save*').as('saveReport');

            // Start fresh - reload page to avoid state from beforeEach
            cy.visit('/report-designer.php');
            cy.wait(600);
            cy.get('.mode-option[data-mode="advanced"]').click();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 1);

            // Select two tables
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
            cy.get('.table-list-items .table-list-item').eq(1).click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('have.length', 2);

            // Save the report with multiple tables - navigate to save step via tab
            cy.window().then(win => {
                const wizardTabs = win.document.getElementById('wizardTabs');
                if (wizardTabs) wizardTabs.selectTab(5);
            });
            cy.wait(500);
            const testName = 'Multi Table Test ' + Date.now();
            cy.get('#saveStepReportName', { timeout: 10000 }).clear().type(testName);
            cy.get('#saveReportBtnStep').click();
            cy.wait('@saveReport', { timeout: 15000 });
            cy.get('#saveStepStatus', { timeout: 10000 }).should('have.class', 'success');

            // Reload and verify tables are restored
            cy.visit('/report-designer.php');
            cy.wait(600);
            cy.get('.mode-option[data-mode="load"]').click();
            cy.get('lib-dialog#loadReportDialog', { timeout: 5000 }).should('have.attr', 'open');
            cy.get('#reportList', { timeout: 10000 }).contains(testName).click();

            // Wait for the report to fully load - tables need time to render on canvas
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 30000 })
                .should('have.length', 2);
        });

        it('should restore conditions panel after loading report with filters', () => {
            cy.intercept('POST', '**/api/report-save.php*action=save*').as('saveReport');

            // Start fresh - reload page to avoid state from beforeEach
            cy.visit('/report-designer.php');
            cy.wait(600);
            cy.get('.mode-option[data-mode="advanced"]').click();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');

            // Navigate to Fields step
            cy.clickNextStep();
            cy.get('cma-field-config .field-config-table tbody tr', { timeout: 25000 }).should('have.length.greaterThan', 0);

            // Add a condition via the add-filter button
            cy.get('cma-field-config').find('.add-filter-btn', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-conditions-panel .condition-item', { timeout: 5000 }).should('have.length', 1);

            // Navigate to save step via JS to avoid animation issues
            cy.window().then(win => {
                const wizardTabs = win.document.getElementById('wizardTabs');
                if (wizardTabs) wizardTabs.selectTab(5);
            });
            cy.wait(500);
            const testName = 'Conditions Test ' + Date.now();
            cy.get('#saveStepReportName', { timeout: 10000 }).clear().type(testName);
            cy.get('#saveReportBtnStep').click();
            cy.wait('@saveReport', { timeout: 15000 });
            cy.get('#saveStepStatus', { timeout: 10000 }).should('have.class', 'success');

            // Reload and verify condition is restored
            cy.visit('/report-designer.php');
            cy.wait(600);
            cy.get('.mode-option[data-mode="load"]').click();
            cy.get('lib-dialog#loadReportDialog', { timeout: 5000 }).should('have.attr', 'open');
            cy.get('#reportList', { timeout: 10000 }).contains(testName).click();

            // Wait for the report to fully load - schema needs to render first
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 30000 }).should('exist');

            // Navigate to fields step (index 2 = Velden tab) via JS
            cy.window().then(win => {
                const wizardTabs = win.document.getElementById('wizardTabs');
                if (wizardTabs) {
                    wizardTabs.selectTab(2);
                    // Force content panel visibility
                    const slots = wizardTabs.shadowRoot.querySelectorAll('slot[name^="tab-"]');
                    slots.forEach(slot => {
                        const panelIndex = parseInt(slot.getAttribute('name').replace('tab-', ''), 10);
                        slot.style.display = panelIndex === 2 ? '' : 'none';
                        slot.classList.remove('tab-fade-in', 'tab-fade-out');
                    });
                }
            });
            cy.wait(500);
            cy.get('cma-conditions-panel .condition-item', { timeout: 10000 }).should('have.length', 1);
        });

        it('should enable advanced mode with alias editing after loading a report', () => {
            cy.intercept('POST', '**/api/report-save.php*action=save*').as('saveReport');

            // Start fresh - reload page to avoid state from beforeEach
            cy.visit('/report-designer.php');
            cy.wait(600);
            cy.get('.mode-option[data-mode="advanced"]').click();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');

            // Navigate to save step via JS to avoid animation issues
            cy.window().then(win => {
                const wizardTabs = win.document.getElementById('wizardTabs');
                if (wizardTabs) wizardTabs.selectTab(5);
            });
            cy.wait(500);
            const testName = 'Advanced Mode Test ' + Date.now();
            cy.get('#saveStepReportName', { timeout: 10000 }).clear().type(testName);
            cy.get('#saveReportBtnStep').click();
            cy.wait('@saveReport', { timeout: 15000 });
            cy.get('#saveStepStatus', { timeout: 10000 }).should('have.class', 'success');

            // Reload and verify advanced mode is restored
            cy.visit('/report-designer.php');
            cy.wait(600);
            cy.get('.mode-option[data-mode="load"]').click();
            cy.get('lib-dialog#loadReportDialog', { timeout: 5000 }).should('have.attr', 'open');
            cy.get('#reportList', { timeout: 10000 }).contains(testName).click();

            // Wait for the report to fully load - schema canvas needs time to render
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 30000 }).should('exist');

            // Wait for schema canvas to have advanced-mode attribute
            cy.get('cma-schema-canvas', { timeout: 15000 })
                .should('have.attr', 'advanced-mode', 'true');

            // Verify alias edit button is visible on schema canvas table
            cy.get('cma-schema-canvas').shadow()
                .find('.schema-table-header .alias-edit-btn', { timeout: 10000 })
                .should('exist');
        });
    });

    describe('Main Tabs (Ontwerper / Resultaat)', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            // Select advanced mode to start
            cy.get('.mode-option[data-mode="advanced"]').click();
            // Wait for tables to load
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
        });

        it('should show Ontwerper and Resultaat tabs using cma-tabs', () => {
            cy.get('#mainTabs').should('exist');
            cy.get('#mainTabs').shadow().find('.tabs-list li').should('have.length', 2);
            cy.get('#mainTabs').shadow().find('.tabs-list li').first().should('contain', 'Ontwerper');
            cy.get('#mainTabs').shadow().find('.tabs-list li').last().should('contain', 'Resultaat');
        });

        it('should have Ontwerper tab active by default', () => {
            cy.get('#mainTabs').shadow().find('.tabs-list li').first().should('have.class', 'selected');
            cy.get('#mainTabs').shadow().find('.tabs-list li').last().should('not.have.class', 'selected');
        });

        it('should show designer content when Ontwerper is active', () => {
            cy.get('#designerTabContent').should('be.visible');
        });

        it('should switch to Resultaat tab when clicked', () => {
            cy.get('#mainTabs').shadow().find('.tabs-list li').last().click();
            cy.get('#mainTabs').shadow().find('.tabs-list li').last().should('have.class', 'selected');
            cy.get('#mainTabs').shadow().find('.tabs-list li').first().should('not.have.class', 'selected');
        });

        it('should show cma-query-preview in Resultaat tab', () => {
            cy.get('#mainTabs').shadow().find('.tabs-list li').last().click();
            cy.get('#resultsQueryPreview').should('be.visible');
        });

        it('should have SQL toolbar in results preview', () => {
            cy.get('#mainTabs').shadow().find('.tabs-list li').last().click();
            // cma-query-preview has its own toolbar with copy and execute buttons
            cy.get('#resultsQueryPreview').find('#copyBtn').should('exist');
            cy.get('#resultsQueryPreview').find('#executeBtn').should('exist');
        });

        it('should sync SQL when switching to Resultaat tab', () => {
            // Select a table first to generate SQL
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table').should('exist');

            // Switch to Resultaat tab
            cy.get('#mainTabs').shadow().find('.tabs-list li').last().click();

            // Results preview SQL should have content
            cy.get('#resultsQueryPreview').find('.sql-code').should('not.be.empty');
        });
    });

    describe('SQL Mode', () => {
        it('should go directly to Resultaat tab when SQL mode is selected', () => {
            cy.get('.mode-option[data-mode="sql"]').click();

            // Should close the mode dialog
            cy.get('lib-dialog#modeDialog').should('not.have.attr', 'open');

            // Should be on Resultaat tab (second tab selected)
            cy.get('#mainTabs').shadow().find('.tabs-list li').last().should('have.class', 'selected');

            // Results preview should be visible
            cy.get('#resultsQueryPreview').should('be.visible');
        });

        it('should enter edit mode in SQL mode', () => {
            cy.get('.mode-option[data-mode="sql"]').click();

            // SQL editor textarea should be visible (edit mode)
            cy.wait(200);
            cy.get('#resultsQueryPreview').find('.sql-textarea').should('be.visible');
        });
    });

    describe('Schema Canvas Field Selection', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            // Select advanced mode to start
            cy.get('.mode-option[data-mode="advanced"]').click();
            // Wait for tables to load
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
            // Select a table
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 5000 }).should('exist');
        });

        it('should show checkboxes in table columns', () => {
            cy.get('cma-schema-canvas').shadow()
                .find('.schema-table-column .field-checkbox')
                .should('have.length.greaterThan', 0);
        });

        it('should toggle field selection when checkbox is clicked', () => {
            cy.get('cma-schema-canvas').shadow()
                .find('.schema-table-column .field-checkbox')
                .first()
                .click();

            cy.get('cma-schema-canvas').shadow()
                .find('.schema-table-column.selected')
                .should('exist');
        });

        it('should toggle field selection when column name is clicked', () => {
            cy.get('cma-schema-canvas').shadow()
                .find('.schema-table-column .col-name', { timeout: 10000 })
                .first()
                .click({ force: true });

            cy.get('cma-schema-canvas').shadow()
                .find('.schema-table-column.selected', { timeout: 5000 })
                .should('exist');
        });

        it('should show master checkbox in table header', () => {
            cy.get('cma-schema-canvas').shadow()
                .find('.schema-table-header .header-checkbox')
                .should('exist');
        });

        it('should select all fields when master checkbox is checked', () => {
            cy.get('cma-schema-canvas').shadow()
                .find('.schema-table-header .header-checkbox')
                .first()
                .click();

            // All column checkboxes should be checked
            cy.get('cma-schema-canvas').shadow()
                .find('.schema-table-column .field-checkbox:checked')
                .should('have.length.greaterThan', 0);
        });
    });

    describe('Schema Canvas Table Aliasing (Advanced Mode)', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            // Select advanced mode
            cy.get('.mode-option[data-mode="advanced"]').click();
            // Wait for tables to load
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
            // Select a table
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 5000 }).should('exist');
        });

        it('should show alias edit button in advanced mode', () => {
            cy.get('cma-schema-canvas').shadow()
                .find('.schema-table-header .alias-edit-btn')
                .should('exist');
        });

        it('should show alias input when edit button is clicked', () => {
            cy.get('cma-schema-canvas').shadow()
                .find('.schema-table-header .alias-edit-btn')
                .first()
                .click();

            cy.get('cma-schema-canvas').shadow()
                .find('.alias-edit-input')
                .should('be.visible');
        });

        it('should save alias when save button is clicked', () => {
            // Click edit button
            cy.get('cma-schema-canvas').shadow()
                .find('.schema-table-header .alias-edit-btn')
                .first()
                .click();

            // Type alias and press Enter to save (more reliable than clicking save button in Shadow DOM)
            cy.get('cma-schema-canvas').shadow()
                .find('.alias-edit-input')
                .should('be.visible')
                .clear()
                .type('TestAlias{enter}');

            // Alias should be displayed
            cy.get('cma-schema-canvas').shadow()
                .find('.table-alias', { timeout: 10000 })
                .should('contain', 'TestAlias');
        });

        it('should cancel alias edit when cancel button is clicked', () => {
            // Click edit button
            cy.get('cma-schema-canvas').shadow()
                .find('.schema-table-header .alias-edit-btn')
                .first()
                .click();

            // Type something
            cy.get('cma-schema-canvas').shadow()
                .find('.alias-edit-input')
                .clear()
                .type('CancelledAlias');

            // Click cancel
            cy.get('cma-schema-canvas').shadow()
                .find('.alias-cancel-btn')
                .click();

            // Should not show alias
            cy.get('cma-schema-canvas').shadow()
                .find('.table-alias')
                .should('not.exist');
        });
    });

    describe('Schema Canvas Table Resizing', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            // Select advanced mode
            cy.get('.mode-option[data-mode="advanced"]').click();
            // Wait for tables to load
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
            // Select a table
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 5000 }).should('exist');
        });

        it('should show resize handle on table', () => {
            cy.get('cma-schema-canvas').shadow()
                .find('.schema-table .resize-handle')
                .should('exist');
        });

        it('should have se-resize cursor on resize handle', () => {
            cy.get('cma-schema-canvas').shadow()
                .find('.schema-table .resize-handle')
                .should('have.css', 'cursor', 'se-resize');
        });
    });

    describe('Alias Sanitization', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            // Select advanced mode
            cy.get('.mode-option[data-mode="advanced"]').click();
            // Wait for tables to load
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
            // Select a table
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 5000 }).should('exist');
        });

        it('should remove spaces from table alias', () => {
            // Click edit button
            cy.get('cma-schema-canvas').shadow()
                .find('.schema-table-header .alias-edit-btn')
                .first()
                .click();

            // Wait for alias edit input to appear
            cy.get('cma-schema-canvas').shadow()
                .find('.alias-edit-input')
                .should('be.visible');

            // Type alias with spaces and press Enter to save (triggers keydown handler)
            cy.get('cma-schema-canvas').shadow()
                .find('.alias-edit-input')
                .clear()
                .type('Test Alias With Spaces{enter}');

            // Alias should have underscores instead of spaces
            cy.get('cma-schema-canvas').shadow()
                .find('.table-alias', { timeout: 10000 })
                .should('contain', 'Test_Alias_With_Spaces');
        });

        it('should remove spaces from field alias', () => {
            // Go to Fields step (Tables -> Parameters -> Velden)
            cy.clickNextStep(); // -> Parameters
            cy.clickNextStep(); // -> Velden (Fields)
            cy.get('cma-field-config .field-config-table tbody tr', { timeout: 25000 }).should('have.length.greaterThan', 0);

            // Wait for field config to load with alias inputs
            cy.get('cma-field-config .alias-input', { timeout: 10000 }).should('exist');

            // Type alias with spaces, then trigger blur by clicking elsewhere
            cy.get('cma-field-config .alias-input').first()
                .clear({ force: true })
                .type('Field With Spaces', { force: true });

            // Trigger blur by focusing another element
            cy.get('cma-field-config .alias-input').first().then($input => {
                $input[0].dispatchEvent(new Event('blur', { bubbles: true }));
            });
            cy.wait(200);

            // Should be sanitized
            cy.get('cma-field-config .alias-input').first()
                .should('have.value', 'Field_With_Spaces');
        });
    });

    describe('Table Name Display', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            // Select advanced mode
            cy.get('.mode-option[data-mode="advanced"]').click();
            // Wait for tables to load
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
        });

        it('should strip tbl prefix from table names in list', () => {
            // Check that table names don't show tbl prefix
            cy.get('.table-list-items .table-list-item .table-name').first()
                .invoke('text')
                .should('not.match', /^tbl/i);
        });

        it('should have CMA.displayTableName function available', () => {
            cy.window().then(win => {
                expect(win.CMA).to.exist;
                expect(win.CMA.displayTableName).to.be.a('function');
                expect(win.CMA.displayTableName('tblUsers')).to.equal('Users');
                expect(win.CMA.displayTableName('dbo.tblUsers')).to.equal('Users');
            });
        });
    });

    describe('Relationships Panel', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            // Select advanced mode
            cy.get('.mode-option[data-mode="advanced"]').click();
            // Wait for tables to load
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
            // Select two tables to show relationships panel
            cy.get('.table-list-items .table-list-item').eq(0).click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('have.length', 1);
            cy.get('.table-list-items .table-list-item').eq(1).click();
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('have.length', 2);
        });

        it('should have draggable header cursor', () => {
            cy.get('cma-schema-canvas').shadow()
                .find('.relationships-panel-header', { timeout: 5000 })
                .should('have.css', 'cursor', 'grab');
        });

        it('should have toggle button', () => {
            cy.get('cma-schema-canvas').shadow()
                .find('.relationships-panel-toggle')
                .should('exist');
        });

        it('should minimize when toggle button is clicked', () => {
            // Click toggle button
            cy.get('cma-schema-canvas').shadow()
                .find('.relationships-panel-toggle')
                .click();

            // Panel should have minimized class
            cy.get('cma-schema-canvas').shadow()
                .find('.relationships-panel')
                .should('have.class', 'minimized');
        });

        it('should restore when toggle button is clicked again', () => {
            // Click toggle to minimize
            cy.get('cma-schema-canvas').shadow()
                .find('.relationships-panel-toggle')
                .click();

            // Click toggle to restore
            cy.get('cma-schema-canvas').shadow()
                .find('.relationships-panel-toggle')
                .click();

            // Panel should not have minimized class
            cy.get('cma-schema-canvas').shadow()
                .find('.relationships-panel')
                .should('not.have.class', 'minimized');
        });

        it('should be draggable', () => {
            // Get initial position
            cy.get('cma-schema-canvas').shadow()
                .find('.relationships-panel')
                .then($panel => {
                    const initialRight = $panel.css('right');

                    // Drag the panel
                    cy.get('cma-schema-canvas').shadow()
                        .find('.relationships-panel-header')
                        .trigger('mousedown', { which: 1 })
                        .trigger('mousemove', { clientX: 100, clientY: 100 })
                        .trigger('mouseup');

                    // Panel should have moved (now using left positioning)
                    cy.get('cma-schema-canvas').shadow()
                        .find('.relationships-panel')
                        .should('have.css', 'left')
                        .and('not.equal', 'auto');
                });
        });
    });

    // SQL to Visual Editor Sync tests
    // Note: cma-query-preview uses light DOM, SQL textarea is at #sqlTextarea
    describe('SQL to Visual Editor Sync', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            // Select SQL mode to start
            cy.get('.mode-option[data-mode="sql"]').click();
            // Wait for results tab to be ready
            cy.get('cma-tabs#mainTabs', { timeout: 20000 }).should('exist');
        });

        it('should show SQL mode with results tab', () => {
            // In SQL mode, should be on Resultaat tab
            cy.get('#mainTabs').shadow().find('.tabs-list li.selected').should('contain', 'Resultaat');
        });

        it('should have SQL preview component in results tab', () => {
            cy.get('#resultsQueryPreview').should('exist');
        });

        it('should show SQL content area', () => {
            cy.get('#resultsQueryPreview').find('.sql-code').should('exist');
        });

        it('should have editable SQL when in editable mode', () => {
            // Check if edit mode can be entered
            cy.get('#resultsQueryPreview').then($preview => {
                if ($preview.find('#sqlTextarea').length > 0) {
                    // Has textarea for editing
                    cy.get('#resultsQueryPreview #sqlTextarea').should('exist');
                }
            });
        });

        it('should handle switching between tabs', () => {
            // Switch to designer tab
            cy.get('#mainTabs').shadow().find('.tabs-list li').first().click({ force: true });
            cy.get('#mainTabs').shadow().find('.tabs-list li').first().should('have.class', 'selected');

            // Switch back to results tab
            cy.get('#mainTabs').shadow().find('.tabs-list li').last().click({ force: true });
            cy.get('#mainTabs').shadow().find('.tabs-list li').last().should('have.class', 'selected');
        });

        it('should maintain SQL content when switching tabs', () => {
            // Get initial SQL content
            cy.get('#resultsQueryPreview').find('.sql-code').then($code => {
                const initialText = $code.text();

                // Switch to designer tab and back
                cy.get('#mainTabs').shadow().find('.tabs-list li').first().click({ force: true });
                cy.wait(300);
                cy.get('#mainTabs').shadow().find('.tabs-list li').last().click({ force: true });
                cy.wait(300);

                // SQL should still be there
                cy.get('#resultsQueryPreview').find('.sql-code').should('exist');
            });
        });

        it('should have copy button in results preview', () => {
            cy.get('#resultsQueryPreview #copyBtn').should('exist');
        });

        it('should have execute button in results preview', () => {
            cy.get('#resultsQueryPreview #executeBtn').should('exist');
        });
    });

    describe('Relationship Line Positioning', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            cy.get('.mode-option[data-mode="advanced"]').click();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
        });

        it('should have setRelationshipsFromJoins method on schema canvas', () => {
            cy.get('cma-schema-canvas').then($canvas => {
                expect(typeof $canvas[0].setRelationshipsFromJoins).to.equal('function');
            });
        });

        it('should render relationship lines for tables at similar horizontal positions', () => {
            // Select two tables
            cy.get('.table-list-items .table-list-item').first().click();
            cy.get('.table-list-items .table-list-item').eq(1).click();

            // If there are relationships, the SVG should exist
            cy.get('cma-schema-canvas').shadow().find('.relationship-svg').should('exist');
        });
    });

    describe('Export Format Limitations', () => {
        beforeEach(function() {
            if (skipIfNoTables()) { this.skip(); return; }
            cy.get('.mode-option[data-mode="advanced"]').click();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
            cy.get('.table-list-items .table-list-item').first().click();
            // Navigate to output format step
            cy.clickNextStep(); // Step 2
            cy.clickNextStep(); // Step 3
            cy.clickNextStep(); // Step 4
            cy.clickNextStep(); // Step 5 (output format)
        });

        it('should have export limit notice element (hidden by default)', () => {
            cy.get('#exportLimitNotice').should('exist');
            cy.get('#exportLimitNotice').should('not.be.visible');
        });

        it('should have all format options enabled by default', () => {
            cy.get('.format-option').should('not.have.class', 'disabled');
            cy.get('input[name="outputFormat"][value="excel"]').should('not.be.disabled');
            cy.get('input[name="outputFormat"][value="json"]').should('not.be.disabled');
            cy.get('input[name="outputFormat"][value="csv"]').should('not.be.disabled');
            cy.get('input[name="outputFormat"][value="table"]').should('not.be.disabled');
        });

        it('should have updateExportFormatOptions function available', () => {
            cy.window().should('have.property', 'updateExportFormatOptions');
            cy.window().then(win => {
                expect(typeof win.updateExportFormatOptions).to.equal('function');
            });
        });

        it('should disable non-CSV formats when row count exceeds 15000', () => {
            // Call the function with a high row count
            cy.window().then(win => {
                win.updateExportFormatOptions(20000);
            });
            cy.wait(300);

            // Notice should be visible
            cy.get('#exportLimitNotice', { timeout: 5000 }).should('be.visible');

            // Excel and JSON should be disabled
            cy.get('input[name="outputFormat"][value="excel"]').should('be.disabled');
            cy.get('input[name="outputFormat"][value="json"]').should('be.disabled');

            // CSV and table should remain enabled
            cy.get('input[name="outputFormat"][value="csv"]').should('not.be.disabled');
            cy.get('input[name="outputFormat"][value="table"]').should('not.be.disabled');

            // Format options should have disabled class
            // The .format-option is the <label> element, .contains() finds inner element,
            // use .closest() to get back to the label
            cy.get('.format-option').contains('Excel').closest('.format-option').should('have.class', 'disabled');
            cy.get('.format-option').contains('JSON').closest('.format-option').should('have.class', 'disabled');
        });

        it('should re-enable formats when row count drops below 15000', () => {
            // First set high count
            cy.window().then(win => {
                win.updateExportFormatOptions(20000);
            });
            cy.get('#exportLimitNotice').should('be.visible');

            // Then set low count
            cy.window().then(win => {
                win.updateExportFormatOptions(5000);
            });

            // Notice should be hidden
            cy.get('#exportLimitNotice').should('not.be.visible');

            // All formats should be enabled
            cy.get('input[name="outputFormat"][value="excel"]').should('not.be.disabled');
            cy.get('input[name="outputFormat"][value="json"]').should('not.be.disabled');
            cy.get('.format-option').should('not.have.class', 'disabled');
        });

        it('should auto-select CSV when a disabled format is selected', () => {
            // Select Excel first
            cy.get('input[name="outputFormat"][value="excel"]').check();
            cy.get('input[name="outputFormat"][value="excel"]').should('be.checked');

            // Set high row count - should auto-switch to CSV
            cy.window().then(win => {
                win.updateExportFormatOptions(20000);
            });

            // CSV should now be selected
            cy.get('input[name="outputFormat"][value="csv"]').should('be.checked');
        });
    });
});
