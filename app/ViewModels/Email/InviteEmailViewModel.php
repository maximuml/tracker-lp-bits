<?php

declare(strict_types=1);

namespace App\ViewModels\Email;

use App\ViewModels\ViewModel;

/**
 * ViewModel for the invitation email body (emails/invite.blade.php).
 *
 * Assembled by SystemBulkController when delivering `takeinvite` mail.
 */
final class InviteEmailViewModel extends ViewModel
{
    public function __construct(
        public readonly string $senderUsername,
        public readonly string $signupUrl,
        public readonly string $inviteTimeout,
        public readonly string $personalBody,
        public readonly string $siteName,
        public readonly string $reportMail,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
