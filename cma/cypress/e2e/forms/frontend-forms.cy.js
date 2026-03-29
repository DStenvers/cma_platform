/**
 * Frontend Forms Test
 *
 * Tests front-end pages that require login as Elizabeth Wansink.
 * Verifies pages load without PHP errors and key elements are present.
 */

const SITE_URL = 'http://172.29.208.1';

// PHP error patterns to detect in page body (matched case-insensitive)
const PHP_ERROR_PATTERNS = [
    'Fatal error',
    'ErrorException',
    'Undefined variable',
    'Call to undefined function',
    'Class .* not found',
    'Maximum execution time',
    'Syntax error',
    'Parse error',
    'Database query failed',
    'operator ontbreekt'
];

// Case-sensitive patterns (to avoid matching JS property names like 'typeError')
const PHP_ERROR_PATTERNS_CASE_SENSITIVE = [
    'TypeError'
];

/**
 * Assert that the page body does not contain PHP error messages
 */
function assertNoPhpErrors() {
    cy.get('body').invoke('text').then((text) => {
        PHP_ERROR_PATTERNS.forEach((pattern) => {
            expect(text).not.to.match(new RegExp(pattern, 'i'),
                `Page should not contain PHP error: ${pattern}`);
        });
        PHP_ERROR_PATTERNS_CASE_SENSITIVE.forEach((pattern) => {
            expect(text).not.to.match(new RegExp(pattern),
                `Page should not contain PHP error: ${pattern}`);
        });
    });
}

describe('Frontend Forms - Elizabeth Wansink', () => {
    beforeEach(() => {
        cy.frontendLogin();
    });

    describe('Voordracht Praktijkopleider', () => {
        it('should load without PHP errors', () => {
            cy.visit(`${SITE_URL}/formulier_voordracht_praktijkopleider.php`, {
                failOnStatusCode: false
            });
            assertNoPhpErrors();
        });

        it('should have page styling (head section with CSS)', () => {
            cy.visit(`${SITE_URL}/formulier_voordracht_praktijkopleider.php`, {
                failOnStatusCode: false
            });
            // Verify the HEAD section was rendered with CSS
            cy.get('head link[rel="stylesheet"]').should('have.length.greaterThan', 0);
        });

        it('should display the 3 praktijkopleider combo boxes', () => {
            cy.visit(`${SITE_URL}/formulier_voordracht_praktijkopleider.php`, {
                failOnStatusCode: false
            });
            // The 3 combo/select fields for praktijkopleiders
            cy.get('select[name="vervangt_huidige_po1"], input[name="vervangt_huidige_po1"]')
                .should('exist');
            cy.get('select[name="vervangt_huidige_po2"], input[name="vervangt_huidige_po2"]')
                .should('exist');
            cy.get('select[name="vervangt_huidige_po3"], input[name="vervangt_huidige_po3"]')
                .should('exist');
        });

        it('should display form fields after the 3 combo boxes', () => {
            cy.visit(`${SITE_URL}/formulier_voordracht_praktijkopleider.php`, {
                failOnStatusCode: false
            });
            // After the 3 combos, there should be additional form fields
            // The form should have input fields beyond just the 3 selects
            cy.get('body').then(($body) => {
                const inputs = $body.find('input[type="text"], textarea, input[type="checkbox"], input[type="radio"]');
                // There should be more than just hidden fields - the form body should be rendered
                expect(inputs.length).to.be.greaterThan(0,
                    'Form should have visible input fields after the combo boxes');
            });
        });

        it('should have the form element', () => {
            cy.visit(`${SITE_URL}/formulier_voordracht_praktijkopleider.php`, {
                failOnStatusCode: false
            });
            // The main form should exist
            cy.get('form').should('exist');
        });
    });

    describe('Formulier Dispensatie', () => {
        beforeEach(() => {
            cy.visit(`${SITE_URL}/formulier_dispensatie.php`, {
                failOnStatusCode: false
            });
        });

        it('should load without PHP errors', () => {
            assertNoPhpErrors();
        });

        it('should have page styling (head section with CSS)', () => {
            cy.get('head link[rel="stylesheet"]').should('have.length.greaterThan', 0);
        });

        it('should have the main form element', () => {
            cy.get('form[name="Main"]').should('exist');
        });

        it('should have the tabbed form structure', () => {
            cy.get('#tabbed_form').should('exist');
            // Tab navigation
            cy.get('#tabbed_form ul.ui-tabs-nav').should('exist');
            cy.get('#tabbed_form ul.ui-tabs-nav li').should('have.length.greaterThan', 0);
        });

        it('should have betreft_aanvraag radio buttons', () => {
            // 3 radio buttons: Supervisor, Werkbegeleider, Leertherapeut
            cy.get('input[type="radio"][name="betreft_aanvraag"]').should('have.length', 3);
            cy.get('input[type="radio"][name="betreft_aanvraag"][value="Supervisor"]').should('exist');
            cy.get('input[type="radio"][name="betreft_aanvraag"][value="Werkbegeleider"]').should('exist');
            cy.get('input[type="radio"][name="betreft_aanvraag"][value="Leertherapeut"]').should('exist');
        });

        it('should have voornaam and achternaam text fields', () => {
            cy.get('input[type="text"][name="voornaam_functionaris"]').should('exist');
            cy.get('input[type="text"][name="achternaam_functionaris"]').should('exist');
        });

        it('should have CV upload section', () => {
            cy.get('input[name="cv"]').should('exist');
            cy.get('.fine-uploader_docs[data-field="cv"]').should('exist');
        });

        it('should have opleiding checkboxes for training types', () => {
            // GZ, PT, KP, KNP, OG checkboxes
            cy.get('input[type="checkbox"][name="opleiding"][value="GZ"]').should('exist');
            cy.get('input[type="checkbox"][name="opleiding"][value="PT"]').should('exist');
            cy.get('input[type="checkbox"][name="opleiding"][value="KP"]').should('exist');
            cy.get('input[type="checkbox"][name="opleiding"][value="KNP"]').should('exist');
            cy.get('input[type="checkbox"][name="opleiding"][value="OG"]').should('exist');
        });

        it('should have situatie toelichting and motivatie textareas', () => {
            cy.get('textarea[name="situatie_toelichting"]').should('exist');
            cy.get('textarea[name="motivatie"]').should('exist');
            cy.get('textarea[name="opmerkingen"]').should('exist');
        });

        it('should have deelnemerslijst section in tabs-2', () => {
            cy.get('#tabs-2 #deelnemerslijst').should('exist');
        });

        it('should have hidden fields for code and deelname_ids', () => {
            cy.get('input[type="hidden"][name="code"]').should('exist');
            cy.get('input[type="hidden"][name="deelname_ids"]').should('exist');
        });
    });

    describe('Eigen Gegevens (Profile Page)', () => {
        it('should load without PHP errors', () => {
            cy.visit(`${SITE_URL}/?pageaction=eigen_gegevens`, {
                failOnStatusCode: false
            });
            assertNoPhpErrors();
        });

        it('should have page styling', () => {
            cy.visit(`${SITE_URL}/?pageaction=eigen_gegevens`, {
                failOnStatusCode: false
            });
            cy.get('head link[rel="stylesheet"]').should('have.length.greaterThan', 0);
        });

        it('should display profile content', () => {
            cy.visit(`${SITE_URL}/?pageaction=eigen_gegevens`, {
                failOnStatusCode: false
            });
            // Profile page should have form fields or profile data
            cy.get('body').invoke('text').should('not.be.empty');
            assertNoPhpErrors();
        });
    });

    describe('Inventarisatie Index New', () => {
        it('should load without PHP errors', () => {
            cy.visit(`${SITE_URL}/inventarisatie/index_new.php?hideheader=false`, {
                failOnStatusCode: false
            });
            assertNoPhpErrors();
        });

        it('should have page styling', () => {
            cy.visit(`${SITE_URL}/inventarisatie/index_new.php?hideheader=false`, {
                failOnStatusCode: false
            });
            cy.get('head link[rel="stylesheet"]').should('have.length.greaterThan', 0);
        });

        it('should display page content', () => {
            cy.visit(`${SITE_URL}/inventarisatie/index_new.php?hideheader=false`, {
                failOnStatusCode: false
            });
            cy.get('body').invoke('text').should('not.be.empty');
            assertNoPhpErrors();
        });
    });
});
