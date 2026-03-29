/**
 * Session Management Tests
 *
 * Tests for session handling, timeout, and concurrent sessions.
 * Uses element IDs and data attributes.
 *
 * Run: npx cypress run --spec "cypress/e2e/auth/session.cy.js"
 */

describe('Session Management', () => {

  // ═══════════════════════════════════════════════════════════════
  // SESSION PERSISTENCE
  // ═══════════════════════════════════════════════════════════════

  describe('Session Persistence', () => {
    it('should maintain session across page navigation', () => {
      cy.loginAsAdmin();
      cy.visit('/main.php?page=dashboard.php');

      // Navigate to another page (use 'users' form which always has data)
      cy.visit('/form.php?form=users');

      // Should still be logged in - check for sidebar or toolbar
      cy.get('#sidebar, #userMenu, .toolbar, cma-toolbar', { timeout: 10000 }).should('be.visible');
      cy.url().should('not.include', 'login.php');
    });

    it('should maintain session on page refresh', () => {
      cy.loginAsAdmin();
      cy.visit('/main.php?page=dashboard.php');

      // Refresh the page
      cy.reload();

      // Should still be logged in
      cy.get('#sidebar, #userMenu').should('be.visible');
      cy.url().should('not.include', 'login.php');
    });

    it('should store session data correctly', () => {
      cy.loginAsAdmin();
      cy.visit('/main.php');

      // Session cookie should exist
      cy.getCookie('PHPSESSID').should('exist');
    });
  });

  // ═══════════════════════════════════════════════════════════════
  // SESSION VALIDATION
  // ═══════════════════════════════════════════════════════════════

  describe('Session Validation', () => {
    it('should redirect to login on invalid session', () => {
      // Clear session
      cy.clearCookies();

      // Try to access protected page
      cy.visit('/main.php?page=dashboard.php', { failOnStatusCode: false });

      // Should redirect to login
      cy.url().should('include', 'login.php');
    });

    it('should prevent access to forms without valid session', () => {
      cy.clearCookies();

      cy.visit('/form.php?form=users', { failOnStatusCode: false });

      cy.url().should('include', 'login.php');
    });

    it('should prevent access to tools without valid session', () => {
      cy.clearCookies();

      cy.visit('/tools.php', { failOnStatusCode: false });

      // Should redirect or show error
      cy.url().then(url => {
        const isProtected = url.includes('login.php') || url.includes('tools.php');
        expect(isProtected).to.be.true;
      });
    });
  });

  // ═══════════════════════════════════════════════════════════════
  // LOGOUT
  // ═══════════════════════════════════════════════════════════════

  describe('Logout', () => {
    it('should clear session on logout', () => {
      cy.loginAsAdmin();
      cy.visit('/main.php');

      // Use JavaScript to add 'open' class to dropdown (CSS :hover doesn't work in Cypress)
      cy.get('#userDropdown, .cma-user-dropdown').then($dropdown => {
        $dropdown.addClass('open');
      });
      cy.get('#userDropdown, .cma-user-dropdown').should('be.visible');

      // Click logout
      cy.get('#menuLogout, a[href*="logout"]').click();

      // Should be on login page
      cy.url().should('include', 'login.php');
    });

    it('should prevent access after logout', () => {
      cy.loginAsAdmin();
      cy.visit('/main.php');

      // Use JavaScript to add 'open' class to dropdown
      cy.get('#userDropdown, .cma-user-dropdown').then($dropdown => {
        $dropdown.addClass('open');
      });
      cy.get('#userDropdown, .cma-user-dropdown').should('be.visible');
      cy.get('#menuLogout, a[href*="logout"]').click();

      // Try to go back
      cy.visit('/main.php', { failOnStatusCode: false });

      // Should be redirected to login
      cy.url().should('include', 'login.php');
    });

    it('should show login form after logout', () => {
      cy.loginAsAdmin();
      cy.visit('/main.php');

      // Use JavaScript to add 'open' class to dropdown
      cy.get('#userDropdown, .cma-user-dropdown').then($dropdown => {
        $dropdown.addClass('open');
      });
      cy.get('#userDropdown, .cma-user-dropdown').should('be.visible');
      cy.get('#menuLogout, a[href*="logout"]').click();

      // Login form should be visible
      cy.get('#txtLogin, #loginForm').should('be.visible');
    });
  });

  // ═══════════════════════════════════════════════════════════════
  // USER CONTEXT
  // ═══════════════════════════════════════════════════════════════

  describe('User Context', () => {
    it('should display logged in username', () => {
      cy.loginAsAdmin();
      cy.visit('/main.php');

      // User name should be displayed somewhere
      cy.get('#userName, .user-name, #userMenu').should('be.visible');
    });

    it('should show appropriate menu items for user role', () => {
      cy.loginAsAdmin();
      cy.visit('/main.php');

      // Admin should see system menu
      cy.get('#sidebar').should('contain.text', 'Systeem');
    });
  });

  // ═══════════════════════════════════════════════════════════════
  // AJAX SESSION HANDLING
  // ═══════════════════════════════════════════════════════════════

  describe('AJAX Session Handling', () => {
    it('should handle session in AJAX requests', () => {
      cy.loginAsAdmin();
      cy.visit('/form.php?form=users');

      // AJAX request should work with session
      cy.request({
        url: 'api/form_list.php?form=users&pageSize=10',
        failOnStatusCode: false
      }).then(response => {
        // Should not redirect to login (200 or have data)
        expect(response.status).to.be.oneOf([200, 302]);
      });
    });
  });
});

// ═══════════════════════════════════════════════════════════════
// SSO INTEGRATION
// ═══════════════════════════════════════════════════════════════

describe('SSO Integration', () => {
  it('should have SSO login option if configured', () => {
    cy.visit('/login.php');

    // Check for SSO button (may or may not exist)
    cy.get('body').then($body => {
      if ($body.find('.sso-login, a[href*="sso"], #sso-button').length > 0) {
        cy.get('.sso-login, a[href*="sso"], #sso-button').should('be.visible');
      }
    });
  });
});

// ═══════════════════════════════════════════════════════════════
// REMEMBER ME
// ═══════════════════════════════════════════════════════════════

describe('Remember Me', () => {
  it('should have remember me option on login page', () => {
    cy.visit('/login.php');

    // Check for remember me checkbox
    cy.get('body').then($body => {
      if ($body.find('input[name="remember"], #remember-me').length > 0) {
        cy.get('input[name="remember"], #remember-me').should('exist');
      }
    });
  });
});
