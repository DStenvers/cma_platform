# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is `stenversonline/platform` — a Composer package providing shared components for multiple projects: PHP helper classes, a shared frontend library, and the CMA (Content Management Application) admin interface. It is distributed to consumer projects via `composer install/update`, which triggers `Installer.php` to sync `library/`, `cma/`, and template files into the project root.

The codebase is a PHP application originally converted from Classic ASP. Many patterns (RecordSet, Application globals, ADO-style database access) reflect this heritage.

## Architecture

### Layers

- **`src/helpers/`** — PSR-4 autoloaded classes under `App\Library\*`. Core platform services: `Database`, `Application`, `Bootstrap`, `Request`, `Response`, `RecordSet`, `SQL`, `Session`, `Email`, etc. These are stateless static-method classes used across all projects.
- **`library/`** — Shared frontend assets (jQuery, CSS, JS) and legacy PHP include files (`lib_*.inc`). The `webcomponents/` subdirectory contains vanilla JS web components prefixed `lib-` (e.g., `lib-table`, `lib-dialog`, `lib-combo`).
- **`cma/`** — The CMA admin application. Entry point is `bootstrap.inc` which loads the parent `_bootstrap.php`. PHP pages serve the admin UI; AJAX operations go through `form_api.php`.
- **`cma/classes/`** — CMA-specific classes under the `Cma\` namespace (not PSR-4 autoloaded; loaded via `require_once`). Service classes live in `classes/Services/`.
- **`cma/webcomponents/`** — CMA-specific web components prefixed `cma-` (e.g., `cma-tree`, `cma-toolbar`, `cma-tabs`).
- **`templates/`** — Project-level template files (`.template` suffix) copied to consumer projects on first install only.

### Key Patterns

- **Application state**: Global config lives in `$GLOBALS['Application']`. Always use `Application::get(key, default)` / `Application::set(key, value)` — never access `$GLOBALS['Application']` directly.
- **Database access**: `Database::getConnection()` returns PDO. Use `Database::executeQuery()`, `Database::executeSingleRecord()`, `Database::execute()`, `Database::getFieldValue()`. RecordSet wraps PDOStatement to emulate ADO cursors (`$rs->EOF`, `$rs->MoveNext()`, `$rs->Fields['col']`).
- **ODBC/Access**: Primary database driver is ODBC for Microsoft Access (`.mdb`). The Database class supports both `native` odbc_* functions and PDO ODBC mode. SQL Server and MySQL are also supported via config.
- **JSON-driven forms**: Form definitions are JSON files in `cma/assets/forms/definitions/` (internal) and `assets/forms/` (app-specific). Loaded by `JsonFormLoader`, rendered by `JsonFormRenderer`. The menu structure is `cma/config/menu.json`.
- **Environment detection**: `Application::get('omgeving')` returns `O` (development), `L` (local), `T` (test), `A` (acceptance), `P` (production). Debug mode auto-enables for non-production.
- **Web server**: Runs on IIS with URL Rewrite. `_bootstrap.php` is auto-prepended to all requests. `web.config` handles routing.

### Config Files (per-project, protected from overwrites)

- `cma/config/app.json` — Branding, features
- `cma/config/databases.json` — Database connection mappings
- `cma/config/menu.json` — CMA navigation menu structure
- `cma/config/reports.json` — Report definitions
- `app.php` — Application globals (paths, DB connections, branding)
- `global.asa.php` — Secrets, credentials
- `.env` / `.env.*` — Environment variables

## Commands

### PHP Unit Tests (custom lightweight runner, no PHPUnit dependency)

```bash
# Run all tests
cd cma && php tests/TestRunner.php

# Run a specific test class
cd cma && php tests/TestRunner.php ArrTest

# Run a specific test method
cd cma && php tests/TestRunner.php ArrTest --filter=testFlatten
```

Tests extend the custom `TestCase` class from `TestRunner.php` which provides PHPUnit-compatible assertion methods. Test files are `cma/tests/*Test.php`.

### Cypress E2E Tests

```bash
cd cma && npx cypress open          # Interactive mode
cd cma && npx cypress run           # Headless run
cd cma && npx cypress run --spec 'cypress/e2e/auth/**/*.cy.js'  # Specific suite
```

Cypress config is in `cma/cypress.config.js`. Tests require a running CMA instance. Shadow DOM piercing is enabled globally (`includeShadowDom: true`) for web component testing.

### Build / Minification

```bash
cd cma && npm run build             # Build icons + minify JS/CSS
cd cma && npm run build:minify      # Minify only (terser for JS, sed for CSS)
cd cma && npm run build:icons       # Generate icon font (requires python3)
```

Every `.js` file has a corresponding `.min.js` alongside it. The build script skips files where `.min.js` is already newer than the source.

### Releasing

Tag a new version in git — consumer projects pull updates via `composer update stenversonline/platform`. The Installer syncs `library/` and `cma/` but never overwrites protected config files.

## Conventions

- Language in code, comments, and commits is English. Documentation and UI strings may be Dutch.
- Legacy `lib_*.inc` files in `library/` contain procedural helper functions. New functionality goes in `src/helpers/` as proper classes.
- Web components use vanilla JS (no framework). Library components use `lib-` prefix, CMA components use `cma-` prefix. Both have `.min.js` counterparts.
- The `Cma\` namespace classes are loaded via `require_once`, not autoloading. The `App\Library\` namespace is Composer-autoloaded.
- SQL is built using `SQL::postString()`, `SQL::postNumber()`, etc. for parameter escaping. `Database::executeQuery($sql, $params)` supports PDO prepared statements.
- Migrations in `cma/migrations/` are versioned PHP scripts run via `MigrationService`. Version tracked in `cma/config/migrations.json`.
