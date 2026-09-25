<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\Config\SiteConfig;
use App\Support\Logger;
use App\Support\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers a legacy mail (sent_mail() transport modes) on the mail queue so
 * web requests never block on SMTP timeouts.
 *
 * A false return from Mail::sent() (transport disabled or delivery refused)
 * is surfaced as a job failure so Horizon retries and, ultimately, records
 * the message in failed_jobs instead of silently dropping it.
 */
class SendLegacyMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 60;

    public function __construct(
        public readonly string $to,
        public readonly string $fromName,
        public readonly string $fromEmail,
        public readonly string $subject,
        public readonly string $body,
        public readonly string $type,
        public readonly bool $multiple,
        public readonly string $multipleMail,
        public readonly string $hdrEncoding,
    ) {
        $this->onQueue('mail');
    }

    public function handle(): void
    {
        // Mail disabled site-wide: drop quietly instead of retrying into
        // failed_jobs — the admin turned delivery off on purpose.
        $smtpType = (string) SiteConfig::current()->smtp->type('none');
        if ($smtpType === 'none' || $smtpType === '') {
            Logger::writeWithContext(
                sprintf('dropped queued mail (smtp disabled): type=%s to=%s', $this->type, $this->to)
            );

            return;
        }

        $sent = Mail::sentLegacy(
            $this->to,
            $this->fromName,
            $this->fromEmail,
            $this->subject,
            $this->body,
            $this->type,
            false,
            $this->multiple,
            $this->multipleMail,
            $this->hdrEncoding,
        );

        if (! $sent) {
            throw new \RuntimeException(
                sprintf('Legacy mail send failed: type=%s to=%s', $this->type, $this->to)
            );
        }
    }
}
