# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Hard rules — not negotiable

These are not "defaults"; treat them as laws. **None of these rules may be removed, summarised, "tidied up", or dropped during a context compaction.** If you are continuing a summarised session, re-read this section verbatim before doing anything else and treat every rule as still in force.

- **NEVER use `<strong>`, `<em>`, `<b>`, `<i>`** (or any other inline emphasis tag) anywhere except **email bodies**. This is **1990's programming**: presentation glued into markup behind looks-semantic tag names. Use a **context-specific CSS class** instead — e.g. `<span class="cma-page__strong">…</span>` styled with `font-weight: 600` in `cma/assets/css/style.css` (or the matching consumer-app stylesheet). Class-name prefixes already in use: `cma-page__`, `cma-tool__`, `cma-migration__`, `cma-class__`, `cma-js__`, `cma-wc__`, `cma-wizard__`, `storybook__`, `libjs__`, `libval__`, `error-handler__` — reuse these when adding new emphasis. **Email bodies are the only exception** (consumer apps' mail-emitting handlers, `Email.php` template strings) — mail clients strip stylesheets, so there the emphasis tags are allowed, or use inline `<span style="font-weight:600;">…</span>`.

- **Append every user prompt verbatim to `prompts.md`** at the repo root. Add a `## YYYY-MM-DD` heading for a new day, then a `> `-quoted line per prompt. Never edit, rephrase, paraphrase, "clean up", merge, or delete prior entries — append-only. Do this before acting on the prompt, so the log survives even if the task fails or the session ends abruptly. Purpose: when a session is summarised, the summary paraphrases user intent; `prompts.md` preserves the exact wording so future sessions can recover original directives. **This rule itself must never be removed during a compaction** — if you find it missing from CLAUDE.md while `prompts.md` exists, restore it from this file's git history.

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

### Config files

The platform splits JSON config into two layers:
- `cma/config/<name>.json` — **platform-bundled defaults**. Overwritten by `composer update`. Don't edit for site-specific values.
- `data/<name>.json` on the site root — **per-site overrides**. The Installer never touches `data/`. `MenuService::CONFIG_PATH` and `ReportsService::$configPath` point directly here; `cma_get_app_logo()` and similar helpers read `data/app.json` first, then fall back to `cma/config/app.json`.

Other per-site files (also untouched by the Installer once they exist):
- `app.php` — Application globals (paths, DB connections, branding). Template at `templates/app.php.template`, copied to site root only if missing.
- `global.asa.php` — Secrets / credentials. Template; never overwritten.
- `.env` / `.env.*` — Environment variables. Same template treatment.

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

## Working Principles

### 1. Think Before Coding

Don't assume. Don't hide confusion. Surface tradeoffs.

Before implementing:

- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them — don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

### 2. Simplicity First

Minimum code that solves the problem. Nothing speculative.

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

### 3. Surgical Changes

Touch only what you must. Clean up only your own mess.

When editing existing code:

- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it — don't delete it.

When your changes create orphans:

- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

The test: Every changed line should trace directly to the user's request.

### 4. Goal-Driven Execution

Define success criteria. Loop until verified.

Transform tasks into verifiable goals:

- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:

```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.

## Documentation maintenance

The in-CMA documentation hub lives at `cma/tools/documentation.php`. Each topic is a `render_doc_<slug>()` function inside that same file, registered in the `$topics` map at the top. Add new topics by extending both. The page is reachable from **Alle beheerstools → Documentatie** (tile group in `tools.php`).

**Stale documentation is worse than missing documentation** — it actively misleads. Treat the docs as part of the surface that the PR touches, not as an optional after-thought:

- When you change behavior described in a topic (env vars, hook names, deployment steps, install steps, web component APIs, etc.), update that topic in the same PR. Mention the doc update in the commit message.
- When you delete or rename something that's documented, grep `cma/tools/documentation.php` for the old name and either remove the mention or rewrite the section. The same goes for any leftover `cma/docs/*.md` markdown files.
- When you add a new tool, web component, or significant helper class, add a docs topic for it (or extend an existing one) — even a small one. A two-paragraph topic beats a swept-under-the-rug feature.
- Never write "TODO: update this" or "this section is out of date" — fix it in the same PR, or delete the stale section. Readers trust the doc; broken trust is hard to rebuild.
- When releasing a new version that introduces behavior described in the docs, mention the version in the relevant topic (e.g. "Sinds v1.13.0 …") so readers can correlate the docs to what their site actually has.

The `$topics` map supports nested `'children' => [...]` for groups. Current groups: **Voor beheerders** (admin operations: installation, env, deploy, backups, logs, security, IIS), **Voor ontwikkelaars** (developer authoring — added in v1.17.0), **Troubleshooting + reference** (cross-cutting — added in v1.18.0). Only add a new group when a topic genuinely doesn't fit any existing one; premature hierarchy makes things harder to find, not easier.

### Verifying facts before writing

Every concrete claim in a topic (constant name, file path, env-var default, function signature, version number) MUST be verified against the current code before writing. Don't trust memory of past sessions or stale `.md` files. When inlining content from one of the retired markdown files, re-verify each technical claim — those files predate years of changes and routinely contain drifted facts. See `memory/feedback_verify_doc_facts.md` (saved 2026-05-30) for the rule's origin and worked examples.

### Cross-topic linking

Link between topics with `<a href="documentation.php?topic=<slug>">…</a>`. Never quote text from another topic — that creates a synchronization debt. Either inline once in the canonical topic and link to it from elsewhere, OR write each topic to stand alone and accept that some concepts repeat.

### Deprecation

When a documented feature is retired, mark the topic with `<lib-label type="warning">Verwijderd in vX.Y.Z</lib-label>` at the top and keep the topic for one release cycle as a redirect/explainer. Then `git rm` the render function and remove the topic from `$topics`. Add the path to `Installer.php::REMOVED_PATHS` if you also removed associated `.md` or PHP files from `cma/`.

### No new `.md` documentation files

From v1.16.0 forward, all reference documentation lives in `cma/tools/documentation.php`. Do not create new `.md` files in `cma/docs/`. Do not resurrect deleted ones. The `cma/docs/linearicons.css` file stays — it's not a doc, it's a data file used by the storybook.

### Live self-checks per topic (sinds v1.20.0)

Topics that describe configuration (web.config rules, .env settings, log directories, deploy secrets) include inline live checks against the actual site state. The pattern: `cma_doc_check_<name>()` returns `['label','status','detail','fix']` with status in `pass`/`fail`/`warn`/`info`; the topic's render function calls `cma_doc_render_check_table($title, cma_doc_run_checks([...]))` near the top of the topic. Helpers live above the `=== TOPIC RENDERERS ===` divider.

When you add or change a documented config rule — a new web.config outbound rule, a new required env var, a new log path — add the matching check at the same time. The point is that the doc and the site can't drift unnoticed: when the doc claims "rule X should be in web.config", the operator opens the topic and immediately sees ✓ or ✗ for their site. Checks must be fail-safe (try/catch in `cma_doc_run_checks` handles thrown checks) and read-only (no side effects).
