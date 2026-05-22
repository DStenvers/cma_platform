/**
 * Marketing URL Form CRUD Tests
 *
 * Full-circle coverage for tblCMAMarketingUrl:
 *   create -> verify in list -> update -> verify update in list ->
 *   delete -> verify removed from list
 *
 * Required fields: Dir, Page
 * Optional: Opmerking (textbox), DATESTAMP (date)
 *
 * Identity tripwire is watched in every test — the verifyIdentity()
 * integrity check should stay silent during healthy CRUD flow.
 */

describe('Marketing URL Form CRUD', () => {
    const FORM = 'marketingurl';
    const ID_FIELD = 'ID';

    // Track records created during the suite for guaranteed teardown.
    const createdIds = [];

    const apiCreate = (payload) =>
        cy.request({
            method: 'POST',
            url: 'form_api.php',
            form: true,
            body: { action: 'save', jsonForm: FORM, ...payload },
        }).then((resp) => {
            expect(resp.body.success, 'API create succeeded').to.be.true;
            createdIds.push(resp.body.id);
            return resp.body.id;
        });

    const apiDelete = (id) =>
        cy.request({
            method: 'POST',
            url: 'form_api.php',
            form: true,
            body: { action: 'delete', jsonForm: FORM, ID: id },
            failOnStatusCode: false,
        });

    beforeEach(() => {
        cy.loginAsAdmin();
        cy.startIdentityWatcher();
    });

    afterEach(() => {
        cy.assertNoIdentityMismatch();
    });

    after(() => {
        // Belt-and-braces cleanup in case a test failed mid-flow.
        cy.loginAsAdmin();
        createdIds.forEach((id) => apiDelete(id));
    });

    describe('Table view setup', () => {
        it('loads the marketingurl form in table view', () => {
            cy.openFormTable(FORM);
            cy.get('#listTable, table.listtable').should('be.visible');
        });

        it('displays expected columns (Dir, Page)', () => {
            cy.openFormTable(FORM);
            cy.get('#listTable thead, table.listtable thead').within(() => {
                cy.contains(/Dir/i).should('exist');
                cy.contains(/Page/i).should('exist');
            });
        });
    });

    describe('Create -> list verify', () => {
        it('rejects save when required fields are empty', () => {
            cy.openFormTree(FORM);
            cy.clickToolbarButton('add');
            cy.get('body.is-creating', { timeout: 5000 }).should('exist');

            cy.clickToolbarButton('save');
            cy.get('lib-message[type="error"], .alert-error, .validation-error', { timeout: 5000 })
                .should('exist');
        });

        it('creates a new URL via the detail form and shows it in the list', () => {
            const stamp = Date.now();
            const dir = `cy-dir-${stamp}`;
            const page = `cy-page-${stamp}.html`;

            cy.openFormTree(FORM);
            cy.clickToolbarButton('add');
            cy.get('body.is-creating', { timeout: 5000 }).should('exist');

            cy.get('[name="Dir"]').clear().type(dir);
            cy.get('[name="Page"]').clear().type(page);

            cy.intercept('POST', '**/form_api.php').as('save');
            cy.clickToolbarButton('save');
            cy.wait('@save').then((interception) => {
                expect(interception.response.statusCode).to.eq(200);
                expect(interception.response.body.success).to.be.true;
                createdIds.push(interception.response.body.id);
            });
            cy.shouldShowSuccess({ timeout: 10000 });

            // Re-open list and verify presence (force a fresh fetch).
            cy.openFormTree(FORM);
            cy.get('#listContent', { timeout: 15000 }).should('contain.text', dir);
        });
    });

    describe('Update -> list verify', () => {
        let recordId;
        let originalDir;
        let originalPage;

        beforeEach(() => {
            const stamp = Date.now();
            originalDir = `cy-upd-${stamp}`;
            originalPage = `upd-${stamp}.html`;
            apiCreate({ Dir: originalDir, Page: originalPage }).then((id) => {
                recordId = id;
            });
        });

        it('edits an existing record via the detail form and reflects the change in the list', () => {
            const newPage = `${originalPage}-edited`;

            cy.openRecord(FORM, recordId);
            cy.get('[name="Page"]', { timeout: 10000 }).should('have.value', originalPage);
            cy.get('[name="Page"]').clear().type(newPage);

            cy.intercept('POST', '**/form_api.php').as('save');
            cy.clickToolbarButton('save');
            cy.wait('@save').its('response.body.success').should('be.true');
            cy.shouldShowSuccess({ timeout: 10000 });

            // Verify in list.
            cy.openFormTree(FORM);
            cy.get('#listContent', { timeout: 15000 }).should('contain.text', newPage);
        });
    });

    describe('Delete -> list verify-gone', () => {
        let recordId;
        let uniqueDir;

        beforeEach(() => {
            const stamp = Date.now();
            uniqueDir = `cy-del-${stamp}`;
            apiCreate({ Dir: uniqueDir, Page: `del-${stamp}.html` }).then((id) => {
                recordId = id;
            });
        });

        it('deletes the record via API and confirms it is gone from the list', () => {
            // First confirm it IS present.
            cy.openFormTree(FORM);
            cy.get('#listContent', { timeout: 15000 }).should('contain.text', uniqueDir);

            apiDelete(recordId).then((resp) => {
                expect(resp.body.success, 'API delete succeeded').to.be.true;
                // Remove from tracking so after() doesn't try again.
                const idx = createdIds.indexOf(recordId);
                if (idx >= 0) createdIds.splice(idx, 1);
            });

            cy.openFormTree(FORM);
            cy.get('#listContent', { timeout: 15000 }).should('not.contain.text', uniqueDir);
        });

        it('deletes via the detail-form delete button', () => {
            cy.openRecord(FORM, recordId);
            cy.get('[name="Dir"]', { timeout: 10000 }).should('have.value', uniqueDir);

            // The delete button triggers a JS confirm — stub it out.
            cy.on('window:confirm', () => true);
            cy.intercept('POST', '**/form_api.php').as('delete');
            cy.clickToolbarButton('delete');

            // libConfirm modal — accept it.
            cy.get('lib-confirm, .lib-confirm-yes, [data-cy="confirm-yes"]', { timeout: 5000 })
                .then(($el) => {
                    if ($el.length && $el.is(':visible')) {
                        cy.wrap($el).find('button').contains(/Verwijderen|Yes|OK/i).click({ force: true });
                    }
                });

            cy.wait('@delete', { timeout: 10000 }).then((interception) => {
                if (interception.response?.body?.success) {
                    const idx = createdIds.indexOf(recordId);
                    if (idx >= 0) createdIds.splice(idx, 1);
                }
            });
        });
    });

    describe('Quick search (Dir, Page)', () => {
        const stamp = Date.now();
        const dir = `cy-search-${stamp}`;
        const page = `searchme-${stamp}.html`;
        let recordId;

        before(() => {
            cy.loginAsAdmin();
            apiCreate({ Dir: dir, Page: page }).then((id) => {
                recordId = id;
            });
        });

        after(() => {
            if (recordId) apiDelete(recordId);
        });

        it('filters the list when typing in quick search', () => {
            cy.openFormTree(FORM);
            cy.get('#searchBox, [data-cy="quick-search"], input[type="search"]', { timeout: 10000 })
                .first()
                .clear()
                .type(dir.substring(0, 12));
            cy.get('#listContent', { timeout: 10000 }).should('contain.text', dir);
        });
    });
});
