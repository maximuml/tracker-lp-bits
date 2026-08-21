<?php

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Message;
use App\Repositories\OfferRepository;
use App\Repositories\UsercpRepository;
use App\Support\Bonus;
use App\Support\Category;
use App\Support\Comment;
use App\Support\Config\SiteConfig;
use App\Support\Form;
use App\Support\Format;
use App\Support\Html;
use App\Support\Http;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Log;
use App\Support\Pagination;
use App\Support\SupportContext;
use App\Support\Time;
use App\Support\Url;
use App\Support\UserClass;
use App\Support\UserDisplay;
use App\Support\Validators;

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (! isset($CURUSER)) {
    $CURUSER = (array) (SupportContext::getUser() ?? []);
}
if (! isset($Cache)) {
    $Cache = SupportContext::getCache();
}
if (! isset($BASEURL)) {
    $BASEURL = SupportContext::getGlobal('BASEURL', '');
}
if (! isset($lang_offers)) {
    $lang_offers = (array) (SupportContext::getGlobal('lang_offers') ?? []);
}
$body = (string) ($body ?? '');
$__server_PHP_SELF = SupportContext::getServerValue('PHP_SELF');
if ($enableoffer == 'no') {
    LegacyResponse::permissionDenied();
}
if (! function_exists('offers_bark')) {
    function offers_bark($msg)
    {
        $lang_offers = (array) (SupportContext::getGlobal('lang_offers') ?? []);
        Html::stdMessage($lang_offers['std_error'], $msg);

    }
}

if (((SupportContext::getQuery('category') !== null)) && SupportContext::getQuery('category')) {
    $categ = ((SupportContext::getQuery('category') !== null)) ? (int) SupportContext::getQuery('category') : 0;
    if (! Validators::isId($categ)) {
        LegacyResponse::abort($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }
}

if (((SupportContext::getQuery('id') !== null)) && SupportContext::getQuery('id')) {
    $id = htmlspecialchars(intval(SupportContext::getQuery('id') ?? 0));
    if (preg_match('/^[0-9]+$/', ! $id)) {
        LegacyResponse::abort($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }
}

// ==== add offer
if (((SupportContext::getQuery('add_offer') !== null)) && SupportContext::getQuery('add_offer')) {
    Permission::assertCan(PermissionEnum::ADD_OFFER);
    $add_offer = intval(SupportContext::getQuery('add_offer') ?? 0);
    if ($add_offer != '1') {
        LegacyResponse::abort($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    echo '<p>'.$lang_offers['text_red_star_required'].'</p>';

    echo '<div align="center"><form id="compose" action="?new_offer=1" name="compose" method="post">'.
    '<table width=100% border=0 cellspacing=0 cellpadding=5><tr><td class=colhead align=center colspan=2>'.$lang_offers['text_offers_open_to_all']."</td></tr>\n";

    $s = "<select name=type>\n<option value=0>".$lang_offers['select_type_select']."</option>\n";
    $cats = Category::listByModeWithContext($browsecatmode);
    foreach ($cats as $row) {
        $s .= '<option value='.$row['id'].'>'.htmlspecialchars($row['name'])."</option>\n";
    }
    $s .= "</select>\n";
    echo '<tr><td class=rowhead align=right><b>'.$lang_offers['row_type']."<font color=red>*</font></b></td><td class=rowfollow align=left> $s</td></tr>".
    '<tr><td class=rowhead align=right><b>'.$lang_offers['row_title'].'<font color=red>*</font></b></td><td class=rowfollow align=left><input type=text name=name style="width: 99%;" />'.
    '</td></tr><tr><td class=rowhead align=right><b>'.$lang_offers['row_post_or_photo'].'</b></td><td class=rowfollow align=left>'.
    '<input type=text name=picture style="width: 99%;"><br />'.$lang_offers['text_link_to_picture'].'</td></tr>'.
    '<tr><td class=rowhead align=right valign=top><b>'.$lang_offers['row_description']."<b><font color=red>*</font></td><td class=rowfollow align=left>\n";
    echo Form::bbcodeEditor('compose', 'body', $body, false, 130, true);
    echo '</td></tr><tr><td class=toolbox align=center colspan=2><input id=qr type=submit class=btn value='.$lang_offers['submit_add_offer']." ></td></tr></table></form><br />\n";

    return;
}
// === end add offer

// === offer details
if (((SupportContext::getQuery('off_details') !== null)) && SupportContext::getQuery('off_details')) {

    $off_details = intval(SupportContext::getQuery('off_details') ?? 0);
    if ($off_details != '1') {
        LegacyResponse::abort($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $id = intval(SupportContext::getQuery('id') ?? 0);
    if (! $id) {
        LegacyResponse::abort($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $offerModel = OfferRepository::findOffer((int) $id);
    if (! $offerModel) {
        offers_bark($lang_offers['text_nothing_found']);
    }
    $num = $offerModel->toArray();

    $s = $num['name'];

    echo '<h1 align="center" id="top">'.htmlspecialchars($s).'</h1>';

    echo '<table width="97%" cellspacing="0" cellpadding="5">';
    $offertime = Time::format($num['added'], true, false);
    if ($CURUSER['timetype'] != 'timealive') {
        $offertime = $lang_offers['text_at'].$offertime;
    } else {
        $offertime = $lang_offers['text_blank'].$offertime;
    }
    Html::tr($lang_offers['row_info'], $lang_offers['text_offered_by'].UserDisplay::username($num['userid']).$offertime, 1);
    if ($num['allowed'] == 'pending') {
        $status = '<font color="red">'.$lang_offers['text_pending'].'</font>';
    } elseif ($num['allowed'] == 'allowed') {
        $status = '<font color="green">'.$lang_offers['text_allowed'].'</font>';
    } else {
        $status = '<font color="red">'.$lang_offers['text_denied'].'</font>';
    }
    Html::tr($lang_offers['row_status'], $status, 1);
    // === if you want to have a pending thing for uploaders use this next bit
    if (Permission::can(PermissionEnum::OFFER_MANAGE) && $num['allowed'] == 'pending') {
        Html::tr($lang_offers['row_allow'], '<table><tr><td class="embedded"><form method="post" action="?allow_offer=1"><input type="hidden" value="'.$id.'" name="offerid" />'.
        '<input class="btn" type="submit" value="'.$lang_offers['submit_allow'].'" />&nbsp;&nbsp;</form></td><td class="embedded"><form method="post" action="?id='.$id.'&amp;finish_offer=1">'.
        '<input type="hidden" value="'.$id.'" name="finish" /><input class="btn" type="submit" value="'.$lang_offers['submit_let_votes_decide'].'" /></form></td></tr></table>', 1);
    }

    $voteCounts = OfferRepository::getVoteCounts((int) $id);
    $za = $voteCounts['yeah'];
    $protiv = $voteCounts['against'];
    // === in the following section, there is a line to report comment... either remove the link or change it to work with your report script :)

    // if pending
    if ($num['allowed'] == 'pending') {
        Html::tr($lang_offers['row_vote'], '<b>'.
        '<a href="?id='.$id.'&amp;vote=yeah"><font color="green">'.$lang_offers['text_for'].'</font></a></b>'.(Permission::can(PermissionEnum::AGAINST_OFFER) ? ' - <b><a href="?id='.$id.'&amp;vote=against">'.
        '<font color="red">'.$lang_offers['text_against'].'</font></a></b>' : ''), 1);
        Html::tr($lang_offers['row_vote_results'], '<b>'.$lang_offers['text_for'].":</b> $za  <b>".$lang_offers['text_against']."</b> $protiv &nbsp; &nbsp; <a href=\"?id=".$id.'&amp;offer_vote=1"><i>'.$lang_offers['text_see_vote_detail'].'</i></a>', 1);
    }
    // ===upload torrent message
    if ($num['allowed'] == 'allowed' && $CURUSER['id'] != $num['userid']) {
        Html::tr($lang_offers['row_offer_allowed'], $lang_offers['text_voter_receives_pm_note'], 1);
    }
    if ($num['allowed'] == 'allowed' && $CURUSER['id'] == $num['userid']) {
        Html::tr($lang_offers['row_offer_allowed'], $lang_offers['text_urge_upload_offer_note'], 1);
    }
    if ($CURUSER['id'] == $num['userid'] || Permission::can(PermissionEnum::OFFER_MANAGE)) {
        $edit = '<a href="?id='.$id.'&amp;edit_offer=1"><img class="dt_edit" src="pic/trans.gif" alt="edit" />&nbsp;<b><font class="small">'.$lang_offers['text_edit_offer'].'</font></b></a>&nbsp;|&nbsp;';
        $delete = '<a href="?id='.$id.'&amp;del_offer=1&amp;sure=0"><img class="dt_delete" src="pic/trans.gif" alt="delete" />&nbsp;<b><font class="small">'.$lang_offers['text_delete_offer'].'</font></b></a>&nbsp;|&nbsp;';
    }
    $report = '<a href="report.php?reportofferid='.$id.'"><img class="dt_report" src="pic/trans.gif" alt="report" />&nbsp;<b><font class="small">'.$lang_offers['report_offer'].'</font></b></a>';
    Html::tr($lang_offers['row_action'], $edit.$delete.$report, 1);
    if ($num['descr']) {
        $off_bb = Format::formatComment($num['descr']);
        Html::tr($lang_offers['row_description'], $off_bb, 1);
    }
    echo '</table>';
    // -----------------COMMENT SECTION ---------------------//
    $commentbar = '<p align="center"><a class="index" href="comment.php?action=add&amp;pid='.$id.'&amp;type=offer">'.$lang_offers['text_add_comment']."</a></p>\n";
    $count = OfferRepository::countComments((int) $id);
    if (! $count) {
        echo '<h1 id="startcomments" align="center">'.$lang_offers['text_no_comments']."</h1>\n";
    } else {
        [$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager(10, $count, "offers.php?id=$id&off_details=1&", ['lastpagedefault' => 1]);

        $commentRows = OfferRepository::getComments((int) $id, (int) $offset, (int) $perpage);
        $allrows = [];
        foreach ($commentRows as $commentObj) {
            $allrows[] = $commentObj->toArray();
        }

        // end_frame();
        // print($commentbar);
        echo $pagertop;

        Comment::tableVoid($allrows, 'offer', $id);
        echo $pagerbottom;
    }
    echo "<table style='border:1px solid #000000;'><tr>".
'<td class="text" align="center"><b>'.$lang_offers['text_quick_comment'].'</b><br /><br />'.
'<form id="compose" name="comment" method="post" action="comment.php?action=add&amp;type=offer" onsubmit="return postvalid(this);">'.
'<input type="hidden" name="pid" value="'.$id.'" /><br />';
    Html::quickReplyVoid('comment', 'body', $lang_offers['submit_add_comment']);
    echo '</form></td></tr></table>';
    echo $commentbar;

    return;
}
// === end offer details

// === edit offer

if (((SupportContext::getQuery('edit_offer') !== null)) && SupportContext::getQuery('edit_offer')) {

    $edit_offer = intval(SupportContext::getQuery('edit_offer') ?? 0);
    if ($edit_offer != '1') {
        LegacyResponse::abort($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $id = intval(SupportContext::getQuery('id') ?? 0);

    $offerRow = OfferRepository::findOffer((int) $id);
    if (! $offerRow) {
        offers_bark($lang_offers['text_nothing_found']);
    }
    $num = $offerRow->toArray();

    $timezone = $num['added'];

    $s = $num['name'];
    $id2 = $num['category'];

    if ($CURUSER['id'] != $num['userid'] && ! Permission::can(PermissionEnum::OFFER_MANAGE)) {
        LegacyResponse::abort($lang_offers['std_error'], $lang_offers['std_cannot_edit_others_offer']);
    }

    $body = htmlspecialchars(Input::unescape($num['descr']));
    $s2 = "<select name=\"category\">\n";

    $cats = Category::listByModeWithContext($browsecatmode);

    foreach ($cats as $row) {
        $s2 .= '<option value="'.$row['id'].'" '.($row['id'] == $id2 ? ' selected="selected"' : '').'>'.htmlspecialchars($row['name'])."</option>\n";
    }
    $s2 .= "</select>\n";

    $title = htmlspecialchars(trim($s));

    echo '<form id="compose" method="post" name="compose" action="?id='.$id.'&amp;take_off_edit=1">'.
    '<table width="97%" cellspacing="0" cellpadding="3"><tr><td class="colhead" align="center" colspan="2">'.$lang_offers['text_edit_offer'].'</td></tr>';
    Html::tr($lang_offers['row_type'].'<font color="red">*</font>', $s2, 1);
    Html::tr($lang_offers['row_title'].'<font color="red">*</font>', '<input type="text" style="width: 99%" name="name" value="'.$title.'" />', 1);
    Html::tr($lang_offers['row_post_or_photo'], "<input type=\"text\" name=\"picture\" style=\"width: 99%\" value='' /><br />".$lang_offers['text_link_to_picture'], 1);
    echo '<tr><td class="rowhead" align="right" valign="top"><b>'.$lang_offers['row_description'].'<font color="red">*</font></b></td><td class="rowfollow" align="left">';
    echo Form::bbcodeEditor('compose', 'body', $body, false, 130, true);
    echo '</td></tr>';
    echo '<tr><td class="toolbox" style="vertical-align: middle; padding-top: 10px; padding-bottom: 10px;" align="center" colspan="2"><input id="qr" type="submit" value="'.$lang_offers['submit_edit_offer']."\" class=\"btn\" /></td></tr></table></form><br />\n";

    return;
}
// === end edit offer

// === offer votes list
if (((SupportContext::getQuery('offer_vote') !== null)) && SupportContext::getQuery('offer_vote')) {

    $offer_vote = intval(SupportContext::getQuery('offer_vote') ?? 0);
    if ($offer_vote != '1') {
        LegacyResponse::abort($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $offerid = htmlspecialchars(intval(SupportContext::getQuery('id') ?? 0));

    $count = OfferRepository::getVoteCount((int) $offerid);

    $offername = OfferRepository::getOfferName((int) $offerid);

    echo '<h1 align=center>'.$lang_offers['text_vote_results_for']." <a  href=offers.php?id=$offerid&off_details=1><b>".htmlspecialchars($offername).'</b></a></h1>';

    $perpage = 25;
    [$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager($perpage, $count, $__server_PHP_SELF.'?id='.$offerid.'&offer_vote=1&');
    $voteRows = OfferRepository::getVoteRows((int) $offerid, (int) $offset, (int) $perpage);

    if ($voteRows->isEmpty()) {
        echo '<p align=center><b>'.$lang_offers['std_no_votes_yet']."</b></p>\n";
    } else {
        echo $pagertop;
        echo '<table border=1 cellspacing=0 cellpadding=5><tr><td class=colhead>'.$lang_offers['col_user'].'</td><td class=colhead align=left>'.$lang_offers['col_vote']."</td>\n";

        foreach ($voteRows as $arr) {
            $arr = (array) $arr;
            if ($arr['vote'] == 'yeah') {
                $vote = '<b><font color=green>'.$lang_offers['text_for'].'</font></b>';
            } elseif ($arr['vote'] == 'against') {
                $vote = '<b><font color=red>'.$lang_offers['text_against'].'</font></b>';
            } else {
                $vote = 'unknown';
            }

            echo '<tr><td class=rowfollow>'.UserDisplay::username($arr['userid']).'</td><td class=rowfollow align=left >'.$vote."</td></tr>\n";
        }
        echo "</table>\n";
        echo $pagerbottom;
    }

    return;
}
// === end offer votes list

// === offer votes
if (((SupportContext::getQuery('vote') !== null)) && SupportContext::getQuery('vote')) {
    $offerid = htmlspecialchars(intval(SupportContext::getQuery('id') ?? 0));
    $vote = htmlspecialchars(SupportContext::getQuery('vote'));
    if ($vote == 'against' && ! Permission::can(PermissionEnum::AGAINST_OFFER)) {
        LegacyResponse::abort($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }
    if ($vote == 'yeah' || $vote == 'against') {
        $userid = intval($CURUSER['id'] ?? 0);
        $voted = OfferRepository::userVoted((int) $offerid, (int) $userid);
        $offerOwner = OfferRepository::getOfferOwner((int) $offerid);
        if ($offerOwner == $CURUSER['id']) {
            LegacyResponse::abort($lang_offers['std_error'], $lang_offers['std_cannot_vote_youself']);
        } elseif ($voted) {
            LegacyResponse::abort($lang_offers['std_already_voted'], $lang_offers['std_already_voted_note']."<a  href=offers.php?id=$offerid&off_details=1>".$lang_offers['std_back_to_offer_detail'], false);
        } else {
            $offer = OfferRepository::findOfferWithUser((int) $offerid);
            if (! $offer) {
                offers_bark($lang_offers['text_nothing_found']);
            }
            $voteColumn = $vote == 'yeah' ? 'yeah' : 'against';
            OfferRepository::incrementVote((int) $offerid, (string) $voteColumn);
            $locale = Locale::userLocale($offer->userid);

            $offer = OfferRepository::findOfferWithVotes((int) $offerid);
            $yeah = $offer->yeah;
            $against = $offer->against;
            $finishtime = date('Y-m-d H:i:s');
            // allowed and send offer voted on message
            if (($yeah - $against) >= $minoffervotes && $offer->allowed != 'allowed') {
                if ($offeruptimeout_main) {
                    $timeouthour = floor($offeruptimeout_main / 3600);
                    $timeoutnote = Locale::trans('offer.msg_you_must_upload_in', [], $locale).$timeouthour.Locale::trans('offer.msg_hours_otherwise', [], $locale);
                } else {
                    $timeoutnote = '';
                }
                OfferRepository::allowOffer((int) $offerid, (string) $finishtime);
                $msg = Locale::trans('offer.msg_offer_voted_on', [], $locale).'[b][url='.Http::protocolPrefix(Url::isSecure()).$BASEURL."/offers.php?id=$offerid&off_details=1]".$offer->name.'[/url][/b].'.Locale::trans('offer.msg_find_offer_option', [], $locale).$timeoutnote;
                $subject = Locale::trans('offer.msg_your_offer_allowed', [], $locale);

                Message::add([
                    'sender' => 0,
                    'receiver' => $offer->userid,
                    'msg' => $msg,
                    'subject' => $subject,
                    'added' => now(),
                ]);

                Log::writeWithContext("System allowed offer {$offer->name}", 'normal');
            }
            // denied and send offer voted off message
            if (($against - $yeah) >= $minoffervotes && $offer->allowed != 'denied') {
                OfferRepository::denyOffer((int) $offerid);
                $msg = Locale::trans('offer.msg_offer_voted_off', [], $locale).'[b][url='.Http::protocolPrefix(Url::isSecure()).$BASEURL."/offers.php?id=$offerid&off_details=1]".$offer->name.'[/url][/b].'.Locale::trans('offer.msg_offer_deleted', [], $locale);
                $subject = Locale::trans('offer.msg_offer_deleted', [], $locale);

                Message::add([
                    'sender' => 0,
                    'receiver' => $offer->userid,
                    'msg' => $msg,
                    'subject' => $subject,
                    'added' => now(),
                ]);

                Log::writeWithContext("System denied offer {$offer->name}", 'normal');
            }

            OfferRepository::recordVote((int) $offerid, (int) $userid, (string) $vote);
            Bonus::updatePoints((string) '+', (float) $offervote_bonus, $CURUSER['id']);
            echo '<h1 align=center>'.$lang_offers['std_vote_accepted'].'</h1>';
            echo $lang_offers['std_vote_accepted_note']."<a  href=offers.php?id=$offerid&off_details=1>".$lang_offers['std_back_to_offer_detail'];

            return;
        }
    } else {
        LegacyResponse::abort($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }
}
// === end offer votes

// === prolly not needed, but what the hell... basically stopping the page getting screwed up
$sort = '';
if (((SupportContext::getQuery('sort') !== null)) && SupportContext::getQuery('sort')) {
    $sort = SupportContext::getQuery('sort');
    if ($sort == 'cat' || $sort == 'name' || $sort == 'added' || $sort == 'comments' || $sort == 'yeah' || $sort == 'against' || $sort == 'v_res') {
        $sort = SupportContext::getQuery('sort');
    } else {
        LegacyResponse::abort($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }
}
// === end of prolly not needed, but what the hell :P

$categ = intval(SupportContext::getQuery('category') ?? 0);
$offerorid = 0;
if (((SupportContext::getQuery('offerorid') !== null)) && SupportContext::getQuery('offerorid')) {
    $offerorid = htmlspecialchars(intval(SupportContext::getQuery('offerorid') ?? 0));
    if (preg_match('/^[0-9]+$/', ! $offerorid)) {
        LegacyResponse::abort($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }
}

$search = (SupportContext::getQuery('search') ?? '');

$cat_order_type = 'desc';
$name_order_type = 'desc';
$added_order_type = 'desc';
$comments_order_type = 'desc';
$v_res_order_type = 'desc';

/*
if ($cat_order_type == "") { $sort = " ORDER BY added " . $added_order_type; $cat_order_type = "asc"; } // for torrent name
if ($name_order_type == "") { $sort = " ORDER BY added " . $added_order_type; $name_order_type = "desc"; }
if ($added_order_type == "") { $sort = " ORDER BY added " . $added_order_type; $added_order_type = "desc"; }
if ($comments_order_type == "") { $sort = " ORDER BY added " . $added_order_type; $comments_order_type = "desc"; }
if ($v_res_order_type == "") { $sort = " ORDER BY added " . $added_order_type; $v_res_order_type = "desc"; }
*/

if ($sort == 'cat') {
    if (SupportContext::getQuery('type') == 'desc') {
        $cat_order_type = 'asc';
    }
    $sort = ' ORDER BY category '.$cat_order_type;
} elseif ($sort == 'name') {
    if (SupportContext::getQuery('type') == 'desc') {
        $name_order_type = 'asc';
    }
    $sort = ' ORDER BY name '.$name_order_type;
} elseif ($sort == 'added') {
    if (SupportContext::getQuery('type') == 'desc') {
        $added_order_type = 'asc';
    }
    $sort = ' ORDER BY added '.$added_order_type;
} elseif ($sort == 'comments') {
    if (SupportContext::getQuery('type') == 'desc') {
        $comments_order_type = 'asc';
    }
    $sort = ' ORDER BY comments '.$comments_order_type;
} elseif ($sort == 'v_res') {
    if (SupportContext::getQuery('type') == 'desc') {
        $v_res_order_type = 'asc';
    }
    $sort = ' ORDER BY (yeah - against) '.$v_res_order_type;
}

$sortColumn = $sort;
$direction = strtolower((string) (SupportContext::getQuery('type') ?? '')) === 'asc' ? 'asc' : 'desc';
$perpage = 25;

$offerResult = OfferRepository::getLegacyList((int) $categ, (int) $offerorid, (string) $search, (string) $sortColumn, (string) $direction, 0, 0);
$count = $offerResult['count'];

[$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager($perpage, $count, $__server_PHP_SELF.'?'.'category='.(SupportContext::getQuery('category') ?? '').'&sort='.(SupportContext::getQuery('sort') ?? '').'&');

$offerResult = OfferRepository::getLegacyList((int) $categ, (int) $offerorid, (string) $search, (string) $sortColumn, (string) $direction, (int) $offset, (int) $perpage);
$offerRows = $offerResult['rows'];
$num = $offerRows->count();

Html::beginFrame($lang_offers['text_offers_section'], true, 10, '100%', 'center');

echo '<p align="left"><b><font size="5">'.$lang_offers['text_rules']."</font></b></p>\n";
echo '<div align="left"><ul>';
echo '<li>'.$lang_offers['text_rule_one_one'].UserClass::name($upload_class, false, true, true).$lang_offers['text_rule_one_two'].UserClass::name($addoffer_class, false, true, true).$lang_offers['text_rule_one_three']."</li>\n";
$offerSkipApprovedCount = SiteConfig::current()->main->offerSkipApprovedCount();
if (is_numeric($offerSkipApprovedCount) && $offerSkipApprovedCount > 0) {
    echo '<li>'.sprintf($lang_offers['text_rule_skip_offer'], $offerSkipApprovedCount)."</li>\n";
}
echo '<li>'.$lang_offers['text_rule_two_one'].'<b>'.$minoffervotes.'</b>'.$lang_offers['text_rule_two_two']."</li>\n";
if ($offervotetimeout_main) {
    echo '<li>'.$lang_offers['text_rule_three_one'].'<b>'.($offervotetimeout_main / 3600).'</b>'.$lang_offers['text_rule_three_two']."</li>\n";
}
if ($offeruptimeout_main) {
    echo '<li>'.$lang_offers['text_rule_four_one'].'<b>'.($offeruptimeout_main / 3600).'</b>'.$lang_offers['text_rule_four_two']."</li>\n";
}
echo '</ul></div>';
if (Permission::can(PermissionEnum::ADD_OFFER)) {
    echo '<div align="center" style="margin-bottom: 8px;"><a href="?add_offer=1">'.
    '<b>'.$lang_offers['text_add_offer'].'</b></a></div>';
}
echo '<div align="center"><form method="get" action="?">'.$lang_offers['text_search_offers'].'&nbsp;&nbsp;<input type="text" id="specialboxg" name="search" />&nbsp;&nbsp;';
$cats = Category::listByModeWithContext($browsecatmode);
$catdropdown = '';
foreach ($cats as $cat) {
    $catdropdown .= '<option value="'.$cat['id'].'"';
    $catdropdown .= '>'.htmlspecialchars($cat['name'])."</option>\n";
}
echo '<select name="category"><option value="0">'.$lang_offers['select_show_all'].'</option>'.$catdropdown.'</select>&nbsp;&nbsp;<input type="submit" class="btn" value="'.$lang_offers['submit_search'].'" /></form></div>';
Html::endFrame();
echo '<br /><br />';

$last_offer = strtotime($CURUSER['last_offer']);
if (! $num) {
    Html::stdMessage($lang_offers['text_nothing_found'], $lang_offers['text_nothing_found']);
} else {
    $catid = SupportContext::getQuery('category');
    echo '<table class="torrents" cellspacing="0" cellpadding="5" width="100%">';
    echo '<tr><td class="colhead" style="padding: 0px"><a href="?category='.$catid.'&amp;sort=cat&amp;type='.$cat_order_type.'">'.$lang_offers['col_type'].'</a></td>'.
'<td class="colhead" width="100%"><a href="?category='.$catid.'&amp;sort=name&amp;type='.$name_order_type.'">'.$lang_offers['col_title'].'</a></td>'.
'<td colspan="3" class="colhead"><a href="?category='.$catid.'&amp;sort=v_res&amp;type='.$v_res_order_type.'">'.$lang_offers['col_vote_results'].'</a></td>'.
'<td class="colhead"><a href="?category='.$catid.'&amp;sort=comments&amp;type='.$comments_order_type.'"><img class="comments" src="pic/trans.gif" alt="comments" title="'.$lang_offers['title_comment'].'" />'.$lang_offers['col_comment'].'</a></td>'.
'<td class="colhead"><a href="?category='.$catid.'&amp;sort=added&amp;type='.$added_order_type.'"><img class="time" src="pic/trans.gif" alt="time" title="'.$lang_offers['title_time_added'].'" /></a></td>';
    if ($offervotetimeout_main > 0 && $offeruptimeout_main > 0) {
        echo '<td class="colhead">'.$lang_offers['col_timeout'].'</td>';
    }
    echo '<td class="colhead">'.$lang_offers['col_offered_by'].'</td>'.
    (Permission::can(PermissionEnum::OFFER_MANAGE) ? '<td class="colhead">'.$lang_offers['col_act'].'</td>' : '')."</tr>\n";
    $i = 0;
    foreach ($offerRows as $row) {
        $arr = (array) $row;

        $addedby = UserDisplay::username($arr['userid']);
        $comms = $arr['comments'];
        if ($comms == 0) {
            $comment = '<a href="comment.php?action=add&amp;pid='.$arr['id'].'&amp;type=offer" title="'.$lang_offers['title_add_comments'].'">0</a>';
        } else {
            if (! $lastcom = $Cache->get_value('offer_'.$arr['id'].'_last_comment_content')) {
                $lastcom = OfferRepository::getLastComment((int) $arr['id']);
                $Cache->cache_value('offer_'.$arr['id'].'_last_comment_content', $lastcom, 1855);
            }
            $timestamp = strtotime($lastcom['added']);
            $hasnewcom = ($lastcom['user'] != $CURUSER['id'] && $timestamp >= $last_offer);
            if ($CURUSER['showlastcom'] != 'no') {
                if ($lastcom) {
                    $title = '';
                    if ($CURUSER['timetype'] != 'timealive') {
                        $lastcomtime = $lang_offers['text_at_time'].$lastcom['added'];
                    } else {
                        $lastcomtime = $lang_offers['text_blank'].Time::format($lastcom['added'], true, false, true);
                    }
                    $counter = $i;
                    $lastcom_tooltip[$counter]['id'] = 'lastcom_'.$counter;
                    $lastcom_tooltip[$counter]['content'] = ($hasnewcom ? "<b>(<font class='new'>".$lang_offers['text_new'].'</font>)</b> ' : '').$lang_offers['text_last_commented_by'].UserDisplay::username($lastcom['user']).$lastcomtime.'<br />'.Format::formatComment(mb_substr($lastcom['text'], 0, 100, 'UTF-8').(mb_strlen($lastcom['text'], 'UTF-8') > 100 ? ' ......' : ''), true, false, false, true, 600, false, false);
                    $onmouseover = "onmouseover=\"domTT_activate(this, event, 'content', document.getElementById('".$lastcom_tooltip[$counter]['id']."'), 'trail', false, 'delay', 500,'lifetime',3000,'fade','both','styleClass','niceTitle','fadeMax', 87,'maxWidth', 400);\"";
                }
            } else {
                $title = ' title="'.($hasnewcom ? $lang_offers['title_has_new_comment'] : $lang_offers['title_no_new_comment']).'"';
                $onmouseover = '';
            }
            $comment = '<b><a'.$title.' href="?id='.$arr['id'].'&amp;off_details=1#startcomments" '.$onmouseover.'>'.($hasnewcom ? "<font class='new'>" : '').$comms.($hasnewcom ? '</font>' : '').'</a></b>';
        }

        // ==== if you want allow deny for offers use this next bit
        if ($arr['allowed'] == 'allowed') {
            $allowed = '&nbsp;<b>[<font color="green">'.$lang_offers['text_allowed'].'</font>]</b>';
        } elseif ($arr['allowed'] == 'denied') {
            $allowed = '&nbsp;<b>[<font color="red">'.$lang_offers['text_denied'].'</font>]</b>';
        } else {
            $allowed = '&nbsp;<b>[<font color="orange">'.$lang_offers['text_pending'].'</font>]</b>';
        }
        // ===end

        if ($arr['yeah'] == 0) {
            $zvote = $arr['yeah'];
        } else {
            $zvote = '<b><a href="?id='.$arr['id'].'&amp;offer_vote=1">'.$arr['yeah'].'</a></b>';
        }
        if ($arr['against'] == 0) {
            $pvote = $arr['against'];
        } else {
            $pvote = '<b><a href="?id='.$arr['id'].'&amp;offer_vote=1">'.$arr['against'].'</a></b>';
        }

        if ($arr['yeah'] == 0 && $arr['against'] == 0) {
            $v_res = '0';
        } else {

            $v_res = '<b><a href="?id='.$arr['id'].'&amp;offer_vote=1" title="'.$lang_offers['title_show_vote_details'].'"><font color="green">'.$arr['yeah'].'</font> - <font color="red">'.$arr['against'].'</font> = '.($arr['yeah'] - $arr['against']).'</a></b>';
        }
        $addtime = Time::format($arr['added'], false, true);
        $dispname = $arr['name'];
        $count_dispname = mb_strlen($arr['name'], 'UTF-8');
        $max_length_of_offer_name = 70;
        if ($count_dispname > $max_length_of_offer_name) {
            $dispname = mb_substr($dispname, 0, $max_length_of_offer_name - 2, 'UTF-8').'..';
        }
        echo '<tr><td class="rowfollow" style="padding: 0px"><a href="?category='.$arr['cat_id'].'">'.Category::imageTagWithContext($arr['cat_id'], '')."</a></td><td style='text-align: left'><a href=\"?id=".$arr['id'].'&amp;off_details=1" title="'.htmlspecialchars($arr['name']).'"><b>'.htmlspecialchars($dispname).'</b></a>'.($CURUSER['appendnew'] != 'no' && strtotime($arr['added']) >= $last_offer ? "<b> (<font class='new'>".$lang_offers['text_new'].'</font>)</b>' : '').$allowed."</td><td class=\"rowfollow nowrap\" style='padding: 5px' align=\"center\">".$v_res.'</td><td class="rowfollow nowrap" '.(! Permission::can(PermissionEnum::AGAINST_OFFER) ? ' colspan="2" ' : '')." style='padding: 5px'><a href=\"?id=".$arr['id'].'&amp;vote=yeah" title="'.$lang_offers['title_i_want_this'].'"><font color="green"><b>'.$lang_offers['text_yep'].'</b></font></a></td>'.(UserDisplay::currentClass() >= $againstoffer_class ? '<td class="rowfollow nowrap" align="center"><a href="?id='.$arr['id'].'&amp;vote=against" title="'.$lang_offers['title_do_not_want_it'].'"><font color="red"><b>'.$lang_offers['text_nah'].'</b></font></a></td>' : '');

        echo '<td class="rowfollow">'.$comment.'</td><td class="rowfollow nowrap">'.$addtime.'</td>';
        if ($offervotetimeout_main > 0 && $offeruptimeout_main > 0) {
            if ($arr['allowed'] == 'allowed') {
                $futuretime = strtotime($arr['allowedtime']) + $offeruptimeout_main;
                $timeout = Time::format(date('Y-m-d H:i:s', $futuretime), false, true, true, false, true);
            } elseif ($arr['allowed'] == 'pending') {
                $futuretime = strtotime($arr['added']) + $offervotetimeout_main;
                $timeout = Time::format(date('Y-m-d H:i:s', $futuretime), false, true, true, false, true);
            }
            if (! $timeout) {
                $timeout = 'N/A';
            }
            echo '<td class="rowfollow nowrap">'.$timeout.'</td>';
        }
        echo '<td class="rowfollow">'.$addedby.'</td>'.(Permission::can(PermissionEnum::OFFER_MANAGE) ? '<td class="rowfollow"><a href="?id='.$arr['id'].'&amp;del_offer=1"><img class="staff_delete" src="pic/trans.gif" alt="D" title="'.$lang_offers['title_delete'].'" /></a><br /><a href="?id='.$arr['id'].'&amp;edit_offer=1"><img class="staff_edit" src="pic/trans.gif" alt="E" title="'.$lang_offers['title_edit'].'" /></a></td>' : '').'</tr>';
        $i++;
    }
    echo "</table>\n";
    echo $pagerbottom;
    if (! (isset($CURUSER)) || $CURUSER['showlastcom'] == 'yes') {
        echo Html::tooltipContainer($lastcom_tooltip, 400);
    }
}
if ($CURUSER) {
    UsercpRepository::updateLastOffer((int) $CURUSER['id']);
}
