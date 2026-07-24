<?php

namespace Tests\Unit\Support;

use App\Support\Promotion;
use PHPUnit\Framework\TestCase;

/**
 * Pins down the promotion → CSS background-class mapping drained out of
 * get_torrent_bg_color() into App\Support\Promotion (Phase 5).
 */
class PromotionTest extends TestCase
{
    public function test_no_promotion_returns_empty_string_not_null(): void
    {
        // Code 1 must return '' (handled) rather than null so the caller
        // skips the sticky-background fallback — the legacy distinction.
        $this->assertSame('', Promotion::backgroundClass(1));
    }

    public function test_known_promotions_map_to_their_bg_classes(): void
    {
        $this->assertSame(" class='free_bg'", Promotion::backgroundClass(2));
        $this->assertSame(" class='twoup_bg'", Promotion::backgroundClass(3));
        $this->assertSame(" class='twoupfree_bg'", Promotion::backgroundClass(4));
        $this->assertSame(" class='halfdown_bg'", Promotion::backgroundClass(5));
        $this->assertSame(" class='twouphalfdown_bg'", Promotion::backgroundClass(6));
        $this->assertSame(" class='thirtypercentdown_bg'", Promotion::backgroundClass(7));
    }

    public function test_out_of_range_codes_return_null(): void
    {
        // null lets the caller leave $sphighlight untouched (sticky bg).
        $this->assertNull(Promotion::backgroundClass(0));
        $this->assertNull(Promotion::backgroundClass(8));
        $this->assertNull(Promotion::backgroundClass(-1));
        $this->assertNull(Promotion::backgroundClass(99));
    }
}
