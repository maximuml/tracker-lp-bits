<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\PollRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for PollRepository.
 *
 * Covers findForEdit(), lastPoll(), createOrUpdate(), listAll(),
 * findWithOptions(), countAnswers(), answers(), and userDisplayMap().
 */
final class PollRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private PollRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable FK checks — some tests insert pollanswers with arbitrary
        // user IDs that do not exist in the users table.
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('pollanswers')->delete();
        DB::table('polls')->delete();
        DB::table('users')->delete();

        $this->repository = new PollRepository;
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPoll(array $overrides = []): int
    {
        return (int) DB::table('polls')->insertGetId(array_merge([
            'added' => now()->toDateTimeString(),
            'question' => 'Test poll?',
            'option0' => 'Yes',
            'option1' => 'No',
        ], $overrides));
    }

    public function test_find_for_edit_returns_null_when_id_is_zero_or_negative(): void
    {
        $this->assertNull($this->repository->findForEdit(0));
        $this->assertNull($this->repository->findForEdit(-1));
    }

    public function test_find_for_edit_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->findForEdit(99999));
    }

    public function test_find_for_edit_returns_poll_data(): void
    {
        $id = $this->createPoll(['question' => 'Is this a test?']);

        $result = $this->repository->findForEdit($id);

        $this->assertIsArray($result);
        $this->assertSame($id, (int) $result['id']);
        $this->assertSame('Is this a test?', $result['question']);
    }

    public function test_last_poll_returns_null_when_no_polls(): void
    {
        $this->assertNull($this->repository->lastPoll());
    }

    public function test_last_poll_returns_most_recent(): void
    {
        $this->createPoll(['question' => 'Old poll', 'added' => '2025-01-01 00:00:00']);
        $this->createPoll(['question' => 'New poll', 'added' => '2025-06-01 00:00:00']);

        $result = $this->repository->lastPoll();

        $this->assertIsArray($result);
        $this->assertSame('New poll', $result['question']);
    }

    public function test_create_or_update_creates_new_poll(): void
    {
        $id = $this->repository->createOrUpdate(['question' => 'New question?', 'option0' => 'A', 'option1' => 'B']);

        $this->assertGreaterThan(0, $id);
        $row = DB::table('polls')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('New question?', $row->question);
    }

    public function test_create_or_update_updates_existing_poll(): void
    {
        $id = $this->createPoll(['question' => 'Old question']);

        $result = $this->repository->createOrUpdate(['question' => 'Updated question'], $id);

        $this->assertSame($id, $result);
        $this->assertSame('Updated question', DB::table('polls')->where('id', $id)->value('question'));
    }

    public function test_list_all_returns_polls_ordered_by_id_desc(): void
    {
        $id1 = $this->createPoll(['question' => 'First']);
        $id2 = $this->createPoll(['question' => 'Second']);

        $result = $this->repository->listAll();

        $this->assertCount(2, $result);
        $this->assertSame($id2, (int) $result[0]['id']);
        $this->assertSame($id1, (int) $result[1]['id']);
    }

    public function test_list_all_returns_empty_when_no_polls(): void
    {
        $this->assertSame([], $this->repository->listAll());
    }

    public function test_find_with_options_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->findWithOptions(99999));
    }

    public function test_find_with_options_returns_poll_data(): void
    {
        $id = $this->createPoll(['question' => 'Options test?']);

        $result = $this->repository->findWithOptions($id);

        $this->assertIsArray($result);
        $this->assertSame($id, (int) $result['id']);
    }

    public function test_count_answers_returns_zero_when_none(): void
    {
        $this->assertSame(0, $this->repository->countAnswers(99999));
    }

    public function test_count_answers_counts_only_selections_under_20(): void
    {
        $pollId = $this->createPoll();

        DB::table('pollanswers')->insert([
            ['pollid' => $pollId, 'userid' => 1, 'selection' => 0],
            ['pollid' => $pollId, 'userid' => 2, 'selection' => 5],
            ['pollid' => $pollId, 'userid' => 3, 'selection' => 20],
            ['pollid' => $pollId, 'userid' => 4, 'selection' => 25],
        ]);

        $this->assertSame(2, $this->repository->countAnswers($pollId));
    }

    public function test_answers_returns_paginated_rows_with_usernames(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();
        /** @var User $user2 */
        $user2 = User::factory()->create();
        $pollId = $this->createPoll();

        DB::table('pollanswers')->insert([
            ['pollid' => $pollId, 'userid' => $user1->id, 'selection' => 0],
            ['pollid' => $pollId, 'userid' => $user2->id, 'selection' => 1],
        ]);

        $result = $this->repository->answers($pollId, 0, 25);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('username', $result[0]);
    }

    public function test_answers_excludes_selections_20_or_above(): void
    {
        $pollId = $this->createPoll();

        DB::table('pollanswers')->insert([
            ['pollid' => $pollId, 'userid' => 1, 'selection' => 0],
            ['pollid' => $pollId, 'userid' => 2, 'selection' => 20],
        ]);

        $result = $this->repository->answers($pollId, 0, 25);

        $this->assertCount(1, $result);
    }

    public function test_answers_respects_offset_and_limit(): void
    {
        $pollId = $this->createPoll();

        for ($i = 0; $i < 3; $i++) {
            DB::table('pollanswers')->insert([
                'pollid' => $pollId, 'userid' => 100 + $i, 'selection' => 0,
            ]);
        }

        $page1 = $this->repository->answers($pollId, 0, 2);
        $page2 = $this->repository->answers($pollId, 2, 2);

        $this->assertCount(2, $page1);
        $this->assertCount(1, $page2);
    }

    public function test_user_display_map_returns_empty_for_empty_answers(): void
    {
        $this->assertSame([], $this->repository->userDisplayMap([]));
    }

    public function test_user_display_map_returns_map_for_valid_user_ids(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();
        /** @var User $user2 */
        $user2 = User::factory()->create();

        $answers = [
            ['userid' => $user1->id, 'selection' => 0],
            ['userid' => $user2->id, 'selection' => 1],
        ];

        $result = $this->repository->userDisplayMap($answers);

        $this->assertArrayHasKey($user1->id, $result);
        $this->assertArrayHasKey($user2->id, $result);
        $this->assertIsString($result[$user1->id]);
    }

    public function test_user_display_map_deduplicates_user_ids(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $answers = [
            ['userid' => $user->id, 'selection' => 0],
            ['userid' => $user->id, 'selection' => 1],
        ];

        $result = $this->repository->userDisplayMap($answers);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey($user->id, $result);
    }

    public function test_user_display_map_skips_invalid_ids(): void
    {
        $answers = [
            ['userid' => 0, 'selection' => 0],
            ['userid' => -1, 'selection' => 1],
        ];

        $this->assertSame([], $this->repository->userDisplayMap($answers));
    }
}
