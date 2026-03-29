describe('Report Designer Screenshot', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    it('captures the mode selection dialog', () => {
        cy.visit('/report-designer.php');

        // Wait for mode dialog to appear and its content to render
        cy.get('#modeDialog', { timeout: 10000 })
            .should('have.attr', 'open');
        // Wait for dialog content (mode options) to render
        cy.get('.mode-option, .report-mode-option', { timeout: 10000 })
            .should('have.length.at.least', 2);

        // Screenshot the full page
        cy.screenshot('mode-selection-dialog');
    });
});
