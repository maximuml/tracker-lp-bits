<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserAcceptPms;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * W1-03: Authorization policy for message ownership and mutations.
 * Extracts ownership checks that were previously inline in MessageService.
 */
class MessagePolicy extends BasePolicy
{
    /**
     * Whether the user can view a specific message.
     * Only the sender or receiver may view a message.
     */
    public function view(User $user, Message $message): bool
    {
        return (int) $message->sender === (int) $user->id
            || (int) $message->receiver === (int) $user->id;
    }

    /**
     * Whether the user can delete a message from their inbox.
     * Only the receiver may delete from inbox.
     */
    public function deleteInbox(User $user, Message $message): bool
    {
        return (int) $message->receiver === (int) $user->id;
    }

    /**
     * Whether the user can delete a message from their sentbox.
     * Only the sender may delete from sentbox.
     */
    public function deleteSentbox(User $user, Message $message): bool
    {
        return (int) $message->sender === (int) $user->id;
    }

    /**
     * Whether the user can forward a message.
     * Only the sender or receiver may forward.
     */
    public function forward(User $user, Message $message): bool
    {
        return (int) $message->sender === (int) $user->id
            || (int) $message->receiver === (int) $user->id;
    }

    /**
     * Whether the user can send a message to a recipient.
     * Staff members bypass accept-pms restrictions.
     */
    public function sendTo(User $sender, User $recipient): bool
    {
        if (Permission::can(PermissionEnum::STAFF_MEMBER, $sender)) {
            return true;
        }

        if ($recipient->parked) {
            return false;
        }

        return match ($recipient->acceptpms) {
            UserAcceptPms::YES => ! $this->isBlockedBy($recipient, $sender),
            UserAcceptPms::FRIENDS => $this->isFriendOf($recipient, $sender),
            UserAcceptPms::NO => false,
            default => true,
        };
    }

    private function isBlockedBy(User $recipient, User $sender): bool
    {
        return DB::table('blocks')
            ->where('userid', $recipient->id)
            ->where('blockid', $sender->id)
            ->exists();
    }

    private function isFriendOf(User $recipient, User $sender): bool
    {
        return DB::table('friends')
            ->where('userid', $recipient->id)
            ->where('friendid', $sender->id)
            ->exists();
    }
}
