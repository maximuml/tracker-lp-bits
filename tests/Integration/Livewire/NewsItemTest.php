<?php

declare(strict_types=1);

namespace Tests\Integration\Livewire;

use App\Livewire\NewsItem;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class NewsItemTest extends TestCase
{
    private array $params = [
        'itemId' => 42,
        'added' => '2026-10-01 10:00:00',
        'title' => 'Site update',
        'body' => 'Some news body',
        'editLabel' => 'Edit',
        'deleteLabel' => 'Delete',
        'showHideTitle' => 'Show/Hide',
    ];

    #[Test]
    public function collapsed_item_renders_plus_and_hidden_body(): void
    {
        Livewire::test(NewsItem::class, [...$this->params, 'open' => false, 'leadingBreak' => true])
            ->assertSee('2026.10.01')
            ->assertSee('Site update')
            ->assertSee('class="plus"', false)
            ->assertSee('nx-hidden')
            ->assertSee('newsid=42');
    }

    #[Test]
    public function toggle_reveals_the_news_body(): void
    {
        Livewire::test(NewsItem::class, [...$this->params, 'open' => false])
            ->call('toggle')
            ->assertSee('class="minus"', false)
            ->assertDontSee('nx-hidden');
    }
}
