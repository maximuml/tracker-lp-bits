<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserClass as UserClassEnum;
use App\Enums\UserStatus;
use App\Events\UserDeleted;
use App\Events\UserUpdated;
use App\Models\User;
use App\Models\UserBanLog;
use App\Services\OutboxService;
use App\Support\Environment;
use App\Support\Locale;
use App\Support\Logger;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Account-lifecycle commands for user moderation (disable, enable, destroy, confirm).
 */
final class UserModerationAccountCommand
{
    public function __construct(
        private readonly OutboxService $outboxService,
    ) {}

    /**
     * @param  mixed  $reason
     */
    public function disable(User $operator, User $targetUser, $reason = ''): bool
    {
        $uid = (int) $targetUser->id;
        $banLog = [
            'uid' => $uid,
            'username' => $targetUser->username,
            'reason' => $reason,
            'operator' => $operator->id,
        ];
        $modCommentText = sprintf('%s - Disable by %s, reason: %s.', now()->format('Y-m-d'), $operator->username, $reason);
        DB::transaction(function () use ($targetUser, $banLog, $modCommentText, $operator, $reason) {
            $targetUser->updateWithModComment(['enabled' => false], $modCommentText);
            UserBanLog::query()->create($banLog);

            // T-24: Record moderation action event in outbox (same transaction)
            $this->outboxService->recordModerationAction(
                moderatorId: (int) $operator->id,
                action: 'disable',
                targetUserId: (int) $targetUser->id,
                actionData: ['reason' => $reason],
            );
        });
        Logger::writeWithContext((string) "user: {$uid}, {$modCommentText}", (string) 'info', (bool) false);

        return true;
    }

    /**
     * @param  mixed  $reason
     */
    public function enable(User $operator, User $targetUser, $reason = ''): bool
    {
        $update = [
            'enabled' => true,
        ];
        if ($targetUser->class == UserClassEnum::PEASANT->value) {
            // warn users until 30 days
            $until = now()->addDays(30)->toDateTimeString();
            $update['leechwarn'] = true;
            $update['leechwarnuntil'] = $until;
        } else {
            $update['leechwarn'] = false;
            $update['leechwarnuntil'] = null;
        }
        $modCommentText = sprintf('%s - Enable by %s, reason: %s', now()->format('Y-m-d'), $operator->username, $reason);
        $targetUser->updateWithModComment($update, $modCommentText);
        Logger::writeWithContext((string) ("user: {$targetUser->id}, {$modCommentText}, update: ".json_encode($update)), (string) 'info', (bool) false);

        return true;
    }

    /**
     * @param  Collection<int, mixed>|int  $id
     * @param  mixed  $reasonKey
     */
    public function destroy(Collection|int $id, $reasonKey = 'user.destroy_by_admin'): bool
    {
        if (! Environment::isConsole()) {
            Permission::assertCan(PermissionEnum::USER_DELETE);
        }
        if (is_int($id)) {
            $uidArr = Arr::wrap($id);
        } else {
            $uidArr = $id->pluck('id')->toArray();
        }
        $users = User::query()->with('language')->whereIn('id', $uidArr)->get();
        if ($users->isEmpty()) {
            return true;
        }
        $tables = [
            'users' => 'id',
            'hit_and_runs' => 'uid',
            'exam_users' => 'uid',
            'exam_progress' => 'uid',
            'user_metas' => 'uid',
            'user_medals' => 'uid',
            'attendance' => 'uid',
            'attendance_logs' => 'uid',
            'login_logs' => 'uid',
            'user_modify_logs' => 'user_id',
            'messages' => 'receiver',
        ];
        foreach ($tables as $table => $key) {
            DB::table($table)->whereIn($key, $uidArr)->delete();
        }
        Logger::writeWithContext((string) ('[DESTROY_USER]: '.json_encode($uidArr)), (string) 'error', (bool) false);
        $userBanLogs = [];
        foreach ($users as $user) {
            $userBanLogs[] = [
                'uid' => $user->id,
                'username' => $user->username,
                'reason' => Locale::trans($reasonKey, [], $user->locale),
            ];
        }
        UserBanLog::query()->insert($userBanLogs);
        // delete by user, make sure torrent is deleted
        DB::table('snatched')
            ->whereIn('userid', $uidArr)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('torrents')->whereColumn('torrents.id', '=', 'snatched.torrentid');
            })
            ->delete();
        if (is_int($id)) {
            event(new UserDeleted($users->first()->toArray()));
        }

        return true;
    }

    /** @param  mixed  $id */
    public function confirm($id): bool
    {
        $ids = Arr::wrap($id);
        $users = User::query()
            ->whereIn('id', $ids)
            ->where('status', UserStatus::PENDING->value)
            ->get();

        if ($users->isEmpty()) {
            return true;
        }

        $update = [
            'status' => UserStatus::CONFIRMED->value,
            'editsecret' => '',
        ];
        User::query()
            ->whereIn('id', $users->pluck('id'))
            ->update($update);

        foreach ($users as $user) {
            $user->status = UserStatus::CONFIRMED->value;
            $user->editsecret = '';
            event(new UserUpdated($user));
        }

        return true;
    }
}
