<?php
/**
 * Editable page templates: a site page marks regions an editor may change,
 *
 *   <!-- #beginedit type=title,name=titel,pre=<title>,post=</title> -->
 *   <title>Welkom</title>
 *   <!-- #endedit -->
 *
 * and the CMA shows one control per region (template_edit.php) and writes the
 * values back into the file, leaving everything outside the regions untouched
 * (template_post.php). tblSiteFiles is the index of files that carry such
 * regions (template_fillrep.php rebuilds it).
 *
 * Tag elements, comma separated, case-insensitive:
 *   type      text | title | textarea | keyword | description | datestamp (default text)
 *   name      label and form field name (default "field"); spaces become _
 *   size      maxlength of a text input (default 80; the visible size is capped at 70)
 *   height    rows of a textarea (default 6); a CKEditor gets height*40 px
 *   html      yes → the region holds HTML (CKEditor); saved as-is
 *   required  yes → the field name gets the required- prefix the form validator reads
 *   pre/post  literal text inside the region around the value (e.g. <title> and </title>)
 *   format    datestamp pattern, dd/mm/yyyy by default (a Dutch j/J for the year reads as y)
 *
 * keyword and description regions hold a <meta … content="…"> tag: the value
 * is the content attribute, and the tag is written back around it. A datestamp
 * region is never shown; it is rewritten with the current date on every save.
 * A value that is not HTML is stored HTML-encoded (text, textarea); keyword and
 * description values are stored as-is, since they live inside an attribute.
 *
 * Everything here is pure: content in, content out. The pages do the I/O.
 */

namespace Cma\Services;

class TemplateService
{
    public const START = '<!-- #beginedit ';
    public const END = '<!-- #endedit -->';

    private const TYPES = ['text', 'title', 'textarea', 'keyword', 'description', 'datestamp'];

    /**
     * The editable regions of a template, in document order.
     *
     * @return array<int, array{type:string, name:string, field:string, size:int, height:int, html:bool, required:bool, pre:string, post:string, format:string, tag:string, value:string, start:int, end:int}>
     *   start/end are the byte offsets of the whole region (start tag through end tag).
     */
    public static function parse(string $content): array
    {
        $regions = [];
        $pos = 0;
        while (($startAt = stripos($content, self::START, $pos)) !== false) {
            $tagClose = strpos($content, '-->', $startAt);
            if ($tagClose === false) {
                break;
            }
            $endAt = stripos($content, self::END, $tagClose);
            if ($endAt === false) {
                break;
            }
            $tag = trim(substr($content, $startAt + strlen(self::START), $tagClose - $startAt - strlen(self::START)));
            $area = substr($content, $tagClose + 3, $endAt - $tagClose - 3);
            $region = self::tagToRegion($tag);
            $region['tag'] = $tag;
            $region['value'] = self::extractValue($area, $region);
            $region['start'] = $startAt;
            $region['end'] = $endAt + strlen(self::END);
            $regions[] = $region;
            $pos = $region['end'];
        }
        return $regions;
    }

    /** Does this file carry editable regions at all? */
    public static function isTemplate(string $content): bool
    {
        return stripos($content, self::START) !== false;
    }

    /**
     * The template with the submitted values written into its regions.
     *
     * @param array<string,string> $values keyed by field name (see parse()['field'])
     * @param int|null $now unix time for datestamp regions (null = time())
     */
    public static function render(string $content, array $values, ?int $now = null): string
    {
        $out = '';
        $cursor = 0;
        foreach (self::parse($content) as $region) {
            $out .= substr($content, $cursor, $region['start'] - $cursor);
            $out .= self::START . $region['tag'] . ' -->' . $region['pre'] . self::storedValue($region, $values, $now ?? time()) . $region['post'] . self::END;
            $cursor = $region['end'];
        }
        return $out . substr($content, $cursor);
    }

    /** The <title> of a page, for the index; the file name when there is none. */
    public static function title(string $content, string $fallback): string
    {
        // Not the <title> inside a region's own tag (pre=<title>): drop the tags first.
        $withoutTags = preg_replace('/<!-- #beginedit .*?-->/is', '', $content) ?? $content;
        if (preg_match('/<title>(.*?)(<\/title>|$)/is', $withoutTags, $m)) {
            $title = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($title !== '') {
                return $title;
            }
        }
        return $fallback;
    }

    private static function tagToRegion(string $tag): array
    {
        $r = ['type' => 'text', 'name' => 'field', 'size' => 80, 'height' => 6, 'html' => false, 'required' => false, 'pre' => '', 'post' => '', 'format' => 'dd/mm/yyyy'];
        foreach (explode(',', $tag) as $element) {
            if (strpos($element, '=') === false) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $element, 2));
            switch (strtolower($key)) {
                case 'type':
                    $type = strtolower($value);
                    $r['type'] = in_array($type, self::TYPES, true) ? $type : 'text';
                    break;
                case 'name':
                    $r['name'] = $value !== '' ? $value : 'field';
                    break;
                case 'size':
                    $r['size'] = max(1, (int) $value);
                    break;
                case 'height':
                    $r['height'] = max(1, (int) $value);
                    break;
                case 'html':
                    $r['html'] = strtolower($value) === 'yes';
                    break;
                case 'required':
                    $r['required'] = strtolower($value) === 'yes';
                    break;
                case 'pre':
                    $r['pre'] = $value;
                    break;
                case 'post':
                    $r['post'] = $value;
                    break;
                case 'format':
                    // The Dutch year letter, either case: the pattern is written by hand.
                    $r['format'] = str_ireplace('j', 'y', $value);
                    break;
            }
        }
        $r['field'] = ($r['required'] ? 'required-' : '') . str_replace(' ', '_', $r['name']);
        return $r;
    }

    private static function extractValue(string $area, array $region): string
    {
        $value = $area;
        if ($region['pre'] !== '') {
            $at = stripos($value, $region['pre']);
            if ($at !== false) {
                $value = substr($value, $at + strlen($region['pre']));
            }
        }
        if ($region['post'] !== '') {
            $at = stripos($value, $region['post']);
            if ($at !== false) {
                $value = substr($value, 0, $at);
            }
        }
        $value = trim($value);
        if ($region['type'] === 'keyword' || $region['type'] === 'description') {
            return self::metaContent($value);
        }
        if (!$region['html']) {
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return $value;
    }

    /** The content="…" of a meta tag; the whole text when there is no such attribute. */
    private static function metaContent(string $meta): string
    {
        if (preg_match('/content\s*=\s*"([^"]*)"/i', $meta, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return $meta;
    }

    private static function storedValue(array $region, array $values, int $now): string
    {
        if ($region['type'] === 'datestamp') {
            return self::datestamp($region['format'], $now);
        }
        $value = (string) ($values[$region['field']] ?? '');
        switch ($region['type']) {
            case 'keyword':
                return '<meta name="KEYWORD" content="' . htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">';
            case 'description':
                return '<meta name="DESCRIPTION" content="' . htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">';
            default:
                return $region['html'] ? $value : htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    /** dd/mm/yyyy style pattern: dd, mm, yyyy, yy are replaced, the rest is literal. */
    public static function datestamp(string $format, int $now): string
    {
        return str_replace(
            ['yyyy', 'dd', 'mm', 'yy'],
            [date('Y', $now), date('j', $now), date('n', $now), date('y', $now)],
            $format
        );
    }
}
