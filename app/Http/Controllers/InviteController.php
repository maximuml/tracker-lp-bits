<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Invite;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\LegacyViewRepository;
use App\Repositories\UserRepository;
use App\Support\Config\SiteConfig;
use App\Support\Locale;
use App\Support\SupportContext;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class InviteController extends LegacyController
{
    public function invite(Request $request): View|RedirectResponse|Response
    {
        $currentUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($currentUser['id'] ?? 0);
        $id = $request->input('id') !== null ? (int) $request->input('id') : $currentUserId;
        $langInvite = (array) (SupportContext::getGlobal('lang_invite') ?? []);

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

        // Globals required by the legacy inviteMenu helper.
        SupportContext::setGlobal('id', $id);
        SupportContext::setGlobal('lang_invite', $langInvite);
        SupportContext::setGlobal('userRep', $userRep);
        SupportContext::setGlobal('invitesystem', $invitesystem);

        $data = [
            'id' => $id,
            'type' => $type,
            'menuSelected' => $menuSelected,
            'user' => $user->toArray(),
            'CURUSER' => $currentUser,
            'lang_invite' => $langInvite,
            'lang_functions' => (array) (SupportContext::getGlobal('lang_functions') ?? []),
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
        }

        $content = LegacyViewRepository::render('invite', $data);

        return response($content, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
