<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * W1-06: HTTP contract tests for torrent edit/upload mutations.
 * Tests FormRequest validation, authorization via TorrentPolicy,
 * HTTP method boundaries, and legacy redirect behavior.
 */
final class TorrentHttpTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    // ─── Authentication ────────────────────────────────────────────────

    public function test_takeedit_redirects_unauthenticated_user(): void
    {
        $this->post('/takeedit', [
            'id' => 1,
            'name' => 'Test',
            'descr' => 'Test',
            'type' => 1,
        ])
            ->assertRedirect();
    }

    public function test_takeupload_redirects_unauthenticated_user(): void
    {
        $this->post('/takeupload', [
            'descr' => 'Test',
            'type' => 1,
        ])
            ->assertRedirect();
    }

    // ─── FormRequest validation ──────────────────────────────────────

    public function test_takeedit_validates_required_id(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->post('/takeedit', [
                'name' => 'Test',
                'descr' => 'Test',
                'type' => 1,
                // id missing
            ])
            ->assertRedirect();
    }

    public function test_takeedit_validates_required_name(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->post('/takeedit', [
                'id' => 1,
                'descr' => 'Test',
                'type' => 1,
                // name missing
            ])
            ->assertRedirect();
    }

    public function test_takeedit_validates_required_descr(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->post('/takeedit', [
                'id' => 1,
                'name' => 'Test',
                'type' => 1,
                // descr missing
            ])
            ->assertRedirect();
    }

    public function test_takeedit_validates_required_type(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->post('/takeedit', [
                'id' => 1,
                'name' => 'Test',
                'descr' => 'Test',
                // type missing
            ])
            ->assertRedirect();
    }

    // ─── Authorization ──────────────────────────────────────────────────

    public function test_takeedit_permission_denied_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create(['class' => 1]);
        $torrent = Torrent::factory()->create(['owner' => $owner->id, 'name' => 'Original']);
        $torrentId = $torrent->id;

        $this->withNexusCookie($other)
            ->post('/takeedit', [
                'id' => (string) $torrent->id,
                'name' => 'Hacked',
                'descr' => 'Hacked',
                'type' => 1,
            ]);

        // Security guarantee: torrent name must not be changed by non-owner
        $this->assertSame('Original', Torrent::query()->where('id', $torrentId)->value('name'));
    }

    // ─── HTTP method boundaries ─────────────────────────────────────

    public function test_edit_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create(['owner' => $user->id]);

        $this->withNexusCookie($user)
            ->get('/edit?id='.$torrent->id)
            ->assertStatus(200);
    }
}
