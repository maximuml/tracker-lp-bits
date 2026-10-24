<?php

declare(strict_types=1);

namespace App\Services\Captcha\Drivers;

use App\Services\Captcha\CaptchaDriverInterface;
use App\Services\Captcha\Concerns\SendsSiteVerifyRequest;
use App\Services\Captcha\Exceptions\CaptchaValidationException;

class TurnstileCaptchaDriver implements CaptchaDriverInterface
{
    use SendsSiteVerifyRequest;

    protected static bool $scriptInjected = false;

    /**
     * Reset per-request memoized state for long-lived workers (Octane).
     */
    public static function resetState(): void
    {
        self::$scriptInjected = false;
    }

    /** @var array<string, mixed> */
    protected array $config;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function isEnabled(): bool
    {
        return ! empty($this->config['site_key']) && ! empty($this->config['secret_key']);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function render(array $context = []): string
    {
        if (! $this->isEnabled()) {
            return '';
        }

        $labels = $context['labels'] ?? [];
        $label = $labels['image'] ?? $labels['code'] ?? 'Security Check';
        $theme = $this->config['theme'] ?? 'light';
        $size = $this->config['size'] ?? 'auto';
        if (is_string($size)) {
            $size = strtolower($size);
        }
        $validSizes = ['compact', 'normal', 'flexible'];
        if (! in_array($size, $validSizes, true)) {
            $size = 'auto';
        }

        $markup = view('components.captcha.turnstile', [
            'grid' => ($context['layout'] ?? '') === 'grid',
            'label' => $label,
            'siteKey' => (string) $this->config['site_key'],
            'theme' => (string) $theme,
            'size' => $size === 'auto' ? null : $size,
            'injectScript' => ! self::$scriptInjected,
        ])->render();

        self::$scriptInjected = true;

        return $markup;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    public function verify(array $payload, array $context = []): bool
    {
        $requestPayload = is_array($payload['request'] ?? null) ? $payload['request'] : [];
        $token = trim((string) ($requestPayload['cf-turnstile-response'] ?? ''));

        if ($token === '') {
            throw new CaptchaValidationException('Captcha verification token is missing.');
        }

        $secret = (string) ($this->config['secret_key'] ?? '');

        if ($secret === '') {
            throw new CaptchaValidationException('Captcha secret key is not configured.');
        }

        $data = [
            'secret' => $secret,
            'response' => $token,
        ];

        $remoteIp = $context['ip'] ?? null;

        if (! empty($remoteIp)) {
            $data['remoteip'] = $remoteIp;
        }

        $result = $this->sendVerificationRequest('https://challenges.cloudflare.com/turnstile/v0/siteverify', $data);

        if (! ($result['success'] ?? false)) {
            throw new CaptchaValidationException('Captcha verification failed.');
        }

        return true;
    }
}
