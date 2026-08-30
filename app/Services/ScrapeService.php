<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ScrapeRequestDto;
use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\ValueObjects\InfoHash;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ScrapeService
{
    /**
     * @return array<string, mixed>
     */
    public function scrape(ScrapeRequestDto $dto): array
    {
        $this->authenticateUser($dto);

        if ($dto->infoHashes === []) {
            throw new TrackerWarningException('Require info_hash.', ['files' => []], 86400);
        }

        $cacheKey = $this->cacheKey($dto->infoHashes);

        return Cache::remember($cacheKey, 1200, function () use ($dto) {
            return $this->buildScrapeData($dto->infoHashes);
        });
    }

    private function authenticateUser(ScrapeRequestDto $dto): void
    {
        $passkey = $dto->passkey->toString();

        $user = Cache::remember("user_passkey_{$passkey}_content", 3600, function () use ($passkey) {
            $record = User::query()
                ->select([
                    'id', 'username', 'downloadpos', 'enabled', 'uploaded', 'downloaded',
                    'class', 'parked', 'clientselect', 'showclienterror', 'passkey',
                    'donor', 'donoruntil', 'seedbonus', 'tracker_url_id',
                ])
                ->where('passkey', $passkey)
                ->first();

            return $record ? $record->toArray() : [];
        });

        if (empty($user)) {
            Redis::connection()->client()->set("passkey_invalid:{$passkey}", TIMENOW, ['ex' => 24 * 3600]);
            throw TrackerException::failure('Invalid passkey! Re-download the .torrent from '.SiteConfig::current()->basic->baseUrl());
        }

        if (! $user['enabled']) {
            throw TrackerException::failure('Your account is disabled!');
        }

        if ($user['parked']) {
            throw TrackerException::failure('Your account is parked! (Read the FAQ)');
        }

        if (! $user['downloadpos']) {
            throw TrackerException::failure('Your downloading privileges have been disabled! (Read the rules)');
        }
    }

    /**
     * @param  list<InfoHash>  $infoHashes
     * @return array<string, mixed>
     */
    private function buildScrapeData(array $infoHashes): array
    {
        $torrents = $this->queryTorrents($infoHashes);

        if ($torrents->isEmpty()) {
            throw new TrackerWarningException('Torrent not registered with this tracker.', ['files' => []], 86400);
        }

        $files = [];
        foreach ($torrents as $torrent) {
            /** @var string $hash */
            $hash = $torrent->getAttribute('info_hash');
            $files[$hash] = [
                'complete' => (int) $torrent->seeders,
                'downloaded' => (int) $torrent->times_completed,
                'incomplete' => (int) $torrent->leechers,
            ];
        }

        return ['files' => $files];
    }

    /**
     * @param  list<InfoHash>  $infoHashes
     * @return Collection<int, Torrent>
     */
    private function queryTorrents(array $infoHashes)
    {
        /** @var list<string> $binaries */
        $binaries = array_map(static fn (InfoHash $h) => $h->toBinary(), $infoHashes);

        $query = Torrent::query()->select(['info_hash', 'times_completed', 'seeders', 'leechers']);

        if (DB::connection()->getDriverName() === 'pgsql') {
            $query->where(function ($q) use ($infoHashes) {
                foreach ($infoHashes as $hash) {
                    $q->orWhereRaw("info_hash = decode(?, 'hex')", [$hash->toHex()]);
                }
            });
        } else {
            $query->whereIn('info_hash', $binaries);
        }

        return $query->get();
    }

    /**
     * @param  list<InfoHash>  $infoHashes
     */
    private function cacheKey(array $infoHashes): string
    {
        return 'scrape:'.md5(http_build_query(array_map(
            static fn (InfoHash $h) => $h->toBinary(),
            $infoHashes
        )));
    }
}
