<?php
/**
 * OPcache Reset Utility
 *
 * Access this file via browser to reset OPcache and reload all PHP files.
 * This is needed when helper class files are updated but PHP is caching
 * the old version.
 */

// No dependencies - standalone file
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>OPcache Reset</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        button { padding: 10px 20px; font-size: var(--font-size-lg); cursor: pointer; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>OPcache Reset Utility</h1>

    <?php if (isset($_POST['reset']) || isset($_GET['clearforms'])): ?>
        <h2>Reset Results:</h2>
        <?php
        // Clear forms cache (file-based)
        $formsDir = __DIR__ . '/../cache/cma/forms';
        $filesCleared = 0;
        if (is_dir($formsDir)) {
            foreach (glob($formsDir . '/*') as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $filesCleared++;
                }
            }
        }
        echo "<p class='success'>Forms cache cleared ({$filesCleared} files removed)</p>";

        // Clear APCu forms cache group
        if (function_exists('apcu_cache_info') && function_exists('apcu_delete')) {
            $info = @apcu_cache_info();
            $keysDeleted = 0;
            if ($info && isset($info['cache_list'])) {
                foreach ($info['cache_list'] as $entry) {
                    $key = $entry['info'] ?? $entry['key'] ?? '';
                    if (strpos($key, 'CMA_form_template_') === 0 || strpos($key, 'forms_') === 0) {
                        apcu_delete($key);
                        $keysDeleted++;
                    }
                }
            }
            echo "<p class='success'>APCu forms cache cleared ({$keysDeleted} keys deleted)</p>";
        }

        if (isset($_POST['reset'])) {
            if (function_exists('opcache_reset')) {
                $result = opcache_reset();
                if ($result) {
                    echo '<p class="success">OPcache successfully reset!</p>';
                } else {
                    echo '<p class="error">OPcache reset returned false (may already be empty)</p>';
                }
            } else {
                echo '<p class="error">OPcache is not available on this server.</p>';
            }

            // Also try APCu if available
            if (function_exists('apcu_clear_cache')) {
                apcu_clear_cache();
                echo '<p class="success">APCu cache fully cleared!</p>';
            }
        }
        ?>
        <p><a href="opcache_reset.php">Back to status</a></p>
    <?php else: ?>
        <h2>OPcache Status:</h2>
        <?php if (function_exists('opcache_get_status')): ?>
            <?php $status = opcache_get_status(false); ?>
            <?php if ($status): ?>
                <ul>
                    <li>Enabled: <?= $status['opcache_enabled'] ? 'Yes' : 'No' ?></li>
                    <li>Cached scripts: <?= $status['opcache_statistics']['num_cached_scripts'] ?? 'N/A' ?></li>
                    <li>Cache hits: <?= $status['opcache_statistics']['hits'] ?? 'N/A' ?></li>
                    <li>Memory used: <?= round(($status['memory_usage']['used_memory'] ?? 0) / 1024 / 1024, 2) ?> MB</li>
                </ul>
            <?php else: ?>
                <lib-message type="info">OPcache is not enabled or no status available.</lib-message>
            <?php endif; ?>
        <?php else: ?>
            <lib-message type="error">OPcache functions not available.</lib-message>
        <?php endif; ?>

        <h2>Clear Forms Cache:</h2>
        <p><a href="opcache_reset.php?clearforms=1" style="padding: 10px 20px; background: #204496; color: white; text-decoration: none; border-radius: 4px;">Clear Forms Cache</a></p>
        <p class="info">Use this after updating form definitions or FormRenderer code.</p>

        <h2>Reset OPcache:</h2>
        <form method="post">
            <button type="submit" name="reset" value="1" class="btn btn-primary">Reset All Cache (OPcache + APCu + Forms)</button>
        </form>

        <h2>Database.php Check:</h2>
        <?php
        $dbFile = __DIR__ . '/app/library/Database.php';
        if (file_exists($dbFile)) {
            $content = file_get_contents($dbFile);
            $hasFetchOne = strpos($content, 'function fetchOne') !== false;
            $hasFetchAll = strpos($content, 'function fetchAll') !== false;

            echo '<ul>';
            echo '<li>File exists: <span class="success">Yes</span></li>';
            echo '<li>fetchOne() method: ' . ($hasFetchOne ? '<span class="success">Found</span>' : '<span class="error">Missing</span>') . '</li>';
            echo '<li>fetchAll() method: ' . ($hasFetchAll ? '<span class="success">Found</span>' : '<span class="error">Missing</span>') . '</li>';
            echo '<li>File modified: ' . date('Y-m-d H:i:s', filemtime($dbFile)) . '</li>';
            echo '</ul>';
        } else {
            echo '<lib-message type="error">Database.php not found at: ' . $dbFile . '</lib-message>';
        }
        ?>
    <?php endif; ?>
</body>
</html>
