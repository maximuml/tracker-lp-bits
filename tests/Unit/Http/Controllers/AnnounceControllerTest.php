<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use App\Http\Controllers\AnnounceController;
use App\Services\AnnounceService;
use Illuminate\Http\Request;
use Mockery;
use Rhilip\Bencode\Bencode;
use Tests\TestCase;

final class AnnounceControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createValidRequest(array $overrides = []): Request
    {
        $params = array_merge([
            'passkey' => str_repeat('a', 32),
            'info_hash' => str_repeat('A', 20),
            'peer_id' => str_repeat('A', 20),
            'port' => 54321,
            'uploaded' => 0,
            'downloaded' => 0,
            'left' => 0,
        ], $overrides);

        $request = Request::create('/announce.php', 'GET', $params);

        return $request;
    }

    public function test_announce_returns_success_response_with_valid_request(): void
    {
        $responseData = ['complete' => 1, 'incomplete' => 0, 'interval' => 1800, 'peers' => ''];

        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldReceive('handle')
            ->once()
            ->andReturn($responseData);

        $controller = new AnnounceController($service);
        $request = $this->createValidRequest(['event' => 'started', 'compact' => 1]);

        $response = $controller->announce($request);

        $this->assertSame(200, $response->status());
        $this->assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
        $decoded = Bencode::decode($response->getContent());
        $this->assertSame(1, $decoded['complete']);
        $this->assertSame(0, $decoded['incomplete']);
    }

    public function test_announce_returns_failure_on_validation_error(): void
    {
        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldNotReceive('handle');

        $controller = new AnnounceController($service);
        $request = Request::create('/announce.php', 'GET', [
            'passkey' => 'short',
            'info_hash' => str_repeat('A', 20),
            'peer_id' => str_repeat('A', 20),
            'port' => 54321,
            'uploaded' => 0,
            'downloaded' => 0,
            'left' => 0,
        ]);

        $response = $controller->announce($request);

        $this->assertSame(200, $response->status());
        $decoded = Bencode::decode($response->getContent());
        $this->assertArrayHasKey('failure reason', $decoded);
    }

    public function test_announce_returns_failure_on_tracker_exception(): void
    {
        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldReceive('handle')
            ->once()
            ->andThrow(new TrackerException('Banned Client'));

        $controller = new AnnounceController($service);
        $request = $this->createValidRequest();

        $response = $controller->announce($request);

        $this->assertSame(200, $response->status());
        $decoded = Bencode::decode($response->getContent());
        $this->assertSame('Banned Client', $decoded['failure reason']);
    }

    public function test_announce_returns_warning_response_on_tracker_warning(): void
    {
        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldReceive('handle')
            ->once()
            ->andThrow(new TrackerWarningException('Port is blacklisted', ['peers' => ''], 1800));

        $controller = new AnnounceController($service);
        $request = $this->createValidRequest(['port' => 6881]);

        $response = $controller->announce($request);

        $this->assertSame(200, $response->status());
        $decoded = Bencode::decode($response->getContent());
        $this->assertSame('Port is blacklisted', $decoded['warning message']);
    }

    public function test_announce_invalid_event_is_set_to_null(): void
    {
        $capturedValidated = null;

        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldReceive('handle')
            ->once()
            ->withArgs(function ($req, $validated) use (&$capturedValidated): bool {
                $capturedValidated = $validated;

                return true;
            })
            ->andReturn(['complete' => 0, 'incomplete' => 0, 'interval' => 1800, 'peers' => '']);

        $controller = new AnnounceController($service);
        $request = $this->createValidRequest(['event' => 'invalid_event']);

        $controller->announce($request);

        $this->assertNull($capturedValidated['event']);
    }

    public function test_announce_valid_events_are_preserved(): void
    {
        $validEvents = ['started', 'completed', 'stopped', 'paused'];

        foreach ($validEvents as $event) {
            $capturedValidated = null;

            /** @var AnnounceService&Mockery\MockInterface $service */
            $service = Mockery::mock(AnnounceService::class);
            $service->shouldReceive('handle')
                ->once()
                ->withArgs(function ($req, $validated) use (&$capturedValidated): bool {
                    $capturedValidated = $validated;

                    return true;
                })
                ->andReturn(['complete' => 0, 'incomplete' => 0, 'interval' => 1800, 'peers' => '']);

            $controller = new AnnounceController($service);
            $request = $this->createValidRequest(['event' => $event]);

            $controller->announce($request);

            $this->assertSame($event, $capturedValidated['event'], "Event '$event' should be preserved");
        }
    }

    public function test_announce_missing_event_is_set_to_null(): void
    {
        $capturedValidated = null;

        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldReceive('handle')
            ->once()
            ->withArgs(function ($req, $validated) use (&$capturedValidated): bool {
                $capturedValidated = $validated;

                return true;
            })
            ->andReturn(['complete' => 0, 'incomplete' => 0, 'interval' => 1800, 'peers' => '']);

        $controller = new AnnounceController($service);
        $request = $this->createValidRequest();

        $controller->announce($request);

        $this->assertNull($capturedValidated['event']);
    }

    public function test_announce_response_has_no_cache_headers(): void
    {
        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldReceive('handle')
            ->once()
            ->andReturn(['complete' => 0, 'incomplete' => 0, 'interval' => 1800, 'peers' => '']);

        $controller = new AnnounceController($service);
        $request = $this->createValidRequest();

        $response = $controller->announce($request);

        $this->assertSame('no-cache', $response->headers->get('Pragma'));
    }

    public function test_announce_empty_event_is_set_to_null(): void
    {
        $capturedValidated = null;

        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldReceive('handle')
            ->once()
            ->withArgs(function ($req, $validated) use (&$capturedValidated): bool {
                $capturedValidated = $validated;

                return true;
            })
            ->andReturn(['complete' => 0, 'incomplete' => 0, 'interval' => 1800, 'peers' => '']);

        $controller = new AnnounceController($service);
        $request = $this->createValidRequest(['event' => '']);

        $controller->announce($request);

        $this->assertNull($capturedValidated['event']);
    }
}
