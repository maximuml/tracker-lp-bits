<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthorizationMatrixTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    /**
     * @return array<int, array{endpoint: string, method: string, required_ability: string|null}>
     */
    public static function apiEndpointsProvider(): array
    {
        return [
            // User endpoints
            ['/api/v1/usercp/settings', 'GET', 'usercp:settings'],
            ['/api/v1/user-me', 'GET', 'user:me'],
            ['/api/v1/user-publish-torrent', 'GET', 'user:torrents'],

            // Torrent endpoints
            ['/api/v1/torrents', 'GET', 'torrents:list'],
            ['/api/v1/search-box', 'GET', 'torrents:search-box'],

            // Bookmark endpoints
            ['/api/v1/bookmarks', 'POST', 'bookmarks:store'],
            ['/api/v1/bookmarks/delete', 'POST', 'bookmarks:delete'],

            // Message endpoints
            ['/api/v1/messages', 'GET', 'messages:list'],
            ['/api/v1/messages/unread', 'GET', 'messages:unread'],

            // News endpoints
            ['/api/v1/news', 'GET', 'news:list'],
            ['/api/v1/news-latest', 'GET', 'news:latest'],

            // Poll endpoints
            ['/api/v1/polls', 'GET', 'polls:list'],
            ['/api/v1/polls-latest', 'GET', 'polls:latest'],
        ];
    }

    /**
     * @dataProvider apiEndpointsProvider
     */
    #[DataProvider('apiEndpointsProvider')]
    public function test_unauthenticated_request_returns_401(string $endpoint, string $method, ?string $requiredAbility): void
    {
        $response = $this->json($method, $endpoint);
        $response->assertStatus(401);
    }

    /**
     * @dataProvider apiEndpointsProvider
     */
    #[DataProvider('apiEndpointsProvider')]
    public function test_authenticated_without_correct_ability_returns_403(string $endpoint, string $method, ?string $requiredAbility): void
    {
        if ($requiredAbility === null) {
            $this->markTestSkipped("No ability required for {$endpoint}");
        }

        $user = User::factory()->create();
        // Grant a different ability to ensure the required one is checked
        Sanctum::actingAs($user, ['some-other-ability']);

        $response = $this->json($method, $endpoint);
        // Should be 403 (forbidden) or 404 (route not found for that method)
        $this->assertContains(
            $response->status(),
            [403, 404],
            "Expected 403 or 404 for {$endpoint} without ability {$requiredAbility}, got {$response->status()}"
        );
    }

    /**
     * @dataProvider apiEndpointsProvider
     */
    #[DataProvider('apiEndpointsProvider')]
    public function test_authenticated_with_correct_ability_does_not_return_401(string $endpoint, string $method, ?string $requiredAbility): void
    {
        if ($requiredAbility === null) {
            $this->markTestSkipped("No ability required for {$endpoint}");
        }

        $user = User::factory()->create();
        Sanctum::actingAs($user, [$requiredAbility]);

        $response = $this->json($method, $endpoint);

        // Should NOT be 401 — the token is valid even if the user lacks
        // the underlying permission (which would give 403, not 401)
        $this->assertNotSame(401, $response->status(), "Expected non-401 for {$endpoint} with valid token");
    }

    public function test_state_changing_endpoints_reject_get(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['bookmarks:store']);

        // POST-only endpoints should reject GET
        $this->getJson('/api/v1/bookmarks')
            ->assertStatus(405);
    }
}
