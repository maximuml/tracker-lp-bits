<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\StaffMessage;
use App\Models\User;
use App\Support\Cache;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Hooks;
use App\Support\SupportContext;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StaffMessageController extends LegacyController
{
    public function staffmess(Request $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Sorry', 'Access denied.');
        }

        $currentUser = app(CurrentUser::class)->get() ?? [];
        $classes = array_chunk(User::$classes, 4, true);

        return $this->legacyPage($request, 'staffmess', true, [
            'classes' => $classes,
            'body' => htmlspecialchars((string) request()->query('body')),
            'receiver' => (int) (request()->query('receiver') ?? 0),
            'username' => htmlspecialchars((string) ($currentUser['username'] ?? '')),
            'sent' => (int) (request()->query('sent') ?? 0),
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

        $currentUser = app(CurrentUser::class)->get() ?? [];
        $senderId = request()->post('sender') === 'system' ? 0 : (int) ($currentUser['id'] ?? 0);
        $subject = trim((string) request()->post('subject'));
        $msg = trim((string) request()->post('msg'));

        if ($msg === '') {
            return $this->legacyAbortResponse('Error', "Don't leave any fields blank.");
        }

        $selectedClasses = (array) request()->post('classes');
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
        $conditions = Hooks::applyFilter('role_query_conditions', $conditions, request()->post());
        if (empty($conditions)) {
            return $this->legacyAbortResponse('Error', 'No valid filter');
        }
        $whereStr = implode(' OR ', $conditions);

        set_time_limit(300);

        while (true) {
            $offset = ($page - 1) * $size;
            $rows = DB::table('users')
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
            'lang_contactstaff' => (array) app(Globals::class)->get('lang_contactstaff', []),
        ]);

    }

    public function takecontact(Request $request): View|RedirectResponse|Response
    {
        $curUser = app(CurrentUser::class)->get() ?? [];
        $langTakecontact = (array) app(Globals::class)->get('lang_takecontact', []);

        if (! $request->isMethod('post')) {
            return $this->legacyAbortResponse($langTakecontact['std_error'] ?? 'Error', $langTakecontact['std_method'] ?? 'Method not allowed.');
        }

        $msg = trim((string) request()->post('body'));
        $subject = trim((string) request()->post('subject'));

        if ($msg === '') {
            return $this->legacyAbortResponse($langTakecontact['std_error'] ?? 'Error', $langTakecontact['std_please_enter_something'] ?? 'Please enter something.');
        }
        if ($subject === '') {
            return $this->legacyAbortResponse($langTakecontact['std_error'] ?? 'Error', $langTakecontact['std_please_define_subject'] ?? 'Please define a subject.');
        }

        $currentUserId = (int) ($curUser['id'] ?? 0);
        $moderatorClass = defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0;
        $timeNow = (int) app(Globals::class)->get('TIMENOW', time());

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

        $returnto = (string) request()->post('returnto');
        if ($returnto !== '') {
            return redirect($returnto);
        }

        return $this->legacyPage($request, 'takecontact', true, [
            'lang_takecontact' => $langTakecontact,
        ]);
    }
}
