const { defineConfig } = require('cypress');

module.exports = defineConfig({
    e2e: {
        // Base configuration
        baseUrl: 'http://172.29.208.1/cma',
        supportFile: 'cypress/support/e2e.js',
        specPattern: 'cypress/e2e/**/*.cy.js',

        // Viewport settings
        viewportWidth: 1280,
        viewportHeight: 800,

        // Timeouts
        defaultCommandTimeout: 10000,
        requestTimeout: 10000,
        responseTimeout: 30000,
        pageLoadTimeout: 60000,

        // Media settings
        video: false,
        screenshotOnRunFailure: true,
        screenshotsFolder: 'cypress/screenshots',

        // Reporter configuration
        reporter: 'spec',

        // Retry configuration
        retries: {
            runMode: 2,
            openMode: 0
        },

        // Experimental features
        experimentalRunAllSpecs: true,

        // Shadow DOM support for web components
        includeShadowDom: true,

        // Environment variables
        env: {
            // Test credentials
            adminUser: 'DiederikStenvers',
            adminPass: '_rino!',
            testUser: 'DiederikStenvers',
            testPass: '_rino!',
            frontendUser: 'hesges@hotmail.com',
            frontendPass: '_rino!',

            // API endpoints
            apiEndpoint: '/form_api.php',

            // Test configuration
            testRecords: [],

            // Timeouts for custom commands
            shortTimeout: 5000,
            mediumTimeout: 10000,
            longTimeout: 30000
        },

        setupNodeEvents(on, config) {
            // Console logging task
            on('task', {
                log(message) {
                    console.log(message);
                    return null;
                },
                table(data) {
                    console.table(data);
                    return null;
                },
                getTestResults() {
                    const fs = require('fs');
                    const path = require('path');
                    const resultsPath = path.join(__dirname, 'cypress/reports/results.json');
                    if (fs.existsSync(resultsPath)) {
                        return JSON.parse(fs.readFileSync(resultsPath, 'utf8'));
                    }
                    return null;
                },
                clearTestData() {
                    // Reset test records
                    config.env.testRecords = [];
                    return null;
                }
            });

            // Browser launch options
            on('before:browser:launch', (browser = {}, launchOptions) => {
                if (browser.name === 'chrome') {
                    launchOptions.args.push('--disable-dev-shm-usage');
                    launchOptions.args.push('--no-sandbox');
                }
                return launchOptions;
            });

            return config;
        }
    }
});
