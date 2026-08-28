<?php

namespace Tests\Unit;

use App\Enums\TorrentVisible;
use App\Models\Bookmark;
use App\Models\Category;
use App\Models\Comment;
use App\Models\File;
use App\Models\Forum;
use App\Models\Message;
use App\Models\Peer;
use App\Models\Post;
use App\Models\SearchBox;
use App\Models\Snatch;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Ensure the new model factories produce valid, storable records.
 */
final class DatabaseFactoriesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null']);
    }

    public function test_core_factories_create_records(): void
    {
        $user = User::factory()->create();
        $this->assertGreaterThan(0, $user->id);
        $this->assertNotEmpty($user->passkey);

        $admin = User::factory()->admin()->create();
        $this->assertSame((int) User::CLASS_SYSOP, (int) $admin->class);

        $searchBox = SearchBox::factory()->create();
        $this->assertGreaterThan(0, $searchBox->id);

        $category = Category::factory()->create();
        $this->assertGreaterThan(0, $category->id);
        $this->assertGreaterThan(0, $category->mode);

        $torrent = Torrent::factory()->create();
        $this->assertGreaterThan(0, $torrent->id);
        $this->assertGreaterThan(0, $torrent->owner);
        $this->assertGreaterThan(0, $torrent->category);
        $this->assertNotEmpty($torrent->info_hash);

        $peer = Peer::factory()->create();
        $this->assertGreaterThan(0, $peer->id);
        $this->assertSame(32, strlen((string) $peer->passkey));

        $seeder = Peer::factory()->seeder()->create();
        $this->assertSame('yes', $seeder->seeder);

        $comment = Comment::factory()->create();
        $this->assertGreaterThan(0, $comment->id);

        $message = Message::factory()->create();
        $this->assertGreaterThan(0, $message->id);

        $bookmark = Bookmark::factory()->create();
        $this->assertGreaterThan(0, $bookmark->id);

        $forum = Forum::factory()->create();
        $this->assertGreaterThan(0, $forum->id);

        $topic = Topic::factory()->create();
        $this->assertGreaterThan(0, $topic->id);

        $post = Post::factory()->create();
        $this->assertGreaterThan(0, $post->id);

        $file = File::factory()->create();
        $this->assertGreaterThan(0, $file->id);

        $tag = Tag::factory()->create();
        $this->assertGreaterThan(0, $tag->id);

        $snatch = Snatch::factory()->create();
        $this->assertGreaterThan(0, $snatch->id);
    }

    public function test_factory_state_methods_work(): void
    {
        $torrent = Torrent::factory()->banned()->create();
        $this->assertSame(Torrent::BANNED_YES, $torrent->banned);

        $invisible = Torrent::factory()->invisible()->create();
        $this->assertSame(TorrentVisible::NO->value, $invisible->visible);

        $user = User::factory()->create();
        $torrent2 = Torrent::factory()->owner($user)->create();
        $this->assertSame($user->id, $torrent2->owner);

        $peer = Peer::factory()->user($user)->torrent($torrent2)->create();
        $this->assertSame($user->id, $peer->userid);
        $this->assertSame($torrent2->id, $peer->torrent);

        $comment = Comment::factory()->author($user)->torrent($torrent2)->create();
        $this->assertSame($user->id, $comment->user);
        $this->assertSame($torrent2->id, $comment->torrent);

        $receiver = User::factory()->create();
        $message = Message::factory()->between($user, $receiver)->read()->create();
        $this->assertSame($user->id, $message->sender);
        $this->assertSame($receiver->id, $message->receiver);
        $this->assertSame('no', $message->unread);
    }
}
