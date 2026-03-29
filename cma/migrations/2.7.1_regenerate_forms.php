<?php
/**
 * Regenerate All Forms - Migration Script
 *
 * Forces regeneration of all form definitions from database to JSON.
 * Simply calls tools_export_forms.php with force=1 parameter.
 */

// Internal routing shim: Set force flag before including the export script
$_GET['force'] = '1';

// Include and run the export script
require __DIR__ . '/2.7.2_export_forms.php';
