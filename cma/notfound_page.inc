<?php
/**
 * 404 page — the visual half of a not-found handler.
 *
 * Lives in the platform so the CMA and a site's front-end show the same page and
 * a change lands everywhere with a composer update. The caller keeps everything
 * that is site-specific: logging the miss, smart redirects for moved assets, the
 * JSON variant for API callers, and the file-versus-page classification.
 *
 * Variables the caller may set before including this file — all optional:
 *
 *   $requestedPath    the path that was not found (default: REQUEST_URI)
 *   $kind             'file' for a missing asset, anything else = a missing page.
 *                     "De pagina die je zoekt bestaat niet" is wrong for
 *                     /favicon.svg or /handleiding.docx, so the copy follows.
 *   $notFoundHomeUrl  where the button goes (default '/')
 *   $notFoundHomeLabel  its label (default 'Terug naar start')
 *
 * MUST stay dependency-free: no Composer autoload, no App\Library\* — a 404
 * handler runs for missing static files too, where bootstrapping would only add
 * failure modes (and sessions would override cache headers).
 */

$cmaNfKind  = (isset($kind) && $kind === 'file') ? 'file' : 'page';
$cmaNfPath  = (isset($requestedPath) && is_string($requestedPath) && $requestedPath !== '')
    ? $requestedPath
    : (string) ($_SERVER['REQUEST_URI'] ?? '/');
$cmaNfHome  = (isset($notFoundHomeUrl) && is_string($notFoundHomeUrl) && $notFoundHomeUrl !== '')
    ? $notFoundHomeUrl
    : '/';
$cmaNfLabel = (isset($notFoundHomeLabel) && is_string($notFoundHomeLabel) && $notFoundHomeLabel !== '')
    ? $notFoundHomeLabel
    : 'Terug naar start';
$cmaNfWhat  = $cmaNfKind === 'file' ? 'Bestand' : 'Pagina';
$cmaNfText  = $cmaNfKind === 'file'
    ? 'Dit bestand kon niet (meer) worden gevonden.'
    : 'De pagina die je zoekt bestaat niet of is verplaatst.';
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - <?= $h($cmaNfWhat) ?> niet gevonden</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        /* The 404 itself is the backdrop: as large as the viewport allows and
           blurred, so it reads as a watermark instead of competing with the
           message on top of it. Decorative — the heading carries the meaning. */
        .ghost {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: min(34vw, 78vh);
            font-weight: 800;
            line-height: 1;
            letter-spacing: -.04em;
            color: #204496;
            opacity: .16;
            filter: blur(16px);
            white-space: nowrap;
            pointer-events: none;
            user-select: none;
            -webkit-user-select: none;
        }
        .container {
            position: relative;
            text-align: center;
            max-width: 500px;
        }
        h1 {
            font-size: 32px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
        }
        p {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .path {
            background: rgba(233, 236, 239, .85);
            padding: 8px 12px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 14px;
            word-break: break-all;
            margin-bottom: 30px;
        }
        a {
            display: inline-block;
            padding: 12px 24px;
            background: #204496;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
        }
        a:hover {
            background: #163070;
        }
    </style>
</head>
<body>
    <div class="ghost" aria-hidden="true"><span>404</span></div>
    <div class="container">
        <h1><?= $h($cmaNfWhat) ?> niet gevonden</h1>
        <p><?= $h($cmaNfText) ?></p>
        <div class="path"><?= $h($cmaNfPath) ?></div>
        <a href="<?= $h($cmaNfHome) ?>"><?= $h($cmaNfLabel) ?></a>
    </div>
</body>
</html>
