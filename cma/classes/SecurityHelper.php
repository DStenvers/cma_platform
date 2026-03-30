<?php

namespace Cma;

use App\Library\Arr;
use App\Library\Cache;
use App\Library\Cookie;

/**
 * CMA Security Helper
 *
 * Provides user authentication and permission checking functionality.
 */
class SecurityHelper
{
    // Security type constants
    public const TYPE_MENU = 10;
    public const TYPE_REPORT = 20;
    public const TYPE_FORM = 30;

    // Access level constants
    public const ACCESS_NONE = 0;
    public const ACCESS_READ = 10;
    public const ACCESS_CHANGE_OWN_DATA = 20;
    public const ACCESS_FULL = 30;
    public const ACCESS_FULL_BEHEER = 40;

    // Array index constants for group rights query result
    private const ARR_OBJECTID = 0;
    private const ARR_ACCESSTYPE = 1;

    // Cookie constants
    public const COOKIE_USERID = 'CMAU';
    public const COOKIE_USERGUID = 'CMAG';  // Must match userGUID in database for validation
    /** @deprecated No longer used - userLevel is fetched from database */
    public const COOKIE_ADMIN = 'CMAADM';
    /** @deprecated No longer used - userLevel is fetched from database */
    public const COOKIE_LEVEL = 'CMALEVEL';
    /** @deprecated No longer used - username is fetched from database */
    public const COOKIE_USERNAME = 'CMAUNAME';
    public const COOKIE_SKIP_NOT = 'CMASK_NOT';
    public const COOKIE_LAST_LOGIN = 'CMAlast_login_name';

    // User level constants
    public const LEVEL_USER = 0;
    public const LEVEL_ADMIN = 1;
    public const LEVEL_DEVELOPER = 2;

    // Static cache for indexed rights lookups (optimization)
    private static array $groupRightsIndex = [];

    // Static cache for menu item lookups by form name
    private static array $menuItemCache = [];

    // Static cache for formId to formName mapping
    private static array $formIdToNameCache = [];
    private static bool $formIdMappingLoaded = false;

    // Cached validation result for current request
    private static ?bool $isValidatedCache = null;

    /**
     * Controleer of de huidige gebruiker is ingelogd
     * Validates userID cookie against database.
     * GUID cookie is set during login for additional security but
     * a missing GUID does not block login (auto-set if missing).
     */
    public static function isLoggedIn(): bool
    {
        // Use cached result if available
        if (self::$isValidatedCache !== null) {
            return self::$isValidatedCache;
        }

        $userId = Cookie::get(self::COOKIE_USERID, '');

        // Must have userID cookie
        if ($userId === '') {
            self::$isValidatedCache = false;
            return false;
        }

        // Validate user exists in database
        $userData = self::getCurrentUserData();
        if ($userData === null) {
            error_log("[SecurityHelper::isLoggedIn] getCurrentUserData returned null for CMAU=$userId");
            self::$isValidatedCache = false;
            return false;
        }

        // If GUID cookie is missing but user is valid, try to auto-set it
        try {
            $guidCookie = Cookie::get(self::COOKIE_USERGUID, '');
            if ($guidCookie === '') {
                $dbGuid = self::getUserGuid((int)$userId);
                if (!empty($dbGuid)) {
                    Cookie::set(self::COOKIE_USERGUID, $dbGuid);
                }
            }
        } catch (\Exception $e) {
            // GUID handling is optional - don't block login
        }

        self::$isValidatedCache = true;
        return true;
    }

    /**
     * Get userGUID from database for a given user ID
     */
    private static function getUserGuid(int $userId): string
    {
        try {
            $conn = \App\Library\Database::getConnection('users');
            if ($conn === null) return '';
            $rs = \App\Library\Database::openRS(
                "SELECT userGUID FROM tblUsers WHERE ID = $userId",
                $conn, 0
            );
            if ($rs && !$rs->EOF) {
                return (string)($rs->fields['userGUID'] ?? '');
            }
        } catch (\Exception $e) {
            // userGUID column may not exist yet - non-fatal
        }
        return '';
    }

    /**
     * Controleer of de huidige gebruiker een administrator is (of hoger)
     * Developers worden ook als administrators beschouwd
     */
    public static function isAdmin(): bool
    {
        $isLoggedIn = self::isLoggedIn();
        if (!$isLoggedIn) {
            error_log("[SecurityHelper::isAdmin] Not logged in, returning false");
            return false;
        }
        // Check userLevel from database - cookies are no longer trusted
        $level = self::getUserLevel();
        $result = $level >= self::LEVEL_ADMIN;
        error_log("[SecurityHelper::isAdmin] userLevel=$level, LEVEL_ADMIN=" . self::LEVEL_ADMIN . ", result=" . ($result ? 'true' : 'false'));
        return $result;
    }

    /**
     * Check if current user is a developer
     */
    public static function isDeveloper(): bool
    {
        if (!self::isLoggedIn()) {
            return false;
        }
        return self::getUserLevel() >= self::LEVEL_DEVELOPER;
    }

    /**
     * Check if debug mode is enabled for current user
     * Debug mode is only available to admins/developers who have enabled it in preferences
     */
    public static function isDebugMode(): bool
    {
        // Must be admin or developer
        if (!self::isAdmin() && !self::isDeveloper()) {
            return false;
        }
        // Check preference cookie
        return Cookie::get('cma_debug_mode', 'N') === 'J';
    }

    // Static cache for user data to avoid repeated database queries
    private static ?array $currentUserCache = null;

    /**
     * Get the current user's level (0=User, 1=Admin, 2=Developer)
     * Fetches from database to avoid trusting cookies for security
     */
    public static function getUserLevel(): int
    {
        $userData = self::getCurrentUserData();
        // Use userLevel if available, otherwise fall back to userAdministrator (legacy)
        if (isset($userData['userLevel']) && $userData['userLevel'] !== null) {
            return (int)$userData['userLevel'];
        }
        // Legacy: userAdministrator=true maps to LEVEL_ADMIN
        if (!empty($userData['userAdministrator'])) {
            return self::LEVEL_ADMIN;
        }
        return self::LEVEL_USER;
    }

    /**
     * Get current user data from database (cached for the request)
     * @return array|null User data or null if not found
     */
    public static function getCurrentUserData(): ?array
    {
        if (self::$currentUserCache !== null) {
            return self::$currentUserCache;
        }

        $userId = Cookie::get(self::COOKIE_USERID, '');
        if ($userId === '') {
            error_log("[SecurityHelper::getCurrentUserData] No CMAU cookie found");
            return null;
        }

        try {
            $conn = \App\Library\Database::getConnection('users');
            if ($conn === null) {
                error_log("[SecurityHelper::getCurrentUserData] Database connection 'users' returned null");
                return null;
            }
            $safeId = intval($userId);
            // Use direct PDO instead of RecordSet — RecordSet has field lookup issues with Access ODBC
            $stmt = $conn->prepare("SELECT * FROM tblUsers WHERE ID = ?");
            $stmt->execute([$safeId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $userData = null;
            if ($row) {
                $dbLevel = $row['userLevel'] ?? null;
                $dbAdmin = $row['userAdministrator'] ?? null;

                // Use userLevel column if available and > 0, fall back to userAdministrator boolean
                if ($dbLevel !== null && $dbLevel !== '' && intval($dbLevel) > 0) {
                    $userLevel = intval($dbLevel);
                } else {
                    $isAdmin = $dbAdmin;
                    $userLevel = ($isAdmin === true || $isAdmin === 1 || $isAdmin === -1 || $isAdmin === '1' || $isAdmin === '-1') ? self::LEVEL_ADMIN : self::LEVEL_USER;
                }
                $userData = [
                    'ID' => $row['ID'],
                    'userLogin' => $row['userLogin'] ?? '',
                    'userFullName' => $row['userFullName'] ?? '',
                    'userLevel' => $userLevel,
                    'userEmail' => $row['userEMail'] ?? '',
                    'userSkipNotifyOwnRecords' => $row['userSkipNotifyOwnRecords'] ?? 0,
                ];
            } else {
                error_log("[SecurityHelper::getCurrentUserData] No user found for ID=$safeId");
            }
            self::$currentUserCache = $userData;
            return self::$currentUserCache;
        } catch (\Throwable $e) {
            error_log("[SecurityHelper::getCurrentUserData] Error: " . get_class($e) . ": " . $e->getMessage());
            return null;
        }
    }

    /**
     * Clear the user data cache (call after logout or user switch)
     */
    public static function clearUserCache(): void
    {
        self::$currentUserCache = null;
        self::$isValidatedCache = null;
    }

    /**
     * Get the current user's full name from database
     */
    public static function getCurrentUserName(): string
    {
        $userData = self::getCurrentUserData();
        return $userData['userFullName'] ?? '';
    }

    /**
     * Get the current user's email from database
     */
    public static function getCurrentUserEmail(): string
    {
        $userData = self::getCurrentUserData();
        return $userData['userEmail'] ?? '';
    }

    /**
     * Check if current user has skip notifications setting enabled
     */
    public static function skipNotifyOwnRecords(): bool
    {
        $userData = self::getCurrentUserData();
        $value = $userData['userSkipNotifyOwnRecords'] ?? 0;
        return $value === true || $value === 1 || $value === -1 || $value === '1' || $value === '-1';
    }

    /**
     * Get user level display name
     */
    public static function getUserLevelName(int $level): string
    {
        return match($level) {
            self::LEVEL_DEVELOPER => 'Developer',
            self::LEVEL_ADMIN => 'Administrator',
            default => 'Gebruiker',
        };
    }

    /**
     * Get the current user's ID from cookie
     */
    public static function getCurrentUserId(): string
    {
        return Cookie::get(self::COOKIE_USERID, '');
    }

    /**
     * Get user's display name
     */
    public static function getUserName(int $userId): string
    {
        if ($userId <= 0 || !self::isLoggedIn()) {
            return '';
        }

        $sql = 'SELECT userFullName, userLogin FROM tblUsers where (ID=' . $userId . ')';
        $arrUser = Cache::retrieve('cma_access_username_' . $userId, 'users', $sql);

        if (Arr::isArray($arrUser)) {
            if (($arrUser[0][0] ?? '') !== '') {
                return $arrUser[0][0];
            }
            return $arrUser[1][0] ?? '';
        }

        return '';
    }

    /**
     * Check notification settings for a user on a form
     * Does NOT take into account the value of COOKIE_SKIP_NOT
     */
    public static function checkNotifyForUser(int $userId, int $formId): bool
    {
        $sql = 'SELECT count(*) as aantal from tblNotifications where ( fkUserID=' . $userId . ' and fkFormID=' . $formId . ' )';
        $arrNotify = Cache::retrieveFromFile('CMA_access_notify_' . $userId . '_' . $formId, 'users', $sql);
        return ($arrNotify[0][0] ?? 0) > 0;
    }

    /**
     * Get email addresses that should be notified of changes to a form
     * Returns semicolon-delimited string of email addresses
     */
    public static function getNotifyEmailsForForm(int $formId): string
    {
        $sql = 'SELECT tblUsers.userEMail, tblUsers.ID FROM tblUsers INNER JOIN tblNotifications ON tblUsers.ID = tblNotifications.fkUserID WHERE (tblNotifications.fkFormID=' . $formId . ')';
        $arrNotify = Cache::retrieveFromFile('CMA_access_notify_email_' . $formId, 'users', $sql);

        $result = '';
        if (Arr::isArray($arrNotify)) {
            for ($t = 0; $t <= (count($arrNotify) - 1); $t++) {
                // check for notifications made by the current user
                $blnSkip = false;
                if (!$blnSkip) {
                    if ($result !== '') {
                        $result .= ';';
                    }
                    $result .= $arrNotify[0][$t];
                }
            }
        }
        return $result;
    }

    /**
     * Check access rights for a specific user
     */
    public static function checkRightsForUser(int $userId, int $type, int $objectId, int $buttonId = -1, ?array &$debugInfo = null): int
    {
        $accessType = self::ACCESS_NONE;
        $typeNames = [self::TYPE_MENU => 'MENU', self::TYPE_REPORT => 'REPORT', self::TYPE_FORM => 'FORM'];

        if ($debugInfo !== null) {
            $debugInfo[] = "CheckRightsforUser: UserID=$userId, Type=" . ($typeNames[$type] ?? $type) . ", ObjectID=$objectId";
        }

        if (self::isAdmin()) {
            $accessType = self::ACCESS_FULL;
            if ($debugInfo !== null) {
                $debugInfo[] = "  => User is admin, returning FULL (30)";
            }
        } elseif (self::isLoggedIn()) {
            $sql = 'SELECT tblGroupRights.secObjectID, Max(tblGroupRights.secAccessType) AS AccessType, tblGroupRights.secButton1, tblGroupRights.secButton2, tblGroupRights.secButton3, tblGroupRights.secButton4, tblGroupRights.secButton5 FROM (tblGroups INNER JOIN tblGroupMembers ON tblGroups.ID = tblGroupMembers.fkGroup) INNER JOIN tblGroupRights ON tblGroups.ID = tblGroupRights.fkGroup GROUP BY tblGroupMembers.fkUser, tblGroupRights.secObjectType, tblGroupRights.secObjectID, tblGroupRights.secButton1, tblGroupRights.secButton2, tblGroupRights.secButton3, tblGroupRights.secButton4, tblGroupRights.secButton5 HAVING (((tblGroupMembers.fkUser)=' . $userId . ') AND ((tblGroupRights.secObjectType)=' . $type . ') AND ((Max(tblGroupRights.secAccessType))>0))';
            $arrRights = Cache::retrieveFromFile('CMA_access_' . $userId . '_' . $type, 'users', $sql);

            if ($debugInfo !== null) {
                $debugInfo[] = "  Group rights query returned: " . (Arr::isArray($arrRights) ? count($arrRights[0] ?? []) . " rows" : "no results");
            }

            if (Arr::isArray($arrRights)) {
                $matchFound = false;
                for ($t = 0; $t <= (count($arrRights[0] ?? []) - 1); $t++) {
                    if ($debugInfo !== null) {
                        $debugInfo[] = "    Row $t: ObjectID=" . ($arrRights[self::ARR_OBJECTID][$t] ?? 'null') . ", AccessType=" . ($arrRights[self::ARR_ACCESSTYPE][$t] ?? 'null');
                    }
                    if (($arrRights[self::ARR_OBJECTID][$t] ?? '') == $objectId . '') {
                        $matchFound = true;
                        $accessType = max($accessType, $arrRights[self::ARR_ACCESSTYPE][$t] ?? 0);
                        if ($buttonId > -1) {
                            $buttonValue = $arrRights[self::ARR_ACCESSTYPE + $buttonId][$t] ?? 0;
                            $accessType = max($accessType, ($buttonValue ? self::ACCESS_FULL : self::ACCESS_NONE));
                        }
                        if ($debugInfo !== null) {
                            $debugInfo[] = "    => MATCH! AccessType now: $accessType";
                        }
                    }
                }
                if (!$matchFound && $debugInfo !== null) {
                    $debugInfo[] = "  => No matching ObjectID found in group rights!";
                }
            }
        } else {
            if ($debugInfo !== null) {
                $debugInfo[] = "  => Not logged in, returning NONE (0)";
            }
        }

        if ($debugInfo !== null) {
            $debugInfo[] = "  Final result: $accessType";
        }
        return $accessType;
    }

    /**
     * In-request cache for form rights
     * @var array<string, int>
     */
    private static array $formRightsCache = [];

    /**
     * Check form-specific rights directly from tblGroupRights (TYPE_FORM)
     * Used for subforms that have their own rights defined
     *
     * @param int $userId User ID
     * @param int $formId Form's sourceFormId
     * @param array|null $debugInfo Debug output array (optional)
     * @return int|null Access level or null if no specific rights defined
     */
    private static function checkFormSpecificRights(int $userId, int $formId, ?array &$debugInfo = null): ?int
    {
        if (!self::isLoggedIn()) {
            return null;
        }

        // Query for form-specific rights (TYPE_FORM = 30)
        $sql = 'SELECT tblGroupRights.secObjectID, Max(tblGroupRights.secAccessType) AS AccessType ' .
            'FROM (tblGroups INNER JOIN tblGroupMembers ON tblGroups.ID = tblGroupMembers.fkGroup) ' .
            'INNER JOIN tblGroupRights ON tblGroups.ID = tblGroupRights.fkGroup ' .
            'WHERE tblGroupMembers.fkUser = ' . $userId . ' ' .
            'AND tblGroupRights.secObjectType = ' . self::TYPE_FORM . ' ' .
            'AND tblGroupRights.secObjectID = ' . $formId . ' ' .
            'GROUP BY tblGroupRights.secObjectID ' .
            'HAVING Max(tblGroupRights.secAccessType) > 0';

        $arrRights = Cache::retrieveFromFile('CMA_form_rights_' . $userId . '_' . $formId, 'users', $sql);

        if ($debugInfo !== null) {
            $debugInfo[] = "  Checking form-specific rights for formId=$formId";
        }

        if (Arr::isArray($arrRights) && !empty($arrRights[0])) {
            $accessType = (int)($arrRights[1][0] ?? 0);
            if ($debugInfo !== null) {
                $debugInfo[] = "  => Form-specific rights found: $accessType";
            }
            return $accessType;
        }

        if ($debugInfo !== null) {
            $debugInfo[] = "  => No form-specific rights found";
        }
        return null;
    }

    /**
     * Check form access rights via menu lookup
     * For subforms: checks form-specific rights first, then falls back to parent form rights
     * For main forms: checks via menu item lookup
     */
    public static function checkFormRights(int $userId, int $formId, bool $debug = false): int
    {
        // Check in-request cache first (skip if debug mode)
        $cacheKey = $userId . '_' . $formId;
        if (!$debug && isset(self::$formRightsCache[$cacheKey])) {
            return self::$formRightsCache[$cacheKey];
        }

        $accessType = self::ACCESS_NONE;
        $debugInfo = [];

        if ($debug) {
            $debugInfo[] = "CheckFormRights called with UserID=$userId, FormID=$formId";
            $debugInfo[] = "bLoggedIn=" . (self::isLoggedIn() ? 'true' : 'false');
        }

        if (self::isAdmin()) {
            $accessType = self::ACCESS_FULL_BEHEER;
            if ($debug) {
                $debugInfo[] = "User is ADMIN => FULL_BEHEER (40)";
            }
        } elseif (self::isLoggedIn()) {
            // Check if this is a subform
            $parentFormId = JsonFormLoader::getParentFormId($formId);
            $isSubform = $parentFormId !== null;

            if ($debug) {
                $debugInfo[] = "Is subform: " . ($isSubform ? "yes (parent=$parentFormId)" : "no");
            }

            if ($isSubform) {
                // For subforms: check form-specific rights first
                $formSpecificRights = $debug
                    ? self::checkFormSpecificRights($userId, $formId, $debugInfo)
                    : self::checkFormSpecificRights($userId, $formId);

                if ($formSpecificRights !== null) {
                    // Subform has its own rights defined
                    $accessType = $formSpecificRights;
                    if ($debug) {
                        $debugInfo[] = "Using subform-specific rights: $accessType";
                    }
                } else {
                    // Fall back to parent form rights
                    if ($debug) {
                        $debugInfo[] = "Falling back to parent form rights (formId=$parentFormId)";
                    }
                    $accessType = self::checkFormRights($userId, $parentFormId, $debug);
                    // Don't duplicate debug info - it's already in $GLOBALS
                }
            } else {
                // For main forms: use menu item lookup
                $formName = self::getFormNameById($formId);
                if ($debug) {
                    $debugInfo[] = "Looking up form name for formId: $formId";
                    $debugInfo[] = "Form name found: " . ($formName ?? 'none');
                }

                $menuItemId = $formName !== null ? self::getMenuItemIdForFormName($formName) : null;
                if ($debug) {
                    $debugInfo[] = "Menu item ID found: " . ($menuItemId ?? 'none');
                }

                if ($menuItemId !== null) {
                    if ($debug) {
                        $debugInfo[] = "Checking rights for menu item ID: $menuItemId";
                        $accessType = self::checkRightsForUser($userId, self::TYPE_MENU, $menuItemId, -1, $debugInfo);
                    } else {
                        $accessType = self::checkRightsForUser($userId, self::TYPE_MENU, $menuItemId);
                    }
                } else {
                    // No menu item found - form is not in menu, deny access for non-admins
                    if ($debug) {
                        $debugInfo[] = "No menu item found for form - denying access";
                    }
                    $accessType = self::ACCESS_NONE;
                }
            }
        } else {
            if ($debug) {
                $debugInfo[] = "User not logged in => NONE (0)";
            }
        }

        if ($debug) {
            $debugInfo[] = "Final access type: $accessType";
            $security_debug = $debugInfo;
        }

        // Store in request cache
        self::$formRightsCache[$cacheKey] = $accessType;

        return $accessType;
    }

    /**
     * Check form access rights by form name (for JSON forms)
     * For subforms: checks form-specific rights first, then falls back to parent form rights
     * For main forms: uses menu item lookup
     *
     * @param int $userId User ID
     * @param string $formName JSON form filename (without .json)
     * @param bool $debug Enable debug output
     * @return int Access level
     */
    public static function checkFormRightsByName(int $userId, string $formName, bool $debug = false): int
    {
        // Check in-request cache first (skip if debug mode)
        $cacheKey = $userId . '_name_' . $formName;
        if (!$debug && isset(self::$formRightsCache[$cacheKey])) {
            return self::$formRightsCache[$cacheKey];
        }

        $accessType = self::ACCESS_NONE;
        $debugInfo = [];

        if ($debug) {
            $debugInfo[] = "CheckFormRightsByName called with UserID=$userId, FormName=$formName";
            $debugInfo[] = "bLoggedIn=" . (self::isLoggedIn() ? 'true' : 'false');
        }

        if (self::isAdmin()) {
            $accessType = self::ACCESS_FULL_BEHEER;
            if ($debug) {
                $debugInfo[] = "User is ADMIN => FULL_BEHEER (40)";
            }
        } elseif (self::isLoggedIn()) {
            // Get form ID and check if this is a subform
            $formId = JsonFormLoader::getFormIdByName($formName);
            $parentFormId = $formId !== null ? JsonFormLoader::getParentFormId($formId) : null;
            $isSubform = $parentFormId !== null;

            if ($debug) {
                $debugInfo[] = "FormId: " . ($formId ?? 'none') . ", Is subform: " . ($isSubform ? "yes (parent=$parentFormId)" : "no");
            }

            if ($isSubform && $formId !== null) {
                // For subforms: check form-specific rights first
                $formSpecificRights = $debug
                    ? self::checkFormSpecificRights($userId, $formId, $debugInfo)
                    : self::checkFormSpecificRights($userId, $formId);

                if ($formSpecificRights !== null) {
                    // Subform has its own rights defined
                    $accessType = $formSpecificRights;
                    if ($debug) {
                        $debugInfo[] = "Using subform-specific rights: $accessType";
                    }
                } else {
                    // Fall back to parent form rights
                    if ($debug) {
                        $debugInfo[] = "Falling back to parent form rights (formId=$parentFormId)";
                    }
                    $accessType = self::checkFormRights($userId, $parentFormId, $debug);
                }
            } else {
                // For main forms: use menu item lookup
                $menuItemId = self::getMenuItemIdForFormName($formName);
                if ($debug) {
                    $debugInfo[] = "Menu item ID found: " . ($menuItemId ?? 'none');
                }

                if ($menuItemId !== null) {
                    if ($debug) {
                        $debugInfo[] = "Checking rights for menu item ID: $menuItemId";
                        $accessType = self::checkRightsForUser($userId, self::TYPE_MENU, $menuItemId, -1, $debugInfo);
                    } else {
                        $accessType = self::checkRightsForUser($userId, self::TYPE_MENU, $menuItemId);
                    }
                } else {
                    // No menu item found - form is not in menu, deny access for non-admins
                    if ($debug) {
                        $debugInfo[] = "No menu item found for form - denying access";
                    }
                    $accessType = self::ACCESS_NONE;
                }
            }
        } else {
            if ($debug) {
                $debugInfo[] = "User not logged in => NONE (0)";
            }
        }

        if ($debug) {
            $debugInfo[] = "Final access type: $accessType";
            $security_debug = $debugInfo;
        }

        // Store in request cache
        self::$formRightsCache[$cacheKey] = $accessType;

        return $accessType;
    }

    /**
     * Check form-specific button rights directly from tblGroupRights (TYPE_FORM)
     * Used for subforms that have their own button rights defined
     *
     * @param int $userId User ID
     * @param int $formId Form's sourceFormId
     * @param int $buttonId Button ID (1-5)
     * @return int|null Access level or null if no specific rights defined
     */
    private static function checkFormSpecificButtonRights(int $userId, int $formId, int $buttonId): ?int
    {
        if (!self::isLoggedIn() || $buttonId < 1 || $buttonId > 5) {
            return null;
        }

        $buttonCol = 'secButton' . $buttonId;

        // Query for form-specific button rights (TYPE_FORM = 30)
        $sql = 'SELECT tblGroupRights.' . $buttonCol . ' ' .
            'FROM (tblGroups INNER JOIN tblGroupMembers ON tblGroups.ID = tblGroupMembers.fkGroup) ' .
            'INNER JOIN tblGroupRights ON tblGroups.ID = tblGroupRights.fkGroup ' .
            'WHERE tblGroupMembers.fkUser = ' . $userId . ' ' .
            'AND tblGroupRights.secObjectType = ' . self::TYPE_FORM . ' ' .
            'AND tblGroupRights.secObjectID = ' . $formId . ' ' .
            'AND tblGroupRights.secAccessType > 0';

        $arrRights = Cache::retrieveFromFile('CMA_form_btn_rights_' . $userId . '_' . $formId . '_' . $buttonId, 'users', $sql);

        if (Arr::isArray($arrRights) && !empty($arrRights[0])) {
            $buttonValue = $arrRights[0][0] ?? 0;
            return $buttonValue ? self::ACCESS_FULL : self::ACCESS_NONE;
        }

        return null;
    }

    /**
     * Check button-specific rights for a form
     * For subforms: checks form-specific button rights first, then falls back to parent form
     * For main forms: uses menu item lookup
     */
    public static function checkFormButtonRights(int $userId, int $formId, int $buttonId): int
    {
        $accessType = self::ACCESS_NONE;

        if (self::isAdmin()) {
            $accessType = self::ACCESS_FULL;
        } elseif (self::isLoggedIn()) {
            // Check if this is a subform
            $parentFormId = JsonFormLoader::getParentFormId($formId);
            $isSubform = $parentFormId !== null;

            if ($isSubform) {
                // For subforms: check form-specific button rights first
                $formSpecificRights = self::checkFormSpecificButtonRights($userId, $formId, $buttonId);

                if ($formSpecificRights !== null) {
                    $accessType = $formSpecificRights;
                } else {
                    // Fall back to parent form button rights
                    $accessType = self::checkFormButtonRights($userId, $parentFormId, $buttonId);
                }
            } else {
                // For main forms: use menu item lookup
                $formName = self::getFormNameById($formId);
                $menuItemId = $formName !== null ? self::getMenuItemIdForFormName($formName) : null;

                if ($menuItemId !== null) {
                    $accessType = self::checkRightsForUser($userId, self::TYPE_MENU, $menuItemId, $buttonId);
                }
            }
        }

        return $accessType;
    }

    /**
     * Load formId to formName mapping from JSON definitions
     * Scans all JSON form files for sourceFormId
     * Searches both internal and external directories
     */
    private static function loadFormIdMapping(): void
    {
        if (self::$formIdMappingLoaded) {
            return;
        }

        // Search both internal and external directories
        $directories = [
            __DIR__ . '/../assets/forms/definitions',  // Internal (CMA)
            __DIR__ . '/../../assets/forms',           // External (site)
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
                    // Don't overwrite if already set (internal takes precedence)
                    if (!isset(self::$formIdToNameCache[$formDef['sourceFormId']])) {
                        self::$formIdToNameCache[$formDef['sourceFormId']] = $formName;
                    }
                }
            }
        }

        self::$formIdMappingLoaded = true;
    }

    /**
     * Get form name from form ID using JSON definitions
     *
     * @param int $formId Database form ID (sourceFormId in JSON)
     * @return string|null Form name or null if not found
     */
    private static function getFormNameById(int $formId): ?string
    {
        self::loadFormIdMapping();
        return self::$formIdToNameCache[$formId] ?? null;
    }

    /**
     * Get menu item ID for a form name
     * Uses JSON config (menu.json) via MenuService
     *
     * @param string $formName JSON form filename (without .json)
     * @return int|null Menu item ID or null if not found
     */
    private static function getMenuItemIdForFormName(string $formName): ?int
    {
        // Check cache first
        if (isset(self::$menuItemCache[$formName])) {
            return self::$menuItemCache[$formName];
        }

        // Use MenuService to find menu item
        $menuItemId = Services\MenuService::getMenuItemIdForForm($formName);
        self::$menuItemCache[$formName] = $menuItemId;
        return $menuItemId;
    }

    /**
     * Get menu item ID for a form by its database ID
     * Maps formId -> formName -> menuItemId
     *
     * @param int $formId Database form ID
     * @return int|null Menu item ID or null if not found
     */
    private static function getMenuItemIdForForm(int $formId): ?int
    {
        $formName = self::getFormNameById($formId);
        if ($formName === null) {
            return null;
        }
        return self::getMenuItemIdForFormName($formName);
    }

    /**
     * Check access rights for the current user
     */
    public static function checkRights(int $type, int $value): int
    {
        if (self::isAdmin()) {
            return self::ACCESS_FULL;
        }
        if (self::isLoggedIn()) {
            return self::checkRightsForUser((int) self::getCurrentUserId(), $type, $value, -1);
        }
        return self::ACCESS_NONE;
    }

    /**
     * Check if an IP address matches a pattern (exact match or CIDR notation)
     *
     * @param string $ipAddress The IP address to check (e.g., "192.168.1.4")
     * @param string $pattern The pattern to match against (e.g., "192.168.1.4" or "192.168.1.0/24")
     * @return bool True if the IP matches the pattern
     */
    public static function ipMatchesPattern(string $ipAddress, string $pattern): bool
    {
        $ipAddress = trim($ipAddress);
        $pattern = trim($pattern);

        if ($ipAddress === '' || $pattern === '') {
            return false;
        }

        // Check if pattern contains CIDR notation
        if (strpos($pattern, '/') !== false) {
            return self::ipInCidrRange($ipAddress, $pattern);
        }

        // Exact match comparison
        return $ipAddress === $pattern;
    }

    /**
     * Check if an IP address is within a CIDR range
     *
     * @param string $ipAddress The IP address to check
     * @param string $cidr The CIDR notation (e.g., "192.168.1.0/24")
     * @return bool True if the IP is within the range
     */
    private static function ipInCidrRange(string $ipAddress, string $cidr): bool
    {
        $parts = explode('/', $cidr);
        if (count($parts) !== 2) {
            return false;
        }

        $networkIp = $parts[0];
        $prefix = (int)$parts[1];

        // Validate prefix length
        if ($prefix < 0 || $prefix > 32) {
            return false;
        }

        // Convert to long integers
        $ipLong = ip2long($ipAddress);
        $networkLong = ip2long($networkIp);

        // Check for invalid IP addresses
        if ($ipLong === false || $networkLong === false) {
            return false;
        }

        // Calculate the subnet mask
        // For /24: -1 << (32-24) = -1 << 8 = 0xFFFFFF00
        $mask = -1 << (32 - $prefix);

        // Check if the IP is in the range
        // Both IP and network must have same bits when masked
        return ($ipLong & $mask) === ($networkLong & $mask);
    }

    /**
     * Check if an IP address matches any pattern in a list
     *
     * @param string $ipAddress The IP address to check
     * @param array $patterns Array of patterns (exact IPs or CIDR notation)
     * @return bool True if the IP matches any pattern
     */
    public static function ipMatchesAnyPattern(string $ipAddress, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (self::ipMatchesPattern($ipAddress, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check access rights for a specific group
     */
    public static function checkGroupRights(int $groupId, int $type, int $objectId, int $buttonId): int
    {
        $accessType = self::ACCESS_NONE;
        if ($groupId <= 0) {
            return $accessType;
        }

        $cacheKey = 'CMA_access_group_' . $groupId . '_' . $type;

        // Check if we have an indexed version already
        if (!isset(self::$groupRightsIndex[$cacheKey])) {
            $sql = 'SELECT tblGroupRights.secObjectID, tblGroupRights.secAccessType, tblGroupRights.secButton1, tblGroupRights.secButton2, tblGroupRights.secButton3, tblGroupRights.secButton4, tblGroupRights.secButton5  FROM tblGroups INNER JOIN tblGroupRights ON tblGroups.ID = tblGroupRights.fkGroup GROUP BY tblGroups.ID, tblGroupRights.secObjectType, tblGroupRights.secObjectID, tblGroupRights.secAccessType, tblGroupRights.secButton1, tblGroupRights.secButton2, tblGroupRights.secButton3, tblGroupRights.secButton4, tblGroupRights.secButton5 HAVING ( (tblGroups.ID=' . $groupId . ') AND (tblGroupRights.secObjectType=' . $type . ') AND (tblGroupRights.secAccessType>0))';
            $arrRights = Cache::retrieve($cacheKey, 'users', $sql);

            // Build indexed lookup for O(1) access
            self::$groupRightsIndex[$cacheKey] = [];
            if (Arr::isArray($arrRights)) {
                for ($t = 0; $t <= (count($arrRights) - 1); $t++) {
                    $objId = $arrRights[self::ARR_OBJECTID][$t];
                    self::$groupRightsIndex[$cacheKey][$objId] = [
                        'accessType' => $arrRights[self::ARR_ACCESSTYPE][$t],
                        'button1' => $arrRights[self::ARR_ACCESSTYPE + 1][$t] ?? 0,
                        'button2' => $arrRights[self::ARR_ACCESSTYPE + 2][$t] ?? 0,
                        'button3' => $arrRights[self::ARR_ACCESSTYPE + 3][$t] ?? 0,
                        'button4' => $arrRights[self::ARR_ACCESSTYPE + 4][$t] ?? 0,
                        'button5' => $arrRights[self::ARR_ACCESSTYPE + 5][$t] ?? 0,
                    ];
                }
            }
        }

        // O(1) lookup by objectID
        if (isset(self::$groupRightsIndex[$cacheKey][$objectId])) {
            $rights = self::$groupRightsIndex[$cacheKey][$objectId];
            $accessType = $rights['accessType'];
            if ($buttonId > 0 && $buttonId <= 5) {
                $accessType = ($rights['button' . $buttonId] != 0 ? self::ACCESS_FULL : self::ACCESS_NONE);
            }
        }

        return $accessType;
    }
}
