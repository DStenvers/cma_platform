/**
 * API Commands
 *
 * Commands for API testing and data manipulation.
 */

/**
 * Make an API request to form_api.php
 * @param {string} action - API action (tree, detail, save, delete, etc.)
 * @param {string} form - Form name
 * @param {object} params - Additional parameters
 */
Cypress.Commands.add('apiRequest', (action, form, params = {}) => {
    const queryParams = new URLSearchParams({
        action,
        form,
        ...params
    });

    return cy.request({
        url: `/form_api.php?${queryParams.toString()}`,
        failOnStatusCode: false
    });
});

/**
 * Get tree data from API
 * @param {string} form - Form name
 * @param {object} filters - Search filters
 */
Cypress.Commands.add('apiGetTree', (form, filters = {}) => {
    return cy.apiRequest('tree', form, filters);
});

/**
 * Get record detail from API
 * @param {string} form - Form name
 * @param {number|string} id - Record ID
 */
Cypress.Commands.add('apiGetDetail', (form, id) => {
    return cy.apiRequest('detail', form, { id });
});

/**
 * Create a record via API
 * @param {string} form - Form name
 * @param {object} data - Record data
 */
Cypress.Commands.add('apiCreate', (form, data) => {
    return cy.request({
        method: 'POST',
        url: `/form_api.php?action=save&form=${form}`,
        body: data,
        failOnStatusCode: false
    });
});

/**
 * Update a record via API
 * @param {string} form - Form name
 * @param {number|string} id - Record ID
 * @param {object} data - Record data
 */
Cypress.Commands.add('apiUpdate', (form, id, data) => {
    return cy.request({
        method: 'POST',
        url: `/form_api.php?action=save&form=${form}&id=${id}`,
        body: data,
        failOnStatusCode: false
    });
});

/**
 * Delete a record via API
 * @param {string} form - Form name
 * @param {number|string} id - Record ID
 */
Cypress.Commands.add('apiDelete', (form, id) => {
    return cy.request({
        method: 'POST',
        url: `/form_api.php?action=delete&form=${form}&id=${id}`,
        failOnStatusCode: false
    });
});

/**
 * Search records via API
 * @param {string} form - Form name
 * @param {object} searchParams - Search parameters
 */
Cypress.Commands.add('apiSearch', (form, searchParams = {}) => {
    return cy.apiRequest('tree', form, searchParams);
});

/**
 * Get options for a combobox field
 * @param {string} form - Form name
 * @param {string} field - Field name
 */
Cypress.Commands.add('apiGetOptions', (form, field) => {
    return cy.apiRequest('options', form, { field });
});

/**
 * Intercept API calls for testing
 * @param {string} action - API action to intercept
 * @param {string} alias - Alias for the intercept
 */
Cypress.Commands.add('interceptApi', (action, alias) => {
    cy.intercept('**/form_api.php*action=' + action + '*').as(alias);
});

/**
 * Wait for API response
 * @param {string} alias - Intercept alias
 */
Cypress.Commands.add('waitForApi', (alias) => {
    return cy.wait('@' + alias);
});

/**
 * Assert API response status
 * @param {object} response - Cypress response object
 * @param {number} expectedStatus - Expected HTTP status
 */
Cypress.Commands.add('assertApiStatus', { prevSubject: true }, (response, expectedStatus) => {
    expect(response.status).to.eq(expectedStatus);
    return cy.wrap(response);
});

/**
 * Assert API response has property
 * @param {object} response - Cypress response object
 * @param {string} property - Property name
 */
Cypress.Commands.add('assertApiHasProperty', { prevSubject: true }, (response, property) => {
    expect(response.body).to.have.property(property);
    return cy.wrap(response);
});

/**
 * Create test data via API and store for cleanup
 * @param {string} form - Form name
 * @param {object} data - Record data
 */
Cypress.Commands.add('createTestRecord', (form, data) => {
    return cy.apiCreate(form, data).then(response => {
        if (response.status === 200 && response.body.id) {
            // Store for cleanup
            const testRecords = Cypress.env('testRecords') || [];
            testRecords.push({ form, id: response.body.id });
            Cypress.env('testRecords', testRecords);
        }
        return cy.wrap(response);
    });
});

/**
 * Cleanup all test records created during tests
 */
Cypress.Commands.add('cleanupTestRecords', () => {
    const testRecords = Cypress.env('testRecords') || [];
    testRecords.forEach(record => {
        cy.apiDelete(record.form, record.id);
    });
    Cypress.env('testRecords', []);
});
