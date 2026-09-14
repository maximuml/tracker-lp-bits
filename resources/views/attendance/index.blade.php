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
    <table width="100%" border="1" cellspacing="0" cellpadding="10"><tbody>
    <tr><td class="text">
    <div style="margin-top: 20px; text-align: center;">
    <form method="post" action="attendance.php" class="nx-inline-block">
    <table border="0" cellpadding="5">
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($captchaHtml ?? ''))
    <tr><td class="toolbox" colspan="2" align="center"><input type="submit" value="{{ $lang_attendance['attend_button'] ?? 'Check in' }}" class="btn" /></td></tr>
    </table>
    </form>
    </div>
    </td></tr>
    </tbody></table>
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::CLOSE))
@endif
@endsection
