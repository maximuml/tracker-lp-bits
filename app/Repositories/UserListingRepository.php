<?php

namespace App\Repositories;

use Nexus\Database\NexusDB;

class UserListingRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getCountries(): array
    {
        return NexusDB::table('countries')
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public static function countUsers(array $filters): int
    {
        return self::buildUserQuery($filters)->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listUsers(array $filters, int $offset, int $perPage): array
    {
        $query = self::buildUserQuery($filters)
            ->leftJoin('countries as c', 'u.country', '=', 'c.id')
            ->select('u.id', 'u.class', 'u.added', 'u.last_access')
            ->selectRaw("CASE WHEN u.country > 0 THEN CONCAT('<img src=\"pic/flag/', c.flagpic, '\" alt=\"', c.name, '\">') ELSE '---' END as country")
            ->orderBy('u.username')
            ->offset($offset)
            ->limit($perPage);

        return $query->get()->map(fn ($row) => (array) $row)->all();
    }

    private static function buildUserQuery(array $filters): \Illuminate\Database\Query\Builder
    {
        $search = trim($filters['search'] ?? '');
        $class = $filters['class'] ?? '-';
        $country = (int) ($filters['country'] ?? 0);
        $letter = trim($filters['letter'] ?? '');

        $query = NexusDB::table('users as u')->where('u.status', 'confirmed');

        if ($search !== '') {
            $query->where('u.username', 'like', "%{$search}%");
        } elseif ($letter !== '') {
            $query->where('u.username', 'like', "{$letter}%");
        }

        if ($class !== '-') {
            $query->where('u.class', $class);
        }

        if ($country > 0) {
            $query->where('u.country', $country);
        }

        return $query;
    }
}
