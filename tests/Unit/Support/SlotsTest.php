<?php

namespace Tests\Unit\Support;

use App\Support\Slots;
use PHPUnit\Framework\TestCase;

class SlotsTest extends TestCase
{
    /** One gibibyte, mirroring the legacy `1024*1024*1024` divisor. */
    private const GIB = 1024 * 1024 * 1024;

    public function test_tier_one_when_ratio_below_half_regardless_of_volume(): void
    {
        // 10 GiB up / 30 GiB down → ratio 0.333. Plenty of volume, but
        // the sub-0.5 ratio pins it to tier 1.
        $this->assertSame(1, Slots::maxDownloadSlots(10 * self::GIB, 30 * self::GIB));
    }

    public function test_tier_one_when_volume_below_five_gigs_regardless_of_ratio(): void
    {
        // 1 GiB up / 1 GiB down → ratio 1.0, but only 1 GiB uploaded,
        // so `gigs < 5` keeps it at tier 1.
        $this->assertSame(1, Slots::maxDownloadSlots(1 * self::GIB, 1 * self::GIB));
    }

    public function test_tier_two(): void
    {
        // ratio branch: 6/10 = 0.6 (≥0.5, <0.65), gigs 6 (≥5).
        $this->assertSame(2, Slots::maxDownloadSlots(6 * self::GIB, 10 * self::GIB));
        // volume branch: ratio 1.0 (≥0.65) but gigs 6 (<6.5).
        $this->assertSame(2, Slots::maxDownloadSlots(6 * self::GIB, 6 * self::GIB));
    }

    public function test_tier_three(): void
    {
        // ratio branch: 7/10 = 0.7 (≥0.65, <0.8), gigs 7 (≥6.5).
        $this->assertSame(3, Slots::maxDownloadSlots(7 * self::GIB, 10 * self::GIB));
        // volume branch: ratio 0.875 (≥0.8) but gigs 7 (<8).
        $this->assertSame(3, Slots::maxDownloadSlots(7 * self::GIB, 8 * self::GIB));
    }

    public function test_tier_four(): void
    {
        // ratio branch: 9/10 = 0.9 (≥0.8, <0.95), gigs 9 (≥8).
        $this->assertSame(4, Slots::maxDownloadSlots(9 * self::GIB, 10 * self::GIB));
        // volume branch: ratio 1.0 (≥0.95) but gigs 9 (<9.5).
        $this->assertSame(4, Slots::maxDownloadSlots(9 * self::GIB, 9 * self::GIB));
    }

    public function test_unlimited_when_ratio_and_volume_both_clear_top_tier(): void
    {
        // ratio ≥ 0.95 AND gigs ≥ 9.5 → 0 ("unlimited" sentinel).
        $this->assertSame(0, Slots::maxDownloadSlots(10 * self::GIB, 10 * self::GIB));
        $this->assertSame(0, Slots::maxDownloadSlots(20 * self::GIB, 10 * self::GIB));
    }

    public function test_never_downloaded_falls_back_to_ratio_one(): void
    {
        // Legacy guard: `downloaded > 0 ? up/down : 1`. A user who has
        // never downloaded is treated as ratio 1, never division-by-zero.
        // Low volume still caps at tier 1...
        $this->assertSame(1, Slots::maxDownloadSlots(1 * self::GIB, 0));
        // ...and a large upload with the ratio-1 fallback goes unlimited.
        $this->assertSame(0, Slots::maxDownloadSlots(10 * self::GIB, 0));
    }

    public function test_strict_boundaries_fall_through_to_next_tier(): void
    {
        // Exactly ratio 0.5 with exactly 5 GiB: `< 0.5` and `< 5` are
        // both false, so tier 1 is skipped and tier 2's `ratio < 0.65`
        // catches it. Pins the strict `<` comparison against an
        // accidental `<=` refactor.
        $this->assertSame(2, Slots::maxDownloadSlots(5 * self::GIB, 10 * self::GIB));
    }

    public function test_zero_upload_and_zero_download_is_tier_one(): void
    {
        // Brand-new account: ratio falls back to 1, gigs 0 (<5) → tier 1.
        $this->assertSame(1, Slots::maxDownloadSlots(0, 0));
    }
}
