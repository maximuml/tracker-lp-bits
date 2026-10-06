<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserAcceptPms;
use App\Enums\UserStatus;
use App\Models\HitAndRun;
use App\Models\User;
use App\Models\UserMeta;
use App\Repositories\HitAndRunRepository;
use App\Repositories\UserDetailRepository;
use App\Services\PermissionChecker;
use App\Support\AssetAppender;
use App\Support\Bonus;
use App\Support\Config\SiteConfig;
use App\Support\Country;
use App\Support\CurrentUser;
use App\Support\Env;
use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\LegacyResponse;
use App\Support\LegacyYesNo;
use App\Support\Locale;
use App\Support\Network;
use App\Support\Strings;
use App\Support\Url;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class UserDetailController extends Controller
{
    private HitAndRunRepository $hitAndRunRepository;

    private UserRepositoryInterface $userRepository;

    private UserDetailRepository $userDetailRepository;

    private CurrentUser $currentUser;

    public function __construct(
        private readonly PermissionChecker $permissionChecker,
        HitAndRunRepository $hitAndRunRepository,
        UserRepositoryInterface $userRepository,
        UserDetailRepository $userDetailRepository,
        CurrentUser $currentUser,
    ) {
        $this->hitAndRunRepository = $hitAndRunRepository;
        $this->userRepository = $userRepository;
        $this->userDetailRepository = $userDetailRepository;
        $this->currentUser = $currentUser;
    }

    public function show(Request $request): View|RedirectResponse
    {
        $id = (int) $request->input('id', 0);

        if ($id <= 0) {
            $currentUser = $this->currentUser->get();
            $id = (int) ($this->currentUser->id());
            if ($id <= 0) {
                abort(404);
            }
        }

        if ($this->currentUser->get() === null) {
            return redirect('/userdetails?'.$request->getQueryString());
        }

        $user = $this->userDetailRepository->getUser($id);

        if ($user === null) {
            LegacyResponse::abort(
                __('legacy/userdetails.std_error'),
                __('legacy/userdetails.std_no_such_user')
            );
        }

        if (($user['status'] ?? null) === UserStatus::PENDING->stringValue()) {
            LegacyResponse::abort(
                __('legacy/userdetails.std_sorry'),
                __('legacy/userdetails.std_user_not_confirmed')
            );
        }

        $userModel = $this->userRepository->findById($id);
        $temporaryInviteCount = $userModel instanceof User ? $this->userDetailRepository->getTemporaryInviteCount($userModel) : 0;

        return view('user.details', array_merge([
            'id' => $id,
            'user' => $user,
            'userModel' => $userModel,
            'torrentcomments' => $this->userDetailRepository->getCommentCount($id),
            'forumposts' => $this->userDetailRepository->getPostCount($id),
            'temporaryInviteCount' => $temporaryInviteCount,
            'modcomment' => $this->userDetailRepository->getModComment($id),
            'bonuscomment' => $this->userDetailRepository->getBonusComment($id),
        ], $this->buildDetailsViewData($id, $user, $userModel)));
    }

    /**
     * @param  array<int|string, mixed>  $user
     * @return array<string, mixed>
     */
    private function buildDetailsViewData(int $id, array $user, ?User $userModel): array
    {
        $currentUser = $this->currentUser->get() ?? [];
        $currentUserId = (int) ($this->currentUser->id());
        $isOwner = $currentUserId === $id;

        $canViewConfidential = Permission::can(PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO);
        $canViewHistory = Permission::can(PermissionEnum::VIEW_USER_HISTORY);
        $canManageBasic = Permission::can(PermissionEnum::MANAGE_USER_BASIC_INFO);
        $canManageConfidential = Permission::can(PermissionEnum::MANAGE_USER_CONFIDENTIAL_INFO);
        $canDeleteUser = Permission::can(PermissionEnum::USER_DELETE);
        $canViewTorrentHistory = Permission::can(PermissionEnum::TORRENT_HISTORY);
        $canViewInvite = Permission::can(PermissionEnum::VIEW_INVITE);
        $staffMember = Permission::can(PermissionEnum::STAFF_MEMBER);
        $currentClass = (int) UserDisplay::currentClass();

        $isFriend = $currentUserId > 0 ? $this->userDetailRepository->isFriend($currentUserId, $id) : false;
        $currentUserBlockedTarget = $currentUserId > 0 ? $this->userDetailRepository->isBlocked($currentUserId, $id) : false;
        $targetBlockedMe = $currentUserId > 0 ? $this->userDetailRepository->isBlocked($id, $currentUserId) : false;
        $currentUserIsFriendOfTarget = $currentUserId > 0 ? $this->userDetailRepository->isFriend($id, $currentUserId) : false;

        $showPmButton = false;
        $acceptPms = UserAcceptPms::fromStringSafe((string) ($user['acceptpms'] ?? ''));
        if ($currentUserId !== $id) {
            if ($staffMember) {
                $showPmButton = true;
            } elseif ($acceptPms === UserAcceptPms::YES) {
                $showPmButton = ! $targetBlockedMe;
            } elseif ($acceptPms === UserAcceptPms::FRIENDS) {
                $showPmButton = $currentUserIsFriendOfTarget;
            }
        }

        $countryRow = Country::rowWithContext($user['country']);

        $locationInfo = [null, null];
        $locationInfoHtml = '';
        if (SiteConfig::current()->tweak->enableLocation()) {
            if (! empty($user['ip'])) {
                $locationInfo = Network::ipLocationWithContext($user['ip']);
            }
            $locationInfoHtml = view('userdetails._location_info', ['locationInfo' => $locationInfo])->render();
        }

        $peerRows = $this->userDetailRepository->getPeers($id);
        $clientRows = [];
        foreach ($peerRows as $arr) {
            if ($canViewConfidential || $isOwner) {
                $clientRows[] = [
                    'agent' => Strings::userAgentClient($arr['agent']),
                    'ipv4' => $isOwner ? Strings::hidden($arr['ipv4']) : $arr['ipv4'],
                    'ipv6' => $isOwner ? Strings::hidden($arr['ipv6']) : $arr['ipv6'],
                    'port' => $arr['port'],
                ];
            } else {
                $clientRows[] = [
                    'agent' => Strings::userAgentClient($arr['agent']),
                    'ipv4' => '---',
                    'ipv6' => '---',
                    'port' => '---',
                ];
            }
        }
        $clientSelectHtml = $clientRows === []
            ? ''
            : view('userdetails._clients', ['rows' => $clientRows])->render();

        $trueTraffic = $this->userDetailRepository->getTrueTraffic($id);

        $userManageSystemUrl = sprintf('%s/%s/user/users/%s', Url::schemeAndHost(false), Env::get('FILAMENT_PATH', 'nexusphp'), $user['id']);

        [$migratedHelpPre, $migratedHelpPost] = array_pad(
            explode('%s', (string) __('legacy/userdetails.change_field_value_migrated'), 2),
            2,
            ''
        );
        $migratedHelp = view('userdetails._migrated_help', [
            'pre' => $migratedHelpPre,
            'post' => $migratedHelpPost,
            'url' => $userManageSystemUrl,
            'linkLabel' => (string) __('legacy/functions.text_management_system'),
        ])->render();

        $usernameHtml = UserDisplay::username($user['id'], true, false);
        $invitedByHtml = $user['invited_by'] > 0 ? UserDisplay::username($user['invited_by']) : '';
        $avatarHtml = $user['avatar'] ? UserDisplay::avatarImageWithContext(htmlspecialchars(trim((string) $user['avatar']))) : '';

        $joinWeeks = '';
        if ($user['added'] !== null && $user['added'] !== '0000-00-00 00:00:00' && $userModel instanceof User) {
            $joinWeeks = number_format(abs(Carbon::parse((string) $user['added'])->diffInWeeks()), 1).Locale::trans('nexus.time_units.week', [], null);
        }

        $shareRatio = null;
        $trueRatio = null;
        $trueDownload = (float) ($trueTraffic['downloaded'] ?? 0);
        $trueUpload = (float) ($trueTraffic['uploaded'] ?? 0);
        if ((float) $user['downloaded'] > 0 && $trueDownload > 0) {
            $shareRatio = floor($user['uploaded'] / $user['downloaded'] * 1000) / 1000;
            $trueRatio = floor($trueUpload / $trueDownload * 1000) / 1000;
        }

        $seedLeechRatio = null;
        if ((float) ($user['leechtime'] ?? 0) > 0) {
            $seedLeechRatio = floor($user['seedtime'] / $user['leechtime'] * 1000) / 1000;
        }

        $warned = LegacyYesNo::isYes($user['warned'] ?? null);
        $leechwarn = LegacyYesNo::isYes($user['leechwarn'] ?? null);
        $lastwarnedTs = ($user['lastwarned'] ?? null) !== null && $user['lastwarned'] !== ''
            ? strtotime((string) $user['lastwarned'])
            : false;
        $elapsedLastWarn = $lastwarnedTs !== false ? Format::getElapsedTime($lastwarnedTs) : '';
        $warnedUntilPretty = null;
        $warnedUntilTs = $warned && ($user['warneduntil'] ?? null) !== null && $user['warneduntil'] !== '0000-00-00 00:00:00'
            ? strtotime((string) $user['warneduntil'])
            : false;
        if ($warnedUntilTs !== false) {
            $warnedUntilPretty = Format::prettyTimeWithLocale($warnedUntilTs - time());
        }
        $leechwarnUntilPretty = null;
        $leechwarnUntilTs = $leechwarn ? strtotime((string) $user['leechwarnuntil']) : false;
        if ($leechwarnUntilTs !== false) {
            $leechwarnUntilPretty = Format::prettyTimeWithLocale($leechwarnUntilTs - time());
        }
        if ($leechwarn) {
            AssetAppender::js(sprintf(<<<'JS'
document.getElementById('remove-leech-warn').addEventListener('click', function () {
    if (!window.confirm(%s)) {
        return
    }
    var params = {uid: this.getAttribute('data-uid')}
    nativePost('/web/users/leech-warn/remove', params, function (response) {
        console.log(response)
        if (response.ret == 0) {
            location.reload()
        } else {
            alert(response.msg)
        }
    })
})
JS, \json_encode(__('legacy/userdetails.sure_to_remove_leech_warn'))), 'footer', false);
        }

        $warnedByHtml = '';
        if (($user['timeswarned'] ?? 0) > 0 && $user['warnedby'] !== 'System') {
            $arr = $this->userDetailRepository->getWarnedBy((int) $user['warnedby']);
            if ($arr !== null) {
                $warnedByHtml = view('userdetails._warned_by', [
                    'userHtml' => UserDisplay::username($arr['id']),
                ])->render();
            }
        }

        $bonusTableHtml = '';
        if ($canManageBasic && $user['class'] < UserDisplay::currentClass()) {
            $bonusTable = Bonus::buildBonusTableForUser($user);
            $bonusTableHtml = $bonusTable['table'] ?? '';
        }

        $hrStatusHtml = '';
        if (($isOwner || $canViewHistory) && HitAndRun::getIsEnabled()) {
            $hrStatusHtml = $this->hitAndRunRepository->getStatusStats($id);
        }

        $ipHistoryCount = $canViewConfidential ? $this->userDetailRepository->getIplogCount($id) : 0;

        $claimAllSeedingConfirmation = Locale::trans('claim.claim_all_seeding_confirmation', [], null);
        $claimJs = '';
        if ($userModel instanceof User && $userModel->id === $currentUserId && $this->permissionChecker->hasRoleWorkSeeding($userModel->id)) {
            $claimJs = <<<JS
document.body.addEventListener("click", function (e) {
    if (!e.target || e.target.id !== "claim-all-seeding") return;
    layer.confirm("$claimAllSeedingConfirmation", {}, function () {
        nativePost('/plugin/claim_all_seeding', {"action": "claimAllSeeding"}, function (response) {
            if (response.ret == 0) {
                window.location.reload()
            } else {
                layer.alert(response.msg)
            }
        })
    })
})
JS;
        }

        AssetAppender::js(<<<JS
document.body.addEventListener("click", function (e) {
    if (!e.target || !e.target.matches || !e.target.matches(".nexus-pagination a")) return;
    e.preventDefault()
    var link = e.target
    var box = link.closest("[data-type]")
    var type = box.getAttribute("data-type");
    var url = link.getAttribute("href") + "&userid={$user['id']}&type=" + type;
    ajax.fetchText(url).then(function (result) {
        box.innerHTML = result
    })
})
$claimJs
JS, 'footer', false);

        $metas = $this->userRepository->listMetas($id);
        $userProps = [];
        $triggerId = '';

        $metaKey = UserMeta::META_KEY_CHANGE_USERNAME;
        if ($metas->has($metaKey)) {
            $triggerId = "consume-$metaKey";
            $changeUsernameCards = $metas->get($metaKey);
            $cardName = $changeUsernameCards->first()->meta_key_text;
            $userProps[] = SafeHtml::fromTrustedHtml(view('userdetails._prop', [
                'name' => $cardName,
                'detail' => $changeUsernameCards->count(),
                'consumeLabel' => (string) __('legacy/userdetails.consume'),
                'triggerId' => $isOwner ? $triggerId : '',
            ])->render());
            if ($isOwner) {
                $consumeLabel = __('legacy/userdetails.consume');
                AssetAppender::html(
                    view('userdetails._consume_template', [
                        'metaKey' => $metaKey,
                        'metaKeyLabel' => (string) __('legacy/userdetails.meta_key_change_username_username'),
                    ])->render(),
                    'footer',
                );
                AssetAppender::js(<<<JS
document.getElementById('{$triggerId}').addEventListener("click", function () {
    layer.open({
        type: 1,
        title: "{$consumeLabel} {$cardName}",
        content: document.getElementById('nx-layer-src-{$metaKey}').content,
        btn: ['OK'],
        btnAlign: 'c',
        yes: function () {
            var form = document.getElementById('layer-form-{$metaKey}')
            var params = serializeForm(form)
            nativePost('/web/benefits/consume', params, function (response) {
                console.log(response)
                if (response.ret != 0) {
                    layer.alert(response.msg)
                    return
                }
                window.location.reload()
            })
        }
    })
})
JS, 'footer', false);
            }
        }

        $metaKey = UserMeta::META_KEY_PERSONALIZED_USERNAME;
        if ($metas->has($metaKey)) {
            $rainbowID = $metas->get($metaKey)->first();
            if ($rainbowID->isValid()) {
                $userProps[] = SafeHtml::fromTrustedHtml(view('userdetails._prop', [
                    'name' => $rainbowID->metaKeyText,
                    'detail' => $rainbowID->getDeadlineText(),
                    'consumeLabel' => '',
                    'triggerId' => '',
                ])->render());
            }
        }

        return [
            'isOwner' => $isOwner,
            'currentUser' => $currentUser,
            'canViewConfidential' => $canViewConfidential,
            'canViewHistory' => $canViewHistory,
            'canManageBasic' => $canManageBasic,
            'canManageConfidential' => $canManageConfidential,
            'canDeleteUser' => $canDeleteUser,
            'canViewTorrentHistory' => $canViewTorrentHistory,
            'canViewInvite' => $canViewInvite,
            'currentClass' => $currentClass,
            'isFriend' => $isFriend,
            'currentUserBlockedTarget' => $currentUserBlockedTarget,
            'targetBlockedMe' => $targetBlockedMe,
            'currentUserIsFriendOfTarget' => $currentUserIsFriendOfTarget,
            'showPmButton' => $showPmButton,
            'countryFlagPic' => (string) ($countryRow['flagpic'] ?? ''),
            'countryName' => (string) ($countryRow['name'] ?? ''),
            'locationInfo' => $locationInfo,
            'locationInfoHtml' => $locationInfoHtml,
            'clientSelectHtml' => SafeHtml::fromTrustedHtml($clientSelectHtml),
            'trueTraffic' => $trueTraffic,
            'trueDownload' => $trueDownload,
            'trueUpload' => $trueUpload,
            'shareRatio' => $shareRatio,
            'trueRatio' => $trueRatio,
            'seedLeechRatio' => $seedLeechRatio,
            'joinWeeks' => $joinWeeks,
            'warned' => $warned,
            'leechwarn' => $leechwarn,
            'elapsedLastWarn' => $elapsedLastWarn,
            'warnedUntilPretty' => $warnedUntilPretty,
            'leechwarnUntilPretty' => $leechwarnUntilPretty,
            'migratedHelp' => SafeHtml::fromTrustedHtml($migratedHelp),
            'userManageSystemUrl' => $userManageSystemUrl,
            'usernameHtml' => SafeHtml::fromTrustedHtml($usernameHtml),
            'invitedByHtml' => SafeHtml::fromTrustedHtml($invitedByHtml),
            'avatarHtml' => SafeHtml::fromTrustedHtml($avatarHtml),
            'warnedByHtml' => SafeHtml::fromTrustedHtml($warnedByHtml),
            'bonusTableHtml' => SafeHtml::fromTrustedHtml($bonusTableHtml),
            'hrStatusHtml' => SafeHtml::fromTrustedHtml($hrStatusHtml),
            'ipHistoryCount' => $ipHistoryCount,
            'userProps' => $userProps,
            'triggerId' => $triggerId,
        ];
    }
}
