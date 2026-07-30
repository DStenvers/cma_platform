<?php
/**
 * Image Format Functions - Backward Compatibility Layer
 *
 * This file provides backward compatibility for legacy code using gfxSpex().
 * New code should use App\Library\Image class directly.
 */

// Include-guard: dit bestand weigert zichzelf een tweede keer uit te voeren.
// require_once dekt het gewone geval al — die lost het pad eerst op — maar niet een
// kale require/include, en niet een pad dat via een junction anders uitkomt. De naam
// is uniek per bestand, zodat de guard niet van de aanroeper afhangt.
if (defined('INC_LIB_IMGFORMAT')) { return; }
define('INC_LIB_IMGFORMAT', 1);

use App\Library\Image;

/**
 * Get image specifications (width, height, depth, type)
 *
 * Legacy wrapper around Image::getInfo() for backward compatibility.
 *
 * @param string $filepath Path to image file
 * @param int &$width Output: image width in pixels
 * @param int &$height Output: image height in pixels
 * @param int &$depth Output: bits per pixel (color depth)
 * @param string &$imageType Output: image type (JPG, PNG, GIF, BMP)
 * @return bool True if successful, false on error
 */
function gfxSpex(string $filepath, &$width, &$height, &$depth, &$imageType): bool
{
    $info = Image::getInfo($filepath);

    if ($info === false) {
        $width = 0;
        $height = 0;
        $depth = 0;
        $imageType = '(unknown)';
        return false;
    }

    $width = $info['width'];
    $height = $info['height'];
    $depth = $info['bits'] ?? 24; // Default to 24-bit if not available
    $imageType = Image::getType($filepath);

    return true;
}
