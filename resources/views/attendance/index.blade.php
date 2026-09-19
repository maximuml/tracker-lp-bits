@extends('layouts.legacy')

@section('title', __('legacy/attendance.title'))

@section('content')
@if ($hasAttendedToday)
    {{ \App\Support\Frame::open((string) (__('legacy/attendance.success')), false, 10, '100%', 'left') }}
    <p>{{ $headerLeft ?? '' }}<span>{{ $headerRight ?? '' }}</span></p>
    {{ \App\Support\Frame::close() }}
    <div class="nx-flex-center"><div id="calendar"></div></div>
    <ul>
        @foreach ($bonusLines['lines'] ?? [] as $line)
            <li>{{ $line }}</li>
        @endforeach
        <li><ol>
            @foreach ($bonusLines['continuous'] ?? [] as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ol></li>
    </ul>
@else
    {{ \App\Support\Frame::open((string) (__('legacy/attendance.title')), false, 10, '100%', 'left') }}
    <div class="nx-box">
    <div>
    <form method="post" action="attendance.php" class="nx-inline-block">
    <div class="nx-fgrid nx-fgrid--flat">
    {{ $captchaHtml ?? '' }}
    <div class="nx-ffull nx-center"><input type="submit" value="{{ __('legacy/attendance.attend_button')}}" class="btn" /></div>
    </div>
    </form>
    </div>
    </div>
    {{ \App\Support\Frame::close() }}
@endif
@endsection
