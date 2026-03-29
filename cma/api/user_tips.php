<?php
/**
 * User Tips API
 *
 * Manages the skip list for tips and tours per user.
 * Stores dismissed tips in the SkipTips memo field in tblUsers.
 */

use App\Library\Arr;
use App\Library\Cookie;
use App\Library\Database;
use App\Library\Request;
use App\Library\Response;
use Cma\SecurityHelper;

require_once __DIR__ . '/../bootstrap.inc';

// Set JSON content type
Response::setContentType('application/json');

// Get current user ID
$userId = Cookie::get(SecurityHelper::COOKIE_USERID, '');
if (empty($userId)) {
    Response::json(['error' => 'Not authenticated'], 401);
    exit;
}

// Get database connection
$conn = Database::getConnection('users');
if (!$conn) {
    Response::json(['error' => 'Database connection failed'], 500);
    exit;
}

// Ensure SkipTips column exists (auto-create if missing)
ensureSkipTipsColumn($conn);

// Read JSON body once (php://input can only be read once)
$jsonInput = null;
if (Request::server('REQUEST_METHOD') === 'POST') {
    $jsonInput = json_decode(file_get_contents('php://input'), true);
}

// Get action from query string, form post, or JSON body
$action = Request::query('action', '');
if (empty($action)) {
    $action = Request::post('action', '');
}
if (empty($action) && $jsonInput && isset($jsonInput['action'])) {
    $action = $jsonInput['action'];
}

switch ($action) {
    case 'get_skip_list':
        getSkipList($conn, $userId);
        break;

    case 'dismiss':
        dismissTip($conn, $userId, $jsonInput);
        break;

    case 'reset':
        resetTips($conn, $userId, $jsonInput);
        break;

    default:
        Response::json(['error' => 'Invalid action'], 400);
        exit;
}

/**
 * Get the skip list for the current user
 */
function getSkipList($conn, $userId) {
    $row = Database::fetchOne("SELECT SkipTips FROM tblUsers WHERE ID = ?", [$userId], $conn);

    if (!$row) {
        Response::json(['skipList' => []]);
        return;
    }

    $skipTips = $row['SkipTips'] ?? '';
    $skipList = parseSkipList($skipTips);

    Response::json(['skipList' => $skipList]);
}

/**
 * Dismiss a tip/tour permanently
 */
function dismissTip($conn, $userId, $input) {
    $tipId = $input['id'] ?? '';

    if (empty($tipId)) {
        Response::json(['error' => 'Missing tip ID'], 400);
        exit;
    }

    // Get current skip list
    $row = Database::fetchOne("SELECT SkipTips FROM tblUsers WHERE ID = ?", [$userId], $conn);
    $skipTips = $row['SkipTips'] ?? '';
    $skipList = parseSkipList($skipTips);

    // Add tip ID if not already present
    if (!in_array($tipId, $skipList)) {
        $skipList[] = $tipId;
    }

    // Save updated list
    $newValue = json_encode($skipList);
    $result = Database::query("UPDATE tblUsers SET SkipTips = ? WHERE ID = ?", [$newValue, $userId], $conn);

    if ($result) {
        Response::json(['success' => true, 'skipList' => $skipList]);
    } else {
        Response::json(['error' => 'Failed to save'], 500);
    }
    exit;
}

/**
 * Reset tips (remove from skip list)
 */
function resetTips($conn, $userId, $input) {
    $tipId = $input['id'] ?? null;

    if ($tipId === null) {
        // Reset all tips
        Database::query("UPDATE tblUsers SET SkipTips = '' WHERE ID = ?", [$userId], $conn);
        Response::json(['success' => true, 'skipList' => []]);
        return;
    }

    // Remove specific tip
    $row = Database::fetchOne("SELECT SkipTips FROM tblUsers WHERE ID = ?", [$userId], $conn);
    $skipTips = $row['SkipTips'] ?? '';
    $skipList = parseSkipList($skipTips);

    $skipList = array_values(array_filter($skipList, fn($id) => $id !== $tipId));

    $newValue = json_encode($skipList);
    Database::query("UPDATE tblUsers SET SkipTips = ? WHERE ID = ?", [$newValue, $userId], $conn);

    Response::json(['success' => true, 'skipList' => $skipList]);
}

/**
 * Ensure the SkipTips column exists in tblUsers.
 * Auto-creates the column if it is missing (migration 8.2.0).
 */
function ensureSkipTipsColumn($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        // Check if column exists by querying PRAGMA
        $stmt = $conn->query("PRAGMA table_info(tblUsers)");
        $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $hasColumn = false;
        foreach ($columns as $col) {
            if (strcasecmp($col['name'], 'SkipTips') === 0) {
                $hasColumn = true;
                break;
            }
        }

        if (!$hasColumn) {
            $conn->exec("ALTER TABLE tblUsers ADD COLUMN SkipTips TEXT DEFAULT ''");
        }
    } catch (\PDOException $e) {
        // Column may already exist or other issue - log but don't fail
        error_log('ensureSkipTipsColumn: ' . $e->getMessage());
    }
}

/**
 * Parse skip list from database value
 */
function parseSkipList($skipTips) {
    if (empty($skipTips)) {
        return [];
    }

    $skipList = json_decode($skipTips, true);
    if (!Arr::isArray($skipList)) {
        // Handle legacy comma-separated format
        $skipList = array_filter(array_map('trim', explode(',', $skipTips)));
    }

    return $skipList;
}
