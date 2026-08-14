<?php
//Send some headers to keep the user's browser from caching the response.
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . "GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Content-Type: text/html; charset=utf-8");

$CURUSER = \App\Support\SupportContext::getUser() ?? [];
if (empty($CURUSER)) {
    return;
}

$lang_functions = \App\Support\SupportContext::getLangFunctions();
$lang_viewpeerlist = (array) (\App\Support\SupportContext::getGlobal('lang_viewpeerlist') ?? []);

$torrent = (array) ($torrent ?? []);
$seeders = (array) ($seeders ?? []);
$leechers = (array) ($leechers ?? []);
$privacyData = (array) ($privacyData ?? []);
$showLocationColumn = (bool) ($showLocationColumn ?? false);
$enablelocationTweak = \App\Support\SupportContext::getGlobal('enablelocation_tweak');
$seedBoxRep = $seedBoxRep ?? new \App\Repositories\SeedBoxRepository();

if (! function_exists('get_location_column')) {
    function get_location_column($e, $isStrongPrivacy, $canView, $enablelocationTweak, $seedBoxRep, $lang_functions, $lang_viewpeerlist): array
    {
        $address = $ips = [];

        if ($enablelocationTweak === 'yes') {
            if (! empty($e['ipv4'])) {
                [$loc_pub, $loc_mod] = \App\Support\Network::ipLocationWithContext($e['ipv4']);
                $seedBoxIcon = $seedBoxRep->renderIcon($e['ipv4'], $e['userid']);
                $address[] = $loc_pub . $seedBoxIcon;
                $ips[] = $e['ipv4'];
            }
            if (! empty($e['ipv6'])) {
                [$loc_pub, $loc_mod] = \App\Support\Network::ipLocationWithContext($e['ipv6']);
                $seedBoxIcon = $seedBoxRep->renderIcon($e['ipv6'], $e['userid']);
                $address[] = $loc_pub . $seedBoxIcon;
                $ips[] = $e['ipv6'];
            }
            $title = $canView ? sprintf('%s%s%s', $lang_functions['text_user_ip'], ':&nbsp;', implode(', ', $ips)) : '';
            $addressStr = implode('<br/>', $address);
            $location = '<div style="margin-right: 6px" title="'.$title.'">'.$addressStr.'</div>';
        } else {
            if (! empty($e['ipv4'])) {
                $seedBoxIcon = $seedBoxRep->renderIcon($e['ipv4'], $e['userid']);
                $ips[] = $e['ipv4'] . $seedBoxIcon;
            }
            if (! empty($e['ipv6'])) {
                $seedBoxIcon = $seedBoxRep->renderIcon($e['ipv6'], $e['userid']);
                $ips[] = $e['ipv6'] . $seedBoxIcon;
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
            'is_seed_box' => ! empty($seedBoxIcon ?? ''),
        ];
    }
}

if (! function_exists('get_username_seed_box_icon')) {
    function get_username_seed_box_icon($e, $seedBoxRep): string
    {
        foreach (array_filter([$e['ipv4'], $e['ipv6']]) as $ip) {
            $icon = $seedBoxRep->renderIcon($ip, $e['userid']);
            if (! empty($icon)) {
                return $icon;
            }
        }

        return '';
    }
}

if (! function_exists('dltable')) {
    function dltable($name, $arr, $torrent, $privacyData, $showLocationColumn, $enablelocationTweak, $seedBoxRep, $lang_viewpeerlist, $lang_functions, $CURUSER)
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
                $columnLocationResult = get_location_column($e, $isStrongPrivacy, $canView, $enablelocationTweak, $seedBoxRep, $lang_functions, $lang_viewpeerlist);
                $columnLocation = $columnLocationResult['td'];
            } else {
                $usernameSeedBoxIcon = get_username_seed_box_icon($e, $seedBoxRep);
            }

            if ($isStrongPrivacy) {
                $columnUsername = "<td class=rowfollow align=left width=1%><i>".$lang_viewpeerlist['text_anonymous'].'</i>'.$usernameSeedBoxIcon;
                if ($canView) {
                    $columnUsername .= '<br />(' . \App\Support\UserDisplay::username($e['userid']) . ')';
                }
                $columnUsername .= '</td>';
            } else {
                $columnUsername = '<td class=rowfollow align=left width=1%>' . \App\Support\UserDisplay::username($e['userid']).$usernameSeedBoxIcon.'</td>';
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

$seederTable = dltable($lang_viewpeerlist['text_seeders'], $seeders, $torrent, $privacyData, $showLocationColumn, $enablelocationTweak, $seedBoxRep, $lang_viewpeerlist, $lang_functions, $CURUSER);
$leecherTable = dltable($lang_viewpeerlist['text_leechers'], $leechers, $torrent, $privacyData, $showLocationColumn, $enablelocationTweak, $seedBoxRep, $lang_viewpeerlist, $lang_functions, $CURUSER);
print $seederTable . $leecherTable;
