<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Database\Seeder;

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
        $owner = User::query()->where('username', 'sysop')->first()
            ?? User::query()->first()
            ?? User::factory()->create();

        if (Torrent::query()->count() === 0) {
            // The category must be a real seeded row — details.php maps
            // categories.mode to the search box id and a missing join
            // yields "Invalid search box: 0" (HTTP 500).
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

        if (Topic::query()->count() > 0) {
            return;
        }

        $forum = Forum::query()->first() ?? Forum::factory()->create();
        $topic = Topic::factory()->forum($forum)->author($owner)->create([
            'subject' => 'Browser smoke fixture topic',
        ]);
        $post = Post::factory()->topic($topic)->author($owner)->create();
        $topic->update(['firstpost' => $post->id, 'lastpost' => $post->id]);
    }
}
