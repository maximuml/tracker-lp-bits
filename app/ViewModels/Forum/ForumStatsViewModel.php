<?php

declare(strict_types=1);

namespace App\ViewModels\Forum;

use App\Support\Strings;

/**
 * Aggregate counters for the "Stats" block at the bottom of the forums
 * index (ADR 0021). Plural/copula fragments stay in PHP because the
 * legacy lang strings split the sentence mid-grammar.
 */
final class ForumStatsViewModel
{
    public function __construct(
        public readonly int $posts,
        public readonly int $topics,
        public readonly int $todayPosts,
        public readonly int $activeUsers,
    ) {}

    public function todayPostsPlural(): string
    {
        return Strings::addS($this->todayPosts);
    }

    public function activeUsersPlural(): string
    {
        return Strings::addS($this->activeUsers);
    }

    public function activeUsersIsAre(): string
    {
        return Strings::isOrAre($this->activeUsers);
    }
}
