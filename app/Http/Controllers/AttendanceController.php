<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Repositories\AttendanceRepository;
use App\Support\Captcha;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\LegacyResponse;
use App\Support\SupportContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AttendanceController extends LegacyController
{
    public function attendance(Request $request, AttendanceRepository $repository): View|RedirectResponse|Response
    {
        $curUser = app(CurrentUser::class)->get();
        if ($curUser === null) {
            return redirect('/attendance.php');
        }

        $uid = (int) ($curUser['id'] ?? 0);
        $captchaEnabled = SiteConfig::current()->captcha->attendanceEnabled((bool) config('captcha.attendance.enabled', true));

        if ($request->isMethod('post')) {
            if ($captchaEnabled && app(Globals::class)->get('iv', '') === 'yes') {
                Captcha::checkCode(
                    (string) (request()->post('imagehash') ?? ''),
                    (string) (request()->post('imagestring') ?? ''),
                    'attendance.php',
                    false,
                    true
                );
            }
            $attendance = $repository->attend($uid);
            $langAttendance = (array) (app(Globals::class)->get('lang_attendance') ?? []);
            if (! $attendance->is_updated) {
                LegacyResponse::abort($langAttendance['sorry'] ?? '', $langAttendance['already_attended'] ?? '');
            }
        } else {
            $attendance = $repository->getAttendance($uid);
            if (! $captchaEnabled && ! ($attendance && $attendance->added && $attendance->added->isSameDay(Carbon::today()))) {
                $attendance = $repository->attend($uid);
            }
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

        return $this->legacyPage($request, 'attendance', true, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function attend(Request $request, AttendanceRepository $repository): array
    {
        $curUser = app(CurrentUser::class)->get();
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
