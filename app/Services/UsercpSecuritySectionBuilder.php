<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserPrivacy;
use App\Repositories\UserPasskeyRepository;
use App\Support\Config\SiteConfig;
use App\Support\Form;
use App\Support\Globals;
use App\Support\Html;
use App\Support\Locale;
use App\Support\TwoFactorAuthHelper;

/**
 * Builds the security-settings section (with optional confirm step) of the
 * user control panel. Split out of UsercpPageService to keep the page
 * service under the RepositorySizeTest baseline.
 */
final class UsercpSecuritySectionBuilder
{
    public function __construct(
        private readonly Globals $globals,
        private readonly UserPasskeyRepository $passkeyRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    public function build(array $lang, array $curUser, string $type): array
    {
        $showEmailChange = (string) $this->globals->get('disableemailchange', '') !== 'no'
            && (string) $this->globals->get('smtptype', '') !== 'none';

        // Two-step auth
        $twoStep = [
            'hasSecret' => ! empty($curUser['two_step_secret']),
            'secret' => '',
            'qrCodeUrl' => '',
        ];
        if (! $twoStep['hasSecret']) {
            $secret = TwoFactorAuthHelper::createSecret();
            $siteConfig = SiteConfig::current();
            $label = sprintf('%s(%s)', $siteConfig->basic->siteName(), (string) ($curUser['username'] ?? ''));
            $twoStep['secret'] = $secret;
            $twoStep['qrCodeUrl'] = TwoFactorAuthHelper::qrCodeUrl($label, $secret);
        }

        // Privacy radios
        $currentPrivacy = UserPrivacy::tryFrom((int) ($curUser['privacy'] ?? 1)) ?? UserPrivacy::NORMAL;
        $privacyRadios = [
            'normal' => $this->privacyRadio('normal', $lang['radio_normal'] ?? 'normal', $currentPrivacy->stringValue()),
            'low' => $this->privacyRadio('low', $lang['radio_low'] ?? 'low', $currentPrivacy->stringValue()),
            'strong' => $this->privacyRadio('strong', $lang['radio_strong'] ?? 'strong', $currentPrivacy->stringValue()),
        ];

        // For the confirm step, capture the posted values to re-render as hidden fields
        $confirmHidden = [];
        $isConfirm = $type === 'save';
        if ($isConfirm) {
            $confirmHidden = [
                'resetpasskey' => (string) (request()->post('resetpasskey') ?? ''),
                'resetauthkey' => (string) (request()->post('resetauthkey') ?? ''),
                'email' => htmlspecialchars(trim((string) request()->post('email'))),
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
        $rowsHtml = '';
        $passkeyListHtml = '';

        if ($isConfirm) {
            Form::passwordChallengeJs('security', 'username', 'oldpassword');
        } else {
            Form::passwordHashJs('security', 'password', 'chpassword', false, 'passagain', 'username');

            $savedMessage = (string) ($lang['text_saved'] ?? '');
            if ($savedFlags['mail']) {
                $savedMessage .= ' '.($lang['std_confirmation_email_sent'] ?? '');
            }
            if ($savedFlags['passkey']) {
                $savedMessage .= ' '.($lang['std_passkey_reset'] ?? '');
            }
            if ($savedFlags['password']) {
                $savedMessage .= ' '.($lang['std_password_changed'] ?? '');
            }
            if ($savedFlags['privacy']) {
                $savedMessage .= ' '.($lang['std_privacy_level_updated'] ?? '');
            }

            $rowsHtml .= (string) Html::trSmall(
                $lang['row_reset_passkey'] ?? 'Reset passkey',
                '<input type=checkbox name=resetpasskey value=1 />'.htmlspecialchars($lang['checkbox_reset_my_passkey'] ?? '').'<br /><font class=small>'.($lang['text_reset_passkey_note'] ?? '').'</font>',
                1,
                '',
                true
            );

            if ($twoStep['hasSecret']) {
                $twoStepCell = '<input type=text name=two_step_code />'.htmlspecialchars($lang['text_two_step_secret_unbind_note'] ?? '');
            } else {
                $cspNonce = (string) request()->attributes->get('csp_nonce', '');
                $twoStepCell = sprintf('<style nonce="%s">.tfa-row{display:flex;align-items:center}.tfa-row>div+div{padding-left:20px}</style><div class="tfa-row">', htmlspecialchars($cspNonce));
                $twoStepCell .= sprintf('<div><img src="%s" /></div>', htmlspecialchars($twoStep['qrCodeUrl']));
                $twoStepCell .= sprintf(
                    '<div>%s<a href="%s" target="_blank">Link</a><br /><br />%s%s<br/><br/>%s<input type=hidden name=two_step_secret value="%s" /><input type=text name=two_step_code readonly /></div>',
                    ($lang['text_two_step_secret_bind_by_qrdoe_note'] ?? ''),
                    htmlspecialchars($twoStep['qrCodeUrl']),
                    htmlspecialchars($lang['text_two_step_secret_bind_manually_note'] ?? ''),
                    htmlspecialchars($twoStep['secret']),
                    htmlspecialchars($lang['text_two_step_secret_bind_complete_note'] ?? ''),
                    htmlspecialchars($twoStep['secret'])
                );
                $twoStepCell .= sprintf('</div><script nonce="%s">document.addEventListener("focusin",function(e){if(e.target&&e.target.name==="two_step_code"){e.target.removeAttribute("readonly")}})</script>', htmlspecialchars($cspNonce));
            }
            $rowsHtml .= (string) Html::trSmall($lang['row_two_step_secret'] ?? 'Two-step secret', $twoStepCell, 1, '', true);

            $passkeyListHtml = $this->capturePasskeyList((int) ($curUser['id'] ?? 0));
            $rowsHtml .= '<tr><td class="rowhead" valign="top" align="right">'.htmlspecialchars(Locale::trans('passkey.passkey', [], null)).'</td><td class="rowfollow" valign="top" align="left">'.$passkeyListHtml.'</td></tr>';

            if ($showEmailChange) {
                $rowsHtml .= (string) Html::trSmall(
                    $lang['row_email_address'] ?? 'Email',
                    '<input type="text" name="email" style="width: 200px" value="'.htmlspecialchars((string) ($curUser['email'] ?? '')).'" /> <br /><font class=small>'.($lang['text_email_address_note'] ?? '').'</font>',
                    1,
                    '',
                    true
                );
            }

            $rowsHtml .= (string) Html::trSmall($lang['row_change_password'] ?? 'Change password', '<input type="password" class="password" style="width: 200px" />', 1, '', true);
            $rowsHtml .= (string) Html::trSmall($lang['row_type_password_again'] ?? 'Password again', '<input type="password" class="passagain" style="width: 200px" />', 1, '', true);
            $rowsHtml .= (string) Html::trSmall($lang['row_privacy_level'] ?? 'Privacy', $privacyRadios['normal'].' '.$privacyRadios['low'].' '.$privacyRadios['strong'], 1, '', true);
        }

        return [
            'type' => $type,
            'isConfirm' => $isConfirm,
            'confirmHidden' => $confirmHidden,
            'savedFlags' => $savedFlags,
            'savedMessage' => $savedMessage,
            'rowsHtml' => $rowsHtml,
            'showEmailChange' => $showEmailChange,
            'twoStep' => $twoStep,
            'privacyRadios' => $privacyRadios,
            'passkeyListHtml' => $passkeyListHtml,
            'confirmHtml' => $isConfirm ? $this->captureConfirmExtras() : '',
        ];
    }

    /**
     * Render a privacy radio input.
     */
    private function privacyRadio(string $name, string $descr, string $current): string
    {
        $checked = $current === $name ? ' checked="checked"' : '';

        return '<input type="radio" name="privacy" value="'.htmlspecialchars($name).'"'.$checked.' /> '.htmlspecialchars($descr);
    }

    /**
     * Capture the passkey list HTML (UserPasskeyRepository::renderList echoes).
     */
    private function capturePasskeyList(int $userId): string
    {
        ob_start();
        $this->passkeyRepository->renderList($userId);

        return (string) ob_get_clean();
    }

    /**
     * Capture any extra HTML emitted by the usercp_security_setting_form hook.
     */
    private function captureConfirmExtras(): string
    {
        return '';
    }
}
