<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Repositories\ForumModRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for ForumModRepository.
 *
 * Covers getModeratorArray() and isModeratorOfForum().
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ForumModRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private ForumModRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(ForumModRepository::class);
    }

    /** @param  array<string, mixed>  $overrides */
    private function makeForum(array $overrides = []): int
    {
        return (int) DB::table('forums')->insertGetId(array_merge([
            'name' => 'Mod Test',
            'description' => '',
            'sort' => 1,
            'forid' => 0,
        ], $overrides));
    }

    public function test_get_moderator_array_groups_by_forum(): void
    {
        $forumId = $this->makeForum(['name' => 'Mod Array']);
        DB::table('forummods')->insert([
            ['forumid' => $forumId, 'userid' => 1],
            ['forumid' => $forumId, 'userid' => 2],
        ]);

        $array = $this->repository->getModeratorArray();

        $this->assertArrayHasKey($forumId, $array);
        $this->assertCount(2, $array[$forumId]);
    }

    public function test_is_moderator_of_forum_returns_true_for_moderator(): void
    {
        $forumId = $this->makeForum(['name' => 'IsMod Forum']);
        DB::table('forummods')->insert(['forumid' => $forumId, 'userid' => 1]);

        $this->assertTrue($this->repository->isModeratorOfForum($forumId, 1));
    }

    public function test_is_moderator_of_forum_returns_false_for_non_moderator(): void
    {
        $forumId = $this->makeForum(['name' => 'NotMod Forum']);
        DB::table('forummods')->insert(['forumid' => $forumId, 'userid' => 1]);

        $this->assertFalse($this->repository->isModeratorOfForum($forumId, 999));
    }
}
