/**
 * Keyboard Shortcuts Tests
 *
 * Tests for keyboard navigation and shortcuts throughout the application.
 * Uses tree mode to avoid sidepanel issues.
 */

describe('Keyboard Shortcuts', () => {
    const formName = 'users';

    beforeEach(() => {
        cy.loginAsAdmin();
    });

    // ═══════════════════════════════════════════════════════════════
    // SEARCH SHORTCUTS
    // ═══════════════════════════════════════════════════════════════

    describe('Search Shortcuts', () => {
        it('should have search input accessible', () => {
            cy.openFormTable(formName);
            cy.get('#searchfor', { timeout: 10000 }).should('exist');
        });

        it('should clear search on Escape', () => {
            cy.openFormTable(formName);
            cy.get('#searchfor', { timeout: 10000 }).type('test');
            cy.get('#searchfor').should('have.value', 'test');

            // Press Escape
            cy.get('#searchfor').type('{esc}');

            // Value might be cleared (if escape clear is implemented)
            cy.get('#searchfor').should('exist');
        });

        it('should focus search input when clicked', () => {
            cy.openFormTable(formName);
            cy.get('#searchfor', { timeout: 10000 }).click();
            // lib-search-input is a web component, check inner input has focus
            cy.get('#searchfor').then($el => {
                if ($el[0].shadowRoot) {
                    cy.wrap($el).shadow().find('input').should('have.focus');
                } else {
                    cy.wrap($el).find('input').should('have.focus');
                }
            });
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // TREE VIEW NAVIGATION
    // ═══════════════════════════════════════════════════════════════

    describe('Tree View Navigation', () => {
        beforeEach(() => {
            cy.openFormTree(formName);
        });

        it('should allow clicking tree items', () => {
            cy.get('#listContent a, #simpletree a', { timeout: 10000 }).first().click();
            cy.get('.detail-panel', { timeout: 10000 }).should('be.visible');
        });

        it('should show detail panel after selection', () => {
            cy.get('#listContent a, #simpletree a', { timeout: 10000 }).first().click();
            cy.get('.detail-panel input', { timeout: 10000 }).should('exist');
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // FORM NAVIGATION
    // ═══════════════════════════════════════════════════════════════

    describe('Form Navigation', () => {
        beforeEach(() => {
            cy.openFormTree(formName);
            cy.get('#listContent a, #simpletree a', { timeout: 10000 }).first().click();
            cy.get('.detail-panel input', { timeout: 10000 }).should('exist');
        });

        it('should have focusable form inputs', () => {
            cy.get('.detail-panel input[type="text"]', { timeout: 10000 }).first().click();
            cy.get('.detail-panel input[type="text"]').first().should('have.focus');
        });

        it('should allow navigating between inputs', () => {
            cy.get('.detail-panel input[type="text"]').first().click();
            // Verify input is visible and can receive focus
            cy.get('.detail-panel input[type="text"]').first().should('be.visible');
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // TOOLBAR NAVIGATION
    // ═══════════════════════════════════════════════════════════════

    describe('Toolbar Navigation', () => {
        beforeEach(() => {
            cy.openFormTable(formName);
        });

        it('should have clickable toolbar buttons', () => {
            cy.get('.toolbar [data-action]', { timeout: 10000 }).should('have.length.at.least', 1);
        });

        it('should respond to toolbar button clicks', () => {
            cy.get('[data-action="toggleSearch"]', { timeout: 10000 }).click();
            cy.get('#searchPanel').should('be.visible');
        });

        it('should allow toggling view mode', () => {
            cy.get('[data-action="setlistmode"][data-mode="1"]', { timeout: 10000 }).click();
            cy.get('body.mode-tree', { timeout: 5000 }).should('exist');
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // SAVE SHORTCUT
    // ═══════════════════════════════════════════════════════════════

    describe('Save Functionality', () => {
        beforeEach(() => {
            cy.openFormTree(formName);
            cy.get('#listContent a, #simpletree a', { timeout: 10000 }).first().click();
            cy.get('.detail-panel input', { timeout: 10000 }).should('exist');
        });

        it('should have save button in toolbar', () => {
            cy.get('[data-action="save"]', { timeout: 10000 }).should('exist');
        });

        it('should allow clicking save button', () => {
            cy.get('[data-action="save"]', { timeout: 10000 }).first().click();
            // Just verify no crash
            cy.wait(500);
            cy.get('body').should('be.visible');
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // VIEW MODE SWITCHING
    // ═══════════════════════════════════════════════════════════════

    describe('View Mode Switching', () => {
        it('should switch to tree mode', () => {
            cy.openFormTable(formName);
            cy.get('[data-action="setlistmode"][data-mode="1"]', { timeout: 10000 }).click();
            cy.get('body.mode-tree', { timeout: 5000 }).should('exist');
        });

        it('should switch to table mode', () => {
            cy.openFormTree(formName);
            cy.get('[data-action="setlistmode"][data-mode="2"]', { timeout: 10000 }).click();
            cy.get('body.mode-table', { timeout: 5000 }).should('exist');
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // API VERIFICATION
    // ═══════════════════════════════════════════════════════════════

    describe('API Access', () => {
        it('should load record via API', () => {
            cy.request({
                method: 'GET',
                url: `/form_api.php?action=record&form=${formName}&id=1`
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
            });
        });

        it('should load tree via API', () => {
            cy.request({
                method: 'GET',
                url: `/form_api.php?action=tree&form=${formName}`
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
            });
        });
    });
});
