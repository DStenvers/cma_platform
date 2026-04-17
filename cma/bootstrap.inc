<?php
/**
 * CMA All-in-One Include File
 * Consolidated from multiple include files for simpler maintenance
 */

// CMA application version — read from Composer package version (git tag)
if (class_exists('\App\Library\Bootstrap')) {
    define('CMA_APP_VERSION', \App\Library\Bootstrap::getPlatformVersion());
} else {
    define('CMA_APP_VERSION', 'dev');
}

// Buffer output so Debug/Profiler script tags don't prevent setcookie()/header() calls
ob_start();

// =========================================================================
// SECTION: Parent Bootstrap - Load site-wide dependencies
// =========================================================================
// The parent _bootstrap.php loads App\Library classes (Application, Database, etc.)
// It should be auto-prepended by IIS, but if not (CLI, direct access), load it here
if (!class_exists('App\Library\Application', false)) {
    $parentBootstrap = dirname(__DIR__) . '/_bootstrap.php';
    if (file_exists($parentBootstrap)) {
        require_once $parentBootstrap;
    } else {
        die('Fatal: Parent bootstrap not found. App\Library classes unavailable.');
    }
}

// CMA uses cookie-based auth, not PHP sessions.
// Close the session immediately to release the session lock and prevent interference.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Ensure CKEditor CSS is always set for CMA (uses CKEditor's contents.css)
if (empty($GLOBALS['Application']['cma_htmledit_css'])) {
    $GLOBALS['Application']['cma_htmledit_css'] = '/cma/CKEditor/contents.css';
}

// =========================================================================
// SECTION: Development Mode - Enable error display for debugging
// =========================================================================
// Automatically enable PHP errors in development environments
// This helps catch conversion bugs that would otherwise show as blank pages
//
// Debug mode is ALWAYS enabled for:
// - O (Ontwikkeling/Development)
// - L (Local)
// - T (Test)
// - A (Acceptance)
//
// For Production (P), you can still enable debug mode via:
// - Add ?debug=1 to any URL
// - Set CMA_DEBUG=1 in environment

$_cmaDebugMode = false;

// Check application environment FIRST - always enable for non-production
$_cmaEnv = $_ENV['APP_ENVIRONMENT'] ?? $_SERVER['APP_ENVIRONMENT'] ?? ($GLOBALS['Application']['omgeving'] ?? 'P');
if (in_array(strtoupper($_cmaEnv), ['O', 'L', 'T', 'A'])) {
    $_cmaDebugMode = true;
}

// For Production: allow override via environment variable or user preference cookie
if (!$_cmaDebugMode) {
    if (getenv('CMA_DEBUG') === '1' || ($_ENV['CMA_DEBUG'] ?? '') === '1') {
        $_cmaDebugMode = true;
    } elseif (isset($_COOKIE['cma_debug_mode']) && $_COOKIE['cma_debug_mode'] === 'J') {
        // Check user preference (admin/developer check happens in SecurityHelper::isDebugMode())
        $_cmaDebugMode = true;
    }
}

// Enable error display if in debug mode
if ($_cmaDebugMode) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);

    // Also enable ErrorHandler verbose mode if class exists
    if (class_exists('\App\Library\ErrorHandler')) {
        \App\Library\ErrorHandler::setVerbose(true);
    }
}

// Make debug mode available globally
define('CMA_DEBUG_MODE', $_cmaDebugMode);

// =========================================================================

use App\Library\Application;
use App\Library\Arr;
use App\Library\Cache;
use App\Library\Cookie;
use App\Library\Database;
use App\Library\Error;
use App\Library\RecordSet;
use App\Library\Response;
use App\Library\Server;
use App\Library\SQL;
use Cma\CmaRepository;
use Cma\SecurityHelper;
use Cma\ToolbarHelper;
use Cma\FormControlHelper;
use Cma\SqlHelper;
use Cma\SchemaHelper;
use Cma\FormRenderer;
use Cma\FormTemplate;
use Cma\FormDataProvider;

// Load helper classes
require_once __DIR__ . '/classes/CmaRepository.php';
require_once __DIR__ . '/classes/SecurityHelper.php';
require_once __DIR__ . '/classes/ToolbarHelper.php';
require_once __DIR__ . '/classes/FormControlHelper.php';
require_once __DIR__ . '/classes/SqlHelper.php';
require_once __DIR__ . '/classes/SchemaHelper.php';
require_once __DIR__ . '/classes/SqlParser.php';
require_once __DIR__ . '/classes/FormDefinition.php';
require_once __DIR__ . '/classes/FormRenderer.php';
require_once __DIR__ . '/classes/FormTemplate.php';
require_once __DIR__ . '/classes/FormDataProvider.php';
require_once __DIR__ . '/classes/JsonFormLoader.php';

// Load service classes
require_once __DIR__ . '/classes/Services/BaseFormService.php';
require_once __DIR__ . '/classes/Services/OptionsService.php';
require_once __DIR__ . '/classes/Services/RecordService.php';
require_once __DIR__ . '/classes/Services/ListService.php';
require_once __DIR__ . '/classes/Services/MenuService.php';
require_once __DIR__ . '/classes/Services/BackupService.php';
require_once __DIR__ . '/classes/Services/MigrationService.php';
require_once __DIR__ . '/classes/Services/SsoService.php';
require_once __DIR__ . '/classes/Services/ReportsService.php';
require_once __DIR__ . '/classes/Services/FormMetadataService.php';
require_once __DIR__ . '/classes/Services/ConfigFormService.php';
require_once __DIR__ . '/classes/Services/DatabasesService.php';
require_once __DIR__ . '/classes/Services/JsonFormService.php';
require_once __DIR__ . '/classes/Services/ListServiceHelper.php';
require_once __DIR__ . '/classes/Services/SubformService.php';
require_once __DIR__ . '/classes/Services/TableService.php';
require_once __DIR__ . '/classes/Services/TreeService.php';
require_once __DIR__ . '/classes/Services/ProcessRunner.php';
require_once __DIR__ . '/classes/Services/Logger.php';
require_once __DIR__ . '/classes/Services/SystemSettings.php';
require_once __DIR__ . '/classes/Services/PerformanceLogger.php';
require_once __DIR__ . '/classes/Services/EmailLogService.php';

// =========================================================================
// SECTION: Email logging hook
// =========================================================================
// Log all sent emails to tblEmailLog for admin review and resend capability.
// Controlled by EMAIL_LOG_ENABLED env variable (default: true).
$emailLogEnabled = ($_ENV['EMAIL_LOG_ENABLED'] ?? 'true') !== 'false';
if ($emailLogEnabled) {
    \App\Library\Email::$afterSend = function(array $data) {
        try {
            \Cma\Services\EmailLogService::log($data);
        } catch (\Exception $e) {
            error_log('EmailLogService::log failed: ' . $e->getMessage());
        }
    };
}

// =========================================================================
// SECTION: Suppress verbose debug/profiler console output
// =========================================================================
// Debug::write() injects <script>console.log()</script> into HTML responses,
// and Profiler::tussenstand() calls Debug::write(). Disable both to keep
// the browser console clean. Errors are still logged server-side.
use App\Library\Debug;
use App\Library\Profiler;
Debug::setActive(false);
Profiler::setEnabled(false);

// =========================================================================
// SECTION: Schema Helper Functions
// =========================================================================

/**
 * Move schema to a specific field (mimics ADO recordset Find)
 *
 * Converts array schema to SchemaRecordSet if needed and positions on the field.
 *
 * @param mixed &$rsSchema Schema (array or SchemaRecordSet) - passed by reference
 * @param string $fieldName Field name to find
 * @return bool True if field found
 */
function lib_dbMoveSchemaToField(&$rsSchema, string $fieldName): bool
{
    // Convert array to SchemaRecordSet if needed
    if (Arr::isArray($rsSchema)) {
        $rsSchema = new \Cma\SchemaRecordSet($rsSchema);
    }

    // If already a SchemaRecordSet, find the column
    if ($rsSchema instanceof \Cma\SchemaRecordSet) {
        return $rsSchema->findColumn($fieldName);
    }

    // For other recordset types, try to use Find method
    if (is_object($rsSchema) && method_exists($rsSchema, 'Find')) {
        $rsSchema->Find("COLUMN_NAME = '" . $fieldName . "'");
        return !$rsSchema->EOF;
    }

    return false;
}

// =========================================================================
// SECTION: Header Constants & HTML Output (from header.inc)
// =========================================================================

define("STRCMAVERSION", '5.13');
if (!defined('STRJQUERYVERSION')) { define("STRJQUERYVERSION", '1.10.2'); }
if (!defined('JQUERYVERSION')) { define("JQUERYVERSION", STRJQUERYVERSION); }
define("STRCHARSET", 'utf-8');
define("CMA_FILTER_COOKIE", 'CMAfilter');

// Legacy constants - kept for backwards compatibility, but use cma_css_url()/cma_js_url() for new code
// These are now generated from the bundle arrays to ensure consistency
define("CMA_CSS_FILES", implode(',', [
    '../library/css/lib-variables.css',
    'assets/css/colors.css',
    '../library/library.css',
    '../library/css/lib-components.css',
    'assets/css/style.css',
    '../library/select2/select2.css',
    '../library/webcomponents/lib-table.css',
    'assets/css/form.css',
    'assets/css/inline-edit.css',
    'assets/css/main.css'
]));

// PERFORMANCE FIX: Separated form-controller.js (276KB) from global bundle
// It's only needed on form pages, not on reports, tools, etc.
define("CMA_JS_FILES", implode(',', [
    '../library/webcomponents/lib-log.js',  // MUST be first - intercepts console.* methods
    'assets/js/cma-utils.js',
    '../library/error-handler.js',
    'assets/js/request-tracker.js',
    '../library/webcomponents/lib-shared-styles.js',  // Shared styles for web components (must be before components)
    '../library/webcomponents/lib-switch.js',
    '../library/webcomponents/lib-dialog.js',
    '../library/webcomponents/lib-message.js',
    '../library/webcomponents/lib-menu.js',
    '../library/webcomponents/lib-toaster.js',
    '../library/webcomponents/lib-search-input.js',
    '../library/webcomponents/lib-datepicker.js',
    '../library/webcomponents/lib-histogram.js',
    '../library/webcomponents/lib-gauge.js',
    '../library/webcomponents/lib-fileuploader.js',
    '../library/webcomponents/lib-combo.js',
    'webcomponents/cma-blockeditor.js',
    'webcomponents/cma-sortlist.js',
    'webcomponents/cma-groupbox.js',
    // cma-combo.js removed - merged into lib-combo.js (registers both tag names)
    'webcomponents/cma-fold.js',
    // cma-tree.js is loaded explicitly by pages that need it (webcomponents/cma-tree.js)
    'webcomponents/cma-toolbar.js',
    'webcomponents/cma-tabs.js',
    '../library/library.js',
    '../library/datepicker.js',
    '../library/select2/select2.js',
    '../library/webcomponents/lib-table.js',
    'assets/js/cma.js',
    'assets/js/cma-users.js',  // User form actions (password reset, login as)
    'webcomponents/cma-htmledit.js',  // Must be after cma.js (depends on CMA.editor)
    'assets/js/blockedit.js',
    'assets/js/table-preferences.js',
    'assets/js/inline-edit.js',
    'assets/js/perf-logger.js',
    '../library/webcomponents/lib-tip.js',
    'assets/js/cma-tours.js',
    'assets/js/main.js'
]));

$bMinified = false;

/**
 * Output a standalone error handler script tag.
 * Use this on pages that don't load the full CMA JS bundle (popups, wizards, etc.)
 * For pages that use cma_js() or cma_html_header(), the error handler is already included.
 *
 * @param bool $return Whether to return the tag instead of echoing
 * @return string The script tag if $return is true
 */
function cma_error_handler($return = false): string {
    $tag = '<script src="/library/error-handler.js"></script>';
    if ($return) {
        return $tag;
    }
    echo $tag . PHP_EOL;
    return '';
}

/**
 * Generate a <script> tag for a JS file, using .min.js in production.
 *
 * - Production (P): always uses .min.js (no file_exists check for performance)
 * - Test/Acceptatie (T/A): auto-minifies via terser if .min.js is missing or outdated;
 *   shows a red/orange error bar if terser fails
 * - Dev (O/L): always uses raw .js for easier debugging
 *
 * Supports both absolute paths ("/library/webcomponents/lib-dialog.js")
 * and relative paths ("../library/webcomponents/lib-dialog.js", "webcomponents/cma-tree.js").
 * Relative paths are resolved from /cma/ for the file_exists check.
 *
 * @param string $src Path to the .js file
 * @param bool $return Return the tag instead of echoing
 * @return string The script tag if $return is true
 */
function cma_script(string $src, bool $return = false): string {
    global $_cmaEnv;
    $env = strtoupper($_cmaEnv);
    $minSrc = preg_replace('/\.js$/', '.min.js', $src);

    if ($env === 'P') {
        // Production: assume .min.js exists, skip file_exists
        $src = $minSrc;
    } elseif ($env === 'T' || $env === 'A') {
        // Test/Acceptatie: use .min.js, auto-minify if missing or outdated
        // Resolve to filesystem path: absolute from DOCUMENT_ROOT, relative from /cma/
        if ($minSrc[0] === '/') {
            $checkPath = $_SERVER['DOCUMENT_ROOT'] . $minSrc;
            $srcPath = $_SERVER['DOCUMENT_ROOT'] . $src;
        } else {
            $checkPath = __DIR__ . '/' . $minSrc;
            $srcPath = __DIR__ . '/' . $src;
        }
        $needsMinify = !file_exists($checkPath)
            || (file_exists($srcPath) && filemtime($checkPath) < filemtime($srcPath));

        if ($needsMinify && file_exists($srcPath)) {
            // Auto-minify via terser (local install in cma/node_modules)
            $terserBin = __DIR__ . '/node_modules/.bin/terser';
            $terserError = '';
            if (!file_exists($terserBin)) {
                $terserError = 'terser niet gevonden — npm install in /cma/';
            } else {
                $cmd = escapeshellarg($terserBin) . ' ' . escapeshellarg($srcPath)
                     . ' --compress --mangle -o ' . escapeshellarg($checkPath) . ' 2>&1';
                $output = [];
                exec($cmd, $output, $exitCode);
                if ($exitCode === 0) {
                    $needsMinify = false;
                } else {
                    $terserError = implode("\n", $output);
                }
            }
        }

        if ($needsMinify) {
            $escaped = htmlspecialchars($minSrc);
            $detail = !empty($terserError) ? ' — ' . htmlspecialchars($terserError) : '';
            $tag = '<script src="' . htmlspecialchars($src) . '"></script>' . PHP_EOL;
            $tag .= '<script>document.addEventListener("DOMContentLoaded",function(){'
                  . 'var d=document.createElement("lib-message");d.setAttribute("type","error");'
                  . 'd.textContent="Minify mislukt: ' . addslashes($escaped . $detail) . '";'
                  . 'document.body.prepend(d)})</script>' . PHP_EOL;
            if ($return) return $tag;
            echo $tag;
            return '';
        }
        $src = $minSrc;
    }
    // Dev (O/L): use raw .js as-is

    $tag = '<script src="' . htmlspecialchars($src) . '"></script>';
    if ($return) return $tag . PHP_EOL;
    echo $tag . PHP_EOL;
    return '';
}

/**
 * Output the standard CMA CSS link tag
 * Use this function to ensure consistent CSS across all CMA pages
 *
 * @param string $extraCss Optional additional CSS files to include (comma-separated)
 * @return string The CSS link tag (for embedding in strings) or echoed if $return is false
 */
function cma_css($extraCss = '', $return = false): string {
    // In nomenu mode (AJAX loading), skip CSS output - main.php already provides it
    if (defined('CMA_NOMENU_MODE') && CMA_NOMENU_MODE) {
        return '';
    }

    // Use centralized bundle URL for consistent caching
    $url = ($extraCss === '') ? cma_css_url() : minify_asset(CMA_CSS_FILES . ',' . $extraCss);
    $link = '<link rel="stylesheet" href="' . $url . '" type="text/css">';
    if ($return) {
        return $link;
    }
    echo $link . PHP_EOL;
    return '';
}

/**
 * Output the standard CMA JavaScript includes
 * Use this function to ensure consistent JS across all CMA pages
 *
 * @param string $extraJs Optional additional JS files to include (comma-separated)
 */
function cma_js($extraJs = ''): void {
    // In nomenu mode (AJAX loading), skip JS output - main.php already provides it
    if (defined('CMA_NOMENU_MODE') && CMA_NOMENU_MODE) {
        return;
    }

    // jQuery first (required by other scripts) - must be synchronous as other scripts depend on it
    // Use absolute path from site root to work from any subdirectory
    echo '<script src="/library/jquery.min.js"></script>' . PHP_EOL;

    // Core web components (lib-loader for loading states)
    cma_script('/library/webcomponents/lib-loader.js');

    // Use centralized bundle URL for consistent caching
    $url = ($extraJs === '') ? cma_js_url() : minify_asset(CMA_JS_FILES . ',' . $extraJs);
    echo '<script src="' . $url . '"></script>' . PHP_EOL;
}

/**
 * Output standard CMA HTML header
 * Call this function at the start of pages that need the standard header
 *
 * @param string $title Optional page title
 * @param string $extraHead Optional extra content for the head section (scripts, styles, etc.)
 * @param bool $closeHead Whether to close the </head> tag (default true)
 */
function cma_html_header($title = '', $extraHead = '', $closeHead = true) {
    // In nomenu mode (AJAX loading), skip the HTML/HEAD structure - main.php already provides it
    if (defined('CMA_NOMENU_MODE') && CMA_NOMENU_MODE) {
        // Bridge parent's error handler to this context
        echo '<script>
        if (window.parent && window.parent !== window && window.parent.CmaErrorHandler) {
            window.CmaErrorHandler = window.parent.CmaErrorHandler;
            window.cmaLogError = window.parent.cmaLogError;
        }
        </script>' . PHP_EOL;
        // Just output extra head content and inline styles if needed
        if ($extraHead != '') {
            echo $extraHead . PHP_EOL;
        }
        return;
    }

    // Get theme preference from cookie
    $currentTheme = Cookie::get('cma_theme', 'light');
    $themeClass = ($currentTheme === 'dark') ? ' class="dark-mode"' : '';
    $useSystemTheme = ($currentTheme === 'system');

    header('Content-Type: text/html;charset=' . STRCHARSET);
    echo '<!DOCTYPE html>' . PHP_EOL;
    echo '<html lang="nl"' . $themeClass . '>' . PHP_EOL;
    echo '<head>' . PHP_EOL;
    echo '<meta charset="' . STRCHARSET . '">' . PHP_EOL;
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . PHP_EOL;
    echo '<meta name="mobile-web-app-capable" content="yes">' . PHP_EOL;

    // PERFORMANCE: Preload critical resources to speed up rendering
    // Use same URLs as actual loads for cache consistency
    // Use absolute paths from site root to work from any subdirectory (like tools/)
    echo '<link rel="preload" href="' . cma_css_url() . '" as="style">' . PHP_EOL;
    echo '<link rel="preload" href="/library/jquery.min.js" as="script">' . PHP_EOL;
    echo '<link rel="preload" href="' . cma_js_url() . '" as="script">' . PHP_EOL;

    // Build page title: "Page Title | Content Management Applicatie"
    $pageTitle = 'Content Management Applicatie';
    if ($title != '') {
        $pageTitle = $title . ' | Content Management Applicatie';
    }
    echo '<title>' . htmlspecialchars($pageTitle) . '</title>' . PHP_EOL;

    // Use centralized CSS loading
    cma_css();

    // Use centralized JS loading
    cma_js();

    // Output any extra head content
    if ($extraHead != '') {
        echo $extraHead . PHP_EOL;
    }

    // Add system theme script if theme is set to 'system'
    if ($useSystemTheme) {
        echo '<script>
        // Apply system theme preference before page renders
        (function() {
            if (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches) {
                document.documentElement.classList.add("dark-mode");
            }
            window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", function(e) {
                document.documentElement.classList.toggle("dark-mode", e.matches);
            });
        })();
        </script>' . PHP_EOL;
    }

    // Sync popup style preference from cookie to localStorage
    // This allows library.js to read the preference without server-side rendering
    $popupStylePref = Cookie::get('cma_popup_style', 'sidepanel');
    echo '<script>
    (function() {
        try {
            localStorage.setItem("cma_popup_style", ' . json_encode($popupStylePref) . ');
        } catch(e) {}
    })();
    </script>' . PHP_EOL;

    // Add migration notification script if pending migrations
    if (!empty($GLOBALS['_pending_migrations'])) {
        $count = count($GLOBALS['_pending_migrations']);
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            // Check if toolbar exists and add migration notification
            var toolbar = document.querySelector(".tbcontent, #c.toolbar");
            if (toolbar && !document.querySelector(".migration-notification")) {
                var notice = document.createElement("div");
                notice.className = "migration-notification";
                notice.innerHTML = \'<span class="lnr lnr-warning"></span> Er zijn \' + ' . $count . ' + \' database updates beschikbaar. <a href="tools_migrations.php" target="_top">Nu uitvoeren</a>\';
                notice.style.cssText = "background:#fff3cd;border:1px solid #ffc107;color:#856404;padding:8px 12px;margin:5px 0;border-radius:4px;font-size:12px;";
                notice.querySelector("a").style.cssText = "color:#856404;font-weight:bold;text-decoration:underline;";
                toolbar.parentNode.insertBefore(notice, toolbar.nextSibling);
            }
        });
        </script>' . PHP_EOL;
    }

    if ($closeHead) {
        echo '</head>' . PHP_EOL;
    }
}

/**
 * Output the HTML body tag with appropriate classes
 * In nomenu mode, outputs a div wrapper instead of body tag
 *
 * @param string $class Optional CSS class for the body/div
 * @param string $extra Optional extra attributes
 */
function cma_body_start($class = '', $extra = '') {
    // Get user level for tips/tours system
    $userLevel = 'U'; // Default to User
    if (SecurityHelper::isLoggedIn()) {
        $level = SecurityHelper::getUserLevel();
        if ($level >= SecurityHelper::LEVEL_DEVELOPER) {
            $userLevel = 'D';
        } elseif ($level >= SecurityHelper::LEVEL_ADMIN) {
            $userLevel = 'A';
        }
    }
    $userLevelAttr = ' data-user-level="' . $userLevel . '"';

    if (defined('CMA_NOMENU_MODE') && CMA_NOMENU_MODE) {
        // In nomenu mode, use a div wrapper instead of body
        echo '<div class="cma-ajax-content ' . htmlspecialchars($class) . '"' . $userLevelAttr . ($extra ? ' ' . $extra : '') . '>' . PHP_EOL;
    } else {
        echo '<body class="' . htmlspecialchars($class) . '"' . $userLevelAttr . ($extra ? ' ' . $extra : '') . '>' . PHP_EOL;
    }
}

/**
 * Output the closing body/html tags
 * In nomenu mode, closes the div wrapper instead
 */
function cma_body_end() {
    if (defined('CMA_NOMENU_MODE') && CMA_NOMENU_MODE) {
        echo '</div>' . PHP_EOL;
    } else {
        echo '</body></html>' . PHP_EOL;
    }
}

// =========================================================================
// SECTION: Asset Bundles - Centralized CSS/JS definitions for caching
// =========================================================================
// These bundles should be used consistently across all pages so browsers
// can cache the combined files effectively. Same URL = cache hit.

/**
 * Core CSS bundle - used by all CMA pages
 */
function cma_css_bundle(): array {
    return [
        '../library/css/lib-variables.css',
        'assets/css/colors.css',
        '../library/library.css',
        '../library/css/lib-components.css',
        'assets/css/style.css',
        '../library/select2/select2.css',
        '../library/classes/class_table.css',
        '../library/webcomponents/lib-table.css',
        'assets/css/form.css',
        'assets/css/inline-edit.css',
        'assets/css/main.css'
    ];
}

/**
 * Core JS bundle - used by all CMA pages
 * Note: jQuery must be loaded separately before this bundle
 */
// ============================================================================
// CRITICAL: DO NOT CHANGE THIS BUNDLE ORDER OR SPLIT INTO DYNAMIC LOADING
// ============================================================================
// All JavaScript MUST be loaded in a single bundle to ensure proper initialization.
// This bundle order is carefully arranged with dependency chains.
// DO NOT:
//  - Remove files from this bundle
//  - Split into separate bundles
//  - Add dynamic/lazy loading
//  - Reorder files
// Any such changes will break form controllers and tree functionality.
// ============================================================================
function cma_js_bundle(): array {
    return [
        // Shared utilities (CMA_DEBUG, cmaLog, cmaErrorParser) - must be first
        'assets/js/cma-utils.js',
        // URL Manager for clean URL routing (must be early for other scripts to use)
        'assets/js/url-manager.js',
        // Error handler and request tracker - for error console panel
        '../library/error-handler.js',
        'assets/js/request-tracker.js',
        // Base component class (must be before other CMA web components)
        'webcomponents/cma-base-component.js',
        // Shared styles for web components (must be before components that use it)
        '../library/webcomponents/lib-shared-styles.js',
        // Library Web Components - ALL must be included, no exceptions
        '../library/webcomponents/lib-loader.js',
        '../library/webcomponents/lib-switch.js',
        '../library/webcomponents/lib-dialog.js',
        '../library/webcomponents/lib-message.js',
        '../library/webcomponents/lib-menu.js',
        '../library/webcomponents/lib-toaster.js',
        '../library/webcomponents/lib-search-input.js',
        '../library/webcomponents/lib-datepicker.js',
        '../library/webcomponents/lib-timepicker.js',
        '../library/webcomponents/lib-histogram.js',
        '../library/webcomponents/lib-gauge.js',
        '../library/webcomponents/lib-combo.js',
        // CMA Web Components - ALL must be included
        'webcomponents/shared-icons.js',  // Must be first - provides icon definitions for other components
        'webcomponents/cma-blockeditor.js',
        'webcomponents/cma-fold.js',
        'webcomponents/cma-tree.js',
        'webcomponents/cma-sortlist.js',
        'webcomponents/cma-groupbox.js',
        'webcomponents/cma-toolbar.js',
        'webcomponents/cma-tabs.js',
        // Core Libraries
        '../library/library.js',
        '../library/formval_nl.js',  // Form validation (lib_form_digitsonly, form_init_container)
        '../library/datepicker.js',
        '../library/select2/select2.js',
        '../library/webcomponents/lib-table.js',
        'assets/js/cma.js',
        'assets/js/cma-users.js',  // User form actions (password reset, login as)
        'webcomponents/cma-htmledit.js',  // Must be after cma.js (depends on CMA.editor)
        'assets/js/blockedit.js',
        // CMA Application (excluding form-controller.js - see cma_form_js_bundle)
        'assets/js/table-preferences.js',
        'assets/js/inline-edit.js',
        'assets/js/perf-logger.js',
        '../library/webcomponents/lib-tip.js',
        'assets/js/cma-tours.js',
        'assets/js/main.js'
    ];
}

/**
 * Get the JS bundle array for form pages only
 * Includes form-controller.js which is a 276KB file only needed on form pages
 */
function cma_form_js_bundle(): array {
    $bundle = cma_js_bundle();
    // Insert form-controller.js before inline-edit.js (which depends on it)
    $insertIndex = array_search('assets/js/inline-edit.js', $bundle);
    if ($insertIndex !== false) {
        array_splice($bundle, $insertIndex, 0, ['assets/js/form-controller.js']);
    } else {
        $bundle[] = 'assets/js/form-controller.js';
    }
    return $bundle;
}

/**
 * Get URL for the core CSS bundle
 */
function cma_css_url(): string {
    return minify_asset(cma_css_bundle());
}

/**
 * Get URL for the core JS bundle
 */
function cma_js_url(): string {
    return minify_asset(cma_js_bundle());
}

/**
 * Get URL for the form-specific JS bundle (includes form-controller.js)
 * Use this in FormTemplate.php and other form pages instead of cma_js_url()
 */
function cma_form_js_url(): string {
    return minify_asset(cma_form_js_bundle());
}

/**
 * Helper function to generate minify.php URL for CSS/JS files
 *
 * @param string|array $files Single file path or array of file paths
 * @return string URL to minify.php with the files
 */
function minify_asset($files): string {
    if (Arr::isArray($files)) {
        $files = implode(',', $files);
    }
    // Use absolute path from site root to work from any subdirectory (like tools/)
    // Add version parameter for cache busting (increment when making JS changes)
    $version = '20260403a';
    return '/cma/minify.php?f=' . $files . '&v=' . $version;
}

// Legacy aliases for backward compatibility
function cma_minify_url($files): string { return minify_asset($files); }
function cma_minify_js($files): string { return minify_asset($files); }
function cma_minify_css($files): string { return minify_asset($files); }

/**
 * Get application logo configuration (cached)
 *
 * Returns company logo configuration from either ConfigLoader (JSON) or database.
 * Result is cached in APCu for 1 hour.
 *
 * @return array ['logo' => string, 'url' => string, 'backgroundColor' => string, 'height' => int]
 */
function cma_get_app_logo(): array {
    // Check APCu cache first
    $cacheKey = 'cma_app_logo_config';
    if (function_exists('apcu_fetch')) {
        $cached = apcu_fetch($cacheKey);
        if ($cached !== false) {
            return $cached;
        }
    }

    require_once __DIR__ . '/classes/ConfigLoader.php';

    $config = [
        'logo' => '',
        'url' => '../',
        'backgroundColor' => '#3F096E',
        'height' => 62,
    ];

    // Primary source: config/app.json company section
    $appJsonPath = __DIR__ . '/config/app.json';
    if (file_exists($appJsonPath)) {
        $appJson = @json_decode(file_get_contents($appJsonPath), true);
        if (isset($appJson['company'])) {
            $company = $appJson['company'];
            if (!empty($company['logo'])) {
                $config['logo'] = $company['logo'];
            }
            if (!empty($company['url'])) {
                $config['url'] = $company['url'];
            }
            if (!empty($company['backgroundColor'])) {
                $config['backgroundColor'] = $company['backgroundColor'];
            }
            $config['height'] = max((int)($company['logoHeight'] ?? 0) + 2, 62);
        }
    }

    // Fallback to menu.json application section if app.json didn't have logo
    if (empty($config['logo'])) {
        $menuConfig = \Cma\Services\MenuService::getApplicationConfig();
        $config['logo'] = $menuConfig['logo'] ?? '';
        if (empty($config['url']) || $config['url'] === '../') {
            $config['url'] = $menuConfig['url'] ?? '../';
        }
        if ($config['backgroundColor'] === '#3F096E' && !empty($menuConfig['backgroundColor'])) {
            $config['backgroundColor'] = $menuConfig['backgroundColor'];
        }
        if ($config['height'] === 62 && !empty($menuConfig['logoHeight'])) {
            $config['height'] = max((int)$menuConfig['logoHeight'] + 2, 62);
        }
    }

    // Ensure logo path starts with / for absolute path from web root
    if (!empty($config['logo']) && $config['logo'][0] !== '/') {
        $config['logo'] = '/' . $config['logo'];
    }

    // Sanitize background color (only allow valid hex colors)
    if (!preg_match('/^#[0-9A-Fa-f]{3,6}$/', $config['backgroundColor'])) {
        $config['backgroundColor'] = '#3F096E';
    }

    // Cache for 1 hour
    if (function_exists('apcu_store')) {
        apcu_store($cacheKey, $config, 3600);
    }

    return $config;
}

// =========================================================================
// SECTION: Template Constants (from template.inc)
// =========================================================================

define("CONSTEDITSTART", '<!-- #beginedit ');
define("CONSTEDITEND", '<!-- #endedit -->');
define("CONSTFILENAMEFLD", '__filename');
define("CONSTIDFLD", '__ID');
define("CONSTFORREADING", 1);
define("CONSTFORWRITING", 2);

// =========================================================================
// SECTION: Subform Constants (from subform.inc)
// =========================================================================

define("SUBFORM_TABLE", 0);
define("SUBFORM_ID", 1);
define("SUBFORM_CONN", 2);
define("SUBFORM_IDFLD", 3);
define("SUBFORM_QUERY", 4);
define("SUBFORM_NAME", 5);
define("SUBFORM_DETAIL", 6);
define("SUBFORM_PARENT", 7);
define("SUBFORM_SECUSER", 8);
define("SUBFORM_MENUNEW", 9);
define("SUBFORM_GROUPFLD", 10);
define("SUBFORM_FULLWIDTH", 11);
define("SUBFORM_ACTIE", 12);
define("SUBFORM_BEHEER", 13);
define("SUBFORM_JSONFORM", 14); // JSON form name for subform (used when sourceFormId is 0)

// =========================================================================
// SECTION: Form Definition Constants (from formdef.inc)
// These use column names instead of numeric indices for robustness.
// The ColumnMajorArray class supports both numeric and string access.
// =========================================================================

// Form-level columns (same value for all rows)
define("Q_FKDATABASE", "fkDatabase");
define("Q_FRMIDFLD", "FormIDField");
define("Q_AFTERPOSTURL", "AfterPostUrl");
define("Q_SQLTABLENAME", "SqlTable");
define("Q_MENUNEW", "MenuNew");
define("Q_MENUDELETE", "MenuDelete");
define("Q_PREVIEWURL", "previewUrl");
define("Q_FORMNAME", "FormName");
define("Q_SECBYUSER", "blnSecurityByUser");
define("Q_STORELASTMOD", "blnStoreLastModified");
define("Q_CACHE_PREFIX", "Cache_Prefix");

// Control-level columns (different value per row/control)
define("Q_CONTROLID", "ControlID");
define("Q_FIELDNAME", "FieldName");
define("Q_CONTROLTYPEID", "ControlTypeID");
define("Q_ISREQUIRED", "IsRequired");
define("Q_CAPTION", "Caption");
define("Q_POSTCAPTION", "PostCaption");
define("Q_BASEFIELDNAME", "BaseFieldname");
define("Q_CTRLIDFIELD", "IDField");
define("Q_FOREIGNIDFIELD", "ForeignIDField");
define("Q_SOURCETABLE", "SourceTable");
define("Q_SQLLIST", "SqlList");
define("Q_HEIGHT", "Height");
define("Q_HTMLTAGS", "TagsAllowed");
define("Q_IMGPATH", "ImgPath");
define("Q_IMGWIDTHFLD", "ImgWidthField");
define("Q_IMGHEIGHTFLD", "ImgHeightField");
define("Q_IMGRESIZETYPE", "ImgResizeType");
define("Q_IMGRESIZEHEIGHT", "ImgResizeHeight");
define("Q_IMGRESIZEWIDTH", "ImgResizeWidth");
define("Q_FILERANDOM", "blnFileRandomName");
define("Q_CHKLISTWIDTH", "CheckListWidth");
define("Q_PASSONTOPOST", "blnPassOnToPostUrl");
define("Q_XMLSNIPPET", "XMLSnippet");
define("Q_DIRFILENAME", "dirFilename");
define("Q_DIRTEMPLATE", "dirTemplate");
define("Q_DATABASEID", "ControlDatabaseID");

// Extra icon columns
define("Q_EXTRAICONURL", "extraIconURL");
define("Q_EXTRAICONRES", "extraIconResource");
define("Q_EXTRAICONTITLE", "extraIconTitle");
define("Q_NOSPAMJS", "blnNoSpamJS");
define("Q_FILTERFIELDNAME", "FilterFieldName");
define("Q_FILTERDESCR", "FilterCaption");
define("Q_NEWCHANGABLEONLY", "blnNewChangableOnly");
define("Q_PARENTFORM", "fkParentForm");
define("Q_EXTRAICON2URL", "extraIcon2URL");
define("Q_EXTRAICON2RES", "extraIcon2Resource");
define("Q_EXTRAICON2TITLE", "extraIcon2Title");
define("Q_EXTRAICON3URL", "extraIcon3URL");
define("Q_EXTRAICON3RES", "extraIcon3Resource");
define("Q_EXTRAICON3TITLE", "extraIcon3Title");
define("Q_EXTRAICON4URL", "extraIcon4URL");
define("Q_EXTRAICON4RES", "extraIcon4Resource");
define("Q_EXTRAICON4TITLE", "extraIcon4Title");
define("Q_EXTRAICON5URL", "extraIcon5URL");
define("Q_EXTRAICON5RES", "extraIcon5Resource");
define("Q_EXTRAICON5TITLE", "extraIcon5Title");
define("Q_ONLOADJS", "onloadJS");
define("Q_FILTERIDNAME", "filterIDName");
define("Q_FLDREADONLY", "blnReadOnly");
define("Q_FLDLIMITEDHTML", "blnLimitedHTML");
define("Q_FLDMAXCHARS", "intMaxChars");
define("Q_QUICKFIELDS", "quickfilterfields");
define("Q_MENUCOPY", "MenuCopy");
define("Q_KEEPWITHNEXT", "bCombineWithNext");

// Schema columns
define("Q_SCHEMA_DATE_PREC", "schema_date_prec");
define("Q_SCHEMA_DEFAULT", "schema_default");
define("Q_SCHEMA_CHAR_MAXL", "schema_char_maxl");
define("Q_SCHEMA_NUM_PREC", "schema_num_prec");
define("Q_SCHEMA_DATATYPE", "schema_datatype");

// Action/behavior columns
define("Q_ACTIE", "actie");
define("Q_FORM_ACTIE", "FormActie");
define("Q_BEHEER", "isBeheer");

// Tree/grouping columns
define("Q_GROUP1FIELD", "Group1Field");
define("Q_GROUP2FIELD", "Group2Field");
define("Q_GROUP3FIELD", "Group3Field");
define("Q_DETAILFIELD", "DetailField");
define("Q_NAMEQUERY", "NameQuery");
define("Q_RECURSEFIELD", "recurseField");

// Custom renderer columns (for JSON forms)
define("Q_RENDERER", "Renderer");
define("Q_RENDEROPTIONS", "RendererOptions");

// =========================================================================
// SECTION: ADO Constants (from adovbs.inc)
// =========================================================================

// Only define if not already defined (may be loaded by bootstrap)
if (!defined('ADINTEGER')) {

// CursorTypeEnum Values
define("ADOPENFORWARDONLY", 0);
define("ADOPENKEYSET", 1);
define("ADOPENDYNAMIC", 2);
define("ADOPENSTATIC", 3);

// CursorOptionEnum Values
define("ADHOLDRECORDS", 256);
define("ADMOVEPREVIOUS", 512);
define("ADADDNEW", 16778240);
define("ADDELETE", 16779264);
define("ADUPDATE", 16809984);
define("ADBOOKMARK", 8192);
define("ADAPPROXPOSITION", 16384);
define("ADUPDATEBATCH", 65536);
define("ADRESYNC", 131072);
define("ADNOTIFY", 262144);
define("ADFIND", 524288);
define("ADSEEK", 4194304);
define("ADINDEX", 8388608);

// LockTypeEnum Values
define("ADLOCKREADONLY", 1);
define("ADLOCKPESSIMISTIC", 2);
define("ADLOCKOPTIMISTIC", 3);
define("ADLOCKBATCHOPTIMISTIC", 4);

// ExecuteOptionEnum Values
define("ADRUNASYNC", 16);
define("ADASYNCEXECUTE", 16);
define("ADASYNCFETCH", 32);
define("ADASYNCFETCHNONBLOCKING", 64);
define("ADEXECUTENORECORDS", 128);

// ConnectOptionEnum Values
define("ADASYNCCONNECT", 16);

// ObjectStateEnum Values
define("ADSTATECLOSED", 0);
define("ADSTATEOPEN", 1);
define("ADSTATECONNECTING", 2);
define("ADSTATEEXECUTING", 4);
define("ADSTATEFETCHING", 8);

// CursorLocationEnum Values
define("ADUSESERVER", 2);
define("ADUSECLIENT", 3);

// DataTypeEnum Values
define("ADEMPTY", 0);
define("ADTINYINT", 16);
define("ADSMALLINT", 2);
define("ADINTEGER", 3);
define("ADBIGINT", 20);
define("ADUNSIGNEDTINYINT", 17);
define("ADUNSIGNEDSMALLINT", 18);
define("ADUNSIGNEDINT", 19);
define("ADUNSIGNEDBIGINT", 21);
define("ADSINGLE", 4);
define("ADDOUBLE", 5);
define("ADCURRENCY", 6);
define("ADDECIMAL", 14);
define("ADNUMERIC", 131);
define("ADBOOLEAN", 11);
define("ADERROR", 10);
define("ADUSERDEFINED", 132);
define("ADVARIANT", 12);
define("ADIDISPATCH", 9);
define("ADIUNKNOWN", 13);
define("ADGUID", 72);
define("ADDATE", 7);
define("ADDBDATE", 133);
define("ADDBTIME", 134);
define("ADDBTIMESTAMP", 135);
define("ADBSTR", 8);
define("ADCHAR", 129);
define("ADVARCHAR", 200);
define("ADLONGVARCHAR", 201);
define("ADWCHAR", 130);
define("ADVARWCHAR", 202);
define("ADLONGVARWCHAR", 203);
define("ADBINARY", 128);
define("ADVARBINARY", 204);
define("ADLONGVARBINARY", 205);
define("ADCHAPTER", 136);
define("ADFILETIME", 64);
define("ADDBFILETIME", 137);
define("ADPROPVARIANT", 138);
define("ADVARNUMERIC", 139);

// FieldAttributeEnum Values
define("ADFLDMAYDEFER", 2);
define("ADFLDUPDATABLE", 4);
define("ADFLDUNKNOWNUPDATABLE", 8);
define("ADFLDFIXED", 16);
define("ADFLDISNULLABLE", 32);
define("ADFLDMAYBENULL", 64);
define("ADFLDLONG", 128);
define("ADFLDROWID", 256);
define("ADFLDROWVERSION", 512);
define("ADFLDCACHEDEFERRED", 4096);
define("ADFLDKEYCOLUMN", 32768);

// EditModeEnum Values
define("ADEDITNONE", 0);
define("ADEDITINPROGRESS", 1);
define("ADEDITADD", 2);
define("ADEDITDELETE", 4);

// RecordStatusEnum Values
define("ADRECOK", 0);
define("ADRECNEW", 1);
define("ADRECMODIFIED", 2);
define("ADRECDELETED", 4);
define("ADRECUNMODIFIED", 8);
define("ADRECINVALID", 16);
define("ADRECMULTIPLECHANGES", 64);
define("ADRECPENDINGCHANGES", 128);
define("ADRECCANCELED", 256);
define("ADRECCANTRELEASE", 1024);
define("ADRECCONCURRENCYVIOLATION", 2048);
define("ADRECINTEGRITYVIOLATION", 4096);
define("ADRECMAXCHANGESEXCEEDED", 8192);
define("ADRECOBJECTOPEN", 16384);
define("ADRECOUTOFMEMORY", 32768);
define("ADRECPERMISSIONDENIED", 65536);
define("ADRECSCHEMAVIOLATION", 131072);
define("ADRECDBDELETED", 262144);

// GetRowsOptionEnum Values
define("ADGETROWSREST", -1);

// PositionEnum Values
define("ADPOSUNKNOWN", -1);
define("ADPOSBOF", -2);
define("ADPOSEOF", -3);

// BookmarkEnum Values
define("ADBOOKMARKCURRENT", 0);
define("ADBOOKMARKFIRST", 1);
define("ADBOOKMARKLAST", 2);

// MarshalOptionsEnum Values
define("ADMARSHALALL", 0);
define("ADMARSHALMODIFIEDONLY", 1);

// AffectEnum Values
define("ADAFFECTCURRENT", 1);
define("ADAFFECTGROUP", 2);
define("ADAFFECTALL", 3);
define("ADAFFECTALLCHAPTERS", 4);

// ResyncEnum Values
define("ADRESYNCUNDERLYINGVALUES", 1);
define("ADRESYNCALLVALUES", 2);

// CompareEnum Values
define("ADCOMPARELESSTHAN", 0);
define("ADCOMPAREEQUAL", 1);
define("ADCOMPAREGREATERTHAN", 2);
define("ADCOMPARENOTEQUAL", 3);
define("ADCOMPARENOTCOMPARABLE", 4);

// FilterGroupEnum Values
define("ADFILTERNONE", 0);
define("ADFILTERPENDINGRECORDS", 1);
define("ADFILTERAFFECTEDRECORDS", 2);
define("ADFILTERFETCHEDRECORDS", 3);
define("ADFILTERPREDICATE", 4);
define("ADFILTERCONFLICTINGRECORDS", 5);

// SearchDirectionEnum Values
define("ADSEARCHFORWARD", 1);
define("ADSEARCHBACKWARD", -1);

// PersistFormatEnum Values
define("ADPERSISTADTG", 0);
define("ADPERSISTXML", 1);

// StringFormatEnum Values
define("ADSTRINGXML", 0);
define("ADSTRINGHTML", 1);
define("ADCLIPSTRING", 2);

// ConnectPromptEnum Values
define("ADPROMPTALWAYS", 1);
define("ADPROMPTCOMPLETE", 2);
define("ADPROMPTCOMPLETEREQUIRED", 3);
define("ADPROMPTNEVER", 4);

// ConnectModeEnum Values
define("ADMODEUNKNOWN", 0);
define("ADMODEREAD", 1);
define("ADMODEWRITE", 2);
define("ADMODEREADWRITE", 3);
define("ADMODESHAREDENYREAD", 4);
define("ADMODESHAREDENYWRITE", 8);
define("ADMODESHAREEXCLUSIVE", 12);
define("ADMODESHAREDENYNONE", 16);

// IsolationLevelEnum Values
define("ADXACTUNSPECIFIED", 4294967295);
define("ADXACTCHAOS", 16);
define("ADXACTREADUNCOMMITTED", 256);
define("ADXACTBROWSE", 256);
define("ADXACTCURSORSTABILITY", 4096);
define("ADXACTREADCOMMITTED", 4096);
define("ADXACTREPEATABLEREAD", 65536);
define("ADXACTSERIALIZABLE", 1048576);
define("ADXACTISOLATED", 1048576);

// XactAttributeEnum Values
define("ADXACTCOMMITRETAINING", 131072);
define("ADXACTABORTRETAINING", 262144);

// PropertyAttributesEnum Values
define("ADPROPNOTSUPPORTED", 0);
define("ADPROPREQUIRED", 1);
define("ADPROPOPTIONAL", 2);
define("ADPROPREAD", 512);
define("ADPROPWRITE", 1024);

// ErrorValueEnum Values
define("ADERRINVALIDARGUMENT", 3001);
define("ADERRNOCURRENTRECORD", 3021);
define("ADERRILLEGALOPERATION", 3219);
define("ADERRINTRANSACTION", 3246);
define("ADERRFEATURENOTAVAILABLE", 3251);
define("ADERRITEMNOTFOUND", 3265);
define("ADERROBJECTINCOLLECTION", 3367);
define("ADERROBJECTNOTSET", 3420);
define("ADERRDATACONVERSION", 3421);
define("ADERROBJECTCLOSED", 3704);
define("ADERROBJECTOPEN", 3705);
define("ADERRPROVIDERNOTFOUND", 3706);
define("ADERRBOUNDTOCOMMAND", 3707);
define("ADERRINVALIDPARAMINFO", 3708);
define("ADERRINVALIDCONNECTION", 3709);
define("ADERRNOTREENTRANT", 3710);
define("ADERRSTILLEXECUTING", 3711);
define("ADERROPERATIONCANCELLED", 3712);
define("ADERRSTILLCONNECTING", 3713);
define("ADERRNOTEXECUTING", 3715);
define("ADERRUNSAFEOPERATION", 3716);

// ParameterAttributesEnum Values
define("ADPARAMSIGNED", 16);
define("ADPARAMNULLABLE", 64);
define("ADPARAMLONG", 128);

// ParameterDirectionEnum Values
define("ADPARAMUNKNOWN", 0);
define("ADPARAMINPUT", 1);
define("ADPARAMOUTPUT", 2);
define("ADPARAMINPUTOUTPUT", 3);
define("ADPARAMRETURNVALUE", 4);

// CommandTypeEnum Values
define("ADCMDUNKNOWN", 8);
define("ADCMDTEXT", 1);
define("ADCMDTABLE", 2);
define("ADCMDSTOREDPROC", 4);
define("ADCMDFILE", 256);
define("ADCMDTABLEDIRECT", 512);

// EventStatusEnum Values
define("ADSTATUSOK", 1);
define("ADSTATUSERRORSOCCURRED", 2);
define("ADSTATUSCANTDENY", 3);
define("ADSTATUSCANCEL", 4);
define("ADSTATUSUNWANTEDEVENT", 5);

// EventReasonEnum Values
define("ADRSNADDNEW", 1);
define("ADRSNDELETE", 2);
define("ADRSNUPDATE", 3);
define("ADRSNUNDOUPDATE", 4);
define("ADRSNUNDOADDNEW", 5);
define("ADRSNUNDODELETE", 6);
define("ADRSNREQUERY", 7);
define("ADRSNRESYNCH", 8);
define("ADRSNCLOSE", 9);
define("ADRSNMOVE", 10);
define("ADRSNFIRSTCHANGE", 11);
define("ADRSNMOVEFIRST", 12);
define("ADRSNMOVENEXT", 13);
define("ADRSNMOVEPREVIOUS", 14);
define("ADRSNMOVELAST", 15);

// SchemaEnum Values
define("ADSCHEMAPROVIDERSPECIFIC", -1);
define("ADSCHEMAASSERTS", 0);
define("ADSCHEMACATALOGS", 1);
define("ADSCHEMACHARACTERSETS", 2);
define("ADSCHEMACOLLATIONS", 3);
define("ADSCHEMACOLUMNS", 4);
define("ADSCHEMACHECKCONSTRAINTS", 5);
define("ADSCHEMACONSTRAINTCOLUMNUSAGE", 6);
define("ADSCHEMACONSTRAINTTABLEUSAGE", 7);
define("ADSCHEMAKEYCOLUMNUSAGE", 8);
define("ADSCHEMAREFERENTIALCONSTRAINTS", 9);
define("ADSCHEMATABLECONSTRAINTS", 10);
define("ADSCHEMACOLUMNSDOMAINUSAGE", 11);
define("ADSCHEMAINDEXES", 12);
define("ADSCHEMACOLUMNPRIVILEGES", 13);
define("ADSCHEMATABLEPRIVILEGES", 14);
define("ADSCHEMAUSAGEPRIVILEGES", 15);
define("ADSCHEMAPROCEDURES", 16);
define("ADSCHEMASCHEMATA", 17);
define("ADSCHEMASQLLANGUAGES", 18);
define("ADSCHEMASTATISTICS", 19);
define("ADSCHEMATABLES", 20);
define("ADSCHEMATRANSLATIONS", 21);
define("ADSCHEMAPROVIDERTYPES", 22);
define("ADSCHEMAVIEWS", 23);
define("ADSCHEMAVIEWCOLUMNUSAGE", 24);
define("ADSCHEMAVIEWTABLEUSAGE", 25);
define("ADSCHEMAPROCEDUREPARAMETERS", 26);
define("ADSCHEMAFOREIGNKEYS", 27);
define("ADSCHEMAPRIMARYKEYS", 28);
define("ADSCHEMAPROCEDURECOLUMNS", 29);
define("ADSCHEMADBINFOKEYWORDS", 30);
define("ADSCHEMADBINFOLITERALS", 31);
define("ADSCHEMACUBES", 32);
define("ADSCHEMADIMENSIONS", 33);
define("ADSCHEMAHIERARCHIES", 34);
define("ADSCHEMALEVELS", 35);
define("ADSCHEMAMEASURES", 36);
define("ADSCHEMAPROPERTIES", 37);
define("ADSCHEMAMEMBERS", 38);

// SeekEnum Values
define("ADSEEKFIRSTEQ", 1);
define("ADSEEKLASTEQ", 2);
define("ADSEEKAFTEREQ", 4);
define("ADSEEKAFTER", 8);
define("ADSEEKBEFOREEQ", 16);
define("ADSEEKBEFORE", 32);

// ADCPROP_UPDATECRITERIA_ENUM Values
define("ADCRITERIAKEY", 0);
define("ADCRITERIAALLCOLS", 1);
define("ADCRITERIAUPDCOLS", 2);
define("ADCRITERIATIMESTAMP", 3);

// ADCPROP_ASYNCTHREADPRIORITY_ENUM Values
define("ADPRIORITYLOWEST", 1);
define("ADPRIORITYBELOWNORMAL", 2);
define("ADPRIORITYNORMAL", 3);
define("ADPRIORITYABOVENORMAL", 4);
define("ADPRIORITYHIGHEST", 5);

// CEResyncEnum Values
define("ADRESYNCNONE", 0);
define("ADRESYNCAUTOINCREMENT", 1);
define("ADRESYNCCONFLICTS", 2);
define("ADRESYNCUPDATES", 4);
define("ADRESYNCINSERTS", 8);
define("ADRESYNCALL", 15);

// ADCPROP_AUTORECALC_ENUM Values
define("ADRECALCUPFRONT", 0);
define("ADRECALCALWAYS", 1);

} // end if (!defined('ADINTEGER'))

// =========================================================================
// SECTION: Language Strings (from language.inc)
// =========================================================================

$lang_access_menu = null;
$lang_access_reports = null;
$lang_change = null;
$lang_closewindow = null;
$lang_geen = null;
$lang_group = null;
$lang_groupsMaintenance = null;
$lang_groups = null;
$lang_group_name = null;
$lang_password = null;
$lang_password_forgotten = null;
$lang_Login = null;
$lang_LoginWelcome = null;
$lang_LoginInvalid = null;
$lang_Logout = null;
$lang_new = null;
$lang_user = null;
$lang_username = null;
$lang_users = null;
$lang_usersMaintenance = null;
$lang_Version = null;
$lang_application = null;
$lang_required_entry = null;
$lang_password_change = null;
$lang_tb_expand = null;
$lang_tb_collapse = null;
$lang_tb_add = null;
$lang_tb_delete = null;
$lang_tb_edit = null;
$lang_tb_copy = null;
$lang_tb_save = null;
$lang_tb_table = null;
$lang_tb_tree = null;
$lang_tb_print = null;
$lang_tb_preview = null;
$lang_tb_refresh = null;
$lang_tb_search = null;
$lang_tb_search_ret = null;
$lang_tb_undo = null;
$lang_grp_collexp = null;
$lang_cropimage = null;
$lang_selimage = null;
$lang_selfile = null;
$lang_clearentry = null;
$lang_viewfile = null;
$lang_viewimage = null;
$lang_viewurl = null;
$lang_wizard_next = null;
$lang_wizard_back = null;
$lang_wizard_finish = null;
$lang_wizard_cancel = null;
$lang_marketing_url = null;
$lang_download_imageupload = null;
$lang_add = null;
$lang_delete = null;
$lang_fullName = null;
$lang_memberof = null;
$lang_notifications = null;
$lang_notifications_self = null;
$lang_relogin_required = null;
$lang_menu_startpage = null;
$lang_welcome = null;
$lang_module_settings = null;
$lang_reports = null;
$lang_save = null;
$lang_back = null;
$lang_copy = null;
$lang_save_and_close = null;

if (Application::get('CMA_language', '') == 'UK') {
    $lang_add = 'Add';
    $lang_delete = 'Delete';
    $lang_save = 'Save';
    $lang_back = 'Back';
    $lang_copy = 'Copy';
    $lang_save_and_close = 'Save and close';
    $lang_menu_startpage = 'Startpage';
    $lang_access_menu = '<b>Menu</b> access rights';
    $lang_access_reports = '<b>Report</b> access rights';
    $lang_change = 'Change';
    $lang_closewindow = 'Close window';
    $lang_geen = '[Not set]';
    $lang_group = 'Group';
    $lang_groups = 'Groups';
    $lang_groupsMaintenance = 'Group (access) maintenance';
    $lang_group_name = 'Group name';
    $lang_password = 'Password';
    $lang_password_forgotten = 'Please contact Stenvers online<br>if you have forgotten your password.';
    $lang_password_change = 'Change ' . $lang_password;
    $lang_fullName = 'Full name';
    $lang_Login = 'Login';
    $lang_LoginWelcome = 'Welcome to the Stenvers online CMA';
    $lang_LoginInvalid = 'Invalid login!';
    $lang_Logout = 'Logout';
    $lang_marketing_url = 'Marketing URL';
    $lang_new = 'New';
    $lang_user = 'User';
    $lang_username = 'Login name';
    $lang_users = 'Users';
    $lang_usersMaintenance = 'User maintenance';
    $lang_Version = 'version';
    $lang_required_entry = 'Required entry';
    $lang_application = 'Application';
    $lang_download_imageupload = 'To crop and upload images you need to install the latest version of the Image Upload Wizard. <BR><a class=downloadlink href=http://download.stenversonline.nl/cma/Toolswizard21.exe>You can download the new Image Upload software here.</a>.';
    $lang_memberof = 'Member of';
    $lang_notifications = 'Notifications';
    $lang_notifications_self = 'Recieve no notifications<BR>for own changes';
    $lang_relogin_required = 'Note: Re-login required';
    $lang_tb_expand = 'Show all';
    $lang_tb_collapse = 'Hide all';
    $lang_tb_add = 'Add a new record';
    $lang_tb_edit = 'Edit this record';
    $lang_tb_copy = 'Copy this record to a new record';
    $lang_tb_save = 'Save this record';
    $lang_tb_delete = 'Delete this record';
    $lang_tb_preview = 'View this record on the site' . PHP_EOL . '(you must save the record first!)';
    $lang_tb_print = 'Print';
    $lang_tb_refresh = 'Refresh list (might take a while)';
    $lang_tb_table = 'Table';
    $lang_tb_tree = 'Tree';
    $lang_tb_search = 'Search';
    $lang_tb_search_ret = 'Return to list view';
    $lang_tb_undo = 'Undo changes';
    $lang_grp_collexp = 'Collapse/Expand entire group';
    $lang_selimage = 'Select and place an image (without cropping)';
    $lang_cropimage = 'Select, crop and upload an image';
    $lang_selfile = 'Select a file';
    $lang_clearentry = 'Clear entry';
    $lang_viewfile = 'Open file to view';
    $lang_viewimage = 'Click to view the actual size of the image';
    $lang_viewurl = 'Click to view the site';
    $lang_wizard_next = 'Next >';
    $lang_wizard_back = '< Back';
    $lang_wizard_finish = 'Finish';
    $lang_wizard_cancel = 'Cancel';
    $lang_welcome = 'Welcome';
    $lang_module_settings = 'Module settings';
    $lang_reports = 'Reports';
} else {
    $lang_add = 'Toevoegen';
    $lang_delete = 'Verwijderen';
    $lang_save = 'Bewaar';
    $lang_back = 'Terug';
    $lang_copy = 'Kopieer';
    $lang_save_and_close = 'Bewaar (en sluit)';
    $lang_menu_startpage = 'Startpagina';
    $lang_access_menu = '<b>Menu</b> rechten';
    $lang_access_reports = '<b>Rapportage</b> rechten';
    $lang_change = 'Wijzigen';
    $lang_closewindow = 'Sluit venster';
    $lang_geen = '[Geen]';
    $lang_group = 'Groep';
    $lang_groupsMaintenance = 'Groepen';
    $lang_groups = 'Groepen';
    $lang_group_name = 'Naam groep';
    $lang_password = 'Wachtwoord';
    $lang_password_forgotten = 'Neem contact op met Stenvers online<br>wanneer je je wachtwoord vergeten bent.';
    $lang_password_change = $lang_password . ' wijzigen';
    $lang_fullName = 'Volledige naam';
    $lang_Login = 'Aanmelden';
    $lang_LoginWelcome = 'Welkom bij de Stenvers online CMA';
    $lang_LoginInvalid = 'Ongeldige login!';
    $lang_Logout = 'Afmelden';
    $lang_marketing_url = 'Marketing URL';
    $lang_new = 'Nieuw';
    $lang_user = 'Gebruiker';
    $lang_username = 'Login naam';
    $lang_users = 'Gebruikers';
    $lang_usersMaintenance = 'Gebruikers';
    $lang_Version = 'versie';
    $lang_required_entry = 'Verplichte invoer';
    $lang_application = 'Applicatie';
    $lang_download_imageupload = 'Om beelden te kunnen bewerken en plaatsen dient u op deze computer de nieuwste versie van de Image Upload Wizard te installeren.<BR><BR><center>[ <a class=downloadlink href=http://download.stenversonline.nl/cma/Toolswizard21.exe>Installeer</a> ]</center><br><br>Kies Uitvoeren en volg de instructies op het scherm. Sluit na installatie dit scherm en open deze opnieuw.';
    $lang_memberof = 'Lid van groep';
    $lang_notifications = 'Notificaties';
    $lang_notifications_self = 'Geen notificatie voor<BR>eigen wijzigingen';
    $lang_relogin_required = 'Let op: opnieuw inloggen is noodzakelijk ';
    $lang_tb_expand = 'Alles tonen';
    $lang_tb_collapse = 'Alles verbergen';
    $lang_tb_add = 'Gegevens toevoegen';
    $lang_tb_edit = 'Gegevens wijzigen';
    $lang_tb_copy = 'Gegevens kopieren naar een nieuw record';
    $lang_tb_save = 'Gegevens opslaan';
    $lang_tb_table = 'Tabel';
    $lang_tb_tree = 'Boomstructuur';
    $lang_tb_delete = 'Gegevens verwijderen';
    $lang_tb_preview = 'Pagina bekijken' . PHP_EOL . '(denk eraan om eerst te bewaren!)';
    $lang_tb_print = 'Afdrukken';
    $lang_tb_refresh = 'Lijst vernieuwen (kan even duren!)';
    $lang_tb_search = 'Gegevens zoeken';
    $lang_tb_search_ret = 'Terug naar lijstweergave';
    $lang_tb_undo = 'Wijzigingen ongedaan maken';
    $lang_grp_collexp = 'Deze groep gegevens tonen/verbergen';
    $lang_selimage = 'Selecteer en plaats een afbeelding (zonder bijsnijden)';
    $lang_cropimage = 'Selecteer, snij en plaats een afbeelding';
    $lang_selfile = 'Selecteer een bestand';
    $lang_clearentry = 'Invoer leegmaken';
    $lang_viewfile = 'Bestand openen om deze te bekijken';
    $lang_viewimage = 'Klik om de afbeelding op' . PHP_EOL . 'ware grootte te bekijken.';
    $lang_viewurl = 'Klik om de site te bekijken';
    $lang_wizard_next = 'Volgende >';
    $lang_wizard_back = '< Terug';
    $lang_wizard_finish = 'Voltooien';
    $lang_wizard_cancel = 'Annuleer';
    $lang_welcome = 'Welkom';
    $lang_module_settings = 'Module instellingen';
    $lang_reports = 'Rapportages';
}

// Copy all lang_ variables to $GLOBALS so they're accessible everywhere
// This is needed because PHP variables set in this scope are local
foreach (get_defined_vars() as $varName => $varValue) {
    if (strpos($varName, 'lang_') === 0) {
        $GLOBALS[$varName] = $varValue;
    }
}

// =========================================================================
// SECTION: Database Connection (from repconn.inc)
// =========================================================================

/**
 * Get repository connection (lazy initialization)
 * @return PDO|resource Repository database connection
 */
function getConnRep() {
    global $connrep;
    if ($connrep === null) {
        $connrep = Database::getRepConnection();
    }
    return $connrep;
}

// For API endpoints, use lazy connection (opened on first use)
// For regular pages, open immediately (they need it anyway)
// Note: SCRIPT_NAME may be wrapper script, so also check REQUEST_URI
$_requestUri = $_SERVER['REQUEST_URI'] ?? '';
$_isApiEndpoint = (stripos($_requestUri, '_api.php') !== false) || (stripos($_requestUri, '/api/') !== false);
$_connrep_debug = [
    'request_uri' => $_requestUri,
    'is_api' => $_isApiEndpoint,
];
if ($_isApiEndpoint) {
    $connrep = null;  // Lazy - will be initialized by getConnRep() or null check
} else {
    $connrep = Database::getRepConnection();  // Eager - regular pages need it
}

// =========================================================================
// SECTION: Login Check (from chk_login.inc)
// =========================================================================

// Skip login check for login.php, default.php, and other entry points
// Use getOriginalScript() to get actual script path (not _bootstrap_wrapper)
$_scriptPath = \App\Library\Request::getOriginalScript() . \App\Library\Request::server('REQUEST_URI', '');
$_excludedPages = [
    'login.php',
    'default.php',
    'copyright.php',
    'logout.php',
    'blank.php',
    'menurep.php',
    'sso_login.php',      // SSO authentication flow
    'sso_callback.php',   // SSO callback from IDP
    'minify.php',         // Static resource handler (CSS/JS)
    'api/test_ip_match.php',  // Test endpoint for IP CIDR matching (env-protected)
];
$_skipLoginCheck = false;
foreach ($_excludedPages as $_excluded) {
    if (stripos($_scriptPath, $_excluded) !== false) {
        $_skipLoginCheck = true;
        break;
    }
}
// Also skip if forcelogin is already set (prevents infinite loop)
if (\App\Library\Request::query('forcelogin', '') !== '') {
    $_skipLoginCheck = true;
}
// Skip login for LLM mode on endpoint tester (returns URL list only)
if (\App\Library\Request::query('llm', '') === 'Y' && stripos($_scriptPath, 'tools_endpoint_tester') !== false) {
    $_skipLoginCheck = true;
}
// Skip login for internal test scripts
if (stripos($_scriptPath, 'test_combo_internal') !== false || stripos($_scriptPath, 'test_all_endpoints') !== false) {
    $_skipLoginCheck = true;
}
// Skip login for test token (used by endpoint tester HTTP requests)
// Only allowed in non-production environments (O=Development, L=Local, T=Test)
$_testToken = \App\Library\Request::server('HTTP_X_CMA_TEST_TOKEN', '');
if ($_testToken === 'cma_endpoint_test_2025' && in_array(strtoupper($_cmaEnv), ['O', 'L', 'T'])) {
    $_skipLoginCheck = true;
    // Look up first admin user from DB and set real auth cookies
    try {
        $_testConn = \App\Library\Database::getConnection('users');
        $_testUser = \App\Library\Database::fetchOne(
            "SELECT TOP 1 ID, userGUID FROM tblUsers WHERE userAdministrator = true OR userLevel >= 1 ORDER BY ID",
            [],
            $_testConn
        );
        if ($_testUser) {
            $_COOKIE[SecurityHelper::COOKIE_USERID] = (string)$_testUser['ID'];
            $_COOKIE[SecurityHelper::COOKIE_USERGUID] = $_testUser['userGUID'] ?? '';
        } else {
            // Fallback: use ID 1 with empty GUID (will pass if DB GUID is also empty)
            $_COOKIE[SecurityHelper::COOKIE_USERID] = '1';
            $_COOKIE[SecurityHelper::COOKIE_USERGUID] = '';
        }
    } catch (\Exception $e) {
        // DB not available yet, use fallback
        $_COOKIE[SecurityHelper::COOKIE_USERID] = '1';
        $_COOKIE[SecurityHelper::COOKIE_USERGUID] = '';
    }
}

// Login check: redirect unauthenticated users to login page
if (!$_skipLoginCheck) {
    if (Cookie::get(SecurityHelper::COOKIE_USERID, '') == '') {
        // Check if this is an API endpoint that expects JSON response
        if ($_isApiEndpoint) {
            // Return JSON error for API endpoints
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Not authenticated', 'requireLogin' => true]);
            exit;
        }
        $_queryString = \App\Library\Request::server('QUERY_STRING', '');
        $sUrl = \App\Library\Request::addToURL('default.php' . ($_queryString !== '' ? '?' . $_queryString : ''), 'forcelogin', 'J');
        echo '<html><script>window.top.location="' . $sUrl . '";</script></html>';
        exit;
    }
}

// =========================================================================
// SECTION: Migration Check
// =========================================================================

// Check for pending migrations (only for admin users on non-API pages)
// This stores pending migrations in a global for optional display in header
$GLOBALS['_pending_migrations'] = [];

if (!$_skipLoginCheck && !$_isApiEndpoint) {
    // Only check if user is admin - use SecurityHelper for robust admin check
    if (SecurityHelper::isAdmin()) {
        try {
            require_once __DIR__ . '/classes/Services/MigrationService.php';
            $migrationService = new \Cma\Services\MigrationService();
            $pendingMigrations = $migrationService->getPendingMigrations();
            if (!empty($pendingMigrations)) {
                $GLOBALS['_pending_migrations'] = $pendingMigrations;
            }
        } catch (\Exception $e) {
            // Silently fail - don't break the application if migration check fails
        }
    }
}

// =========================================================================
// SECTION: Functions
// =========================================================================

// In-memory cache for form definitions
$_formDefCache = [];

/**
 * Get form definition data (from formdef.inc)
 * Returns cached form definition data (uses in-memory cache + file cache)
 */
/**
 * Static cache for formId to formName mapping
 */
$_formIdToNameCache = null;

/**
 * Load formId to formName mapping from JSON definitions
 */
function loadFormIdToNameMapping(): array
{
    global $_formIdToNameCache;

    if ($_formIdToNameCache !== null) {
        return $_formIdToNameCache;
    }

    $_formIdToNameCache = [];

    // Scan both internal (CMA system) and app-specific form directories
    $directories = [
        __DIR__ . '/assets/forms/definitions',  // Internal/system forms
        __DIR__ . '/../assets/forms',           // App-specific forms
    ];

    foreach ($directories as $definitionsDir) {
        if (!is_dir($definitionsDir)) {
            continue;
        }

        $jsonFiles = glob($definitionsDir . '/*.json');
        foreach ($jsonFiles as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }
            $formDef = json_decode($content, true);
            if ($formDef && isset($formDef['sourceFormId'])) {
                $formName = basename($file, '.json');
                // Don't override existing mappings (internal forms take precedence)
                if (!isset($_formIdToNameCache[$formDef['sourceFormId']])) {
                    $_formIdToNameCache[$formDef['sourceFormId']] = $formName;
                }
            }
        }
    }

    return $_formIdToNameCache;
}

/**
 * Get form definition by form ID
 * Uses JSON definitions exclusively (no database fallback)
 */
function GetFormDef($sFormID)
{
    $debugMode = (\App\Library\Request::query('debug', '') != '');
    $noCache = (\App\Library\Request::query('nocache', '') != '');

    // Skip in-memory cache if nocache requested
    if (!$noCache && isset($_formDefCache[$sFormID])) {
        if ($debugMode) echo '<!-- GetFormDef: returning from memory cache -->';
        return $_formDefCache[$sFormID];
    }

    // Look up form name from JSON definitions using sourceFormId
    $formIdToName = loadFormIdToNameMapping();
    $formName = $formIdToName[$sFormID] ?? null;

    if ($debugMode) {
        echo '<!-- GetFormDef: looking up FormID=' . htmlspecialchars($sFormID) . ' -->';
        echo '<!-- GetFormDef: form name found: ' . ($formName ?? 'none') . ' -->';
    }

    if ($formName === null) {
        if ($debugMode) {
            echo '<!-- GetFormDef: no JSON form found for FormID=' . htmlspecialchars($sFormID) . ' -->';
        }
        $_formDefCache[$sFormID] = null;
        return null;
    }

    // Load from JSON (JsonFormLoader::load returns legacy Q_* format)
    $result = \Cma\JsonFormLoader::load($formName);

    if ($debugMode) {
        echo '<!-- GetFormDef result type: ' . gettype($result) . ' -->';
        if (Arr::isArray($result)) {
            $cnt = Arr::isArray($result) ? count($result) : (method_exists($result, 'count') ? $result->count() : '?');
            echo '<!-- GetFormDef result count: ' . $cnt . ' -->';
        }
    }

    $_formDefCache[$sFormID] = $result;
    return $result;
}

/**
 * Get subform array for a form ID
 * Uses JSON definitions exclusively (no database fallback)
 */
function SubFormGetArray($sFormID)
{
    $formName = null;
    $formDef = null;

    // Check if $sFormID is a string form name (not numeric)
    if (is_string($sFormID) && !is_numeric($sFormID)) {
        // Direct form name - try to load it
        $formName = $sFormID;
        $formDef = \Cma\JsonFormLoader::loadRaw($formName);
    } else {
        // Numeric ID - look up form name from JSON definitions
        $formIdToName = loadFormIdToNameMapping();
        $formName = $formIdToName[$sFormID] ?? null;

        if ($formName !== null) {
            $formDef = \Cma\JsonFormLoader::loadRaw($formName);
        }
    }

    if ($formDef === null || empty($formDef['subforms'])) {
        return null;
    }

    // Sort subforms by order property (if present)
    $subforms = $formDef['subforms'];
    usort($subforms, function($a, $b) {
        $orderA = $a['order'] ?? 999;
        $orderB = $b['order'] ?? 999;
        return $orderA - $orderB;
    });

    // Build legacy array format
    $result = [
        SUBFORM_TABLE => [],
        SUBFORM_ID => [],
        SUBFORM_CONN => [],
        SUBFORM_IDFLD => [],
        SUBFORM_QUERY => [],
        SUBFORM_NAME => [],
        SUBFORM_DETAIL => [],
        SUBFORM_PARENT => [],
        SUBFORM_SECUSER => [],
        SUBFORM_MENUNEW => [],
        SUBFORM_GROUPFLD => [],
        SUBFORM_FULLWIDTH => [],
        SUBFORM_ACTIE => [],
        SUBFORM_BEHEER => [],
        SUBFORM_JSONFORM => [], // JSON form name for use when sourceFormId is 0
    ];

    foreach ($subforms as $subform) {
        $subformFile = $subform['form'] ?? $subform['name'] ?? '';
        $subformDef = \Cma\JsonFormLoader::loadRaw($subformFile);

        // If JSON not found by name, try to load via sourceFormId mapping
        if ($subformDef === null && !empty($subform['sourceFormId'])) {
            $formIdToName = loadFormIdToNameMapping();
            $mappedName = $formIdToName[$subform['sourceFormId']] ?? null;
            if ($mappedName !== null) {
                $subformDef = \Cma\JsonFormLoader::loadRaw($mappedName);
                if ($subformDef !== null) {
                    $subformFile = $mappedName;
                }
            }
        }

        // If still no JSON def, use a minimal config from parent's subform entry
        // This enables tabs to show even when subform JSON doesn't exist
        if ($subformDef === null) {
            if (empty($subform['sourceFormId'])) {
                // No sourceFormId and no JSON - skip this subform
                continue;
            }
            // Create minimal subform definition for database-backed forms
            $subformDef = [
                'name' => $subformFile ?: $subform['name'] ?? '',
                'title' => $subform['title'] ?? '',
                'sourceFormId' => $subform['sourceFormId'],
                'table' => '',  // Will be loaded from database
                'database' => $formDef['database'] ?? '',  // Inherit from parent
                'idField' => 'ID',
                'listQuery' => '',  // Will use default query from SubformService
                'allowAdd' => $subform['allowAdd'] ?? true,
                'allowDelete' => $subform['allowDelete'] ?? true,
            ];
        }

        // Get database connection string from database ID
        $databaseId = $subformDef['database'] ?? '';
        $connString = '';
        if (!empty($databaseId)) {
            $connString = \Cma\CmaRepository::getConnectionStringById((int)$databaseId);
        }

        // Build NameQuery from JSON (used for listing subform records)
        $table = $subformDef['table'] ?? '';
        $idField = $subformDef['idField'] ?? 'ID';
        $detailField = $subformDef['detailField'] ?? $subformDef['nameField'] ?? 'ID';
        $groupField = $subformDef['groupField'] ?? '';

        // Get parentField - priority order:
        // 1. Explicit parentField in subform config (from parent form)
        // 2. Explicit parentField in subform definition
        // 3. Parent form's filterIdName
        // 4. Auto-derive from table name
        $parentField = $subform['parentField'] ?? $subformDef['parentField'] ?? '';
        if (empty($parentField)) {
            // Try parent form's filterIdName first
            if (!empty($formDef['filterIdName'])) {
                $parentField = $formDef['filterIdName'];
            } else {
                // Auto-derive: look for FK field in subform that references parent table
                $parentTable = $formDef['table'] ?? '';
                if (!empty($parentTable)) {
                    // Remove 'tbl' prefix and create FK field name variations
                    $baseName = preg_replace('/^tbl/i', '', $parentTable);
                    // Try variations: exact, singular (remove trailing 's'), singular (remove trailing 'en')
                    $variations = [
                        'fk' . $baseName,                                    // fkOpleidingenBlokken
                        'fk' . rtrim($baseName, 's'),                        // fkOpleidingenBlokken -> fkOpleidingenBlokken (no change if no 's')
                        'fk' . preg_replace('/en$/i', '', $baseName),        // fkOpleidingenBlokken -> fkOpleidingBlokk
                        'fk' . preg_replace('/(en|s)$/i', '', $baseName),    // fkOpleidingenBlokken -> fkOpleidingBlokk
                    ];
                    // Also try singular of the parent table name without compound suffix
                    // e.g., tblOpleidingenBlokken -> fkOpleidingBlok, fkBlok
                    if (preg_match('/^(.+?)([A-Z][a-z]+)$/u', $baseName, $matches)) {
                        // Last word: Blokken -> Blok
                        $lastWord = preg_replace('/(en|ken|s)$/i', '', $matches[2]);
                        $variations[] = 'fk' . preg_replace('/(en|s)$/i', '', $matches[1]) . $lastWord;
                        $variations[] = 'fk' . $lastWord;
                    }
                    // Search subform fields for any matching FK
                    foreach ($subformDef['fields'] ?? [] as $field) {
                        $fieldName = $field['name'] ?? '';
                        foreach ($variations as $variation) {
                            if (strcasecmp($fieldName, $variation) === 0) {
                                $parentField = $fieldName;
                                break 2;
                            }
                        }
                        // Also check if sourceTable matches parent table
                        $sourceTable = $field['sourceTable'] ?? '';
                        if (!empty($sourceTable) && strcasecmp($sourceTable, $parentTable) === 0) {
                            $parentField = $fieldName;
                            break;
                        }
                    }
                }
            }
        }

        // Get listQuery (for subform table display) - try listQuery first, then nameQuery for backward compatibility
        $nameQuery = $subformDef['listQuery'] ?? $subformDef['nameQuery'] ?? '';
        // Only build SQL query if this is NOT a JSON config form
        // JSON config forms (database: "json") are handled by ConfigFormService, not SQL
        $isJsonConfigForm = ($subformDef['database'] ?? '') === 'json';
        if (empty($nameQuery) && !empty($table) && !$isJsonConfigForm) {
            $nameQuery = "SELECT * FROM [$table]";
        }

        $result[SUBFORM_TABLE][] = $table;
        $result[SUBFORM_ID][] = $subformDef['sourceFormId'] ?? $subform['sourceFormId'] ?? 0;
        $result[SUBFORM_CONN][] = $connString;
        $result[SUBFORM_IDFLD][] = $idField;
        $result[SUBFORM_QUERY][] = $nameQuery;
        $result[SUBFORM_NAME][] = $subform['title'] ?? $subformDef['title'] ?? '';
        $result[SUBFORM_DETAIL][] = $detailField;
        $result[SUBFORM_PARENT][] = $parentField;
        $result[SUBFORM_SECUSER][] = $subformDef['securityByUser'] ?? false;
        $result[SUBFORM_MENUNEW][] = $subformDef['allowAdd'] ?? true;
        $result[SUBFORM_GROUPFLD][] = $groupField;
        $result[SUBFORM_FULLWIDTH][] = $subformDef['fullWidth'] ?? false;
        $result[SUBFORM_ACTIE][] = $subformDef['action'] ?? '';
        $result[SUBFORM_BEHEER][] = false; // adminOnly feature removed in 5.4.0
        $result[SUBFORM_JSONFORM][] = $subformFile; // JSON form name for use in subform loading
    }

    return empty($result[SUBFORM_ID]) ? null : $result;
}

/**
 * Add a main tab item (from subform.inc)
 */
function AddMainItem($ID, $strText, $blnPrint)
{
    if ($blnPrint) {
        echo '<B>' . $strText . '</B><BR>';
    } else {
        $myTabRow->AddTab($ID, $strText, 'Ga naar dit subformulier', '');
    }
}

/**
 * Write all subforms for a form (from subform.inc)
 */
function WriteSubForms($sFormID, $sParentID, $blnPrint)
{
    global $lang_tb_add;
    $arrFormDetails = array();
    $nCurRights = SecurityHelper::checkFormRights((int) Cookie::get(SecurityHelper::COOKIE_USERID, ''), (int) $sFormID);
    ToolbarHelper::writeJS();

    if ($sParentID != '') {
        $arrFormDetails = SubFormGetArray($sFormID);
        if (Arr::isArray($arrFormDetails)) {
            for ($tel = 0; $tel <= (count($arrFormDetails) - 1); $tel++) {
                $nCurRights = SecurityHelper::checkFormRights((int) Cookie::get(SecurityHelper::COOKIE_USERID, ''), (int) $arrFormDetails[SUBFORM_ID][$tel]);
                if ($nCurRights > SecurityHelper::ACCESS_NONE && ($nCurRights == SecurityHelper::ACCESS_FULL_BEHEER || !$arrFormDetails[SUBFORM_BEHEER][$tel])) {
                    if ($blnPrint) {
                        echo PHP_EOL . '<div>';
                    } else {
                        echo PHP_EOL . '<div id=form' . $tel . ' style=display:none class=scroll>' . PHP_EOL;
                    }
                    $sFormDataSQL = $arrFormDetails[SUBFORM_QUERY][$tel];
                    if ($arrFormDetails[SUBFORM_PARENT][$tel] == "") {
                        // Check if SQL has [id] placeholder - if not and we have a parentId, config is incomplete
                        if (!empty($sParentID) && stripos($sFormDataSQL, '[id]') === false) {
                            $subformName = $arrFormDetails[SUBFORM_NAME][$tel] ?? '';
                            $jsonFormName = $arrFormDetails[SUBFORM_JSONFORM][$tel] ?? '';
                            \Cma\Services\Logger::error('Subform configuration error: parentField is empty but parentId is provided', [
                                'parentFormId' => $sFormID,
                                'subformName' => $subformName,
                                'subformIndex' => $tel,
                                'jsonFormName' => $jsonFormName,
                                'parentId' => $sParentID,
                            ]);
                            throw new \Exception("Subformulier '$subformName' heeft geen parentField geconfigureerd. Voeg parentField toe aan de subform definitie in het parent form.");
                        }
                        $sFormDataSQL = str_replace('[id]', $sParentID, $sFormDataSQL);
                    } else {
                        $sFormDataSQL = SQL::addWhere($sFormDataSQL, $arrFormDetails[SUBFORM_PARENT][$tel] . '=' . $sParentID);
                    }
                    $connString = $arrFormDetails[SUBFORM_CONN][$tel] ?? '';
                    $FormDataRs = Database::openRS($sFormDataSQL, CmaRepository::resolveConnectionString($connString), adOpenKeyset);
                    if ($FormDataRs === null) {
                        throw new \Exception('Database query failed: ' . Database::getLastError() . ' | SQL: ' . substr($sFormDataSQL, 0, 500));
                    }
                    $FormDataRs_current_row = $FormDataRs->fetch(PDO::FETCH_ASSOC);
                    if (Application::get('local', '') || Application::get('test', '')) {
                        if (!$FormDataRs->eof) {
                            if (!Database::fieldInRecordset($FormDataRs, $arrFormDetails[SUBFORM_DETAIL][$tel])) {
                                Error::show($arrFormDetails[SUBFORM_DETAIL][$tel] . ' not found in form query of ' . $arrFormDetails[SUBFORM_NAME][$tel]);
                                exit();
                            }
                        }
                    }
                    $sTabName = Server::htmlEncode($arrFormDetails[SUBFORM_NAME][$tel]);
                    if ($arrFormDetails[SUBFORM_BEHEER][$tel]) {
                        $arrFormDetails[SUBFORM_ACTIE][$tel] = 'beheer';
                    }
                    $sTabName .= ($arrFormDetails[SUBFORM_ACTIE][$tel] != '' ? '<span class=actie title="' . $arrFormDetails[SUBFORM_ACTIE][$tel] . '"></span>' : '');
                    if ($FormDataRs_current_row !== false) {
                        $sTabName .= '<span class="have_data">' . $FormDataRs->RecordCount . '</span>';
                    }
                    AddMainItem($tel, $sTabName, $blnPrint);
                    if (!$blnPrint) {
                        ToolbarHelper::start(false);
                        if ($nCurRights >= SecurityHelper::ACCESS_FULL) {
                            ToolbarHelper::linearButton('<a id="toolbar_new" data-tooltip=\'' . $lang_tb_add . "' href=\"javascript:w('" . $arrFormDetails[SUBFORM_ID][$tel] . "','','" . $arrFormDetails[SUBFORM_PARENT][$tel] . "','" . $sParentID . "','Toevoegen record bij " . Server::htmlEncode($arrFormDetails[SUBFORM_NAME][$tel]) . "'," . ($arrFormDetails[SUBFORM_FULLWIDTH][$tel] ? 'true' : 'false') . ')">', '<span class="lnr lnr-file-add"></span>', $arrFormDetails[SUBFORM_MENUNEW][$tel], 'Voeg toe');
                        }
                        ToolbarHelper::end(false);
                    }
                    if (!$FormDataRs->eof) {
                        $Lijst = new LibTable();
                        $Lijst->TableRS = $FormDataRs;
                        $Lijst->ID = $arrFormDetails[SUBFORM_ID][$tel];
                        $Lijst->IDField = $arrFormDetails[SUBFORM_IDFLD][$tel];
                        $Lijst->Name = $arrFormDetails[SUBFORM_NAME][$tel];
                        $Lijst->ShowIDField = false;
                        if ($arrFormDetails[SUBFORM_GROUPFLD][$tel] != '') {
                            $Lijst->GroupField = $arrFormDetails[SUBFORM_GROUPFLD][$tel];
                        }
                        $Lijst->RowsPerPage = 9999999;
                        $Lijst->Filtered = true;
                        $Lijst->DefaultSortCol = -1;
                        $Lijst->OnClick = "javascript:w('" . $arrFormDetails[SUBFORM_ID][$tel] . "','[ID]','" . $arrFormDetails[SUBFORM_PARENT][$tel] . "','" . $sParentID . "','" . Server::htmlEncode($arrFormDetails[SUBFORM_NAME][$tel]) . " | '+$(this).closest('tr').find('td:first-child')[0].innerText," . ($arrFormDetails[SUBFORM_FULLWIDTH][$tel] ? 'true' : 'false') . ')';
                        $Lijst->ShowCaptions = (Arr::isArray($FormDataRs->fields) ? count(array_filter(array_keys($FormDataRs->fields), 'is_string')) : 0) > 2;
                        $Lijst->Render();
                        $Lijst = null;
                    }
                    $FormDataRs = null;
                    echo '</div>';
                }
            }
        }
    }
}

/**
 * VBScript-style array access for $arrRep (form definition array)
 */
function arrRep($column, $row = 0)
{
    global $arrRep;
    if (!isset($arrRep[$column])) {
        return null;
    }
    return $arrRep[$column][$row] ?? null;
}

/**
 * VBScript-style array access for $arrSubRep (subform definition array)
 */
function arrSubRep($column, $row = 0)
{
    if (!isset($arrSubRep[$column])) {
        return null;
    }
    return $arrSubRep[$column][$row] ?? null;
}

/**
 * VBScript-style array access for $arrSubForms (subform list array)
 */
function arrSubForms($column, $row = 0)
{
    if (!isset($arrSubForms[$column])) {
        return null;
    }
    return $arrSubForms[$column][$row] ?? null;
}

// =========================================================================
// SECTION: Tools Helper Functions (deprecated wrappers)
// =========================================================================

/**
 * Wrapper function for CmaRepository::renderDatabaseSelect
 * @deprecated Use CmaRepository::renderDatabaseSelect() directly
 */
function ToolsDatabaseSelect($defaultId, bool $enabled, bool $includeRepository, bool $autoSubmit): string
{
    return CmaRepository::renderDatabaseSelect($defaultId, $enabled, $includeRepository, $autoSubmit);
}

/**
 * Wrapper function for rendering a table select dropdown
 * @deprecated Consider using a proper class-based approach
 */
function ToolsTableSelect($name, $conn, $autoSubmit, $default): void
{
    $disabledAttr = '';
    $onChangeAttr = $autoSubmit ? ' onchange="document.forms.main.submit()" ' : '';

    echo '<SELECT style="width:150px" id="' . htmlspecialchars($name) . '" name="' . htmlspecialchars($name) . '"' . $disabledAttr . $onChangeAttr . '>';
    echo '<OPTION VALUE=""></OPTION>';

    try {
        // Get tables via centralized SchemaHelper (handles filtering of system/hidden tables)
        $tables = \Cma\SchemaHelper::getTables($conn);
        foreach ($tables as $table) {
            $tableName = $table['name'];
            $selected = (strtolower($tableName) == strtolower($default)) ? ' selected' : '';
            echo '<OPTION VALUE="' . htmlspecialchars($tableName) . '"' . $selected . '>' . htmlspecialchars($tableName) . '</OPTION>';
        }
    } catch (\Exception $e) {
        // Silently fail - tables list not available
    }

    echo '</SELECT>';
}

/**
 * Wrapper functie voor het renderen van een module selectielijst
 *
 * @deprecated Modules worden niet meer gebruikt. Deze functie blijft bestaan voor
 * backwards compatibiliteit met verouderde tools pagina's. Niet gebruiken in nieuwe code.
 */
function ToolsModuleSelect($databaseId, $default): void
{
    global $connrep;

    // Toon waarschuwing in niet-productie omgevingen
    if (\App\Library\Application::get('environment', 'P') !== 'P') {
        trigger_error('ToolsModuleSelect() is verouderd. Modules worden niet meer gebruikt.', E_USER_DEPRECATED);
    }

    // Lazy initialize connection
    if ($connrep === null) {
        $connrep = Database::getRepConnection();
    }

    echo '<SELECT style="width:184px" id="module" name="module">';
    echo '<OPTION VALUE="">-- Niet meer beschikbaar --</OPTION>';
    echo '</SELECT>';
    echo '<small style="color:#999;margin-left:8px">Modules zijn verouderd</small>';
}

/**
 * Wrapper function for rendering a field type select dropdown
 * @deprecated Consider using a proper class-based approach
 */
function ToolsFieldTypeSelect($name, $default): void
{
    $types = [
        3 => 'Integer',
        5 => 'Double',
        6 => 'Currency',
        7 => 'Date',
        11 => 'Boolean',
        202 => 'Text',
        203 => 'Memo'
    ];

    echo '<SELECT style="width:150px" id="' . htmlspecialchars($name) . '" name="' . htmlspecialchars($name) . '">';
    echo '<OPTION VALUE=""></OPTION>';

    foreach ($types as $typeId => $typeName) {
        $selected = ($typeId == $default) ? ' selected' : '';
        echo '<OPTION VALUE="' . $typeId . '"' . $selected . '>' . htmlspecialchars($typeName) . '</OPTION>';
    }

    echo '</SELECT>';
}

/**
 * Wrapper function for rendering a control type select dropdown
 * @deprecated Consider using a proper class-based approach
 */
function ToolsControlSelect($name, $allowedTypes, $enabled, $default): void
{
    $allTypes = FormControlHelper::getControlTypes();
    $allowed = explode(',', $allowedTypes);

    $disabledAttr = $enabled ? '' : ' disabled';

    echo '<SELECT style="width:120px" id="' . htmlspecialchars($name) . '" name="' . htmlspecialchars($name) . '"' . $disabledAttr . '>';

    foreach ($allowed as $typeId) {
        $typeId = trim($typeId);
        $typeName = $allTypes[$typeId] ?? 'Type ' . $typeId;
        $selected = ($typeId == $default) ? ' selected' : '';
        echo '<OPTION VALUE="' . htmlspecialchars($typeId) . '"' . $selected . '>' . htmlspecialchars($typeName) . '</OPTION>';
    }

    echo '</SELECT>';
}
