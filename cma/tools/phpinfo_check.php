<?php
/**
 * Quick PHP configuration check for debugging
 */
header('Content-Type: text/plain');

echo "=== PHP Timeout Settings ===\n\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "max_input_time: " . ini_get('max_input_time') . "\n";
echo "default_socket_timeout: " . ini_get('default_socket_timeout') . "\n";

// Check if hard_timeout exists (PHP 8.4+)
$hardTimeout = ini_get('hard_timeout');
echo "hard_timeout: " . ($hardTimeout !== false ? $hardTimeout : 'not set') . "\n";

// Check user_ini settings
echo "user_ini.filename: " . ini_get('user_ini.filename') . "\n";
echo "user_ini.cache_ttl: " . ini_get('user_ini.cache_ttl') . " seconds\n";

echo "\n=== Other Relevant Settings ===\n\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "disable_functions: " . ini_get('disable_functions') . "\n";
echo "open_basedir: " . (ini_get('open_basedir') ?: '(none)') . "\n";

echo "\n=== Loaded php.ini ===\n\n";
echo php_ini_loaded_file() . "\n";

echo "\n=== Additional ini files ===\n\n";
echo php_ini_scanned_files() ?: "(none)\n";

echo "\n=== Test: Can we change max_execution_time? ===\n\n";
$before = ini_get('max_execution_time');
set_time_limit(600);
$after = ini_get('max_execution_time');
echo "Before set_time_limit(600): $before\n";
echo "After set_time_limit(600): $after\n";
echo "Change successful: " . ($after == 600 ? "YES" : "NO") . "\n";
