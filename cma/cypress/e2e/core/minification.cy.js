/**
 * Minification Tests
 *
 * Tests that minify.php serves combined/minified JS and CSS bundles correctly,
 * and that lib-tip.js and cma-tours.js are bundled (not loaded as separate requests).
 *
 * Run: npx cypress run --spec "cypress/e2e/core/minification.cy.js"
 */

describe('Minification', () => {

  // ═══════════════════════════════════════════════════════════════
  // JS BUNDLE
  // ═══════════════════════════════════════════════════════════════

  describe('JS Bundle', () => {
    it('should serve content from minify.php for JS bundle', () => {
      cy.request({
        url: '/minify.php?f=assets/js/cma-utils.js',
        failOnStatusCode: false
      }).then((response) => {
        expect(response.status).to.eq(200);
        expect(response.headers['content-type']).to.include('javascript');
        expect(response.body.length).to.be.greaterThan(0);
      });
    });

    it('should return valid JavaScript (no PHP errors)', () => {
      cy.request({
        url: '/minify.php?f=assets/js/cma-utils.js',
        failOnStatusCode: false
      }).then((response) => {
        expect(response.status).to.eq(200);
        // PHP error pages contain HTML tags - valid JS should not start with HTML
        expect(response.body).to.not.match(/^<br\s*\/>/);
        expect(response.body).to.not.match(/^<b>Fatal error<\/b>/);
        expect(response.body).to.not.match(/^<b>Warning<\/b>/);
        // Content type must be JS, not text/html (PHP error default)
        expect(response.headers['content-type']).to.include('javascript');
      });
    });

    it('should combine multiple JS files into one response', () => {
      cy.request({
        url: '/minify.php?f=assets/js/cma-utils.js,assets/js/main.js',
        failOnStatusCode: false
      }).then((response) => {
        expect(response.status).to.eq(200);
        expect(response.headers['content-type']).to.include('javascript');
      });
    });

    it('should set cache headers', () => {
      cy.request({
        url: '/minify.php?f=assets/js/cma-utils.js',
        failOnStatusCode: false
      }).then((response) => {
        expect(response.status).to.eq(200);
        expect(response.headers).to.have.property('etag');
        expect(response.headers['cache-control']).to.include('public');
      });
    });
  });

  // ═══════════════════════════════════════════════════════════════
  // CSS BUNDLE
  // ═══════════════════════════════════════════════════════════════

  describe('CSS Bundle', () => {
    it('should serve content from minify.php for CSS bundle', () => {
      cy.request({
        url: '/minify.php?f=assets/css/style.css',
        failOnStatusCode: false
      }).then((response) => {
        expect(response.status).to.eq(200);
        expect(response.headers['content-type']).to.include('css');
        expect(response.body.length).to.be.greaterThan(0);
      });
    });
  });

  // ═══════════════════════════════════════════════════════════════
  // BUNDLE CONSOLIDATION
  // ═══════════════════════════════════════════════════════════════

  describe('Bundle Consolidation', () => {
    beforeEach(() => {
      cy.loginAsAdmin();
    });

    it('should NOT load lib-tip.js as a separate script tag', () => {
      cy.visit('/main.php');
      cy.document().then((doc) => {
        const scripts = Array.from(doc.querySelectorAll('script[src]'));
        const libTipScript = scripts.find(s =>
          s.getAttribute('src').includes('lib-tip.js') &&
          !s.getAttribute('src').includes('minify.php')
        );
        expect(libTipScript, 'lib-tip.js should be in the bundle, not a separate script tag').to.be.undefined;
      });
    });

    it('should NOT load cma-tours.js as a separate script tag', () => {
      cy.visit('/main.php');
      cy.document().then((doc) => {
        const scripts = Array.from(doc.querySelectorAll('script[src]'));
        const toursScript = scripts.find(s =>
          s.getAttribute('src').includes('cma-tours.js') &&
          !s.getAttribute('src').includes('minify.php')
        );
        expect(toursScript, 'cma-tours.js should be in the bundle, not a separate script tag').to.be.undefined;
      });
    });

    it('should include lib-tip and cma-tours in the minify bundle URL', () => {
      cy.visit('/main.php');
      cy.document().then((doc) => {
        const scripts = Array.from(doc.querySelectorAll('script[src*="minify.php"]'));
        const bundleScript = scripts.find(s => s.getAttribute('src').includes('cma-utils.js'));
        expect(bundleScript, 'should have a bundle script tag').to.not.be.undefined;
        const src = bundleScript.getAttribute('src');
        expect(src).to.include('lib-tip.js');
        expect(src).to.include('cma-tours.js');
      });
    });
  });

  // ═══════════════════════════════════════════════════════════════
  // ERROR HANDLING
  // ═══════════════════════════════════════════════════════════════

  describe('Error Handling', () => {
    it('should return 404 for non-existent files', () => {
      cy.request({
        url: '/minify.php?f=nonexistent-file.js',
        failOnStatusCode: false
      }).then((response) => {
        expect(response.status).to.eq(404);
      });
    });

    it('should return 400 for missing file parameter', () => {
      cy.request({
        url: '/minify.php',
        failOnStatusCode: false
      }).then((response) => {
        expect(response.status).to.eq(400);
      });
    });

    it('should return 403 for disallowed extensions', () => {
      cy.request({
        url: '/minify.php?f=somefile.php',
        failOnStatusCode: false
      }).then((response) => {
        expect(response.status).to.eq(403);
      });
    });
  });
});
