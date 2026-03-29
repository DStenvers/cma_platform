/**
 * lib-datepicker Web Component Tests
 *
 * Tests for the lib-datepicker custom element - a date picker component
 * with calendar popup and smart date auto-fill features.
 */

describe('lib-datepicker Web Component', () => {
    beforeEach(() => {
        cy.loginAsAdmin();
        cy.visit('/main.php');
    });

    describe('Component Registration', () => {
        it('should be defined in customElements registry', () => {
            cy.window().then(win => {
                expect(win.customElements.get('lib-datepicker')).to.exist;
            });
        });

        it('should render as a custom element', () => {
            cy.document().then(doc => {
                const datepicker = doc.createElement('lib-datepicker');
                datepicker.setAttribute('name', 'test-date');
                doc.body.appendChild(datepicker);

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-input')
                    .should('exist');
            });
        });
    });

    describe('Smart Date Auto-fill', () => {
        describe('Partial date (day-month only)', () => {
            it('should auto-add current year when entering day-month only', () => {
                const currentYear = new Date().getFullYear();

                cy.document().then(doc => {
                    const datepicker = doc.createElement('lib-datepicker');
                    datepicker.setAttribute('name', 'test-date');
                    datepicker.setAttribute('format', 'dd-mm-yyyy');
                    doc.body.appendChild(datepicker);

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .clear()
                        .type('15-6{enter}');

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .should('have.value', `15-06-${currentYear}`);
                });
            });

            it('should handle single digit day and month', () => {
                const currentYear = new Date().getFullYear();

                cy.document().then(doc => {
                    const datepicker = doc.createElement('lib-datepicker');
                    datepicker.setAttribute('name', 'test-date');
                    datepicker.setAttribute('format', 'dd-mm-yyyy');
                    doc.body.appendChild(datepicker);

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .clear()
                        .type('5-3{enter}');

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .should('have.value', `05-03-${currentYear}`);
                });
            });

            it('should work with dot separator', () => {
                const currentYear = new Date().getFullYear();

                cy.document().then(doc => {
                    const datepicker = doc.createElement('lib-datepicker');
                    datepicker.setAttribute('name', 'test-date');
                    datepicker.setAttribute('format', 'dd-mm-yyyy');
                    doc.body.appendChild(datepicker);

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .clear()
                        .type('12.7{enter}');

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .should('have.value', `12-07-${currentYear}`);
                });
            });

            it('should work with slash separator', () => {
                const currentYear = new Date().getFullYear();

                cy.document().then(doc => {
                    const datepicker = doc.createElement('lib-datepicker');
                    datepicker.setAttribute('name', 'test-date');
                    datepicker.setAttribute('format', 'dd-mm-yyyy');
                    doc.body.appendChild(datepicker);

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .clear()
                        .type('25/12{enter}');

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .should('have.value', `25-12-${currentYear}`);
                });
            });
        });

        describe('Smart year expansion', () => {
            it('should expand 2-digit year <= 40 to 20xx', () => {
                cy.document().then(doc => {
                    const datepicker = doc.createElement('lib-datepicker');
                    datepicker.setAttribute('name', 'test-date');
                    datepicker.setAttribute('format', 'dd-mm-yyyy');
                    doc.body.appendChild(datepicker);

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .clear()
                        .type('15-06-25{enter}');

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .should('have.value', '15-06-2025');
                });
            });

            it('should expand year 40 to 2040', () => {
                cy.document().then(doc => {
                    const datepicker = doc.createElement('lib-datepicker');
                    datepicker.setAttribute('name', 'test-date');
                    datepicker.setAttribute('format', 'dd-mm-yyyy');
                    doc.body.appendChild(datepicker);

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .clear()
                        .type('01-01-40{enter}');

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .should('have.value', '01-01-2040');
                });
            });

            it('should expand 2-digit year > 40 to 19xx', () => {
                cy.document().then(doc => {
                    const datepicker = doc.createElement('lib-datepicker');
                    datepicker.setAttribute('name', 'test-date');
                    datepicker.setAttribute('format', 'dd-mm-yyyy');
                    doc.body.appendChild(datepicker);

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .clear()
                        .type('15-06-95{enter}');

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .should('have.value', '15-06-1995');
                });
            });

            it('should expand year 41 to 1941', () => {
                cy.document().then(doc => {
                    const datepicker = doc.createElement('lib-datepicker');
                    datepicker.setAttribute('name', 'test-date');
                    datepicker.setAttribute('format', 'dd-mm-yyyy');
                    doc.body.appendChild(datepicker);

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .clear()
                        .type('01-01-41{enter}');

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .should('have.value', '01-01-1941');
                });
            });

            it('should expand 3-digit year to add 1000', () => {
                cy.document().then(doc => {
                    const datepicker = doc.createElement('lib-datepicker');
                    datepicker.setAttribute('name', 'test-date');
                    datepicker.setAttribute('format', 'dd-mm-yyyy');
                    doc.body.appendChild(datepicker);

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .clear()
                        .type('15-06-999{enter}');

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .should('have.value', '15-06-1999');
                });
            });

            it('should keep 4-digit years as-is', () => {
                cy.document().then(doc => {
                    const datepicker = doc.createElement('lib-datepicker');
                    datepicker.setAttribute('name', 'test-date');
                    datepicker.setAttribute('format', 'dd-mm-yyyy');
                    doc.body.appendChild(datepicker);

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .clear()
                        .type('15-06-2024{enter}');

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .should('have.value', '15-06-2024');
                });
            });
        });

        describe('Zero padding', () => {
            it('should zero-pad single digit day and month', () => {
                cy.document().then(doc => {
                    const datepicker = doc.createElement('lib-datepicker');
                    datepicker.setAttribute('name', 'test-date');
                    datepicker.setAttribute('format', 'dd-mm-yyyy');
                    doc.body.appendChild(datepicker);

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .clear()
                        .type('5-3-2024{enter}');

                    cy.get('lib-datepicker').last()
                        .shadow()
                        .find('.datepicker-input')
                        .should('have.value', '05-03-2024');
                });
            });
        });
    });

    describe('Calendar Popup', () => {
        it('should open calendar when clicking input', () => {
            cy.document().then(doc => {
                const datepicker = doc.createElement('lib-datepicker');
                datepicker.setAttribute('name', 'test-date');
                doc.body.appendChild(datepicker);

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-input')
                    .click();

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-calendar')
                    .should('be.visible');
            });
        });

        it('should open calendar when clicking icon', () => {
            cy.document().then(doc => {
                const datepicker = doc.createElement('lib-datepicker');
                datepicker.setAttribute('name', 'test-date');
                doc.body.appendChild(datepicker);

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-icon')
                    .click();

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-calendar')
                    .should('be.visible');
            });
        });

        it('should close calendar when pressing Escape', () => {
            cy.document().then(doc => {
                const datepicker = doc.createElement('lib-datepicker');
                datepicker.setAttribute('name', 'test-date');
                doc.body.appendChild(datepicker);

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-input')
                    .click();

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-calendar')
                    .should('be.visible');

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-input')
                    .type('{esc}');

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-calendar')
                    .should('not.be.visible');
            });
        });

        it('should select date from calendar and update input', () => {
            cy.document().then(doc => {
                const datepicker = doc.createElement('lib-datepicker');
                datepicker.setAttribute('name', 'test-date');
                datepicker.setAttribute('format', 'dd-mm-yyyy');
                doc.body.appendChild(datepicker);

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-input')
                    .click();

                // Click the 15th day (class is datepicker-day, not calendar-day)
                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-day:not(.other-month)')
                    .contains('15')
                    .click();

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-input')
                    .invoke('val')
                    .should('include', '15-');
            });
        });

        it('should navigate to previous month', () => {
            cy.document().then(doc => {
                const datepicker = doc.createElement('lib-datepicker');
                datepicker.setAttribute('name', 'test-date');
                datepicker.setAttribute('value', '2024-06-15');
                doc.body.appendChild(datepicker);

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-input')
                    .click();

                // Class is datepicker-title, not calendar-month-name
                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-title')
                    .should('contain', 'Juni');

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('[data-action="prev-month"]')
                    .click();

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-title')
                    .should('contain', 'Mei');
            });
        });

        it('should navigate to next month', () => {
            cy.document().then(doc => {
                const datepicker = doc.createElement('lib-datepicker');
                datepicker.setAttribute('name', 'test-date');
                datepicker.setAttribute('value', '2024-06-15');
                doc.body.appendChild(datepicker);

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-input')
                    .click();

                // Class is datepicker-title, not calendar-month-name
                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-title')
                    .should('contain', 'Juni');

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('[data-action="next-month"]')
                    .click();

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-title')
                    .should('contain', 'Juli');
            });
        });
    });

    describe('Today and Clear buttons', () => {
        it('should set today date when clicking Today button', () => {
            const today = new Date();
            const expectedDay = String(today.getDate()).padStart(2, '0');
            const expectedMonth = String(today.getMonth() + 1).padStart(2, '0');
            const expectedYear = today.getFullYear();

            cy.document().then(doc => {
                const datepicker = doc.createElement('lib-datepicker');
                datepicker.setAttribute('name', 'test-date');
                datepicker.setAttribute('format', 'dd-mm-yyyy');
                doc.body.appendChild(datepicker);

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-input')
                    .click();

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('[data-action="today"]')
                    .click();

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-input')
                    .should('have.value', `${expectedDay}-${expectedMonth}-${expectedYear}`);
            });
        });

        it('should clear date when clicking Clear button', () => {
            cy.document().then(doc => {
                const datepicker = doc.createElement('lib-datepicker');
                datepicker.setAttribute('name', 'test-date');
                datepicker.setAttribute('value', '2024-06-15');
                doc.body.appendChild(datepicker);

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-input')
                    .click();

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('[data-action="clear"]')
                    .click();

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-input')
                    .should('have.value', '');
            });
        });
    });

    describe('Value attribute', () => {
        it('should display initial value in correct format', () => {
            cy.document().then(doc => {
                const datepicker = doc.createElement('lib-datepicker');
                datepicker.setAttribute('name', 'test-date');
                datepicker.setAttribute('format', 'dd-mm-yyyy');
                datepicker.setAttribute('value', '2024-06-15');
                doc.body.appendChild(datepicker);

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-input')
                    .should('have.value', '15-06-2024');
            });
        });

        it('should update display when value attribute changes', () => {
            cy.document().then(doc => {
                const datepicker = doc.createElement('lib-datepicker');
                datepicker.setAttribute('name', 'test-date');
                datepicker.setAttribute('format', 'dd-mm-yyyy');
                datepicker.setAttribute('value', '2024-06-15');
                doc.body.appendChild(datepicker);

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-input')
                    .should('have.value', '15-06-2024');

                cy.get('lib-datepicker').last().then(el => {
                    el[0].setAttribute('value', '2024-12-25');
                });

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-input')
                    .should('have.value', '25-12-2024');
            });
        });
    });

    describe('Disabled and Readonly states', () => {
        it('should not open calendar when disabled', () => {
            cy.document().then(doc => {
                const datepicker = doc.createElement('lib-datepicker');
                datepicker.setAttribute('name', 'test-date');
                datepicker.setAttribute('disabled', '');
                doc.body.appendChild(datepicker);

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-input')
                    .click({ force: true });

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-calendar')
                    .should('not.be.visible');
            });
        });

        it('should not open calendar when readonly', () => {
            cy.document().then(doc => {
                const datepicker = doc.createElement('lib-datepicker');
                datepicker.setAttribute('name', 'test-date');
                datepicker.setAttribute('readonly', '');
                doc.body.appendChild(datepicker);

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-input')
                    .click({ force: true });

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-calendar')
                    .should('not.be.visible');
            });
        });

        it('should hide calendar icon when readonly', () => {
            cy.document().then(doc => {
                const datepicker = doc.createElement('lib-datepicker');
                datepicker.setAttribute('name', 'test-date');
                datepicker.setAttribute('readonly', '');
                datepicker.setAttribute('value', '2024-06-15');
                doc.body.appendChild(datepicker);

                // Calendar icon should be hidden
                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-icon')
                    .should('not.be.visible');

                // Input should still show the date value
                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-input')
                    .should('have.value', '15-06-2024');
            });
        });

        it('should show plain text without border when readonly', () => {
            cy.document().then(doc => {
                const datepicker = doc.createElement('lib-datepicker');
                datepicker.setAttribute('name', 'test-date');
                datepicker.setAttribute('readonly', '');
                datepicker.setAttribute('value', '2024-06-15');
                doc.body.appendChild(datepicker);

                // Wrapper should have no border
                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-wrapper')
                    .should('have.css', 'border-style', 'none');
            });
        });
    });

    describe('Change event', () => {
        it('should fire change event when date is selected', () => {
            cy.document().then(doc => {
                const datepicker = doc.createElement('lib-datepicker');
                datepicker.setAttribute('name', 'test-date');
                doc.body.appendChild(datepicker);

                let changeEventFired = false;
                datepicker.addEventListener('change', () => {
                    changeEventFired = true;
                });

                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-input')
                    .click();

                // Class is datepicker-day, not calendar-day
                cy.get('lib-datepicker').last()
                    .shadow()
                    .find('.datepicker-day:not(.other-month)')
                    .first()
                    .click()
                    .then(() => {
                        expect(changeEventFired).to.be.true;
                    });
            });
        });
    });
});
