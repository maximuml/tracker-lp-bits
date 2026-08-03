<?php

namespace Tests\Unit\Support;

use App\Support\Input;
use PHPUnit\Framework\TestCase;

final class InputTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (['id', 'name', 'foo'] as $key) {
            if (isset($GLOBALS[$key])) {
                unset($GLOBALS[$key]);
            }
        }
        parent::tearDown();
    }

    public function test_globalize_imports_get_variables_into_globals(): void
    {
        $result = Input::globalize(['id', 'name'], ['id' => '5', 'name' => 'test'], []);

        $this->assertSame(1, $result);
        $this->assertSame('5', $GLOBALS['id']);
        $this->assertSame('test', $GLOBALS['name']);
    }

    public function test_globalize_imports_post_when_get_missing(): void
    {
        $result = Input::globalize('id:name', [], ['id' => '7', 'name' => 'post']);

        $this->assertSame(1, $result);
        $this->assertSame('7', $GLOBALS['id']);
        $this->assertSame('post', $GLOBALS['name']);
    }

    public function test_globalize_prefers_get_over_post(): void
    {
        $result = Input::globalize(['id'], ['id' => 'from_get'], ['id' => 'from_post']);

        $this->assertSame('from_get', $GLOBALS['id']);
    }

    public function test_globalize_returns_zero_when_any_key_missing(): void
    {
        $result = Input::globalize(['id', 'missing'], ['id' => '5'], []);

        $this->assertSame(0, $result);
        $this->assertFalse(isset($GLOBALS['missing']));
    }

    public function test_globalize_accepts_colon_separated_string(): void
    {
        $result = Input::globalize('foo:bar', ['foo' => 'a', 'bar' => 'b'], []);

        $this->assertSame(1, $result);
        $this->assertSame('a', $GLOBALS['foo']);
        $this->assertSame('b', $GLOBALS['bar']);
    }

    public function test_unescape_returns_value_unchanged(): void
    {
        $this->assertSame('hello', Input::unescape('hello'));
        $this->assertSame(123, Input::unescape(123));
        $this->assertNull(Input::unescape(null));
    }
}
