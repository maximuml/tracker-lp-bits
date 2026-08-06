<?php

namespace App\Support;

use App\Models\Setting;
use App\Repositories\ToolRepository;
use Illuminate\Support\Facades\Request;

/**
 * Legacy mail-sending helper extracted from `include/functions.php`.
 *
 * Backs `sent_mail()`. It keeps the three transport modes (`default`,
 * `advanced`, `external`) and no longer calls `stderr()` from inside the
 * mailer; failures are returned as `false` and the caller decides how to
 * report them. Site/SMTP configuration is read directly from `Setting`.
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
        return self::sent(
            $to,
            $fromName,
            $fromEmail,
            $subject,
            $body,
            $type,
            $multiple,
            $multipleMail,
            $hdrEncoding,
            [
                'site_name' => (string) Setting::get('basic.SITENAME', ''),
                'site_email' => (string) Setting::get('main.SITEEMAIL', ''),
                'smtp_type' => (string) Setting::get('smtp.smtptype', 'none'),
                'smtp' => (string) Setting::get('smtp.smtp', ''),
                'smtp_host' => (string) Setting::get('smtp.smtp_host', ''),
                'smtp_port' => (string) Setting::get('smtp.smtp_port', ''),
                'smtp_from' => (string) Setting::get('smtp.smtp_from', ''),
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
     */
    public static function sent(
        string $to,
        string $fromName,
        string $fromEmail,
        string $subject,
        string $body,
        string $type,
        bool $multiple,
        string $multipleMail,
        string $hdrEncoding,
        array $mailConfig,
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
            return (bool) @mail(
                $to,
                '=?'.$hdrEncoding.'?B?'.base64_encode($subject).'?=',
                $body,
                'From: '.$siteEmail.$eol.'Content-type: text/html; charset='.$hdrEncoding.$eol,
                "-f$siteEmail"
            );
        }

        if ($smtpType === 'advanced') {
            $mid = md5(Network::clientIp() . $fromName);
            $name = (string) Request::server('SERVER_NAME', $siteName);
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

            $result = (bool) @mail(
                $to,
                '=?'.$hdrEncoding.'?B?'.base64_encode($subject).'?=',
                $body,
                $headers
            );

            ini_restore('SMTP');
            ini_restore('smtp_port');
            if ($windows) {
                ini_restore('sendmail_from');
            }

            return $result;
        }

        if ($smtpType === 'external') {
            $toolRep = new ToolRepository();

            return (bool) $toolRep->sendMail($to, $subject, $body);
        }

        return false;
    }
}
