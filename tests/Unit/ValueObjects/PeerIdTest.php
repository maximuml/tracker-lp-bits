<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\PeerId;
use PHPUnit\Framework\TestCase;

final class PeerIdTest extends TestCase
{
    private function validBinary(): string
    {
        return str_repeat("\xab", PeerId::LENGTH);
    }

    public function test_can_be_built_from_binary(): void
    {
        $binary = $this->validBinary();
        $peerId = PeerId::fromBinary($binary);

        $this->assertSame($binary, $peerId->toBinary());
        $this->assertSame(bin2hex($binary), $peerId->toHex());
    }

    public function test_can_be_built_from_hex(): void
    {
        $binary = $this->validBinary();
        $peerId = PeerId::fromHex(bin2hex($binary));

        $this->assertSame($binary, $peerId->toBinary());
    }

    public function test_to_printable_replaces_non_printable_bytes(): void
    {
        $binary = "\x00\x01UT3231-0123456789";
        // pad to 20 bytes
        $binary = substr($binary.str_repeat("\x00", PeerId::LENGTH), 0, PeerId::LENGTH);
        $peerId = PeerId::fromBinary($binary);

        $this->assertSame('..UT3231-0123456789.', substr($peerId->toPrintable(), 0, 21));
    }

    public function test_rejects_invalid_lengths(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PeerId::fromBinary('short');
    }

    public function test_try_from_binary_returns_null_on_invalid(): void
    {
        $this->assertNull(PeerId::tryFromBinary(null));
        $this->assertNull(PeerId::tryFromBinary(str_repeat('x', 21)));
    }

    public function test_equals(): void
    {
        $a = PeerId::fromBinary($this->validBinary());
        $b = PeerId::fromBinary($this->validBinary());
        $c = PeerId::fromBinary(str_repeat("\xcd", PeerId::LENGTH));

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function test_to_string_returns_hex(): void
    {
        $binary = $this->validBinary();
        $peerId = PeerId::fromBinary($binary);

        $this->assertSame(bin2hex($binary), (string) $peerId);
    }
}
