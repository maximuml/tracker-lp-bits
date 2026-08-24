<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Repositories\OfferRepository;
use App\Repositories\UsercpRepository;
use App\Support\Category;
use App\Support\Comment;
use App\Support\Config\SiteConfig;
use App\Support\Form;
use App\Support\Format;
use App\Support\Frame;
use App\Support\Html;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\Pagination;
use App\Support\SupportContext;
use App\Support\Time;
use App\Support\UserClass;
use App\Support\UserDisplay;
use Illuminate\Http\Request;

/**
 * Prepares section data for the offers page, replacing the legacy
 * offers_content.php partial with typed Blade-rendered sections.
 *
 * Sections:
 *  - list:      paginated offer list with rules + search box
 *  - add_offer: new offer form
 *  - off_details: single offer view with vote/comment controls
 *  - edit_offer: edit offer form
 *  - offer_vote: vote results list
 */
final class OfferPageService
{
    /**
     * Build the data for the requested action.
     *
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $curUser = (array) (SupportContext::getUser() ?? []);
        $lang = (array) (SupportContext::getGlobal('lang_offers') ?? []);
        $userId = (int) ($curUser['id'] ?? 0);

        $action = $this->resolveAction($request);

        $data = [
            'lang' => $lang,
            'curUser' => $curUser,
            'userId' => $userId,
            'action' => $action,
            'baseUrl' => (string) SupportContext::getGlobal('BASEURL', ''),
            'contentWidth' => (string) SupportContext::getGlobal('CONTENT_WIDTH', '737'),
            'browsecatmode' => SupportContext::getGlobal('browsecatmode', 1),
            'enableoffer' => (string) SupportContext::getGlobal('enableoffer', 'yes'),
            'minoffervotes' => (int) SupportContext::getGlobal('minoffervotes', 0),
            'offervotetimeoutMain' => (int) SupportContext::getGlobal('offervotetimeout_main', 0),
            'offeruptimeoutMain' => (int) SupportContext::getGlobal('offeruptimeout_main', 0),
            'offervoteBonus' => (float) SupportContext::getGlobal('offervote_bonus', 0),
            'uploadClass' => (int) SupportContext::getGlobal('upload_class', 0),
            'addofferClass' => (int) SupportContext::getGlobal('addoffer_class', 0),
            'againstofferClass' => (int) SupportContext::getGlobal('againstoffer_class', 0),
        ];

        if ($data['enableoffer'] === 'no') {
            LegacyResponse::permissionDenied();
        }

        switch ($action) {
            case 'add_offer':
                Permission::assertCan(PermissionEnum::ADD_OFFER);
                $data['add_offer'] = $this->buildAddOffer($lang, $data['browsecatmode']);
                break;
            case 'off_details':
                $data['off_details'] = $this->buildOfferDetails($lang, $curUser, $userId, $request);
                break;
            case 'edit_offer':
                $data['edit_offer'] = $this->buildEditOffer($lang, $curUser, $userId, $request, $data['browsecatmode']);
                break;
            case 'offer_vote':
                $data['offer_vote'] = $this->buildOfferVoteList($lang, $request);
                break;
            default:
                $data['list'] = $this->buildList($lang, $curUser, $userId, $request, $data);
                $data['action'] = 'list';
                break;
        }

        return $data;
    }

    private function resolveAction(Request $request): string
    {
        foreach (['add_offer', 'off_details', 'edit_offer', 'offer_vote'] as $key) {
            $value = $request->query($key);
            if ($value !== null && $value !== '' && $value !== '0') {
                return $key;
            }
        }

        return 'list';
    }

    /**
     * Build the "add offer" form section.
     *
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    private function buildAddOffer(array $lang, mixed $browsecatmode): array
    {
        $typeOptions = '<select name=type>'."\n".'<option value=0>'.(string) ($lang['select_type_select'] ?? '')."</option>\n";
        foreach (Category::listByModeWithContext($browsecatmode) as $row) {
            $rowArr = (array) $row;
            $typeOptions .= '<option value='.(int) $rowArr['id'].'>'.htmlspecialchars((string) $rowArr['name'])."</option>\n";
        }
        $typeOptions .= "</select>\n";

        return [
            'typeOptions' => $typeOptions,
            'bbcodeEditor' => Form::bbcodeEditor('compose', 'body', '', false, 130, true),
        ];
    }

    /**
     * Build the offer details section.
     *
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildOfferDetails(array $lang, array $curUser, int $userId, Request $request): array
    {
        $id = (int) $request->query('id', 0);
        if (! $id) {
            LegacyResponse::abort((string) ($lang['std_error'] ?? ''), (string) ($lang['std_smell_rat'] ?? ''));
        }

        $offer = OfferRepository::findOffer($id);
        if (! $offer) {
            Html::stdMessage((string) ($lang['std_error'] ?? ''), (string) ($lang['text_nothing_found'] ?? ''));

            return [];
        }
        $num = $offer->toArray();

        $timeFormat = Time::format((string) $num['added'], true, false);
        $offertime = ($curUser['timetype'] ?? '') !== 'timealive'
            ? (string) ($lang['text_at'] ?? '').$timeFormat
            : (string) ($lang['text_blank'] ?? '').$timeFormat;

        $status = match ($num['allowed'] ?? '') {
            'pending' => '<font color="red">'.htmlspecialchars((string) ($lang['text_pending'] ?? '')).'</font>',
            'allowed' => '<font color="green">'.htmlspecialchars((string) ($lang['text_allowed'] ?? '')).'</font>',
            default => '<font color="red">'.htmlspecialchars((string) ($lang['text_denied'] ?? '')).'</font>',
        };

        $voteCounts = OfferRepository::getVoteCounts($id);
        $yeah = (int) $voteCounts['yeah'];
        $against = (int) $voteCounts['against'];

        $allowRow = '';
        if (Permission::can(PermissionEnum::OFFER_MANAGE) && ($num['allowed'] ?? '') === 'pending') {
            $allowRow = '<table><tr><td class="embedded"><form method="post" action="?allow_offer=1"><input type="hidden" value="'.$id.'" name="offerid" />'.
                '<input class="btn" type="submit" value="'.htmlspecialchars((string) ($lang['submit_allow'] ?? '')).'" />&nbsp;&nbsp;</form></td><td class="embedded"><form method="post" action="?id='.$id.'&amp;finish_offer=1">'.
                '<input type="hidden" value="'.$id.'" name="finish" /><input class="btn" type="submit" value="'.htmlspecialchars((string) ($lang['submit_let_votes_decide'] ?? '')).'" /></form></td></tr></table>';
        }

        $voteRow = '';
        $voteResultsRow = '';
        if (($num['allowed'] ?? '') === 'pending') {
            $voteRow = '<b><a href="?id='.$id.'&amp;vote=yeah"><font color="green">'.htmlspecialchars((string) ($lang['text_for'] ?? '')).'</font></a></b>'.
                (Permission::can(PermissionEnum::AGAINST_OFFER) ? ' - <b><a href="?id='.$id.'&amp;vote=against"><font color="red">'.htmlspecialchars((string) ($lang['text_against'] ?? '')).'</font></a></b>' : '');
            $voteResultsRow = '<b>'.htmlspecialchars((string) ($lang['text_for'] ?? '')).":</b> {$yeah}  <b>".htmlspecialchars((string) ($lang['text_against'] ?? ''))."</b> {$against} &nbsp; &nbsp; <a href=\"?id=".$id.'&amp;offer_vote=1"><i>'.htmlspecialchars((string) ($lang['text_see_vote_detail'] ?? '')).'</i></a>';
        }

        $allowedNote = '';
        if (($num['allowed'] ?? '') === 'allowed' && $userId !== (int) ($num['userid'] ?? 0)) {
            $allowedNote = (string) ($lang['text_voter_receives_pm_note'] ?? '');
        }
        if (($num['allowed'] ?? '') === 'allowed' && $userId === (int) ($num['userid'] ?? 0)) {
            $allowedNote = (string) ($lang['text_urge_upload_offer_note'] ?? '');
        }

        $edit = '';
        $delete = '';
        if ($userId === (int) ($num['userid'] ?? 0) || Permission::can(PermissionEnum::OFFER_MANAGE)) {
            $edit = '<a href="?id='.$id.'&amp;edit_offer=1"><img class="dt_edit" src="pic/trans.gif" alt="edit" />&nbsp;<b><font class="small">'.htmlspecialchars((string) ($lang['text_edit_offer'] ?? '')).'</font></b></a>&nbsp;|&nbsp;';
            $delete = '<a href="?id='.$id.'&amp;del_offer=1&amp;sure=0"><img class="dt_delete" src="pic/trans.gif" alt="delete" />&nbsp;<b><font class="small">'.htmlspecialchars((string) ($lang['text_delete_offer'] ?? '')).'</font></b></a>&nbsp;|&nbsp;';
        }
        $report = '<a href="report.php?reportofferid='.$id.'"><img class="dt_report" src="pic/trans.gif" alt="report" />&nbsp;<b><font class="small">'.htmlspecialchars((string) ($lang['report_offer'] ?? '')).'</font></b></a>';

        $description = '';
        if (! empty($num['descr'])) {
            $description = Format::formatComment((string) $num['descr']);
        }

        // Comments section
        $commentCount = OfferRepository::countComments($id);
        $commentbar = '<p align="center"><a class="index" href="comment.php?action=add&amp;pid='.$id.'&amp;type=offer">'.htmlspecialchars((string) ($lang['text_add_comment'] ?? '')).'</a></p>'."\n";

        $commentsHtml = '';
        $pagerTop = '';
        $pagerBottom = '';
        if (! $commentCount) {
            $commentsHtml = '<h1 id="startcomments" align="center">'.htmlspecialchars((string) ($lang['text_no_comments'] ?? '')).'</h1>'."\n";
        } else {
            [$pagerTop, $pagerBottom, , $offset, $perpage] = Pagination::pager(10, $commentCount, "offers.php?id={$id}&off_details=1&", ['lastpagedefault' => 1]);
            $commentRows = OfferRepository::getComments($id, (int) $offset, (int) $perpage);
            $allrows = [];
            foreach ($commentRows as $commentObj) {
                $allrows[] = $commentObj->toArray();
            }
            ob_start();
            echo $pagerTop;
            Comment::tableVoid($allrows, 'offer', $id);
            echo $pagerBottom;
            $commentsHtml = (string) ob_get_clean();
        }

        $quickComment = '<table style=\'border:1px solid #000000;\'><tr>'.
            '<td class="text" align="center"><b>'.htmlspecialchars((string) ($lang['text_quick_comment'] ?? '')).'</b><br /><br />'.
            '<form id="compose" name="comment" method="post" action="comment.php?action=add&amp;type=offer" onsubmit="return postvalid(this);">'.
            '<input type="hidden" name="pid" value="'.$id.'" /><br />';
        ob_start();
        Html::quickReplyVoid('comment', 'body', (string) ($lang['submit_add_comment'] ?? ''));
        $quickComment .= (string) ob_get_clean();
        $quickComment .= '</form></td></tr></table>';

        return [
            'id' => $id,
            'name' => htmlspecialchars((string) ($num['name'] ?? '')),
            'offeredBy' => UserDisplay::username((int) ($num['userid'] ?? 0)),
            'offerTime' => $offertime,
            'status' => $status,
            'allowRow' => $allowRow,
            'voteRow' => $voteRow,
            'voteResultsRow' => $voteResultsRow,
            'allowedNote' => $allowedNote,
            'editLink' => $edit,
            'deleteLink' => $delete,
            'reportLink' => $report,
            'description' => $description,
            'commentCount' => $commentCount,
            'commentbar' => $commentbar,
            'commentsHtml' => $commentsHtml,
            'pagerTop' => $pagerTop,
            'pagerBottom' => $pagerBottom,
            'quickComment' => $quickComment,
        ];
    }

    /**
     * Build the edit offer form section.
     *
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    private function buildEditOffer(array $lang, array $curUser, int $userId, Request $request, mixed $browsecatmode): array
    {
        $id = (int) $request->query('id', 0);
        $offer = OfferRepository::findOffer($id);
        if (! $offer) {
            Html::stdMessage((string) ($lang['std_error'] ?? ''), (string) ($lang['text_nothing_found'] ?? ''));

            return [];
        }
        $num = $offer->toArray();

        if ($userId !== (int) ($num['userid'] ?? 0) && ! Permission::can(PermissionEnum::OFFER_MANAGE)) {
            LegacyResponse::abort((string) ($lang['std_error'] ?? ''), (string) ($lang['std_cannot_edit_others_offer'] ?? ''));
        }

        $body = htmlspecialchars(Input::unescape((string) ($num['descr'] ?? '')));
        $id2 = (int) ($num['category'] ?? 0);

        $catSelect = "<select name=\"category\">\n";
        foreach (Category::listByModeWithContext($browsecatmode) as $row) {
            $rowArr = (array) $row;
            $selected = (int) $rowArr['id'] === $id2 ? ' selected="selected"' : '';
            $catSelect .= '<option value="'.(int) $rowArr['id'].'"'.$selected.'>'.htmlspecialchars((string) $rowArr['name'])."</option>\n";
        }
        $catSelect .= "</select>\n";

        return [
            'id' => $id,
            'title' => htmlspecialchars(trim((string) ($num['name'] ?? ''))),
            'catSelect' => $catSelect,
            'bbcodeEditor' => Form::bbcodeEditor('compose', 'body', $body, false, 130, true),
        ];
    }

    /**
     * Build the offer vote results list section.
     *
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    private function buildOfferVoteList(array $lang, Request $request): array
    {
        $offerId = (int) $request->query('id', 0);
        $count = OfferRepository::getVoteCount($offerId);
        $offerName = (string) OfferRepository::getOfferName($offerId);

        $perpage = 25;
        $self = (string) SupportContext::getServerValue('PHP_SELF');
        [$pagerTop, $pagerBottom, , $offset, $perpage] = Pagination::pager($perpage, $count, $self.'?id='.$offerId.'&offer_vote=1&');
        $voteRows = OfferRepository::getVoteRows($offerId, (int) $offset, (int) $perpage);

        $rows = [];
        foreach ($voteRows as $arr) {
            $arrArr = (array) $arr;
            $vote = match ($arrArr['vote'] ?? '') {
                'yeah' => '<b><font color=green>'.htmlspecialchars((string) ($lang['text_for'] ?? '')).'</font></b>',
                'against' => '<b><font color=red>'.htmlspecialchars((string) ($lang['text_against'] ?? '')).'</font></b>',
                default => 'unknown',
            };
            $rows[] = [
                'username' => UserDisplay::username((int) ($arrArr['userid'] ?? 0)),
                'vote' => $vote,
            ];
        }

        return [
            'offerId' => $offerId,
            'offerName' => htmlspecialchars($offerName),
            'hasVotes' => ! $voteRows->isEmpty(),
            'noVotesNote' => (string) ($lang['std_no_votes_yet'] ?? ''),
            'pagerTop' => $pagerTop,
            'pagerBottom' => $pagerBottom,
            'rows' => $rows,
        ];
    }

    /**
     * Build the main offer list section.
     *
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>  $globalData
     * @return array<string, mixed>
     */
    private function buildList(array $lang, array $curUser, int $userId, Request $request, array $globalData): array
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

        $self = (string) SupportContext::getServerValue('PHP_SELF');
        $offerResult = OfferRepository::getLegacyList($categ, $offerorid, $search, $sortColumn, $direction, 0, 0);
        $count = (int) $offerResult['count'];

        [$pagerTop, $pagerBottom, , $offset, $perpage] = Pagination::pager(
            $perpage,
            $count,
            $self.'?'.'category='.((string) $request->query('category', '')).'&sort='.((string) $request->query('sort', '')).'&'
        );

        $offerResult = OfferRepository::getLegacyList($categ, $offerorid, $search, $sortColumn, $direction, (int) $offset, (int) $perpage);
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
            $Cache = SupportContext::getCache();
            foreach ($offerRows as $row) {
                $arr = (array) $row;
                $addedby = UserDisplay::username((int) ($arr['userid'] ?? 0));
                $comms = (int) ($arr['comments'] ?? 0);
                if ($comms === 0) {
                    $comment = '<a href="comment.php?action=add&amp;pid='.(int) $arr['id'].'&amp;type=offer" title="'.htmlspecialchars((string) ($lang['title_add_comments'] ?? '')).'">0</a>';
                } else {
                    $lastcom = $Cache?->get_value('offer_'.(int) $arr['id'].'_last_comment_content');
                    if (! $lastcom) {
                        $lastcom = OfferRepository::getLastComment((int) $arr['id']);
                        $Cache?->cache_value('offer_'.(int) $arr['id'].'_last_comment_content', $lastcom, 1855);
                    }
                    $lastcom = (array) $lastcom;
                    $timestamp = strtotime((string) ($lastcom['added'] ?? 'now'));
                    $hasnewcom = (($lastcom['user'] ?? 0) !== $userId && $timestamp >= $last_offer);
                    if (($curUser['showlastcom'] ?? 'yes') !== 'no') {
                        $title = '';
                        if (! empty($lastcom)) {
                            if (($curUser['timetype'] ?? '') !== 'timealive') {
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

                $allowed = match ($arr['allowed'] ?? '') {
                    'allowed' => '&nbsp;<b>[<font color="green">'.htmlspecialchars((string) ($lang['text_allowed'] ?? '')).'</font>]</b>',
                    'denied' => '&nbsp;<b>[<font color="red">'.htmlspecialchars((string) ($lang['text_denied'] ?? '')).'</font>]</b>',
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
                    if (($arr['allowed'] ?? '') === 'allowed') {
                        $futuretime = strtotime((string) ($arr['allowedtime'] ?? 'now')) + $globalData['offeruptimeoutMain'];
                        $timeout = Time::format(date('Y-m-d H:i:s', $futuretime), false, true, true, false, true);
                    } elseif (($arr['allowed'] ?? '') === 'pending') {
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
            if (($curUser['showlastcom'] ?? 'yes') === 'yes') {
                echo Html::tooltipContainer($lastcom_tooltip, 400);
            }
            $tableHtml = (string) ob_get_clean();
        }

        // Update last_offer timestamp
        if ($curUser) {
            UsercpRepository::updateLastOffer($userId);
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
