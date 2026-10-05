@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', '')

@section('content')
<h1>{{ ('Test IP address')}}</h1>
@if (! empty($hasResult))
<div class="nx-embedded">The IP address <b>{{ $ip }}</b> is {{ $isBanned ? '' : 'not ' }}banned{{ $isBanned ? ':' : '.' }}</div>
    @if ($isBanned)
<p><x-data-table caption="Test IP address" captionHidden class="main"><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold" scope="col">First</th><th class="bg-nxm-surface-alt font-semibold" scope="col">Last</th><th class="bg-nxm-surface-alt font-semibold" scope="col">Comment</th></tr></thead></x-slot:head>
@foreach ($banRows as $row)
<tr><td>{{ $row['first'] }}</td><td>{{ $row['last'] }}</td><td>{{ $row['comment'] }}</td></tr>
@endforeach
</x-data-table>
</p>
    @endif
@endif
<form method=post action=testip.php>
<div class="nx-fgrid">
<div class="nx-fhead">{{ ('IP address')}}</div><div class="nx-fcell"><input type=text name=ip value="{{ $ip ?? '' }}"></div>
<div class="nx-ffull text-center"><input type=submit class=btn value='OK'></div>
</div>
</form>
@endsection
