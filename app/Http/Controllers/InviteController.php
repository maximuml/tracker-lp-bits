<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Invite;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\InviteRepository;
use App\Repositories\UserRepository;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Locale;
use App\Support\Pagination;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Nexus\Nexus;

class InviteController extends LegacyController
{
    public function invite(Request $request): View|RedirectResponse|Response
    {
        $currentUser = app(CurrentUser::class)->get() ?? [];
        $currentUserId = (int) ($currentUser['id'] ?? 0);
        $id = $request->input('id') !== null ? (int) $request->input('id') : $currentUserId;
        $langInvite = (array) (app(Globals::class)->get('lang_invite') ?? []);

        if (! Validators::isId($id) || ($currentUserId !== $id && ! Permission::can(PermissionEnum::VIEW_INVITE))) {
            return $this->legacyAbortResponse($langInvite['std_sorry'] ?? 'Sorry', $langInvite['std_permission_denied'] ?? 'Permission denied.');
        }

        $user = User::query()->find($id);
        if (! $user) {
            return $this->legacyAbortResponse($langInvite['std_sorry'] ?? 'Sorry', 'Invalid id');
        }

        $type = htmlspecialchars((string) ($request->input('type') ?? ''));
        $menuSelected = (string) ($request->input('menu', 'invitee'));
        $enabled = (string) $request->input('enabled', '');
        $status = (string) $request->input('status', '');
        $sent = (string) $request->input('sent', '');
        $userRep = new UserRepository;
        $SITENAME = Setting::getSiteName();
        $invitesystem = SiteConfig::current()->main->inviteSystem() ? 'yes' : 'no';

        $data = [
            'id' => $id,
            'type' => $type,
            'menuSelected' => $menuSelected,
            'user' => $user->toArray(),
            'CURUSER' => $currentUser,
            'lang_invite' => $langInvite,
            'lang_functions' => (array) (app(Globals::class)->get('lang_functions') ?? []),
            'SITENAME' => $SITENAME,
            'userRep' => $userRep,
            'invitesystem' => $invitesystem,
            '__server_REQUEST_URI' => $request->getRequestUri(),
            'enabled' => $enabled,
            'status' => $status,
            'sent' => $sent,
            'UC_SYSOP' => (int) User::CLASS_SYSOP,
        ];

        if ($type === 'new') {
            if ($currentUserId !== $id) {
                return $this->legacyAbortResponse($langInvite['std_sorry'] ?? 'Sorry', $langInvite['std_permission_denied'] ?? 'Permission denied.');
            }

            try {
                $sendBtnText = $userRep->getInviteBtnText($currentUserId);
                $disabled = '';
            } catch (\Exception $exception) {
                return $this->legacyAbortResponse(
                    $langInvite['std_sorry'] ?? 'Sorry',
                    $exception->getMessage().'  <a class=altlink href=invite.php?id='.htmlspecialchars((string) $currentUserId).'>'.$langInvite['here_to_go_back'].'</a>'
                );
            }

            $inv = $user->toArray();
            $temporaryInvites = Invite::query()->where('inviter', $currentUserId)
                ->where('invitee', '')
                ->where('expired_at', '>', now())
                ->orderBy('expired_at', 'asc')
                ->get();

            $inviteSelectOptions = '';
            if ((int) ($inv['invites'] ?? 0) > 0) {
                $inviteSelectOptions = '<option value="permanent">'.$langInvite['text_permanent'].'</option>';
            }
            foreach ($temporaryInvites as $tmp) {
                $inviteSelectOptions .= sprintf('<option value="%s">%s (%s: %s)</option>', e($tmp->hash), e($tmp->hash), $langInvite['text_expired_at'], $tmp->expired_at);
            }

            $invitation_body = sprintf($langInvite['text_invitation_body'], $SITENAME).$currentUser['username'];
            $preUsernameTr = '';
            if (SiteConfig::current()->system->isInvitePreEmailAndUsername()) {
                $preUsernameTr = '<tr><td class="rowhead nowrap" valign="top" align="right">'.Locale::trans('invite.pre_register_username', [], null).'</td><td align=left><input type=text size=40 name=pre_register_username><br /><font align=left class=small>'.Locale::trans('invite.pre_register_username_help', [], null).'</font></td></tr>';
            }
            $_s = ((int) ($inv['invites'] ?? 0) !== 1) ? ($langInvite['text_s'] ?? 's') : '';

            $data = array_merge($data, [
                'inv' => $inv,
                'sendBtnText' => $sendBtnText,
                'disabled' => $disabled,
                'temporaryInvites' => $temporaryInvites,
                'inviteSelectOptions' => $inviteSelectOptions,
                'invitation_body' => $invitation_body,
                'preUsernameTr' => $preUsernameTr,
                '_s' => $_s,
            ]);
        } else {
            // invitee / sent / tmp modes — fetch data in the controller
            $data = array_merge($data, $this->inviteMenuData($id, $menuSelected, $currentUserId, $langInvite, $userRep));

            if ($menuSelected === 'invitee') {
                $data = array_merge($data, $this->inviteeData($id, $enabled, $status, $currentUserId, $langInvite, $request->getRequestUri()));
            } elseif (in_array($menuSelected, ['sent', 'tmp'], true)) {
                $data = array_merge($data, $this->sentTmpData($id, $menuSelected, $langInvite, $langFunctions = (array) (app(Globals::class)->get('lang_functions') ?? [])));
            }
        }

        return $this->legacyPage($request, 'invite', true, $data);
    }

    /**
     * Build the invite menu button data (send button text / disabled state).
     *
     * @param  array<string, mixed>  $langInvite
     * @return array<string, mixed>
     */
    private function inviteMenuData(int $id, string $menuSelected, int $currentUserId, array $langInvite, UserRepository $userRep): array
    {
        $sendBtnText = '';
        $sendBtnDisabled = '';
        if ($currentUserId === $id) {
            try {
                $sendBtnText = $userRep->getInviteBtnText($currentUserId);
            } catch (\Exception $exception) {
                $sendBtnText = $exception->getMessage();
                $sendBtnDisabled = ' disabled';
            }
        }

        return [
            'sendBtnText' => $sendBtnText,
            'sendBtnDisabled' => $sendBtnDisabled,
        ];
    }

    /**
     * Fetch invitee list data for the "invitee" menu tab.
     *
     * @param  array<string, mixed>  $langInvite
     * @return array<string, mixed>
     */
    private function inviteeData(int $id, string $enabled, string $status, int $currentUserId, array $langInvite, string $requestUri): array
    {
        $filters = ['status' => $status, 'enabled' => $enabled];
        $number = InviteRepository::countInvitees($id, $filters);
        $pageSize = 50;

        $enabledOptions = '';
        foreach (['yes', 'no'] as $item) {
            $enabledOptions .= sprintf('<option value="%s"%s>%s</option>', $item, ($enabled !== '' && $enabled == $item) ? ' selected' : '', strtoupper($item));
        }
        $statusOptions = '';
        foreach (['pending' => $langInvite['text_pending'] ?? 'Pending', 'confirmed' => $langInvite['text_confirmed'] ?? 'Confirmed'] as $name => $text) {
            $statusOptions .= sprintf('<option value="%s"%s>%s</option>', $name, ($status !== '' && $status == $name) ? ' selected' : '', $text);
        }

        $inviteRows = [];
        $pagertop = '';
        $pagerbottom = '';
        $haremAdditionFactor = SiteConfig::current()->bonus->haremAddition();
        $pendingCount = 0;

        if ($number > 0) {
            [$pagertop, $pagerbottom, , $offset] = Pagination::pager($pageSize, $number, "?id=$id&menu=invitee&");
            $inviteRows = InviteRepository::getInvitees($id, $filters, (int) $offset, $pageSize);
        }

        if ($currentUserId === $id || UserDisplay::currentClass() >= (int) User::CLASS_SYSOP) {
            $pendingCount = InviteRepository::countPendingInvitees($currentUserId);
        }

        // Register reset JS
        $resetJs = <<<'JS'
jQuery("#reset").on('click', function () {
    jQuery("select[name=status]").val('')
    jQuery("select[name=enabled]").val('')
})
JS;
        Nexus::js($resetJs, 'footer', false);

        return [
            'inviteeCount' => $number,
            'inviteeRows' => $inviteRows,
            'inviteePagertop' => $pagertop,
            'inviteePagerbottom' => $pagerbottom,
            'inviteeEnabledOptions' => $enabledOptions,
            'inviteeStatusOptions' => $statusOptions,
            'haremAdditionFactor' => $haremAdditionFactor,
            'pendingCount' => $pendingCount,
            'textSelectOnePlease' => Locale::trans('nexus.select_one_please', [], null),
            'resetText' => Locale::trans('label.reset', [], null),
            'submitText' => Locale::trans('label.submit', [], null),
        ];
    }

    /**
     * Fetch sent/tmp invite data.
     *
     * @param  array<string, mixed>  $langInvite
     * @param  array<string, mixed>  $langFunctions
     * @return array<string, mixed>
     */
    private function sentTmpData(int $id, string $menuSelected, array $langInvite, array $langFunctions): array
    {
        $number = InviteRepository::countInvites($id, $menuSelected);
        $pageSize = 50;
        $inviteRows = [];
        $pagertop = '';
        $pagerbottom = '';

        if ($number > 0) {
            [$pagertop, $pagerbottom, , $offset] = Pagination::pager($pageSize, $number, "?id=$id&menu=$menuSelected&");
            $inviteRows = InviteRepository::getInvites($id, $menuSelected, (int) $offset, $pageSize);
        }

        return [
            'sentTmpCount' => $number,
            'sentTmpRows' => $inviteRows,
            'sentTmpPagertop' => $pagertop,
            'sentTmpPagerbottom' => $pagerbottom,
        ];
    }
}
