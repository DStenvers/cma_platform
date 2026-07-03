/**
 * Navigation / routing variants — thorough coverage.
 *
 * For EVERY way a form or tool can be opened, this spec asserts BOTH:
 *   (a) the correct content is displayed, and
 *   (b) the URL is set correctly.
 *
 * Design under test (v1.28.50):
 *   - Forms are callable TWO ways:
 *       • canonical route  /cma/form/<form>        -> main shell (sidebar + form)
 *       • via tools page   /cma/tools?form=<form>  -> tools chrome (Menu button)
 *                                                     with form.php in the R iframe
 *   - Tools open via       /cma/tools?tool=<tool>  -> tools chrome + tool iframe
 *   - The tools launcher (mega-menu) routes:
 *       • form tiles -> /cma/tools?form=<form>
 *       • tool tiles -> /cma/tools?tool=<tool>
 *
 * baseUrl is .../cma, so cy.visit('/form/groups') hits /cma/form/groups.
 */

const FORMS = ['groups', 'users', 'marketingurl', 'emaillog'];
const TOOLS = [
    { key: 'serverinfo', srcIncludes: 'tools_serverinfo' },
    { key: 'clearcache', srcIncludes: 'tools_clearcache' },
];

const FORM_CONTENT = '.form-layout, #listTable, table.listtable, .detail-content, .error-message';

describe('Navigation / routing variants', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    // ------------------------------------------------------------------
    // 1. Canonical form route: /cma/form/<form>  (main shell)
    // ------------------------------------------------------------------
    describe('Canonical form route (/cma/form/<form>)', () => {
        FORMS.forEach((form) => {
            it(`opens ${form} in the main shell with a clean /cma/form/${form} URL`, () => {
                cy.visit(`/form/${form}`);
                cy.get(FORM_CONTENT, { timeout: 30000 }).should('exist');
                cy.location('pathname').should('match', new RegExp(`/cma/form/${form}(/|$)`));
                // This is the main-shell form, NOT the tools iframe.
                cy.get('#tools-content').should('not.exist');
            });
        });
    });

    // ------------------------------------------------------------------
    // 2. Form via the tools page: /cma/tools?form=<form>  (tools chrome)
    //    This is the variant behind "if I open a form from the menu, all
    //    I see is the form" — the tools Menu button MUST still be present.
    // ------------------------------------------------------------------
    describe('Form via tools page (/cma/tools?form=<form>)', () => {
        FORMS.forEach((form) => {
            it(`opens ${form} inside the tools chrome (Menu button present)`, () => {
                cy.visit(`/tools?form=${form}`);
                // Tools chrome must render — this is the whole point of routing
                // forms through tools.php.
                cy.get('#toolsMenuBtn', { timeout: 30000 }).should('exist');
                // The form loads in the tools iframe (form.php?form=<form>).
                cy.get('#tools-content', { timeout: 30000 })
                    .should('have.attr', 'src')
                    .and('include', `form=${form}`);
                cy.location('search').should('include', `form=${form}`);
            });
        });
    });

    // ------------------------------------------------------------------
    // 3. Tool via the tools page: /cma/tools?tool=<tool>
    // ------------------------------------------------------------------
    describe('Tool via tools page (/cma/tools?tool=<tool>)', () => {
        TOOLS.forEach((tool) => {
            it(`opens ${tool.key} inside the tools chrome`, () => {
                cy.visit(`/tools?tool=${tool.key}`);
                cy.get('#toolsMenuBtn', { timeout: 30000 }).should('exist');
                cy.get('#tools-content', { timeout: 30000 })
                    .should('have.attr', 'src')
                    .and('include', tool.srcIncludes);
                cy.location('search').should('include', `tool=${tool.key}`);
            });
        });
    });

    // ------------------------------------------------------------------
    // 4. Opening from the tools launcher (mega-menu)
    // ------------------------------------------------------------------
    describe('Opening from the tools launcher', () => {
        function openLauncher() {
            // /cma/tools with no ?tool= auto-opens the launcher overlay.
            cy.visit('/tools');
            cy.get('.cma-launcher__panel, cma-launcher.is-open', { timeout: 30000 })
                .should('exist');
            cy.get('.cma-launcher__item', { timeout: 30000 }).should('have.length.greaterThan', 0);
        }

        it('quick-find filters the item list (regression guard)', () => {
            openLauncher();
            cy.get('.cma-launcher__search').type('cache');
            cy.get('.cma-launcher__item:not([hidden])').should('have.length.greaterThan', 0);
            cy.get('.cma-launcher__group:not([hidden]) .cma-launcher__item:not([hidden])').each(($el) => {
                expect(($el.attr('data-search') || '')).to.include('cache');
            });
        });

        it('a FORM tile routes through tools.php (URL gets ?form=, chrome present)', () => {
            openLauncher();
            cy.get('.cma-launcher__item[data-url*="form=groups"]', { timeout: 30000 })
                .first()
                .click();
            cy.get('#toolsMenuBtn', { timeout: 30000 }).should('exist');
            cy.get('#tools-content', { timeout: 30000 })
                .should('have.attr', 'src')
                .and('include', 'form=groups');
            cy.location('href').should('include', 'form=groups');
        });

        it('a TOOL tile routes to /cma/tools?tool=', () => {
            openLauncher();
            cy.get('.cma-launcher__search').type('server');
            cy.get('.cma-launcher__group:not([hidden]) .cma-launcher__item:not([hidden])', { timeout: 30000 })
                .first()
                .click();
            cy.get('#toolsMenuBtn', { timeout: 30000 }).should('exist');
            cy.get('#tools-content', { timeout: 30000 }).should('have.attr', 'src');
            cy.location('search').should('include', 'tool=');
        });

        it('clicking the Menu button re-opens (toggle) the launcher', () => {
            cy.visit('/tools?tool=serverinfo');
            cy.get('#toolsMenuBtn', { timeout: 30000 }).click();
            cy.get('.cma-launcher__panel', { timeout: 10000 }).should('exist');
            // A second click closes it.
            cy.get('#toolsMenuBtn').click();
            cy.get('.cma-launcher__panel').should('not.exist');
        });
    });

    // ------------------------------------------------------------------
    // 5. Forms reachable BOTH ways (the explicit requirement)
    // ------------------------------------------------------------------
    describe('Forms are callable both ways', () => {
        FORMS.forEach((form) => {
            it(`${form}: canonical route AND tools-page route both render it`, () => {
                cy.visit(`/form/${form}`);
                cy.get(FORM_CONTENT, { timeout: 30000 }).should('exist');
                cy.get('#tools-content').should('not.exist');

                cy.visit(`/tools?form=${form}`);
                cy.get('#toolsMenuBtn', { timeout: 30000 }).should('exist');
                cy.get('#tools-content', { timeout: 30000 })
                    .should('have.attr', 'src')
                    .and('include', `form=${form}`);
            });
        });
    });

    // ------------------------------------------------------------------
    // 6. Opening a record reflects the id in the URL
    // ------------------------------------------------------------------
    describe('Opening a record updates the URL', () => {
        it('groups: selecting a row puts the record id in the /cma/form/groups/<id> URL', () => {
            cy.visit('/form/groups');
            cy.get('#listTable tbody tr, table.listtable tbody tr', { timeout: 30000 })
                .first()
                .click();
            cy.get('.detail-content, .lib_sidepanel_container', { timeout: 15000 }).should('be.visible');
            cy.location('pathname').should('match', /\/cma\/form\/groups\/\d+/);
        });
    });
});
