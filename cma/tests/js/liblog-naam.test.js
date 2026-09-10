/**
 * lib-log.js: de naam is libLog, LibLog is een dun laagje.
 *
 * Gevraagd: "LibLog starts with a capital, can we change that to libLog and create a
 * small wrapper for LibLog". libLog past bij libToast, libAlert en libConfirm. De oude
 * naam blijft bestaan als wrapper die elke aanroep doorgeeft, zodat code die nog
 * LibLog.* zegt niet omvalt — maar in het platform zelf mag die naam niet meer
 * voorkomen (behalve in lib-log.js, dat hem definieert).
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const PLATFORM = path.join(__dirname, '..', '..', '..');
const BRON = path.join(PLATFORM, 'library', 'webcomponents', 'lib-log.js');

function laad() {
    const dom = new JSDOM(
        '<!doctype html><html><body>' +
        '<script>window.LIBLOG_CONFIG = { sendToServer: false, interceptConsole: false };</script>' +
        '<script>' + fs.readFileSync(BRON, 'utf8') + '</script>' +
        '</body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/cma/main.php' }
    );
    return dom.window;
}

suite('lib-log: libLog is de naam, LibLog het laagje');

test('window.libLog is de logger, cmaLog wijst naar hetzelfde object', () => {
    const win = laad();
    for (const m of ['log', 'info', 'warning', 'warn', 'error', 'debug', 'flush', 'isDebug', 'setDebug', 'getConfig', 'refreshFromCookie', 'getRequestId']) {
        assert.gelijk(typeof win.libLog[m], 'function', 'libLog.' + m);
    }
    assert.waar(win.cmaLog === win.libLog, 'cmaLog === libLog');
});

test('LibLog bestaat nog, is een ander object, en geeft elke aanroep door', () => {
    const win = laad();
    assert.waar(!!win.LibLog, 'LibLog bestaat');
    assert.waar(win.LibLog !== win.libLog, 'LibLog is een laagje, niet hetzelfde object');
    for (const k of Object.keys(win.libLog)) {
        assert.waar(k in win.LibLog, 'LibLog mist ' + k);
    }
    win.LibLog.setDebug(true);
    assert.waar(win.libLog.isDebug(), 'setDebug via LibLog komt bij libLog terecht');
    win.libLog.setDebug(false);
    assert.onwaar(win.LibLog.isDebug(), 'isDebug via LibLog leest libLog');
    assert.gelijk(win.LibLog.getRequestId(), win.libLog.getRequestId(), 'zelfde request-id');
    assert.gelijk(win.LibLog.version, win.libLog.version, 'eigenschappen zijn live, geen kopie');
    assert.waar(win.LibLog.console === win.libLog.console, 'LibLog.console is dezelfde console');
});

test('nergens in het platform wordt LibLog nog aangeroepen', () => {
    const mappen = ['cma', 'cma/tools', 'cma/assets/js', 'cma/webcomponents', 'cma/classes', 'library', 'library/webcomponents', 'library/assets/js'];
    const treffers = [];
    for (const map of mappen) {
        const dir = path.join(PLATFORM, map);
        if (!fs.existsSync(dir)) continue;
        for (const f of fs.readdirSync(dir)) {
            if (!/\.(js|php|inc)$/.test(f) || /\.min\.js$/.test(f) || f === 'lib-log.js') continue;
            const src = fs.readFileSync(path.join(dir, f), 'utf8');
            const m = src.match(/\bLibLog\s*[.(]/g);
            if (m) treffers.push(map + '/' + f + ' (' + m.length + 'x)');
        }
    }
    assert.diepgelijk(treffers, [], 'gebruik libLog: ' + treffers.join(', '));
});
