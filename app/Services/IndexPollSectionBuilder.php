<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Poll;
use App\Repositories\IndexRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Globals;

/**
 * Builds the current-poll section of the index page. Extracted from
 * IndexPageService to keep both classes under the 400-line ratchet.
 */
final class IndexPollSectionBuilder
{
    public function __construct(
        private readonly Globals $globals,
        private readonly IndexRepository $indexRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    public function buildPolls(array $lang, array $curUser, bool $canManage, bool $canLog, LegacyRedisCache $cache): array
    {
        $show = ! empty($curUser) && $this->globals->get('showpolls_main', '') === 'yes';

        if (! $show) {
            return ['show' => false];
        }

        $pollArr = $cache->get_value('current_poll_content');
        if ($pollArr === false || $pollArr === null) {
            $pollArr = $this->indexRepository->getCurrentPoll();
            if ($pollArr) {
                $cache->cache_value('current_poll_content', $pollArr, 7226);
            }
        }

        $pollExists = ! empty($pollArr);

        $result = [
            'show' => true,
            'title' => $lang['text_polls'] ?? 'Polls',
            'canManage' => $canManage,
            'newLabel' => $lang['text_new'] ?? 'New',
            'editLabel' => $lang['text_edit'] ?? 'Edit',
            'deleteLabel' => $lang['text_delete'] ?? 'Delete',
            'detailLabel' => $lang['text_detail'] ?? 'Detail',
            'exists' => $pollExists,
        ];

        if ($pollExists) {
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
            $result['pollId'] = $pollid;
            $result['question'] = $question;
            $result['options'] = $options;
            $result['hasVoted'] = $uservote !== null;
            $result['blankVoteLabel'] = $lang['radio_blank_vote'] ?? 'Blank vote';
            $result['submitVoteLabel'] = $lang['submit_vote'] ?? 'Vote';
            $result['canLog'] = $canLog;
            $result['previousPollsLabel'] = $lang['text_previous_polls'] ?? 'Previous polls';
            $result['votesLabel'] = $lang['text_votes'] ?? 'Votes';

            if ($uservote !== null) {
                $results = $cache->get_value('current_poll_result');
                if ($results === false || $results === null) {
                    $results = $this->indexRepository->getPollResults($pollid);
                    $cache->cache_value('current_poll_result', $results, 3652);
                }
                $tvotes = array_sum(array_column($results, 'count'));
                $bars = [];
                foreach ($results as $item) {
                    $p = $tvotes == 0 ? 0 : (int) round($item['count'] / $tvotes * 100);
                    $bars[] = [
                        'option' => $item['option'],
                        'percent' => $p,
                        'width' => $p * 3,
                        'selected' => $item['index'] == $uservote,
                    ];
                }
                $result['bars'] = $bars;
                $result['totalVotes'] = number_format($tvotes);
            }
        }

        return $result;
    }
}
