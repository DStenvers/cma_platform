// Diagnostic: measure page-root heights on a mobile viewport against live.
// Confirms whether .cma-form / .tools-page fill the viewport below the header.
describe('mobile height diag', () => {
  const VP_W = 390, VP_H = 844;

  function measure(label, sel) {
    cy.get(sel, { timeout: 25000 }).should('be.visible').then(($el) => {
      cy.window().then((win) => {
        const ci = win.document.querySelector('.cma-content-inner');
        const h = $el[0].getBoundingClientRect().height;
        const cih = ci ? ci.getBoundingClientRect().height : -1;
        // header is ~50px; "full" ≈ innerHeight - 50
        cy.log(`${label}: innerHeight=${win.innerHeight} ${sel}=${Math.round(h)} content-inner=${Math.round(cih)} => full≈${win.innerHeight - 50}`);
        // eslint-disable-next-line no-console
        console.log(`[HEIGHT] ${label}: innerHeight=${win.innerHeight} ${sel}=${Math.round(h)} content-inner=${Math.round(cih)} target=${win.innerHeight - 50}`);
      });
    });
  }

  it('measures reports + form root heights', () => {
    cy.viewport(VP_W, VP_H);
    cy.visit('/cma/login.php');
    cy.get('input[name=naam]', { timeout: 20000 }).type('claude');
    cy.get('input[name=wachtwoord]').type('claude!{enter}');
    cy.location('pathname', { timeout: 25000 }).should('include', '/cma/');

    // Reports page (has the explicit dvh rule → expected full)
    cy.visit('/cma/main.php?page=reports.php');
    measure('REPORTS .tools-page', '.tools-page');

    // A stone form (relies on flex:1 → suspected short)
    cy.visit('/cma/form/steensoorten_producten/1957');
    measure('FORM .cma-form', '.cma-form');
  });
});
