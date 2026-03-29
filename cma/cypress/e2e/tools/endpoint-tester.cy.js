/**
 * Endpoint Tester Tests
 *
 * Tests for the CMA endpoint tester tool.
 * Verifies page structure, filters, and response time display.
 *
 * Run: npx cypress run --spec "cypress/e2e/tools/endpoint-tester.cy.js"
 */

describe('Endpoint Tester', () => {
  beforeEach(() => {
    cy.loginAsAdmin();
    cy.visit('/tools/tools_endpoint_tester.php');
  });

  // ═══════════════════════════════════════════════════════════════
  // PAGE STRUCTURE
  // ═══════════════════════════════════════════════════════════════

  describe('Page Structure', () => {
    it('should display summary stat cards', () => {
      cy.get('.summary-stats .stat-card').should('have.length', 4);
      cy.get('.summary-stats .stat-label').eq(0).should('contain', 'Endpoints');
      cy.get('.summary-stats .stat-label').eq(1).should('contain', 'Formulieren');
    });

    it('should display filter controls', () => {
      cy.get('#filterInput').should('exist');
      cy.get('#statusFilter').should('exist');
      cy.get('#categoryFilter').should('exist');
      cy.get('#errorsOnly').should('exist');
    });

    it('should display test buttons', () => {
      cy.get('#testAllBtn').should('be.visible').and('contain', 'Alle endpoints testen');
      cy.get('#testVisibleBtn').should('be.visible');
      cy.get('#resetBtn').should('be.visible');
    });

    it('should display endpoint categories with tables', () => {
      cy.get('.category').should('have.length.at.least', 1);
      cy.get('.category .listtable').should('have.length.at.least', 1);
    });

    it('should have table header with Tijd column', () => {
      cy.get('.listtable .listheader th').should('contain', 'Tijd');
    });

    it('should display endpoint rows with pending status', () => {
      cy.get('.endpoint-row').should('have.length.at.least', 1);
      cy.get('.endpoint-row').first().find('.status-badge').should('contain', 'Niet getest');
      cy.get('.endpoint-row').first().find('.response-time').should('contain', '-');
    });

    it('should have test gauges container (hidden by default)', () => {
      cy.get('#testGauges').should('exist').and('not.be.visible');
    });

    it('should have lib-gauge elements for progress, success, error, pending', () => {
      cy.get('#gaugeProgress').should('exist');
      cy.get('#gaugeSuccess').should('exist');
      cy.get('#gaugeError').should('exist');
      cy.get('#gaugePending').should('exist');
    });
  });

  // ═══════════════════════════════════════════════════════════════
  // RESPONSE TIME FORMAT
  // ═══════════════════════════════════════════════════════════════

  describe('Response Time Format', () => {
    it('should show ms suffix in response time after testing an endpoint', () => {
      // Click the first category to make sure it's expanded
      cy.get('.category').first().find('.category-content').should('be.visible');

      // Test visible endpoints (just a few)
      cy.get('#testVisibleBtn').click();

      // Wait for at least one endpoint to complete
      cy.get('.endpoint-row[data-status="success"], .endpoint-row[data-status="error"]', { timeout: 30000 })
        .should('have.length.at.least', 1);

      // Stop testing
      cy.get('#stopBtn').click();

      // Verify completed endpoints show "ms" suffix
      cy.get('.endpoint-row[data-status="success"], .endpoint-row[data-status="error"]').each(($row) => {
        cy.wrap($row).find('.response-time').invoke('text').should('match', /\d+\s*ms/);
      });
    });

    it('should reset response time to dash on reset', () => {
      cy.get('#resetBtn').click();
      cy.get('.endpoint-row').first().find('.response-time').should('have.text', '-');
    });
  });

  // ═══════════════════════════════════════════════════════════════
  // CATEGORY TOGGLE
  // ═══════════════════════════════════════════════════════════════

  describe('Category Toggle', () => {
    it('should collapse category on header click', () => {
      cy.get('.category').first().as('cat');
      cy.get('@cat').find('.category-header').click();
      cy.get('@cat').should('have.class', 'collapsed');
    });

    it('should expand collapsed category on header click', () => {
      cy.get('.category').first().as('cat');
      // Collapse first
      cy.get('@cat').find('.category-header').click();
      cy.get('@cat').should('have.class', 'collapsed');
      // Expand
      cy.get('@cat').find('.category-header').click();
      cy.get('@cat').should('not.have.class', 'collapsed');
    });
  });

  // ═══════════════════════════════════════════════════════════════
  // FILTERS
  // ═══════════════════════════════════════════════════════════════

  describe('Filters', () => {
    it('should filter endpoints by text input', () => {
      cy.get('.endpoint-row').then($rows => {
        const totalBefore = $rows.length;
        cy.get('#filterInput').type('form_api');
        cy.get('.endpoint-row:visible').should('have.length.below', totalBefore);
      });
    });

    it('should have category filter options', () => {
      cy.get('#categoryFilter option').should('have.length.at.least', 2);
    });
  });
});
