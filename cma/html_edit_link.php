<?php
require_once __DIR__ . '/bootstrap.inc';

use App\Library\Request;

$mode = (Request::query('mode', 'insert') === 'edit') ? 'edit' : 'insert';
// Upload folder for "Bestand kiezen / uploaden" (old link-pages.asp used cma_htmledit_img_path)
$uploadBase = trim((string) \App\Library\Settings::get('editor_image_path'), '/');
if ($uploadBase === '') {
    $uploadBase = 'uploads';
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $mode === 'edit' ? 'Link bewerken' : 'Link invoegen'; ?></title>
    <?php cma_error_handler(); ?>
    <link rel="stylesheet" href="/cma/minify.php?f=assets/css/style.css,assets/css/form.css">
    <style>
        body {
            margin: 0;
            padding: 20px;
            background: var(--bg-body);
        }
        .link-form {
            max-width: 500px;
        }
        .form-row {
            display: flex;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .form-row label {
            width: 100px;
            flex-shrink: 0;
            padding-top: 6px;
            color: var(--text-muted);
        }
        .form-row .input-group {
            flex: 1;
        }
        .form-row input[type="text"] {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }
        .form-row .help-text {
            font-size: var(--font-size-xs);
            color: var(--text-muted);
            margin-top: 4px;
        }
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .radio-group label {
            width: auto;
            padding-top: 0;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }
        .radio-group input[type="radio"] {
            margin: 0;
        }
        .other-target {
            margin-left: 20px;
            margin-top: 4px;
        }
        .other-target input {
            width: 200px;
            padding: 4px 6px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 20px;
        }
    </style>
    <script>
    var MODE = <?php echo json_encode($mode); ?>;
    var UPLOAD_BASE = <?php echo json_encode($uploadBase . '/'); ?>;
    var ZOOM_RE = /^javascript:lib_window_ImageZoom\('([^']*)'(?:,\s*'([^']*)')?\)$/i;
    var IMAGE_RE = /\.(jpe?g|png|gif|webp)(\?.*)?$/i;

    // "Bestand kiezen / uploaden": the file browser in an iframe inside this dialog
    // (old link-pages.asp embedded file_frameset.asp the same way). It posts
    // {type:'file-browser-select', value} to its parent, which is this window.
    var browserOpen = false;
    function toggleFileBrowser() {
        var wrap = document.getElementById('fileBrowserWrap');
        var frame = document.getElementById('fileBrowserFrame');
        browserOpen = !browserOpen;
        if (browserOpen && !frame.getAttribute('src')) {
            frame.setAttribute('src', 'wizards/file-browser.php?layout=0&fieldname=cma_link_file&basepath=' + encodeURIComponent(UPLOAD_BASE));
        }
        wrap.style.display = browserOpen ? '' : 'none';
        document.getElementById('page1').style.display = browserOpen ? 'none' : '';
        if (window.parent && typeof window.parent.lib_OpenWindowCenteredMax === 'function') {
            window.parent.lib_OpenWindowCenteredMax();
        }
    }
    // The embedded browser closes "its popup" through this on select/cancel
    window.lib_OpenWindowCenteredClose = function() { if (browserOpen) toggleFileBrowser(); };
    window.addEventListener('message', function(e) {
        if (e.origin !== window.location.origin || !e.data || e.data.type !== 'file-browser-select') return;
        var value = String(e.data.value || '').replace(/^\/+/, '');
        document.getElementById('href').value = '/' + UPLOAD_BASE + value;
        if (browserOpen) toggleFileBrowser();
        if (IMAGE_RE.test(value)) document.getElementById('zoom').checked = true;
        updateSubjectRow();
        document.getElementById('title').focus();
    });

    // Image links open in the zoom window (lib_window_ImageZoom) as the old wizard did
    function updateZoomRow() {
        var href = document.getElementById('href').value.trim();
        var isImage = IMAGE_RE.test(href.split('?')[0]) || ZOOM_RE.test(href);
        document.getElementById('zoomRow').style.display = isImage ? '' : 'none';
    }
    // The editor and the selected anchor live on the top window (set by CMA.editor
    // before this dialog was opened). Same-origin iframe, so we can read them directly.
    var EDITWIN = window.top;

    function closeDialog() {
        if (window.parent && typeof window.parent.lib_OpenWindowCenteredClose === 'function') {
            window.parent.lib_OpenWindowCenteredClose(true);
        }
    }

    function init() {
        var anchor = (MODE === 'edit' && EDITWIN.selectedAnchor) ? (EDITWIN.selectedAnchor.$ || EDITWIN.selectedAnchor) : null;
        if (anchor) {
            var href = anchor.getAttribute('data-cke-saved-href') || anchor.getAttribute('href') || '';
            var target = anchor.getAttribute('target') || '';
            var zoom = href.match(ZOOM_RE);
            if (zoom) {
                // javascript:lib_window_ImageZoom('url','title') -> plain url + checkbox
                href = zoom[1].replace(/^https?:\/\/[^\/]+/i, '');
                document.getElementById('zoom').checked = true;
            }
            document.getElementById("href").value = href;
            document.getElementById("title").value = anchor.getAttribute('title') || '';

            var t = target.toLowerCase();
            if (t === '' || t === '_top' || t === '_self') {
                document.getElementById("target0").checked = true;
            } else if (t === '_blank' || t === 'blank') {
                document.getElementById("target1").checked = true;
            } else {
                document.getElementById("target2").checked = true;
                document.getElementById("target_other").value = target;
            }
        } else {
            document.getElementById("target0").checked = true;
        }
        document.getElementById("href").focus();
        updateSubjectRow();
    }

    // E-mail link builder (old link-pages.asp "email adres" wizard): a bare address
    // becomes mailto:, and the subject field appears for mailto links
    function isEmailLike(v) { return /^[^\s@\/:]+@[^\s@\/:]+\.[a-z]{2,}$/i.test(v); }
    function updateSubjectRow() {
        var href = document.getElementById("href").value.trim();
        var isMail = href.toLowerCase().indexOf('mailto:') === 0 || isEmailLike(href);
        document.getElementById("subjectRow").style.display = isMail ? '' : 'none';
        updateZoomRow();
        if (isMail && href.indexOf('?subject=') > -1 && document.getElementById("subject").value === '') {
            document.getElementById("subject").value = decodeURIComponent(href.split('?subject=')[1] || '');
        }
    }
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById("href").addEventListener('input', updateSubjectRow);
        updateSubjectRow();
    });

    function save() {
        var href = document.getElementById("href").value.trim();
        if (href === '') {
            if (typeof libAlert === 'function') { libAlert('Vul een URL in.'); } else { alert('Vul een URL in.'); }
            document.getElementById("href").focus();
            return;
        }
        if (isEmailLike(href)) {
            href = 'mailto:' + href;
        }
        if (href.toLowerCase().indexOf('mailto:') === 0) {
            var subject = document.getElementById("subject").value.trim();
            href = href.split('?')[0] + (subject !== '' ? '?subject=' + encodeURIComponent(subject) : '');
        } else if (/^www\./i.test(href)) {
            href = 'https://' + href;
        }
        var target = '';
        if (document.getElementById("target1").checked) {
            target = '_blank';
        } else if (document.getElementById("target2").checked) {
            target = document.getElementById("target_other").value.trim();
        }
        var zoomRow = document.getElementById('zoomRow');
        if (zoomRow.style.display !== 'none' && document.getElementById('zoom').checked) {
            var abs = /^https?:\/\//i.test(href) ? href : window.location.origin + (href.charAt(0) === '/' ? '' : '/') + href;
            var zoomTitle = document.getElementById("title").value.trim();
            href = "javascript:lib_window_ImageZoom('" + abs.replace(/'/g, '%27') + "','" + encodeURIComponent(zoomTitle) + "')";
            target = '';
        }
        EDITWIN.CMA.editor.applyLink({
            href: href,
            title: document.getElementById("title").value.trim(),
            target: target
        });
        closeDialog();
    }
    </script>
</head>
<body onload="init()" onkeydown="if(event.keyCode===13){event.preventDefault();save();}">
    <div id="page1">
        <form name="linkForm" class="link-form" onsubmit="return false;">
            <div class="form-row">
                <label for="href">URL:</label>
                <div class="input-group">
                    <input type="text" name="href" id="href" maxlength="256">
                    <div class="help-text">Inclusief https:// of mailto: &mdash; of <a href="#" onclick="toggleFileBrowser();return false;">bestand kiezen / uploaden</a></div>
                </div>
            </div>

            <div class="form-row" id="zoomRow" style="display:none">
                <label></label>
                <div class="input-group">
                    <label><input type="checkbox" id="zoom" name="zoom"> Plaatje openen in een zoom-venster</label>
                </div>
            </div>

            <div class="form-row" id="subjectRow" style="display:none">
                <label for="subject">Onderwerp:</label>
                <div class="input-group">
                    <input type="text" name="subject" id="subject" maxlength="256">
                    <div class="help-text">Optioneel onderwerp voor de e-mail (mailto)</div>
                </div>
            </div>

            <div class="form-row">
                <label for="title">Omschrijving:</label>
                <div class="input-group">
                    <input type="text" name="title" id="title" maxlength="256">
                </div>
            </div>

            <div class="form-row">
                <label>Openen in:</label>
                <div class="input-group">
                    <div class="radio-group">
                        <label>
                            <input type="radio" id="target0" name="target" value="_top">
                            Hetzelfde venster
                        </label>
                        <label>
                            <input type="radio" id="target1" name="target" value="_blank">
                            Een nieuw venster
                        </label>
                        <label>
                            <input type="radio" id="target2" name="target" value="">
                            Anders, namelijk:
                        </label>
                        <div class="other-target">
                            <input type="text" id="target_other" name="target_other" maxlength="156" placeholder="Frame of tabblad naam">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="button" onclick="closeDialog()">Annuleren</button>
                <button type="button" class="button" onclick="save()"><?php echo $mode === 'edit' ? 'Opslaan' : 'Invoegen'; ?></button>
            </div>
        </form>
    </div>
    <div id="fileBrowserWrap" style="display:none">
        <div class="form-actions" style="margin:6px 0"><button type="button" class="button" onclick="toggleFileBrowser()">&larr; Terug naar de link</button></div>
        <iframe id="fileBrowserFrame" title="Bestand kiezen" style="width:100%;height:calc(100vh - 70px);border:0"></iframe>
    </div>
</body>
</html>
