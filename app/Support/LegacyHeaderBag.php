<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Per-request header bag for the legacy bridge.
 *
 * Replaces the PHP SAPI globals {@see headers_list()},
 * {@see http_response_code()}, and {@see header_remove()} that leak
 * state across Octane worker requests.
 *
 * Legacy code that previously called `header('Location: ...')` or
 * `header('Content-Type: ...')` should now call
 * `LegacyHeaderBag::set()` / `LegacyHeaderBag::setStatusCode()`.
 * The legacy controller reads and clears the bag at the end of each
 * request via {@see LegacyHeaderBag::flush()}.
 *
 * The bag is bound as a singleton in the container and reset by
 * {@see ResetNexus} between requests, so it is safe under Octane.
 */
final class LegacyHeaderBag
{
    /** @var array<string, list<string>> */
    private array $headers = [];

    private ?int $statusCode = null;

    /**
     * Set a response header (replaces any existing value for the same name).
     *
     * @param  string  $name  Header name (e.g. "Location", "Content-Type").
     * @param  string  $value  Header value.
     */
    public function set(string $name, string $value): void
    {
        $this->headers[$this->normalizeName($name)] = [$value];
    }

    /**
     * Add a response header (appends to existing values for the same name).
     *
     * @param  string  $name  Header name.
     * @param  string  $value  Header value.
     */
    public function add(string $name, string $value): void
    {
        $key = $this->normalizeName($name);
        $this->headers[$key][] = $value;
    }

    /**
     * Remove a response header by name.
     *
     * @param  string  $name  Header name.
     */
    public function remove(string $name): void
    {
        unset($this->headers[$this->normalizeName($name)]);
    }

    /**
     * Check if a header exists.
     *
     * @param  string  $name  Header name.
     */
    public function has(string $name): bool
    {
        return isset($this->headers[$this->normalizeName($name)]);
    }

    /**
     * Get all values for a header name.
     *
     * @param  string  $name  Header name.
     * @return list<string> The header values, or empty list if not set.
     */
    public function get(string $name): array
    {
        return $this->headers[$this->normalizeName($name)] ?? [];
    }

    /**
     * Get the first value of a header, or null.
     *
     * @param  string  $name  Header name.
     */
    public function first(string $name): ?string
    {
        $values = $this->get($name);

        return $values[0] ?? null;
    }

    /**
     * Set the HTTP response status code.
     *
     * @param  int  $code  HTTP status code (100-599).
     */
    public function setStatusCode(int $code): void
    {
        $this->statusCode = $code;
    }

    /**
     * Get the HTTP response status code, or null if not set.
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * Get all headers as a flat array of "Name: Value" strings,
     * matching the format returned by PHP's {@see headers_list()}.
     *
     * @return list<string>
     */
    public function all(): array
    {
        $result = [];
        foreach ($this->headers as $name => $values) {
            foreach ($values as $value) {
                // Restore original case for common headers
                $displayName = $this->displayName($name);
                $result[] = $displayName.': '.$value;
            }
        }

        return $result;
    }

    /**
     * Get all headers as an associative array [name => value].
     *
     * If a header has multiple values, they are joined with ", ".
     *
     * @return array<string, string>
     */
    public function toResponseHeaders(): array
    {
        $result = [];
        foreach ($this->headers as $name => $values) {
            $displayName = $this->displayName($name);
            $result[$displayName] = implode(', ', $values);
        }

        return $result;
    }

    /**
     * Clear all headers and status code.
     *
     * Called by {@see ResetNexus} between requests under Octane.
     */
    public function flush(): void
    {
        $this->headers = [];
        $this->statusCode = null;
    }

    /**
     * Normalize header name to lowercase for case-insensitive lookup.
     */
    private function normalizeName(string $name): string
    {
        return strtolower($name);
    }

    /**
     * Restore common header names to their conventional casing.
     */
    private function displayName(string $normalized): string
    {
        return match ($normalized) {
            'location' => 'Location',
            'content-type' => 'Content-Type',
            'content-disposition' => 'Content-Disposition',
            'cache-control' => 'Cache-Control',
            'set-cookie' => 'Set-Cookie',
            'x-request-id' => 'X-Request-Id',
            'x-nexusphp-version' => 'X-Nexusphp-Version',
            default => $normalized,
        };
    }
}
