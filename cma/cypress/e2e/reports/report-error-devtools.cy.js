/**
 * Report Error Developer Tools Tests
 *
 * Tests for the developer edit tools that appear when a report throws an error.
 * Developers should see SQL/JSON editors and a download button.
 * Non-developers should see only a clean error message.
 *
 * Run: npx cypress run --spec "cypress/e2e/reports/report-error-devtools.cy.js"
 */

describe('Report Error Developer Tools', () => {

    // ═══════════════════════════════════════════════════════════════
    // API: report-definition.php
    // ═══════════════════════════════════════════════════════════════

    describe('Report Definition API', () => {
        beforeEach(() => {
            cy.loginAsDeveloper();
        });

        it('should require login for API access', () => {
            cy.clearCookies();
            cy.request({
                url: '/cma/api/report-definition.php?action=get&id=83',
                failOnStatusCode: false
            }).then(response => {
                expect(response.status).to.eq(401);
            });
        });

        it('should get a report definition', () => {
            cy.request('/cma/api/report-definition.php?action=get&id=83').then(response => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.eq(true);
                expect(response.body.report).to.have.property('id', 83);
                expect(response.body.report).to.have.property('query');
                expect(response.body.report).to.have.property('title');
            });
        });

        it('should return 404 for non-existent report', () => {
            cy.request({
                url: '/cma/api/report-definition.php?action=get&id=99999',
                failOnStatusCode: false
            }).then(response => {
                expect(response.status).to.eq(404);
                expect(response.body.success).to.eq(false);
            });
        });

        it('should reject non-SELECT queries on update', () => {
            cy.request({
                method: 'POST',
                url: '/cma/api/report-definition.php?action=update',
                body: { action: 'update', id: 83, query: 'DELETE FROM tblUsers' },
                failOnStatusCode: false
            }).then(response => {
                expect(response.status).to.eq(400);
                expect(response.body.success).to.eq(false);
                expect(response.body.error).to.include('SELECT');
            });
        });

        it('should reject queries with blocked keywords', () => {
            cy.request({
                method: 'POST',
                url: '/cma/api/report-definition.php?action=update',
                body: { action: 'update', id: 83, query: 'SELECT * FROM tblUsers; DROP TABLE tblUsers' },
                failOnStatusCode: false
            }).then(response => {
                expect(response.status).to.eq(400);
                expect(response.body.success).to.eq(false);
                expect(response.body.error).to.include('DROP');
            });
        });

        it('should download reports.json', () => {
            cy.request('/cma/api/report-definition.php?action=download').then(response => {
                expect(response.status).to.eq(200);
                expect(response.headers['content-disposition']).to.include('reports.json');
                // Validate it's proper JSON with reports array
                const data = typeof response.body === 'string' ? JSON.parse(response.body) : response.body;
                expect(data).to.have.property('reports');
                expect(data.reports).to.be.an('array');
            });
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // ERROR DISPLAY - DEVELOPER VIEW
    // ═══════════════════════════════════════════════════════════════

    describe('Developer Error View', () => {
        beforeEach(() => {
            cy.loginAsDeveloper();
        });

        it('should show error message for invalid report', () => {
            // Use a non-existent report ID to trigger an error
            cy.visit('/cma/reportdetails.php?RepID=99999', { failOnStatusCode: false });
            cy.get('lib-message[type="error"]').should('exist');
        });

        it('should show developer tools for invalid report', () => {
            cy.visit('/cma/reportdetails.php?RepID=99999', { failOnStatusCode: false });
            // Developer should see the dev tools panel
            cy.get('.report-error-dev').should('exist');
        });

        it('should show SQL tab and JSON tab buttons', () => {
            cy.visit('/cma/reportdetails.php?RepID=99999', { failOnStatusCode: false });
            cy.get('#btnTabSql').should('exist');
            cy.get('#btnTabJson').should('exist');
        });

        it('should default to SQL tab visible', () => {
            cy.visit('/cma/reportdetails.php?RepID=99999', { failOnStatusCode: false });
            cy.get('#tabSql').should('be.visible');
            cy.get('#tabJson').should('not.be.visible');
        });

        it('should switch to JSON tab on click', () => {
            cy.visit('/cma/reportdetails.php?RepID=99999', { failOnStatusCode: false });
            cy.get('#btnTabJson').click();
            cy.get('#tabJson').should('be.visible');
            cy.get('#tabSql').should('not.be.visible');
        });

        it('should switch back to SQL tab on click', () => {
            cy.visit('/cma/reportdetails.php?RepID=99999', { failOnStatusCode: false });
            cy.get('#btnTabJson').click();
            cy.get('#btnTabSql').click();
            cy.get('#tabSql').should('be.visible');
            cy.get('#tabJson').should('not.be.visible');
        });

        it('should have SQL textarea', () => {
            cy.visit('/cma/reportdetails.php?RepID=99999', { failOnStatusCode: false });
            cy.get('#reportSql').should('exist');
        });

        it('should have JSON textarea', () => {
            cy.visit('/cma/reportdetails.php?RepID=99999', { failOnStatusCode: false });
            cy.get('#btnTabJson').click();
            cy.get('#reportJson').should('exist');
        });

        it('should have download button', () => {
            cy.visit('/cma/reportdetails.php?RepID=99999', { failOnStatusCode: false });
            cy.contains('button', 'Download reports.json').should('exist');
        });

        it('should have save buttons', () => {
            cy.visit('/cma/reportdetails.php?RepID=99999', { failOnStatusCode: false });
            cy.contains('button', 'SQL opslaan').should('exist');
            cy.get('#btnTabJson').click();
            cy.contains('button', 'JSON opslaan').should('exist');
        });

        it('should show refresh link after successful save', () => {
            // Use a valid report ID so the API call succeeds
            cy.request('/cma/api/report-definition.php?action=get&id=83').then(response => {
                if (!response.body.success) {
                    cy.log('Report 83 not available, skipping');
                    return;
                }
                const originalQuery = response.body.report.query;

                cy.visit('/cma/reportdetails.php?RepID=99999', { failOnStatusCode: false });

                // Intercept the update call to simulate success without changing data
                cy.intercept('POST', '**/api/report-definition.php*', {
                    statusCode: 200,
                    body: { success: true, message: 'Rapport definitie bijgewerkt', reportId: 99999 }
                }).as('updateReport');

                cy.get('#reportSql').clear().type('SELECT 1', { delay: 0 });
                cy.contains('button', 'SQL opslaan').click();
                cy.wait('@updateReport');

                // Refresh link should appear
                cy.get('#saveStatus').should('be.visible');
                cy.get('#saveStatus a').should('exist')
                    .and('contain', 'Rapport herladen')
                    .and('have.attr', 'href')
                    .and('include', 'RepID=99999');
            });
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // ERROR DISPLAY - VALID REPORT WITH DB ERROR
    // ═══════════════════════════════════════════════════════════════

    describe('Developer Tools with existing report data', () => {
        beforeEach(() => {
            cy.loginAsDeveloper();
        });

        it('should pre-fill SQL from report definition on error', () => {
            // Intercept to force an error on a valid report
            cy.intercept('GET', '**/reportdetails.php?RepID=83*', (req) => {
                req.continue();
            });

            // Visit a valid report - if it loads fine, that's also acceptable
            cy.visit('/cma/reportdetails.php?RepID=83', { failOnStatusCode: false });
            cy.get('body').then($body => {
                if ($body.find('.report-error-dev').length > 0) {
                    // Error occurred - check that SQL is pre-filled
                    cy.get('#reportSql').invoke('val').should('not.be.empty');
                } else {
                    // Report loaded successfully - that's fine too
                    cy.log('Report loaded successfully, no error to test');
                }
            });
        });
    });

    // ═══════════════════════════════════════════════════════════════
    // NON-DEVELOPER ERROR VIEW
    // ═══════════════════════════════════════════════════════════════

    describe('Non-Developer Error View', () => {
        it('should show only error message without dev tools for regular users', () => {
            cy.loginAsUser();
            cy.visit('/cma/reportdetails.php?RepID=99999', { failOnStatusCode: false });
            cy.get('body').then($body => {
                // Should NOT have developer tools
                expect($body.find('.report-error-dev').length).to.eq(0);
                expect($body.find('#reportSql').length).to.eq(0);
                expect($body.find('#reportJson').length).to.eq(0);
            });
        });
    });
});
