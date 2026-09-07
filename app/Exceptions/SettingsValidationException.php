<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when settings values fail JSON schema validation.
 *
 * Carries the prefix and a list of human-readable validation error messages.
 * The {@see getStatusCode()} method returns 422 so Filament and API controllers
 * can render it as a "Unprocessable Entity" response.
 */
class SettingsValidationException extends \RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        private readonly string $prefix,
        private readonly array $errors,
    ) {
        parent::__construct(
            sprintf("Settings validation failed for prefix '%s': %s", $prefix, implode('; ', $errors)),
        );
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getStatusCode(): int
    {
        return 422;
    }
}
