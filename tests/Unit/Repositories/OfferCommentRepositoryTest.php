<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\OfferCommentRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for OfferCommentRepository.
 *
 * Covers deleteOfferComments(), getLastComment(), countComments(), getComments().
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class OfferCommentRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private OfferCommentRepository $repository;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('offervotes')->delete();
        DB::table('offers')->delete();
        DB::table('comments')->where('offer', '>', 0)->delete();
        DB::table('staffmessages')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->repository = new OfferCommentRepository;

        /** @var User $user */
        $user = User::factory()->create();
        $this->userId = $user->id;
    }

    public function test_delete_offer_comments_removes_comments(): void
    {
        $id = $this->insertOffer('Delete Comments');
        DB::table('comments')->insert([
            'user' => $this->userId,
            'offer' => $id,
            'text' => 'test comment',
            'added' => now()->toDateTimeString(),
        ]);

        $count = $this->repository->deleteOfferComments($id);

        $this->assertSame(1, $count);
        $this->assertSame(0, DB::table('comments')->where('offer', $id)->count());
    }

    public function test_get_last_comment_returns_null_when_no_comments(): void
    {
        $id = $this->insertOffer('No Comments');

        $this->assertNull($this->repository->getLastComment($id));
    }

    public function test_get_last_comment_returns_latest_comment(): void
    {
        $id = $this->insertOffer('With Comments');
        DB::table('comments')->insert([
            'user' => $this->userId,
            'offer' => $id,
            'text' => 'older comment',
            'added' => '2025-01-01 00:00:00',
        ]);
        DB::table('comments')->insert([
            'user' => $this->userId,
            'offer' => $id,
            'text' => 'newer comment',
            'added' => '2025-06-01 00:00:00',
        ]);

        $result = $this->repository->getLastComment($id);

        $this->assertNotNull($result);
        $this->assertSame('newer comment', $result['text']);
    }

    public function test_count_comments_returns_zero_when_none(): void
    {
        $id = $this->insertOffer('Count Zero');

        $this->assertSame(0, $this->repository->countComments($id));
    }

    public function test_count_comments_returns_count(): void
    {
        $id = $this->insertOffer('Count Comments');
        DB::table('comments')->insert([
            'user' => $this->userId,
            'offer' => $id,
            'text' => 'comment 1',
            'added' => now()->toDateTimeString(),
        ]);
        DB::table('comments')->insert([
            'user' => $this->userId,
            'offer' => $id,
            'text' => 'comment 2',
            'added' => now()->toDateTimeString(),
        ]);

        $this->assertSame(2, $this->repository->countComments($id));
    }

    public function test_get_comments_returns_paginated_collection(): void
    {
        $id = $this->insertOffer('Get Comments');
        DB::table('comments')->insert([
            'user' => $this->userId,
            'offer' => $id,
            'text' => 'comment 1',
            'added' => now()->toDateTimeString(),
        ]);

        $comments = $this->repository->getComments($id, 0, 10)->all();

        $this->assertCount(1, $comments);
        $this->assertSame('comment 1', $comments[0]->text);
    }

    private function insertOffer(string $name, int $yeah = 0, int $against = 0, int $category = 0, ?int $userId = null): int
    {
        return (int) DB::table('offers')->insertGetId([
            'userid' => $userId ?? $this->userId,
            'name' => $name,
            'added' => now()->toDateTimeString(),
            'yeah' => $yeah,
            'against' => $against,
            'category' => $category,
            'comments' => 0,
            'allowed' => 1,
        ]);
    }

    private function ensureCategory(): int
    {
        return (int) DB::table('categories')->insertGetId([
            'mode' => 1,
            'class_name' => 'test',
            'name' => 'Test Category',
            'image' => 'test.gif',
            'sort_index' => 0,
        ]);
    }
}
