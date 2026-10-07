<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\User;
use App\Services\TorrentBookmarkService;
use App\Support\Cache\NexusCache;
use App\Support\TorrentBookmark;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Per-row bookmark toggle (torrents lists + the details download row) —
 * replaces the `bookmark.php` text-response swap: `toggle()` runs the
 * same `TorrentBookmarkService::toggleBookmark` and the icon morphs
 * between the bookmark/delbookmark states without a reload.
 */
final class BookmarkIcon extends Component
{
    public int $torrentId = 0;

    public bool $bookmarked = false;

    private ?TorrentBookmarkService $service = null;

    public function boot(TorrentBookmarkService $service): void
    {
        $this->service = $service;
    }

    public function mount(int $torrentId): void
    {
        $this->torrentId = $torrentId;
        $user = Auth::guard('nexus-web')->user();
        if ($user instanceof User) {
            $this->bookmarked = TorrentBookmark::isBookmarked(
                NexusCache::instance(),
                (int) $user->id,
                $torrentId
            );
        }
    }

    public function toggle(): void
    {
        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return;
        }

        $status = $this->service()->toggleBookmark((int) $user->id, $this->torrentId);
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
}
