<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class OfferRepository extends BaseRepository
{
    private const DEFAULT_PER_PAGE = 25;

    /** @var list<string> */
    private const ALLOWED_SORT_COLUMNS = ['category', 'name', 'added', 'comments', 'yeah', 'against', 'v_res'];

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
            $query->where('offers.name', 'like', '%' . $search . '%');
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
            ], fn ($v) => $v !== '' && $v !== 0 && $v !== []),
        ];
    }
}
