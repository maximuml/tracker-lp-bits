<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\HitAndRunMode;
use App\Enums\UserClass as UserClassEnum;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression tests for the API authorization audit (Phase 1).
 *
 * API clients are issued wildcard ('*') Sanctum tokens at login, so the
 * `ability:` middleware alone cannot protect staff endpoints — every staff
 * scope must additionally map to a legacy permission via
 * RoutePermissionEnum::toPermissionEnum(). These tests pin that contract:
 * a regular USER with a wildcard token must get 403 on every staff surface
 * and must still reach the user-facing endpoints.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ApiAuthorizationAuditTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
    }

    private function createUser(int $class = UserClassEnum::USER->value): User
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => $class]);

        return $user;
    }

    // -------------------------------------------------------------------
    // Staff endpoints: wildcard token + regular user -> 403
    // -------------------------------------------------------------------

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function staffOnlyEndpoints(): array
    {
        return [
            // Settings — additionally SYSOP-gated inside the controller
            'settings.index' => ['GET', '/api/v1/settings'],
            'settings.store' => ['POST', '/api/v1/settings'],

            // Admin user surface
            'users.index' => ['GET', '/api/v1/users'],
            'users.store' => ['POST', '/api/v1/users'],
            'user-classes' => ['GET', '/api/v1/user-classes'],
            'user-invite-info' => ['GET', '/api/v1/user-invite-info'],
            'user-mod-comment' => ['GET', '/api/v1/user-mod-comment'],

            // Hit & run admin
            'hr.index' => ['GET', '/api/v1/hr'],
            'hr-status' => ['GET', '/api/v1/hr-status'],
            'hr-pardon' => ['PUT', '/api/v1/hr-pardon'],
            'hr-delete' => ['PUT', '/api/v1/hr-delete'],

            // Agent allow/deny admin
            'agent-allows.index' => ['GET', '/api/v1/agent-allows'],
            'all-agent-allows' => ['GET', '/api/v1/all-agent-allows'],
            'agent-check' => ['POST', '/api/v1/agent-check'],
            'agent-denies.index' => ['GET', '/api/v1/agent-denies'],

            // Dashboard
            'dashboard.system-info' => ['GET', '/api/v1/dashboard/system-info'],
        ];
    }

    #[DataProvider('staffOnlyEndpoints')]
    public function test_regular_user_with_wildcard_token_gets_403_on_staff_endpoints(string $method, string $uri): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user, ['*']);

        $response = $this->json($method, $uri);

        $this->assertSame(
            403,
            $response->status(),
            "Expected 403 for {$method} {$uri} as regular user with wildcard token, got {$response->status()}",
        );
    }

    // -------------------------------------------------------------------
    // User-facing endpoints: wildcard token + regular user -> reachable
    // -------------------------------------------------------------------

    /**
     * Endpoints a regular tracker user must reach with a wildcard token.
     * Status may be 200 or a validation failure — anything but 401/403
     * proves the ability + permission gate let the request through.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function userFacingEndpoints(): array
    {
        return [
            'user-me' => ['GET', '/api/v1/user-me'],
            'user-base' => ['GET', '/api/v1/user-base'],
            'profile.self' => ['GET', '/api/v1/profile'],
            'user-publish-torrent' => ['GET', '/api/v1/user-publish-torrent'],
            'user-seeding-torrent' => ['GET', '/api/v1/user-seeding-torrent'],
            'user-leeching-torrent' => ['GET', '/api/v1/user-leeching-torrent'],
            'user-finished-torrent' => ['GET', '/api/v1/user-finished-torrent'],
            'comments.store' => ['POST', '/api/v1/comments'],
            'thanks.store' => ['POST', '/api/v1/thanks'],
            'rewards.store' => ['POST', '/api/v1/rewards'],
            'attend' => ['POST', '/api/v1/attend'],
        ];
    }

    #[DataProvider('userFacingEndpoints')]
    public function test_regular_user_reaches_user_facing_endpoints(string $method, string $uri): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user, ['*']);

        $response = $this->json($method, $uri);

        $this->assertNotSame(401, $response->status(), "Unexpected 401 for {$method} {$uri}");
        $this->assertNotSame(403, $response->status(), "Unexpected 403 for {$method} {$uri} — user-facing endpoint must not be permission-gated");
    }

    // -------------------------------------------------------------------
    // /api/settings — SYSOP gate, secret redaction, schema allowlist
    // -------------------------------------------------------------------

    public function test_settings_index_is_sysop_only_below_sysop_class(): void
    {
        foreach ([UserClassEnum::MODERATOR->value, UserClassEnum::ADMINISTRATOR->value] as $class) {
            $user = $this->createUser($class);
            Sanctum::actingAs($user, ['*']);

            $this->getJson('/api/v1/settings')->assertStatus(403);
        }
    }

    public function test_settings_index_returns_settings_for_sysop(): void
    {
        $sysop = $this->createUser(UserClassEnum::SYSOP->value);
        Sanctum::actingAs($sysop, ['*']);

        $response = $this->getJson('/api/v1/settings')
            ->assertStatus(200)
            ->assertJsonPath('ret', 0);

        $this->assertIsArray($response->json('data'));
        $this->assertNotEmpty($response->json('data'));
    }

    public function test_settings_index_redacts_secrets(): void
    {
        Setting::query()->updateOrInsert(
            ['name' => 'security.login_secret'],
            ['value' => 'audit-secret-value', 'autoload' => 1],
        );
        Setting::query()->updateOrInsert(
            ['name' => 'smtp.accountpassword'],
            ['value' => 'audit-smtp-password', 'autoload' => 1],
        );
        Setting::query()->updateOrInsert(
            ['name' => 'security.login_secret_lifetime'],
            ['value' => '86400', 'autoload' => 1],
        );

        $sysop = $this->createUser(UserClassEnum::SYSOP->value);
        Sanctum::actingAs($sysop, ['*']);

        $response = $this->getJson('/api/v1/settings')->assertStatus(200);

        $this->assertNull($response->json('data.security.login_secret'));
        $this->assertNull($response->json('data.smtp.accountpassword'));
        // Metadata next to a secret stays visible — only the leaf is redacted.
        $this->assertSame('86400', $response->json('data.security.login_secret_lifetime'));
    }

    public function test_settings_store_rejects_unknown_prefix(): void
    {
        $sysop = $this->createUser(UserClassEnum::SYSOP->value);
        Sanctum::actingAs($sysop, ['*']);

        $response = $this->postJson('/api/v1/settings', [
            'nosuchprefix' => ['whatever' => '1'],
        ]);

        $response->assertStatus(422);
    }

    public function test_settings_store_rejects_non_array_prefix(): void
    {
        $sysop = $this->createUser(UserClassEnum::SYSOP->value);
        Sanctum::actingAs($sysop, ['*']);

        $this->postJson('/api/v1/settings', ['main' => 'not-an-array'])
            ->assertStatus(422);
    }

    public function test_settings_store_persists_valid_hr_batch(): void
    {
        $sysop = $this->createUser(UserClassEnum::SYSOP->value);
        Sanctum::actingAs($sysop, ['*']);

        // The hr schema declares string-typed fields (settings are stored
        // as strings) — integers are rejected by SettingsSchemaValidator.
        $payload = [
            'hr' => [
                'mode' => HitAndRunMode::MANUAL->value,
                'inspect_time' => '15',
                'seed_time_minimum' => '10',
                'ignore_when_ratio_reach' => '1',
                'ban_user_when_counts_reach' => '3',
            ],
        ];

        $this->postJson('/api/v1/settings', $payload)
            ->assertStatus(200)
            ->assertJsonPath('ret', 0);

        $this->assertDatabaseHas('settings', [
            'name' => 'hr.mode',
            'value' => HitAndRunMode::MANUAL->value,
        ]);
    }

    // -------------------------------------------------------------------
    // /api/attend — POST only
    // -------------------------------------------------------------------

    public function test_attend_rejects_get(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/attend')->assertStatus(405);
    }

    public function test_attend_records_attendance_on_post(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/attend')
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.uid', $user->id);
    }

    // -------------------------------------------------------------------
    // /api/profile — own-scope profile access for regular users
    // -------------------------------------------------------------------

    public function test_profile_returns_own_profile_for_regular_user(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/profile')
            ->assertStatus(200)
            ->assertJsonPath('ret', 0)
            ->assertJsonPath('data.data.id', $user->id);
    }

    public function test_profile_omits_email_of_other_users(): void
    {
        $viewer = $this->createUser();
        $other = $this->createUser();
        Sanctum::actingAs($viewer, ['*']);

        $response = $this->getJson("/api/v1/profile/{$other->id}");

        $response->assertStatus(200);
        $this->assertNotSame($other->email, $response->json('data.data.email'));
    }
}
