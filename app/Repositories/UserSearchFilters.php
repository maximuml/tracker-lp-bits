<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * Ordered dispatcher for the administrative user search where-clauses.
 * Each collaborator returns the pager query-string fragments it produced.
 */
final class UserSearchFilters
{
    public function __construct(
        private readonly UserSearchTextFilters $text = new UserSearchTextFilters,
        private readonly UserSearchRangeFilters $range = new UserSearchRangeFilters,
        private readonly UserSearchAttributeFilters $attribute = new UserSearchAttributeFilters,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function apply(Builder $userQuery, array $params, bool $hasModcomment): string
    {
        return implode('&', array_merge(
            $this->text->applyName($userQuery, $params),
            $this->text->applyEmail($userQuery, $params),
            $this->attribute->applyClass($userQuery, $params),
            $this->attribute->applyIp($userQuery, $params),
            $this->range->applyRatio($userQuery, $params),
            $this->text->applyComment($userQuery, $params, $hasModcomment),
            $this->range->applyUploaded($userQuery, $params),
            $this->range->applyDownloaded($userQuery, $params),
            $this->range->applyJoined($userQuery, $params),
            $this->range->applyLastAccess($userQuery, $params),
            $this->attribute->applyStatus($userQuery, $params),
            $this->attribute->applyAccountStatus($userQuery, $params),
            $this->attribute->applyDonor($userQuery, $params),
            $this->attribute->applyWarned($userQuery, $params),
            $this->attribute->applyDisabledIp($userQuery, $params),
            $this->attribute->applyActive($userQuery, $params),
        ));
    }
}
