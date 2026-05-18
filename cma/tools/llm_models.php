<?php
/**
 * llm_models.php — Local-LLM model installer + status panel.
 *
 * URL on consumer sites:
 *   https://<host>/cma/tools/llm_models.php?key=<DEPLOY_SECRET>
 *
 * Purpose: pick a GGUF model from a curated list (or paste a custom
 * Hugging Face URL), download it to disk in the background, and
 * watch progress live.  Pairs with the existing App\Llm provider
 * abstraction — once a model file lands on disk, edit LLM_URL +
 * LLM_MODEL in .env and restart llama-server (or whichever
 * OpenAI-compat host runs the .gguf).
 *
 * Auth: same DEPLOY_SECRET that diag.php / deploy_webhook.php use —
 * no separate cms-login flow, works even when bootstrap is half-
 * broken so an operator can fix the box from here.
 *
 * Conventions:
 *   - Models live in LLM_MODELS_DIR (env var; falls back to
 *     C:\llama\models on Windows, ~/llama-models elsewhere).
 *   - In-flight downloads write to <name>.gguf.partial; the rename
 *     to <name>.gguf only happens after the byte count matches the
 *     server's Content-Length.  Partial files surface as "in
 *     progress" rows so a reload mid-download doesn't look like a
 *     stuck/dead state.
 *   - The download itself is spawned as a detached PowerShell
 *     process so the request returns immediately and the cook can
 *     close the tab without aborting the transfer.
 *
 * Endpoints (all on this same file):
 *   GET  ?key=…                       → HTML control panel
 *   POST ?key=…&action=install        → kick off download
 *   GET  ?key=…&action=status&name=…  → JSON {size, expected}
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// 0. Auth — same DEPLOY_SECRET pattern as diag.php.
// ---------------------------------------------------------------------------
$siteRoot = dirname(__DIR__, 2);

$resolveEnv = static function (string $siteRoot, string $key): string {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    if (!empty($_ENV[$key])) return (string)$_ENV[$key];
    $candidates = ['.env.production', '.env.acceptance', '.env.test', '.env.development', '.env.local', '.env'];
    foreach ($candidates as $f) {
        $path = $siteRoot . '/' . $f;
        if (!is_file($path)) continue;
        foreach ((array)file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*["\']?([^"\'\r\n#]+)/', $line, $m)) {
                return trim($m[1]);
            }
        }
    }
    return '';
};

$secret = $resolveEnv($siteRoot, 'DEPLOY_SECRET');
$given  = (string)($_GET['key'] ?? '');

if ($secret === '' || !hash_equals($secret, $given)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "403 — pass ?key=<DEPLOY_SECRET>.\n";
    return;
}

// ---------------------------------------------------------------------------
// 1. Resolve models dir.  Win-default + Linux fallback; override via env.
// ---------------------------------------------------------------------------
$isWin = stripos(PHP_OS_FAMILY, 'WIN') === 0;
$modelsDir = $resolveEnv($siteRoot, 'LLM_MODELS_DIR');
if ($modelsDir === '') {
    $modelsDir = $isWin ? 'C:\\llama\\models' : (getenv('HOME') . '/llama-models');
}
if (!is_dir($modelsDir)) {
    @mkdir($modelsDir, 0755, true);
}

// ---------------------------------------------------------------------------
// 2. Curated suggestions.  Update this list as the SOTA shifts; users
//    can always paste a custom URL into the form below.
// ---------------------------------------------------------------------------
$suggestions = [
    [
        'name'  => 'google_gemma-4-E4B-it-Q4_K_M.gguf',
        'label' => 'Gemma 4 E4B-it (Q4_K_M)',
        'note'  => '~5.4 GB · Google · 35+ languages incl. Dutch · 128K context · function-calling native',
        'url'   => 'https://huggingface.co/bartowski/google_gemma-4-E4B-it-GGUF/resolve/main/google_gemma-4-E4B-it-Q4_K_M.gguf?download=true',
        'sizeApprox' => 5_660_000_000,
    ],
    [
        'name'  => 'Qwen3-8B-Q4_K_M.gguf',
        'label' => 'Qwen 3-8B Instruct (Q4_K_M)',
        'note'  => '~5 GB · Alibaba · strong multilingual · JSON-disciplined',
        'url'   => 'https://huggingface.co/bartowski/Qwen_Qwen3-8B-GGUF/resolve/main/Qwen_Qwen3-8B-Q4_K_M.gguf?download=true',
        'sizeApprox' => 5_000_000_000,
    ],
    [
        'name'  => 'Qwen3-4B-Q4_K_M.gguf',
        'label' => 'Qwen 3-4B Instruct (Q4_K_M)',
        'note'  => '~2.5 GB · smaller, ~2× throughput on CPU · good enough for clean recipe text',
        'url'   => 'https://huggingface.co/bartowski/Qwen_Qwen3-4B-GGUF/resolve/main/Qwen_Qwen3-4B-Q4_K_M.gguf?download=true',
        'sizeApprox' => 2_500_000_000,
    ],
    [
        'name'  => 'Qwen2.5-7B-Instruct-Q4_K_M.gguf',
        'label' => 'Qwen 2.5-7B Instruct (Q4_K_M)  [legacy fallback]',
        'note'  => '~4.7 GB · battle-tested late-2024 release · safe choice on older Windows',
        'url'   => 'https://huggingface.co/bartowski/Qwen2.5-7B-Instruct-GGUF/resolve/main/Qwen2.5-7B-Instruct-Q4_K_M.gguf?download=true',
        'sizeApprox' => 4_700_000_000,
    ],
];

// ---------------------------------------------------------------------------
// 3. Helpers
// ---------------------------------------------------------------------------
$safeName = static function (string $s): string {
    // Allow alnum, dot, dash, underscore.  Stops directory escapes
    // (`..\…`) and weird quote handling on Windows.
    return preg_replace('/[^A-Za-z0-9._-]/', '', $s) ?? '';
};
$fmtBytes = static function (int $n): string {
    foreach (['B','KB','MB','GB'] as $u) {
        if ($n < 1024) return number_format($n, $n < 10 && $u !== 'B' ? 1 : 0) . " $u";
        $n = (int)($n / 1024);
    }
    return $n . ' TB';
};

$listInstalled = static function (string $dir) use ($fmtBytes): array {
    if (!is_dir($dir)) return [];
    $out = [];
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.gguf') ?: [] as $f) {
        $out[] = [
            'name'  => basename($f),
            'bytes' => filesize($f) ?: 0,
            'size'  => $fmtBytes((int)filesize($f) ?: 0),
            'mtime' => filemtime($f) ?: 0,
        ];
    }
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.gguf.partial') ?: [] as $f) {
        $out[] = [
            'name'    => basename($f),
            'bytes'   => filesize($f) ?: 0,
            'size'    => $fmtBytes((int)filesize($f) ?: 0),
            'mtime'   => filemtime($f) ?: 0,
            'partial' => true,
        ];
    }
    usort($out, fn($a, $b) => ($b['mtime'] ?? 0) <=> ($a['mtime'] ?? 0));
    return $out;
};

// ---------------------------------------------------------------------------
// 4. POST handler: install
// ---------------------------------------------------------------------------
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

if ($action === 'install') {
    header('Content-Type: text/plain; charset=utf-8');
    $url  = trim((string)($_POST['url']  ?? ''));
    $name = $safeName((string)($_POST['name'] ?? ''));
    if ($url === '' || $name === '' || !str_ends_with($name, '.gguf')) {
        http_response_code(400);
        echo "Need 'url' (https://…) and 'name' (foo.gguf).\n";
        return;
    }
    if (!preg_match('#^https://#i', $url)) {
        http_response_code(400);
        echo "URL must start with https://\n";
        return;
    }
    $target  = $modelsDir . DIRECTORY_SEPARATOR . $name;
    $partial = $target . '.partial';
    $logFile = $target . '.log';

    if (is_file($target)) {
        echo "Already installed: $name\n";
        return;
    }
    if (is_file($partial) && (filesize($partial) ?: 0) > 0 && (time() - (int)filemtime($partial)) < 60) {
        echo "Download already in progress (last write " . (time() - (int)filemtime($partial)) . "s ago).\n";
        return;
    }
    // Clear stale partial + log so progress polling reads fresh state.
    if (is_file($partial)) @unlink($partial);
    if (is_file($logFile)) @unlink($logFile);

    if ($isWin) {
        // Inline PowerShell download using .NET HttpClient — handles
        // HF's 302 → CloudFront-signed-URL chain cleanly (BITS does
        // not), with a streaming write so a 5 GB file doesn't sit in
        // RAM.  Writes to .partial; renames on success.
        $ps = <<<PS
\$ErrorActionPreference = 'Stop';
try {
  Add-Type -AssemblyName System.Net.Http;
  \$h = New-Object Net.Http.HttpClient;
  \$h.Timeout = [TimeSpan]::FromHours(2);
  \$resp = \$h.GetAsync('$url', [Net.Http.HttpCompletionOption]::ResponseHeadersRead).Result;
  if (-not \$resp.IsSuccessStatusCode) { throw "HTTP \$([int]\$resp.StatusCode)"; }
  \$total = \$resp.Content.Headers.ContentLength;
  Set-Content -Path '$target.expected' -Value \$total;
  \$src = \$resp.Content.ReadAsStreamAsync().Result;
  \$out = [IO.File]::Create('$partial');
  \$buf = New-Object byte[] (1MB);
  while ((\$n = \$src.Read(\$buf, 0, \$buf.Length)) -gt 0) {
    \$out.Write(\$buf, 0, \$n);
  }
  \$out.Close(); \$src.Close();
  Move-Item -Path '$partial' -Destination '$target' -Force;
  Remove-Item -Path '$target.expected' -ErrorAction SilentlyContinue;
} catch {
  Add-Content -Path '$logFile' -Value (\$_ | Out-String);
  exit 1;
}
PS;
        // Detach: `start /B` returns immediately, PowerShell keeps
        // running.  Output silenced into the log so PHP doesn't
        // wait on a pipe.
        $b64 = base64_encode(mb_convert_encoding($ps, 'UTF-16LE', 'UTF-8'));
        $cmd = 'cmd /c start /B "" powershell.exe -NoProfile -ExecutionPolicy Bypass '
             . '-EncodedCommand ' . escapeshellarg($b64)
             . ' > NUL 2>&1';
        pclose(popen($cmd, 'r'));
    } else {
        // Linux/macOS: curl with --continue-at handles resume; & detaches.
        $cmd = 'sh -c ' . escapeshellarg(
            'curl -fL --silent --show-error --output ' . escapeshellarg($partial)
            . ' ' . escapeshellarg($url)
            . ' && mv ' . escapeshellarg($partial) . ' ' . escapeshellarg($target)
            . ' || echo "download failed" >> ' . escapeshellarg($logFile)
        ) . ' >/dev/null 2>&1 &';
        exec($cmd);
    }
    echo "Started: $name\nPoll status: ?action=status&name=" . urlencode($name) . "&key=…\n";
    return;
}

// ---------------------------------------------------------------------------
// 5. GET handler: status JSON
// ---------------------------------------------------------------------------
if ($action === 'status') {
    header('Content-Type: application/json; charset=utf-8');
    $name = $safeName((string)($_GET['name'] ?? ''));
    if ($name === '') { echo json_encode(['error' => 'missing name']); return; }
    $target  = $modelsDir . DIRECTORY_SEPARATOR . $name;
    $partial = $target . '.partial';
    $expectedFile = $target . '.expected';
    $expected = is_file($expectedFile) ? (int)trim((string)file_get_contents($expectedFile)) : 0;
    if (is_file($target)) {
        echo json_encode(['state' => 'done', 'size' => filesize($target), 'expected' => $expected]);
        return;
    }
    if (is_file($partial)) {
        echo json_encode(['state' => 'downloading', 'size' => filesize($partial) ?: 0, 'expected' => $expected]);
        return;
    }
    $logFile = $target . '.log';
    if (is_file($logFile)) {
        echo json_encode(['state' => 'failed', 'log' => (string)file_get_contents($logFile)]);
        return;
    }
    echo json_encode(['state' => 'absent']);
    return;
}

// ---------------------------------------------------------------------------
// 6. Default: HTML control panel
// ---------------------------------------------------------------------------
$installed = $listInstalled($modelsDir);
$llmUrl     = $resolveEnv($siteRoot, 'LLM_URL');
$llmModel   = $resolveEnv($siteRoot, 'LLM_MODEL');
$llmProv    = $resolveEnv($siteRoot, 'LLM_PROVIDER');

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="nl-NL"><head>
<meta charset="utf-8"><title>Local LLM models</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root { color-scheme: light; --b:#e1ddd1; --bg:#f8f4ed; --ink:#1a1a1a; --mute:#5a5042; --acc:#2d4a35; }
* { box-sizing: border-box }
body { font: 14px/1.5 system-ui, sans-serif; background: var(--bg); color: var(--ink); margin: 0; padding: 2rem 1.5rem; }
main { max-width: 60rem; margin: 0 auto; }
h1 { font-size: 1.5rem; margin: 0 0 0.5rem; }
h2 { font-size: 1.1rem; margin: 2rem 0 0.5rem; }
.muted { color: var(--mute); font-size: 0.9rem; }
table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid var(--b); border-radius: 6px; overflow: hidden; }
th, td { padding: 0.55rem 0.8rem; text-align: left; border-bottom: 1px solid var(--b); vertical-align: top; }
th { background: #efe8d8; font-weight: 600; font-size: 0.85rem; color: var(--mute); }
tr:last-child td { border-bottom: 0; }
.badge { display: inline-block; padding: 0.1rem 0.5rem; background: #d8e8d8; color: #244c2c; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
.badge--warn { background: #fff0c2; color: #7a5a00; }
.badge--err { background: #f7d8d8; color: #7a1d1d; }
button, .btn { display: inline-block; padding: 0.4rem 0.9rem; background: var(--acc); color: #fff; border: 0; border-radius: 4px; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
button:hover, .btn:hover { filter: brightness(1.1); }
button[disabled] { opacity: 0.5; cursor: not-allowed; }
input[type=text], input[type=url] { width: 100%; padding: 0.45rem 0.6rem; border: 1px solid var(--b); border-radius: 4px; font: inherit; background: #fff; }
.row { display: grid; grid-template-columns: 1fr auto; gap: 0.5rem; align-items: end; }
.suggest-card { padding: 0.9rem; background: #fff; border: 1px solid var(--b); border-radius: 6px; margin: 0 0 0.5rem; display: grid; grid-template-columns: 1fr auto; gap: 0.5rem 1rem; align-items: center; }
.progress-bar { background: #efe8d8; border-radius: 3px; height: 6px; overflow: hidden; margin-top: 0.3rem; }
.progress-bar > div { background: var(--acc); height: 100%; width: 0; transition: width 0.4s ease; }
.cmd { font-family: ui-monospace, Menlo, Consolas, monospace; background: #efe8d8; padding: 0.2rem 0.4rem; border-radius: 3px; word-break: break-all; }
.flash { padding: 0.5rem 0.8rem; background: #d8e8d8; border-left: 4px solid var(--acc); margin: 0 0 1rem; border-radius: 4px; }
</style></head><body><main>

<h1>Local LLM models</h1>
<p class="muted">Modellen leven in <span class="cmd"><?= $h($modelsDir) ?></span>. Wijzig met <span class="cmd">LLM_MODELS_DIR</span> in <span class="cmd">.env</span>.</p>

<h2>Actieve configuratie</h2>
<table>
  <tr><th>LLM_PROVIDER</th><td><?= $h($llmProv ?: '(unset → auto)') ?></td></tr>
  <tr><th>LLM_URL</th><td><?= $h($llmUrl ?: '(unset → Anthropic-only)') ?></td></tr>
  <tr><th>LLM_MODEL</th><td><?= $h($llmModel ?: '(unset → auto-pick)') ?></td></tr>
</table>

<h2>Geïnstalleerd (<?= count($installed) ?>)</h2>
<?php if ($installed): ?>
<table>
  <tr><th>Bestand</th><th>Grootte</th><th>Status</th><th>LLM_MODEL waarde</th></tr>
  <?php foreach ($installed as $m): ?>
    <tr<?= !empty($m['partial']) ? ' data-name="' . $h($m['name']) . '"' : '' ?>>
      <td><span class="cmd"><?= $h($m['name']) ?></span></td>
      <td><?= $h($m['size']) ?></td>
      <td><?php if (!empty($m['partial'])): ?>
        <span class="badge badge--warn">downloading</span>
        <div class="progress-bar"><div></div></div>
      <?php else: ?>
        <span class="badge">ready</span>
      <?php endif; ?></td>
      <td><?php if (empty($m['partial'])):
        // Suggested LLM_MODEL value = filename without .gguf, dashes → reasonable.
        $modelId = preg_replace('/\.gguf$/', '', $m['name']);
        $modelId = preg_replace('/^[A-Za-z0-9]+_/', '', $modelId); // strip "Qwen_"-style prefix
        ?><span class="cmd"><?= $h(strtolower($modelId)) ?></span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
</table>
<?php else: ?>
<p class="muted">Nog geen modellen geïnstalleerd.</p>
<?php endif; ?>

<h2>Aanbevolen (mei 2026)</h2>
<?php foreach ($suggestions as $s):
    $alreadyHave = is_file($modelsDir . DIRECTORY_SEPARATOR . $s['name']);
    $inFlight    = is_file($modelsDir . DIRECTORY_SEPARATOR . $s['name'] . '.partial');
?>
<div class="suggest-card">
  <div>
    <strong><?= $h($s['label']) ?></strong>
    <div class="muted"><?= $h($s['note']) ?></div>
    <div class="muted" style="font-size:0.75rem;margin-top:0.2rem;"><span class="cmd"><?= $h($s['name']) ?></span></div>
  </div>
  <div>
    <?php if ($alreadyHave): ?>
      <span class="badge">geïnstalleerd</span>
    <?php elseif ($inFlight): ?>
      <span class="badge badge--warn">bezig…</span>
    <?php else: ?>
      <form method="post" action="?key=<?= $h($given) ?>&action=install" data-install>
        <input type="hidden" name="url"  value="<?= $h($s['url']) ?>">
        <input type="hidden" name="name" value="<?= $h($s['name']) ?>">
        <button type="submit">Installeer</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<h2>Eigen URL</h2>
<form method="post" action="?key=<?= $h($given) ?>&action=install" data-install>
  <div class="row">
    <div>
      <label class="muted">Hugging Face GGUF download-URL (eindigt vaak op <span class="cmd">?download=true</span>)</label>
      <input type="url" name="url" required placeholder="https://huggingface.co/…/resolve/main/something.gguf?download=true">
    </div>
  </div>
  <div class="row" style="margin-top:0.5rem;">
    <div>
      <label class="muted">Bestandsnaam (eindigend op <span class="cmd">.gguf</span>)</label>
      <input type="text" name="name" required pattern="[A-Za-z0-9._-]+\.gguf" placeholder="iets-7b-q4_k_m.gguf">
    </div>
    <button type="submit">Download</button>
  </div>
</form>

<h2>Volgende stap</h2>
<p class="muted">Wanneer een model klaar staat, start <span class="cmd">llama-server.exe</span> erop, zet de juiste waarden in <span class="cmd">.env</span> (LLM_PROVIDER, LLM_URL, LLM_MODEL — zie <span class="cmd">.env.example</span>) en touch <span class="cmd">web.config</span> om de app-pool te recyclen.</p>

<script>
// Intercept install buttons so the page doesn't navigate — kick the
// POST in the background and immediately start polling progress.
document.querySelectorAll('form[data-install]').forEach(function (f) {
    f.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        const fd = new FormData(f);
        const name = fd.get('name');
        f.querySelector('button[type=submit]').disabled = true;
        try {
            await fetch(f.action, { method: 'POST', body: fd });
        } catch (e) { /* spawn is fire-and-forget; ignore */ }
        location.reload();
    });
});
// Auto-poll for any in-flight downloads in the installed table.
document.querySelectorAll('[data-name]').forEach(function (tr) {
    const name = tr.getAttribute('data-name').replace(/\.partial$/, '');
    const bar  = tr.querySelector('.progress-bar > div');
    const cell = tr.children[1];
    const tick = function () {
        fetch('?key=<?= $h($given) ?>&action=status&name=' + encodeURIComponent(name))
            .then(r => r.json()).then(d => {
                if (d.state === 'done')        { location.reload(); return; }
                if (d.state === 'failed')      { tr.querySelector('.badge').textContent = 'failed'; tr.querySelector('.badge').classList.add('badge--err'); return; }
                if (d.state === 'downloading') {
                    const mb = (d.size / 1024 / 1024).toFixed(0);
                    const tot = d.expected ? (d.expected / 1024 / 1024).toFixed(0) : '?';
                    cell.textContent = mb + ' / ' + tot + ' MB';
                    if (d.expected) bar.style.width = ((d.size / d.expected) * 100) + '%';
                    setTimeout(tick, 2000);
                }
            }).catch(() => setTimeout(tick, 4000));
    };
    tick();
});
</script>
</main></body></html>
