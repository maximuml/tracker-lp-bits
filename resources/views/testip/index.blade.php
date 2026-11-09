@extends('layouts.app')

@section('title', '')

@section('content')
<x-page :title="'Test IP address'" width="form">
@if (! empty($hasResult))
<div class="nx-embedded">The IP address <b>{{ $ip }}</b> is {{ $isBanned ? '' : 'not ' }}banned{{ $isBanned ? ':' : '.' }}</div>
    @if ($isBanned)
<p><x-data-table caption="Test IP address" captionHidden class="nx-main"><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold" scope="col">First</th><th class="bg-nxm-surface-alt font-semibold" scope="col">Last</th><th class="bg-nxm-surface-alt font-semibold" scope="col">Comment</th></tr></thead></x-slot:head>
@foreach ($banRows as $row)
<tr><td>{{ $row['first'] }}</td><td>{{ $row['last'] }}</td><td>{{ $row['comment'] }}</td></tr>
@endforeach
</x-data-table>
</p>
    @endif
@endif
<form method=post action="/web/system/test-ip">@csrf
<div class="nx-fgrid">
<label class="nx-fhead" for="testip-ip">{{ ('IP address')}}</label><div class="nx-fcell"><input id="testip-ip" type=text name=ip value="{{ $ip ?? '' }}"></div>
<div class="nx-ffull text-center"><input type="submit" class="nx-btn nx-btn--primary" value="OK"></div>
</div>
</form>
</x-page>
@endsection
