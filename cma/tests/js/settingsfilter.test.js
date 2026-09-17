/**
 * Systeeminstellingen: het zoekveld verbergt rijen die niet matchen, verbergt
 * groepen zonder zichtbare rij en opent een groep die wél matcht.
 *
 * Draait tegen de echte functie uit cma/tools/tools_settings.php (het blok
 * tussen de markers settings-filter:start/end), in jsdom.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const BRON = path.join(__dirname, '..', '..', 'tools', 'tools_settings.php');

function filterScript() {
    const src = fs.readFileSync(BRON, 'utf8');
    const start = src.indexOf('/* settings-filter:start */');
    const end = src.indexOf('/* settings-filter:end */');
    if (start === -1 || end === -1) throw new Error('markers settings-filter:start/end ontbreken in tools_settings.php');
    return src.slice(start, end);
}

function maak() {
    const dom = new JSDOM(
        '<!doctype html><html><body><form id="f"><table>' +
        '<tr class="groupbox-row" data-group="1"><td><cma-groupbox id="g1"></cma-groupbox></td></tr>' +
        '<tr id="r1" data-group-row="1" data-search="fouten mailen error_mail_enabled"><td>a</td></tr>' +
        '<tr id="r2" data-group-row="1" data-search="ontvanger foutmeldingen error_mail_to"><td>b</td></tr>' +
        '<tr class="groupbox-row" data-group="2"><td><cma-groupbox id="g2" collapsed></cma-groupbox></td></tr>' +
        '<tr id="r3" data-group-row="2" data-search="standaard cachetijd cache_default_ttl"><td>c</td></tr>' +
        '</table></form><script>' + filterScript() + '</script></body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/cma/tools/tools_settings.php' }
    );
    const win = dom.window;
    // Een minimale cma-groupbox: open(false) registreren we, zodat de test ziet dat hij geroepen wordt.
    win.document.querySelectorAll('cma-groupbox').forEach((box) => {
        box.opened = 0;
        box.open = function () { this.opened++; this.removeAttribute('collapsed'); };
    });
    const hidden = (id) => win.document.getElementById(id).classList.contains('cma-tool__settings-hidden');
    return { win, hidden, filter: (t) => win.cmaSettingsFilter(win.document.getElementById('f'), t) };
}

suite('settings-filter');

test('leeg zoekveld toont alles', () => {
    const p = maak();
    p.filter('');
    assert.gelijk(p.hidden('r1'), false);
    assert.gelijk(p.hidden('r3'), false);
});

test('een zoekterm verbergt niet-matchende rijen en lege groepen', () => {
    const p = maak();
    p.filter('cachetijd');
    assert.gelijk(p.hidden('r1'), true, 'rij zonder match verborgen');
    assert.gelijk(p.hidden('r2'), true);
    assert.gelijk(p.hidden('r3'), false, 'de match blijft zichtbaar');
    const kop1 = p.win.document.querySelector('tr[data-group="1"]');
    const kop2 = p.win.document.querySelector('tr[data-group="2"]');
    assert.gelijk(kop1.classList.contains('cma-tool__settings-hidden'), true, 'groep zonder zichtbare rij verborgen');
    assert.gelijk(kop2.classList.contains('cma-tool__settings-hidden'), false);
});

test('een groep met een match wordt geopend, ook op de variabelenaam', () => {
    const p = maak();
    p.filter('CACHE_DEFAULT');
    const box = p.win.document.getElementById('g2');
    assert.gelijk(box.opened, 1, 'open() geroepen op de matchende, ingeklapte groep');
    assert.gelijk(box.hasAttribute('collapsed'), false);
    p.filter('');
    assert.gelijk(p.hidden('r1'), false, 'wissen brengt alles terug');
});
