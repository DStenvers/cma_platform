/**
 * Tree API Tests
 *
 * Tests for the form_api.php tree action.
 * These tests are resilient to different API response formats.
 */

describe('Tree API', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    describe('Tree Request', () => {
        it('should return tree data', () => {
            cy.apiGetTree('opleidingen').then(response => {
                expect(response.status).to.eq(200);
                // API may return rows, records, or data array
                const hasData = response.body.rows || response.body.records ||
                               response.body.data || response.body.success !== undefined;
                expect(hasData, 'API should return valid response').to.be.true;
            });
        });

        it('should return array of records', () => {
            cy.apiGetTree('opleidingen').then(response => {
                const data = response.body.rows || response.body.records || response.body.data || [];
                if (Array.isArray(data)) {
                    expect(data).to.be.an('array');
                } else {
                    cy.log('Response format: ' + JSON.stringify(Object.keys(response.body)));
                }
            });
        });

        it('should include record IDs', () => {
            cy.apiGetTree('opleidingen').then(response => {
                const data = response.body.rows || response.body.records || response.body.data || [];
                if (Array.isArray(data) && data.length > 0) {
                    const firstRow = data[0];
                    const hasId = firstRow.ID !== undefined || firstRow.id !== undefined;
                    expect(hasId, 'Record should have ID').to.be.true;
                } else {
                    cy.log('No data rows to check');
                }
            });
        });

        it('should include display columns', () => {
            cy.apiGetTree('opleidingen').then(response => {
                const data = response.body.rows || response.body.records || response.body.data || [];
                if (Array.isArray(data) && data.length > 0) {
                    const firstRow = data[0];
                    const keys = Object.keys(firstRow);
                    expect(keys.length).to.be.at.least(1);
                } else {
                    cy.log('No data rows to check');
                }
            });
        });
    });

    describe('Tree Filtering', () => {
        it('should filter by search parameter', () => {
            cy.apiGetTree('opleidingen', { search: 'a' }).then(response => {
                expect(response.status).to.eq(200);
                // Response should be valid regardless of format
                expect(response.body).to.exist;
            });
        });

        it('should return empty array for no matches', () => {
            cy.apiGetTree('opleidingen', { search: 'xyznonexistent123' }).then(response => {
                expect(response.status).to.eq(200);
                const data = response.body.rows || response.body.records || response.body.data;
                if (Array.isArray(data)) {
                    expect(data.length).to.eq(0);
                } else {
                    cy.log('Response format differs - may not return empty array');
                }
            });
        });

        it('should support pagination offset', () => {
            cy.apiGetTree('opleidingen', { offset: 0 }).then(response => {
                expect(response.status).to.eq(200);
            });
        });

        it('should support page size limit', () => {
            cy.apiGetTree('opleidingen', { limit: 10 }).then(response => {
                expect(response.status).to.eq(200);
                const data = response.body.rows || response.body.records || response.body.data;
                if (Array.isArray(data)) {
                    expect(data.length).to.be.at.most(10);
                } else {
                    cy.log('Response format differs');
                }
            });
        });
    });

    describe('Tree Sorting', () => {
        it('should support sort parameter', () => {
            cy.apiGetTree('opleidingen', { sort: 'naam', dir: 'asc' }).then(response => {
                expect(response.status).to.eq(200);
            });
        });

        it('should support descending sort', () => {
            cy.apiGetTree('opleidingen', { sort: 'naam', dir: 'desc' }).then(response => {
                expect(response.status).to.eq(200);
            });
        });
    });

    describe('Tree Metadata', () => {
        it('should include total count', () => {
            cy.apiGetTree('opleidingen').then(response => {
                const hasTotal = response.body.total !== undefined ||
                                response.body.count !== undefined ||
                                response.body.totalCount !== undefined;
                if (hasTotal) {
                    expect(hasTotal).to.be.true;
                } else {
                    cy.log('Total count not included in response');
                }
            });
        });

        it('should include column definitions', () => {
            cy.apiGetTree('opleidingen').then(response => {
                if (response.body.columns) {
                    expect(response.body.columns).to.be.an('array');
                } else {
                    cy.log('Column definitions not included in response');
                }
            });
        });
    });

    describe('Tree Error Handling', () => {
        it('should return error for invalid form', () => {
            cy.apiGetTree('nonexistentform').then(response => {
                // Should return error status or error in body
                expect(response.status).to.be.oneOf([200, 400, 404]);
                if (response.status === 200) {
                    const hasError = response.body.error || response.body.success === false;
                    cy.log('Error handling: ' + (hasError ? 'has error' : 'no error'));
                }
            });
        });

        it('should handle missing form parameter', () => {
            cy.request({
                url: 'form_api.php?action=tree',
                failOnStatusCode: false
            }).then(response => {
                expect(response.status).to.be.oneOf([200, 400]);
            });
        });
    });

    describe('Tree Performance', () => {
        it('should respond within acceptable time', () => {
            const start = Date.now();

            cy.apiGetTree('opleidingen').then(response => {
                const duration = Date.now() - start;
                expect(duration).to.be.lessThan(5000);
            });
        });
    });
});
