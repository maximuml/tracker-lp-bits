<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Repositories\AttendanceRepository;
use App\Support\AssetAppender;
use App\Support\Captcha;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Html\SafeHtml;
use App\Support\LegacyResponse;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AttendanceController extends LegacyController
{
    public function __construct(
        private readonly Globals $globals,
        private readonly CurrentUser $currentUser,
    ) {}

    public function attendance(Request $request, AttendanceRepository $repository): View|RedirectResponse|Response
    {
        $curUser = $this->currentUser->get();
        if ($curUser === null) {
            return redirect('/attendance.php');
        }

        $uid = (int) ($curUser['id'] ?? 0);
        $captchaEnabled = SiteConfig::current()->captcha->attendanceEnabled((bool) config('captcha.attendance.enabled', true));

        if ($request->isMethod('post')) {
            if ($captchaEnabled && SiteConfig::current()->security->captchaRequired()) {
                Captcha::checkCode(
                    (string) (request()->post('imagehash') ?? ''),
                    (string) (request()->post('imagestring') ?? ''),
                    'attendance.php',
                    false,
                    true
                );
            }
            $attendance = $repository->attend($uid);
            if (! $attendance->is_updated) {
                LegacyResponse::abort(__('legacy/attendance.sorry'), __('legacy/attendance.already_attended'));
            }
        } else {
            $attendance = $repository->getAttendance($uid);
        }

        if (! $attendance) {
            $attendance = new Attendance([
                'uid' => $uid,
                'points' => 0,
                'days' => 0,
                'total_days' => 0,
            ]);
        }

        $data = $repository->buildViewData($attendance, $uid);
        $data['attendanceCaptchaEnabled'] = $captchaEnabled;
        $data['iv'] = SiteConfig::current()->security->captchaRequired() ? 'yes' : 'no';

        AssetAppender::css('vendor/fullcalendar-5.10.2/main.min.css', 'header', true);
        AssetAppender::js('vendor/fullcalendar-5.10.2/main.min.js', 'footer', true);
        if (($data['localeJs'] ?? null) !== null) {
            AssetAppender::js("vendor/fullcalendar-5.10.2/locales/{$data['localeJs']}.js", 'footer', true);
        }

        if ($data['hasAttendedToday']) {
            $data['headerLeft'] = SafeHtml::fromTrustedHtml(sprintf(
                (string) (__('legacy/attendance.attend_info')).(string) (__('legacy/attendance.retroactive_description')),
                $attendance->total_days,
                $attendance->days,
                $attendance->points,
                $curUser['attendance_card'] ?? 0
            ));
            $data['headerRight'] = SafeHtml::fromTrustedHtml(Locale::trans(
                'attendance.ranking',
                ['ranking' => $data['myRanking'], 'counts' => $data['todayCounts']],
                null
            ));
            AssetAppender::js($this->calendarScript($data), 'footer', false);
            $data['bonusLines'] = $this->bonusLines();
        } else {
            if ($captchaEnabled && $data['iv'] === 'yes') {
                ob_start();
                Captcha::showImageCode('grid');
                $data['captchaHtml'] = (string) ob_get_clean();
            }
        }

        return $this->legacyPage($request, 'attendance', true, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function calendarScript(array $data): string
    {
        $eventStr = (string) json_encode($data['events'] ?? []);
        $validRangeStr = (string) json_encode($data['validRange'] ?? []);
        $localeJs = (string) ($data['localeJs'] ?? '');
        $confirmTip = (string) (__('legacy/attendance.retroactive_confirm_tip'));

        return <<<EOP
let events = JSON.parse('$eventStr')
let validRange = JSON.parse('$validRangeStr')
let confirmText = "{$confirmTip}"
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      locale: '$localeJs',
      events: events,
      validRange: validRange,
      eventClick: function(info) {
        if (info.event.groupId == 'to_do') {
            retroactive(info.event.startStr)
        }
      }
    });
    calendar.render();
});

function retroactive(dateStr) {
    if (!window.confirm(confirmText + dateStr + ' ?')) {
        return
    }
    nativePost('ajax.php', {params: {date: dateStr}, action: 'attendanceRetroactive'}, function (response) {
        if (response.ret != 0) {
            alert(response.msg)
        } else {
            location.reload();
        }
    })
}
EOP;
    }

    /**
     * @return array{lines: list<string>, continuous: list<string>}
     */
    private function bonusLines(): array
    {
        $initial = (int) ($this->globals->get('attendance_initial_bonus') ?? 0);
        $step = (int) ($this->globals->get('attendance_step_bonus') ?? 0);
        $max = (int) ($this->globals->get('attendance_max_bonus') ?? 0);
        $continuous = $this->globals->get('attendance_continuous_bonus');
        $continuousLines = [];
        foreach (is_array($continuous) ? $continuous : [] as $day => $value) {
            $continuousLines[] = sprintf((string) (__('legacy/attendance.continuous')), $day, $value);
        }

        return [
            'lines' => [
                sprintf((string) (__('legacy/attendance.initial')), $initial),
                sprintf((string) (__('legacy/attendance.steps')), $step, $max),
            ],
            'continuous' => $continuousLines,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attend(Request $request, AttendanceRepository $repository): array
    {
        $curUser = $this->currentUser->get();
        if ($curUser === null) {
            return $this->fail([], 'Unauthenticated');
        }

        $uid = (int) ($curUser['id'] ?? 0);
        $attendance = $repository->attend($uid);

        return $this->success([
            'uid' => $attendance->uid,
            'points' => $attendance->points,
            'days' => $attendance->days,
            'total_days' => $attendance->total_days,
            'is_updated' => $attendance->is_updated,
        ], 'Attendance recorded');
    }
}
