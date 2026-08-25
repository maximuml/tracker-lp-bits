<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Torrent;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ShoutboxRepository extends BaseRepository
{
    private const DEFAULT_PER_PAGE = 50;

    /**
     * @return array<string, mixed>
     */
    public function history(Request $request): array
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) ($this->getPerPageFromRequest($request) ?: self::DEFAULT_PER_PAGE);
        $perPage = max(1, min($perPage, 100));
        $offset = ($page - 1) * $perPage;

        $filters = [
            'user' => trim((string) $request->input('user', '')),
            'from' => trim((string) $request->input('from', '')),
            'to' => trim((string) $request->input('to', '')),
            'search' => trim((string) $request->input('search', '')),
        ];

        $query = DB::table('shoutbox')
            ->where('type', 'sb')
            ->orderByDesc('date');

        $countQuery = DB::table('shoutbox')->where('type', 'sb');

        if ($filters['user'] !== '') {
            $userId = User::query()->whereRaw('LOWER(username) = LOWER(?)', [$filters['user']])->value('id');
            if ($userId) {
                $query->where('userid', (int) $userId);
                $countQuery->where('userid', (int) $userId);
            } else {
                $query->where('userid', -1);
                $countQuery->where('userid', -1);
            }
        }

        if ($filters['from'] !== '') {
            $fromTs = strtotime($filters['from']);
            if ($fromTs !== false) {
                $query->where('date', '>=', $fromTs);
                $countQuery->where('date', '>=', $fromTs);
            }
        }

        if ($filters['to'] !== '') {
            $toTs = strtotime($filters['to']);
            if ($toTs !== false) {
                $query->where('date', '<=', $toTs + 86399);
                $countQuery->where('date', '<=', $toTs + 86399);
            }
        }

        if ($filters['search'] !== '') {
            $like = '%'.$filters['search'].'%';
            $query->where('text', 'like', $like);
            $countQuery->where('text', 'like', $like);
        }

        $rows = $query->offset($offset)->limit($perPage)->get()->map(fn ($r) => (array) $r)->all();
        $total = (int) $countQuery->count();

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'filters' => array_filter($filters, fn ($v) => $v !== ''),
        ];
    }

    /**
     * @param  list<int>  $shoutIds
     * @return array{counts: array<int, array<string, int>>, mine: array<int, list<string>>, users: array<int, array<string, list<string>>>}
     */
    public static function prefetchReactions(array $shoutIds, int $currentUserId): array
    {
        if ($shoutIds === []) {
            return ['counts' => [], 'mine' => [], 'users' => []];
        }

        $ids = array_map('intval', $shoutIds);
        $reactions = ['👍', '🔥', '❤️', '😂', '😮', '😢'];

        /** @var array<int, array<string, int>> $counts */
        $counts = [];
        $rawCounts = DB::table('shoutbox_reactions')
            ->select('shoutbox_id', 'reaction', DB::raw('COUNT(*) as cnt'))
            ->whereIn('shoutbox_id', $ids)
            ->groupBy('shoutbox_id', 'reaction')
            ->get();
        foreach ($rawCounts as $row) {
            $id = (int) $row->shoutbox_id;
            $emoji = (string) $row->reaction;
            $counts[$id][$emoji] = (int) $row->cnt;
        }

        /** @var array<int, list<string>> $mine */
        $mine = [];
        $rawMine = DB::table('shoutbox_reactions')
            ->whereIn('shoutbox_id', $ids)
            ->where('user_id', $currentUserId)
            ->get(['shoutbox_id', 'reaction']);
        foreach ($rawMine as $row) {
            $id = (int) $row->shoutbox_id;
            $mine[$id][] = (string) $row->reaction;
        }

        /** @var array<int, array<string, list<string>>> $users */
        $users = [];
        $rawUsers = DB::table('shoutbox_reactions as sr')
            ->select('sr.shoutbox_id', 'sr.reaction', 'u.username')
            ->join('users as u', 'u.id', '=', 'sr.user_id')
            ->whereIn('sr.shoutbox_id', $ids)
            ->whereIn('sr.reaction', $reactions)
            ->orderBy('sr.id')
            ->limit(100 * count($ids))
            ->get();
        foreach ($rawUsers as $row) {
            $id = (int) $row->shoutbox_id;
            $emoji = (string) $row->reaction;
            $name = (string) $row->username;
            if (! isset($users[$id][$emoji])) {
                $users[$id][$emoji] = [];
            }
            if (count($users[$id][$emoji]) < 20) {
                $users[$id][$emoji][] = $name;
            }
        }

        return ['counts' => $counts, 'mine' => $mine, 'users' => $users];
    }

    /**
     * @return array<string, int>
     */
    public static function getReactionCounts(int $shoutId): array
    {
        return DB::table('shoutbox_reactions')
            ->select('reaction', DB::raw('COUNT(*) as cnt'))
            ->where('shoutbox_id', $shoutId)
            ->groupBy('reaction')
            ->pluck('cnt', 'reaction')
            ->toArray();
    }

    /**
     * @return list<string>
     */
    public static function getMyReactions(int $shoutId, int $currentUserId): array
    {
        $values = DB::table('shoutbox_reactions')
            ->where('shoutbox_id', $shoutId)
            ->where('user_id', $currentUserId)
            ->pluck('reaction')
            ->toArray();

        return array_values(array_map('strval', $values));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getMentions(int $userId, int $lastShoutId): array
    {
        $user = User::query()->find($userId, ['username']);
        $username = $user?->username;
        if ($username === null || $username === '') {
            return [];
        }

        $like = '%@'.strtolower($username).'%';
        $pattern = '/(?<![\w\-\[\]\(\)])@'.preg_quote($username, '/').'(?![\w\-\[\]\(\)])/ui';

        $query = DB::table('shoutbox')
            ->leftJoin('users', 'shoutbox.userid', '=', 'users.id')
            ->where('shoutbox.id', '>', $lastShoutId)
            ->where('shoutbox.userid', '!=', $userId)
            ->whereRaw('LOWER(shoutbox.text) LIKE ?', [$like])
            ->select('shoutbox.id', 'shoutbox.date', 'shoutbox.text', 'users.username as author_name')
            ->orderBy('shoutbox.id')
            ->limit(50);

        self::applyTypeFilter($query, 'shoutbox', null);
        $rows = $query->get();

        $result = [];
        foreach ($rows as $row) {
            $text = (string) ($row->text ?? '');
            if (! preg_match($pattern, $text)) {
                continue;
            }
            $result[] = [
                'id' => (int) $row->id,
                'date' => (int) $row->date,
                'text' => $text,
                'author_name' => (string) ($row->author_name ?? 'System'),
            ];
        }

        return $result;
    }

    public static function getLastShoutId(): int
    {
        return (int) (DB::table('shoutbox')->max('id') ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findUserByUsername(string $username): ?array
    {
        $row = User::query()->whereRaw('LOWER(username) = LOWER(?)', [$username])->first(['id', 'username']);

        return $row ? ['id' => (int) $row->id, 'name' => (string) $row->username] : null;
    }

    public static function torrentExists(int $id): bool
    {
        return Torrent::query()->where('id', $id)->exists();
    }

    /**
     * @param  Builder  $query
     * @param  array<string, mixed>|object|null  $user
     */
    public static function applyTypeFilter($query, string $type, $user = null): void
    {
        $query->where('type', 'sb');
    }
}
