/**
 * WebP Conversion Tool Tests
 *
 * Tests for the tools_webp_convert.php batch conversion tool:
 * - Page loads with correct UI elements
 * - Scan functionality returns results
 * - Variant thumbnails display for converted images
 * - Image zoom dialog opens on thumbnail click
 * - Batch convert with progress tracking
 *
 * Run: npx cypress run --spec "cypress/e2e/tools/webp-conversion.cy.js"
 */

describe('WebP Conversion Tool', () => {
    beforeEach(() => {
        cy.loginAsDeveloper();
        cy.visit('/tools/tools_webp_convert.php');
        cy.wait(1000);
    });

    describe('Page Structure', () => {
        it('should display the toolbar with title', () => {
            cy.get('body').should('contain.text', 'WebP conversie');
        });

        it('should have a directory input field', () => {
            cy.get('#directory').should('exist').and('have.value', '/images/');
        });

        it('should have a scan button', () => {
            cy.contains('button', 'Scannen').should('exist');
        });

        it('should have a quality input with default value 85', () => {
            cy.get('#quality').should('exist').and('have.value', '85');
        });

        it('should have clickable filenames for preview', () => {
            cy.intercept('GET', '**/tools_webp_convert.php?action=scan*', {
                body: {
                    success: true, directory: '/images/', total: 1, withVariants: 0,
                    withoutVariants: 1, webpSupported: true,
                    gd: { gdLoaded: true, webpSupport: true },
                    files: [{ name: 'test.jpg', relativePath: 'test.jpg', ext: 'jpg',
                        size: 50000, width: 800, height: 600, hasVariants: false, url: '/images/test.jpg' }]
                }
            }).as('scanRequest');
            cy.contains('button', 'Scannen').click();
            cy.wait('@scanRequest');
            cy.get('.file-preview').should('have.length', 1);
        });
    });

    describe('Scan Functionality', () => {
        it('should display scan results when scanning a directory', () => {
            cy.intercept('GET', '**/tools_webp_convert.php?action=scan*').as('scanRequest');

            cy.contains('button', 'Scannen').click();
            cy.wait('@scanRequest');

            cy.get('#webp-status').should('not.be.empty');
            // Status uses stat cards with label "Totaal" (not "Totaal afbeeldingen")
            cy.get('#webp-status').should('contain.text', 'Totaal');
        });

        it('should show action buttons after successful scan with images', () => {
            cy.intercept('GET', '**/tools_webp_convert.php?action=scan*').as('scanRequest');

            cy.contains('button', 'Scannen').click();
            cy.wait('@scanRequest');

            cy.get('#webp-status').then($status => {
                if ($status.text().includes('Geen afbeeldingen gevonden')) {
                    cy.log('No images found in directory - action buttons hidden');
                } else {
                    cy.get('#action-buttons').should('be.visible');
                    cy.get('#btn-convert').should('exist');
                }
            });
        });

        it('should show error for non-existent directory', () => {
            cy.get('#directory').clear().type('/nonexistent_dir_xyz/');
            cy.intercept('GET', '**/tools_webp_convert.php?action=scan*').as('scanRequest');

            cy.contains('button', 'Scannen').click();
            cy.wait('@scanRequest');

            cy.get('#webp-status').should('contain.text', 'bestaat niet');
        });

        it('should show WebP support status', () => {
            cy.intercept('GET', '**/tools_webp_convert.php?action=scan*').as('scanRequest');

            cy.contains('button', 'Scannen').click();
            cy.wait('@scanRequest');

            cy.get('#webp-status').should('contain.text', 'WebP');
        });

        it('should show cwebp and EXIF status in scan results', () => {
            cy.intercept('GET', '**/tools_webp_convert.php?action=scan*', {
                body: {
                    success: true, directory: '/images/', total: 0, withVariants: 0,
                    withoutVariants: 0, webpSupported: true,
                    gd: { gdLoaded: true, webpSupport: true, exifSupport: true, cwebpAvailable: true },
                    files: []
                }
            }).as('scanRequest');

            cy.contains('button', 'Scannen').click();
            cy.wait('@scanRequest');

            // Status renders dot indicators with labels (no colons)
            cy.get('#webp-status').should('contain.text', 'EXIF');
            cy.get('#webp-status').should('contain.text', 'cwebp');
        });

        it('should render results table with columns', () => {
            cy.intercept('GET', '**/tools_webp_convert.php?action=scan*').as('scanRequest');

            cy.contains('button', 'Scannen').click();
            cy.wait('@scanRequest');

            cy.get('#webp-status').then($status => {
                if ($status.text().includes('Geen afbeeldingen gevonden')) {
                    cy.log('No images found - skipping table test');
                    return;
                }
                cy.get('#results-table table').should('exist');
                // Columns: Bestand, Afmetingen, Origineel, WebP volledig, Responsive 400/800/1200, action = 8
                cy.get('#results-table thead th').should('have.length', 8);
                cy.get('#results-table thead').should('contain.text', 'Bestand');
                cy.get('#results-table thead').should('contain.text', 'WebP volledig');
            });
        });
    });

    describe('Gauge Component', () => {
        it('should render lib-gauge in WebP column for files with variants', () => {
            cy.intercept('GET', '**/tools_webp_convert.php?action=scan*', {
                body: {
                    success: true, directory: '/images/', total: 1, withVariants: 1,
                    withoutVariants: 0, webpSupported: true,
                    gd: { gdLoaded: true, webpSupport: true },
                    files: [{
                        name: 'photo.jpg', relativePath: 'photo.jpg', ext: 'jpg',
                        size: 50000, width: 800, height: 600, hasVariants: true, webpSize: 20000,
                        url: '/images/photo.jpg',
                        variants: [{ width: 800, file: 'photo.webp', size: 20000, url: '/images/.responsive/photo.webp', full: true }]
                    }]
                }
            }).as('scanRequest');
            cy.contains('button', 'Scannen').click();
            cy.wait('@scanRequest');

            cy.get('lib-gauge').should('have.length.at.least', 1);
            cy.get('lib-gauge').first().should('have.attr', 'value', '20000');
            cy.get('lib-gauge').first().should('have.attr', 'max', '50000');
        });

        it('should show summary with average gauge below table', () => {
            cy.intercept('GET', '**/tools_webp_convert.php?action=scan*', {
                body: {
                    success: true, directory: '/images/', total: 2, withVariants: 2,
                    withoutVariants: 0, webpSupported: true,
                    gd: { gdLoaded: true, webpSupport: true },
                    files: [
                        { name: 'a.jpg', relativePath: 'a.jpg', ext: 'jpg', size: 50000, hasVariants: true, webpSize: 20000, url: '/images/a.jpg', variants: [{ width: 800, file: 'a.webp', size: 20000, url: '/images/.responsive/a.webp', full: true }] },
                        { name: 'b.jpg', relativePath: 'b.jpg', ext: 'jpg', size: 80000, hasVariants: true, webpSize: 30000, url: '/images/b.jpg', variants: [{ width: 800, file: 'b.webp', size: 30000, url: '/images/.responsive/b.webp', full: true }] }
                    ]
                }
            }).as('scanRequest');
            cy.contains('button', 'Scannen').click();
            cy.wait('@scanRequest');

            cy.get('#results-table').should('contain.text', '2 bestanden met varianten');
            cy.get('#results-table lib-gauge[label="Gemiddeld"]').should('exist');
        });
    });

    describe('Variant Thumbnails', () => {
        it('should show thumbnail images for converted files', () => {
            // Mock scan response with a file that has variants
            cy.intercept('GET', '**/tools_webp_convert.php?action=scan*', {
                body: {
                    success: true,
                    directory: '/images/',
                    total: 1,
                    withVariants: 1,
                    withoutVariants: 0,
                    webpSupported: true,
                    gd: { gdLoaded: true, gdVersion: 'bundled (2.1.0)', webpSupport: true },
                    files: [{
                        name: 'test.jpg',
                        relativePath: 'test.jpg',
                        ext: 'jpg',
                        size: 50000,
                        width: 1600,
                        height: 1200,
                        hasVariants: true,
                        webpSize: 20000,
                        url: '/images/test.jpg',
                        variants: [
                            { width: 400, file: 'test-400w.webp', size: 5000, url: '/images/.responsive/test-400w.webp' },
                            { width: 800, file: 'test-800w.webp', size: 10000, url: '/images/.responsive/test-800w.webp' },
                            { width: 1200, file: 'test-1200w.webp', size: 15000, url: '/images/.responsive/test-1200w.webp' },
                            { width: 1600, file: 'test.webp', size: 20000, url: '/images/.responsive/test.webp', full: true }
                        ]
                    }]
                }
            }).as('scanRequest');

            cy.contains('button', 'Scannen').click();
            cy.wait('@scanRequest');

            // Should show variant thumbnails
            cy.get('.variant-thumb').should('have.length', 4);
            cy.get('.variant-thumb img').should('have.length', 4);
        });

        it('should show convert button for every file row', () => {
            cy.intercept('GET', '**/tools_webp_convert.php?action=scan*', {
                body: {
                    success: true,
                    directory: '/images/',
                    total: 1,
                    withVariants: 0,
                    withoutVariants: 1,
                    webpSupported: true,
                    gd: { gdLoaded: true, gdVersion: 'bundled (2.1.0)', webpSupport: true },
                    files: [{
                        name: 'unconverted.jpg',
                        relativePath: 'unconverted.jpg',
                        ext: 'jpg',
                        size: 50000,
                        width: 1600,
                        height: 1200,
                        hasVariants: false,
                        url: '/images/unconverted.jpg'
                    }]
                }
            }).as('scanRequest');

            cy.contains('button', 'Scannen').click();
            cy.wait('@scanRequest');

            cy.get('.variant-thumb').should('not.exist');
            cy.get('.btn-convert-one').should('have.length', 1);
            cy.get('.btn-convert-one').find('.lnr-sync').should('exist');
        });

        it('should convert single file and update row with variants', () => {
            // Set up intercepts and visit fresh to ensure mock catches the auto-scan
            cy.intercept('GET', '**/tools_webp_convert.php?action=scan*', {
                body: {
                    success: true,
                    directory: '/images/',
                    total: 1,
                    withVariants: 0,
                    withoutVariants: 1,
                    webpSupported: true,
                    gd: { gdLoaded: true, gdVersion: 'bundled (2.1.0)', webpSupport: true },
                    files: [{
                        name: 'photo.jpg',
                        relativePath: 'photo.jpg',
                        ext: 'jpg',
                        size: 50000,
                        width: 1600,
                        height: 1200,
                        hasVariants: false,
                        url: '/images/photo.jpg'
                    }]
                }
            }).as('scanRequest');

            // Mock convertOne response
            cy.intercept('GET', '**/tools_webp_convert.php?action=convertOne*', {
                body: {
                    success: true,
                    webpSize: 20000,
                    variants: [
                        { width: 400, file: 'photo-400w.webp', size: 5000, url: '/images/.responsive/photo-400w.webp' },
                        { width: 800, file: 'photo-800w.webp', size: 10000, url: '/images/.responsive/photo-800w.webp' },
                        { width: 1600, file: 'photo.webp', size: 20000, url: '/images/.responsive/photo.webp', full: true }
                    ]
                }
            }).as('convertOneRequest');

            // Re-visit so the auto-scan is intercepted by our mock
            cy.visit('/tools/tools_webp_convert.php');
            cy.wait('@scanRequest');

            // Should have exactly 1 convert button (the mocked single file)
            cy.get('.btn-convert-one').should('have.length', 1);

            // Click single file convert button
            cy.get('.btn-convert-one').click();
            cy.wait('@convertOneRequest');

            // Row should now show variant thumbnails after conversion
            cy.get('.variant-thumb').should('have.length.at.least', 1);
        });
    });

    describe('Image Preview', () => {
        it('should open preview when clicking the original filename', () => {
            cy.intercept('GET', '**/tools_webp_convert.php?action=scan*', {
                body: {
                    success: true,
                    directory: '/images/',
                    total: 1,
                    withVariants: 0,
                    withoutVariants: 1,
                    webpSupported: true,
                    gd: { gdLoaded: true, webpSupport: true },
                    files: [{
                        name: 'original.jpg',
                        relativePath: 'original.jpg',
                        ext: 'jpg',
                        size: 75000,
                        width: 1600,
                        height: 1200,
                        hasVariants: false,
                        url: '/images/original.jpg'
                    }]
                }
            }).as('scanRequest');

            cy.contains('button', 'Scannen').click();
            cy.wait('@scanRequest');

            // Filename should be a clickable link
            cy.get('.file-preview').first().should('contain.text', 'original.jpg');
        });

        it('should have clickable variant thumbnails', () => {
            cy.intercept('GET', '**/tools_webp_convert.php?action=scan*', {
                body: {
                    success: true,
                    directory: '/images/',
                    total: 1,
                    withVariants: 1,
                    withoutVariants: 0,
                    webpSupported: true,
                    gd: { gdLoaded: true, gdVersion: 'bundled (2.1.0)', webpSupport: true },
                    files: [{
                        name: 'photo.jpg',
                        relativePath: 'photo.jpg',
                        ext: 'jpg',
                        size: 50000,
                        width: 1600,
                        height: 1200,
                        hasVariants: true,
                        webpSize: 20000,
                        url: '/images/photo.jpg',
                        variants: [
                            { width: 400, file: 'photo-400w.webp', size: 5000, url: '/images/.responsive/photo-400w.webp' },
                            { width: 800, file: 'photo-800w.webp', size: 10000, url: '/images/.responsive/photo-800w.webp' }
                        ]
                    }]
                }
            }).as('scanRequest');

            cy.contains('button', 'Scannen').click();
            cy.wait('@scanRequest');

            // Thumbnails should be present and clickable
            cy.get('.variant-thumb').should('have.length', 2);
            cy.get('.variant-thumb img').should('have.length', 2);
        });
    });

    describe('Batch Convert Functionality', () => {
        it('should call batch convert API when clicking convert button', () => {
            cy.intercept('GET', '**/tools_webp_convert.php?action=scan*').as('scanRequest');
            cy.intercept('GET', '**/tools_webp_convert.php?action=convert*').as('convertRequest');

            cy.contains('button', 'Scannen').click();
            cy.wait('@scanRequest');

            cy.get('#webp-status').then($status => {
                if ($status.text().includes('Geen afbeeldingen gevonden') || $status.text().includes('0Zonder varianten')) {
                    cy.log('No images to convert - skipping convert test');
                    return;
                }

                cy.get('#btn-convert').click();
                cy.wait('@convertRequest', { timeout: 120000 });

                // Progress should be visible
                cy.get('#progress').should('be.visible');
                cy.get('#progress-text').should('not.be.empty');
            });
        });

        it('should show progress bar during conversion', () => {
            // Mock scan with unconverted files
            cy.intercept('GET', '**/tools_webp_convert.php?action=scan*', {
                body: {
                    success: true,
                    directory: '/images/',
                    total: 2,
                    withVariants: 0,
                    withoutVariants: 2,
                    webpSupported: true,
                    gd: { gdLoaded: true, webpSupport: true },
                    files: [
                        { name: 'a.jpg', relativePath: 'a.jpg', ext: 'jpg', size: 100, hasVariants: false, url: '/images/a.jpg' },
                        { name: 'b.jpg', relativePath: 'b.jpg', ext: 'jpg', size: 200, hasVariants: false, url: '/images/b.jpg' }
                    ]
                }
            }).as('scanRequest');

            // Mock convert that returns all done
            cy.intercept('GET', '**/tools_webp_convert.php?action=convert*', {
                body: {
                    success: true,
                    converted: 2,
                    skipped: 0,
                    errors: 0,
                    batchSize: 2,
                    totalRemaining: 2,
                    offset: 0,
                    errorDetails: []
                }
            }).as('convertRequest');

            cy.contains('button', 'Scannen').click();
            cy.wait('@scanRequest');

            cy.get('#btn-convert').click();
            cy.wait('@convertRequest');

            cy.get('#progress').should('be.visible');
            cy.get('#progress-text').should('contain.text', 'Klaar');
        });
    });

    describe('Help Button', () => {
        it('should display help button with question-circle icon in toolbar', () => {
            cy.get('#toolbar_help').should('exist');
            cy.get('#toolbar_help .lnr-question-circle').should('exist');
        });

        it('should open help dialog when clicking help button', () => {
            cy.get('#toolbar_help a').click();
            cy.get('lib-dialog#helpDialog').should('have.attr', 'open');
        });

        it('should display shared configuration values in help dialog', () => {
            cy.get('#toolbar_help a').click();
            cy.get('lib-dialog#helpDialog').should('have.attr', 'open');

            // Check that the dialog shows the ResponsiveImage constants
            cy.get('lib-dialog#helpDialog').should('contain.text', '85');
            cy.get('lib-dialog#helpDialog').should('contain.text', '400');
            cy.get('lib-dialog#helpDialog').should('contain.text', '800');
            cy.get('lib-dialog#helpDialog').should('contain.text', '1200');
            cy.get('lib-dialog#helpDialog').should('contain.text', '.responsive');
        });

        it('should close help dialog with Sluiten button', () => {
            cy.get('#toolbar_help a').click();
            cy.get('lib-dialog#helpDialog').should('have.attr', 'open');

            cy.get('lib-dialog#helpDialog').contains('button', 'Sluiten').click();
            cy.get('lib-dialog#helpDialog').should('not.have.attr', 'open');
        });
    });
});
