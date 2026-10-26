<?php

declare(strict_types=1);

namespace App\Livewire;

use App\DTOs\Auth\ActorContext;
use App\Support\SetlistLookup as SetlistLookupSupport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Upload-page torrent-name cell: name input + the setlist.fm lookup
 * button. lookup() calls SetlistLookup::fromTorrentName server-side and
 * dispatches 'setlist-append' — the bbcode editor component appends
 * the tracklist to its bound descr body (client-side props ship with
 * the request, so freshly typed name/descr are never stale).
 *
 * Errors surface through the $error property rendered in the view —
 * $this->js('alert(...)') needs unsafe-eval, which the strict public
 * CSP forbids.
 */
final class SetlistLookup extends Component
{
    public string $name = '';

    public bool $invalid = false;

    #[Locked]
    public string $error = '';

    private ?ActorContext $actor = null;

    public function boot(ActorContext $actor): void
    {
        $this->actor = $actor;
    }

    public function mount(string $name = '', bool $invalid = false): void
    {
        $this->name = $name;
        $this->invalid = $invalid;
    }

    public function lookup(): void
    {
        if ($this->actor()->id <= 0) {
            return;
        }
        $this->error = '';

        $name = trim($this->name);
        if ($name === '') {
            $this->error = __('upload.fill_setlist_empty_name');

            return;
        }
        try {
            $result = SetlistLookupSupport::fromTorrentName($name);
        } catch (\Throwable) {
            $this->error = __('upload.fill_setlist_failed');

            return;
        }
        if (($result['success'] ?? false) && ($result['text'] ?? '') !== '') {
            $this->dispatch('setlist-append', form: 'upload', text: 'descr', content: (string) $result['text']);

            return;
        }
        $this->error = (string) ($result['error'] ?? __('upload.fill_setlist_not_found'));
    }

    public function render(): View
    {
        return view('livewire.setlist-lookup');
    }

    private function actor(): ActorContext
    {
        return $this->actor ?? throw new \LogicException('SetlistLookup used before boot()');
    }
}
