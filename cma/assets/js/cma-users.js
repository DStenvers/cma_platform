/**
 * CMA Users Module
 *
 * Handles user-specific actions like password reset and login-as.
 * Only available to admins and developers.
 */

(function() {
    'use strict';

    // console.log('[cma-users.js] Module loading...');

    // Wait for CMA namespace
    if (typeof CMA === 'undefined') {
        window.CMA = {};
    }

    CMA.Users = {
        /**
         * Initialize user actions for the users form
         * Called via onLoadJs when a user record is loaded
         *
         * @param {string} recordId The user ID being viewed
         */
        initUserActions: function(recordId) {
            // cmaLog.log('[CMA.Users] initUserActions called with recordId:', recordId);

            // Update groups field visibility based on userLevel
            this.updateGroupsFieldVisibility();

            // Listen for userLevel changes to update groups field visibility
            var userLevelField = document.querySelector('[name="userLevel"]');
            if (userLevelField) {
                // For radio groups, listen on the container
                var container = userLevelField.closest('.radiogroup, .form-row');
                if (container) {
                    container.addEventListener('change', this.updateGroupsFieldVisibility.bind(this));
                }
            }

            // Only proceed if we have a record and user is admin/developer
            if (!recordId || recordId === 'new') {
                // cmaLog.log('[CMA.Users] No valid recordId, removing buttons');
                this.removeActionButtons();
                return;
            }

            // Check if current user is admin or developer
            // ONLY use server-set variables - NEVER trust cookies client-side for authorization
            // These variables are set securely by main.php after server-side authentication
            var isAdmin = window.CMA_IS_ADMIN || (window.top && window.top.CMA_IS_ADMIN);
            var isDeveloper = window.CMA_IS_DEVELOPER || (window.top && window.top.CMA_IS_DEVELOPER);
            // cmaLog.log('[CMA.Users] isAdmin:', isAdmin, 'isDeveloper:', isDeveloper);

            if (!isAdmin && !isDeveloper) {
                // cmaLog.log('[CMA.Users] User is not admin or developer, skipping buttons');
                return;
            }

            // cmaLog.log('[CMA.Users] Adding action buttons');
            this.addActionButtons(recordId, isDeveloper);
        },

        /**
         * Hide/show the "Lid van groepen" field based on userLevel
         * Admins (1) and Developers (2) don't need group membership - they have full access
         */
        updateGroupsFieldVisibility: function() {
            var userLevelField = document.querySelector('[name="userLevel"]:checked');
            var groupsRow = document.querySelector('[name="user_groups"]');
            if (!groupsRow) {
                groupsRow = document.getElementById('user_groups');
            }
            if (!groupsRow) return;

            // Find the form row containing the groups field
            var formRow = groupsRow.closest('.form-row, tr');
            if (!formRow) return;

            var userLevel = userLevelField ? parseInt(userLevelField.value, 10) : 0;
            // cmaLog.log('[CMA.Users] userLevel:', userLevel, 'hiding groups:', userLevel >= 1);

            // Hide groups field for admins (1) and developers (2)
            if (userLevel >= 1) {
                formRow.style.display = 'none';
            } else {
                formRow.style.display = '';
            }
        },

        /**
         * Add action buttons to the toolbar
         */
        addActionButtons: function(recordId, isDeveloper) {
            // Find the detail toolbar's left section where buttons are placed
            var toolbar = document.querySelector('#detailToolbar .toolbar-left');
            if (!toolbar) {
                cmaLog.warn('CMA.Users: Could not find #detailToolbar .toolbar-left');
                return;
            }

            // Remove existing buttons first
            this.removeActionButtons();

            // Add separator before user action buttons
            var separator = document.createElement('span');
            separator.className = 'tb-sep requires-record user-action-buttons';
            toolbar.appendChild(separator);

            // Login as button (for developers, and admins only for regular users)
            var currentUserId = window.CMA_CURRENT_USER_ID || (window.top && window.top.CMA_CURRENT_USER_ID) || '';
            var isCurrentUser = String(recordId) === String(currentUserId);

            var loginAsBtn = document.createElement('span');
            loginAsBtn.className = 'tb-btn responsive-btn requires-record user-action-buttons' + (isCurrentUser ? ' disabled' : '');
            loginAsBtn.title = isCurrentUser ? 'Dit is de huidige gebruiker' : 'Log in als deze gebruiker';
            loginAsBtn.innerHTML = '<a href="#" data-action="loginAs"><span class="lnr lnr-enter"></span><span class="btn-text">Inloggen als</span></a>';
            if (isCurrentUser) {
                loginAsBtn.querySelector('a').onclick = function(e) { e.preventDefault(); };
                loginAsBtn.style.opacity = '0.4';
                loginAsBtn.style.pointerEvents = 'none';
            } else {
                loginAsBtn.querySelector('a').onclick = function(e) {
                    e.preventDefault();
                    CMA.Users.loginAsUser(recordId);
                };
            }
            toolbar.appendChild(loginAsBtn);
        },

        /**
         * Remove action buttons
         */
        removeActionButtons: function() {
            document.querySelectorAll('.user-action-buttons').forEach(function(el) {
                el.remove();
            });
        },

        /**
         * Reset a user's password
         */
        resetPassword: async function(userId) {
            // Confirm action
            var confirmed = await libConfirm(
                'Weet je zeker dat je het wachtwoord wilt resetten? Er wordt een tijdelijk wachtwoord gegenereerd.',
                {
                    title: 'Wachtwoord resetten',
                    type: 'warning',
                    confirmText: 'Resetten',
                    cancelText: 'Annuleren'
                }
            );

            if (!confirmed) return;

            try {
                var response = await fetch('/cma/api/user_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'reset_password',
                        userId: userId
                    })
                });

                var result = await response.json();

                if (result.success) {
                    // Show the temporary password in a dialog
                    await libAlert(
                        '<p>' + result.message + '</p>' +
                        '<p style="margin-top: 15px;"><strong>Tijdelijk wachtwoord:</strong></p>' +
                        '<code style="display: block; padding: 10px; background: var(--bg-surface-alt); border-radius: 4px; font-size: var(--font-size-lg); text-align: center; user-select: all;">' +
                        result.tempPassword +
                        '</code>' +
                        '<p style="margin-top: 15px; font-size: var(--font-size-sm); color: var(--text-muted);">' +
                        result.note +
                        '</p>',
                        {
                            title: 'Wachtwoord gereset',
                            type: 'success',
                            html: true
                        }
                    );
                } else {
                    await libAlert(result.error || 'Wachtwoord resetten mislukt', {
                        title: 'Fout',
                        type: 'danger'
                    });
                }
            } catch (e) {
                cmaLog.error('Reset password error:', e);
                await libAlert('Er is een fout opgetreden: ' + e.message, {
                    title: 'Fout',
                    type: 'danger'
                });
            }
        },

        /**
         * Login as another user
         */
        loginAsUser: async function(userId) {
            // Confirm action
            var confirmed = await libConfirm(
                'Je gaat inloggen als deze gebruiker. Wil je doorgaan?',
                {
                    title: 'Inloggen als gebruiker',
                    type: 'info',
                    confirmText: 'Inloggen',
                    cancelText: 'Annuleren'
                }
            );

            if (!confirmed) return;

            try {
                var response = await fetch('/cma/api/user_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'login_as',
                        userId: userId
                    })
                });

                var result = await response.json();

                if (result.success) {
                    await libAlert(
                        '<p>' + result.message + '</p>' +
                        '<p style="margin-top: 10px; font-size: var(--font-size-sm); color: var(--text-muted);">' +
                        result.note +
                        '</p>',
                        {
                            title: 'Ingelogd',
                            type: 'success',
                            html: true
                        }
                    );

                    // Redirect to dashboard
                    if (result.redirect) {
                        window.top.location.href = result.redirect;
                    } else {
                        window.top.location.reload();
                    }
                } else {
                    await libAlert(result.error || 'Inloggen mislukt', {
                        title: 'Fout',
                        type: 'danger'
                    });
                }
            } catch (e) {
                cmaLog.error('Login as error:', e);
                await libAlert('Er is een fout opgetreden: ' + e.message, {
                    title: 'Fout',
                    type: 'danger'
                });
            }
        }
    };

})();
