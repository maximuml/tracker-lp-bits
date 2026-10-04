<?php

declare(strict_types=1);

namespace Tests\Integration\Policies;

use App\Models\Message;
use App\Models\User;
use App\Policies\MessagePolicy;
use App\Repositories\FriendsRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class MessagePolicyTest extends TestCase
{
    use DatabaseTransactions;

    private MessagePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new MessagePolicy(new FriendsRepository);
    }

    public function test_view_allows_only_sender_or_receiver(): void
    {
        $sender = User::factory()->create(['class' => 1]);
        $receiver = User::factory()->create();
        $outsider = User::factory()->create();
        $message = new Message(['sender' => $sender->id, 'receiver' => $receiver->id]);

        $this->assertTrue($this->policy->view($sender, $message));
        $this->assertTrue($this->policy->view($receiver, $message));
        $this->assertFalse($this->policy->view($outsider, $message));
    }

    public function test_delete_inbox_allows_only_receiver(): void
    {
        $sender = User::factory()->create(['class' => 1]);
        $receiver = User::factory()->create();
        $message = new Message(['sender' => $sender->id, 'receiver' => $receiver->id]);

        $this->assertTrue($this->policy->deleteInbox($receiver, $message));
        $this->assertFalse($this->policy->deleteInbox($sender, $message));
    }

    public function test_delete_sentbox_allows_only_sender(): void
    {
        $sender = User::factory()->create(['class' => 1]);
        $receiver = User::factory()->create();
        $message = new Message(['sender' => $sender->id, 'receiver' => $receiver->id]);

        $this->assertTrue($this->policy->deleteSentbox($sender, $message));
        $this->assertFalse($this->policy->deleteSentbox($receiver, $message));
    }

    public function test_forward_allows_only_sender_or_receiver(): void
    {
        $sender = User::factory()->create(['class' => 1]);
        $receiver = User::factory()->create();
        $outsider = User::factory()->create();
        $message = new Message(['sender' => $sender->id, 'receiver' => $receiver->id]);

        $this->assertTrue($this->policy->forward($sender, $message));
        $this->assertTrue($this->policy->forward($receiver, $message));
        $this->assertFalse($this->policy->forward($outsider, $message));
    }

    public function test_send_to_allows_staff_bypass(): void
    {
        $staff = User::factory()->admin()->create();
        $recipient = User::factory()->create(['class' => 1, 'acceptpms' => 2, 'parked' => 0]);

        $this->assertTrue($this->policy->sendTo($staff, $recipient));
    }

    public function test_send_to_denies_parked_recipient(): void
    {
        $sender = User::factory()->create(['class' => 1]);
        $recipient = User::factory()->create(['class' => 1, 'parked' => 1]);

        $this->assertFalse($this->policy->sendTo($sender, $recipient));
    }

    public function test_send_to_acceptpms_yes_unless_blocked(): void
    {
        $sender = User::factory()->create(['class' => 1]);
        $recipient = User::factory()->create(['class' => 1, 'acceptpms' => 0, 'parked' => 0]);

        $this->assertTrue($this->policy->sendTo($sender, $recipient));

        DB::table('blocks')->insert(['userid' => $recipient->id, 'blockid' => $sender->id]);

        $this->assertFalse($this->policy->sendTo($sender, $recipient));
    }

    public function test_send_to_acceptpms_friends_requires_friendship(): void
    {
        $sender = User::factory()->create(['class' => 1]);
        $recipient = User::factory()->create(['class' => 1, 'acceptpms' => 1, 'parked' => 0]);

        $this->assertFalse($this->policy->sendTo($sender, $recipient));

        DB::table('friends')->insert(['userid' => $recipient->id, 'friendid' => $sender->id]);

        $this->assertTrue($this->policy->sendTo($sender, $recipient));
    }

    public function test_send_to_acceptpms_no_denies(): void
    {
        $sender = User::factory()->create(['class' => 1]);
        $recipient = User::factory()->create(['class' => 1, 'acceptpms' => 2, 'parked' => 0]);

        $this->assertFalse($this->policy->sendTo($sender, $recipient));
    }
}
