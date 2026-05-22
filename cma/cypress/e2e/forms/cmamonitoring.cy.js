/**
 * CMA Monitoring Form — list-management tests.
 *
 * cmamonitoring is read-only (allowAdd/Delete/Copy/Edit all false). It's an
 * audit log: rows are created server-side as side-effects of other actions.
 *
 * Coverage focuses on list management, since CRUD is not applicable:
 *   - List loads with columns from listColumns
 *   - Ordering (orderField=ID, orderDirection=DESC)
 *   - Quick search across Username/Formname/Form/Notificatie
 *   - Row click → detail with read-only fields
 *   - Add/Delete buttons are NOT present (allowAdd/Delete = false)
 *   - listLimit (200) is respected — list shouldn't break with many rows
 *
 * Identity tripwire watched throughout — verifyIdentity() must stay quiet
 * even though the form is read-only (the tripwire runs on init/load).
 */

describe('CMA Monitoring Form (read-only list)', () => {
    const FORM = 'cmamonitoring';

    beforeEach(() => {
        cy.loginAsAdmin();
        cy.startIdentityWatcher();
    });

    afterEach(() => {
        cy.assertNoIdentityMismatch();
    });

    describe('List view', () => {
        it('loads the monitoring form in table view', () => {
            cy.openFormTable(FORM);
            cy.get('#listTable, table.listtable', { timeout: 15000 }).should('be.visible');
        });

        it('displays the configured listColumns (Datum, Gebruiker, Formulier, Actie, Melding)', () => {
            cy.openFormTable(FORM);
            cy.get('#listTable thead, table.listtable thead').within(() => {
                cy.contains(/Datum/i).should('exist');
                cy.contains(/Gebruiker/i).should('exist');
                cy.contains(/Formulier/i).should('exist');
                cy.contains(/Actie/i).should('exist');
                cy.contains(/Melding/i).should('exist');
            });
        });

        it('does NOT show add/delete/copy buttons (allowAdd/Delete/Copy all false)', () => {
            cy.openFormTable(FORM);
            // Whichever toolbar exists, these data-action buttons should not be visible.
            // (They may exist disabled or simply not be rendered — assert no visible ones.)
            cy.get('[data-action="add"]:visible, [data-action="addInline"]:visible').should('not.exist');
            cy.get('[data-action="delete"]:visible').should('not.exist');
            cy.get('[data-action="copy"]:visible').should('not.exist');
        });
    });

    describe('Ordering (orderField=ID DESC)', () => {
        it('shows newest rows first when present', () => {
            cy.openFormTable(FORM);
            cy.get('#listTable tbody tr, table.listtable tbody tr').then(($rows) => {
                if ($rows.length < 2) {
                    cy.log('Skipping ordering check — fewer than 2 rows in tblCMAMonitoring');
                    return;
                }
                // The 'Datum' column should be monotonically non-increasing.
                // Extract first column text values and verify each is >= the next.
                const dates = $rows.toArray().map((tr) => tr.cells[0]?.innerText?.trim() || '');
                const parsed = dates.map((d) => Date.parse(d.replace(/-/g, '/')) || 0);
                for (let i = 0; i < parsed.length - 1; i++) {
                    expect(parsed[i], `row ${i} date should be >= row ${i + 1}`).to.be.gte(parsed[i + 1]);
                }
            });
        });
    });

    describe('Quick search', () => {
        it('filters rows when typing a value present in the table', () => {
            cy.openFormTable(FORM);
            cy.get('#listTable tbody tr, table.listtable tbody tr', { timeout: 15000 }).then(($rows) => {
                if ($rows.length === 0) {
                    cy.log('Skipping search test — no monitoring rows exist yet');
                    return;
                }
                // Pull a substring from the Gebruiker column of the first row.
                const userCell = $rows.first().find('td').eq(1).text().trim();
                if (!userCell) {
                    cy.log('Skipping — first row has no Username value');
                    return;
                }
                const needle = userCell.substring(0, Math.min(4, userCell.length));

                cy.get('#searchBox, [data-cy="quick-search"], input[type="search"]', { timeout: 10000 })
                    .first()
                    .clear()
                    .type(needle);

                // After search, every visible row's Username (col 1) should contain the needle.
                cy.get('#listTable tbody tr:visible, table.listtable tbody tr:visible')
                    .should('have.length.at.least', 1)
                    .each(($r) => {
                        const cellText = $r.find('td').eq(1).text();
                        // case-insensitive contains
                        expect(cellText.toLowerCase()).to.include(needle.toLowerCase());
                    });
            });
        });

        it('shows an empty-state when search yields no results', () => {
            cy.openFormTable(FORM);
            const noMatch = `__cy_no_match_${Date.now()}__`;
            cy.get('#searchBox, [data-cy="quick-search"], input[type="search"]', { timeout: 10000 })
                .first()
                .clear()
                .type(noMatch);

            // Either: zero visible rows, OR an empty-state message.
            cy.get('body').then(($body) => {
                const visibleRows = $body.find('#listTable tbody tr:visible, table.listtable tbody tr:visible').length;
                if (visibleRows > 0) {
                    // Allow the case where the empty-state is a placeholder ROW with a "no data" message.
                    cy.get('#listTable tbody, table.listtable tbody').then(($tb) => {
                        const txt = $tb.text();
                        expect(txt.length === 0 || /Geen gegevens|No data|0 records/i.test(txt))
                            .to.be.true;
                    });
                }
            });
        });
    });

    describe('Row → read-only detail', () => {
        it('opens a record in read-only mode (all fields disabled)', () => {
            cy.openFormTable(FORM);
            cy.get('#listTable tbody tr, table.listtable tbody tr', { timeout: 15000 }).then(($rows) => {
                if ($rows.length === 0) {
                    cy.log('Skipping detail test — no monitoring rows exist');
                    return;
                }
                cy.wrap($rows.first()).click();
                cy.get('.detail-content, .form-layout, .lib_sidepanel_container', { timeout: 10000 })
                    .should('be.visible');

                // All listed text fields are readOnly: true → DOM should have readonly/disabled.
                cy.get('[name="Username"], [name="Form"], [name="Actie"], [name="datestamp"]').each(($el) => {
                    expect(
                        $el.is('[readonly]') || $el.is('[disabled]') || $el.prop('readOnly') === true,
                        `${$el.attr('name')} should be read-only`
                    ).to.be.true;
                });
            });
        });
    });

    describe('listLimit', () => {
        it('respects listLimit=200 — list contains no more than 200 rows', () => {
            cy.openFormTable(FORM);
            cy.get('#listTable tbody tr, table.listtable tbody tr', { timeout: 15000 })
                .should('have.length.at.most', 200);
        });
    });
});
