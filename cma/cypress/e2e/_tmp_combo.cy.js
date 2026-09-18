describe('combo probe', () => {
  it('records combo requests and the options each combo holds', () => {
    cy.loginAsAdmin();
    const reqs = [];
    cy.intercept('GET', '**/form_api.php?action=combo*', (req) => {
      req.continue((res) => {
        let n = '?';
        try { const b = typeof res.body === 'string' ? JSON.parse(res.body) : res.body; n = b.combos ? Object.keys(b.combos).map(k => k + ':' + ((b.combos[k].options || []).length) + (b.combos[k].requires_search ? '(search)' : '')).join(',') : ((b.options || []).length + (b.label ? ' label=' + b.label : '') + (b.requires_search ? ' (search)' : '')); } catch (e) { n = 'parse-err'; }
        reqs.push(req.url.replace(/^.*form_api\.php\?/, '') + ' => ' + n);
      });
    });
    cy.visit('/form/opleidingen/244');
    cy.get('.form-layout', { timeout: 20000 }).should('exist');
    cy.wait(6000);
    cy.document().then((doc) => {
      const out = [];
      doc.querySelectorAll('lib-combo:not([id^="search_"])').forEach((el) => {
        const opts = el._options || [];
        out.push(el.getAttribute('name') + ' value=' + el.value + ' options=' + opts.length + ' ' + JSON.stringify(opts.slice(0, 3).map(o => o.value + ':' + String(o.label).slice(0, 18))) + ' requiresSearch=' + el.dataset.requiresSearch + ' dynamic=' + el.getAttribute('data-dynamic'));
      });
      cy.writeFile('cypress/_tmp_combo.txt', reqs.join('\n') + '\n---\n' + out.join('\n'));
    });
  });
});
