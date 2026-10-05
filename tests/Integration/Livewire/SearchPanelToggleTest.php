<?php

declare(strict_types=1);

namespace Tests\Integration\Livewire;

use App\Livewire\SearchPanelToggle;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class SearchPanelToggleTest extends TestCase
{
    #[Test]
    public function panel_starts_collapsed_with_plus_icon(): void
    {
        Livewire::test(SearchPanelToggle::class)
            ->assertSee('class="plus"', false)
            ->assertSee('ksearchboxmain')
            ->assertSee('nx-hidden')
            ->assertSee('aria-expanded="false"', false);
    }

    #[Test]
    public function toggle_opens_the_panel(): void
    {
        Livewire::test(SearchPanelToggle::class)
            ->call('toggle')
            ->assertSee('class="minus"', false)
            ->assertSee('aria-expanded="true"', false)
            ->assertDontSee('nxm-searchpanel__body nx-hidden');
    }
}
