<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\DTOs\Message\StoreMessageDto;
use App\Models\Message;
use App\Models\User;
use App\Repositories\MessageRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for MessageRepository.
 *
 * Covers getUserMailboxes(), getMailboxName(), getMailboxMessages(),
 * getMessageForUser(), getMessageForForward(), markAsRead(), moveMessages(),
 * deleteSingleMessage(), deleteMultipleMessages(), getNextMailboxNumber(),
 * addMailboxes(), updateMailbox(), deleteMailbox(), getUsername(),
 * getList(), store(), update(), getDetail(), delete(), getLastPmId(),
 * getUnreadPmNotifications(), and countStaffMessage().
 */
final class MessageRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private MessageRepository $repository;

    private int $userId;

    private int $senderId;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable FK checks for the duration of the test — several tests
        // insert messages with arbitrary sender/receiver IDs that do not
        // exist in the users table.
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('messages')->delete();
        DB::table('pmboxes')->delete();
        DB::table('staffmessages')->delete();

        $this->repository = new MessageRepository;

        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $sender */
        $sender = User::factory()->create();
        $this->userId = $user->id;
        $this->senderId = $sender->id;
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        parent::tearDown();
    }

    public function test_get_user_mailboxes_returns_empty_when_none(): void
    {
        $this->assertTrue($this->repository->getUserMailboxes($this->userId)->isEmpty());
    }

    public function test_get_user_mailboxes_returns_mailboxes_ordered_by_boxnumber(): void
    {
        DB::table('pmboxes')->insert([
            ['userid' => $this->userId, 'boxnumber' => 3, 'name' => 'Box C'],
            ['userid' => $this->userId, 'boxnumber' => 1, 'name' => 'Box A'],
        ]);

        $result = $this->repository->getUserMailboxes($this->userId);
        $boxes = $result->all();

        $this->assertCount(2, $boxes);
        $this->assertSame(1, (int) $boxes[0]->boxnumber);
        $this->assertSame(3, (int) $boxes[1]->boxnumber);
    }

    public function test_get_mailbox_name_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getMailboxName($this->userId, 1));
    }

    public function test_get_mailbox_name_returns_name_when_found(): void
    {
        DB::table('pmboxes')->insert([
            'userid' => $this->userId, 'boxnumber' => 2, 'name' => 'My Box',
        ]);

        $this->assertSame('My Box', $this->repository->getMailboxName($this->userId, 2));
    }

    public function test_get_mailbox_messages_returns_count_and_messages(): void
    {
        $this->insertMessage(['receiver' => $this->userId, 'location' => 1, 'subject' => 'Msg 1']);
        $this->insertMessage(['receiver' => $this->userId, 'location' => 1, 'subject' => 'Msg 2']);

        $result = $this->repository->getMailboxMessages($this->userId, 1, '', 'all', null, 0, 10);

        $this->assertSame(2, $result['count']);
        $this->assertCount(2, $result['messages']);
    }

    public function test_get_mailbox_messages_filters_by_keyword_in_body(): void
    {
        $this->insertMessage(['receiver' => $this->userId, 'location' => 1, 'subject' => 'Subject', 'msg' => 'findme body']);
        $this->insertMessage(['receiver' => $this->userId, 'location' => 1, 'subject' => 'Other', 'msg' => 'different text']);

        $result = $this->repository->getMailboxMessages($this->userId, 1, 'findme', 'body', null, 0, 10);
        $messages = $result['messages']->all();

        $this->assertSame(1, $result['count']);
        $this->assertSame('Subject', $messages[0]->subject);
    }

    public function test_get_mailbox_messages_filters_by_keyword_in_title(): void
    {
        $this->insertMessage(['receiver' => $this->userId, 'location' => 1, 'subject' => 'findme title', 'msg' => 'body']);
        $this->insertMessage(['receiver' => $this->userId, 'location' => 1, 'subject' => 'Other', 'msg' => 'body']);

        $result = $this->repository->getMailboxMessages($this->userId, 1, 'findme', 'title', null, 0, 10);

        $this->assertSame(1, $result['count']);
    }

    public function test_get_mailbox_messages_filters_unread(): void
    {
        $this->insertMessage(['receiver' => $this->userId, 'location' => 1, 'unread' => 1]);
        $this->insertMessage(['receiver' => $this->userId, 'location' => 1, 'unread' => 0]);

        $result = $this->repository->getMailboxMessages($this->userId, 1, '', 'all', true, 0, 10);

        $this->assertSame(1, $result['count']);
    }

    public function test_get_mailbox_messages_sentbox_returns_saved_sent_messages(): void
    {
        $this->insertMessage(['sender' => $this->userId, 'saved' => 1, 'subject' => 'Sent Msg']);

        $result = $this->repository->getMailboxMessages($this->userId, -1, '', 'all', null, 0, 10);
        $messages = $result['messages']->all();

        $this->assertSame(1, $result['count']);
        $this->assertSame('Sent Msg', $messages[0]->subject);
    }

    public function test_get_message_for_user_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getMessageForUser(999999, $this->userId));
    }

    public function test_get_message_for_user_returns_message_for_receiver(): void
    {
        $id = $this->insertMessage(['receiver' => $this->userId, 'subject' => 'For Receiver']);

        $message = $this->repository->getMessageForUser($id, $this->userId);

        $this->assertNotNull($message);
        $this->assertSame($id, $message->id);
    }

    public function test_get_message_for_user_returns_message_for_sender_if_saved(): void
    {
        $id = $this->insertMessage(['sender' => $this->userId, 'receiver' => 999, 'saved' => 1]);

        $message = $this->repository->getMessageForUser($id, $this->userId);

        $this->assertNotNull($message);
        $this->assertSame($id, $message->id);
    }

    public function test_get_message_for_user_returns_null_for_sender_if_not_saved(): void
    {
        $id = $this->insertMessage(['sender' => $this->userId, 'receiver' => 999, 'saved' => 0]);

        $message = $this->repository->getMessageForUser($id, $this->userId);

        $this->assertNull($message);
    }

    public function test_get_message_for_forward_returns_message_for_receiver(): void
    {
        $id = $this->insertMessage(['receiver' => $this->userId]);

        $message = $this->repository->getMessageForForward($id, $this->userId);

        $this->assertNotNull($message);
    }

    public function test_get_message_for_forward_returns_message_for_sender(): void
    {
        $id = $this->insertMessage(['sender' => $this->userId, 'receiver' => 999]);

        $message = $this->repository->getMessageForForward($id, $this->userId);

        $this->assertNotNull($message);
    }

    public function test_get_message_for_forward_returns_null_for_other_user(): void
    {
        $id = $this->insertMessage(['sender' => 888, 'receiver' => 999]);

        $this->assertNull($this->repository->getMessageForForward($id, $this->userId));
    }

    public function test_mark_as_read_updates_unread_flag(): void
    {
        $id1 = $this->insertMessage(['receiver' => $this->userId, 'unread' => 1]);
        $id2 = $this->insertMessage(['receiver' => $this->userId, 'unread' => 1]);

        $count = $this->repository->markAsRead([$id1, $id2], $this->userId);

        $this->assertSame(2, $count);
        $this->assertSame(0, (int) DB::table('messages')->where('id', $id1)->value('unread'));
    }

    public function test_mark_as_read_only_updates_receiver_messages(): void
    {
        $id = $this->insertMessage(['receiver' => 999, 'unread' => 1]);

        $count = $this->repository->markAsRead($id, $this->userId);

        $this->assertSame(0, $count);
        $this->assertSame(1, (int) DB::table('messages')->where('id', $id)->value('unread'));
    }

    public function test_move_messages_updates_location(): void
    {
        $id = $this->insertMessage(['receiver' => $this->userId, 'location' => 1]);

        $count = $this->repository->moveMessages($id, $this->userId, 5);

        $this->assertSame(1, $count);
        $this->assertSame(5, (int) DB::table('messages')->where('id', $id)->value('location'));
    }

    public function test_delete_single_message_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->deleteSingleMessage(999999, $this->userId));
    }

    public function test_delete_single_message_deletes_when_receiver_and_not_saved(): void
    {
        $id = $this->insertMessage(['receiver' => $this->userId, 'saved' => 0]);

        $result = $this->repository->deleteSingleMessage($id, $this->userId);

        $this->assertNotNull($result);
        $this->assertSame(0, DB::table('messages')->where('id', $id)->count());
    }

    public function test_delete_single_message_deletes_when_sender_and_location_zero(): void
    {
        $id = $this->insertMessage(['sender' => $this->userId, 'receiver' => 999, 'location' => 0, 'saved' => 0]);

        $result = $this->repository->deleteSingleMessage($id, $this->userId);

        $this->assertNotNull($result);
        $this->assertSame(0, DB::table('messages')->where('id', $id)->count());
    }

    public function test_delete_single_message_moves_to_deleted_when_receiver_and_saved(): void
    {
        $id = $this->insertMessage(['receiver' => $this->userId, 'saved' => 1, 'location' => 3]);

        $result = $this->repository->deleteSingleMessage($id, $this->userId);

        $this->assertNotNull($result);
        $this->assertSame(0, (int) DB::table('messages')->where('id', $id)->value('location'));
    }

    public function test_delete_single_message_unsaves_when_sender_and_location_not_zero(): void
    {
        $id = $this->insertMessage(['sender' => $this->userId, 'receiver' => 999, 'saved' => 1, 'location' => 3]);

        $result = $this->repository->deleteSingleMessage($id, $this->userId);

        $this->assertNotNull($result);
        $this->assertSame(0, (int) DB::table('messages')->where('id', $id)->value('saved'));
    }

    public function test_delete_single_message_returns_null_for_unrelated_user(): void
    {
        $id = $this->insertMessage(['sender' => 888, 'receiver' => 999, 'saved' => 0, 'location' => 1]);

        $this->assertNull($this->repository->deleteSingleMessage($id, $this->userId));
    }

    public function test_delete_multiple_messages_returns_count(): void
    {
        $id1 = $this->insertMessage(['receiver' => $this->userId, 'saved' => 0]);
        $id2 = $this->insertMessage(['receiver' => $this->userId, 'saved' => 0]);
        $id3 = $this->insertMessage(['receiver' => 999, 'saved' => 0]);

        $count = $this->repository->deleteMultipleMessages([$id1, $id2, $id3], $this->userId);

        $this->assertSame(2, $count);
    }

    public function test_get_next_mailbox_number_returns_one_when_no_mailboxes(): void
    {
        $this->assertSame(1, $this->repository->getNextMailboxNumber($this->userId));
    }

    public function test_get_next_mailbox_number_returns_max(): void
    {
        DB::table('pmboxes')->insert([
            'userid' => $this->userId, 'boxnumber' => 5, 'name' => 'Box',
        ]);

        $this->assertSame(5, $this->repository->getNextMailboxNumber($this->userId));
    }

    public function test_add_mailboxes_inserts_new_boxes(): void
    {
        $this->repository->addMailboxes($this->userId, ['First Box', 'Second Box']);

        $boxes = DB::table('pmboxes')->where('userid', $this->userId)->orderBy('boxnumber')->get()->all();
        $this->assertCount(2, $boxes);
        $this->assertSame('First Box', $boxes[0]->name);
        $this->assertSame('Second Box', $boxes[1]->name);
        $this->assertGreaterThan(1, (int) $boxes[0]->boxnumber);
    }

    public function test_add_mailboxes_skips_empty_names(): void
    {
        $this->repository->addMailboxes($this->userId, ['Valid', '', '  ', 'Also Valid']);

        $this->assertSame(2, DB::table('pmboxes')->where('userid', $this->userId)->count());
    }

    public function test_update_mailbox_updates_name(): void
    {
        $boxId = (int) DB::table('pmboxes')->insertGetId([
            'userid' => $this->userId, 'boxnumber' => 2, 'name' => 'Old Name',
        ]);

        $this->repository->updateMailbox($this->userId, $boxId, 'New Name');

        $this->assertSame('New Name', DB::table('pmboxes')->where('id', $boxId)->value('name'));
    }

    public function test_update_mailbox_does_not_affect_other_user(): void
    {
        /** @var User $user2 */
        $user2 = User::factory()->create();
        $boxId = (int) DB::table('pmboxes')->insertGetId([
            'userid' => $user2->id, 'boxnumber' => 2, 'name' => 'User2 Box',
        ]);

        $this->repository->updateMailbox($this->userId, $boxId, 'Hacked');

        $this->assertSame('User2 Box', DB::table('pmboxes')->where('id', $boxId)->value('name'));
    }

    public function test_delete_mailbox_removes_box(): void
    {
        $boxId = (int) DB::table('pmboxes')->insertGetId([
            'userid' => $this->userId, 'boxnumber' => 3, 'name' => 'Delete Me',
        ]);

        $this->repository->deleteMailbox($this->userId, $boxId, 3);

        $this->assertSame(0, DB::table('pmboxes')->where('id', $boxId)->count());
    }

    public function test_get_username_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getUsername(999999));
    }

    public function test_get_username_returns_name_when_found(): void
    {
        $name = DB::table('users')->where('id', $this->userId)->value('username');

        $this->assertSame($name, $this->repository->getUsername($this->userId));
    }

    public function test_get_list_returns_paginated_messages(): void
    {
        $this->insertMessage(['receiver' => $this->userId, 'subject' => 'List Msg 1']);
        $this->insertMessage(['receiver' => $this->userId, 'subject' => 'List Msg 2']);

        $paginator = $this->repository->getList([]);

        $this->assertGreaterThan(0, $paginator->total());
    }

    public function test_store_creates_message_from_dto(): void
    {
        $dto = new StoreMessageDto(
            receiver: $this->userId,
            subject: 'Stored Subject',
            msg: 'Stored body',
            sender: $this->senderId,
            added: now()->toDateTimeString(),
        );

        $message = $this->repository->store($dto);

        $this->assertInstanceOf(Message::class, $message);
        $this->assertSame('Stored Subject', $message->subject);
        $this->assertSame($this->userId, $message->receiver);
        $this->assertSame($this->senderId, $message->sender);
    }

    public function test_update_modifies_message(): void
    {
        $id = $this->insertMessage(['receiver' => $this->userId, 'subject' => 'Old Subject']);

        $message = $this->repository->update(['subject' => 'New Subject'], $id);

        $this->assertSame('New Subject', $message->subject);
    }

    public function test_get_detail_returns_message(): void
    {
        $id = $this->insertMessage(['receiver' => $this->userId, 'subject' => 'Detail Msg']);

        $message = $this->repository->getDetail($id);

        $this->assertInstanceOf(Message::class, $message);
        $this->assertSame($id, $message->id);
    }

    public function test_delete_removes_message(): void
    {
        $id = $this->insertMessage(['receiver' => $this->userId]);

        $result = $this->repository->delete($id);

        $this->assertTrue((bool) $result);
        $this->assertSame(0, DB::table('messages')->where('id', $id)->count());
    }

    public function test_get_last_pm_id_returns_zero_when_no_messages(): void
    {
        $this->assertSame(0, $this->repository->getLastPmId($this->userId));
    }

    public function test_get_last_pm_id_returns_max_id(): void
    {
        $id1 = $this->insertMessage(['receiver' => $this->userId]);
        $id2 = $this->insertMessage(['receiver' => $this->userId]);

        $this->assertSame($id2, $this->repository->getLastPmId($this->userId));
    }

    public function test_get_unread_pm_notifications_returns_empty_when_none(): void
    {
        $this->assertSame([], $this->repository->getUnreadPmNotifications($this->userId, 0, 10));
    }

    public function test_get_unread_pm_notifications_returns_unread_after_last_pm_id(): void
    {
        $id1 = $this->insertMessage(['receiver' => $this->userId, 'unread' => 1, 'subject' => 'First']);
        $id2 = $this->insertMessage(['receiver' => $this->userId, 'unread' => 1, 'subject' => 'Second']);

        $notifications = $this->repository->getUnreadPmNotifications($this->userId, $id1, 10);

        $this->assertCount(1, $notifications);
        $this->assertSame('pm_'.$id2, $notifications[0]['id']);
        $this->assertSame('Second', $notifications[0]['body']);
    }

    public function test_get_unread_pm_notifications_excludes_read_messages(): void
    {
        $this->insertMessage(['receiver' => $this->userId, 'unread' => 0, 'subject' => 'Read']);
        $this->insertMessage(['receiver' => $this->userId, 'unread' => 1, 'subject' => 'Unread']);

        $notifications = $this->repository->getUnreadPmNotifications($this->userId, 0, 10);

        $this->assertCount(1, $notifications);
        $this->assertSame('Unread', $notifications[0]['body']);
    }

    public function test_count_staff_message_returns_zero_when_none(): void
    {
        $this->assertSame(0, $this->repository->countStaffMessage($this->userId));
    }

    public function test_count_staff_message_returns_count(): void
    {
        // Use an admin user so Permission::can(STAFF_MEMBER) returns true,
        // bypassing the permission filter in buildStaffMessageQuery.
        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        DB::table('staffmessages')->insert([
            'sender' => $this->senderId,
            'subject' => 'Staff Msg',
            'msg' => 'test',
            'added' => now()->toDateTimeString(),
            'answered' => 0,
            'permission' => '',
        ]);

        $this->assertSame(1, $this->repository->countStaffMessage($admin->id));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertMessage(array $overrides = []): int
    {
        return (int) DB::table('messages')->insertGetId(array_merge([
            'sender' => $this->senderId,
            'receiver' => $this->userId,
            'added' => now()->toDateTimeString(),
            'subject' => 'Test Subject',
            'msg' => 'Test body',
            'unread' => 1,
            'location' => 1,
            'saved' => 0,
        ], $overrides));
    }
}
