<?php

declare(strict_types=1);

namespace App\ViewModels\Index;

/**
 * Current poll section on the index page — vote form or result bars.
 */
final readonly class IndexPollsSection
{
    /**
     * @param  array<int, string>  $options
     * @param  list<IndexPollBar>  $bars
     */
    public function __construct(
        public bool $show = false,
        public string $title = '',
        public bool $canManage = false,
        public string $newLabel = '',
        public string $editLabel = '',
        public string $deleteLabel = '',
        public string $detailLabel = '',
        public bool $exists = false,
        public int $pollId = 0,
        public string $question = '',
        public array $options = [],
        public bool $hasVoted = false,
        public string $blankVoteLabel = '',
        public string $submitVoteLabel = '',
        public bool $canLog = false,
        public string $previousPollsLabel = '',
        public string $votesLabel = '',
        public array $bars = [],
        public string $totalVotes = '',
    ) {}
}
