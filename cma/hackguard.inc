<?php
/**
 * Hack-URL gate for the 404 handlers (the site's 404.php and cma/404.php).
 *
 * IIS sends a missing file or an unknown URL straight to the 404 handler,
 * without the bootstrap, so this is where a 404 on a hack-list path is counted
 * and where a client over the limit gets its 403 (App\Library\HackBlocker).
 * Loads only the Composer autoloader and the HACK_BLOCK_* values from .env:
 * no session, no app — the handler stays as light as it was.
 *
 * Require it before the handler logs or renders anything:
 *   require __DIR__ . '/cma/hackguard.inc';
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

\App\Library\HackBlocker::guardNotFound(dirname(__DIR__));
