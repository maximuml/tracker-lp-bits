<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\PersistsKlappe;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Torrents search-panel collapse (data-klappe="searchboxmain") —
 * the toggle flips server-side; the slot body is wire:ignored so
 * morphing never resets inputs the user already filled in.
 */
final class SearchPanelToggle extends Component
{
    use PersistsKlappe;

    public bool $open = false;

    public function mount(): void
    {
        $this->open = $this->klappeInitial('searchboxmain', false);
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
        $this->klappePersist('searchboxmain', $this->open);
    }

    public function render(): View
    {
        return view('livewire.search-panel-toggle');
    }
}
