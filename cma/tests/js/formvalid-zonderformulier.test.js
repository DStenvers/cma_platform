/**
 * form_valid() zonder formulier valt terug op het laatste formulier op de pagina.
 *
 * Een aanroep als form_valid(document.getElementById("Beoordeling")) krijgt null zodra het
 * id niet (meer) bestaat. Dan sloeg de validatie stil over. Net als formval_nl.js van de
 * ASP-site pakt form_valid() dan het laatste formulier van de pagina, zodat de controle
 * gewoon loopt.
 *
 * De test laadt de echte library/formval_nl.js in jsdom.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const BRON = fs.readFileSync(path.join(__dirname, '..', '..', '..', 'library', 'formval_nl.js'), 'utf8');

function maakPagina() {
    const dom = new JSDOM(
        '<!doctype html><html><body>' +
        '<form id="zoek"><input name="q"></form>' +
        '<form id="beoordeling"><input name="opmerking"></form>' +
        '</body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/formulier.php' }
    );
    const s = dom.window.document.createElement('script');
    s.textContent = BRON;
    dom.window.document.body.appendChild(s);
    return dom.window;
}

suite('form_valid zonder formulier');

test('null valideert het laatste formulier van de pagina', () => {
    const w = maakPagina();
    let uitkomst;
    try { uitkomst = w.form_valid(null); } catch (e) { /* alleen de keuze van het formulier telt */ }
    assert.gelijk(w.document.getElementById('beoordeling').getAttribute('data-form-init'), 'J',
        'het laatste formulier is gevalideerd (en dus geïnitialiseerd)');
    assert.gelijk(w.document.getElementById('zoek').getAttribute('data-form-init'), null,
        'het eerste formulier blijft ongemoeid');
    assert.onwaar(uitkomst === false, 'een formulier zonder fouten is geldig');
});

test('een meegegeven formulier blijft het formulier dat gevalideerd wordt', () => {
    const w = maakPagina();
    try { w.form_valid(w.document.getElementById('zoek')); } catch (e) { /* idem */ }
    assert.gelijk(w.document.getElementById('zoek').getAttribute('data-form-init'), 'J');
    assert.gelijk(w.document.getElementById('beoordeling').getAttribute('data-form-init'), null);
});
