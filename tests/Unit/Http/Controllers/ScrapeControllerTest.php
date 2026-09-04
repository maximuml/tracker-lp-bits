<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use App\Http\Controllers\ScrapeController;
use App\Services\ScrapeService;
use Illuminate\Http\Request;
use Mockery;
use Rhilip\Bencode\Bencode;
use Tests\TestCase;

final class ScrapeControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createValidScrapeRequest(array $overrides = []): Request
    {
        $params = array_merge([
            'passkey' => str_repeat('a', 32),
            'info_hash' => str_repeat('A', 20),
        ], $overrides);

        $request = Request::create('/scrape.php', 'GET', $params);
        $request->headers->set('User-Agent', 'Transmission/4.0.0');

        return $request;
    }

    public function test_scrape_returns_failure_on_tracker_exception(): void
    {
        /** @var ScrapeService&Mockery\MockInterface $service */
        $service = Mockery::mock(ScrapeService::class);
        $service->shouldReceive('scrape')
            ->once()
            ->andThrow(new TrackerException('Invalid passkey'));

        $controller = new ScrapeController($service);
        $request = $this->createValidScrapeRequest();

        $response = $controller->scrape($request);

        $this->assertSame(200, $response->status());
        $decoded = Bencode::decode($response->getContent());
        $this->assertSame('Invalid passkey', $decoded['failure reason']);
    }

    public function test_scrape_returns_warning_on_tracker_warning(): void
    {
        /** @var ScrapeService&Mockery\MockInterface $service */
        $service = Mockery::mock(ScrapeService::class);
        $service->shouldReceive('scrape')
            ->once()
            ->andThrow(new TrackerWarningException('Too many info_hashes', ['files' => []], 7200));

        $controller = new ScrapeController($service);
        $request = $this->createValidScrapeRequest();

        $response = $controller->scrape($request);

        $this->assertSame(200, $response->status());
        $decoded = Bencode::decode($response->getContent());
        $this->assertSame('Too many info_hashes', $decoded['warning message']);
    }

    public function test_scrape_response_has_correct_content_type(): void
    {
        /** @var ScrapeService&Mockery\MockInterface $service */
        $service = Mockery::mock(ScrapeService::class);
        $service->shouldReceive('scrape')
            ->once()
            ->andReturn(['files' => []]);

        $controller = new ScrapeController($service);
        $request = $this->createValidScrapeRequest();

        $response = $controller->scrape($request);

        $this->assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
    }

    public function test_scrape_response_has_no_cache_pragma(): void
    {
        /** @var ScrapeService&Mockery\MockInterface $service */
        $service = Mockery::mock(ScrapeService::class);
        $service->shouldReceive('scrape')
            ->once()
            ->andReturn(['files' => []]);

        $controller = new ScrapeController($service);
        $request = $this->createValidScrapeRequest();

        $response = $controller->scrape($request);

        $this->assertSame('no-cache', $response->headers->get('Pragma'));
    }
}
