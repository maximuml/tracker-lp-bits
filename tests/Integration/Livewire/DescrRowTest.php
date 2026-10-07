<?php

declare(strict_types=1);

namespace Tests\Integration\Livewire;

use App\Livewire\DescrRow;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class DescrRowTest extends TestCase
{
    #[Test]
    public function renders_expanded_with_formatted_body(): void
    {
        Livewire::test(DescrRow::class, [
            'descrRaw' => 'Some [b]bold[/b] description',
            'showOrHideTitle' => 'Show/Hide',
        ])
            ->assertSee('class="minus"', false)
            ->assertSee('bold')
            ->assertSee(__('details.row_description'))
            ->assertDontSee('nx-hidden');
    }

    #[Test]
    public function toggle_hides_the_description(): void
    {
        Livewire::test(DescrRow::class, [
            'descrRaw' => 'description text',
            'showOrHideTitle' => 'Show/Hide',
        ])
            ->call('toggle')
            ->assertSee('class="plus"', false)
            ->assertSee('nx-hidden');
    }
}
