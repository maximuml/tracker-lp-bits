<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\AuthCookie;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * HTTP surface for REL-01: the bell panel endpoint must honour the
 * offset parameter and markRead must accept the snapshot watermark.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class NotificationFeedHttpTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('notification_cursors')->delete();
        DB::table('messages')->delete();
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function createUser(string $username): User
    {
        return User::factory()->create(['username' => $username]);
    }

    /**
     * Authenticate for the legacy stack the way a browser does — with a
     * real `c_secure_pass` cookie. LegacyAuth::loginFromContext() fills
     * CurrentUser during boot; the nexus-web guard passes middleware.
     */
    private function cookieFor(User $user): string
    {
        return AuthCookie::buildToken(
            (int) $user->id,
            null,
            time() + 3600,
            (int) $user->auth_version,
        );
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

    public function test_notifications_index_returns_feed_shape_and_offset(): void
    {
        $user = $this->createUser('http_owner');
        $sender = $this->createUser('http_sender');
        $cookie = $this->cookieFor($user);

        $response = $this->withCredentials()->withUnencryptedCookie(AuthCookie::COOKIE_NAME, $cookie)->getJson('/notifications');

        $response->assertOk()->assertJsonPath('ret', 0);
        $data = $response->json('data');
        $this->assertIsArray($data);
        foreach (['items', 'counts', 'cursors', 'watermark', 'has_more'] as $key) {
            $this->assertArrayHasKey($key, $data, "missing key: $key");
        }
        // First call seeds cursors — nothing unread.
        $this->assertSame(0, $data['counts']['total']);

        for ($i = 0; $i < 25; $i++) {
            $this->createPm((int) $user->id, (int) $sender->id);
        }

        $first = $this->withCredentials()->withUnencryptedCookie(AuthCookie::COOKIE_NAME, $cookie)->getJson('/notifications')->json('data');
        $this->assertSame(25, $first['counts']['pm']);
        $this->assertCount(20, $first['items']);
        $this->assertTrue($first['has_more']);

        $second = $this->withCredentials()->withUnencryptedCookie(AuthCookie::COOKIE_NAME, $cookie)->getJson('/notifications?offset=20')->json('data');
        $this->assertCount(5, $second['items']);
        $this->assertFalse($second['has_more']);
    }

    public function test_mark_read_with_watermark_keeps_later_events(): void
    {
        $user = $this->createUser('wm_owner');
        $sender = $this->createUser('wm_sender');
        $cookie = $this->cookieFor($user);

        // Seed cursors first.
        $this->withCredentials()->withUnencryptedCookie(AuthCookie::COOKIE_NAME, $cookie)->getJson('/notifications');

        $this->createPm((int) $user->id, (int) $sender->id);
        $panel = $this->withCredentials()->withUnencryptedCookie(AuthCookie::COOKIE_NAME, $cookie)->getJson('/notifications')->json('data');
        $this->assertSame(1, $panel['counts']['pm']);

        // A new PM arrives after the panel snapshot; mark-read must not
        // swallow it.
        $this->createPm((int) $user->id, (int) $sender->id);

        $this->withCredentials()->withUnencryptedCookie(AuthCookie::COOKIE_NAME, $cookie)->postJson('/web/notifications/mark-read', ['watermark' => $panel['watermark']])
            ->assertOk()->assertJsonPath('ret', 0);

        $after = $this->withCredentials()->withUnencryptedCookie(AuthCookie::COOKIE_NAME, $cookie)->getJson('/notifications')->json('data');
        $this->assertSame(1, $after['counts']['pm']);
    }

    public function test_mark_read_without_watermark_marks_all(): void
    {
        $user = $this->createUser('nowm_owner');
        $sender = $this->createUser('nowm_sender');
        $cookie = $this->cookieFor($user);

        $this->withCredentials()->withUnencryptedCookie(AuthCookie::COOKIE_NAME, $cookie)->getJson('/notifications');
        $this->createPm((int) $user->id, (int) $sender->id);

        $this->withCredentials()->withUnencryptedCookie(AuthCookie::COOKIE_NAME, $cookie)->postJson('/web/notifications/mark-read', [])
            ->assertOk()->assertJsonPath('ret', 0);

        $after = $this->withCredentials()->withUnencryptedCookie(AuthCookie::COOKIE_NAME, $cookie)->getJson('/notifications')->json('data');
        $this->assertSame(0, $after['counts']['pm']);
    }

    public function test_notifications_require_auth(): void
    {
        $this->getJson('/notifications')->assertUnauthorized();
    }
}
