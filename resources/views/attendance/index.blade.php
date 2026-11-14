@extends('layouts.app')

@section('title', __('attendance.title'))

@section('content')
<x-page :title="__('attendance.title')" width="list">
@if ($vm->hasAttendedToday)
    <x-frame :caption="__('attendance.success')" :center="false">
    <p>{{ $vm->headerLeft ?? '' }}<span>{{ $vm->headerRight ?? '' }}</span></p>
    </x-frame>
@else
    <x-frame :caption="__('attendance.title')" :center="false">
    <div class="nx-box">
    <div>
    <form method="post" action="/web/user/attendance" class="nx-inline-block">@csrf
    <div class="nx-fgrid nx-fgrid--flat">
    {{ $vm->captchaHtml ?? '' }}
    <div class="nx-ffull text-center"><input type="submit" value="{{ __('attendance.attend_button')}}" class="nx-btn nx-btn--primary"  data-submit-once /></div>
    </div>
    </form>
    </div>
    </div>
    </x-frame>
@endif
    <div class="nx-flex-center"><div id="calendar" class="nx-calendar"></div></div>
    <ul>
        @foreach ($vm->bonusLines['lines'] ?? [] as $line)
            <li>{{ $line }}</li>
        @endforeach
        <li><ol>
            @foreach ($vm->bonusLines['continuous'] ?? [] as $line)
                <li>{{ $line }}</li>
            @endforeach
        </ol></li>
    </ul>
</x-page>
@endsection
