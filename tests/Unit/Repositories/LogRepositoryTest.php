<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\News;
use App\Models\User;
use App\Repositories\LogRepository;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for LogRepository.
 *
 * Covers countSiteLog(), getSiteLog(), countChronicle(), getChronicle(),
 * getChronicleById(), addChronicle(), updateChronicle(), deleteChronicle(),
 * getGenericById(), countNews(), getNews(), getPollCount(),
 * getPollsExceptFirst(), deletePoll(), and getPollVoteCounts().
 *
 * Site log tests run without authentication, so the confidential-log
 * filter restricts results to security_level='normal' only.
 */
final class LogRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private LogRepository $repository;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        Permissions::resetState();
        News::flushEventListeners();

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('pollanswers')->delete();
        DB::table('polls')->delete();
        DB::table('news')->delete();
        DB::table('chronicle')->delete();
        DB::table('sitelog')->delete();
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        /** @var User $user */
        $user = User::factory()->create();
        $this->userId = $user->id;

        $this->repository = new LogRepository;
    }

    public function test_count_site_log_returns_zero_when_empty(): void
    {
        $this->assertSame(0, $this->repository->countSiteLog([]));
    }

    public function test_count_site_log_counts_only_normal_without_auth(): void
    {
        $this->insertSiteLog('normal action', 'normal');
        $this->insertSiteLog('mod action', 'mod');

        $this->assertSame(1, $this->repository->countSiteLog([]));
    }

    public function test_count_site_log_filters_by_query_text(): void
    {
        $this->insertSiteLog('user logged in', 'normal');
        $this->insertSiteLog('user logged out', 'normal');

        $this->assertSame(1, $this->repository->countSiteLog(['query' => 'logged in']));
    }

    public function test_get_site_log_returns_ordered_by_added_desc(): void
    {
        $this->insertSiteLog('older log', 'normal', '2025-01-01 00:00:00');
        $this->insertSiteLog('newer log', 'normal', '2025-06-01 00:00:00');

        $result = $this->repository->getSiteLog([], 0, 10);

        $this->assertCount(2, $result);
        $this->assertSame('newer log', $result[0]['txt']);
        $this->assertSame('older log', $result[1]['txt']);
    }

    public function test_get_site_log_respects_offset_and_limit(): void
    {
        $this->insertSiteLog('log A', 'normal', '2025-01-01 00:00:00');
        $this->insertSiteLog('log B', 'normal', '2025-02-01 00:00:00');
        $this->insertSiteLog('log C', 'normal', '2025-03-01 00:00:00');

        $result = $this->repository->getSiteLog([], 1, 1);

        $this->assertCount(1, $result);
        $this->assertSame('log B', $result[0]['txt']);
    }

    public function test_count_chronicle_returns_zero_when_empty(): void
    {
        $this->assertSame(0, $this->repository->countChronicle(''));
    }

    public function test_count_chronicle_counts_all_with_empty_query(): void
    {
        $this->insertChronicle(1, 'first entry');
        $this->insertChronicle(1, 'second entry');

        $this->assertSame(2, $this->repository->countChronicle(''));
    }

    public function test_count_chronicle_filters_by_query(): void
    {
        $this->insertChronicle(1, 'alpha chronicle');
        $this->insertChronicle(1, 'beta chronicle');

        $this->assertSame(1, $this->repository->countChronicle('alpha'));
    }

    public function test_get_chronicle_returns_ordered_by_added_desc(): void
    {
        $this->insertChronicle(1, 'older', '2025-01-01 00:00:00');
        $this->insertChronicle(1, 'newer', '2025-06-01 00:00:00');

        $result = $this->repository->getChronicle('', 0, 10);

        $this->assertCount(2, $result);
        $this->assertSame('newer', $result[0]['txt']);
        $this->assertSame('older', $result[1]['txt']);
    }

    public function test_get_chronicle_respects_offset_and_limit(): void
    {
        $this->insertChronicle(1, 'A', '2025-01-01 00:00:00');
        $this->insertChronicle(1, 'B', '2025-02-01 00:00:00');
        $this->insertChronicle(1, 'C', '2025-03-01 00:00:00');

        $result = $this->repository->getChronicle('', 1, 1);

        $this->assertCount(1, $result);
        $this->assertSame('B', $result[0]['txt']);
    }

    public function test_get_chronicle_by_id_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getChronicleById(99999));
    }

    public function test_get_chronicle_by_id_returns_array_when_found(): void
    {
        $id = $this->insertChronicle(1, 'find me');

        $result = $this->repository->getChronicleById($id);

        $this->assertNotNull($result);
        $this->assertSame($id, (int) $result['id']);
        $this->assertSame('find me', $result['txt']);
    }

    public function test_add_chronicle_inserts_record(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->repository->addChronicle($user->id, 'new chronicle entry');

        $this->assertSame(1, DB::table('chronicle')->count());

        $row = DB::table('chronicle')->first();
        $this->assertNotNull($row);
        $this->assertSame($user->id, (int) $row->userid);
        $this->assertSame('new chronicle entry', $row->txt);
        $this->assertNotNull($row->added);
    }

    public function test_update_chronicle_modifies_text(): void
    {
        $id = $this->insertChronicle(1, 'original text');

        $count = $this->repository->updateChronicle($id, 'updated text');

        $this->assertSame(1, $count);
        $this->assertSame('updated text', DB::table('chronicle')->where('id', $id)->value('txt'));
    }

    public function test_update_chronicle_returns_zero_when_not_found(): void
    {
        $this->assertSame(0, $this->repository->updateChronicle(99999, 'text'));
    }

    public function test_delete_chronicle_removes_record(): void
    {
        $id = $this->insertChronicle(1, 'delete me');

        $count = $this->repository->deleteChronicle($id);

        $this->assertSame(1, $count);
        $this->assertSame(0, DB::table('chronicle')->where('id', $id)->count());
    }

    public function test_delete_chronicle_returns_zero_when_not_found(): void
    {
        $this->assertSame(0, $this->repository->deleteChronicle(99999));
    }

    public function test_get_generic_by_id_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getGenericById('chronicle', 99999));
    }

    public function test_get_generic_by_id_returns_array_when_found(): void
    {
        $id = $this->insertChronicle(1, 'generic test');

        $result = $this->repository->getGenericById('chronicle', $id);

        $this->assertNotNull($result);
        $this->assertSame($id, (int) $result['id']);
    }

    public function test_count_news_returns_zero_when_empty(): void
    {
        $this->assertSame(0, $this->repository->countNews([]));
    }

    public function test_count_news_counts_all_without_filter(): void
    {
        $this->insertNews($this->userId, 'Title A', 'body a');
        $this->insertNews($this->userId, 'Title B', 'body b');

        $this->assertSame(2, $this->repository->countNews([]));
    }

    public function test_count_news_filters_by_title(): void
    {
        $this->insertNews($this->userId, 'Alpha News', 'body');
        $this->insertNews($this->userId, 'Beta News', 'body');

        $this->assertSame(1, $this->repository->countNews(['search' => 'title', 'query' => 'Alpha']));
    }

    public function test_count_news_filters_by_body(): void
    {
        $this->insertNews($this->userId, 'News', 'special body content');
        $this->insertNews($this->userId, 'News', 'ordinary content');

        $this->assertSame(1, $this->repository->countNews(['search' => 'body', 'query' => 'special']));
    }

    public function test_count_news_filters_by_both(): void
    {
        $this->insertNews($this->userId, 'Alpha Title', 'ordinary');
        $this->insertNews($this->userId, 'Other', 'alpha body');

        $this->assertSame(2, $this->repository->countNews(['search' => 'both', 'query' => 'alpha']));
    }

    public function test_get_news_returns_ordered_by_added_desc(): void
    {
        $this->insertNews($this->userId, 'Older', 'body', '2025-01-01 00:00:00');
        $this->insertNews($this->userId, 'Newer', 'body', '2025-06-01 00:00:00');

        $result = $this->repository->getNews([], 0, 10);

        $this->assertCount(2, $result);
        $this->assertSame('Newer', $result[0]['title']);
        $this->assertSame('Older', $result[1]['title']);
    }

    public function test_get_news_respects_offset_and_limit(): void
    {
        $this->insertNews($this->userId, 'A', 'body', '2025-01-01 00:00:00');
        $this->insertNews($this->userId, 'B', 'body', '2025-02-01 00:00:00');
        $this->insertNews($this->userId, 'C', 'body', '2025-03-01 00:00:00');

        $result = $this->repository->getNews([], 1, 1);

        $this->assertCount(1, $result);
        $this->assertSame('B', $result[0]['title']);
    }

    public function test_get_poll_count_returns_zero_when_empty(): void
    {
        $this->assertSame(0, $this->repository->getPollCount());
    }

    public function test_get_poll_count_returns_total(): void
    {
        $this->insertPoll('Poll 1');
        $this->insertPoll('Poll 2');

        $this->assertSame(2, $this->repository->getPollCount());
    }

    public function test_get_polls_except_first_returns_empty_when_one_or_less(): void
    {
        $this->insertPoll('Only Poll');

        $this->assertSame([], $this->repository->getPollsExceptFirst());
    }

    public function test_get_polls_except_first_returns_all_but_first(): void
    {
        $this->insertPoll('Poll 1');
        $this->insertPoll('Poll 2');
        $this->insertPoll('Poll 3');

        $result = $this->repository->getPollsExceptFirst();

        $this->assertCount(2, $result);
        // Ordered by id desc, offset 1: skips the newest, returns Poll 2 and Poll 1
        $this->assertSame('Poll 2', $result[0]['question']);
        $this->assertSame('Poll 1', $result[1]['question']);
    }

    public function test_delete_poll_removes_poll_and_answers(): void
    {
        $pollId = $this->insertPoll('Delete Me');
        DB::table('pollanswers')->insert([
            ['pollid' => $pollId, 'userid' => $this->userId, 'selection' => 0],
            ['pollid' => $pollId, 'userid' => $this->userId, 'selection' => 1],
        ]);

        $this->repository->deletePoll($pollId);

        $this->assertSame(0, DB::table('polls')->where('id', $pollId)->count());
        $this->assertSame(0, DB::table('pollanswers')->where('pollid', $pollId)->count());
    }

    public function test_get_poll_vote_counts_returns_empty_when_no_votes(): void
    {
        $pollId = $this->insertPoll('No Votes');

        $this->assertSame([], $this->repository->getPollVoteCounts($pollId));
    }

    public function test_get_poll_vote_counts_counts_votes_by_selection(): void
    {
        $pollId = $this->insertPoll('Vote Poll');
        DB::table('pollanswers')->insert([
            ['pollid' => $pollId, 'userid' => $this->userId, 'selection' => 0],
            ['pollid' => $pollId, 'userid' => $this->userId, 'selection' => 0],
            ['pollid' => $pollId, 'userid' => $this->userId, 'selection' => 1],
        ]);

        $result = $this->repository->getPollVoteCounts($pollId);

        $this->assertSame(2, $result[0]);
        $this->assertSame(1, $result[1]);
    }

    public function test_get_poll_vote_counts_excludes_selections_above_limit(): void
    {
        $pollId = $this->insertPoll('High Selection');
        DB::table('pollanswers')->insert([
            ['pollid' => $pollId, 'userid' => $this->userId, 'selection' => 5],
            ['pollid' => $pollId, 'userid' => $this->userId, 'selection' => 20],
        ]);

        $result = $this->repository->getPollVoteCounts($pollId);

        $this->assertSame(1, $result[5]);
        $this->assertArrayNotHasKey(20, $result);
    }

    private function insertSiteLog(string $txt, string $securityLevel, ?string $added = null): int
    {
        return (int) DB::table('sitelog')->insertGetId([
            'txt' => $txt,
            'security_level' => $securityLevel,
            'added' => $added ?? now()->toDateTimeString(),
            'uid' => 0,
        ]);
    }

    private function insertChronicle(int $userId, string $txt, ?string $added = null): int
    {
        return (int) DB::table('chronicle')->insertGetId([
            'userid' => $userId,
            'txt' => $txt,
            'added' => $added ?? now()->toDateTimeString(),
        ]);
    }

    private function insertNews(int $userId, string $title, string $body, ?string $added = null): int
    {
        return (int) DB::table('news')->insertGetId([
            'userid' => $userId,
            'title' => $title,
            'body' => $body,
            'added' => $added ?? now()->toDateTimeString(),
            'notify' => 0,
        ]);
    }

    private function insertPoll(string $question): int
    {
        return (int) DB::table('polls')->insertGetId([
            'question' => $question,
            'added' => now()->toDateTimeString(),
            'option0' => 'Yes',
            'option1' => 'No',
        ]);
    }
}
