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

// Optional aspect-ratio lock (e.g. aspect=16:9). Unlike resizetype=fixed this only
// constrains the crop to a ratio — it does NOT force a specific output pixel size.
// Consumer sites pass this to restrict what shape can be produced (karaat: 16:9).
$aspectW = 0;
$aspectH = 0;
$aspectRaw = trim((string)Request::query('aspect', ''));
if ($aspectRaw !== '' && preg_match('/^\s*(\d+)\s*[:x\/]\s*(\d+)\s*$/i', $aspectRaw, $m)) {
    $aspectW = (int)$m[1];
    $aspectH = (int)$m[2];
}

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
    'aspectW'      => $aspectW,
    'aspectH'      => $aspectH,
    // Op endpoint, relative to this page (/cma/image-editor.php -> /cma/wizards/...).
    'endpoint'     => 'wizards/file-browser.php',
];

$ieJs = minify_asset('../library/error-handler.js,../library/webcomponents/lib-message.js,../library/webcomponents/lib-loader.js,webcomponents/cma-toolbar.js,assets/js/image-editor.js');
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
    <div class="image-editor-container">
        <cma-toolbar wrap variant="detail">
            <left>
                <button type="button" class="btn" onclick="imgEditor.rotate(-90)" title="Linksom draaien">&#8634; 90&deg;</button>
                <button type="button" class="btn" onclick="imgEditor.rotate(90)" title="Rechtsom draaien">&#8635; 90&deg;</button>
                <button type="button" class="btn" onclick="imgEditor.rotate(180)" title="180&deg; draaien">180&deg;</button>
                <button type="button" class="btn" onclick="imgEditor.flip('h')" title="Horizontaal spiegelen">Spiegel &#8596;</button>
                <button type="button" class="btn" onclick="imgEditor.flip('v')" title="Verticaal spiegelen">Spiegel &#8597;</button>
                <button type="button" class="btn" onclick="imgEditor.filter('brightness','+')" title="Lichter">Lichter</button>
                <button type="button" class="btn" onclick="imgEditor.filter('brightness','-')" title="Donkerder">Donkerder</button>
                <button type="button" class="btn" onclick="imgEditor.filter('contrast','+')" title="Meer contrast">Contrast +</button>
                <button type="button" class="btn" onclick="imgEditor.filter('contrast','-')" title="Minder contrast">Contrast &#8722;</button>
                <button type="button" class="btn" onclick="imgEditor.filter('saturation','+')" title="Meer verzadiging">Kleur +</button>
                <button type="button" class="btn" onclick="imgEditor.filter('saturation','-')" title="Minder verzadiging">Kleur &#8722;</button>
                <button type="button" class="btn" onclick="imgEditor.filter('sharpen','')" title="Verscherpen"><span class="lnr lnr-magic-wand"></span> Scherp</button>
            </left>
            <right>
                <button type="button" class="btn" onclick="imgEditor.startCrop()" title="Bijsnijden"><span class="lnr lnr-crop"></span> Bijsnijden</button>
                <button type="button" class="btn" onclick="imgEditor.autocrop()" title="Witruimte automatisch bijsnijden"><span class="lnr lnr-frame-contract"></span> Autocrop</button>
                <label class="ie-margin" title="Marge rondom de inhoud bij autocrop">marge <input type="number" id="autocropMargin" min="0" max="50" step="1" value="10">%</label>
                <button type="button" class="btn" id="ieRestore" onclick="imgEditor.restore()" title="Origineel terugzetten"><span class="lnr lnr-history"></span> Herstel</button>
            </right>
        </cma-toolbar>

        <div class="image-editor-canvas">
            <div class="preview-wrap" id="previewWrap">
                <img id="editorImage" alt="Afbeelding bewerken">
            </div>
        </div>

        <div class="image-editor-info">
            <span class="dimensions" id="ieDimensions">&ndash;</span>
            <span class="crop-info" id="ieRule"></span>
        </div>

        <div class="image-editor-footer">
            <button type="button" class="btn" onclick="imgEditor.cancel()">Annuleren</button>
            <button type="button" class="btn btn-primary" onclick="imgEditor.finish()">Klaar</button>
        </div>
    </div>
    <lib-loader id="ieLoader" overlay text="Even bezig&hellip;"></lib-loader>
</body>
</html>
