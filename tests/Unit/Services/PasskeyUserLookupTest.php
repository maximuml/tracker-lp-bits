<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\PasskeyUserLookup;
use App\Support\RedisGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * W5-04: passkey lookup must never poison a valid passkey by caching a
 * transient empty result, and must stay correct when Redis is down.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class PasskeyUserLookupTest extends TestCase
{
    use DatabaseTransactions;

    private PasskeyUserLookup $lookup;

    protected function setUp(): void
    {
        parent::setUp();
        RedisGuard::reset();
        $this->lookup = new PasskeyUserLookup;
    }

    protected function tearDown(): void
    {
        RedisGuard::reset();
        parent::tearDown();
    }

    private static function passkey(): string
    {
        return md5((string) mt_getrandmax().microtime(true).random_bytes(8));
    }

    public function test_find_returns_user_for_known_passkey(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['passkey' => self::passkey()]);

        $found = $this->lookup->find($user->passkey);

        $this->assertSame($user->id, $found['id']);
        $this->assertSame($user->id, Cache::get("user_passkey_{$user->passkey}_content")['id']);
    }

    public function test_find_returns_empty_array_for_unknown_passkey(): void
    {
        $this->assertSame([], $this->lookup->find(self::passkey()));
    }

    public function test_missing_passkey_is_not_cached(): void
    {
        $passkey = self::passkey();

        $this->assertSame([], $this->lookup->find($passkey));

        /** @var User $user */
        $user = User::factory()->create(['passkey' => $passkey]);

        $this->assertSame($user->id, $this->lookup->find($passkey)['id']);
    }

    public function test_cached_empty_result_is_treated_as_miss(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['passkey' => self::passkey()]);
        Cache::put("user_passkey_{$user->passkey}_content", [], 3600);

        $this->assertSame($user->id, $this->lookup->find($user->passkey)['id']);
    }

    public function test_find_uses_cache_when_populated(): void
    {
        $passkey = self::passkey();
        Cache::put("user_passkey_{$passkey}_content", ['id' => 424242, 'passkey' => $passkey], 3600);

        $found = $this->lookup->find($passkey);

        $this->assertSame(424242, $found['id']);
    }

    public function test_find_clears_stale_invalid_flag_for_known_passkey(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['passkey' => self::passkey()]);
        $redis = Redis::connection()->client();
        $redis->set("passkey_invalid:{$user->passkey}", time(), ['ex' => 3600]);

        $this->assertSame($user->id, $this->lookup->find($user->passkey)['id']);
        $this->assertSame(0, $redis->exists("passkey_invalid:{$user->passkey}"));
    }

    public function test_find_falls_back_to_db_when_redis_is_down(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['passkey' => self::passkey()]);
        RedisGuard::markDown();

        $this->assertSame($user->id, $this->lookup->find($user->passkey)['id']);
        $this->assertSame([], $this->lookup->find(self::passkey()));
    }
}
