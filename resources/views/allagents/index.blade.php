@extends('layouts.legacy_bare')

@section('title', 'All Clients')

@section('content')
<table data-nx="data" align="center" border="3" cellspacing="0" cellpadding="5">
    <tr><th class="colhead" scope="col">Client</th><th class="colhead" scope="col">Counts</th></tr>
    @foreach ($agents as $row)
        <tr><td align="left">{{ ((array) $row)['agent'] ?? '' }}</td><td align="left">{{ ((array) $row)['counts'] ?? '' }}</td></tr>
    @endforeach
</table>
@endsection
