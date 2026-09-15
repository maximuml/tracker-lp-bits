@extends('layouts.legacy')

@section('title', '')

@section('content')
<h1>{{ $lang_testip['head_test_ip'] ?? 'Test IP address' }}</h1>
@if (! empty($hasResult))
<div class="nx-embedded">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($message ?? ''))</div>
    @if (($banstable ?? '') !== '')
<p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($banstable))</p>
    @endif
@endif
<form method=post action=testip.php>
<div class="nx-fgrid">
<div class="nx-fhead">{{ $lang_testip['text_ip_address'] ?? 'IP address' }}</div><div class="nx-fcell"><input type=text name=ip value="{{ $ip ?? '' }}"></div>
<div class="nx-ffull nx-center"><input type=submit class=btn value='OK'></div>
</div>
</form>
@endsection
