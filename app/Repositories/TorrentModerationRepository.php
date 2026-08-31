<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\Permission;
use App\Enums\ModelEventEnum;
use App\Enums\Permission\PermissionEnum;
use App\Enums\PromotionTimeType;
use App\Enums\TorrentApprovalStatus;
use App\Enums\TorrentOperationAction;
use App\Enums\TorrentPosState;
use App\Enums\TorrentPromotion;
use App\Exceptions\InsufficientPermissionException;
use App\Exceptions\NexusException;
use App\Models\Category;
use App\Models\SearchBox;
use App\Models\SiteLog;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\TorrentOperationLog;
use App\Models\TorrentTag;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\Events;
use App\Support\Json;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\Path;
use App\Support\Permissions;
use App\Support\UserDisplay;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
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
        private readonly SearchBoxRepository $searchBoxRepository,
        private readonly TorrentDownloadRepository $downloadRepository,
        private readonly MeiliSearchRepository $meiliSearchRepository,
    ) {}

    /**
     * @param  mixed  $user
     * @return array<int|string, mixed>
     */
    public function buildApprovalModal($user, int $torrentId)
    {
        $user = $this->getUser($user);
        Permission::assertCan(PermissionEnum::TORRENT_APPROVAL, $user);
        $torrent = Torrent::query()->findOrFail($torrentId, ['id', 'approval_status', 'banned']);
        $radios = [];
        foreach (Torrent::$approvalStatus as $key => $value) {
            if ($torrent->approval_status == $key) {
                $checked = ' checked';
            } else {
                $checked = '';
            }
            $radios[] = sprintf(
                '<label><input type="radio" name="params[approval_status]" value="%s"%s>%s</label>',
                $key, $checked, Locale::trans("torrent.approval.status_text.{$key}", [], null)
            );
        }
        $id = 'torrent-approval';
        $rows = [];
        $rowStyle = 'display: flex; padding: 10px; align-items: center';
        $labelStyle = 'width: 80px';
        $formId = "$id-form";
        $rows[] = sprintf(
            '<div class="%s-row" style="%s"><div style="%s">%s: </div><div>%s</div></div>',
            $id, $rowStyle, $labelStyle, Locale::trans('torrent.approval.status_label', [], null), implode('', $radios)
        );
        $rows[] = sprintf(
            '<div class="%s-row" style="%s"><div style="%s">%s: </div><div><textarea name="params[comment]" rows="4" cols="40"></textarea></div></div>',
            $id, $rowStyle, $labelStyle, Locale::trans('torrent.approval.comment_label', [], null)
        );
        $rows[] = sprintf('<input type="hidden" name="params[torrent_id]" value="%s" />', $torrent->id);

        $html = sprintf('<div id="%s-box" style="padding: 15px 30px"><form id="%s">%s</form></div>', $id, $formId, implode('', $rows));

        return [
            'id' => $id,
            'form_id' => $formId,
            'title' => Locale::trans('torrent.approval.modal_title', [], null),
            'content' => $html,
        ];

    }

    /**
     * @param  mixed  $user
     * @param  array<int|string, mixed>  $params
     * @return array<int|string, mixed>
     */
    public function approval($user, array $params): array
    {
        $user = $this->getUser($user) ?? Auth::user();
        Permission::assertCan(PermissionEnum::TORRENT_APPROVAL, $user);
        if (! $user instanceof User) {
            throw new InsufficientPermissionException;
        }
        $torrentId = (int) $params['torrent_id'];
        $approvalStatus = (int) $params['approval_status'];
        $comment = (string) ($params['comment'] ?? '');
        $torrent = Torrent::query()->findOrFail($torrentId, Torrent::$commentFields);
        $lastLog = TorrentOperationLog::query()
            ->where('torrent_id', $torrentId)
            ->where('uid', $user->id)
            ->orderBy('id', 'desc')
            ->first();
        if ($torrent->approval_status == $approvalStatus && $lastLog && $lastLog->comment == $comment) {
            // No change
            return $params;
        }
        $torrentUpdate = $torrentOperationLog = [];
        $torrentUpdate['approval_status'] = $approvalStatus;
        $notifyUser = false;
        if ($approvalStatus == TorrentApprovalStatus::ALLOW->value) {
            $torrentUpdate['banned'] = 0;
            $torrentUpdate['visible'] = 1;
            if ($torrent->approval_status != $approvalStatus) {
                $torrentOperationLog['action_type'] = TorrentOperationAction::APPROVAL_ALLOW->value;
                // increase promotion time
                if (
                    ! SiteConfig::current()->torrent->approvalStatusNoneVisible()
                    && $torrent->sp_state != TorrentPromotion::NORMAL->value
                    && $torrent->promotion_until
                ) {
                    $hasBeenDownloaded = Snatch::query()->where('torrentid', $torrent->id)->exists();
                    $log = "Torrent: {$torrent->id} is in promotion, hasBeenDownloaded: $hasBeenDownloaded";
                    if (! $hasBeenDownloaded) {
                        $diffInSeconds = $torrent->promotion_until->diffInSeconds($torrent->added, true);
                        $log .= ", addSeconds: $diffInSeconds";
                        $torrentUpdate['promotion_until'] = $torrent->promotion_until->addSeconds($diffInSeconds);
                    }
                    Logger::writeWithContext((string) $log, (string) 'info', (bool) false);
                }
            }
            if ($torrent->approval_status == TorrentApprovalStatus::DENY->value) {
                $notifyUser = true;
            }
        } elseif ($approvalStatus == TorrentApprovalStatus::DENY->value) {
            $torrentUpdate['banned'] = 1;
            $torrentUpdate['visible'] = 0;
            // Deny, record and notify all the time
            $torrentOperationLog['action_type'] = TorrentOperationAction::APPROVAL_DENY->value;
            $notifyUser = true;
        } elseif ($approvalStatus == TorrentApprovalStatus::NONE->value) {
            $torrentUpdate['banned'] = 0;
            $torrentUpdate['visible'] = 1;
            if ($torrent->approval_status != $approvalStatus) {
                $torrentOperationLog['action_type'] = TorrentOperationAction::APPROVAL_NONE->value;
            }
            if ($torrent->approval_status == TorrentApprovalStatus::DENY->value) {
                $notifyUser = true;
            }
        } else {
            throw new \InvalidArgumentException('Invalid approval_status: '.$approvalStatus);
        }

        if (isset($torrentOperationLog['action_type'])) {
            $torrentOperationLog['uid'] = $user->id;
            $torrentOperationLog['torrent_id'] = $torrent->id;
            $torrentOperationLog['comment'] = $comment;
        }

        DB::transaction(function () use ($torrent, $torrentOperationLog, $torrentUpdate, $notifyUser) {
            $log = 'torrent: '.$torrent->id;
            /** @var array<string, mixed> $torrentUpdate */
            $log .= ', [UPDATE_TORRENT]: '.Json::encode($torrentUpdate);
            $torrent->update($torrentUpdate);
            if (! empty($torrentOperationLog)) {
                $log .= ', [ADD_TORRENT_OPERATION_LOG]: '.Json::encode($torrentOperationLog);
                TorrentOperationLog::add($torrentOperationLog, $notifyUser);
            }
            Logger::writeWithContext((string) $log, (string) 'info', (bool) false);
        });

        return $params;

    }

    /**
     * @param  mixed  $approvalStatus
     * @param  mixed  $show
     */
    public function renderApprovalStatus($approvalStatus, $show = null): string
    {
        if ($show === null) {
            $show = $this->shouldShowApprovalStatusIcon($approvalStatus);
        }
        if ($show) {
            return sprintf(
                '<span style="margin-left: 6px" title="%s">%s</span>',
                Locale::trans("torrent.approval.status_text.{$approvalStatus}", [], null),
                Torrent::$approvalStatus[$approvalStatus]['icon']
            );
        }

        return '';
    }

    /** @param  mixed  $approvalStatus */
    public function shouldShowApprovalStatusIcon($approvalStatus): bool
    {
        if (SiteConfig::current()->torrent->approvalStatusIconEnabled()) {
            // 启用审核状态图标，肯定显示
            return true;
        }
        if (
            $approvalStatus != TorrentApprovalStatus::ALLOW->value
            && ! SiteConfig::current()->torrent->approvalStatusNoneVisible()
        ) {
            // 不启用审核状态图标，尽量不显示。在种子不是审核通过状态，而审核不通过又不能被用户看到时，显示
            return true;
        }

        return false;
    }

    public function getApprovalDenyCount(int $ownerId): int
    {
        return (int) Torrent::query()
            ->where('owner', $ownerId)
            ->where('approval_status', TorrentApprovalStatus::DENY->value)
            ->count();
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
     * @param  \Illuminate\Support\Collection<int, mixed>|Collection<int, mixed>  $torrents
     * @param  array<int|string, mixed>  $specificSubCategoryAndTags
     */
    public function changeCategory(\Illuminate\Support\Collection|Collection $torrents, int $sectionId, array $specificSubCategoryAndTags): void
    {
        Permissions::assertHasPermission(Permission::canManageTorrent());
        $torrentIdArr = $torrents->pluck('id')->toArray();
        if (empty($torrentIdArr)) {
            Logger::writeWithContext((string) 'torrents is empty', (string) 'warn', (bool) false);

            return;
        }
        $torrentIdStr = implode(',', $torrentIdArr);
        Logger::writeWithContext((string) "torrentIdStr: {$torrentIdStr}, sectionId: {$sectionId}", (string) 'info', (bool) false);
        $searchBoxRep = $this->searchBoxRepository;
        $sections = $searchBoxRep->listSections(SearchBox::listAllSectionId(), true)->keyBy('id');
        if (! $sections->has($sectionId)) {
            throw new NexusException(Locale::trans('upload.invalid_section', [], null));
        }
        $section = $sections->get($sectionId);
        if (! $section instanceof SearchBox) {
            throw new NexusException(Locale::trans('upload.invalid_section', [], null));
        }
        $validCategoryIdArr = $section->categories->pluck('id')->toArray();
        if (! empty($specificSubCategoryAndTags['category']) && ! in_array($specificSubCategoryAndTags['category'], $validCategoryIdArr)) {
            throw new NexusException(Locale::trans('upload.invalid_category', [], null));
        }
        $categoryId = (int) ($specificSubCategoryAndTags['category'] ?? 0);
        $category = Category::query()->find($categoryId);
        if (! $category instanceof Category) {
            $category = null;
        }
        $baseUpdateQuery = Torrent::query()->whereIn('id', $torrentIdArr);
        $updateCategoryQuery = $baseUpdateQuery->clone();
        if (! empty($validCategoryIdArr)) {
            $updateCategoryQuery->whereNotIn('category', $validCategoryIdArr);
        }
        $updateCategoryResult = $updateCategoryQuery->update(['category' => 0]);
        Logger::writeWithContext((string) sprintf('update category = 0 when category not in: %s, result: %s', implode(', ', $validCategoryIdArr), $updateCategoryResult), (string) 'info', (bool) false);

        foreach (SearchBox::$taxonomies as $name => $info) {
            $relationName = "taxonomy_{$name}";
            $relation = $section->{$relationName};
            if (empty($specificSubCategoryAndTags[$name])) {
                continue;
            }
            // 有指定，看是否有效
            if (! $relation) {
                Logger::writeWithContext((string) "searchBox: {$section->id} no relation of {$name}", (string) 'info', (bool) false);
                throw new NexusException(Locale::trans('upload.not_supported_sub_category_field', ['field' => $name], null));
            }
            $validIdArr = $relation->pluck('id')->toArray();
            $taxonomyId = (int) $specificSubCategoryAndTags[$name];
            if (! in_array($taxonomyId, $validIdArr)) {
                Logger::writeWithContext((string) ("taxonomy {$name}, specific: {$taxonomyId} not in validIdArr: ".implode(', ', $validIdArr)), (string) 'info', (bool) false);
                throw new NexusException(Locale::trans('upload.not_supported_sub_category_field', ['field' => $name], null));
            }

        }
        $operatorId = UserDisplay::currentId();
        $siteLogArr = [];
        foreach ($torrents as $torrent) {
            $siteLogArr[] = [
                'added' => now(),
                'txt' => sprintf('torrent: %s category was set to: %s(%s)', $torrent->id, $category ? $category->name : 'unknown', $category ? $category->id : 0),
                'uid' => $operatorId,
            ];
        }
        DB::transaction(function () use ($torrentIdArr, $categoryId, $siteLogArr) {
            SiteLog::query()->insert($siteLogArr);
            Torrent::query()->whereIn('id', $torrentIdArr)->update(['category' => $categoryId]);
        });
        foreach ($torrents as $torrent) {
            Events::fire(ModelEventEnum::TORRENT_UPDATED, $torrent, null);
        }
        Logger::writeWithContext((string) ("success change to section {$sectionId}, torrent count:".$torrents->count()), (string) 'info', (bool) false);
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

        $downloadRepo = $this->downloadRepository;
        foreach ($idArr as $_id) {
            /** @var Torrent|null $torrent */
            $torrent = $torrentInfo->get($_id);

            if ($torrent instanceof Torrent) {
                $downloadRepo->delPiecesHashCache((string) $torrent->getAttribute('pieces_hash'));
            }

            Logger::writeWithContext("delete torrent: $_id", 'error');
            @unlink(Path::resolve("$torrentDir/$_id.torrent", defined('ROOT_PATH') ? (string) ROOT_PATH : ''));

            TorrentOperationLog::add([
                'torrent_id' => $_id,
                'uid' => UserDisplay::currentId(),
                'action_type' => TorrentOperationAction::DELETE->value,
                'comment' => '',
            ], $notify);

            if ($torrent instanceof Torrent) {
                Events::fire('torrent_deleted', $torrent);
            }
        }

        try {
            $meiliSearchRep = $this->meiliSearchRepository;
            $meiliSearchRep->deleteDocuments($idArr);
        } catch (\Throwable $e) {
            Logger::writeWithContext('MeiliSearch delete on torrent delete failed: '.$e->getMessage(), 'error');
        }
    }
}
