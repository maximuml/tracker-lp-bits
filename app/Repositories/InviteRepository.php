<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
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
            ->where('status', 'pending')
            ->where('invited_by', $inviterId)
            ->count();
    }

    /**
     * @param  array<int|string, mixed>  $filters
     */
    public function countInvitees(int $inviterId, array $filters): int
    {
        $query = DB::table('users as u')->where('u.invited_by', $inviterId);

        if (! empty($filters['status'])) {
            $query->where('u.status', $filters['status']);
        }
        if (! empty($filters['enabled'])) {
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

        if (! empty($filters['status'])) {
            $query->where('u.status', $filters['status']);
        }
        if (! empty($filters['enabled'])) {
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
}
