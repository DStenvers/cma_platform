/**
 * lib-sheet Web Component Tests
 *
 * Gemeld: "lib-sheet does not work". De storybook-demo viel om met
 * "document.getElementById(...).open is not a function": lib-sheet.js zat niet in
 * cma_js_bundle(), dus customElements.get('lib-sheet') was leeg en het element bleef een
 * kale HTMLElement. Deze spec eist registratie (geen "skip als niet geladen") en loopt
 * connectedCallback → attribute change → user event → expected state door.
 *
 * Run: npx cypress run --spec "cypress/e2e/components/lib-sheet.cy.js"
 */

describe('lib-sheet Web Component', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit('/tools/storybook.php');
        cy.wait(1000);
    });

    it('is geregistreerd op een CMA-pagina (zit in de bundel)', () => {
        cy.window().then(win => {
            expect(win.customElements.get('lib-sheet'), 'lib-sheet ontbreekt in cma_js_bundle()').to.exist;
        });
    });

    it('de demo-knop opent het paneel en de sluitknop sluit het weer', () => {
        cy.get('#lib-sheet').scrollIntoView();
        cy.get('#lib-sheet .playground-preview button').first().click();
        cy.get('#demoSheet1').should('have.attr', 'open');
        cy.get('#demoSheet1').should('not.have.attr', 'aria-hidden');
        // Het paneel staat in beeld: onderaan, met de heading uit het attribuut.
        cy.get('#demoSheet1').then($el => {
            const panel = $el[0].shadowRoot.querySelector('.panel');
            const r = panel.getBoundingClientRect();
            expect(r.height, 'paneelhoogte').to.be.greaterThan(50);
            expect(r.bottom, 'paneel hangt onderaan het venster').to.be.closeTo(Cypress.config('viewportHeight'), 2);
            expect($el[0].shadowRoot.querySelector('.title').textContent).to.equal('Acties');
        });
        cy.get('#demoSheet1').then($el => { $el[0].shadowRoot.querySelector('.close').click(); });
        cy.get('#demoSheet1').should('not.have.attr', 'open');
        cy.get('#demoSheet1').should('have.attr', 'aria-hidden', 'true');
    });

    it('open()/close()/toggle() en de events', () => {
        cy.window().then(win => {
            const doc = win.document;
            const sheet = doc.getElementById('sheetMethods');
            expect(sheet.open, 'open() bestaat').to.be.a('function');
            const events = [];
            sheet.addEventListener('sheet-open', () => events.push('open'));
            sheet.addEventListener('sheet-close', () => events.push('close'));
            sheet.open();
            expect(sheet.hasAttribute('open')).to.equal(true);
            sheet.removeAttribute('open');   // directe attribuutwissel: meteen dicht, geen animatie
            expect(sheet.hasAttribute('open')).to.equal(false);
            sheet.toggle();
            expect(sheet.hasAttribute('open')).to.equal(true);
            expect(events).to.deep.equal(['open', 'close', 'open']);
            sheet.removeAttribute('open');
        });
    });

    it('closable="false" negeert Escape en een klik op de backdrop', () => {
        cy.window().then(win => { win.document.getElementById('demoSheet3').open(); });
        cy.get('#demoSheet3').should('have.attr', 'open');
        cy.get('body').type('{esc}');
        cy.get('#demoSheet3').should('have.attr', 'open');
        cy.get('#demoSheet3').then($el => { $el[0].shadowRoot.querySelector('.backdrop').click(); });
        cy.get('#demoSheet3').should('have.attr', 'open');
        cy.get('#demoSheet3 button').contains('Bevestigen').click();
        cy.get('#demoSheet3').should('not.have.attr', 'open');
    });
});
