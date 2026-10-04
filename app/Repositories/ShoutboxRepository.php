<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ShoutboxRepositoryInterface;
use App\Enums\ShoutboxType;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ShoutboxRepository extends BaseRepository implements ShoutboxRepositoryInterface
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
            ->where('type', ShoutboxType::SB->value)
            ->orderByDesc('date');

        $countQuery = DB::table('shoutbox')->where('type', ShoutboxType::SB->value);

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
    public function prefetchReactions(array $shoutIds, int $currentUserId): array
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
    public function getReactionCounts(int $shoutId): array
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
    public function getMyReactions(int $shoutId, int $currentUserId): array
    {
        $values = DB::table('shoutbox_reactions')
            ->where('shoutbox_id', $shoutId)
            ->where('user_id', $currentUserId)
            ->pluck('reaction')
            ->toArray();

        return array_values(array_map('strval', $values));
    }

    /**
     * Mentions of the user newer than the cursor. The SQL LIKE is only a
     * prefilter — the real "is a mention" check is the PHP regex, so the
     * candidate scan is keyset-paginated rather than a single LIMIT'd
     * query: a page full of non-mention LIKE hits must not hide real
     * mentions behind it (backlog correctness, REL-01).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMentions(int $userId, int $lastShoutId, int $limit = 50, bool $oldestFirst = true): array
    {
        $result = [];
        foreach ($this->mentionRows($userId, $lastShoutId, $oldestFirst) as $row) {
            $result[] = $row;
            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    /**
     * Exact unread-mention count — runs the same LIKE prefilter + PHP
     * regex as getMentions so the badge count and the item list cannot
     * diverge.
     */
    public function countMentions(int $userId, int $lastShoutId): int
    {
        $count = 0;
        foreach ($this->mentionRows($userId, $lastShoutId, true) as $row) {
            $count++;
        }

        return $count;
    }

    /**
     * Lazily yields validated mention rows (id, date, text, author_name)
     * in id order — ascending for delivery cursors, descending for the
     * newest-first panel view.
     *
     * @return \Generator<int, array{id: int, date: int, text: string, author_name: string}>
     */
    private function mentionRows(int $userId, int $lastShoutId, bool $oldestFirst): \Generator
    {
        $user = User::query()->find($userId, ['username']);
        $username = $user?->username;
        if ($username === null || $username === '') {
            return;
        }

        $like = '%@'.strtolower($username).'%';
        $pattern = '/(?<![\w\-\[\]\(\)])@'.preg_quote($username, '/').'(?![\w\-\[\]\(\)])/ui';
        // Desc iteration walks down from the top of the range — seeding the
        // boundary with $lastShoutId would yield the empty set (id > X AND
        // id < X) whenever the cursor is non-zero.
        $boundary = $oldestFirst ? $lastShoutId : PHP_INT_MAX;

        while (true) {
            $query = DB::table('shoutbox')
                ->leftJoin('users', 'shoutbox.userid', '=', 'users.id')
                ->where('shoutbox.id', '>', $lastShoutId)
                ->where('shoutbox.userid', '!=', $userId)
                ->whereRaw('LOWER(shoutbox.text) LIKE ?', [$like])
                ->select('shoutbox.id', 'shoutbox.date', 'shoutbox.text', 'users.username as author_name')
                ->orderBy('shoutbox.id', $oldestFirst ? 'asc' : 'desc')
                ->limit(200);
            if ($oldestFirst) {
                $query->where('shoutbox.id', '>', $boundary);
            } else {
                $query->where('shoutbox.id', '<', $boundary);
            }

            $this->applyTypeFilter($query, 'shoutbox', null);
            $rows = $query->get();
            if ($rows->isEmpty()) {
                return;
            }

            foreach ($rows as $row) {
                $boundary = (int) $row->id;
                $text = (string) ($row->text ?? '');
                if (! preg_match($pattern, $text)) {
                    continue;
                }
                yield [
                    'id' => (int) $row->id,
                    'date' => (int) $row->date,
                    'text' => $text,
                    'author_name' => (string) ($row->author_name ?? 'System'),
                ];
            }

            if ($rows->count() < 200) {
                return;
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findUserByUsername(string $username): ?array
    {
        $row = User::query()->whereRaw('LOWER(username) = LOWER(?)', [$username])->first(['id', 'username']);

        return $row ? ['id' => (int) $row->id, 'name' => (string) $row->username] : null;
    }

    public function torrentExists(int $id): bool
    {
        return Torrent::query()->where('id', $id)->exists();
    }

    /**
     * @param  Builder  $query
     * @param  array<string, mixed>|object|null  $user
     */
    public function applyTypeFilter($query, string $type, $user = null): void
    {
        $query->where('type', ShoutboxType::SB->value);
    }

    /**
     * @param  array<string, mixed>|null  $user
     */
    public function maxId(string $type, ?array $user): int
    {
        $query = DB::table('shoutbox');
        $this->applyTypeFilter($query, $type, $user);

        return (int) $query->max('id');
    }

    /**
     * @param  array<string, mixed>|null  $user
     * @return Collection<int, \stdClass>
     */
    public function listLatest(string $type, ?array $user, int $limit): Collection
    {
        $query = DB::table('shoutbox')->orderByDesc('date')->limit($limit);
        $this->applyTypeFilter($query, $type, $user);

        return $query->get();
    }

    /**
     * Prepared query for shouts after $lastId — re-executed by the SSE loop.
     *
     * @param  array<string, mixed>|null  $user
     */
    public function newAfterIdQuery(string $type, int $lastId, ?array $user): Builder
    {
        $query = DB::table('shoutbox')
            ->orderBy('id')
            ->where('id', '>', $lastId);
        $this->applyTypeFilter($query, $type, $user);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function insertMessage(array $data): void
    {
        DB::table('shoutbox')->insert($data);
    }

    public function findById(int $id): ?\stdClass
    {
        /** @var \stdClass|null $row */
        $row = DB::table('shoutbox')->where('id', $id)->first();

        return $row;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateById(int $id, array $data): void
    {
        DB::table('shoutbox')->where('id', $id)->update($data);
    }

    public function deleteWithReactions(int $id): void
    {
        DB::table('shoutbox')->where('id', $id)->delete();
        DB::table('shoutbox_reactions')->where('shoutbox_id', $id)->delete();
    }

    public function deleteAllWithReactions(): void
    {
        DB::table('shoutbox')->delete();
        DB::table('shoutbox_reactions')->delete();
    }

    /**
     * Toggle a reaction: deletes the existing row or inserts a new one.
     *
     * @return bool True when the reaction was added, false when removed.
     */
    public function toggleReaction(int $shoutId, int $userId, string $reaction): bool
    {
        $existing = DB::table('shoutbox_reactions')
            ->where('shoutbox_id', $shoutId)
            ->where('user_id', $userId)
            ->where('reaction', $reaction)
            ->first();

        if ($existing) {
            DB::table('shoutbox_reactions')->where('id', $existing->id)->delete();

            return false;
        }

        DB::table('shoutbox_reactions')->insert([
            'shoutbox_id' => $shoutId,
            'user_id' => $userId,
            'reaction' => $reaction,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function deleteBefore(int $unixTs): int
    {
        return DB::table('shoutbox')->where('date', '<', $unixTs)->delete();
    }
}
