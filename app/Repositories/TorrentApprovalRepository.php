<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Enums\TorrentApprovalStatus;
use App\Enums\TorrentOperationAction;
use App\Enums\TorrentPromotion;
use App\Exceptions\InsufficientPermissionException;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\TorrentOperationLog;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\Json;
use App\Support\Locale;
use App\Support\Logger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Torrent approval workflow: modal markup, status transitions with
 * operation logging and owner notification, status icon rendering.
 *
 * Extracted from TorrentModerationRepository to keep both classes under
 * the 400-line ratchet.
 */
class TorrentApprovalRepository extends BaseRepository
{
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
            '<div class="%s-row"><div>%s: </div><div>%s</div></div>',
            $id, $rowStyle, $labelStyle, Locale::trans('torrent.approval.status_label', [], null), implode('', $radios)
        );
        $rows[] = sprintf(
            '<div class="%s-row"><div>%s: </div><div><textarea name="params[comment]" rows="4" cols="40"></textarea></div></div>',
            $id, $rowStyle, $labelStyle, Locale::trans('torrent.approval.comment_label', [], null)
        );
        $rows[] = sprintf('<input type="hidden" name="params[torrent_id]" value="%s" />', $torrent->id);

        $html = sprintf('<div id="%s-box"><form id="%s">%s</form></div>', $id, $formId, implode('', $rows));

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
                '<span title="%s">%s</span>',
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
}
