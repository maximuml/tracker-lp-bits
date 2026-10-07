<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Contracts\Repositories\TorrentAjaxRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Services\PermissionChecker;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Strings;
use App\ViewModels\Torrent\UserTorrentListVmFactory;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * One expandable "show/hide" torrent-list block on userdetails —
 * replaces getusertorrentlistajax.php: toggle() morphs the block
 * server-side through the same userTorrentList() + VmFactory pipeline.
 */
final class UserTorrentList extends Component
{
    public int $userId = 0;

    public string $type = '';

    public string $label = '';

    public string $title = '';

    public string $linkText = '';

    public bool $open = false;

    private ?PermissionChecker $permissionChecker = null;

    private ?UserRepositoryInterface $userRepository = null;

    private ?CurrentUser $currentUser = null;

    private ?TorrentAjaxRepositoryInterface $torrentAjaxRepository = null;

    private ?UserTorrentListVmFactory $userTorrentListVmFactory = null;

    public function boot(
        PermissionChecker $permissionChecker,
        UserRepositoryInterface $userRepository,
        CurrentUser $currentUser,
        TorrentAjaxRepositoryInterface $torrentAjaxRepository,
        UserTorrentListVmFactory $userTorrentListVmFactory,
    ): void {
        $this->permissionChecker = $permissionChecker;
        $this->userRepository = $userRepository;
        $this->currentUser = $currentUser;
        $this->torrentAjaxRepository = $torrentAjaxRepository;
        $this->userTorrentListVmFactory = $userTorrentListVmFactory;
    }

    public function mount(int $userId, string $type, string $label = '', string $title = '', string $linkText = ''): void
    {
        $this->userId = $userId;
        $this->type = $type;
        $this->label = $label;
        $this->title = $title;
        $this->linkText = $linkText;
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function render(): View
    {
        $data = null;
        if ($this->open) {
            $curUser = $this->currentUser()->get() ?? [];
            $currentUser = $curUser !== []
                ? $this->userRepository()->findById((int) ($curUser['id'] ?? 0))
                : null;
            $allowed = $currentUser !== null
                && ($this->permissionChecker()->userCan(PermissionEnum::TORRENT_HISTORY->value, false, $currentUser->id)
                    || $currentUser->id === $this->userId);

            if ($allowed) {
                $data = $this->torrentAjaxRepository()->userTorrentList($this->userId, $this->type, 0, $currentUser);
                $data['userTorrentListVm'] = ($data['count'] > 0 && ! empty($data['rows']))
                    ? $this->userTorrentListVmFactory()->build($data['rows'], $this->type, $this->userId, $curUser, $data['seedTimeAndUploaded'], $data['torrentRep'])
                    : null;

                $hasData = (bool) ($data['total_size'] || $data['count']);
                $summaryText = (__('getusertorrentlistajax.text_record')).Strings::addS($data['count']);
                if ($data['total_size']) {
                    $summaryText .= (__('getusertorrentlistajax.text_total_size')).Format::size((float) $data['total_size']);
                }
                $data['hasData'] = $hasData;
                $data['summaryCount'] = (int) $data['count'];
                $data['summaryText'] = $summaryText;
            }
        }

        return view('livewire.user-torrent-list', ['data' => $data]);
    }

    private function permissionChecker(): PermissionChecker
    {
        return $this->permissionChecker ?? throw new \LogicException('UserTorrentList used before boot()');
    }

    private function userRepository(): UserRepositoryInterface
    {
        return $this->userRepository ?? throw new \LogicException('UserTorrentList used before boot()');
    }

    private function currentUser(): CurrentUser
    {
        return $this->currentUser ?? throw new \LogicException('UserTorrentList used before boot()');
    }

    private function torrentAjaxRepository(): TorrentAjaxRepositoryInterface
    {
        return $this->torrentAjaxRepository ?? throw new \LogicException('UserTorrentList used before boot()');
    }

    private function userTorrentListVmFactory(): UserTorrentListVmFactory
    {
        return $this->userTorrentListVmFactory ?? throw new \LogicException('UserTorrentList used before boot()');
    }
}
