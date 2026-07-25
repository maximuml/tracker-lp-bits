<?php

namespace Tests\Unit\Support;

use App\Support\Arrays;
use PHPUnit\Framework\TestCase;

class ArraysTest extends TestCase
{
    public function test_get_dot_notation(): void
    {
        $array = ['foo' => ['bar' => 'baz']];
        $this->assertSame('baz', Arrays::get($array, 'foo.bar'));
        $this->assertSame('default', Arrays::get($array, 'foo.missing', 'default'));
    }

    public function test_set_dot_notation(): void
    {
        $array = [];
        Arrays::set($array, 'foo.bar', 'baz');
        $this->assertSame(['foo' => ['bar' => 'baz']], $array);
    }
}
