<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Contracts\Repositories\UserModerationRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\InviteValid;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserClass as UserClassEnum;
use App\Models\Invite;
use App\Models\Setting;
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
    public function __construct(private readonly UserRepositoryInterface $userRepository,
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

        $user = $this->userRepository->findById($id);
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
                    view('invite._back_message', [
                        'message' => $exception->getMessage(),
                        'backUrl' => '/web/invite?id='.(string) $currentUserId,
                        'backText' => (string) __('legacy/invite.std_here'),
                        'backSuffix' => (string) __('legacy/invite.std_to_go_back'),
                    ])->render(),
                    false
                );
            }

            $inv = $user->toArray();
            $temporaryInvites = $this->inviteRepository->listPendingForInviter($currentUserId);

            $inviteOptions = [];
            if ((int) ($inv['invites'] ?? 0) > 0) {
                $inviteOptions[] = ['value' => 'permanent', 'text' => (string) __('legacy/invite.text_permanent')];
            }
            foreach ($temporaryInvites as $tmp) {
                $inviteOptions[] = [
                    'value' => (string) $tmp->hash,
                    'text' => sprintf('%s (%s: %s)', $tmp->hash, __('legacy/invite.text_expired_at'), $tmp->expired_at),
                ];
            }

            $invitation_body = sprintf(__('legacy/invite.text_invitation_body'), $SITENAME).$currentUser['username'];
            $showPreUsername = SiteConfig::current()->system->isInvitePreEmailAndUsername();
            $_s = ((int) ($inv['invites'] ?? 0) !== 1) ? (__('legacy/invite.text_s')) : '';

            $data = array_merge($data, [
                'inv' => $inv,
                'sendBtnText' => $sendBtnText,
                'disabled' => $disabled,
                'temporaryInvites' => $temporaryInvites,
                'inviteOptions' => $inviteOptions,
                'invitation_body' => $invitation_body,
                'showPreUsername' => $showPreUsername,
                'preUsernameLabel' => $showPreUsername ? Locale::trans('invite.pre_register_username', [], null) : '',
                'preUsernameHelp' => $showPreUsername ? Locale::trans('invite.pre_register_username_help', [], null) : '',
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

        $enabledOptions = [];
        foreach (['yes', 'no'] as $item) {
            $enabledOptions[] = ['value' => $item, 'text' => strtoupper($item), 'selected' => $enabled !== '' && $enabled == $item];
        }
        $statusOptions = [];
        foreach (['pending' => __('legacy/invite.text_pending'), 'confirmed' => __('legacy/invite.text_confirmed')] as $name => $text) {
            $statusOptions[] = ['value' => $name, 'text' => (string) $text, 'selected' => $status !== '' && $status == $name];
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

        UserDisplay::preload(array_values(array_map(fn ($r) => (int) ($r['id'] ?? 0), $inviteRows)));
        foreach ($inviteRows as &$row) {
            $row['usernameHtml'] = UserDisplay::username((int) $row['id']);
            if ((float) $row['downloaded'] > 0) {
                $ratio = number_format($row['uploaded'] / $row['downloaded'], 3);
                $row['ratioText'] = $ratio;
                $row['ratioClass'] = Ratio::colorClass($ratio);
            } else {
                $row['ratioText'] = $row['uploaded'] > 0 ? 'Inf.' : '---';
                $row['ratioClass'] = '';
            }
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
            'inviteeEnabledOptions' => $enabledOptions,
            'inviteeStatusOptions' => $statusOptions,
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
            $row['hashValid'] = (int) $row['valid'] === InviteValid::YES->value;
            $row['validText'] = Invite::$validInfo[$row['valid']]['text'] ?? '';
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
