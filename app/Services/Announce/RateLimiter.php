<?php

declare(strict_types=1);

namespace App\Services\Announce;

use App\DTOs\AnnounceRequestDto;
use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use Nexus\Database\NexusDB;

/**
 * Announce rate limiting / deduplication checks.
 *
 * Implements the Redis-backed locks previously found in
 * `AnnounceService::rateLimitLocks()`:
 *   - passkey_invalid cache check
 *   - isReAnnounce (deduplication of concurrent announces)
 *   - torrent_not_exists cache check
 *   - reAnnounceCheckByInfoHash frequency gate
 */
final class RateLimiter
{
    private const RE_ANNOUNCE_INTERVAL = 5;
    private const FREQUENCY_INTERVAL = 30;

    public function check(AnnounceRequestDto $dto): RateLimitResult
    {
        $redis = NexusDB::redis();

        $passkey = $dto->passkey->toString();
        $infoHashBinary = $dto->infoHash->toBinary();
        $infoHashFingerprint = $dto->infoHash->fingerprint();

        if ($redis->get("passkey_invalid:{$passkey}")) {
            $this->warn($dto, 'Passkey invalid');
        }

        $lockParams = ['info_hash' => $infoHashBinary, 'passkey' => $passkey];
        $reAnnounceKey = 'isReAnnounce:' . md5(http_build_query($lockParams));
        $isReAnnounce = !$redis->set($reAnnounceKey, TIMENOW, ['nx', 'ex' => self::RE_ANNOUNCE_INTERVAL]);

        if ($redis->get("torrent_not_exists:{$infoHashBinary}")) {
            throw TrackerException::failure('torrent not registered with this tracker');
        }

        $frequencyKey = "reAnnounceCheckByInfoHash:{$passkey}:{$infoHashFingerprint}";
        $isStoppedOrCompleted = $dto->isStoppedOrCompleted();

        if (
            !$isStoppedOrCompleted
            && !$isReAnnounce
            && !$redis->set($frequencyKey, TIMENOW, ['nx', 'ex' => self::FREQUENCY_INTERVAL])
        ) {
            $this->warn($dto, 'Request too frequent(h)', 300);
        }

        return new RateLimitResult($isReAnnounce);
    }

    private function warn(AnnounceRequestDto $dto, string $message, int $interval = 7200): void
    {
        if ($dto->isStoppedOrCompleted()) {
            throw TrackerException::failure($message);
        }

        throw new TrackerWarningException($message, $this->emptyBase($dto), $interval);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyBase(AnnounceRequestDto $dto): array
    {
        $base = [
            'interval'     => MIN_ANNOUNCE_WAIT_SECOND,
            'min interval' => MIN_ANNOUNCE_WAIT_SECOND,
            'complete'     => 0,
            'incomplete'   => 0,
            'downloaded'   => 0,
            'peers'        => $dto->compact ? '' : [],
        ];

        if ($dto->compact) {
            $base['peers6'] = '';
        }

        return $base;
    }
}
