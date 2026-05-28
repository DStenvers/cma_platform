<?php
/**
 * Curated GGUF model suggestions — single source of truth.
 *
 * Required by:
 *   - cma/tools/tools_llm.php   (the LLM-management page: the Ollama card lists
 *                                 every entry's `ollama_tag` as a `ollama pull …`
 *                                 command; index 0 also gets interpolated into
 *                                 the per-engine setup-step `code` blocks)
 *
 * Ordering matters: tools_llm.php uses index 0 as the "current recommendation".
 * When a newer-better GGUF lands, prepend it here and both surfaces follow
 * automatically — no per-file string edits.
 *
 * Each row:
 *   name        — filename on disk (the bartowski-style GGUF basename)
 *   label       — human-readable label for the UI
 *   note        — one-line context (size, vendor, strengths)
 *   url         — Hugging Face direct-download URL (resolve/main/<file>?download=true)
 *   sizeApprox  — bytes (for the downloader progress bar's initial estimate)
 *   ollama_tag  — closest Ollama-registry equivalent for tools that use Ollama
 *                 instead of llama.cpp.  Optional — falls back to a sensible
 *                 default in tools_llm.php when missing.
 */

declare(strict_types=1);

return [
    [
        'name'       => 'google_gemma-4-E4B-it-Q4_K_M.gguf',
        'label'      => 'Gemma 4 E4B-it (Q4_K_M)',
        'note'       => '~5.4 GB · Google · 35+ languages incl. Dutch · 128K context · function-calling native',
        'url'        => 'https://huggingface.co/bartowski/google_gemma-4-E4B-it-GGUF/resolve/main/google_gemma-4-E4B-it-Q4_K_M.gguf?download=true',
        'sizeApprox' => 5_660_000_000,
        'ollama_tag' => 'gemma3:4b',
    ],
    [
        'name'       => 'Qwen3-8B-Q4_K_M.gguf',
        'label'      => 'Qwen 3-8B Instruct (Q4_K_M)',
        'note'       => '~5 GB · Alibaba · strong multilingual · JSON-disciplined',
        'url'        => 'https://huggingface.co/bartowski/Qwen_Qwen3-8B-GGUF/resolve/main/Qwen_Qwen3-8B-Q4_K_M.gguf?download=true',
        'sizeApprox' => 5_000_000_000,
        'ollama_tag' => 'qwen3:8b',
    ],
    [
        'name'       => 'Qwen3-4B-Q4_K_M.gguf',
        'label'      => 'Qwen 3-4B Instruct (Q4_K_M)',
        'note'       => '~2.5 GB · smaller, ~2× throughput on CPU · good enough for clean recipe text',
        'url'        => 'https://huggingface.co/bartowski/Qwen_Qwen3-4B-GGUF/resolve/main/Qwen_Qwen3-4B-Q4_K_M.gguf?download=true',
        'sizeApprox' => 2_500_000_000,
        'ollama_tag' => 'qwen3:4b',
    ],
    [
        'name'       => 'Qwen2.5-7B-Instruct-Q4_K_M.gguf',
        'label'      => 'Qwen 2.5-7B Instruct (Q4_K_M)  [legacy fallback]',
        'note'       => '~4.7 GB · battle-tested late-2024 release · safe choice on older Windows',
        'url'        => 'https://huggingface.co/bartowski/Qwen2.5-7B-Instruct-GGUF/resolve/main/Qwen2.5-7B-Instruct-Q4_K_M.gguf?download=true',
        'sizeApprox' => 4_700_000_000,
        'ollama_tag' => 'qwen2.5:7b',
    ],
];
