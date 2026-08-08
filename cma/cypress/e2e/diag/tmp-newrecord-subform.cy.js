describe('diag: waarom klapt de groep niet open', () => {
    it('probe', () => {
        cy.loginAsAdmin();
        cy.visit('/form.php?form=opleidingen&New=Y', {
            onBeforeLoad(win) {
                win.__fouten = [];
                win.addEventListener('error', e => win.__fouten.push(String(e.message)));
                win.addEventListener('unhandledrejection', e => win.__fouten.push('rejection: ' + e.reason));
                win.localStorage.setItem('cma_grp_0_2', 'closed');
            }
        });
        cy.get('cma-groupbox[group-id="2"]', { timeout: 20000 }).should('exist');
        cy.wait(3000);
        cy.window().then(win => {
            const gb = win.document.querySelector('cma-groupbox[group-id="2"]');
            cy.task('log', JSON.stringify({
                fouten: win.__fouten,
                heeftCmaForm: typeof win.cmaForm,
                gedefinieerd: !!win.customElements.get('cma-groupbox'),
                isOpen: gb.isOpen,
                heeftOpen: typeof gb.open,
                opslag: win.localStorage.getItem('cma_grp_0_2')
            }, null, 2));
            // Handmatig aanroepen: werkt de logica als de timing goed is?
            if (win.cmaForm && win.cmaForm.expandGroupboxesWithRequiredFields) {
                win.cmaForm.expandGroupboxesWithRequiredFields();
                cy.task('log', 'na handmatige aanroep: isOpen=' + gb.isOpen +
                    ' opslag=' + win.localStorage.getItem('cma_grp_0_2'));
            }
        });
    });
});
