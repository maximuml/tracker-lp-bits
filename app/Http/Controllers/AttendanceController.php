<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\Auth\ActorContext;
use App\Http\Requests\AttendanceRequest;
use App\Models\Attendance;
use App\Repositories\AttendanceRepository;
use App\Support\AssetAppender;
use App\Support\Captcha;
use App\Support\Config\SiteConfig;
use App\Support\Html\SafeHtml;
use App\Support\PageResponses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Js;
use Illuminate\View\View;

class AttendanceController extends BasePageController
{
    public function __construct(
        private readonly ActorContext $actor,
    ) {}

    public function attendance(Request $request, AttendanceRepository $repository): View|RedirectResponse|Response
    {
        if ($this->actor->isGuest()) {
            return redirect('/web/attendance');
        }

        $uid = (int) ($this->actor->id);
        $captchaEnabled = SiteConfig::current()->captcha->attendanceEnabled((bool) config('captcha.attendance.enabled', true));
        $attendance = $repository->getAttendance($uid);

        return $this->renderAttendance($request, $repository, $this->actor, $uid, $attendance, $captchaEnabled);
    }

    public function attendanceSubmit(AttendanceRequest $request, AttendanceRepository $repository): View|RedirectResponse|Response
    {
        if ($this->actor->isGuest()) {
            return redirect('/web/attendance');
        }

        $uid = (int) ($this->actor->id);
        $captchaEnabled = SiteConfig::current()->captcha->attendanceEnabled((bool) config('captcha.attendance.enabled', true));

        if ($captchaEnabled && SiteConfig::current()->security->captchaRequired()) {
            Captcha::checkCode(
                (string) ($request->post('imagehash') ?? ''),
                (string) ($request->post('imagestring') ?? ''),
                '/web/attendance',
                false,
                true
            );
        }
        $attendance = $repository->attend($uid);
        if (! $attendance->is_updated) {
            PageResponses::abort(__('attendance.sorry'), __('attendance.already_attended'));
        }

        return $this->renderAttendance($request, $repository, $this->actor, $uid, $attendance, $captchaEnabled);
    }

    private function renderAttendance(
        Request $request,
        AttendanceRepository $repository,
        ActorContext $actor,
        int $uid,
        ?Attendance $attendance,
        bool $captchaEnabled
    ): View|RedirectResponse {
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
            $data['headerLeft'] = view('components.attendance.header-left', [
                'totalDays' => $attendance->total_days,
                'days' => $attendance->days,
                'points' => $attendance->points,
                'cards' => $this->actor->value('attendance_card', 0),
            ]);
            $data['headerRight'] = view('components.attendance.header-right', [
                'myRanking' => (int) $data['myRanking'],
                'todayCounts' => (int) $data['todayCounts'],
            ]);
        } else {
            if ($captchaEnabled && $data['iv'] === 'yes') {
                $data['captchaHtml'] = SafeHtml::fromTrustedHtml(Captcha::renderHtml(layout: 'grid'));
            }
        }
        AssetAppender::js($this->calendarScript($data), 'footer', false);
        $data['bonusLines'] = $this->bonusLines();

        return $this->renderPage($request, 'attendance', true, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function calendarScript(array $data): string
    {
        $eventsExpr = Js::from($data['events'] ?? []);
        $validRangeExpr = Js::from($data['validRange'] ?? []);
        $localeExpr = Js::from((string) ($data['localeJs'] ?? ''));
        $confirmExpr = Js::from((string) (__('attendance.retroactive_confirm_tip')));

        return <<<EOP
let events = {$eventsExpr}
let validRange = {$validRangeExpr}
let confirmText = {$confirmExpr}
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      locale: {$localeExpr},
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
    nativePost('/web/attendance/retroactive', {date: dateStr}, function (response) {
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
        $initial = (int) SiteConfig::current()->bonus->attendanceInitial(Attendance::INITIAL_BONUS);
        $step = (int) SiteConfig::current()->bonus->attendanceStep(Attendance::STEP_BONUS);
        $max = (int) SiteConfig::current()->bonus->attendanceMax(Attendance::MAX_BONUS);
        $continuous = SiteConfig::current()->bonus->attendanceContinuous(Attendance::CONTINUOUS_BONUS);
        $continuousLines = [];
        foreach ($continuous as $day => $value) {
            $continuousLines[] = sprintf((string) (__('attendance.continuous')), $day, $value);
        }

        return [
            'lines' => [
                sprintf((string) (__('attendance.initial')), $initial),
                sprintf((string) (__('attendance.steps')), $step, $max),
            ],
            'continuous' => $continuousLines,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attend(Request $request, AttendanceRepository $repository): array
    {
        if ($this->actor->isGuest()) {
            return $this->fail([], 'Unauthenticated');
        }

        $uid = (int) ($this->actor->id);
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
