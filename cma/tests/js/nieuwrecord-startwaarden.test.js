/**
 * Een nieuw record opent met de startwaarden uit zijn definitie.
 *
 * Een veld kan in de JSON-definitie een "defaultValue" hebben; de renderer zet die als
 * data-default op het besturingselement en applyDefaultValues() past hem toe. Die functie
 * werd alleen aangeroepen door newRecord() — de knop "Toevoegen" in een al geladen
 * formulier. De init-tak voor een SERVER-gerenderd nieuw record (/cma/form/<form>/new, en
 * dus ook het zijpaneel dat die URL opent) sloeg hem over. Gevolg: logins.json zegt
 * "defaultValue": true bij actief, en het scherm opende met de schakelaar op uit.
 *
 * Twee dingen worden hier vastgehouden: dat de functie doet wat hij moet doen, en dat de
 * tak hem aanroept. Dat tweede is waar de fout zat — de functie zelf was in orde.
 *
 * De functie wordt NIET nagebouwd: dit bestand knipt hem uit de echte form-controller.js.
 */

const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const BRON = path.join(__dirname, '..', '..', 'assets', 'js', 'form-controller.js');
const bron = fs.readFileSync(BRON, 'utf8');

/** Knip applyDefaultValues() uit de echte bron en maak er een losse functie van. */
function haalToepasserUitBron() {
    const start = bron.indexOf('    applyDefaultValues() {');
    const eind = bron.indexOf('    updateNewChangableOnlyFields(isNewRecord) {', start);
    if (start === -1 || eind === -1) {
        throw new Error('Kon applyDefaultValues() niet in form-controller.js vinden');
    }
    return bron.slice(start, eind)
        .replace('applyDefaultValues() {', 'function applyDefaultValues() {')
        .replace(/this\.mainForm/g, 'mainForm');
}

/** Een formulier met de drie soorten velden die een startwaarde kunnen dragen. */
function maakPagina() {
    const dom = new JSDOM(`<!doctype html><html><body class="is-creating mode-detail">
        <div class="form-layout"><form id="mainForm">
            <lib-switch name="actief" data-type="checkbox" data-default="True"></lib-switch>
            <lib-switch name="geblokkeerd" data-type="checkbox" data-default="False"></lib-switch>
            <input type="text" name="soort" data-type="textbox" data-default="deelnemer">
            <input type="text" name="opmerking" data-type="textbox" data-default="">
        </form></div></body></html>`, { runScripts: 'dangerously' });
    const win = dom.window;
    const script = win.document.createElement('script');
    script.textContent = `
        var mainForm = document.getElementById('mainForm');
        var cmaLog = { log: function () {}, warn: function () {}, error: function () {} };
        ${haalToepasserUitBron()}
    `;
    win.document.body.appendChild(script);
    return { win, doc: win.document };
}

test('een schakelaar met defaultValue true opent aan', () => {
    const { win, doc } = maakPagina();

    win.applyDefaultValues();

    assert.waar(doc.querySelector('lib-switch[name="actief"]').checked,
        'de schakelaar met data-default="True" hoort aan te staan');
});

test('een schakelaar zonder startwaarde blijft uit', () => {
    const { win, doc } = maakPagina();

    win.applyDefaultValues();

    assert.onwaar(!!doc.querySelector('lib-switch[name="geblokkeerd"]').checked,
        'data-default="False" mag de schakelaar niet aanzetten');
});

test('een tekstveld krijgt zijn startwaarde, een leeg data-default doet niets', () => {
    const { win, doc } = maakPagina();

    win.applyDefaultValues();

    assert.gelijk(doc.querySelector('input[name="soort"]').value, 'deelnemer',
        'het tekstveld hoort zijn startwaarde te krijgen');
    assert.gelijk(doc.querySelector('input[name="opmerking"]').value, '',
        'een leeg data-default hoort niets te zetten');
});

test('de directe URL naar een nieuw record past de startwaarden ook toe', () => {
    // Hier zat de fout. De behaviour-tests hierboven bewijzen de functie; deze houdt de
    // AANROEP vast op de weg waar hij ontbrak: het server-gerenderde nieuwe record, de
    // URL die het zijpaneel opent. newRecord() had hem al.
    const start = bron.indexOf("} else if (document.body.classList.contains('is-creating')) {");
    assert.waar(start > -1, 'de init-tak voor een nieuw record moet bestaan');
    const eind = bron.indexOf('        } else {', start);
    const tak = bron.slice(start, eind > start ? eind : start + 3000);
    assert.waar(tak.indexOf('applyDefaultValues') > -1,
        'de server-gerenderde nieuw-record-tak moet de startwaarden toepassen');
});
