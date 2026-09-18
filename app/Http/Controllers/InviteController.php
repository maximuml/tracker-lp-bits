<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Contracts\Repositories\UserModerationRepositoryInterface;
use App\Enums\InviteValid;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserClass as UserClassEnum;
use App\Models\Invite;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\InviteRepository;
use App\Support\AssetAppender;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Html\SafeHtml;
use App\Support\Locale;
use App\Support\Pagination;
use App\Support\Ratio;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class InviteController extends LegacyController
{
    public function __construct(
        private readonly UserModerationRepositoryInterface $userModerationRepository,
        private readonly CurrentUser $currentUser,
        private readonly InviteRepository $inviteRepository,
    ) {}

    public function inviteAction(Request $request): View|RedirectResponse|Response
    {
        return $this->invite($request);
    }

    public function invite(Request $request): View|RedirectResponse|Response
    {
        $currentUser = $this->currentUser->get() ?? [];
        $currentUserId = (int) ($currentUser['id'] ?? 0);
        $id = $request->input('id') !== null ? (int) $request->input('id') : $currentUserId;

        if (! Validators::isId($id) || ($currentUserId !== $id && ! Permission::can(PermissionEnum::VIEW_INVITE))) {
            return $this->legacyAbortResponse(__('legacy/invite.std_sorry'), __('legacy/invite.std_permission_denied'));
        }

        $user = User::query()->find($id);
        if (! $user) {
            return $this->legacyAbortResponse(__('legacy/invite.std_sorry'), 'Invalid id');
        }

        $type = htmlspecialchars((string) ($request->input('type') ?? ''));
        $menuSelected = (string) ($request->input('menu', 'invitee'));
        $enabled = (string) $request->input('enabled', '');
        $status = (string) $request->input('status', '');
        $sent = (string) $request->input('sent', '');
        $SITENAME = Setting::getSiteName();
        $invitesystem = SiteConfig::current()->main->inviteSystem() ? 'yes' : 'no';

        $data = [
            'id' => $id,
            'type' => $type,
            'menuSelected' => $menuSelected,
            'user' => $user->toArray(),
            'CURUSER' => $currentUser,
            'SITENAME' => $SITENAME,
            'invitesystem' => $invitesystem,
            '__server_REQUEST_URI' => $request->getRequestUri(),
            'enabled' => $enabled,
            'status' => $status,
            'sent' => $sent,
            'UC_SYSOP' => (int) UserClassEnum::SYSOP->value,
        ];

        if ($type === 'new') {
            if ($currentUserId !== $id) {
                return $this->legacyAbortResponse(__('legacy/invite.std_sorry'), __('legacy/invite.std_permission_denied'));
            }

            try {
                $sendBtnText = $this->userModerationRepository->getInviteBtnText($currentUserId);
                $disabled = '';
            } catch (\Exception $exception) {
                return $this->legacyAbortResponse(
                    __('legacy/invite.std_sorry'),
                    $exception->getMessage().'  <a class=altlink href=invite.php?id='.htmlspecialchars((string) $currentUserId).'>'.__('legacy/invite.here_to_go_back').'</a>'
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
                $inviteSelectOptions = '<option value="permanent">'.__('legacy/invite.text_permanent').'</option>';
            }
            foreach ($temporaryInvites as $tmp) {
                $inviteSelectOptions .= sprintf('<option value="%s">%s (%s: %s)</option>', e($tmp->hash), e($tmp->hash), __('legacy/invite.text_expired_at'), $tmp->expired_at);
            }

            $invitation_body = sprintf(__('legacy/invite.text_invitation_body'), $SITENAME).$currentUser['username'];
            $preUsernameTr = '';
            if (SiteConfig::current()->system->isInvitePreEmailAndUsername()) {
                $preUsernameTr = '<div class="nx-fhead nx-nowrap">'.Locale::trans('invite.pre_register_username', [], null).'</div><div class="nx-fcell"><input type=text size=40 name=pre_register_username><br /><font align=left class=small>'.Locale::trans('invite.pre_register_username_help', [], null).'</font></div>';
            }
            $_s = ((int) ($inv['invites'] ?? 0) !== 1) ? (__('legacy/invite.text_s')) : '';

            $data = array_merge($data, [
                'inv' => $inv,
                'sendBtnText' => $sendBtnText,
                'disabled' => $disabled,
                'temporaryInvites' => $temporaryInvites,
                'inviteSelectOptions' => SafeHtml::fromTrustedHtml($inviteSelectOptions),
                'invitation_body' => $invitation_body,
                'preUsernameTr' => SafeHtml::fromTrustedHtml($preUsernameTr),
                '_s' => $_s,
            ]);
        } else {
            // invitee / sent / tmp modes — fetch data in the controller
            $data = array_merge($data, $this->inviteMenuData($id, $menuSelected, $currentUserId));

            if ($menuSelected === 'invitee') {
                $data = array_merge($data, $this->inviteeData($id, $enabled, $status, $currentUserId, $request->getRequestUri()));
            } elseif (in_array($menuSelected, ['sent', 'tmp'], true)) {
                $data = array_merge($data, $this->sentTmpData($id, $menuSelected));
            }
        }

        return $this->legacyPage($request, 'invite', true, $data);
    }

    /**
     * Build the invite menu button data (send button text / disabled state).
     *
     * @return array<string, mixed>
     */
    private function inviteMenuData(int $id, string $menuSelected, int $currentUserId): array
    {
        $sendBtnText = '';
        $sendBtnDisabled = '';
        if ($currentUserId === $id) {
            try {
                $sendBtnText = $this->userModerationRepository->getInviteBtnText($currentUserId);
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
     * @return array<string, mixed>
     */
    private function inviteeData(int $id, string $enabled, string $status, int $currentUserId, string $requestUri): array
    {
        $filters = ['status' => $status, 'enabled' => $enabled];
        $number = $this->inviteRepository->countInvitees($id, $filters);
        $pageSize = 50;

        $enabledOptions = '';
        foreach (['yes', 'no'] as $item) {
            $enabledOptions .= sprintf('<option value="%s"%s>%s</option>', $item, ($enabled !== '' && $enabled == $item) ? ' selected' : '', strtoupper($item));
        }
        $statusOptions = '';
        foreach (['pending' => __('legacy/invite.text_pending'), 'confirmed' => __('legacy/invite.text_confirmed')] as $name => $text) {
            $statusOptions .= sprintf('<option value="%s"%s>%s</option>', $name, ($status !== '' && $status == $name) ? ' selected' : '', $text);
        }

        $inviteRows = [];
        $pagertop = SafeHtml::fromTrustedHtml('');
        $pagerbottom = SafeHtml::fromTrustedHtml('');
        $haremAdditionFactor = SiteConfig::current()->bonus->haremAddition();
        $pendingCount = 0;

        if ($number > 0) {
            [$pagertop, $pagerbottom, , $offset] = Pagination::pager($pageSize, $number, "?id=$id&menu=invitee&");
            $inviteRows = $this->inviteRepository->getInvitees($id, $filters, (int) $offset, $pageSize);
        }

        $canConfirm = $currentUserId === $id || UserDisplay::currentClass() >= (int) UserClassEnum::SYSOP->value;
        if ($canConfirm) {
            $pendingCount = $this->inviteRepository->countPendingInvitees($currentUserId);
        }

        foreach ($inviteRows as &$row) {
            $row['usernameHtml'] = UserDisplay::username((int) $row['id']);
            if ((float) $row['downloaded'] > 0) {
                $ratio = number_format($row['uploaded'] / $row['downloaded'], 3);
                $row['ratioHtml'] = SafeHtml::fromTrustedHtml('<font color='.Ratio::color($ratio).">$ratio</font>");
            } else {
                $row['ratioHtml'] = SafeHtml::fromTrustedHtml($row['uploaded'] > 0 ? 'Inf.' : '---');
            }
            $row['statusHtml'] = SafeHtml::fromTrustedHtml($row['status'] === 'confirmed'
                ? '<a href=userdetails.php?id='.(int) $row['id'].'><font color=#1f7309>'.e(__('legacy/invite.text_confirmed')).'</font></a>'
                : '<a href=checkuser.php?id='.(int) $row['id'].'><font color=#ca0226>'.e(__('legacy/invite.text_pending')).'</font></a>');
        }
        unset($row);

        // Register reset JS
        $resetJs = <<<'JS'
document.getElementById("reset").addEventListener('click', function () {
    var status = document.querySelector("select[name=status]")
    var enabled = document.querySelector("select[name=enabled]")
    if (status) status.value = ''
    if (enabled) enabled.value = ''
})
JS;
        AssetAppender::js($resetJs, 'footer', false);

        return [
            'inviteeCount' => $number,
            'inviteeRows' => $inviteRows,
            'inviteePagertop' => $pagertop,
            'inviteePagerbottom' => $pagerbottom,
            'inviteeEnabledOptions' => SafeHtml::fromTrustedHtml($enabledOptions),
            'inviteeStatusOptions' => SafeHtml::fromTrustedHtml($statusOptions),
            'haremAdditionFactor' => $haremAdditionFactor,
            'pendingCount' => $pendingCount,
            'canConfirm' => $canConfirm,
            'inviteeColSpan' => $haremAdditionFactor > 0 ? 13 : 12,
            'textSelectOnePlease' => Locale::trans('nexus.select_one_please', [], null),
            'resetText' => Locale::trans('label.reset', [], null),
            'submitText' => Locale::trans('label.submit', [], null),
        ];
    }

    /**
     * Fetch sent/tmp invite data.
     *
     * @return array<string, mixed>
     */
    private function sentTmpData(int $id, string $menuSelected): array
    {
        $number = $this->inviteRepository->countInvites($id, $menuSelected);
        $pageSize = 50;
        $inviteRows = [];
        $pagertop = SafeHtml::fromTrustedHtml('');
        $pagerbottom = SafeHtml::fromTrustedHtml('');

        if ($number > 0) {
            [$pagertop, $pagerbottom, , $offset] = Pagination::pager($pageSize, $number, "?id=$id&menu=$menuSelected&");
            $inviteRows = $this->inviteRepository->getInvites($id, $menuSelected, (int) $offset, $pageSize);
        }

        foreach ($inviteRows as &$row) {
            $isHashValid = (int) $row['valid'] === InviteValid::YES->value;
            $row['registerLink'] = $isHashValid
                ? sprintf('&nbsp;<a href="signup.php?type=invite&invitenumber=%s" title="%s" target="_blank"><small>[%s]</small></a>', e($row['hash']), e(__('legacy/invite.signup_link_help')), e(__('legacy/invite.signup_link')))
                : '';
            $row['validText'] = Invite::$validInfo[$row['valid']]['text'] ?? '';
            $row['inviteeUserHtml'] = ! $isHashValid
                ? '<a href=userdetails.php?id='.(int) $row['invitee_register_uid'].'><font color=#1f7309>'.e($row['invitee_register_username']).'</font></a>'
                : '';
        }
        unset($row);

        return [
            'sentTmpCount' => $number,
            'sentTmpRows' => $inviteRows,
            'sentTmpPagertop' => $pagertop,
            'sentTmpPagerbottom' => $pagerbottom,
        ];
    }
}
