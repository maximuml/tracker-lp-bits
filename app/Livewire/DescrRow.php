<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Torrent-details description row — the data-klappe="descr" toggle
 * becomes a server-side expand/collapse; the body is re-rendered
 * through Format::formatComment so output matches the legacy SafeHtml.
 */
final class DescrRow extends Component
{
    public string $descrRaw = '';

    public string $showOrHideTitle = '';

    public bool $open = true;

    public function mount(string $descrRaw, string $showOrHideTitle): void
    {
        $this->descrRaw = $descrRaw;
        $this->showOrHideTitle = $showOrHideTitle;
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function render(): View
    {
        return view('livewire.descr-row');
    }
}
