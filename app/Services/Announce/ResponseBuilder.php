<?php

declare(strict_types=1);

namespace App\Services\Announce;

use App\DTOs\AnnounceRequestDto;
use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use App\Support\Strings;
use Nexus\Database\NexusDB;

final class ResponseBuilder
{
    private int $realAnnounceInterval = MIN_ANNOUNCE_WAIT_SECOND;

    public function __construct(
        private readonly AnnounceRequestDto $dto,
        private readonly ?array $torrent = null,
        private readonly int $baseInterval = MIN_ANNOUNCE_WAIT_SECOND,
    ) {}

    public function withTorrent(array $torrent): self
    {
        return new self($this->dto, $torrent, $this->baseInterval);
    }

    /**
     * @return InitialResponseResult
     */
    public function initial(int $torrentId): InitialResponseResult
    {
        $announceInterval = (int) \App\Support\Config\SiteConfig::current()->main->announceInterval(1800);
        $annInterTwoAge = (int) \App\Support\Config\SiteConfig::current()->main->annintertwoage(0);
        $annInterTwo = (int) \App\Support\Config\SiteConfig::current()->main->annintertwo(0);
        $annInterThreeAge = (int) \App\Support\Config\SiteConfig::current()->main->anninterthreeage(0);
        $annInterThree = (int) \App\Support\Config\SiteConfig::current()->main->anninterthree(0);
        $autocleanIntervalOne = (int) \App\Support\Config\SiteConfig::current()->main->autocleanIntervalOne(900);

        $begin = (int) ($announceInterval / 2);
        $end1 = (int) (($announceInterval + $annInterTwo) / 2);
        $end2 = (int) (($annInterTwo + $annInterThree) / 2);

        $this->realAnnounceInterval = mt_rand($begin, $end1);
        if ($annInterThreeAge && $annInterThree > MIN_ANNOUNCE_WAIT_SECOND && (TIMENOW - (int) ($this->torrent['ts'] ?? 0)) >= ($annInterThreeAge * 86400)) {
            $this->realAnnounceInterval = mt_rand($end2, $annInterThree);
        } elseif ($annInterTwoAge && $annInterTwo > MIN_ANNOUNCE_WAIT_SECOND && (TIMENOW - (int) ($this->torrent['ts'] ?? 0)) >= ($annInterTwoAge * 86400)) {
            $this->realAnnounceInterval = mt_rand($end1, $end2);
        }

        $counts = $this->countPeers($torrentId) ?: (object) ['seeders' => 0, 'leechers' => 0];

        $response = [
            'interval'     => $this->realAnnounceInterval,
            'min interval' => MIN_ANNOUNCE_WAIT_SECOND,
            'complete'     => (int) ($counts->seeders ?? 0),
            'incomplete'   => (int) ($counts->leechers ?? 0),
            'downloaded'   => (int) (($this->torrent['times_completed'] ?? 0)),
            'peers'        => $this->dto->compact ? '' : [],
            'peers6'       => '',
        ];

        return new InitialResponseResult($response, $this->realAnnounceInterval, $autocleanIntervalOne);
    }

    public function peerList(int $torrentId, int $userId, string $seeder): array
    {
        $counts = $this->countPeers($torrentId) ?: (object) ['seeders' => 0, 'leechers' => 0];
        $complete = (int) ($counts->seeders ?? 0);
        $incomplete = (int) ($counts->leechers ?? 0);
        $downloaded = (int) (NexusDB::table('torrents')->where('id', $torrentId)->value('times_completed') ?? 0);

        $peerIdBinary = $this->dto->peerId->toBinary();

        $peers = $this->dto->compact ? '' : [];
        $peers6 = '';

        if ($this->dto->event !== 'stopped') {
            $query = NexusDB::table('peers')
                ->where('torrent', $torrentId)
                ->where(function ($q) use ($peerIdBinary, $userId) {
                    $q->where('peer_id', '!=', $peerIdBinary)
                        ->orWhere('userid', '!=', $userId);
                })
                ->limit($this->dto->numWant);

            if ($seeder === 'yes') {
                $query->where('seeder', 'no');
            }

            foreach ($query->inRandomOrder()->get() as $row) {
                if ($this->dto->compact) {
                    if (!empty($row->ipv4)) {
                        $peers .= inet_pton($row->ipv4) . pack('n', (int) $row->port);
                    }
                    if (!empty($row->ipv6)) {
                        $peers6 .= inet_pton($row->ipv6) . pack('n', (int) $row->port);
                    }
                } else {
                    $ip = !empty($row->ipv4) ? $row->ipv4 : ($row->ipv6 ?? '');
                    $peers[] = [
                        'peer id' => Strings::padHash($row->peer_id),
                        'ip'      => $ip,
                        'port'    => (int) $row->port,
                    ];
                }
            }
        }

        $repDict = [
            'interval'     => $this->realAnnounceInterval,
            'min interval' => MIN_ANNOUNCE_WAIT_SECOND,
            'complete'     => $complete,
            'incomplete'   => $incomplete,
            'downloaded'   => $downloaded,
            'peers'        => $peers,
        ];

        if ($this->dto->compact) {
            $repDict['peers6'] = $peers6;
        }

        return $repDict;
    }

    public function countPeers(int $torrentId): ?\stdClass
    {
        return NexusDB::table('peers')
            ->where('torrent', $torrentId)
            ->selectRaw("SUM(CASE WHEN seeder = 'yes' THEN 1 ELSE 0 END) as seeders, SUM(CASE WHEN seeder = 'no' THEN 1 ELSE 0 END) as leechers")
            ->first();
    }

    public function warn(string $message, int $interval = 7200): never
    {
        if ($this->dto->event !== null && in_array($this->dto->event, ['completed', 'stopped'], true)) {
            throw TrackerException::failure($message);
        }

        $torrentValues = is_array($this->torrent) ? $this->torrent : [];

        $base = [
            'interval'     => $this->baseInterval,
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
