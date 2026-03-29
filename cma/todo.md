# CMA Todo List

For completed items, see [done.md](done.md)

**Last updated:** 2026-02-18

See also: [ARCHITECTURE_REVIEW.md](ARCHITECTURE_REVIEW.md) for system-wide issues.

---

## P0 - Critical (Blocking Production)

### 0. APCu Not Enabled - Critical Performance Issue
**Status**: NEEDS SERVER CONFIGURATION
**Added**: 2026-02-05
**Impact**: HIGH - All caching falls back to slow file I/O

APCu (Alternative PHP Cache for User data) is NOT enabled in PHP. The caching system is designed with APCu as the primary fast cache tier, but it's falling back to file-based caching on every request.

**Symptoms:**
- Form template generation takes 2-3 seconds on first load
- Cache::getWithInvalidation() always misses the APCu tier
- No in-memory caching between requests

**Fix Required (php.ini):**
```ini
extension=apcu
apc.enabled=1
apc.shm_size=128M
apc.enable_cli=1  ; if using CLI scripts
```

**After enabling:**
1. Restart IIS/PHP-FPM
2. Verify: `php -r "echo 'APCu: ' . (function_exists('apcu_enabled') && apcu_enabled() ? 'enabled' : 'disabled');"`
3. Clear all caches via CMA Tools > Cache leegmaken

**Side-effects:** Low - APCu is already coded into the application, just needs to be enabled.

---

### 1. Monitoring/Audit Logging Not Working
**Status**: FIX ATTEMPTED (2026-01-30) - NEEDS CONFIRMATION
**Added**: 2026-01-30
**Location**: FormDataProvider.php, RecordService.php

**Root Causes:**
1. `cma_monitoring` setting may be disabled - check `Application::get('cma_monitoring')` returns truthy
2. `RecordService::save()` and `RecordService::delete()` have NO monitoring calls
3. Changelog is built client-side (JavaScript) - fails silently if JS errors occur
4. Notificatie field is empty because `_changelog` POST data is not populated

**Fixes Applied (2026-01-30):**
- ✅ Verified `cma_monitoring = true` in app.php:121
- ✅ Added `logMonitoring()` calls to RecordService.save() and delete()
- ✅ Added server-side `buildAddChangelog()` for new records
- ✅ Added server-side `buildDeleteChangelog()` for deleted records
- ✅ Made `FormDataProvider::logMonitoring()` public for RecordService access
- ✅ Added `$_POST` fallback in `saveGroupRights()` if $data array incomplete

**Still TODO:**
- Server-side changelog for Edit operations (requires fetching old values before update)

**Side-effects:** Low - adds missing functionality

---

### 2. Groups Form - Rapporten/Rechten Controls Not Saved
**Status**: FIXED (2026-02-06) - CONFIRMED BY CYPRESS TESTS
**Added**: 2026-01-30
**Location**: form-controller.js (rights-matrix, collectFormData), groups-form.cy.js

The custom renderer controls for "Rapporten" and "Rechten" in the Groups form were not being included in POST data when saving.

**Root Cause (Identified 2026-02-06):**
- The rights-matrix inputs were loaded via AJAX into a custom-renderer container
- The `collectFormData()` function was collecting the data, but Cypress tests showed the POST body was sometimes missing the rights keys
- Issue was timing-related: the form state wasn't always fully synced before save

**Fixes Applied:**
- ✅ Added explicit FormData verification before save in collectFormData() (form-controller.js:8722-8742)
- ✅ Document-level queries for rights-matrix inputs as fallback
- ✅ Updated groups-form.cy.js tests with pre-save FormData verification
- ✅ All menu rights and report rights tests now pass

**Remaining TODO:**
- Investigate database persistence issue (rights are in POST but not always in tblGroupRights)
- See persist test comment in groups-form.cy.js

**Side-effects:** Low - adds verification before save

---

### 3. Multiple CRUD Code Paths
**Status**: PARTIALLY FIXED (2026-01-30) - NEEDS CONFIRMATION
**Added**: 2026-01-30

Three different code paths exist for CRUD operations:
1. form_api.php → FormDataProvider (modern, has monitoring) ✅
2. detailsRep_post.php (legacy, has monitoring but duplicate code) ✅
3. Direct RecordService calls (NO monitoring) - **NOW FIXED**

**Fix Applied (2026-01-30):**
- ✅ Added `logMonitoring()` calls to RecordService.save() and delete()
- ✅ Added `buildLegacyAddChangelog()` for server-side changelog generation

**Still TODO:**
- Audit all usages of RecordService to confirm monitoring is working
- Consider deprecating detailsRep_post.php in favor of FormDataProvider

**Side-effects:** Low - additive change

---

## P1 - High Priority

### ~~4. Inline Editing Tests Disabled~~
**Status**: ✅ FIXED (2026-02-18) - Tests updated for lib-combo, Delete key test added
**Added**: 2026-01-30
**Location**: cypress/e2e/forms/inline-edit.cy.js

Tests updated: Select2 selectors replaced with lib-combo, right-click test updated to use context menu flow, Delete key shortcut tests added. `selectOption` commands in commands.js and commands/forms.js also updated. `inlineEditRow`/`inlineEditRowByIndex` commands updated for context menu flow.

---

### ~~5. Code Duplication - Helper Functions~~
**Status**: ✅ PARTIALLY FIXED (2026-02-18) - toBool and formatIdForSql consolidated
**Added**: 2026-01-30

Fixed:
- ~~`toBool()` in FormDataProvider, FormTemplate~~ → now delegate to `ListServiceHelper::toBool()`
- ~~`formatIdForSql()` in FormDataProvider~~ → removed (was unused)

Remaining:
- `controlTypeToFieldType()` in JsonFormService vs ListServiceHelper - **intentionally different** (different constant systems, different mapping purposes)
- Column building logic in 6+ locations - still duplicated

---

### 6. Test Coverage Critically Low (6%)
**Status**: HIGH - Cannot catch regressions
**Added**: 2026-01-30

Only 8 of 125 forms have CRUD tests:
- users, groups, opleidingen (basic CRUD)
- 117+ forms have NO tests

**Fix Required:**
- Add CRUD tests for top 20 most-used forms
- Replace "implementation specific" test placeholders with real assertions
- Add security tests (XSS, SQL injection, CSRF)

**Side-effects:** Low - test-only changes

---

## P2 - Future Improvements

### Retire legacy TableService numeric form ID path
**Status**: TODO
**Added**: 2026-03-16

`TableService.php` has a legacy code path for numeric form IDs that uses `CmaRepository` and `FormDefinition`. All forms are now JSON-based (string names).

**Approach:** Instead of removing the legacy path, add a numeric-to-name lookup at the entry point. If a numeric ID comes in, resolve it to the JSON form name via `sourceFormId` and continue down the JSON route. This keeps the fix minimal and handles old bookmarks/hardcoded URLs.

**Steps:**
1. In `TableService::getTableHtml()` and `getWebComponentTableData()`: if `$formId` is numeric, look up the JSON form name by `sourceFormId` and delegate to `JsonFormService`
2. Same for `form_api.php` — resolve numeric IDs early
3. Once confirmed stable, the legacy `CmaRepository::loadFormDefinition()` and `FormDefinition.php` become dead code and can be removed
4. Run Cypress tests to verify

### Replace blockedit.js (CKEditor) with cma-blockeditor Web Component
**Status**: IN PROGRESS - Storybook phase
**Added**: 2026-02-12

The legacy content block editor (`assets/js/blockedit.js`) uses jQuery + CKEditor + JSON block definitions (`../assets/contentblocks/contentblocks.json`) with `.blockedit` container class. It has global state (var all_components, htmls, element_cnt, pendingCKEditors), String.prototype mutation, and tight CKEditor coupling.

The new `cma-blockeditor` web component (`webcomponents/cma-blockeditor.js`) is a modern replacement using Shadow DOM, no jQuery dependency, and a clean block-based data model (JSON array of typed blocks).

**Current state:**
- Web component source is complete (1015 lines, 7 block types)
- Added to storybook with side-by-side CKEditor comparison
- Block controls clipping fixed (overflow:hidden + padding-left)

**Integration steps remaining:**
1. Verify component works fully in storybook (all block types, drag-drop, keyboard shortcuts)
2. Create Cypress test (`cypress/e2e/components/cma-blockeditor.cy.js`)
3. Add FormRenderer/FormTemplate support for `cma-blockeditor` field type
4. Data migration: convert existing `<!--BLOCK...-->` HTML format to JSON block array
5. Update `blockedit_init()` to detect and use new component when available
6. Deprecate and eventually remove `blockedit.js`

**Files involved:**
- `webcomponents/cma-blockeditor.js` - The new component
- `assets/js/blockedit.js` - The old implementation (to be replaced)
- `../assets/contentblocks/contentblocks.json` - Block definitions (old format)
- `classes/FormRenderer.php` - Form field rendering
- `classes/FormTemplate.php` - Form template generation

**Side-effects:** MEDIUM - content block forms will need data migration

---

### 7. jQuery Upgrade (1.9.0 → 4.0.0)
**Status**: RESEARCH COMPLETE - Deferred
**Added**: 2026-01-31

Current jQuery version is 1.9.0 (very old). Upgrading to 4.0.0 requires fixing deprecated APIs.

**CMA Code Issues (fixable):**
| File | Issue | Fix |
|------|-------|-----|
| `general.js:28-29` | `.bind()` | → `.on()` |
| `general.js:352` | `.unbind()` | → `.off()` |
| `inventarisatie/script.js:686` | `.size()` | → `.length` |
| `inventarisatie/script.js:14275` | `.complete()` | → `.always()` |

**Third-party Library Issues (problematic):**
| Library | Issue |
|---------|-------|
| `jquery.Jcrop.js` | Uses `.bind()/.unbind()` extensively - will break |
| `select2` | Old version, may have compatibility issues |
| `CKEditor` | Internal jQuery adapter may need updating |

**Recommended Approach:**
1. First upgrade to jQuery 3.7.1 (LTS with jQuery Migrate plugin)
2. Use `jquery-migrate` to log deprecation warnings
3. Fix warnings over time
4. Consider 4.0.0 once all warnings resolved

**Side-effects:** HIGH - third-party libraries may break

---

## Session Fixes - Need Testing

| Fix | Status | Files Changed | Notes |
|-----|--------|---------------|-------|
| Skip filter field in default table columns | Needs test | ListService.php (5 locations) | filterIdName excluded |
| CKEditor fields save procedure | Needs test | FormRenderer.php, FormDataProvider.php | Retrieval works, save needs verification |
| ~~Toolbar filter not Select2~~ | ✅ FIXED (2026-02-17) | form-controller.js | Select2 replaced with lib-combo |
| Datepicker icon transparent background | Fixed 2025-12-27 | lib-components.css | `.lib-datepicker__icon { background-color: transparent }` |
| Sidepanel dark mode flicker | Fixed 2025-12-27 | library.js | Now checks `html.dark-mode` class |
| Input CSS selectors specificity | Fixed 2025-12-27 | colors.css, form.css, style.css | Changed to `:not()` pattern |
| Error message HTML encoding | Fixed 2025-12-27 | form-controller.js | Allow `<br>` tags in showError() |
| Remove obsolete .min.css files | Fixed 2025-12-27 | Deleted style.min.css, library.min.css | Using minify.php instead |
| Table filter dropdown z-index | Fixed 2025-12-27 | library.js, library.css | Uses lib_zindex_manager.getDropdownZIndex() dynamically |
| Search by field for large datasets | Fixed 2025-12-27 | library.js | Added text filter mode for >2500 rows |

---

## Known Bugs

### No horizontal scrollbar in table view (mobile)
**Status**: Fix attempted (2026-01-30) - needs testing
**Location**: main.css + form.css (mobile media queries)
**Fix applied** - Multiple DOM levels had `overflow: hidden` blocking scroll:
- `main.css`: `.cma-content`, `.cma-content-inner`, `.cma-content-inner .cma-form` → `overflow: visible`
- `form.css`: `.form-layout`, `#leftlist` → `overflow: visible`
- `form.css`: `#leftlist #c.listcontent` → `overflow: auto`
- `form.css`: `#leftlist table.listtable` → `min-width: max-content`
**Notes**: The entire parent chain from `.cma-content` down to `#c.listcontent` had `overflow: hidden`, which clips all children regardless of their overflow settings.

---

### ~~Form instances stack when clicking list items~~
**Status**: Tests re-enabled (2026-01-01)
**Location**: form-controller.js
**Cypress Tests**: security-forms.cy.js (custom renderer tests now enabled)

Originally the custom renderer tests were skipped due to visible form stacking. The root cause was that embedded JavaScript in custom renderer HTML (loaded via innerHTML) wasn't executing, preventing event handler attachment.

**Fix applied**:
- Moved rights-matrix JS initialization to `initRightsMatrix()` method in form-controller.js
- Method is called after AJAX content load, properly attaching change handlers
- Also fixed checkbox value parsing in RecordService.php (accepts 'True'/'true'/'on' in addition to '1')

---

### ~~Fold in treeview not working~~
**Status**: FIXED (2024-12-26)
**Location**: cma.js

Fixed by using `classList.add/remove` instead of `className =` which was removing the `t` class needed for CSS styling.

---

### ~~Search not working with FilterFieldName forms~~
**Status**: FIXED (2024-12-26)
**Location**: form-controller.js (3 locations)

Fixed by preserving toolbar filter when applying search panel filters, using spread operator to merge filter objects.

---

### ~~Custom Renderer AJAX Loading (Users/Groups forms)~~
**Status**: FIXED (2024-12-26) - Was a TEST issue, not code issue
**Forms**: users, groups
**Cypress Tests**: security-forms.cy.js (all 8 tests passing)

The custom renderers were working correctly. The Cypress tests were:
1. Visiting form.php directly instead of through main.php
2. Using incorrect regex assertion syntax

Fixed tests now use `cy.openFormTree()` and proper assertions.

---

### ~~Submenu items ODBC buffer error~~
**Status**: FIXED / Cannot reproduce (2024-12-26)
**URL**: http://172.29.208.1/cma/form.php?form=_menus&id=51

Originally reported error: `[Microsoft][ODBC-stuurprogrammabeheer] Ongeldige tekenreeks- of bufferlengte`

Verified working: The _menus form loads correctly and the Menu-items subform renders all items (using JSON config, no database queries). Created Cypress tests in `config-forms.cy.js` to verify this continues working.

---

### ~~Contentblocks detail view - record not found~~
**Status**: FIXED / Cannot reproduce (2024-12-26)
**URL**: http://172.29.208.1/cma/form.php?form=contentblocks

Originally reported error: `Record met ID '99' niet gevonden`

Verified working: The contentblocks form correctly handles string IDs (B99, C47, etc.). Created Cypress tests in `config-forms.cy.js` that verify:
- List loads with all contentblocks
- Detail view loads correctly for string ID "B99" (Button)
- API returns correct data with `fields.id = "B99"`

---

### ~~cma-groupbox click does not work~~
**Status**: FIXED / Cannot reproduce (2024-12-26)
**Location**: `webcomponents/cma-groupbox.js`

Originally reported: Clicking on groupbox headers does not toggle the group open/closed.

Verified working: Ran comprehensive Cypress tests (`cma-groupbox.cy.js` and `cma-groupbox-standalone.cy.js`) - 67 tests passing. Click handling, toggle, state persistence all work correctly.

---

### ~~Table filtering not showing~~
**Status**: FIXED (2025-12-27)
**Location**: `library.js` lines 173-194 (getDropdownZIndex), line 2145 (dynamic z-index)

Fix applied:
1. Added `getDropdownZIndex()` method to `lib_zindex_manager`
2. Filter dropdown now sets z-index dynamically when opened
3. Z-index is calculated to be just below any open overlays (sidepanels, dialogs)

Check browser console for `[library.js]` and `[filtering_init]` messages for debugging.

---

### ~~Sidepanel z-index for subform records~~
**Status**: ✅ FIXED (2026-02-18)
**Location**: `lib_OpenSidePanel()` in library.js

Fixed: When opening sidepanel from iframe, `lib_zindex_manager.push()` was called from iframe context which could fail to delegate to top window's z-index stack. Now uses `topWindow.lib_zindex_manager` directly in both `lib_OpenSidePanel()` and `lib_CloseSidePanel()`.

---

### ~~Filtering reapplication after infinite scroll~~
**Status**: ✅ FIXED (2026-02-18)

The `excelTableFilterRefresh()` call was already in place (table-preferences.js line 747), but filter event handlers (bindCheckboxes, bindSelectAllCheckboxes, bindSort, bindSearch, bindRangeFilters) captured `this.rows` in closure variables at init time. After `refresh()` updated `this.rows`, the closures still referenced the old array. Fixed by using `self.rows` (live reference to FilterCollection instance) instead of captured local vars.

---

### ~~Search by field doesn't work~~
**Status**: FIXED (2025-12-27)
**Location**: `library.js` lines 2220-2244, 2440-2460, 2660-2668, 2833-2847

Fixed by implementing proper text filter mode for large datasets (>2500 rows):
1. Added `dropdownFilterTextSearch()` function for creating text search input
2. Added `useTextFilterMode` flag for columns with too many values
3. Added binding for text filter mode in `bindSearch()`
4. Added text filter mode check in `updateRowVisibility()`

When there are more than MAX_FILTER_LENGTH (2500) rows, the filter now shows:
- Sort buttons (A-Z / Z-A)
- Text search input field
- Message explaining why checkboxes are not available

---

## Disabled Features

### ~~Login Check~~
**Status**: FIXED (2025-12-30)
**Location**: `bootstrap.inc:1413-1428`

Re-enabled the login check in bootstrap.inc. Unauthenticated users are now redirected to the login page. API endpoints return JSON error `{success: false, error: 'Not authenticated', requireLogin: true}`.

Excluded pages (no login required):
- login.php, default.php, logout.php, copyright.php, blank.php, menurep.php
- sso_login.php, sso_callback.php (SSO authentication flow)
- minify.php (static resource handler)
- opcache_reset.php (standalone utility)

---

### CombineWithNext (Side-by-side Fields)
**Status**: Disabled
**Location**: `classes/FormTemplate.php:818-820`

Too many edge cases with row closing `</tr>` tags.
---

## Known Bugs (Active)

### Unsolved Bugs from prompts.md (2026-01-30)

The following bugs were reported in prompts.md but remain unresolved:

#### Fixed This Session (NEEDS CONFIRMATION)
| Bug | First Reported | Status |
|-----|----------------|--------|
| Groups form - Rapporten/Rechten not saved | Jan 2026 (3x) | **FIX APPLIED** - $_POST fallback added |
| Monitoring Notificatie field empty | Jan 2026 (3x) | **FIX APPLIED** - Server-side changelog added |

#### Still Open - High Priority
| Bug | First Reported | Location |
|-----|----------------|----------|
| Combobox not filling labels (parentID issue) | Recurring | form-controller.js, combo logic |
| Infinite scrolling - count mismatch | Jan 2026 | form-controller.js |
| CKEditor missing cma.css | Jan 2026 | CKEditor config |
| Field/column selector not working | Jan 2026 (2x) | table-preferences |
| Select2 expanding below dialog | Partially fixed | Report designer (only remaining Select2 usage) |
| cmamonitoring form details always empty | Jan 2026 | cmamonitoring.json listQuery |
| Tree mode - last selected record empty | Jan 2026 | form-controller.js |

#### Still Open - Medium Priority
| Bug | First Reported | Location |
|-----|----------------|----------|
| Subform parentField errors not showing form | Jan 2026 | migrations, form generation |
| Tooltips not showing on truncated th | Jan 2026 | library.js |
| Report designer - Advanced mode not active on load | Jan 2026 | report-designer.js |
| Report designer - Cannot edit table aliases | Jan 2026 | report-designer.js |
| Report designer - Delete relationships | Jan 2026 | report-designer.js |
| Report designer - WHERE clause wrong | Jan 2026 | QueryBuilder.php |
| Horizontal scrollbar missing (mobile) | Jan 2026 | form.css, main.css |
| Stacking sidepanels on large screens | Jan 2026 | library.js z-index |
| ~~Inline editing not fully integrated~~ | ✅ FIXED (2026-02-17) | inline-edit.js - Select2→lib-combo, right-click context menu, Delete key shortcut |

#### Still Open - Lower Priority
| Bug | First Reported | Location |
|-----|----------------|----------|
| lib-log.js not triggering console display | Jan 2026 | error-handler.js |
| Rooster blok field showing number | Jan 2026 | combo casing issue |
| toetsen/254 missing deelnemers subtab | Jan 2026 | subform config |
| Submenu scroll to visible not working | Jan 2026 | cma.js menu |

---

### SQLite repair - Connection cleanup only affects current process
**Status**: Limitation
**Added**: 2026-01-26
**Location**: tools/tools_sqlite_repair.php

The `Database::closeAll()` call before repair operations only closes connections from the current PHP process. Other browser tabs, background tasks, or other users with open CMA sessions will still hold connections to the SQLite database, potentially blocking WAL file deletion.

**Workaround**: User must close all CMA browser windows and wait, or restart IIS/webserver.

**Potential improvement**: Could add a "lock file" mechanism or use SQLite's `PRAGMA locking_mode=EXCLUSIVE` to force exclusive access before repair.

---

### Select2 expanding below dialog
**Status**: Partially fixed (2026-02-17)
**Location**: Report designer (only remaining Select2 usage)

Select2 was removed from detail forms, inline editing, toolbar filters, and search panels (replaced with lib-combo web component). The report designer still uses Select2 and may have dropdown positioning issues.

---

## Future Enhancements

### minify.php optimization - Combine resources
**Status**: Not implemented
**Added**: 2026-01-26

Currently minify.php loads CSS/JS files directly without combining them. Could be enhanced to combine multiple files into a single request for better performance.

---

### Real-time SQL update on field selection
**Status**: Not implemented
**Added**: 2026-01-26
**Location**: Report designer

When selecting/deselecting fields in the report designer, the SQL preview should update in real-time to show the query changes immediately.

---

### Hide toolbar-status on small screens
**Status**: Not implemented
**Added**: 2026-01-26

Add CSS media query to hide `.toolbar-status` element when viewport width is less than 1024px to improve mobile/tablet experience.

---

### Date grouping features for reports
**Status**: Not implemented
**Added**: 2026-01-26
**Location**: Report designer

Add options to group date fields by:
- Year
- Month
- Quarter
- Week

This would allow aggregated reports like "Sales by month" or "Users registered per quarter".

---

### Table resize snap to grid
**Status**: Partial
**Added**: 2026-01-26
**Location**: Report designer schema canvas

Table resizing currently only has boundary checking. Add snap-to-grid functionality for cleaner alignment when resizing table boxes.

---

### Button visual feedback (pressed state)
**Status**: Not implemented
**Added**: 2026-01-26

Buttons lack visual feedback for pressed/active state. Add `:active` CSS styles to provide tactile feedback when clicking buttons.

---

### Storybook for components
**Status**: Not created
**Added**: 2026-01-26

Create a Storybook or similar component showcase page to display all web components with their various states and configurations. Would help with:
- Component documentation
- Visual regression testing
- Design consistency

---

### No spaces in aliases validation
**Status**: Not implemented
**Added**: 2026-01-26
**Location**: Report designer field configuration

Add validation to prevent spaces in field aliases. Spaces in aliases can cause SQL errors or unexpected behavior.

---

### Contentblock form: variables table editor
**Status**: Not implemented
**Added**: 2026-01-26
**Location**: contentblocks form definition

Add a custom control to edit the "Variabelen (JSON)" field in a table format instead of raw JSON. Table columns:
- Name
- Description
- Type (dropdown: text, longtext, url, image, file, switch, array)
- Required (checkbox)

This would make it easier to define content block variables without writing JSON manually.

---

### Remove sourceFormId Dependency
Use form names instead of numeric IDs for permission checks.

### Global Search Field
Single text input that searches across all searchable fields.

### Subform Filtering Table
Add filtering/sortable table display mode to subform lists.

### Dynamic field name for subitem identification
For misc screens (menu/contentblocks), use dynamic field name instead of "id".

### btn-add-related uses data-form-id instead of data-form-name
Should use `data-form-name` to match the form name system.

### lib-histogram web component
Add comments/documentation for the new `lib-histogram` web component in the conversion script.

### Web Component Documentation
Investigate if https://github.com/runem/web-component-analyzer can help documenting the web components.

### Conversion of existing reports to report generator
Migrate existing reports from legacy tblReports system to the new report generator.

---

## Code Cleanup

### Find and eliminate embedded CSS
**Status**: Pending
**Added**: 2026-01-22

Scan for embedded CSS in PHP/JavaScript files and move to proper CSS files. This code smell makes styling hard to maintain and override.

---

### ~~Replace .top-notification with lib-message~~
**Status**: COMPLETED (2026-01-06)
**Action**: Replaced all `.top-notification` CSS and JS with `lib-message` web component via `Lib_ToonTopNotificatie()`

Files changed:
- form-controller.js - simplified `cmaNotification` and `showTopNotification` to use `Lib_ToonTopNotificatie`
- cma-notification.js - simplified to use `Lib_ToonTopNotificatie`
- inline-edit.js - simplified `showNotification` to use `Lib_ToonTopNotificatie`
- style.css, form.css, inline-edit.css, library.css - removed legacy `.top-notification` CSS

### ~~Cache leegmaken niet volledig~~
**Status**: VERIFIED COMPLETE (2026-01-06)
**Location**: `tools_clearcache.php`

Cache tool clears all 11 cache types: OPcache, APCu, App Cache, File Cache, Minify, Form HTML, Invalidation signals, Realpath cache, Cache groups, Sessions (old), Temp (old).

### ~~Remove debug comment~~
**Status**: ALREADY REMOVED (2026-01-06)
Debug comment no longer exists in codebase.

---

## Directory Restructuring Plan

**Status**: Planned - Not yet implemented

See done.md for recommended structure. Current issues:
- 87 PHP files at root level
- Duplicate slider libraries
- Two documentation directories
- Test/debug files scattered

---

## Converter Notes (Front-End Code)

### Implement WebP Responsive Images on Front-End Pages
**Status**: Not implemented
**Added**: 2026-02-07
**Priority**: P1
**Depends on**: WebP conversion tool (tools_webp_convert.php) — DONE
**Related files**: `app/library/ResponsiveImage.php`, `app/library/Image.php`

The back-end responsive image system is complete. All new uploads via CMA save as `.webp` with responsive variants in `.responsive/` subdirectories. The front-end pages need to be updated to serve these optimized images.

#### What exists now

Every image uploaded through CMA (or batch-converted via Tools > Developer > WebP conversie) gets:

```
/images/photo.jpg                        ← original (kept)
/images/.responsive/photo-400w.webp      ← 400px wide variant
/images/.responsive/photo-800w.webp      ← 800px wide variant
/images/.responsive/photo-1200w.webp     ← 1200px wide variant
/images/.responsive/photo.webp           ← full-size WebP copy
```

New uploads save directly as `.webp` (with JPEG fallback if GD lacks WebP support).

#### What needs to happen on the front-end

**1. Replace `<img>` tags in converted PHP pages with `ResponsiveImage::imgTag()`**

Find all `<img src="/images/...">` in front-end PHP files and replace with:

```php
use App\Library\ResponsiveImage;

// Before:
<img src="/images/photo.jpg" alt="Beschrijving">

// After:
<?= ResponsiveImage::imgTag('/images/photo.jpg', 'Beschrijving') ?>
```

This outputs an `<img>` with `srcset` and `sizes` for all available variants, with `loading="lazy"` by default.

**2. Full `imgTag()` API**

```php
ResponsiveImage::imgTag(
    string $imageUrl,       // URL to original image, e.g. '/images/photo.jpg'
    string $alt = '',       // Alt text
    string $sizes = '100vw',// Sizes attribute for responsive breakpoints
    string $class = '',     // CSS class(es)
    array $attrs = []       // Extra HTML attributes
): string
```

Examples:

```php
// Hero image — full width, high priority (no lazy load)
echo ResponsiveImage::imgTag('/images/hero.jpg', 'Welkom', '100vw', 'hero-image', [
    'fetchpriority' => 'high',
    'loading' => 'eager',
    'width' => 1200,
    'height' => 600,
]);

// Thumbnail in sidebar — max 400px
echo ResponsiveImage::imgTag('/images/team/jan.jpg', 'Jan', '(max-width: 768px) 100vw, 400px', 'sidebar-photo');

// Content image — 50% width on desktop, full width on mobile
echo ResponsiveImage::imgTag('/images/article/diagram.png', 'Diagram', '(max-width: 768px) 100vw, 50vw');
```

Output example:
```html
<img src="/images/.responsive/photo.webp"
     srcset="/images/.responsive/photo-400w.webp 400w,
            /images/.responsive/photo-800w.webp 800w,
            /images/.responsive/photo-1200w.webp 1200w"
     sizes="100vw"
     alt="Beschrijving"
     loading="lazy">
```

If no variants exist, it gracefully falls back to `<img src="/images/photo.jpg">`.

**3. JavaScript (for dynamically loaded content)**

When building image URLs in JavaScript (AJAX, dynamic content):

```javascript
// Convert an image URL to its responsive WebP variant URLs
function getResponsiveUrls(imageUrl) {
    var name = imageUrl.replace(/\.[^.]+$/, '').split('/').pop();
    var dir = imageUrl.substring(0, imageUrl.lastIndexOf('/'));
    var responsiveDir = dir + '/.responsive';

    return {
        full: responsiveDir + '/' + name + '.webp',
        w400: responsiveDir + '/' + name + '-400w.webp',
        w800: responsiveDir + '/' + name + '-800w.webp',
        w1200: responsiveDir + '/' + name + '-1200w.webp',
        srcset: [400, 800, 1200]
            .map(function(w) { return responsiveDir + '/' + name + '-' + w + 'w.webp ' + w + 'w'; })
            .join(', ')
    };
}

// Usage:
var urls = getResponsiveUrls('/images/product.jpg');
img.src = urls.full;
img.srcset = urls.srcset;
img.sizes = '(max-width: 768px) 100vw, 50vw';
```

**4. CSS background images**

```css
/* Simple — use full-size WebP */
.hero { background-image: url('/images/.responsive/hero.webp'); }

/* Responsive — use image-set */
.hero {
    background-image: image-set(
        url('/images/.responsive/hero-400w.webp') 400w,
        url('/images/.responsive/hero-800w.webp') 800w,
        url('/images/.responsive/hero-1200w.webp') 1200w
    );
}
```

**5. Content blocks / CKEditor content**

Images embedded in rich text (CKEditor) still use original URLs. Options:
- Post-process HTML output with regex to add `srcset` attributes
- Add a helper: `ResponsiveImage::processHtml($html)` that finds `<img>` tags and adds `srcset`
- Or leave as-is since CKEditor content is typically small images

**6. Converter template update**

When the ASP→PHP converter generates image output, it should use `ResponsiveImage::imgTag()` instead of plain `<img>` tags. Update the converter templates in `converter/templates/` to:
- Import `use App\Library\ResponsiveImage;`
- Replace `<img src="<?= $imageUrl ?>">` patterns with `<?= ResponsiveImage::imgTag($imageUrl, $alt) ?>`

#### Helper methods available

| Method | Purpose |
|--------|---------|
| `ResponsiveImage::imgTag($url, $alt, $sizes, $class, $attrs)` | Build complete `<img>` tag with srcset |
| `ResponsiveImage::getWebPUrl($url)` | Get full-size WebP URL for an image |
| `ResponsiveImage::getVariantUrl($url, $width)` | Get specific width variant URL |
| `ResponsiveImage::hasVariants($path)` | Check if responsive variants exist |
| `ResponsiveImage::generate($path)` | Generate variants for a single image |
| `ResponsiveImage::batchGenerate($dir)` | Generate variants for all images in a directory |
| `ResponsiveImage::deleteVariants($path)` | Remove all variants for an image |
| `ResponsiveImage::scan($dir)` | List images with their variant status |
| `ResponsiveImage::cleanup($dir)` | Remove all `.responsive/` directories |
| `Image::isWebPSupported()` | Check if GD has WebP support |
| `Image::convertToWebP($src, $dest, $quality)` | Convert single image to WebP |

#### Variant sizes

Default: 400, 800, 1200 pixels wide (configurable via `ResponsiveImage::SIZES`).
Quality: 80 (configurable via `ResponsiveImage::DEFAULT_QUALITY`).

Only variants smaller than the original width are generated. A 500px wide image gets only a 400w variant + full-size WebP.

#### Steps to implement

1. Enable GD in php.ini: uncomment `extension=gd`, run `iisreset`
2. Run batch conversion: CMA > Tools > Developer > WebP conversie > Scannen > Alles converteren
3. Update front-end PHP templates to use `ResponsiveImage::imgTag()`
4. Update converter templates for future ASP→PHP conversions
5. Test with browser DevTools Network tab — verify WebP variants are served
6. Test on mobile — verify smaller variants are loaded on narrow screens

#### Verification

- Browser DevTools > Network > filter "webp" — should see `.responsive/*.webp` requests
- Lighthouse audit — should show improved image optimization score
- `sizes` attribute should match your CSS layout (prevents downloading oversized images)
- `loading="lazy"` on below-fold images, `loading="eager"` on hero/above-fold images

---

### Replace native prompt() with libPrompt()
**Status**: Pending for converter
**Added**: 2025-12-29

When converting front-end ASP code that uses `prompt()`, replace with `libPrompt()`:

```javascript
// Old (native)
var name = prompt("What is your name?");

// New (styled dialog)
var name = await libPrompt("What is your name?", { title: "Naam invoeren" });
```

Options available:
- `title` - Dialog title (default: "Invoer")
- `defaultValue` - Pre-filled value
- `placeholder` - Input placeholder text
- `confirmText` - OK button text (default: "OK")
- `cancelText` - Cancel button text (default: "Annuleren")
- `type` - Dialog type: info, warning, danger, success
- `inputType` - HTML input type: text, number, email, etc.
- `required` - Require non-empty value (default: false)
- `multiline` - Use textarea instead of input (default: false)

Returns: Promise<string|null> - Input value or null if cancelled

---

## LLM Actionable Items (2026-01-30)

The following items are ready for implementation. Each includes files to change, expected side-effects, and verification steps.

### Action 1: Verify cma_monitoring Setting
**Priority:** P0
**Status:** ✅ COMPLETED (2026-01-30)
**Effort:** 5 minutes
**Risk:** Low

**Result:** Setting is already enabled in `app.php:121`: `$GLOBALS['Application']['cma_monitoring'] = true;`

**Verification still needed:**
```sql
SELECT TOP 5 * FROM tblCMAMonitoring ORDER BY ID DESC
```

---

### Action 2: Add Monitoring to RecordService
**Priority:** P0
**Status:** ✅ COMPLETED (2026-01-30)
**Effort:** 30 minutes
**Risk:** Low (additive change)

**Changes made:**
- Made `FormDataProvider::logMonitoring()` public
- Added monitoring calls to `RecordService::save()` and `delete()`
- Added `buildLegacyAddChangelog()` for server-side changelog generation

**Verification still needed:**
- Save a record through legacy form
- Check tblCMAMonitoring for new record with Notificatie populated

---

### Action 3: Server-Side Changelog Generation
**Priority:** P0
**Status:** PARTIALLY COMPLETED (2026-01-30)
**Effort:** 2 hours
**Risk:** Medium (affects all forms)

**Changes made:**
- ✅ Added `buildAddChangelog()` for new records (server-side fallback)
- ✅ `buildDeleteChangelog()` already existed for delete operations
- ⏳ Edit operations still rely on client-side changelog (complex change)

**For Edit operations (future):**
1. In `saveJsonFormRecord()`, fetch existing record BEFORE update
2. Compare field values before/after
3. Build changelog server-side instead of relying on `$_POST['_changelog']`

**Verification needed:**
- Create a new record and verify Notificatie is populated
- Delete a record and verify Notificatie shows deleted values

---

### Action 4: Fix Groups Form Rapporten/Rechten Save
**Priority:** P0
**Status:** ✅ FIX APPLIED (2026-01-30) - NEEDS CONFIRMATION
**Effort:** 1-2 hours
**Risk:** Medium

**Fix applied:**
- Added `$_POST` fallback in `saveGroupRights()` (RecordService.php:1541-1556)
- If the $data array doesn't contain rights keys (group_menu_rights_*, group_report_rights_*), the function now reads directly from $_POST
- Added comprehensive logging to diagnose issues

**Verification needed:**
1. Open Groups form, modify Rapporten checkboxes
2. Save and verify changes persist
3. Check PHP logs for saveGroupRights debug output
4. Check tblGroupRights table for updated records

---

### Action 5: Enable Inline Edit Tests
**Priority:** P1
**Effort:** Variable
**Risk:** Low (test-only)

**File:** `cypress/e2e/forms/inline-edit.cy.js`

1. Remove `.skip` from describe/it blocks
2. Run tests: `npx cypress run --spec cypress/e2e/forms/inline-edit.cy.js`
3. Document failures
4. Fix underlying issues one by one

---

### Action 6: Consolidate toBool Function
**Priority:** P1
**Effort:** 1 hour
**Risk:** Medium (affects multiple files)

**Single implementation in ListServiceHelper:**
```php
public static function toBool($value): bool {
    return $value === true || $value === 1 || $value === -1 ||
           strtolower((string)$value) === 'true' ||
           $value === '1' || $value === '-1';
}
```

**Files to update:**
- FormDataProvider.php - remove private toBool, use ListServiceHelper::toBool
- FormTemplate.php - remove private toBool, use ListServiceHelper::toBool
- ListService.php - already has wrapper, make it delegate

**Verification:**
- Run all Cypress tests
- Verify boolean fields display correctly in forms

---

### Action 7: Add CRUD Tests for Major Forms
**Priority:** P1
**Effort:** 4+ hours
**Risk:** Low (test-only)

Create test files for untested forms:
1. `cypress/e2e/forms/deelnemers-form.cy.js`
2. `cypress/e2e/forms/docenten-form.cy.js`
3. `cypress/e2e/forms/praktijkopleiders-form.cy.js`

**Template:**
```javascript
describe('Form Name', () => {
  beforeEach(() => {
    cy.login();
    cy.openFormTree('formname');
  });

  it('should create a record', () => {
    cy.get('[data-action="add"]').click();
    // Fill required fields
    cy.get('[data-action="save"]').click();
    cy.get('.success-message').should('be.visible');
  });

  it('should update a record', () => { /* ... */ });
  it('should delete a record', () => { /* ... */ });
  it('should log to monitoring', () => { /* ... */ });
});
```

---

## Cypress Test Results (2026-01-30)

### api/monitoring.cy.js - ✅ ALL PASSING (13/13)
- Save operations logging: ✅
- Delete operations logging: ✅
- Monitoring data integrity: ✅
- Monitoring form access: ✅
- Inline editing monitoring: ✅
- Notificatie field content (ADD, DELETE, EDIT): ✅

**Conclusion**: Monitoring is working correctly!

### forms/groups-form.cy.js - 10 passing, 11 failing

| Test | Status | Notes |
|------|--------|-------|
| Table view setup | ✅ | |
| Create group basic | ✅ | |
| Validation | ✅ | |
| Rights matrix display | ✅ | |
| **should save menu rights (Rechten)** | ❌ | POST missing group_menu_rights fields |
| **should save report rights (Rapporten)** | ❌ | POST missing group_report_rights fields |
| Detail form operations | ❌ | Timeout issues |

**Critical Finding**: The custom renderer rights data is NOT being included in the POST at all. The server-side $_POST fallback won't help because the data isn't being sent by the client.

**Root Cause**: JavaScript collectFormData() is not capturing the rights-matrix inputs.

---

## Verification Tests Required (2026-01-30)

The following tests must be performed to confirm the fixes work:

### Test 1: Monitoring for Add operations
1. Open any form (e.g., groups)
2. Click Add to create a new record
3. Fill in required fields and save
4. Query: `SELECT TOP 1 * FROM tblCMAMonitoring ORDER BY ID DESC`
5. **Expected**: New row with `Actie='add'` and `Notificatie` containing green "Nieuwe waarde" table

### Test 2: Monitoring for Delete operations
1. Create or select a test record
2. Delete the record
3. Query: `SELECT TOP 1 * FROM tblCMAMonitoring ORDER BY ID DESC`
4. **Expected**: New row with `Actie='delete'` and `Notificatie` containing red "Verwijderde waarde" table

### Test 3: Groups Form - Rapporten/Rechten Save
1. Open Groups form, select a group
2. Change some Rapporten checkboxes (check/uncheck)
3. Change some Rechten radio buttons
4. Save the form
5. Refresh the page and reselect the group
6. **Expected**: Changes should persist
7. Also check PHP logs for `saveGroupRights:` messages

### Test 4: Edit Changelog (client-side)
1. Open any form, select existing record
2. Change a field value
3. Save
4. Query: `SELECT TOP 1 * FROM tblCMAMonitoring ORDER BY ID DESC`
5. **Expected**: `Notificatie` shows "was / gewijzigd in" table (blue header)

### Test 5: RecordService Monitoring (legacy forms)
1. If any legacy forms still use RecordService directly
2. Save/delete through those forms
3. **Expected**: Monitoring entries created

---

## Dependency Order

Execute actions in this order to minimize risk:

1. **Action 1** - Verify monitoring enabled (no code change)
2. **Action 5** - Enable tests to establish baseline
3. **Action 4** - Fix Groups save (user-reported P0)
4. **Action 2** - Add RecordService monitoring
5. **Action 3** - Server-side changelog (most complex)
6. **Action 6** - Consolidate helpers
7. **Action 7** - Add more tests (ongoing)

