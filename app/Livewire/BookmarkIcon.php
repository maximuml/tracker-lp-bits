<?php

declare(strict_types=1);

namespace App\Livewire;

use App\DTOs\Auth\ActorContext;
use App\Services\TorrentBookmarkService;
use App\Support\Cache\NexusCache;
use App\Support\TorrentBookmark;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Per-row bookmark toggle (torrents lists + the details download row) —
 * replaces the `bookmark.php` text-response swap: `toggle()` runs the
 * same `TorrentBookmarkService::toggleBookmark` and the icon morphs
 * between the bookmark/delbookmark states without a reload.
 */
final class BookmarkIcon extends Component
{
    #[Locked]
    public int $torrentId = 0;

    public bool $bookmarked = false;

    private ?TorrentBookmarkService $service = null;

    private ?ActorContext $actor = null;

    public function boot(TorrentBookmarkService $service, ActorContext $actor): void
    {
        $this->service = $service;
        $this->actor = $actor;
    }

    public function mount(int $torrentId): void
    {
        $this->torrentId = $torrentId;
        if ($this->actor()->id > 0) {
            $this->bookmarked = TorrentBookmark::isBookmarked(
                NexusCache::instance(),
                $this->actor()->id,
                $torrentId
            );
        }
    }

    public function toggle(): void
    {
        if ($this->actor()->id <= 0) {
            return;
        }

        $status = $this->service()->toggleBookmark($this->actor()->id, $this->torrentId);
        if ($status === 'added') {
            $this->bookmarked = true;
        } elseif ($status === 'deleted') {
            $this->bookmarked = false;
        }
    }

    public function render(): View
    {
        return view('livewire.bookmark-icon');
    }

    private function service(): TorrentBookmarkService
    {
        return $this->service ?? throw new \LogicException('BookmarkIcon used before boot()');
    }

    private function actor(): ActorContext
    {
        return $this->actor ?? throw new \LogicException('BookmarkIcon used before boot()');
    }
}
