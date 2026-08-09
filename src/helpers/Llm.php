<?php
/**
 * Platform LLM helper — shared by every consumer site.
 *
 * Three providers, one interface:
 *   - ollama    — local Ollama (or llama.cpp/LM Studio) at LLM_URL
 *   - anthropic — api.anthropic.com Claude (LLM_KEY or OCR_VISION_KEY)
 *   - openai    — api.openai.com GPT (LLM_KEY)
 *
 * Provider selection (in order):
 *   1. LLM_PROVIDER env var if set (ollama|anthropic|openai)
 *   2. If LLM_URL hostname is api.anthropic.com → anthropic
 *   3. If LLM_URL hostname is api.openai.com    → openai
 *   4. If LLM_KEY OR OCR_VISION_KEY is set AND LLM_URL is empty
 *      → anthropic (the OCR fallback already in use)
 *   5. ollama (back-compat default)
 *
 * Configuration env vars:
 *   - LLM_PROVIDER  — explicit override
 *   - LLM_URL       — Ollama endpoint, e.g. http://localhost:11434/api/generate
 *   - LLM_MODEL     — model name; provider-specific (e.g. "llama3.1:8b",
 *                     "claude-haiku-4-5", "gpt-4o-mini")
 *   - LLM_KEY       — hosted-provider API key. Falls back to OCR_VISION_KEY
 *                     when the provider matches OCR_VISION_PROVIDER.
 *
 * Auto-pick-largest still applies to ollama only — hosted providers
 * default to a sane model per provider when LLM_MODEL is unset.
 */

declare(strict_types=1);

namespace App\Library;

final class Llm
{
    /**
     * Per-request cache for /api/tags so resolveModel() and the
     * /dev/info renderer don't both pay the curl round-trip and
     * disagree on reachability if the second call times out.
     */
    private static ?array $modelsCache = null;
    private static bool $modelsCacheSet = false;

    /** Returns the configured base URL (no trailing slash, no /api/* suffix). */
    public static function baseUrl(): ?string
    {
        $raw = trim((string)($_ENV['LLM_URL'] ?? ''));
        if ($raw === '') {
            return null;
        }
        // Strip any /api/* suffix so /api/tags + /api/generate compose cleanly.
        $base = preg_replace('#/api/[a-z_-]+/?$#i', '', $raw);
        return rtrim((string)$base, '/');
    }

    public static function isConfigured(): bool
    {
        $p = self::provider();
        if ($p === 'ollama') { return self::baseUrl() !== null; }
        // OpenAI-compat at a local endpoint (LM Studio etc.) doesn't
        // need LLM_KEY — server accepts any Bearer token.
        if ($p === 'openai' && self::isLocalEndpoint(self::openAiUrl())) { return true; }
        return self::apiKey() !== '';
    }

    /**
     * Resolve which backend to talk to. Returns 'ollama' | 'anthropic'
     * | 'openai'. Single source of truth — everywhere else asks this.
     */
    public static function provider(): string
    {
        $explicit = strtolower(trim((string)($_ENV['LLM_PROVIDER'] ?? '')));
        if (in_array($explicit, ['ollama', 'anthropic', 'openai'], true)) {
            return $explicit;
        }
        $raw = trim((string)($_ENV['LLM_URL'] ?? ''));
        if ($raw !== '') {
            $host = strtolower((string)parse_url($raw, PHP_URL_HOST));
            if (str_contains($host, 'anthropic.com')) { return 'anthropic'; }
            if (str_contains($host, 'openai.com'))    { return 'openai'; }
            return 'ollama';
        }
        // No LLM_URL, but a key is configured somewhere → hosted.
        if (self::apiKey() !== '') {
            return strtolower(trim((string)($_ENV['OCR_VISION_PROVIDER'] ?? 'anthropic'))) === 'openai'
                ? 'openai' : 'anthropic';
        }
        return 'ollama';
    }

    /**
     * API key for hosted providers. Prefers the dedicated LLM_KEY,
     * falls back to OCR_VISION_KEY when the provider matches the OCR
     * fallback's provider — same vendor, no need to maintain two keys.
     */
    public static function apiKey(): string
    {
        $k = trim((string)($_ENV['LLM_KEY'] ?? ''));
        if ($k !== '') { return $k; }
        $ocrKey      = trim((string)($_ENV['OCR_VISION_KEY']      ?? ''));
        $ocrProvider = strtolower(trim((string)($_ENV['OCR_VISION_PROVIDER'] ?? 'anthropic')));
        if ($ocrKey !== '' && $ocrProvider === self::provider()) {
            return $ocrKey;
        }
        return '';
    }

    /** Default model per provider when LLM_MODEL is unset. */
    public static function defaultModel(): string
    {
        return match (self::provider()) {
            'anthropic' => 'claude-haiku-4-5',
            'openai'    => 'gpt-4o-mini',
            default     => '',
        };
    }

    public static function generateUrl(): ?string
    {
        $base = self::baseUrl();
        return $base === null ? null : $base . '/api/generate';
    }

    public static function tagsUrl(): ?string
    {
        $base = self::baseUrl();
        return $base === null ? null : $base . '/api/tags';
    }

    /**
     * Resolve the OpenAI-compatible chat-completions URL.  Defaults to
     * api.openai.com, but LM Studio / llama-server / Ollama-in-OAI-mode
     * can be pointed at via LLM_URL (e.g. http://localhost:1234/v1).
     * If LLM_URL already ends in /chat/completions we use it verbatim;
     * otherwise we append the standard suffix.
     */
    public static function openAiUrl(): string
    {
        $raw = trim((string)($_ENV['LLM_URL'] ?? ''));
        if ($raw === '') {
            return 'https://api.openai.com/v1/chat/completions';
        }
        $clean = rtrim($raw, '/');
        if (str_ends_with($clean, '/chat/completions')) {
            return $clean;
        }
        // Strip /v1 or /v1/ if present and re-add — covers both
        // "http://host:1234" and "http://host:1234/v1".
        $clean = preg_replace('#/v1$#', '', $clean) ?? $clean;
        return $clean . '/v1/chat/completions';
    }

    /** Loopback / private-net check — used to decide whether LLM_KEY
     *  is required (cloud) or optional (local LM Studio / Ollama). */
    private static function isLocalEndpoint(string $url): bool
    {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if ($host === '') { return false; }
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') { return true; }
        // RFC 1918 + link-local + ::1.  Crude check, fine for our use.
        return (bool)preg_match('#^(10|127|192\.168|172\.(1[6-9]|2[0-9]|3[01]))\.#', $host);
    }

    /**
     * Fetch installed models from /api/tags.
     *
     * Returns an array on success or null on transport/parse error.
     * Each entry: ['name' => 'llama3.1:8b', 'size' => 4661224676,
     *              'parameter_size' => '8B', 'quantization' => 'Q4_K_M',
     *              'family' => 'llama', 'parameter_billions' => 8.0]
     */
    public static function installedModels(): ?array
    {
        if (self::$modelsCacheSet) {
            return self::$modelsCache;
        }
        $url = self::tagsUrl();
        if ($url === null) {
            self::$modelsCacheSet = true;
            return self::$modelsCache = null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $body = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($body === false || $http >= 400) {
            self::$modelsCacheSet = true;
            return self::$modelsCache = null;
        }
        $data = json_decode((string)$body, true);
        if (!is_array($data) || !isset($data['models']) || !is_array($data['models'])) {
            self::$modelsCacheSet = true;
            return self::$modelsCache = null;
        }

        $out = [];
        foreach ($data['models'] as $m) {
            if (!is_array($m) || !isset($m['name'])) {
                continue;
            }
            $details      = is_array($m['details'] ?? null) ? $m['details'] : [];
            $paramSize    = (string)($details['parameter_size'] ?? '');
            $quant        = (string)($details['quantization_level'] ?? '');
            $family       = (string)($details['family'] ?? '');
            $out[] = [
                'name'                => (string)$m['name'],
                'size'                => (int)($m['size'] ?? 0),
                'parameter_size'      => $paramSize,
                'parameter_billions'  => self::paramSizeToBillions($paramSize),
                'quantization'        => $quant,
                'family'              => $family,
            ];
        }
        self::$modelsCacheSet = true;
        return self::$modelsCache = $out;
    }

    /**
     * Picks the largest installed model.
     *
     * Ranking:
     *   1. parameter_billions DESC (parsed from "8B", "70B", "405B" or
     *      "1.5B", "0.5B")
     *   2. size DESC (bytes on disk — quantization-aware tiebreaker)
     *
     * Returns the model tag (e.g. "llama3.1:70b") or null when the list
     * is empty.
     */
    public static function pickBestModel(array $models): ?string
    {
        if ($models === []) {
            return null;
        }
        usort($models, static function (array $a, array $b): int {
            $cmp = ($b['parameter_billions'] ?? 0.0) <=> ($a['parameter_billions'] ?? 0.0);
            if ($cmp !== 0) return $cmp;
            return ($b['size'] ?? 0) <=> ($a['size'] ?? 0);
        });
        return (string)$models[0]['name'];
    }

    /**
     * Resolves which model to send to /api/generate.
     *
     * Order:
     *   1. LLM_MODEL env var if set AND that exact tag is installed.
     *   2. Largest installed model (auto-pick).
     *   3. LLM_MODEL env var even when not installed (let Ollama pull or fail
     *      with a clear error — better than silently swapping in a model the
     *      caller didn't pick).
     *   4. null when nothing is configured and /api/tags is unreachable.
     *
     * The $reason out-param explains the choice for /dev/info display.
     */
    public static function resolveModel(?string &$reason = null): ?string
    {
        $envModel = trim((string)($_ENV['LLM_MODEL'] ?? ''));

        // Hosted providers skip /api/tags entirely — the API accepts
        // whatever model string we hand it (and 404s with a clear
        // error if it's not valid).
        if (self::provider() !== 'ollama') {
            if ($envModel !== '') {
                $reason = 'env LLM_MODEL';
                return $envModel;
            }
            $def = self::defaultModel();
            if ($def !== '') {
                $reason = 'provider default (' . self::provider() . ')';
                return $def;
            }
            $reason = 'no model configured for provider ' . self::provider();
            return null;
        }

        $models = self::installedModels();

        // /api/tags reachable + at least one model installed → auto-pick.
        if (is_array($models) && $models !== []) {
            if ($envModel !== '') {
                foreach ($models as $m) {
                    if ($m['name'] === $envModel) {
                        $reason = 'env LLM_MODEL is installed';
                        return $envModel;
                    }
                }
            }
            $reason = $envModel === ''
                ? 'auto-picked largest installed model'
                : 'LLM_MODEL "' . $envModel . '" not installed — auto-picked largest';
            return self::pickBestModel($models);
        }

        // /api/tags reachable but the list is empty → no models pulled yet.
        if (is_array($models) && $models === []) {
            if ($envModel !== '') {
                $reason = 'no models installed — falling back to env LLM_MODEL (Ollama will need to pull it)';
                return $envModel;
            }
            $reason = 'no models installed and no LLM_MODEL set — run `ollama pull <model>` first';
            return null;
        }

        // /api/tags unreachable.
        if ($envModel !== '') {
            $reason = '/api/tags unreachable — falling back to env LLM_MODEL';
            return $envModel;
        }
        $reason = '/api/tags unreachable and no LLM_MODEL set';
        return null;
    }

    /**
     * Parse a parameter_size string like "8B", "70B", "1.5B", "405B" to
     * billions of params as a float.  Returns 0.0 on parse failure so
     * pickBestModel ranks unknowns last.
     */
    public static function paramSizeToBillions(string $s): float
    {
        $s = trim($s);
        if ($s === '') return 0.0;
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([BMK])?$/i', $s, $m)) {
            $n = (float)$m[1];
            $unit = strtoupper($m[2] ?? 'B');
            return match ($unit) {
                'B' => $n,
                'M' => $n / 1000.0,
                'K' => $n / 1_000_000.0,
                default => $n,
            };
        }
        return 0.0;
    }

    /**
     * Single-shot JSON generation routed through the active provider.
     *
     * When the active provider is Ollama and the local server doesn't
     * respond (connection refused / timeout / HTTP 0), automatically
     * retries against Anthropic Claude if a usable API key is reachable
     * via LLM_KEY or OCR_VISION_KEY (when OCR_VISION_PROVIDER=anthropic
     * or unset). The fallback model is `LLM_FALLBACK_MODEL` if set,
     * otherwise `claude-haiku-4-5`. The result's `diag` array carries
     * `fallback_from` + `fallback_reason` so callers can surface "we
     * used Claude because Ollama was down" in the UI.
     *
     * @param string     $systemPrompt  Instructions for the model.
     * @param string     $userText      Free-text input (paste blob / OCR / URL fetch).
     * Strip wrapping that models like to add even when not asked for —
     * ```json``` / ``` fences and {"antwoord":"…"}-style single-key
     * objects. Returns the inner text on success, the input on failure
     * (no harm done). Idempotent.
     */
    public static function stripWrapping(string $text): string
    {
        $t = trim($text);

        // Code fences: ```json\n{…}\n``` or ```\n…\n```
        if (preg_match('/^```(?:[a-z]+)?\s*\n?(.*?)\n?```$/si', $t, $m)) {
            $t = trim($m[1]);
        }

        // Single-key JSON wrapper — accept the common reply keys models reach for.
        // Require exactly one key so a structured multi-key payload (data, not a
        // wrapper) stays intact and we don't silently drop fields.
        if (strlen($t) >= 2 && $t[0] === '{' && substr($t, -1) === '}') {
            $decoded = json_decode($t, true);
            if (is_array($decoded) && count($decoded) === 1) {
                foreach (['antwoord', 'answer', 'response', 'reply', 'text', 'message', 'output'] as $k) {
                    if (isset($decoded[$k]) && is_string($decoded[$k])) {
                        return trim($decoded[$k]);
                    }
                }
            }
        }

        return $t;
    }

    /**
     * @param array|null  $jsonSchema      Ollama-only schema for `format` field.
     * @param int         $maxTokens       Hard cap on output length.
     * @param string|null $modelOverride   Skip auto-pick and use this model
     *                                     verbatim (callers that want a
     *                                     specific tier — e.g. Haiku for the
     *                                     cooking assistant, Sonnet for parse
     *                                     — pass a model ID here without
     *                                     touching LLM_MODEL env).
     * @return array{ok:bool, text:string, model:string, error:string, diag:array<string,mixed>}
     */
    public static function generate(string $systemPrompt, string $userText, ?array $jsonSchema = null, int $maxTokens = 1024, ?string $modelOverride = null, ?string $userLabel = null): array
    {
        $primary = self::provider();
        $model   = ($modelOverride !== null && $modelOverride !== '')
            ? $modelOverride
            : self::resolveModel();
        if ($model === null) {
            // Even with no Ollama model resolved, the Anthropic fallback can still rescue us.
            $fbKey = self::anthropicFallbackKey();
            if ($primary === 'ollama' && $fbKey !== '') {
                return self::generateAnthropicFallback($systemPrompt, $userText, $maxTokens, $fbKey, 'no model resolved for ollama');
            }
            return ['ok' => false, 'text' => '', 'model' => '', 'error' => 'no model configured', 'diag' => ['provider' => $primary]];
        }

        $result = match ($primary) {
            'anthropic' => self::generateAnthropic($systemPrompt, $userText, $model, $maxTokens),
            'openai'    => self::generateOpenAi($systemPrompt, $userText, $model, $maxTokens),
            default     => self::generateOllama($systemPrompt, $userText, $model, $jsonSchema, $maxTokens),
        };

        // Local-LLM safety net: zodra de lokale server faalt — om
        // welke reden dan ook (transport-down, 4xx, 5xx, model-not-
        // found, schema rejected) — en er ligt een Anthropic-key in
        // .env, dan transparant overschakelen op Claude in plaats
        // van de cook met een foutmelding op te zadelen.
        //
        // De eerdere strictere `isConnectionFailure`-gate stopte bij
        // logische 4xx-fouten ("dat HOORT te surfacen"), maar voor de
        // cook is het resultaat hetzelfde: geen geparseerd recept. Liever
        // een werkende Claude-pass dan een geslaagde diagnose.
        //
        // Triggers voor zowel ollama ALS openai-naar-een-lokale-host
        // (LM Studio / llama.cpp / Ollama in OpenAI-compat mode).
        // Anthropic-as-primary valt obviously niet op zichzelf terug.
        $localPrimary = $primary === 'ollama'
            || ($primary === 'openai' && self::isLocalEndpoint(self::openAiUrl()));
        if (!$result['ok'] && $localPrimary) {
            $fbKey = self::anthropicFallbackKey();
            if ($fbKey !== '') {
                return self::generateAnthropicFallback($systemPrompt, $userText, $maxTokens, $fbKey, (string)$result['error']);
            }
        }

        return $result;
    }

    /**
     * Anthropic key resolver that ignores the active-provider gate in
     * apiKey(). Used by the Ollama-down fallback path so a cook with
     * OCR_VISION_KEY set (for OCR fallback) automatically gets it
     * reused here too.
     */
    /**
     * The model used for image questions. A text model cannot see pictures, so
     * it is configured separately; falls back to the normal model for setups
     * where one multimodal model does both (gemma3, qwen2-vl, llava…).
     */
    public static function visionModel(): string
    {
        $explicit = trim((string)($_ENV['LLM_VISION_MODEL'] ?? getenv('LLM_VISION_MODEL') ?: ''));
        return $explicit !== '' ? $explicit : self::defaultModel();
    }

    /**
     * Ask the model about images. $images are absolute paths to local files.
     * Returns the assistant text, or null when unavailable/unusable.
     *
     * Speaks both dialects: Ollama takes base64 strings in `images`, while
     * OpenAI-compatible endpoints take data-URI `image_url` content parts.
     */
    public static function vision(string $systemPrompt, string $userText, array $images, float $temperature = 0.0): ?string
    {
        if (!self::isConfigured() || $images === []) {
            return null;
        }
        $encoded = [];
        foreach ($images as $path) {
            $bytes = @file_get_contents($path);
            if ($bytes !== false && $bytes !== '') {
                $encoded[] = base64_encode($bytes);
            }
        }
        if ($encoded === []) {
            return null;
        }

        $provider = self::provider();
        $model    = self::visionModel();
        $headers  = ['Content-Type: application/json'];

        if ($provider === 'ollama') {
            $url = rtrim((string)self::baseUrl(), '/');
            $url = preg_replace('#/api/.*$#', '', $url) . '/api/chat';
            $payload = [
                'model'    => $model !== '' ? $model : 'llava',
                'stream'   => false,
                'options'  => ['temperature' => $temperature],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userText, 'images' => $encoded],
                ],
            ];
        } else {
            $key = self::apiKey();
            if ($key === '') {
                return null;
            }
            $content = [['type' => 'text', 'text' => $userText]];
            foreach ($encoded as $b64) {
                $content[] = ['type' => 'image_url',
                              'image_url' => ['url' => 'data:image/jpeg;base64,' . $b64]];
            }
            if ($provider === 'anthropic') {
                $url = 'https://api.anthropic.com/v1/messages';
                $headers[] = 'x-api-key: ' . $key;
                $headers[] = 'anthropic-version: 2023-06-01';
                $blocks = [['type' => 'text', 'text' => $userText]];
                foreach ($encoded as $b64) {
                    $blocks[] = ['type' => 'image', 'source' => [
                        'type' => 'base64', 'media_type' => 'image/jpeg', 'data' => $b64]];
                }
                $payload = [
                    'model'      => $model !== '' ? $model : 'claude-haiku-4-5',
                    'max_tokens' => 1024,
                    'system'     => $systemPrompt,
                    'messages'   => [['role' => 'user', 'content' => $blocks]],
                ];
            } else {
                $url = self::openAiUrl();
                $headers[] = 'Authorization: Bearer ' . $key;
                $payload = [
                    'model'       => $model !== '' ? $model : 'gpt-4o-mini',
                    'temperature' => $temperature,
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $content],
                    ],
                ];
            }
        }

        $r = self::curlPost($url, $payload, $headers, 180);
        if ($r[0] === null || $r[1] !== 200) {
            return null;
        }
        $data = json_decode((string)$r[0], true);
        if (!is_array($data)) {
            return null;
        }
        $text = $data['choices'][0]['message']['content']   // OpenAI-compatible
             ?? $data['message']['content']                  // Ollama /api/chat
             ?? $data['content'][0]['text']                  // Anthropic messages
             ?? $data['response']                            // Ollama /api/generate
             ?? null;
        return is_string($text) && trim($text) !== '' ? $text : null;
    }

    /**
     * Prefix the user text with an optional label. Consumer apps use this to
     * mark what they are sending ("--- RECIPE TEXT ---", "--- INVOICE ---");
     * the platform itself assumes no domain.
     */
    private static function labelled(string $userText, ?string $label): string
    {
        $label = $label === null ? '' : trim($label);
        return $label === '' ? $userText : $label . "\n" . $userText;
    }

    public static function anthropicFallbackKey(): string
    {
        // Read $_ENV first, then the process environment: consumer sites load
        // .env into $_ENV, but a server may just as well set real env vars.
        $read = static fn(string $name, string $default = ''): string
            => trim((string)($_ENV[$name] ?? (getenv($name) ?: $default)));

        $k = $read('LLM_KEY');
        if ($k !== '') { return $k; }
        $ocrKey      = $read('OCR_VISION_KEY');
        $ocrProvider = strtolower($read('OCR_VISION_PROVIDER', 'anthropic'));
        if ($ocrKey !== '' && $ocrProvider === 'anthropic') {
            return $ocrKey;
        }
        return '';
    }

    /**
     * Heuristic "did we fail because we couldn't reach the server?"
     * Matches curl's transport-level errors so we only fall back when
     * the LLM endpoint is genuinely down, not when the server answered
     * with a 4xx/5xx that includes a real error message.
     */
    public static function isConnectionFailure(string $errorMessage): bool
    {
        if ($errorMessage === '') { return false; }
        $needles = [
            'Failed to connect',
            'Could not connect',
            'Connection refused',
            'Connection reset',
            'Connection timed out',
            'Operation timed out',
            'Couldn\'t resolve host',
            'Could not resolve host',
            'No route to host',
        ];
        foreach ($needles as $n) {
            if (stripos($errorMessage, $n) !== false) { return true; }
        }
        // HTTP 0 from curlPost means curl never got a response from the server.
        return (bool)preg_match('/\bHTTP 0\b/', $errorMessage);
    }

    private static function generateAnthropicFallback(string $systemPrompt, string $userText, int $maxTokens, string $apiKey, string $reason): array
    {
        $model = trim((string)($_ENV['LLM_FALLBACK_MODEL'] ?? '')) ?: 'claude-haiku-4-5';
        error_log('[Llm] Ollama unreachable — falling back to Anthropic (' . $model . '). Reason: ' . $reason);
        $result = self::generateAnthropicRaw($systemPrompt, $userText, $model, $maxTokens, $apiKey);
        $result['diag']['fallback_from']   = 'ollama';
        $result['diag']['fallback_reason'] = $reason;
        return $result;
    }

    private static function generateOllama(string $systemPrompt, string $userText, string $model, ?array $schema, int $maxTokens): array
    {
        $url = self::generateUrl();
        if ($url === null) {
            return ['ok' => false, 'text' => '', 'model' => $model, 'error' => 'LLM_URL not set', 'diag' => []];
        }
        $payload = [
            'model'   => $model,
            'prompt'  => $systemPrompt . "\n\n" . self::labelled($userText, $userLabel),
            'stream'  => false,
            'options' => [
                'temperature' => 0.1,
                'num_predict' => $maxTokens,
                'num_ctx'     => 8192,
            ],
        ];
        if ($schema !== null) { $payload['format'] = $schema; }

        [$body, $http, $err] = self::curlPost($url, $payload, ['Content-Type: application/json'], 90);
        if ($body === null) {
            return ['ok' => false, 'text' => '', 'model' => $model, 'error' => "HTTP $http $err", 'diag' => []];
        }
        $env = json_decode($body, true);
        $text = is_array($env) ? (string)($env['response'] ?? '') : '';
        $doneReason = is_array($env) ? (string)($env['done_reason'] ?? '') : '';
        $diag = is_array($env) ? [
            'eval_count'       => $env['eval_count'] ?? null,
            'eval_duration_ms' => isset($env['eval_duration']) ? (int)round((int)$env['eval_duration'] / 1_000_000) : null,
            'done_reason'      => $doneReason,
        ] : [];
        // Ollama signals "hit num_predict" via done_reason='length'.
        // Same symptom as Anthropic's stop_reason='max_tokens' — the
        // text is partial JSON downstream. Surface it as a structured
        // truncation error so the caller can show the user the
        // 'Poeh zeg' message instead of 'unparseable output'.
        if ($doneReason === 'length') {
            return [
                'ok'    => false,
                'text'  => $text,
                'model' => $model,
                'error' => 'truncated: hit num_predict=' . $maxTokens . ' ceiling (output ' . ($diag['eval_count'] ?? '?') . ' tok)',
                'diag'  => $diag,
            ];
        }
        return ['ok' => $text !== '', 'text' => $text, 'model' => $model, 'error' => '', 'diag' => $diag];
    }

    private static function generateAnthropic(string $systemPrompt, string $userText, string $model, int $maxTokens): array
    {
        $key = self::apiKey();
        if ($key === '') {
            return ['ok' => false, 'text' => '', 'model' => $model, 'error' => 'LLM_KEY (or OCR_VISION_KEY) not set', 'diag' => []];
        }
        return self::generateAnthropicRaw($systemPrompt, $userText, $model, $maxTokens, $key);
    }

    private static function generateAnthropicRaw(string $systemPrompt, string $userText, string $model, int $maxTokens, string $apiKey): array
    {
        $payload = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'system'     => $systemPrompt . "\n\nGeef je antwoord ALLEEN als geldige JSON, geen markdown-fences, geen prose, geen commentaar.",
            'messages'   => [[
                'role'    => 'user',
                'content' => self::labelled($userText, $userLabel),
            ]],
            'temperature' => 0.1,
        ];
        [$body, $http, $err] = self::curlPost('https://api.anthropic.com/v1/messages', $payload, [
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ], 90);
        if ($body === null) {
            return ['ok' => false, 'text' => '', 'model' => $model, 'error' => "HTTP $http $err", 'diag' => []];
        }
        $env = json_decode($body, true);
        if (isset($env['error']['message'])) {
            return ['ok' => false, 'text' => '', 'model' => $model, 'error' => 'anthropic: ' . (string)$env['error']['message'], 'diag' => []];
        }
        $text = (string)($env['content'][0]['text'] ?? '');
        $stopReason = (string)($env['stop_reason'] ?? '');
        $diag = [
            'input_tokens'  => $env['usage']['input_tokens']  ?? null,
            'output_tokens' => $env['usage']['output_tokens'] ?? null,
            'stop_reason'   => $stopReason,
        ];
        // stop_reason='max_tokens' means Sonnet/Haiku hit the
        // max_tokens ceiling mid-output. Earlier we returned the
        // truncated text as if it were complete — json_decode then
        // failed on the partial JSON and the cook saw 'De LLM gaf
        // onbruikbare output terug' instead of the actual cause.
        // Surface it as a structured error so the parse handler can
        // tell the caller the input was too long for the model budget.
        if ($stopReason === 'max_tokens') {
            return [
                'ok'    => false,
                'text'  => $text,
                'model' => $model,
                'error' => 'truncated: hit max_tokens=' . $maxTokens . ' ceiling (output ' . ($diag['output_tokens'] ?? '?') . ' tok)',
                'diag'  => $diag,
            ];
        }
        return ['ok' => $text !== '', 'text' => $text, 'model' => $model, 'error' => '', 'diag' => $diag];
    }

    private static function generateOpenAi(string $systemPrompt, string $userText, string $model, int $maxTokens): array
    {
        $key = self::apiKey();
        // LM Studio (and llama.cpp's server, and Ollama in OpenAI-compat
        // mode) all accept any non-empty Bearer token.  Default to a
        // placeholder so the cook doesn't have to set LLM_KEY to make
        // a local server work.  Real api.openai.com calls still need a
        // valid key — apiKey() returns '' there and we bail.
        $url = self::openAiUrl();
        $isLocal = self::isLocalEndpoint($url);
        if ($key === '' && !$isLocal) {
            return ['ok' => false, 'text' => '', 'model' => $model, 'error' => 'LLM_KEY not set', 'diag' => []];
        }
        if ($key === '') { $key = 'lm-studio'; }
        $payload = [
            'model'           => $model,
            'messages'        => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => self::labelled($userText, $userLabel)],
            ],
            'temperature'     => 0.1,
            'max_tokens'      => $maxTokens,
            'response_format' => ['type' => 'json_object'],
        ];
        [$body, $http, $err] = self::curlPost($url, $payload, [
            'authorization: Bearer ' . $key,
            'content-type: application/json',
        ], 90);
        if ($body === null) {
            return ['ok' => false, 'text' => '', 'model' => $model, 'error' => "HTTP $http $err", 'diag' => []];
        }
        $env = json_decode($body, true);
        if (isset($env['error']['message'])) {
            return ['ok' => false, 'text' => '', 'model' => $model, 'error' => 'openai: ' . (string)$env['error']['message'], 'diag' => []];
        }
        $text = (string)($env['choices'][0]['message']['content'] ?? '');
        $finishReason = (string)($env['choices'][0]['finish_reason'] ?? '');
        $diag = [
            'prompt_tokens'     => $env['usage']['prompt_tokens']     ?? null,
            'completion_tokens' => $env['usage']['completion_tokens'] ?? null,
            'finish_reason'     => $finishReason,
        ];
        // Same truncation guard as the Anthropic path — OpenAI calls
        // it 'length' instead of 'max_tokens', but the symptom is the
        // same: partial JSON that json_decode rejects downstream.
        if ($finishReason === 'length') {
            return [
                'ok'    => false,
                'text'  => $text,
                'model' => $model,
                'error' => 'truncated: hit max_tokens=' . $maxTokens . ' ceiling (output ' . ($diag['completion_tokens'] ?? '?') . ' tok)',
                'diag'  => $diag,
            ];
        }
        return ['ok' => $text !== '', 'text' => $text, 'model' => $model, 'error' => '', 'diag' => $diag];
    }

    /**
     * @return array{0: string|null, 1: int, 2: string}  [body or null on failure, HTTP code, curl/error excerpt]
     */
    private static function curlPost(string $url, array $payload, array $headers, int $timeout): array
    {
        $body  = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $start = microtime(true);

        // First attempt: full TLS verification.
        $r = self::curlPostOnce($url, (string)$body, $headers, $timeout, true);

        // TLS-CA fallback — many
        // Windows PHP installs ship without curl.cainfo set so the
        // first hosted-API call fails on cert verification. Retry
        // without peer-verify on dev/test boxes, log loudly so prod
        // can't ship in the same broken state.
        $isCaError = $r[0] === null && (
            stripos($r[2], 'unable to get local issuer certificate') !== false
            || stripos($r[2], 'CAfile') !== false
            || stripos($r[2], 'self-signed') !== false
        );
        if ($isCaError && self::isNonProd()) {
            error_log('[Llm] TLS CA store missing — retrying without peer verification (dev only). '
                    . 'Configure curl.cainfo in php.ini before deploying.');
            $r = self::curlPostOnce($url, (string)$body, $headers, $timeout, false);
        }

        // Log to api_call_log (best-effort, never breaks the call).
        // Provider derived from URL host: api.anthropic.com → anthropic,
        // api.openai.com → openai, anything else → ollama (local).
        $provider = str_contains($url, 'anthropic.com') ? 'anthropic'
                  : (str_contains($url, 'openai.com')   ? 'openai' : 'ollama');
        $model    = (string)($payload['model'] ?? '');
        if (class_exists('\\App\\Models\\ApiCallLog')) {
        \App\Models\ApiCallLog::record(
            $provider,
            $url,
            (int)$r[1],
            (int) round((microtime(true) - $start) * 1000),
            $r[0] !== null,
            strlen((string)$body),
            $r[0] !== null ? strlen((string)$r[0]) : 0,
            'llm_generate',
            $model !== '' ? $model : null,
            class_exists('\\App\\Auth') ? \App\Auth::userId() : null,
            null,
            $r[0] === null ? (string)$r[2] : ''
        );
        }

        return $r;
    }

    /**
     * @return array{0: string|null, 1: int, 2: string}
     */
    private static function curlPostOnce(string $url, string $body, array $headers, int $timeout, bool $verifyPeer): array
    {
        $ch = curl_init($url);
        if ($ch === false) { return [null, 0, 'curl_init failed']; }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => $verifyPeer,
            CURLOPT_SSL_VERIFYHOST => $verifyPeer ? 2 : 0,
        ]);
        $resp = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = (string)curl_error($ch);
        // PHP 8.0+ closes the handle automatically when $ch goes out
        // of scope; curl_close() is deprecated in 8.5.
        if ($resp === false) { return [null, $http, "curl error: $err"]; }
        if ($http >= 400)    { return [null, $http, mb_substr((string)$resp, 0, 300)]; }
        return [(string)$resp, $http, ''];
    }

    private static function isNonProd(): bool
    {
        if (class_exists(\App\Library\Application::class)) {
            $env = strtoupper((string)\App\Library\Application::get('omgeving', 'L'));
            return in_array($env, ['L', 'O', 'T', 'A'], true);
        }
        return strtoupper((string)($_ENV['APP_ENVIRONMENT'] ?? 'L')) !== 'P';
    }
}
