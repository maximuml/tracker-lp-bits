<?php

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\Setting;
use App\Models\User;
use App\Support\Country;
use App\Support\Permissions;
use App\Support\SupportContext;
use App\Support\UserClass;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Nexus\Database\NexusDB;

class StaffController extends LegacyController
{
    public function modtask(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'modtask');

    }

    public function staff(Request $request): View|RedirectResponse|Response
    {
        $curUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);

        if (! Permissions::userCan(PermissionEnum::STAFF_MEMBER->value, false, $currentUserId)) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $langStaff = (array) SupportContext::getGlobal('lang_staff', []);
        $secs = 900;
        $dt = time() - $secs;

        $onlineImg = '<img class="button_online" src="pic/trans.gif" alt="online" title="' . ($langStaff['title_online'] ?? 'Online') . '" />';
        $offlineImg = '<img class="button_offline" src="pic/trans.gif" alt="offline" title="' . ($langStaff['title_offline'] ?? 'Offline') . '" />';
        $sendPmImg = '<img class="button_pm" src="pic/trans.gif" alt="pm" />';

        $buildUserRow = function (array $arr, string $extraKey = '') use ($dt, $onlineImg, $offlineImg, $sendPmImg, $langStaff): array {
            $countryrow = Country::rowWithContext($arr['country'] ?? 0) ?? ['flagpic' => '', 'name' => ''];
            $isOnline = strtotime((string) $arr['last_access']) > $dt;
            return [
                'id' => (int) $arr['id'],
                'username_html' => UserDisplay::username((int) $arr['id']),
                'flag_html' => '<img width=24 height=15 src="pic/flag/' . $countryrow['flagpic'] . '" title="' . $countryrow['name'] . '" style="padding-bottom:1px;">',
                'online_html' => $isOnline ? $onlineImg : $offlineImg,
                'pm_html' => '<a href=sendmessage.php?receiver=' . (int) $arr['id'] . ' title="' . ($langStaff['title_send_pm'] ?? 'Send PM') . '">' . $sendPmImg . '</a>',
                'extra' => $extraKey ? ($arr[$extraKey] ?? '') : '',
            ];
        };

        $supportRows = User::query()
            ->where('support', 'yes')
            ->where('status', 'confirmed')
            ->orderBy('username')
            ->get(['id', 'country', 'last_access', 'supportlang', 'supportfor'])
            ->map(fn ($r) => $buildUserRow((array) $r->getAttributes(), 'supportfor'))
            ->all();

        $pickerRows = User::query()
            ->where('picker', 'yes')
            ->where('status', 'confirmed')
            ->orderBy('username')
            ->get(['id', 'country', 'last_access', 'pickfor'])
            ->map(fn ($r) => $buildUserRow((array) $r->getAttributes(), 'pickfor'))
            ->all();

        $forumModRows = [];
        $forumMods = NexusDB::table('forummods')
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
            $forumRows = NexusDB::table('forums as f')
                ->leftJoin('forummods as fm', 'f.id', '=', 'fm.forumid')
                ->where('fm.userid', $userId)
                ->get(['f.id', 'f.name']);
            foreach ($forumRows as $forumRow) {
                $forums[] = '<a href=forums.php?action=viewforum&forumid=' . (int) $forumRow['id'] . '>' . htmlspecialchars((string) $forumRow['name']) . '</a>';
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
                $staffRows[] = ['subheader' => true];
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

    public function staffbox(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'staffbox');

    }

    public function staffmess(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'staffmess');

    }

    public function takeStaffmess(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'takestaffmess');

    }

    public function contactstaff(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'contactstaff');

    }

    public function takecontact(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'takecontact');

    }

    public function modrules(Request $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Error', 'Only Administrators and above can modify the Rules, sorry.');
        }

        $act = (string) (SupportContext::getQuery('act') ?? 'list');

        if ($act === 'addsect' && $request->isMethod('post')) {
            $title = (string) SupportContext::getPost('title');
            $text = (string) SupportContext::getPost('text');
            $language = (int) SupportContext::getPost('language');
            NexusDB::table('rules')->insert([
                'title' => $title,
                'text' => $text,
                'lang_id' => $language,
            ]);
            NexusDB::cache_del('rules');
            return redirect('modrules.php');
        }

        if ($act === 'edited' && $request->isMethod('post')) {
            $id = (int) (SupportContext::getPost('id') ?? 0);
            $title = (string) SupportContext::getPost('title');
            $text = (string) SupportContext::getPost('text');
            $language = (int) SupportContext::getPost('language');
            NexusDB::table('rules')->where('id', $id)->update([
                'title' => $title,
                'text' => $text,
                'lang_id' => $language,
            ]);
            NexusDB::cache_del('rules');
            return redirect('modrules.php');
        }

        if ($act === 'del') {
            $id = (int) (SupportContext::getQuery('id') ?? 0);
            $sure = (int) (SupportContext::getQuery('sure') ?? 0);
            if (! $sure) {
                return $this->legacyAbortResponse('Delete Rule', 'You are about to delete a rule. Click <a class=altlink href=?act=del&id=' . $id . '&sure=1>here</a> if you are sure.', false);
            }
            NexusDB::table('rules')->where('id', $id)->delete();
            NexusDB::cache_del('rules');
            return redirect('modrules.php');
        }

        if ($act === 'newsect') {
            $langs = \App\Support\Locale::languageList('rule_lang', null);
            $defLang = (string) SupportContext::getGlobal('deflang', '');
            return $this->legacyPage($request, 'modrules', true, [
                'mode' => 'newsect',
                'langs' => $langs,
                'deflang' => $defLang,
            ]);
        }

        if ($act === 'edit') {
            $id = (int) (SupportContext::getQuery('id') ?? 0);
            $rule = (array) NexusDB::table('rules')->where('id', $id)->first();
            $langs = \App\Support\Locale::languageList('site_lang', null);
            return $this->legacyPage($request, 'modrules', true, [
                'mode' => 'edit',
                'rule' => $rule,
                'langs' => $langs,
            ]);
        }

        $rules = NexusDB::table('rules')
            ->leftJoin('language', 'rules.lang_id', '=', 'language.id')
            ->orderBy('lang_name')
            ->orderBy('rules.id')
            ->get(['rules.*', 'language.lang_name'])
            ->map(fn ($r) => (array) $r)
            ->all();

        return $this->legacyPage($request, 'modrules', true, [
            'mode' => 'list',
            'rows' => $rules,
        ]);

    }

    public function staffpanel(Request $request): View|RedirectResponse|Response
    {
        $moderatorClass = defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0;
        if (UserDisplay::currentClass() < $moderatorClass) {
            return $this->legacyAbortResponse('Error', 'Access denied!!!');
        }

        $langStaffpanel = (array) SupportContext::getGlobal('lang_staffpanel', []);

        $sysopPanels = [];
        $adminPanels = [];
        $modPanels = [];

        $sysopClass = defined('UC_SYSOP') ? \constant('UC_SYSOP') : PHP_INT_MAX;
        $adminClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : PHP_INT_MAX;

        if (UserDisplay::currentClass() >= $sysopClass) {
            $sysopPanels = NexusDB::table('sysoppanel')->get()->map(fn ($r) => (array) $r)->all();
        }
        if (UserDisplay::currentClass() >= $adminClass) {
            $adminPanels = NexusDB::table('adminpanel')->get()->map(fn ($r) => (array) $r)->all();
        }
        if (UserDisplay::currentClass() >= $moderatorClass) {
            $modPanels = NexusDB::table('modpanel')->get()->map(fn ($r) => (array) $r)->all();
        }

        return $this->legacyPage($request, 'staffpanel', true, [
            'lang_staffpanel' => $langStaffpanel,
            'sysopPanels' => $sysopPanels,
            'adminPanels' => $adminPanels,
            'modPanels' => $modPanels,
        ]);

    }

}