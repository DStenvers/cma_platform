# JSON Configuration System

## Overview

The JSON configuration system replaces the MS Access repository database with JSON files for better version control, easier maintenance, and faster deployments.

## Architecture

```
/cma/config/
├── app.json                    # Application settings
├── databases.json              # Database connections
├── modules.json                # Module definitions
├── menu.json                   # Navigation structure
├── control-types.json          # Field type definitions
├── reports.json                # Report definitions
├── data-sources.json           # Combo/XMLStore data sources
└── schema/                     # JSON schemas for validation
    ├── app.schema.json
    ├── databases.schema.json
    ├── modules.schema.json
    ├── menu.schema.json
    ├── control-types.schema.json
    ├── reports.schema.json
    └── data-sources.schema.json
```

## ConfigLoader Class

Central class for loading and managing JSON configurations.

### Location
`/cma/classes/ConfigLoader.php`

### Basic Usage

```php
require_once __DIR__ . '/classes/ConfigLoader.php';

// Load any config file
$data = ConfigLoader::load('databases');

// Typed accessors
$databases = ConfigLoader::getDatabases();
$modules = ConfigLoader::getModules();
$menus = ConfigLoader::getMenu();
$controlTypes = ConfigLoader::getControlTypes();
$reports = ConfigLoader::getReports();
$dataSources = ConfigLoader::getDataSources();
$appConfig = ConfigLoader::getAppConfig();

// Find specific items
$db = ConfigLoader::getDatabase('data');           // By name
$db = ConfigLoader::getDatabase(6);                // By ID
$module = ConfigLoader::getModule('Opleidingen');
$controlType = ConfigLoader::getControlType(3);    // textbox

// Filtered lists
$activeModules = ConfigLoader::getActiveModules();
$visibleReports = ConfigLoader::getVisibleReports();
$selectableDataSources = ConfigLoader::getSelectableDataSources();

// Menu helpers
$menuItems = ConfigLoader::getMenuItems();         // Flat list
$menuItemId = ConfigLoader::getMenuItemIdForForm('users');

// Company branding
$companyConfig = ConfigLoader::getCompanyConfig();
```

### Caching

ConfigLoader uses in-memory caching. Cache is automatically invalidated when:
- `ConfigLoader::save()` is called
- `ConfigLoader::invalidate()` is called manually

```php
// Invalidate specific config
ConfigLoader::invalidate('databases');

// Invalidate all configs
ConfigLoader::invalidate();
```

### Saving Configuration

```php
$data = ConfigLoader::load('databases');
$data['databases'][] = [
    'id' => 10,
    'name' => 'new_db',
    'title' => 'New Database',
    'connectionString' => '...',
    'type' => 'access'
];
ConfigLoader::save('databases', $data);
```

### Feature Flag

```php
// Disable JSON config (fall back to database)
ConfigLoader::setEnabled(false);

// Check if enabled
if (ConfigLoader::isEnabled()) {
    // Use JSON
}
```

## Configuration Files

### databases.json

```json
{
  "$schema": "./schema/databases.schema.json",
  "databases": [
    {
      "id": 6,
      "name": "data",
      "title": "Hoofddatabase",
      "connectionString": "Provider=Microsoft.ACE.OLEDB.12.0;Data Source=[path]db/cmadata.mdb",
      "type": "access",
      "readOnly": false
    }
  ]
}
```

### modules.json

```json
{
  "$schema": "./schema/modules.schema.json",
  "modules": [
    {
      "id": 1,
      "name": "Opleidingen",
      "database": "data",
      "active": true,
      "order": 1,
      "cachePrefix": "opl",
      "parameters": [
        {
          "name": "maxParticipants",
          "type": "number",
          "caption": "Maximum deelnemers",
          "value": 25
        }
      ]
    }
  ]
}
```

### menu.json

```json
{
  "$schema": "./schema/menu.schema.json",
  "menus": [
    {
      "id": 1,
      "name": "Opleidingen",
      "order": 1,
      "items": [
        {
          "id": 101,
          "name": "Opleidingen",
          "form": "opleidingen",
          "formId": 15,
          "order": 1
        },
        {
          "id": 102,
          "name": "Externe link",
          "href": "https://example.com",
          "target": "_blank",
          "order": 2
        }
      ]
    }
  ]
}
```

### control-types.json

```json
{
  "$schema": "./schema/control-types.schema.json",
  "controlTypes": [
    {"id": 1, "name": "hidden", "description": "01_Hidden"},
    {"id": 2, "name": "combobox", "description": "02_ComboBox"},
    {"id": 3, "name": "textbox", "description": "03_TextBox"}
  ]
}
```

### app.json

```json
{
  "$schema": "./schema/app.schema.json",
  "company": {
    "logo": "/images/logo.png",
    "logoWidth": 200,
    "logoHeight": 50,
    "url": "https://www.company.com"
  },
  "settings": {
    "language": "NL",
    "dateFormat": "DD-MM-YYYY",
    "timezone": "Europe/Amsterdam"
  }
}
```

### reports.json

```json
{
  "$schema": "./schema/reports.schema.json",
  "reports": [
    {
      "id": 1,
      "title": "Deelnemerslijst",
      "module": "Deelnemers",
      "moduleId": 3,
      "url": "report_deelnemers.php",
      "visible": true,
      "parentReport": null
    }
  ]
}
```

### data-sources.json

```json
{
  "$schema": "./schema/data-sources.schema.json",
  "dataSources": [
    {
      "id": 1,
      "name": "Docenten actief",
      "module": "Docenten",
      "query": "SELECT ID, Naam FROM tblDocenten WHERE Actief=True ORDER BY Naam",
      "selectable": true,
      "database": "data"
    }
  ]
}
```

## API Endpoints

### config_api.php

```
GET /cma/api/config_api.php?action=list&config=databases
GET /cma/api/config_api.php?action=get&config=databases&id=6
GET /cma/api/config_api.php?action=schema&config=databases
```

### config_post.php

```
POST /cma/api/config_post.php
Content-Type: application/x-www-form-urlencoded

_config_file=databases
_config_array_key=databases
_action=save
id=6
name=data
title=Hoofddatabase
...
```

## Maintenance Forms

Access via `form.php?form=<formname>`:

| Form | Description |
|------|-------------|
| `config_databases` | Database connections |
| `config_modules` | Module definitions |
| `config_menu` | Menu structure |
| `config_control_types` | Field types |
| `config_reports` | Report definitions |
| `config_data_sources` | Data sources |
| `config_app` | Application settings |

## Migration Tools

### Export from Database

```
# Via browser (requires admin login)
http://localhost/cma/tools_export_repository.php

# The script exports all repository tables to JSON files
```

### Test Scripts

```
# Test ConfigLoader functionality
http://localhost/cma/tools_test_config.php

# Validate JSON files
http://localhost/cma/tools_validate_config.php
```

## Database Fallback

All code that uses ConfigLoader falls back to database queries if:
- JSON files don't exist
- `ConfigLoader::setEnabled(false)` is called
- JSON parsing fails

This allows gradual migration and easy rollback.

## Integration Points

### CmaRepository

Updated methods that now use ConfigLoader:
- `renderDatabaseSelect()` - Database dropdown
- `renderModuleSelect()` - Module dropdown
- `renderControlSelect()` - Control type dropdown
- `getConnectionStringById()` - Connection string lookup

### menurep.php

- `loadMenuData()` - Loads menu from JSON or database
- Application config (logo, etc.) loaded from JSON

## Best Practices

1. **Always use typed accessors** - Use `getDatabases()` instead of `load('databases')['databases']`
2. **Check for null** - Methods like `getDatabase($id)` return null if not found
3. **Use feature flag** - Set `ConfigLoader::setEnabled(false)` to disable during migration issues
4. **Validate after changes** - Run `tools_validate_config.php` after manual edits
5. **Keep schemas updated** - Update schemas when adding new fields

## Security

- All API endpoints require admin authentication
- Config files should not be web-accessible (protected by web.config/htaccess)
- Sensitive data (passwords) should use environment variables, not JSON

## Performance Notes

### Form Definition Caching

JSON form definitions use a **zero-overhead caching strategy**:

1. **No file cache needed** - The JSON files ARE the cache. Unlike database-backed forms that use `Cache::retrieveFromFile`, JSON forms are read directly from disk.

2. **In-memory caching** - `JsonFormLoader` maintains a per-request memory cache (`private static array $cache = []`), so repeated calls to `JsonFormLoader::load()` within the same request don't re-read or re-parse the file.

3. **No cache invalidation needed** - When you edit a JSON form file, changes are immediate. No need to clear caches.

### Comparison: Database vs JSON Forms

| Aspect | Database Forms | JSON Forms |
|--------|---------------|------------|
| Source | tblForms + tblControls | .json file |
| File Cache | Yes (Cache::retrieveFromFile) | No (file IS the source) |
| Memory Cache | Yes ($_formDefCache) | Yes (JsonFormLoader::$cache) |
| Cache Invalidation | Required | Not needed |
| Parse Overhead | SQL result → array | JSON decode |

### ConfigLoader Caching

The `ConfigLoader` class also uses in-memory caching:

```php
// First call: reads from disk
$databases = ConfigLoader::getDatabases();

// Subsequent calls: returns from memory
$databases = ConfigLoader::getDatabases();

// Force re-read
ConfigLoader::invalidate('databases');
$databases = ConfigLoader::getDatabases();
```

This means configuration is only read once per request, making JSON configs as fast as (or faster than) cached database queries.

## ConfigFormService

The `ConfigFormService` class handles CRUD operations for forms that use JSON configuration files as their data source (forms with `database: "json"`).

### Location
`/cma/classes/Services/ConfigFormService.php`

### How It Works

When a JSON form definition has `database: "json"`, the form system routes data operations through `ConfigFormService` instead of executing SQL queries:

```
Form Definition (database: "json")
         ↓
    FormDataProvider / ListService
         ↓
    ConfigFormService
         ↓
    ConfigLoader (read/write JSON files)
```

### Form Definition Properties

Forms backed by JSON config files use these special properties:

| Property | Description | Example |
|----------|-------------|---------|
| `database` | Must be `"json"` to use ConfigFormService | `"json"` |
| `configFile` | Name of the config file (without .json) | `"databases"` |
| `configArrayKey` | Key containing the array of items | `"databases"` |
| `singleRecord` | If true, form edits entire config object | `true` (for app.json) |
| `idField` | Field used as record identifier | `"id"` |

### Example Form Definitions

#### Array-based Config (databases.json)

```json
{
    "name": "config_databases",
    "title": "Database Configuratie",
    "database": "json",
    "configFile": "databases",
    "configArrayKey": "databases",
    "idField": "id",
    "allowAdd": true,
    "allowDelete": true,
    "fields": [
        {"name": "id", "type": "readonly", "caption": "ID"},
        {"name": "name", "type": "textbox", "caption": "Naam"},
        {"name": "title", "type": "textbox", "caption": "Titel"},
        {"name": "connectionString", "type": "memo", "caption": "Connection String"},
        {"name": "type", "type": "combobox", "caption": "Type"}
    ]
}
```

#### Single-record Config (app.json)

```json
{
    "name": "config_app",
    "title": "Applicatie Configuratie",
    "database": "json",
    "configFile": "app",
    "singleRecord": true,
    "allowAdd": false,
    "allowDelete": false,
    "fields": [
        {"name": "company.logo", "type": "textbox", "caption": "Logo pad"},
        {"name": "company.logoWidth", "type": "number", "caption": "Logo breedte"},
        {"name": "settings.language", "type": "combobox", "caption": "Taal"}
    ]
}
```

### Nested Field Support

For single-record forms like `config_app`, fields can use dot notation to access nested properties:

| Field Name | Maps To |
|------------|---------|
| `company.logo` | `config.company.logo` |
| `company.logoWidth` | `config.company.logoWidth` |
| `settings.language` | `config.settings.language` |

The service automatically flattens nested objects for form display and unflattens them when saving.

### API Methods

```php
use Cma\Services\ConfigFormService;

// Check if a form uses JSON config
$isJsonConfig = ConfigFormService::isJsonConfigForm($formDef);

// Get list data for tree/table view
$result = ConfigFormService::getListData('config_databases');
// Returns: ['success' => true, 'data' => [...], 'total' => 5]

// Get single record
$result = ConfigFormService::getRecord('config_databases', 6);
// Returns: ['success' => true, 'data' => ['id' => 6, 'name' => 'data', ...]]

// Save record (create or update)
$result = ConfigFormService::saveRecord('config_databases', [
    'id' => 6,
    'name' => 'data',
    'title' => 'Hoofddatabase',
    'connectionString' => '...',
    'type' => 'access'
]);
// Returns: ['success' => true, 'id' => 6]

// Delete record
$result = ConfigFormService::deleteRecord('config_databases', 6);
// Returns: ['success' => true]
```

### Integration with FormDataProvider

`FormDataProvider` automatically detects JSON config forms and routes to `ConfigFormService`:

```php
// In FormDataProvider.php
$jsonData = $formDef['_json'] ?? [];
$database = $jsonData['database'] ?? '';

if ($database === 'json') {
    // Route to ConfigFormService
    return ConfigFormService::getRecord($formName, $recordId);
}
```

### Integration with ListService

`ListService` provides tree and table HTML generation for JSON config forms:

```php
// In ListService.php
if ($database === 'json') {
    return self::getJsonConfigTreeHtml($formName, $activeId, $options, $jsonData);
}
```

### Computed Fields

`ConfigFormService` can add computed fields to list data. For example, the menu config form shows item counts:

```php
// In ConfigFormService::addComputedFields()
if ($formName === 'config_menu') {
    foreach ($items as &$item) {
        $item['_itemCount'] = count($item['items'] ?? []);
    }
}
```

These computed fields (prefixed with `_`) can be displayed in list columns but are not saved.

## Available Config Forms

| Form Name | Config File | Type | Description |
|-----------|-------------|------|-------------|
| `config_databases` | databases.json | Array | Database connections |
| `config_modules` | modules.json | Array | Module definitions |
| `config_menu` | menu.json | Array | Menu groups |
| `config_menu_items` | menu.json | Nested | Menu items within groups |
| `config_control_types` | control-types.json | Array | Form control types |
| `config_reports` | reports.json | Array | Report definitions |
| `config_data_sources` | data-sources.json | Array | Combo data sources |
| `config_app` | app.json | Single | Application settings |

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        Browser (form.php)                        │
└─────────────────────────────────────────────────────────────────┘
                                │
                    ┌───────────┴───────────┐
                    │    API Endpoint       │
                    │  (form_api.php)       │
                    └───────────┬───────────┘
                                │
        ┌───────────────────────┼───────────────────────┐
        │                       │                       │
        ▼                       ▼                       ▼
┌───────────────┐     ┌─────────────────┐     ┌─────────────────┐
│ FormDataProvider│     │   ListService   │     │  RecordService  │
│  (get/save/   │     │ (tree/table    │     │    (delete)     │
│   delete)     │     │   HTML)        │     │                 │
└───────┬───────┘     └────────┬────────┘     └────────┬────────┘
        │                      │                       │
        │   database: "json"?  │                       │
        │         ↓            │                       │
        └──────────────────────┼───────────────────────┘
                               │
                    ┌──────────┴──────────┐
                    │  ConfigFormService  │
                    │   (CRUD for JSON    │
                    │    config forms)    │
                    └──────────┬──────────┘
                               │
                    ┌──────────┴──────────┐
                    │    ConfigLoader     │
                    │  (read/write JSON)  │
                    └──────────┬──────────┘
                               │
                    ┌──────────┴──────────┐
                    │   /cma/config/      │
                    │   *.json files      │
                    └─────────────────────┘
```

## Error Handling

All ConfigFormService methods return a consistent response format:

```php
// Success
['success' => true, 'data' => [...], ...]

// Error
['success' => false, 'error' => 'Error message']
```

Common errors:
- `"Formulier 'x' niet gevonden"` - Form definition not found
- `"Form missing configFile property"` - Form def lacks configFile
- `"Record met ID 'x' niet gevonden"` - Record not found
- `"Dit record kan niet worden verwijderd"` - Protected record
- `"Opslaan mislukt"` - File write failed

## Database Migration System

The migration system provides automatic database versioning and schema updates.

### Overview

```
/cma/config/
├── migrations.json             # Migration definitions
└── ...

/cma/migrations/sql/
└── 2.0.0_performance_indexes.sql   # SQL scripts for complex migrations

/cma/classes/Services/
└── MigrationService.php        # Core migration logic
```

### migrations.json Structure

```json
{
    "$schema": "./schema/migrations.schema.json",
    "schemaVersion": "1.0",
    "targetVersion": "2.0.0",
    "migrations": [
        {
            "version": "1.0.0",
            "description": "Initial version - create version tracking tables",
            "date": "2025-12-05",
            "changes": [
                {
                    "type": "createVersionTable",
                    "database": "rep"
                }
            ]
        },
        {
            "version": "1.1.0",
            "description": "Add isBeheer and actie fields",
            "date": "2025-12-05",
            "changes": [
                {
                    "type": "addColumn",
                    "database": "rep",
                    "table": "tblControls",
                    "column": "isBeheer",
                    "dataType": "INTEGER",
                    "default": "0"
                }
            ]
        }
    ]
}
```

### Supported Change Types

| Type | Description | Parameters |
|------|-------------|------------|
| `createVersionTable` | Create `_cma_version` table | database |
| `addColumn` | Add column to table | database, table, column, dataType, default |
| `dropColumn` | Remove column | database, table, column |
| `addIndex` | Create index | database, table, columns[], indexName |
| `runSql` | Execute raw SQL | database, sql |
| `runScript` | Execute SQL file | script (relative path) |
| `updateData` | Update existing data | database, sql |

### MigrationService Class

```php
use Cma\Services\MigrationService;

$service = new MigrationService();

// Get current database versions
$versions = $service->getCurrentVersions();
// Returns: ['rep' => '1.5.0', 'users' => '1.5.0', 'data' => '1.5.0']

// Get target version from migrations.json
$target = $service->getTargetVersion();
// Returns: '2.0.0'

// Get pending migrations
$pending = $service->getPendingMigrations();
// Returns: array of migrations not yet applied

// Apply all pending migrations
$result = $service->applyAllPending();
// Returns: ['success' => bool, 'applied' => [], 'errors' => [], 'log' => []]

// Apply a single migration
$result = $service->applyMigration($migration);

// Get migration history
$history = $service->getMigrationHistory();

// Check if migrations are pending
if ($service->hasPendingMigrations()) {
    // Show notification
}
```

### Version Tracking

Each database (rep, users, data) has a `_cma_version` table:

```sql
CREATE TABLE _cma_version (
    id INT IDENTITY(1,1) PRIMARY KEY,
    version VARCHAR(20) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT GETDATE(),
    description NVARCHAR(255)
);
```

### Admin Interface

Access via **Tools > Database onderhoud > Database migraties** (`tools_migrations.php`):

- View current database versions
- See pending migrations with descriptions
- Apply all or individual migrations
- View migration history

### Automatic Notification

When admin users log in and pending migrations exist, a notification banner appears:

```
⚠ Er zijn 3 database updates beschikbaar. Nu uitvoeren
```

### Adding New Migrations

1. Edit `config/migrations.json`
2. Add new migration entry with incremented version:

```json
{
    "version": "2.1.0",
    "description": "Add new feature fields",
    "date": "2025-12-06",
    "changes": [
        {
            "type": "addColumn",
            "database": "rep",
            "table": "tblForms",
            "column": "newField",
            "dataType": "VARCHAR(100)"
        }
    ]
}
```

3. Update `targetVersion` to match the new version
4. For complex migrations, create SQL script in `migrations/sql/`

### SQL Dialect Handling

The MigrationService automatically handles differences between Access (ODBC) and SQL Server:

| Feature | Access | SQL Server |
|---------|--------|------------|
| Auto-increment | `AUTOINCREMENT` | `IDENTITY(1,1)` |
| Add column | `ADD COLUMN` | `ADD` |
| Boolean | `YESNO` | `BIT` |
| Default datetime | `DEFAULT Now()` | `DEFAULT GETDATE()` |

### Migration Safety

- Migrations run in order by version number
- Each migration checks if already applied
- `addColumn` checks if column exists before adding
- Errors stop migration process (no partial applies)
- Optional migrations (marked `"optional": true`) continue on failure

### Deprecation of ?convert=

The old `default.php?convert=` parameter is deprecated. Existing migrations in default.php remain for backward compatibility but new migrations should use the migration system.

### Best Practices

1. **Version numbers** - Use semantic versioning (MAJOR.MINOR.PATCH)
2. **Descriptions** - Write clear descriptions for each migration
3. **Testing** - Test migrations on a copy of production data first
4. **Backups** - Always backup before applying migrations
5. **Idempotency** - Design migrations to be safe to run multiple times
6. **Optional flag** - Mark non-critical migrations as optional
