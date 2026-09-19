/**
 * Het debug-baken van de shell gaat naar /cma/api/log.php, ook vanaf een
 * schone URL. Relatief ('api/log.php') werd het vanaf /cma/form/opleidingen
 * /cma/form/api/log.php: de rewrite gaf dat aan de shell, die per baken een
 * volledige pagina van 54 KB rendert, en de regel kwam nooit in het log.
 *
 * Run: node tests/js/run.js debugbeacon
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const MAIN = path.join(__dirname, '..', '..', 'assets', 'js', 'main.js');

function debugLogUitBron() {
    const src = fs.readFileSync(MAIN, 'utf8');
    const start = src.indexOf('    function debugLog(');
    const eind = src.indexOf('\n    }\n', start) + 7;
    if (start === -1) throw new Error('debugLog niet gevonden in main.js');
    return src.slice(start, eind);
}

suite('Debug-baken van de shell');

test('vanaf een schone URL komt het baken bij /cma/api/log.php uit', () => {
    const dom = new JSDOM('<!doctype html><html><body></body></html>', { runScripts: 'outside-only', url: 'https://test-mijn.rino.nl/cma/form/opleidingen/244' });
    const win = dom.window;
    win.CMA_DEBUG = true;
    win.cmaLog = { warn() {} };
    const bakens = [];
    win.navigator.sendBeacon = (url) => { bakens.push(url); return true; };
    win.eval(debugLogUitBron() + '\nwindow.debugLog = debugLog;');
    win.debugLog('test', { a: 1 });
    assert.gelijk(bakens.length, 1, 'één baken verstuurd');
    assert.gelijk(new win.URL(bakens[0], win.location.href).pathname, '/cma/api/log.php');
});

test('zonder debugmodus wordt er niets verstuurd', () => {
    const dom = new JSDOM('<!doctype html><html><body></body></html>', { runScripts: 'outside-only', url: 'https://test-mijn.rino.nl/cma/form/opleidingen' });
    const win = dom.window;
    win.CMA_DEBUG = false;
    let verstuurd = 0;
    win.navigator.sendBeacon = () => { verstuurd++; return true; };
    win.eval(debugLogUitBron() + '\nwindow.debugLog = debugLog;');
    win.debugLog('test', {});
    assert.gelijk(verstuurd, 0);
});
