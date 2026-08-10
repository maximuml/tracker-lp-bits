<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\HitAndRunMode;
use PHPUnit\Framework\TestCase;

final class HitAndRunModeTest extends TestCase
{
    public function test_cases_match_legacy_constants(): void
    {
        $this->assertSame('disabled', HitAndRunMode::DISABLED->value);
        $this->assertSame('manual', HitAndRunMode::MANUAL->value);
        $this->assertSame('global', HitAndRunMode::GLOBAL->value);
    }

    public function test_labels(): void
    {
        $this->assertSame('Disabled', HitAndRunMode::DISABLED->label());
        $this->assertSame('Manual', HitAndRunMode::MANUAL->label());
        $this->assertSame('Global', HitAndRunMode::GLOBAL->label());
    }

    public function test_is_enabled(): void
    {
        $this->assertFalse(HitAndRunMode::DISABLED->isEnabled());
        $this->assertTrue(HitAndRunMode::MANUAL->isEnabled());
        $this->assertTrue(HitAndRunMode::GLOBAL->isEnabled());
    }

    public function test_is_global(): void
    {
        $this->assertFalse(HitAndRunMode::DISABLED->isGlobal());
        $this->assertFalse(HitAndRunMode::MANUAL->isGlobal());
        $this->assertTrue(HitAndRunMode::GLOBAL->isGlobal());
    }

    public function test_from_string_safe(): void
    {
        $this->assertSame(HitAndRunMode::MANUAL, HitAndRunMode::fromStringSafe('manual'));
        $this->assertSame(HitAndRunMode::DISABLED, HitAndRunMode::fromStringSafe(null));
        $this->assertSame(HitAndRunMode::DISABLED, HitAndRunMode::fromStringSafe('unknown'));
    }
}
