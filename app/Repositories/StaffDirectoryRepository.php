<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

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
}
