<?php
/**
 * Global Constants for ASP to PHP Conversion
 *
 * This file contains all constants extracted from:
 * - library/library.inc
 * - library/cma_fldtypes.inc
 *
 * These constants are loaded globally in _bootstrap.php
 */

// ============================================================
// File System Constants (from library.inc)
// ============================================================

define('XSLT_PATH', 'xslt/');
define('CACHE_PATH', 'cache/');
define('CACHE_DIR_INDENT', 'dirs');

// ============================================================
// File I/O Constants (from library.inc)
// ============================================================

define('constFileForReading', 1);
define('constFileForWriting', 2);
define('constFileAttrReadOnly', 1);

// ============================================================
// ADO Constants (from library.inc)
// ============================================================

// ADO Constants that are still referenced in converted code
// Note: These are kept for backward compatibility with converted ASP code
// In PHP/PDO, most of these are not needed, but they're defined to prevent errors
define('adOpenForwardOnly', 0);  // Used as bootstrap check marker
define('adOpenReadOnly', 3);
define('adLockReadOnly', 1);
define('adLockOptimistic', 3);

// ============================================================
// CMA Field Type Constants (from cma_fldtypes.inc)
// ============================================================

define('constFldType_Combobox', 2);
define('constFldType_Textbox', 3);
define('constFldType_Checkbox', 5);
define('constFldType_Memo', 6);
define('constFldType_CheckList', 8);
define('constFldType_Image', 9);
define('constFldType_URL', 10);
define('constFldType_File', 11);
define('constFldType_Label', 12);
define('constFldType_SortList', 13);
define('constFldType_Directory', 14);
define('constFldType_GroupSeparator', 15);
define('constFldType_UserList', 16);
define('constFldType_EMail', 17);
define('constFldType_XMLStore', 18);
define('constFldType_HTMLStrip', 19);
define('constFldType_Thumbnail', 20);
define('constFldType_Time', 21);
define('constFldType_Password', 22);

define('constFldTypeCount', 18);

// ============================================================
// Table/Report Constants (from library/lib_table.inc)
// ============================================================

define('CONST_STRSORTPARAM', 'Sort');
