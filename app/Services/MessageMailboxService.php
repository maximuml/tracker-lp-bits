<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\MailboxRepository;
use App\Repositories\MessageRepository;
use App\Support\Cache;
use App\Support\LegacyResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * Mailbox mutation handlers (moveordel bulk actions, editmailboxes2,
 * deletemessage). Extracted from MessageService to keep both classes
 * under the 400-line ratchet.
 */
final class MessageMailboxService
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly MailboxRepository $mailboxRepository,
    ) {}

    public function handleMoveOrDel(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            LegacyResponse::abort('Error', 'Permission denied.');
        }
        if (! $user instanceof User) {
            throw new LogicException('Expected authenticated user.');
        }
        $userId = (int) $user->id;

        $pmId = (int) $request->input('id', 0);
        $pmBox = (int) $request->input('box', 0);
        /** @var array<int, mixed> $pmMessages */
        $pmMessages = (array) $request->input('messages', []);

        if ($request->has('markread')) {
            if ($pmId > 0) {
                $updated = $this->messageRepository->markAsRead($pmId, $userId);
            } else {
                if ($pmMessages === []) {
                    LegacyResponse::abort('Error', __('legacy/functions.select_at_least_one_record'));
                }
                $updated = $this->messageRepository->markAsRead($pmMessages, $userId);
            }
            Cache::clearInboxCount($userId);
            if ($updated == 0) {
                LegacyResponse::abort(__('legacy/messages.std_error'), __('legacy/messages.std_cannot_mark_messages'));
            }

            return redirect("/messages.php?action=viewmailbox&box={$pmBox}");
        }

        if ($request->has('move')) {
            if ($pmId > 0) {
                $updated = $this->messageRepository->moveMessages($pmId, $userId, $pmBox);
            } else {
                $updated = $this->messageRepository->moveMessages($pmMessages, $userId, $pmBox);
            }
            if ($updated == 0) {
                LegacyResponse::abort(__('legacy/messages.std_error'), __('legacy/messages.std_cannot_move_messages'));
            }
            Cache::clearInboxCount($userId);
            Cache::forgetWithLocales('user_'.$userId.'_outbox_count');

            return redirect("/messages.php?action=viewmailbox&box={$pmBox}");
        }

        if ($request->has('delete')) {
            if ($pmId > 0) {
                $deletedCount = $this->messageRepository->deleteSingleMessage($pmId, $userId) ? 1 : 0;
            } else {
                if ($pmMessages === []) {
                    LegacyResponse::abort(__('legacy/messages.std_error'), __('legacy/messages.std_no_message_selected'));
                }
                $deletedCount = $this->messageRepository->deleteMultipleMessages($pmMessages, $userId);
            }
            Cache::clearInboxCount($userId);
            Cache::forgetWithLocales('user_'.$userId.'_outbox_count');
            if ($deletedCount == 0) {
                LegacyResponse::abort(__('legacy/messages.std_error'), __('legacy/messages.std_cannot_delete_messages'));
            }

            return redirect('/messages.php?action=viewmailbox');
        }
        LegacyResponse::abort(__('legacy/messages.std_error'), __('legacy/messages.std_no_action'));

        return redirect('/messages.php');
    }

    public function handleEditMailboxes(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            LegacyResponse::abort('Error', 'Permission denied.');
        }
        if (! $user instanceof User) {
            throw new LogicException('Expected authenticated user.');
        }
        $userId = (int) $user->id;

        $action2 = (string) $request->input('action2', '');

        if ($action2 === 'add') {
            $this->mailboxRepository->addMailboxes($userId, [
                $request->input('new1'),
                $request->input('new2'),
                $request->input('new3'),
            ]);

            return redirect('/messages.php?action=editmailboxes');
        }

        if ($action2 === 'edit') {
            $pmBoxes = $this->mailboxRepository->getUserMailboxes($userId);
            if ($pmBoxes->isEmpty()) {
                LegacyResponse::abort(__('legacy/messages.std_error'), __('legacy/messages.text_no_mailboxes_to_edit'));
            }
            foreach ($pmBoxes as $pmBox) {
                $newValue = (string) ($request->input('edit'.$pmBox->id) ?? '');
                if ($newValue !== '' && $newValue !== $pmBox->name) {
                    $this->mailboxRepository->updateMailbox($userId, (int) $pmBox->id, $newValue);
                } elseif ($newValue === '') {
                    $this->mailboxRepository->deleteMailbox($userId, (int) $pmBox->id, (int) $pmBox->boxnumber);
                }
            }

            return redirect('/messages.php?action=editmailboxes');
        }

        LegacyResponse::abort(__('legacy/messages.std_error'), __('legacy/messages.std_no_action'));

        return redirect('/messages.php');
    }

    public function handleDeleteMessage(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            LegacyResponse::abort('Error', 'Permission denied.');
        }
        if (! $user instanceof User) {
            throw new LogicException('Expected authenticated user.');
        }
        $userId = (int) $user->id;

        $pmId = (int) $request->input('id', 0);
        $message = $this->messageRepository->deleteSingleMessage($pmId, $userId);
        if (! $message) {
            LegacyResponse::abort(__('legacy/messages.std_error'), __('legacy/messages.std_no_message_id'));
        }
        if ($message === null) {
            throw new LogicException('Expected non-null message.');
        }

        Cache::clearInboxCount($userId);
        Cache::forgetWithLocales('user_'.$userId.'_outbox_count');

        return redirect('/messages.php?action=viewmailbox&id='.(int) ($message['location'] ?? 0));
    }
}
