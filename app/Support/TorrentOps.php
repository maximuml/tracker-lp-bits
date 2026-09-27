<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TorrentPromotion;
use App\Enums\UserClass as UserClassEnum;
use App\Models\Torrent;
use App\Repositories\TorrentModerationRepository;
use App\Support\Config\SiteConfig;

/**
 * Legacy torrent operations helpers extracted from `include/functions.php`.
 *
 * Backs `deletetorrent()` and `get_torrent_2_user_value()`.
 */
final class TorrentOps
{
    /**
     * Delete one or more torrents and related records.
     *
     * Mirrors `deletetorrent($id, $notify = false)`.
     *
     * @param  int|int[]  $id
     */
    public static function deleteTorrents($id, bool $notify = false): void
    {
        app(TorrentModerationRepository::class)->deleteTorrents($id, $notify);
    }

    /**
     * Compute the upload/download increments for a peer announce.
     *
     * Mirrors `getDataTraffic()`. Applies global/torrent promotion multipliers,
     * VIP download exemption.
     *
     * @param  array<string, mixed>  $torrent
     * @param  array<string, mixed>  $queries
     * @param  array<string, mixed>  $user
     * @param  array<string, mixed>  $peer
     * @param  array<string, mixed>  $snatch
     * @param  array<string, mixed>  $promotionInfo
     * @return array<string, mixed>
     */
    public static function dataTraffic(
        array $torrent,
        array $queries,
        array $user,
        $peer,
        $snatch,
        $promotionInfo,
    ): array {
        if (! isset($user['__is_donor'])) {
            throw new \InvalidArgumentException("user no '__is_donor' field");
        }

        $log = sprintf(
            'torrent: %s, owner: %s, user: %s, peerUploaded: %s, peerDownloaded: %s, queriesUploaded: %s, queriesDownloaded: %s',
            $torrent['id'], $torrent['owner'], $user['id'], $peer['uploaded'] ?? '', $peer['downloaded'] ?? '', $queries['uploaded'], $queries['downloaded'],
        );

        if (! empty($peer)) {
            /** @var numeric-string $queriesUploaded */
            $queriesUploaded = (string) $queries['uploaded'];
            /** @var numeric-string $peerUploaded */
            $peerUploaded = (string) $peer['uploaded'];
            /** @var numeric-string $queriesDownloaded */
            $queriesDownloaded = (string) $queries['downloaded'];
            /** @var numeric-string $peerDownloaded */
            $peerDownloaded = (string) $peer['downloaded'];
            $realUploaded = max((int) \bcsub($queriesUploaded, $peerUploaded), 0);
            $realDownloaded = max((int) \bcsub($queriesDownloaded, $peerDownloaded), 0);
            $log .= ", [PEER_EXISTS], realUploaded: $realUploaded, realDownloaded: $realDownloaded, [SP_STATE]";

            $spStateGlobal = Promotion::globalSpecialState();
            $spStateNormal = TorrentPromotion::NORMAL->value;
            if (! empty($promotionInfo) && isset($promotionInfo['__ignore_global_sp_state'])) {
                $log .= ', use promotionInfo';
                $spStateReal = $promotionInfo['sp_state'];
            } elseif ($spStateGlobal != $spStateNormal) {
                $log .= ', use global';
                $spStateReal = $spStateGlobal;
            } else {
                $log .= ', use torrent individual';
                $spStateReal = $torrent['sp_state'];
            }

            $promotion = TorrentPromotion::fromIntSafe((int) $spStateReal);
            $log .= ", spStateReal = $spStateReal, promotion: {$promotion->label()}";

            $uploaderRatio = SiteConfig::current()->torrent->uploaderdouble();
            $log .= ", uploaderRatio: $uploaderRatio";
            if ($torrent['owner'] == $user['id'] && $uploaderRatio != 1) {
                $upRatio = max($uploaderRatio, $promotion->upMultiplier());
                $log .= ", [IS_UPLOADER] && uploaderRatio != 1, upRatio: $upRatio";
            } else {
                $upRatio = $promotion->upMultiplier();
                $log .= ", [IS_NOT_UPLOADER] || uploaderRatio == 1, upRatio: $upRatio";
            }

            if ($user['class'] == UserClassEnum::VIP->value) {
                $downRatio = 0;
                $log .= ", [IS_VIP], downRatio: $downRatio";
            } else {
                $downRatio = $promotion->downMultiplier();
                $log .= ", [IS_NOT_VIP], downRatio: $downRatio";
            }
        } else {
            $realUploaded = $queries['uploaded'];
            $realDownloaded = $queries['downloaded'];
            $upRatio = 0;
            $downRatio = 0;
            $log .= ", [PEER_NOT_EXISTS], realUploaded: $realUploaded, realDownloaded: $realDownloaded, upRatio: $upRatio, downRatio: $downRatio";
        }

        $uploadedIncrementForUser = $realUploaded * $upRatio;
        $downloadedIncrementForUser = $realDownloaded * $downRatio;
        $log .= ", uploadedIncrementForUser: $uploadedIncrementForUser, downloadedIncrementForUser: $downloadedIncrementForUser";

        $result = [
            'uploaded_increment' => $realUploaded,
            'uploaded_increment_for_user' => $uploadedIncrementForUser,
            'downloaded_increment' => $realDownloaded,
            'downloaded_increment_for_user' => $downloadedIncrementForUser,
        ];
        Logger::writeWithContext("$log, result: ".Json::encode($result), 'info');

        return $result;
    }
}
