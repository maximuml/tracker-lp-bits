<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\StaffMessage;
use App\Models\User;
use App\Support\Cache;
use App\Support\Hooks;
use App\Support\SupportContext;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Nexus\Database\NexusDB;

class StaffMessageController extends LegacyController
{
    public function staffmess(Request $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Sorry', 'Access denied.');
        }

        $currentUser = SupportContext::getUser() ?? [];
        $classes = array_chunk(User::$classes, 4, true);

        return $this->legacyPage($request, 'staffmess', true, [
            'classes' => $classes,
            'body' => htmlspecialchars((string) SupportContext::getQuery('body')),
            'receiver' => (int) (SupportContext::getQuery('receiver') ?? 0),
            'username' => htmlspecialchars((string) ($currentUser['username'] ?? '')),
            'sent' => (int) (SupportContext::getQuery('sent') ?? 0),
        ]);
    }

    public function takeStaffmess(Request $request): Response|RedirectResponse
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        if (! $request->isMethod('post')) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $currentUser = SupportContext::getUser() ?? [];
        $senderId = SupportContext::getPost('sender') === 'system' ? 0 : (int) ($currentUser['id'] ?? 0);
        $subject = trim((string) SupportContext::getPost('subject'));
        $msg = trim((string) SupportContext::getPost('msg'));

        if ($msg === '') {
            return $this->legacyAbortResponse('Error', "Don't leave any fields blank.");
        }

        $selectedClasses = (array) SupportContext::getPost('classes');
        if (empty($selectedClasses)) {
            return $this->legacyAbortResponse('Error', 'No valid filter');
        }
        foreach ($selectedClasses as $class) {
            $classId = (int) $class;
            if (! Validators::isId($classId) && $classId !== 0) {
                return $this->legacyAbortResponse('Error', 'Invalid Class');
            }
        }

        $size = 10000;
        $page = 1;
        $dt = now()->toDateTimeString();
        $conditions = [];
        $classIds = array_map('intval', $selectedClasses);
        $conditions[] = 'class IN ('.implode(', ', $classIds).')';
        $conditions = Hooks::applyFilter('role_query_conditions', $conditions, SupportContext::allPost());
        if (empty($conditions)) {
            return $this->legacyAbortResponse('Error', 'No valid filter');
        }
        $whereStr = implode(' OR ', $conditions);

        set_time_limit(300);

        while (true) {
            $offset = ($page - 1) * $size;
            $rows = NexusDB::table('users')
                ->whereRaw("($whereStr)")
                ->where('enabled', 'yes')
                ->where('status', 'confirmed')
                ->offset($offset)
                ->limit($size)
                ->get(['id']);

            if ($rows->isEmpty()) {
                break;
            }

            $msgRecords = [];
            foreach ($rows as $dat) {
                $msgRecords[] = [
                    'sender' => $senderId,
                    'receiver' => $dat->id,
                    'added' => $dt,
                    'subject' => $subject,
                    'msg' => $msg,
                ];
            }
            Message::query()->insert($msgRecords);
            $page++;
        }

        return redirect('staffmess.php?sent=1');
    }

    public function contactstaff(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'contactstaff', true, [
            'lang_contactstaff' => (array) SupportContext::getGlobal('lang_contactstaff', []),
        ]);

    }

    public function takecontact(Request $request): View|RedirectResponse|Response
    {
        $curUser = SupportContext::getUser() ?? [];
        $langTakecontact = (array) SupportContext::getGlobal('lang_takecontact', []);

        if (! $request->isMethod('post')) {
            return $this->legacyAbortResponse($langTakecontact['std_error'] ?? 'Error', $langTakecontact['std_method'] ?? 'Method not allowed.');
        }

        $msg = trim((string) SupportContext::getPost('body'));
        $subject = trim((string) SupportContext::getPost('subject'));

        if ($msg === '') {
            return $this->legacyAbortResponse($langTakecontact['std_error'] ?? 'Error', $langTakecontact['std_please_enter_something'] ?? 'Please enter something.');
        }
        if ($subject === '') {
            return $this->legacyAbortResponse($langTakecontact['std_error'] ?? 'Error', $langTakecontact['std_please_define_subject'] ?? 'Please define a subject.');
        }

        $currentUserId = (int) ($curUser['id'] ?? 0);
        $moderatorClass = defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0;
        $timeNow = (int) SupportContext::getGlobal('TIMENOW', time());

        if (UserDisplay::currentClass() < $moderatorClass) {
            $last = $curUser['last_staffmsg'] ?? null;
            if ($last !== null && strtotime((string) $last) > ($timeNow - 60)) {
                $secs = 60 - ($timeNow - strtotime((string) $last));

                return $this->legacyAbortResponse(
                    $langTakecontact['std_error'] ?? 'Error',
                    ($langTakecontact['std_message_flooding'] ?? 'Message flooding: wait ').$secs.($langTakecontact['std_second'] ?? ' second').($secs == 1 ? '' : ($langTakecontact['std_s'] ?? 's')).($langTakecontact['std_before_sending_pm'] ?? ' before sending PM.')
                );
            }
        }

        StaffMessage::add($currentUserId, $subject, $msg);

        User::query()->where('id', $currentUserId)->update(['last_staffmsg' => date('Y-m-d H:i:s')]);
        Cache::clearStaffMessage();

        $returnto = (string) SupportContext::getPost('returnto');
        if ($returnto !== '') {
            return redirect($returnto);
        }

        return $this->legacyPage($request, 'takecontact', true, [
            'lang_takecontact' => $langTakecontact,
        ]);
    }
}
