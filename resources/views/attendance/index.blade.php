@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', __('attendance.title'))

@section('content')
@if ($hasAttendedToday)
    <x-frame :caption="__('attendance.success')" :center="false">
    <p>{{ $headerLeft ?? '' }}<span>{{ $headerRight ?? '' }}</span></p>
    </x-frame>
@else
    <x-frame :caption="__('attendance.title')" :center="false">
    <div class="nx-box">
    <div>
    <form method="post" action="/web/user/attendance" class="nx-inline-block">@csrf
    <div class="nx-fgrid nx-fgrid--flat">
    {{ $captchaHtml ?? '' }}
    <div class="nx-ffull text-center"><input type="submit" value="{{ __('attendance.attend_button')}}" class="btn" /></div>
    </div>
    </form>
    </div>
    </div>
    </x-frame>
@endif
    <div class="nx-flex-center"><div id="calendar" class="nx-calendar"></div></div>
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
@endsection
