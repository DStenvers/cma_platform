# IIS Setup Documentation

## Prerequisites

- IIS (Internet Information Services) with PHP configured
- IIS URL Rewrite Module installed

## URL Rewrite Module Configuration

### Unlocking Server Variables

The `web.config` uses custom server variables (`HTTP_X_ORIGINAL_FILE`, `HTTP_X_TOOL_NAME`) for URL rewriting. By default, IIS locks the `allowedServerVariables` section at the server level.

**Error message if not unlocked:**
```
Deze configuratiesectie kan niet worden gebruikt voor dit pad. Dit gebeurt wanneer de sectie is vergrendeld op bovenliggend niveau.
```

Or in English:
```
This configuration section cannot be used at this path. This happens when the section is locked at the parent level.
```

**To unlock, run this command as Administrator:**

```cmd
%windir%\system32\inetsrv\appcmd.exe unlock config -section:system.webServer/rewrite/allowedServerVariables
```

This only needs to be done once per server.

## Friendly URLs for CMA Tools

The application supports friendly URLs for tools:

| Friendly URL | Maps to |
|--------------|---------|
| `/cma/tools/clearcache` | `tools/tools_clearcache.php` |
| `/cma/tools/migrations` | `tools/tools_migrations.php` |
| `/cma/tools/query` | `tools/tools_query.php` |
| `/cma/tools/dbsummary` | `tools/tools_dbsummary.php` |
| `/cma/tools/logs` | `tools/logreader.php` |
| `/cma/tools/serverinfo` | `tools/tools_serverinfo.php` |
| `/cma/tools/export_forms` | `migrations/tools_export_forms.php` |
| `/cma/tools/export_menu` | `migrations/tools_export_menu.php` |
| `/cma/tools/export_reports` | `migrations/tools_export_reports.php` |

### How It Works

1. IIS rewrite rule captures `/cma/tools/[name]` URLs
2. Sets `HTTP_X_TOOL_NAME` server variable with the tool name
3. Routes to `_bootstrap_wrapper.php`
4. Bootstrap sets `$_GET['tool']` from the server variable
5. `tools.php` maps the friendly name to the actual file path

### Adding New Tool Mappings

To add a new friendly URL, update the `$toolNameMap` array in `/cma/tools.php`:

```php
$toolNameMap = [
    'mynewtool' => 'tools/my_new_tool.php',
    // ... existing mappings
];
```

## PHP Bootstrap

All PHP files are routed through `_bootstrap_wrapper.php` which:

1. Loads the common bootstrap (`_bootstrap.php`)
2. Sets up autoloading and error handling
3. Includes the actual requested PHP file

This ensures consistent initialization across all pages.

## Security Headers

The `web.config` includes security headers:

- `X-Content-Type-Options: nosniff` - Prevents MIME type sniffing
- `X-Frame-Options: SAMEORIGIN` - Prevents clickjacking

## Hidden Files

The following files are hidden from web access:

- `.env` - Environment configuration
- `.app_started` - Application state file
- `composer.json` - Composer configuration
- `composer.lock` - Composer lock file
