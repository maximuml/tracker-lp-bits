<?php

namespace App\Support;

use App\Repositories\ToolRepository;

/**
 * Legacy mail-sending helper extracted from `include/functions.php`.
 *
 * Backs `sent_mail()`. It keeps the three transport modes (`default`,
 * `advanced`, `external`) and the legacy `stderr()` UI side effects.
 */
final class Mail
{
    /**
     * Send a legacy mail using the configured transport, reading the
     * site/smtp configuration from legacy globals.
     *
     * Backs the `sent_mail()` helper.
     */
    public static function sentLegacy(
        string $to,
        string $fromName,
        string $fromEmail,
        string $subject,
        string $body,
        string $type,
        bool $showMsg,
        bool $multiple,
        string $multipleMail,
        string $hdrEncoding,
    ): bool {
        $lang = SupportContext::getLangFunctions();
        $siteConfig = SupportContext::getSiteConfig();

        return self::sent(
            $to,
            $fromName,
            $fromEmail,
            $subject,
            $body,
            $type,
            $showMsg,
            $multiple,
            $multipleMail,
            $hdrEncoding,
            [
                'site_name' => (string) ($siteConfig['SITENAME'] ?? ''),
                'site_email' => (string) ($siteConfig['SITEEMAIL'] ?? ''),
                'smtp_type' => (string) ($siteConfig['smtptype'] ?? ''),
                'smtp' => (string) ($siteConfig['smtp'] ?? ''),
                'smtp_host' => (string) ($siteConfig['smtp_host'] ?? ''),
                'smtp_port' => (string) ($siteConfig['smtp_port'] ?? ''),
                'smtp_from' => (string) ($siteConfig['smtp_from'] ?? ''),
            ],
            [
                'error' => $lang['std_error'] ?? 'Error',
                'success' => $lang['std_success'] ?? 'Success',
                'unable_to_send_mail' => $lang['text_unable_to_send_mail'] ?? 'Unable to send mail',
                'confirmation_email_sent' => $lang['std_confirmation_email_sent'] ?? 'Confirmation email sent to ',
                'account_details_sent' => $lang['std_account_details_sent'] ?? 'Account details sent to ',
                'please_wait' => $lang['std_please_wait'] ?? 'Please wait...',
            ]
        );
    }

    /**
     * Send a legacy mail using the configured transport.
     *
     * Mirrors `sent_mail()`.
     */
    /**
     * @param  array<string, mixed>  $mailConfig
     * @param  array<string, string>  $labels
     */
    public static function sent(
        string $to,
        string $fromName,
        string $fromEmail,
        string $subject,
        string $body,
        string $type,
        bool $showMsg,
        bool $multiple,
        string $multipleMail,
        string $hdrEncoding,
        array $mailConfig,
        array $labels,
    ): bool {
        \do_log("to: $to, fromname: $fromName, fromemail: $fromEmail, subject: $subject, body: $body. type: $type");

        $siteName = $mailConfig['site_name'] ?? '';
        $siteEmail = $mailConfig['site_email'] ?? '';
        $smtpType = $mailConfig['smtp_type'] ?? 'none';
        $smtp = $mailConfig['smtp'] ?? '';
        $smtpHost = $mailConfig['smtp_host'] ?? '';
        $smtpPort = $mailConfig['smtp_port'] ?? '';
        $smtpFrom = $mailConfig['smtp_from'] ?? '';

        $eol = match (strtoupper(substr(PHP_OS, 0, 3))) {
            'WIN' => "\r\n",
            'MAC' => "\r",
            default => "\n",
        };
        $windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if ($smtpType === 'none' || $smtpType === '') {
            return false;
        }

        if ($smtpType === 'default') {
            @mail(
                $to,
                '=?'.$hdrEncoding.'?B?'.base64_encode($subject).'?=',
                $body,
                'From: '.$siteEmail.$eol.'Content-type: text/html; charset='.$hdrEncoding.$eol,
                "-f$siteEmail"
            ) or \stderr($labels['error'], $labels['unable_to_send_mail']);

            return true;
        }

        if ($smtpType === 'advanced') {
            $mid = md5(getip() . $fromName);
            $name = SupportContext::getServerValue('SERVER_NAME', $siteName);
            $headers = '';
            $headers .= "From: $fromName <$fromEmail>".$eol;
            $headers .= "Reply-To: $fromName <$fromEmail>".$eol;
            $headers .= "Return-Path: $fromName <$fromEmail>".$eol;
            $headers .= "Message-ID: <$mid thesystem@$name>".$eol;
            $headers .= "X-Mailer: PHP v".phpversion().$eol;
            $headers .= "MIME-Version: 1.0".$eol;
            $headers .= "Content-type: text/html; charset=".$hdrEncoding.$eol;
            $headers .= "X-Sender: PHP".$eol;

            if ($multiple) {
                $bccMultipleMail = '';
                foreach (explode(',', $multipleMail) as $toemail) {
                    $toemail = trim($toemail);
                    if ($toemail === '') {
                        continue;
                    }
                    $bccMultipleMail = $bccMultipleMail . ($bccMultipleMail !== '' ? ',' : '') . $toemail;
                }
                $headers .= "Bcc: $bccMultipleMail".$eol;
            }

            if ($smtp === 'yes') {
                ini_set('SMTP', $smtpHost);
                ini_set('smtp_port', $smtpPort);
                if ($windows) {
                    ini_set('sendmail_from', $smtpFrom);
                }
            }

            @mail(
                $to,
                '=?'.$hdrEncoding.'?B?'.base64_encode($subject).'?=',
                $body,
                $headers
            ) or \stderr($labels['error'], $labels['unable_to_send_mail']);

            ini_restore('SMTP');
            ini_restore('smtp_port');
            if ($windows) {
                ini_restore('sendmail_from');
            }

            return true;
        }

        if ($smtpType === 'external') {
            $toolRep = new ToolRepository();
            $sendResult = $toolRep->sendMail($to, $subject, $body);
            if ($sendResult === false) {
                \stderr($labels['error'], $labels['unable_to_send_mail']);
            }

            return true;
        }

        if ($showMsg) {
            if ($type === 'confirmation') {
                \stderr($labels['success'], $labels['confirmation_email_sent'] . '<b>'. htmlspecialchars($to) ."</b>.\n" . $labels['please_wait'], false);
            } elseif ($type === 'details') {
                \stderr($labels['success'], $labels['account_details_sent'] . '<b>'. htmlspecialchars($to) ."</b>.\n" . $labels['please_wait'], false);
            }
        }

        return true;
    }
}
