<?php

namespace App\Library;

/**
 * Responsive Image Helper
 *
 * Generates WebP variants at multiple widths for responsive <img srcset> output.
 *
 * ## Storage Layout
 *
 * For each source image, variants are stored in a `.responsive/` subdirectory:
 *
 *     /images/photo.jpg                    <- original (kept)
 *     /images/.responsive/photo-400w.webp  <- 400px wide variant
 *     /images/.responsive/photo-800w.webp  <- 800px wide variant
 *     /images/.responsive/photo-1200w.webp <- 1200px wide variant
 *     /images/.responsive/photo.webp       <- full-size WebP
 *
 * ## Front-end Usage
 *
 * ### PHP (server-rendered pages)
 *
 *     use App\Library\ResponsiveImage;
 *
 *     // Simple — auto-generates srcset with all available variants:
 *     echo ResponsiveImage::imgTag('/images/photo.jpg', 'Beschrijving');
 *
 *     // With sizing hints (for layout):
 *     echo ResponsiveImage::imgTag('/images/photo.jpg', 'Beschrijving', '(max-width: 600px) 100vw, 50vw');
 *
 *     // With CSS class and extra attributes:
 *     echo ResponsiveImage::imgTag('/images/photo.jpg', 'Beschrijving', '100vw', 'hero-image', [
 *         'width' => 1200,
 *         'height' => 800,
 *         'fetchpriority' => 'high',
 *     ]);
 *
 *     // Output example:
 *     // <img src="/images/.responsive/photo.webp"
 *     //      srcset="/images/.responsive/photo-400w.webp 400w,
 *     //             /images/.responsive/photo-800w.webp 800w,
 *     //             /images/.responsive/photo-1200w.webp 1200w"
 *     //      sizes="100vw"
 *     //      alt="Beschrijving"
 *     //      class="hero-image"
 *     //      loading="lazy">
 *
 * ### JavaScript (dynamic/AJAX content)
 *
 *     // Build the URL manually using the same pattern:
 *     const baseUrl = '/images/photo.jpg';
 *     const name = baseUrl.replace(/\.[^.]+$/, '').split('/').pop();
 *     const dir = baseUrl.substring(0, baseUrl.lastIndexOf('/'));
 *     const responsiveDir = dir + '/.responsive';
 *
 *     // Full-size WebP:
 *     const webpUrl = responsiveDir + '/' + name + '.webp';
 *
 *     // Specific width:
 *     const url800 = responsiveDir + '/' + name + '-800w.webp';
 *
 *     // Build srcset:
 *     const srcset = [400, 800, 1200]
 *         .map(w => `${responsiveDir}/${name}-${w}w.webp ${w}w`)
 *         .join(', ');
 *
 * ### CSS Background Images
 *
 *     // Use the full-size WebP URL:
 *     .hero { background-image: url('/images/.responsive/photo.webp'); }
 *
 *     // Or with image-set for responsive backgrounds:
 *     .hero {
 *         background-image: image-set(
 *             url('/images/.responsive/photo-400w.webp') 400w,
 *             url('/images/.responsive/photo-800w.webp') 800w,
 *             url('/images/.responsive/photo-1200w.webp') 1200w
 *         );
 *     }
 *
 * ## Generating Variants
 *
 * Variants are generated automatically on:
 * - Image upload via CMA form (form_api.php)
 * - Image edit in file browser (rotate/crop/resize)
 *
 * For existing images, use the batch conversion tool:
 * CMA > Tools > Developer > WebP conversie
 *
 * Or programmatically:
 *     ResponsiveImage::generate('/full/path/to/image.jpg');
 *     ResponsiveImage::batchGenerate('/full/path/to/images/');
 */
class ResponsiveImage
{
    public const SIZES = [400, 800, 1200];
    public const RESPONSIVE_DIR = '.responsive';
    public const DEFAULT_QUALITY = 85;

    /**
     * Generate all WebP variants for an image
     *
     * @param string $sourcePath Full filesystem path to source image
     * @param int $quality WebP quality (1-100)
     * @return array ['success' => bool, 'variants' => [...], 'error' => string]
     */
    public static function generate(string $sourcePath, int $quality = self::DEFAULT_QUALITY): array
    {
        if (!Image::isWebPSupported()) {
            return ['success' => false, 'variants' => [], 'error' => 'WebP niet ondersteund door GD'];
        }

        if (!file_exists($sourcePath)) {
            return ['success' => false, 'variants' => [], 'error' => 'Bronbestand niet gevonden'];
        }

        $info = Image::getInfo($sourcePath);
        if ($info === false) {
            $fileSize = filesize($sourcePath);
            $finfo = function_exists('finfo_open') ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $sourcePath) : 'onbekend';
            $detail = 'Bestand: ' . round($fileSize / 1024) . ' KB, MIME: ' . $finfo;
            return ['success' => false, 'variants' => [], 'error' => 'Kan afbeelding niet lezen (' . $detail . ')'];
        }

        $responsiveDir = self::getResponsiveDir($sourcePath);
        if (!is_dir($responsiveDir)) {
            if (!@mkdir($responsiveDir, 0775, true)) {
                return ['success' => false, 'variants' => [], 'error' => 'Kan .responsive map niet aanmaken'];
            }
        }

        $baseName = pathinfo($sourcePath, PATHINFO_FILENAME);
        $variants = [];

        // Generate width variants (only for sizes smaller than original)
        foreach (self::SIZES as $width) {
            if ($width < $info['width']) {
                $variantPath = $responsiveDir . DIRECTORY_SEPARATOR . $baseName . '-' . $width . 'w.webp';
                if (Image::resize($sourcePath, $variantPath, $width, 0, $quality)) {
                    $variants[] = ['width' => $width, 'path' => $variantPath];
                }
            }
        }

        // Generate full-size WebP
        $fullWebP = $responsiveDir . DIRECTORY_SEPARATOR . $baseName . '.webp';
        if (Image::convertToWebP($sourcePath, $fullWebP, $quality)) {
            $variants[] = ['width' => $info['width'], 'path' => $fullWebP, 'full' => true];
        }

        if (empty($variants)) {
            $lastErr = error_get_last();
            $errMsg = $lastErr ? $lastErr['message'] : 'Geen varianten gegenereerd';
            return ['success' => false, 'variants' => [], 'error' => $errMsg];
        }

        return ['success' => true, 'variants' => $variants];
    }

    /**
     * Normalize a URL path by collapsing double slashes (except after protocol)
     */
    private static function normalizePath(string $url): string
    {
        return preg_replace('#(?<!:)//#', '/', $url);
    }

    /**
     * Get .responsive directory path for an image
     *
     * @param string $imagePath Full filesystem path
     * @return string Path to .responsive directory
     */
    public static function getResponsiveDir(string $imagePath): string
    {
        return dirname($imagePath) . DIRECTORY_SEPARATOR . self::RESPONSIVE_DIR;
    }

    /**
     * Get WebP URL for a specific width variant
     *
     * @param string $imageUrl URL path (e.g., /images/photo.jpg)
     * @param int $width Target width
     * @return string URL to width variant (e.g., /images/.responsive/photo-800w.webp)
     */
    public static function getVariantUrl(string $imageUrl, int $width): string
    {
        $dir = dirname($imageUrl);
        $baseName = pathinfo($imageUrl, PATHINFO_FILENAME);
        return self::normalizePath($dir . '/' . self::RESPONSIVE_DIR . '/' . $baseName . '-' . $width . 'w.webp');
    }

    /**
     * Get full-size WebP URL
     *
     * @param string $imageUrl URL path (e.g., /images/photo.jpg)
     * @return string URL to full-size WebP (e.g., /images/.responsive/photo.webp)
     */
    public static function getWebPUrl(string $imageUrl): string
    {
        $dir = dirname($imageUrl);
        $baseName = pathinfo($imageUrl, PATHINFO_FILENAME);
        return self::normalizePath($dir . '/' . self::RESPONSIVE_DIR . '/' . $baseName . '.webp');
    }

    /**
     * Build <img> tag with responsive WebP srcset
     *
     * Falls back to simple <img src="original"> if no variants exist.
     *
     * @param string $imageUrl URL path to original image (e.g., /images/photo.jpg)
     * @param string $alt Alt text
     * @param string $sizes Sizes attribute (default: 100vw)
     * @param string $class CSS class(es)
     * @param array $attrs Extra HTML attributes (e.g., ['width' => 1200, 'fetchpriority' => 'high'])
     * @return string HTML <img> tag
     */
    public static function imgTag(string $imageUrl, string $alt = '', string $sizes = '100vw', string $class = '', array $attrs = []): string
    {
        $sourcePath = Server::mapPath($imageUrl);
        $responsiveDir = self::getResponsiveDir($sourcePath);
        $baseName = pathinfo($imageUrl, PATHINFO_FILENAME);
        $urlDir = dirname($imageUrl);

        $srcsetParts = [];
        $src = $imageUrl; // fallback to original
        $originalSize = file_exists($sourcePath) ? filesize($sourcePath) : 0;

        // Check for width variants
        if (is_dir($responsiveDir)) {
            foreach (self::SIZES as $width) {
                $variantFile = $responsiveDir . DIRECTORY_SEPARATOR . $baseName . '-' . $width . 'w.webp';
                if (file_exists($variantFile)) {
                    // Skip variant if larger than original
                    if ($originalSize > 0 && filesize($variantFile) > $originalSize) {
                        continue;
                    }
                    $srcsetParts[] = self::normalizePath($urlDir . '/' . self::RESPONSIVE_DIR . '/' . $baseName . '-' . $width . 'w.webp') . ' ' . $width . 'w';
                }
            }

            // Full-size WebP as primary src (only if smaller than original)
            $fullWebP = $responsiveDir . DIRECTORY_SEPARATOR . $baseName . '.webp';
            if (file_exists($fullWebP)) {
                $webpSize = filesize($fullWebP);
                if ($originalSize <= 0 || $webpSize <= $originalSize) {
                    $src = self::normalizePath($urlDir . '/' . self::RESPONSIVE_DIR . '/' . $baseName . '.webp');
                    // Add full-size to srcset with its actual width
                    $fullInfo = @getimagesize($fullWebP);
                    if ($fullInfo) {
                        $srcsetParts[] = $src . ' ' . $fullInfo[0] . 'w';
                    }
                }
            }
        }

        // Build attributes
        $htmlAttrs = 'src="' . htmlspecialchars($src) . '"';

        if (!empty($srcsetParts)) {
            $htmlAttrs .= ' srcset="' . htmlspecialchars(implode(', ', $srcsetParts)) . '"';
            $htmlAttrs .= ' sizes="' . htmlspecialchars($sizes) . '"';
        }

        $htmlAttrs .= ' alt="' . htmlspecialchars($alt) . '"';

        if ($class !== '') {
            $htmlAttrs .= ' class="' . htmlspecialchars($class) . '"';
        }

        // Default to lazy loading unless overridden
        if (!isset($attrs['loading'])) {
            $attrs['loading'] = 'lazy';
        }

        foreach ($attrs as $key => $value) {
            $htmlAttrs .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars((string)$value) . '"';
        }

        return '<img ' . $htmlAttrs . '>';
    }

    /**
     * Check if responsive variants exist for an image
     *
     * @param string $sourcePath Full filesystem path
     * @return bool
     */
    public static function hasVariants(string $sourcePath): bool
    {
        $responsiveDir = self::getResponsiveDir($sourcePath);
        $baseName = pathinfo($sourcePath, PATHINFO_FILENAME);

        if (!is_dir($responsiveDir)) {
            return false;
        }

        // Check for at least the full-size WebP
        return file_exists($responsiveDir . DIRECTORY_SEPARATOR . $baseName . '.webp');
    }

    /**
     * Delete all responsive variants for an image
     *
     * @param string $sourcePath Full filesystem path to original image
     * @return bool True if all variants deleted (or none existed)
     */
    public static function deleteVariants(string $sourcePath): bool
    {
        $responsiveDir = self::getResponsiveDir($sourcePath);
        $baseName = pathinfo($sourcePath, PATHINFO_FILENAME);

        if (!is_dir($responsiveDir)) {
            return true;
        }

        $deleted = 0;
        $pattern = $responsiveDir . DIRECTORY_SEPARATOR . $baseName . '*.webp';
        $files = glob($pattern);

        if ($files === false) {
            return true;
        }

        foreach ($files as $file) {
            if (@unlink($file)) {
                $deleted++;
            }
        }

        // Remove .responsive dir if now empty
        $remaining = @scandir($responsiveDir);
        if ($remaining !== false && count($remaining) <= 2) {
            @rmdir($responsiveDir);
        }

        return true;
    }

    /**
     * Batch generate responsive variants for all images in a directory
     *
     * @param string $directory Full filesystem path to scan
     * @param bool $recursive Scan subdirectories
     * @param int $quality WebP quality
     * @return array ['total' => int, 'generated' => int, 'skipped' => int, 'errors' => int, 'files' => [...]]
     */
    public static function batchGenerate(string $directory, bool $recursive = true, int $quality = self::DEFAULT_QUALITY): array
    {
        $result = ['total' => 0, 'generated' => 0, 'skipped' => 0, 'errors' => 0, 'files' => []];

        if (!is_dir($directory)) {
            return $result;
        }

        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        $iterator = $recursive
            ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS))
            : new \DirectoryIterator($directory);

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            // Skip files inside .responsive directories
            if (strpos($file->getPathname(), DIRECTORY_SEPARATOR . self::RESPONSIVE_DIR . DIRECTORY_SEPARATOR) !== false) {
                continue;
            }

            $ext = strtolower($file->getExtension());
            if (!in_array($ext, $extensions)) {
                continue;
            }

            $result['total']++;
            $filePath = $file->getPathname();

            // Skip if already has variants
            if (self::hasVariants($filePath)) {
                $result['skipped']++;
                $result['files'][] = ['path' => $filePath, 'status' => 'skipped'];
                continue;
            }

            $genResult = self::generate($filePath, $quality);
            if ($genResult['success']) {
                $result['generated']++;
                $result['files'][] = ['path' => $filePath, 'status' => 'generated', 'variants' => count($genResult['variants'])];
            } else {
                $result['errors']++;
                $result['files'][] = ['path' => $filePath, 'status' => 'error', 'error' => $genResult['error'] ?? 'Onbekende fout'];
            }
        }

        return $result;
    }

    /**
     * Get scan results for a directory (without generating)
     *
     * @param string $directory Full filesystem path
     * @param bool $recursive Scan subdirectories
     * @return array List of image files with their variant status
     */
    public static function scan(string $directory, bool $recursive = true): array
    {
        $files = [];

        if (!is_dir($directory)) {
            return $files;
        }

        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        $iterator = $recursive
            ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS))
            : new \DirectoryIterator($directory);

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            // Skip files inside .responsive directories
            if (strpos($file->getPathname(), DIRECTORY_SEPARATOR . self::RESPONSIVE_DIR . DIRECTORY_SEPARATOR) !== false) {
                continue;
            }

            $ext = strtolower($file->getExtension());
            if (!in_array($ext, $extensions)) {
                continue;
            }

            $filePath = $file->getPathname();
            $info = Image::getInfo($filePath);
            $hasVariants = self::hasVariants($filePath);

            $entry = [
                'path' => $filePath,
                'name' => $file->getFilename(),
                'ext' => $ext,
                'size' => $file->getSize(),
                'hasVariants' => $hasVariants,
            ];

            if ($info !== false) {
                $entry['width'] = $info['width'];
                $entry['height'] = $info['height'];
            }

            // Collect variant details if they exist
            if ($hasVariants) {
                $responsiveDir = self::getResponsiveDir($filePath);
                $baseName = pathinfo($filePath, PATHINFO_FILENAME);
                $variants = [];

                // Width variants
                foreach (self::SIZES as $width) {
                    $variantPath = $responsiveDir . DIRECTORY_SEPARATOR . $baseName . '-' . $width . 'w.webp';
                    if (file_exists($variantPath)) {
                        $variants[] = [
                            'width' => $width,
                            'file' => $baseName . '-' . $width . 'w.webp',
                            'size' => filesize($variantPath),
                        ];
                    }
                }

                // Full-size WebP
                $webpPath = $responsiveDir . DIRECTORY_SEPARATOR . $baseName . '.webp';
                if (file_exists($webpPath)) {
                    $entry['webpSize'] = filesize($webpPath);
                    $variants[] = [
                        'width' => $info !== false ? $info['width'] : 0,
                        'file' => $baseName . '.webp',
                        'size' => filesize($webpPath),
                        'full' => true,
                    ];
                }

                $entry['variants'] = $variants;
            }

            $files[] = $entry;
        }

        return $files;
    }

    /**
     * Delete all .responsive directories under a path
     *
     * @param string $directory Full filesystem path
     * @param bool $recursive Scan subdirectories
     * @return array ['deleted' => int, 'errors' => int]
     */
    public static function cleanup(string $directory, bool $recursive = true): array
    {
        $result = ['deleted' => 0, 'errors' => 0];

        if (!is_dir($directory)) {
            return $result;
        }

        $iterator = $recursive
            ? new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            )
            : new \DirectoryIterator($directory);

        // Collect .responsive directories
        $responsiveDirs = [];
        foreach ($iterator as $file) {
            if ($file->isDir() && $file->getFilename() === self::RESPONSIVE_DIR) {
                $responsiveDirs[] = $file->getPathname();
            }
        }

        // Delete contents then directories
        foreach ($responsiveDirs as $dir) {
            $files = glob($dir . DIRECTORY_SEPARATOR . '*');
            if ($files !== false) {
                foreach ($files as $f) {
                    if (is_file($f)) {
                        @unlink($f);
                    }
                }
            }
            if (@rmdir($dir)) {
                $result['deleted']++;
            } else {
                $result['errors']++;
            }
        }

        return $result;
    }
}
