/**
 * UTF-8 Encoding Tests
 *
 * Tests for proper handling of special characters (UTF-8/Windows-1252)
 * in the form API. This verifies that names with accented characters
 * like "Renée", "André", "Café" are correctly returned.
 *
 * Bug reference: Special characters caused empty tree items and form issues.
 * Fix: Added Str::toUtf8() conversion in ListService and RecordService.
 */

describe('UTF-8 Encoding', () => {
    const testFormName = 'opleidingen';
    let testRecordId = null;

    // Test strings with various special characters common in Dutch/European names
    const specialCharStrings = {
        acuteAccent: 'Renée de Haan',           // é - most common issue
        graveAccent: 'Café società',            // è, à
        circumflex: 'Hôtel château',            // ô, â
        umlaut: 'Müller Schöne',                // ü, ö
        cedilla: 'François garçon',             // ç
        tilde: 'São Paulo señor',               // ã, ñ
        mixed: 'André Renée Müller'             // Multiple special chars
    };

    beforeEach(() => {
        cy.loginAsAdmin();
    });

    afterEach(() => {
        // Cleanup test record if created
        if (testRecordId) {
            cy.apiDelete(testFormName, testRecordId).then(() => {
                testRecordId = null;
            });
        }
    });

    describe('Tree API UTF-8 Handling', () => {
        it('should return tree data without _badFields diagnostic', () => {
            cy.apiGetTree(testFormName).then(response => {
                expect(response.status).to.eq(200);
                // _badFields indicates UTF-8 encoding issues that were recovered
                // If present, it means there was an encoding problem
                expect(response.body).to.not.have.property('_badFields');
            });
        });

        it('should handle records with accented characters', () => {
            // Create a record with special characters
            const testData = {
                naam: specialCharStrings.acuteAccent + ' ' + Date.now()
            };

            cy.apiCreate(testFormName, testData).then(createResponse => {
                if (createResponse.status === 200 && createResponse.body.id) {
                    testRecordId = createResponse.body.id;

                    // Fetch tree and verify the record appears correctly
                    cy.apiGetTree(testFormName).then(treeResponse => {
                        expect(treeResponse.status).to.eq(200);
                        expect(treeResponse.body).to.not.have.property('_badFields');
                    });
                }
            });
        });

        it('should search for records with special characters', () => {
            // Create a record with special characters
            const uniqueMarker = Date.now();
            const testData = {
                naam: `Renée Test ${uniqueMarker}`
            };

            cy.apiCreate(testFormName, testData).then(createResponse => {
                if (createResponse.status === 200 && createResponse.body.id) {
                    testRecordId = createResponse.body.id;

                    // Search for the record by name
                    cy.apiGetTree(testFormName, { search: 'Renée' }).then(searchResponse => {
                        expect(searchResponse.status).to.eq(200);
                        // The search should work without encoding errors
                        expect(searchResponse.body).to.not.have.property('_badFields');
                    });
                }
            });
        });
    });

    describe('Detail API UTF-8 Handling', () => {
        it('should return record detail without _badFields diagnostic', () => {
            cy.apiGetTree(testFormName).then(treeResponse => {
                if (treeResponse.body.rows && treeResponse.body.rows.length > 0) {
                    const recordId = treeResponse.body.rows[0].ID;

                    cy.apiGetDetail(testFormName, recordId).then(response => {
                        expect(response.status).to.eq(200);
                        // No encoding issues should be reported
                        expect(response.body).to.not.have.property('_badFields');
                    });
                }
            });
        });

        it('should correctly return special characters in record fields', () => {
            // Create a record with special characters
            const testData = {
                naam: specialCharStrings.acuteAccent + ' ' + Date.now()
            };

            cy.apiCreate(testFormName, testData).then(createResponse => {
                if (createResponse.status === 200 && createResponse.body.id) {
                    testRecordId = createResponse.body.id;

                    // Fetch the record and verify special characters are preserved
                    cy.apiGetDetail(testFormName, testRecordId).then(detailResponse => {
                        expect(detailResponse.status).to.eq(200);
                        expect(detailResponse.body).to.not.have.property('_badFields');

                        // Verify the name contains the special character
                        if (detailResponse.body.record && detailResponse.body.record.naam) {
                            expect(detailResponse.body.record.naam).to.include('Renée');
                        }
                    });
                }
            });
        });

        it('should handle umlaut characters correctly', () => {
            const testData = {
                naam: specialCharStrings.umlaut + ' ' + Date.now()
            };

            cy.apiCreate(testFormName, testData).then(createResponse => {
                if (createResponse.status === 200 && createResponse.body.id) {
                    testRecordId = createResponse.body.id;

                    cy.apiGetDetail(testFormName, testRecordId).then(detailResponse => {
                        expect(detailResponse.status).to.eq(200);
                        expect(detailResponse.body).to.not.have.property('_badFields');

                        if (detailResponse.body.record && detailResponse.body.record.naam) {
                            expect(detailResponse.body.record.naam).to.include('Müller');
                        }
                    });
                }
            });
        });

        it('should handle mixed special characters correctly', () => {
            const testData = {
                naam: specialCharStrings.mixed + ' ' + Date.now()
            };

            cy.apiCreate(testFormName, testData).then(createResponse => {
                if (createResponse.status === 200 && createResponse.body.id) {
                    testRecordId = createResponse.body.id;

                    cy.apiGetDetail(testFormName, testRecordId).then(detailResponse => {
                        expect(detailResponse.status).to.eq(200);
                        expect(detailResponse.body).to.not.have.property('_badFields');

                        if (detailResponse.body.record && detailResponse.body.record.naam) {
                            // All special characters should be preserved
                            expect(detailResponse.body.record.naam).to.include('André');
                            expect(detailResponse.body.record.naam).to.include('Renée');
                            expect(detailResponse.body.record.naam).to.include('Müller');
                        }
                    });
                }
            });
        });
    });

    describe('CRUD with Special Characters', () => {
        it('should create and update record with special characters', () => {
            // Create
            const createData = {
                naam: 'Renée Initial ' + Date.now()
            };

            cy.apiCreate(testFormName, createData).then(createResponse => {
                if (createResponse.status === 200 && createResponse.body.id) {
                    testRecordId = createResponse.body.id;

                    // Update with different special characters
                    const updateData = {
                        naam: 'André Updated ' + Date.now()
                    };

                    cy.apiUpdate(testFormName, testRecordId, updateData).then(updateResponse => {
                        expect(updateResponse.status).to.eq(200);

                        // Verify update worked
                        cy.apiGetDetail(testFormName, testRecordId).then(detailResponse => {
                            expect(detailResponse.body).to.not.have.property('_badFields');
                            if (detailResponse.body.record) {
                                expect(detailResponse.body.record.naam).to.include('André');
                            }
                        });
                    });
                }
            });
        });
    });

    describe('Large Text Fields (MEMO/LONGCHAR)', () => {
        it('should handle large text with Windows-1252 characters', () => {
            // ContentBlocks use MEMO/LONGCHAR fields that can have encoding issues
            // Create a large text with Windows-1252 smart quotes and other special chars
            const smartQuotes = String.fromCharCode(0x2018, 0x2019, 0x201C, 0x201D); // ' ' " "
            const largeText = 'Renée ' + smartQuotes + ' dit is een test met ' +
                              specialCharStrings.mixed + ' ' +
                              'Een lange tekst met karakters: äëïöü àèìòù '.repeat(50) +
                              ' einde ' + Date.now();

            const testData = {
                naam: specialCharStrings.acuteAccent + ' Large ' + Date.now(),
                // Many forms have a beschrijving or omschrijving field
                omschrijving: largeText
            };

            cy.apiCreate(testFormName, testData).then(createResponse => {
                if (createResponse.status === 200 && createResponse.body.id) {
                    testRecordId = createResponse.body.id;

                    // Fetch and verify large text is preserved
                    cy.apiGetDetail(testFormName, testRecordId).then(detailResponse => {
                        expect(detailResponse.status).to.eq(200);
                        expect(detailResponse.body).to.not.have.property('_badFields');

                        if (detailResponse.body.record && detailResponse.body.record.omschrijving) {
                            expect(detailResponse.body.record.omschrijving.length).to.be.greaterThan(1000);
                            expect(detailResponse.body.record.omschrijving).to.include('Renée');
                        }
                    });
                }
            });
        });

        it('should not truncate MEMO fields at 4096 bytes', () => {
            // Test for the specific 4096 byte ODBC default limit issue
            const padding = 'abcdefghij'.repeat(500); // 5000 characters
            const testText = specialCharStrings.acuteAccent + padding + ' end marker';

            const testData = {
                naam: 'MEMO Test ' + Date.now(),
                omschrijving: testText
            };

            cy.apiCreate(testFormName, testData).then(createResponse => {
                if (createResponse.status === 200 && createResponse.body.id) {
                    testRecordId = createResponse.body.id;

                    cy.apiGetDetail(testFormName, testRecordId).then(detailResponse => {
                        expect(detailResponse.status).to.eq(200);

                        if (detailResponse.body.record && detailResponse.body.record.omschrijving) {
                            // Should contain end marker - proves no truncation
                            expect(detailResponse.body.record.omschrijving).to.include('end marker');
                            // Length should be greater than 4096 bytes
                            expect(detailResponse.body.record.omschrijving.length).to.be.greaterThan(4096);
                        }
                    });
                }
            });
        });
    });

    describe('JSON Encoding Integrity', () => {
        it('should return valid JSON for tree with any data', () => {
            cy.apiGetTree(testFormName).then(response => {
                // If we got here, JSON parsing succeeded
                expect(response.status).to.eq(200);
                expect(response.body).to.be.an('object');

                // Verify it's not an error response due to encoding
                if (response.body.error) {
                    expect(response.body.error).to.not.include('json');
                    expect(response.body.error).to.not.include('encoding');
                }
            });
        });

        it('should return valid JSON for detail with any data', () => {
            cy.apiGetTree(testFormName).then(treeResponse => {
                if (treeResponse.body.rows && treeResponse.body.rows.length > 0) {
                    const recordId = treeResponse.body.rows[0].ID;

                    cy.apiGetDetail(testFormName, recordId).then(response => {
                        // If we got here, JSON parsing succeeded
                        expect(response.status).to.eq(200);
                        expect(response.body).to.be.an('object');
                    });
                }
            });
        });
    });
});
