<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\PersistsKlappe;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One index-page news entry — the klappe expander (data-klappe="a{id}")
 * becomes a server-side toggle; the first item starts open like legacy.
 */
final class NewsItem extends Component
{
    use PersistsKlappe;

    #[Locked]
    public int $itemId = 0;

    #[Locked]
    public string $added = '';

    #[Locked]
    public string $title = '';

    #[Locked]
    public string $body = '';

    #[Locked]
    public string $editLabel = '';

    #[Locked]
    public string $deleteLabel = '';

    #[Locked]
    public string $showHideTitle = '';

    #[Locked]
    public bool $leadingBreak = false;

    #[Locked]
    public bool $canManage = false;

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
        bool $canManage = false,
    ): void {
        $this->itemId = $itemId;
        $this->added = $added;
        $this->title = $title;
        $this->body = $body;
        $this->editLabel = $editLabel;
        $this->deleteLabel = $deleteLabel;
        $this->showHideTitle = $showHideTitle;
        $this->leadingBreak = $leadingBreak;
        $this->canManage = $canManage;
        $this->open = $this->klappeInitial('a'.$itemId, $open);
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
        $this->klappePersist('a'.$this->itemId, $this->open);
    }

    public function render(): View
    {
        return view('livewire.news-item');
    }
}
