/**
 * Startwaarden voor datumvelden: "today"/"Date()" is vandaag, "+14" is over twee
 * weken, "01-01-2099" wordt ISO. De oude details.asp evalueerde zulke
 * standaardexpressies bij een nieuw record; de nieuwe renderer gaf een
 * lib-datepicker helemaal geen data-default.
 */
const fs = require('fs');
const path = require('path');

const FORM_CONTROLLER = path.join(__dirname, '..', '..', 'assets', 'js', 'form-controller.js');

function haalMethodeUitBron(bestand, naam) {
    const src = fs.readFileSync(bestand, 'utf8');
    const match = new RegExp('\\n[ \\t]*' + naam + '\\s*\\(').exec(src);
    if (!match) throw new Error('Methode ' + naam + ' niet gevonden');
    let i = src.indexOf('{', match.index);
    let diepte = 0;
    for (; i < src.length; i++) {
        if (src[i] === '{') diepte++;
        else if (src[i] === '}') { diepte--; if (diepte === 0) return src.slice(match.index, i + 1); }
    }
    throw new Error('Einde van ' + naam + ' niet gevonden');
}

const obj = new Function('return { ' + haalMethodeUitBron(FORM_CONTROLLER, 'resolveDateDefault') + ' };')();
const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

suite('Datumveld: startwaarde-expressies');

test('today, Date() en Now() zijn vandaag', () => {
    const vandaag = iso(new Date());
    for (const e of ['today', 'Date()', 'Now()', 'CURRENT_DATE_STAMP', 'vandaag']) {
        assert.gelijk(obj.resolveDateDefault(e), vandaag, e);
    }
});

test('+14 is veertien dagen verder', () => {
    const d = new Date(); d.setDate(d.getDate() + 14);
    assert.gelijk(obj.resolveDateDefault('+14'), iso(d));
});

test('vaste dd-mm-jjjj wordt ISO, ISO blijft', () => {
    assert.gelijk(obj.resolveDateDefault('01-01-2099'), '2099-01-01');
    assert.gelijk(obj.resolveDateDefault('2026-09-19'), '2026-09-19');
});
