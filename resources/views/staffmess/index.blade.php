@extends('layouts.legacy')

@section('title', "Mass PM")

@section('content')
<div class="nx-main nx-embedded nx-box--737">
<div align=center>
<h1>Mass PM to all Staff members and users:</h1>
<form method=post action="takestaffmess.php">
@csrf
@if ($showReturnto)
    <input type=hidden name=returnto value="{{ $returnto }}">
@endif
<div class="nx-fgrid nx-fgrid--flat">
@if ($sent === 1)
<div class="nx-ffull"><span class="nx-color-red"><b>The message has ben sent.</b></span></div>
@endif
    <div class="nx-fcell"><b>Send to class:</b></div>
    <div class="nx-fcell">
            @foreach ($classes as $chunk)
            <div class="nx-row">
                @foreach ($chunk as $class => $info)
                <div class="nx-fcell"><label><input type="checkbox" name="classes[]" value="{{ (int) $class }}" />{{ $info['text'] ?? '' }}</label></div>
                @endforeach
            </div>
            @endforeach
    </div>
    <div class="nx-fhead">Subject</div>
    <div class="nx-fcell"><input type=text name=subject size=75></div>
    <div class="nx-fhead">Message</div>
    <div class="nx-fcell"><textarea name=msg cols=80 rows=15>{{ $body }}</textarea></div>
<div class="nx-ffull"><div align="center"><b>Sender:&nbsp;&nbsp;</b>
{{ $username }}
<input name="sender" type="radio" value="self" checked>
&nbsp; System
<input name="sender" type="radio" value="system">
</div></div>
<div class="nx-ffull nx-center"><input type=submit value="Send!" class=btn></div>
</div>
<input type=hidden name=receiver value={{ (int) $receiver }}>
</form>

 </div></div>
<br />
NOTE: Do not user BB codes. (NO HTML)
@endsection
