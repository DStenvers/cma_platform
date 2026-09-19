# CMA Completed Fixes

Items moved from todo.md after being confirmed working.

---

## Session Fixes - Confirmed Working

| Fix | Date | Notes |
|-----|------|-------|
| Table headers showing underscores | 2025-12-20 | Replace underscores with spaces in clicker text (library.js line 1803) |
| groupbox-end row not hidden when collapsed | 2025-12-20 | Now hides rows with data-group-row attribute (cma.js, cma-groupbox.js) |
| Tree item clicks not working | 2025-12-20 | Fixed bindTreeEvents ID extraction (raw href, data-id) + added target="R" to simple trees |
| toggleMenuGroup is not defined | 2025-12-20 | Removed duplicate script loads from main.php, bootstrap.inc |
| no-data message vertical centering | 2025-12-20 | Removed `display: block` from #noDataMessage in form.css |
| Toolbar filter placeholder | 2025-12-20 | Empty option shows placeholder "Selecteer een [caption]" in FormTemplate.php |

---

## Bug Fixes

### CKEditor fields not updated on save
**Fixed**: 2025-12-19
**Location**: `assets/js/form-controller.js` - `collectFormData()` method

Fixed by calling `CKEDITOR.instances[name].updateElement()` for each CKEditor instance before collecting FormData.

---

### Special Characters (UTF-8/Windows-1252) Cause Empty Tree Items
**Fixed**: 2025-12-19
**Location**: `ListService.php`, `RecordService.php`

**Problem**: Non-ASCII characters (like `é` in "Renée") caused issues in the tree view and form loading.

**Fix Applied**:
Added `Str::toUtf8()` conversion immediately after fetching rows from database in:
1. `ListService::getJsonFormTreeHtml()` - Line 1559-1560
2. `RecordService::getRecord()` - Line 142-143
3. `RecordService::getSubformData()` - Line 479-480

**Cypress Test**: `cypress/e2e/api/utf8-encoding.cy.js`

---

### "Geen gegevens" message conditional on Toevoegen button
**Fixed**: 2025-12-19
**Location**: `classes/Services/ListService.php`

The message now checks `$formDef->hasMenuNew()` and user rights before showing "klik op 'Toevoegen'" text.

---

### Subforms not loading (empty tabs)
**Fixed**: 2025-12-19
**Location**: `assets/js/form-controller.js` - `loadSubforms()` method

Race condition where `loadSubforms()` was called before the `cma-tabs` web component had finished initializing.

**Fix**: Added wait logic using `customElements.whenDefined` and double `requestAnimationFrame`.

---

### Folds in tools.php don't work
**Fixed**: 2025-12-19
**Location**: Cypress tests

The fold functionality was working but Cypress tests were failing. Fixed by:
1. Updated `cma-fold.cy.js` tests with `waitForCmaFold()` helper
2. Added proper `beforeEach()` blocks
3. Updated `tools-tree.cy.js` with correct shadow DOM selectors

**Result**: All 38 cma-fold tests and 12 tools-tree tests pass.

---

### file-clear-btn text incorrect
**Fixed**: 2025-12-19
**Location**: `FormRenderer.php:589`, `form-controller.js:2991-3003`

Changed title from "Bestand verwijderen" to "Invoer leegmaken" and removed confirmation dialog.

---

### Extra buttons error "Selecteer eerst een record"
**Fixed**: 2025-12-19
**Location**: `form-controller.js:handleExtraButtonClick()`

Modified to get record ID from form's `data-record-id` attribute or `cmaGetRecordId()` function.

---

### Combo limit increased
**Fixed**: 2025-12-19
**Location**: `OptionsService.php:129`

Increased combo dropdown limit from 100 to 500 items for large datasets.

---

### security_groups not rendering
**Fixed**: 2025-12-19
**Location**: `ListService.php:1956,2416,2561`

Added `'custom'` to `$skipTypes` arrays to exclude custom renderer fields from SQL column lists.

**Cypress test**: `cypress/e2e/forms/security-forms.cy.js`

---

### pctchecklist web control
**Status**: Already implemented
**Location**: `FormRenderer.php`, `OptionsService.php`, `RecordService.php`

Implementation follows the original ASP pattern using `chklst_{controlId}`, `chklstinfo_{controlId}`, and `chklstall_{controlId}` form fields.

---

### Filter field skipped in default table columns
**Fixed**: 2025-12-20
**Location**: `ListService.php` - 5 locations

When a form has a required filter field, that field is now automatically excluded from the default table column list.

**Cypress test**: `cypress/e2e/forms/table-view.cy.js` - "Filter Field Skip" describe block

---

## Completed Enhancements

### Fold visibility in direct record mode
**Completed**: 2025-12-19

The fold divider is now hidden in direct record mode via CSS. See `assets/css/form.css:57-58` - `body.mode-detail #fold { display: none !important; }`.

---

### Windows-1252 encoding for combobox options
**Completed**: 2025-12-19

UTF-8 sanitization is now applied to combo and checklist option loading in `OptionsService.php` using `Str::toUtf8()`.

---

### Database Connection Pooling
**Status**: Working as designed (not a bug)

The connection pool shows `"in_pool": false, "hits": 0` on every HTTP request. This is **expected behavior** because PHP doesn't persist state between HTTP requests.

The pool works within a single request - if a request calls `Database::getConnection('data')` multiple times, the second call reuses the pooled connection.

---

## FormID Deprecation - Code Cleanup

**Completed**: 2025-12-19

The CMA form system has been simplified to use only JSON form definitions (`form=` parameter). The old numeric FormID system (`FormID=` parameter) has been completely removed.

**Cleanup Completed**:
- `form.php` - Entry point now only accepts `form=` parameter
- `form_api.php` - All `$useJsonForm` conditionals removed
- `FormTemplate.php` - Removed database form methods, renamed `formId` to `sourceFormId`
- `form-controller.js` - Updated to use `jsonForm=` for API calls

**Migration Reference**:
- Git commit before deprecation: `004b3de`

---

## Performance System

### Performance Logging System
**Implemented**: 2025-12-18
**Location**:
- `classes/Services/PerformanceLogger.php` - PHP server-side logger
- `assets/js/perf-logger.js` - JavaScript client-side logger
- `api/log.php` - API endpoint for JS to submit logs

**Log files**: `cache/perf_logs/perf_YYYY-MM-DD.log` (JSON lines format)

**Analysis endpoints**:
- `api/log.php?action=summary&date=YYYY-MM-DD` - Get summary statistics
- `api/log.php?action=read&date=YYYY-MM-DD&type=query` - Read specific log type

---

### Treeview shows wrong field as detail
**Completed**: 2025-12-20

**Problem**: Many forms showed the group1 field instead of the correct detailField in the treeview.

**Root causes**:
1. `buildJsonColumnList()` didn't include `detailField` and `groupFields` in SQL SELECT
2. PHP array key lookups are case-sensitive, so `"detailField": "Item"` didn't match SQL alias `as item`

**Fix** (ListService.php):
1. Modified `buildJsonColumnList()` to accept and include `$detailField` and `$groupFields` parameters
2. Added case-insensitive field lookup using a `$fieldMap` built from first row's column names
3. Applied case-insensitive lookups to `displayField`, `detailField`, `groupFields`, and `idField`

---

### LocalStorage prefix reset to v2
**Completed**: 2025-12-20

**Problem**: User wanted to reset localStorage to test default filter behavior after changes.

**Fix**: Changed localStorage prefix from `cma_` to `cma_v2_` across all files:
- `table-preferences.js`: `cma_v2_table_prefs_`
- `main.js`: `cma_v2_menu_state`, `cma_v2_menu_collapsed`
- `form-controller.js`: `cma_v2_use_web_component_table`, `cma_v2_table_prefs_`
- `error-handler.js`: `cma_v2_js_errors`

---

### Error panel Clear button removes panel
**Completed**: 2025-12-20

**Problem**: Pressing Clear on the JS error panel only cleared errors but kept the panel visible.

**Fix** (error-handler.js:499-507):
Modified `clear()` function to remove panel from DOM after clearing:
```javascript
clear: function() {
    panelErrors.length = 0;
    clearStoredErrors();
    if (errorPanel && errorPanel.parentNode) {
        errorPanel.parentNode.removeChild(errorPanel);
    }
    errorPanel = null; // Allow recreation if new errors occur
},
```

---

### Report Designer - SQL sync not updating relations
**Completed**: 2026-02-06

**Problem**: After changing SQL with JOINs in the report designer, the table display (relations) were not updated. The parsed joins were stored in `state.parsedJoins` but never pushed to the schema canvas.

**Root Cause**: The `syncSqlToVisualEditor()` function stored parsed joins but didn't call any method to update the schema canvas relationships. The canvas was only reading relationships from the database schema via `_updateRelationshipsFromSelectedTables()`.

**Fix**:
1. Added `setRelationshipsFromJoins(joins)` method to `cma-schema-canvas.js` that:
   - Parses ON conditions to extract from/to table.column pairs
   - Converts join type (INNER/LEFT) to internal relationship format
   - Updates `_relationships` array and re-renders lines
2. Called this method from `report-designer.php` after parsing SQL and adding tables

---

### Schema Canvas - Relationship lines positioning for overlapping tables
**Completed**: 2026-02-06

**Problem**: When two tables had similar horizontal positions (overlapping X coordinates), the relationship line would awkwardly start from one side and end on the opposite side of the tables.

**Fix** (cma-schema-canvas.js):
Modified `_updateRelationshipLines()` to detect horizontal overlap and connect both endpoints from the left side when tables overlap:
- Tables that overlap horizontally: both connect from left side (curves left)
- Tables where one is clearly to the left: from=right, to=left
- Tables where one is clearly to the right: from=left, to=right
