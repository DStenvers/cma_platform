# Platform Installatie Handleiding

## Overzicht

`stenversonline/platform` is een Composer package dat de gedeelde componenten bevat voor alle projecten:
- **PHP Helper classes** (`App\Library\*`) — Database, Request, Response, Email, etc.
- **Shared Library** — jQuery, webcomponents, CSS, legacy PHP functies
- **CMA Admin** — Content Management Application
- **Modules** — Calendar, Login, Search, etc.

## Nieuw project opzetten

### 1. Project directory aanmaken

```bash
mkdir mijn-project
cd mijn-project
```

### 2. Composer configureren

Maak `composer.json`:

```json
{
    "name": "stenversonline/mijn-project",
    "type": "project",
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/DStenvers/cma_platform"
        }
    ],
    "require": {
        "stenversonline/platform": "^1.0"
    },
    "scripts": {
        "post-install-cmd": "App\\Library\\Installer::postInstall",
        "post-update-cmd": "App\\Library\\Installer::postUpdate"
    }
}
```

### 3. GitHub authenticatie

Maak `auth.json` (NIET in git committen!):

```json
{
    "github-oauth": {
        "github.com": "ghp_JouwGitHubPersonalAccessToken"
    }
}
```

Een Personal Access Token maak je aan op: https://github.com/settings/tokens
Benodigde scope: `repo` (voor private repositories)

### 4. Installeren

```bash
composer install
```

Dit doet automatisch:
- Download het platform package naar `vendor/`
- Kopieert `library/` bestanden naar je project root
- Kopieert `cma/` bestanden naar je project root
- Kopieert `module/` bestanden naar je project root
- Maakt template bestanden aan als ze nog niet bestaan:
  - `_bootstrap.php` — Bootstrap wrapper
  - `_bootstrap_wrapper.php` — IIS URL rewrite handler
  - `web.config` — IIS configuratie
  - `app.php` — Project configuratie (features, branding)
  - `global.asa.php` — Database connections, SSO credentials
  - `.env.example` — Environment variabelen template
- Maakt writable directories: `sessions/`, `cache/`, `logs/`

### 5. Project configureren

1. **Kopieer `.env.example` naar `.env.local`** (of `.env.development`, etc.)
2. **Bewerk `app.php`** — stel organisatienaam, features, kleuren in
3. **Bewerk `global.asa.php`** — configureer database connections per omgeving
4. **Maak database bestanden aan** in `db/`

### 6. IIS configureren

- Maak een IIS Website aan die naar je project root wijst
- Stel PHP FastCGI in (server level, niet in web.config)
- Voeg Server Variables toe aan IIS URL Rewrite:
  - `HTTP_X_ORIGINAL_FILE` (allowed)
  - `HTTP_X_TOOL_NAME` (allowed)

### 7. Testen

Open je browser:
- **Front-end:** http://localhost/
- **CMA Admin:** http://localhost/cma/

## Bestaand project updaten

```bash
# Bekijk beschikbare updates
composer outdated stenversonline/platform

# Update naar nieuwste versie
composer update stenversonline/platform

# Review wat er veranderd is
git diff library/ cma/ module/

# Commit
git add composer.lock
git commit -m "Update platform to vX.Y.Z"
```

**Belangrijk:** De Installer overschrijft NOOIT deze project-specifieke configuratie:
- `cma/config/app.json`
- `cma/config/databases.json`
- `cma/config/menu.json`
- `cma/config/reports.json`
- `app.php`, `global.asa.php`, `.env*`, `web.config`

## .gitignore voor projecten

Voeg dit toe aan je project `.gitignore`:

```gitignore
/vendor/
/library/
/cma/
/module/
/sessions/
/cache/
/logs/
auth.json
.env
.env.*
!.env.example
.app_started
.platform-manifest.json
```

De directories `library/`, `cma/`, en `module/` worden beheerd door Composer en hoeven niet in git.

## Versioning

Het platform gebruikt [Semantic Versioning](https://semver.org/):
- **Major** (v2.0.0): Breaking changes — vereist mogelijk aanpassingen in je project
- **Minor** (v1.1.0): Nieuwe features, backward compatible
- **Patch** (v1.0.1): Bugfixes

De CMA admin toont automatisch de platform versie (uit de Composer package tag).

Pin je versie in `composer.json`:
- `"^1.0"` — Accepteer minor en patch updates (aanbevolen)
- `"~1.2"` — Accepteer alleen patch updates
- `"1.2.3"` — Exact deze versie

## Platform structuur

```
cma_platform/
├── composer.json               — Package definitie
├── .gitignore
├── platform_install.md         — Deze handleiding
├── src/
│   ├── helpers/                — PHP Helper classes (App\Library\*)
│   │   ├── Application.php
│   │   ├── Bootstrap.php       — Herbruikbare bootstrap logica
│   │   ├── Database.php
│   │   └── ... (31 classes)
│   └── Installer.php           — Composer post-install script
├── library/                    — Shared web assets + legacy PHP
│   ├── library.js, library.css
│   ├── webcomponents/          — lib-table, lib-dialog, etc.
│   ├── lib_*.inc               — Legacy helper functies
│   └── ...
├── cma/                        — CMA Admin applicatie
│   ├── bootstrap.inc
│   ├── classes/                — CMA service classes
│   ├── assets/                 — JS, CSS
│   ├── webcomponents/          — CMA web components
│   ├── config/                 — Migraties, schema's
│   └── ...
├── module/                     — Gedeelde modules
│   ├── calendar/
│   ├── login/
│   └── ...
└── templates/                  — Project templates
    ├── _bootstrap.php.template
    ├── web.config.template
    ├── app.php.template
    └── ...
```

## Veelgestelde vragen

### Kan ik CMA bestanden aanpassen in mijn project?

Nee — bij de volgende `composer update` worden ze overschreven. Maak in plaats daarvan:
- **Custom forms**: Voeg JSON formulierdefinities toe in `assets/forms/definitions/`
- **Custom config**: Pas `cma/config/app.json` aan (wordt beschermd)
- **Custom CSS**: Voeg project-specifieke CSS toe buiten de CMA directory

### Hoe maak ik een nieuwe platform release?

```bash
cd cma_platform
# Maak wijzigingen en commit
git add -A
git commit -m "Beschrijving van de wijziging"

# Tag een nieuwe versie
git tag v1.2.0
git push origin main --tags
```

Projecten kunnen dan updaten met `composer update`.

### Wat als composer install de Installer niet uitvoert?

Controleer dat je `composer.json` de scripts sectie bevat:
```json
"scripts": {
    "post-install-cmd": "App\\Library\\Installer::postInstall",
    "post-update-cmd": "App\\Library\\Installer::postUpdate"
}
```

### Hoe reset ik de Application cache?

Verwijder het `.app_started` bestand in je project root, of clear de APCu cache.
Op O/L omgevingen wordt de cache automatisch overgeslagen.
