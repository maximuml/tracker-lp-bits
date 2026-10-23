<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Enums\UserClass as UserClassEnum;
use App\Models\User;
use App\Services\ToolMaintenanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Pins ToolMaintenanceService behaviour — including the memoized
 * listUserAllPermissions path that is reset between worker cycles.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ToolMaintenanceServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ToolMaintenanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        ToolMaintenanceService::resetState();
        $this->service = app(ToolMaintenanceService::class);
    }

    protected function tearDown(): void
    {
        ToolMaintenanceService::resetState();

        parent::tearDown();
    }

    public function test_list_user_class_permissions_grows_with_class(): void
    {
        $peasant = $this->service->listUserClassPermissions(UserClassEnum::PEASANT->value);
        $sysop = $this->service->listUserClassPermissions(UserClassEnum::SYSOP->value);

        $this->assertIsArray($peasant);
        $this->assertIsArray($sysop);
        $this->assertGreaterThanOrEqual(count($peasant), count($sysop));
    }

    public function test_list_user_all_permissions_unknown_uid_returns_empty(): void
    {
        $this->assertSame([], $this->service->listUserAllPermissions(99999999));
    }

    public function test_list_user_all_permissions_maps_key_to_value(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $result = $this->service->listUserAllPermissions((int) $user->id);

        $this->assertSame(array_keys($result), array_values($result));
    }

    public function test_list_user_all_permissions_is_memoized(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $first = $this->service->listUserAllPermissions((int) $user->id);
        $second = $this->service->listUserAllPermissions((int) $user->id);

        $this->assertSame($first, $second);
    }

    public function test_reset_state_clears_permission_memo(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->service->listUserAllPermissions((int) $user->id);

        ToolMaintenanceService::resetState();

        $ref = new \ReflectionProperty(ToolMaintenanceService::class, 'uidPermissionsCached');
        $this->assertSame([], $ref->getValue());
    }

    public function test_get_notification_count_returns_expected_keys(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $result = $this->service->getNotificationCount($user);

        foreach (['attendance', 'news', 'message', 'poll'] as $key) {
            $this->assertArrayHasKey($key, $result);
            $this->assertIsInt($result[$key]);
        }
    }

    public function test_generate_unique_invite_hash_returns_unique_hashes(): void
    {
        $hashes = $this->service->generateUniqueInviteHash([], 5, 5);

        $this->assertCount(5, $hashes);
        $this->assertSame($hashes, array_unique($hashes));
        foreach ($hashes as $hash) {
            $this->assertSame(32, strlen($hash));
        }
    }

    public function test_generate_unique_invite_hash_throws_beyond_max_depth(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->service->generateUniqueInviteHash([], 5, 5, 11);
    }
}
