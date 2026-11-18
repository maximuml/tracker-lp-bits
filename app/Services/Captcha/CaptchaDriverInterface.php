<?php

declare(strict_types=1);

namespace App\Services\Captcha;

use App\Support\Html\SafeHtml;

interface CaptchaDriverInterface
{
    public function isEnabled(): bool;

    /**
     * Render the captcha markup for HTML forms.
     *
     * @param  array<string, mixed>  $context
     */
    public function render(array $context = []): SafeHtml;

    /**
     * Verify the captcha response.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    public function verify(array $payload, array $context = []): bool;
}
