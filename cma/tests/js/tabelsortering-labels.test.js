/**
 * De sorteerknoppen in het kolommenu van <lib-table> zeggen wat ze doen: een datumkolom oud-nieuw /
 * nieuw-oud, een getalkolom laag-hoog / hoog-laag, een tekstkolom A-Z / Z-A. De interne volgorde
 * (oplopend eerst) en de sleutels a---z / z---a blijven gelijk. Draait tegen de echte bron.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const LIBTABLE = path.join(__dirname, '..', '..', '..', 'library', 'webcomponents', 'lib-table.js');

function maakTabel() {
    const dom = new JSDOM(
        '<!doctype html><html><body>' +
        '<lib-table resizable><table><thead><tr>' +
        '<th>Naam</th><th data-type="date">Datum</th><th data-type="number">Aantal</th><th data-type="decimal">Bedrag</th><th data-type="time">Tijd</th>' +
        '</tr></thead><tbody>' +
        '<tr><td>Anja</td><td>01-02-2026</td><td>5</td><td>1,50</td><td>09:30</td></tr>' +
        '<tr><td>Piet</td><td>03-04-2026</td><td>9</td><td>2,50</td><td>10:30</td></tr>' +
        '</tbody></table></lib-table>' +
        '<script>' + fs.readFileSync(LIBTABLE, 'utf8') + '</script></body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/cma/main.php', pretendToBeVisual: true }
    );
    return dom.window.document;
}

function naOpbouw(doc) {
    return new Promise((klaar, mis) => {
        let pogingen = 0;
        const kijk = () => {
            if (doc.querySelectorAll('thead th .dropdown-filter-content').length >= 5) { klaar(doc); return; }
            if (++pogingen > 100) { mis(new Error('<lib-table> heeft geen filtermenu opgebouwd')); return; }
            setTimeout(kijk, 20);
        };
        kijk();
    });
}

function sorteerLabels(doc, n) {
    const th = doc.querySelectorAll('thead th')[n];
    return [...th.querySelectorAll('.dropdown-filter-sort span')].map((s) => s.className.split(' ')[0] + '=' + s.textContent);
}

suite('lib-table: namen van de sorteerknoppen');

test('tekst: A - Z en Z - A', async () => {
    const doc = await naOpbouw(maakTabel());
    assert.gelijk(JSON.stringify(sorteerLabels(doc, 0)), JSON.stringify(['a---z=A - Z', 'z---a=Z - A']), 'tekstkolom');
});

test('datum: Oud - Nieuw (oplopend) en Nieuw - Oud (aflopend)', async () => {
    const doc = await naOpbouw(maakTabel());
    assert.gelijk(JSON.stringify(sorteerLabels(doc, 1)), JSON.stringify(['a---z=Oud - Nieuw', 'z---a=Nieuw - Oud']), 'datumkolom');
});

test('getal en bedrag: Laag - Hoog (oplopend) en Hoog - Laag (aflopend)', async () => {
    const doc = await naOpbouw(maakTabel());
    const verwacht = JSON.stringify(['a---z=Laag - Hoog', 'z---a=Hoog - Laag']);
    assert.gelijk(JSON.stringify(sorteerLabels(doc, 2)), verwacht, 'getalkolom');
    assert.gelijk(JSON.stringify(sorteerLabels(doc, 3)), verwacht, 'decimale kolom');
});

test('tijd blijft A - Z / Z - A (niet gevraagd)', async () => {
    const doc = await naOpbouw(maakTabel());
    assert.gelijk(JSON.stringify(sorteerLabels(doc, 4)), JSON.stringify(['a---z=A - Z', 'z---a=Z - A']), 'tijdkolom');
});
