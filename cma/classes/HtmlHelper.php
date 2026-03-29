<?php
namespace Cma;

use App\Library\Cookie;

/**
 * HTML Helper - Shared HTML generation utilities
 */
class HtmlHelper
{
    /**
     * Generate HTML document start with dark mode support
     *
     * @param string $title Page title
     * @param string $lang Language code (default: 'nl')
     * @return string HTML doctype, html, head start with meta tags
     */
    public static function htmlStart(string $title, string $lang = 'nl'): string
    {
        // Get theme preference from cookie
        $currentTheme = Cookie::get('cma_theme', 'light');
        $themeClass = ($currentTheme === 'dark') ? ' class="dark-mode"' : '';
        $useSystemTheme = ($currentTheme === 'system');

        $html = '<!DOCTYPE html>' . PHP_EOL;
        $html .= '<html lang="' . htmlspecialchars($lang) . '"' . $themeClass . '>' . PHP_EOL;
        $html .= '<head>' . PHP_EOL;

        // Handle system theme or prevent flash of white in dark mode
        if ($useSystemTheme) {
            // System theme: apply based on OS preference
            $html .= '<script>(function(){' . PHP_EOL;
            $html .= '  if(window.matchMedia&&window.matchMedia("(prefers-color-scheme:dark)").matches){' . PHP_EOL;
            $html .= '    document.documentElement.classList.add("dark-mode");' . PHP_EOL;
            $html .= '    document.documentElement.style.backgroundColor="#1a1a1a";' . PHP_EOL;
            $html .= '  }' . PHP_EOL;
            $html .= '  window.matchMedia("(prefers-color-scheme:dark)").addEventListener("change",function(e){' . PHP_EOL;
            $html .= '    document.documentElement.classList.toggle("dark-mode",e.matches);' . PHP_EOL;
            $html .= '  });' . PHP_EOL;
            $html .= '})();</script>' . PHP_EOL;
        } elseif ($currentTheme === 'dark') {
            // Manual dark mode: set background immediately to prevent flash
            $html .= '<script>document.documentElement.style.backgroundColor="#1a1a1a";</script>' . PHP_EOL;
        }

        $html .= '<meta charset="UTF-8">' . PHP_EOL;
        $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . PHP_EOL;
        $html .= '<title>' . htmlspecialchars($title) . '</title>' . PHP_EOL;

        return $html;
    }
}
