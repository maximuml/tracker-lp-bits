<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Poll;
use App\Repositories\IndexRepository;
use App\Support\Bonus;
use App\Support\Cache\NexusCache;
use App\Support\Config\SiteConfig;

/**
 * Records an index-poll vote — shared by the legacy POST /index
 * handler and the Livewire IndexPoll component.
 */
final class PollVoteService
{
    public function __construct(
        private readonly IndexRepository $indexRepository,
        private readonly ?NexusCache $cache = null,
    ) {}

    /**
     * @param  array<string, mixed>  $user
     */
    public function vote(array $user, int $choice): bool
    {
        // Allow 0-19 for normal options, 255 for blank vote.
        if ($choice < 0 || ($choice > Poll::MAX_OPTION_INDEX && $choice !== 255)) {
            return false;
        }

        $poll = $this->indexRepository->getCurrentPoll();
        if (! is_array($poll) || ! isset($poll['id']) || $user === []) {
            return false;
        }

        $pollId = $poll['id'];

        $optionKey = "option{$choice}";
        if ($choice !== 255 && empty($poll[$optionKey])) {
            return false;
        }

        if ($this->indexRepository->hasVoted($pollId, $user['id'])) {
            return false;
        }

        $this->indexRepository->recordPollVote($pollId, $user['id'], $choice);

        $cache = $this->cache;
        if ($cache !== null) {
            $cache->forget('current_poll_content');
            $cache->forget('current_poll_result', true);
        }

        $pollvoteBonus = SiteConfig::current()->bonus->pollVote();
        if ($pollvoteBonus > 0) {
            Bonus::updatePoints((string) '+', (float) $pollvoteBonus, $user['id']);
        }

        return true;
    }
}
