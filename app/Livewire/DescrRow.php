<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\PersistsKlappe;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Torrent-details description row — the data-klappe="descr" toggle
 * becomes a server-side expand/collapse; the body is re-rendered
 * through Format::formatComment so output matches the legacy SafeHtml.
 */
final class DescrRow extends Component
{
    use PersistsKlappe;

    public string $descrRaw = '';

    public string $showOrHideTitle = '';

    public bool $open = true;

    public function mount(string $descrRaw, string $showOrHideTitle): void
    {
        $this->descrRaw = $descrRaw;
        $this->showOrHideTitle = $showOrHideTitle;
        $this->open = $this->klappeInitial('descr', true);
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
        $this->klappePersist('descr', $this->open);
    }

    public function render(): View
    {
        return view('livewire.descr-row');
    }
}
