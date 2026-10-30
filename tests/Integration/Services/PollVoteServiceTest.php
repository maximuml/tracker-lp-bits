<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Models\Poll;
use App\Models\User;
use App\Repositories\IndexRepository;
use App\Services\BonusLedgerService;
use App\Services\PollVoteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class PollVoteServiceTest extends TestCase
{
    use DatabaseTransactions;

    private PollVoteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('pollanswers')->delete();
        DB::table('polls')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->service = new PollVoteService(new IndexRepository, $this->app->make(BonusLedgerService::class));
    }

    public function test_vote_rejects_invalid_choice(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $pollId = $this->createPoll();

        $this->assertFalse($this->service->vote($user->toLegacyArray(), -1));
        $this->assertFalse($this->service->vote($user->toLegacyArray(), Poll::MAX_OPTION_INDEX + 1));
        $this->assertSame(0, DB::table('pollanswers')->where('pollid', $pollId)->count());
    }

    public function test_vote_rejects_blank_user(): void
    {
        $this->createPoll();

        $this->assertFalse($this->service->vote([], 0));
    }

    public function test_vote_rejects_when_no_poll(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->assertFalse($this->service->vote($user->toLegacyArray(), 0));
    }

    public function test_vote_rejects_empty_option(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createPoll(['option2' => '']);

        $this->assertFalse($this->service->vote($user->toLegacyArray(), 2));
    }

    public function test_vote_records_answer(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $pollId = $this->createPoll();

        $this->assertTrue($this->service->vote($user->toLegacyArray(), 1));
        $this->assertSame(1, (int) DB::table('pollanswers')
            ->where('pollid', $pollId)
            ->where('userid', $user->id)
            ->value('selection'));
    }

    public function test_vote_accepts_blank_vote_255(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $pollId = $this->createPoll();

        $this->assertTrue($this->service->vote($user->toLegacyArray(), 255));
        $this->assertSame(255, (int) DB::table('pollanswers')
            ->where('pollid', $pollId)
            ->where('userid', $user->id)
            ->value('selection'));
    }

    public function test_vote_rejects_repeat_vote(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $pollId = $this->createPoll();
        $legacyUser = $user->toLegacyArray();

        $this->assertTrue($this->service->vote($legacyUser, 0));
        $this->assertFalse($this->service->vote($legacyUser, 1));
        $this->assertSame(1, DB::table('pollanswers')
            ->where('pollid', $pollId)
            ->where('userid', $user->id)
            ->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPoll(array $overrides = []): int
    {
        return (int) DB::table('polls')->insertGetId(array_merge([
            'added' => now()->toDateTimeString(),
            'question' => 'Test Poll',
            'option0' => 'Yes',
            'option1' => 'No',
        ], $overrides));
    }
}
