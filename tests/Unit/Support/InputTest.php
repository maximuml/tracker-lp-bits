<?php

namespace Tests\Unit\Support;

use App\Support\Input;
use App\Support\SupportContext;
use PHPUnit\Framework\TestCase;

final class InputTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SupportContext::reset();
    }

    public function test_globalize_imports_get_variables_into_context(): void
    {
        $result = Input::globalize(['id', 'name'], ['id' => '5', 'name' => 'test'], []);

        $this->assertSame(1, $result);
        $this->assertSame('5', SupportContext::getGlobal('id'));
        $this->assertSame('test', SupportContext::getGlobal('name'));
    }

    public function test_globalize_imports_post_when_get_missing(): void
    {
        $result = Input::globalize('id:name', [], ['id' => '7', 'name' => 'post']);

        $this->assertSame(1, $result);
        $this->assertSame('7', SupportContext::getGlobal('id'));
        $this->assertSame('post', SupportContext::getGlobal('name'));
    }

    public function test_globalize_prefers_get_over_post(): void
    {
        $result = Input::globalize(['id'], ['id' => 'from_get'], ['id' => 'from_post']);

        $this->assertSame('from_get', SupportContext::getGlobal('id'));
    }

    public function test_globalize_returns_zero_when_any_key_missing(): void
    {
        $result = Input::globalize(['id', 'missing'], ['id' => '5'], []);

        $this->assertSame(0, $result);
        $this->assertNull(SupportContext::getGlobal('missing'));
    }

    public function test_globalize_accepts_colon_separated_string(): void
    {
        $result = Input::globalize('foo:bar', ['foo' => 'a', 'bar' => 'b'], []);

        $this->assertSame(1, $result);
        $this->assertSame('a', SupportContext::getGlobal('foo'));
        $this->assertSame('b', SupportContext::getGlobal('bar'));
    }

    public function test_unescape_returns_value_unchanged(): void
    {
        $this->assertSame('hello', Input::unescape('hello'));
        $this->assertSame(123, Input::unescape(123));
        $this->assertNull(Input::unescape(null));
    }
}
