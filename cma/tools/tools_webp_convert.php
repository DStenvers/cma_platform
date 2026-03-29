<?php
/**
 * WebP Batch Conversion Tool
 *
 * Scans image directories, shows conversion status, and batch-generates
 * responsive WebP variants in .responsive/ subdirectories.
 *
 * API endpoints (action parameter):
 * - scan: Scan directory and return image list with variant status
 * - convert: Generate responsive variants for all images
 * - cleanup: Remove all .responsive/ directories
 */
use App\Library\Application;
use App\Library\Image;
use App\Library\Request;
use App\Library\Response;
use App\Library\ResponsiveImage;
use App\Library\Server;
use Cma\SecurityHelper;
use Cma\ToolbarHelper;

require_once __DIR__ . '/../bootstrap.inc';

if (!SecurityHelper::isDeveloper()) {
    echo '<lib-message type="error">Alleen voor developers</lib-message>';
    exit;
}

Response::noCache();

// Handle API requests
$action = Request::query('action', '') ?: Request::post('action', '');
if ($action !== '') {
    // Discard any buffered output from bootstrap/profiler/debug (e.g. <script> tags)
    // to ensure clean JSON response
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    $directory = Request::post('directory', Request::query('directory', '/images/'));
    $directory = '/' . trim($directory, '/') . '/';
    $fullPath = Server::mapPath($directory);

    // Collect GD diagnostics for all responses
    $gdLoaded = extension_loaded('gd');
    $gdInfo = $gdLoaded ? gd_info() : [];
    $gdDiag = [
        'gdLoaded' => $gdLoaded,
        'gdVersion' => $gdInfo['GD Version'] ?? null,
        'webpSupport' => !empty($gdInfo['WebP Support']),
        'jpegSupport' => !empty($gdInfo['JPEG Support']),
        'pngSupport' => !empty($gdInfo['PNG Support']),
        'phpVersion' => PHP_VERSION,
        'phpSapi' => PHP_SAPI,
        'os' => PHP_OS,
        'exifSupport' => function_exists('exif_read_data'),
        'cwebpAvailable' => Image::isCwebpAvailable(),
        'cwebpPath' => Image::findCwebp() ?: null,
    ];
    if (!$gdLoaded) {
        $gdDiag['iniPath'] = php_ini_loaded_file();
        $gdDiag['iniScanned'] = php_ini_scanned_files();
    }

    switch ($action) {
        case 'scan':
            if (!is_dir($fullPath)) {
                echo json_encode(['success' => false, 'error' => 'Map bestaat niet: ' . $directory, 'gd' => $gdDiag]);
                break;
            }
            $files = ResponsiveImage::scan($fullPath);
            // Make paths relative for display and add URL paths for variants
            foreach ($files as &$f) {
                $f['relativePath'] = str_replace($fullPath, '', $f['path']);
                // Build URL for original image
                $f['url'] = preg_replace('#(?<!:)//#', '/', $directory . str_replace(DIRECTORY_SEPARATOR, '/', $f['relativePath']));
                // Build URLs for variants
                if (!empty($f['variants'])) {
                    $relDir = dirname($f['relativePath']);
                    $responsiveUrlDir = preg_replace('#(?<!:)//#', '/', $directory . ($relDir && $relDir !== '.' ? str_replace(DIRECTORY_SEPARATOR, '/', $relDir) . '/' : '') . '.responsive/');
                    foreach ($f['variants'] as &$v) {
                        $v['url'] = $responsiveUrlDir . $v['file'];
                    }
                    unset($v);
                }
            }
            unset($f);
            $total = count($files);
            $withVariants = count(array_filter($files, fn($f) => $f['hasVariants']));
            echo json_encode([
                'success' => true,
                'directory' => $directory,
                'total' => $total,
                'withVariants' => $withVariants,
                'withoutVariants' => $total - $withVariants,
                'files' => $files,
                'webpSupported' => Image::isWebPSupported(),
                'gd' => $gdDiag,
            ]);
            break;

        case 'convertOne':
            // Convert a single file and return variant details for row update
            set_time_limit(120);
            ini_set('memory_limit', '512M');
            if (!Image::isWebPSupported()) {
                echo json_encode(['success' => false, 'error' => 'WebP niet ondersteund door GD']);
                break;
            }
            $file = Request::query('file', '');
            if ($file === '') {
                echo json_encode(['success' => false, 'error' => 'Geen bestand opgegeven']);
                break;
            }
            $file = str_replace('..', '', $file);
            $singlePath = $fullPath . str_replace('/', DIRECTORY_SEPARATOR, $file);
            if (!file_exists($singlePath)) {
                echo json_encode(['success' => false, 'error' => 'Bestand niet gevonden: ' . $file]);
                break;
            }
            $quality = (int)Request::query('quality', (string)ResponsiveImage::DEFAULT_QUALITY);
            if ($quality < 1 || $quality > 100) {
                $quality = ResponsiveImage::DEFAULT_QUALITY;
            }
            // Delete existing variants when regenerating
            if (Request::query('regenerate', '0') === '1' && ResponsiveImage::hasVariants($singlePath)) {
                ResponsiveImage::deleteVariants($singlePath);
            }
            $genResult = ResponsiveImage::generate($singlePath, $quality);
            if (empty($genResult['success'])) {
                echo json_encode(['success' => false, 'error' => $genResult['error'] ?? 'Conversie mislukt']);
                break;
            }
            // Return updated file info with variants for row update
            $info = Image::getInfo($singlePath);
            $responsiveDir = ResponsiveImage::getResponsiveDir($singlePath);
            $baseName = pathinfo($singlePath, PATHINFO_FILENAME);
            $relDir = dirname($file);
            $responsiveUrlDir = preg_replace('#(?<!:)//#', '/', $directory . ($relDir && $relDir !== '.' ? $relDir . '/' : '') . '.responsive/');
            $variants = [];
            foreach (ResponsiveImage::SIZES as $w) {
                $vPath = $responsiveDir . DIRECTORY_SEPARATOR . $baseName . '-' . $w . 'w.webp';
                if (file_exists($vPath)) {
                    $variants[] = [
                        'width' => $w,
                        'file' => $baseName . '-' . $w . 'w.webp',
                        'size' => filesize($vPath),
                        'url' => $responsiveUrlDir . $baseName . '-' . $w . 'w.webp',
                    ];
                }
            }
            $webpPath = $responsiveDir . DIRECTORY_SEPARATOR . $baseName . '.webp';
            $webpSize = null;
            if (file_exists($webpPath)) {
                $webpSize = filesize($webpPath);
                $variants[] = [
                    'width' => $info !== false ? $info['width'] : 0,
                    'file' => $baseName . '.webp',
                    'size' => $webpSize,
                    'url' => $responsiveUrlDir . $baseName . '.webp',
                    'full' => true,
                ];
            }
            echo json_encode([
                'success' => true,
                'variants' => $variants,
                'webpSize' => $webpSize,
            ]);
            break;

        case 'convert':
            // Convert a batch of files (up to 100 per request for progress tracking)
            set_time_limit(600);
            ini_set('memory_limit', '512M');
            if (!Image::isWebPSupported()) {
                echo json_encode(['success' => false, 'error' => 'WebP niet ondersteund door GD']);
                break;
            }
            if (!is_dir($fullPath)) {
                echo json_encode(['success' => false, 'error' => 'Map bestaat niet: ' . $directory]);
                break;
            }
            $quality = (int)Request::post('quality', Request::query('quality', (string)ResponsiveImage::DEFAULT_QUALITY));
            if ($quality < 1 || $quality > 100) {
                $quality = ResponsiveImage::DEFAULT_QUALITY;
            }
            $offset = max(0, (int)Request::query('offset', '0'));
            $limit = min(20, max(1, (int)Request::query('limit', '20')));
            $regenerate = Request::query('regenerate', '0') === '1';

            // Scan all files; filter to unconverted unless regenerating
            $allFiles = ResponsiveImage::scan($fullPath);
            $toConvert = $regenerate
                ? array_values($allFiles)
                : array_values(array_filter($allFiles, fn($f) => !$f['hasVariants']));
            $totalRemaining = count($toConvert);

            // Take the batch from offset
            $batch = array_slice($toConvert, $offset, $limit);
            $converted = 0;
            $skipped = 0;
            $errors = 0;
            $results = [];

            foreach ($batch as $f) {
                $filePath = $f['path'];
                if (!$regenerate && ResponsiveImage::hasVariants($filePath)) {
                    $skipped++;
                    continue;
                }
                // Delete existing variants before regenerating
                if ($regenerate && ResponsiveImage::hasVariants($filePath)) {
                    ResponsiveImage::deleteVariants($filePath);
                }
                try {
                    @error_clear_last();
                    $result = ResponsiveImage::generate($filePath, $quality);
                    if (!empty($result['success'])) {
                        $converted++;
                    } else {
                        $errors++;
                        $results[] = ['file' => basename($filePath), 'error' => $result['error'] ?? 'Onbekende fout'];
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $results[] = ['file' => basename($filePath), 'error' => $e->getMessage()];
                }
                // Free memory after each image to prevent OOM on large batches
                gc_collect_cycles();
            }

            echo json_encode([
                'success' => true,
                'converted' => $converted,
                'skipped' => $skipped,
                'errors' => $errors,
                'batchSize' => count($batch),
                'totalRemaining' => $totalRemaining,
                'offset' => $offset,
                'errorDetails' => $results,
            ]);
            break;

        case 'cleanup':
            if (!is_dir($fullPath)) {
                echo json_encode(['success' => false, 'error' => 'Map bestaat niet: ' . $directory]);
                break;
            }
            $result = ResponsiveImage::cleanup($fullPath);
            echo json_encode(array_merge(['success' => true], $result));
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Ongeldige actie']);
    }
    exit;
}

// HTML page
cma_html_header('WebP conversie');
echo '<body class="contentbody tools tool-webp-convert" style="margin: 0;">';
ToolbarHelper::report('WebP conversie', false, false, false, false, 'Converteer afbeeldingen naar WebP met responsieve varianten', '', 'helpDialog');
?>
<div id="c" class="tools" style="padding: 24px;">

<div style="margin-bottom: 20px;">
    <label for="directory" style="font-weight: bold; display: block; margin-bottom: 4px;">Map om te scannen:</label>
    <div style="display: flex; gap: 0; align-items: stretch;">
        <input type="text" id="directory" value="/images/" class="form-control" style="max-width: 400px; height: 32px; box-sizing: border-box; border-radius: 3px 0 0 3px;">
        <button class="btn btn-primary" onclick="scanDirectory()" style="height: 32px; box-sizing: border-box; border-radius: 0 3px 3px 0;">
            <span class="lnr lnr-magnifier"></span> Scannen
        </button>
    </div>
</div>

<div id="webp-status" style="margin-bottom: 16px;"></div>

<div id="action-buttons" style="display: none; margin-bottom: 16px; gap: 8px;">
    <button class="btn btn-primary" onclick="convertAll()" id="btn-convert">
        <span class="lnr lnr-picture"></span> Alles converteren
    </button>
    <label style="margin-left: 16px;">
        Kwaliteit: <input type="number" id="quality" value="85" min="1" max="100" style="width: 60px;" class="form-control">
    </label>
    <label style="margin-left: 16px; cursor: pointer;">
        <input type="checkbox" id="regenerate"> Opnieuw aanmaken
    </label>
</div>

<div id="progress" style="display: none; margin-bottom: 16px;">
    <div id="progress-text" style="margin-bottom: 4px; font-size: 0.85em; color: var(--text-muted);"></div>
    <div style="background: var(--gauge-track-bg, #eee); border-radius: 3px; overflow: hidden; height: 6px;">
        <div id="progress-bar" style="background: var(--color-primary, #4a90d9); height: 100%; width: 0%; transition: width 0.3s; border-radius: 3px;"></div>
    </div>
</div>

<div id="results-table"></div>

<lib-dialog id="helpDialog" heading="WebP conversie — Help" size="large">
    <div style="font-size: 0.95em; line-height: 1.6;">
        <h3 style="margin-top: 0;">Wat doet deze tool?</h3>
        <p>Scant afbeeldingsmappen en genereert <strong>responsieve WebP-varianten</strong> in een <code>.responsive/</code> submap.
        WebP is een modern beeldformaat dat kleinere bestanden oplevert bij gelijke kwaliteit.</p>

        <h3>Gedeelde configuratie</h3>
        <table class="table" style="width: 100%; margin-bottom: 16px;">
            <thead>
                <tr>
                    <th style="text-align: left; padding: 6px 8px;">Instelling</th>
                    <th style="text-align: left; padding: 6px 8px;">Waarde</th>
                    <th style="text-align: left; padding: 6px 8px;">Bron</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding: 6px 8px;">Kwaliteit</td>
                    <td style="padding: 6px 8px;"><strong><?= ResponsiveImage::DEFAULT_QUALITY ?></strong></td>
                    <td style="padding: 6px 8px;"><code>ResponsiveImage::DEFAULT_QUALITY</code></td>
                </tr>
                <tr>
                    <td style="padding: 6px 8px;">Responsive breedtes</td>
                    <td style="padding: 6px 8px;"><strong><?= implode(', ', ResponsiveImage::SIZES) ?></strong> px</td>
                    <td style="padding: 6px 8px;"><code>ResponsiveImage::SIZES</code></td>
                </tr>
                <tr>
                    <td style="padding: 6px 8px;">Submap</td>
                    <td style="padding: 6px 8px;"><strong><?= ResponsiveImage::RESPONSIVE_DIR ?></strong></td>
                    <td style="padding: 6px 8px;"><code>ResponsiveImage::RESPONSIVE_DIR</code></td>
                </tr>
            </tbody>
        </table>

        <h3>Bestandsstructuur</h3>
        <pre style="background: var(--bg-surface, #f5f5f5); padding: 12px; border-radius: 4px; font-size: 0.85em; overflow-x: auto;">/images/photo.jpg                        &larr; origineel (blijft behouden)
/images/.responsive/photo-400w.webp      &larr; 400px breed
/images/.responsive/photo-800w.webp      &larr; 800px breed
/images/.responsive/photo-1200w.webp     &larr; 1200px breed
/images/.responsive/photo.webp           &larr; volledige grootte WebP</pre>

        <h3>Vergelijken</h3>
        <p>Klik op een bestandsnaam of variant-thumbnail om het beeld te openen in een preview.
        Bij afbeeldingen met een volledige WebP-variant verschijnt een <strong>Vergelijk</strong>-tab
        waarmee je het origineel en de WebP-versie naast elkaar kunt vergelijken met een versleepbare scheidslijn.</p>

        <h3>Wanneer worden varianten automatisch aangemaakt?</h3>
        <ul style="margin: 0; padding-left: 20px;">
            <li><strong>Formulier upload</strong> — bij het uploaden van afbeeldingen via CMA formulieren</li>
            <li><strong>Beeld bijsnijden</strong> — na het bijsnijden in de image crop wizard</li>
            <li><strong>Bestandsbeheer</strong> — bij bewerken (draaien/bijsnijden/formaat wijzigen) in de bestandsbrowser</li>
            <li><strong>Batch conversie</strong> — via deze tool voor bestaande afbeeldingen</li>
        </ul>

        <h3>Implementatie in de front-end</h3>
        <p>Gebruik <code>ResponsiveImage::imgTag()</code> om automatisch een <code>&lt;img&gt;</code> met <code>srcset</code> te genereren:</p>
        <pre style="background: var(--bg-surface, #f5f5f5); padding: 12px; border-radius: 4px; font-size: 0.85em; overflow-x: auto;">// Simpel — genereert srcset met alle beschikbare varianten:
echo ResponsiveImage::imgTag('/images/photo.jpg', 'Beschrijving');

// Met sizing hints (voor layout):
echo ResponsiveImage::imgTag('/images/photo.jpg', 'Alt', '(max-width: 600px) 100vw, 50vw');

// Met CSS class en extra attributen:
echo ResponsiveImage::imgTag('/images/photo.jpg', 'Alt', '100vw', 'hero-image', [
    'width' => 1200, 'height' => 800, 'fetchpriority' => 'high',
]);</pre>

        <p style="margin-top: 12px;">In <strong>JavaScript</strong> (dynamische content):</p>
        <pre style="background: var(--bg-surface, #f5f5f5); padding: 12px; border-radius: 4px; font-size: 0.85em; overflow-x: auto;">const dir = '/images';
const name = 'photo';  // zonder extensie
const srcset = [<?= implode(', ', ResponsiveImage::SIZES) ?>]
    .map(w => `${dir}/.responsive/${name}-${w}w.webp ${w}w`)
    .join(', ');</pre>

        <p style="margin-top: 12px;">Als <strong>CSS achtergrond</strong>:</p>
        <pre style="background: var(--bg-surface, #f5f5f5); padding: 12px; border-radius: 4px; font-size: 0.85em; overflow-x: auto;">.hero { background-image: url('/images/.responsive/photo.webp'); }</pre>
    </div>
    <div slot="footer">
        <button class="btn" onclick="document.getElementById('helpDialog').close()">Sluiten</button>
    </div>
</lib-dialog>

</div>

<script>
(function() {
    'use strict';

    var currentFiles = [];
    var platformInfo = null;

    /**
     * Get platform-specific restart instructions based on GD diagnostics.
     * @param {object} gd - GD diagnostics from scan response
     * @returns {object} { isWindows, sapi, restartCmd, restartLabel, iniInstruction }
     */
    function getPlatformInfo(gd) {
        if (!gd) return { isWindows: false, sapi: '', restartCmd: 'herstart de webserver', restartLabel: 'webserver', iniInstruction: 'php.ini' };
        var isWindows = gd.os && gd.os.indexOf('WIN') === 0;
        var sapi = (gd.phpSapi || '').toLowerCase();
        var restartCmd, restartLabel;

        if (isWindows) {
            if (sapi.indexOf('cgi') !== -1 || sapi.indexOf('isapi') !== -1 || sapi === 'srv') {
                restartCmd = '<code>iisreset</code>';
                restartLabel = 'IIS';
            } else if (sapi.indexOf('apache') !== -1) {
                restartCmd = '<code>httpd -k restart</code>';
                restartLabel = 'Apache';
            } else {
                restartCmd = '<code>iisreset</code>';
                restartLabel = 'de webserver';
            }
        } else {
            if (sapi.indexOf('apache') !== -1) {
                restartCmd = '<code>sudo systemctl restart apache2</code>';
                restartLabel = 'Apache';
            } else if (sapi.indexOf('fpm') !== -1) {
                var phpVer = gd.phpVersion ? gd.phpVersion.substring(0, 3) : '';
                restartCmd = '<code>sudo systemctl restart php' + phpVer + '-fpm</code>';
                restartLabel = 'PHP-FPM';
            } else if (sapi.indexOf('nginx') !== -1 || sapi.indexOf('litespeed') !== -1) {
                restartCmd = '<code>sudo systemctl restart nginx</code>';
                restartLabel = 'Nginx';
            } else {
                restartCmd = '<code>sudo systemctl restart apache2</code> of de betreffende webserver';
                restartLabel = 'de webserver';
            }
        }

        return {
            isWindows: isWindows,
            sapi: sapi,
            restartCmd: restartCmd,
            restartLabel: restartLabel,
            iniInstruction: gd.iniPath ? '<code>' + gd.iniPath + '</code>' : '<code>php.ini</code>'
        };
    }

    function formatSize(bytes) {
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
        if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return bytes + ' B';
    }

    function showStatus(html) {
        document.getElementById('webp-status').innerHTML = html;
    }

    window.scanDirectory = function() {
        var dir = document.getElementById('directory').value.trim();
        if (!dir) return;

        showStatus('<lib-message type="info">Scannen...</lib-message>');
        document.getElementById('results-table').innerHTML = '';
        document.getElementById('action-buttons').style.display = 'none';

        fetch('tools_webp_convert.php?action=scan&directory=' + encodeURIComponent(dir))
            .then(function(r) {
                if (!r.ok || !r.headers.get('content-type')?.includes('json')) {
                    return r.text().then(function(txt) {
                        throw new Error('Server fout (HTTP ' + r.status + '): ' + txt.substring(0, 200));
                    });
                }
                return r.json();
            })
            .then(function(data) {
                if (!data.success) {
                    showStatus('<lib-message type="error">' + data.error + '</lib-message>');
                    return;
                }

                currentFiles = data.files;
                var gd = data.gd || {};
                platformInfo = getPlatformInfo(gd);
                var webpNote = '';

                if (data.webpSupported) {
                    webpNote = '<span style="color: green;">WebP ondersteund</span>' +
                        ' (GD ' + (gd.gdVersion || '?') + ')';
                } else {
                    webpNote = '<span style="color: red;">WebP NIET ondersteund</span>';
                }

                var dot = function(ok) { return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' + (ok ? '#27ae60' : '#e74c3c') + ';margin-right:6px;vertical-align:middle;"></span>'; };

                var statCard = function(label, value, color) {
                    return '<div style="background:var(--bg-surface,#f5f5f5);border:1px solid var(--border-color,#ddd);border-radius:4px;padding:8px 16px;min-width:100px;text-align:center;">' +
                        '<div style="font-size:1.5em;font-weight:bold;color:' + (color || 'var(--text-color,#333)') + ';line-height:1.2;">' + value + '</div>' +
                        '<div style="font-size:0.8em;color:var(--text-muted,#888);margin-top:2px;">' + label + '</div>' +
                    '</div>';
                };

                var statusHtml =
                    '<div style="display:flex;justify-content:space-between;align-items:start;gap:16px;margin-bottom:12px;flex-wrap:wrap;">' +
                    '<div style="display:flex;gap:10px;flex-wrap:wrap;">' +
                        statCard('Totaal', data.total, 'var(--color-primary,#4a90d9)') +
                        statCard('Met varianten', data.withVariants, '#27ae60') +
                        statCard('Zonder varianten', data.withoutVariants, data.withoutVariants > 0 ? '#f39c12' : '#27ae60') +
                    '</div>' +
                    '<div style="display:flex;gap:4px 12px;flex-wrap:wrap;font-size:0.85em;background:var(--bg-surface,#f5f5f5);border:1px solid var(--border-color,#ddd);border-radius:4px;padding:6px 12px;align-self:center;">' +
                        '<span>' + dot(data.webpSupported) + 'WebP</span>' +
                        '<span>' + dot(gd.exifSupport) + 'EXIF</span>' +
                        '<span>' + dot(gd.cwebpAvailable) + 'cwebp</span>' +
                    '</div>' +
                    '</div>' +
                    (!gd.exifSupport ? '<div style="margin-bottom:8px;"><lib-message type="warning">EXIF extensie niet geladen &mdash; JPEG ori&euml;ntatie wordt niet gecorrigeerd bij conversie. Activeer in ' + platformInfo.iniInstruction + ': verwijder de <code>;</code> voor <code>extension=exif</code> (moet na <code>mbstring</code> staan) en herstart ' + platformInfo.restartLabel + ': ' + platformInfo.restartCmd + '</lib-message></div>' : '') +
                    (!gd.cwebpAvailable ? '<div style="margin-bottom:8px;"><lib-message type="warning">cwebp niet gevonden &mdash; conversie gebruikt GD (kleuren kunnen afwijken door ontbrekende ICC-profielondersteuning). Installeer cwebp voor nauwkeurige kleuren: ' + (platformInfo.isWindows ? 'download <code>libwebp</code> van <a href="https://developers.google.com/speed/webp/docs/precompiled" target="_blank">Google WebP</a> en plaats <code>cwebp.exe</code> in de PHP-map' : '<code>sudo apt install webp</code>') + '</lib-message></div>' : '');

                // Show detailed diagnostics when WebP is not supported
                if (!data.webpSupported && gd) {
                    statusHtml += '<div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 4px; padding: 16px; margin-bottom: 12px;">';
                    statusHtml += '<strong style="color: var(--color-danger, #c00);">WebP is niet beschikbaar</strong>';
                    statusHtml += '<table style="margin-top: 8px; font-size: 0.9em;">';
                    statusHtml += '<tr><td style="padding: 2px 12px 2px 0;">PHP versie:</td><td><strong>' + (gd.phpVersion || '?') + '</strong></td></tr>';
                    statusHtml += '<tr><td style="padding: 2px 12px 2px 0;">PHP SAPI:</td><td><strong>' + (gd.phpSapi || '?') + '</strong></td></tr>';
                    statusHtml += '<tr><td style="padding: 2px 12px 2px 0;">OS:</td><td><strong>' + (gd.os || '?') + '</strong></td></tr>';
                    statusHtml += '<tr><td style="padding: 2px 12px 2px 0;">GD extensie geladen:</td><td><strong style="color:' + (gd.gdLoaded ? 'green' : 'red') + ';">' + (gd.gdLoaded ? 'Ja' : 'Nee') + '</strong></td></tr>';
                    if (gd.gdLoaded) {
                        statusHtml += '<tr><td style="padding: 2px 12px 2px 0;">GD versie:</td><td><strong>' + (gd.gdVersion || '?') + '</strong></td></tr>';
                        statusHtml += '<tr><td style="padding: 2px 12px 2px 0;">JPEG:</td><td>' + (gd.jpegSupport ? '<span style="color:green;">Ja</span>' : '<span style="color:red;">Nee</span>') + '</td></tr>';
                        statusHtml += '<tr><td style="padding: 2px 12px 2px 0;">PNG:</td><td>' + (gd.pngSupport ? '<span style="color:green;">Ja</span>' : '<span style="color:red;">Nee</span>') + '</td></tr>';
                        statusHtml += '<tr><td style="padding: 2px 12px 2px 0;">WebP:</td><td>' + (gd.webpSupport ? '<span style="color:green;">Ja</span>' : '<span style="color:red;">Nee</span>') + '</td></tr>';
                    }
                    if (gd.iniPath) {
                        statusHtml += '<tr><td style="padding: 2px 12px 2px 0;">php.ini:</td><td><code style="font-size: 0.85em;">' + gd.iniPath + '</code></td></tr>';
                    }
                    statusHtml += '</table>';

                    // Show fix instructions based on diagnosis
                    statusHtml += '<div style="margin-top: 12px; padding: 10px; background: var(--bg-body); border-radius: 4px;">';
                    statusHtml += '<strong>Oplossing:</strong><br>';
                    if (!gd.gdLoaded) {
                        statusHtml += '<ol style="margin: 6px 0 0 20px; padding: 0;">';
                        if (platformInfo.isWindows) {
                            statusHtml += '<li>Open ' + platformInfo.iniInstruction + '</li>';
                            statusHtml += '<li>Zoek de regel <code>;extension=gd</code></li>';
                            statusHtml += '<li>Verwijder de <code>;</code> aan het begin (uncommenten)</li>';
                        } else {
                            var phpVer = gd.phpVersion ? gd.phpVersion.substring(0, 3) : '';
                            statusHtml += '<li>Installeer de GD extensie: <code>sudo apt install php' + phpVer + '-gd</code></li>';
                        }
                        statusHtml += '<li>Herstart ' + platformInfo.restartLabel + ': ' + platformInfo.restartCmd + '</li>';
                        statusHtml += '</ol>';
                    } else if (!gd.webpSupport) {
                        statusHtml += '<ol style="margin: 6px 0 0 20px; padding: 0;">';
                        if (platformInfo.isWindows) {
                            statusHtml += '<li>PHP ' + (gd.phpVersion || '') + ' op Windows heeft WebP ingebouwd in de GD extensie</li>';
                            statusHtml += '<li>Controleer of <code>libwebp.dll</code> aanwezig is in de PHP directory</li>';
                            statusHtml += '<li>Gebruik PHP 8.1+ voor betrouwbare WebP ondersteuning</li>';
                            statusHtml += '<li>Download eventueel een nieuwere PHP build van <code>windows.php.net</code></li>';
                        } else {
                            var phpVer = gd.phpVersion ? gd.phpVersion.substring(0, 3) : '';
                            statusHtml += '<li>Installeer WebP bibliotheek: <code>sudo apt install libwebp-dev</code></li>';
                            statusHtml += '<li>Herinstalleer GD: <code>sudo apt install --reinstall php' + phpVer + '-gd</code></li>';
                        }
                        statusHtml += '<li>Herstart ' + platformInfo.restartLabel + ': ' + platformInfo.restartCmd + '</li>';
                        statusHtml += '</ol>';
                    }
                    statusHtml += '</div>';
                    statusHtml += '</div>';
                }

                showStatus(statusHtml);

                if (data.total > 0 && data.webpSupported) {
                    document.getElementById('action-buttons').style.display = 'flex';
                }

                renderTable(data.files);
            })
            .catch(function(err) {
                showStatus('<lib-message type="error">Fout: ' + err.message + '</lib-message>');
            });
    };

    function showImagePreview(fileData) {
        // Build tabs: original + each variant
        var tabs = [];
        var panels = [];
        var fileName = fileData.relativePath || fileData.name;
        var fullVariant = null;

        var isDark = document.documentElement.classList.contains('dark-mode');
        var originalBg = isDark ? '#2a2a2a' : 'rgb(220,218,218)';

        // Original tab
        tabs.push('Origineel (' + fileData.ext.toUpperCase() + ')');
        panels.push(
            '<div style="text-align:center;padding:16px;height:100%;display:flex;align-items:center;justify-content:center;background:' + originalBg + ';">' +
            '<img src="' + fileData.url + '" alt="Origineel" style="max-width:95%;max-height:95%;object-fit:contain;">' +
            '</div>'
        );

        // Variant tabs
        if (fileData.variants && fileData.variants.length > 0) {
            for (var i = 0; i < fileData.variants.length; i++) {
                var v = fileData.variants[i];
                if (v.full) fullVariant = v;
                var tabLabel = v.full ? 'WebP volledig' : 'WebP ' + v.width + 'w';
                tabLabel += ' (' + formatSize(v.size) + ')';
                tabs.push(tabLabel);
                panels.push(
                    '<div style="text-align:center;padding:16px;height:100%;display:flex;align-items:center;justify-content:center;">' +
                    '<img src="' + v.url + '" alt="' + tabLabel + '" style="max-width:95%;max-height:95%;object-fit:contain;">' +
                    '</div>'
                );
            }
        }

        // Comparison tab (only if full-size WebP variant exists)
        var compareId = 'img-compare-' + Date.now();
        if (fullVariant) {
            tabs.push('Vergelijk');
            var bgCss = 'background-image:url(' + fullVariant.url + ');background-size:contain;background-position:center;background-repeat:no-repeat;background-origin:content-box;padding:2.5%;box-sizing:border-box;';
            var fgCss = 'background-image:url(' + fileData.url + ');background-size:contain;background-position:center;background-repeat:no-repeat;background-origin:content-box;padding:2.5%;box-sizing:border-box;';
            panels.push(
                '<div id="' + compareId + '" style="position:relative;height:100%;overflow:hidden;cursor:col-resize;user-select:none;">' +
                    // WebP (background, full container, visible on the right)
                    '<div class="compare-bg" style="position:absolute;top:0;left:0;width:100%;height:100%;' + bgCss + '"></div>' +
                    // Original (foreground, clipped from right, visible on the left)
                    '<div class="compare-fg" style="position:absolute;top:0;left:0;width:100%;height:100%;background-color:' + originalBg + ';clip-path:inset(0 50% 0 0);' + fgCss + '"></div>' +
                    // Splitter line
                    '<div class="compare-splitter" style="position:absolute;top:0;left:50%;width:3px;height:100%;background:white;box-shadow:0 0 4px rgba(0,0,0,0.5);z-index:2;transform:translateX(-50%);">' +
                        '<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:32px;height:32px;background:white;border-radius:50%;box-shadow:0 0 6px rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center;">' +
                            '<span style="font-size:var(--font-size-3xl);color:#666;margin-top:-3px;">&#8596;</span>' +
                        '</div>' +
                    '</div>' +
                    // Labels
                    '<div style="position:absolute;top:12px;left:12px;background:rgba(0,0,0,0.6);color:white;padding:4px 10px;border-radius:4px;font-size:0.8em;z-index:3;">' +
                        'Origineel (' + fileData.ext.toUpperCase() + ') &mdash; ' + formatSize(fileData.size) +
                    '</div>' +
                    '<div style="position:absolute;top:12px;right:12px;background:rgba(0,0,0,0.6);color:white;padding:4px 10px;border-radius:4px;font-size:0.8em;z-index:3;">' +
                        'WebP &mdash; ' + formatSize(fullVariant.size) +
                    '</div>' +
                '</div>'
            );
        }

        // Build HTML with cma-tabs and fixed footer
        var footerHeight = '36px';
        var html = '<div style="position:relative;height:100%;overflow:hidden;">';
        html += '<cma-tabs tabs=\'' + JSON.stringify(tabs) + '\' selected="0" style="height:calc(100% - ' + footerHeight + ');">';
        for (var p = 0; p < panels.length; p++) {
            html += '<div slot="tab-' + p + '" style="height:100%;overflow:auto;">' + panels[p] + '</div>';
        }
        html += '</cma-tabs>';
        html += '<div style="position:absolute;bottom:0;left:0;right:0;height:' + footerHeight + ';padding:8px 16px;background:var(--tab-bar-bg,#dee1e6);border-top:1px solid var(--border-color,#ddd);font-size:0.85em;color:var(--text-muted,#666);display:grid;grid-template-columns:1fr auto 1fr;align-items:center;box-sizing:border-box;">';
        var dims = (fileData.width && fileData.height) ? fileData.width + ' &times; ' + fileData.height : '';
        var webpLarger = fileData.webpSize && fileData.webpSize > fileData.size;
        html += '<span>' + fileName + (dims ? ' &mdash; ' + dims : '') + ' &mdash; ' + formatSize(fileData.size) + '</span>';
        html += '<span style="width:200px;">' + renderWebpGauge(fileData.size, fileData.webpSize, 200) + '</span>';
        html += '<span style="text-align:right;">' + (fileData.webpSize ? 'WebP: ' + formatSize(fileData.webpSize) + (webpLarger ? ' <span style="color:var(--color-danger,#c00);">&#9888; origineel kleiner</span>' : '') : '') + '</span>';
        html += '</div>';
        html += '</div>';

        // Open in top window, maximized
        var topWin = window.top || window.parent || window;
        if (typeof topWin.lib_OpenWindowCentered === 'function') {
            var w = (topWin.innerWidth || 800) - 40;
            var h = (topWin.innerHeight || 600) - 40;
            topWin.lib_OpenWindowCentered('', 'image_preview', w, h, fileName, html);
            // Auto-maximize
            setTimeout(function() {
                if (typeof topWin.lib_OpenWindowCenteredMax === 'function') {
                    topWin.lib_OpenWindowCenteredMax();
                }
                // Initialize comparison splitter if present
                if (fullVariant) {
                    initCompareSplitter(topWin.document, compareId);
                }
            }, 100);
        }
    }

    function initCompareSplitter(doc, containerId) {
        var container = doc.getElementById(containerId);
        if (!container) return;

        var fgDiv = container.querySelector('.compare-fg');
        var splitter = container.querySelector('.compare-splitter');
        if (!fgDiv || !splitter) return;

        function setPosition(pct) {
            pct = Math.max(0, Math.min(100, pct));
            // clip-path clips the WebP from the right: inset(top right bottom left)
            fgDiv.style.clipPath = 'inset(0 ' + (100 - pct) + '% 0 0)';
            splitter.style.left = pct + '%';
        }

        // Set initial 50%
        setPosition(50);

        var dragging = false;

        function getPercent(e) {
            var rect = container.getBoundingClientRect();
            var clientX = e.touches ? e.touches[0].clientX : e.clientX;
            return ((clientX - rect.left) / rect.width) * 100;
        }

        function onStart(e) {
            e.preventDefault();
            dragging = true;
        }

        function onMove(e) {
            if (!dragging) return;
            e.preventDefault();
            setPosition(getPercent(e));
        }

        function onEnd() {
            dragging = false;
        }

        // Mouse events on the splitter handle
        splitter.addEventListener('mousedown', onStart);
        doc.addEventListener('mousemove', onMove);
        doc.addEventListener('mouseup', onEnd);

        // Touch events
        splitter.addEventListener('touchstart', onStart, { passive: false });
        doc.addEventListener('touchmove', onMove, { passive: false });
        doc.addEventListener('touchend', onEnd);

        // Also allow clicking anywhere in the container to move the splitter
        container.addEventListener('mousedown', function(e) {
            if (e.target === splitter || splitter.contains(e.target)) return;
            dragging = true;
            setPosition(getPercent(e));
        });
    }

    var VARIANT_SIZES = [400, 800, 1200];

    function findVariant(f, width, isFull) {
        if (!f.variants) return null;
        for (var i = 0; i < f.variants.length; i++) {
            if (isFull && f.variants[i].full) return f.variants[i];
            if (!isFull && !f.variants[i].full && f.variants[i].width === width) return f.variants[i];
        }
        return null;
    }

    function renderVariantCell(f, rowIndex, width, isFull) {
        var v = findVariant(f, width, isFull);
        if (!v) return '<span style="color:var(--text-muted);">-</span>';
        var isLarger = v.size > f.size;
        var html = '<div class="variant-thumb" data-row="' + rowIndex + '" style="display:flex;align-items:center;gap:4px;cursor:pointer;font-size:0.85em;">';
        html += '<img src="' + v.url + '" alt="" style="max-height:24px;max-width:40px;object-fit:contain;border-radius:2px;border:1px solid var(--border-color);">';
        html += '<lib-gauge value="' + v.size + '" max="' + f.size + '" format="size" min-width="70"></lib-gauge>';
        if (isLarger) {
            html += '<span title="WebP is groter dan origineel" style="color:var(--color-danger,#c00);">&#9888;</span>';
        }
        html += '</div>';
        return html;
    }

    function renderWebpGauge(originalSize, webpSize, minWidth) {
        if (!webpSize) return '-';
        return '<lib-gauge value="' + webpSize + '" max="' + originalSize + '" format="size" min-width="' + (minWidth || 100) + '"></lib-gauge>';
    }

    function convertOneFile(rowIndex) {
        var f = currentFiles[rowIndex];
        if (!f) return;
        var dir = document.getElementById('directory').value.trim();
        var quality = parseInt(document.getElementById('quality').value) || 85;
        var regenerate = document.getElementById('regenerate').checked;
        var row = document.querySelector('tr[data-row="' + rowIndex + '"]');
        if (!row) return;

        // Show spinner in first variant cell
        var variantCells = row.querySelectorAll('.cell-variant');
        variantCells.forEach(function(c) { c.innerHTML = '<span style="color: var(--text-muted);">...</span>'; });

        var url = 'tools_webp_convert.php?action=convertOne' +
            '&directory=' + encodeURIComponent(dir) +
            '&file=' + encodeURIComponent(f.relativePath || f.name) +
            '&quality=' + quality +
            '&regenerate=1';

        fetch(url)
            .then(function(r) {
                if (!r.ok || !r.headers.get('content-type')?.includes('json')) {
                    return r.text().then(function(txt) {
                        throw new Error('Server fout (HTTP ' + r.status + '): ' + txt.substring(0, 200));
                    });
                }
                return r.json();
            })
            .then(function(data) {
                if (!data.success) {
                    variantCells.forEach(function(c) { c.innerHTML = '<span style="color:red;" title="' + (data.error || '') + '">Fout</span>'; });
                    return;
                }

                // Update the file data in currentFiles
                f.hasVariants = true;
                f.variants = data.variants;
                f.webpSize = data.webpSize;

                // Update each variant column
                var cells = row.querySelectorAll('.cell-variant');
                if (cells[0]) cells[0].innerHTML = renderVariantCell(f, rowIndex, 0, true);
                for (var si = 0; si < VARIANT_SIZES.length; si++) {
                    if (cells[si + 1]) cells[si + 1].innerHTML = renderVariantCell(f, rowIndex, VARIANT_SIZES[si], false);
                }

                // Re-attach click handlers for new thumbnails in this row
                attachThumbHandlers(row);
            })
            .catch(function(err) {
                variantCells.forEach(function(c) { c.innerHTML = '<span style="color:red;" title="' + err.message + '">Fout</span>'; });
            });
    }

    function attachThumbHandlers(container) {
        var thumbs = (container || document).querySelectorAll('.variant-thumb');
        for (var j = 0; j < thumbs.length; j++) {
            if (thumbs[j].dataset.bound) continue;
            thumbs[j].dataset.bound = '1';
            thumbs[j].addEventListener('click', function() {
                var idx = parseInt(this.getAttribute('data-row'));
                if (currentFiles[idx]) showImagePreview(currentFiles[idx]);
            });
        }
    }

    function attachConvertOneHandlers(container) {
        var btns = (container || document).querySelectorAll('.btn-convert-one');
        for (var j = 0; j < btns.length; j++) {
            if (btns[j].dataset.bound) continue;
            btns[j].dataset.bound = '1';
            btns[j].addEventListener('click', function() {
                convertOneFile(parseInt(this.getAttribute('data-row')));
            });
        }
    }

    function attachFilePreviewHandlers(container) {
        var links = (container || document).querySelectorAll('.file-preview');
        for (var j = 0; j < links.length; j++) {
            if (links[j].dataset.bound) continue;
            links[j].dataset.bound = '1';
            links[j].addEventListener('click', function(e) {
                e.preventDefault();
                var idx = parseInt(this.getAttribute('data-row'));
                if (currentFiles[idx]) showImagePreview(currentFiles[idx]);
            });
        }
    }

    function renderTable(files) {
        if (files.length === 0) {
            document.getElementById('results-table').innerHTML = '<lib-message type="info">Geen afbeeldingen gevonden</lib-message>';
            return;
        }

        var html = '<table class="table" style="width: 100%; border-collapse: collapse;">';
        html += '<thead><tr>';
        html += '<th style="text-align: left; padding: 8px; border-bottom: 2px solid var(--border-color);">Bestand</th>';
        html += '<th style="text-align: right; padding: 8px; border-bottom: 2px solid var(--border-color);">Afmetingen</th>';
        html += '<th style="text-align: right; padding: 8px; border-bottom: 2px solid var(--border-color);">Origineel</th>';
        html += '<th style="text-align: left; padding: 8px; border-bottom: 2px solid var(--border-color);">WebP volledig</th>';
        for (var si = 0; si < VARIANT_SIZES.length; si++) {
            html += '<th style="text-align: left; padding: 8px; border-bottom: 2px solid var(--border-color);">Responsive ' + VARIANT_SIZES[si] + '</th>';
        }
        html += '<th style="text-align: center; padding: 8px; border-bottom: 2px solid var(--border-color); width: 40px;"></th>';
        html += '</tr></thead><tbody>';

        for (var i = 0; i < files.length; i++) {
            var f = files[i];
            var dims = (f.width && f.height) ? f.width + ' &times; ' + f.height : '-';

            html += '<tr data-row="' + i + '" style="border-bottom: 1px solid var(--border-color);">';
            html += '<td style="padding: 6px 8px;"><a href="#" class="file-preview" data-row="' + i + '" style="color: var(--color-primary, #4a90d9); text-decoration: none;">' + (f.relativePath || f.name) + '</a></td>';
            html += '<td style="padding: 6px 8px; text-align: right;">' + dims + '</td>';
            html += '<td style="padding: 6px 8px; text-align: right;">' + formatSize(f.size) + '</td>';
            html += '<td class="cell-variant" style="padding: 6px 8px;">' + renderVariantCell(f, i, 0, true) + '</td>';
            for (var si = 0; si < VARIANT_SIZES.length; si++) {
                html += '<td class="cell-variant" style="padding: 6px 8px;">' + renderVariantCell(f, i, VARIANT_SIZES[si], false) + '</td>';
            }
            html += '<td class="cell-action"><button class="btn btn-convert-one" data-row="' + i + '" title="Converteer"><span class="lnr lnr-sync"></span></button></td>';
            html += '</tr>';
        }

        html += '</tbody></table>';

        // Summary below table: file count + average savings gauge
        var filesWithWebp = files.filter(function(f) { return f.webpSize && f.size; });
        if (filesWithWebp.length > 0) {
            var totalOriginal = 0;
            var totalWebp = 0;
            for (var s = 0; s < filesWithWebp.length; s++) {
                totalOriginal += filesWithWebp[s].size;
                totalWebp += filesWithWebp[s].webpSize;
            }
            html += '<div style="display:flex;align-items:center;gap:16px;margin-top:12px;padding:10px 16px;background:var(--bg-surface,#f5f5f5);border:1px solid var(--border-color,#ddd);border-radius:4px;font-size:0.9em;">';
            html += '<span><strong>' + filesWithWebp.length + '</strong> bestanden met varianten</span>';
            html += '<span style="color:var(--text-muted,#888);">&mdash;</span>';
            html += '<span>Totaal: ' + formatSize(totalOriginal) + ' &rarr; ' + formatSize(totalWebp) + '</span>';
            html += '<lib-gauge value="' + totalWebp + '" max="' + totalOriginal + '" format="size" label="Gemiddeld" min-width="180"></lib-gauge>';
            html += '</div>';
        }

        document.getElementById('results-table').innerHTML = html;

        attachThumbHandlers();
        attachConvertOneHandlers();
        attachFilePreviewHandlers();
    }

    window.convertAll = function() {
        var dir = document.getElementById('directory').value.trim();
        var quality = parseInt(document.getElementById('quality').value) || 85;
        var regenerate = document.getElementById('regenerate').checked;
        if (!dir) return;

        // Count files to convert: all files when regenerating, only unconverted otherwise
        var toConvertCount = regenerate
            ? currentFiles.length
            : currentFiles.filter(function(f) { return !f.hasVariants; }).length;
        if (toConvertCount === 0) {
            showStatus('<lib-message type="info">Alle afbeeldingen hebben al varianten</lib-message>');
            return;
        }

        var btnConvert = document.getElementById('btn-convert');
        btnConvert.disabled = true;

        var progressDiv = document.getElementById('progress');
        progressDiv.style.display = 'block';
        var progressBar = document.getElementById('progress-bar');
        var progressText = document.getElementById('progress-text');
        progressBar.style.width = '0%';
        progressText.textContent = '0 / ' + toConvertCount + ' — Bezig met converteren...';

        var totalErrors = 0;
        var totalConverted = 0;
        var totalToConvert = toConvertCount;
        var BATCH_SIZE = 20;
        var currentOffset = 0;
        var allErrorDetails = [];

        function convertBatch() {
            var url = 'tools_webp_convert.php?action=convert' +
                '&directory=' + encodeURIComponent(dir) +
                '&quality=' + quality +
                '&offset=' + currentOffset +
                '&limit=' + BATCH_SIZE +
                (regenerate ? '&regenerate=1' : '');

            fetch(url)
                .then(function(r) {
                    if (!r.ok || !r.headers.get('content-type')?.includes('json')) {
                        return r.text().then(function(txt) {
                            throw new Error('Server fout (HTTP ' + r.status + '): ' + txt.substring(0, 200));
                        });
                    }
                    return r.json();
                })
                .then(function(data) {
                    if (!data.success) {
                        showStatus('<lib-message type="error">' + (data.error || 'Conversie mislukt') + '</lib-message>');
                        btnConvert.disabled = false;
                        btnConvert.innerHTML = '<span class="lnr lnr-picture"></span> Alles converteren';
                        return;
                    }

                    totalConverted += data.converted;
                    totalErrors += data.errors;
                    if (data.errorDetails && data.errorDetails.length > 0) {
                        allErrorDetails = allErrorDetails.concat(data.errorDetails);
                    }

                    var processed = totalConverted + totalErrors;
                    var pct = Math.round((processed / totalToConvert) * 100);
                    var remaining = totalToConvert - processed;
                    progressBar.style.width = Math.min(pct, 100) + '%';
                    var errLabel = totalErrors === 1 ? ' fout' : ' fouten';
                    progressText.textContent = totalConverted + ' / ' + totalToConvert +
                        ' geconverteerd' + (totalErrors > 0 ? ', ' + totalErrors + errLabel : '') +
                        (remaining > 0 ? ' — nog ' + remaining + ' te gaan...' : '');
                    btnConvert.textContent = processed + '/' + totalToConvert;

                    // Determine if there are more files to process
                    if (data.batchSize > 0 && processed < totalToConvert) {
                        // For regenerate: increment offset (all files stay in list)
                        // For normal: offset stays 0 (converted files get filtered out server-side)
                        if (regenerate) {
                            currentOffset += data.batchSize;
                        }
                        convertBatch();
                    } else {
                        // Done
                        progressBar.style.width = '100%';
                        var doneText = 'Klaar: ' + totalConverted + ' geconverteerd';
                        if (totalErrors > 0) doneText += ', ' + totalErrors + (totalErrors === 1 ? ' fout' : ' fouten');
                        progressText.textContent = doneText;

                        // Show error details if any
                        if (allErrorDetails.length > 0) {
                            var errHtml = '<div style="margin-top: 8px; max-height: 200px; overflow-y: auto; ' +
                                'background: var(--bg-surface, #f9f9f9); border: 1px solid var(--border-color, #ddd); ' +
                                'border-radius: 4px; padding: 8px; font-size: 0.85em;">';
                            errHtml += '<strong style="color: var(--color-danger, #c00);">Fouten:</strong><ul style="margin: 4px 0 0 16px; padding: 0;">';
                            for (var ei = 0; ei < allErrorDetails.length; ei++) {
                                errHtml += '<li><strong>' + allErrorDetails[ei].file + '</strong>: ' + allErrorDetails[ei].error + '</li>';
                            }
                            errHtml += '</ul></div>';
                            progressText.innerHTML = doneText + errHtml;
                        }

                        btnConvert.disabled = false;
                        btnConvert.innerHTML = '<span class="lnr lnr-picture"></span> Alles converteren';
                        // Refresh scan to update table
                        window.scanDirectory();
                    }
                })
                .catch(function(err) {
                    showStatus('<lib-message type="error">Fout: ' + err.message + '</lib-message>');
                    btnConvert.disabled = false;
                    btnConvert.innerHTML = '<span class="lnr lnr-picture"></span> Alles converteren';
                });
        }

        // Start first batch
        convertBatch();
    };

    window.cleanupAll = function() {
        var dir = document.getElementById('directory').value.trim();
        if (!dir) return;

        var btnCleanup = document.getElementById('btn-cleanup');
        btnCleanup.disabled = true;
        btnCleanup.textContent = 'Bezig...';

        var formData = new FormData();
        formData.append('directory', dir);

        fetch('tools_webp_convert.php?action=cleanup', {
            method: 'POST',
            body: formData
        })
            .then(function(r) {
                if (!r.ok || !r.headers.get('content-type')?.includes('json')) {
                    return r.text().then(function(txt) {
                        throw new Error('Server fout (HTTP ' + r.status + '): ' + txt.substring(0, 200));
                    });
                }
                return r.json();
            })
            .then(function(data) {
                btnCleanup.disabled = false;
                btnCleanup.innerHTML = '<span class="lnr lnr-trash"></span> Opruimen';

                if (!data.success) {
                    showStatus('<lib-message type="error">' + (data.error || 'Opruimen mislukt') + '</lib-message>');
                    return;
                }

                showStatus('<lib-message type="success">' + data.deleted + ' .responsive map(pen) verwijderd</lib-message>');

                // Refresh scan
                setTimeout(function() { window.scanDirectory(); }, 1000);
            })
            .catch(function(err) {
                btnCleanup.disabled = false;
                btnCleanup.innerHTML = '<span class="lnr lnr-trash"></span> Opruimen';
                showStatus('<lib-message type="error">Fout: ' + err.message + '</lib-message>');
            });
    };
    // Auto-scan default directory on load
    window.scanDirectory();
})();
</script>

<?php cma_body_end(); ?>
