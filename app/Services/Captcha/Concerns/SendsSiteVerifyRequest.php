<?php

declare(strict_types=1);

namespace App\Services\Captcha\Concerns;

use App\Services\Captcha\Exceptions\CaptchaValidationException;
use Illuminate\Support\Facades\Http;

/**
 * Shared `siteverify` POST for remote captcha providers (reCAPTCHA,
 * Turnstile). Previously each driver carried an identical curl/stream
 * block; the Http facade gives one timeout'd, redirect-free request
 * that `Http::fake()` can intercept in tests.
 */
trait SendsSiteVerifyRequest
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sendVerificationRequest(string $url, array $data): array
    {
        try {
            $response = Http::asForm()
                ->timeout(10)
                ->withoutRedirecting()
                ->post($url, $data);
        } catch (\Throwable $e) {
            throw new CaptchaValidationException('Captcha verification request failed: '.$e->getMessage());
        }

        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new CaptchaValidationException('Unexpected captcha verification response.');
        }

        return $decoded;
    }
}
