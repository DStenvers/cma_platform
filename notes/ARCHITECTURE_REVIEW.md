# CMA Architectural Review Report

**Date:** 2026-01-30
**Status:** CRITICAL - System not production-ready
**Reviewed by:** Claude Code

---

## Executive Summary

The CMA codebase has fundamental architectural problems that cause recurring bugs:

1. **Multiple conflicting code paths** for the same operations
2. **Monitoring/audit logging is broken** by design
3. **Test coverage is critically low** (6% of forms)
4. **Massive code duplication** causes inconsistent behavior

Until these issues are resolved, CRUD operations will continue to fail intermittently and audit logging will remain incomplete.

---

## Critical Finding #1: Monitoring is Disabled or Broken

### Root Cause
The monitoring system has **three separate failure points**:

#### 1.1 Monitoring May Be Disabled
```php
// FormDataProvider.php:1945
if (!Application::get('cma_monitoring', '')) {
    Logger::debug('logMonitoring: Monitoring disabled');
    return;  // SILENTLY EXITS - NO MONITORING
}
```
**If `cma_monitoring` is not set in application config, ALL monitoring is silently skipped.**

#### 1.2 Changelog Built Client-Side (Fragile)
The changelog (what changed) is built in JavaScript (`form-controller.js:8630-8845`) and passed via hidden form fields:
- If JavaScript errors occur, changelog is empty
- If form fields don't have `data-original-value`, no changes are detected
- Complex controls (custom renderers, rights-matrix) may not be tracked

#### 1.3 Dual Code Paths for Monitoring
| Path | File | Monitoring | Notes |
|------|------|------------|-------|
| Modern JSON Forms | FormDataProvider.php:1071 | `logMonitoring()` | Uses changelog from POST |
| Legacy Forms | detailsRep_post.php:611 | Inline SQL INSERT | Duplicate code, different format |
| RecordService | RecordService.php | **NONE** | Completely missing |

### Impact
- Records are modified but no audit trail exists
- Security compliance failure
- Cannot track who changed what

### Recommended Fix
```
Priority: P0 (Critical)
Effort: Medium
Side-effects: Low (adds missing functionality)

1. Verify cma_monitoring setting is enabled in production config
2. Add monitoring to RecordService.save() and RecordService.delete()
3. Move changelog generation to server-side (compare before/after values)
4. Remove duplicate monitoring code from detailsRep_post.php
```

---

## Critical Finding #2: Multiple CRUD Code Paths

### The Problem
There are **three different ways** data can be saved:

```
┌─────────────────────────────────────────────────────────────────┐
│                     CRUD Entry Points                            │
├─────────────────┬─────────────────┬─────────────────────────────┤
│ form_api.php    │ detailsRep_post │ Direct RecordService        │
│ (Modern)        │ (Legacy)        │ (Internal)                  │
├─────────────────┼─────────────────┼─────────────────────────────┤
│ ↓               │ ↓               │ ↓                           │
│ FormDataProvider│ Inline SQL      │ RecordService               │
│ ↓               │ ↓               │ ↓                           │
│ logMonitoring() │ Inline INSERT   │ **NO MONITORING**           │
│ ✓               │ ✓ (duplicate)   │ ✗                           │
└─────────────────┴─────────────────┴─────────────────────────────┘
```

### Specific Issues

| Service | Calls Monitoring | Used By |
|---------|-----------------|---------|
| FormDataProvider::saveJsonFormRecord | YES | form_api.php |
| FormDataProvider::deleteJsonFormRecord | YES | form_api.php |
| ConfigFormService::saveRecord | NO (relies on caller) | FormDataProvider |
| ConfigFormService::deleteRecord | NO (relies on caller) | FormDataProvider |
| RecordService::save | **NO** | Unknown - legacy |
| RecordService::delete | **NO** | Unknown - legacy |

### Impact
- Same operation may or may not be logged depending on which code path is triggered
- Database changes without corresponding audit records
- Inconsistent error handling between paths

### Recommended Fix
```
Priority: P0 (Critical)
Effort: High
Side-effects: Medium (need to verify all callers)

1. Audit all usages of RecordService - determine if still needed
2. Add monitoring calls to RecordService.save() and delete()
3. Migrate detailsRep_post.php to use FormDataProvider
4. Create single entry point for all CRUD operations
```

---

## Critical Finding #3: Test Coverage is Critically Low

### Statistics

| Metric | Value | Rating |
|--------|-------|--------|
| Total forms | 125 | - |
| Forms with CRUD tests | 8 | **6%** |
| CRUD operations tested | 3 forms | **CRITICAL** |
| Monitoring tests | Basic only | **INCOMPLETE** |
| Error handling tests | Marked "implementation specific" | **NOT VERIFIED** |
| Inline editing tests | **DISABLED** | **BLOCKED** |

### Forms with Tests vs Without

**WITH Tests (8):**
- users, groups, opleidingen (basic CRUD)
- contentblocks, cmamonitoring, _menus (navigation)
- locaties, rooster (partial)

**WITHOUT Tests (117+):**
- deelnemers, docenten, praktijkopleiders, supervisoren
- werkbegeleiders, toetsing, evaluatie_template
- afspraak, rooster, taken, verklaringen
- vrijstellingaanvragen, dispensatie
- All subforms and relationship tables

### Critical Gaps

1. **Inline Editing**: Tests are DISABLED in cypress
2. **Error Handling**: Tests exist but marked "implementation specific" - not actually verified
3. **Cascade Delete**: No tests for FK constraint handling
4. **Concurrent Edits**: No tests for conflict handling
5. **Bulk Operations**: No tests
6. **Security**: No XSS, SQL injection, or CSRF tests

### Recommended Fix
```
Priority: P1 (High)
Effort: Very High
Side-effects: Low (test-only changes)

1. Enable inline editing tests and fix underlying bugs
2. Add CRUD tests for top 20 most-used forms
3. Replace "implementation specific" placeholders with real assertions
4. Add security tests (XSS, SQL injection)
5. Add cascade delete/FK constraint tests
```

---

## Critical Finding #4: Massive Code Duplication

### Duplicate Helper Functions

| Function | Locations | Risk |
|----------|-----------|------|
| `toBool()` | FormDataProvider, FormTemplate, ListServiceHelper, ListService | Different behavior possible |
| `controlTypeToFieldType()` | JsonFormService, ListServiceHelper, ListService | Inconsistent type mapping |
| `parseSearchDate()` | ListServiceHelper, ListService | Date parsing differences |
| `formatIdForSql()` | RecordService, FormDataProvider | SQL injection risk |

### Duplicate Column Building Logic

Same 40+ line block appears in:
- JsonFormService.php (4 locations: lines 61-176, 728-823, 1434-1469, 1650-1683)
- TableService.php
- FormDataProvider.php

Changes to column logic must be made in **6+ places** or behavior diverges.

### Duplicate Row Rendering

Table cell rendering logic duplicated in:
- JsonFormService::getTableHtml()
- JsonFormService::getRowHtml()
- JsonFormService::getJsonConfigTableHtml()
- JsonFormService::getJsonConfigRowHtml()

### Impact
- Bug fixes must be applied multiple times
- Behavior differs between code paths
- Maintenance nightmare

### Recommended Fix
```
Priority: P1 (High)
Effort: High
Side-effects: High (need extensive testing)

1. Create ListServiceHelper as single source for column building
2. Consolidate toBool() to single implementation
3. Create TableRenderer class for row rendering
4. Remove all deprecated wrappers after migration
```

---

## High-Priority Bugs from prompts.md

These bugs were reported but appear unresolved:

### Still Open

| Bug | Status | Impact |
|-----|--------|--------|
| Inline editing not fully integrated | DISABLED | Major feature blocked |
| Select2 expanding below dialog | Not fixed | UX issue |
| Sidepanel z-index for subform records | Under investigation | Records not visible |
| Filtering reapplication after infinite scroll | Not implemented | Data inconsistency |
| Security groups - Rapporten and Rechten not saved | Reported multiple times | Data loss |
| CKEditor fields save procedure | Needs test | Potential data loss |
| Toolbar filter not Select2 | Investigating | UX degradation |

### Recurring Issues (Reported Multiple Times)

1. **Groups form - Rapporten/Rechten not saved** (prompts #7, #9, #11 in Jan 2026)
2. **Monitoring Notificatie field empty** (prompts #3, #4, #5 in Jan 2026)
3. **Stacking sidepanels** (reported multiple times)

---

## Architecture Recommendations

### Immediate Actions (P0)

1. **Enable cma_monitoring in production config**
   - Verify `Application::get('cma_monitoring')` returns truthy value
   - Side-effects: None if already enabled; enables audit logging if disabled

2. **Add monitoring to RecordService**
   - Add `logMonitoring()` calls to `save()` and `delete()`
   - Side-effects: More records in tblCMAMonitoring (desired)

3. **Move changelog generation server-side**
   - Compare record before/after in FormDataProvider
   - Side-effects: More reliable changelog; may change format slightly

### Short-term Actions (P1)

4. **Consolidate helper functions**
   - Single `toBool()`, `formatIdForSql()`, etc. in ListServiceHelper
   - Side-effects: Need to test all forms after change

5. **Enable and fix inline editing tests**
   - Inline editing is a core feature that's currently broken
   - Side-effects: May expose more bugs

6. **Fix Groups form save issues**
   - Rapporten/Rechten controls not saving is reported 3+ times
   - Side-effects: Need to verify rights-matrix custom renderer

### Long-term Actions (P2)

7. **Create unified CRUD service**
   - Single entry point for all database operations
   - Automatic monitoring, validation, caching
   - Side-effects: Major refactor, high testing requirement

8. **Migrate legacy forms**
   - Convert detailsRep_post.php to use FormDataProvider
   - Side-effects: Legacy form behavior may change

9. **Increase test coverage to 50%+**
   - Add CRUD tests for all major forms
   - Side-effects: Slow CI, but catches regressions

---

## Risk Assessment for Fixes

| Fix | Risk | Mitigation |
|-----|------|------------|
| Enable monitoring | Low | Check config, test one form |
| Add monitoring to RecordService | Low | Additive change only |
| Server-side changelog | Medium | Run parallel with client-side first |
| Consolidate helpers | Medium | Extensive unit tests |
| Fix inline editing | High | Feature is currently broken anyway |
| Migrate legacy forms | High | Run both paths in parallel |

---

## Conclusion

The CMA system has fundamental architectural issues that prevent production deployment:

1. **Audit logging is unreliable** - critical for compliance
2. **Multiple code paths** cause inconsistent behavior
3. **Test coverage is insufficient** to catch regressions
4. **Code duplication** makes maintenance error-prone

Recommended approach:
1. Fix monitoring first (P0) - it's the foundation for tracking issues
2. Consolidate code paths (P1) - reduces bug surface
3. Increase test coverage (P1) - prevents regressions
4. Then address individual bugs with confidence

Without these fixes, production deployment will result in continued data inconsistencies and audit failures.
