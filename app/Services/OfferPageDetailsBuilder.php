<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\OfferAllowed;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserTimeType;
use App\Repositories\OfferRepository;
use App\Support\Comment;
use App\Support\Format;
use App\Support\Html;
use App\Support\LegacyResponse;
use App\Support\Pagination;
use App\Support\Time;
use App\Support\UserDisplay;
use Illuminate\Http\Request;

final class OfferPageDetailsBuilder
{
    public function __construct(
        private readonly OfferRepository $offerRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @return array<string, mixed>
     */
    public function build(array $lang, array $curUser, int $userId, Request $request): array
    {
        $id = (int) $request->query('id', 0);
        if (! $id) {
            LegacyResponse::abort((string) ($lang['std_error'] ?? ''), (string) ($lang['std_smell_rat'] ?? ''));
        }

        $offer = $this->offerRepository->findOffer($id);
        if (! $offer) {
            Html::stdMessage((string) ($lang['std_error'] ?? ''), (string) ($lang['text_nothing_found'] ?? ''));

            return [];
        }
        $num = $offer->toArray();

        $timeFormat = Time::format((string) $num['added'], true, false);
        $offertime = ($curUser['timetype'] ?? 1) !== UserTimeType::TIMEALIVE->value
            ? (string) ($lang['text_at'] ?? '').$timeFormat
            : (string) ($lang['text_blank'] ?? '').$timeFormat;

        $status = match ((int) ($num['allowed'] ?? 1)) {
            OfferAllowed::PENDING->value => '<font color="red">'.htmlspecialchars((string) ($lang['text_pending'] ?? '')).'</font>',
            OfferAllowed::ALLOWED->value => '<font color="green">'.htmlspecialchars((string) ($lang['text_allowed'] ?? '')).'</font>',
            default => '<font color="red">'.htmlspecialchars((string) ($lang['text_denied'] ?? '')).'</font>',
        };

        $voteCounts = $this->offerRepository->getVoteCounts($id);
        $yeah = (int) $voteCounts['yeah'];
        $against = (int) $voteCounts['against'];

        $allowRow = '';
        if (Permission::can(PermissionEnum::OFFER_MANAGE) && (int) ($num['allowed'] ?? 1) === OfferAllowed::PENDING->value) {
            $allowRow = '<table><tr><td class="embedded"><form method="post" action="?allow_offer=1"><input type="hidden" value="'.$id.'" name="offerid" />'.
                '<input class="btn" type="submit" value="'.htmlspecialchars((string) ($lang['submit_allow'] ?? '')).'" />&nbsp;&nbsp;</form></td><td class="embedded"><form method="post" action="?id='.$id.'&amp;finish_offer=1">'.
                '<input type="hidden" value="'.$id.'" name="finish" /><input class="btn" type="submit" value="'.htmlspecialchars((string) ($lang['submit_let_votes_decide'] ?? '')).'" /></form></td></tr></table>';
        }

        $voteRow = '';
        $voteResultsRow = '';
        if ((int) ($num['allowed'] ?? 1) === OfferAllowed::PENDING->value) {
            $voteRow = '<b><a href="?id='.$id.'&amp;vote=yeah"><font color="green">'.htmlspecialchars((string) ($lang['text_for'] ?? '')).'</font></a></b>'.
                (Permission::can(PermissionEnum::AGAINST_OFFER) ? ' - <b><a href="?id='.$id.'&amp;vote=against"><font color="red">'.htmlspecialchars((string) ($lang['text_against'] ?? '')).'</font></a></b>' : '');
            $voteResultsRow = '<b>'.htmlspecialchars((string) ($lang['text_for'] ?? '')).":</b> {$yeah}  <b>".htmlspecialchars((string) ($lang['text_against'] ?? ''))."</b> {$against} &nbsp; &nbsp; <a href=\"?id=".$id.'&amp;offer_vote=1"><i>'.htmlspecialchars((string) ($lang['text_see_vote_detail'] ?? '')).'</i></a>';
        }

        $allowedNote = '';
        if ((int) ($num['allowed'] ?? 1) === OfferAllowed::ALLOWED->value && $userId !== (int) ($num['userid'] ?? 0)) {
            $allowedNote = (string) ($lang['text_voter_receives_pm_note'] ?? '');
        }
        if ((int) ($num['allowed'] ?? 1) === OfferAllowed::ALLOWED->value && $userId === (int) ($num['userid'] ?? 0)) {
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
        $commentCount = $this->offerRepository->countComments($id);
        $commentbar = '<p align="center"><a class="index" href="comment.php?action=add&amp;pid='.$id.'&amp;type=offer">'.htmlspecialchars((string) ($lang['text_add_comment'] ?? '')).'</a></p>'."\n";

        $commentsHtml = '';
        $pagerTop = '';
        $pagerBottom = '';
        if (! $commentCount) {
            $commentsHtml = '<h1 id="startcomments" align="center">'.htmlspecialchars((string) ($lang['text_no_comments'] ?? '')).'</h1>'."\n";
        } else {
            [$pagerTop, $pagerBottom, , $offset, $perpage] = Pagination::pager(10, $commentCount, "offers.php?id={$id}&off_details=1&", ['lastpagedefault' => 1]);
            $commentRows = $this->offerRepository->getComments($id, (int) $offset, (int) $perpage);
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
}
