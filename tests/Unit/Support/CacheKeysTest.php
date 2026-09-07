<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Cache\Keys;
use App\Support\Cache\TaggedCacheService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * @covers \App\Support\Cache\Keys
 * @covers \App\Support\Cache\TaggedCacheService
 */
final class CacheKeysTest extends TestCase
{
    public function test_key_substitutes_placeholders(): void
    {
        $this->assertSame('user_42_content', Keys::USER_CONTENT->key(['id' => 42]));
        $this->assertSame('user_passkey_abc123_content', Keys::USER_PASSKEY_CONTENT->key(['passkey' => 'abc123']));
        $this->assertSame('torrent_hash_deadbeef_content', Keys::TORRENT_HASH_CONTENT->key(['hash' => 'deadbeef']));
        $this->assertSame('topic_99_post_count', Keys::TOPIC_POST_COUNT->key(['id' => 99]));
    }

    public function test_key_without_params_returns_pattern_as_is(): void
    {
        $this->assertSame('category_content', Keys::CATEGORY_CONTENT->key());
        $this->assertSame('search_box_content', Keys::SEARCH_BOX_CONTENT->key());
    }

    public function test_key_with_locale_prefix(): void
    {
        $this->assertSame('en_user_42_content', Keys::USER_CONTENT->keyWithLocale('en', ['id' => 42]));
        $this->assertSame('zh_category_content', Keys::CATEGORY_CONTENT->keyWithLocale('zh'));
    }

    public function test_ttl_returns_positive_int(): void
    {
        foreach (Keys::cases() as $key) {
            $this->assertGreaterThan(0, $key->ttl(), "TTL for {$key->name} must be positive");
        }
    }

    public function test_tags_returns_non_empty_array(): void
    {
        foreach (Keys::cases() as $key) {
            $tags = $key->tags();
            $this->assertNotEmpty($tags, "Tags for {$key->name} must not be empty");
        }
    }

    public function test_user_keys_have_user_tag(): void
    {
        $this->assertContains('user', Keys::USER_CONTENT->tags());
        $this->assertContains('user', Keys::USER_ROLES->tags());
        $this->assertContains('user', Keys::USER_INBOX_COUNT->tags());
    }

    public function test_settings_keys_have_settings_tag(): void
    {
        $this->assertContains('settings', Keys::SETTINGS_LARAVEL->tags());
        $this->assertContains('settings', Keys::SETTINGS_NEXUS->tags());
    }

    public function test_torrent_keys_have_torrent_tag(): void
    {
        $this->assertContains('torrent', Keys::TORRENT_HASH_CONTENT->tags());
        $this->assertContains('torrent', Keys::TORRENT_NOT_EXISTS->tags());
    }

    public function test_tagged_cache_service_put_and_get(): void
    {
        $svc = app(TaggedCacheService::class);

        $svc->put(Keys::USER_CONTENT, ['id' => 99999], ['name' => 'test_user']);

        $this->assertSame(
            ['name' => 'test_user'],
            $svc->get(Keys::USER_CONTENT, ['id' => 99999])
        );
    }

    public function test_tagged_cache_service_remember(): void
    {
        $svc = app(TaggedCacheService::class);
        $callCount = 0;

        $result1 = $svc->remember(Keys::USER_ROLES, ['id' => 99998], function () use (&$callCount) {
            $callCount++;

            return ['role' => 'admin'];
        });
        $result2 = $svc->get(Keys::USER_ROLES, ['id' => 99998]);

        $this->assertSame(['role' => 'admin'], $result1);
        $this->assertSame(['role' => 'admin'], $result2);
        $this->assertSame(1, $callCount, 'Callback should only execute once');
    }

    public function test_tagged_cache_service_forget(): void
    {
        $svc = app(TaggedCacheService::class);

        $svc->put(Keys::USER_CONTENT, ['id' => 99997], 'cached_value');
        $this->assertSame('cached_value', $svc->get(Keys::USER_CONTENT, ['id' => 99997]));

        $svc->forget(Keys::USER_CONTENT, ['id' => 99997]);
        $this->assertNull($svc->get(Keys::USER_CONTENT, ['id' => 99997]));
    }

    public function test_tagged_cache_service_flush_tag(): void
    {
        $svc = app(TaggedCacheService::class);

        $svc->put(Keys::USER_CONTENT, ['id' => 99996], 'user1');
        $svc->put(Keys::USER_ROLES, ['id' => 99996], 'roles1');

        $svc->flushTag('user');

        $this->assertNull($svc->get(Keys::USER_CONTENT, ['id' => 99996]));
        $this->assertNull($svc->get(Keys::USER_ROLES, ['id' => 99996]));
    }

    public function test_tagged_cache_service_raw_operations(): void
    {
        $svc = app(TaggedCacheService::class);

        $svc->putRaw('test_raw_key', 'raw_value', 60);
        $this->assertSame('raw_value', $svc->getRaw('test_raw_key'));

        $svc->forgetRaw('test_raw_key');
        $this->assertNull($svc->getRaw('test_raw_key'));
    }

    public function test_tagged_cache_service_remember_raw(): void
    {
        $svc = app(TaggedCacheService::class);
        $called = false;

        $result = $svc->rememberRaw('test_remember_raw', 60, function () use (&$called) {
            $called = true;

            return 'remembered';
        });

        $this->assertSame('remembered', $result);
        $this->assertTrue($called);

        // Second call should not invoke callback
        $called = false;
        $result2 = $svc->rememberRaw('test_remember_raw', 60, function () use (&$called) {
            $called = true;

            return 'should_not_be_called';
        });

        $this->assertSame('remembered', $result2);
        $this->assertFalse($called);
    }

    public function test_put_with_custom_ttl(): void
    {
        $svc = app(TaggedCacheService::class);

        $svc->putWithTtl(Keys::USER_CONTENT, ['id' => 99995], 'short_lived', 60);
        $this->assertSame('short_lived', $svc->get(Keys::USER_CONTENT, ['id' => 99995]));
    }

    protected function tearDown(): void
    {
        // Clean up test keys
        Cache::flush();

        parent::tearDown();
    }
}
