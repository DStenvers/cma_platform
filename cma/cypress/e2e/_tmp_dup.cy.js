describe('duplicate init', () => {
  it('records stacks of init fetches', () => {
    cy.loginAsAdmin();
    cy.visit('/form/opleidingen', { onBeforeLoad(w) {
      w.__initStacks = [];
      const orig = w.fetch;
      w.fetch = function (u, o) { const s = String(u); if (/action=(init|tree|combos)/.test(s)) { w.__initStacks.push({ url: s.slice(0, 90), stack: (new Error()).stack.split('\n').slice(1, 12).join('\n') }); } return orig.apply(this, arguments); };
    } });
    cy.get('lib-table tbody tr', { timeout: 20000 }).should('have.length.greaterThan', 3);
    cy.wait(5000);
    cy.window().then((win) => {
      const layouts = win.document.querySelectorAll('.form-layout');
      const info = ['form-layouts in document: ' + layouts.length, 'controllers: ' + Array.from(layouts).map(l => !!l._cmaController).join(','), 'frames: ' + win.frames.length];
      cy.writeFile('cypress/_tmp_dup.txt', info.join('\n') + '\n\n' + win.__initStacks.map(s => s.url + '\n' + s.stack).join('\n\n'));
    });
  });
});
