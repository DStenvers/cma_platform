/**
 * E-mail log form tests
 *
 * The emaillog form is read-mostly: allowAdd=false, allowEdit=false,
 * allowDelete=true. Rows are inserted server-side as a side-effect of the
 * mail-send hook. Coverage focuses on:
 *   - Load (tree + table modes)
 *   - Toolbar shape: delete + resend extra button, NO add/edit
 *   - List management: sort, search, listLimit
 *   - Detail view: all fields read-only
 *   - Delete API end-to-end
 *
 * Identity tripwire (verifyIdentity → form_identity_mismatch) watched throughout.
 */

describe('E-mail log form', () => {
    const FORM = 'emaillog';

    beforeEach(() => {
        cy.loginAsDeveloper();
        cy.startIdentityWatcher();
    });

    afterEach(() => {
        cy.assertNoIdentityMismatch();
    });

    describe('Form load', () => {
        it('loads the emaillog form in tree mode', () => {
            cy.openFormTree(FORM);
            cy.get('#listContent', { timeout: 10000 }).should('exist');
        });

        it('loads the emaillog form in table mode', () => {
            cy.openFormTable(FORM);
            cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');
        });

        it('displays the configured listColumns (Datum, Aan, Onderwerp, Status, Fout)', () => {
            cy.openFormTable(FORM);
            cy.get('#listTable thead, table.listtable thead').within(() => {
                cy.contains(/Datum/i).should('exist');
                cy.contains(/Aan/i).should('exist');
                cy.contains(/Onderwerp/i).should('exist');
                cy.contains(/Status/i).should('exist');
                cy.contains(/Fout/i).should('exist');
            });
        });
    });

    describe('Toolbar shape (allowAdd:false, allowEdit:false, allowDelete:true)', () => {
        it('shows the delete button and the redo (resend) extra button', () => {
            cy.openFormTree(FORM);
            cy.get('[data-action="delete"]').should('exist');
            cy.get('.extra-button').should('exist');
            cy.get('.extra-button .lnr-redo, .extra-button .lnr.lnr-redo').should('exist');
        });

        it('does NOT show add/copy buttons', () => {
            cy.openFormTable(FORM);
            cy.get('[data-action="add"]:visible, [data-action="addInline"]:visible').should('not.exist');
            cy.get('[data-action="copy"]:visible').should('not.exist');
        });
    });

    describe('List management', () => {
        it('respects listLimit=100 — list contains no more than 100 rows', () => {
            cy.openFormTable(FORM);
            cy.get('#listTable tbody tr, table.listtable tbody tr', { timeout: 15000 })
                .should('have.length.at.most', 100);
        });

        it('orders by ID DESC (newest first) — datestamp column non-increasing', () => {
            cy.openFormTable(FORM);
            cy.get('#listTable tbody tr, table.listtable tbody tr').then(($rows) => {
                if ($rows.length < 2) {
                    cy.log('Skipping ordering check — fewer than 2 rows');
                    return;
                }
                const dates = $rows.toArray().map((tr) => tr.cells[0]?.innerText?.trim() || '');
                const parsed = dates.map((d) => Date.parse(d.replace(/-/g, '/')) || 0);
                for (let i = 0; i < parsed.length - 1; i++) {
                    expect(parsed[i], `row ${i} >= row ${i + 1}`).to.be.gte(parsed[i + 1]);
                }
            });
        });

        it('filters rows when typing a substring of a "mail_to" value', () => {
            cy.openFormTable(FORM);
            cy.get('#listTable tbody tr, table.listtable tbody tr', { timeout: 15000 }).then(($rows) => {
                if ($rows.length === 0) {
                    cy.log('Skipping search — no rows present');
                    return;
                }
                // Column 1 is mail_to ("Aan").
                const cell = $rows.first().find('td').eq(1).text().trim();
                if (!cell) {
                    cy.log('Skipping search — first row mail_to is empty');
                    return;
                }
                const needle = cell.substring(0, Math.min(5, cell.length));
                cy.get('#searchBox, [data-cy="quick-search"], input[type="search"]', { timeout: 10000 })
                    .first()
                    .clear()
                    .type(needle);
                cy.get('#listTable tbody tr:visible, table.listtable tbody tr:visible')
                    .should('have.length.at.least', 1);
            });
        });
    });

    describe('Detail view — read-only', () => {
        it('opens a record and shows all fields as read-only', () => {
            cy.openFormTable(FORM);
            cy.get('#listTable tbody tr, table.listtable tbody tr', { timeout: 15000 }).then(($rows) => {
                if ($rows.length === 0) {
                    cy.log('Skipping detail — no rows');
                    return;
                }
                cy.wrap($rows.first()).click();
                cy.get('.detail-content, .form-layout, .lib_sidepanel_container', { timeout: 10000 })
                    .should('be.visible');

                // Every email field is readOnly: true per emaillog.json.
                cy.get('[name="mail_to"], [name="mail_subject"], [name="mail_status"], [name="mail_from"]').each(($el) => {
                    expect(
                        $el.is('[readonly]') || $el.is('[disabled]') || $el.prop('readOnly') === true,
                        `${$el.attr('name')} should be read-only`
                    ).to.be.true;
                });
            });
        });
    });

    describe('Delete API', () => {
        it('returns valid JSON (not a 500) for a non-existent record', () => {
            cy.request({
                method: 'GET',
                url: '/form_api.php?action=delete&form=emaillog&id=999999',
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(200);
                const body = typeof response.body === 'string'
                    ? JSON.parse(response.body)
                    : response.body;
                expect(body).to.have.property('success');
                expect(body.success).to.eq(false);
            });
        });

        it('returns valid JSON from the tree API', () => {
            cy.request({
                method: 'GET',
                url: '/form_api.php?action=tree&jsonForm=emaillog',
                failOnStatusCode: false,
            }).then((response) => {
                expect(response.status).to.eq(200);
                const body = typeof response.body === 'string'
                    ? JSON.parse(response.body)
                    : response.body;
                expect(body).to.have.property('success');
            });
        });
    });
});
