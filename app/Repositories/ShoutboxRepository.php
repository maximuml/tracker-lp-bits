<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
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
            $like = '%' . $filters['search'] . '%';
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
}
