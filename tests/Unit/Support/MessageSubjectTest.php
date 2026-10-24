<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\MessageSubject;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

#[TestCategory(TestCategory::PURE_UNIT)]
final class MessageSubjectTest extends TestCase
{
    public function test_adds_reply_prefix(): void
    {
        self::assertSame('Re: Hi', MessageSubject::reply('Hi'));
    }

    public function test_increments_plain_reply_prefix(): void
    {
        self::assertSame('Re(2): Hi', MessageSubject::reply('Re: Hi'));
    }

    public function test_increments_numbered_reply_prefix(): void
    {
        self::assertSame('Re(4): Hi', MessageSubject::reply('Re(3): Hi'));
    }

    public function test_adds_reply_prefix_to_empty_subject(): void
    {
        self::assertSame('Re: ', MessageSubject::reply(''));
    }
}
