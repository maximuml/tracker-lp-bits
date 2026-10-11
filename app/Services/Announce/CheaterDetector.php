<?php

declare(strict_types=1);

namespace App\Services\Announce;

use App\DTOs\Announce\AnnounceUser;
use App\Enums\UserClass as UserClassEnum;
use App\Exceptions\TrackerException;
use App\Repositories\LogRepository;
use App\Repositories\UserAccountRepository;
use App\Repositories\UserCleanupRepository;
use App\Support\Config\SiteConfig;
use App\Support\Format;
use Illuminate\Support\Facades\DB;

final class CheaterDetector
{
    public function __construct(
        private readonly LogRepository $logRepository,
        private readonly UserAccountRepository $userAccountRepository,
        private readonly UserCleanupRepository $userCleanupRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $self
     */
    public function checkSpeed(
        int $upthis,
        ?array $self,
        AnnounceUser $user,
    ): void {
        if ($self === null || $self['announcetime'] <= 0) {
            return;
        }

        if ($user->classAtLeast((int) UserClassEnum::VIP->value) || $user->isDonor) {
            return;
        }
    }

    /**
     * @param  array<string, mixed>|null  $self
     * @param  array<string, mixed>  $torrent
     */
    public function checkCheating(
        int $upthis,
        int $downthis,
        ?array $self,
        AnnounceUser $user,
        array $torrent,
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
        if ($user->classAtLeast($nodetectSecurity)) {
            return;
        }

        $this->doCheaterCheck($upthis, $downthis, (int) $torrent['seeders'], (int) $torrent['leechers'], $cheaterdetSecurity, $self, $user, $torrentId, $dt);
    }

    /**
     * @param  array<string, mixed>  $self
     */
    private function doCheaterCheck(
        int $uploaded,
        int $downloaded,
        int $seeders,
        int $leechers,
        int $cheaterdetSecurity,
        array $self,
        AnnounceUser $user,
        int $torrentId,
        string $time,
    ): void {
        $upspeed = $uploaded > 0 ? $uploaded / $self['announcetime'] : 0;
        $mustBeCheaterSpeed = (int) SiteConfig::current()->system->maximumUploadSpeed(8000) * 1024 * 1024 / 8;
        $mayBeCheaterSpeed = $mustBeCheaterSpeed / 2;

        if ($uploaded > 1073741824 && $upspeed > ($mustBeCheaterSpeed / $cheaterdetSecurity)) {
            DB::transaction(function () use ($time, $uploaded, $downloaded, $seeders, $leechers, $upspeed, $self, $user, $torrentId) {
                $comment = 'User account was automatically disabled by system';
                $this->logRepository->insertCheater([
                    'added' => $time,
                    'userid' => $user->id,
                    'torrentid' => $torrentId,
                    'uploaded' => $uploaded,
                    'downloaded' => $downloaded,
                    'anctime' => $self['announcetime'],
                    'seeders' => $seeders,
                    'leechers' => $leechers,
                    'comment' => $comment,
                ]);
                $this->userAccountRepository->updateById($user->id, ['enabled' => false]);
                $this->userCleanupRepository->insertBanLogs([[
                    'uid' => $user->id,
                    'username' => $user->username,
                    'reason' => "$comment(Upload speed:".Format::size($upspeed).'/s)',
                ]]);
            });

            throw TrackerException::failure('We believe you\'re trying to cheat. And your account is disabled.');
        }

        if ($uploaded > 1073741824 && $upspeed > ($mayBeCheaterSpeed / $cheaterdetSecurity)) {
            $this->insertOrUpdateCheater($time, $uploaded, $downloaded, $seeders, $leechers, 'Abnormally high uploading rate', $self, $user->id, $torrentId);

            return;
        }

        if ($cheaterdetSecurity > 1 && $uploaded > 1073741824 && $upspeed > 1048576 && $leechers < (2 * $cheaterdetSecurity)) {
            $this->insertOrUpdateCheater($time, $uploaded, $downloaded, $seeders, $leechers, 'User is uploading fast when there is few leechers', $self, $user->id, $torrentId);

            return;
        }

        if ($cheaterdetSecurity > 1 && $uploaded > 10485760 && $upspeed > 102400 && $leechers == 0) {
            $this->insertOrUpdateCheater($time, $uploaded, $downloaded, $seeders, $leechers, 'User is uploading when there is no leecher', $self, $user->id, $torrentId);
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

        $cheaterId = $this->logRepository->findRecentCheaterId($userId, $torrentId, $dt);

        if (empty($cheaterId)) {
            $this->logRepository->insertCheater([
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
            $this->logRepository->incrementCheaterHit((int) $cheaterId);
        }
    }
}
