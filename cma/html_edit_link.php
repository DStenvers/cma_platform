<?php
require_once __DIR__ . '/bootstrap.inc';

use App\Library\Application;

$appBasePath = Application::get('base_path', '/');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link bewerken</title>
    <?php cma_error_handler(); ?>
    <link rel="stylesheet" href="minify.php?f=assets/css/style.css,assets/css/form.css">
    <script src="minify.php?f=wizards/wizard.js"></script>
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
    </style>
    <script>
    var selectedA = window.parent.window.dialogArguments ? window.parent.window.dialogArguments["anchor"] : null;

    function init() {
        if (selectedA) {
            document.getElementById("href").value = selectedA.href || '';
            document.getElementById("title").value = selectedA.title || '';

            if (selectedA.target == "" || selectedA.target.toLowerCase() == "_top" || selectedA.target.toLowerCase() == "_self") {
                document.getElementById("target0").checked = true;
            } else if (selectedA.target.toLowerCase() == "_blank" || selectedA.target.toLowerCase() == "blank") {
                document.getElementById("target1").checked = true;
            } else {
                document.getElementById("target2").checked = true;
                document.getElementById("target_other").value = selectedA.target;
            }
        } else {
            if (typeof modal_alert === 'function') {
                modal_alert('Kan de te wijzigen link niet vinden!');
            } else {
                libAlert('Kan de te wijzigen link niet vinden!');
            }
        }
    }

    function call_WizardGetNextPage(current_page) {
        return 1;
    }

    function call_WizardFinishPressed() {
        if (selectedA) {
            selectedA.setAttribute("href", document.getElementById("href").value);
            selectedA.href = document.getElementById("href").value;
            selectedA.setAttribute("data-cke-saved-href", document.getElementById("href").value);
            selectedA.title = document.getElementById("title").value;

            if (selectedA.title == '') {
                selectedA.removeAttribute("title");
            }

            if (document.getElementById("target0").checked) {
                selectedA.target = "";
                selectedA.removeAttribute("target");
            } else if (document.getElementById("target1").checked) {
                selectedA.target = "_blank";
            } else {
                selectedA.target = document.getElementById("target_other").value;
            }
            return true;
        }
        return false;
    }

    function call_WizardGetShowFinish(current_page) {
        return (current_page == 1);
    }
    </script>
</head>
<body class="wizardcontent" onload="init(); if(window.parent && typeof window.parent.WizardActivatePage === 'function') window.parent.WizardActivatePage(1)" onkeypress="if(window.parent && typeof window.parent.WizardButtonPressed === 'function') window.parent.WizardButtonPressed(event.keyCode); return true;">
    <div id="page1">
        <form name="linkForm" class="link-form">
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
        </form>
    </div>
</body>
</html>
