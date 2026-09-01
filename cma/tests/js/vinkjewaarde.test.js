/**
 * Eén waarheid voor "staat dit vinkje aan".
 *
 * Access levert een ja/nee-veld als -1 of 0, en of dat als GETAL of als TEKST bij de
 * browser aankomt hangt van de driver af. Twee plekken beoordeelden dat elk met een
 * eigen lijstje, en die liepen uiteen:
 *
 *   tabelweergave (inline-edit): true 'True' 'true' 1 '1' -1 '-1' 'J' 'Ja' 'Yes' 'Y'
 *   formulier (form-controller): true 'true' '1' 'True' -1
 *
 * De string '-1' zat alleen in de eerste. Gevolg: in de lijst stond de schakelaar aan
 * en in het formulier eronder uit, voor hetzelfde record — gemeld op het veld Actief.
 *
 * Beide roepen nu CMA.utils.isAangevinkt aan. Deze test pint de waarheidstabel vast op
 * de uitgeleverde bron.
 *
 * Run: node tests/js/run.js vinkjewaarde
 */
const fs = require('fs');
const path = require('path');

const UTILS = path.join(__dirname, '..', '..', 'assets', 'js', 'cma-utils.js');
const CONTROLLER = path.join(__dirname, '..', '..', 'assets', 'js', 'form-controller.js');
const INLINE = path.join(__dirname, '..', '..', 'assets', 'js', 'inline-edit.js');

function laadHelper() {
    const src = fs.readFileSync(UTILS, 'utf8');
    const start = src.indexOf('CMA.utils.isAangevinkt = function');
    if (start === -1) { throw new Error('isAangevinkt niet gevonden in cma-utils.js'); }
    const eind = src.indexOf('\n};', start);
    const body = src.slice(start, eind + 3).replace('CMA.utils.isAangevinkt = function', 'return function');
    return new Function(body)();
}

suite('vinkjewaarde');

test('alles wat Access of de driver voor "waar" kan opleveren telt als aan', () => {
    const aan = laadHelper();
    for (const w of [true, 1, -1, '1', '-1', 'true', 'True', 'TRUE', ' ja ', 'J', 'Yes', 'y', 'on']) {
        assert.gelijk(aan(w), true, JSON.stringify(w));
    }
});

test('en de rest telt als uit', () => {
    const aan = laadHelper();
    for (const w of [false, 0, '0', '', ' ', null, undefined, 'nee', 'N', 'false', 'False', 2, '2', {}, []]) {
        assert.gelijk(aan(w), false, JSON.stringify(w) || String(w));
    }
});

test('het formulier en de tabelweergave gebruiken dezelfde functie', () => {
    const controller = fs.readFileSync(CONTROLLER, 'utf8');
    const inline = fs.readFileSync(INLINE, 'utf8');
    assert.waar(/const isChecked = CMA\.utils\.isAangevinkt\(value\);/.test(controller), 'form-controller');
    assert.waar(/const isChecked = CMA\.utils\.isAangevinkt\(value\);/.test(inline), 'inline-edit');
    // En geen eigen lijstje meer ernaast.
    assert.onwaar(/isChecked = value === true \|\| value === 'true'/.test(controller), 'form-controller heeft nog een eigen lijst');
    assert.onwaar(/isChecked = value === true \|\| value === 'True'/.test(inline), 'inline-edit heeft nog een eigen lijst');
});

test('de helper hangt niet als global aan window', () => {
    assert.onwaar(/window\.isAangevinkt/.test(fs.readFileSync(UTILS, 'utf8')), 'window.isAangevinkt bestaat');
});
