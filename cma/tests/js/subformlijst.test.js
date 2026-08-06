/**
 * Bewijs voor "de lijst ververst na een wijziging in een subformulier".
 *
 * Een record dat je in een subformulier opent, staat in een lijst die aan het
 * BOVENLIGGENDE formulier toebehoort. Het venster waarin je het bewerkt weet dat
 * alleen als het met parent-context is geopend. Twee afspraken houden dat spoor
 * heel, en beide zijn hier vastgelegd:
 *
 *   1. CmaInlineEdit opent een rij via onRowClick zodra de eigenaar van de tabel
 *      die meegeeft (dat doet een subformulier); zonder callback valt het terug
 *      op zijn eigen popup (dat is de hoofdlijst).
 *   2. De ververser zoekt het tabblad op form-id, niet op positie — de index van
 *      een tabblad zegt niets zonder de tabs erbij.
 *
 * Cypress kan dit niet dekken: dat vraagt een draaiende site.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const FORM_CONTROLLER = path.join(__dirname, '..', '..', 'assets', 'js', 'form-controller.js');
const INLINE_EDIT = path.join(__dirname, '..', '..', 'assets', 'js', 'inline-edit.js');

/**
 * Knip één methode uit de echte bron, op naam. Zo test dit bestand de code die
 * ook wordt uitgeleverd, niet een kopie ervan.
 */
function haalMethodeUitBron(bestand, naam) {
    const src = fs.readFileSync(bestand, 'utf8');
    const match = new RegExp('\\n[ \\t]*' + naam + '\\s*\\(').exec(src);
    if (!match) {
        throw new Error('Methode ' + naam + ' niet gevonden in ' + path.basename(bestand));
    }
    const start = match.index;
    let i = src.indexOf('{', start);
    let diepte = 0;
    for (; i < src.length; i++) {
        if (src[i] === '{') diepte++;
        else if (src[i] === '}') {
            diepte--;
            if (diepte === 0) return src.slice(start, i + 1);
        }
    }
    throw new Error('Einde van ' + naam + ' niet gevonden');
}

/** Document met een subformulier-tabblad per opgegeven form-id. */
function maakPaginaMetTabs(formIds) {
    const dom = new JSDOM('<!doctype html><html><body></body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/cma/form.php' });
    const doc = dom.window.document;
    const tabs = doc.createElement('cma-tabs');
    tabs.id = 'subformTabs';
    formIds.forEach((id, i) => {
        const item = doc.createElement('tab-item');
        item.dataset.id = id;
        item.dataset.index = String(i);
        doc.body.appendChild(tabs);
        tabs.appendChild(item);
    });
    doc.body.appendChild(tabs);
    return dom;
}

/** Controller-stub met de echte zoek-/verversmethoden erin. */
function maakController(dom) {
    const bron = haalMethodeUitBron(FORM_CONTROLLER, 'findSubformIndex') + ',' +
                 haalMethodeUitBron(FORM_CONTROLLER, 'refreshSubformList');
    const script = dom.window.document.createElement('script');
    script.textContent = `
        window.__geladen = [];
        window.__controller = {
            loadSubformData(index) { window.__geladen.push(index); },
            ${bron}
        };
    `;
    dom.window.document.body.appendChild(script);
    return dom.window.__controller;
}

/** CmaInlineEdit-stub met de echte openRecord erin. */
function maakEditor(opties) {
    const dom = new JSDOM('<!doctype html><html><body></body></html>', { runScripts: 'dangerously' });
    const script = dom.window.document.createElement('script');
    script.textContent = `
        window.__eigenPopup = [];
        window.__maakEditor = function(opties) {
            return {
                options: opties,
                openFormPopup(recordId, copy) { window.__eigenPopup.push([recordId, copy]); },
                ${haalMethodeUitBron(INLINE_EDIT, 'openRecord')}
            };
        };
    `;
    dom.window.document.body.appendChild(script);
    return { editor: dom.window.__maakEditor(opties), win: dom.window };
}

suite('Subformulier-lijst verversen');

test('het tabblad wordt op form-id gevonden, niet op positie', () => {
    const dom = maakPaginaMetTabs(['_deelnemers', '_menu_items', '_verklaringen']);
    const controller = maakController(dom);

    assert.gelijk(controller.findSubformIndex('_menu_items'), 1);
    assert.gelijk(controller.findSubformIndex('_deelnemers'), 0);
});

test('een onbekend subformulier levert -1 op (en dus geen verkeerde lijst)', () => {
    const dom = maakPaginaMetTabs(['_deelnemers']);
    const controller = maakController(dom);

    assert.gelijk(controller.findSubformIndex('_menu_items'), -1);
    assert.gelijk(controller.findSubformIndex(''), -1);
    assert.gelijk(controller.findSubformIndex(null), -1);
});

test('verversen haalt precies het tabblad van dat subformulier op', () => {
    const dom = maakPaginaMetTabs(['_deelnemers', '_menu_items']);
    const controller = maakController(dom);

    assert.waar(controller.refreshSubformList('_menu_items'), 'ververst gemeld');
    assert.diepgelijk(dom.window.__geladen, [1]);
});

test('een formulier zonder dat subformulier vraagt niets op en meldt dat', () => {
    const dom = maakPaginaMetTabs(['_deelnemers']);
    const controller = maakController(dom);

    assert.onwaar(controller.refreshSubformList('_menu_items'), 'niets ververst');
    assert.diepgelijk(dom.window.__geladen, []);
});

test('een rij in een subformulier gaat naar de eigenaar van de tabel', () => {
    const geopend = [];
    const { editor, win } = maakEditor({ onRowClick: (rowId) => geopend.push(rowId) });

    editor.openRecord('42');

    assert.diepgelijk(geopend, ['42']);
    assert.diepgelijk(win.__eigenPopup, [], 'niet ook nog de eigen popup');
});

test('zonder callback (de hoofdlijst) opent de eigen popup', () => {
    const { editor, win } = maakEditor({});

    editor.openRecord('42');

    assert.diepgelijk(win.__eigenPopup, [['42', false]]);
});
