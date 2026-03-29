<?php
/**
 * Migration 7.0.0: Normalize databases.json
 *
 * Changes:
 * - Adds 'type' field to each database entry (access, sqlserver, mysql, etc.)
 * - Renames 'rep' to 'DEPRECATED_rep' (read-only repository no longer needed)
 */

use App\Library\Application;

require_once dirname(__DIR__, 2) . '/_bootstrap.php';

function runMigration(): array {
    $result = [
        'success' => false,
        'message' => '',
        'changes' => []
    ];

    $databasesFile = dirname(__DIR__, 2) . '/data/databases.json';

    if (!file_exists($databasesFile)) {
        $result['message'] = 'databases.json niet gevonden';
        return $result;
    }

    $content = file_get_contents($databasesFile);
    $config = json_decode($content, true);

    if (!$config || !isset($config['databases'])) {
        $result['message'] = 'Ongeldig databases.json formaat';
        return $result;
    }

    $databases = $config['databases'];
    $newDatabases = [];

    // Check if DEPRECATED_rep already exists (idempotency check)
    $hasDeprecatedRep = false;
    foreach ($databases as $db) {
        $name = $db['name'] ?? '';
        if ($name === 'DEPRECATED_rep') {
            $hasDeprecatedRep = true;
        }
    }

    foreach ($databases as $db) {
        $name = $db['name'] ?? '';
        $connString = $db['connectionString'] ?? '';

        // Detect type from connection string
        $type = $db['type'] ?? 'unknown';
        if ($type === 'unknown') {
            if (preg_match('/Microsoft\.Jet\.OLEDB|Microsoft\.ACE\.OLEDB/i', $connString)) {
                $type = 'access';
            } elseif (preg_match('/MySql|MariaDB/i', $connString)) {
                $type = 'mysql';
            } elseif (preg_match('/Npgsql|PostgreSQL/i', $connString)) {
                $type = 'postgresql';
            } elseif (preg_match('/SqlClient|SQL Server|SQLNCLI/i', $connString)) {
                $type = 'sqlserver';
            }
        }

        // Handle specific databases
        if ($name === 'rep' && !$hasDeprecatedRep) {
            // Mark repository as deprecated (only if not already done)
            $newDatabases[] = [
                'id' => $db['id'] ?? null,
                'name' => 'DEPRECATED_rep',
                'type' => $type,
                'connectionString' => $connString,
                'description' => 'DEPRECATED: Repository database (was read-only, no longer used)',
                'deprecated' => true,
                'deprecatedReason' => 'Formulierdefinities zijn gemigreerd naar JSON bestanden'
            ];
            $result['changes'][] = "rep → DEPRECATED_rep (type: $type)";
        } elseif ($name === 'rep' && $hasDeprecatedRep) {
            // Skip 'rep' if DEPRECATED_rep already exists
            $result['changes'][] = "Skipped rep (DEPRECATED_rep already exists)";
        } else {
            // Keep other databases, just add type
            $db['type'] = $type;
            $newDatabases[] = $db;
            $result['changes'][] = "$name: added type '$type'";
        }
    }

    // Update config
    $config['databases'] = $newDatabases;
    $config['version'] = '2.0.0';
    $config['lastUpdated'] = date('Y-m-d H:i:s');

    // Write back
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (file_put_contents($databasesFile, $json) === false) {
        $result['message'] = 'Kon databases.json niet schrijven';
        return $result;
    }

    $result['success'] = true;
    $result['message'] = 'databases.json succesvol bijgewerkt met ' . count($result['changes']) . ' wijzigingen';

    return $result;
}

// Run if called directly
if (php_sapi_name() === 'cli' || !defined('MIGRATION_CONTEXT')) {
    $result = runMigration();
    if (php_sapi_name() === 'cli') {
        echo $result['success'] ? "OK: " : "FOUT: ";
        echo $result['message'] . "\n";
        foreach ($result['changes'] as $change) {
            echo "  - $change\n";
        }
    }
}
