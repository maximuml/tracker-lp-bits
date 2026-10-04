<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\InviteValid;
use App\Enums\UserStatus;
use App\Models\Invite;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InviteRepository
{
    /**
     * @return ?array<int|string, mixed>
     */
    public function getUserArray(int $id): ?array
    {
        $user = User::query()->find($id);

        return $user === null ? null : $user->toArray();
    }

    public function countPendingInvitees(int $inviterId): int
    {
        return User::query()
            ->where('status', UserStatus::PENDING->value)
            ->where('invited_by', $inviterId)
            ->count();
    }

    /**
     * @param  array<int|string, mixed>  $filters
     */
    public function countInvitees(int $inviterId, array $filters): int
    {
        $query = DB::table('users as u')->where('u.invited_by', $inviterId);

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('u.status', $filters['status']);
        }
        if (isset($filters['enabled']) && $filters['enabled'] !== '') {
            $query->where('u.enabled', $filters['enabled'] === 'yes');
        }

        return (int) $query->count();
    }

    /**
     * @param  array<int|string, mixed>  $filters
     * @return array<int|string, mixed>
     */
    public function getInvitees(int $inviterId, array $filters, int $offset, int $perPage): array
    {
        $query = DB::table('users as u')
            ->where('u.invited_by', $inviterId)
            ->leftJoin('torrents as t', 't.owner', '=', 'u.id');

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('u.status', $filters['status']);
        }
        if (isset($filters['enabled']) && $filters['enabled'] !== '') {
            $query->where('u.enabled', $filters['enabled'] === 'yes');
        }

        return $query
            ->select(
                'u.id', 'u.username', 'u.email', 'u.uploaded', 'u.downloaded',
                'u.status', 'u.warned', 'u.enabled', 'u.donor',
                'u.seed_points_per_hour', 'u.seeding_torrent_count', 'u.seeding_torrent_size', 'u.last_announce_at',
                DB::raw('COUNT(t.id) as torrent_count')
            )
            ->groupBy('u.id')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    public function countInvites(int $inviterId, string $type): int
    {
        $query = DB::table('invites')->where('inviter', $inviterId);

        if ($type === 'sent') {
            $query->where('invitee', '!=', '');
        } elseif ($type === 'tmp') {
            $query->where('invitee', '')->whereNotNull('expired_at');
        }

        return (int) $query->count();
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getInvites(int $inviterId, string $type, int $offset, int $perPage): array
    {
        $query = DB::table('invites')->where('inviter', $inviterId);

        if ($type === 'sent') {
            $query->where('invitee', '!=', '');
        } elseif ($type === 'tmp') {
            $query->where('invitee', '')->whereNotNull('expired_at');
        }

        return $query
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @param  array<string>  $columns
     */
    public function findValidByHash(string $hash, array $columns = ['*']): ?Invite
    {
        return Invite::query()
            ->where('hash', $hash)
            ->where('valid', InviteValid::YES->value)
            ->first($columns);
    }

    /**
     * @return Collection<int, Invite>
     */
    public function listPendingForInviter(int $inviterId): Collection
    {
        return Invite::query()
            ->where('inviter', $inviterId)
            ->where('invitee', '')
            ->where('expired_at', '>', Carbon::now())
            ->orderBy('expired_at')
            ->get();
    }

    public function existsForInvitee(string $email): bool
    {
        return Invite::query()->where('invitee', $email)->exists();
    }

    public function findByInviterAndHash(int $inviterId, string $hash): ?Invite
    {
        return Invite::query()
            ->where('inviter', $inviterId)
            ->where('hash', $hash)
            ->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function insertInvites(array $rows): bool
    {
        return Invite::query()->insert($rows);
    }

    public function decrementInvites(int $inviterId): int
    {
        return User::query()->where('id', $inviterId)->decrement('invites');
    }

    public function deleteExpiredCodes(string $invitedBefore, string $expiredBefore): int
    {
        return Invite::query()
            ->where(function ($query) use ($invitedBefore): void {
                $query->where('time_invited', '<', $invitedBefore)
                    ->whereNotNull('time_invited')
                    ->where('invitee', '!=', '');
            })
            ->orWhere(function ($query) use ($expiredBefore): void {
                $query->where('invitee', '')
                    ->whereNotNull('expired_at')
                    ->where('expired_at', '<', $expiredBefore);
            })
            ->delete();
    }

    public function markInvalid(int $id): int
    {
        return Invite::query()->where('id', $id)->update(['valid' => InviteValid::NO->value]);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function markConsumed(int $id, array $fields): int
    {
        return Invite::query()->where('id', $id)->update($fields);
    }

    /**
     * @param  array<int, string>  $hashes
     * @return Collection<int, Invite>
     */
    public function listByHashes(array $hashes): Collection
    {
        return Invite::query()->whereIn('hash', $hashes)->get(['id', 'hash']);
    }
}
