<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\Permission\RoutePermissionEnum;
use App\Repositories\TokenRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for TokenRepository.
 *
 * Covers listUserTokenPermissions() (formatted and raw) and
 * listUserTokenPermissionAllowed() (reads from the settings table).
 *
 * The listUserTokenPermissionAllowed test is ordered first because
 * listUserTokenPermissions(true) indirectly calls Setting::get() via
 * Locale::trans() → Setting::getDefaultLang(), which populates a
 * function-level static cache in Setting::get() that cannot be reset.
 * By running the Setting-dependent test first, the static is still null
 * and the updateOrInsert + Cache::forget forces a fresh DB read.
 */
final class TokenRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private TokenRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new TokenRepository;
    }

    public function test_list_user_token_permission_allowed_returns_formatted_settings(): void
    {
        $allowed = ['torrent:list', 'torrent:view'];

        DB::table('settings')->updateOrInsert(
            ['name' => 'permission.user_token_allowed'],
            [
                'value' => json_encode($allowed, JSON_THROW_ON_ERROR),
                'autoload' => 1,
                'updated_at' => now()->toDateTimeString(),
            ]
        );

        Cache::forget('nexus_settings_in_laravel');

        $result = $this->repository->listUserTokenPermissionAllowed();

        $this->assertArrayHasKey('torrent:list', $result);
        $this->assertArrayHasKey('torrent:view', $result);
        $this->assertSame('Fetch torrent list', $result['torrent:list']);
        $this->assertSame('View torrent detail', $result['torrent:view']);
    }

    public function test_list_user_token_permissions_unformatted_returns_all_enum_values(): void
    {
        $result = $this->repository->listUserTokenPermissions(false);

        $expected = array_map(
            static fn (RoutePermissionEnum $permission) => $permission->value,
            RoutePermissionEnum::cases()
        );

        $this->assertSame($expected, $result);
    }

    public function test_list_user_token_permissions_formatted_returns_keyed_array(): void
    {
        $result = $this->repository->listUserTokenPermissions(true);

        $this->assertNotEmpty($result);
        $this->assertSame(array_keys($result), $this->repository->listUserTokenPermissions(false));
    }

    public function test_list_user_token_permissions_formatted_uses_translation_values(): void
    {
        $result = $this->repository->listUserTokenPermissions(true);

        $firstPermission = RoutePermissionEnum::TORRENT_LIST->value;

        $this->assertArrayHasKey($firstPermission, $result);
        $this->assertSame('Fetch torrent list', $result[$firstPermission]);
    }

    public function test_list_user_token_permissions_defaults_to_formatted(): void
    {
        $formatted = $this->repository->listUserTokenPermissions();
        $explicit = $this->repository->listUserTokenPermissions(true);

        $this->assertSame($explicit, $formatted);
    }
}
