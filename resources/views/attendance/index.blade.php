@extends('layouts.legacy')

@section('title', $lang_attendance['title'] ?? 'Attendance')

@section('content')
@if ($hasAttendedToday)
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::open((string) ($lang_attendance['success'] ?? ''), false, 10, '100%', 'left')))
    <p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($headerLeft ?? ''))<span style="float:right">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($headerRight ?? ''))</span></p>
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::CLOSE))
    <div class="nx-flex-center" style="padding: 20px 0"><div id="calendar" style="width: 60%"></div></div>
    <ul>
        @foreach ($bonusLines['lines'] ?? [] as $line)
            <li>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($line))</li>
        @endforeach
        <li><ol>
            @foreach ($bonusLines['continuous'] ?? [] as $line)
                <li>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($line))</li>
            @endforeach
        </ol></li>
    </ul>
@else
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::open((string) ($lang_attendance['title'] ?? ''), false, 10, '100%', 'left')))
    <div class="nx-box">
    <div style="margin-top: 20px; text-align: center;">
    <form method="post" action="attendance.php" class="nx-inline-block">
    <div class="nx-fgrid nx-fgrid--flat">
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($captchaHtml ?? ''))
    <div class="nx-ffull nx-center"><input type="submit" value="{{ $lang_attendance['attend_button'] ?? 'Check in' }}" class="btn" /></div>
    </div>
    </form>
    </div>
    </div>
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::CLOSE))
@endif
@endsection
