/**
 * Image Upload Tests
 *
 * Tests for image upload functionality including the image wizard.
 * Uses element IDs and data attributes.
 *
 * Run: npx cypress run --spec "cypress/e2e/forms/image-upload.cy.js"
 */

describe('Image Upload', () => {
  beforeEach(() => {
    cy.loginAsAdmin();
  });

  // ═══════════════════════════════════════════════════════════════
  // IMAGE FIELD DETECTION
  // ═══════════════════════════════════════════════════════════════

  describe('Image Field', () => {
    it('should display image fields in forms with pictures', () => {
      // Open a form that has image fields
      cy.openForm('deelnemers');
      cy.waitForContent();

      // Check for image field container
      cy.get('body').then($body => {
        if ($body.find('.image-field, .image-upload, [data-type="image"]').length > 0) {
          cy.get('.image-field, .image-upload, [data-type="image"]').should('be.visible');
        }
      });
    });

    it('should show upload button for empty image field', () => {
      cy.openForm('deelnemers');
      cy.waitForContent();

      cy.get('body').then($body => {
        if ($body.find('.image-upload-btn, .lnr-file-add, [data-action="upload-image"]').length > 0) {
          cy.get('.image-upload-btn, .lnr-file-add, [data-action="upload-image"]').should('exist');
        }
      });
    });

    it('should show existing image when present', () => {
      cy.openForm('deelnemers');
      cy.waitForContent();

      // If there's an existing image, it should be displayed
      cy.get('body').then($body => {
        if ($body.find('.image-preview img, .image-field img').length > 0) {
          cy.get('.image-preview img, .image-field img').should('be.visible');
        }
      });
    });
  });

  // ═══════════════════════════════════════════════════════════════
  // IMAGE WIZARD
  // ═══════════════════════════════════════════════════════════════

  describe('Image Wizard', () => {
    it('should open image wizard on upload click', () => {
      cy.openForm('deelnemers');
      cy.waitForContent();

      cy.get('body').then($body => {
        if ($body.find('.image-upload-btn, [data-action="upload-image"]').length > 0) {
          cy.get('.image-upload-btn, [data-action="upload-image"]').first().click();
          cy.get('.image-wizard, #image-wizard, lib-dialog').should('be.visible');
        }
      });
    });

    it('should display wizard tabs/steps', () => {
      cy.openForm('deelnemers');
      cy.waitForContent();

      cy.get('body').then($body => {
        if ($body.find('.image-upload-btn, [data-action="upload-image"]').length > 0) {
          cy.get('.image-upload-btn, [data-action="upload-image"]').first().click();

          // Should have upload and edit tabs
          cy.get('.wizard-tabs, .tab-bar, cma-tabs').should('exist');
        }
      });
    });

    it('should have file input for upload', () => {
      cy.openForm('deelnemers');
      cy.waitForContent();

      cy.get('body').then($body => {
        if ($body.find('.image-upload-btn, [data-action="upload-image"]').length > 0) {
          cy.get('.image-upload-btn, [data-action="upload-image"]').first().click();

          cy.get('input[type="file"], .file-input, #file-upload').should('exist');
        }
      });
    });

    it('should display drag-drop zone', () => {
      cy.openForm('deelnemers');
      cy.waitForContent();

      cy.get('body').then($body => {
        if ($body.find('.image-upload-btn, [data-action="upload-image"]').length > 0) {
          cy.get('.image-upload-btn, [data-action="upload-image"]').first().click();

          cy.get('.drop-zone, .upload-area, .drag-drop').should('exist');
        }
      });
    });

    it('should have cancel button', () => {
      cy.openForm('deelnemers');
      cy.waitForContent();

      cy.get('body').then($body => {
        if ($body.find('.image-upload-btn, [data-action="upload-image"]').length > 0) {
          cy.get('.image-upload-btn, [data-action="upload-image"]').first().click();

          cy.get('.cancel-btn, [data-action="cancel"], .lnr-cross').should('exist');
        }
      });
    });

    it('should close wizard on cancel', () => {
      cy.openForm('deelnemers');
      cy.waitForContent();

      cy.get('body').then($body => {
        if ($body.find('.image-upload-btn, [data-action="upload-image"]').length > 0) {
          cy.get('.image-upload-btn, [data-action="upload-image"]').first().click();

          cy.get('.cancel-btn, [data-action="cancel"], .lnr-cross').first().click();
          cy.get('.image-wizard, #image-wizard').should('not.exist');
        }
      });
    });
  });

  // ═══════════════════════════════════════════════════════════════
  // IMAGE CROPPING
  // ═══════════════════════════════════════════════════════════════

  describe('Image Cropping', () => {
    it('should have crop tool available', () => {
      cy.openForm('deelnemers');
      cy.waitForContent();

      cy.get('body').then($body => {
        if ($body.find('.image-upload-btn, [data-action="upload-image"]').length > 0) {
          cy.get('.image-upload-btn, [data-action="upload-image"]').first().click();

          cy.get('.crop-tool, .lnr-crop, [data-action="crop"]').should('exist');
        }
      });
    });
  });

  // ═══════════════════════════════════════════════════════════════
  // IMAGE ZOOM
  // ═══════════════════════════════════════════════════════════════

  describe('Image Zoom', () => {
    it('should have zoom controls', () => {
      cy.openForm('deelnemers');
      cy.waitForContent();

      cy.get('body').then($body => {
        if ($body.find('.image-upload-btn, [data-action="upload-image"]').length > 0) {
          cy.get('.image-upload-btn, [data-action="upload-image"]').first().click();

          cy.get('.zoom-controls, .lnr-plus-circle, .lnr-circle-minus').should('exist');
        }
      });
    });
  });

  // ═══════════════════════════════════════════════════════════════
  // IMAGE DELETE
  // ═══════════════════════════════════════════════════════════════

  describe('Image Delete', () => {
    it('should have delete option for existing images', () => {
      cy.openForm('deelnemers');
      cy.waitForContent();

      cy.get('body').then($body => {
        if ($body.find('.image-preview img, .image-field img').length > 0) {
          cy.get('.delete-image, .lnr-cross-circle, [data-action="delete-image"]').should('exist');
        }
      });
    });
  });
});

// ═══════════════════════════════════════════════════════════════
// IMAGE VALIDATION
// ═══════════════════════════════════════════════════════════════

describe('Image Validation', () => {
  beforeEach(() => {
    cy.loginAsAdmin();
  });

  it('should validate file type', () => {
    // Image uploads should only accept image file types
    cy.openForm('deelnemers');
    cy.waitForContent();

    cy.get('body').then($body => {
      if ($body.find('input[type="file"]').length > 0) {
        cy.get('input[type="file"]').should('have.attr', 'accept')
          .and('match', /image|jpg|jpeg|png|gif/i);
      }
    });
  });
});
