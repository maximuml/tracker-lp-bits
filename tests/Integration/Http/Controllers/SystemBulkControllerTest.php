<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Controllers;

use App\Contracts\Repositories\ToolRepositoryInterface;
use App\Contracts\Repositories\UserModerationRepositoryInterface;
use App\Enums\UserClass;
use App\Http\Controllers\SystemBulkController;
use App\Jobs\BulkUserIncrementJob;
use App\Jobs\BulkUserMessageJob;
use App\Jobs\SendLegacyMail;
use App\Models\User;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\View\View;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION, TestCategory::MUTATION)]
final class SystemBulkControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupLegacyEnvironment();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─── takeamountupload ────────────────────────────────────────────────

    public function test_takeamountupload_denies_non_sysop(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::MODERATOR->value)->create();
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::MODERATOR->value);

        $response = $this->callTakeAmountUpload('POST', ['msg' => 'hi', 'amount' => '1', 'clases' => [1]]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_takeamountupload_rejects_blank_fields(): void
    {
        $this->loginAsSysop();

        $response = $this->callTakeAmountUpload('POST', ['msg' => '', 'amount' => '1', 'clases' => [1]]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('leave any fields blank', (string) $response->getContent());
    }

    public function test_takeamountupload_rejects_non_numeric_amount(): void
    {
        $this->loginAsSysop();

        $response = $this->callTakeAmountUpload('POST', ['msg' => 'hi', 'amount' => 'abc', 'clases' => [1]]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('amount must be numeric', (string) $response->getContent());
    }

    public function test_takeamountupload_rejects_invalid_class(): void
    {
        $this->loginAsSysop();

        $response = $this->callTakeAmountUpload('POST', ['msg' => 'hi', 'amount' => '1', 'clases' => ['abc']]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Invalid Class', (string) $response->getContent());
    }

    public function test_takeamountupload_dispatches_increment_job(): void
    {
        Queue::fake();
        $this->loginAsSysop();

        $response = $this->callTakeAmountUpload('POST', [
            'msg' => 'Enjoy!',
            'subject' => 'Bonus upload',
            'amount' => '2',
            'clases' => [UserClass::USER->value],
            'sender' => 'system',
        ]);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('takeamountupload.php?sent=1', $response->getTargetUrl());
        Queue::assertPushed(BulkUserIncrementJob::class, 1);
    }

    public function test_takeamountupload_get_sent_shows_success(): void
    {
        $this->loginAsSysop();

        $response = $this->callTakeAmountUpload('GET', [], ['sent' => '1']);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Upload amount has been added successfully', (string) $response->getContent());
    }

    // ─── takeIncrementBulk ───────────────────────────────────────────────

    public function test_take_increment_bulk_rejects_get(): void
    {
        $this->loginAsSysop();

        $response = $this->callTakeIncrementBulk('GET');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_take_increment_bulk_denies_non_sysop(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::ADMINISTRATOR->value)->create();
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::ADMINISTRATOR->value);

        $response = $this->callTakeIncrementBulk('POST', ['msg' => 'hi', 'amount' => '1', 'type' => 'seedbonus', 'classes' => [1]]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_take_increment_bulk_rejects_blank_fields(): void
    {
        $this->loginAsSysop();

        $response = $this->callTakeIncrementBulk('POST', ['msg' => '', 'amount' => '1', 'type' => 'seedbonus', 'classes' => [1]]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('leave any fields blank', (string) $response->getContent());
    }

    public function test_take_increment_bulk_rejects_invalid_type(): void
    {
        $this->loginAsSysop();

        $response = $this->callTakeIncrementBulk('POST', ['msg' => 'hi', 'amount' => '1', 'type' => 'bogus', 'classes' => [1]]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Invalid type', (string) $response->getContent());
    }

    public function test_take_increment_bulk_rejects_empty_classes(): void
    {
        $this->loginAsSysop();

        $response = $this->callTakeIncrementBulk('POST', ['msg' => 'hi', 'amount' => '1', 'type' => 'seedbonus', 'classes' => []]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('No valid filter', (string) $response->getContent());
    }

    public function test_take_increment_bulk_tmp_invites_requires_duration(): void
    {
        $this->loginAsSysop();

        $response = $this->callTakeIncrementBulk('POST', [
            'msg' => 'hi',
            'amount' => '1',
            'type' => 'tmp_invites',
            'classes' => [1],
            'duration' => 0,
        ]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Invalid duration', (string) $response->getContent());
    }

    public function test_take_increment_bulk_dispatches_increment_job(): void
    {
        Queue::fake();
        $this->loginAsSysop();

        $response = $this->callTakeIncrementBulk('POST', [
            'msg' => 'hi',
            'subject' => 'Bonus',
            'amount' => '100',
            'type' => 'seedbonus',
            'classes' => [UserClass::USER->value],
        ]);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/web/increment-bulk?sent=1&type=seedbonus', $response->getTargetUrl());
        Queue::assertPushed(BulkUserIncrementJob::class, 1);
    }

    public function test_take_increment_bulk_tmp_invites_dispatches_message_job(): void
    {
        Queue::fake();
        $this->loginAsSysop();

        $response = $this->callTakeIncrementBulk('POST', [
            'msg' => 'hi',
            'amount' => '1',
            'type' => 'tmp_invites',
            'classes' => [UserClass::USER->value],
            'duration' => 7,
            'dry_run' => true,
        ]);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/web/increment-bulk?sent=1&type=tmp_invites', $response->getTargetUrl());
        Queue::assertPushed(BulkUserMessageJob::class, 1);
    }

    // ─── takeupdate ──────────────────────────────────────────────────────

    public function test_takeupdate_redirects_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(SystemBulkController::class);
        $request = Request::create('/takeupdate', 'POST');
        app()->instance('request', $request);

        $response = $controller->takeupdate($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/takeupdate.php', $response->getTargetUrl());
    }

    public function test_takeupdate_denies_regular_user(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::USER->value)->create();
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::USER->value);

        $response = $this->callTakeupdate(['delreport' => [1], 'setdealt' => 1]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Permission denied', (string) $response->getContent());
    }

    public function test_takeupdate_requires_report_selection(): void
    {
        $this->loginAsStaffLeader();

        $response = $this->callTakeupdate(['setdealt' => 1]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Select at least one record', (string) $response->getContent());
    }

    public function test_takeupdate_rejects_invalid_ids(): void
    {
        $this->loginAsStaffLeader();

        $response = $this->callTakeupdate(['delreport' => ['abc'], 'setdealt' => 1]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Invalid report ids', (string) $response->getContent());
    }

    public function test_takeupdate_setdealt_marks_reports(): void
    {
        $user = $this->loginAsStaffLeader();

        $reportIds = collect(range(1, 2))->map(
            fn (): int => (int) DB::table('reports')->insertGetId([
                'addedby' => $user->id,
                'added' => now(),
                'reportid' => 1,
                'type' => 0,
                'reason' => 'spam',
                'dealtwith' => 0,
                'dealtby' => 0,
            ])
        )->all();

        $response = $this->callTakeupdate(['delreport' => $reportIds, 'setdealt' => 1]);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/web/reports', $response->getTargetUrl());
        foreach ($reportIds as $id) {
            $this->assertDatabaseHas('reports', ['id' => $id, 'dealtwith' => 1, 'dealtby' => $user->id]);
        }
    }

    public function test_takeupdate_delete_removes_reports(): void
    {
        $user = $this->loginAsStaffLeader();

        $reportId = (int) DB::table('reports')->insertGetId([
            'addedby' => $user->id,
            'added' => now(),
            'reportid' => 1,
            'type' => 0,
            'reason' => 'spam',
            'dealtwith' => 0,
            'dealtby' => 0,
        ]);

        $response = $this->callTakeupdate(['delreport' => [$reportId], 'delete' => 1]);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertDatabaseMissing('reports', ['id' => $reportId]);
    }

    // ─── takeinvite ──────────────────────────────────────────────────────

    public function test_takeinvite_redirects_guest(): void
    {
        $this->mockCurrentUser(null);

        $controller = app(SystemBulkController::class);
        $request = Request::create('/takeinvite', 'POST');
        app()->instance('request', $request);

        $response = $controller->takeinvite($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/takeinvite.php', $response->getTargetUrl());
    }

    public function test_takeinvite_aborts_when_invite_system_disabled(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::USER->value)->create();
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::USER->value);

        $controller = app(SystemBulkController::class);
        $request = Request::create('/takeinvite', 'POST', ['email' => 'x@example.com', 'body' => 'hi', 'hash' => 'permanent']);
        app()->instance('request', $request);

        try {
            $controller->takeinvite($request);
            $this->fail('Expected HttpResponseException for disabled invite system');
        } catch (HttpResponseException $e) {
            $this->assertStringContainsString('invite', (string) $e->getResponse()->getContent());
        }
    }

    public function test_takeinvite_rejects_blank_email(): void
    {
        $this->enableInviteSystem();

        $response = $this->callTakeinvite(['email' => '', 'body' => 'hi', 'hash' => 'permanent']);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('email', (string) $response->getContent());
    }

    public function test_takeinvite_rejects_invalid_email(): void
    {
        $this->enableInviteSystem();

        $response = $this->callTakeinvite(['email' => 'not-an-email', 'body' => 'hi', 'hash' => 'permanent']);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('email address', (string) $response->getContent());
    }

    public function test_takeinvite_rejects_blank_body(): void
    {
        $this->enableInviteSystem();

        $response = $this->callTakeinvite(['email' => 'x@example.com', 'body' => '', 'hash' => 'permanent']);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('personal message', (string) $response->getContent());
    }

    public function test_takeinvite_rejects_missing_hash(): void
    {
        $this->enableInviteSystem();

        $response = $this->callTakeinvite(['email' => 'x@example.com', 'body' => 'hi']);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('select', strtolower((string) $response->getContent()));
    }

    public function test_takeinvite_rejects_unknown_hash(): void
    {
        $this->enableInviteSystem();

        $response = $this->callTakeinvite([
            'email' => 'x@example.com',
            'body' => 'hi',
            'hash' => str_repeat('a', 32),
        ]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Hash does not exist', (string) $response->getContent());
    }

    public function test_takeinvite_with_existing_hash_redirects_when_smtp_disabled(): void
    {
        Queue::fake();
        $user = $this->enableInviteSystem();
        Settings::saveBatch('smtp', ['smtptype' => 'none']);
        Settings::resetCache();

        $hash = str_repeat('b', 32);
        DB::table('invites')->insert([
            'inviter' => $user->id,
            'invitee' => '',
            'hash' => $hash,
            'valid' => 1,
        ]);

        $response = $this->callTakeinvite([
            'email' => 'invitee@example.com',
            'body' => 'welcome aboard',
            'hash' => $hash,
        ]);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/web/invite?id='.$user->id.'&sent=1', $response->getTargetUrl());
        // SMTP is disabled in the test settings, so the mail is not sent and
        // the invitee is not marked on the hash.
        Queue::assertNotPushed(SendLegacyMail::class);
        $this->assertDatabaseHas('invites', ['hash' => $hash, 'invitee' => '']);
    }

    // ─── setlistLookup ───────────────────────────────────────────────────

    public function test_setlist_lookup_requires_input(): void
    {
        $this->loginAsSysop();

        $controller = app(SystemBulkController::class);
        $request = Request::create('/setlist-lookup', 'POST');
        app()->instance('request', $request);

        $response = $controller->setlistLookup($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertFalse($response->getData(true)['success']);
        $this->assertStringContainsString('required', $response->getData(true)['error']);
    }

    public function test_setlist_lookup_rejects_non_setlist_host(): void
    {
        $this->loginAsSysop();

        $controller = app(SystemBulkController::class);
        $request = Request::create('/setlist-lookup', 'POST', ['url' => 'https://evil.example.com/setlist/1']);
        app()->instance('request', $request);

        $response = $controller->setlistLookup($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertFalse($response->getData(true)['success']);
        $this->assertStringContainsString('setlist.fm', $response->getData(true)['error']);
    }

    // ─── helpers ─────────────────────────────────────────────────────────

    private function setupLegacyEnvironment(): void
    {
        app(Globals::class)->set('lang_functions', (array) trans('legacy/functions'));

        app()->bind(LegacyRedisCache::class, fn () => null);

        /** @var ToolRepositoryInterface&MockInterface $repo */
        $repo = Mockery::mock(ToolRepositoryInterface::class);
        $repo->shouldReceive('listUserAllPermissions')->andReturn([]);
        app()->instance(ToolRepositoryInterface::class, $repo);
    }

    private function loginAsSysop(): User
    {
        /** @var User $user */
        $user = User::factory()->admin()->create();
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::SYSOP->value);

        return $user;
    }

    private function loginAsStaffLeader(): User
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::STAFFLEADER->value);

        return $user;
    }

    /**
     * Enable the invite system and log in as a regular user with invites.
     */
    private function enableInviteSystem(): User
    {
        DB::table('settings')->updateOrInsert(
            ['name' => 'main.invitesystem'],
            ['value' => 'yes', 'autoload' => 1]
        );
        Settings::resetCache();

        /** @var UserModerationRepositoryInterface&MockInterface $moderation */
        $moderation = Mockery::mock(UserModerationRepositoryInterface::class);
        $moderation->shouldReceive('getInviteBtnText')->andReturn('Invite');
        app()->instance(UserModerationRepositoryInterface::class, $moderation);

        /** @var User $user */
        $user = User::factory()->class(UserClass::USER->value)->create(['invites' => 5]);
        $this->actingAs($user);
        $this->mockCurrentUserWithDefaults($user->id, UserClass::USER->value);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $post
     * @param  array<string, mixed>  $query
     */
    private function callTakeAmountUpload(string $method, array $post = [], array $query = []): Response|RedirectResponse|View
    {
        $controller = app(SystemBulkController::class);
        $request = Request::create('/takeamountupload', $method, $post + $query);
        app()->instance('request', $request);

        return $controller->takeamountupload($request);
    }

    /** @param  array<string, mixed>  $post */
    private function callTakeIncrementBulk(string $method, array $post = []): Response|RedirectResponse
    {
        $controller = app(SystemBulkController::class);
        $request = Request::create('/take-increment-bulk', $method, $post);
        app()->instance('request', $request);

        return $controller->takeIncrementBulk($request);
    }

    /** @param  array<string, mixed>  $post */
    private function callTakeupdate(array $post): Response|RedirectResponse
    {
        $controller = app(SystemBulkController::class);
        $request = Request::create('/takeupdate', 'POST', $post);
        app()->instance('request', $request);

        return $controller->takeupdate($request);
    }

    /** @param  array<string, mixed>  $post */
    private function callTakeinvite(array $post): Response|RedirectResponse
    {
        $controller = app(SystemBulkController::class);
        $request = Request::create('/takeinvite', 'POST', $post);
        app()->instance('request', $request);

        return $controller->takeinvite($request);
    }

    /**
     * @param  array<string, mixed>|null  $user
     */
    private function mockCurrentUser(?array $user): void
    {
        $real = new CurrentUser;
        $mock = Mockery::mock($real);
        $mock->shouldReceive('get')->andReturn($user);
        app()->instance(CurrentUser::class, $mock);
    }

    private function mockCurrentUserWithDefaults(int $userId, int $class): void
    {
        $this->mockCurrentUser([
            'id' => $userId,
            'class' => $class,
            'username' => 'admin',
            'seedbonus' => 0.0,
            'uploaded' => 0,
            'downloaded' => 0,
            'invites' => 0,
            'seedtime' => 0,
            'leechtime' => 0,
            'enabled' => true,
            'status' => 1,
            'last_access' => date('Y-m-d H:i:s'),
            'added' => date('Y-m-d H:i:s'),
            'stylesheet' => 1,
            'fontsize' => '',
            'showclienterror' => false,
            'attendance_card' => 0,
            'last_home' => null,
            'passkey' => 'test',
            'auth_key' => 'test',
            'privacy' => 1,
            'noad' => false,
            'downloadpos' => true,
            'donor' => false,
            'donoruntil' => null,
            'leechwarn' => false,
            'parked' => false,
            'avatar' => '',
            'title' => '',
            'lang' => 'en',
            'seed_points' => 0,
            'seed_points_per_hour' => 0,
            'page' => 1,
            'support' => false,
            'picker' => false,
            'vip_added' => false,
            'vip_until' => null,
            'clientselect' => '',
            'last_login' => null,
            'last_pm' => null,
            'last_staffmsg' => null,
            'last_comment' => null,
            'last_post' => null,
            'lastwarned' => null,
            'last_browse' => null,
            'last_music' => null,
            'last_catchup' => null,
            'warneduntil' => null,
            'noaduntil' => null,
            'leechwarnuntil' => null,
            'gender' => '',
            'charity' => 0.0,
            'invited_by' => 0,
            'last_offer' => null,
            'forum_access' => null,
            'appendnew' => false,
            'appendpicked' => false,
            'appendsticky' => false,
            'avatars' => true,
            'bmicon' => false,
            'commentpm' => false,
            'deletepms' => false,
            'dlicon' => false,
            'forumpost' => true,
            'savepms' => false,
            'signatures' => true,
            'showcomment' => true,
            'showcomnum' => true,
            'showdescription' => true,
            'showimdb' => true,
            'showlastcom' => true,
            'showlastpost' => true,
            'shownfo' => true,
            'showsmalldescr' => true,
            'uploadpos' => true,
            'timetype' => 1,
            'editsecret' => '',
            'secret' => '',
            'passhash' => '',
            'passhash_algo' => '',
            'email' => 'admin@example.com',
        ]);
    }
}
