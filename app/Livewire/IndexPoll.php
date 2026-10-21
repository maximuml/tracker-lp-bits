<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Auth\Permission;
use App\DTOs\Auth\ActorContext;
use App\Enums\Permission\PermissionEnum;
use App\Services\PollVoteService;
use App\ViewModels\Index\IndexPollsSectionFactory;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Index-page poll card — the vote form POSTs via wire:submit and the
 * result bars morph in without a full-page redirect, through the same
 * PollVoteService the legacy POST /index handler uses.
 */
final class IndexPoll extends Component
{
    public int $choice = -1;

    private ?IndexPollsSectionFactory $indexPollsSectionFactory = null;

    private ?PollVoteService $pollVoteService = null;

    private ?ActorContext $actor = null;

    public function boot(
        IndexPollsSectionFactory $indexPollsSectionFactory,
        PollVoteService $pollVoteService,
        ActorContext $actor,
    ): void {
        $this->indexPollsSectionFactory = $indexPollsSectionFactory;
        $this->pollVoteService = $pollVoteService;
        $this->actor = $actor;
    }

    public function vote(): void
    {
        if ($this->actor()->isGuest()) {
            return;
        }

        $this->pollVoteService()->vote($this->actor()->toLegacyArray(), $this->choice);
    }

    public function render(): View
    {
        $curUser = $this->actor()->toLegacyArray();
        $polls = $this->indexPollsSectionFactory()->build(
            $curUser,
            Permission::can(PermissionEnum::POLL_MANAGE),
            Permission::can(PermissionEnum::LOG),
        );

        return view('livewire.index-poll', ['polls' => $polls]);
    }

    private function indexPollsSectionFactory(): IndexPollsSectionFactory
    {
        return $this->indexPollsSectionFactory ?? throw new \LogicException('IndexPoll used before boot()');
    }

    private function pollVoteService(): PollVoteService
    {
        return $this->pollVoteService ?? throw new \LogicException('IndexPoll used before boot()');
    }

    private function actor(): ActorContext
    {
        return $this->actor ?? throw new \LogicException('IndexPoll used before boot()');
    }
}
