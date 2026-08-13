<?php

namespace App\Repositories;

use App\Models\Comment;
use App\Models\Offer;
use App\Models\Torrent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Nexus\Database\NexusDB;

class CommentRepository
{
    /**
     * @param  int  $parentId
     * @param  string  $type
     * @return  ?array<int|string, mixed>
     */
    public static function getParent(int $parentId, string $type): ?array
    {
        $row = match ($type) {
            'torrent' => Torrent::query()->where('id', $parentId)->select(['name', 'owner'])->first(),
            'offer' => Offer::query()->where('id', $parentId)->select(['name', 'userid as owner'])->first(),
            default => NexusDB::table('requests')->where('id', $parentId)->selectRaw('request as name, userid as owner')->first(),
        };

        return $row === null ? null : $row->toArray();
    }

    /**
     * @param  int  $limit
     * @param  int  $offset
     * @return  array<int, array<string, mixed>>
     */
    public static function getLatest(int $limit, int $offset): array
    {
        return NexusDB::table('comments as c')
            ->leftJoin('users as u', 'c.user', '=', 'u.id')
            ->leftJoin('torrents as t', 'c.torrent', '=', 't.id')
            ->leftJoin('offers as o', 'c.offer', '=', 'o.id')
            ->orderByDesc('c.id')
            ->offset($offset)
            ->limit($limit)
            ->selectRaw('c.*, u.username, COALESCE(t.name, o.name) as parent_name, CASE WHEN c.torrent > 0 THEN "torrent" WHEN c.offer > 0 THEN "offer" ELSE NULL END as parent_type, COALESCE(c.torrent, c.offer) as parent_id')
            ->get()
            ->toArray();
    }

    public static function countLatest(): int
    {
        return (int) NexusDB::table('comments')->count();
    }

    /**
     * @param  int  $commentId
     * @return  ?array<int|string, mixed>
     */
    public static function getQuote(int $commentId): ?array
    {
        $row = NexusDB::table('comments')
            ->leftJoin('users', 'comments.user', '=', 'users.id')
            ->where('comments.id', $commentId)
            ->select('comments.text', 'users.username')
            ->first();

        return $row === null ? null : (array) $row;
    }

    /**
     * @param  int  $commentId
     * @param  string  $type
     * @return  ?array<int|string, mixed>
     */
    public static function getForEdit(int $commentId, string $type): ?array
    {
        $query = NexusDB::table('comments as c');

        if ($type == 'torrent') {
            $query = $query->join('torrents as t', 'c.torrent', '=', 't.id')
                ->where('c.id', $commentId)
                ->select('c.*', 't.name', 't.id as parent_id');
        } elseif ($type == 'offer') {
            $query = $query->join('offers as o', 'c.offer', '=', 'o.id')
                ->where('c.id', $commentId)
                ->select('c.*', 'o.name', 'o.id as parent_id');
        } else {
            $query = $query->join('requests as r', 'c.request', '=', 'r.id')
                ->where('c.id', $commentId)
                ->select('c.*', 'r.request as name', 'r.id as parent_id');
        }

        $row = $query->first();

        return $row === null ? null : (array) $row;
    }

    /**
     * @param  int  $commentId
     * @param  string  $type
     * @return  ?array<int|string, mixed>
     */
    public static function getForDelete(int $commentId, string $type): ?array
    {
        $row = NexusDB::table('comments')
            ->where('id', $commentId)
            ->selectRaw("{$type} as pid, user")
            ->first();

        return $row === null ? null : (array) $row;
    }

    /**
     * @param  int  $commentId
     * @param  string  $type
     * @return  ?array<int|string, mixed>
     */
    public static function getForViewOriginal(int $commentId, string $type): ?array
    {
        $query = NexusDB::table('comments as c');

        if ($type == 'torrent') {
            $query = $query->join('torrents as t', 'c.torrent', '=', 't.id')
                ->where('c.id', $commentId)
                ->select('c.*', 't.name');
        } elseif ($type == 'offer') {
            $query = $query->join('offers as o', 'c.offer', '=', 'o.id')
                ->where('c.id', $commentId)
                ->select('c.*', 'o.name');
        } else {
            $query = $query->join('requests as r', 'c.request', '=', 'r.id')
                ->where('c.id', $commentId)
                ->select('c.*', 'r.request as name');
        }

        $row = $query->first();

        return $row === null ? null : (array) $row;
    }

    /** @param  int  $userId */
    public static function getCommentPmSetting(int $userId): ?string
    {
        return User::query()->where('id', $userId)->value('commentpm');
    }

    /**
     * Fetch a paginated list of comments for the given parent and type.
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, Comment>
     */
    public static function getList(Request $request, User $user)
    {
        $type = $request->input('type', Comment::TYPE_TORRENT);
        $parentId = (int) $request->input('parent_id', 0);

        $query = Comment::query()->with(['create_user', 'update_user']);

        $typeMap = Comment::TYPE_MAPS[$type] ?? null;
        if ($typeMap !== null) {
            foreach (Comment::TYPE_MAPS as $key => $value) {
                if ($type === $key) {
                    $query->where($value['foreign_key'], $parentId);
                } else {
                    $query->where($value['foreign_key'], 0);
                }
            }
        } else {
            $query->where('torrent', 0)->where('offer', 0);
        }

        return $query->orderBy('id', 'desc')->paginate((int) $request->input('per_page', 20));
    }

    /**
     * @param  int  $parentId
     * @param  string  $type
     * @param  string  $text
     * @param  int  $userId
     */
    public static function create(int $parentId, string $type, string $text, int $userId): int
    {
        $now = Carbon::now();

        if ($type == 'torrent') {
            $comment = Comment::create([
                'user' => $userId,
                'torrent' => $parentId,
                'added' => $now,
                'text' => $text,
                'ori_text' => $text,
            ]);
            Torrent::query()->where('id', $parentId)->increment('comments');
        } elseif ($type == 'offer') {
            $comment = Comment::create([
                'user' => $userId,
                'offer' => $parentId,
                'added' => $now,
                'text' => $text,
                'ori_text' => $text,
            ]);
            Offer::query()->where('id', $parentId)->increment('comments');
        } else {
            $id = (int) NexusDB::table('comments')->insertGetId([
                'user' => $userId,
                'request' => $parentId,
                'added' => $now->toDateTimeString(),
                'text' => $text,
                'ori_text' => $text,
            ]);
            NexusDB::table('requests')->where('id', $parentId)->increment('comments');
            $comment = (object) ['id' => $id];
        }

        User::query()->where('id', $userId)->update(['last_comment' => $now]);

        return (int) $comment->id;
    }

    /**
     * @param  int  $commentId
     * @param  string  $text
     * @param  int  $editedBy
     */
    public static function update(int $commentId, string $text, int $editedBy): void
    {
        Comment::query()->where('id', $commentId)->update([
            'text' => $text,
            'editdate' => Carbon::now(),
            'editedby' => $editedBy,
        ]);
    }

    /**
     * @param  int  $commentId
     * @param  string  $type
     * @param  int  $parentId
     */
    public static function delete(int $commentId, string $type, int $parentId): bool
    {
        $deleted = Comment::query()->where('id', $commentId)->delete();

        if ($deleted) {
            if ($type == 'torrent') {
                Torrent::query()->where('id', $parentId)->decrement('comments');
            } elseif ($type == 'offer') {
                Offer::query()->where('id', $parentId)->decrement('comments');
            } else {
                NexusDB::table('requests')->where('id', $parentId)->decrement('comments');
            }
        }

        return (bool) $deleted;
    }
}
