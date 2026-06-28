<?php
/**
 * Stand-alone image editor.
 *
 * Opens an image that already lives on the server (identified by basepath + file)
 * and exposes the same editing operations as the files-wizard right-pane editor:
 * rotate, flip, brightness/contrast/saturation, sharpen, autocrop and crop. Every
 * operation is delegated to wizards/file-browser.php's existing image-op endpoint
 * (the canonical, login-guarded implementation backed by App\Library\Image), so no
 * image processing is duplicated here.
 *
 * Designed to be embedded in an iframe/lib-dialog from:
 *   - the files wizard's "Open editor" button, and
 *   - a consumer-site front-end (behind the CMA login guard below).
 *
 * On finish it posts {type:'image-editor-complete', file, width, height} to its
 * opener (parent window and/or window.opener).
 *
 * Query params:
 *   basepath    (required) base directory, e.g. /images/
 *   file        (required) filename to edit (may include a sub-path)
 *   path        (optional) sub-directory under basepath
 *   resizetype  0=free, 1=maximum, 2=fixed   (FormControlHelper::IMG_*)
 *   resizewidth / resizeheight  constraint dimensions for the rule above
 */

use App\Library\Request;
use App\Library\Response;
use Cma\SecurityHelper;

require_once __DIR__ . '/bootstrap.inc';

Response::noCache();

// CSS bundle is shared with the page chrome; needed for both the guard message and
// the editor itself.
$ieCss = minify_asset('../library/css/lib-variables.css,assets/css/colors.css,../library/library.css,assets/css/style.css,assets/css/image-editor.css');

// ── Login guard ──────────────────────────────────────────────────────────────
// Front-end visitors without a valid CMA session get an inline message (no
// redirect), which reads correctly inside an embedded dialog.
if (!SecurityHelper::isLoggedIn()) {
    ?><!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Afbeelding bewerken</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($ieCss) ?>">
    <script src="<?= htmlspecialchars(minify_asset('../library/webcomponents/lib-message.js')) ?>"></script>
</head>
<body class="image-editor-guard">
    <lib-message type="error">Geen toegang &mdash; log in op de CMA</lib-message>
</body>
</html><?php
    exit;
}

// ── Parameter handling (mirrors wizards/file-browser.php so the op endpoint and
// this page agree on basepath/path/file) ────────────────────────────────────
$basePath    = Request::query('basepath', '');
$currentFile = strtok(Request::query('file', ''), '?'); // strip ?versie= cache buster
$currentPath = Request::query('path', '');
$resizeType  = Request::queryInt('resizetype');
$resizeWidth = Request::queryInt('resizewidth');
$resizeHeight = Request::queryInt('resizeheight');

// If the file value carries a path, split it into directory + filename.
if ($currentFile !== '' && strpos($currentFile, '/') !== false) {
    if ($basePath !== '' && strpos($currentFile, $basePath) === 0) {
        $relativePath = substr($currentFile, strlen($basePath));
    } else {
        $relativePath = $currentFile;
    }
    $lastSlash = strrpos($relativePath, '/');
    if ($lastSlash !== false) {
        $currentPath = substr($relativePath, 0, $lastSlash + 1);
        $currentFile = substr($relativePath, $lastSlash + 1);
    }
}

// Normalise basepath to /.../ form.
if ($basePath !== '' && substr($basePath, 0, 1) !== '/') {
    $basePath = '/' . $basePath;
}
if ($basePath !== '' && substr($basePath, -1) !== '/') {
    $basePath .= '/';
}

$config = [
    'basePath'     => $basePath,
    'path'         => $currentPath,
    'file'         => $currentFile,
    'resizeType'   => $resizeType,
    'resizeWidth'  => $resizeWidth,
    'resizeHeight' => $resizeHeight,
    // Op endpoint, relative to this page (/cma/image-editor.php -> /cma/wizards/...).
    'endpoint'     => 'wizards/file-browser.php',
];

$ieJs = minify_asset('../library/error-handler.js,../library/webcomponents/lib-message.js,assets/js/image-editor.js');
?><!DOCTYPE html>
<html lang="nl">
<head>
    <script>
    // Inherit dark mode from the parent window.
    try {
        if (window.parent && window.parent !== window && window.parent.document.documentElement.classList.contains('dark-mode')) {
            document.documentElement.classList.add('dark-mode');
        }
    } catch (e) {}
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Afbeelding bewerken</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($ieCss) ?>">
    <script>window.IMAGE_EDITOR_CONFIG = <?= json_encode($config) ?>;</script>
    <script src="<?= htmlspecialchars($ieJs) ?>"></script>
</head>
<body class="image-editor-body">
    <div class="image-editor">
        <div class="ie-toolbar">
            <div class="ie-toolbar__group">
                <span class="tb-btn" title="Linksom draaien"><a href="javascript:imgEditor.rotate(-90)"><span class="lnr lnr-undo"></span><span class="tb-btn-text">90&deg;</span></a></span>
                <span class="tb-btn" title="Rechtsom draaien"><a href="javascript:imgEditor.rotate(90)"><span class="lnr lnr-redo"></span><span class="tb-btn-text">90&deg;</span></a></span>
                <span class="tb-btn" title="180&deg; draaien"><a href="javascript:imgEditor.rotate(180)"><span class="tb-btn-text">180&deg;</span></a></span>
                <span class="tb-sep"></span>
                <span class="tb-btn" title="Horizontaal spiegelen"><a href="javascript:imgEditor.flip('h')"><span class="lnr lnr-flip"></span></a></span>
                <span class="tb-btn" title="Verticaal spiegelen"><a href="javascript:imgEditor.flip('v')"><span class="lnr lnr-flip ie-icon-rot90"></span></a></span>
                <span class="tb-sep"></span>
                <span class="tb-btn" title="Lichter"><a href="javascript:imgEditor.filter('brightness','+')"><span class="lnr lnr-sun"></span></a></span>
                <span class="tb-btn" title="Donkerder"><a href="javascript:imgEditor.filter('brightness','-')"><span class="lnr lnr-moon"></span></a></span>
                <span class="tb-btn" title="Meer contrast"><a href="javascript:imgEditor.filter('contrast','+')"><span class="lnr lnr-contrast"></span></a></span>
                <span class="tb-btn" title="Minder contrast"><a href="javascript:imgEditor.filter('contrast','-')"><span class="lnr lnr-contrast ie-icon-dim"></span></a></span>
                <span class="tb-btn" title="Meer verzadiging"><a href="javascript:imgEditor.filter('saturation','+')"><span class="lnr lnr-drop"></span></a></span>
                <span class="tb-btn" title="Minder verzadiging"><a href="javascript:imgEditor.filter('saturation','-')"><span class="lnr lnr-drop ie-icon-dim"></span></a></span>
                <span class="tb-btn" title="Verscherpen"><a href="javascript:imgEditor.filter('sharpen','')"><span class="lnr lnr-magic-wand"></span></a></span>
            </div>
            <div class="ie-toolbar__group ie-toolbar__group--right">
                <span class="tb-btn" title="Bijsnijden"><a href="javascript:imgEditor.startCrop()"><span class="lnr lnr-crop"></span><span class="tb-btn-text">Bijsnijden</span></a></span>
                <span class="tb-btn" title="Witruimte automatisch bijsnijden"><a href="javascript:imgEditor.autocrop()"><span class="lnr lnr-frame-contract"></span></a></span>
                <label class="ie-margin" title="Marge rondom de inhoud bij autocrop">marge <input type="number" id="autocropMargin" min="0" max="50" step="1" value="10">%</label>
                <span class="tb-sep"></span>
                <span class="tb-btn ie-restore" id="ieRestore" title="Origineel terugzetten"><a href="javascript:imgEditor.restore()"><span class="lnr lnr-history"></span><span class="tb-btn-text">Herstel</span></a></span>
            </div>
        </div>

        <div class="image-editor-canvas">
            <div class="preview-wrap" id="previewWrap">
                <img id="editorImage" alt="Afbeelding bewerken">
            </div>
        </div>

        <div class="image-editor-info">
            <span id="ieDimensions">&ndash;</span>
            <span id="ieRule" class="ie-rule"></span>
        </div>

        <div class="image-editor-footer">
            <button type="button" class="btn btn-cancel" onclick="imgEditor.cancel()">Annuleren</button>
            <button type="button" class="btn btn-primary" onclick="imgEditor.finish()">Klaar</button>
        </div>
    </div>
</body>
</html>
