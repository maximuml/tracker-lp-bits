<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Enums\Permission\PermissionEnum;
use App\Models\Torrent;
use App\Repositories\TorrentModerationRepository;
use App\Services\PermissionChecker;
use App\Support\Category;
use App\Support\Format;
use App\Support\LegacyYesNo;
use App\Support\Locale;
use App\Support\Promotion;
use App\Support\Ratio;
use App\Support\Strings;
use App\Support\TorrentAccess;

/**
 * Builds the UserTorrentListViewModel for the getusertorrentlistajax
 * fragment — extracted from TorrentAjaxController so the Livewire
 * UserTorrentList can run the same row pipeline.
 */
final class UserTorrentListVmFactory
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    /**
     * @param  iterable<int, mixed>  $rows
     * @param  array<string, mixed>  $currentUser
     */
    public function build(iterable $rows, string $mode, int $id, array $currentUser, mixed $seedTimeAndUploaded, TorrentModerationRepository $torrentRep): UserTorrentListViewModel
    {
        $showsize = $showsenum = $showlenum = $showuploaded = $showdownloaded = $showratio = $showsetime = $showletime = $showcotime = $showanonymous = false;
        $showClient = false;
        switch ($mode) {
            case 'uploaded':
                $showsize = $showsenum = $showlenum = $showuploaded = $showsetime = $showanonymous = true;
                break;
            case 'seeding':
                $showsize = $showsenum = $showlenum = $showuploaded = $showdownloaded = $showratio = $showsetime = true;
                $showClient = true;
                break;
            case 'leeching':
                $showsize = $showsenum = $showlenum = $showuploaded = $showdownloaded = $showratio = true;
                $showClient = true;
                break;
            case 'completed':
                $showsize = $showuploaded = $showsetime = $showletime = $showcotime = true;
                break;
            case 'incomplete':
                $showsize = $showuploaded = $showdownloaded = $showratio = $showletime = true;
                break;
        }

        $currentUserId = (int) ($currentUser['id'] ?? 0);
        $shouldShowClient = $showClient
            && ($this->permissionChecker->userCan(PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO->value, false, $currentUserId) || $currentUserId == $id);
        $maxNameLength = ($currentUser['fontsize'] ?? null) == 'large' ? 70 : 80;

        $vmRows = [];
        foreach ($rows as $row) {
            $arr = (array) $row;
            if ($mode === 'uploaded') {
                $seedTimeAndUploadedData = $seedTimeAndUploaded->get($arr['torrent']);
                $arr['seedtime'] = $seedTimeAndUploadedData ? $seedTimeAndUploadedData->seedtime : 0;
                $arr['uploaded'] = $seedTimeAndUploadedData ? $seedTimeAndUploadedData->uploaded : 0;
            }

            $nameTitle = trim((string) $arr['torrentname']);
            $displayName = $nameTitle;
            if (mb_strlen($displayName, 'UTF-8') > $maxNameLength) {
                $displayName = mb_substr($displayName, 0, $maxNameLength, 'UTF-8').'..';
            }

            $uploadedBytes = (float) ($arr['uploaded'] ?? 0);
            $downloadedBytes = (float) ($arr['downloaded'] ?? 0);
            if ($downloadedBytes > 0) {
                $ratioText = number_format($uploadedBytes / $downloadedBytes, 3);
                $ratioClass = Ratio::colorClass($ratioText) ?: null;
            } elseif ($uploadedBytes > 0) {
                $ratioText = 'Inf.';
                $ratioClass = null;
            } else {
                $ratioText = '---';
                $ratioClass = null;
            }

            $added = (string) ($arr['added'] ?? '');
            $categoryIcon = null;
            if (isset($arr['category'])) {
                $catData = Category::iconData($arr['category']);
                $categoryIcon = new CategoryIcon($catData['iconClass'], $catData['name'], '/web/torrents?allsec=1&cat='.$arr['category']);
            }

            $vmRows[] = new UserTorrentRow(
                rowClass: Promotion::rowClassWithContext((int) $arr['sp_state'], '', $arr),
                categoryIcon: $categoryIcon,
                nameUrl: '/web/details/'.$arr['torrent'].'?hit=1',
                nameTitle: $nameTitle,
                displayName: $displayName,
                isBanned: LegacyYesNo::isYes($arr['banned'] ?? null),
                badges: new TorrentBadgeSet(
                    promotion: Promotion::badgeWithContext((int) $arr['sp_state'], '', false, '', 0, '', $arr['__ignore_global_sp_state'] ?? false),
                    hitAndRun: TorrentAccess::requiresHrIcon($arr, $arr['search_box_id'] ?? 0),
                    approval: $torrentRep->shouldShowApprovalStatusIcon($arr['approval_status'])
                        ? new ApprovalBadge(
                            title: (string) Locale::trans("torrent.approval.status_text.{$arr['approval_status']}", [], null),
                            icon: Torrent::approvalStatusIcon((int) $arr['approval_status']),
                        )
                        : null,
                ),
                addedDate: substr($added, 0, 10),
                addedTime: substr($added, 11),
                size: Format::sizeParts((float) ($arr['size'] ?? 0)),
                seeders: (int) ($arr['seeders'] ?? 0),
                leechers: (int) ($arr['leechers'] ?? 0),
                uploaded: Format::sizeParts($uploadedBytes),
                downloaded: Format::sizeParts($downloadedBytes),
                ratioText: $ratioText,
                ratioClass: $ratioClass,
                seedTime: Format::prettyTimeWithLocale((float) ($arr['seedtime'] ?? 0)),
                leechTime: Format::prettyTimeWithLocale((float) ($arr['leechtime'] ?? 0)),
                completedAt: $arr['completedat'] ?? null,
                anonymous: (string) ($arr['anonymous'] ?? ''),
                clientAgent: Strings::userAgentClient((string) ($arr['agent'] ?? '')),
                clientPort: (string) ($arr['port'] ?? ''),
                clientIps: array_values(array_filter([(string) ($arr['ipv4'] ?? ''), (string) ($arr['ipv6'] ?? '')])),
            );
        }

        return new UserTorrentListViewModel(
            rows: $vmRows,
            showSize: $showsize,
            showSeeders: $showsenum,
            showLeechers: $showlenum,
            showUploaded: $showuploaded,
            showDownloaded: $showdownloaded,
            showRatio: $showratio,
            showSeedTime: $showsetime,
            showLeechTime: $showletime,
            showCompletedAt: $showcotime,
            showAnonymous: $showanonymous,
            showClient: $shouldShowClient,
        );
    }
}
