<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Models\User;
use App\Repositories\TorrentDetailRepository;
use App\Services\ThankService;
use App\ViewModels\Torrent\ThanksSectionFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The "Thanks" row on details.php: say-thanks button plus the list of
 * thanking users. Replaces the legacy `saythanks()` innerHTML swap —
 * `thank()` runs the same ThankService transaction as thanks.php
 * (dedupe + self-check + bonus increments) and the morph re-renders
 * the row server-side.
 */
final class ThanksSection extends Component
{
    public int $torrentId = 0;

    public string $status = '';

    private ?TorrentDetailRepository $detailRepository = null;

    private ?ThankService $service = null;

    private ?TorrentRepositoryInterface $torrents = null;

    private ?ThanksSectionFactory $factory = null;

    public function boot(
        TorrentDetailRepository $detailRepository,
        ThankService $service,
        TorrentRepositoryInterface $torrents,
        ThanksSectionFactory $factory,
    ): void {
        $this->detailRepository = $detailRepository;
        $this->service = $service;
        $this->torrents = $torrents;
        $this->factory = $factory;
    }

    public function mount(int $torrentId): void
    {
        $this->torrentId = $torrentId;
    }

    public function thank(): void
    {
        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return;
        }

        $torrent = $this->torrents()->findById($this->torrentId);
        if (! $torrent) {
            $this->status = (string) __('comment.std_no_torrent_id');

            return;
        }

        try {
            $this->service()->thankTorrent($user, $torrent);
        } catch (\LogicException|\RuntimeException $e) {
            $this->status = $e->getMessage();

            return;
        }

        $this->status = '';
    }

    public function render(): View
    {
        $userId = (int) (Auth::guard('nexus-web')->id() ?? 0);
        $thanksInfo = $this->detailRepository()->getThanksInfo($this->torrentId, $userId);

        return view('livewire.thanks-section', [
            'vm' => $this->factory()->build($this->torrentId, ['id' => $userId], $thanksInfo),
        ]);
    }

    private function detailRepository(): TorrentDetailRepository
    {
        return $this->detailRepository ?? throw new \LogicException('ThanksSection used before boot()');
    }

    private function service(): ThankService
    {
        return $this->service ?? throw new \LogicException('ThanksSection used before boot()');
    }

    private function torrents(): TorrentRepositoryInterface
    {
        return $this->torrents ?? throw new \LogicException('ThanksSection used before boot()');
    }

    private function factory(): ThanksSectionFactory
    {
        return $this->factory ?? throw new \LogicException('ThanksSection used before boot()');
    }
}
