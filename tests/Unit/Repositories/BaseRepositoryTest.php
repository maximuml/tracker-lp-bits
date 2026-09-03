<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\UserClass;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\BaseRepository;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for BaseRepository.
 *
 * Covers the protected helper methods via reflection:
 * allowedSortColumns(), getSortFieldAndType(), getPerPageFromRequest(),
 * handleAnonymous(), getUser(), and executeCommand().
 */
final class BaseRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private BaseRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('torrents')->delete();
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        Permissions::resetState();

        $this->repository = new BaseRepository;
    }

    protected function tearDown(): void
    {
        Permissions::resetState();
        parent::tearDown();
    }

    public function test_allowed_sort_columns_defaults_to_id(): void
    {
        $method = new \ReflectionMethod($this->repository, 'allowedSortColumns');
        $method->setAccessible(true);

        $this->assertSame(['id'], $method->invoke($this->repository));
    }

    public function test_get_sort_field_and_type_defaults_to_id_desc(): void
    {
        $method = new \ReflectionMethod($this->repository, 'getSortFieldAndType');
        $method->setAccessible(true);

        [$field, $type] = $method->invoke($this->repository, []);

        $this->assertSame('id', $field);
        $this->assertSame('desc', $type);
    }

    public function test_get_sort_field_and_type_falls_back_when_field_not_allowed(): void
    {
        $method = new \ReflectionMethod($this->repository, 'getSortFieldAndType');
        $method->setAccessible(true);

        [$field, $type] = $method->invoke($this->repository, ['sort_field' => 'evil', 'sort_type' => 'asc']);

        $this->assertSame('id', $field);
        $this->assertSame('asc', $type);
    }

    public function test_get_sort_field_and_type_accepts_asc(): void
    {
        $method = new \ReflectionMethod($this->repository, 'getSortFieldAndType');
        $method->setAccessible(true);

        [, $type] = $method->invoke($this->repository, ['sort_type' => 'asc']);

        $this->assertSame('asc', $type);
    }

    public function test_get_sort_field_and_type_defaults_to_desc_for_invalid_type(): void
    {
        $method = new \ReflectionMethod($this->repository, 'getSortFieldAndType');
        $method->setAccessible(true);

        [, $type] = $method->invoke($this->repository, ['sort_type' => 'invalid']);

        $this->assertSame('desc', $type);
    }

    public function test_get_per_page_from_request_returns_null_when_not_set(): void
    {
        $request = Request::create('/test', 'GET');

        $method = new \ReflectionMethod($this->repository, 'getPerPageFromRequest');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($this->repository, $request));
    }

    public function test_get_per_page_from_request_returns_value_when_under_cap(): void
    {
        $request = Request::create('/test', 'GET', ['per_page' => 50]);

        $method = new \ReflectionMethod($this->repository, 'getPerPageFromRequest');
        $method->setAccessible(true);

        $this->assertSame(50, (int) $method->invoke($this->repository, $request));
    }

    public function test_get_per_page_from_request_caps_at_100(): void
    {
        $request = Request::create('/test', 'GET', ['per_page' => 200]);

        $method = new \ReflectionMethod($this->repository, 'getPerPageFromRequest');
        $method->setAccessible(true);

        $this->assertSame(100, (int) $method->invoke($this->repository, $request));
    }

    public function test_handle_anonymous_returns_empty_when_user_is_null(): void
    {
        $method = new \ReflectionMethod($this->repository, 'handleAnonymous');
        $method->setAccessible(true);

        /** @var User $authenticator */
        $authenticator = User::factory()->create();

        $this->assertSame('', $method->invoke($this->repository, 'someuser', null, $authenticator));
    }

    public function test_handle_anonymous_returns_username_when_privacy_not_strong(): void
    {
        $method = new \ReflectionMethod($this->repository, 'handleAnonymous');
        $method->setAccessible(true);

        /** @var User $user */
        $user = User::factory()->create(['privacy' => 'normal']);
        /** @var User $authenticator */
        $authenticator = User::factory()->create();

        $this->assertSame($user->username, $method->invoke($this->repository, $user->username, $user, $authenticator));
    }

    public function test_handle_anonymous_shows_real_name_when_staff_leader_can_view(): void
    {
        $method = new \ReflectionMethod($this->repository, 'handleAnonymous');
        $method->setAccessible(true);

        /** @var User $user */
        $user = User::factory()->create(['privacy' => 'strong']);
        /** @var User $authenticator */
        $authenticator = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $result = $method->invoke($this->repository, (string) $user->username, $user, $authenticator);

        $this->assertStringContainsString((string) $user->username, $result);
    }

    public function test_handle_anonymous_shows_anonymous_when_cannot_view_and_different_user(): void
    {
        $method = new \ReflectionMethod($this->repository, 'handleAnonymous');
        $method->setAccessible(true);

        /** @var User $user */
        $user = User::factory()->create(['privacy' => 'strong']);
        /** @var User $authenticator */
        $authenticator = User::factory()->create();

        // Pre-set the permission cache so Permission::can returns false
        // without hitting the complex permission resolution logic.
        $this->setUserCanCache('viewanonymous', $authenticator->id, false);

        $result = $method->invoke($this->repository, (string) $user->username, $user, $authenticator);

        $this->assertStringNotContainsString((string) $user->username, $result);
    }

    public function test_handle_anonymous_shows_real_name_when_viewing_own_data(): void
    {
        $method = new \ReflectionMethod($this->repository, 'handleAnonymous');
        $method->setAccessible(true);

        /** @var User $user */
        $user = User::factory()->create(['privacy' => 'strong']);

        $this->setUserCanCache('viewanonymous', $user->id, false);

        $result = $method->invoke($this->repository, (string) $user->username, $user, $user);

        $this->assertStringContainsString((string) $user->username, $result);
    }

    public function test_handle_anonymous_with_torrent_anonymous_owner(): void
    {
        $method = new \ReflectionMethod($this->repository, 'handleAnonymous');
        $method->setAccessible(true);

        /** @var User $owner */
        $owner = User::factory()->create(['privacy' => 'normal']);
        /** @var User $authenticator */
        $authenticator = User::factory()->create(['class' => UserClass::STAFFLEADER->value]);

        $torrentId = (int) DB::table('torrents')->insertGetId([
            'name' => 'Test Torrent',
            'filename' => 'test.torrent',
            'save_as' => 'test',
            'category' => 1,
            'size' => 1024,
            'type' => 'single',
            'numfiles' => 1,
            'owner' => $owner->id,
            'info_hash' => random_bytes(20),
            'visible' => 1,
            'banned' => 0,
            'anonymous' => 1,
            'added' => now()->toDateTimeString(),
        ]);
        $torrent = Torrent::query()->findOrFail($torrentId);

        $result = $method->invoke($this->repository, (string) $owner->username, $owner, $authenticator, $torrent);

        $this->assertStringContainsString((string) $owner->username, $result);
    }

    public function test_get_user_returns_null_when_user_is_null(): void
    {
        $method = new \ReflectionMethod($this->repository, 'getUser');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($this->repository, null));
    }

    public function test_get_user_returns_user_instance_when_given_user(): void
    {
        $method = new \ReflectionMethod($this->repository, 'getUser');
        $method->setAccessible(true);

        /** @var User $user */
        $user = User::factory()->create();

        $result = $method->invoke($this->repository, $user);

        $this->assertSame($user->id, $result->id);
    }

    public function test_get_user_finds_by_id_with_common_fields(): void
    {
        $method = new \ReflectionMethod($this->repository, 'getUser');
        $method->setAccessible(true);

        /** @var User $user */
        $user = User::factory()->create();

        $result = $method->invoke($this->repository, $user->id);

        $this->assertSame($user->id, $result->id);
        $this->assertSame($user->username, $result->username);
    }

    public function test_get_user_finds_by_id_with_custom_fields(): void
    {
        $method = new \ReflectionMethod($this->repository, 'getUser');
        $method->setAccessible(true);

        /** @var User $user */
        $user = User::factory()->create();

        $result = $method->invoke($this->repository, $user->id, ['id', 'username']);

        $this->assertSame($user->id, $result->id);
        $this->assertSame($user->username, $result->username);
    }

    public function test_execute_command_returns_string_output(): void
    {
        $method = new \ReflectionMethod($this->repository, 'executeCommand');
        $method->setAccessible(true);

        $result = $method->invoke($this->repository, 'echo hello');

        $this->assertIsString($result);
        $this->assertSame('hello', trim((string) $result));
    }

    public function test_execute_command_returns_array_when_format_is_array(): void
    {
        $method = new \ReflectionMethod($this->repository, 'executeCommand');
        $method->setAccessible(true);

        $result = $method->invoke($this->repository, 'echo hello', 'json');

        $this->assertIsArray($result);
    }

    /**
     * Set the Permissions static cache so Permission::can returns a known value.
     */
    private function setUserCanCache(string $permission, int $uid, bool $value): void
    {
        $reflection = new \ReflectionClass(Permissions::class);
        $cache = $reflection->getProperty('userCanCache');
        $cache->setAccessible(true);
        $current = $cache->getValue();
        $current[$permission][$uid] = $value;
        $cache->setValue(null, $current);
    }
}
