# PHP Change Log

This file documents all manual PHP changes made after ASP-to-PHP conversion.
When ASP files are updated and re-converted, replay these changes to restore modifications.

## Format

Each entry includes:
- **File**: The PHP file that was changed
- **Description**: What was changed and why
- **Change**: The specific modification (old → new)

---

## Changes

### 1. Comprehensive GLOBALS cleanup in class_login.inc (2026-02-12)

- **File**: `module/login/class_login.inc`
- **Description**: The ASP-to-PHP converter creates `$GLOBALS['var']` for every variable. In PHP, method-local variables don't need GLOBALS. This change:
  1. Converts ~150 method-local `$GLOBALS['var']` to just `$var`
  2. Removes redundant `$var = null;` lines before immediate assignment
  3. Normalizes case-sensitivity bugs: `$GLOBALS['Id']`/`$GLOBALS['ID']` → `$GLOBALS['id']`, `$GLOBALS['Guid']` → `$GLOBALS['guid']` (ASP is case-insensitive, PHP is not)
  4. Fixes undefined `$guid` → `Cookie::get(COOKIE_GUID, '')` in LoginAs/LoginByEmailAddress
  5. Fixes undefined `$id` → `Cookie::get(COOKIE_ID, '')` in LoginAs
  6. Fixes undefined `$strTableName` → `self::$strTableName` in LoginByEmailAddress
  7. Fixes infinite loop in `Login_GetOpl()` - missing `$rs_opl->fetch()` in while loop
  8. Fixes case mismatch `$GLOBALS['useropleidingen']` → `$GLOBALS['UserOpleidingen']` etc. in Assistent_CheckOpleidingen
  9. Fixes `$GLOBALS['sql']` → `$sql` in Login_GetOpl (was reading wrong variable)
  10. Cleans up constructor to use `self::$prop` instead of GLOBALS
  11. Uses `self::$strLoginError` instead of `$GLOBALS['strLoginError']`
- **Pattern**: The converter pattern `$var = null; $GLOBALS['var'] = value; ... $GLOBALS['var'] ...` becomes `$var = value; ... $var ...` for all method-local variables
- **Kept as GLOBALS**: `$GLOBALS['id']`, `$GLOBALS['guid']`, `$GLOBALS['loginType']`, `$GLOBALS['PromptusID']`, `$GLOBALS['libSQL_KeepOffAmp']` - these are cross-file shared state

### 2. Fix queryIntAndGuid stripping dashes from GUIDs (2026-02-12)

- **File**: `app/library/Request.php`
- **Description**: `queryIntAndGuid()` only preserved GUIDs starting with `{`. Bare GUIDs (without braces) had their dashes stripped by `Str::numbersOnly()`, turning e.g. `70962414-1108-1194-9739-2...` into `709624141108119497392`, which breaks SQL GUID lookups.
- **Change**: Added GUID pattern detection before falling back to `numbersOnly()`. If the value matches a GUID pattern (hex + dashes), return it as-is.

### 4. Fix GUID comparisons for Access ODBC (2026-02-12)

- **File**: `app/library/SQL.php`
- **Description**: Access Replication ID (GUID) columns cannot be compared with `=` through native ODBC — the query executes but returns no rows regardless of format (`{guid 'value'}`, `'value'`, `'{value}'`). Only `LIKE` forces a text comparison and works correctly. Added `SQL::guidEquals($column, $guid)` which uses `LIKE` for Access and `=` for SQL Server. Changed `postGuid()` to return plain quoted strings for both databases.
- **Change**:
  1. `postGuid()`: Access branch changed from `{guid 'value'}` to `'value'` (plain quoted string)
  2. New method `guidEquals($column, $guid)`: returns `col LIKE 'value'` for Access, `col = 'value'` for SQL Server
  3. All GUID WHERE clauses in class_login.inc changed from `WHERE col=SQL::postGuid($val)` to `WHERE SQL::guidEquals('col', $val)`

### 4b. Fix GUID comparisons in class_login.inc (2026-02-12)

- **File**: `module/login/class_login.inc`
- **Description**: Four GUID WHERE clauses used `= SQL::postGuid()` which doesn't work for Access ODBC. Changed to use `SQL::guidEquals()`.
- **Change**: In `LoginAs`, `ForgotPassword`, `LoginWithSessionID`, and `isLoggedIn`: `WHERE guid=SQL::postGuid($val)` → `WHERE SQL::guidEquals('guid', $val)`

### 5. Add Cookie::set for id and guid in Login class (2026-02-12)

- **File**: `module/login/class_login.inc`
- **Description**: In ASP, `Id` and `Guid` were property setters that automatically wrote cookies (`Response.Cookies(COOKIE_ID) = parID`). The converter turned these into `$GLOBALS['id']` assignments, losing the cookie-writing side effect. Without cookies, the login state is lost on redirect.
- **Change**: Added `Cookie::set(COOKIE_ID, ...)` and `Cookie::set(COOKIE_GUID, ...)` wherever `$GLOBALS['id']` and `$GLOBALS['guid']` are assigned in `TryLogin()`, `LoginWithSessionID()`, and `isLoggedIn()`. Also in `isLoggedIn()`, changed `$GLOBALS['id'] = $GLOBALS['id'] ?? ''` to read from `Cookie::get(COOKIE_ID, '')` when empty — matching the ASP `Property Get` behavior that always read from cookies on each request.

### 6. Fix column name casing and converter artifacts in class_login.inc (2026-02-12)

- **File**: `module/login/class_login.inc`
- **Description**: Multiple converter artifacts cause errors in PHP due to case sensitivity and stale API patterns:
  1. `$rs_current_row['GUID']` → `$rs_current_row['Guid']` in TryLogin (ODBC returns column as `Guid`, not `GUID`)
  2. `$rs->Fields[$strFieldLastLoginName]` → `$rs_current_row[$strFieldLastLoginName] ?? null` in TryLogin (old recordset API → PDO fetch array)
  3. `SELECT * FROM tblLogins` → explicit column list in Login_GetRelated (avoids case mismatch on column names)
  4. Removed dead `if (COOKIE_EXPIRE_SET)` blocks (constant is always `false`, and the `Cookie::get()` inside was a no-op)
- **Change**: Explicit column list: `SELECT tblLogins.ID, fkDeelnemer, fkDocent, fkPraktijkopleider, fkAssistent, fkP_Praktopl, fkSRHServicePakket, fkSRHForumLid, fkSupervisor, fkWerkbegeleider, fkKlantContactPersoon FROM tblLogins`

### 7. Fix wissel_rol inverted POST condition (2026-02-12)

- **File**: `index_wissel_rol.inc`
- **Description**: The ASP code `if request.Form.Count=0 then` shows the form on GET requests (no POST data). The converter produced `if (Request::postAll())` which inverts the logic — showing the form when there IS POST data, causing an infinite redirect loop.
- **Change**: `if (Request::postAll()) {` → `if (empty($_POST)) {` (show form when no POST data, process form when POST data exists)

### 8. Fix Login_InitVars missing global declarations (2026-02-12)

- **File**: `module/login/class_login.inc`
- **Description**: `Login_InitVars()` sets role ID variables (`$deelnemerID`, `$docentID`, `$assistentID`, etc.) that are used by `wissel_rol()` and other functions. In ASP, variables are module-level by default. The converter created them as local variables inside the function, making them invisible outside. This caused the role selection page to show an empty list.
- **Change**: Added `global` declarations at the top of `Login_InitVars()`:
  ```php
  global $deelnemerID, $docentID, $assistentID, $praktijkopleiderID, $p_praktoplID;
  global $SRHServicepakketID, $ContInvID, $SupervisorID, $WerkbegeleiderID, $klantContactpersoonID;
  global $UserJaargroepopleiderOpl, $UserOplExclCursorisch, $UserPraktijkopleiders, $UserPlanning;
  global $USER_bServiceBureau, $UserAantalRollen, $LoginType;
  ```

### 9. Fix undefined $intStandaardRol in index.php (2026-02-12)

- **File**: `index.php`
- **Description**: ASP auto-initializes variables to empty string. PHP does not. `$intStandaardRol` was used without initialization, causing an "Undefined variable" error.
- **Change**: Added `$intStandaardRol = '';` before the first use of the variable (around line 111)

### 10. Fix StringBuffer method calls without parentheses (2026-02-12)

- **Files**: `header.inc`, `utils.inc`
- **Description**: In VBScript, `obj.GetSize` works for both property access and method calls. The converter produces `$obj->GetSize` (property access) but `GetSize` is a method in the PHP StringBuffer class and needs `()`.
- **Change**: `$menu_cache->GetSize` → `$menu_cache->GetSize()` in header.inc, `$sLijst->GetSize` → `$sLijst->GetSize()` in utils.inc
- **Converter fix**: Added `fix_method_as_property_access` postprocessor that adds `()` to known methods (GetSize, ToString, Clear, SaveToFile, GetBuffer) when accessed as properties.

### 11. Fix missing global $menu_cache in WriteInMenu (2026-02-12)

- **File**: `header.inc`
- **Description**: `WriteInMenu()` uses `$menu_cache` (a top-level StringBuffer) without declaring it `global`. ASP variables are module-level by default; PHP function variables are local.
- **Change**: Added `global $menu_cache;` inside `WriteInMenu()`

### 12. Fix $GLOBALS['useropleidingen'] case mismatch in utils.inc (2026-02-12)

- **File**: `utils.inc`
- **Description**: ~35 occurrences of `$GLOBALS['useropleidingen']` (lowercase) should use `$UserOpleidingen` (PascalCase), which is the correct variable name and already declared `global` in each function. ASP is case-insensitive; PHP is not.
- **Change**: Bulk replaced all `$GLOBALS['useropleidingen']` → `$UserOpleidingen`

### 13. Fix missing ->fetch() in while loops (infinite loops) (2026-02-12)

- **Files**: `utils.inc` (3 loops), `header.inc` (1 loop)
- **Description**: The converter produces `while ($rs_current_row !== false) { ... }` but omits the `$rs_current_row = $rs->fetch()` at the end of the loop body. This causes infinite loops (30s timeout). Same pattern as the `Login_GetOpl()` fix in change #1.
- **Change**: Added `$rs->fetch(PDO::FETCH_ASSOC)` at end of while body in:
  - `utils.inc`: `AlgemeneInfo_GetAllowed()` (line ~2300), `Nieuws_GetAllowed()` (line ~2393), `Get_P_Praktijkopleiders()` (line ~2882)
  - `header.inc`: `get_menu()` rsInfo loop (line ~389)

### 14. Fix SELECT * column casing in header.inc get_menu (2026-02-12)

- **File**: `header.inc`
- **Description**: `SELECT * from tblAlgemeneInfo` returns columns with database casing. Code used lowercase (`titel`, `externe_link`, `guid`) but ODBC returns PascalCase (`Titel`, `Externe_Link`, `Guid`).
- **Change**: Changed to explicit column list `SELECT ID, Titel, Externe_Link, Guid` and updated code references to match.

### 15. Fix Login::$Id static property access in header.inc (2026-02-12)

- **File**: `header.inc`
- **Description**: ASP's `MyLogin.Id` was a property getter that read from cookies. The converter produced `Login::$Id` but the PHP Login class has no static `$Id` property.
- **Change**: `Login::$Id` → `Cookie::get(COOKIE_ID, '')`

### 17. Fix GUID comparison and column casing in index_info.inc (2026-02-12)

- **File**: `index_info.inc`
- **Description**: GUID WHERE clause used bare `= guid_value` (unquoted, no LIKE). Changed to `SQL::guidEquals()`. Also changed `SELECT *` to explicit column list and fixed column casing.
- **Change**: `guid=' . $parInfoGuid` → `SQL::guidEquals('Guid', $parInfoGuid)`. Column refs: `titel` → `Titel`, `inhoud` → `Inhoud`, `footer` → `Footer`.

### 16. Fix $loginType/$LoginType case mismatch in index.php (2026-02-12)

- **File**: `index.php`
- **Description**: index.php used `$loginType` (lowercase L, 30 occurrences) but `Login_InitVars()` sets `$LoginType` (uppercase L). In ASP these are the same variable. In PHP they're different, so after `Login_GetRelated()` set `$LoginType`, index.php's `$loginType` remained empty, causing the "wissel_rol keeps coming back" loop — the code on line 104 `if ($loginType == '')` was always true.
- **Change**: Bulk replaced all `$loginType` → `$LoginType` in index.php (30 occurrences)

### 18. Bulk fix GUID WHERE clauses across all PHP/INC files (2026-02-12)

- **Files**: `cgo_document.php` (9), `bevestig_email.php` (7), `eval_document.php` (2), `deelnemer_selecteer_compensatie.php` (3), `_moodle/sso.php` (1), `_moodle/loggedin_user.php` (1), `taak_teruggeven.php` (3), `taak_terugnemen.php` (3), `taak_opmerking.php` (1), `taak_delegeer.php` (1), `dig_presentie.php` (1), `ajax_delete_docentplanning_melding.php` (2), `agenda/index.php` (4), `eval_resultaten.php` (1), `planning_wijzig.php` (4), `planning_docent_verstuur.php` (5), `planning_akkoord.php` (1), `opleiding_cgo_verzoek.php` (1), `formulier7.php` (1)
- **Description**: Access ODBC GUID (Replication ID) columns cannot be compared with `=` — see change #4. All GUID WHERE clauses across the entire site changed from `guid=SQL::postGuid($val)` or bare `guid=$val` to `SQL::guidEquals('guid', $val)`.
- **Patterns fixed**:
  1. `WHERE guid=' . SQL::postGuid($val)` → `WHERE ' . SQL::guidEquals('guid', $val)` (quoted but using `=`)
  2. `WHERE guid=' . $val` → `WHERE ' . SQL::guidEquals('guid', $val)` (bare unquoted — also an injection risk)
  3. `WHERE [guid]=' . $val` → `WHERE ' . SQL::guidEquals('guid', $val)` (Access bracket-quoted column)
- **Also**: Added `use App\Library\SQL;` to `planning_akkoord.php` (was missing)

### 19. Fix LOGIN_EMAIL constant → variable in index_profiel.inc (2026-02-12)

- **File**: `index_profiel.inc`
- **Description**: `LOGIN_EMAIL` was used as a constant (no `$` prefix) but it's actually a variable set in `index.php:56` as `$LOGIN_EMAIL = true`. The converter dropped the `$` prefix. Used on lines 47, 101, 131. Additionally, `$LOGIN_EMAIL` was not in the `global` declaration of `function index_profiel()`, so it was undefined inside the function.
- **Change**: `LOGIN_EMAIL` → `$LOGIN_EMAIL` (3 occurrences). Added `$LOGIN_EMAIL` to the `global` declaration on line 23.

### 20. Fix column casing in index_profiel.inc (2026-02-12)

- **File**: `index_profiel.inc`
- **Description**: `SELECT * from tblLogins` returns ODBC-cased column names. Code used `$rs2_current_row['werkervaring']` (lowercase) but ODBC returns `Werkervaring` (PascalCase).
- **Change**: `$rs2_current_row['werkervaring']` → `$rs2_current_row['Werkervaring']`

### 3. Remove $myLogin object reference in footer.inc (2026-02-12)

- **File**: `footer.inc`
- **Description**: `$myLogin` was the ASP Login object instance. In PHP, `Login` is a static class — no object exists. The `is_object($myLogin)` check causes an undefined variable error. Replaced with `Login::isLoggedIn()`.
- **Change**: `is_object($myLogin)` → `Login::isLoggedIn()`, removed `$myLogin = null;`, removed empty `if (Application::get('performance_log', '')) {}` block

### 21. Extract all CSS from ErrorHandler.php into errorhandler.css (2026-02-12)

- **Files**: `app/library/ErrorHandler.php`, `app/css/errorhandler.css`
- **Description**: ErrorHandler.php contained 5 embedded `<style>` blocks (~500 lines of CSS) and ~38 inline `style="..."` attributes. All CSS extracted to the external `errorhandler.css` file using `eh-` prefixed class names to avoid collisions with page styles.
- **Change**:
  1. Removed all 5 embedded `<style>` blocks
  2. Converted all ~38 inline `style="..."` attributes to `eh-` prefixed CSS classes
  3. Replaced generic class names (`.container`, `.card`, `h1`, `.btn`) with namespaced equivalents (`.eh-container`, `.eh-card`, `.eh-prod-heading`, `.eh-prod-btn`)
  4. Updated JavaScript selectors in `showTab()`, `toggleComments()`, `askClaudeHelp()`, `copySQL()` to use new class names
  5. Scoped unscoped `h1 { border: 0 }` reset to `.eh-header h1` etc.
  6. Kept only 3 minimal inline `body { font-family: sans-serif; margin: 0; padding: 0; }` for fallback error pages (where external CSS may not load)
  7. Organized CSS into 19 sections with CSS custom properties (`--eh-red`, `--eh-yellow`, etc.)

### 22. Fix undefined array key emailNieuwsNotificaties in index_profiel.inc (2026-02-12)

- **File**: `index_profiel.inc`
- **Description**: The `emailNieuwsNotificaties` column may not exist in the database table. Accessing it without a null check caused "Undefined array key" on line 265.
- **Change**: Added null coalescing on lines 265 and 268: `$rs_current_row['emailNieuwsNotificaties']` → `$rs_current_row['emailNieuwsNotificaties'] ?? ''`

### 23. Add missing JS library files (2026-02-12)

- **Files**: `library/fineuploader/fineuploader-jquery-plugin.js`, `library/fineuploader/fineuploader.min.js`, `library/fineuploader/fineuploader.css`, `js/jquery.tooltipster.js`, `js/jquery.tooltipster.min.js`
- **Description**: Three JS files referenced in PHP pages were missing from the converted site, causing 404 errors.
- **Change**:
  1. Copied FineUploader files (2 JS + 1 CSS) from original ASP source at `ASPCode_CMA/library/fineuploader/`
  2. Downloaded Tooltipster v3.3.0 from cdnjs CDN and placed in `js/` directory

### 24. Convert global variables to constants in utils.inc (2026-02-12)

- **File**: `utils.inc`
- **Description**: Five configuration flags (`$FUNCTIE_DISPENSATIES`, `$FUNCTIE_PO_RECHTEN_VERDELEN`, `$FUNCTIE_VRIJSTELLINGEN`, `$FUNCTIE_TAKEN`, `$SEND_LOGIN_EMAILS`) were defined as global variables but referenced as constants (without `$`) throughout the codebase. As variables, they were undefined in included files running in different scope.
- **Change**: Converted from `$VAR = value` to `define("VAR", value)` for all five. No changes needed in referencing files — they already used the constant form.

### 25. Fix unconverted ASP tags and MyLogin.id in index_profiel.inc (2026-02-12)

- **File**: `index_profiel.inc`
- **Description**: Lines 331-356 contained unconverted ASP `<% if not parPopup then %>` / `<% else %>` / `<% end if %>` conditionals inside a JavaScript `echo` block. Also `MyLogin.id` (ASP property) was not converted.
- **Change**:
  1. Replaced ASP `<% if not parPopup then %>` with PHP string concatenation using `(!$parPopup ? ... : ...)`
  2. Replaced `MyLogin.id` → `Cookie::get(COOKIE_ID, '')`

### 26. Case-insensitive recordset field access with Arr::field() (2026-02-12)

- **Files**: 82 PHP/INC files across `site/`
- **Description**: ASP/VBScript recordset field access is case-insensitive (`rs("fkDocent")` == `rs("fkdocent")`). After conversion to PHP, `$rs_current_row['fkdocent']` fails when ODBC returns `fkDocent`. Added `Arr::field()` helper with case-insensitive fallback and replaced all 2610 occurrences of `$xxx_current_row['field']` and `$xxx_current_row[$var]`.
- **Change**: `$rs_current_row['field']` → `Arr::field($rs_current_row, 'field')` across all files. Two write-context assignments reverted to direct array access.

### 27. Fix unconverted VBScript Rnd function (2026-02-12)

- **File**: `ajax_profiel_popup.inc`
- **Description**: `Rnd` (VBScript random float) was not converted, causing "Undefined constant" error.
- **Change**: `Rnd` → `mt_rand()` (used as cache-buster in profile photo URL)

### 28. Fix $lib_profile___start scope and operator precedence (2026-02-12)

- **File**: `ajax_profiel_popup.php`
- **Description**: `$lib_profile___start` is a global variable not visible inside `main()`. Also operator precedence bug: `microtime() - $var * 1000` multiplied start time by 1000 first.
- **Change**: `$lib_profile___start` → `$GLOBALS['lib_profile___start'] ?? microtime(true)`, wrapped subtraction in parentheses before `* 1000`

### 29. Fix Request::queryIntAndGuid() to accept bare GUIDs (2026-02-12)

- **File**: `converter/templates/library/Request.php` (synced to `app/library/`)
- **Description**: Method only recognized GUIDs wrapped in braces `{D3B9...}`. Bare GUIDs like `D3B9BFB7-0F3F-4979-8269-597DC6AC35B6` (used in `?assumeidentity=` URLs) were stripped to numbers only.
- **Change**: Replaced `substr($value, 0, 1) === '{'` check with proper GUID regex accepting both braced and bare formats.

### 30. Fix unconverted SQL IN clause (2026-02-12)

- **Files**: `module/login/class_login.inc`, `opleiding_voortgang.inc`
- **Description**: VBScript `IN(1,2)` was incorrectly converted to `$IN[1][2]` (array notation).
- **Change**: `$IN[1][2]` → `IN (1,2)` in 3 occurrences across 2 files.

### 31. Fix OPT_LIVE referenced as constant instead of variable (2026-02-12)

- **Files**: `header.inc`, `cma_afterpost.php`
- **Description**: `OPT_LIVE` is defined as `$OPT_LIVE = false` in `utils.inc` but referenced without `$` as if it were a constant.
- **Change**: `!OPT_LIVE` → `!($GLOBALS['OPT_LIVE'] ?? false)` in 4 occurrences across 2 files.

### 32. Fix vendor/autoload.php relative paths in profiel/ (2026-02-12)

- **Files**: 20 PHP files in `profiel/`
- **Description**: `require_once 'vendor/autoload.php'` resolved against site root (due to bootstrap changing working directory) instead of the `profiel/` directory.
- **Change**: `require_once 'vendor/autoload.php'` → `require_once __DIR__ . '/vendor/autoload.php'`

### 33. Fix ToonBerichten() multiple conversion bugs (2026-02-12)

- **File**: `index.php`
- **Description**: The `ToonBerichten()` function had 5 conversion bugs causing empty berichten page and dashboard messages:
- **Changes**:
  1. Line 277: Empty `berichten` page action block — added `ToonBerichten(999)` call
  2. Line 313: `Database::openRS($rsMessages, $sql, ...)` — wrong arg order (ASP byref pattern), fixed to `$rsMessages = Database::openRS($sql, ...)`
  3. Line 317: Spurious `$rsMessages->MoveNext()` right after open — removed (was skipping first record)
  4. Line 321: `$rsMessages_current_row === false` — undefined variable, fixed to `$rsMessages->EOF`
  5. Lines 347/351/362: `rsMessages('ID')` — unconverted ASP function syntax, fixed to `$rsMessages->fields['ID']`

### 34. Fix SessionSecurity ini_set with active session (2026-02-12)

- **File**: `profiel/app/SessionSecurity.php`
- **Description**: `initSecureSession()` called `ini_set()` for session settings unconditionally. When the site bootstrap already started the session, this threw "Session ini settings cannot be changed when a session is active".
- **Change**: Wrapped session ini_set calls in `if (session_status() === PHP_SESSION_NONE)` guard.

### 35. Enable error display for non-production environments (2026-02-12)

- **File**: `_bootstrap.php`
- **Description**: PHP `display_errors` was Off globally (php.ini), causing errors to silently produce empty pages. Added error display in bootstrap for development/test/local environments.
- **Change**: After environment detection, set `error_reporting(E_ALL)` and `ini_set('display_errors', '1')` for all environments except Production (P).

### 36. Fix INTOPLID → $intOplID in utils.inc (2026-02-14)

- **File**: `utils.inc`
- **Description**: 8 occurrences of `INTOPLID` (function parameter without `$` prefix) in lines 812-862. VBScript is case-insensitive and doesn't use `$`; the converter missed the `$` prefix.
- **Change**: Bulk replaced `INTOPLID` → `$intOplID`
- **Converter fix**: Created `postprocess_constant_variables.py` — parses function signatures and detects parameter names used without `$` prefix in function bodies.

### 37. Fix SQL::addInClause null argument in index_deelnemers.inc (2026-02-14)

- **File**: `index_deelnemers.inc`
- **Description**: `$userOpleidingen` can be null when passed to `SQL::addInClause()` which requires a string.
- **Change**: Added `$userOpleidingen = $userOpleidingen ?? '';` before the call.

### 38. Fix Login::$name → Login::name() in two files (2026-02-14)

- **Files**: `formulier_dispensatie.php:66`, `formulier_voordracht_praktijkopleider.php:58`
- **Description**: ASP `MyLogin.Name` was a property getter. Converter produced `Login::$name` but in PHP it's a static method `Login::name()`.
- **Change**: `Login::$name` → `Login::name()`

### 39. Add Cookie class_alias to bootstrap (2026-02-14)

- **File**: `_bootstrap.php`
- **Description**: Missing `class_alias` for Cookie class. Files like `inventarisatie/` that include `utils.inc` call `Cookie::get()` but the `Cookie` alias wasn't registered.
- **Change**: Added `class_alias('\App\Library\Cookie', 'Cookie')` to the existing class_alias block.

### 40. Fix SELECTIE_BEDRIJF / TOON_DEELNEMERS in inventarisatie (2026-02-14)

- **Files**: `inventarisatie.inc`, `inventarisatie/index.php`
- **Description**: Variables used as constants (without `$` prefix) and missing `global` declarations.
- **Changes**:
  1. `inventarisatie.inc`: Added `$TOON_DEELNEMERS, $SELECTIE_BEDRIJF` to `global` declarations in `fetchErkJSON()` and `SelectBedrijf()`. Changed bare `TOON_DEELNEMERS` → `$TOON_DEELNEMERS` and `SELECTIE_BEDRIJF` → `$SELECTIE_BEDRIJF`.
  2. `inventarisatie/index.php`: Fixed `<?php if/else/endif ?>` tags that were embedded inside a PHP `echo '...'` string (lines 83-681). Converted to PHP string concatenation with ternary operators: `' . ($TOON_DEELNEMERS ? '...' : '...') . '`. Also fixed `<?php echo $CartaID_Gebruiker; ?>` → `' . ($CartaID_Gebruiker) . '`, `<?php if (Login::isLoggedInAs()): ?>` → ternary, and `<?php if ($strFormError...): ?>` → ternary. Fixed unescaped single quotes (`'table'` → `\'table\'`, `'jaar'` → `\'jaar\'`).

### 41. Fix .asp references and confirm/alert in general.js (2026-02-14)

- **File**: `general.js`
- **Description**: ~70+ `.asp` file references not converted to `.php`. Also 6 `confirm()` and 9 `alert()` calls not converted to `libConfirm()`/`libAlert()`.
- **Changes**:
  1. Bulk replaced all `.asp?` → `.php?` and remaining `.asp` references
  2. Converted `confirm(` → `await libConfirm(` in 6 functions (made them `async`)
  3. Converted `alert(` → `libAlert(` (9 occurrences)
  4. Used `.then()` pattern for inline JS string confirm in `toon_bericht_intern`

### 42. Bulk convert confirm/alert → libConfirm/libAlert across front-end (2026-02-14)

- **Files**: ~40+ PHP/INC/JS files across `site/` and `site/cma/`
- **Description**: Remaining `confirm()` and `alert()` calls converted to Promise-based `libConfirm()`/`libAlert()` web component dialogs.
- **Patterns applied**:
  1. Simple `alert(` in JS → `libAlert(` (fire-and-forget)
  2. `alert(` in `javascript:` href → `javascript:void(libAlert(...))`
  3. `confirm(` in regular JS functions → `await libConfirm(` + function made `async`
  4. `confirm(` in `javascript:` href → `javascript:void(libConfirm(...).then(function(ok){if(ok){...}}))`
  5. `onsubmit="return fn()"` with async fn → `event.preventDefault()` + `fn().then()`
  6. `onclick="return fn()"` on submit buttons → button changed to `type="button"` + `.then()` pattern
  7. `confirm(` inside callbacks/Promises → `.then()` pattern

### 43. Fix stray CartaID_Gebruiker() function call (2026-02-14)

- **File**: `formulier_voordracht_praktijkopleider.php`
- **Description**: Line 266 had a bare `CartaID_Gebruiker();` — a conversion artifact. In VBScript this was a variable reference, not a function call.
- **Change**: Removed the stray line.

### 44. Fix undefined $MagBeoordelen in formulier_dispensatie.php (2026-02-14)

- **File**: `formulier_dispensatie.php`
- **Description**: `$MagBeoordelen` is only set inside an `if` block but used unconditionally on line 116. When the condition is false, the variable is undefined.
- **Change**: `if (!$MagBeoordelen)` → `if (!($MagBeoordelen ?? false))`

### 45. Fix missing WriteHeadAndTitle() in two front-end forms (2026-02-14)

- **Files**: `formulier_voordracht_praktijkopleider.php`, `formulier_dispensatie.php`
- **Description**: Both forms require `WriteDocType()`, `WriteHeadAndTitle()`, and `WriteTop()` for proper page rendering. These were missing, causing blank/broken pages.
- **Change**: Added `WriteDocType()`, `WriteHeadAndTitle($pageTitle)`, `WriteTop()` calls near the top of each file.

### 46. Fix GetPraktijkopleiders array access → function call (2026-02-14)

- **File**: `formulier_dispensatie.php`
- **Description**: `$GetPraktijkopleiders[$popl]` was array access syntax but `GetPraktijkopleiders()` is a function defined in `utils.inc`.
- **Change**: `$GetPraktijkopleiders[$popl]` → `GetPraktijkopleiders($popl)`

### 47. Add null guard to GetPraktijkopleiders() in utils.inc (2026-02-14)

- **File**: `utils.inc`
- **Description**: `GetPraktijkopleiders()` could receive null `$intOplID`, causing SQL errors.
- **Change**: Added `if (empty($intOplID)) return '';` guard at the start of the function.

### 48. Fix byref Database::openRS patterns in voordracht form (2026-02-14)

- **File**: `formulier_voordracht_praktijkopleider.php`
- **Description**: Three instances of ASP-style byref pattern `Database::openRS($rs, $sql, ...)` where the first arg was the output variable. PHP doesn't support this pattern.
- **Change**: `Database::openRS($rs, $sql, 'data')` → `$rs = Database::openRS($sql, 'data')`

### 49. Fix missing fetch() in inventarisatie.inc while loop (2026-02-14)

- **File**: `inventarisatie.inc`
- **Description**: `Inventarisatie_Getopleidingsplaatsen_perTermijn()` had a `while (!$rsAll->EOF)` loop without `$rsAll_current_row = $rsAll->fetch()` at the end, causing an infinite loop (30s timeout).
- **Change**: Added `$rsAll_current_row = $rsAll->fetch(PDO::FETCH_ASSOC);` at end of the while loop body.

### 50. Reconstruct missing form HTML in voordracht form (2026-02-14)

- **File**: `formulier_voordracht_praktijkopleider.php`
- **Description**: Lines 349-487 (after the 3 combo boxes) were all no-op expressions like `($blnReadOnly ? 'disabled' : '');` where the converter lost all HTML output. The original ASP had full HTML forms for 4 tabs. Reconstructed from the ASP source (`ASPCode/formulier_voordracht_praktijkopleider.asp` lines 700-1241).
- **Changes**:
  1. **Tab 1**: Radio buttons (praktijkopleider/werkbegeleider), vervangt section with combo boxes + checkboxes, ingangsdatum field, praktijkopleider details (naam, functie, locatie, email, telefoons, CV upload)
  2. **Tab 2**: Education sections (GZ, PT, KP, OG) with criteria checkboxes, dispensatie textareas, BIG registration numbers
  3. **Tab 3**: Deelnemers textarea
  4. **Tab 4**: Akkoord section (naam, functie, datum, verstuur button)

### 51. Fix bReadOnly → bReadOnly() in inventarisatie.inc (2026-02-14)

- **File**: `inventarisatie.inc`
- **Description**: 5 occurrences of bare `bReadOnly` (constant reference) at lines 470, 472, 476, 477, 502. The function `bReadOnly()` is defined at line 368 and must be called with parentheses.
- **Changes**:
  1. Fixed `bReadOnly` → `bReadOnly()` in all 5 occurrences
  2. Removed duplicated code block (lines 476-477 were exact duplicates of 470-472, a converter artifact)

### 52. Fix ColumnMajorArray access order in inventarisatie.inc (2026-02-14)

- **File**: `inventarisatie.inc`
- **Description**: `$arrPeriodes[$PeriodeCnt][0]` used row-major access (row index, column index) but `Cache::retrieve()` returns a `ColumnMajorArray` that uses column-major access (column index, row index). When PeriodeCnt > 0, it accessed non-existent columns, returning null and causing SQL error: `tblbiginventarisatie.jaar = )`.
- **Change**: `$arrPeriodes[$PeriodeCnt][0]` → `$arrPeriodes[0][$PeriodeCnt]` (column 0 = jaar, row = PeriodeCnt)

### 53. Add closing HTML structure and script wrapper to voordracht form (2026-02-14)

- **File**: `formulier_voordracht_praktijkopleider.php`
- **Description**: After the tab content (tabs-5), the closing `</div>`, `</form>`, `</div>` tags and the `<script>$(document).ready(function() {` wrapper were missing. The converter lost the HTML structure and script wrapper, leaving data-filling code as raw PHP output.
- **Changes**:
  1. Added closing `</div><!-- /tabbed_form -->`, `</form>`, `</div><!-- /kader -->` after tabs-5
  2. Added `<script>$(document).ready(function() {` before data-filling code
  3. Ported all JS functions from ASP original (lines 1328-1760): `set_verv_po`, `sync_deelnemers`, `check_deelnemers`, `set_dispensatie`, `set_dispensatie_per_type`, `uploader_setfile`, `check_form`, `set_vervolgvraag`, postcode functions, tab navigation, FineUploader init, textarea autoGrow
  4. Added closing `});` and `</script>`

### 54. Add style block, script functions, and script wrapper to dispensatie form (2026-02-14)

- **File**: `formulier_dispensatie.php`
- **Description**: The `<style>` block (tabbed_form styling, sorttable, fine-uploader), initial `<script>` block (prepare_table, my_form_valid functions), and the `$(document).ready()` wrapper for data-filling and JS init code were all missing.
- **Changes**:
  1. Added `<style>` block after `WriteHeadAndTitle()` with CSS from ASP lines 45-82
  2. Added `<script>` with `prepare_table()` and `my_form_valid()` functions from ASP lines 83-117
  3. Added `<script>$(document).ready(function() {` before data-filling code
  4. Ported all JS functions from ASP original: `save_deelnemerids`, `sync_deelnemers`, `uploader_setfile`, `check_form`, `set_vervolgvraag`, `set_switch`, `zet_betreft`, tab navigation, FineUploader init
  5. Moved `sPraktijkopleider` PHP echo inside `sync_deelnemers()` JS function (matching ASP structure)
  6. Added closing `});` and `</script>`

### 55. Fix inverted Request::postAll() condition in both forms (2026-02-14)

- **Files**: `formulier_voordracht_praktijkopleider.php`, `formulier_dispensatie.php`
- **Description**: The ASP code `if request.form.count=0 then` loads data on GET requests (no POST data). The converter produced `if (Request::postAll())` which inverts the logic — loading data when there IS POST data, and skipping it on GET.
- **Change**: `if (Request::postAll())` → `if (empty(Request::postAll()))` in both files

### 56. Fix SQL GUID quoting (ODBC escape sequence) in both forms (2026-02-14)

- **Files**: `formulier_voordracht_praktijkopleider.php`, `formulier_dispensatie.php`
- **Description**: `WHERE code={d922295e-...}` — ODBC interprets `{...}` as escape sequences, stripping the opening brace. Error: `Syntaxisfout (operator ontbreekt) in query-expressie code = d922295e-...}`.
- **Change**: `'WHERE code=' . $sRecordID` → `"WHERE code='" . $sRecordID . "'"` — quoting the GUID string prevents ODBC escape sequence interpretation.

### 57. Fix undefined $sDeelnameids in formulier_dispensatie.php (2026-02-14)

- **File**: `formulier_dispensatie.php`
- **Description**: `$sDeelnameids` was used at line 272 (inside loop) and line 279 (outside loop) without initialization. The variable was only initialized inside the `if ($fkDispensatie != '')` block, but line 279 uses it unconditionally.
- **Change**: Moved `$sDeelnameids = '';` initialization before the `if ($fkDispensatie != '')` block.

### 58. Fix Cypress test TypeError false positive (2026-02-14)

- **File**: `cma/cypress/e2e/forms/frontend-forms.cy.js`
- **Description**: The `PHP_ERROR_PATTERNS` array included `'TypeError'` which was matched case-insensitively, causing false positives on the JS uploader config text `messages: { typeError: "..." }`.
- **Change**: Moved `'TypeError'` to a separate `PHP_ERROR_PATTERNS_CASE_SENSITIVE` array that matches without the `'i'` flag. This matches PHP's `TypeError` (capital T) but not JS property `typeError` (lowercase t).

### 59. Reconstruct complete WriteForm() HTML in dispensatie form (2026-02-14)

- **File**: `formulier_dispensatie.php`
- **Description**: The `WriteForm()` function was largely hollow — the converter lost all HTML template output, leaving only PHP logic with no-op expressions like `($blnReadOnly ? 'disabled' : '');` where full HTML should be. The form showed only a textarea instead of the complete multi-tab dispensatie form.
- **Changes**:
  1. Added `<div class="default_content">`, `<form method="POST" name="Main" id="Main">`, hidden fields (`code`, `deelname_ids`, `actie`)
  2. Added `<div id="tabbed_form">` with `<ul>` tab navigation (4 tabs: Algemene gegevens, Deelnemers, Historie, Beoordeling)
  3. **Tab 1** (Algemene gegevens): betreft_aanvraag radio buttons (Supervisor/Werkbegeleider/Leertherapeut), institution name, voornaam/achternaam text fields, CV upload with FineUploader, opleiding checkboxes (GZ/PT/KP/KNP/OG) with domain sublists, sessies count field, situatie checkboxes, toelichting/motivatie/opmerkingen textareas
  4. **Tab 2** (Deelnemers): deelnemerslijst div populated via AJAX, Verstuur/Volgende button
  5. **Tab 3** (Historie): LibTable with previous dispensatie requests
  6. **Tab 4** (Beoordeling): Per-training fieldsets with akkoord radio buttons, rejection reason textarea, duration radio buttons, opmerkingen textarea, hidden date field
  7. Fixed `WriteList()` function — `echo` line was completely mangled by the converter (concatenation of ASP & operators and PHP . operators). Rebuilt from ASP original to output checkbox + label HTML for each list item, with "Anders namelijk" text input support.
  8. Fixed operator precedence in `if ($sPraktInstCartaID == '' && ...)` — added parentheses around the OR conditions
  9. Fixed `!$LoginType == USER_TYPE_ASSISTENT` → `$LoginType != USER_TYPE_ASSISTENT` (operator precedence bug)
  10. Fixed SQL GUID quoting in `$sqlInstelling` query (same ODBC escape issue as change #56)

### 60. Add Cypress tests for dispensatie form fields (2026-02-14)

- **File**: `cma/cypress/e2e/forms/frontend-forms.cy.js`
- **Description**: Added 8 specific field tests for the dispensatie form (previously only had 3 generic tests).
- **Tests added**:
  1. `should have the main form element` — checks for `form[name="Main"]`
  2. `should have the tabbed form structure` — checks for `#tabbed_form`, `ul.ui-tabs-nav`, tab `<li>` elements
  3. `should have betreft_aanvraag radio buttons` — 3 radio buttons (Supervisor, Werkbegeleider, Leertherapeut)
  4. `should have voornaam and achternaam text fields`
  5. `should have CV upload section` — `input[name="cv"]` and `.fine-uploader_docs[data-field="cv"]`
  6. `should have opleiding checkboxes for training types` — GZ, PT, KP, KNP, OG
  7. `should have situatie toelichting and motivatie textareas`
  8. `should have deelnemerslijst section in tabs-2`
  9. `should have hidden fields for code and deelname_ids`

### 61. Fix unconverted ASP code in TakenScherm() (2026-02-19)

- **File**: `taken.inc`
- **Description**: The `TakenScherm()` function (lines 248-272) contained raw ASP/VBScript code inside `<% %>` blocks that was never converted to PHP. The entire block was inside a PHP `echo '...'` string, so it rendered as visible text on the page at `/?pageaction=taken`. Also, `$parActiveTab` and `$parActiveTaak` (defined at file scope on lines 12-13) were inaccessible inside the function due to missing `global` declarations.
- **Changes**:
  1. Converted ASP `<% if parActiveTab&""="" then %>` → PHP `if ($parActiveTab == '')`
  2. Converted `left(parActiveTaak,1)<>"{"` → `substr($parActiveTaak, 0, 1) != '{'`
  3. Converted `parActiveTaak = "{" & parActiveTaak & "}"` → PHP string concatenation
  4. Converted `dim sUrl : sUrl = TaakGetUrlPerGuid(parActiveTaak)` → `$sUrl = TaakGetUrlPerGuid($parActiveTaak)`
  5. Converted `echo "taak_bekijk('"&sUrl&"');"` (VBScript Response.Write) → PHP `echo` with `addslashes()` for XSS safety
  6. Added `global $parActiveTab, $parActiveTaak;` to function declaration

### 62. Fix missing global declarations in taak_getlijst.php (2026-02-19)

- **File**: `taak_getlijst.php`
- **Description**: `$LoginType`, `$userOpleidingen`, `$assistentID`, `$deelnemerID`, `$docentID`, and `$praktijkopleiderID` are set by `Login_InitVars()` in `class_login.inc` as global variables. `taak_getlijst.php` used them without `global` declarations, causing "Undefined variable" errors (e.g., `$userOpleidingen` on line 40, `$docentID` on line 82).
- **Change**: Added `global $LoginType, $userOpleidingen, $assistentID, $deelnemerID, $docentID, $praktijkopleiderID;` after the require statements.

### 63. Normalize $DocentID/$DeelnemerID case to lowercase (2026-02-19)

- **Files**: `module/login/class_login.inc`, `utils.inc`, `competentie.inc`, `opleiding_vrijstelling.inc`, `opleiding_factuurgegevens.inc`, `dig_presentie.php`, `index_homepage.inc`, `module/calendar/class_calendar.inc`, `taak_getlijst.php`
- **Description**: ASP is case-insensitive, so `DocentID` and `docentID` were the same variable. After PHP conversion, `Login_InitVars()` set `$deelnemerID`/`$docentID` (lowercase) but many consumer files used `$DeelnemerID`/`$DocentID` (uppercase). This caused empty values in SQL queries like `fkdocent = AND isDeleted = 0` (missing value after `=`).
- **Change**: Bulk replaced all `$DocentID` → `$docentID` and `$DeelnemerID` → `$deelnemerID` across all PHP/INC files to match the lowercase convention used by `$assistentID`, `$praktijkopleiderID`, etc. Approximately 50 occurrences across 9+ files.

### 64. Fix Database::getIds() array return used as string in utils.inc (2026-02-19)

- **File**: `utils.inc`
- **Description**: `Differentiaties_Per_Gebruiker()` returned `Database::getIds()` directly, which returns an `array`. The caller on line 2299 concatenated the result into a SQL `IN (...)` clause, causing "Array to string conversion" error.
- **Change**: `return Database::getIds('data', $SQLDiffPerGebr)` → `return implode(',', Database::getIds('data', $SQLDiffPerGebr))`

### 65. Fix missing fetch() in rapportage_voordrachten_po.inc while loop (2026-02-19)

- **File**: `rapportage_voordrachten_po.inc`
- **Description**: The `while ($rsdata_current_row !== false)` loop (line 41-62) was missing `$rsdata_current_row = $rsdata->fetch(PDO::FETCH_ASSOC)` at the end of the loop body, causing an infinite loop and memory exhaustion (134MB limit).
- **Change**: Added `$rsdata_current_row = $rsdata->fetch(PDO::FETCH_ASSOC);` before the closing `}` of the while loop.

### 66. Fix Login::StoreValue/RetrieveValue losing data between requests (2026-02-19)

- **File**: `module/login/class_login.inc`
- **Description**: In ASP, `Application("key")` persists across all requests (server-global storage). The PHP `Application::set/get` only stores in `$GLOBALS['Application']` which is per-request. So `Login::StoreValue()` (called during login to store `COOKIE_ALLEOPL`, `COOKIE_TYPE`, role IDs, etc.) lost all data on the next page load. `Login::RetrieveValue()` returned `''` for everything, causing `$UserAlleOpleidingen`, `$UserOpleidingen`, `$LoginType`, and all role IDs to be empty after login. This meant the "opleidingen" and "deelnemers" menu items were never shown (they require `$UserOpleidingen != ''`).
- **Changes**:
  1. `StoreValue()`: Now writes to both `$_SESSION` (persists across requests) and `Application` (same-request access)
  2. `RetrieveValue()`: Reads from `$_SESSION` first, falls back to `Application`
  3. `Logout()`: Added cleanup of `$_SESSION` keys matching `login_setting_` prefix to prevent stale session data

### 67. Fix library/500.php multiple undefined methods (2026-02-20)

- **File**: `library/500.php`
- **Description**: The 500 error handler page (converted from ASP) crashed with multiple errors: `Request::queryString()` (doesn't exist), `lib_URLDecode()` (broken ASP conversion of `lib_url.inc`), `Error::formatMessage()` (non-existent), and `lib_error_verbose()` (non-existent).
- **Changes**:
  1. `Request::queryString()` → `$_SERVER['QUERY_STRING'] ?? ''` (2 occurrences)
  2. `lib_URLDecode($strPage)` → `urldecode($strPage)` (native PHP equivalent)
  3. Removed `Error::formatMessage($strError)` call (legacy ASP method, no PHP equivalent needed)
  4. `lib_error_verbose()` → environment check `in_array(Application::get('omgeving', 'P'), ['L', 'O', 'T'])`

### 68. Fix library/404.php broken includes and variable scope (2026-02-20)

- **File**: `library/404.php`
- **Description**: Three issues: (1) `require_once __DIR__ . '/../CMA/include/repconn.inc'` — file doesn't exist (database connections now handled by bootstrap), (2) `Request::queryAll()` returns an array but was used as a string, (3) `404.inc` was included at file level but references `$sNotFoundUrl` which is only defined inside `main()`.
- **Changes**:
  1. Removed non-existent `repconn.inc` require
  2. `Request::queryAll()` → `$_SERVER['QUERY_STRING'] ?? ''`
  3. Moved `include 404.inc` from file level to inside `main()` after `$sNotFoundUrl` is defined

### 69. Fix profiel/ relative path resolution (bootstrap wrapper issue) (2026-02-20)

- **Files**: ~20 PHP files in `profiel/` directory
- **Description**: The IIS bootstrap wrapper (`_bootstrap_wrapper.php`) runs from the site root, so relative paths like `require_once 'app/EnvLoader.php'` and `include 'templates/header.php'` resolved against the site root instead of the `profiel/` directory. This caused "file not found" errors for all profiel pages.
- **Changes** (all relative paths → `__DIR__`-based absolute paths):
  1. `profiel/audit-logs.php`: Fixed `vendor/autoload.php`, `app/EnvLoader.php`, `app/Logger.php`, `templates/*.html.php`
  2. `profiel/profile-create.php`: Fixed `templates/header.php`, `templates/footer.php`, `templates/profile-create.html.php`
  3. `profiel/reset-password-request.php`: Fixed `templates/header.php`, `templates/footer.php`, `templates/reset-password-request.html.php`
  4. `profiel/registration-success.php`: Added `vendor/autoload.php` require, fixed `app/EnvLoader.php`, `templates/registration-success.html.php`
  5. `profiel/sso-callback.php`: Fixed `templates/header.php`, `templates/footer.php`
  6. `profiel/sso-login.php`: Fixed 4 template includes
  7. `profiel/index.php`: Fixed `templates/login.html.php`
  8. `profiel/logout.php`: Replaced 3 manual `app/*.php` requires with `vendor/autoload.php`
  9. `profiel/reset-password.php`: Fixed `templates/reset-password.html.php`
  10. `profiel/profile-edit.php`: Fixed `templates/profile-edit.html.php`
  11. `profiel/registration-api-main.php`: Fixed 3 `app/*.php` requires
  12. `profiel/registration-crm-portal-api.php`: Fixed 6 `app/*.php` requires
  13. `profiel/voordracht-api.php`: Fixed 3 `app/*.php` requires
  14. `profiel/example-email-token-usage.php`: Fixed 2 `app/*.php` requires
  15. `profiel/mailjet-test.php`: Fixed 2 `app/*.php` requires
  16. `profiel/templates/profile-edit.html.php`: Fixed `templates/header.php` → `__DIR__ . '/header.php'`
  17. `profiel/templates/profile-create.html.php`: Fixed `templates/header.php` → `__DIR__ . '/header.php'`
  18. `profiel/app/EmailSender.php`: Fixed `templates/email-template.html` → `dirname(__DIR__) . '/templates/...'`

### 70. Fix profiel/ duplicate session_start() warnings (2026-02-20)

- **Files**: `profiel/registration-form.php`, `profiel/audit-logs.php`
- **Description**: These files called `session_start()` unconditionally, but the site bootstrap already starts the session. This caused "session_start(): Ignoring session_start() because a session is already active" warnings.
- **Change**: Wrapped `session_start()` calls with `if (session_status() === PHP_SESSION_NONE)` guard.

### 71. Fix profiel/ missing use statements and null checks (2026-02-20)

- **Files**: `profiel/audit-logs.php`, `profiel/registration-success.php`, `profiel/logout.php`, `profiel/reset-password-request.php`, `profiel/templates/login.html.php`
- **Description**: Several files used `App\` namespaced classes without `use` statements, and one file had an unguarded `$_COOKIE` access.
- **Changes**:
  1. `audit-logs.php`: Added `use App\EnvLoader; use App\Logger;`
  2. `registration-success.php`: Added `use App\EnvLoader;`
  3. `logout.php`: Added `use App\EnvLoader; use App\RinoHttpClient; use App\AuthClient;` and switched to vendor autoloader
  4. `reset-password-request.php`: `$_COOKIE["email"]` → `$_COOKIE["email"] ?? ''`
  5. `templates/login.html.php`: `EnvLoader::get(...)` → `\App\EnvLoader::get(...)` (fully qualified, since `use` statements don't carry into included files)

### 72. Reinstall incomplete league/oauth2-client package (2026-02-20)

- **File**: `profiel/vendor/league/oauth2-client/`
- **Description**: The `league/oauth2-client` composer package was incomplete — only had `README.md`, `LICENSE`, `composer.json`, and `phpunit.xml.dist` but no `src/` directory. This caused `Class "League\OAuth2\Client\Provider\GenericProvider" not found` in `sso-callback.php`.
- **Change**: Ran `composer reinstall league/oauth2-client` to restore the complete package with source files.
