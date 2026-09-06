<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserClass as UserClassEnum;
use App\Http\Middleware\Locale;
use App\Models\Invite;
use App\Models\Message;
use App\Models\News;
use App\Models\Poll;
use App\Models\PollAnswer;
use App\Models\User;
use App\Repositories\AttendanceRepository;
use App\Support\Config\SiteConfig;
use App\Support\Locale as SupportLocale;
use App\Support\Logger;
use App\Support\UserDisplay;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class ToolMaintenanceService
{
    /**
     * @param  mixed  $to
     * @param  mixed  $subject
     * @param  mixed  $body
     * @param  mixed  $exception
     */
    public function sendMail($to, $subject, $body, $exception = false): bool
    {
        $log = '[SEND_MAIL]';
        $factory = new EsmtpTransportFactory;
        $smtpConfig = SiteConfig::fromDb()->smtp;
        Logger::writeWithContext((string) ("{$log}, to: {$to}, subject: {$subject}, smtp_host: {$smtpConfig->address()}:{$smtpConfig->port()}, encryption: ".($smtpConfig->encryption() ?? 'none')), (string) 'info', (bool) false);
        $encryption = $smtpConfig->encryption();
        if ($encryption !== null && ! in_array($encryption, ['ssl', 'tls'])) {
            $encryption = null;
        }
        $smtpPort = $smtpConfig->port();
        $smtpAddress = $smtpConfig->address();
        $accountName = $smtpConfig->accountName() ?: null;
        $accountPassword = $smtpConfig->accountPassword() ?: null;
        $port = $smtpPort !== '' ? (int) $smtpPort : null;
        // Create the Transport
        $transport = $factory->create(new Dsn(
            $port === 465 && $encryption !== null ? 'smtps' : 'smtp',
            $smtpAddress,
            $accountName,
            $accountPassword,
            $port,
            ['verify_peer' => (bool) config('mail.verify_peer', true), 'verify_peer_name' => (bool) config('mail.verify_peer', true), 'allow_self_signed' => false]
        ));

        // Create the Mailer using your created Transport
        $mailer = new Mailer($transport);

        // Create a message
        $message = (new Email)
            ->from(new Address(SiteConfig::current()->main->siteEmail(), SiteConfig::current()->basic->siteName()))
            ->to($to)
            ->subject($subject)
            ->text($body)
            ->html(nl2br($body));

        // Send the message
        try {
            $mailer->send($message);

            return true;
        } catch (\Throwable $e) {
            Logger::writeWithContext((string) ("{$log}, fail: ".$e->getMessage()."\n".$e->getTraceAsString()), (string) 'error', (bool) false);
            if ($exception) {
                throw $e;
            } else {
                return false;
            }
        }
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getNotificationCount(User $user): array
    {
        $result = [];
        // attend or not
        $attendRep = app(AttendanceRepository::class);
        $attendance = $attendRep->getAttendance($user->id, date('Ymd'));
        $result['attendance'] = $attendance ? 0 : 1;

        // unread news
        $count = News::query()->where('added', '>', $user->last_home ?? '1970-01-01 00:00:00')->count();
        $result['news'] = $count;

        // unread messages
        $count = Message::query()->where('receiver', $user->id)->where('unread', true)->count();
        $result['message'] = $count;

        // un-vote poll
        $total = Poll::query()->count();
        $userVoteRow = PollAnswer::query()->where('userid', $user->id)->selectRaw('count(distinct(pollid)) as counts')->first();
        $result['poll'] = $total - ($userVoteRow === null ? 0 : (int) $userVoteRow->counts);

        return $result;
    }

    /**
     * @param  mixed  $class
     * @return array<int|string, mixed>
     */
    public function listUserClassPermissions($class): array
    {
        $settings = SiteConfig::current()->authority->toArray();
        $result = [];
        foreach ($settings as $permission => $minClass) {
            if ($minClass >= UserClassEnum::PEASANT->value && $minClass <= $class) {
                $result[] = $permission;
            }
        }

        return $result;
    }

    /**
     * @param  mixed  $uid
     * @return array<int|string, mixed>
     */
    public function listUserAllPermissions($uid): array
    {
        static $uidPermissionsCached = [];
        if (isset($uidPermissionsCached[$uid])) {
            return $uidPermissionsCached[$uid];
        }
        $log = "uid: $uid";
        $userInfo = UserDisplay::row($uid);
        if (! is_array($userInfo)) {
            Logger::writeWithContext((string) "{$log}, user not found", (string) 'warn', (bool) false);

            return [];
        }
        $class = $userInfo['class'];

        // Class permission
        $classPermissions = $this->listUserClassPermissions($class);

        // Role permission
        $rolePermissions = [];

        // Direct permission
        $directPermissions = [];

        $allPermissions = array_merge($classPermissions, $rolePermissions, $directPermissions);
        Logger::writeWithContext((string) ("{$log}, allPermissions: ".json_encode($allPermissions)), (string) 'info', (bool) false);
        $result = array_combine($allPermissions, $allPermissions);
        $uidPermissionsCached[$uid] = $result;

        return $result;
    }

    /**
     * @param  array<int|string, mixed>  $hashArr
     * @return array<int|string, mixed>
     */
    public function generateUniqueInviteHash(array $hashArr, int $total, int $left, int $deep = 0): array
    {
        Logger::writeWithContext((string) "total: {$total}, left: {$left}, deep: {$deep}", (string) 'info', (bool) false);
        if ($deep > 10) {
            throw new \RuntimeException("deep: $deep > 10");
        }
        if (count($hashArr) >= $total) {
            return array_slice(array_values($hashArr), 0, $total);
        }
        for ($i = 0; $i < $left; $i++) {
            $hash = Str::random(32);
            $hashArr[$hash] = $hash;
        }
        $exists = Invite::query()->whereIn('hash', array_values($hashArr))->get(['id', 'hash']);
        foreach ($exists as $value) {
            unset($hashArr[$value->hash]);
        }

        return $this->generateUniqueInviteHash($hashArr, $total, $total - count($hashArr), ++$deep);

    }

    /**
     * @param  array<int|string, mixed>  $subjectTransContext
     * @param  array<int|string, mixed>  $msgTransContext
     */
    public function sendAlarmEmail(string $subjectTransKey, array $subjectTransContext, string $msgTransKey, array $msgTransContext): void
    {
        /** @var array<string, mixed> $subjectContext */
        $subjectContext = $subjectTransContext;
        /** @var array<string, mixed> $msgContext */
        $msgContext = $msgTransContext;
        $receiverUid = SiteConfig::current()->system->alarmEmailReceiver();
        if (empty($receiverUid)) {
            $locale = Locale::getDefault();
            $subject = SupportLocale::trans($subjectTransKey, $subjectContext, $locale);
            $msg = SupportLocale::trans($msgTransKey, $msgContext, $locale);
            Logger::writeWithContext((string) sprintf('%s - %s', $subject, $msg), (string) 'error', (bool) false);
        } else {
            $receiverUidArr = preg_split("/[\r\n\s,，]+/", $receiverUid);
            $users = User::query()->whereIn('id', $receiverUidArr)->get(User::$commonFields);
            foreach ($users as $user) {
                $locale = $user->locale;
                $subject = SupportLocale::trans($subjectTransKey, $subjectContext, $locale);
                $msg = SupportLocale::trans($msgTransKey, $msgContext, $locale);
                $result = $this->sendMail($user->email, $subject, $msg);
                Logger::writeWithContext((string) sprintf('send msg: %s result: %s', $msg, var_export($result, true)), (string) ($result ? 'info' : 'error'), (bool) false);
            }
        }
    }
}
