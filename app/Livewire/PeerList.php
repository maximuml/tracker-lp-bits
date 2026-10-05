<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Contracts\Repositories\TorrentAjaxRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Support\CurrentUser;
use App\ViewModels\Torrent\PeerTableFactory;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The expandable peer list on details.php — replaces the
 * viewpeerlist.php AJAX fragment: show()/hide() morph the seeder/leecher
 * tables server-side. `openOnLoad` mirrors the legacy ?dllist=1 autoload.
 */
final class PeerList extends Component
{
    public int $torrentId = 0;

    public int $seeders = 0;

    public int $leechers = 0;

    public bool $open = false;

    private ?TorrentAjaxRepositoryInterface $torrentAjaxRepository = null;

    private ?PeerTableFactory $peerTableFactory = null;

    private ?UserRepositoryInterface $userRepository = null;

    private ?CurrentUser $currentUser = null;

    public function boot(
        TorrentAjaxRepositoryInterface $torrentAjaxRepository,
        PeerTableFactory $peerTableFactory,
        UserRepositoryInterface $userRepository,
        CurrentUser $currentUser,
    ): void {
        $this->torrentAjaxRepository = $torrentAjaxRepository;
        $this->peerTableFactory = $peerTableFactory;
        $this->userRepository = $userRepository;
        $this->currentUser = $currentUser;
    }

    public function mount(int $torrentId, int $seeders = 0, int $leechers = 0, bool $openOnLoad = false): void
    {
        $this->torrentId = $torrentId;
        $this->seeders = $seeders;
        $this->leechers = $leechers;
        $this->open = $openOnLoad;
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
        $data = ['seederTable' => null, 'leecherTable' => null];
        if ($this->open) {
            $curUser = $this->currentUser()->get() ?? [];
            $currentUser = $curUser !== []
                ? $this->userRepository()->findById((int) ($curUser['id'] ?? 0))
                : null;
            $peerData = $this->torrentAjaxRepository()->peerList($this->torrentId, $currentUser);
            $data['seederTable'] = $this->peerTableFactory()->buildTable(
                (string) __('legacy/viewpeerlist.text_seeders'), $peerData['seeders'], $peerData['torrent'],
                $peerData['privacyData'], $peerData['showLocationColumn'], $peerData['enablelocationTweak'],
                $peerData['peerIpInfo'], $peerData['usernameHtmlMap'], $curUser
            );
            $data['leecherTable'] = $this->peerTableFactory()->buildTable(
                (string) __('legacy/viewpeerlist.text_leechers'), $peerData['leechers'], $peerData['torrent'],
                $peerData['privacyData'], $peerData['showLocationColumn'], $peerData['enablelocationTweak'],
                $peerData['peerIpInfo'], $peerData['usernameHtmlMap'], $curUser
            );
        }

        return view('livewire.peer-list', $data);
    }

    private function torrentAjaxRepository(): TorrentAjaxRepositoryInterface
    {
        return $this->torrentAjaxRepository ?? throw new \LogicException('PeerList used before boot()');
    }

    private function peerTableFactory(): PeerTableFactory
    {
        return $this->peerTableFactory ?? throw new \LogicException('PeerList used before boot()');
    }

    private function userRepository(): UserRepositoryInterface
    {
        return $this->userRepository ?? throw new \LogicException('PeerList used before boot()');
    }

    private function currentUser(): CurrentUser
    {
        return $this->currentUser ?? throw new \LogicException('PeerList used before boot()');
    }
}
