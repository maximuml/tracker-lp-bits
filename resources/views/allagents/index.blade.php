@extends('layouts.legacy_torrents')

@section('title', 'All Clients')

@section('content')
@php
if (\App\Support\UserDisplay::currentClass() < UC_MODERATOR) {
    \App\Support\LegacyResponse::abort('Error', 'Permission denied.');
}
$agents = \Nexus\Database\NexusDB::table('peers')
    ->selectRaw('agent, count(*) as counts')
    ->groupBy('agent')
    ->orderBy('agent')
    ->get();
@endphp

<table align="center" border="3" cellspacing="0" cellpadding="5">
    <tr><td class="colhead">Client</td><td class="colhead">Counts</td></tr>
    @foreach ($agents as $row)
        @php $arr2 = (array) $row; @endphp
        <tr><td align="left">{{ $arr2['agent'] }}</td><td align="left">{{ $arr2['counts'] }}</td></tr>
    @endforeach
</table>
@endsection
