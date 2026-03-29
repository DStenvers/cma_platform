<?php
/**
 * Migration 9.7.0: Set openInNewWindow for extra buttons containing [domein]
 *
 * Extra buttons with [domein] in the URL point to the front-end site
 * and should always open in a new tab.
 */

echo "=== Migration: [domein] buttons open in new tab ===\n\n";

$formsDir = dirname(__DIR__, 2) . '/assets/forms';
$updated = 0;
$skipped = 0;

foreach (glob($formsDir . '/*.json') as $file) {
    $content = file_get_contents($file);
    $data = json_decode($content, true);
    if (!is_array($data) || empty($data['extraButtons'])) {
        continue;
    }

    $changed = false;
    foreach ($data['extraButtons'] as &$btn) {
        $url = $btn['url'] ?? '';
        if (stripos($url, '[domein]') !== false && empty($btn['openInNewWindow'])) {
            $btn['openInNewWindow'] = true;
            $changed = true;
        }
    }
    unset($btn);

    if ($changed) {
        // Preserve original indentation
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (str_contains($content, "\t")) {
            $json = preg_replace_callback('/^( +)/m', function ($m) {
                return str_repeat("\t", (int)(strlen($m[1]) / 4));
            }, $json);
        }
        file_put_contents($file, $json);
        echo "  ✓ " . basename($file) . "\n";
        $updated++;
    } else {
        $skipped++;
    }
}

echo "\n$updated formulieren bijgewerkt, $skipped overgeslagen.\n";
return true;
