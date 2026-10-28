<?php

namespace Tests\Unit\Auth;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Exceptions\InsufficientPermissionException;
use App\Models\User;
use App\Services\PermissionChecker;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT, TestCategory::MUTATION)]
class PermissionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @param  list<array{0: string, 1: bool, 2: int}>  $calls
     */
    private function mockChecker(array $calls): void
    {
        $checker = Mockery::mock(PermissionChecker::class);
        foreach ($calls as [$permission, $result, $uid]) {
            $checker->shouldReceive('userCan')
                ->with($permission, false, $uid)
                ->andReturn($result);
        }
        app()->instance(PermissionChecker::class, $checker);
    }

    private function user(int $id = 7, bool $uploadpos = false): User
    {
        $user = new User;
        $user->id = $id;
        $user->uploadpos = $uploadpos;

        return $user;
    }

    public function test_can_returns_true_when_checker_allows(): void
    {
        $this->mockChecker([['upload', true, 7]]);

        $this->assertTrue(Permission::can(PermissionEnum::UPLOAD, $this->user()));
    }

    public function test_can_returns_false_when_checker_denies(): void
    {
        $this->mockChecker([['upload', false, 7]]);

        $this->assertFalse(Permission::can(PermissionEnum::UPLOAD, $this->user()));
    }

    public function test_can_passes_zero_uid_for_guest(): void
    {
        $this->mockChecker([['viewanonymous', false, 0]]);

        $this->assertFalse(Permission::canViewAnonymous(null));
    }

    public function test_assert_can_throws_on_deny(): void
    {
        $this->mockChecker([['torrentmanage', false, 7]]);

        $this->expectException(InsufficientPermissionException::class);

        Permission::assertCan(PermissionEnum::TORRENT_MANAGE, $this->user());
    }

    public function test_assert_can_passes_on_allow(): void
    {
        $this->mockChecker([['torrentmanage', true, 7]]);

        Permission::assertCan(PermissionEnum::TORRENT_MANAGE, $this->user());

        $this->addToAssertionCount(1);
    }

    public function test_can_upload_to_normal_section_requires_uploadpos_and_permission(): void
    {
        $this->mockChecker([['upload', true, 7]]);

        $this->assertTrue(Permission::canUploadToNormalSection($this->user(uploadpos: true)));
        $this->assertFalse(Permission::canUploadToNormalSection($this->user(uploadpos: false)));
    }

    public function test_can_upload_to_normal_section_is_false_for_guest(): void
    {
        $this->assertFalse(Permission::canUploadToNormalSection(null));
    }

    public function test_domain_wrappers_delegate_to_matching_permission_enum(): void
    {
        $this->mockChecker([
            ['beanonymous', true, 7],
            ['torrentmanage', true, 7],
            ['torrent-delete', true, 7],
            ['movetorrent', true, 7],
            ['viewanonymous', true, 7],
        ]);

        $user = $this->user();
        $this->assertTrue(Permission::canBeAnonymous($user));
        $this->assertTrue(Permission::canManageTorrent($user));
        $this->assertTrue(Permission::canDeleteTorrent($user));
        $this->assertTrue(Permission::canMoveTorrent($user));
        $this->assertTrue(Permission::canViewAnonymous($user));
    }
}
