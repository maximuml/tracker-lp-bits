<?php

namespace App\Services\Captcha;

interface CaptchaDriverInterface
{
    public function isEnabled(): bool;

    /**
     * Render the captcha markup for HTML forms.
     *
     * @param  array<string, mixed>  $context
     */
    public function render(array $context = []): string;

    /**
     * Verify the captcha response.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    public function verify(array $payload, array $context = []): bool;
}
