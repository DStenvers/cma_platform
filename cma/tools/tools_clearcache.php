<?php
/**
 * Cache clearing tool
 *
 * IMPORTANT: We count and clear file-based caches BEFORE loading the bootstrap,
 * because the bootstrap itself may create cache files (config, etc.)
 *
 * API Mode: Call with ?api=1 to get JSON response (useful for Cypress tests)
 */

// Pre-calculate cache directories (before Application class is available)
// Cache is now in site root: /site/cache/cma/
$_siteRoot = dirname(__DIR__, 2);
$_envCacheDir = getenv('CACHE_DIRECTORY') ?: ($_ENV['CACHE_DIRECTORY'] ?? null);
$_appCacheDir = $_envCacheDir ?: ($_siteRoot . '/cache');
$_cmaCacheDir = $_siteRoot . '/cache/cma';
$_formCacheDir = $_siteRoot . '/cache/cma/forms';
$_minifyDir = $_siteRoot . '/cache/cma/minify';

// Session and temp directories
$_sessionDir = ini_get('session.save_path') ?: sys_get_temp_dir();
$_tempDir = sys_get_temp_dir();
$_todayStart = strtotime('today midnight');

// ============================================================================
// API Mode - Quick cache clear for testing (no HTML output)
// Note: $_GET is used directly here because bootstrap is not yet loaded.
// ============================================================================
if (isset($_GET['api']) && $_GET['api'] === '1') {
    header('Content-Type: application/json');

    $cleared = 0;

    // Clear form cache
    if (is_dir($_formCacheDir)) {
        $files = glob($_formCacheDir . '/*') ?: [];
        foreach ($files as $file) {
            if (is_file($file) && @unlink($file)) {
                $cleared++;
            }
        }
    }

    // Clear app cache
    if (is_dir($_appCacheDir)) {
        $files = glob($_appCacheDir . '/*.cache') ?: [];
        foreach ($files as $file) {
            if (is_file($file) && @unlink($file)) {
                $cleared++;
            }
        }
    }

    // Clear CMA cache
    if (is_dir($_cmaCacheDir)) {
        $files = glob($_cmaCacheDir . '/*.cache') ?: [];
        foreach ($files as $file) {
            if (is_file($file) && @unlink($file)) {
                $cleared++;
            }
        }
    }

    // Clear memory caches (APCu, OPcache) to invalidate form templates
    if (function_exists('apcu_clear_cache')) {
        @apcu_clear_cache();
    }
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }

    echo json_encode(['success' => true, 'cleared' => $cleared]);
    exit;
}

// ============================================================================
// PHASE 1: Clear file-based caches BEFORE bootstrap (to get accurate counts)
// ============================================================================

// Track detailed file lists for "show details"
$_clearedFiles = [
    'app_cache' => [],
    'file_cache' => [],
    'minify' => [],
    'form_html' => [],
    'sessions' => [],
    'temp' => [],
    'js_minify' => [],
];

// Count and clear App Cache files (before bootstrap creates new ones)
$_preAppCacheCount = 0;
$_preAppCacheCleared = 0;
if (is_dir($_appCacheDir)) {
    $files = glob($_appCacheDir . '/*.cache') ?: [];
    $_preAppCacheCount = count($files);
    foreach ($files as $file) {
        if (is_file($file)) {
            $basename = basename($file);
            $size = filesize($file);
            if (@unlink($file)) {
                $_preAppCacheCleared++;
                $_clearedFiles['app_cache'][] = [
                    'name' => $basename,
                    'path' => $file,
                    'size' => $size,
                    'type' => 'Applicatie cache item'
                ];
            }
        }
    }
}

// Count and clear File Cache (recursive)
$_preFileCacheCount = 0;
$_preFileCacheDirs = 0;
if (is_dir($_appCacheDir) && is_readable($_appCacheDir)) {
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($_appCacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $basename = $file->getBasename();
                $pathname = $file->getPathname();
                $size = $file->getSize();
                $subpath = str_replace($_appCacheDir . '/', '', $pathname);
                if (@unlink($pathname)) {
                    $_preFileCacheCount++;
                    $_clearedFiles['file_cache'][] = [
                        'name' => $basename,
                        'path' => $subpath,
                        'size' => $size,
                        'type' => 'Gecacht bestand'
                    ];
                }
            } elseif ($file->isDir()) {
                if (@rmdir($file->getPathname())) {
                    $_preFileCacheDirs++;
                }
            }
        }
    } catch (Exception $e) {
        // Ignore
    }
}

// Count and clear Minify cache
$_preMinifyCount = 0;
if (is_dir($_minifyDir)) {
    foreach (glob($_minifyDir . '/*') as $file) {
        if (is_file($file)) {
            $basename = basename($file);
            $size = filesize($file);
            if (@unlink($file)) {
                $_preMinifyCount++;
                $_clearedFiles['minify'][] = [
                    'name' => $basename,
                    'path' => 'cache/minify/' . $basename,
                    'size' => $size,
                    'type' => strpos($basename, '.css') !== false ? 'Geminificeerde CSS bundel' : 'Geminificeerde JS bundel'
                ];
            }
        }
    }
}
// Also clear cma/cache root
if (is_dir($_cmaCacheDir)) {
    foreach (glob($_cmaCacheDir . '/*') as $file) {
        if (is_file($file)) {
            $basename = basename($file);
            $size = filesize($file);
            if (@unlink($file)) {
                $_preMinifyCount++;
                $_clearedFiles['minify'][] = [
                    'name' => $basename,
                    'path' => 'cache/' . $basename,
                    'size' => $size,
                    'type' => 'CMA cache bestand'
                ];
            }
        }
    }
}

// Count and clear Form HTML templates (generated cache files in cache/forms/)
$_preFormCount = 0;
if (is_dir($_formCacheDir)) {
    foreach (glob($_formCacheDir . '/*.html') as $file) {
        $basename = basename($file);
        $size = filesize($file);
        if (@unlink($file)) {
            $_preFormCount++;
            // Extract form ID from filename like form_123_40.html or form_json_xxx_40.html
            $formInfo = 'Formulier template';
            if (preg_match('/form_(?:json_)?(\w+)_(\d+)\.html/', $basename, $m)) {
                $formInfo = "Formulier {$m[1]} template (variant {$m[2]})";
            }
            $_clearedFiles['form_html'][] = [
                'name' => $basename,
                'path' => 'cache/forms/' . $basename,
                'size' => $size,
                'type' => $formInfo
            ];
        }
    }
}

// Count and clear old PHP session files (not from today)
$_preSessionCount = 0;
$_preSessionSize = 0;
if (is_dir($_sessionDir) && is_readable($_sessionDir)) {
    // Session files typically start with "sess_"
    foreach (glob($_sessionDir . '/sess_*') as $file) {
        if (is_file($file)) {
            $mtime = filemtime($file);
            // Only delete files not from today
            if ($mtime < $_todayStart) {
                $basename = basename($file);
                $size = filesize($file);
                if (@unlink($file)) {
                    $_preSessionCount++;
                    $_preSessionSize += $size;
                    $_clearedFiles['sessions'][] = [
                        'name' => $basename,
                        'path' => $_sessionDir . '/' . $basename,
                        'size' => $size,
                        'type' => 'Sessiebestand (' . date('d-m-Y', $mtime) . ')'
                    ];
                }
            }
        }
    }
}

// Count and clear old temp files (not from today)
// Only clean common PHP temp file patterns to avoid deleting system files
$_preTempCount = 0;
$_preTempSize = 0;
$_tempPatterns = ['php*.tmp', 'tmp*.tmp', 'upload_*', '~DF*.tmp'];
if (is_dir($_tempDir) && is_readable($_tempDir)) {
    foreach ($_tempPatterns as $pattern) {
        foreach (glob($_tempDir . '/' . $pattern) as $file) {
            if (is_file($file)) {
                $mtime = filemtime($file);
                // Only delete files not from today
                if ($mtime < $_todayStart) {
                    $basename = basename($file);
                    $size = filesize($file);
                    if (@unlink($file)) {
                        $_preTempCount++;
                        $_preTempSize += $size;
                        $_clearedFiles['temp'][] = [
                            'name' => $basename,
                            'path' => $_tempDir . '/' . $basename,
                            'size' => $size,
                            'type' => 'Tijdelijk bestand (' . date('d-m-Y', $mtime) . ')'
                        ];
                    }
                }
            }
        }
    }
}

// Check invalidation file before bootstrap
$_invalidationFile = $_appCacheDir . '/cache_invalidation.json';
$_hadInvalidationSignals = file_exists($_invalidationFile) && filesize($_invalidationFile) > 2;
$_invalidationContent = '';
if ($_hadInvalidationSignals) {
    $_invalidationContent = @file_get_contents($_invalidationFile);
}

// ============================================================================
// PHASE 1b: Capture and clear OPcache/APCu BEFORE bootstrap
// This ensures we capture what was actually cached, not files loaded by this request
// ============================================================================
$_preOpcacheStats = null;
$_preOpcacheScripts = 0;
$_preOpcacheResult = false;
if (function_exists('opcache_get_status')) {
    $_preOpcacheStats = @opcache_get_status(false);
    $_preOpcacheScripts = $_preOpcacheStats['opcache_statistics']['num_cached_scripts'] ?? 0;
}
if (function_exists('opcache_reset')) {
    $_preOpcacheResult = @opcache_reset();
}

$_preApcuInfo = null;
$_preApcuCount = 0;
$_preApcuResult = false;
if (function_exists('apcu_cache_info')) {
    $_preApcuInfo = @apcu_cache_info(true);
    $_preApcuCount = $_preApcuInfo['num_entries'] ?? 0;
}
if (function_exists('apcu_clear_cache')) {
    $_preApcuResult = @apcu_clear_cache();
}

// ============================================================================
// SILENT MODE: Just clear caches and exit (for AJAX calls from migrations)
// ============================================================================
if (isset($_GET['silent']) && $_GET['silent'] === '1') {
    // Clear remaining memory caches
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    if (function_exists('apcu_clear_cache')) {
        @apcu_clear_cache();
    }
    clearstatcache(true);

    // Clear invalidation file
    if (file_exists($_invalidationFile)) {
        @unlink($_invalidationFile);
    }

    // Return simple JSON response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Caches cleared']);
    exit;
}

// ============================================================================
// PHASE 2: Load bootstrap (this may create new cache entries)
// ============================================================================

use App\Library\Application;
use App\Library\Cache;
use App\Library\Response;
use Cma\Services\BaseFormService;
use Cma\ToolbarHelper;

require_once __DIR__ . '/../bootstrap.inc';

Response::noCache();

// Capture how many files bootstrap loaded (for informational display)
$_bootstrapOpcacheCount = 0;
if (function_exists('opcache_get_status')) {
    $_bootstrapStats = @opcache_get_status(false);
    $_bootstrapOpcacheCount = $_bootstrapStats['opcache_statistics']['num_cached_scripts'] ?? 0;
}

// ============================================================================
// PHASE 3: Clear memory-based caches and collect results
// ============================================================================

$caches = [];

// 1. OPcache - use pre-captured stats from Phase 1b
if (function_exists('opcache_reset')) {
    $caches['OPcache'] = [
        'available' => true,
        'result' => $_preOpcacheResult,
        'detail' => 'PHP bytecode',
        'count' => $_preOpcacheScripts,
        'extra' => $_preOpcacheStats ? [
            'Geheugen gebruikt' => number_format(($_preOpcacheStats['memory_usage']['used_memory'] ?? 0) / 1024 / 1024, 2) . ' MB',
            'Geheugen vrij' => number_format(($_preOpcacheStats['memory_usage']['free_memory'] ?? 0) / 1024 / 1024, 2) . ' MB',
            'Gecachte scripts' => $_preOpcacheScripts,
            'Bootstrap scripts' => $_bootstrapOpcacheCount . ' (normaal na refresh)',
            'Cache hits' => number_format($_preOpcacheStats['opcache_statistics']['hits'] ?? 0),
            'Cache misses' => number_format($_preOpcacheStats['opcache_statistics']['misses'] ?? 0),
        ] : null
    ];
} else {
    $caches['OPcache'] = ['available' => false];
}

// 2. APCu - use pre-captured stats from Phase 1b
if (function_exists('apcu_clear_cache')) {
    $caches['APCu'] = [
        'available' => true,
        'result' => $_preApcuResult,
        'detail' => $_preApcuCount . ' items',
        'count' => $_preApcuCount,
        'extra' => $_preApcuInfo ? [
            'Geheugengrootte' => number_format(($_preApcuInfo['mem_size'] ?? 0) / 1024 / 1024, 2) . ' MB',
            'Items' => $_preApcuCount,
            'Hits' => number_format($_preApcuInfo['num_hits'] ?? 0),
            'Misses' => number_format($_preApcuInfo['num_misses'] ?? 0),
        ] : null
    ];
} else {
    $caches['APCu'] = ['available' => false];
}

// 3. Application cache - use pre-counted values
$cacheStatus = Cache::getStatus();
$backend = $cacheStatus['backend'] ?? 'unknown';
$cacheEnabled = $cacheStatus['enabled'] ?? false;

if ($backend === 'none' || !$cacheEnabled) {
    $caches['App Cache'] = ['available' => false, 'hint' => 'Caching uitgeschakeld in configuratie'];
} elseif ($backend === 'redis') {
    $redisCount = 0;
    $redisInfo = null;
    try {
        $redis = new Redis();
        $redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1', (int)(getenv('REDIS_PORT') ?: 6379));
        $redisCount = $redis->dbSize();
        $redisInfo = $redis->info();
    } catch (Exception $e) {
        // Ignore
    }
    $appCacheResult = Cache::clear();
    $caches['App Cache'] = [
        'available' => true,
        'result' => $appCacheResult,
        'detail' => $redisCount . ' items (Redis)',
        'count' => $redisCount,
        'extra' => $redisInfo ? [
            'Server versie' => $redisInfo['redis_version'] ?? 'onbekend',
            'Geheugen gebruikt' => $redisInfo['used_memory_human'] ?? 'onbekend',
            'Sleutels' => $redisCount,
            'Verbonden clients' => $redisInfo['connected_clients'] ?? 'onbekend',
        ] : null
    ];
    if (!$appCacheResult) {
        $caches['App Cache']['hint'] = 'Controleer Redis verbinding: redis-cli ping';
    }
} elseif ($backend === 'apcu') {
    // APCu count already captured in Phase 1b
    $caches['App Cache'] = [
        'available' => true,
        'result' => true,
        'detail' => $_preApcuCount . ' items (APCu gedeeld)',
        'count' => $_preApcuCount
    ];
} elseif ($backend === 'file') {
    // Use pre-counted and pre-cleared values
    $caches['App Cache'] = [
        'available' => true,
        'result' => true,
        'detail' => $_preAppCacheCount . ' bestanden',
        'count' => $_preAppCacheCount,
        'files' => $_clearedFiles['app_cache']
    ];
} else {
    $caches['App Cache'] = ['available' => false, 'hint' => 'Onbekende backend: ' . $backend];
}

// 4. File cache directory - use pre-cleared values
$caches['File Cache'] = [
    'available' => true,
    'result' => true,
    'detail' => $_preFileCacheCount . ' bestanden' . ($_preFileCacheDirs > 0 ? ", {$_preFileCacheDirs} mappen" : ''),
    'count' => $_preFileCacheCount,
    'files' => $_clearedFiles['file_cache']
];

// 5. Minify cache - use pre-cleared values
$caches['Minify'] = [
    'available' => true,
    'result' => true,
    'detail' => $_preMinifyCount . ' bestanden',
    'count' => $_preMinifyCount,
    'files' => $_clearedFiles['minify']
];

// 6. Form templates - use pre-cleared values
$caches['Form HTML'] = [
    'available' => true,
    'result' => true,
    'detail' => $_preFormCount . ' bestanden',
    'count' => $_preFormCount,
    'files' => $_clearedFiles['form_html']
];

// 7. Invalidation signals - use pre-checked value
$invalidationResult = Cache::clearInvalidationSignals();
$caches['Invalidation'] = [
    'available' => true,
    'result' => $invalidationResult,
    'detail' => $_hadInvalidationSignals ? 'Signalen gewist' : 'Geen signalen',
    'count' => $_hadInvalidationSignals ? 1 : 0,
    'extra' => $_hadInvalidationSignals && $_invalidationContent ? [
        'Inhoud' => strlen($_invalidationContent) > 200
            ? substr($_invalidationContent, 0, 200) . '...'
            : $_invalidationContent
    ] : null
];

// 8. Realpath cache
// PHP doesn't expose realpath cache size, so we mark it as "always runs" type
$realpathInfo = null;
if (function_exists('realpath_cache_size')) {
    $realpathInfo = [
        'Cache grootte' => number_format(realpath_cache_size()) . ' bytes',
        'Cache TTL' => ini_get('realpath_cache_ttl') . ' seconden',
    ];
}
clearstatcache(true);
$caches['Realpath'] = [
    'available' => true,
    'result' => true,
    'detail' => 'Intern (PHP)',
    'alwaysRuns' => true,
    'extra' => $realpathInfo
];

// 9. Cache groups (invalidate)
// These always write new timestamps, so mark as "always runs"
Cache::invalidateGroup('forms');
Cache::invalidateGroup(BaseFormService::CACHE_GROUP_FORMDEFS);
$caches['Groups'] = [
    'available' => true,
    'result' => true,
    'detail' => '2 groepen',
    'alwaysRuns' => true,
    'extra' => [
        'Groepen geïnvalideerd' => 'forms, formdefs',
        'Effect' => 'Alle gecachte items in deze groepen worden ververst bij volgende toegang'
    ]
];

// 10. PHP Sessions (old files only)
$caches['Sessions'] = [
    'available' => is_dir($_sessionDir) && is_writable($_sessionDir),
    'result' => true,
    'detail' => $_preSessionCount . ' bestanden',
    'count' => $_preSessionCount,
    'files' => $_clearedFiles['sessions'],
    'extra' => $_preSessionCount > 0 ? [
        'Map' => $_sessionDir,
        'Vrijgemaakt' => formatSize($_preSessionSize),
        'Filter' => 'Alleen bestanden van vóór vandaag'
    ] : ['Map' => $_sessionDir]
];

// 11. Temp files (old files only)
// is_writable() can be unreliable on Windows; do a real write test
$_tempAvailable = false;
if (is_dir($_tempDir)) {
    $_tempTestFile = $_tempDir . DIRECTORY_SEPARATOR . 'cma_write_test_' . getmypid() . '.tmp';
    if (@file_put_contents($_tempTestFile, 'test') !== false) {
        $_tempAvailable = true;
        @unlink($_tempTestFile);
    }
}
$caches['Temp'] = [
    'available' => $_tempAvailable,
    'result' => true,
    'detail' => $_preTempCount . ' bestanden',
    'count' => $_preTempCount,
    'files' => $_clearedFiles['temp'],
    'extra' => $_preTempCount > 0 ? [
        'Map' => $_tempDir,
        'Vrijgemaakt' => formatSize($_preTempSize),
        'Patronen' => implode(', ', $_tempPatterns),
        'Filter' => 'Alleen bestanden van vóór vandaag'
    ] : ['Map' => $_tempDir, 'Patronen' => implode(', ', $_tempPatterns)]
];
if (!$_tempAvailable) {
    $reason = !is_dir($_tempDir) ? 'map bestaat niet' : 'niet schrijfbaar (schrijftest mislukt)';
    $caches['Temp']['hint'] = 'Temp: ' . $reason . '. Map: ' . $_tempDir;
}

// 12. JS Minification (rebuild .min.js files using terser)
$_isWindows = DIRECTORY_SEPARATOR === '\\';
$_cmaRoot = dirname(__DIR__);
$_terserCmd = '';
$_terserDebug = [];

// Step 1: Find node.exe (IIS typically doesn't have it in PATH)
$_nodePath = '';
if ($_isWindows) {
    $nodeCandidates = [
        'C:\\Program Files\\nodejs\\node.exe',
        'C:\\Program Files (x86)\\nodejs\\node.exe',
    ];
    // Also check nvm-windows paths (C:\Program Files\nodejs is often a symlink)
    // Scan C:\Users\*\AppData\Roaming\nvm\*\node.exe
    $nvmGlob = glob('C:\\Users\\*\\AppData\\Roaming\\nvm\\v*\\node.exe');
    if ($nvmGlob) {
        foreach ($nvmGlob as $nvmPath) {
            $nodeCandidates[] = $nvmPath;
        }
    }
    foreach ($nodeCandidates as $nc) {
        if (file_exists($nc)) {
            $_nodePath = $nc;
            break;
        }
    }
    if (empty($_nodePath)) {
        // Try PATH as last resort
        $fromPath = trim(shell_exec('where node 2>nul') ?? '');
        if (!empty($fromPath)) {
            $_nodePath = trim(strtok($fromPath, "\n"));
        }
    }
    $_terserDebug['node'] = $_nodePath ?: 'niet gevonden';
} else {
    $_nodePath = trim(shell_exec('which node 2>/dev/null') ?? '');
    if (empty($_nodePath)) {
        foreach (['/usr/bin/node', '/usr/local/bin/node'] as $nc) {
            if (file_exists($nc) && is_executable($nc)) { $_nodePath = $nc; break; }
        }
    }
}

// Step 2: Find terser module
$_terserModule = $_cmaRoot . '/node_modules/terser/bin/terser';
if ($_isWindows) {
    $_terserModule = $_cmaRoot . '\\node_modules\\terser\\bin\\terser';
}
$_terserModuleExists = file_exists($_terserModule);
$_terserDebug['module'] = $_terserModuleExists ? $_terserModule : 'niet gevonden';

// Step 3: Build the command and verify
if (!empty($_nodePath) && $_terserModuleExists) {
    // Use node directly with the module - bypasses PATH issues entirely
    $_terserCmd = escapeshellarg($_nodePath) . ' ' . escapeshellarg($_terserModule);
    $testOutput = shell_exec($_terserCmd . ' --version 2>&1');
    if (!$testOutput || strpos($testOutput, 'terser') === false) {
        $_terserDebug['versiecheck'] = trim($testOutput ?: 'geen output');
        $_terserCmd = ''; // Failed
    } else {
        $_terserDebug['versie'] = trim($testOutput);
    }
} elseif (!$_isWindows) {
    // On Linux, try terser directly from PATH
    $directTerser = trim(shell_exec('which terser 2>/dev/null') ?? '');
    if (!empty($directTerser) && is_executable($directTerser)) {
        $_terserCmd = escapeshellarg($directTerser);
        $_terserDebug['pad'] = $directTerser;
    }
}

$_terserAvailable = !empty($_terserCmd);
$_jsMinifyCount = 0;
$_jsMinifySaved = 0;
$_jsMinifyResult = true;
$_clearedFiles['js_minify'] = [];

if ($_terserAvailable) {
    $_jsDirs = [
        __DIR__ . '/../webcomponents' => 'cma/webcomponents',
        __DIR__ . '/../assets/js' => 'cma/assets/js',
        $_siteRoot . '/library/webcomponents' => 'library/webcomponents',
        $_siteRoot . '/library' => 'library',
        $_siteRoot => 'site',
    ];

    foreach ($_jsDirs as $dir => $label) {
        if (!is_dir($dir)) continue;

        foreach (glob($dir . '/*.js') as $jsFile) {
            // Skip .min.js files
            if (substr($jsFile, -7) === '.min.js') continue;

            $minFile = substr($jsFile, 0, -3) . '.min.js';
            $basename = basename($jsFile);

            // Skip if .min.js exists and is newer than source
            if (file_exists($minFile) && filemtime($minFile) >= filemtime($jsFile)) {
                continue;
            }

            $origSize = filesize($jsFile);
            $escapedJs = escapeshellarg($jsFile);
            $escapedMin = escapeshellarg($minFile);
            $output = shell_exec("{$_terserCmd} {$escapedJs} --compress --mangle -o {$escapedMin} 2>&1");

            clearstatcache(true, $minFile);
            if (file_exists($minFile) && filesize($minFile) > 0) {
                $minSize = filesize($minFile);
                $saved = $origSize - $minSize;
                $_jsMinifyCount++;
                $_jsMinifySaved += $saved;
                $_clearedFiles['js_minify'][] = [
                    'name' => $basename,
                    'path' => $label . '/' . $basename,
                    'size' => $saved,
                    'type' => "Geminificeerd ({$origSize} → {$minSize} bytes)"
                ];
            } else {
                // Track failed files
                $_jsMinifyResult = false;
                $_clearedFiles['js_minify'][] = [
                    'name' => $basename,
                    'path' => $label . '/' . $basename,
                    'size' => 0,
                    'type' => 'FOUT: ' . trim($output ?: 'geen output')
                ];
            }
        }
    }
}

$caches['JS Minify'] = [
    'available' => $_terserAvailable,
    'result' => $_jsMinifyResult,
    'detail' => $_jsMinifyCount . ' bestanden',
    'count' => $_jsMinifyCount,
    'files' => $_clearedFiles['js_minify'],
    'extra' => $_jsMinifyCount > 0 ? array_merge([
        'Bestanden geminificeerd' => $_jsMinifyCount,
        'Totaal bespaard' => formatSize($_jsMinifySaved),
    ], $_terserDebug) : ($_terserAvailable ? $_terserDebug : $_terserDebug),
];

if (!$_terserAvailable) {
    $caches['JS Minify']['hint'] = 'terser niet beschikbaar';
}

// Check if any available cache failed
$anyFailed = false;
$failedCaches = [];
foreach ($caches as $name => $info) {
    if ($info['available'] && !$info['result']) {
        $anyFailed = true;
        $failedCaches[] = $name;
    }
}

// Helper function to format file size
function formatSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

// Collect all cleared files for detailed view
$allClearedFiles = [];
$totalSize = 0;
foreach ($_clearedFiles as $category => $files) {
    foreach ($files as $file) {
        $file['category'] = $category;
        $allClearedFiles[] = $file;
        $totalSize += $file['size'] ?? 0;
    }
}

// Output HTML
cma_html_header('Cache leegmaken');
echo '<BODY class="contentbody tools tool-clearcache">';
ToolbarHelper::report('Cache leegmaken', false, false, false, false, 'Leeg de applicatie en form caches');
echo '<div id="c" class="tools">';

// ==================== RESULT FIRST ====================
if ($anyFailed) {
    echo '<lib-message type="warning" style="margin-bottom:15px;">Sommige caches konden niet worden geleegd</lib-message>';
} else {
    echo '<lib-message type="success" style="margin-bottom:15px;">Alle beschikbare caches zijn geleegd</lib-message>';
}

// OPcache warning - critical for performance
if (!$caches['OPcache']['available']) {
    echo '<lib-message type="warning" style="margin-bottom:15px;" closable="false">';
    echo '<strong>OPcache is niet geïnstalleerd of uitgeschakeld!</strong> Dit heeft een grote impact op performance.<br><br>';
    echo 'PHP scripts worden bij elk request opnieuw gecompileerd. Dit kan pagina\'s 5-10x trager maken.<br><br>';
    echo '<strong>Oplossing:</strong> Voeg toe aan php.ini:<br>';
    echo '<code style="display:block;margin:10px 0;padding:10px;background:var(--bg-surface);border-radius:4px;">';
    echo 'zend_extension=opcache<br>';
    echo 'opcache.enable=1<br>';
    echo 'opcache.enable_cli=1<br>';
    echo 'opcache.memory_consumption=128<br>';
    echo 'opcache.interned_strings_buffer=8<br>';
    echo 'opcache.max_accelerated_files=10000<br>';
    echo 'opcache.validate_timestamps=1<br>';
    echo 'opcache.revalidate_freq=2';
    echo '</code>';
    echo 'Herstart daarna IIS of PHP-FPM. Controleer met <code>php -m | grep OPcache</code>.';
    echo '</lib-message>';
}

// APCu warning - critical for performance
if (!$caches['APCu']['available']) {
    echo '<lib-message type="warning" style="margin-bottom:15px;" closable="false">';
    echo '<strong>APCu is niet geïnstalleerd!</strong> Dit heeft een grote impact op performance.<br><br>';
    echo 'De cache valt nu terug op trage file-based caching. Formulieren laden langzamer en API calls duren langer.<br><br>';
    echo '<strong>Oplossing:</strong> Voeg toe aan php.ini:<br>';
    echo '<code style="display:block;margin:10px 0;padding:10px;background:var(--bg-surface);border-radius:4px;">';
    echo 'extension=apcu<br>';
    echo 'apc.enabled=1<br>';
    echo 'apc.shm_size=128M';
    echo '</code>';
    echo 'Herstart daarna IIS of PHP-FPM.';
    echo '</lib-message>';
}

// ==================== DETAILS TABLE ====================
// Info descriptions for each cache type (Dutch)
$cacheInfo = [
    'OPcache' => 'PHP bytecode cache. Slaat gecompileerde PHP scripts op in geheugen voor snellere uitvoering. Wordt geleegd met opcache_reset().',
    'APCu' => 'PHP gebruikerscache. Slaat applicatiedata op in gedeeld geheugen. Wordt geleegd met apcu_clear_cache().',
    'App Cache' => 'Applicatie-cache (Redis, APCu of Bestand). Slaat database queries, formulierdefinities en andere data op. Configureer CACHE_DIRECTORY in .env naar een map binnen de site (bijv. /cache/cma).',
    'File Cache' => 'Bestandsgebaseerde cache map. Bevat geserialiseerde cachedata op schijf.',
    'Minify' => 'Geminificeerde CSS/JS bestanden. Gecomprimeerde versies van stylesheets en scripts voor sneller laden.',
    'Form HTML' => 'Vooraf gegenereerde formuliertemplates. Gecachte HTML bestanden in cache/forms/.',
    'Invalidation' => 'Cache invalidatiesignalen. Coördineert cache legen over meerdere PHP processen.',
    'Realpath' => 'PHP bestandspad cache. Interne cache van opgeloste bestandspaden. Geleegd met clearstatcache().',
    'Groups' => 'Cache groep markers. Invalidatie timestamps voor cachegroepen zoals formulieren, beveiliging, menu.',
    'Sessions' => 'PHP sessiebestanden. Oude sessies (van vóór vandaag) worden verwijderd. Actieve sessies blijven behouden.',
    'Temp' => 'PHP tijdelijke bestanden. Oude temp bestanden (van vóór vandaag) worden verwijderd. Patronen: php*.tmp, tmp*.tmp, upload_*, ~DF*.tmp.',
    'JS Minify' => 'JavaScript minificatie met terser. Bouwt .min.js bestanden naast de originelen. Ondersteunt variable mangling en dead-code elimination voor optimale compressie.',
];

echo '<h3>Overzicht</h3>';
echo '<table class="tools-table">';
echo '<thead>';

// Header row with cache types and info icons
echo '<tr>';
foreach ($caches as $name => $info) {
    $tooltip = htmlspecialchars($cacheInfo[$name] ?? 'Cache type');
    echo '<th>' . htmlspecialchars($name);
    echo '<span class="info-icon">i<span class="tooltip">' . $tooltip . '</span></span>';
    echo '</th>';
}
echo '</tr>';
echo '</thead>';
echo '<tbody>';

// Status row
echo '<tr>';
foreach ($caches as $name => $info) {
    if (!$info['available']) {
        echo '<td class="status na">n/a</td>';
    } elseif ($info['result']) {
        // Check if this is an "always runs" type (like Realpath, Groups)
        $alwaysRuns = $info['alwaysRuns'] ?? false;
        // Check if count is available and zero (no action taken)
        $hasCount = isset($info['count']);
        $noAction = $hasCount && $info['count'] === 0;

        if ($alwaysRuns) {
            echo '<td class="status always">✓</td>';
        } elseif ($noAction) {
            echo '<td class="status noaction">–</td>';
        } else {
            echo '<td class="status ok">✓</td>';
        }
    } else {
        echo '<td class="status fail">✗</td>';
    }
}
echo '</tr>';

// Detail row
echo '<tr class="detail">';
foreach ($caches as $name => $info) {
    if (!$info['available']) {
        // Show hint for unavailable caches (help admin fix it)
        $hintHtml = $info['hintHtml'] ?? '';
        $hint = $info['hint'] ?? '';
        if ($hintHtml) {
            echo '<td class="na-detail">' . $hintHtml . '</td>';
        } elseif ($hint) {
            echo '<td class="na-detail">' . htmlspecialchars($hint) . '</td>';
        } else {
            echo '<td class="na-detail"></td>';
        }
    } else {
        $alwaysRuns = $info['alwaysRuns'] ?? false;
        $hasCount = isset($info['count']);
        $count = $info['count'] ?? 0;
        $noAction = $hasCount && $count === 0;

        if ($hasCount && $count > 0) {
            // Emphasize count when items were cleared
            echo '<td><strong>' . $count . '</strong> ' . ($count === 1 ? 'item' : 'items') . '</td>';
        } elseif ($alwaysRuns) {
            echo '<td class="always-detail">' . htmlspecialchars($info['detail'] ?? '') . '</td>';
        } elseif ($noAction) {
            echo '<td class="noaction-detail">' . htmlspecialchars($info['detail'] ?? '') . '</td>';
        } else {
            echo '<td>' . htmlspecialchars($info['detail'] ?? '') . '</td>';
        }
    }
}
echo '</tr>';

echo '</tbody>';
echo '</table>';

// ==================== TERSER SETUP INSTRUCTIONS ====================
if (!$_terserAvailable) {
    $nodeFound = !empty($_nodePath);
    $moduleFound = $_terserModuleExists;
    echo '<lib-message type="warning" style="margin-top:15px;" closable="false">';
    echo '<strong>JS Minificatie niet beschikbaar</strong><br><br>';
    echo 'Terser comprimeert JavaScript bestanden voor sneller laden. ';
    echo 'De volgende vereisten worden gecontroleerd:<br><br>';

    // Step-by-step checklist
    echo '<table style="font-size:var(--font-size-sm);border-collapse:collapse;width:100%">';

    // Step 1: Node.js
    $nodeIcon = $nodeFound ? '✓' : '✗';
    $nodeColor = $nodeFound ? 'green' : 'red';
    echo '<tr><td style="padding:4px 8px;font-size:var(--font-size-lg);color:' . $nodeColor . ';width:24px">' . $nodeIcon . '</td>';
    echo '<td style="padding:4px 8px"><strong>Stap 1:</strong> Node.js geïnstalleerd</td>';
    echo '<td style="padding:4px 8px;color:var(--text-secondary)">';
    if ($nodeFound) {
        echo '<code style="background:var(--bg-code,#f0f0f0);padding:1px 4px;border-radius:3px">' . htmlspecialchars($_nodePath) . '</code>';
    } else {
        echo 'Installeer Node.js van <a href="https://nodejs.org" target="_blank">nodejs.org</a> naar <code>C:\\Program Files\\nodejs\\</code>';
    }
    echo '</td></tr>';

    // Step 2: terser module
    $modIcon = $moduleFound ? '✓' : '✗';
    $modColor = $moduleFound ? 'green' : 'red';
    echo '<tr><td style="padding:4px 8px;font-size:var(--font-size-lg);color:' . $modColor . '">' . $modIcon . '</td>';
    echo '<td style="padding:4px 8px"><strong>Stap 2:</strong> terser geïnstalleerd</td>';
    echo '<td style="padding:4px 8px;color:var(--text-secondary)">';
    if ($moduleFound) {
        echo 'Module gevonden';
    } else {
        $installCmd = 'cd /d ' . $_cmaRoot . ' && npm install terser --save-dev';
        echo 'Voer uit in een terminal:<br>';
        echo '<code id="terserInstallCmd" style="display:inline-block;margin:4px 0;padding:4px 8px;background:var(--bg-code,#f0f0f0);border-radius:3px;user-select:all;word-break:break-all">'
            . htmlspecialchars($installCmd) . '</code> ';
        echo '<button onclick="navigator.clipboard.writeText(document.getElementById(\'terserInstallCmd\').textContent).then(function(){var b=document.getElementById(\'terserCopyBtn\');b.textContent=\'Gekopieerd!\';setTimeout(function(){b.textContent=\'Kopieer\'},1500)})" '
            . 'id="terserCopyBtn" class="btn" style="font-size:var(--font-size-xs);padding:2px 8px;vertical-align:middle">Kopieer</button>';
    }
    echo '</td></tr>';

    // Step 3: IIS restart
    echo '<tr><td style="padding:4px 8px;font-size:var(--font-size-lg);color:var(--text-secondary)">3</td>';
    echo '<td style="padding:4px 8px"><strong>Stap 3:</strong> IIS herstarten</td>';
    echo '<td style="padding:4px 8px;color:var(--text-secondary)">';
    $iisCmd = 'iisreset';
    echo '<code id="iisResetCmd" style="display:inline-block;padding:4px 8px;background:var(--bg-code,#f0f0f0);border-radius:3px;user-select:all">'
        . htmlspecialchars($iisCmd) . '</code> ';
    echo '<button onclick="navigator.clipboard.writeText(document.getElementById(\'iisResetCmd\').textContent).then(function(){var b=document.getElementById(\'iisCopyBtn\');b.textContent=\'Gekopieerd!\';setTimeout(function(){b.textContent=\'Kopieer\'},1500)})" '
        . 'id="iisCopyBtn" class="btn" style="font-size:var(--font-size-xs);padding:2px 8px;vertical-align:middle">Kopieer</button>';
    echo '</td></tr>';

    echo '</table>';
    echo '</lib-message>';
}

// ==================== SHOW DETAILS LINK ====================
$hasDetails = count($allClearedFiles) > 0 || array_filter($caches, fn($c) => !empty($c['extra']));
if ($hasDetails) {
    echo '<p style="margin-top:15px;">';
    echo '<a href="#" id="toggleDetails" onclick="toggleDetails(); return false;" style="color:var(--link-color,#077ab2);text-decoration:underline;">';
    echo '<span class="lnr lnr-list" style="margin-right:5px;"></span>Toon details</a>';
    if (count($allClearedFiles) > 0) {
        echo ' <span style="color:var(--text-muted,#888);">(' . count($allClearedFiles) . ' bestanden, ' . formatSize($totalSize) . ' totaal)</span>';
    }
    echo '</p>';

    echo '<div id="detailsPanel" style="display:none; margin-top:15px;">';

    // Browser cache note
    echo '<lib-message type="info" id="browser-cache-note" style="margin-bottom:15px;"><strong>Browser cache:</strong> Ververs handmatig met <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>R</kbd></lib-message>';

    // Memory/Runtime cache details
    $hasExtraInfo = array_filter($caches, fn($c) => !empty($c['extra']));
    if ($hasExtraInfo) {
        echo '<h4 style="margin:20px 0 10px 0; color:var(--text-color);">Cache statistieken</h4>';
        echo '<table class="details-table">';
        echo '<thead><tr><th>Cache</th><th>Eigenschap</th><th>Waarde</th></tr></thead>';
        echo '<tbody>';
        foreach ($caches as $name => $info) {
            if (!empty($info['extra'])) {
                $first = true;
                foreach ($info['extra'] as $key => $value) {
                    echo '<tr>';
                    if ($first) {
                        $rowspan = count($info['extra']);
                        echo '<td rowspan="' . $rowspan . '" style="font-weight:bold;vertical-align:top;">' . htmlspecialchars($name) . '</td>';
                        $first = false;
                    }
                    echo '<td>' . htmlspecialchars($key) . '</td>';
                    echo '<td>' . htmlspecialchars($value) . '</td>';
                    echo '</tr>';
                }
            }
        }
        echo '</tbody>';
        echo '</table>';
    }

    // File details
    if (count($allClearedFiles) > 0) {
        echo '<h4 style="margin:20px 0 10px 0; color:var(--text-color);">Verwijderde bestanden</h4>';
        echo '<table class="details-table">';
        echo '<thead><tr><th>Categorie</th><th>Bestand</th><th>Type</th><th style="text-align:right;">Grootte</th></tr></thead>';
        echo '<tbody>';

        // Group by category
        $byCategory = [];
        foreach ($allClearedFiles as $file) {
            $cat = $file['category'];
            if (!isset($byCategory[$cat])) {
                $byCategory[$cat] = [];
            }
            $byCategory[$cat][] = $file;
        }

        $categoryNames = [
            'app_cache' => 'App Cache',
            'file_cache' => 'File Cache',
            'minify' => 'Minify',
            'form_html' => 'Form HTML',
            'sessions' => 'Sessions',
            'temp' => 'Temp',
            'js_minify' => 'JS Minify',
        ];

        foreach ($byCategory as $cat => $files) {
            $catName = $categoryNames[$cat] ?? $cat;
            $first = true;
            foreach ($files as $file) {
                echo '<tr>';
                if ($first) {
                    $rowspan = count($files);
                    echo '<td rowspan="' . $rowspan . '" style="font-weight:bold;vertical-align:top;">' . htmlspecialchars($catName) . '</td>';
                    $first = false;
                }
                echo '<td><code style="font-size:var(--font-size-xs);">' . htmlspecialchars($file['path']) . '</code></td>';
                echo '<td>' . htmlspecialchars($file['type']) . '</td>';
                echo '<td style="text-align:right;white-space:nowrap;">' . formatSize($file['size'] ?? 0) . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p style="color:var(--text-muted,#888);font-style:italic;">Geen bestanden verwijderd.</p>';
    }

    echo '</div>';

    // JavaScript for toggle
    echo '<script>
function toggleDetails() {
    var panel = document.getElementById("detailsPanel");
    var link = document.getElementById("toggleDetails");
    if (panel.style.display === "none") {
        panel.style.display = "block";
        link.innerHTML = \'<span class="lnr lnr-list" style="margin-right:5px;"></span>Verberg details\';
    } else {
        panel.style.display = "none";
        link.innerHTML = \'<span class="lnr lnr-list" style="margin-right:5px;"></span>Toon details\';
    }
}
</script>';
}

// ==================== FAILURE HINTS ====================
if ($anyFailed) {
    echo '<lib-message type="warning" closable>';
    echo '<p style="margin:0 0 10px 0;"><strong>Handmatig legen:</strong></p>';
    echo '<ul style="margin:0;padding-left:20px;font-size:var(--font-size-sm);">';

    // Default hints per cache type (Dutch) - may contain safe HTML (links)
    $defaultHints = [
        'OPcache' => 'opcache_reset() is mislukt. <a href="javascript:location.reload()">Probeer het nog een keer</a>, dit kan een tijdelijk probleem zijn. Blijft het falen, controleer dan of OPcache is ingeschakeld in php.ini.',
        'APCu' => 'apcu_clear_cache() is mislukt. Controleer of APCu is ingeschakeld in php.ini',
        'App Cache' => 'Controleer cache backend verbinding en rechten',
        'File Cache' => 'Controleer schrijfrechten op cache map: ' . htmlspecialchars($_appCacheDir),
        'Minify' => 'Controleer schrijfrechten op: ' . htmlspecialchars($_cmaCacheDir),
        'Form HTML' => 'Controleer schrijfrechten op: ' . htmlspecialchars($_formCacheDir),
        'Invalidation' => 'Controleer schrijfrechten voor cache invalidatie bestand',
        'Realpath' => 'Dit zou niet moeten falen - controleer PHP configuratie',
        'Groups' => 'Controleer cache backend verbinding',
        'Sessions' => 'Controleer schrijfrechten op sessiemap: ' . htmlspecialchars($_sessionDir),
        'Temp' => 'Controleer schrijfrechten op temp map: ' . htmlspecialchars($_tempDir),
        'JS Minify' => 'terser niet gevonden',
    ];

    foreach ($failedCaches as $name) {
        // Use hintHtml (with copy button etc.) first, then plain hint, then default
        $hintHtml = $caches[$name]['hintHtml'] ?? '';
        $hint = $caches[$name]['hint'] ?? ($defaultHints[$name] ?? null);
        echo '<li><strong>' . htmlspecialchars($name) . ':</strong> ';
        if ($hintHtml) {
            echo $hintHtml;
        } elseif ($hint) {
            // Default hints may contain safe HTML (links), output as-is
            echo $hint;
        } else {
            echo 'Controleer configuratie en rechten';
        }
        echo '</li>';
    }
    echo '</ul></lib-message>';
}

// ==================== STYLES FOR DETAILS TABLE ====================
echo '<style>
.details-table {
    width: 100%;
    border-collapse: collapse;
    font-size: var(--font-size-sm);
    margin-bottom: 15px;
}
.details-table th {
    background: var(--bg-header, #f5f5f5);
    padding: 8px 10px;
    text-align: left;
    border-bottom: 2px solid var(--border-color, #ddd);
    font-weight: 600;
}
.details-table td {
    padding: 6px 10px;
    border-bottom: 1px solid var(--border-color, #eee);
    vertical-align: top;
}
.details-table tr:hover td {
    background: var(--bg-hover);
}
.details-table code {
    background: var(--bg-code, #f0f0f0);
    padding: 2px 4px;
    border-radius: 3px;
    word-break: break-all;
}
</style>';

// Auto-clear browser form cache on page load (Service Worker cache)
echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    if (typeof CMA !== "undefined" && typeof CMA.clearFormCache === "function") {
        CMA.clearFormCache().then(function(cleared) {
            if (cleared) {
                // Update browser cache message
                var msg = document.getElementById("browser-cache-note");
                if (msg) {
                    msg.setAttribute("type", "success");
                    msg.innerHTML = "<strong>Browser cache:</strong> Formulier cache automatisch geleegd";
                }
            }
        });
    }
});
</script>';

echo '</div></BODY></HTML>';
