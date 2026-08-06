#!/usr/bin/env node
/**
 * Testloper voor de JavaScript-kant van de CMA.
 *
 * Cypress test tegen een draaiende site; deze loper niet. Hij bouwt met jsdom
 * een document in het geheugen, laadt de te testen functies erin en controleert
 * de afspraken. Dat is wat "bewijzen" hier kan betekenen zonder site: elke
 * bewering is een uitvoerbare test die ook op een kale werkplek draait.
 *
 *   node tests/js/run.js            alle tests
 *   node tests/js/run.js formstate  alleen tests/js/formstate.test.js
 */

const fs = require('fs');
const path = require('path');

const TEST_DIR = __dirname;
const filter = process.argv[2] || '';

let huidigeSuite = '';
let geslaagd = 0;
const gefaald = [];

global.suite = (naam) => { huidigeSuite = naam; console.log('\n  ' + naam); };

global.test = (naam, fn) => {
    try {
        fn();
        geslaagd++;
        console.log('    [32m✓[39m ' + naam);
    } catch (e) {
        gefaald.push({ suite: huidigeSuite, naam, fout: e });
        console.log('    [31m✗[39m ' + naam);
        console.log('      ' + e.message.split('\n')[0]);
    }
};

global.assert = {
    gelijk(werkelijk, verwacht, bericht) {
        if (werkelijk !== verwacht) {
            throw new Error((bericht ? bericht + ': ' : '') +
                'verwacht ' + JSON.stringify(verwacht) + ', kreeg ' + JSON.stringify(werkelijk));
        }
    },
    diepgelijk(werkelijk, verwacht, bericht) {
        const a = JSON.stringify(werkelijk);
        const b = JSON.stringify(verwacht);
        if (a !== b) {
            throw new Error((bericht ? bericht + ': ' : '') + 'verwacht ' + b + ', kreeg ' + a);
        }
    },
    waar(waarde, bericht) {
        if (waarde !== true) {
            throw new Error((bericht || 'verwacht true') + ', kreeg ' + JSON.stringify(waarde));
        }
    },
    onwaar(waarde, bericht) {
        if (waarde !== false) {
            throw new Error((bericht || 'verwacht false') + ', kreeg ' + JSON.stringify(waarde));
        }
    },
    gooit(fn, bericht) {
        let gegooid = false;
        try { fn(); } catch (e) { gegooid = true; }
        if (!gegooid) throw new Error(bericht || 'verwachtte een fout, maar er kwam er geen');
    }
};

const bestanden = fs.readdirSync(TEST_DIR)
    .filter(f => f.endsWith('.test.js'))
    .filter(f => !filter || f.includes(filter));

if (bestanden.length === 0) {
    console.error('Geen tests gevonden' + (filter ? ' voor "' + filter + '"' : ''));
    process.exit(1);
}

console.log('JS-tests');
for (const bestand of bestanden) {
    require(path.join(TEST_DIR, bestand));
}

console.log('\n' + '-'.repeat(60));
if (gefaald.length === 0) {
    console.log('Tests: ' + geslaagd + ', [32mallemaal geslaagd[39m');
    process.exit(0);
}
console.log('Tests: ' + (geslaagd + gefaald.length) + ', geslaagd: ' + geslaagd +
            ', [31mgefaald: ' + gefaald.length + '[39m');
for (const f of gefaald) {
    console.log('\n  ' + f.suite + ' → ' + f.naam);
    console.log('  ' + f.fout.stack.split('\n').slice(0, 3).join('\n  '));
}
process.exit(1);
