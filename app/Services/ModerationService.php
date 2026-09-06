<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InsufficientPermissionException;
use App\Models\Message;
use App\Models\User;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\Locale;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * User moderation service: privileges, warnings, and leech-warn removal.
 *
 * Extracted from UserModerationRepository to reduce god-object surface area.
 * Covers privilege toggles (download/upload/forum), user warnings, and
 * leech-warn / two-step-authentication removal.
 */
final class ModerationService
{
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
        $user->leechwarn = false;
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
     * @param  mixed  $disableReasonKey
     * @return mixed
     */
    public function updateDownloadPrivileges($operator, $user, bool $status, $disableReasonKey = null)
    {
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
        if (! $status) {
            $update = ['downloadpos' => false];
            $modComment = date('Y-m-d').' - Download disable by '.$operatorUsername;
            $msgTransPrefix = 'message.download_disable';
            if ($disableReasonKey !== null) {
                $msgTransPrefix .= "_$disableReasonKey";
            }
            $message['subject'] = Locale::trans("{$msgTransPrefix}.subject", [], $targetUser->locale);
            $message['msg'] = Locale::trans("{$msgTransPrefix}.body", ['operator' => $operatorUsername], $targetUser->locale);
        } else {
            $update = ['downloadpos' => true];
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
     * @return mixed
     */
    public function updateUploadPrivileges($operator, $user, bool $status)
    {
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
        if (! $status) {
            $update = ['uploadpos' => false];
            $modComment = date('Y-m-d').' - Upload disable by '.$operatorUsername;
            $message['subject'] = Locale::trans('message.upload_disable.subject', [], $targetUser->locale);
            $message['msg'] = Locale::trans('message.upload_disable.body', ['operator' => $operatorUsername], $targetUser->locale);
        } else {
            $update = ['uploadpos' => true];
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
     * @return mixed
     */
    public function updateForumPost($operator, $user, bool $status)
    {
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
        if (! $status) {
            $update = ['forumpost' => false];
            $modComment = date('Y-m-d').' - Forum posting disabled by '.$operatorUsername;
            $message['subject'] = Locale::trans('message.forumpost_disable.subject', [], $targetUser->locale);
            $message['msg'] = Locale::trans('message.forumpost_disable.body', ['operator' => $operatorUsername], $targetUser->locale);
        } else {
            $update = ['forumpost' => true];
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
        $message = ['added' => now(), 'receiver' => $targetUser->id, 'sender' => null];

        if ($weeks === 0) {
            $update['warned'] = false;
            $update['warneduntil'] = null;
            $message['subject'] = Locale::trans('user.msg_warn_removed', [], $locale);
            $message['msg'] = Locale::trans('user.msg_your_warning_removed_by', [], $locale).$operatorUsername.'.';
        } else {
            $update['warned'] = true;
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
     * @param  mixed  $user
     * @param  mixed  $fields
     */
    private function getUser($user, $fields = null): ?User
    {
        if ($user === null) {
            return null;
        }
        if ($user instanceof User) {
            return $user;
        }
        if ($fields === null) {
            $fields = User::$commonFields;
        }

        return User::query()->findOrFail(intval($user), $fields);
    }
}
