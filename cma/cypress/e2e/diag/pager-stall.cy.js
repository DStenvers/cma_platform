/**
 * READ-ONLY diagnostic: reproduce the "records 1-1500 van 1796 (laden...)" stall
 * on a large list and capture the infinite-scroll's own console warnings so the
 * stall reason (cursor-stall net / give-up-after-retries / an exception) is
 * visible. Not part of the suite; run on demand:
 *
 *   CYPRESS_baseUrl=https://www.karaatedelstenen.nl/cma \
 *   CYPRESS_u=<user> CYPRESS_p=<pass> \
 *   npx cypress run --spec cypress/e2e/diag/pager-stall.cy.js
 */
describe('pager stall diag', () => {
    const U = Cypress.env('u');
    const P = Cypress.env('p');
    const FORM = Cypress.env('form') || 'steensoorten_producten';

    it(`walks ${FORM} and reports whether the counter reaches the total`, () => {
        cy.request({ method: 'POST', url: '/login.php', form: true, followRedirect: true,
            body: { naam: U, wachtwoord: P } });

        const logs = [];
        cy.visit(`/form/${FORM}`, {
            onBeforeLoad(win) {
                ['log', 'warn', 'error'].forEach((level) => {
                    const orig = win.console[level].bind(win.console);
                    win.console[level] = (...args) => {
                        try {
                            const line = args.map((a) => (typeof a === 'string' ? a : JSON.stringify(a))).join(' ');
                            if (/scroll|laden|cursor|retr|hasMore|pending|load/i.test(line)) {
                                logs.push(level.toUpperCase() + ': ' + line.slice(0, 300));
                            }
                        } catch (e) { /* ignore */ }
                        orig(...args);
                    };
                });
            },
        });

        cy.get('#listTable, .error-message', { timeout: 30000 }).should('exist');
        // Give the background prefetch plenty of time to walk all pages.
        cy.wait(25000);

        cy.document().then((doc) => {
            const countEl = doc.getElementById('recordCount');
            const domRows = doc.querySelectorAll('#listTable tbody tr').length;
            const out = {
                counterText: countEl ? countEl.textContent.trim() : '(no #recordCount)',
                domRows,
                relevantConsole: logs.slice(-40),
            };
            cy.writeFile('cypress/pager-stall-out.json', out);
            // eslint-disable-next-line no-console
            cy.log('COUNTER: ' + out.counterText + ' | domRows=' + domRows);
        });
    });
});
