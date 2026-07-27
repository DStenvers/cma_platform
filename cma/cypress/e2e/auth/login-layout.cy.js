/**
 * login-layout.cy.js — de afmetingen van het loginscherm.
 *
 * Waarom dit een eigen spec is: de velden hebben zelf geen breedte meer
 * (width:100%), die zit op `form#login div.kader`. Haalt iemand die regel weg,
 * dan klapt het hele vak samen tot de breedte van de langste tekst en ziet het
 * formulier er kapot uit — zonder dat er iets faalt. Dat is precies wat een
 * mens niet opmerkt en een assertie wel.
 *
 * login.cy.js dekt het gedrag (velden zichtbaar, focus, inloggen); hier gaat
 * het uitsluitend over layout.
 */

const PHONE = { width: 390, height: 844 };   // iPhone 12/13/14
const DESKTOP = { width: 1280, height: 800 };

/** De breedte die style.css aan het loginkader geeft. */
const CARD_WIDTH = 445;
/** Breedte van het icoon in het label — de tekst mag daar niet onder vallen. */
const ICON_WIDTH = 28;

const px = (value) => parseFloat(value) || 0;

describe('Login — layout', () => {
    describe('desktop', () => {
        beforeEach(() => {
            cy.viewport(DESKTOP.width, DESKTOP.height);
            cy.visit('/login.php');
        });

        it('het loginkader klapt niet samen', () => {
            cy.get('form#login div.kader').then(($kader) => {
                const width = $kader[0].getBoundingClientRect().width;
                // Tolerantie voor sub-pixel afronding; het gaat erom dat de
                // breedte van .kader komt en niet van de inhoud.
                expect(width, 'breedte van div.kader').to.be.closeTo(CARD_WIDTH, 2);
            });
        });

        it('de velden vullen de rij', () => {
            cy.get('#loginForm').then(($form) => {
                const style = getComputedStyle($form[0]);
                const inner = $form[0].getBoundingClientRect().width
                    - px(style.paddingLeft) - px(style.paddingRight);

                cy.get('#txtLogin').then(($input) => {
                    const width = $input[0].getBoundingClientRect().width;
                    expect(width, 'naamveld vult de beschikbare breedte').to.be.closeTo(inner, 2);
                });
                cy.get('#txtPW').then(($input) => {
                    const width = $input[0].getBoundingClientRect().width;
                    expect(width, 'wachtwoordveld vult de beschikbare breedte').to.be.closeTo(inner, 2);
                });
            });
        });

        it('de tekst valt niet onder het icoon', () => {
            // De labels staan absoluut gepositioneerd over de linkerkant van het
            // veld heen; zonder voldoende padding-left typ je ín het icoon.
            ['#txtLogin', '#txtPW'].forEach((selector) => {
                cy.get(selector).then(($input) => {
                    const padding = px(getComputedStyle($input[0]).paddingLeft);
                    expect(padding, `padding-left van ${selector}`).to.be.at.least(ICON_WIDTH);
                });
            });
        });

        it('border-box, anders steekt het veld buiten het kader', () => {
            cy.get('#txtLogin').should('have.css', 'box-sizing', 'border-box');
        });
    });

    describe('telefoon', () => {
        beforeEach(() => {
            cy.viewport(PHONE.width, PHONE.height);
            cy.visit('/login.php');
        });

        it('geen horizontale overflow', () => {
            cy.document().then((doc) => {
                const viewport = doc.defaultView.innerWidth;
                // +1 voor sub-pixel afronding in de browser.
                expect(doc.documentElement.scrollWidth, 'scrollWidth van het document')
                    .to.be.at.most(viewport + 1);
                expect(doc.body.scrollWidth, 'scrollWidth van de body')
                    .to.be.at.most(viewport + 1);
            });
        });

        it('het kader past binnen het scherm', () => {
            cy.get('form#login div.kader').then(($kader) => {
                const rect = $kader[0].getBoundingClientRect();
                expect(rect.width, 'breedte van div.kader').to.be.at.most(PHONE.width);
                expect(rect.left, 'linkerkant valt niet buiten beeld').to.be.at.least(0);
                expect(rect.right, 'rechterkant valt niet buiten beeld').to.be.at.most(PHONE.width + 1);
            });
        });

        it('de velden blijven binnen het kader', () => {
            cy.get('form#login div.kader').then(($kader) => {
                const card = $kader[0].getBoundingClientRect();
                cy.get('#txtLogin').then(($input) => {
                    const rect = $input[0].getBoundingClientRect();
                    expect(rect.right, 'naamveld steekt niet buiten het kader')
                        .to.be.at.most(card.right + 1);
                });
            });
        });
    });

    describe('wachtwoord-vergeten-formulier', () => {
        beforeEach(() => {
            cy.viewport(DESKTOP.width, DESKTOP.height);
            cy.visit('/login.php');
        });

        it('het e-mailveld krijgt dezelfde geometrie als de loginvelden', () => {
            // Dit veld had een eigen override (smallere padding, geen
            // padding-rechts) die overbodig werd toen de velden fluid werden.
            // Als iemand die override terugzet, wijkt dit veld weer af.
            cy.get('#login_vergeten_form').should('not.be.visible');
            cy.get('span.login_vergeten a').first().click();
            cy.get('#login_vergeten_form').should('be.visible');

            cy.get('#txtLogin').then(($reference) => {
                const want = px(getComputedStyle($reference[0]).paddingLeft);
                cy.get('#email_id').then(($input) => {
                    const style = getComputedStyle($input[0]);
                    expect(px(style.paddingLeft), 'padding-left van het e-mailveld').to.equal(want);
                    expect(px(style.paddingRight), 'padding-right van het e-mailveld').to.be.greaterThan(0);
                });
            });
        });
    });
});
