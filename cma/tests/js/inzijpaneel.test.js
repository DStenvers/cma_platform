/**
 * Bewijs voor "zit ik in een zijpaneel?" (lib_IsInSidePanel).
 *
 * De DOM beslist: de iframe van een paneel staat in een element met de klasse
 * lib_sidepanel_container, welk venster het paneel ook opende. De paneelstapel
 * is daar geen bron voor — die staat op het venster dat het paneel opende (in
 * het CMA de content-frame van de shell, niet top), dus vanuit het paneel vond
 * een stapelcontrole via parent of top niets, en de pagina hield zichzelf voor
 * een gewone deep link: die opende het record opnieuw in een paneel, dat weer
 * dezelfde pagina laadde, tot in het oneindige.
 *
 * Hier: een top met daarin een shell-iframe, daarin een paneel-iframe (en een
 * paneel in een paneel). De functie komt uit de echte bron.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const BRON = path.join(__dirname, '..', '..', '..', 'library', 'library.js');

function functieUitBron() {
    const src = fs.readFileSync(BRON, 'utf8');
    const start = src.indexOf('function lib_IsInSidePanel(');
    const eind = src.indexOf('\n}\n', start) + 3;
    if (start === -1) throw new Error('lib_IsInSidePanel niet gevonden in library.js');
    return src.slice(start, eind);
}

/** top > shell-iframe > (paneel-iframe > (paneel-in-paneel-iframe)) — allemaal same-origin. */
function maakVensters() {
    const dom = new JSDOM('<!doctype html><html><body><iframe id="shell"></iframe></body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/cma/main.php', resources: 'usable' });
    const top = dom.window;
    const shell = top.document.getElementById('shell').contentWindow;
    shell.document.write('<!doctype html><html><body><div id="p1" class="lib_sidepanel_container"><iframe id="f1"></iframe></div></body></html>');
    shell.document.close();
    const paneel1 = shell.document.getElementById('f1').contentWindow;
    paneel1.document.write('<!doctype html><html><body><div id="p2" class="lib_sidepanel_container"><iframe id="f2"></iframe></div></body></html>');
    paneel1.document.close();
    const paneel2 = paneel1.document.getElementById('f2').contentWindow;
    paneel2.document.write('<!doctype html><html><body></body></html>');
    paneel2.document.close();
    // Geen stapel nodig: bewust niet gezet, de DOM moet volstaan.
    const fn = functieUitBron();
    const inVenster = (w) => { w.eval(fn); return w.lib_IsInSidePanel(); };
    return { top, shell, paneel1, paneel2, inVenster };
}

suite('lib_IsInSidePanel');

test('de pagina in het eerste paneel weet dat ze in een paneel zit', () => {
    const v = maakVensters();
    assert.gelijk(v.inVenster(v.paneel1), true);
});

test('een paneel in een paneel ook, al is het niet het bovenste', () => {
    const v = maakVensters();
    assert.gelijk(v.inVenster(v.paneel2), true, 'bovenste paneel');
    assert.gelijk(v.inVenster(v.paneel1), true, 'onderliggende paneel telt nog steeds');
});

test('de shell-pagina en het top-venster zitten niet in een paneel', () => {
    const v = maakVensters();
    assert.gelijk(v.inVenster(v.shell), false, 'de opener zelf');
    assert.gelijk(v.inVenster(v.top), false, 'top');
});

test('een iframe zonder paneel-element eromheen is geen paneel', () => {
    const v = maakVensters();
    v.shell.document.getElementById('p1').classList.remove('lib_sidepanel_container');
    assert.gelijk(v.inVenster(v.paneel1), false);
});
