/**
 * Bewijs voor "selected kiest het beginblad, ongeacht de volgorde van de attributen".
 *
 * cma-tabs krijgt zijn tabs uit het tabs-attribuut of uit <tab-item>-kinderen.
 * Het selected-attribuut komt bij het parsen langs vóór die tabs er zijn; de
 * index valt dan buiten bereik en werd weggegooid. Een pagina die met
 * selected="2" een bepaald tabblad wil openen (de logreader met ?tab=…, de
 * backuptool) toonde dan stil het eerste.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const TABS = path.join(__dirname, '..', '..', 'webcomponents', 'cma-tabs.js');

function maakTabs(markup) {
    const dom = new JSDOM(
        '<!doctype html><html><body>' +
        '<script>window.cmaLog = { warn() {}, log() {}, error() {} };</script>' +
        markup +
        '<script>' + fs.readFileSync(TABS, 'utf8') + '</script>' +
        '</body></html>',
        { runScripts: 'dangerously', pretendToBeVisual: true, url: 'http://localhost/cma/tools/logreader.php' }
    );
    // Zonder kinderen initialiseert de component een frame later; wacht dat af.
    const tabs = dom.window.document.querySelector('cma-tabs');
    return new Promise((klaar) => dom.window.requestAnimationFrame(() => klaar(tabs)));
}

suite('cma-tabs: selected als beginblad');

test('selected vóór het tabs-attribuut', () => maakTabs('<cma-tabs selected="2" tabs=\'["A","B","C"]\'><div slot="tab-0">a</div><div slot="tab-1">b</div><div slot="tab-2">c</div></cma-tabs>').then((tabs) => {
    assert.gelijk(tabs.selectedIndex, 2);
}));

test('selected na het tabs-attribuut', () => maakTabs('<cma-tabs tabs=\'["A","B","C"]\' selected="1"></cma-tabs>').then((tabs) => {
    assert.gelijk(tabs.selectedIndex, 1);
}));

test('selected met <tab-item>-kinderen', () => maakTabs('<cma-tabs selected="1"><tab-item title="Maken"></tab-item><tab-item title="Beheren"></tab-item></cma-tabs>').then((tabs) => {
    assert.gelijk(tabs.selectedIndex, 1);
}));

test('een selected buiten bereik houdt het eerste blad', () => maakTabs('<cma-tabs selected="7" tabs=\'["A","B"]\'></cma-tabs>').then((tabs) => {
    assert.gelijk(tabs.selectedIndex, 0);
}));
