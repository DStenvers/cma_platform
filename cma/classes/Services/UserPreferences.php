<?php
/**
 * Per-user CMA preferences: theme, popup style and the developer switches
 * (console logging, debug overlay, SQL log threshold).
 *
 * Stored in tblUsers and mirrored to cookies, which is how the JavaScript side
 * (libLog, the debug overlay) and the first page load read them. Two pages
 * write here: preferences.php (display) and tools/tools_settings.php
 * (developer switches, next to the site-wide settings).
 */

namespace Cma\Services;

use App\Library\Cookie;
use App\Library\Database;

class UserPreferences
{
    /** Allowed SQL log thresholds: -1 = off, 0 = all queries, else "longer than n ms". */
    public const SQL_THRESHOLDS = [-1, 0, 50, 100, 250];

    private const DEFAULTS = [
        'prefTheme' => 'light',
        'prefMenuStyle' => 'sidebar',
        'prefPopupStyle' => 'sidepanel',
        'prefDebugMode' => false,
        'prefDebugOverlay' => false,
        'prefSqlThreshold' => -1,
    ];

    /**
     * Load a user's preferences from the database, falling back to the cookie
     * values (a site whose tblUsers lacks the pref columns still works).
     */
    public static function load(int $userId): array
    {
        if ($userId <= 0) {
            return self::DEFAULTS;
        }

        $usersConn = Database::getConnection('users');
        if (!$usersConn) {
            return self::fromCookies();
        }

        $sql = "SELECT prefTheme, prefMenuStyle, prefPopupStyle, prefDebugMode, prefDebugOverlay, prefSqlThreshold FROM tblUsers WHERE ID = $userId";
        try {
            $rs = Database::openRS($sql, $usersConn);
            if ($rs && !$rs->EOF) {
                $row = $rs->fields;
                // Merge database values with defaults (handle NULL values)
                return [
                    'prefTheme' => $row['prefTheme'] ?? Cookie::get('cma_theme', self::DEFAULTS['prefTheme']),
                    'prefMenuStyle' => $row['prefMenuStyle'] ?? Cookie::get('cma_menu_style', self::DEFAULTS['prefMenuStyle']),
                    'prefPopupStyle' => $row['prefPopupStyle'] ?? Cookie::get('cma_popup_style', self::DEFAULTS['prefPopupStyle']),
                    'prefDebugMode' => ($row['prefDebugMode'] ?? false) || Cookie::get('cma_debug_mode', 'N') === 'J',
                    'prefDebugOverlay' => ($row['prefDebugOverlay'] ?? false) || Cookie::get('cma_debug_overlay', 'N') === 'J',
                    'prefSqlThreshold' => (int)($row['prefSqlThreshold'] ?? Cookie::get('cma_sql_threshold', self::DEFAULTS['prefSqlThreshold'])),
                ];
            }
        } catch (\Exception $e) {
            // Typically tblUsers without the preference columns (migration
            // 6.5.0). The cookies still carry the values, but the admin must
            // hear that saves do not reach the database.
            \App\Library\ErrorHandler::report($e, 'Gebruikersvoorkeuren niet uit tblUsers gelezen (migratie 6.5.0 gedraaid?)');
        }
        return self::fromCookies();
    }

    /**
     * Save a user's preferences to the database and sync them to cookies.
     * Pass the complete array (load() merged with the changed keys): every
     * column is written.
     */
    public static function save(int $userId, array $prefs): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $usersConn = Database::getConnection('users');
        if (!$usersConn) {
            return false;
        }

        $theme = Database::escape($prefs['prefTheme'] ?? 'light');
        $menuStyle = Database::escape($prefs['prefMenuStyle'] ?? 'sidebar');
        $popupStyle = Database::escape($prefs['prefPopupStyle'] ?? 'sidepanel');
        $debugMode = ($prefs['prefDebugMode'] ?? false) ? 1 : 0;
        $debugOverlay = ($prefs['prefDebugOverlay'] ?? false) ? 1 : 0;
        $sqlThreshold = (int)($prefs['prefSqlThreshold'] ?? 0);

        $sql = "UPDATE tblUsers SET
            prefTheme = '$theme',
            prefMenuStyle = '$menuStyle',
            prefPopupStyle = '$popupStyle',
            prefDebugMode = $debugMode,
            prefDebugOverlay = $debugOverlay,
            prefSqlThreshold = $sqlThreshold
            WHERE ID = $userId";

        try {
            $usersConn->exec($sql);
        } catch (\Exception $e) {
            \App\Library\ErrorHandler::report($e, 'Gebruikersvoorkeuren niet in tblUsers opgeslagen (migratie 6.5.0 gedraaid?)');
        }
        self::toCookies($prefs);
        return true;
    }

    private static function fromCookies(): array
    {
        return [
            'prefTheme' => Cookie::get('cma_theme', self::DEFAULTS['prefTheme']),
            'prefMenuStyle' => Cookie::get('cma_menu_style', self::DEFAULTS['prefMenuStyle']),
            'prefPopupStyle' => Cookie::get('cma_popup_style', self::DEFAULTS['prefPopupStyle']),
            'prefDebugMode' => Cookie::get('cma_debug_mode', 'N') === 'J',
            'prefDebugOverlay' => Cookie::get('cma_debug_overlay', 'N') === 'J',
            'prefSqlThreshold' => (int)Cookie::get('cma_sql_threshold', '-1'),
        ];
    }

    private static function toCookies(array $prefs): void
    {
        $expires = time() + (365 * 24 * 60 * 60);
        Cookie::set('cma_theme', $prefs['prefTheme'] ?? 'light', $expires);
        Cookie::set('cma_menu_style', $prefs['prefMenuStyle'] ?? 'sidebar', $expires);
        Cookie::set('cma_popup_style', $prefs['prefPopupStyle'] ?? 'sidepanel', $expires);
        // Delete old debug cookie first (may have been httponly), then set new one as non-httponly
        Cookie::delete('cma_debug_mode');
        Cookie::set('cma_debug_mode', ($prefs['prefDebugMode'] ?? false) ? 'J' : 'N', $expires, '/', '', false, false);
        Cookie::set('cma_debug_overlay', ($prefs['prefDebugOverlay'] ?? false) ? 'J' : 'N', $expires);
        Cookie::set('cma_sql_threshold', (string)($prefs['prefSqlThreshold'] ?? 0), $expires);
    }
}
