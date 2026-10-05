<?php

declare(strict_types=1);

namespace Tests\Integration\Livewire;

use App\Livewire\CommentSection;
use App\Models\Comment;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class CommentSectionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
    }

    public function test_render_lists_comments(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        Comment::factory()->create(['torrent' => $torrent->id, 'user' => $user->id, 'text' => 'livewire comment check']);

        Livewire::test(CommentSection::class, ['parentId' => $torrent->id])
            ->assertSee('livewire comment check')
            ->assertOk();
    }

    public function test_post_creates_comment_and_clears_input(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        $this->actingAs($user, 'nexus-web');

        Livewire::test(CommentSection::class, ['parentId' => $torrent->id])
            ->set('text', 'hello from livewire')
            ->call('post')
            ->assertSet('text', '')
            ->assertSet('status', '');

        $this->assertDatabaseHas('comments', ['torrent' => $torrent->id, 'text' => 'hello from livewire']);
    }

    public function test_post_empty_text_sets_status(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        $this->actingAs($user, 'nexus-web');

        Livewire::test(CommentSection::class, ['parentId' => $torrent->id])
            ->set('text', '   ')
            ->call('post')
            ->assertSet('status', (string) __('legacy/comment.std_comment_body_empty'));

        $this->assertSame(0, Comment::query()->where('torrent', $torrent->id)->count());
    }

    public function test_post_as_guest_does_not_comment(): void
    {
        $torrent = Torrent::factory()->create();

        Livewire::test(CommentSection::class, ['parentId' => $torrent->id])
            ->set('text', 'guest comment')
            ->call('post');

        $this->assertSame(0, DB::table('comments')->where('torrent', $torrent->id)->count());
    }

    public function test_offer_render_lists_comments(): void
    {
        $user = User::factory()->create();
        $offerId = $this->insertOffer((int) $user->id);
        Comment::factory()->create(['offer' => $offerId, 'user' => $user->id, 'text' => 'offer livewire check']);

        Livewire::test(CommentSection::class, ['parentId' => $offerId, 'type' => 'offer'])
            ->assertSee('offer livewire check')
            ->assertOk();
    }

    public function test_offer_post_creates_comment(): void
    {
        $user = User::factory()->create();
        $offerId = $this->insertOffer((int) $user->id);
        $this->actingAs($user, 'nexus-web');

        Livewire::test(CommentSection::class, ['parentId' => $offerId, 'type' => 'offer'])
            ->set('text', 'offer comment via livewire')
            ->call('post')
            ->assertSet('text', '')
            ->assertSet('status', '');

        $this->assertDatabaseHas('comments', ['offer' => $offerId, 'text' => 'offer comment via livewire']);
    }

    private function insertOffer(int $userId): int
    {
        return (int) DB::table('offers')->insertGetId([
            'userid' => $userId,
            'name' => 'Test Offer',
            'descr' => 'A test offer description',
            'added' => now()->toDateTimeString(),
            'allowedtime' => now()->toDateTimeString(),
            'yeah' => 0,
            'against' => 0,
            'category' => 1,
            'comments' => 0,
            'allowed' => 1,
        ]);
    }
}
