<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\UserListingRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for UserListingRepository.
 *
 * Covers getCountries(), countUsers(), listUsers(), and
 * getSearchExtraStats().
 */
final class UserListingRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private UserListingRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable FK checks for the duration of the test — getSearchExtraStats
        // inserts peers/comments/posts with arbitrary torrent/topic IDs that
        // do not exist in the referenced tables.  Use DELETE (DML) instead of
        // TRUNCATE (DDL) to avoid an implicit commit that would break
        // DatabaseTransactions rollback for subsequent tests.
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('bans')->delete();
        DB::table('comments')->delete();
        DB::table('posts')->delete();
        DB::table('topics')->delete();
        DB::table('forums')->delete();
        DB::table('peers')->delete();
        DB::table('users')->delete();
        DB::table('countries')->delete();
        $this->repository = new UserListingRepository;
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        parent::tearDown();
    }

    public function test_get_countries_returns_empty_array_when_none(): void
    {
        $this->assertSame([], $this->repository->getCountries());
    }

    public function test_get_countries_returns_rows_ordered_by_name(): void
    {
        DB::table('countries')->insert([
            ['name' => 'Zambia', 'flagpic' => 'zambia.gif'],
            ['name' => 'Albania', 'flagpic' => 'albania.gif'],
            ['name' => 'Brazil', 'flagpic' => 'brazil.gif'],
        ]);

        $result = $this->repository->getCountries();

        $this->assertCount(3, $result);
        $this->assertSame('Albania', $result[0]['name']);
        $this->assertSame('Brazil', $result[1]['name']);
        $this->assertSame('Zambia', $result[2]['name']);
    }

    public function test_count_users_returns_zero_when_none(): void
    {
        $this->assertSame(0, $this->repository->countUsers([]));
    }

    public function test_count_users_counts_confirmed_users(): void
    {
        $this->insertUser(['username' => 'alice', 'status' => 'confirmed']);
        $this->insertUser(['username' => 'bob', 'status' => 'confirmed']);
        $this->insertUser(['username' => 'carol', 'status' => 'pending']);

        $this->assertSame(2, $this->repository->countUsers([]));
    }

    public function test_count_users_filters_by_search(): void
    {
        $this->insertUser(['username' => 'alice', 'status' => 'confirmed']);
        $this->insertUser(['username' => 'bob', 'status' => 'confirmed']);

        $this->assertSame(1, $this->repository->countUsers(['search' => 'ali']));
    }

    public function test_count_users_filters_by_letter(): void
    {
        $this->insertUser(['username' => 'alice', 'status' => 'confirmed']);
        $this->insertUser(['username' => 'bob', 'status' => 'confirmed']);
        $this->insertUser(['username' => 'alex', 'status' => 'confirmed']);

        $this->assertSame(2, $this->repository->countUsers(['letter' => 'a']));
    }

    public function test_count_users_filters_by_class(): void
    {
        $this->insertUser(['username' => 'alice', 'status' => 'confirmed', 'class' => 3]);
        $this->insertUser(['username' => 'bob', 'status' => 'confirmed', 'class' => 5]);

        $this->assertSame(1, $this->repository->countUsers(['class' => 3]));
    }

    public function test_count_users_class_dash_does_not_filter(): void
    {
        $this->insertUser(['username' => 'alice', 'status' => 'confirmed', 'class' => 3]);
        $this->insertUser(['username' => 'bob', 'status' => 'confirmed', 'class' => 5]);

        $this->assertSame(2, $this->repository->countUsers(['class' => '-']));
    }

    public function test_count_users_filters_by_country(): void
    {
        $countryId = (int) DB::table('countries')->insertGetId(['name' => 'TestLand', 'flagpic' => 'test.gif']);
        $this->insertUser(['username' => 'alice', 'status' => 'confirmed', 'country' => $countryId]);
        $this->insertUser(['username' => 'bob', 'status' => 'confirmed', 'country' => 0]);

        $this->assertSame(1, $this->repository->countUsers(['country' => $countryId]));
    }

    public function test_list_users_returns_empty_array_when_none(): void
    {
        $this->assertSame([], $this->repository->listUsers([], 0, 25));
    }

    public function test_list_users_returns_rows_ordered_by_username(): void
    {
        $this->insertUser(['username' => 'zoe', 'status' => 'confirmed']);
        $this->insertUser(['username' => 'amy', 'status' => 'confirmed']);

        $result = $this->repository->listUsers([], 0, 25);

        $this->assertCount(2, $result);
        $this->assertSame('amy', $result[0]['username'] ?? $this->fetchUsername($result[0]['id']));
    }

    public function test_list_users_respects_offset_and_limit(): void
    {
        $this->insertUser(['username' => 'a_user', 'status' => 'confirmed']);
        $this->insertUser(['username' => 'b_user', 'status' => 'confirmed']);
        $this->insertUser(['username' => 'c_user', 'status' => 'confirmed']);

        $result = $this->repository->listUsers([], 1, 1);

        $this->assertCount(1, $result);
    }

    public function test_list_users_includes_country_flag_when_country_set(): void
    {
        $countryId = (int) DB::table('countries')->insertGetId(['name' => 'FlagLand', 'flagpic' => 'flag.gif']);
        $this->insertUser(['username' => 'flagger', 'status' => 'confirmed', 'country' => $countryId]);

        $result = $this->repository->listUsers([], 0, 25);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('flag.gif', (string) $result[0]['country']);
    }

    public function test_list_users_shows_dashes_when_country_zero(): void
    {
        $this->insertUser(['username' => 'nocountry', 'status' => 'confirmed', 'country' => 0]);

        $result = $this->repository->listUsers([], 0, 25);

        $this->assertCount(1, $result);
        $this->assertSame('---', $result[0]['country']);
    }

    public function test_get_search_extra_stats_returns_empty_when_no_user_ids(): void
    {
        $result = $this->repository->getSearchExtraStats([], [], 1);

        $this->assertSame(['peers' => [], 'posts' => [], 'comments' => [], 'bannedIps' => []], $result);
    }

    public function test_get_search_extra_stats_aggregates_peers_posts_comments(): void
    {
        $userId1 = $this->insertUser(['username' => 'stat1', 'status' => 'confirmed']);
        $userId2 = $this->insertUser(['username' => 'stat2', 'status' => 'confirmed']);

        DB::table('peers')->insert([
            ['torrent' => 1, 'peer_id' => random_bytes(20), 'ip' => '1.1.1.1', 'userid' => $userId1, 'agent' => 'x', 'ipv4' => '1.1.1.1', 'ipv6' => '', 'port' => 1000, 'connectable' => 1, 'seeder' => 1, 'uploaded' => 500, 'downloaded' => 100, 'to_go' => 0, 'passkey' => str_repeat('a', 32), 'started' => now()->toDateTimeString(), 'last_action' => now()->toDateTimeString()],
        ]);

        $forumId = (int) DB::table('forums')->insertGetId(['name' => 'Test', 'description' => 'd', 'minclassread' => 1, 'minclasswrite' => 1, 'minclasscreate' => 1, 'sort' => 1, 'forid' => 0, 'postcount' => 0, 'topiccount' => 0]);
        $topicId = (int) DB::table('topics')->insertGetId(['userid' => $userId1, 'subject' => 'topic', 'forumid' => $forumId, 'firstpost' => 0, 'lastpost' => 0, 'views' => 0]);
        DB::table('posts')->insert([
            ['topicid' => $topicId, 'userid' => $userId1, 'added' => now()->toDateTimeString(), 'body' => 'p1', 'ori_body' => 'p1'],
            ['topicid' => $topicId, 'userid' => $userId2, 'added' => now()->toDateTimeString(), 'body' => 'p2', 'ori_body' => 'p2'],
        ]);

        DB::table('comments')->insert([
            ['user' => $userId1, 'torrent' => 1, 'added' => now()->toDateTimeString(), 'text' => 'c1', 'ori_text' => 'c1'],
            ['user' => $userId1, 'torrent' => 2, 'added' => now()->toDateTimeString(), 'text' => 'c2', 'ori_text' => 'c2'],
        ]);

        $result = $this->repository->getSearchExtraStats([$userId1, $userId2], [], 1);

        $this->assertArrayHasKey($userId1, $result['peers']);
        $this->assertSame(500.0, $result['peers'][$userId1]['pul']);
        $this->assertSame(100.0, $result['peers'][$userId1]['pdl']);
        $this->assertSame(1, $result['posts'][$userId1]);
        $this->assertSame(1, $result['posts'][$userId2]);
        $this->assertSame(2, $result['comments'][$userId1]);
        $this->assertSame([], $result['bannedIps']);
    }

    public function test_get_search_extra_stats_detects_banned_ips(): void
    {
        $userId = $this->insertUser(['username' => 'bannedip', 'status' => 'confirmed']);

        // Ban the IP range 1.2.3.0 - 1.2.3.255
        DB::table('bans')->insert([
            'added' => now()->toDateTimeString(),
            'addedby' => 1,
            'comment' => 'ban',
            'first' => ip2long('1.2.3.0'),
            'last' => ip2long('1.2.3.255'),
        ]);

        $result = $this->repository->getSearchExtraStats([$userId], ['1.2.3.4'], 1);

        $this->assertArrayHasKey('1.2.3.4', $result['bannedIps']);
        $this->assertTrue($result['bannedIps']['1.2.3.4']);
    }

    public function test_get_search_extra_stats_ignores_invalid_ips(): void
    {
        $userId = $this->insertUser(['username' => 'badip', 'status' => 'confirmed']);

        $result = $this->repository->getSearchExtraStats([$userId], ['not-an-ip', ''], 1);

        $this->assertSame([], $result['bannedIps']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertUser(array $overrides = []): int
    {
        return (int) DB::table('users')->insertGetId(array_merge([
            'username' => 'user_'.uniqid(),
            'email' => uniqid().'@example.net',
            'secret' => bin2hex(random_bytes(10)),
            'passhash' => md5('secret123456secret'),
            'status' => 'confirmed',
            'class' => 1,
            'country' => 0,
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
        ], $overrides));
    }

    private function fetchUsername(int $id): string
    {
        $row = DB::table('users')->where('id', $id)->first();

        return $row !== null ? (string) $row->username : '';
    }
}
