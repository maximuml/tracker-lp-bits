<?php

namespace Tests\Feature;

use App\Enums\UserClass;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * The /ajax dispatcher 308-redirects actions that migrated to REST
 * endpoints (308 preserves method + body, so legacy `{action, params}`
 * POSTs replay unchanged). These tests pin the redirect targets and the
 * {ret,msg,data} wire format on the new endpoints.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class AjaxRestEndpointsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
    }

    private function csrfToken(): string
    {
        return Str::random(40);
    }

    private function asNexusUser(User $user): static
    {
        $token = $this->csrfToken();

        return $this->withNexusCookie($user)
            ->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token);
    }

    /** @return array<string, string> */
    public static function redirectedActions(): array
    {
        return [
            'attendanceRetroactive' => ['attendanceRetroactive', '/web/attendance/retroactive'],
            'removeUserLeechWarn' => ['removeUserLeechWarn', '/web/users/leech-warn/remove'],
            'getOffer' => ['getOffer', '/web/offers/show'],
            'approvalModal' => ['approvalModal', '/web/torrents/approval-modal'],
            'approval' => ['approval', '/web/torrent-approval'],
            'removeHitAndRun' => ['removeHitAndRun', '/web/hit-and-runs/remove'],
            'consumeBenefit' => ['consumeBenefit', '/web/benefits/consume'],
            'claimTask' => ['claimTask', '/web/tasks/claim'],
            'addToken' => ['addToken', '/web/token/add'],
            'removeToken' => ['removeToken', '/web/token/del'],
            'getToastNotifications' => ['getToastNotifications', '/web/notifications/feed'],
            'clearShoutBox' => ['clearShoutBox', '/web/shoutbox/clear'],
            'shoutboxPost' => ['shoutboxPost', '/web/shoutbox/post'],
            'shoutboxEdit' => ['shoutboxEdit', '/web/shoutbox/edit'],
            'shoutboxDelete' => ['shoutboxDelete', '/web/shoutbox/delete'],
            'shoutboxReact' => ['shoutboxReact', '/web/shoutbox/react'],
            'getPasskeyCreateArgs' => ['getPasskeyCreateArgs', '/web/passkey/create-args'],
            'processPasskeyCreate' => ['processPasskeyCreate', '/web/passkey/create'],
            'getPasskeyList' => ['getPasskeyList', '/web/passkey/list'],
            'deletePasskey' => ['deletePasskey', '/web/passkey/delete'],
            'getPasskeyGetArgs' => ['getPasskeyGetArgs', '/web/passkey/get-args'],
            'processPasskeyGet' => ['processPasskeyGet', '/web/passkey/get'],
        ];
    }

    /**
     * Every migrated action 308-redirects to its REST URI.
     *
     * @dataProvider redirectedActions
     */
    #[DataProvider('redirectedActions')]
    public function test_ajax_action_308_redirects_to_rest_uri(string $action, string $uri): void
    {
        $user = User::factory()->create();
        $token = $this->csrfToken();
        $response = $this->asNexusUser($user)
            ->post('/ajax', ['action' => $action, 'params' => [], '_token' => $token]);

        $response->assertStatus(308);
        $response->assertRedirect($uri);
    }

    public function test_ajax_shim_hits_are_counted_per_action(): void
    {
        $redis = Redis::connection();
        $redis->del('metrics:legacy_ajax:clearShoutBox');
        $redis->srem('metrics:legacy_ajax_actions', 'clearShoutBox');

        /** @var User $user */
        $user = User::factory()->create();
        $this->asNexusUser($user)
            ->post('/ajax', ['action' => 'clearShoutBox', 'params' => [], '_token' => $this->csrfToken()])
            ->assertStatus(308);

        $this->assertSame('1', (string) $redis->get('metrics:legacy_ajax:clearShoutBox'));
        $this->assertTrue((bool) $redis->sismember('metrics:legacy_ajax_actions', 'clearShoutBox'));
    }

    public function test_ajax_shim_invalid_action_counts_under_invalid_label(): void
    {
        $redis = Redis::connection();
        $redis->del('metrics:legacy_ajax:__invalid');
        $redis->srem('metrics:legacy_ajax_actions', '__invalid');

        /** @var User $user */
        $user = User::factory()->create();
        $this->asNexusUser($user)
            ->post('/ajax', ['action' => 'notARealAction', 'params' => [], '_token' => $this->csrfToken()])
            ->assertOk();

        $this->assertSame('1', (string) $redis->get('metrics:legacy_ajax:__invalid'));
    }

    /**
     * Redirected `{action, params}` envelopes still work end-to-end: the
     * client re-POSTs the same body to the target, where the FormRequest
     * flattens `params` — legacy callers get a normal {ret,msg,data} reply.
     */
    public function test_redirected_envelope_reaches_endpoint_and_returns_envelope(): void
    {
        $user = User::factory()->create();
        $token = $this->csrfToken();

        // getOffer needs a real offer row; use the read-only offer flow.
        $offer = Offer::query()->create([
            'userid' => $user->id,
            'name' => 'test-offer',
            'descr' => 'desc',
        ]);

        $body = ['action' => 'getOffer', 'params' => ['id' => $offer->id], '_token' => $token];

        $redirect = $this->asNexusUser($user)->post('/ajax', $body);
        $redirect->assertStatus(308);

        // A 308-compliant client re-POSTs the identical body to Location.
        // (Laravel's followRedirects() always GETs, so we replay it
        // ourselves — exactly what fetch/XHR do on 308.)
        $response = $this->asNexusUser($user)
            ->post($redirect->headers->get('Location'), $body);

        $response->assertOk();
        $response->assertJsonPath('ret', 0);
        $response->assertJsonStructure(['ret', 'msg', 'data']);
    }

    public function test_offer_show_direct_endpoint_returns_offer(): void
    {
        $user = User::factory()->create();
        $offer = Offer::query()->create([
            'userid' => $user->id,
            'name' => 'test-offer-direct',
            'descr' => 'desc',
        ]);

        $response = $this->asNexusUser($user)
            ->post('/web/offers/show', ['id' => $offer->id, '_token' => $this->csrfToken()]);

        $response->assertOk();
        $response->assertJsonPath('ret', 0);
        $body = $response->json();
        $this->assertSame($offer->id, $body['data']['id'] ?? null);
    }

    public function test_toast_feed_direct_endpoint_returns_cursors(): void
    {
        $user = User::factory()->create();
        $response = $this->asNexusUser($user)
            ->post('/web/notifications/feed', ['init' => true, '_token' => $this->csrfToken()]);

        $response->assertOk();
        $response->assertJsonPath('ret', 0);
        $body = $response->json();
        $this->assertArrayHasKey('cursors', $body['data'] ?? []);
    }

    public function test_offer_show_validation_failure_uses_envelope(): void
    {
        $user = User::factory()->create();
        $response = $this->asNexusUser($user)
            ->post('/web/offers/show', ['_token' => $this->csrfToken()]);

        // Validation failures stay in the legacy {ret!=0,msg} wire format
        // — not the default 422 shape — so old callers keep working.
        $response->assertOk();
        $body = $response->json();
        $this->assertNotEquals(0, $body['ret']);
        $this->assertNotEmpty($body['msg']);
    }

    public function test_shoutbox_post_through_redirect_posts_message(): void
    {
        $user = User::factory()->create();
        $token = $this->csrfToken();
        $body = ['action' => 'shoutboxPost', 'params' => ['text' => 'rest endpoint shout'], '_token' => $token];

        $redirect = $this->asNexusUser($user)->post('/ajax', $body);
        $redirect->assertStatus(308);

        $response = $this->asNexusUser($user)
            ->post($redirect->headers->get('Location'), $body);

        $response->assertOk();
        $response->assertJsonPath('ret', 0);
        $this->assertDatabaseHas('shoutbox', [
            'userid' => $user->id,
            'text' => 'rest endpoint shout',
        ]);
    }

    public function test_shoutbox_post_validation_failure_uses_envelope(): void
    {
        $user = User::factory()->create();
        $response = $this->asNexusUser($user)
            ->post('/web/shoutbox/post', ['text' => str_repeat('x', 2001), '_token' => $this->csrfToken()]);

        $response->assertOk();
        $body = $response->json();
        $this->assertNotEquals(0, $body['ret']);
        $this->assertSame('Message too long', $body['msg']);
    }

    /** @return array<string, array{0: string, 1: array<string, mixed>, 2: string}> */
    public static function shoutboxFailures(): array
    {
        return [
            'post blank' => ['/web/shoutbox/post', ['text' => '   '], 'Message cannot be empty'],
            'edit blank text' => ['/web/shoutbox/edit', ['id' => 1, 'text' => '   '], 'The text field is required.'],
            'react bad emoji' => ['/web/shoutbox/react', ['id' => 1, 'reaction' => 'nope'], 'Invalid reaction or reacting too often'],
            'clear no permission' => ['/web/shoutbox/clear', [], 'No permission'],
        ];
    }

    /**
     * Domain failures keep the legacy error strings the shoutbox.js
     * callers alert() — pinned after the failWithContext msg fix.
     *
     * @dataProvider shoutboxFailures
     */
    #[DataProvider('shoutboxFailures')]
    public function test_shoutbox_endpoint_error_messages(string $uri, array $body, string $expectedMsg): void
    {
        // Pin the class — the factory randomly grants staff classes, which
        // would pass the sbmanage gate on the 'clear' row.
        $user = User::factory()->class(UserClass::USER->value)->create();
        $body['_token'] = $this->csrfToken();
        $response = $this->asNexusUser($user)->post($uri, $body);

        $response->assertOk();
        $response->assertJsonPath('msg', $expectedMsg);
        $this->assertNotEquals(0, $response->json('ret'));
    }

    public function test_shoutbox_formrequest_failure_uses_envelope(): void
    {
        $user = User::factory()->create();
        $response = $this->asNexusUser($user)
            ->post('/web/shoutbox/delete', ['_token' => $this->csrfToken()]);

        $response->assertOk();
        $body = $response->json();
        $this->assertNotEquals(0, $body['ret']);
        $this->assertNotEmpty($body['msg']);
    }

    public function test_endpoints_require_login(): void
    {
        $token = $this->csrfToken();
        $response = $this->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->post('/web/offers/show', ['id' => 1, '_token' => $token]);

        // auth.nexus redirects guests away — never a 200 with ret envelope.
        $response->assertRedirect();
    }

    public function test_guest_passkey_endpoints_reachable_without_login(): void
    {
        // The login page calls these before the user has a session —
        // they intentionally live outside auth.nexus. Both return the
        // {ret,msg,data} envelope (get-args needs a challenge backend,
        // so ret!=0 is acceptable — 200 is the contract).
        $token = $this->csrfToken();
        $response = $this->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->post('/web/passkey/get-args', ['_token' => $token]);

        $response->assertOk();
        $this->assertIsInt($response->json('ret'));
    }

    public function test_authed_passkey_endpoints_redirect_guests(): void
    {
        $token = $this->csrfToken();
        $response = $this->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->post('/web/passkey/list', ['_token' => $token]);

        $response->assertRedirect();
    }
}
