<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class StaffDirectoryRepository extends BaseRepository
{
    /**
     * @return Collection<int, User>
     */
    public function listSupportStaff(): Collection
    {
        return User::query()
            ->where('support', true)
            ->where('status', UserStatus::CONFIRMED->value)
            ->orderBy('username')
            ->get(['id', 'country', 'last_access', 'supportlang', 'supportfor']);
    }

    /**
     * @return Collection<int, User>
     */
    public function listStaffAbove(int $class): Collection
    {
        return User::query()
            ->where('class', '>', $class)
            ->where('status', UserStatus::CONFIRMED->value)
            ->orderByDesc('class')
            ->orderBy('username')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function listAtClass(int $class): Collection
    {
        return User::query()
            ->where('class', $class)
            ->where('status', UserStatus::CONFIRMED->value)
            ->orderBy('username')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function listPendingOrdered(): Collection
    {
        return User::query()
            ->where('status', UserStatus::PENDING->value)
            ->orderBy('username')
            ->get();
    }

    /**
     * Forum moderators joined with display columns, one row per user.
     *
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    public function listForumModerators(): \Illuminate\Support\Collection
    {
        return DB::table('forummods')
            ->leftJoin('users', 'forummods.userid', '=', 'users.id')
            ->orderBy('forummods.forumid')
            ->orderBy('forummods.userid')
            ->get(['forummods.userid AS userid', 'users.last_access', 'users.country'])
            ->unique('userid')
            ->values();
    }

    /**
     * Forums moderated by each of the given users, grouped by userid.
     *
     * @param  array<int>  $userIds
     * @return \Illuminate\Support\Collection<int|string, \Illuminate\Support\Collection<int, \stdClass>>
     */
    public function listModeratedForums(array $userIds): \Illuminate\Support\Collection
    {
        return DB::table('forums as f')
            ->leftJoin('forummods as fm', 'f.id', '=', 'fm.forumid')
            ->whereIn('fm.userid', $userIds)
            ->get(['fm.userid', 'f.id', 'f.name'])
            ->groupBy('userid');
    }

    /** @return list<array<string, mixed>> */
    public function listSysopPanels(): array
    {
        return $this->normalizePanelRows(DB::table('sysoppanel')->get()->map(fn ($r): array => (array) $r)->all());
    }

    /** @return list<array<string, mixed>> */
    public function listAdminPanels(): array
    {
        return $this->normalizePanelRows(DB::table('adminpanel')->get()->map(fn ($r): array => (array) $r)->all());
    }

    /** @return list<array<string, mixed>> */
    public function listModPanels(): array
    {
        return $this->normalizePanelRows(DB::table('modpanel')->get()->map(fn ($r): array => (array) $r)->all());
    }

    public function panelMenuExists(string $table, string $url): bool
    {
        return DB::table($table)->whereIn('url', $this->panelUrlVariants($url))->exists();
    }

    /** @param  array<string, mixed>  $menu */
    public function insertPanelMenu(string $table, array $menu): int
    {
        if (isset($menu['url'])) {
            $menu['url'] = $this->canonicalPanelUrl((string) $menu['url']);
        }

        return (int) DB::table($table)->insertGetId($menu);
    }

    /**
     * @param  list<string>  $urls
     * @param  list<string>  $tables
     */
    public function deletePanelMenusByUrls(array $urls, array $tables): int
    {
        $candidates = [];
        foreach ($urls as $url) {
            array_push($candidates, ...$this->panelUrlVariants($url));
        }

        $deleted = 0;
        foreach ($tables as $table) {
            $deleted += DB::table($table)->whereIn('url', array_values(array_unique($candidates)))->delete();
        }

        return $deleted;
    }

    /**
     * Legacy seed data stores bare script names ("forummanage.php") that the
     * browser resolves under the current path (/web/…) and 404s. Panel URLs
     * must be root-relative; absolute and fragment URLs pass through.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function normalizePanelRows(array $rows): array
    {
        foreach ($rows as $i => $row) {
            if (isset($row['url'])) {
                $rows[$i]['url'] = $this->canonicalPanelUrl((string) $row['url']);
            }
        }

        return array_values($rows);
    }

    private function canonicalPanelUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '/') || str_starts_with($url, '#')
            || preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) === 1) {
            return $url;
        }

        return '/'.$url;
    }

    /** @return list<string> */
    private function panelUrlVariants(string $url): array
    {
        $variants = [trim($url), $this->canonicalPanelUrl($url)];
        if (str_starts_with($variants[0], '/')) {
            $variants[] = substr($variants[0], 1);
        }

        return array_values(array_unique($variants));
    }
}
