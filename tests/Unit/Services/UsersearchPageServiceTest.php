<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Repositories\UserListingRepository;
use App\Services\UsersearchPageService;
use App\Support\CurrentUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for UsersearchPageService.
 *
 * Covers build(): permission-denied for non-moderator, form-only output
 * with no query params, help view, search with results, search with no
 * results, invalid email error, invalid IP error, form field structure,
 * and common data keys.
 *
 * UserSearchRepository is final and cannot be mocked, so real users are
 * inserted via DB::table() for search tests. UserListingRepository is
 * not final and is mocked to return empty extra stats.
 */
final class UsersearchPageServiceTest extends TestCase
{
    use DatabaseTransactions;

    private int $initialObLevel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialObLevel = ob_get_level();
        Redis::connection()->flushdb();

        if (! defined('IN_NEXUS')) {
            define('IN_NEXUS', true);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->initialObLevel) {
            ob_end_clean();
        }

        Mockery::close();
        parent::tearDown();
    }

    private function service(): UsersearchPageService
    {
        return new UsersearchPageService;
    }

    /** @param  array<string, mixed>  $overrides */
    private function insertUser(string $username, int $class = 1, array $overrides = []): int
    {
        return (int) DB::table('users')->insertGetId(array_merge([
            'username' => $username,
            'email' => $username.'@test.com',
            'passhash' => 'hash',
            'secret' => 'secret',
            'passkey' => str_pad((string) mt_rand(1, 999999), 32, '0'),
            'class' => $class,
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
            'status' => 'confirmed',
            'enabled' => 1,
            'parked' => 0,
            'downloadpos' => 1,
            'seedbonus' => 100.0,
            'uploaded' => 1073741824,
            'downloaded' => 536870912,
            'ip' => '127.0.0.1',
        ], $overrides));
    }

    /**
     * Authenticate via both CurrentUser and Laravel's Auth guard.
     *
     * IN_NEXUS is defined as false by bootstrap/app.php before setUp()
     * runs, so UserDisplay::currentClass() uses auth()->user()->class
     * rather than CurrentUser. We must log in via Auth for the
     * permission check to see the correct class.
     *
     * @param  array<string, mixed>  $userData
     */
    private function authenticatedUser(array $userData = []): void
    {
        $defaults = [
            'id' => 1,
            'username' => 'admin',
            'class' => 13, // UC_MODERATOR
        ];
        $data = array_merge($defaults, $userData);

        $currentUser = new CurrentUser;
        $currentUser->set($data);
        $this->app->instance(CurrentUser::class, $currentUser);

        $user = new User;
        $user->id = $data['id'];
        $user->class = $data['class'];
        $user->username = $data['username'];
        auth()->login($user);
    }

    private function mockUserListingRepo(): void
    {
        $repo = Mockery::mock(UserListingRepository::class);
        $repo->shouldIgnoreMissing();
        $repo->shouldReceive('getSearchExtraStats')
            ->andReturn(['peers' => [], 'posts' => [], 'comments' => [], 'bannedIps' => []]);
        $this->app->instance(UserListingRepository::class, $repo);
    }

    /**
     * Call build() while binding the request to the container (the
     * service uses request() helper internally) and suppressing
     * E_NOTICE/E_WARNING from legacy rendering.
     *
     * @return array<string, mixed>
     */
    private function callBuild(Request $request): array
    {
        $this->app->instance('request', $request);

        set_error_handler(function (int $severity): bool {
            return true;
        }, E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING);

        try {
            /** @var array<string, mixed> */
            return $this->service()->build($request);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Assert that calling build() with $request triggers an abort.
     */
    private function assertBuildThrows(Request $request): void
    {
        $threw = false;
        try {
            $this->callBuild($request);
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected exception was not thrown');
    }

    // --- permission denied for non-moderator ---

    public function test_build_aborts_for_non_moderator(): void
    {
        $this->authenticatedUser(['class' => 1]);
        $this->mockUserListingRepo();

        $request = Request::create('/usersearch.php', 'GET');

        $this->assertBuildThrows($request);
    }

    public function test_build_aborts_for_unauthenticated_user(): void
    {
        $currentUser = new CurrentUser;
        $currentUser->set([]);
        $this->app->instance(CurrentUser::class, $currentUser);
        // Do not log in via Auth — auth()->check() returns false
        $this->mockUserListingRepo();

        $request = Request::create('/usersearch.php', 'GET');

        $this->assertBuildThrows($request);
    }

    // --- form only (no query params) ---

    public function test_build_returns_form_only_with_no_query_params(): void
    {
        $this->authenticatedUser();
        $this->mockUserListingRepo();

        $request = Request::create('/usersearch.php', 'GET');

        $data = $this->callBuild($request);

        $this->assertFalse($data['hasResults']);
        $this->assertSame('', $data['resultsHtml']);
        $this->assertSame('', $data['resultsError']);
        $this->assertArrayHasKey('form', $data);
    }

    // --- help view ---

    public function test_build_returns_help_view_with_h_param(): void
    {
        $this->authenticatedUser();
        $this->mockUserListingRepo();

        $request = Request::create('/usersearch.php', 'GET', ['h' => '1']);

        $data = $this->callBuild($request);

        $this->assertTrue($data['showHelp']);
        $this->assertFalse($data['hasResults']);
    }

    // --- search with no results ---

    public function test_build_with_search_params_no_results_returns_warning(): void
    {
        $this->authenticatedUser();
        $this->mockUserListingRepo();

        // Search for a username that doesn't exist
        $request = Request::create('/usersearch.php', 'GET', ['n' => 'nonexistentuser999']);

        $data = $this->callBuild($request);

        $this->assertTrue($data['hasResults']);
        $this->assertStringContainsString('No user was found', $data['resultsHtml']);
        $this->assertSame('', $data['resultsError']);
    }

    // --- search with results ---

    public function test_build_with_search_params_returns_results(): void
    {
        $userId = $this->insertUser('searchtarget');
        $this->authenticatedUser(['id' => $userId + 100, 'class' => 13]);
        $this->mockUserListingRepo();

        $request = Request::create('/usersearch.php', 'GET', ['n' => 'searchtarget']);

        $data = $this->callBuild($request);

        $this->assertTrue($data['hasResults']);
        $this->assertStringContainsString('<table', $data['resultsHtml']);
        $this->assertSame('', $data['resultsError']);
    }

    // --- search with wildcard ---

    public function test_build_with_wildcard_search_returns_results(): void
    {
        $this->insertUser('wildcarduser1');
        $this->insertUser('wildcarduser2');
        $this->authenticatedUser(['class' => 13]);
        $this->mockUserListingRepo();

        $request = Request::create('/usersearch.php', 'GET', ['n' => 'wildcard*']);

        $data = $this->callBuild($request);

        $this->assertTrue($data['hasResults']);
        $this->assertStringContainsString('<table', $data['resultsHtml']);
    }

    // --- invalid email error ---

    public function test_build_with_invalid_email_returns_error(): void
    {
        $this->authenticatedUser();
        $this->mockUserListingRepo();

        $request = Request::create('/usersearch.php', 'GET', ['em' => 'notanemail']);

        $data = $this->callBuild($request);

        $this->assertTrue($data['hasResults']);
        $this->assertStringContainsString('Bad email', $data['resultsError']);
        $this->assertSame('', $data['resultsHtml']);
    }

    // --- invalid IP error ---

    public function test_build_with_invalid_ip_returns_error(): void
    {
        $this->authenticatedUser();
        $this->mockUserListingRepo();

        $request = Request::create('/usersearch.php', 'GET', ['ip' => 'notanip']);

        $data = $this->callBuild($request);

        $this->assertTrue($data['hasResults']);
        $this->assertStringContainsString('Bad IP', $data['resultsError']);
    }

    // --- form fields structure ---

    public function test_build_form_fields_include_all_expected_keys(): void
    {
        $this->authenticatedUser();
        $this->mockUserListingRepo();

        $request = Request::create('/usersearch.php', 'GET');

        $data = $this->callBuild($request);

        $form = $data['form'];
        $this->assertArrayHasKey('n', $form);
        $this->assertArrayHasKey('em', $form);
        $this->assertArrayHasKey('ip', $form);
        $this->assertArrayHasKey('c', $form);
        $this->assertArrayHasKey('c_options', $form);
        $this->assertArrayHasKey('rt_options', $form);
        $this->assertArrayHasKey('st_options', $form);
        $this->assertArrayHasKey('as_options', $form);
        $this->assertArrayHasKey('dt_options', $form);
        $this->assertArrayHasKey('ult_options', $form);
        $this->assertArrayHasKey('dlt_options', $form);
        $this->assertArrayHasKey('w_options', $form);
        $this->assertArrayHasKey('do_options', $form);
        $this->assertArrayHasKey('ac', $form);
        $this->assertArrayHasKey('dip', $form);
    }

    // --- form fields highlight when query params present ---

    public function test_build_form_fields_highlight_active_search(): void
    {
        $this->authenticatedUser();
        $this->mockUserListingRepo();

        $request = Request::create('/usersearch.php', 'GET', ['n' => 'somename', 'em' => 'some@email.com']);

        $data = $this->callBuild($request);

        $this->assertNotEmpty($data['form']['n_hl']);
        $this->assertNotEmpty($data['form']['em_hl']);
    }

    // --- common data keys ---

    public function test_build_includes_request_uri_and_pagemenu(): void
    {
        $this->authenticatedUser();
        $this->mockUserListingRepo();

        $request = Request::create('/usersearch.php', 'GET');

        $data = $this->callBuild($request);

        $this->assertArrayHasKey('requestUri', $data);
        $this->assertArrayHasKey('pagemenu', $data);
        $this->assertArrayHasKey('browsemenu', $data);
        $this->assertSame('', $data['pagemenu']);
        $this->assertSame('', $data['browsemenu']);
    }

    // --- class options include (any) ---

    public function test_build_class_options_include_any_option(): void
    {
        $this->authenticatedUser();
        $this->mockUserListingRepo();

        $request = Request::create('/usersearch.php', 'GET');

        $data = $this->callBuild($request);

        $this->assertStringContainsString('(any)', $data['form']['c_options']);
    }
}
