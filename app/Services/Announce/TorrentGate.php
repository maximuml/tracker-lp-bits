<?php

declare(strict_types=1);

namespace App\Services\Announce;

use App\Contracts\Repositories\UserModerationRepositoryInterface;
use App\DTOs\Announce\AnnounceContext;
use App\Enums\Permission\PermissionEnum;
use App\Enums\TorrentApprovalStatus;
use App\Exceptions\TrackerException;
use App\Jobs\BuyTorrent;
use App\Repositories\TorrentPurchaseRepository;
use App\Support\Config\SiteConfig;
use App\Support\Database;
use App\Support\Logger;
use App\Support\Permissions;
use App\Support\RedisGuard;
use App\Utils\MsgAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Torrent-side announce gate: resolves the torrent row (with cache), enforces
 * banned/approval/fake-announce rules, and runs the paid-torrent purchase gate.
 * Extracted from AnnounceService to keep both classes under the 400-line ratchet.
 */
final class TorrentGate
{
    public function __construct(
        private readonly TorrentPurchaseRepository $purchaseRepository,
        private readonly UserModerationRepositoryInterface $userModerationRepository,
    ) {}

    public function resolve(AnnounceContext $ctx): AnnounceContext
    {
        $infoHashHex = bin2hex($ctx->infoHashBinary());

        $lookupTorrent = static function () use ($ctx) {
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
        };

        $torrentCacheKey = "torrent_hash_{$ctx->infoHashBinary()}_content";
        $torrent = RedisGuard::attempt(static fn () => Cache::get($torrentCacheKey));
        if (! is_array($torrent)) {
            $torrent = $lookupTorrent();
            if ($torrent !== false) {
                RedisGuard::attempt(static fn () => Cache::put($torrentCacheKey, $torrent, 350));
            }
        }

        if ($torrent === false) {
            Logger::writeWithContext((string) ('[TORRENT NOT EXISTS] info_hash: '.$infoHashHex), (string) 'info', (bool) false);
            RedisGuard::attempt(static fn () => Redis::connection()->client()->set('torrent_not_exists:'.$ctx->infoHashBinary(), TIMENOW, ['ex' => 24 * 3600]));
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
            $this->userModerationRepository->updateDownloadPrivileges(null, $ctx->userId(), false, 'fake_announce');
            Logger::writeWithContext((string) sprintf('fake announce, user: %s, torrent: %s, announce left: %s > size: %s', $ctx->userId(), $ctx->torrentId(), $ctx->dto->left, $torrent['size']), (string) 'warn', (bool) false);
            $ctx->responseBuilder->warn('fake announce', 300);
        }

        return $ctx;
    }

    public function checkPaid(AnnounceContext $ctx): void
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

        $purchaseRep = $this->purchaseRepository;
        $buyStatus = $purchaseRep->getBuyStatus($ctx->userId(), $ctx->torrentId());
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
                $this->userModerationRepository->updateDownloadPrivileges(null, $ctx->userId(), false, 'announce_paid_torrent_too_many_times');
            }
            RedisGuard::attempt(static fn () => dispatch(new BuyTorrent($ctx->userId(), $ctx->torrentId())));
            $purchaseRep->addBuyFailCache($ctx->userId(), $ctx->torrentId());
            $ctx->responseBuilder->warn('purchase in progress, please try again later, and make sure you have enough bonus', 300);
        }

        if ($buyStatus == TorrentPurchaseRepository::BUY_STATUS_UNKNOWN) {
            RedisGuard::attempt(static fn () => dispatch(new BuyTorrent($ctx->userId(), $ctx->torrentId())));
            $ctx->responseBuilder->warn('purchase started, please wait', 300);
        }
    }
}
