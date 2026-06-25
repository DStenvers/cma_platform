<?php
require_once __DIR__ . '/bootstrap.inc';

use App\Library\Request;

$mode = (Request::query('mode', 'insert') === 'edit') ? 'edit' : 'insert';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $mode === 'edit' ? 'Link bewerken' : 'Link invoegen'; ?></title>
    <?php cma_error_handler(); ?>
    <link rel="stylesheet" href="minify.php?f=assets/css/style.css,assets/css/form.css">
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
    }

    function save() {
        var href = document.getElementById("href").value.trim();
        if (href === '') {
            if (typeof libAlert === 'function') { libAlert('Vul een URL in.'); } else { alert('Vul een URL in.'); }
            document.getElementById("href").focus();
            return;
        }
        var target = '';
        if (document.getElementById("target1").checked) {
            target = '_blank';
        } else if (document.getElementById("target2").checked) {
            target = document.getElementById("target_other").value.trim();
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
                    <div class="help-text">Inclusief https:// of mailto:</div>
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
</body>
</html>
