/**
 * Bewijs dat een zijpaneel zijn inhoud de volle hoogte geeft.
 *
 * Het paneel is een flex-kolom: kop, iframe, en twee sleepgrepen die er OVER
 * horen te liggen. Verliezen die grepen hun absolute positionering, dan worden
 * het gewone kinderen en snoept de hoekgreep 14px van de iframe af — een strook
 * paneelachtergrond onder elk scherm, het duidelijkst bij een formulier dat toch
 * al moet scrollen. Dat is geen kwestie van smaak maar van rekenen, dus telt
 * deze test kop + inhoud op en legt hem naast de hoogte van het paneel.
 */
const FORM = Cypress.env('sidepanelHeightForm') || 'opleidingen';

describe('Zijpaneel geeft zijn inhoud de volle hoogte', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit(`/form.php?form=${FORM}`);
        cy.get('.form-layout', { timeout: 20000 }).should('exist');
        cy.window().then(win => {
            win.lib_OpenPanel(`form.php?form=${FORM}&New=Y`, 'hoogteproef', 800, 600, 'Toevoegen');
        });
        cy.get('.lib_sidepanel_container iframe', { timeout: 20000 }).should('exist');
        cy.wait(2000);
    });

    it('kop + inhoud vullen het paneel precies', () => {
        cy.document().then(doc => {
            const paneel = doc.querySelector('.lib_sidepanel_container');
            const kop = paneel.querySelector('.lib_sidepanel_header');
            const inhoud = paneel.querySelector('.lib_sidepanel_content');
            const h = el => el.getBoundingClientRect().height;

            expect(h(kop) + h(inhoud), 'kop + inhoud is de hele paneelhoogte')
                .to.be.closeTo(h(paneel), 1);
        });
    });

    it('de sleepgrepen liggen over het paneel, niet erin', () => {
        cy.document().then(doc => {
            const win = doc.defaultView;
            ['.lib_sidepanel_greep_rand', '.lib_sidepanel_greep_hoek'].forEach(sel => {
                const greep = doc.querySelector(`.lib_sidepanel_container > ${sel}`);
                expect(greep, sel + ' bestaat').to.exist;
                expect(win.getComputedStyle(greep).position, sel + ' is absolute')
                    .to.equal('absolute');
            });
        });
    });
});
