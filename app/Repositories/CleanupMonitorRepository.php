<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Http\Middleware\Locale;
use App\Models\Avp;
use App\Models\User;
use App\Support\Config;
use App\Support\Config\SiteConfig;
use App\Support\Format;
use App\Support\Logger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cleanup monitoring: overdue-cleanup and failed-queue-job alarms,
 * extracted from CleanupRepository.
 */
final class CleanupMonitorRepository
{
    /** @param  mixed  $level */
    private function getInterval($level): int
    {
        return SiteConfig::current()->main->autocleanInterval((string) $level);
    }

    public function checkCleanup(): void
    {
        $now = Carbon::now();
        $timestamp = $now->getTimestamp();
        $toolRep = app(ToolRepository::class);
        $arvToLevel = [
            'lastcleantime' => 'one',
            'lastcleantime2' => 'two',
            'lastcleantime3' => 'three',
            'lastcleantime4' => 'four',
            'lastcleantime5' => 'five',
        ];
        $avps = Avp::query()->get()->keyBy('arg');
        if ($avps->isEmpty()) {
            return;
        }
        foreach ($arvToLevel as $arg => $level) {
            $value = $avps->get($arg);
            if (! $value instanceof Avp) {
                continue;
            }
            $interval = $this->getInterval($level);
            if ($interval <= 0) {
                Logger::writeWithContext((string) sprintf('level: %s not set cleanup interval', $level), (string) 'error', (bool) false);

                continue;
            }
            $lastTime = (int) ($value->value_u ?? 0);
            if ($timestamp < $lastTime + $interval * 2) {
                continue;
            }
            $receiverUid = SiteConfig::current()->system->alarmEmailReceiver();
            Logger::writeWithContext((string) "receiverUid: {$receiverUid}", (string) 'info', (bool) false);
            if (empty($receiverUid)) {
                $locale = Locale::getDefault();
                $subject = $this->getAlarmEmailSubjectForCleanup($locale);
                $msg = $this->getAlarmEmailBodyForCleanup($now, $level, $lastTime, $interval, $locale);
                Logger::writeWithContext((string) sprintf('%s - %s', $subject, $msg), (string) 'error', (bool) false);
            } else {
                $receiverUidArr = preg_split("/\s+/", $receiverUid);
                $users = User::query()->whereIn('id', $receiverUidArr)->get(User::$commonFields);
                foreach ($users as $user) {
                    $locale = $user->locale;
                    $subject = $this->getAlarmEmailSubjectForCleanup($locale);
                    $msg = $this->getAlarmEmailBodyForCleanup($now, $level, $lastTime, $interval, $locale);
                    $result = $toolRep->sendMail($user->email, $subject, $msg);
                    Logger::writeWithContext((string) sprintf('send msg: %s result: %s', $msg, var_export($result, true)), (string) ($result ? 'info' : 'error'), (bool) false);
                }
            }

            return;
        }
    }

    /**
     * @return mixed
     */
    private function getAlarmEmailSubjectForCleanup(?string $locale = null)
    {
        return \App\Support\Locale::trans('cleanup.alarm_email_subject', ['site_name' => SiteConfig::current()->basic->siteName()], $locale);
    }

    /**
     * @return mixed
     */
    private function getAlarmEmailBodyForCleanup(Carbon $now, string $level, int $lastTime, int $interval, ?string $locale = null)
    {
        return \App\Support\Locale::trans('cleanup.alarm_email_body', ['now_time' => $now->toDateTimeString(), 'level' => $level, 'last_time' => $lastTime > 0 ? Carbon::createFromTimestamp($lastTime)->toDateTimeString() : '', 'elapsed_seconds' => $lastTime > 0 ? $now->getTimestamp() - $lastTime : '', 'elapsed_seconds_human' => $lastTime > 0 ? Format::prettyTimeWithLocale($now->getTimestamp() - $lastTime) : '', 'interval' => $interval, 'interval_human' => Format::prettyTimeWithLocale($interval)], $locale);
    }

    public function checkQueueFailedJobs(): void
    {
        $now = Carbon::now();
        $since = $now->subHours(6)->toDateTimeString();
        $failedJobsTable = Config::get('queue.failed.table', null);
        $failedJobsCount = DB::table($failedJobsTable)->where('failed_at', '>=', $since)->count();
        if ($failedJobsCount == 0) {
            Logger::writeWithContext((string) sprintf('no failed jobs since: %s', $since), (string) 'info', (bool) false);

            return;
        }
        $receiverUid = SiteConfig::current()->system->alarmEmailReceiver();
        Logger::writeWithContext((string) "receiverUid: {$receiverUid}", (string) 'info', (bool) false);
        $toolRep = app(ToolRepository::class);
        if (empty($receiverUid)) {
            $locale = Locale::getDefault();
            $subject = $this->getAlarmEmailSubjectForQueueFailedJobs($locale);
            $msg = $this->getAlarmEmailBodyForQueueFailedJobs($since, $failedJobsCount, $failedJobsTable, $locale);
            Logger::writeWithContext((string) sprintf('%s - %s', $subject, $msg), (string) 'error', (bool) false);
        } else {
            $receiverUidArr = preg_split("/\s+/", $receiverUid);
            $users = User::query()->whereIn('id', $receiverUidArr)->get(User::$commonFields);
            foreach ($users as $user) {
                $locale = $user->locale;
                $subject = $this->getAlarmEmailSubjectForQueueFailedJobs($locale);
                $msg = $this->getAlarmEmailBodyForQueueFailedJobs($since, $failedJobsCount, $failedJobsTable, $locale);
                $result = $toolRep->sendMail($user->email, $subject, $msg);
                Logger::writeWithContext((string) sprintf('send msg: %s result: %s', $msg, var_export($result, true)), (string) ($result ? 'info' : 'error'), (bool) false);
            }
        }
    }

    /**
     * @return mixed
     */
    private function getAlarmEmailSubjectForQueueFailedJobs(?string $locale = null)
    {
        return \App\Support\Locale::trans('cleanup.alarm_email_subject_for_queue_failed_jobs', ['site_name' => SiteConfig::current()->basic->siteName()], $locale);
    }

    /**
     * @return mixed
     */
    private function getAlarmEmailBodyForQueueFailedJobs(string $since, int $count, string $failedJobTable, ?string $locale = null)
    {
        return \App\Support\Locale::trans('cleanup.alarm_email_body_for_queue_failed_jobs', ['since' => $since, 'count' => $count, 'failed_job_table' => $failedJobTable], $locale);
    }
}
