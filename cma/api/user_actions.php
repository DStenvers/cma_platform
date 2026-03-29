<?php
/**
 * User Actions API
 *
 * Provides admin actions for user management:
 * - reset_password: Generate a temporary password
 * - login_as: Login as another user (with audit trail)
 */

use App\Library\Cookie;
use App\Library\Database;
use App\Library\Response;
use Cma\SecurityHelper;
use Cma\Services\Logger;

require_once __DIR__ . '/../bootstrap.inc';

Response::setContentType('application/json');

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$targetUserId = $input['userId'] ?? '';

// Get current user info
$currentUserId = Cookie::get(SecurityHelper::COOKIE_USERID, '');
$currentUserLevel = SecurityHelper::getUserLevel();

if (empty($currentUserId)) {
    Response::json(['error' => 'Niet ingelogd'], 401);
    exit;
}

// return_to_self is special - verify the original user was actually an admin
if ($action === 'return_to_self') {
    $originalUserId = Cookie::get('CMAU_ORIGINAL', '');
    if (empty($originalUserId)) {
        Response::json(['error' => 'Je bent niet ingelogd als een andere gebruiker.']);
        exit;
    }
    // SECURITY: Verify the original user is actually an admin/developer
    // This prevents cookie manipulation attacks
    $conn = Database::getConnection('users');
    $originalUser = Database::fetchOne(
        "SELECT userLevel FROM tblUsers WHERE ID = ?",
        [$originalUserId],
        $conn
    );
    if (!$originalUser || (int)$originalUser['userLevel'] < SecurityHelper::LEVEL_ADMIN) {
        // Someone tried to manipulate the cookie - clear it and deny access
        Cookie::set('CMAU_ORIGINAL', '', -1);
        Response::json(['error' => 'Ongeldige sessie'], 403);
        exit;
    }
} else {
    // Only admins and developers can use these actions
    if ($currentUserLevel < SecurityHelper::LEVEL_ADMIN) {
        Response::json(['error' => 'Geen toegang'], 403);
        exit;
    }
}

// return_to_self doesn't need a target user
if (empty($targetUserId) && $action !== 'return_to_self') {
    Response::json(['error' => 'Geen gebruiker opgegeven'], 400);
    exit;
}

// Get database connection
$conn = Database::getConnection('users');
if (!$conn) {
    Response::json(['error' => 'Database connectie mislukt'], 500);
    exit;
}

// Get target user info (not needed for return_to_self)
$targetUser = null;
$targetUserLevel = 0;
if ($action !== 'return_to_self' && !empty($targetUserId)) {
    $targetUser = Database::fetchOne("SELECT ID, userLogin, userFullName, userLevel, userGUID FROM tblUsers WHERE ID = ?", [$targetUserId], $conn);
    if (!$targetUser) {
        Response::json(['error' => 'Gebruiker niet gevonden'], 404);
        exit;
    }
    $targetUserLevel = (int)($targetUser['userLevel'] ?? 0);
}

switch ($action) {
    case 'reset_password':
        resetPassword($conn, $targetUserId, $targetUser, $currentUserId, $currentUserLevel);
        break;

    case 'login_as':
        loginAsUser($conn, $targetUserId, $targetUser, $currentUserId, $currentUserLevel);
        break;

    case 'return_to_self':
        returnToSelf($conn);
        break;

    default:
        Response::json(['error' => 'Ongeldige actie'], 400);
        exit;
}

/**
 * Reset a user's password
 */
function resetPassword($conn, $targetUserId, $targetUser, $currentUserId, $currentUserLevel) {
    $targetUserLevel = (int)($targetUser['userLevel'] ?? 0);

    // Admins can only reset passwords for regular users
    // Developers can reset for anyone
    if ($currentUserLevel < SecurityHelper::LEVEL_DEVELOPER && $targetUserLevel >= SecurityHelper::LEVEL_ADMIN) {
        Response::json(['error' => 'Je kunt alleen wachtwoorden resetten van gewone gebruikers.']);
        return;
    }

    // Generate temporary password (readable format)
    $tempPassword = generateTempPassword();

    // Hash the password
    $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);

    // Update the password
    $result = Database::query(
        "UPDATE tblUsers SET userPassword = ? WHERE ID = ?",
        [$hashedPassword, $targetUserId],
        $conn
    );

    if ($result) {
        // Log the action
        Logger::info('Password reset', [
            'target_user' => $targetUser['userLogin'],
            'reset_by' => $currentUserId,
        ]);

        Response::json([
            'success' => true,
            'message' => 'Wachtwoord is gereset voor ' . htmlspecialchars($targetUser['userFullName']),
            'tempPassword' => $tempPassword,
            'note' => 'Geef dit wachtwoord door aan de gebruiker. Ze moeten het bij eerste login wijzigen.',
        ]);
    } else {
        Response::json(['error' => 'Wachtwoord resetten mislukt'], 500);
    }
}

/**
 * Login as another user
 */
function loginAsUser($conn, $targetUserId, $targetUser, $currentUserId, $currentUserLevel) {
    $targetUserLevel = (int)($targetUser['userLevel'] ?? 0);

    // Only developers can login as other admins/developers
    if ($currentUserLevel < SecurityHelper::LEVEL_DEVELOPER && $targetUserLevel >= SecurityHelper::LEVEL_ADMIN) {
        Response::json(['error' => 'Je kunt alleen inloggen als gewone gebruikers.']);
        return;
    }

    // Cannot login as yourself
    if ($targetUserId === $currentUserId) {
        Response::json(['error' => 'Je bent al ingelogd als deze gebruiker.']);
        return;
    }

    // Store the original user ID for returning later
    $originalUserId = Cookie::get('CMAU_ORIGINAL', '');
    if (empty($originalUserId)) {
        // First impersonation - store current user as original
        Cookie::set('CMAU_ORIGINAL', $currentUserId, 0); // Session cookie
    }

    // Set cookies for the target user (userID + userGUID for dual validation)
    Cookie::set(SecurityHelper::COOKIE_USERID, $targetUserId, 0);

    // Get or generate GUID for target user
    $targetGUID = $targetUser['userGUID'] ?? '';
    if (empty($targetGUID)) {
        $targetGUID = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        Database::query("UPDATE tblUsers SET userGUID = ? WHERE ID = ?", [$targetGUID, $targetUserId], $conn);
    }
    Cookie::set(SecurityHelper::COOKIE_USERGUID, $targetGUID, 0);

    // Clear cached user data so next request fetches fresh data
    SecurityHelper::clearUserCache();

    // Log the action
    Logger::info('Login as user', [
        'target_user' => $targetUser['userLogin'],
        'impersonated_by' => $currentUserId,
    ]);

    Response::json([
        'success' => true,
        'message' => 'Je bent nu ingelogd als ' . htmlspecialchars($targetUser['userFullName']),
        'note' => 'Gebruik "Terugkeren naar eigen account" in het menu om terug te keren.',
        'redirect' => '/cma/dashboard',
    ]);
}

/**
 * Return to original user after impersonation
 * Note: CMAU_ORIGINAL cookie is already validated in main code
 */
function returnToSelf($conn) {
    $originalUserId = Cookie::get('CMAU_ORIGINAL', '');

    // Get original user info
    $originalUser = Database::fetchOne("SELECT ID, userLogin, userFullName, userLevel, userGUID FROM tblUsers WHERE ID = ?", [$originalUserId], $conn);
    if (!$originalUser) {
        // Clear the invalid cookie and error
        Cookie::set('CMAU_ORIGINAL', '', -1);
        Response::json(['error' => 'Originele gebruiker niet gevonden.'], 404);
        exit;
    }

    // Restore original user cookies (userID + userGUID for dual validation)
    Cookie::set(SecurityHelper::COOKIE_USERID, $originalUserId, 0);
    Cookie::set(SecurityHelper::COOKIE_USERGUID, $originalUser['userGUID'] ?? '', 0);

    // Clear cached user data so next request fetches fresh data
    SecurityHelper::clearUserCache();

    // Clear the impersonation cookie
    Cookie::set('CMAU_ORIGINAL', '', -1);

    // Log the action
    Logger::info('Returned to own account', [
        'user' => $originalUser['userLogin'],
    ]);

    Response::json([
        'success' => true,
        'message' => 'Je bent teruggekeerd naar je eigen account.',
        'redirect' => '/cma/dashboard',
    ]);
}

/**
 * Generate a readable temporary password
 */
function generateTempPassword(): string {
    // Use words for readability
    $adjectives = ['Happy', 'Quick', 'Bright', 'Cool', 'Swift', 'Smart', 'Bold', 'Calm'];
    $nouns = ['Tiger', 'Eagle', 'River', 'Mountain', 'Star', 'Moon', 'Tree', 'Cloud'];

    $adj = $adjectives[array_rand($adjectives)];
    $noun = $nouns[array_rand($nouns)];
    $number = rand(100, 999);

    return $adj . $noun . $number;
}
