<?php

namespace Database\Seeders;

use App\Enums\SitelogSecurityLevel;
use App\Models\Category;
use App\Models\File;
use App\Models\Forum;
use App\Models\HitAndRun;
use App\Models\Post;
use App\Models\Snatch;
use App\Models\Topic;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Minimal browser-smoke fixture data (ADR 0016). The base seeders ship
 * reference tables only — no torrents and no forum topics — while the
 * Playwright suite exercises /details.php and forums → viewtopic.
 * Idempotent: existing rows satisfy the fixtures, nothing is duplicated.
 */
class BrowserSmokeSeeder extends Seeder
{
    public function run(): void
    {
        /** @var User $owner */
        $owner = User::query()->where('username', 'sysop')->first()
            ?? User::query()->first()
            ?? User::factory()->create();

        if (Torrent::query()->count() === 0) {
            // The category must be a real seeded row — details.php maps
            // categories.mode to the search box id and a missing join
            // yields "Invalid search box: 0" (HTTP 500).
            /** @var Category $category */
            $category = Category::query()->first() ?? Category::factory()->create();
            // MeiliSearch may not be running when this seeder runs (CI
            // seeds before the search container is up) — the browser
            // suite does not need the index, so skip Scout syncing.
            Torrent::withoutSyncingToSearch(
                fn () => Torrent::factory()->owner($owner)->create([
                    'name' => 'Browser Smoke Fixture Torrent',
                    'category' => $category->id,
                ]),
            );
        }

        $hrTorrentName = 'Browser Smoke H&R Fixture Torrent';
        $hrTorrent = Torrent::query()->where('name', $hrTorrentName)->first();
        if (! $hrTorrent instanceof Torrent) {
            /** @var Category $category */
            $category = Category::query()->first() ?? Category::factory()->create();
            $hrTorrent = Torrent::withoutSyncingToSearch(
                fn () => Torrent::factory()->owner($owner)->create([
                    'name' => $hrTorrentName,
                    'category' => $category->id,
                ]),
            );
        }

        // File-list and peer fixtures: details.php renders an empty file
        // list and an empty peer list without them.
        foreach (['smoke-fixture-video.mkv' => 1_500_000_000, 'smoke-fixture-info.nfo' => 4_096] as $filename => $size) {
            File::query()->firstOrCreate(
                ['torrent' => $hrTorrent->id, 'filename' => $filename],
                ['size' => $size],
            );
        }
        if (! DB::table('peers')->where('torrent', $hrTorrent->id)->where('peer_id', '-qB4250-smokefixtur')->exists()) {
            DB::table('peers')->insert([
                'torrent' => $hrTorrent->id,
                'peer_id' => '-qB4250-smokefixtur',
                'ip' => '127.0.0.1',
                'port' => 51413,
                'uploaded' => 900_000_000,
                'downloaded' => 647_865_608,
                'to_go' => 0,
                'started' => now()->subDay()->toDateTimeString(),
                'last_action' => now()->toDateTimeString(),
                'userid' => $owner->id,
                'agent' => 'qBittorrent/4.2.5',
                'finishedat' => 0,
                'downloadoffset' => 0,
                'uploadoffset' => 0,
                'passkey' => str_repeat('0', 32),
                'ipv4' => '127.0.0.1',
                'ipv6' => '::1',
                'connectable' => 1,
                'seeder' => 1,
            ]);
        }

        $hrSnatch = Snatch::query()->firstOrCreate(
            ['torrentid' => $hrTorrent->id, 'userid' => $owner->id],
            [
                'ip' => '127.0.0.1',
                'port' => 51413,
                'uploaded' => 900000000,
                'downloaded' => 647865608,
                'to_go' => 0,
                'seedtime' => 18 * 3600,
                'leechtime' => 3600,
                'last_action' => now()->toDateTimeString(),
                'startdat' => now()->subDays(2)->toDateTimeString(),
                'completedat' => now()->subDay()->toDateTimeString(),
                'finished' => 1,
            ],
        );
        $hrRow = HitAndRun::query()->firstOrCreate(
            ['uid' => $owner->id, 'torrent_id' => $hrTorrent->id],
            [
                'snatched_id' => $hrSnatch->id,
                'status' => 1,
                'comment' => 'Browser smoke H&R fixture comment',
            ],
        );
        if ($hrSnatch->hit_and_run_id !== $hrRow->id) {
            $hrSnatch->update(['hit_and_run_id' => $hrRow->id]);
        }

        $logPrefix = 'Browser smoke pager fixture ';
        $existingLogs = DB::table('sitelog')
            ->where('txt', 'like', $logPrefix.'%')
            ->pluck('txt')
            ->all();
        $logRows = [];
        for ($index = 1; $index <= 51; $index++) {
            $text = $logPrefix.$index;
            if (in_array($text, $existingLogs, true)) {
                continue;
            }

            $logRows[] = [
                'added' => now()->subSeconds($index)->toDateTimeString(),
                'txt' => $text,
                'security_level' => SitelogSecurityLevel::NORMAL->value,
            ];
        }
        if ($logRows !== []) {
            DB::table('sitelog')->insert($logRows);
        }

        if (Topic::query()->count() > 0) {
            return;
        }

        /** @var Forum $forum */
        $forum = Forum::query()->first() ?? Forum::factory()->create();
        /** @var Topic $topic */
        $topic = Topic::factory()->forum($forum)->author($owner)->create([
            'subject' => 'Browser smoke fixture topic',
        ]);
        /** @var Post $post */
        $post = Post::factory()->topic($topic)->author($owner)->create();
        $topic->update(['firstpost' => $post->id, 'lastpost' => $post->id]);
    }
}
