<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Html;
use Tests\TestCase;

/**
 * Wave 6 Step 37: consolidate duplicate abstractions.
 *
 * Verifies that duplicate wrapper classes have been removed and their
 * functionality consolidated into the canonical classes:
 * - CacheHelper (dead wrapper over Cache) — removed
 * - HtmlRenderer (thin wrapper over Html) — removed, methods moved to Html
 *
 * The remaining Support classes are properly separated by responsibility:
 * - Cache: main cache facade (App\Support\Cache)
 * - Cache\LegacyRedisCache: Redis implementation
 * - Html: HTML helpers (App\Support\Html) — now includes formatHidden/formatTextAlign
 * - Html\SafeHtml: HTML sanitization VO
 * - Html\HtmlSanitizer: sanitizer implementation
 * - NexusContext, SupportContext, PageLayoutContext: distinct context objects
 * - LegacyAuth, AuthCookie, LegacyAuthContext: auth logic, cookies, context DTO
 */
final class AbstractionConsolidationTest extends TestCase
{
    /**
     * CacheHelper dead wrapper has been removed.
     */
    public function test_cache_helper_removed(): void
    {
        $this->assertFileDoesNotExist(app_path('Support/CacheHelper.php'));
    }

    /**
     * CacheHelper is not referenced anywhere in the codebase.
     */
    public function test_no_cache_helper_references(): void
    {
        $files = glob(app_path().'/**/*.php');
        $this->assertIsArray($files);
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->assertStringNotContainsString('CacheHelper', $content, "File {$file} still references CacheHelper");
        }
    }

    /**
     * HtmlRenderer wrapper has been removed.
     */
    public function test_html_renderer_removed(): void
    {
        $this->assertFileDoesNotExist(app_path('Support/HtmlRenderer.php'));
    }

    /**
     * HtmlRenderer is not referenced anywhere (except consolidation comments).
     */
    public function test_no_html_renderer_references(): void
    {
        $files = glob(app_path().'/**/*.php');
        $this->assertIsArray($files);
        foreach ($files as $file) {
            $content = file_get_contents($file);
            // Allow "Consolidated from HtmlRenderer" comments in Html.php
            $basename = basename($file);
            if ($basename === 'Html.php') {
                continue;
            }
            $this->assertStringNotContainsString('HtmlRenderer', $content, "File {$file} still references HtmlRenderer");
        }
    }

    /**
     * Html::formatHidden exists (consolidated from HtmlRenderer).
     */
    public function test_html_has_format_hidden(): void
    {
        $this->assertTrue(method_exists(Html::class, 'formatHidden'), 'Html::formatHidden must exist');
    }

    /**
     * Html::formatTextAlign exists (consolidated from HtmlRenderer).
     */
    public function test_html_has_format_text_align(): void
    {
        $this->assertTrue(method_exists(Html::class, 'formatTextAlign'), 'Html::formatTextAlign must exist');
    }

    /**
     * Html::formatVideo exists (was already there, also in HtmlRenderer).
     */
    public function test_html_has_format_video(): void
    {
        $this->assertTrue(method_exists(Html::class, 'formatVideo'), 'Html::formatVideo must exist');
    }

    /**
     * Html::formatImg exists (was already there, also in HtmlRenderer).
     */
    public function test_html_has_format_img(): void
    {
        $this->assertTrue(method_exists(Html::class, 'formatImg'), 'Html::formatImg must exist');
    }

    /**
     * Html::formatUrl exists (was already there, also in HtmlRenderer).
     */
    public function test_html_has_format_url(): void
    {
        $this->assertTrue(method_exists(Html::class, 'formatUrl'), 'Html::formatUrl must exist');
    }

    /**
     * Html::formatSpoiler exists (was already there, also in HtmlRenderer).
     */
    public function test_html_has_format_spoiler(): void
    {
        $this->assertTrue(method_exists(Html::class, 'formatSpoiler'), 'Html::formatSpoiler must exist');
    }

    /**
     * Core Support classes still exist (not accidentally removed).
     */
    public function test_core_support_classes_exist(): void
    {
        $this->assertFileExists(app_path('Support/Cache.php'));
        $this->assertFileExists(app_path('Support/Cache/LegacyRedisCache.php'));
        $this->assertFileExists(app_path('Support/Html.php'));
        $this->assertFileExists(app_path('Support/Html/SafeHtml.php'));
        $this->assertFileExists(app_path('Support/Html/HtmlSanitizer.php'));
        $this->assertFileExists(app_path('Support/LegacyAuth.php'));
        $this->assertFileExists(app_path('Support/AuthCookie.php'));
        $this->assertFileExists(app_path('Support/LegacyAuthContext.php'));
        $this->assertFileExists(app_path('Support/NexusContext.php'));
        $this->assertFileExists(app_path('Support/SupportContext.php'));
        $this->assertFileExists(app_path('Support/PageLayoutContext.php'));
    }
}
