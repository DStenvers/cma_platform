/**
 * Close button → URL regression tests
 *
 * Recurring bug: closing a record/subform does not revert the URL correctly.
 * Closing a sidepanel must take the URL back to whatever is underneath it:
 *   - close a record sidepanel  → back to the list URL        /cma/form/<form>
 *   - close a subform sidepanel → back to the parent record   /cma/form/<form>/<id>
 *
 * The URL on close is driven by lib_CloseSidePanel (library.js) via the
 * CMA.url clean-URL contract. These tests pin that behaviour so it can't
 * regress again.
 */

describe('Close button → URL (regression)', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    /**
     * Open a record sidepanel by clicking the first table row (table mode opens
     * the record as a sidepanel; popup style defaults to 'sidepanel').
     */
    function openRecordSidepanel(form) {
        cy.openFormTable(form);
        cy.window().then(win => win.localStorage.setItem('cma_popup_style', 'sidepanel'));
        cy.get('#listTable tbody tr[data-id]', { timeout: 15000 }).first()
            .scrollIntoView().click({ force: true });
        cy.get('.lib_sidepanel_container', { timeout: 15000 }).should('exist');
    }

    it('closing a record sidepanel restores the list URL', () => {
        openRecordSidepanel('users');

        // URL reflects the open record
        cy.url().should('match', /\/cma\/form\/users\/\w+/);

        // Click the close (X) button
        cy.get('.lib_sidepanel_close').first().click({ force: true });
        cy.get('.lib_sidepanel_container').should('not.exist');

        // URL must revert to the list route — no trailing record id
        cy.url().should('match', /\/cma\/form\/users\/?$/);
    });

    it('closing via the backdrop also restores the list URL', () => {
        openRecordSidepanel('groups');
        cy.url().should('match', /\/cma\/form\/groups\/\w+/);

        cy.get('.lib_sidepanel_backdrop').click({ force: true });
        cy.get('.lib_sidepanel_container', { timeout: 5000 }).should('not.exist');

        cy.url().should('match', /\/cma\/form\/groups\/?$/);
    });

    it('closing a subform sidepanel restores the parent record URL', () => {
        cy.request({
            method: 'POST', url: 'form_api.php', form: true,
            body: { action: 'list', jsonForm: 'opleidingen', limit: 1 }
        }).then(res => {
            const rows = res.body && res.body.data;
            if (!rows || !rows.length) { cy.log('No opleidingen records — skipping'); return; }
            const id = rows[0].ID || rows[0].id;
            if (!id || String(id) === '0') { cy.log('No usable id — skipping'); return; }

            cy.visit(`/form/opleidingen/${id}`);
            cy.get('#contentArea', { timeout: 15000 }).should('exist');
            cy.url().should('include', `/form/opleidingen/${id}`);

            // Find a subform row in the detail panel and open it (→ sidepanel).
            cy.get('body', { timeout: 15000 }).then($b => {
                const sel = '.subform a[target="R"], .subform tr[data-id], [data-subform] a[target="R"], .subform-list a[target="R"]';
                const subRows = $b.find(sel);
                if (!subRows.length) { cy.log('No subform rows present — skipping'); return; }

                cy.wrap(subRows).first().click({ force: true });
                cy.get('.lib_sidepanel_container', { timeout: 15000 }).should('exist');

                // URL now includes a segment under the parent record
                cy.url().should('match', new RegExp(`/cma/form/opleidingen/${id}/`));

                // Close the subform sidepanel
                cy.get('.lib_sidepanel_close').first().click({ force: true });
                cy.get('.lib_sidepanel_container').should('not.exist');

                // URL reverts to the parent record (not the bare list, not stale subform)
                cy.url().should('match', new RegExp(`/cma/form/opleidingen/${id}/?$`));
            });
        });
    });

    it('closing a deep-linked record returns to the list URL (no in-app history)', () => {
        // The bug: a record opened by deep link has no in-app history entry, so
        // history.back() could not revert the URL to the list. closeForm() must
        // navigate to the list deterministically instead.
        cy.request({
            method: 'POST', url: 'form_api.php', form: true,
            body: { action: 'list', jsonForm: 'groups', limit: 1 }
        }).then(res => {
            const rows = res.body && res.body.data;
            if (!rows || !rows.length) { cy.log('No groups records — skipping'); return; }
            const id = rows[0].ID || rows[0].id;
            if (!id || String(id) === '0') { cy.log('No usable id — skipping'); return; }

            // Deep-link straight to the record (no list visit first)
            cy.visit(`/form/groups/${id}`);
            cy.get('body', { timeout: 15000 }).should('have.class', 'has-record');
            cy.url().should('include', `/form/groups/${id}`);

            // Trigger the detail close (exactly what the close button invokes)
            cy.window().then(win => {
                const layout = win.document.querySelector('.form-layout');
                expect(layout, '.form-layout exists').to.exist;
                expect(layout._cmaController, 'controller attached').to.exist;
                layout._cmaController.closeForm();
            });

            // URL must revert to the list route, and the list must be shown
            cy.url({ timeout: 15000 }).should('match', /\/cma\/form\/groups\/?$/);
            cy.get('#listTable, #listContent, table.listtable, lib-table, cma-tree, #leftlist', { timeout: 15000 })
                .should('exist');
        });
    });

    it('reopening after close produces a clean URL (no stale record id)', () => {
        openRecordSidepanel('groups');
        cy.url().should('match', /\/cma\/form\/groups\/\w+/);
        cy.get('.lib_sidepanel_close').first().click({ force: true });
        cy.get('.lib_sidepanel_container').should('not.exist');
        cy.url().should('match', /\/cma\/form\/groups\/?$/);

        // Open a second record — URL must carry the NEW id only, no residue
        cy.get('#listTable tbody tr[data-id]', { timeout: 15000 }).eq(1)
            .scrollIntoView().click({ force: true });
        cy.get('.lib_sidepanel_container', { timeout: 15000 }).should('exist');
        cy.url().should('match', /\/cma\/form\/groups\/\w+$/);
    });
});
