<?php
/**
 * GitHub deploy webhook entry point — generic, ships with cma_platform.
 *
 * URL on consumer sites: https://<host>/cma/deploy.php
 *
 * Configure the GitHub webhook (Settings → Webhooks → Add webhook):
 *   - Payload URL: https://<host>/cma/deploy.php
 *   - Content type: application/json
 *   - Secret: same value as DEPLOY_SECRET on the server
 *   - Events: just the push event
 *
 * The site's .env (or .env.production) must define DEPLOY_SECRET. The
 * server needs git + composer in PATH, and the IIS app pool user
 * needs write access to the site root.
 *
 * Per-site customisation (different branch, no composer, etc.) can
 * be done by passing config to DeployWebhook::handle() — copy this
 * file to the project's cma/ override, or wrap it.
 */

declare(strict_types=1);

\App\Library\DeployWebhook::handle();
