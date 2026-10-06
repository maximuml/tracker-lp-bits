<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\AuthCookie;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * REL-02: SSE admission control must answer with a real HTTP status
 * before the stream starts, resume from the multi-channel cursor id in
 * Last-Event-ID, and release the slot/lock when the loop ends.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class ShoutboxSseTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('messages')->delete();
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        Redis::connection()->client()->del('shoutbox_sse_global');
    }

    protected function tearDown(): void
    {
        Redis::connection()->client()->del('shoutbox_sse_global');
        parent::tearDown();
    }

    private function cookieFor(User $user): string
    {
        return AuthCookie::buildToken(
            (int) $user->id,
            null,
            time() + 3600,
            (int) $user->auth_version,
        );
    }

    private function asUser(User $user): self
    {
        return $this->withCredentials()
            ->withUnencryptedCookie(AuthCookie::COOKIE_NAME, $this->cookieFor($user));
    }

    private function createPm(int $receiverId, int $senderId): int
    {
        return (int) DB::table('messages')->insertGetId([
            'sender' => $senderId,
            'receiver' => $receiverId,
            'added' => now()->toDateTimeString(),
            'subject' => 'hi',
            'msg' => 'body',
            'unread' => 1,
            'location' => 1,
        ]);
    }

    public function test_guest_is_forbidden(): void
    {
        $this->get('/web/shoutbox_sse?type=notifications')->assertForbidden();
    }

    public function test_saturated_global_counter_returns_real_503(): void
    {
        $user = User::factory()->create();
        Redis::connection()->client()->set('shoutbox_sse_global', 30);

        $this->asUser($user)->get('/web/shoutbox_sse?type=notifications')
            ->assertStatus(503);

        // The refused connection must not leak a slot.
        $this->assertSame(30, (int) Redis::connection()->client()->get('shoutbox_sse_global'));
    }

    public function test_held_slot_is_taken_over_by_newer_stream(): void
    {
        // Latest-wins: a stale slot must not 429 a fresh stream (a quick
        // page navigation would otherwise be rejected until the displaced
        // loop notices the client abort — which can take its whole
        // bounded lifetime behind a buffering proxy).
        $user = User::factory()->create();
        $key = 'sse:notifications:'.(int) $user->id;
        $redis = Redis::connection()->client();
        $redis->set($key, 'foreign-token');

        $this->asUser($user)
            ->get('/web/shoutbox_sse?type=notifications&loops=1&interval=0')
            ->assertOk()
            ->streamedContent();

        // The new stream owned the slot and released it — the displaced
        // foreign token was overwritten, then deleted on clean exit.
        $this->assertFalse($redis->get($key));
    }

    public function test_stream_emits_notifications_event_with_json_cursor_id(): void
    {
        $user = User::factory()->create();
        $sender = User::factory()->create();
        $pmId = $this->createPm((int) $user->id, (int) $sender->id);

        $response = $this->asUser($user)
            ->get('/web/shoutbox_sse?type=notifications&loops=1&interval=0&last_pm_id='.($pmId - 1));

        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertStringContainsString('event: notifications', $body);
        $this->assertStringContainsString('"id":"pm_'.$pmId.'"', $body);
        // The event id carries every channel cursor, not just pm.
        $this->assertMatchesRegularExpression('/id: \{"pm":\d+,"shout":\d+,"comment":\d+,"topic_reply":\d+,"staff":\d+\}/', $body);
    }

    public function test_json_last_event_id_resumes_without_redelivery(): void
    {
        $user = User::factory()->create();
        $sender = User::factory()->create();
        $pmId = $this->createPm((int) $user->id, (int) $sender->id);

        // Reconnect with only Last-Event-ID — no query params. The JSON
        // cursor must suppress re-delivery of the already-delivered pm.
        $response = $this->asUser($user)
            ->withHeader('Last-Event-ID', '{"pm":'.$pmId.',"shout":0,"comment":0,"topic_reply":0,"staff":0}')
            ->get('/web/shoutbox_sse?type=notifications&loops=1&interval=0');

        $body = $response->streamedContent();
        $this->assertStringNotContainsString('event: notifications', $body);
        $this->assertStringContainsString('event: ping', $body);
    }

    public function test_legacy_numeric_last_event_id_still_covers_pm(): void
    {
        $user = User::factory()->create();
        $sender = User::factory()->create();
        $pmId = $this->createPm((int) $user->id, (int) $sender->id);

        $response = $this->asUser($user)
            ->withHeader('Last-Event-ID', (string) $pmId)
            ->get('/web/shoutbox_sse?type=notifications&loops=1&interval=0');

        $body = $response->streamedContent();
        $this->assertStringNotContainsString('event: notifications', $body);
    }

    public function test_counter_and_lock_released_after_loop_ends(): void
    {
        $user = User::factory()->create();

        $this->asUser($user)
            ->get('/web/shoutbox_sse?type=notifications&loops=1&interval=0')
            ->assertOk()
            ->streamedContent();

        $this->assertSame(0, (int) Redis::connection()->client()->get('shoutbox_sse_global'));

        // The slot is free: a second stream is admitted, not 429'd.
        $this->asUser($user)
            ->get('/web/shoutbox_sse?type=notifications&loops=1&interval=0')
            ->assertOk()
            ->streamedContent();
    }
}
