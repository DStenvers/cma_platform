<?php
/**
 * CMA Sidebar Layout
 *
 * WordPress-style sidebar menu for CMA.
 * Replaces the classic frameset layout when cma_menu_style='sidebar'.
 *
 * This file serves as the main application shell with:
 * - Collapsible sidebar navigation on the left
 * - Content area on the right that loads pages dynamically via AJAX
 * - Header with logo and user info
 *
 * Parameters:
 * - page: The page to load in the content area
 * - nomenu: If set, proxy the requested page content without the sidebar shell
 */

use App\Library\Application;
use App\Library\Cookie;
use App\Library\Database;
use App\Library\Html;
use App\Library\Request;
use App\Library\Response;
use App\Library\Server;
use Cma\JsonFormLoader;
use Cma\SecurityHelper;

require_once __DIR__ . '/bootstrap.inc';

// CMA version from migrations config
$cmaVersion = '';
$migrationsFile = __DIR__ . '/config/migrations.json';
if (file_exists($migrationsFile)) {
    $migConfig = json_decode(file_get_contents($migrationsFile), true);
    if ($migConfig && !empty($migConfig['targetVersion'])) {
        $cmaVersion = $migConfig['targetVersion'];
    }
}

// Must be logged in
if (!SecurityHelper::isLoggedIn()) {
    // For AJAX requests (nomenu mode), return a proper error message instead of redirect
    if (Request::hasQuery('nomenu')) {
        http_response_code(401);
        echo '<div class="session-expired-message" style="padding: 40px; text-align: center;">';
        echo '<lib-message type="warning"><strong>Sessie verlopen</strong><br>Je sessie is verlopen of cookies zijn niet beschikbaar.</lib-message>';
        echo '<a href="/cma/default.php" class="btn btn-primary" onclick="window.top.location.href=this.href; return false;" style="margin-top: 16px;">Opnieuw inloggen</a>';
        echo '</div>';
        exit;
    }
    header('Location: default.php');
    exit;
}

// Handle nomenu mode - proxy content from requested page
if (Request::hasQuery('nomenu')) {
    // Don't set cache headers here - let included pages decide their own caching
    // This prevents error responses from being cached
    // Note: form.php sets its own cache headers when returning successful responses

    $page = Request::query('page', '');
    if (empty($page)) {
        echo '<lib-message type="error">Geen pagina opgegeven</lib-message>';
        exit;
    }

    // Save additional GET parameters (besides 'nomenu' and 'page')
    // These may be passed directly to main.php instead of being encoded in the page URL
    $extraParams = [];
    foreach (Request::queryAll() as $key => $value) {
        if ($key !== 'nomenu' && $key !== 'page') {
            $extraParams[$key] = $value;
        }
    }

    // Parse page URL - extract file and query string
    $queryString = '';
    if (strpos($page, '?') !== false) {
        list($page, $queryString) = explode('?', $page, 2);
    }

    // Security: allow specific subdirectories, prevent directory traversal
    // Remove any directory traversal attempts (../)
    $page = str_replace(['../', '..\\'], '', $page);
    // Only allow tools/ and api/ subdirectories, otherwise use basename
    if (!preg_match('#^(tools|api)/[a-zA-Z0-9_-]+\.php$#', $page)) {
        $page = basename($page);
    }

    // Internal routing shim: Re-add query parameters to $_GET and $_REQUEST
    // so included pages can read them via Request::query(). These writes are
    // intentional - they simulate query string params for the included page.
    if (!empty($queryString)) {
        parse_str($queryString, $params);
        foreach ($params as $key => $value) {
            $_GET[$key] = $value;
            $_REQUEST[$key] = $value;
        }
    }

    // Then merge extra parameters (these override page URL params if duplicated)
    foreach ($extraParams as $key => $value) {
        $_GET[$key] = $value;
        $_REQUEST[$key] = $value;
    }

    // Validate file exists and is PHP
    $filePath = __DIR__ . '/' . $page;
    if (!file_exists($filePath) || pathinfo($page, PATHINFO_EXTENSION) !== 'php') {
        // Never cache error responses
        Response::noCache();
        http_response_code(404);
        $debugInfo = [
            'page' => $page,
            'filePath' => $filePath,
            'exists' => file_exists($filePath),
            'ext' => pathinfo($page, PATHINFO_EXTENSION),
            'queryString' => $queryString,
            'GET' => Request::queryAll(),
            'requestUri' => Request::server('REQUEST_URI'),
        ];
        echo '<div style="padding: 40px; text-align: center;">';
        echo '<lib-message type="warning"><strong>Oeps, die kan ik niet vinden</strong><br>' . Server::htmlEncode($page) . '</lib-message>';
        echo '<details style="text-align: left; max-width: 600px; margin: 20px auto 0;">';
        echo '<summary style="cursor: pointer; color: var(--text-muted, #999); font-size: var(--font-size-sm);">Details</summary>';
        echo '<pre style="background: var(--bg-surface-alt, #f5f5f5); padding: 12px; border-radius: 4px; font-size: var(--font-size-xs); overflow-x: auto; margin-top: 8px;">' . Server::htmlEncode(json_encode($debugInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
        echo '</details>';
        echo '</div>';
        exit;
    }

    // Set flag so included pages know they're in nomenu mode
    define('CMA_NOMENU_MODE', true);

    // Include the page
    include $filePath;
    exit;
}

// Full page with sidebar - don't cache (menu depends on user permissions)
Response::noCache();

$userName = SecurityHelper::getCurrentUserName() ?: 'Gebruiker';
$isAdmin = SecurityHelper::isAdmin();
$isDeveloper = SecurityHelper::isDeveloper();
$isImpersonating = !empty(Cookie::get('CMAU_ORIGINAL', ''));

// Get menu style preference (sidebar or classic)
$appMenuStyle = Application::get('cma_menu_style', 'sidebar');
$menuStyle = Cookie::get('cma_menu_style', $appMenuStyle);
$isClassicMode = ($menuStyle === 'classic');

// Get theme preference
$currentTheme = Cookie::get('cma_theme', 'light');
// For PHP rendering, 'system' defaults to light - JS handles dynamic system preference
$themeClass = ($currentTheme === 'dark') ? 'dark-mode' : '';
$useSystemTheme = ($currentTheme === 'system');

// Get menu items using the same logic as menurep.php
require_once __DIR__ . '/menurep.inc';

// Load application config for logo and background color using centralized function
$appLogoConfig = cma_get_app_logo();
$appLogoPath = $appLogoConfig['logo'] ?? '';
$appLogoUrl = $appLogoConfig['url'] ?? '../';
$appBgColor = $appLogoConfig['backgroundColor'] ?? '#3F096E';

// Environment label
$omgeving = Application::get('omgeving', '');
$envLabel = '';
if ($omgeving === 'P') {
    $envLabel = 'Productie';
} elseif ($omgeving === 'A') {
    $envLabel = 'Acceptatie';
} elseif ($omgeving === 'T' || $omgeving === 'L' || $omgeving === 'O') {
    $envLabel = 'Test';
}

$menuItems = [];

// Menu group icons mapping - based on kader_icon from login.php
$menuGroupIcons = [
    'dashboard' => 'lnr-home',
    'systeem' => 'lnr-cog',
    'beheer' => 'lnr-database',
    'content' => 'lnr-file-add',
    'rapportage' => 'lnr-document',        // e6d8 - document icon
    'rapporten' => 'lnr-document',         // e6d8 - document icon
    'rapportages' => 'lnr-document',       // e6d8 - document icon
    'rapport' => 'lnr-document',           // e6d8 - document icon
    'instellingen' => 'lnr-cog',
    'tools' => 'lnr-construction',
    'utilities' => 'lnr-construction',
    'formulieren' => 'lnr-layers',
    'opleidingen' => 'lnr-graduation-hat',
    'toetsing' => 'lnr-diploma',           // e6db - diploma/certificate
    'toetsen' => 'lnr-file-check',         // e6b5 - file with checkmark
    'opdrachten' => 'lnr-file-check',      // e6b5 - file with checkmark
    'evaluaties' => 'lnr-thumbs-up',
    'rino_info' => 'lnr-clipboard-user',   // e6d0 - user-tailored information
    'marketing_url' => 'lnr-link',
    'marketing' => 'lnr-link',
    'afspraken' => 'lnr-calendar-31',      // e788 - calendar with 31
    'literatuur' => 'lnr-book',
    'personen' => 'lnr-users2',
    'cgo' => 'lnr-file-preview',           // e911 - file preview
    'gesprekken' => 'lnr-group-work',      // e726 - group conversation
    'servicebureau' => 'lnr-site-map',     // e883 - site-map/organization
    'menu' => 'lnr-menu',
    'zoektermen' => 'lnr-magnifier',
    'urls' => 'lnr-link',
    'materialen' => 'lnr-box',
    'artikelen' => 'lnr-file-empty',
    'nieuws' => 'lnr-news',                // e6d5 - news icon
    'agenda' => 'lnr-calendar-31',         // e788 - calendar with 31
    'agendareserveringen' => 'lnr-calendar-31',
    'tijdsblokken' => 'lnr-calendar-31',
    'kalender' => 'lnr-calendar-31',
    'rooster' => 'lnr-calendar-31',        // e788 - calendar with 31
    'tags' => 'lnr-tag',
    'autos' => 'lnr-car',
    'taken' => 'lnr-clipboard-check',      // e6cc - clipboard with checkmark
    'teksten' => 'lnr-papers',             // e6d4 - papers/documents
    'inventarisatie' => 'lnr-inbox',       // e69c - inbox/inventory
    'audit' => 'lnr-clipboard-pencil',     // e6ca - clipboard with pencil
    'audit_log' => 'lnr-clipboard-pencil', // e6ca - clipboard with pencil
    'documenten' => 'lnr-document',        // e6d8 - document icon
];

// Add Dashboard as first menu item
$menuItems[0] = [
    'name' => 'Dashboard',
    'icon' => 'lnr-home',
    'items' => [
        [
            'id' => 0,
            'name' => 'Dashboard',
            'href' => 'dashboard.php',
            'formName' => '',
            'icon' => 'lnr-home'
        ]
    ]
];

$arrMenu = loadMenuData();

if (\App\Library\Arr::isArray($arrMenu)) {
    $currentMenuName = null;
    $menuIndex = 0; // Will be incremented to 1 on first menu group (Dashboard is at 0)

    for ($i = 0; $i < count($arrMenu[MENU_MENUNAME] ?? []); $i++) {
        $menuItemId = $arrMenu[MENU_MENUITEMID][$i] ?? 0;
        $menuName = $arrMenu[MENU_MENUNAME][$i] ?? '';

        // Check access rights for this menu item
        $accessRights = SecurityHelper::checkRights(SecurityHelper::TYPE_MENU, $menuItemId);
        if ($accessRights <= SecurityHelper::ACCESS_NONE) {
            continue;
        }

        // New menu group?
        if ($currentMenuName !== $menuName) {
            $currentMenuName = $menuName;
            $menuIndex++;
            $menuNameLower = strtolower($menuName);
            $groupIcon = $menuGroupIcons[$menuNameLower] ?? 'lnr-menu';
            $menuItems[$menuIndex] = [
                'name' => $menuName,
                'icon' => $groupIcon,
                'items' => []
            ];
        }

        // Get form name (JSON filename) and href
        $formName = $arrMenu[MENU_FORMNAME][$i] ?? '';
        $href = $arrMenu[MENU_MENUITEMHREF][$i] ?? '';

        // Get item display name (prefer menuitem name, fallback to form title from JSON)
        $itemName = $arrMenu[MENU_MENUITEMNAME][$i] ?? '';
        if (empty($itemName) && !empty($formName)) {
            // Load form title from JSON
            $formDef = JsonFormLoader::loadRaw($formName);
            $itemName = $formDef['title'] ?? ucfirst($formName);
        }

        // Build href - form-based or direct href
        // dataPage is for AJAX loading (relative URL works with loadPage JS function)
        // href is for direct navigation/bookmarks (absolute URL works from any path)
        $dataPage = '';
        if (!empty($formName)) {
            // Form-based menu item - use relative path for AJAX, absolute for href
            $dataPage = 'form.php?form=' . urlencode($formName);
            $href = '/cma/form/' . urlencode($formName);  // Clean URL for bookmarks/sharing
        } elseif (!empty($href)) {
            // Replace all .asp with .php for converted pages
            $href = str_ireplace('.asp', '.php', $href);
            $dataPage = $href;
        }

        if (!empty($href)) {
            // Determine icon based on item type
            $itemIcon = 'lnr-file-empty';  // Default for forms
            if (!empty($formName)) {
                $itemIcon = 'lnr-file-empty';
            } elseif (stripos($href, 'report') !== false || stripos($href, 'Rep') !== false) {
                $itemIcon = 'lnr-chart-bars';
            } elseif (stripos($href, 'tool') !== false) {
                $itemIcon = 'lnr-cog';
            }

            $menuItems[$menuIndex]['items'][] = [
                'id' => $menuItemId,
                'name' => $itemName,
                'href' => $href,
                'dataPage' => $dataPage,
                'formName' => $formName,
                'icon' => $itemIcon
            ];
        }
    }
}

// Fallback: If no Systeem menu exists in menu.json, create it with Tools link
if (SecurityHelper::isAdmin() || SecurityHelper::isDeveloper()) {
    $systeemFound = false;
    foreach ($menuItems as $menu) {
        if (strtolower($menu['name']) === 'systeem') {
            $systeemFound = true;
            break;
        }
    }

    if (!$systeemFound) {
        $menuItems[] = [
            'name' => 'Systeem',
            'icon' => 'lnr-cog',
            'items' => [
                [
                    'id' => -3,
                    'name' => 'Tools',
                    'href' => 'tools.php',
                    'formName' => '',
                    'icon' => 'lnr-cog'
                ]
            ]
        ];
    }
}

// Get initial content page
$contentPage = Request::query('page', '');
if (empty($contentPage)) {
    // Default to first menu item or blank
    $contentPage = 'about:blank';
    if (!empty($menuItems)) {
        $firstMenu = reset($menuItems);
        if (!empty($firstMenu['items'])) {
            $contentPage = $firstMenu['items'][0]['href'];
        }
    }
} else {
    // Append any extra parameters (besides 'page') to the content page URL
    // This handles URLs like main.php?page=form.php%3Fform%3Dx&FormID=157&ID=1
    $extraParams = [];
    foreach (Request::queryAll() as $key => $value) {
        if ($key !== 'page') {
            $extraParams[$key] = $value;
        }
    }
    if (!empty($extraParams)) {
        $separator = (strpos($contentPage, '?') !== false) ? '&' : '?';
        $contentPage .= $separator . http_build_query($extraParams);
    }
}

$appTitle = Application::get('appname_simple', '') ?: Application::get('Name', 'CMA');
$envPrefix = Application::get('omgeving', '') === 'T' ? 'TEST: ' : (Application::get('omgeving', '') === 'A' ? 'ACC: ' : '');
?>
<!DOCTYPE html>
<html lang="nl" class="<?= $themeClass ?><?= $isClassicMode ? ' classic-mode' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title><?= Server::htmlEncode($envPrefix . 'CMA ' . $appTitle) ?></title>
    <link rel="shortcut icon" href="/favicon.ico">
    <link rel="stylesheet" href="<?= cma_css_url() ?>">
    <?php
    /*
     * CRITICAL: DO NOT CHANGE THIS SCRIPT LOADING ORDER OR ADD DYNAMIC LOADING
     * ========================================================================
     * 1. cma-utils.js - Provides cmaLog fallback (needed by error handler)
     * 2. error-handler.js - MUST load separately, before the bundle, so it catches
     *    SyntaxErrors if the bundle fails to parse (e.g., PHP warnings in minify.php).
     *    Has a double-load guard so the copy inside the bundle is safely skipped.
     * 3. jQuery - required by the bundle
     * 4. Main bundle - all CMA JavaScript (includes error-handler.js again, guarded)
     * DO NOT:
     *   - Remove the standalone error-handler.js load
     *   - Reorder these scripts
     *   - Add dynamic/lazy loading
     * Any such changes will break error catching and form controllers.
     */
    ?>
    <script>window.CMA_IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>; window.CMA_IS_DEVELOPER = <?= $isDeveloper ? 'true' : 'false' ?>; window.CMA_CURRENT_USER_ID = <?= json_encode(SecurityHelper::getCurrentUserId()) ?>;</script>
    <?php cma_script('/cma/assets/js/cma-utils.js'); ?>
    <?php cma_script('/library/error-handler.js'); ?>
    <script src="/library/jquery.min.js"></script>
    <script src="<?= cma_form_js_url() ?>"></script>
    <script src="/cma/ckeditor/ckeditor.js" defer></script>
    <script>
    // CKEditor configuration for HTML editors
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof SetFKEditorConfig === 'function') {
            SetFKEditorConfig({
                customCSS: <?= json_encode(Application::get('cma_htmledit_css', '')) ?>,
                allowBR: <?= Application::get('cma_htmledit_allowBR', '') ? 'false' : 'true' ?>,
                extraPlugins: ''
            });
        }
    });
    </script>
    <?php if ($appBgColor): ?>
    <style>:root { --sidebar-header-bg: <?= $appBgColor ?>; }</style>
    <?php endif; ?>
    <?php if ($useSystemTheme): ?>
    <script>
    // Apply system theme preference before page renders
    (function() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark-mode');
        }
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            document.documentElement.classList.toggle('dark-mode', e.matches);
        });
    })();
    </script>
    <?php endif; ?>
    <?php if ($isImpersonating): ?>
    <script>
    async function returnToOwnLogin() {
        try {
            const response = await fetch('/cma/api/user_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'return_to_self' })
            });
            const result = await response.json();
            if (result.success) {
                window.location.href = result.redirect || 'default.php';
            } else {
                libAlert(result.error || 'Terugkeren mislukt');
            }
        } catch (e) {
            console.error('Return to self error:', e);
            libAlert('Er is een fout opgetreden: ' + e.message);
        }
    }
    </script>
    <?php endif; ?>
</head>
<body>
    <div class="cma-app">
        <aside class="cma-sidebar" id="sidebar">
            <div class="cma-sidebar-header">
                <a href="<?= Server::htmlEncode($appLogoUrl) ?>" class="cma-logo" target="_blank">
                    <?php if (!empty($appLogoPath)): ?>
                    <img src="<?= Server::htmlEncode($appLogoPath) ?>" alt="<?= Server::htmlEncode($appTitle) ?>">
                    <?php else: ?>
                    <span class="cma-logo-text"><?= Server::htmlEncode($appTitle) ?></span>
                    <?php endif; ?>
                </a>
                <button class="cma-toggle-btn" id="sidebarToggle" onclick="toggleSidebar()" title="Menu in-/uitklappen">
                    <span id="toggleIcon" class="lnr lnr-chevron-left"></span>
                </button>
            </div>

            <nav class="cma-sidebar-nav" id="sidebarNav">
                <?php $menuIndex = 0; ?>
                <?php foreach ($menuItems as $menuId => $menu):
                    $isSingleItem = count($menu['items']) === 1;
                    $animDelay = $menuIndex * 50; // 50ms stagger per menu item
                    $menuIndex++;
                ?>
                <?php if ($isSingleItem): ?>
                <div class="cma-menu-group single-item" id="menuGroup-<?= $menuId ?>" data-menu-id="<?= $menuId ?>" style="animation-delay: <?= $animDelay ?>ms">
                    <a class="cma-menu-group-header cma-menu-item" href="<?= Server::htmlEncode($menu['items'][0]['href']) ?>" data-page="<?= Server::htmlEncode($menu['items'][0]['dataPage'] ?? $menu['items'][0]['href']) ?>" data-tooltip="<?= Server::htmlEncode($menu['name']) ?>" data-tooltip-pos="right">
                        <span class="cma-menu-group-icon lnr <?= Server::htmlEncode($menu['icon'] ?? 'lnr-menu') ?>"></span>
                        <span class="cma-menu-group-title"><?= Server::htmlEncode($menu['name']) ?></span>
                    </a>
                </div>
                <?php else: ?>
                <div class="cma-menu-group" id="menuGroup-<?= $menuId ?>" data-menu-id="<?= $menuId ?>" style="animation-delay: <?= $animDelay ?>ms">
                    <div class="cma-menu-group-header" onclick="toggleMenuGroup(<?= $menuId ?>)">
                        <span class="cma-menu-group-icon lnr <?= Server::htmlEncode($menu['icon'] ?? 'lnr-menu') ?>" title="<?= Server::htmlEncode($menu['name']) ?>"></span>
                        <span class="cma-menu-group-title"><?= Server::htmlEncode($menu['name']) ?></span>
                        <span class="cma-menu-group-arrow">&#x25BC;</span>
                    </div>
                    <div class="cma-menu-group-items">
                        <?php foreach ($menu['items'] as $item): ?>
                        <a class="cma-menu-item" href="<?= Server::htmlEncode($item['href']) ?>" data-page="<?= Server::htmlEncode($item['dataPage'] ?? $item['href']) ?>" data-fulltext="<?= Server::htmlEncode($item['name']) ?>">
                            <span class="cma-menu-icon lnr <?= Server::htmlEncode($item['icon'] ?? 'lnr-file-empty') ?>"></span>
                            <span class="cma-menu-item-text"><?= Server::htmlEncode($item['name']) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <!-- Popup menu for collapsed sidebar -->
                    <div class="cma-menu-popup">
                        <div class="cma-menu-popup-title"><?= Server::htmlEncode($menu['name']) ?></div>
                        <?php foreach ($menu['items'] as $item): ?>
                        <a class="cma-menu-popup-item" href="<?= Server::htmlEncode($item['href']) ?>" data-page="<?= Server::htmlEncode($item['dataPage'] ?? $item['href']) ?>">
                            <span class="cma-menu-icon lnr <?= Server::htmlEncode($item['icon'] ?? 'lnr-file-empty') ?>"></span>
                            <span class="cma-menu-popup-text"><?= Server::htmlEncode($item['name']) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </nav>

        </aside>

        <div class="cma-sidebar-backdrop" onclick="toggleSidebar()"></div>

        <main class="cma-main">
            <header class="cma-header">
                <div id="menuToggle">
                    <input type="checkbox" id="menuToggleCheckbox" />
                    <span class="menuToggleHamburger">menu</span>
                </div>
                <div class="cma-breadcrumb" id="breadcrumb" role="navigation" aria-label="Breadcrumb">Dashboard</div>
                <?php if (!empty($envLabel)): ?>
                <lib-label type="information" size="large"><?= Server::htmlEncode($envLabel) ?></lib-label>
                <?php endif; ?>
                <div class="cma-user-info" id="userInfo">
                    <div class="cma-user-menu" id="userMenu">
                        <span class="cma-user-name" id="userName"><?= Server::htmlEncode($userName) ?><?php if ($isImpersonating): ?> <span class="impersonating-label">(ingelogd als)</span><?php endif; ?></span>
                        <div class="cma-user-dropdown" id="userDropdown">
                            <?php if ($isAdmin || $isDeveloper): ?>
                            <div class="cma-user-dropdown-item cma-user-level-item">
                                <span class="cma-dropdown-icon lnr lnr-user"></span>
                                <span class="cma-user-level-label"><?= SecurityHelper::getUserLevelName(SecurityHelper::getUserLevel()) ?></span>
                                <span class="cma-version"><?= htmlspecialchars(CMA_APP_VERSION) ?></span>
                            </div>
                            <div class="cma-user-dropdown-divider"></div>
                            <?php endif; ?>
                            <a href="/cma/preferences" class="cma-user-dropdown-item" id="menuPreferences" onclick="loadPage('preferences.php'); history.pushState(null, '', '/cma/preferences'); return false;">
                                <span class="cma-dropdown-icon lnr lnr-cog"></span>Voorkeuren
                            </a>
                            <a href="#" class="cma-user-dropdown-item" id="menuPassword" onclick="openPasswordModal(); return false;">
                                <span class="cma-dropdown-icon lnr lnr-lock"></span>Wachtwoord wijzigen
                            </a>
                            <?php if ($isImpersonating): ?>
                            <div class="cma-user-dropdown-divider"></div>
                            <a href="#" class="cma-user-dropdown-item cma-return-to-self" id="menuReturnToSelf" onclick="returnToOwnLogin(); return false;">
                                <span class="cma-dropdown-icon lnr lnr-undo"></span>Terug naar eigen login
                            </a>
                            <?php endif; ?>
                            <div class="cma-user-dropdown-divider"></div>
                            <a href="/cma/logout.php" class="cma-user-dropdown-item" id="menuLogout">
                                <span class="cma-dropdown-icon lnr lnr-exit"></span>Uitloggen
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="cma-content" id="contentArea">
                <div class="cma-content-loading">Laden...</div>
            </div>
        </main>
    </div>

    <script>
    // Wait for main.js to define loadInitialPage
    (function waitForLoadInitialPage() {
        if (typeof loadInitialPage === 'function') {
            loadInitialPage(<?= json_encode($contentPage) ?>);
        } else {
            setTimeout(waitForLoadInitialPage, 10);
        }
    })();
    </script>
    <script>
    // Add tooltips only to truncated elements (menu items, toolbar buttons)
    (function() {
        function checkMenuTruncation() {
            // Menu items - show tooltip if text is truncated
            document.querySelectorAll('.cma-menu-item[data-fulltext]').forEach(function(item) {
                const textSpan = item.querySelector('.cma-menu-item-text');
                if (textSpan && textSpan.scrollWidth > textSpan.clientWidth) {
                    item.setAttribute('data-tooltip', item.getAttribute('data-fulltext'));
                    item.setAttribute('data-tooltip-pos', 'right');
                } else {
                    item.removeAttribute('data-tooltip');
                }
            });
        }

        function checkToolbarTruncation() {
            // Toolbar buttons - show tooltip only if .btn-text is hidden
            document.querySelectorAll('.responsive-btn[data-tooltip], .tb-btn[data-tooltip]').forEach(function(btn) {
                const textSpan = btn.querySelector('.btn-text');
                // If button has no text span, always show tooltip (icon-only buttons)
                if (!textSpan) {
                    btn.classList.add('tooltip-enabled');
                    return;
                }
                // Check if text is hidden (display: none)
                const isHidden = window.getComputedStyle(textSpan).display === 'none';
                btn.classList.toggle('tooltip-enabled', isHidden);
            });
        }

        function checkAllTruncation() {
            checkMenuTruncation();
            checkToolbarTruncation();
        }

        // Check after DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                requestAnimationFrame(checkAllTruncation);
            });
        } else {
            requestAnimationFrame(checkAllTruncation);
        }
        // Re-check on sidebar toggle
        document.querySelector('.cma-sidebar')?.addEventListener('transitionend', function() {
            requestAnimationFrame(checkMenuTruncation);
        });
        // Re-check on window resize
        window.addEventListener('resize', function() {
            requestAnimationFrame(checkAllTruncation);
        });

        // Expose for re-checking after dynamic content loads
        window.CMA = window.CMA || {};
        window.CMA.checkTruncation = checkAllTruncation;
    })();
    </script>
</body>
</html>
