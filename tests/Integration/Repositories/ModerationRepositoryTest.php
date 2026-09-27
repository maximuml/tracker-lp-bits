<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Enums\ReportType;
use App\Models\User;
use App\Repositories\ModerationRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for ModerationRepository.
 *
 * Covers reportExists(), createReport(), getForumPost(), countReports(),
 * getReports(), findMatchingBans().
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ModerationRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private ModerationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('reports')->delete();
        DB::table('bans')->delete();
        DB::table('iplog')->delete();
        DB::table('peers')->delete();
        DB::table('posts')->delete();
        DB::table('topics')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->repository = new ModerationRepository;
    }

    public function test_report_exists_returns_true_when_found(): void
    {
        $this->insertReport(10, 500, 'torrent');

        $this->assertTrue($this->repository->reportExists(10, 500, 'torrent'));
    }

    public function test_report_exists_returns_false_when_not_found(): void
    {
        $this->insertReport(10, 500, 'torrent');

        $this->assertFalse($this->repository->reportExists(10, 501, 'torrent'));
        $this->assertFalse($this->repository->reportExists(11, 500, 'torrent'));
        $this->assertFalse($this->repository->reportExists(10, 500, 'user'));
    }

    public function test_create_report_inserts_row(): void
    {
        $this->repository->createReport([
            'addedby' => 10,
            'added' => now()->toDateTimeString(),
            'reportid' => 500,
            'type' => 'torrent',
            'reason' => 'spam',
            'dealtby' => 0,
            'dealtwith' => 0,
        ]);

        $this->assertSame(1, DB::table('reports')->where('addedby', 10)->count());
    }

    public function test_get_forum_post_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getForumPost(999999));
    }

    public function test_get_forum_post_returns_topic_and_post_data(): void
    {
        /** @var User $topicUser */
        $topicUser = User::factory()->create();
        /** @var User $postUser */
        $postUser = User::factory()->create();
        $forumId = (int) DB::table('forums')->insertGetId([
            'name' => 'test-forum-'.substr(md5((string) mt_rand()), 0, 8),
            'description' => 'test',
            'minclassread' => 0,
            'minclasswrite' => 0,
            'minclasscreate' => 0,
            'forid' => 0,
        ]);
        $topicId = (int) DB::table('topics')->insertGetId([
            'userid' => $topicUser->id,
            'subject' => 'Test Topic',
            'forumid' => $forumId,
            'firstpost' => 0,
            'lastpost' => 0,
        ]);
        $postId = (int) DB::table('posts')->insertGetId([
            'topicid' => $topicId,
            'userid' => $postUser->id,
            'added' => now()->toDateTimeString(),
            'body' => 'hello',
            'ori_body' => 'hello',
        ]);

        $result = $this->repository->getForumPost($postId);

        $this->assertNotNull($result);
        $this->assertSame($topicId, (int) $result['topicid']);
        $this->assertSame('Test Topic', $result['subject']);
        $this->assertSame($postUser->id, (int) $result['postuserid']);
    }

    public function test_count_reports_returns_total(): void
    {
        $this->insertReport(1, 100, 'torrent');
        $this->insertReport(2, 200, 'user');

        $this->assertSame(2, $this->repository->countReports());
    }

    public function test_count_reports_returns_zero_when_empty(): void
    {
        $this->assertSame(0, $this->repository->countReports());
    }

    public function test_get_reports_orders_by_dealtwith_then_id_desc(): void
    {
        $this->insertReport(1, 100, 'torrent', 0);
        $id2 = $this->insertReport(2, 200, 'user', 0);
        $id3 = $this->insertReport(3, 300, 'post', 1);

        $reports = $this->repository->getReports(0, 10);

        // dealtwith=0 first (ordered by id desc), then dealtwith=1
        $this->assertSame($id2, (int) $reports[0]['id']);
        $this->assertSame($id3, (int) $reports[2]['id']);
    }

    public function test_get_reports_respects_offset_and_limit(): void
    {
        $this->insertReport(1, 100, 'torrent');
        $this->insertReport(2, 200, 'user');
        $this->insertReport(3, 300, 'post');

        $reports = $this->repository->getReports(1, 1);

        $this->assertCount(1, $reports);
    }

    public function test_find_matching_bans_returns_overlapping_ranges(): void
    {
        $this->insertBan(0, 100, 'ban one');
        $this->insertBan(200, 300, 'ban two');

        $bans = $this->repository->findMatchingBans(50);

        $this->assertCount(1, $bans);
        $this->assertSame('ban one', $bans[0]['comment']);
    }

    public function test_find_matching_bans_returns_empty_when_no_match(): void
    {
        $this->insertBan(0, 100, 'ban one');

        $bans = $this->repository->findMatchingBans(500);

        $this->assertSame([], $bans);
    }

    private function insertReport(int $addedBy, int $reportId, string $type, int $dealtwith = 0): int
    {
        return (int) DB::table('reports')->insertGetId([
            'addedby' => $addedBy,
            'added' => now()->toDateTimeString(),
            'reportid' => $reportId,
            'type' => ReportType::fromStringSafe($type)->value,
            'reason' => 'test',
            'dealtby' => 0,
            'dealtwith' => $dealtwith,
        ]);
    }

    private function insertBan(int $first, int $last, string $comment, string $added = '2025-01-01 00:00:00'): int
    {
        return (int) DB::table('bans')->insertGetId([
            'added' => $added,
            'addedby' => 1,
            'comment' => $comment,
            'first' => $first,
            'last' => $last,
        ]);
    }
}
