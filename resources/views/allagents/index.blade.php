@extends('layouts.legacy_torrents')

@section('title', 'All Clients')

@section('content')
<table align="center" border="3" cellspacing="0" cellpadding="5">
    <tr><td class="colhead">Client</td><td class="colhead">Counts</td></tr>
    @foreach ($agents as $row)
        <tr><td align="left">{{ ((array) $row)['agent'] ?? '' }}</td><td align="left">{{ ((array) $row)['counts'] ?? '' }}</td></tr>
    @endforeach
</table>
@endsection
