<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\TorrentAjaxController;
use App\Models\User;
use App\Repositories\TorrentAjaxRepository;
use App\Support\CurrentUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class TorrentAjaxControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_view_file_list_returns_400_for_invalid_id(): void
    {
        $controller = new TorrentAjaxController;
        $request = Request::create('/viewfilelist', 'GET', ['id' => 0]);
        app()->instance('request', $request);

        $response = $controller->viewFileList($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_view_file_list_returns_200_for_valid_id(): void
    {
        $repo = $this->mockTorrentAjaxRepository();
        $repo->shouldReceive('fileList')->once()->with(1)->andReturn(new Collection);

        $controller = new TorrentAjaxController;
        $request = Request::create('/viewfilelist', 'GET', ['id' => 1]);
        app()->instance('request', $request);

        $response = $controller->viewFileList($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_view_peer_list_returns_400_for_invalid_id(): void
    {
        $controller = new TorrentAjaxController;
        $request = Request::create('/viewpeerlist', 'GET', ['id' => 0]);
        app()->instance('request', $request);

        $response = $controller->viewPeerList($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_view_peer_list_returns_200_for_valid_id(): void
    {
        $this->mockCurrentUser([]);
        $repo = $this->mockTorrentAjaxRepository();
        $repo->shouldReceive('peerList')->once()->with(1, null)->andReturn([
            'torrent' => [],
            'seeders' => [],
            'leechers' => [],
            'privacyData' => [],
            'showLocationColumn' => false,
            'peerIpInfo' => [],
            'usernameHtmlMap' => [],
            'enablelocationTweak' => 'no',
            'currentUser' => null,
            'CURUSER' => [],
            'lang_viewpeerlist' => $this->viewPeerListLang(),
        ]);

        $controller = new TorrentAjaxController;
        $request = Request::create('/viewpeerlist', 'GET', ['id' => 1]);
        app()->instance('request', $request);

        $response = $controller->viewPeerList($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_get_user_torrent_list_returns_400_for_invalid_userid(): void
    {
        $controller = new TorrentAjaxController;
        $request = Request::create('/getusertorrentlistajax', 'GET', ['userid' => 0, 'type' => 'uploaded']);
        app()->instance('request', $request);

        $response = $controller->getUserTorrentListAjax($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_get_user_torrent_list_returns_400_for_invalid_type(): void
    {
        $controller = new TorrentAjaxController;
        $request = Request::create('/getusertorrentlistajax', 'GET', ['userid' => 1, 'type' => 'invalid']);
        app()->instance('request', $request);

        $response = $controller->getUserTorrentListAjax($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_get_user_torrent_list_returns_403_without_permission(): void
    {
        $this->mockCurrentUser(null);

        $controller = new TorrentAjaxController;
        $request = Request::create('/getusertorrentlistajax', 'GET', ['userid' => 1, 'type' => 'uploaded']);
        app()->instance('request', $request);

        $response = $controller->getUserTorrentListAjax($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_get_user_torrent_list_returns_200_for_valid_request(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $userId = (int) $user->getKey();

        $this->mockCurrentUser(['id' => $userId, 'enabled' => true]);
        $repo = $this->mockTorrentAjaxRepository();
        $repo->shouldReceive('userTorrentList')->once()->andReturn([
            'id' => $userId,
            'type' => 'uploaded',
            'rows' => [],
            'count' => 0,
            'total_size' => 0,
            'pagertop' => '',
            'pagerbottom' => '',
            'torrentRep' => null,
            'seedTimeAndUploaded' => collect(),
            'CURUSER' => ['id' => $userId],
            'lang_getusertorrentlistajax' => $this->userTorrentListLang(),
            'lang_functions' => ['text_banned' => 'Banned'],
        ]);

        $controller = new TorrentAjaxController;
        $request = Request::create('/getusertorrentlistajax', 'GET', ['userid' => $userId, 'type' => 'uploaded']);
        app()->instance('request', $request);

        $response = $controller->getUserTorrentListAjax($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_search_suggest_returns_empty_array_for_empty_query(): void
    {
        $controller = new TorrentAjaxController;
        $request = Request::create('/searchsuggest', 'GET', ['q' => '']);
        app()->instance('request', $request);

        $response = $controller->searchSuggest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('[]', $response->getContent());
    }

    public function test_search_suggest_returns_suggestions_for_valid_query(): void
    {
        $repo = $this->mockTorrentAjaxRepository();
        $repo->shouldReceive('searchSuggest')->once()->with('test')->andReturn(['test', ['foo', 'bar'], [10, 20]]);

        $controller = new TorrentAjaxController;
        $request = Request::create('/searchsuggest', 'GET', ['q' => 'test']);
        app()->instance('request', $request);

        $response = $controller->searchSuggest($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame('test', $data[0]);
        $this->assertSame(['foo', 'bar'], $data[1]);
        $this->assertSame([10, 20], $data[2]);
    }

    public function test_autocomplete_torrents_returns_empty_for_empty_query(): void
    {
        $controller = new TorrentAjaxController;
        $request = Request::create('/autocomplete', 'GET', ['q' => '']);
        app()->instance('request', $request);

        $response = $controller->autocompleteTorrents($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertSame([], $data['torrents']);
    }

    public function test_autocomplete_torrents_returns_empty_for_no_user(): void
    {
        $this->mockCurrentUser(['id' => 99999]);

        $controller = new TorrentAjaxController;
        $request = Request::create('/autocomplete', 'GET', ['q' => 'test']);
        app()->instance('request', $request);

        $response = $controller->autocompleteTorrents($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertSame([], $data['torrents']);
    }

    public function test_autocomplete_torrents_returns_torrents_for_valid_request(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $userId = (int) $user->getKey();

        $this->mockCurrentUser(['id' => $userId, 'enabled' => true]);
        $repo = $this->mockTorrentAjaxRepository();
        $repo->shouldReceive('autocompleteTorrents')->once()->andReturn(['torrents' => [['id' => 1, 'name' => 'Test Torrent']]]);

        $controller = new TorrentAjaxController;
        $request = Request::create('/autocomplete', 'GET', ['q' => 'test']);
        app()->instance('request', $request);

        $response = $controller->autocompleteTorrents($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertCount(1, $data['torrents']);
        $this->assertSame('Test Torrent', $data['torrents'][0]['name']);
    }

    /**
     * Bind a partial mock of TorrentAjaxRepository so the controller's
     * app() resolution returns the mock instead of hitting the database.
     */
    private function mockTorrentAjaxRepository(): MockInterface
    {
        $real = new TorrentAjaxRepository;
        $mock = Mockery::mock($real);
        app()->instance(TorrentAjaxRepository::class, $mock);

        return $mock;
    }

    /**
     * Bind a partial mock of CurrentUser that returns the given user array.
     *
     * @param  array<string, mixed>|null  $user
     */
    private function mockCurrentUser(?array $user): void
    {
        $real = new CurrentUser;
        $mock = Mockery::mock($real);
        $mock->shouldReceive('get')->andReturn($user);
        app()->instance(CurrentUser::class, $mock);
    }

    /**
     * @return array<string, string>
     */
    private function viewPeerListLang(): array
    {
        return [
            'text_seeders' => 'Seeders',
            'text_leechers' => 'Leechers',
            'text_anonymous' => 'Anonymous',
            'text_inf' => 'Inf.',
            'text_yes' => 'Yes',
            'text_no' => 'No',
            'col_user_ip' => 'User/IP',
            'col_uploaded' => 'Uploaded',
            'col_downloaded' => 'Downloaded',
            'col_ratio' => 'Ratio',
            'col_rate' => 'Rate',
            'col_idle' => 'Idle',
            'col_connected' => 'Connected',
            'col_connectable' => 'Connectable',
            'col_client' => 'Client',
            'col_complete' => 'Complete',
            'col_location' => 'Location',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function userTorrentListLang(): array
    {
        return [
            'col_type' => 'Type',
            'col_name' => 'Name',
            'col_added' => 'Added',
            'title_size' => 'Size',
            'title_seeders' => 'Seeders',
            'title_leechers' => 'Leechers',
            'col_uploaded' => 'Uploaded',
            'col_downloaded' => 'Downloaded',
            'col_ratio' => 'Ratio',
            'col_se_time' => 'SE Time',
            'col_le_time' => 'LE Time',
            'col_time_completed' => 'Completed',
            'col_anonymous' => 'Anonymous',
            'col_client' => 'Client',
            'text_no_record' => 'No records',
            'text_record' => ' record',
            'text_total_size' => 'Total size: ',
        ];
    }
}
