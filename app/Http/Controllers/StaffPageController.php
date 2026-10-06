<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\StaffDirectoryRepository;
use App\Services\PermissionChecker;
use App\Support\Country;
use App\Support\CurrentUser;
use App\Support\UserClass;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class StaffPageController extends LegacyController
{
    public function __construct(private readonly PermissionChecker $permissionChecker,
        private readonly CurrentUser $currentUser,
        private readonly StaffDirectoryRepository $staffDirectoryRepository,
    ) {}

    public function staff(Request $request): View|RedirectResponse|Response
    {
        $curUser = $this->currentUser->get() ?? [];
        $currentUserId = (int) ($this->currentUser->id());

        if (! $this->permissionChecker->userCan(PermissionEnum::STAFF_MEMBER->value, false, $currentUserId)) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $secs = 900;
        $dt = time() - $secs;

        $buildUserRow = function (array $arr, string ...$extraKeys) use ($dt): array {
            $countryrow = Country::rowWithContext($arr['country'] ?? 0) ?? ['flagpic' => '', 'name' => ''];

            return [
                'id' => (int) $arr['id'],
                'username_html' => UserDisplay::username((int) $arr['id']),
                'flag_pic' => (string) $countryrow['flagpic'],
                'flag_name' => (string) $countryrow['name'],
                'is_online' => strtotime((string) $arr['last_access']) > $dt,
                'extras' => array_map(fn (string $k): string => (string) ($arr[$k] ?? ''), $extraKeys),
            ];
        };

        $supportRows = $this->staffDirectoryRepository->listSupportStaff();

        $pickerRows = $this->staffDirectoryRepository->listPickers();

        $forumMods = $this->staffDirectoryRepository->listForumModerators();

        // Preload user display rows to avoid N+1 in buildUserRow
        $allUserIds = $supportRows->pluck('id')
            ->merge($pickerRows->pluck('id'))
            ->merge($forumMods->pluck('userid'))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        UserDisplay::preload($allUserIds);

        $supportRows = $supportRows->map(fn ($r) => $buildUserRow((array) $r->getAttributes(), 'supportlang', 'supportfor'))->all();
        $pickerRows = $pickerRows->map(fn ($r) => $buildUserRow((array) $r->getAttributes(), 'pickfor'))->all();

        $modUserIds = $forumMods->map(fn ($m) => (int) ((array) $m)['userid'])->all();
        $modForums = $modUserIds === []
            ? collect()
            : $this->staffDirectoryRepository->listModeratedForums($modUserIds);

        $forumModRows = [];
        foreach ($forumMods as $modRow) {
            $arr = (array) $modRow;
            $userId = (int) $arr['userid'];
            $forums = [];
            foreach ($modForums->get($userId, collect()) as $forumRow) {
                $forums[] = ['id' => (int) $forumRow->id, 'name' => (string) $forumRow->name];
            }
            $base = $buildUserRow($arr);
            $base['forums'] = $forums;
            $forumModRows[] = $base;
        }

        $staffRows = [];
        $vipClass = defined('UC_VIP') ? \constant('UC_VIP') : 0;
        $staffUsers = $this->staffDirectoryRepository->listStaffAbove($vipClass)
            ->map(fn ($r) => (array) $r->getAttributes())
            ->all();

        $currentClass = null;
        foreach ($staffUsers as $arr) {
            if ($currentClass !== $arr['class']) {
                $currentClass = $arr['class'];
                $staffRows[] = ['header' => true, 'class_name' => UserClass::name((int) $arr['class'], false, true, true)];
            }
            $staffRows[] = $buildUserRow($arr, 'stafffor');
        }

        $vipRows = $this->staffDirectoryRepository->listAtClass($vipClass)
            ->map(fn ($r) => $buildUserRow((array) $r->getAttributes(), 'stafffor'))
            ->all();

        return $this->legacyPage($request, 'staff', true, [
            'supportRows' => $supportRows,
            'pickerRows' => $pickerRows,
            'forumModRows' => $forumModRows,
            'staffRows' => $staffRows,
            'vipRows' => $vipRows,
            'siteName' => Setting::getSiteName(),
        ]);

    }

    public function staffpanel(Request $request): View|RedirectResponse|Response
    {
        $moderatorClass = defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0;
        if (UserDisplay::currentClass() < $moderatorClass) {
            return $this->legacyAbortResponse('Error', 'Access denied!!!');
        }

        $sysopPanels = [];
        $adminPanels = [];
        $modPanels = [];

        $sysopClass = defined('UC_SYSOP') ? \constant('UC_SYSOP') : PHP_INT_MAX;
        $adminClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : PHP_INT_MAX;

        if (UserDisplay::currentClass() >= $sysopClass) {
            $sysopPanels = $this->staffDirectoryRepository->listSysopPanels();
        }
        if (UserDisplay::currentClass() >= $adminClass) {
            $adminPanels = $this->staffDirectoryRepository->listAdminPanels();
        }
        if (UserDisplay::currentClass() >= $moderatorClass) {
            $modPanels = $this->staffDirectoryRepository->listModPanels();
        }

        return $this->legacyPage($request, 'staffpanel', true, [
            'sysopPanels' => $sysopPanels,
            'adminPanels' => $adminPanels,
            'modPanels' => $modPanels,
        ]);

    }
}
