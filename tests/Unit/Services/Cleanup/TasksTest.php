<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cleanup;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Cleanup\Tasks;
use App\Support\Cache;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TasksTest extends TestCase
{
    private Tasks $tasks;

    protected function setUp(): void
    {
        parent::setUp();

        DB::beginTransaction();
        Cache::clearSettings();

        $this->tasks = app(Tasks::class);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        Cache::clearSettings();

        parent::tearDown();
    }

    public function test_cleanup_class_5_performs_all_housekeeping(): void
    {
        $familyIdOne = $this->createAgentFamily();
        $familyIdTwo = $this->createAgentFamily();

        $userOne = $this->createUser(['clientselect' => $familyIdOne]);
        $userTwo = $this->createUser(['clientselect' => $familyIdOne]);
        $userThree = $this->createUser(['clientselect' => $familyIdTwo]);

        $oldMessageId = $this->createMessage(['added' => Carbon::now()->subDays(181)]);
        $newMessageId = $this->createMessage(['added' => Carbon::now()->subDay()]);

        $oldPostId = $this->createPost(['added' => Carbon::now()->subDays(370)]);
        $newPostId = $this->createPost(['added' => Carbon::now()->subDay()]);

        $catchupUserId = $this->createUser(['last_catchup' => 0]);
        DB::table('readposts')->insert([
            'userid' => $catchupUserId,
            'topicid' => 1,
            'lastpostread' => 0,
        ]);

        $oldCheaterId = $this->createCheater(['added' => Carbon::now()->subDays(181)]);
        $newCheaterId = $this->createCheater(['added' => Carbon::now()->subDay()]);

        $oldShoutId = $this->createShout(['date' => Carbon::now()->subDays(181)->timestamp]);
        $newShoutId = $this->createShout(['date' => Carbon::now()->subDay()->timestamp]);

        $oldLogId = $this->createSiteLog(['added' => Carbon::now()->subDays(181)]);
        $newLogId = $this->createSiteLog(['added' => Carbon::now()->subDay()]);

        $oldTopicId = $this->createTopic([
            'sticky' => 'no',
            'lastpost' => $oldPostId,
        ]);
        $newTopicId = $this->createTopic([
            'sticky' => 'no',
            'lastpost' => $newPostId,
        ]);

        $oldReportId = $this->createReport([
            'added' => Carbon::now()->subDays(30),
            'dealtwith' => 1,
        ]);
        $newReportId = $this->createReport([
            'added' => Carbon::now()->subDay(),
            'dealtwith' => 1,
        ]);

        $result = $this->tasks->cleanupClass5();

        $this->assertSame('cleanup class 5', $result);

        $this->assertSame(2, (int) DB::table('agent_allowed_family')->where('id', $familyIdOne)->value('hits'));
        $this->assertSame(1, (int) DB::table('agent_allowed_family')->where('id', $familyIdTwo)->value('hits'));

        $this->assertNull(DB::table('messages')->find($oldMessageId));
        $this->assertNotNull(DB::table('messages')->find($newMessageId));

        $this->assertSame($oldPostId, (int) DB::table('users')->where('id', $catchupUserId)->value('last_catchup'));
        $this->assertSame(0, (int) DB::table('readposts')->where('userid', $catchupUserId)->count());

        $this->assertNull(DB::table('cheaters')->find($oldCheaterId));
        $this->assertNotNull(DB::table('cheaters')->find($newCheaterId));

        $this->assertNull(DB::table('shoutbox')->find($oldShoutId));
        $this->assertNotNull(DB::table('shoutbox')->find($newShoutId));

        $this->assertNull(DB::table('sitelog')->find($oldLogId));
        $this->assertNotNull(DB::table('sitelog')->find($newLogId));

        $this->assertSame('yes', DB::table('topics')->where('id', $oldTopicId)->value('locked'));
        $this->assertSame('no', DB::table('topics')->where('id', $newTopicId)->value('locked'));

        $this->assertNull(DB::table('reports')->find($oldReportId));
        $this->assertNotNull(DB::table('reports')->find($newReportId));
    }

    public function test_cleanup_dead_torrents_and_ip_logs_purges_stale_failed_jobs(): void
    {
        Settings::saveBatch('torrent', ['deldeadtorrent' => 0]);

        $oldJobId = $this->createFailedJob(['failed_at' => Carbon::now()->subDays(11)]);
        $newJobId = $this->createFailedJob(['failed_at' => Carbon::now()->subDay()]);

        $result = $this->tasks->cleanupDeadTorrentsAndIpLogs();

        $this->assertStringContainsString('failed jobs', $result);
        $this->assertNull(DB::table('failed_jobs')->find($oldJobId));
        $this->assertNotNull(DB::table('failed_jobs')->find($newJobId));
    }

    public function test_cleanup_tasks_command_runs_class_five(): void
    {
        $this->artisan('cleanup:tasks', ['task' => 'cleanup-class-5'])
            ->assertSuccessful()
            ->expectsOutput('cleanup class 5');
    }

    private function createAgentFamily(): int
    {
        return (int) DB::table('agent_allowed_family')->insertGetId([
            'family' => 'test-family-'.Str::random(),
            'start_name' => '',
            'peer_id_pattern' => '',
            'peer_id_match_num' => 0,
            'peer_id_matchtype' => 'dec',
            'peer_id_start' => '',
            'agent_pattern' => '',
            'agent_match_num' => 0,
            'agent_matchtype' => 'dec',
            'agent_start' => '',
            'exception' => 'no',
            'allowhttps' => 'no',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createUser(array $overrides = []): int
    {
        $unique = Str::random();

        return (int) DB::table('users')->insertGetId(array_merge([
            'username' => 'testuser-'.$unique,
            'email' => $unique.'@example.net',
            'passhash' => md5($unique),
            'secret' => Str::random(),
            'auth_key' => Str::random(),
            'editsecret' => '',
            'added' => Carbon::now()->toDateTimeString(),
            'status' => UserStatus::CONFIRMED->value,
            'class' => User::CLASS_USER,
            'clientselect' => 0,
            'last_catchup' => 0,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createMessage(array $overrides = []): int
    {
        $unique = Str::random();

        return (int) DB::table('messages')->insertGetId(array_merge([
            'sender' => 0,
            'receiver' => 0,
            'added' => Carbon::now()->toDateTimeString(),
            'subject' => 'test-'.$unique,
            'msg' => 'test message',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPost(array $overrides = []): int
    {
        return (int) DB::table('posts')->insertGetId(array_merge([
            'topicid' => 1,
            'userid' => 1,
            'added' => Carbon::now()->toDateTimeString(),
            'body' => 'test',
            'ori_body' => 'test',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createCheater(array $overrides = []): int
    {
        return (int) DB::table('cheaters')->insertGetId(array_merge([
            'added' => Carbon::now()->toDateTimeString(),
            'userid' => 1,
            'torrentid' => 1,
            'comment' => 'test',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createShout(array $overrides = []): int
    {
        return (int) DB::table('shoutbox')->insertGetId(array_merge([
            'userid' => 1,
            'date' => Carbon::now()->timestamp,
            'text' => 'test',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSiteLog(array $overrides = []): int
    {
        return (int) DB::table('sitelog')->insertGetId(array_merge([
            'added' => Carbon::now()->toDateTimeString(),
            'txt' => 'test',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createTopic(array $overrides = []): int
    {
        return (int) DB::table('topics')->insertGetId(array_merge([
            'userid' => 1,
            'subject' => 'test-topic-'.Str::random(),
            'forumid' => 1,
            'firstpost' => 0,
            'lastpost' => 0,
            'sticky' => 'no',
            'locked' => 'no',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createReport(array $overrides = []): int
    {
        return (int) DB::table('reports')->insertGetId(array_merge([
            'addedby' => 1,
            'reportid' => 1,
            'type' => 'torrent',
            'reason' => 'test',
            'dealtwith' => 0,
            'added' => Carbon::now()->toDateTimeString(),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createFailedJob(array $overrides = []): int
    {
        return (int) DB::table('failed_jobs')->insertGetId(array_merge([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'test',
            'failed_at' => Carbon::now()->toDateTimeString(),
        ], $overrides));
    }
}
