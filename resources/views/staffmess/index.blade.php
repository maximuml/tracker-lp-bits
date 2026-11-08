@extends('layouts.app')

@section('title', "Mass PM")

@section('content')
<x-page :title="'Mass PM'" width="form">
<div class="nx-main nx-embedded nx-box--737">
<form method=post action="/web/staffmess/send">
@csrf
@if ($showReturnto)
    <input type=hidden name=returnto value="{{ $returnto }}">
@endif
<div class="nx-fgrid nx-fgrid--flat">
@if ($sent === 1)
<div class="nx-ffull"><span class="text-nxm-danger"><b>The message has ben sent.</b></span></div>
@endif
    <div class="nx-fcell"><b>Send to class:</b></div>
    <div class="nx-fcell">
            @foreach ($classes as $chunk)
            <div class="flex flex-wrap items-start">
                @foreach ($chunk as $class => $info)
                <div class="nx-fcell"><label><input type="checkbox" name="classes[]" value="{{ (int) $class }}" />{{ $info['text'] ?? '' }}</label></div>
                @endforeach
            </div>
            @endforeach
    </div>
    <div class="nx-fhead">Subject</div>
    <div class="nx-fcell"><input type=text name=subject size=75 aria-label="Subject" class="w-full"></div>
    <div class="nx-fhead">Message</div>
    <div class="nx-fcell"><textarea name=msg cols=80 rows=15 aria-label="Message" class="w-full">{{ $body }}</textarea></div>
<div class="nx-ffull"><div class="text-center"><b>Sender:&nbsp;&nbsp;</b>
{{ $username }}
<input name="sender" type="radio" value="self" checked aria-label="{{ $username }}">
&nbsp; System
<input name="sender" type="radio" value="system" aria-label="System">
</div></div>
<div class="nx-ffull text-center"><input type="submit" class="nx-btn nx-btn--primary" value="Send!"></div>
</div>
<input type=hidden name=receiver value={{ (int) $receiver }}>
</form>

 </div>
<br />
NOTE: Do not user BB codes. (NO HTML)
</x-page>
@endsection
