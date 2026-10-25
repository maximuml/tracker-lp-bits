<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Contracts\Repositories\ShoutboxRepositoryInterface;
use App\DTOs\Auth\ActorContext;
use App\Enums\Permission\PermissionEnum;
use App\Livewire\Concerns\PersistsKlappe;
use App\Services\ShoutboxService;
use App\Support\Shoutbox as ShoutboxSupport;
use App\Support\UserDisplay;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Index-page shoutbox card: collapse toggle + polling refresh + compose
 * form + edit/delete/react/clear — all server-side via Livewire.
 * public/js/shoutbox-index.js keeps only DOM-native concerns the server
 * cannot own: toolbar cursor insertion, the collapsed-mentions badge,
 * long-message clamp toggling and avatar fallbacks. The full
 * public/js/shoutbox.js (polling + SSE + ajax actions) is loaded solely
 * by the standalone /web/shoutbox document.
 */
final class Shoutbox extends Component
{
    use PersistsKlappe;

    public string $text = '';

    public string $status = '';

    public string $editText = '';

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $openPickerId = null;

    #[Locked]
    public ?int $confirmingDeleteId = null;

    #[Locked]
    public bool $confirmingClear = false;

    #[Locked]
    public int $refresh = 120;

    #[Locked]
    public int $limit = 70;

    #[Locked]
    public string $cardTitle = '';

    #[Locked]
    public string $autoRefreshLabel = '';

    #[Locked]
    public string $refreshSeconds = '';

    #[Locked]
    public string $secondsLabel = '';

    #[Locked]
    public string $historyLabel = '';

    #[Locked]
    public bool $canManage = false;

    #[Locked]
    public string $clearConfirm = '';

    #[Locked]
    public string $clearLabel = '';

    #[Locked]
    public string $showHideTitle = '';

    public bool $open = true;

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

    public function mount(
        string $cardTitle,
        string $autoRefreshLabel,
        string $refreshSeconds,
        string $secondsLabel,
        string $historyLabel,
        bool $canManage,
        string $clearConfirm,
        string $clearLabel,
        string $showHideTitle,
    ): void {
        $currentUser = $this->actor()->toLegacyArray();
        $refresh = (int) ($currentUser['sbrefresh'] ?? 120);
        $this->refresh = $refresh > 0 ? $refresh : 120;
        $limit = (int) ($currentUser['sbnum'] ?? 70);
        $this->limit = $limit > 0 ? $limit : 70;
        $this->cardTitle = $cardTitle;
        $this->autoRefreshLabel = $autoRefreshLabel;
        $this->refreshSeconds = $refreshSeconds;
        $this->secondsLabel = $secondsLabel;
        $this->historyLabel = $historyLabel;
        $this->canManage = $canManage;
        $this->clearConfirm = $clearConfirm;
        $this->clearLabel = $clearLabel;
        $this->showHideTitle = $showHideTitle;
        $this->open = $this->klappeInitial('shoutbox', true);
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
        $this->klappePersist('shoutbox', $this->open);
    }

    #[On('shoutbox-expand')]
    public function expand(): void
    {
        $this->open = true;
        $this->klappePersist('shoutbox', true);
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
            $this->status = __('shoutbox.js_request_failed');

            return;
        }
        if (! ($this->shoutboxService ?? throw new \LogicException('Shoutbox component used before boot()'))->postMessage($this->actor(), $text)) {
            $this->status = __('shoutbox.js_request_failed');

            return;
        }
        $this->reset('text');
    }

    public function startEdit(int $id): void
    {
        $msg = $id > 0 ? $this->repository()->findById($id) : null;
        if (! $msg) {
            return;
        }
        $this->editingId = $id;
        $this->editText = (string) ($msg->text ?? '');
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editText = '';
    }

    public function saveEdit(): void
    {
        $id = $this->editingId;
        if ($id === null) {
            return;
        }
        $text = trim($this->editText);
        if ($text === '') {
            $this->status = __('shoutbox.js_request_failed');

            return;
        }
        if (! ($this->shoutboxService ?? throw new \LogicException('Shoutbox component used before boot()'))->editMessage($this->actor(), $id, $text)) {
            $this->status = __('shoutbox.js_request_failed');

            return;
        }
        $this->cancelEdit();
        $this->status = '';
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingDeleteId = $id > 0 ? $id : null;
        $this->confirmingClear = false;
    }

    public function confirmClear(): void
    {
        $this->confirmingClear = true;
        $this->confirmingDeleteId = null;
    }

    public function cancelConfirm(): void
    {
        $this->confirmingDeleteId = null;
        $this->confirmingClear = false;
    }

    public function delete(int $id): void
    {
        $this->confirmingDeleteId = null;
        if ($id <= 0 || ! ($this->shoutboxService ?? throw new \LogicException('Shoutbox component used before boot()'))->deleteMessage($this->actor(), $id)) {
            $this->status = __('shoutbox.js_request_failed');

            return;
        }
        if ($this->editingId === $id) {
            $this->cancelEdit();
        }
        $this->status = '';
    }

    public function react(int $id, string $emoji): void
    {
        if ($id <= 0) {
            return;
        }
        ($this->shoutboxService ?? throw new \LogicException('Shoutbox component used before boot()'))->toggleReaction($this->actor(), $id, $emoji);
        if ($this->openPickerId === $id) {
            $this->openPickerId = null;
        }
    }

    public function togglePicker(int $id): void
    {
        $this->openPickerId = $this->openPickerId === $id ? null : $id;
    }

    public function clear(): void
    {
        $this->confirmingClear = false;
        if (! ($this->shoutboxService ?? throw new \LogicException('Shoutbox component used before boot()'))->clearAll($this->actor())) {
            $this->status = __('shoutbox.js_request_failed');

            return;
        }
        $this->cancelEdit();
        $this->openPickerId = null;
        $this->status = '';
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
            'toolbar' => ShoutboxSupport::toolbar('shbox', 'shbox_text'),
        ]);
    }
}
