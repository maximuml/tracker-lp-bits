<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Enums\PromotionTimeType;
use App\Enums\TorrentOperationAction;
use App\Enums\TorrentPosState;
use App\Enums\TorrentPromotion;
use App\Events\TorrentDeleted;
use App\Models\Category;
use App\Models\Torrent;
use App\Models\TorrentTag;
use App\Support\Config\SiteConfig;
use App\Support\Logger;
use App\Support\Path;
use App\Support\UserDisplay;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Handles staff/admin torrent operations: approval, promotion, sticky, HR,
 * tags, bulk category moves, and deletion.
 *
 * Extracted from TorrentRepository to reduce god-object surface area.
 */
class TorrentModerationRepository extends BaseRepository
{
    public function __construct(
        private readonly TorrentDownloadRepository $downloadRepository,
        private readonly MeiliSearchRepository $meiliSearchRepository,
        private readonly TorrentOperationLogRepository $operationLogRepository,
        private readonly TorrentApprovalRepository $approvalRepository,
    ) {}

    /**
     * @param  mixed  $user
     * @return array<int|string, mixed>
     */
    public function buildApprovalModal($user, int $torrentId)
    {
        return $this->approvalRepository->buildApprovalModal($user, $torrentId);
    }

    /**
     * @param  mixed  $user
     * @param  array<int|string, mixed>  $params
     * @return array<int|string, mixed>
     */
    public function approval($user, array $params): array
    {
        return $this->approvalRepository->approval($user, $params);
    }

    /** @param  mixed  $approvalStatus */
    public function shouldShowApprovalStatusIcon($approvalStatus): bool
    {
        return $this->approvalRepository->shouldShowApprovalStatusIcon($approvalStatus);
    }

    public function getApprovalDenyCount(int $ownerId): int
    {
        return $this->approvalRepository->getApprovalDenyCount($ownerId);
    }

    /**
     * @param  mixed  $id
     * @param  array<int|string, mixed>  $tagIdArr
     * @param  mixed  $remove
     * @return mixed
     */
    public function syncTags($id, array $tagIdArr = [], $remove = true)
    {
        Permission::assertCan(PermissionEnum::TORRENT_MANAGE);
        $idArr = Arr::wrap($id);

        return DB::transaction(function () use ($idArr, $tagIdArr, $remove) {
            $time = now()->toDateTimeString();
            $records = [];
            foreach ($idArr as $torrentId) {
                foreach ($tagIdArr as $tagId) {
                    $records[] = [
                        'torrent_id' => $torrentId,
                        'tag_id' => $tagId,
                        'created_at' => $time,
                        'updated_at' => $time,
                    ];
                }
            }
            if ($remove) {
                TorrentTag::query()->whereIn('torrent_id', $idArr)->delete();
            }
            if (! empty($records)) {
                DB::table('torrent_tags')->upsert($records, ['torrent_id', 'tag_id'], ['updated_at']);
            }

            return count($records);
        });

    }

    /**
     * @param  mixed  $id
     * @param  mixed  $posState
     * @param  mixed  $posStateUntil
     */
    public function setPosState($id, $posState, $posStateUntil = null): int
    {
        Permission::assertCan(PermissionEnum::TORRENT_SET_STICKY);
        if ($posState == TorrentPosState::NONE->value) {
            $posStateUntil = null;
        }
        if ($posStateUntil && Carbon::parse($posStateUntil)->lte(now())) {
            $posState = TorrentPosState::NONE->value;
            $posStateUntil = null;
        }
        $update = [
            'pos_state' => $posState,
            'pos_state_until' => $posStateUntil,
        ];
        $idArr = Arr::wrap($id);

        return Torrent::query()->whereIn('id', $idArr)->update($update);
    }

    /**
     * @param  mixed  $id
     * @param  mixed  $hrStatus
     */
    public function setHr($id, $hrStatus): int
    {
        Permission::assertCan(PermissionEnum::TORRENT_MANAGE);
        if (! isset(Torrent::$hrStatus[$hrStatus])) {
            throw new \InvalidArgumentException("Invalid hrStatus: $hrStatus");
        }
        $update = [
            'hr' => $hrStatus,
        ];
        $idArr = Arr::wrap($id);
        Logger::writeWithContext((string) sprintf('set torrent: %s hr: %s', implode(',', $idArr), $hrStatus), (string) 'info', (bool) false);

        return Torrent::query()->whereIn('id', $idArr)->update($update);
    }

    /**
     * @param  mixed  $id
     * @param  mixed  $spState
     * @param  mixed  $promotionTimeType
     * @param  mixed  $promotionUntil
     */
    public function setSpState($id, $spState, $promotionTimeType, $promotionUntil = null): int
    {
        Permission::assertCan(PermissionEnum::TORRENT_ON_PROMOTION);
        if (TorrentPromotion::tryFrom((int) $spState) === null) {
            throw new \InvalidArgumentException("Invalid spState: $spState");
        }
        if (PromotionTimeType::tryFrom((int) $promotionTimeType) === null) {
            throw new \InvalidArgumentException("Invalid promotionTimeType: $promotionTimeType");
        }
        if (in_array((int) $promotionTimeType, [PromotionTimeType::GLOBAL->value, PromotionTimeType::PERMANENT->value])) {
            $promotionUntil = null;
        } elseif (! $promotionUntil || Carbon::parse($promotionUntil)->lte(now())) {
            throw new \InvalidArgumentException("Invalid promotionUntil: $promotionUntil");
        }
        $update = [
            'sp_state' => $spState,
            'promotion_time_type' => $promotionTimeType,
            'promotion_until' => $promotionUntil,
        ];
        $idArr = Arr::wrap($id);

        return Torrent::query()->whereIn('id', $idArr)->update($update);
    }

    /**
     * Delete one or more torrents and related records.
     *
     * Mirrors the legacy {@see TorrentOps::deleteTorrents()}.
     *
     * @param  int|int[]  $id
     */
    public function deleteTorrents(int|array $id, bool $notify = false): void
    {
        $idArr = array_map('intval', is_array($id) ? $id : [$id]);

        $torrentInfo = Torrent::query()
            ->whereIn('id', $idArr)
            ->get()
            ->keyBy('id');

        $torrentDir = SiteConfig::current()->main->torrentDir();

        $downloadRepo = $this->downloadRepository;
        foreach ($idArr as $_id) {
            /** @var Torrent|null $torrent */
            $torrent = $torrentInfo->get($_id);

            if ($torrent instanceof Torrent) {
                $downloadRepo->delPiecesHashCache((string) $torrent->getAttribute('pieces_hash'));
            }

            Logger::writeWithContext("delete torrent: $_id", 'error');
            @unlink(Path::resolve("$torrentDir/$_id.torrent", defined('ROOT_PATH') ? (string) ROOT_PATH : ''));

            $this->operationLogRepository->add([
                'torrent_id' => $_id,
                'uid' => UserDisplay::currentId(),
                'action_type' => TorrentOperationAction::DELETE->value,
                'comment' => '',
            ], $notify);

            if ($torrent instanceof Torrent) {
                event(new TorrentDeleted($torrent->toArray()));
            }
        }

        DB::table('torrents')->whereIn('id', $idArr)->delete();
        DB::table('torrent_extras')->whereIn('torrent_id', $idArr)->delete();
        DB::table('snatched')
            ->whereIn('torrentid', $idArr)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('users')->whereColumn('users.id', '=', 'snatched.userid');
            })
            ->delete();

        foreach (['peers', 'files', 'comments'] as $x) {
            DB::table($x)->whereIn('torrent', $idArr)->delete();
        }

        DB::table('hit_and_runs')->whereIn('torrent_id', $idArr)->delete();

        try {
            $meiliSearchRep = $this->meiliSearchRepository;
            $meiliSearchRep->deleteDocuments($idArr);
        } catch (\Throwable $e) {
            Logger::writeWithContext('MeiliSearch delete on torrent delete failed: '.$e->getMessage(), 'error');
        }
    }
}
