describe('duplicate init', () => {
  it('records stacks of inline script execution', () => {
    cy.loginAsAdmin();
    cy.visit('/form/opleidingen', { onBeforeLoad(w) {
      w.__stacks = [];
      const orig = w.Node.prototype.replaceChild;
      w.Node.prototype.replaceChild = function (n, o) {
        if (n && n.tagName === 'SCRIPT') {
          w.__stacks.push({ what: n.src ? 'ext ' + n.src.slice(-60) : 'inline ' + (n.textContent || '').slice(0, 40).replace(/\n/g, ' '), t: Math.round(w.performance.now()), stack: (new Error()).stack.split('\n').slice(2, 14).map(l => l.replace(/https:[^)]*minify\.php[^:]*/, 'BUNDLE')).join('\n') });
        }
        return orig.apply(this, arguments);
      };
      const of = w.fetch;
      w.fetch = function (u) { if (/nomenu/.test(String(u))) { w.__stacks.push({ what: 'FETCH ' + String(u).slice(0, 80), t: Math.round(w.performance.now()), stack: (new Error()).stack.split('\n').slice(2, 10).map(l => l.replace(/https:[^)]*minify\.php[^:]*/, 'BUNDLE')).join('\n') }); } return of.apply(this, arguments); };
    } });
    cy.get('lib-table tbody tr', { timeout: 20000 }).should('have.length.greaterThan', 3);
    cy.wait(4000);
    cy.window().then((win) => {
      cy.writeFile('cypress/_tmp_dup.txt', win.__stacks.map(s => 't=' + s.t + ' ' + s.what + '\n' + s.stack).join('\n\n'));
    });
  });
});
