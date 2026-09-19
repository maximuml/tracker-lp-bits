<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\LegacyRuntime;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT)]
class LegacyRuntimeTest extends TestCase
{
    public function test_defaults_to_non_legacy_non_tracker(): void
    {
        $runtime = new LegacyRuntime;

        $this->assertFalse($runtime->isLegacy());
        $this->assertFalse($runtime->isTracker());
    }

    public function test_constructor_captures_entry_state(): void
    {
        $runtime = new LegacyRuntime(entryLegacy: true, entryTracker: true);

        $this->assertTrue($runtime->isLegacy());
        $this->assertTrue($runtime->isTracker());
    }

    public function test_mark_flags_independently(): void
    {
        $runtime = new LegacyRuntime;

        $runtime->markLegacy();
        $this->assertTrue($runtime->isLegacy());
        $this->assertFalse($runtime->isTracker());

        $runtime->markTracker();
        $this->assertTrue($runtime->isTracker());
        $this->assertTrue($runtime->isLegacy());
    }

    public function test_mark_can_unset_flag(): void
    {
        $runtime = new LegacyRuntime(entryLegacy: true);

        $runtime->markLegacy(false);

        $this->assertFalse($runtime->isLegacy());
    }

    public function test_reset_restores_entry_state(): void
    {
        $runtime = new LegacyRuntime(entryLegacy: false, entryTracker: false);

        $runtime->markLegacy();
        $runtime->markTracker();
        $runtime->reset();

        $this->assertFalse($runtime->isLegacy());
        $this->assertFalse($runtime->isTracker());
    }

    public function test_reset_restores_non_default_entry_state(): void
    {
        $runtime = new LegacyRuntime(entryLegacy: true, entryTracker: true);

        $runtime->markLegacy(false);
        $runtime->markTracker(false);
        $runtime->reset();

        $this->assertTrue($runtime->isLegacy());
        $this->assertTrue($runtime->isTracker());
    }

    public function test_boot_entry_updates_defaults_and_resets(): void
    {
        $runtime = new LegacyRuntime;

        $runtime->bootEntry(legacy: true, tracker: true);

        $this->assertTrue($runtime->isLegacy());
        $this->assertTrue($runtime->isTracker());

        // The new entry state is what reset() restores.
        $runtime->markLegacy(false);
        $runtime->reset();
        $this->assertTrue($runtime->isLegacy());
        $this->assertTrue($runtime->isTracker());
    }

    public function test_container_resolves_shared_instance(): void
    {
        $first = $this->app->make(LegacyRuntime::class);
        $first->markLegacy();

        $second = $this->app->make(LegacyRuntime::class);

        $this->assertSame($first, $second);
        $this->assertTrue($second->isLegacy());
    }
}
