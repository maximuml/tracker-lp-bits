<?php

namespace App\Services;

use App\DTOs\AnnounceRequestDto;
use App\Exceptions\ClientNotAllowedException;
use App\Enums\HitAndRunMode;
use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use App\Models\HitAndRun;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\AgentAllowRepository;
use App\Repositories\CleanupRepository;
use App\Repositories\IpLogRepository;
use App\Repositories\RequireSeedTorrentRepository;
use App\Repositories\TorrentRepository;
use App\Repositories\UserRepository;
use App\Services\Announce\PeerLifecycle;
use App\Services\Announce\RateLimiter;
use App\Support\LegacyDb;
use App\Support\Strings;
use App\Support\SupportContext;
use App\Support\Tracker;
use App\Support\Url;
use App\Utils\MsgAlert;
use Illuminate\Http\Request;
use Nexus\Database\NexusDB;

final class AnnounceService
{
    private Request $request;
    private AnnounceRequestDto $dto;

    /** @var array<string, mixed> */
    private array $params;

    /** @var array<string, mixed> */
    private array $user = [];

    /** @var array<string, mixed>|null */
    private ?array $torrent = null;

    /** @var array<string, mixed>|null */
    private ?array $self = null;

    /** @var array<string, mixed>|false */
    private array|false $snatchInfo = false;

    /** @var array<string, mixed> */
    private array $userUpdate = [];

    /** @var array<string, mixed> */
    private array $torrentUpdate = [];

    private int $uploadedIncrementForUser = 0;
    private int $downloadedIncrementForUser = 0;

    private string $ip = '';
    private string $ipv4 = '';
    private string $ipv6 = '';
    private string $agent = '';
    private bool $isIPSeedBox = false;
    private bool $isDonor = false;
    private int $clientFamilyId = 0;
    private bool $isReAnnounce = false;
    private int $realAnnounceInterval = MIN_ANNOUNCE_WAIT_SECOND;
    private string $dt = '';
    private int $userId = 0;
    private int $torrentId = 0;
    private string $peerId = '';
    private string $infoHash = '';
    private string $seeder = 'no';
    private int $left = 0;
    private ?string $event = null;
    private int $rsize = 50;
    private bool $compact = false;
    private int $announceWait = MIN_ANNOUNCE_WAIT_SECOND;
    private int $autocleanIntervalOne = 900;

    public function handle(Request $request, array $params): array
    {
        $this->request = $request;
        $this->dto = AnnounceRequestDto::fromRequest($request, $params);
        $this->params = $this->dto->toParams();
        $this->agent = $this->dto->userAgent;
        $this->ip = $this->dto->ip;
        $this->ipv4 = $this->dto->ipv4 ?? '';
        $this->ipv6 = $this->dto->ipv6 ?? '';
        $this->peerId = $this->dto->peerId->toBinary();
        $this->infoHash = $this->dto->infoHash->toBinary();
        $this->left = $this->dto->left;
        $this->event = $this->dto->event;
        $this->compact = $this->dto->compact;
        $this->rsize = $this->dto->numWant;
        $this->seeder = $this->dto->isSeeder() ? 'yes' : 'no';
        $this->dt = date('Y-m-d H:i:s', TIMENOW);

        $this->blockBrowser();
        $this->checkPort();
        $rateLimitResult = (new RateLimiter())->check($this->dto);
        $this->isReAnnounce = $rateLimitResult->isReAnnounce;
        $this->authenticateUser();
        $this->checkClient();
        $this->loadTorrent();
        $repDict = $this->buildInitialRepDict();

        if ($this->isReAnnounce) {
            do_log('[ANNOUNCE] re-announce, return early.');
            return $repDict;
        }

        $this->userId = (int) $this->user['id'];
        $this->torrentId = (int) $this->torrent['id'];

        $this->detectSeedBox();

        $peerLifecycle = new PeerLifecycle($this->dto, $this->torrent, $this->user, $this->isIPSeedBox, $this->dt);
        $this->self = $peerLifecycle->findSelf();
        $this->loadSnatchInfo();
        $peerLifecycle->setSnatchInfo($this->snatchInfo ?: false);

        $this->validateAnnounceTime();
        $this->handlePaidTorrent();

        [$upthis, $downthis, $snatchTimeColumn, $snatchTimeIncrement, $leechTimeNoSeederIncrement] = $this->computeTraffic();
        $this->checkSeedBoxSpeed($upthis);
        $this->cheaterCheck($upthis, $downthis);

        $response = NexusDB::transaction(function () use ($peerLifecycle, $upthis, $downthis, $snatchTimeColumn, $snatchTimeIncrement, $leechTimeNoSeederIncrement) {
            return $this->process($peerLifecycle, $upthis, $downthis, $snatchTimeColumn, $snatchTimeIncrement, $leechTimeNoSeederIncrement);
        });

        $this->postProcess();

        return $response;
    }

    private function blockBrowser(): void
    {
        if (preg_match('/^Mozilla/', $this->agent)
            || preg_match('/^Opera/', $this->agent)
            || preg_match('/^Links/', $this->agent)
            || preg_match('/^Lynx/', $this->agent)
        ) {
            throw TrackerException::failure('Browser access blocked!');
        }

        $https = $this->request->server('HTTPS');
        if ($https !== null && $https !== 'on') {
            $headers = $this->request->headers->all();
            if (isset($headers['cookie']) || isset($headers['accept-language']) || isset($headers['accept-charset'])) {
                throw TrackerException::failure('Anti-Cheater: You cannot use this agent');
            }
        }
    }

    private function checkPort(): void
    {
        $port = (int) $this->params['port'];

        if ($port <= 0 || $port > 0xffff) {
            $this->warn('invalid port');
        }

        if ($this->portBlacklisted($port)) {
            $this->warn("Port $port is blacklisted.");
        }
    }

    private function portBlacklisted(int $port): bool
    {
        return match (true) {
            $port >= 411 && $port <= 413 => true,
            $port >= 6881 && $port <= 6889 => true,
            $port == 1214 => true,
            $port >= 6346 && $port <= 6347 => true,
            $port == 4662 => true,
            $port == 6699 => true,
            default => false,
        };
    }

    private function authenticateUser(): void
    {
        $passkey = $this->params['passkey'];

        $this->user = NexusDB::remember("user_passkey_{$passkey}_content", 3600, function () use ($passkey) {
            $user = User::query()
                ->select([
                    'id', 'username', 'downloadpos', 'enabled', 'uploaded', 'downloaded',
                    'class', 'parked', 'clientselect', 'showclienterror', 'passkey',
                    'donor', 'donoruntil', 'seedbonus', 'tracker_url_id',
                ])
                ->where('passkey', $passkey)
                ->first();

            return $user ? $user->toArray() : [];
        });

        if (!$this->user) {
            NexusDB::redis()->set("passkey_invalid:{$passkey}", TIMENOW, ['ex' => 24 * 3600]);
            throw TrackerException::failure('Invalid passkey! Re-download the .torrent from ' . Url::schemeAndHost(true));
        }

        $this->userId = (int) $this->user['id'];
        SupportContext::setUser($this->user);

        if ($this->user['enabled'] === 'no') {
            throw TrackerException::failure('Your account is disabled!');
        }
        if ($this->user['parked'] === 'yes') {
            throw TrackerException::failure('Your account is parked! (Read the FAQ)');
        }
        if ($this->user['downloadpos'] === 'no') {
            throw TrackerException::failure('Your downloading privileges have been disabled! (Read the rules)');
        }

        $this->isDonor = is_donor($this->user);
        $this->user['__is_donor'] = $this->isDonor;

        $this->checkTrackerUrl();
    }

    private function checkTrackerUrl(): void
    {
        $trackerUrl = Tracker::schemaAndHost((int) $this->user['tracker_url_id'], true);
        $currentUrl = Url::schemeAndHost();

        if (!str_contains($trackerUrl, $currentUrl)) {
            do_log("announce check tracker url, trackerUrl: {$trackerUrl} does not contains: {$currentUrl}");
            $this->warn("you should announce to: {$trackerUrl}");
        }
    }

    private function checkClient(): void
    {
        $agentAllowRep = new AgentAllowRepository();
        $clicheckRes = '';

        try {
            $checkClientResult = $agentAllowRep->checkClient($this->peerId, $this->agent);
            $this->clientFamilyId = (int) $checkClientResult->id;
        } catch (ClientNotAllowedException $exception) {
            $clicheckRes = $exception->getMessage();
        }

        if ($clicheckRes) {
            if ($this->user['showclienterror'] === 'no') {
                User::query()->where('id', $this->userId)->update(['showclienterror' => 'yes']);
                NexusDB::cache_del("user_passkey_{$this->params['passkey']}_content");
            }
            throw TrackerException::failure($clicheckRes);
        }

        if ($this->user['showclienterror'] === 'yes') {
            $this->userUpdate['showclienterror'] = 'no';
            NexusDB::cache_del("user_passkey_{$this->params['passkey']}_content");
        }
    }

    private function loadTorrent(): void
    {
        $infoHashHex = bin2hex($this->infoHash);

        $this->torrent = NexusDB::remember("torrent_hash_{$this->infoHash}_content", 350, function () {
            $tsField = NexusDB::unixTimestampField('added');
            $torrent = NexusDB::table('torrents')
                ->leftJoin('categories', 'torrents.category', '=', 'categories.id')
                ->select([
                    'torrents.id', 'torrents.size', 'torrents.owner', 'torrents.sp_state',
                    'torrents.seeders', 'torrents.leechers', 'torrents.times_completed',
                    'torrents.banned', 'torrents.hr', 'torrents.approval_status', 'torrents.price',
                    'torrents.visible', 'torrents.last_action', 'categories.mode',
                    NexusDB::raw("{$tsField} AS ts"),
                ])
                ->where('torrents.info_hash', $this->infoHash)
                ->first();

            return $torrent ? (array) $torrent : null;
        });

        if (!$this->torrent) {
            do_log('[TORRENT NOT EXISTS] info_hash: ' . $infoHashHex);
            NexusDB::redis()->set('torrent_not_exists:' . $this->infoHash, TIMENOW, ['ex' => 24 * 3600]);
            throw TrackerException::failure('torrent not registered with this tracker');
        }

        $this->torrentId = (int) $this->torrent['id'];

        if ($this->torrent['banned'] === 'yes' && !user_can('seebanned', false, $this->userId)) {
            throw TrackerException::failure('torrent banned');
        }

        if ($this->torrent['approval_status'] != Torrent::APPROVAL_STATUS_ALLOW
            && get_setting('torrent.approval_status_none_visible') == 'no'
            && !user_can('seebanned', false, $this->userId)
        ) {
            throw TrackerException::failure('torrent review not approved');
        }

        if ($this->left > (int) $this->torrent['size']) {
            (new UserRepository())->updateDownloadPrivileges(null, $this->userId, 'no', 'fake_announce');
            do_log(sprintf('fake announce, user: %s, torrent: %s, announce left: %s > size: %s', $this->userId, $this->torrentId, $this->left, $this->torrent['size']), 'warn');
            $this->warn('fake announce', 300);
        }
    }

    private function buildInitialRepDict(): array
    {
        $announceInterval = (int) get_setting('main.announce_interval', 1800);
        $annInterTwoAge = (int) get_setting('main.annintertwoage', 0);
        $annInterTwo = (int) get_setting('main.annintertwo', 0);
        $annInterThreeAge = (int) get_setting('main.anninterthreeage', 0);
        $annInterThree = (int) get_setting('main.anninterthree', 0);
        $this->autocleanIntervalOne = (int) get_setting('main.autoclean_interval_one', 900);

        $begin = (int) ($announceInterval / 2);
        $end1 = (int) (($announceInterval + $annInterTwo) / 2);
        $end2 = (int) (($annInterTwo + $annInterThree) / 2);

        $this->realAnnounceInterval = mt_rand($begin, $end1);
        if ($annInterThreeAge && $annInterThree > $this->announceWait && (TIMENOW - (int) $this->torrent['ts']) >= ($annInterThreeAge * 86400)) {
            $this->realAnnounceInterval = mt_rand($end2, $annInterThree);
        } elseif ($annInterTwoAge && $annInterTwo > $this->announceWait && (TIMENOW - (int) $this->torrent['ts']) >= ($annInterTwoAge * 86400)) {
            $this->realAnnounceInterval = mt_rand($end1, $end2);
        }

        if ($this->torrentId > 0) {
            $counts = $this->countPeers() ?: (object) ['seeders' => 0, 'leechers' => 0];
        } else {
            $counts = (object) [
                'seeders' => (int) ($this->torrent['seeders'] ?? 0),
                'leechers' => (int) ($this->torrent['leechers'] ?? 0),
            ];
        }

        return [
            'interval'     => $this->realAnnounceInterval,
            'min interval' => MIN_ANNOUNCE_WAIT_SECOND,
            'complete'     => (int) ($counts->seeders ?? 0),
            'incomplete'   => (int) ($counts->leechers ?? 0),
            'downloaded'   => (int) ($this->torrent['times_completed'] ?? 0),
            'peers'        => $this->compact ? '' : [],
            'peers6'       => '',
        ];
    }

    private function loadSnatchInfo(): void
    {
        if ($this->self !== null) {
            $this->snatchInfo = LegacyDb::snatchInfo($this->torrentId, $this->userId);
        }
    }

    private function validateAnnounceTime(): void
    {
        if ($this->self !== null && empty($this->event) && (int) $this->self['prevts'] > (TIMENOW - $this->announceWait)) {
            $this->warn('There is a minimum announce time of ' . $this->announceWait . ' seconds', $this->announceWait);
        }
    }

    private function detectSeedBox(): void
    {
        if (get_setting('seed_box.enabled') != 'yes') {
            return;
        }

        if ($this->ipv4 && isIPSeedBox($this->ipv4, $this->userId)) {
            $this->isIPSeedBox = true;
        }
        if (!$this->isIPSeedBox && $this->ipv6 && isIPSeedBox($this->ipv6, $this->userId)) {
            $this->isIPSeedBox = true;
        }
    }

    private function handlePaidTorrent(): void
    {
        if ($this->seeder === 'yes'
            || !isset($this->user['seedbonus'])
            || !isset($this->torrent['price'])
            || (int) $this->torrent['price'] <= 0
            || (int) $this->torrent['owner'] == $this->userId
            || get_setting('torrent.paid_torrent_enabled') != 'yes'
        ) {
            return;
        }

        $torrentRep = new TorrentRepository();
        $buyStatus = $torrentRep->getBuyStatus($this->userId, $this->torrentId);
        do_log("user: {$this->userId} buy torrent: {$this->torrentId}, status: {$buyStatus}");

        if ($buyStatus > 0) {
            do_log(sprintf('user: %s buy torrent： %s fail count: %s', $this->userId, $this->torrentId, $buyStatus), 'error');
            if ($buyStatus > 3) {
                MsgAlert::getInstance()->add(
                    'announce_paid_torrent_too_many_times',
                    time() + 86400,
                    'announce to paid torrent and fail too many times, please make sure you have enough bonus!',
                    '',
                    'black'
                );
            }
            if ($buyStatus > 10) {
                (new UserRepository())->updateDownloadPrivileges(null, $this->userId, 'no', 'announce_paid_torrent_too_many_times');
            }
            \Nexus\Nexus::dispatchQueueJob(new \App\Jobs\BuyTorrent($this->userId, $this->torrentId));
            $torrentRep->addBuyFailCache($this->userId, $this->torrentId);
            $this->warn('purchase in progress, please try again later, and make sure you have enough bonus', 300);
        }

        if ($buyStatus == TorrentRepository::BUY_STATUS_UNKNOWN) {
            \Nexus\Nexus::dispatchQueueJob(new \App\Jobs\BuyTorrent($this->userId, $this->torrentId));
            $this->warn('purchase started, please wait', 300);
        }
    }

    private function computeTraffic(): array
    {
        if ($this->self === null) {
            $this->uploadedIncrementForUser = 0;
            $this->downloadedIncrementForUser = 0;

            return [0, 0, null, 0, 0];
        }

        $upthis = max(0, (int) \bcsub((string) $this->params['uploaded'], (string) $this->self['uploaded']));
        $downthis = max(0, (int) \bcsub((string) $this->params['downloaded'], (string) $this->self['downloaded']));
        $snatchTimeColumn = $this->self['seeder'] === 'yes' ? 'seedtime' : 'leechtime';
        $snatchTimeIncrement = max(0, (int) $this->self['announcetime']);

        $leechTimeNoSeederIncrement = 0;
        if ((int) $this->torrent['seeders'] <= 0 && $this->seeder === 'no' && $snatchTimeIncrement > 0) {
            $leechTimeNoSeederIncrement = $snatchTimeIncrement;
        }

        if ($upthis > 0 || $downthis > 0) {
            $queries = $this->params;
            $queries['ip'] = $this->ip;
            $promotionInfo = apply_filter('torrent_promotion', $this->torrent);
            $dataTraffic = getDataTraffic($this->torrent, $queries, $this->user, $this->self, $this->snatchInfo ?: [], $promotionInfo);
            $this->uploadedIncrementForUser = (int) $dataTraffic['uploaded_increment_for_user'];
            $this->downloadedIncrementForUser = (int) $dataTraffic['downloaded_increment_for_user'];
        } else {
            $this->uploadedIncrementForUser = 0;
            $this->downloadedIncrementForUser = 0;
        }

        return [$upthis, $downthis, $snatchTimeColumn, $snatchTimeIncrement, $leechTimeNoSeederIncrement];
    }

    private function checkSeedBoxSpeed(int $upthis): void
    {
        if ($this->self === null || $this->self['announcetime'] <= 0 || get_setting('seed_box.enabled') != 'yes') {
            return;
        }

        if ((int) $this->user['class'] >= (int) User::CLASS_VIP || $this->isDonor || $this->isIPSeedBox) {
            return;
        }

        $notSeedBoxMaxSpeedMbps = (float) get_setting('seed_box.not_seed_box_max_speed', 0);
        if ($notSeedBoxMaxSpeedMbps <= 0) {
            return;
        }

        $upSpeedMbps = number_format(($upthis / $this->self['announcetime'] / 1024 / 1024) * 8, 2);
        do_log("notSeedBoxMaxSpeedMbps: {$notSeedBoxMaxSpeedMbps}, upSpeedMbps: {$upSpeedMbps}");

        if ($upSpeedMbps > $notSeedBoxMaxSpeedMbps) {
            (new UserRepository())->updateDownloadPrivileges(null, $this->userId, 'no', 'upload_over_speed');
            do_log("user: {$this->userId} downloading privileges have been disabled! (over speed), upSpeedMbps: {$upSpeedMbps} > notSeedBoxMaxSpeedMbps: {$notSeedBoxMaxSpeedMbps}", 'error');
            $this->warn('Your downloading privileges have been disabled! (over speed)', 300);
        }
    }

    private function cheaterCheck(int $upthis, int $downthis): void
    {
        if ($this->self === null || $this->self['announcetime'] <= 10) {
            return;
        }

        $cheaterdetSecurity = (int) get_setting('security.cheaterdet', 0);
        if (!$cheaterdetSecurity) {
            return;
        }

        $nodetectSecurity = (int) get_setting('security.nodetect', 0);
        if ((int) $this->user['class'] >= $nodetectSecurity) {
            return;
        }

        $this->doCheaterCheck($upthis, $downthis, (int) $this->torrent['seeders'], (int) $this->torrent['leechers'], $cheaterdetSecurity);
    }

    private function doCheaterCheck(int $uploaded, int $downloaded, int $seeders, int $leechers, int $cheaterdetSecurity): void
    {
        $time = $this->dt;
        $upspeed = $uploaded > 0 ? $uploaded / $this->self['announcetime'] : 0;
        $mustBeCheaterSpeed = (int) get_setting('system.maximum_upload_speed', 8000) * 1024 * 1024 / 8;
        $mayBeCheaterSpeed = $mustBeCheaterSpeed / 2;

        if ($uploaded > 1073741824 && $upspeed > ($mustBeCheaterSpeed / $cheaterdetSecurity)) {
            NexusDB::transaction(function () use ($time, $uploaded, $downloaded, $seeders, $leechers, $upspeed) {
                $comment = 'User account was automatically disabled by system';
                NexusDB::table('cheaters')->insert([
                    'added'       => $time,
                    'userid'      => $this->userId,
                    'torrentid'   => $this->torrentId,
                    'uploaded'    => $uploaded,
                    'downloaded'  => $downloaded,
                    'anctime'     => $this->self['announcetime'],
                    'seeders'     => $seeders,
                    'leechers'    => $leechers,
                    'comment'     => $comment,
                ]);
                NexusDB::table('users')->where('id', $this->userId)->update(['enabled' => 'no']);
                \App\Models\UserBanLog::query()->insert([
                    'uid'      => $this->userId,
                    'username' => $this->user['username'],
                    'reason'   => "$comment(Upload speed:" . mksize($upspeed) . '/s)',
                ]);
            });

            throw TrackerException::failure('We believe you\'re trying to cheat. And your account is disabled.');
        }

        if ($uploaded > 1073741824 && $upspeed > ($mayBeCheaterSpeed / $cheaterdetSecurity)) {
            $this->insertOrUpdateCheater($time, $uploaded, $downloaded, $seeders, $leechers, 'Abnormally high uploading rate');
            return;
        }

        if ($cheaterdetSecurity > 1 && $uploaded > 1073741824 && $upspeed > 1048576 && $leechers < (2 * $cheaterdetSecurity)) {
            $this->insertOrUpdateCheater($time, $uploaded, $downloaded, $seeders, $leechers, 'User is uploading fast when there is few leechers');
            return;
        }

        if ($cheaterdetSecurity > 1 && $uploaded > 10485760 && $upspeed > 102400 && $leechers == 0) {
            $this->insertOrUpdateCheater($time, $uploaded, $downloaded, $seeders, $leechers, 'User is uploading when there is no leecher');
        }
    }

    private function insertOrUpdateCheater(string $time, int $uploaded, int $downloaded, int $seeders, int $leechers, string $comment): void
    {
        $secs = 24 * 60 * 60;
        $dt = date('Y-m-d H:i:s', strtotime($this->dt) - $secs);

        $cheaterId = NexusDB::table('cheaters')
            ->where('userid', $this->userId)
            ->where('torrentid', $this->torrentId)
            ->where('added', '>', $dt)
            ->value('id');

        if (empty($cheaterId)) {
            NexusDB::table('cheaters')->insert([
                'added'      => $time,
                'userid'     => $this->userId,
                'torrentid'  => $this->torrentId,
                'uploaded'   => $uploaded,
                'downloaded' => $downloaded,
                'anctime'    => $this->self['announcetime'],
                'seeders'    => $seeders,
                'leechers'   => $leechers,
                'hit'        => 1,
                'comment'    => $comment,
            ]);
        } else {
            NexusDB::table('cheaters')->where('id', $cheaterId)->update([
                'hit'       => NexusDB::raw('hit + 1'),
                'dealtwith' => 0,
            ]);
        }
    }

    private function process(PeerLifecycle $peerLifecycle, int $upthis, int $downthis, ?string $snatchTimeColumn, int $snatchTimeIncrement, int $leechTimeNoSeederIncrement): array
    {
        $result = $peerLifecycle->process($upthis, $downthis, $snatchTimeColumn, $snatchTimeIncrement, $leechTimeNoSeederIncrement);
        $this->self = $result->self;
        $this->snatchInfo = $result->snatchInfo;
        $this->torrentUpdate = $result->torrentUpdate;

        $this->handleHitAndRun();
        $this->applyUserUpdate();

        if (!empty($this->torrentUpdate)) {
            $this->torrentUpdate['visible'] = 'yes';
            $this->torrentUpdate['last_action'] = $this->dt;
            NexusDB::table('torrents')->where('id', $this->torrentId)->update($this->torrentUpdate);
            do_log('[ANNOUNCE_UPDATE_TORRENT], ' . nexus_json_encode($this->torrentUpdate));
        }

        return $this->buildPeerListResponse();
    }

    private function handleHitAndRun(): void
    {
        if (($this->left <= 0 && $this->event !== 'completed')
            || (int) $this->user['class'] >= (int) User::CLASS_VIP
            || $this->isDonor
            || empty($this->torrent['mode'])
        ) {
            return;
        }

        $this->snatchInfo = LegacyDb::snatchInfo($this->torrentId, $this->userId);
        if (!$this->snatchInfo) {
            return;
        }

        $hrMode = HitAndRunMode::fromStringSafe(
            is_string($mode = HitAndRun::getConfig('mode', $this->torrent['mode'])) ? $mode : null
        );
        do_log("[HR_LOG] user: {$this->userId}, torrent: {$this->torrentId}, hrMode: {$hrMode->value}");

        if (! $hrMode->isGlobal() && ($hrMode !== HitAndRunMode::MANUAL || $this->torrent['hr'] != Torrent::HR_YES)) {
            do_log("[HR_LOG] user: {$this->userId}, torrent: {$this->torrentId}, hrMode: {$hrMode->value}, not match", 'debug');
            return;
        }

        $hrCacheKey = HitAndRun::getCacheKey($this->userId, $this->torrentId);
        $hrExists = NexusDB::remember($hrCacheKey, mt_rand(86400, 86400 * 3), function () {
            $record = HitAndRun::query()->where('uid', $this->userId)->where('torrent_id', $this->torrentId)->first();
            return $record ? $record->toJson() : null;
        });

        if ($hrExists) {
            do_log("[HR_LOG] user: {$this->userId}, torrent: {$this->torrentId}, already exists", 'debug');
            return;
        }

        $includeRate = (float) HitAndRun::getConfig('include_rate', $this->torrent['mode']);
        $requiredDownloaded = (int) $this->torrent['size'] * $includeRate;

        do_log("[HR_LOG] user: {$this->userId}, torrent: {$this->torrentId}, includeRate: {$includeRate}, requiredDownloaded: {$requiredDownloaded}, snatchDownloaded: {$this->snatchInfo['downloaded']}");

        if ((int) $this->snatchInfo['downloaded'] >= $requiredDownloaded) {
            $hrRecord = [
                'uid'         => $this->userId,
                'torrent_id'  => $this->torrentId,
                'snatched_id' => $this->snatchInfo['id'],
                'created_at'  => $this->dt,
                'updated_at'  => $this->dt,
            ];

            $affectedRows = NexusDB::table('hit_and_runs')->insertOrIgnore($hrRecord);
            do_log("[HR_LOG] user: {$this->userId}, torrent: {$this->torrentId}, total downloaded: {$this->snatchInfo['downloaded']} >= required: {$requiredDownloaded}, [INSERT_H&R], affectedRows: {$affectedRows}");

            if ($affectedRows > 0) {
                $hitAndRunRecord = HitAndRun::query()->where('uid', $this->userId)->where('torrent_id', $this->torrentId)->first();
                if ($hitAndRunRecord) {
                    NexusDB::table('snatched')->where('id', (int) $this->snatchInfo['id'])->update(['hit_and_run_id' => $hitAndRunRecord->id]);
                    fire_event(\App\Enums\ModelEventEnum::HIT_AND_RUN_CREATED, $hitAndRunRecord);
                }
            }
        } else {
            do_log("[HR_LOG] user: {$this->userId}, torrent: {$this->torrentId}, total downloaded: {$this->snatchInfo['downloaded']} < required: {$requiredDownloaded}", 'debug');
        }
    }

    private function applyUserUpdate(): void
    {
        if ($this->clientFamilyId != 0 && $this->clientFamilyId != (int) $this->user['clientselect']) {
            $this->userUpdate['clientselect'] = $this->clientFamilyId;
        }

        $this->userUpdate['last_announce_at'] = $this->dt;

        if ($this->uploadedIncrementForUser > 0) {
            $this->userUpdate['uploaded'] = NexusDB::raw('uploaded + ' . $this->uploadedIncrementForUser);
        }
        if ($this->downloadedIncrementForUser > 0) {
            $this->userUpdate['downloaded'] = NexusDB::raw('downloaded + ' . $this->downloadedIncrementForUser);
        }

        if ((int) $this->user['class'] === (int) User::CLASS_VIP) {
            unset($this->userUpdate['downloaded']);
        }

        if (!empty($this->userUpdate) && $this->userId) {
            User::query()->where('id', $this->userId)->update($this->userUpdate);
            do_log('[ANNOUNCE_UPDATE_USER], ' . nexus_json_encode($this->userUpdate));
        }
    }

    private function buildPeerListResponse(): array
    {
        $counts = $this->countPeers() ?: (object) ['seeders' => 0, 'leechers' => 0];
        $complete = (int) ($counts->seeders ?? 0);
        $incomplete = (int) ($counts->leechers ?? 0);
        $downloaded = (int) (NexusDB::table('torrents')->where('id', $this->torrentId)->value('times_completed') ?? 0);

        $peers = $this->compact ? '' : [];
        $peers6 = '';

        if ($this->event !== 'stopped') {
            $query = NexusDB::table('peers')
                ->where('torrent', $this->torrentId)
                ->where(function ($q) {
                    $q->where('peer_id', '!=', $this->peerId)
                        ->orWhere('userid', '!=', $this->userId);
                })
                ->limit($this->rsize);

            if ($this->seeder === 'yes') {
                $query->where('seeder', 'no');
            }

            foreach ($query->inRandomOrder()->get() as $row) {
                if ($this->compact) {
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

        if ($this->compact) {
            $repDict['peers6'] = $peers6;
        }

        return $repDict;
    }

    private function countPeers(): ?\stdClass
    {
        return NexusDB::table('peers')
            ->where('torrent', $this->torrentId)
            ->selectRaw("SUM(CASE WHEN seeder = 'yes' THEN 1 ELSE 0 END) as seeders, SUM(CASE WHEN seeder = 'no' THEN 1 ELSE 0 END) as leechers")
            ->first();
    }

    private function postProcess(): void
    {
        $redis = NexusDB::redis();

        $lockKey = sprintf('record_batch_lock:%s:%s', $this->userId, $this->torrentId);
        if ($redis->set($lockKey, TIMENOW, ['nx', 'ex' => $this->autocleanIntervalOne])) {
            CleanupRepository::recordBatch($redis, $this->userId, $this->torrentId);
            IpLogRepository::saveToCache($this->userId, null, [$this->ip]);
        }

        if (RequireSeedTorrentRepository::shouldRecordUser($redis, $this->userId, $this->torrentId)) {
            $this->snatchInfo = LegacyDb::snatchInfo($this->torrentId, $this->userId);
            if ($this->snatchInfo) {
                RequireSeedTorrentRepository::recordUser($redis, $this->userId, $this->torrentId, $this->snatchInfo);
            }
        }

        do_action('announced', $this->torrent, $this->user, $this->request->all());
    }

    private function warn(string $message, int $interval = 7200): void
    {
        if (!empty($this->event) && in_array($this->event, ['completed', 'stopped'], true)) {
            throw TrackerException::failure($message);
        }

        $torrentValues = is_array($this->torrent) ? $this->torrent : [];

        $base = [
            'interval'     => $this->realAnnounceInterval,
            'min interval' => MIN_ANNOUNCE_WAIT_SECOND,
            'complete'     => (int) ($torrentValues['seeders'] ?? 0),
            'incomplete'   => (int) ($torrentValues['leechers'] ?? 0),
            'downloaded'   => (int) ($torrentValues['times_completed'] ?? 0),
            'peers'        => $this->compact ? '' : [],
        ];
        if ($this->compact) {
            $base['peers6'] = '';
        }

        throw new TrackerWarningException($message, $base, $interval);
    }
}
