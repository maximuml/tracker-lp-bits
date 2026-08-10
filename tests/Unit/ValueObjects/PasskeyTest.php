<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\Passkey;
use PHPUnit\Framework\TestCase;

final class PasskeyTest extends TestCase
{
    public function test_can_be_built_from_valid_string(): void
    {
        $value = str_repeat('a', Passkey::LENGTH);
        $passkey = Passkey::fromString($value);

        $this->assertSame($value, $passkey->toString());
    }

    public function test_rejects_invalid_lengths(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Passkey::fromString('too-short');
    }

    public function test_try_from_string_returns_null_on_invalid(): void
    {
        $this->assertNull(Passkey::tryFromString(null));
        $this->assertNull(Passkey::tryFromString('short'));
        $this->assertNull(Passkey::tryFromString(str_repeat('x', Passkey::LENGTH + 1)));
    }

    public function test_equals(): void
    {
        $a = Passkey::fromString(str_repeat('a', Passkey::LENGTH));
        $b = Passkey::fromString(str_repeat('a', Passkey::LENGTH));
        $c = Passkey::fromString(str_repeat('b', Passkey::LENGTH));

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function test_to_string_returns_value(): void
    {
        $value = str_repeat('a', Passkey::LENGTH);
        $passkey = Passkey::fromString($value);

        $this->assertSame($value, (string) $passkey);
    }
}
