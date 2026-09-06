<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\HitAndRunStatus;
use App\Models\HitAndRun;
use App\Models\Snatch;
use App\Models\User;
use App\Support\Format;
use App\Support\LegacyDb;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\Ratio;
use App\Support\Time;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HitAndRunStatusService
{
    /**
     * @return array<int|string, mixed>
     */
    public function geReachedMessage(HitAndRun $hitAndRun): array
    {
        $snatched = $hitAndRun->snatch;
        if (! $snatched instanceof Snatch) {
            throw new \InvalidArgumentException('Snatch not found');
        }
        $user = $hitAndRun->user;
        if (! $user instanceof User) {
            throw new \InvalidArgumentException('User not found');
        }

        return [
            'receiver' => $hitAndRun->uid,
            'added' => Carbon::now()->toDateTimeString(),
            'subject' => Locale::trans('hr.reached_message_subject', ['hit_and_run_id' => $hitAndRun->id], $user->locale),
            'msg' => Locale::trans('hr.reached_message_content', ['completed_at' => Time::formatDateTime($snatched->completedat ?: $snatched->startdat), 'torrent_id' => $hitAndRun->torrent_id, 'torrent_name' => $hitAndRun->torrent->name], $user->locale),
        ];
    }

    /**
     * @param  array<int|string, mixed>  $setting
     * @param  array<int, array<int|string, mixed>>  $messages
     */
    public function reachedByShareRatio(HitAndRun $hitAndRun, array $setting, array &$messages = []): bool
    {
        Logger::writeWithContext((string) __METHOD__, (string) 'info', (bool) false);
        $snatch = $hitAndRun->snatch;
        if (! $snatch instanceof Snatch) {
            throw new \InvalidArgumentException('Snatch not found');
        }
        $user = $hitAndRun->user;
        if (! $user instanceof User) {
            throw new \InvalidArgumentException('User not found');
        }
        $comment = Locale::trans('hr.reached_by_share_ratio_comment', ['now' => Carbon::now()->toDateTimeString(), 'seed_time_minimum' => $setting['seed_time_minimum'], 'seed_time' => bcdiv((string) $snatch->seedtime, '3600', 1), 'share_ratio' => Ratio::hr($snatch->uploaded, $snatch->downloaded), 'ignore_when_ratio_reach' => $setting['ignore_when_ratio_reach']], $user->locale);
        $update = [
            'comment' => $comment,
        ];

        return $this->inspectingToReached($hitAndRun, $update, __FUNCTION__, $messages);
    }

    /**
     * @param  array<int|string, mixed>  $setting
     * @param  array<int, array<int|string, mixed>>  $messages
     */
    public function reachedBySeedTime(HitAndRun $hitAndRun, array $setting, array &$messages = []): bool
    {
        Logger::writeWithContext((string) __METHOD__, (string) 'info', (bool) false);
        $snatch = $hitAndRun->snatch;
        if (! $snatch instanceof Snatch) {
            throw new \InvalidArgumentException('Snatch not found');
        }
        $user = $hitAndRun->user;
        if (! $user instanceof User) {
            throw new \InvalidArgumentException('User not found');
        }
        $comment = Locale::trans('hr.reached_by_seed_time_comment', ['now' => Carbon::now()->toDateTimeString(), 'seed_time' => bcdiv((string) $snatch->seedtime, '3600', 1), 'seed_time_minimum' => $setting['seed_time_minimum']], $user->locale);
        $update = [
            'comment' => $comment,
        ];

        return $this->inspectingToReached($hitAndRun, $update, __FUNCTION__, $messages);
    }

    /**
     * @param  array<int|string, mixed>  $setting
     * @param  array<int, array<int|string, mixed>>  $messages
     */
    public function reachedByLeechTime(HitAndRun $hitAndRun, array $setting, array &$messages = []): bool
    {
        Logger::writeWithContext((string) __METHOD__, (string) 'info', (bool) false);
        $snatch = $hitAndRun->snatch;
        if (! $snatch instanceof Snatch) {
            throw new \InvalidArgumentException('Snatch not found');
        }
        $user = $hitAndRun->user;
        if (! $user instanceof User) {
            throw new \InvalidArgumentException('User not found');
        }
        $comment = Locale::trans('hr.reached_by_leech_time_comment', ['now' => Carbon::now()->toDateTimeString(), 'leech_time' => bcdiv((string) ($snatch->leech_time_no_seeder - $hitAndRun->leech_time_no_seeder_begin), '3600', 1), 'leech_time_minimum' => $setting['leech_time_minimum']], $user->locale);
        $update = [
            'comment' => $comment,
        ];

        return $this->inspectingToReached($hitAndRun, $update, __FUNCTION__, $messages);
    }

    /**
     * @param  array<int, array<int|string, mixed>>  $messages
     */
    public function reachedBySpecialUserClass(HitAndRun $hitAndRun, array &$messages = []): bool
    {
        Logger::writeWithContext((string) __METHOD__, (string) 'info', (bool) false);
        $comment = Locale::trans('hr.reached_by_special_user_class_comment', ['user_class_text' => $hitAndRun->user->class_text], $hitAndRun->user->locale);
        $update = [
            'comment' => $comment,
        ];

        return $this->inspectingToReached($hitAndRun, $update, __FUNCTION__, $messages);
    }

    /**
     * @param  array<string, mixed>  $update
     * @param  array<int, array<int|string, mixed>>  $messages
     */
    public function inspectingToReached(HitAndRun $hitAndRun, array $update, string $logPrefix = '', array &$messages = []): bool
    {
        $update['status'] = HitAndRunStatus::REACHED->value;
        $affectedRows = DB::table($hitAndRun->getTable())
            ->where('id', $hitAndRun->id)
            ->where('status', HitAndRunStatus::INSPECTING->value)
            ->update($update);
        Logger::writeWithContext((string) ("[{$logPrefix}], ".LegacyDb::lastQuery(false, 'json').", affectedRows: {$affectedRows}"), (string) 'info', (bool) false);
        if ($affectedRows != 1) {
            Logger::writeWithContext((string) ($hitAndRun->toJson().", [{$logPrefix}], affectedRows != 1, skip!"), (string) 'notice', (bool) false);

            return false;
        }
        if ($hitAndRun->user->acceptNotification('hr_reached')) {
            $message = $this->geReachedMessage($hitAndRun);
            $messages[] = $message;
        } else {
            Logger::writeWithContext((string) ($hitAndRun->toJson().", [{$logPrefix}], user do not accept hr_reached notification"), (string) 'notice', (bool) false);
        }
        HitAndRun::clearCache($hitAndRun);

        return true;
    }

    /**
     * @param  array<int|string, mixed>  $setting
     * @param  array<int, array<int|string, mixed>>  $messages
     */
    public function unreached(HitAndRun $hitAndRun, array $setting, bool $disableUser = true, array &$messages = []): bool
    {
        Logger::writeWithContext((string) sprintf('hitAndRun: %s, disableUser: %s', $hitAndRun->toJson(), var_export($disableUser, true)), (string) 'info', (bool) false);
        $snatch = $hitAndRun->snatch;
        if (! $snatch instanceof Snatch) {
            throw new \InvalidArgumentException('Snatch not found');
        }
        $user = $hitAndRun->user;
        if (! $user instanceof User) {
            throw new \InvalidArgumentException('User not found');
        }
        $comment = Locale::trans('hr.unreached_comment', ['now' => Carbon::now()->toDateTimeString(), 'seed_time' => bcdiv((string) $snatch->seedtime, '3600', 1), 'seed_time_minimum' => $setting['seed_time_minimum'], 'share_ratio' => Ratio::hr($snatch->uploaded, $snatch->downloaded), 'torrent_size' => Format::size($hitAndRun->torrent->size), 'ignore_when_ratio_reach' => $setting['ignore_when_ratio_reach']], $user->locale);
        $update = [
            'status' => HitAndRunStatus::UNREACHED->value,
            'comment' => $comment,
        ];
        $affectedRows = DB::table($hitAndRun->getTable())
            ->where('id', $hitAndRun->id)
            ->where('status', HitAndRunStatus::INSPECTING->value)
            ->update($update);
        Logger::writeWithContext((string) ('[H&R_UNREACHED], '.LegacyDb::lastQuery(false, 'json').", affectedRows: {$affectedRows}"), (string) 'info', (bool) false);
        if ($affectedRows != 1) {
            Logger::writeWithContext((string) ($hitAndRun->toJson().', [H&R_UNREACHED], affectedRows != 1, skip!'), (string) 'notice', (bool) false);

            return false;
        }
        $message = [
            'receiver' => $hitAndRun->uid,
            'added' => Carbon::now()->toDateTimeString(),
            'subject' => Locale::trans('hr.unreached_message_subject', ['hit_and_run_id' => $hitAndRun->id], $hitAndRun->user->locale),
            'msg' => Locale::trans('hr.unreached_message_content', ['completed_at' => Time::formatDateTime($snatch->completedat), 'torrent_id' => $hitAndRun->torrent_id, 'torrent_name' => $hitAndRun->torrent->name], $user->locale),
        ];
        $messages[] = $message;
        HitAndRun::clearCache($hitAndRun);

        return true;
    }
}
