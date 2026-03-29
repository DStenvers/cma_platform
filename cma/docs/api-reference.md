# CMA Form API Reference

The Form API (`form_api.php`) provides AJAX endpoints for form operations in the CMA admin interface.

## Base URL

```
/cma/form_api.php
```

## Common Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `jsonForm` or `form` | string | Yes | JSON form name (e.g., `opleidingen`, `deelnemers`) |
| `action` | string | Yes | Operation to perform |

> **Note**: The `FormID` parameter is deprecated and no longer supported. Use `jsonForm` or `form` parameter with the JSON form name instead.

## Response Format

All responses are JSON with the following structure:

```json
{
  "success": true|false,
  "error": "Error message (only if success=false)",
  ...action-specific fields
}
```

In development mode, responses include:
- `_debugPath`: Array of execution steps
- `_exception`: Exception details (if error occurred)
- `_badFields`: Fields with UTF-8 encoding issues

---

## Actions

### `tree` - Get Tree/Table HTML

Returns HTML for the list panel (tree or table view).

**Method:** GET

**Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `ID` | integer | null | Active record ID to highlight |
| `search` | string | "" | Search term to filter results |
| `displayMode` | integer | 1 | 1=tree view, 2=table view |

**Response:**
```json
{
  "success": true,
  "html": "<div class='libTree'>...</div>",
  "count": 42,
  "hasMore": false
}
```

**Cache:** Private, 30 seconds

---

### `list` - Get List Data as JSON

Returns list data in JSON format (alternative to HTML).

**Method:** GET

**Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `search` | string | "" | Search term |
| `limit` | integer | 800 | Maximum records to return |

**Response:**
```json
{
  "success": true,
  "items": [
    {"ID": 1, "Name": "Item 1", ...},
    {"ID": 2, "Name": "Item 2", ...}
  ],
  "count": 42
}
```

**Cache:** Private, 30 seconds

---

### `record` - Get Record Data

Retrieves a single record for form population.

**Method:** GET

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `ID` | string | Yes | Record ID (integer or GUID) |

**Response:**
```json
{
  "success": true,
  "fields": {
    "ID": 123,
    "Name": "Example",
    "Email": "test@example.com",
    ...
  },
  "lastModified": "2024-01-15 10:30:00",
  "lastModifiedBy": "admin"
}
```

**Cache:** No cache

---

### `save` - Save Record

Creates or updates a record.

**Method:** POST

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `ID` | string | No | Record ID (empty for new record) |
| `[fieldName]` | mixed | Varies | Field values to save |

**Request Body:** Form data (application/x-www-form-urlencoded)

**Response (Success):**
```json
{
  "success": true,
  "id": 123,
  "message": "Record opgeslagen"
}
```

**Response (Validation Error):**
```json
{
  "success": false,
  "error": "Validation failed",
  "validationErrors": {
    "Email": "Invalid email format",
    "Name": "This field is required"
  }
}
```

**Cache:** No cache

---

### `delete` - Delete Record

Deletes a record.

**Method:** GET or POST

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `ID` | string | Yes | Record ID to delete |

**Response:**
```json
{
  "success": true,
  "message": "Record verwijderd"
}
```

**Cache:** No cache

---

### `combo` - Get Combo Options

Returns options for a single dropdown field.

**Method:** GET

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `field` | string | Yes | Field name |
| `search` | string | No | Filter options by search term |

**Response:**
```json
{
  "success": true,
  "options": [
    {"id": 1, "text": "Option 1"},
    {"id": 2, "text": "Option 2"}
  ]
}
```

**Cache:** Public, 5 minutes

---

### `combos` - Get Multiple Combo Options (Batch)

Returns options for multiple dropdown fields in one request.

**Method:** GET

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `fields` | string | Yes | Comma-separated field names |

**Response:**
```json
{
  "success": true,
  "combos": {
    "Status": {
      "success": true,
      "options": [...]
    },
    "Category": {
      "success": true,
      "options": [...]
    }
  }
}
```

**Cache:** Public, 5 minutes

---

### `checklist` - Get Checklist Options

Returns options for a checklist field with selection state.

**Method:** GET

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `controlId` | integer | Yes | Control ID from form definition |
| `ID` | string | No | Record ID (for selection state, -1 for new) |

**Response:**
```json
{
  "success": true,
  "options": [
    {"id": 1, "text": "Option 1", "selected": true},
    {"id": 2, "text": "Option 2", "selected": false}
  ]
}
```

**Cache:** Public, 5 minutes

---

### `subform` - Get Subform Data

Returns data for an embedded subform.

**Method:** GET

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `ParentID` | string | Yes | Parent record ID |
| `SubformIndex` | integer | No | Subform index (0-based) |

**Response:**
```json
{
  "success": true,
  "html": "<table class='libTable'>...</table>",
  "count": 5
}
```

**Cache:** No cache

---

### `renderer` - Get Custom Renderer HTML

Returns HTML from a custom field renderer.

**Method:** GET

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `renderer` | string | Yes | Renderer name |
| `field` | string | No | Field name |
| `ID` | string | No | Record ID |
| `options` | string | No | JSON-encoded options |

**Response:**
```json
{
  "success": true,
  "html": "<div class='custom-renderer'>...</div>"
}
```

**Cache:** No cache

---

## Error Codes

| HTTP Status | Description |
|-------------|-------------|
| 200 | Success (check `success` field for operation result) |
| 500 | Server error (exception occurred) |

All errors return JSON with `success: false` and an `error` message.

---

## Cache Headers

| Action | Cache-Control | Duration |
|--------|--------------|----------|
| combo, combos, checklist | public, max-age=300 | 5 minutes |
| tree, list | private, max-age=30 | 30 seconds |
| record, subform, renderer | private, no-cache | - |
| save, delete | no-store, no-cache | - |

---

## Examples

### Load a form's tree view
```javascript
fetch('/cma/form_api.php?FormID=123&action=tree&displayMode=1')
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      document.getElementById('listContent').innerHTML = data.html;
    }
  });
```

### Load a record
```javascript
fetch('/cma/form_api.php?FormID=123&action=record&ID=456')
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      populateForm(data.fields);
    }
  });
```

### Save a record
```javascript
const formData = new FormData(document.getElementById('mainForm'));
formData.append('FormID', 123);
formData.append('action', 'save');

fetch('/cma/form_api.php', {
  method: 'POST',
  body: formData
})
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      showNotification('Record saved', 'success');
    } else {
      showNotification(data.error, 'error');
    }
  });
```

### Batch load combo options
```javascript
fetch('/cma/form_api.php?FormID=123&action=combos&fields=Status,Category,Priority')
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      Object.entries(data.combos).forEach(([field, options]) => {
        populateSelect(field, options.options);
      });
    }
  });
```

---

## JSON Form Support

For JSON-defined forms, use `jsonForm` instead of `FormID`:

```javascript
fetch('/cma/form_api.php?jsonForm=users&action=tree')
```

Available JSON forms:
- `users` - User management
- `groups` - Group management
- `marketing_urls` - Marketing URL redirects

---

## Form Page URL Reference

The form page (`form.php`) supports various display modes controlled by URL parameters.

> **Note**: The `FormID` parameter is deprecated. All forms now use JSON definitions with the `form=` parameter.

### Display Modes

| Mode | Description | URL Parameters | Body Classes |
|------|-------------|----------------|--------------|
| **List View (Default)** | Shows list panel with tree/table, detail panel empty | `form=opleidingen` | (none) |
| **Detail View (Edit)** | Direct to record, hides list panel | `form=opleidingen&ID=40` | `has-record mode-detail` |
| **Detail View (New)** | Empty form for new record | `form=opleidingen&New=Y` | `is-creating mode-detail` |
| **Copy Mode** | Load record data but save as new | `form=opleidingen&ID=40&copy=Y` | `is-creating mode-detail` |
| **Subform (Add Related)** | New record with parent FK preset | `form=competentie_template_vragen&parentID=5&parentField=fkCompetentieTemplate` | `is-creating mode-detail` |
| **Subform (Edit Related)** | Edit existing subform record | `form=competentie_template_vragen&ID=119&parentID=5&parentField=fkCompetentieTemplate` | `has-record mode-detail` |

### URL Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `form` | string | JSON form name (from assets/forms/definitions/) - **Required** |
| `ID` | int | Record ID to load (0 = new record) |
| `New` | Y/N | Force new record mode |
| `copy` | Y/N | Copy mode - load existing data but save as new record |
| `parentID` | int/string | Parent record ID for subforms |
| `parentField` | string | FK field name linking to parent record |

### Body Classes

The form page injects CSS classes on the `<body>` element based on request parameters:

| Class | Triggered By | Purpose |
|-------|--------------|---------|
| `has-record` | `ID` > 0 | Indicates an existing record is being edited |
| `is-creating` | `New=Y` or `ID=0` or `parentID+parentField` | Indicates new record mode |
| `mode-detail` | Any direct record access | Hides list panel, shows only detail form |
| `mode-tree` | Default list view (tree) | Shows tree navigation in list panel |
| `mode-table` | Table view selected | Shows table navigation in list panel |

### JavaScript Global Variables

When accessing a form directly with parameters, these globals are set:

```javascript
window.CMA_DIRECT_RECORD_ID  // Record ID to load (int or null)
window.CMA_COPY_MODE         // true if copy mode (boolean)
window.CMA_PARENT_ID         // Parent record ID for subforms (string)
window.CMA_PARENT_FIELD      // FK field name for subforms (string)
```

### Example URLs

```
# List view (default)
/cma/form.php?form=opleidingen

# Edit existing record
/cma/form.php?form=opleidingen&ID=40

# Create new record
/cma/form.php?form=opleidingen&New=Y

# Copy existing record
/cma/form.php?form=opleidingen&ID=40&copy=Y

# Add related subform record
/cma/form.php?form=competentie_template_vragen&parentID=5&parentField=fkCompetentieTemplate

# Edit related subform record
/cma/form.php?form=competentie_template_vragen&ID=119&parentID=5&parentField=fkCompetentieTemplate
```
