<?php

declare(strict_types=1);

namespace Tests\Builders;

use App\Models\Category;
use App\Models\Peer;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * W3-05: Realistic torrent listing scenario builder.
 *
 * Creates a dataset that mimics a real torrent listing page:
 * - Multiple uploaders with different user classes
 * - Torrents spread across categories
 * - Peers (seeders/leechers) on each torrent
 * - Snatch records for download history
 * - Bookmarks and thanks for some torrents
 *
 * Usage:
 *   $scenario = TorrentListingScenario::create()
 *       ->withUploaders(3)
 *       ->withTorrentsPerUploader(10)
 *       ->withPeersPerTorrent(5)
 *       ->build();
 */
final class TorrentListingScenario
{
    /** @var Collection<int, User> */
    public Collection $uploaders;

    /** @var list<Torrent> */
    public array $torrents = [];

    /** @var list<Peer> */
    public array $peers = [];

    /** @var list<Snatch> */
    public array $snatches = [];

    private int $uploaderCount = 3;

    private int $torrentsPerUploader = 10;

    private int $peersPerTorrent = 5;

    private int $snatchesPerTorrent = 3;

    private bool $withBookmarks = true;

    private bool $withThanks = true;

    public static function create(): self
    {
        return new self;
    }

    public function withUploaders(int $count): self
    {
        $this->uploaderCount = $count;

        return $this;
    }

    public function withTorrentsPerUploader(int $count): self
    {
        $this->torrentsPerUploader = $count;

        return $this;
    }

    public function withPeersPerTorrent(int $count): self
    {
        $this->peersPerTorrent = $count;

        return $this;
    }

    public function withSnatchesPerTorrent(int $count): self
    {
        $this->snatchesPerTorrent = $count;

        return $this;
    }

    public function withBookmarks(bool $enabled = true): self
    {
        $this->withBookmarks = $enabled;

        return $this;
    }

    public function withThanks(bool $enabled = true): self
    {
        $this->withThanks = $enabled;

        return $this;
    }

    public function build(): self
    {
        // Create categories
        $categories = Category::factory()->count(3)->create();
        $categoryIds = $categories->pluck('id')->toArray();

        // Create uploaders
        $this->uploaders = User::factory()->count($this->uploaderCount)->create();
        $uploaderIds = $this->uploaders->pluck('id')->toArray();

        // Create extra downloader users for snatches (to avoid unique constraint)
        $snatchUserCount = max($this->snatchesPerTorrent, $this->uploaderCount);
        $snatchUsers = User::factory()->count($snatchUserCount)->create();
        $snatchUserIds = $snatchUsers->pluck('id')->toArray();
        $allUserIds = array_merge($uploaderIds, $snatchUserIds);

        // Create torrents for each uploader
        foreach ($this->uploaders as $uploader) {
            for ($i = 0; $i < $this->torrentsPerUploader; $i++) {
                $torrent = Torrent::factory()
                    ->owner($uploader)
                    ->category($categoryIds[array_rand($categoryIds)])
                    ->create([
                        'name' => "Torrent-{$uploader->id}-{$i} ".$this->fakerName(),
                    ]);

                $this->torrents[] = $torrent;

                // Create peers (mix of seeders and leechers)
                for ($p = 0; $p < $this->peersPerTorrent; $p++) {
                    $peer = Peer::factory()->create([
                        'torrent' => $torrent->id,
                        'userid' => $allUserIds[array_rand($allUserIds)],
                        'seeder' => $p < $this->peersPerTorrent / 2 ? 1 : 0,
                    ]);
                    $this->peers[] = $peer;
                }

                // Create snatch records (unique torrentid+userid)
                $shuffledSnatchIds = $snatchUserIds;
                shuffle($shuffledSnatchIds);
                for ($s = 0; $s < $this->snatchesPerTorrent; $s++) {
                    $snatchUserId = $shuffledSnatchIds[$s % count($shuffledSnatchIds)];

                    $snatch = Snatch::factory()->create([
                        'torrentid' => $torrent->id,
                        'userid' => $snatchUserId,
                    ]);
                    $this->snatches[] = $snatch;
                }

                // Create bookmarks for some torrents
                if ($this->withBookmarks && $i % 3 === 0) {
                    DB::table('bookmarks')->insert([
                        'torrentid' => $torrent->id,
                        'userid' => $this->uploaders[0]->id,
                    ]);
                }

                // Create thanks for some torrents
                if ($this->withThanks && $i % 4 === 0) {
                    $thankUserId = $snatchUserIds[0];
                    DB::table('thanks')->insert([
                        'torrentid' => $torrent->id,
                        'userid' => $thankUserId,
                    ]);
                }
            }
        }

        return $this;
    }

    public function torrentCount(): int
    {
        return count($this->torrents);
    }

    public function peerCount(): int
    {
        return count($this->peers);
    }

    public function snatchCount(): int
    {
        return count($this->snatches);
    }

    private function fakerName(): string
    {
        $words = ['Movie', 'TV', 'Music', 'Game', 'Software', 'Book', 'Anime'];

        return $words[array_rand($words)].' '.random_int(1000, 9999);
    }
}
