<?php

namespace Tests\Unit\Services;

use App\Services\BonusPageService;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Boundary pins for the seedbonus progress-bar class mapping and the
 * percent label formatting extracted from BonusPageService::buildInfo().
 */
#[TestCategory(TestCategory::PURE_UNIT)]
final class BonusPageServiceProgressTest extends TestCase
{
    public function test_progress_class_boundaries(): void
    {
        $this->assertSame('nx-bshop__progress--low', BonusPageService::progressClass(0.0));
        $this->assertSame('nx-bshop__progress--low', BonusPageService::progressClass(29.99));
        $this->assertSame('nx-bshop__progress--low', BonusPageService::progressClass(30.0));
        $this->assertSame('nx-bshop__progress--mid', BonusPageService::progressClass(30.01));
        $this->assertSame('nx-bshop__progress--mid', BonusPageService::progressClass(60.0));
        $this->assertSame('nx-bshop__progress--high', BonusPageService::progressClass(60.01));
        $this->assertSame('nx-bshop__progress--high', BonusPageService::progressClass(100.0));
    }

    public function test_percent_label_rounds_to_one_decimal(): void
    {
        $this->assertSame('33.4', BonusPageService::percentLabel(33.36));
        $this->assertSame('33', BonusPageService::percentLabel(33.0));
        $this->assertSame('0', BonusPageService::percentLabel(0.0));
    }
}
