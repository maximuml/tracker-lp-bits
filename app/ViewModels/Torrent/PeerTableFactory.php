<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Enums\Permission\PermissionEnum;
use App\Services\PermissionChecker;
use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\Ratio;
use App\Support\Strings;
use App\Support\YesNo;

final class PeerTableFactory
{
    public function __construct(
        private readonly PermissionChecker $permissionChecker,
    ) {}

    /**
     * @param  array<array<string, mixed>>  $arr
     * @param  array<string, mixed>  $torrent
     * @param  array<int, string>  $privacyData
     * @param  array<int, list<array<string, string>>>  $peerIpInfo
     * @param  array<int, SafeHtml>  $usernameHtmlMap
     * @param  array<string, mixed>  $curUser
     */
    public function buildTable(string $name, array $arr, array $torrent, array $privacyData, bool $showLocationColumn, mixed $enablelocationTweak, array $peerIpInfo, array $usernameHtmlMap, array $curUser): PeerTableViewModel
    {
        $rows = [];
        $now = time();
        $currentUserId = (int) ($curUser['id'] ?? 0);
        $locationEnabled = YesNo::isYes($enablelocationTweak);
        $canViewAnonymous = $this->permissionChecker->userCan(PermissionEnum::VIEW_ANONYMOUS->value, false, $currentUserId);
        $canViewConfidential = $this->permissionChecker->userCan(PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO->value, false, $currentUserId);

        foreach ($arr as $e) {
            $privacy = $privacyData[$e['userid']] ?? '';
            $secs = max(1, $e['la'] - $e['st']);
            $isStrongPrivacy = $privacy === 'strong' || (YesNo::isYes($torrent['anonymous'] ?? null) && $e['userid'] == $torrent['owner']);
            $canView = $canViewAnonymous || $e['userid'] == $currentUserId;

            $uploaded = (float) $e['uploaded'];
            $downloaded = (float) $e['downloaded'];
            if (YesNo::isNo($e['seeder'] ?? null)) {
                $downloadRate = Format::size(($downloaded - $e['downloadoffset']) / $secs);
            } else {
                $downloadRate = Format::size(($downloaded - $e['downloadoffset']) / max(1, $e['finishedat'] - $e['st']));
            }

            if ($downloaded) {
                $ratio = floor(($uploaded / $downloaded) * 1000) / 1000;
                $ratioClass = Ratio::colorClass($ratio);
                $ratioText = number_format($ratio, 3);
            } elseif ($uploaded) {
                $ratioClass = null;
                $ratioText = (string) (__('viewpeerlist.text_inf'));
            } else {
                $ratioClass = null;
                $ratioText = '---';
            }

            $rows[] = new PeerRowViewModel(
                highlighted: $currentUserId == $e['userid'],
                anonymous: $isStrongPrivacy,
                revealUsername: $canView,
                username: $usernameHtmlMap[$e['userid']] ?? SafeHtml::fromPlainText(''),
                revealLocation: ! $isStrongPrivacy || $canView,
                // Raw IPs go to the title only for viewers with the
                // confidential-info right (or the peer's owner). The
                // enablelocation tweak arrives as bool — a strict 'yes'
                // comparison silently fell into the raw-IP branch and
                // leaked addresses to every viewer.
                locationTitle: $locationEnabled
                    ? ($canViewConfidential || $e['userid'] == $currentUserId
                        ? sprintf('%s%s%s', self::plainTitle('functions.text_user_ip'), ":\u{00A0}", implode(', ', array_column($peerIpInfo[$e['id']] ?? [], 'ip')))
                        : '')
                    : null,
                locationLines: $locationEnabled
                    ? array_column($peerIpInfo[$e['id']] ?? [], 'public')
                    : (($canViewConfidential || $e['userid'] == $currentUserId)
                        ? array_column($peerIpInfo[$e['id']] ?? [], 'ip')
                        : []),
                connectableYes: YesNo::isYes($e['connectable'] ?? null),
                uploaded: Format::size($uploaded),
                uploadRate: Format::size(($uploaded - $e['uploadoffset']) / $secs),
                downloaded: Format::size($downloaded),
                downloadRate: $downloadRate,
                ratioClass: $ratioClass,
                ratioText: $ratioText,
                completePercent: sprintf('%.2f%%', 100 * (1 - ($e['to_go'] / max(1, $torrent['size'])))),
                connected: Format::prettyTimeWithLocale($now - $e['st']),
                idle: Format::prettyTimeWithLocale($now - $e['la']),
                client: Strings::userAgentClient($e['agent']),
            );
        }

        return new PeerTableViewModel(
            name: $name,
            count: count($arr),
            showLocationColumn: $showLocationColumn,
            rows: $rows,
        );
    }

    private static function plainTitle(string $key): string
    {
        return html_entity_decode((string) __($key), ENT_QUOTES | ENT_HTML401, 'UTF-8');
    }
}
