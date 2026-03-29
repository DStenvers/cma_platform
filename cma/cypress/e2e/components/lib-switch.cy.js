/**
 * Cypress tests for lib-switch web component
 */
describe('lib-switch', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit('/tools/storybook.php');
        // Wait for page to load
        cy.get('body').should('be.visible');
    });

    describe('Keyboard support', () => {
        it('should toggle with Space key', () => {
            // Create a test switch dynamically
            cy.document().then(doc => {
                const container = doc.createElement('div');
                container.id = 'switch-test-container';
                container.style.cssText = 'padding: 20px;';
                container.innerHTML = '<lib-switch id="testSwitch" name="test"></lib-switch>';
                doc.body.appendChild(container);
            });

            cy.get('#testSwitch').should('exist');
            cy.get('#testSwitch').scrollIntoView();
            cy.get('#testSwitch').find('.lib-switch').focus();
            cy.get('#testSwitch').should('not.have.attr', 'checked');

            // Press Space to toggle on
            cy.get('#testSwitch').find('.lib-switch').trigger('keydown', { key: ' ', force: true });
            cy.get('#testSwitch').should('have.attr', 'checked');

            // Press Space to toggle off
            cy.get('#testSwitch').find('.lib-switch').trigger('keydown', { key: ' ', force: true });
            cy.get('#testSwitch').should('not.have.attr', 'checked');
        });

        it('should check with ArrowRight key', () => {
            cy.document().then(doc => {
                const existing = doc.getElementById('switch-test-container');
                if (existing) existing.remove();
                const container = doc.createElement('div');
                container.id = 'switch-test-container';
                container.style.cssText = 'padding: 20px;';
                container.innerHTML = '<lib-switch id="testSwitch2" name="test2"></lib-switch>';
                doc.body.appendChild(container);
            });

            cy.get('#testSwitch2').should('not.have.attr', 'checked');
            cy.get('#testSwitch2').scrollIntoView();
            cy.get('#testSwitch2').find('.lib-switch').focus();

            // ArrowRight should check (turn on)
            cy.get('#testSwitch2').find('.lib-switch').trigger('keydown', { key: 'ArrowRight', force: true });
            cy.get('#testSwitch2').should('have.attr', 'checked');

            // ArrowRight again should NOT uncheck (already on)
            cy.get('#testSwitch2').find('.lib-switch').trigger('keydown', { key: 'ArrowRight', force: true });
            cy.get('#testSwitch2').should('have.attr', 'checked');
        });

        it('should uncheck with ArrowLeft key', () => {
            cy.document().then(doc => {
                const existing = doc.getElementById('switch-test-container');
                if (existing) existing.remove();
                const container = doc.createElement('div');
                container.id = 'switch-test-container';
                container.style.cssText = 'padding: 20px;';
                container.innerHTML = '<lib-switch id="testSwitch3" name="test3" checked></lib-switch>';
                doc.body.appendChild(container);
            });

            cy.get('#testSwitch3').should('have.attr', 'checked');
            cy.get('#testSwitch3').scrollIntoView();
            cy.get('#testSwitch3').find('.lib-switch').focus();

            // ArrowLeft should uncheck (turn off)
            cy.get('#testSwitch3').find('.lib-switch').trigger('keydown', { key: 'ArrowLeft', force: true });
            cy.get('#testSwitch3').should('not.have.attr', 'checked');

            // ArrowLeft again should NOT check (already off)
            cy.get('#testSwitch3').find('.lib-switch').trigger('keydown', { key: 'ArrowLeft', force: true });
            cy.get('#testSwitch3').should('not.have.attr', 'checked');
        });

        it('should check with ArrowUp and uncheck with ArrowDown', () => {
            cy.document().then(doc => {
                const existing = doc.getElementById('switch-test-container');
                if (existing) existing.remove();
                const container = doc.createElement('div');
                container.id = 'switch-test-container';
                container.style.cssText = 'padding: 20px;';
                container.innerHTML = '<lib-switch id="testSwitch4" name="test4"></lib-switch>';
                doc.body.appendChild(container);
            });

            cy.get('#testSwitch4').should('not.have.attr', 'checked');
            cy.get('#testSwitch4').scrollIntoView();
            cy.get('#testSwitch4').find('.lib-switch').focus();

            // ArrowUp should check
            cy.get('#testSwitch4').find('.lib-switch').trigger('keydown', { key: 'ArrowUp', force: true });
            cy.get('#testSwitch4').should('have.attr', 'checked');

            // ArrowDown should uncheck
            cy.get('#testSwitch4').find('.lib-switch').trigger('keydown', { key: 'ArrowDown', force: true });
            cy.get('#testSwitch4').should('not.have.attr', 'checked');
        });

        it('should not toggle when disabled', () => {
            cy.document().then(doc => {
                const existing = doc.getElementById('switch-test-container');
                if (existing) existing.remove();
                const container = doc.createElement('div');
                container.id = 'switch-test-container';
                container.style.cssText = 'padding: 20px;';
                container.innerHTML = '<lib-switch id="testSwitch5" name="test5" disabled></lib-switch>';
                doc.body.appendChild(container);
            });

            cy.get('#testSwitch5').should('not.have.attr', 'checked');
            cy.get('#testSwitch5').find('.lib-switch').trigger('keydown', { key: 'ArrowRight', force: true });
            cy.get('#testSwitch5').should('not.have.attr', 'checked');
        });
    });
});
