<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Usercp;

use App\Contracts\Repositories\UsercpLookupRepositoryInterface;
use App\Contracts\Repositories\UsercpRepositoryInterface;
use App\Models\Passkey;
use App\Models\User;
use App\Repositories\TokenRepository;
use App\Services\Usercp\UsercpHomeBuilder;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Settings;
use App\ViewModels\Usercp\UsercpHomeSection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\Concerns\SeedsLegacySettings;
use Tests\TestCase;

/**
 * Kills escaped mutants in the home-section builder (formerly
 * UsercpPageService::buildHome + buildReadTopics + buildTokenSection):
 * forum-post counters and cache hit/miss paths, day-posts/percentage math,
 * IP-location toggle, passkey-login HMAC form gating, token section data,
 * read-topic item assembly.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UsercpHomeBuilderTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLegacySettings;

    /** @var UsercpLookupRepositoryInterface&MockInterface */
    private UsercpLookupRepositoryInterface $lookup;

    /** @var UsercpRepositoryInterface&MockInterface */
    private UsercpRepositoryInterface $usercpRepo;

    /** @var TokenRepository&MockInterface */
    private TokenRepository $tokenRepo;

    /** @var LegacyRedisCache&MockInterface */
    private LegacyRedisCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var UsercpLookupRepositoryInterface&MockInterface $lookup */
        $lookup = Mockery::mock(UsercpLookupRepositoryInterface::class);
        $this->lookup = $lookup;
        /** @var UsercpRepositoryInterface&MockInterface $usercpRepo */
        $usercpRepo = Mockery::mock(UsercpRepositoryInterface::class);
        $this->usercpRepo = $usercpRepo;
        /** @var TokenRepository&MockInterface $tokenRepo */
        $tokenRepo = Mockery::mock(TokenRepository::class);
        $this->tokenRepo = $tokenRepo;
        /** @var LegacyRedisCache&MockInterface $cache */
        $cache = Mockery::mock(LegacyRedisCache::class);
        $this->cache = $cache;

        $this->seedTestSettings([
            'enablelocation_tweak' => 'no',
            'SITENAME' => 'TestSite',
        ]);

        $this->lookup->shouldReceive('getCommentCount')->andReturn(3)->byDefault();
        $this->lookup->shouldReceive('getForumPostCount')->andReturn(0)->byDefault();
        $this->lookup->shouldReceive('getReadTopics')->andReturn([])->byDefault();
        $this->tokenRepo->shouldReceive('listUserTokenPermissionAllowed')->andReturn([])->byDefault();
        $this->usercpRepo->shouldReceive('getUserTokens')->andReturn([])->byDefault();
        $this->cache->shouldReceive('get_value')->andReturn(false)->byDefault();
        $this->cache->shouldReceive('get_values')->andReturn([])->byDefault();
        $this->cache->shouldReceive('cache_value')->byDefault();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Calls build() while suppressing legacy error handlers left by
     * inner helpers (postRowWithContext / view fragments).
     *
     * @param  array<string, mixed>  $curUser
     */
    private function callBuild(array $curUser): UsercpHomeSection
    {
        set_error_handler(static fn (int $severity): bool => true, E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING);

        try {
            return $this->builder()->build($curUser, $this->userInfo());
        } finally {
            restore_error_handler();
        }
    }

    private function builder(): UsercpHomeBuilder
    {
        return new UsercpHomeBuilder(
            $this->lookup,
            $this->usercpRepo,
            $this->tokenRepo,
            $this->cache,
        );
    }

    /** @param  array<string, mixed>  $overrides
     * @return array<string, mixed> */
    private function curUser(array $overrides = []): array
    {
        return array_merge([
            'id' => 5,
            'username' => 'u',
            'email' => 'u@x.c',
            'passkey' => 'p',
            'avatar' => '',
            'invites' => 2,
            'seedbonus' => '10.0',
            'added' => now()->subDays(30)->toDateTimeString(),
            'ip' => '127.0.0.1',
        ], $overrides);
    }

    private function userInfo(): User
    {
        return (new User)->newFromBuilder($this->curUser());
    }

    public function test_forum_posts_path_computes_day_posts_and_percentage(): void
    {
        $this->lookup->shouldReceive('getForumPostCount')->once()->with(5)->andReturn(50);
        $this->lookup->shouldReceive('getTotalPostCount')->once()->andReturn(1000);
        // cache misses for both counters
        $this->cache->shouldReceive('get_value')->with('user_5_post_count')->andReturn(false);
        $this->cache->shouldReceive('get_value')->with('total_posts_count')->andReturn(false);
        $this->cache->shouldReceive('cache_value')->with('user_5_post_count', 50, 3600)->once();
        $this->cache->shouldReceive('cache_value')->with('total_posts_count', 1000, 96400)->once();

        $s = $this->builder()->build($this->curUser(), $this->userInfo());

        $forumPosts = $s->forumPosts;
        $this->assertNotNull($forumPosts);
        $this->assertSame(50, $forumPosts->posts);
        $this->assertSame(1, $forumPosts->dayPosts); // (int) round(50/30, 1) = 1
        $this->assertSame('5%', $forumPosts->percentages);
    }

    public function test_cached_forum_post_count_skips_db(): void
    {
        $this->cache->shouldReceive('get_value')->with('user_5_post_count')->andReturn(9);
        $this->cache->shouldReceive('get_value')->with('total_posts_count')->andReturn(90);
        $this->lookup->shouldReceive('getForumPostCount')->never();
        $this->lookup->shouldReceive('getTotalPostCount')->never();

        $s = $this->builder()->build($this->curUser(), $this->userInfo());

        $forumPosts = $s->forumPosts;
        $this->assertNotNull($forumPosts);
        $this->assertSame(9, $forumPosts->posts);
        $this->assertSame('10%', $forumPosts->percentages);
    }

    public function test_zero_forum_posts_leaves_stats_null(): void
    {
        $s = $this->builder()->build($this->curUser(), $this->userInfo());

        $this->assertNull($s->forumPosts);
        $this->assertSame(3, $s->commentCount);
        $this->assertSame(2, $s->invites);
        $this->assertSame('u@x.c', $s->email);
        $this->assertSame(5, $s->userId);
        $this->assertFalse($s->showAvatar);
        $this->assertSame([], $s->readTopics);
    }

    public function test_new_user_joined_today_has_no_day_posts(): void
    {
        $this->lookup->shouldReceive('getForumPostCount')->andReturn(10);
        $this->lookup->shouldReceive('getTotalPostCount')->andReturn(100);
        $this->cache->shouldReceive('get_value')->with('user_5_post_count')->andReturn(false);
        $this->cache->shouldReceive('get_value')->with('total_posts_count')->andReturn(false);

        $s = $this->builder()->build($this->curUser(['added' => now()->toDateTimeString()]), $this->userInfo());

        $forumPosts = $s->forumPosts;
        $this->assertNotNull($forumPosts);
        $this->assertSame(0, $forumPosts->dayPosts);
        $this->assertSame('10%', $forumPosts->percentages);
    }

    public function test_ip_location_renders_region_when_enabled(): void
    {
        $this->seedTestSettings(['enablelocation_tweak' => 'yes']);

        $s = $this->builder()->build($this->curUser(), $this->userInfo());

        $this->assertStringContainsString('127.0.0.1', (string) $s->ipLocation);
        $this->assertStringContainsString('<span', (string) $s->ipLocation);
    }

    public function test_ip_location_hidden_markup_when_disabled(): void
    {
        $s = $this->builder()->build($this->curUser(), $this->userInfo());

        $this->assertStringContainsString('127.0.0.1', (string) $s->ipLocation);
    }

    public function test_passkey_login_form_rendered_only_with_valid_config(): void
    {
        Settings::saveBatch('security', [
            'login_type' => 'passkey',
            'login_secret' => 's3cr3t',
            'login_secret_deadline' => now()->addDay()->toDateTimeString(),
        ]);
        Settings::resetCache();

        $s = $this->builder()->build($this->curUser(), $this->userInfo());

        $this->assertNotNull($s->passkeyLogin);
        $this->assertSame('p', $s->passkeyLogin->passkey);
        $this->assertStringContainsString('/s3cr3t', $s->passkeyLogin->action);
        $this->assertSame(
            hash_hmac('sha256', 'p'.$s->passkeyLogin->timestamp, 's3cr3t'),
            $s->passkeyLogin->signature,
        );
    }

    public function test_passkey_login_form_absent_when_deadline_passed(): void
    {
        Settings::saveBatch('security', [
            'login_type' => 'passkey',
            'login_secret' => 's3cr3t',
            'login_secret_deadline' => '2000-01-01 00:00:00',
        ]);
        Settings::resetCache();

        $s = $this->builder()->build($this->curUser(), $this->userInfo());

        $this->assertNull($s->passkeyLogin);
    }

    public function test_passkey_login_form_absent_for_normal_login_type(): void
    {
        Settings::saveBatch('security', [
            'login_type' => 'normal',
            'login_secret' => 's3cr3t',
            'login_secret_deadline' => now()->addDay()->toDateTimeString(),
        ]);
        Settings::resetCache();

        $this->assertNull($this->builder()->build($this->curUser(), $this->userInfo())->passkeyLogin);
    }

    public function test_token_section_collects_permissions_and_items(): void
    {
        $this->tokenRepo->shouldReceive('listUserTokenPermissionAllowed')->once()->andReturn([
            'torrent:read' => 'Read torrents',
        ]);
        $this->usercpRepo->shouldReceive('getUserTokens')->once()->andReturn([
            ['id' => 11, 'name' => 'ci', 'abilitiesText' => 'read', 'created_at' => '2024-01-01 00:00:00'],
        ]);

        $s = $this->builder()->build($this->curUser(), $this->userInfo());

        $this->assertSame([['value' => 'torrent:read', 'label' => 'Read torrents']], $s->tokens->permissions);
        $this->assertSame(
            [['id' => 11, 'name' => 'ci', 'abilities' => 'read', 'createdAt' => '2024-01-01 00:00:00']],
            $s->tokens->items,
        );
        $this->assertNotEmpty($s->tokens->columnName);
        $this->assertNotEmpty($s->tokens->actionCreate);
    }

    public function test_read_topics_assembled_with_post_counts(): void
    {
        $this->lookup->shouldReceive('getReadTopics')->with(5)->andReturn([
            ['id' => 7, 'subject' => 'Hello', 'views' => 42, 'lastpost' => 0, 'userid' => 5],
            ['id' => 8, 'subject' => 'World', 'views' => 1, 'lastpost' => 9, 'userid' => 5],
        ]);
        // topic_7 post count + post_9 content come from cache; topic_8 goes to DB
        $this->cache->shouldReceive('get_values')->andReturnUsing(function (array $keys) {
            if ($keys === ['topic_7_post_count', 'topic_8_post_count']) {
                return ['topic_7_post_count' => 4];
            }
            if ($keys === ['post_9_content']) {
                return ['post_9_content' => ['id' => 9, 'added' => '2024-03-04 05:06:07']];
            }

            return [];
        });
        $this->lookup->shouldReceive('getTopicPostCounts')->once()->with([8])->andReturn([8 => 2]);
        $this->cache->shouldReceive('cache_value')->with('topic_8_post_count', 2, 3600)->once();

        $s = $this->callBuild($this->curUser());

        $this->assertCount(2, $s->readTopics);
        [$a, $b] = $s->readTopics;
        $this->assertSame(7, $a->id);
        $this->assertSame('Hello', $a->subject);
        $this->assertSame(3, $a->replies); // count - 1
        $this->assertNull($a->lastPostAdded); // lastpost=0 → no fetch
        $this->assertSame(8, $b->id);
        $this->assertSame(1, $b->replies);
        $this->assertSame('2024-03-04 05:06:07', $b->lastPostAdded);
    }

    public function test_join_date_blank_strings_become_null(): void
    {
        $this->assertNull($this->builder()->build($this->curUser(['added' => '0000-00-00 00:00:00']), $this->userInfo())->joinDate);
        $this->assertNull($this->builder()->build($this->curUser(['added' => '']), $this->userInfo())->joinDate);
    }
}
