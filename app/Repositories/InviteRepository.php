<?php

namespace App\Repositories;

use App\Models\User;
use Nexus\Database\NexusDB;

class InviteRepository
{
    /**
     * @param  int  $id
     * @return  ?array<int|string, mixed>
     */
    public static function getUserArray(int $id): ?array
    {
        $user = User::query()->find($id);

        return $user === null ? null : $user->toArray();
    }

    /** @param  int  $inviterId */
    public static function countPendingInvitees(int $inviterId): int
    {
        return User::query()
            ->where('status', 'pending')
            ->where('invited_by', $inviterId)
            ->count();
    }

    /**
     * @param  int  $inviterId
     * @param  array<int|string, mixed>  $filters
     */
    public static function countInvitees(int $inviterId, array $filters): int
    {
        $query = NexusDB::table('users as u')->where('u.invited_by', $inviterId);

        if (!empty($filters['status'])) {
            $query->where('u.status', $filters['status']);
        }
        if (!empty($filters['enabled'])) {
            $query->where('u.enabled', $filters['enabled']);
        }

        return (int) $query->count();
    }

    /**
     * @param  int  $inviterId
     * @param  array<int|string, mixed>  $filters
     * @param  int  $offset
     * @param  int  $perPage
     * @return  array<int|string, mixed>
     */
    public static function getInvitees(int $inviterId, array $filters, int $offset, int $perPage): array
    {
        $query = NexusDB::table('users as u')
            ->where('u.invited_by', $inviterId)
            ->leftJoin('torrents as t', 't.owner', '=', 'u.id');

        if (!empty($filters['status'])) {
            $query->where('u.status', $filters['status']);
        }
        if (!empty($filters['enabled'])) {
            $query->where('u.enabled', $filters['enabled']);
        }

        return $query
            ->select(
                'u.id', 'u.username', 'u.email', 'u.uploaded', 'u.downloaded',
                'u.status', 'u.warned', 'u.enabled', 'u.donor',
                'u.seed_points_per_hour', 'u.seeding_torrent_count', 'u.seeding_torrent_size', 'u.last_announce_at',
                NexusDB::raw('COUNT(t.id) as torrent_count')
            )
            ->groupBy('u.id')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @param  int  $inviterId
     * @param  string  $type
     */
    public static function countInvites(int $inviterId, string $type): int
    {
        $query = NexusDB::table('invites')->where('inviter', $inviterId);

        if ($type === 'sent') {
            $query->where('invitee', '!=', '');
        } elseif ($type === 'tmp') {
            $query->where('invitee', '')->whereNotNull('expired_at');
        }

        return (int) $query->count();
    }

    /**
     * @param  int  $inviterId
     * @param  string  $type
     * @param  int  $offset
     * @param  int  $perPage
     * @return  array<int|string, mixed>
     */
    public static function getInvites(int $inviterId, string $type, int $offset, int $perPage): array
    {
        $query = NexusDB::table('invites')->where('inviter', $inviterId);

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
