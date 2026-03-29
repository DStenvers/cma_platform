<?php
/**
 * Migration 0.0.1: Create databases.json
 *
 * Generates config/databases.json from Application globals so that
 * subsequent migrations (1.0.0+) can find database paths for backups.
 */

use App\Library\Application;

// Fix path when running as migration
$basePath = defined('MIGRATION_RUNNING') ? dirname(__DIR__) : __DIR__;
if (strpos($basePath, 'migrations') !== false) {
    $basePath = dirname($basePath);
}
require_once $basePath . '/bootstrap.inc';

$configDir = $basePath . '/config';
$jsonPath = $configDir . '/databases.json';

// If databases.json already exists and has content, skip
if (file_exists($jsonPath)) {
    $existing = json_decode(file_get_contents($jsonPath), true);
    if (!empty($existing['databases'])) {
        echo "✓ databases.json bestaat al met " . count($existing['databases']) . " database(s)\n";
        if (defined('MIGRATION_RUNNING')) return true;
        exit(0);
    }
}

// Build database configs from Application globals
$databases = [];

// Helper: extract path from ODBC connection string
function extractPathFromConnString(string $connStr): string
{
    // Try DBQ= (ODBC format)
    if (preg_match('/DBQ=([^;]+)/i', $connStr, $m)) {
        return trim($m[1]);
    }
    // Try Data Source=[path] (OLE DB format)
    if (preg_match('/Data Source=\[([^\]]+)\]/', $connStr, $m)) {
        return trim($m[1]);
    }
    // Try Data Source=path (OLE DB format without brackets)
    if (preg_match('/Data Source=([^;]+)/', $connStr, $m)) {
        return trim($m[1]);
    }
    return '';
}

// Helper: make path relative to site root
function makeRelativePath(string $absPath, string $siteRoot): string
{
    $siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/') . '/';
    $absPath = str_replace('\\', '/', $absPath);
    if (stripos($absPath, $siteRoot) === 0) {
        return substr($absPath, strlen($siteRoot));
    }
    return $absPath;
}

$siteRoot = dirname($basePath);

// Data database
$dataConn = Application::get('conn_data_str', Application::get('data_conn', ''));
$dataPath = extractPathFromConnString($dataConn);
if ($dataPath) {
    $dataPath = makeRelativePath($dataPath, $siteRoot);
}
$databases[] = [
    'id' => 6,
    'name' => 'data',
    'path' => $dataPath ?: 'db/main.mdb',
    'connectionString' => '',
    'description' => 'Main data database',
    'type' => 'access'
];

// Repository database
$repConn = Application::get('conn_rep', '');
$repPath = extractPathFromConnString($repConn);
$repConnStr = '';
if ($repPath) {
    $relPath = makeRelativePath($repPath, $siteRoot);
    $repConnStr = "Provider=Microsoft.Jet.OLEDB.4.0;Locale Identifier=1043;Data Source=[$relPath]";
}
$databases[] = [
    'id' => 4,
    'name' => 'rep',
    'connectionString' => $repConnStr ?: "Provider=Microsoft.Jet.OLEDB.4.0;Locale Identifier=1043;Data Source=[db/repository.mdb]",
    'description' => 'Repository database (form definitions)',
    'type' => 'access'
];

// Users database
$usersConn = Application::get('conn_users', '');
$usersPath = extractPathFromConnString($usersConn);
$usersConnStr = '';
if ($usersPath) {
    $relPath = makeRelativePath($usersPath, $siteRoot);
    $usersConnStr = "Provider=Microsoft.Jet.OLEDB.4.0;Locale Identifier=1043;Data Source=[$relPath]";
}
$databases[] = [
    'id' => 5,
    'name' => 'users',
    'type' => 'access',
    'connectionString' => $usersConnStr ?: "Provider=Microsoft.Jet.OLEDB.4.0;Locale Identifier=1043;Data Source=[db/CMAusers.mdb]",
    'description' => 'CMA users database (MS Access)'
];

// Write databases.json
$config = [
    '$schema' => './schema/databases.schema.json',
    'version' => '2.0.0',
    'description' => 'Database connection mappings',
    'databases' => $databases,
    'lastUpdated' => date('Y-m-d')
];

$json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    echo "✗ JSON encoding mislukt: " . json_last_error_msg() . "\n";
    if (defined('MIGRATION_RUNNING')) return false;
    exit(1);
}

if (file_put_contents($jsonPath, $json) === false) {
    echo "✗ Kan databases.json niet schrijven: $jsonPath\n";
    if (defined('MIGRATION_RUNNING')) return false;
    exit(1);
}

echo "✓ databases.json aangemaakt met " . count($databases) . " database(s)\n";
foreach ($databases as $db) {
    $pathInfo = $db['path'] ?? '';
    if (!$pathInfo && !empty($db['connectionString'])) {
        $pathInfo = '(via connectionString)';
    }
    echo "  - {$db['name']}: $pathInfo\n";
}

if (defined('MIGRATION_RUNNING')) return true;
exit(0);
