<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Invite;
use App\Models\News;
use App\Models\Poll;
use App\Models\User;
use App\Repositories\ToolRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit tests for ToolRepository.
 *
 * Covers getNotificationCount(), generateUniqueInviteHash(),
 * getBackupExportPathDefault().
 */
final class ToolRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private ToolRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ToolRepository;
    }

    public function test_get_notification_count_returns_array_with_expected_keys(): void
    {
        $user = User::factory()->create();

        $result = $this->repository->getNotificationCount($user);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('attendance', $result);
        $this->assertArrayHasKey('news', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('poll', $result);
    }

    public function test_get_notification_count_reports_unread_news(): void
    {
        $user = User::factory()->create(['last_home' => '1970-01-01 00:00:00']);
        News::query()->create([
            'body' => 'Test news',
            'userid' => $user->id,
            'added' => now()->toDateTimeString(),
        ]);

        $result = $this->repository->getNotificationCount($user);

        $this->assertGreaterThanOrEqual(1, $result['news']);
    }

    public function test_get_notification_count_reports_zero_news_when_up_to_date(): void
    {
        $user = User::factory()->create(['last_home' => now()->addDay()->toDateTimeString()]);

        $result = $this->repository->getNotificationCount($user);

        $this->assertSame(0, $result['news']);
    }

    public function test_get_notification_count_reports_unvoted_polls(): void
    {
        $user = User::factory()->create();
        Poll::factory()->create();

        $result = $this->repository->getNotificationCount($user);

        $this->assertGreaterThanOrEqual(1, $result['poll']);
    }

    public function test_generate_unique_invite_hash_returns_requested_count(): void
    {
        $result = $this->repository->generateUniqueInviteHash([], 5, 5);

        $this->assertCount(5, $result);
        foreach ($result as $hash) {
            $this->assertSame(32, strlen($hash));
        }
    }

    public function test_generate_unique_invite_hash_avoids_existing_hashes(): void
    {
        $existingHash = Str::random(32);
        Invite::query()->create([
            'hash' => $existingHash,
            'inviter' => 1,
            'invitee' => 'test@example.com',
            'time_invited' => now()->toDateTimeString(),
            'valid' => 1,
        ]);

        $result = $this->repository->generateUniqueInviteHash([], 3, 3);

        $this->assertCount(3, $result);
        $this->assertNotContains($existingHash, $result);
    }

    public function test_generate_unique_invite_hash_returns_unique_values(): void
    {
        $result = $this->repository->generateUniqueInviteHash([], 10, 10);

        $this->assertCount(10, $result);
        $this->assertSame(10, count(array_unique($result)));
    }

    public function test_get_backup_export_path_default_returns_string(): void
    {
        $path = $this->repository->getBackupExportPathDefault();

        $this->assertIsString($path);
        $this->assertNotEmpty($path);
    }
}
