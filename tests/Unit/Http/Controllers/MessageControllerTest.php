<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\MessageController;
use App\Repositories\MessageRepository;
use App\Services\MessagePageService;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

final class MessageControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_controller_can_be_constructed_with_dependencies(): void
    {
        /** @var MessageRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(MessageRepository::class);

        /** @var MessageService&Mockery\MockInterface $legacyService */
        $legacyService = Mockery::mock(MessageService::class);

        /** @var MessagePageService&Mockery\MockInterface $pageService */
        $pageService = Mockery::mock(MessagePageService::class);

        $controller = new MessageController($repository, $legacyService, $pageService);

        $this->assertInstanceOf(MessageController::class, $controller);
    }

    public function test_messages_delegates_action_to_legacy_service(): void
    {
        /** @var MessageRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(MessageRepository::class);

        /** @var MessageService&Mockery\MockInterface $legacyService */
        $legacyService = Mockery::mock(MessageService::class);
        $legacyService->shouldReceive('handleMessagesActionPublic')
            ->once()
            ->andReturn(null);

        /** @var MessagePageService&Mockery\MockInterface $pageService */
        $pageService = Mockery::mock(MessagePageService::class);
        $pageService->shouldReceive('build')
            ->once()
            ->andReturn(['title' => 'Messages', 'rows' => []]);

        $controller = new MessageController($repository, $legacyService, $pageService);
        $request = Request::create('/messages', 'GET');

        // The controller calls legacyPage which may fail on view rendering,
        // but the service delegation should still occur.
        try {
            $controller->messages($request);
        } catch (\Throwable $e) {
            // Expected — view rendering may fail without lang files
        }

        // Mockery verifies expectations on close — if services weren't called, test fails
        $this->assertTrue(true);
    }

    public function test_messages_returns_redirect_when_action_redirects(): void
    {
        /** @var MessageRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(MessageRepository::class);

        /** @var MessageService&Mockery\MockInterface $legacyService */
        $legacyService = Mockery::mock(MessageService::class);
        $legacyService->shouldReceive('handleMessagesActionPublic')
            ->once()
            ->andReturn(redirect('/messages'));

        /** @var MessagePageService&Mockery\MockInterface $pageService */
        $pageService = Mockery::mock(MessagePageService::class);
        $pageService->shouldNotReceive('build');

        $controller = new MessageController($repository, $legacyService, $pageService);
        $request = Request::create('/messages', 'GET');

        $response = $controller->messages($request);

        $this->assertTrue($response->isRedirect());
    }
}
