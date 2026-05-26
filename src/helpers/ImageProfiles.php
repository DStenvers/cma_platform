<?php

namespace App\Library;

/**
 * Image Profiles — declarative variant pipeline.
 *
 * Replaces hand-rolled `Image::resize` + `Image::cropAndResize` calls
 * scattered across consumer pages with a single profile-driven helper.
 * Each profile (e.g. `recipe.hero`, `user.avatar`) declares its
 * variants once in /site/data/image-profiles.json and every consumer
 * — upload pipeline, view layer, batch WebP-tool — reads the same
 * truth.
 *
 * JSON shape (see /assets/forms/definitions for similar JSON forms):
 *
 *     {
 *         "profiles": {
 *             "<name>": {
 *                 "naming": "<basename>-{key}",          // optional, default: "{basename}-{key}"
 *                 "source": { "format": "jpg", "quality": 92 },
 *                 "variants": {
 *                     "display": {
 *                         "format": "webp",
 *                         "width":  1600,
 *                         "height": null,                  // null = auto from aspect
 *                         "fit":    "contain",             // contain | cover-center | original
 *                         "quality": 85
 *                     },
 *                     ...
 *                 },
 *                 "srcset": [400, 800, 1200]                // optional widths
 *             }
 *         },
 *         "managed_paths": [
 *             "/assets/uploads/recipes/",
 *             "/assets/uploads/avatars/"
 *         ]
 *     }
 *
 * Usage:
 *
 *     // 1. On upload:
 *     $paths = ImageProfiles::generate('recipe.hero', $tmpPath, $uploadDir, "hero-{$ts}");
 *     // → ['original' => '/.../hero-20260526-original.jpg',
 *     //    'display'  => '/.../hero-20260526-display.webp',
 *     //    'og'       => '/.../hero-20260526-og.webp',
 *     //    'thumb'    => '/.../hero-20260526-thumb.webp',
 *     //    'srcset'   => ['400' => '/.../hero-20260526-w400.webp', ...] ]
 *
 *     // 2. In a view (when srcset configured, emits multi-width):
 *     echo ImageProfiles::imgTag('recipe.hero', 'hero-20260526', $altText, [
 *         'sizes' => '(max-width: 600px) 100vw, 50vw',
 *         'class' => 'recipe-hero__img',
 *     ]);
 *
 *     // 3. WebP-tool path skip:
 *     if (ImageProfiles::isManaged($candidateDir)) continue;
 */
final class ImageProfiles
{
    /** @var array<string,mixed>|null  parsed JSON, lazy-loaded once per request */
    private static ?array $cache = null;

    /**
     * Load + cache the image-profiles JSON. Searches the consumer's
     * /site/data/image-profiles.json (relative to vendor's parent dir);
     * returns an empty config (no profiles, no managed paths) when the
     * file isn't present so callers don't have to null-check.
     *
     * @return array{profiles: array<string,mixed>, managed_paths: list<string>}
     */
    public static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $siteRoot = self::resolveSiteRoot();
        $path     = $siteRoot . '/data/image-profiles.json';
        if (!is_file($path)) {
            return self::$cache = ['profiles' => [], 'managed_paths' => []];
        }
        $raw  = (string)file_get_contents($path);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            error_log('[ImageProfiles] failed to parse ' . $path . ': ' . json_last_error_msg());
            return self::$cache = ['profiles' => [], 'managed_paths' => []];
        }
        return self::$cache = [
            'profiles'       => is_array($data['profiles'] ?? null)      ? $data['profiles']      : [],
            'managed_paths'  => is_array($data['managed_paths'] ?? null) ? $data['managed_paths'] : [],
        ];
    }

    /** Reset the in-memory cache. Mostly for tests + tools_image_profiles. */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /**
     * Get a single profile by name, or null when it doesn't exist.
     *
     * @return array<string,mixed>|null
     */
    public static function getProfile(string $name): ?array
    {
        $cfg = self::load();
        return is_array($cfg['profiles'][$name] ?? null) ? $cfg['profiles'][$name] : null;
    }

    /**
     * Returns true when $dir falls under one of the configured
     * managed_paths — used by the WebP-conversion tool to skip
     * directories that the upload pipeline already handles.
     *
     * Matches by prefix on the path AFTER normalising to forward
     * slashes; both absolute and site-root-relative paths work.
     */
    public static function isManaged(string $dir): bool
    {
        $cfg = self::load();
        $normalised = '/' . trim(str_replace('\\', '/', $dir), '/') . '/';
        foreach ($cfg['managed_paths'] as $managed) {
            $needle = '/' . trim((string)$managed, '/') . '/';
            if (str_contains($normalised, $needle)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate every variant declared on a profile from a single source
     * image. $srcPath is the user-uploaded file; $targetDir is the
     * absolute directory where outputs land; $basename is the stem
     * (e.g. "hero-20260526143012") around which the per-variant
     * suffixes are added.
     *
     * Returns a map: variant-key => absolute output path. The
     * 'original' entry is always present (we copy the source). Missing
     * variants (generation failed) are absent from the map; callers
     * should null-check the keys they need.
     *
     * Srcset widths land under 'srcset' as a width => path map so the
     * caller can persist a single basename and let imgTag() compose
     * the srcset attribute at render time.
     *
     * @return array{
     *     original: string,
     *     display?: string,
     *     og?: string,
     *     thumb?: string,
     *     srcset?: array<int,string>
     * }
     */
    public static function generate(string $profileName, string $srcPath, string $targetDir, string $basename): array
    {
        $profile = self::getProfile($profileName);
        if ($profile === null) {
            error_log('[ImageProfiles] unknown profile ' . $profileName);
            return ['original' => $srcPath];
        }
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
            error_log('[ImageProfiles] cannot create ' . $targetDir);
            return ['original' => $srcPath];
        }

        $out = [];

        // 1. Original — copy untouched. Source format from JSON (or
        //    fall back to the upload's actual extension).
        $sourceCfg = is_array($profile['source'] ?? null) ? $profile['source'] : [];
        $origExt   = self::resolveSourceExt($srcPath, $sourceCfg);
        $origPath  = self::join($targetDir, $basename . '-original.' . $origExt);
        if (@copy($srcPath, $origPath)) {
            $out['original'] = $origPath;
        }

        // 2. Each named variant.
        $variants = is_array($profile['variants'] ?? null) ? $profile['variants'] : [];
        foreach ($variants as $key => $spec) {
            $variantPath = self::join($targetDir, $basename . '-' . $key . '.' . (string)($spec['format'] ?? 'webp'));
            if (self::renderVariant($srcPath, $variantPath, $spec)) {
                $out[$key] = $variantPath;
            }
        }

        // 3. Srcset widths — same rules as a contain variant in WebP,
        //    only width varies. Filenames follow `<basename>-w<NNN>.webp`
        //    so imgTag() can rebuild URLs without consulting JSON again.
        $srcset = is_array($profile['srcset'] ?? null) ? $profile['srcset'] : [];
        if (!empty($srcset)) {
            $defaultQuality = (int)($profile['srcset_quality'] ?? 85);
            $widths = [];
            foreach ($srcset as $w) {
                $width = (int)$w;
                if ($width <= 0) { continue; }
                $widthPath = self::join($targetDir, $basename . '-w' . $width . '.webp');
                if (Image::resize($srcPath, $widthPath, $width, 0, $defaultQuality)
                    || Image::convertToWebP($srcPath, $widthPath, $defaultQuality)) {
                    $widths[$width] = $widthPath;
                }
            }
            if ($widths !== []) {
                ksort($widths);
                $out['srcset'] = $widths;
            }
        }

        return $out;
    }

    /**
     * Build an <img> tag from the profile + a stored basename.
     *
     * If the profile has a `srcset` list the tag emits the standard
     * srcset + sizes pair; falls back to a plain src when not.
     *
     * $basename is the SAME stem passed to generate() — the helper
     * rebuilds variant URLs by re-applying the naming convention.
     *
     * @param array{
     *     base_url?: string,     // URL prefix for the basename, default ''
     *     variant?: string,      // which variant to use as fallback src, default 'display'
     *     sizes?: string,        // sizes attribute for srcset
     *     class?: string, alt?: string, loading?: string, fetchpriority?: string,
     *     width?: int, height?: int, style?: string,
     * } $opts
     */
    public static function imgTag(string $profileName, string $basename, string $alt = '', array $opts = []): string
    {
        $profile = self::getProfile($profileName);
        if ($profile === null) {
            return '<!-- ImageProfiles: unknown profile ' . htmlspecialchars($profileName) . ' -->';
        }
        $baseUrl     = (string)($opts['base_url'] ?? '');
        $fallbackKey = (string)($opts['variant']  ?? 'display');
        $fallbackExt = (string)(($profile['variants'][$fallbackKey]['format'] ?? null) ?? 'webp');
        $src = $baseUrl . $basename . '-' . $fallbackKey . '.' . $fallbackExt;

        $attrs = [
            'src' => $src,
            'alt' => $alt,
        ];

        $srcset = is_array($profile['srcset'] ?? null) ? $profile['srcset'] : [];
        if (!empty($srcset)) {
            $parts = [];
            foreach ($srcset as $w) {
                $width = (int)$w;
                if ($width > 0) {
                    $parts[] = $baseUrl . $basename . '-w' . $width . '.webp ' . $width . 'w';
                }
            }
            if ($parts !== []) {
                $attrs['srcset'] = implode(', ', $parts);
                if (!empty($opts['sizes'])) {
                    $attrs['sizes'] = (string)$opts['sizes'];
                }
            }
        }

        // Pass-through visual attrs. Only emit when set — minder magic.
        foreach (['class', 'loading', 'fetchpriority', 'width', 'height', 'style'] as $passthrough) {
            if (isset($opts[$passthrough]) && $opts[$passthrough] !== '') {
                $attrs[$passthrough] = (string)$opts[$passthrough];
            }
        }

        $rendered = '';
        foreach ($attrs as $k => $v) {
            $rendered .= ' ' . $k . '="' . htmlspecialchars((string)$v, ENT_QUOTES) . '"';
        }
        return '<img' . $rendered . '>';
    }

    // ----- internal helpers -------------------------------------------------

    private static function renderVariant(string $srcPath, string $dstPath, array $spec): bool
    {
        $width   = isset($spec['width'])   ? (int)$spec['width']   : 0;
        $height  = isset($spec['height'])  ? (int)$spec['height']  : 0;
        $quality = isset($spec['quality']) ? (int)$spec['quality'] : 85;
        $fit     = (string)($spec['fit']   ?? 'contain');

        if ($fit === 'cover-center' && $width > 0 && $height > 0) {
            return self::cropCenter($srcPath, $dstPath, $width, $height, $quality);
        }
        // Plain contain (or 'original' with no resize when width=0).
        if ($width > 0 || $height > 0) {
            if (Image::resize($srcPath, $dstPath, $width, $height, $quality)) {
                return true;
            }
            return Image::convertToWebP($srcPath, $dstPath, $quality);
        }
        // No dimensions = just transcode to the target format.
        return Image::convertToWebP($srcPath, $dstPath, $quality);
    }

    /**
     * Centred crop to a fixed aspect, then resize to $tw x $th. Lifted
     * from the inline helper that mijntoprecepten's recipe_save.php used
     * to carry — same maths, just generalised.
     */
    private static function cropCenter(string $src, string $dst, int $tw, int $th, int $q): bool
    {
        $info = Image::getInfo($src);
        if (!is_array($info)) { return false; }
        $w = (int)($info['width']  ?? 0);
        $h = (int)($info['height'] ?? 0);
        if ($w <= 0 || $h <= 0) { return false; }
        $dstAspect = $tw / $th;
        $srcAspect = $w / $h;
        if ($srcAspect > $dstAspect) {
            $cropH = $h;
            $cropW = (int)round($h * $dstAspect);
            $cropX = (int)round(($w - $cropW) / 2);
            $cropY = 0;
        } else {
            $cropW = $w;
            $cropH = (int)round($w / $dstAspect);
            $cropX = 0;
            $cropY = (int)round(($h - $cropH) / 2);
        }
        return Image::cropAndResize($src, $dst, $cropX, $cropY, $cropW, $cropH, $tw, $th, $q);
    }

    private static function resolveSourceExt(string $srcPath, array $sourceCfg): string
    {
        if (!empty($sourceCfg['format'])) {
            return (string)$sourceCfg['format'];
        }
        $info = Image::getInfo($srcPath);
        if (is_array($info)) {
            return match ((int)($info['type'] ?? 0)) {
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG  => 'png',
                IMAGETYPE_WEBP => 'webp',
                default        => 'bin',
            };
        }
        return 'bin';
    }

    private static function join(string $dir, string $name): string
    {
        $clean = rtrim($dir, "/\\") . DIRECTORY_SEPARATOR . $name;
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $clean);
    }

    /** Resolve the consumer site root. */
    private static function resolveSiteRoot(): string
    {
        if (class_exists(Bootstrap::class) && Bootstrap::getRootDir() !== '') {
            return rtrim(Bootstrap::getRootDir(), "/\\");
        }
        // Vendor layout: /site/vendor/stenversonline/platform/src/helpers/
        return rtrim(dirname(__DIR__, 4), "/\\");
    }
}
