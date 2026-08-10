<?php

declare(strict_types=1);

namespace App\Services\Announce;

use App\DTOs\AnnounceRequestDto;
use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use App\Models\User;
use App\Support\LegacyDb;
use App\Support\Url;
use Nexus\Database\NexusDB;

final class PeerLifecycle
{
    private AnnounceRequestDto $dto;

    /** @var array<string, mixed> */
    private array $torrent;

    /** @var array<string, mixed> */
    private array $user;

    private bool $isIPSeedBox;
    private string $dt;

    /** @var array<string, mixed> */
    private array $params;
    private string $peerId = '';
    private string $infoHash = '';
    private string $ip = '';
    private ?string $ipv4 = null;
    private ?string $ipv6 = null;
    private string $agent = '';
    private string $seeder = 'no';
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

    public function __construct(AnnounceRequestDto $dto, array $torrent, array $user, bool $isIPSeedBox, string $dt)
    {
        $this->dto = $dto;
        $this->torrent = $torrent;
        $this->user = $user;
        $this->isIPSeedBox = $isIPSeedBox;
        $this->dt = $dt;

        $this->params = $dto->toParams();
        $this->peerId = $dto->peerId->toBinary();
        $this->infoHash = $dto->infoHash->toBinary();
        $this->ip = $dto->ip;
        $this->ipv4 = $dto->ipv4;
        $this->ipv6 = $dto->ipv6;
        $this->agent = $dto->userAgent;
        $this->seeder = $dto->isSeeder() ? 'yes' : 'no';
        $this->left = $dto->left;
        $this->event = $dto->event;
        $this->userId = (int) ($user['id'] ?? 0);
        $this->torrentId = (int) ($torrent['id'] ?? 0);
    }

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


    public function findSelf(): ?array
    {
        $selfRecord = NexusDB::table('peers')
            ->where('torrent', $this->torrentId)
            ->where('userid', $this->userId)
            ->where('peer_id', $this->peerId)
            ->first();

        if (!$selfRecord) {
            return null;
        }

        $this->self = (array) $selfRecord;
        $this->self['last_action_unix_timestamp'] = $this->self['last_action']
            ? strtotime($this->self['last_action'])
            : 0;
        $this->self['announcetime'] = max(0, TIMENOW - (int) $this->self['last_action_unix_timestamp']);
        $this->self['prevts'] = !empty($this->self['prev_action'])
            ? strtotime($this->self['prev_action'])
            : 0;

        return $this->self;
    }


    private function processNewPeer(): void
    {
        if ($this->event === 'stopped') {
            do_log("[INSERT PEER] event = 'stopped', ignore.");
            return;
        }

        $isPeerExist = NexusDB::table('peers')
            ->where('torrent', $this->torrentId)
            ->where('peer_id', $this->peerId)
            ->where('userid', $this->userId)
            ->exists();

        if ($isPeerExist) {
            do_log("[INSERT PEER] peer already exists for torrent: {$this->torrentId}, user: {$this->userId}.");
            return;
        }

        $sameIPRecord = NexusDB::table('peers')
            ->where('torrent', $this->torrentId)
            ->where('userid', $this->userId)
            ->where('ip', $this->ip)
            ->value('id');
        if (!empty($sameIPRecord) && $this->seeder === 'yes') {
            $this->warn('You cannot seed the same torrent in the same location from more than 1 client.', 300);
        }

        $valid = NexusDB::table('peers')
            ->where('torrent', $this->torrentId)
            ->where('userid', $this->userId)
            ->count();
        if ($valid >= 1 && $this->seeder === 'no') {
            throw TrackerException::failure('You already are downloading the same torrent. You may only leech from one location at a time.');
        }
        if ($valid >= 3 && $this->seeder === 'yes') {
            throw TrackerException::failure('You cannot seed the same torrent from more than 3 locations.');
        }

        $this->enforceWaitAndSlotLimitsForNewPeer();

        $peerInsert = [
            'torrent'        => $this->torrentId,
            'userid'         => $this->userId,
            'peer_id'        => $this->peerId,
            'ip'             => $this->ip,
            'port'           => (int) $this->params['port'],
            'connectable'    => 'yes',
            'uploaded'       => (int) $this->params['uploaded'],
            'downloaded'     => (int) $this->params['downloaded'],
            'to_go'          => $this->left,
            'started'        => $this->dt,
            'last_action'    => $this->dt,
            'seeder'         => $this->seeder,
            'agent'          => $this->agent,
            'downloadoffset' => (int) $this->params['downloaded'],
            'uploadoffset'   => (int) $this->params['uploaded'],
            'passkey'        => $this->params['passkey'],
            'is_seed_box'    => (int) $this->isIPSeedBox,
        ];

        if ($this->ipv4) {
            $peerInsert['ipv4'] = $this->ipv4;
        }
        if ($this->ipv6) {
            $peerInsert['ipv6'] = $this->ipv6;
        }

        do_log("[INSERT PEER] peer not exists for torrent: {$this->torrentId}, user: {$this->userId}, peer_id: " . bin2hex($this->peerId));

        try {
            NexusDB::table('peers')->insert($peerInsert);
            $this->torrentUpdate[$this->seeder === 'yes' ? 'seeders' : 'leechers'] = NexusDB::raw(
                $this->seeder === 'yes' ? 'seeders + 1' : 'leechers + 1'
            );

            $existingSnatchId = NexusDB::table('snatched')
                ->where('torrentid', $this->torrentId)
                ->where('userid', $this->userId)
                ->value('id');

            if ($existingSnatchId) {
                NexusDB::table('snatched')->where('id', (int) $existingSnatchId)->update([
                    'to_go'       => $this->left,
                    'last_action' => $this->dt,
                ]);
                $this->snatchInfo = LegacyDb::snatchInfo($this->torrentId, $this->userId);
            } else {
                $snatchInsert = [
                    'torrentid'  => $this->torrentId,
                    'userid'     => $this->userId,
                    'ip'         => $this->ip,
                    'port'       => (int) $this->params['port'],
                    'uploaded'   => (int) $this->params['uploaded'],
                    'downloaded' => (int) $this->params['downloaded'],
                    'to_go'      => $this->left,
                    'startdat'   => $this->dt,
                    'last_action'=> $this->dt,
                ];
                NexusDB::table('snatched')->insert($snatchInsert);
                $this->snatchInfo = LegacyDb::snatchInfo($this->torrentId, $this->userId);
            }
        } catch (\Exception $exception) {
            do_log('[INSERT PEER] error: ' . $exception->getMessage());
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
            'ip'          => $this->ip,
            'port'        => (int) $this->params['port'],
            'uploaded'    => (int) $this->params['uploaded'],
            'downloaded'  => (int) $this->params['downloaded'],
            'to_go'       => $this->left,
            'prev_action' => $this->self['last_action'],
            'last_action' => $this->dt,
            'seeder'      => $this->seeder,
            'agent'       => $this->agent,
            'is_seed_box' => (int) $this->isIPSeedBox,
        ];

        $snatchUpdate = $this->buildSnatchUpdate($upthis, $downthis, $snatchTimeColumn, $snatchTimeIncrement, $leechTimeNoSeederIncrement);

        if ($this->event === 'completed') {
            $peerUpdate['finishedat'] = TIMENOW;
            $snatchUpdate['completedat'] = $this->dt;
            $snatchUpdate['finished'] = 'yes';
            $this->torrentUpdate['times_completed'] = NexusDB::raw('times_completed + 1');
        }

        $peerUpdate = array_merge($peerUpdate, $peerIPUpdate);

        $peerAffected = NexusDB::table('peers')->where('id', (int) $this->self['id'])->update($peerUpdate);

        if ($peerAffected > 0) {
            if ($this->seeder !== $this->self['seeder']) {
                if ($this->seeder === 'yes') {
                    $this->torrentUpdate['seeders'] = NexusDB::raw('seeders + 1');
                    $this->torrentUpdate['leechers'] = NexusDB::raw('leechers - 1');
                } else {
                    $this->torrentUpdate['seeders'] = NexusDB::raw('seeders - 1');
                    $this->torrentUpdate['leechers'] = NexusDB::raw('leechers + 1');
                }
            }

            if (!empty($this->snatchInfo)) {
                NexusDB::table('snatched')->where('id', (int) $this->snatchInfo['id'])->update($snatchUpdate);
                do_action('snatched_saved', $this->torrent, $this->snatchInfo);
            }
        }
    }


    private function processStopped(int $upthis, int $downthis, string $snatchTimeColumn, int $snatchTimeIncrement, int $leechTimeNoSeederIncrement): void
    {
        $snatchUpdate = $this->buildSnatchUpdate($upthis, $downthis, $snatchTimeColumn, $snatchTimeIncrement, $leechTimeNoSeederIncrement);

        $deleted = NexusDB::table('peers')->where('id', (int) $this->self['id'])->delete();
        if ($deleted) {
            $this->torrentUpdate[$this->self['seeder'] === 'yes' ? 'seeders' : 'leechers'] = NexusDB::raw(
                $this->self['seeder'] === 'yes' ? 'seeders - 1' : 'leechers - 1'
            );

            if (!empty($this->snatchInfo)) {
                NexusDB::table('snatched')->where('id', (int) $this->snatchInfo['id'])->update($snatchUpdate);
            }
        }
    }


    private function enforceWaitAndSlotLimitsForNewPeer(): void
    {
        if ((int) $this->user['class'] >= (int) User::CLASS_VIP) {
            return;
        }

        $ratio = ($this->user['downloaded'] > 0) ? ($this->user['uploaded'] / $this->user['downloaded']) : 1;
        $gigs  = $this->user['downloaded'] / (1024 * 1024 * 1024);

        if ($gigs <= 10) {
            return;
        }

        if (\App\Support\Config\SiteConfig::current()->main->waitSystem() && is_array($this->torrent)) {
            $elapsed = TIMENOW - (int) ($this->torrent['ts'] ?? 0);
            $wait = match (true) {
                $ratio < 0.4  => 24,
                $ratio < 0.5  => 12,
                $ratio < 0.6  => 6,
                $ratio < 0.8  => 3,
                default       => 0,
            };

            if ($elapsed < $wait) {
                $faqUrl = Url::schemeAndHost(true) . '/faq.php#id46';
                $this->warn(
                    'Your ratio is too low! You need to wait ' . mkprettytime($wait * 3600 - $elapsed) . ' to start, please read ' . $faqUrl . ' for details',
                    $elapsed
                );
            }
        }

        if (\App\Support\Config\SiteConfig::current()->main->maxDlSystem()) {
            $max = match (true) {
                $ratio < 0.5  => 1,
                $ratio < 0.65 => 2,
                $ratio < 0.8  => 3,
                $ratio < 0.95 => 4,
                default       => 0,
            };

            if ($max > 0) {
                $leechingCount = NexusDB::table('peers')
                    ->where('userid', $this->userId)
                    ->where('seeder', 'no')
                    ->count();

                if ($leechingCount >= $max) {
                    throw TrackerException::failure(
                        "Your slot limit is reached! You may at most download $max torrents at the same time, please read " . Url::schemeAndHost(true) . '/faq.php#id66 for details'
                    );
                }
            }
        }
    }


    private function buildSnatchUpdate(int $upthis, int $downthis, string $snatchTimeColumn, int $snatchTimeIncrement, int $leechTimeNoSeederIncrement): array
    {
        $snatchUpdate = [
            'uploaded'    => NexusDB::raw('uploaded + ' . $upthis),
            'downloaded'  => NexusDB::raw('downloaded + ' . $downthis),
            'to_go'       => $this->left,
            $snatchTimeColumn => NexusDB::raw("{$snatchTimeColumn} + " . $snatchTimeIncrement),
            'last_action' => $this->dt,
        ];

        if ($leechTimeNoSeederIncrement > 0) {
            $snatchUpdate['leech_time_no_seeder'] = NexusDB::raw('leech_time_no_seeder + ' . $leechTimeNoSeederIncrement);
        }

        return $snatchUpdate;
    }

    private function warn(string $message, int $interval = 7200): void
    {
        if ($this->event !== null && in_array($this->event, ['completed', 'stopped'], true)) {
            throw TrackerException::failure($message);
        }

        $torrentValues = $this->torrent;

        $base = [
            'interval'     => MIN_ANNOUNCE_WAIT_SECOND,
            'min interval' => MIN_ANNOUNCE_WAIT_SECOND,
            'complete'     => (int) ($torrentValues['seeders'] ?? 0),
            'incomplete'   => (int) ($torrentValues['leechers'] ?? 0),
            'downloaded'   => (int) ($torrentValues['times_completed'] ?? 0),
            'peers'        => $this->dto->compact ? '' : [],
        ];
        if ($this->dto->compact) {
            $base['peers6'] = '';
        }

        throw new TrackerWarningException($message, $base, $interval);
    }

}
