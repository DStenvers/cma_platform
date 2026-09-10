/**
 * lib-search-input Web Component Tests
 *
 * Gemeld: "lib-search-input, the readonly variant is not readonly". De storybook toont een
 * demo met het attribuut readonly, maar het component kende alleen disabled: het veld was
 * gewoon te bewerken en de wisknop stond erbij. Nu is het invoerveld readonly, blijft de
 * wisknop weg en laten Escape en clear() de waarde staan; Enter geeft nog wel `search`.
 *
 * Run: npx cypress run --spec "cypress/e2e/components/lib-search-input.cy.js"
 */

describe('lib-search-input Web Component', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit('/tools/storybook.php');
        cy.wait(1000);
        cy.get('#lib-search-input').scrollIntoView();
    });

    const demo = () => cy.get('#lib-search-input .playground-preview lib-search-input[readonly]');

    it('is geregistreerd', () => {
        cy.window().then(win => {
            expect(win.customElements.get('lib-search-input')).to.exist;
        });
    });

    it('de readonly-demo is echt readonly: veld readonly, geen wisknop', () => {
        demo().find('input').should('have.attr', 'readonly');
        demo().find('input').should('have.value', 'Readonly waarde');
        demo().find('.clear-btn').should('not.have.class', 'visible');
        demo().find('.lib-search-input').should('have.class', 'readonly');
    });

    it('typen, Escape en clear() laten de waarde staan; Enter zoekt nog wel', () => {
        demo().find('input').type('xyz', { force: true });
        demo().find('input').should('have.value', 'Readonly waarde');
        demo().find('input').type('{esc}', { force: true });
        demo().find('input').should('have.value', 'Readonly waarde');
        demo().then($el => {
            const el = $el[0];
            const events = [];
            el.addEventListener('clear', () => events.push('clear'));
            el.addEventListener('search', e => events.push('search:' + e.detail.value));
            el.clear();
            expect(el.value, 'clear() op een readonly veld').to.equal('Readonly waarde');
            el.querySelector('input').dispatchEvent(new el.ownerDocument.defaultView.KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
            expect(events).to.deep.equal(['search:Readonly waarde']);
        });
    });

    it('readonly is omkeerbaar: attribuut weg, wisknop terug', () => {
        demo().then($el => { $el[0].readOnly = false; });
        cy.get('#lib-search-input .playground-preview lib-search-input').eq(4).within(() => {
            cy.get('input').should('not.have.attr', 'readonly');
            cy.get('.clear-btn').should('have.class', 'visible');
        });
        cy.get('#lib-search-input .playground-preview lib-search-input').eq(4).then($el => { $el[0].readOnly = true; });
        demo().find('.clear-btn').should('not.have.class', 'visible');
    });

    it('de disabled-demo blijft disabled (geen regressie)', () => {
        cy.get('#lib-search-input .playground-preview lib-search-input[disabled] input').should('be.disabled');
    });
});
