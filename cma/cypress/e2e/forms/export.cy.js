/**
 * Export Functionality Tests
 *
 * Tests for table/form data export functionality (Excel, CSV).
 * The export menu is added dynamically by filtering_init() in library.js.
 *
 * Run: npx cypress run --spec "cypress/e2e/forms/export.cy.js"
 */

describe('Export Functionality', () => {
    const formName = 'users';

    beforeEach(() => {
        cy.loginAsAdmin();
    });

    describe('Export Menu', () => {
        it('should display export menu in table', () => {
            cy.openFormTable(formName);

            // Wait for table to be visible and filtering_init to run
            cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');

            // Export menu is added dynamically by filtering_init()
            cy.get('.menutrigger', { timeout: 10000 }).should('exist');
        });

        it('should have visible dimensions for the export menu trigger', () => {
            cy.openFormTable(formName);

            // Wait for table to be visible and filtering_init to run
            cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');

            // Export menu trigger should have visible height (not 0px)
            cy.get('.menutrigger', { timeout: 10000 }).first()
                .should('have.css', 'height')
                .and('not.eq', '0px');

            // Trigger should have minimum clickable area
            cy.get('.menutrigger').first().then($trigger => {
                const height = parseFloat($trigger.css('height'));
                expect(height).to.be.at.least(15, 'menutrigger should have at least 15px height');
            });
        });

        it('should open export dropdown on click', () => {
            cy.openFormTable(formName);
            cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');

            // Click the menu trigger
            cy.get('.menutrigger', { timeout: 10000 }).first().click();

            // Menu should have open class
            cy.get('.menutrigger.open').should('exist');
        });

        it('should display Excel export option', () => {
            cy.openFormTable(formName);
            cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');

            // Open the menu
            cy.get('.menutrigger', { timeout: 10000 }).first().click();

            // Should have Excel export option
            cy.get('.exportXLS, li:contains("Excel")').should('exist');
        });

        it('should display CSV export option', () => {
            cy.openFormTable(formName);
            cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');

            // Open the menu
            cy.get('.menutrigger', { timeout: 10000 }).first().click();

            // Should have CSV export option
            cy.get('.exportCSV, li:contains("CSV")').should('exist');
        });

        it('should close menu when clicking outside', () => {
            cy.openFormTable(formName);
            cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');

            // Open the menu
            cy.get('.menutrigger', { timeout: 10000 }).first().click();
            cy.get('.menutrigger.open').should('exist');

            // Click outside
            cy.get('body').click(10, 10);

            // Menu should be closed
            cy.get('.menutrigger.open').should('not.exist');
        });
    });

    describe('Excel Export', () => {
        it('should have Excel export with icon', () => {
            cy.openFormTable(formName);
            cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');

            // Open the menu
            cy.get('.menutrigger', { timeout: 10000 }).first().click();

            // Should have Excel option with icon
            cy.get('.exportXLS .lnr-excel, .exportXLS .lnr').should('exist');
        });

        it('should have clickable Excel export', () => {
            cy.openFormTable(formName);
            cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');

            // Open the menu
            cy.get('.menutrigger', { timeout: 10000 }).first().click();

            // Excel option should exist and be clickable
            cy.get('.exportXLS').should('exist').and('be.visible');
        });
    });

    describe('CSV Export', () => {
        it('should have CSV export with icon', () => {
            cy.openFormTable(formName);
            cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');

            // Open the menu
            cy.get('.menutrigger', { timeout: 10000 }).first().click();

            // Should have CSV option with icon
            cy.get('.exportCSV .lnr-csv, .exportCSV .lnr').should('exist');
        });

        it('should have clickable CSV export', () => {
            cy.openFormTable(formName);
            cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');

            // Open the menu
            cy.get('.menutrigger', { timeout: 10000 }).first().click();

            // CSV option should exist and be clickable
            cy.get('.exportCSV').should('exist').and('be.visible');
        });
    });

    describe('Export After Operations', () => {
        it('should maintain export menu after search', () => {
            cy.openFormTable(formName);
            cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');

            // Type in search
            cy.get('#searchfor').type('admin');

            // Wait for search to apply
            cy.wait(500);

            // Export menu should still exist
            cy.get('.menutrigger').should('exist');
        });
    });
});
