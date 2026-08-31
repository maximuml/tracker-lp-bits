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

final class OfferRepository extends BaseRepository
{
    private const DEFAULT_PER_PAGE = 25;

    /** @var list<string> */
    private const ALLOWED_SORT_COLUMNS = ['category', 'name', 'added', 'comments', 'yeah', 'against', 'v_res'];

    public function findOffer(int $id): ?Offer
    {
        return Offer::query()->where('id', $id)->first();
    }

    public function findOfferWithUser(int $id): ?Offer
    {
        return Offer::query()->with('user')->where('offers.id', $id)->first(['offers.userid', 'offers.name']);
    }

    public function findOfferWithVotes(int $id): ?Offer
    {
        return Offer::query()->where('id', $id)->first(['yeah', 'against', 'allowed', 'userid', 'name']);
    }

    public function offerNameExists(string $name): bool
    {
        return Offer::query()->where('name', $name)->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createOffer(array $data): int
    {
        return (int) Offer::query()->insertGetId($data);
    }

    /**
     * @return array{yeah: int, against: int}
     */
    public function getVoteCounts(int $offerId): array
    {
        return [
            'yeah' => (int) DB::table('offervotes')->where('vote', 'yeah')->where('offerid', $offerId)->count(),
            'against' => (int) DB::table('offervotes')->where('vote', 'against')->where('offerid', $offerId)->count(),
        ];
    }

    public function getOfferOwner(int $id): ?int
    {
        $value = Offer::query()->where('id', $id)->value('userid');

        return $value === null ? null : (int) $value;
    }

    public function getOfferName(int $id): ?string
    {
        return Offer::query()->where('id', $id)->value('name');
    }

    public function getVoteCount(int $offerId): int
    {
        return (int) DB::table('offervotes')->where('offerid', $offerId)->count();
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public function getVoteRows(int $offerId, int $offset, int $perPage): Collection
    {
        return DB::table('offervotes')
            ->where('offerid', $offerId)
            ->orderBy('id')
            ->offset($offset)
            ->limit($perPage)
            ->get();
    }

    public function userVoted(int $offerId, int $userId): bool
    {
        return (bool) DB::table('offervotes')->where('offerid', $offerId)->where('userid', $userId)->exists();
    }

    public function recordVote(int $offerId, int $userId, string $vote): void
    {
        DB::table('offervotes')->insert([
            'offerid' => $offerId,
            'userid' => $userId,
            'vote' => $vote,
        ]);
    }

    public function incrementVote(int $offerId, string $column): bool
    {
        return (bool) Offer::query()->where('id', $offerId)->increment($column);
    }

    public function allowOffer(int $offerId, string $allowedTime): bool
    {
        return (bool) Offer::query()->where('id', $offerId)->update(['allowed' => 'allowed', 'allowedtime' => $allowedTime]);
    }

    public function denyOffer(int $offerId): bool
    {
        return (bool) Offer::query()->where('id', $offerId)->update(['allowed' => 'denied']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateOffer(int $offerId, array $data): bool
    {
        return (bool) Offer::query()->where('id', $offerId)->update($data);
    }

    public function deleteOffer(int $offerId): bool
    {
        return (bool) Offer::query()->where('id', $offerId)->delete();
    }

    public function deleteOfferVotes(int $offerId): int
    {
        return DB::table('offervotes')->where('offerid', $offerId)->delete();
    }

    public function deleteOfferComments(int $offerId): int
    {
        return Comment::query()->where('offer', $offerId)->delete();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLastComment(int $offerId): ?array
    {
        $row = Comment::query()->where('offer', $offerId)->orderByDesc('added')->first(['user', 'added', 'text']);

        return $row ? $row->toArray() : null;
    }

    public function countComments(int $offerId): int
    {
        return (int) Comment::query()->where('offer', $offerId)->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Comment>
     */
    public function getComments(int $offerId, int $offset, int $perPage): \Illuminate\Database\Eloquent\Collection
    {
        return Comment::query()
            ->where('offer', $offerId)
            ->orderBy('id')
            ->offset($offset)
            ->limit($perPage)
            ->get(['id', 'text', 'user', 'added', 'editedby', 'editdate']);
    }

    public function addStaffMessage(int $senderId, string $senderName, string $offerName, int $offerId): void
    {
        StaffMessage::query()->insert([
            'sender' => $senderId,
            'subject' => Locale::trans('offer.msg_new_offer_subject', [], null),
            'msg' => Locale::trans('offer.msg_new_offer_msg', ['username' => "[url=userdetails.php?id={$senderId}]{$senderName}[/url]", 'offername' => "[url=offers.php?id={$offerId}&off_details=1]{$offerName}[/url]"], null),
            'added' => now(),
        ]);
    }

    public function getUsername(int $userId): ?string
    {
        return User::query()->where('id', $userId)->value('username');
    }

    /**
     * @return array{count: int, rows: Collection<int, \stdClass>}
     */
    public function getLegacyList(int $category, int $offerorId, string $search, string $sort, string $direction, int $offset, int $perPage): array
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';
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
