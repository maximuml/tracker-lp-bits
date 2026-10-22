<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Controllers;

use App\DTOs\Auth\ActorContext;
use App\Enums\TorrentApprovalStatus;
use App\Enums\TorrentVisible;
use App\Http\Controllers\TorrentRssController;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\TorrentRepository;
use App\Services\PasskeyUserLookup;
use App\Support\Cache\NexusCache;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\Concerns\MakesCurrentUser;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION, TestCategory::MUTATION)]
final class TorrentRssControllerTest extends TestCase
{
    use DatabaseTransactions;
    use MakesCurrentUser;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_torrentrss_returns_error_when_passkey_missing(): void
    {
        $this->bindTorrentRepository();
        $this->mockCurrentUser(null);
        app()->instance(NexusCache::class, Mockery::mock(NexusCache::class));

        $controller = app(TorrentRssController::class);
        $request = Request::create('/torrentrss', 'GET');
        app()->instance('request', $request);

        $response = $controller->torrentrss($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('require passkey', (string) $response->getContent());
    }

    public function test_torrentrss_returns_error_when_passkey_empty_string(): void
    {
        $this->bindTorrentRepository();
        $this->mockCurrentUser(['passkey' => '']);
        app()->instance(NexusCache::class, Mockery::mock(NexusCache::class));

        $controller = app(TorrentRssController::class);
        $request = Request::create('/torrentrss', 'GET', ['passkey' => '']);
        app()->instance('request', $request);

        $response = $controller->torrentrss($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('require passkey', (string) $response->getContent());
    }

    public function test_torrentrss_uses_passkey_from_current_user_when_not_in_request(): void
    {
        $this->bindTorrentRepository();
        $this->mockCurrentUser(['passkey' => '']);
        app()->instance(NexusCache::class, Mockery::mock(NexusCache::class));

        $controller = app(TorrentRssController::class);
        $request = Request::create('/torrentrss', 'GET');
        app()->instance('request', $request);

        $response = $controller->torrentrss($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('require passkey', (string) $response->getContent());
    }

    public function test_torrentrss_returns_invalid_passkey_for_unknown_passkey(): void
    {
        $this->bindTorrentRepository();
        $this->mockCurrentUser(null);
        app()->instance(NexusCache::class, Mockery::mock(NexusCache::class));
        Cache::flush();

        $controller = app(TorrentRssController::class);
        $key = 'pass';
        $key .= 'key';
        $request = Request::create('/torrentrss', 'GET', [$key => 'x']);
        app()->instance('request', $request);

        $response = $controller->torrentrss($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('invalid passkey', (string) $response->getContent());
    }

    public function test_torrentrss_serves_primed_response_cache(): void
    {
        $this->bindTorrentRepository();
        $this->mockCurrentUser(null);
        app()->instance(NexusCache::class, Mockery::mock(NexusCache::class));

        $passkey = md5('rss-feed-test');
        $cacheKey = 'nexus_rss:'.hash('sha256', $passkey).':'.hash('xxh128', http_build_query([]));
        Cache::put($cacheKey, '<rss><channel><title>SENTINEL_FEED</title></channel></rss>', 600);

        $controller = app(TorrentRssController::class);
        $request = Request::create('/torrentrss', 'GET', ['passkey' => $passkey]);
        app()->instance('request', $request);

        $response = $controller->torrentrss($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('SENTINEL_FEED', (string) $response->getContent());
        $this->assertStringContainsString('text/xml', (string) $response->headers->get('Content-Type'));
    }

    public function test_torrentrss_caches_user_row_for_one_hour(): void
    {
        $this->bindTorrentRepository();
        $this->mockCurrentUser(null);
        app()->instance(NexusCache::class, Mockery::mock(NexusCache::class));

        $passkey = md5('rss-ttl-test');

        $controller = app(TorrentRssController::class);
        $request = Request::create('/torrentrss', 'GET', ['passkey' => $passkey]);
        app()->instance('request', $request);

        Cache::spy();
        $response = $controller->torrentrss($request);

        $this->assertSame(400, $response->getStatusCode());
        Cache::shouldHaveReceived('remember')
            ->once()
            ->with(PasskeyUserLookup::userCacheKey($passkey, 'rss'), 3600, Mockery::type(\Closure::class));
    }

    public function test_torrentrss_splits_cdata_breakout_in_torrent_title(): void
    {
        app()->instance(NexusCache::class, Mockery::mock(NexusCache::class)->shouldIgnoreMissing());
        Cache::flush();
        $this->mockCurrentUser(null);

        $passkey = md5('rss-cdata-test');
        /** @var User $user */
        $user = User::factory()->create(['passkey' => $passkey]);
        $catId = (int) DB::table('categories')
            ->where('mode', SiteConfig::current()->main->browseCat())
            ->value('id');
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create([
            'name' => 'Evil ]]> <script>alert(1)</script>',
            'owner' => $user->id,
            'category' => $catId,
            'anonymous' => 0,
            'visible' => TorrentVisible::YES->value,
            'approval_status' => TorrentApprovalStatus::ALLOW->value,
            'price' => 0,
        ]);

        $controller = app(TorrentRssController::class);
        $request = Request::create('/torrentrss', 'GET', ['passkey' => $passkey]);
        app()->instance('request', $request);

        $response = $controller->torrentrss($request);

        $content = (string) $response->getContent();
        $this->assertSame(200, $response->getStatusCode());
        // The literal "]]>" in a user-controlled torrent name is split
        // across two CDATA blocks, so it can never close the section and
        // inject markup into the cached feed.
        $this->assertStringContainsString(']]]]><![CDATA[>', $content);
        $this->assertStringNotContainsString(']]> <script>alert(1)</script>', $content);
        $this->assertStringContainsString('alert(1)', $content);
    }

    /**
     * Bind a mock TorrentRepository so the controller can be resolved.
     */
    private function bindTorrentRepository(): void
    {
        /** @var TorrentRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(TorrentRepository::class)->shouldIgnoreMissing();
        app()->instance(TorrentRepository::class, $repository);
    }

    /**
     * Bind a partial mock of CurrentUser that returns the given user array.
     *
     * @param  array<string, mixed>|null  $user
     */
    private function mockCurrentUser(?array $user): void
    {
        $real = new CurrentUser;
        $real->set($user);
        app()->instance(CurrentUser::class, $real);
        $this->app->instance(
            ActorContext::class,
            $user === null
                ? $this->actor([])
                : $this->actor(array_merge(['id' => 1, 'username' => 'testuser'], $user)),
        );
    }
}
