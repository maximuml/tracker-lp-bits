<?php

namespace Tests\Feature;

use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression coverage for Phase 9: CSS token consolidation across
 * themes and Livewire polling/asset optimizations.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class CssLivewireConsolidationTest extends TestCase
{
    private const DARK_INFO_BG = '#16283a';

    private const LIGHT_INFO_BG = '#eef6fd';

    public function test_nxt_css_defines_dark_status_tokens_for_auto_and_forced_dark(): void
    {
        $css = (string) file_get_contents(base_path('resources/css/nxt.css'));

        $this->assertMatchesRegularExpression(
            '/@media \(prefers-color-scheme: dark\)\s*\{\s*:where\(html:not\(\[data-theme\]\)\),\s*:where\(html\[data-theme="auto"\]\)\s*\{[^}]*--nxm-info-bg:\s*'.preg_quote(self::DARK_INFO_BG, '/').'/s',
            $css,
            'prefers-dark auto/unset fallback must carry dark status tints',
        );
        $this->assertMatchesRegularExpression(
            '/:where\(html\[data-theme="dark"\]\)\s*\{[^}]*--nxm-info-bg:\s*'.preg_quote(self::DARK_INFO_BG, '/').'/s',
            $css,
            'forced data-theme="dark" fallback must carry dark status tints',
        );
        $this->assertMatchesRegularExpression(
            '/:where\(html\[data-theme="dark"\]\)\s*\{[^}]*--nxm-danger-bg:\s*#35191c/s',
            $css,
        );
    }

    public function test_theme_packs_define_status_tints_per_variant(): void
    {
        $unshatter = (string) file_get_contents(base_path('public/styles/Unshatter/theme.css'));
        // Dark is the :root default; light + auto-light must override it.
        $this->assertMatchesRegularExpression(
            '/:root,\s*html\[data-theme="dark"\]:root\s*\{[^}]*--nxm-info-bg:\s*'.preg_quote(self::DARK_INFO_BG, '/').'/s',
            $unshatter,
        );
        $this->assertMatchesRegularExpression(
            '/html\[data-theme="light"\]:root\s*\{[^}]*--nxm-info-bg:\s*'.preg_quote(self::LIGHT_INFO_BG, '/').'/s',
            $unshatter,
            'Unshatter light must override the dark :root status tints',
        );

        $classic = (string) file_get_contents(base_path('public/styles/Classic/theme.css'));
        $this->assertMatchesRegularExpression(
            '/html\[data-theme="dark"\]:root\s*\{[^}]*--nxm-info-bg:\s*'.preg_quote(self::DARK_INFO_BG, '/').'/s',
            $classic,
        );
        $this->assertMatchesRegularExpression(
            '/^:root\s*\{[^}]*--nxm-info-bg:\s*'.preg_quote(self::LIGHT_INFO_BG, '/').'/ms',
            $classic,
        );
    }

    public function test_nxt_css_has_no_duplicate_skip_link_or_tag_rules(): void
    {
        $css = (string) file_get_contents(base_path('resources/css/nxt.css'));

        $this->assertSame(
            1,
            preg_match_all('/^\.skip-link\s*\{/m', $css),
            'Exactly one .skip-link base rule must remain',
        );
        $this->assertSame(
            1,
            preg_match_all('/^\.nx-tag\s*\{/m', $css),
            'Exactly one .nx-tag rule must remain',
        );
        $this->assertStringNotContainsString('textarea {', $css);
        $this->assertStringContainsString('textarea:not([class])', $css);
    }

    public function test_shoutbox_polling_is_visibility_aware_and_toolbar_is_ignored(): void
    {
        $view = (string) file_get_contents(base_path('resources/views/livewire/shoutbox.blade.php'));

        $this->assertMatchesRegularExpression('/wire:poll\.visible\./', $view);
        $this->assertDoesNotMatchRegularExpression('/wire:poll\.\{\{/', $view);
        $this->assertMatchesRegularExpression(
            '/<div wire:ignore>\{\{ \$toolbar \}\}<\/div>/',
            $view,
            'Static BBCode toolbar must be wrapped in wire:ignore',
        );
    }
}
