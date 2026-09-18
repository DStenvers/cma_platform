describe('perf waterfall', () => {
  it('captures resource timings for list, tree record and tab', () => {
    cy.loginAsAdmin();
    const grab = (win, label, since) => {
      const nav = win.performance.getEntriesByType('navigation')[0];
      const res = win.performance.getEntriesByType('resource').filter(r => r.startTime >= since);
      const rows = res.map(r => ({ name: r.name.replace('https://test-mijn.rino.nl', ''), type: r.initiatorType, dur: Math.round(r.duration), ttfb: Math.round(r.responseStart - r.requestStart), enc: r.encodedBodySize, dec: r.decodedBodySize, start: Math.round(r.startTime), cache: r.transferSize === 0 ? 'cache' : '' }));
      return { label, nav: nav ? { ttfb: Math.round(nav.responseStart), domContentLoaded: Math.round(nav.domContentLoadedEventEnd), load: Math.round(nav.loadEventEnd) } : null, rows };
    };
    const out = [];
    cy.visit('/form/opleidingen');
    cy.get('lib-table tbody tr', { timeout: 20000 }).should('have.length.greaterThan', 3);
    cy.wait(4000);
    cy.window().then((win) => {
      out.push(grab(win, 'LIST PAGE', 0));
      const t1 = win.performance.now();
      cy.get('#btn_treeview a').click({ force: true });
      cy.wait(3000);
      cy.get('#leftlist', { timeout: 20000 }).contains('GZ2024-V').click({ force: true });
      cy.wait(9000).then(() => {
        out.push(grab(win, 'TREE + RECORD 244', t1));
        const t2 = win.performance.now();
        win.document.getElementById('subformTabs').selectTab(1, true);
        cy.wait(5000).then(() => {
          out.push(grab(win, 'TAB Docenten', t2));
          cy.writeFile('cypress/_tmp_perf.json', JSON.stringify(out, null, 1));
        });
      });
    });
  });
});
