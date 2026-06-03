/**
 * BlockEdit content-loss reproduction.
 *
 * Symptom (production): a content-block ("blockedit") field becomes totally
 * empty and the user's edits are lost, intermittently.
 *
 * Mechanism this spec pins down: the blockedit field is rendered by
 * blockedit.js on top of a textarea. On save, blockedit_collect_htmls()
 * serializes the rendered blocks back into the field — but only if blocks are
 * present in the DOM and contentblocks.json has loaded. If the field was just
 * emptied (clearForm → CKEDITOR.setData('')) and its blocks were removed
 * (blockedit_clear) without being rebuilt, the next collect produces an empty
 * string, the write is skipped, and an empty value is persisted.
 *
 * We drive the storybook blockedit demo (#blockeditor), which mounts a real
 * .blockedit field seeded with two blocks (Beeldblok + Anker) and exposes the
 * global blockedit_* functions. The diagnostics tripwire added to blockedit.js
 * emits "[BlockEdit][LOSS-RISK]" warnings on exactly the two loss events; we
 * assert both that content is lost and that the tripwire caught it.
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

    it('REPRODUCES loss: clear-then-collect (the clearForm→save sequence) empties the field silently', () => {
        cy.window().then((win) => {
            // Precondition: the field has content and blocks are rendered.
            expect(fieldVal(win), 'seeded content present').to.contain('Cube Series');
            expect(win.jQuery('#blockeditor .blockedit_block[data-type]').length).to.be.greaterThan(1);

            // Simulate clearForm(): it empties the field's editor (setData(''))
            // and then removes the rendered blocks. Here the field has no
            // CKEditor instance, so emptying the textarea models setData('').
            win.jQuery(`textarea[name="${FIELD}"]`).val('');
            win.blockedit_clear(); // removes the .blockedit_block elements

            // Simulate the save harvest running before blocks are rebuilt.
            win.blockedit_collect_htmls();

            // BUG: the field is now empty — the seeded content is gone, and a
            // save at this point would persist the empty value.
            expect(fieldVal(win), 'field was silently emptied (content lost)').to.eq('');
        });

        // The tripwire must have flagged both loss events.
        cy.get('@cmaWarn').then((spy) => {
            const warnings = lossWarnings(spy).map((c) => String(c.args[0]));
            expect(warnings.length, 'tripwire fired on the loss path').to.be.greaterThan(0);
            expect(
                warnings.some((m) => m.indexOf('blockedit_clear()') !== -1),
                'clear() loss warning emitted'
            ).to.be.true;
            expect(
                warnings.some((m) => m.indexOf('EMPTY output') !== -1),
                'empty-serialization loss warning emitted'
            ).to.be.true;
        });
    });

    // Defines what "solved" looks like. Enable once the fix lands: collect must
    // not be able to overwrite a non-empty field with an empty serialization
    // (and/or clear must harvest first). Expected to pass after the fix.
    it.skip('DESIRED post-fix: content survives a clear-then-collect race', () => {
        cy.window().then((win) => {
            win.jQuery(`textarea[name="${FIELD}"]`).val('');
            win.blockedit_clear();
            win.blockedit_collect_htmls();
            expect(fieldVal(win), 'content preserved through the race').to.contain('Cube Series');
        });
    });
});
