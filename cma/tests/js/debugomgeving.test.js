/**
 * debugomgeving — welke hostnamen als O/T-omgeving gelden.
 *
 * WAAROM DEZE TEST ER IS. De herkenning keek op '.test' en '-t.' in de hostnaam.
 * test-mijn.rino.nl viel daar buiten: op de testomgeving stond de debugmodus dus
 * uit, precies waar hij nodig is — en dat is niet te zien, want het gevolg is
 * alleen dat er niets in de console verschijnt.
 *
 * De grens ligt nu op woordniveau. Te ruim is net zo fout als te krap:
 * contest.nl en protest-mijn.nl bevatten "test" maar zijn geen testomgeving.
 */

const fs = require('fs');
const path = require('path');

const BRON = path.join(__dirname, '..', '..', 'assets', 'js', 'cma-utils.js');

function laadHerkenning() {
    const src = fs.readFileSync(BRON, 'utf8');
    const start = src.indexOf('window.CMA_DEBUG = (function() {');
    if (start === -1) { throw new Error('CMA_DEBUG-herkenning niet gevonden'); }
    const eind = src.indexOf('})();', start);
    const body = src.slice(start + 'window.CMA_DEBUG = '.length, eind + '})()'.length);
    return function (hostname) {
        const window = { location: { hostname: hostname } };
        return new Function('window', 'return ' + body + ';')(window);
    };
}

suite('debugomgeving');

test('de testomgeving van mijnRINO telt mee', () => {
    const debug = laadHerkenning();
    assert.waar(debug('test-mijn.rino.nl'), 'test-mijn.rino.nl is de testomgeving');
    assert.waar(debug('t-mijn.rino.nl'));
    assert.waar(debug('acc-mijn.rino.nl'));
    assert.waar(debug('o-cma.rino.nl'));
    assert.waar(debug('dev.rino.nl'));
});

test('de eigen machine telt mee', () => {
    const debug = laadHerkenning();
    assert.waar(debug('localhost'));
    assert.waar(debug('127.0.0.1'));
    assert.waar(debug('172.29.208.1'), 'het lokale netwerk');
});

test('productie blijft stil', () => {
    const debug = laadHerkenning();
    assert.onwaar(debug('mijn.rino.nl'));
    assert.onwaar(debug('www.rino.nl'));
});

test('een naam die toevallig "test" bevat is geen testomgeving', () => {
    const debug = laadHerkenning();
    assert.onwaar(debug('contest.nl'));
    assert.onwaar(debug('protest-mijn.nl'));
});
