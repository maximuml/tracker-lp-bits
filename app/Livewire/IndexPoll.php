<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Services\PollVoteService;
use App\Support\CurrentUser;
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

    private ?CurrentUser $currentUser = null;

    public function boot(
        IndexPollsSectionFactory $indexPollsSectionFactory,
        PollVoteService $pollVoteService,
        CurrentUser $currentUser,
    ): void {
        $this->indexPollsSectionFactory = $indexPollsSectionFactory;
        $this->pollVoteService = $pollVoteService;
        $this->currentUser = $currentUser;
    }

    public function vote(): void
    {
        $user = $this->currentUser()->get();
        if (! is_array($user) || $user === []) {
            return;
        }

        $this->pollVoteService()->vote($user, $this->choice);
    }

    public function render(): View
    {
        $curUser = (array) ($this->currentUser()->get() ?? []);
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

    private function currentUser(): CurrentUser
    {
        return $this->currentUser ?? throw new \LogicException('IndexPoll used before boot()');
    }
}
