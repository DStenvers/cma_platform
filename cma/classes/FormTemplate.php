<?php

namespace Cma;

use App\Library\Application;
use App\Library\Arr;
use App\Library\Cache;
use App\Library\Cookie;
use App\Library\Database;
use App\Library\Request;
use App\Library\Server;
use Cma\AdoType;
use Cma\CmaRepository;
use Cma\Services\ListServiceHelper;
use Cma\Services\Logger;
use Cma\Services\PerformanceLogger;

// Ensure JsonFormLoader and JsonFormRenderer are available
require_once __DIR__ . '/JsonFormLoader.php';
require_once __DIR__ . '/JsonFormRenderer.php';
require_once __DIR__ . '/HtmlHelper.php';
require_once __DIR__ . '/Services/PerformanceLogger.php';

/**
 * CMA Form Template Generator
 *
 * Generates static HTML templates for forms that can be cached in APCu
 * and stored in cma/assets/forms/ for instant serving.
 *
 * The templates contain:
 * - List panel structure with placeholders for data
 * - Detail panel with empty form controls
 * - Subform tabs structure
 * - Toolbar buttons based on permissions
 *
 * Data is populated via JavaScript using AJAX calls to FormDataProvider.
 */
class FormTemplate
{
    /**
     * Cache key prefix for templates
     */
    private const CACHE_PREFIX = 'CMA_form_template_';

    /**
     * Cache group for invalidation
     */
    private const CACHE_GROUP = 'forms';

    /**
     * Directory for storing static templates (cached HTML)
     * Uses site root cache: /site/cache/cma/forms/
     */
    private const TEMPLATE_DIR = __DIR__ . '/../../cache/cma/forms';

    /**
     * @var int Source Form ID (from JSON definition's sourceFormId, used for subforms and permissions)
     */
    private int $sourceFormId;

    /**
     * @var FormDefinition Form definition
     */
    private FormDefinition $formDef;

    /**
     * @var array|null Raw form definition array
     */
    private array|\ArrayAccess|null $arrRep = null;

    /**
     * @var array Subform definitions
     */
    private array|\ArrayAccess|null $arrSubForms = null;

    /**
     * @var int Current access rights level
     */
    private int $accessLevel;

    /**
     * @var bool Whether toolbar filter combo has options (determines if filter field shows in search panel)
     */
    private bool $toolbarFilterHasOptions = false;

    /**
     * @var string|null JSON form name (e.g., 'users', 'opleidingen')
     */
    private ?string $jsonFormName = null;

    /**
     * Private constructor - use static methods
     */
    private function __construct()
    {
        $this->sourceFormId = 0;
    }

    /**
     * Get a form template for a JSON-defined form (from cache or generate)
     *
     * @param string $formName Form name (e.g., 'users', 'groups')
     * @param int $accessLevel User's access level (affects toolbar buttons)
     * @return string HTML template
     */
    public static function getForJsonForm(string $formName, int $accessLevel = SecurityHelper::ACCESS_FULL): string
    {
        $cacheKey = self::CACHE_PREFIX . 'json_' . $formName . '_' . $accessLevel;
        $noCache = Request::hasQuery('nocache') || Request::hasQuery('refresh');

        if (!$noCache) {
            // Try APCu cache with invalidation support
            $cached = Cache::getWithInvalidation($cacheKey, self::CACHE_GROUP);
            if ($cached !== null) {
                PerformanceLogger::log('cache', 'jsonFormTemplate_apcu_hit', 0, ['formName' => $formName]);
                return $cached;
            }

            // Try file cache (validate against JSON definition mtime)
            $filePath = self::TEMPLATE_DIR . '/form_json_' . $formName . '_' . $accessLevel . '.html';
            if (file_exists($filePath)) {
                $jsonDefPath = JsonFormLoader::getFilePath($formName);
                $cacheValid = !$jsonDefPath || !file_exists($jsonDefPath) || filemtime($filePath) >= filemtime($jsonDefPath);
                if ($cacheValid) {
                    $template = file_get_contents($filePath);
                    // Store in APCu for faster subsequent access
                    Cache::setWithInvalidation($cacheKey, $template, self::CACHE_GROUP, 86400);
                    PerformanceLogger::log('cache', 'jsonFormTemplate_file_hit', 0, ['formName' => $formName]);
                    return $template;
                }
            }
        }

        // Generate new template from JSON definition
        PerformanceLogger::startTimer('jsonFormTemplate_generate');
        $filePath = self::TEMPLATE_DIR . '/form_json_' . $formName . '_' . $accessLevel . '.html';
        $generator = new self();
        $template = $generator->generateFromJson($formName, $accessLevel);
        PerformanceLogger::endTimer('jsonFormTemplate_generate', ['formName' => $formName, 'size' => strlen($template)]);

        // Cache in APCu with invalidation support (also updates if nocache was used)
        Cache::setWithInvalidation($cacheKey, $template, self::CACHE_GROUP, 86400);

        // Save to file
        @mkdir(dirname($filePath), 0755, true);
        file_put_contents($filePath, $template);

        return $template;
    }

    /**
     * Generate template from JSON form definition
     *
     * @param string $formName Form name
     * @param int $accessLevel User access level
     * @return string HTML template
     */
    private function generateFromJson(string $formName, int $accessLevel): string
    {
        $this->accessLevel = $accessLevel;
        $this->jsonFormName = $formName; // Store JSON form name for hidden field

        // Load JSON definition and convert to legacy format
        $legacyFormat = JsonFormLoader::load($formName);
        if ($legacyFormat === null) {
            return $this->generateErrorTemplate("JSON form '$formName' not found");
        }

        $this->arrRep = $legacyFormat;
        $this->formDef = FormDefinition::fromArray($legacyFormat);

        if (!$this->formDef->isValid()) {
            return $this->generateErrorTemplate("Invalid form definition for '$formName'");
        }

        // Get sourceFormId from JSON - this allows unified handling with database forms
        $jsonData = $legacyFormat['_json'] ?? [];
        $sourceFormId = $jsonData['sourceFormId'] ?? null;

        // Store form ID for API calls and subform loading
        $this->sourceFormId = $sourceFormId ? (int)$sourceFormId : 0;

        // Load subforms using sourceFormId (same as database forms)
        if ($this->sourceFormId > 0) {
            $this->arrSubForms = \SubFormGetArray($this->sourceFormId);
        } elseif (!empty($jsonData['subforms'])) {
            // For JSON config forms without sourceFormId, build subform array from JSON definition
            $this->arrSubForms = $this->buildSubformsFromJson($jsonData['subforms']);
        } else {
            $this->arrSubForms = null;
        }

        $html = $this->generateHeaderForJson($formName);
        $html .= $this->generateBody();

        return $html;
    }

    /**
     * Generate HTML header section for JSON form
     *
     * @param string $formName Form name
     * @return string HTML header
     */
    private function generateHeaderForJson(string $formName): string
    {
        $jsonData = $this->arrRep['_json'] ?? [];
        $title = $jsonData['title'] ?? $formName;

        $html = HtmlHelper::htmlStart($title . ' - CMA');

        // Determine which JS libraries are needed based on control types
        $needsCKEditor = $this->hasControlType([FormRenderer::TYPE_MEMO]) && $this->hasMemoWithHtml();

        // CSS/JS - use centralized bundles for consistent browser caching
        $cssUrl = cma_css_url();
        $jsUrl = cma_form_js_url();

        // PERFORMANCE: Preload critical resources for faster rendering
        $html .= '<link rel="preload" href="' . $cssUrl . '" as="style">' . PHP_EOL;
        $html .= '<link rel="preload" href="../library/jquery.min.js" as="script">' . PHP_EOL;

        $html .= '<link rel="stylesheet" href="' . $cssUrl . '">' . PHP_EOL;

        // JavaScript - These are skipped by main.js executeScripts() when loading via AJAX
        // but included for standalone page loads (e.g., popup windows)
        // CRITICAL: main.php already loads error-handler.js before jQuery and bundle
        $html .= '<script src="../library/jquery.min.js"></script>' . PHP_EOL;
        $html .= '<script src="' . $jsUrl . '"></script>' . PHP_EOL;

        // CKEditor is large and should be loaded separately with defer to avoid blocking
        if ($needsCKEditor) {
            $html .= '<script src="ckeditor/ckeditor.js" defer></script>' . PHP_EOL;

            // Include blockedit.js for content blocks if available
            // Content blocks allow structured content editing with predefined block types
            if (FormRenderer::hasContentBlocks() && $this->hasMemoWithBlockEdit()) {
                $html .= '<script src="assets/js/blockedit.js" defer></script>' . PHP_EOL;
            }
        }

        // Build comprehensive config for JSON forms
        // sourceFormId is used for subforms and permission checks (references tblForms.ID)
        // formName = plural title (e.g., "Opleidingen") for table headers
        // formNameSingular = singular title (e.g., "Opleiding") for add/details actions
        $titleSingular = $jsonData['titleSingular'] ?? $title;
        $config = [
            'sourceFormId' => $this->sourceFormId, // For subforms and permission checks
            'formName' => $title,
            'formNameSingular' => $titleSingular,
            'jsonForm' => $formName, // JSON form name for JSON-specific API calls
            'tableName' => $jsonData['table'] ?? $this->formDef->getSqlTableName(),
            'idField' => $jsonData['idField'] ?? $this->formDef->getFormIdField(),
            'hasSubforms' => Arr::isArray($this->arrSubForms) || $this->arrSubForms instanceof \ArrayAccess,
            'accessLevel' => $this->accessLevel,
            'canAdd' => ($jsonData['allowAdd'] ?? true) && $this->accessLevel >= SecurityHelper::ACCESS_FULL,
            'canDelete' => ($jsonData['allowDelete'] ?? true) && $this->accessLevel >= SecurityHelper::ACCESS_FULL,
            'canCopy' => ($jsonData['allowCopy'] ?? false) && $this->accessLevel >= SecurityHelper::ACCESS_FULL,
            'storeLastModified' => $this->formDef->hasStoreLastModified(),
            'previewUrl' => $this->arrRep[\Q_PREVIEWURL][0] ?? '',
            'filterIdName' => $this->formDef->getFilterIdName(),
            'filterFieldName' => $this->formDef->getFilterFieldName(),
            'language' => Application::get('CMA_Language', 'NL'),
            'basePath' => Application::get('base_path', ''),
            'domain' => Request::currentDomain(),
            'debug' => (bool) Application::get('development', ''),
            'showDetails' => SecurityHelper::isAdmin() || SecurityHelper::isDeveloper(),
            'appName' => Services\MenuService::getApplicationValue('name', Application::get('appname', '')),
            'imageConfig' => [
                'resize_type' => FormControlHelper::IMG_MAXIMUM,
                'max_width' => Application::get('cma_htmledit_img_maxwidth', 800),
                'max_height' => Application::get('cma_htmledit_img_maxheight', 600),
                'path' => Application::get('cma_htmledit_img_path', ''),
            ],
            'editorConfig' => [
                'allowBR' => !Application::get('cma_htmledit_allowBR', ''),
                'customCSS' => Application::get('cma_htmledit_css', ''),
                'extraPlugins' => stripos(Services\MenuService::getApplicationValue('name', Application::get('appname', '')), 'rino') !== false ? ',literatuur' : '',
            ],
        ];

        // Store form config in CMA namespace (not global window.CMA_FORM_CONFIG)
        // Use JSON_THROW_ON_ERROR in PHP 7.3+ or check for encoding failure
        $configJson = json_encode($config, JSON_UNESCAPED_UNICODE);
        if ($configJson === false) {
            // JSON encoding failed - create minimal valid config and log error
            Logger::error('FormTemplate: json_encode failed for config', ['error' => json_last_error_msg()]);
            $configJson = json_encode([
                'error' => 'Config encoding failed',
                'jsonForm' => $this->jsonFormName,
                'formName' => $formName,
            ]);
        }
        $html .= '<script>window.CMA = window.CMA || {}; CMA.formConfig = ' . $configJson . ';</script>' . PHP_EOL;
        $html .= '</head>' . PHP_EOL;

        return $html;
    }

    /**
     * Invalidate a specific JSON form template
     *
     * @param string $formName JSON form name
     */
    public static function invalidateJsonForm(string $formName): void
    {
        // Invalidate all access level variants
        foreach ([
            SecurityHelper::ACCESS_NONE,
            SecurityHelper::ACCESS_READ,
            SecurityHelper::ACCESS_CHANGE_OWN_DATA,
            SecurityHelper::ACCESS_FULL,
            SecurityHelper::ACCESS_FULL_BEHEER
        ] as $level) {
            $cacheKey = self::CACHE_PREFIX . 'json_' . $formName . '_' . $level;
            Cache::delete($cacheKey);

            // Delete file
            $filePath = self::TEMPLATE_DIR . '/form_json_' . $formName . '_' . $level . '.html';
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        // Also invalidate the cache group
        Cache::invalidateGroup(self::CACHE_GROUP);
    }

    /**
     * Invalidate all form templates
     */
    public static function invalidateAll(): void
    {
        // Invalidate cache group
        Cache::invalidateGroup(self::CACHE_GROUP);

        // Delete all template files
        $dir = self::TEMPLATE_DIR;
        if (is_dir($dir)) {
            $files = glob($dir . '/form_*.html');
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        // Update manifest
        self::updateManifest([]);
    }

    /**
     * Generate HTML header section
     */
    private function generateHeader(): string
    {
        $formName = $this->formDef->getTitle() ?: 'Form';

        $html = HtmlHelper::htmlStart($formName . ' - CMA');

        // Determine which JS libraries are needed based on control types
        $needsCKEditor = $this->hasControlType([FormRenderer::TYPE_MEMO]) && $this->hasMemoWithHtml();

        // CSS/JS - use centralized bundles for consistent browser caching
        // Same URL across all pages = browser cache hit
        // PERFORMANCE FIX: Use cma_form_js_url() which includes form-controller.js (276KB)
        // Other pages use cma_js_url() which excludes it for faster loading
        $cssUrl = cma_css_url();
        $jsUrl = cma_form_js_url();

        // PERFORMANCE: Preload critical resources for faster rendering
        $html .= '<link rel="preload" href="' . $cssUrl . '" as="style">' . PHP_EOL;
        $html .= '<link rel="preload" href="../library/jquery.min.js" as="script">' . PHP_EOL;

        $html .= '<link rel="stylesheet" href="' . $cssUrl . '">' . PHP_EOL;

        // JavaScript - These are skipped by main.js executeScripts() when loading via AJAX
        // but included for standalone page loads (e.g., popup windows)
        // CRITICAL: main.php already loads error-handler.js before jQuery and bundle
        $html .= '<script src="../library/jquery.min.js"></script>' . PHP_EOL;
        $html .= '<script src="' . $jsUrl . '"></script>' . PHP_EOL;

        // CKEditor is large and should be loaded separately with defer to avoid blocking
        if ($needsCKEditor) {
            $html .= '<script src="ckeditor/ckeditor.js" defer></script>' . PHP_EOL;

            // Include blockedit.js for content blocks if available
            if (FormRenderer::hasContentBlocks() && $this->hasMemoWithBlockEdit()) {
                $html .= '<script src="assets/js/blockedit.js" defer></script>' . PHP_EOL;
            }
        }

        // Form configuration (stored in CMA namespace)
        $html .= '<script>' . PHP_EOL;
        $config = [
            'sourceFormId' => $this->sourceFormId,
            'jsonForm' => $this->jsonFormName ?: $formName,  // Form identifier for API calls (e.g., "locaties")
            'formName' => $formName,  // Display title (e.g., "Locaties")
            'formNameSingular' => $this->formDef->getTitleSingular() ?: $formName,
            'tableName' => $this->formDef->getSqlTableName(),
            'idField' => $this->formDef->getFormIdField(),
            'hasSubforms' => Arr::isArray($this->arrSubForms) || $this->arrSubForms instanceof \ArrayAccess,
            'accessLevel' => $this->accessLevel,
            'canAdd' => $this->formDef->allowAdd() && $this->accessLevel >= SecurityHelper::ACCESS_FULL,
            'canDelete' => $this->formDef->hasMenuDelete() && $this->accessLevel >= SecurityHelper::ACCESS_FULL,
            'canCopy' => $this->formDef->hasMenuCopy() && $this->accessLevel >= SecurityHelper::ACCESS_FULL,
            'storeLastModified' => $this->formDef->hasStoreLastModified(),
            'previewUrl' => $this->arrRep[\Q_PREVIEWURL][0] ?? '',
            'filterIdName' => $this->formDef->getFilterIdName(),
            'filterFieldName' => $this->formDef->getFilterFieldName(),
            'language' => Application::get('CMA_Language', 'NL'),
            'basePath' => Application::get('base_path', ''),
            'domain' => Request::currentDomain(),
            'debug' => (bool) Application::get('development', ''),
            'showDetails' => SecurityHelper::isAdmin() || SecurityHelper::isDeveloper(),
            'imageConfig' => [
                'resize_type' => FormControlHelper::IMG_MAXIMUM,
                'max_width' => Application::get('cma_htmledit_img_maxwidth', 800),
                'max_height' => Application::get('cma_htmledit_img_maxheight', 600),
                'path' => Application::get('cma_htmledit_img_path', ''),
            ],
            'editorConfig' => [
                'allowBR' => !Application::get('cma_htmledit_allowBR', ''),
                'customCSS' => Application::get('cma_htmledit_css', ''),
                'extraPlugins' => Application::get('appname', '') === 'RINO Portal' ? ',literatuur' : '',
            ],
            'onLoadJS' => $this->arrRep[\Q_ONLOADJS][0] ?? '',
        ];
        // Use JSON_UNESCAPED_UNICODE to handle special characters properly
        $configJson = json_encode($config, JSON_UNESCAPED_UNICODE);
        if ($configJson === false) {
            // JSON encoding failed - create minimal valid config and log error
            \Cma\Services\Logger::warning('FormTemplate: json_encode failed for legacy config', [
                'jsonError' => json_last_error_msg()
            ]);
            $configJson = json_encode([
                'error' => 'Config encoding failed',
                'jsonForm' => $this->jsonFormName ?: $formName,
                'formName' => $formName,
            ]);
        }
        $html .= 'window.CMA = window.CMA || {}; CMA.formConfig = ' . $configJson . ';' . PHP_EOL;

        // CKEditor config for HTML editors
        if ($needsCKEditor) {
            $html .= 'var config = {' . PHP_EOL;
            $html .= '    image_resize_type: ' . FormControlHelper::IMG_MAXIMUM . ',' . PHP_EOL;
            $html .= '    image_max_width: ' . (int) Application::get('cma_htmledit_img_maxwidth', 800) . ',' . PHP_EOL;
            $html .= '    image_max_height: ' . (int) Application::get('cma_htmledit_img_maxheight', 600) . ',' . PHP_EOL;
            $html .= '    image_path: ' . json_encode(Application::get('cma_htmledit_img_path', '')) . ',' . PHP_EOL;
            $html .= '    domain: ' . json_encode(Request::currentDomain()) . ',' . PHP_EOL;
            $html .= '    subpath: ' . json_encode(Application::get('base_path', '')) . ',' . PHP_EOL;
            $html .= '    maximized: false,' . PHP_EOL;
            $html .= '    debug: ' . (Application::get('development', '') ? 'true' : 'false') . ',' . PHP_EOL;
            $html .= '    allowBR: ' . (Application::get('cma_htmledit_allowBR', '') ? 'false' : 'true') . ',' . PHP_EOL;
            $html .= '    customCSS: ' . json_encode(Application::get('cma_htmledit_css', '')) . ',' . PHP_EOL;
            $html .= '    extraPlugins: ""' . PHP_EOL;
            $html .= '};' . PHP_EOL;
            if (Application::get('appname', '') === 'RINO Portal') {
                $html .= 'config.extraPlugins = ",literatuur";' . PHP_EOL;
            }
            $html .= 'if (typeof SetFKEditorConfig === "function") SetFKEditorConfig(config);' . PHP_EOL;
        }
        $html .= '</script>' . PHP_EOL;

        $html .= '</head>' . PHP_EOL;

        return $html;
    }

    /**
     * Generate HTML body section
     */
    private function generateBody(): string
    {
        $isPopup = Request::query('parentID', '') !== '' || Request::query('parentField', '') !== ''
            || Request::query('updatevalues', '') !== ''; // "Add related record" popup
        $isNewMode = Request::query('New', '') === 'Y';
        $directRecordId = Request::query('ID', '') !== '' ? Request::queryInt('ID')
            : (Request::query('id', '') !== '' ? Request::queryInt('id') : null);
        $hasDirectRecordId = $directRecordId !== null || $isNewMode || Request::query('guid', '') !== '';

        // Determine initial display mode to avoid visual flicker
        // - Popup or direct record: mode-detail (hides left panel)
        // - Normal: check localStorage for saved preference, then default to tree
        $initialMode = 'mode-tree';
        if ($isPopup || $hasDirectRecordId) {
            $initialMode = 'mode-detail';
        }

        // Determine detail content state classes
        // - is-creating: new record mode (New=Y or popup with parentID without ID) - shows empty form
        // - has-record: editing existing record (any ID including 0, or guid) - shows form with data
        $detailStateClass = '';
        $hasGuid = Request::query('guid', '') !== '';
        $isAddRelatedRecord = Request::query('parentID', '') !== '' && Request::query('parentField', '') !== '' && $directRecordId === null;
        if ($isNewMode || $isAddRelatedRecord) {
            $detailStateClass = ' is-creating';
        } elseif (($directRecordId !== null && $directRecordId !== '') || $hasGuid) {
            $detailStateClass = ' has-record';
        } elseif ($isPopup) {
            // Popup without explicit ID - JavaScript will set the correct class
            // For now, assume it will load a record (has-record)
            $detailStateClass = ' has-record';
        }

        // Body with onbeforeunload for unsaved changes warning
        // Note: 'has-subform' class is added dynamically by JS when subforms load data
        // 'has-subforms-defined' is added here when form has subforms, to reserve space upfront
        $hasSubformsClass = (Arr::isArray($this->arrSubForms) || $this->arrSubForms instanceof \ArrayAccess) ? ' has-subforms-defined' : '';
        $html = '<body class="cma-form ' . $initialMode . $detailStateClass . ($isPopup ? ' popup' : '') . $hasSubformsClass . '" ';
        $html .= 'data-form-id="' . $this->sourceFormId . '" ';
        $html .= 'data-initial-mode="' . $initialMode . '" ';
        $html .= 'onbeforeunload="if (typeof cmaIsDirty === \'function\' && cmaIsDirty()) return \'Je laatste wijzigingen zijn nog niet opgeslagen\'">';
        $html .= PHP_EOL;

        // Inline script to immediately set correct mode class from localStorage (prevents layout shift)
        // This runs synchronously before any rendering occurs
        if (!$isPopup && !$hasDirectRecordId) {
            $html .= '<script>' . PHP_EOL;
            $html .= '(function(){' . PHP_EOL;
            $html .= '  try {' . PHP_EOL;
            $html .= '    var formId = ' . $this->sourceFormId . ';' . PHP_EOL;
            $html .= '    var stored = localStorage.getItem("cma_listMode_" + formId);' . PHP_EOL;
            $html .= '    if (stored === "2") {' . PHP_EOL;
            $html .= '      document.body.classList.remove("mode-tree", "mode-detail");' . PHP_EOL;
            $html .= '      document.body.classList.add("mode-table");' . PHP_EOL;
            $html .= '    }' . PHP_EOL;
            $html .= '  } catch(e) {}' . PHP_EOL;
            $html .= '})();' . PHP_EOL;
            $html .= '</script>' . PHP_EOL;
        } else {
            $html .= PHP_EOL;
        }

        // Main layout with split toolbars
        $jsonFormAttr = $this->jsonFormName ? ' data-json-form="' . htmlspecialchars($this->jsonFormName) . '"' : '';
        $html .= '<div class="form-layout"' . $jsonFormAttr . '>' . PHP_EOL;

        // List panel (with its own toolbar)
        $html .= $this->generateListPanel();

        // Fold bar for resizing columns (draggable)
        $html .= '<cma-fold class="fold-vertical" orientation="vertical" target="#leftlist" min-size="150" max-size="600" storage-key="form_fold"></cma-fold>' . PHP_EOL;

        // Detail panel (with its own toolbar)
        $html .= $this->generateDetailPanel();

        $html .= '</div>' . PHP_EOL;

        // Loading overlay
        $html .= $this->generateLoadingOverlay();

        // Initialize controller and execute onLoadJS
        // Use IIFE that runs immediately if DOM already loaded (for AJAX loading in sidebar)
        $html .= '<script>' . PHP_EOL;
        $html .= '(function() {' . PHP_EOL;
        $html .= '    function initForm() {' . PHP_EOL;
        $html .= '        // Get form-layout element - controller is stored ON this element, not window' . PHP_EOL;
        $html .= '        var formLayout = document.querySelector(".form-layout");' . PHP_EOL;
        $html .= '        if (!formLayout) { cmaLog.error("No .form-layout element found"); return; }' . PHP_EOL;
        $html .= '        // Destroy previous controller if exists on this element' . PHP_EOL;
        $html .= '        if (formLayout._cmaController && typeof formLayout._cmaController.destroy === "function") {' . PHP_EOL;
        $html .= '            formLayout._cmaController.destroy();' . PHP_EOL;
        $html .= '        }' . PHP_EOL;
        $html .= '        // Create new controller and store on the element (NOT on window)' . PHP_EOL;
        $html .= '        formLayout._cmaController = new CMA.FormController(' . $this->sourceFormId . ', CMA.formConfig);' . PHP_EOL;

        // NOTE: onLoadJS is now executed by FormController.executeOnLoadJS() when a record is loaded
        // This ensures recordId is available when the callback runs

        // Initialize library functions
        $html .= '        if (typeof list_init === "function") list_init();' . PHP_EOL;
        $html .= '        if (typeof form_init === "function" && document.forms.main) form_init(document.forms.main);' . PHP_EOL;
        $html .= '        if (typeof lib_Form_Scale_htmleditors === "function") lib_Form_Scale_htmleditors(0);' . PHP_EOL;

        // Initialize fold bars for resizing (needed when loaded via AJAX)
        $html .= '        if (typeof initFoldBar === "function") initFoldBar();' . PHP_EOL;
        $html .= '        if (typeof initHorizontalFoldBar === "function") initHorizontalFoldBar();' . PHP_EOL;
        $html .= '        if (typeof restoreFormState === "function") restoreFormState();' . PHP_EOL;

        $html .= '    }' . PHP_EOL;
        $html .= '    if (document.readyState === "loading") {' . PHP_EOL;
        $html .= '        document.addEventListener("DOMContentLoaded", initForm);' . PHP_EOL;
        $html .= '    } else {' . PHP_EOL;
        $html .= '        initForm();' . PHP_EOL;
        $html .= '    }' . PHP_EOL;
        $html .= '})();' . PHP_EOL;
        $html .= '</script>' . PHP_EOL;

        $html .= '</body>' . PHP_EOL;
        $html .= '</html>' . PHP_EOL;

        return $html;
    }

    /**
     * Generate list toolbar - search, tree/table toggle, expand/collapse
     */
    private function generateListToolbar(): string
    {
        $formName = $this->formDef->getTitle() ?: 'Form';
        $formNameSingular = $this->formDef->getTitleSingular() ?: $formName;
        $filterFieldName = $this->formDef->getFilterFieldName();
        $filterCaption = $this->formDef->get('filterDescription', 0) ?: $filterFieldName;

        $html = '<div id="listToolbar" class="toolbar">' . PHP_EOL;

        // Left side - view toggle buttons
        $html .= '<div class="toolbar-left">' . PHP_EOL;

        // Search toggle button (always show)
        $html .= '<span class="tb-btn" id="btn_search" title="Uitgebreid zoeken binnen alle gegevens"><a href="#" data-action="toggleSearch"><span class="lnr lnr-search"></span></a></span>' . PHP_EOL;

        // Tree/Table mode toggle buttons (visibility controlled by CSS via body.mode-* classes)
        $html .= '<span class="tb-btn" id="btn_treeview" title="Groepeer/Boom weergave"><a href="#" data-action="setlistmode" data-mode="1"><span class="lnr lnr-grouped"></span></a></span>' . PHP_EOL;
        $html .= '<span class="tb-btn" id="btn_tableview" title="Tabel weergave"><a href="#" data-action="setlistmode" data-mode="2"><span class="lnr lnr-table"></span></a></span>' . PHP_EOL;

        // Column selector button (only visible in table mode via CSS)
        $html .= '<span class="tb-btn" id="btn_columns" title="Selecteer kolommen en hun volgorde"><a href="#" data-action="selectColumns"><span class="lnr lnr-select"></span></a></span>' . PHP_EOL;

        // Tree expand/collapse buttons - only render if form has grouping configured
        // Render hidden by default - JavaScript will show them when tree has expandable folders
        if ($this->formDef->hasGrouping()) {
            $html .= '<span class="tb-btn" id="btn_expand" style="display:none" title="' . Server::htmlEncode($lang_tb_expand ?? 'Uitklappen') . '"><a href="javascript:fExpandAll()"><span class="lnr lnr-expandall"></span></a></span>' . PHP_EOL;
            $html .= '<span class="tb-btn" id="btn_collapse" style="display:none" title="' . Server::htmlEncode($lang_tb_collapse ?? 'Inklappen') . '"><a href="javascript:fCollapseAll()"><span class="lnr lnr-collapseall"></span></a></span>' . PHP_EOL;
        }

        // Add button (only visible in table mode via CSS) - separator + button
        // Uses data-action="add" which opens popup in table mode, inline form in tree mode
        $canAdd = $this->formDef->allowAdd() && $this->accessLevel >= SecurityHelper::ACCESS_FULL;
        if ($canAdd) {
            $addTooltip = 'Voeg een ' . Server::htmlEncode(strtolower($formNameSingular)) . ' toe';
            $html .= '<span class="tb-sep table-mode-only"></span>' . PHP_EOL;
            $html .= '<span class="tb-btn table-mode-only" id="btn_add" title="' . $addTooltip . '"><a href="#" data-action="add"><span class="lnr lnr-file-add"></span></a></span>' . PHP_EOL;
        }

        // Readonly indicator (shown when form doesn't allow editing)
        $canEdit = $this->formDef->allowEdit() && $this->accessLevel >= SecurityHelper::ACCESS_FULL;
        if (!$canEdit) {
            $html .= '<span class="tb-sep"></span>' . PHP_EOL;
            $html .= '<span id="listReadonlyIndicator" class="toolbar-readonly-indicator" data-tooltip="Alleen lezen" data-tooltip-pos="top"><span class="lnr lnr-lock"></span></span>' . PHP_EOL;
        }

        $html .= '</div>' . PHP_EOL;

        // Filter field in toolbar OR quick search box on right
        $toolbarFilterHasOptions = false;
        if (!empty($filterFieldName)) {
            // Form requires specific filter field - show combobox in toolbar (takes full width)
            // But only if we can get options for it
            $filterOptions = $this->getFilterFieldOptions($filterFieldName);
            if ($filterOptions !== null && count($filterOptions) > 0) {
                $html .= '<div class="toolbar-center">' . PHP_EOL;
                $html .= $this->generateToolbarFilterFieldWithOptions($filterFieldName, $filterCaption, $filterOptions);
                $html .= '</div>' . PHP_EOL;
                $toolbarFilterHasOptions = true;
            } else {
                // No options found - show quick search box instead
                // The filter field will be included in the search panel
                $html .= '<div class="toolbar-right">' . PHP_EOL;
                $html .= '<span id="recordCount" class="toolbar-status table-mode-only" style="display:none"></span>' . PHP_EOL;
                $html .= '<lib-search-input id="searchfor" name="searchfor" placeholder="Zoeken..."></lib-search-input>' . PHP_EOL;
                $html .= '</div>' . PHP_EOL;
            }
        } else {
            // Standard quick search box on right
            $html .= '<div class="toolbar-right">' . PHP_EOL;
            $html .= '<span id="recordCount" class="toolbar-status table-mode-only" style="display:none"></span>' . PHP_EOL;
            $html .= '<lib-search-input id="searchfor" name="searchfor" placeholder="Zoeken..."></lib-search-input>' . PHP_EOL;
            $html .= '</div>' . PHP_EOL;
        }

        $html .= '</div>' . PHP_EOL;

        // Store whether toolbar filter has options (used by search panel to know if filter field should be included)
        $this->toolbarFilterHasOptions = $toolbarFilterHasOptions;

        // Expandable search panel (hidden by default) - always show
        $html .= $this->generateSearchPanel();

        return $html;
    }

    /**
     * Generate toolbar filter field (combobox) for forms with FilterFieldName
     * Options are passed in since they're already fetched to check if they exist
     */
    private function generateToolbarFilterFieldWithOptions(string $filterFieldName, string $filterCaption, array $options): string
    {
        // Strip "Selecteer de " prefix (case-insensitive) and capitalize first letter
        $displayCaption = preg_replace('/^selecteer\s+de\s+/i', '', $filterCaption);
        $displayCaption = ucfirst($displayCaption);

        // Build placeholder text: "Selecteer een [caption]"
        $placeholder = 'Selecteer een ' . strtolower($displayCaption);

        $html = '<div class="toolbar-filter">' . PHP_EOL;
        $html .= '<label>' . Server::htmlEncode($displayCaption) . ':</label>' . PHP_EOL;
        $html .= '<lib-combo id="toolbarFilter" name="toolbarFilter" data-field="' . Server::htmlEncode($filterFieldName) . '" placeholder="' . Server::htmlEncode($placeholder) . '">' . PHP_EOL;
        $html .= '<option value=""></option>' . PHP_EOL;

        foreach ($options as $opt) {
            $value = Server::htmlEncode($opt['value'] ?? '');
            $display = Server::htmlEncode($opt['display'] ?? $opt['value'] ?? '');
            $html .= '<option value="' . $value . '">' . $display . '</option>' . PHP_EOL;
        }

        $html .= '</lib-combo>' . PHP_EOL;
        $html .= '</div>' . PHP_EOL;

        return $html;
    }

    /**
     * Get options for a filter field by looking up its definition
     */
    private function getFilterFieldOptions(string $filterFieldName): ?array
    {
        $arrRep = $this->arrRep;
        $rowCount = count($arrRep[\Q_FIELDNAME] ?? []);

        // Find the field in the form definition
        $fieldIndex = -1;
        for ($i = 0; $i < $rowCount; $i++) {
            $fieldName = (string)($arrRep[\Q_FIELDNAME][$i] ?? '');
            $baseFieldName = (string)($arrRep[\Q_BASEFIELDNAME][$i] ?? '');

            if (strcasecmp($fieldName, $filterFieldName) === 0 ||
                strcasecmp($baseFieldName, $filterFieldName) === 0) {
                $fieldIndex = $i;
                break;
            }
        }

        // Field found - try to get combo options
        if ($fieldIndex >= 0) {
            // First try getComboOptionsForSearch (uses sourceTable, sqlList etc.)
            $options = $this->getComboOptionsForSearch($fieldIndex);
            if ($options !== null && count($options) > 0) {
                return $options;
            }

            // If that failed, try direct source table lookup
            $sourceTable = (string)($arrRep[\Q_SOURCETABLE][$fieldIndex] ?? '');
            $sqlList = (string)($arrRep[\Q_SQLLIST][$fieldIndex] ?? '');
            $idField = (string)($arrRep[\Q_CTRLIDFIELD][$fieldIndex] ?? '');
            $foreignIdField = (string)($arrRep[\Q_FOREIGNIDFIELD][$fieldIndex] ?? '');

            // If we have a SQL list, use that
            if ($sqlList !== '') {
                $options = $this->executeSqlForOptions($sqlList, $idField, $foreignIdField);
                if ($options !== null && count($options) > 0) {
                    return $options;
                }
            }

            // If we have source table info, build a query
            if ($sourceTable !== '' && $idField !== '') {
                $displayField = $foreignIdField !== '' ? $foreignIdField : $idField;
                $sql = "SELECT [$idField], [$displayField] FROM [$sourceTable] ORDER BY [$displayField]";
                $options = $this->executeSqlForOptions($sql, $idField, $displayField);
                if ($options !== null && count($options) > 0) {
                    return $options;
                }
            }
        }

        // If no options found from control definition, get distinct values from the data table
        return $this->getDistinctValuesForFilter($filterFieldName);
    }

    /**
     * Execute SQL and return options array
     * @param string $sql The SQL query
     * @param string $idField The field name for the value (optional, uses first column if empty)
     * @param string $displayField The field name for the display text (optional, uses second column if empty)
     */
    private function executeSqlForOptions(string $sql, string $idField = '', string $displayField = ''): ?array
    {
        try {
            global $conn;

            // Open data connection if not already open
            if ($conn === null) {
                // Use raw array with Q_FKDATABASE constant (form-level database)
                // Empty or 0 means use default database
                $databaseId = $this->arrRep[\Q_FKDATABASE][0] ?? '';
                CmaRepository::openConnectionById($databaseId);
                $conn = $conn;
            }

            if ($conn === null) {
                return null;
            }

            $sql = \App\Library\SQL::addTop($sql, 200);
            $rs = \App\Library\Database::openRS($sql, $conn);
            if ($rs === null) {
                return null;
            }

            $options = [];
            while (!$rs->EOF) {
                $row = $rs->fields;
                $keys = array_keys($row);
                $filteredKeys = array_values(array_filter($keys, fn($k) => !is_int($k)));

                // Use specified field names if provided, otherwise fall back to positional
                if ($idField !== '' && isset($row[$idField])) {
                    $value = $row[$idField] ?? '';
                } elseif ($idField !== '' && isset($row[strtolower($idField)])) {
                    $value = $row[strtolower($idField)] ?? '';
                } elseif ($idField !== '' && isset($row[strtoupper($idField)])) {
                    $value = $row[strtoupper($idField)] ?? '';
                } elseif (count($filteredKeys) >= 1) {
                    $value = $row[$filteredKeys[0]] ?? '';
                } else {
                    $value = '';
                }

                if ($displayField !== '' && isset($row[$displayField])) {
                    $display = $row[$displayField] ?? $value;
                } elseif ($displayField !== '' && isset($row[strtolower($displayField)])) {
                    $display = $row[strtolower($displayField)] ?? $value;
                } elseif ($displayField !== '' && isset($row[strtoupper($displayField)])) {
                    $display = $row[strtoupper($displayField)] ?? $value;
                } elseif (count($filteredKeys) >= 2) {
                    $display = $row[$filteredKeys[1]] ?? $value;
                } else {
                    $display = $value;
                }

                if ($value !== '' && $value !== null) {
                    $options[] = [
                        'value' => $value,
                        'display' => $display,
                    ];
                }
                $rs->MoveNext();
            }

            return $options;
        } catch (\Exception $e) {
            Logger::debug('executeSqlForOptions failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get distinct values for a filter field from the data table
     */
    private function getDistinctValuesForFilter(string $filterFieldName): ?array
    {
        try {
            global $conn;

            // Open data connection if not already open
            if ($conn === null) {
                $databaseId = $this->formDef->getDatabaseId();
                if ($databaseId !== null) {
                    CmaRepository::openConnectionById($databaseId);
                    $conn = $conn;
                }
            }

            if ($conn === null) {
                return null;
            }

            $tableName = $this->formDef->getSqlTableName();
            if (empty($tableName)) {
                return null;
            }

            // Query distinct values from the filter field
            $sql = "SELECT DISTINCT [$filterFieldName] FROM [$tableName] WHERE [$filterFieldName] IS NOT NULL AND [$filterFieldName] <> '' ORDER BY [$filterFieldName]";

            $rs = \App\Library\Database::openRS($sql, $conn);
            if ($rs === null) {
                return null;
            }

            $options = [];
            while (!$rs->EOF) {
                $value = $rs->fields[$filterFieldName] ?? '';
                if ($value !== '' && $value !== null) {
                    $options[] = [
                        'value' => $value,
                        'display' => $value,
                    ];
                }
                $rs->MoveNext();
            }

            return $options;
        } catch (\Exception $e) {
            Logger::debug('getDistinctValuesForFilter failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Generate expandable search panel with filter fields
     */
    private function generateSearchPanel(): string
    {
        $html = '<div id="searchPanel" class="search-panel" style="display:none;">' . PHP_EOL;
        $html .= '<div class="search-panel-content">' . PHP_EOL;

        // Generate filter fields based on form definition
        $fields = $this->getSearchableFields();
        $visibleCount = 6; // Show first N fields by default, rest require "Meer velden" click

        // If no searchable fields found, show a message
        if (empty($fields)) {
            $html .= '<div class="search-fields-empty">Geen doorzoekbare velden beschikbaar</div>' . PHP_EOL;
            $html .= '</div></div>' . PHP_EOL;
            return $html;
        }

        // Primary fields (always visible)
        $html .= '<div class="search-fields search-fields-primary" id="searchFields">' . PHP_EOL;
        foreach (array_slice($fields, 0, $visibleCount) as $field) {
            $html .= $this->generateSearchField($field);
        }
        $html .= '</div>' . PHP_EOL;

        // Extra fields (hidden by default)
        if (count($fields) > $visibleCount) {
            $html .= '<div class="search-fields search-fields-extra" id="searchFieldsExtra" style="display:none;">' . PHP_EOL;
            foreach (array_slice($fields, $visibleCount) as $field) {
                $html .= $this->generateSearchField($field);
            }
            $html .= '</div>' . PHP_EOL;
        }

        // Search panel buttons
        $html .= '<div class="search-panel-buttons">' . PHP_EOL;
        $html .= '<button type="button" class="btn btn-primary" onclick="cmaApplySearchFilters()">Zoeken</button>' . PHP_EOL;
        $html .= '<button type="button" class="btn search-reset-btn" id="searchResetBtn" onclick="cmaClearSearchFilters()" style="display:none;"><span class="lnr lnr-cross"></span> Wissen</button>' . PHP_EOL;
        if (count($fields) > $visibleCount) {
            $html .= '<button type="button" class="btn btn-link search-more-btn" id="searchMoreBtn" onclick="cmaToggleSearchMore()">' . PHP_EOL;
            $html .= '<span class="lnr lnr-chevron-down"></span> Meer velden' . PHP_EOL;
            $html .= '</button>' . PHP_EOL;
        }
        $html .= '</div>' . PHP_EOL;

        $html .= '</div>' . PHP_EOL;
        $html .= '</div>' . PHP_EOL;

        return $html;
    }

    /**
     * Cache for searchable fields (computed once per instance)
     */
    private ?array $searchableFieldsCache = null;

    /**
     * Get searchable fields from form definition (with instance-level caching)
     *
     * Uses the BaseFieldName (actual database column) for searching,
     * as the FieldName might be an alias in the NameQuery.
     */
    private function getSearchableFields(): array
    {
        // Return cached result if available
        if ($this->searchableFieldsCache !== null) {
            return $this->searchableFieldsCache;
        }

        $fields = [];
        $arrRep = $this->arrRep;

        // Get filter field name - only skip it from search panel if toolbar has options
        $filterFieldName = (string)($this->formDef->getFilterFieldName() ?? '');
        $skipFilterField = $filterFieldName !== '' && $this->toolbarFilterHasOptions;

        $rowCount = count($arrRep[\Q_FIELDNAME] ?? []);
        for ($i = 0; $i < $rowCount; $i++) {
            $fieldName = (string)($arrRep[\Q_FIELDNAME][$i] ?? '');
            $baseFieldName = (string)($arrRep[\Q_BASEFIELDNAME][$i] ?? '');
            $caption = (string)($arrRep[\Q_CAPTION][$i] ?? $fieldName);
            $controlType = (int)($arrRep[\Q_CONTROLTYPEID][$i] ?? 0);

            // Skip empty fields and group separators
            if ($fieldName === '' || $controlType === FormRenderer::TYPE_GROUPSEPARATOR) {
                continue;
            }

            // Skip the filter field only if it's in the toolbar with options
            if ($skipFilterField && (
                strcasecmp($fieldName, $filterFieldName) === 0 ||
                strcasecmp($baseFieldName, $filterFieldName) === 0
            )) {
                continue;
            }

            // Skip file/image/checklist/sortlist/password controls (not searchable)
            if (in_array($controlType, [
                FormRenderer::TYPE_IMAGE,
                FormRenderer::TYPE_FILE,
                FormRenderer::TYPE_CHECKLIST,
                FormRenderer::TYPE_SORTLIST,
                FormRenderer::TYPE_THUMBNAIL,
                FormRenderer::TYPE_DIRECTORY,
                FormRenderer::TYPE_PASSWORD  // Passwords should never be searchable
            ])) {
                continue;
            }

            // Use base field name for searching (the actual column name)
            // If no base field name, use the field name (might be a direct column)
            $searchColumn = ($baseFieldName !== '') ? $baseFieldName : $fieldName;

            // Determine field type for search input
            $searchType = 'text';
            if ($controlType === FormRenderer::TYPE_CHECKBOX) {
                $searchType = 'boolean';
            } elseif ($controlType === FormRenderer::TYPE_COMBOBOX || $controlType === FormRenderer::TYPE_USERLIST || $controlType === FormRenderer::TYPE_XMLSTORE) {
                // Check if the combo SQL depends on current record ID
                // Such combos can't be used for searching as they need the record context
                $sqlList = (string)($arrRep[\Q_SQLLIST][$i] ?? '');
                $sourceTable = (string)($arrRep[\Q_SOURCETABLE][$i] ?? '');

                if ($sqlList !== '' && (stripos($sqlList, '[id]') !== false || stripos($sqlList, '[ProdID]') !== false)) {
                    // SQL depends on current record - skip field if no source table fallback
                    if ($sourceTable === '') {
                        continue; // Can't search this field without a proper source table
                    }
                    // Fall through to show as combo - will use source table instead
                }

                // Combo options loaded lazily via AJAX when search panel opens
                $searchType = 'select';
            } else {
                // Check for date fields using ADO type codes (7=Date, 133=DBDate, 135=DBTimeStamp)
                $schemaType = $arrRep[\Q_SCHEMA_DATATYPE][$i] ?? '';
                $schemaTypeLower = strtolower((string)$schemaType);
                $isDateField = false;
                $isNumberField = false;

                if (is_numeric($schemaType) && in_array((int)$schemaType, [7, 133, 135])) {
                    $isDateField = true;
                } elseif (in_array($schemaTypeLower, ['date', 'datetime', 'datetime2', 'smalldatetime', 'datetimeoffset'])) {
                    $isDateField = true;
                }

                // Check for numeric fields via dataType or numericPrecision
                if (in_array($schemaTypeLower, ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'decimal', 'numeric', 'float', 'real', 'money', 'number'])) {
                    $isNumberField = true;
                } elseif (($arrRep[\Q_SCHEMA_NUM_PREC][$i] ?? '') !== '') {
                    $isNumberField = true;
                }

                if ($isDateField) {
                    $searchType = 'date';
                } elseif ($isNumberField) {
                    // Numeric field - show range inputs
                    $searchType = 'number';
                }
            }

            $fields[] = [
                'name' => $searchColumn,  // Use the actual column name for searching
                'fieldName' => $fieldName, // Original field name for API calls
                'caption' => strip_tags($caption),
                'type' => $searchType,
                'controlType' => $controlType,
                'index' => $i,
            ];
        }

        // Cache the result for subsequent calls
        $this->searchableFieldsCache = $fields;
        return $fields;
    }

    /**
     * Get combo box options for search filter
     */
    private function getComboOptionsForSearch(int $fieldIndex): ?array
    {
        $arrRep = $this->arrRep;

        $sourceTable = $arrRep[\Q_SOURCETABLE][$fieldIndex] ?? '';
        $sqlList = $arrRep[\Q_SQLLIST][$fieldIndex] ?? '';
        $idField = $arrRep[\Q_CTRLIDFIELD][$fieldIndex] ?? '';
        $foreignIdField = $arrRep[\Q_FOREIGNIDFIELD][$fieldIndex] ?? '';

        // Need either source table or SQL list
        if ($sourceTable === '' && $sqlList === '') {
            return null;
        }

        try {
            global $conn;

            // Open data connection if not already open
            if ($conn === null) {
                // Use raw array with Q_FKDATABASE constant (form-level database)
                // Empty or 0 means use default database
                $databaseId = $this->arrRep[\Q_FKDATABASE][0] ?? '';
                CmaRepository::openConnectionById($databaseId);
                $conn = $conn;
            }

            if ($conn === null) {
                return null;
            }

            // Build query - use sqlList if available (like detail screen does)
            if ($sqlList !== '') {
                $sql = $sqlList;
            } else {
                // Default: select from source table
                $displayField = $foreignIdField ?: $idField;
                $sql = "SELECT [$idField], [$displayField] FROM [$sourceTable] ORDER BY [$displayField]";
            }

            // No limit — toolbar filter must show all available options

            $rs = \App\Library\Database::openRS($sql, $conn);
            if ($rs === null) {
                return null;
            }

            $options = [];
            while (!$rs->EOF) {
                $row = $rs->fetchAssoc();
                // Get first two columns as value and display (same as detail screen)
                $keys = array_keys($row);
                $filteredKeys = array_filter($keys, fn($k) => !is_int($k));
                $filteredKeys = array_values($filteredKeys);

                if (count($filteredKeys) >= 2) {
                    $value = $row[$filteredKeys[0]] ?? '';
                    $display = $row[$filteredKeys[1]] ?? $value;
                } else {
                    $value = $row[$filteredKeys[0] ?? 0] ?? '';
                    $display = $value;
                }

                $options[] = [
                    'value' => $value,
                    'display' => $display,
                ];
                $rs->MoveNext();
            }

            return $options;
        } catch (\Exception $e) {
            Logger::debug('getComboOptionsForSearch failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Generate a single search field input (caption above input)
     */
    private function generateSearchField(array $field): string
    {
        $name = 'search_' . Server::htmlEncode($field['name']);
        $caption = Server::htmlEncode($field['caption']);

        $html = '<div class="search-field">' . PHP_EOL;
        $html .= '<label for="' . $name . '">' . $caption . '</label>' . PHP_EOL;

        switch ($field['type']) {
            case 'boolean':
                $html .= '<select id="' . $name . '" name="' . $name . '" class="search-input">' . PHP_EOL;
                $html .= '<option value="">-- Alle --</option>' . PHP_EOL;
                $html .= '<option value="1">Ja</option>' . PHP_EOL;
                $html .= '<option value="0">Nee</option>' . PHP_EOL;
                $html .= '</select>' . PHP_EOL;
                break;

            case 'date':
                // Stacked layout: van: [input] / tot: [input]
                $html = '<div class="search-field">' . PHP_EOL;
                $html .= '<label>' . $caption . '</label>' . PHP_EOL;
                $html .= '<div class="date-range-filter">' . PHP_EOL;
                $html .= '<div class="date-range-row">' . PHP_EOL;
                $html .= '<label for="' . $name . '_from">van:</label>' . PHP_EOL;
                $html .= '<lib-datepicker id="' . $name . '_from" name="' . $name . '_from" class="search-input" format="dd-mm-yyyy" placeholder="dd-mm-jjjj" locale="nl" small></lib-datepicker>' . PHP_EOL;
                $html .= '</div>' . PHP_EOL;
                $html .= '<div class="date-range-row">' . PHP_EOL;
                $html .= '<label for="' . $name . '_to">tot:</label>' . PHP_EOL;
                $html .= '<lib-datepicker id="' . $name . '_to" name="' . $name . '_to" class="search-input" format="dd-mm-yyyy" placeholder="dd-mm-jjjj" locale="nl" small></lib-datepicker>' . PHP_EOL;
                $html .= '</div>' . PHP_EOL;
                $html .= '</div>' . PHP_EOL;
                break;

            case 'time':
                // Update label to point to first input
                $html = '<div class="search-field">' . PHP_EOL;
                $html .= '<label for="' . $name . '_from">' . $caption . '</label>' . PHP_EOL;
                $html .= '<div class="search-time-range">' . PHP_EOL;
                $html .= '<div class="time-input-wrapper">' . PHP_EOL;
                $html .= '<input type="time" id="' . $name . '_from" name="' . $name . '_from" class="search-input">' . PHP_EOL;
                $html .= '</div>' . PHP_EOL;
                $html .= '<span class="time-separator">t/m</span>' . PHP_EOL;
                $html .= '<div class="time-input-wrapper">' . PHP_EOL;
                $html .= '<input type="time" id="' . $name . '_to" name="' . $name . '_to" class="search-input">' . PHP_EOL;
                $html .= '</div>' . PHP_EOL;
                $html .= '</div>' . PHP_EOL;
                break;

            case 'number':
                // Update label to point to first input
                $html = '<div class="search-field">' . PHP_EOL;
                $html .= '<label for="' . $name . '_from">' . $caption . '</label>' . PHP_EOL;
                $html .= '<div class="search-number-range">' . PHP_EOL;
                $html .= '<div class="number-input-wrapper">' . PHP_EOL;
                $html .= '<input type="number" id="' . $name . '_from" name="' . $name . '_from" class="search-input" placeholder="Min">' . PHP_EOL;
                $html .= '</div>' . PHP_EOL;
                $html .= '<span class="number-separator">t/m</span>' . PHP_EOL;
                $html .= '<div class="number-input-wrapper">' . PHP_EOL;
                $html .= '<input type="number" id="' . $name . '_to" name="' . $name . '_to" class="search-input" placeholder="Max">' . PHP_EOL;
                $html .= '</div>' . PHP_EOL;
                $html .= '</div>' . PHP_EOL;
                break;

            case 'select':
                // Combobox with lazy loading - options loaded via AJAX when search panel opens
                $fieldName = Server::htmlEncode($field['fieldName'] ?? $field['name'] ?? '');
                $html .= '<lib-combo id="' . $name . '" name="' . $name . '" class="search-input search-combo-lazy" data-field="' . $fieldName . '" placeholder="-- Alle --">' . PHP_EOL;
                $html .= '<option value="">-- Alle --</option>' . PHP_EOL;
                $html .= '</lib-combo>' . PHP_EOL;
                break;

            default:
                $html .= '<input type="text" id="' . $name . '" name="' . $name . '" class="search-input" placeholder="Zoek...">' . PHP_EOL;
        }

        $html .= '</div>' . PHP_EOL;

        return $html;
    }

    /**
     * Generate detail toolbar - add, save, cancel, copy, delete, preview, extra buttons
     */
    private function generateDetailToolbar(): string
    {
        $canAdd = $this->formDef->allowAdd() && $this->accessLevel >= SecurityHelper::ACCESS_FULL;
        $canDelete = $this->formDef->hasMenuDelete() && $this->accessLevel >= SecurityHelper::ACCESS_FULL;
        $canCopy = $this->formDef->hasMenuCopy() && $this->accessLevel >= SecurityHelper::ACCESS_FULL;
        $formName = $this->formDef->getTitle() ?: 'Form';
        $formNameSingular = $this->formDef->getTitleSingular() ?: $formName;

        $html = '<div id="detailToolbar" class="toolbar">' . PHP_EOL;

        $html .= '<div class="toolbar-left">' . PHP_EOL;

        // Add button - uses addInline to switch to add mode without popup
        // This button is always visible (doesn't require a record)
        // Button order determines text hide priority (left hides first)
        $btnIndex = 1;
        $singularLower = strtolower($formNameSingular);
        if ($canAdd) {
            $addTooltip = 'Voeg een ' . Server::htmlEncode($singularLower) . ' toe';
            $html .= '<span class="tb-btn responsive-btn" data-btn-order="' . $btnIndex++ . '" title="' . $addTooltip . '"><a href="#" data-action="addInline"><span class="lnr lnr-file-add"></span><span class="btn-text">Toevoegen</span></a></span>' . PHP_EOL;
        }

        // Save button - requires record
        $saveTooltip = 'Sla deze ' . Server::htmlEncode($singularLower) . ' op';
        $html .= '<span class="tb-btn responsive-btn requires-record" id="toolbar_save" data-btn-order="' . $btnIndex++ . '" title="' . $saveTooltip . '"><a href="#" data-action="save"><span class="lnr lnr-save"></span><span class="btn-text">Opslaan</span></a></span>' . PHP_EOL;

        // Cancel button - requires record
        $cancelTooltip = 'Wijzigingen aan deze ' . Server::htmlEncode($singularLower) . ' annuleren';
        $html .= '<span class="tb-btn responsive-btn requires-record" id="toolbar_cancel" data-btn-order="' . $btnIndex++ . '" title="' . $cancelTooltip . '"><a href="#" data-action="cancel"><span class="lnr lnr-undo"></span><span class="btn-text">Annuleren</span></a></span>' . PHP_EOL;

        // Copy button - requires record
        if ($canCopy) {
            $copyTooltip = 'Maak een kopie van deze ' . Server::htmlEncode($singularLower);
            $html .= '<span class="tb-btn responsive-btn requires-record" data-btn-order="' . $btnIndex++ . '" title="' . $copyTooltip . '"><a href="#" data-action="copy"><span class="lnr lnr-kopieer"></span><span class="btn-text">Kopiëren</span></a></span>' . PHP_EOL;
        }

        // Delete button - requires record
        if ($canDelete) {
            $deleteTooltip = 'Verwijder deze ' . Server::htmlEncode($singularLower);
            $html .= '<span class="tb-btn responsive-btn requires-record" data-btn-order="' . $btnIndex++ . '" title="' . $deleteTooltip . '"><a href="#" data-action="delete"><span class="lnr lnr-delete"></span><span class="btn-text">Verwijderen</span></a></span>' . PHP_EOL;
        }

        // Preview button (if configured) - requires record
        if (($this->arrRep[\Q_PREVIEWURL][0] ?? '') !== '') {
            $html .= '<span class="tb-sep requires-record"></span>' . PHP_EOL;
            $html .= '<span class="tb-btn responsive-btn requires-record" data-btn-order="' . $btnIndex++ . '" title="Voorbeeld"><a href="#" data-action="preview"><span class="lnr lnr-preview"></span><span class="btn-text">Voorbeeld</span></a></span>' . PHP_EOL;
        }

        // Extra buttons from form definition
        $extraButtons = $this->generateExtraButtons();
        if ($extraButtons !== '') {
            $html .= '<span class="tb-sep"></span>' . PHP_EOL;
            $html .= $extraButtons;
        }

        $html .= '</div>' . PHP_EOL;

        // Right side - form name
        $html .= '<div class="toolbar-right">' . PHP_EOL;
        $html .= '<span class="form-title">' . Server::htmlEncode($formName) . '</span>' . PHP_EOL;
        $html .= '</div>' . PHP_EOL;

        $html .= '</div>' . PHP_EOL;

        return $html;
    }

    /**
     * Generate extra toolbar buttons from form definition
     * Uses actual column names to avoid Q_* constant index misalignment issues
     */
    private function generateExtraButtons(): string
    {
        $html = '';
        // Use actual database column names instead of Q_* constants
        // The Q_* constants can be misaligned due to ODBC column deduplication differences
        $icons = [
            ['url' => 'extraIconURL', 'res' => 'extraIconResource', 'title' => 'extraIconTitle'],
            ['url' => 'extraIcon2URL', 'res' => 'extraIcon2Resource', 'title' => 'extraIcon2Title'],
            ['url' => 'extraIcon3URL', 'res' => 'extraIcon3Resource', 'title' => 'extraIcon3Title'],
            ['url' => 'extraIcon4URL', 'res' => 'extraIcon4Resource', 'title' => 'extraIcon4Title'],
            ['url' => 'extraIcon5URL', 'res' => 'extraIcon5Resource', 'title' => 'extraIcon5Title'],
        ];

        // Get JSON extra buttons for openInNewWindow flag
        $jsonData = $this->arrRep['_json'] ?? [];
        $jsonExtraButtons = $jsonData['extraButtons'] ?? [];

        $btnOrder = 10; // Start after standard buttons
        foreach ($icons as $index => $icon) {
            $url = $this->arrRep[$icon['url']][0] ?? '';
            $res = $this->arrRep[$icon['res']][0] ?? '';
            $title = $this->arrRep[$icon['title']][0] ?? '';

            if ($url !== '' || $res !== '') {
                // Check if URL contains placeholders or JS references that need a record ID
                $hasPlaceholder = preg_match('/\[(id|guid|guid2)\]/i', $url)
                    || preg_match('/\brecordId\b/', $url);
                $extraClass = $hasPlaceholder ? ' requires-record' : '';
                $openNewWindow = !empty($jsonExtraButtons[$index]['openInNewWindow']);
                $openNewWindowAttr = $openNewWindow ? ' data-open-new-window="true"' : '';
                // Use same structure as standard toolbar buttons: responsive-btn class and data-btn-order
                $html .= '<span class="tb-btn responsive-btn extra-button' . $extraClass . '" data-btn-order="' . ($btnOrder + $index) . '" data-extra-index="' . ($index + 1) . '">';
                $html .= '<a href="#" data-action="extra" data-url="' . Server::htmlEncode($url) . '" data-url-template="' . Server::htmlEncode($url) . '" data-title="' . Server::htmlEncode($title) . '"' . $openNewWindowAttr . ' title="' . Server::htmlEncode($title) . '">';
                if ($res !== '') {
                    if (strpos($res, 'lnr') === 0) {
                        // CSS icon class (e.g. "lnr lnr-redo")
                        $html .= '<span class="' . Server::htmlEncode($res) . '"></span>';
                    } else {
                        // Image file path
                        $iconPath = str_replace('.png', '.svg', $res);
                        if (strpos($iconPath, 'assets/icons/') !== 0) {
                            $iconPath = 'assets/icons/' . basename($iconPath);
                        }
                        $html .= '<img src="/cma/' . Server::htmlEncode($iconPath) . '" alt="">';
                    }
                } else {
                    $html .= '<span class="lnr lnr-link"></span>';
                }
                // Add button text label
                if ($title !== '') {
                    $html .= '<span class="btn-text">' . Server::htmlEncode($title) . '</span>';
                }
                $html .= '</a></span>' . PHP_EOL;
            }
        }

        return $html;
    }

    /**
     * Generate list panel section - matches list.php structure
     */
    private function generateListPanel(): string
    {
        $html = '<div id="leftlist">' . PHP_EOL;

        // List toolbar (search, tree/table toggle, expand/collapse)
        $html .= $this->generateListToolbar();

        // List content area (populated via AJAX)
        $html .= '<div id="c" class="listcontent blockselect" onselectstart="return(false)">' . PHP_EOL;
        $html .= '<lib-loader id="listLoader" delay="500" text="Laden..." overlay></lib-loader>' . PHP_EOL;
        $html .= '<div id="listContent"></div>' . PHP_EOL;
        $html .= '</div>' . PHP_EOL;

        $html .= '</div>' . PHP_EOL;

        return $html;
    }

    /**
     * Generate detail panel section
     */
    private function generateDetailPanel(): string
    {
        $html = '<main class="detail-panel" id="detailPanel">' . PHP_EOL;

        // Detail toolbar (add, save, cancel, copy, delete, preview, extra buttons)
        $html .= $this->generateDetailToolbar();

        // Detail loader
        $html .= '<lib-loader id="detailLoader" delay="500" text="Laden..." overlay></lib-loader>' . PHP_EOL;

        // Empty state
        $html .= '<div class="no-data" id="noDataMessage">' . PHP_EOL;
        if (Application::get('CMA_Language', '') == 'UK') {
            $html .= 'Please select a record from the list to edit';
            if ($this->formDef->allowAdd() && $this->accessLevel >= SecurityHelper::ACCESS_FULL) {
                $html .= ", or click 'Add' to add a record.";
            }
        } else {
            $html .= 'Selecteer een record uit de linker lijst om te ' .
                ($this->accessLevel == SecurityHelper::ACCESS_READ ? 'bekijken' : 'wijzigen');
            if ($this->formDef->allowAdd() && $this->accessLevel >= SecurityHelper::ACCESS_FULL) {
                $html .= ", of klik op 'Toevoegen' om een nieuw record aan te maken";
            }
        }
        $html .= '</div>' . PHP_EOL;

        // Detail form container - visibility controlled via CSS based on body.has-record or body.is-creating classes
        $html .= '<div class="detail-content" id="detailContent">' . PHP_EOL;

        // Last modified info (if enabled)
        if ($this->formDef->hasStoreLastModified()) {
            $html .= '<div class="last-modified" id="lastModified" style="display:none">' . PHP_EOL;
            $html .= '<span class="modified-label">Laatst gewijzigd: </span>' . PHP_EOL;
            $html .= '<span class="modified-user" id="modifiedUser"></span>' . PHP_EOL;
            $html .= '<span class="modified-date" id="modifiedDate"></span>' . PHP_EOL;
            $html .= '</div>' . PHP_EOL;
        }

        // Main form
        $html .= '<form name="main" id="mainForm" method="post" autocomplete="off" data-show-tooltip="N">' . PHP_EOL;

        // Hidden fields for form state
        $html .= '<input type="hidden" name="actie" id="actie">' . PHP_EOL;
        $html .= '<input type="hidden" name="action_close" id="action_close" value="Y">' . PHP_EOL;

        // Changelog fields for notifications
        $html .= '<textarea id="_changelog" style="display:none" name="_changelog"></textarea>' . PHP_EOL;
        $html .= '<input type="hidden" id="_changelog_flds" name="_changelog_flds">' . PHP_EOL;
        $html .= '<input type="hidden" id="_changelog_type" name="_changelog_type">' . PHP_EOL;
        $html .= '<input type="hidden" id="_changelog_email" name="_changelog_email">' . PHP_EOL;
        $html .= '<input type="hidden" id="_changelog_user" name="_changelog_user" value="' . Server::htmlEncode(SecurityHelper::getCurrentUserName()) . '">' . PHP_EOL;
        $html .= '<input type="hidden" id="_changelog_form" name="_changelog_form" value="' . Server::htmlEncode($this->formDef->getFormName() ?: '') . '">' . PHP_EOL;
        $html .= '<input type="hidden" id="_changelog_formname" name="_changelog_formname" value="' . Server::htmlEncode($this->jsonFormName ?? '') . '">' . PHP_EOL;
        $html .= '<input type="hidden" id="_changelog_copy" name="_changelog_copy" value="">' . PHP_EOL;
        $html .= '<input type="hidden" id="_changelog_copy_id" name="_changelog_copy_id" value="">' . PHP_EOL;

        // Parent field support (for subforms/popups)
        $parentField = Request::query('parentField', '');
        $parentID = Request::query('parentID', '');
        if ($parentField !== '') {
            $html .= '<input type="hidden" name="__ParentField" value="' . Server::htmlEncode($parentField) . '">' . PHP_EOL;
            $html .= '<input type="hidden" name="__ParentValue" value="' . Server::htmlEncode($parentID) . '">' . PHP_EOL;
        }

        // Required fields tracking (populated dynamically via JS)
        $html .= '<input type="hidden" name="required" id="requiredFields" value="">' . PHP_EOL;

        // Full load check field (ensures complete form submission)
        $html .= '<input type="hidden" name="' . FormControlHelper::FULL_LOAD_CHK_FIELD . '" value="Y">' . PHP_EOL;

        // Form fields table
        $html .= '<table id="maintable" cellspacing="0" cellpadding="0">' . PHP_EOL;
        $html .= $this->generateFormFields();
        $html .= '</table>' . PHP_EOL;

        $html .= '</form>' . PHP_EOL;

        $html .= '</div>' . PHP_EOL; // Close detail-content

        // Subform tabs (if any) - MUST be outside detail-content for flexbox layout
        if (Arr::isArray($this->arrSubForms) || $this->arrSubForms instanceof \ArrayAccess) {
            // Horizontal fold bar between detail form and subforms (visibility controlled by CSS via body.has-subform)
            $html .= '<cma-fold class="fold-horizontal" orientation="horizontal" target=".detail-content" min-size="100" max-size="800" storage-key="form_foldH" reverse></cma-fold>' . PHP_EOL;
            $html .= $this->generateSubformTabs();
        }

        $html .= '</main>' . PHP_EOL;

        return $html;
    }

    /**
     * Generate form fields from definition
     */
    private function generateFormFields(): string
    {
        $html = '';
        $groupId = 0;
        $groupRow = 0;
        $isCombining = false; // Track if we're adding fields to an existing row

        // arrRep is ColumnMajorArray where each column index contains an array of values
        // $this->arrRep[\Q_FIELDNAME] returns an array of all field names
        $fieldNames = $this->arrRep[\Q_FIELDNAME] ?? null;

        // Handle both array and ColumnMajorArray (ArrayAccess)
        $rowCount = 0;
        if (Arr::isArray($fieldNames)) {
            $rowCount = count($fieldNames);
        } elseif ($fieldNames instanceof \Countable) {
            $rowCount = count($fieldNames);
        }

        for ($i = 0; $i < $rowCount; $i++) {
            $fieldName = $this->arrRep[\Q_FIELDNAME][$i] ?? null;
            if ($fieldName === null) {
                continue;
            }

            $controlType = (int)($this->arrRep[\Q_CONTROLTYPEID][$i] ?? 0);
            $caption = $this->arrRep[\Q_CAPTION][$i] ?? '';
            $required = $this->toBool($this->arrRep[\Q_ISREQUIRED][$i] ?? false);
            $readonly = $this->toBool($this->arrRep[\Q_FLDREADONLY][$i] ?? false);
            $isBeheer = $this->toBool($this->arrRep[\Q_BEHEER][$i] ?? false);
            $actie = $this->arrRep[\Q_ACTIE][$i] ?? '';
            $postCaption = $this->arrRep[\Q_POSTCAPTION][$i] ?? '';
            // combineWithNext disabled - too many edge cases with row closing
            // TODO: Re-implement combineWithNext (side-by-side fields) properly
            $combineWithNext = false; // $this->toBool($this->arrRep[\Q_KEEPWITHNEXT][$i] ?? false);

            // Skip beheer fields for non-beheer users
            if ($isBeheer && $this->accessLevel != SecurityHelper::ACCESS_FULL_BEHEER) {
                continue;
            }

            // Skip hidden control types
            if ($controlType == FormRenderer::TYPE_HTMLSTRIP || $controlType == FormRenderer::TYPE_THUMBNAIL || $controlType == FormRenderer::TYPE_IGNOREFIELD) {
                continue;
            }

            // Handle group separator
            if ($controlType == FormRenderer::TYPE_GROUPSEPARATOR) {
                // Add groupbox-end row if there was a previous groupbox
                if ($groupId > 0) {
                    $html .= '<tr class="groupbox-end" data-group-row="' . $groupId . '"><td colspan="99"></td></tr>';
                }

                $groupId++;
                $groupRow = 0;

                $html .= FormRenderer::renderGroupSeparator($fieldName, [
                    'caption' => $caption,
                    'groupId' => $groupId,
                    'collapsed' => false,
                    'sourceFormId' => $this->sourceFormId,
                ]);
                continue;
            }

            // Build control config
            $config = $this->buildControlConfig($i, $controlType);

            // Apply user-level readonly if access is read-only (not full or full_beheer)
            // ACCESS_READ = 10, ACCESS_CHANGE_OWN_DATA = 20, ACCESS_FULL = 30, ACCESS_FULL_BEHEER = 40
            if ($this->accessLevel <= SecurityHelper::ACCESS_READ) {
                $config['readonly'] = true;
            }

            // Check for custom renderer (JSON forms have Q_RENDERER column)
            // sourceFormId=0 means it's a pure JSON form without database backing
            $renderer = ($this->sourceFormId === 0) ? ($this->arrRep[\Q_RENDERER][$i] ?? '') : '';
            $controlHtml = '';

            if (!empty($renderer)) {
                // Use custom renderer for special field types
                $rendererOptions = $this->arrRep[\Q_RENDEROPTIONS][$i] ?? '';
                if ($rendererOptions) {
                    $config['options'] = json_decode($rendererOptions, true) ?? [];
                }
                $config['sql'] = $this->arrRep[\Q_SQLLIST][$i] ?? '';
                // For template rendering, recordId is not available yet - render placeholder
                $controlHtml = '<div class="custom-renderer" data-renderer="' . htmlspecialchars($renderer) . '" '
                    . 'data-field="' . htmlspecialchars($fieldName) . '">'
                    . '<div class="loading-placeholder"><span class="lnr lnr-sync spin-animation"></span> Laden...</div>'
                    . '</div>';
            } else {
                // Render standard control
                $controlHtml = FormRenderer::renderControl($controlType, $fieldName, $config);
            }

            // Update group row
            if ($groupId > 0) {
                $groupRow++;
            }

            // Add hidden field label input if caption differs from field name (for changelog)
            $hiddenInputs = '';
            if ($fieldName !== '' && strtolower($caption) !== strtolower($fieldName)) {
                $hiddenInputs .= '<input type="hidden" name="' . Server::htmlEncode($fieldName) . '__label" value="' . Server::htmlEncode($caption) . '">';
            }

            // Add _old_ input for passOnToPost fields (tracks original values for notifications)
            $passOnToPost = $this->toBool($this->arrRep[\Q_PASSONTOPOST][$i] ?? false);
            if ($passOnToPost) {
                $hiddenInputs .= '<input type="hidden" name="_old_' . Server::htmlEncode($fieldName) . '" value="" data-track-original="true">';
            }

            // Append hidden inputs to control HTML
            $controlHtml .= $hiddenInputs;

            // Handle combining fields (side-by-side layout)
            if ($isCombining) {
                // Add this field inline with the previous one
                $html .= '<div class="next_col">';
                $html .= '<span>' . Server::htmlEncode($caption) . '</span>';
                $html .= $controlHtml;
                $html .= '</div>';

                // Check if we should continue combining or close the row
                if (!$combineWithNext) {
                    $html .= '</td></tr>' . PHP_EOL;
                    $isCombining = false;
                }
            } else {
                // Render normal row
                $html .= FormRenderer::renderFormRow($fieldName, $caption, $controlHtml, [
                    'required' => $required,
                    'beheer' => $isBeheer,
                    'actie' => $actie,
                    'postCaption' => $postCaption,
                    'combineWithNext' => $combineWithNext,
                    'groupId' => $groupId,
                    'groupRow' => $groupRow,
                    'controlType' => $controlType,
                    'maxLength' => $config['maxLength'] ?? 0,
                    'dataType' => $config['dataType'] ?? '',
                ]);

                // If this field has combineWithNext, next field will be added inline
                // But we need to remove the closing </td></tr> from the row
                if ($combineWithNext) {
                    // Strip the closing tags using regex to handle variations
                    $html = preg_replace('/<\/td>\s*<\/tr>\s*$/i', '', $html);
                    $isCombining = true;
                }
            }
        }

        // Close any open combining row (if last field had combineWithNext=true)
        if ($isCombining) {
            $html .= '</td></tr>' . PHP_EOL;
        }

        // Add final groupbox-end if there was at least one groupbox
        if ($groupId > 0) {
            $html .= '<tr class="groupbox-end" data-group-row="' . $groupId . '"><td colspan="99"></td></tr>';
        }

        return $html;
    }

    /**
     * Build control configuration from form definition
     */
    private function buildControlConfig(int $index, int $controlType): array
    {
        $config = [
            'required' => $this->toBool($this->arrRep[\Q_ISREQUIRED][$index] ?? false),
            'readonly' => $this->toBool($this->arrRep[\Q_FLDREADONLY][$index] ?? false),
            'newChangableOnly' => $this->toBool($this->arrRep[\Q_NEWCHANGABLEONLY][$index] ?? false),
            'height' => (int)($this->arrRep[\Q_HEIGHT][$index] ?? 1),
            'caption' => $this->arrRep[\Q_CAPTION][$index] ?? '',
            'postCaption' => $this->arrRep[\Q_POSTCAPTION][$index] ?? '',
            // Include dataType for all controls - used for boolean formatting in labels (dataType 11 = Yes/No)
            'dataType' => $this->arrRep[\Q_SCHEMA_DATATYPE][$index] ?? '',
        ];

        // Control type specific config
        switch ($controlType) {
            case FormRenderer::TYPE_TEXTBOX:
            case FormRenderer::TYPE_EMAIL:
            case FormRenderer::TYPE_DIRECTORY:
            case FormRenderer::TYPE_PASSWORD:
                // Check if this is a date field:
                // 1. schema_date_prec is set (legacy detection from database schema)
                // 2. schema_datatype indicates date type (from JSON dataType or database)
                // 3. ADO numeric data types for dates (7=adDate, 133=adDBDate, 135=adDBTimeStamp)
                $datePrecValue = $this->arrRep[\Q_SCHEMA_DATE_PREC][$index] ?? null;
                $schemaDataType = strtolower($this->arrRep[\Q_SCHEMA_DATATYPE][$index] ?? '');

                $hasDateSchema = ($datePrecValue !== null && $datePrecValue !== '');
                $hasDateDataType = in_array($schemaDataType, ['date', 'datetime', 'datetime2', 'smalldatetime']);
                // Check for ADO numeric date types (JSON forms use numeric dataType values)
                $schemaDataTypeInt = is_numeric($schemaDataType) ? (int)$schemaDataType : 0;
                $hasAdoDateType = in_array($schemaDataTypeInt, AdoType::DATE_TYPES);

                $config['isDate'] = $hasDateSchema || $hasDateDataType || $hasAdoDateType;

                // Detect datetime vs pure date fields
                // datetime fields get a separate time input next to the date field
                $isDateTime = in_array($schemaDataType, ['datetime', 'datetime2', 'smalldatetime']);
                if (!$isDateTime && in_array($schemaDataTypeInt, [135])) { // 135 = adDBTimeStamp
                    $isDateTime = true;
                }
                $config['isDateTime'] = $isDateTime;

                if ($config['isDate']) {
                    // Date field: fixed maxLength of 10 and validation type 'datum'
                    $config['maxLength'] = 10;
                    $config['validationType'] = 'datum';
                } else {
                    // Non-date field: use schema character length
                    $config['maxLength'] = (int)($this->arrRep[\Q_SCHEMA_CHAR_MAXL][$index] ?? 50);
                    if ($config['maxLength'] == 0) {
                        $config['maxLength'] = 50;
                    }
                    $config['validationType'] = $this->getValidationType(
                        $this->arrRep[\Q_FIELDNAME][$index] ?? '',
                        $this->arrRep[\Q_SCHEMA_NUM_PREC][$index] ?? null,
                        $this->arrRep[\Q_SCHEMA_DATATYPE][$index] ?? null
                    );
                }
                break;

            case FormRenderer::TYPE_DATE:
                // Detect datetime vs pure date fields
                // datetime fields get a separate time input next to the date field
                $schemaDataType = strtolower($this->arrRep[\Q_SCHEMA_DATATYPE][$index] ?? '');
                $schemaDataTypeInt = is_numeric($schemaDataType) ? (int)$schemaDataType : 0;
                $isDateTime = in_array($schemaDataType, ['datetime', 'datetime2', 'smalldatetime']);
                if (!$isDateTime && in_array($schemaDataTypeInt, [135])) { // 135 = adDBTimeStamp
                    $isDateTime = true;
                }
                $config['isDateTime'] = $isDateTime;
                break;

            case FormRenderer::TYPE_COMBOBOX:
            case FormRenderer::TYPE_USERLIST:
            case FormRenderer::TYPE_XMLSTORE:
                $config['sourceTable'] = $this->arrRep[\Q_SOURCETABLE][$index] ?? '';
                $config['idField'] = $this->arrRep[\Q_CTRLIDFIELD][$index] ?? '';
                $config['displayField'] = $this->arrRep[\Q_FOREIGNIDFIELD][$index] ?? '';
                $config['sqlList'] = $this->arrRep[\Q_SQLLIST][$index] ?? '';
                $config['databaseId'] = $this->arrRep[\Q_DATABASEID][$index] ?? '';
                $config['isDynamic'] = false; // Will be determined at runtime
                $config['ajaxUrl'] = 'api/form_combo.php?sourceFormId=' . $this->sourceFormId .
                    '&field=' . urlencode($this->arrRep[\Q_FIELDNAME][$index] ?? '');
                $config['sourceFormId'] = $this->sourceFormId;

                // Inline options for static dropdown/combobox (stored as JSON in Q_RENDEROPTIONS)
                $inlineOptionsJson = $this->arrRep[\Q_RENDEROPTIONS][$index] ?? '';
                if (!empty($inlineOptionsJson)) {
                    $config['options'] = json_decode($inlineOptionsJson, true) ?? [];
                }

                // filterByField: cascading combo filter from JSON field definition
                $jsonFields = $this->arrRep['_json']['fields'] ?? [];
                $jsonField = $jsonFields[$index] ?? [];
                if (!empty($jsonField['filterByField'])) {
                    $config['filterByField'] = $jsonField['filterByField'];
                }

                // Lookup related form for "add new" button (only for regular combobox)
                // DISABLED: Feature not fully implemented - see todo.md
                // if ($controlType === FormRenderer::TYPE_COMBOBOX && !empty($config['sourceTable'])) {
                //     $config['addFormId'] = $this->getRelatedFormId($config['sourceTable']);
                // }
                break;

            case FormRenderer::TYPE_MEMO:
                $config['allowHtml'] = $this->toBool($this->arrRep[\Q_HTMLTAGS][$index] ?? false);
                $config['limitedHtml'] = $this->toBool($this->arrRep[\Q_FLDLIMITEDHTML][$index] ?? false);
                $config['maxChars'] = (int)($this->arrRep[\Q_FLDMAXCHARS][$index] ?? 0);
                $config['noSpamJs'] = $this->toBool($this->arrRep[\Q_NOSPAMJS][$index] ?? false);
                // Support JSON dataType for formatted JSON textareas
                $config['dataType'] = $this->arrRep[\Q_SCHEMA_DATATYPE][$index] ?? '';
                // Pass useContentBlocks from JSON field definition to renderer
                // This allows fields to explicitly disable content block editing
                $jsonFields = $this->arrRep['_json']['fields'] ?? [];
                $jsonField = $jsonFields[$index] ?? [];
                if (isset($jsonField['useContentBlocks'])) {
                    $config['useContentBlocks'] = $jsonField['useContentBlocks'];
                }
                break;

            case FormRenderer::TYPE_CHECKLIST:
                $config['controlId'] = $this->arrRep[\Q_CONTROLID][$index] ?? '';
                $config['width'] = (int)($this->arrRep[\Q_CHKLISTWIDTH][$index] ?? 200);
                $config['sqlList'] = $this->arrRep[\Q_SQLLIST][$index] ?? '';
                break;

            case FormRenderer::TYPE_IMAGE:
                $config['imagePath'] = $this->arrRep[\Q_IMGPATH][$index] ?? '';
                $config['resizeType'] = (int)($this->arrRep[\Q_IMGRESIZETYPE][$index] ?? 0);
                $config['resizeWidth'] = (int)($this->arrRep[\Q_IMGRESIZEWIDTH][$index] ?? 0);
                $config['resizeHeight'] = (int)($this->arrRep[\Q_IMGRESIZEHEIGHT][$index] ?? 0);
                $config['randomName'] = $this->toBool($this->arrRep[\Q_FILERANDOM][$index] ?? false);
                $config['widthField'] = $this->arrRep[\Q_IMGWIDTHFLD][$index] ?? '';
                $config['heightField'] = $this->arrRep[\Q_IMGHEIGHTFLD][$index] ?? '';
                break;

            case FormRenderer::TYPE_FILE:
                $config['filePath'] = $this->arrRep[\Q_IMGPATH][$index] ?? '';
                $config['randomName'] = $this->toBool($this->arrRep[\Q_FILERANDOM][$index] ?? false);
                break;

            case FormRenderer::TYPE_URL:
                $config['maxLength'] = (int)($this->arrRep[\Q_SCHEMA_CHAR_MAXL][$index] ?? 255);
                break;

            case FormRenderer::TYPE_RADIOGROUP:
                // Radio group options stored as JSON in Q_RENDEROPTIONS
                $optionsJson = $this->arrRep[\Q_RENDEROPTIONS][$index] ?? '';
                if ($optionsJson) {
                    $config['options'] = json_decode($optionsJson, true) ?? [];
                }
                $config['defaultValue'] = $this->arrRep[\Q_SCHEMA_DEFAULT][$index] ?? '';
                break;
        }

        return $config;
    }

    /**
     * Get validation type based on field name and schema
     */
    private function getValidationType(string $fieldName, $numericPrecision, $dataType = null): string
    {
        $fieldLower = strtolower($fieldName);

        if ($fieldLower === 'postcode') {
            return 'postalcode';
        }
        if ($fieldLower === 'telefoon' || strpos($fieldLower, 'phone') !== false) {
            return 'telephone';
        }
        if ($fieldLower === 'adres' || strpos($fieldLower, 'address') !== false) {
            return 'address';
        }
        if ($fieldLower === 'email' || $fieldLower === 'emailadres' || strpos($fieldLower, 'e-mail') !== false || strpos($fieldLower, 'email') !== false) {
            return 'email';
        }
        if ($numericPrecision !== null && $numericPrecision !== '') {
            return 'number';
        }
        // Check dataType for numeric types (from JSON form definitions)
        if ($dataType !== null) {
            $dataTypeLower = strtolower((string)$dataType);
            if (in_array($dataTypeLower, ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'decimal', 'numeric', 'float', 'real', 'money', 'number'])) {
                return 'number';
            }
        }

        return '';
    }

    /**
     * Get related form ID for a source table (for combo "add new" button)
     * Cached per source table to avoid repeated queries
     *
     * @param string $sourceTable Source table name
     * @return int|null Form ID if found, null otherwise
     */
    private function getRelatedFormId(string $sourceTable): ?int
    {
        static $cache = [];

        $sourceTable = strtoupper(trim($sourceTable));
        if ($sourceTable === '') {
            return null;
        }

        if (isset($cache[$sourceTable])) {
            return $cache[$sourceTable];
        }

        // Use JSON-based table-to-form mapping instead of repository query
        $tableToFormMap = JsonFormLoader::getTableToFormMap();

        if (isset($tableToFormMap[$sourceTable])) {
            $formId = $tableToFormMap[$sourceTable];
            // Don't show add button if it's the same form
            if ($formId !== $this->sourceFormId) {
                // Check if current user has access to add form
                $userId = (int) Cookie::get(SecurityHelper::COOKIE_USERID, 0);
                if (SecurityHelper::checkFormRights($userId, $formId)) {
                    $cache[$sourceTable] = $formId;
                    return $formId;
                }
            }
        }

        $cache[$sourceTable] = null;
        return null;
    }

    /**
     * Build subforms array from JSON subforms definition
     *
     * Converts JSON subforms array to the legacy format expected by generateSubformTabs.
     * This enables JSON config forms (like _menus) to have subforms without needing
     * a sourceFormId in the database.
     *
     * @param array $jsonSubforms Array of subform definitions from JSON
     * @return array Legacy subforms array format with SUBFORM_* constants as keys
     */
    private function buildSubformsFromJson(array $jsonSubforms): array
    {
        $result = [
            \SUBFORM_TABLE => [],
            \SUBFORM_ID => [],
            \SUBFORM_CONN => [],
            \SUBFORM_IDFLD => [],
            \SUBFORM_QUERY => [],
            \SUBFORM_NAME => [],
            \SUBFORM_DETAIL => [],
            \SUBFORM_PARENT => [],
            \SUBFORM_SECUSER => [],
            \SUBFORM_MENUNEW => [],
            \SUBFORM_GROUPFLD => [],
            \SUBFORM_FULLWIDTH => [],
            \SUBFORM_ACTIE => [],
            \SUBFORM_BEHEER => [],
            \SUBFORM_JSONFORM => [],
        ];

        foreach ($jsonSubforms as $subform) {
            // Skip disabled subforms
            if (!empty($subform['_disabled'])) {
                continue;
            }

            // Use formName as the subform ID (for JSON forms)
            // Support multiple key names: 'form', 'formName', 'name'
            $formName = $subform['form'] ?? $subform['formName'] ?? $subform['name'] ?? '';
            if (empty($formName)) {
                continue;
            }

            $result[\SUBFORM_TABLE][] = '';
            $result[\SUBFORM_ID][] = $formName; // Use form name as ID
            $result[\SUBFORM_CONN][] = '';
            $result[\SUBFORM_IDFLD][] = $subform['linkField'] ?? '';
            $result[\SUBFORM_QUERY][] = '';
            $result[\SUBFORM_NAME][] = $subform['title'] ?? $formName;
            $result[\SUBFORM_DETAIL][] = '';
            $result[\SUBFORM_PARENT][] = $subform['parentIdField'] ?? $subform['parentField'] ?? 'id';
            $result[\SUBFORM_SECUSER][] = false;
            $result[\SUBFORM_MENUNEW][] = true;
            $result[\SUBFORM_GROUPFLD][] = '';
            $result[\SUBFORM_FULLWIDTH][] = $subform['fullWidth'] ?? false;
            $result[\SUBFORM_ACTIE][] = '';
            $result[\SUBFORM_BEHEER][] = $subform['beheer'] ?? false;
            $result[\SUBFORM_JSONFORM][] = $formName; // JSON form name for subform loading
        }

        return $result;
    }

    /**
     * Generate subform tabs section
     */
    private function generateSubformTabs(): string
    {
        if (!Arr::isArray($this->arrSubForms) && !($this->arrSubForms instanceof \ArrayAccess)) {
            return '';
        }

        // Count visible subforms (excluding beheer subforms for non-beheer users)
        $subformCount = count($this->arrSubForms[\SUBFORM_ID] ?? []);
        $visibleSubforms = [];
        for ($i = 0; $i < $subformCount; $i++) {
            $isBeheer = (bool)($this->arrSubForms[\SUBFORM_BEHEER][$i] ?? false);
            // Skip beheer subforms for non-beheer users
            if ($isBeheer && $this->accessLevel != SecurityHelper::ACCESS_FULL_BEHEER) {
                continue;
            }
            $visibleSubforms[] = $i;
        }

        // Don't render subform section if no visible subforms
        if (empty($visibleSubforms)) {
            return '';
        }

        // Subform section - visibility controlled by CSS via body.has-subforms-defined / body.has-subform
        // Initially shows loading state, then tabs when data loads
        $html = '<div class="subform-section" id="subformSection">' . PHP_EOL;

        // Loading indicator shown until subform data loads
        $html .= '<div class="subform-loading" id="subformLoading">' . PHP_EOL;
        $html .= '  <lib-loader active text="Laden..."></lib-loader>' . PHP_EOL;
        $html .= '</div>' . PHP_EOL;

        // Tab headers using cma-tabs web component
        $html .= '<cma-tabs id="subformTabs" selected="0">' . PHP_EOL;

        foreach ($visibleSubforms as $i) {
            $subformId = $this->arrSubForms[\SUBFORM_ID][$i] ?? '';
            $subformName = $this->arrSubForms[\SUBFORM_NAME][$i] ?? '';
            $isBeheer = (bool)($this->arrSubForms[\SUBFORM_BEHEER][$i] ?? false);

            $html .= '<tab-item title="' . Server::htmlEncode($subformName) . '" ';
            $html .= 'data-id="' . $subformId . '" ';
            $html .= 'data-index="' . $i . '" ';
            $html .= 'data-count="."';  // Show placeholder until count is loaded
            if ($isBeheer) {
                $html .= ' beheer';
            }
            $html .= '></tab-item>' . PHP_EOL;
        }

        $html .= '</cma-tabs>' . PHP_EOL;

        // Tab content container - panes are created dynamically by JavaScript
        $html .= '<div class="subform-content" id="subformContent"></div>' . PHP_EOL;
        $html .= '</div>' . PHP_EOL;

        return $html;
    }

    /**
     * Generate loading overlay
     */
    private function generateLoadingOverlay(): string
    {
        return '<div class="loading-overlay" id="loadingOverlay" style="display:none">' . PHP_EOL .
            '<lib-loader active text="Laden..."></lib-loader>' . PHP_EOL .
            '</div>' . PHP_EOL;
    }

    /**
     * Generate error template
     */
    private function generateErrorTemplate(string $message): string
    {
        return '<!DOCTYPE html><html><head><title>Error</title></head>' .
            '<body><lib-message type="error">' . Server::htmlEncode($message) . '</lib-message></body></html>';
    }

    /**
     * Update the manifest file
     */
    private static function updateManifest(array $updates): void
    {
        $manifestPath = self::TEMPLATE_DIR . '/manifest.json';
        $manifest = [];

        if (file_exists($manifestPath)) {
            $content = file_get_contents($manifestPath);
            $manifest = json_decode($content, true) ?? [];
        }

        $manifest['templates'] = array_merge($manifest['templates'] ?? [], $updates);
        $manifest['lastUpdate'] = date('c');

        @file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));
    }

    /**
     * Cache for form feature flags (computed once in single scan)
     */
    private ?array $formFeaturesCache = null;

    /**
     * Scan form once and cache all feature detection flags
     * OPTIMIZATION: Single pass instead of 3 separate scans
     */
    private function detectFormFeatures(): array
    {
        if ($this->formFeaturesCache !== null) {
            return $this->formFeaturesCache;
        }

        $features = [
            'controlTypes' => [],  // Set of all control types present
            'hasDateFields' => false,
            'hasMemoWithHtml' => false,
            'hasMemoWithBlockEdit' => false,  // Memo with HTML but NOT limited (for content blocks)
        ];

        // Check JSON form definition first (for pure JSON forms)
        if ($this->formDef !== null && $this->formDef->isJsonForm()) {
            $jsonData = $this->formDef->getJsonData();
            if ($jsonData !== null && !empty($jsonData['fields'])) {
                foreach ($jsonData['fields'] as $field) {
                    $fieldType = strtolower($field['type'] ?? '');

                    // Track control types
                    if ($fieldType === 'memo') {
                        $features['controlTypes'][FormRenderer::TYPE_MEMO] = true;
                        // Check for memo with HTML (support both 'allowHtml' and 'html' properties)
                        // Readonly HTML memos render as a div, so they don't need CKEditor
                        $isReadonly = !empty($field['readOnly']) || !empty($field['readonly']);
                        if ((!empty($field['allowHtml']) || !empty($field['html'])) && !$isReadonly) {
                            $features['hasMemoWithHtml'] = true;
                            // Check for block edit (HTML without limited)
                            if (empty($field['limitedHtml'])) {
                                $features['hasMemoWithBlockEdit'] = true;
                            }
                        }
                    } elseif ($fieldType === 'textbox') {
                        $features['controlTypes'][FormRenderer::TYPE_TEXTBOX] = true;
                    } elseif (in_array($fieldType, ['combobox', 'combo', 'dropdown'])) {
                        $features['controlTypes'][FormRenderer::TYPE_COMBOBOX] = true;
                    } elseif ($fieldType === 'checkbox') {
                        $features['controlTypes'][FormRenderer::TYPE_CHECKBOX] = true;
                    } elseif ($fieldType === 'image') {
                        $features['controlTypes'][FormRenderer::TYPE_IMAGE] = true;
                    } elseif ($fieldType === 'date') {
                        $features['hasDateFields'] = true;
                    }

                    // Check for date dataType
                    $dataType = strtolower($field['dataType'] ?? '');
                    if (in_array($dataType, ['date', 'datetime'])) {
                        $features['hasDateFields'] = true;
                    }
                }
            }
            $this->formFeaturesCache = $features;
            return $features;
        }

        // Legacy database form - check arrRep
        if ($this->arrRep === null) {
            $this->formFeaturesCache = $features;
            return $features;
        }

        $controlTypeCol = $this->arrRep[\Q_CONTROLTYPEID] ?? null;
        $rowCount = $this->getColumnCount($controlTypeCol);

        for ($i = 0; $i < $rowCount; $i++) {
            $controlType = (int)($this->arrRep[\Q_CONTROLTYPEID][$i] ?? 0);

            // Track all control types
            $features['controlTypes'][$controlType] = true;

            // Check for date fields using ADO type codes (7=Date, 133=DBDate, 135=DBTimeStamp)
            if (!$features['hasDateFields']) {
                $schemaType = $this->arrRep[\Q_SCHEMA_DATATYPE][$i] ?? '';
                if (is_numeric($schemaType) && in_array((int)$schemaType, [7, 133, 135])) {
                    $features['hasDateFields'] = true;
                } elseif (in_array(strtolower((string)$schemaType), ['date', 'datetime', 'datetime2', 'smalldatetime', 'datetimeoffset'])) {
                    $features['hasDateFields'] = true;
                }
            }

            // Check for memo with HTML
            if ($controlType === FormRenderer::TYPE_MEMO) {
                $allowHtml = $this->toBool($this->arrRep[\Q_HTMLTAGS][$i] ?? false);
                if ($allowHtml) {
                    $features['hasMemoWithHtml'] = true;
                    // Check for block edit (HTML without limited)
                    $limitedHtml = $this->toBool($this->arrRep[\Q_FLDLIMITEDHTML][$i] ?? false);
                    if (!$limitedHtml) {
                        $features['hasMemoWithBlockEdit'] = true;
                    }
                }
            }
        }

        $this->formFeaturesCache = $features;
        return $features;
    }

    /**
     * Check if form has any of the specified control types
     *
     * @param array $controlTypes Array of control type IDs
     * @return bool
     */
    private function hasControlType(array $controlTypes): bool
    {
        $features = $this->detectFormFeatures();
        foreach ($controlTypes as $type) {
            if (isset($features['controlTypes'][$type])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if form has any date fields
     *
     * @return bool
     */
    private function hasDateFields(): bool
    {
        return $this->detectFormFeatures()['hasDateFields'];
    }

    /**
     * Check if form has memo fields with HTML enabled
     *
     * @return bool
     */
    private function hasMemoWithHtml(): bool
    {
        return $this->detectFormFeatures()['hasMemoWithHtml'];
    }

    /**
     * Check if form has any memo field with HTML that uses block editing
     * (allowHtml=true AND limitedHtml=false)
     */
    private function hasMemoWithBlockEdit(): bool
    {
        return $this->detectFormFeatures()['hasMemoWithBlockEdit'];
    }

    /**
     * Get count from a column (handles both array and ArrayAccess/Countable)
     */
    private function getColumnCount($column): int
    {
        if ($column === null) {
            return 0;
        }
        if (Arr::isArray($column)) {
            return count($column);
        }
        if ($column instanceof \Countable) {
            return count($column);
        }
        return 0;
    }

    /**
     * Convert database value to boolean
     * Delegates to ListServiceHelper::toBool() (single canonical implementation)
     */
    private function toBool($value): bool
    {
        return ListServiceHelper::toBool($value);
    }

    /**
     * Clear all cached templates
     */
    public static function clearAllCache(): void
    {
        self::invalidateAll();
    }
}
