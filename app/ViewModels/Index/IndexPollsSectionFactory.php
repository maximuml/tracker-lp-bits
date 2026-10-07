<?php

declare(strict_types=1);

namespace App\ViewModels\Index;

use App\Models\Poll;
use App\Repositories\IndexRepository;
use App\Support\Cache\NexusCache;
use App\Support\Config\SiteConfig;

/**
 * Builds the index-page poll section — extracted from IndexPageService
 * so the Livewire IndexPoll can re-render the same data after a vote.
 */
final class IndexPollsSectionFactory
{
    public function __construct(
        private readonly NexusCache $cache,
        private readonly IndexRepository $indexRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $curUser
     */
    public function build(array $curUser, bool $canManage, bool $canLog): IndexPollsSection
    {
        $show = ! empty($curUser) && SiteConfig::current()->main->showPolls();

        if (! $show) {
            return new IndexPollsSection;
        }

        $pollArr = $this->cache->get('current_poll_content');
        if ($pollArr === false || $pollArr === null) {
            $pollArr = $this->indexRepository->getCurrentPoll();
            if ($pollArr) {
                $this->cache->put('current_poll_content', $pollArr, 7226);
            }
        }

        $pollExists = ! empty($pollArr);

        $section = new IndexPollsSection(
            show: true,
            title: __('index.text_polls'),
            canManage: $canManage,
            newLabel: __('index.text_new'),
            editLabel: __('index.text_edit'),
            deleteLabel: __('index.text_delete'),
            detailLabel: __('index.text_detail'),
            exists: $pollExists,
        );

        if (! $pollExists) {
            return $section;
        }

        $pollid = (int) ($pollArr['id'] ?? 0);
        $question = (string) ($pollArr['question'] ?? '');
        $options = [];
        for ($i = 0; $i <= Poll::MAX_OPTION_INDEX; $i++) {
            $opt = (string) ($pollArr["option{$i}"] ?? '');
            if ($opt !== '') {
                $options[$i] = $opt;
            }
        }

        $uservote = $this->indexRepository->getUserVote($pollid, (int) ($curUser['id'] ?? 0));

        $bars = [];
        $totalVotes = '';
        if ($uservote !== null) {
            $results = $this->cache->get('current_poll_result');
            if ($results === false || $results === null) {
                $results = $this->indexRepository->getPollResults($pollid);
                $this->cache->put('current_poll_result', $results, 3652);
            }
            $tvotes = array_sum(array_column($results, 'count'));
            foreach ($results as $item) {
                $p = $tvotes == 0 ? 0 : (int) round($item['count'] / $tvotes * 100);
                $bars[] = new IndexPollBar(
                    option: (string) $item['option'],
                    percent: $p,
                    selected: $item['index'] == $uservote,
                );
            }
            $totalVotes = number_format($tvotes);
        }

        return new IndexPollsSection(
            show: true,
            title: $section->title,
            canManage: $canManage,
            newLabel: $section->newLabel,
            editLabel: $section->editLabel,
            deleteLabel: $section->deleteLabel,
            detailLabel: $section->detailLabel,
            exists: true,
            pollId: $pollid,
            question: $question,
            options: $options,
            hasVoted: $uservote !== null,
            blankVoteLabel: __('index.radio_blank_vote'),
            submitVoteLabel: __('index.submit_vote'),
            canLog: $canLog,
            previousPollsLabel: __('index.text_previous_polls'),
            votesLabel: __('index.text_votes'),
            bars: $bars,
            totalVotes: $totalVotes,
        );
    }
}
