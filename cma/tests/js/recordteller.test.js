/**
 * Bewijs voor "de teller in de werkbalk heeft één eigenaar".
 *
 * #recordCount draagt de klasse table-mode-only, en die is in BEIDE richtingen
 * met !important gestyled: verborgen buiten tabelmodus, getoond erbinnen. Een
 * inline style.display uit JavaScript komt daar dus nooit doorheen. Wie de
 * teller toch zo probeert te verbergen, verbergt niets — en omdat juist die tak
 * de tekst niet herschreef, bleef de regel van de vórige batch staan:
 * "records 1-1600 van 1625 (laden...)" terwijl alle 1625 regels binnen waren.
 * Dat leest als een lijst die halverwege stopt met laden.
 *
 * De afspraak is daarom: het blad bepaalt of het element getoond wordt, JS
 * bepaalt alleen de TEKST, en niets te melden is een lege tekst.
 *
 * Cypress kan het einde van dit gedrag wel zien maar niet de afspraak eronder;
 * hier is één document genoeg.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const UTILS = path.join(__dirname, '..', '..', 'assets', 'js', 'cma-utils.js');
const JS_DIR = path.join(__dirname, '..', '..', 'assets', 'js');

function maakPagina() {
    const dom = new JSDOM(
        '<!doctype html><html><body class="mode-table">' +
        '<span id="recordCount" class="toolbar-status table-mode-only"></span>' +
        '<script>' + fs.readFileSync(UTILS, 'utf8') + '</script>' +
        '</body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/cma/form.php' }
    );
    const win = dom.window;
    return {
        win,
        teller: win.document.getElementById('recordCount'),
        toon: (geladen, totaal, nogMeer) =>
            win.CMA.utils.setRecordCount(win.CMA.utils.formatRecordCount(geladen, totaal, nogMeer))
    };
}

suite('Recordteller: het blad toont, JS schrijft de tekst');

test('tijdens het laden staat het aantal met "(laden...)" erbij', () => {
    const p = maakPagina();
    p.toon(1600, 1625, true);
    assert.gelijk(p.teller.textContent, 'records 1-1600 van 1625 (laden...)');
});

test('als alles binnen is blijft er niets van de vorige melding staan', () => {
    const p = maakPagina();
    p.toon(1600, 1625, true);
    p.toon(1625, 1625, false);
    assert.gelijk(p.teller.textContent, '', 'de teller hoort leeg te zijn, niet stil verborgen');
});

test('stopt het laden onder het totaal, dan vervalt "(laden...)" maar blijft het aantal', () => {
    const p = maakPagina();
    p.toon(1600, 1625, true);
    p.toon(1600, 1625, false);
    assert.gelijk(p.teller.textContent, 'records 1-1600 van 1625');
});

test('zonder bekend totaal wordt het aantal geladen regels gemeld', () => {
    const p = maakPagina();
    p.toon(200, null, true);
    assert.gelijk(p.teller.textContent, '200 records');
});

test('niets geladen en niets bekend meldt niets', () => {
    const p = maakPagina();
    p.toon(1600, 1625, true);
    p.toon(0, null, false);
    assert.gelijk(p.teller.textContent, '');
});

/**
 * De tweede eigenaar mag niet terugkomen. Elke poging om de teller via een
 * inline display te sturen is dode code die een oude regel laat staan.
 */
test('geen enkel bronbestand stuurt de teller via style.display', () => {
    const overtreders = [];
    for (const naam of fs.readdirSync(JS_DIR)) {
        if (!naam.endsWith('.js') || naam.endsWith('.min.js')) continue;
        const regels = fs.readFileSync(path.join(JS_DIR, naam), 'utf8').split('\n');
        let tellerInBeeld = false;
        regels.forEach((regel, i) => {
            if (/getElementById\(['"]recordCount['"]\)/.test(regel)) tellerInBeeld = true;
            if (tellerInBeeld && /countEl\.style\.display\s*=/.test(regel)) {
                overtreders.push(naam + ':' + (i + 1));
            }
        });
    }
    assert.diepgelijk(overtreders, [], 'de teller wordt via zijn tekst gestuurd, niet via display');
});
