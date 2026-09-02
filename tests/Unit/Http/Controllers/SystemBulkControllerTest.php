<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\SystemBulkController;
use App\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

final class SystemBulkControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_setlist_lookup_returns_error_when_name_and_url_empty(): void
    {
        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        $controller = new SystemBulkController($userRepository);
        $request = Request::create('/api/setlist-lookup', 'GET', ['name' => '', 'url' => '']);
        app()->instance('request', $request);

        $response = $controller->setlistLookup($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('required', $data['error']);
    }

    public function test_setlist_lookup_returns_error_for_non_setlist_fm_url(): void
    {
        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        $controller = new SystemBulkController($userRepository);
        $request = Request::create('/api/setlist-lookup', 'GET', ['url' => 'https://example.com/setlist']);
        app()->instance('request', $request);

        $response = $controller->setlistLookup($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('setlist.fm', $data['error']);
    }

    public function test_setlist_lookup_returns_error_for_non_setlist_fm_url_even_with_name(): void
    {
        /** @var UserRepository&Mockery\MockInterface $userRepository */
        $userRepository = Mockery::mock(UserRepository::class);

        $controller = new SystemBulkController($userRepository);
        $request = Request::create('/api/setlist-lookup', 'GET', [
            'name' => 'Some Torrent',
            'url' => 'https://google.com/test',
        ]);
        app()->instance('request', $request);

        $response = $controller->setlistLookup($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('setlist.fm', $data['error']);
    }
}
