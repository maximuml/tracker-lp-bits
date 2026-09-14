@extends('layouts.legacy')

@section('title', "Mass PM")

@section('content')
<table class=main width=737 border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
<div align=center>
<h1>Mass PM to all Staff members and users:</h1>
<form method=post action="takestaffmess.php">
@csrf
@if ($showReturnto)
    <input type=hidden name=returnto value="@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($returnto))">
@endif
<table cellspacing=0 cellpadding=5>
@if ($sent === 1)
<tr><td colspan=2><font color=red><b>The message has ben sent.</b></font></td></tr>
@endif
<tr>
    <td><b>Send to class:</b></td>
    <td>
        <table style="border: 0" width="100%" cellpadding="0" cellspacing="0">
            @foreach ($classes as $chunk)
            <tr>
                @foreach ($chunk as $class => $info)
                <td style="border: 0"><label><input type="checkbox" name="classes[]" value="{{ (int) $class }}" />{{ $info['text'] ?? '' }}</label></td>
                @endforeach
            </tr>
            @endforeach
        </table>
    </td>
</tr>
<tr>
    <td class="rowhead">Subject</td>
    <td> <input type=text name=subject size=75></td>
</tr>
<tr>
    <td class="rowhead">Message</td>
    <td><textarea name=msg cols=80 rows=15>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($body))</textarea></td>
</tr>
<tr>
<td colspan=2><div align="center"><b>Sender:&nbsp;&nbsp;</b>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($username))
<input name="sender" type="radio" value="self" checked>
&nbsp; System
<input name="sender" type="radio" value="system">
</div></td></tr>
<tr><td colspan=2 align=center><input type=submit value="Send!" class=btn></td></tr>
</table>
<input type=hidden name=receiver value={{ (int) $receiver }}>
</form>

 </div></td></tr></table>
<br />
NOTE: Do not user BB codes. (NO HTML)
@endsection
