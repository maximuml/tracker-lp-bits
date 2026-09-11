<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Contracts\Repositories\ExamRepositoryInterface;
use App\Contracts\Repositories\ForumRepositoryInterface;
use App\Contracts\Repositories\PostRepositoryInterface;
use App\Contracts\Repositories\SearchBoxRepositoryInterface;
use App\Contracts\Repositories\TagRepositoryInterface;
use App\Contracts\Repositories\ToolRepositoryInterface;
use App\Contracts\Repositories\TorrentDownloadRepositoryInterface;
use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Contracts\Repositories\UserModerationRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\TorrentController;
use App\Http\Controllers\TorrentDetailsController;
use App\Http\Controllers\UserAdminController;
use App\Http\Controllers\UserController;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * W3-step 3.1: every top-used repository interface must be injectable into
 * controllers as a mock — i.e. constructors type-hint the contract, not the
 * concrete (final) repository class.
 */
#[TestCategory(TestCategory::PURE_UNIT)]
final class RepositoryInterfaceInjectionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{0: class-string, 1: class-string}>
     */
    public static function controllerInterfaceProvider(): iterable
    {
        yield 'ExamController + ExamRepositoryInterface' => [ExamController::class, ExamRepositoryInterface::class];
        yield 'UserController + UserRepositoryInterface' => [UserController::class, UserRepositoryInterface::class];
        yield 'UserController + UserModerationRepositoryInterface' => [UserController::class, UserModerationRepositoryInterface::class];
        yield 'UserAdminController + UserModerationRepositoryInterface' => [UserAdminController::class, UserModerationRepositoryInterface::class];
        yield 'ToolController + ToolRepositoryInterface' => [ToolController::class, ToolRepositoryInterface::class];
        yield 'TagController + TagRepositoryInterface' => [TagController::class, TagRepositoryInterface::class];
        yield 'TorrentController + TorrentRepositoryInterface' => [TorrentController::class, TorrentRepositoryInterface::class];
        yield 'TorrentController + TorrentDownloadRepositoryInterface' => [TorrentController::class, TorrentDownloadRepositoryInterface::class];
        yield 'TorrentDetailsController + TorrentRepositoryInterface' => [TorrentDetailsController::class, TorrentRepositoryInterface::class];
        yield 'TorrentDetailsController + SearchBoxRepositoryInterface' => [TorrentDetailsController::class, SearchBoxRepositoryInterface::class];
        yield 'TopicController + ForumRepositoryInterface' => [TopicController::class, ForumRepositoryInterface::class];
        yield 'TopicController + PostRepositoryInterface' => [TopicController::class, PostRepositoryInterface::class];
    }

    /**
     * @param  class-string  $controllerClass
     * @param  class-string  $interface
     */
    #[DataProvider('controllerInterfaceProvider')]
    public function test_controller_resolves_interface_mock(string $controllerClass, string $interface): void
    {
        $mock = Mockery::mock($interface);
        $this->app->instance($interface, $mock);

        $controller = $this->app->make($controllerClass);

        $found = false;
        foreach ((new \ReflectionObject($controller))->getProperties() as $property) {
            if ($property->isInitialized($controller) && $property->getValue($controller) === $mock) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            sprintf('%s must type-hint %s so a mock can be injected', $controllerClass, $interface),
        );
    }
}
