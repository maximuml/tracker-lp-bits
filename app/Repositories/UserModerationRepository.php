<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\Permission;
use App\Enums\ModelEventEnum;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserClass as UserClassEnum;
use App\Enums\UserEnabled;
use App\Enums\UserStatus;
use App\Exceptions\InsufficientPermissionException;
use App\Exceptions\NexusException;
use App\Models\Invite;
use App\Models\Message;
use App\Models\User;
use App\Models\UserBanLog;
use App\Models\UserModifyLog;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\Environment;
use App\Support\Events;
use App\Support\Format;
use App\Support\Locale;
use App\Support\Logger;
use Carbon\Carbon;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Facades\DB;

/**
 * Handles user moderation and administration operations.
 *
 * Extracted from UserRepository to reduce god-object surface area.
 */
class UserModerationRepository extends BaseRepository
{
    /**
     * @param  mixed  $uid
     * @param  mixed  $reason
     * @return mixed
     */
    public function disableUser(User $operator, $uid, $reason = '')
    {
        $targetUser = User::query()->findOrFail((int) $uid, ['id', 'enabled', 'username', 'class']);
        if ($targetUser->enabled == UserEnabled::NO->value) {
            throw new NexusException('Already disabled !');
        }
        if (empty($reason)) {
            $reason = Locale::trans('user.disable_by_admin', [], null);
        }
        $this->checkPermission($operator, $targetUser);
        $banLog = [
            'uid' => $uid,
            'username' => $targetUser->username,
            'reason' => $reason,
            'operator' => $operator->id,
        ];
        $modCommentText = sprintf('%s - Disable by %s, reason: %s.', now()->format('Y-m-d'), $operator->username, $reason);
        DB::transaction(function () use ($targetUser, $banLog, $modCommentText) {
            $targetUser->updateWithModComment(['enabled' => UserEnabled::NO->value], $modCommentText);
            UserBanLog::query()->create($banLog);
        });
        Logger::writeWithContext((string) "user: {$uid}, {$modCommentText}", (string) 'info', (bool) false);
        $this->clearCache($targetUser);
        Events::fire('user_disabled', $targetUser, null);

        return true;
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $reason
     * @return mixed
     */
    public function enableUser(User $operator, $uid, $reason = '')
    {
        $targetUser = User::query()->findOrFail((int) $uid, ['id', 'enabled', 'username', 'class']);
        if ($targetUser->enabled == UserEnabled::YES->value) {
            throw new NexusException('Already enabled !');
        }
        $this->checkPermission($operator, $targetUser);
        $update = [
            'enabled' => UserEnabled::YES->value,
        ];
        if ($targetUser->class == UserClassEnum::PEASANT->value) {
            // warn users until 30 days
            $until = now()->addDays(30)->toDateTimeString();
            $update['leechwarn'] = 'yes';
            $update['leechwarnuntil'] = $until;
        } else {
            $update['leechwarn'] = 'no';
            $update['leechwarnuntil'] = null;
        }
        $modCommentText = sprintf('%s - Enable by %s, reason: %s', now()->format('Y-m-d'), $operator->username, $reason);
        $targetUser->updateWithModComment($update, $modCommentText);
        Logger::writeWithContext((string) ("user: {$uid}, {$modCommentText}, update: ".json_encode($update)), (string) 'info', (bool) false);
        $this->clearCache($targetUser);
        Events::fire('user_enabled', $targetUser, null);
        $this->setEnableLatelyCache($targetUser->id);

        return true;
    }

    private function setEnableLatelyCache(int $userId): void
    {
        CacheFacade::put(User::getUserEnableLatelyCacheKey($userId), now()->toDateTimeString(), 86400);
    }

    /**
     * @return mixed
     */
    public function getModComment(int $id)
    {
        $user = User::query()->findOrFail((int) $id, ['modcomment']);

        return $user->modcomment;
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $action
     * @param  mixed  $field
     * @param  mixed  $value
     * @param  mixed  $reason
     */
    public function incrementDecrement(User $operator, $uid, $action, $field, $value, $reason = ''): bool
    {
        $fieldMap = [
            'uploaded' => 'uploaded',
            'downloaded' => 'downloaded',
            'seedbonus' => 'seedbonus',
            'invites' => 'invites',
            'attendance_card' => 'attendance_card',
        ];
        if (! isset($fieldMap[$field])) {
            throw new \InvalidArgumentException("Invalid field: $field, only support: ".implode(', ', array_keys($fieldMap)));
        }
        $sourceField = $fieldMap[$field];
        $targetUser = User::query()->findOrFail((int) $uid, User::$commonFields);
        $this->checkPermission($operator, $targetUser);
        $old = (float) $targetUser->{$sourceField};
        $valueAtomic = (float) $value;
        $formatSize = false;
        if (in_array($field, ['uploaded', 'downloaded'])) {
            // Frontend unit: GB
            $valueAtomic = $valueAtomic * 1024 * 1024 * 1024;
            $formatSize = true;
        }
        if ($action == 'Increment') {
            $new = $old + abs($valueAtomic);
        } elseif ($action == 'Decrement') {
            $new = $old - abs($valueAtomic);
        } else {
            throw new \InvalidArgumentException("Invalid action: $action.");
        }
        if ($new < 0) {
            throw new NexusException("New value($new) lte 0");
        }
        // for administrator, use english
        $modCommentText = Locale::trans('message.field_value_change_message_body', ['field' => Locale::trans("user.labels.{$sourceField}", [], 'en'), 'operator' => $operator->username, 'old' => $formatSize ? Format::size((float) $old) : $old, 'new' => $formatSize ? Format::size((float) $new) : $new, 'reason' => $reason], 'en');
        Logger::writeWithContext((string) "user: {$uid}, {$modCommentText}", (string) 'alert', (bool) false);
        $update = [
            $sourceField => $new,
            //            'modcomment' => DB::raw("if(modcomment = '', '$modCommentText', concat_ws('\n', '$modCommentText', modcomment))"),
        ];
        $locale = $targetUser->locale;
        $fieldLabel = Locale::trans("user.labels.{$sourceField}", [], $locale);
        $msg = Locale::trans('message.field_value_change_message_body', ['field' => $fieldLabel, 'operator' => $operator->username, 'old' => $formatSize ? Format::size((float) $old) : $old, 'new' => $formatSize ? Format::size((float) $new) : $new, 'reason' => $reason], $locale);
        $message = [
            'sender' => 0,
            'receiver' => $targetUser->id,
            'subject' => Locale::trans('message.field_value_change_message_subject', ['field' => $fieldLabel], $locale),
            'msg' => $msg,
            'added' => Carbon::now(),
        ];
        DB::transaction(function () use ($uid, $sourceField, $old, $update, $message, $modCommentText) {
            $affectedRows = User::query()
                ->where('id', $uid)
                ->where($sourceField, $old)
                ->update($update);
            if ($affectedRows != 1) {
                throw new \RuntimeException("Change fail, affected rows != 1($affectedRows)");
            }
            Message::query()->insert($message);
            UserModifyLog::query()->insert([
                'user_id' => $uid,
                'content' => $modCommentText,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        });
        $this->clearCache($targetUser);

        return true;
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $uid
     */
    public function removeLeechWarn($operator, $uid): bool
    {
        $operator = $this->getUser($operator);
        $user = User::query()->findOrFail((int) $uid, User::$commonFields);
        $this->checkPermission($operator, $user);
        $this->clearCache($user);
        $user->leechwarn = 'no';
        $user->leechwarnuntil = null;

        return $user->save();
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $uid
     */
    public function removeTwoStepAuthentication($operator, $uid): bool
    {
        if (! $operator->canAccessAdmin()) {
            throw new \RuntimeException('No permission.');
        }
        $user = User::query()->findOrFail((int) $uid, User::$commonFields);
        $this->checkPermission($operator, $user);
        $this->clearCache($user);
        $user->two_step_secret = '';

        return $user->save();
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $user
     * @param  mixed  $status
     * @param  mixed  $disableReasonKey
     * @return mixed
     */
    public function updateDownloadPrivileges($operator, $user, $status, $disableReasonKey = null)
    {
        if (! in_array($status, ['yes', 'no'])) {
            throw new \InvalidArgumentException("Invalid status: $status");
        }
        $targetUser = $this->getUser($user);
        if ($targetUser === null) {
            throw new \InvalidArgumentException('Target user not found');
        }
        $operator = $this->getUser($operator);
        $operatorUsername = 'System';
        if ($operator) {
            $operatorUsername = $operator->username;
            $this->checkPermission($operator, $targetUser);
        }
        $message = [
            'added' => now(),
            'receiver' => $targetUser->id,
        ];
        if ($status == 'no') {
            $update = ['downloadpos' => 'no'];
            $modComment = date('Y-m-d').' - Download disable by '.$operatorUsername;
            $msgTransPrefix = 'message.download_disable';
            if ($disableReasonKey !== null) {
                $msgTransPrefix .= "_$disableReasonKey";
            }
            $message['subject'] = Locale::trans("{$msgTransPrefix}.subject", [], $targetUser->locale);
            $message['msg'] = Locale::trans("{$msgTransPrefix}.body", ['operator' => $operatorUsername], $targetUser->locale);
        } else {
            $update = ['downloadpos' => 'yes'];
            $modComment = date('Y-m-d').' - Download enable by '.$operatorUsername;
            $message['subject'] = Locale::trans('message.download_enable.subject', [], $targetUser->locale);
            $message['msg'] = Locale::trans('message.download_enable.body', ['operator' => $operatorUsername], $targetUser->locale);
        }
        $result = DB::transaction(function () use ($targetUser, $update, $modComment, $message) {
            Message::add($message);

            return $targetUser->updateWithModComment($update, $modComment);
        });
        $this->clearCache($targetUser);

        return $result;
    }

    /**
     * Mirror the legacy modtask uploadpos toggle.
     *
     * @param  mixed  $operator
     * @param  mixed  $user
     * @param  string  $status  'yes' or 'no'
     * @return mixed
     */
    public function updateUploadPrivileges($operator, $user, string $status)
    {
        if (! in_array($status, ['yes', 'no'])) {
            throw new \InvalidArgumentException("Invalid status: $status");
        }
        $targetUser = $this->getUser($user);
        if ($targetUser === null) {
            throw new \InvalidArgumentException('Target user not found');
        }
        $operator = $this->getUser($operator);
        $operatorUsername = $operator ? $operator->username : 'System';
        if ($operator) {
            $this->checkPermission($operator, $targetUser);
        }
        $message = ['added' => now(), 'receiver' => $targetUser->id];
        if ($status == 'no') {
            $update = ['uploadpos' => 'no'];
            $modComment = date('Y-m-d').' - Upload disable by '.$operatorUsername;
            $message['subject'] = Locale::trans('message.upload_disable.subject', [], $targetUser->locale);
            $message['msg'] = Locale::trans('message.upload_disable.body', ['operator' => $operatorUsername], $targetUser->locale);
        } else {
            $update = ['uploadpos' => 'yes'];
            $modComment = date('Y-m-d').' - Upload enable by '.$operatorUsername;
            $message['subject'] = Locale::trans('message.upload_enable.subject', [], $targetUser->locale);
            $message['msg'] = Locale::trans('message.upload_enable.body', ['operator' => $operatorUsername], $targetUser->locale);
        }
        $result = DB::transaction(function () use ($targetUser, $update, $modComment, $message) {
            Message::add($message);

            return $targetUser->updateWithModComment($update, $modComment);
        });
        $this->clearCache($targetUser);

        return $result;
    }

    /**
     * Mirror the legacy modtask forumpost toggle.
     *
     * @param  mixed  $operator
     * @param  mixed  $user
     * @param  string  $status  'yes' or 'no'
     * @return mixed
     */
    public function updateForumPost($operator, $user, string $status)
    {
        if (! in_array($status, ['yes', 'no'])) {
            throw new \InvalidArgumentException("Invalid status: $status");
        }
        $targetUser = $this->getUser($user);
        if ($targetUser === null) {
            throw new \InvalidArgumentException('Target user not found');
        }
        $operator = $this->getUser($operator);
        $operatorUsername = $operator ? $operator->username : 'System';
        if ($operator) {
            $this->checkPermission($operator, $targetUser);
        }
        $message = ['added' => now(), 'receiver' => $targetUser->id];
        if ($status == 'no') {
            $update = ['forumpost' => 'no'];
            $modComment = date('Y-m-d').' - Forum posting disabled by '.$operatorUsername;
            $message['subject'] = Locale::trans('message.forumpost_disable.subject', [], $targetUser->locale);
            $message['msg'] = Locale::trans('message.forumpost_disable.body', ['operator' => $operatorUsername], $targetUser->locale);
        } else {
            $update = ['forumpost' => 'yes'];
            $modComment = date('Y-m-d').' - Forum posting enabled by '.$operatorUsername;
            $message['subject'] = Locale::trans('message.forumpost_enable.subject', [], $targetUser->locale);
            $message['msg'] = Locale::trans('message.forumpost_enable.body', ['operator' => $operatorUsername], $targetUser->locale);
        }
        $result = DB::transaction(function () use ($targetUser, $update, $modComment, $message) {
            Message::add($message);

            return $targetUser->updateWithModComment($update, $modComment);
        });
        $this->clearCache($targetUser);

        return $result;
    }

    /**
     * Warn a user for a given number of weeks (mirrors legacy modtask warnlength).
     *
     * @param  mixed  $operator
     * @param  mixed  $user
     * @param  int  $weeks  0 = remove warning, 255 = indefinite
     * @param  string  $reason  PM reason text
     * @return mixed
     */
    public function warnUser($operator, $user, int $weeks, string $reason = '')
    {
        $targetUser = $this->getUser($user);
        if ($targetUser === null) {
            throw new \InvalidArgumentException('Target user not found');
        }
        $operator = $this->getUser($operator);
        $operatorId = $operator ? $operator->id : 0;
        $operatorUsername = $operator ? $operator->username : 'System';
        if ($operator) {
            $this->checkPermission($operator, $targetUser);
        }
        $locale = $targetUser->locale;
        $update = [];
        $message = ['added' => now(), 'receiver' => $targetUser->id, 'sender' => 0];

        if ($weeks === 0) {
            $update['warned'] = 'no';
            $update['warneduntil'] = null;
            $message['subject'] = Locale::trans('user.msg_warn_removed', [], $locale);
            $message['msg'] = Locale::trans('user.msg_your_warning_removed_by', [], $locale).$operatorUsername.'.';
        } else {
            $update['warned'] = 'yes';
            $update['lastwarned'] = now()->toDateTimeString();
            $update['warnedby'] = $operatorId;
            $update['timeswarned'] = new Expression('timeswarned + 1');
            if ($weeks == 255) {
                $update['warneduntil'] = null;
                $msg = Locale::trans('user.msg_you_are_warned_by', [], $locale).$operatorUsername.'.'.($reason ? Locale::trans('user.msg_reason', [], $locale).$reason : '');
            } else {
                $warneduntil = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s')) + $weeks * 604800);
                $update['warneduntil'] = $warneduntil;
                $dur = $weeks.Locale::trans('user.msg_week', [], $locale).($weeks > 1 ? Locale::trans('user.msg_s', [], $locale) : '');
                $msg = Locale::trans('user.msg_you_are_warned_for', [], $locale).$dur.Locale::trans('user.msg_by', [], $locale).$operatorUsername.'.'.($reason ? Locale::trans('user.msg_reason', [], $locale).$reason : '');
            }
            $message['subject'] = Locale::trans('user.msg_you_are_warned', [], $locale);
            $message['msg'] = $msg;
        }

        $result = DB::transaction(function () use ($targetUser, $update, $message) {
            Message::add($message);
            $modComment = date('Y-m-d').' - Warning updated';

            return $targetUser->updateWithModComment($update, $modComment);
        });
        $this->clearCache($targetUser);

        return $result;
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $minAuthClass
     * @return void
     */
    private function checkPermission($operator, User $user, $minAuthClass = 'authority.prfmanage')
    {
        $operator = $this->getUser($operator);
        if ($operator === null) {
            throw new \RuntimeException('Operator not found');
        }
        if ($operator->id == $user->id) {
            return;
        }
        $permissionName = str_starts_with($minAuthClass, 'authority.')
            ? substr($minAuthClass, strlen('authority.'))
            : $minAuthClass;
        $classRequire = SiteConfig::current()->authority->permission($permissionName);
        if ($classRequire === null || $operator->class < $classRequire || $operator->class <= $user->class) {
            throw new InsufficientPermissionException;
        }
    }

    /**
     * @return mixed
     */
    private function clearCache(User $user)
    {
        Cache::clearUser($user->id, (string) $user->passkey);
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $targetUser
     * @param  mixed  $newClass
     * @param  mixed  $reason
     * @param  array<int|string, mixed>  $extra
     */
    public function changeClass($operator, $targetUser, $newClass, $reason = '', array $extra = []): bool
    {
        Permission::assertCan(PermissionEnum::USER_CHANGE_CLASS);
        $newClass = (int) $newClass;
        $operator = $this->getUser($operator);
        $targetUser = $this->getUser($targetUser);
        if ($operator === null || $targetUser === null) {
            throw new \InvalidArgumentException('Operator or target user not found');
        }
        if ($operator->class <= $targetUser->class || $operator->class <= $newClass) {
            throw new InsufficientPermissionException;
        }
        if ($targetUser->class == $newClass && $newClass != UserClassEnum::VIP->value) {
            return true;
        }
        $locale = $targetUser->locale;
        $subject = Locale::trans('user.edit_notifications.change_class.subject', [], $locale);
        $body = Locale::trans('user.edit_notifications.change_class.body', ['action' => Locale::trans('user.edit_notifications.change_class.'.($newClass > $targetUser->class ? 'promote' : 'demote'), [], null), 'new_class' => User::getClassText($newClass), 'operator' => $operator->username, 'reason' => $reason], $locale);
        $message = [
            'sender' => 0,
            'receiver' => $targetUser->id,
            'subject' => $subject,
            'msg' => $body,
            'added' => Carbon::now(),
        ];
        $userUpdates = [
            'class' => $newClass,
        ];
        if ($newClass == UserClassEnum::VIP->value) {
            if (! empty($extra['vip_added']) && in_array($extra['vip_added'], ['yes', 'no'])) {
                $userUpdates['vip_added'] = $extra['vip_added'];
            } else {
                $userUpdates['vip_added'] = 'no';
            }
            if (! empty($extra['vip_until'])) {
                $until = Carbon::parse($extra['vip_until']);
                $userUpdates['vip_until'] = $until;
            } else {
                $userUpdates['vip_until'] = null;
            }
        } else {
            $userUpdates['vip_added'] = 'no';
            $userUpdates['vip_until'] = null;
        }
        Logger::writeWithContext((string) ('userUpdates: '.json_encode($userUpdates)), (string) 'info', (bool) false);
        DB::transaction(function () use ($targetUser, $userUpdates, $message) {
            $modComment = date('Y-m-d').' - '.$message['msg'];
            if ($targetUser->class != $userUpdates['class']) {
                $targetUser->updateWithModComment($userUpdates, $modComment);
                Message::add($message);
            } else {
                $targetUser->update($userUpdates);
            }
        });
        $this->clearCache($targetUser);

        return true;
    }

    /** @param  mixed  $id */
    public function confirmUser($id): bool
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
            Events::fire(ModelEventEnum::USER_UPDATED, $user, null);
        }

        return true;
    }

    /**
     * Remove warnings from the given user IDs.
     *
     * Mirrors the legacy nowarn action: sets warned='no', warneduntil=NULL,
     * and prepends a modcomment noting who removed the warning.
     *
     * @param  array<int>  $userIds
     */
    public function removeWarnings(User $operator, array $userIds): void
    {
        $userIds = array_values(array_filter(array_map('intval', $userIds)));
        if (empty($userIds)) {
            return;
        }

        $modcomment = date('Y-m-d').' - Warning Removed By '.$operator->username;

        foreach ($userIds as $uid) {
            $user = User::query()->find($uid, ['id', 'warned', 'modcomment']);
            if ($user === null || $user->warned !== 'yes') {
                continue;
            }
            $newModcomment = $user->modcomment === '' || $user->modcomment === null
                ? $modcomment
                : $modcomment."\n".$user->modcomment;

            $user->warned = 'no';
            $user->warneduntil = null;
            $user->modcomment = $newModcomment;
            $user->save();

            Events::fire(ModelEventEnum::USER_UPDATED, $user, null);
        }
    }

    /**
     * @param  Collection<int, mixed>|int  $id
     * @param  mixed  $reasonKey
     * @return mixed
     */
    public function destroy(Collection|int $id, $reasonKey = 'user.destroy_by_admin')
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
            Events::fire(ModelEventEnum::USER_DELETED, $users->first(), null);
        }

        return true;
    }

    /**
     * @return mixed
     */
    public function addTemporaryInvite(?User $operator, int $uid, string $action, int $count, ?int $days, ?string $reason = '')
    {
        Logger::writeWithContext((string) "uid: {$uid}, action: {$action}, count: {$count}, days: {$days}, reason: {$reason}", (string) 'info', (bool) false);
        $action = strtolower($action);
        if ($count <= 0 || ($action == 'increment' && $days <= 0)) {
            throw new \InvalidArgumentException('days or count lte 0');
        }
        $targetUser = User::query()->findOrFail((int) $uid, User::$commonFields);
        if ($operator) {
            $this->checkPermission($operator, $targetUser);
        }
        $toolRep = new ToolRepository;
        $locale = $targetUser->locale;

        $changeType = Locale::trans("nexus.{$action}", [], $locale);
        $subject = Locale::trans('message.temporary_invite_change.subject', ['change_type' => $changeType], $locale);
        $body = Locale::trans('message.temporary_invite_change.body', ['change_type' => $changeType, 'count' => $count, 'operator' => $operator->username ?? '', 'reason' => $reason], $locale);
        $message = [
            'sender' => 0,
            'receiver' => $targetUser->id,
            'subject' => $subject,
            'msg' => $body,
            'added' => Carbon::now(),
        ];
        $inviteData = [];
        if ($action == 'increment') {
            $hashArr = $toolRep->generateUniqueInviteHash([], $count, $count);
            foreach ($hashArr as $hash) {
                $inviteData[] = [
                    'inviter' => $uid,
                    'invitee' => '',
                    'hash' => $hash,
                    'valid' => 0,
                    'expired_at' => Carbon::now()->addDays((int) $days),
                    'created_at' => Carbon::now(),
                ];
            }
        }
        DB::transaction(function () use ($uid, $message, $inviteData, $count, $operator) {
            if (! empty($inviteData)) {
                Invite::query()->insert($inviteData);
                Logger::writeWithContext((string) "[INSERT TEMPORARY INVITE] to {$uid}, count: {$count}", (string) 'info', (bool) false);
            } else {
                Invite::query()->where('inviter', $uid)
                    ->where('invitee', '')
                    ->orderBy('expired_at', 'asc')
                    ->limit($count)
                    ->delete();
                Logger::writeWithContext((string) "[DELETE TEMPORARY INVITE] of {$uid}, count: {$count}", (string) 'info', (bool) false);
            }
            if ($operator) {
                Message::add($message);
            }
        });

        return true;
    }

    /**
     * @return mixed
     */
    public function getInviteBtnText(int $uid)
    {
        if (! SiteConfig::current()->main->inviteSystem()) {
            throw new NexusException(Locale::trans('invite.send_deny_reasons.invite_system_closed', [], null));
        }
        if (! Permission::can(PermissionEnum::SEND_INVITE, User::findOrFail((int) $uid))) {
            $requireClass = SiteConfig::current()->authority->permission(PermissionEnum::SEND_INVITE->value);
            throw new NexusException(Locale::trans('invite.send_deny_reasons.no_permission', ['class' => User::getClassText((int) $requireClass)], null));
        }
        $userInfo = User::query()->findOrFail((int) $uid, User::$commonFields);
        $temporaryInviteCount = $userInfo->temporary_invites()->count();
        if ($userInfo->invites + $temporaryInviteCount < 1) {
            throw new NexusException(Locale::trans('invite.send_deny_reasons.invite_not_enough', [], null));
        }

        return Locale::trans('invite.send_allow_text', [], null);
    }
}
