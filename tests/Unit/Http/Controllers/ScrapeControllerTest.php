<?php

namespace Tests\Unit\Http\Controllers;

use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use App\Http\Controllers\ScrapeController;
use App\Services\ScrapeService;
use Illuminate\Http\Request;
use Mockery;
use Rhilip\Bencode\Bencode;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class ScrapeControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_scrape_returns_bencoded_files_dict(): void
    {
        /** @var ScrapeService&Mockery\MockInterface $service */
        $service = Mockery::mock(ScrapeService::class);
        $service->shouldReceive('scrape')->once()->andReturn([
            'files' => [
                "\x01\x02\x03" => [
                    'complete' => 5,
                    'downloaded' => 10,
                    'incomplete' => 2,
                ],
            ],
        ]);

        $controller = new ScrapeController($service);
        $request = Request::create('/scrape', 'GET', [
            'passkey' => '0123456789abcdef0123456789abcdef',
            'info_hash' => '%01%02%03',
        ]);

        $response = $controller->scrape($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));

        $decoded = Bencode::decode($response->getContent());
        $this->assertSame(['complete' => 5, 'downloaded' => 10, 'incomplete' => 2], $decoded['files']["\x01\x02\x03"]);
    }

    public function test_tracker_failure_returns_bencoded_failure_reason(): void
    {
        /** @var ScrapeService&Mockery\MockInterface $service */
        $service = Mockery::mock(ScrapeService::class);
        $service->shouldReceive('scrape')->once()->andThrow(TrackerException::failure('require passkey'));

        $controller = new ScrapeController($service);
        $request = Request::create('/scrape', 'GET');

        $response = $controller->scrape($request);

        $this->assertSame(200, $response->getStatusCode());
        $decoded = Bencode::decode($response->getContent());
        $this->assertSame('require passkey', $decoded['failure reason']);
    }

    public function test_tracker_warning_returns_bencoded_warning_with_interval(): void
    {
        /** @var ScrapeService&Mockery\MockInterface $service */
        $service = Mockery::mock(ScrapeService::class);
        $service->shouldReceive('scrape')->once()->andThrow(new TrackerWarningException(
            'Torrent not registered with this tracker.',
            ['files' => []],
            86400
        ));

        $controller = new ScrapeController($service);
        $request = Request::create('/scrape', 'GET', [
            'passkey' => '0123456789abcdef0123456789abcdef',
        ]);

        $response = $controller->scrape($request);

        $this->assertSame(200, $response->getStatusCode());
        $decoded = Bencode::decode($response->getContent());
        $this->assertSame('Torrent not registered with this tracker.', $decoded['warning message']);
        $this->assertSame(86400, $decoded['interval']);
        $this->assertSame(86400, $decoded['min interval']);
        $this->assertSame([], $decoded['files']);
    }
}
