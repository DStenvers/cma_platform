/**
 * Identity tripwire watcher.
 *
 * The CmaFormController.verifyIdentity() check (see form-controller.js) emits
 * a `form_identity_mismatch` event through window.CMA.requestTracker whenever
 * the controller's cached form name / id disagrees with the .form-layout
 * data-* attributes. During a healthy CRUD flow that event should NEVER fire
 * — if it does, either a real drift bug exists or the tripwire is
 * false-positive.
 *
 * Usage in a spec:
 *
 *   beforeEach(() => {
 *       cy.loginAsAdmin();
 *       cy.startIdentityWatcher();
 *   });
 *   afterEach(() => {
 *       cy.assertNoIdentityMismatch();
 *   });
 */

/**
 * Begin collecting form_identity_mismatch events.
 *
 * Hooks `cmaRequestTracker.recordEvent` after the page boot installs it.
 * Stores events on Cypress.env('_identityMismatches') so the assertion
 * command can read them later. Cypress.env is per-test scope, so a fresh
 * call in beforeEach starts a clean slate.
 */
Cypress.Commands.add('startIdentityWatcher', () => {
    Cypress.env('_identityMismatches', []);

    cy.window({ log: false }).then((win) => {
        const install = () => {
            const tracker = win.CMA && win.CMA.requestTracker;
            if (!tracker || typeof tracker.recordEvent !== 'function') {
                // Page hasn't booted yet — retry until it has, but cap so a
                // missing tracker doesn't hang the spec.
                return false;
            }
            // Avoid double-wrapping if a previous test left the wrapper.
            if (tracker._cypressTripwireWrapped) {
                return true;
            }
            const original = tracker.recordEvent.bind(tracker);
            tracker.recordEvent = function (name, details) {
                if (name === 'form_identity_mismatch') {
                    const bag = Cypress.env('_identityMismatches') || [];
                    bag.push(details);
                    Cypress.env('_identityMismatches', bag);
                }
                return original(name, details);
            };
            tracker._cypressTripwireWrapped = true;
            return true;
        };

        // Try immediately; if not yet ready, schedule one retry after a tick.
        if (!install()) {
            cy.wrap(null, { log: false }).then(() => {
                // Second attempt after the page has had time to load CMA.
                const tracker = win.CMA && win.CMA.requestTracker;
                if (tracker && typeof tracker.recordEvent === 'function' && !tracker._cypressTripwireWrapped) {
                    install();
                }
            });
        }
    });
});

/**
 * Fail the test if any form_identity_mismatch events were recorded since the
 * last `startIdentityWatcher()` call. Pass — the tripwire didn't fire and
 * the integrity check was quiet during this flow.
 */
Cypress.Commands.add('assertNoIdentityMismatch', () => {
    cy.then(() => {
        const bag = Cypress.env('_identityMismatches') || [];
        if (bag.length === 0) {
            return;
        }
        // Surface details so failures are diagnosable from the spec output.
        const summary = bag.map((d, i) =>
            `  #${i + 1} context=${d?.context} mismatches=${JSON.stringify(d?.mismatches)}`
        ).join('\n');
        throw new Error(
            `form_identity_mismatch fired ${bag.length} time(s) during this test — ` +
            `verifyIdentity() detected drift that shouldn't happen in a healthy flow:\n${summary}`
        );
    });
});
