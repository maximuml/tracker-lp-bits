<?php

namespace Tests\Unit\Support;

use App\Support\Hooks;
use Nexus\Plugin\Hook;
use PHPUnit\Framework\TestCase;

final class HooksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['hook'] = new Hook();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['hook']);
        parent::tearDown();
    }

    public function test_add_filter_and_apply_filter_modify_value(): void
    {
        Hooks::addFilter('test.filter', static fn ($value) => $value . ' modified', 10, 1);

        $this->assertSame('input modified', Hooks::applyFilter('test.filter', 'input'));
    }

    public function test_priority_ordering(): void
    {
        Hooks::addFilter('test.priority', static fn ($value) => $value . 'A', 20, 1);
        Hooks::addFilter('test.priority', static fn ($value) => $value . 'B', 10, 1);

        $this->assertSame('startBA', Hooks::applyFilter('test.priority', 'start'));
    }

    public function test_add_action_and_do_action_call_callback(): void
    {
        $called = false;
        Hooks::addAction('test.action', function () use (&$called) {
            $called = true;
        }, 10, 0);

        Hooks::doAction('test.action');

        $this->assertTrue($called);
    }

    public function test_do_action_with_arguments_passes_them_to_callback(): void
    {
        $received = null;
        Hooks::addAction('test.action.args', function ($arg) use (&$received) {
            $received = $arg;
        }, 10, 1);

        Hooks::doAction('test.action.args', 'hello');

        $this->assertSame('hello', $received);
    }
}
