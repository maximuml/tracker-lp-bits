<?php
$type = (string) ($type ?? '');
$id = (int) ($id ?? 0);
$count = (int) ($count ?? 0);
$total_size = (float) ($total_size ?? 0);
$rows = (array) ($rows ?? []);
$pagertop = (string) ($pagertop ?? '');
$pagerbottom = (string) ($pagerbottom ?? '');
$torrentRep = $torrentRep ?? null;
$seedBoxRep = $seedBoxRep ?? null;
$seedTimeAndUploaded = $seedTimeAndUploaded ?? collect();

if (! function_exists('maketable')) {
    function maketable($rows, $mode, $id, $currentUser, $seedTimeAndUploaded, $torrentRep, $seedBoxRep, $lang_getusertorrentlistajax, $lang_functions)
    {
        $showsize = $showsenum = $showlenum = $showuploaded = $showdownloaded = $showratio = $showsetime = $showletime = $showcotime = $showanonymous = $showtotalsize = false;
        $columncount = 7;
        $showClient = false;
        switch ($mode) {
            case 'uploaded':
                $showsize = true; $showsenum = true; $showlenum = true; $showuploaded = true; $showdownloaded = false; $showratio = false; $showsetime = true; $showletime = false; $showcotime = false; $showanonymous = true; $showtotalsize = true; $columncount = 8;
                break;
            case 'seeding':
                $showsize = true; $showsenum = true; $showlenum = true; $showuploaded = true; $showdownloaded = true; $showratio = true; $showsetime = true; $showletime = false; $showcotime = false; $showanonymous = false; $showtotalsize = true; $columncount = 8; $showClient = true;
                break;
            case 'leeching':
                $showsize = true; $showsenum = true; $showlenum = true; $showuploaded = true; $showdownloaded = true; $showratio = true; $showsetime = false; $showletime = false; $showcotime = false; $showanonymous = false; $showtotalsize = true; $columncount = 8; $showClient = true;
                break;
            case 'completed':
                $showsize = true; $showsenum = false; $showlenum = false; $showuploaded = true; $showdownloaded = false; $showratio = false; $showsetime = true; $showletime = true; $showcotime = true; $showanonymous = false; $showtotalsize = false;
                break;
            case 'incomplete':
                $showsize = true; $showsenum = false; $showlenum = false; $showuploaded = true; $showdownloaded = true; $showratio = true; $showsetime = false; $showletime = true; $showcotime = false; $showanonymous = false; $showtotalsize = false; $columncount = 7;
                break;
        }

        $shouldShowClient = false;
        $currentUserId = (int) ($currentUser['id'] ?? 0);
        if ($showClient && (\App\Support\Permissions::userCan(\App\Enums\Permission\PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO->value, false, $currentUserId) || $currentUserId == $id)) {
            $shouldShowClient = true;
        }

        $results = [];
        foreach ($rows as $row) {
            $results[] = (array) $row;
        }

        $ret = "<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" width=\"100%\"><tr><td class=\"colhead\" style=\"padding: 0px\">".$lang_getusertorrentlistajax['col_type']."</td><td class=\"colhead\" align=\"center\">".$lang_getusertorrentlistajax['col_name']."</td><td class=\"colhead\" align=\"center\">".$lang_getusertorrentlistajax['col_added']."</td>".
            ($showsize ? "<td class=\"colhead\" align=\"center\"><img class=\"size\" src=\"pic/trans.gif\" alt=\"size\" title=\"".$lang_getusertorrentlistajax['title_size']."\" /></td>" : "").
            ($showsenum ? "<td class=\"colhead\" align=\"center\"><img class=\"seeders\" src=\"pic/trans.gif\" alt=\"seeders\" title=\"".$lang_getusertorrentlistajax['title_seeders']."\" /></td>" : "").
            ($showlenum ? "<td class=\"colhead\" align=\"center\"><img class=\"leechers\" src=\"pic/trans.gif\" alt=\"leechers\" title=\"".$lang_getusertorrentlistajax['title_leechers']."\" /></td>" : "").
            ($showuploaded ? "<td class=\"colhead\" align=\"center\">".$lang_getusertorrentlistajax['col_uploaded']."</td>" : "").
            ($showdownloaded ? "<td class=\"colhead\" align=\"center\">".$lang_getusertorrentlistajax['col_downloaded']."</td>" : "").
            ($showratio ? "<td class=\"colhead\" align=\"center\">".$lang_getusertorrentlistajax['col_ratio']."</td>" : "").
            ($showsetime ? "<td class=\"colhead\" align=\"center\">".$lang_getusertorrentlistajax['col_se_time']."</td>" : "").
            ($showletime ? "<td class=\"colhead\" align=\"center\">".$lang_getusertorrentlistajax['col_le_time']."</td>" : "").
            ($showcotime ? "<td class=\"colhead\" align=\"center\">".$lang_getusertorrentlistajax['col_time_completed']."</td>" : "").
            ($showanonymous ? "<td class=\"colhead\" align=\"center\">".$lang_getusertorrentlistajax['col_anonymous']."</td>" : "");
        if ($shouldShowClient) {
            $ret .= sprintf('<td class="colhead" align="center">%s</td><td class="colhead" align="center">IP</td>', $lang_getusertorrentlistajax['col_client']);
        }
        $ret .= "</tr>";

        $total_size = 0;
        foreach ($results as $arr) {
            if ($mode == 'uploaded') {
                $seedTimeAndUploadedData = $seedTimeAndUploaded->get($arr['torrent']);
                $arr['seedtime'] = $seedTimeAndUploadedData ? $seedTimeAndUploadedData->seedtime : 0;
                $arr['uploaded'] = $seedTimeAndUploadedData ? $seedTimeAndUploadedData->uploaded : 0;
            }

            $catimage = htmlspecialchars($arr["image"]);
            $catname = htmlspecialchars($arr["catname"]);

            $sphighlight = \App\Support\Promotion::backgroundStyleWithContext($arr['sp_state']);
            $banned_torrent = ($arr["banned"] == 'yes' ? " <b>(<font class=\"striking\">".$lang_functions['text_banned']."</font>)</b>" : "");
            $sp_torrent = \App\Support\Promotion::appendWithContext($arr['sp_state'], '', false, '', 0, '', $arr['__ignore_global_sp_state'] ?? false);
            if ($showtotalsize) {
                $total_size += $arr['size'];
            }

            $hrImg = \App\Support\TorrentAccess::hrImage($arr, $arr['search_box_id']);
            $approvalStatusIcon = $torrentRep->renderApprovalStatus($arr["approval_status"]);

            $dispname = $nametitle = htmlspecialchars($arr["torrentname"]);
            $count_dispname = mb_strlen($dispname, "UTF-8");
            $max_lenght_of_torrent_name = ($currentUser['fontsize'] == 'large' ? 70 : 80);
            if ($count_dispname > $max_lenght_of_torrent_name) {
                $dispname = mb_substr($dispname, 0, $max_lenght_of_torrent_name, "UTF-8") . "..";
            }

            $ret .= "<tr" .  $sphighlight  . "><td class=\"rowfollow nowrap\" valign=\"middle\" style='padding: 0px'>".
                \App\Support\Category::imageTagWithContext($arr['category'], "torrents.php?allsec=1&amp;").
                "</td>\n" .
                "<td class=\"rowfollow\" width=\"100%\" align=\"left\"><a href=\"".
                htmlspecialchars("details.php?id=".$arr['torrent']."&hit=1").
                "\" title=\"".$nametitle."\"><b>" . $dispname . "</b></a>".
                $banned_torrent . $sp_torrent . $hrImg . $approvalStatusIcon  . "</td>";
            $ret .= sprintf('<td class="rowfollow nowrap" align="center">%s<br/>%s</td>', substr($arr['added'], 0, 10), substr($arr['added'], 11));

            if ($showsize) {
                $ret .= "<td class=\"rowfollow\" align=\"center\">". \App\Support\Format::sizeCompact($arr['size'])."</td>";
            }
            if ($showsenum) {
                $ret .= "<td class=\"rowfollow\" align=\"center\">".$arr['seeders']."</td>";
            }
            if ($showlenum) {
                $ret .= "<td class=\"rowfollow\" align=\"center\">".$arr['leechers']."</td>";
            }
            if ($showuploaded) {
                $uploaded = \App\Support\Format::sizeCompact($arr["uploaded"]);
                $ret .= "<td class=\"rowfollow\" align=\"center\">".$uploaded."</td>";
            }
            if ($showdownloaded) {
                $downloaded = \App\Support\Format::sizeCompact($arr["downloaded"]);
                $ret .= "<td class=\"rowfollow\" align=\"center\">".$downloaded."</td>";
            }
            if ($showratio) {
                if ($arr['downloaded'] > 0) {
                    $ratio = number_format($arr['uploaded'] / $arr['downloaded'], 3);
                    $ratio = "<font color=\"" . \App\Support\Ratio::color($ratio) . "\">".$ratio."</font>";
                } elseif ($arr['uploaded'] > 0) {
                    $ratio = "Inf.";
                } else {
                    $ratio = "---";
                }
                $ret .= "<td class=\"rowfollow\" align=\"center\">".$ratio."</td>";
            }
            if ($showsetime) {
                $ret .= "<td class=\"rowfollow\" align=\"center\">".\App\Support\Format::prettyTimeWithLocale($arr['seedtime'])."</td>";
            }
            if ($showletime) {
                $ret .= "<td class=\"rowfollow\" align=\"center\">".\App\Support\Format::prettyTimeWithLocale($arr['leechtime'])."</td>";
            }
            if ($showcotime) {
                $ret .= "<td class=\"rowfollow\" align=\"center\">"."". str_replace("&nbsp;", "<br />", \App\Support\Time::format($arr['completedat'], false)). "</td>";
            }
            if ($showanonymous) {
                $ret .= "<td class=\"rowfollow\" align=\"center\">".$arr['anonymous']."</td>";
            }
            if ($shouldShowClient) {
                $ipArr = array_filter([$arr['ipv4'], $arr['ipv6']]);
                foreach ($ipArr as &$_ip) {
                    $_ip = sprintf('<span class="nowrap">%s</span>', $_ip . $seedBoxRep->renderIcon($_ip, $arr['userid']));
                }
                $ret .= sprintf(
                    '<td class="rowfollow" align="center">%s<br/>%s</td><td class="rowfollow" align="center">%s</td>',
                    \App\Support\Strings::userAgentClient($arr['agent']), $arr['port'],
                    implode('<br/>', $ipArr)
                );
            }
            $ret .= "</tr>\n";
        }

        $ret .= "</table>\n";

        return [$ret, $total_size];
    }
}

$torrentlist = '';
if ($count > 0 && ! empty($rows)) {
    list($torrentlist, $total_size_this_page) = maketable($rows, $type, $id, $CURUSER, $seedTimeAndUploaded, $torrentRep, $seedBoxRep, $lang_getusertorrentlistajax, $lang_functions);
}

$table = $pagertop . $torrentlist . $pagerbottom;
$hasData = false;
$summary = sprintf('<b>%s</b>%s', $count, $lang_getusertorrentlistajax['text_record'] . \App\Support\Strings::addS($count));
if ($total_size) {
    $hasData = true;
    $summary .= $lang_getusertorrentlistajax['text_total_size'] . \App\Support\Format::size($total_size);
} elseif ($count) {
    $hasData = true;
}
if ($hasData) {
    $btnArr = \App\Support\Hooks::applyFilter("user_seeding_top_btn", [], $CURUSER['id'] ?? 0);
    $header = sprintf('<div style="display: flex;justify-content: space-between"><div>%s</div><div>%s</div></div>', $summary, implode("", $btnArr));
    echo '<br/>' . $header . $table;
} else {
    echo $lang_getusertorrentlistajax['text_no_record'];
}
