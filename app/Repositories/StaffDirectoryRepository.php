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
    public function listPickers(): Collection
    {
        return User::query()
            ->where('picker', true)
            ->where('status', UserStatus::CONFIRMED->value)
            ->orderBy('username')
            ->get(['id', 'country', 'last_access', 'pickfor']);
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
        return array_values(DB::table('sysoppanel')->get()->map(fn ($r): array => (array) $r)->all());
    }

    /** @return list<array<string, mixed>> */
    public function listAdminPanels(): array
    {
        return array_values(DB::table('adminpanel')->get()->map(fn ($r): array => (array) $r)->all());
    }

    /** @return list<array<string, mixed>> */
    public function listModPanels(): array
    {
        return array_values(DB::table('modpanel')->get()->map(fn ($r): array => (array) $r)->all());
    }

    public function panelMenuExists(string $table, string $url): bool
    {
        return DB::table($table)->where('url', $url)->exists();
    }

    /** @param  array<string, mixed>  $menu */
    public function insertPanelMenu(string $table, array $menu): int
    {
        return (int) DB::table($table)->insertGetId($menu);
    }

    /**
     * @param  list<string>  $urls
     * @param  list<string>  $tables
     */
    public function deletePanelMenusByUrls(array $urls, array $tables): int
    {
        $deleted = 0;
        foreach ($tables as $table) {
            $deleted += DB::table($table)->whereIn('url', $urls)->delete();
        }

        return $deleted;
    }
}
