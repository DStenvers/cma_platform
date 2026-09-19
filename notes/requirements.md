# CMA Functional Requirements

This document contains functional requirements extracted from prompts.md, focusing on functionality only (CSS/styling changes excluded).

## 1. Form Management

### 1.1 Form Display
- REQ-F001: Forms must support JSON-based form definitions
- REQ-F002: Forms must support both tree and table view modes
- REQ-F003: Form list must support infinite scrolling for large datasets
- REQ-F004: Forms must support keyset pagination (WHERE ID > lastId)
- REQ-F005: Readonly forms must enforce read-only state (allowEdit: false)
- REQ-F006: Required fields must show visual indication (red left border on input)
- REQ-F007: Forms must support group separators (cma-groupbox)
- REQ-F008: Groupboxes must be collapsible/expandable
- REQ-F009: Groupboxes containing required fields must auto-expand when adding new record
- REQ-F010: Empty groupboxes should be hidden via CSS
- REQ-F011: Tree view with single item should auto-select that item

### 1.2 Form Fields
- REQ-F020: Comboboxes must show resolved text values (not IDs) in list view
- REQ-F021: Comboboxes must support lazy-loading for large datasets (>1000 records)
- REQ-F022: Comboboxes with >1000 records require 3+ character search
- REQ-F023: Date fields must use lib-datepicker component
- REQ-F024: Time fields must show HH:MM format (not 1899-12-30 prefix)
- REQ-F025: Boolean fields must use lib-switch component
- REQ-F026: File fields must show view/delete icons appropriately
- REQ-F027: Image fields must support crop functionality
- REQ-F028: Radio button groups must be supported
- REQ-F029: Checklist fields must support inline display mode
- REQ-F030: HTML fields must use CKEditor
- REQ-F031: Default values from JSON must be pre-filled on new records
- REQ-F032: Parent field values must be pre-filled from context

### 1.3 Form Actions
- REQ-F040: Save action must persist data to database
- REQ-F041: Delete action must show confirmation dialog
- REQ-F042: Copy action must duplicate record without ID
- REQ-F043: Cancel action must check for unsaved changes
- REQ-F044: "Je hebt niet-opgeslagen wijzigingen" must only show for actual changes
- REQ-F045: Save must log changes to tblCMAMonitoring
- REQ-F046: Delete must log all visible field values before deletion
- REQ-F047: Save must update table row without full page reload

### 1.4 Inline Editing
- REQ-F050: Table cells must support inline editing
- REQ-F051: Right-click on table row should activate inline editing
- REQ-F052: Tab key must navigate between inline edit fields
- REQ-F053: Switch fields must work in inline edit mode
- REQ-F054: Date fields must show datepicker in inline edit mode
- REQ-F055: Time fields must show time picker in inline edit mode
- REQ-F056: Inline editing must use aliased field names correctly
- REQ-F057: Inline editing buttons must hide when loading new form

## 2. Subforms

### 2.1 Subform Display
- REQ-S001: Subforms must appear as tabs below main form
- REQ-S002: Subform tabs must show record count
- REQ-S003: Empty subform tabs must show count as "."" initially
- REQ-S004: Subforms must filter data based on parentField
- REQ-S005: Subforms must show error if parentField is not configured
- REQ-S006: Empty subforms area should be hidden
- REQ-S007: Subform height should adapt based on main form content

### 2.2 Subform Actions
- REQ-S010: Subforms must support Add button (based on permissions)
- REQ-S011: Subforms must support inline editing
- REQ-S012: Subforms must support table filtering
- REQ-S013: Deleting subform record must only remove that ID from list
- REQ-S014: Adding subform record must preserve filter state
- REQ-S015: Changes in subform must trigger parent table refresh

## 3. Navigation

### 3.1 Menu System
- REQ-N001: Menu must support main items and sub-items
- REQ-N002: Collapsed menu must show sub-items on click
- REQ-N003: Menu must support access level restrictions (user/admin/developer)
- REQ-N004: Clicking "Vaak gebruikt" must activate corresponding menu item
- REQ-N005: Menu must support icons from Linearicons font

### 3.2 URL State
- REQ-N010: URL must reflect current form and record ID
- REQ-N011: Clean URLs must be supported (/cma/form/rooster/123)
- REQ-N012: URL must support up to 3 levels of nesting
- REQ-N013: Browser refresh must restore exact state
- REQ-N014: Sidepanel close must update URL correctly
- REQ-N015: Form names with spaces must be URL-encoded

### 3.3 Sidepanels
- REQ-N020: Detail forms must open in sidepanel by default
- REQ-N021: Multiple sidepanels must stack with offset
- REQ-N022: Sidepanels must have their own backdrop
- REQ-N023: Closing sidepanel must restore parent state
- REQ-N024: Sidepanels must appear below cma-header
- REQ-N025: On mobile, forms must open as popups instead of sidepanels

### 3.4 Breadcrumbs
- REQ-N030: Breadcrumb must show current navigation path
- REQ-N031: Breadcrumb must not update for subform navigation

## 4. Table Features

### 4.1 Table Display
- REQ-T001: Tables must support column filtering
- REQ-T002: Tables must support column sorting (A-Z, Z-A)
- REQ-T003: Filter icon must show active state when filter applied
- REQ-T004: Column headers must show tooltip if truncated
- REQ-T005: Column underscores must be replaced with spaces
- REQ-T006: Boolean fields must show as switches in table view
- REQ-T007: Date columns must support range filter (from/to)
- REQ-T008: Numeric columns must support range filter (from/to)
- REQ-T009: Filter checkboxes must be limited to 30 items max
- REQ-T010: Filter with >30 items must show search field instead

### 4.2 Table Actions
- REQ-T020: Row context menu must appear on three-dots click
- REQ-T021: Context menu must support Edit/Delete/Copy actions
- REQ-T022: Table must support field chooser (column selector)
- REQ-T023: Column visibility must be persisted in localStorage
- REQ-T024: Column order must be persisted in localStorage
- REQ-T025: Active row must be highlighted when sidepanel opens
- REQ-T026: Highlight must be reset when sidepanel closes

### 4.3 Infinite Scroll
- REQ-T030: Tables must support infinite scroll loading
- REQ-T031: Scroll must load next batch at 80% scroll position
- REQ-T032: Toolbar must show record count (Records 1-100 van 1500)
- REQ-T033: DOM count must match tracked count
- REQ-T034: Filter changes must reset scroll position
- REQ-T035: Double scroll events must be debounced

## 5. Search Features

### 5.1 Search Functionality
- REQ-X001: Search-as-you-type must be supported in toolbar
- REQ-X002: Search with >3 characters must highlight matches in tree/table
- REQ-X003: Search with single result must auto-select that record
- REQ-X004: Quick search must filter tree items
- REQ-X005: Search must work on multiple fields (quickSearchFields)

## 6. Reports

### 6.1 Report Display
- REQ-R001: Reports must support tree navigation (like forms)
- REQ-R002: Reports must show in details area
- REQ-R003: Reports must show spinner when loading >2 seconds
- REQ-R004: Reports must support edit links with proper form references
- REQ-R005: Report queries must support parameterized SQL

## 7. Tools

### 7.1 Tools Infrastructure
- REQ-U001: Tools must be accessible via /cma/tools/{toolname}
- REQ-U002: Tools must show in sidebar tree
- REQ-U003: Tools must respect access level restrictions

### 7.2 Database Tools
- REQ-U010: Database backup must support all configured databases
- REQ-U011: Database restore must create backup first
- REQ-U012: Database sync must compare form definitions with DB schema
- REQ-U013: Query tool must show query results in filterable table
- REQ-U014: Query tool must maintain query history

### 7.3 Log Tools
- REQ-U020: Log reader must show PHP errors
- REQ-U021: Log reader must show JavaScript errors
- REQ-U022: Performance logs must be viewable per date
- REQ-U023: Logs must support filtering

### 7.4 Migration Tools
- REQ-U030: Migrations must run in version order
- REQ-U031: Migrations must support rollback
- REQ-U032: Migration status must show on dashboard
- REQ-U033: Migrations must support PHP scripts for complex operations

## 8. Dashboard

### 8.1 Dashboard Widgets
- REQ-D001: Dashboard must show "Vaak gebruikt" (frequently used forms)
- REQ-D002: Dashboard must show recent activity for admins
- REQ-D003: Dashboard must show system health for admins
- REQ-D004: Dashboard must show cache statistics
- REQ-D005: Dashboard must show warning if debug logging enabled
- REQ-D006: Performance stats must support click for details
- REQ-D007: Performance popup must support API retest (10 calls)

## 9. Security

### 9.1 Access Control
- REQ-C001: Forms must enforce access levels (read/edit/full)
- REQ-C002: Rights matrix must show form permissions
- REQ-C003: Extra buttons must respect access level
- REQ-C004: Subform access must fall back to parent access
- REQ-C005: Admin-only tools must require admin access
- REQ-C006: Developer-only tools must require developer access

### 9.2 Audit Trail
- REQ-C010: All changes must be logged to tblCMAMonitoring
- REQ-C011: Log must include user, form, action, timestamp
- REQ-C012: Log must include changed field values
- REQ-C013: Log must show readable form names (not IDs)

## 10. Error Handling

### 10.1 Error Display
- REQ-E001: PHP errors must show in development mode
- REQ-E002: JavaScript errors must show in error console (developers)
- REQ-E003: API errors must show user-friendly messages
- REQ-E004: Form validation errors must be highlighted
- REQ-E005: Database errors must strip driver-specific prefixes
- REQ-E006: 404 errors must be logged

### 10.2 Error Recovery
- REQ-E010: Failed saves must retry before showing error
- REQ-E011: Combo lookup failures must show descriptive error
- REQ-E012: Missing parent field must show configuration dialog

## 11. Performance

### 11.1 Caching
- REQ-P001: Form HTML templates must be cached
- REQ-P002: Combo options must be cached in browser
- REQ-P003: Static files must have cache headers (28 days)
- REQ-P004: Fonts must have cache headers (1 year)
- REQ-P005: Cache must be clearable via dashboard/tools

### 11.2 Optimization
- REQ-P010: Form definitions must support localStorage caching
- REQ-P011: Combo values must support session storage
- REQ-P012: Multiple API calls should be batched where possible
- REQ-P013: Subform tab data should only load on tab click
- REQ-P014: Change log should build after form is visible

## 12. Mobile Support

### 12.1 Responsive Design
- REQ-M001: Labels must appear above fields on mobile
- REQ-M002: Fields must use 100% width on mobile
- REQ-M003: Tables must limit visible columns on mobile
- REQ-M004: Toolbar button text must be hidden on mobile
- REQ-M005: Forms must open as popups on mobile (not sidepanels)
- REQ-M006: User menu must be accessible via hamburger menu

## 13. File Management

### 13.1 File Browser
- REQ-B001: File browser must support list and thumbnail views
- REQ-B002: File browser must support folder navigation
- REQ-B003: File browser must prevent navigation outside base folder
- REQ-B004: File upload must support dimension validation
- REQ-B005: Image editing must support crop and rotate

---

## Requirement Coverage Matrix

| Requirement | Implemented | Cypress Test |
|-------------|------------|--------------|
| REQ-F001    | Yes        | config-forms.cy.js |
| REQ-F002    | Yes        | tree-navigation.cy.js |
| REQ-F003    | Yes        | infinite-scroll.cy.js |
| REQ-F004    | Yes        | infinite-scroll.cy.js |
| REQ-F005    | Yes        | security-forms.cy.js |
| REQ-F020    | Partial    | NEEDS TEST |
| REQ-F024    | Partial    | NEEDS TEST |
| REQ-F025    | Yes        | inline-edit.cy.js |
| REQ-F031    | Partial    | NEEDS TEST |
| REQ-F045    | Partial    | NEEDS TEST |
| REQ-F047    | Yes        | inline-edit.cy.js |
| REQ-F050    | Yes        | inline-edit.cy.js |
| REQ-S001    | Yes        | subforms.cy.js |
| REQ-S004    | Yes        | subforms.cy.js |
| REQ-T001    | Yes        | table-functions.cy.js |
| REQ-T002    | Yes        | table-functions.cy.js |
| REQ-T030    | Yes        | infinite-scroll.cy.js |
| REQ-N011    | Yes        | clean-urls.cy.js |
| REQ-U010    | Partial    | NEEDS TEST |
| REQ-D001    | Yes        | dashboard.cy.js |
