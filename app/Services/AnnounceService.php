<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Announce\AnnounceContext;
use App\DTOs\AnnounceRequestDto;
use App\Enums\Permission\PermissionEnum;
use App\Enums\TorrentApprovalStatus;
use App\Enums\UserClass as UserClassEnum;
use App\Exceptions\ClientNotAllowedException;
use App\Exceptions\TrackerException;
use App\Jobs\BuyTorrent;
use App\Models\User;
use App\Repositories\AgentAllowRepository;
use App\Repositories\CleanupRepository;
use App\Repositories\IpLogRepository;
use App\Repositories\RequireSeedTorrentRepository;
use App\Repositories\TorrentPurchaseRepository;
use App\Repositories\TorrentRepository;
use App\Repositories\UserRepository;
use App\Services\Announce\PeerLifecycle;
use App\Services\Announce\PeerLifecycleResult;
use App\Services\Announce\ResponseBuilder;
use App\Services\Announce\TrafficResult;
use App\Support\Cache as AppCache;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Database;
use App\Support\Json;
use App\Support\LegacyDb;
use App\Support\Logger;
use App\Support\Permissions;
use App\Support\Tracker;
use App\Support\Url;
use App\Support\UserDisplay;
use App\Utils\MsgAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class AnnounceService
{
    public function __construct(
        private readonly AgentAllowRepository $agentAllowRepository,
        private readonly TorrentRepository $torrentRepository,
        private readonly UserRepository $userRepository,
        private readonly Announce\RateLimiter $rateLimiter,
        private readonly Announce\TrafficAccountant $trafficAccountant,
        private readonly Announce\CheaterDetector $cheaterDetector,
        private readonly Announce\HitAndRunHandler $hitAndRunHandler,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function handle(Request $request, array $params): array
    {
        $dto = AnnounceRequestDto::fromRequest($request, $params);

        $ctx = new AnnounceContext(
            dto: $dto,
            params: $dto->toParams(),
            ip: $dto->ip,
            agent: $dto->userAgent,
            dt: date('Y-m-d H:i:s', TIMENOW),
            seeder: $dto->isSeeder() ? 1 : 0,
            isDonor: false,
            isReAnnounce: false,
            clientFamilyId: 0,
            announceWait: MIN_ANNOUNCE_WAIT_SECOND,
            autocleanIntervalOne: 900,
            responseBuilder: new ResponseBuilder($dto),
        );

        $this->blockBrowser($ctx);
        $this->checkPort($ctx);

        $rateLimitResult = $this->rateLimiter->check($dto);
        $ctx = $ctx->withIsReAnnounce($rateLimitResult->isReAnnounce);

        $ctx = $this->authenticateUser($ctx);
        $ctx = $this->checkClient($ctx);
        $ctx = $this->loadTorrent($ctx);

        $torrent = $ctx->torrent;
        if ($torrent === null) {
            throw TrackerException::failure('torrent not registered with this tracker');
        }

        $ctx = $ctx->withResponseBuilder($ctx->responseBuilder->withTorrent($torrent));

        $initialResult = $ctx->responseBuilder->initial($ctx->torrentId());
        $ctx = $ctx->withAutocleanIntervalOne($initialResult->autocleanIntervalOne);
        $repDict = $initialResult->response;

        if ($ctx->isReAnnounce) {
            Logger::writeWithContext((string) '[ANNOUNCE] re-announce, return early.', (string) 'info', (bool) false);

            return $repDict;
        }

        $peerLifecycle = new PeerLifecycle($dto, $torrent, $ctx->user, $ctx->dt);
        $self = $peerLifecycle->findSelf();
        $ctx = $ctx->withSelf($self);

        $snatchInfo = $this->loadSnatchInfo($ctx);
        $ctx = $ctx->withSnatchInfo($snatchInfo);
        $peerLifecycle->setSnatchInfo($ctx->snatchInfo);

        $this->validateAnnounceTime($ctx);
        $this->handlePaidTorrent($ctx);

        $traffic = $this->trafficAccountant->calculate(
            $ctx->self,
            $ctx->params,
            $torrent,
            $ctx->user,
            $ctx->snatchInfo,
            $ctx->ip,
            $ctx->seeder,
        );
        $ctx = $ctx->withTraffic($traffic);

        $this->cheaterDetector->checkSpeed($traffic->upthis, $ctx->self, $ctx->user, $ctx->userId(), $ctx->isDonor);
        $this->cheaterDetector->checkCheating($traffic->upthis, $traffic->downthis, $ctx->self, $ctx->user, $torrent, $ctx->userId(), $ctx->torrentId(), $ctx->dt);

        $response = DB::transaction(function () use ($ctx, $peerLifecycle, $traffic): array {
            return $this->process($ctx, $peerLifecycle, $traffic);
        });

        $this->postProcess($ctx);

        return $response;
    }

    private function blockBrowser(AnnounceContext $ctx): void
    {
        if (preg_match('/^Mozilla/', $ctx->agent)
            || preg_match('/^Opera/', $ctx->agent)
            || preg_match('/^Links/', $ctx->agent)
            || preg_match('/^Lynx/', $ctx->agent)
        ) {
            throw TrackerException::failure('Browser access blocked!');
        }
    }

    private function checkPort(AnnounceContext $ctx): void
    {
        $port = (int) $ctx->params['port'];

        if ($port <= 0 || $port > 0xFFFF) {
            $ctx->responseBuilder->warn('invalid port');
        }

        if ($this->portBlacklisted($port)) {
            $ctx->responseBuilder->warn("Port $port is blacklisted.");
        }
    }

    private function portBlacklisted(int $port): bool
    {
        $list = SiteConfig::current()->security->portBlacklist();
        if ($list === '') {
            return false;
        }

        foreach (explode(',', $list) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            if (str_contains($entry, '-')) {
                [$min, $max] = array_map('intval', explode('-', $entry, 2));
                if ($port >= $min && $port <= $max) {
                    return true;
                }
            } elseif ((int) $entry === $port) {
                return true;
            }
        }

        return false;
    }

    private function authenticateUser(AnnounceContext $ctx): AnnounceContext
    {
        $passkey = $ctx->params['passkey'];

        $user = Cache::remember("user_passkey_{$passkey}_content", 3600, function () use ($passkey) {
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

        if (! $user) {
            Redis::connection()->client()->set("passkey_invalid:{$passkey}", TIMENOW, ['ex' => 24 * 3600]);
            throw TrackerException::failure('Invalid passkey! Re-download the .torrent from '.Url::schemeAndHost(true));
        }

        app(CurrentUser::class)->set($user);

        if (! $user['enabled']) {
            throw TrackerException::failure('Your account is disabled!');
        }
        if ($user['parked']) {
            throw TrackerException::failure('Your account is parked! (Read the FAQ)');
        }
        if (! $user['downloadpos']) {
            throw TrackerException::failure('Your downloading privileges have been disabled! (Read the rules)');
        }

        $isDonor = UserDisplay::isDonor($user);
        $user['__is_donor'] = $isDonor;

        $ctx = $ctx->withUser($user)->withIsDonor($isDonor);

        $this->checkTrackerUrl($ctx);

        return $ctx;
    }

    private function checkTrackerUrl(AnnounceContext $ctx): void
    {
        $trackerUrlRaw = Tracker::schemaAndHost((int) $ctx->user['tracker_url_id'], true);
        $trackerUrl = is_array($trackerUrlRaw) ? implode('', $trackerUrlRaw) : $trackerUrlRaw;
        $currentUrl = Url::schemeAndHost();

        if (! str_contains($trackerUrl, $currentUrl)) {
            Logger::writeWithContext((string) "announce check tracker url, trackerUrl: {$trackerUrl} does not contains: {$currentUrl}", (string) 'info', (bool) false);
            $ctx->responseBuilder->warn("you should announce to: {$trackerUrl}");
        }
    }

    private function checkClient(AnnounceContext $ctx): AnnounceContext
    {
        $agentAllowRep = $this->agentAllowRepository;
        $clicheckRes = '';

        try {
            $checkClientResult = $agentAllowRep->checkClient($ctx->peerIdBinary(), $ctx->agent);
            $ctx = $ctx->withClientFamilyId((int) $checkClientResult->id);
        } catch (ClientNotAllowedException $exception) {
            $clicheckRes = $exception->getMessage();
        }

        if ($clicheckRes) {
            if (! $ctx->user['showclienterror']) {
                User::query()->where('id', $ctx->userId())->update(['showclienterror' => true]);
                AppCache::forgetWithLocales("user_passkey_{$ctx->params['passkey']}_content");
            }
            throw TrackerException::failure($clicheckRes);
        }

        $userUpdate = $ctx->userUpdate;
        if ($ctx->user['showclienterror']) {
            $userUpdate['showclienterror'] = false;
            AppCache::forgetWithLocales("user_passkey_{$ctx->params['passkey']}_content");
        }

        return $ctx->withUserUpdate($userUpdate);
    }

    private function loadTorrent(AnnounceContext $ctx): AnnounceContext
    {
        $infoHashHex = bin2hex($ctx->infoHashBinary());

        $torrent = Cache::remember("torrent_hash_{$ctx->infoHashBinary()}_content", 350, function () use ($ctx) {
            $tsField = Database::unixTimestampField('added');
            $torrent = DB::table('torrents')
                ->leftJoin('categories', 'torrents.category', '=', 'categories.id')
                ->select([
                    'torrents.id', 'torrents.size', 'torrents.owner', 'torrents.sp_state',
                    'torrents.seeders', 'torrents.leechers', 'torrents.times_completed',
                    'torrents.banned', 'torrents.hr', 'torrents.approval_status', 'torrents.price',
                    'torrents.visible', 'torrents.last_action', 'categories.mode',
                    DB::raw("{$tsField} AS ts"), // @phpstan-ignore argument.type
                ])
                ->where('torrents.info_hash', $ctx->infoHashBinary())
                ->first();

            return $torrent ? (array) $torrent : false;
        });

        if ($torrent === false) {
            Logger::writeWithContext((string) ('[TORRENT NOT EXISTS] info_hash: '.$infoHashHex), (string) 'info', (bool) false);
            Redis::connection()->client()->set('torrent_not_exists:'.$ctx->infoHashBinary(), TIMENOW, ['ex' => 24 * 3600]);
            throw TrackerException::failure('torrent not registered with this tracker');
        }

        $ctx = $ctx->withTorrent($torrent);

        if ($torrent['banned'] && ! Permissions::userCan(PermissionEnum::TORRENT_VIEW_BANNED->value, false, $ctx->userId())) {
            throw TrackerException::failure('torrent banned');
        }

        if ($torrent['approval_status'] != TorrentApprovalStatus::ALLOW->value
            && ! SiteConfig::current()->torrent->approvalStatusNoneVisible()
            && ! Permissions::userCan(PermissionEnum::TORRENT_VIEW_BANNED->value, false, $ctx->userId())
        ) {
            throw TrackerException::failure('torrent review not approved');
        }

        $ctx = $ctx->withResponseBuilder($ctx->responseBuilder->withTorrent($torrent));

        if ($ctx->dto->left > (int) $torrent['size']) {
            $this->userRepository->updateDownloadPrivileges(null, $ctx->userId(), false, 'fake_announce');
            Logger::writeWithContext((string) sprintf('fake announce, user: %s, torrent: %s, announce left: %s > size: %s', $ctx->userId(), $ctx->torrentId(), $ctx->dto->left, $torrent['size']), (string) 'warn', (bool) false);
            $ctx->responseBuilder->warn('fake announce', 300);
        }

        return $ctx;
    }

    /** @return array<string, mixed>|false */
    private function loadSnatchInfo(AnnounceContext $ctx): array|false
    {
        if ($ctx->self !== null) {
            return LegacyDb::snatchInfo($ctx->torrentId(), $ctx->userId());
        }

        return false;
    }

    private function validateAnnounceTime(AnnounceContext $ctx): void
    {
        if ($ctx->self !== null && empty($ctx->dto->event) && (int) $ctx->self['prevts'] > (TIMENOW - $ctx->announceWait)) {
            $ctx->responseBuilder->warn('There is a minimum announce time of '.$ctx->announceWait.' seconds', $ctx->announceWait);
        }
    }

    private function handlePaidTorrent(AnnounceContext $ctx): void
    {
        if ($ctx->seeder === 1
            || ! isset($ctx->user['seedbonus'])
            || ! isset($ctx->torrent['price'])
            || (int) $ctx->torrent['price'] <= 0
            || (int) $ctx->torrent['owner'] == $ctx->userId()
            || ! SiteConfig::current()->torrent->paidTorrentEnabled()
        ) {
            return;
        }

        $torrentRep = $this->torrentRepository;
        $buyStatus = $torrentRep->getBuyStatus($ctx->userId(), $ctx->torrentId());
        Logger::writeWithContext((string) "user: {$ctx->userId()} buy torrent: {$ctx->torrentId()}, status: {$buyStatus}", (string) 'info', (bool) false);

        if ($buyStatus > 0) {
            Logger::writeWithContext((string) sprintf('user: %s buy torrent： %s fail count: %s', $ctx->userId(), $ctx->torrentId(), $buyStatus), (string) 'error', (bool) false);
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
                $this->userRepository->updateDownloadPrivileges(null, $ctx->userId(), false, 'announce_paid_torrent_too_many_times');
            }
            dispatch(new BuyTorrent($ctx->userId(), $ctx->torrentId()));
            $torrentRep->addBuyFailCache($ctx->userId(), $ctx->torrentId());
            $ctx->responseBuilder->warn('purchase in progress, please try again later, and make sure you have enough bonus', 300);
        }

        if ($buyStatus == TorrentPurchaseRepository::BUY_STATUS_UNKNOWN) {
            dispatch(new BuyTorrent($ctx->userId(), $ctx->torrentId()));
            $ctx->responseBuilder->warn('purchase started, please wait', 300);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function process(AnnounceContext $ctx, PeerLifecycle $peerLifecycle, TrafficResult $traffic): array
    {
        $torrent = $ctx->torrent;
        if ($torrent === null) {
            throw TrackerException::failure('torrent not registered with this tracker');
        }

        // Lock the peer row, snatch row, and user row to prevent concurrent
        // announce updates from racing on the same peer. This ensures
        // consistent accounting under high concurrency.
        $this->lockRowsForUpdate($ctx);

        $result = $peerLifecycle->process($traffic->upthis, $traffic->downthis, $traffic->snatchTimeColumn, $traffic->snatchTimeIncrement, $traffic->leechTimeNoSeederIncrement);

        $snatchInfo = $result->snatchInfo;
        $hitAndRunResult = $this->hitAndRunHandler->handle($ctx->dto->left, $ctx->dto->event, $ctx->user, $torrent, $ctx->userId(), $ctx->torrentId(), $ctx->isDonor, $ctx->dt, $snatchInfo);
        if ($hitAndRunResult !== null) {
            $snatchInfo = $hitAndRunResult;
        }

        $this->applyUserUpdate($ctx, $result);

        $torrentUpdate = $result->torrentUpdate;
        if (! empty($torrentUpdate)) {
            $torrentUpdate['visible'] = 1;
            $torrentUpdate['last_action'] = $ctx->dt;
            DB::table('torrents')->where('id', $ctx->torrentId())->update($torrentUpdate);
            Logger::writeWithContext((string) ('[ANNOUNCE_UPDATE_TORRENT], '.Json::encode($torrentUpdate)), (string) 'info', (bool) false);
        }

        return $ctx->responseBuilder->peerList($ctx->torrentId(), $ctx->userId(), $ctx->seeder === 1);
    }

    /**
     * Lock peer, snatch, and user rows for update within the transaction
     * to prevent concurrent announce races on the same peer.
     */
    private function lockRowsForUpdate(AnnounceContext $ctx): void
    {
        // Lock the existing peer row if present
        if ($ctx->self !== null && ! empty($ctx->self['id'])) {
            DB::table('peers')
                ->where('id', (int) $ctx->self['id'])
                ->lockForUpdate()
                ->first();
        }

        // Lock the snatch row if present
        if (! empty($ctx->snatchInfo) && ! empty($ctx->snatchInfo['id'])) {
            DB::table('snatched')
                ->where('id', (int) $ctx->snatchInfo['id'])
                ->lockForUpdate()
                ->first();
        }

        // Lock the user row to serialize uploaded/downloaded increments
        DB::table('users')
            ->where('id', $ctx->userId())
            ->lockForUpdate()
            ->first();
    }

    private function applyUserUpdate(AnnounceContext $ctx, PeerLifecycleResult $result): void
    {
        $userUpdate = $ctx->userUpdate;

        if ($ctx->clientFamilyId != 0 && $ctx->clientFamilyId != (int) $ctx->user['clientselect']) {
            $userUpdate['clientselect'] = $ctx->clientFamilyId;
        }

        $userUpdate['last_announce_at'] = $ctx->dt;

        if ($ctx->uploadedIncrementForUser > 0) {
            $userUpdate['uploaded'] = DB::raw(DB::getQueryGrammar()->wrap('uploaded').' + '.(int) $ctx->uploadedIncrementForUser); // @phpstan-ignore argument.type
        }
        if ($ctx->downloadedIncrementForUser > 0) {
            $userUpdate['downloaded'] = DB::raw(DB::getQueryGrammar()->wrap('downloaded').' + '.(int) $ctx->downloadedIncrementForUser); // @phpstan-ignore argument.type
        }

        if ((int) $ctx->user['class'] === (int) UserClassEnum::VIP->value) {
            unset($userUpdate['downloaded']);
        }

        if ($ctx->userId() !== 0) {
            User::query()->where('id', $ctx->userId())->update($userUpdate);
            Logger::writeWithContext((string) ('[ANNOUNCE_UPDATE_USER], '.Json::encode($userUpdate)), (string) 'info', (bool) false);
        }
    }

    private function postProcess(AnnounceContext $ctx): void
    {
        $redis = Redis::connection()->client();

        $lockKey = sprintf('record_batch_lock:%s:%s', $ctx->userId(), $ctx->torrentId());
        if ($redis->set($lockKey, TIMENOW, ['nx', 'ex' => $ctx->autocleanIntervalOne])) {
            app(CleanupRepository::class)->recordBatch($redis, $ctx->userId(), $ctx->torrentId());
            app(IpLogRepository::class)->saveToCache($ctx->userId(), null, [$ctx->ip]);
        }

        if (app(RequireSeedTorrentRepository::class)->shouldRecordUser($redis, $ctx->userId(), $ctx->torrentId())) {
            $snatchInfo = LegacyDb::snatchInfo($ctx->torrentId(), $ctx->userId());
            if ($snatchInfo) {
                app(RequireSeedTorrentRepository::class)->recordUser($redis, $ctx->userId(), $ctx->torrentId(), $snatchInfo);
            }
        }
    }
}
