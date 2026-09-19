<?php

declare(strict_types=1);

namespace App\ViewModels\Usersearch;

use App\Support\Html\SafeHtml;

/**
 * The user-search results block: either the rows table with pagers, or
 * the "No user was found" message.
 */
final class UsersearchResultsViewModel
{
    /**
     * @param  list<UsersearchRow>  $rows
     */
    public function __construct(
        public readonly array $rows,
        public readonly bool $showPager,
        public readonly SafeHtml $pagerTop,
        public readonly SafeHtml $pagerBottom,
        public readonly ?SafeHtml $emptyMessage,
    ) {}
}
