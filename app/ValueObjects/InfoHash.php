<?php

declare(strict_types=1);

namespace App\ValueObjects;

/**
 * Value object representing a BitTorrent info_hash.
 *
 * The info_hash is exactly 20 raw bytes. This class centralises validation,
 * conversion to/from hex, and helper methods for cache/lock fingerprints.
 */
final readonly class InfoHash
{
    public const LENGTH = 20;

    private string $binary;

    public function __construct(string $binary)
    {
        if (strlen($binary) !== self::LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('InfoHash must be exactly %d bytes, got %d', self::LENGTH, strlen($binary))
            );
        }

        $this->binary = $binary;
    }

    public static function fromBinary(string $binary): self
    {
        return new self($binary);
    }

    /**
     * Build an InfoHash from a 40-character hexadecimal representation.
     */
    public static function fromHex(string $hex): self
    {
        $binary = @hex2bin($hex);
        if ($binary === false || strlen($binary) !== self::LENGTH) {
            throw new \InvalidArgumentException('Invalid info_hash hex string');
        }

        return new self($binary);
    }

    /**
     * Try to build from raw bytes; return null on failure instead of throwing.
     */
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

    /**
     * Sha1 fingerprint, useful for cache/lock keys that need a URL-safe string.
     */
    public function fingerprint(): string
    {
        return sha1($this->binary);
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
