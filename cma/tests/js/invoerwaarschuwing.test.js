/**
 * Een invoerfout is een waarschuwing, geen fout.
 *
 * "Vul alle verplichte velden in: …" is iets wat de gebruiker oplost, geen
 * fout van de applicatie. showError(…, { level: 'warning' }) meldt hem via
 * CmaErrorHandler.reportWarning (foutpaneel, niet de serverlog) en cmaLog.warn;
 * zonder level blijft het reportServerError + cmaLog.error.
 *
 * De methodes worden uit de uitgeleverde bron geknipt, niet nagebouwd.
 *
 * Run: node tests/js/run.js invoerwaarschuwing
 */

const fs = require('fs');
const path = require('path');

const CONTROLLER = path.join(__dirname, '..', '..', 'assets', 'js', 'form-controller.js');
const INLINE = path.join(__dirname, '..', '..', 'assets', 'js', 'inline-edit.js');

function knipMethode(src, kop) {
    const begin = src.indexOf(kop);
    if (begin === -1) throw new Error(`${kop} niet gevonden`);
    let i = src.indexOf('{', begin + kop.length), diepte = 0;
    const start = i;
    for (; i < src.length; i++) {
        if (src[i] === '{') diepte++;
        else if (src[i] === '}' && --diepte === 0) break;
    }
    return src.slice(start + 1, i);
}

function maakOmgeving() {
    const log = [];
    return {
        log,
        cmaLog: { error: (...a) => log.push(['console.error', a.join(' ')]), warn: (...a) => log.push(['console.warn', a.join(' ')]) },
        CmaErrorHandler: {
            reportServerError: (bron, msg) => log.push(['server', bron + ': ' + msg]),
            reportWarning: (msg) => log.push(['warning', msg])
        },
        window: { location: { href: 'http://localhost/cma/form.php' } }
    };
}

const fcBody = knipMethode(fs.readFileSync(CONTROLLER, 'utf8'), '    showError(message, options = false)');
function fcShowError(env, ...args) {
    const fn = new Function('cmaLog', 'CmaErrorHandler', 'window', 'message', 'options', fcBody);
    const self = { jsonForm: 'test', detailPanel: null, showTopNotification() {} };
    fn.call(self, env.cmaLog, env.CmaErrorHandler, env.window, args[0], args[1] === undefined ? false : args[1]);
}

const ieBody = knipMethode(fs.readFileSync(INLINE, 'utf8'), "        showError(message, details = '', level = 'error')");
function ieShowError(env, message, details = '', level = 'error') {
    const fn = new Function('CmaErrorHandler', 'window', 'message', 'details', 'level', ieBody);
    fn.call({ showNotification() {}, options: {} }, env.CmaErrorHandler, env.window, message, details, level);
}

suite('FormController.showError: invoerfouten als waarschuwing');

test('level warning gaat niet naar de serverlog', () => {
    const env = maakOmgeving();
    fcShowError(env, 'Vul alle verplichte velden in: Manager', { level: 'warning' });
    assert.gelijk(env.log.some(r => r[0] === 'server' || r[0] === 'console.error'), false);
    assert.gelijk(env.log.some(r => r[0] === 'warning'), true);
    assert.gelijk(env.log.some(r => r[0] === 'console.warn'), true);
});

test('zonder level blijft het een fout', () => {
    const env = maakOmgeving();
    fcShowError(env, 'Opslaan mislukt');
    assert.gelijk(env.log.some(r => r[0] === 'server'), true);
    assert.gelijk(env.log.some(r => r[0] === 'warning'), false);
});

test('validatie in validateForm gebruikt het waarschuwingsniveau', () => {
    const src = fs.readFileSync(CONTROLLER, 'utf8');
    assert.gelijk(src.includes("'Vul alle verplichte velden in', { level: 'warning' })"), true);
});

suite('InlineEdit.showError: invoerfouten als waarschuwing');

test('level warning gaat niet naar de serverlog', () => {
    const env = maakOmgeving();
    ieShowError(env, 'Vul alle verplichte velden in: Naam', '', 'warning');
    assert.gelijk(env.log.some(r => r[0] === 'server'), false);
    assert.gelijk(env.log.some(r => r[0] === 'warning'), true);
});

test('zonder level blijft het een fout', () => {
    const env = maakOmgeving();
    ieShowError(env, 'Fout bij opslaan');
    assert.gelijk(env.log.some(r => r[0] === 'server'), true);
});

suite('Invoerfouten zien er neutraal uit');

test('FormController toont level warning als lib-message type validation', () => {
    const { JSDOM } = require('jsdom');
    const dom = new JSDOM('<!doctype html><div class="detail-content"></div>');
    const env = maakOmgeving();
    const fn = new Function('cmaLog', 'CmaErrorHandler', 'window', 'customElements', 'document', 'message', 'options', fcBody);
    const self = { jsonForm: 'test', detailPanel: dom.window.document, showTopNotification() {} };
    const ce = { get: () => true };
    fn.call(self, env.cmaLog, env.CmaErrorHandler, env.window, ce, dom.window.document, 'Vul alle verplichte velden in: Manager', { level: 'warning' });
    assert.gelijk(dom.window.document.querySelector('lib-message').getAttribute('type'), 'validation');
    fn.call(self, env.cmaLog, env.CmaErrorHandler, env.window, ce, dom.window.document, 'Opslaan mislukt', false);
    assert.gelijk(dom.window.document.querySelector('lib-message').getAttribute('type'), 'error');
});

test('InlineEdit toont level warning als validation-toast', () => {
    const env = maakOmgeving();
    const types = [];
    const fn = new Function('CmaErrorHandler', 'window', 'message', 'details', 'level', ieBody);
    const self = { showNotification: (m, t) => types.push(t), options: {} };
    fn.call(self, env.CmaErrorHandler, env.window, 'Vul alle verplichte velden in: Naam', '', 'warning');
    fn.call(self, env.CmaErrorHandler, env.window, 'Fout bij opslaan', '', 'error');
    assert.gelijk(types.join(','), 'validation,error');
});

test('lib-message en lib-toaster kennen het type validation met een formulier-icoon', () => {
    const msg = fs.readFileSync(path.join(__dirname, '..', '..', '..', 'library', 'webcomponents', 'lib-message.js'), 'utf8');
    const toast = fs.readFileSync(path.join(__dirname, '..', '..', '..', 'library', 'webcomponents', 'lib-toaster.js'), 'utf8');
    const css = fs.readFileSync(path.join(__dirname, '..', '..', '..', 'library', 'css', 'lib-components.css'), 'utf8');
    assert.gelijk(msg.includes("validation: 'lnr-clipboard-text'"), true);
    assert.gelijk(toast.includes("'error', 'validation']"), true);
    assert.gelijk(css.includes('.lib-message--validation {'), true);
});
