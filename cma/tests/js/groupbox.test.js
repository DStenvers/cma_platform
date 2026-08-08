/**
 * Bewijs voor "openklappen is niet hetzelfde als een voorkeur wijzigen".
 *
 * Een groepvak onthoudt in localStorage of het open of dicht staat. Bij het
 * toevoegen van een record wil je álle verplichte velden in één blik zien, ook
 * die in een groep die de gebruiker ooit heeft dichtgeklapt. Dat openklappen is
 * een eigenschap van dít lege record, geen voorkeur — het mag de bewaarde stand
 * dus niet overschrijven.
 *
 * Twee afspraken houden die scheiding heel, en beide staan hier vast:
 *
 *   1. cma-groupbox.open(false) klapt open zonder te bewaren; toggle(), open()
 *      zonder argument en close() bewaren wél.
 *   2. expandGroupboxesWithRequiredFields opent alleen groepen met een leeg
 *      verplicht veld, en doet dat via die niet-bewarende weg.
 *
 * Cypress kan dit niet dekken: het gaat om wat er NIET in localStorage terecht
 * komt, en dat vraagt een schone opslag per geval.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const GROUPBOX = path.join(__dirname, '..', '..', 'webcomponents', 'cma-groupbox.js');
const FORM_CONTROLLER = path.join(__dirname, '..', '..', 'assets', 'js', 'form-controller.js');

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
    let i = src.indexOf('{', match.index);
    let diepte = 0;
    for (; i < src.length; i++) {
        if (src[i] === '{') diepte++;
        else if (src[i] === '}') {
            diepte--;
            if (diepte === 0) return src.slice(match.index, i + 1);
        }
    }
    throw new Error('Einde van ' + naam + ' niet gevonden');
}

/**
 * Formulier met één groep per opgegeven beschrijving: een kop (cma-groupbox) en
 * een rij met één veld. De pagina wordt als HTML geparsed, zodat de component
 * zijn beginstand toepast zoals in de browser — inclusief de bewaarde stand die
 * hier vóór de component wordt weggeschreven.
 *
 * @param {Array<{verplicht: boolean, waarde?: string, bewaard?: string}>} groepen
 */
function maakFormulier(groepen) {
    const opslag = groepen.map((groep, i) => groep.bewaard
        ? 'localStorage.setItem("cma_grp_7_' + (i + 1) + '", "' + groep.bewaard + '");'
        : '').join('');

    const markup = groepen.map((groep, i) => {
        const id = i + 1;
        return '<cma-groupbox group-id="' + id + '" form-id="7" caption="Groep ' + id + '"></cma-groupbox>' +
            '<table><tr id="_g' + id + '_1"><td><input name="veld' + id + '"' +
            (groep.verplicht ? ' data-required="true"' : '') +
            ' value="' + (groep.waarde || '') + '"></td></tr></table>';
    }).join('');

    const dom = new JSDOM(
        '<!doctype html><html><body>' +
        '<script>' + opslag + '</script>' +
        '<form id="mainForm">' + markup + '</form>' +
        '<script>' + fs.readFileSync(GROUPBOX, 'utf8') + '</script>' +
        '</body></html>',
        { runScripts: 'dangerously', pretendToBeVisual: true, url: 'http://localhost/cma/form.php' }
    );

    const doc = dom.window.document;
    return {
        win: dom.window,
        doc,
        groepvak: (id) => doc.querySelector('cma-groupbox[group-id="' + id + '"]'),
        rij: (id) => doc.getElementById('_g' + id + '_1'),
        bewaard: (id) => dom.window.localStorage.getItem('cma_grp_7_' + id)
    };
}

/** Roept de echte expandGroupboxesWithRequiredFields aan op dit document. */
function klapVerplichteGroepenOpen({ win, doc }) {
    const script = doc.createElement('script');
    script.textContent = 'window.__controller = { mainForm: document.getElementById("mainForm"), ' +
        haalMethodeUitBron(FORM_CONTROLLER, 'expandGroupboxesWithRequiredFields') + ' };';
    doc.body.appendChild(script);
    win.__controller.expandGroupboxesWithRequiredFields();
}

suite('Groepvak: openklappen versus bewaren');

test('een dichtgeklapt groepvak leest zijn stand uit de opslag', () => {
    const f = maakFormulier([{ verplicht: true, bewaard: 'closed' }]);
    assert.onwaar(f.groepvak(1).isOpen);
});

test('open(false) klapt open zonder de bewaarde stand te raken', () => {
    const f = maakFormulier([{ verplicht: true, bewaard: 'closed' }]);
    f.groepvak(1).open(false);
    assert.waar(f.groepvak(1).isOpen);
    assert.onwaar(f.rij(1).classList.contains('groupbox-hidden'), 'rij zichtbaar');
    assert.gelijk(f.bewaard(1), 'closed', 'opslag ongemoeid');
});

test('open() zonder argument bewaart wél — dat is de handmatige weg', () => {
    const f = maakFormulier([{ verplicht: true, bewaard: 'closed' }]);
    f.groepvak(1).open();
    assert.gelijk(f.bewaard(1), 'open');
});

test('toggle() bewaart — een klik is een voorkeur', () => {
    const f = maakFormulier([{ verplicht: true, bewaard: 'closed' }]);
    f.groepvak(1).toggle();
    assert.gelijk(f.bewaard(1), 'open');
    f.groepvak(1).toggle();
    assert.gelijk(f.bewaard(1), 'closed');
});

test('close() bewaart', () => {
    const f = maakFormulier([{ verplicht: true, bewaard: 'open' }]);
    f.groepvak(1).close();
    assert.gelijk(f.bewaard(1), 'closed');
});

suite('Toevoegen: groepen met een leeg verplicht veld gaan open');

test('een dichtgeklapte groep met een leeg verplicht veld gaat open, de opslag blijft dicht', () => {
    const f = maakFormulier([{ verplicht: true, bewaard: 'closed' }]);
    klapVerplichteGroepenOpen(f);
    assert.waar(f.groepvak(1).isOpen);
    assert.gelijk(f.bewaard(1), 'closed');
});

test('een verplicht veld met een standaardwaarde laat de groep dicht', () => {
    const f = maakFormulier([{ verplicht: true, waarde: 'X', bewaard: 'closed' }]);
    klapVerplichteGroepenOpen(f);
    assert.onwaar(f.groepvak(1).isOpen);
});

test('een groep zonder verplicht veld blijft dicht', () => {
    const f = maakFormulier([{ verplicht: false, bewaard: 'closed' }]);
    klapVerplichteGroepenOpen(f);
    assert.onwaar(f.groepvak(1).isOpen);
});

test('alleen de groep met het lege verplichte veld gaat open', () => {
    const f = maakFormulier([
        { verplicht: false, bewaard: 'closed' },
        { verplicht: true, bewaard: 'closed' }
    ]);
    klapVerplichteGroepenOpen(f);
    assert.onwaar(f.groepvak(1).isOpen);
    assert.waar(f.groepvak(2).isOpen);
    assert.gelijk(f.bewaard(2), 'closed');
});
