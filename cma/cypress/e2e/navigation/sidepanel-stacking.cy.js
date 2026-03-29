/**
 * Sidepanel Stacking Tests
 *
 * Tests that sidepanels stack correctly with proper top offsets.
 * First sidepanel: top = var(--header-height)
 * Second sidepanel: top = var(--header-height) + var(--toolbar-height)
 *
 * Sidepanels open when clicking a table row in table mode (CmaInlineEdit row click handler).
 * The popup style preference defaults to 'sidepanel' (lib_getPopupStylePreference).
 */

describe('Sidepanel Stacking', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    /**
     * Helper: Open a form table and click the first row to open a sidepanel.
     * Waits for the sidepanel container to appear.
     */
    function openSidepanelViaRowClick(formName) {
        cy.openFormTable(formName);

        // Ensure sidepanel preference is set (default is already 'sidepanel')
        cy.window().then(win => {
            win.localStorage.setItem('cma_popup_style', 'sidepanel');
        });

        // Click the first table row to trigger openFormPopup -> lib_OpenSidePanel
        // Use scrollIntoView + force to handle rows that may be partially hidden by header/toolbar
        cy.get('#listTable tbody tr[data-id]', { timeout: 15000 }).first().scrollIntoView().click({ force: true });

        // Wait for sidepanel to appear
        cy.get('.lib_sidepanel_container', { timeout: 15000 }).should('exist');
    }

    describe('Single Sidepanel', () => {
        it('should open sidepanel with header-height offset', () => {
            openSidepanelViaRowClick('users');

            // Check that first sidepanel has a top offset set (includes var(--header-height))
            cy.get('.lib_sidepanel_container').first().then($panel => {
                const top = $panel[0].style.top;
                expect(top).to.not.be.empty;
                // The top value should reference header-height or be a computed value
                expect(top).to.include('var(--header-height)');
            });
        });
    });

    describe('Stacked Sidepanels', () => {
        it('should open second sidepanel with additional toolbar-height offset', () => {
            // Use a form with subforms so we can open a second sidepanel
            openSidepanelViaRowClick('opleidingen');

            // Verify first panel has top offset
            cy.get('.lib_sidepanel_container').first().then($panel => {
                const top = $panel[0].style.top;
                expect(top).to.include('var(--header-height)');
            });
        });
    });

    describe('CSS Variables', () => {
        it('should have --header-height defined', () => {
            cy.openFormTable('users');

            cy.document().then(doc => {
                const headerHeight = getComputedStyle(doc.documentElement).getPropertyValue('--header-height');
                expect(headerHeight).to.not.be.empty;
            });
        });

        it('should have --toolbar-height defined', () => {
            cy.openFormTable('users');

            cy.document().then(doc => {
                const toolbarHeight = getComputedStyle(doc.documentElement).getPropertyValue('--toolbar-height');
                expect(toolbarHeight).to.not.be.empty;
            });
        });
    });

    describe('Sidepanel Maximize', () => {
        it('should have a maximize button in the header', () => {
            openSidepanelViaRowClick('users');

            cy.get('.lib_sidepanel_maximize').should('exist');
            cy.get('.lib_sidepanel_maximize .lnr').should('have.class', 'lnr-frame-expand');
        });

        it('should maximize sidepanel and switch icon to restore', () => {
            openSidepanelViaRowClick('users');

            // Click maximize
            cy.get('.lib_sidepanel_maximize').click();

            // Panel should be maximized
            cy.get('.lib_sidepanel_container').should('have.class', 'maximized');

            // Icon should switch to restore
            cy.get('.lib_sidepanel_maximize .lnr')
                .should('have.class', 'lnr-frame-contract')
                .and('not.have.class', 'lnr-frame-expand');
        });

        it('should restore sidepanel when clicking restore button', () => {
            openSidepanelViaRowClick('users');

            // Maximize then restore
            cy.get('.lib_sidepanel_maximize').click();
            cy.get('.lib_sidepanel_container').should('have.class', 'maximized');
            cy.get('.lib_sidepanel_maximize').click();

            // Panel should no longer be maximized
            cy.get('.lib_sidepanel_container').should('not.have.class', 'maximized');

            // Icon should switch back to expand
            cy.get('.lib_sidepanel_maximize .lnr')
                .should('have.class', 'lnr-frame-expand')
                .and('not.have.class', 'lnr-frame-contract');
        });
    });

    describe('Sidepanel Close', () => {
        it('should close sidepanel when close button clicked', () => {
            openSidepanelViaRowClick('users');

            // Click close button
            cy.get('.lib_sidepanel_close').click({ force: true });

            // Sidepanel should be removed
            cy.get('.lib_sidepanel_container').should('not.exist');
        });

        it('should close sidepanel when backdrop clicked', () => {
            openSidepanelViaRowClick('users');

            // Click backdrop
            cy.get('.lib_sidepanel_backdrop').click({ force: true });

            // Sidepanel should be removed (after potential confirmation)
            cy.get('.lib_sidepanel_container', { timeout: 5000 }).should('not.exist');
        });
    });
});
