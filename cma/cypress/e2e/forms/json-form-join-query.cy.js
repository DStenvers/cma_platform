/**
 * JSON Form with JOIN Query - Cypress Tests
 *
 * Tests for JSON form definitions that use listQuery with JOIN clauses.
 * Verifies that ORDER BY column is properly qualified to avoid ambiguous column errors.
 *
 * Regression test for bug: ORDER BY [ID] without table prefix causes
 * "Het opgegeven veld kan naar meer dan een tabel verwijzen" error
 * when listQuery contains JOIN.
 */

describe('JSON Form with JOIN Query', () => {
    beforeEach(() => {
        cy.login();
    });

    describe('Forms with JOIN in listQuery', () => {
        // Note: This test uses a form that has INNER JOIN in its listQuery
        // The taken.json form has: FROM tblLogins INNER JOIN tblTasks

        it('should load form list without ambiguous column error', () => {
            // Visit a JSON form that has JOIN in listQuery (taken form)
            cy.visit('/form.php?form=taken');
            cy.get('.loading', { timeout: 10000 }).should('not.exist');

            // Verify no SQL error is shown
            cy.get('body').should('not.contain', 'Het opgegeven veld kan naar meer dan een tabel verwijzen');
            cy.get('body').should('not.contain', 'Query uitvoering mislukt');

            // List should be visible (either tree or table view)
            cy.get('#simpletree, .list-content, lib-table').should('exist');
        });

        it('should handle table view sorting without error', () => {
            // Load form in table view mode
            cy.visit('/form.php?form=taken');
            cy.get('.loading', { timeout: 10000 }).should('not.exist');

            // Switch to table view if not already (click the view switcher if present)
            cy.get('body').then(($body) => {
                if ($body.find('.view-switch, [data-cy="view-table"]').length) {
                    cy.get('.view-switch, [data-cy="view-table"]').click();
                }
            });

            // Wait for table to potentially load
            cy.wait(500);

            // Verify no SQL error after potential table rendering
            cy.get('body').should('not.contain', 'Het opgegeven veld kan naar meer dan een tabel verwijzen');
            cy.get('body').should('not.contain', 'ambiguous');
        });

        it('should API call work for JSON form list', () => {
            // Test the API endpoint directly
            cy.request({
                method: 'GET',
                url: 'api/user_forms.php?action=table&form=taken',
                failOnStatusCode: false,
            }).then((response) => {
                // Should succeed or at least not have SQL ambiguity error
                if (response.status === 200) {
                    expect(response.body.success).to.not.be.false;
                    // If there's an error, it should not be the ambiguous column error
                    if (response.body.error) {
                        expect(response.body.error).to.not.contain('naar meer dan een tabel verwijzen');
                        expect(response.body.error).to.not.contain('ambiguous');
                    }
                }
            });
        });
    });

    describe('Pagination with JOIN query', () => {
        it('should handle infinite scroll pagination without error', () => {
            // Load form
            cy.visit('/form.php?form=taken');
            cy.get('.loading', { timeout: 10000 }).should('not.exist');

            // Switch to table view
            cy.get('body').then(($body) => {
                if ($body.find('.view-switch, [data-cy="view-table"]').length) {
                    cy.get('.view-switch, [data-cy="view-table"]').click();
                    cy.wait(500);
                }
            });

            // Scroll down to trigger pagination (if there's enough data)
            cy.get('body').then(($body) => {
                if ($body.find('lib-table table tbody tr').length > 0) {
                    cy.get('lib-table table tbody').scrollTo('bottom');
                    cy.wait(300);

                    // Verify no error after pagination
                    cy.get('body').should('not.contain', 'Het opgegeven veld kan naar meer dan een tabel verwijzen');
                }
            });
        });
    });
});
