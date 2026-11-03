/**
 * Platform updates: kleur van het uitvoeringslog.
 *
 * Gemeld: bij een mislukte migratie stonden de "⊘ Overgeslagen"-regels rood, terwijl
 * overslaan geen fout is. De hele uitvoer van het script kwam als één logregel binnen
 * en kreeg de kleur van de ✗ erin. Afspraak: elke fysieke regel krijgt zijn eigen kleur;
 * een ingesprongen regel zonder teken neemt de kleur van de regel erboven over.
 *
 * Draait tegen de echte bron in cma/assets/js/migration-log.js, in jsdom.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const BRON = path.join(__dirname, '..', '..', 'assets', 'js', 'migration-log.js');

function regels(entries) {
    const dom = new JSDOM('<!doctype html><body><div id="log"></div><script>' + fs.readFileSync(BRON, 'utf8') + '</script></body>',
        { runScripts: 'dangerously' });
    const el = dom.window.document.getElementById('log');
    el.innerHTML = dom.window.cmaMigrationLogHtml(entries);
    // [tekst, klasse] per fysieke regel
    return el.innerHTML.split('\n').map((html) => {
        const tmp = dom.window.document.createElement('div');
        tmp.innerHTML = html;
        const span = tmp.querySelector('span');
        return [tmp.textContent, span ? span.className : ''];
    });
}

suite('Platform updates: kleur van het uitvoeringslog');

test('overgeslagen regels in de uitvoer van een mislukte migratie zijn geel, de fout rood', () => {
    const r = regels([
        '  1 wijziging(en) uit te voeren...',
        '✗ Versie platform:2.7.1 MISLUKT:',
        '  ⊘ Overgeslagen (bestaand bestand met tabel/velden): afspraak.json\n' +
        '  ⊘ Overgeslagen (bestaand bestand met tabel/velden): auditlog.json\n' +
        '  ✗ Fout: COUNT field incorrect: -3010 Too few parameters. Expected 1.\n' +
        '    - COUNT field incorrect: -3010 Too few parameters. Expected 1.',
    ]);
    assert.diepgelijk(r.map((x) => x[1]), ['', 'log-error', 'log-warning', 'log-warning', 'log-error', 'log-error']);
});

test('een ingesprongen detailregel zonder teken erft de kleur van de regel erboven', () => {
    const r = regels(['✓ Geexporteerd: 2 nieuwe', '  - Afspraak -> afspraak.json', 'Versie 2.7.2: Formulieren exporteren']);
    assert.diepgelijk(r.map((x) => x[1]), ['log-success', 'log-success', 'log-version']);
});

test('een niet-ingesprongen regel zonder teken krijgt geen kleur', () => {
    const r = regels(['✗ Versie 1 MISLUKT:', 'gewone tekst']);
    assert.diepgelijk(r.map((x) => x[1]), ['log-error', '']);
});

test('HTML in het log wordt als tekst getoond', () => {
    const r = regels(['✗ Fout: <b>x</b> & y']);
    assert.gelijk(r[0][0], '✗ Fout: <b>x</b> & y');
});
