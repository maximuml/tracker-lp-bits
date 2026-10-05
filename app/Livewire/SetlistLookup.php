<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\SetlistLookup as SetlistLookupSupport;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Upload-page torrent-name cell: name input + the setlist.fm lookup
 * button. lookup() calls SetlistLookup::fromTorrentName server-side and
 * dispatches 'setlist-append' — the bbcode editor component appends
 * the tracklist to its bound descr body (client-side props ship with
 * the request, so freshly typed name/descr are never stale).
 */
final class SetlistLookup extends Component
{
    public string $name = '';

    public bool $invalid = false;

    public function mount(string $name = '', bool $invalid = false): void
    {
        $this->name = $name;
        $this->invalid = $invalid;
    }

    public function lookup(): void
    {
        $name = trim($this->name);
        if ($name === '') {
            $this->js("alert('Enter the torrent name first.')");

            return;
        }
        try {
            $result = SetlistLookupSupport::fromTorrentName($name);
        } catch (\Throwable) {
            $this->js('alert('.json_encode('Setlist lookup failed.', JSON_THROW_ON_ERROR).')');

            return;
        }
        if (($result['success'] ?? false) && ($result['text'] ?? '') !== '') {
            $this->dispatch('setlist-append', form: 'upload', text: 'descr', content: (string) $result['text']);

            return;
        }
        $error = (string) ($result['error'] ?? 'Setlist not found.');
        $this->js('alert('.json_encode($error, JSON_THROW_ON_ERROR).')');
    }

    public function render(): View
    {
        return view('livewire.setlist-lookup');
    }
}
