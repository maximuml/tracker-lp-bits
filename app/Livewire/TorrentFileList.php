<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Contracts\Repositories\TorrentAjaxRepositoryInterface;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Torrent\FileBadge;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The expandable file list on details.php — replaces the
 * viewfilelist.php AJAX fragment: show()/hide() morph the toggle links
 * and the file table server-side.
 */
final class TorrentFileList extends Component
{
    public int $torrentId = 0;

    public bool $open = false;

    private ?TorrentAjaxRepositoryInterface $torrentAjaxRepository = null;

    private ?CurrentUser $currentUser = null;

    public function boot(TorrentAjaxRepositoryInterface $torrentAjaxRepository, CurrentUser $currentUser): void
    {
        $this->torrentAjaxRepository = $torrentAjaxRepository;
        $this->currentUser = $currentUser;
    }

    public function mount(int $torrentId): void
    {
        $this->torrentId = $torrentId;
    }

    public function show(): void
    {
        $this->open = true;
    }

    public function hide(): void
    {
        $this->open = false;
    }

    public function render(): View
    {
        $files = $this->open
            ? $this->torrentAjaxRepository()->fileList($this->torrentId)
                ->map(fn ($fileRow): array => [
                    'badge' => FileBadge::forFilename((string) (((array) $fileRow)['filename'] ?? '')),
                    'filename' => (string) (((array) $fileRow)['filename'] ?? ''),
                    'size' => Format::size((float) (((array) $fileRow)['size'] ?? 0)),
                ])
                ->all()
            : [];

        return view('livewire.torrent-file-list', ['files' => $files, 'CURUSER' => $this->currentUser()->get()]);
    }

    private function torrentAjaxRepository(): TorrentAjaxRepositoryInterface
    {
        return $this->torrentAjaxRepository ?? throw new \LogicException('TorrentFileList used before boot()');
    }

    private function currentUser(): CurrentUser
    {
        return $this->currentUser ?? throw new \LogicException('TorrentFileList used before boot()');
    }
}
