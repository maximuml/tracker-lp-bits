<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\TorrentPromotion;
use PHPUnit\Framework\TestCase;

final class TorrentPromotionTest extends TestCase
{
    public function test_cases_match_legacy_constants(): void
    {
        $this->assertSame(1, TorrentPromotion::NORMAL->value);
        $this->assertSame(2, TorrentPromotion::FREE->value);
        $this->assertSame(3, TorrentPromotion::TWO_TIMES_UP->value);
        $this->assertSame(4, TorrentPromotion::FREE_TWO_TIMES_UP->value);
        $this->assertSame(5, TorrentPromotion::HALF_DOWN->value);
        $this->assertSame(6, TorrentPromotion::HALF_DOWN_TWO_TIMES_UP->value);
        $this->assertSame(7, TorrentPromotion::ONE_THIRD_DOWN->value);
    }

    public function test_labels_are_english_fallback(): void
    {
        $this->assertSame('Normal', TorrentPromotion::NORMAL->label());
        $this->assertSame('Free', TorrentPromotion::FREE->label());
        $this->assertSame('2X Free', TorrentPromotion::FREE_TWO_TIMES_UP->label());
        $this->assertSame('30%', TorrentPromotion::ONE_THIRD_DOWN->label());
    }

    public function test_multipliers(): void
    {
        $this->assertSame(1, TorrentPromotion::NORMAL->upMultiplier());
        $this->assertSame(1, TorrentPromotion::NORMAL->downMultiplier());

        $this->assertSame(1, TorrentPromotion::FREE->upMultiplier());
        $this->assertSame(0, TorrentPromotion::FREE->downMultiplier());

        $this->assertSame(2, TorrentPromotion::TWO_TIMES_UP->upMultiplier());
        $this->assertSame(1, TorrentPromotion::TWO_TIMES_UP->downMultiplier());

        $this->assertSame(2, TorrentPromotion::HALF_DOWN_TWO_TIMES_UP->upMultiplier());
        $this->assertSame(0.5, TorrentPromotion::HALF_DOWN_TWO_TIMES_UP->downMultiplier());

        $this->assertSame(0.3, TorrentPromotion::ONE_THIRD_DOWN->downMultiplier());
    }

    public function test_color_matches_legacy_gradients(): void
    {
        $this->assertSame('', TorrentPromotion::NORMAL->color());
        $this->assertStringContainsString('rgba(0,52,206', TorrentPromotion::FREE->color());
        $this->assertStringContainsString('rgba(0,153,0', TorrentPromotion::TWO_TIMES_UP->color());
    }

    public function test_from_int_safe_returns_matching_case(): void
    {
        $this->assertSame(TorrentPromotion::FREE, TorrentPromotion::fromIntSafe(2));
        $this->assertSame(TorrentPromotion::NORMAL, TorrentPromotion::fromIntSafe(999));
    }

    public function test_is_promotion(): void
    {
        $this->assertFalse(TorrentPromotion::NORMAL->isPromotion());
        $this->assertTrue(TorrentPromotion::FREE->isPromotion());
        $this->assertTrue(TorrentPromotion::HALF_DOWN_TWO_TIMES_UP->isPromotion());
    }
}
