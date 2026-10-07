@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', __('incrementbulk.page_title'))

@section('content')
    <div class="nx-main nx-embedded nx-box--737">
                <div class="text-center">
                    <h1>{{ __('incrementbulk.page_title') }}</a></h1>
                    <form method=post action="/web/system/increment-bulk">@csrf
                        @if ($returnto || $httpReferer)
                            <input type=hidden name=returnto value="{{ $returnto }}">
                        @endif
                        <div class="nx-fgrid nx-fgrid--flat">
                            @if ($sent !== null && $sent == 1)
                            <div class="nx-ffull p-[10pt] text-center"><span class="text-nxm-danger"><b> {{ $validTypeMap[$type] ?? '' }}{{ __('incrementbulk.sent_success') }}</span></b></div>
                            @endif
                                <div class="nx-fhead">{{ __('incrementbulk.labels')['type'] }}</div>
                                <div class="nx-fcell">
                                    @foreach ($validTypeMap as $name => $text)
                                    <label><input type="radio" name="type" value="{{ $name }}">{{ $text }}@if ($name == 'uploaded')&nbsp;(GB)@endif</label>
                                    @endforeach
                                </div>
                            <div class="nx-fhead">{{ __('incrementbulk.labels')['amount'] }} </div><div class="nx-fcell"><input type=text name=amount size=10></div>
                            <div class="nx-fhead">{{ __('incrementbulk.labels')['duration'] }}</div><div class="nx-fcell"><input type=number min="1" name=duration size=10> {{ __('incrementbulk.labels')['duration_help'] }}</div>
                                <div class="nx-fhead">{{ __('incrementbulk.labels')['user_class'] }}</div><div class="nx-fcell">
                                        @foreach ($classes as $chunk)
                                        <div class="flex items-start">
                                            @foreach ($chunk as $class => $info)
                                            <div class="nx-fcell"><label><input type="checkbox" name="classes[]" value="{{ $class }}" />{{ $info }}</label></div>
                                            @endforeach
                                        </div>
                                        @endforeach
                                </div>
                            <div class="nx-fhead">{{ __('incrementbulk.labels')['msg_subject'] }} </div><div class="nx-fcell"><input type=text name=subject size=82></div>
                            <div class="nx-fhead">{{ __('incrementbulk.labels')['msg_body'] }} </div><div class="nx-fcell"><textarea name=msg cols=80 rows=5>{{ $body }}</textarea></div>
                                <div class="nx-ffull"><div class="text-center"><b>{{ __('incrementbulk.labels')['operator'] }}:&nbsp;&nbsp;</b>
                                        <label><input name="sender" type="radio" value="self" checked>{{ $CURUSER['username'] ?? '' }}</label>
                                        &nbsp; <label><input name="sender" type="radio" value="system">System</label>
                                    </div></div>
                            <div class="nx-ffull text-center"><input type=submit value="{{ $submitLabel }}" class=btn></div>
                        </div>
                        <input type=hidden name=receiver value={{ $receiver }}>
                    </form>

                </div></div>
@endsection
