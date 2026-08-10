<?php

namespace App\Services;

use App\DTOs\AnnounceRequestDto;
use App\Enums\Permission\PermissionEnum;
use App\Exceptions\ClientNotAllowedException;
use App\Exceptions\TrackerException;
use App\Models\Torrent;
use App\Support\Permissions;
use App\Models\User;
use App\Repositories\AgentAllowRepository;
use App\Repositories\CleanupRepository;
use App\Repositories\IpLogRepository;
use App\Repositories\RequireSeedTorrentRepository;
use App\Repositories\TorrentRepository;
use App\Repositories\UserRepository;
use App\Services\Announce\CheaterDetector;
use App\Services\Announce\HitAndRunHandler;
use App\Services\Announce\InitialResponseResult;
use App\Services\Announce\PeerLifecycle;
use App\Services\Announce\RateLimiter;
use App\Services\Announce\ResponseBuilder;
use App\Services\Announce\TrafficAccountant;
use App\Services\Announce\TrafficResult;
use App\Support\LegacyDb;
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
    private ResponseBuilder $responseBuilder;
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

        $this->responseBuilder = new ResponseBuilder($this->dto);

        $this->blockBrowser();
        $this->checkPort();
        $rateLimitResult = (new RateLimiter())->check($this->dto);
        $this->isReAnnounce = $rateLimitResult->isReAnnounce;
        $this->authenticateUser();
        $this->checkClient();
        $this->loadTorrent();

        $this->responseBuilder = $this->responseBuilder->withTorrent($this->torrent);
        $initialResult = $this->responseBuilder->initial($this->torrentId);
        $this->realAnnounceInterval = $initialResult->realAnnounceInterval;
        $this->autocleanIntervalOne = $initialResult->autocleanIntervalOne;
        $repDict = $initialResult->response;

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

        $traffic = (new TrafficAccountant())->calculate($this->self, $this->params, $this->torrent, $this->user, $this->snatchInfo ?: false, $this->ip, $this->seeder);
        $this->uploadedIncrementForUser = $traffic->uploadedIncrementForUser;
        $this->downloadedIncrementForUser = $traffic->downloadedIncrementForUser;

        $cheaterDetector = new CheaterDetector($this->responseBuilder);
        $cheaterDetector->checkSpeed($traffic->upthis, $this->self, $this->user, $this->userId, $this->isDonor, $this->isIPSeedBox);
        $cheaterDetector->checkCheating($traffic->upthis, $traffic->downthis, $this->self, $this->user, $this->torrent, $this->userId, $this->torrentId, $this->dt);

        $response = NexusDB::transaction(function () use ($peerLifecycle, $traffic) {
            return $this->process($peerLifecycle, $traffic);
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
            $this->responseBuilder->warn('invalid port');
        }

        if ($this->portBlacklisted($port)) {
            $this->responseBuilder->warn("Port $port is blacklisted.");
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
            $this->responseBuilder->warn("you should announce to: {$trackerUrl}");
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

        if ($this->torrent['banned'] === 'yes' && !Permissions::userCan(PermissionEnum::TORRENT_VIEW_BANNED->value, false, $this->userId)) {
            throw TrackerException::failure('torrent banned');
        }

        if ($this->torrent['approval_status'] != Torrent::APPROVAL_STATUS_ALLOW
            && !\App\Support\Config\SiteConfig::current()->torrent->approvalStatusNoneVisible()
            && !Permissions::userCan(PermissionEnum::TORRENT_VIEW_BANNED->value, false, $this->userId)
        ) {
            throw TrackerException::failure('torrent review not approved');
        }

        assert($this->torrent !== null);
        $this->responseBuilder = $this->responseBuilder->withTorrent($this->torrent);

        if ($this->left > (int) $this->torrent['size']) {
            (new UserRepository())->updateDownloadPrivileges(null, $this->userId, 'no', 'fake_announce');
            do_log(sprintf('fake announce, user: %s, torrent: %s, announce left: %s > size: %s', $this->userId, $this->torrentId, $this->left, $this->torrent['size']), 'warn');
            $this->responseBuilder->warn('fake announce', 300);
        }
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
            $this->responseBuilder->warn('There is a minimum announce time of ' . $this->announceWait . ' seconds', $this->announceWait);
        }
    }

    private function detectSeedBox(): void
    {
        if (!\App\Support\Config\SiteConfig::current()->seedBox->enabled()) {
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
            || !\App\Support\Config\SiteConfig::current()->torrent->paidTorrentEnabled()
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
            $this->responseBuilder->warn('purchase in progress, please try again later, and make sure you have enough bonus', 300);
        }

        if ($buyStatus == TorrentRepository::BUY_STATUS_UNKNOWN) {
            \Nexus\Nexus::dispatchQueueJob(new \App\Jobs\BuyTorrent($this->userId, $this->torrentId));
            $this->responseBuilder->warn('purchase started, please wait', 300);
        }
    }

    private function process(PeerLifecycle $peerLifecycle, TrafficResult $traffic): array
    {
        $result = $peerLifecycle->process($traffic->upthis, $traffic->downthis, $traffic->snatchTimeColumn, $traffic->snatchTimeIncrement, $traffic->leechTimeNoSeederIncrement);
        $this->self = $result->self;
        $this->snatchInfo = $result->snatchInfo;
        $this->torrentUpdate = $result->torrentUpdate;

        $hitAndRunResult = (new HitAndRunHandler())->handle($this->left, $this->event, $this->user, $this->torrent, $this->userId, $this->torrentId, $this->isDonor, $this->dt, $this->snatchInfo ?: false);
        if ($hitAndRunResult !== null) {
            $this->snatchInfo = $hitAndRunResult;
        }

        $this->applyUserUpdate();

        if (!empty($this->torrentUpdate)) {
            $this->torrentUpdate['visible'] = 'yes';
            $this->torrentUpdate['last_action'] = $this->dt;
            NexusDB::table('torrents')->where('id', $this->torrentId)->update($this->torrentUpdate);
            do_log('[ANNOUNCE_UPDATE_TORRENT], ' . nexus_json_encode($this->torrentUpdate));
        }

        return $this->responseBuilder->peerList($this->torrentId, $this->userId, $this->seeder);
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
}