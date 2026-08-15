<?php

namespace App\Repositories;

use App\Support\SupportContext;
use App\Support\UserDisplay;
use Illuminate\Support\Facades\Cache;
use Nexus\Database\NexusDB;

class PollRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public static function findForEdit(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $row = NexusDB::table('polls')->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function lastPoll(): ?array
    {
        $row = NexusDB::table('polls')->orderByDesc('added')->first(['question', 'added']);
        return $row ? (array) $row : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function createOrUpdate(array $data, ?int $id = null): int
    {
        if ($id) {
            NexusDB::table('polls')->where('id', $id)->update($data);
            $cache = SupportContext::getCache();
            if ($cache !== null) {
                $cache->delete_value('current_poll_content');
                $cache->delete_value('current_poll_result', true);
            }

            return $id;
        }

        $data['added'] = now()->toDateTimeString();
        $newId = (int) NexusDB::table('polls')->insertGetId($data);

        $cache = SupportContext::getCache();
        if ($cache !== null) {
            $cache->delete_value('current_poll_content');
            $cache->delete_value('current_poll_result', true);
        }

        return $newId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listAll(): array
    {
        return NexusDB::table('polls')
            ->orderByDesc('id')
            ->get(['id', 'added', 'question'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findWithOptions(int $id): ?array
    {
        $row = NexusDB::table('polls')->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    public static function countAnswers(int $pollId): int
    {
        return (int) NexusDB::table('pollanswers')
            ->where('pollid', $pollId)
            ->where('selection', '<', 20)
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function answers(int $pollId, int $offset, int $perPage): array
    {
        return NexusDB::table('pollanswers')
            ->leftJoin('users', 'pollanswers.userid', '=', 'users.id')
            ->where('pollanswers.pollid', $pollId)
            ->where('pollanswers.selection', '<', 20)
            ->orderBy('users.username')
            ->offset($offset)
            ->limit($perPage)
            ->get(['pollanswers.*', 'users.username'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $answers
     * @return array<int, string>
     */
    public static function userDisplayMap(array $answers): array
    {
        $ids = array_filter(array_unique(array_column($answers, 'userid')));
        $map = [];
        foreach ($ids as $id) {
            $uid = (int) $id;
            if ($uid > 0) {
                $map[$uid] = UserDisplay::username($uid);
            }
        }

        return $map;
    }
}
