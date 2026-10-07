<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InviteValid;
use App\Exceptions\AuthenticationException;
use App\Models\Invite;
use App\Repositories\InviteRepository;
use App\Repositories\MessageRepository;
use App\Support\Cache;
use App\Support\Locale;

/**
 * Validates and consumes invitation codes during registration.
 */
class InviteValidator
{
    public function __construct(
        private readonly OutboxService $outboxService,
        private readonly InviteRepository $inviteRepository,
        private readonly MessageRepository $messageRepository,
    ) {}

    /**
     * @throws AuthenticationException
     */
    public function validate(string $code, int $inviter, string $langFolder): Invite
    {
        if ($code === '') {
            throw new AuthenticationException(
                __('signup.std_error').': '.__('signup.std_uninvited')
            );
        }

        $invite = $this->inviteRepository->findValidByHash($code);

        if (! $invite) {
            throw new AuthenticationException(__('signup.std_uninvited'));
        }

        if ((int) $invite->inviter !== $inviter) {
            $this->inviteRepository->markInvalid((int) $invite->id);
            throw new AuthenticationException(Locale::trans('invite.invalid_inviter', [], $langFolder));
        }

        return $invite;
    }

    public function consume(Invite $invite, int $userId, string $email, string $username): void
    {
        $this->inviteRepository->markConsumed((int) $invite->id, [
            'valid' => InviteValid::NO->value,
            'invitee_register_uid' => $userId,
            'invitee_register_email' => $email,
            'invitee_register_username' => $username,
        ]);

        // T-24: Record invite consumed event in outbox
        $this->outboxService->recordInviteConsumed(
            inviteId: (int) $invite->id,
            inviterId: (int) $invite->inviter,
            inviteeId: $userId,
            inviteData: [
                'email' => $email,
                'username' => $username,
            ],
        );

        $inviter = (int) $invite->inviter;
        $locale = Locale::userLocale($inviter);
        $subject = Locale::trans('user.msg_invited_user_has_registered', [], $locale);
        $msg = Locale::trans('user.msg_user_you_invited', [], $locale)
            .$username
            .Locale::trans('user.msg_has_registered', [], $locale);

        $this->messageRepository->add([
            'sender' => null,
            'receiver' => $inviter,
            'subject' => $subject,
            'added' => now()->toDateTimeString(),
            'msg' => $msg,
        ]);

        Cache::clearUser($inviter, '');
    }
}
