<?php
/**
 * CMA Dashboard
 * Shows menu items in a card-based grid layout
 */

use App\Library\Arr;
use App\Library\Cache;
use App\Library\Cookie;
use App\Library\Response;
use Cma\SecurityHelper;

require_once __DIR__ . '/bootstrap.inc';

// Prevent browser caching - dashboard shows real-time status
Response::noCache();
require_once __DIR__ . '/menurep.inc';

// Check if logged in
$userId = Cookie::get(SecurityHelper::COOKIE_USERID, '');
if (empty($userId)) {
    header('Location: login.php');
    exit;
}

// Get theme preference
$currentTheme = Cookie::get('cma_theme', 'light');
$themeClass = ($currentTheme === 'dark') ? 'dark-mode' : '';
$useSystemTheme = ($currentTheme === 'system');

// Get user access level for AI section
$userLevel = SecurityHelper::getUserLevel();
$userLevelName = SecurityHelper::getUserLevelName($userLevel);
$isAdmin = SecurityHelper::isAdmin();
$isDeveloper = SecurityHelper::isDeveloper();

// Load menu data
$arrMenu = loadMenuData();

// Group menu items
$menuGroups = [];
if (Arr::isArray($arrMenu) && isset($arrMenu[MENU_MENUNAME])) {
    for ($i = 0; $i < count($arrMenu[MENU_MENUNAME]); $i++) {
        $menuItemId = $arrMenu[MENU_MENUITEMID][$i] ?? 0;

        // Check access rights
        if (SecurityHelper::checkRights(SecurityHelper::TYPE_MENU, $menuItemId) <= SecurityHelper::ACCESS_NONE) {
            continue;
        }

        $menuName = $arrMenu[MENU_MENUNAME][$i] ?? '';
        $itemName = $arrMenu[MENU_MENUITEMNAME][$i] ?? '';
        $formName = $arrMenu[MENU_FORMNAME][$i] ?? '';

        if (empty($itemName) && !empty($formName)) {
            // Get title from JSON form definition
            $formDef = \Cma\JsonFormLoader::loadRaw($formName);
            $itemName = $formDef['title'] ?? ucfirst($formName);
        }

        $href = $arrMenu[MENU_MENUITEMHREF][$i] ?? '';

        if (!empty($formName)) {
            $href = 'form.php?form=' . urlencode($formName);
        } elseif (!empty($href)) {
            // Replace all .asp with .php for converted pages (including in query strings)
            $href = str_ireplace('.asp', '.php', $href);
        }

        if (empty($href)) continue;

        if (!isset($menuGroups[$menuName])) {
            $menuGroups[$menuName] = [];
        }
        $menuGroups[$menuName][] = [
            'name' => $itemName,
            'href' => $href
        ];
    }
}

// Add Systeem items for admins
if ($isAdmin) {
    if (!isset($menuGroups['Systeem'])) {
        $menuGroups['Systeem'] = [];
    }
    // Add at the beginning
    array_unshift($menuGroups['Systeem'],
        ['name' => 'Gebruikers', 'href' => 'form.php?form=users'],
        ['name' => 'Groepen', 'href' => 'form.php?form=groups'],
        ['name' => 'Tools', 'href' => 'tools.php'],
        ['name' => 'Cache leegmaken', 'href' => 'tools/clearcache']
    );
}
?>
<!DOCTYPE html>
<html lang="nl"<?= $themeClass ? ' class="' . $themeClass . '"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - CMA</title>
    <link rel="stylesheet" href="minify.php?f=../library/css/lib-variables.css,assets/css/colors.css,../library/library.css,assets/css/style.css">
    <?php cma_error_handler(); ?>
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
    <style>
        * { box-sizing: border-box; }
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-surface, #f5f5f5);
            color: var(--text-primary, #333);
            overflow-y: auto;
        }

        .dashboard-container {
            padding: 20px;
            min-height: 100%;
            overflow: auto;
        }

        /* Dashboard card - consistent with CMA styling */
        .dashboard-card {
            background: var(--bg-card, #fff);
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid var(--border-color, #e0e0e0);
            margin-bottom: 20px;
        }

        .dashboard-card-header {
            background: var(--color-primary, #204496);
            color: #fff;
            padding: 12px 16px;
            font-size: var(--font-size-md);
            font-weight: 100;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dashboard-card-header .lnr::before {
            color: #fff;
            font-size: var(--font-size-lg);
        }

        .dashboard-card-body {
            padding: 16px;
        }

        /* Warning card - for migration alerts (orange, not yellow) */
        .dashboard-card.warning .dashboard-card-header {
            background: #e4a400;
        }

        /* Grid layouts */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        @media (max-width: 1200px) {
            .menu-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 900px) {
            .menu-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 600px) {
            .menu-grid { grid-template-columns: 1fr; }
        }

        /* Menu card - matches dashboard-card base */
        .menu-card {
            background: var(--bg-card, #fff);
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid var(--border-color, #e0e0e0);
        }

        .menu-card-header {
            background: var(--color-primary, #204496);
            color: #fff;
            padding: 12px 16px;
            font-size: var(--font-size-md);
            font-weight: 600;
        }

        .menu-card-body {
            padding: 8px 0;
        }

        .menu-card-body a {
            display: block;
            padding: 10px 16px;
            color: var(--text-primary, #333);
            text-decoration: none;
            font-size: var(--font-size);
            transition: background 0.15s ease;
            border-left: 3px solid transparent;
        }

        .menu-card-body a:hover {
            background: var(--bg-hover);
            color: var(--color-primary);
            border-left-color: var(--color-accent);
        }

        /* Quick access grid */
        .quick-access-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 12px;
        }

        .quick-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 16px 12px;
            background: var(--bg-surface, #f5f5f5);
            border: 1px solid var(--border-color, #e0e0e0);
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-primary, #333);
            transition: all 0.2s ease;
        }

        .quick-card:hover {
            background: var(--bg-card, #fff);
            border-color: var(--color-info, #077ab2);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .quick-card .lnr {
            width: auto;
            height: auto;
            margin-bottom: 8px;
        }

        .quick-card .lnr::before {
            font-size: var(--font-size-3xl);
            color: var(--color-primary, #204496);
        }

        .quick-card:hover .lnr::before {
            color: var(--color-accent, #077ab2);
        }

        .quick-card span:last-child {
            font-size: var(--font-size-sm);
            font-weight: 500;
            text-align: center;
        }

        .quick-card.developer-only {
            border-left: 3px solid var(--color-accent, #077ab2);
        }

        /* Migration link */
        .migration-link {
            display: inline-block;
            margin-top: 10px;
            padding: 6px 12px;
            background: var(--color-primary, #204496);
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
            font-size: var(--font-size);
            font-weight: 500;
        }

        .migration-link:hover {
            color: #ffffff;
            background-color: #bb8805;
        }

        /* AI Prompt Box - minimal chat-like design */
        .ai-prompt-box {
            background: var(--bg-card, #fff);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid var(--border-color, #e0e0e0);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .ai-prompt-wrapper {
            position: relative;
            padding: 16px;
            padding-bottom: 60px;
        }

        .ai-prompt-input {
            width: 100%;
            min-height: 60px;
            padding: 12px;
            border: none;
            border-radius: 0;
            font-size: var(--font-size-md);
            font-family: inherit;
            resize: none;
            outline: none;
            background: transparent;
        }

        .ai-prompt-input:focus {
            border: none;
            box-shadow: none;
            background: transparent;
            outline: none !important;
        }

        .ai-prompt-input::placeholder {
            color: var(--text-muted, #999);
            font-style: italic;
        }

        .ai-submit-btn {
            position: absolute;
            right: 16px;
            bottom: 16px;
            padding: 8px 16px;
            background: var(--color-primary, #204496);
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: var(--font-size);
            font-weight: 500;
            font-family: inherit;
            transition: background 0.2s, transform 0.1s;
        }

        .ai-submit-btn:hover {
            background: var(--color-info, #077ab2);
            transform: translateY(-1px);
        }

        .ai-submit-btn:active {
            transform: translateY(0);
        }

        .ai-response {
            margin: 0 16px 16px 16px;
            padding: 12px;
            background: var(--bg-surface, #f5f5f5);
            border-radius: 8px;
            border-left: 3px solid var(--color-info, #077ab2);
            display: none;
        }

        .ai-response.visible {
            display: block;
        }

        .ai-response-message {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--color-info, #077ab2);
            font-size: var(--font-size);
        }

        .ai-response-message .lnr {
            width: auto;
            height: auto;
        }

        .ai-response-message .lnr::before {
            font-size: var(--font-size-lg);
            color: var(--color-info, #077ab2);
        }

        /* Stats grid for developer dashboard */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stats-card {
            background: var(--bg-card, #fff);
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            border: 1px solid var(--border-color, #e0e0e0);
        }

        .stats-card-header {
            background: var(--bg-surface, #f5f5f5);
            padding: 10px 16px;
            font-size: var(--font-size);
            font-weight: 100;
            border-bottom: 1px solid var(--border-color, #e0e0e0);
            display: flex;
            align-items: center;
            gap: 8px;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .stats-card-header .lnr::before {
            font-size: var(--font-size-md);
            color: var(--color-primary, #204496);
        }

        .stats-card-header .header-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0px;
            border-radius: 4px;
            border: 1px solid transparent;
            background: none;
            cursor: pointer;
            color: var(--text-primary, #333);
        }

        .stats-card-header .header-action:first-of-type {
            margin-left: auto;
        }

        .stats-card-header .header-action:hover {
            background: var(--bg-hover);
            border-color: var(--border-hover);
            color: var(--color-accent);
        }

        .stats-card-header .header-action .lnr {
            font-size: var(--font-size-lg);
            line-height: 1;
        }

        .stats-card-body {
            padding: 16px;
            /* Constrain width for text-overflow to work */
            overflow: hidden;
        }

        /* Horizontal bar items (used in performance stats) */
        .hbar-item {
            display: flex;
            align-items: center;
            min-width: 0; /* Required for text-overflow in flex children */
        }
        .hbar-label {
            flex: 1;
            min-width: 0; /* Required for text-overflow in flex children */
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .hbar-value {
            flex-shrink: 0;
        }

        /* Performance SQL links */
        .perf-sql-link {
            color: var(--text-primary, #333);
            cursor: pointer;
        }
        .perf-sql-link:hover {
            color: var(--color-primary, #3F096E);
            text-decoration: underline;
        }
        /* Performance API links */
        .perf-api-link {
            color: var(--text-primary, #333);
            cursor: pointer;
        }
        .perf-api-link:hover {
            color: var(--color-warning, #f59e0b);
            text-decoration: underline;
        }
        /* API Details dialog content - used inside lib-dialog */
        .api-detail-row {
            display: flex;
            margin-bottom: 10px;
            font-size: var(--font-size);
        }
        .api-detail-label {
            font-weight: 600;
            min-width: 100px;
            color: var(--text-muted, #666);
        }
        .api-detail-value {
            flex: 1;
            word-break: break-all;
        }
        .api-detail-value.duration {
            color: var(--color-warning, #f59e0b);
            font-weight: 600;
        }
        .api-detail-context {
            background: var(--bg-secondary, #f5f5f5);
            color: var(--text-api-detail-context, #ffffff);
            padding: 10px;
            border-radius: 4px;
            font-family: "Consolas", "Monaco", monospace;
            font-size: var(--font-size-xs);
            white-space: pre-wrap;
            overflow-x: auto;
        }
        /* API Re-test results - used inside lib-dialog */
        .api-retest-results {
            margin-top: 12px;
            background: var(--bg-secondary, #f5f5f5);
            border-radius: 4px;
            padding: 12px;
            max-height: 200px;
            overflow-y: auto;
        }
        .api-retest-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: var(--font-size-sm);
            border-bottom: 1px solid var(--border-color, #e0e0e0);
        }
        .api-retest-row:last-child {
            border-bottom: none;
        }
        .api-retest-row.success { color: var(--color-success, #28a745); }
        .api-retest-row.error { color: var(--color-error, #dc3545); }
        .api-retest-summary {
            margin-top: 8px;
            padding-top: 8px;
            font-weight: 600;
            font-size: var(--font-size);
        }

        /* Bar chart styling - stacked bars */
        .bar-chart {
            display: flex;
            align-items: flex-end;
            gap: 2px;
            height: 60px;
            padding-top: 8px;
        }

        .bar-chart .bar {
            flex: 1;
            min-width: 6px;
            display: flex;
            flex-direction: column-reverse;
            border-radius: 2px 2px 0 0;
            position: relative;
            overflow: visible;
        }

        .bar-chart .bar .bar-segment {
            width: 100%;
            transition: opacity 0.2s;
        }

        .bar-chart .bar .bar-segment.error { background: var(--color-error, #dc3545); }
        .bar-chart .bar .bar-segment.warning { background: var(--color-warning, #f59e0b); }
        .bar-chart .bar .bar-segment.notice { background: var(--color-info, #077ab2); }
        .bar-chart .bar .bar-segment.other { background: var(--text-muted, #999); }

        .bar-chart .bar:hover .bar-segment {
            opacity: 0.8;
        }

        .bar-chart .bar[data-count]:hover::after {
            content: attr(data-count);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: var(--font-size-2xs);
            white-space: nowrap;
            z-index: 10;
        }

        /* Stats row */
        .stats-row {
            display: flex;
            justify-content: space-between;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border-color, #e0e0e0);
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: var(--font-size-2xl);
            font-weight: 600;
            color: var(--color-primary, #204496);
        }

        .stat-value.error { color: var(--color-error, #dc3545); }
        .stat-value.warning { color: var(--color-warning, #f59e0b); }
        .stat-value.success { color: var(--color-success, #28a745); }

        .stat-label {
            font-size: var(--font-size-xs);
            color: var(--text-muted, #666);
            margin-top: 2px;
        }

        /* Donut chart for cache ratio */
        .donut-chart {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }

        .donut-chart::before {
            content: '';
            position: absolute;
            width: 50px;
            height: 50px;
            background: var(--bg-card, #fff);
            border-radius: 50%;
        }

        .donut-value {
            position: relative;
            z-index: 1;
            font-size: var(--font-size-md);
            font-weight: 600;
        }

        /* Cache misses list */
        .cache-misses-list {
            flex: 1;
            min-width: 0;
            font-size: var(--font-size-xs);
            border-left: 1px solid var(--border-color, #ddd);
            padding-left: 15px;
        }
        .cache-misses-title {
            font-weight: 600;
            color: var(--text-secondary, #666);
            margin-bottom: 6px;
        }
        .cache-miss-item {
            display: flex;
            gap: 8px;
            padding: 2px 0;
            color: var(--text-muted, #999);
        }
        .cache-miss-time {
            color: var(--text-muted, #999);
            white-space: nowrap;
        }
        .cache-miss-key {
            color: var(--color-error, #dc3545);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Log settings panel */
        .log-settings-panel {
            border-top: 1px solid var(--border-color, #ddd);
            padding: 12px 16px;
            background: var(--bg-secondary, #f8f9fa);
            font-size: var(--font-size-sm);
        }
        .log-settings-panel .log-setting-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 0;
        }
        .log-settings-panel .log-setting-icon {
            width: 16px;
            text-align: center;
        }
        .log-settings-panel .log-setting-icon.enabled { color: var(--color-success, #28a745); }
        .log-settings-panel .log-setting-icon.disabled { color: var(--text-muted, #999); }
        .log-settings-panel .log-setting-label {
            min-width: 140px;
            color: var(--text-primary, #333);
        }
        .log-settings-panel .log-setting-status {
            color: var(--text-muted, #666);
            font-size: var(--font-size-xs);
        }
        .log-settings-panel .log-setting-status.file-exists { color: var(--color-success, #28a745); }
        .log-settings-panel .log-setting-status.file-missing { color: var(--color-warning, #f59e0b); }
        .log-settings-panel .log-settings-footer {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid var(--border-color, #ddd);
            text-align: right;
        }
        .log-settings-panel .log-settings-footer a {
            color: var(--color-primary, #204496);
            text-decoration: none;
            font-size: var(--font-size-xs);
        }
        .log-settings-panel .log-settings-footer a:hover {
            text-decoration: underline;
        }

        .stats-loading {
            text-align: center;
            padding: 20px;
            color: var(--text-muted, #666);
            font-size: var(--font-size);
        }

        .stats-error {
            color: var(--color-error, #dc3545);
            font-size: var(--font-size-sm);
            padding: 8px;
            background: rgba(220,53,69,0.1);
            border-radius: 4px;
        }

        /* Horizontal bar chart for forms stats */
        .hbar-chart {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .hbar-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .hbar-label {
            flex: 0 0 auto;
            min-width: 180px;
            max-width: 300px;
            font-size: var(--font-size-xs);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-primary, #333);
            text-decoration: none;
        }
        .hbar-track {
            flex: 1;
        }

        a.hbar-label:hover {
            color: var(--color-primary, #204496);
            text-decoration: underline;
        }

        .hbar-track {
            flex: 6;
            height: 16px;
            background: var(--bg-surface, #f5f5f5);
            border-radius: 3px;
            overflow: hidden;
        }

        .hbar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--color-info, #077ab2), var(--color-primary, #204496));
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .hbar-value {
            width: 40px;
            font-size: var(--font-size-xs);
            font-weight: 600;
            text-align: right;
            color: var(--text-muted, #666);
        }

        .activity-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: var(--font-size-2xs);
            font-weight: 500;
        }

        .activity-badge.view { background: #e3f2fd; color: #1565c0; }
        .activity-badge.edit { background: #fff3e0; color: #ef6c00; }
        .activity-badge.add { background: #e8f5e9; color: #2e7d32; }
        .activity-badge.delete { background: #ffebee; color: #c62828; }
        .activity-badge.login { background: #e0f2f1; color: #00695c; }

        /* Security stats */
        .security-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .security-item {
            background: var(--bg-surface, #f5f5f5);
            border-radius: 6px;
            padding: 12px;
            text-align: center;
        }

        .security-item.alert {
            background: rgba(220,53,69,0.1);
            border: 1px solid rgba(220,53,69,0.3);
        }

        .security-value {
            font-size: var(--font-size-3xl);
            font-weight: 700;
            color: var(--color-primary, #204496);
        }

        .security-item.alert .security-value {
            color: var(--color-error, #dc3545);
        }

        .security-label {
            font-size: var(--font-size-xs);
            color: var(--text-muted, #666);
            margin-top: 4px;
        }

        /* Template cache grid */
        .cache-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            text-align: center;
        }

        .cache-item {
            padding: 8px;
        }

        .cache-value {
            font-size: var(--font-size-2xl);
            font-weight: 600;
            color: var(--color-primary, #204496);
        }

        .cache-value.success { color: var(--color-success, #28a745); }

        .cache-label {
            font-size: var(--font-size-2xs);
            color: var(--text-muted, #666);
            margin-top: 2px;
        }

        /* Frequent forms grid */
        .frequent-forms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
        }

        .frequent-form-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 4px;
            background: var(--bg-surface, #f5f5f5);
            border: 1px solid var(--border-color, #e0e0e0);
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-primary, #333);
            font-size: var(--font-size-sm);
            transition: all 0.2s ease;
        }

        .frequent-form-link:hover {
            background: var(--bg-card, #fff);
            border-color: var(--color-info, #077ab2);
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }

        .frequent-form-link .lnr {
            width: auto;
            height: auto;
        }

        .frequent-form-link .lnr::before {
            font-size: var(--font-size-lg);
            color: var(--color-primary, #204496);
        }

        .frequent-form-link:hover .lnr::before {
            color: var(--color-accent, #077ab2);
        }

        .frequent-form-link span:last-child {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .frequent-form-count {
            font-size: var(--font-size-2xs);
            color: var(--text-muted, #666);
            background: var(--bg-card, #fff);
            padding: 2px 6px;
            border-radius: 10px;
        }

        .frequent-forms-empty {
            text-align: center;
            padding: 20px;
            color: var(--text-muted, #666);
            font-size: var(--font-size);
        }
    </style>
    <?php cma_script('../library/webcomponents/lib-dialog.js'); ?>
    <?php cma_script('../library/webcomponents/lib-histogram.js'); ?>
    <?php cma_script('../library/webcomponents/lib-tip.js'); ?>
    <?php cma_script('assets/js/cma-tours.js'); ?>
</head>
<body data-user-level="<?php echo $isDeveloper ? 'D' : ($isAdmin ? 'A' : ''); ?>">
<div class="dashboard-container">
    <?php
    // Show migration notification for admin/developer users
    $showMigrationNotice = ($isAdmin || $isDeveloper)
        && !empty($GLOBALS['_pending_migrations']);
    if ($showMigrationNotice):
        $pendingCount = count($GLOBALS['_pending_migrations']);
    ?>
    <div class="dashboard-card warning">
        <div class="dashboard-card-header">
            <span class="lnr lnr-warning"></span>
            Database update vereist
        </div>
        <div class="dashboard-card-body">
            Er <?= $pendingCount === 1 ? 'is' : 'zijn' ?> <?= $pendingCount ?> database <?= $pendingCount === 1 ? 'migratie' : 'migraties' ?> beschikbaar.
            <br>
            <a href="tools/tools_migrations.php" class="migration-link">Bekijk migraties</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Debug logging notification - shown via JS when verbose logging is enabled -->
    <div class="dashboard-card warning" id="loggingNotice" style="display: none;">
        <div class="dashboard-card-header">
            <span class="lnr lnr-cog"></span>
            Uitgebreide logging actief
        </div>
        <div class="dashboard-card-body">
            <span id="loggingNoticeText">Er is uitgebreide logging ingeschakeld.</span>
            Dit kan de systeemprestaties negatief beïnvloeden.
            <br>
            <a href="preferences.php" class="migration-link" onclick="return navigateToPreferences();">Instellingen wijzigen</a>
        </div>
    </div>

    <?php
    // PHP performance warnings for admin/developer users
    if ($isAdmin || $isDeveloper):
        $phpWarnings = [];
        // Check OPcache
        if (!function_exists('opcache_get_status') || opcache_get_status(false) === false) {
            $phpWarnings[] = '<b>OPcache</b> is niet actief. Dit vertraagt elke pagina-aanvraag aanzienlijk doordat PHP-bestanden steeds opnieuw gecompileerd worden.';
        }
        // Check APCu (used by Cache class for in-memory caching)
        if (!function_exists('apcu_fetch')) {
            $phpWarnings[] = '<b>APCu</b> is niet geïnstalleerd. Zonder APCu valt de cache terug op bestandssysteem-I/O, wat formulierlijsten en templates aanzienlijk vertraagt.';
        }
        // Check realpath cache size (low values cause excessive disk I/O)
        $realpathCacheSize = ini_get('realpath_cache_size');
        if ($realpathCacheSize && intval($realpathCacheSize) < 4096) {
            $phpWarnings[] = '<b>realpath_cache_size</b> is laag (' . $realpathCacheSize . '). Verhoog naar minimaal 4M voor betere prestaties.';
        }
    endif;
    if (!empty($phpWarnings)):
    ?>
    <div class="dashboard-card warning">
        <div class="dashboard-card-header">
            <span class="lnr lnr-warning"></span>
            PHP prestatie-instellingen
        </div>
        <div class="dashboard-card-body">
            <?php foreach ($phpWarnings as $w): ?>
                <div style="margin-bottom: 6px;"><?= $w ?></div>
            <?php endforeach; ?>
            <br>
            <a href="tools/tools_serverinfo.php" class="migration-link">Server informatie</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- AI Question Section - disabled for now
    <div class="ai-prompt-box" id="aiSection" data-user-level="<?= $userLevel ?>" data-user-level-name="<?= htmlspecialchars($userLevelName) ?>">
        <div class="ai-prompt-wrapper">
            <textarea class="ai-prompt-input" id="aiQuestion" placeholder="Stel je vraag aan AI en laat je verrassen..." rows="3"></textarea>
            <button type="button" class="ai-submit-btn" id="aiSubmit">Verras me!</button>
        </div>
        <div class="ai-response" id="aiResponse">
            <div class="ai-response-message">
                <span class="lnr lnr-hourglass"></span>
                <span id="aiResponseText">Nog even geduld, we zijn de AI nog aan het trainen</span>
            </div>
        </div>
    </div>
    -->

    <!-- User widgets row - for all users -->
    <div class="stats-grid">
        <!-- Vaak gebruikt -->
        <div class="dashboard-card" id="frequentFormsCard" style="margin-bottom: 0;">
            <div class="dashboard-card-header">
                <span class="lnr lnr-star"></span>
                Vaak gebruikt
            </div>
            <div class="dashboard-card-body" id="frequentForms">
                <div class="stats-loading">Laden...</div>
            </div>
        </div>

        <!-- Recente activiteit -->
        <div class="dashboard-card" id="recentActivityCard" style="margin-bottom: 0;">
            <div class="dashboard-card-header">
                <span class="lnr lnr-history"></span>
                Mijn recente activiteit
                <a href="#" class="header-action" id="exportActivityBtn" onclick="exportRecentActivity(); return false;" data-tooltip="Exporteer naar CSV" style="display:none;">
                    <span class="lnr lnr-download"></span>
                </a>
                <a href="form.php?form=cmamonitoring" class="header-action" data-tooltip="Alle activiteit bekijken">
                    <span class="lnr lnr-list"></span>
                </a>
            </div>
            <div class="dashboard-card-body" id="recentActivity" style="padding: 0;">
                <div class="stats-loading" style="padding: 16px;">Laden...</div>
            </div>
        </div>
    </div>

    <?php if ($isAdmin || $isDeveloper): ?>
    <!-- Health & Cache Stats for admins/developers - Row 1 -->
    <div class="stats-grid" id="statsGrid">
        <div class="stats-card">
            <div class="stats-card-header">
                <span class="lnr lnr-heart-pulse"></span>
                Systeemgezondheid (afgelopen week)
                <a href="#" class="header-action" id="showLogSettingsBtn" onclick="toggleLogSettings(); return false;" data-tooltip="Log instellingen bekijken">
                    <span class="lnr lnr-cog"></span>
                </a>
                <a href="#" class="header-action" id="showErrorsBtn" onclick="showErrorPopup(); return false;" data-tooltip="Laatste errors bekijken" style="display:none;">
                    <span class="lnr lnr-list"></span>
                </a>
            </div>
            <div class="stats-card-body" id="healthStats">
                <div class="stats-loading">Laden...</div>
            </div>
            <div class="log-settings-panel" id="logSettingsPanel" style="display:none;">
                <div class="log-settings-content">
                    <div class="log-settings-loading">Laden...</div>
                </div>
            </div>
        </div>
        <div class="stats-card">
            <div class="stats-card-header">
                <span class="lnr lnr-database"></span>
                Cache prestaties
                <a href="#" class="header-action" onclick="openCacheTool(); return false;" data-tooltip="Cache legen">
                    <span class="lnr lnr-trash"></span>
                </a>
            </div>
            <div class="stats-card-body" id="cacheStats">
                <div class="stats-loading">Laden...</div>
            </div>
        </div>
    </div>

    <!-- Activity & Forms Stats - Row 2 -->
    <div class="stats-grid">
        <div class="stats-card">
            <div class="stats-card-header">
                <span class="lnr lnr-chart-bars"></span>
                Gebruikersactiviteit (7 dagen)
                <a href="#" class="header-action" onclick="openCmaMonitoring(); return false;" data-tooltip="CMA Monitoring openen">
                    <span class="lnr lnr-list"></span>
                </a>
            </div>
            <div class="stats-card-body" id="activityStats">
                <div class="stats-loading">Laden...</div>
            </div>
        </div>
        <div class="stats-card">
            <div class="stats-card-header">
                <span class="lnr lnr-layers"></span>
                Meest gebruikte formulieren
            </div>
            <div class="stats-card-body" id="formsStats">
                <div class="stats-loading">Laden...</div>
            </div>
        </div>
    </div>

    <!-- Security Overview & Performance - Row 3 -->
    <div class="stats-grid" id="securityPerfRow">
        <div class="stats-card" id="securityCard">
            <div class="stats-card-header">
                <span class="lnr lnr-warning"></span>
                Beveiligingsoverzicht
            </div>
            <div class="stats-card-body" id="securityStats">
                <div class="stats-loading">Laden...</div>
            </div>
        </div>
        <?php if ($isDeveloper): ?>
        <div class="stats-card" id="performanceCard">
            <div class="stats-card-header">
                <span class="lnr lnr-hourglass"></span>
                Prestaties
            </div>
            <div class="stats-card-body" id="performanceStats">
                <div class="stats-loading">Laden...</div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($isDeveloper): ?>
    <!-- JavaScript Logs - Row 4 (Developer only) -->
    <div class="stats-grid">
        <div class="stats-card">
            <div class="stats-card-header">
                <span class="lnr lnr-code"></span>
                JavaScript errors (afgelopen week)
                <a href="tools.php?tool=logs&log=jserrors" class="header-action" id="showJSLogsBtn" data-tooltip="JavaScript logs bekijken in logreader" style="display:none;">
                    <span class="lnr lnr-list"></span>
                </a>
            </div>
            <div class="stats-card-body" id="jslogStats">
                <div class="stats-loading">Laden...</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <!-- Quick access for admins/developers -->
    <div class="dashboard-card">
        <div class="dashboard-card-header">
            <span class="lnr lnr-rocket"></span>
            Snelle toegang
        </div>
        <div class="dashboard-card-body">
            <div class="quick-access-grid">
                <a href="form.php?form=users" class="quick-card">
                    <span class="lnr lnr-user"></span>
                    <span>Gebruikers</span>
                </a>
                <a href="form.php?form=groups" class="quick-card">
                    <span class="lnr lnr-users"></span>
                    <span>Groepen</span>
                </a>
                <a href="tools.php" class="quick-card">
                    <span class="lnr lnr-cog"></span>
                    <span>Tools</span>
                </a>
                <a href="tools.php?tool=clearcache" class="quick-card">
                    <span class="lnr lnr-trash"></span>
                    <span>Cache leegmaken</span>
                </a>
                <a href="tools.php?tool=backup" class="quick-card">
                    <span class="lnr lnr-download"></span>
                    <span>Backup</span>
                </a>
                <?php if ($isDeveloper): ?>
                <a href="tools.php?tool=query" class="quick-card developer-only">
                    <span class="lnr lnr-code"></span>
                    <span>SQL Query</span>
                </a>
                <a href="tools.php?tool=logs" class="quick-card developer-only">
                    <span class="lnr lnr-list"></span>
                    <span>Logbestanden</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php /* Menu grid — same group structure as the sidebar menu, rendered
       at the bottom of every dashboard regardless of role. Earlier
       gated to normal-users-only, but admins/developers also want it
       back as the quick-jump panel under the stats widgets. */ ?>
    <div class="menu-grid">
        <?php foreach ($menuGroups as $groupName => $items): ?>
        <div class="menu-card">
            <div class="menu-card-header"><?= htmlspecialchars($groupName) ?></div>
            <div class="menu-card-body">
                <?php foreach ($items as $item): ?>
                <a href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['name']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
    (function() {
        'use strict';

        // AI Question handler - disabled for now
        /*
        var aiSection = document.getElementById('aiSection');
        var aiInput = document.getElementById('aiQuestion');
        var aiSubmit = document.getElementById('aiSubmit');
        var aiResponse = document.getElementById('aiResponse');
        var aiResponseText = document.getElementById('aiResponseText');

        // Get user level from data attribute
        var userLevel = aiSection ? aiSection.dataset.userLevel : '';
        var userLevelName = aiSection ? aiSection.dataset.userLevelName : '';

        function submitAiQuestion() {
            var question = aiInput.value.trim();
            if (!question) {
                aiInput.focus();
                return;
            }

            // Show response area with training message
            aiResponse.classList.add('visible');
            aiResponseText.textContent = 'Nog even geduld, we zijn de AI nog aan het trainen';

            // Log the question with user level for future implementation
            if (typeof cmaLog !== 'undefined') {
                cmaLog.log('AI Question submitted:', {
                    question: question,
                    userLevel: userLevel,
                    userLevelName: userLevelName
                });
            }

            // Future: Send to API endpoint
            // fetch('api/ai_question.php', {
            //     method: 'POST',
            //     headers: { 'Content-Type': 'application/json' },
            //     body: JSON.stringify({
            //         question: question,
            //         userLevel: userLevel
            //     })
            // }).then(response => response.json())
            //   .then(data => { aiResponseText.textContent = data.answer; });
        }

        if (aiSubmit) {
            aiSubmit.addEventListener('click', submitAiQuestion);
        }

        if (aiInput) {
            // Submit on Ctrl+Enter (since Enter creates new lines in textarea)
            aiInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault();
                    submitAiQuestion();
                }
            });
        }
        */

        // Handle menu card clicks when loaded in sidebar
        document.querySelectorAll('.menu-card-body a, .quick-card').forEach(function(link) {
            link.addEventListener('click', function(e) {
                if (window.parent && window.parent !== window && typeof window.parent.loadPage === 'function') {
                    e.preventDefault();
                    window.parent.loadPage(this.getAttribute('href'));
                }
            });
        });

        // Load frequent forms for all users
        var frequentForms = document.getElementById('frequentForms');
        var frequentFormsCard = document.getElementById('frequentFormsCard');

        if (frequentForms) {
            loadFrequentForms();
        }

        // Load recent activity for all users
        var recentActivity = document.getElementById('recentActivity');
        var recentActivityCard = document.getElementById('recentActivityCard');

        if (recentActivity) {
            loadRecentActivity();
        }

        function loadFrequentForms() {
            fetch('api/user_forms.php')
                .then(function(response) {
                    if (!response.ok) throw new Error('API fout');
                    return response.json();
                })
                .then(function(forms) {
                    renderFrequentForms(forms);
                })
                .catch(function(error) {
                    frequentForms.innerHTML = '<div class="stats-error">Kan formulieren niet laden</div>';
                });
        }

        function renderFrequentForms(forms) {
            if (!forms || forms.length === 0) {
                // Hide card if no frequent forms
                if (frequentFormsCard) {
                    frequentFormsCard.style.display = 'none';
                }
                return;
            }

            var linksHtml = forms.slice(0, 10).map(function(f) {
                var legacyUrl = 'form.php?form=' + encodeURIComponent(f.name);
                var icon = f.icon || 'lnr-file-empty';
                var displayName = f.title || ucfirst(f.name);
                return '<a href="' + legacyUrl + '" class="frequent-form-link">' +
                    '<span class="lnr ' + icon + '"></span>' +
                    '<span title="' + displayName + '">' + displayName + '</span>' +
                '</a>';
            }).join('');

            frequentForms.innerHTML = '<div class="frequent-forms-grid">' + linksHtml + '</div>';
        }

        function loadRecentActivity() {
            fetch('api/user_activity.php')
                .then(function(response) {
                    if (!response.ok) throw new Error('API fout');
                    return response.json();
                })
                .then(function(data) {
                    renderRecentActivity(data);
                })
                .catch(function(error) {
                    recentActivity.innerHTML = '<div class="stats-error">Kan activiteit niet laden</div>';
                });
        }

        // Store activity data for export
        var recentActivityData = [];

        function renderRecentActivity(data) {
            if (!data.success || !data.entries || data.entries.length === 0) {
                // Hide card if no recent activity
                if (recentActivityCard) {
                    recentActivityCard.style.display = 'none';
                }
                return;
            }

            // Store for export
            recentActivityData = data.entries;

            // Show export button
            var exportBtn = document.getElementById('exportActivityBtn');
            if (exportBtn) {
                exportBtn.style.display = 'flex';
            }

            // Build standard cma-table
            var html = '<div style="max-height: 200px; overflow-y: auto;">' +
                '<table class="libTable" style="width: 100%; font-size: var(--font-size-sm);">' +
                '<thead><tr>' +
                '<th class="libTableTH" style="width: 80px; padding: 6px 8px;">Actie</th>' +
                '<th class="libTableTH" style="padding: 6px 8px;">Formulier</th>' +
                '<th class="libTableTH" style="width: 60px; padding: 6px 8px; text-align: right;">Tijd</th>' +
                '</tr></thead><tbody>';

            data.entries.forEach(function(entry, idx) {
                var actionClass = 'view';
                var actionLabel = entry.action;
                if (entry.action === 'edit' || entry.action === 'wijzig') {
                    actionClass = 'edit';
                    actionLabel = 'Bewerkt';
                } else if (entry.action === 'add') {
                    actionClass = 'add';
                    actionLabel = 'Nieuw';
                } else if (entry.action === 'delete') {
                    actionClass = 'delete';
                    actionLabel = 'Verwijderd';
                } else if (entry.action === 'view') {
                    actionLabel = 'Bekeken';
                }

                var href = 'form.php?form=' + encodeURIComponent(entry.form);
                if (entry.record) {
                    href += '&id=' + entry.record;
                }

                var rowClass = (idx % 2 === 0) ? 'libTableTD1' : 'libTableTD2';
                html += '<tr class="libTableTR">' +
                    '<td class="' + rowClass + '" style="padding: 6px 8px;"><span class="activity-badge ' + actionClass + '">' + actionLabel + '</span></td>' +
                    '<td class="' + rowClass + '" style="padding: 6px 8px;"><a href="' + href + '" style="text-decoration: none; color: var(--text-primary);">' + escapeHtml(entry.formTitle) + '</a></td>' +
                    '<td class="' + rowClass + '" style="padding: 6px 8px; text-align: right; color: var(--text-muted); font-size: var(--font-size-2xs);">' + entry.time + '</td>' +
                '</tr>';
            });

            html += '</tbody></table></div>';
            recentActivity.innerHTML = html;
        }

        function exportRecentActivity() {
            if (!recentActivityData || recentActivityData.length === 0) return;

            var csv = 'Actie;Formulier;Tijd\n';
            recentActivityData.forEach(function(entry) {
                var actionLabel = entry.action;
                if (entry.action === 'edit' || entry.action === 'wijzig') actionLabel = 'Bewerkt';
                else if (entry.action === 'add') actionLabel = 'Nieuw';
                else if (entry.action === 'delete') actionLabel = 'Verwijderd';
                else if (entry.action === 'view') actionLabel = 'Bekeken';

                csv += '"' + actionLabel + '";"' + (entry.formTitle || '').replace(/"/g, '""') + '";"' + entry.time + '"\n';
            });

            var blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'recente_activiteit.csv';
            link.click();
            URL.revokeObjectURL(link.href);
        }
        window.exportRecentActivity = exportRecentActivity;

        // Load dashboard stats for admins/developers
        var healthStats = document.getElementById('healthStats');
        var cacheStats = document.getElementById('cacheStats');
        var activityStats = document.getElementById('activityStats');
        var formsStats = document.getElementById('formsStats');
        var securityStats = document.getElementById('securityStats');
        var performanceStats = document.getElementById('performanceStats');
        var jslogStats = document.getElementById('jslogStats');

        if (healthStats || cacheStats || jslogStats) {
            loadDashboardStats();
        }

        function loadDashboardStats() {
            fetch('api/dashboard_stats.php?action=all')
                .then(function(response) {
                    if (!response.ok) throw new Error('API fout');
                    return response.json();
                })
                .then(function(data) {
                    if (healthStats && data.errors) {
                        renderHealthStats(data.errors);
                    }
                    if (cacheStats && data.cache) {
                        renderCacheStats(data.cache);
                    }
                    if (activityStats && data.activity) {
                        renderActivityStats(data.activity);
                    }
                    if (formsStats && data.forms) {
                        renderFormsStats(data.forms);
                    }
                    if (securityStats && data.logins) {
                        renderSecurityStats(data.logins);
                    }
                    if (performanceStats && data.performance) {
                        renderPerformanceStats(data.performance);
                    }
                    if (jslogStats && data.jslog) {
                        renderJSLogStats(data.jslog);
                    }
                    // Store log settings for the toggle panel
                    if (data.log_settings) {
                        logSettingsData = data.log_settings;
                        // Check if verbose logging is enabled and show notification
                        checkVerboseLogging(data.log_settings);
                    }
                })
                .catch(function(error) {
                    var errorHtml = '<div class="stats-error">Kan gegevens niet laden</div>';
                    if (healthStats) healthStats.innerHTML = errorHtml;
                    if (cacheStats) cacheStats.innerHTML = errorHtml;
                    if (activityStats) activityStats.innerHTML = errorHtml;
                    if (formsStats) formsStats.innerHTML = errorHtml;
                    if (securityStats) securityStats.innerHTML = errorHtml;
                    if (performanceStats) performanceStats.innerHTML = errorHtml;
                    if (jslogStats) jslogStats.innerHTML = errorHtml;
                });
        }

        // Store last errors for popup
        var lastErrors = [];

        function renderHealthStats(errors) {
            if (!errors.exists) {
                healthStats.innerHTML = '<div class="stats-error">Errorlog niet gevonden</div>';
                return;
            }

            // Store last errors for popup
            lastErrors = errors.last_errors || [];

            // Show/hide the errors button based on whether there are errors
            var showErrorsBtn = document.getElementById('showErrorsBtn');
            if (showErrorsBtn) {
                showErrorsBtn.style.display = lastErrors.length > 0 ? 'flex' : 'none';
            }

            // Create stacked bar chart for daily errors by type (last 7 days)
            var daily = errors.daily || [];
            var maxCount = Math.max.apply(null, daily.map(function(d) { return d.count; })) || 1;
            var barsHtml = daily.map(function(d) {
                var totalHeight = Math.max(2, (d.count / maxCount) * 100);
                var errorPct = d.count > 0 ? (d.error / d.count * 100) : 0;
                var warningPct = d.count > 0 ? (d.warning / d.count * 100) : 0;
                var noticePct = d.count > 0 ? (d.notice / d.count * 100) : 0;
                var otherPct = d.count > 0 ? (d.other / d.count * 100) : 0;
                var tooltip = d.day + ': ' + d.count + ' (E:' + d.error + ' W:' + d.warning + ' N:' + d.notice + ')';
                return '<div class="bar" style="height:' + totalHeight + '%;" data-count="' + tooltip + '">' +
                    (d.error > 0 ? '<div class="bar-segment error" style="height:' + errorPct + '%;"></div>' : '') +
                    (d.warning > 0 ? '<div class="bar-segment warning" style="height:' + warningPct + '%;"></div>' : '') +
                    (d.notice > 0 ? '<div class="bar-segment notice" style="height:' + noticePct + '%;"></div>' : '') +
                    (d.other > 0 ? '<div class="bar-segment other" style="height:' + otherPct + '%;"></div>' : '') +
                '</div>';
            }).join('');

            var weekTypes = errors.by_type_week || {};
            // Calculate total including 'other' for accurate display
            var totalWeek = (weekTypes.error || 0) + (weekTypes.warning || 0) + (weekTypes.notice || 0) + (weekTypes.other || 0);

            healthStats.innerHTML =
                '<div class="bar-chart">' + barsHtml + '</div>' +
                '<div class="stats-row">' +
                    '<div class="stat-item">' +
                        '<div class="stat-value error">' + (weekTypes.error || 0) + '</div>' +
                        '<div class="stat-label">Fouten</div>' +
                    '</div>' +
                    '<div class="stat-item">' +
                        '<div class="stat-value warning">' + (weekTypes.warning || 0) + '</div>' +
                        '<div class="stat-label">Waarschuwingen</div>' +
                    '</div>' +
                    '<div class="stat-item">' +
                        '<div class="stat-value" style="color:var(--color-info, #077ab2);">' + (weekTypes.notice || 0) + '</div>' +
                        '<div class="stat-label">Meldingen</div>' +
                    '</div>' +
                    '<div class="stat-item">' +
                        '<div class="stat-value" style="color:var(--text-muted, #999);">' + (weekTypes.other || 0) + '</div>' +
                        '<div class="stat-label">Overig</div>' +
                    '</div>' +
                '</div>';
        }

        function showErrorPopup() {
            if (lastErrors.length === 0) {
                return;
            }

            var errorsHtml = lastErrors.map(function(e) {
                return '<div style="padding: 8px 0; border-bottom: 1px solid var(--border-color, #e0e0e0);">' +
                    '<div style="font-weight: 600; color: var(--text-muted, #666); font-size: var(--font-size-xs);">' + e.time + '</div>' +
                    '<div style="font-family: monospace; font-size: var(--font-size-xs); word-break: break-word;">' + e.message + '</div>' +
                '</div>';
            }).join('');

            if (errorsHtml === '') {
                errorsHtml = '<div style="padding: 8px 0;">Geen recente errors</div>';
            }

            // Use lib-dialog component
            var dialog = document.createElement('lib-dialog');
            dialog.id = 'errorDialog';
            dialog.setAttribute('title', 'Laatste errors (vandaag)');
            dialog.setAttribute('type', 'danger');
            dialog.setAttribute('size', 'large');
            dialog.innerHTML =
                '<div style="max-height: 500px; overflow-y: auto;">' + errorsHtml + '</div>' +
                '<div slot="footer" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">' +
                    '<a href="tools/logs" onclick="return navigateToLog()" style="color: var(--color-primary, #204496); text-decoration: none; font-size: var(--font-size-sm);">Volledige errorlog bekijken</a>' +
                    '<button class="btn btn-primary" onclick="document.getElementById(\'errorDialog\').close()">Sluiten</button>' +
                '</div>';

            document.body.appendChild(dialog);
            dialog.open();

            // Clean up dialog when closed
            dialog.addEventListener('dialog-close', function() {
                dialog.remove();
            });
        }

        // Log settings panel toggle and render
        var logSettingsLoaded = false;
        var logSettingsData = null;

        // Make globally accessible for onclick handler
        window.toggleLogSettings = function() {
            var panel = document.getElementById('logSettingsPanel');
            if (!panel) return;

            if (panel.style.display === 'none') {
                panel.style.display = 'block';
                if (!logSettingsLoaded) {
                    renderLogSettings(logSettingsData);
                }
            } else {
                panel.style.display = 'none';
            }
        };

        function renderLogSettings(settings) {
            var panel = document.getElementById('logSettingsPanel');
            if (!panel) return;

            var content = panel.querySelector('.log-settings-content');
            if (!content) return;

            if (!settings) {
                content.innerHTML = '<div style="color: var(--text-muted, #666);">Geen gegevens beschikbaar</div>';
                return;
            }

            var html = '';
            var logs = ['perf_log', 'cache_log', 'debug_log', 'php_error_log'];

            logs.forEach(function(key) {
                var log = settings[key];
                if (!log) return;

                var iconClass = log.enabled ? 'enabled' : 'disabled';
                var icon = log.enabled ? '✓' : '○';
                var statusClass = '';
                var statusText = '';

                if (log.enabled) {
                    if (log.exists) {
                        statusClass = 'file-exists';
                        statusText = 'Bestand aanwezig';
                    } else if (log.path) {
                        statusClass = 'file-missing';
                        statusText = 'Bestand niet gevonden';
                    }
                } else {
                    statusText = 'Uitgeschakeld';
                }

                html += '<div class="log-setting-row">' +
                    '<span class="log-setting-icon ' + iconClass + '">' + icon + '</span>' +
                    '<span class="log-setting-label">' + log.label + '</span>' +
                    '<span class="log-setting-status ' + statusClass + '">' + statusText + '</span>' +
                '</div>';
            });

            html += '<div class="log-settings-footer">' +
                '<a href="preferences.php" onclick="return navigateToPreferences()">Instellingen wijzigen</a>' +
            '</div>';

            content.innerHTML = html;
            logSettingsLoaded = true;
        }

        // Check if verbose logging is enabled and show notification
        function checkVerboseLogging(settings) {
            if (!settings) return;

            var enabledLogs = [];
            var logLabels = {
                'perf_log': 'Performance logging',
                'cache_log': 'Cache logging',
                'debug_log': 'Debug logging'
            };

            // Check which logs are enabled (exclude php_error_log as that's always needed)
            ['perf_log', 'cache_log', 'debug_log'].forEach(function(key) {
                if (settings[key] && settings[key].enabled) {
                    enabledLogs.push(logLabels[key]);
                }
            });

            var notice = document.getElementById('loggingNotice');
            var noticeText = document.getElementById('loggingNoticeText');

            if (enabledLogs.length > 0 && notice && noticeText) {
                // Build readable text
                var text;
                if (enabledLogs.length === 1) {
                    text = enabledLogs[0] + ' is ingeschakeld.';
                } else if (enabledLogs.length === 2) {
                    text = enabledLogs.join(' en ') + ' zijn ingeschakeld.';
                } else {
                    text = enabledLogs.slice(0, -1).join(', ') + ' en ' + enabledLogs[enabledLogs.length - 1] + ' zijn ingeschakeld.';
                }
                noticeText.textContent = text;
                notice.style.display = 'block';
            }
        }

        function navigateToPreferences() {
            if (typeof window.loadPage === 'function') {
                window.loadPage('preferences.php');
                return false;
            }
            return true;
        }

        function navigateToLog() {
            var dialog = document.getElementById('errorDialog');
            if (dialog) dialog.close();
            if (typeof window.loadPage === 'function') {
                window.loadPage('tools.php?tool=logs');
                return false;
            }
            return true;
        }

        // Expose functions to global scope for onclick handlers
        window.showErrorPopup = showErrorPopup;
        window.navigateToLog = navigateToLog;
        window.navigateToPreferences = navigateToPreferences;

        function openCacheTool() {
            if (typeof window.loadPage === 'function') {
                window.loadPage('tools.php?tool=clearcache');
                return false;
            }
            window.location.href = 'tools.php?tool=clearcache';
            return false;
        }
        window.openCacheTool = openCacheTool;

        function openCmaMonitoring() {
            if (typeof window.loadPage === 'function') {
                window.loadPage('form.php?form=cmamonitoring');
                return false;
            }
            window.location.href = 'form.php?form=cmamonitoring';
            return false;
        }
        window.openCmaMonitoring = openCmaMonitoring;

        function renderCacheStats(cache) {
            var ratio = Math.round(cache.hit_ratio || 0);
            var ratioClass = ratio >= 80 ? 'success' : (ratio >= 50 ? 'warning' : 'error');

            // Create conic gradient for donut chart
            var gradient = 'conic-gradient(var(--color-success, #28a745) 0% ' + ratio + '%, var(--color-error, #dc3545) ' + ratio + '% 100%)';

            // Build misses list
            var missesHtml = '';
            if (cache.recent_misses && cache.recent_misses.length > 0) {
                missesHtml = '<div class="cache-misses-list">' +
                    '<div class="cache-misses-title">Recente misses:</div>' +
                    cache.recent_misses.slice(0, 5).map(function(m) {
                        var shortKey = m.key.length > 50 ? m.key.substring(0, 50) + '...' : m.key;
                        var timeOnly = m.time.split(' ')[1] || m.time;
                        return '<div class="cache-miss-item" title="' + escapeHtml(m.key) + '">' +
                            '<span class="cache-miss-time">' + timeOnly + '</span>' +
                            '<span class="cache-miss-key">' + escapeHtml(shortKey) + '</span>' +
                        '</div>';
                    }).join('') +
                '</div>';
            }

            cacheStats.innerHTML =
                '<div style="display: flex; align-items: flex-start; gap: 20px;">' +
                    '<div style="flex: 0 1 200px; min-width: 0;">' +
                        '<div style="display: flex; align-items: flex-start; gap: 15px;">' +
                            '<div>' +
                                '<div class="donut-chart" style="background: ' + gradient + ';">' +
                                    '<span class="donut-value ' + ratioClass + '">' + ratio + '%</span>' +
                                '</div>' +
                                '<div style="margin-top: 6px; font-size: var(--font-size-xs); color: var(--text-muted); text-align: center;">' +
                                    'Backend: <strong>' + (cache.backend || '-') + '</strong>' +
                                '</div>' +
                            '</div>' +
                            '<div style="flex: 1;">' +
                                '<div class="stat-item" style="margin-bottom: 8px;">' +
                                    '<div class="stat-value success">' + cache.hits + '</div>' +
                                    '<div class="stat-label">Hits</div>' +
                                '</div>' +
                                '<div class="stat-item">' +
                                    '<div class="stat-value error">' + cache.misses + '</div>' +
                                    '<div class="stat-label">Misses</div>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    missesHtml +
                '</div>';
        }

        function renderActivityStats(activity) {
            if (activity.error || !activity.daily || activity.daily.length === 0) {
                activityStats.innerHTML = '<div class="stats-loading">Geen gegevens</div>';
                return;
            }

            // Create bar chart for daily activity
            var maxCount = Math.max.apply(null, activity.daily.map(function(d) { return d.count; })) || 1;
            var barsHtml = activity.daily.map(function(d) {
                var height = Math.max(2, (d.count / maxCount) * 100);
                return '<div class="bar" style="height:' + height + '%; background: var(--color-info, #077ab2);" data-count="' + d.date + ': ' + d.count + '"></div>';
            }).join('');

            activityStats.innerHTML =
                '<div class="bar-chart">' + barsHtml + '</div>' +
                '<div class="stats-row">' +
                    '<div class="stat-item">' +
                        '<div class="stat-value">' + activity.total_actions + '</div>' +
                        '<div class="stat-label">Acties</div>' +
                    '</div>' +
                    '<div class="stat-item">' +
                        '<div class="stat-value">' + activity.unique_users + '</div>' +
                        '<div class="stat-label">Gebruikers</div>' +
                    '</div>' +
                '</div>';
        }

        function renderFormsStats(forms) {
            if (forms.error || !forms.forms || forms.forms.length === 0) {
                // Hide card if no data
                var formsCard = formsStats.closest('.stats-card');
                if (formsCard) {
                    formsCard.style.display = 'none';
                }
                return;
            }

            var maxCount = forms.forms[0].count || 1;
            var barsHtml = forms.forms.slice(0, 5).map(function(f) {
                var pct = Math.round((f.count / maxCount) * 100);
                var href = 'form.php?form=' + encodeURIComponent(f.name);
                var displayTitle = f.title || ucfirst(f.name);
                return '<div class="hbar-item">' +
                    '<a class="hbar-label" href="' + href + '" title="' + displayTitle + '">' + displayTitle + '</a>' +
                    '<div class="hbar-track"><div class="hbar-fill" style="width:' + pct + '%"></div></div>' +
                    '<span class="hbar-value">' + f.count + '</span>' +
                '</div>';
            }).join('');

            formsStats.innerHTML = '<div class="hbar-chart">' + barsHtml + '</div>';
        }

        function renderSecurityStats(logins) {
            if (logins.error) {
                securityStats.innerHTML = '<div class="stats-loading">Geen gegevens</div>';
                return;
            }

            // Hide the card if both values are 0
            if (logins.today === 0 && logins.week === 0) {
                var securityCard = document.getElementById('securityCard');
                if (securityCard) {
                    securityCard.style.display = 'none';
                }
                checkSecurityPerfRow();
                return;
            }

            var todayAlert = logins.today > 5 ? ' alert' : '';
            var weekAlert = logins.week > 20 ? ' alert' : '';

            securityStats.innerHTML =
                '<div class="security-grid">' +
                    '<div class="security-item' + todayAlert + '">' +
                        '<div class="security-value">' + logins.today + '</div>' +
                        '<div class="security-label">Vandaag mislukt</div>' +
                    '</div>' +
                    '<div class="security-item' + weekAlert + '">' +
                        '<div class="security-value">' + logins.week + '</div>' +
                        '<div class="security-label">Deze week</div>' +
                    '</div>' +
                '</div>';
        }

        // Check if Row 3 should be hidden (both security and performance cards hidden)
        function checkSecurityPerfRow() {
            var row = document.getElementById('securityPerfRow');
            var secCard = document.getElementById('securityCard');
            var perfCard = document.getElementById('performanceCard');
            if (row) {
                var secHidden = secCard && secCard.style.display === 'none';
                var perfHidden = !perfCard || perfCard.style.display === 'none';
                if (secHidden && perfHidden) {
                    row.style.display = 'none';
                }
            }
        }

        function renderPerformanceStats(perf) {
            if (!perf || ((!perf.slow_queries || perf.slow_queries.length === 0) && (!perf.slow_api || perf.slow_api.length === 0))) {
                // Hide the entire card when no slow operations
                var perfCard = document.getElementById('performanceCard');
                if (perfCard) {
                    perfCard.style.display = 'none';
                }
                checkSecurityPerfRow();
                return;
            }

            var html = '';

            // Slow queries section
            if (perf.slow_queries && perf.slow_queries.length > 0) {
                html += '<div style="margin-bottom: 12px;">' +
                    '<div style="font-weight: 600; font-size: var(--font-size-xs); color: var(--text-muted, #666); margin-bottom: 6px;">Trage queries</div>';
                perf.slow_queries.slice(0, 3).forEach(function(q) {
                    var duration = q.ms ? Math.round(q.ms) + 'ms' : '-';
                    var sqlPreview = (q.sql || '-').substring(0, 254);
                    if (q.sql && q.sql.length > 254) sqlPreview += '...';
                    var queryUrl = 'tools.php?tool=query&sql=' + encodeURIComponent(q.sql || '');
                    html += '<div class="hbar-item" style="margin-bottom: 4px; display: flex; align-items: center;">' +
                        '<a href="' + queryUrl + '" class="hbar-label perf-sql-link" style="flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: var(--font-size-xs); text-decoration: none;" title="Klik om te openen in Query Tool&#10;&#10;' + (q.sql || '').replace(/"/g, '&quot;') + '">' + sqlPreview + '</a>' +
                        '<span class="hbar-value" style="color: var(--color-error, #dc3545); min-width: 50px; text-align: right; font-size: var(--font-size-xs); font-weight: 600;">' + duration + '</span>' +
                    '</div>';
                });
                html += '</div>';
            }

            // Slow API calls section
            if (perf.slow_api && perf.slow_api.length > 0) {
                // Store slow_api data for popup access
                window._slowApiData = perf.slow_api;

                html += '<div>' +
                    '<div style="font-weight: 600; font-size: var(--font-size-xs); color: var(--text-muted, #666); margin-bottom: 6px;">Trage API calls</div>';
                perf.slow_api.slice(0, 3).forEach(function(a, idx) {
                    var duration = a.ms ? Math.round(a.ms) + 'ms' : '-';
                    var endpoint = (a.action || '-').substring(0, 254);
                    if (a.action && a.action.length > 254) endpoint += '...';
                    html += '<div class="hbar-item" style="margin-bottom: 4px; display: flex; align-items: center;">' +
                        '<a href="#" class="hbar-label perf-api-link" style="flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: var(--font-size-xs); text-decoration: none;" title="Klik voor details" data-api-idx="' + idx + '" onclick="showApiPopup(' + idx + '); return false;">' + endpoint + '</a>' +
                        '<span class="hbar-value" style="color: var(--color-warning, #f59e0b); min-width: 50px; text-align: right; font-size: var(--font-size-xs); font-weight: 600;">' + duration + '</span>' +
                    '</div>';
                });
                html += '</div>';
            }

            performanceStats.innerHTML = html;
        }

        // Store last JS errors for popup
        var lastJSErrors = [];

        function renderJSLogStats(jslog) {
            if (!jslog) {
                jslogStats.innerHTML = '<div class="stats-loading">Geen data</div>';
                return;
            }

            // Store last errors for popup
            lastJSErrors = jslog.last_errors || [];

            // Show/hide the logs button based on whether there are entries
            var showJSLogsBtn = document.getElementById('showJSLogsBtn');
            var totalWeek = (jslog.week.error || 0) + (jslog.week.warning || 0) + (jslog.week.info || 0);
            if (showJSLogsBtn) {
                showJSLogsBtn.style.display = totalWeek > 0 ? 'flex' : 'none';
            }

            // Hide card if no data
            if (totalWeek === 0) {
                var jslogCard = jslogStats.closest('.stats-card');
                if (jslogCard) {
                    jslogCard.style.display = 'none';
                }
                return;
            }

            var errorClass = jslog.week.error > 10 ? 'error' : (jslog.week.error > 0 ? 'warning' : 'success');

            jslogStats.innerHTML =
                '<div class="stats-row" style="margin-top: 0; padding-top: 0; border-top: none;">' +
                    '<div class="stat-item">' +
                        '<div class="stat-value error">' + (jslog.week.error || 0) + '</div>' +
                        '<div class="stat-label">Errors</div>' +
                    '</div>' +
                    '<div class="stat-item">' +
                        '<div class="stat-value warning">' + (jslog.week.warning || 0) + '</div>' +
                        '<div class="stat-label">Warnings</div>' +
                    '</div>' +
                    '<div class="stat-item">' +
                        '<div class="stat-value" style="color:var(--color-info, #077ab2);">' + (jslog.week.info || 0) + '</div>' +
                        '<div class="stat-label">Info</div>' +
                    '</div>' +
                '</div>';
        }

        function showJSLogsPopup() {
            if (lastJSErrors.length === 0) {
                return;
            }

            var errorsHtml = lastJSErrors.map(function(e) {
                return '<div style="padding: 8px 0; border-bottom: 1px solid var(--border-color, #e0e0e0);">' +
                    '<div style="display: flex; justify-content: space-between;">' +
                        '<span style="font-weight: 600; color: var(--color-primary, #204496); font-size: var(--font-size-xs);">' + escapeHtml(e.source || 'unknown') + '</span>' +
                        '<span style="color: var(--text-muted, #666); font-size: var(--font-size-xs);">' + e.time + '</span>' +
                    '</div>' +
                    '<div style="font-family: monospace; font-size: var(--font-size-xs); word-break: break-word; margin-top: 4px;">' + escapeHtml(e.message || '') + '</div>' +
                '</div>';
            }).join('');

            if (errorsHtml === '') {
                errorsHtml = '<div style="padding: 8px 0;">Geen recente JavaScript errors</div>';
            }

            // Use lib-dialog component
            var dialog = document.createElement('lib-dialog');
            dialog.id = 'jslogDialog';
            dialog.setAttribute('title', 'Laatste JavaScript Errors');
            dialog.setAttribute('type', 'danger');
            dialog.setAttribute('size', 'large');
            dialog.innerHTML =
                '<div style="max-height: 500px; overflow-y: auto;">' + errorsHtml + '</div>' +
                '<div slot="footer">' +
                    '<button class="btn btn-primary" onclick="document.getElementById(\'jslogDialog\').close()">Sluiten</button>' +
                '</div>';

            document.body.appendChild(dialog);
            dialog.open();

            // Clean up dialog when closed
            dialog.addEventListener('dialog-close', function() {
                dialog.remove();
            });
        }

        // Expose to global scope
        window.showJSLogsPopup = showJSLogsPopup;

        // Show API details popup using lib-dialog
        function showApiPopup(idx) {
            var data = window._slowApiData ? window._slowApiData[idx] : null;
            if (!data) return;

            // Close existing dialog if any
            hideApiPopup();

            var html = '';
            html += '<div class="api-detail-row"><span class="api-detail-label">Actie:</span><span class="api-detail-value">' + escapeHtml(data.action || '-') + '</span></div>';
            html += '<div class="api-detail-row"><span class="api-detail-label">Duur:</span><span class="api-detail-value duration">' + (data.ms ? Math.round(data.ms) + ' ms' : '-') + '</span></div>';

            if (data.ts) {
                var timestamp = data.ts;
                if (typeof timestamp === 'number') {
                    var d = new Date(timestamp * 1000);
                    timestamp = d.toLocaleString('nl-NL');
                }
                html += '<div class="api-detail-row"><span class="api-detail-label">Tijdstip:</span><span class="api-detail-value">' + escapeHtml(timestamp) + '</span></div>';
            }

            if (data.url) {
                html += '<div class="api-detail-row"><span class="api-detail-label">URL:</span><span class="api-detail-value">' + escapeHtml(data.url) + '</span></div>';
            }

            if (data.method) {
                html += '<div class="api-detail-row"><span class="api-detail-label">Methode:</span><span class="api-detail-value">' + escapeHtml(data.method) + '</span></div>';
            }

            if (data.ctx && Object.keys(data.ctx).length > 0) {
                html += '<div class="api-detail-row" style="flex-direction: column;">' +
                    '<span class="api-detail-label" style="margin-bottom: 6px;">Context:</span>' +
                    '<div class="api-detail-context">' + escapeHtml(JSON.stringify(data.ctx, null, 2)) + '</div>' +
                '</div>';
            }

            // Build URL for re-testing
            var retestUrl = data.url;
            if (!retestUrl && data.action) {
                var params = ['action=' + encodeURIComponent(data.action)];
                if (data.ctx && data.ctx.form) {
                    params.push('form=' + encodeURIComponent(data.ctx.form));
                }
                retestUrl = 'form_api.php?' + params.join('&');
            }

            // Add re-test section if we have a URL
            if (retestUrl) {
                data.url = retestUrl;
                window._currentApiRetest = data;

                html += '<div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-color, #e0e0e0);">' +
                    '<button class="btn btn-primary" onclick="reTestApi()" id="retestBtn">Test opnieuw (10x)</button>' +
                    '<div id="retestResults" style="display: none; width: 100%; margin-top: 40px;"></div>' +
                '</div>';
            }

            // Create lib-dialog - use large size for 35% more width than medium
            var dialog = document.createElement('lib-dialog');
            dialog.id = 'apiDialog';
            dialog.setAttribute('title', 'API Call Details');
            dialog.setAttribute('size', 'large');
            dialog.innerHTML = html +
                '<div slot="footer">' +
                    '<button class="btn btn-primary" onclick="hideApiPopup()">Sluiten</button>' +
                '</div>';

            document.body.appendChild(dialog);
            dialog.open();

            dialog.addEventListener('dialog-close', function() {
                window._currentApiRetest = null;
                dialog.remove();
            });
        }

        function hideApiPopup() {
            var dialog = document.getElementById('apiDialog');
            if (dialog) {
                dialog.close();
            }
            window._currentApiRetest = null;
        }

        // Re-test API call 10 times and show results
        async function reTestApi() {
            var data = window._currentApiRetest;
            if (!data || !data.url) return;

            var btn = document.getElementById('retestBtn');
            var resultsDiv = document.getElementById('retestResults');

            if (!btn || !resultsDiv) return;

            // Disable button and show loading text
            btn.disabled = true;
            btn.textContent = 'Bezig...';
            resultsDiv.style.display = 'block';
            resultsDiv.innerHTML = '<div style="text-align: center; color: var(--text-muted, #666);">Bezig met testen...</div>';

            var results = [];
            var successCount = 0;
            var errorCount = 0;
            var totalTime = 0;

            // Run 10 tests sequentially
            for (var i = 0; i < 10; i++) {
                var testNum = i + 1;
                var startTime = performance.now();

                try {
                    // Add cache buster to prevent caching
                    var testUrl = data.url + (data.url.includes('?') ? '&' : '?') + '_retest=' + Date.now() + '_' + i;
                    var response = await fetch(testUrl, {
                        method: data.method || 'GET',
                        cache: 'no-store'
                    });
                    var endTime = performance.now();
                    var duration = Math.round(endTime - startTime);
                    totalTime += duration;

                    if (response.ok) {
                        successCount++;
                        results.push({ num: testNum, success: true, ms: duration, status: response.status });
                    } else {
                        errorCount++;
                        results.push({ num: testNum, success: false, ms: duration, status: response.status });
                    }
                } catch (err) {
                    var endTime = performance.now();
                    var duration = Math.round(endTime - startTime);
                    totalTime += duration;
                    errorCount++;
                    results.push({ num: testNum, success: false, ms: duration, error: err.message });
                }

                // Update results display after each test
                updateRetestDisplay(resultsDiv, results, successCount, errorCount, totalTime, testNum);
            }

            // Re-enable button
            btn.disabled = false;
            btn.textContent = 'Test opnieuw (10x)';
        }

        function updateRetestDisplay(container, results, successCount, errorCount, totalTime, completed) {
            var html = '';

            // Add histogram at the top if all tests complete
            if (completed >= 10) {
                var times = results.map(function(r) { return r.ms; });
                var colors = results.map(function(r) { return r.success ? 'success' : 'error'; });

                html += '<lib-histogram ' +
                    'mode="values" ' +
                    'data="' + times.join(',') + '" ' +
                    'colors="' + colors.join(',') + '" ' +
                    'unit="ms" ' +
                    'height="180" ' +
                    'show-stats="bottom" ' +
                    'show-labels="false">' +
                '</lib-histogram>';

                // Success/error count
                html += '<div class="api-retest-summary" style="margin-top: 8px; color: ' +
                    (errorCount > 0 ? 'var(--color-error, #dc3545)' : 'var(--color-success, #28a745)') + ';">' +
                    successCount + '/10 succesvol' + (errorCount > 0 ? ', ' + errorCount + ' fouten' : '') +
                '</div>';
            }

            // Individual test results
            results.forEach(function(r) {
                var statusClass = r.success ? 'success' : 'error';
                var statusText = r.success ? 'OK' : (r.error || 'HTTP ' + r.status);
                html += '<div class="api-retest-row ' + statusClass + '">' +
                    '<span>Test #' + r.num + '</span>' +
                    '<span>' + r.ms + ' ms - ' + statusText + '</span>' +
                '</div>';
            });

            container.innerHTML = html;
        }

        // escapeHtml() and ucfirst() provided by cma-utils.js

        // Expose popup functions to global scope for onclick handlers
        window.showApiPopup = showApiPopup;
        window.hideApiPopup = hideApiPopup;
        window.reTestApi = reTestApi;

    })();
    </script>
</div>

</body>
</html>
