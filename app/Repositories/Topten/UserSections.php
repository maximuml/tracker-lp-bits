<?php

declare(strict_types=1);

namespace App\Repositories\Topten;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Top-ten user leaderboard sections — extracted from ToptenRepository to keep both
 * classes under the 400-line ratchet.
 */
final class UserSections extends SectionQueries
{
    /**
     * @return list<array<string, mixed>>
     */
    public function build(int $limit, ?string $subtype): array
    {
        $base = $this->userBaseQuery();
        $sections = [];

        if ($limit === 10 || $subtype === 'ul') {
            $sections[] = [
                'view' => 'usershare',
                'data' => $this->toArray((clone $base)->orderBy('uploaded', 'desc')->limit($limit)->get()),
                'caption' => $this->caption(__('legacy/topten.text_top'), $limit, __('legacy/topten.text_uploaders')),
                'limits' => [100, 250],
                'subtype' => 'ul',
            ];
        }

        if ($limit === 10 || $subtype === 'dl') {
            $sections[] = [
                'view' => 'usershare',
                'data' => $this->toArray((clone $base)->orderBy('downloaded', 'desc')->limit($limit)->get()),
                'caption' => $this->caption(__('legacy/topten.text_top'), $limit, __('legacy/topten.text_downloaders')),
                'limits' => [100, 250],
                'subtype' => 'dl',
            ];
        }

        if ($limit === 10 || $subtype === 'uls') {
            $note = __('legacy/topten.text_fastest_up_note');
            $sections[] = [
                'view' => 'usershare',
                'data' => $this->toArray((clone $base)->where('uploaded', '>', 53687091200)->orderBy('upspeed', 'desc')->limit($limit)->get()),
                'caption' => $this->caption(__('legacy/topten.text_top'), $limit, __('legacy/topten.text_fastest_uploaders'), $note),
                'limits' => [100, 250],
                'subtype' => 'uls',
            ];
        }

        if ($limit === 10 || $subtype === 'dls') {
            $note = __('legacy/topten.text_fastest_note');
            $sections[] = [
                'view' => 'usershare',
                'data' => $this->toArray((clone $base)->orderBy('downspeed', 'desc')->limit($limit)->get()),
                'caption' => $this->caption(__('legacy/topten.text_top'), $limit, __('legacy/topten.text_fastest_downloaders'), $note),
                'limits' => [100, 250],
                'subtype' => 'dls',
            ];
        }

        if ($limit === 10 || $subtype === 'bsh') {
            $sections[] = [
                'view' => 'usershare',
                'data' => $this->toArray((clone $base)->where('downloaded', '>', 53687091200)->orderByRaw('uploaded / downloaded DESC')->limit($limit)->get()),
                'caption' => $this->caption(__('legacy/topten.text_top'), $limit, __('legacy/topten.text_best_sharers'), __('legacy/topten.text_sharers_note')),
                'limits' => [100, 250],
                'subtype' => 'bsh',
            ];
        }

        if ($limit === 10 || $subtype === 'wsh') {
            $sections[] = [
                'view' => 'usershare',
                'data' => $this->toArray((clone $base)->where('downloaded', '>', 53687091200)->orderByRaw('uploaded / downloaded ASC, downloaded DESC')->limit($limit)->get()),
                'caption' => $this->caption(__('legacy/topten.text_top'), $limit, __('legacy/topten.text_worst_sharers'), __('legacy/topten.text_sharers_note')),
                'limits' => [100, 250],
                'subtype' => 'wsh',
            ];
        }

        return $sections;
    }

    private function userBaseQuery(): Builder
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $speedStr = 'uploaded / (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(added)) AS upspeed, downloaded / (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(added)) AS downspeed';
        } elseif (DB::connection()->getDriverName() === 'pgsql') {
            $speedStr = 'uploaded::numeric / (EXTRACT(EPOCH FROM NOW()) - EXTRACT(EPOCH FROM added)) AS upspeed, downloaded::numeric / (EXTRACT(EPOCH FROM NOW()) - EXTRACT(EPOCH FROM added)) AS downspeed';
        } else {
            throw new \RuntimeException('Unsupported database driver for top-ten speed calculation.');
        }

        return DB::table('users')
            ->selectRaw("id as userid, username, added, uploaded, downloaded, {$speedStr}")
            ->where('enabled', true);
    }
}
