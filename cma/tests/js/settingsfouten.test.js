/**
 * Systeeminstellingen: een validatiefout noemt de veldnaam, kleurt veld en label
 * en klapt een ingeklapte groep open.
 *
 * Draait tegen de echte functies uit cma/tools/tools_settings.php (het blok
 * tussen de markers settings-errors:start/end), in jsdom.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const BRON = path.join(__dirname, '..', '..', 'tools', 'tools_settings.php');

function foutenScript() {
    const src = fs.readFileSync(BRON, 'utf8');
    const start = src.indexOf('/* settings-errors:start */');
    const end = src.indexOf('/* settings-errors:end */');
    if (start === -1 || end === -1) throw new Error('markers settings-errors:start/end ontbreken in tools_settings.php');
    return src.slice(start, end);
}

function maak() {
    const dom = new JSDOM(
        '<!doctype html><html><body><form id="f"><table>' +
        '<tr class="groupbox-row" data-group="1"><td><cma-groupbox id="g1" collapsed></cma-groupbox></td></tr>' +
        '<tr id="r1" data-group-row="1"><td class="label-cell"><label for="hack_block_threshold">Max. hackpogingen</label></td><td><input id="hack_block_threshold"></td></tr>' +
        '<tr id="r2" data-group-row="1"><td class="label-cell"><label for="hack_block_window_minutes">Tijdvenster (minuten)</label></td><td><input id="hack_block_window_minutes"></td></tr>' +
        '<tr class="groupbox-row" data-group="2"><td><cma-groupbox id="g2" collapsed></cma-groupbox></td></tr>' +
        '<tr id="r3" data-group-row="2"><td class="label-cell"><label for="x">Andere</label></td><td><input id="x"></td></tr>' +
        '</table></form><script>' + foutenScript() + '</script></body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/cma/tools/tools_settings.php' }
    );
    const win = dom.window;
    win.document.querySelectorAll('cma-groupbox').forEach((box) => {
        box.open = function () { this.removeAttribute('collapsed'); };
    });
    return { win, form: win.document.getElementById('f') };
}

suite('settings-fouten');

test('de melding noemt de veldnaam', () => {
    const p = maak();
    const m = p.win.cmaSettingsErrorMessages(p.form, {
        hack_block_threshold: 'Vul een geheel getal in.',
        hack_block_window_minutes: 'Vul een geheel getal in.',
    });
    assert.gelijk(m[0], 'Max. hackpogingen: Vul een geheel getal in.');
    assert.gelijk(m[1], 'Tijdvenster (minuten): Vul een geheel getal in.');
});

test('veld en rij worden gemarkeerd en de ingeklapte groep gaat open', () => {
    const p = maak();
    p.win.cmaSettingsMarkErrors(p.form, { hack_block_threshold: 'x' });
    const d = p.win.document;
    assert.gelijk(d.getElementById('hack_block_threshold').classList.contains('cma-tool__settings-invalid'), true);
    assert.gelijk(d.getElementById('r1').classList.contains('cma-tool__settings-invalid-row'), true);
    assert.gelijk(d.getElementById('r2').classList.contains('cma-tool__settings-invalid-row'), false);
    assert.gelijk(d.getElementById('g1').hasAttribute('collapsed'), false, 'groep met de fout is open');
    assert.gelijk(d.getElementById('g2').hasAttribute('collapsed'), true, 'andere groep blijft dicht');
});

test('een volgende run haalt oude markeringen weg', () => {
    const p = maak();
    p.win.cmaSettingsMarkErrors(p.form, { hack_block_threshold: 'x' });
    p.win.cmaSettingsMarkErrors(p.form, {});
    assert.gelijk(p.win.document.querySelectorAll('.cma-tool__settings-invalid, .cma-tool__settings-invalid-row').length, 0);
});
