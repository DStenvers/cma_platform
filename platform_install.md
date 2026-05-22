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

## Deployment vanuit GitHub

Onderstaande beschrijft hoe je een platform-gebaseerd project (zoals `karaat` of `rec`) automatisch deploy't vanuit een GitHub repository naar een Windows/IIS server. De stappen werken voor elk project dat het platform gebruikt.

### Server-vereisten

Op de productieserver eenmalig installeren:

- **Git for Windows** — `https://git-scm.com/download/win` (zorg dat `git.exe` in PATH staat)
- **Composer** — `https://getcomposer.org/download/` (globaal in PATH)
- **PHP** — versie matchend met `composer.json` van het project (≥7.4 voor karaat, ≥8.0 voor rec)
- **IIS** met URL Rewrite module + FastCGI configuratie voor PHP

### 1. Initiële deploy (eenmalig per project)

```powershell
# Op de server: clone het project repo
cd C:\inetpub\wwwroot
git clone https://github.com/DStenvers/karaatedelstenen.git karaat
cd karaat

# Auth voor private platform package (zie sectie "GitHub authenticatie" hierboven)
# Maak auth.json met PAT in dezelfde map als composer.json

# Composer install (haalt platform binnen, kopieert library/cma/module bestanden)
composer install --no-dev --optimize-autoloader

# Maak runtime directories aan (worden door installer normaal aangemaakt)
mkdir sessions cache logs

# Project-specifieke config
copy .env.production.example .env.production
# Bewerk .env.production met DB credentials, mail SMTP, etc.

# Database files plaatsen
# Voor Access: kopieer .mdb files naar /db/ (NIET via git — zie .gitignore)
# Voor SQL: voer migraties uit via cma/tools/tools_db_migrations.php

# IIS: maak een Site die naar deze map wijst (zie sectie "IIS configureren")
```

### 2. Automatische deploy via GitHub webhook

#### a. Deploy-script op de server

Maak `deploy.php` aan in de project root (NIET committen — zie `.gitignore`):

```php
<?php
// deploy.php — receiver voor GitHub webhook pushes
// Plaats in project root; secret moet matchen met de GitHub webhook config

$secret = getenv('DEPLOY_SECRET') ?: '';  // zet als env var of in .env
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

// Verifieer GitHub signature
if ($secret === '' || !hash_equals('sha256=' . hash_hmac('sha256', $payload, $secret), $signature)) {
    http_response_code(403);
    exit('Invalid signature');
}

// Verifieer dat het een push naar main is
$event = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
$data = json_decode($payload, true);
if ($event !== 'push' || ($data['ref'] ?? '') !== 'refs/heads/main') {
    http_response_code(200);
    exit('Ignored (not a push to main)');
}

// Schrijf logs
$logFile = __DIR__ . '/logs/deploy.log';
file_put_contents($logFile, "[" . date('c') . "] Deploy gestart\n", FILE_APPEND);

// Voer deploy stappen uit
chdir(__DIR__);
$steps = [
    'git pull origin main 2>&1',
    'composer install --no-dev --optimize-autoloader 2>&1',
];

foreach ($steps as $cmd) {
    $output = shell_exec($cmd);
    file_put_contents($logFile, "[" . date('c') . "] $ $cmd\n$output\n", FILE_APPEND);
}

// Clear caches
foreach (glob(__DIR__ . '/cache/*.html') as $f) @unlink($f);
foreach (glob(__DIR__ . '/cache/*.json') as $f) @unlink($f);
file_put_contents($logFile, "[" . date('c') . "] Cache gewist, deploy klaar\n", FILE_APPEND);

echo "OK";
```

Bescherm de URL via IIS — alleen GitHub IP-ranges toelaten of via webhook secret check (zoals hierboven).

#### b. Server-side permissies

De PHP/IIS gebruiker (typisch `IIS APPPOOL\DefaultAppPool` of `IUSR`) moet:
- **Lees+schrijf** rechten op de project directory (voor `git pull`)
- **Execute** rechten op `git.exe` en `composer.phar`
- Een schrijfbaar `.gitconfig` of `HOME` env var

Test handmatig eerst als de IIS-user:
```powershell
runas /user:"IIS APPPOOL\DefaultAppPool" "git pull"
```

#### c. GitHub webhook configureren

In de GitHub repo:
1. **Settings → Webhooks → Add webhook**
2. **Payload URL**: `https://www.karaatedelstenen.nl/deploy.php`
3. **Content type**: `application/json`
4. **Secret**: genereer een willekeurig 32-char string, vul ook in als `DEPLOY_SECRET` env var op de server (of in `.env.production`)
5. **Events**: alleen `push`
6. **Active**: aanvinken

Test door op de "Recent Deliveries" tab naar de response code te kijken (moet 200 zijn).

### 3. Alternatief: scheduled pull (eenvoudiger, polling delay)

Als de webhook-aanpak te complex is, kun je een Windows Task Scheduler taak instellen die periodiek pult:

```powershell
# Maak een script auto-pull.ps1 in de project root
$projectPath = 'C:\inetpub\wwwroot\karaat'
cd $projectPath
$result = git pull origin main 2>&1
if ($result -match 'Already up to date') {
    exit 0
}
# Er was een wijziging — composer + cache wis
composer install --no-dev --optimize-autoloader
Get-ChildItem "$projectPath\cache\*.html" | Remove-Item -Force -ErrorAction SilentlyContinue
Get-ChildItem "$projectPath\cache\*.json" | Remove-Item -Force -ErrorAction SilentlyContinue
Add-Content "$projectPath\logs\deploy.log" "[$(Get-Date -Format o)] auto-pull: $result"
```

Plan via Task Scheduler:
- **Trigger**: Daily, repeat every 5 minutes
- **Action**: `powershell.exe -ExecutionPolicy Bypass -File C:\inetpub\wwwroot\karaat\auto-pull.ps1`
- **Run as**: account met git+composer rechten

Nadeel t.o.v. webhook: tot 5 min vertraging na een push.

### 4. Handmatige deploy (als auto-deploy uit staat / fallback)

Via RDP/SSH op de server:

```powershell
cd C:\inetpub\wwwroot\karaat
git pull origin main
composer install --no-dev --optimize-autoloader

# Clear caches
Remove-Item cache\*.html, cache\*.json -Force -ErrorAction SilentlyContinue

# Optioneel: forceer .app_started reset om Application cache te verversen
Remove-Item .app_started -ErrorAction SilentlyContinue
```

### 5. Rollback

Bij een mislukte deploy:

```powershell
# Bekijk recente commits
git log --oneline -10

# Rollback naar vorige werkende commit
git reset --hard <commit-hash>

# Composer naar matching state
composer install --no-dev --optimize-autoloader

# Cache wissen
Remove-Item cache\*.html, cache\*.json -Force -ErrorAction SilentlyContinue
```

**Belangrijk:** rollback via `git reset --hard` wist lokale wijzigingen. Maak backups van `.env*`, `db/*.mdb`, en custom config files (die staan in `.gitignore` dus blijven veilig).

### 6. Veelvoorkomende deploy-problemen

| Probleem | Oorzaak | Oplossing |
|----------|---------|-----------|
| `git pull` faalt op auth | Geen credentials store of expired PAT | Gebruik PAT in URL: `git remote set-url origin https://USER:PAT@github.com/...` |
| `composer install` faalt op platform package | Geen `auth.json` met PAT | Plaats `auth.json` (zie sectie "GitHub authenticatie") |
| Site geeft 500 na deploy | Permissies op `sessions/`, `cache/`, `logs/` | Geef IIS-user schrijfrechten op deze mappen |
| Wijzigingen niet zichtbaar | OPcache | Restart IIS App Pool of zet `opcache.validate_timestamps=1` in php.ini |
| `prod_detail_*.html` cache stale | Mijn-cache niet gewist | Run `Cache leegmaken` via `/cma/tools_clearcache.php` of voeg cache-wis stap toe in deploy script |

### 7. .gitignore aanvulling voor deploy

Zorg dat het project `.gitignore` deze server-only bestanden uitsluit:

```gitignore
# Deploy-only files (server-side, niet committen)
/deploy.php
/auto-pull.ps1
/auth.json
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
