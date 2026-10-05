<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Contracts\Repositories\ShoutboxRepositoryInterface;
use App\DTOs\Auth\ActorContext;
use App\Enums\Permission\PermissionEnum;
use App\Services\ShoutboxService;
use App\Support\Html\SafeHtml;
use App\Support\Shoutbox as ShoutboxSupport;
use App\Support\UserDisplay;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Index-page shoutbox panel: polling refresh + compose form.
 * Edit/delete/react stay on the delegated ajax.php handlers in
 * public/js/shoutbox.js; they dispatch 'shout-refresh' afterwards.
 */
final class Shoutbox extends Component
{
    public string $text = '';

    public string $status = '';

    public int $refresh = 120;

    public int $limit = 70;

    private ?ShoutboxRepositoryInterface $repository = null;

    private ?ShoutboxService $shoutboxService = null;

    private ?ActorContext $actor = null;

    public function boot(ShoutboxRepositoryInterface $repository, ShoutboxService $shoutboxService, ActorContext $actor): void
    {
        $this->repository = $repository;
        $this->shoutboxService = $shoutboxService;
        $this->actor = $actor;
    }

    private function repository(): ShoutboxRepositoryInterface
    {
        return $this->repository ?? throw new \LogicException('Shoutbox component used before boot()');
    }

    private function actor(): ActorContext
    {
        return $this->actor ?? throw new \LogicException('Shoutbox component used before boot()');
    }

    public function mount(): void
    {
        $currentUser = $this->actor()->toLegacyArray();
        $refresh = (int) ($currentUser['sbrefresh'] ?? 120);
        $this->refresh = $refresh > 0 ? $refresh : 120;
        $limit = (int) ($currentUser['sbnum'] ?? 70);
        $this->limit = $limit > 0 ? $limit : 70;
    }

    public function send(): void
    {
        if ($this->actor()->id <= 0) {
            return;
        }
        $text = trim($this->text);
        if ($text === '') {
            return;
        }
        $this->status = '';
        if (mb_strlen($text) > ShoutboxSupport::MAX_MESSAGE_LENGTH) {
            $this->status = __('legacy/shoutbox.js_request_failed');

            return;
        }
        if (! ($this->shoutboxService ?? throw new \LogicException('Shoutbox component used before boot()'))->postMessage($this->actor(), $text)) {
            $this->status = __('legacy/shoutbox.js_request_failed');

            return;
        }
        $this->reset('text');
    }

    #[On('shout-refresh')]
    public function onShoutRefresh(): void
    {
        // Re-render is implicit; new rows are fetched in render().
    }

    public function render(): View
    {
        $currentUser = $this->actor()->toLegacyArray();
        $currentUserId = $this->actor()->id;

        $rows = $this->repository()->listLatest('shoutbox', $currentUser !== [] ? $currentUser : null, $this->limit);
        $shoutIds = array_values($rows->pluck('id')->map(fn ($id) => (int) $id)->all());
        $reactionData = ShoutboxSupport::prefetchReactions($shoutIds, $currentUserId);

        $userIds = array_filter(array_unique($rows->pluck('userid')->map(fn ($id) => (int) $id)->all()));
        UserDisplay::preload(array_values($userIds));

        $isStaff = $this->actor()->can(PermissionEnum::SB_MANAGE);
        $items = ShoutboxSupport::decorateRows($rows, $currentUser, $currentUserId, $isStaff, $reactionData);

        return view('livewire.shoutbox', [
            'items' => $items,
            'toolbar' => SafeHtml::fromTrustedHtml(ShoutboxSupport::toolbar('shbox', 'shbox_text')),
        ]);
    }
}
