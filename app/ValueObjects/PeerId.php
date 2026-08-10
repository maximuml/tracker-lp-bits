<?php

declare(strict_types=1);

namespace App\ValueObjects;

/**
 * Value object representing a BitTorrent peer_id.
 *
 * The peer_id is exactly 20 raw bytes. This class centralises validation,
 * conversion to/from hex, and helper methods for display and comparison.
 */
final readonly class PeerId
{
    public const LENGTH = 20;

    private string $binary;

    public function __construct(string $binary)
    {
        if (strlen($binary) !== self::LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('PeerId must be exactly %d bytes, got %d', self::LENGTH, strlen($binary))
            );
        }

        $this->binary = $binary;
    }

    public static function fromBinary(string $binary): self
    {
        return new self($binary);
    }

    public static function fromHex(string $hex): self
    {
        $binary = @hex2bin($hex);
        if ($binary === false || strlen($binary) !== self::LENGTH) {
            throw new \InvalidArgumentException('Invalid peer_id hex string');
        }

        return new self($binary);
    }

    public static function tryFromBinary(?string $binary): ?self
    {
        if ($binary === null || strlen($binary) !== self::LENGTH) {
            return null;
        }

        return new self($binary);
    }

    public function toBinary(): string
    {
        return $this->binary;
    }

    public function toHex(): string
    {
        return bin2hex($this->binary);
    }

    public function toPrintable(): string
    {
        $escaped = '';
        foreach (str_split($this->binary) as $char) {
            $byte = ord($char);
            $escaped .= ($byte >= 32 && $byte < 127) ? $char : '.';
        }

        return $escaped;
    }

    public function equals(self $other): bool
    {
        return $this->binary === $other->binary;
    }

    public function __toString(): string
    {
        return $this->toHex();
    }
}
