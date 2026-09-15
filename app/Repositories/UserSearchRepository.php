<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Administrative user search query builder, migrated from usersearch_content.php.
 */
final class UserSearchRepository
{
    public function __construct(
        private readonly UserSearchFilters $filters = new UserSearchFilters
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{count: int, rows: array<int, array<string, mixed>>, q: string}
     */
    public function administrativeSearch(array $params, bool $hasModcomment, int $perPage = 30): array
    {
        $userQuery = DB::table('users as u');
        $q = '';

        if (count($params) > 0 && empty($params['h'])) {
            $q = $this->filters->apply($userQuery, $params, $hasModcomment);
        }

        $select_is = 'u.id, u.username, u.email, u.status, u.added, u.last_access, u.ip,
	u.class, u.uploaded, u.downloaded, u.donor, u.enabled, u.warned';
        if ($hasModcomment) {
            $select_is = str_replace('u.donor, u.enabled', 'u.donor, u.modcomment, u.enabled', $select_is);
        }

        $count = (int) (clone $userQuery)->selectRaw('count(distinct u.id) as count')->value('count');

        $page = (int) ($params['page'] ?? 0);
        if ($page < 0) {
            $page = 0;
        }
        $offset = $page * $perPage;

        $rows = (clone $userQuery)->distinct()->selectRaw($select_is)->offset($offset)->limit($perPage)->get()->map(fn ($row) => (array) $row)->all(); // @phpstan-ignore argument.type

        $q = $q !== '' ? $q.'&' : '';

        return [
            'count' => $count,
            'rows' => $rows,
            'q' => $q,
        ];
    }
}
