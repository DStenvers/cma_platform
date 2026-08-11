/**
 * De lijst moet doorladen tot de laatste regel, en de teller moet dat eerlijk
 * melden.
 *
 * Dit is de derde keer dat dit terugvalt, en dat komt doordat er tot nu toe
 * steeds een symptoom is gerepareerd zonder de afspraak vast te leggen. Twee
 * afspraken staan hier vast:
 *
 *   1. alle regels komen binnen — de scroller telt tot het totaal dat de server
 *      noemt, en de tabel bevat er evenveel;
 *   2. de teller liegt niet — hij blijft nooit op "(laden...)" hangen. Is alles
 *      binnen, dan valt er niets meer te melden en is hij leeg; stopt het laden
 *      eronder, dan staat er een aantal ZONDER "(laden...)".
 *
 * Die tweede afspraak is de eigenlijke melding: het laden liep gewoon door,
 * maar de teller bleef staan op de regel van de batch ervoor, dus las het als
 * een lijst die halverwege stopt.
 *
 * Waarom E2E: de scroller kiest zijn container uit de DOM en luistert op
 * scroll. Of dat de juiste container is, blijkt alleen op een echt scherm met
 * een echte lijst — jsdom scrollt niet.
 *
 * Het formulier is instelbaar, want welke lijst lang genoeg is verschilt per
 * site. De standaard hoort bij mijnRINO.
 */
const FORM = Cypress.env('scrollForm') || 'deelnemers';

describe('Lijst laadt door tot de laatste regel', () => {
    it('laadt alles en laat de teller niet op "(laden...)" staan', () => {
        cy.loginAsAdmin();
        cy.visit(`/form.php?form=${FORM}&view=table`);
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

            // De kern: "(laden...)" moet vallen. Ruim de tijd, want het gaat in
            // batches met pauzes.
            cy.get('#recordCount', { timeout: 120000 }).should('not.contain', 'laden');

            // En wat er dan staat mag geen halve lijst suggereren: leeg (alles
            // binnen) of een aantal, maar nooit een aantal ONDER het totaal
            // terwijl de scroller wel alles heeft.
            cy.get('#recordCount').invoke('text').then(eind => {
                const g = /1-(\d+)\s+van\s+(\d+)/.exec(eind);
                if (g) {
                    expect(Number(g[1]), 'teller onder het totaal zonder te laden: ' + eind)
                        .to.equal(Number(g[2]));
                }
            });

            // De echte maat: de scroller heeft alles opgehaald en de tabel bevat
            // net zoveel regels als de server zegt te hebben.
            cy.window().then(win => {
                const ctrl = win.document.querySelector('.form-layout')._cmaController;
                const scroller = ctrl.infiniteScroll;
                expect(scroller, 'de lijst heeft een scroller').to.exist;
                expect(scroller.totalCount, 'de server noemde een totaal').to.equal(totaal);
                expect(scroller.currentCount, 'alle records geladen').to.equal(totaal);
                expect(scroller.hasMore, 'het laden is afgerond').to.be.false;

                const rijen = win.document
                    .querySelectorAll('#listTable tbody tr[data-id]').length;
                expect(rijen, 'alle regels staan in de tabel').to.equal(totaal);
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

            // En de teller meet dezelfde container, anders beslist de een over
            // "past dit op het scherm?" op een element dat de ander niet kent.
            expect(ctrl.getListScrollContainer(), 'teller en scroller meten hetzelfde element')
                .to.equal(gekozen);
        });
    });
});
