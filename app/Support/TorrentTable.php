<?php

declare(strict_types=1);

namespace App\Support;

use App\Auth\Permission;
use App\Models\Torrent;
use App\Repositories\TagRepository;
use App\Repositories\TorrentRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Torrent\TorrentStatus;

final class TorrentTable
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function render(array $rows, string $variant = 'torrent', int $searchBoxId = 0): string
    {
        ob_start();

        $cache = app(LegacyRedisCache::class);
        if ($cache === null) {
            throw new \RuntimeException('Cache not initialized');
        }
        $lang_functions = app(Language::class)->functions();
        $user = app(CurrentUser::class)->get() ?? [];
        $waitsystem = (string) app(Globals::class)->get('waitsystem', '');
        $enabletooltip_tweak = (string) app(Globals::class)->get('enabletooltip_tweak', '');

        $torrent = new TorrentStatus;
        $torrentRep = app(TorrentRepository::class);
        $torrentIdArr = $ownerIdArr = [];
        foreach ($rows as $row) {
            $torrentIdArr[] = $row['id'];
            $ownerIdArr[] = $row['owner'];
        }
        unset($row);

        UserDisplay::preload($ownerIdArr);

        $torrentSeedingLeechingStatus = $torrent->listLeechingSeedingStatus($user['id'], $torrentIdArr);
        $tagRep = app(TagRepository::class);
        $torrentTagResult = $torrentRep->getTorrentTagsGrouped($torrentIdArr);
        $showCover = false;
        if ($searchBoxId) {
            $searchBoxExtra = SearchBox::value($cache, $searchBoxId, 'extra');
            if (! empty($searchBoxExtra[\App\Models\SearchBox::EXTRA_DISPLAY_COVER_ON_TORRENT_LIST])) {
                $showCover = true;
            }
        }

        $last_browse = $user['last_browse'];
        $time_now = TIMENOW;
        if ($last_browse > $time_now) {
            $last_browse = $time_now;
        }
        $wait = 0;
        if (UserDisplay::currentClass() < UC_VIP && $waitsystem == 'yes') {
            $ratio = Ratio::forUserId($user['id'], false);
            $gigs = $user['uploaded'] / (1024 * 1024 * 1024);
            if ($gigs > 10) {
                if ($ratio < 0.4) {
                    $wait = 24;
                } elseif ($ratio < 0.5) {
                    $wait = 12;
                } elseif ($ratio < 0.6) {
                    $wait = 6;
                } elseif ($ratio < 0.8) {
                    $wait = 3;
                } else {
                    $wait = 0;
                }
            } else {
                $wait = 0;
            }
        }
        ?>
<table class="torrents" cellspacing="0" cellpadding="5" width="100%">
<tr>
<?php
        $queryParams = [];
        foreach (request()->query() as $get_name => $get_value) {
            if (is_array($get_value) || in_array($get_name, ['sort', 'type'], true)) {
                continue;
            }
            $queryParams[(string) $get_name] = (string) $get_value;
        }
        $oldlink = $queryParams ? str_replace('&', '&amp;', http_build_query($queryParams, '', '&')).'&amp;' : '';
        $sort = request()->query('sort', '');
        $link = [];
        for ($i = 1; $i <= 9; $i++) {
            if ($sort == $i) {
                $link[$i] = (request()->query('type') == 'desc' ? 'asc' : 'desc');
            } else {
                $link[$i] = ($i == 1 ? 'asc' : 'desc');
            }
        }
        ?>
<td class="colhead" style="padding: 0px"><?php echo $lang_functions['col_type'] ?></td>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=1&amp;type=<?php echo $link[1]?>"><?php echo $lang_functions['col_name'] ?></a></td>
<?php

        if ($wait) {
            echo '<td class="colhead">'.$lang_functions['col_wait']."</td>\n";
        }
        if ($user['showcomnum']) { ?>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=3&amp;type=<?php echo $link[3]?>"><img class="comments" src="pic/trans.gif" alt="comments" title="<?php echo $lang_functions['title_number_of_comments'] ?>" /></a></td>
<?php } ?>

<td class="colhead"><a href="?<?php echo $oldlink?>sort=4&amp;type=<?php echo $link[4]?>"><img class="time" src="pic/trans.gif" alt="time" title="<?php echo $user['timetype'] != 'timealive' ? $lang_functions['title_time_added'] : $lang_functions['title_time_alive']?>" /></a></td>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=5&amp;type=<?php echo $link[5]?>"><img class="size" src="pic/trans.gif" alt="size" title="<?php echo $lang_functions['title_size'] ?>" /></a></td>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=7&amp;type=<?php echo $link[7]?>"><img class="seeders" src="pic/trans.gif" alt="seeders" title="<?php echo $lang_functions['title_number_of_seeders'] ?>" /></a></td>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=8&amp;type=<?php echo $link[8]?>"><img class="leechers" src="pic/trans.gif" alt="leechers" title="<?php echo $lang_functions['title_number_of_leechers'] ?>" /></a></td>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=6&amp;type=<?php echo $link[6]?>"><img class="snatched" src="pic/trans.gif" alt="snatched" title="<?php echo $lang_functions['title_number_of_snatched']?>" /></a></td>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=9&amp;type=<?php echo $link[9]?>"><?php echo $lang_functions['col_uploader']?></a></td>
<?php
if (Permission::canManageTorrent()) { ?>
	<td class="colhead"><?php echo $lang_functions['col_action'] ?></td>
<?php } ?>
</tr>
<?php
        $caticonrow = Category::iconRowWithContext($user['caticon']);
        if (is_array($caticonrow) && (bool) ($caticonrow['secondicon'] ?? false)) {
            $has_secondicon = true;
        } else {
            $has_secondicon = false;
        }
        $counter = 0;
        $lastcom_tooltip = [];
        $torrent_tooltip = [];
        foreach ($rows as $row) {
            $id = $row['id'];
            $sphighlight = Promotion::backgroundStyleWithContext($row['sp_state'], $row['pos_state'], $row);
            echo '<tr'.$sphighlight.">\n";

            echo "<td class=\"rowfollow nowrap\" valign=\"middle\" style='padding: 0px'>";
            if (isset($row['category'])) {
                echo Category::imageTagWithContext($row['category'], '?');
                if ($has_secondicon) {
                    echo Category::secondIconWithContext($row);
                }
            } else {
                echo '-';
            }
            echo "</td>\n";

            // torrent name
            $dispname = trim($row['name']);
            $short_torrent_name_alt = 'title="'.htmlspecialchars($dispname).'"';
            $mouseovertorrent = '';
            $count_dispname = mb_strlen($dispname, 'UTF-8');
            $max_length_of_torrent_name = 200;

            if ($count_dispname > $max_length_of_torrent_name) {
                $dispname = mb_substr($dispname, 0, $max_length_of_torrent_name - 2, 'UTF-8').'..';
            }
            if ($user['appendsticky']) {
                $posStates = Torrent::listPosStates();
                $stickyicon = str_repeat('<img class="sticky" src="pic/trans.gif" alt="Sticky" title="'.$posStates[$row['pos_state']]['text'].'" />&nbsp;', $posStates[$row['pos_state']]['icon_counts'] ?? 0);
            } else {
                $stickyicon = '';
            }
            $sp_torrent = Promotion::appendWithContext($row['sp_state'], '', true, $row['added'], $row['promotion_time_type'], $row['promotion_until'], $row['__ignore_global_sp_state'] ?? false);
            $hrImg = TorrentAccess::hrImage($row, $row['search_box_id']);

            // cover
            $coverSrc = $tdCover = '';

            if ($showCover) {
                if (! empty($row['cover'])) {
                    $coverSrc = $row['cover'];
                }
                $tdCover = sprintf('<td class="embedded" style="text-align: center;width: 46px;height: 46px"><img src="pic/misc/spinner.svg" data-src="%s" class="nexus-lazy-load" style="max-height: 46px;max-width: 46px" /></td>', $coverSrc);
            }

            echo "<td class=\"rowfollow\" width=\"100%\" align=\"left\" style='padding: 0px'><table class=\"torrentname\" width=\"100%\"><tr".$sphighlight.">$tdCover<td class=\"embedded\" style='padding-left: 5px'>".$stickyicon."<a $short_torrent_name_alt $mouseovertorrent href=\"details.php?id=".$id.'&amp;hit=1"><b>'.htmlspecialchars($dispname).'</b></a>';
            if ($user['appendnew'] && strtotime($row['added']) >= $last_browse) {
                echo "<b> (<font class='new'>".$lang_functions['text_new_uppercase'].'</font>)</b>';
            }

            $banned_torrent = ($row['banned'] == 1 ? ' <b>(<font class="striking">'.$lang_functions['text_banned'].'</font>)</b>' : '');
            $sp_torrent_sub = Promotion::appendSubWithContext($row['sp_state'], '', true, $row['added'], $row['promotion_time_type'], $row['promotion_until'], $row['__ignore_global_sp_state'] ?? false);
            $approvalStatusIcon = $torrentRep->renderApprovalStatus($row['approval_status']);
            $paidIcon = $torrentRep->getPaidIcon($row);
            $titleSuffix = $banned_torrent.$paidIcon.$sp_torrent.$sp_torrent_sub.$hrImg.$approvalStatusIcon;
            echo $titleSuffix;
            /**
             * render tags
             */
            $tagOwns = $torrentTagResult->get($id);
            if ($tagOwns) {
                $tags = $tagRep->renderSpan($row['search_box_id'], $tagOwns->pluck('tag_id')->toArray());
            } else {
                $tags = '';
            }

            echo $tags ? "<br />$tags" : '';
            // progress bar
            if (isset($torrentSeedingLeechingStatus[$row['id']])) {
                echo $torrent->renderProgressBar($torrentSeedingLeechingStatus[$row['id']]['active_status'], $torrentSeedingLeechingStatus[$row['id']]['progress']);
            }
            echo '</td>';

            $act = '';
            if ($user['dlicon'] && $user['downloadpos']) {
                $act .= '<a href="download.php?id='.$id."\"><img class=\"download\" src=\"pic/trans.gif\" style='padding-bottom: 2px;' alt=\"download\" title=\"".$lang_functions['title_download_torrent'].'" /></a>';
            }
            if ($user['bmicon']) {
                $bookmark = ' href="javascript: bookmark('.$id.','.$counter.');"';
                $act .= ($act ? '<br />' : '').'<a id="bookmark'.$counter.'" '.$bookmark.' >'.TorrentBookmark::stateMarkupWithContext($user['id'], $id).'</a>';
            }

            echo '<td width="20" class="embedded" style="text-align: right;padding-right: 5px" valign="middle">'.$act."</td>\n";

            echo '</tr></table></td>';
            if ($wait) {
                $elapsed = floor((TIMENOW - strtotime($row['added'])) / 3600);
                if ($elapsed < $wait) {
                    $color = dechex((int) (floor(127 * ($wait - $elapsed) / 48 + 128) * 65536));
                    echo '<td class="rowfollow nowrap"><a href="faq.php#id46"><font color="'.$color.'">'.number_format($wait - $elapsed).$lang_functions['text_h']."</font></a></td>\n";
                } else {
                    echo '<td class="rowfollow nowrap">'.$lang_functions['text_none']."</td>\n";
                }
            }

            if ($user['showcomnum']) {
                echo '<td class="rowfollow">';
                $nl = '';

                // comments

                $nl = '<br />';
                if (! $row['comments']) {
                    $commentCount = is_scalar($row['comments']) ? (string) $row['comments'] : '0';
                    echo '<a href="comment.php?action=add&amp;pid='.$id.'&amp;type=torrent" title="'.$lang_functions['title_add_comments'].'">'.$commentCount.'</a>';
                } else {
                    if ($enabletooltip_tweak == 'yes' && $user['showlastcom']) {
                        if (! $lastcom = $cache->get_value('torrent_'.$id.'_last_comment_content')) {
                            $lastcom = $torrentRep->getLastComment((int) $id);
                            $cache->cache_value('torrent_'.$id.'_last_comment_content', $lastcom, 1855);
                        }
                        $timestamp = strtotime($lastcom['added']);
                        $hasnewcom = ($lastcom['user'] != $user['id'] && $timestamp >= $last_browse);
                        $onmouseover = '';
                        if ($lastcom) {
                            if ($user['timetype'] != 'timealive') {
                                $lastcomtime = $lang_functions['text_at_time'].$lastcom['added'];
                            } else {
                                $lastcomtime = $lang_functions['text_blank'].Time::format($lastcom['added'], true, false, true);
                            }
                            $lastcom_tooltip[$counter]['id'] = 'lastcom_'.$counter;
                            $lastcom_tooltip[$counter]['content'] = ($hasnewcom ? "<b>(<font class='new'>".$lang_functions['text_new_uppercase'].'</font>)</b> ' : '').$lang_functions['text_last_commented_by'].UserDisplay::username($lastcom['user']).$lastcomtime.'<br />'.Format::formatComment(mb_substr($lastcom['text'], 0, 100, 'UTF-8').(mb_strlen($lastcom['text'], 'UTF-8') > 100 ? ' ......' : ''), true, false, false, true, 600, false, false);
                            $onmouseover = "onmouseover=\"domTT_activate(this, event, 'content', document.getElementById('".$lastcom_tooltip[$counter]['id']."'), 'trail', false, 'delay', 500,'lifetime',3000,'fade','both','styleClass','niceTitle','fadeMax', 87,'maxWidth', 400);\"";
                        }
                    } else {
                        $hasnewcom = false;
                        $onmouseover = '';
                    }
                    echo '<b><a href="details.php?id='.$id.'&amp;hit=1&amp;cmtpage=1#startcomments" '.$onmouseover.'>'.($hasnewcom ? "<font class='new'>" : '').$row['comments'].($hasnewcom ? '</font>' : '').'</a></b>';
                }

                echo '</td>';
            }

            $time = $row['added'];
            $time = Time::format($time, false, true);
            echo '<td class="rowfollow nowrap">'.$time.'</td>';

            // size
            echo '<td class="rowfollow">'.Format::sizeCompact($row['size']).'</td>';

            if ($row['seeders']) {
                $ratio = ($row['leechers'] ? ($row['seeders'] / $row['leechers']) : 1);
                $ratiocolor = Ratio::seedLeechColor($ratio);
                echo '<td class="rowfollow" align="center"><b><a href="details.php?id='.$id.'&amp;hit=1&amp;dllist=1#seeders">'.($ratiocolor ? '<font color="'.
                $ratiocolor.'">'.number_format($row['seeders']).'</font>' : number_format($row['seeders']))."</a></b></td>\n";
            } else {
                $seederCount = (int) ($row['seeders'] ?? 0);
                echo '<td class="rowfollow"><span class="'.Palette::seederLink($seederCount).'">'.number_format($seederCount)."</span></td>\n";
            }

            if ($row['leechers']) {
                echo '<td class="rowfollow"><b><a href="details.php?id='.$id.'&amp;hit=1&amp;dllist=1#leechers">'.
                number_format($row['leechers'])."</a></b></td>\n";
            } else {
                echo "<td class=\"rowfollow\">0</td>\n";
            }

            if ($row['times_completed'] >= 1) {
                echo '<td class="rowfollow"><a href="viewsnatches.php?id='.$row['id'].'"><b>'.number_format($row['times_completed'])."</b></a></td>\n";
            } else {
                echo '<td class="rowfollow">'.number_format($row['times_completed'])."</td>\n";
            }

            if (
                $row['anonymous'] == 1
                && (Permission::canViewAnonymous() || (isset($row['owner']) && $row['owner'] == $user['id']))
            ) {
                echo '<td class="rowfollow" align="center"><i>'.$lang_functions['text_anonymous'].'</i><br />'.(isset($row['owner']) ? '('.UserDisplay::username($row['owner']).')' : '<i>'.$lang_functions['text_orphaned'].'</i>')."</td>\n";
            } elseif ($row['anonymous'] == 1) {
                echo '<td class="rowfollow"><i>'.$lang_functions['text_anonymous']."</i></td>\n";
            } else {
                echo '<td class="rowfollow">'.(isset($row['owner']) ? UserDisplay::username($row['owner']) : '<i>'.$lang_functions['text_orphaned'].'</i>')."</td>\n";
            }

            if (Permission::canManageTorrent()) {
                $actions = [];
                if (Permission::canDeleteTorrent()) {
                    $actions[] = '<a href="'.htmlspecialchars('fastdelete.php?id='.$row['id']).'"><img class="staff_delete" src="pic/trans.gif" alt="D" title="'.$lang_functions['text_delete'].'" /></a>';
                }
                $actions[] = '<a href="edit.php?returnto='.rawurlencode(Input::serverValue('REQUEST_URI', '')).'&amp;id='.$row['id'].'"><img class="staff_edit" src="pic/trans.gif" alt="E" title="'.$lang_functions['text_edit'].'" /></a>';
                echo sprintf('<td class="rowfollow">%s</td>', implode('<br />', $actions));
            }
            echo "</tr>\n";
            $counter++;
        }
        echo '</table>';
        if ($user['appendpromotion'] == 'highlight') {
            echo '<p align="center"> '.$lang_functions['text_promoted_torrents_note']."</p>\n";
        }

        if ($enabletooltip_tweak == 'yes' && (empty($user) || ($user['showlastcom'] ?? false))) {
            echo Html::tooltipContainer($lastcom_tooltip, 400);
        }
        echo Html::tooltipContainer($torrent_tooltip, 500);

        return (string) ob_get_clean();
    }
}
