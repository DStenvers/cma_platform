<?php
/**
 * GitHub deploy webhook entry point — generic, ships with cma_platform.
 *
 * URL on consumer sites: https://<host>/cma/tools/deploy_webhook.php
 *
 * Unlike most cma/tools/* files, this is NOT a developer-only UI tool:
 * it's the receiver for an inbound HTTPS POST from GitHub. Authentication
 * is handled inside DeployWebhook::handle() via HMAC-SHA256 against
 * $_ENV['DEPLOY_SECRET'] — no CMA login is required (and would defeat
 * the purpose, since GitHub doesn't have one).
 *
 * Configure the GitHub webhook (Settings → Webhooks → Add webhook):
 *   - Payload URL: https://<host>/cma/tools/deploy_webhook.php
 *   - Content type: application/json
 *   - Secret: same value as DEPLOY_SECRET on the server's .env
 *   - Events: just the push event
 *
 * The site's .env must define DEPLOY_SECRET. The server needs git +
 * composer in PATH, and the IIS app pool user needs write access to
 * the site root.
 *
 * Per-environment branch: set DEPLOY_BRANCH in .env (e.g. "staging"
 * on the test server, leave unset on production so it defaults to
 * "main"). One repo can fan out to multiple environments by
 * configuring two GitHub webhooks — one per host — both with the
 * same secret; each only deploys its own branch and skips the others.
 *
 * Other per-site customisation (no composer, different branch
 * regardless of env, etc.) is done by passing config to
 * DeployWebhook::handle() — copy this file into the project's
 * cma/tools/ override, or wrap it.
 */

declare(strict_types=1);

\App\Library\DeployWebhook::handle();
