<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Torrent;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * T-17: Deterministic dataset for k6 performance tests.
 *
 * Creates a fixed set of users, torrents, peers, snatched records,
 * comments, forum posts, and messages so that k6 tests have
 * reproducible data to exercise realistic scenarios.
 *
 * Idempotent: checks for existing test data before inserting.
 */
class PerformanceTestDatasetSeeder extends Seeder
{
    private const TEST_USER_PREFIX = 'perf_user_';

    private const TEST_TORRENT_PREFIX = 'perf-torrent-';

    /**
     * Get a user from a collection by index, safely.
     *
     * @param  Collection<int, User>  $users
     */
    private function userAt($users, int $index): User
    {
        return $users[$index % $users->count()] ?? $users->firstOrFail();
    }

    /**
     * Run the seeder.
     */
    public function run(): void
    {
        // Disable MeiliSearch indexing during seeding — the perf-budget
        // CI workflow does not start MeiliSearch, and Torrent model's
        // Scout observer would try to index each created torrent.
        $this->disableMeiliSearch();

        // Create 10 test users with known credentials
        $this->createTestUsers();

        // Create 50 torrents across different categories
        $this->createTestTorrents();

        // Create peers for the torrents
        $this->createTestPeers();

        // Create snatched records
        $this->createTestSnatched();

        // Create comments on torrents
        $this->createTestComments();

        // Create forum topics and posts
        $this->createTestForumPosts();

        // Create private messages between test users
        $this->createTestMessages();

        // Disable captcha for test login (k6 can't solve image captcha)
        $this->disableCaptcha();
    }

    /**
     * Create 10 deterministic test users.
     */
    private function createTestUsers(): void
    {
        $existing = User::where('username', 'like', self::TEST_USER_PREFIX.'%')->count();
        if ($existing >= 10) {
            return;
        }

        $class = DB::table('users')->where('username', 'sysop')->value('class') ?? 3;

        for ($i = 1; $i <= 10; $i++) {
            $username = self::TEST_USER_PREFIX.$i;
            if (User::where('username', $username)->exists()) {
                continue;
            }

            User::create([
                'username' => $username,
                'email' => $username.'@perf-test.local',
                'passhash' => password_hash('PerfTest2026!', PASSWORD_BCRYPT),
                'passhash_algo' => 'bcrypt',
                'secret' => Str::random(20),
                'auth_key' => Str::random(60),
                'passkey' => Str::random(32),
                'class' => $class,
                'enabled' => 1,
                'status' => 'confirmed',
                'uploaded' => 10 * 1024 * 1024 * 1024, // 10 GB
                'downloaded' => 1 * 1024 * 1024 * 1024, // 1 GB
                'added' => now(),
            ]);
        }
    }

    /**
     * Create 50 deterministic test torrents.
     */
    private function createTestTorrents(): void
    {
        $existing = Torrent::where('name', 'like', self::TEST_TORRENT_PREFIX.'%')->count();
        if ($existing >= 50) {
            return;
        }

        $firstUser = User::where('username', self::TEST_USER_PREFIX.'1')->first();
        if ($firstUser === null) {
            return;
        }

        $categories = DB::table('categories')->pluck('id')->toArray();
        if ($categories === []) {
            $categories = [1];
        }

        $codecs = DB::table('codecs')->pluck('id')->toArray();
        if ($codecs === []) {
            $codecs = [1];
        }

        $sources = DB::table('sources')->pluck('id')->toArray();
        if ($sources === []) {
            $sources = [1];
        }

        $media = DB::table('media')->pluck('id')->toArray();
        if ($media === []) {
            $media = [1];
        }

        for ($i = 1; $i <= 50; $i++) {
            $name = self::TEST_TORRENT_PREFIX.$i;
            if (Torrent::where('name', $name)->exists()) {
                continue;
            }

            Torrent::create([
                'name' => $name,
                'filename' => $name.'.torrent',
                'save_as' => $name,
                'owner' => $firstUser->id,
                'size' => 1024 * 1024 * 1024 * ($i % 10 + 1), // 1-10 GB
                'category' => $categories[($i - 1) % count($categories)],
                'codec' => $codecs[($i - 1) % count($codecs)],
                'source' => $sources[($i - 1) % count($sources)],
                'medium' => $media[($i - 1) % count($media)],
                'info_hash' => substr(md5($name), 0, 20),
                'added' => now()->subDays($i),
                'visible' => 1,
                'banned' => 0,
                'seeders' => ($i % 5) + 1,
                'leechers' => ($i % 3),
                'times_completed' => $i * 2,
                'type' => 'single',
                'numfiles' => 1,
                'anonymous' => false,
            ]);
        }
    }

    /**
     * Create peers for the test torrents.
     */
    private function createTestPeers(): void
    {
        $torrents = Torrent::where('name', 'like', self::TEST_TORRENT_PREFIX.'%')->get();
        $users = User::where('username', 'like', self::TEST_USER_PREFIX.'%')->get();

        if ($torrents->isEmpty() || $users->isEmpty()) {
            return;
        }

        foreach ($torrents as $torrent) {
            // Create 2-5 peers per torrent
            $peerCount = ($torrent->id % 4) + 2;
            for ($i = 0; $i < $peerCount; $i++) {
                $user = $this->userAt($users, $i);
                DB::table('peers')->updateOrInsert(
                    [
                        'torrent' => $torrent->id,
                        'userid' => $user->id,
                        'peer_id' => '-perf-'.$user->id.'-'.$torrent->id,
                    ],
                    [
                        'ip' => '127.0.0.1',
                        'port' => 50000 + $i,
                        'uploaded' => 1024 * 1024 * 100,
                        'downloaded' => 1024 * 1024 * 50,
                        'to_go' => 0,
                        'seeder' => $i < $peerCount / 2 ? 1 : 0,
                        'started' => now()->subHours($i),
                        'last_action' => now()->subMinutes($i * 5),
                        'connectable' => 1,
                    ]
                );
            }
        }
    }

    /**
     * Create snatched records.
     */
    private function createTestSnatched(): void
    {
        $torrents = Torrent::where('name', 'like', self::TEST_TORRENT_PREFIX.'%')->get();
        $users = User::where('username', 'like', self::TEST_USER_PREFIX.'%')->get();

        if ($torrents->isEmpty() || $users->isEmpty()) {
            return;
        }

        foreach ($torrents as $torrent) {
            foreach ($users as $user) {
                DB::table('snatched')->updateOrInsert(
                    ['torrentid' => $torrent->id, 'userid' => $user->id],
                    [
                        'ip' => '127.0.0.1',
                        'port' => 50000,
                        'uploaded' => 1024 * 1024 * 100,
                        'downloaded' => 1024 * 1024 * 50,
                        'to_go' => 0,
                        'seedtime' => 3600,
                        'leechtime' => 1800,
                        'startdat' => now()->subHours(2),
                        'completedat' => now()->subHours(1),
                        'finished' => 1,
                    ]
                );
            }
        }
    }

    /**
     * Create comments on test torrents.
     */
    private function createTestComments(): void
    {
        $torrents = Torrent::where('name', 'like', self::TEST_TORRENT_PREFIX.'%')->get();
        $users = User::where('username', 'like', self::TEST_USER_PREFIX.'%')->get();

        if ($torrents->isEmpty() || $users->isEmpty()) {
            return;
        }

        foreach ($torrents->take(20) as $torrent) {
            for ($i = 0; $i < 3; $i++) {
                $user = $this->userAt($users, $i);
                DB::table('comments')->insertOrIgnore([
                    'user' => $user->id,
                    'torrent' => $torrent->id,
                    'added' => now()->subHours($i),
                    'text' => 'Performance test comment #'.$i.' on '.$torrent->name,
                ]);
            }
        }
    }

    /**
     * Create forum topics and posts.
     */
    private function createTestForumPosts(): void
    {
        $forums = DB::table('forums')->pluck('id')->toArray();
        if ($forums === []) {
            return;
        }

        $users = User::where('username', 'like', self::TEST_USER_PREFIX.'%')->get();
        if ($users->isEmpty()) {
            return;
        }

        foreach ($forums as $forumId) {
            for ($i = 1; $i <= 5; $i++) {
                $user = $this->userAt($users, $i);

                // Create topic first (firstpost will be updated after post creation)
                $topicId = DB::table('topics')->insertGetId([
                    'forumid' => $forumId,
                    'userid' => $user->id,
                    'subject' => 'Perf test topic #'.$i,
                    'firstpost' => 0, // Will be updated after post creation
                    'lastpost' => now()->subHours($i)->timestamp,
                    'views' => $i * 10,
                ]);

                // Create the post referencing the topic
                $postId = DB::table('posts')->insertGetId([
                    'topicid' => $topicId,
                    'userid' => $user->id,
                    'added' => now()->subHours($i),
                    'body' => 'Performance test post content #'.$i,
                ]);

                // Update the topic with the correct firstpost
                DB::table('topics')->where('id', $topicId)->update(['firstpost' => $postId]);
            }
        }
    }

    /**
     * Create private messages between test users.
     */
    private function createTestMessages(): void
    {
        $users = User::where('username', 'like', self::TEST_USER_PREFIX.'%')->get();
        if ($users->count() < 2) {
            return;
        }

        for ($i = 0; $i < 20; $i++) {
            $sender = $this->userAt($users, $i);
            $receiver = $this->userAt($users, $i + 1);

            DB::table('messages')->insert([
                'sender' => $sender->id,
                'receiver' => $receiver->id,
                'added' => now()->subHours($i),
                'subject' => 'Perf test message #'.$i,
                'msg' => 'Performance test message content #'.$i,
                'unread' => 1,
                'location' => 1, // 1 = inbox
            ]);
        }
    }

    /**
     * Disable captcha for test login (k6 can't solve image captcha).
     */
    private function disableCaptcha(): void
    {
        DB::table('settings')
            ->where('name', 'security.iv')
            ->update(['value' => 'no']);

        Settings::resetCache();
    }

    /**
     * Disable MeiliSearch indexing — the perf-budget CI workflow
     * does not start MeiliSearch, so Scout observer would fail.
     */
    private function disableMeiliSearch(): void
    {
        DB::table('settings')
            ->where('name', 'meilisearch.enabled')
            ->update(['value' => 'no']);

        Settings::resetCache();
    }
}
