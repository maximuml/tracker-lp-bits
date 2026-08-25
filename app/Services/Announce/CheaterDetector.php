<?php

declare(strict_types=1);

namespace App\Services\Announce;

use App\Exceptions\TrackerException;
use App\Models\User;
use App\Models\UserBanLog;
use App\Repositories\UserRepository;
use App\Support\Config\SiteConfig;
use App\Support\Format;
use App\Support\Logger;
use Illuminate\Support\Facades\DB;

final class CheaterDetector
{
    public function __construct(
        private readonly ResponseBuilder $responseBuilder,
    ) {}

    /**
     * @param  array<string, mixed>  $self
     * @param  array<string, mixed>  $user
     */
    public function checkSpeed(
        int $upthis,
        ?array $self,
        array $user,
        int $userId,
        bool $isDonor,
        bool $isIPSeedBox,
    ): void {
        if ($self === null || $self['announcetime'] <= 0 || ! SiteConfig::current()->seedBox->enabled()) {
            return;
        }

        if ((int) $user['class'] >= (int) User::CLASS_VIP || $isDonor || $isIPSeedBox) {
            return;
        }

        $notSeedBoxMaxSpeedMbps = (float) SiteConfig::current()->seedBox->notSeedBoxMaxSpeed(0);
        if ($notSeedBoxMaxSpeedMbps <= 0) {
            return;
        }

        $upSpeed = ($upthis / $self['announcetime'] / 1024 / 1024) * 8;
        $upSpeedMbps = number_format($upSpeed, 2, '.', '');
        Logger::writeWithContext((string) "notSeedBoxMaxSpeedMbps: {$notSeedBoxMaxSpeedMbps}, upSpeedMbps: {$upSpeedMbps}", (string) 'info', (bool) false);

        if ($upSpeed > $notSeedBoxMaxSpeedMbps) {
            (new UserRepository)->updateDownloadPrivileges(null, $userId, 'no', 'upload_over_speed');
            Logger::writeWithContext((string) "user: {$userId} downloading privileges have been disabled! (over speed), upSpeedMbps: {$upSpeedMbps} > notSeedBoxMaxSpeedMbps: {$notSeedBoxMaxSpeedMbps}", (string) 'error', (bool) false);
            $this->responseBuilder->warn('Your downloading privileges have been disabled! (over speed)', 300);
        }
    }

    /**
     * @param  array<string, mixed>|null  $self
     * @param  array<string, mixed>  $user
     * @param  array<string, mixed>  $torrent
     */
    public function checkCheating(
        int $upthis,
        int $downthis,
        ?array $self,
        array $user,
        array $torrent,
        int $userId,
        int $torrentId,
        string $dt,
    ): void {
        if ($self === null || (int) ($self['announcetime'] ?? 0) <= 10) {
            return;
        }

        $cheaterdetSecurity = (int) SiteConfig::current()->security->cheaterdet(0);
        if (! $cheaterdetSecurity) {
            return;
        }

        $nodetectSecurity = (int) SiteConfig::current()->security->noDetect(0);
        if ((int) $user['class'] >= $nodetectSecurity) {
            return;
        }

        $this->doCheaterCheck($upthis, $downthis, (int) $torrent['seeders'], (int) $torrent['leechers'], $cheaterdetSecurity, $self, $user, $userId, $torrentId, $dt);
    }

    /**
     * @param  array<string, mixed>  $self
     * @param  array<string, mixed>  $user
     */
    private function doCheaterCheck(
        int $uploaded,
        int $downloaded,
        int $seeders,
        int $leechers,
        int $cheaterdetSecurity,
        array $self,
        array $user,
        int $userId,
        int $torrentId,
        string $time,
    ): void {
        $upspeed = $uploaded > 0 ? $uploaded / $self['announcetime'] : 0;
        $mustBeCheaterSpeed = (int) SiteConfig::current()->system->maximumUploadSpeed(8000) * 1024 * 1024 / 8;
        $mayBeCheaterSpeed = $mustBeCheaterSpeed / 2;

        if ($uploaded > 1073741824 && $upspeed > ($mustBeCheaterSpeed / $cheaterdetSecurity)) {
            DB::transaction(function () use ($time, $uploaded, $downloaded, $seeders, $leechers, $upspeed, $self, $user, $userId, $torrentId) {
                $comment = 'User account was automatically disabled by system';
                DB::table('cheaters')->insert([
                    'added' => $time,
                    'userid' => $userId,
                    'torrentid' => $torrentId,
                    'uploaded' => $uploaded,
                    'downloaded' => $downloaded,
                    'anctime' => $self['announcetime'],
                    'seeders' => $seeders,
                    'leechers' => $leechers,
                    'comment' => $comment,
                ]);
                DB::table('users')->where('id', $userId)->update(['enabled' => 'no']);
                UserBanLog::query()->insert([
                    'uid' => $userId,
                    'username' => $user['username'],
                    'reason' => "$comment(Upload speed:".Format::size($upspeed).'/s)',
                ]);
            });

            throw TrackerException::failure('We believe you\'re trying to cheat. And your account is disabled.');
        }

        if ($uploaded > 1073741824 && $upspeed > ($mayBeCheaterSpeed / $cheaterdetSecurity)) {
            $this->insertOrUpdateCheater($time, $uploaded, $downloaded, $seeders, $leechers, 'Abnormally high uploading rate', $self, $userId, $torrentId);

            return;
        }

        if ($cheaterdetSecurity > 1 && $uploaded > 1073741824 && $upspeed > 1048576 && $leechers < (2 * $cheaterdetSecurity)) {
            $this->insertOrUpdateCheater($time, $uploaded, $downloaded, $seeders, $leechers, 'User is uploading fast when there is few leechers', $self, $userId, $torrentId);

            return;
        }

        if ($cheaterdetSecurity > 1 && $uploaded > 10485760 && $upspeed > 102400 && $leechers == 0) {
            $this->insertOrUpdateCheater($time, $uploaded, $downloaded, $seeders, $leechers, 'User is uploading when there is no leecher', $self, $userId, $torrentId);
        }
    }

    /**
     * @param  array<string, mixed>  $self
     */
    private function insertOrUpdateCheater(
        string $time,
        int $uploaded,
        int $downloaded,
        int $seeders,
        int $leechers,
        string $comment,
        array $self,
        int $userId,
        int $torrentId,
    ): void {
        $secs = 24 * 60 * 60;
        $dt = date('Y-m-d H:i:s', strtotime($time) - $secs);

        $cheaterId = DB::table('cheaters')
            ->where('userid', $userId)
            ->where('torrentid', $torrentId)
            ->where('added', '>', $dt)
            ->value('id');

        if (empty($cheaterId)) {
            DB::table('cheaters')->insert([
                'added' => $time,
                'userid' => $userId,
                'torrentid' => $torrentId,
                'uploaded' => $uploaded,
                'downloaded' => $downloaded,
                'anctime' => $self['announcetime'],
                'seeders' => $seeders,
                'leechers' => $leechers,
                'hit' => 1,
                'comment' => $comment,
            ]);
        } else {
            DB::table('cheaters')->where('id', $cheaterId)->update([
                'hit' => DB::raw('hit + 1'),
                'dealtwith' => 0,
            ]);
        }
    }
}
