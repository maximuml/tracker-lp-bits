<?php

declare(strict_types=1);

namespace App\Support\Config;

abstract class Config
{
    /** @param array<string, mixed> $data */
    public function __construct(protected readonly array $data = []) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    protected function raw(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    protected function int(string $key, int $default = 0): int
    {
        $value = $this->data[$key] ?? $default;
        return is_numeric($value) ? (int) $value : $default;
    }

    protected function float(string $key, float $default = 0.0): float
    {
        $value = $this->data[$key] ?? $default;
        return is_numeric($value) ? (float) $value : $default;
    }

    protected function string(string $key, string $default = ''): string
    {
        $value = $this->data[$key] ?? $default;
        return is_scalar($value) ? (string) $value : $default;
    }

    protected function bool(string $key, bool $default = false): bool
    {
        $value = $this->data[$key] ?? ($default ? 'yes' : 'no');
        return $value === 'yes' || $value === true || $value === 1 || $value === '1';
    }

    /**
     * @param array<int|string, mixed> $default
     * @return array<int|string, mixed>
     */
    protected function array(string $key, array $default = []): array
    {
        $value = $this->data[$key] ?? $default;
        return is_array($value) ? $value : $default;
    }

    /**
     * @param array<int|string, mixed> $default
     * @return array<int|string, mixed>|null
     */
    protected function nullableArray(string $key, ?array $default = null): ?array
    {
        $value = $this->data[$key] ?? $default;
        return is_array($value) ? $value : $default;
    }
}
