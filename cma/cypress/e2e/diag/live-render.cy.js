/**
 * READ-ONLY live render diagnostic (not part of the suite; run on demand).
 * Logs in, opens pages, measures layout/overflow, writes JSON + screenshots.
 * No clicks on destructive controls.
 */
describe('live render diag', () => {
    const U = Cypress.env('u');
    const P = Cypress.env('p');

    function login() {
        cy.request({ method: 'POST', url: '/login.php', form: true, followRedirect: true,
            body: { naam: U, wachtwoord: P } });
    }

    it('steensoorten: horizontal overflow (10% zoom) + sidebar visibility', () => {
        login();
        cy.visit('/form/steensoorten');
        cy.get('.form-layout, #listTable, .detail-content, .error-message', { timeout: 30000 }).should('exist');
        cy.wait(5000); // let subform + pager settle
        cy.document().then((doc) => {
            const win = doc.defaultView;
            const vw = win.innerWidth;
            const de = doc.documentElement;
            const meta = doc.querySelector('meta[name=viewport]');
            const widest = [];
            doc.querySelectorAll('body *').forEach((el) => {
                const w = Math.max(el.scrollWidth || 0, el.offsetWidth || 0);
                if (w > vw + 40) {
                    widest.push({ tag: el.tagName, id: el.id || '',
                        cls: (el.className || '').toString().slice(0, 70), w });
                }
            });
            widest.sort((a, b) => b.w - a.w);
            const sb = doc.getElementById('sidebar');
            const r = sb ? sb.getBoundingClientRect() : null;
            const cs = sb ? win.getComputedStyle(sb) : null;
            const count = doc.getElementById('recordCount');
            const out = {
                innerWidth: vw,
                documentScrollWidth: de.scrollWidth,
                bodyScrollWidth: doc.body.scrollWidth,
                horizontalOverflow: de.scrollWidth - vw,
                devicePixelRatio: win.devicePixelRatio,
                viewportMeta: meta ? meta.content : '(none)',
                widestElements: widest.slice(0, 12),
                sidebar: r ? { left: Math.round(r.left), width: Math.round(r.width),
                    display: cs.display, visibility: cs.visibility, transform: cs.transform,
                    opacity: cs.opacity } : 'NOT FOUND',
                recordCount: count ? count.textContent.trim() : '(none)',
            };
            cy.writeFile('cypress/diag-out.json', out);
        });
        cy.screenshot('steensoorten-live', { capture: 'fullPage', overwrite: true });
    });
});
