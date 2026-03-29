/**
 * Report Designer SQL Editing E2E Tests
 *
 * Tests the integrated SQL editing functionality in the preview panel:
 * - Editable SQL preview
 * - SQL-only mode fallback
 * - SQL parsing and sync to visual editor
 */

describe('Report Designer - SQL Editing', () => {
    beforeEach(() => {
        // Login using standard command
        cy.loginAsAdmin();

        // Navigate to report designer
        cy.visit('/report-designer.php');

        // Wait for page to fully load and dialog to appear
        cy.get('lib-dialog#modeDialog', { timeout: 15000 }).should('have.attr', 'open');

        // Dismiss any tips/tours that may be blocking interactions
        cy.window().then(win => {
            if (win.LibTip && typeof win.LibTip.close === 'function') {
                win.LibTip.close();
            }
        });

        // Select advanced mode and wait for tables to load
        cy.get('.mode-option[data-mode="advanced"]').click();
        cy.get('lib-dialog#modeDialog').should('not.have.attr', 'open');
    });

    /**
     * Helper: select a table, wait for schema and SQL generation,
     * then switch to the Results tab where cma-query-preview lives.
     */
    function selectTableAndSwitchToResults() {
        // Wait for table list items to appear with generous timeout
        cy.get('.table-list-items .table-list-item', { timeout: 20000 })
            .should('have.length.greaterThan', 0);

        // Click the first table
        cy.get('.table-list-items .table-list-item').first().click();

        // Wait for schema to load on canvas (confirms the table fetch completed)
        cy.get('cma-schema-canvas').shadow()
            .find('.schema-table', { timeout: 20000 }).should('exist');

        // Wait for field data rows to populate (data rows have data-index attribute)
        cy.get('cma-field-config .field-config-table tbody tr[data-index]', { timeout: 25000 })
            .should('have.length.greaterThan', 0);

        // Switch to the Results tab where cma-query-preview lives
        cy.window().then(win => {
            const mainTabs = win.document.getElementById('mainTabs');
            if (mainTabs) {
                mainTabs.selectTab(1);
            }
        });

        // Wait for the results tab content to be visible
        cy.get('#resultsTabContent', { timeout: 10000 }).should('be.visible');

        // Wait for SQL to be generated and set on the preview component
        // The SQL is generated asynchronously via an API call
        cy.get('cma-query-preview#resultsQueryPreview .sql-code', { timeout: 15000 })
            .should('not.contain', 'Selecteer tabellen');
    }

    describe('Mode Selection', () => {
        it('should have four mode options including SQL mode', () => {
            cy.visit('/report-designer.php');
            cy.get('lib-dialog#modeDialog', { timeout: 15000 }).should('have.attr', 'open');
            // 4 options: load, quick, advanced, sql
            cy.get('.mode-option').should('have.length', 4);
        });

        it('should have a SQL mode option for pasting existing SQL', () => {
            cy.visit('/report-designer.php');
            cy.get('.mode-option[data-mode="sql"]').should('exist');
            cy.get('.mode-option[data-mode="sql"]').should('contain', 'SQL');
        });
    });

    describe('Editable SQL Preview', () => {
        beforeEach(() => {
            selectTableAndSwitchToResults();
        });

        it('should have editable attribute on preview component', () => {
            cy.get('cma-query-preview#resultsQueryPreview')
                .should('have.attr', 'editable');
        });

        it('should display SQL toolbar with edit button', () => {
            cy.get('cma-query-preview#resultsQueryPreview .toolbar')
                .should('be.visible');
            cy.get('cma-query-preview#resultsQueryPreview #editBtn')
                .should('be.visible');
        });

        it('should display execute button in toolbar', () => {
            cy.get('cma-query-preview#resultsQueryPreview #executeBtn')
                .should('be.visible');
        });

        it('should have execute button enabled when SQL is present', () => {
            // The component uses CSS class 'disabled' on span, not the disabled attribute
            cy.get('cma-query-preview#resultsQueryPreview #executeBtn')
                .should('not.have.class', 'disabled');
        });

        it('should have copy button enabled when SQL is present', () => {
            cy.get('cma-query-preview#resultsQueryPreview #copyBtn')
                .should('not.have.class', 'disabled');
        });

        it('should hide save and cancel buttons initially', () => {
            cy.get('cma-query-preview#resultsQueryPreview #saveEditBtn')
                .should('not.be.visible');
            cy.get('cma-query-preview#resultsQueryPreview #cancelEditBtn')
                .should('not.be.visible');
        });

        it('should enter edit mode when clicking edit button', () => {
            cy.get('cma-query-preview#resultsQueryPreview #editBtn a').click();

            // Edit button should be hidden
            cy.get('cma-query-preview#resultsQueryPreview #editBtn')
                .should('not.be.visible');

            // Save and cancel should be visible
            cy.get('cma-query-preview#resultsQueryPreview #saveEditBtn')
                .should('be.visible');
            cy.get('cma-query-preview#resultsQueryPreview #cancelEditBtn')
                .should('be.visible');
        });

        it('should enter edit mode when double-clicking SQL code', () => {
            cy.get('cma-query-preview#resultsQueryPreview .sql-code').dblclick();

            // Should be in edit mode - textarea visible, edit button hidden
            cy.get('cma-query-preview#resultsQueryPreview .sql-textarea')
                .should('be.visible');
            cy.get('cma-query-preview#resultsQueryPreview #editBtn')
                .should('not.be.visible');
            cy.get('cma-query-preview#resultsQueryPreview #saveEditBtn')
                .should('be.visible');
            cy.get('cma-query-preview#resultsQueryPreview #cancelEditBtn')
                .should('be.visible');
        });

        it('should show textarea in edit mode', () => {
            cy.get('cma-query-preview#resultsQueryPreview #editBtn a').click();

            cy.get('cma-query-preview#resultsQueryPreview .sql-textarea')
                .should('be.visible');
        });

        it('should hide SQL code display in edit mode', () => {
            cy.get('cma-query-preview#resultsQueryPreview #editBtn a').click();

            cy.get('cma-query-preview#resultsQueryPreview .sql-code')
                .should('not.be.visible');
        });

        it('should allow editing SQL in textarea', () => {
            cy.get('cma-query-preview#resultsQueryPreview #editBtn a').click();

            cy.get('cma-query-preview#resultsQueryPreview .sql-textarea')
                .clear()
                .type('SELECT TOP 10 * FROM [tblUsers]');

            cy.get('cma-query-preview#resultsQueryPreview .sql-textarea')
                .should('have.value', 'SELECT TOP 10 * FROM [tblUsers]');
        });

        it('should exit edit mode and revert changes when clicking cancel', () => {
            // Enter edit mode
            cy.get('cma-query-preview#resultsQueryPreview #editBtn a').click();

            // Make changes
            cy.get('cma-query-preview#resultsQueryPreview .sql-textarea')
                .clear()
                .type('SELECT * FROM [different_table]');

            // Cancel
            cy.get('cma-query-preview#resultsQueryPreview #cancelEditBtn a').click();

            // Should exit edit mode - edit button visible again
            cy.get('cma-query-preview#resultsQueryPreview #editBtn')
                .should('be.visible');

            // SQL code display should show original (not the edited version)
            cy.get('cma-query-preview#resultsQueryPreview .sql-code')
                .should('be.visible');
        });

        it('should apply changes when clicking save button', () => {
            // Enter edit mode
            cy.get('cma-query-preview#resultsQueryPreview #editBtn a').click();

            // Make changes
            const newSql = 'SELECT TOP 5 id, name FROM [tblUsers]';
            cy.get('cma-query-preview#resultsQueryPreview .sql-textarea')
                .clear()
                .type(newSql);

            // Save
            cy.get('cma-query-preview#resultsQueryPreview #saveEditBtn a').click();

            // Should exit edit mode
            cy.get('cma-query-preview#resultsQueryPreview #editBtn')
                .should('be.visible');

            // SQL code should show new SQL
            cy.get('cma-query-preview#resultsQueryPreview .sql-code')
                .should('contain', 'SELECT');
        });

        it('should support Tab key for indentation in edit mode', () => {
            cy.get('cma-query-preview#resultsQueryPreview #editBtn a').click();

            cy.get('cma-query-preview#resultsQueryPreview .sql-textarea')
                .clear()
                .type('SELECT{enter}')
                .trigger('keydown', { key: 'Tab' });

            cy.get('cma-query-preview#resultsQueryPreview .sql-textarea')
                .should('contain.value', '  ');
        });
    });

    describe('SQL Preview Content', () => {
        beforeEach(() => {
            selectTableAndSwitchToResults();
        });

        it('should show SQL with syntax highlighting', () => {
            cy.get('cma-query-preview#resultsQueryPreview .sql-code .keyword')
                .should('exist');
        });

        it('should have copy button', () => {
            cy.get('cma-query-preview#resultsQueryPreview #copyBtn')
                .should('exist');
        });

        it('should copy SQL when clicking copy button', () => {
            // The component uses navigator.clipboard with a fallback to execCommand.
            // In headless mode, clipboard may be undefined so the fallback runs.
            // Either way, the button text changes to "Gekopieerd!" on success.
            cy.get('cma-query-preview#resultsQueryPreview #copyBtn a').click();

            cy.get('cma-query-preview#resultsQueryPreview #copyBtn', { timeout: 5000 })
                .should('contain', 'Gekopieerd');
        });
    });

    describe('Query Execution', () => {
        beforeEach(() => {
            selectTableAndSwitchToResults();
        });

        it('should execute query and show data in data tab', () => {
            // Click execute - this fires a refresh event which calls runResultsQuery
            cy.get('cma-query-preview#resultsQueryPreview #executeBtn a').click();

            // Wait for loading to start (the loading state appears briefly)
            cy.wait(500);

            // Switch to data tab - this also triggers a refresh if no data yet
            cy.get('cma-query-preview#resultsQueryPreview .tabs-list li[data-tab="data"]').click();

            // Wait for loading to finish - the loading-state should become hidden
            cy.get('cma-query-preview#resultsQueryPreview #loadingState', { timeout: 20000 })
                .should('have.class', 'hidden');

            // After execution, data tab should show either results table or empty state
            // (depends on whether test database has data in the selected table)
            cy.get('cma-query-preview#resultsQueryPreview #dataTableContainer', { timeout: 5000 })
                .should('not.be.empty');
        });
    });

    describe('SQL-Only Mode', () => {
        beforeEach(() => {
            selectTableAndSwitchToResults();
        });

        it('should not show SQL-only indicator by default', () => {
            cy.get('cma-query-preview#resultsQueryPreview .sql-only-indicator')
                .should('not.be.visible');
        });

        it('should not show sync button when not in SQL-only mode', () => {
            cy.get('cma-query-preview#resultsQueryPreview #syncBtn')
                .should('not.be.visible');
        });
    });

    describe('SQL Parser API', () => {
        it('should successfully parse simple SELECT query', () => {
            cy.request({
                method: 'POST',
                url: 'api/report-query.php?action=parseSql',
                body: {
                    sql: 'SELECT id, name FROM [tblUsers] ORDER BY name ASC',
                    database: 1
                },
                headers: {
                    'Content-Type': 'application/json'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.parsed).to.exist;
                expect(response.body.parsed.tables).to.include('tblUsers');
                expect(response.body.parsed.fields).to.have.length.greaterThan(0);
            });
        });

        it('should parse tables from JOIN clauses', () => {
            cy.request({
                method: 'POST',
                url: 'api/report-query.php?action=parseSql',
                body: {
                    sql: 'SELECT u.id, g.name FROM [tblUsers] u LEFT JOIN [tblGroups] g ON u.groupId = g.id',
                    database: 1
                },
                headers: {
                    'Content-Type': 'application/json'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.parsed.tables).to.include('tblUsers');
                expect(response.body.parsed.tables).to.include('tblGroups');
            });
        });

        it('should parse tables from MS Access nested JOIN syntax', () => {
            // MS Access requires nested parentheses for multiple JOINs
            cy.request({
                method: 'POST',
                url: 'api/report-query.php?action=parseSql',
                body: {
                    sql: 'SELECT [tblAgenda].[Omschrijving] FROM (([tblAgenda] INNER JOIN [tblOpleidingen] ON [tblAgenda].[fkOpleiding] = [tblOpleidingen].[ID]) INNER JOIN [tblAgendaDownloads] ON [tblAgendaDownloads].[fkAgenda] = [tblAgenda].[ID])',
                    database: 1
                },
                headers: {
                    'Content-Type': 'application/json'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.parsed.tables).to.include('tblAgenda');
                expect(response.body.parsed.tables).to.include('tblOpleidingen');
                expect(response.body.parsed.tables).to.include('tblAgendaDownloads');
            });
        });

        it('should parse ORDER BY clause', () => {
            cy.request({
                method: 'POST',
                url: 'api/report-query.php?action=parseSql',
                body: {
                    sql: 'SELECT id, name FROM [tblUsers] ORDER BY name DESC, id ASC',
                    database: 1
                },
                headers: {
                    'Content-Type': 'application/json'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.parsed.sorting).to.have.length(2);
                expect(response.body.parsed.sorting[0].direction).to.eq('desc');
            });
        });

        it('should reject non-SELECT queries', () => {
            cy.request({
                method: 'POST',
                url: 'api/report-query.php?action=parseSql',
                body: {
                    sql: 'DELETE FROM [tblUsers]',
                    database: 1
                },
                headers: {
                    'Content-Type': 'application/json'
                },
                failOnStatusCode: false
            }).then((response) => {
                expect(response.status).to.eq(400);
                expect(response.body.success).to.be.false;
            });
        });

        it('should fail parsing UNION queries', () => {
            cy.request({
                method: 'POST',
                url: 'api/report-query.php?action=parseSql',
                body: {
                    sql: 'SELECT id FROM [tblUsers] UNION SELECT id FROM [tblGroups]',
                    database: 1
                },
                headers: {
                    'Content-Type': 'application/json'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.false;
            });
        });

        it('should parse queries with IIf() functions', () => {
            cy.request({
                method: 'POST',
                url: 'api/report-query.php?action=parseSql',
                body: {
                    sql: 'SELECT id, IIf([status]=1, "Active", "Inactive") AS StatusText FROM [tblUsers]',
                    database: 1
                },
                headers: {
                    'Content-Type': 'application/json'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.parsed).to.exist;
                expect(response.body.parsed.tables).to.include('tblUsers');
                expect(response.body.parsed.fields).to.have.length.greaterThan(0);
                // Should have the IIf expression field with alias StatusText
                const exprField = response.body.parsed.fields.find(f => f.alias === 'StatusText');
                expect(exprField).to.exist;
                expect(exprField.expression).to.be.true;
            });
        });

        it('should parse queries with Switch() functions', () => {
            cy.request({
                method: 'POST',
                url: 'api/report-query.php?action=parseSql',
                body: {
                    sql: 'SELECT id, Switch([type]=1, "Type A", [type]=2, "Type B") AS TypeName FROM [tblUsers]',
                    database: 1
                },
                headers: {
                    'Content-Type': 'application/json'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.parsed).to.exist;
                expect(response.body.parsed.tables).to.include('tblUsers');
                expect(response.body.parsed.fields).to.have.length.greaterThan(0);
                // Should have the Switch expression field with alias TypeName
                const exprField = response.body.parsed.fields.find(f => f.alias === 'TypeName');
                expect(exprField).to.exist;
                expect(exprField.expression).to.be.true;
            });
        });

        it('should parse deeply nested JOINs (more than 2 levels)', () => {
            // MS Access style deeply nested JOINs with >2 levels of parentheses
            cy.request({
                method: 'POST',
                url: 'api/report-query.php?action=parseSql',
                body: {
                    sql: 'SELECT a.id FROM ((([tblA] INNER JOIN [tblB] ON a.id = b.aid) INNER JOIN [tblC] ON b.id = c.bid) INNER JOIN [tblD] ON c.id = d.cid)',
                    database: 1
                },
                headers: {
                    'Content-Type': 'application/json'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.parsed).to.exist;
                expect(response.body.parsed.tables).to.have.length(4);
                expect(response.body.parsed.tables).to.include('tblA');
                expect(response.body.parsed.tables).to.include('tblB');
                expect(response.body.parsed.tables).to.include('tblC');
                expect(response.body.parsed.tables).to.include('tblD');
            });
        });

        it('should still parse moderately nested JOINs (2 levels)', () => {
            // 2 levels of nesting should still be parseable
            cy.request({
                method: 'POST',
                url: 'api/report-query.php?action=parseSql',
                body: {
                    sql: 'SELECT [tblAgenda].[Omschrijving] FROM (([tblAgenda] INNER JOIN [tblOpleidingen] ON [tblAgenda].[fkOpleiding] = [tblOpleidingen].[ID]) INNER JOIN [tblAgendaDownloads] ON [tblAgendaDownloads].[fkAgenda] = [tblAgenda].[ID])',
                    database: 1
                },
                headers: {
                    'Content-Type': 'application/json'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.parsed.tables).to.include('tblAgenda');
            });
        });
    });

    describe('Operator Types API', () => {
        it('should return boolean operators including empty and notempty', () => {
            cy.request({
                method: 'POST',
                url: 'api/report-query.php?action=getOperators',
                body: {
                    type: 'boolean',
                    database: 1
                },
                headers: {
                    'Content-Type': 'application/json'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.operators).to.be.an('array');

                // Should have yes, no, empty, notempty
                const values = response.body.operators.map(op => op.value);
                expect(values).to.include('yes');
                expect(values).to.include('no');
                expect(values).to.include('empty');
                expect(values).to.include('notempty');
            });
        });

        it('should return text operators with empty and notempty', () => {
            cy.request({
                method: 'POST',
                url: 'api/report-query.php?action=getOperators',
                body: {
                    type: 'text',
                    database: 1
                },
                headers: {
                    'Content-Type': 'application/json'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;

                const values = response.body.operators.map(op => op.value);
                expect(values).to.include('empty');
                expect(values).to.include('notempty');
            });
        });
    });

    describe('Loading SQL Mode Reports', () => {
        const testReportName = 'Cypress SQL Integration Test ' + Date.now();

        before(() => {
            // Ensure login session is available for the API call
            cy.loginAsAdmin();
            // Create a test SQL mode report via API
            cy.request({
                method: 'POST',
                url: 'api/report-save.php?action=save',
                body: {
                    name: testReportName,
                    mode: 'sql',
                    database: 1,
                    isGlobal: false,
                    rawSql: 'SELECT TOP 10 * FROM [tblUsers]',
                    tables: [],
                    fields: [],
                    parameters: [],
                    sorting: [],
                    grouping: [],
                    output: { format: 'table' }
                },
                headers: {
                    'Content-Type': 'application/json'
                }
            }).then((response) => {
                expect(response.body.success).to.be.true;
            });
        });

        it('should load SQL mode report into visual editor', () => {
            cy.visit('/report-designer.php');

            // Wait for mode dialog to appear, then click load
            cy.get('lib-dialog#modeDialog', { timeout: 15000 }).should('have.attr', 'open');
            cy.get('.mode-option[data-mode="load"]').click();

            // Wait for load dialog to open (check attribute since content may still be loading)
            cy.get('lib-dialog#loadReportDialog', { timeout: 10000 }).should('have.attr', 'open');

            // Wait for report list to populate
            cy.get('#reportList', { timeout: 10000 }).should('be.visible');

            // Find and click on the test report
            cy.get('#reportList').contains(testReportName).click();

            // Should load into the visual editor (not a separate SQL mode)
            cy.get('.report-designer-wizard', { timeout: 10000 }).should('be.visible');
        });
    });
});
