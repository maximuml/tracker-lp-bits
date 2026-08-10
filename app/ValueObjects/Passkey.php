<?php

declare(strict_types=1);

namespace App\ValueObjects;

/**
 * Value object representing a tracker passkey.
 *
 * Passkeys are 32-character alphanumeric strings used to authenticate
 * a user to the tracker announce/scrape endpoints and .torrent downloads.
 */
final readonly class Passkey
{
    public const LENGTH = 32;

    private string $value;

    public function __construct(string $value)
    {
        if (strlen($value) !== self::LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Passkey must be exactly %d characters, got %d', self::LENGTH, strlen($value))
            );
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null || strlen($value) !== self::LENGTH) {
            return null;
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
