/**
 * lib-pagination Web Component Tests (storybook)
 *
 * Run: npx cypress run --spec "cypress/e2e/components/lib-pagination.cy.js"
 */

describe('lib-pagination Web Component', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit('/tools/storybook.php');
        cy.wait(1000);
        cy.get('#lib-pagination').scrollIntoView();
    });

    it('is geregistreerd (zit in de bundel)', () => {
        cy.window().then(win => {
            expect(win.customElements.get('lib-pagination')).to.exist;
        });
    });

    it('de demo met links toont een venster rond pagina 3 en linkt met {page} ingevuld', () => {
        cy.get('#lib-pagination .playground-preview lib-pagination').first().shadow().within(() => {
            cy.get('[data-page]').should('have.length', 10);   // « ‹ 1 2 3 4 5 6 › »
            cy.get('[aria-current="page"]').should('have.text', '3');
            cy.get('a[data-page="4"]').should('have.attr', 'href', '#lib-pagination?p=4');
            cy.get('[data-page="1"][aria-label="Eerste pagina"]').should('not.have.attr', 'aria-disabled');
        });
    });

    it('de client-side demo vuurt page-change en zet page zelf', () => {
        cy.get('#pagerEvent').shadow().find('[data-page="2"][aria-label="Pagina 2"]').click();   // niet "volgende", die wijst ook naar 2
        cy.get('#pagerEvent').should('have.attr', 'page', '2');
        cy.get('#pagerEventUit').should('contain', 'pagina 2');
        cy.get('#lib-pagination .playground-preview button').contains('page = 5').click();
        cy.get('#pagerEvent').shadow().find('[aria-current="page"]').should('have.text', '5');
    });

    it('één pagina rendert niets', () => {
        cy.get('#lib-pagination .playground-preview lib-pagination[pages="1"]').shadow().find('[data-page]').should('not.exist');
    });
});
