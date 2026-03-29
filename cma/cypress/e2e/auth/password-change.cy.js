/**
 * Password Change Tests
 *
 * Tests for the password change functionality via password.php and api/change-password.php.
 */

describe('Password Change', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
    });

    describe('Password Change Page', () => {
        beforeEach(() => {
            cy.visit('/password.php');
        });

        it('should display password change form', () => {
            cy.get('.pwd-container, #pwdForm, form').should('be.visible');
        });

        it('should have current password field', () => {
            cy.get('#oldPwd, input[name="old_password"]')
                .should('exist')
                .and('have.attr', 'type', 'password');
        });

        it('should have new password field', () => {
            cy.get('#newPwd, input[name="new_password"]')
                .should('exist')
                .and('have.attr', 'type', 'password');
        });

        it('should have submit button', () => {
            cy.get('.pwd-buttons button[type="submit"], .btn-primary')
                .should('exist')
                .and('be.visible');
        });

        it('should have autocomplete attributes for security', () => {
            cy.get('#oldPwd, input[name="old_password"]')
                .should('have.attr', 'autocomplete', 'current-password');

            cy.get('#newPwd, input[name="new_password"]')
                .should('have.attr', 'autocomplete', 'new-password');
        });
    });

    describe('Form Validation', () => {
        beforeEach(() => {
            cy.visit('/password.php');
        });

        it('should require old password', () => {
            cy.get('#newPwd').type('newpassword123');
            cy.get('button[type="submit"]').click();

            // Form should not submit due to HTML5 validation or show error
            cy.get('#oldPwd').then($input => {
                // Check if HTML5 validation is present
                expect($input[0].validationMessage || $input.attr('required')).to.exist;
            });
        });

        it('should require new password', () => {
            cy.get('#oldPwd').type('oldpassword');
            cy.get('#newPwd').clear();
            cy.get('button[type="submit"]').click();

            // Form should not submit
            cy.get('#newPwd').then($input => {
                expect($input[0].validationMessage || $input.attr('required')).to.exist;
            });
        });

        it('should show error message when fields are empty', () => {
            // Try to submit empty form via JavaScript
            cy.window().then(win => {
                // Bypass HTML5 validation
                win.document.getElementById('oldPwd').removeAttribute('required');
                win.document.getElementById('newPwd').removeAttribute('required');
            });

            cy.get('button[type="submit"]').click();

            cy.get('#pwdMessage, .pwd-message')
                .should('be.visible')
                .and('have.class', 'error');
        });
    });

    describe('API Integration', () => {
        beforeEach(() => {
            cy.visit('/password.php');
        });

        it('should call password change API on submit', () => {
            cy.intercept('POST', '**/api/change-password.php').as('changePassword');

            cy.get('#oldPwd').type('currentpassword');
            cy.get('#newPwd').type('newpassword');
            cy.get('button[type="submit"]').click();

            cy.wait('@changePassword');
        });

        it('should send correct parameters', () => {
            cy.intercept('POST', '**/api/change-password.php', req => {
                expect(req.body).to.include('old_password=test123');
                expect(req.body).to.include('new_password=newtest456');
            }).as('changePassword');

            cy.get('#oldPwd').type('test123');
            cy.get('#newPwd').type('newtest456');
            cy.get('button[type="submit"]').click();

            cy.wait('@changePassword');
        });

        it('should show success message on successful change', () => {
            cy.intercept('POST', '**/api/change-password.php', {
                body: { success: true, message: 'Wachtwoord is gewijzigd' }
            }).as('changePassword');

            cy.get('#oldPwd').type('currentpassword');
            cy.get('#newPwd').type('newpassword');
            cy.get('button[type="submit"]').click();

            cy.wait('@changePassword');

            cy.get('#pwdMessage, .pwd-message')
                .should('be.visible')
                .and('have.class', 'success')
                .and('contain', 'gewijzigd');
        });

        it('should clear form fields on successful change', () => {
            cy.intercept('POST', '**/api/change-password.php', {
                body: { success: true, message: 'Wachtwoord is gewijzigd' }
            }).as('changePassword');

            cy.get('#oldPwd').type('currentpassword');
            cy.get('#newPwd').type('newpassword');
            cy.get('button[type="submit"]').click();

            cy.wait('@changePassword');

            cy.get('#oldPwd').should('have.value', '');
            cy.get('#newPwd').should('have.value', '');
        });

        it('should show error message on incorrect old password', () => {
            cy.intercept('POST', '**/api/change-password.php', {
                body: { success: false, message: 'Wachtwoord kon niet worden gewijzigd' }
            }).as('changePassword');

            cy.get('#oldPwd').type('wrongpassword');
            cy.get('#newPwd').type('newpassword');
            cy.get('button[type="submit"]').click();

            cy.wait('@changePassword');

            cy.get('#pwdMessage, .pwd-message')
                .should('be.visible')
                .and('have.class', 'error');
        });

        it('should show error on network failure', () => {
            cy.intercept('POST', '**/api/change-password.php', {
                forceNetworkError: true
            }).as('networkError');

            cy.get('#oldPwd').type('currentpassword');
            cy.get('#newPwd').type('newpassword');
            cy.get('button[type="submit"]').click();

            // Should show error message
            cy.get('#pwdMessage, .pwd-message')
                .should('be.visible')
                .and('have.class', 'error');
        });
    });

    describe('Password Change API Directly', () => {
        it('should return error when not logged in', () => {
            cy.clearCookies();

            cy.request({
                method: 'POST',
                url: 'api/change-password.php',
                body: 'old_password=test&new_password=test',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                failOnStatusCode: false
            }).then(response => {
                // Response can be JSON with error, HTML redirect, or 401
                // Either should indicate not logged in
                if (typeof response.body === 'object' && response.body !== null) {
                    // JSON response
                    expect(response.body.success).to.be.false;
                } else if (typeof response.body === 'string') {
                    // May be HTML redirect to login or error message
                    expect(
                        response.body.includes('login') ||
                        response.body.includes('ingelogd') ||
                        response.status === 401 ||
                        response.status === 302
                    ).to.be.true;
                } else {
                    // No body = error or redirect
                    expect(response.status).to.be.oneOf([401, 302, 403]);
                }
            });
        });

        it('should return error when old password is empty', () => {
            cy.request({
                method: 'POST',
                url: 'api/change-password.php',
                body: 'old_password=&new_password=newtest',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                failOnStatusCode: false
            }).then(response => {
                expect(response.body.success).to.be.false;
            });
        });

        it('should return error when new password is empty', () => {
            cy.request({
                method: 'POST',
                url: 'api/change-password.php',
                body: 'old_password=test&new_password=',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                failOnStatusCode: false
            }).then(response => {
                expect(response.body.success).to.be.false;
            });
        });
    });

    describe('Security Considerations', () => {
        it('should use HTTPS-safe autocomplete values', () => {
            cy.visit('/password.php');

            cy.get('#oldPwd').should('have.attr', 'autocomplete', 'current-password');
            cy.get('#newPwd').should('have.attr', 'autocomplete', 'new-password');
        });

        it('should not expose password in URL', () => {
            cy.visit('/password.php');

            cy.intercept('POST', '**/api/change-password.php', req => {
                // Should use POST body, not query params
                expect(req.query.old_password).to.be.undefined;
                expect(req.query.new_password).to.be.undefined;
            }).as('changePassword');

            cy.get('#oldPwd').type('testpass');
            cy.get('#newPwd').type('newpass');
            cy.get('button[type="submit"]').click();

            cy.wait('@changePassword');
        });

        it('should use password input type to mask input', () => {
            cy.visit('/password.php');

            cy.get('#oldPwd').should('have.attr', 'type', 'password');
            cy.get('#newPwd').should('have.attr', 'type', 'password');
        });
    });

    describe('Accessibility', () => {
        beforeEach(() => {
            cy.visit('/password.php');
        });

        it('should have labels for input fields', () => {
            cy.get('label[for="oldPwd"]').should('exist');
            cy.get('label[for="newPwd"]').should('exist');
        });

        it('should associate labels with inputs', () => {
            cy.get('label[for="oldPwd"]')
                .invoke('attr', 'for')
                .then(labelFor => {
                    cy.get(`#${labelFor}`).should('exist');
                });
        });

        it('should have required attribute on inputs', () => {
            cy.get('#oldPwd').should('have.attr', 'required');
            cy.get('#newPwd').should('have.attr', 'required');
        });

        // COMMENTED OUT: Requires cypress-plugin-tab plugin which is not installed
        // it('should be keyboard navigable', () => {
        //     cy.get('#oldPwd').focus().should('be.focused');
        //     cy.focused().tab();
        //     cy.focused().should('have.attr', 'id', 'newPwd');
        //     cy.focused().tab();
        //     cy.focused().should('have.attr', 'type', 'submit');
        // });
    });

    describe('Password Change Modal (in main UI)', () => {
        // COMMENTED OUT: Password change is done via password.php page, not via user menu modal
        // The user menu doesn't have a password change option in the current implementation
        // it('should have password change option in user menu', () => {
        //     cy.visit('/main.php');
        //     cy.get('.cma-user-name').click(); // Open user menu
        //     cy.get('#userDropdown').should('be.visible');
        //     cy.get('[href*="password"], .change-password')
        //         .should('exist');
        // });
    });

    describe('Dark Mode Support', () => {
        beforeEach(() => {
            cy.setCookie('cma_theme', 'dark');
            cy.visit('/password.php');
        });

        it('should style form for dark mode', () => {
            cy.get('.pwd-container')
                .should('have.css', 'color')
                .and('not.match', /rgb\\(0,\\s*0,\\s*0\\)/);
        });

        it('should style input fields for dark mode', () => {
            cy.get('#oldPwd')
                .should('have.css', 'background-color')
                .and('not.match', /rgb\\(255,\\s*255,\\s*255\\)/);
        });

        it('should style success message for dark mode', () => {
            cy.intercept('POST', '**/api/change-password.php', {
                body: { success: true }
            }).as('changePassword');

            cy.get('#oldPwd').type('test');
            cy.get('#newPwd').type('test');
            cy.get('button[type="submit"]').click();

            cy.wait('@changePassword');

            cy.get('.pwd-message.success')
                .should('be.visible');
        });
    });
});
