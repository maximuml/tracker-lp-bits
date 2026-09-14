@extends('layouts.legacy')

@section('title', '')

@section('content')
<h1>{{ $lang_testip['head_test_ip'] ?? 'Test IP address' }}</h1>
@if (! empty($hasResult))
<table border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($message ?? ''))</td></tr></table>
    @if (($banstable ?? '') !== '')
<p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($banstable))</p>
    @endif
@endif
<form method=post action=testip.php>
<table border=1 cellspacing=0 cellpadding=5>
<tr><td class=rowhead>{{ $lang_testip['text_ip_address'] ?? 'IP address' }}</td><td><input type=text name=ip value="{{ $ip ?? '' }}"></td></tr>
<tr><td colspan=2 align=center><input type=submit class=btn value='OK'></td></tr>
</form>
</table>
@endsection
