<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Message;
use App\Models\Setting;
use App\Models\StaffMessage;
use App\Models\User;
use App\Repositories\MessageRepository;
use App\Repositories\ToolRepository;
use App\Support\Cache;
use App\Support\Country;
use App\Support\Format;
use App\Support\Permissions;
use App\Support\SupportContext;
use App\Support\Time;
use App\Support\UserClass;
use App\Support\UserDisplay;
use App\Support\Validators;
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

    public function staffbox(Request $request): View|RedirectResponse|Response
    {
        $currentUser = SupportContext::getUser() ?? [];
        $currentUserId = (int) ($currentUser['id'] ?? 0);
        $langStaffbox = (array) SupportContext::getGlobal('lang_staffbox', []);
        $langFunctions = SupportContext::getLangFunctions();

        $action = (string) (SupportContext::getQuery('action') ?? '');
        $queryString = (string) SupportContext::getServerValue('QUERY_STRING');
        $httpReferer = (string) SupportContext::getServerValue('HTTP_REFERER');

        $canAccessStaffMessage = function (array|int $msg) use ($currentUserId): void {
            if (Permission::can(PermissionEnum::STAFF_MEMBER, User::findOrFail($currentUserId))) {
                return;
            }
            if (is_numeric($msg)) {
                $msg = StaffMessage::query()->findOrFail((int) $msg)->toArray();
            }
            if (empty($msg['permission']) || ! in_array($msg['permission'], ToolRepository::listUserAllPermissions($currentUserId))) {
                abort(403, 'Permission denied.');
            }
        };

        if ($request->isMethod('post') && $action === 'takeanswer') {
            $receiver = (int) (SupportContext::getPost('receiver') ?? 0);
            $answeringto = (int) (SupportContext::getPost('answeringto') ?? 0);

            Validators::assertId($receiver, true);

            if (! User::query()->find($receiver)) {
                return $this->legacyAbortResponse($langStaffbox['std_error'] ?? 'Error', $langStaffbox['std_no_user_id'] ?? 'No user with that ID.');
            }

            $msg = trim((string) SupportContext::getPost('body'));
            if ($msg === '') {
                return $this->legacyAbortResponse($langStaffbox['std_error'] ?? 'Error', $langStaffbox['std_body_is_empty'] ?? 'Body is empty.');
            }

            $canAccessStaffMessage($answeringto);
            $subject = StaffMessage::query()->findOrFail($answeringto)->value('subject');

            Message::add([
                'sender' => $currentUserId,
                'receiver' => $receiver,
                'subject' => $subject,
                'added' => now(),
                'msg' => $msg,
            ]);

            StaffMessage::query()->where('id', $answeringto)->update(['answer' => $msg, 'answered' => 1, 'answeredby' => $currentUserId]);
            Cache::clearStaffMessage();

            return redirect('staffbox.php?action=viewpm&pmid=' . $answeringto);
        }

        if ($action === 'deletestaffmessage') {
            $id = (int) (SupportContext::getQuery('id') ?? 0);
            if ($id < 1) {
                return $this->legacyAbortResponse('Error', 'Invalid id');
            }
            $canAccessStaffMessage($id);
            StaffMessage::query()->where('id', $id)->delete();
            Cache::clearStaffMessage();
            $baseUrl = SupportContext::getGlobal('BASEURL', '');
            return redirect((string) $baseUrl . '/staffbox.php');
        }

        if ($action === 'setanswered') {
            $id = (int) (SupportContext::getQuery('id') ?? 0);
            $canAccessStaffMessage($id);
            StaffMessage::query()->where('id', $id)->update(['answered' => 1, 'answeredby' => $currentUserId]);
            Cache::clearStaffMessage();
            $return = (string) (SupportContext::getQuery('return') ?? '');
            return redirect('staffbox.php' . ($return !== '' ? '?' . $return : ''));
        }

        if ($request->isMethod('post') && $action === 'takecontactanswered') {
            $setAnswered = (array) SupportContext::getPost('setanswered');
            if (empty($setAnswered)) {
                return $this->legacyAbortResponse($langStaffbox['std_sorry'] ?? 'Sorry', \App\Support\Locale::trans('nexus.select_one_please', [], null));
            }
            $setDealt = SupportContext::getPost('setdealt') !== null;
            $delete = SupportContext::getPost('delete') !== null;

            $messages = StaffMessage::query()->whereIn('id', $setAnswered)->get();
            foreach ($messages as $message) {
                $canAccessStaffMessage($message->toArray());
                if ($setDealt) {
                    $message->update(['answered' => 1, 'answeredby' => $currentUserId]);
                } elseif ($delete) {
                    $message->delete();
                }
            }
            Cache::clearStaffMessage();
            return redirect('staffbox.php');
        }

        if ($action === 'viewpm') {
            $pmid = (int) (SupportContext::getQuery('pmid') ?? 0);
            $arr = StaffMessage::query()->findOrFail($pmid)->toArray();
            $canAccessStaffMessage($arr);

            $sender = Validators::isId($arr['sender']) ? UserDisplay::username($arr['sender']) : ($langStaffbox['text_system'] ?? 'System');
            $answeredby = UserDisplay::username($arr['answeredby']);

            return $this->legacyPage($request, 'staffbox', true, [
                'mode' => 'viewpm',
                'arr' => $arr,
                'sender' => $sender,
                'answeredby' => $answeredby,
                'lang_staffbox' => $langStaffbox,
            ]);
        }

        if ($action === 'answermessage') {
            $answeringto = (int) (SupportContext::getQuery('answeringto') ?? 0);
            $receiver = (int) (SupportContext::getQuery('receiver') ?? 0);

            Validators::assertId($receiver, true);

            $user = User::query()->find($receiver);
            if (! $user) {
                return $this->legacyAbortResponse($langStaffbox['std_error'] ?? 'Error', $langStaffbox['std_no_user_id'] ?? 'No user with that ID.');
            }

            $staffmsg = StaffMessage::query()->findOrFail($answeringto)->toArray();
            $canAccessStaffMessage($staffmsg);

            $returnTo = (string) (SupportContext::getQuery('returnto') ?? $httpReferer);

            return $this->legacyPage($request, 'staffbox', true, [
                'mode' => 'answermessage',
                'receiver' => $receiver,
                'answeringto' => $answeringto,
                'staffmsg' => $staffmsg,
                'returnTo' => $returnTo,
                'lang_staffbox' => $langStaffbox,
            ]);
        }

        // default list
        $query = MessageRepository::buildStaffMessageQuery($currentUserId);
        $count = (clone $query)->count();
        $perPage = 20;
        [$pagertop, $pagerbottom, , $offset, $pageSize] = \App\Support\Pagination::pager($perPage, $count, 'staffbox.php?');
        $rows = (clone $query)
            ->offset($offset)
            ->limit($pageSize)
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();

        return $this->legacyPage($request, 'staffbox', true, [
            'mode' => 'list',
            'rows' => $rows,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'queryString' => $queryString,
            'lang_staffbox' => $langStaffbox,
            'lang_functions' => $langFunctions,
        ]);

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