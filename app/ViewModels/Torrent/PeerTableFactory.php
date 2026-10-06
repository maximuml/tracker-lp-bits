<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Services\PermissionChecker;
use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\YesNo;
use App\Support\Ratio;
use App\Support\Strings;

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

        foreach ($arr as $e) {
            $privacy = $privacyData[$e['userid']] ?? '';
            $secs = max(1, $e['la'] - $e['st']);
            $isStrongPrivacy = $privacy === 'strong' || (YesNo::isYes($torrent['anonymous'] ?? null) && $e['userid'] == $torrent['owner']);
            $canView = $this->permissionChecker->userCan('viewanonymous', false, $currentUserId) || $e['userid'] == $currentUserId;

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
                $ratioText = (string) (__('legacy/viewpeerlist.text_inf'));
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
                locationTitle: $enablelocationTweak === 'yes'
                    ? ($canView ? sprintf('%s%s%s', self::plainTitle('legacy/functions.text_user_ip'), ":\u{00A0}", implode(', ', array_column($peerIpInfo[$e['id']] ?? [], 'ip'))) : '')
                    : null,
                locationLines: $enablelocationTweak === 'yes'
                    ? array_column($peerIpInfo[$e['id']] ?? [], 'public')
                    : array_column($peerIpInfo[$e['id']] ?? [], 'ip'),
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
