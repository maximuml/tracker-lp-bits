<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Repositories\ForumModRepository;
use App\Repositories\ForumRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for ForumModRepository.
 *
 * Covers replaceModerators(), getModeratorArray(), isModeratorOfForum().
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ForumModRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private ForumModRepository $repository;

    private ForumRepository $forumRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(ForumModRepository::class);
        $this->forumRepository = app(ForumRepository::class);
    }

    /** @param  array<string, mixed>  $overrides */
    private function makeForum(array $overrides = []): int
    {
        return $this->forumRepository->createForum(array_merge([
            'name' => 'Mod Test',
            'description' => '',
            'sort' => 1,
            'forid' => 0,
        ], $overrides));
    }

    public function test_replace_moderators_sets_new_moderators(): void
    {
        $forumId = $this->makeForum();

        $this->repository->replaceModerators($forumId, [1, 2, 3]);

        $mods = DB::table('forummods')->where('forumid', $forumId)->get();
        $this->assertCount(3, $mods);
    }

    public function test_replace_moderators_replaces_existing(): void
    {
        $forumId = $this->makeForum(['name' => 'Mod Replace']);

        $this->repository->replaceModerators($forumId, [1, 2]);
        $this->repository->replaceModerators($forumId, [3]);

        $mods = DB::table('forummods')->where('forumid', $forumId)->get();
        $this->assertCount(1, $mods);
        $this->assertSame(3, $mods->first()->userid);
    }

    public function test_replace_moderators_respects_limit(): void
    {
        $forumId = $this->makeForum(['name' => 'Mod Limit']);

        $this->repository->replaceModerators($forumId, [1, 2, 3, 4, 5], 2);

        $mods = DB::table('forummods')->where('forumid', $forumId)->get();
        $this->assertCount(2, $mods);
    }

    public function test_get_moderator_array_groups_by_forum(): void
    {
        $forumId = $this->makeForum(['name' => 'Mod Array']);
        $this->repository->replaceModerators($forumId, [1, 2]);

        $array = $this->repository->getModeratorArray();

        $this->assertArrayHasKey($forumId, $array);
        $this->assertCount(2, $array[$forumId]);
    }

    public function test_is_moderator_of_forum_returns_true_for_moderator(): void
    {
        $forumId = $this->makeForum(['name' => 'IsMod Forum']);
        $this->repository->replaceModerators($forumId, [1]);

        $this->assertTrue($this->repository->isModeratorOfForum($forumId, 1));
    }

    public function test_is_moderator_of_forum_returns_false_for_non_moderator(): void
    {
        $forumId = $this->makeForum(['name' => 'NotMod Forum']);
        $this->repository->replaceModerators($forumId, [1]);

        $this->assertFalse($this->repository->isModeratorOfForum($forumId, 999));
    }
}
