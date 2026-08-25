<?php

namespace App\Repositories;

use App\Models\Comment;
use App\Models\Offer;
use App\Models\Torrent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CommentRepository
{
    /**
     * @return ?array<int|string, mixed>
     */
    public static function getParent(int $parentId, string $type): ?array
    {
        $row = match ($type) {
            'torrent' => Torrent::query()->where('id', $parentId)->select(['name', 'owner'])->first(),
            'offer' => Offer::query()->where('id', $parentId)->select(['name', 'userid as owner'])->first(),
            default => DB::table('requests')->where('id', $parentId)->selectRaw('request as name, userid as owner')->first(),
        };

        if ($row === null) {
            return null;
        }

        return $row instanceof Model ? $row->toArray() : (array) $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getLatest(int $limit, int $offset): array
    {
        return DB::table('comments as c')
            ->leftJoin('users as u', 'c.user', '=', 'u.id')
            ->leftJoin('torrents as t', 'c.torrent', '=', 't.id')
            ->leftJoin('offers as o', 'c.offer', '=', 'o.id')
            ->orderByDesc('c.id')
            ->offset($offset)
            ->limit($limit)
            ->selectRaw('c.*, u.username, u.avatar, COALESCE(t.name, o.name) as parent_name, CASE WHEN c.torrent > 0 THEN "torrent" WHEN c.offer > 0 THEN "offer" ELSE NULL END as parent_type, COALESCE(c.torrent, c.offer) as parent_id')
            ->get()
            ->toArray();
    }

    public static function countLatest(): int
    {
        return (int) DB::table('comments')->count();
    }

    /**
     * @return ?array<int|string, mixed>
     */
    public static function getQuote(int $commentId): ?array
    {
        $row = DB::table('comments')
            ->leftJoin('users', 'comments.user', '=', 'users.id')
            ->where('comments.id', $commentId)
            ->select('comments.text', 'users.username')
            ->first();

        return $row === null ? null : (array) $row;
    }

    /**
     * @return ?array<int|string, mixed>
     */
    public static function getForEdit(int $commentId, string $type): ?array
    {
        $query = DB::table('comments as c');

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
     * @return ?array<int|string, mixed>
     */
    public static function getForDelete(int $commentId, string $type): ?array
    {
        $row = DB::table('comments')
            ->where('id', $commentId)
            ->selectRaw("{$type} as pid, user")
            ->first();

        return $row === null ? null : (array) $row;
    }

    /**
     * @return ?array<int|string, mixed>
     */
    public static function getForViewOriginal(int $commentId, string $type): ?array
    {
        $query = DB::table('comments as c');

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

    public static function getCommentPmSetting(int $userId): ?string
    {
        return User::query()->where('id', $userId)->value('commentpm');
    }

    /**
     * Fetch a paginated list of comments for the given parent and type.
     *
     * @return LengthAwarePaginator<int, Comment>
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
            $id = (int) DB::table('comments')->insertGetId([
                'user' => $userId,
                'request' => $parentId,
                'added' => $now->toDateTimeString(),
                'text' => $text,
                'ori_text' => $text,
            ]);
            DB::table('requests')->where('id', $parentId)->increment('comments');
            $comment = (object) ['id' => $id];
        }

        User::query()->where('id', $userId)->update(['last_comment' => $now]);

        return (int) $comment->id;
    }

    public static function update(int $commentId, string $text, int $editedBy): void
    {
        Comment::query()->where('id', $commentId)->update([
            'text' => $text,
            'editdate' => Carbon::now(),
            'editedby' => $editedBy,
        ]);
    }

    public static function delete(int $commentId, string $type, int $parentId): bool
    {
        $deleted = Comment::query()->where('id', $commentId)->delete();

        if ($deleted) {
            if ($type == 'torrent') {
                Torrent::query()->where('id', $parentId)->decrement('comments');
            } elseif ($type == 'offer') {
                Offer::query()->where('id', $parentId)->decrement('comments');
            } else {
                DB::table('requests')->where('id', $parentId)->decrement('comments');
            }
        }

        return (bool) $deleted;
    }
}
