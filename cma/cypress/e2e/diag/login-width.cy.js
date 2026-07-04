/**
 * READ-ONLY diag: measure why the CMA login box is too wide on mobile.
 * Run: CYPRESS_baseUrl=https://www.karaatedelstenen.nl/cma npx cypress run
 *      --spec cypress/e2e/diag/login-width.cy.js --browser electron
 */
describe('login width diag', () => {
    it('measures the login box on a phone viewport', () => {
        cy.viewport(390, 844);
        cy.visit('/login.php');
        cy.get('form#login div.kader, #login', { timeout: 20000 }).should('exist');
        cy.document().then((doc) => {
            const win = doc.defaultView;
            const q = (sel) => doc.querySelector(sel);
            const w = (el) => (el ? Math.round(el.getBoundingClientRect().width) : null);
            const kader = q('form#login div.kader') || q('.kader');
            const input = q('#txtLogin') || q('form#login input[type="text"]');
            const header = q('form#login .login-header');
            const form = q('form#login table#loginForm');
            const widest = [];
            doc.querySelectorAll('body *').forEach((el) => {
                const ww = Math.max(el.scrollWidth || 0, Math.round(el.getBoundingClientRect().width));
                if (ww > win.innerWidth + 5) {
                    widest.push({ tag: el.tagName, cls: (el.className || '').toString().slice(0, 40), w: ww });
                }
            });
            widest.sort((a, b) => b.w - a.w);
            const out = {
                innerWidth: win.innerWidth,
                docScrollWidth: doc.documentElement.scrollWidth,
                bodyScrollWidth: doc.body.scrollWidth,
                mqMatch600: win.matchMedia('(max-width: 600px)').matches,
                kaderWidth: w(kader),
                kaderCSSWidth: kader ? win.getComputedStyle(kader).width : null,
                inputWidth: w(input),
                inputCSSWidth: input ? win.getComputedStyle(input).width : null,
                headerWidth: w(header),
                formWidth: w(form),
                viewportMeta: (q('meta[name=viewport]') || {}).content || '(none)',
                overflowers: widest.slice(0, 8),
            };
            cy.writeFile('cypress/login-width-out.json', out);
        });
    });
});
