<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\LoginLog;
use App\Models\User;
use App\Repositories\ToolRepository;
use App\Support\Config\SiteConfig;
use App\Support\Locale;
use App\Support\Logger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendLoginNotify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $thisLoginLogId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $thisLoginLogId)
    {
        $this->thisLoginLogId = $thisLoginLogId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        /** @var LoginLog $thisLoginLog */
        $thisLoginLog = LoginLog::query()->where('id', $this->thisLoginLogId)->firstOrFail();
        $log = 'handling login log: '.$thisLoginLog->toJson();
        if (! $thisLoginLog->country || ! $thisLoginLog->city) {
            Logger::writeWithContext((string) "{$log}, this login log no country or city", (string) 'info', (bool) false);

            return;
        }
        $lastLoginLog = LoginLog::query()
            ->where('uid', $thisLoginLog->uid)
            ->where('id', '<', $thisLoginLog->id)
            ->orderBy('id', 'desc')
            ->first();
        if (! $lastLoginLog) {
            Logger::writeWithContext((string) "{$log}, no last login log", (string) 'info', (bool) false);

            return;
        }
        $log .= sprintf(', last login: %s', $lastLoginLog->toJson());
        if (! $lastLoginLog->country || ! $lastLoginLog->city) {
            Logger::writeWithContext((string) "{$log}, last login log no country or city", (string) 'info', (bool) false);

            return;
        }
        if ($thisLoginLog->country == $lastLoginLog->country && $thisLoginLog->city == $lastLoginLog->city) {
            Logger::writeWithContext((string) "{$log}, country and city are equals", (string) 'info', (bool) false);

            return;
        }
        /** @var User $user */
        $user = User::query()->where('id', $thisLoginLog->uid)->firstOrFail(User::$commonFields);
        $locale = $user->locale;
        $toolRep = new ToolRepository;
        $subject = Locale::trans('message.login_notify.subject', ['site_name' => SiteConfig::current()->basic->siteName()], $locale);
        $body = Locale::trans('message.login_notify.body', ['this_login_time' => $thisLoginLog->created_at, 'this_ip' => $thisLoginLog->ip, 'this_location' => sprintf('%s·%s', $thisLoginLog->city, $thisLoginLog->country), 'last_login_time' => $lastLoginLog->created_at, 'last_ip' => $lastLoginLog->ip, 'last_location' => sprintf('%s·%s', $lastLoginLog->city, $lastLoginLog->country)], $locale);
        $result = $toolRep->sendMail($user->email, $subject, $body);
        Logger::writeWithContext((string) sprintf('%s, user: %s login notify result: %s', $log, $user->username, var_export($result, true)), (string) 'info', (bool) false);

    }

    /**
     * Handle a job failure.
     *
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Logger::writeWithContext((string) ('failed: '.$exception->getMessage().$exception->getTraceAsString()), (string) 'error', (bool) false);
    }
}
