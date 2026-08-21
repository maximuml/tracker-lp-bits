<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Comment;
use App\Models\Offer;
use App\Models\StaffMessage;
use App\Models\User;
use App\Support\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

final class OfferRepository extends BaseRepository
{
    private const DEFAULT_PER_PAGE = 25;

    /** @var list<string> */
    private const ALLOWED_SORT_COLUMNS = ['category', 'name', 'added', 'comments', 'yeah', 'against', 'v_res'];

    public static function findOffer(int $id): ?Offer
    {
        return Offer::query()->where('id', $id)->first();
    }

    public static function findOfferWithUser(int $id): ?Offer
    {
        return Offer::query()->with('user')->where('offers.id', $id)->first(['offers.userid', 'offers.name']);
    }

    public static function findOfferWithVotes(int $id): ?Offer
    {
        return Offer::query()->where('id', $id)->first(['yeah', 'against', 'allowed', 'userid', 'name']);
    }

    public static function offerNameExists(string $name): bool
    {
        return Offer::query()->where('name', $name)->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function createOffer(array $data): int
    {
        return (int) Offer::query()->insertGetId($data);
    }

    /**
     * @return array{yeah: int, against: int}
     */
    public static function getVoteCounts(int $offerId): array
    {
        return [
            'yeah' => (int) NexusDB::table('offervotes')->where('vote', 'yeah')->where('offerid', $offerId)->count(),
            'against' => (int) NexusDB::table('offervotes')->where('vote', 'against')->where('offerid', $offerId)->count(),
        ];
    }

    public static function getOfferOwner(int $id): ?int
    {
        $value = Offer::query()->where('id', $id)->value('userid');

        return $value === null ? null : (int) $value;
    }

    public static function getOfferName(int $id): ?string
    {
        return Offer::query()->where('id', $id)->value('name');
    }

    public static function getVoteCount(int $offerId): int
    {
        return (int) NexusDB::table('offervotes')->where('offerid', $offerId)->count();
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public static function getVoteRows(int $offerId, int $offset, int $perPage): Collection
    {
        return NexusDB::table('offervotes')
            ->where('offerid', $offerId)
            ->orderBy('id')
            ->offset($offset)
            ->limit($perPage)
            ->get();
    }

    public static function userVoted(int $offerId, int $userId): bool
    {
        return (bool) NexusDB::table('offervotes')->where('offerid', $offerId)->where('userid', $userId)->exists();
    }

    public static function recordVote(int $offerId, int $userId, string $vote): void
    {
        NexusDB::table('offervotes')->insert([
            'offerid' => $offerId,
            'userid' => $userId,
            'vote' => $vote,
        ]);
    }

    public static function incrementVote(int $offerId, string $column): bool
    {
        return (bool) Offer::query()->where('id', $offerId)->increment($column);
    }

    public static function allowOffer(int $offerId, string $allowedTime): bool
    {
        return (bool) Offer::query()->where('id', $offerId)->update(['allowed' => 'allowed', 'allowedtime' => $allowedTime]);
    }

    public static function denyOffer(int $offerId): bool
    {
        return (bool) Offer::query()->where('id', $offerId)->update(['allowed' => 'denied']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function updateOffer(int $offerId, array $data): bool
    {
        return (bool) Offer::query()->where('id', $offerId)->update($data);
    }

    public static function deleteOffer(int $offerId): bool
    {
        return (bool) Offer::query()->where('id', $offerId)->delete();
    }

    public static function deleteOfferVotes(int $offerId): int
    {
        return NexusDB::table('offervotes')->where('offerid', $offerId)->delete();
    }

    public static function deleteOfferComments(int $offerId): int
    {
        return Comment::query()->where('offer', $offerId)->delete();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getLastComment(int $offerId): ?array
    {
        $row = Comment::query()->where('offer', $offerId)->orderByDesc('added')->first(['user', 'added', 'text']);

        return $row ? $row->toArray() : null;
    }

    public static function countComments(int $offerId): int
    {
        return (int) Comment::query()->where('offer', $offerId)->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Comment>
     */
    public static function getComments(int $offerId, int $offset, int $perPage): \Illuminate\Database\Eloquent\Collection
    {
        return Comment::query()
            ->where('offer', $offerId)
            ->orderBy('id')
            ->offset($offset)
            ->limit($perPage)
            ->get(['id', 'text', 'user', 'added', 'editedby', 'editdate']);
    }

    public static function addStaffMessage(int $senderId, string $senderName, string $offerName, int $offerId): void
    {
        StaffMessage::query()->insert([
            'sender' => $senderId,
            'subject' => Locale::trans('offer.msg_new_offer_subject', [], null),
            'msg' => Locale::trans('offer.msg_new_offer_msg', ['username' => "[url=userdetails.php?id={$senderId}]{$senderName}[/url]", 'offername' => "[url=offers.php?id={$offerId}&off_details=1]{$offerName}[/url]"], null),
            'added' => now(),
        ]);
    }

    public static function getUsername(int $userId): ?string
    {
        return User::query()->where('id', $userId)->value('username');
    }

    /**
     * @return array{count: int, rows: Collection<int, \stdClass>}
     */
    public static function getLegacyList(int $category, int $offerorId, string $search, string $sort, string $direction, int $offset, int $perPage): array
    {
        $query = DB::table('offers')
            ->join('categories', 'offers.category', '=', 'categories.id')
            ->join('users', 'offers.userid', '=', 'users.id');

        if ($offerorId > 0) {
            $query->where('offers.userid', $offerorId);
            if ($category > 0) {
                $query->where('offers.category', $category);
            }
        } elseif ($category > 0) {
            $query->where('offers.category', $category);
        }

        if ($search !== '') {
            $query->where('offers.name', 'like', '%'.$search.'%');
        }

        $count = (int) $query->count('offers.id');

        $selectColumns = [
            'offers.id',
            'offers.userid',
            'offers.name',
            'offers.added',
            'offers.allowedtime',
            'offers.comments',
            'offers.yeah',
            'offers.against',
            'offers.category as cat_id',
            'offers.allowed',
            'categories.image',
            'categories.name as cat',
        ];

        if (in_array($sort, self::ALLOWED_SORT_COLUMNS, true)) {
            if ($sort === 'v_res') {
                $query->orderBy(DB::raw('(offers.yeah - offers.against)'), $direction);
            } elseif ($sort === 'category') {
                $query->orderBy('offers.category', $direction);
            } else {
                $query->orderBy("offers.{$sort}", $direction);
            }
        } else {
            $query->orderByDesc('offers.added');
        }

        $rows = $query->select($selectColumns)
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return ['count' => $count, 'rows' => $rows];
    }

    /**
     * @return array<string, mixed>
     */
    public function list(Request $request): array
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) ($this->getPerPageFromRequest($request) ?: self::DEFAULT_PER_PAGE);
        $perPage = max(1, min($perPage, 100));
        $offset = ($page - 1) * $perPage;

        $category = (int) $request->input('category', 0);
        $offerorId = (int) $request->input('offerorid', 0);
        $search = trim((string) $request->input('search', ''));
        $sort = (string) $request->input('sort', '');
        $direction = strtolower((string) $request->input('type', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = DB::table('offers')
            ->join('categories', 'offers.category', '=', 'categories.id')
            ->join('users', 'offers.userid', '=', 'users.id');

        if ($offerorId > 0) {
            $query->where('offers.userid', $offerorId);
            if ($category > 0) {
                $query->where('offers.category', $category);
            }
        } elseif ($category > 0) {
            $query->where('offers.category', $category);
        }

        if ($search !== '') {
            $query->where('offers.name', 'like', '%'.$search.'%');
        }

        $count = (int) $query->count('offers.id');

        $selectColumns = [
            'offers.id',
            'offers.userid',
            'offers.name',
            'offers.added',
            'offers.allowedtime',
            'offers.comments',
            'offers.yeah',
            'offers.against',
            'offers.category as cat_id',
            'offers.allowed',
            'categories.image',
            'categories.name as cat',
            'users.username as username',
        ];

        if (in_array($sort, self::ALLOWED_SORT_COLUMNS, true)) {
            if ($sort === 'v_res') {
                $query->orderBy(DB::raw('(offers.yeah - offers.against)'), $direction);
            } elseif ($sort === 'category') {
                $query->orderBy('offers.category', $direction);
            } else {
                $query->orderBy("offers.{$sort}", $direction);
            }
        } else {
            $query->orderByDesc('offers.added');
        }

        $rows = $query->select($selectColumns)
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return [
            'data' => $rows,
            'total' => $count,
            'page' => $page,
            'per_page' => $perPage,
            'filters' => array_filter([
                'category' => $category,
                'offerorid' => $offerorId,
                'search' => $search,
                'sort' => $sort,
                'type' => $direction,
            ]),
        ];
    }
}
