/**
 * Route → Layout regression tests
 *
 * Walks every kind of route the CMA exposes and asserts the DESIRED LAYOUT is
 * actually rendered for it. Guards the recurring class of bugs where a route
 * resolves but shows the wrong thing (e.g. a record deep link that renders the
 * list only, or a form reachable under two different routes/layouts).
 *
 * Route map verified against cma/web.config (rewrite rules), Cma\FormRoute
 * (server contract) and tools.php ($toolNameMap / $formBackedTools).
 */

describe('Route → layout (regression)', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    // ── Top-level shell routes ────────────────────────────────────────────
    describe('Shell pages', () => {
        it('/cma/ redirects to the dashboard', () => {
            cy.visit('/');
            cy.url().should('include', '/dashboard');
            cy.get('.dashboard-container, .dashboard-card, .stats-card', { timeout: 15000 }).should('exist');
        });

        it('/cma/dashboard shows the dashboard layout', () => {
            cy.visit('/dashboard');
            cy.url().should('not.include', 'login');
            cy.get('#contentArea', { timeout: 15000 }).should('exist');
            cy.get('.dashboard-container, .dashboard-card, .stats-card', { timeout: 15000 }).should('exist');
        });

        it('/cma/preferences shows the preferences form', () => {
            cy.visit('/preferences');
            cy.url().should('not.include', 'login');
            cy.get('#preferencesForm, #theme, #menuStyle', { timeout: 15000 }).should('exist');
        });

        it('/cma/tools shows the tools tree layout', () => {
            cy.visit('/tools');
            cy.url().should('not.include', 'login');
            cy.get('#tools-tree, cma-tree', { timeout: 15000 }).should('exist');
        });
    });

    // ── Form list routes ──────────────────────────────────────────────────
    describe('Form list routes /cma/form/<form>', () => {
        const forms = ['users', 'groups', 'opleidingen'];

        forms.forEach(form => {
            it(`${form}: list layout (form-layout + a list)`, () => {
                cy.visit(`/form/${form}`);
                cy.url().should('not.include', 'login');
                cy.get('#contentArea .form-layout', { timeout: 20000 }).should('exist');
                // A list must be present (tree, table or lib-table)
                cy.get('#leftlist, #listTable, #listContent, table.listtable, lib-table, cma-tree', { timeout: 20000 })
                    .should('exist');
                // URL stays the clean list route (no record id)
                cy.url().should('match', new RegExp(`/cma/form/${form}/?$`));
            });
        });
    });

    // ── Form record deep links — the recurring bug ────────────────────────
    // /cma/form/<form>/<id> must open the record in the detail panel WITH the
    // list visible (the active view), not list-only and not a sidepanel.
    describe('Form record deep links /cma/form/<form>/<id>', () => {
        const forms = ['users', 'groups', 'opleidingen'];

        forms.forEach(form => {
            it(`${form}: opens the record next to the list`, () => {
                cy.request({
                    method: 'POST', url: 'form_api.php', form: true,
                    body: { action: 'list', jsonForm: form, limit: 1 }
                }).then(res => {
                    const rows = res.body && res.body.data;
                    if (!rows || !rows.length) { cy.log(`No ${form} records — skipping`); return; }
                    const id = rows[0].ID || rows[0].id;
                    if (!id || String(id) === '0') { cy.log('No usable id — skipping'); return; }

                    cy.visit(`/form/${form}/${id}`);

                    // URL keeps the record id
                    cy.url().should('include', `/form/${form}/${id}`);

                    // The list is still visible (active view) — body carries a list mode
                    cy.get('body').should($b => {
                        expect($b.hasClass('mode-tree') || $b.hasClass('mode-table'),
                            'body has mode-tree or mode-table').to.be.true;
                    });
                    cy.get('#leftlist, #listTable, #listContent, table.listtable, lib-table, cma-tree', { timeout: 20000 })
                        .should('exist');

                    // The record detail is loaded
                    cy.get('body').should('have.class', 'has-record');
                    cy.get('#mainForm, .detail-content, #detailContent', { timeout: 20000 }).should('exist');

                    // A top-level record deep link must NOT open a sidepanel
                    cy.get('.lib_sidepanel_container').should('not.exist');
                });
            });
        });
    });

    // ── User & group management specifically ──────────────────────────────
    describe('User / group management', () => {
        ['users', 'groups'].forEach(form => {
            it(`/cma/form/${form} renders the ${form} admin form`, () => {
                cy.visit(`/form/${form}`);
                cy.get('#contentArea .form-layout', { timeout: 20000 }).should('exist');
            });
        });
    });

    // ── Tool friendly URLs load (no 403/404) ──────────────────────────────
    describe('Tool routes /cma/tools/<name>', () => {
        const tools = [
            'serverinfo', 'logreader', 'dbsummary', 'db_consistency',
            'documentation', 'storybook', 'query', 'migrations', 'backup'
        ];

        tools.forEach(tool => {
            it(`${tool}: tools layout with the tool loaded`, () => {
                cy.visit(`/tools.php?tool=${tool}`);
                cy.url().should('not.include', 'login');
                cy.get('#tools-tree, cma-tree', { timeout: 15000 }).should('exist');
                // Tool content loads in the tools iframe
                cy.get('#details_iframe, #tools-content', { timeout: 15000 })
                    .should('have.attr', 'src').and('match', /\.php/);
            });
        });
    });

    // ── Form-backed tool aliases collapse to ONE canonical form route ──────
    // Regression for the route-dedup: a form-backed tool alias must redirect to
    // /cma/form/<form>, never render the form inside the tools iframe.
    describe('Form-backed tool aliases redirect to the form route', () => {
        const aliases = [
            { alias: 'users', form: 'users' },
            { alias: 'gebruikers', form: 'users' },
            { alias: 'groups', form: 'groups' },
            { alias: 'groepen', form: 'groups' },
            { alias: 'contentblocks', form: 'contentblocks' },
            { alias: 'marketingurl', form: 'marketingurl' },
            { alias: 'monitoring', form: 'cmamonitoring' }
        ];

        aliases.forEach(({ alias, form }) => {
            it(`?tool=${alias} → /cma/form/${form}`, () => {
                cy.visit(`/tools.php?tool=${alias}`);
                // Collapses to the canonical form route, leaving the tools URL
                cy.url({ timeout: 15000 }).should('include', `/form/${form}`);
                cy.url().should('not.match', /tool=/);
                cy.get('#contentArea .form-layout, #contentArea lib-table, #contentArea table.listtable', { timeout: 20000 })
                    .should('exist');
            });
        });
    });
});
