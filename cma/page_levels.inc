<?php
/**
 * CMA page-level access guard — the minimum SecurityHelper level required to open
 * each CMA page/tool. Enforced once per request in main.php, right after the
 * isLoggedIn gate.
 *
 * Levels are DERIVED from the tools catalog (buildToolsTreeData in tools_catalog.inc)
 * so the menu and the access rule can never drift: a menu item's badge is the
 * authoritative level — 'D' => developer, 'A' => admin, none => user — and a folder's
 * badge is inherited by children that don't set their own.
 *
 * Defaults (fail-safe): a tools/*.php script NOT in the catalog is developer-only
 * (raw dev/ops scripts); every other page is LEVEL_USER (any logged-in CMA user, the
 * behaviour main.php already enforced). Raise/relax a page by giving its catalog item
 * a badge.
 */

use Cma\SecurityHelper;

/** href ('tools/logreader.php' / 'form.php?form=users') => 'user'|'admin'|'developer'. */
function cma_page_level_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    if (!function_exists('buildToolsTreeData')) {
        require_once __DIR__ . '/tools_catalog.inc';
    }
    $map = [];
    if (!function_exists('buildToolsTreeData')) {
        return $map;
    }
    $badgeToLevel = static function (string $b): string {
        return $b === 'D' ? 'developer' : ($b === 'A' ? 'admin' : 'user');
    };
    $walk = static function (array $nodes, string $inherited) use (&$walk, &$map, $badgeToLevel): void {
        foreach ($nodes as $n) {
            if (!is_array($n)) {
                continue;
            }
            $badge = (string) ($n['badge'] ?? '');
            $lvl = $badge !== '' ? $badgeToLevel($badge) : $inherited;
            if (($n['type'] ?? '') === 'folder') {
                $walk($n['children'] ?? [], $lvl);
            } elseif (!empty($n['href'])) {
                $map[(string) $n['href']] = $lvl;
            }
        }
    };
    // Full (developer) view so every item is present in the map.
    $walk(buildToolsTreeData(true), 'user');
    return $map;
}

/** Minimum level slug required to open $requestedPage (main.php's raw 'page' value). */
function cma_required_level(string $requestedPage): string
{
    $page = trim($requestedPage);
    if ($page === '') {
        return 'user';
    }
    // Split off the querystring, but keep a form= param (forms are catalog items
    // keyed as 'form.php?form=X').
    $qs = '';
    if (strpos($page, '?') !== false) {
        list($page, $qs) = explode('?', $page, 2);
    }
    $page = str_replace(['../', '..\\'], '', $page);
    if (!preg_match('#^(tools|api)/[a-zA-Z0-9_-]+\.php$#', $page)) {
        $page = basename($page);
    }

    $map = cma_page_level_map();
    if ($page === 'form.php' && $qs !== '') {
        parse_str($qs, $q);
        $formKey = 'form.php?form=' . ($q['form'] ?? '');
        if (isset($map[$formKey])) {
            return $map[$formKey];
        }
    }
    if (isset($map[$page])) {
        return $map[$page];
    }
    // Raw dev/ops scripts under tools/ that aren't in the menu are developer-only.
    if (strncmp($page, 'tools/', 6) === 0) {
        return 'developer';
    }
    return 'user';
}

/** Enforce the minimum level for the requested CMA page. 403s (nomenu-aware) if below. */
function cma_enforce_page_level(string $requestedPage): void
{
    $need = cma_required_level($requestedPage);
    if ($need === 'user') {
        return; // any logged-in user — main.php already verified the login
    }
    $slugToLevel = [
        'user'      => SecurityHelper::LEVEL_USER,
        'admin'     => SecurityHelper::LEVEL_ADMIN,
        'developer' => SecurityHelper::LEVEL_DEVELOPER,
    ];
    $needLevel = $slugToLevel[$need] ?? SecurityHelper::LEVEL_USER;
    if (SecurityHelper::getUserLevel() >= $needLevel) {
        return;
    }

    // Denied — mirror main.php's nomenu-aware response.
    if (!headers_sent()) {
        http_response_code(403);
    }
    $label = SecurityHelper::getUserLevelName($needLevel);
    if (isset($_GET['nomenu'])) {
        echo '<div class="session-expired-message" style="padding:40px;text-align:center">';
        echo '<lib-message type="warning"><span class="cma-page__strong">Geen toegang</span><br>'
            . 'Deze functie vereist minimaal het niveau <b>' . htmlspecialchars($label) . '</b>.</lib-message>';
        echo '</div>';
    } else {
        echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><title>Geen toegang</title></head>'
            . '<body style="font-family:Segoe UI,Arial,sans-serif;padding:48px;color:#333">'
            . '<h2 style="color:#b00">Geen toegang</h2>'
            . '<p>Deze functie vereist minimaal het niveau <b>' . htmlspecialchars($label) . '</b>.</p>'
            . '<p><a href="/cma/">&larr; Terug naar CMA</a></p></body></html>';
    }
    exit;
}
