/**
 * BlockEdit content-loss regression test.
 *
 * Symptom (production, pre-v1.20.19): a content-block ("blockedit") field became
 * totally empty and the user's edits were lost, intermittently.
 *
 * Mechanism: the blockedit field is rendered by blockedit.js on top of a
 * textarea. On save, blockedit_collect_htmls() serializes the rendered blocks
 * back into the field — but only if blocks are present. If the field was just
 * emptied (clearForm → CKEDITOR.setData('')) and its blocks were removed
 * (blockedit_clear) without being rebuilt, the next collect produced an empty
 * string and an empty value was persisted.
 *
 * Fix (v1.20.19): collect_htmls restores the last known-good content for the
 * field — guarded by record id, so a new record never inherits another record's
 * content — instead of persisting empty.
 *
 * We drive the storybook blockedit demo (#blockeditor), which mounts a real
 * .blockedit field and exposes the global blockedit_* functions. This spec
 * asserts the content SURVIVES the clear→save race and that the tripwire reports
 * the prevention. (Requires a CMA host whose contentblocks templates cover the
 * demo's seeded block types.)
 *
 * Run: npx cypress run --spec "cypress/e2e/forms/blockedit-content-loss.cy.js"
 */

describe('BlockEdit content-loss', () => {
    const FIELD = 'demo-blockeditor';
    const fieldVal = (win) => win.jQuery(`textarea[name="${FIELD}"]`).val() || '';
    const lossWarnings = (spy) =>
        spy.getCalls().filter((c) => String(c.args[0]).indexOf('[BlockEdit][LOSS-RISK]') !== -1);

    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit('/tools/storybook.php', {
            onBeforeLoad(win) {
                // Force console logging on so the cmaLog-gated tripwire fires.
                win.CMA_DEBUG = true;
                win.CMA_CONSOLE_LOGGING = true;
            },
        });

        // Wait until the demo's own init has rendered the seeded blocks.
        cy.get('#blockeditor .blockedit_block[data-type]', { timeout: 20000 })
            .should('have.length.greaterThan', 1);

        // Spy on the tripwire channel only after init, so clean-load logging
        // (there should be none) does not pollute the assertions.
        cy.window().then((win) => {
            expect(win.cmaLog, 'cmaLog present').to.exist;
            cy.spy(win.cmaLog, 'warn').as('cmaWarn');
        });
    });

    it('sanity: a normal collect serializes the seeded blocks and raises no loss warning', () => {
        cy.window().then((win) => {
            win.blockedit_collect_htmls();
            expect(fieldVal(win), 'field still holds serialized blocks').to.contain('Cube Series');
        });
        cy.get('@cmaWarn').then((spy) => {
            expect(lossWarnings(spy).length, 'no LOSS-RISK warning on a healthy collect').to.eq(0);
        });
    });

    it('PREVENTS loss: clear-then-collect (the clearForm→save race) keeps the content (fix v1.20.19)', () => {
        cy.window().then((win) => {
            // Precondition: the field has content and blocks are rendered. Init
            // has snapshotted this as the known-good state for the field.
            expect(fieldVal(win), 'seeded content present').to.contain('Cube Series');
            expect(win.jQuery('#blockeditor .blockedit_block[data-type]').length).to.be.greaterThan(1);

            // Simulate clearForm(): empty the field's editor (models setData(''))
            // and remove the rendered blocks, then run the save harvest before
            // the blocks are rebuilt.
            win.jQuery(`textarea[name="${FIELD}"]`).val('');
            win.blockedit_clear();          // removes the .blockedit_block elements
            win.blockedit_collect_htmls();  // save harvest during the not-rendered window

            // FIX: collect detects "no blocks rendered" and restores the last
            // known-good content (same record) instead of persisting empty.
            expect(fieldVal(win), 'content preserved through the race').to.contain('Cube Series');
        });

        // The tripwire should report that it prevented the empty save.
        cy.get('@cmaWarn').then((spy) => {
            const warnings = lossWarnings(spy).map((c) => String(c.args[0]));
            expect(
                warnings.some((m) => m.indexOf('prevented empty save') !== -1),
                'restore/prevention warning emitted'
            ).to.be.true;
        });
    });
});
