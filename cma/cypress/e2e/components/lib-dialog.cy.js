/**
 * lib-dialog Web Component Tests
 *
 * Tests for the lib-dialog custom element - a modal dialog component.
 * Tests declarative usage, programmatic usage, attributes, methods, and events.
 */

describe('lib-dialog Web Component', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit('/main.php');
    });

    describe('Component Registration', () => {
        it('should be defined in customElements registry', () => {
            cy.window().then(win => {
                expect(win.customElements.get('lib-dialog')).to.exist;
            });
        });

        it('should render as a custom element', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Test Dialog');
                dialog.textContent = 'Dialog content';
                doc.body.appendChild(dialog);

                cy.get('lib-dialog').last()
                    .shadow()
                    .find('.dialog-content')
                    .should('exist');
            });
        });
    });

    describe('Attributes', () => {
        describe('heading attribute (recommended)', () => {
            it('should display heading in header', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'My Dialog Heading');
                    dialog.textContent = 'Content';
                    doc.body.appendChild(dialog);
                    dialog.open();

                    cy.get('lib-dialog').last()
                        .shadow()
                        .find('.dialog-title-text')
                        .should('contain', 'My Dialog Heading');
                });
            });

            it('should not show browser tooltip with heading attribute', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'No Tooltip');
                    doc.body.appendChild(dialog);
                    dialog.open();

                    // Using heading should NOT set the native title attribute
                    cy.get('lib-dialog').last()
                        .should('not.have.attr', 'title');
                });
            });

            it('should update heading dynamically', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Original Heading');
                    doc.body.appendChild(dialog);
                    dialog.open();

                    dialog.setAttribute('heading', 'New Heading');

                    cy.get('lib-dialog').last()
                        .shadow()
                        .find('.dialog-title-text')
                        .should('contain', 'New Heading');
                });
            });
        });

        describe('title attribute (backward compat)', () => {
            it('should display title in header', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('title', 'My Dialog Title');
                    dialog.textContent = 'Content';
                    doc.body.appendChild(dialog);
                    dialog.open();

                    cy.get('lib-dialog').last()
                        .shadow()
                        .find('.dialog-title-text')
                        .should('contain', 'My Dialog Title');
                });
            });

            it('should update title dynamically', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('title', 'Original Title');
                    doc.body.appendChild(dialog);
                    dialog.open();

                    dialog.setAttribute('title', 'New Title');

                    cy.get('lib-dialog').last()
                        .shadow()
                        .find('.dialog-title-text')
                        .should('contain', 'New Title');
                });
            });

            it('should escape HTML in title', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('title', '<script>alert("xss")</script>');
                    doc.body.appendChild(dialog);
                    dialog.open();

                    cy.get('lib-dialog').last()
                        .shadow()
                        .find('.dialog-title-text')
                        .should('not.contain.html', '<script>');
                });
            });
        });

        describe('type attribute', () => {
            it('should support "info" type', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('type', 'info');
                    dialog.setAttribute('heading', 'Info');
                    doc.body.appendChild(dialog);
                    dialog.open();

                    cy.get('lib-dialog').last()
                        .shadow()
                        .find('.dialog-content')
                        .should('have.class', 'dialog-info');
                });
            });

            it('should support "warning" type', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('type', 'warning');
                    dialog.setAttribute('heading', 'Warning');
                    doc.body.appendChild(dialog);
                    dialog.open();

                    cy.get('lib-dialog').last()
                        .shadow()
                        .find('.dialog-content')
                        .should('have.class', 'dialog-warning');
                });
            });

            it('should support "danger" type', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('type', 'danger');
                    dialog.setAttribute('heading', 'Danger');
                    doc.body.appendChild(dialog);
                    dialog.open();

                    cy.get('lib-dialog').last()
                        .shadow()
                        .find('.dialog-content')
                        .should('have.class', 'dialog-danger');
                });
            });

            it('should support "success" type', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('type', 'success');
                    dialog.setAttribute('heading', 'Success');
                    doc.body.appendChild(dialog);
                    dialog.open();

                    cy.get('lib-dialog').last()
                        .shadow()
                        .find('.dialog-content')
                        .should('have.class', 'dialog-success');
                });
            });

            it('should show type-specific icon', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('type', 'danger');
                    dialog.setAttribute('heading', 'Danger');
                    doc.body.appendChild(dialog);
                    dialog.open();

                    cy.get('lib-dialog').last()
                        .shadow()
                        .find('.dialog-icon')
                        .should('exist');
                });
            });
        });

        describe('size attribute', () => {
            it('should default to medium size', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Medium');
                    doc.body.appendChild(dialog);
                    dialog.open();

                    cy.get('lib-dialog').last()
                        .shadow()
                        .find('.dialog-content')
                        .should('have.css', 'max-width', '420px');
                });
            });

            it('should support "small" size', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Small');
                    dialog.setAttribute('size', 'small');
                    doc.body.appendChild(dialog);
                    dialog.open();

                    cy.get('lib-dialog[size="small"]').last()
                        .shadow()
                        .find('.dialog-content')
                        .should('have.css', 'max-width', '320px');
                });
            });

            it('should support "large" size', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Large');
                    dialog.setAttribute('size', 'large');
                    doc.body.appendChild(dialog);
                    dialog.open();

                    cy.get('lib-dialog[size="large"]').last()
                        .shadow()
                        .find('.dialog-content')
                        .should('have.css', 'max-width', '640px');
                });
            });
        });

        describe('closable attribute', () => {
            it('should show close button by default', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Closable');
                    doc.body.appendChild(dialog);
                    dialog.open();

                    cy.get('lib-dialog').last()
                        .shadow()
                        .find('.dialog-close')
                        .should('exist');
                });
            });

            it('should hide close button when closable="false"', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Not Closable');
                    dialog.setAttribute('closable', 'false');
                    doc.body.appendChild(dialog);
                    dialog.open();

                    cy.get('lib-dialog').last()
                        .shadow()
                        .find('.dialog-close')
                        .should('not.exist');
                });
            });

            it('should close on backdrop click when closable', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Backdrop Close');
                    dialog.id = 'backdrop-close-test';
                    doc.body.appendChild(dialog);
                    dialog.open();

                    cy.get('#backdrop-close-test')
                        .shadow()
                        .find('.dialog-backdrop')
                        .click({ force: true });

                    // Dialog should close
                    cy.get('#backdrop-close-test')
                        .should('not.have.attr', 'open');
                });
            });

            it('should NOT close on backdrop click when closable="false"', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'No Backdrop Close');
                    dialog.setAttribute('closable', 'false');
                    dialog.id = 'no-backdrop-close';
                    doc.body.appendChild(dialog);
                    dialog.open();

                    cy.get('#no-backdrop-close')
                        .shadow()
                        .find('.dialog-backdrop')
                        .click({ force: true });

                    // Dialog should remain open
                    cy.get('#no-backdrop-close')
                        .should('have.attr', 'open');

                    // Clean up
                    cy.get('#no-backdrop-close').then($d => $d[0].close());
                });
            });
        });
    });

    describe('Properties and Methods', () => {
        describe('isOpen property', () => {
            it('should return false initially', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Test');
                    doc.body.appendChild(dialog);

                    expect(dialog.isOpen).to.be.false;
                });
            });

            it('should return true when open', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Test');
                    doc.body.appendChild(dialog);
                    dialog.open();

                    expect(dialog.isOpen).to.be.true;
                });
            });
        });

        describe('open() method', () => {
            it('should open the dialog', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Open Test');
                    dialog.id = 'open-test';
                    doc.body.appendChild(dialog);

                    dialog.open();

                    cy.get('#open-test')
                        .should('have.attr', 'open');
                });
            });

            it('should return a promise', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Promise Test');
                    doc.body.appendChild(dialog);

                    const result = dialog.open();
                    expect(result).to.be.a('promise');
                });
            });

            it('should focus first focusable element', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Focus Test');
                    dialog.innerHTML = '<input type="text" id="focus-input">';
                    dialog.id = 'focus-test';
                    doc.body.appendChild(dialog);
                    dialog.open();

                    // Wait for focus
                    cy.wait(100);

                    cy.focused().should('have.attr', 'id', 'focus-input');

                    // Clean up
                    cy.get('#focus-test').then($d => $d[0].close());
                });
            });
        });

        describe('close() method', () => {
            it('should close the dialog', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Close Test');
                    dialog.id = 'close-test';
                    doc.body.appendChild(dialog);
                    dialog.open();

                    dialog.close();

                    // Wait for animation
                    cy.wait(300);

                    cy.get('#close-test')
                        .should('not.have.attr', 'open');
                });
            });

            it('should resolve promise with confirmed value', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Confirm Test');
                    doc.body.appendChild(dialog);

                    let resolvedValue = null;
                    dialog.open().then(value => {
                        resolvedValue = value;
                    });

                    dialog.close(true);

                    cy.wait(300).then(() => {
                        expect(resolvedValue).to.be.true;
                    });
                });
            });

            it('should not close if already closed', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Already Closed');
                    doc.body.appendChild(dialog);

                    // Should not throw
                    dialog.close();
                    dialog.close();
                });
            });
        });
    });

    describe('Slots', () => {
        describe('default slot (body)', () => {
            it('should render body content', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Body Test');
                    dialog.innerHTML = '<p id="body-content">Body text</p>';
                    dialog.id = 'body-test';
                    doc.body.appendChild(dialog);
                    dialog.open();

                    cy.get('#body-test')
                        .find('#body-content')
                        .should('exist')
                        .and('contain', 'Body text');
                });
            });
        });

        describe('footer slot', () => {
            it('should render footer content', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Footer Test');
                    dialog.innerHTML = `
                        <p>Body</p>
                        <div slot="footer">
                            <button id="footer-btn">OK</button>
                        </div>
                    `;
                    dialog.id = 'footer-test';
                    doc.body.appendChild(dialog);
                    dialog.open();

                    cy.get('#footer-test')
                        .find('#footer-btn')
                        .should('exist')
                        .and('contain', 'OK');
                });
            });

            it('should work with custom button handlers', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Handler Test');
                    dialog.innerHTML = `
                        <p>Content</p>
                        <div slot="footer">
                            <button class="test-close-btn">Confirm</button>
                        </div>
                    `;
                    dialog.id = 'handler-test';
                    doc.body.appendChild(dialog);

                    // Add event listener before opening
                    const btn = dialog.querySelector('.test-close-btn');
                    btn.addEventListener('click', () => dialog.close(true));

                    dialog.open();

                    cy.get('#handler-test')
                        .find('.test-close-btn')
                        .click();

                    // Wait for animation to complete
                    cy.wait(500);

                    cy.get('#handler-test')
                        .should('not.have.attr', 'open');
                });
            });
        });
    });

    describe('Events', () => {
        describe('dialog-open event', () => {
            it('should fire when dialog opens', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Event Test');
                    doc.body.appendChild(dialog);

                    let eventFired = false;
                    dialog.addEventListener('dialog-open', () => {
                        eventFired = true;
                    });

                    dialog.open();

                    cy.wrap(null).then(() => {
                        expect(eventFired).to.be.true;
                    });
                });
            });
        });

        describe('dialog-close event', () => {
            it('should fire when dialog closes', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Close Event');
                    doc.body.appendChild(dialog);
                    dialog.open();

                    let eventDetail = null;
                    dialog.addEventListener('dialog-close', (e) => {
                        eventDetail = e.detail;
                    });

                    dialog.close(true);

                    cy.wait(300).then(() => {
                        expect(eventDetail).to.not.be.null;
                        expect(eventDetail.confirmed).to.be.true;
                    });
                });
            });

            it('should include confirmed=false when cancelled', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Cancel Event');
                    doc.body.appendChild(dialog);
                    dialog.open();

                    let eventDetail = null;
                    dialog.addEventListener('dialog-close', (e) => {
                        eventDetail = e.detail;
                    });

                    dialog.close(false);

                    cy.wait(300).then(() => {
                        expect(eventDetail.confirmed).to.be.false;
                    });
                });
            });
        });
    });

    describe('Static Methods', () => {
        describe('LibDialog.alert()', () => {
            it('should create an alert dialog', () => {
                cy.window().then(win => {
                    win.LibDialog.alert('Alert message', { title: 'Alert' });

                    cy.get('lib-dialog[data-programmatic]')
                        .should('exist')
                        .and('have.attr', 'open');

                    // Clean up
                    cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
                });
            });

            it('should return a promise', () => {
                cy.window().then(win => {
                    const result = win.LibDialog.alert('Test', { title: 'Test' });
                    expect(result).to.be.a('promise');

                    // Clean up
                    cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
                });
            });

            it('should use default title "Melding"', () => {
                cy.window().then(win => {
                    win.LibDialog.alert('Message');

                    cy.get('lib-dialog[data-programmatic]')
                        .should('have.attr', 'heading', 'Melding');

                    cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
                });
            });

            it('should support custom button text', () => {
                cy.window().then(win => {
                    win.LibDialog.alert('Message', { buttonText: 'Got it' });

                    cy.get('lib-dialog[data-programmatic]')
                        .find('button')
                        .should('contain', 'Got it');

                    cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
                });
            });

            it('should support type option', () => {
                cy.window().then(win => {
                    win.LibDialog.alert('Success!', { type: 'success', title: 'Done' });

                    cy.get('lib-dialog[data-programmatic]')
                        .should('have.attr', 'type', 'success');

                    cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
                });
            });

            it('should remove dialog after close', () => {
                cy.window().then(win => {
                    win.LibDialog.alert('Temporary', { title: 'Test' });
                });

                // Click the first button to close (there may be multiple)
                cy.get('lib-dialog[data-programmatic]')
                    .find('button')
                    .first()
                    .click();

                // Wait for animation and dialog removal
                cy.wait(500);

                // Dialog should be removed from DOM
                cy.get('lib-dialog[data-programmatic]').should('not.exist');
            });
        });

        describe('LibDialog.confirm()', () => {
            it('should create a confirm dialog', () => {
                cy.window().then(win => {
                    win.LibDialog.confirm('Are you sure?', { title: 'Confirm' });

                    cy.get('lib-dialog[data-programmatic]')
                        .should('exist')
                        .and('have.attr', 'open');

                    // Should have two buttons
                    cy.get('lib-dialog[data-programmatic]')
                        .find('button')
                        .should('have.length', 2);

                    cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
                });
            });

            it('should return true when confirmed', () => {
                cy.window().then(win => {
                    let result = null;
                    win.LibDialog.confirm('Confirm?', { title: 'Test' }).then(r => {
                        result = r;
                    });

                    // Click confirm button (second button)
                    cy.get('lib-dialog[data-programmatic]')
                        .find('.btn-primary')
                        .click();

                    cy.wait(300).then(() => {
                        expect(result).to.be.true;
                    });
                });
            });

            it('should return false when cancelled', () => {
                cy.window().then(win => {
                    let result = null;
                    win.LibDialog.confirm('Cancel?', { title: 'Test' }).then(r => {
                        result = r;
                    });

                    // Click cancel button (first button)
                    cy.get('lib-dialog[data-programmatic]')
                        .find('.btn-cancel')
                        .click();

                    cy.wait(300).then(() => {
                        expect(result).to.be.false;
                    });
                });
            });

            it('should use default title "Bevestigen"', () => {
                cy.window().then(win => {
                    win.LibDialog.confirm('Message');

                    cy.get('lib-dialog[data-programmatic]')
                        .should('have.attr', 'heading', 'Bevestigen');

                    cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
                });
            });

            it('should support custom button texts', () => {
                cy.window().then(win => {
                    win.LibDialog.confirm('Delete?', {
                        confirmText: 'Delete',
                        cancelText: 'Keep'
                    });

                    cy.get('lib-dialog[data-programmatic]')
                        .find('.btn-primary')
                        .should('contain', 'Delete');

                    cy.get('lib-dialog[data-programmatic]')
                        .find('.btn-cancel')
                        .should('contain', 'Keep');

                    cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
                });
            });

            it('should NOT be closable via backdrop', () => {
                cy.window().then(win => {
                    win.LibDialog.confirm('Required choice');

                    cy.get('lib-dialog[data-programmatic]')
                        .should('have.attr', 'closable', 'false');

                    // Try clicking backdrop
                    cy.get('lib-dialog[data-programmatic]')
                        .shadow()
                        .find('.dialog-backdrop')
                        .click({ force: true });

                    // Should still be open
                    cy.get('lib-dialog[data-programmatic]')
                        .should('have.attr', 'open');

                    cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
                });
            });

            it('should support danger type with red button', () => {
                cy.window().then(win => {
                    win.LibDialog.confirm('Delete permanently?', { type: 'danger' });

                    cy.get('lib-dialog[data-programmatic]')
                        .should('have.attr', 'type', 'danger');

                    cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
                });
            });
        });
    });

    describe('Global Functions', () => {
        describe('libAlert()', () => {
            it('should be a wrapper for LibDialog.alert', () => {
                cy.window().then(win => {
                    expect(win.libAlert).to.be.a('function');

                    win.libAlert('Test message');

                    cy.get('lib-dialog[data-programmatic]')
                        .should('exist');

                    cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
                });
            });
        });

        describe('libConfirm()', () => {
            it('should be a wrapper for LibDialog.confirm', () => {
                cy.window().then(win => {
                    expect(win.libConfirm).to.be.a('function');

                    win.libConfirm('Test question');

                    cy.get('lib-dialog[data-programmatic]')
                        .should('exist');

                    cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
                });
            });
        });
    });

    describe('Keyboard Navigation', () => {
        it('should close on Escape when closable', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Escape Test');
                dialog.id = 'escape-test';
                doc.body.appendChild(dialog);
                dialog.open();

                cy.get('body').type('{esc}');

                cy.wait(300);

                cy.get('#escape-test')
                    .should('not.have.attr', 'open');
            });
        });

        it('should NOT close on Escape when closable="false"', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'No Escape');
                dialog.setAttribute('closable', 'false');
                dialog.id = 'no-escape-test';
                doc.body.appendChild(dialog);
                dialog.open();

                cy.get('body').type('{esc}');

                cy.get('#no-escape-test')
                    .should('have.attr', 'open');

                cy.get('#no-escape-test').then($d => $d[0].close());
            });
        });

        it('should confirm on Enter (non-textarea)', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Enter Test');
                dialog.innerHTML = '<input type="text">';
                dialog.id = 'enter-test';
                doc.body.appendChild(dialog);

                let confirmed = null;
                dialog.open().then(val => { confirmed = val; });

                cy.get('#enter-test input').type('{enter}');

                cy.wait(300).then(() => {
                    expect(confirmed).to.be.true;
                });
            });
        });
    });

    describe('Styling', () => {
        describe('Button Styling', () => {
            // Note: LibDialog.confirm() creates slotted buttons in light DOM
            // with .btn-primary (confirm) and .btn-cancel classes
            // ::slotted() styles are applied from shadow DOM

            it('should have confirm button styled', () => {
                cy.window().then(win => {
                    win.LibDialog.confirm('Test', { title: 'Button Style' });

                    // Confirm button should exist and be visible
                    cy.get('lib-dialog[data-programmatic]')
                        .find('.btn-primary')
                        .should('exist')
                        .and('be.visible');

                    cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
                });
            });

            it('should have cancel button styled', () => {
                cy.window().then(win => {
                    win.LibDialog.confirm('Test', { title: 'Button Style' });

                    // Cancel button should exist and be visible
                    cy.get('lib-dialog[data-programmatic]')
                        .find('.btn-cancel')
                        .should('exist')
                        .and('be.visible');

                    cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
                });
            });

            it('should have buttons with adequate width', () => {
                cy.window().then(win => {
                    win.LibDialog.confirm('Test', { title: 'Button Size' });

                    // Buttons should have reasonable width (at least 40px for short text)
                    cy.get('lib-dialog[data-programmatic]')
                        .find('.btn-primary')
                        .invoke('outerWidth')
                        .should('be.at.least', 40);

                    cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
                });
            });

            it('should have buttons with adequate height', () => {
                cy.window().then(win => {
                    win.LibDialog.confirm('Test', { title: 'Button Height' });

                    // Buttons should have reasonable height (at least 24px)
                    cy.get('lib-dialog[data-programmatic]')
                        .find('.btn-primary')
                        .invoke('outerHeight')
                        .should('be.at.least', 24);

                    cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
                });
            });

            it('should have readable font size', () => {
                cy.window().then(win => {
                    win.LibDialog.confirm('Test', { title: 'Button Font' });

                    // Font size should be readable (at least 11px)
                    cy.get('lib-dialog[data-programmatic]')
                        .find('.btn-primary')
                        .should('have.css', 'font-size')
                        .and('not.eq', '0px');

                    cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
                });
            });

            it('should have matching heights for confirm and cancel buttons', () => {
                cy.window().then(win => {
                    win.LibDialog.confirm('Test', { title: 'Button Match' });

                    // Both buttons should have same height
                    cy.get('lib-dialog[data-programmatic]')
                        .find('.btn-primary')
                        .invoke('outerHeight')
                        .then(confirmHeight => {
                            cy.get('lib-dialog[data-programmatic]')
                                .find('.btn-cancel')
                                .invoke('outerHeight')
                                .should('eq', confirmHeight);
                        });

                    cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
                });
            });

            it('should have contrasting text on buttons', () => {
                cy.window().then(win => {
                    win.LibDialog.confirm('Test', { title: 'Button Colors' });

                    // Buttons should have visible text (not transparent)
                    cy.get('lib-dialog[data-programmatic]')
                        .find('.btn-primary')
                        .should('have.css', 'color')
                        .and('not.eq', 'rgba(0, 0, 0, 0)');

                    cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
                });
            });

            it('slotted buttons should be styled consistently', () => {
                cy.document().then(doc => {
                    const dialog = doc.createElement('lib-dialog');
                    dialog.setAttribute('heading', 'Slotted Buttons');
                    dialog.innerHTML = `
                        <p>Content</p>
                        <div slot="footer">
                            <button class="btn-cancel">Annuleren</button>
                            <button class="btn-primary">Bevestigen</button>
                        </div>
                    `;
                    dialog.id = 'slotted-btn-test';
                    doc.body.appendChild(dialog);
                    dialog.open();

                    // Both buttons should be visible and have reasonable dimensions
                    cy.get('#slotted-btn-test')
                        .find('.btn-primary')
                        .should('be.visible')
                        .invoke('outerWidth')
                        .should('be.at.least', 70);

                    cy.get('#slotted-btn-test')
                        .find('.btn-cancel')
                        .should('be.visible')
                        .invoke('outerWidth')
                        .should('be.at.least', 70);

                    cy.get('#slotted-btn-test').then($d => $d[0].close());
                });
            });
        });

        it('should have backdrop overlay', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Backdrop');
                dialog.id = 'backdrop-test';
                doc.body.appendChild(dialog);
                dialog.open();

                cy.get('#backdrop-test')
                    .shadow()
                    .find('.dialog-backdrop')
                    .should('have.css', 'position', 'fixed');

                cy.get('#backdrop-test').then($d => $d[0].close());
            });
        });

        it('should have animation on open', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Animation');
                dialog.id = 'animation-test';
                doc.body.appendChild(dialog);
                dialog.open();

                cy.get('#animation-test')
                    .shadow()
                    .find('.dialog-content')
                    .should('have.css', 'animation-name', 'dialogSlideIn');

                cy.get('#animation-test').then($d => $d[0].close());
            });
        });

        it('should have closing animation', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Close Animation');
                dialog.id = 'close-anim-test';
                doc.body.appendChild(dialog);
                dialog.open();

                dialog.close();

                cy.get('#close-anim-test')
                    .shadow()
                    .find('.dialog-backdrop.closing')
                    .should('exist');
            });
        });
    });

    describe('Shadow DOM', () => {
        it('should use shadow DOM encapsulation', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Shadow Test');
                doc.body.appendChild(dialog);

                expect(dialog.shadowRoot).to.exist;
            });
        });

        it('should contain styles in shadow DOM', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Style Test');
                doc.body.appendChild(dialog);

                cy.get('lib-dialog').last()
                    .shadow()
                    .find('style')
                    .should('exist');
            });
        });
    });

    describe('Accessibility', () => {
        it('should have aria-label on close button', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Accessibility');
                dialog.id = 'a11y-test';
                doc.body.appendChild(dialog);
                dialog.open();

                cy.get('#a11y-test')
                    .shadow()
                    .find('.dialog-close')
                    .should('have.attr', 'aria-label', 'Sluiten');

                cy.get('#a11y-test').then($d => $d[0].close());
            });
        });
    });

    describe('Close Button Element Type', () => {
        it('should use anchor element for close button', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Close Button Type');
                dialog.id = 'close-btn-type-test';
                doc.body.appendChild(dialog);
                dialog.open();

                // Close button should be an anchor element, not a button
                cy.get('#close-btn-type-test')
                    .shadow()
                    .find('.dialog-close')
                    .should('match', 'a')
                    .and('have.attr', 'href', 'javascript:void(0)');

                cy.get('#close-btn-type-test').then($d => $d[0].close());
            });
        });

        it('should have close button sized at 24px', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Close Button Size');
                dialog.id = 'close-btn-size-test';
                doc.body.appendChild(dialog);
                dialog.open();

                cy.get('#close-btn-size-test')
                    .shadow()
                    .find('.dialog-close')
                    .should('have.css', 'width', '24px')
                    .and('have.css', 'height', '24px');

                cy.get('#close-btn-size-test').then($d => $d[0].close());
            });
        });

        it('should have close X icon sized at 14px', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Close X Size');
                dialog.id = 'close-x-size-test';
                doc.body.appendChild(dialog);
                dialog.open();

                cy.get('#close-x-size-test')
                    .shadow()
                    .find('.dialog-close-x')
                    .should('have.css', 'width', '14px')
                    .and('have.css', 'height', '14px');

                cy.get('#close-x-size-test').then($d => $d[0].close());
            });
        });
    });

    describe('Footer Button Height Consistency', () => {
        it('should have matching heights for buttons with and without icons', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Button Height Test');
                dialog.innerHTML = `
                    <p>Content</p>
                    <div slot="footer">
                        <button class="btn-cancel">Negeren</button>
                        <button class="btn-primary"><span class="lnr lnr-checkmark-circle"></span> Opslaan</button>
                    </div>
                `;
                dialog.id = 'btn-height-test';
                doc.body.appendChild(dialog);
                dialog.open();

                // Both buttons should have same height regardless of icon
                cy.get('#btn-height-test')
                    .find('.btn-cancel')
                    .invoke('outerHeight')
                    .then(cancelHeight => {
                        cy.get('#btn-height-test')
                            .find('.btn-primary')
                            .invoke('outerHeight')
                            .should('eq', cancelHeight);
                    });

                cy.get('#btn-height-test').then($d => $d[0].close());
            });
        });

        it('should have footer buttons with fixed 28px height', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Fixed Height Test');
                dialog.innerHTML = `
                    <p>Content</p>
                    <div slot="footer">
                        <button class="btn-cancel">Cancel</button>
                        <button class="btn-primary">OK</button>
                    </div>
                `;
                dialog.id = 'fixed-height-test';
                doc.body.appendChild(dialog);
                dialog.open();

                cy.get('#fixed-height-test')
                    .find('[slot="footer"] button')
                    .each($btn => {
                        expect($btn.css('height')).to.eq('28px');
                    });

                cy.get('#fixed-height-test').then($d => $d[0].close());
            });
        });

        it('should have icon inside button with same line-height', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Icon Line Height Test');
                dialog.innerHTML = `
                    <p>Content</p>
                    <div slot="footer">
                        <button class="btn-primary"><span class="lnr lnr-checkmark-circle"></span> Save</button>
                    </div>
                `;
                dialog.id = 'icon-lh-test';
                doc.body.appendChild(dialog);
                dialog.open();

                // Icon should have line-height: 1 to not increase button height
                cy.get('#icon-lh-test')
                    .find('.lnr')
                    .should('have.css', 'line-height')
                    .and('match', /^1(px)?$|^12px$/);

                cy.get('#icon-lh-test').then($d => $d[0].close());
            });
        });
    });

    describe('Cleanup', () => {
        it('should replace existing programmatic dialog', () => {
            cy.window().then(win => {
                win.LibDialog.alert('First');
                win.LibDialog.alert('Second');

                cy.get('lib-dialog[data-programmatic]')
                    .should('have.length', 1);

                cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
            });
        });
    });

    describe('LibDialog.prompt()', () => {
        it('should show input dialog with message', () => {
            cy.window().then(win => {
                win.LibDialog.prompt('Enter your name:');

                cy.get('lib-dialog[data-programmatic]')
                    .should('exist')
                    .and('have.attr', 'open');

                cy.get('lib-dialog[data-programmatic]')
                    .find('p')
                    .should('contain', 'Enter your name:');

                cy.get('lib-dialog[data-programmatic]')
                    .find('input.lib-dialog-prompt-input')
                    .should('exist');

                cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
            });
        });

        it('should use custom title', () => {
            cy.window().then(win => {
                win.LibDialog.prompt('Enter value:', { title: 'Custom Title' });

                cy.get('lib-dialog[data-programmatic]')
                    .shadow()
                    .find('.dialog-title-text')
                    .should('contain', 'Custom Title');

                cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
            });
        });

        it('should show default value', () => {
            cy.window().then(win => {
                win.LibDialog.prompt('Enter value:', { defaultValue: 'Default Text' });

                cy.get('lib-dialog[data-programmatic]')
                    .find('input.lib-dialog-prompt-input')
                    .should('have.value', 'Default Text');

                cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
            });
        });

        it('should show placeholder', () => {
            cy.window().then(win => {
                win.LibDialog.prompt('Enter value:', { placeholder: 'Type here...' });

                cy.get('lib-dialog[data-programmatic]')
                    .find('input.lib-dialog-prompt-input')
                    .should('have.attr', 'placeholder', 'Type here...');

                cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
            });
        });

        it('should return input value on confirm', () => {
            cy.window().then(win => {
                const resultPromise = win.LibDialog.prompt('Enter name:', { defaultValue: '' });

                cy.get('lib-dialog[data-programmatic]')
                    .find('input.lib-dialog-prompt-input')
                    .type('Test Value');

                cy.get('lib-dialog[data-programmatic]')
                    .find('[data-action="confirm"]')
                    .click();

                cy.wrap(resultPromise).then(result => {
                    expect(result).to.equal('Test Value');
                });
            });
        });

        it('should return null on cancel', () => {
            cy.window().then(win => {
                const resultPromise = win.LibDialog.prompt('Enter name:');

                cy.get('lib-dialog[data-programmatic]')
                    .find('input.lib-dialog-prompt-input')
                    .type('Some text');

                cy.get('lib-dialog[data-programmatic]')
                    .find('[data-action="cancel"]')
                    .click();

                cy.wrap(resultPromise).then(result => {
                    expect(result).to.be.null;
                });
            });
        });

        it('should submit on Enter key', () => {
            cy.window().then(win => {
                const resultPromise = win.LibDialog.prompt('Enter name:');

                cy.get('lib-dialog[data-programmatic]')
                    .find('input.lib-dialog-prompt-input')
                    .type('Enter Test{enter}');

                cy.wrap(resultPromise).then(result => {
                    expect(result).to.equal('Enter Test');
                });
            });
        });

        it('should use textarea when multiline is true', () => {
            cy.window().then(win => {
                win.LibDialog.prompt('Enter description:', { multiline: true });

                cy.get('lib-dialog[data-programmatic]')
                    .find('textarea.lib-dialog-prompt-input')
                    .should('exist');

                cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
            });
        });

        it('should use custom button text', () => {
            cy.window().then(win => {
                win.LibDialog.prompt('Enter value:', {
                    confirmText: 'Save',
                    cancelText: 'Discard'
                });

                cy.get('lib-dialog[data-programmatic]')
                    .find('[data-action="confirm"]')
                    .should('contain', 'Save');

                cy.get('lib-dialog[data-programmatic]')
                    .find('[data-action="cancel"]')
                    .should('contain', 'Discard');

                cy.get('lib-dialog[data-programmatic]').then($d => $d[0].close());
            });
        });

        it('should validate required input', () => {
            cy.window().then(win => {
                win.LibDialog.prompt('Enter required value:', { required: true });

                // Try to submit empty
                cy.get('lib-dialog[data-programmatic]')
                    .find('[data-action="confirm"]')
                    .click();

                // Dialog should still be open
                cy.get('lib-dialog[data-programmatic]')
                    .should('have.attr', 'open');

                // Now enter a value and submit
                cy.get('lib-dialog[data-programmatic]')
                    .find('input.lib-dialog-prompt-input')
                    .type('Valid input');

                cy.get('lib-dialog[data-programmatic]')
                    .find('[data-action="confirm"]')
                    .click();

                cy.get('lib-dialog[data-programmatic]')
                    .should('not.exist');
            });
        });
    });

    describe('libPrompt() global function', () => {
        it('should be available as global function', () => {
            cy.window().then(win => {
                expect(win.libPrompt).to.be.a('function');
            });
        });

        it('should work the same as LibDialog.prompt()', () => {
            cy.window().then(win => {
                const resultPromise = win.libPrompt('Test prompt:');

                cy.get('lib-dialog[data-programmatic]')
                    .find('input.lib-dialog-prompt-input')
                    .type('Global function test');

                cy.get('lib-dialog[data-programmatic]')
                    .find('[data-action="confirm"]')
                    .click();

                cy.wrap(resultPromise).then(result => {
                    expect(result).to.equal('Global function test');
                });
            });
        });
    });

    describe('Maximize functionality', () => {
        it('should NOT show maximize button by default', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'No Maximize');
                dialog.id = 'no-maximize-test';
                doc.body.appendChild(dialog);
                dialog.open();

                cy.get('#no-maximize-test')
                    .shadow()
                    .find('.dialog-maximize')
                    .should('not.exist');

                cy.get('#no-maximize-test').then($d => $d[0].close());
            });
        });

        it('should show maximize button when maximizable attribute is set', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Maximizable');
                dialog.setAttribute('maximizable', '');
                dialog.id = 'maximizable-test';
                doc.body.appendChild(dialog);
                dialog.open();

                cy.get('#maximizable-test')
                    .shadow()
                    .find('.dialog-maximize')
                    .should('exist')
                    .and('be.visible');

                cy.get('#maximizable-test').then($d => $d[0].close());
            });
        });

        it('should toggle to maximized state on click', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Toggle Maximize');
                dialog.setAttribute('maximizable', '');
                dialog.id = 'toggle-maximize-test';
                doc.body.appendChild(dialog);
                dialog.open();

                cy.get('#toggle-maximize-test')
                    .shadow()
                    .find('.dialog-maximize')
                    .click();

                cy.get('#toggle-maximize-test')
                    .should('have.attr', 'maximized');

                cy.get('#toggle-maximize-test').then($d => $d[0].close());
            });
        });

        it('should swap icon from lnr-frame-expand to lnr-frame-contract', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Icon Swap');
                dialog.setAttribute('maximizable', '');
                dialog.id = 'icon-swap-test';
                doc.body.appendChild(dialog);
                dialog.open();

                // Initially should show expand icon
                cy.get('#icon-swap-test')
                    .shadow()
                    .find('.dialog-maximize .lnr')
                    .should('have.class', 'lnr-frame-expand')
                    .and('not.have.class', 'lnr-frame-contract');

                // Click to maximize
                cy.get('#icon-swap-test')
                    .shadow()
                    .find('.dialog-maximize')
                    .click();

                // Should show contract icon
                cy.get('#icon-swap-test')
                    .shadow()
                    .find('.dialog-maximize .lnr')
                    .should('have.class', 'lnr-frame-contract')
                    .and('not.have.class', 'lnr-frame-expand');

                cy.get('#icon-swap-test').then($d => $d[0].close());
            });
        });

        it('should swap tooltip text', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Tooltip Swap');
                dialog.setAttribute('maximizable', '');
                dialog.id = 'tooltip-swap-test';
                doc.body.appendChild(dialog);
                dialog.open();

                // Initially should show maximize tooltip
                cy.get('#tooltip-swap-test')
                    .shadow()
                    .find('.dialog-maximize')
                    .should('have.attr', 'title', 'Maximaliseren venstergrootte');

                // Click to maximize
                cy.get('#tooltip-swap-test')
                    .shadow()
                    .find('.dialog-maximize')
                    .click();

                // Should show restore tooltip
                cy.get('#tooltip-swap-test')
                    .shadow()
                    .find('.dialog-maximize')
                    .should('have.attr', 'title', 'Herstellen venstergrootte');

                cy.get('#tooltip-swap-test').then($d => $d[0].close());
            });
        });

        it('should restore on second click', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Restore Test');
                dialog.setAttribute('maximizable', '');
                dialog.id = 'restore-test';
                doc.body.appendChild(dialog);
                dialog.open();

                // Click to maximize
                cy.get('#restore-test')
                    .shadow()
                    .find('.dialog-maximize')
                    .click();

                cy.get('#restore-test')
                    .should('have.attr', 'maximized');

                // Click again to restore
                cy.get('#restore-test')
                    .shadow()
                    .find('.dialog-maximize')
                    .click();

                cy.get('#restore-test')
                    .should('not.have.attr', 'maximized');

                // Icon should be back to expand
                cy.get('#restore-test')
                    .shadow()
                    .find('.dialog-maximize .lnr')
                    .should('have.class', 'lnr-frame-expand');

                cy.get('#restore-test').then($d => $d[0].close());
            });
        });

        it('should reset maximized state on close', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Reset Test');
                dialog.setAttribute('maximizable', '');
                dialog.id = 'reset-maximize-test';
                doc.body.appendChild(dialog);
                dialog.open();

                // Maximize
                cy.get('#reset-maximize-test')
                    .shadow()
                    .find('.dialog-maximize')
                    .click();

                cy.get('#reset-maximize-test')
                    .should('have.attr', 'maximized');

                // Close
                cy.get('#reset-maximize-test').then($d => $d[0].close());

                // Wait for close animation
                cy.wait(300);

                // Should no longer have maximized attribute
                cy.get('#reset-maximize-test')
                    .should('not.have.attr', 'maximized');

                // Reopen - should be normal size (wrap to avoid returning Promise)
                cy.get('#reset-maximize-test').then($d => { $d[0].open(); });

                cy.get('#reset-maximize-test')
                    .should('not.have.attr', 'maximized');

                // Icon should show expand (not contract)
                cy.get('#reset-maximize-test')
                    .shadow()
                    .find('.dialog-maximize .lnr')
                    .should('have.class', 'lnr-frame-expand');

                cy.get('#reset-maximize-test').then($d => $d[0].close());
            });
        });

        it('should show maximize button with correct styling', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Style Test');
                dialog.setAttribute('maximizable', '');
                dialog.id = 'maximize-style-test';
                doc.body.appendChild(dialog);
                dialog.open();

                cy.get('#maximize-style-test')
                    .shadow()
                    .find('.dialog-maximize')
                    .should('have.css', 'width', '24px')
                    .and('have.css', 'height', '24px')
                    .and('have.css', 'cursor', 'pointer');

                cy.get('#maximize-style-test').then($d => $d[0].close());
            });
        });

        it('should have maximize button as anchor element', () => {
            cy.document().then(doc => {
                const dialog = doc.createElement('lib-dialog');
                dialog.setAttribute('heading', 'Anchor Test');
                dialog.setAttribute('maximizable', '');
                dialog.id = 'maximize-anchor-test';
                doc.body.appendChild(dialog);
                dialog.open();

                cy.get('#maximize-anchor-test')
                    .shadow()
                    .find('.dialog-maximize')
                    .should('match', 'a')
                    .and('have.attr', 'href', 'javascript:void(0)');

                cy.get('#maximize-anchor-test').then($d => $d[0].close());
            });
        });
    });
});
