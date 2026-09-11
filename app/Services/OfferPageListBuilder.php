<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\OfferAllowed;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserTimeType;
use App\Repositories\OfferRepository;
use App\Repositories\UsercpRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Category;
use App\Support\Config\SiteConfig;
use App\Support\Format;
use App\Support\Frame;
use App\Support\Html;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\Pagination;
use App\Support\Time;
use App\Support\UserClass;
use App\Support\UserDisplay;
use Illuminate\Http\Request;

final class OfferPageListBuilder
{
    public function __construct(
        private readonly OfferRepository $offerRepository,
        private readonly LegacyRedisCache $cache,
        private readonly UsercpRepository $usercpRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>  $globalData
     * @return array<string, mixed>
     */
    public function build(array $lang, array $curUser, int $userId, Request $request, array $globalData): array
    {
        // Validate sort
        $sort = '';
        $sortParam = (string) $request->query('sort', '');
        $allowedSorts = ['cat', 'name', 'added', 'comments', 'yeah', 'against', 'v_res'];
        if (in_array($sortParam, $allowedSorts, true)) {
            $sort = $sortParam;
        } elseif ($sortParam !== '') {
            LegacyResponse::abort((string) ($lang['std_error'] ?? ''), (string) ($lang['std_smell_rat'] ?? ''));
        }

        $catOrderType = 'desc';
        $nameOrderType = 'desc';
        $addedOrderType = 'desc';
        $commentsOrderType = 'desc';
        $vResOrderType = 'desc';

        $sortColumn = '';
        if ($sort === 'cat') {
            if ($request->query('type') === 'desc') {
                $catOrderType = 'asc';
            }
            $sortColumn = ' ORDER BY category '.$catOrderType;
        } elseif ($sort === 'name') {
            if ($request->query('type') === 'desc') {
                $nameOrderType = 'asc';
            }
            $sortColumn = ' ORDER BY name '.$nameOrderType;
        } elseif ($sort === 'added') {
            if ($request->query('type') === 'desc') {
                $addedOrderType = 'asc';
            }
            $sortColumn = ' ORDER BY added '.$addedOrderType;
        } elseif ($sort === 'comments') {
            if ($request->query('type') === 'desc') {
                $commentsOrderType = 'asc';
            }
            $sortColumn = ' ORDER BY comments '.$commentsOrderType;
        } elseif ($sort === 'v_res') {
            if ($request->query('type') === 'desc') {
                $vResOrderType = 'asc';
            }
            $sortColumn = ' ORDER BY (yeah - against) '.$vResOrderType;
        }

        $direction = strtolower((string) $request->query('type', '')) === 'asc' ? 'asc' : 'desc';
        $perpage = 25;
        $categ = (int) $request->query('category', 0);

        $offerorid = 0;
        if ($request->query('offerorid') !== null && $request->query('offerorid') !== '') {
            $offerorid = (int) $request->query('offerorid', 0);
        }

        $search = (string) ($request->query('search', '') ?? '');

        $self = Input::serverValue('PHP_SELF');
        $offerResult = $this->offerRepository->getLegacyList($categ, $offerorid, $search, $sortColumn, $direction, 0, 0);
        $count = (int) $offerResult['count'];

        [$pagerTop, $pagerBottom, , $offset, $perpage] = Pagination::pager(
            $perpage,
            $count,
            $self.'?'.'category='.((string) $request->query('category', '')).'&sort='.((string) $request->query('sort', '')).'&'
        );

        $offerResult = $this->offerRepository->getLegacyList($categ, $offerorid, $search, $sortColumn, $direction, (int) $offset, (int) $perpage);
        $offerRows = $offerResult['rows'];
        $num = $offerRows->count();

        // Rules section
        $rules = '';
        $rules .= '<p align="left"><b><font size="5">'.htmlspecialchars((string) ($lang['text_rules'] ?? '')).'</font></b></p>'."\n";
        $rules .= '<div align="left"><ul>';
        $rules .= '<li>'.htmlspecialchars((string) ($lang['text_rule_one_one'] ?? '')).
            UserClass::name((int) $globalData['uploadClass'], false, true, true).
            htmlspecialchars((string) ($lang['text_rule_one_two'] ?? '')).
            UserClass::name((int) $globalData['addofferClass'], false, true, true).
            htmlspecialchars((string) ($lang['text_rule_one_three'] ?? '')).'</li>'."\n";
        $offerSkipApprovedCount = SiteConfig::current()->main->offerSkipApprovedCount();
        if ($offerSkipApprovedCount > 0) {
            $rules .= '<li>'.sprintf((string) ($lang['text_rule_skip_offer'] ?? ''), $offerSkipApprovedCount).'</li>'."\n";
        }
        $rules .= '<li>'.htmlspecialchars((string) ($lang['text_rule_two_one'] ?? '')).'<b>'.(int) $globalData['minoffervotes'].'</b>'.htmlspecialchars((string) ($lang['text_rule_two_two'] ?? '')).'</li>'."\n";
        if ($globalData['offervotetimeoutMain'] > 0) {
            $rules .= '<li>'.htmlspecialchars((string) ($lang['text_rule_three_one'] ?? '')).'<b>'.((int) ($globalData['offervotetimeoutMain'] / 3600)).'</b>'.htmlspecialchars((string) ($lang['text_rule_three_two'] ?? '')).'</li>'."\n";
        }
        if ($globalData['offeruptimeoutMain'] > 0) {
            $rules .= '<li>'.htmlspecialchars((string) ($lang['text_rule_four_one'] ?? '')).'<b>'.((int) ($globalData['offeruptimeoutMain'] / 3600)).'</b>'.htmlspecialchars((string) ($lang['text_rule_four_two'] ?? '')).'</li>'."\n";
        }
        $rules .= '</ul></div>';

        $addOfferLink = '';
        if (Permission::can(PermissionEnum::ADD_OFFER)) {
            $addOfferLink = '<div align="center" style="margin-bottom: 8px;"><a href="?add_offer=1"><b>'.htmlspecialchars((string) ($lang['text_add_offer'] ?? '')).'</b></a></div>';
        }

        // Search box
        $catdropdown = '';
        foreach (Category::listByModeWithContext($globalData['browsecatmode']) as $cat) {
            $catArr = (array) $cat;
            $catdropdown .= '<option value="'.(int) $catArr['id'].'"';
            $catdropdown .= '>'.htmlspecialchars((string) $catArr['name'])."</option>\n";
        }
        $searchBox = '<div align="center"><form method="get" action="?">'.htmlspecialchars((string) ($lang['text_search_offers'] ?? '')).'&nbsp;&nbsp;<input type="text" id="specialboxg" name="search" />&nbsp;&nbsp;';
        $searchBox .= '<select name="category"><option value="0">'.htmlspecialchars((string) ($lang['select_show_all'] ?? '')).'</option>'.$catdropdown.'</select>&nbsp;&nbsp;<input type="submit" class="btn" value="'.htmlspecialchars((string) ($lang['submit_search'] ?? '')).'" /></form></div>';

        // Build the table rows
        $last_offer = strtotime((string) ($curUser['last_offer'] ?? 'now'));
        $tableHtml = '';
        $tooltipContainer = '';
        if (! $num) {
            $tableHtml = Frame::stdMessage((string) ($lang['text_nothing_found'] ?? ''), (string) ($lang['text_nothing_found'] ?? ''), false);
        } else {
            $catid = (string) $request->query('category', '');
            ob_start();
            echo '<table class="torrents" cellspacing="0" cellpadding="5" width="100%">';
            echo '<tr><td class="colhead" style="padding: 0px"><a href="?category='.htmlspecialchars($catid).'&amp;sort=cat&amp;type='.$catOrderType.'">'.htmlspecialchars((string) ($lang['col_type'] ?? '')).'</a></td>'.
                '<td class="colhead" width="100%"><a href="?category='.htmlspecialchars($catid).'&amp;sort=name&amp;type='.$nameOrderType.'">'.htmlspecialchars((string) ($lang['col_title'] ?? '')).'</a></td>'.
                '<td colspan="3" class="colhead"><a href="?category='.htmlspecialchars($catid).'&amp;sort=v_res&amp;type='.$vResOrderType.'">'.htmlspecialchars((string) ($lang['col_vote_results'] ?? '')).'</a></td>'.
                '<td class="colhead"><a href="?category='.htmlspecialchars($catid).'&amp;sort=comments&amp;type='.$commentsOrderType.'"><img class="comments" src="pic/trans.gif" alt="comments" title="'.htmlspecialchars((string) ($lang['title_comment'] ?? '')).'" />'.htmlspecialchars((string) ($lang['col_comment'] ?? '')).'</a></td>'.
                '<td class="colhead"><a href="?category='.htmlspecialchars($catid).'&amp;sort=added&amp;type='.$addedOrderType.'"><img class="time" src="pic/trans.gif" alt="time" title="'.htmlspecialchars((string) ($lang['title_time_added'] ?? '')).'" /></a></td>';
            if ($globalData['offervotetimeoutMain'] > 0 && $globalData['offeruptimeoutMain'] > 0) {
                echo '<td class="colhead">'.htmlspecialchars((string) ($lang['col_timeout'] ?? '')).'</td>';
            }
            echo '<td class="colhead">'.htmlspecialchars((string) ($lang['col_offered_by'] ?? '')).'</td>'.
                (Permission::can(PermissionEnum::OFFER_MANAGE) ? '<td class="colhead">'.htmlspecialchars((string) ($lang['col_act'] ?? '')).'</td>' : '')."</tr>\n";

            $i = 0;
            $lastcom_tooltip = [];
            foreach ($offerRows as $row) {
                $arr = (array) $row;
                $addedby = UserDisplay::username((int) ($arr['userid'] ?? 0));
                $comms = (int) ($arr['comments'] ?? 0);
                if ($comms === 0) {
                    $comment = '<a href="comment.php?action=add&amp;pid='.(int) $arr['id'].'&amp;type=offer" title="'.htmlspecialchars((string) ($lang['title_add_comments'] ?? '')).'">0</a>';
                } else {
                    $lastcom = $this->cache->get_value('offer_'.(int) $arr['id'].'_last_comment_content');
                    if (! $lastcom) {
                        $lastcom = $this->offerRepository->getLastComment((int) $arr['id']);
                        $this->cache->cache_value('offer_'.(int) $arr['id'].'_last_comment_content', $lastcom, 1855);
                    }
                    $lastcom = (array) $lastcom;
                    $timestamp = strtotime((string) ($lastcom['added'] ?? 'now'));
                    $hasnewcom = (($lastcom['user'] ?? 0) !== $userId && $timestamp >= $last_offer);
                    if (($curUser['showlastcom'] ?? true)) {
                        $title = '';
                        if (! empty($lastcom)) {
                            if (($curUser['timetype'] ?? 1) !== UserTimeType::TIMEALIVE->value) {
                                $lastcomtime = (string) ($lang['text_at_time'] ?? '').($lastcom['added'] ?? '');
                            } else {
                                $lastcomtime = (string) ($lang['text_blank'] ?? '').Time::format((string) ($lastcom['added'] ?? 'now'), true, false, true);
                            }
                            $counter = $i;
                            $lastcom_tooltip[$counter]['id'] = 'lastcom_'.$counter;
                            $lastcom_tooltip[$counter]['content'] = ($hasnewcom ? "<b>(<font class='new'>".htmlspecialchars((string) ($lang['text_new'] ?? '')).'</font>)</b> ' : '').htmlspecialchars((string) ($lang['text_last_commented_by'] ?? '')).UserDisplay::username((int) ($lastcom['user'] ?? 0)).$lastcomtime.'<br />'.Format::formatComment(mb_substr((string) ($lastcom['text'] ?? ''), 0, 100, 'UTF-8').(mb_strlen((string) ($lastcom['text'] ?? ''), 'UTF-8') > 100 ? ' ......' : ''), true, false, false, true, 600, false, false);
                            $onmouseover = "onmouseover=\"domTT_activate(this, event, 'content', document.getElementById('".$lastcom_tooltip[$counter]['id']."'), 'trail', false, 'delay', 500,'lifetime',3000,'fade','both','styleClass','niceTitle','fadeMax', 87,'maxWidth', 400);\"";
                        } else {
                            $onmouseover = '';
                        }
                    } else {
                        $title = ' title="'.($hasnewcom ? htmlspecialchars((string) ($lang['title_has_new_comment'] ?? '')) : htmlspecialchars((string) ($lang['title_no_new_comment'] ?? ''))).'"';
                        $onmouseover = '';
                    }
                    $comment = '<b><a'.$title.' href="?id='.(int) $arr['id'].'&amp;off_details=1#startcomments" '.$onmouseover.'>'.($hasnewcom ? "<font class='new'>" : '').$comms.($hasnewcom ? '</font>' : '').'</a></b>';
                }

                $allowed = match ((int) ($arr['allowed'] ?? 1)) {
                    OfferAllowed::ALLOWED->value => '&nbsp;<b>[<font color="green">'.htmlspecialchars((string) ($lang['text_allowed'] ?? '')).'</font>]</b>',
                    OfferAllowed::DENIED->value => '&nbsp;<b>[<font color="red">'.htmlspecialchars((string) ($lang['text_denied'] ?? '')).'</font>]</b>',
                    default => '&nbsp;<b>[<font color="orange">'.htmlspecialchars((string) ($lang['text_pending'] ?? '')).'</font>]</b>',
                };

                $zvote = ((int) ($arr['yeah'] ?? 0)) === 0 ? (string) ((int) ($arr['yeah'] ?? 0)) : '<b><a href="?id='.(int) $arr['id'].'&amp;offer_vote=1">'.(int) ($arr['yeah'] ?? 0).'</a></b>';
                $pvote = ((int) ($arr['against'] ?? 0)) === 0 ? (string) ((int) ($arr['against'] ?? 0)) : '<b><a href="?id='.(int) $arr['id'].'&amp;offer_vote=1">'.(int) ($arr['against'] ?? 0).'</a></b>';

                if ((int) ($arr['yeah'] ?? 0) === 0 && (int) ($arr['against'] ?? 0) === 0) {
                    $v_res = '0';
                } else {
                    $v_res = '<b><a href="?id='.(int) $arr['id'].'&amp;offer_vote=1" title="'.htmlspecialchars((string) ($lang['title_show_vote_details'] ?? '')).'"><font color="green">'.(int) ($arr['yeah'] ?? 0).'</font> - <font color="red">'.(int) ($arr['against'] ?? 0).'</font> = '.((int) ($arr['yeah'] ?? 0) - (int) ($arr['against'] ?? 0)).'</a></b>';
                }

                $addtime = Time::format((string) ($arr['added'] ?? 'now'), false, true);
                $dispname = (string) ($arr['name'] ?? '');
                $countDispname = mb_strlen($dispname, 'UTF-8');
                $maxLength = 70;
                if ($countDispname > $maxLength) {
                    $dispname = mb_substr($dispname, 0, $maxLength - 2, 'UTF-8').'..';
                }

                echo '<tr><td class="rowfollow" style="padding: 0px"><a href="?category='.(int) ($arr['cat_id'] ?? 0).'">'.Category::imageTagWithContext((int) ($arr['cat_id'] ?? 0), '')."</a></td><td style='text-align: left'><a href=\"?id=".(int) $arr['id'].'&amp;off_details=1" title="'.htmlspecialchars((string) ($arr['name'] ?? '')).'"><b>'.htmlspecialchars($dispname).'</b></a>'.(($curUser['appendnew'] ?? '') !== 'no' && strtotime((string) ($arr['added'] ?? 'now')) >= $last_offer ? "<b> (<font class='new'>".htmlspecialchars((string) ($lang['text_new'] ?? '')).'</font>)</b>' : '').$allowed."</td><td class=\"rowfollow nowrap\" style='padding: 5px' align=\"center\">".$v_res.'</td><td class="rowfollow nowrap" '.(! Permission::can(PermissionEnum::AGAINST_OFFER) ? ' colspan="2" ' : '')." style='padding: 5px'><a href=\"?id=".(int) $arr['id'].'&amp;vote=yeah" title="'.htmlspecialchars((string) ($lang['title_i_want_this'] ?? '')).'"><font color="green"><b>'.htmlspecialchars((string) ($lang['text_yep'] ?? '')).'</b></font></a></td>'.(UserDisplay::currentClass() >= $globalData['againstofferClass'] ? '<td class="rowfollow nowrap" align="center"><a href="?id='.(int) $arr['id'].'&amp;vote=against" title="'.htmlspecialchars((string) ($lang['title_do_not_want_it'] ?? '')).'"><font color="red"><b>'.htmlspecialchars((string) ($lang['text_nah'] ?? '')).'</b></font></a></td>' : '');

                echo '<td class="rowfollow">'.$comment.'</td><td class="rowfollow nowrap">'.$addtime.'</td>';
                if ($globalData['offervotetimeoutMain'] > 0 && $globalData['offeruptimeoutMain'] > 0) {
                    $timeout = '';
                    if ((int) ($arr['allowed'] ?? 1) === OfferAllowed::ALLOWED->value) {
                        $futuretime = strtotime((string) ($arr['allowedtime'] ?? 'now')) + $globalData['offeruptimeoutMain'];
                        $timeout = Time::format(date('Y-m-d H:i:s', $futuretime), false, true, true, false, true);
                    } elseif ((int) ($arr['allowed'] ?? 1) === OfferAllowed::PENDING->value) {
                        $futuretime = strtotime((string) ($arr['added'] ?? 'now')) + $globalData['offervotetimeoutMain'];
                        $timeout = Time::format(date('Y-m-d H:i:s', $futuretime), false, true, true, false, true);
                    }
                    if (! $timeout) {
                        $timeout = 'N/A';
                    }
                    echo '<td class="rowfollow nowrap">'.$timeout.'</td>';
                }
                echo '<td class="rowfollow">'.$addedby.'</td>'.(Permission::can(PermissionEnum::OFFER_MANAGE) ? '<td class="rowfollow"><a href="?id='.(int) $arr['id'].'&amp;del_offer=1"><img class="staff_delete" src="pic/trans.gif" alt="D" title="'.htmlspecialchars((string) ($lang['title_delete'] ?? '')).'" /></a><br /><a href="?id='.(int) $arr['id'].'&amp;edit_offer=1"><img class="staff_edit" src="pic/trans.gif" alt="E" title="'.htmlspecialchars((string) ($lang['title_edit'] ?? '')).'" /></a></td>' : '').'</tr>';
                $i++;
            }
            echo "</table>\n";
            echo $pagerBottom;
            if (($curUser['showlastcom'] ?? true)) {
                echo Html::tooltipContainer($lastcom_tooltip, 400);
            }
            $tableHtml = (string) ob_get_clean();
        }

        // Update last_offer timestamp
        if ($curUser) {
            $this->usercpRepository->updateLastOffer($userId);
        }

        return [
            'rules' => $rules,
            'addOfferLink' => $addOfferLink,
            'searchBox' => $searchBox,
            'hasRows' => $num > 0,
            'tableHtml' => $tableHtml,
            'pagerTop' => $pagerTop,
            'pagerBottom' => $pagerBottom,
            'count' => $count,
        ];
    }
}
