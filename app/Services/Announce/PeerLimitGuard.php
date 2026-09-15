<?php

declare(strict_types=1);

namespace App\Services\Announce;

use App\DTOs\AnnounceRequestDto;
use App\Enums\UserClass as UserClassEnum;
use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use App\Support\Config\SiteConfig;
use App\Support\Format;
use App\Support\Url;
use Illuminate\Support\Facades\DB;

/**
 * Wait-time and simultaneous-leech slot policy for new peers, plus the
 * announce "warning" response used to report them. Extracted from
 * PeerLifecycle to keep both classes under the 400-line ratchet.
 */
final class PeerLimitGuard
{
    /**
     * @param  array<string, mixed>  $torrent
     */
    public function __construct(
        private readonly AnnounceRequestDto $dto,
        private readonly array $torrent,
    ) {}

    /**
     * @param  array<string, mixed>  $user
     */
    public function enforceForNewPeer(array $user, int $userId): void
    {
        if ((int) $user['class'] >= (int) UserClassEnum::VIP->value) {
            return;
        }

        $ratio = ($user['downloaded'] > 0) ? ($user['uploaded'] / $user['downloaded']) : 1;
        $gigs = $user['downloaded'] / (1024 * 1024 * 1024);

        if ($gigs <= 10) {
            return;
        }

        if (SiteConfig::current()->main->waitSystem()) {
            $elapsed = TIMENOW - (int) ($this->torrent['ts'] ?? 0);
            $wait = match (true) {
                $ratio < 0.4 => 24,
                $ratio < 0.5 => 12,
                $ratio < 0.6 => 6,
                $ratio < 0.8 => 3,
                default => 0,
            };

            if ($elapsed < $wait) {
                $faqUrl = Url::schemeAndHost(true).'/faq.php#id46';
                $this->warn(
                    'Your ratio is too low! You need to wait '.Format::prettyTimeWithLocale($wait * 3600 - $elapsed).' to start, please read '.$faqUrl.' for details',
                    $elapsed
                );
            }
        }

        if (SiteConfig::current()->main->maxDlSystem()) {
            $max = match (true) {
                $ratio < 0.5 => 1,
                $ratio < 0.65 => 2,
                $ratio < 0.8 => 3,
                $ratio < 0.95 => 4,
                default => 0,
            };

            if ($max > 0) {
                $leechingCount = DB::table('peers')
                    ->where('userid', $userId)
                    ->where('seeder', 0)
                    ->count();

                if ($leechingCount >= $max) {
                    throw TrackerException::failure(
                        "Your slot limit is reached! You may at most download $max torrents at the same time, please read ".Url::schemeAndHost(true).'/faq.php#id66 for details'
                    );
                }
            }
        }
    }

    public function warn(string $message, int $interval = 7200): void
    {
        if ($this->dto->event !== null && in_array($this->dto->event, ['completed', 'stopped'], true)) {
            throw TrackerException::failure($message);
        }

        $torrentValues = $this->torrent;

        $base = [
            'interval' => MIN_ANNOUNCE_WAIT_SECOND,
            'min interval' => MIN_ANNOUNCE_WAIT_SECOND,
            'complete' => (int) ($torrentValues['seeders'] ?? 0),
            'incomplete' => (int) ($torrentValues['leechers'] ?? 0),
            'downloaded' => (int) ($torrentValues['times_completed'] ?? 0),
            'peers' => $this->dto->compact ? '' : [],
        ];
        if ($this->dto->compact) {
            $base['peers6'] = '';
        }

        throw new TrackerWarningException($message, $base, $interval);
    }
}
