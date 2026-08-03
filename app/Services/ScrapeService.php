<?php

namespace App\Services;

use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Nexus\Database\NexusDB;

class ScrapeService
{
    /**
     * @return array<string, mixed>
     */
    public function scrape(Request $request): array
    {
        $this->blockBrowser($request);

        $passkey = (string) $request->input('passkey', '');
        if ($passkey === '') {
            throw TrackerException::failure('require passkey');
        }

        $user = $this->authenticateUser($passkey);

        $infoHashes = $this->parseInfoHashes($request);
        if (empty($infoHashes)) {
            throw new TrackerWarningException('Require info_hash.', ['files' => []], 86400);
        }

        $cacheKey = $this->cacheKey($infoHashes);

        return Cache::remember($cacheKey, 1200, function () use ($infoHashes) {
            return $this->buildScrapeData($infoHashes);
        });
    }

    private function blockBrowser(Request $request): void
    {
        $agent = (string) $request->header('User-Agent');

        if (preg_match('/^(Mozilla|Opera|Links|Lynx)/', $agent)) {
            throw TrackerException::failure('Browser access blocked!');
        }

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'on') {
            $headers = $request->headers->all();
            if (isset($headers['cookie']) || isset($headers['accept-language']) || isset($headers['accept-charset'])) {
                throw TrackerException::failure('Anti-Cheater: You cannot use this agent');
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function authenticateUser(string $passkey): array
    {
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
            NexusDB::redis()->set("passkey_invalid:{$passkey}", TIMENOW, ['ex' => 24 * 3600]);
            throw TrackerException::failure("Invalid passkey! Re-download the .torrent from " . get_setting('basic.BASEURL'));
        }

        if ($user['enabled'] === 'no') {
            throw TrackerException::failure('Your account is disabled!');
        }

        if ($user['parked'] === 'yes') {
            throw TrackerException::failure('Your account is parked! (Read the FAQ)');
        }

        if ($user['downloadpos'] === 'no') {
            throw TrackerException::failure('Your downloading privileges have been disabled! (Read the rules)');
        }

        return $user;
    }

    /**
     * @return list<string>
     */
    private function parseInfoHashes(Request $request): array
    {
        $queryString = (string) $request->server->get('QUERY_STRING', '');
        if ($queryString === '') {
            $queryString = (string) $request->getQueryString();
        }

        preg_match_all('/info_hash=([^&]*)/i', $queryString, $matches);

        return array_values(array_filter(array_map('urldecode', $matches[1])));
    }

    /**
     * @param list<string> $infoHashes
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
            $hash = $torrent->getAttribute('info_hash');
            $files[$hash] = [
                'complete'   => (int) $torrent->seeders,
                'downloaded' => (int) $torrent->times_completed,
                'incomplete' => (int) $torrent->leechers,
            ];
        }

        return ['files' => $files];
    }

    /**
     * @param list<string> $infoHashes
     * @return \Illuminate\Database\Eloquent\Collection<int, Torrent>
     */
    private function queryTorrents(array $infoHashes)
    {
        $query = Torrent::query()->select(['info_hash', 'times_completed', 'seeders', 'leechers']);

        if (NexusDB::isPgsql()) {
            $query->where(function ($q) use ($infoHashes) {
                foreach ($infoHashes as $hash) {
                    $q->orWhereRaw("info_hash = decode(?, 'hex')", [bin2hex($hash)]);
                }
            });
        } else {
            $query->whereIn('info_hash', $infoHashes);
        }

        return $query->get();
    }

    /**
     * @param list<string> $infoHashes
     */
    private function cacheKey(array $infoHashes): string
    {
        return 'scrape:' . md5(http_build_query($infoHashes));
    }
}
