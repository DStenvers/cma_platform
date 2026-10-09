/**
 * Contentblok-veldtype "scorm" in blockedit.
 *
 * Een blok als S01 (SCORM-module op een infopagina) kiest zijn pakket via de site-picker
 * /mod/scorm/picker.php?mode=block. Het veld is een verborgen input met het pakket-id, een
 * label en een [Selecteer SCORM-pakket]-link; blockedit_scorm_set() vult id + label en zet de
 * pakketnaam als titel zolang die leeg is. Opgeslagen wordt het id, net als bij image/file,
 * zodat het formaat gelijk is aan de ASP-site.
 *
 * Daarnaast, nodig voor S01: een nieuw blok krijgt de "default" uit de definitie (de switch
 * "Voortgang" staat dan op Nee in plaats van ongekozen), en elke [variabele] in de template
 * wordt vervangen, ook als hij er vaker in staat (scorm_titel staat er twee keer in).
 *
 * De functies worden uit de echte cma/assets/js/blockedit.js geknipt.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const ROOT = path.join(__dirname, '..', '..', '..');
const SRC = fs.readFileSync(path.join(ROOT, 'cma', 'assets', 'js', 'blockedit.js'), 'utf8');
const JQUERY = fs.readFileSync(path.join(ROOT, 'library', 'jquery.min.js'), 'utf8');

function knip(naam) {
    const start = SRC.indexOf('function ' + naam + '(');
    if (start === -1) throw new Error(naam + ' niet gevonden in blockedit.js');
    let i = SRC.indexOf('{', start), d = 0;
    for (; i < SRC.length; i++) {
        if (SRC[i] === '{') d++;
        else if (SRC[i] === '}' && --d === 0) return SRC.slice(start, i + 1);
    }
    throw new Error('einde van ' + naam + ' niet gevonden');
}

const S01 = {
    id: 'S01', title: 'SCORM',
    html: '<div class="mr-scorm-embed" data-scorm-id="[scorm_id]" data-voortgang="[scorm_voortgang]" data-titel="[scorm_titel]"><span>[scorm_titel]</span></div>',
    variables: {
        scorm_id: { description: 'SCORM-pakket', type: 'scorm', required: true },
        scorm_titel: { description: 'Titel (boven de module)', type: 'text' },
        scorm_voortgang: { description: 'Voortgang bijhouden', type: 'switch', options: ['Nee', 'Ja'], default: 'Nee' }
    }
};

function pagina() {
    const dom = new JSDOM('<!doctype html><html><body><div class="blockedit_elt"><table id="t"></table></div></body></html>',
        { runScripts: 'dangerously', url: 'http://localhost/cma/details.php' });
    const w = dom.window;
    const s = w.document.createElement('script');
    s.textContent = JQUERY + '\n' +
        'var htmls = []; var element_cnt = 0; var cmaLog = { warn(){}, error(){} };\n' +
        knip('ucfirst') + '\n' + knip('string_JSON_fetch') + '\n' +
        knip('blockedit_createfield') + '\n' + knip('blockedit_scorm_set') + '\n' +
        'window.__veld = blockedit_createfield; window.__set = blockedit_scorm_set;';
    w.document.body.appendChild(s);
    return w;
}

suite('blockedit: veldtype scorm (contentblok S01)');

test('nieuw blok: verborgen pakket-id, label en selecteerlink naar de picker', () => {
    const w = pagina();
    const html = w.__veld(S01, 'scorm_id', null);
    assert.onwaar(html.indexOf('Onbekend type veld') > -1, 'type scorm is bekend');
    w.document.getElementById('t').innerHTML = html;
    const input = w.document.querySelector("input[type=hidden][name='scorm_id']");
    assert.waar(!!input, 'verborgen input met name=scorm_id');
    assert.gelijk(input.value, '');
    assert.gelijk(w.document.getElementById(input.id + '_label').textContent, '(geen pakket gekozen)');
    assert.waar(html.indexOf("blockedit_scorm_select('" + input.id + "')") > -1, 'selecteerlink roept de picker aan');
});

test('bestaand blok toont het opgeslagen pakket-id', () => {
    const w = pagina();
    w.document.getElementById('t').innerHTML = w.__veld(S01, 'scorm_id', { type: 'S01', variables: { scorm_id: '42' } });
    const input = w.document.querySelector("input[name='scorm_id']");
    assert.gelijk(input.value, '42');
    assert.gelijk(w.document.getElementById(input.id + '_label').textContent, 'pakket #42');
});

test('blockedit_scorm_set vult id, label en een lege titel; wissen maakt leeg', () => {
    const w = pagina();
    w.document.getElementById('t').innerHTML = w.__veld(S01, 'scorm_id', null) + w.__veld(S01, 'scorm_titel', null);
    const input = w.document.querySelector("input[name='scorm_id']");
    w.__set(input.id, 7, 'Module Diagnostiek');
    assert.gelijk(input.value, '7');
    assert.gelijk(w.document.getElementById(input.id + '_label').textContent, 'Module Diagnostiek (#7)');
    assert.gelijk(w.document.querySelector("input[name='scorm_titel']").value, 'Module Diagnostiek');
    w.__set(input.id, '', '');
    assert.gelijk(input.value, '');
    assert.gelijk(w.document.getElementById(input.id + '_label').textContent, '(geen pakket gekozen)');
});

test('switch in een nieuw blok staat op de default uit de definitie', () => {
    const w = pagina();
    w.document.getElementById('t').innerHTML = w.__veld(S01, 'scorm_voortgang', null);
    const gekozen = w.document.querySelector("input[type=radio]:checked");
    assert.waar(!!gekozen, 'er is een keuze gemaakt');
    assert.gelijk(gekozen.value, 'Nee');
});

test('opslaan: scorm leest de verborgen input, elke [variabele] wordt vervangen, exports aanwezig', () => {
    const collect = knip('blockedit_collect_htmls');
    assert.waar(/case "file":\s*case "scorm":\s*sValue = \$\( this \)\.find\("input\[name='"\+cDataEltName\+"'\]"\)\.val\(\);/.test(collect),
        'scorm wordt als input-waarde opgehaald, net als image/file');
    assert.waar(collect.indexOf('cHTML.split( "[" + cDataEltName + "]" ).join( sValue )') > -1, 'alle voorkomens vervangen');
    assert.waar(SRC.indexOf('window.blockedit_scorm_select = blockedit_scorm_select;') > -1, 'select geëxporteerd (inline onclick)');
    assert.waar(SRC.indexOf('window.blockedit_scorm_set = blockedit_scorm_set;') > -1, 'set geëxporteerd');
    assert.waar(knip('blockedit_scorm_select').indexOf("'/mod/scorm/picker.php?mode=block'") > -1, 'picker in mode=block');
});

suite('blockedit: site-bloklijst samenvoegen met de platformlijst');

test('site-template met nieuw id komt erbij, zelfde id vervangt het platformblok', () => {
    const merge = new Function(knip('blockedit_merge_templates') + '; return blockedit_merge_templates;')();
    const platform = { version: '1.0.0', templates: [{ id: 'C47', title: 'Accordeon' }, { id: 'T01', title: 'Tekst' }] };
    const site = { templates: [{ id: 'T01', title: 'Tekst (site)' }, { id: 'S01', title: 'SCORM' }] };
    const uit = merge(platform, site);
    assert.gelijk(uit.templates.map(t => t.id + ':' + t.title).join(','), 'C47:Accordeon,T01:Tekst (site),S01:SCORM');
    assert.gelijk(uit.version, '1.0.0', 'overige sleutels van de platformlijst blijven');
    assert.gelijk(platform.templates.length, 2, 'de platformlijst zelf wordt niet aangepast');
    assert.gelijk(merge(platform, null), platform, 'geen site-lijst: platformlijst ongewijzigd');
    assert.gelijk(merge(platform, { templates: [] }), platform);
});

test('de loader haalt na de platformlijst de site-lijst assets/contentblocks/contentblocks.json op', () => {
    assert.waar(SRC.indexOf('"/assets/contentblocks/contentblocks.json?v="') > -1, 'site-URL');
    assert.waar(knip('blockedit_load_definitions').indexOf('blockedit_load_site_definitions(parsed)') > -1,
        'na een bruikbare platformlijst volgt de site-lijst');
    const site = knip('blockedit_load_site_definitions');
    assert.waar(site.indexOf('.fail(') > -1 && site.indexOf('klaar(null)') > -1, 'zonder site-lijst wordt gewoon gerenderd');
});

suite('CKEditor-plugin scorm');

test('"Zonder voortgang" zet data-scorm-preview="1" op de link', () => {
    const PLUGIN = fs.readFileSync(path.join(ROOT, 'cma', 'ckeditor', 'plugins', 'scorm', 'plugin.js'), 'utf8');
    const start = PLUGIN.indexOf('function insertLink(');
    const insertLink = new Function(PLUGIN.slice(start) + '; return insertLink;')();
    let html = '';
    const editor = { insertHtml: h => { html = h; } };
    insertLink(editor, { id: 3, name: 'Module', url: '/mod/scorm/player.php?id=3&preview=1&mode=review', preview: true });
    assert.waar(html.indexOf('data-scorm-preview="1"') > -1, 'preview-attribuut aanwezig');
    assert.waar(html.indexOf('class="mr-scorm-launch"') > -1 && html.indexOf('data-scorm-id="3"') > -1);
    insertLink(editor, { id: 4, name: 'Module', url: '/mod/scorm/player.php?id=4' });
    assert.onwaar(html.indexOf('data-scorm-preview') > -1, 'zonder preview geen attribuut');
});
