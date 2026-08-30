<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\Setting;
use App\Models\User;
use App\Support\Country;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Permissions;
use App\Support\UserClass;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StaffPageController extends LegacyController
{
    public function staff(Request $request): View|RedirectResponse|Response
    {
        $curUser = app(CurrentUser::class)->get() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);

        if (! Permissions::userCan(PermissionEnum::STAFF_MEMBER->value, false, $currentUserId)) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $langStaff = (array) app(Globals::class)->get('lang_staff', []);
        $secs = 900;
        $dt = time() - $secs;

        $onlineImg = '<img class="button_online" src="pic/trans.gif" alt="online" title="'.($langStaff['title_online'] ?? 'Online').'" />';
        $offlineImg = '<img class="button_offline" src="pic/trans.gif" alt="offline" title="'.($langStaff['title_offline'] ?? 'Offline').'" />';
        $sendPmImg = '<img class="button_pm" src="pic/trans.gif" alt="pm" />';

        $buildUserRow = function (array $arr, string $extraKey = '') use ($dt, $onlineImg, $offlineImg, $sendPmImg, $langStaff): array {
            $countryrow = Country::rowWithContext($arr['country'] ?? 0) ?? ['flagpic' => '', 'name' => ''];
            $isOnline = strtotime((string) $arr['last_access']) > $dt;

            return [
                'id' => (int) $arr['id'],
                'username_html' => UserDisplay::username((int) $arr['id']),
                'flag_html' => '<img width=24 height=15 src="pic/flag/'.$countryrow['flagpic'].'" title="'.$countryrow['name'].'" style="padding-bottom:1px;">',
                'online_html' => $isOnline ? $onlineImg : $offlineImg,
                'pm_html' => '<a href=sendmessage.php?receiver='.(int) $arr['id'].' title="'.($langStaff['title_send_pm'] ?? 'Send PM').'">'.$sendPmImg.'</a>',
                'extra' => $extraKey ? ($arr[$extraKey] ?? '') : '',
            ];
        };

        $supportRows = User::query()
            ->where('support', true)
            ->where('status', 'confirmed')
            ->orderBy('username')
            ->get(['id', 'country', 'last_access', 'supportlang', 'supportfor'])
            ->map(fn ($r) => $buildUserRow((array) $r->getAttributes(), 'supportfor'))
            ->all();

        $pickerRows = User::query()
            ->where('picker', true)
            ->where('status', 'confirmed')
            ->orderBy('username')
            ->get(['id', 'country', 'last_access', 'pickfor'])
            ->map(fn ($r) => $buildUserRow((array) $r->getAttributes(), 'pickfor'))
            ->all();

        $forumModRows = [];
        $forumMods = DB::table('forummods')
            ->leftJoin('users', 'forummods.userid', '=', 'users.id')
            ->orderBy('forummods.forumid')
            ->orderBy('forummods.userid')
            ->get(['forummods.userid AS userid', 'users.last_access', 'users.country'])
            ->unique('userid')
            ->values();

        foreach ($forumMods as $modRow) {
            $arr = (array) $modRow;
            $userId = (int) $arr['userid'];
            $forums = [];
            $forumRows = DB::table('forums as f')
                ->leftJoin('forummods as fm', 'f.id', '=', 'fm.forumid')
                ->where('fm.userid', $userId)
                ->get(['f.id', 'f.name']);
            foreach ($forumRows as $forumRow) {
                $forums[] = '<a href=forums.php?action=viewforum&forumid='.(int) $forumRow->id.'>'.htmlspecialchars($forumRow->name).'</a>';
            }
            $base = $buildUserRow($arr, '');
            $base['forums_html'] = implode(', ', $forums);
            $forumModRows[] = $base;
        }

        $staffRows = [];
        $vipClass = defined('UC_VIP') ? \constant('UC_VIP') : 0;
        $staffUsers = User::query()
            ->where('class', '>', $vipClass)
            ->where('status', 'confirmed')
            ->orderByDesc('class')
            ->orderBy('username')
            ->get()
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

        $vipRows = User::query()
            ->where('class', $vipClass)
            ->where('status', 'confirmed')
            ->orderBy('username')
            ->get()
            ->map(fn ($r) => $buildUserRow((array) $r->getAttributes(), 'stafffor'))
            ->all();

        return $this->legacyPage($request, 'staff', true, [
            'lang_staff' => $langStaff,
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

        $langStaffpanel = (array) app(Globals::class)->get('lang_staffpanel', []);

        $sysopPanels = [];
        $adminPanels = [];
        $modPanels = [];

        $sysopClass = defined('UC_SYSOP') ? \constant('UC_SYSOP') : PHP_INT_MAX;
        $adminClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : PHP_INT_MAX;

        if (UserDisplay::currentClass() >= $sysopClass) {
            $sysopPanels = DB::table('sysoppanel')->get()->map(fn ($r) => (array) $r)->all();
        }
        if (UserDisplay::currentClass() >= $adminClass) {
            $adminPanels = DB::table('adminpanel')->get()->map(fn ($r) => (array) $r)->all();
        }
        if (UserDisplay::currentClass() >= $moderatorClass) {
            $modPanels = DB::table('modpanel')->get()->map(fn ($r) => (array) $r)->all();
        }

        return $this->legacyPage($request, 'staffpanel', true, [
            'lang_staffpanel' => $langStaffpanel,
            'sysopPanels' => $sysopPanels,
            'adminPanels' => $adminPanels,
            'modPanels' => $modPanels,
        ]);

    }
}
