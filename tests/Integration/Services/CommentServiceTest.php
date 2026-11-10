<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Services\CommentService;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * CommentService::post() takes the actor as an explicit User argument —
 * callers (Legacy controller, Livewire component) resolve it from
 * ActorContext, not the default auth guard. The torrent branch must
 * therefore authorize via Gate::forUser($user): a bare Gate::authorize()
 * resolves the default 'web' session guard, which is empty on
 * /livewire/update requests and denied every torrent comment (403).
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class CommentServiceTest extends TestCase
{
    use DatabaseTransactions;

    private CommentService $comments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->comments = app(CommentService::class);
    }

    public function test_post_torrent_comment_without_guard_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();

        // No actingAs()/guard user on purpose: reproduces the /livewire/update
        // condition where the default 'web' guard has no authenticated user.
        $id = $this->comments->post($user, 'torrent', (int) $torrent->id, 'service-level comment');

        $this->assertGreaterThan(0, $id);
        $this->assertDatabaseHas('comments', [
            'id' => $id,
            'torrent' => $torrent->id,
            'user' => $user->id,
            'text' => 'service-level comment',
        ]);
    }

    public function test_post_torrent_comment_denies_parked_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['parked' => 1]);
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();

        try {
            $this->comments->post($user, 'torrent', (int) $torrent->id, 'parked comment');
            $this->fail('expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_post_torrent_comment_missing_torrent_is_404(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->expectException(NotFoundHttpException::class);

        $this->comments->post($user, 'torrent', 999999, 'ghost torrent comment');
    }

    public function test_post_torrent_comment_flood_control(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['last_comment' => date('Y-m-d H:i:s', time() - 5)]);
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();

        try {
            $this->comments->post($user, 'torrent', (int) $torrent->id, 'flood comment');
            $this->fail('expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_authorize_comment_offer_type_skips_torrent_policy(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $offerId = (int) DB::table('offers')->insertGetId([
            'userid' => $user->id,
            'name' => 'Svc Offer',
            'descr' => 'desc',
            'added' => now()->toDateTimeString(),
            'allowedtime' => now()->toDateTimeString(),
            'yeah' => 0,
            'against' => 0,
            'category' => 1,
            'comments' => 0,
            'allowed' => 1,
        ]);

        $this->comments->authorizeComment($user, 'offer', $offerId);

        $this->assertTrue(true);
    }
}
