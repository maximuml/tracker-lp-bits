<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

/**
 * RFC 9457 Problem Details for HTTP APIs.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9457
 *
 * @implements Arrayable<string, mixed>
 */
final class ProblemDetails implements Arrayable
{
    /** @var array<string, mixed> */
    private array $extensions = [];

    /**
     * @param  array<string, mixed>  $extensions
     */
    public function __construct(
        private int $status,
        private string $title,
        private string $detail,
        private string $type = 'about:blank',
        private ?string $instance = null,
        array $extensions = [],
    ) {
        $this->extensions = $extensions;
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function validation(array $errors, ?string $instance = null): self
    {
        $firstError = (string) Arr::first(Arr::flatten($errors));

        return new self(
            status: 422,
            title: 'Validation Error',
            detail: $firstError !== '' ? $firstError : 'The given data was invalid.',
            type: 'https://datatracker.ietf.org/doc/html/rfc9110#section-15.5.21',
            instance: $instance,
            extensions: ['errors' => $errors],
        );
    }

    public static function unauthorized(string $detail, ?string $instance = null): self
    {
        return new self(
            status: 401,
            title: 'Unauthorized',
            detail: $detail !== '' ? $detail : 'Authentication is required.',
            type: 'https://datatracker.ietf.org/doc/html/rfc9110#section-15.5.2',
            instance: $instance,
        );
    }

    public static function forbidden(string $detail, ?string $instance = null): self
    {
        return new self(
            status: 403,
            title: 'Forbidden',
            detail: $detail !== '' ? $detail : 'You do not have permission to access this resource.',
            type: 'https://datatracker.ietf.org/doc/html/rfc9110#section-15.5.4',
            instance: $instance,
        );
    }

    public static function notFound(string $detail, ?string $instance = null): self
    {
        return new self(
            status: 404,
            title: 'Not Found',
            detail: $detail !== '' ? $detail : 'The requested resource was not found.',
            type: 'https://datatracker.ietf.org/doc/html/rfc9110#section-15.5.5',
            instance: $instance,
        );
    }

    public static function serverError(string $detail, ?string $instance = null): self
    {
        return new self(
            status: 500,
            title: 'Internal Server Error',
            detail: $detail !== '' ? $detail : 'An unexpected error occurred.',
            type: 'https://datatracker.ietf.org/doc/html/rfc9110#section-15.6.1',
            instance: $instance,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
        ];

        if ($this->instance !== null) {
            $data['instance'] = $this->instance;
        }

        return array_merge($data, $this->extensions);
    }

    public function toResponse(): JsonResponse
    {
        return new JsonResponse(
            $this->toArray(),
            $this->status,
            ['Content-Type' => 'application/problem+json'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );
    }
}
