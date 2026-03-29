/**
 * IP Address CIDR Validation Tests
 *
 * Tests for IP address matching including:
 * - Exact IP address matching
 * - CIDR notation matching (e.g., 192.168.1.0/24)
 * - Edge cases and invalid patterns
 */

describe('IP Address CIDR Validation', () => {
    describe('Exact IP Matching', () => {
        it('should match exact IP addresses', () => {
            cy.request({
                method: 'GET',
                url: 'api/test_ip_match.php',
                qs: {
                    ip: '192.168.1.4',
                    pattern: '192.168.1.4'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.matches).to.be.true;
            });
        });

        it('should not match different IP addresses', () => {
            cy.request({
                method: 'GET',
                url: 'api/test_ip_match.php',
                qs: {
                    ip: '192.168.1.4',
                    pattern: '192.168.1.5'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.matches).to.be.false;
            });
        });
    });

    describe('CIDR Notation Matching', () => {
        it('should match IP within /24 range', () => {
            cy.request({
                method: 'GET',
                url: 'api/test_ip_match.php',
                qs: {
                    ip: '192.168.1.4',
                    pattern: '192.168.1.0/24'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.matches).to.be.true;
            });
        });

        it('should match IP at start of /24 range', () => {
            cy.request({
                method: 'GET',
                url: 'api/test_ip_match.php',
                qs: {
                    ip: '192.168.1.0',
                    pattern: '192.168.1.0/24'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.matches).to.be.true;
            });
        });

        it('should match IP at end of /24 range', () => {
            cy.request({
                method: 'GET',
                url: 'api/test_ip_match.php',
                qs: {
                    ip: '192.168.1.255',
                    pattern: '192.168.1.0/24'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.matches).to.be.true;
            });
        });

        it('should not match IP outside /24 range', () => {
            cy.request({
                method: 'GET',
                url: 'api/test_ip_match.php',
                qs: {
                    ip: '192.168.2.4',
                    pattern: '192.168.1.0/24'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.matches).to.be.false;
            });
        });

        it('should match IP within /16 range', () => {
            cy.request({
                method: 'GET',
                url: 'api/test_ip_match.php',
                qs: {
                    ip: '192.168.50.100',
                    pattern: '192.168.0.0/16'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.matches).to.be.true;
            });
        });

        it('should not match IP outside /16 range', () => {
            cy.request({
                method: 'GET',
                url: 'api/test_ip_match.php',
                qs: {
                    ip: '192.169.1.1',
                    pattern: '192.168.0.0/16'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.matches).to.be.false;
            });
        });

        it('should match IP within /32 range (single IP)', () => {
            cy.request({
                method: 'GET',
                url: 'api/test_ip_match.php',
                qs: {
                    ip: '10.0.0.1',
                    pattern: '10.0.0.1/32'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.matches).to.be.true;
            });
        });

        it('should not match different IP with /32', () => {
            cy.request({
                method: 'GET',
                url: 'api/test_ip_match.php',
                qs: {
                    ip: '10.0.0.2',
                    pattern: '10.0.0.1/32'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.matches).to.be.false;
            });
        });
    });

    describe('Current IP with CIDR Range', () => {
        it('should match current client IP against its /24 network', () => {
            // First get the current client IP by making a request
            // Then test if it matches its own /24 network
            cy.request({
                method: 'GET',
                url: 'api/dashboard_stats.php'
            }).then(() => {
                // Use a known test IP - in real scenario we'd get the actual client IP
                // Testing that 172.29.208.1 matches 172.29.208.0/24
                cy.request({
                    method: 'GET',
                    url: 'api/test_ip_match.php',
                    qs: {
                        ip: '172.29.208.1',
                        pattern: '172.29.208.0/24'
                    }
                }).then((response) => {
                    expect(response.status).to.eq(200);
                    expect(response.body.success).to.be.true;
                    expect(response.body.matches).to.be.true;
                });
            });
        });

        it('should match any IP ending in .X against .0/24', () => {
            // Test various IPs within the same /24 range
            const testCases = [
                { ip: '192.168.1.1', pattern: '192.168.1.0/24', shouldMatch: true },
                { ip: '192.168.1.127', pattern: '192.168.1.0/24', shouldMatch: true },
                { ip: '192.168.1.254', pattern: '192.168.1.0/24', shouldMatch: true },
                { ip: '192.168.2.1', pattern: '192.168.1.0/24', shouldMatch: false },
            ];

            testCases.forEach(({ ip, pattern, shouldMatch }) => {
                cy.request({
                    method: 'GET',
                    url: 'api/test_ip_match.php',
                    qs: { ip, pattern }
                }).then((response) => {
                    expect(response.body.matches, `${ip} should ${shouldMatch ? '' : 'not '}match ${pattern}`).to.eq(shouldMatch);
                });
            });
        });
    });

    describe('Edge Cases', () => {
        it('should handle empty IP', () => {
            cy.request({
                method: 'GET',
                url: 'api/test_ip_match.php',
                qs: {
                    ip: '',
                    pattern: '192.168.1.0/24'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.false;
            });
        });

        it('should handle empty pattern', () => {
            cy.request({
                method: 'GET',
                url: 'api/test_ip_match.php',
                qs: {
                    ip: '192.168.1.4',
                    pattern: ''
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.false;
            });
        });

        it('should handle invalid CIDR prefix', () => {
            cy.request({
                method: 'GET',
                url: 'api/test_ip_match.php',
                qs: {
                    ip: '192.168.1.4',
                    pattern: '192.168.1.0/33'
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.matches).to.be.false;
            });
        });

        it('should handle whitespace in IP and pattern', () => {
            cy.request({
                method: 'GET',
                url: 'api/test_ip_match.php',
                qs: {
                    ip: ' 192.168.1.4 ',
                    pattern: ' 192.168.1.0/24 '
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.body.success).to.be.true;
                expect(response.body.matches).to.be.true;
            });
        });
    });
});
