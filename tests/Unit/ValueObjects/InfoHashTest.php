<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\InfoHash;
use PHPUnit\Framework\TestCase;

final class InfoHashTest extends TestCase
{
    private function validBinary(): string
    {
        return str_repeat("\xab", InfoHash::LENGTH);
    }

    public function test_can_be_built_from_binary(): void
    {
        $binary = $this->validBinary();
        $hash = InfoHash::fromBinary($binary);

        $this->assertSame($binary, $hash->toBinary());
        $this->assertSame(bin2hex($binary), $hash->toHex());
    }

    public function test_can_be_built_from_hex(): void
    {
        $binary = $this->validBinary();
        $hash = InfoHash::fromHex(bin2hex($binary));

        $this->assertSame($binary, $hash->toBinary());
    }

    public function test_rejects_invalid_lengths(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InfoHash::fromBinary(str_repeat('x', 19));
    }

    public function test_try_from_binary_returns_null_on_invalid(): void
    {
        $this->assertNull(InfoHash::tryFromBinary(null));
        $this->assertNull(InfoHash::tryFromBinary('too short'));
        $this->assertNull(InfoHash::tryFromBinary(str_repeat('x', 21)));
    }

    public function test_fingerprint_is_sha1_of_binary(): void
    {
        $binary = $this->validBinary();
        $hash = InfoHash::fromBinary($binary);

        $this->assertSame(sha1($binary), $hash->fingerprint());
        $this->assertSame(40, strlen($hash->fingerprint()));
    }

    public function test_equals(): void
    {
        $a = InfoHash::fromBinary($this->validBinary());
        $b = InfoHash::fromBinary($this->validBinary());
        $c = InfoHash::fromBinary(str_repeat("\xcd", InfoHash::LENGTH));

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function test_to_string_returns_hex(): void
    {
        $binary = $this->validBinary();
        $hash = InfoHash::fromBinary($binary);

        $this->assertSame(bin2hex($binary), (string) $hash);
    }
}
