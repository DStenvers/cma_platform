/**
 * Storybook Icons Tests
 *
 * Tests for the Linearicons section of the storybook,
 * including icon grid rendering, add-icon button, and font loading.
 *
 * Run: npx cypress run --spec "cypress/e2e/tools/storybook-icons.cy.js"
 */

describe('Storybook Linearicons', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit('/tools/storybook.php');
        cy.wait(1000);
    });

    describe('Icon Grid Rendering', () => {
        it('should display the Linearicons section', () => {
            cy.get('#linearicons').should('exist');
            cy.get('#linearicons .component-header h2').should('contain', 'Linearicons');
        });

        it('should render used icons grid with correct count', () => {
            cy.get('#iconsGridUsed .icon-item', { timeout: 5000 }).should('have.length.greaterThan', 50);
            cy.get('#usedIconCount').invoke('text').then(text => {
                const count = parseInt(text, 10);
                expect(count).to.be.greaterThan(50);
            });
        });

        it('should show icon name and code for each used icon', () => {
            cy.get('#iconsGridUsed .icon-item', { timeout: 5000 }).first().within(() => {
                cy.get('.lnr').should('exist');
                cy.get('.icon-name').should('exist').invoke('text').should('match', /^lnr-/);
                cy.get('.icon-code').should('exist').invoke('text').should('match', /^\\[a-f0-9]+$/);
            });
        });

        it('should have remaining icons in a collapsible details element', () => {
            cy.get('.icons-collapsible').should('exist');
            cy.get('#remainingIconCount').invoke('text').then(text => {
                const count = parseInt(text, 10);
                expect(count).to.be.greaterThan(0);
            });
        });

        it('should render remaining icons when details is opened', () => {
            cy.get('.icons-collapsible summary').click();
            cy.get('#iconsGridAll .icon-item', { timeout: 5000 }).should('have.length.greaterThan', 10);
        });
    });

    describe('Add Icon Button', () => {
        it('should show add button on hover for unused icons', () => {
            cy.get('.icons-collapsible summary').click();
            cy.get('#iconsGridAll .icon-item-unused', { timeout: 5000 }).first().within(() => {
                cy.get('.btn-add-icon').should('exist');
            });
        });

        it('should POST to icon_add API when add button clicked', () => {
            cy.get('.icons-collapsible summary').click();

            cy.intercept('POST', '**/api/icon_add.php').as('addIcon');

            cy.get('#iconsGridAll .icon-item-unused', { timeout: 5000 }).first().then($item => {
                const name = $item.find('.btn-add-icon').attr('data-icon-name');
                const code = $item.find('.btn-add-icon').attr('data-icon-code');

                // Click the add button
                cy.wrap($item).find('.btn-add-icon').click({ force: true });

                cy.wait('@addIcon').then(interception => {
                    expect(interception.request.method).to.equal('POST');
                    // Verify the body contains the icon name
                    const body = interception.request.body;
                    expect(body).to.include(name);
                });
            });
        });
    });

    describe('Icon Search', () => {
        it('should filter icons when searching', () => {
            cy.get('#iconSearch', { timeout: 5000 }).should('exist');
            // lib-search-input uses light DOM (no shadow root), so query input directly
            cy.get('#iconSearch').find('input').type('home');
            cy.get('#iconSearchResults', { timeout: 3000 }).should('be.visible');
            cy.get('#iconsGridSearch .icon-item').should('have.length.greaterThan', 0);
            cy.get('#searchResultCount').invoke('text').then(text => {
                expect(parseInt(text, 10)).to.be.greaterThan(0);
            });
        });

        it('should show default view when search is cleared', () => {
            // lib-search-input uses light DOM (no shadow root), so query input directly
            cy.get('#iconSearch').find('input').type('home');
            cy.get('#iconSearchResults').should('be.visible');
            cy.get('#iconSearch').find('input').clear();
            cy.get('#iconDefaultView').should('be.visible');
        });
    });

    describe('Font Rendering', () => {
        it('should render all used icons with correct font', () => {
            // Check that the first few icons actually render with Linearicons font
            cy.get('#iconsGridUsed .icon-item', { timeout: 5000 }).first().find('.lnr').then($el => {
                const fontFamily = window.getComputedStyle($el[0], '::before').fontFamily;
                expect(fontFamily).to.include('Linearicons');
            });
        });

        it('should use optimized font references in main stylesheet', () => {
            // Verify the main stylesheet references woff2
            // Use relative path since baseUrl already includes /cma
            cy.request('/assets/css/style.css').then(response => {
                expect(response.body).to.include('woff2');
                expect(response.body).to.include('font-display: swap');
                expect(response.body).not.to.include('.eot');
                expect(response.body).not.to.include('.svg');
            });
        });
    });
});
