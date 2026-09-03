<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\CommentType;
use App\Models\User;
use App\Repositories\CommentRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for CommentRepository.
 *
 * Covers getParent(), getLatest(), countLatest(), getQuote(), getForEdit(),
 * getForDelete(), getForViewOriginal(), getCommentPmSetting(), getList(),
 * create(), update(), and delete().
 *
 * The 'request' comment type is not tested because the requests table
 * does not exist in the test database schema.
 */
final class CommentRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private CommentRepository $repository;

    private User $user;

    private int $torrentId;

    private int $offerId;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('comments')->delete();
        DB::table('torrents')->delete();
        DB::table('offers')->delete();
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->repository = new CommentRepository;

        /** @var User $user */
        $user = User::factory()->create();
        $this->user = $user;

        $this->torrentId = $this->createTorrent($user->id);
        $this->offerId = $this->createOffer($user->id);
    }

    public function test_get_parent_returns_null_when_torrent_not_found(): void
    {
        $this->assertNull($this->repository->getParent(99999, 'torrent'));
    }

    public function test_get_parent_returns_array_for_torrent(): void
    {
        $result = $this->repository->getParent($this->torrentId, 'torrent');

        $this->assertNotNull($result);
        $this->assertSame('Test Torrent', $result['name']);
        $this->assertSame($this->user->id, (int) $result['owner']);
    }

    public function test_get_parent_returns_array_for_offer(): void
    {
        $result = $this->repository->getParent($this->offerId, 'offer');

        $this->assertNotNull($result);
        $this->assertSame('Test Offer', $result['name']);
        $this->assertSame($this->user->id, (int) $result['owner']);
    }

    public function test_get_latest_returns_comments_ordered_by_id_desc(): void
    {
        $this->createComment($this->user->id, $this->torrentId, 'torrent', 'First');
        $this->createComment($this->user->id, $this->offerId, 'offer', 'Second');

        $result = $this->repository->getLatest(10, 0);

        $this->assertCount(2, $result);
        $row0 = (array) $result[0];
        $row1 = (array) $result[1];
        $this->assertSame('Second', $row0['text']);
        $this->assertSame('First', $row1['text']);
    }

    public function test_get_latest_respects_limit_and_offset(): void
    {
        $this->createComment($this->user->id, $this->torrentId, 'torrent', 'A');
        $this->createComment($this->user->id, $this->torrentId, 'torrent', 'B');
        $this->createComment($this->user->id, $this->torrentId, 'torrent', 'C');

        $result = $this->repository->getLatest(1, 1);

        $this->assertCount(1, $result);
        $row = (array) $result[0];
        $this->assertSame('B', $row['text']);
    }

    public function test_get_latest_includes_parent_name_and_type(): void
    {
        $this->createComment($this->user->id, $this->torrentId, 'torrent', 'Torrent comment');

        $result = $this->repository->getLatest(10, 0);

        $row = (array) $result[0];
        $this->assertSame('Test Torrent', $row['parent_name']);
        $this->assertSame('torrent', $row['parent_type']);
        $this->assertSame($this->torrentId, (int) $row['parent_id']);
    }

    public function test_count_latest_returns_total_count(): void
    {
        $this->createComment($this->user->id, $this->torrentId, 'torrent', 'A');
        $this->createComment($this->user->id, $this->offerId, 'offer', 'B');

        $this->assertSame(2, $this->repository->countLatest());
    }

    public function test_count_latest_returns_zero_when_empty(): void
    {
        $this->assertSame(0, $this->repository->countLatest());
    }

    public function test_get_quote_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getQuote(99999));
    }

    public function test_get_quote_returns_text_and_username(): void
    {
        $commentId = $this->createComment($this->user->id, $this->torrentId, 'torrent', 'Quote me');

        $result = $this->repository->getQuote($commentId);

        $this->assertNotNull($result);
        $this->assertSame('Quote me', $result['text']);
        $this->assertSame($this->user->username, $result['username']);
    }

    public function test_get_for_edit_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getForEdit(99999, 'torrent'));
    }

    public function test_get_for_edit_returns_array_for_torrent(): void
    {
        $commentId = $this->createComment($this->user->id, $this->torrentId, 'torrent', 'Edit me');

        $result = $this->repository->getForEdit($commentId, 'torrent');

        $this->assertNotNull($result);
        $this->assertSame('Edit me', $result['text']);
        $this->assertSame('Test Torrent', $result['name']);
        $this->assertSame($this->torrentId, (int) $result['parent_id']);
    }

    public function test_get_for_edit_returns_array_for_offer(): void
    {
        $commentId = $this->createComment($this->user->id, $this->offerId, 'offer', 'Edit offer comment');

        $result = $this->repository->getForEdit($commentId, 'offer');

        $this->assertNotNull($result);
        $this->assertSame('Edit offer comment', $result['text']);
        $this->assertSame('Test Offer', $result['name']);
        $this->assertSame($this->offerId, (int) $result['parent_id']);
    }

    public function test_get_for_delete_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getForDelete(99999, 'torrent'));
    }

    public function test_get_for_delete_returns_pid_and_user(): void
    {
        $commentId = $this->createComment($this->user->id, $this->torrentId, 'torrent', 'Delete me');

        $result = $this->repository->getForDelete($commentId, 'torrent');

        $this->assertNotNull($result);
        $this->assertSame($this->torrentId, (int) $result['pid']);
        $this->assertSame($this->user->id, (int) $result['user']);
    }

    public function test_get_for_view_original_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getForViewOriginal(99999, 'torrent'));
    }

    public function test_get_for_view_original_returns_array_for_torrent(): void
    {
        $commentId = $this->createComment($this->user->id, $this->torrentId, 'torrent', 'View me');

        $result = $this->repository->getForViewOriginal($commentId, 'torrent');

        $this->assertNotNull($result);
        $this->assertSame('View me', $result['text']);
        $this->assertSame('Test Torrent', $result['name']);
    }

    public function test_get_comment_pm_setting_returns_bool(): void
    {
        // The column defaults to 1; explicitly set to 0 first.
        DB::table('users')->where('id', $this->user->id)->update(['commentpm' => 0]);
        $this->assertFalse($this->repository->getCommentPmSetting($this->user->id));

        DB::table('users')->where('id', $this->user->id)->update(['commentpm' => 1]);
        $this->assertTrue($this->repository->getCommentPmSetting($this->user->id));
    }

    public function test_get_list_returns_paginated_comments_for_torrent(): void
    {
        $this->createComment($this->user->id, $this->torrentId, 'torrent', 'A');
        $this->createComment($this->user->id, $this->torrentId, 'torrent', 'B');
        $this->createComment($this->user->id, $this->offerId, 'offer', 'C');

        $request = Request::create('/comments', 'GET', [
            'type' => CommentType::TORRENT->value,
            'parent_id' => $this->torrentId,
        ]);

        $paginator = $this->repository->getList($request, $this->user);

        $this->assertCount(2, $paginator->items());
    }

    public function test_get_list_returns_paginated_comments_for_offer(): void
    {
        $this->createComment($this->user->id, $this->offerId, 'offer', 'Offer comment');

        $request = Request::create('/comments', 'GET', [
            'type' => CommentType::OFFER->value,
            'parent_id' => $this->offerId,
        ]);

        $paginator = $this->repository->getList($request, $this->user);

        $this->assertCount(1, $paginator->items());
    }

    public function test_create_for_torrent_inserts_comment_and_increments_count(): void
    {
        $commentId = $this->repository->create($this->torrentId, 'torrent', 'New torrent comment', $this->user->id);

        $this->assertGreaterThan(0, $commentId);
        $this->assertSame(1, DB::table('comments')->where('id', $commentId)->count());

        $torrentComments = (int) DB::table('torrents')->where('id', $this->torrentId)->value('comments');
        $this->assertSame(1, $torrentComments);
    }

    public function test_create_for_offer_inserts_comment_and_increments_count(): void
    {
        $commentId = $this->repository->create($this->offerId, 'offer', 'New offer comment', $this->user->id);

        $this->assertGreaterThan(0, $commentId);
        $this->assertSame(1, DB::table('comments')->where('id', $commentId)->count());

        $offerComments = (int) DB::table('offers')->where('id', $this->offerId)->value('comments');
        $this->assertSame(1, $offerComments);
    }

    public function test_create_updates_user_last_comment(): void
    {
        $this->repository->create($this->torrentId, 'torrent', 'comment', $this->user->id);

        $lastComment = DB::table('users')->where('id', $this->user->id)->value('last_comment');
        $this->assertNotNull($lastComment);
    }

    public function test_update_modifies_comment_text_and_sets_editdate(): void
    {
        $commentId = $this->createComment($this->user->id, $this->torrentId, 'torrent', 'Original text');

        $this->repository->update($commentId, 'Edited text', $this->user->id);

        $row = DB::table('comments')->where('id', $commentId)->first();
        $this->assertNotNull($row);
        $this->assertSame('Edited text', $row->text);
        $this->assertSame($this->user->id, (int) $row->editedby);
        $this->assertNotNull($row->editdate);
    }

    public function test_delete_for_torrent_removes_comment_and_decrements_count(): void
    {
        $this->repository->create($this->torrentId, 'torrent', 'comment', $this->user->id);
        $commentId = $this->repository->create($this->torrentId, 'torrent', 'another', $this->user->id);

        $this->assertSame(2, (int) DB::table('torrents')->where('id', $this->torrentId)->value('comments'));

        $result = $this->repository->delete($commentId, 'torrent', $this->torrentId);

        $this->assertTrue($result);
        $this->assertSame(0, DB::table('comments')->where('id', $commentId)->count());
        $this->assertSame(1, (int) DB::table('torrents')->where('id', $this->torrentId)->value('comments'));
    }

    public function test_delete_for_offer_removes_comment_and_decrements_count(): void
    {
        $this->repository->create($this->offerId, 'offer', 'comment', $this->user->id);
        $commentId = $this->repository->create($this->offerId, 'offer', 'another', $this->user->id);

        $this->assertSame(2, (int) DB::table('offers')->where('id', $this->offerId)->value('comments'));

        $result = $this->repository->delete($commentId, 'offer', $this->offerId);

        $this->assertTrue($result);
        $this->assertSame(0, DB::table('comments')->where('id', $commentId)->count());
        $this->assertSame(1, (int) DB::table('offers')->where('id', $this->offerId)->value('comments'));
    }

    public function test_delete_returns_false_when_comment_not_found(): void
    {
        $result = $this->repository->delete(99999, 'torrent', $this->torrentId);

        $this->assertFalse($result);
    }

    private function createTorrent(int $ownerId): int
    {
        return (int) DB::table('torrents')->insertGetId([
            'name' => 'Test Torrent',
            'filename' => 'test.torrent',
            'save_as' => 'test',
            'category' => 1,
            'size' => 1024,
            'type' => 'single',
            'numfiles' => 1,
            'owner' => $ownerId,
            'info_hash' => random_bytes(20),
            'visible' => 1,
            'banned' => 0,
            'added' => now()->toDateTimeString(),
            'comments' => 0,
        ]);
    }

    private function createOffer(int $userId): int
    {
        return (int) DB::table('offers')->insertGetId([
            'userid' => $userId,
            'name' => 'Test Offer',
            'added' => now()->toDateTimeString(),
            'allowed' => 'allowed',
            'comments' => 0,
        ]);
    }

    private function createComment(int $userId, int $parentId, string $type, string $text): int
    {
        $data = [
            'user' => $userId,
            'added' => now()->toDateTimeString(),
            'text' => $text,
            'ori_text' => $text,
        ];

        if ($type === 'torrent') {
            $data['torrent'] = $parentId;
        } else {
            $data['offer'] = $parentId;
        }

        return (int) DB::table('comments')->insertGetId($data);
    }
}
