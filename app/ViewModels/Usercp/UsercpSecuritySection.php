<?php

declare(strict_types=1);

namespace App\ViewModels\Usercp;

use App\Support\Html\SafeHtml;

/**
 * Security settings section of the user control panel.
 *
 * `isConfirm` selects the password-challenge step (`type=save` POST);
 * `confirmHidden` replays the submitted values as hidden inputs.
 * `confirmHtml` is the (currently empty) usercp_security_setting_form
 * hook payload kept for parity with the legacy page.
 */
final readonly class UsercpSecuritySection
{
    /**
     * @param  array<string, string>  $confirmHidden
     * @param  array<string, bool>  $savedFlags
     * @param  list<PasskeyItem>  $passkeys
     */
    public function __construct(
        public string $type,
        public bool $isConfirm,
        public array $confirmHidden,
        public array $savedFlags,
        public string $savedMessage,
        public bool $showEmailChange,
        public TwoStepState $twoStep,
        public string $privacy,
        public string $email,
        public array $passkeys,
        public string $cspNonce,
        public SafeHtml $confirmHtml,
    ) {}
}
