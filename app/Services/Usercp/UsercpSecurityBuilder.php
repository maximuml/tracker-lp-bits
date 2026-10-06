<?php

declare(strict_types=1);

namespace App\Services\Usercp;

use App\Enums\UserPrivacy;
use App\Repositories\UserPasskeyRepository;
use App\Support\AssetAppender;
use App\Support\Config\SiteConfig;
use App\Support\Html\SafeHtml;
use App\Support\TwoFactorAuthHelper;
use App\ViewModels\Usercp\PasskeyItem;
use App\ViewModels\Usercp\TwoStepState;
use App\ViewModels\Usercp\UsercpSecuritySection;

/**
 * Builds the usercp "security" settings section (with optional confirm step).
 */
final class UsercpSecurityBuilder
{
    public function __construct(
        private readonly UserPasskeyRepository $passkeyRepository
    ) {}

    /**
     * @param  array<string, mixed>  $curUser
     */
    public function build(array $curUser, string $type): UsercpSecuritySection
    {
        $showEmailChange = SiteConfig::current()->security->disableEmailChange(true)
            && SiteConfig::current()->smtp->type() !== 'none';

        // Two-step auth
        $hasSecret = ! empty($curUser['two_step_secret']);
        $secret = '';
        $qrCodeUrl = '';
        if (! $hasSecret) {
            $secret = TwoFactorAuthHelper::createSecret();
            $siteConfig = SiteConfig::current();
            $label = sprintf('%s(%s)', $siteConfig->basic->siteName(), (string) ($curUser['username'] ?? ''));
            $qrCodeUrl = TwoFactorAuthHelper::qrCodeUrl($label, $secret);
        }

        $currentPrivacy = UserPrivacy::tryFrom((int) ($curUser['privacy'] ?? 1)) ?? UserPrivacy::NORMAL;

        // For the confirm step, capture the posted values to re-render as hidden fields
        $confirmHidden = [];
        $isConfirm = $type === 'save';
        if ($isConfirm) {
            $confirmHidden = [
                'resetpasskey' => (string) (request()->post('resetpasskey') ?? ''),
                'resetauthkey' => (string) (request()->post('resetauthkey') ?? ''),
                'email' => trim((string) request()->post('email')),
                'chpassword' => (string) (request()->post('chpassword') ?? ''),
                'privacy' => (string) (request()->post('privacy') ?? ''),
                'two_step_secret' => (string) (request()->post('two_step_secret') ?? ''),
                'two_step_code' => (string) (request()->post('two_step_code') ?? ''),
            ];
        }

        // Saved message flags
        $savedFlags = [
            'mail' => request()->query('mail') === '1',
            'passkey' => request()->query('passkey') === '1',
            'password' => request()->query('password') === '1',
            'privacy' => request()->query('privacy') === '1',
        ];

        $savedMessage = '';

        if ($isConfirm) {
            AssetAppender::js('js/nx-crypto.js', 'footer', true, 'nx-crypto');
            AssetAppender::js('js/auth-form.js', 'footer', true, 'auth-form');
        } else {
            AssetAppender::js('js/auth-form.js', 'footer', true, 'auth-form');

            $savedMessage = (string) (__('legacy/usercp.text_saved'));
            if ($savedFlags['mail']) {
                $savedMessage .= ' '.(__('legacy/usercp.std_confirmation_email_sent'));
            }
            if ($savedFlags['passkey']) {
                $savedMessage .= ' '.(__('legacy/usercp.std_passkey_reset'));
            }
            if ($savedFlags['password']) {
                $savedMessage .= ' '.(__('legacy/usercp.std_password_changed'));
            }
            if ($savedFlags['privacy']) {
                $savedMessage .= ' '.(__('legacy/usercp.std_privacy_level_updated'));
            }
        }

        return new UsercpSecuritySection(
            type: $type,
            isConfirm: $isConfirm,
            confirmHidden: $confirmHidden,
            savedFlags: $savedFlags,
            savedMessage: $savedMessage,
            showEmailChange: $showEmailChange,
            twoStep: new TwoStepState($hasSecret, $secret, $qrCodeUrl),
            privacy: $currentPrivacy->stringValue(),
            email: (string) ($curUser['email'] ?? ''),
            passkeys: $isConfirm ? [] : $this->buildPasskeyItems((int) ($curUser['id'] ?? 0)),
            cspNonce: (string) request()->attributes->get('csp_nonce', ''),
            confirmHtml: SafeHtml::fromTrustedHtml(''),
        );
    }

    /**
     * Map the user's registered passkeys to view items with resolved
     * authenticator metadata (icon + display name) from the AAGUID list.
     *
     * @return list<PasskeyItem>
     */
    private function buildPasskeyItems(int $userId): array
    {
        AssetAppender::js('js/passkey.js', 'footer', true);

        $passkeys = $this->passkeyRepository->getList($userId);
        $aaguids = $passkeys->isEmpty() ? [] : (array) $this->passkeyRepository->getAaguids();

        $items = [];
        foreach ($passkeys as $passkey) {
            $meta = $aaguids[$passkey->getAaguidFormatted()] ?? null;
            $items[] = new PasskeyItem(
                credentialId: (string) $passkey->credential_id,
                iconUrl: isset($meta['icon_dark']) ? (string) $meta['icon_dark'] : UserPasskeyRepository::DEFAULT_ICON,
                iconAlt: isset($meta['name']) ? (string) $meta['name'] : (string) $passkey->credential_id,
                displayName: isset($meta['name']) ? (string) $meta['name'] : (string) $passkey->credential_id,
                showCredentialId: $meta !== null,
                createdAt: $passkey->created_at,
            );
        }

        return $items;
    }
}
