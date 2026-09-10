/**
 * lib-search-input: het attribuut readonly.
 *
 * Gemeld: "the readonly variant is not readonly". De storybook toonde een demo met
 * readonly, maar het component kende alleen disabled: het veld was te bewerken en de
 * wisknop stond erbij. Afspraak nu: het invoerveld is readonly, de wisknop blijft weg,
 * Escape en clear() laten de waarde staan, Enter geeft nog wel `search`, en het is
 * omkeerbaar via het attribuut of de property readOnly.
 *
 * Draait tegen de echte bron in library/webcomponents/lib-search-input.js, in jsdom.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const BRON = path.join(__dirname, '..', '..', '..', 'library', 'webcomponents', 'lib-search-input.js');

function maakVeld(attributen) {
    const dom = new JSDOM(
        '<!doctype html><html><body>' +
        '<lib-search-input id="z" ' + attributen + '></lib-search-input>' +
        '<script>' + fs.readFileSync(BRON, 'utf8') + '</script>' +
        '</body></html>',
        { runScripts: 'dangerously', pretendToBeVisual: true, url: 'http://localhost/cma/main.php' }
    );
    const win = dom.window;
    const el = win.document.getElementById('z');
    return {
        win, el,
        input: () => el.querySelector('input'),
        wisknopZichtbaar: () => el.querySelector('.clear-btn').classList.contains('visible'),
        toets: (key) => el.querySelector('input').dispatchEvent(new win.KeyboardEvent('keydown', { key, bubbles: true, cancelable: true })),
    };
}

suite('lib-search-input: readonly');

test('readonly zet het invoerveld op readonly en verbergt de wisknop', () => {
    const v = maakVeld('value="Vast" readonly');
    assert.waar(v.input().readOnly, 'input.readOnly');
    assert.waar(v.el.querySelector('.lib-search-input').classList.contains('readonly'), 'klasse readonly op de container');
    assert.onwaar(v.wisknopZichtbaar(), 'wisknop hoort weg te blijven');
});

test('zonder readonly staat de wisknop er wél bij een waarde (geen regressie)', () => {
    const v = maakVeld('value="Vast"');
    assert.onwaar(v.input().readOnly);
    assert.waar(v.wisknopZichtbaar());
});

test('Escape en clear() laten de waarde staan; Enter geeft nog wel search', () => {
    const v = maakVeld('value="Vast" readonly');
    const events = [];
    v.el.addEventListener('clear', () => events.push('clear'));
    v.el.addEventListener('search', (e) => events.push('search:' + e.detail.value));
    v.toets('Escape');
    v.el.clear();
    assert.gelijk(v.el.value, 'Vast', 'waarde na Escape en clear()');
    v.toets('Enter');
    assert.diepgelijk(events, ['search:Vast']);
});

test('omkeerbaar: attribuut weg → wisknop terug; property readOnly zet hem weer aan', () => {
    const v = maakVeld('value="Vast" readonly');
    v.el.removeAttribute('readonly');
    assert.onwaar(v.input().readOnly, 'na removeAttribute');
    assert.waar(v.wisknopZichtbaar(), 'wisknop terug');
    v.el.readOnly = true;
    assert.waar(v.el.hasAttribute('readonly'), 'property zet het attribuut');
    assert.waar(v.input().readOnly, 'na readOnly = true');
    assert.onwaar(v.wisknopZichtbaar());
});

test('readonly later gezet op een leeg veld met waarde via property', () => {
    const v = maakVeld('');
    v.el.value = 'Later';
    assert.waar(v.wisknopZichtbaar());
    v.el.setAttribute('readonly', '');
    assert.waar(v.input().readOnly);
    assert.onwaar(v.wisknopZichtbaar());
});
