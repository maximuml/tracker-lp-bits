<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserClass as UserClassEnum;
use App\Events\UserDisabled;
use App\Events\UserEnabled;
use App\Events\UserUpdated;
use App\Exceptions\InsufficientPermissionException;
use App\Exceptions\NexusException;
use App\Models\Message;
use App\Models\User;
use App\Services\ModerationService;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\Locale;
use App\Support\Logger;
use Carbon\Carbon;
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
    public function __construct(
        private readonly UserModerationAccountCommand $account,
        private readonly UserModerationCommentCommand $comment,
        private readonly UserModerationInviteCommand $invite,
        private readonly ModerationService $moderationService = new ModerationService,
    ) {}

    /**
     * @param  mixed  $uid
     * @param  mixed  $reason
     * @return mixed
     */
    public function disableUser(User $operator, $uid, $reason = '')
    {
        $targetUser = User::query()->findOrFail((int) $uid, ['id', 'enabled', 'username', 'class']);
        if (! $targetUser->enabled) {
            throw new NexusException('Already disabled !');
        }
        if (empty($reason)) {
            $reason = Locale::trans('user.disable_by_admin', [], null);
        }
        $this->checkPermission($operator, $targetUser);
        $this->account->disable($operator, $targetUser, $reason);
        $this->clearCache($targetUser);
        event(new UserDisabled($targetUser));

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
        if ($targetUser->enabled) {
            throw new NexusException('Already enabled !');
        }
        $this->checkPermission($operator, $targetUser);
        $this->account->enable($operator, $targetUser, $reason);
        $this->clearCache($targetUser);
        event(new UserEnabled($targetUser));
        $this->setEnableLatelyCache($targetUser->id);

        return true;
    }

    /**
     * Get the latest moderation comment for a user.
     *
     * Since the `modcomment` column was dropped in migration
     * 2025_01_18_235747, moderation comments are stored in
     * `user_modify_logs` via the `modifyLogs()` relationship.
     *
     * @return string|null
     */
    public function getModComment(int $id)
    {
        return $this->comment->getModComment($id);
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
        $targetUser = User::query()->findOrFail((int) $uid, User::$commonFields);
        $this->checkPermission($operator, $targetUser);
        $this->comment->incrementDecrement($operator, $targetUser, $action, $field, $value, $reason);
        $this->clearCache($targetUser);

        return true;
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $uid
     */
    public function removeLeechWarn($operator, $uid): bool
    {
        return $this->moderationService->removeLeechWarn($operator, $uid);
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $uid
     */
    public function removeTwoStepAuthentication($operator, $uid): bool
    {
        return $this->moderationService->removeTwoStepAuthentication($operator, $uid);
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $user
     * @param  mixed  $disableReasonKey
     * @return mixed
     */
    public function updateDownloadPrivileges($operator, $user, bool $status, $disableReasonKey = null)
    {
        return $this->moderationService->updateDownloadPrivileges($operator, $user, $status, $disableReasonKey);
    }

    /**
     * Mirror the legacy modtask uploadpos toggle.
     *
     * @param  mixed  $operator
     * @param  mixed  $user
     * @return mixed
     */
    public function updateUploadPrivileges($operator, $user, bool $status)
    {
        return $this->moderationService->updateUploadPrivileges($operator, $user, $status);
    }

    /**
     * Mirror the legacy modtask forumpost toggle.
     *
     * @param  mixed  $operator
     * @param  mixed  $user
     * @return mixed
     */
    public function updateForumPost($operator, $user, bool $status)
    {
        return $this->moderationService->updateForumPost($operator, $user, $status);
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
        return $this->moderationService->warnUser($operator, $user, $weeks, $reason);
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
            $user = User::query()->find($uid, ['id', 'warned']);
            if ($user === null || ! $user->warned) {
                continue;
            }

            DB::table('users')->where('id', $uid)->update([
                'warned' => 0,
                'warneduntil' => null,
            ]);
            $user->modifyLogs()->create(['content' => $modcomment]);
            $user->warned = false;
            $user->warneduntil = null;

            event(new UserUpdated($user));
        }
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
            'sender' => null,
            'receiver' => $targetUser->id,
            'subject' => $subject,
            'msg' => $body,
            'added' => Carbon::now(),
        ];
        $userUpdates = [
            'class' => $newClass,
        ];
        if ($newClass == UserClassEnum::VIP->value) {
            if (array_key_exists('vip_added', $extra)) {
                $userUpdates['vip_added'] = (bool) $extra['vip_added'];
            } else {
                $userUpdates['vip_added'] = false;
            }
            if (! empty($extra['vip_until'])) {
                $until = Carbon::parse($extra['vip_until']);
                $userUpdates['vip_until'] = $until;
            } else {
                $userUpdates['vip_until'] = null;
            }
        } else {
            $userUpdates['vip_added'] = false;
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

    /**
     * @param  Collection<int, mixed>|int  $id
     * @param  mixed  $reasonKey
     * @return mixed
     */
    public function destroy(Collection|int $id, $reasonKey = 'user.destroy_by_admin')
    {
        return $this->account->destroy($id, $reasonKey);
    }

    /** @param  mixed  $id */
    public function confirmUser($id): bool
    {
        return $this->account->confirm($id);
    }

    /**
     * @return mixed
     */
    public function addTemporaryInvite(?User $operator, int $uid, string $action, int $count, ?int $days, ?string $reason = '')
    {
        $targetUser = User::query()->findOrFail((int) $uid, User::$commonFields);
        if ($operator) {
            $this->checkPermission($operator, $targetUser);
        }

        return $this->invite->addTemporaryInvite($operator, $targetUser, $action, $count, $days, $reason);
    }

    /**
     * @return mixed
     */
    public function getInviteBtnText(int $uid)
    {
        return $this->invite->getInviteBtnText($uid);
    }

    private function setEnableLatelyCache(int $userId): void
    {
        CacheFacade::put(User::getUserEnableLatelyCacheKey($userId), now()->toDateTimeString(), 86400);
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $minAuthClass
     */
    private function checkPermission($operator, User $user, $minAuthClass = 'authority.prfmanage'): void
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
}
