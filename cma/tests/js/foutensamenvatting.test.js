/**
 * Bewijs voor de regels in de foutensamenvatting (form_errors_regel).
 *
 * Zo'n regel navigeert niet: hij opent het tabblad waar het veld in zit en zet
 * de focus erop. Als <a href="#"> las een schermlezer een rij links voor die
 * nergens heen gingen (de box heeft role=alert, dus de hele lijst wordt
 * voorgelezen), en elk pad dat preventDefault misloopt schreef een '#' in de
 * URL — waar deze applicatie state in bewaart.
 *
 * Deze tests draaien de échte functie uit library/formval_nl.js in jsdom, dus
 * ze zeggen iets over het element dat de gebruiker krijgt: welk soort knop het
 * is, dat hij het formulier niet verstuurt, en dat klikken het veld ophaalt.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const BRON = path.join(__dirname, '..', '..', '..', 'library', 'formval_nl.js');

/** Knip form_errors_regel uit de echte bron (haakjes tellend). */
function haalRegelFunctieUitBron() {
    const src = fs.readFileSync(BRON, 'utf8');
    const start = src.indexOf('function form_errors_regel(');
    if (start === -1) throw new Error('form_errors_regel niet gevonden in formval_nl.js');
    let i = src.indexOf('{', start);
    let diepte = 0;
    for (; i < src.length; i++) {
        if (src[i] === '{') diepte++;
        else if (src[i] === '}' && --diepte === 0) return src.slice(start, i + 1);
    }
    throw new Error('einde van form_errors_regel niet gevonden');
}

/** Formulier met een veld, plus de functie erin geladen. */
function maakPagina() {
    const dom = new JSDOM(
        '<!doctype html><html><body><form id="f"><ul id="lijst"></ul>' +
        '<input id="veld" name="veld"></form></body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/cma/form.php' }
    );
    const script = dom.window.document.createElement('script');
    script.textContent = `
        var form_errors_classname = 'form_errors';
        window.__onthuld = [];
        function form_field_reveal(fld) { window.__onthuld.push(fld ? fld.id : null); }
        function lib_addEvent(el, type, fn) { el.addEventListener(type, fn); }
        ${haalRegelFunctieUitBron()}
        window.__regel = form_errors_regel;
    `;
    dom.window.document.body.appendChild(script);
    return dom;
}

suite('Regels in de foutensamenvatting');

test('een regel met een veld is een knop, geen link', () => {
    const dom = maakPagina();
    const veld = dom.window.document.getElementById('veld');
    const li = dom.window.__regel({ fld: veld, html: 'Veld is verplicht' });
    const bedienbaar = li.firstChild;

    assert.gelijk(bedienbaar.tagName, 'BUTTON');
    assert.onwaar(bedienbaar.hasAttribute('href'), 'draagt geen href');
});

test('de knop verstuurt het formulier niet', () => {
    const dom = maakPagina();
    const veld = dom.window.document.getElementById('veld');
    const knop = dom.window.__regel({ fld: veld, html: 'Veld is verplicht' }).firstChild;

    // Zonder type='button' is submit de standaard, en de samenvatting staat ín het formulier
    assert.gelijk(knop.type, 'button');
});

test('klikken haalt het bijbehorende veld op', () => {
    const dom = maakPagina();
    const doc = dom.window.document;
    const veld = doc.getElementById('veld');
    const li = dom.window.__regel({ fld: veld, html: 'Veld is verplicht' });
    doc.getElementById('lijst').appendChild(li);

    let verstuurd = false;
    doc.getElementById('f').addEventListener('submit', () => { verstuurd = true; });
    li.firstChild.dispatchEvent(new dom.window.MouseEvent('click', { bubbles: true, cancelable: true }));

    assert.diepgelijk(dom.window.__onthuld, ['veld']);
    assert.onwaar(verstuurd, 'het formulier is niet verstuurd');
});

test('een regel zonder veld blijft platte tekst', () => {
    const dom = maakPagina();
    const li = dom.window.__regel({ fld: null, html: 'Er ging iets mis' });

    assert.gelijk(li.tagName, 'LI');
    assert.gelijk(li.querySelectorAll('button, a').length, 0);
    assert.gelijk(li.textContent, 'Er ging iets mis');
});
