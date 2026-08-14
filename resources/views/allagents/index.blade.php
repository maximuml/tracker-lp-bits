@extends('layouts.legacy_torrents')

@section('title', 'All Clients')

@section('content')
<table align="center" border="3" cellspacing="0" cellpadding="5">
    <tr><td class="colhead">Client</td><td class="colhead">Counts</td></tr>
    @foreach ($agents as $row)
        @php $arr2 = (array) $row; @endphp
        <tr><td align="left">{{ $arr2['agent'] }}</td><td align="left">{{ $arr2['counts'] }}</td></tr>
    @endforeach
</table>
@endsection
