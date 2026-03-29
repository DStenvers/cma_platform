# CMA Logging System

Dit document beschrijft de complete logging architectuur van het CMA systeem.

## Overzicht

Het logging systeem bestaat uit meerdere lagen die samenwerken:

```
┌─────────────────────────────────────────────────────────────┐
│                      CLIENT SIDE                            │
├─────────────────────────────────────────────────────────────┤
│  LibLog (lib-log.js)                                        │
│  ├─ Errors → Altijd console + altijd server                 │
│  ├─ Warnings → Console als debug=AAN, server als minLevel   │
│  └─ Debug/Info → Console alleen als debug=AAN               │
│                                                              │
│  CmaErrorHandler (error-handler.js)                         │
│  ├─ Error panel (zichtbaar in dev mode)                     │
│  ├─ Rate limited (max 5/min client, 100/uur server)         │
│  └─ Deduplicatie (60-seconden venster)                      │
└─────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────┐
│                      SERVER SIDE                            │
├─────────────────────────────────────────────────────────────┤
│  Logger.php (Applicatie logging)                            │
│  ├─ Productie: WARNING+ → cache/logs/app_YYYY-MM-DD.log     │
│  ├─ Dev/Test: DEBUG+ → zelfde bestand                       │
│  └─ ERROR+ → Ook naar PHP error_log()                       │
│                                                              │
│  PerformanceLogger.php (Timing metrics)                     │
│  ├─ Gecontroleerd door PERF_LOG_ENABLED                     │
│  └─ Output → cache/perf_logs/perf_YYYY-MM-DD.log            │
│                                                              │
│  JS Errors → tblCMAJavascriptErrors (database)              │
└─────────────────────────────────────────────────────────────┘
```

## Componenten

### 1. LibLog (Client-side)

**Locatie:** `/library/webcomponents/lib-log.js`

Centrale logging voor JavaScript die console.* methoden onderschept.

#### Configuratie

```javascript
window.LIBLOG_CONFIG = {
    apiEndpoint: '/cma/api/log.php',  // Server endpoint
    sendToServer: true,                // Server logging aan
    batchSize: 10,                     // Batch grootte voor flush
    flushInterval: 5000,               // Auto-flush interval (ms)
    interceptConsole: true,            // Vervang console.* methoden
    minLevelForServer: 'error',        // Alleen errors naar server
    debugMode: false                   // Via cookie of CMA_DEBUG
};
```

#### Gebruik

```javascript
// Direct gebruik
LibLog.info('User logged in', { userId: 123 });
LibLog.warning('Slow query detected', { ms: 500 });
LibLog.error('Failed to save', { error: err.message });

// Via console (wordt onderschept)
console.log('Debug info');    // → LibLog.debug
console.warn('Warning');      // → LibLog.warning
console.error('Error');       // → LibLog.error
```

#### Gedrag per niveau

| Niveau | Console output | Error panel | Naar server |
|--------|---------------|-------------|-------------|
| error | Altijd | Altijd | Altijd |
| warning | Alleen debug=AAN | Alleen debug=AAN | Als minLevel ≤ warning |
| info | Alleen debug=AAN | Nee | Als minLevel ≤ info |
| debug | Alleen debug=AAN | Nee | Als minLevel ≤ debug |

### 2. CmaErrorHandler (Client-side)

**Locatie:** `/cma/assets/js/error-handler.js`

Vangt JavaScript errors en toont ze in een visueel paneel.

#### Features

- **Automatische error capture:** `window.onerror` en `unhandledrejection`
- **Visueel error panel:** Toont errors in dev mode (rechtsboven)
- **Rate limiting:** Max 5 errors per minuut (client-side)
- **Deduplicatie:** Identieke errors binnen 60 seconden worden genegeerd
- **Server logging:** Stuurt errors naar `form_api.php?action=logJsError`

#### Rate Limits

| Waar | Limiet |
|------|--------|
| Client (panel) | 5 per minuut |
| Server (database) | 100 per IP per uur |

### 3. Logger (Server-side)

**Locatie:** `/cma/classes/Services/Logger.php`

PSR-3 compatible structured logging voor PHP.

#### Log Levels

```php
Logger::EMERGENCY  // Systeem onbruikbaar
Logger::ALERT      // Directe actie vereist
Logger::CRITICAL   // Kritieke condities
Logger::ERROR      // Error condities
Logger::WARNING    // Waarschuwingen
Logger::NOTICE     // Normaal maar significant
Logger::INFO       // Informatief
Logger::DEBUG      // Debug berichten
```

#### Gebruik

```php
use Cma\Services\Logger;

Logger::info('User logged in', ['userId' => 123]);
Logger::warning('Slow query', ['ms' => 500, 'sql' => $sql]);
Logger::error('Save failed', ['formId' => 45, 'error' => $e->getMessage()]);

// Exception logging met volledige trace
Logger::exception($e, 'Database error', ['query' => $sql]);
```

#### Minimum Level per Omgeving

| Omgeving | Code | Minimum Level |
|----------|------|---------------|
| Productie | P | WARNING |
| Test | T | DEBUG |
| Ontwikkeling | O | DEBUG |

#### Output Locaties

- **Alle levels:** `cache/logs/app_YYYY-MM-DD.log`
- **ERROR en hoger:** Ook naar PHP `error_log()`

#### Log Format

```json
{
    "ts": "2024-01-15T14:30:45.123",
    "level": "error",
    "req": "a1b2c3d4",
    "msg": "Database connection failed",
    "ctx": {"host": "localhost", "error": "Connection refused"},
    "url": "/cma/api/form_list.php",
    "method": "GET",
    "ip": "192.168.1.1"
}
```

#### Sensitive Data

De logger verwijdert automatisch gevoelige data:
- password
- token
- secret
- api_key
- credentials

### 4. PerformanceLogger (Server-side)

**Locatie:** `/cma/classes/Services/PerformanceLogger.php`

Logging voor performance metrics en timing.

#### Configuratie

Via `.env` of `system_settings.json`:

```env
PERF_LOG_ENABLED=true
CACHE_LOG_ENABLED=false
```

#### Gebruik

```php
use Cma\Services\PerformanceLogger;

// Timer
PerformanceLogger::startTimer('query');
$result = $db->query($sql);
PerformanceLogger::endTimer('query', ['rows' => count($result)]);

// SQL query logging
PerformanceLogger::logQuery($sql, $durationMs, ['table' => 'users']);

// API call logging
PerformanceLogger::logApi('form_list', $durationMs, ['formName' => 'users']);

// Memory usage
PerformanceLogger::logMemory('after_query');
```

#### Output

```json
{
    "ts": "14:30:45.123",
    "req": "a1b2c3d4",
    "type": "query",
    "name": "sql",
    "ms": 45.23,
    "ctx": {"sql": "SELECT * FROM users...", "sql_length": 150}
}
```

#### Cleanup

- **Performance logs:** 7 dagen bewaard
- **Application logs:** 30 dagen bewaard

## Gebruikersvoorkeuren

### Debug Mode Cookie

De `cma_debug_mode` cookie bepaalt of console logging actief is:

| Waarde | Effect |
|--------|--------|
| J | Console logging AAN |
| N | Console logging UIT (alleen errors) |

Instelbaar via: **Voorkeuren → Console logging**

### Systeem Instellingen

Via **Tools → Systeem instellingen**:

| Instelling | Effect |
|------------|--------|
| Performance logging | PerformanceLogger aan/uit |
| Cache logging | Cache hit/miss logging aan/uit |
| Debug logging | Verbose debugging aan/uit |

## Errors: Altijd Zichtbaar

**Belangrijk:** Errors worden ALTIJD gelogd, ongeacht debug instellingen:

| Component | Errors altijd... |
|-----------|------------------|
| LibLog | Naar console |
| CmaErrorHandler | In error panel (dev) |
| Server | In database |
| Logger.php | In log file + error_log() |

Dit zorgt ervoor dat productie-errors nooit verloren gaan.

## Best Practices

### JavaScript

```javascript
// Goed - gebruikt LibLog wrapper
LibLog.error('Failed to save', { formId: 123, error: err.message });

// Vermijd - direct console gebruik (wordt wel onderschept maar minder context)
console.error('Failed to save');
```

### PHP

```php
// Goed - structured logging met context
Logger::error('Save failed', [
    'formId' => $formId,
    'userId' => $userId,
    'error' => $e->getMessage()
]);

// Vermijd - alleen tekst
error_log('Save failed: ' . $e->getMessage());
```

### Performance

```php
// Meet database queries
PerformanceLogger::startTimer('complex_query');
$result = $db->query($complexSql);
$ms = PerformanceLogger::endTimer('complex_query', [
    'rows' => $result->rowCount(),
    'table' => 'orders'
]);

if ($ms > 100) {
    Logger::warning('Slow query detected', ['ms' => $ms, 'sql' => $complexSql]);
}
```

## Log Bestanden

| Bestand | Locatie | Inhoud |
|---------|---------|--------|
| Application log | `cache/logs/app_YYYY-MM-DD.log` | Applicatie events |
| Performance log | `cache/perf_logs/perf_YYYY-MM-DD.log` | Timing metrics |
| JS errors | Database `tblCMAJavascriptErrors` | Client-side errors |

## Troubleshooting

### Logs bekijken

1. **Application logs:** Tools → Log reader
2. **Performance logs:** Tools → Log reader → Performance
3. **JS errors:** Direct in database of via error panel

### Debug mode activeren

1. Ga naar **Voorkeuren**
2. Zet **Console logging** op **Ja**
3. Herlaad de pagina

### Logs opschonen

```php
// Handmatig cleanup aanroepen
Logger::cleanup(30);           // Bewaar 30 dagen
PerformanceLogger::cleanup(7); // Bewaar 7 dagen
```

Of via: **Tools → Cache legen → Logs opschonen**
