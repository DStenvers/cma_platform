/**
 * cma-query-preview: SQL bewerken met <cma-sql-editor>.
 *
 * De bewerkmodus van de rapportgenerator is een <cma-sql-editor> (regelnummers,
 * kleuring, opmaakknop) in plaats van een kale textarea. Afspraken:
 * - de editor krijgt bij Bewerken de huidige SQL;
 * - preview.sql leest tijdens het bewerken direct uit de editor, niet uit diens
 *   vertraagde change-event, zodat Uitvoeren meteen na typen het getypte draait;
 * - een render tijdens het bewerken (setError na een mislukte run) laat het getypte staan;
 * - Ctrl+Enter in de editor past de wijziging toe (sql-change) en voert uit (refresh);
 * - de change van de editor blijft binnen de preview.
 *
 * Draait tegen de echte bronnen (library/sql-utils.js, cma-sql-editor.js,
 * cma-query-preview.js), in jsdom.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const ROOT = path.join(__dirname, '..', '..', '..');
const BRONNEN = [
    path.join(ROOT, 'library', 'sql-utils.js'),
    path.join(ROOT, 'cma', 'webcomponents', 'cma-sql-editor.js'),
    path.join(ROOT, 'cma', 'webcomponents', 'cma-query-preview.js'),
];

function maakPreview() {
    const dom = new JSDOM(
        '<!doctype html><html><body>' +
        '<div id="host"><cma-query-preview id="p" editable></cma-query-preview></div>' +
        BRONNEN.map((b) => '<script>' + fs.readFileSync(b, 'utf8') + '</script>').join('') +
        '</body></html>',
        { runScripts: 'dangerously', pretendToBeVisual: true, url: 'http://localhost/cma/report-designer.php' }
    );
    const win = dom.window;
    const p = win.document.getElementById('p');
    const editor = () => p.querySelector('cma-sql-editor');
    const typ = (tekst) => {
        const ta = editor().shadowRoot.getElementById('sqlTextarea');
        ta.value = tekst;
        ta.dispatchEvent(new win.Event('input', { bubbles: true }));
    };
    const toets = (key, extra) => {
        const ta = editor().shadowRoot.getElementById('sqlTextarea');
        ta.dispatchEvent(new win.KeyboardEvent('keydown', Object.assign({ key, bubbles: true, cancelable: true }, extra || {})));
    };
    return { win, p, editor, typ, toets };
}

suite('cma-query-preview: SQL bewerken met cma-sql-editor');

test('de bewerkmodus is een cma-sql-editor met regelnummers, gevuld met de huidige SQL', () => {
    const t = maakPreview();
    t.p.setSql('SELECT Naam\nFROM tblDeelnemer');
    t.p.enterEditMode();
    assert.waar(!!t.editor(), 'cma-sql-editor aanwezig');
    assert.gelijk(t.p.querySelector('textarea'), null, 'geen kale textarea in de preview');
    assert.gelijk(t.editor().sql, 'SELECT Naam\nFROM tblDeelnemer');
    assert.waar(t.p.querySelector('#sqlContainer').classList.contains('editing'), 'container in bewerkmodus');
});

test('preview.sql geeft meteen na typen het getypte, zonder op de vertraagde change te wachten', () => {
    const t = maakPreview();
    t.p.setSql('SELECT 1');
    t.p.enterEditMode();
    t.typ('SELECT 2');
    assert.gelijk(t.p.sql, 'SELECT 2');
});

test('setError tijdens het bewerken laat het getypte staan', () => {
    const t = maakPreview();
    t.p.setSql('SELECT 1');
    t.p.enterEditMode();
    t.typ('SELECT * FROM tblFout');
    t.p.setError('Tabel bestaat niet');
    assert.gelijk(t.editor().sql, 'SELECT * FROM tblFout');
    assert.gelijk(t.p.sql, 'SELECT * FROM tblFout');
});

test('Ctrl+Enter past de wijziging toe en voert uit', () => {
    const t = maakPreview();
    const events = [];
    t.p.addEventListener('sql-change', (e) => events.push('sql-change:' + e.detail.sql + ':' + e.detail.requestSync));
    t.p.addEventListener('refresh', () => events.push('refresh'));
    t.p.setSql('SELECT 1');
    t.p.enterEditMode();
    t.typ('SELECT 3');
    t.toets('Enter', { ctrlKey: true });
    assert.diepgelijk(events, ['sql-change:SELECT 3:true', 'refresh']);
    assert.gelijk(t.p.sql, 'SELECT 3');
    assert.onwaar(t.p.querySelector('#sqlContainer').classList.contains('editing'), 'bewerkmodus verlaten');
});

test('Annuleren laat de oude SQL staan', () => {
    const t = maakPreview();
    t.p.setSql('SELECT 1');
    t.p.enterEditMode();
    t.typ('DELETE FROM x');
    t.p.exitEditMode();
    assert.gelijk(t.p.sql, 'SELECT 1');
});

test('de change van de editor komt niet buiten de preview', () => {
    const t = maakPreview();
    let buiten = 0;
    t.win.document.getElementById('host').addEventListener('change', () => { buiten++; });
    t.p.setSql('SELECT 1');
    t.p.enterEditMode();
    t.editor().format();   // dispatcht direct een change
    assert.gelijk(buiten, 0);
});
