/**
 * Report Designer Web Components Tests
 *
 * Unit tests for the report designer web components:
 * - cma-schema-canvas
 * - cma-field-config
 * - cma-param-config
 * - cma-sort-config
 * - cma-group-config
 * - cma-query-preview
 */

describe('Report Designer Web Components', () => {
    // Setup a test page with all components
    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit('/report-designer.php?mode=advanced');
        // Wait for page to load
        cy.get('.table-list-items', { timeout: 10000 }).should('exist');
    });

    describe('cma-schema-canvas', () => {
        it('should render as a custom element', () => {
            cy.get('cma-schema-canvas').should('exist');
            cy.get('cma-schema-canvas').shadow().find('.canvas-container').should('exist');
        });

        it('should show empty state initially', () => {
            cy.get('cma-schema-canvas').shadow().find('.canvas-empty').should('exist');
        });

        it('should add table when clicking table list item', () => {
            // Wait for table list to be populated
            cy.get('.table-list-items .table-list-item', { timeout: 10000 }).should('have.length.greaterThan', 0);
            // Select a table from the list to add it (force:true to bypass any overlays)
            cy.get('.table-list-items .table-list-item').first().click({ force: true });
            // Wait for schema table to appear
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('have.length', 1);
        });

        it('should display table name in header', () => {
            cy.get('.table-list-items .table-list-item', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
            cy.get('cma-schema-canvas').shadow().find('.table-name').should('have.length.greaterThan', 0);
        });

        it('should remove table when clicking remove button', () => {
            cy.get('.table-list-items .table-list-item', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
            cy.get('cma-schema-canvas').shadow().find('.remove-btn').first().click({ force: true });
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 5000 }).should('have.length', 0);
        });

        it('should display table columns', () => {
            cy.get('.table-list-items .table-list-item', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
            // Column elements are shown in the table
            cy.get('cma-schema-canvas').shadow().find('.schema-table-column', { timeout: 10000 }).should('have.length.greaterThan', 0);
        });
    });

    // Note: cma-field-config, cma-sort-config, cma-group-config do NOT use shadow DOM
    describe('cma-field-config', () => {
        beforeEach(() => {
            cy.get('.table-list-items .table-list-item', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
            // Navigate to step 2 (Fields)
            cy.get('#btnNextStepInline, #btnNextStep').filter(':visible').first().click({ force: true });
        });

        it('should render as a custom element', () => {
            cy.get('cma-field-config', { timeout: 10000 }).should('exist');
        });

        it('should display field table', () => {
            cy.get('cma-field-config .field-config-table', { timeout: 10000 }).should('exist');
        });

        it('should have field rows', () => {
            cy.get('cma-field-config .field-config-table tbody tr', { timeout: 25000 }).should('have.length.greaterThan', 0);
        });
    });

    describe('cma-param-config', () => {
        beforeEach(() => {
            cy.get('.table-list-items .table-list-item', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
            // Navigate to step 3 (Parameters) - click next twice
            cy.get('#btnNextStepInline, #btnNextStep').filter(':visible').first().click({ force: true });
            cy.wait(300);
            cy.get('#btnNextStep').click({ force: true });
        });

        it('should render as a custom element', () => {
            cy.get('cma-param-config', { timeout: 10000 }).should('exist');
        });
    });

    describe('cma-sort-config', () => {
        beforeEach(() => {
            cy.get('.table-list-items .table-list-item', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
            // Navigate to step 4 (Sorting) - click next 3 times
            cy.get('#btnNextStepInline, #btnNextStep').filter(':visible').first().click({ force: true });
            cy.wait(300);
            cy.get('#btnNextStep').click({ force: true });
            cy.wait(300);
            cy.get('#btnNextStep').click({ force: true });
        });

        it('should render as a custom element', () => {
            cy.get('cma-sort-config', { timeout: 10000 }).should('exist');
        });

        it('should have sort table', () => {
            cy.get('cma-sort-config .sort-config-container', { timeout: 10000 }).should('exist');
            cy.get('cma-sort-config .sort-list', { timeout: 10000 }).should('exist');
        });
    });

    describe('cma-group-config', () => {
        beforeEach(() => {
            cy.get('.table-list-items .table-list-item', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
            // Navigate to step 4 (Sorting/Grouping) - click next 3 times
            cy.get('#btnNextStepInline, #btnNextStep').filter(':visible').first().click({ force: true });
            cy.wait(300);
            cy.get('#btnNextStep').click({ force: true });
            cy.wait(300);
            cy.get('#btnNextStep').click({ force: true });
        });

        it('should render as a custom element', () => {
            cy.get('cma-group-config', { timeout: 10000 }).should('exist');
        });

        it('should have group table', () => {
            cy.get('cma-group-config .group-config-container', { timeout: 10000 }).should('exist');
            cy.get('cma-group-config .group-list', { timeout: 10000 }).should('exist');
        });
    });

    describe('cma-query-preview', () => {
        // NOTE: cma-query-preview does NOT use Shadow DOM - it uses light DOM
        beforeEach(() => {
            cy.get('.table-list-items .table-list-item', { timeout: 10000 }).first().click({ force: true });
            cy.get('cma-schema-canvas').shadow().find('.schema-table', { timeout: 20000 }).should('exist');
        });

        it('should render as a custom element', () => {
            cy.get('cma-query-preview', { timeout: 10000 }).should('exist');
        });

        it('should have SQL and Data tabs', () => {
            cy.get('cma-query-preview .tabs-list li', { timeout: 10000 }).should('have.length', 2);
            cy.get('cma-query-preview .tabs-list li[data-tab="sql"]').should('exist');
            cy.get('cma-query-preview .tabs-list li[data-tab="data"]').should('exist');
        });

        it('should show SQL tab by default', () => {
            cy.get('cma-query-preview .tabs-list li[data-tab="sql"]', { timeout: 10000 }).should('have.class', 'selected');
        });

        it('should display SQL code area', () => {
            cy.get('cma-query-preview .sql-code', { timeout: 10000 }).should('exist');
        });

        it('should have copy button', () => {
            cy.get('cma-query-preview #copyBtn', { timeout: 10000 }).should('exist');
        });

        it('should have execute button (for refresh)', () => {
            cy.get('cma-query-preview #executeBtn', { timeout: 10000 }).should('exist');
        });

        it('should switch to data tab when clicked', () => {
            cy.get('cma-query-preview .tabs-list li[data-tab="data"]', { timeout: 10000 }).click({ force: true });
            cy.get('cma-query-preview .tabs-list li[data-tab="data"]').should('have.class', 'selected');
        });

        it('should switch back to SQL tab when clicked', () => {
            // First switch to data tab
            cy.get('cma-query-preview .tabs-list li[data-tab="data"]', { timeout: 10000 }).click({ force: true });
            cy.get('cma-query-preview .tabs-list li[data-tab="data"]').should('have.class', 'selected');
            // Then switch back to SQL tab
            cy.get('cma-query-preview .tabs-list li[data-tab="sql"]').click({ force: true });
            cy.get('cma-query-preview .tabs-list li[data-tab="sql"]').should('have.class', 'selected');
        });
    });
});
