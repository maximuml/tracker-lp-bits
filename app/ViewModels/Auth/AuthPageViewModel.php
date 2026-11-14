<?php

declare(strict_types=1);

namespace App\ViewModels\Auth;

use App\Support\Html\SafeHtml;
use App\ViewModels\ViewModel;
use Illuminate\Support\ViewErrorBag;

/**
 * ViewModel for the public auth pages (login, signup, recover, confirm_resend).
 *
 * They share the same chrome data (languages, captcha, attempt counters,
 * site name); each page adds its own optional extras. Built in the Auth
 * controllers and handed to the view as `vm`.
 */
final class AuthPageViewModel extends ViewModel
{
    /**
     * @param  array<int, array<string, mixed>>  $languages
     * @param  array<int, mixed>  $countries
     * @param  mixed  $invite
     */
    public function __construct(
        // Shared auth chrome
        public readonly string $langFolder,
        public readonly array $languages,
        public readonly bool $captchaEnabled,
        public readonly SafeHtml|string $captchaMarkup,
        public readonly int $remaining,
        public readonly int $maxAttempts,
        public readonly ?string $error,
        public readonly string $siteName,
        public readonly ?ViewErrorBag $errors = null,
        // Shared by login/signup/confirm_resend
        public readonly string $secret = '',
        // login
        public readonly string $returnto = '',
        public readonly bool $showWarn = false,
        public readonly bool $nowarn = false,
        public readonly bool $isComplainEnabled = false,
        public readonly bool $isSmtpEnabled = false,
        // recover
        public readonly ?int $resetUserId = null,
        public readonly ?string $resetToken = null,
        public readonly ?string $resetError = null,
        public readonly string $status = '',
        // signup
        public readonly string $type = '',
        public readonly bool $isInvite = false,
        public readonly mixed $invite = null,
        public readonly string $code = '',
        public readonly array $countries = [],
        public readonly string $headTitle = '',
        public readonly string $usernameValue = '',
        public readonly bool $usernameReadonly = false,
        public readonly string $emailValue = '',
        public readonly bool $emailReadonly = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
