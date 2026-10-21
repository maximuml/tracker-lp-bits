<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\DTOs\Auth\ActorContext;
use App\Repositories\TorrentDetailRepository;
use App\Services\ThankService;
use App\ViewModels\Torrent\ThanksSectionFactory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
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
    #[Locked]
    public int $torrentId = 0;

    public string $status = '';

    private ?TorrentDetailRepository $detailRepository = null;

    private ?ThankService $service = null;

    private ?TorrentRepositoryInterface $torrents = null;

    private ?ThanksSectionFactory $factory = null;

    private ?ActorContext $actor = null;

    public function boot(
        TorrentDetailRepository $detailRepository,
        ThankService $service,
        TorrentRepositoryInterface $torrents,
        ThanksSectionFactory $factory,
        ActorContext $actor,
    ): void {
        $this->detailRepository = $detailRepository;
        $this->service = $service;
        $this->torrents = $torrents;
        $this->factory = $factory;
        $this->actor = $actor;
    }

    public function mount(int $torrentId): void
    {
        $this->torrentId = $torrentId;
    }

    public function thank(): void
    {
        $user = $this->actor()->user;
        if ($user === null) {
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
        $userId = $this->actor()->id;
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

    private function actor(): ActorContext
    {
        return $this->actor ?? throw new \LogicException('ThanksSection used before boot()');
    }
}
