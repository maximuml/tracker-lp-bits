<?php

namespace Tests\Integration\Support;

use App\Support\Html\SafeHtml;
use App\Support\Pagination;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class PaginationTest extends TestCase
{
    private const LABELS = [
        'prev' => 'Prev',
        'next' => 'Next',
        'alt_prev_title' => 'Alt+PageUp',
        'alt_next_title' => 'Alt+PageDown',
        'shift_prev_title' => 'Shift+PageUp',
        'shift_next_title' => 'Shift+PageDown',
    ];

    // ---------- render() ----------

    public function test_render_returns_six_element_array(): void
    {
        $result = Pagination::render(10, 100, '/list.php?', 0, 10, self::LABELS);
        $this->assertCount(6, $result);
    }

    public function test_render_pagers_are_safe_html(): void
    {
        $result = Pagination::render(10, 100, '/list.php?', 0, 10, self::LABELS);
        $this->assertInstanceOf(SafeHtml::class, $result[0]);
        $this->assertInstanceOf(SafeHtml::class, $result[1]);
    }

    public function test_render_limit_clause_format(): void
    {
        $result = Pagination::render(20, 200, '/list.php?', 3, 10, self::LABELS);
        $this->assertSame('limit 20 offset 60', $result[2]);
        $this->assertSame(60, $result[3]);
        $this->assertSame(20, $result[4]);
        $this->assertSame(3, $result[5]);
    }

    public function test_render_first_page_prev_is_disabled(): void
    {
        $result = Pagination::render(10, 50, '/list.php?', 0, 5, self::LABELS);
        $this->assertStringContainsString('<span class="inline-block border border-nxm-border px-2 py-0.5 text-nxm-text-dim">&lsaquo; Prev</span>', $result[0]);
    }

    public function test_render_last_page_next_is_disabled(): void
    {
        $result = Pagination::render(10, 50, '/list.php?', 4, 5, self::LABELS);
        $this->assertStringContainsString('<span class="inline-block border border-nxm-border px-2 py-0.5 text-nxm-text-dim">Next &rsaquo;</span>', $result[0]);
    }

    public function test_render_mid_page_has_both_links(): void
    {
        $result = Pagination::render(10, 50, '/list.php?', 2, 5, self::LABELS);
        // prev link present
        $this->assertStringContainsString('page=1', $result[0]);
        // next link present
        $this->assertStringContainsString('page=3', $result[0]);
    }

    public function test_render_zero_count_no_page_numbers(): void
    {
        $result = Pagination::render(10, 0, '/list.php?', 0, 0, self::LABELS);
        $this->assertStringNotContainsString('&nbsp;-&nbsp;', $result[0]);
        $this->assertSame(
            str_replace('aria-label="Pagination top"', '', $result[0]->toHtml()),
            str_replace('aria-label="Pagination bottom"', '', $result[1]->toHtml()),
        );
    }

    public function test_render_current_page_is_marked_current(): void
    {
        $result = Pagination::render(10, 30, '/list.php?', 1, 3, self::LABELS);
        // Page 1 (0-indexed) covers items "11 - 20" and is the current page button
        $this->assertStringContainsString('bg-nxm-accent', $result[0]);
        $this->assertStringContainsString('aria-current="page" title="11 - 20">2</span>', $result[0]);
    }

    public function test_render_pagertop_has_nexus_pagination_class(): void
    {
        $result = Pagination::render(10, 30, '/list.php?', 0, 3, self::LABELS);
        $this->assertStringContainsString('class="my-2.5 text-center"', $result[0]);
    }

    public function test_render_pager_top_and_bottom_are_identical(): void
    {
        $result = Pagination::render(10, 30, '/list.php?', 0, 3, self::LABELS);
        $this->assertStringContainsString('&lsaquo; Prev', $result[0]);
        $this->assertStringContainsString('aria-label="Pagination top"', $result[0]->toHtml());
        $this->assertStringContainsString('aria-label="Pagination bottom"', $result[1]->toHtml());
        $this->assertSame(
            str_replace('aria-label="Pagination top"', '', $result[0]->toHtml()),
            str_replace('aria-label="Pagination bottom"', '', $result[1]->toHtml()),
        );
    }

    public function test_render_presto_uses_shift_titles(): void
    {
        $result = Pagination::render(10, 50, '/list.php?', 2, 5, self::LABELS, 'page', true);
        $this->assertStringContainsString('Shift+PageUp', $result[0]);
        $this->assertStringContainsString('Shift+PageDown', $result[0]);
    }

    public function test_render_non_presto_uses_alt_titles(): void
    {
        $result = Pagination::render(10, 50, '/list.php?', 2, 5, self::LABELS, 'page', false);
        $this->assertStringContainsString('Alt+PageUp', $result[0]);
        $this->assertStringContainsString('Alt+PageDown', $result[0]);
    }

    public function test_render_custom_pagename(): void
    {
        $result = Pagination::render(10, 50, '/list.php?', 2, 5, self::LABELS, 'p');
        $this->assertStringContainsString('p=1', $result[0]);
        $this->assertStringContainsString('p=3', $result[0]);
    }

    public function test_render_ellipsis_for_many_pages(): void
    {
        // With 20 pages and page=10, dots should appear between page groups
        $result = Pagination::render(10, 200, '/list.php?', 10, 20, self::LABELS);
        $this->assertStringContainsString('…', $result[0]);
    }

    public function test_render_last_page_shows_correct_end_count(): void
    {
        // 25 items, 10 per page = 3 pages. Last page shows the "21 - 25" range in its tooltip
        $result = Pagination::render(10, 25, '/list.php?', 2, 3, self::LABELS);
        $this->assertStringContainsString('title="21 - 25"', $result[0]);
    }

    public function test_render_href_is_escaped(): void
    {
        $result = Pagination::render(10, 30, '/list.php?foo=1&bar=2&', 0, 3, self::LABELS);
        $this->assertStringContainsString('&amp;bar=2&amp;page=', $result[0]);
    }

    // ---------- resolvePage() ----------

    public function test_resolve_page_null_returns_zero_by_default(): void
    {
        $this->assertSame(0, Pagination::resolvePage(null, 100, 10));
    }

    public function test_resolve_page_null_with_last_page_default(): void
    {
        // 100 items, 10 per page → last page is floor((100-1)/10) = 9
        $this->assertSame(9, Pagination::resolvePage(null, 100, 10, true));
    }

    public function test_resolve_page_negative_falls_to_default(): void
    {
        $this->assertSame(0, Pagination::resolvePage(-1, 100, 10));
        $this->assertSame(9, Pagination::resolvePage(-5, 100, 10, true));
    }

    public function test_resolve_page_valid_integer(): void
    {
        $this->assertSame(5, Pagination::resolvePage(5, 100, 10));
        $this->assertSame(5, Pagination::resolvePage('5', 100, 10));
    }

    public function test_resolve_page_zero_is_valid(): void
    {
        $this->assertSame(0, Pagination::resolvePage(0, 100, 10));
        $this->assertSame(0, Pagination::resolvePage('0', 100, 10));
    }

    public function test_resolve_page_last_page_default_with_empty_count(): void
    {
        // count=0, lastPageDefault=true → floor(-1/10) = -1, clamped to 0
        $this->assertSame(0, Pagination::resolvePage(null, 0, 10, true));
    }
}
