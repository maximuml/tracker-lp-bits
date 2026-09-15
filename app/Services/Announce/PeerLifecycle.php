<?php

declare(strict_types=1);

namespace App\Services\Announce;

use App\DTOs\AnnounceRequestDto;
use App\Exceptions\TrackerException;
use App\Support\LegacyDb;
use App\Support\Logger;
use Illuminate\Support\Facades\DB;

final class PeerLifecycle
{
    /** @var array<string, mixed> */
    private array $user;

    private string $dt;

    /** @var array<string, mixed> */
    private array $params;

    private string $peerId = '';

    private string $ip = '';

    private ?string $ipv4 = null;

    private ?string $ipv6 = null;

    private string $agent = '';

    private int $seeder = 0;

    private ?string $event = null;

    private int $left = 0;

    private int $userId = 0;

    private int $torrentId = 0;

    /** @var array<string, mixed>|null */
    private ?array $self = null;

    /** @var array<string, mixed>|false */
    private array|false $snatchInfo = false;

    /** @var array<string, mixed> */
    private array $torrentUpdate = [];

    private readonly PeerLimitGuard $limitGuard;

    /**
     * @param  array<string, mixed>  $torrent
     * @param  array<string, mixed>  $user
     */
    public function __construct(AnnounceRequestDto $dto, array $torrent, array $user, string $dt)
    {
        $this->limitGuard = new PeerLimitGuard($dto, $torrent);
        $this->user = $user;
        $this->dt = $dt;

        $this->params = $dto->toParams();
        $this->peerId = $dto->peerId->toBinary();
        $this->ip = $dto->ip;
        $this->ipv4 = $dto->ipv4;
        $this->ipv6 = $dto->ipv6;
        $this->agent = $dto->userAgent;
        $this->seeder = $dto->isSeeder() ? 1 : 0;
        $this->left = $dto->left;
        $this->event = $dto->event;
        $this->userId = (int) ($user['id'] ?? 0);
        $this->torrentId = (int) ($torrent['id'] ?? 0);
    }

    /**
     * @param  array<string, mixed>|false  $snatchInfo
     */
    public function setSnatchInfo(array|false $snatchInfo): void
    {
        $this->snatchInfo = $snatchInfo;
    }

    public function process(int $upthis, int $downthis, ?string $snatchTimeColumn, int $snatchTimeIncrement, int $leechTimeNoSeederIncrement): PeerLifecycleResult
    {
        if ($this->self !== null && $this->event === 'stopped') {
            $this->processStopped($upthis, $downthis, (string) $snatchTimeColumn, $snatchTimeIncrement, $leechTimeNoSeederIncrement);
        } elseif ($this->self !== null) {
            $this->processUpdate($upthis, $downthis, (string) $snatchTimeColumn, $snatchTimeIncrement, $leechTimeNoSeederIncrement);
        } else {
            $this->processNewPeer();
        }

        return new PeerLifecycleResult($this->torrentUpdate, $this->snatchInfo, $this->self);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSelf(): ?array
    {
        $selfRecord = DB::table('peers')
            ->where('torrent', $this->torrentId)
            ->where('userid', $this->userId)
            ->where('peer_id', $this->peerId)
            ->first();

        if (! $selfRecord) {
            return null;
        }

        $this->self = (array) $selfRecord;
        $this->self['last_action_unix_timestamp'] = $this->self['last_action']
            ? strtotime($this->self['last_action'])
            : 0;
        $this->self['announcetime'] = max(0, TIMENOW - (int) $this->self['last_action_unix_timestamp']);
        $this->self['prevts'] = ! empty($this->self['prev_action'])
            ? strtotime($this->self['prev_action'])
            : 0;

        return $this->self;
    }

    private function processNewPeer(): void
    {
        if ($this->event === 'stopped') {
            Logger::writeWithContext((string) "[INSERT PEER] event = 'stopped', ignore.", (string) 'info', (bool) false);

            return;
        }

        $isPeerExist = DB::table('peers')
            ->where('torrent', $this->torrentId)
            ->where('peer_id', $this->peerId)
            ->where('userid', $this->userId)
            ->exists();

        if ($isPeerExist) {
            Logger::writeWithContext((string) "[INSERT PEER] peer already exists for torrent: {$this->torrentId}, user: {$this->userId}.", (string) 'info', (bool) false);

            return;
        }

        $sameIPRecord = DB::table('peers')
            ->where('torrent', $this->torrentId)
            ->where('userid', $this->userId)
            ->where('ip', $this->ip)
            ->value('id');
        if (! empty($sameIPRecord) && $this->seeder === 1) {
            $this->limitGuard->warn('You cannot seed the same torrent in the same location from more than 1 client.', 300);
        }

        $valid = DB::table('peers')
            ->where('torrent', $this->torrentId)
            ->where('userid', $this->userId)
            ->count();
        if ($valid >= 1 && $this->seeder === 0) {
            throw TrackerException::failure('You already are downloading the same torrent. You may only leech from one location at a time.');
        }
        if ($valid >= 3 && $this->seeder === 1) {
            throw TrackerException::failure('You cannot seed the same torrent from more than 3 locations.');
        }

        $this->limitGuard->enforceForNewPeer($this->user, $this->userId);

        $peerInsert = [
            'torrent' => $this->torrentId,
            'userid' => $this->userId,
            'peer_id' => $this->peerId,
            'ip' => $this->ip,
            'port' => (int) $this->params['port'],
            'connectable' => 1,
            'uploaded' => (int) $this->params['uploaded'],
            'downloaded' => (int) $this->params['downloaded'],
            'to_go' => $this->left,
            'started' => $this->dt,
            'last_action' => $this->dt,
            'seeder' => $this->seeder,
            'agent' => $this->agent,
            'downloadoffset' => (int) $this->params['downloaded'],
            'uploadoffset' => (int) $this->params['uploaded'],
            'passkey' => $this->params['passkey'],
        ];

        if ($this->ipv4) {
            $peerInsert['ipv4'] = $this->ipv4;
        }
        if ($this->ipv6) {
            $peerInsert['ipv6'] = $this->ipv6;
        }

        Logger::writeWithContext((string) ("[INSERT PEER] peer not exists for torrent: {$this->torrentId}, user: {$this->userId}, peer_id: ".bin2hex($this->peerId)), (string) 'info', (bool) false);

        try {
            DB::table('peers')->insert($peerInsert);
            $this->torrentUpdate[$this->seeder === 1 ? 'seeders' : 'leechers'] = DB::raw(
                $this->seeder === 1 ? 'seeders + 1' : 'leechers + 1'
            );

            $existingSnatchId = DB::table('snatched')
                ->where('torrentid', $this->torrentId)
                ->where('userid', $this->userId)
                ->value('id');

            if ($existingSnatchId) {
                DB::table('snatched')->where('id', (int) $existingSnatchId)->update([
                    'to_go' => $this->left,
                    'last_action' => $this->dt,
                ]);
                $this->snatchInfo = LegacyDb::snatchInfo($this->torrentId, $this->userId);
            } else {
                $snatchInsert = [
                    'torrentid' => $this->torrentId,
                    'userid' => $this->userId,
                    'ip' => $this->ip,
                    'port' => (int) $this->params['port'],
                    'uploaded' => (int) $this->params['uploaded'],
                    'downloaded' => (int) $this->params['downloaded'],
                    'to_go' => $this->left,
                    'startdat' => $this->dt,
                    'last_action' => $this->dt,
                ];
                DB::table('snatched')->insert($snatchInsert);
                $this->snatchInfo = LegacyDb::snatchInfo($this->torrentId, $this->userId);
            }
        } catch (\Exception $exception) {
            Logger::writeWithContext((string) ('[INSERT PEER] error: '.$exception->getMessage()), (string) 'info', (bool) false);
        }
    }

    private function processUpdate(int $upthis, int $downthis, string $snatchTimeColumn, int $snatchTimeIncrement, int $leechTimeNoSeederIncrement): void
    {
        $peerIPUpdate = [];
        if ($this->ipv4) {
            $peerIPUpdate['ipv4'] = $this->ipv4;
        }
        if ($this->ipv6) {
            $peerIPUpdate['ipv6'] = $this->ipv6;
        }

        $peerUpdate = [
            'ip' => $this->ip,
            'port' => (int) $this->params['port'],
            'uploaded' => (int) $this->params['uploaded'],
            'downloaded' => (int) $this->params['downloaded'],
            'to_go' => $this->left,
            'prev_action' => (string) ($this->self['last_action'] ?? ''),
            'last_action' => $this->dt,
            'seeder' => $this->seeder,
            'agent' => $this->agent,
        ];

        $snatchUpdate = $this->buildSnatchUpdate($upthis, $downthis, $snatchTimeColumn, $snatchTimeIncrement, $leechTimeNoSeederIncrement);

        if ($this->event === 'completed') {
            $peerUpdate['finishedat'] = TIMENOW;
            $snatchUpdate['completedat'] = $this->dt;
            $snatchUpdate['finished'] = 1;

            // W2-04: Idempotency guard — only increment times_completed if
            // this peer has not already completed this torrent. Without this
            // check, a duplicate/retried "completed" event would double-count.
            $alreadyFinished = is_array($this->snatchInfo)
                && (int) ($this->snatchInfo['finished'] ?? 0) === 1;
            if (! $alreadyFinished) {
                $this->torrentUpdate['times_completed'] = DB::raw('times_completed + 1');
            }
        }

        $peerUpdate = array_merge($peerUpdate, $peerIPUpdate);

        $peerAffected = DB::table('peers')->where('id', (int) ($this->self['id'] ?? 0))->update($peerUpdate);

        if ($peerAffected > 0) {
            if ($this->seeder !== (int) ($this->self['seeder'] ?? 0)) {
                if ($this->seeder === 1) {
                    $this->torrentUpdate['seeders'] = DB::raw('seeders + 1');
                    $this->torrentUpdate['leechers'] = DB::raw('leechers - 1');
                } else {
                    $this->torrentUpdate['seeders'] = DB::raw('seeders - 1');
                    $this->torrentUpdate['leechers'] = DB::raw('leechers + 1');
                }
            }

            if (! empty($this->snatchInfo)) {
                DB::table('snatched')->where('id', (int) $this->snatchInfo['id'])->update($snatchUpdate);
            }
        }
    }

    private function processStopped(int $upthis, int $downthis, string $snatchTimeColumn, int $snatchTimeIncrement, int $leechTimeNoSeederIncrement): void
    {
        $snatchUpdate = $this->buildSnatchUpdate($upthis, $downthis, $snatchTimeColumn, $snatchTimeIncrement, $leechTimeNoSeederIncrement);

        $deleted = DB::table('peers')->where('id', (int) ($this->self['id'] ?? 0))->delete();
        if ($deleted) {
            $this->torrentUpdate[((int) ($this->self['seeder'] ?? 0)) === 1 ? 'seeders' : 'leechers'] = DB::raw(
                ((int) ($this->self['seeder'] ?? 0)) === 1 ? 'seeders - 1' : 'leechers - 1'
            );

            if (! empty($this->snatchInfo)) {
                DB::table('snatched')->where('id', (int) $this->snatchInfo['id'])->update($snatchUpdate);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnatchUpdate(int $upthis, int $downthis, string $snatchTimeColumn, int $snatchTimeIncrement, int $leechTimeNoSeederIncrement): array
    {
        if (! in_array($snatchTimeColumn, ['seedtime', 'leechtime'], true)) {
            throw new \InvalidArgumentException('Invalid snatch time column: '.$snatchTimeColumn);
        }
        $snatchUpdate = [
            'uploaded' => DB::raw(DB::getQueryGrammar()->wrap('uploaded').' + '.(int) $upthis), // @phpstan-ignore argument.type
            'downloaded' => DB::raw(DB::getQueryGrammar()->wrap('downloaded').' + '.(int) $downthis), // @phpstan-ignore argument.type
            'to_go' => $this->left,
            $snatchTimeColumn => DB::raw(DB::getQueryGrammar()->wrap($snatchTimeColumn).' + '.(int) $snatchTimeIncrement), // @phpstan-ignore argument.type
            'last_action' => $this->dt,
        ];

        if ($leechTimeNoSeederIncrement > 0) {
            $snatchUpdate['leech_time_no_seeder'] = DB::raw(DB::getQueryGrammar()->wrap('leech_time_no_seeder').' + '.(int) $leechTimeNoSeederIncrement); // @phpstan-ignore argument.type
        }

        return $snatchUpdate;
    }
}
