<?php

namespace App\Library;

/**
 * Image Helper Class
 *
 * Provides image processing utilities using PHP's native GD library.
 * Replaces legacy FileSystemObject and ASPX-based image operations.
 */
class Image
{
    /** @var string|null|false Cached cwebp binary path (null=not checked, false=not found) */
    private static $cwebpPath = null;

    /**
     * Find the cwebp binary on the system.
     * Checks known locations and PATH.
     *
     * @return string|false Path to cwebp binary, or false if not found
     */
    public static function findCwebp(): string|false
    {
        if (self::$cwebpPath !== null) {
            return self::$cwebpPath;
        }

        // Known locations to check
        $candidates = [];

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates = [
                'C:\\repos\\img_optimize\\node_modules\\cwebp-bin\\vendor\\cwebp.exe',
                dirname(PHP_BINARY) . '\\cwebp.exe',
                'C:\\Program Files\\WebP\\bin\\cwebp.exe',
                'C:\\Program Files (x86)\\WebP\\bin\\cwebp.exe',
            ];
            // Also check PATH
            $where = @shell_exec('where cwebp 2>nul');
            if ($where) {
                $candidates[] = trim(explode("\n", $where)[0]);
            }
        } else {
            $candidates = [
                '/usr/bin/cwebp',
                '/usr/local/bin/cwebp',
            ];
            $which = @shell_exec('which cwebp 2>/dev/null');
            if ($which) {
                $candidates[] = trim($which);
            }
        }

        foreach ($candidates as $path) {
            if ($path && file_exists($path) && is_executable($path)) {
                self::$cwebpPath = $path;
                return self::$cwebpPath;
            }
        }

        self::$cwebpPath = false;
        return false;
    }

    /**
     * Convert an image to WebP using the cwebp command-line tool.
     * Preserves ICC color profiles for accurate color reproduction.
     *
     * @param string $sourcePath Source image path
     * @param string $destPath Destination WebP path
     * @param int $quality WebP quality (1-100)
     * @param int|null $resizeWidth Optional: resize to this width (0 or null = no resize)
     * @return bool True if successful
     */
    private static function convertWithCwebp(string $sourcePath, string $destPath, int $quality = 85, ?int $resizeWidth = null): bool
    {
        $cwebp = self::findCwebp();
        if ($cwebp === false) {
            return false;
        }

        // Build command: preserve ICC profile for color accuracy
        $cmd = escapeshellarg($cwebp)
            . ' -q ' . (int)$quality
            . ' -metadata icc';

        // Optional resize (width, height 0 = proportional)
        if ($resizeWidth && $resizeWidth > 0) {
            $cmd .= ' -resize ' . (int)$resizeWidth . ' 0';
        }

        $cmd .= ' ' . escapeshellarg($sourcePath)
            . ' -o ' . escapeshellarg($destPath);

        if (PHP_OS_FAMILY === 'Windows') {
            $cmd .= ' 2>nul';
        } else {
            $cmd .= ' 2>/dev/null';
        }

        @exec($cmd, $output, $returnCode);

        return $returnCode === 0 && file_exists($destPath) && filesize($destPath) > 0;
    }

    /**
     * Check if cwebp command-line tool is available
     *
     * @return bool
     */
    public static function isCwebpAvailable(): bool
    {
        return self::findCwebp() !== false;
    }

    /**
     * Create a GD image resource from any supported format
     *
     * @param string $path Path to image file
     * @param int $type IMAGETYPE_* constant
     * @return \GdImage|false
     */
    private static function createFromAny(string $path, int $type): \GdImage|false
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_GIF  => @imagecreatefromgif($path),
            IMAGETYPE_BMP  => @imagecreatefrombmp($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default        => false,
        };
    }

    /**
     * Save a GD image to a file, format determined by extension
     *
     * @param \GdImage $image GD image resource
     * @param string $destPath Destination file path
     * @param int $quality Quality (1-100, default 85)
     * @return bool
     */
    private static function saveAs(\GdImage $image, string $destPath, int $quality = 85): bool
    {
        $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => imagejpeg($image, $destPath, $quality),
            'png'         => imagepng($image, $destPath, (int)(9 - ($quality / 100 * 9))),
            'gif'         => imagegif($image, $destPath),
            'bmp'         => imagebmp($image, $destPath),
            'webp'        => imagewebp($image, $destPath, $quality),
            default       => false,
        };
    }

    /**
     * Apply EXIF orientation to a GD image (JPEG only)
     * Corrects rotation/flip based on EXIF Orientation tag.
     *
     * @param \GdImage $image GD image resource
     * @param string $sourcePath Path to source file (for reading EXIF)
     * @param int $type IMAGETYPE_* constant
     * @return \GdImage Corrected image (may be a new resource)
     */
    private static function applyExifOrientation(\GdImage $image, string $sourcePath, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($sourcePath);
        if ($exif === false || empty($exif['Orientation'])) {
            return $image;
        }

        $orientation = (int)$exif['Orientation'];

        // imagerotate() rotates counter-clockwise
        // EXIF orientations:
        // 1=normal, 2=flip-H, 3=180°, 4=flip-V,
        // 5=rotate 90°CW+flip-H, 6=rotate 90°CW, 7=rotate 90°CCW+flip-H, 8=rotate 90°CCW
        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $rotated = imagerotate($image, 180, 0);
                if ($rotated) {$image = $rotated; }
                break;
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);
                break;
            case 5:
                $rotated = imagerotate($image, -90, 0);
                if ($rotated) {imageflip($rotated, IMG_FLIP_HORIZONTAL); $image = $rotated; }
                break;
            case 6:
                $rotated = imagerotate($image, -90, 0);
                if ($rotated) {$image = $rotated; }
                break;
            case 7:
                $rotated = imagerotate($image, 90, 0);
                if ($rotated) {imageflip($rotated, IMG_FLIP_HORIZONTAL); $image = $rotated; }
                break;
            case 8:
                $rotated = imagerotate($image, 90, 0);
                if ($rotated) {$image = $rotated; }
                break;
        }

        return $image;
    }

    /**
     * Preserve transparency on a destination image for formats that support it
     *
     * @param \GdImage $destImage Destination GD image
     * @param int $sourceType Source IMAGETYPE_* constant
     * @param int $width Image width
     * @param int $height Image height
     */
    private static function preserveTransparency(\GdImage $destImage, int $sourceType, int $width, int $height): void
    {
        if ($sourceType === IMAGETYPE_PNG || $sourceType === IMAGETYPE_GIF || $sourceType === IMAGETYPE_WEBP) {
            imagealphablending($destImage, false);
            imagesavealpha($destImage, true);
            $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
            imagefilledrectangle($destImage, 0, 0, $width, $height, $transparent);
        }
    }

    /**
     * Check if WebP is supported by the current GD installation
     *
     * @return bool
     */
    public static function isWebPSupported(): bool
    {
        if (!function_exists('imagecreatefromwebp')) {
            return false;
        }
        $info = gd_info();
        return !empty($info['WebP Support']);
    }

    /**
     * Get image dimensions
     *
     * @param string $filepath Absolute or relative path to image file
     * @param int &$width Output parameter for image width
     * @param int &$height Output parameter for image height
     * @return bool True if successful, false on error
     */
    public static function getSize(string $filepath, &$width, &$height): bool
    {
        if (!file_exists($filepath)) {
            $width = 0;
            $height = 0;
            return false;
        }

        $imageInfo = @getimagesize($filepath);

        if ($imageInfo === false) {
            $width = 0;
            $height = 0;
            return false;
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        return true;
    }

    /**
     * Get detailed image information
     *
     * @param string $filepath Path to image file
     * @return array|false Array with keys: width, height, type, mime, or false on error
     */
    public static function getInfo(string $filepath)
    {
        if (!file_exists($filepath)) {
            return false;
        }

        $imageInfo = @getimagesize($filepath);

        if ($imageInfo === false) {
            return false;
        }

        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'type' => $imageInfo[2],
            'mime' => $imageInfo['mime'],
            'channels' => $imageInfo['channels'] ?? null,
            'bits' => $imageInfo['bits'] ?? null
        ];
    }

    /**
     * Get image type as string
     *
     * @param string $filepath Path to image file
     * @return string Image type (JPG, PNG, GIF, BMP, WEBP) or '(unknown)'
     */
    public static function getType(string $filepath): string
    {
        $info = self::getInfo($filepath);

        if ($info === false) {
            return '(unknown)';
        }

        return match ($info['type']) {
            IMAGETYPE_JPEG => 'JPG',
            IMAGETYPE_PNG  => 'PNG',
            IMAGETYPE_GIF  => 'GIF',
            IMAGETYPE_BMP  => 'BMP',
            IMAGETYPE_WEBP => 'WEBP',
            default        => '(unknown)',
        };
    }

    /**
     * Convert an image to WebP format.
     * Prefers cwebp command-line tool (preserves ICC color profiles) over GD.
     *
     * @param string $sourcePath Source image path
     * @param string $destPath Destination WebP path
     * @param int $quality WebP quality (1-100, default 85)
     * @return bool True if successful
     */
    public static function convertToWebP(string $sourcePath, string $destPath, int $quality = 85): bool
    {
        if (!file_exists($sourcePath)) {
            return false;
        }

        // Prefer cwebp: preserves ICC color profiles for accurate colors
        if (self::convertWithCwebp($sourcePath, $destPath, $quality)) {
            return true;
        }

        // Fallback to GD (strips ICC profiles — may cause color shift)
        if (!self::isWebPSupported()) {
            return false;
        }

        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }

        $sourceImage = self::createFromAny($sourcePath, $imageInfo[2]);
        if ($sourceImage === false) {
            return false;
        }

        // Apply EXIF orientation (JPEG rotation correction)
        $sourceImage = self::applyExifOrientation($sourceImage, $sourcePath, $imageInfo[2]);

        // Preserve transparency
        if ($imageInfo[2] === IMAGETYPE_PNG || $imageInfo[2] === IMAGETYPE_GIF || $imageInfo[2] === IMAGETYPE_WEBP) {
            imagealphablending($sourceImage, false);
            imagesavealpha($sourceImage, true);
        }

        $success = imagewebp($sourceImage, $destPath, $quality);

        return $success;
    }

    /**
     * Create thumbnail/resized image
     *
     * @param string $sourcePath Source image path
     * @param string $destPath Destination path for resized image
     * @param int $maxWidth Maximum width (0 = auto-calculate from height)
     * @param int $maxHeight Maximum height (0 = auto-calculate from width)
     * @param int $quality Quality (1-100, default 85)
     * @return bool True if successful, false on error
     */
    public static function resize(string $sourcePath, string $destPath, int $maxWidth, int $maxHeight, int $quality = 85): bool
    {
        if (!file_exists($sourcePath)) {
            return false;
        }

        // For WebP output with width-only resize, prefer cwebp (preserves ICC colors)
        $destExt = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
        if ($destExt === 'webp' && $maxWidth > 0 && $maxHeight === 0) {
            if (self::convertWithCwebp($sourcePath, $destPath, $quality, $maxWidth)) {
                return true;
            }
        }

        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }

        list($origWidth, $origHeight, $type) = $imageInfo;

        // Calculate scaling
        if ($maxWidth > 0 && $maxHeight > 0) {
            $scaleX = $maxWidth / $origWidth;
            $scaleY = $maxHeight / $origHeight;
            $scale = min($scaleX, $scaleY);
        } elseif ($maxWidth > 0) {
            $scale = $maxWidth / $origWidth;
        } elseif ($maxHeight > 0) {
            $scale = $maxHeight / $origHeight;
        } else {
            return false;
        }

        $newWidth = (int)($origWidth * $scale);
        $newHeight = (int)($origHeight * $scale);

        $sourceImage = self::createFromAny($sourcePath, $type);
        if ($sourceImage === false) {
            return false;
        }

        // Apply EXIF orientation (JPEG rotation correction)
        $sourceImage = self::applyExifOrientation($sourceImage, $sourcePath, $type);
        // After rotation, actual dimensions may have changed
        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);

        // Recalculate scaling with corrected dimensions
        if ($maxWidth > 0 && $maxHeight > 0) {
            $scaleX = $maxWidth / $origWidth;
            $scaleY = $maxHeight / $origHeight;
            $scale = min($scaleX, $scaleY);
        } elseif ($maxWidth > 0) {
            $scale = $maxWidth / $origWidth;
        } elseif ($maxHeight > 0) {
            $scale = $maxHeight / $origHeight;
        }
        $newWidth = (int)($origWidth * $scale);
        $newHeight = (int)($origHeight * $scale);

        $destImage = imagecreatetruecolor($newWidth, $newHeight);
        self::preserveTransparency($destImage, $type, $newWidth, $newHeight);

        imagecopyresampled($destImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        $success = self::saveAs($destImage, $destPath, $quality);


        return $success;
    }

    /**
     * Crop an image to specified region
     *
     * @param string $sourcePath Source image path
     * @param string $destPath Destination path
     * @param int $x Left offset
     * @param int $y Top offset
     * @param int $width Crop width
     * @param int $height Crop height
     * @param int $quality Quality (1-100, default 85)
     * @return bool True if successful
     */
    public static function crop(string $sourcePath, string $destPath, int $x, int $y, int $width, int $height, int $quality = 85): bool
    {
        if (!file_exists($sourcePath)) {
            return false;
        }

        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }

        list($origWidth, $origHeight, $type) = $imageInfo;

        $sourceImage = self::createFromAny($sourcePath, $type);
        if ($sourceImage === false) {
            return false;
        }

        $sourceImage = self::applyExifOrientation($sourceImage, $sourcePath, $type);

        $destImage = imagecreatetruecolor($width, $height);
        self::preserveTransparency($destImage, $type, $width, $height);

        imagecopyresampled($destImage, $sourceImage, 0, 0, $x, $y, $width, $height, $width, $height);

        $success = self::saveAs($destImage, $destPath, $quality);


        return $success;
    }

    /**
     * Crop an image and resize the cropped region to destination dimensions
     *
     * @param string $sourcePath Source image path
     * @param string $destPath Destination path
     * @param int $x Left offset
     * @param int $y Top offset
     * @param int $width Crop width
     * @param int $height Crop height
     * @param int $destWidth Target width
     * @param int $destHeight Target height
     * @param int $quality Quality (1-100, default 85)
     * @return bool True if successful
     */
    public static function cropAndResize(string $sourcePath, string $destPath, int $x, int $y, int $width, int $height, int $destWidth, int $destHeight, int $quality = 85): bool
    {
        if (!file_exists($sourcePath)) {
            return false;
        }

        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }

        list($origWidth, $origHeight, $type) = $imageInfo;

        $sourceImage = self::createFromAny($sourcePath, $type);
        if ($sourceImage === false) {
            return false;
        }

        $sourceImage = self::applyExifOrientation($sourceImage, $sourcePath, $type);

        $destImage = imagecreatetruecolor($destWidth, $destHeight);
        self::preserveTransparency($destImage, $type, $destWidth, $destHeight);

        imagecopyresampled($destImage, $sourceImage, 0, 0, $x, $y, $destWidth, $destHeight, $width, $height);

        $success = self::saveAs($destImage, $destPath, $quality);


        return $success;
    }

    /**
     * Rotate an image by the given number of degrees (clockwise)
     *
     * @param string $sourcePath Source image path
     * @param string $destPath Destination path (may be the same as source)
     * @param int $degrees Clockwise rotation in degrees (typically 90, 180, 270)
     * @param int $quality Quality (1-100, default 85)
     * @return bool True if successful
     */
    public static function rotate(string $sourcePath, string $destPath, int $degrees, int $quality = 85): bool
    {
        if (!file_exists($sourcePath)) {
            return false;
        }

        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }

        list($origWidth, $origHeight, $type) = $imageInfo;

        $sourceImage = self::createFromAny($sourcePath, $type);
        if ($sourceImage === false) {
            return false;
        }

        $sourceImage = self::applyExifOrientation($sourceImage, $sourcePath, $type);

        // Determine background color for rotation
        // For formats with transparency, use a transparent background
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF || $type === IMAGETYPE_WEBP) {
            imagealphablending($sourceImage, false);
            imagesavealpha($sourceImage, true);
            $bgColor = imagecolorallocatealpha($sourceImage, 255, 255, 255, 127);
        } else {
            $bgColor = 0;
        }

        // GD's imagerotate() rotates counter-clockwise, negate for clockwise
        $rotated = imagerotate($sourceImage, -$degrees, $bgColor);
        if ($rotated === false) {
            imagedestroy($sourceImage);
            return false;
        }

        // Preserve alpha channel on the rotated image for transparency-capable formats
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF || $type === IMAGETYPE_WEBP) {
            imagealphablending($rotated, false);
            imagesavealpha($rotated, true);
        }

        $success = self::saveAs($rotated, $destPath, $quality);

        imagedestroy($sourceImage);
        imagedestroy($rotated);

        return $success;
    }

    /**
     * Calculate thumbnail dimensions while preserving aspect ratio
     *
     * @param int $origWidth Original width
     * @param int $origHeight Original height
     * @param int $maxDimension Maximum dimension (square)
     * @return array ['width' => int, 'height' => int]
     */
    public static function calculateThumbnailSize(int $origWidth, int $origHeight, int $maxDimension): array
    {
        if ($origWidth > $origHeight) {
            $newWidth = $maxDimension;
            $newHeight = (int)(($origHeight * $maxDimension) / $origWidth);
        } else {
            $newHeight = $maxDimension;
            $newWidth = (int)(($origWidth * $maxDimension) / $origHeight);
        }

        return ['width' => $newWidth, 'height' => $newHeight];
    }

    /**
     * Optimize image quality/file size
     *
     * @param string $filename Path to image file to optimize
     * @param int $quality Quality/compression level (1-100, default 90)
     * @return bool True if successful, false on error
     */
    public static function optimize(string $filename, int $quality = 90): bool
    {
        if (!file_exists($filename)) {
            return false;
        }

        $imageInfo = @getimagesize($filename);
        if ($imageInfo === false) {
            return false;
        }

        list($width, $height, $type) = $imageInfo;

        $sourceImage = self::createFromAny($filename, $type);
        if ($sourceImage === false) {
            return false;
        }

        // Preserve transparency for PNG/GIF/WebP
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF || $type === IMAGETYPE_WEBP) {
            imagealphablending($sourceImage, false);
            imagesavealpha($sourceImage, true);
        }

        $tempFilename = $filename . '.tmp';
        $success = self::saveAs($sourceImage, $tempFilename, $quality);


        if ($success) {
            unlink($filename);
            rename($tempFilename, $filename);
            return true;
        }

        if (file_exists($tempFilename)) {
            unlink($tempFilename);
        }

        return false;
    }

    /**
     * Create thumbnail from image
     *
     * @param string $sourceFile Source image path
     * @param string $destFile Destination path (if empty, prepends "tn_" to source)
     * @param int $maxHeight Maximum height (0 = proportional to width)
     * @param int $maxWidth Maximum width (0 = proportional to height)
     * @param int $quality Quality (1-100, default 95)
     * @return bool True if successful, false on error
     */
    public static function thumbnail(string $sourceFile, string $destFile = '', int $maxHeight = 0, int $maxWidth = 0, int $quality = 95): bool
    {
        if (empty($destFile)) {
            $pathInfo = pathinfo($sourceFile);
            $destFile = $pathInfo['dirname'] . '/tn_' . $pathInfo['basename'];
        }

        if (!file_exists($sourceFile)) {
            return false;
        }

        $imageInfo = @getimagesize($sourceFile);
        if ($imageInfo === false) {
            return false;
        }

        list($origWidth, $origHeight, $type) = $imageInfo;

        if ($maxWidth == 0 && $maxHeight == 0) {
            return copy($sourceFile, $destFile);
        }

        if ($maxWidth == 0) {
            $scale = $maxHeight / $origHeight;
            $newWidth = (int)($origWidth * $scale);
            $newHeight = $maxHeight;
        } elseif ($maxHeight == 0) {
            $scale = $maxWidth / $origWidth;
            $newWidth = $maxWidth;
            $newHeight = (int)($origHeight * $scale);
        } else {
            $scaleX = $maxWidth / $origWidth;
            $scaleY = $maxHeight / $origHeight;
            $scale = min($scaleX, $scaleY);
            $newWidth = (int)($origWidth * $scale);
            $newHeight = (int)($origHeight * $scale);
        }

        $sourceImage = self::createFromAny($sourceFile, $type);
        if ($sourceImage === false) {
            return false;
        }

        $destImage = imagecreatetruecolor($newWidth, $newHeight);
        self::preserveTransparency($destImage, $type, $newWidth, $newHeight);

        imagecopyresampled($destImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        $success = self::saveAs($destImage, $destFile, $quality);


        return $success;
    }
}
