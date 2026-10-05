<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * One index-page news entry — the klappe expander (data-klappe="a{id}")
 * becomes a server-side toggle; the first item starts open like legacy.
 */
final class NewsItem extends Component
{
    public int $itemId = 0;

    public string $added = '';

    public string $title = '';

    public string $body = '';

    public string $editLabel = '';

    public string $deleteLabel = '';

    public string $showHideTitle = '';

    public bool $leadingBreak = false;

    public bool $open = true;

    public function mount(
        int $itemId,
        string $added,
        string $title,
        string $body,
        string $editLabel,
        string $deleteLabel,
        string $showHideTitle,
        bool $leadingBreak = false,
        bool $open = true,
    ): void {
        $this->itemId = $itemId;
        $this->added = $added;
        $this->title = $title;
        $this->body = $body;
        $this->editLabel = $editLabel;
        $this->deleteLabel = $deleteLabel;
        $this->showHideTitle = $showHideTitle;
        $this->leadingBreak = $leadingBreak;
        $this->open = $open;
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function render(): View
    {
        return view('livewire.news-item');
    }
}
