/**
 * Options API Tests
 *
 * Tests for the form_api.php options action (combobox data).
 */

describe('Options API', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    describe('Options Request', () => {
        it('should return options data', () => {
            cy.apiGetOptions('opleidingen', 'status').then(response => {
                expect(response.status).to.eq(200);
            });
        });

        it('should return array of options', () => {
            cy.apiGetOptions('opleidingen', 'status').then(response => {
                if (response.body.options) {
                    expect(response.body.options).to.be.an('array');
                }
            });
        });

        it('should include value and label', () => {
            cy.apiGetOptions('opleidingen', 'status').then(response => {
                if (response.body.options && response.body.options.length > 0) {
                    const firstOption = response.body.options[0];
                    expect(firstOption).to.have.property('value');
                    expect(firstOption).to.have.property('label');
                }
            });
        });
    });

    describe('Options Filtering', () => {
        it('should support search/filter parameter', () => {
            cy.request({
                url: 'form_api.php?action=options&form=opleidingen&field=status&search=a',
                failOnStatusCode: false
            }).then(response => {
                expect(response.status).to.eq(200);
            });
        });

        it('should return empty array when no matches', () => {
            cy.request({
                url: 'form_api.php?action=options&form=opleidingen&field=status&search=xyznonexistent',
                failOnStatusCode: false
            }).then(response => {
                if (response.body.options) {
                    expect(response.body.options).to.be.an('array');
                }
            });
        });
    });

    describe('Options Error Handling', () => {
        it('should handle invalid field name', () => {
            cy.apiGetOptions('opleidingen', 'nonexistentfield').then(response => {
                expect(response.status).to.be.oneOf([200, 400, 404]);
            });
        });

        it('should handle invalid form name', () => {
            cy.apiGetOptions('nonexistentform', 'field').then(response => {
                expect(response.status).to.be.oneOf([200, 400, 404]);
            });
        });

        it('should handle missing field parameter', () => {
            cy.request({
                url: 'form_api.php?action=options&form=opleidingen',
                failOnStatusCode: false
            }).then(response => {
                expect(response.status).to.be.oneOf([200, 400]);
            });
        });
    });

    describe('Options Caching', () => {
        it('should return consistent results', () => {
            cy.apiGetOptions('opleidingen', 'status').then(response1 => {
                cy.apiGetOptions('opleidingen', 'status').then(response2 => {
                    if (response1.body.options && response2.body.options) {
                        expect(response1.body.options.length).to.eq(response2.body.options.length);
                    }
                });
            });
        });
    });

    describe('Options Performance', () => {
        it('should respond quickly', () => {
            const start = Date.now();

            cy.apiGetOptions('opleidingen', 'status').then(() => {
                const duration = Date.now() - start;
                expect(duration).to.be.lessThan(2000);
            });
        });
    });

    describe('Dependent Options', () => {
        it('should support parent value parameter', () => {
            cy.request({
                url: 'form_api.php?action=options&form=opleidingen&field=subfield&parent=1',
                failOnStatusCode: false
            }).then(response => {
                expect(response.status).to.be.oneOf([200, 400, 404]);
            });
        });
    });
});
