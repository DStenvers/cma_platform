/**
 * De lijst moet doorladen tot de laatste regel.
 *
 * Dit is de derde keer dat dit terugvalt, en dat komt doordat er tot nu toe
 * steeds een symptoom is gerepareerd zonder de afspraak vast te leggen. Deze
 * test legt hem vast: welk formulier je ook opent, de teller loopt door tot het
 * totaal en blijft niet halverwege op "(laden...)" staan.
 *
 * Waarom E2E: de scroller kiest zijn container uit de DOM en luistert op scroll.
 * Of dat de juiste container is, blijkt alleen op een echt scherm met een echte
 * lijst — jsdom scrollt niet.
 *
 * Het formulier is instelbaar, want welke lijst lang genoeg is verschilt per
 * site. De standaard hoort bij mijnRINO.
 */
const FORM = Cypress.env('scrollForm') || 'deelnemers';
// Alleen tabelmodus: #recordCount draagt de klasse table-mode-only, dus in
// boommodus is er geen teller om iets aan af te lezen.
const MODI = ['table'];

describe('Lijst laadt door tot de laatste regel', () => {
    MODI.forEach(modus => {
        it('in ' + modus + '-modus', () => {
            cy.loginAsAdmin();
            cy.visit(`/form.php?form=${FORM}&view=${modus}`);
            cy.get('.form-layout', { timeout: 20000 }).should('exist');

            // Wachten tot de teller een totaal noemt; zonder totaal valt er niets
            // te bewijzen (te korte lijst) en slaan we over.
            cy.get('#recordCount', { timeout: 30000 }).should('not.be.empty');
            cy.get('#recordCount').invoke('text').then(tekst => {
                const m = /van\s+(\d+)/.exec(tekst);
                if (!m) {
                    cy.log('Geen totaal in "' + tekst + '" — lijst te kort, overslaan');
                    return;
                }
                const totaal = parseInt(m[1], 10);
                if (totaal <= 200) {
                    cy.log('Maar ' + totaal + ' records: past in één batch, zegt niets');
                    return;
                }

                // De kern: de teller moet het totaal halen en "(laden...)" laten
                // vallen. Ruim de tijd, want het gaat in batches met pauzes.
                cy.get('#recordCount', { timeout: 120000 })
                    .should('contain', 'van ' + totaal)
                    .and('not.contain', 'laden');

                cy.get('#recordCount').invoke('text').then(eind => {
                    const g = /1-(\d+)\s+van\s+(\d+)/.exec(eind);
                    expect(g, 'teller leesbaar: ' + eind).to.not.be.null;
                    expect(Number(g[1]), 'alle records geladen (' + eind + ')')
                        .to.equal(Number(g[2]));
                });
            });
        });
    });

    /**
     * De afspraak achter de fout: er is één scroller, en die luistert op het
     * element dat ECHT scrollt. Kiest hij zijn container op DOM-positie
     * (parentElement), dan klopt dat in de ene weergave wel en in de andere
     * niet, komt er nooit een scroll-gebeurtenis binnen, en stopt het laden
     * zodra de achtergrond-prefetch klaar is.
     */
    it('luistert op het element dat werkelijk scrollt', () => {
        cy.loginAsAdmin();
        cy.visit(`/form.php?form=${FORM}&view=table`);
        cy.get('#listContent', { timeout: 20000 }).should('exist');
        cy.wait(4000);

        cy.window().then(win => {
            const doc = win.document;
            const ctrl = doc.querySelector('.form-layout')._cmaController;
            const gekozen = ctrl && ctrl.infiniteScroll && ctrl.infiniteScroll.container;
            expect(gekozen, 'de scroller heeft een container').to.exist;

            const cs = win.getComputedStyle(gekozen);
            const scrollt = (cs.overflowY === 'auto' || cs.overflowY === 'scroll');
            expect(scrollt,
                'gekozen container <' + gekozen.tagName + ' id=' + gekozen.id +
                ' class=' + gekozen.className + '> heeft overflow-y=' + cs.overflowY
            ).to.be.true;
        });
    });
});
