/**
 * Report Designer: robuustheid en veiligheid (v1.59.76)
 *
 *  - De tabellijst toont een fout met "Opnieuw proberen" in plaats van een spinner die blijft draaien, en leest een API-antwoord
 *    waar PHP-tekst voor de JSON staat.
 *  - Ruwe SQL in een rapportdefinitie wordt gecontroleerd voor preview, uitvoeren en opslaan: alleen één SELECT.
 *  - De QueryBuilder meldt SQL die niet kan (een tabel zonder relatie) in plaats van SQL die de database weigert.
 *  - De parser leest SQL zoals Access hem schrijft.
 *
 * Er wordt nooit iets echt gewijzigd: de "gevaarlijke" opdracht is een DELETE met WHERE 1=0 (raakt geen enkele rij, ook niet als een
 * oudere versie hem tóch uitvoert), en een opgeslagen testrapport begint met "Cypress" en wordt opgeruimd.
 */

describe('Report Designer: robuustheid en veiligheid', () => {
    const DB = Cypress.env('reportDatabase') || 6;
    let tabellen = [];

    function ruimOp() {
        cy.loginAsAdmin();
        cy.request('api/report-save.php?action=list').then((r) => {
            ((r.body && r.body.reports) || []).forEach((rep) => {
                if (rep.name && rep.name.startsWith('Cypress')) {
                    cy.request('POST', `api/report-save.php?action=delete&id=${rep.id}`);
                }
            });
        });
    }

    before(() => {
        ruimOp();
        cy.loginAsAdmin();
        cy.request(`api/report-schema.php?action=getTables&database=${DB}`).then((r) => {
            tabellen = (r.body.tables || []).map((t) => t.name).filter((n) => !n.startsWith('_') && /^\w+$/.test(n));
        });
    });
    after(ruimOp);

    function openGeavanceerd() {
        cy.loginAsAdmin();
        cy.visit('/report-designer.php');
        cy.wait(600);
        cy.get('.mode-option[data-mode="advanced"]').click();
    }

    describe('Tabellen laden', () => {
        it('toont een fout met "Opnieuw proberen" als de server een foutpagina geeft', () => {
            cy.intercept({ method: 'GET', url: '**/api/report-schema.php?action=getTables*' },
                { statusCode: 500, headers: { 'content-type': 'text/html' }, body: '<h1>Fatal error</h1> Call to undefined function' }).as('tabellen');
            openGeavanceerd();
            cy.wait('@tabellen');
            cy.get('.table-list-items', { timeout: 10000 }).should('contain.text', 'Tabellen laden mislukt');
            cy.get('.table-list-items').should('contain.text', 'HTTP 500');
            cy.get('.table-list-items button').should('contain.text', 'Opnieuw proberen');
            cy.get('#tableLoader').should('not.exist');
        });

        it('toont de reden uit een JSON-fout van de server', () => {
            cy.intercept({ method: 'GET', url: '**/api/report-schema.php?action=getTables*' },
                { statusCode: 400, body: { success: false, error: 'Database ID is verplicht' } });
            openGeavanceerd();
            cy.get('.table-list-items', { timeout: 10000 }).should('contain.text', 'Database ID is verplicht');
        });

        it('laadt de lijst na "Opnieuw proberen"', () => {
            cy.intercept({ method: 'GET', url: '**/api/report-schema.php?action=getTables*', times: 1 },
                { statusCode: 500, body: 'stuk' });
            openGeavanceerd();
            cy.get('.table-list-items button', { timeout: 10000 }).contains('Opnieuw proberen').click();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
        });

        it('leest het antwoord ook als er PHP-tekst voor de JSON staat', () => {
            cy.intercept({ method: 'GET', url: '**/api/report-schema.php?action=getTables*' }, (req) => {
                req.continue((res) => { res.body = '\nDeprecated: iets in x.php on line 3\n' + res.body; });
            });
            openGeavanceerd();
            cy.get('.table-list-items .table-list-item', { timeout: 20000 }).should('have.length.greaterThan', 0);
        });
    });

    describe('Ruwe SQL wordt gecontroleerd', () => {
        function gevaarlijk() {
            return `DELETE FROM [${tabellen[0]}] WHERE 1=0`;
        }

        it('preview voert geen DELETE uit', function () {
            if (!tabellen.length) { this.skip(); }
            cy.loginAsAdmin();
            cy.request({ method: 'POST', url: 'api/report-query.php?action=preview', failOnStatusCode: false,
                body: { definition: { database: DB, mode: 'sql', rawSql: gevaarlijk() } } }).then((r) => {
                expect(r.body.success).to.equal(false);
            });
        });

        it('executeRawSql weigert een DELETE', function () {
            if (!tabellen.length) { this.skip(); }
            cy.loginAsAdmin();
            cy.request({ method: 'POST', url: 'api/report-query.php?action=executeRawSql', failOnStatusCode: false,
                body: { sql: gevaarlijk(), database: DB } }).then((r) => {
                expect(r.status).to.equal(400);
                expect(r.body.success).to.equal(false);
            });
        });

        it('opslaan van een SQL-rapport met een DELETE wordt geweigerd', function () {
            if (!tabellen.length) { this.skip(); }
            cy.loginAsAdmin();
            cy.request({ method: 'POST', url: 'api/report-save.php?action=save', failOnStatusCode: false,
                body: { name: 'Cypress gevaarlijk', mode: 'sql', rawSql: gevaarlijk(), database: DB } }).then((r) => {
                expect(r.status).to.equal(400);
                expect(r.body.success).to.equal(false);
            });
        });

        it('een tekstwaarde met het woord update is geen opdracht', function () {
            if (!tabellen.length) { this.skip(); }
            cy.loginAsAdmin();
            cy.request({ method: 'POST', url: 'api/report-query.php?action=executeRawSql', failOnStatusCode: false,
                body: { sql: `SELECT TOP 1 * FROM [${tabellen[0]}] WHERE 'update' = 'delete'`, database: DB } }).then((r) => {
                expect(r.status).to.equal(200);
                expect(r.body.success).to.equal(true);
            });
        });

        it('een rapport-ID met padtekens wordt een veilig ID', () => {
            cy.loginAsAdmin();
            cy.request({ method: 'POST', url: 'api/report-save.php?action=save', failOnStatusCode: false,
                body: { id: '../../cypressx', name: 'Cypress id', database: DB, tables: [] } }).then((r) => {
                expect(r.body.success).to.equal(true);
                expect(r.body.id).to.equal('cypressx');
            });
        });
    });

    describe('SQL-opbouw en parser', () => {
        it('meldt een tabel zonder relatie in plaats van kapotte SQL', function () {
            if (tabellen.length < 2) { this.skip(); }
            cy.loginAsAdmin();
            cy.request({ method: 'POST', url: 'api/report-query.php?action=getSql', failOnStatusCode: false, body: { definition: {
                tables: [{ name: tabellen[0] }, { name: tabellen[1] }],
                fields: [{ table: tabellen[0], field: 'ID', visible: true }, { table: tabellen[1], field: 'ID', alias: 'ID2', visible: true }],
            } } }).then((r) => {
                expect(r.body.success).to.equal(false);
                expect(r.body.errors.join(' ')).to.contain('niet verbonden');
            });
        });

        it('FULL JOIN wordt een LEFT JOIN met een melding (Access kent hem niet)', function () {
            if (tabellen.length < 2) { this.skip(); }
            cy.loginAsAdmin();
            cy.request({ method: 'POST', url: 'api/report-query.php?action=getSql', failOnStatusCode: false, body: { definition: {
                tables: [{ name: 'tblA', joins: [{ table: 'tblB', type: 'FULL', on: '[tblA].[id] = [tblB].[fk]' }] }],
                fields: [{ table: 'tblA', field: 'x', visible: true }],
            } } }).then((r) => {
                expect(r.body.sql).to.contain('LEFT JOIN');
                expect(r.body.sql).not.to.contain('FULL');
                expect(r.body.errors.join(' ')).to.contain('Access');
            });
        });

        it('de parser behoudt de basistabel bij Access-SQL zonder blokhaken', () => {
            cy.loginAsAdmin();
            cy.request({ method: 'POST', url: 'api/report-query.php?action=parseSql', failOnStatusCode: false, body: {
                database: DB,
                sql: 'SELECT tblA.x FROM ((tblA INNER JOIN tblB ON tblA.id = tblB.fk) INNER JOIN tblC ON tblB.id = tblC.fk)',
            } }).then((r) => {
                expect(r.body.success).to.equal(true);
                const namen = r.body.parsed.tables.map((t) => t.name || t);
                expect(namen).to.deep.equal(['tblA', 'tblB', 'tblC']);
            });
        });

        it('de parser weigert een komma-join (SQL-modus) in plaats van een tabel te verliezen', () => {
            cy.loginAsAdmin();
            cy.request({ method: 'POST', url: 'api/report-query.php?action=parseSql', failOnStatusCode: false, body: {
                database: DB, sql: 'SELECT tblA.n FROM tblA, tblB WHERE tblA.id = tblB.fk',
            } }).then((r) => {
                expect(r.body.success).to.equal(false);
            });
        });
    });
});
