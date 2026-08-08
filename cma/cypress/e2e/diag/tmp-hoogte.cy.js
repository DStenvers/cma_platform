describe('diag: hoogte in toevoegmodus', () => {
    it('zijpaneel', () => {
        cy.loginAsAdmin();
        cy.visit('/form.php?form=opleidingen');
        cy.get('.form-layout', { timeout: 20000 }).should('exist');
        cy.wait(1500);
        cy.window().then(win => {
            win.lib_OpenPanel('form.php?form=opleidingen&New=Y', 'diagPanel', 800, 600, 'Toevoegen');
        });
        cy.get('.lib_sidepanel_container iframe', { timeout: 20000 }).should('exist');
        cy.wait(4000);
        cy.get('.lib_sidepanel_container iframe').then($f => {
            const doc = $f[0].contentDocument;
            const win = doc.defaultView;
            const r = (sel) => {
                const el = typeof sel === 'string' ? doc.querySelector(sel) : sel;
                if (!el) return sel + ' = ONTBREEKT';
                const b = el.getBoundingClientRect();
                const cs = win.getComputedStyle(el);
                return (typeof sel === 'string' ? sel : el.tagName) +
                    ' top=' + Math.round(b.top) + ' h=' + Math.round(b.height) +
                    ' display=' + cs.display + ' flex=' + cs.flex + ' overflow=' + cs.overflowY;
            };
            const regels = [
                'iframe h=' + Math.round($f[0].getBoundingClientRect().height),
                'innerHeight=' + win.innerHeight,
                r(doc.body), r('.form-layout'), r('.detail-panel'), r('#detailToolbar'),
                r('.detail-content'), r('#detailContent'), r('#mainForm'),
                r('.fold-horizontal'), r('.subform-section'),
                r(doc.querySelector('#mainForm table') || '#mainForm table')
            ];
            cy.task('log', regels.join('\n'));
        });
        cy.screenshot('diag-hoogte-nu', { capture: 'viewport' });
    });

    it('hoofdvenster', () => {
        cy.loginAsAdmin();
        cy.visit('/form.php?form=opleidingen&New=Y');
        cy.get('.form-layout', { timeout: 20000 }).should('exist');
        cy.wait(3000);
        cy.screenshot('diag-hoogte-hoofd', { capture: 'viewport' });
    });
});
