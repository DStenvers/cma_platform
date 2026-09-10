/**
 * Storybook: de sectie "libLog" draait op de echte logger.
 *
 * lib-log.js zit bewust niet in de CMA-bundel (het neemt console.* over), en de storybook
 * laadde het ook niet los. De sectie viel daardoor terug op de kale libLog uit library.js:
 * flush(), getRequestId() en isDebug() gaven "is not a function". Nu laadt de storybook
 * lib-log.js zelf, en is LibLog (oude naam) een dun laagje op libLog.
 *
 * Run: npx cypress run --spec "cypress/e2e/tools/storybook-liblog.cy.js"
 */

describe('Storybook libLog', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit('/tools/storybook.php');
        cy.wait(1000);
        cy.get('#liblog').scrollIntoView();
    });

    it('lib-log.js is geladen: libLog is de echte logger, LibLog het laagje', () => {
        cy.window().then(win => {
            for (const m of ['flush', 'getRequestId', 'isDebug', 'getConfig', 'setDebug', 'refreshFromCookie']) {
                expect(win.libLog[m], 'libLog.' + m).to.be.a('function');
            }
            expect(win.LibLog, 'LibLog bestaat nog').to.exist;
            expect(win.LibLog, 'LibLog is een laagje, niet hetzelfde object').to.not.equal(win.libLog);
            expect(win.LibLog.getRequestId()).to.equal(win.libLog.getRequestId());
        });
    });

    it('de methode-knoppen geven geen fout', () => {
        cy.on('uncaught:exception', (e) => { throw e; });
        for (const tekst of ['libLog.info()', 'flush()', 'getRequestId()', 'isDebug()', 'getConfig()']) {
            cy.get('#liblog .playground-preview button').contains(tekst).click({ force: true });
            cy.get('body').type('{esc}');
        }
    });
});
