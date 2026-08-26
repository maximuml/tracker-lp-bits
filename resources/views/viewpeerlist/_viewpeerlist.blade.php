<?php
$lang_functions = $lang_functions ?? \app(\App\Support\Language::class)->functions();
$lang_viewpeerlist = (array) ($lang_viewpeerlist ?? \app(\App\Support\Globals::class)->get('lang_viewpeerlist') ?? []);

$torrent = (array) ($torrent ?? []);
$seeders = (array) ($seeders ?? []);
$leechers = (array) ($leechers ?? []);
$privacyData = (array) ($privacyData ?? []);
$showLocationColumn = (bool) ($showLocationColumn ?? false);
$enablelocationTweak = $enablelocationTweak ?? \app(\App\Support\Globals::class)->get('enablelocation_tweak');
$peerIpInfo = (array) ($peerIpInfo ?? []);
$usernameSeedBoxIconMap = (array) ($usernameSeedBoxIconMap ?? []);
$usernameHtmlMap = (array) ($usernameHtmlMap ?? []);

if (! function_exists('get_location_column')) {
    /**
     * @param array<string, mixed> $e
     * @param array<int, list<array<string, string>>> $peerIpInfo
     * @param array<string, string> $lang_functions
     * @param array<string, string> $lang_viewpeerlist
     */
    function get_location_column($e, $isStrongPrivacy, $canView, $enablelocationTweak, $peerIpInfo, $lang_functions, $lang_viewpeerlist): array
    {
        $address = $ips = [];
        $info = $peerIpInfo[$e['id']] ?? [];
        $isSeedBox = false;

        if ($enablelocationTweak === 'yes') {
            foreach ($info as $ipInfo) {
                $address[] = $ipInfo['public'] . $ipInfo['seedBoxIcon'];
                $ips[] = $ipInfo['ip'];
                if ($ipInfo['seedBoxIcon'] !== '') {
                    $isSeedBox = true;
                }
            }
            $title = $canView ? sprintf('%s%s%s', $lang_functions['text_user_ip'], ':&nbsp;', implode(', ', $ips)) : '';
            $addressStr = implode('<br/>', $address);
            $location = '<div style="margin-right: 6px" title="'.$title.'">'.$addressStr.'</div>';
        } else {
            foreach ($info as $ipInfo) {
                $ips[] = $ipInfo['ip'] . $ipInfo['seedBoxIcon'];
                if ($ipInfo['seedBoxIcon'] !== '') {
                    $isSeedBox = true;
                }
            }
            $location = '<div style="margin-right: 6px">'.implode('<br/>', $ips).'</div>';
        }

        if ($isStrongPrivacy) {
            $result = '<div><i>'.$lang_viewpeerlist['text_anonymous'].'</i></div>';
            if ($canView) {
                $result = $location . $result;
            }
        } else {
            $result = $location;
        }

        return [
            'td' => "<td class=rowfollow align=left width=1%><div style='display: flex;white-space: nowrap;align-items: center'>" . $result . '</div></td>',
            'is_seed_box' => $isSeedBox,
        ];
    }
}

if (! function_exists('get_username_seed_box_icon')) {
    /**
     * @param array<string, mixed> $e
     * @param array<int, string> $usernameSeedBoxIconMap
     */
    function get_username_seed_box_icon($e, $usernameSeedBoxIconMap): string
    {
        return $usernameSeedBoxIconMap[$e['id']] ?? '';
    }
}

if (! function_exists('dltable')) {
    /**
     * @param array<array<string, mixed>> $arr
     * @param array<int, string> $privacyData
     * @param array<int, list<array<string, string>>> $peerIpInfo
     * @param array<int, string> $usernameSeedBoxIconMap
     * @param array<int, string> $usernameHtmlMap
     * @param array<string, string> $lang_viewpeerlist
     * @param array<string, string> $lang_functions
     */
    function dltable($name, $arr, $torrent, $privacyData, $showLocationColumn, $enablelocationTweak, $peerIpInfo, $usernameSeedBoxIconMap, $usernameHtmlMap, $lang_viewpeerlist, $lang_functions, $CURUSER)
    {
        $s = '<b>' . count($arr) . ' ' . $name . "</b>\n";
        if (! count($arr)) {
            return $s;
        }

        $s .= "\n";
        $s .= "<table width=100% class=main border=1 cellspacing=0 cellpadding=3>\n";
        $s .= "<tr><td class=colhead align=center width=1%>".$lang_viewpeerlist['col_user_ip'].'</td>' .
            ($showLocationColumn ? "<td class=colhead align=center>".$lang_viewpeerlist['col_location'].'</td>' : '') .
            "<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_connectable'].'</td>'.
            "<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_uploaded'].'</td>'.
            "<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_rate'].'</td>' .
            "<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_downloaded'].'</td>' .
            "<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_rate'].'</td>' .
            "<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_ratio'].'</td>' .
            "<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_complete'].'</td>' .
            "<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_connected'].'</td>' .
            "<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_idle'].'</td>' .
            "<td class=colhead align=center width=1%>".$lang_viewpeerlist['col_client'].'</td></tr>\n';
        $now = time();
        $num = 0;

        foreach ($arr as $e) {
            $e = (array) $e;
            $privacy = $privacyData[$e['userid']] ?? '';
            ++$num;

            $highlight = $CURUSER['id'] == $e['userid'] ? ' bgcolor=#BBAF9B' : '';
            $s .= "<tr$highlight>\n";
            $secs = max(1, ($e['la'] - $e['st']));
            $columnLocation = $usernameSeedBoxIcon = '';
            $currentUserId = (int) ($CURUSER['id'] ?? 0);
            $isStrongPrivacy = $privacy == 'strong' || ($torrent['anonymous'] == 'yes' && $e['userid'] == $torrent['owner']);
            $canView = \App\Support\Permissions::userCan('viewanonymous', false, $currentUserId) || $e['userid'] == $currentUserId;
            if ($showLocationColumn) {
                $columnLocationResult = get_location_column($e, $isStrongPrivacy, $canView, $enablelocationTweak, $peerIpInfo, $lang_functions, $lang_viewpeerlist);
                $columnLocation = $columnLocationResult['td'];
            } else {
                $usernameSeedBoxIcon = get_username_seed_box_icon($e, $usernameSeedBoxIconMap);
            }

            $usernameHtml = $usernameHtmlMap[$e['userid']] ?? '';
            if ($isStrongPrivacy) {
                $columnUsername = "<td class=rowfollow align=left width=1%><i>".$lang_viewpeerlist['text_anonymous'].'</i>'.$usernameSeedBoxIcon;
                if ($canView) {
                    $columnUsername .= '<br />(' . $usernameHtml . ')';
                }
                $columnUsername .= '</td>';
            } else {
                $columnUsername = '<td class=rowfollow align=left width=1%>' . $usernameHtml.$usernameSeedBoxIcon.'</td>';
            }

            $s .= $columnUsername . $columnLocation;

            $s .= '<td class=rowfollow align=center width=1%><nobr>' . ($e['connectable'] == 'yes' ? $lang_viewpeerlist['text_yes'] : '<font color=red>'.$lang_viewpeerlist['text_no'].'</font>') . "</nobr></td>\n";
            $s .= '<td class=rowfollow align=center width=1%><nobr>' . \App\Support\Format::size($e['uploaded']) . "</nobr></td>\n";
            $s .= '<td class=rowfollow align=center width=1%><nobr>' . \App\Support\Format::size(($e['uploaded'] - $e['uploadoffset']) / $secs) . "/s</nobr></td>\n";
            $s .= '<td class=rowfollow align=center width=1%><nobr>' . \App\Support\Format::size($e['downloaded']) . "</nobr></td>\n";

            if ($e['seeder'] == 'no') {
                $s .= '<td class=rowfollow align=center width=1%><nobr>' . \App\Support\Format::size(($e['downloaded'] - $e['downloadoffset']) / $secs) . "/s</nobr></td>\n";
            } else {
                $s .= '<td class=rowfollow align=center width=1%><nobr>' . \App\Support\Format::size(($e['downloaded'] - $e['downloadoffset']) / max(1, $e['finishedat'] - $e['st'])) . "/s</nobr></td>\n";
            }

            if ($e['downloaded']) {
                $ratio = floor(($e['uploaded'] / $e['downloaded']) * 1000) / 1000;
                $s .= '<td class=rowfollow align="center" width=1%><font color=' . \App\Support\Ratio::color($ratio) . '><nobr>' . number_format($ratio, 3) . "</nobr></font></td>\n";
            } elseif ($e['uploaded']) {
                $s .= '<td class=rowfollow align=center width=1%>'.$lang_viewpeerlist['text_inf'].'</td>';
            } else {
                $s .= '<td class=rowfollow align=center width=1%>---</td>';
            }

            $s .= '<td class=rowfollow align=center width=1%><nobr>' . sprintf('%.2f%%', 100 * (1 - ($e['to_go'] / max(1, $torrent['size'])))) . "</nobr></td>\n";
            $s .= '<td class=rowfollow align=center width=1%><nobr>' . \App\Support\Format::prettyTimeWithLocale($now - $e['st']) . "</nobr></td>\n";
            $s .= '<td class=rowfollow align=center width=1%><nobr>' . \App\Support\Format::prettyTimeWithLocale($now - $e['la']) . "</nobr></td>\n";
            $s .= '<td class=rowfollow align=center width=1%><nobr>' . htmlspecialchars(\App\Support\Strings::userAgentClient($e['agent'])) . "</nobr></td>\n";
            $s .= "</tr>\n";
        }

        $s .= "</table>\n";

        return $s;
    }
}

$seederTable = dltable($lang_viewpeerlist['text_seeders'], $seeders, $torrent, $privacyData, $showLocationColumn, $enablelocationTweak, $peerIpInfo, $usernameSeedBoxIconMap, $usernameHtmlMap, $lang_viewpeerlist, $lang_functions, $CURUSER);
$leecherTable = dltable($lang_viewpeerlist['text_leechers'], $leechers, $torrent, $privacyData, $showLocationColumn, $enablelocationTweak, $peerIpInfo, $usernameSeedBoxIconMap, $usernameHtmlMap, $lang_viewpeerlist, $lang_functions, $CURUSER);
print $seederTable . $leecherTable;
