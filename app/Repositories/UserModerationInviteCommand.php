<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Exceptions\NexusException;
use App\Models\Invite;
use App\Models\Message;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\Locale;
use App\Support\Logger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Temporary invite administration commands.
 */
final class UserModerationInviteCommand
{
    public function __construct(
        private readonly ToolRepository $toolRepository,
    ) {}

    public function addTemporaryInvite(?User $operator, User $targetUser, string $action, int $count, ?int $days, ?string $reason = ''): bool
    {
        $uid = (int) $targetUser->id;
        Logger::writeWithContext((string) "uid: {$uid}, action: {$action}, count: {$count}, days: {$days}, reason: {$reason}", (string) 'info', (bool) false);
        $action = strtolower($action);
        if ($count <= 0 || ($action == 'increment' && $days <= 0)) {
            throw new \InvalidArgumentException('days or count lte 0');
        }
        $locale = $targetUser->locale;

        $changeType = Locale::trans("nexus.{$action}", [], $locale);
        $subject = Locale::trans('message.temporary_invite_change.subject', ['change_type' => $changeType], $locale);
        $body = Locale::trans('message.temporary_invite_change.body', ['change_type' => $changeType, 'count' => $count, 'operator' => $operator->username ?? '', 'reason' => $reason], $locale);
        $message = [
            'sender' => null,
            'receiver' => $targetUser->id,
            'subject' => $subject,
            'msg' => $body,
            'added' => Carbon::now(),
        ];
        $inviteData = [];
        if ($action == 'increment') {
            $hashArr = $this->toolRepository->generateUniqueInviteHash([], $count, $count);
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

    public function getInviteBtnText(int $uid): string
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
