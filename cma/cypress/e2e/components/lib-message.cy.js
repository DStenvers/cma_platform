/**
 * lib-message Web Component Tests
 *
 * Tests for the lib-message custom element - a notification/alert component.
 * Tests all types (info, success, warning, error), attributes, methods, and events.
 *
 * Note: lib-message does NOT use Shadow DOM - it renders into light DOM using innerHTML.
 * The lib-toaster component DOES use Shadow DOM.
 */

describe('lib-message Web Component', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit('/main.php');
        // Wait for the custom element to be registered
        cy.window().then(win => {
            expect(win.customElements.get('lib-message')).to.exist;
        });
    });

    /**
     * Helper: create a lib-message element, append to a fresh container, and wait for render.
     * Returns a Cypress chainable for the container.
     */
    function createMessage(attrs = {}, content = 'Test message') {
        const containerId = 'test-msg-container-' + Math.random().toString(36).slice(2, 8);
        const messageId = attrs.id || 'test-msg-' + Math.random().toString(36).slice(2, 8);

        cy.document().then(doc => {
            // Create a container so we can isolate lookups
            const container = doc.createElement('div');
            container.id = containerId;
            doc.body.appendChild(container);

            const message = doc.createElement('lib-message');
            message.id = messageId;
            Object.keys(attrs).forEach(key => {
                if (key === 'id') return; // already set
                message.setAttribute(key, attrs[key]);
            });
            if (content !== null) {
                message.textContent = content;
            }
            container.appendChild(message);
        });

        // Wait for requestAnimationFrame render
        // eslint-disable-next-line cypress/no-unnecessary-waiting
        cy.wait(100);

        return { containerId, messageId };
    }

    describe('Component Registration', () => {
        it('should be defined in customElements registry', () => {
            cy.window().then(win => {
                expect(win.customElements.get('lib-message')).to.exist;
            });
        });

        it('should render as a custom element', () => {
            const { messageId } = createMessage();

            cy.get(`#${messageId}`)
                .find('.lib-message')
                .should('exist');
        });
    });

    describe('Attributes', () => {
        describe('type attribute', () => {
            it('should default to "info" type', () => {
                const { messageId } = createMessage();

                cy.get(`#${messageId}`)
                    .find('.lib-message')
                    .should('have.class', 'lib-message--info');
            });

            it('should support "success" type', () => {
                const { messageId } = createMessage({ type: 'success' }, 'Success message');

                cy.get(`#${messageId}`)
                    .find('.lib-message')
                    .should('have.class', 'lib-message--success');
            });

            it('should support "warning" type', () => {
                const { messageId } = createMessage({ type: 'warning' }, 'Warning message');

                cy.get(`#${messageId}`)
                    .find('.lib-message')
                    .should('have.class', 'lib-message--warning');
            });

            it('should support "error" type', () => {
                const { messageId } = createMessage({ type: 'error' }, 'Error message');

                cy.get(`#${messageId}`)
                    .find('.lib-message')
                    .should('have.class', 'lib-message--error');
            });

            it('should re-render when type changes', () => {
                const { messageId } = createMessage({ type: 'info' }, 'Type change test');

                cy.get(`#${messageId}`)
                    .find('.lib-message')
                    .should('have.class', 'lib-message--info');

                // Change type
                cy.get(`#${messageId}`).then($msg => {
                    $msg[0].setAttribute('type', 'error');
                });

                cy.get(`#${messageId}`)
                    .find('.lib-message')
                    .should('have.class', 'lib-message--error');
            });
        });

        describe('closable attribute', () => {
            it('should NOT show close button by default', () => {
                const { messageId } = createMessage();

                cy.get(`#${messageId}`)
                    .find('.lib-message__close')
                    .should('not.exist');
            });

            it('should show close button when closable attribute present', () => {
                const { messageId } = createMessage({ closable: '' }, 'Closable message');

                cy.get(`#${messageId}`)
                    .find('.lib-message__close')
                    .should('exist');
            });

            it('should close message when close button clicked', () => {
                const { messageId } = createMessage({ closable: '' }, 'Click to close');

                cy.get(`#${messageId}`)
                    .find('.lib-message__close')
                    .click();

                // Message should be removed after animation
                cy.get(`#${messageId}`, { timeout: 5000 }).should('not.exist');
            });
        });

        describe('auto-dismiss attribute', () => {
            it('should auto-close after specified milliseconds', () => {
                const { messageId } = createMessage({ 'auto-dismiss': '500' }, 'Auto dismiss');

                cy.get(`#${messageId}`).should('exist');

                // Wait for auto-dismiss + animation
                cy.get(`#${messageId}`, { timeout: 5000 }).should('not.exist');
            });

            it('should NOT auto-dismiss if attribute not set', () => {
                const { messageId } = createMessage({}, 'Persistent message');

                cy.get(`#${messageId}`).should('exist');

                // eslint-disable-next-line cypress/no-unnecessary-waiting
                cy.wait(1000);

                cy.get(`#${messageId}`).should('exist');
            });
        });

        describe('icon attribute', () => {
            it('should show icon by default', () => {
                const { messageId } = createMessage({ type: 'success' }, 'With icon');

                cy.get(`#${messageId}`)
                    .find('.lib-message__icon')
                    .should('exist');
            });

            it('should hide icon when icon="false"', () => {
                const { messageId } = createMessage({ icon: 'false' }, 'No icon');

                cy.get(`#${messageId}`)
                    .find('.lib-message__icon')
                    .should('not.exist');
            });
        });

        describe('compact attribute', () => {
            it('should have lib-message--compact class when attribute present', () => {
                const { messageId } = createMessage({ compact: '' }, 'Compact padding');

                cy.get(`#${messageId}`)
                    .find('.lib-message')
                    .should('have.class', 'lib-message--compact');
            });

            it('should NOT have lib-message--compact class by default', () => {
                const { messageId } = createMessage({}, 'Normal padding');

                cy.get(`#${messageId}`)
                    .find('.lib-message')
                    .should('not.have.class', 'lib-message--compact');
            });
        });
    });

    describe('Methods', () => {
        describe('close() method', () => {
            it('should close the message programmatically', () => {
                const { messageId } = createMessage({}, 'Close me');

                cy.get(`#${messageId}`).then($msg => {
                    $msg[0].close();
                });

                // Should be removed after animation
                cy.get(`#${messageId}`, { timeout: 5000 }).should('not.exist');
            });

            it('should clear auto-dismiss timer on close', () => {
                const { messageId } = createMessage({ 'auto-dismiss': '5000' }, 'Timer test');

                // Close immediately
                cy.get(`#${messageId}`).then($msg => {
                    $msg[0].close();
                });

                cy.get(`#${messageId}`, { timeout: 5000 }).should('not.exist');
            });
        });

        describe('show() method', () => {
            it('should show a hidden message', () => {
                const messageId = 'show-method-test-' + Math.random().toString(36).slice(2, 8);

                cy.document().then(doc => {
                    const container = doc.createElement('div');
                    container.id = 'show-container';
                    doc.body.appendChild(container);

                    const message = doc.createElement('lib-message');
                    message.id = messageId;
                    message.hidden = true;
                    message.textContent = 'Show me';
                    container.appendChild(message);
                });

                // eslint-disable-next-line cypress/no-unnecessary-waiting
                cy.wait(100);

                cy.get(`#${messageId}`).should('not.be.visible');

                cy.get(`#${messageId}`).then($msg => {
                    $msg[0].show('Show me', 'info');
                });

                cy.get(`#${messageId}`).should('be.visible');
            });
        });
    });

    describe('Events', () => {
        describe('lib-message-close event', () => {
            it('should fire when message is closed', () => {
                const messageId = 'event-test-' + Math.random().toString(36).slice(2, 8);

                cy.document().then(doc => {
                    const container = doc.createElement('div');
                    container.id = 'event-container';
                    doc.body.appendChild(container);

                    const message = doc.createElement('lib-message');
                    message.id = messageId;
                    message.setAttribute('closable', '');
                    message.textContent = 'Event test';
                    container.appendChild(message);

                    // Track the event on the container (since message will be removed)
                    container._eventFired = false;
                    container.addEventListener('lib-message-close', () => {
                        container._eventFired = true;
                    });
                });

                // eslint-disable-next-line cypress/no-unnecessary-waiting
                cy.wait(100);

                cy.get(`#${messageId}`)
                    .find('.lib-message__close')
                    .click();

                // Wait for the element to be removed (confirms close completed)
                cy.get(`#${messageId}`, { timeout: 5000 }).should('not.exist');

                // Check event was fired
                cy.get('#event-container').then($container => {
                    expect($container[0]._eventFired).to.be.true;
                });
            });
        });
    });

    describe('Styling', () => {
        describe('type-specific styling', () => {
            it('should have success-specific colors', () => {
                const { messageId } = createMessage({ type: 'success' }, 'Success');

                cy.get(`#${messageId}`)
                    .find('.lib-message.lib-message--success')
                    .should('have.css', 'background-color')
                    .and('not.eq', 'rgba(0, 0, 0, 0)');
            });

            it('should have error-specific colors', () => {
                const { messageId } = createMessage({ type: 'error' }, 'Error');

                cy.get(`#${messageId}`)
                    .find('.lib-message.lib-message--error')
                    .should('have.css', 'background-color')
                    .and('not.eq', 'rgba(0, 0, 0, 0)');
            });

            it('should have warning-specific colors', () => {
                const { messageId } = createMessage({ type: 'warning' }, 'Warning');

                cy.get(`#${messageId}`)
                    .find('.lib-message.lib-message--warning')
                    .should('have.css', 'background-color')
                    .and('not.eq', 'rgba(0, 0, 0, 0)');
            });

            it('should have info-specific colors', () => {
                const { messageId } = createMessage({ type: 'info' }, 'Info');

                cy.get(`#${messageId}`)
                    .find('.lib-message.lib-message--info')
                    .should('have.css', 'background-color')
                    .and('not.eq', 'rgba(0, 0, 0, 0)');
            });
        });

        describe('icons', () => {
            it('should show icon for success type', () => {
                const { messageId } = createMessage({ type: 'success' }, 'Success');

                cy.get(`#${messageId}`)
                    .find('.lib-message__icon .lnr')
                    .should('exist');
            });

            it('should show icon for error type', () => {
                const { messageId } = createMessage({ type: 'error' }, 'Error');

                cy.get(`#${messageId}`)
                    .find('.lib-message__icon .lnr')
                    .should('exist');
            });
        });

        describe('animation', () => {
            it('should have fade-in animation', () => {
                const { messageId } = createMessage({}, 'Animated');

                cy.get(`#${messageId}`)
                    .find('.lib-message')
                    .should('have.css', 'animation-name', 'lib-message-fadeIn');
            });

            it('should have removing class during close', () => {
                const { messageId } = createMessage({}, 'Close animation');

                cy.get(`#${messageId}`).then($msg => {
                    $msg[0].close();
                });

                // The removing class is added before the element is removed
                cy.get(`#${messageId}`)
                    .find('.lib-message.lib-message--removing')
                    .should('exist');
            });
        });
    });

    describe('Light DOM', () => {
        it('should NOT use shadow DOM', () => {
            const { messageId } = createMessage({}, 'Light DOM test');

            cy.get(`#${messageId}`).then($msg => {
                expect($msg[0].shadowRoot).to.be.null;
            });
        });

        it('should render content in light DOM', () => {
            const { messageId } = createMessage({}, 'Visible content');

            cy.get(`#${messageId}`)
                .find('.lib-message__content')
                .should('exist')
                .and('contain.text', 'Visible content');
        });
    });

    describe('Global Helper Functions', () => {
        describe('libMessage.create()', () => {
            it('should create a message element', () => {
                cy.window().then(win => {
                    const container = win.document.createElement('div');
                    container.id = 'helper-container';
                    win.document.body.appendChild(container);

                    win.libMessage.create('Test message', {
                        type: 'success',
                        container: container
                    });
                });

                // eslint-disable-next-line cypress/no-unnecessary-waiting
                cy.wait(100);

                cy.get('#helper-container lib-message')
                    .should('exist')
                    .and('have.attr', 'type', 'success');
            });

            it('should support closable option', () => {
                cy.window().then(win => {
                    const container = win.document.createElement('div');
                    container.id = 'closable-container';
                    win.document.body.appendChild(container);

                    win.libMessage.create('Closable', {
                        closable: true,
                        container: container
                    });
                });

                // eslint-disable-next-line cypress/no-unnecessary-waiting
                cy.wait(100);

                cy.get('#closable-container lib-message')
                    .should('have.attr', 'closable');
            });

            it('should support autoDismiss option', () => {
                cy.window().then(win => {
                    const container = win.document.createElement('div');
                    container.id = 'autodismiss-container';
                    win.document.body.appendChild(container);

                    win.libMessage.create('Auto dismiss', {
                        autoDismiss: 3000,
                        container: container
                    });
                });

                // eslint-disable-next-line cypress/no-unnecessary-waiting
                cy.wait(100);

                cy.get('#autodismiss-container lib-message')
                    .should('have.attr', 'auto-dismiss', '3000');
            });
        });

        describe('libMessage.info()', () => {
            it('should create an info message', () => {
                cy.window().then(win => {
                    const container = win.document.createElement('div');
                    container.id = 'info-container';
                    win.document.body.appendChild(container);

                    win.libMessage.info('Info message', { container: container });
                });

                // eslint-disable-next-line cypress/no-unnecessary-waiting
                cy.wait(100);

                cy.get('#info-container lib-message')
                    .should('have.attr', 'type', 'info');
            });
        });

        describe('libMessage.success()', () => {
            it('should create a success message', () => {
                cy.window().then(win => {
                    const container = win.document.createElement('div');
                    container.id = 'success-container';
                    win.document.body.appendChild(container);

                    win.libMessage.success('Success message', { container: container });
                });

                // eslint-disable-next-line cypress/no-unnecessary-waiting
                cy.wait(100);

                cy.get('#success-container lib-message')
                    .should('have.attr', 'type', 'success');
            });
        });

        describe('libMessage.warning()', () => {
            it('should create a warning message', () => {
                cy.window().then(win => {
                    const container = win.document.createElement('div');
                    container.id = 'warning-container';
                    win.document.body.appendChild(container);

                    win.libMessage.warning('Warning message', { container: container });
                });

                // eslint-disable-next-line cypress/no-unnecessary-waiting
                cy.wait(100);

                cy.get('#warning-container lib-message')
                    .should('have.attr', 'type', 'warning');
            });
        });

        describe('libMessage.error()', () => {
            it('should create an error message', () => {
                cy.window().then(win => {
                    const container = win.document.createElement('div');
                    container.id = 'error-container';
                    win.document.body.appendChild(container);

                    win.libMessage.error('Error message', { container: container });
                });

                // eslint-disable-next-line cypress/no-unnecessary-waiting
                cy.wait(100);

                cy.get('#error-container lib-message')
                    .should('have.attr', 'type', 'error');
            });
        });

        describe('libMessage.create() with special characters', () => {
            it('should safely display messages containing < characters', () => {
                cy.window().then(win => {
                    const container = win.document.createElement('div');
                    container.id = 'html-escape-container';
                    win.document.body.appendChild(container);

                    // Simulate a JSON parse error message containing <
                    const errorMsg = 'Unexpected token \'<\', "co"... is not valid JSON';
                    win.libMessage.error(errorMsg, { container: container });
                });

                // eslint-disable-next-line cypress/no-unnecessary-waiting
                cy.wait(100);

                // The message should render correctly without breaking the element
                cy.get('#html-escape-container lib-message')
                    .should('exist')
                    .and('have.attr', 'type', 'error');

                // The visible text should contain the full error message
                cy.get('#html-escape-container lib-message .lib-message__content')
                    .should('contain.text', 'Unexpected token')
                    .and('contain.text', 'is not valid JSON');

                // The </lib-message> closing tag should NOT appear as visible text
                cy.get('#html-escape-container')
                    .invoke('text')
                    .should('not.contain', '</lib-message>');
            });

            it('should not render HTML tags in message text', () => {
                cy.window().then(win => {
                    const container = win.document.createElement('div');
                    container.id = 'no-html-container';
                    win.document.body.appendChild(container);

                    win.libMessage.create('<script>alert("xss")</script>', {
                        type: 'error',
                        container: container
                    });
                });

                // eslint-disable-next-line cypress/no-unnecessary-waiting
                cy.wait(100);

                // Should not create a script element
                cy.get('#no-html-container script').should('not.exist');
            });
        });

        describe('libMessage.clearAll()', () => {
            it('should clear all messages in container', () => {
                cy.window().then(win => {
                    const container = win.document.createElement('div');
                    container.id = 'clear-container';
                    win.document.body.appendChild(container);

                    win.libMessage.info('Message 1', { container: container });
                    win.libMessage.success('Message 2', { container: container });
                });

                // eslint-disable-next-line cypress/no-unnecessary-waiting
                cy.wait(100);

                cy.get('#clear-container lib-message').should('have.length', 2);

                cy.window().then(win => {
                    const cont = win.document.getElementById('clear-container');
                    win.libMessage.clearAll(cont);
                });

                // After animation completes, messages should be removed
                cy.get('#clear-container lib-message', { timeout: 5000 }).should('have.length', 0);
            });
        });
    });

    describe('Lib_ToonTopNotificatie', () => {
        it('should create a fixed top notification', () => {
            cy.window().then(win => {
                win.Lib_ToonTopNotificatie('Test notification', false, 'success');
            });

            // eslint-disable-next-line cypress/no-unnecessary-waiting
            cy.wait(100);

            cy.get('#lib-top-notification')
                .should('exist')
                .and('have.attr', 'type', 'success');
        });

        it('should auto-dismiss when not fixed', () => {
            cy.window().then(win => {
                win.Lib_ToonTopNotificatie('Auto dismiss', false, 'info');
            });

            // eslint-disable-next-line cypress/no-unnecessary-waiting
            cy.wait(100);

            cy.get('#lib-top-notification')
                .should('have.attr', 'auto-dismiss', '3000');
        });

        it('should NOT auto-dismiss when fixed', () => {
            cy.window().then(win => {
                win.Lib_ToonTopNotificatie('Fixed notification', true, 'warning');
            });

            // eslint-disable-next-line cypress/no-unnecessary-waiting
            cy.wait(100);

            cy.get('#lib-top-notification')
                .should('not.have.attr', 'auto-dismiss');
        });

        it('should replace existing top notification', () => {
            cy.window().then(win => {
                win.Lib_ToonTopNotificatie('First', true, 'info');
                win.Lib_ToonTopNotificatie('Second', true, 'error');
            });

            // eslint-disable-next-line cypress/no-unnecessary-waiting
            cy.wait(100);

            cy.get('#lib-top-notification')
                .should('have.length', 1)
                .and('have.attr', 'type', 'error');
        });
    });

    describe('libToast API', () => {
        it('should show success toast', () => {
            cy.window().then(win => {
                win.libToast.success('Opgeslagen');
            });

            cy.get('lib-toaster')
                .shadow()
                .find('.toast.success')
                .should('be.visible')
                .and('contain.text', 'Opgeslagen');
        });

        it('should show error toast', () => {
            cy.window().then(win => {
                win.libToast.error('Er ging iets mis');
            });

            cy.get('lib-toaster')
                .shadow()
                .find('.toast.error')
                .should('be.visible')
                .and('contain.text', 'Er ging iets mis');
        });

        it('should show warning toast', () => {
            cy.window().then(win => {
                win.libToast.warning('Let op');
            });

            cy.get('lib-toaster')
                .shadow()
                .find('.toast.warning')
                .should('be.visible')
                .and('contain.text', 'Let op');
        });

        it('should show info toast', () => {
            cy.window().then(win => {
                win.libToast.info('Informatie');
            });

            cy.get('lib-toaster')
                .shadow()
                .find('.toast.info')
                .should('be.visible')
                .and('contain.text', 'Informatie');
        });

        it('should auto-dismiss after default duration', () => {
            cy.window().then(win => {
                win.libToast.info('Auto dismiss test');
            });

            cy.get('lib-toaster')
                .shadow()
                .find('.toast.info')
                .should('be.visible');

            // Wait for auto-dismiss (default 4000ms + animation)
            cy.get('lib-toaster')
                .shadow()
                .find('.toast.info', { timeout: 6000 })
                .should('not.exist');
        });

        it('should clear all toasts', () => {
            cy.window().then(win => {
                win.libToast.info('Toast 1');
                win.libToast.success('Toast 2');
                win.libToast.warning('Toast 3');
            });

            cy.get('lib-toaster')
                .shadow()
                .find('.toast')
                .should('have.length', 3);

            cy.window().then(win => {
                win.libToast.clear();
            });

            // Wait for animation
            cy.get('lib-toaster')
                .shadow()
                .find('.toast', { timeout: 5000 })
                .should('not.exist');
        });
    });

    describe('Accessibility', () => {
        it('should have role="alert"', () => {
            const { messageId } = createMessage({}, 'Alert message');

            cy.get(`#${messageId}`)
                .find('.lib-message')
                .should('have.attr', 'role', 'alert');
        });

        it('should have aria-label on close button', () => {
            const { messageId } = createMessage({ closable: '' }, 'Closable');

            cy.get(`#${messageId}`)
                .find('.lib-message__close')
                .should('have.attr', 'aria-label', 'Sluiten');
        });
    });

    describe('Cleanup', () => {
        it('should clear auto-dismiss timer on disconnect', () => {
            const { messageId } = createMessage({ 'auto-dismiss': '10000' }, 'Cleanup test');

            // Remove element before timer fires
            cy.get(`#${messageId}`).then($msg => {
                $msg[0].remove();
            });

            // No errors should occur
            cy.get(`#${messageId}`).should('not.exist');
        });
    });

    describe('Copy Button (Admin/Developer only)', () => {
        it('should show copy button for admin users', () => {
            cy.window().then(win => {
                win.CMA_IS_ADMIN = true;
                win.CMA_IS_DEVELOPER = false;

                const container = win.document.createElement('div');
                container.id = 'copy-admin-container';
                win.document.body.appendChild(container);

                const message = win.document.createElement('lib-message');
                message.setAttribute('type', 'info');
                message.textContent = 'Copyable message';
                container.appendChild(message);
            });

            // eslint-disable-next-line cypress/no-unnecessary-waiting
            cy.wait(100);

            cy.get('#copy-admin-container lib-message')
                .find('.lib-message__copy')
                .should('exist');
        });

        it('should show copy button for developer users', () => {
            cy.window().then(win => {
                win.CMA_IS_ADMIN = false;
                win.CMA_IS_DEVELOPER = true;

                const container = win.document.createElement('div');
                container.id = 'copy-dev-container';
                win.document.body.appendChild(container);

                const message = win.document.createElement('lib-message');
                message.setAttribute('type', 'info');
                message.textContent = 'Copyable message';
                container.appendChild(message);
            });

            // eslint-disable-next-line cypress/no-unnecessary-waiting
            cy.wait(100);

            cy.get('#copy-dev-container lib-message')
                .find('.lib-message__copy')
                .should('exist');
        });

        it('should NOT show copy button for regular users (non-error messages)', () => {
            cy.window().then(win => {
                win.CMA_IS_ADMIN = false;
                win.CMA_IS_DEVELOPER = false;

                const container = win.document.createElement('div');
                container.id = 'copy-nonadmin-container';
                win.document.body.appendChild(container);

                const message = win.document.createElement('lib-message');
                message.setAttribute('type', 'info');
                message.textContent = 'Non-copyable message';
                container.appendChild(message);
            });

            // eslint-disable-next-line cypress/no-unnecessary-waiting
            cy.wait(100);

            cy.get('#copy-nonadmin-container lib-message')
                .find('.lib-message__copy')
                .should('not.exist');
        });

        it('should ALWAYS show copy button for error messages (even for regular users)', () => {
            cy.window().then(win => {
                win.CMA_IS_ADMIN = false;
                win.CMA_IS_DEVELOPER = false;

                const container = win.document.createElement('div');
                container.id = 'copy-error-container';
                win.document.body.appendChild(container);

                const message = win.document.createElement('lib-message');
                message.setAttribute('type', 'error');
                message.textContent = 'Error message with copy button';
                container.appendChild(message);
            });

            // eslint-disable-next-line cypress/no-unnecessary-waiting
            cy.wait(100);

            cy.get('#copy-error-container lib-message')
                .find('.lib-message__copy')
                .should('exist');
        });

        it('should copy message content to clipboard', () => {
            cy.window().then(win => {
                win.CMA_IS_ADMIN = true;

                // Ensure clipboard API exists (may be undefined in headless mode)
                const calls = [];
                win._clipboardCalls = calls;
                if (!win.navigator.clipboard) {
                    Object.defineProperty(win.navigator, 'clipboard', {
                        value: { writeText: function() { return Promise.resolve(); } },
                        writable: true,
                        configurable: true
                    });
                }
                win.navigator.clipboard.writeText = function(text) {
                    calls.push(text);
                    return Promise.resolve();
                };

                const container = win.document.createElement('div');
                container.id = 'copy-clipboard-container';
                win.document.body.appendChild(container);

                const message = win.document.createElement('lib-message');
                message.id = 'copy-test';
                message.textContent = 'Test clipboard content';
                container.appendChild(message);
            });

            // eslint-disable-next-line cypress/no-unnecessary-waiting
            cy.wait(100);

            cy.get('#copy-test .lib-message__copy').click();

            // Verify clipboard was called
            cy.window().then(win => {
                expect(win._clipboardCalls.length).to.be.greaterThan(0);
            });
        });

        it('should show success feedback after copy', () => {
            cy.window().then(win => {
                win.CMA_IS_ADMIN = true;

                // Ensure clipboard API exists (may be undefined in headless mode)
                if (!win.navigator.clipboard) {
                    Object.defineProperty(win.navigator, 'clipboard', {
                        value: { writeText: function() { return Promise.resolve(); } },
                        writable: true,
                        configurable: true
                    });
                }
                win.navigator.clipboard.writeText = function() {
                    return Promise.resolve();
                };

                const container = win.document.createElement('div');
                container.id = 'copy-feedback-container';
                win.document.body.appendChild(container);

                const message = win.document.createElement('lib-message');
                message.id = 'copy-feedback-test';
                message.textContent = 'Test feedback';
                container.appendChild(message);
            });

            // eslint-disable-next-line cypress/no-unnecessary-waiting
            cy.wait(100);

            cy.get('#copy-feedback-test .lib-message__copy').click();

            // Check for success class
            cy.get('#copy-feedback-test .lib-message__copy')
                .should('have.class', 'lib-message__copy--success');

            // Wait for success feedback to disappear
            cy.get('#copy-feedback-test .lib-message__copy', { timeout: 3000 })
                .should('not.have.class', 'lib-message__copy--success');
        });
    });
});
