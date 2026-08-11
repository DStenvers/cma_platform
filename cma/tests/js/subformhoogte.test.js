/**
 * Bewijs voor "één eigenaar van de hoogte onder het formulier".
 *
 * Het detailpaneel is een flex-kolom met drie kinderen: het formulier, de
 * sleepbalk, en de subform-sectie. Er kan er maar ÉÉN een eigen maat hebben; de
 * ander krijgt wat overblijft. Dat was niet zo:
 *
 *   - .subform-section had `height: var(--subform-height)` én `flex: 1 0 auto`,
 *     dus een eigen maat én de opdracht te groeien, plus een min-height;
 *   - .detail-content had `flex: 1`, dus groeide mee;
 *   - de sleepbalk zette daarnaast een inline hoogte op .detail-content;
 *   - en de maat werd op twee plekken bewaard (formState.subformHeight naast
 *     cma_fold_form_foldH), die uiteen konden lopen.
 *
 * Vier partijen over dezelfde ruimte: de sectie kreeg niet de hoogte die het
 * paneel wel had, en records in het subformulier vielen buiten beeld.
 *
 * Nu meet de balk alleen de subform-sectie op. Het formulier erboven krijgt de
 * rest via flex — er wordt niets aan uitgerekend, met of zonder gegevens. Is er
 * geen balk (nieuw record), dan is er geen sectie.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const FOLD = path.join(__dirname, '..', '..', 'webcomponents', 'cma-fold.js');
const FORM_TEMPLATE = path.join(__dirname, '..', '..', 'classes', 'FormTemplate.php');
const FORM_CSS = path.join(__dirname, '..', '..', 'assets', 'css', 'form.css');
const CONTROLLER = path.join(__dirname, '..', '..', 'assets', 'js', 'form-controller.js');

/**
 * Detailpaneel met formulier, balk en subform-sectie — de echte volgorde en de
 * echte maatregels uit form.css.
 *
 * @param {Object} opties
 * @param {string} [opties.bewaard]  stand in localStorage (JSON)
 * @param {boolean} [opties.nieuw]   nieuw record: sectie en balk verborgen
 */
function maakPaneel({ bewaard = null, nieuw = false } = {}) {
    const opslag = bewaard
        ? `localStorage.setItem('cma_fold_form_foldH', ${JSON.stringify(bewaard)});`
        : '';
    const verborgen = nieuw ? 'display:none' : '';

    const dom = new JSDOM(
        '<!doctype html><html><head><style>' +
        '.detail-panel{display:flex;flex-direction:column;height:600px}' +
        '.detail-content{flex:1;min-height:0;overflow-y:auto}' +
        '.subform-section{display:flex;flex-direction:column;flex:0 0 auto}' +
        '</style></head><body>' +
        '<script>' + opslag + '</script>' +
        '<div class="detail-panel">' +
        '<div class="detail-content" id="detailContent"></div>' +
        '<cma-fold class="fold-horizontal" orientation="horizontal" target=".subform-section"' +
        ' reverse min-size="100" max-size="800" default-size="250" storage-key="form_foldH"' +
        (verborgen ? ' style="' + verborgen + '"' : '') + '></cma-fold>' +
        '<div class="subform-section" id="subformSection"' +
        (verborgen ? ' style="' + verborgen + '"' : '') + '></div>' +
        '</div>' +
        '<script>' + fs.readFileSync(FOLD, 'utf8') + '</script>' +
        '</body></html>',
        { runScripts: 'dangerously', pretendToBeVisual: true, url: 'http://localhost/cma/form.php' }
    );

    const doc = dom.window.document;
    return {
        win: dom.window,
        sectie: doc.getElementById('subformSection'),
        formulier: doc.getElementById('detailContent'),
        balk: doc.querySelector('cma-fold')
    };
}

suite('Hoogte onder het formulier: één eigenaar');

test('de balk meet de subform-sectie op, niet het formulier', () => {
    const p = maakPaneel({ bewaard: '{"size":300,"collapsed":false}' });
    assert.gelijk(p.sectie.style.height, '300px');
    assert.gelijk(p.formulier.style.height, '', 'aan het formulier wordt niets gerekend');
    assert.gelijk(p.formulier.style.flex, '', 'ook geen flex opgelegd');
});

test('zonder bewaarde stand krijgt de sectie de startmaat', () => {
    const p = maakPaneel();
    assert.gelijk(p.sectie.style.height, '250px');
    assert.gelijk(p.sectie.style.flex, '0 0 250px');
});

test('bij een nieuw record krijgt de sectie geen hoogte', () => {
    const p = maakPaneel({ bewaard: '{"size":300,"collapsed":false}', nieuw: true });
    assert.gelijk(p.sectie.style.height, '', 'geen hoogte');
    assert.gelijk(p.sectie.style.flex, '', 'geen flex');
});

test('een nieuw record vergeet de bewaarde stand niet', () => {
    const p = maakPaneel({ bewaard: '{"size":300,"collapsed":false}', nieuw: true });
    assert.waar(p.win.localStorage.getItem('cma_fold_form_foldH') !== null);
});

test('een ingeklapte balk drukt de sectie samen, niet het formulier', () => {
    const p = maakPaneel({ bewaard: '{"collapsed":true,"savedSize":300}' });
    assert.gelijk(p.sectie.style.height, '0px');
    assert.gelijk(p.formulier.style.height, '');
});

suite('De bron: geen tweede eigenaar meer');

test('form.css kent geen --subform-height meer', () => {
    const css = fs.readFileSync(FORM_CSS, 'utf8');
    assert.gelijk(css.indexOf('--subform-height'), -1,
        'een vaste hoogte in een variabele is een tweede eigenaar naast de balk');
});

test('de subform-sectie groeit niet mee', () => {
    const css = fs.readFileSync(FORM_CSS, 'utf8');
    // Het basisblok staat aan het begin van een regel; selectors als
    // "body.has-subform ... .subform-section {" bevatten dezelfde tekens maar
    // zijn een ander blok. Op de substring zoeken pakt de verkeerde.
    const match = /^\.subform-section \{([^}]*)\}/m.exec(css);
    assert.waar(!!match, 'basisblok .subform-section gevonden');
    const regels = match[1];
    assert.waar(regels.indexOf('flex: 0 0 auto') !== -1, 'flex staat op 0 0 auto');
    assert.gelijk(regels.indexOf('min-height'), -1, 'geen min-height die met flex vecht');
});

test('de controller bewaart de hoogte niet nog eens apart', () => {
    const js = fs.readFileSync(CONTROLLER, 'utf8');
    assert.gelijk(js.indexOf('subform-height'), -1, 'geen CSS-variabele meer gezet');
    assert.gelijk(js.indexOf('calculateDynamicSubformHeight'), -1, 'geen eigen schatting meer');
});

test('het formulier zet de balk op de subform-sectie', () => {
    const php = fs.readFileSync(FORM_TEMPLATE, 'utf8');
    const regel = php.split('\n').find(r => r.indexOf('fold-horizontal') !== -1 && r.indexOf('cma-fold') !== -1);
    assert.waar(!!regel, 'de balk staat in het sjabloon');
    assert.waar(regel.indexOf('target=".subform-section"') !== -1, 'meet de sectie op');
    assert.waar(regel.indexOf('reverse') !== -1, 'sleeprichting omgekeerd, want de sectie staat eronder');
    assert.waar(regel.indexOf('default-size=') !== -1, 'heeft een startmaat');
});
