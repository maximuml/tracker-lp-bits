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

    /** @return array<string, mixed> */
    private function validParams(): array
    {
        return [
            'passkey' => '0123456789abcdef0123456789abcdef',
            'info_hash' => str_repeat("\x01", 20),
            'peer_id' => str_repeat("\x02", 20),
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'left' => 1000,
            'event' => 'started',
        ];
    }

    public function test_announce_returns_bencoded_success_response(): void
    {
        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldReceive('handle')
            ->once()
            ->andReturn([
                'interval' => 1800,
                'min interval' => 1800,
                'complete' => 1,
                'incomplete' => 2,
                'downloaded' => 3,
                'peers' => '',
                'peers6' => '',
            ]);

        $controller = new AnnounceController($service);
        $request = Request::create('/announce', 'GET', $this->validParams());

        $response = $controller->announce($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertSame('no-cache', $response->headers->get('Pragma'));

        $decoded = Bencode::decode($response->getContent());
        $this->assertSame(1800, $decoded['interval']);
        $this->assertSame(1, $decoded['complete']);
        $this->assertSame(2, $decoded['incomplete']);
        $this->assertSame('', $decoded['peers']);
    }

    public function test_announce_returns_failure_reason_when_passkey_missing(): void
    {
        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldNotReceive('handle');

        $controller = new AnnounceController($service);
        $request = Request::create('/announce', 'GET', [
            'info_hash' => str_repeat("\x01", 20),
            'peer_id' => str_repeat("\x02", 20),
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'left' => 1000,
        ]);

        $response = $controller->announce($request);

        $this->assertSame(200, $response->getStatusCode());
        $decoded = Bencode::decode($response->getContent());
        $this->assertArrayHasKey('failure reason', $decoded);
    }

    public function test_announce_returns_failure_reason_when_passkey_invalid(): void
    {
        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldNotReceive('handle');

        $controller = new AnnounceController($service);
        $params = $this->validParams();
        $params['passkey'] = 'too-short';

        $request = Request::create('/announce', 'GET', $params);

        $response = $controller->announce($request);

        $this->assertSame(200, $response->getStatusCode());
        $decoded = Bencode::decode($response->getContent());
        $this->assertArrayHasKey('failure reason', $decoded);
    }

    public function test_announce_returns_failure_reason_when_info_hash_missing(): void
    {
        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldNotReceive('handle');

        $controller = new AnnounceController($service);
        $params = $this->validParams();
        unset($params['info_hash']);

        $request = Request::create('/announce', 'GET', $params);

        $response = $controller->announce($request);

        $decoded = Bencode::decode($response->getContent());
        $this->assertArrayHasKey('failure reason', $decoded);
    }

    public function test_announce_returns_failure_reason_when_port_missing(): void
    {
        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldNotReceive('handle');

        $controller = new AnnounceController($service);
        $params = $this->validParams();
        unset($params['port']);

        $request = Request::create('/announce', 'GET', $params);

        $response = $controller->announce($request);

        $decoded = Bencode::decode($response->getContent());
        $this->assertArrayHasKey('failure reason', $decoded);
    }

    public function test_announce_returns_failure_reason_when_port_out_of_range(): void
    {
        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldNotReceive('handle');

        $controller = new AnnounceController($service);
        $params = $this->validParams();
        $params['port'] = 99999;

        $request = Request::create('/announce', 'GET', $params);

        $response = $controller->announce($request);

        $decoded = Bencode::decode($response->getContent());
        $this->assertArrayHasKey('failure reason', $decoded);
    }

    public function test_announce_returns_failure_reason_on_tracker_exception(): void
    {
        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldReceive('handle')
            ->once()
            ->andThrow(TrackerException::failure('torrent not registered'));

        $controller = new AnnounceController($service);
        $request = Request::create('/announce', 'GET', $this->validParams());

        $response = $controller->announce($request);

        $this->assertSame(200, $response->getStatusCode());
        $decoded = Bencode::decode($response->getContent());
        $this->assertSame('torrent not registered', $decoded['failure reason']);
    }

    public function test_announce_returns_warning_on_tracker_warning_exception(): void
    {
        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldReceive('handle')
            ->once()
            ->andThrow(new TrackerWarningException(
                'port 6881 is blacklisted',
                ['interval' => 1800, 'min interval' => 1800],
                7200
            ));

        $controller = new AnnounceController($service);
        $request = Request::create('/announce', 'GET', $this->validParams());

        $response = $controller->announce($request);

        $this->assertSame(200, $response->getStatusCode());
        $decoded = Bencode::decode($response->getContent());
        $this->assertSame('port 6881 is blacklisted', $decoded['warning message']);
        $this->assertSame(7200, $decoded['interval']);
        $this->assertSame(7200, $decoded['min interval']);
    }

    public function test_announce_normalizes_unknown_event_to_null(): void
    {
        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldReceive('handle')
            ->once()
            ->with(Mockery::any(), Mockery::on(function (array $params): bool {
                return $params['event'] === null;
            }))
            ->andReturn(['interval' => 1800]);

        $controller = new AnnounceController($service);
        $params = $this->validParams();
        $params['event'] = 'not-a-real-event';

        $request = Request::create('/announce', 'GET', $params);

        $response = $controller->announce($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_announce_passes_valid_event_started(): void
    {
        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldReceive('handle')
            ->once()
            ->with(Mockery::any(), Mockery::on(function (array $params): bool {
                return $params['event'] === 'started';
            }))
            ->andReturn(['interval' => 1800]);

        $controller = new AnnounceController($service);
        $params = $this->validParams();
        $params['event'] = 'started';

        $request = Request::create('/announce', 'GET', $params);

        $response = $controller->announce($request);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_announce_passes_valid_event_completed(): void
    {
        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldReceive('handle')
            ->once()
            ->with(Mockery::any(), Mockery::on(function (array $params): bool {
                return $params['event'] === 'completed';
            }))
            ->andReturn(['interval' => 1800]);

        $controller = new AnnounceController($service);
        $params = $this->validParams();
        $params['event'] = 'completed';

        $request = Request::create('/announce', 'GET', $params);

        $response = $controller->announce($request);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_announce_passes_valid_event_stopped(): void
    {
        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldReceive('handle')
            ->once()
            ->with(Mockery::any(), Mockery::on(function (array $params): bool {
                return $params['event'] === 'stopped';
            }))
            ->andReturn(['interval' => 1800]);

        $controller = new AnnounceController($service);
        $params = $this->validParams();
        $params['event'] = 'stopped';

        $request = Request::create('/announce', 'GET', $params);

        $response = $controller->announce($request);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_announce_passes_valid_event_paused(): void
    {
        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldReceive('handle')
            ->once()
            ->with(Mockery::any(), Mockery::on(function (array $params): bool {
                return $params['event'] === 'paused';
            }))
            ->andReturn(['interval' => 1800]);

        $controller = new AnnounceController($service);
        $params = $this->validParams();
        $params['event'] = 'paused';

        $request = Request::create('/announce', 'GET', $params);

        $response = $controller->announce($request);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_announce_normalizes_empty_event_to_null(): void
    {
        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldReceive('handle')
            ->once()
            ->with(Mockery::any(), Mockery::on(function (array $params): bool {
                return $params['event'] === null;
            }))
            ->andReturn(['interval' => 1800]);

        $controller = new AnnounceController($service);
        $params = $this->validParams();
        $params['event'] = '';

        $request = Request::create('/announce', 'GET', $params);

        $response = $controller->announce($request);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_announce_normalizes_missing_event_to_null(): void
    {
        /** @var AnnounceService&Mockery\MockInterface $service */
        $service = Mockery::mock(AnnounceService::class);
        $service->shouldReceive('handle')
            ->once()
            ->with(Mockery::any(), Mockery::on(function (array $params): bool {
                return $params['event'] === null;
            }))
            ->andReturn(['interval' => 1800]);

        $controller = new AnnounceController($service);
        $params = $this->validParams();
        unset($params['event']);

        $request = Request::create('/announce', 'GET', $params);

        $response = $controller->announce($request);
        $this->assertSame(200, $response->getStatusCode());
    }
}
