/**
 * mijnRINO SCORM-plugin voor CKEditor 4.
 *
 * Voegt een toolbar-knop toe (groep "insert") die een SCORM-picker opent in
 * een lib_OpenWindowCentered-popup. De picker draait op /mod/scorm/picker.php
 * en stuurt na selectie via window.postMessage een pakket-object terug
 * (id / name / url). De plugin zet dan een klikbare link in de editor; bij
 * klik in de gepubliceerde tekst opent diezelfde lib_OpenWindowCentered het
 * SCORM-pakket maximaal in een iframe.
 */

CKEDITOR.plugins.add('scorm', {
  icons: 'scorm',
  init: function(editor) {

    editor.addCommand('mrScormInsert', {
      exec: function() {
        // De picker zelf bepaalt zijn breedte via /adam.css; we openen
        // viewport-vullend zodat hij maximaal voelt. De maximize-knop op
        // de window-caption blijft beschikbaar voor toggle.
        var w = Math.max(800, window.innerWidth - 40);
        var h = Math.max(500, window.innerHeight - 80);
        var pickerUrl = '/mod/scorm/picker.php';

        // Eénmalige listener voor het bericht dat de picker terugstuurt.
        var onMsg = function(e) {
          var d = e && e.data;
          if (!d || d.source !== 'mr-scorm-picker' || !d.pkg) return;
          window.removeEventListener('message', onMsg);
          insertLink(editor, d.pkg);
        };
        window.addEventListener('message', onMsg);

        if (typeof window.lib_OpenWindowCentered === 'function') {
          lib_OpenWindowCentered(pickerUrl, '', w, h, 'SCORM-pakket kiezen');
        } else {
          // Fallback: eenvoudige popup als de helper (om welke reden dan
          // ook) niet beschikbaar is op deze pagina.
          window.open(pickerUrl, 'mr_scorm_picker',
            'width=' + w + ',height=' + h + ',scrollbars=yes,resizable=yes');
        }
      }
    });

    editor.ui.addButton('Scorm', {
      label: 'SCORM-pakket invoegen',
      command: 'mrScormInsert',
      toolbar: 'insert',
      icon: CKEDITOR.plugins.getPath('scorm') + 'icons/scorm.svg'
    });
  }
});

/**
 * Bouwt de invoeg-HTML en plakt 'm in de editor. De link bevat de player-URL
 * als data-attribuut; bij klik in de live pagina opent lib_OpenWindowCentered
 * het pakket maximaal in een iframe.
 *
 * De zichtbare tekst komt uit pkg.linkText (door de gebruiker ingevoerd in
 * de picker, default = pakketnaam). Vóór de tekst staat een vast toga-icoon
 * (inline SVG, geen font-dependency — werkt ook als adam.css niet geladen is).
 */
function insertLink(editor, pkg) {
  function esc(s) {
    return String(s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  var rawName = pkg.name || ('SCORM #' + pkg.id);
  var rawText = pkg.linkText || rawName;
  var safeName = esc(rawName);   // voor de window-caption (popup-titel)
  var safeText = esc(rawText);   // voor de zichtbare linktekst
  var safeUrl  = String(pkg.url).replace(/'/g, '%27');

  var onclick =
      "var u=this.getAttribute('data-scorm-url');" +
      "if(window.lib_OpenWindowCentered){" +
        "lib_OpenWindowCentered(u,'',Math.max(800,window.innerWidth-40),Math.max(500,window.innerHeight-80),'" + safeName + "');" +
      "}else{window.open(u,'_blank');}" +
      "return false;";

  // Toga-icoon (mortarboard) — inline SVG zodat de knop óók zonder
  // adam.css de juiste vorm heeft.
  var icon =
      '<svg class="mr-scorm-icon" viewBox="0 0 24 24" aria-hidden="true">' +
        '<path d="M12 3 L1 8.5 L12 14 L21 9.5 L21 14.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"/>' +
        '<path d="M5 11 L5 15 C5 16.6 8.5 18 12 18 C15.5 18 19 16.6 19 15 L19 11" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"/>' +
      '</svg>';

  // pkg.preview: gekozen via "Zonder voortgang" in de picker; de URL bevat dan al
  // preview=1. Het data-attribuut is er zodat het in de HTML terug te zien is.
  var html =
      '<a class="mr-scorm-launch" href="#" data-scorm-id="' + pkg.id + '"' +
      ' data-scorm-url="' + safeUrl + '" data-scorm-name="' + safeName + '"' +
      (pkg.preview ? ' data-scorm-preview="1"' : '') +
      ' onclick="' + onclick.replace(/"/g, '&quot;') + '">' +
      icon + '<span class="mr-scorm-text">' + safeText + '</span>' +
      '</a>';

  editor.insertHtml(html);
}
