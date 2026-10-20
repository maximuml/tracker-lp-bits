<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression tests for GET-request state mutations (CSRF vector):
 * GET /web/shoutbox?del=N and ?sent=yes used to mutate the shoutbox
 * table, and GET /web/bookmark?torrentid=N toggled bookmarks — all
 * reachable via <img src> CSRF. Mutations are POST-only now.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class GetMutationHardeningTest extends TestCase
{
    use DatabaseTransactions;

    private function asNexusUser(User $user): static
    {
        $token = Str::random(40);
        $this->withNexusCookie($user);

        return $this->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token);
    }

    public function test_get_shoutbox_does_not_delete_message(): void
    {
        /** @var User $staff */
        $staff = User::factory()->create(['class' => 13]);
        $shoutId = (int) DB::table('shoutbox')->insertGetId([
            'userid' => $staff->id,
            'date' => time(),
            'text' => 'keep me',
            'type' => 0,
        ]);

        $this->asNexusUser($staff)->get('/web/shoutbox?del='.$shoutId);

        $this->assertDatabaseHas('shoutbox', ['id' => $shoutId]);
    }

    public function test_get_shoutbox_does_not_post_message(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->asNexusUser($user)->get('/web/shoutbox?sent=yes&shbox_text='.urlencode('csrf planted shout'));

        $this->assertDatabaseMissing('shoutbox', ['text' => 'csrf planted shout']);
    }

    public function test_get_bookmark_is_rejected_and_does_not_toggle(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();

        $this->asNexusUser($user)->get('/web/bookmark?torrentid='.$torrent->id)
            ->assertStatus(405);

        $this->assertDatabaseMissing('bookmarks', [
            'userid' => $user->id,
            'torrentid' => $torrent->id,
        ]);
    }

    public function test_post_bookmark_still_toggles(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();
        $token = Str::random(40);

        $this->withNexusCookie($user)
            ->withSession(['_token' => $token])
            ->post('/web/bookmark', ['torrentid' => $torrent->id, '_token' => $token])
            ->assertOk();

        $this->assertDatabaseHas('bookmarks', [
            'userid' => $user->id,
            'torrentid' => $torrent->id,
        ]);
    }
}
