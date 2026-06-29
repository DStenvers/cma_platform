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
        <?php
        // Inline SVG icons for operations whose lnr glyph doesn't exist. The title=
        // tooltip explains each button; a dimmed icon = the "less" of an adjustment.
        $svgFlipH = '<svg class="ie-svg" viewBox="0 0 24 24" aria-hidden="true"><line x1="12" y1="3" x2="12" y2="21"/><polygon class="ie-svg-fill" points="9,7 4,12 9,17"/><polygon class="ie-svg-fill" points="15,7 20,12 15,17"/></svg>';
        $svgFlipV = '<svg class="ie-svg" viewBox="0 0 24 24" aria-hidden="true"><line x1="3" y1="12" x2="21" y2="12"/><polygon class="ie-svg-fill" points="7,9 12,4 17,9"/><polygon class="ie-svg-fill" points="7,15 12,20 17,15"/></svg>';
        $svgContrast = '<svg class="ie-svg" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path class="ie-svg-fill" d="M12 3a9 9 0 0 1 0 18z"/></svg>';
        $svgDrop = '<svg class="ie-svg" viewBox="0 0 24 24" aria-hidden="true"><path class="ie-svg-fill" d="M12 3c4 5 6 8 6 11a6 6 0 0 1-12 0c0-3 2-6 6-11z"/></svg>';
        $svgCompare = '<svg class="ie-svg" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="1"/><line x1="12" y1="3" x2="12" y2="21"/></svg>';
        $svgRgb = '<svg class="ie-svg" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="9" r="6"/><circle cx="15" cy="9" r="6"/><circle cx="12" cy="15" r="6"/></svg>';
        // lnr-sun's glyph is missing from the built icon font; use an inline SVG sun.
        $svgSun = '<svg class="ie-svg" viewBox="0 0 24 24" aria-hidden="true"><circle class="ie-svg-fill" cx="12" cy="12" r="4"/><line x1="12" y1="1" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="23"/><line x1="1" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="23" y2="12"/><line x1="4.2" y1="4.2" x2="6.3" y2="6.3"/><line x1="17.7" y1="17.7" x2="19.8" y2="19.8"/><line x1="4.2" y1="19.8" x2="6.3" y2="17.7"/><line x1="17.7" y1="6.3" x2="19.8" y2="4.2"/></svg>';
        ?>
        <cma-toolbar wrap variant="detail">
            <left>
                <span class="tb-btn responsive-btn" data-tooltip="Linksom draaien"><a href="javascript:imgEditor.rotate(-90)"><span class="lnr lnr-undo"></span><span class="btn-text">90&deg; L</span></a></span>
                <span class="tb-btn responsive-btn" data-tooltip="Rechtsom draaien"><a href="javascript:imgEditor.rotate(90)"><span class="lnr lnr-redo"></span><span class="btn-text">90&deg; R</span></a></span>
                <span class="tb-btn responsive-btn" data-tooltip="180&deg; draaien"><a href="javascript:imgEditor.rotate(180)"><span class="lnr lnr-sync"></span><span class="btn-text">180&deg;</span></a></span>
                <span class="tb-btn responsive-btn" data-tooltip="Horizontaal spiegelen"><a href="javascript:imgEditor.flip('h')"><?= $svgFlipH ?><span class="btn-text">Spiegel H</span></a></span>
                <span class="tb-btn responsive-btn" data-tooltip="Verticaal spiegelen"><a href="javascript:imgEditor.flip('v')"><?= $svgFlipV ?><span class="btn-text">Spiegel V</span></a></span>
                <span class="tb-btn responsive-btn" data-tooltip="Lichter"><a href="javascript:imgEditor.filter('brightness','+')"><?= $svgSun ?><span class="btn-text">Lichter</span></a></span>
                <span class="tb-btn responsive-btn" data-tooltip="Donkerder"><a href="javascript:imgEditor.filter('brightness','-')"><span class="ie-dim"><?= $svgSun ?></span><span class="btn-text">Donkerder</span></a></span>
                <span class="tb-btn responsive-btn" data-tooltip="Meer contrast"><a href="javascript:imgEditor.filter('contrast','+')"><?= $svgContrast ?><span class="btn-text">Contrast +</span></a></span>
                <span class="tb-btn responsive-btn" data-tooltip="Minder contrast"><a href="javascript:imgEditor.filter('contrast','-')"><span class="ie-dim"><?= $svgContrast ?></span><span class="btn-text">Contrast &minus;</span></a></span>
                <span class="tb-btn responsive-btn" data-tooltip="Meer verzadiging"><a href="javascript:imgEditor.filter('saturation','+')"><?= $svgDrop ?><span class="btn-text">Kleur +</span></a></span>
                <span class="tb-btn responsive-btn" data-tooltip="Minder verzadiging"><a href="javascript:imgEditor.filter('saturation','-')"><span class="ie-dim"><?= $svgDrop ?></span><span class="btn-text">Kleur &minus;</span></a></span>
                <span class="tb-btn responsive-btn" data-tooltip="Kleurbalans (R/G/B) met live voorbeeld"><a href="javascript:imgEditor.toggleRgb()"><?= $svgRgb ?><span class="btn-text">RGB</span></a></span>
                <span class="tb-btn responsive-btn" data-tooltip="Verscherpen"><a href="javascript:imgEditor.filter('sharpen','')"><span class="lnr lnr-magic-wand"></span><span class="btn-text">Scherp</span></a></span>
            </left>
            <right>
                <span class="tb-btn responsive-btn" data-tooltip="Bijsnijden"><a href="javascript:imgEditor.startCrop()"><span class="lnr lnr-crop"></span><span class="btn-text">Bijsnijden</span></a></span>
                <span class="tb-btn responsive-btn" data-tooltip="Witruimte automatisch bijsnijden"><a href="javascript:imgEditor.autocrop()"><span class="lnr lnr-frame-contract"></span><span class="btn-text">Autocrop</span></a></span>
                <label class="ie-margin" data-tooltip="Marge rondom de inhoud bij autocrop">marge <input type="number" id="autocropMargin" min="0" max="50" step="1" value="10">%</label>
                <span class="tb-btn responsive-btn ie-optional" id="ieCompareBtn" data-tooltip="Vergelijk met origineel"><a href="javascript:imgEditor.toggleCompare()"><?= $svgCompare ?><span class="btn-text">Vergelijk</span></a></span>
                <span class="tb-btn responsive-btn ie-optional" id="ieRestore" data-tooltip="Origineel terugzetten"><a href="javascript:imgEditor.restore()"><span class="lnr lnr-history"></span><span class="btn-text">Herstel</span></a></span>
            </right>
        </cma-toolbar>

        <div class="image-editor-canvas">
            <div class="preview-wrap" id="previewWrap">
                <img id="editorImage" alt="Afbeelding bewerken">
                <canvas id="ieRgbCanvas" class="ie-rgb-canvas"></canvas>
            </div>
            <!-- Colour-balance panel with live preview (shown via the R/G/B button). -->
            <div class="ie-rgb-panel" id="ieRgbPanel">
                <div class="ie-rgb-title">Kleurbalans &mdash; live voorbeeld</div>
                <div class="ie-rgb-wb"><button type="button" class="btn btn-secondary" id="ieRgbWb" onclick="imgEditor.whiteBalance()" title="Automatische witbalans (neutraliseert een kleurzweem)">Auto witbalans</button></div>
                <label class="ie-rgb-row"><span class="ie-rgb-lbl ie-rgb-lbl--r">R</span><input type="range" id="ieRgbR" min="-100" max="100" value="0" oninput="imgEditor.previewRgb()"><span class="ie-rgb-val" id="ieRgbRv">0</span></label>
                <label class="ie-rgb-row"><span class="ie-rgb-lbl ie-rgb-lbl--g">G</span><input type="range" id="ieRgbG" min="-100" max="100" value="0" oninput="imgEditor.previewRgb()"><span class="ie-rgb-val" id="ieRgbGv">0</span></label>
                <label class="ie-rgb-row"><span class="ie-rgb-lbl ie-rgb-lbl--b">B</span><input type="range" id="ieRgbB" min="-100" max="100" value="0" oninput="imgEditor.previewRgb()"><span class="ie-rgb-val" id="ieRgbBv">0</span></label>
                <div class="ie-rgb-actions">
                    <button type="button" class="btn btn-secondary" onclick="imgEditor.cancelRgb()">Annuleren</button>
                    <button type="button" class="btn btn-primary" onclick="imgEditor.applyRgb()">Toepassen</button>
                </div>
            </div>
            <!-- Before/after compare slider (shown only when an edit has been made). -->
            <div class="ie-compare" id="ieComparePane">
                <div class="ie-compare-bg" id="ieCompareBg"></div>
                <div class="ie-compare-fg" id="ieCompareFg"></div>
                <div class="ie-compare-split" id="ieCompareSplit"><span>&#8596;</span></div>
                <div class="ie-compare-tag ie-compare-tag--l">Origineel</div>
                <div class="ie-compare-tag ie-compare-tag--r">Bewerkt</div>
                <button type="button" class="btn btn-secondary ie-compare-close" onclick="imgEditor.toggleCompare()">Sluiten</button>
            </div>
        </div>

        <div class="image-editor-info">
            <span class="dimensions" id="ieDimensions">&ndash;</span>
            <span class="crop-info" id="ieRule"></span>
        </div>

        <div class="image-editor-footer">
            <button type="button" class="btn btn-secondary" onclick="imgEditor.cancel()">Annuleren</button>
            <button type="button" class="btn btn-primary" onclick="imgEditor.finish()">Klaar</button>
        </div>
    </div>
    <lib-loader id="ieLoader" overlay text="Even bezig&hellip;"></lib-loader>
</body>
</html>
