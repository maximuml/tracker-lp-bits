<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\HitAndRun;
use App\Models\User;
use App\Models\UserMeta;
use App\Repositories\HitAndRunRepository;
use App\Repositories\UserDetailRepository;
use App\Repositories\UserRepository;
use App\Support\Bonus;
use App\Support\Country;
use App\Support\CurrentUser;
use App\Support\Env;
use App\Support\Globals;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Network;
use App\Support\Permissions;
use App\Support\Strings;
use App\Support\Url;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserDetailController extends Controller
{
    private HitAndRunRepository $hitAndRunRepository;

    private UserRepository $userRepository;

    public function __construct(HitAndRunRepository $hitAndRunRepository, UserRepository $userRepository)
    {
        $this->hitAndRunRepository = $hitAndRunRepository;
        $this->userRepository = $userRepository;
    }

    public function show(Request $request): View|RedirectResponse
    {
        $id = (int) $request->input('id', 0);

        if ($id <= 0) {
            $currentUser = app(CurrentUser::class)->get();
            $id = (int) ($currentUser['id'] ?? 0);
            if ($id <= 0) {
                abort(404);
            }
        }

        if (app(CurrentUser::class)->get() === null) {
            return redirect('/userdetails.php?'.$request->getQueryString());
        }

        $user = app(UserDetailRepository::class)->getUser($id);
        /** @var array<string, string> $lang */
        $lang = (array) app(Globals::class)->get('lang_userdetails', []);

        if ($user === null) {
            LegacyResponse::abort(
                $lang['std_error'] ?? 'Error',
                $lang['std_no_such_user'] ?? 'No user with this ID!'
            );

            return redirect('/userdetails.php');
        }

        if (($user['status'] ?? '') === 'pending') {
            LegacyResponse::abort(
                $lang['std_sorry'] ?? 'Sorry',
                $lang['std_user_not_confirmed'] ?? 'This user is not confirmed.'
            );
        }

        $userModel = app(UserDetailRepository::class)->getUserWithMedals($id);
        $temporaryInviteCount = $userModel instanceof User ? app(UserDetailRepository::class)->getTemporaryInviteCount($userModel) : 0;

        return view('user.details', array_merge([
            'id' => $id,
            'user' => $user,
            'lang' => $lang,
            'userModel' => $userModel,
            'torrentcomments' => app(UserDetailRepository::class)->getCommentCount($id),
            'forumposts' => app(UserDetailRepository::class)->getPostCount($id),
            'temporaryInviteCount' => $temporaryInviteCount,
            'modcomment' => app(UserDetailRepository::class)->getModComment($id),
            'bonuscomment' => app(UserDetailRepository::class)->getBonusComment($id),
        ], $this->buildDetailsViewData($id, $user, $userModel)));
    }

    /**
     * @param  array<int|string, mixed>  $user
     * @return array<string, mixed>
     */
    private function buildDetailsViewData(int $id, array $user, ?User $userModel): array
    {
        $currentUser = app(CurrentUser::class)->get() ?? [];
        $currentUserId = (int) ($currentUser['id'] ?? 0);
        $isOwner = $currentUserId === $id;

        $canViewConfidential = Permission::can(PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO);
        $canViewHistory = Permission::can(PermissionEnum::VIEW_USER_HISTORY);
        $canManageBasic = Permission::can(PermissionEnum::MANAGE_USER_BASIC_INFO);
        $canDeleteUser = Permission::can(PermissionEnum::USER_DELETE);
        $staffMember = Permission::can(PermissionEnum::STAFF_MEMBER);

        $isFriend = $currentUserId > 0 ? app(UserDetailRepository::class)->isFriend($currentUserId, $id) : false;
        $currentUserBlockedTarget = $currentUserId > 0 ? app(UserDetailRepository::class)->isBlocked($currentUserId, $id) : false;
        $targetBlockedMe = $currentUserId > 0 ? app(UserDetailRepository::class)->isBlocked($id, $currentUserId) : false;
        $currentUserIsFriendOfTarget = $currentUserId > 0 ? app(UserDetailRepository::class)->isFriend($id, $currentUserId) : false;

        $showPmButton = false;
        if ($currentUserId !== $id) {
            if ($staffMember) {
                $showPmButton = true;
            } elseif ($user['acceptpms'] === 'yes') {
                $showPmButton = ! $targetBlockedMe;
            } elseif ($user['acceptpms'] === 'friends') {
                $showPmButton = $currentUserIsFriendOfTarget;
            }
        }

        $countryRow = Country::rowWithContext($user['country']);
        $countryHtml = '<img src="pic/flag/'.htmlspecialchars((string) ($countryRow['flagpic'] ?? '')).'" alt="'.htmlspecialchars((string) ($countryRow['name'] ?? '')).'" style="margin-left: 8pt" />';

        $locationInfo = [null, null];
        if (app(Globals::class)->get('enablelocation_tweak', '') === 'yes' && ! empty($user['ip'])) {
            $locationInfo = Network::ipLocationWithContext($user['ip']);
        }

        $peerRows = app(UserDetailRepository::class)->getPeers($id);
        $clientSelectHtml = '';
        if (! empty($peerRows)) {
            $clientSelectHtml .= "<table border='1' cellspacing='0' cellpadding='5'><tr><td class='colhead'>Agent</td><td class='colhead'>IPV4</td><td class='colhead'>IPV6</td><td class='colhead'>Port</td></tr>";
            foreach ($peerRows as $arr) {
                $clientSelectHtml .= '<tr>';
                $clientSelectHtml .= sprintf('<td>%s</td>', Strings::userAgentClient($arr['agent']));
                if ($canViewConfidential || $isOwner) {
                    $v4 = $isOwner ? Strings::hidden($arr['ipv4']) : $arr['ipv4'];
                    $v6 = $isOwner ? Strings::hidden($arr['ipv6']) : $arr['ipv6'];
                    $clientSelectHtml .= sprintf(
                        '<td>%s</td><td>%s</td><td>%s</td>',
                        $v4,
                        $v6,
                        $arr['port']
                    );
                } else {
                    $clientSelectHtml .= sprintf('<td>%s</td><td>%s</td><td>%s</td>', '---', '---', '---');
                }
                $clientSelectHtml .= '</tr>';
            }
            $clientSelectHtml .= '</table>';
        }

        $trueTraffic = app(UserDetailRepository::class)->getTrueTraffic($id);

        $userManageSystemUrl = sprintf('%s/%s/user/users/%s', Url::schemeAndHost(false), Env::get('FILAMENT_PATH', 'nexusphp'), $user['id']);

        $langDetails = (array) app(Globals::class)->get('lang_userdetails', []);

        $usernameHtml = UserDisplay::username($user['id'], true, false);
        $invitedByHtml = $user['invited_by'] > 0 ? UserDisplay::username($user['invited_by']) : '';
        $avatarHtml = $user['avatar'] ? UserDisplay::avatarImageWithContext(htmlspecialchars(trim((string) $user['avatar']))) : '';

        $warnedByHtml = '';
        if (($user['timeswarned'] ?? 0) > 0 && $user['warnedby'] !== 'System') {
            $arr = app(UserDetailRepository::class)->getWarnedBy((int) $user['warnedby']);
            if ($arr !== null) {
                $warnedByHtml = '<br />['.$langDetails['text_by'].'<u>'.UserDisplay::username($arr['id']).'</u></a>]';
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

        $ipHistoryCount = $canViewConfidential ? app(UserDetailRepository::class)->getIplogCount($id) : 0;

        $claimAllSeedingConfirmation = Locale::trans('claim.claim_all_seeding_confirmation', [], null);
        $claimJs = '';
        if ($userModel instanceof User && $userModel->id === $currentUserId && Permissions::hasRoleWorkSeeding($userModel->id)) {
            $claimJs = <<<JS
jQuery("body").on("click", "#claim-all-seeding", function (e) {
    layer.confirm("$claimAllSeedingConfirmation", {}, function () {
        jQuery.post('/plugin/claim_all_seeding', {"action": "claimAllSeeding"}, function (response) {
            if (response.ret == 0) {
                window.location.reload()
            } else {
                layer.alert(response.msg)
            }
        }, 'json')
    })
})
JS;
        }

        $metas = $this->userRepository->listMetas($id);
        $userPropsHtml = '';
        $consumeChangeUsernameJs = '';
        $consumeChangeUsernameForm = '';
        $triggerId = '';
        $props = [];

        $metaKey = UserMeta::META_KEY_CHANGE_USERNAME;
        if ($metas->has($metaKey)) {
            $triggerId = "consume-$metaKey";
            $changeUsernameCards = $metas->get($metaKey);
            $cardName = $changeUsernameCards->first()->meta_key_text;
            $useInput = '';
            if ($isOwner) {
                $useInput = sprintf('<input type="button" value="%s" id="%s">', $langDetails['consume'] ?? '', $triggerId);
            }
            $props = [sprintf(
                '<div><strong>[%s]</strong>(%s)</div>%s',
                $cardName, $changeUsernameCards->count(), $useInput
            )];
            if ($isOwner) {
                $consumeChangeUsernameForm = <<<HTML
<div class="layer-form">
<form id="layer-form-$metaKey">
    <input type="hidden" name="params[meta_key]" value="$metaKey">
    <div class="form-control-row">
        <div class="label">{$langDetails['meta_key_change_username_username']}</div>
        <div class="field"><input type="text" name="params[username]"></div>
    </div>
</form>
</div>
HTML;
                $consumeChangeUsernameJs = <<<JS
jQuery('#{$triggerId}').on("click", function () {
    layer.open({
        type: 1,
        title: "{$langDetails['consume']} {$cardName}",
        content: `$consumeChangeUsernameForm`,
        btn: ['OK'],
        btnAlign: 'c',
        yes: function () {
            let params = jQuery('#layer-form-{$metaKey}').serialize()
            jQuery.post('ajax.php', params + "&action=consumeBenefit", function (response) {
                console.log(response)
                if (response.ret != 0) {
                    layer.alert(response.msg)
                    return
                }
                window.location.reload()
            }, 'json')
        }
    })
})
JS;
            }
        }

        $metaKey = UserMeta::META_KEY_PERSONALIZED_USERNAME;
        if ($metas->has($metaKey)) {
            $rainbowID = $metas->get($metaKey)->first();
            if ($rainbowID->isValid()) {
                $props[] = sprintf(
                    '<div><strong>[%s]</strong>(%s)</div>',
                    $rainbowID->metaKeyText, $rainbowID->getDeadlineText()
                );
            }
        }

        if (! empty($props)) {
            $userPropsHtml = sprintf('<div style="display: flex;align-items: center">%s</div>', implode('&nbsp;|&nbsp;', $props));
        }

        return [
            'isOwner' => $isOwner,
            'currentUser' => $currentUser,
            'canViewConfidential' => $canViewConfidential,
            'canViewHistory' => $canViewHistory,
            'canManageBasic' => $canManageBasic,
            'canDeleteUser' => $canDeleteUser,
            'isFriend' => $isFriend,
            'currentUserBlockedTarget' => $currentUserBlockedTarget,
            'targetBlockedMe' => $targetBlockedMe,
            'currentUserIsFriendOfTarget' => $currentUserIsFriendOfTarget,
            'showPmButton' => $showPmButton,
            'countryHtml' => $countryHtml,
            'locationInfo' => $locationInfo,
            'clientSelectHtml' => $clientSelectHtml,
            'trueTraffic' => $trueTraffic,
            'userManageSystemUrl' => $userManageSystemUrl,
            'usernameHtml' => $usernameHtml,
            'invitedByHtml' => $invitedByHtml,
            'avatarHtml' => $avatarHtml,
            'warnedByHtml' => $warnedByHtml,
            'bonusTableHtml' => $bonusTableHtml,
            'hrStatusHtml' => $hrStatusHtml,
            'ipHistoryCount' => $ipHistoryCount,
            'claimJs' => $claimJs,
            'claimAllSeedingConfirmation' => $claimAllSeedingConfirmation,
            'userPropsHtml' => $userPropsHtml,
            'consumeChangeUsernameJs' => $consumeChangeUsernameJs,
            'consumeChangeUsernameForm' => $consumeChangeUsernameForm,
            'triggerId' => $triggerId,
        ];
    }
}
