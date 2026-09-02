<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\BonusRepository;
use App\Services\BonusService;
use App\Support\Globals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for BonusService.
 *
 * Covers the public handleExchangeActionPublic entry point:
 * action routing, POST enforcement, insufficient bonus guard,
 * and unknown art type handling.
 */
final class BonusServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function service(): BonusService
    {
        /** @var BonusRepository&Mockery\MockInterface $repo */
        $repo = Mockery::mock(BonusRepository::class);

        return new BonusService($repo);
    }

    public function test_returns_null_when_action_is_not_exchange(): void
    {
        $request = Request::create('/mybonus.php', 'GET', ['action' => 'view']);

        $result = $this->service()->handleExchangeActionPublic(
            $request,
            [],
            ['id' => 1, 'username' => 'test', 'seedbonus' => 1000],
            [],
            'locked',
        );

        $this->assertNull($result);
    }

    public function test_returns_null_when_action_is_empty(): void
    {
        $request = Request::create('/mybonus.php', 'GET');

        $result = $this->service()->handleExchangeActionPublic(
            $request,
            [],
            ['id' => 1, 'username' => 'test', 'seedbonus' => 1000],
            [],
            'locked',
        );

        $this->assertNull($result);
    }

    public function test_redirects_to_mybonus_when_not_post(): void
    {
        $request = Request::create('/mybonus.php?action=exchange', 'GET');

        $result = $this->service()->handleExchangeActionPublic(
            $request,
            [],
            ['id' => 1, 'username' => 'test', 'seedbonus' => 1000],
            [],
            'locked',
        );

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('mybonus.php', $result->getTargetUrl());
    }

    public function test_returns_null_when_insufficient_bonus(): void
    {
        // Bind a real Globals instance (it's final, can't be mocked)
        $this->app->instance(Globals::class, new Globals);

        $allBonus = [
            0 => ['art' => 'traffic', 'points' => 5000, 'value' => 10737418240],
        ];

        $request = Request::create('/mybonus.php?action=exchange', 'POST', [
            'option' => 0,
        ]);

        $result = $this->service()->handleExchangeActionPublic(
            $request,
            $allBonus,
            ['id' => 1, 'username' => 'test', 'seedbonus' => 100, 'ip' => '1.2.3.4'],
            [],
            'locked',
        );

        $this->assertNull($result, 'Should return null when user has insufficient bonus points');
    }

    public function test_returns_null_for_unknown_art_type(): void
    {
        $this->app->instance(Globals::class, new Globals);

        // Lock uses Redis internally; in the test environment Redis is available.
        // The lock will be acquired and released automatically.
        $allBonus = [
            0 => ['art' => 'unknown_type', 'points' => 100, 'value' => 0],
        ];

        $request = Request::create('/mybonus.php?action=exchange', 'POST', [
            'option' => 0,
        ]);

        $result = $this->service()->handleExchangeActionPublic(
            $request,
            $allBonus,
            ['id' => 1, 'username' => 'test', 'seedbonus' => 500, 'ip' => '1.2.3.4'],
            [],
            'locked',
        );

        $this->assertNull($result, 'Should return null for unknown art type');
    }
}
