<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\DTOs\Auth\ActorContext;
use App\Models\Message;
use App\Models\User;
use App\Services\MessageMailboxService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Tests\Attributes\TestCategory;
use Tests\Concerns\MakesCurrentUser;
use Tests\TestCase;

/**
 * Pins MessageMailboxService mailbox operations against real message rows
 * and the per-request actor (not the ambient guard).
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class MessageMailboxServiceTest extends TestCase
{
    use DatabaseTransactions;
    use MakesCurrentUser;

    private MessageMailboxService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MessageMailboxService::class);
    }

    /** @param  array<string, mixed>  $data */
    private function actingAsActor(array $data): void
    {
        $this->app->instance(ActorContext::class, $this->actor($data));
    }

    public function test_markread_marks_message_as_read(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        /** @var User $sender */
        $sender = User::factory()->create();
        $message = Message::factory()->between($sender, $owner)->create(['unread' => true]);

        $this->actingAsActor(['id' => $owner->id]);

        $request = Request::create('/web/messages', 'POST', [
            'markread' => '1',
            'id' => $message->id,
            'box' => 0,
        ]);

        $response = $this->service->handleMoveOrDel($request);

        $this->assertStringContainsString('/web/messages', $response->getTargetUrl());
        $this->assertSame(0, (int) $message->fresh()->unread);
    }

    public function test_move_moves_messages_to_box(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        /** @var User $sender */
        $sender = User::factory()->create();
        $message = Message::factory()->between($sender, $owner)->create(['location' => 1]);

        $this->actingAsActor(['id' => $owner->id]);

        $request = Request::create('/web/messages', 'POST', [
            'move' => '1',
            'id' => $message->id,
            'box' => 2,
        ]);

        $response = $this->service->handleMoveOrDel($request);

        $this->assertStringContainsString('box=2', $response->getTargetUrl());
        $this->assertSame(2, (int) $message->fresh()->location);
    }

    public function test_delete_removes_own_inbox_message(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        /** @var User $sender */
        $sender = User::factory()->create();
        $message = Message::factory()->between($sender, $owner)->create(['location' => 1]);

        $this->actingAsActor(['id' => $owner->id]);

        $request = Request::create('/web/messages', 'POST', [
            'delete' => '1',
            'id' => $message->id,
        ]);

        $response = $this->service->handleMoveOrDel($request);

        $this->assertStringContainsString('/web/messages', $response->getTargetUrl());
        $this->assertNull(Message::query()->find($message->id));
    }

    public function test_delete_other_users_message_aborts(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create();
        /** @var User $other */
        $other = User::factory()->create();
        /** @var User $sender */
        $sender = User::factory()->create();
        $message = Message::factory()->between($sender, $owner)->create(['location' => 1]);

        $this->actingAsActor(['id' => $other->id]);

        $request = Request::create('/web/messages', 'POST', [
            'delete' => '1',
            'id' => $message->id,
        ]);

        try {
            $this->service->handleMoveOrDel($request);
            $this->fail('expected HttpResponseException');
        } catch (HttpResponseException) {
            $this->assertNotNull(Message::query()->find($message->id));
        }
    }
}
