<?php

namespace Tests\Unit\Support;

use App\Support\Comment;
use PHPUnit\Framework\TestCase;

final class CommentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Comment::resetTempCode();
    }

    public function test_add_temp_code_returns_unique_placeholders(): void
    {
        $a = Comment::addTempCode('first');
        $b = Comment::addTempCode('second');

        $this->assertSame('<tempCode_0>', $a);
        $this->assertSame('<tempCode_1>', $b);
    }

    public function test_reset_temp_code_restarts_counter(): void
    {
        Comment::addTempCode('first');
        Comment::resetTempCode();

        $this->assertSame('<tempCode_0>', Comment::addTempCode('second'));
    }
}
