<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Responsive-layout invariants: fixed-width declarations that used to
 * blow out the page on narrow viewports (920px header table, 1200px
 * outer shell, `cols=` textareas, long <pre> code/media-info blocks,
 * max-content form-grid label columns) must stay capped or scrollable.
 * These are source-level checks — the mobile.spec.ts Playwright suite
 * verifies the runtime behaviour.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class ResponsiveCssTest extends TestCase
{
    private function modernCss(): string
    {
        $css = file_get_contents(public_path('css/modern.css'));
        $this->assertNotFalse($css);

        return $css;
    }

    private function nxtSource(): string
    {
        $css = file_get_contents(base_path('resources/css/nxt.css'));
        $this->assertNotFalse($css);

        return $css;
    }

    public function test_textarea_cannot_overflow_its_container(): void
    {
        $css = $this->modernCss();
        $this->assertMatchesRegularExpression(
            '/textarea\s*\{[^}]*max-width:\s*100%[^}]*\}/',
            $css,
            'textarea must be capped at container width'
        );
    }

    public function test_pre_blocks_scroll_instead_of_widening_the_page(): void
    {
        $css = $this->modernCss();
        $this->assertMatchesRegularExpression(
            '/\bpre\s*\{[^}]*overflow-x:\s*auto[^}]*\}/',
            $css,
            'pre must scroll horizontally inside its container'
        );
    }

    public function test_classic_header_table_is_capped_not_fixed(): void
    {
        $css = file_get_contents(public_path('styles/Classic/theme.css'));
        $this->assertNotFalse($css);
        $this->assertMatchesRegularExpression(
            '/table\.head,\s*\.nx-head\s*\{[^}]*max-width:\s*920px[^}]*\}/',
            $css,
            'Classic header table must cap at 920px instead of forcing it'
        );
    }

    public function test_outer_shell_and_form_grid_cap_at_viewport(): void
    {
        $css = $this->nxtSource();
        $this->assertMatchesRegularExpression(
            '/\.nx-mainouter\s*\{[^}]*max-width:\s*100%[^}]*\}/',
            $css,
            'nx-mainouter must not exceed viewport width'
        );
        $this->assertMatchesRegularExpression(
            '/\.nx-fgrid--auto\s*\{[^}]*max-width:\s*100%[^}]*\}/',
            $css,
            'max-content form grids must be capped at viewport width'
        );
    }

    public function test_form_grid_stacks_on_mobile(): void
    {
        $css = $this->nxtSource();
        $this->assertMatchesRegularExpression(
            '/@media \(max-width:\s*768px\)\s*\{[^@]*\.nx-fgrid[^}]*grid-template-columns:\s*1fr/',
            $css,
            'form grids must collapse to a single column on narrow viewports'
        );
    }
}
