# Cypress Test Failure Fix Plan

**Generated:** 2026-01-30
**Test Run Summary:**
- Total Tests: 2226
- Passing: 1712 (77%)
- Failing: 168 (8%)
- Pending: 153 (7%)
- Skipped: 193 (9%)

---

## Issue Categories

### Category 1: Authentication/Session Issues (High Priority)
**Error Type:** `cy.type()` can only accept a string or number. You passed in: `undefined`

**Affected Files:**
- `forms/json-config-forms.cy.js`
- `forms/blockedit-ckeditor.cy.js`
- `components/cma-fold.cy.js`
- `components/cma-report-components.cy.js`

**Root Cause:**
The `auth.js` command file at line 15 is trying to type an undefined password value. The Cypress environment variables for authentication credentials are not properly set.

**Fix Plan:**
1. Check `cypress/support/commands/auth.js` line 15
2. Verify `cypress.env.json` has proper credentials:
   ```json
   {
     "CMA_USER": "admin",
     "CMA_PASSWORD": "yourpassword"
   }
   ```
3. Ensure `Cypress.env('CMA_PASSWORD')` returns a valid string

---

### Category 2: 404 Not Found Errors (High Priority)
**Error Type:** `cy.visit()` failed - 404: Not Found

**Affected Files:**
- `forms/inline-edit.cy.js` - visiting `/cma/cma/default.php` (double `/cma/`)
- `forms/report-designer-sql.cy.js` - visiting `/cma/cma/login.php`
- Multiple other test files

**Root Cause:**
Tests are constructing URLs with duplicate `/cma/` path segments.

**Fix Plan:**
1. Review and fix URL construction in affected test files
2. Check `cypress.config.js` for `baseUrl` setting - should be `http://172.29.208.1/cma`
3. Ensure tests use relative paths correctly:
   - WRONG: `cy.visit('/cma/default.php')`
   - CORRECT: `cy.visit('/default.php')` when baseUrl includes `/cma`

**Files to modify:**
- `cypress/e2e/forms/inline-edit.cy.js` - lines with `cy.visit('/cma/cma/...')`
- `cypress/e2e/forms/report-designer-sql.cy.js` - same issue

---

### Category 3: Shadow DOM Access Issues (Medium Priority)
**Error Type:** Expected the subject to host a shadow root, but never found it

**Affected Files:**
- `core/utility-functions.cy.js` (lib_alertbox tests)
- `components/lib-table.cy.js`
- `components/lib-datepicker.cy.js`

**Root Cause:**
Tests expecting Shadow DOM on components that either:
1. Don't use Shadow DOM anymore
2. Haven't rendered completely before assertion

**Fix Plan:**
1. For `lib_alertbox()` tests in `core/utility-functions.cy.js`:
   - Check if `lib-dialog` component uses Shadow DOM
   - If not, remove `.shadow()` calls from selectors
   - Add proper wait for dialog to render

2. For `lib-datepicker.cy.js`:
   - Verify component's Shadow DOM implementation
   - Add `{ includeShadowDom: true }` option or remove `.shadow()` calls

---

### Category 4: Missing UI Elements (Medium Priority)
**Error Type:** Expected to find element: `#nextStepBtn`, `.add-relationship-btn`, etc.

**Affected Files:**
- `forms/report-designer.cy.js` (47 failures)
- `components/cma-tree.cy.js` (7 failures)
- `components/lib-table.cy.js` (9 failures)

**Root Cause:**
UI elements are either:
1. Not rendered yet (timing issue)
2. Have different selectors than expected
3. Conditional rendering not met

**Fix Plan:**
For `forms/report-designer.cy.js`:
1. Check if `#nextStepBtn` exists - verify the actual button ID/class
2. Add `cy.wait()` or use `.should('exist')` with timeout
3. Review component state requirements before buttons appear

For `components/cma-tree.cy.js`:
1. Verify tree node selectors
2. Check if tree data is loaded before interacting

For `components/lib-table.cy.js`:
1. Verify filter dropdown selectors
2. Check boolean column filter implementation

---

### Category 5: Storybook/Fixture Issues (Low Priority)
**Error Type:** Expected to find element: `button:disabled`, `.btn:disabled`

**Affected File:**
- `core/styling.cy.js` (4 failures in "Disabled Button Hover State")

**Root Cause:**
The storybook page doesn't have disabled buttons as expected.

**Fix Plan:**
1. Add disabled button examples to `tools/storybook.php`
2. Or update tests to use a page that has disabled buttons
3. Example buttons to add:
   ```html
   <button class="btn btn-primary" disabled>Disabled Primary</button>
   <button class="btn" disabled>Disabled Default</button>
   ```

---

### Category 6: Mobile Layout Tests (Low Priority)
**Error Type:** Various mobile layout assertions failing

**Affected File:**
- `responsive/mobile-layout.cy.js` (multiple failures)

**Root Cause:**
Mobile layout CSS not matching expected values.

**Fix Plan:**
1. Check if `.hamburger-icon` or `.cma-mobile-menu-btn` exists
2. Verify `.c3_g` width at mobile viewport
3. Verify `.postcaption` display property
4. Update CSS or tests based on actual implementation

---

### Category 7: lib-search-input Component Issues (Medium Priority)
**Error Type:** `cy.clear()` failed because it requires a valid clearable element

**Affected Files:**
- `forms/infinite-scroll.cy.js`
- `forms/keyboard-shortcuts.cy.js`

**Root Cause:**
`lib-search-input` is a custom element and Cypress can't directly clear it.

**Fix Plan:**
1. Access the internal input element:
   ```javascript
   cy.get('lib-search-input')
     .shadow()
     .find('input')
     .clear()
   ```
2. Or use the component's API if available:
   ```javascript
   cy.get('lib-search-input').then($el => {
     $el[0].value = '';
   });
   ```

---

### Category 8: Focus/Selection Issues (Low Priority)
**Error Type:** expected `<lib-search-input#searchfor>` to be 'focused'

**Affected File:**
- `forms/keyboard-shortcuts.cy.js`

**Root Cause:**
Custom element doesn't receive focus the same way native inputs do.

**Fix Plan:**
1. Check if focus is on the internal input within shadow DOM
2. Update test:
   ```javascript
   cy.get('lib-search-input')
     .shadow()
     .find('input')
     .should('be.focused');
   ```

---

### Category 9: Tab/Loading State Issues (Low Priority)
**Error Type:** Expected `<span.tab-count.loading>` not to exist

**Affected Files:**
- `components/cma-tree.cy.js`
- `forms/report-designer.cy.js`

**Root Cause:**
Loading states persisting longer than expected.

**Fix Plan:**
1. Increase timeout for loading state to clear
2. Or check actual loading behavior and adjust expectations
3. Example:
   ```javascript
   cy.get('.tab-count.loading', { timeout: 15000 }).should('not.exist');
   ```

---

### Category 10: CRUD Operation Failures (High Priority)
**Error Type:** Various delete/save operation failures

**Affected Files:**
- `forms/fk-lookup.cy.js` (7 failures)
- `forms/groups.cy.js` (4 failures)
- `forms/users.cy.js` (3 failures)
- `integration/user-workflows.cy.js` (8 failures)

**Root Cause:**
- API responses not matching expectations
- UI not reflecting saved/deleted state
- Timing issues with async operations

**Fix Plan:**
1. Add proper API response handling
2. Wait for success messages after operations
3. Verify actual backend changes before continuing
4. Example pattern:
   ```javascript
   cy.intercept('POST', '/cma/form_api.php*').as('saveRequest');
   cy.get('.save-btn').click();
   cy.wait('@saveRequest').its('response.statusCode').should('eq', 200);
   cy.get('lib-message[type="success"]').should('be.visible');
   ```

---

### Category 11: Database Tools Tests (Medium Priority)
**Error Type:** Multiple failures in database tool tests

**Affected File:**
- `tools/database-tools.cy.js` (10 failures)

**Root Cause:**
- Database selector element differences
- Backup tool UI changes
- Missing input elements

**Fix Plan:**
1. Check `#preRestoreDescription` element existence
2. Verify database checkbox selectors
3. Update selectors to match current UI

---

### Category 12: Report Designer Complex Failures (High Priority)
**Multiple categories of failures in report designer**

**Fix Plan:**
1. **Mode Selection Dialog:**
   - Verify dialog renders on page load
   - Check for correct modal ID/class

2. **Table Filtering:**
   - Test search input in table list panel
   - Verify filter function works

3. **Navigation Steps:**
   - Check step button visibility logic
   - Verify step transition works

4. **Save Functionality:**
   - Test save dialog opens
   - Verify form validation
   - Check save API calls

5. **Schema Canvas:**
   - Test table position persistence
   - Verify relationship lines render

---

## Recommended Fix Order

### Phase 1: Critical Infrastructure (Fix first)
1. **Authentication setup** - Fix undefined password in auth commands
2. **URL construction** - Fix double `/cma/` paths in tests
3. **Base configuration** - Verify `cypress.config.js` settings

### Phase 2: High-Impact Fixes
4. **CRUD operations** - Fix save/delete test patterns
5. **Report designer** - Fix major navigation issues
6. **Shadow DOM access** - Standardize component testing approach

### Phase 3: Medium Priority
7. **lib-search-input** - Fix clear/focus methods
8. **Database tools** - Update selectors
9. **Component loading states** - Adjust timeouts

### Phase 4: Low Priority
10. **Mobile layout** - Update CSS expectations
11. **Storybook fixtures** - Add missing examples
12. **Focus behavior** - Custom element focus handling

---

## Files Requiring Changes

### Test Files
| File | Failures | Priority |
|------|----------|----------|
| `forms/report-designer.cy.js` | 47 | High |
| `forms/fk-lookup.cy.js` | 7 | High |
| `tools/database-tools.cy.js` | 10 | Medium |
| `components/lib-table.cy.js` | 9 | Medium |
| `integration/user-workflows.cy.js` | 8 | High |
| `components/cma-tree.cy.js` | 7 | Medium |
| `forms/combo-dropdown.cy.js` | 6 | Low |
| `forms/config-forms.cy.js` | 8 | Medium |

### Support/Configuration Files
| File | Issue |
|------|-------|
| `cypress/support/commands/auth.js` | Undefined password handling |
| `cypress.config.js` | Verify baseUrl |
| `cypress.env.json` | Missing/incorrect credentials |

### Application Files (if tests reveal real bugs)
| File | Potential Issue |
|------|-----------------|
| `tools/storybook.php` | Add disabled button examples |
| Various CSS | Mobile layout adjustments |

---

## Next Steps

1. Start with Phase 1 authentication and URL fixes
2. Run affected test suites individually to verify fixes
3. Progress through phases in order
4. Re-run full test suite after all fixes
5. Update any tests that are testing outdated behavior

