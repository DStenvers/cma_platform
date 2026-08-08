describe('diag: waar strandt newRecord()', () => {
    it('probe', () => {
        cy.loginAsAdmin();
        cy.visit('/form.php?form=opleidingen&New=Y', {
            onBeforeLoad(win) {
                win.__fouten = [];
                win.addEventListener('unhandledrejection', e =>
                    win.__fouten.push('rejection: ' + (e.reason && e.reason.stack || e.reason)));
                win.addEventListener('error', e => win.__fouten.push('error: ' + e.message));
            }
        });
        cy.get('#mainForm', { timeout: 20000 }).should('exist');
        cy.wait(3000);
        cy.window().then(win => {
            const doc = win.document;
            cy.task('log', JSON.stringify({
                fouten: win.__fouten,
                listContent: !!doc.getElementById('listContent'),
                mainForm: !!doc.getElementById('mainForm'),
                actief: doc.activeElement ? (doc.activeElement.name || doc.activeElement.tagName) : null,
                status: (doc.getElementById('statusText') || {}).textContent
            }, null, 2));
        });
    });
});
