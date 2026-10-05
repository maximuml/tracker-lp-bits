<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\TorrentDetailRepository;
use App\Services\MagicRewardService;
use App\ViewModels\Torrent\MagicSectionFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The "Magic value of awards" row on details.php — mirrors the legacy
 * fragment but renders server-side on each Livewire update: giving a
 * magic value morphs the options list into the disabled "given" state
 * without a full page reload.
 *
 * `showAll` replaces the #magic_show_all hidden-span toggle; the giver
 * list still renders all entries server-side and reveals them on click.
 */
final class MagicSection extends Component
{
    public int $torrentId = 0;

    public bool $showAll = false;

    public string $status = '';

    private ?MagicRewardService $service = null;

    private ?TorrentDetailRepository $detailRepository = null;

    private ?TorrentRepositoryInterface $torrentRepository = null;

    private ?MagicSectionFactory $factory = null;

    public function boot(
        MagicRewardService $service,
        TorrentDetailRepository $detailRepository,
        TorrentRepositoryInterface $torrentRepository,
        MagicSectionFactory $factory
    ): void {
        $this->service = $service;
        $this->detailRepository = $detailRepository;
        $this->torrentRepository = $torrentRepository;
        $this->factory = $factory;
    }

    public function mount(int $torrentId): void
    {
        $this->torrentId = $torrentId;
    }

    public function give(int $value): void
    {
        $this->status = '';
        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return;
        }

        try {
            $this->service()->give($user, $this->torrentId, $value);
        } catch (\LogicException $e) {
            $this->status = $e->getMessage();
        }
    }

    public function showAllGivers(): void
    {
        $this->showAll = true;
    }

    public function render(): View
    {
        $user = Auth::guard('nexus-web')->user();
        $currentUser = $user instanceof User ? $user->toLegacyArray() : [];
        $ownerId = $this->torrentRepository()->getOwnerId($this->torrentId);
        $isOwner = $ownerId !== null && (int) $ownerId === (int) ($currentUser['id'] ?? 0);
        $magicInfo = $this->detailRepository()->getMagicInfo($this->torrentId, (int) ($currentUser['id'] ?? 0));

        return view('livewire.magic-section', [
            'vm' => $this->factory()->build(
                $this->torrentId,
                $currentUser,
                $isOwner,
                $magicInfo,
                Setting::getBonusRewardOptions()
            ),
        ]);
    }

    private function service(): MagicRewardService
    {
        return $this->service ?? throw new \LogicException('MagicSection used before boot()');
    }

    private function detailRepository(): TorrentDetailRepository
    {
        return $this->detailRepository ?? throw new \LogicException('MagicSection used before boot()');
    }

    private function torrentRepository(): TorrentRepositoryInterface
    {
        return $this->torrentRepository ?? throw new \LogicException('MagicSection used before boot()');
    }

    private function factory(): MagicSectionFactory
    {
        return $this->factory ?? throw new \LogicException('MagicSection used before boot()');
    }
}
