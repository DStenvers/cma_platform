<?php
/**
 * Tests for App\Library\ImageProfiles — the declarative image-variant
 * pipeline's JSON loading / lookup / tag-rendering logic.
 *
 * Run with: php tests/TestRunner.php ImageProfilesTest
 *
 * ImageProfiles::load() resolves its config from <siteRoot>/data/image-profiles.json,
 * where siteRoot = Bootstrap::getRootDir() when set. We point the class at a
 * temp dir by setting Bootstrap's private static $rootDir via reflection in
 * setUp (and restoring it in tearDown), then writing our own JSON there.
 *
 * generate()/renderVariant()/cropCenter() are NOT covered here: they call the
 * GD-backed App\Library\Image class against real image files, which is out of
 * scope for a pure config/lookup unit test. The load/getProfile/isManaged/
 * imgTag surface (all the JSON-driven logic) is fully covered.
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\ImageProfiles;
use App\Library\Bootstrap;

class ImageProfilesTest extends TestCase
{
    private string $tmpRoot;
    private string $origRootDir;

    public function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/image-profiles-' . bin2hex(random_bytes(6));
        mkdir($this->tmpRoot . '/data', 0755, true);

        // Redirect ImageProfiles::resolveSiteRoot() at our temp dir.
        $prop = new \ReflectionProperty(Bootstrap::class, 'rootDir');
        $prop->setAccessible(true);
        $this->origRootDir = (string)$prop->getValue();
        $prop->setValue(null, $this->tmpRoot);

        ImageProfiles::clearCache();
    }

    public function tearDown(): void
    {
        $prop = new \ReflectionProperty(Bootstrap::class, 'rootDir');
        $prop->setAccessible(true);
        $prop->setValue(null, $this->origRootDir);

        ImageProfiles::clearCache();
        $this->rmrf($this->tmpRoot);
    }

    private function rmrf(string $path): void
    {
        if (!file_exists($path)) return;
        if (is_file($path) || is_link($path)) { @unlink($path); return; }
        foreach ((array)scandir($path) as $e) {
            if ($e === '.' || $e === '..') continue;
            $this->rmrf($path . '/' . $e);
        }
        @rmdir($path);
    }

    /** Write the profiles JSON (raw string) and reset the in-memory cache. */
    private function writeJson(string $raw): void
    {
        file_put_contents($this->tmpRoot . '/data/image-profiles.json', $raw);
        ImageProfiles::clearCache();
    }

    private function writeProfiles(array $config): void
    {
        $this->writeJson((string)json_encode($config));
    }

    private function sampleConfig(): array
    {
        return [
            'profiles' => [
                'recipe.hero' => [
                    'source'   => ['format' => 'jpg', 'quality' => 92],
                    'variants' => [
                        'display' => ['format' => 'webp', 'width' => 1600, 'fit' => 'contain', 'quality' => 85],
                        'thumb'   => ['format' => 'webp', 'width' => 320, 'height' => 320, 'fit' => 'cover-center'],
                    ],
                    'srcset' => [400, 800, 1200],
                ],
                'user.avatar' => [
                    'variants' => [
                        'display' => ['format' => 'png', 'width' => 200, 'height' => 200, 'fit' => 'cover-center'],
                    ],
                ],
            ],
            'managed_paths' => [
                '/assets/uploads/recipes/',
                '/assets/uploads/avatars/',
            ],
        ];
    }

    // ----- load() ----------------------------------------------------------

    public function testMissingFileYieldsEmptyConfig(): void
    {
        // No JSON written at all.
        $cfg = ImageProfiles::load();
        $this->assertIsArray($cfg);
        $this->assertArrayHasKey('profiles', $cfg);
        $this->assertArrayHasKey('managed_paths', $cfg);
        $this->assertEmpty($cfg['profiles']);
        $this->assertEmpty($cfg['managed_paths']);
    }

    public function testValidProfilesLoad(): void
    {
        $this->writeProfiles($this->sampleConfig());

        $cfg = ImageProfiles::load();
        $this->assertCount(2, $cfg['profiles']);
        $this->assertArrayHasKey('recipe.hero', $cfg['profiles']);
        $this->assertCount(2, $cfg['managed_paths']);
        $this->assertContains('/assets/uploads/recipes/', $cfg['managed_paths']);
    }

    public function testMalformedJsonDoesNotFatal(): void
    {
        // Truncated / invalid JSON — must degrade to an empty config, not throw.
        $this->writeJson('{ "profiles": { "x": { "variants": ');

        $cfg = ImageProfiles::load();
        $this->assertEmpty($cfg['profiles'], 'malformed JSON should give empty profiles');
        $this->assertEmpty($cfg['managed_paths']);
        // And downstream lookups stay safe.
        $this->assertNull(ImageProfiles::getProfile('x'));
    }

    public function testNonObjectSectionsCoerceToEmpty(): void
    {
        // profiles / managed_paths present but of the wrong type.
        $this->writeJson('{ "profiles": "nope", "managed_paths": 42 }');

        $cfg = ImageProfiles::load();
        $this->assertEmpty($cfg['profiles']);
        $this->assertEmpty($cfg['managed_paths']);
    }

    public function testCacheIsReusedUntilCleared(): void
    {
        $this->writeProfiles($this->sampleConfig());
        $this->assertNotNull(ImageProfiles::getProfile('recipe.hero'));

        // Overwrite the file WITHOUT clearing the cache — old value should stick.
        file_put_contents($this->tmpRoot . '/data/image-profiles.json', json_encode(['profiles' => []]));
        $this->assertNotNull(ImageProfiles::getProfile('recipe.hero'), 'cache should still serve the old config');

        // After clearCache the new (empty) config is read.
        ImageProfiles::clearCache();
        $this->assertNull(ImageProfiles::getProfile('recipe.hero'), 'cleared cache should re-read from disk');
    }

    // ----- getProfile() ----------------------------------------------------

    public function testGetProfileReturnsProfile(): void
    {
        $this->writeProfiles($this->sampleConfig());
        $p = ImageProfiles::getProfile('recipe.hero');
        $this->assertNotNull($p);
        $this->assertArrayHasKey('variants', $p);
        $this->assertArrayHasKey('display', $p['variants']);
    }

    public function testGetProfileUnknownReturnsNull(): void
    {
        $this->writeProfiles($this->sampleConfig());
        $this->assertNull(ImageProfiles::getProfile('does.not.exist'));
    }

    // ----- isManaged() -----------------------------------------------------

    public function testIsManagedTrueForManagedDir(): void
    {
        $this->writeProfiles($this->sampleConfig());
        $this->assertTrue(ImageProfiles::isManaged('/assets/uploads/recipes/'));
        // Sub-directory of a managed path also matches (prefix/contains).
        $this->assertTrue(ImageProfiles::isManaged('/assets/uploads/recipes/2026/'));
        // Backslash normalisation.
        $this->assertTrue(ImageProfiles::isManaged('\\assets\\uploads\\avatars\\'));
    }

    public function testIsManagedFalseForUnmanagedDir(): void
    {
        $this->writeProfiles($this->sampleConfig());
        $this->assertFalse(ImageProfiles::isManaged('/assets/uploads/other/'));
        $this->assertFalse(ImageProfiles::isManaged('/var/www/somewhere/'));
    }

    public function testIsManagedFalseWhenNoManagedPaths(): void
    {
        $this->writeProfiles(['profiles' => []]);
        $this->assertFalse(ImageProfiles::isManaged('/assets/uploads/recipes/'));
    }

    // ----- imgTag() --------------------------------------------------------

    public function testImgTagUnknownProfileReturnsComment(): void
    {
        $this->writeProfiles($this->sampleConfig());
        $tag = ImageProfiles::imgTag('nope.profile', 'basename-1', 'alt');
        $this->assertStringContainsString('<!-- ImageProfiles: unknown profile', $tag);
        $this->assertStringContainsString('nope.profile', $tag);
    }

    public function testImgTagEmitsSrcAndAlt(): void
    {
        $this->writeProfiles($this->sampleConfig());
        // recipe.hero has a display variant (webp) → default fallback src.
        $tag = ImageProfiles::imgTag('recipe.hero', 'hero-20260526', 'A tasty dish');

        $this->assertStringContainsString('<img', $tag);
        $this->assertStringContainsString('src="hero-20260526-display.webp"', $tag);
        $this->assertStringContainsString('alt="A tasty dish"', $tag);
    }

    public function testImgTagUsesVariantFormatForFallbackExt(): void
    {
        $this->writeProfiles($this->sampleConfig());
        // user.avatar's display variant is png → extension follows the variant.
        $tag = ImageProfiles::imgTag('user.avatar', 'ava-7', '');
        $this->assertStringContainsString('src="ava-7-display.png"', $tag);
    }

    public function testImgTagEmitsSrcsetAndSizesAndBaseUrl(): void
    {
        $this->writeProfiles($this->sampleConfig());
        $tag = ImageProfiles::imgTag('recipe.hero', 'hero-1', 'alt', [
            'base_url' => 'https://cdn.example/img/',
            'sizes'    => '(max-width: 600px) 100vw, 50vw',
            'class'    => 'recipe-hero__img',
            'loading'  => 'lazy',
        ]);

        // srcset built from the [400,800,1200] list, with base_url prefix.
        $this->assertStringContainsString('srcset="', $tag);
        $this->assertStringContainsString('https://cdn.example/img/hero-1-w400.webp 400w', $tag);
        $this->assertStringContainsString('hero-1-w1200.webp 1200w', $tag);
        $this->assertStringContainsString('sizes="(max-width: 600px) 100vw, 50vw"', $tag);
        // Pass-through visual attributes.
        $this->assertStringContainsString('class="recipe-hero__img"', $tag);
        $this->assertStringContainsString('loading="lazy"', $tag);
        // Fallback src still uses base_url.
        $this->assertStringContainsString('src="https://cdn.example/img/hero-1-display.webp"', $tag);
    }

    public function testImgTagNoSrcsetWhenProfileHasNone(): void
    {
        $this->writeProfiles($this->sampleConfig());
        // user.avatar has no srcset list.
        $tag = ImageProfiles::imgTag('user.avatar', 'ava-9', 'avatar');
        $this->assertStringNotContainsString('srcset=', $tag);
    }

    public function testImgTagEscapesAttributes(): void
    {
        $this->writeProfiles($this->sampleConfig());
        $tag = ImageProfiles::imgTag('recipe.hero', 'hero-1', 'Dish "special" & <b>bold</b>');
        // htmlspecialchars(ENT_QUOTES) escaping in the alt attribute.
        $this->assertStringContainsString('&quot;', $tag);
        $this->assertStringContainsString('&amp;', $tag);
        $this->assertStringContainsString('&lt;', $tag);
        $this->assertStringNotContainsString('<b>bold</b>', $tag);
    }
}
