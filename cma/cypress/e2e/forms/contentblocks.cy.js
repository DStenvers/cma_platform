/**
 * Content Blocks Form CRUD Tests
 *
 * The contentblocks form is special:
 *   - database: "json" — data lives in a JSON config file, not SQL
 *   - idField: "id" — USER-SUPPLIED (textbox required, maxLen 10), not auto
 *   - allowCopy: true — the only internal form with copy enabled, so we
 *     cover that path too
 *
 * Full-circle CRUD:
 *   create -> list -> update -> list -> copy -> list -> delete (both)
 *
 * The identity tripwire (verifyIdentity → form_identity_mismatch event)
 * is watched in every test.
 */

describe('Content Blocks Form CRUD', () => {
    const FORM = 'contentblocks';

    // Track created ids for guaranteed teardown.
    const createdIds = [];

    /** ID must fit in maxLength=10 — use short prefix + base36 timestamp. */
    const makeId = (prefix = 'cy') => {
        const suffix = Date.now().toString(36).slice(-7);
        return `${prefix}${suffix}`.slice(0, 10);
    };

    const apiCreate = (payload) =>
        cy.request({
            method: 'POST',
            url: 'form_api.php',
            form: true,
            body: { action: 'save', jsonForm: FORM, ...payload },
        }).then((resp) => {
            expect(resp.body.success, 'API create succeeded').to.be.true;
            createdIds.push(resp.body.id ?? payload.id);
            return resp.body.id ?? payload.id;
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
        cy.loginAsAdmin();
        createdIds.forEach((id) => apiDelete(id));
    });

    describe('List view', () => {
        it('loads the contentblocks form', () => {
            cy.openFormTable(FORM);
            cy.get('#listTable, table.listtable').should('be.visible');
        });

        it('shows the title column (detailField)', () => {
            cy.openFormTable(FORM);
            cy.get('#listTable thead, table.listtable thead').within(() => {
                cy.contains(/Titel|title/i).should('exist');
            });
        });
    });

    describe('Create -> list verify', () => {
        it('shows a validation error when required fields (id, title) are empty', () => {
            cy.openFormTree(FORM);
            cy.clickToolbarButton('add');
            cy.get('body.is-creating', { timeout: 5000 }).should('exist');

            cy.clickToolbarButton('save');
            cy.get('lib-message[type="error"], .alert-error, .validation-error', { timeout: 5000 })
                .should('exist');
        });

        it('creates a new content block with user-supplied id and shows it in the list', () => {
            const id = makeId('cyc');
            const title = `CY Block ${id}`;

            cy.openFormTree(FORM);
            cy.clickToolbarButton('add');
            cy.get('body.is-creating', { timeout: 5000 }).should('exist');

            cy.get('[name="id"]').clear().type(id);
            cy.get('[name="title"]').clear().type(title);
            cy.get('[name="description"]').clear().type('Cypress test block');

            cy.intercept('POST', '**/form_api.php').as('save');
            cy.clickToolbarButton('save');
            cy.wait('@save').then((interception) => {
                expect(interception.response.statusCode).to.eq(200);
                expect(interception.response.body.success).to.be.true;
                // For JSON-database forms the returned id matches what we supplied.
                createdIds.push(interception.response.body.id ?? id);
            });
            cy.shouldShowSuccess({ timeout: 10000 });

            cy.openFormTree(FORM);
            cy.get('#listContent', { timeout: 15000 }).should('contain.text', title);
        });
    });

    describe('Update -> list verify', () => {
        let id;
        let originalTitle;

        beforeEach(() => {
            id = makeId('cyu');
            originalTitle = `Update Block ${id}`;
            apiCreate({ id, title: originalTitle, description: 'original' });
        });

        it('updates the title via detail form and reflects the change in the list', () => {
            const newTitle = `${originalTitle} EDITED`;

            cy.openRecord(FORM, id);
            cy.get('[name="title"]', { timeout: 10000 }).should('have.value', originalTitle);
            cy.get('[name="title"]').clear().type(newTitle);

            cy.intercept('POST', '**/form_api.php').as('save');
            cy.clickToolbarButton('save');
            cy.wait('@save').its('response.body.success').should('be.true');
            cy.shouldShowSuccess({ timeout: 10000 });

            cy.openFormTree(FORM);
            cy.get('#listContent', { timeout: 15000 }).should('contain.text', newTitle);
        });
    });

    describe('Copy (allowCopy: true)', () => {
        let sourceId;
        let copyId;
        let sourceTitle;

        beforeEach(() => {
            sourceId = makeId('cysrc');
            sourceTitle = `Copy Source ${sourceId}`;
            apiCreate({ id: sourceId, title: sourceTitle, description: 'source for copy' });
        });

        it('copies an existing block: the copy toolbar action clears id and creates a new record on save', () => {
            cy.openRecord(FORM, sourceId);
            cy.get('[name="title"]', { timeout: 10000 }).should('have.value', sourceTitle);

            // Copy clears the id; user supplies a new one.
            cy.clickToolbarButton('copy');
            cy.get('body.is-creating', { timeout: 5000 }).should('exist');

            copyId = makeId('cycpy');
            cy.get('[name="id"]').clear().type(copyId);
            // Title should still be filled from the source — change it to disambiguate.
            cy.get('[name="title"]').clear().type(`${sourceTitle} (copy)`);

            cy.intercept('POST', '**/form_api.php').as('save');
            cy.clickToolbarButton('save');
            cy.wait('@save').its('response.body.success').should('be.true');
            cy.shouldShowSuccess({ timeout: 10000 });
            createdIds.push(copyId);

            cy.openFormTree(FORM);
            // Both records should now be present.
            cy.get('#listContent').should('contain.text', sourceTitle);
            cy.get('#listContent').should('contain.text', `${sourceTitle} (copy)`);
        });
    });

    describe('Delete -> list verify-gone', () => {
        let id;
        let title;

        beforeEach(() => {
            id = makeId('cyd');
            title = `Delete Block ${id}`;
            apiCreate({ id, title });
        });

        it('deletes the block via API and confirms it is gone from the list', () => {
            cy.openFormTree(FORM);
            cy.get('#listContent', { timeout: 15000 }).should('contain.text', title);

            apiDelete(id).then((resp) => {
                expect(resp.body.success, 'API delete succeeded').to.be.true;
                const idx = createdIds.indexOf(id);
                if (idx >= 0) createdIds.splice(idx, 1);
            });

            cy.openFormTree(FORM);
            cy.get('#listContent', { timeout: 15000 }).should('not.contain.text', title);
        });
    });

    describe('Quick search (title, description)', () => {
        let id;
        let title;

        before(() => {
            cy.loginAsAdmin();
            id = makeId('cys');
            title = `Searchable Block ${id}`;
            apiCreate({ id, title, description: 'findme-needle' });
        });

        after(() => {
            if (id) apiDelete(id);
        });

        it('filters by title fragment via quick search', () => {
            cy.openFormTree(FORM);
            cy.get('#searchBox, [data-cy="quick-search"], input[type="search"]', { timeout: 10000 })
                .first()
                .clear()
                .type('Searchable');
            cy.get('#listContent', { timeout: 10000 }).should('contain.text', title);
        });

        it('filters by description content via quick search', () => {
            cy.openFormTree(FORM);
            cy.get('#searchBox, [data-cy="quick-search"], input[type="search"]', { timeout: 10000 })
                .first()
                .clear()
                .type('findme-needle');
            cy.get('#listContent', { timeout: 10000 }).should('contain.text', title);
        });
    });
});
