<?php
/**
 * Maintenance page — rendered while a maintenance.flag is present (raised by the
 * CMA maintenance tool or by deploy.php around a `composer update`).
 *
 * Lives in the platform so every site shows the same page and improvements
 * arrive with a composer update. A site's own maintenance page is a thin include
 * of this file (see templates/src/pages/maintenance.php.template); it keeps an
 * inline fallback for the moment cma/ is not there yet.
 *
 * The gate may set ONE variable before including this file:
 *
 *   $message — the text the admin typed in the CMA maintenance tool. When the
 *              gate does not pass it, the flag file is read here instead.
 *
 * Branding, all optional and all read defensively, per field in this order:
 *   1. data/maintenance.json          — dedicated, branding-only file. Safe on a
 *      site without data/app.json, because ConfigLoader REPLACES rather than
 *      merges: a partial app.json would break the CMA logo.
 *   2. data/app.json → "maintenance"  — same fields inside the app config.
 *   3. the "company" block of data/cma_branding.json (or the legacy
 *      data/app.json) — the CMA's own branding.
 * Fields: logo, name, message, accent, whatsapp, email.
 *
 * MUST stay dependency-free: no Composer autoload, no App\Library\* — this runs
 * before the autoloader and possibly mid-deploy, when vendor/ may be absent or
 * half-written. Native PHP only, and it must never itself fatal.
 */

// Site root: this file sits in <site>/cma/.
$cmaMaintRoot = dirname(__DIR__);

$cmaMaintRead = static function (string $path): array {
    if (is_file($path)) {
        $data = json_decode((string) @file_get_contents($path), true);
        if (is_array($data)) {
            return $data;
        }
    }
    return [];
};
$cmaMaintFirst = static function (...$values): string {
    foreach ($values as $value) {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }
    return '';
};
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$cmaMaintJson     = $cmaMaintRead($cmaMaintRoot . '/data/maintenance.json');
$cmaMaintApp      = $cmaMaintRead($cmaMaintRoot . '/data/app.json');
$cmaMaintBranding = $cmaMaintRead($cmaMaintRoot . '/data/cma_branding.json');
$cmaMaintBlock    = isset($cmaMaintApp['maintenance']) && is_array($cmaMaintApp['maintenance']) ? $cmaMaintApp['maintenance'] : [];
$cmaMaintCompany  = [];
foreach ([$cmaMaintBranding, $cmaMaintApp] as $cmaMaintSource) {
    if (isset($cmaMaintSource['company']) && is_array($cmaMaintSource['company'])) {
        $cmaMaintCompany = $cmaMaintSource['company'];
        break;
    }
}

// A manually raised flag can carry a one-off message. The gate normally hands it
// over in $message; read the flag ourselves when it did not.
$cmaMaintFlagMessage = '';
if (!isset($message) || !is_string($message) || trim($message) === '') {
    $cmaMaintFlagRaw = trim((string) @file_get_contents($cmaMaintRoot . '/maintenance.flag'));
    if ($cmaMaintFlagRaw !== '') {
        $cmaMaintFlagJson = json_decode($cmaMaintFlagRaw, true);
        if (is_array($cmaMaintFlagJson) && isset($cmaMaintFlagJson['message']) && is_string($cmaMaintFlagJson['message'])) {
            $cmaMaintFlagMessage = trim($cmaMaintFlagJson['message']);
        }
    }
}

// "cma" is de placeholder-bedrijfsnaam van een kaal platform, geen sitenaam.
// Zonder deze uitzondering stond er "cma" op de onderhoudspagina van een site
// die zijn eigen naam niet opgaf; nu valt hij door naar de domeinnaam, die
// altijd klopt en herkenbaar is.
$cmaMaintCompanyName = trim((string) ($cmaMaintCompany['name'] ?? ''));
if (strcasecmp($cmaMaintCompanyName, 'cma') === 0) {
    $cmaMaintCompanyName = '';
}
$cmaMaintName = $cmaMaintFirst(
    $cmaMaintJson['name'] ?? '',
    $cmaMaintBlock['name'] ?? '',
    $cmaMaintCompanyName,
    $_SERVER['HTTP_HOST'] ?? ''
);
$message = $cmaMaintFirst(
    isset($message) && is_string($message) ? $message : '',
    $cmaMaintFlagMessage,
    $cmaMaintJson['message'] ?? '',
    $cmaMaintBlock['message'] ?? '',
    'We zijn even bezig met de website, duurt niet lang.'
);
$cmaMaintAccent = $cmaMaintFirst(
    $cmaMaintJson['accent'] ?? '',
    $cmaMaintBlock['accent'] ?? '',
    $cmaMaintCompany['backgroundColor'] ?? '',
    '#b8892b'
);
if (!preg_match('/^#[0-9a-f]{3,8}$/i', $cmaMaintAccent)) {
    $cmaMaintAccent = '#b8892b';
}

// A logo configured for THIS page is drawn for a light background, so it goes on
// the page as-is. The CMA's company logo is drawn for the brand colour — the CMA
// header pairs the two — so that one goes on a band in that colour. Without the
// distinction a white logo lands invisibly on the light background.
$cmaMaintLogo   = $cmaMaintFirst($cmaMaintJson['logo'] ?? '', $cmaMaintBlock['logo'] ?? '');
$cmaMaintOnBand = false;
$cmaMaintWidth  = 0;
$cmaMaintHeight = 0;
if ($cmaMaintLogo === '') {
    $cmaMaintLogo = $cmaMaintFirst($cmaMaintCompany['logo'] ?? '');
    if ($cmaMaintLogo !== '') {
        $cmaMaintOnBand = true;
        $cmaMaintWidth  = (int) ($cmaMaintCompany['logoWidth'] ?? 0);
        $cmaMaintHeight = (int) ($cmaMaintCompany['logoHeight'] ?? 0);
    }
}
// Relative paths resolve against the web root; a missing file is dropped rather
// than rendered as a broken image. IIS serves static files without the
// auto-prepend, so the logo still loads while the app itself is down.
$cmaMaintLogoUrl = '';
if ($cmaMaintLogo !== '') {
    if (preg_match('#^(https?:)?//#', $cmaMaintLogo)) {
        $cmaMaintLogoUrl = $cmaMaintLogo;
    } elseif (is_file($cmaMaintRoot . '/' . ltrim($cmaMaintLogo, '/'))) {
        $cmaMaintLogoUrl = '/' . ltrim($cmaMaintLogo, '/');
    }
}

// Optional illustration, shipped by the site.
$cmaMaintArt = is_file($cmaMaintRoot . '/assets/images/maintenance.svg')
    ? '/assets/images/maintenance.svg'
    : '';

// Contact line, built from whatever the site configured.
$cmaMaintWhats = preg_replace('/\D+/', '', (string) ($cmaMaintJson['whatsapp'] ?? $cmaMaintBlock['whatsapp'] ?? ''));
$cmaMaintEmail = $cmaMaintFirst($cmaMaintJson['email'] ?? '', $cmaMaintBlock['email'] ?? '');
$cmaMaintContact = [];
if ($cmaMaintWhats !== '') {
    $cmaMaintShown = (strpos($cmaMaintWhats, '31') === 0) ? '0' . substr($cmaMaintWhats, 2) : $cmaMaintWhats;
    $cmaMaintShown = preg_replace('/^(\d{2})(\d+)$/', '$1 $2', $cmaMaintShown);
    $cmaMaintContact[] = 'App me even op <a href="https://wa.me/' . $h($cmaMaintWhats) . '">' . $h($cmaMaintShown) . '</a>';
}
if ($cmaMaintEmail !== '') {
    $cmaMaintContact[] = ($cmaMaintContact ? 'of mail ' : 'Mail ') . '<a href="mailto:' . $h($cmaMaintEmail) . '">' . $h($cmaMaintEmail) . '</a>';
}
$cmaMaintContactLine = $cmaMaintContact ? implode(' ', $cmaMaintContact) . '.' : '';
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Even geduld<?= $cmaMaintName !== '' ? ' — ' . $h($cmaMaintName) : '' ?></title>
<style>
  :root { color-scheme: light; --accent: <?= $h($cmaMaintAccent) ?>; }
  html { height: 100%; }
  body {
    margin: 0; min-height: 100%;
    display: flex; align-items: center; justify-content: center; padding: 1.5rem;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    background: #faf8f5; color: #2b2622;
  }
  .box {
    max-width: 32rem; width: 100%; text-align: center; overflow: hidden;
    background: #fff; border-radius: 14px; box-shadow: 0 10px 30px rgba(0, 0, 0, .08);
  }
  .band {
    background: var(--accent); padding: 1.15rem 2rem;
    display: flex; align-items: center; justify-content: center;
  }
  .band img { max-width: min(14rem, 60vw); height: auto; }
  .content { padding: 2.5rem 2rem; }
  .logo { max-width: 15rem; max-height: 4.5rem; width: auto; height: auto; margin: 0 0 1.75rem; }
  .brand {
    font-size: .8rem; letter-spacing: .2em; text-transform: uppercase;
    color: #9a8f80; margin-bottom: 1.5rem;
  }
  .art { margin: 0 0 1.5rem; }
  .art img { width: 7.5rem; height: 7.5rem; }
  h1 { font-size: 1.55rem; font-weight: 600; margin: 0 0 .6rem; line-height: 1.3; }
  p { margin: .5rem 0; line-height: 1.6; color: #5c5449; }
  .help { margin-top: 1.75rem; font-size: .95rem; }
  a { color: var(--accent); font-weight: 600; text-decoration: none; }
  a:hover { text-decoration: underline; }
  @media (max-width: 30rem) {
    .content { padding: 2rem 1.25rem; }
    .art img { width: 5.5rem; height: 5.5rem; }
    h1 { font-size: 1.3rem; }
  }
</style>
</head>
<body>
  <div class="box">
<?php if ($cmaMaintLogoUrl !== '' && $cmaMaintOnBand): ?>
    <div class="band"><img src="<?= $h($cmaMaintLogoUrl) ?>" alt="<?= $h($cmaMaintName) ?>"<?php
        echo $cmaMaintWidth > 0 ? ' width="' . $cmaMaintWidth . '"' : '';
        echo $cmaMaintHeight > 0 ? ' height="' . $cmaMaintHeight . '"' : ''; ?>></div>
<?php endif; ?>
    <div class="content">
<?php if ($cmaMaintLogoUrl !== '' && !$cmaMaintOnBand): ?>
      <img class="logo" src="<?= $h($cmaMaintLogoUrl) ?>" alt="<?= $h($cmaMaintName) ?>">
<?php elseif ($cmaMaintLogoUrl === '' && $cmaMaintName !== ''): ?>
      <div class="brand"><?= $h($cmaMaintName) ?></div>
<?php endif; ?>
<?php if ($cmaMaintArt !== ''): ?>
      <div class="art"><img src="<?= $h($cmaMaintArt) ?>" alt="" width="120" height="120"></div>
<?php endif; ?>
      <h1><?= $h($message) ?></h1>
      <p>Probeer het over een paar minuten opnieuw.</p>
<?php if ($cmaMaintContactLine !== ''): ?>
      <p class="help">Blijft u deze melding langer dan 10 minuten zien?<br><?= $cmaMaintContactLine ?></p>
<?php endif; ?>
    </div>
  </div>
</body>
</html>
