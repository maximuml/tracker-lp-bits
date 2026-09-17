<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Contracts\Repositories\TagRepositoryInterface;
use App\Contracts\Repositories\TorrentDownloadRepositoryInterface;
use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Enums\TorrentApprovalStatus;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\TorrentBuyLog;
use App\Models\TorrentOperationLog;
use App\Models\User;
use App\Repositories\SearchBoxSchemaBuilder;
use App\Repositories\TorrentDetailRepository;
use App\Repositories\TorrentModerationRepository;
use App\Support\AssetAppender;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Comment;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\CustomField;
use App\Support\Format;
use App\Support\Globals;
use App\Support\Html;
use App\Support\Html\SafeHtml;
use App\Support\LegacyYesNo;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\Pagination;
use App\Support\Promotion;
use App\Support\Strings;
use App\Support\Time;
use App\Support\Torrent\BdInfoExtra;
use App\Support\Torrent\TechnicalInformation;
use App\Support\TorrentAccess;
use App\Support\TorrentBookmark;
use App\Support\UserDisplay;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TorrentDetailsController extends Controller
{
    private TorrentRepositoryInterface $torrentRepository;

    private TorrentDownloadRepositoryInterface $downloadRepository;

    private TorrentModerationRepository $moderationRepository;

    private SearchBoxSchemaBuilder $searchBoxSchemaBuilder;

    private TagRepositoryInterface $tagRepository;

    private TorrentDetailRepository $torrentDetailRepository;

    private CurrentUser $currentUser;

    private Globals $globals;

    private ?LegacyRedisCache $legacyRedisCache;

    public function __construct(
        TorrentRepositoryInterface $torrentRepository,
        TorrentDownloadRepositoryInterface $downloadRepository,
        TorrentModerationRepository $moderationRepository,
        SearchBoxSchemaBuilder $searchBoxSchemaBuilder,
        TagRepositoryInterface $tagRepository,
        TorrentDetailRepository $torrentDetailRepository,
        CurrentUser $currentUser,
        Globals $globals,
        ?LegacyRedisCache $legacyRedisCache = null,
    ) {
        $this->torrentRepository = $torrentRepository;
        $this->downloadRepository = $downloadRepository;
        $this->moderationRepository = $moderationRepository;
        $this->searchBoxSchemaBuilder = $searchBoxSchemaBuilder;
        $this->tagRepository = $tagRepository;
        $this->torrentDetailRepository = $torrentDetailRepository;
        $this->currentUser = $currentUser;
        $this->globals = $globals;
        $this->legacyRedisCache = $legacyRedisCache;
    }

    public function show(Request $request, int $id): View|RedirectResponse|Response
    {
        if ($id <= 0) {
            abort(404);
        }

        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return redirect('/login.php?returnto='.urlencode($request->fullUrl()));
        }

        $torrent = Torrent::query()->find($id);
        if (! $torrent instanceof Torrent) {
            abort(404);
        }

        Gate::forUser($user)->authorize('view', $torrent);

        if ($this->legacyRedisCache === null) {
            $query = $request->query->all();
            unset($query['id']);

            return redirect('/details.php?id='.$id.($query ? '&'.http_build_query($query) : ''));
        }

        $row = $this->torrentDetailRepository->getTorrent($id);
        if (empty($row)) {
            Logger::writeWithContext((string) "TorrentDetailsRepository getTorrent empty: {$id}", (string) 'info', (bool) false);
            abort(404);
        }

        $currentUser = $this->currentUser->get() ?? $user->toLegacyArray();
        $this->currentUser->set($currentUser);

        $headTitle = empty($request->input('cmtpage'))
            ? (__('legacy/details.head_details_for_torrent')).'"'.$row['name'].'"'
            : (__('legacy/details.head_comments_for_torrent')).'"'.$row['name'].'"';

        $denyLog = $row['approval_status'] == TorrentApprovalStatus::DENY->value
            ? $this->torrentDetailRepository->getLatestApprovalDenyLog($id)
            : null;

        $hasBuy = TorrentBuyLog::query()->where('uid', $currentUser['id'] ?? 0)->where('torrent_id', $id)->exists();

        $requestFlags = [
            'hit' => $request->has('hit'),
            'cmtpage' => $request->has('cmtpage'),
            'uploaded' => $request->has('uploaded'),
            'edited' => $request->has('edited'),
            'existed' => $request->has('existed'),
            'returnto' => (string) $request->input('returnto', ''),
            'dllist' => (int) $request->input('dllist', 0) === 1,
        ];

        if ($requestFlags['hit']) {
            $this->torrentDetailRepository->incrementViews($id);
        }

        $headers = [];
        if ($requestFlags['uploaded']) {
            $headers['Refresh'] = "1; url=download.php?id={$id}";
        }

        $tagIds = $this->torrentDetailRepository->getTagIds($id);

        $viewData = $this->buildDetailsViewData($id, $row, $currentUser, $user, $denyLog, $hasBuy, $tagIds, $requestFlags);

        return response()->view('torrent.details', array_merge([
            'id' => $id,
            'torrentId' => $id,
            'torrentRow' => $row,
            'user' => $user,
            'currentUser' => $currentUser,
            'customField' => new CustomField,
            'headTitle' => $headTitle,
            'tagIds' => $tagIds,
            'denyLog' => $denyLog,
            'hasBuy' => $hasBuy,
            'requestFlags' => $requestFlags,
        ], $viewData), 200, $headers);
    }

    /**
     * @param  array<int|string, mixed>  $row
     * @param  array<int|string, mixed>  $currentUser
     * @param  array<int, int>  $tagIds
     * @param  array<string, mixed>  $requestFlags
     * @return array<string, mixed>
     */
    private function buildDetailsViewData(int $id, array $row, array $currentUser, User $user, ?TorrentOperationLog $denyLog, bool $hasBuy, array $tagIds, array $requestFlags): array
    {
        $torrentRep = $this->torrentRepository;
        $searchBoxRep = $this->searchBoxSchemaBuilder;
        $tagRep = $this->tagRepository;
        $customField = new CustomField;

        $bannedTorrent = ($row['banned'] ?? 0) == 1
            ? ' <b>(<font class="striking">'.(__('legacy/functions.text_banned')).'</font>)</b>'
            : '';

        $spTorrent = Promotion::appendWithContext(
            (int) $row['sp_state'], 'word', false, '', 0, '', $row['__ignore_global_sp_state'] ?? false
        );
        $spTorrentSub = Promotion::appendSubWithContext(
            (int) $row['sp_state'], '', true, $row['added'] ?? null,
            (int) ($row['promotion_time_type'] ?? 0),
            $row['promotion_until'] ?? null,
            $row['__ignore_global_sp_state'] ?? false
        );

        $torrentTopHtml = htmlspecialchars((string) $row['name'])
            .$bannedTorrent
            .$torrentRep->getPaidIcon($row, 20)
            .($spTorrent ? '&nbsp;&nbsp;&nbsp;'.$spTorrent : '')
            .$spTorrentSub
            .TorrentAccess::hrImage($row, (int) ($row['search_box_id'] ?? 0))
            .$this->moderationRepository->renderApprovalStatus($row['approval_status'] ?? null);

        $editUrl = "edit.php?id={$id}";
        if ($requestFlags['returnto'] ?? '') {
            $editUrl .= '&returnto='.rawurlencode($requestFlags['returnto']);
        }

        $canViewAnonymous = Permission::can(PermissionEnum::VIEW_ANONYMOUS);
        $isOwner = (int) $currentUser['id'] === (int) ($row['owner'] ?? 0);
        if (($row['anonymous'] ?? 0) == 1) {
            if (! $canViewAnonymous && ! $isOwner) {
                $uprow = '<i>'.(__('legacy/details.text_anonymous')).'</i>';
            } else {
                $uprow = '<i>'.(__('legacy/details.text_anonymous')).'</i> ('.UserDisplay::username((int) ($row['owner'] ?? 0), false, true, true, false, false, true).')';
            }
        } else {
            $uprow = isset($row['owner'])
                ? UserDisplay::username((int) $row['owner'], false, true, true, false, false, true)
                : '<i>'.(('')).'</i>';
        }

        $bookmarkMarkup = TorrentBookmark::stateMarkupWithContext((int) $currentUser['id'], $id, false);
        $tagHtml = $tagRep->renderSpan((int) ($row['search_box_id'] ?? 0), $tagIds);

        $taxonomyInfo = $searchBoxRep->listTaxonomyInfo((int) ($row['search_box_id'] ?? 0), $row);
        $taxonomyRendered = '';
        foreach ($taxonomyInfo as $item) {
            $taxonomyRendered .= sprintf('&nbsp;&nbsp;&nbsp;<b>%s: </b>%s', $item['label'] ?? '', $item['value'] ?? '');
        }

        $downloadUrl = $this->downloadRepository->getDownloadUrl($id, $currentUser);
        $customFieldsHtml = $customField->renderOnTorrentDetailsPage($id, (int) ($row['search_box_id'] ?? 0));

        $technicalInfoResult = null;
        if (SiteConfig::current()->main->enableTechnicalInfo() && ! empty($row['technical_info'])) {
            $escaped = Strings::escapeHtml((string) $row['technical_info']);
            $technicalData = is_string($escaped) ? $escaped : '';
            $isBdInfo = false;
            if (! empty($technicalData)) {
                $firstLine = (string) strtok($technicalData, "\n");
                if (
                    str_contains($firstLine, 'DISC INFO')
                    || str_contains($firstLine, 'Disc Title')
                    || str_contains($firstLine, 'Disc Label')
                ) {
                    $isBdInfo = true;
                }
            }

            if ($isBdInfo) {
                $technicalInfo = new BdInfoExtra($technicalData);
            } else {
                $technicalInfo = new TechnicalInformation($technicalData);
            }

            $technicalInfoResult = $technicalInfo->renderOnDetailsPage();
        }

        $descr = ! empty($row['descr']) ? Format::formatComment((string) $row['descr']) : '';
        $bonusOptions = Setting::getBonusRewardOptions();

        $isOwner = (int) $currentUser['id'] === (int) ($row['owner'] ?? 0);
        $owned = Permission::can(PermissionEnum::TORRENT_MANAGE) || $isOwner;
        $downloadAllowed = $isOwner || ! LegacyYesNo::isNo($currentUser['downloadpos'] ?? null);

        $uploadTime = ($currentUser['timetype'] ?? '') !== 'timealive'
            ? (__('legacy/details.text_at')).$row['added']
            : (__('legacy/details.text_blank')).Time::format((string) $row['added'], true, false);

        $denyBannerHtml = '';
        if (($row['approval_status'] ?? null) == TorrentApprovalStatus::DENY->value && $denyLog !== null) {
            $dangerIcon = '<svg t="1655242121471" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="46590" width="16" height="16"><path d="M963.555556 856.888889a55.978667 55.978667 0 0 1-55.978667 56.007111c-0.284444 0-0.540444-0.085333-0.824889-0.085333l-0.056889 0.085333H110.734222l-0.654222-1.137778A55.409778 55.409778 0 0 1 56.888889 856.462222c0-9.756444 2.730667-18.773333 7.139555-26.737778l-3.726222-6.599111L453.461333 156.302222A59.335111 59.335111 0 0 1 510.236444 113.777778c26.936889 0 49.436444 18.005333 56.803556 42.552889l389.973333 661.447111-3.669333 6.997333c6.4 9.102222 10.211556 20.138667 10.211556 32.113778z m-497.777778-541.326222l16.014222 312.888889h56.888889l16.014222-312.888889h-88.917333z m44.458666 398.222222a56.888889 56.888889 0 1 0-0.028444 113.749333 56.888889 56.888889 0 0 0 0.028444-113.749333z" p-id="46591" fill="#d81e06" data-spm-anchor-id="a313x.7781069.0.i61" class="selected"></path></svg>';
            $denyBannerHtml = sprintf(
                '<div class="nx-flex-center" style="margin-bottom: 10px"><div style="background-color: black; color: white;font-weight: bold; padding: 10px 100px">%s&nbsp;%s</div></div>',
                $dangerIcon,
                Locale::trans('torrent.approval.deny_comment_show', ['reason' => $denyLog->comment], null)
            );
        }

        $actions = [];
        if ($downloadAllowed) {
            if ($row['price'] > 0) {
                $downloadBtn = $hasBuy
                    ? __('legacy/details.text_download_bought_torrent')
                    : sprintf(__('legacy/details.text_download_paid_torrent'), number_format((float) $row['price']));
            } else {
                $downloadBtn = __('legacy/details.text_download_torrent');
            }
            $actions[] = sprintf(
                '<a title="%s" href="download.php?id=%s"><img class="dt_download" src="pic/trans.gif" alt="download" />&nbsp;<b><font class="small">%s</font></b></a>',
                __('legacy/details.title_download_torrent'),
                $id,
                $downloadBtn
            );
        }
        if ($owned) {
            $actions[] = sprintf(
                '<a title="%s" href="%s"><img class="dt_edit" src="pic/trans.gif" alt="edit" />&nbsp;<b><font class="small">%s</font></b></a>',
                __('legacy/details.title_edit_torrent'),
                $editUrl,
                Permission::can(PermissionEnum::TORRENT_MANAGE)
                    ? __('legacy/details.text_edit_and_delete_torrent')
                    : __('legacy/details.text_edit_torrent')
            );
        }
        if (Permission::can(PermissionEnum::ASK_RESEED) && (int) $row['seeders'] === 0) {
            $actions[] = sprintf(
                '<a title="%s" href="takereseed.php?reseedid=%s"><img class="dt_reseed" src="pic/trans.gif" alt="reseed">&nbsp;<b><font class="small">%s</font></b></a>',
                __('legacy/details.title_ask_for_reseed'),
                $id,
                __('legacy/details.text_ask_for_reseed')
            );
        }
        if (
            Permission::can(PermissionEnum::TORRENT_APPROVAL)
            && (SiteConfig::current()->torrent->approvalStatusIconEnabled() || ! SiteConfig::current()->torrent->approvalStatusNoneVisible())
        ) {
            $approvalIcon = '<svg t="1655224943277" class="icon" viewBox="0 0 1397 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="45530" width="16" height="16"><path d="M1396.363636 121.018182c0 0-223.418182 74.472727-484.072727 372.363636-242.036364 269.963636-297.890909 381.672727-390.981818 530.618182C512 1014.690909 372.363636 744.727273 0 549.236364l195.490909-186.181818c0 0 176.872727 121.018182 297.890909 344.436364 0 0 307.2-474.763636 902.981818-707.490909L1396.363636 121.018182 1396.363636 121.018182zM1396.363636 121.018182" p-id="45531" fill="#e78d0f"></path></svg>';
            $actions[] = sprintf(
                '<a href="#"><b><font id="approval" class="small approval" data-torrent_id="%s">%s&nbsp;%s</font></b></a>',
                $row['id'],
                $approvalIcon,
                __('legacy/details.action_approval')
            );
            $approvalTitle = Locale::trans('torrent.approval.modal_title', [], null);
            AssetAppender::js(sprintf(<<<'JS'
document.getElementById('approval').addEventListener("click", function () {
    var torrentId = this.getAttribute('data-torrent_id')
    layer.open({
        type: 2,
        title: %s,
        area: ['60%%', '600px'],
        content: '/web/torrent-approval-page?torrent_id=' + torrentId,
    })
})
JS, \json_encode($approvalTitle)), 'footer', false);
        }
        $actions[] = sprintf(
            '<a title="%s" href="report.php?torrent=%s"><img class="dt_report" src="pic/trans.gif" alt="report" />&nbsp;<b><font class="small">%s</font></b></a>',
            __('legacy/details.title_report_torrent'),
            $id,
            __('legacy/details.text_report_torrent')
        );
        $actionsHtml = implode('&nbsp;|&nbsp;', $actions);

        $filesInfo = '';
        if (($row['type'] ?? '') === 'multi') {
            $filesInfo = sprintf(
                '<b>%s</b>%s%s<br /><span id="showfl"><a href="#" data-filelist="%s">%s</a></span><span id="hidefl" class="nx-hidden"><a href="#" data-filelist="%s" data-filelist-mode="hide">%s</a></span>',
                __('legacy/details.text_num_files'),
                $row['numfiles'],
                __('legacy/details.text_files'),
                $id,
                __('legacy/details.text_see_full_list'),
                $id,
                __('legacy/details.text_hide_list')
            );
        }
        $infoTds = [];
        if ($filesInfo !== '') {
            $infoTds[] = '<td class="no_border_wide">'.$filesInfo.'</td>';
        }
        $infoTds[] = sprintf(
            '<td class="no_border_wide"><b>%s:</b>&nbsp;%s</td>',
            __('legacy/details.row_info_hash'),
            bin2hex(Strings::padHash($row['info_hash']))
        );
        if (Permission::can(PermissionEnum::TORRENT_STRUCTURE)) {
            $infoTds[] = sprintf(
                '<td class="no_border_wide"><b>%s</b><a href="torrent_info.php?id=%s">%s</a></td>',
                __('legacy/details.text_torrent_structure'),
                $id,
                __('legacy/details.text_torrent_info_note')
            );
        }
        $torrentInfoRowHtml = '<table><tr>'.implode('', $infoTds).'</tr></table><span id=\'filelist\'></span>';

        $hotMeterHtml = sprintf(
            '<table><tr><td class="no_border_wide"><b>%s</b>%s</td><td class="no_border_wide"><b>%s</b>%s</td><td class="no_border_wide"><b>%s</b><a href="viewsnatches.php?id=%s"><b>%s%s</td><td class="no_border_wide"><b>%s</b>%s</td></tr></table>',
            __('legacy/details.text_views'),
            $row['views'],
            __('legacy/details.text_hits'),
            $row['hits'],
            __('legacy/details.text_snatched'),
            $id,
            $row['times_completed'],
            __('legacy/details.text_view_snatches'),
            __('legacy/details.row_last_seeder'),
            Time::format((string) $row['last_action'])
        );

        $peersHeadHtml = SafeHtml::fromTrustedHtml(sprintf(
            '<span id="seeders"></span><span id="leechers"></span>%s<br /><span id="showpeer"><a href="#" data-peerlist="%s" class="sublink">%s</a></span><span id="hidepeer" class="nx-hidden"><a href="#" data-peerlist="%s" data-peerlist-mode="hide" class="sublink">%s</a></span>',
            __('legacy/details.row_peers'),
            $row['id'],
            __('legacy/details.text_see_full_list'),
            $row['id'],
            __('legacy/details.text_hide_list')
        ));
        $peersBodyHtml = sprintf(
            '<div id="peercount"><b>%s%s%s</b> | <b>%s%s%s</b></div><div id="peerlist"></div>',
            $row['seeders'],
            __('legacy/details.text_seeders'),
            Strings::addS((int) $row['seeders']),
            $row['leechers'],
            __('legacy/details.text_leechers'),
            Strings::addS((int) $row['leechers'])
        );

        if ($requestFlags['dllist'] ?? false) {
            AssetAppender::js(sprintf('viewpeerlist(%s)', (int) $row['id']), 'footer', false);
        }

        AssetAppender::css(<<<'CSS'
ul.magic
{
    cursor:pointer;
    list-style-type:none;
    padding-left:0px;
}
ul.magic li
{
    margin:0px;text-align:center;float:left;width:40px;margin-right:15px; height:21px;background:url("styles/huise.png") no-repeat;
    padding-left:5px;padding-right:5px;
    line-height:20px;
}
ul.magic li:hover
{
    background:url("styles/boli.png") no-repeat
}
CSS, 'header', false);

        $descrHeadHtml = SafeHtml::fromTrustedHtml(sprintf(
            '<a href="#" data-klappe="descr"><span class="nowrap"><img class="minus" src="pic/trans.gif" alt="Show/Hide" id="picdescr" title="%s" /> %s</span></a>',
            __('legacy/details.title_show_or_hide'),
            __('legacy/details.row_description')
        ));
        $showDescription = ! LegacyYesNo::isNo($currentUser['showdescription'] ?? null) && $descr !== '';

        $magicInfo = $this->torrentDetailRepository->getMagicInfo($id, (int) $currentUser['id']);
        $thanksInfo = $this->torrentDetailRepository->getThanksInfo($id, (int) $currentUser['id']);

        $userIds = array_filter(array_unique([
            (int) ($row['owner'] ?? 0),
            (int) ($currentUser['id'] ?? 0),
        ]));
        foreach ($magicInfo['givers'] as $giver) {
            $userIds[] = (int) ($giver->userid ?? 0);
        }
        foreach ($thanksInfo['thanks'] as $t) {
            $userIds[] = (int) ($t->userid ?? 0);
        }
        $userIds = array_filter(array_unique($userIds));

        $userDisplayMap = [];
        foreach ($userIds as $uid) {
            $userDisplayMap[$uid] = UserDisplay::username($uid, false, true, true, false, false, true);
        }
        $currentUserHtml = UserDisplay::username((int) ($currentUser['id'] ?? 0), false, true, true, false, false, true);

        $magicButtonsInner = '';
        $bonusHas = (float) ($currentUser['seedbonus'] ?? 0);
        if (! $isOwner) {
            if ((int) $bonusHas < (int) ($bonusOptions[0] ?? 0)) {
                $magicButtonsInner = sprintf(
                    '<input class="btn" type="button" value="%s" disabled="disabled" />',
                    __('legacy/details.magic_have_no_enough_bonus_value')
                );
            } else {
                foreach ($bonusOptions as $key => $eachTemp) {
                    $eachTemp = (int) $eachTemp;
                    if ($eachTemp > 0 && $eachTemp <= $bonusHas) {
                        $magicButtonsInner .= sprintf(
                            '<li data-torrent-id="%s" data-magic-value="%s" style="cursor:pointer"><font style="font-size:8pt;padding-right:5px;">%s</font></li>',
                            $id,
                            $eachTemp,
                            '+'.$eachTemp
                        );
                    }
                }
            }
        }
        $magicSpan = sprintf(
            '<input class="btn nx-hidden" type="button" id="magic_add" value="%s" disabled="disabled" />&nbsp;',
            __('legacy/details.span_description_have_given')
        );
        $giveValue = [];
        foreach ($magicInfo['givers'] as $giver) {
            $giveValue[] = ($userDisplayMap[(int) $giver->userid] ?? '').' ';
        }
        $magicValueButton = $magicButtonsInner;
        $lowBonus = ! $isOwner && (int) $bonusHas < (int) ($bonusOptions[0] ?? 0);
        if (! $lowBonus) {
            if ((int) $magicInfo['whether_have_give_value'] === 0) {
                $magicValueButton = '<ul id="listNumber" class="magic">'.$magicValueButton.'</ul>';
            } else {
                $addValueText = str_replace('Number', (string) $magicInfo['add_value'], (string) __('legacy/details.magic_value_number'));
                $magicValueButton = sprintf('<input class="btn" type="button" value="%s" disabled="disabled" />', $addValueText);
            }
        }

        $showList = '';
        $showAll = '';
        $otherUserSpan = '';
        $showListDescription = '';
        $showListNewNumber = 6;
        if (count($giveValue) > 0) {
            $countUserSpan = '<span id="count_user_spa">'.$magicInfo['count_user_number'].'</span>';
            $newestRecord = '<span id="magic_newest_record">'.__('legacy/details.magic_newest_record').'</span>';
            $showListDescription = str_replace('Number', $countUserSpan, '('.$newestRecord.__('legacy/details.magic_sum_user_give_number').')');
            $showList = implode('', array_map(static fn ($v) => $v.'  ', array_slice($giveValue, 0, $showListNewNumber)));
            if (count($giveValue) > $showListNewNumber) {
                $showList .= '<span id="ellipsis">&nbsp;......&nbsp;</span>';
                $showAll = '<a href="#" id="magic_show_all" style="cursor:pointer">['.__('legacy/details.magic_show_all_description').']</a>'.'<br/>';
                $otherUserSpan = '<span id="other_user_list" class="nx-hidden">'
                    .implode('', array_map(static fn ($v) => $v.'  ', array_slice($giveValue, $showListNewNumber)))
                    .'</span>';
            }
        }
        $currentUserMagic = "<span id='current_user_magic' class='nx-hidden'>".$currentUserHtml.'</span>&nbsp;';
        $haveGotBonus = str_replace(
            'Number',
            '<span id="spanSumAll">'.$magicInfo['sum_value'].'</span>',
            __('legacy/details.magic_haveGotBonus').'&nbsp'
        );
        $magicRowHtml = '<div style="height:25px">'.$magicValueButton.$magicSpan.$haveGotBonus.$showAll.'</div>'
            .'<div>'.$currentUserMagic.$showList.$otherUserSpan.$showListDescription.'</div>';

        $thanksBy = '';
        foreach ($thanksInfo['thanks'] as $t) {
            if ((int) $t->userid !== (int) $currentUser['id']) {
                $thanksBy .= ($userDisplayMap[(int) $t->userid] ?? '').' ';
            }
        }
        $thanksAll = count($thanksInfo['thanks']);
        $noThanks = $thanksAll === 0 ? __('legacy/details.text_no_thanks_added') : '';
        if ($thanksInfo['has_thanked']) {
            $buttonValue = ' value="'.__('legacy/details.submit_you_said_thanks').'" disabled="disabled"';
            $thanksBy = $currentUserHtml.' '.$thanksBy;
        } else {
            $buttonValue = ' value="'.__('legacy/details.submit_say_thanks').'"';
        }
        $thanksButton = '<input class="btn" type="button" id="saythanks" data-torrent-id="'.$id.'" '.$buttonValue.' />';
        $commentPagerTop = '';
        $commentPagerBottom = '';
        $commentsTableHtml = '';
        $commentCount = 0;
        if (! LegacyYesNo::isNo($currentUser['showcomment'] ?? null)) {
            $commentCount = $this->torrentDetailRepository->getCommentCount($id);
            if ($commentCount > 0) {
                [$commentPagerTop, $commentPagerBottom, , $commentOffset, $commentRpp] = Pagination::pager(
                    10, $commentCount, "details.php?id=$id&cmtpage=1&", ['lastpagedefault' => 1], 'page'
                );
                $commentsTableHtml = Comment::table(
                    array_values(array_map(
                        fn ($comment) => (array) $comment,
                        $this->torrentDetailRepository->getComments($id, (int) $commentOffset, (int) $commentRpp)
                    )),
                    'torrent',
                    $id
                );
            }
        }
        $quickReplyHtml = Html::quickReply('comment', 'body', (string) (__('legacy/details.submit_add_comment')));

        $andMore = $thanksAll < $thanksInfo['count']
            ? __('legacy/details.text_and_more').$thanksInfo['count'].__('legacy/details.text_users_in_total')
            : '';
        $thanksRowHtml = '<span id="thanksadded" class="nx-hidden"><input class="btn" type="button" value="'
            .__('legacy/details.text_thanks_added').'" disabled="disabled" /></span><span id="curuser" class="nx-hidden">'
            .$currentUserHtml.' </span><span id="thanksbutton">'.$thanksButton.'</span>&nbsp;&nbsp;<span id="nothanks">'
            .$noThanks.'</span><span id="addcuruser"></span>'.$thanksBy.$andMore;

        return [
            'torrentTopHtml' => SafeHtml::fromTrustedHtml($torrentTopHtml),
            'editUrl' => $editUrl,
            'uprow' => SafeHtml::fromTrustedHtml($uprow),
            'bookmarkMarkup' => SafeHtml::fromTrustedHtml($bookmarkMarkup),
            'tagHtml' => SafeHtml::fromTrustedHtml($tagHtml),
            'taxonomyRendered' => SafeHtml::fromTrustedHtml($taxonomyRendered),
            'downloadUrl' => $downloadUrl,
            'customFieldsHtml' => SafeHtml::fromTrustedHtml($customFieldsHtml),
            'technicalInfoResult' => SafeHtml::fromTrustedHtml((string) ($technicalInfoResult ?? '')),
            'descr' => SafeHtml::fromTrustedHtml($descr),
            'bonusOptions' => $bonusOptions,
            'magicInfo' => $magicInfo,
            'thanksInfo' => $thanksInfo,
            'userDisplayMap' => $userDisplayMap,
            'currentUserHtml' => $currentUserHtml,
            'owned' => $owned,
            'isOwner' => $isOwner,
            'downloadAllowed' => $downloadAllowed,
            'uploadTime' => SafeHtml::fromTrustedHtml($uploadTime),
            'denyBannerHtml' => SafeHtml::fromTrustedHtml($denyBannerHtml),
            'actionsHtml' => SafeHtml::fromTrustedHtml($actionsHtml),
            'torrentInfoRowHtml' => SafeHtml::fromTrustedHtml($torrentInfoRowHtml),
            'hotMeterHtml' => SafeHtml::fromTrustedHtml($hotMeterHtml),
            'peersHeadHtml' => $peersHeadHtml,
            'peersBodyHtml' => SafeHtml::fromTrustedHtml($peersBodyHtml),
            'descrHeadHtml' => $descrHeadHtml,
            'showDescription' => $showDescription,
            'magicRowHtml' => SafeHtml::fromTrustedHtml($magicRowHtml),
            'thanksRowHtml' => SafeHtml::fromTrustedHtml($thanksRowHtml),
            'torrentNamePrefix' => $this->globals->get('torrentnameprefix') ?? '',
            'commentCount' => $commentCount,
            'commentPagerTop' => $commentPagerTop instanceof SafeHtml ? $commentPagerTop : SafeHtml::fromTrustedHtml($commentPagerTop),
            'commentPagerBottom' => $commentPagerBottom instanceof SafeHtml ? $commentPagerBottom : SafeHtml::fromTrustedHtml($commentPagerBottom),
            'commentsTableHtml' => SafeHtml::fromTrustedHtml($commentsTableHtml),
            'quickReplyHtml' => SafeHtml::fromTrustedHtml($quickReplyHtml),
        ];
    }
}
