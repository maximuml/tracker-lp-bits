@extends('layouts.legacy')

@section('title', '')

@section('content')
<h1>{{ ('Test IP address')}}</h1>
@if (! empty($hasResult))
<div class="nx-embedded">{{ $message ?? '' }}</div>
    @if (($banstable ?? '') !== '')
<p>{{ $banstable }}</p>
    @endif
@endif
<form method=post action=testip.php>
<div class="nx-fgrid">
<div class="nx-fhead">{{ ('IP address')}}</div><div class="nx-fcell"><input type=text name=ip value="{{ $ip ?? '' }}"></div>
<div class="nx-ffull nx-center"><input type=submit class=btn value='OK'></div>
</div>
</form>
@endsection
